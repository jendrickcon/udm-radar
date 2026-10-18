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
  foreach(['first','second','third','fourth','irregular','allinc','overflow','mixed','empty','regular1','regular2','regular3','regular4','regular_missing','legacy_cy','legacy_ds'] as $case){
   $a=$fixture['accounts'][$case];$c=pefCcReadContext($db,$a['id']);
   pefTestCheck($c['identity']['id']===$a['id']&&$c['identity']['student_number']===$a['login'],'Ownership uses internal users.id: '.$case);
   pefTestCheck(count($c['records'])===$a['count']+count($a['current_grade_ids']),'Whole owned history count including current binding: '.$case);
   pefTestCheck(!array_intersect(['predicted_gwa','current_gwa','risk_level'],array_keys($c['identity'])),'No cached/prediction source: '.$case);
   pefTestCheck(count(array_unique(array_column($c['records'],'id')))===count($c['records']),'No duplicated query rows: '.$case);
   $m=pefCcBuildViewModel($c,$a['inputs'],pefTestReference(),$config,$fixture['current_term'])->data;
   pefTestCheck($m['recorded_count']===$a['count'],'Model keeps complete selected term: '.$case);
   if(in_array($case,['first','second','third','fourth','irregular','allinc'],true))pefTestCheck($m['exportable'],'Reviewed Irregular manual workflow remains available: '.$case);
   elseif(isset($a['expected_exportable'])){
    pefTestCheck($m['exportable']===$a['expected_exportable'],'Coherent Regular context respects actual target availability: '.$case);
    if($a['expected_exportable'])pefTestCheck($m['planning']['subject_ids']===$a['inputs']['subject_ids']&&$m['proposed']!==[],'Regular nominal subjects resolve from borrowed verified catalogue: '.$case);
   }
   else pefTestCheck(!$m['exportable'],'Invalid/empty/over-capacity term blocked: '.$case);
   if($case==='allinc')pefTestCheck($m['gwa']===null,'All-INC actual stored rows produce unavailable GWA');
   if($case==='first')pefTestCheck($m['gwa']===3.0,'Stored cached 4.00 profile GWA ignored in favor of term 3.00');
   if(str_starts_with($case,'legacy_'))pefTestCheck($m['policy']['status']==='unsupported_legacy_track_requires_review','Historical track never converted to SD: '.$case);
   if($case==='regular_missing')pefTestCheck($m['policy']['status']==='evaluator_review_required'&&$m['proposed']===[],'No anchor means review rather than empty Regular export');
   pefTestCheck(!array_filter($c['catalog'],static fn(array $s):bool=>preg_match('/^(CY|DS)/i',$s['code'])===1),'Selectable catalogue excludes legacy codes: '.$case);
   foreach($c['sections'] as $section)pefTestCheck(array_keys($section)===['section','year_level','school_year','semester','source','evidence_sha256'],'Section evidence contains no student identity/grade fields');
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
