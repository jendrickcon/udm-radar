<?php
/**
 * tests/integration/faculty_trend_midterm_visibility_test.php
 * Integration Regression Test: Faculty Performance Trends Midterm Grade Visibility
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Runtime database identity is udm_radar_scratch.
 * 2. Assigned class loads in Third-Year sections (e.g. IT-35, IT-34) have complete Prelim and Midterm records.
 * 3. Exact faculty/trend.php queries retrieve both Prelim and Midterm records.
 * 4. Estimated Grade Point Trajectory contains both Prelim and Midterm points.
 * 5. Risk Distribution Across Grading Periods has non-gray Midterm distribution.
 * 6. Latest period label evaluates to 'Midterm' with accurate Midterm mean percentage.
 * 7. Encoding completeness evaluates to 100% for the Midterm period.
 * 8. Baseline Analysis banner is suppressed when Midterm data is present.
 * 9. Significant Changes table is populated with score shifts >= 5%.
 *
 * Run via CLI: php tests/integration/faculty_trend_midterm_visibility_test.php
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
        echo "  [PASS] $desc\n";
        $passed++;
    } else {
        echo "  [FAIL] $desc: $detail\n";
        $failed++;
    }
}

echo "=== Faculty Performance Trends Midterm Grade Visibility Test ===\n";

$db = getDB();
$currentDb = $db->query('SELECT DATABASE()')->fetchColumn();
assertTest('Target database is udm_radar_scratch', $currentDb === 'udm_radar_scratch', "Got $currentDb");

if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Unsafe target database. Aborting.\n");
    exit(1);
}

// Test Case 1: Section IT-35, Subject 37 (Networking 1, taught by user 302)
echo "\n--- Test Case 1: IT-35 (Subject 37, Networking 1) ---\n";
$currentTerm = getCurrentTerm();
$syFilter = $currentTerm['school_year'];
$semFilter = (string)$currentTerm['semester'];
$subjFilter = 37;
$secFilter = 'IT-35';

$stmtGrades = $db->prepare("
    SELECT g.student_id, u.first_name, u.last_name,
           g.prelim, g.midterm, g.prefinal, g.final_grade
    FROM grades g
    JOIN users u ON u.id = g.student_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE g.subject_id = ? AND sp.section = ? AND g.school_year = ? AND g.semester = ? AND g.is_current = 1 AND sp.record_status = 'Active'
");
$stmtGrades->execute([$subjFilter, $secFilter, $syFilter, $semFilter]);
$grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

$enrolledCount = count($grades);
assertTest("IT-35 enrolled students count is 37", $enrolledCount === 37, "Got $enrolledCount");

$periodEncoded = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];
$rawSums       = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];
$ptSums        = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];
$numericCounts = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];
$riskMovement  = [
    'Prelim'    => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
    'Midterm'   => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
    'Pre-Final' => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
    'Final'     => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0]
];
$significantChanges = [];

$pMap = ['Prelim' => 'prelim', 'Midterm' => 'midterm', 'Pre-Final' => 'prefinal', 'Final' => 'final_grade'];

foreach ($grades as $g) {
    $stuName = formatNameLastFirst($g['first_name'], '', $g['last_name']);

    foreach ($pMap as $pName => $dbCol) {
        if ($g[$dbCol] !== null && trim((string)$g[$dbCol]) !== '') {
            $periodEncoded[$pName]++;
            $pct = (float)$g[$dbCol];
            $rawSums[$pName] += $pct;
            $pt = normalizeTermGrade($pct);
            if ($pt !== null) {
                $ptSums[$pName] += $pt;
                $numericCounts[$pName]++;
                $riskMovement[$pName][computeRiskFromAvg($pt)]++;
            }
        } else {
            $riskMovement[$pName]['NONE']++;
        }
    }

    if ($g['prelim'] !== null && $g['midterm'] !== null && trim((string)$g['prelim']) !== '' && trim((string)$g['midterm']) !== '') {
        $pPct = (float)$g['prelim'];
        $mPct = (float)$g['midterm'];
        $diff = $mPct - $pPct;
        if (abs($diff) >= 5.0) {
            $ptTerm = normalizeTermGrade($mPct) ?? 0.0;
            $significantChanges[] = [
                'name' => $stuName,
                'diff' => $diff,
                'current_risk' => computeRiskFromAvg($ptTerm)
            ];
        }
    }
}

assertTest("Prelims encoded count is 37 of 37", $periodEncoded['Prelim'] === 37, "Got {$periodEncoded['Prelim']}");
assertTest("Midterms encoded count is 37 of 37", $periodEncoded['Midterm'] === 37, "Got {$periodEncoded['Midterm']}");

// Determine Latest Period
$latestPeriodName = 'Preliminary';
$latestMean = null;
$completenessPct = 0;

if ($periodEncoded['Final'] > 0) {
    $latestPeriodName = 'Final';
} elseif ($periodEncoded['Pre-Final'] > 0) {
    $latestPeriodName = 'Pre-Final';
} elseif ($periodEncoded['Midterm'] > 0) {
    $latestPeriodName = 'Midterm';
    $latestMean = round((float) ($rawSums['Midterm'] / $numericCounts['Midterm'])) . '%';
    $completenessPct = ($periodEncoded['Midterm'] / $enrolledCount) * 100;
} elseif ($periodEncoded['Prelim'] > 0) {
    $latestPeriodName = 'Preliminary';
}

assertTest("Latest period label is 'Midterm'", $latestPeriodName === 'Midterm', "Got $latestPeriodName");
assertTest("Latest period mean is evaluated ('85%')", $latestMean === '85%', "Got $latestMean");
assertTest("Data completeness evaluates to 100.0%", abs((float)$completenessPct - 100.0) < 0.001, "Got $completenessPct");

// Trajectory data
$chartMeans = [
    'Prelim' => round($ptSums['Prelim'] / $numericCounts['Prelim'], 2),
    'Midterm' => round($ptSums['Midterm'] / $numericCounts['Midterm'], 2)
];
assertTest("Estimated Grade Point Trajectory has valid Prelim point", $chartMeans['Prelim'] > 0, "Got {$chartMeans['Prelim']}");
assertTest("Estimated Grade Point Trajectory has valid Midterm point", $chartMeans['Midterm'] > 0, "Got {$chartMeans['Midterm']}");

// Risk distribution non-gray
$midtermEvaluated = $riskMovement['Midterm']['HIGH'] + $riskMovement['Midterm']['MODERATE'] + $riskMovement['Midterm']['LOW'];
assertTest("Risk distribution for Midterm is evaluated (non-gray)", $midtermEvaluated === 37, "Evaluated: $midtermEvaluated, None: {$riskMovement['Midterm']['NONE']}");

// Baseline notice suppression
$showBaselineNotice = ($periodEncoded['Midterm'] == 0 && $periodEncoded['Prelim'] > 0);
assertTest("Baseline Analysis banner is suppressed (2 periods encoded)", !$showBaselineNotice, "Notice shown: " . ($showBaselineNotice ? 'yes' : 'no'));

// Significant changes
assertTest("Significant score shifts (>= 5%) detected", count($significantChanges) >= 1, "Got " . count($significantChanges));

// Test Case 2: Section IT-34, Subject 38 (HCI, taught by user 2)
echo "\n--- Test Case 2: IT-34 (Subject 38, HCI) ---\n";
$stmtGrades->execute([38, 'IT-34', $syFilter, $semFilter]);
$grades34 = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);
$mCount34 = 0; $pCount34 = 0;
foreach ($grades34 as $g) {
    if ($g['prelim'] !== null) $pCount34++;
    if ($g['midterm'] !== null) $mCount34++;
}
assertTest("IT-34 enrolled students count is 40", count($grades34) === 40, "Got " . count($grades34));
assertTest("IT-34 Prelims encoded count is 40", $pCount34 === 40, "Got $pCount34");
assertTest("IT-34 Midterms encoded count is 40", $mCount34 === 40, "Got $mCount34");

echo "\n=== Summary ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    exit(1);
}
