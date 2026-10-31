<?php
declare(strict_types=1);
require_once __DIR__.'/analytics_mvp_fixture.php';
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(403);exit;}
$stateFile=getenv('STUDENT_TREND_STATE');
if(!$stateFile||!is_file($stateFile)){http_response_code(503);exit;}
$state=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
if(getenv('DB_NAME')!==analyticsFixtureName($state['database'])){http_response_code(503);exit;}
analyticsFixtureConnect($state);
$root=realpath(__DIR__.'/../..');$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'');
if(!str_starts_with($path,'/udm-radar/')){http_response_code(404);exit;}
$relative=substr($path,11);
$allowed=['login.php','logout.php','student/index.php','student/dashboard.php','student/trend.php','student/grades.php','student/academic_analytics.php','student/enrolment_planning.php','student/feedback.php','api/academic_analytics.php','admin/index.php','admin/analytics.php','faculty/index.php','faculty/assigned_analytics.php'];
$static=str_starts_with($relative,'assets/')&&preg_match('/\.(css|js|png|svg|jpg|woff2)$/D',$relative);
$file=realpath($root.'/'.$relative);
if(!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||(!in_array($relative,$allowed,true)&&!$static)){http_response_code(404);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET' && !in_array($relative,['login.php','student/enrolment_planning.php'],true)){http_response_code(405);exit;}
if($static){$ext=pathinfo($file,PATHINFO_EXTENSION);header('Content-Type: '.(['css'=>'text/css','js'=>'text/javascript','png'=>'image/png','svg'=>'image/svg+xml','jpg'=>'image/jpeg','woff2'=>'font/woff2'][$ext]));readfile($file);exit;}
$_SERVER['SCRIPT_NAME']='/udm-radar/'.$relative;$_SERVER['SCRIPT_FILENAME']=$file;
chdir(dirname($file));require $file;
