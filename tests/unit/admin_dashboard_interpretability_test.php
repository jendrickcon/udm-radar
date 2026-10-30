<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Actual presentation with synthetic values; no connection or authentication.
if (($argv[1] ?? '') === '--render') $_SERVER['SCRIPT_NAME']='/udm-radar/admin/index.php';
require_once dirname(__DIR__,2).'/config/constants.php';
require_once dirname(__DIR__,2).'/includes/analytics/presentation.php';
function getCsrfToken():string { return 'synthetic-fixture-no-session'; }
function adminInterpretabilityFixture(string $page,string $scenario='normal'):string {
 $root=dirname(__DIR__,2);$source=file_get_contents($root.'/admin/index.php');
 $total=40;$highRisk=$scenario==='high'?24:6;$modRisk=10;$lowRisk=$total-$highRisk-$modRisk;$noPredict=0;
 if($scenario==='missing'){$highRisk=$modRisk=$lowRisk=0;$noPredict=40;}
 if($scenario==='empty'){$total=$highRisk=$modRisk=$lowRisk=0;}
 $atRisk=$highRisk+$modRisk;$irregular=3;$avgGwa=2.75;$analyticsAvailable=$scenario!=='error';
 $analyticsSummary=['gwa'=>2.75,'student_count'=>40,'eligible_grade_count'=>60,'expected_grade_count'=>80,'numeric_coverage_pct'=>75.0];
 $analyticsTrend=['current'=>['gwa'=>2.75,'provisional'=>false],'previous'=>null,'change'=>['point_change'=>null]];
 $analyticsCoverage=['coverage_pct'=>100.0,'available_count'=>40,'student_count'=>40];
 $analyticsSupport=['open_cases'=>0,'eligible_cases'=>0];
 if(!$analyticsAvailable){$analyticsSummary=array_fill_keys(array_keys($analyticsSummary),null);$analyticsCoverage=array_fill_keys(array_keys($analyticsCoverage),null);$analyticsSupport=['open_cases'=>null,'eligible_cases'=>null];}
 $mtr20Hours=null;$mtr20Sample=0;$mtr20Formatted='Unavailable';$mtr20SampleText=formatAnalyticsSampleSize(0);$mtr20Warning=null;
 $yearFilter=$sectionFilter='';$years=[3];$sections=$students=[];
 $openReports=$pendingApprovals=$pendingBatches=$pendingCorrections=$supportReviews=$totalAdminActions=0;
 $lastRunText='Synthetic fixture';$chartSectionLabels=$chartSectionAverages=[];
 $sectionData=$chartSecHigh=$chartSecMod=$chartSecLow=$chartSecNone=[];
 for($i=1;$i<=30;$i++){
  $label='SYN-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
  $sections[]=$chartSectionLabels[]=$label;$chartSectionAverages[]=2.25+($i%5)/4;
  $chartSecHigh[]=10;$chartSecMod[]=20;$chartSecLow[]=70;$chartSecNone[]=0;
  $sectionData[$label]=['total'=>10,'gwa_count'=>10,'gwa_sum'=>27.5,'coverage_count'=>10,'irregular'=>2,'risks'=>['HIGH'=>1,'MODERATE'=>2,'LOW'=>7,'NONE'=>0]];
 }
 $overview=['totalStudents'=>300,'meanGwa'=>2.75,'atRiskPct'=>30.0,'coveragePct'=>100.0,'sectionData'=>$sectionData,'chartSecLabels'=>$chartSectionLabels,'chartSecHigh'=>$chartSecHigh,'chartSecMod'=>$chartSecMod,'chartSecLow'=>$chartSecLow,'chartSecNone'=>$chartSecNone,'distinctions'=>['Summa-level threshold'=>5,'Magna-level threshold'=>10,'Cum Laude-level threshold'=>20,'Not currently within a distinction threshold'=>255,'Insufficient Data'=>10]];
 $role=$page==='faculty'?'faculty':'admin';$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$riskBadgeClass=static fn($risk)=>'na';$user=['id'=>1,'role'=>$role];$pageTitle='Generated portal fixture';
 if($page==='overview'||$page==='faculty'){
  if($page==='faculty')$overview=null;
  $presentation=file_get_contents($root.'/includes/analytics/presentation.php');
  $start=strpos($presentation,'    $escape = static fn');$end=strrpos($presentation,"require __DIR__ . '/../footer.php';");
  if($start===false||$end===false)throw new RuntimeException('Overview seam missing');
  $template='<?php '.str_replace('__DIR__',var_export($root.'/includes/analytics',true),substr($presentation,$start,$end-$start));
 }else{
  $start=strpos($source,'?>');if($start===false)throw new RuntimeException('Dashboard seam missing');
  $template=substr($source,$start+2);
  $template=str_replace('__DIR__',var_export($root.'/admin',true),$template);
  $template=str_replace("require_once '../includes/footer.php';","require '".str_replace('\\','/',$root)."/includes/footer.php';",$template);
 }
 ob_start();try{require $root.'/includes/header.php';eval('?>'.$template);if($page==='overview'||$page==='faculty')require $root.'/includes/footer.php';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}
}
if(($argv[1]??'')==='--render'){$_SERVER['SCRIPT_NAME']='/udm-radar/admin/index.php';echo adminInterpretabilityFixture($argv[2]??'dashboard',$argv[3]??'normal');exit;}
$passed=0;$failed=0;
function check(bool $ok,string $label):void {global $passed,$failed;if($ok)$passed++;else{$failed++;echo "FAIL: $label\n";}}
check(str_contains(file_get_contents(dirname(__DIR__,2).'/admin/index.php'),'$analyticsAvailable = true;'),'Successful snapshot starts available');
foreach(['normal','high','missing','empty','error'] as $case){
 $html=adminInterpretabilityFixture('dashboard',$case);
 $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();$x=new DOMXPath($dom);
 check($x->query('//*[@id="comparison-help-trigger" and @aria-controls="comparison-help"]')->length===1,'Accessible help: '.$case);
 check($x->query('//*[contains(@class,"kpi-grid--four-cohort")]/*')->length===4,'Four cohort cards: '.$case);
 check(!str_contains($html,'require some level of advisory attention'),'Neutral risk interpretation: '.$case);
 if(in_array($case,['high','missing','empty'],true))check(!str_contains($html,'Most displayed Students are currently classified as Low Risk'),'No false majority: '.$case);
 if($case==='missing')check(!str_contains($html,'All clear'),'Missing predictions are not reassurance');
 if($case==='error'){check(str_contains($html,'Temporarily unavailable because analytics could not be loaded'),'Visible error reason');check($x->query('//*[@data-dashboard-metric="open-cases" and normalize-space(.)="Unavailable"]')->length===1,'Error is not zero');}
}
$overview=adminInterpretabilityFixture('overview');
check(str_contains($overview,'data-focused-chart="risk"')&&str_contains($overview,'data-focused-chart="distinction"'),'Both focused chart controls');
check(!str_contains($overview,'<summary>View exact values</summary>'),'No expanding Overview values');
check(!str_contains(file_get_contents(dirname(__DIR__,2).'/assets/css/dashboard.css'),'.kpi-grid--five:has('),'Semantic four-card CSS');
check(str_contains(file_get_contents(dirname(__DIR__,2).'/assets/js/academic-analytics.js'),'Recorded outcomes resolved'),'Admin completeness does not claim independent verification');
echo "RESULT: $passed passed, $failed failed\n";exit($failed?1:0);
