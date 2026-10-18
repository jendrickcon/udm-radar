<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/config/pef_cc.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_export_service.php';
$config=pefTestConfig();
try {
 $reference=pefCcValidateTemplate($config);
 pefTestCheck(hash_file('sha256',$config['template_path'])===$config['template_sha256'],'Deployed candidate has approved immutable hash');
 $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($config['template_path']);
 pefTestCheck($book->getSheetNames()===['PEF','CC'],'Exactly two approved sheets; roster and Study Plan physically absent');
 $zip=new ZipArchive();pefTestCheck($zip->open($config['template_path'])===true,'Template ZIP reopens');$formulaCount=0;$mediaCount=0;
 for($i=0;$i<$zip->numFiles;$i++){
  $name=$zip->getNameIndex($i);$xml=$zip->getFromIndex($i);
  if(str_starts_with($name,'xl/media/'))$mediaCount++;
  pefTestCheck(!str_starts_with($name,'xl/externalLinks/')&&!str_contains($name,'vbaProject'),'No external-link or macro part '.$name);
  if(str_ends_with($name,'.rels'))pefTestCheck(!preg_match('/TargetMode\s*=\s*["\x27]External/i',$xml),'No external relationships '.$name);
  if(str_starts_with($name,'xl/worksheets/sheet')&&str_ends_with($name,'.xml')){
   preg_match_all('/<f(?:\s[^>]*)?>(.*?)<\/f>/s',$xml,$matches);$formulaCount+=count($matches[1]);
   foreach($matches[1] as $f)pefTestCheck(preg_match('/^SUM\([A-Z]+[0-9]+:[A-Z]+[0-9]+\)$/',html_entity_decode($f))===1,'Only approved bounded SUM formula: '.$f);
   pefTestCheck(!str_contains($xml,'[1]')&&!str_contains($xml,'StudentList')&&!str_contains($xml,'Need to Enroll'),'No stale lookup/roster/enrolment claims '.$name);
  }
 }
 $zip->close();pefTestCheck($formulaCount===9,'Nine allowed formula cells preserved');pefTestCheck($mediaCount===1,'Approved logo image retained');
 $pef=$book->getSheetByName('PEF');$cc=$book->getSheetByName('CC');
 $contract=pefCcTemplateContract($config['template_version']);
 pefTestCheck($pef->getStyle('A51')->getAlignment()->getWrapText()===$contract['section_wrap']
  &&$pef->getRowDimension(51)->getRowHeight()===$contract['section_height'],'Immutable version-specific section geometry: '.$config['template_version']);
 pefTestCheck(pefCcConfig()['template_version']==='v0.2-r6','Application default is activated v0.2-r6');
 pefTestCheck($pef->getRowDimension(52)->getRowHeight()===12.0&&$pef->getStyle('A51')->getFont()->getSize()===18.0
  &&$pef->getStyle('A51')->getAlignment()->getHorizontal()==='center'&&$pef->getStyle('A51')->getAlignment()->getVertical()==='center'
  &&!$pef->getStyle('A51')->getAlignment()->getShrinkToFit(),'Section label row, centered 18pt text and no shrink-to-fit preserved');
 foreach(range(54,65) as $row)pefTestCheck($pef->getRowDimension(row: $row)->getRowHeight()===12.0,'Proposed slot retained at original 12pt height: '.$row);
 if($config['template_version']==='v0.2-r3'){
  pefTestCheck($pef->getRowDimension(51)->getRowHeight()===19.5&&!$pef->getStyle('A51')->getAlignment()->getWrapText(),'r3 restores original compact row and disables wrapping');
  $base=$config['private_root'].'/templates/pef-cc/v0.2-r2/template.xlsx';
  pefTestCheck(hash_file('sha256',$base)===pefCcTemplateContract('v0.2-r2')['sha256'],'Original r2 version remains byte-for-byte unchanged');
  $old=new ZipArchive();$new=new ZipArchive();$old->open($base);$new->open($config['template_path']);$changed=[];
  for($i=0;$i<$old->numFiles;$i++){$part=$old->getNameIndex($i);if($old->getFromIndex($i)!==$new->getFromName($part))$changed[]=$part;}
  sort($changed);pefTestCheck($old->numFiles===$new->numFiles&&$changed===['xl/styles.xml','xl/worksheets/sheet1.xml'],'Only PEF row/style package parts differ; CC and metadata are byte-identical');
  $old->close();$new->close();
 }
 $blankCells=['A10','A12','A19','C19','C37','C38','C42','D42','E42','F42','C45','F45','A49','C49','A51','D68','D70','G68','G70'];
 if(!in_array($config['template_version'],['v0.2-r5','v0.2-r6'],true)){
  $blankCells=array_merge($blankCells,['B68','B70']);
 }
 foreach($blankCells as $cell)
  pefTestCheck($pef->getCell($cell)->getValue()===null || $pef->getCell($cell)->getValue()==='','Candidate identity/results/plans/approval field blank '.$cell);
 if(in_array($config['template_version'],['v0.2-r5','v0.2-r6'],true)){
  pefTestCheck($pef->getCell('B68')->getValue()==='MS. CHRISTINA P. ATAL','Configured Program Chairperson name present in B68');
  pefTestCheck($pef->getCell('B70')->getValue()==='MR. FRANCIS ARLANDO L. ATIENZA','Configured Dean name present in B70');
  pefTestCheck($pef->getCell('D69')->getValue()==='Signature','Signature label present in D69');
  pefTestCheck($pef->getCell('D71')->getValue()==='Signature','Signature label present in D71');
  pefTestCheck($pef->getCell('G69')->getValue()==='Date','Date label present in G69');
  pefTestCheck($pef->getCell('G71')->getValue()==='Date','Date label present in G71');
 }
 pefTestCheck((string)$cc->getCell('D51')->getValue()==="ITE252\nITE353",'ITE252 unresolved text retained exactly');
 foreach(['PEF','CC'] as $name){$s=$book->getSheetByName($name);pefTestCheck($s->getPageSetup()->getPrintArea()!=='','Bounded print area '.$name);pefTestCheck($s->getPageSetup()->getOrientation()==='portrait','Portrait print setting '.$name);}
 if(in_array($config['template_version'],['v0.2-r5','v0.2-r6'],true)){
  pefTestCheck($pef->getCell('A73')->getValue()===null || $pef->getCell('A73')->getValue()==='','v0.2-r5/r6 template A73 blank');
  pefTestCheck($pef->getCell('A74')->getValue()===null || $pef->getCell('A74')->getValue()==='','v0.2-r5/r6 template A74 blank');
 } else {
  pefTestCheck(str_contains((string)$pef->getCell('A74')->getValue(),'Student-prepared') || str_contains(json_encode($pef->toArray()),'Student-prepared'),'PEF planning-copy disclosure visible');
 }
 if($config['template_version']==='v0.2-r6'){
  pefTestCheck(str_contains((string)$cc->getCell('A3')->getValue(),'REFERENCE ONLY')&&$cc->getCell('A67')->getValue()===null,'v0.2-r6 retains CC reference title and cleans obsolete commentary');
  pefTestCheck((string)$cc->getCell('B66')->getValue()==='Prepared by','Prepared by label present in B66');
  pefTestCheck((string)$cc->getCell('J66')->getValue()==='Noted by','Noted by label present in J66');
  pefTestCheck($cc->getCell('B69')->getValue()===null,'Evaluator signature space blank in B69');
  pefTestCheck((string)$cc->getCell('J69')->getValue()==='MR. FRANCIS ARLANDO L. ATIENZA','Configured Dean name present in J69');
  pefTestCheck((string)$cc->getCell('B70')->getValue()==='Evaluator','Evaluator title label present in B70');
  pefTestCheck((string)$cc->getCell('J70')->getValue()==='Dean','Dean title label present in J70');
 } else {
  pefTestCheck(str_contains((string)$cc->getCell('A3')->getValue(),'REFERENCE ONLY')&&str_contains((string)$cc->getCell('A67')->getValue(),'approved student-to-curriculum mapping'),'Approved candidate retains CC reference/mapping disclosure');
 }
 pefTestThrows(fn()=>pefCcValidateTemplate(pefTestConfig(['template_path'=>$config['template_path'].'.missing'])),'Missing template rejected without source fallback');
 pefTestThrows(fn()=>pefCcValidateTemplate(pefTestConfig(['private_root'=>dirname(__DIR__,2),'template_path'=>$config['template_path']])),'Invalid private-root location rejected');
 pefTestThrows(fn()=>pefCcValidateTemplate(pefTestConfig(['temp_path'=>dirname(__DIR__,2).'/test-results'])),'Web-accessible temporary path rejected');
 // Exercise tampered bytes and package structure with an isolated blank copy.
 // The deployed approved template is never edited or used as a fallback.
 $tampered=$config['temp_path'].'/template-audit-'.bin2hex(random_bytes(16)).'.xlsx';
 try {
  copy($config['template_path'],$tampered);
  $z=new ZipArchive();$z->open($tampered);$xml=$z->getFromName('xl/worksheets/sheet1.xml');
  $xml=str_replace('SUM(G54:G65)','SUM(G54:G64)',$xml);$z->addFromString('xl/worksheets/sheet1.xml',$xml);$z->close();
  pefTestThrows(fn()=>pefCcValidateTemplate(pefTestConfig(['template_path'=>$tampered])),'Changed template bytes rejected by approved hash');
  pefTestThrows(fn()=>pefCcInspectPackage($tampered),'Unapproved formula destination/range rejected by package contract');
  copy($config['template_path'],$tampered);
  $z=new ZipArchive();$z->open($tampered);$name='xl/_rels/workbook.xml.rels';$xml=$z->getFromName($name);
  $xml=str_replace('</Relationships>','<Relationship Id="audit-external" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/externalLink" Target="https://invalid.example/audit" TargetMode="External"/></Relationships>',$xml);
  $z->addFromString($name,$xml);$z->close();
  pefTestThrows(fn()=>pefCcInspectPackage($tampered),'External relationship rejected without following the target');
  $wrongVersion=$config;$wrongVersion['template_version']='unapproved';
  pefTestThrows(fn()=>pefCcValidateTemplate($wrongVersion),'Unapproved template version rejected');
 } finally { if(is_file($tampered))unlink($tampered); }
 $book->disconnectWorksheets();
} catch(Throwable $e){ pefTestCheck(false,'Template validation prerequisite or integrity failure: '.$e->getMessage()); }
pefTestFinish();
