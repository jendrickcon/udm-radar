<?php
require_once __DIR__ . '/../../student/performance_trend_model.php';
$checks = 0;
function trendCheck(bool $condition, string $label): void {
    global $checks; $checks++;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
function grade(int $id, string $year, int $sem, mixed $final, int $current=0, mixed $prelim=null, mixed $midterm=null): array {
    return ['id'=>$id,'student_id'=>1,'school_year'=>$year,'semester'=>$sem,'final_grade'=>$final,'is_current'=>$current,'prelim'=>$prelim,'midterm'=>$midterm,'prefinal'=>null,'units'=>3];
}
$empty = studentTrendModel([], []);
trendCheck($empty['latest'] === null && $empty['estimate'] === null, 'Empty input invents no scalar or estimate');
trendCheck(count($empty['points']) === 1 && $empty['points'][0]['value'] === null, 'Unavailable estimate has a table record');
$rows = [grade(1,'2024-2025',1,'2.00'),grade(2,'2024-2025',2,'3.00'),grade(3,'2025-2026',1,null,1,80,90)];
$model = studentTrendModel($rows, ['predicted_gwa'=>2.15,'risk_level'=>'LOW','prediction_source'=>'calculation_fallback','data_completeness'=>'complete']);
trendCheck($model['latest'] === 3.0, 'Latest completed GWA excludes pending current term');
trendCheck(str_contains($model['change'], '+1.00'), 'Adjacent completed-term delta uses accepted helper');
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
trendCheck(str_contains($source,"foreach (\$model['points']") && str_contains($source,"json_encode(\$model['points']"), 'Source inspection: chart and table consume same point payload');
foreach ([['2.25','2.50','+11.11%'],['2.50','2.25','-10.00%'],['2.50','2.50','+0.00%']] as [$before,$after,$expected]) {
    $relative = studentTrendModel([grade(1,'2024-2025',1,$before),grade(2,'2024-2025',2,$after)], []);
    trendCheck(str_contains($relative['change'], 'Supporting relative change: '.$expected), 'Supporting relative percent '.$before.' to '.$after.' = '.$expected);
}
foreach (['0.00',null] as $before) {
    $relative = studentTrendModel([grade(1,'2024-2025',1,$before),grade(2,'2024-2025',2,'2.50')], []);
    trendCheck(!str_contains($relative['change'],'relative change:'), 'No percent for zero/missing previous completed GWA');
}
echo "SUMMARY: $checks checks passed; 0 failures. No database accessed.\n";