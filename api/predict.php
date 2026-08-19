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
        fn($r) => ['grade' => (float) $r['final_grade'], 'units' => (int) $r['units']],
        $stmtHist->fetchAll()
    );
    $historicalGwa = computeWeightedGWA($historical_rows) ?? 0.0;

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
    $currentPrelimAvg = computeWeightedGWA($prelim_rows) ?? 0.0;

    // 3. Count Failed Subjects, Irregular Semesters, & Latin Honor Eligibility
    $stmtPast = $db->prepare("
        SELECT school_year, semester, final_grade 
        FROM grades 
        WHERE student_id = ? AND is_current = 0 AND final_grade IS NOT NULL
    ");
    $stmtPast->execute([$studentId]);
    $pastRecords = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

    $failedCount = 0;
    $failedSemesters = []; 
    $hasDisqualifyingGrade = false;

    foreach ($pastRecords as $row) {
        $gStr = strtoupper(trim((string)$row['final_grade']));
        
        if (in_array($gStr, ['0', '0.00', 'INC', 'DO', 'DU', 'FA', 'UD'])) {
            $failedCount++;
            $failedSemesters[] = $row['school_year'] . '_' . $row['semester'];
            $hasDisqualifyingGrade = true;
        } 
        elseif (is_numeric($gStr)) {
            $val = (float)$gStr;
            if ($val < 1.75 && $val > 0) {
                $hasDisqualifyingGrade = true;
            }
        }
    }
    $irregularSemesters = count(array_unique($failedSemesters));

    $payload = [
        'historical_gwa'        => $historicalGwa,
        'current_prelim_avg'    => $currentPrelimAvg,
        'failed_subjects_count' => $failedCount,
        'irregular_semesters'   => $irregularSemesters
    ];

    // 4. Mathematical Base (Always calculate this regardless of AI status)
    $predGwa = predictFinalGradeHeuristic(
        $currentPrelimAvg > 0 ? $currentPrelimAvg : null, 
        $historicalGwa > 0 ? $historicalGwa : null
    ) ?? 0.0;
    
    $latinHonor = getLatinHonor($predGwa, $hasDisqualifyingGrade);
    
    // Default Heuristics (will be overwritten if AI is online)
    $risk = computeRiskFromAvg($predGwa);
    $irregularProb = $risk === 'HIGH' ? 70.0 : ($risk === 'MODERATE' ? 30.0 : 5.0);
    $predictionSource = 'heuristic';

    // 5. Call Flask Python Microservice via cURL to get AI Overrides
    $ch = curl_init('http://127.0.0.1:5000/predict');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $mlResult = json_decode($response, true);
        if ($mlResult && isset($mlResult['risk_level'])) {
            // Override heuristics with ML Intelligence
            $risk = $mlResult['risk_level'];
            $irregularProb = $mlResult['irregular_prob'] ?? $irregularProb;
            $predictionSource = 'decision_tree';
        }
    }

    // 6. Persist to predictions Table
    $stmtSave = $db->prepare("
        INSERT INTO predictions (student_id, predicted_gwa, risk_level, latin_honor, irregular_prob, prediction_source)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtSave->execute([
        $studentId,
        $predGwa,
        $risk,
        $latinHonor,
        $irregularProb,
        $predictionSource
    ]);

    $finalResult = [
        'predicted_gwa'     => $predGwa,
        'risk_level'        => $risk,
        'latin_honor'       => $latinHonor,
        'irregular_prob'    => $irregularProb,
        'prediction_source' => $predictionSource,
        'features_used'     => $payload
    ];
    
    return $finalResult;
}

// Allow direct execution via API calls (Safely isolated at the very bottom)
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    $studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
    if (!$studentId) {
        echo json_encode(['error' => 'Valid student_id required']);
        exit;
    }
    $db = getDB();
    echo json_encode(getStudentPrediction($studentId, $db));
}