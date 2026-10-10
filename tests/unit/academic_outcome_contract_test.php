<?php
/** Database-free behavior and source-inspection regression checks (no HTTP or DB). */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/constants.php';

$checks = 0;
$failures = 0;
function checkOutcome(mixed $actual, mixed $expected, string $message): void {
    global $checks, $failures;
    $checks++;
    if ($actual !== $expected) {
        $failures++;
        echo "[FAIL] $message: " . var_export($actual, true) . "\n";
    }
}

foreach (['1.00' => 'HIGH', '1.25' => 'HIGH', '1.50' => 'HIGH', '1.75' => 'MODERATE', '2.50' => 'LOW'] as $grade => $risk) {
    checkOutcome(isPassingFinalGrade($grade), true, "$grade passes");
    checkOutcome(computeRiskFromAvg((float)$grade), $risk, "$grade retains independent risk");
}
checkOutcome(isIncompleteFinalGrade(' inc '), true, 'INC normalization');
foreach ([null, '', 'invalid', '1.10', '0.50', '0.75'] as $grade) {
    checkOutcome(isPassingFinalGrade($grade), false, 'Invalid grade is not passed');
    checkOutcome(isFailingFinalGrade($grade), false, 'Invalid grade is not failed');
    checkOutcome(isIncompleteFinalGrade($grade), false, 'Invalid grade is not incomplete');
}

// Honors must remain equivalent to the PRE-hotfix predicate for the full domain.
foreach (array_merge(FINAL_GRADE_POINTS, FINAL_GRADE_STATUSES, ['0.00', null, '', 'invalid']) as $grade) {
    $canon = canonicalizeFinalGrade($grade);
    $legacyDisqualification = $canon === '0.00'
        || in_array($canon, ['INC', 'DO', 'DU', 'FA', 'UD'], true)
        || (in_array($canon, FINAL_GRADE_POINTS, true) && (float)$canon < 1.75);
    checkOutcome(isHonorsDisqualifyingFinalGrade($grade), $legacyDisqualification, 'Honors predicate preserved');
    checkOutcome(getLatinHonor(3.90, isHonorsDisqualifyingFinalGrade($grade)),
        $legacyDisqualification ? 'Not Eligible' : 'Summa Cum Laude', 'Honors result preserved');
}
checkOutcome(computeWeightedGWA([
    ['grade' => '1.00', 'units' => 3], ['grade' => '1.25', 'units' => 3],
    ['grade' => '1.50', 'units' => 3], ['grade' => '4.00', 'units' => 3],
    ['grade' => '0.00', 'units' => 3], ['grade' => 'INC', 'units' => 3], ['grade' => 'P', 'units' => 3],
]), 1.94, 'GWA inclusion/exclusion preserved');

foreach (['1.00', '1.25', '1.50', '0.00', 'INC'] as $grade) {
    $features = computeHistoricalFailureFeatures([
        ['final_grade' => $grade, 'school_year' => '2025-2026', 'semester' => 1],
    ]);
    $failure = $grade === '0.00' ? 1 : 0;
    checkOutcome($features, ['failed_subjects_count' => $failure, 'irregular_semesters' => $failure],
        "$grade future failure features");
}
checkOutcome(computeHistoricalFailureFeatures([
    ['final_grade' => '0.00', 'school_year' => '2025-2026', 'semester' => 1],
    ['final_grade' => '0.00', 'school_year' => '2025-2026', 'semester' => 1],
    ['final_grade' => 'INC', 'school_year' => '2025-2026', 'semester' => 2],
]), ['failed_subjects_count' => 2, 'irregular_semesters' => 1], 'Failure semesters deduplicated');

// Execute only the actual pure aggregation blocks, never portal bootstraps/SQL.
// This is repository source-inspection evidence, not a live application test.
function aggregatePortalFixture(string $relativePath, array $grades): array {
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    $start = strpos($source, '$histStudents = [];');
    $end = strpos($source, '    usort($histSubjects', $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Historical aggregation boundaries not found');
    }
    $rawHistGrades = [];
    foreach ($grades as $index => $grade) {
        $rawHistGrades[] = ['student_id' => $index + 1, 'id' => 1, 'code' => 'TEST', 'title' => 'Synthetic fixture', 'final_grade' => $grade];
    }
    $histSubjects = [];
    $histDroppedCount = $histTotalGradedCount = $histGradesCount = $histPassedCount = $histFailedCount = 0;
    $histGradesSum = 0.0;
    eval(substr($source, $start, $end - $start));
    return $histSubjects[1];
}
foreach ([
    [['1.00', '1.25', '1.50', '0.00', 'INC', 'P', 'DRP', null, '', '1.10'], 4, 1, 1, 5],
    [['INC', ' inc '], 0, 0, 2, 0],
    [['DO', 'DU', 'FA', 'UD', 'DRP'], 0, 4, 0, 4],
] as [$grades, $passed, $failed, $incomplete, $recognized]) {
    $screen = aggregatePortalFixture('admin/analytics.php', $grades);
    $pdf = aggregatePortalFixture('admin/export_program_analytics_pdf.php', $grades);
    checkOutcome($screen, $pdf, 'Screen/PDF outcome aggregation parity');
    checkOutcome($screen['passed'], $passed, 'Passing count');
    checkOutcome($screen['failed'], $failed, 'Failed count (deferred statuses preserved)');
    checkOutcome($screen['incomplete_count'], $incomplete, 'Unresolved count');
    checkOutcome($screen['recognized_outcome_count'], $recognized, 'Recognized denominator excludes INC');
}
// Actual Faculty Trends aggregation: encoded coverage must not be the mean divisor.
function aggregateFacultyTrendFixture(array $finals): array {
    $source = file_get_contents(dirname(__DIR__, 2) . '/faculty/trend.php');
    $start = strpos($source, '$enrolledCount = count($grades);');
    $end = strpos($source, '$chartLineData = [', $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Faculty trend aggregation boundaries not found');
    }
    $periods = ['Prelim', 'Midterm', 'Pre-Final', 'Final'];
    $periodMeans = $chartMeans = array_fill_keys($periods, null);
    $periodEncoded = array_fill_keys($periods, 0);
    $riskMovement = array_fill_keys($periods, ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0]);
    $significantChanges = [];
    $grades = array_map(fn($grade) => ['first_name' => 'Synthetic', 'last_name' => 'Fixture',
        'prelim' => null, 'midterm' => null, 'prefinal' => null, 'final_grade' => $grade], $finals);
    eval('if (true) {' . substr($source, $start, $end - $start));
    return [$periodMeans['Final'], $chartMeans['Final'], $periodEncoded['Final'], $latestMean];
}
checkOutcome(aggregateFacultyTrendFixture(['4.00', '1.00', 'INC', 'P']), [2.5, 2.5, 4, '2.50'],
    'Faculty numeric mean excludes textual finals while preserving encoded coverage');
checkOutcome(aggregateFacultyTrendFixture(['INC', 'INC']), [null, null, 2, 'N/A'],
    'All-INC Faculty means are absent, not numeric zero');

echo "SUMMARY: Ran $checks tests, $failures failures.\n";
exit($failures ? 1 : 0);
