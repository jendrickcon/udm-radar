<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_repository.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_export_service.php';
/** HTTP checks use the guarded loopback child only, never the normal Apache URL. */
function pefHttp(string $url,array|string|null $post=null,string &$cookie='',string $method=''):array{
 $ch=curl_init($url);$headers=[];
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>45,
  CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers,&$cookie){$parts=explode(':',$line,2);if(count($parts)===2){$key=strtolower(trim($parts[0]));$headers[$key]=trim($parts[1]);if($key==='set-cookie')$cookie=explode(';',$headers[$key],2)[0];}return strlen($line);}]);
 if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>is_array($post)?http_build_query($post):$post,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
 if($method!=='')curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$method);
 $body=curl_exec($ch);if($body===false)throw new RuntimeException('Loopback HTTP unavailable: '.curl_error($ch));$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return ['status'=>$status,'headers'=>$headers,'body'=>$body];
}
function pefForm(string $html,string $id):array{
 $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();$xpath=new DOMXPath($dom);$out=[];
 foreach($xpath->query('//*[@id="'.$id.'"]//input[@name]') as $input){$n=$input->getAttribute('name');$v=$input->getAttribute('value');if(str_ends_with($n,'[]'))$out[substr($n,0,-2)][]=$v;else $out[$n]=$v;}
 return $out;
}
function pefLogin(array $state,string $case,string &$cookie):array{
 return pefHttp($state['base_url'].'login.php',['userid'=>$state['fixture']['accounts'][$case]['login'],'password'=>$state['fixture']['password']],$cookie);
}
function pefPreview(array $state,string &$cookie,array $replace=[]):array{
 $page=pefHttp($state['base_url'].'student/enrolment_planning.php',null,$cookie);
 $transport=pefForm($page['body'],'pef-cc-planning-form');
 return pefHttp($state['base_url'].'student/enrolment_planning.php',array_replace($transport,['action'=>'preview'], $replace),$cookie);
}
/** Real HTTP PDF checks; native layout has a separate independent PDF parser gate. */
function pefAssertPublicPdf(array $response,string $case):void {
 pefTestCheck($response['status']===200,'PDF response succeeds: '.$case);
 pefTestCheck(($response['headers']['content-type']??'')==='application/pdf','Public MIME is PDF: '.$case);
 pefTestCheck(($response['headers']['content-disposition']??'')==='attachment; filename="UDM-RADAR-PEF-CC.pdf"','Public filename is PDF: '.$case);
 pefTestCheck(str_starts_with($response['body'],'%PDF-'),'Public body has PDF header: '.$case);
 preg_match_all('~/Type\\s*/Page\\b~',$response['body'],$pages);
 pefTestCheck(count($pages[0])===2,'Public response contains exactly two page objects: '.$case);
 pefTestCheck(!str_starts_with($response['body'],'PK')&&!str_contains($response['headers']['content-disposition']??'','.xlsx'),'No public XLSX fallback: '.$case);
}
function pefPrivateBook(PDO $db,array $account,array $input,array $config,array $current):array {
 $context=pefCcReadContext($db,$account['id']);
 $keys=array_flip(['evaluation_year','evaluation_semester','target_year','target_semester','section','subject_ids']);
 $model=pefCcBuildViewModel($context,array_intersect_key($input,$keys),pefCcTemplateReference($config),$config,$current);
 return [$model,pefCcGenerateWorkbook($model,$config)];
}
function pefChunkedPost(string $url,string $cookie,string $body): int {
 $parts=parse_url($url);
 if(($parts['host']??'')!=='127.0.0.1'||empty($parts['port'])||!str_starts_with($parts['path']??'','/udm-radar/'))throw new RuntimeException('Loopback chunked test URL required');
 $socket=fsockopen('127.0.0.1',(int)$parts['port'],$errno,$error,10);if(!$socket)throw new RuntimeException('Local chunked HTTP socket unavailable');
 stream_set_timeout($socket,15);
 try{fwrite($socket,'POST '.$parts['path']." HTTP/1.1\r\nHost: 127.0.0.1:".$parts['port']."\r\nCookie: ".$cookie."\r\nContent-Type: application/x-www-form-urlencoded\r\nTransfer-Encoding: chunked\r\nConnection: close\r\n\r\n".dechex(strlen($body))."\r\n".$body."\r\n0\r\n\r\n");$line=fgets($socket);if(!preg_match('~^HTTP/1\.[01] ([0-9]{3})~',$line?:'',$match))throw new RuntimeException('No HTTP status for chunked request');return(int)$match[1];}finally{fclose($socket);}
}
try{
 $db=pefTestScratch();$state=pefTestLoadState(getenv('PEF_CC_STATE_FILE')?:'');$m=$state['fixture'];pefTestValidateManifest($db,$m);$before=pefTestFingerprint($db);$base=$state['base_url'];
 $cookie='';$r=pefHttp($base.'student/export_pef_cc.php',null,$cookie);pefTestCheck($r['status']===302&&str_contains($r['headers']['location']??'','login.php'),'Unauthenticated download redirects through actual auth');
 foreach(['faculty','inactive'] as $case){$c='';$r=pefLogin($state,$case,$c);$r=pefHttp($base.'student/enrolment_planning.php',null,$c);pefTestCheck($r['status']===302&&str_contains($r['headers']['location']??'','login.php'),'Wrong-role/inactive auth denied: '.$case);}
 $r=pefLogin($state,'first',$cookie);pefTestCheck($r['status']===302&&str_contains($r['headers']['location']??'','student/index.php'),'Real login handler establishes Student session');
 $r=pefHttp($base.'student/export_pef_cc.php',null,$cookie);pefTestCheck($r['status']===405,'Authenticated GET download returns 405');
 $r=pefHttp($base.'student/export_pef_cc.php',pefTestInputs(),$cookie);pefTestCheck($r['status']===403,'CSRF-less POST blocked');
 $preview=pefPreview($state,$cookie,['subject_ids'=>array_slice($m['subjects'],0,2)]);pefTestCheck($preview['status']===200,'Actual server preview succeeds');
 $download=pefForm($preview['body'],'pef-cc-download-form');pefTestCheck(!empty($download['preview'])&&!empty($download['csrf_token']),'Owned preview emits signed envelope and CSRF token');
 $r=pefHttp($base.'student/export_pef_cc.php',$download,$cookie);pefAssertPublicPdf($r,'first safe review');
 foreach(['content-type'=>'application/pdf','content-disposition'=>'attachment; filename="UDM-RADAR-PEF-CC.pdf"','x-content-type-options'=>'nosniff','pragma'=>'no-cache'] as $k=>$v)pefTestCheck(($r['headers'][$k]??'')===$v,'Binary header '.$k);
 pefTestCheck(str_contains($r['headers']['cache-control']??'','no-store'),'Download private no-store');
 $cfg=pefCcConfig();$private=dirname(getenv('PEF_CC_STATE_FILE'));
 [$firstModel,$firstOutput]=pefPrivateBook($db,$m['accounts']['first'],$download,$cfg,$m['current_term']);
 try{$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($firstOutput['path']);
 pefTestCheck($book->getSheetNames()===['PEF','CC'],'Private intermediate retains exactly PEF and CC');
 pefTestCheck($book->getSheetByName('PEF')->getCell('C37')->getValue()===$firstModel->data['gwa'],'Private GWA agrees with signed selected term');
 pefTestCheck($book->getSheetByName('PEF')->getCell('A12')->getValue()===$m['accounts']['first']['login'],'Private workbook retains authenticated identity');
 $book->disconnectWorksheets();}finally{pefCcRemoveOutput($firstOutput['path'],$cfg);}
 pefTestCheck(count(glob($cfg['temp_path'].'/pefcc-*')?:[])===0,'Stream and private intermediate cleanup leave no job output');
 foreach(['student_id'=>$m['accounts']['second']['id'],'gwa'=>'4.00','sheet'=>'CC','output_path'=>'../evil','filename'=>'x.xlsx'] as $key=>$value){$r=pefHttp($base.'student/export_pef_cc.php',$download+[$key=>$value],$cookie);pefTestCheck($r['status']===422,'Unexpected authoritative/path input rejected: '.$key);}
 $r=pefHttp($base.'student/export_pef_cc.php',array_replace($download,['section'=>'CHANGED']),$cookie);pefTestCheck($r['status']===409,'Modified planning input rejects stale snapshot');
 $r=pefHttp($base.'student/export_pef_cc.php',array_replace($download,['preview'=>$download['preview'].'x']),$cookie);pefTestCheck($r['status']===409,'Tampered signed envelope rejected');
 $other='';pefLogin($state,'second',$other);$p=pefPreview($state,$other);$form=pefForm($p['body'],'pef-cc-download-form');$r=pefHttp($base.'student/export_pef_cc.php',array_replace($download,['csrf_token'=>$form['csrf_token']??'']),$other);pefTestCheck($r['status']===409,'Cross-Student signed-preview replay rejected');
 $same='';pefLogin($state,'first',$same);$p=pefPreview($state,$same);$form=pefForm($p['body'],'pef-cc-download-form');$r=pefHttp($base.'student/export_pef_cc.php',array_replace($download,['csrf_token'=>$form['csrf_token']??'']),$same);pefTestCheck($r['status']===409,'Same Student cross-session replay rejected');
 $a=$m['accounts']['first'];$s=$db->prepare('UPDATE grades SET final_grade=? WHERE id=? AND student_id=?');
 try{$s->execute(['3.25',$a['grade_ids'][0],$a['id']]);$r=pefHttp($base.'student/export_pef_cc.php',$download,$cookie);pefTestCheck($r['status']===409,'Changed authoritative grade rejects old preview');}finally{$s->execute(['3.00',$a['grade_ids'][0],$a['id']]);}
 $r=pefHttp($base.'student/export_pef_cc.php',str_repeat('x',17000),$cookie);pefTestCheck(in_array($r['status'],[413,422],true),'Oversized request bounded before writer');
 pefTestCheck(pefChunkedPost($base.'student/export_pef_cc.php',$cookie,'section='.str_repeat('x',17000))===413,'Chunked oversized body without Content-Length rejected before writer');
 foreach(['config/pef_cc.php','vendor/autoload.php','tests/fixtures/pef_cc_fixture.php','database/udm_radar.sql','../private/template.xlsx','%2e%2e/private/template.xlsx'] as $path){$r=pefHttp($base.$path,null,$cookie);pefTestCheck(in_array($r['status'],[400,404],true),'Private/source route unreachable: '.$path);}
 foreach(['allinc','overflow','mixed','empty'] as $case){$c='';pefLogin($state,$case,$c);$p=pefPreview($state,$c);$form=pefForm($p['body'],'pef-cc-download-form');if($case==='allinc')pefTestCheck(!empty($form['preview']),'All-INC term permits preview despite unavailable GWA');else pefTestCheck(empty($form['preview']),'Unexportable term never signs download: '.$case);}
 foreach(['regular1','regular2','regular3','regular4'] as $case){
  $a=$m['accounts'][$case];$c='';pefLogin($state,$case,$c);$p=pefPreview($state,$c,$a['inputs']);$form=pefForm($p['body'],'pef-cc-download-form');
  if(!$a['expected_exportable']){pefTestCheck(empty($form['preview']),'Unavailable future nominal section/terminal year requires review: '.$case);continue;}
  pefTestCheck($p['status']===200&&!empty($form['preview']),'Authenticated regular preview resolves read-only plan: '.$case);
  $r=pefHttp($base.'student/export_pef_cc.php',$form,$c);
  pefAssertPublicPdf($r,$case);
  [$regularModel,$regularOutput]=pefPrivateBook($db,$a,$form,$cfg,$m['current_term']);
  try{
   $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($regularOutput['path']);$sheet=$book->getSheetByName('PEF');
   pefTestCheck($sheet->getCell('A51')->getValue()===$regularModel->data['planning']['section'],'Private PEF section matches verified plan: '.$case);
   pefTestCheck($sheet->getCell('C37')->getValue()===$regularModel->data['gwa'],'Private selected-term GWA including Pending/blank matches model: '.$case);
   $codes=[];foreach($regularModel->data['proposed'] as $n=>$subject){$codes[]=$subject['code'];pefTestCheck($sheet->getCell('A'.(54+$n))->getValue()===$subject['code'],'Exact private nominal order at row '.(54+$n).': '.$case);}
   pefTestCheck(!array_filter($codes,static fn(string $code):bool=>preg_match('/^(CY|DS)/',$code)===1),'Private proposed list stays SD-only: '.$case);
   $book->disconnectWorksheets();
  }finally{pefCcRemoveOutput($regularOutput['path'],$cfg);}
  foreach([['subject_ids'=>empty($form['subject_ids'])?[$m['subjects'][0]]:[]],['section'=>'IT-29'],['target_semester'=>(int)$form['target_semester']===1?2:1]] as $attack){$r=pefHttp($base.'student/export_pef_cc.php',array_replace($form,$attack),$c);pefTestCheck($r['status']===409,'Regular tampering rejected by actual export endpoint: '.$case);}
 }
 $c='';pefLogin($state,'regular_missing',$c);$p=pefPreview($state,$c,$m['accounts']['regular_missing']['inputs']);$safeForm=pefForm($p['body'],'pef-cc-download-form');
 pefTestCheck(!empty($safeForm['preview'])&&str_contains($p['body'],'Download review planning PDF'),'Safe State C has exact review action and signed preview');
 pefAssertPublicPdf(pefHttp($base.'student/export_pef_cc.php',$safeForm,$c),'safe unresolved Regular C');
 $c='';pefLogin($state,'overflow',$c);$p=pefPreview($state,$c);$unsafeForm=pefForm($p['body'],'pef-cc-planning-form');
 $unsafeResponse=pefHttp($base.'student/export_pef_cc.php',array_replace($unsafeForm,['action'=>'download','preview'=>'invalid']),$c);
 pefTestCheck(in_array($unsafeResponse['status'],[409,422],true)&&!str_starts_with($unsafeResponse['body'],'%PDF-'),'Unsafe D direct endpoint rejects export');
 foreach(['legacy_cy','legacy_ds'] as $case){$c='';pefLogin($state,$case,$c);$p=pefPreview($state,$c);pefTestCheck(empty(pefForm($p['body'],'pef-cc-download-form')['preview']),'Review-only context cannot acquire export token: '.$case);}
 pefTestCheck(pefTestFingerprint($db)===$before,'Login, preview, HTTP export and rejected attacks preserve every application row');
}catch(Throwable $e){pefTestCheck(false,'Actual HTTP integration prerequisite/contract: '.$e->getMessage());}
pefTestFinish();
