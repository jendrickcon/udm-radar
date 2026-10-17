<?php
/**
 * tests/integration/multiyear_cohort_seed_test.php — Integration Tests for Multi-Year Synthetic Cohorts
 *
 * PACKAGE: COHORT-DATA-WP1
 *
 * PURPOSE:
 * Verifies end-to-end execution of tools/seed_multiyear_cohorts.php against udm_radar_scratch:
 * 1. Database Target Isolation (strict refusal of live database; strictly udm_radar_scratch; rejection of --target=demo).
 * 2. Pre-execution baseline capture with identity-level row content hashing across 7 related tables.
 * 3. Dry-run execution (--dry-run) guarantees zero database writes.
 * 4. Live seeding (--target=scratch) produces exactly:
 *    - 15 synthetic users across Cohorts A, B, C, D, E.
 *    - 15 student profiles correctly assigned to years 1, 2, 3, 4 (strictly no "Fifth Year").
 *    - 473 grade records respecting institutional grading scales and prerequisite chains.
 *    - 14 prediction records with compliant completeness metadata and provenance.
 *    - Support case, referrals, and actions for Cohort C Case 1.
 * 5. Seeder verification mode (--verify) passes with zero discrepancies.
 * 6. Direct openable test accounts verification (credentials & password_verify against COHORT_TEST_PASSWORD):
 *    - 26-00-001 (1Y: COHORT-1Y-01)
 *    - 25-00-001 (2Y: COHORT-2Y-01)
 *    - 24-00-001 (3Y: COHORT-3Y-01)
 *    - 23-00-001 (4Y: COHORT-4Y-01)
 *    - 24-00-012 (XR: COHORT-XR-03)
 * 7. Portal view query compatibility:
 *    - Cohort A (First Year): handles null historical GWA and partial/complete current terms safely.
 *    - Cohort B (Second Year): prerequisite failure (ITE112) and retake (GED102) query safety.
 *    - Cohort C (Third Year): ML prediction and academic support notice joins.
 *    - Cohort D (Fourth Year): Capstone 1 INC non-numeric exclusion in GWA.
 *    - Cohort E (Cross-Year): Irregular status decoupled from High Risk (24-00-012 GWA >= 3.50, Low Risk).
 * 8. Idempotency check: Re-seeding produces identical clean state without orphan rows or key collisions.
 * 9. Cleanup mode (--cleanup): Completely purges synthetic cohort data.
 * 10. Post-cleanup baseline preservation: 100% bit-for-bit row content hash match for all pre-existing records.
 *
 * EXECUTION:
 *   php tests/integration/multiyear_cohort_seed_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../fixtures/multiyear_cohort_cases.php';

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

// -----------------------------------------------------------------------------
// Test Harness
// -----------------------------------------------------------------------------
$totalTests = 0;
$failedTests = 0;

function assertTest(bool $condition, string $message): void {
    global $totalTests, $failedTests;
    $totalTests++;
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        $failedTests++;
        echo "[FAIL] $message\n";
    }
}

function runSeeder(array $args = []): array {
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../tools/seed_multiyear_cohorts.php');
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg($a);
    }
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        return ['code' => -1, 'stdout' => '', 'stderr' => 'Failed to open process'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'code'   => $exitCode,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
    ];
}

echo "========================================================================\n";
echo "UDM-RADAR: MULTI-YEAR COHORT SEED INTEGRATION TESTS (COHORT-DATA-WP1)\n";
echo "Target Database: $scratchDb\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Database Target Isolation Guard
// -----------------------------------------------------------------------------
echo "=== 1. Database Target Isolation ===\n";

$connectedDb = $db->query("SELECT DATABASE()")->fetchColumn();
assertTest($connectedDb === 'udm_radar_scratch', "Connected database is strictly 'udm_radar_scratch'");
assertTest($connectedDb !== 'udm_radar', "Live production database 'udm_radar' is strictly NOT target");

// Verify rejection of --target=demo
$demoRes = runSeeder(['--target=demo']);
assertTest($demoRes['code'] === 1, "Seeder strictly rejects '--target=demo' with exit code 1");
assertTest(str_contains($demoRes['stderr'], "Refused disallowed target") || str_contains($demoRes['stderr'], "strictly forbidden"), "Seeder outputs refusal message for demo target");

// Pre-test cleanup if any lingering test records existed
$initialCohortUsers = (int)$db->query("
    SELECT count(*) FROM users
    WHERE user_id LIKE '%-00-%'
       OR user_id LIKE 'COHORT-%'
       OR email LIKE '%@synthetic.udm.edu.ph'
")->fetchColumn();

if ($initialCohortUsers > 0) {
    echo "Notice: Cleaning $initialCohortUsers lingering cohort records before baseline capture...\n";
    runSeeder(['--target=scratch', '--cleanup']);
}

// -----------------------------------------------------------------------------
// 2. Pre-execution Baseline Capture (IDs and Content Hashes)
// -----------------------------------------------------------------------------
echo "\n=== 2. Pre-execution Baseline Invariants & Content Hashes ===\n";

$trackedTables = [
    'users',
    'student_profiles',
    'grades',
    'predictions',
    'academic_support_cases',
    'support_case_referrals',
    'support_actions',
];

$preTestRowHashes = [];
$baselineCounts = [];

foreach ($trackedTables as $tbl) {
    $rows = $db->query("SELECT * FROM $tbl ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $baselineCounts[$tbl] = count($rows);
    $preTestRowHashes[$tbl] = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $preTestRowHashes[$tbl][$id] = md5(serialize($r));
    }
    echo "  - $tbl: " . count($rows) . " pre-existing records hashed\n";
}

assertTest($baselineCounts['users'] > 0, "Non-cohort baseline users exist (" . $baselineCounts['users'] . ")");
assertTest($baselineCounts['student_profiles'] > 0, "Non-cohort baseline student_profiles exist (" . $baselineCounts['student_profiles'] . ")");

try {
    // -------------------------------------------------------------------------
    // 3. Dry-Run Verification
    // -------------------------------------------------------------------------
    echo "\n=== 3. Dry-Run Execution ===\n";
    $dryRes = runSeeder(['--target=scratch', '--dry-run']);
    assertTest($dryRes['code'] === 0, "Dry-run exits with code 0");
    assertTest(str_contains($dryRes['stdout'], "[DRY RUN] No database writes were performed"), "Dry-run reports zero database writes");

    $dryCohortUsers = (int)$db->query("
        SELECT count(*) FROM users
        WHERE user_id LIKE '%-00-%'
           OR email LIKE '%@synthetic.udm.edu.ph'
    ")->fetchColumn();
    assertTest($dryCohortUsers === 0, "Dry-run wrote zero rows to users table");

    // -------------------------------------------------------------------------
    // 4. Live Seeding on Scratch
    // -------------------------------------------------------------------------
    echo "\n=== 4. Live Seeding Execution (--target=scratch) ===\n";
    $seedRes = runSeeder(['--target=scratch']);
    assertTest($seedRes['code'] === 0, "Seeding execution exits with code 0");
    assertTest(str_contains($seedRes['stdout'], "Total Students Seeded: 15"), "Seeder reports 15 students seeded");
    assertTest(str_contains($seedRes['stdout'], "Total Grades Inserted: 473"), "Seeder reports 473 grades inserted");
    assertTest(str_contains($seedRes['stdout'], "Total Predictions Inserted: 14"), "Seeder reports 14 predictions inserted");

    // Verify Users table
    $cohortUsers = $db->query("
        SELECT id, user_id, email, password_hash, role, first_name, last_name, is_active
        FROM users
        WHERE email LIKE '%@synthetic.udm.edu.ph'
        ORDER BY user_id
    ")->fetchAll();
    assertTest(count($cohortUsers) === 15, "Exactly 15 synthetic users created in database");

    $allStudents = true;
    $allActive = true;
    $allSyntheticEmails = true;
    $allValidIds = true;
    foreach ($cohortUsers as $u) {
        if ($u['role'] !== 'student') $allStudents = false;
        if ((int)$u['is_active'] !== 1) $allActive = false;
        if (!str_ends_with($u['email'], '@synthetic.udm.edu.ph')) $allSyntheticEmails = false;
        if (preg_match('/^\d{2}-00-\d{3}$/', $u['user_id']) !== 1) $allValidIds = false;
    }
    assertTest($allStudents, "All 15 users have role='student'");
    assertTest($allActive, "All 15 users have is_active=1");
    assertTest($allSyntheticEmails, "All 15 users use domain '@synthetic.udm.edu.ph'");
    assertTest($allValidIds, "All 15 users have user_id formatted as <year>-00-<seq>");

    // Verify Profiles and Year Levels
    $profiles = $db->query("
        SELECT sp.*, u.user_id
        FROM student_profiles sp
        JOIN users u ON sp.user_id = u.id
        WHERE u.email LIKE '%@synthetic.udm.edu.ph'
    ")->fetchAll();
    assertTest(count($profiles) === 15, "Exactly 15 student profiles created in database");

    $yearCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $hasOutsideYear = false;
    foreach ($profiles as $p) {
        $yl = (int)$p['year_level'];
        if (isset($yearCounts[$yl])) {
            $yearCounts[$yl]++;
        } else {
            $hasOutsideYear = true;
        }
    }
    assertTest(!$hasOutsideYear, "No student profile has year_level outside 1..4 (strictly no Fifth Year)");
    assertTest($yearCounts[1] === 3, "Year 1 contains exactly 3 students (Cohort A: 1Y-01..03)");
    assertTest($yearCounts[2] === 4, "Year 2 contains exactly 4 students (Cohort B: 2Y-01..03 + Cohort E: XR-01)");
    assertTest($yearCounts[3] === 5, "Year 3 contains exactly 5 students (Cohort C: 3Y-01..03 + Cohort E: XR-02, XR-03)");
    assertTest($yearCounts[4] === 3, "Year 4 contains exactly 3 students (Cohort D: 4Y-01..03)");

    // Verify Grades count and distribution
    $totalCohortGrades = (int)$db->query("
        SELECT count(*)
        FROM grades g
        JOIN users u ON g.student_id = u.id
        WHERE u.email LIKE '%@synthetic.udm.edu.ph'
    ")->fetchColumn();
    assertTest($totalCohortGrades === 473, "Exactly 473 grades created for synthetic cohorts (expected: 473, actual: $totalCohortGrades)");

    $historicalGradesCount = (int)$db->query("
        SELECT count(*)
        FROM grades g
        JOIN users u ON g.student_id = u.id
        WHERE u.email LIKE '%@synthetic.udm.edu.ph' AND g.is_current = 0
    ")->fetchColumn();
    $currentGradesCount = (int)$db->query("
        SELECT count(*)
        FROM grades g
        JOIN users u ON g.student_id = u.id
        WHERE u.email LIKE '%@synthetic.udm.edu.ph' AND g.is_current = 1
    ")->fetchColumn();
    assertTest($historicalGradesCount === 363, "Historical grades count is 363 (expected: 363, actual: $historicalGradesCount)");
    assertTest($currentGradesCount === 110, "Current term grades count is 110 (expected: 110, actual: $currentGradesCount)");

    // Verify Predictions
    $cohortPredictions = $db->query("
        SELECT p.*, u.user_id
        FROM predictions p
        JOIN users u ON p.student_id = u.id
        WHERE u.email LIKE '%@synthetic.udm.edu.ph'
    ")->fetchAll();
    assertTest(count($cohortPredictions) === 14, "Exactly 14 predictions created (26-00-003 empty state has null prediction)");

    $validCompleteness = true;
    foreach ($cohortPredictions as $p) {
        if (!in_array($p['data_completeness'], ['complete', 'historical_only', 'prelim_only'], true)) {
            $validCompleteness = false;
        }
    }
    assertTest($validCompleteness, "All predictions have valid data_completeness values ('complete', 'historical_only', 'prelim_only')");

    // -------------------------------------------------------------------------
    // 5. Seeder Verification Mode (--verify)
    // -------------------------------------------------------------------------
    echo "\n=== 5. Seeder Verification Mode (--verify) ===\n";
    $verifyRes = runSeeder(['--target=scratch', '--verify']);
    assertTest($verifyRes['code'] === 0, "Seeder verification passes with exit code 0");
    assertTest(str_contains($verifyRes['stdout'], "[VERIFY SUCCESS] All 15 synthetic cohort cases verified"), "Seeder verification confirms all 15 cases match specification");

    // -------------------------------------------------------------------------
    // 6. Openable Test Accounts & Credential Verification
    // -------------------------------------------------------------------------
    echo "\n=== 6. Openable Test Accounts & Credential Verification ===\n";
    $openableAccounts = [
        ['user_id' => '26-00-001', 'cohort' => '1Y', 'year_level' => 1, 'section' => 'IT-11', 'status' => 'Regular'],
        ['user_id' => '25-00-001', 'cohort' => '2Y', 'year_level' => 2, 'section' => 'IT-21', 'status' => 'Irregular'],
        ['user_id' => '24-00-001', 'cohort' => '3Y', 'year_level' => 3, 'section' => 'IT-31', 'status' => 'Regular'],
        ['user_id' => '23-00-001', 'cohort' => '4Y', 'year_level' => 4, 'section' => 'IT-41', 'status' => 'Irregular'],
        ['user_id' => '24-00-012', 'cohort' => 'XR', 'year_level' => 3, 'section' => 'IT-IRREG', 'status' => 'Irregular'],
    ];

    foreach ($openableAccounts as $oa) {
        $uStmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $uStmt->execute([$oa['user_id']]);
        $uRow = $uStmt->fetch();
        assertTest($uRow !== false, "Openable account {$oa['user_id']} ({$oa['cohort']}) exists in users table");
        assertTest(password_verify(COHORT_TEST_PASSWORD, $uRow['password_hash']), "Openable account {$oa['user_id']} authenticates with COHORT_TEST_PASSWORD");

        $pStmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
        $pStmt->execute([$uRow['id']]);
        $pRow = $pStmt->fetch();
        assertTest($pRow !== false, "Openable account {$oa['user_id']} has valid student_profile");
        assertTest((int)$pRow['year_level'] === $oa['year_level'], "Openable account {$oa['user_id']} year_level is {$oa['year_level']}");
        assertTest($pRow['section'] === $oa['section'], "Openable account {$oa['user_id']} section is '{$oa['section']}'");
        assertTest($pRow['status'] === $oa['status'], "Openable account {$oa['user_id']} status is '{$oa['status']}'");
        assertTest($pRow['course'] === 'BSIT - Software Development', "Openable account {$oa['user_id']} course is 'BSIT - Software Development'");
    }

    // -------------------------------------------------------------------------
    // 7. Portal View Query Compatibility Across Cohorts
    // -------------------------------------------------------------------------
    echo "\n=== 7. Portal View Query Compatibility ===\n";

    // Cohort A (First Year) Portal Queries
    $stmtC1 = $db->prepare("
        SELECT u.id, u.user_id, sp.year_level, sp.status, sp.current_gwa,
               (SELECT count(*) FROM grades g WHERE g.student_id = u.id AND g.is_current = 1) as current_subjects,
               (SELECT count(*) FROM grades g WHERE g.student_id = u.id AND g.is_current = 0) as historical_subjects
        FROM users u
        JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.user_id = '26-00-001'
    ");
    $stmtC1->execute();
    $c1Row = $stmtC1->fetch();
    assertTest($c1Row !== false, "Cohort A Case 1 resolves in student portal profile query");
    assertTest((int)$c1Row['year_level'] === 1, "Cohort A Case 1 displays year_level = 1");
    assertTest((int)$c1Row['historical_subjects'] === 0, "Cohort A Case 1 has 0 historical subjects");
    assertTest((int)$c1Row['current_subjects'] === 8, "Cohort A Case 1 has 8 current subjects enrolled");
    assertTest($c1Row['current_gwa'] === null, "Cohort A Case 1 current_gwa is NULL (no historical completed terms)");

    // Cohort B (Second Year) Portal Queries — Prereq & Retake
    $stmtB1 = $db->prepare("
        SELECT g.final_grade, s.code, s.title
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        JOIN users u ON g.student_id = u.id
        WHERE u.user_id = '25-00-001' AND s.code = 'ITE112'
    ");
    $stmtB1->execute();
    $b1Ite112 = $stmtB1->fetch();
    assertTest($b1Ite112 && $b1Ite112['final_grade'] === '0.00', "Cohort B Case 1 records prerequisite failure ITE112 with 0.00");

    $stmtB2 = $db->prepare("
        SELECT g.final_grade, g.school_year, g.semester, s.code
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        JOIN users u ON g.student_id = u.id
        WHERE u.user_id = '25-00-002' AND s.code = 'GED102'
        ORDER BY g.school_year ASC, g.semester ASC
    ");
    $stmtB2->execute();
    $b2Ged102Grades = $stmtB2->fetchAll();
    assertTest(count($b2Ged102Grades) === 2, "Cohort B Case 2 records both failed prerequisite and successful retake for GED102");
    assertTest($b2Ged102Grades[0]['final_grade'] === '0.00', "Cohort B Case 2 initial attempt was 0.00");
    assertTest($b2Ged102Grades[1]['final_grade'] === '2.25', "Cohort B Case 2 retake attempt was 2.25");

    // Cohort C (Third Year) Portal Queries — Prediction & Support Case Joins
    $stmtC = $db->prepare("
        SELECT u.user_id, p.predicted_gwa, p.risk_level, p.prediction_source,
               asc_t.id as case_id, asc_t.status as case_status,
               sa.action_type, sa.message_to_student
        FROM users u
        JOIN predictions p ON u.id = p.student_id
        LEFT JOIN academic_support_cases asc_t ON u.id = asc_t.student_id
        LEFT JOIN support_actions sa ON asc_t.id = sa.case_id
        WHERE u.user_id = '24-00-001'
    ");
    $stmtC->execute();
    $cRow = $stmtC->fetch();
    assertTest($cRow !== false, "Cohort C Case 1 query joins predictions and academic support correctly");
    assertTest($cRow['prediction_source'] === 'decision_tree', "Cohort C Case 1 uses 'decision_tree' prediction_source");
    assertTest(in_array($cRow['risk_level'], ['HIGH', 'High Risk'], true), "Cohort C Case 1 risk_level is High Risk ('{$cRow['risk_level']}')");
    assertTest($cRow['case_id'] !== null && $cRow['case_status'] === 'action_taken', "Cohort C Case 1 has active support case with status='action_taken'");
    assertTest(str_contains($cRow['message_to_student'], 'academic consultation'), "Cohort C Case 1 support action message delivers supportive student phrasing");

    // Cohort D (Fourth Year) Portal Queries — Capstone 1 INC
    $stmtD = $db->prepare("
        SELECT g.final_grade, s.code
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        JOIN users u ON g.student_id = u.id
        WHERE u.user_id = '23-00-001' AND s.code = 'ITE326'
    ");
    $stmtD->execute();
    $dGrades = $stmtD->fetch();
    assertTest($dGrades && $dGrades['final_grade'] === 'INC', "Cohort D Case 1 models Capstone 1 (ITE326) as unresolved 'INC'");

    // GWA verification for Cohort D Case 1: non-numeric 'INC' must be excluded from GWA numerator/denominator
    $stmtDGwa = $db->prepare("SELECT current_gwa FROM student_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.user_id = '23-00-001'");
    $stmtDGwa->execute();
    $dGwa = (float)$stmtDGwa->fetchColumn();
    assertTest($dGwa > 0.0 && $dGwa <= 4.0, "Cohort D Case 1 GWA ($dGwa) calculated without fatal error from non-numeric INC");

    // Cohort E (Cross-Year) Portal Queries — Decoupling Pacing from Risk
    $stmtE = $db->prepare("
        SELECT u.user_id, sp.status, sp.current_gwa, p.risk_level
        FROM student_profiles sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN predictions p ON u.id = p.student_id
        WHERE u.user_id = '24-00-012'
    ");
    $stmtE->execute();
    $e3Row = $stmtE->fetch();
    assertTest($e3Row['status'] === 'Irregular', "Cohort E Case 3 profile status is 'Irregular'");
    assertTest((float)$e3Row['current_gwa'] >= 3.50, "Cohort E Case 3 has High academic standing GWA >= 3.50 ({$e3Row['current_gwa']})");
    assertTest(in_array($e3Row['risk_level'], ['LOW', 'Low Risk'], true), "Cohort E Case 3 has Low Risk triage classification ('{$e3Row['risk_level']}')");
    assertTest(
        $e3Row['status'] === 'Irregular' && in_array($e3Row['risk_level'], ['LOW', 'Low Risk'], true),
        "Cohort E Case 3 strictly proves that Irregular curriculum pacing is decoupled from High Risk (pacing != risk)"
    );

    // -------------------------------------------------------------------------
    // 8. Idempotent Re-seeding Test
    // -------------------------------------------------------------------------
    echo "\n=== 8. Idempotency Check (Re-seeding) ===\n";
    $reseedRes = runSeeder(['--target=scratch']);
    assertTest($reseedRes['code'] === 0, "Re-seeding execution exits with code 0");
    assertTest(str_contains($reseedRes['stdout'], "Removed 15 previous synthetic cohort account(s)"), "Re-seeder purges prior cohort state cleanly");
    assertTest(str_contains($reseedRes['stdout'], "Total Students Seeded: 15"), "Re-seeder re-creates exactly 15 students");

    $reseedUserCount = (int)$db->query("SELECT count(*) FROM users WHERE email LIKE '%@synthetic.udm.edu.ph'")->fetchColumn();
    $reseedGradeCount = (int)$db->query("SELECT count(*) FROM grades g JOIN users u ON g.student_id = u.id WHERE u.email LIKE '%@synthetic.udm.edu.ph'")->fetchColumn();
    $reseedPredCount = (int)$db->query("SELECT count(*) FROM predictions p JOIN users u ON p.student_id = u.id WHERE u.email LIKE '%@synthetic.udm.edu.ph'")->fetchColumn();

    assertTest($reseedUserCount === 15, "Re-seeded user count is exactly 15 (no duplicates)");
    assertTest($reseedGradeCount === 473, "Re-seeded grade count is exactly 473 (no duplicates)");
    assertTest($reseedPredCount === 14, "Re-seeded prediction count is exactly 14 (no duplicates)");

    // -------------------------------------------------------------------------
    // 9. Cleanup Execution (--cleanup)
    // -------------------------------------------------------------------------
    echo "\n=== 9. Cleanup Execution (--cleanup) ===\n";
    $cleanRes = runSeeder(['--target=scratch', '--cleanup']);
    assertTest($cleanRes['code'] === 0, "Cleanup exits with code 0");
    assertTest(str_contains($cleanRes['stdout'], "Multi-year cohort cleanup completed successfully"), "Cleanup reports successful completion");

    $postCleanUsers = (int)$db->query("SELECT count(*) FROM users WHERE email LIKE '%@synthetic.udm.edu.ph' OR user_id LIKE '%-00-%'")->fetchColumn();
    $postCleanProfiles = (int)$db->query("SELECT count(*) FROM student_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.email LIKE '%@synthetic.udm.edu.ph' OR u.user_id LIKE '%-00-%'")->fetchColumn();
    $postCleanGrades = (int)$db->query("SELECT count(*) FROM grades g JOIN users u ON g.student_id = u.id WHERE u.email LIKE '%@synthetic.udm.edu.ph' OR u.user_id LIKE '%-00-%'")->fetchColumn();
    $postCleanPreds = (int)$db->query("SELECT count(*) FROM predictions p JOIN users u ON p.student_id = u.id WHERE u.email LIKE '%@synthetic.udm.edu.ph' OR u.user_id LIKE '%-00-%'")->fetchColumn();
    $postCleanCases = (int)$db->query("SELECT count(*) FROM academic_support_cases WHERE closure_note LIKE '%[COHORT-DATA-WP1]%'")->fetchColumn();

    assertTest($postCleanUsers === 0, "Zero synthetic cohort users remain in users table");
    assertTest($postCleanProfiles === 0, "Zero synthetic cohort profiles remain in student_profiles table");
    assertTest($postCleanGrades === 0, "Zero synthetic cohort grades remain in grades table");
    assertTest($postCleanPreds === 0, "Zero synthetic cohort predictions remain in predictions table");
    assertTest($postCleanCases === 0, "Zero synthetic cohort support cases remain in academic_support_cases");

} finally {
    // -------------------------------------------------------------------------
    // 10. Post-execution Baseline Identity Set & Bit-for-Bit Preservation
    // -------------------------------------------------------------------------
    echo "\n=== 10. Baseline Invariant Preservation & Bit-for-Bit Hash Verification ===\n";

    $missingCount = 0;
    $mutatedCount = 0;
    $extraCount = 0;

    foreach ($trackedTables as $tbl) {
        $postRows = $db->query("SELECT * FROM $tbl ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $postMap = [];
        foreach ($postRows as $pr) {
            $id = (int)$pr['id'];
            $postMap[$id] = md5(serialize($pr));
        }

        // Verify all pre-test rows exist with identical hash
        foreach ($preTestRowHashes[$tbl] as $preId => $preHash) {
            if (!isset($postMap[$preId])) {
                $missingCount++;
                echo "[FAIL] Table $tbl: pre-existing ID $preId is MISSING post-cleanup\n";
            } elseif ($postMap[$preId] !== $preHash) {
                $mutatedCount++;
                echo "[FAIL] Table $tbl: pre-existing ID $preId was MUTATED post-cleanup\n";
            }
        }

        // Verify no orphan records remain
        foreach ($postMap as $postId => $postHash) {
            if (!isset($preTestRowHashes[$tbl][$postId])) {
                $extraCount++;
                echo "[FAIL] Table $tbl: orphan ID $postId remains post-cleanup\n";
            }
        }
    }

    assertTest($missingCount === 0, "All pre-existing baseline row IDs are 100% present post-cleanup (0 missing)");
    assertTest($mutatedCount === 0, "All pre-existing baseline rows match 100% bit-for-bit (0 mutations)");
    assertTest($extraCount === 0, "Zero orphan cohort records exist post-cleanup (0 extra)");

    // Auto-increment status reporting
    echo "\nAuto-Increment Status post-cleanup:\n";
    foreach ($trackedTables as $tbl) {
        $aiStmt = $db->prepare("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $aiStmt->execute([$scratchDb, $tbl]);
        $aiVal = $aiStmt->fetchColumn();
        $maxId = $db->query("SELECT COALESCE(MAX(id), 0) FROM $tbl")->fetchColumn();
        echo "  - $tbl: MAX(id)=$maxId, AUTO_INCREMENT=" . ($aiVal !== null ? $aiVal : 'N/A') . "\n";
    }
}

echo "\n========================================================================\n";
echo "INTEGRATION SUMMARY: Total Tests: $totalTests, Failures: $failedTests\n";
echo "========================================================================\n";

exit($failedTests > 0 ? 1 : 0);
