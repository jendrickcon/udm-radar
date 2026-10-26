<?php
declare(strict_types=1);
require_once __DIR__.'/../fixtures/analytics_mvp_fixture.php';
$path=__DIR__.'/../../includes/analytics/service.php';if(is_file($path))require_once $path;
if(!function_exists('analyticsReport')){echo "FAIL: Missing scoped report contract analyticsReport\n";exit(1);}
$passed=0;$failed=0;
function verify(bool $ok,string $label):void {global $passed,$failed;if($ok)$passed++;else{$failed++;echo "FAIL: $label\n";}}
foreach(['udm_radar','udm_radar_scratch','udm_radar_demo','other'] as $forbidden){try{analyticsFixtureName($forbidden);verify(false,'Reject protected fixture name');}catch(RuntimeException){verify(true,'Protected name rejected');}}
$statePath=getenv('ANALYTICS_MVP_STATE');$own=!$statePath;
$state=$statePath?json_decode(file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR):analyticsFixtureSetup();
try{
 $db=analyticsFixtureConnect($state);
 $admin=['id'=>1,'role'=>'admin'];$student=['id'=>101,'role'=>'student'];$faculty=['id'=>11,'role'=>'faculty'];
 $before=[];foreach(['grades','predictions','academic_support_cases','support_case_referrals'] as $table)$before[$table]=$db->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();
 $all=analyticsReport($db,$admin,[]);verify($all['summary']['expected_grade_count']===12,'All recorded slots appear once');
 $current=analyticsReport($db,$admin,['school_year'=>'2026-2027','semester'=>'1']);
 foreach(['gwa'=>2.93,'student_count'=>4,'eligible_grade_count'=>3,'expected_grade_count'=>6,'numeric_coverage_pct'=>50.0] as $k=>$v)verify($current['summary'][$k]===$v,'Current aggregate '.$k);
 verify($current['prediction_coverage']['available_count']===3&&$current['prediction_coverage']['student_count']===4,'Prediction coverage denominator');
 $subject=analyticsReport($db,$admin,['school_year'=>'2026-2027','subject'=>'101']);verify($subject['summary']['gwa']===2.75&&$subject['summary']['student_count']===3,'Same-subject comparison spans sections');
 $section=analyticsReport($db,$admin,['section'=>'AN-A']);verify($section['summary']['expected_grade_count']===4,'Current section filter cannot attribute historical grades');
 $fa=analyticsReport($db,$faculty,[]);$aa=analyticsReport($db,$admin,['faculty'=>'11']);
 verify($fa['summary']===$aa['summary'],'Identical assigned subset has cross-portal parity');
 verify($fa['summary']['expected_grade_count']===4,'Faculty reads only current assigned subject/section slots');
 verify($fa['support']['referrals_created']===3&&$fa['support']['eligible_cases']===2,'Faculty own-referral scope');
 verify($fa['faculty_comparison']['change']['direction']==='Unavailable','No undated historical teaching comparison');
 verify(count($fa['options']['faculty'])===1&&$fa['options']['faculty'][0]['value']==='11','Faculty options reveal only self');
 $mine=analyticsReport($db,$student,[]);verify($mine['summary']['student_count']===1&&$mine['summary']['expected_grade_count']===5,'Student cannot read other records');
 verify($mine['trend']['current']['gwa']===3.63&&$mine['trend']['previous']['gwa']===3.0,'Student term averages');
 verify($mine['trend']['change']['point_change']===0.63&&$mine['trend']['change']['relative_change_pct']===21.0,'Student canonical change');
 verify($mine['prediction']['estimated_gwa']===3.25&&$mine['prediction']['advisory_indicator']==='LOW','Latest prediction ties use ID and rules recompute advisory');
 verify($mine['support']===null&&$mine['drilldowns']===[],'Student gets no support analytics');
 $incomplete=analyticsReport($db,['id'=>102,'role'=>'student'],[]);verify($incomplete['trend']['change']['direction']==='Unavailable','Incomplete records do not imply decline');
 $empty=analyticsReport($db,['id'=>105,'role'=>'student'],[]);verify($empty['summary']['gwa']===null&&$empty['trend']['current']===null,'No-record account');
 foreach([[$student,['student_id'=>'103']],[$faculty,['faculty'=>'12']],[$student,['section'=>'AN-B']],[$admin,['subject'=>['101']]],[$admin,['semester'=>'9']],[$admin,['school_year'=>'2026-2029']]] as [$actor,$filters]){
  try{analyticsReport($db,$actor,$filters);verify(false,'Tampered scope/filter rejected');}catch(InvalidArgumentException|DomainException){verify(true,'Tampered filter rejected');}
 }
 try{analyticsReport($db,['id'=>11,'role'=>'admin'],[]);verify(false,'Stale/tampered role fails closed');}catch(DomainException){verify(true,'Actual account role verified');}
 $year=analyticsReport($db,$admin,['year_level'=>'3']);verify($year['summary']['expected_grade_count']===2,'Curriculum year filter');
 verify($current['support']['eligible_cases']===4&&$current['support']['referrals_created']===4,'Cases/referrals use separate denominators');
 verify($current['support']['mean_first_action_hours']===2.5&&$current['support']['first_action_sample']===2,'MTR20 mean referral-to-notice duration, no unrelated parent case events');
 verify($current['support']['median_closure_hours']===48.0&&$current['support']['notices_deferred']===null,'Closure and unavailable deferral');
 $closed=analyticsReport($db,$admin,['case_status'=>'closed']);verify($closed['support']['eligible_cases']===1&&$closed['support']['referrals_created']===1,'Case status filter');
 $issued=analyticsReport($db,$admin,['decision'=>'issued']);verify($issued['support']['referrals_created']===2&&$issued['support']['eligible_cases']===2,'Decision filter');
 $deferred=analyticsReport($db,$admin,['decision'=>'deferred']);verify($deferred['support']['referrals_created']===0&&$deferred['support']['notices_deferred']===null,'No invented deferred records');
 verify(!str_contains(json_encode($all),'analytics_student_')&&!str_contains(json_encode($all),'student_id'),'Report excludes Student identities and raw row IDs');
 foreach($before as $table=>$rows)verify($rows===$db->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(),'Report is read-only: '.$table);
 echo "RESULT $passed passed, $failed failed; owned disposable database only\n";
}finally{if($own)analyticsFixtureCleanup($state);}
exit($failed?1:0);
