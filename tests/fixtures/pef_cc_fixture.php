<?php
declare(strict_types=1);
/** Synthetic WP1 fixtures. No bootstrap, mutation, or connection occurs on include. */
require_once dirname(__DIR__, 2) . '/config/constants.php';

function pefTestCheck(bool $condition, string $message): void {
    $GLOBALS['pef_test_checks'] = ($GLOBALS['pef_test_checks'] ?? 0) + 1;
    if (!$condition) $GLOBALS['pef_test_failures'] = ($GLOBALS['pef_test_failures'] ?? 0) + 1;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL;
}
function pefTestThrows(callable $operation, string $message): void {
    try { $operation(); pefTestCheck(false, $message); }
    catch (Throwable $e) { pefTestCheck(true, $message); }
}
function pefTestFinish(): never {
    $checks = $GLOBALS['pef_test_checks'] ?? 0; $failures = $GLOBALS['pef_test_failures'] ?? 0;
    echo "SUMMARY: $checks assertions; $failures failures.\n";
    exit($failures ? 1 : 0);
}
function pefTestInputs(array $replace = []): array {
    return array_replace(['evaluation_year'=>'2024-2025','evaluation_semester'=>2,
        'target_year'=>'2026-2027','target_semester'=>1,'section'=>'SYN-A','subject_ids'=>[]], $replace);
}
function pefTestContext(int $count = 3, array $grades = ['3.00','INC','0.00']): array {
    $catalog = $records = [];
    for ($i=1;$i<=max(15,$count);$i++) {
        $catalog[]=['id'=>$i,'code'=>'SYN'.str_pad((string)$i,3,'0',STR_PAD_LEFT),
            'title'=>'Synthetic subject '.$i,'units'=>$i===2 ? 1 : 3];
        if ($i<=$count) $records[]=['id'=>$i,'subject_id'=>$i,'school_year'=>'2024-2025','semester'=>2,
            'is_current'=>0,'code'=>$catalog[$i-1]['code'],'title'=>$catalog[$i-1]['title'],
            'units'=>$catalog[$i-1]['units'],'grade'=>$grades[($i-1)%count($grades)]];
    }
    return ['identity'=>['id'=>900001,'student_number'=>'SYN-PEF-001','name'=>'EXAMPLE, SYNTHETIC',
        'college'=>'CCS','program'=>'BSIT - Software Development','is_active'=>1,'role'=>'student'],
        'records'=>$records,'catalog'=>$catalog];
}
function pefTestReference(): array {
    $rows=[];
    foreach(pefTestContext(6)['catalog'] as $i=>$s) $rows[]=$s+['prereq'=>$i===0?'ITE252':'',
        'grade_cell'=>'E'.($i+7),'remark_cell'=>'F'.($i+7),'status_cell'=>'G'.($i+7)];
    return ['version'=>'2022-2023','rows'=>$rows];
}
function pefTestScratch(): PDO {
    if (getenv('DB_NAME') !== 'udm_radar_scratch') throw new RuntimeException('DATABASE SAFETY BLOCKED: explicit DB_NAME=udm_radar_scratch required');
    $host=getenv('DB_HOST') ?: '127.0.0.1';
    if (!in_array($host,['127.0.0.1','localhost','::1'],true)) throw new RuntimeException('DATABASE SAFETY BLOCKED: loopback DB host required');
    $db=new PDO('mysql:host='.$host.';dbname=udm_radar_scratch;charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    pefTestAssertScratch($db);
    return $db;
}
function pefTestAssertScratch(PDO $db): void {
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='udm_radar_scratch') throw new RuntimeException('DATABASE SAFETY BLOCKED: actual target is not scratch');
}
/** Fingerprints every existing application table, including unrelated packages, without logging rows. */
function pefTestFingerprint(PDO $db, array $excludeUsers = [], array $excludeSubjects = []): array {
    pefTestAssertScratch($db); $result=[];
    foreach ($db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) throw new RuntimeException('Unexpected table identifier');
        $where='';$params=[];
        if ($excludeUsers && in_array($table,['users','student_profiles','grades'],true)) {
            $column=$table==='users'?'id':($table==='student_profiles'?'user_id':'student_id');
            $where=' WHERE `'.$column.'` NOT IN ('.implode(',',array_fill(0,count($excludeUsers),'?')).')'; $params=$excludeUsers;
        }
        if ($excludeSubjects && $table==='subjects') { $where=' WHERE id NOT IN ('.implode(',',array_fill(0,count($excludeSubjects),'?')).')'; $params=$excludeSubjects; }
        $s=$db->prepare('SELECT * FROM `'.$table.'`'.$where.' ORDER BY 1');$s->execute($params);
        $hash=hash_init('sha256');$count=0;
        while ($row=$s->fetch(PDO::FETCH_ASSOC)) { hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++; }
        $result[$table]=['count'=>$count,'sha256'=>hash_final($hash)];
    }
    return $result;
}
function pefTestNewId(PDO $db, string $table): int {
    if (!in_array($table,['users','student_profiles'],true)) throw new RuntimeException('Unsupported ID allocation');
    do { $id=random_int(1000000000,1800000000);$s=$db->prepare('SELECT id FROM '.$table.' WHERE id=?');$s->execute([$id]); } while($s->fetchColumn()!==false);
    return $id;
}
/** All IDs are manifest-owned; no schema migration, TRUNCATE, or existing-row replacement. */
function pefTestCreateFixture(PDO $db): array {
    pefTestAssertScratch($db);
    $type=$db->query("SHOW COLUMNS FROM grades LIKE 'final_grade'")->fetch();
    if (!str_starts_with(strtolower($type['Type']??''),'varchar')) throw new RuntimeException('Scratch final-grade migration prerequisite unavailable; no automatic rebuild');
    $token=bin2hex(random_bytes(5));$password=bin2hex(random_bytes(18));
    $manifest=['token'=>$token,'password'=>$password,'accounts'=>[],'subjects'=>[],'evaluation_year'=>'2024-2025','evaluation_semester'=>2,
        'target_year'=>'2026-2027','target_semester'=>1,'database'=>'udm_radar_scratch'];
    $lock='pefcc-fixture-'.$token;
    $s=$db->prepare('SELECT GET_LOCK(?, 0)');$s->execute([$lock]);
    if ((int)$s->fetchColumn()!==1) throw new RuntimeException('Fixture lock unavailable');
    $db->beginTransaction();
    try {
        $subject=$db->prepare('INSERT INTO subjects(code,title,units,year_level,semester) VALUES(?,?,?,?,?)');
        for($i=1;$i<=15;$i++) { $subject->execute(['PC'.$token.str_pad((string)$i,2,'0',STR_PAD_LEFT),'Synthetic planning subject '.$i,$i===2?1:3,(($i-1)%4)+1,($i%2)+1]);$manifest['subjects'][]=(int)$db->lastInsertId(); }
        $cases=['first'=>[1,'Regular',3],'second'=>[2,'Regular',3],'third'=>[3,'Regular',13],'fourth'=>[4,'Regular',3],
            'irregular'=>[2,'Irregular',3],'allinc'=>[1,'Regular',3],'empty'=>[1,'Regular',0],
            'overflow'=>[4,'Irregular',14],'mixed'=>[3,'Regular',3],'faculty'=>[null,null,0],'inactive'=>[1,'Regular',3]];
        $user=$db->prepare('INSERT INTO users(id,user_id,password_hash,role,first_name,last_name,is_active) VALUES(?,?,?,?,?,?,?)');
        $profile=$db->prepare('INSERT INTO student_profiles(id,user_id,student_number,course,college,year_level,section,status,record_status,current_gwa) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $grade=$db->prepare('INSERT INTO grades(student_id,subject_id,school_year,semester,final_grade,is_current) VALUES(?,?,?,?,?,?)');
        $accountIndex=0;
        foreach($cases as $key=>[$year,$status,$count]) {
            $id=pefTestNewId($db,'users');$login='PC'.$token.str_pad((string)++$accountIndex,2,'0',STR_PAD_LEFT);$role=$key==='faculty'?'faculty':'student';
            $user->execute([$id,$login,password_hash($password,PASSWORD_DEFAULT),$role,'Synthetic','PEFCC_'.$token.'_'.$key,$key==='inactive'?0:1]);
            $manifest['accounts'][$key]=['id'=>$id,'login'=>$login,'year_level'=>$year,'status'=>$status,'count'=>$count,'role'=>$role,'grade_ids'=>[]];
            if($role==='student') $profile->execute([pefTestNewId($db,'student_profiles'),$id,$login,'BSIT - Software Development','CCS',$year,'SYN-A',$status,'Active',4.00]);
            for($i=0;$i<$count;$i++) {
                $g=$key==='allinc'?'INC':(['3.00','INC','0.00',null,'1.00','4.00'][$i%6]);
                $grade->execute([$id,$manifest['subjects'][$i],'2024-2025',2,$g,$key==='mixed'&&$i===1?1:0]);
                $manifest['accounts'][$key]['grade_ids'][]=(int)$db->lastInsertId();
            }
        }
        $db->commit();return $manifest;
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
    finally { $s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]); }
}
function pefTestValidateManifest(PDO $db,array $m): void {
    pefTestAssertScratch($db);
    if(($m['database']??'')!=='udm_radar_scratch'||!preg_match('/^[0-9a-f]{10}$/',$m['token']??''))throw new RuntimeException('Invalid fixture ownership manifest');
    foreach($m['accounts'] as $key=>$a) { $s=$db->prepare('SELECT user_id,first_name,last_name FROM users WHERE id=?');$s->execute([$a['id']]);$r=$s->fetch();
        if($r && ($r['user_id']!==$a['login']||$r['first_name']!=='Synthetic'||$r['last_name']!=='PEFCC_'.$m['token'].'_'.$key))throw new RuntimeException('Fixture account ownership mismatch'); }
    foreach($m['subjects'] as $index=>$id){$s=$db->prepare('SELECT code FROM subjects WHERE id=?');$s->execute([$id]);$r=$s->fetchColumn();
        if($r!==false&&$r!=='PC'.$m['token'].str_pad((string)($index+1),2,'0',STR_PAD_LEFT))throw new RuntimeException('Fixture subject ownership mismatch');}
}
function pefTestCleanupFixture(PDO $db,array $m): void {
    pefTestValidateManifest($db,$m);$db->beginTransaction();
    try {
        foreach($m['accounts'] as $a) { $s=$db->prepare('DELETE FROM grades WHERE student_id=?');$s->execute([$a['id']]);$s=$db->prepare('DELETE FROM student_profiles WHERE user_id=?');$s->execute([$a['id']]);$s=$db->prepare('DELETE FROM users WHERE id=? AND user_id=?');$s->execute([$a['id'],$a['login']]); }
        foreach($m['subjects'] as $index=>$id){$s=$db->prepare('DELETE FROM subjects WHERE id=? AND code=?');$s->execute([$id,'PC'.$m['token'].str_pad((string)($index+1),2,'0',STR_PAD_LEFT)]);}
        $db->commit();
    } catch(Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
}
function pefTestLoadState(string $path): array {
    $real=realpath($path);$root=realpath(dirname(__DIR__,2));
    if(!$real||str_starts_with(strtolower(str_replace('\\','/',$real)),strtolower(str_replace('\\','/',$root)).'/'))throw new RuntimeException('Private state outside repository required');
    $s=json_decode(file_get_contents($real),true,64,JSON_THROW_ON_ERROR);
    if(!preg_match('~^http://127\\.0\\.0\\.1:[0-9]+/udm-radar/$~',$s['base_url']??''))throw new RuntimeException('Loopback mounted test server required');
    return $s;
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    try {
        $action=$argv[1]??'';$db=pefTestScratch();
        if($action==='create'){ $before=pefTestFingerprint($db);$m=pefTestCreateFixture($db);$m['baseline']=$before;echo json_encode($m,JSON_THROW_ON_ERROR); }
        elseif($action==='cleanup-stdin') {
            $m=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);pefTestCleanupFixture($db,$m);
            if(pefTestFingerprint($db)!==$m['baseline'])throw new RuntimeException('Existing application records changed during fixture lifecycle');
            echo '{"cleaned":true,"preserved":true}';
        }
        elseif(in_array($action,['cleanup','mutate-grade','restore-grade','fingerprint'],true)) {
            $state=pefTestLoadState($argv[2]??'');$m=$state['fixture'];pefTestValidateManifest($db,$m);
            if($action==='cleanup'){ pefTestCleanupFixture($db,$m);$after=pefTestFingerprint($db);if($after!==$m['baseline'])throw new RuntimeException('Existing application records changed during fixture lifecycle');echo json_encode(['cleaned'=>true,'preserved'=>true]); }
            elseif($action==='fingerprint') echo json_encode(pefTestFingerprint($db),JSON_THROW_ON_ERROR);
            else { $a=$m['accounts']['first'];$s=$db->prepare('UPDATE grades SET final_grade=? WHERE id=? AND student_id=?');$s->execute([$action==='mutate-grade'?'3.25':'3.00',$a['grade_ids'][0],$a['id']]);echo '{"updated":true}'; }
        } else throw new RuntimeException('Unknown fixture action');
    } catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
}
