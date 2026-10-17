<?php
/**
 * tests/unit/multiyear_cohort_contract_test.php
 * Unit Test Suite: Multi-Year Cohort Specifications and Seeder Safety Contracts (COHORT-DATA-WP1)
 *
 * Verifies:
 * 1. Seeder CLI Target Allowlist & Safety Gates:
 *    - Rejects missing target argument with exit code 1.
 *    - Rejects production, demo, and arbitrary database targets ('udm_radar', 'demo', 'live', 'production').
 *    - Accepts valid target strictly ('scratch').
 *    - Dry-run mode exits with code 0 without executing any database mutations.
 * 2. Five Testing Cohorts Specification Matrix:
 *    - Exactly 15 deterministic synthetic test cases.
 *    - Exactly 3 cases per cohort across Cohorts A, B, C, D, and E.
 *    - Cohort A (First Year): Minimal/no history, complete/partial/no-grade in-progress cases.
 *    - Cohort B (Second Year): 1 year history, failed prereq, retake passed, missing historical result.
 *    - Cohort C (Third Year): 2 years history, high, moderate, and low risk examples.
 *    - Cohort D (Fourth Year): 3 years history, Capstone 1 INC and Immersion readiness examples.
 *    - Cohort E (Cross-Year / Irregular): Multi-level enrollment, unpassed failures, high GWA irregular.
 * 3. Synthetic Namespace, Privacy & Test Credentials:
 *    - All student numbers follow the reserved format /^\d{2}-00-\d{3}$/ (middle segment strictly '00').
 *    - All user IDs match student numbers for direct portal login compatibility.
 *    - All email addresses use the reserved non-deliverable domain @synthetic.udm.edu.ph.
 *    - Prohibits genuine student number patterns, @udm.edu.ph, and public webmail domains.
 *    - All test accounts share deterministic COHORT_TEST_PASSWORD.
 * 4. Prediction Provenance & Completeness Contracts:
 *    - All synthetic predictions carry prediction_fixture_origin === 'synthetic_contract_fixture'.
 *    - Risk classification matches institutional GWA boundaries (< 1.75 HIGH, 1.75-2.49 MODERATE, >= 2.50 LOW).
 *    - Completeness metadata respects Migration 008 column domains.
 * 5. Academic Governance & Neutral Language Boundaries:
 *    - Year levels are strictly within 1 to 4 (no "Fifth Year" cohort).
 *    - Zero occurrences of "Dean's List", "caliber", "blocks", or "ineligible".
 *    - Prerequisite conditions framed as neutral unresolved relationships for curriculum review.
 *    - Risk classification and irregular program pacing are strictly decoupled.
 *    - Missing grades remain NULL, never converted to 0.00.
 *
 * Run via CLI: php tests/unit/multiyear_cohort_contract_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/tests/fixtures/multiyear_cohort_cases.php';

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
echo "UDM-RADAR: MULTI-YEAR COHORT CONTRACT UNIT TESTS (COHORT-DATA-WP1)\n";
echo "========================================================================\n\n";

$seederScript = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'seed_multiyear_cohorts.php';

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
// 1. Seeder CLI Target Allowlist & Safety Gates
// -----------------------------------------------------------------------------
echo "=== 1. Seeder CLI Target Allowlist & Safety Gates ===\n";

$resMissing = runSeederCli([]);
assertTest($resMissing['code'] === 1, "Seeder rejects missing target with exit code 1");
assertTest(str_contains($resMissing['stderr'], "Target option missing"), "Seeder outputs target option missing message");

// Reject demo explicitly
$resDemo = runSeederCli(['--target=demo']);
assertTest($resDemo['code'] === 1, "Seeder strictly rejects '--target=demo' with exit code 1");
assertTest(str_contains($resDemo['stderr'], "Refused disallowed target") || str_contains($resDemo['stderr'], "strictly forbidden"), "Seeder outputs refusal message for demo target");

$disallowedTargets = ['udm_radar', 'live', 'production', 'arbitrary_db', 'master', 'udm_radar_demo'];
foreach ($disallowedTargets as $disTarget) {
    $resDis = runSeederCli(["--target=$disTarget"]);
    assertTest($resDis['code'] === 1, "Seeder rejects disallowed target '--target=$disTarget' with exit code 1");
    assertTest(str_contains($resDis['stderr'], "Refused disallowed target") || str_contains($resDis['stderr'], "cannot be seeded") || str_contains($resDis['stderr'], "strictly"), "Seeder outputs rejection message for disallowed target '$disTarget'");
}

$resDryRun = runSeederCli(['--target=scratch', '--dry-run']);
assertTest($resDryRun['code'] === 0, "Seeder accepts valid target '--target=scratch --dry-run' with exit code 0");
assertTest(str_contains($resDryRun['stdout'], "[DRY RUN] No database writes were performed"), "Seeder explicitly confirms dry-run mode performed no writes");

// -----------------------------------------------------------------------------
// 2. Five Testing Cohorts Specification Matrix
// -----------------------------------------------------------------------------
echo "\n=== 2. Five Testing Cohorts Specification Matrix ===\n";

$cases = getMultiyearCohortCases();
assertTest(count($cases) === 15, "Master fixture contains exactly 15 synthetic validation cases");

$casesByCohort = [];
foreach ($cases as $c) {
    $casesByCohort[$c['cohort_key']][] = $c;
}

assertTest(count($casesByCohort) === 5, "Fixture covers exactly 5 distinct testing cohorts");
assertTest(isset($casesByCohort['COHORT_A_FIRST_YEAR']) && count($casesByCohort['COHORT_A_FIRST_YEAR']) === 3, "Cohort A (First Year) contains exactly 3 cases");
assertTest(isset($casesByCohort['COHORT_B_SECOND_YEAR']) && count($casesByCohort['COHORT_B_SECOND_YEAR']) === 3, "Cohort B (Second Year) contains exactly 3 cases");
assertTest(isset($casesByCohort['COHORT_C_THIRD_YEAR']) && count($casesByCohort['COHORT_C_THIRD_YEAR']) === 3, "Cohort C (Third Year) contains exactly 3 cases");
assertTest(isset($casesByCohort['COHORT_D_FOURTH_YEAR']) && count($casesByCohort['COHORT_D_FOURTH_YEAR']) === 3, "Cohort D (Fourth Year) contains exactly 3 cases");
assertTest(isset($casesByCohort['COHORT_E_CROSS_YEAR']) && count($casesByCohort['COHORT_E_CROSS_YEAR']) === 3, "Cohort E (Cross-Year) contains exactly 3 cases");

// Check Cohort A characteristics
$c1Complete = $cases[0];
$c1Partial  = $cases[1];
$c1NoGrade  = $cases[2];

assertTest(empty($c1Complete['historical_terms']), "Cohort A Case 1 has zero historical terms (First-Year start)");
assertTest(!empty($c1Complete['current_term']['subjects']), "Cohort A Case 1 has active current term subjects");
assertTest($c1Complete['current_term']['subjects'][0]['prelim'] !== null && $c1Complete['current_term']['subjects'][0]['midterm'] !== null, "Cohort A Case 1 represents complete current term progress");

assertTest(empty($c1Partial['historical_terms']), "Cohort A Case 2 has zero historical terms");
assertTest($c1Partial['current_term']['subjects'][0]['prelim'] !== null && $c1Partial['current_term']['subjects'][0]['midterm'] === null, "Cohort A Case 2 represents partial current term progress (prelim only)");

assertTest(empty($c1NoGrade['historical_terms']), "Cohort A Case 3 has zero historical terms");
assertTest($c1NoGrade['current_term']['subjects'][0]['prelim'] === null && $c1NoGrade['current_term']['subjects'][0]['midterm'] === null, "Cohort A Case 3 represents zero recorded current grades");
assertTest($c1NoGrade['prediction'] === null, "Cohort A Case 3 has null prediction (pure empty state)");

// Check Cohort B characteristics
$c2FailPrereq = $cases[3];
$c2RetakePass = $cases[4];
$c2Missing    = $cases[5];

assertTest(count($c2FailPrereq['historical_terms']) === 2, "Cohort B Case 1 has 2 completed historical terms (1 completed year)");
$hasZeroFailure = false;
foreach ($c2FailPrereq['historical_terms'][0]['subjects'] as $s) {
    if ($s['code'] === 'ITE112' && $s['final_grade'] === '0.00') {
        $hasZeroFailure = true;
    }
}
assertTest($hasZeroFailure, "Cohort B Case 1 records failed prerequisite ITE112 with 0.00 in Year 1 Sem 1");

$retakeHistoricalGrades = [];
foreach ($c2RetakePass['historical_terms'] as $term) {
    foreach ($term['subjects'] as $s) {
        if ($s['code'] === 'GED102') {
            $retakeHistoricalGrades[] = $s['final_grade'];
        }
    }
}
assertTest(count($retakeHistoricalGrades) === 2 && in_array('0.00', $retakeHistoricalGrades, true) && in_array('2.25', $retakeHistoricalGrades, true), "Cohort B Case 2 preserves both initial 0.00 failure and subsequent 2.25 retake");

// Check Cohort C characteristics
$c3High = $cases[6];
$c3Mod  = $cases[7];
$c3Low  = $cases[8];

assertTest(count($c3High['historical_terms']) === 4, "Cohort C Case 1 has 4 completed historical terms (2 completed years)");
assertTest($c3High['prediction']['risk_level'] === 'HIGH' && $c3High['prediction']['predicted_gwa'] < 1.75, "Cohort C Case 1 models verified High Risk prediction (< 1.75)");
assertTest($c3Mod['prediction']['risk_level'] === 'MODERATE' && $c3Mod['prediction']['predicted_gwa'] >= 1.75 && $c3Mod['prediction']['predicted_gwa'] < 2.50, "Cohort C Case 2 models verified Moderate Risk prediction (1.75 - 2.49)");
assertTest($c3Low['prediction']['risk_level'] === 'LOW' && $c3Low['prediction']['predicted_gwa'] >= 2.50, "Cohort C Case 3 models verified Low Risk prediction (>= 2.50)");

// Check Cohort D characteristics
$c4Capstone = $cases[9];
$c4Elective = $cases[10];
$c4Immersion = $cases[11];

assertTest(count($c4Capstone['historical_terms']) === 6, "Cohort D Case 1 has 6 completed historical terms (3 completed years)");
$hasIncCapstone = false;
foreach ($c4Capstone['historical_terms'][5]['subjects'] as $s) {
    if ($s['code'] === 'ITE326' && $s['final_grade'] === null && ($s['recorded_status'] ?? null) === 'INC' && ($s['expected_gwa_behavior'] ?? null) === 'excluded') {
        $hasIncCapstone = true;
    }
}
assertTest($hasIncCapstone, "Cohort D Case 1 fixture records unresolved Capstone 1 (ITE326) status metadata as INC with null final_grade");

// Check Cohort E characteristics
$c5Unpassed = $cases[12];
$c5Delayed  = $cases[13];
$c5HighGwa  = $cases[14];

assertTest($c5Unpassed['status'] === 'Irregular', "Cohort E Case 1 has Irregular status");
assertTest($c5Delayed['status'] === 'Irregular', "Cohort E Case 2 has Irregular status");
assertTest($c5HighGwa['status'] === 'Irregular', "Cohort E Case 3 has Irregular status");

// Crucial negative constraint assertion: High GWA + Irregular status
assertTest(
    $c5HighGwa['status'] === 'Irregular' && $c5HighGwa['prediction']['risk_level'] === 'LOW' && $c5HighGwa['prediction']['predicted_gwa'] >= 3.50,
    "Cohort E Case 3 strictly proves that Irregular curriculum pacing is decoupled from High Risk (pacing != risk)"
);

// -----------------------------------------------------------------------------
// 3. Synthetic Namespace, Privacy & Test Credentials
// -----------------------------------------------------------------------------
echo "\n=== 3. Synthetic Namespace, Privacy & Test Credentials ===\n";

$studentNumberPattern = '/^\d{2}-00-\d{3}$/';
$prohibitedEmailDomains = ['@udm.edu.ph', '@student.udm.edu.ph', '@gmail.com', '@yahoo.com', '@outlook.com', '@hotmail.com'];

assertTest(defined('COHORT_TEST_PASSWORD') && COHORT_TEST_PASSWORD === 'CohortTest2026!', "Deterministic COHORT_TEST_PASSWORD is defined as 'CohortTest2026!'");

foreach ($cases as $c) {
    // Student number checks
    assertTest(
        preg_match($studentNumberPattern, $c['student_number']) === 1,
        "Case {$c['case_id']} student_number '{$c['student_number']}' matches format <year>-00-<seq>"
    );
    $parts = explode('-', $c['student_number']);
    assertTest(
        isset($parts[1]) && $parts[1] === '00',
        "Case {$c['case_id']} student_number '{$c['student_number']}' middle segment is strictly '00'"
    );

    // User ID matches student number for login compatibility
    assertTest(
        $c['user_id'] === $c['student_number'],
        "Case {$c['case_id']} user_id ('{$c['user_id']}') matches student_number ('{$c['student_number']}')"
    );

    // Synthetic email domain checks
    assertTest(
        str_ends_with($c['email'], '@synthetic.udm.edu.ph'),
        "Case {$c['case_id']} uses non-deliverable reserved email domain @synthetic.udm.edu.ph"
    );

    $hasProhibitedDomain = false;
    foreach ($prohibitedEmailDomains as $pDom) {
        if (str_ends_with($c['email'], $pDom)) {
            $hasProhibitedDomain = true;
        }
    }
    assertTest(!$hasProhibitedDomain, "Case {$c['case_id']} rejects real and public email domains");

    // Synthetic naming checks
    assertTest(
        str_starts_with($c['first_name'], 'Syn'),
        "Case {$c['case_id']} first_name '{$c['first_name']}' begins with synthetic prefix 'Syn'"
    );
}

// -----------------------------------------------------------------------------
// 4. Prediction Provenance & Completeness Contracts
// -----------------------------------------------------------------------------
echo "\n=== 4. Prediction Provenance & Completeness Contracts ===\n";

foreach ($cases as $c) {
    if ($c['prediction'] === null) {
        assertTest($c['case_id'] === 'COHORT-1Y-03', "Only COHORT-1Y-03 (zero current grades) has null prediction");
        continue;
    }

    $p = $c['prediction'];

    // Provenance assertion
    assertTest(
        isset($p['prediction_fixture_origin']) && $p['prediction_fixture_origin'] === 'synthetic_contract_fixture',
        "Case {$c['case_id']} prediction carries prediction_fixture_origin = 'synthetic_contract_fixture'"
    );

    // Source domain assertion
    assertTest(
        in_array($p['prediction_source'], ALLOWED_PREDICTION_SOURCES, true),
        "Case {$c['case_id']} prediction_source '{$p['prediction_source']}' belongs to ALLOWED_PREDICTION_SOURCES"
    );

    // Risk level mapping assertion
    $predGwa = (float)$p['predicted_gwa'];
    if ($predGwa < 1.75) {
        assertTest($p['risk_level'] === 'HIGH', "Case {$c['case_id']} predicted_gwa < 1.75 maps to HIGH risk");
    } elseif ($predGwa < 2.50) {
        assertTest($p['risk_level'] === 'MODERATE', "Case {$c['case_id']} predicted_gwa 1.75..2.49 maps to MODERATE risk");
    } else {
        assertTest($p['risk_level'] === 'LOW', "Case {$c['case_id']} predicted_gwa >= 2.50 maps to LOW risk");
    }

    // Completeness metadata assertion
    assertTest(
        in_array($p['data_completeness'], ['complete', 'historical_only', 'prelim_only'], true),
        "Case {$c['case_id']} data_completeness '{$p['data_completeness']}' is in valid domain"
    );
    assertTest(
        in_array($p['is_provisional'], [0, 1], true),
        "Case {$c['case_id']} is_provisional is binary (0 or 1)"
    );

    if ($p['data_completeness'] === 'complete') {
        assertTest($p['is_provisional'] === 0 && $p['provisional_basis'] === null, "Case {$c['case_id']} complete record is not provisional");
    } else {
        assertTest($p['is_provisional'] === 1 && $p['provisional_basis'] !== null, "Case {$c['case_id']} partial record is provisional with stated basis");
    }

    assertTest(
        $p['input_subject_count'] <= $p['expected_subject_count'],
        "Case {$c['case_id']} input_subject_count ({$p['input_subject_count']}) <= expected ({$p['expected_subject_count']})"
    );
}

// -----------------------------------------------------------------------------
// 5. Academic Governance & Neutral Language Boundaries
// -----------------------------------------------------------------------------
echo "\n=== 5. Academic Governance & Neutral Language Boundaries ===\n";

$fixtureRaw = file_get_contents(dirname(__DIR__, 2) . '/tests/fixtures/multiyear_cohort_cases.php');

$forbiddenPhrases = ["Dean's List", "caliber", "blocks", "ineligible"];
foreach ($forbiddenPhrases as $phrase) {
    $found = stripos($fixtureRaw, $phrase) !== false;
    assertTest(!$found, "multiyear_cohort_cases.php has zero occurrences of forbidden phrase '$phrase'");
}

foreach ($cases as $c) {
    // Year levels must be strictly 1, 2, 3, or 4
    assertTest(
        in_array($c['year_level'], [1, 2, 3, 4], true),
        "Case {$c['case_id']} year_level ({$c['year_level']}) is strictly within supported 1-4 range"
    );

    // Cohort E must never be called "Fifth Year"
    assertTest(
        $c['cohort_name'] !== 'Fifth Year' && !str_contains($c['cohort_key'], 'FIFTH'),
        "Case {$c['case_id']} cohort is not represented as a formal 'Fifth Year'"
    );

    // Prerequisite phrasing verification
    if (isset($c['prerequisite_case']) && $c['prerequisite_case'] !== null) {
        assertTest(
            $c['expected_portal_behavior'] === 'record remains visible for later curriculum review',
            "Case {$c['case_id']} prerequisite expected_portal_behavior is neutral ('record remains visible for later curriculum review')"
        );
    }
}

// Check seeder script source code integrity
$seederSource = file_get_contents($seederScript);
assertTest(
    !str_contains($seederSource, 'updated_at'),
    "tools/seed_multiyear_cohorts.php does not reference non-existent updated_at column"
);
assertTest(
    str_contains($seederSource, "\$dbName !== 'udm_radar_scratch'") && !str_contains($seederSource, "'demo'    => 'udm_radar_demo'"),
    "tools/seed_multiyear_cohorts.php strictly allows only udm_radar_scratch and excludes demo target"
);
assertTest(
    str_contains($seederSource, "COHORT_TEST_PASSWORD"),
    "tools/seed_multiyear_cohorts.php hashes COHORT_TEST_PASSWORD for seeded accounts"
);
assertTest(
    str_contains($seederSource, "recalculateStudentGwa"),
    "tools/seed_multiyear_cohorts.php utilizes canonical recalculateStudentGwa for cumulative GWA"
);

echo "\n========================================================================\n";
echo "SUMMARY: Total Tests: $totalTests, Failures: $failedTests\n";
echo "========================================================================\n";

exit($failedTests > 0 ? 1 : 0);
