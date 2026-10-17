<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__,2).'/config/pef_cc.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_repository.php';
require_once dirname(__DIR__,2).'/includes/pef_cc_view_model.php';
$fixture=null;$db=null;
try {
 $db=pefTestScratch();$baseline=pefTestFingerprint($db);$fixture=pefTestCreateFixture($db);
 pefTestCheck($db->query('SELECT DATABASE()')->fetchColumn()==='udm_radar_scratch','Actual scratch target proven before fixture inserts');
 $before=pefTestFingerprint($db);$config=pefCcConfig();
 $db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
 try {
  foreach(['first','second','third','fourth','irregular','allinc','overflow','mixed','empty'] as $case){
   $a=$fixture['accounts'][$case];$c=pefCcReadContext($db,$a['id']);
   pefTestCheck($c['identity']['id']===$a['id']&&$c['identity']['student_number']===$a['login'],'Ownership uses internal users.id: '.$case);
   pefTestCheck(count($c['records'])===$a['count'],'Whole owned history count: '.$case);
   pefTestCheck(!array_intersect(['predicted_gwa','current_gwa','risk_level'],array_keys($c['identity'])),'No cached/prediction source: '.$case);
   pefTestCheck(count(array_unique(array_column($c['records'],'id')))===count($c['records']),'No duplicated query rows: '.$case);
   $m=pefCcBuildViewModel($c,pefTestInputs(),pefTestReference(),$config,['school_year'=>'2026-2027','semester'=>1])->data;
   pefTestCheck($m['recorded_count']===$a['count'],'Model keeps complete selected term: '.$case);
   if(in_array($case,['first','second','third','fourth','irregular','allinc'],true))pefTestCheck($m['exportable'],'No year-level or regular-pacing restriction: '.$case);
   else pefTestCheck(!$m['exportable'],'Invalid/empty/over-capacity term blocked: '.$case);
   if($case==='allinc')pefTestCheck($m['gwa']===null,'All-INC actual stored rows produce unavailable GWA');
   if($case==='first')pefTestCheck($m['gwa']===3.0,'Stored cached 4.00 profile GWA ignored in favor of term 3.00');
   $other=$fixture['accounts'][$case==='first'?'second':'first']['grade_ids'];
   pefTestCheck(!array_intersect(array_column($c['records'],'id'),$other),'Another Student records never enter context: '.$case);
  }
  pefTestThrows(fn()=>pefCcReadContext($db,0),'Invalid authenticated key rejected');
  pefTestThrows(fn()=>pefCcReadContext($db,$fixture['accounts']['faculty']['id']),'Wrong-role profile read rejected');
  pefTestThrows(fn()=>pefCcReadContext($db,$fixture['accounts']['inactive']['id']),'Inactive profile read rejected');
  $db->rollBack();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 pefTestCheck(pefTestFingerprint($db)===$before,'All application tables byte-equivalent after owned read/model paths');
 putenv('DB_NAME=udm_radar');pefTestThrows(fn()=>pefTestScratch(),'Fixture refuses live DB name before connecting');putenv('DB_NAME=udm_radar_scratch');
} catch(Throwable $e){pefTestCheck(false,'Scratch integration prerequisite: '.$e->getMessage());}
finally{
 if($db&&$fixture){try{pefTestCleanupFixture($db,$fixture);pefTestCheck(pefTestFingerprint($db)===$baseline,'Fixture cleanup preserves every pre-existing application row');}catch(Throwable $e){pefTestCheck(false,'Fixture cleanup/preservation failed: '.$e->getMessage());}}
}
pefTestFinish();
