<?php
// Read-only scratch verification: does not invoke auth, HTTP handlers or predictions.
putenv('DB_NAME=udm_radar_scratch');
require_once dirname(__DIR__,2).'/config/db.php';
require_once dirname(__DIR__,2).'/includes/grade_coverage.php';
$db=getDB();if($db->query('SELECT DATABASE()')->fetchColumn()!=='udm_radar_scratch'){fwrite(STDERR,"Scratch target required\n");exit(1);}
$checks=$failures=0;
function readCheck(bool $pass,string $label):void{global $checks,$failures;$checks++;if(!$pass)$failures++;echo ($pass?'[PASS] ':'[FAIL] ').$label.PHP_EOL;}
$tables=['grades','student_profiles','predictions','academic_support_cases','support_case_referrals','support_actions','support_status_history'];
$before=[];foreach($tables as $t)$before[$t]=$db->query('CHECKSUM TABLE '.$t)->fetch(PDO::FETCH_ASSOC)['Checksum'];
$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
try{
 $records=loadGradeCoverage($db,getCurrentTerm());
 $expected=(int)$db->query("SELECT COUNT(*) FROM student_profiles sp JOIN users u ON u.id=sp.user_id AND u.role='student' WHERE sp.record_status='Active'")->fetchColumn();
 readCheck(count($records)===$expected,'Population matches active student profile scope');
 foreach($records as $r){readCheck($r['expected']>=0&&max($r['counts'])<=$r['expected'],'Recorded denominator bounds');}
 readCheck(count(array_unique(array_column($records,'id')))===count($records),'One record per student; deterministic latest prediction');
 $db->rollBack();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,"Read-only coverage verification failed\n");exit(1);}
foreach($tables as $t)readCheck($db->query('CHECKSUM TABLE '.$t)->fetch(PDO::FETCH_ASSOC)['Checksum']===$before[$t],"$t checksum unchanged");
echo "SUMMARY: Ran $checks tests, $failures failures.\n";exit($failures?1:0);
