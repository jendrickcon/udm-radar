<?php
/**
 * Integration Test: Partial-Data Prediction Policy & Completeness Metadata (WP-7)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies the 4-Case Policy in api/predict.php:
 * 1. Case A (Complete):
 *    - Both historical GWA and current prelim average available.
 *    - Returns status 'complete', data_completeness 'complete', is_partial false.
 *    - Uses ML Decision Tree or reconciled 50/50 fallback blend.
 * 2. Case B (Historical GWA only):
 *    - Historical GWA available, current prelim null.
 *    - Returns status 'partial_provisional', data_completeness 'historical_only', is_partial true.
 *    - Sets provisional_basis 'historical_gwa', never injects artificial 0.0.
 * 3. Case C (Current Prelim only):
 *    - Current prelim available, historical GWA null.
 *    - Returns status 'partial_provisional', data_completeness 'prelim_only', is_partial true.
 *    - Sets provisional_basis 'current_prelim_avg', never injects artificial 0.0.
 * 4. Case D (Missing Both):
 *    - Both historical GWA and current prelim null.
 *    - Returns status 'insufficient_data', data_completeness 'missing_all', is_partial true.
 *    - Strictly NULL predicted_gwa and risk_level.
 *    - ZERO rows written to predictions table.
 * 5. Persistence control:
 *    - $persist = false generates prediction in-memory without database writes.
 *
 * Run via CLI: php tests/integration/partial_data_policy_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/api/predict.php';

$scratchHost = '127.0.0.1';
$scratchDb   = 'udm_radar_scratch';
$scratchUser = 'root';
$scratchPass = '';

try {
    $db = new PDO("mysql:host=$scratchHost;dbname=$scratchDb;charset=utf8mb4", $scratchUser, $scratchPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not connect to scratch database '$scratchDb': " . $e->getMessage() . "\n");
    exit(1);
}

// Safety guard: ensure we are not connected to production database
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', not 'udm_radar_scratch'! Refusing to run tests.\n");
    exit(1);
}

$testsRun = 0;
$failures = 0;

function assertCondition(bool $cond, string $msg): void {
    global $testsRun, $failures;
    $testsRun++;
    if ($cond) {
        echo "[PASS] $msg\n";
    } else {
        $failures++;
        echo "[FAIL] $msg\n";
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: PARTIAL-DATA PREDICTION POLICY TESTS (WP-7)\n";
echo "Database: $scratchDb\n";
echo "========================================================================\n\n";

// Pick an active student for controlled transaction testing
$testStudent = $db->query("
    SELECT u.id, sp.course, sp.section
    FROM users u
    JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.role = 'student' AND u.is_active = 1
    LIMIT 1
")->fetch();

if (!$testStudent) {
    fwrite(STDERR, "FATAL: No active student found in scratch database.\n");
    exit(1);
}

$studentId = (int) $testStudent['id'];

// Get current term
$term = getCurrentTerm();
$currentSy = $term['school_year'];
$currentSem = (int) $term['semester'];

// Find a current subject
$subjectId = (int) $db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();

// We run all tests inside a rolled-back transaction so the database remains unchanged
$db->beginTransaction();

try {
    echo "=== 1. Case D: Missing Both Features (Failsafe) ===\n";
    // Clear student's current grades and past grades temporarily
    $db->prepare("DELETE FROM grades WHERE student_id = ?")->execute([$studentId]);
    $db->prepare("UPDATE student_profiles SET current_gwa = NULL, predicted_gwa = NULL WHERE user_id = ?")->execute([$studentId]);

    $initialPredCount = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE student_id = $studentId")->fetchColumn();

    $resD = getStudentPrediction($studentId, $db, true);

    assertCondition($resD['status'] === 'insufficient_data', "Case D returns status 'insufficient_data'");
    assertCondition($resD['data_completeness'] === 'missing_all', "Case D data_completeness is 'missing_all'");
    assertCondition($resD['is_partial'] === true, "Case D is_partial flag is true");
    assertCondition($resD['has_historical_gwa'] === false, "Case D has_historical_gwa is false");
    assertCondition($resD['has_current_prelim'] === false, "Case D has_current_prelim is false");
    assertCondition($resD['predicted_gwa'] === null, "Case D predicted_gwa is strictly null");
    assertCondition($resD['risk_level'] === null, "Case D risk_level is strictly null");
    assertCondition($resD['prediction_source'] === 'none', "Case D prediction_source is 'none'");

    $postPredCountD = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE student_id = $studentId")->fetchColumn();
    assertCondition($postPredCountD === $initialPredCount, "Case D writes ZERO rows to predictions table");


    echo "\n=== 2. Case B: Historical GWA Only (Partial Provisional) ===\n";
    // Set a historical passed grade in a prior term
    $stmtPast = $db->prepare("
        INSERT INTO grades (student_id, subject_id, school_year, semester, final_grade, is_current)
        VALUES (?, ?, '2024-2025', 1, '2.50', 0)
    ");
    $stmtPast->execute([$studentId, $subjectId]);

    $resB = getStudentPrediction($studentId, $db, false); // in-memory first

    assertCondition($resB['status'] === 'partial_provisional', "Case B returns status 'partial_provisional'");
    assertCondition($resB['data_completeness'] === 'historical_only', "Case B data_completeness is 'historical_only'");
    assertCondition($resB['is_partial'] === true, "Case B is_partial flag is true");
    assertCondition($resB['has_historical_gwa'] === true, "Case B has_historical_gwa is true");
    assertCondition($resB['has_current_prelim'] === false, "Case B has_current_prelim is false");
    assertCondition($resB['provisional_basis'] === 'historical_gwa', "Case B provisional_basis is 'historical_gwa'");
    assertCondition(abs((float)$resB['predicted_gwa'] - 2.50) < 0.01, "Case B predicted_gwa matches historical GWA (2.50)");
    assertCondition($resB['features_used']['current_prelim_point_avg'] === null, "Case B prelim feature is null (no 0.0 injection)");
    assertCondition($resB['prediction_source'] === PREDICTION_SOURCE_CALCULATION_FALLBACK, "Case B uses calculation_fallback source");


    echo "\n=== 3. Case C: Current Prelim Average Only (Partial Provisional) ===\n";
    // Remove past grade and insert current prelim grade
    $db->prepare("DELETE FROM grades WHERE student_id = ?")->execute([$studentId]);
    $stmtCurr = $db->prepare("
        INSERT INTO grades (student_id, subject_id, school_year, semester, prelim, is_current)
        VALUES (?, ?, ?, ?, '85.00', 1)
    ");
    $stmtCurr->execute([$studentId, $subjectId, $currentSy, $currentSem]);

    $resC = getStudentPrediction($studentId, $db, false);

    assertCondition($resC['status'] === 'partial_provisional', "Case C returns status 'partial_provisional'");
    assertCondition($resC['data_completeness'] === 'prelim_only', "Case C data_completeness is 'prelim_only'");
    assertCondition($resC['is_partial'] === true, "Case C is_partial flag is true");
    assertCondition($resC['has_historical_gwa'] === false, "Case C has_historical_gwa is false");
    assertCondition($resC['has_current_prelim'] === true, "Case C has_current_prelim is true");
    assertCondition($resC['provisional_basis'] === 'current_prelim_avg', "Case C provisional_basis is 'current_prelim_avg'");
    assertCondition($resC['predicted_gwa'] !== null, "Case C produces non-null predicted_gwa");
    assertCondition($resC['features_used']['historical_gwa'] === null, "Case C historical feature is null (no 0.0 injection)");
    assertCondition($resC['prediction_source'] === PREDICTION_SOURCE_CALCULATION_FALLBACK, "Case C uses calculation_fallback source");


    echo "\n=== 4. Case A: Complete Features (Both Historical GWA and Prelim Available) ===\n";
    // Insert both past grade and current prelim
    $stmtPast->execute([$studentId, $subjectId]);

    $resA = getStudentPrediction($studentId, $db, false);

    assertCondition($resA['status'] === 'complete', "Case A returns status 'complete'");
    assertCondition($resA['data_completeness'] === 'complete', "Case A data_completeness is 'complete'");
    assertCondition($resA['is_partial'] === false, "Case A is_partial flag is false");
    assertCondition($resA['has_historical_gwa'] === true, "Case A has_historical_gwa is true");
    assertCondition($resA['has_current_prelim'] === true, "Case A has_current_prelim is true");
    assertCondition($resA['predicted_gwa'] !== null, "Case A produces valid predicted_gwa");
    assertCondition(in_array($resA['prediction_source'], [PREDICTION_SOURCE_DECISION_TREE, PREDICTION_SOURCE_CALCULATION_FALLBACK], true), "Case A produces valid canonical source");
    assertCondition($resA['features_used']['historical_gwa'] !== null, "Case A has non-null historical_gwa feature");
    assertCondition($resA['features_used']['current_prelim_point_avg'] !== null, "Case A has non-null current_prelim_point_avg feature");


    echo "\n=== 5. Persistence Control & Database Check Constraint Verification ===\n";
    // Test that when persist = true, Case B and C write valid records satisfying check constraints
    $predCountBeforePersist = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE student_id = $studentId")->fetchColumn();
    
    // Persist Case B
    $db->prepare("DELETE FROM grades WHERE student_id = ?")->execute([$studentId]);
    $stmtPast->execute([$studentId, $subjectId]);
    $persistedB = getStudentPrediction($studentId, $db, true);
    
    $predCountAfterPersistB = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE student_id = $studentId")->fetchColumn();
    assertCondition($predCountAfterPersistB === $predCountBeforePersist + 1, "Persisting Case B adds exactly 1 prediction row");

    // Persist Case A
    $stmtCurr->execute([$studentId, $subjectId, $currentSy, $currentSem]);
    $persistedA = getStudentPrediction($studentId, $db, true);
    $predCountAfterPersistA = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE student_id = $studentId")->fetchColumn();
    assertCondition($predCountAfterPersistA === $predCountAfterPersistB + 1, "Persisting Case A adds exactly 1 prediction row");

    // Compatibility alias predictStudent
    $aliasResult = predictStudent($studentId, $db, false);
    assertCondition($aliasResult['status'] === 'complete', "predictStudent alias executes getStudentPrediction correctly");

} finally {
    $db->rollBack();
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
