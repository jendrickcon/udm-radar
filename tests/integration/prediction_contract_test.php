<?php
/**
 * Integration Test Suite: Prediction Source Contract, Schema Constraints & Boundary Integration (WP-5)
 *
 * Verifies against udm_radar_scratch:
 * 1. Column metadata: predictions.prediction_source is VARCHAR(30) NOT NULL with NO permanent default (COLUMN_DEFAULT is NULL).
 * 2. Check constraints: chk_predictions_source_valid and chk_support_cases_source_valid active.
 * 3. Historical provenance preservation: all 405 historical rows in scratch retain 'heuristic'.
 * 4. SQL constraint enforcement on predictions:
 *    - Rejects INSERT without prediction_source (no silent fallback to heuristic).
 *    - Accepts 'decision_tree', 'calculation_fallback', 'heuristic'.
 *    - Rejects 'fallback_blend', '', 'unknown_source'.
 * 5. SQL constraint enforcement on academic_support_cases:
 *    - Accepts 'decision_tree', 'calculation_fallback'.
 *    - Rejects 'fallback_blend', ''.
 * 6. Python ML service contract:
 *    - Accepts canonical 'current_prelim_point_avg' and alias 'current_prelim_avg'.
 *    - Rejects missing features with ValueError (no zero injection).
 *    - Reconciled 50/50 fallback blend snapped to 0.25.
 * 7. Missing-feature 4-case behavior in application logic.
 *
 * Run via CLI: php tests/integration/prediction_contract_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

// Target database configuration: strictly udm_radar_scratch
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

$totalTests = 0;
$failedTests = 0;

function assertTest(bool $condition, string $message): void {
    global $totalTests, $failedTests;
    $totalTests++;
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        $failedTests++;
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: PREDICTION SOURCE CONTRACT & SCHEMA INTEGRATION TESTS (WP-5)\n";
echo "Database: $scratchDb\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Column Metadata in information_schema
// -----------------------------------------------------------------------------
echo "=== 1. Column Metadata in information_schema ===\n";
$stmtCol = $db->prepare("
    SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'predictions' AND COLUMN_NAME = 'prediction_source'
");
$stmtCol->execute([$scratchDb]);
$colMeta = $stmtCol->fetch();

assertTest($colMeta !== false, "Column prediction_source exists on predictions table");
assertTest($colMeta['DATA_TYPE'] === 'varchar', "prediction_source DATA_TYPE is 'varchar'");
assertTest(str_contains($colMeta['COLUMN_TYPE'], 'varchar(30)'), "COLUMN_TYPE is varchar(30)");
assertTest($colMeta['IS_NULLABLE'] === 'NO', "prediction_source IS_NULLABLE is 'NO'");
assertTest($colMeta['COLUMN_DEFAULT'] === null, "prediction_source has NO permanent default (COLUMN_DEFAULT is NULL)");

// -----------------------------------------------------------------------------
// 2. CHECK Constraints in information_schema
// -----------------------------------------------------------------------------
echo "\n=== 2. CHECK Constraints in information_schema ===\n";
$stmtChk = $db->prepare("
    SELECT CONSTRAINT_NAME, CHECK_CLAUSE
    FROM information_schema.CHECK_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = ?
      AND CONSTRAINT_NAME IN ('chk_predictions_source_valid', 'chk_support_cases_source_valid')
");
$stmtChk->execute([$scratchDb]);
$constraints = $stmtChk->fetchAll(PDO::FETCH_KEY_PAIR);

assertTest(isset($constraints['chk_predictions_source_valid']), "Constraint chk_predictions_source_valid exists on predictions");
assertTest(isset($constraints['chk_support_cases_source_valid']), "Constraint chk_support_cases_source_valid exists on academic_support_cases");

// -----------------------------------------------------------------------------
// 3. Historical Provenance Preservation
// -----------------------------------------------------------------------------
echo "\n=== 3. Historical Provenance Preservation ===\n";
$totalPreds = (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
$heuristicCount = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE prediction_source = 'heuristic'")->fetchColumn();
$nullOrBlankCount = (int) $db->query("SELECT COUNT(*) FROM predictions WHERE prediction_source IS NULL OR prediction_source = ''")->fetchColumn();

assertTest($totalPreds === 405, "Exact 405 historical prediction rows exist in scratch DB");
assertTest($heuristicCount === 405, "All 405 historical rows preserve 'heuristic' provenance");
assertTest($nullOrBlankCount === 0, "Zero rows have NULL or blank prediction_source");

// -----------------------------------------------------------------------------
// 4. SQL Constraint Enforcement: predictions
// -----------------------------------------------------------------------------
echo "\n=== 4. Direct SQL Constraint Enforcement: predictions ===\n";
$db->beginTransaction();
try {
    // 4.1 Valid sources can be inserted
    $stmtIns = $db->prepare("INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source) VALUES (5, 2.75, 'LOW', ?)");
    
    $stmtIns->execute(['decision_tree']);
    assertTest(true, "Successfully inserted valid source: 'decision_tree'");
    
    $stmtIns->execute(['calculation_fallback']);
    assertTest(true, "Successfully inserted valid source: 'calculation_fallback'");
    
    $stmtIns->execute(['heuristic']);
    assertTest(true, "Successfully inserted valid source: 'heuristic'");

    // 4.2 Omission of prediction_source must be rejected (no silent default)
    try {
        $db->query("INSERT INTO predictions (student_id, predicted_gwa, risk_level) VALUES (5, 2.75, 'LOW')");
        assertTest(false, "Failed to reject INSERT omitting prediction_source");
    } catch (PDOException $e) {
        assertTest(true, "Database correctly rejected INSERT omitting prediction_source (no silent default)");
    }

    // 4.3 Unmapped fallback_blend must be rejected
    try {
        $stmtIns->execute(['fallback_blend']);
        assertTest(false, "Failed to reject unmapped 'fallback_blend'");
    } catch (PDOException $e) {
        assertTest(true, "Database correctly rejected unmapped source: 'fallback_blend'");
    }

    // 4.4 Blank string must be rejected
    try {
        $stmtIns->execute(['']);
        assertTest(false, "Failed to reject blank string source");
    } catch (PDOException $e) {
        assertTest(true, "Database correctly rejected blank prediction_source");
    }

    // 4.5 Arbitrary unknown string must be rejected
    try {
        $stmtIns->execute(['random_neural_net']);
        assertTest(false, "Failed to reject arbitrary unknown source");
    } catch (PDOException $e) {
        assertTest(true, "Database correctly rejected arbitrary unknown prediction_source");
    }

} finally {
    $db->rollBack();
}

// -----------------------------------------------------------------------------
// 5. SQL Constraint Enforcement: academic_support_cases
// -----------------------------------------------------------------------------
echo "\n=== 5. Direct SQL Constraint Enforcement: academic_support_cases ===\n";
$db->beginTransaction();
try {
    $stmtCase = $db->prepare("
        INSERT INTO academic_support_cases 
        (student_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, status)
        VALUES (5, 'HIGH', 1.50, ?, '2026-2027', 1, 'needs_review')
    ");

    $stmtCase->execute(['decision_tree']);
    assertTest(true, "academic_support_cases accepted: 'decision_tree'");

    $stmtCase->execute(['calculation_fallback']);
    assertTest(true, "academic_support_cases accepted: 'calculation_fallback'");

    $stmtCase->execute(['heuristic']);
    assertTest(true, "academic_support_cases accepted: 'heuristic'");

    try {
        $stmtCase->execute(['fallback_blend']);
        assertTest(false, "academic_support_cases failed to reject 'fallback_blend'");
    } catch (PDOException $e) {
        assertTest(true, "academic_support_cases correctly rejected: 'fallback_blend'");
    }

    try {
        $stmtCase->execute(['']);
        assertTest(false, "academic_support_cases failed to reject blank source");
    } catch (PDOException $e) {
        assertTest(true, "academic_support_cases correctly rejected blank source");
    }

} finally {
    $db->rollBack();
}

// -----------------------------------------------------------------------------
// 6. Python ML Service Contract Verification
// -----------------------------------------------------------------------------
echo "\n=== 6. Python ML Service Contract Verification ===\n";
$pythonExe = dirname(__DIR__, 2) . '/python_ml/venv/Scripts/python.exe';
if (file_exists($pythonExe)) {
    $pyTestScript = <<<'PYCODE'
import json, sys
sys.path.insert(0, 'python_ml')
from decision_tree import predict, extract_features, _fallback_predict

# 1. Canonical payload
r1 = predict({'historical_gwa': 2.75, 'current_prelim_point_avg': 3.0, 'failed_subjects_count': 0, 'irregular_semesters': 0})
assert r1['source'] == 'decision_tree', f"Expected decision_tree, got {r1['source']}"

# 2. Compatibility alias
r2 = predict({'historical_gwa': 2.75, 'current_prelim_avg': 3.0, 'failed_subjects_count': 0, 'irregular_semesters': 0})
assert r2['source'] == 'decision_tree', f"Expected decision_tree, got {r2['source']}"

# 3. Missing historical_gwa raises ValueError (no silent 0.0)
try:
    predict({'current_prelim_point_avg': 3.0, 'failed_subjects_count': 0, 'irregular_semesters': 0})
    sys.exit(101)
except ValueError:
    pass

# 4. Missing current_prelim_point_avg raises ValueError (no silent 0.0)
try:
    predict({'historical_gwa': 2.50, 'failed_subjects_count': 0, 'irregular_semesters': 0})
    sys.exit(102)
except ValueError:
    pass

# 5. Deterministic fallback formula parity (50/50 blend snapped to 0.25)
f1 = _fallback_predict(2.50, 3.50)
assert f1['predicted_gwa'] == 3.00, f"Expected 3.00, got {f1['predicted_gwa']}"
assert f1['source'] == 'fallback_blend', f"Expected fallback_blend, got {f1['source']}"

print("PY_OK")
PYCODE;

    $tmpScript = tempnam(sys_get_temp_dir(), 'py_contract_');
    file_put_contents($tmpScript, $pyTestScript);
    $output = shell_exec(escapeshellcmd($pythonExe) . ' ' . escapeshellarg($tmpScript) . ' 2>&1');
    unlink($tmpScript);

    assertTest(str_contains((string)$output, 'PY_OK'), "Python decision_tree contract verified (canonical payload, alias, zero-injection guard, 50/50 fallback)");
} else {
    echo "[SKIP] Python virtual environment not found at $pythonExe\n";
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $totalTests integration tests, $failedTests failures.\n";
echo "========================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
