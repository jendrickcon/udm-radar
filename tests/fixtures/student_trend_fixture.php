<?php
declare(strict_types=1);
require_once __DIR__.'/analytics_mvp_fixture.php';
function studentTrendFixtureSetup(): array {
    $state=analyticsFixtureSetup();
    try {
        $db=analyticsFixtureConnect($state);
        $blocks=analyticsFixtureSchemaStatements(file_get_contents(__DIR__.'/../../database/udm_radar.sql'),$sections);
        foreach($sections as $i=>$section) if(in_array($section['table'],['otp_tokens','export_audit_logs'],true)) $db->exec($blocks[$i]);
        $state['scenarios']=['trend_no_grades'=>201,'trend_prelim_only'=>202,'trend_dense_history'=>203,'trend_history_only'=>204,'trend_projection_only'=>205,'trend_risk_mismatch'=>206,'trend_planning'=>207,'trend_summer'=>208,'trend_undated'=>209,'trend_lone_summer'=>210,'trend_manual_plan'=>211];
        $hash=password_hash($state['password'],PASSWORD_DEFAULT);
        foreach($state['scenarios'] as $name=>$id) {
            analyticsFixtureInsert($db,'users',[['id'=>$id,'user_id'=>$name,'password_hash'=>$hash,'role'=>'student','first_name'=>'Synthetic','last_name'=>$name,'is_active'=>1]]);
            analyticsFixtureInsert($db,'student_profiles',[['user_id'=>$id,'student_number'=>$name,'course'=>'BSIT - Software Development','college'=>'CCS','year_level'=>3,'section'=>'AN-A','record_status'=>'Active']]);
        }
        $grades=[];$id=1000;
        $add=function(int $student,string $year,int $semester,mixed $final,int $current=0,mixed $prelim=null,mixed $midterm=null)use(&$grades,&$id){$grades[]=['id'=>++$id,'student_id'=>$student,'subject_id'=>101,'school_year'=>$year,'semester'=>$semester,'final_grade'=>$final,'is_current'=>$current,'prelim'=>$prelim,'midterm'=>$midterm];};
        $add(201,'2026-2027',1,null,1);$add(202,'2026-2027',1,null,1,89);
        for($year=2018;$year<=2025;$year++) foreach([1,2] as $semester) $add(203,$year.'-'.($year+1),$semester,($year===2022&&$semester===1)?null:($semester===1?'2.25':'2.50'));
        $add(203,'2026-2027',1,null,1,89,90);
        $add(204,'2025-2026',2,'2.25');
        $add(206,'2025-2026',2,'2.25');$add(206,'2026-2027',1,null,1,88,89);
        $add(208,'2023-2024',3,'2.00');$add(208,'2024-2025',2,'4.00');$add(208,'2024-2025',3,'2.50');
        $add(209,'2024-2025',2,'2.00');$add(209,'Unknown',1,'4.00');
        $add(210,'2024-2025',2,'2.00');$add(210,'2024-2025',3,'2.50');
        analyticsFixtureInsert($db,'grades',$grades);
        foreach([203,205,206] as $student) analyticsFixtureInsert($db,'predictions',[['student_id'=>$student,'predicted_gwa'=>2.15,'risk_level'=>$student===206?'LOW':'MODERATE','prediction_source'=>'calculation_fallback','data_completeness'=>'prelim_only','is_provisional'=>1,'generated_at'=>'2026-10-01 08:00:00']]);
        // Planning GET validates the public curriculum vocabulary, not a private workbook.
        $curriculum=require __DIR__.'/../../config/bsit_software_development_curriculum_2022_2023.php';
        $known=array_fill_keys($db->query('SELECT code FROM subjects')->fetchAll(PDO::FETCH_COLUMN),true);
        $catalog=[];$subjectId=10000;
        foreach($curriculum['terms'] as $term)foreach($term['subjects'] as $subject){
            if(isset($known[$subject['code']]))continue;
            $known[$subject['code']]=true;
            $catalog[]=['id'=>++$subjectId,'code'=>$subject['code'],'title'=>$subject['title'],'units'=>$subject['units'],'year_level'=>$subject['year_level'],'semester'=>$subject['semester']];
        }
        analyticsFixtureInsert($db,'subjects',$catalog);
        $state['public_curriculum_subjects_added']=count($catalog);
        $db->exec("UPDATE student_profiles SET section='IT-31' WHERE user_id=207");
        $db->exec("UPDATE student_profiles SET status='Irregular',section='IT-IRREG' WHERE user_id=211");
        $publicIds=$db->query('SELECT code,id FROM subjects')->fetchAll(PDO::FETCH_KEY_PAIR);
        $planningGrades=[];$planningGradeId=20000;
        foreach($curriculum['terms'] as $term) {
            if((int)$term['year_level']!==3 || (int)$term['semester']!==1)continue;
            foreach($term['subjects'] as $subject)$planningGrades[]=['id'=>++$planningGradeId,'student_id'=>207,'subject_id'=>(int)$publicIds[$subject['code']],'school_year'=>'2026-2027','semester'=>1,'is_current'=>1,'prelim'=>89,'midterm'=>null,'prefinal'=>null,'final_grade'=>null];
        }
        analyticsFixtureInsert($db,'grades',$planningGrades);
        $manualGrades=[];
        foreach($planningGrades as $row){$row['id']+=1000;$row['student_id']=211;$manualGrades[]=$row;}
        analyticsFixtureInsert($db,'grades',$manualGrades);
        $state['all_table_count']=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
        return $state;
    }catch(Throwable $error){analyticsFixtureCleanup($state);throw $error;}
}
if(PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')==='setup') echo json_encode(studentTrendFixtureSetup(),JSON_THROW_ON_ERROR);
    elseif(($argv[1]??'')==='cleanup-stdin') echo json_encode(analyticsFixtureCleanup(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);
    elseif(($argv[1]??'')==='fingerprint-stdin') {
        $state=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$db=analyticsFixtureConnect($state);$contents=[];
        foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
            if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Unexpected table name');
            $contents[$table]=$db->query('SELECT * FROM `'.$table.'` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
        }
        ksort($contents);echo hash('sha256',json_encode($contents,JSON_THROW_ON_ERROR));
    }
    else exit(1);
}
