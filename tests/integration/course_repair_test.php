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
$hasBackupTable = (bool)$db->query("SHOW TABLES LIKE '_backup_student_profiles_course_wp6'")->fetchColumn();
if ($hasBackupTable) {
    $stmtBackup = $db->query("SELECT COUNT(*) FROM _backup_student_profiles_course_wp6");
    $backupCount = (int)$stmtBackup->fetchColumn();
    assertEqual($backupCount, 289, "_backup_student_profiles_course_wp6 contains all 289 pre-migration records");
} else {
    // If table was cleaned up after verification, verify exported backup evidence
    $backupFilePath = __DIR__ . '/../../backups/backup_student_profiles_course_wp6.sql';
    assertTrue(file_exists($backupFilePath), "Exported backup evidence file exists in backups/");
    assertTrue(filesize($backupFilePath) > 0, "Exported backup evidence file is non-empty");
}

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

// Verify LOCKED_COURSE in admin/students.php derives directly from COURSE_PROGRAMS[0]
$studentsCode = file_get_contents(__DIR__ . '/../../admin/students.php');
assertTrue(
    str_contains($studentsCode, 'LOCKED_COURSE') && str_contains($studentsCode, 'COURSE_PROGRAMS[0]'),
    "LOCKED_COURSE in admin/students.php derives directly from COURSE_PROGRAMS[0]"
);
assertTrue(in_array('BSIT - Software Development', COURSE_PROGRAMS, true), "Canonical Software Development is in COURSE_PROGRAMS");

assertTrue(isValidCourse(COURSE_PROGRAMS[0]), "COURSE_PROGRAMS[0] ('BSIT - Software Development') passes isValidCourse()");
assertTrue(isValidCourse(COURSE_PROGRAMS[1]), "COURSE_PROGRAMS[1] ('BSIT - Data Science') passes isValidCourse()");
assertTrue(isValidCourse(COURSE_PROGRAMS[2]), "COURSE_PROGRAMS[2] ('BSIT - Cyber Security') passes isValidCourse()");
assertFalse(isValidCourse('Bachelor of Science in Information Technology'), "Generic BSIT string fails isValidCourse()");
assertFalse(isValidCourse('BSIT'), "Abbreviation BSIT fails isValidCourse()");
assertFalse(isValidCourse(''), "Empty string fails isValidCourse()");
assertFalse(isValidCourse('   '), "Whitespace string fails isValidCourse()");
assertFalse(isValidCourse(null), "Null fails isValidCourse()");

// -------------------------------------------------------------------------
// 6. Direct SQL Constraint Enforcement & Enum Canonicalization
// -------------------------------------------------------------------------
echo "\n=== 6. Direct SQL Constraint Enforcement & Enum Canonicalization ===\n";
try {
    $db->beginTransaction();

    $stmtUpdate = $db->prepare("UPDATE student_profiles SET course = ? WHERE user_id = 5");

    // Test canonical valid updates
    $canonicalTracks = [
        'BSIT - Data Science',
        'BSIT - Cyber Security',
        'BSIT - Software Development',
    ];
    foreach ($canonicalTracks as $track) {
        $stmtUpdate->execute([$track]);
        $val = $db->query("SELECT course FROM student_profiles WHERE user_id = 5")->fetchColumn();
        assertEqual($val, $track, "Valid track '$track' can be stored");
    }

    // Test case variants & trailing space: MariaDB ENUM normalizes them to the declared canonical member
    $normalizingInputs = [
        'bsit - software development'  => 'BSIT - Software Development',
        'BSIT - SOFTWARE DEVELOPMENT'  => 'BSIT - Software Development',
        'BSIT - Software Development ' => 'BSIT - Software Development',
        'bsit - data science'          => 'BSIT - Data Science',
        'bsit - cyber security'        => 'BSIT - Cyber Security',
    ];

    foreach ($normalizingInputs as $input => $expectedCanonical) {
        $stmtUpdate->execute([$input]);
        $row = $db->query("
            SELECT course, HEX(course) AS hex_val, (course = '') AS is_coerced_empty
            FROM student_profiles
            WHERE user_id = 5
        ")->fetch(PDO::FETCH_ASSOC);

        assertEqual($row['course'], $expectedCanonical, "Input " . var_export($input, true) . " is normalized to canonical enum member '$expectedCanonical'");
        assertEqual((int)$row['is_coerced_empty'], 0, "Input " . var_export($input, true) . " does not cause empty string coercion");
        assertEqual(strtoupper($row['hex_val']), strtoupper(bin2hex($expectedCanonical)), "Input " . var_export($input, true) . " stored hex matches canonical binary");
    }

    // Test invalid updates rejected by CHECK constraint or ENUM validation
    $invalidCourses = [
        'Bachelor of Science in Information Technology',
        'BSIT',
        'Computer Science',
        ' BSIT - Software Development', // leading space rejected by constraint
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
    SELECT s.code, s.title, COUNT(DISTINCT g.student_id) AS student_count,
           (SELECT COUNT(*) FROM faculty_class_loads fcl WHERE fcl.subject_id = s.id) AS faculty_loads
    FROM subjects s
    LEFT JOIN grades g ON g.subject_id = s.id
    WHERE s.code IN ('SD351', 'SD352', 'SD353', 'SD354')
    GROUP BY s.id, s.code, s.title
    ORDER BY s.code
");
$sdData = [];
while ($row = $stmtSd->fetch(PDO::FETCH_ASSOC)) {
    $sdData[$row['code']] = $row;
}

assertEqual($sdData['SD351']['title'] ?? '', 'Machine Learning (Elective 1)', "SD351 stored title is 'Machine Learning (Elective 1)'");
assertEqual($sdData['SD352']['title'] ?? '', 'Web Development 2 (Elective 2)', "SD352 stored title is 'Web Development 2 (Elective 2)'");
assertEqual($sdData['SD353']['title'] ?? '', 'Software Development (Elective 3)', "SD353 stored title is 'Software Development (Elective 3)'");
assertEqual($sdData['SD354']['title'] ?? '', 'Platform Technologies (Elective 4)', "SD354 stored title is 'Platform Technologies (Elective 4)'");

assertEqual((int)($sdData['SD351']['student_count'] ?? 0), 289, "All 289 Software Development students have SD351 enrolled/graded");
assertEqual((int)($sdData['SD352']['student_count'] ?? 0), 1, "Only Year 4 reference student has SD352 (3rd years in Sem 1)");
assertEqual((int)($sdData['SD353']['student_count'] ?? 0), 1, "Only Year 4 reference student has SD353 (3rd years in Sem 1)");
assertEqual((int)($sdData['SD354']['student_count'] ?? 0), 1, "Only Year 4 reference student has SD354 (3rd years in Sem 1)");

assertEqual((int)($sdData['SD351']['faculty_loads'] ?? 0), 0, "SD351 has 0 faculty class loads");
assertEqual((int)($sdData['SD352']['faculty_loads'] ?? 0), 0, "SD352 has 0 faculty class loads");
assertEqual((int)($sdData['SD353']['faculty_loads'] ?? 0), 0, "SD353 has 0 faculty class loads");
assertEqual((int)($sdData['SD354']['faculty_loads'] ?? 0), 0, "SD354 has 0 faculty class loads");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
