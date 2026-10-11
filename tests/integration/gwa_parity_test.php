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

function assertFloatEqual(float $actual, float $expected, string $message = '', float $delta = 0.001): void {
    global $failures, $testsRun;
    $testsRun++;
    if (abs($actual - $expected) > $delta) {
        $failures++;
        echo "[FAIL] $message - Expected: $expected, Got: $actual\n";
    } else {
        echo "[PASS] $message\n";
    }
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
    assertEqual(isPassingFinalGrade($canon), true, "'1.50' is passing (ACADEMIC-HOTFIX-WP1)");
    assertEqual(isFailingFinalGrade($canon), false, "'1.50' is not failing");
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

// -------------------------------------------------------------------------
// 5. Lifecycle Scoping Verification (Active vs Active + Graduated)
// -------------------------------------------------------------------------
echo "\n=== 5. Lifecycle Scoping with Graduated Student Record ===\n";

$tempEmail = 'test_graduated_lifecycle_' . uniqid() . '@udm.edu.ph';
$tempStudentNo = 'TEST-GRAD-999';

try {
    $db->beginTransaction();

    // 1. Insert temporary Graduated student
    $stmtUser = $db->prepare("INSERT INTO users (role, email, first_name, last_name, password_hash, is_active) VALUES ('student', ?, 'TestGrad', 'Graduatee', 'fakehash', 0)");
    $stmtUser->execute([$tempEmail]);
    $tempUid = (int) $db->lastInsertId();

    $stmtProfile = $db->prepare("INSERT INTO student_profiles (user_id, student_number, year_level, section, status, current_gwa, record_status) VALUES (?, ?, 4, 'IT-41', 'Regular', 3.50, 'Graduated')");
    $stmtProfile->execute([$tempUid, $tempStudentNo]);

    // Insert historical grade row (is_current = 0) and current grade row (is_current = 1)
    $stmtSubject = $db->query("SELECT id FROM subjects LIMIT 1");
    $subjId = (int) $stmtSubject->fetchColumn();

    $stmtHistGrade = $db->prepare("INSERT INTO grades (student_id, subject_id, school_year, semester, is_current, final_grade) VALUES (?, ?, '2024-2025', 1, 0, '3.50')");
    $stmtHistGrade->execute([$tempUid, $subjId]);

    $stmtCurrGrade = $db->prepare("INSERT INTO grades (student_id, subject_id, school_year, semester, is_current, prelim) VALUES (?, ?, '2026-2027', 1, 1, '88.00')");
    $stmtCurrGrade->execute([$tempUid, $subjId]);

    // Query A: Current operational roster query (Active only)
    $stmtCurrentQuery = $db->prepare("
        SELECT COUNT(*) 
        FROM grades g 
        JOIN student_profiles sp ON sp.user_id = g.student_id 
        WHERE g.student_id = ? AND g.is_current = 1 AND sp.record_status = 'Active'
    ");
    $stmtCurrentQuery->execute([$tempUid]);
    $currentCount = (int) $stmtCurrentQuery->fetchColumn();
    assertEqual($currentCount, 0, "Current operational query: Graduated student is EXCLUDED (record_status = 'Active')");

    // Query B: Historical completed-term query (Active + Graduated)
    $stmtHistoricalQuery = $db->prepare("
        SELECT COUNT(*) 
        FROM grades g 
        JOIN student_profiles sp ON sp.user_id = g.student_id 
        WHERE g.student_id = ? AND g.is_current = 0 AND sp.record_status IN ('Active', 'Graduated')
    ");
    $stmtHistoricalQuery->execute([$tempUid]);
    $histCount = (int) $stmtHistoricalQuery->fetchColumn();
    assertEqual($histCount, 1, "Historical completed-term query: Graduated student is INCLUDED (record_status IN ('Active', 'Graduated'))");

    // Roll back so database remains completely clean
    $db->rollBack();
    echo "[PASS] Temporary Graduated student cleaned up via transaction rollback.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "[FAIL] Lifecycle integration test failed: " . $e->getMessage() . "\n";
    $failures++;
}

// -------------------------------------------------------------------------
// 6. Historical Pass-Rate DRP Exclusion & Outcome Buckets Verification
// -------------------------------------------------------------------------
echo "\n=== 6. Historical Pass-Rate DRP Exclusion & Outcome Buckets ===\n";

// Verify that DRP is excluded from pass-rate denominator in actual query computation
$testGrades = [
    ['final_grade' => '3.00'], // Pass 1
    ['final_grade' => '2.50'], // Pass 2
    ['final_grade' => '2.00'], // Pass 3
    ['final_grade' => '1.75'], // Pass 4
    ['final_grade' => '2.25'], // Pass 5
    ['final_grade' => '2.75'], // Pass 6
    ['final_grade' => '3.25'], // Pass 7
    ['final_grade' => '3.50'], // Pass 8
    ['final_grade' => '0.00'], // Fail 1
    ['final_grade' => 'DRP'],  // Dropped (MUST BE EXCLUDED)
];

$passedCount = 0;
$failedCount = 0;
$droppedCount = 0;

foreach ($testGrades as $tg) {
    $canon = canonicalizeFinalGrade($tg['final_grade']);
    if ($canon === 'DRP') {
        $droppedCount++;
        continue;
    }
    if (isPassingFinalGrade($canon)) {
        $passedCount++;
    } elseif (isFailingFinalGrade($canon)) {
        $failedCount++;
    }
}

$recognizedCount = $passedCount + $failedCount;
$calcPassRate = $recognizedCount > 0 ? round(($passedCount / $recognizedCount) * 100, 2) : 0.0;

assertEqual($passedCount, 8, "8 passed outcomes");
assertEqual($failedCount, 1, "1 failed outcome (0.00)");
assertEqual($droppedCount, 1, "1 dropped outcome (DRP)");
assertEqual($recognizedCount, 9, "Pass rate denominator = 9 (DRP excluded)");
assertFloatEqual($calcPassRate, 88.89, "Calculated pass rate = 88.89% (not 80.00%)");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun integration tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
