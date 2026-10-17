<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/config/pef_cc.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_export_service.php';
$config=pefCcConfig();
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
 foreach(['A10','A12','A19','C19','C37','C38','C42','D42','E42','F42','C45','F45','A49','C49','A51','B68','B70','D68','D70','G68','G70'] as $cell)
  pefTestCheck($pef->getCell($cell)->getValue()===null || $pef->getCell($cell)->getValue()==='','Candidate identity/results/plans/approval field blank '.$cell);
 pefTestCheck((string)$cc->getCell('D51')->getValue()==="ITE252\nITE353",'ITE252 unresolved text retained exactly');
 foreach(['PEF','CC'] as $name){$s=$book->getSheetByName($name);pefTestCheck($s->getPageSetup()->getPrintArea()!=='','Bounded print area '.$name);pefTestCheck($s->getPageSetup()->getOrientation()==='portrait','Portrait print setting '.$name);}
 pefTestCheck(str_contains((string)$pef->getCell('A74')->getValue(),'Student-prepared') || str_contains(json_encode($pef->toArray()),'Student-prepared'),'PEF planning-copy disclosure visible');
 pefTestCheck(str_contains((string)$cc->getCell('A3')->getValue(),'REFERENCE ONLY')&&str_contains((string)$cc->getCell('A67')->getValue(),'approved student-to-curriculum mapping'),'Approved candidate retains CC reference/mapping disclosure');
 pefTestThrows(fn()=>pefCcValidateTemplate(pefCcConfig(['template_path'=>$config['template_path'].'.missing'])),'Missing template rejected without source fallback');
 pefTestThrows(fn()=>pefCcValidateTemplate(pefCcConfig(['private_root'=>dirname(__DIR__,2),'template_path'=>$config['template_path']])),'Invalid private-root location rejected');
 pefTestThrows(fn()=>pefCcValidateTemplate(pefCcConfig(['temp_path'=>dirname(__DIR__,2).'/test-results'])),'Web-accessible temporary path rejected');
 // Exercise tampered bytes and package structure with an isolated blank copy.
 // The deployed approved template is never edited or used as a fallback.
 $tampered=$config['temp_path'].'/template-audit-'.bin2hex(random_bytes(16)).'.xlsx';
 try {
  copy($config['template_path'],$tampered);
  $z=new ZipArchive();$z->open($tampered);$xml=$z->getFromName('xl/worksheets/sheet1.xml');
  $xml=str_replace('SUM(G54:G65)','SUM(G54:G64)',$xml);$z->addFromString('xl/worksheets/sheet1.xml',$xml);$z->close();
  pefTestThrows(fn()=>pefCcValidateTemplate(pefCcConfig(['template_path'=>$tampered])),'Changed template bytes rejected by approved hash');
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
