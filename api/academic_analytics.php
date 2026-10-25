<?php
declare(strict_types=1);
require_once __DIR__.'/../config/constants.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/analytics/service.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['error'=>'Use the read-only analytics view.']);exit;}
if(empty($_SESSION['user_id'])||!isSessionAccountActive()){http_response_code(401);echo json_encode(['error'=>'Sign in to view authorized analytics.']);exit;}
$user=currentUser();session_write_close();
try{echo json_encode(analyticsReport(getDB(),$user,$_GET),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);}
catch(DomainException){http_response_code(403);echo json_encode(['error'=>'These records are outside your authorized scope.']);}
catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Academic analytics unavailable: '.$e->getMessage());http_response_code(503);echo json_encode(['error'=>'Academic analytics could not be loaded.']);}
