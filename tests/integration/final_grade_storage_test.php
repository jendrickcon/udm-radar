<?php
/**
 * Integration Test: Standardized final_grade Storage (VARCHAR(10)) & Domain Constraints
 * 
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 * 
 * Verifies:
 * 1. Column grades.final_grade is VARCHAR(10) NULL.
 * 2. CHECK constraint chk_grades_final_grade_domain is present and enforced.
 * 3. Textual statuses ('INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD') store and retrieve exactly without truncation or coercion.
 * 4. Canonical numeric point grades ('4.00'..'1.00') and legacy '0.00' store and retrieve exactly.
 * 5. Invalid values ('5.00', '3.60', 'PASSED', 'FAIL', 'abc', '', '   ', '75', 'TOOLONGVALUE123') are rejected by CHECK constraint.
 * 6. Stored textual statuses are correctly excluded from GWA calculation.
 * 
 * Run via CLI: php tests/integration/final_grade_storage_test.php
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
echo "UDM-RADAR: INTEGRATION TESTS — FINAL GRADE STORAGE MIGRATION (WP-3)\n";
echo "Database: udm_radar_scratch\n";
echo "========================================================================\n\n";

// -------------------------------------------------------------------------
// 1. Column Metadata Verification
// -------------------------------------------------------------------------
echo "=== 1. Column Metadata in information_schema ===\n";
$stmtCol = $db->prepare("
    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_COMMENT
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'final_grade'
");
$stmtCol->execute();
$colMeta = $stmtCol->fetch();

assertEqual($colMeta['DATA_TYPE'] ?? '', 'varchar', "final_grade DATA_TYPE is varchar");
assertEqual((int)($colMeta['CHARACTER_MAXIMUM_LENGTH'] ?? 0), 10, "final_grade length is 10");
assertEqual($colMeta['IS_NULLABLE'] ?? '', 'YES', "final_grade IS_NULLABLE is YES");
assertEqual($colMeta['COLUMN_COMMENT'] ?? '', "Mixed point (4.00-1.00), textual status (INC, DRP, P, DO, DU, FA, UD), legacy 0.00, or NULL if in-progress", "final_grade column comment is descriptive");

// -------------------------------------------------------------------------
// 2. CHECK Constraint Verification
// -------------------------------------------------------------------------
echo "\n=== 2. CHECK Constraint in information_schema ===\n";
$stmtChk = $db->prepare("
    SELECT CONSTRAINT_NAME, CHECK_CLAUSE
    FROM information_schema.CHECK_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND CONSTRAINT_NAME = 'chk_grades_final_grade_domain'
");
$stmtChk->execute();
$chkRow = $stmtChk->fetch();

assertEqual($chkRow['CONSTRAINT_NAME'] ?? '', 'chk_grades_final_grade_domain', "Constraint chk_grades_final_grade_domain exists");
assertTrue(str_contains(strtolower($chkRow['CHECK_CLAUSE'] ?? ''), 'binary'), "Constraint uses binary exact comparison");

// -------------------------------------------------------------------------
// 3. Valid Values Persistence (Textual Statuses & Numeric Points)
// -------------------------------------------------------------------------
echo "\n=== 3. Storage and Retrieval of Approved Domain Values ===\n";

$approvedValues = [
    // Canonical points
    '4.00', '3.75', '3.50', '3.25', '3.00', '2.75', '2.50', '2.25', '2.00', '1.75', '1.50', '1.25', '1.00',
    // Canonical textual statuses
    'INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD',
    // Legacy compatibility value
    '0.00',
    // NULL
    null,
];

try {
    $db->beginTransaction();

    // Use a designated test student and subject from scratch DB
    $testStudentId = (int)$db->query("SELECT user_id FROM student_profiles LIMIT 1")->fetchColumn();
    $testSubjectId = (int)$db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();

    $stmtInsert = $db->prepare("
        INSERT INTO grades (student_id, subject_id, school_year, semester, final_grade, is_current)
        VALUES (?, ?, '2026-2027', 1, ?, 1)
    ");
    $stmtSelect = $db->prepare("SELECT final_grade FROM grades WHERE id = ?");

    foreach ($approvedValues as $val) {
        $stmtInsert->execute([$testStudentId, $testSubjectId, $val]);
        $insertedId = (int)$db->lastInsertId();

        $stmtSelect->execute([$insertedId]);
        $storedVal = $stmtSelect->fetchColumn();

        if ($val === null) {
            assertEqual($storedVal, null, "Stored and retrieved NULL exactly");
        } else {
            assertEqual($storedVal, $val, "Stored and retrieved '{$val}' exactly without coercion");
        }
    }

    // -------------------------------------------------------------------------
    // 4. Stored Textual Status GWA Math Verification
    // -------------------------------------------------------------------------
    echo "\n=== 4. GWA Math Exclusion with Stored Textual Statuses ===\n";
    // Student GWA should only compute over numeric point grades
    $gradeRows = [
        ['grade' => '3.00', 'units' => 3],
        ['grade' => 'INC',  'units' => 3], // Excluded
        ['grade' => 'DRP',  'units' => 3], // Excluded
        ['grade' => 'P',    'units' => 3], // Excluded
        ['grade' => '0.00', 'units' => 3], // Excluded (legacy 0.00)
        ['grade' => '2.00', 'units' => 3],
    ];
    $weightedGwa = computeWeightedGWA($gradeRows);
    assertEqual($weightedGwa, 2.50, "GWA math correctly excludes textual statuses and legacy 0.00 ((3.00*3 + 2.00*3) / 6 = 2.50)");

    // Clean up test rows via rollback
    $db->rollBack();
    echo "[PASS] All temporary test records cleaned up via transaction rollback.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "[FAIL] Storage test failed: " . $e->getMessage() . "\n";
    $failures++;
}

// -------------------------------------------------------------------------
// 5. Invalid Values Rejection by CHECK Constraint (Hardened Noncanonical Variants)
// -------------------------------------------------------------------------
echo "\n=== 5. CHECK Constraint Rejection of Noncanonical Variants ===\n";

$invalidValues = [
    // Lowercase / mixed-case statuses (must be strictly uppercase)
    'inc',
    'Inc',
    'drp',
    'p',
    'do',
    'du',
    'fa',
    'ud',
    // Aliases & unauthorized statuses
    'PASSED',
    'FAIL',
    'abc',
    // Leading / trailing space padding
    ' INC',
    'INC ',
    'P ',
    '3.50 ',
    // Alternate numeric formatting
    '3.5',
    '01.00',
    '+1.00',
    '75',
    '5.00',
    '3.60',
    // Empty & whitespace
    '',
    '   ',
    // Overflow
    'TOOLONGVALUE123',
];

$stmtInvalid = $db->prepare("
    INSERT INTO grades (student_id, subject_id, school_year, semester, final_grade, is_current)
    VALUES (?, ?, '2026-2027', 1, ?, 1)
");

foreach ($invalidValues as $inval) {
    $rejected = false;
    try {
        $stmtInvalid->execute([$testStudentId, $testSubjectId, $inval]);
    } catch (PDOException $e) {
        // MySQL / MariaDB error 4025: CONSTRAINT `chk_grades_final_grade_domain` failed
        if (str_contains($e->getMessage(), 'chk_grades_final_grade_domain') || str_contains($e->getMessage(), 'CONSTRAINT') || $e->getCode() === '23000') {
            $rejected = true;
        }
    }
    assertTrue($rejected, "Database CHECK constraint rejected invalid value: " . var_export($inval, true));
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
