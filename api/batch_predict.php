<?php
// api/batch_predict.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/predict.php';

/**
 * Creates an academic support case and associated subject-level referrals for a high-risk prediction.
 * Ensures referrals are only created for subjects with encoded, non-null numeric prelim grades,
 * and prevents the creation of orphan parent cases when no valid subject grades exist.
 *
 * @param PDO $db
 * @param int $studentId
 * @param int $predictionId
 * @param array $result Prediction output containing risk_level, predicted_gwa, prediction_source
 * @param string $currentSy School year (e.g. '2025-2026')
 * @param int|string $currentSem Semester (e.g. 1 or 2)
 * @return int Number of cases created (1 on success, 0 if skipped/no valid loads)
 */
function createSupportCaseWithReferrals(PDO $db, int $studentId, int $predictionId, array $result, string $currentSy, $currentSem): int {
    $stmtCheck = $db->prepare("SELECT id FROM academic_support_cases WHERE student_id = ? AND school_year = ? AND semester = ?");
    $stmtCheck->execute([$studentId, $currentSy, $currentSem]);
    $existingCase = $stmtCheck->fetchColumn();

    if ($existingCase || !$predictionId) {
        return 0;
    }

    // Query assigned subject loads with encoded prelim grades
    $stmtLoads = $db->prepare("
        SELECT g.subject_id, sp.section, fcl.faculty_user_id, g.prelim
        FROM grades g
        JOIN student_profiles sp ON sp.user_id = g.student_id
        JOIN faculty_class_loads fcl ON fcl.subject_id = g.subject_id AND fcl.section = sp.section
        WHERE g.student_id = ? AND g.is_current = 1 AND g.school_year = ? AND g.semester = ?
          AND g.prelim IS NOT NULL
    ");
    $stmtLoads->execute([$studentId, $currentSy, $currentSem]);
    $loads = $stmtLoads->fetchAll();

    // Filter to ensure prelim is strictly non-null and numeric (distinguishing genuine 0 / 0.00 from missing data)
    $validLoads = array_filter($loads, function($ld) {
        return $ld['prelim'] !== null && is_numeric($ld['prelim']);
    });

    // Only create a parent support case if there is at least one subject with an encoded prelim
    if (empty($validLoads)) {
        return 0;
    }

    $db->beginTransaction();
    try {
        $stmtCreateCase = $db->prepare("
            INSERT INTO academic_support_cases
            (student_id, prediction_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'needs_review')
        ");
        $stmtCreateCase->execute([
            $studentId,
            $predictionId,
            $result['risk_level'],
            $result['predicted_gwa'],
            $result['prediction_source'],
            $currentSy,
            $currentSem
        ]);
        $newCaseId = (int)$db->lastInsertId();

        $stmtInsertRef = $db->prepare("
            INSERT IGNORE INTO support_case_referrals
            (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status)
            VALUES (?, ?, ?, ?, ?, 'prelim', ?, 'needs_review')
        ");

        $referralsCreatedForCase = 0;
        foreach ($validLoads as $ld) {
            $prelimVal = (float)$ld['prelim'];
            $subjRisk = $prelimVal < 75.0 ? 'HIGH' : ($prelimVal < 82.0 ? 'MODERATE' : 'LOW');
            $stmtInsertRef->execute([
                $newCaseId,
                $ld['faculty_user_id'],
                $ld['subject_id'],
                $ld['section'],
                $subjRisk,
                $prelimVal
            ]);
            if ($stmtInsertRef->rowCount() > 0) {
                $referralsCreatedForCase++;
            }
        }

        if ($referralsCreatedForCase > 0) {
            $db->commit();
            return 1;
        } else {
            $db->rollBack();
            return 0;
        }
    } catch (Exception $caseEx) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $caseEx;
    }
}

// If included as a function library (e.g. by integration tests), skip the HTTP endpoint execution
if (defined('BATCH_PREDICT_LIB_ONLY')) {
    return;
}

// FIXED: Explicitly enforce admin role and POST method
requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['status' => 'error', 'error' => 'Method Not Allowed. Must be POST.']));
}

// Enforce CSRF Validation for AJAX calls via centralized helper
$rawInput = file_get_contents('php://input');
if (($rawInput === '' || $rawInput === false) && php_sapi_name() === 'cli') {
    $rawInput = @file_get_contents('php://stdin');
}
$input = json_decode($rawInput ?: '', true) ?? [];
$submittedToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

if (!validateCsrfToken($submittedToken)) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'error' => 'CSRF validation failed.']));
}

// FIXED: Concurrent Job Locking
$lockFile = sys_get_temp_dir() . '/udm_radar_batch.lock';

if (file_exists($lockFile)) {
    if (time() - filemtime($lockFile) > 900) {
        unlink($lockFile); // Clear stale lock > 15 minutes
    } else {
        http_response_code(429);
        exit(json_encode(['status' => 'error', 'error' => 'A batch prediction is already running. Please wait.']));
    }
}

file_put_contents($lockFile, time());

register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

// FIXED: Replaced unsafe 0 with a practical 5-minute timeout
set_time_limit(300);

try {
    $db = getDB();

    $term = getCurrentTerm();
    $currentSy = $term['school_year'];
    $currentSem = $term['semester'];

    // FIXED: Exclude archived and inactive students
    $stmt = $db->query("
        SELECT u.id FROM users u
        JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.role = 'student' AND sp.record_status = 'Active' AND u.is_active = 1
    ");
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $successCount = 0;
    $failCount = 0;
    $casesCreated = 0;

    foreach ($students as $studentId) {
        $result = getStudentPrediction((int)$studentId, $db);

        if (isset($result['error'])) {
            $failCount++;
            // Silently log missing data errors to server instead of failing the whole batch
            error_log("Batch Predict Notice for Student $studentId: " . $result['error']);
        } else {
            $successCount++;

            // Only verified complete predictions (not partial provisional estimates) trigger new support cases
            if ($result['risk_level'] === 'HIGH' && empty($result['is_partial'])) {
                $stmtPredId = $db->prepare("SELECT id FROM predictions WHERE student_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1");
                $stmtPredId->execute([$studentId]);
                $predictionId = (int)$stmtPredId->fetchColumn();

                if ($predictionId > 0) {
                    $casesCreated += createSupportCaseWithReferrals($db, (int)$studentId, $predictionId, $result, (string)$currentSy, $currentSem);
                }
            }
        }
    }

    // FIXED: Return rich counters in the JSON payload
    echo json_encode([
        'status' => 'success',
        'message' => "System-Wide AI Analysis complete. Triggered $casesCreated new academic support cases for review.",
        'metrics' => [
            'total_processed' => count($students),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'cases_created' => $casesCreated
        ]
    ]);

} catch (Exception $e) {
    // FIXED: Server-side logging only to prevent info leakage
    error_log("Batch Prediction Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'An internal server error occurred while processing the batch.']);
}
?>