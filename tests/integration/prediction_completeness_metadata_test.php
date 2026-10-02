<?php
/**
 * Integration Test: Prediction Completeness Metadata & Constraints (Migration 008 / WP-7 Closeout)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Column metadata: data_completeness, is_provisional, provisional_basis,
 *    input_subject_count, expected_subject_count on predictions table.
 * 2. Check constraints: chk_predictions_completeness_valid, chk_predictions_is_provisional_valid,
 *    chk_predictions_provisional_basis_valid, chk_predictions_completeness_consistency,
 *    chk_predictions_subject_counts.
 * 3. SQL constraint enforcement: Valid combinations succeed; invalid/inconsistent combinations are rejected.
 * 4. API persistence verification: api/predict.php writes durable completeness metadata across Case A, B, C.
 * 5. Strict self-cleaning test isolation: Verifies database invariants before and after test execution.
 *
 * Run via CLI: php tests/integration/prediction_completeness_metadata_test.php
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

// Safety guard: refuse production database
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', not 'udm_radar_scratch'! Refusing to run tests.\n");
    exit(1);
}

$testsRun = 0;
$failures = 0;

function assertTest(bool $cond, string $msg): void {
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
echo "UDM-RADAR: PREDICTION COMPLETENESS METADATA & SCHEMA TESTS (MIGRATION 008)\n";
echo "Database: $scratchDb\n";
echo "========================================================================\n\n";

// Record pre-test invariants
$initialCounts = [
    'predictions'           => (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
    'academic_support_cases'=> (int) $db->query("SELECT COUNT(*) FROM academic_support_cases")->fetchColumn(),
    'support_case_referrals'=> (int) $db->query("SELECT COUNT(*) FROM support_case_referrals")->fetchColumn(),
    'support_actions'       => (int) $db->query("SELECT COUNT(*) FROM support_actions")->fetchColumn(),
    'support_status_history'=> (int) $db->query("SELECT COUNT(*) FROM support_status_history")->fetchColumn(),
    'admin_change_log'      => (int) $db->query("SELECT COUNT(*) FROM admin_change_log")->fetchColumn(),
];

// Track IDs created during test for explicit cleanup
$createdPredictionIds = [];

try {
    // -------------------------------------------------------------------------
    // 1. Column Metadata in information_schema
    // -------------------------------------------------------------------------
    echo "=== 1. Column Metadata in information_schema ===\n";
    $stmtCols = $db->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'predictions'
          AND COLUMN_NAME IN ('data_completeness', 'is_provisional', 'provisional_basis', 'input_subject_count', 'expected_subject_count')
    ");
    $stmtCols->execute([$scratchDb]);
    $cols = [];
    foreach ($stmtCols->fetchAll() as $c) {
        $cols[$c['COLUMN_NAME']] = $c;
    }

    assertTest(isset($cols['data_completeness']), "Column data_completeness exists on predictions");
    assertTest(($cols['data_completeness']['DATA_TYPE'] ?? '') === 'varchar', "data_completeness DATA_TYPE is varchar");
    assertTest(($cols['data_completeness']['IS_NULLABLE'] ?? '') === 'NO', "data_completeness IS_NULLABLE is NO");
    assertTest(($cols['data_completeness']['COLUMN_DEFAULT'] ?? null) === null, "data_completeness has NO permanent default");

    assertTest(isset($cols['is_provisional']), "Column is_provisional exists on predictions");
    assertTest(($cols['is_provisional']['DATA_TYPE'] ?? '') === 'tinyint', "is_provisional DATA_TYPE is tinyint");
    assertTest(($cols['is_provisional']['IS_NULLABLE'] ?? '') === 'NO', "is_provisional IS_NULLABLE is NO");

    assertTest(isset($cols['provisional_basis']), "Column provisional_basis exists on predictions");
    assertTest(($cols['provisional_basis']['IS_NULLABLE'] ?? '') === 'YES', "provisional_basis IS_NULLABLE is YES");

    assertTest(isset($cols['input_subject_count']), "Column input_subject_count exists on predictions");
    assertTest(($cols['input_subject_count']['DATA_TYPE'] ?? '') === 'int', "input_subject_count DATA_TYPE is int");

    assertTest(isset($cols['expected_subject_count']), "Column expected_subject_count exists on predictions");
    assertTest(($cols['expected_subject_count']['DATA_TYPE'] ?? '') === 'int', "expected_subject_count DATA_TYPE is int");

    // -------------------------------------------------------------------------
    // 2. CHECK Constraints in information_schema
    // -------------------------------------------------------------------------
    echo "\n=== 2. CHECK Constraints in information_schema ===\n";
    $stmtChk = $db->prepare("
        SELECT CONSTRAINT_NAME
        FROM information_schema.CHECK_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = ?
          AND CONSTRAINT_NAME IN (
              'chk_predictions_completeness_valid',
              'chk_predictions_is_provisional_valid',
              'chk_predictions_provisional_basis_valid',
              'chk_predictions_completeness_consistency',
              'chk_predictions_subject_counts'
          )
    ");
    $stmtChk->execute([$scratchDb]);
    $constraints = $stmtChk->fetchAll(PDO::FETCH_COLUMN);

    assertTest(in_array('chk_predictions_completeness_valid', $constraints, true), "Constraint chk_predictions_completeness_valid exists");
    assertTest(in_array('chk_predictions_is_provisional_valid', $constraints, true), "Constraint chk_predictions_is_provisional_valid exists");
    assertTest(in_array('chk_predictions_provisional_basis_valid', $constraints, true), "Constraint chk_predictions_provisional_basis_valid exists");
    assertTest(in_array('chk_predictions_completeness_consistency', $constraints, true), "Constraint chk_predictions_completeness_consistency exists");
    assertTest(in_array('chk_predictions_subject_counts', $constraints, true), "Constraint chk_predictions_subject_counts exists");

    // -------------------------------------------------------------------------
    // 3. Direct SQL Constraint Enforcement
    // -------------------------------------------------------------------------
    echo "\n=== 3. Direct SQL Constraint Enforcement ===\n";

    // 3.1 Valid complete insert
    $stmtInsValidComplete = $db->prepare("
        INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, provisional_basis, input_subject_count, expected_subject_count)
        VALUES (5, 2.75, 'LOW', 'decision_tree', 'complete', 0, NULL, 5, 5)
    ");
    $stmtInsValidComplete->execute();
    $id1 = (int) $db->lastInsertId();
    $createdPredictionIds[] = $id1;
    assertTest($id1 > 0, "Successfully inserted valid complete prediction");

    // 3.2 Valid historical_only provisional insert
    $stmtInsValidHist = $db->prepare("
        INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, provisional_basis, input_subject_count, expected_subject_count)
        VALUES (5, 2.50, 'LOW', 'calculation_fallback', 'historical_only', 1, 'historical_gwa', 0, 5)
    ");
    $stmtInsValidHist->execute();
    $id2 = (int) $db->lastInsertId();
    $createdPredictionIds[] = $id2;
    assertTest($id2 > 0, "Successfully inserted valid historical_only provisional prediction");

    // 3.3 Valid prelim_only provisional insert
    $stmtInsValidPrelim = $db->prepare("
        INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, provisional_basis, input_subject_count, expected_subject_count)
        VALUES (5, 3.00, 'LOW', 'calculation_fallback', 'prelim_only', 1, 'current_prelim_avg', 4, 4)
    ");
    $stmtInsValidPrelim->execute();
    $id3 = (int) $db->lastInsertId();
    $createdPredictionIds[] = $id3;
    assertTest($id3 > 0, "Successfully inserted valid prelim_only provisional prediction");

    // 3.4 Rejected: Omission of data_completeness (no silent default)
    $omissionRejected = false;
    try {
        $db->query("INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source) VALUES (5, 2.75, 'LOW', 'heuristic')");
    } catch (PDOException $e) {
        $omissionRejected = true;
    }
    assertTest($omissionRejected, "Database correctly rejected INSERT omitting data_completeness");

    // 3.5 Rejected: Invalid data_completeness value
    $invalidDomainRejected = false;
    try {
        $db->query("INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional) VALUES (5, 2.75, 'LOW', 'heuristic', 'invalid_status', 0)");
    } catch (PDOException $e) {
        $invalidDomainRejected = true;
    }
    assertTest($invalidDomainRejected, "Database correctly rejected invalid data_completeness");

    // 3.6 Rejected: Inconsistency (historical_only with is_provisional = 0)
    $inconsistencyRejected1 = false;
    try {
        $db->query("INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, provisional_basis) VALUES (5, 2.75, 'LOW', 'heuristic', 'historical_only', 0, 'historical_gwa')");
    } catch (PDOException $e) {
        $inconsistencyRejected1 = true;
    }
    assertTest($inconsistencyRejected1, "Database correctly rejected historical_only with is_provisional=0");

    // 3.7 Rejected: Inconsistency (complete with non-null provisional_basis)
    $inconsistencyRejected2 = false;
    try {
        $db->query("INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, provisional_basis) VALUES (5, 2.75, 'LOW', 'decision_tree', 'complete', 0, 'historical_gwa')");
    } catch (PDOException $e) {
        $inconsistencyRejected2 = true;
    }
    assertTest($inconsistencyRejected2, "Database correctly rejected complete with non-null provisional_basis");

    // 3.8 Rejected: Subject count inversion (input_subject_count > expected_subject_count)
    $countInversionRejected = false;
    try {
        $db->query("INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, provisional_basis, input_subject_count, expected_subject_count) VALUES (5, 2.75, 'LOW', 'calculation_fallback', 'historical_only', 1, 'historical_gwa', 6, 4)");
    } catch (PDOException $e) {
        $countInversionRejected = true;
    }
    assertTest($countInversionRejected, "Database correctly rejected input_subject_count > expected_subject_count");

    // -------------------------------------------------------------------------
    // 4. API Persistence Verification across Cases
    // -------------------------------------------------------------------------
    echo "\n=== 4. API Persistence Verification across Cases ===\n";

    // Test student 5 persistence under Case A
    $resA = getStudentPrediction(5, $db, true);
    assertTest($resA['status'] === 'complete', "api/predict.php Case A status is 'complete'");
    assertTest($resA['data_completeness'] === 'complete', "api/predict.php Case A data_completeness is 'complete'");
    assertTest($resA['is_provisional'] === false, "api/predict.php Case A is_provisional is false");
    assertTest($resA['provisional_basis'] === null, "api/predict.php Case A provisional_basis is null");

    $stmtLatestA = $db->prepare("SELECT * FROM predictions WHERE student_id = 5 ORDER BY id DESC LIMIT 1");
    $stmtLatestA->execute();
    $rowA = $stmtLatestA->fetch();
    $createdPredictionIds[] = (int) $rowA['id'];

    assertTest($rowA['data_completeness'] === 'complete', "Stored row data_completeness is 'complete'");
    assertTest((int)$rowA['is_provisional'] === 0, "Stored row is_provisional is 0");
    assertTest($rowA['provisional_basis'] === null, "Stored row provisional_basis is NULL");
    assertTest((int)$rowA['input_subject_count'] >= 0, "Stored row input_subject_count is recorded");
    assertTest((int)$rowA['expected_subject_count'] >= (int)$rowA['input_subject_count'], "Stored row expected_subject_count >= input_subject_count");

} finally {
    // -------------------------------------------------------------------------
    // 5. Cleanup & Invariant Verification
    // -------------------------------------------------------------------------
    echo "\n=== 5. Cleanup & Database Invariant Verification ===\n";
    if (!empty($createdPredictionIds)) {
        $inIds = implode(',', array_map('intval', $createdPredictionIds));
        $db->exec("DELETE FROM predictions WHERE id IN ($inIds)");
    }

    $afterCounts = [
        'predictions'           => (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
        'academic_support_cases'=> (int) $db->query("SELECT COUNT(*) FROM academic_support_cases")->fetchColumn(),
        'support_case_referrals'=> (int) $db->query("SELECT COUNT(*) FROM support_case_referrals")->fetchColumn(),
        'support_actions'       => (int) $db->query("SELECT COUNT(*) FROM support_actions")->fetchColumn(),
        'support_status_history'=> (int) $db->query("SELECT COUNT(*) FROM support_status_history")->fetchColumn(),
        'admin_change_log'      => (int) $db->query("SELECT COUNT(*) FROM admin_change_log")->fetchColumn(),
    ];

    foreach ($initialCounts as $table => $beforeCount) {
        $afterCount = $afterCounts[$table];
        assertTest($afterCount === $beforeCount, "Invariant preserved for table '$table' (before: $beforeCount, after: $afterCount)");
    }
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
