<?php
// api/predict.php — Prediction service bridge between MySQL & Python ML
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

// EVERYTHING must be wrapped inside this function!
function getStudentPrediction(int $studentId, PDO $db): array {
    // 0. Security Check
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$studentId]);
    $role = $stmtRole->fetchColumn();

    if ($role !== 'student') {
        return ['error' => "Invalid target: ID $studentId belongs to a $role. Predictions are for students only."];
    }

    // 1. Calculate Historical GWA using official Unit-Weighted formula
    $stmtHist = $db->prepare("
        SELECT g.final_grade, s.units 
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ? AND g.is_current = 0 AND g.final_grade IS NOT NULL
    ");
    $stmtHist->execute([$studentId]);
    $historical_rows = array_map(
        // FIXED: Do not cast to float here! Preserves "INC", "DO", "DU" so computeWeightedGWA can filter them out safely
        fn($r) => ['grade' => $r['final_grade'], 'units' => (int) $r['units']],
        $stmtHist->fetchAll()
    );
    $historicalGwa = computeWeightedGWA($historical_rows); 

    // 2. Calculate Current Prelim GWA using official Unit-Weighted formula
    $stmtCurr = $db->prepare("
        SELECT g.prelim, s.units 
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ? AND g.is_current = 1 AND g.prelim IS NOT NULL
    ");
    $stmtCurr->execute([$studentId]);
    $currRows = $stmtCurr->fetchAll();
    
    $prelim_rows = [];
    foreach($currRows as $r) {
        $pt = normalizeTermGrade($r['prelim']);
        if ($pt !== null) {
            $prelim_rows[] = ['grade' => $pt, 'units' => (int)$r['units']];
        }
    }
    $currentPrelimAvg = computeWeightedGWA($prelim_rows); 

    // FIXED: Insufficient Data Failsafe
    // Prevents Python from receiving zeros and hallucinating a 0.00 / HIGH Risk prediction
    if ($historicalGwa === null && $currentPrelimAvg === null) {
        return [
            'error' => 'Insufficient academic data to generate a reliable prediction.',
            'predicted_gwa' => null,
            'risk_level' => null,
            'latin_honor' => null,
            'prediction_source' => 'none'
        ];
    }

    $histGwaVal = $historicalGwa ?? 0.0;
    $prelimAvgVal = $currentPrelimAvg ?? 0.0;

    // 3. Count Failed Subjects & Irregular Semesters
    $stmtPast = $db->prepare("
        SELECT school_year, semester, final_grade 
        FROM grades 
        WHERE student_id = ? AND is_current = 0 AND final_grade IS NOT NULL
    ");
    $stmtPast->execute([$studentId]);
    $pastRecords = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

    $failedCount = 0;
    $failedSemesters = []; 

    foreach ($pastRecords as $row) {
        $gStr = strtoupper(trim((string)$row['final_grade']));
        // FIXED: Count textual statuses AND explicitly failing numerical grades (< 1.75)
        if (in_array($gStr, ['0', '0.00', 'INC', 'DO', 'DU', 'FA', 'UD']) || (is_numeric($gStr) && (float)$gStr > 0 && (float)$gStr < 1.75)) {
            $failedCount++;
            $failedSemesters[] = $row['school_year'] . '_' . $row['semester'];
        }
    }
    $irregularSemesters = count(array_unique($failedSemesters));

    $hasDisqGrade = hasDisqualifyingGrade($studentId, $db);

    $payload = [
        'historical_gwa'           => $histGwaVal,
        'current_prelim_point_avg' => $prelimAvgVal, // FIXED: Synced with Python contract
        'failed_subjects_count'    => $failedCount,
        'irregular_semesters'      => $irregularSemesters
    ];

    // 4. Mathematical Base
    $predGwa = predictFinalGradeHeuristic($currentPrelimAvg, $historicalGwa) ?? 0.0;
    $predictionSource = 'calculation_fallback'; // FIXED: Standardized label

    // 5. Call Flask Python Microservice safely
    $pythonUrl = defined('PYTHON_ML_API_URL') ? PYTHON_ML_API_URL : 'http://127.0.0.1:5000/predict';
    $ch = curl_init($pythonUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // FIXED: Split timeouts to prevent indefinite hanging
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); 

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // FIXED: Safe error logging for failed Python connections
    if ($curlErr) {
        error_log("ML API cURL Error for Student $studentId: " . $curlErr);
    } elseif ($httpCode === 200 && $response) {
        $mlResult = json_decode($response, true);
        if ($mlResult && isset($mlResult['predicted_gwa']) && is_numeric($mlResult['predicted_gwa'])) {
            $predGwa = max(1.00, min(4.00, (float) $mlResult['predicted_gwa']));
            $predictionSource = ($mlResult['source'] ?? '') === 'decision_tree' ? 'decision_tree' : 'calculation_fallback';
        } elseif (!$mlResult) {
            error_log("ML API Invalid JSON Response for Student $studentId: " . $response);
        }
    } else {
        error_log("ML API Error $httpCode for Student $studentId: " . $response);
    }

    // 6. Everything else is derived from predGwa
    $risk = computeRiskFromAvg($predGwa);
    $latinHonor = getLatinHonor($predGwa, $hasDisqGrade);

    // 7. Persist to predictions Table
    // FIXED: Removed irregular_prob calculation/insertion
    $stmtSave = $db->prepare("
        INSERT INTO predictions (student_id, predicted_gwa, risk_level, latin_honor, irregular_prob, prediction_source)
        VALUES (?, ?, ?, ?, NULL, ?)
    ");
    $stmtSave->execute([
        $studentId,
        $predGwa,
        $risk,
        $latinHonor,
        $predictionSource
    ]);

    return [
        'predicted_gwa'     => $predGwa,
        'risk_level'        => $risk,
        'latin_honor'       => $latinHonor,
        'prediction_source' => $predictionSource,
        'features_used'     => $payload
    ];
}

// FIXED: Protected Direct Execution Wrapper
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../includes/auth.php'; 
    
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        exit(json_encode(['error' => 'Unauthorized. Please log in.']));
    }

    $targetStudentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
    if (!$targetStudentId) {
        http_response_code(400);
        exit(json_encode(['error' => 'Valid student_id required']));
    }

    $db = getDB();
    $role = $_SESSION['role'] ?? '';
    $userId = $_SESSION['user_id'];

    if ($role === 'student' && $userId != $targetStudentId) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden. Students can only predict their own grades.']));
    }

    if ($role === 'faculty') {
        $stmtCheck = $db->prepare("
            SELECT 1 FROM student_profiles sp
            JOIN faculty_class_loads fcl ON sp.section = fcl.section
            WHERE sp.user_id = ? AND fcl.faculty_user_id = ?
        ");
        $stmtCheck->execute([$targetStudentId, $userId]);
        if (!$stmtCheck->fetchColumn()) {
            http_response_code(403);
            exit(json_encode(['error' => 'Forbidden. Student is not in your assigned class loads.']));
        }
    }

    echo json_encode(getStudentPrediction($targetStudentId, $db));
}
?>