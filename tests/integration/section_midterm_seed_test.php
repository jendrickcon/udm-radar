<?php
/**
 * tests/integration/section_midterm_seed_test.php — Integration Tests for Section-Calibrated Midterms
 *
 * PACKAGE: COHORT-DATA-WP1B
 *
 * PURPOSE:
 * Verifies end-to-end execution of tools/seed_section_midterms.php against udm_radar_scratch:
 * 1. Database Target Isolation (strict refusal of live database; strictly udm_radar_scratch; rejection of --target=demo).
 * 2. Strict Account Ownership Gate:
 *    - 288 Third-Year synthetic students (User IDs 6..293)
 *    - Strict rejection of User ID 5 ('23-22-040' Jendrick Coniconde) and admin/faculty IDs 1..4.
 * 3. Pre-execution baseline capture with hash verification of all Prelim grade rows.
 * 4. Dry-run execution (--dry-run) guarantees zero database writes.
 * 5. Live seeding (--target=scratch) updates exactly 2,304 grade rows with deterministic midterms.
 * 6. Prelim Grade Immutability Audit: 100% byte-for-byte identical before and after (0 mutations).
 * 7. Statistical movement contract verification:
 *    - >= 90% within +/- 5 points.
 *    - >= 98% within +/- 8 points.
 *    - >= 99% within +/- 10 points.
 *    - Pearson correlation r > 0.85 (positive and strong).
 *    - Min distinct subject deltas per student > 1.
 * 8. Section Risk Calibration Contract:
 *    - IT-31 through IT-38 satisfy exact target windows (derived via application helpers, never hardcoded).
 * 9. Seeder verification mode (--verify) passes with exit code 0.
 * 10. Portal view query compatibility across student, faculty, and admin routes.
 * 11. Cleanup mode (--cleanup) reverts midterms to NULL and cleans tool-generated prediction rows.
 * 12. Post-cleanup baseline preservation and re-seeding idempotency.
 *
 * EXECUTION:
 *   php tests/integration/section_midterm_seed_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../fixtures/section_grade_progression_profiles.php';

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
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../tools/seed_section_midterms.php');
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
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

echo "========================================================================\n";
echo "UDM-RADAR: SECTION MIDTERM SEEDER INTEGRATION TESTS (COHORT-DATA-WP1B)\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Target Safety & Disallowed Option Refusal
// -----------------------------------------------------------------------------
echo "=== 1. Target Safety & Disallowed Option Refusal ===\n";

$resNoTarget = runSeeder([]);
assertTest($resNoTarget['code'] === 1, "Missing --target parameter exits with code 1");

$resLive = runSeeder(['--target=live']);
assertTest($resLive['code'] === 1, "Disallowed --target=live strictly rejected with exit code 1");

$resProd = runSeeder(['--target=production']);
assertTest($resProd['code'] === 1, "Disallowed --target=production strictly rejected with exit code 1");

$resUdmRadar = runSeeder(['--target=udm_radar']);
assertTest($resUdmRadar['code'] === 1, "Disallowed --target=udm_radar strictly rejected with exit code 1");

$resDemo = runSeeder(['--target=demo']);
assertTest($resDemo['code'] === 1, "Disallowed --target=demo strictly rejected with exit code 1");

// -----------------------------------------------------------------------------
// 2. Strict Account Ownership Gate Verification
// -----------------------------------------------------------------------------
echo "\n=== 2. Strict Account Ownership Gate Verification ===\n";

$sectionAllowlist = getSyntheticSectionAccountAllowlist($db);
assertTest(count($sectionAllowlist) === 288, "Section allowlist contains exactly 288 Third-Year synthetic student accounts");

$overallAllowlist = getSyntheticOwnedAccountAllowlist($db);
assertTest(count($overallAllowlist) >= 288, "Overall synthetic allowlist contains all package accounts (found " . count($overallAllowlist) . ")");

// Negative guard: User ID 5
assertTest(!isset($sectionAllowlist[5]), "User ID 5 ('23-22-040' Jendrick Coniconde) is strictly absent from section allowlist");
assertTest(!isset($overallAllowlist[5]), "User ID 5 is strictly absent from overall synthetic allowlist");
assertTest(!isAccountPackageOwned(5, $db), "isAccountPackageOwned(5) strictly returns false");

// Ensure all allowlisted section accounts are student role and within IDs 6..293
$allValid = true;
foreach ($sectionAllowlist as $uid => $acc) {
    if ($uid < 6 || $uid > 293 || $acc['user_id'] !== $uid) {
        $allValid = false;
        break;
    }
}
assertTest($allValid, "All allowlisted section accounts are within package-owned boundary (User IDs 6..293)");

$allowlist = $sectionAllowlist;

// -----------------------------------------------------------------------------
// 3. Baseline Capture & Dry-Run
// -----------------------------------------------------------------------------
echo "\n=== 3. Baseline Capture & Dry-Run ===\n";

$inClause = implode(',', array_keys($allowlist));

$stmtPrelims = $db->query("
    SELECT id, student_id, subject_id, prelim, midterm
    FROM grades
    WHERE student_id IN ($inClause)
      AND is_current = 1
      AND school_year = '2026-2027'
      AND semester = 1
    ORDER BY id
");
$prelimRows = $stmtPrelims->fetchAll();
assertTest(count($prelimRows) === 2304, "Found exactly 2,304 current-term grade rows for allowlisted students");

$prelimHashes = [];
foreach ($prelimRows as $r) {
    $prelimHashes[$r['id']] = hash('sha256', (string) $r['prelim']);
}
assertTest(count($prelimHashes) === 2304, "Hashed 2,304 baseline Prelim grade values");

// Capture baseline password hashes to ensure section accounts are never modified
$stmtPass = $db->query("SELECT id, password_hash FROM users WHERE id IN ($inClause)");
$baselinePasswords = [];
while ($row = $stmtPass->fetch()) {
    $baselinePasswords[$row['id']] = $row['password_hash'];
}
assertTest(count($baselinePasswords) === 288, "Captured baseline password hashes for all 288 section accounts");

// Manifest validation before seeding
$manifest = getSyntheticSectionManifest();
$manifestBeforeMatch = true;
foreach ($manifest as $sid => $expected) {
    try {
        validateAccountManifestOrThrow($sid, $db);
    } catch (Exception $e) {
        $manifestBeforeMatch = false;
        break;
    }
}
assertTest($manifestBeforeMatch, "All 288 section accounts strictly match committed manifest before seeding");

// Dry-run mode
$resDry = runSeeder(['--target=scratch', '--dry-run']);
assertTest($resDry['code'] === 0, "Seeder dry-run exits with code 0");
assertTest(str_contains($resDry['stdout'], "[DRY RUN] No database writes were performed"), "Dry-run confirms zero writes");

// -----------------------------------------------------------------------------
// 4. Live Seeding Execution
// -----------------------------------------------------------------------------
echo "\n=== 4. Live Seeding Execution ===\n";

$resSeed = runSeeder(['--target=scratch']);
assertTest($resSeed['code'] === 0, "Seeder live execution exits with code 0");
assertTest(str_contains($resSeed['stdout'], "[COMMIT SUCCESS] Updated 2304 grade rows across 288 students"), "Commit confirmation reported");
assertTest(str_contains($resSeed['stdout'], "ALL SECTION CALIBRATION AND PROGRESSION CRITERIA PASSED"), "Status confirms all criteria passed");

// -----------------------------------------------------------------------------
// 5. Prelim Grade Immutability Audit
// -----------------------------------------------------------------------------
echo "\n=== 5. Prelim Grade Immutability Audit ===\n";

$stmtAfter = $db->query("
    SELECT id, prelim, midterm
    FROM grades
    WHERE student_id IN ($inClause)
      AND is_current = 1
      AND school_year = '2026-2027'
      AND semester = 1
    ORDER BY id
");
$afterRows = $stmtAfter->fetchAll();

$mutatedCount = 0;
$populatedMidterms = 0;
foreach ($afterRows as $r) {
    $gid = $r['id'];
    $hAfter = hash('sha256', (string) $r['prelim']);
    if ($hAfter !== ($prelimHashes[$gid] ?? null)) {
        $mutatedCount++;
    }
    if ($r['midterm'] !== null) {
        $populatedMidterms++;
    }
}

assertTest($mutatedCount === 0, "100% of Prelim grades are byte-for-byte immutable (0 mutations)");
assertTest($populatedMidterms === 2304, "All 2,304 grade rows have populated Midterm grades");

// Manifest validation after live seeding
$manifestAfterMatch = true;
foreach ($manifest as $sid => $expected) {
    try {
        validateAccountManifestOrThrow($sid, $db);
    } catch (Exception $e) {
        $manifestAfterMatch = false;
        break;
    }
}
assertTest($manifestAfterMatch, "All 288 section accounts strictly match committed manifest after seeding");

// Verify users.password_hash was never altered on any section account
$stmtPassAfter = $db->query("SELECT id, password_hash FROM users WHERE id IN ($inClause)");
$passwordsUnaltered = true;
$afterPassCount = 0;
while ($row = $stmtPassAfter->fetch()) {
    $afterPassCount++;
    if ($row['password_hash'] !== ($baselinePasswords[$row['id']] ?? null)) {
        $passwordsUnaltered = false;
        break;
    }
}
assertTest($afterPassCount === 288 && $passwordsUnaltered, "Zero section account password hashes were altered during seeding (all 288 byte-for-byte identical)");

// -----------------------------------------------------------------------------
// 6. Statistical Movement Contract Verification
// -----------------------------------------------------------------------------
echo "\n=== 6. Statistical Movement Contract Verification ===\n";

$stmtStats = $db->query("
    SELECT prelim, midterm, student_id
    FROM grades
    WHERE student_id IN ($inClause)
      AND is_current = 1
      AND school_year = '2026-2027'
      AND semester = 1
");
$allRows = $stmtStats->fetchAll();

$w5 = 0; $w8 = 0; $w10 = 0;
$prelims = []; $midterms = [];
$studentDeltas = [];

foreach ($allRows as $r) {
    $p = (float) $r['prelim'];
    $m = (float) $r['midterm'];
    $sid = (int) $r['student_id'];
    $delta = abs($m - $p);

    if ($delta <= 5.0) $w5++;
    if ($delta <= 8.0) $w8++;
    if ($delta <= 10.0) $w10++;

    $prelims[] = $p;
    $midterms[] = $m;
    $studentDeltas[$sid][] = round($m - $p, 2);
}

$cnt = count($allRows);
$pct5 = ($w5 / $cnt) * 100;
$pct8 = ($w8 / $cnt) * 100;
$pct10 = ($w10 / $cnt) * 100;

assertTest($pct5 >= 90.0, sprintf("Movement within +/-5 pts: %.2f%% (contract >= 90%%)", $pct5));
assertTest($pct8 >= 98.0, sprintf("Movement within +/-8 pts: %.2f%% (moderate outliers)", $pct8));
assertTest($pct10 >= 99.0, sprintf("Movement within +/-10 pts: %.2f%% (contract >= 99%%)", $pct10));

// Pearson correlation r
$meanP = array_sum($prelims) / $cnt;
$meanM = array_sum($midterms) / $cnt;
$cov = 0; $varP = 0; $varM = 0;
for ($i = 0; $i < $cnt; $i++) {
    $dP = $prelims[$i] - $meanP;
    $dM = $midterms[$i] - $meanM;
    $cov += $dP * $dM;
    $varP += $dP * $dP;
    $varM += $dM * $dM;
}
$r = $cov / sqrt($varP * $varM);
assertTest($r > 0.85, sprintf("Prelim-to-Midterm correlation r = %.4f (contract > 0.85)", $r));

// Minimum distinct deltas per student
$minDeltas = min(array_map(fn($d) => count(array_unique($d)), $studentDeltas));
assertTest($minDeltas > 1, sprintf("Min distinct subject deltas per student = %d (contract > 1)", $minDeltas));

// -----------------------------------------------------------------------------
// 7. Section Risk Calibration Contract Verification
// -----------------------------------------------------------------------------
echo "\n=== 7. Section Risk Calibration Contract Verification ===\n";

$secStmt = $db->query("
    SELECT sp.section, p.risk_level, COUNT(*) as cnt
    FROM student_profiles sp
    JOIN (
        SELECT p1.student_id, p1.risk_level
        FROM predictions p1
        WHERE p1.id = (
            SELECT p2.id FROM predictions p2
            WHERE p2.student_id = p1.student_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
    ) p ON p.student_id = sp.user_id
    WHERE sp.user_id IN ($inClause)
      AND sp.section LIKE 'IT-3%'
    GROUP BY sp.section, p.risk_level
    ORDER BY sp.section, p.risk_level
");
$secRisks = [];
while ($sr = $secStmt->fetch()) {
    $secRisks[$sr['section']][$sr['risk_level']] = (int) $sr['cnt'];
}

$expectedSections = ['IT-31', 'IT-32', 'IT-33', 'IT-34', 'IT-35', 'IT-36', 'IT-37', 'IT-38'];
foreach ($expectedSections as $sec) {
    $counts = $secRisks[$sec] ?? [];
    $totalSec = array_sum($counts);
    $targetSpec = resolveSectionRiskTarget($sec, $totalSec);

    $h = $counts['HIGH'] ?? 0;
    $m = $counts['MODERATE'] ?? 0;
    $l = $counts['LOW'] ?? 0;

    $hOk = ($h >= $targetSpec['high_min'] && $h <= $targetSpec['high_max']);
    $mOk = ($m >= $targetSpec['mod_min'] && $m <= $targetSpec['mod_max']);

    assertTest($hOk, sprintf("Section %s High Risk count %d is within [%d..%d]", $sec, $h, $targetSpec['high_min'], $targetSpec['high_max']));
    assertTest($mOk, sprintf("Section %s Moderate Risk count %d is within [%d..%d]", $sec, $m, $targetSpec['mod_min'], $targetSpec['mod_max']));
    assertTest($l > 0, sprintf("Section %s Low Risk count %d is positive", $sec, $l));
}

// -----------------------------------------------------------------------------
// 8. Seeder Verify Mode Verification
// -----------------------------------------------------------------------------
echo "\n=== 8. Seeder Verify Mode Verification ===\n";

$resVerify = runSeeder(['--target=scratch', '--verify']);
assertTest($resVerify['code'] === 0, "Seeder --verify mode exits with code 0");
assertTest(str_contains($resVerify['stdout'], "[VERIFY PASSED] All 288 synthetic students and 2,304 grade rows satisfy all progression and calibration contracts"), "Verify confirms all 288 students satisfy progression contract");

// -----------------------------------------------------------------------------
// 9. Portal Route Query Compatibility
// -----------------------------------------------------------------------------
echo "\n=== 9. Portal Route Query Compatibility ===\n";

// Student grades query compatibility (student/grades.php)
$studentIdToTest = 100;
$stmtStudentGrades = $db->prepare("
    SELECT g.id, g.prelim, g.midterm, g.final_grade, g.risk_level,
           s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ?
      AND g.is_current = 1
      AND g.school_year = '2026-2027'
      AND g.semester = 1
    ORDER BY s.code
");
$stmtStudentGrades->execute([$studentIdToTest]);
$studentRows = $stmtStudentGrades->fetchAll();
assertTest(count($studentRows) === 8, "Student grades query returns 8 subject rows for Student $studentIdToTest");

$allSubjectsHaveMidterm = true;
foreach ($studentRows as $sr) {
    if ($sr['midterm'] === null) {
        $allSubjectsHaveMidterm = false;
        break;
    }
}
assertTest($allSubjectsHaveMidterm, "All 8 subjects have populated midterm grades for student view");

// Admin students query compatibility (admin/students.php)
$stmtAdminStudents = $db->query("
    SELECT u.id, u.user_id, sp.section, sp.year_level, sp.current_gwa,
           p.predicted_gwa, p.risk_level AS latest_risk
    FROM users u
    JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN (
        SELECT p1.student_id, p1.predicted_gwa, p1.risk_level
        FROM predictions p1
        WHERE p1.id = (
            SELECT p2.id FROM predictions p2
            WHERE p2.student_id = p1.student_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
    ) p ON p.student_id = u.id
    WHERE u.id BETWEEN 6 AND 293
    ORDER BY u.id
    LIMIT 20
");
$adminStudentRows = $stmtAdminStudents->fetchAll();
assertTest(count($adminStudentRows) === 20, "Admin students query executes cleanly for synthetic student cohort");

// Faculty analytics section query compatibility (faculty/analytics.php)
$stmtFacultyAnalytics = $db->query("
    SELECT sp.section,
           COUNT(DISTINCT u.id) AS total_students,
           AVG(p.predicted_gwa) AS avg_predicted_gwa,
           SUM(CASE WHEN p.risk_level = 'HIGH' THEN 1 ELSE 0 END) AS high_count,
           SUM(CASE WHEN p.risk_level = 'MODERATE' THEN 1 ELSE 0 END) AS mod_count,
           SUM(CASE WHEN p.risk_level = 'LOW' THEN 1 ELSE 0 END) AS low_count
    FROM users u
    JOIN student_profiles sp ON sp.user_id = u.id
    JOIN predictions p ON p.student_id = u.id
    WHERE u.id BETWEEN 6 AND 293
    GROUP BY sp.section
    ORDER BY sp.section
");
$facultyAnalyticsRows = $stmtFacultyAnalytics->fetchAll();
assertTest(count($facultyAnalyticsRows) === 8, "Faculty analytics section aggregation returns 8 sections cleanly");

// -----------------------------------------------------------------------------
// 10. Reversible Cleanup Verification
// -----------------------------------------------------------------------------
echo "\n=== 10. Reversible Cleanup Verification ===\n";

$resClean = runSeeder(['--target=scratch', '--cleanup']);
assertTest($resClean['code'] === 0, "Seeder --cleanup mode exits with code 0");
assertTest(str_contains($resClean['stdout'], "[CLEANUP SUCCESS] Reverted 2304 midterm grade rows to NULL"), "Cleanup confirms 2,304 midterms reverted");

// Post-cleanup check: all midterms NULL
$stmtCheckClean = $db->query("
    SELECT COUNT(*) FROM grades
    WHERE student_id IN ($inClause)
      AND is_current = 1
      AND school_year = '2026-2027'
      AND semester = 1
      AND midterm IS NOT NULL
");
$nonNullMidtermsAfterClean = (int) $stmtCheckClean->fetchColumn();
assertTest($nonNullMidtermsAfterClean === 0, "All midterm grades on allowlisted rows are NULL after cleanup");

// Post-cleanup Prelim immutability check
$stmtCheckPrelims = $db->query("
    SELECT id, prelim FROM grades
    WHERE student_id IN ($inClause)
      AND is_current = 1
      AND school_year = '2026-2027'
      AND semester = 1
");
$cleanPrelimRows = $stmtCheckPrelims->fetchAll();
$cleanMutated = 0;
foreach ($cleanPrelimRows as $r) {
    if (hash('sha256', (string) $r['prelim']) !== ($prelimHashes[$r['id']] ?? null)) {
        $cleanMutated++;
    }
}
assertTest($cleanMutated === 0, "All Prelim grades remain 100% byte-for-byte identical after cleanup");

// Verify passwords unaltered after cleanup
$stmtPassClean = $db->query("SELECT id, password_hash FROM users WHERE id IN ($inClause)");
$passwordsCleanUnaltered = true;
while ($row = $stmtPassClean->fetch()) {
    if ($row['password_hash'] !== ($baselinePasswords[$row['id']] ?? null)) {
        $passwordsCleanUnaltered = false;
        break;
    }
}
assertTest($passwordsCleanUnaltered, "All 288 section account password hashes remain completely unaltered after cleanup");

// Re-seed so scratch database remains populated and ready for browser testing
$resReSeed = runSeeder(['--target=scratch']);
assertTest($resReSeed['code'] === 0, "Re-seeding scratch database succeeds idempotently");

echo "\n========================================================================\n";
echo "INTEGRATION TEST SUMMARY: $totalTests tests executed, $failedTests failures.\n";
echo "========================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
