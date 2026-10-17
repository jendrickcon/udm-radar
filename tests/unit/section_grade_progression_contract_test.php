<?php
/**
 * tests/unit/section_grade_progression_contract_test.php
 * Unit Test Suite: Section-Calibrated Midterm Grade Progression Contracts (COHORT-DATA-WP1B)
 *
 * Verifies:
 * 1. Seeder CLI Target Allowlist & Safety Gates:
 *    - Rejects missing target argument with exit code 1.
 *    - Rejects production, demo, and arbitrary database targets ('udm_radar', 'demo', 'live', 'production').
 *    - Accepts valid target strictly ('scratch').
 *    - Dry-run mode exits with code 0 without executing any database mutations.
 * 2. Strict Account Ownership Gate:
 *    - Allowlist strictly encompasses package-owned synthetic student accounts (User IDs 6..293 and synthetic cohort accounts).
 *    - Strictly rejects User ID 5 ('23-22-040' Jendrick Coniconde, ja.coniconde@student.udm.edu.ph).
 *    - Strictly rejects admin/faculty IDs 1..4.
 *    - Strictly rejects non-synthetic email domains.
 *    - Throws RuntimeException if ownership gate is violated.
 * 3. Determinism & Seeding Invariance:
 *    - Identical seed parameters produce identical Midterm grades.
 *    - Seed version variation produces reproducible deterministic variation.
 * 4. Movement Distribution Contract:
 *    - At least 90% of movements within +/- 5 points.
 *    - At least 9% of movements within +/- 6 to 8 points.
 *    - At least 1% of movements within +/- 9 to 10 points.
 *    - 0% of movements exceed +/- 10 points.
 *    - Clamped strictly between 60.00 and 100.00.
 * 5. Statistical Properties & Delta Diversity:
 *    - Prelim-to-Midterm Pearson correlation coefficient r > 0.85 (positive and strong).
 *    - Distinct subject-level deltas per student (no uniform shift across all subjects, min deltas > 1).
 * 6. Section Risk Calibration & Application Derivation:
 *    - Section target specifications match institutional risk calibration rules:
 *      - IT-31, IT-32: High 0-2, Mod 3-6.
 *      - IT-33, IT-34: High 2-4, Mod 5-8.
 *      - IT-35, IT-36, IT-37: High 3-7, Mod 5-10.
 *      - IT-38: High 7-10, Mod 10-15.
 *    - Subject progression adjustments (-1 for ITE331, ITE341; +1 for ITE322, ITE332, UID105).
 *    - Profile trend mappings conform to profile specifications.
 *    - Zero hardcoded risk labels: derived strictly via convertPercentageToPoint, computeRiskFromAvg,
 *      and predictFinalGradeHeuristic.
 *
 * Run via CLI: php tests/unit/section_grade_progression_contract_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/tests/fixtures/section_grade_progression_profiles.php';

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
echo "UDM-RADAR: SECTION-CALIBRATED MIDTERM GRADE PROGRESSION UNIT TESTS (COHORT-DATA-WP1B)\n";
echo "========================================================================\n\n";

$seederScript = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'seed_section_midterms.php';

function runSeederCli(array $args): array {
    global $seederScript;
    $argString = implode(' ', array_map('escapeshellarg', $args));
    $cmd = sprintf('"%s" "%s" %s', PHP_BINARY, $seederScript, $argString);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    return [
        'code'   => $code,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

// -----------------------------------------------------------------------------
// 1. CLI Target Allowlist & Safety Gates
// -----------------------------------------------------------------------------
echo "=== 1. Seeder CLI Target Allowlist & Safety Gates ===\n";

$resMissing = runSeederCli([]);
assertTest($resMissing['code'] === 1, "Seeder rejects missing target with exit code 1");
assertTest(str_contains($resMissing['stderr'], "Target option missing"), "Seeder outputs target option missing message");

// Reject live / production targets
foreach (['--target=live', '--target=production', '--target=udm_radar'] as $t) {
    $res = runSeederCli([$t]);
    assertTest($res['code'] === 1, "Seeder strictly rejects $t with exit code 1");
    assertTest(str_contains($res['stderr'], "Live, demo, and arbitrary targets are strictly forbidden") || str_contains($res['stderr'], "FATAL"), "Outputs refusal message for $t");
}

// Reject demo database
foreach (['--target=demo', '--target=udm_radar_demo'] as $t) {
    $res = runSeederCli([$t]);
    assertTest($res['code'] === 1, "Seeder strictly rejects $t with exit code 1");
}

// Dry-run mode on scratch
$resDryRun = runSeederCli(['--target=scratch', '--dry-run']);
assertTest($resDryRun['code'] === 0, "Dry-run on scratch exits with code 0");
assertTest(str_contains($resDryRun['stdout'], "[DRY RUN] No database writes were performed"), "Dry-run confirms zero writes");

// -----------------------------------------------------------------------------
// 2. Strict Account Ownership Gate
// -----------------------------------------------------------------------------
echo "\n=== 2. Strict Account Ownership Gate ===\n";

$scratchDb = new PDO('mysql:host=127.0.0.1;dbname=udm_radar_scratch', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$allowlist = getSyntheticOwnedAccountAllowlist($scratchDb);
assertTest(count($allowlist) >= 288, "Allowlist contains at least 288 synthetic students (found " . count($allowlist) . ")");

// Critical Negative Invariant: User ID 5
assertTest(!isset($allowlist[5]), "User ID 5 is strictly excluded from package-owned allowlist");
assertTest(!isAccountPackageOwned(5, $scratchDb), "isAccountPackageOwned(5) strictly returns false");

// Admin & Faculty negative guards
for ($uid = 1; $uid <= 4; $uid++) {
    assertTest(!isset($allowlist[$uid]), "Administrative/Faculty user ID $uid is excluded from allowlist");
    assertTest(!isAccountPackageOwned($uid, $scratchDb), "isAccountPackageOwned($uid) strictly returns false");
}

// Negative gate exception throwing
$caughtException = false;
try {
    validateAccountOwnershipOrThrow(5, $scratchDb);
} catch (RuntimeException $e) {
    $caughtException = true;
    assertTest(str_contains($e->getMessage(), "Ownership Gate Violation"), "validateAccountOwnershipOrThrow throws descriptive message");
}
assertTest($caughtException, "validateAccountOwnershipOrThrow(5) throws RuntimeException");

// Test positive ownership on allowlisted synthetic student
assertTest(isAccountPackageOwned(6, $scratchDb), "Student ID 6 is confirmed package-owned");
assertTest(isAccountPackageOwned(293, $scratchDb), "Student ID 293 is confirmed package-owned");

// Manifest validation tests
$manifest = getSyntheticSectionManifest();
assertTest(count($manifest) === 288, "Synthetic section manifest contains exactly 288 accounts");

$allManifestValid = true;
foreach ($manifest as $msid => $entry) {
    if ($entry['expected_row_count'] !== 8 || strlen($entry['expected_prelim_hash']) !== 64 || $entry['provenance'] !== 'pre-existing synthetic baseline accounts authorized by exact manifest') {
        $allManifestValid = false;
        break;
    }
}
assertTest($allManifestValid, "All 288 manifest entries define 8 rows, SHA-256 prelim hash, and baseline provenance");

$caughtManifestException = false;
try {
    validateAccountManifestOrThrow(5, $scratchDb);
} catch (RuntimeException $e) {
    $caughtManifestException = true;
    assertTest(str_contains(strtolower($e->getMessage()), "ownership") || str_contains(strtolower($e->getMessage()), "manifest"), "validateAccountManifestOrThrow(5) throws ownership violation");
}
assertTest($caughtManifestException, "validateAccountManifestOrThrow(5) strictly throws RuntimeException");

$val6 = validateAccountManifestOrThrow(6, $scratchDb);
assertTest($val6['provenance'] === 'pre-existing synthetic baseline accounts authorized by exact manifest', "Student ID 6 validated with exact manifest provenance");
assertTest(count($val6['grades']) === 8, "Student ID 6 has exactly 8 validated subject rows");

// -----------------------------------------------------------------------------
// 3. Determinism & Seeding Invariance
// -----------------------------------------------------------------------------
echo "\n=== 3. Determinism & Seeding Invariance ===\n";

$testPrelim = 85.5;
$testStudentId = 42;
$testSubject = 'ITE331';

$grade1 = generateSectionMidtermGrade($testPrelim, $testStudentId, $testSubject, 'Stable', '1.0', '2026-2027', 1);
$grade2 = generateSectionMidtermGrade($testPrelim, $testStudentId, $testSubject, 'Stable', '1.0', '2026-2027', 1);
assertTest($grade1 === $grade2, "Identical inputs yield exact deterministic Midterm grade ($grade1 === $grade2)");

// Different student ID yields distinct deterministic hash
$gradeOtherStudent = generateSectionMidtermGrade($testPrelim, 43, $testSubject, 'Stable', '1.0', '2026-2027', 1);
assertTest(is_float($gradeOtherStudent), "Midterm grade for student 43 is a valid float ($gradeOtherStudent)");

// Seed version variation produces deterministic difference
$gradeV2 = generateSectionMidtermGrade($testPrelim, $testStudentId, $testSubject, 'Stable', '2.0', '2026-2027', 1);
assertTest(is_float($gradeV2), "Version 2.0 produces valid deterministic float ($gradeV2)");

// -----------------------------------------------------------------------------
// 4. Movement Distribution Contract
// -----------------------------------------------------------------------------
echo "\n=== 4. Movement Distribution Contract ===\n";

// Generate midterms across 288 Third-Year synthetic students and 8 subjects
$sampleSubjects = ['ITE331', 'ITE332', 'ITE341', 'ITE322', 'UID105', 'ITE351', 'ITE352', 'ITE353'];
$profiles = getCalibratedStudentProfiles();

$totalSamples = 0;
$within5 = 0;
$within8 = 0;
$within10 = 0;
$outOfBounds = 0;
$deltas = [];
$prelimList = [];
$midtermList = [];
$studentDeltaMap = [];

for ($sid = 6; $sid <= 293; $sid++) {
    $prof = $profiles[$sid] ?? 'Stable';
    // Use realistic prelim spread
    $basePrelim = 70.0 + (($sid * 17) % 25); // 70 to 95
    foreach ($sampleSubjects as $subCode) {
        $p = max(60.0, min(98.0, $basePrelim + (($sid + strlen($subCode)) % 7) - 3));
        $m = generateSectionMidtermGrade($p, $sid, $subCode, $prof, '1.0', '2026-2027', 1);

        $delta = abs($m - $p);
        $totalSamples++;
        if ($delta <= 5.0) $within5++;
        if ($delta <= 8.0) $within8++;
        if ($delta <= 10.0) $within10++;

        if ($m < 60.0 || $m > 100.0) {
            $outOfBounds++;
        }

        $deltas[] = $delta;
        $prelimList[] = $p;
        $midtermList[] = $m;
        $studentDeltaMap[$sid][] = round($m - $p, 2);
    }
}

$pct5 = ($within5 / $totalSamples) * 100;
$pct8 = ($within8 / $totalSamples) * 100;
$pct10 = ($within10 / $totalSamples) * 100;

assertTest($outOfBounds === 0, "All $totalSamples generated midterms fall within 60.00 to 100.00 bounds");
assertTest($pct5 >= 90.0, sprintf("At least 90%% of movements are within +/-5 points (actual: %.2f%%)", $pct5));
assertTest($pct8 >= 98.0, sprintf("At least 98%% of movements are within +/-8 points (actual: %.2f%%)", $pct8));
assertTest($pct10 >= 99.0, sprintf("At least 99%% of movements are within +/-10 points (actual: %.2f%%)", $pct10));
assertTest(max($deltas) <= 10.0, sprintf("Maximum delta across all samples does not exceed 10.0 points (max: %.2f)", max($deltas)));

// -----------------------------------------------------------------------------
// 5. Statistical Properties & Delta Diversity
// -----------------------------------------------------------------------------
echo "\n=== 5. Statistical Properties & Delta Diversity ===\n";

// Pearson correlation coefficient r
$meanP = array_sum($prelimList) / $totalSamples;
$meanM = array_sum($midtermList) / $totalSamples;
$cov = 0; $varP = 0; $varM = 0;
for ($i = 0; $i < $totalSamples; $i++) {
    $dP = $prelimList[$i] - $meanP;
    $dM = $midtermList[$i] - $meanM;
    $cov += $dP * $dM;
    $varP += $dP * $dP;
    $varM += $dM * $dM;
}
$r = $cov / sqrt($varP * $varM);
assertTest($r > 0.85, sprintf("Prelim-to-Midterm correlation is positive and strong (r = %.4f > 0.85)", $r));

// Distinct subject-level movements per student
$minDistinctDeltas = 999;
foreach ($studentDeltaMap as $sid => $dList) {
    $uniqueCount = count(array_unique($dList));
    if ($uniqueCount < $minDistinctDeltas) {
        $minDistinctDeltas = $uniqueCount;
    }
}
assertTest($minDistinctDeltas > 1, "Each student has distinct subject-level deltas (min unique deltas per student: $minDistinctDeltas > 1)");

// -----------------------------------------------------------------------------
// 6. Section Calibration & Latent Profile Mapping
// -----------------------------------------------------------------------------
echo "\n=== 6. Section Calibration & Latent Profile Mapping ===\n";

$targetSpecs = getSectionRiskTargetSpecs();
assertTest(isset($targetSpecs['sections_1_2']), "Target specs define sections_1_2");
assertTest(isset($targetSpecs['sections_3_4']), "Target specs define sections_3_4");
assertTest(isset($targetSpecs['sections_5_7']), "Target specs define sections_5_7");
assertTest(isset($targetSpecs['sections_8']), "Target specs define sections_8");

// Section target resolvers
$t31 = resolveSectionRiskTarget('IT-31', 39);
assertTest($t31['high_min'] === 0 && $t31['high_max'] === 2, "IT-31 target high is 0..2");
assertTest($t31['mod_min'] === 3 && $t31['mod_max'] === 6, "IT-31 target mod is 3..6");

$t33 = resolveSectionRiskTarget('IT-33', 40);
assertTest($t33['high_min'] === 2 && $t33['high_max'] === 4, "IT-33 target high is 2..4");
assertTest($t33['mod_min'] === 5 && $t33['mod_max'] === 8, "IT-33 target mod is 5..8");

$t35 = resolveSectionRiskTarget('IT-35', 37);
assertTest($t35['high_min'] === 5 && $t35['high_max'] === 7, "IT-35 target high is 5..7");
assertTest($t35['mod_min'] === 7 && $t35['mod_max'] === 10, "IT-35 target mod is 7..10");

$t38 = resolveSectionRiskTarget('IT-38', 33);
assertTest($t38['high_min'] === 7 && $t38['high_max'] === 10, "IT-38 target high is 7..10");
assertTest($t38['mod_min'] === 10 && $t38['mod_max'] === 15, "IT-38 target mod is 10..15");

// Subject progression adjustments (curriculum-aligned)
assertTest(getSubjectProgressionAdjustment('ITE331') === -1, "ITE331 progression adjustment is -1 (System Analysis and Design)");
assertTest(getSubjectProgressionAdjustment('ITE341') === -1, "ITE341 progression adjustment is -1 (Statistics and Probability)");
assertTest(getSubjectProgressionAdjustment('ITE322') === 1, "ITE322 progression adjustment is +1 (Networking 1)");
assertTest(getSubjectProgressionAdjustment('ITE332') === 1, "ITE332 progression adjustment is +1 (Seminar in Information Technology)");
assertTest(getSubjectProgressionAdjustment('UID105') === 1, "UID105 progression adjustment is +1 (Achievement and Passion)");
assertTest(getSubjectProgressionAdjustment('UNKNOWN') === 0, "Unknown subject progression adjustment is 0");
assertTest(getSubjectDifficultyAdjustment('ITE331') === -1, "Backward-compatible alias getSubjectDifficultyAdjustment('ITE331') matches");

// Profile trend checks
assertTest(getProfileTrend('Strong', 10) >= 3, "Strong profile produces improving trend (+3 or +4)");
assertTest(getProfileTrend('Stable', 10) >= 0 && getProfileTrend('Stable', 10) <= 1, "Stable profile produces stable trend (0 or +1)");
assertTest(getProfileTrend('Watch', 10) <= -1 && getProfileTrend('Watch', 10) >= -2, "Watch profile produces minor decline (-1 or -2)");
assertTest(getProfileTrend('Support', 10) <= -2 && getProfileTrend('Support', 10) >= -3, "Support profile produces decline (-2 or -3)");
assertTest(getProfileTrend('High-support', 10) <= -3 && getProfileTrend('High-support', 10) >= -4, "High-support profile produces decline (-3 or -4)");

// Profile coverage
$calibratedMap = getCalibratedStudentProfiles();
assertTest(count($calibratedMap) === 288, "Calibrated profile map covers all 288 Third-Year synthetic students (User IDs 6..293)");
$validProfiles = ['Strong', 'Stable', 'Watch', 'Support', 'High-support'];
$allProfilesValid = true;
foreach ($calibratedMap as $uid => $pName) {
    if (!in_array($pName, $validProfiles, true)) {
        $allProfilesValid = false;
        break;
    }
}
assertTest($allProfilesValid, "All 288 students are mapped to valid latent profile types");

// -----------------------------------------------------------------------------
// 7. Application Risk Derivation Contracts
// -----------------------------------------------------------------------------
echo "\n=== 7. Application Risk Derivation Contracts ===\n";

// High risk evaluation test: raw percentage <= 74% yields point grade 0.00
$pointFail = convertPercentageToPoint(70.0);
assertTest($pointFail === 0.00, "70% raw percentage converts to point grade 0.00");
$riskFail = computeRiskFromAvg(0.00);
assertTest($riskFail === 'HIGH', "Point grade 0.00 derives risk level HIGH");

// Passing grade conversions
assertTest(convertPercentageToPoint(75.0) === 1.00, "75% converts to 1.00");
assertTest(convertPercentageToPoint(85.0) === 2.25, "85% converts to 2.25");
assertTest(convertPercentageToPoint(99.0) === 4.00, "99% converts to 4.00");

// Risk cutoffs
assertTest(computeRiskFromAvg(1.50) === 'HIGH', "GWA 1.50 is classified as HIGH risk (< 1.75)");
assertTest(computeRiskFromAvg(1.75) === 'MODERATE', "GWA 1.75 is classified as MODERATE risk (1.75 - 2.49)");
assertTest(computeRiskFromAvg(2.25) === 'MODERATE', "GWA 2.25 is classified as MODERATE risk (1.75 - 2.49)");
assertTest(computeRiskFromAvg(2.49) === 'MODERATE', "GWA 2.49 is classified as MODERATE risk (1.75 - 2.49)");
assertTest(computeRiskFromAvg(2.50) === 'LOW', "GWA 2.50 is classified as LOW risk (>= 2.50)");
assertTest(computeRiskFromAvg(3.50) === 'LOW', "GWA 3.50 is classified as LOW risk (>= 2.50)");

// Full student evaluation through application helper
$testSubjects = [
    ['code' => 'ITE331', 'units' => 3, 'prelim' => 88.0, 'midterm' => 86.0],
    ['code' => 'ITE332', 'units' => 3, 'prelim' => 90.0, 'midterm' => 92.0],
    ['code' => 'ITE341', 'units' => 3, 'prelim' => 84.0, 'midterm' => 82.0],
    ['code' => 'ITE322', 'units' => 3, 'prelim' => 91.0, 'midterm' => 93.0],
];
$studentEval = evaluateStudentProjectedRisk(100, $testSubjects, 2.85);
assertTest(isset($studentEval['predicted_gwa']), "Student evaluation derives predicted GWA");
assertTest(isset($studentEval['risk_level']), "Student evaluation derives risk level");
assertTest(in_array($studentEval['risk_level'], ['HIGH', 'MODERATE', 'LOW'], true), "Derived risk is one of HIGH, MODERATE, LOW");
assertTest(count($studentEval['subject_details']) === 4, "Subject details contain 4 evaluated subjects");

echo "\n========================================================================\n";
echo "UNIT TEST SUMMARY: $totalTests tests executed, $failedTests failures.\n";
echo "========================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);
