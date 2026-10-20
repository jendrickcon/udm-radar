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
/** Review opt-in is CLI-only and never changes the application's configured default. */
function pefTestConfig(array $overrides = []): array {
    $cand = getenv('PEF_CC_REVIEW_CANDIDATE');
    return ($cand === 'v0.2-r3' || $cand === 'v0.2-r4' || $cand === 'v0.2-r5' || $cand === 'v0.2-r6')
        ? pefCcReviewCandidateConfig($overrides, $cand) : pefCcConfig($overrides);
}
function pefTestInputs(array $replace = []): array {
    return array_replace(['evaluation_year'=>'2025-2026','evaluation_semester'=>2,
        'target_year'=>'2026-2027','target_semester'=>1,'section'=>'IT-32','subject_ids'=>[]], $replace);
}
function pefTestContext(int $count = 3, array $grades = ['3.00','2.50','2.75'], string $schoolYear = '2025-2026', int $semester = 2): array {
    $catalog = $records = [];
    for ($i=1;$i<=max(15,$count);$i++) {
        $catalog[]=['id'=>$i,'code'=>'SYN'.str_pad((string)$i,3,'0',STR_PAD_LEFT),
            'title'=>'Synthetic subject '.$i,'units'=>$i===2 ? 1 : 3];
        if ($i<=$count) $records[]=['id'=>$i,'subject_id'=>$i,'school_year'=>$schoolYear,'semester'=>$semester,
            'is_current'=>0,'code'=>$catalog[$i-1]['code'],'title'=>$catalog[$i-1]['title'],
            'units'=>$catalog[$i-1]['units'],'grade'=>$grades[($i-1)%count($grades)]];
    }
    return ['identity'=>['id'=>900001,'student_number'=>'SYN-PEF-001','name'=>'EXAMPLE, SYNTHETIC',
        'college'=>'CCS','program'=>'BSIT - Software Development','is_active'=>1,'role'=>'student',
        'status'=>'Irregular','record_status'=>'Active','year_level'=>3,'section'=>'IT-IRREG'],
        'records'=>$records,'catalog'=>$catalog,
        'sections'=>array_map(static fn(string $section):array=>['section'=>$section,'year_level'=>3,'school_year'=>'2026-2027','semester'=>1,'source'=>'active_sd_current_records','evidence_sha256'=>hash('sha256',$section)],['IT-31','IT-32','IT-33'])];
}
/** Synthetic own current rows, with real public curriculum codes but no real identities. */
function pefTestRegularContext(int $year=2, int $currentSemester=1, bool $completePredecessor=true): array {
    $c=pefTestContext(3, ['3.00', '3.00', '3.00'], '2025-2026', 2);
    $c['identity']=array_replace($c['identity'],['status'=>'Regular','year_level'=>$year,'section'=>'IT-'.$year.'2']);
    $mapping=require dirname(__DIR__,2).'/config/bsit_software_development_curriculum_2022_2023.php';
    $c['catalog']=[];$id=1000;
    foreach($mapping['terms'] as $term)foreach($term['subjects'] as $s)$c['catalog'][]=[
        'id'=>++$id,'code'=>$s['code'],'title'=>$s['title'],'units'=>$s['units'],'year_level'=>$s['year_level'],'semester'=>$s['semester']];
    $current=array_values(array_filter($c['catalog'],static fn(array $s):bool=>$s['year_level']===$year&&$s['semester']===$currentSemester));
    foreach(array_slice($current,0,3) as $i=>$s)$c['records'][]=[
        'id'=>900100+$i,'subject_id'=>$s['id'],'school_year'=>'2026-2027','semester'=>$currentSemester,
        'is_current'=>1,'code'=>$s['code'],'title'=>$s['title'],'units'=>$s['units'],'grade'=>$completePredecessor ? '3.00' : ($i===1?'INC':null)];
    $targetYear=$currentSemester===2?$year+1:$year;
    $targetSchoolYear=$currentSemester===2?'2027-2028':'2026-2027';
    $targetSemester=$currentSemester===2?1:2;
    $c['sections']=[];
    foreach([1,2,3] as $block)$c['sections'][]=['section'=>'IT-'.$targetYear.$block,'year_level'=>$targetYear,'school_year'=>$targetSchoolYear,'semester'=>$targetSemester,'source'=>'active_sd_current_records','evidence_sha256'=>hash('sha256',$targetSchoolYear.'/'.$targetSemester.'/IT-'.$targetYear.$block)];
    return $c;
}
function pefTestRegularInputs(array $c, int $currentSemester=1): array {
    $targetYear=(int)$c['identity']['year_level']+($currentSemester===2?1:0);
    $targetSemester=$currentSemester===2?1:2;
    $targetSchoolYear=$currentSemester===2?'2027-2028':'2026-2027';
    $evalSchoolYear='2026-2027';
    $evalSemester=$currentSemester;
    $subjects=array_values(array_filter($c['catalog'],static fn(array $s):bool=>$s['year_level']===$targetYear&&$s['semester']===$targetSemester));
    return pefTestInputs(['evaluation_year'=>$evalSchoolYear,'evaluation_semester'=>$evalSemester,
        'target_year'=>$targetSchoolYear,'target_semester'=>$targetSemester,
        'section'=>'IT-'.$targetYear.'2','subject_ids'=>array_column($subjects,'id')]);
}
/** Multi-term regular student context spanning Year 1 to Year 3 with verified GS and WS terms. */
function pefTestMultiTermRegularContext(bool $currentComplete = false): array {
    $c = pefTestRegularContext(3, 1, $currentComplete);
    $y1s1 = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['year_level'] === 1 && $s['semester'] === 1));
    $y1s2 = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['year_level'] === 1 && $s['semester'] === 2));
    $y2s1 = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['year_level'] === 2 && $s['semester'] === 1));

    foreach (array_slice($y1s1, 0, 3) as $i => $s) {
        $c['records'][] = [
            'id' => 900200 + $i, 'subject_id' => $s['id'], 'school_year' => '2024-2025', 'semester' => 1,
            'is_current' => 0, 'code' => $s['code'], 'title' => $s['title'], 'units' => $s['units'], 'grade' => $i === 1 ? '2.75' : '3.00',
        ];
    }
    foreach (array_slice($y1s2, 0, 3) as $i => $s) {
        $c['records'][] = [
            'id' => 900300 + $i, 'subject_id' => $s['id'], 'school_year' => '2024-2025', 'semester' => 2,
            'is_current' => 0, 'code' => $s['code'], 'title' => $s['title'], 'units' => $s['units'], 'grade' => $i === 1 ? '1.25' : '1.50',
        ];
    }
    foreach (array_slice($y2s1, 0, 3) as $i => $s) {
        $c['records'][] = [
            'id' => 900400 + $i, 'subject_id' => $s['id'], 'school_year' => '2025-2026', 'semester' => 1,
            'is_current' => 0, 'code' => $s['code'], 'title' => $s['title'], 'units' => $s['units'], 'grade' => '3.00',
        ];
    }
    return $c;
}
/** Irregular student context with mixed curriculum year-levels in a completed term. */
function pefTestAmbiguousIrregularContext(): array {
    $c = pefTestRegularContext(3, 1, false);
    $c['identity']['status'] = 'Irregular';
    $c['identity']['section'] = 'IT-IRREG';
    $c['records'] = [];
    $y1 = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['year_level'] === 1));
    $y2 = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['year_level'] === 2));
    $c['records'][] = [
        'id' => 900501, 'subject_id' => $y1[0]['id'], 'school_year' => '2025-2026', 'semester' => 2,
        'is_current' => 0, 'code' => $y1[0]['code'], 'title' => $y1[0]['title'], 'units' => $y1[0]['units'], 'grade' => '3.00',
    ];
    $c['records'][] = [
        'id' => 900502, 'subject_id' => $y2[0]['id'], 'school_year' => '2025-2026', 'semester' => 2,
        'is_current' => 0, 'code' => $y2[0]['code'], 'title' => $y2[0]['title'], 'units' => $y2[0]['units'], 'grade' => '2.50',
    ];
    $c['records'][] = [
        'id' => 900503, 'subject_id' => $y2[1]['id'], 'school_year' => '2026-2027', 'semester' => 1,
        'is_current' => 1, 'code' => $y2[1]['code'], 'title' => $y2[1]['title'], 'units' => $y2[1]['units'], 'grade' => null,
    ];
    return $c;
}
function pefTestReference(): array {
    $rows=[];
    foreach(pefTestContext(6)['catalog'] as $i=>$s) $rows[]=$s+['prereq'=>$i===0?'ITE252':'',
        'grade_cell'=>'E'.($i+7),'remark_cell'=>'F'.($i+7),'status_cell'=>'G'.($i+7)];
    return ['version'=>'2022-2023','rows'=>$rows];
}
/** Explicit A/B/C/D scalar fixtures. No account, session or database writes. */
function pefTestPlanningState(string $state): array {
    $current = ['school_year'=>'2026-2027', 'semester'=>1];
    $context = pefTestRegularContext(3, 1, $state !== 'B');
    $inputs = pefTestRegularInputs($context);
    if (in_array($state, ['C', 'C-pending'], true)) {
        $context['records'] = array_slice($context['records'], 0, 3);
        if ($state === 'C-pending') $context['records'][0]['grade'] = null;
        $inputs = pefCcInitialPlanningInputs($context, pefTestInputs(), $current);
    } elseif ($state === 'D') {
        $inputs['section'] = 'UNVERIFIED';
    } elseif (!in_array($state, ['A', 'B'], true)) {
        throw new InvalidArgumentException('Unknown synthetic planning state');
    }
    return [$context, $inputs, $current];
}
/** Catalogue-derived print data; identities/grades remain entirely synthetic. */
function pefTestOperationalContext(array $subjects, ?string $name = null): array {
    $c=pefTestContext(0);$c['catalog']=[];$c['records']=[];
    $c['identity']['name']=$name??'DELA CRUZ-SANTOS, ALEXANDRA ISABELLA SYNTHETIC MARIA REYES';
    foreach($subjects as $i=>$subject){
        $s=['id'=>1001+$i,'code'=>$subject['code'],'title'=>$subject['title'],'units'=>$subject['units'],
            'year_level'=>$subject['year_level'],'semester'=>$subject['semester']];
        $c['catalog'][]=$s;
        $c['records'][]=['id'=>2001+$i,'subject_id'=>$s['id'],'school_year'=>'2025-2026','semester'=>2,
            'is_current'=>0,'code'=>$s['code'],'title'=>$s['title'],'units'=>$s['units'],'grade'=>'3.25'];
    }
    return $c;
}

/** Full ownership token is in the title; compact codes fit the operational contract. */
function pefTestSubjectIdentity(array $manifest, int $index): array {
    if($index<0||$index>=15||!preg_match('/^[0-9a-f]{10}$/',$manifest['token']??''))throw new RuntimeException('Invalid subject fixture identity');
    if(($manifest['subject_format']??null)==='compact-print-v1')return [
        'code'=>'P'.strtoupper(substr($manifest['token'],0,3)).str_pad((string)($index+1),2,'0',STR_PAD_LEFT),
        'title'=>'Synthetic '.$manifest['token'].' subject '.($index+1)];
    if(isset($manifest['subject_format']))throw new RuntimeException('Unknown subject fixture format');
    // Preserve safe cleanup of older private manifests; never reinterpret them.
    return ['code'=>'PC'.$manifest['token'].str_pad((string)($index+1),2,'0',STR_PAD_LEFT),'title'=>'Synthetic planning subject '.($index+1)];
}
/** Explicit private synthetic print samples; no database connection or original roster access. */
function pefTestPrintSamples(string $directory): array {
    $real=realpath($directory);
    if(!$real||preg_match('~[\\\\/]htdocs(?:[\\\\/]|$)~i',$real)||glob($real.'/*'))throw new RuntimeException('Fresh private print directory outside web roots required');
    require_once dirname(__DIR__,2).'/includes/pef_cc_export_service.php';
    $config=pefTestConfig();$reference=pefCcTemplateReference($config);$cases=[];
    $mapping=pefCcCurriculum();$all=array_merge(...array_column($mapping['mapping']['terms'],'subjects'));
    $normal=pefTestOperationalContext(array_slice($all,0,6),'EXAMPLE, SYNTHETIC');
    $regularCase=static function(int $year,string $section)use($all):array{
        $c=pefTestRegularContext($year);$c['identity']['section']=$section;$c['identity']['name']='EXAMPLE, SYNTHETIC';
        $c['records']=array_values(array_filter($c['records'],static fn(array $r):bool=>$r['is_current']===1));
        foreach(array_slice($c['catalog'],0,6) as $i=>$s)$c['records'][]=['id'=>7000+$i,'subject_id'=>$s['id'],'school_year'=>'2024-2025','semester'=>2,
            'is_current'=>0,'code'=>$s['code'],'title'=>$s['title'],'units'=>$s['units'],'grade'=>'3.25'];
        $input=pefTestRegularInputs($c);$input['section']=$section;return [$c,$input];
    };
    $cases['normal']=$regularCase(3,'IT-32');
    foreach(['provisional'=>'B','review'=>'C','review-pending'=>'C-pending'] as $name=>$state){
        [$stateContext,$stateInputs]=pefTestPlanningState($state);$cases[$name]=[$stateContext,$stateInputs];
    }
    $cases['longest-section']=$regularCase(4,'IT-43');
    $cases['irregular-marker']=[$normal,pefTestInputs(['subject_ids'=>[1001,1002],'section'=>'IT-IRREG'])];
    $blank=pefTestOperationalContext(array_slice($all,0,3),'EXAMPLE, SYNTHETIC');
    foreach($blank['records'] as &$r)$r['grade']=null;unset($r);
    $cases['blank']=[$blank,pefTestInputs(['subject_ids'=>[],'section'=>''])];
    $longest=$all;usort($longest,static fn(array $a,array $b):int=>mb_strlen($b['title'])<=>mb_strlen($a['title']));
    $capacity=pefTestOperationalContext(array_slice($longest,0,13));$capacity['identity']['student_number']='SYN-ACCEPTANCE-00001';
    $cases['capacity']=[$capacity,pefTestInputs(['subject_ids'=>range(1001,1012),'section'=>'IT-32'])];
    $titleCase=pefTestOperationalContext([$longest[0]],'EXAMPLE, SYNTHETIC');
    $cases['longest-title']=[$titleCase,pefTestInputs(['subject_ids'=>[1001],'section'=>'IT-32'])];
    foreach(array_chunk($all,12) as $index=>$subjects){
        $c=pefTestOperationalContext($subjects);$cases['catalogue-'.($index+1)]=[$c,pefTestInputs(['subject_ids'=>array_column($c['catalog'],'id'),'section'=>'IT-32'])];
    }
    $result=['matrix'=>'pef-cc-four-state-r6','template_version'=>$config['template_version'],'template_sha256'=>$config['template_sha256'],'curriculum_sha256'=>$mapping['sha256'],'cases'=>[],
        'metrics'=>['active_subjects'=>count($all),'code_max'=>max(array_map(static fn(array $s):int=>mb_strlen($s['code']),$all)),
            'title_max'=>max(array_map(static fn(array $s):int=>mb_strlen($s['title']),$all)),
            'normal_name'=>$capacity['identity']['name'],'normal_name_characters'=>mb_strlen($capacity['identity']['name']),
            'student_number_characters'=>mb_strlen($capacity['identity']['student_number']),'verified_section'=>'IT-32','verified_section_characters'=>5,
            'recorded_rows'=>13,'proposed_rows'=>12], 'defensive_rejections'=>[]];
    foreach($cases as $name=>[$context,$inputs]){
        $model=pefCcBuildViewModel($context,$inputs,$reference,$config,['school_year'=>'2026-2027','semester'=>1]);
        $out=pefCcGenerateWorkbook($model,$config);$path=$real.'/'.$name.'.xlsx';
        try{if(!copy($out['path'],$path))throw new RuntimeException('Print sample copy failed');}
        finally{pefCcRemoveOutput($out['path'],$config);}
        $expectedCells=['PEF'=>['C37'=>$model->data['gwa'],'C38'=>null,
            'B68'=>$config['signatories']['program_head_name'],'B70'=>$config['signatories']['dean_name']],
            'CC'=>['B69'=>null,'J69'=>$config['signatories']['dean_name'],'B70'=>'Evaluator','J70'=>$config['signatories']['dean_title']]];
        foreach([1=>42,2=>43,3=>44,4=>45] as $year=>$row)foreach(['sem1'=>'C','sem2'=>'D','sem3'=>'E','summer'=>'F'] as $key=>$column){
            $code=null;foreach($model->data['historical_standing'] as $standing)if((int)$standing['year_level']===$year && in_array($standing[$key]??null,['GS','WS'],true))$code=$standing[$key];
            $expectedCells['PEF'][$column.$row]=$code;
        }
        foreach($reference['rows'] as $i=>$ref){$annotation=$model->data['cc']['rows'][$i];
            $expectedCells['CC'][$ref['grade_cell']]=$annotation['grade_display']??$annotation['grade']??null;
            $expectedCells['CC'][$ref['remark_cell']]=$annotation['remarks']??null;
            $expectedCells['CC'][$ref['status_cell']]=$annotation['status']??null;
        }
        $result['cases'][$name]=['role'=>'operational','path'=>$path,'sha256'=>hash_file('sha256',$path),'section'=>$inputs['section'],'planning_mode'=>$model->data['policy']['mode'],
            'expected_cells'=>$expectedCells,'mode'=>$model->data['mode'],'gwa'=>$model->data['gwa'],'evaluation_complete'=>$model->data['evaluation_complete'],
            'recorded_count'=>count($model->data['recorded']),'proposed_count'=>count($model->data['proposed']),
            'recorded_codes'=>array_column($model->data['recorded'],'code'),'proposed_codes'=>array_column($model->data['proposed'],'code')];
    }
    foreach(['name147','code20','title150','name61','number21','code7','title47','section96','wide96','unicode96','regular-marker'] as $case){
        $c=$normal;
        $inputs=pefTestInputs(['subject_ids'=>[1001],'section'=>'IT-32']);$sectionCase=in_array($case,['section96','wide96','unicode96','regular-marker'],true);
        if(in_array($case,['section96','wide96','unicode96'],true))$inputs['section']=$case==='unicode96'?str_repeat('界',96):($case==='wide96'?str_repeat('W',96):str_repeat('SYNTHETIC ',9).'ABCDEF');
        if($case==='regular-marker'){[$c,$inputs]=$cases['normal'];$inputs['section']='IT-IRREG';}
        if(str_starts_with($case,'name'))$c['identity']['name']=str_repeat('W',(int)substr($case,4));
        if($case==='number21')$c['identity']['student_number']=str_repeat('S',21);
        if(str_starts_with($case,'code')){$c['records'][0]['code']=str_repeat('W',(int)substr($case,4));$c['catalog'][0]['code']=$c['records'][0]['code'];}
        if(str_starts_with($case,'title')){$c['records'][0]['title']=str_repeat('W',(int)substr($case,5));$c['catalog'][0]['title']=$c['records'][0]['title'];}
        $before=hash('sha256',serialize($c));$m=pefCcBuildViewModel($c,$inputs,$reference,$config,['school_year'=>'2026-2027','semester'=>1]);
        if($m->data['exportable']||($sectionCase?!isset($m->data['field_errors']['section']):!in_array(PEF_CC_PRINT_LIMIT_MESSAGE,$m->data['errors'],true)))throw new RuntimeException('Defensive print case was not visibly rejected: '.$case);
        $files=glob($config['temp_path'].'/pefcc-*')?:[];$rejected=false;
        try{pefCcGenerateWorkbook($m,$config);}catch(RuntimeException){$rejected=true;}
        if(!$rejected||(glob($config['temp_path'].'/pefcc-*')?:[])!==$files)throw new RuntimeException('Defensive print case produced an output');
        $result['defensive_rejections'][$case]=['preview_rejected'=>true,'export_rejected'=>true,'output_created'=>false,
            'field_errors'=>$m->data['field_errors'],'safe_subject_inputs_retained'=>$m->data['planning']['subject_ids']===$inputs['subject_ids'],
            'source_context_unchanged'=>hash('sha256',serialize($c))===$before,'database_connection_used'=>false];
    }
    file_put_contents($real.'/samples.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    return $result;
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
    $gradeType=$db->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='grades' AND COLUMN_NAME='final_grade'")->fetchColumn();
    if(!in_array($gradeType,['varchar','char','text'],true))throw new RuntimeException('SCHEMA PREREQUISITE: scratch grades.final_grade must support textual outcomes; found '.(string)$gradeType.'. No fixtures were inserted; no migration is authorized.');

    pefTestAssertScratch($db);
    $type=$db->query("SHOW COLUMNS FROM grades LIKE 'final_grade'")->fetch();
    if (!str_starts_with(strtolower($type['Type']??''),'varchar')) throw new RuntimeException('Scratch final-grade migration prerequisite unavailable; no automatic rebuild');
    $token=bin2hex(random_bytes(5));$password=bin2hex(random_bytes(18));
    $manifest=['token'=>$token,'password'=>$password,'accounts'=>[],'subjects'=>[],'evaluation_year'=>'2024-2025','evaluation_semester'=>2,
        'target_year'=>'2026-2027','target_semester'=>1,'database'=>'udm_radar_scratch'];
    // Borrow verified public nominal IDs; they never enter the owned cleanup list.
    $mapping=require dirname(__DIR__,2).'/config/bsit_software_development_curriculum_2022_2023.php';
    $catalog=$db->query('SELECT id,code,title,units,year_level,semester FROM subjects ORDER BY id')->fetchAll();
    for($attempt=0;;$attempt++){
        $manifest['subject_format']='compact-print-v1';
        $codes=array_map(static fn(int $i):string=>pefTestSubjectIdentity($manifest,$i)['code'],range(0,14));
        if(!array_intersect($codes,array_column($catalog,'code')))break;
        if($attempt>=10)throw new RuntimeException('Synthetic short-code namespace unavailable');
        $token=bin2hex(random_bytes(5));$manifest['token']=$token;
    }
    $nominal=[];
    foreach($mapping['terms'] as $term)foreach($term['subjects'] as $subject){
        $matches=array_values(array_filter($catalog,static fn(array $s):bool=>$s['code']===$subject['code']));
        if(count($matches)!==1||(int)$matches[0]['units']!==$subject['units']||(int)$matches[0]['year_level']!==$subject['year_level']
            ||(int)$matches[0]['semester']!==$subject['semester']||$matches[0]['title']!==$subject['title'])throw new RuntimeException('Verified SD catalogue prerequisite unavailable; no catalogue repair');
        $nominal[$subject['year_level'].'/'.$subject['semester']][]=(int)$matches[0]['id'];
    }
    $currentTerm=getCurrentTerm();
    $manifest['current_term']=$currentTerm;$manifest['nominal_subject_ids']=$nominal;
    $manifest['evaluation_year']=$currentTerm['semester']===1
        ?(((int)substr($currentTerm['school_year'],0,4)-1).'-'.((int)substr($currentTerm['school_year'],5,4)-1)):$currentTerm['school_year'];
    $manifest['evaluation_semester']=$currentTerm['semester']===1?2:1;
    $manifest['target_year']=$currentTerm['school_year'];$manifest['target_semester']=$currentTerm['semester'];
    $lock='pefcc-fixture-'.$token;
    $s=$db->prepare('SELECT GET_LOCK(?, 0)');$s->execute([$lock]);
    if ((int)$s->fetchColumn()!==1) throw new RuntimeException('Fixture lock unavailable');
    $db->beginTransaction();
    try {
        $subject=$db->prepare('INSERT INTO subjects(code,title,units,year_level,semester) VALUES(?,?,?,?,?)');
        for($i=1;$i<=15;$i++) { $identity=pefTestSubjectIdentity($manifest,$i-1);$subject->execute([$identity['code'],$identity['title'],$i===2?1:3,(($i-1)%4)+1,($i%2)+1]);$manifest['subjects'][]=(int)$db->lastInsertId(); }
        $cases=['first'=>[1,'Irregular',3],'second'=>[2,'Irregular',3],'third'=>[3,'Irregular',13],'fourth'=>[4,'Irregular',3],
            'irregular'=>[2,'Irregular',3],'allinc'=>[1,'Irregular',3],'empty'=>[1,'Irregular',0],
            'overflow'=>[4,'Irregular',14],'mixed'=>[3,'Irregular',3],'faculty'=>[null,null,0],'inactive'=>[1,'Irregular',3],
            'regular1'=>[1,'Regular',3],'regular2'=>[2,'Regular',3],'regular3'=>[3,'Regular',3],'regular4'=>[4,'Regular',3],
            'regular_missing'=>[2,'Regular',3],'legacy_cy'=>[3,'Regular',3],'legacy_ds'=>[3,'Irregular',3]];
        $user=$db->prepare('INSERT INTO users(id,user_id,password_hash,role,first_name,last_name,is_active) VALUES(?,?,?,?,?,?,?)');
        $profile=$db->prepare('INSERT INTO student_profiles(id,user_id,student_number,course,college,year_level,section,status,record_status,current_gwa) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $grade=$db->prepare('INSERT INTO grades(student_id,subject_id,school_year,semester,final_grade,is_current) VALUES(?,?,?,?,?,?)');
        $accountIndex=0;
        foreach($cases as $key=>[$year,$status,$count]) {
            $id=pefTestNewId($db,'users');$login='PC'.$token.str_pad((string)++$accountIndex,2,'0',STR_PAD_LEFT);$role=$key==='faculty'?'faculty':'student';
            $user->execute([$id,$login,password_hash($password,PASSWORD_DEFAULT),$role,'Synthetic','PEFCC_'.$token.'_'.$key,$key==='inactive'?0:1]);
            $isRegular=in_array($key,['regular1','regular2','regular3','regular4'],true);
            $section=$isRegular||$key==='regular_missing'?'IT-'.$year.'2':'IT-IRREG';
            $course=$key==='legacy_cy'?'BSIT - Cyber Security':($key==='legacy_ds'?'BSIT - Data Science':'BSIT - Software Development');
            $manifest['accounts'][$key]=['id'=>$id,'login'=>$login,'year_level'=>$year,'status'=>$status,'count'=>$count,'role'=>$role,'grade_ids'=>[],'current_grade_ids'=>[],
                'section'=>$section,'course'=>$course,'inputs'=>pefTestInputs(['evaluation_year'=>$manifest['evaluation_year'],'evaluation_semester'=>$manifest['evaluation_semester'],'target_year'=>$manifest['target_year'],'target_semester'=>$manifest['target_semester'],'section'=>$section])];
            if($role==='student') $profile->execute([pefTestNewId($db,'student_profiles'),$id,$login,$course,'CCS',$year,$section,$status,'Active',4.00]);
            for($i=0;$i<$count;$i++) {
                $g=$key==='allinc'?'INC':(in_array($key,['first','regular_missing','legacy_cy','legacy_ds'],true)?'3.00':(['3.00','INC','0.00',null,'1.00','4.00'][$i%6]));
                $grade->execute([$id,$manifest['subjects'][$i],$manifest['evaluation_year'],$manifest['evaluation_semester'],$g,$key==='mixed'&&$i===1?1:0]);
                $manifest['accounts'][$key]['grade_ids'][]=(int)$db->lastInsertId();
            }
            if($key==='regular_missing'){
                $manifest['accounts'][$key]['inputs']['section']='';
                $manifest['accounts'][$key]['inputs']['subject_ids']=[];
                $manifest['accounts'][$key]['expected_mode']='review';
                $manifest['accounts'][$key]['expected_exportable']=true;
                $manifest['accounts'][$key]['expected_gwa_display']=$key==='regular3'?'Pending':'3.00';
            }
            if($isRegular){
                foreach(array_slice($nominal[$year.'/'.$currentTerm['semester']],0,3) as $subjectId){
                    $grade->execute([$id,$subjectId,$currentTerm['school_year'],$currentTerm['semester'],$key==='regular3'?null:'3.00',1]);
                    $manifest['accounts'][$key]['current_grade_ids'][]=(int)$db->lastInsertId();
                }
                $targetNominal=$year+($currentTerm['semester']===2?1:0);
                $targetSemester=$currentTerm['semester']===1?2:1;
                $targetYear=$currentTerm['semester']===1?$currentTerm['school_year']:
                    (((int)substr($currentTerm['school_year'],0,4)+1).'-'.((int)substr($currentTerm['school_year'],5,4)+1));
                $manifest['accounts'][$key]['inputs']=pefTestInputs(['evaluation_year'=>$currentTerm['school_year'],'evaluation_semester'=>$currentTerm['semester'],'target_year'=>$targetYear,'target_semester'=>$targetSemester,'section'=>$currentTerm['semester']===1?$section:'',
                    'subject_ids'=>$nominal[$targetNominal.'/'.$targetSemester]??[]]);
                $manifest['accounts'][$key]['expected_exportable']=true;
                $manifest['accounts'][$key]['expected_gwa_display']=$key==='regular3'?'Pending':'3.00';
                $manifest['accounts'][$key]['expected_mode']=$currentTerm['semester']===1?($key==='regular3'?'provisional':'normal'):'review';
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
    foreach($m['subjects'] as $index=>$id){$s=$db->prepare('SELECT code,title FROM subjects WHERE id=?');$s->execute([$id]);$r=$s->fetch();$expected=pefTestSubjectIdentity($m,$index);
        if($r!==false&&($r['code']!==$expected['code']||$r['title']!==$expected['title']))throw new RuntimeException('Fixture subject ownership mismatch');}
}
function pefTestCleanupFixture(PDO $db,array $m): void {
    pefTestValidateManifest($db,$m);$db->beginTransaction();
    try {
        foreach($m['accounts'] as $a) { $s=$db->prepare('DELETE FROM grades WHERE student_id=?');$s->execute([$a['id']]);$s=$db->prepare('DELETE FROM student_profiles WHERE user_id=?');$s->execute([$a['id']]);$s=$db->prepare('DELETE FROM users WHERE id=? AND user_id=?');$s->execute([$a['id'],$a['login']]); }
        foreach($m['subjects'] as $index=>$id){$expected=pefTestSubjectIdentity($m,$index);$s=$db->prepare('DELETE FROM subjects WHERE id=? AND code=? AND title=?');$s->execute([$id,$expected['code'],$expected['title']]);}
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
