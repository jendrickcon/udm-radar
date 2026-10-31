<?php
declare(strict_types=1);
require_once __DIR__.'/student_trend_fixture.php';

function facultyAssessmentFixtureSetup(): array {
    $state=studentTrendFixtureSetup();
    try {
        $db=analyticsFixtureConnect($state); $hash=password_hash($state['password'],PASSWORD_DEFAULT);
        analyticsFixtureInsert($db,'users',[
            ['id'=>31,'user_id'=>'assessment_faculty','password_hash'=>$hash,'role'=>'faculty','first_name'=>'Synthetic','last_name'=>'Assessment Faculty','is_active'=>1],
            ['id'=>32,'user_id'=>'assessment_other_faculty','password_hash'=>$hash,'role'=>'faculty','first_name'=>'Synthetic','last_name'=>'Other Faculty','is_active'=>1],
            ['id'=>34,'user_id'=>'assessment_interim_only','password_hash'=>$hash,'role'=>'faculty','first_name'=>'Synthetic','last_name'=>'Interim Faculty','is_active'=>1],
            ['id'=>35,'user_id'=>'assessment_no_loads','password_hash'=>$hash,'role'=>'faculty','first_name'=>'Synthetic','last_name'=>'Unassigned Faculty','is_active'=>1],
        ]);
        $classes=[[501,'IT-32','Machine Learning (Elective 1)'],[502,'IT-35','Networking 1'],[503,'IT-36','Networking 1'],[504,'IT-37','System Analysis and Design'],[505,'IT-38','System Analysis and Design'],[506,'FOREIGN','Foreign assigned subject'],[507,'INTERIM','Synthetic interim only']];
        $state['faculty_scenarios']=[];
        foreach($classes as $index=>[$subject,$section,$title]) {
            analyticsFixtureInsert($db,'subjects',[['id'=>$subject,'code'=>'FASYN'.$subject,'title'=>$title,'units'=>3,'year_level'=>3,'semester'=>1]]);
            analyticsFixtureInsert($db,'faculty_class_loads',[['faculty_user_id'=>$subject===506?32:($subject===507?34:31),'subject_id'=>$subject,'section'=>$section]]);
            $state['faculty_scenarios'][$section]=$subject.'|'.$section;
            for($i=0;$i<4;$i++) {
                $id=5000+$index*10+$i;
                analyticsFixtureInsert($db,'users',[['id'=>$id,'user_id'=>'fa_syn_'.$id,'password_hash'=>$hash,'role'=>'student','first_name'=>'Synthetic','last_name'=>$subject===506?'FOREIGN_PRIVATE_TEST':'Assessment '.$id,'is_active'=>1]]);
                analyticsFixtureInsert($db,'student_profiles',[['user_id'=>$id,'student_number'=>'FA-SYN-'.$id,'section'=>$section,'year_level'=>3,'course'=>'BSIT - Software Development','record_status'=>'Active']]);
                $p=[80,85,90,0][$i];$m=$p+5;$pf=null;$f=null;
                if($subject===502){$p=[80,40,70,null][$i];$m=[90,null,80,100][$i];}
                if($subject===503){$p=$m=null;}
                if($subject===504){$f=['3.50','0.00','INC','P'][$i];}
                if($subject===505){$p=75;$m=80;$pf=85;}
                if($subject===506){$p=$m=99;}
                if($subject===507){$p=80;$m=85;}
                analyticsFixtureInsert($db,'grades',[['id'=>50000+$index*10+$i,'student_id'=>$id,'subject_id'=>$subject,'school_year'=>'2026-2027','semester'=>1,'is_current'=>1,'prelim'=>$p,'midterm'=>$m,'prefinal'=>$pf,'final_grade'=>$f]]);
            }
        }
        // Past grades alone never establish this Faculty's historical assignment.
        analyticsFixtureInsert($db,'grades',[['id'=>51000,'student_id'=>5000,'subject_id'=>501,'school_year'=>'2025-2026','semester'=>1,'is_current'=>0,'prelim'=>99,'midterm'=>99,'final_grade'=>'4.00']]);
        return $state;
    } catch(Throwable $e) { analyticsFixtureCleanup($state); throw $e; }
}

function facultyAssessmentFixtureFingerprint(array $state): string {
    $db=analyticsFixtureConnect($state);$contents=[];
    foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if(!preg_match('/^[a-z_]+$/D',$table))throw new RuntimeException('Unexpected fixture table');
        $contents[$table]=$db->query('SELECT * FROM `'.$table.'` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }
    ksort($contents);return hash('sha256',json_encode($contents,JSON_THROW_ON_ERROR));
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    if(($argv[1]??'')==='setup')echo json_encode(facultyAssessmentFixtureSetup(),JSON_THROW_ON_ERROR);
    elseif(($argv[1]??'')==='cleanup-stdin')echo json_encode(analyticsFixtureCleanup(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);
    elseif(($argv[1]??'')==='fingerprint-stdin')echo facultyAssessmentFixtureFingerprint(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR));
    else exit(1);
}
