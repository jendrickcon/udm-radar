<?php
require_once dirname(__DIR__, 2) . '/includes/grade_coverage.php';
function coverageFixtureGrades(array $override = []): array {
    return $override + ['prelim' => null, 'midterm' => null, 'prefinal' => null, 'final_grade' => null, 'units' => 3];
}
function coverageFixturePrediction(array $override = []): array {
    return $override + ['prediction_source' => 'decision_tree', 'data_completeness' => 'complete', 'is_provisional' => 0,
        'input_subject_count' => 1, 'expected_subject_count' => 1, 'generated_at' => '2026-10-10 10:00:00'];
}
function coverageFixtureRecords(): array {
    $cases = [
        'Complete' => [[coverageFixtureGrades(['prelim'=>90,'midterm'=>90,'prefinal'=>90,'final_grade'=>'3.00'])], [coverageFixtureGrades(['final_grade'=>'3.00'])], coverageFixturePrediction()],
        'Partial' => [[coverageFixtureGrades(['prelim'=>80]),coverageFixtureGrades(['final_grade'=>'INC'])], [coverageFixtureGrades(['final_grade'=>'2.50'])], coverageFixturePrediction(['is_provisional'=>1,'data_completeness'=>'historical_only','provisional_basis'=>'historical_gwa','expected_subject_count'=>2])],
        'All INC' => [[coverageFixtureGrades(['final_grade'=>'INC'])], [coverageFixtureGrades(['final_grade'=>'INC'])], null],
        'Mixed' => [[coverageFixtureGrades(['final_grade'=>'1.50']),coverageFixtureGrades(['final_grade'=>'INC'])], [coverageFixtureGrades(['final_grade'=>'2.50'])], coverageFixturePrediction(['prediction_source'=>'calculation_fallback','is_provisional'=>1,'data_completeness'=>'historical_only','provisional_basis'=>'historical_gwa','input_subject_count'=>0,'expected_subject_count'=>2])],
        'Legacy' => [[coverageFixtureGrades(['prelim'=>90])], [coverageFixtureGrades(['final_grade'=>'3.00'])], coverageFixturePrediction(['prediction_source'=>'heuristic','data_completeness'=>'legacy_unknown','input_subject_count'=>null,'expected_subject_count'=>null])],
        'Zero' => [[coverageFixtureGrades(['prelim'=>0,'final_grade'=>'0.00'])], [], null],
        'No records' => [[],[],null],
    ];
    $records=[];$id=0;
    foreach($cases as $name=>[$current,$history,$prediction]) {
        $id++;$records[]=gradeCoverageRecord(['id'=>$id,'name'=>'Synthetic '.$name,'student_number'=>'TEST-'.$id,'section'=>$id<4?'SYN-A':'SYN-B'],$current,$history,$prediction);
    }
    return $records;
}
function coverageFixturePagedRecords(): array {
    $templates=coverageFixtureRecords();$records=[];
    for($id=1;$id<=125;$id++) $records[]=array_merge($templates[($id-1)%count($templates)],
        ['id'=>$id,'name'=>'Synthetic Student '.str_pad((string)$id,3,'0',STR_PAD_LEFT),'student_number'=>'TEST-PAGE-'.$id]);
    return $records;
}
if(realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__) {
    $mode=$argv[1]??'default';
    if($mode==='json'){echo json_encode(coverageFixtureRecords(),JSON_THROW_ON_ERROR);exit;}
    echo '<!doctype html><html lang="en" data-theme="light"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.file_get_contents(dirname(__DIR__,2).'/assets/css/dashboard.css').file_get_contents(dirname(__DIR__,2).'/assets/css/grade-coverage.css').'</style></head><body>';
    if($mode==='analytics') {
        $analytics=file_get_contents(dirname(__DIR__,2).'/admin/analytics.php');
        $start=strpos($analytics,'    <div class="header"');$end=strpos($analytics,'    <!-- Wired Interactive Filters -->',$start);
        echo '<main class="main-content">'.substr($analytics,$start,$end-$start).'</main></body></html>';exit;
    }
    if($mode==='analytics_reference') {
        echo '<main class="main-content" style="padding: 24px;"><div class="card" style="margin-bottom: 24px;"><div class="table-title" style="margin-bottom: 4px; font-weight: 600; font-size: 1.1rem; color: var(--text-dark);">Curriculum-Wide Overview (Preliminary)</div><p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Consolidated subject performance across all matching sections.</p><div class="data-table-scroll"><table class="data-table table-density--compact" style="width: 100%; border-collapse: collapse;"><thead><tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);"><th style="padding: 10px 12px; text-align: left; font-weight: 600;">Subject</th><th style="padding: 10px 12px; text-align: left; font-weight: 600;">Title</th><th style="padding: 10px 12px; text-align: center; font-weight: 600;">Enrolled</th><th style="padding: 10px 12px; text-align: center; font-weight: 600;">Mean Score</th><th style="padding: 10px 12px; text-align: right; font-weight: 600;">Action</th></tr></thead><tbody><tr style="border-bottom: 1px solid var(--border-color);"><td style="padding: 8px 12px; font-weight: 600;">CC-101<br><small style="font-weight: 400; color: var(--text-gray);">Core Curriculum</small></td><td style="padding: 8px 12px; font-weight: 400;">Introduction to Computing</td><td style="padding: 8px 12px; text-align: center; font-weight: 400;">45</td><td style="padding: 8px 12px; text-align: center; font-weight: 400;">88.5</td><td style="padding: 8px 12px; text-align: right;"><button type="button" class="btn btn--secondary" style="height: 36px; padding: 0 12px; font-size: 0.8125rem; font-weight: 600;">View record</button></td></tr><tr style="border-bottom: 1px solid var(--border-color);"><td style="padding: 8px 12px; font-weight: 600;">CS-102<br><small style="font-weight: 400; color: var(--text-gray);">Major Subject</small></td><td style="padding: 8px 12px; font-weight: 400;">Computer Programming 1</td><td style="padding: 8px 12px; text-align: center; font-weight: 400;">42</td><td style="padding: 8px 12px; text-align: center; font-weight: 400;">84.2</td><td style="padding: 8px 12px; text-align: right;"><button type="button" class="btn btn--secondary" style="height: 36px; padding: 0 12px; font-size: 0.8125rem; font-weight: 600;">View record</button></td></tr></tbody></table></div></div></main></body></html>';exit;
    }

    renderGradeCoverage($mode==='empty'?[]:($mode==='paged'?coverageFixturePagedRecords():coverageFixtureRecords()),getCurrentTerm(),$mode==='error');
    $_SESSION = ['role' => 'admin'];
    require_once dirname(__DIR__, 2) . '/includes/glossary_modal.php';
    echo '<script>'.file_get_contents(dirname(__DIR__,2).'/assets/js/grade-coverage.js').'</script></body></html>';
}
