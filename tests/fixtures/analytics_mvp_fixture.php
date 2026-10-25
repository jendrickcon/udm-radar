<?php
declare(strict_types=1);
// Test utility only. No current/live/scratch database or onboarding seed is used.
const ANALYTICS_FIXTURE_PREFIX='udm_radar_analytics_mvp_';
function analyticsFixtureName(string $name):string {
    if(!preg_match('/^udm_radar_analytics_mvp_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Only a newly owned disposable analytics database is permitted');
    return $name;
}
function analyticsFixtureServer():PDO {
    return new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function analyticsFixtureConnect(array $state):PDO {
    $name=analyticsFixtureName((string)($state['database']??''));
    $db=analyticsFixtureServer();$db->exec('USE `'.$name.'`');
    $token=$db->query('SELECT token FROM analytics_fixture_owner')->fetchColumn();
    if(!is_string($state['token']??null)||!hash_equals($token,$state['token']))throw new RuntimeException('Disposable fixture ownership mismatch');
    if($db->query('SELECT DATABASE()')->fetchColumn()!==$name)throw new RuntimeException('Disposable target mismatch');
    return $db;
}
function analyticsFixtureInsert(PDO $db,string $table,array $rows):void {
    if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Invalid fixture table');
    foreach($rows as $r){$cols=array_keys($r);$stmt=$db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');$stmt->execute(array_values($r));}
}
function analyticsFixtureSchemaStatements(string $sql,?array &$sections=null):array {
    $out=[];$sections=[];$statement='';$quote=null;$lineComment=false;$blockComment=false;$length=strlen($sql);$rawStart=0;
    for($i=0;$i<$length;$i++){
        $ch=$sql[$i];$next=$sql[$i+1]??'';
        if($lineComment){if($ch==="\n"){$lineComment=false;$statement.="\n";}continue;}
        if($blockComment){if($ch==='*'&&$next==='/'){$blockComment=false;$i++;}continue;}
        if($quote!==null){
            $statement.=$ch;
            if($ch==='\\'&&$next!==''){$statement.=$next;$i++;}
            elseif($ch===$quote){if($next===$quote){$statement.=$next;$i++;}else{$quote=null;}}
            continue;
        }
        if($ch==='-'&&$next==='-'&&ctype_space($sql[$i+2]??' ')){$lineComment=true;$i++;continue;}
        if($ch==='/'&&$next==='*'){$blockComment=true;$i++;continue;}
        if(in_array($ch,["'",'"','`'],true))$quote=$ch;
        $statement.=$ch;
        if($ch===';'){
            $trim=trim($statement);
            if(preg_match('/^CREATE TABLE `([^`]+)`/i',$trim,$match)){
                $out[]=$trim;$start=strpos($sql,'CREATE TABLE `'.$match[1].'`',$rawStart);
                if($start===false)throw new RuntimeException('DDL source position unavailable');
                $sections[]=['table'=>$match[1],'start_line'=>substr_count(substr($sql,0,$start),"\n")+1,'end_line'=>substr_count(substr($sql,0,$i+1),"\n")+1,'sha256'=>hash('sha256',$trim)];
            }
            $statement='';$rawStart=$i+1;
        }
    }
    if($quote!==null)throw new RuntimeException('Unterminated schema literal');
    return $out;
}
function analyticsFixtureSetup():array {
    mt_srand(20261026); // Repeatable scenario values; namespace/token/password remain run-specific.
    $state=['database'=>ANALYTICS_FIXTURE_PREFIX.bin2hex(random_bytes(8)),'token'=>bin2hex(random_bytes(32)),'password'=>bin2hex(random_bytes(16)),'seed'=>20261026];
    $name=analyticsFixtureName($state['database']);$server=analyticsFixtureServer();
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try{
        $server->exec('USE `'.$name.'`');$server->exec('CREATE TABLE analytics_fixture_owner (token CHAR(64) NOT NULL)');
        $server->prepare('INSERT INTO analytics_fixture_owner VALUES (?)')->execute([$state['token']]);
        $schema=file_get_contents(__DIR__.'/../../database/udm_radar.sql');
        $parsed=analyticsFixtureSchemaStatements($schema,$sections);$blocks=[];$used=[];$unused=[];
        // Exact dependencies of the allowlisted login/analytics/existing-workspace handlers.
        $required=['users','student_profiles','subjects','grades','predictions','faculty_class_loads','academic_support_cases','support_case_referrals','support_actions','support_status_history','feedback_reports','feedback_messages','feedback_status_history','pending_corrections','pending_grade_batches','admin_change_log'];
        foreach($sections as $index=>$section){if(in_array($section['table'],$required,true)){$blocks[]=$parsed[$index];$used[]=$section;}else{$unused[]=$section;}}
        if(count($used)!==count($required))throw new RuntimeException('Required accepted table definition unavailable');
        $state['schema_source']=['path'=>'database/udm_radar.sql','sha256'=>hash('sha256',$schema),'sections'=>$used,'excluded_sections'=>$unused,'data_imported'=>false];
        $state['provenance']=['marker_table'=>'analytics_fixture_owner','token_sha256'=>hash('sha256',$state['token']),'verified'=>true];
        if(!$blocks)throw new RuntimeException('Accepted schema unavailable');
        $server->exec('SET FOREIGN_KEY_CHECKS=0');foreach($blocks as $block)$server->exec($block);$server->exec('SET FOREIGN_KEY_CHECKS=1');
        $hash=password_hash($state['password'],PASSWORD_DEFAULT);$users=[];
        foreach([[1,'admin','analytics_admin'],[11,'faculty','analytics_faculty_a'],[12,'faculty','analytics_faculty_b'],[101,'student','analytics_student_a'],[102,'student','analytics_student_b'],[103,'student','analytics_student_c'],[104,'student','analytics_student_d'],[105,'student','analytics_no_records']] as [$id,$role,$identifier]){
            $users[]=['id'=>$id,'user_id'=>$identifier,'password_hash'=>$hash,'role'=>$role,'first_name'=>'Synthetic','last_name'=>$identifier,'is_active'=>1];
        }
        analyticsFixtureInsert($server,'users',$users);
        $profiles=[];foreach([[101,2,'AN-A'],[102,2,'AN-A'],[103,2,'AN-B'],[104,3,'AN-C'],[105,2,'AN-A']] as [$id,$year,$section])$profiles[]=['user_id'=>$id,'student_number'=>'SYN-'.$id,'year_level'=>$year,'section'=>$section,'record_status'=>'Active','college'=>'CCS'];
        analyticsFixtureInsert($server,'student_profiles',$profiles);
        analyticsFixtureInsert($server,'subjects',[
            ['id'=>101,'code'=>'AN201','title'=>'Synthetic Analysis','units'=>3,'year_level'=>2,'semester'=>1],
            ['id'=>102,'code'=>'AN202','title'=>'Synthetic Laboratory','units'=>1,'year_level'=>2,'semester'=>1],
            ['id'=>103,'code'=>'AN301','title'=>'Synthetic Methods','units'=>3,'year_level'=>3,'semester'=>1],
        ]);
        analyticsFixtureInsert($server,'faculty_class_loads',[
            ['faculty_user_id'=>11,'subject_id'=>101,'section'=>'AN-A'],['faculty_user_id'=>12,'subject_id'=>101,'section'=>'AN-B'],
            ['faculty_user_id'=>11,'subject_id'=>102,'section'=>'AN-A'],['faculty_user_id'=>12,'subject_id'=>102,'section'=>'AN-A'],
            ['faculty_user_id'=>12,'subject_id'=>103,'section'=>'AN-C'],
        ]);
        $grades=[];
        foreach([[1,101,101,'2024-2025',1,'2.00',0],[2,101,101,'2024-2025',2,'2.50',0],[3,101,101,'2025-2026',1,'3.00',0],
            [4,101,101,'2026-2027',1,'3.50',1],[5,101,102,'2026-2027',1,'4.00',1],[6,102,101,'2025-2026',1,'2.00',0],
            [7,102,101,'2026-2027',1,'INC',1],[8,102,102,'2026-2027',1,null,1],[9,103,101,'2025-2026',1,'2.50',0],
            [10,103,101,'2026-2027',1,'2.00',1],[11,104,103,'2026-2027',1,'0.00',1],[12,104,103,'2025-2026',2,'P',0]] as [$id,$student,$subject,$year,$sem,$grade,$current]){
            $grades[]=['id'=>$id,'student_id'=>$student,'subject_id'=>$subject,'school_year'=>$year,'semester'=>$sem,'final_grade'=>$grade,'is_current'=>$current,'prelim'=>$current?mt_rand(70,95):null];
        }
        analyticsFixtureInsert($server,'grades',$grades);
        analyticsFixtureInsert($server,'predictions',[
            ['id'=>1,'student_id'=>101,'predicted_gwa'=>3.50,'risk_level'=>'LOW','prediction_source'=>'decision_tree','data_completeness'=>'complete','generated_at'=>'2026-10-20 08:00:00'],
            ['id'=>2,'student_id'=>101,'predicted_gwa'=>3.25,'risk_level'=>'HIGH','prediction_source'=>'decision_tree','data_completeness'=>'complete','generated_at'=>'2026-10-20 08:00:00'],
            ['id'=>3,'student_id'=>102,'predicted_gwa'=>2.50,'risk_level'=>'LOW','prediction_source'=>'calculation_fallback','data_completeness'=>'historical_only','is_provisional'=>1,'generated_at'=>'2026-10-20 08:00:00'],
            ['id'=>4,'student_id'=>103,'predicted_gwa'=>2.25,'risk_level'=>'MODERATE','prediction_source'=>'heuristic','data_completeness'=>'legacy_unknown','generated_at'=>'2026-10-20 08:00:00'],
        ]);
        $cases=[];foreach([[1,101,'needs_review',null],[2,102,'closed','2026-10-22 08:00:00'],[3,103,'action_taken',null],[4,104,'needs_review',null]] as [$id,$student,$status,$closed])$cases[]=['id'=>$id,'student_id'=>$student,'trigger_risk_level'=>'HIGH','trigger_predicted_gwa'=>1.50,'prediction_source'=>'decision_tree','school_year'=>'2026-2027','semester'=>1,'status'=>$status,'created_at'=>'2026-10-20 08:00:00','closed_at'=>$closed];
        analyticsFixtureInsert($server,'academic_support_cases',$cases);
        analyticsFixtureInsert($server,'support_case_referrals',[
            ['id'=>10,'case_id'=>1,'faculty_id'=>11,'subject_id'=>101,'section'=>'AN-A','subject_risk_level'=>'HIGH','latest_term_checked'=>'prelim','latest_term_grade'=>70,'status'=>'needs_review','created_at'=>'2026-10-20 08:00:00'],
            ['id'=>11,'case_id'=>1,'faculty_id'=>11,'subject_id'=>102,'section'=>'AN-A','subject_risk_level'=>'HIGH','latest_term_checked'=>'prelim','latest_term_grade'=>70,'status'=>'acknowledged','notice_issued_at'=>'2026-10-20 11:00:00','student_acknowledged_at'=>'2026-10-20 13:00:00','created_at'=>'2026-10-20 08:00:00'],
            ['id'=>12,'case_id'=>2,'faculty_id'=>11,'subject_id'=>101,'section'=>'AN-A','subject_risk_level'=>'HIGH','latest_term_checked'=>'prelim','latest_term_grade'=>70,'status'=>'closed','created_at'=>'2026-10-20 08:00:00'],
            ['id'=>13,'case_id'=>3,'faculty_id'=>12,'subject_id'=>101,'section'=>'AN-B','subject_risk_level'=>'MODERATE','latest_term_checked'=>'prelim','latest_term_grade'=>80,'status'=>'action_taken','notice_issued_at'=>'2026-10-20 10:00:00','created_at'=>'2026-10-20 08:00:00'],
        ]);
        analyticsFixtureInsert($server,'support_actions',[
            ['case_id'=>1,'actor_id'=>11,'action_type'=>'consultation_requested','created_at'=>'2026-10-20 09:00:00'],
            ['case_id'=>2,'actor_id'=>11,'action_type'=>'advising_recommended','created_at'=>'2026-10-20 14:00:00'],
            ['case_id'=>3,'actor_id'=>12,'action_type'=>'academic_notice_sent','created_at'=>'2026-10-20 10:00:00'],
        ]);
        return $state;
    }catch(Throwable $e){$server->exec('DROP DATABASE `'.$name.'`');$q=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$name]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Setup-failure cleanup could not remove '.$name,0,$e);throw new RuntimeException('Disposable setup failed; cleanup asserted for '.$name,0,$e);}
}
function analyticsFixtureCleanup(array $state):array {
    $db=analyticsFixtureConnect($state);$name=analyticsFixtureName($state['database']);$db->exec('DROP DATABASE `'.$name.'`');
    $server=analyticsFixtureServer();$q=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$name]);
    if((int)$q->fetchColumn()!==0)throw new RuntimeException('Owned disposable database cleanup assertion failed: '.$name);
    $unchanged=isset($state['schema_source']['sha256'])&&hash_equals($state['schema_source']['sha256'],hash_file('sha256',__DIR__.'/../../database/udm_radar.sql'));
    return ['database'=>$name,'seed'=>$state['seed'],'removed'=>true,'cleanup_asserted'=>true,'provenance'=>$state['provenance'],'schema_source'=>$state['schema_source'],'schema_byte_identical'=>$unchanged,'genuine_or_dump_data_imported'=>false,'protected_databases_accessed'=>false];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $command=$argv[1]??'';
    if($command==='setup'){echo json_encode(analyticsFixtureSetup(),JSON_THROW_ON_ERROR);}
    elseif($command==='cleanup-stdin'){echo json_encode(analyticsFixtureCleanup(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);}
    elseif($command==='cleanup'&&isset($argv[2])){echo json_encode(analyticsFixtureCleanup(json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);}
    else{fwrite(STDERR,"Use setup or cleanup <owned-state-file>\n");exit(1);}
}
