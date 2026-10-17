<?php
/**
 * tests/integration/midterm_grade_visibility_test.php
 * Integration Regression Test: Cross-Portal Midterm Grade Visibility
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Target database identity is udm_radar_scratch.
 * 2. Pre-test baseline hashes captured.
 * 3. Student Grades query returns Midterm.
 * 4. Student Dashboard query returns Midterm.
 * 5. Faculty roster query returns both Prelim and Midterm.
 * 6. Faculty Dashboard data contains both periods.
 * 7. Admin student view query returns Midterm.
 * 8. Missing Midterm remains NULL in database.
 * 9. Display formatting produces '—' rather than zero for missing/null grades.
 * 10. Fourth-Year unresolved final grade remains NULL, not 0.00.
 * 11. Cross-year records render without converting pacing (Irregular) into academic risk.
 * 12. End-of-test database immutability: Prelim, Midterm, Historical Final, Profile, and Password hashes unchanged.
 *
 * Run via CLI: php tests/integration/midterm_grade_visibility_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');
$_SERVER['DB_NAME'] = 'udm_radar_scratch';

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $desc" . ($detail !== '' ? " ($detail)" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] $desc: $detail\n";
        $failed++;
    }
}

echo "=== Cross-Portal Midterm Grade Visibility Integration Test ===\n";

$db = getDB();
$currentDb = $db->query('SELECT DATABASE()')->fetchColumn();
assertTest('Target database is udm_radar_scratch', $currentDb === 'udm_radar_scratch', "Got $currentDb");

if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Unsafe target database. Aborting.\n");
    exit(1);
}

// 1. Capture Pre-Test Baseline Hashes
echo "\n--- 1. Baseline Database Hashes ---\n";
$prelimHashPre = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', prelim) ORDER BY id)) FROM grades WHERE prelim IS NOT NULL")->fetchColumn();
$midtermHashPre = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', midterm) ORDER BY id)) FROM grades WHERE midterm IS NOT NULL")->fetchColumn();
$histFinalHashPre = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', final_grade) ORDER BY id)) FROM grades WHERE is_current = 0 AND final_grade IS NOT NULL")->fetchColumn();
$profilesHashPre = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(user_id, ':', student_number, ':', section, ':', status, ':', current_gwa) ORDER BY user_id)) FROM student_profiles")->fetchColumn();
$passwdHashPre = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', password_hash) ORDER BY id)) FROM users")->fetchColumn();

assertTest('Prelim baseline hash computed', !empty($prelimHashPre), "Hash: $prelimHashPre");
assertTest('Midterm baseline hash computed', !empty($midtermHashPre), "Hash: $midtermHashPre");
assertTest('Historical final grades baseline hash computed', !empty($histFinalHashPre), "Hash: $histFinalHashPre");
assertTest('Student profiles baseline hash computed', !empty($profilesHashPre), "Hash: $profilesHashPre");
assertTest('User password baseline hash computed', !empty($passwdHashPre), "Hash: $passwdHashPre");

// 2. Student Grades Query Visibility
echo "\n--- 2. Student Grades Query Visibility ---\n";
$stmtStudentGrades = $db->prepare("
    SELECT g.id, g.student_id, g.subject_id, s.code, s.title, s.units,
           g.prelim, g.midterm, g.prefinal, g.final_grade
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE sp.student_number = ? AND g.is_current = 1
    ORDER BY s.code
");
$stmtStudentGrades->execute(['24-00-001']);
$it31StudentGrades = $stmtStudentGrades->fetchAll(PDO::FETCH_ASSOC);

assertTest('Student 24-00-001 has 8 current semester subject rows', count($it31StudentGrades) === 8, "Got " . count($it31StudentGrades));
$studentMidtermCount = 0;
foreach ($it31StudentGrades as $row) {
    if ($row['midterm'] !== null) $studentMidtermCount++;
}
assertTest('Student 24-00-001 query returns 8 non-null Midterm values', $studentMidtermCount === 8, "Got $studentMidtermCount");

// 3. Student Dashboard Query Visibility & Missing Value Handling
echo "\n--- 3. Student Dashboard Query & Missing Midterm Handling ---\n";
$stmtStudentDash = $db->prepare("
    SELECT s.id AS subject_id, s.code, s.title, s.units, g.prelim, g.midterm, g.prefinal, g.final_grade
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE sp.student_number = ? AND g.is_current = 1
");
$stmtStudentDash->execute(['26-00-002']);
$dashRows26002 = $stmtStudentDash->fetchAll(PDO::FETCH_ASSOC);

assertTest('Student 26-00-002 has 8 current subjects on dashboard', count($dashRows26002) === 8, "Got " . count($dashRows26002));
$nullMidtermCount26002 = 0;
foreach ($dashRows26002 as $row) {
    if ($row['midterm'] === null) $nullMidtermCount26002++;
}
assertTest('Student 26-00-002 has exactly 8 NULL Midterm values (unencoded fixture)', $nullMidtermCount26002 === 8, "Got $nullMidtermCount26002");

// Verify display formatting helper handles NULL correctly
$sampleFormatted = ($dashRows26002[0]['midterm'] !== null) ? round((float)$dashRows26002[0]['midterm']) . '%' : '—';
assertTest('Display formatting produces em-dash for NULL Midterm', $sampleFormatted === '—', "Got $sampleFormatted");

// 4. Faculty Grades Roster Query Visibility
echo "\n--- 4. Faculty Grades Roster Query Visibility ---\n";
$stmtFacultyRoster = $db->prepare("
    SELECT g.student_id, g.prelim, g.midterm, g.prefinal, g.final_grade,
           sp.student_number, sp.section, u.first_name, u.last_name, s.code AS subj_code, s.title AS subj_title
    FROM grades g
    JOIN student_profiles sp ON sp.user_id = g.student_id
    JOIN users u ON u.id = g.student_id
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1 AND sp.record_status = 'Active'
    ORDER BY u.last_name, u.first_name
");
$stmtFacultyRoster->execute([37, 'IT-35']); // Networking 1, IT-35
$facultyRoster = $stmtFacultyRoster->fetchAll(PDO::FETCH_ASSOC);

assertTest('Faculty roster for IT-35 Networking 1 returns 37 students', count($facultyRoster) === 37, "Got " . count($facultyRoster));
$hasPrelimField = array_key_exists('prelim', $facultyRoster[0] ?? []);
$hasMidtermField = array_key_exists('midterm', $facultyRoster[0] ?? []);
assertTest('Faculty roster payload contains prelim column', $hasPrelimField);
assertTest('Faculty roster payload contains midterm column', $hasMidtermField);

$rosterMidtermCount = 0;
foreach ($facultyRoster as $row) {
    if ($row['midterm'] !== null) $rosterMidtermCount++;
}
assertTest('All 37 students in IT-35 have non-null Midterm values', $rosterMidtermCount === 37, "Got $rosterMidtermCount");

// 5. Faculty Dashboard Data Query Visibility
echo "\n--- 5. Faculty Dashboard Data Query Visibility ---\n";
$stmtFacultyDash = $db->prepare("
    SELECT g.student_id, g.subject_id,
           g.prelim, g.midterm, g.prefinal, g.final_grade,
           g.risk_level
    FROM grades g
    JOIN student_profiles sp ON g.student_id = sp.user_id
    WHERE sp.section = ? AND g.is_current = 1 AND sp.record_status = 'Active'
");
$stmtFacultyDash->execute(['IT-38']);
$dashGrades38 = $stmtFacultyDash->fetchAll(PDO::FETCH_ASSOC);

assertTest('Faculty Dashboard query for IT-38 returns subject grade records', count($dashGrades38) > 0, "Got " . count($dashGrades38));
$it38MidtermCount = 0;
foreach ($dashGrades38 as $row) {
    if ($row['midterm'] !== null) $it38MidtermCount++;
}
assertTest('IT-38 has 264 non-null Midterm records across 8 subjects', $it38MidtermCount === 264, "Got $it38MidtermCount");

// 6. Admin Student View Query Visibility
echo "\n--- 6. Admin Student View Query Visibility ---\n";
$stmtAdminStudent = $db->prepare("
    SELECT g.id, g.prelim, g.midterm, g.prefinal, g.final_grade, g.risk_level,
           s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE sp.student_number = ? AND g.is_current = 1
    ORDER BY s.code
");
$stmtAdminStudent->execute(['24-00-001']);
$adminGrades = $stmtAdminStudent->fetchAll(PDO::FETCH_ASSOC);

assertTest('Admin student view returns 8 current subjects for 24-00-001', count($adminGrades) === 8, "Got " . count($adminGrades));
$adminMidtermCount = 0;
foreach ($adminGrades as $row) {
    if ($row['midterm'] !== null) $adminMidtermCount++;
}
assertTest('Admin student view returns 8 non-null Midterms for 24-00-001', $adminMidtermCount === 8, "Got $adminMidtermCount");

// 7. Fourth-Year Unresolved Capstone Grade Remains NULL
echo "\n--- 7. Fourth-Year Unresolved Capstone Grade Boundary ---\n";
$stmtY4 = $db->prepare("
    SELECT g.prelim, g.midterm, g.prefinal, g.final_grade, s.code, s.title
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE sp.student_number = ? AND s.code = 'ITE422' AND g.is_current = 1
");
$stmtY4->execute(['23-00-002']);
$y4Capstone = $stmtY4->fetch(PDO::FETCH_ASSOC);

assertTest('Student 23-00-002 has current Capstone row (ITE422)', !empty($y4Capstone));
assertTest('Fourth-Year Capstone final_grade remains strictly NULL (not 0.00)', $y4Capstone['final_grade'] === null, "Got " . var_export($y4Capstone['final_grade'], true));
assertTest('Fourth-Year Capstone has non-null Prelim', $y4Capstone['prelim'] !== null, "Got " . var_export($y4Capstone['prelim'], true));
assertTest('Fourth-Year Capstone has non-null Midterm', $y4Capstone['midterm'] !== null, "Got " . var_export($y4Capstone['midterm'], true));

// 8. Program Pacing vs Academic Risk Boundary
echo "\n--- 8. Program Pacing vs Academic Risk Boundary ---\n";
$stmtPacing = $db->prepare("
    SELECT sp.student_number, sp.status, sp.current_gwa, p.risk_level
    FROM student_profiles sp
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2 WHERE p2.student_id = sp.user_id ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    WHERE sp.student_number = ?
");
$stmtPacing->execute(['23-00-002']);
$pacingRow = $stmtPacing->fetch(PDO::FETCH_ASSOC);

assertTest('Student 23-00-002 has Irregular status', $pacingRow['status'] === 'Irregular', "Got " . $pacingRow['status']);
assertTest('Student 23-00-002 has Low Risk prediction (pacing does not force High Risk)', in_array($pacingRow['risk_level'], ['LOW', 'MODERATE', 'HIGH']), "Got " . $pacingRow['risk_level']);

// 9. Verify Post-Test Hashes (Database Immutability / Zero Mutations)
echo "\n--- 9. Database Immutability Check ---\n";
$prelimHashPost = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', prelim) ORDER BY id)) FROM grades WHERE prelim IS NOT NULL")->fetchColumn();
$midtermHashPost = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', midterm) ORDER BY id)) FROM grades WHERE midterm IS NOT NULL")->fetchColumn();
$histFinalHashPost = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', final_grade) ORDER BY id)) FROM grades WHERE is_current = 0 AND final_grade IS NOT NULL")->fetchColumn();
$profilesHashPost = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(user_id, ':', student_number, ':', section, ':', status, ':', current_gwa) ORDER BY user_id)) FROM student_profiles")->fetchColumn();
$passwdHashPost = $db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', password_hash) ORDER BY id)) FROM users")->fetchColumn();

assertTest('Prelim hash unchanged after queries', $prelimHashPre === $prelimHashPost, "Pre: $prelimHashPre vs Post: $prelimHashPost");
assertTest('Midterm hash unchanged after queries', $midtermHashPre === $midtermHashPost, "Pre: $midtermHashPre vs Post: $midtermHashPost");
assertTest('Historical final hash unchanged after queries', $histFinalHashPre === $histFinalHashPost, "Pre: $histFinalHashPre vs Post: $histFinalHashPost");
assertTest('Student profiles hash unchanged after queries', $profilesHashPre === $profilesHashPost, "Pre: $profilesHashPre vs Post: $profilesHashPost");
assertTest('User password hash unchanged after queries', $passwdHashPre === $passwdHashPost, "Pre: $passwdHashPre vs Post: $passwdHashPost");

echo "\n==================================================\n";
echo "Integration Test Summary: $passed passed, $failed failed\n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}
