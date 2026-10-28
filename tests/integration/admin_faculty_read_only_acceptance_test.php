<?php
declare(strict_types=1);
// Explicit opt-in, SELECT-only integration against the owner's synthetic local DB.
// Never creates/loads/cleans any database, changes account/session data, or issues academic writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('PHASE_C_READ_ONLY_DB') !== 'udm_radar') {
    fwrite(STDERR, "NOT RUN: Set PHASE_C_READ_ONLY_DB=udm_radar for authorized read-only verification.\n"); exit(2);
}
$acceptanceCompleted = false;
register_shutdown_function(static function() use (&$acceptanceCompleted): void {
    if (!$acceptanceCompleted) {
        fwrite(STDERR, "FAIL: Read-only acceptance terminated before completing its checks.\n");
        exit(1);
    }
});
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../includes/analytics/service.php';
$db = getDB();
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'udm_radar') throw new RuntimeException('Unexpected target; no test executed');
$db->exec('SET SESSION TRANSACTION READ ONLY');
function acceptanceFingerprint(PDO $db): array {
    $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $out = [];
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Unsafe table identifier');
        $rows = array_map(static fn($row)=>json_encode($row,JSON_THROW_ON_ERROR), $db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC));
        sort($rows,SORT_STRING);
        $out[$table] = ['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];
    }
    return $out;
}
$before = acceptanceFingerprint($db);
if (($argv[1] ?? '') === '--snapshot') {echo json_encode($before,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);$acceptanceCompleted=true;exit;}
$passed=0;$failed=0;
function acceptanceCheck(bool $ok,string $label):void {global $passed,$failed;$ok?$passed++:$failed++;echo ($ok?'PASS: ':'FAIL: ').$label."\n";}
$adminId=(int)$db->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
$faculty=$db->query("SELECT id FROM users WHERE role='faculty' AND is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
acceptanceCheck($adminId>0&&count($faculty)>=2,'Documented synthetic Admin and two Faculty accounts are available');
$db->beginTransaction();
try {
    $all=analyticsReport($db,['id'=>$adminId,'role'=>'admin'],[]);
    foreach($faculty as $facultyId){
        $id=(int)$facultyId;$actor=['id'=>$id,'role'=>'faculty'];$report=analyticsReport($db,$actor,[]);
        $loads=$db->prepare('SELECT subject_id,section FROM faculty_class_loads WHERE faculty_user_id=?');$loads->execute([$id]);$assigned=$loads->fetchAll(PDO::FETCH_ASSOC);
        $subjects=array_map('strval',array_column($assigned,'subject_id'));$sections=array_column($assigned,'section');
        acceptanceCheck(!array_diff(array_column($report['groups']['subject'],'key'),$subjects),'Faculty '.$id.' academic subject groups are assigned only');
        acceptanceCheck(!array_diff(array_column($report['groups']['section'],'key'),$sections),'Faculty '.$id.' section groups are assigned only');
        acceptanceCheck(array_column($report['options']['faculty'],'value')===[(string)$id],'Faculty '.$id.' options reveal only self');
        $adminSubset=analyticsReport($db,['id'=>$adminId,'role'=>'admin'],['faculty'=>(string)$id]);
        acceptanceCheck($report['summary']===$adminSubset['summary'],'Faculty '.$id.' and Admin filtered summary have exact parity');
        $own=$db->prepare('SELECT id FROM support_case_referrals WHERE faculty_id=? ORDER BY id');$own->execute([$id]);$ownIds=array_map('intval',$own->fetchAll(PDO::FETCH_COLUMN));
        acceptanceCheck(!array_diff(array_column($report['drilldowns'],'record_id'),$ownIds),'Faculty '.$id.' drill-down IDs belong to own referrals');
        acceptanceCheck($report['support']['referrals_created']===count($ownIds),'Faculty '.$id.' own-referral count matches existing synthetic records');
        $foreign=(int)array_values(array_filter($faculty,static fn($other)=>(int)$other!==$id))[0];
        try {analyticsReport($db,$actor,['faculty'=>(string)$foreign]);acceptanceCheck(false,'Foreign Faculty filter denied');}catch(DomainException){acceptanceCheck(true,'Faculty '.$id.' foreign Faculty filter denied');}
        $missing=analyticsReport($db,$actor,['section'=>'UNASSIGNED-ACCEPTANCE']);
        acceptanceCheck($missing['summary']['student_count']===0&&$missing['support']['referrals_created']===0&&$missing['drilldowns']===[],'Faculty '.$id.' unassigned section reveals no grades/referrals');
        $foreignSubjects=$db->prepare('SELECT subject_id FROM faculty_class_loads WHERE faculty_user_id=?');$foreignSubjects->execute([$foreign]);
        $unassigned=array_values(array_diff(array_map('strval',$foreignSubjects->fetchAll(PDO::FETCH_COLUMN)),$subjects));
        acceptanceCheck(count($unassigned)>0,'Faculty '.$id.' has a concrete foreign-only subject to test');
        if($unassigned){$denied=analyticsReport($db,$actor,['subject'=>$unassigned[0]]);acceptanceCheck($denied['summary']['expected_grade_count']===0&&$denied['drilldowns']===[],'Faculty '.$id.' foreign-only subject reveals no records');}
        acceptanceCheck($report['faculty_comparison']['change']['direction']==='Unavailable','Faculty '.$id.' historical attribution remains unavailable');
        try {analyticsReport($db,['id'=>$id,'role'=>'admin'],[]);acceptanceCheck(false,'Tampered actual role denied');}catch(DomainException){acceptanceCheck(true,'Faculty '.$id.' actual account role verified');}
    }
    foreach([['student_id'=>'999'],['subject'=>['1']],['semester'=>'9'],['school_year'=>'2026-2029']] as $filters){try{analyticsReport($db,['id'=>$adminId,'role'=>'admin'],$filters);acceptanceCheck(false,'Malformed/unsupported filter rejected');}catch(InvalidArgumentException){acceptanceCheck(true,'Malformed/unsupported filter rejected');}}
    acceptanceCheck(!str_contains(json_encode($all),'student_id'),'Aggregate report excludes raw Student IDs');
    $db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
$after=acceptanceFingerprint($db);
acceptanceCheck($before===$after,'All application tables preserve row counts and SHA-256 contents');
echo "RESULT $passed passed, $failed failed; read-only actual repository integration; no fixture/migration/cleanup\n";
$acceptanceCompleted = true;
exit($failed?1:0);
