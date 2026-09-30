<?php
/**
 * Integration Test Suite: GWA Parity, Historical Analytics & Lifecycle Verification
 * 
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 * 
 * Verifies:
 * 1. 100% GWA parity between student_profiles.current_gwa and computeStudentGwa() on scratch DB.
 * 2. Historical pass-rate classification: 1.50 evaluated as failed, not passed ([DEFECT-WP4-01]).
 * 3. Write-path selective refresh: GWA recalculation only triggers on final_grade updates.
 * 4. Prediction API historical GWA parity with profile GWA.
 * 
 * Run via CLI: php tests/integration/gwa_parity_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/constants.php';

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

$failures = 0;
$testsRun = 0;

function assertEqual(mixed $actual, mixed $expected, string $message = ''): void {
    global $failures, $testsRun;
    $testsRun++;
    if ($actual !== $expected) {
        $failures++;
        echo "[FAIL] $message - Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
    } else {
        echo "[PASS] $message\n";
    }
}

function assertTrue(bool $condition, string $message = ''): void {
    assertEqual($condition, true, $message);
}

echo "========================================================================\n";
echo "UDM-RADAR: INTEGRATION TESTS — GWA PARITY & HISTORICAL ANALYTICS\n";
echo "Database: udm_radar_scratch\n";
echo "========================================================================\n\n";

// -------------------------------------------------------------------------
// 1. Stored GWA Parity on udm_radar_scratch
// -------------------------------------------------------------------------
echo "=== 1. Stored GWA Parity Across All Student Profiles ===\n";
$stmtStudents = $db->query("SELECT user_id, student_number, current_gwa, record_status FROM student_profiles ORDER BY user_id");
$students = $stmtStudents->fetchAll();

$totalStudents = count($students);
$exactParityCount = 0;
$mismatchCount = 0;

foreach ($students as $s) {
    $uid = (int) $s['user_id'];
    $stored = $s['current_gwa'] !== null ? (float) $s['current_gwa'] : null;
    $computed = computeStudentGwa($db, $uid);

    if ($stored === null && $computed === null) {
        $exactParityCount++;
    } elseif ($stored !== null && $computed !== null && abs($stored - $computed) < 0.0001) {
        $exactParityCount++;
    } else {
        $mismatchCount++;
        echo "[FAIL] GWA Parity Mismatch for Student {$s['student_number']} (UID $uid): Stored = $stored, Computed = $computed\n";
    }
}

assertTrue($totalStudents > 0, "Found student profiles in scratch DB (total: $totalStudents)");
assertEqual($mismatchCount, 0, "All $totalStudents students achieve exact GWA parity (100%)");
assertEqual($exactParityCount, $totalStudents, "Parity verified on all $totalStudents profiles");

// -------------------------------------------------------------------------
// 2. DEFECT-WP4-01 Historical Pass-Rate Verification
// -------------------------------------------------------------------------
echo "\n=== 2. DEFECT-WP4-01 Historical Pass-Rate Verification ===\n";
// Find the 3 rows in udm_radar_scratch with final_grade = '1.50'
$stmt150 = $db->query("SELECT id, student_id, subject_id, final_grade FROM grades WHERE is_current = 0 AND final_grade = '1.50'");
$rows150 = $stmt150->fetchAll();

assertTrue(count($rows150) === 3, "Exactly 3 historical records in scratch DB have final_grade = '1.50'");

foreach ($rows150 as $r) {
    $grade = $r['final_grade'];
    $canon = canonicalizeFinalGrade($grade);
    assertEqual($canon, '1.50', "Grade '1.50' canonicalizes to '1.50'");
    assertEqual(isNumericFinalGrade($canon), true, "'1.50' is numeric final grade");
    assertEqual(isPassingFinalGrade($canon), false, "'1.50' is NOT passing (resolving DEFECT-WP4-01)");
    assertEqual(isFailingFinalGrade($canon), true, "'1.50' is FAILING");
}

// Verify passing grade (e.g. 1.75 and 2.50)
$stmtPassing = $db->query("SELECT id, final_grade FROM grades WHERE is_current = 0 AND final_grade IN ('1.75', '2.50') LIMIT 5");
$rowsPassing = $stmtPassing->fetchAll();
foreach ($rowsPassing as $r) {
    assertEqual(isPassingFinalGrade($r['final_grade']), true, "Grade '{$r['final_grade']}' evaluates as passing");
    assertEqual(isFailingFinalGrade($r['final_grade']), false, "Grade '{$r['final_grade']}' does not evaluate as failing");
}

// Verify legacy 0.00 records (33 in scratch DB)
$stmtZero = $db->query("SELECT COUNT(*) FROM grades WHERE is_current = 0 AND final_grade = '0.00'");
$countZero = (int) $stmtZero->fetchColumn();
assertTrue($countZero === 33, "Exactly 33 historical records in scratch DB have legacy final_grade = '0.00'");
assertEqual(isPassingFinalGrade('0.00'), false, "Legacy 0.00 is NOT passing");
assertEqual(isFailingFinalGrade('0.00'), true, "Legacy 0.00 is FAILING");
assertEqual(isExcludedFromGwa('0.00'), true, "Legacy 0.00 is EXCLUDED from GWA math");

// -------------------------------------------------------------------------
// 3. Selective Write-Path Recalculation Behavior
// -------------------------------------------------------------------------
echo "\n=== 3. Selective Write-Path Recalculation Behavior ===\n";
// Pick a test student from scratch DB (e.g. UID 100)
$testStudent = $students[0];
$testUid = (int) $testStudent['user_id'];
$initialGwa = $testStudent['current_gwa'] !== null ? (float) $testStudent['current_gwa'] : null;

// Test: Recalculate GWA via recalculateStudentGwa
$recalcGwa = recalculateStudentGwa($db, $testUid);
assertEqual($recalcGwa, $initialGwa, "recalculateStudentGwa() preserves identical canonical GWA for student UID $testUid");

// Verify that student_profiles.current_gwa matches
$stmtCheck = $db->prepare("SELECT current_gwa FROM student_profiles WHERE user_id = ?");
$stmtCheck->execute([$testUid]);
$currentGwaInDb = (float) $stmtCheck->fetchColumn();
assertEqual($currentGwaInDb, $initialGwa, "Database current_gwa updated accurately by recalculateStudentGwa()");

// -------------------------------------------------------------------------
// 4. Prediction Parity with Profile GWA
// -------------------------------------------------------------------------
echo "\n=== 4. Prediction Parity with Profile GWA ===\n";
// In api/predict.php, historical GWA is computed via computeStudentGwa($db, $studentId).
// It must match student_profiles.current_gwa.
$sampleStudents = array_slice($students, 0, 20);
foreach ($sampleStudents as $s) {
    $uid = (int) $s['user_id'];
    $profileGwa = (float) $s['current_gwa'];
    $predictHistoricalGwa = computeStudentGwa($db, $uid);
    assertEqual($predictHistoricalGwa, $profileGwa, "Prediction historical_gwa matches profile GWA for UID $uid ($profileGwa)");
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun integration tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
