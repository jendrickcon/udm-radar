<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/config/pef_cc.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_view_model.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_snapshot.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_export_service.php';
$config=pefTestConfig();$current=['school_year'=>'2026-2027','semester'=>1];
$context=pefTestContext(13,['1.00','2.00','2.25','2.50','3.25']);
$model=pefCcBuildViewModel($context,pefTestInputs(['subject_ids'=>range(1,12)]),pefTestReference(),$config,$current);
$key=random_bytes(32);$binding='synthetic-session-A';$student=$context['identity']['id'];$now=2000000000;
$token=pefCcSignSnapshot($model,$key,$student,$binding,$config,$now);
pefTestCheck(pefCcVerifySnapshot($token,$model,$key,$student,$binding,$config,$now+1),'Matching owned snapshot verifies');
pefTestCheck(pefCcVerifySnapshot($token,$model,$key,$student,$binding,$config,$now+599),'Unchanged repeat download valid before expiry');
foreach(['key','session','student','expiry','future','template'] as $case){
 $c=$config;if($case==='template')$c['template_sha256']=str_repeat('0',64);
 $ok=pefCcVerifySnapshot($token,$model,$case==='key'?random_bytes(32):$key,$case==='student'?$student+1:$student,$case==='session'?'other-session':$binding,$c,$case==='expiry'?$now+600:($case==='future'?$now-1:$now));
 pefTestCheck(!$ok,'Reject snapshot replay/change: '.$case);
}
foreach(['','bad',str_repeat('x',2049),$token.'x',substr($token,0,-3).'AAA'] as $bad)pefTestCheck(!pefCcVerifySnapshot($bad,$model,$key,$student,$binding,$config,$now),'Reject malformed or tampered envelope');
foreach(['identity','grade','units','section','proposed','reference'] as $case){
 $data=$model->data;
 if($case==='identity')$data['identity']['name'].=' CHANGED';
 if($case==='grade')$data['recorded'][0]['grade']='4.00';
 if($case==='units')$data['recorded'][0]['units']=9;
 if($case==='section')$data['planning']['section']='CHANGED';
 if($case==='proposed')$data['proposed']=array_reverse($data['proposed']);
 if($case==='reference')$data['cc']['rows'][0]['prereq']='CHANGED';
 pefTestCheck(!pefCcVerifySnapshot($token,new PefCcViewModel($data),$key,$student,$binding,$config,$now),'Stale authoritative/preview evidence rejected: '.$case);
}
pefTestCheck(!str_contains($token,$binding)&&!str_contains($token,bin2hex($key)),'Snapshot exposes neither signing key nor raw session binding');
pefTestCheck(pefCcCanonicalJson(['b'=>1,'a'=>2])===pefCcCanonicalJson(['a'=>2,'b'=>1]),'Canonical snapshot stable across object key order');
pefTestCheck(pefCcCanonicalJson([1,2])!==pefCcCanonicalJson([2,1]),'Canonical snapshot preserves academic row order');
$files=glob($config['temp_path'].'/pefcc-*')?:[];
foreach(['target_year','target_semester','forged_option','removed_evidence','wrong_evidence_term'] as $case){
 $data=$model->data;
 if($case==='target_year')$data['planning']['target_year']='2027-2028';
 if($case==='target_semester')$data['planning']['target_semester']=2;
 if($case==='forged_option'){$data['planning']['section']='IT-39';$data['policy']['section_options'][]='IT-39';}
 if($case==='removed_evidence')$data['policy']['section_evidence']=[];
 if($case==='wrong_evidence_term')foreach($data['policy']['section_evidence'] as &$e)$e['school_year']='2027-2028';unset($e);
 pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($data),$config),'Independent writer rejects forged context: '.$case);
}
$regular=pefTestRegularContext(2,2);$regularModel=pefCcBuildViewModel($regular,pefTestRegularInputs($regular,2),pefTestReference(),$config,['school_year'=>'2026-2027','semester'=>2]);
foreach(['distant_block','wrong_nominal_year','wrong_target','removed_provenance'] as $case){
 $data=$regularModel->data;
 if($case==='distant_block'){$data['planning']['section']='IT-39';$data['policy']['section_options'][]='IT-39';$data['policy']['section_evidence'][]=['section'=>'IT-39','year_level'=>3,'school_year'=>'2027-2028','semester'=>1,'source'=>'active_sd_current_records','evidence_sha256'=>str_repeat('a',64)];}
 if($case==='wrong_nominal_year'){$data['planning']['section']='IT-42';$data['policy']['section_options'][]='IT-42';}
 if($case==='wrong_target')$data['planning']['target_year']='2028-2029';
 if($case==='removed_provenance')$data['policy']['section_evidence']=[];
 pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($data),$config),'Regular writer independently rejects target/adjacency forgery: '.$case);
}
pefTestCheck((glob($config['temp_path'].'/pefcc-*')?:[])===$files,'Forged options, target and provenance create no export file');
$sameYear=pefTestRegularContext(2,1);$sameYearModel=pefCcBuildViewModel($sameYear,pefTestRegularInputs($sameYear),pefTestReference(),$config,$current);
$data=$sameYearModel->data;$data['planning']['section']='IT-23';$data['policy']['section_options'][]='IT-23';
pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($data),$config),'Same-year writer retains only the original section despite forged option membership');
foreach(['identity_year','anchor_year','lifecycle','inactive','role','empty_anchor'] as $case){
 $data=$sameYearModel->data;
 if($case==='identity_year')$data['identity']['year_level']=3;
 if($case==='anchor_year')$data['policy']['profile_term']['year_level']=3;
 if($case==='lifecycle')$data['identity']['record_status']='Archived';
 if($case==='inactive')$data['identity']['is_active']=0;
 if($case==='role')$data['identity']['role']='faculty';
 if($case==='empty_anchor')$data['policy']['profile_term']['records']=[];
 pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($data),$config),'Writer rejects inconsistent active Student identity/anchor: '.$case);
}
pefTestCheck((glob($config['temp_path'].'/pefcc-*')?:[])===$files,'Identity/anchor rejection creates no output');
foreach(['current','duplicate'] as $case){
 $c=pefTestContext();$extra=$c['records'][0];$extra['id']=99;$extra['school_year']='2023-2024';
 if($case==='current'){$extra['code']=$c['catalog'][3]['code'];$extra['subject_id']=$c['catalog'][3]['id'];$extra['is_current']=1;$extra['school_year']='2026-2027';$extra['semester']=1;}
 $c['records'][]=$extra;
 $a=pefCcBuildViewModel($c,pefTestInputs(),pefTestReference(),$config,$current);$c['records'][3]['grade']='1.25';$b=pefCcBuildViewModel($c,pefTestInputs(),pefTestReference(),$config,$current);
 $signed=pefCcSignSnapshot($a,$key,$student,$binding,$config,$now);
 $row=$case==='current'?3:0;pefTestCheck($a->data['cc']['rows'][$row]['state']===$b->data['cc']['rows'][$row]['state'],'Visible CC state stable while '.$case.' evidence changes');
 pefTestCheck(!pefCcVerifySnapshot($signed,$b,$key,$student,$binding,$config,$now),'Changed hidden '.$case.' evidence invalidates snapshot');
}
pefTestThrows(fn()=>new PefCcViewModel(['db'=>new stdClass()]),'No mutable objects in model');
try {
 $reference=pefCcTemplateReference($config);$before=hash_file('sha256',$config['template_path']);
 $build=fn(array $ctx,array $input=[])=>pefCcBuildViewModel($ctx,pefTestInputs($input),$reference,$config,$current);
 $model=$build($context,['subject_ids'=>range(1,12)]);
 $generated=pefCcGenerateWorkbook($model,$config);
 try {
  pefTestCheck(is_file($generated['path'])&&$generated['bytes']===filesize($generated['path']),'Complete private file exists with reported byte count');
  pefTestCheck(hash_file('sha256',$generated['path'])===$generated['sha256'],'Generated checksum matches output');
  pefTestCheck(preg_match('/^pefcc-[a-f0-9]{32}\.xlsx$/',basename($generated['path']))===1,'Random fixed-format private basename contains no Student values');
  $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($generated['path']);$template=\PhpOffice\PhpSpreadsheet\IOFactory::load($config['template_path']);
  pefTestCheck($book->getSheetNames()===['PEF','CC'],'Writer creates no extra sheets');
  pefTestCheck(str_contains((string)$book->getSheetByName('CC')->getCell('A3')->getValue(),'Reference / Requires Review'),'Generated CC title visibly states applicability review boundary');
  $pef=$book->getSheetByName('PEF');
  pefTestCheck($pef->getCell('C37')->getDataType()==='n'&&$pef->getCell('C37')->getValue()===$model->data['gwa'],'GWA is canonical numeric scalar');
  pefTestCheck($pef->getCell('A35')->getValue()===$model->data['recorded'][12]['code'],'Thirteenth recorded subject written, not dropped');
  pefTestCheck($pef->getCell('A65')->getValue()===$model->data['proposed'][11]['code'],'Twelfth proposed subject written');
  pefTestCheck((float)$pef->getCell('G66')->getCalculatedValue()===(float)array_sum(array_column($model->data['proposed'],'units')),'Proposed total formula evaluates accurately');
  foreach(['C38','C42','F45','D68','D70','G68','G70'] as $cell)pefTestCheck($pef->getCell($cell)->getValue()===null,'Withheld approval/standing blank '.$cell);
  pefTestCheck($pef->getCell('B68')->getValue()==='MS. CHRISTINA P. ATAL','Configured Program Chairperson name written');
  pefTestCheck($pef->getCell('B69')->getValue()==='Program Chairperson','Program Chairperson title written');
  pefTestCheck($pef->getCell('B70')->getValue()==='MR. FRANCIS ARLANDO L. ATIENZA','Configured Dean name written');
  pefTestCheck($pef->getCell('B71')->getValue()==='Dean','Dean title written');
  pefTestCheck($pef->getCell('D69')->getValue()==='Signature'&&$pef->getCell('D71')->getValue()==='Signature','Signature labels preserved');
  pefTestCheck($pef->getCell('G69')->getValue()==='Date'&&$pef->getCell('G71')->getValue()==='Date','Date labels preserved');
  foreach(['PEF','CC'] as $name){$s=$book->getSheetByName($name);$t=$template->getSheetByName($name);
   $expectedMerges=$t->getMergeCells();
   if($name==='PEF' && in_array($model->data['mode'],['provisional','review'],true))foreach(['A73:G73','A74:G75'] as $merge)$expectedMerges[$merge]=$merge;
   pefTestCheck($s->getMergeCells()===$expectedMerges,'Only approved provisional notice merges added '.$name);
   pefTestCheck($s->getPageSetup()->getPrintArea()===($name==='PEF' && $model->data['mode']!=='normal'?'A1:G75':$t->getPageSetup()->getPrintArea()),'Print area follows exact approved state geometry '.$name);
   pefTestCheck($s->getPageSetup()->getPaperSize()===$t->getPageSetup()->getPaperSize(),'Paper configuration preserved '.$name);
   pefTestCheck(count($s->getDataValidationCollection())===count($t->getDataValidationCollection()),'Validation preserved '.$name);
   pefTestCheck(count($s->getConditionalStylesCollection())===count($t->getConditionalStylesCollection()),'Conditional formatting preserved '.$name);
  }
  $parts=static function(string $path):array{$z=new ZipArchive();$z->open($path);$a=[];for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i);if(str_starts_with($n,'xl/media/'))$a[]=hash('sha256',$z->getFromIndex($i));}$z->close();sort($a);return $a;};
  pefTestCheck($parts($generated['path'])===$parts($config['template_path']),'Original logo image bytes preserved');
  $book->disconnectWorksheets();$template->disconnectWorksheets();
 } finally { pefCcRemoveOutput($generated['path'],$config); }
 pefTestCheck(!file_exists($generated['path']),'Success cleanup removes owned output');
 $empty=$build(pefTestContext(3,['INC']));
 $incompleteOutput=pefCcGenerateWorkbook($empty,$config);
 try{$incompleteBook=\PhpOffice\PhpSpreadsheet\IOFactory::load($incompleteOutput['path']);pefTestCheck($incompleteBook->getSheetByName('PEF')->getCell('C37')->getValue()===null,'Incomplete INC term exports with blank selected-term GWA');$incompleteBook->disconnectWorksheets();}finally{pefCcRemoveOutput($incompleteOutput['path'],$config);}
 $blank=$build(pefTestContext(3,['','   ',null]));
 $blankOutput=pefCcGenerateWorkbook($blank,$config);
 try{$blankBook=\PhpOffice\PhpSpreadsheet\IOFactory::load($blankOutput['path']);pefTestCheck($blankBook->getSheetByName('PEF')->getCell('C37')->getValue()===null,'Blank/null term exports without inventing GWA');$blankBook->disconnectWorksheets();}finally{pefCcRemoveOutput($blankOutput['path'],$config);}
 // Writer defense independent of form rejection: even control/newline prefixes cannot create formulas.
 $payloads=['=1+1','+SUM(A1:A2)','-1+2','@SUM(A1)','  =HYPERLINK("https://invalid.example")',"\t=1+1","\r=1+1","\n=1+1","\r\n=1+1",'Unicode 界 ñ','../name.xlsx','a<>:"|?*'];
 $data=$model->data;
 $primitive=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$primitiveSheet=$primitive->getActiveSheet();
 foreach($payloads as $i=>$payload){
  pefCcWriteCell($primitiveSheet,'A'.($i+1),$payload);
  $cell=$primitiveSheet->getCell('A'.($i+1));$expected=str_replace(["\r\n","\r"],"\n",$payload);
  pefTestCheck($cell->getDataType()==='s'&&$cell->getValue()===$expected,'Low-level explicit typing and newline normalization retained for payload '.($i+1));
  if(preg_match('/[\t\r\n]/',$payload)){
   $invalid=$model->data;$invalid['recorded'][$i]['title']=$payload;
   pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($invalid),$config),'Multiline/control source title now rejected by print contract before export');
   $data['recorded'][$i]['title']='LINEFEED REJECTED';
  }else{$data['recorded'][$i]['title']=$payload;}
 }
 $primitive->disconnectWorksheets();
 $data['planning']['section']='=HYPERLINK("https://invalid.example")';
 $files=glob($config['temp_path'].'/pefcc-*')?:[];
 pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($data),$config),'Formula-like section rejected independently by writer membership check');
 pefTestCheck((glob($config['temp_path'].'/pefcc-*')?:[])===$files,'Rejected formula-like section creates no file');
 $data['planning']['section']=$model->data['planning']['section'];
 $out=pefCcGenerateWorkbook(new PefCcViewModel($data),$config);
 try{$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($out['path']);$s=$book->getSheetByName('PEF');
  foreach($payloads as $i=>$payload){$cell=$s->getCell('B'.($i+23));$expected=preg_match('/[\t\r\n]/',$payload)?'LINEFEED REJECTED':$payload;pefTestCheck($cell->getDataType()==='s'&&$cell->getValue()===$expected,'Within-contract untrusted source text remains literal at row '.($i+23));}
  pefTestCheck($s->getCell('A51')->getDataType()==='s'&&$s->getCell('A51')->getValue()===$data['planning']['section'],'Approved section stored exactly as explicit text');
  $z=new ZipArchive();$z->open($out['path']);$formulas=0;for($i=0;$i<$z->numFiles;$i++){ $n=$z->getNameIndex($i);$x=$z->getFromIndex($i);if(str_ends_with($n,'.rels'))pefTestCheck(!str_contains($x,'TargetMode="External"'),'No injected external relationship '.$n);if(str_starts_with($n,'xl/worksheets/sheet')&&str_ends_with($n,'.xml')){$formulas+=preg_match_all('/<f(?:\s[^>]*)?>/',$x);}}
  $z->close();pefTestCheck($formulas===9,'Injection introduces zero additional formulas');$book->disconnectWorksheets();
 }finally{pefCcRemoveOutput($out['path'],$config);}
 $beforeFiles=glob($config['temp_path'].'/pefcc-*')?:[];
 pefTestThrows(fn()=>pefCcGenerateWorkbook($build(pefTestContext(14)),$config),'Overflow rejected before output');
 pefTestThrows(fn()=>pefCcGenerateWorkbook($model,pefTestConfig(['max_output_bytes'=>1])),'Forced output-size failure rejected');
 $invalid=$model->data;$invalid['recorded'][0]['title']="Bad\x01XML";
 pefTestThrows(fn()=>pefCcGenerateWorkbook(new PefCcViewModel($invalid),$config),'XML-invalid source text rejected even at writer boundary');
 pefTestCheck((glob($config['temp_path'].'/pefcc-*')?:[])===$beforeFiles,'Failure path leaves no temporary file');
 pefTestCheck(hash_file('sha256',$config['template_path'])===$before,'Generation never overwrites template');
 pefTestThrows(fn()=>pefCcRemoveOutput($config['template_path'],$config),'Cleanup refuses template path');
 pefTestThrows(fn()=>pefCcRemoveOutput($config['temp_path'].'/../outside.xlsx',$config),'Cleanup rejects traversal');
 $unrelated=$config['temp_path'].'/unrelated-'.bin2hex(random_bytes(8)).'.txt';file_put_contents($unrelated,'Synthetic unrelated sentinel');
 $owned=$config['temp_path'].'/pefcc-'.bin2hex(random_bytes(16)).'.tmp';file_put_contents($owned,'Synthetic abandoned output');touch($owned,time()-1000);
 try{$dry=pefCcCleanupExports($config,false);pefTestCheck(file_exists($owned),'Dry cleanup preserves abandoned output');$done=pefCcCleanupExports($config,true);pefTestCheck(!file_exists($owned)&&file_exists($unrelated),'Bounded reaper removes only owned aged file');}finally{if(file_exists($owned))unlink($owned);unlink($unrelated);}
} catch(Throwable $e){pefTestCheck(false,'Writer prerequisite/contract: '.$e->getMessage());}
pefTestFinish();
