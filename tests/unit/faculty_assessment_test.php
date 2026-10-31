<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/faculty_assessment_repository.php';
$passed=0; $failed=0;
function faCheck(bool $ok,string $label): void {global $passed,$failed; if($ok)$passed++;else{$failed++;echo "FAIL: $label\n";}}
function faRow(int $id,mixed $p=null,mixed $m=null,mixed $pf=null,mixed $f=null,int $subject=1): array {return ['student_id'=>$id,'subject_id'=>$subject,'prelim'=>$p,'midterm'=>$m,'prefinal'=>$pf,'final_grade'=>$f,'units'=>3,'name'=>'Synthetic '.$id];}
function faNear(mixed $actual,float $expected): bool {return $actual!==null&&abs($actual-$expected)<1e-9;}
$s=facultyAssessmentSummary([faRow(1,80,85),faRow(2,90,95)]);
faCheck($s['assessment']['label']==='Midterm','Single explicitly named latest stage');
faCheck(faNear($s['assessment']['mean'],90),'Raw percentages retained, not converted GWA');
faCheck($s['assessment']['count']===2&&faNear($s['assessment']['coverage'],100),'Stage numerator and denominator');
faCheck($s['final']['count']===0&&faNear($s['final']['coverage'],0)&&$s['final']['mean']===null,'Final outcomes remain independently unavailable');
faCheck(faNear($s['change']['point_change'],5)&&faNear($s['change']['relative_change'],100*5/85),'Positive change and secondary relative change');
faCheck($s['change']['sample']===2&&!$s['change']['provisional'],'Matched population count');
faCheck(count($s['notable'])===2,'Five percentage points inclusive');
$boundary=facultyAssessmentSummary([faRow(1,3.04,8.04),faRow(2,8.04,3.04)]);
faCheck(count($boundary['notable'])===2&&$boundary['notable'][0]['change']===-5.0&&$boundary['notable'][1]['change']===5.0,'Decimal-boundary five-point changes remain included despite binary floating error');
$s=facultyAssessmentSummary([faRow(1,90,80),faRow(2,80,70)]);
faCheck(faNear($s['change']['point_change'],-10),'Negative change');
faCheck($s['notable'][0]['student_id']===1,'Stable Student tie order');
faCheck($s['notable'][0]['risk_stage']==='Midterm','Risk source explicitly named');
$s=facultyAssessmentSummary([faRow(1,80,90),faRow(2,40,null),faRow(3,null,100)]);
faCheck(faNear($s['stages']['prelim']['mean'],60)&&faNear($s['stages']['midterm']['mean'],95),'Each stage preserves own sample');
faCheck(faNear($s['change']['point_change'],10),'Change uses matched Students, not unmatched-stage means');
faCheck($s['change']['sample']===1&&$s['change']['provisional'],'Any incomplete paired population visibly provisional');
faCheck(faNear($s['assessment']['coverage'],200/3),'No missing-to-zero substitution');
$s=facultyAssessmentSummary([faRow(1,0,10)]);
faCheck($s['stages']['prelim']['count']===1&&faNear($s['stages']['prelim']['mean'],0),'Numeric zero is recorded assessment');
faCheck(faNear($s['change']['point_change'],10)&&$s['change']['relative_change']===null,'Zero baseline withholds relative change');
foreach([null,'','INC',-1,101,INF,NAN,[],true] as $invalid) faCheck(facultyAssessmentValue($invalid)===null,'Invalid assessment withheld: '.get_debug_type($invalid));
$s=facultyAssessmentSummary([faRow(1,80),faRow(2,null,90)]);
faCheck($s['change']['point_change']===null&&$s['change']['sample']===0,'Disjoint populations have unavailable direct change');
$s=facultyAssessmentSummary([faRow(1,80,null,90)]);
faCheck($s['change']['point_change']===null,'Missing adjacent stage never silently skipped');
$s=facultyAssessmentSummary([faRow(1,80,85,90)]);
faCheck($s['change']['from']==='Midterm'&&$s['change']['to']==='Pre-Final'&&faNear($s['change']['point_change'],5),'Next adjacent recorded stage pair');
$s=facultyAssessmentSummary([]);
faCheck($s['assessment']===null&&$s['final']['mean']===null&&$s['change']['point_change']===null,'Empty set invents no mean or change');
faCheck($s['stages']['prefinal']['risk']===null&&$s['final']['risk']===null,'Unrecorded stages have no risk bars');
$s=facultyAssessmentSummary([faRow(1,80,85,null,'INC'),faRow(2,90,95,null,'P')]);
faCheck($s['final']['recorded']===2&&$s['final']['count']===0&&$s['final']['mean']===null,'Textual final coverage not numeric final GWA');
faCheck($s['assessment']['label']==='Midterm'&&faNear($s['assessment']['mean'],90),'Final text cannot replace a percentage mean');
faCheck($s['final']['risk']['NONE']===2,'Textual outcomes not fabricated numeric risk');
$s=facultyAssessmentSummary([faRow(1,80,85,null,'0.00'),faRow(2,90,95,null,'3.50')]);
faCheck(faNear($s['final']['mean'],3.5)&&$s['final']['count']===1,'Canonical zero exclusion preserved for finalized mean');
faCheck($s['final']['risk']['HIGH']===1,'Failed numeric final retains independent High advisory risk');
faCheck($s['notable'][0]['risk_stage']==='Final'&&$s['notable'][0]['current_risk']==='HIGH','Notable changes use latest finalized risk when present');
$a=faRow(1,80,85,null,'2.00');$b=faRow(1,90,95,null,'4.00',2);$b['units']=1;
$s=facultyAssessmentSummary([$a,$b]);
faCheck($s['expected']===2&&$s['students']===1,'Portfolio distinct Students differ from Student-subject slots');
faCheck(faNear($s['final']['mean'],2.5),'Finalized mean preserves unit weighting');
faCheck(faNear($s['assessment']['mean'],90),'Assessment uses equal record weight, separate from final units');
faCheck($s['attention']===1,'Student requiring attention counted once across own classes');
try{facultyAssessmentSummary([$a,$a]);faCheck(false,'Duplicate grade grain rejected');}catch(DomainException){faCheck(true,'Duplicate grade grain rejected');}
$loads=[['load_id'=>10,'subject_id'=>1,'section'=>'SYN-A']];$term=['school_year'=>'2026-2027','semester'=>1];
faCheck(facultyAssessmentSelect($loads,[],$term)['key']==='1|SYN-A','Default belongs to own load');
faCheck(facultyAssessmentSelect($loads,['load'=>'1|SYN-A'],$term)['current'],'Valid assigned selection');
foreach([['load'=>'2|SYN-A'],['load'=>'1|SYN-B'],['load'=>'1|SYN-A OR 1=1']] as $bad){try{facultyAssessmentSelect($loads,$bad,$term);faCheck(false,'Foreign/tampered class denied');}catch(DomainException){faCheck(true,'Foreign/tampered class denied');}}
foreach([['load'=>['1|SYN-A']],['faculty_id'=>'2'],['student_id'=>'42'],['sy'=>'2025-2025'],['sem'=>'4'],['sem'=>'1','semester'=>'2']] as $bad){try{facultyAssessmentSelect($loads,$bad,$term);faCheck(false,'Invalid/foreign identifier rejected');}catch(InvalidArgumentException){faCheck(true,'Invalid/foreign identifier rejected');}}
faCheck(!facultyAssessmentSelect($loads,['sy'=>'2025-2026'],$term)['current'],'Historical request cannot be current teaching evidence');
$prior=facultyAssessmentPriorStatus();
faCheck(!$prior['available']&&$prior['status']==='No dated prior assignment','Current-only table cannot activate previous-semester comparison');
$source=file_get_contents(__DIR__.'/../../faculty/trend.php');
faCheck(!str_contains($source,'Estimated Grade Point Trajectory')&&!str_contains($source,'Students With Significant Changes'),'Misleading trajectory/significance labels removed');
faCheck(str_contains($source,'5 percentage points')&&str_contains($source,'Previous-semester comparison'),'Visible units and explicit prior unavailable state');
faCheck(str_contains($source,'Final entries recorded'),'Final entries including INC are not described as finalized completion');
faCheck(!str_contains(file_get_contents(__DIR__.'/../../assets/js/faculty-assessment.js'),'spanGaps:true'),'No false continuation through unrecorded stages');
echo "SUMMARY: $passed passed, $failed failed; no database accessed.\n";
exit($failed?1:0);
