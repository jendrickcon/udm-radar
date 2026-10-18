<?php
declare(strict_types=1);
if(($argv[1]??'')==='--render')$_SERVER['SCRIPT_NAME']='/udm-radar/student/enrolment_planning.php';
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/config/pef_cc.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_view_model.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_snapshot.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_export_service.php';

/** Actual page template with synthetic scalar context; no auth/bootstrap/database. */
function pefPrintRender(string $case): string {
    $config=pefTestConfig();$currentTerm=['school_year'=>'2026-2027','semester'=>1];
    if (in_array($case, ['incomplete', 'provisional', 'provisional-preview'], true)) {
        $context = pefTestRegularContext(3, 1, false);
        $inputs = pefTestRegularInputs($context, 1);
        $reference = pefTestReference();
        $model = pefCcBuildViewModel($context, $inputs, $reference, $config, $currentTerm);
        $data = $model->data;
        $cc = $data['cc'];
        $formPolicy = $data['policy'];
        $manualPlanning = false;
        $regularPlanning = true;
        $formUnavailable = false;
        $errors = $data['errors'];
        $fieldErrors = $data['field_errors'];
        $selectedIds = array_map('strval', $inputs['subject_ids']);
        $choices = pefCcTermChoices($context['records'], $currentTerm);
        $years = array_unique(array_column($choices, 'school_year'));
        $hasCompletedTerm = true;
        $hasCompletePredecessor = false;
        $isProvisional = true;
        $incompleteWarning = $formPolicy['provisional_warning'] ?? '';
        $csrf = 'synthetic-render-csrf';
        $requestValid = true;
        $showPreview = ($case === 'provisional-preview');
        $preview = ($showPreview && $data['exportable'])
            ? pefCcSignSnapshot($model, random_bytes(32), $data['identity']['id'], 'synthetic-render', $config)
            : '';
    } else {
        $context=pefTestContext();$inputs=pefTestInputs(['section'=>'IT-32','subject_ids'=>[1,2]]);
        if($case==='record'){$context['records'][0]['code']=str_repeat('W',20);$context['records'][0]['title']=str_repeat('T',150);}
        if($case==='proposed')$context['catalog'][1]['title']=str_repeat('T',150);
        if($case==='identity'){$context['identity']['name']=str_repeat('N',61);$context['identity']['student_number']=str_repeat('S',21);}
        if(!in_array($case,['normal','record','proposed','identity'],true))throw new InvalidArgumentException('Unknown synthetic render case');
        $reference=pefTestReference();$model=pefCcBuildViewModel($context,$inputs,$reference,$config,$currentTerm);
        $data=$model->data;$cc=$data['cc'];$formPolicy=$data['policy'];
        $manualPlanning=true;$regularPlanning=false;$formUnavailable=false;
        $errors=$data['errors'];$fieldErrors=$data['field_errors'];$selectedIds=array_map('strval',$inputs['subject_ids']);
        $choices=pefCcTermChoices($context['records'],$currentTerm);$years=array_unique(array_column($choices,'school_year'));
        $hasCompletedTerm=true;$hasCompletePredecessor=true;
        $isProvisional=false;
        $incompleteWarning='';
        $csrf='synthetic-render-csrf';$requestValid=true;$showPreview=true;
        $preview=($data['exportable']&&$hasCompletePredecessor)?pefCcSignSnapshot($model,random_bytes(32),$data['identity']['id'],'synthetic-render',$config):'';
    }
    $history = $data['history'] ?? [];
    $summaryStatus = $isProvisional ? 'Provisional, final grades incomplete' : 'Complete';
    $summaryEvalTerm = ($data['evaluation']['school_year'] ?? $inputs['evaluation_year']) . ' · ' . ($data['evaluation']['label'] ?? pefCcSemesterLabel((int) ($inputs['evaluation_semester'] ?? 1)));
    $summaryTargetTerm = ($data['planning']['target_year'] ?? $inputs['target_year']) . ' · ' . ((int) ($data['planning']['target_semester'] ?? $inputs['target_semester']) === 1 ? 'First Semester' : 'Second Semester');
    $summarySection = ($data['planning']['section'] ?? $inputs['section']) !== '' ? ($data['planning']['section'] ?? $inputs['section']) : ($formPolicy['section_default'] ?? 'Not assigned — evaluator review');
    $summaryProposedCount = count($data['planning']['subject_ids'] ?? $inputs['subject_ids'] ?? []);
    if(!defined('BASE_URL'))define('BASE_URL','/udm-radar/');
    $source=file_get_contents(dirname(__DIR__,2).'/student/enrolment_planning.php');
    $start=strpos($source,'function pefCcPageEscape(');$end=strpos($source,'/** Bound actual bytes');
    if(!function_exists('pefCcPageEscape'))eval(substr($source,$start,$end-$start));
    $start=strpos($source,'<link rel="stylesheet"');$end=strpos($source,'<?php require_once __DIR__',$start);
    $template=substr($source,$start,$end-$start);$template=str_replace('__DIR__',var_export(dirname(__DIR__,2).'/student',true),$template);
    ob_start();try{$pageTitle='Enrolment Planning';require dirname(__DIR__,2).'/includes/header.php';eval('?>'.$template);echo '</body></html>';return ob_get_clean();}catch(Throwable $e){ob_end_clean();throw $e;}
}
if(($argv[1]??'')==='--render'){echo pefPrintRender($argv[2]??'normal');exit;}

$config=pefTestConfig();$term=['school_year'=>'2026-2027','semester'=>1];
$build=static fn(array $c,array $replace=[]):PefCcViewModel=>pefCcBuildViewModel($c,pefTestInputs(array_replace(['section'=>'IT-32','subject_ids'=>[1,2]],$replace)),pefTestReference(),$config,$term);
$base=pefTestContext();$original=$build($base);
$mapping=pefCcCurriculum()['mapping'];$subjects=array_merge(...array_column($mapping['terms'],'subjects'));
pefTestCheck(count($subjects)===58,'All 58 approved active SD subjects audited');
pefTestCheck(max(array_map(static fn(array $s):int=>mb_strlen($s['code']),$subjects))===6,'Longest active code is 6 characters');
pefTestCheck(max(array_map(static fn(array $s):int=>mb_strlen($s['title']),$subjects))===46,'Longest canonical title is 46 characters');
foreach($subjects as $s)pefTestCheck(!preg_match('/^(CY|DS)/',$s['code'])&&mb_strlen($s['code'])<=$config['printable']['recorded_code']&&mb_strlen($s['title'])<=$config['printable']['recorded_title'],'Operational limits cover '.$s['code'].' without truncation');

foreach(['name'=>60,'student_number'=>20,'recorded_code'=>6,'recorded_title'=>46,'proposed_code'=>6,'proposed_title'=>46] as $field=>$limit){
    foreach([$limit,$limit+1] as $length){
        $c=$base;$value=str_repeat('W',$length);
        if(in_array($field,['name','student_number'],true)){$c['identity'][$field]=$value;$key='identity.'.$field;}
        elseif(str_starts_with($field,'recorded_')){$part=substr($field,9);$c['records'][0][$part]=$value;$key='recorded.0.'.$part;}
        else{$part=substr($field,9);$c['catalog'][0][$part]=$value;$key='proposed.0.'.$part;}
        $before=$c;$m=$build($c)->data;
        if(str_starts_with($field,'recorded_'))$key='recorded.'.array_search($c['records'][0]['id'],array_column($m['recorded'],'id'),true).'.'.substr($field,9);
        pefTestCheck($m['exportable']===($length===$limit),$field.' boundary '.$length.' has correct export decision');
        pefTestCheck($c===$before,'No source mutation at '.$field.' boundary '.$length);
        if($length>$limit){
            pefTestCheck(isset($m['field_errors'][$key])&&in_array(PEF_CC_PRINT_LIMIT_MESSAGE,$m['errors'],true),'Visible exact field rejection for '.$field);
            pefTestCheck($m['planning']['section']==='IT-32'&&$m['planning']['subject_ids']===[1,2]&&$m['gwa']===$original->data['gwa'],'Safe planning inputs and GWA preserved after '.$field.' rejection');
            pefTestThrows(fn()=>pefCcSignSnapshot(new PefCcViewModel($m),random_bytes(32),$base['identity']['id'],'synthetic',$config),'Rejected '.$field.' cannot sign preview');
        }
    }
}
foreach(['code','title'] as $field){
    $c=$base;$c['records'][0][$field]="A\nB";$m=$build($c)->data;
    pefTestCheck(!$m['exportable']&&isset($m['field_errors']['recorded.0.'.$field]),'Line breaks cannot cross the recorded '.$field.' print row');
}
foreach([str_repeat('SYNTHETIC ',9).'ABCDEF',str_repeat('W',96),str_repeat('界',96)] as $section){
    $m=$build($base,['section'=>$section])->data;
    pefTestCheck(!$m['exportable']&&$m['field_errors']['section']===PEF_CC_SECTION_MESSAGE&&$m['planning']['section']===$section,'Arbitrary 96-character section is rejected without truncation');
}
$rejected=$build($base,['section'=>str_repeat('界',97)])->data;
pefTestCheck(!$rejected['exportable']&&isset($rejected['field_errors']['section']),'97-character section remains visibly rejected');
foreach(['IT-32','IT-IRREG'] as $section){$m=$build($base,['section'=>$section])->data;pefTestCheck($m['exportable']&&$m['planning']['section']===$section,'Approved operational code or pacing marker fits without wrapping: '.$section);}
$c=$base;$c['identity']['name']=str_repeat('ß',31);$m=$build($c)->data;
pefTestCheck(!$m['exportable']&&mb_strlen($m['identity']['name'])===62,'Identity limit is checked after display uppercase expansion');
pefTestThrows(fn()=>pefCcConfig(['printable'=>['name'=>999]]),'Printable contract cannot be overridden from request/deployment overrides');
$key=random_bytes(32);$token=pefCcSignSnapshot($original,$key,$base['identity']['id'],'synthetic',$config);
pefTestCheck($original->data['print_contract']===$config['printable'],'Print contract participates in immutable signed preview data');
$changed=$original->data;$changed['print_contract']['name']=61;
pefTestCheck(!pefCcVerifySnapshot($token,new PefCcViewModel($changed),$key,$base['identity']['id'],'synthetic',$config),'Changed printable limits invalidate an existing preview');

// Forging the exportable flag cannot bypass the second writer-boundary check.
$files=glob($config['temp_path'].'/pefcc-*')?:[];
foreach(['name147','code20','title150','number21'] as $case){
    $data=$original->data;
    if($case==='name147')$data['identity']['name']=str_repeat('N',147);
    if($case==='number21')$data['identity']['student_number']=str_repeat('S',21);
    if($case==='code20')$data['recorded'][0]['code']=str_repeat('W',20);
    if($case==='title150')$data['proposed'][0]['title']=str_repeat('T',150);
    try{pefCcGenerateWorkbook(new PefCcViewModel($data),$config);pefTestCheck(false,'Writer rejects forged '.$case);}
    catch(PefCcValidationException $e){pefTestCheck($e->errors===[PEF_CC_PRINT_LIMIT_MESSAGE]&&$e->fieldErrors!==[],'Writer rejects forged '.$case.' with safe field explanation');}
}
pefTestCheck((glob($config['temp_path'].'/pefcc-*')?:[])===$files,'Rejected writer requests create no temporary export');
for($i=0;$i<15;$i++){
    $s=pefTestSubjectIdentity(['token'=>'a1b2c3d4e5','subject_format'=>'compact-print-v1'],$i);
    pefTestCheck(strlen($s['code'])===6&&str_contains($s['title'],'a1b2c3d4e5')&&strlen($s['title'])<=46,'Compact synthetic catalogue keeps full ownership token in title: '.$i);
}
pefTestCheck(pefTestSubjectIdentity(['token'=>'a1b2c3d4e5'],0)===['code'=>'PCa1b2c3d4e501','title'=>'Synthetic planning subject 1'],'Legacy manifest ownership interpretation remains exact');
foreach(['record','proposed','identity'] as $case){
    $html=pefPrintRender($case);$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();$x=new DOMXPath($dom);
    pefTestCheck($x->query('//*[@id="pef-print-limit-explanation"]')->length===1,'Actual generated page explains '.$case.' print limit');
    pefTestCheck($x->query('//*[@aria-invalid="true" and @aria-describedby="pef-print-limit-explanation"]')->length>0,'Actual generated page highlights '.$case.' fields accessibly');
    pefTestCheck($x->query('//*[@id="pef-cc-download-form"]')->length===0,'Rejected '.$case.' page offers no download');
    pefTestCheck($x->query('//*[@data-testid="cc-preview"]//*[@aria-invalid]')->length===0,'Proposed/identity print errors never mark reference CC fields: '.$case);
    pefTestCheck($x->query('//*[@id="proposed-section"]/option[@value="IT-32" and @selected]')->length===1&&$x->query('//*[@id="pef-cc-subjects"]/option[@selected]')->length===2,'Rejected '.$case.' page retains safe section and subject choices');
}
pefTestFinish();
