<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/config/pef_cc.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_view_model.php';
$config=pefCcConfig();$now=['school_year'=>'2026-2027','semester'=>1];
$build=fn(array $c,array $i=[])=>pefCcBuildViewModel($c,pefTestInputs($i),pefTestReference(),$config,$now)->data;
$base=pefTestContext();$m=$build($base);
pefTestCheck($m['exportable']===true,'Valid completed-term model exports without curriculum mapping');
pefTestCheck($m['gwa']===3.0,'Term GWA excludes INC and recorded numeric zero');
pefTestCheck($m['recorded_count']===3 && count($m['recorded'])===3,'All records retained');
pefTestCheck($m['coverage']['eligible_count']===1 && $m['coverage']['eligible_units']===3,'Numeric grade and unit coverage agrees');
pefTestCheck(array_sum($m['coverage']['excluded'])+$m['coverage']['eligible_count']===3,'One exclusion reason per record reconciles denominator');
pefTestCheck($m['cc']['label']==='Reference / Requires Review','CC applicability independent of PEF');
pefTestCheck($m['cc']['rows'][0]['state']==='CMP' && $m['cc']['rows'][1]['state']==='INC' && $m['cc']['rows'][2]['state']==='FAIL','Passing, INC, zero states preserved');
pefTestCheck($m['cc']['rows'][3]['state']==='','No exact record stays blank');
pefTestCheck($m['cc']['rows'][0]['prereq']==='ITE252','Unresolved prerequisite reference unchanged');
foreach([['INC'],[null],[''],['0.00'],['DRP']] as $grades) {
 $c=pefTestContext(3,$grades);$d=$build($c);
 pefTestCheck($d['gwa']===null,'Unavailable GWA for '.json_encode($grades));
 pefTestCheck($d['recorded_count']===3,'Unavailable GWA retains all records '.json_encode($grades));
}
$c=pefTestContext(13,['1.00']);$c['records'][12]['grade']='4.00';$d=$build($c);
pefTestCheck($d['exportable'] && count($d['recorded'])===13 && $d['gwa']===computeWeightedGWA($c['records']),'All thirteen records contribute through canonical helper');
$d=$build(pefTestContext(14));pefTestCheck(!$d['exportable']&&count($d['recorded'])===14,'Fourteen evaluation rows rejected without truncation');
$d=$build(pefTestContext(0));pefTestCheck(!$d['exportable'],'No selected-term records blocks PEF');
$d=$build($base,['subject_ids'=>range(1,12)]);pefTestCheck($d['exportable']&&$d['proposed_count']===12,'Twelve proposed rows permitted');
try{$d=$build($base,['subject_ids'=>range(1,13)]);pefTestCheck(!$d['exportable']&&$d['proposed_count']===13,'Thirteen proposed rows blocked, never truncated');}
catch(PefCcValidationException $e){pefTestCheck(true,'Thirteen proposed rows visibly rejected');}
foreach([['subject_ids'=>[1,1]],['subject_ids'=>[999999]],['subject_ids'=>['1 OR 1=1']],['subject_ids'=>[0]],['subject_ids'=>[-1]],['subject_ids'=>[[1]]],['subject_ids'=>'1'],
 ['target_year'=>'2025-2027'],['target_year'=>'2025/2026'],['target_semester'=>3],['evaluation_semester'=>0],
 ['section'=>"A\tB"],['section'=>"A\rB"],['section'=>"A\nB"],['section'=>"A\r\nB"],['section'=>"A\x01B"],['section'=>str_repeat('界',97)],['section'=>['nested']],['target_year'=>['2025-2026']],
 ['student_id'=>1],['gwa'=>4],['cell'=>'A1'],['sheet'=>'PEF'],['template_path'=>'../file'],['filename'=>'../../evil.xlsx']] as $invalid) {
 try{$v=$build($base,$invalid);pefTestCheck(!$v['exportable'],'Rejected input '.json_encode(array_keys($invalid)));}
 catch(PefCcValidationException $e){pefTestCheck(count($e->errors)>0,'Field error for '.json_encode(array_keys($invalid)));}
}
foreach(['=1+1','+SUM(A1:A2)','-1+2','@SUM(A1)','  =HYPERLINK("x")','../file.xlsx','[x]<>|:*?','Unicode 界 ñ'] as $section) {
 $d=$build($base,['section'=>$section]);pefTestCheck($d['exportable']&&$d['planning']['section']===$section,'Literal single-line planning text preserved: '.$section);
}
foreach(['mixed','current','future','duplicate','orphan','unsupported','units'] as $case){
 $c=pefTestContext();
 if($case==='mixed')$c['records'][1]['is_current']=1;
 if($case==='current')foreach($c['records'] as &$r){$r['school_year']='2026-2027';$r['semester']=1;}unset($r);
 if($case==='future')foreach($c['records'] as &$r){$r['school_year']='2027-2028';$r['semester']=1;}unset($r);
 if($case==='duplicate'){$c['records'][]=$c['records'][0];$c['records'][3]['id']=99;}
 if($case==='orphan'){$c['records'][0]['code']=null;$c['records'][0]['title']=null;}
 if($case==='unsupported')$c['records'][0]['grade']='garbage';
 if($case==='units')$c['records'][0]['units']=-1;
 $d=$build($c,in_array($case,['current','future'],true)?['evaluation_year'=>$case==='current'?'2026-2027':'2027-2028','evaluation_semester'=>1]:[]);
 pefTestCheck(!$d['exportable']&&count($d['recorded'])===count($c['records']),'Source conflict visible and blocked: '.$case);
 if($case!=='units')pefTestCheck($d['gwa']===null,'Unreviewed term/source conflict never presents authoritative GWA: '.$case);
}
$c=pefTestContext();$c['records'][]=$c['records'][0];$c['records'][3]['school_year']='2023-2024';$d=$build($c);
pefTestCheck($d['cc']['rows'][0]['state']==='REVIEW'&&$d['cc']['rows'][0]['grade']===null,'Multiple attempts not resolved as best/latest');
$c=pefTestContext();$c['records'][1]['school_year']='2026-2027';$c['records'][1]['semester']=1;$c['records'][1]['is_current']=1;$d=$build($c);
pefTestCheck($d['cc']['rows'][1]['state']==='CUR','Current exact-code evidence maps CUR separately from historical PEF');
foreach([1,2,3,4] as $year){$c=$base;$c['identity']['year_level']=$year;$d=$build($c);pefTestCheck($d['exportable']&&$d['cc']['label']==='Reference / Requires Review','Year '.$year.' does not infer curriculum or gate PEF');}
$c=$base;$c['identity']['status']='Irregular';$d=$build($c);pefTestCheck($d['exportable'],'Stored irregular status does not gate manual planning');
foreach([['college'=>'Other College'],['college'=>''],['program'=>'Unknown program'],['program'=>'']] as $change){$c=$base;$c['identity']=array_replace($c['identity'],$change);$d=$build($c);pefTestCheck(!$d['exportable'],'Missing/incompatible fixed-template identity blocks export: '.json_encode($change));}
$c=$base;$c['identity']['name']='Example, Synthetic ñ';$d=$build($c);pefTestCheck($d['identity']['name']==='EXAMPLE, SYNTHETIC Ñ','Uppercase identity convention is normalized once in model');
foreach([['1.00',3],['1.25',1],['1.50',3],['3.75',4],['4.00',1],['0.00',3],['INC',3],[null,3],['',3],['P',3],['DRP',3]] as [$grade,$units]){
 $c=pefTestContext(1,[$grade]);$c['records'][0]['units']=$units;$d=$build($c);pefTestCheck($d['gwa']===computeWeightedGWA([['grade'=>$grade,'units'=>$units]]),'Model/helper parity '.json_encode([$grade,$units]));
}
$obj=pefCcBuildViewModel($base,pefTestInputs(),pefTestReference(),$config,$now);
pefTestCheck((new ReflectionClass($obj))->isReadOnly(),'View model is PHP readonly');
pefTestThrows(function()use($obj){$obj->data['gwa']=4.00;},'Readonly nested model mutation prevented');
pefTestCheck(!preg_match('/prediction|risk_level|current_gwa|PDO|signing_key/',json_encode($obj,JSON_THROW_ON_ERROR)),'No predictions, cached GWA, secrets, or PDO in model');
pefTestFinish();
