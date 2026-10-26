<?php
declare(strict_types=1);
// Hand-checked outcomes catch weighting, missingness, source and timestamp regressions.
$passed=0; $failed=0;
function check(bool $ok,string $label):void {global $passed,$failed; if($ok){$passed++;}else{$failed++;echo "FAIL: $label\n";}}
foreach(['calculations','predictions','interventions'] as $module){
    $path=__DIR__.'/../../includes/analytics/'.$module.'.php';
    if(is_file($path))require_once $path;
}
foreach(['analyticsAggregate','analyticsChange','analyticsTrend','analyticsGroup','analyticsPrediction','analyticsClipRange','analyticsSupportSummary'] as $fn){check(function_exists($fn),"Missing analytics contract: $fn");}
if($failed){echo "RESULT $passed passed, $failed failed\n";exit(1);}
$rows=[
 ['id'=>1,'student_id'=>1,'subject_id'=>1,'final_grade'=>'4.00','units'=>1],
 ['id'=>2,'student_id'=>1,'subject_id'=>2,'final_grade'=>'2.00','units'=>3],
 ['id'=>3,'student_id'=>2,'subject_id'=>1,'final_grade'=>'0.00','units'=>3],
 ['id'=>4,'student_id'=>2,'subject_id'=>2,'final_grade'=>'INC','units'=>3],
 ['id'=>5,'student_id'=>2,'subject_id'=>3,'final_grade'=>'P','units'=>3],
 ['id'=>6,'student_id'=>3,'subject_id'=>1,'final_grade'=>'3.00','units'=>0],
 ['id'=>7,'student_id'=>4,'subject_id'=>1,'final_grade'=>null,'units'=>3],
];
$a=analyticsAggregate($rows);
foreach(['gwa'=>2.5,'student_count'=>4,'contributing_student_count'=>1,'expected_grade_count'=>7,'eligible_grade_count'=>2,'eligible_units'=>4,'recorded_outcome_count'=>6,'resolved_final_count'=>5,'numeric_coverage_pct'=>28.57,'final_coverage_pct'=>71.43,'recognized_outcome_count'=>5,'provisional'=>true] as $key=>$want){check($a[$key]===$want,"Mixed records $key");}
$e=analyticsAggregate([]);check($e['gwa']===null&&$e['numeric_coverage_pct']===null&&$e['student_count']===0,'Empty is unavailable, not zero GWA');
$dup=analyticsAggregate([$rows[0],$rows[0],$rows[1]]);check($dup['gwa']===2.5&&$dup['expected_grade_count']===2,'Duplicate grade join does not multiply records');
foreach(['INC','DRP','DO','DU','FA','UD','P','0.00',null,'bad'] as $grade){check(analyticsAggregate([['id'=>1,'student_id'=>1,'final_grade'=>$grade,'units'=>3]])['gwa']===null,'Excluded outcome stays nonnumeric '.(string)$grade);}
foreach([null,0,-1,'bad'] as $units){check(analyticsAggregate([['id'=>1,'student_id'=>1,'final_grade'=>'4.00','units'=>$units]])['gwa']===null,'Missing/nonpositive units');}
$up=analyticsChange(2.5,3.0);check($up['point_change']===0.5&&$up['relative_change_pct']===20.0&&$up['direction']==='Improving','Point/relative improving change');
$down=analyticsChange(3.0,2.25);check($down['point_change']===-0.75&&$down['relative_change_pct']===-25.0&&$down['direction']==='Declining','Declining change');
check(analyticsChange(2.5,2.5)['direction']==='Stable','Stable at reported precision');
foreach([[null,3.0,true],[0.0,3.0,true],[2.5,null,true],[2.5,3.0,false],[NAN,3.0,true]] as $args){$c=analyticsChange(...$args);check($c['relative_change_pct']===null&&$c['direction']==='Unavailable','Unsafe comparison unavailable');}
$trendRows=[
 ['id'=>1,'student_id'=>1,'subject_id'=>1,'school_year'=>'2025-2026','semester'=>1,'final_grade'=>'2.50','units'=>3],
 ['id'=>2,'student_id'=>1,'subject_id'=>2,'school_year'=>'2025-2026','semester'=>2,'final_grade'=>'3.00','units'=>3],
];
$trend=analyticsTrend($trendRows);check($trend['current']['gwa']===3.0&&$trend['previous']['gwa']===2.5&&$trend['change']['point_change']===0.5,'Chronological comparable term change');
$trendRows[]=['id'=>3,'student_id'=>1,'subject_id'=>3,'school_year'=>'2025-2026','semester'=>2,'final_grade'=>null,'units'=>3];
$trend=analyticsTrend($trendRows);check($trend['current']['provisional']&&$trend['change']['direction']==='Unavailable','Incomplete latest term cannot assert a trend direction');
check($trend['current']['gwa']===null,'Incomplete current scalar is Pending, never a partial term GWA');
$seasonal=[];foreach([[1,'2025-2026',2,'2.50'],[2,'2025-2026',3,'4.00'],[3,'2026-2027',1,'3.00']] as [$id,$sy,$sem,$grade])$seasonal[]=['id'=>$id,'student_id'=>1,'subject_id'=>1,'school_year'=>$sy,'semester'=>$sem,'final_grade'=>$grade,'units'=>3];
check(analyticsTrend($seasonal)['previous']['key']==='2025-2026|2','Regular term excludes intervening summer from comparison');
$seasonal[]=['id'=>4,'student_id'=>1,'subject_id'=>1,'school_year'=>'2026-2027','semester'=>3,'final_grade'=>'3.50','units'=>3];
check(analyticsTrend($seasonal)['previous']['key']==='2025-2026|3','Summer is comparable only with previous summer');
check(analyticsTrend([$trendRows[0]])['previous']===null,'No prior term stays unavailable');
check(analyticsClipRange(0.5,4.5)===['lower'=>1.0,'upper'=>4.0],'Prediction range clips to GWA domain');
check(analyticsClipRange(3.5,2.0)===null&&analyticsClipRange(NAN,3.0)===null,'Reversed/nonfinite bounds unavailable');
$p=analyticsPrediction(['predicted_gwa'=>'2.50','risk_level'=>'HIGH','prediction_source'=>'decision_tree','data_completeness'=>'complete']);
check($p['estimated_gwa']===2.5&&$p['advisory_indicator']==='LOW','Rules derived separately from cached model risk');
check($p['source_family']==='model'&&$p['lower']===null&&$p['upper']===null&&$p['range_status']==='unavailable','No residual evidence means no fabricated range');
foreach(['heuristic','calculation_fallback'] as $source){check(analyticsPrediction(['predicted_gwa'=>2.75,'prediction_source'=>$source,'data_completeness'=>'complete'])['source_family']==='calculation','Fallback stays calculation');}
foreach(['historical_only','prelim_only','legacy_unknown',null] as $complete){check(analyticsPrediction(['predicted_gwa'=>2.75,'prediction_source'=>'decision_tree','data_completeness'=>$complete])['is_provisional'],'Partial/unknown metadata not silently complete');}
foreach([null,0,4.5,'bad',NAN] as $value){$p=analyticsPrediction(['predicted_gwa'=>$value]);check($p['estimated_gwa']===null&&$p['advisory_indicator']===null,'Invalid estimate not risk classified');}
$cases=[['id'=>1,'status'=>'closed','created_at'=>'2026-09-01 00:00:00','closed_at'=>'2026-09-02 00:00:00'],['id'=>2,'status'=>'closed','created_at'=>'2026-09-01 00:00:00','closed_at'=>'2026-09-03 00:00:00'],['id'=>3,'status'=>'needs_review','created_at'=>'2026-09-01 00:00:00','closed_at'=>null]];
$refs=[['id'=>10,'case_id'=>1,'created_at'=>'2026-09-01 00:00:00','status'=>'acknowledged','notice_issued_at'=>'2026-09-01 12:00:00','student_acknowledged_at'=>'2026-09-01 13:00:00'],['id'=>11,'case_id'=>1,'status'=>'closed','notice_issued_at'=>null],['id'=>12,'case_id'=>2,'status'=>'needs_review','updated_at'=>'2026-09-01 01:00:00']];
$actions=[['case_id'=>1,'actor_role'=>'admin','created_at'=>'2026-09-01 00:30:00'],['case_id'=>1,'actor_role'=>'faculty','created_at'=>'2026-09-01 06:00:00'],['case_id'=>2,'actor_role'=>'faculty','created_at'=>'2026-09-01 02:00:00']];
$s=analyticsSupportSummary([...$cases,$cases[0]],[...$refs,$refs[0]],$actions);
foreach(['eligible_cases'=>3,'referrals_created'=>3,'referrals_reviewed'=>2,'notices_issued'=>1,'notices_deferred'=>null,'student_acknowledgments'=>1,'open_cases'=>1,'closed_cases'=>2,'mean_first_action_hours'=>12.0,'first_action_sample'=>1,'median_closure_hours'=>36.0,'closure_sample'=>2] as $key=>$want){check(($s[$key]??null)===$want,"Support $key");}
$unequal=[];foreach([[1,1],[2,2],[3,9]] as [$id,$hours])$unequal[]=['id'=>$id,'case_id'=>1,'created_at'=>'2026-09-01 01:00:00','notice_issued_at'=>sprintf('2026-09-01 %02d:00:00',1+$hours),'status'=>'action_taken'];
$mean=analyticsSupportSummary([$cases[0]],$unequal,$actions);check(($mean['mean_first_action_hours']??null)===4.0&&$mean['first_action_sample']===3,'MTR20 uses arithmetic mean per distinct referral origin, not case-event median');
$invalidRefs=[['id'=>20,'case_id'=>1,'created_at'=>'2026-09-01 10:00:00','notice_issued_at'=>'2026-09-01 09:00:00','status'=>'needs_review'],['id'=>21,'case_id'=>1,'created_at'=>'2026-09-01 10:00:00','notice_issued_at'=>null,'status'=>'needs_review']];
$invalid=analyticsSupportSummary([$cases[0]],$invalidRefs,$actions);check(($invalid['mean_first_action_hours']??null)===null&&$invalid['first_action_sample']===0,'Invalid/unacted referral evidence cannot use unrelated case actions or zero durations');
$bad=analyticsSupportSummary([['id'=>1,'status'=>'closed','created_at'=>'2026-09-02 00:00:00','closed_at'=>'2026-09-01 00:00:00']],[],[['case_id'=>1,'actor_role'=>'faculty','created_at'=>'2026-08-01 00:00:00']]);
check($bad['median_closure_hours']===null&&($bad['mean_first_action_hours']??null)===null,'Reversed/missing times never become durations');
$none=analyticsSupportSummary([],[],[]);check($none['eligible_cases']===0&&$none['first_action_sample']===0&&($none['mean_first_action_hours']??null)===null,'No cases has counts but no invented response time');
require_once __DIR__.'/../../includes/analytics/service.php';
$supportOnly=['grades'=>[],'faculty'=>[11=>'Assigned Faculty'],'cases'=>[['school_year'=>'2023-2024','semester'=>2]],'referrals'=>[['subject_id'=>102,'code'=>'OLD102','title'=>'Historical referral','section'=>'OLD-A','faculty_id'=>11,'curriculum_year'=>2]]];
$options=analyticsOptions($supportOnly,['id'=>1,'role'=>'admin']);
foreach(['school_year'=>'2023-2024','semester'=>'2','section'=>'OLD-A','subject'=>'102','faculty'=>'11'] as $key=>$value){check(in_array($value,array_column($options[$key],'value'),true),'Support-only records remain selectable: '.$key);}

require_once __DIR__.'/../../includes/analytics/presentation.php';
check(formatAnalyticsDurationHours(2.5)==='2.5 hours','MTR-20 hours formatting < 24');
check(formatAnalyticsDurationHours(2.0)==='2 hours','MTR-20 integer hours formatting < 24');
check(formatAnalyticsDurationHours(30.0)==='1 day 6 hours','MTR-20 days and hours formatting');
check(formatAnalyticsDurationHours(1151.62)==='48 days','MTR-20 days rounding for large duration');
check(formatAnalyticsDurationHours(null)==='Unavailable','MTR-20 null is Unavailable');
check(formatAnalyticsDurationHours(-5.0)==='Unavailable','MTR-20 negative is Unavailable');
check(formatAnalyticsSampleSize(1)==='Based on 1 referral with a recorded notice','MTR-20 singular sample text');
check(formatAnalyticsSampleSize(3)==='Based on 3 referrals with recorded notices','MTR-20 plural sample text');
check(formatAnalyticsSampleSize(0)==='No referrals have a recorded first notice for this scope.','MTR-20 zero sample text');
check(formatAnalyticsSampleSize(null)==='No referrals have a recorded first notice for this scope.','MTR-20 null sample text');
check(analyticsSampleWarning(1)==='Very limited sample. Interpret cautiously.','MTR-20 sample warning for n=1');
check(analyticsSampleWarning(2)==='Very limited sample. Interpret cautiously.','MTR-20 sample warning for n=2');
check(analyticsSampleWarning(3)===null,'MTR-20 no sample warning for n=3');
check(analyticsSampleWarning(0)===null,'MTR-20 no sample warning for n=0');
check(analyticsSampleWarning(null)===null,'MTR-20 no sample warning for null');

echo "RESULT $passed passed, $failed failed\n";exit($failed?1:0);
