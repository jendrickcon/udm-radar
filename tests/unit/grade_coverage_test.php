<?php
require_once dirname(__DIR__) . '/fixtures/admin_coverage_fixture.php';
$checks=$failures=0;
function coverageCheck(bool $pass,string $label):void{global $checks,$failures;$checks++;if(!$pass)$failures++;echo ($pass?'[PASS] ':'[FAIL] ').$label.PHP_EOL;}
$r=coverageFixtureRecords();
coverageCheck($r[0]['counts']===['prelim'=>1,'midterm'=>1,'prefinal'=>1],'Complete period availability');
coverageCheck($r[0]['inputs']==='complete'&&$r[0]['prediction_state']==='complete','Both model features and complete stored prediction');
coverageCheck($r[1]['counts']['prelim']===1&&$r[1]['expected']===2,'Partial recorded coverage uses actual two-row denominator');
coverageCheck($r[1]['prediction_state']==='provisional'&&$r[1]['source']==='decision_tree','Decision Tree can remain visibly provisional');
coverageCheck(!$r[2]['has_numeric']&&$r[2]['inputs']==='insufficient','All-INC is unavailable model data');
coverageCheck(isset($r[2]['outcomes']['INC']),'INC preserved as text');
coverageCheck($r[3]['has_numeric']&&$r[3]['inputs']==='partial','Mixed numeric/INC availability distinct from model features');
coverageCheck(isset($r[3]['outcomes']['1.50'],$r[3]['outcomes']['INC']),'Mixed outcomes preserved');
coverageCheck($r[3]['source_label']==='Calculation-based estimate','Fallback uses plain calculation label');
coverageCheck($r[4]['source_label']==='Earlier calculation method'&&$r[4]['prediction_state']==='unknown','Legacy flag zero never means complete metadata');
coverageCheck($r[4]['snapshot']==='Not available','Legacy denominator remains not available');
coverageCheck($r[5]['counts']['prelim']===1&&$r[5]['missing_counts']['prelim']===0,'Actual zero percentage is encoded, not missing');
coverageCheck(isset($r[5]['outcomes']['0.00']),'Actual failed zero outcome preserved');
coverageCheck($r[6]['expected']===0&&$r[6]['inputs']==='insufficient','No records has no invented denominator');
coverageCheck(gradeCoveragePercent(0,0)==='N/A','Undefined denominator is unavailable');
coverageCheck(gradeCoveragePercent(0,7)==='0.0%','Zero count with valid denominator');
coverageCheck(gradeCoveragePercent(1,2)==='50.0%','Percentage denominator contract');
$a=gradeCoverageRecord(['inputs'=>'complete','expected'=>99],[],[],null);
coverageCheck($a['inputs']==='insufficient'&&$a['expected']===0,'Derived coverage cannot be overridden by identity fields');
coverageCheck($r[1]['basis']==='Historical GWA only','Provisional basis has a readable canonical explanation');
foreach(['INC','DO','DU','DRP','FA','UD','P'] as $text){$a=gradeCoverageRecord([], [coverageFixtureGrades(['final_grade'=>$text])], [],null);coverageCheck(!$a['has_numeric']&&isset($a['outcomes'][$text]),"$text never coerced to zero");}
$a=gradeCoverageRecord([], [coverageFixtureGrades(['prelim'=>'INC'])],[],null);
coverageCheck($a['counts']['prelim']===0&&$a['missing_counts']['prelim']===0,'Invalid present value is not misreported as absent or zero');
$a=gradeCoverageRecord([],[],[],coverageFixturePrediction(['is_provisional'=>1]));
coverageCheck($a['prediction_state']==='provisional','Provisional flag takes priority over complete label');
$a=gradeCoverageRecord([],[],[],coverageFixturePrediction(['prediction_source'=>'unknown-source','data_completeness'=>null]));
coverageCheck($a['source']==='unknown'&&$a['prediction_state']==='unknown','Unknown source and metadata preserved');
$a=gradeCoverageRecord([],[],[],coverageFixturePrediction(['input_subject_count'=>3,'expected_subject_count'=>2]));
coverageCheck($a['snapshot']==='Not available','Inconsistent snapshot counts not displayed as valid coverage');
$a=gradeCoverageRecord([],[],[],coverageFixturePrediction(['input_subject_count'=>0,'expected_subject_count'=>0]));
coverageCheck($a['snapshot']==='Not available','Zero snapshot denominator not promoted to completeness');
foreach($r as $record)coverageCheck(!array_key_exists('risk_level',$record),'Coverage never infers a risk category');
ob_start();renderGradeCoverage($r,getCurrentTerm());$html=ob_get_clean();
coverageCheck(str_contains($html,'Source not recognized')&&str_contains($html,'0 students'),'Zero-count provenance category remains visible');
coverageCheck(!str_contains($html,'legacy_unknown'),'legacy_unknown is never displayed in HTML');
coverageCheck(str_contains($html,'Preliminary prediction'),'Preliminary prediction is explicit');
coverageCheck(str_contains($html,'Details unavailable'),'legacy metadata translated to plain language Details unavailable');
coverageCheck(str_contains($html,'role="status"')&&str_contains($html,'aria-live="polite"'),'Accessible result status');
coverageCheck(str_contains($html,'Registrar finalization')&&str_contains($html,'Prototype data')&&str_contains($html,'<aside class="card coverage-notice"'),'Non-duplicated compact limitation statement present');
coverageCheck(!preg_match('/\b(stale|fresh|outdated|refresh required)\b/i',$html),'No unsupported recency claim');
ob_start();renderGradeCoverage([],getCurrentTerm());$empty=ob_get_clean();coverageCheck(str_contains($empty,'N/A')&&!str_contains($empty,'NaN'),'Empty population has undefined percentages');
ob_start();renderGradeCoverage([],getCurrentTerm(),true);$error=ob_get_clean();coverageCheck(str_contains($error,'role="alert"')&&str_contains($error,'Retry loading records'),'Load error and retry state');
coverageCheck(str_contains($html,'class="btn btn--quiet coverage-target coverage-back"'),'Back link uses the existing tertiary component');
coverageCheck(str_contains($html,'<details class="card coverage-methodology"><summary class="data-table-title">How this view counts results'),'Methodology is a native closed disclosure titled How this view counts results');
coverageCheck(str_contains($html,'coverage-methodology-rules')&&str_contains($html,'A recorded numeric zero counts as an available result.'),'Methodology structured into concise page-specific counting rules');
coverageCheck(str_contains($html,'id="help-tooltip-results"')&&str_contains($html,'id="help-tooltip-info"')&&str_contains($html,'id="help-tooltip-prediction"'),'Three accessible column-header help tooltips present');
coverageCheck(str_contains($html,'class="card coverage-filter-card"')&&str_contains($html,'id="coverage-page-size"'),'Shared filter card and page-size label present');
coverageCheck(str_contains($html,'value="25"')&&str_contains($html,'value="50"')&&str_contains($html,'value="100"')&&!str_contains($html,'value="all"'),'Only manageable page sizes are offered');
coverageCheck(substr_count($html,'data-pagination hidden')===1,'Single bottom pagination remains hidden until JS enhancement');
coverageCheck(substr_count($html,'class="coverage-detail-row"')===7&&substr_count($html,'class="coverage-detail-cell"')===7&&str_contains($html,'View details for Synthetic Complete'),'Full-width record details have student-specific names');
coverageCheck(str_contains($html,'class="coverage-empty-row" id="coverage-empty"')&&str_contains($html,'No students match the selected filters.')&&str_contains($html,'id="coverage-empty-clear"'),'Empty state rendered inside table body with colspan 5 and Clear filters action');
coverageCheck(!str_contains($html,'—:'),'Recorded outcomes render without malformed placeholders');
coverageCheck(str_contains($html,'id="coverage-kpi-context"')&&str_contains($html,'Current-term results')&&str_contains($html,'Students reviewed</h4>')&&str_contains($html,'id="coverage-complete-title">Complete</h4>')&&str_contains($html,'id="coverage-partial-title">Some missing</h4>')&&str_contains($html,'id="coverage-unavailable-title">Needs review</h4>')&&str_contains($html,'students have results recorded for every current subject row in the selected Preliminary period.'),'Concise sentence-case KPI labels, shared context, and accessible descriptions present');
coverageCheck(str_contains($html,'id="help-tooltip-needs-review"')&&str_contains($html,'no Preliminary results recorded or no current subjects found')&&!str_contains($html,'Active student population'),'Needs review card provides accessible category explanation without redundant subtexts');
coverageCheck(str_contains($html,'All recorded results are shown'),'No-script fallback explicitly retains all results');
coverageCheck(str_contains($html,'Prototype performance on synthetic academic records'),'Synthetic boundary stays visible');
coverageCheck(!str_contains($html,'Eligible numeric records available'),'Redundant developer line removed from Grade information');
coverageCheck(!str_contains($html,'<strong class="coverage-count">')&&str_contains($html,'<span class="coverage-count">'),'Available results use span instead of bold strong tags');
coverageCheck(str_contains($html,'class="btn btn--secondary coverage-detail-toggle"'),'Details action uses compact secondary table-action button');
$paged=coverageFixturePagedRecords();
coverageCheck(count($paged)===125&&count(array_unique(array_column($paged,'id')))===125,'Paged fixture has unique synthetic records');
coverageCheck(array_intersect_key($paged[0],$r[0])===array_replace($r[0],['id'=>1,'name'=>'Synthetic Student 001','student_number'=>'TEST-PAGE-1']),'Paged fixture preserves derived contract fields');
coverageCheck(substr_count($html,'class="stat-card ')===4&&str_contains($html,'stat-grid coverage-metrics'),'Four summaries use existing KPI components');
coverageCheck(str_contains($html,'data-table table-density--compact')&&str_contains($html,'data-table-scroll coverage-table-region'),'Existing table and compact density classes are reused');
coverageCheck(strpos($html,'id="coverage-filters"')<strpos($html,'id="coverage-results"')&&strpos($html,'id="coverage-results"')<strpos($html,'id="coverage-sources"'),'Record task precedes secondary provenance summary');
coverageCheck(substr_count($html,'aria-pressed="false"')===5&&substr_count($html,'data-source-cue>View students')===4&&substr_count($html,'data-source-cue>No matching students')===1,'Source controls expose state and count-aware action cues');
coverageCheck(str_contains($html,'id="coverage-active-filters" class="data-table-subtitle" hidden'),'Default filters do not show an active restriction');
coverageCheck(str_contains($html,'id="coverage-guide-trigger"')&&str_contains($html,'Learn about coverage and prediction sources'),'Guide trigger link present with approved plain-language text');

coverageCheck(str_contains($html,'tooltip-bottom'),'Header tooltips use downward placement to prevent scroll container clipping');
coverageCheck(str_contains($html,'Input details unavailable')&&str_contains($html,'Preliminary result'),'Secondary prediction state uses small muted text');
coverageCheck(!str_contains($html,'class="badge na coverage-badge"'),'Redundant row-level status pills removed');
coverageCheck(str_contains($html,'1 missing')&&str_contains($html,'No results recorded')&&str_contains($html,'No current subjects'),'Exceptional period coverage states remain visibly retained');

// Verification of 8/8 Preliminary results coexisting with 0/8 final outcomes
$coexistStudent=gradeCoverageRecord(
    ['id'=>99,'name'=>'Synthetic Coexist','student_number'=>'TEST-COEXIST','section'=>'SYN-A'],
    array_fill(0, 8, coverageFixtureGrades(['prelim'=>85])),
    [],
    coverageFixturePrediction()
);
coverageCheck($coexistStudent['counts']['prelim']===8&&$coexistStudent['expected']===8,'8 of 8 Preliminary results recorded');
coverageCheck(($coexistStudent['outcomes']['—']??0)===8&&count(array_filter($coexistStudent['outcomes'],fn($k)=>$k!=='—',ARRAY_FILTER_USE_KEY))===0,'0 of 8 final grades recorded');
ob_start();renderGradeCoverage([$coexistStudent],getCurrentTerm());$coexistHtml=ob_get_clean();
coverageCheck(str_contains($coexistHtml,'8 of 8 Preliminary')&&!str_contains($coexistHtml,'<span class="badge na coverage-badge">Complete</span>'),'Coexistence row displays 8 of 8 Preliminary without redundant Complete pill');
coverageCheck(str_contains($coexistHtml,'No final grades recorded yet')&&str_contains($coexistHtml,'0 of 8 subjects have a final grade'),'Coexistence details display 0 of 8 subjects clearly without ambiguity');

foreach(['legacy_unknown','input_subject_count','expected_subject_count','is_provisional'] as $rawTerm){
    coverageCheck(!str_contains($html,$rawTerm),"Raw internal key $rawTerm never displayed in user-facing HTML");
}

echo "SUMMARY: Ran $checks tests, $failures failures.\n";exit($failures?1:0);
