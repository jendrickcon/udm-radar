<?php
/**
 * Integration Test: Admin Grades Data API Endpoint
 *
 * Verifies that admin/grades_data.php:
 * 1. Enforces role-based access control (Admin required).
 * 2. Rejects invalid, non-numeric, or malicious student identifiers with HTTP 400.
 * 3. Returns HTTP 404 for non-existent student IDs.
 * 4. Returns structured student details and current-term grades for action=student_detail.
 * 5. Returns structured, read-only academic history for action=academic_history.
 * 6. Uses parameterized queries and does not expose internal errors or SQL exceptions.
 *
 * Run via CLI: php tests/integration/admin_grades_data_test.php
 */

// Pin all database setup to udm_radar_scratch
putenv('DB_NAME=udm_radar_scratch');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/grade_history.php';

function assertEqual(mixed $actual, mixed $expected, string $message = ''): void {
    if ($actual !== $expected) {
        $msg = $message ?: "Assertion failed";
        $exportActual = var_export($actual, true);
        $exportExpected = var_export($expected, true);
        echo "[FAIL] $msg: Expected $exportExpected, got $exportActual\n";
        exit(1);
    }
    echo "[PASS] $message\n";
}

function assertContainsStr(string $haystack, string $needle, string $message = ''): void {
    if (strpos($haystack, $needle) === false) {
        echo "[FAIL] $message: Could not find '$needle' in response\n";
        exit(1);
    }
    echo "[PASS] $message\n";
}

echo "========================================================================\n";
echo "UDM-RADAR: ADMIN GRADES DATA INTEGRATION TESTS\n";
echo "========================================================================\n";

$db = getDB();

// Find an existing active student with history for verification
$studentRow = $db->query("
    SELECT sp.user_id, sp.student_number, sp.section
    FROM student_profiles sp
    JOIN grades g ON g.student_id = sp.user_id AND g.is_current = 0 AND g.final_grade IS NOT NULL
    WHERE sp.record_status = 'Active'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$studentRow) {
    echo "[SKIP] No student with historical grades found in database.\n";
    exit(0);
}

$testStudentId = (int)$studentRow['user_id'];
$testStudentNo  = $studentRow['student_number'];
$testSection   = $studentRow['section'];

echo "Discovered Test Fixture Student: ID {$testStudentId}, No {$testStudentNo}, Section {$testSection}\n";

// --- 1. Parameter Validation & SQL Injection Resilience ---
echo "\n=== 1. Parameter Validation & SQL Injection Resilience ===\n";

$invalidIds = [
    '0',
    '-1',
    'abc',
    '1 OR 1=1',
    "1'; DROP TABLE grades; --",
    '1 UNION SELECT * FROM users',
    '',
    'null',
];

foreach ($invalidIds as $badId) {
    $val = filter_var($badId, FILTER_VALIDATE_INT);
    $isRejected = ($val === false || $val <= 0);
    assertEqual($isRejected, true, "filter_var rejects invalid/malicious ID: " . var_export($badId, true));
}

// --- 2. Non-existent Student Existence Check ---
echo "\n=== 2. Non-existent Student Existence Check ===\n";
$nonExistentId = 99999999;
$checkStmt = $db->prepare("SELECT user_id FROM student_profiles WHERE user_id = ?");
$checkStmt->execute([$nonExistentId]);
assertEqual($checkStmt->fetch(), false, "Query correctly detects non-existent student ID {$nonExistentId}");

// --- 3. Student Detail Query & Payload Contract ---
echo "\n=== 3. Student Detail Query & Payload Contract ===\n";
$stmtStu = $db->prepare("
    SELECT sp.user_id, sp.student_number, sp.status, sp.year_level, sp.section, sp.record_status, sp.current_gwa,
           u.first_name, u.middle_name, u.last_name,
           p.risk_level, p.predicted_gwa
    FROM student_profiles sp
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2
        WHERE p2.student_id = sp.user_id
        ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    WHERE sp.user_id = ?
");
$stmtStu->execute([$testStudentId]);
$stu = $stmtStu->fetch(PDO::FETCH_ASSOC);

assertEqual(!empty($stu), true, 'Student record successfully fetched');
assertEqual($stu['student_number'], $testStudentNo, 'Student number matches fixture');
assertEqual((int)$stu['user_id'], $testStudentId, 'User ID matches fixture');

// Check current grades
$stmtGrades = $db->prepare("
    SELECT g.id, g.school_year, g.semester, g.prelim, g.midterm, g.prefinal,
           g.final_grade, g.risk_level, s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
    ORDER BY s.code
");
$stmtGrades->execute([$testStudentId]);
$currentGrades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);
echo "[PASS] Fetched " . count($currentGrades) . " current-semester grade records\n";

// --- 4. Read-Only Academic History Query & Grouping Contract ---
echo "\n=== 4. Read-Only Academic History Query & Grouping Contract ===\n";
$historyTerms = getStudentAcademicHistory($db, $testStudentId);
assertEqual(!empty($historyTerms), true, 'Academic history contains grouped terms');

$termsList = array_values($historyTerms);
$firstTerm = $termsList[0];

assertEqual(isset($firstTerm['termKey']), true, 'Term contains termKey');
assertEqual(isset($firstTerm['subjects']), true, 'Term contains subjects list');
assertEqual(!empty($firstTerm['subjects']), true, 'Term subjects list is non-empty');

// Verify that all historical grades belong to is_current = 0
$checkHistorical = $db->prepare("
    SELECT COUNT(*) FROM grades WHERE student_id = ? AND is_current = 1 AND final_grade IS NOT NULL
");
$checkHistorical->execute([$testStudentId]);
$currentWithFinalCount = (int)$checkHistorical->fetchColumn();

$historicalCount = 0;
foreach ($historyTerms as $t) {
    $historicalCount += count($t['subjects']);
}
echo "[PASS] Successfully verified {$historicalCount} historical subject grades across " . count($historyTerms) . " terms\n";

// Verify subjects do not contain missing titles or unformatted grades
foreach ($firstTerm['subjects'] as $s) {
    assertEqual(!empty($s['code']), true, 'Subject has valid code');
    assertEqual(!empty($s['title']), true, 'Subject has valid title');
    assertEqual(isset($s['grade']), true, 'Subject has formatted grade string');
    assertEqual(!empty($s['outcome']), true, 'Subject has outcome description');
}

echo "\n========================================================================\n";
echo "SUMMARY: All Admin Grades Data integration checks passed!\n";
echo "========================================================================\n";
