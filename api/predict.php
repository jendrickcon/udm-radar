<?php
// api/predict.php — Prediction service bridge between MySQL & Python ML
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

// EVERYTHING must be wrapped inside this function!
// Canonical prediction function with explicit partial data policy support
function getStudentPrediction(int $studentId, PDO $db, bool $persist = true): array {
    // 0. Security Check
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmtRole->execute([$studentId]);
    $role = $stmtRole->fetchColumn();

    if ($role !== 'student') {
        return [
            'status' => 'error',
            'error'  => "Invalid target: ID $studentId belongs to a $role. Predictions are for students only."
        ];
    }

    // 1. Calculate Historical GWA using official Unit-Weighted formula
    $historicalGwa = computeStudentGwa($db, $studentId); 

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

    // ------------------------------------------------------------------------
    // CASE D: Missing Both Features -> Strict Failsafe (No prediction generated)
    // ------------------------------------------------------------------------
    if ($historicalGwa === null && $currentPrelimAvg === null) {
        $resD = [
            'status'             => 'insufficient_data',
            'error'              => 'Insufficient academic data to generate a reliable prediction.',
            'data_completeness'  => 'missing_all',
            'is_partial'         => true,
            'has_historical_gwa' => false,
            'has_current_prelim' => false,
            'predicted_gwa'      => null,
            'risk_level'         => null,
            'latin_honor'        => null,
            'prediction_source'  => 'none',
            'features_used'      => null
        ];
        $resD['explanation'] = getPredictionExplanationMetadata($resD, 'student');
        return $resD;
    }

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
        if (isFailingFinalGrade($row['final_grade'])) {
            $failedCount++;
            $failedSemesters[] = $row['school_year'] . '_' . $row['semester'];
        }
    }
    $irregularSemesters = count(array_unique($failedSemesters));

    $hasDisqGrade = hasDisqualifyingGrade($studentId, $db);

    // ------------------------------------------------------------------------
    // CASE B: Historical GWA only (no prelim grades in current term)
    // ------------------------------------------------------------------------
    if ($historicalGwa !== null && $currentPrelimAvg === null) {
        $predGwa = round((float) $historicalGwa, 2);
        $risk = computeRiskFromAvg($predGwa);
        $latinHonor = getLatinHonor($predGwa, $hasDisqGrade);
        $predictionSource = PREDICTION_SOURCE_CALCULATION_FALLBACK;

        if ($persist) {
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
        }

        $resB = [
            'status'             => 'partial_provisional',
            'data_completeness'  => 'historical_only',
            'is_partial'         => true,
            'has_historical_gwa' => true,
            'has_current_prelim' => false,
            'provisional_basis'  => 'historical_gwa',
            'predicted_gwa'      => $predGwa,
            'risk_level'         => $risk,
            'latin_honor'        => $latinHonor,
            'prediction_source'  => $predictionSource,
            'features_used'      => [
                'historical_gwa'           => (float) $historicalGwa,
                'current_prelim_point_avg' => null,
                'failed_subjects_count'    => (int) $failedCount,
                'irregular_semesters'      => (int) $irregularSemesters,
            ]
        ];
        $resB['explanation'] = getPredictionExplanationMetadata($resB, 'student');
        return $resB;
    }

    // ------------------------------------------------------------------------
    // CASE C: Current Prelim Average only (no prior term records)
    // ------------------------------------------------------------------------
    if ($historicalGwa === null && $currentPrelimAvg !== null) {
        $predGwa = round((float) $currentPrelimAvg, 2);
        $risk = computeRiskFromAvg($predGwa);
        $latinHonor = getLatinHonor($predGwa, $hasDisqGrade);
        $predictionSource = PREDICTION_SOURCE_CALCULATION_FALLBACK;

        if ($persist) {
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
        }

        $resC = [
            'status'             => 'partial_provisional',
            'data_completeness'  => 'prelim_only',
            'is_partial'         => true,
            'has_historical_gwa' => false,
            'has_current_prelim' => true,
            'provisional_basis'  => 'current_prelim_avg',
            'predicted_gwa'      => $predGwa,
            'risk_level'         => $risk,
            'latin_honor'        => $latinHonor,
            'prediction_source'  => $predictionSource,
            'features_used'      => [
                'historical_gwa'           => null,
                'current_prelim_point_avg' => (float) $currentPrelimAvg,
                'failed_subjects_count'    => (int) $failedCount,
                'irregular_semesters'      => (int) $irregularSemesters,
            ]
        ];
        $resC['explanation'] = getPredictionExplanationMetadata($resC, 'student');
        return $resC;
    }

    // ------------------------------------------------------------------------
    // CASE A: Complete Features (Both historical GWA and prelim average present)
    // ------------------------------------------------------------------------
    $predGwa = predictFinalGradeHeuristic($currentPrelimAvg, $historicalGwa) ?? 0.0;
    $predictionSource = PREDICTION_SOURCE_CALCULATION_FALLBACK;

    $payload = [
        'historical_gwa'           => (float) $historicalGwa,
        'current_prelim_point_avg' => (float) $currentPrelimAvg,
        'failed_subjects_count'    => (int) $failedCount,
        'irregular_semesters'      => (int) $irregularSemesters,
    ];

    $pythonUrl = defined('PYTHON_ML_API_URL') ? PYTHON_ML_API_URL : 'http://127.0.0.1:5000/predict';
    $ch = curl_init($pythonUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); 

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        error_log("ML API cURL Error for Student $studentId: " . $curlErr);
    } elseif ($httpCode === 200 && $response) {
        $mlResult = json_decode($response, true);
        if ($mlResult && isset($mlResult['predicted_gwa']) && is_numeric($mlResult['predicted_gwa'])) {
            $predGwa = max(1.00, min(4.00, (float) $mlResult['predicted_gwa']));
            $normalizedSource = normalizePredictionSourceBoundary($mlResult['source'] ?? null);
            $predictionSource = $normalizedSource ?? PREDICTION_SOURCE_CALCULATION_FALLBACK;
        } elseif (!$mlResult) {
            error_log("ML API Invalid JSON Response for Student $studentId: " . $response);
        }
    } else {
        error_log("ML API Error $httpCode for Student $studentId: " . $response);
    }

    $risk = computeRiskFromAvg($predGwa);
    $latinHonor = getLatinHonor($predGwa, $hasDisqGrade);

    if ($persist) {
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
    }

    $resA = [
        'status'             => 'complete',
        'data_completeness'  => 'complete',
        'is_partial'         => false,
        'has_historical_gwa' => true,
        'has_current_prelim' => true,
        'predicted_gwa'      => $predGwa,
        'risk_level'         => $risk,
        'latin_honor'        => $latinHonor,
        'prediction_source'  => $predictionSource,
        'features_used'      => $payload
    ];
    $resA['explanation'] = getPredictionExplanationMetadata($resA, 'student');
    return $resA;
}

// Function alias for compatibility
function predictStudent(int $studentId, PDO $db, bool $persist = true): array {
    return getStudentPrediction($studentId, $db, $persist);
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

    // A faculty member may only request a prediction for a student they
    // actually teach. This is a row-level check against a real enrollment, not
    // a section-name string comparison.
    //
    // The previous version joined student_profiles.section to
    // faculty_class_loads.section on the section code alone. Section codes are
    // not unique across the curriculum — 'IT-31' identifies one cohort's 1st
    // Year block and a later cohort's 3rd Year block — so the join matched any
    // faculty member holding that code for any year level, and it would grant
    // access to an entire cohort on the strength of an unrelated class load.
    // Matching the student's own grade rows to the load's subject and section
    // restricts the check to the specific class being taught.
    if ($role === 'faculty') {
        $stmtCheck = $db->prepare("
            SELECT 1
            FROM grades g
            JOIN faculty_class_loads fcl
              ON fcl.subject_id = g.subject_id
             AND fcl.section    = (SELECT sp.section FROM student_profiles sp WHERE sp.user_id = g.student_id)
            WHERE g.student_id = ? AND fcl.faculty_user_id = ?
            LIMIT 1
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