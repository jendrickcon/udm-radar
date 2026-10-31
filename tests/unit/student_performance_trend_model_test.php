<?php
require_once __DIR__ . '/../../student/performance_trend_model.php';
$checks = 0;
function trendCheck(bool $condition, string $label): void {
    global $checks; $checks++;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
function grade(int $id, string $year, int $sem, mixed $final, int $current=0, mixed $prelim=null, mixed $midterm=null, mixed $prefinal=null): array {
    return ['id'=>$id,'student_id'=>1,'school_year'=>$year,'semester'=>$sem,'final_grade'=>$final,'is_current'=>$current,'prelim'=>$prelim,'midterm'=>$midterm,'prefinal'=>$prefinal,'units'=>3];
}
$empty = studentTrendModel([], []);
trendCheck($empty['latest'] === null && $empty['estimate'] === null, 'Empty input invents no scalar or estimate');
trendCheck(count($empty['points']) === 1 && $empty['points'][0]['value'] === null, 'Unavailable estimate has a table record');
$rows = [grade(1,'2024-2025',1,'2.00'),grade(2,'2024-2025',2,'3.00'),grade(3,'2025-2026',1,null,1,80,90)];
$model = studentTrendModel($rows, ['predicted_gwa'=>2.15,'risk_level'=>'LOW','prediction_source'=>'calculation_fallback','data_completeness'=>'complete']);
trendCheck($model['latest'] === 3.0, 'Latest completed GWA excludes pending current term');
trendCheck(str_contains($model['change'], 'increased by 1.00 points'), 'Adjacent completed-term delta uses accepted helper');
trendCheck($model['points'][2]['value'] === null, 'Incomplete current final remains null');
trendCheck($model['points'][3]['zone'] === 'current' && $model['points'][3]['value'] === convertPercentageToPoint(80), 'Preliminary snapshot uses existing raw-percent conversion');
trendCheck(str_contains($model['points'][3]['detail'], 'Pre-Final 0/1'), 'Coverage denominator counts actual recorded subject slots');
trendCheck($model['estimate'] === 2.15, 'Stored estimate preserved, without fabricating interval');
trendCheck(str_contains(end($model['points'])['detail'],'Calculation fallback'), 'Estimate source derives from accepted prediction helper');
trendCheck(str_contains(end($model['points'])['period'],'target period not supplied'), 'Estimate not falsely assigned to current academic period');
trendCheck(str_contains(end($model['points'])['detail'],'timestamp unavailable'), 'Absent generation time remains unavailable');
$partial = studentTrendModel([grade(1,'2024-2025',1,'3.00'),grade(2,'2024-2025',1,null)], []);
trendCheck($partial['latest'] === null && $partial['points'][0]['value'] === null, 'Numeric plus missing final is not a complete-term GWA');
$inc = studentTrendModel([grade(1,'2024-2025',1,'INC')], []);
trendCheck($inc['points'][0]['value'] === null, 'INC-only term remains null');
$zero = studentTrendModel([grade(1,'2024-2025',1,'0.00')], []);
trendCheck($zero['latest'] === null, 'Canonical zero exclusion is preserved');
$gap = studentTrendModel([grade(1,'2023-2024',1,'2.00'),grade(2,'2024-2025',2,'3.00')], []);
trendCheck(!$gap['points'][1]['connect'] && str_contains($gap['change'],'unavailable'), 'Unrepresented regular terms break continuity and change');
$knownGap = studentTrendModel([grade(1,'2023-2024',1,'2.00'),grade(2,'2023-2024',2,null),grade(3,'2024-2025',1,'3.00')], []);
trendCheck($knownGap['points'][1]['value'] === null && str_contains($knownGap['change'],'unavailable'), 'Known incomplete term is not skipped in growth annotation');
$summer = studentTrendModel([grade(1,'2024-2025',2,'2.00'),grade(2,'2024-2025',3,'3.00')], []);
trendCheck(str_contains($summer['change'],'unavailable'), 'Summer versus regular comparison withheld');
$ordered = studentTrendModel(array_reverse($rows), []);
trendCheck($ordered['points'][0]['key'] === '2024-2025|1', 'Input row order cannot reverse chronological presentation');
$duplicate = studentTrendModel([grade(1,'2024-2025',1,'3.00'),grade(1,'2024-2025',1,'3.00')], []);
trendCheck(str_contains($duplicate['points'][0]['detail'],'1 of 1'), 'Accepted aggregation deduplicates grade IDs');
$completedCurrent = studentTrendModel([grade(1,'2025-2026',1,'3.00',1)], []);
trendCheck($completedCurrent['latest'] === 3.0, 'Fully recorded current term can supply completed result');
$source = file_get_contents(__DIR__.'/../../student/trend.php');
trendCheck(str_contains($source,"WHERE g.student_id=?") && str_contains($source,"requireRole('student')"), 'Source inspection: Student session scoping retained (not live authorization proof)');
trendCheck((str_contains($source,"foreach (\$model['accessible_summary']") || str_contains($source,"foreach (\$model['points']")) && str_contains($source,"json_encode(\$model['points']"), 'Source inspection: chart and table consume model payloads');
$tableStart = strpos($source, '<table');
$tableEnd = strpos($source, '</table>');
$tableContent = ($tableStart !== false && $tableEnd !== false) ? substr($source, $tableStart, $tableEnd - $tableStart) : '';
trendCheck($tableContent !== '' && !str_contains($tableContent, '<details'), 'No nested details elements inside exact values table');
trendCheck($tableContent !== '' && !str_contains($tableContent, '<summary'), 'No nested summary elements inside exact values table');
trendCheck(!str_contains($source, 'Expand a period for its source and completeness'), 'Technical instruction copy removed from table caption');
trendCheck(str_contains($source, '<th scope="col">Academic period</th>') && str_contains($source, '<th scope="col">Result type</th>') && str_contains($source, '<th scope="col">Value</th>') && str_contains($source, '<th scope="col">Status</th>'), 'Table columns are Academic period, Result type, Value, and Status');
trendCheck(!str_contains($source, '[?]'), 'Literal [?] replaced with SVG help icon');
trendCheck(str_contains($source, 'Your performance over time') && str_contains($source, 'Recorded results, current progress, and estimate.'), 'Chart title and concise subtitle present');
trendCheck(str_contains($source, 'Honors guides are reference thresholds.') && str_contains($source, 'Official eligibility remains subject to institutional review.'), 'Honors disclaimer present with distinct reference lines');
trendCheck(str_contains($source, 'Completed-term GWA uses finalized results. Current progress and estimated GWA are shown separately.'), 'Table note follows finalized results contract');
foreach ([['2.25','2.50','Your GWA increased by 0.25 points (11.11%)'],['2.50','2.25','Your GWA decreased by 0.25 points (10.00%)'],['2.50','2.50','Your GWA stayed the same (0.00%)']] as [$before,$after,$expected]) {
    $relative = studentTrendModel([grade(1,'2024-2025',1,$before),grade(2,'2024-2025',2,$after)], []);
    trendCheck(str_contains($relative['change'], $expected), 'Plain-language direction and relative percent '.$before.' to '.$after);
}
foreach (['0.00',null] as $before) {
    $relative = studentTrendModel([grade(1,'2024-2025',1,$before),grade(2,'2024-2025',2,'2.50')], []);
    trendCheck(!str_contains($relative['change'],'%'), 'No percent for zero/missing previous completed GWA');
}
$exampleRows = [];
foreach (['3.00','3.00','3.00','3.25','3.25'] as $i => $final) $exampleRows[] = grade($i+1,'2025-2026',1,$final);
foreach (array_merge(array_fill(0,16,'3.25'),array_fill(0,9,'3.50')) as $i => $final) $exampleRows[] = grade($i+6,'2025-2026',2,$final);
$sameYear = studentTrendModel($exampleRows, []);
trendCheck($sameYear['change'] === 'Your GWA increased by 0.24 points (7.74%). Comparison: First Semester to Second Semester, SY 2025-2026.', 'Same-year comparison explains the screenshot without repeated year labels');
$crossYear = studentTrendModel([grade(1,'2024-2025',2,'2.00'),grade(2,'2025-2026',1,'2.50')], []);
trendCheck(str_contains($crossYear['change'],'SY 2024-2025 — Second Semester to SY 2025-2026 — First Semester'), 'Cross-year comparison preserves both complete period labels');
foreach ([['', 1], ['2025-2026', 0], ['Unknown', 3], ['2025-2025', 1], ['2025-2027', 2]] as [$year, $semester]) {
    $dated = grade(1, '2024-2025', 2, '2.00');
    $undated = grade(2, $year, $semester, '4.00', 1, 100, 100);
    $safe = studentTrendModel([$undated, $dated], []);
    trendCheck($safe['latest'] === 2.0 && $safe['snapshot'] === null, 'Invalid period cannot replace dated result or current snapshot: '.$year.'|'.$semester);
    trendCheck($safe['points'][1]['value'] === null && $safe['points'][1]['period'] === 'Academic period unavailable', 'Invalid period stays separate and unavailable');
}
$missing = grade(2, '', 1, '4.00');
unset($missing['school_year'], $missing['semester']);
trendCheck(studentTrendModel([grade(1,'2024-2025',2,'2.00'), $missing], [])['latest'] === 2.0, 'Absent period fields handled safely');
$ties = [grade(1,'2024-2025',2,'2.00'), grade(2,'2024-2025',2,'3.00')];
trendCheck(studentTrendModel($ties, []) === studentTrendModel(array_reverse($ties), []), 'Identical-period subjects aggregate deterministically');
$summerPair = studentTrendModel([grade(1,'2023-2024',3,'2.00'),grade(2,'2024-2025',1,null),grade(3,'2024-2025',2,'4.00'),grade(4,'2024-2025',3,'2.50')], []);
trendCheck($summerPair['latest'] === 2.5, 'Summer follows second semester chronologically');
trendCheck($summerPair['change'] === 'Your GWA increased by 0.50 points (25.00%). Comparison: SY 2023-2024 — Summer to SY 2024-2025 — Summer.', 'Summer comparison preserves points, percentage and exact labels across regular terms');
trendCheck(str_contains(studentTrendModel([grade(1,'2024-2025',3,'2.50')], [])['change'], 'unavailable'), 'Summer without earlier Summer has unavailable change');
$pendingSummer = studentTrendModel([grade(1,'2022-2023',3,'2.00'),grade(2,'2023-2024',3,null),grade(3,'2024-2025',3,'2.50')], []);
trendCheck(str_contains($pendingSummer['change'], 'unavailable'), 'Known incomplete Summer prevents skipping to an older Summer');

// Additional coverage for simplified student academic information flow
$directGrowth = computeStudentGrowth($exampleRows, ['school_year' => '2026-2027', 'semester' => 1]);
trendCheck($directGrowth['is_available'] === true, 'computeStudentGrowth reports availability for valid adjacent terms');
trendCheck($directGrowth['point_change'] === 0.24, 'computeStudentGrowth point change is exactly +0.24');
trendCheck($directGrowth['relative_change_pct'] === 7.74, 'computeStudentGrowth relative percent change is exactly +7.74%');
trendCheck($directGrowth['direction'] === 'Improving', 'computeStudentGrowth direction is Improving');
trendCheck(str_contains($directGrowth['formatted_points'], '+0.24 GWA points'), 'computeStudentGrowth formatted_points follows contract');
trendCheck(str_contains($directGrowth['formatted_relative'], '+7.74% from previous comparable term'), 'computeStudentGrowth formatted_relative follows contract');

$summerRegularGrowth = computeStudentGrowth([grade(1,'2024-2025',2,'2.00'), grade(2,'2024-2025',3,'3.00')]);
trendCheck($summerRegularGrowth['is_available'] === false, 'computeStudentGrowth withholds comparison between Summer and regular semester');

$growthModel = studentTrendModel($exampleRows, [], ['school_year' => '2026-2027', 'semester' => 1]);
trendCheck(isset($growthModel['growth']) && $growthModel['growth']['is_available'] === true, 'studentTrendModel payload embeds growth contract');
trendCheck(isset($growthModel['current_term_stages']) && is_array($growthModel['current_term_stages']), 'studentTrendModel payload embeds current_term_stages');
trendCheck(isset($growthModel['accessible_summary']) && is_array($growthModel['accessible_summary']), 'studentTrendModel payload embeds accessible_summary');

$stageRows = [grade(1, '2026-2027', 1, null, 1, 87.55, null)];
$stageModel = studentTrendModel($stageRows, [], ['school_year' => '2026-2027', 'semester' => 1]);
trendCheck($stageModel['current_term_stages']['prelim']['status'] === 'Recorded', 'Prelim stage accurately detects recorded raw score');
trendCheck($stageModel['current_term_stages']['prelim']['percentage'] === 87.55, 'Prelim stage retains raw percentage 87.55% without GWA conversion');
trendCheck($stageModel['current_term_stages']['midterm']['status'] === 'Not recorded', 'Midterm stage correctly reports unrecorded without zero fallback');
trendCheck($stageModel['current_term_stages']['midterm']['percentage'] === null, 'Midterm percentage is null when unrecorded');

// Current-term consolidation checks in accessible_summary
$consolidatedModel = studentTrendModel($stageRows, ['predicted_gwa' => '2.15'], ['school_year' => '2026-2027', 'semester' => 1]);
$summaryRows = $consolidatedModel['accessible_summary'];
$currentPeriodSummary = array_values(array_filter($summaryRows, fn($r) => $r['period'] === '2026-2027 · Semester 1'));
trendCheck(count($currentPeriodSummary) === 3, 'Exactly three current-term result records exist in accessible summary');
trendCheck($currentPeriodSummary[0]['result_type'] === 'Preliminary progress' && $currentPeriodSummary[0]['hide_period_visually'] === false, 'First current-term row is Preliminary progress with visible period');
trendCheck($currentPeriodSummary[1]['result_type'] === 'Current-term assessment point' && $currentPeriodSummary[1]['value'] === '2.50' && $currentPeriodSummary[1]['hide_period_visually'] === true, 'Second current-term row is Current-term assessment point with converted point 2.50 and visually grouped period');
trendCheck($currentPeriodSummary[2]['result_type'] === 'Estimated GWA' && $currentPeriodSummary[2]['hide_period_visually'] === true, 'Third current-term row is Estimated GWA with visually grouped period');
$unavailableCompletedRows = array_filter($summaryRows, fn($r) => $r['period'] === '2026-2027 · Semester 1' && $r['result_type'] === 'Completed-term GWA');
trendCheck(count($unavailableCompletedRows) === 0, 'No unavailable completed-term GWA row exists for current term');

// 92.94% Preliminary conversion assertions
$example92Rows = [grade(1, '2026-2027', 1, null, 1, 92.94, null)];
$example92Model = studentTrendModel($example92Rows, ['predicted_gwa' => '3.05'], ['school_year' => '2026-2027', 'semester' => 1]);
$example92Summary = array_values(array_filter($example92Model['accessible_summary'], fn($r) => $r['period'] === '2026-2027 · Semester 1'));
trendCheck($example92Summary[1]['result_type'] === 'Current-term assessment point', '92.94% row 2 is Current-term assessment point');
trendCheck($example92Summary[1]['value'] === '3.25', '92.94% converts to exactly 3.25 via authoritative conversion');
trendCheck($example92Summary[1]['status'] === 'Converted · not completed GWA', '92.94% assessment status is Converted · not completed GWA');
trendCheck($example92Model['current_progress']['converted_point'] === 3.25, 'Model current_progress converted_point is exactly 3.25');
trendCheck($example92Model['current_progress']['stage_based_label'] === 'Preliminary-based', 'Model current_progress stage_based_label is Preliminary-based');

// Authoritative percentage-to-point boundary checks
trendCheck(convertPercentageToPoint(99.0) === 4.00, 'Boundary check: 99.0% -> 4.00');
trendCheck(convertPercentageToPoint(97.0) === 3.75, 'Boundary check: 97.0% -> 3.75');
trendCheck(convertPercentageToPoint(95.0) === 3.50, 'Boundary check: 95.0% -> 3.50');
trendCheck(convertPercentageToPoint(92.94) === 3.25, 'Boundary check: 92.94% -> 3.25');
trendCheck(convertPercentageToPoint(92.0) === 3.25, 'Boundary check: 92.0% -> 3.25');
trendCheck(convertPercentageToPoint(90.0) === 3.00, 'Boundary check: 90.0% -> 3.00');
trendCheck(convertPercentageToPoint(88.0) === 2.75, 'Boundary check: 88.0% -> 2.75');
trendCheck(convertPercentageToPoint(86.0) === 2.50, 'Boundary check: 86.0% -> 2.50');
trendCheck(convertPercentageToPoint(75.0) === 1.00, 'Boundary check: 75.0% -> 1.00');
trendCheck(convertPercentageToPoint(74.99) === 0.00, 'Boundary check: 74.99% -> 0.00');
trendCheck(convertPercentageToPoint(0.0) === 0.00, 'Boundary check: 0.0% -> 0.00');

// Latest actually recorded stage selection and context assertions
trendCheck($consolidatedModel['current_progress']['latest_stage_field'] === 'prelim', 'Scenario A: Prelim-only latest stage is prelim');
trendCheck($consolidatedModel['current_progress']['primary_value'] === '87.55%', 'Scenario A: Prelim-only primary value is 87.55%');
trendCheck($consolidatedModel['current_progress']['subtitle'] === 'Preliminary average', 'Scenario A: Prelim-only subtitle is Preliminary average');
trendCheck($consolidatedModel['current_progress']['earlier_context'] === null, 'Scenario A: Prelim-only earlier context is null');

$twoStageRows = [grade(1, '2026-2027', 1, null, 1, 87.55, 91.20)];
$twoStageModel = studentTrendModel($twoStageRows, [], ['school_year' => '2026-2027', 'semester' => 1]);
trendCheck($twoStageModel['current_progress']['latest_stage_field'] === 'midterm', 'Scenario B: Prelim+Midterm latest stage is midterm');
trendCheck($twoStageModel['current_progress']['primary_value'] === '91.20%', 'Scenario B: Prelim+Midterm primary value is Midterm percentage 91.20%');
trendCheck($twoStageModel['current_progress']['subtitle'] === 'Midterm average', 'Scenario B: Prelim+Midterm subtitle is Midterm average');
trendCheck($twoStageModel['current_progress']['earlier_context'] === 'Preliminary: 87.55%', 'Scenario B: Earlier stage context displays Preliminary: 87.55%');
trendCheck($twoStageModel['current_progress']['primary_value'] !== '89.38%', 'Scenario B: Simple arithmetic mean across stages is rejected');

$threeStageRows = [grade(1, '2026-2027', 1, null, 1, 87.55, 91.20, 94.00)];
$threeStageModel = studentTrendModel($threeStageRows, [], ['school_year' => '2026-2027', 'semester' => 1]);
trendCheck($threeStageModel['current_progress']['latest_stage_field'] === 'prefinal', 'Scenario C: Prelim+Midterm+PreFinal latest stage is prefinal');
trendCheck($threeStageModel['current_progress']['primary_value'] === '94.00%', 'Scenario C: Three stages primary value is Pre-Final percentage 94.00%');
trendCheck($threeStageModel['current_progress']['subtitle'] === 'Pre-Final average', 'Scenario C: Three stages subtitle is Pre-Final average');
trendCheck($threeStageModel['current_progress']['earlier_context'] === 'Preliminary: 87.55% · Midterm: 91.20%', 'Scenario C: Earlier stage context displays Preliminary and Midterm');

// Scale separation and non-GWA current-term average contracts
trendCheck($consolidatedModel['latest'] === null, 'Scale separation: Incomplete current term produces no completed GWA');
trendCheck($consolidatedModel['current_progress']['percentage'] === 87.55, 'Scale separation: Assessment value remains raw percentage without 4.00 point scale conversion');
trendCheck($consolidatedModel['current_progress']['primary_value'] === '87.55%', 'Scale separation: Primary value formatted with explicit % unit');
trendCheck($model['latest'] === 3.0, 'Scale separation: Completed GWA scalar strictly excludes current term');
trendCheck($model['estimate'] === 2.15, 'Scale separation: Advisory estimate scalar remains 2.15 point value');

echo "SUMMARY: $checks checks passed; 0 failures. No database accessed.\n";
