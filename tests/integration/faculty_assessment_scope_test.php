<?php
declare(strict_types=1);
// No default target, credentials or protected database fallback. Caller owns creation and finally-cleanup.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../fixtures/analytics_mvp_fixture.php';
require_once __DIR__.'/../../includes/faculty_assessment_repository.php';
$state=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$db=analyticsFixtureConnect($state);$passed=0;$failed=0;
function scopeCheck(bool $ok,string $label):void {global $passed,$failed;if($ok)$passed++;else{$failed++;echo "FAIL: $label\n";}}
$before=__DIR__.'/../fixtures/faculty_assessment_fixture.php';require_once $before;
$fingerprint=facultyAssessmentFixtureFingerprint($state);
$term=['school_year'=>'2026-2027','semester'=>1];
$data=facultyAssessmentRead($db,31,$term);
scopeCheck(count($data['loads'])===5,'Only five owned class loads');
scopeCheck(count($data['rows'])===20,'Exactly current owned Student-subject records');
scopeCheck(!in_array(506,array_map('intval',array_column($data['rows'],'subject_id')),true),'Foreign Faculty subject excluded');
scopeCheck(count(array_filter($data['rows'],fn($r)=>$r['school_year']!=='2026-2027'))===0,'Historical grades excluded despite matching current class');
$other=facultyAssessmentRead($db,32,$term);
scopeCheck(count($other['rows'])===4&&count($other['loads'])===1,'Other Faculty receives only its own scope');
foreach([1,101,999999] as $id){try{facultyAssessmentRead($db,$id,$term);scopeCheck(false,'Wrong role/missing account denied');}catch(DomainException){scopeCheck(true,'Wrong role/missing account denied');}}
foreach(['506|FOREIGN','501|IT-35','999|IT-32'] as $key){try{facultyAssessmentSelect($data['loads'],['load'=>$key],$term);scopeCheck(false,'Unassigned class fails closed');}catch(DomainException){scopeCheck(true,'Unassigned class fails closed');}}
$selected=array_values(array_filter($data['rows'],fn($r)=>(int)$r['subject_id']===501));$s=facultyAssessmentSummary($selected);
scopeCheck(abs($s['assessment']['mean']-68.75)<1e-9&&$s['assessment']['count']===4,'Recorded percentage mean and zero included');
scopeCheck($s['final']['mean']===null&&$s['final']['count']===0,'Available interim mean does not populate finals');
scopeCheck(abs($s['change']['point_change']-5)<1e-9&&$s['change']['sample']===4,'Matched change calculated on current assigned records');
scopeCheck(!facultyAssessmentSelect($data['loads'],['sy'=>'2025-2026'],$term)['current'],'Past request cannot claim dated attribution');
scopeCheck(facultyAssessmentPriorStatus()['available']===false,'No current-only historical comparison');
scopeCheck(facultyAssessmentFixtureFingerprint($state)===$fingerprint,'All fixture rows unchanged by SELECT-only model/repository');
echo "SUMMARY: $passed passed, $failed failed; owned disposable target; no mutations.\n";
exit($failed?1:0);
