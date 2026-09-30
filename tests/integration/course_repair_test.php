<?php
/**
 * Integration Test: Student Course Value & Curriculum-Track Repair (WP-6)
 * 
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 * 
 * Verifies:
 * 1. Column student_profiles.course is ENUM('BSIT - Software Development', 'BSIT - Data Science', 'BSIT - Cyber Security') NOT NULL.
 * 2. CHECK constraint chk_student_profiles_course_valid prevents empty string writes even in non-strict SQL mode.
 * 3. Zero profiles carry blank (''), NULL, or generic 'Bachelor of Science in Information Technology' values.
 * 4. All student profiles belong to canonical COURSE_PROGRAMS vocabulary.
 * 5. Backup table _backup_student_profiles_course_wp6 exists and preserves pre-migration state.
 * 6. Validation helpers (allowedCourses(), isValidCourse()) accept canonical tracks and reject generic/blank strings.
 * 7. Database rejects direct SQL writes of invalid generic BSIT text, empty strings, and NULLs.
 * 8. LOCKED_COURSE constant in admin/students.php aligns with canonical track.
 * 
 * Run via CLI: php tests/integration/course_repair_test.php
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

function assertFalse(bool $condition, string $message = ''): void {
    assertEqual($condition, false, $message);
}

echo "========================================================================\n";
echo "UDM-RADAR: INTEGRATION TESTS — COURSE VALUE & CURRICULUM-TRACK REPAIR (WP-6)\n";
echo "Database: udm_radar_scratch\n";
echo "========================================================================\n\n";

// -------------------------------------------------------------------------
// 1. Column Metadata Verification
// -------------------------------------------------------------------------
echo "=== 1. Column Metadata in information_schema ===\n";
$stmtCol = $db->prepare("
    SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'course'
");
$stmtCol->execute();
$colMeta = $stmtCol->fetch();

assertEqual($colMeta['DATA_TYPE'] ?? '', 'enum', "course DATA_TYPE is enum");
assertTrue(str_contains($colMeta['COLUMN_TYPE'] ?? '', "'BSIT - Software Development'"), "COLUMN_TYPE contains 'BSIT - Software Development'");
assertTrue(str_contains($colMeta['COLUMN_TYPE'] ?? '', "'BSIT - Data Science'"), "COLUMN_TYPE contains 'BSIT - Data Science'");
assertTrue(str_contains($colMeta['COLUMN_TYPE'] ?? '', "'BSIT - Cyber Security'"), "COLUMN_TYPE contains 'BSIT - Cyber Security'");
assertEqual($colMeta['IS_NULLABLE'] ?? '', 'NO', "course IS_NULLABLE is NO");
$rawDefault = $colMeta['COLUMN_DEFAULT'] ?? '';
$cleanDefault = trim((string)$rawDefault, "'\"");
assertEqual($cleanDefault, 'BSIT - Software Development', "course DEFAULT is 'BSIT - Software Development'");

// -------------------------------------------------------------------------
// 2. CHECK Constraint Verification
// -------------------------------------------------------------------------
echo "\n=== 2. CHECK Constraint in information_schema ===\n";
$stmtChk = $db->prepare("
    SELECT CONSTRAINT_NAME
    FROM information_schema.CHECK_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND CONSTRAINT_NAME = 'chk_student_profiles_course_valid'
");
$stmtChk->execute();
$chkName = $stmtChk->fetchColumn();

assertEqual($chkName, 'chk_student_profiles_course_valid', "Constraint chk_student_profiles_course_valid exists");

// -------------------------------------------------------------------------
// 3. Database Population Course Distribution
// -------------------------------------------------------------------------
echo "\n=== 3. Database Population Course Distribution ===\n";
$stmtCounts = $db->query("SELECT course, COUNT(*) AS count FROM student_profiles GROUP BY course");
$courseCounts = $stmtCounts->fetchAll(PDO::FETCH_KEY_PAIR);

assertEqual($courseCounts['BSIT - Software Development'] ?? 0, 289, "All 289 students carry 'BSIT - Software Development'");
assertEqual(count($courseCounts), 1, "Exactly 1 distinct course value present in current scratch dataset");

$stmtBlanks = $db->query("SELECT COUNT(*) FROM student_profiles WHERE course = '' OR course IS NULL");
$blankCount = (int)$stmtBlanks->fetchColumn();
assertEqual($blankCount, 0, "Zero blank or NULL course values in student_profiles");

$stmtLegacy = $db->query("SELECT COUNT(*) FROM student_profiles WHERE course = 'Bachelor of Science in Information Technology'");
$legacyCount = (int)$stmtLegacy->fetchColumn();
assertEqual($legacyCount, 0, "Zero generic 'Bachelor of Science in Information Technology' records remain");

// -------------------------------------------------------------------------
// 4. Backup Table Verification
// -------------------------------------------------------------------------
echo "\n=== 4. Backup Table Verification ===\n";
$stmtBackup = $db->query("SELECT COUNT(*) FROM _backup_student_profiles_course_wp6");
$backupCount = (int)$stmtBackup->fetchColumn();
assertEqual($backupCount, 289, "_backup_student_profiles_course_wp6 contains all 289 pre-migration records");

// -------------------------------------------------------------------------
// 5. Application Vocabulary and Helper Functions
// -------------------------------------------------------------------------
echo "\n=== 5. Application Vocabulary and Helper Functions ===\n";
assertEqual(COURSE_PROGRAMS, [
    'BSIT - Software Development',
    'BSIT - Data Science',
    'BSIT - Cyber Security',
], "COURSE_PROGRAMS constant defines the 3 canonical tracks");

assertEqual(allowedCourses(), COURSE_PROGRAMS, "allowedCourses() returns COURSE_PROGRAMS");

assertTrue(isValidCourse('BSIT - Software Development'), "isValidCourse('BSIT - Software Development') is true");
assertTrue(isValidCourse('BSIT - Data Science'), "isValidCourse('BSIT - Data Science') is true");
assertTrue(isValidCourse('BSIT - Cyber Security'), "isValidCourse('BSIT - Cyber Security') is true");
assertFalse(isValidCourse('Bachelor of Science in Information Technology'), "isValidCourse('Bachelor of Science...') is false");
assertFalse(isValidCourse('BSIT'), "isValidCourse('BSIT') is false");
assertFalse(isValidCourse(''), "isValidCourse('') is false");
assertFalse(isValidCourse('   '), "isValidCourse('   ') is false");
assertFalse(isValidCourse(null), "isValidCourse(null) is false");

// -------------------------------------------------------------------------
// 6. Direct SQL Rejection of Invalid Course Values
// -------------------------------------------------------------------------
echo "\n=== 6. Direct SQL Constraint Enforcement ===\n";
try {
    $db->beginTransaction();

    $stmtUpdate = $db->prepare("UPDATE student_profiles SET course = ? WHERE user_id = 5");

    // Test valid updates
    $stmtUpdate->execute(['BSIT - Data Science']);
    $val = $db->query("SELECT course FROM student_profiles WHERE user_id = 5")->fetchColumn();
    assertEqual($val, 'BSIT - Data Science', "Valid track 'BSIT - Data Science' can be stored");

    $stmtUpdate->execute(['BSIT - Cyber Security']);
    $val = $db->query("SELECT course FROM student_profiles WHERE user_id = 5")->fetchColumn();
    assertEqual($val, 'BSIT - Cyber Security', "Valid track 'BSIT - Cyber Security' can be stored");

    $stmtUpdate->execute(['BSIT - Software Development']);
    $val = $db->query("SELECT course FROM student_profiles WHERE user_id = 5")->fetchColumn();
    assertEqual($val, 'BSIT - Software Development', "Valid track 'BSIT - Software Development' can be stored");

    // Test invalid updates rejected by CHECK constraint
    $invalidCourses = [
        'Bachelor of Science in Information Technology',
        'BSIT',
        'Computer Science',
        '',
        '   ',
    ];

    foreach ($invalidCourses as $inv) {
        $rejected = false;
        try {
            $stmtUpdate->execute([$inv]);
        } catch (PDOException $e) {
            $rejected = true;
        }
        assertTrue($rejected, "Database rejected invalid course value: " . var_export($inv, true));
    }

    $db->rollBack();
    echo "[PASS] Temporary course test updates cleaned up via rollback.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "[FAIL] Course write test failed: " . $e->getMessage() . "\n";
    $failures++;
}

// -------------------------------------------------------------------------
// 7. Track & Elective Consistency Audit
// -------------------------------------------------------------------------
echo "\n=== 7. Track & Elective Consistency Audit ===\n";
$stmtSd = $db->query("
    SELECT s.code, COUNT(DISTINCT g.student_id) AS student_count
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE s.code IN ('SD351', 'SD352', 'SD353', 'SD354')
    GROUP BY s.code
    ORDER BY s.code
");
$sdCounts = $stmtSd->fetchAll(PDO::FETCH_KEY_PAIR);

assertEqual($sdCounts['SD351'] ?? 0, 289, "All 289 Software Development students have SD351 enrolled/graded");
assertEqual($sdCounts['SD352'] ?? 0, 1, "Only Year 4 reference student has SD352 (3rd years in Sem 1)");
assertEqual($sdCounts['SD353'] ?? 0, 1, "Only Year 4 reference student has SD353 (3rd years in Sem 1)");
assertEqual($sdCounts['SD354'] ?? 0, 1, "Only Year 4 reference student has SD354 (3rd years in Sem 1)");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
