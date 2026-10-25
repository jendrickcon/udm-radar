<?php
declare(strict_types=1);
// Optional CLI path reproduces these same contracts against the saved checkpoint implementation.
require_once $argv[1]??__DIR__.'/../../includes/analytics/interventions.php';
$passed=0;$failed=0;
function reviewCheck(bool $ok,string $label):void{global $passed,$failed;if($ok)$passed++;else{$failed++;echo "FAIL: $label\n";}}
$cases=[['id'=>1,'status'=>'needs_review','created_at'=>'2026-09-01 00:00:00']];
$ref=['id'=>1,'case_id'=>1,'status'=>'needs_review','created_at'=>'2026-09-01 00:00:00'];
foreach(['0000-00-00 00:00:00','malformed','2026-08-31 23:00:00'] as $timestamp){
 $s=analyticsSupportSummary($cases,[$ref+['notice_issued_at'=>$timestamp]],[]);
 reviewCheck($s['notices_issued']===0&&$s['referrals_reviewed']===0,'Invalid/reversed notice cannot count as issuance or review: '.$timestamp);
}
foreach(['0000-00-00 00:00:00','malformed'] as $timestamp){$s=analyticsSupportSummary($cases,[$ref+['student_acknowledged_at'=>$timestamp]],[]);reviewCheck($s['student_acknowledgments']===0,'Invalid acknowledgment is not recorded receipt evidence');}
$s=analyticsSupportSummary($cases,[$ref+['student_acknowledged_at'=>'2026-09-01 02:00:00']],[]);reviewCheck($s['student_acknowledgments']===1,'Valid legacy receipt count is retained without inventing notice evidence');
$s=analyticsSupportSummary($cases,[$ref+['notice_issued_at'=>'2026-09-01 03:00:00','student_acknowledged_at'=>'2026-09-01 02:00:00']],[]);reviewCheck($s['student_acknowledgments']===0,'Receipt cannot precede a valid linked notice');
echo "RESULT $passed passed, $failed failed; pure synthetic timestamp evidence\n";exit($failed?1:0);
