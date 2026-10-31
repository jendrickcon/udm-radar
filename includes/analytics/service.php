<?php
declare(strict_types=1);
require_once __DIR__.'/repository.php';
require_once __DIR__.'/predictions.php';
require_once __DIR__.'/interventions.php';

function analyticsFilters(array $raw,array $user):array {
    $keys=['school_year','semester','year_level','subject','section','faculty','case_status','decision'];
    $student=($user['role']??'')==='student';$allowed=$student?['school_year','semester']:$keys;
    if(array_diff(array_keys($raw),$allowed))throw new InvalidArgumentException('Unsupported analytics filter for this role');
    $f=array_fill_keys($keys,'');
    foreach($raw as $k=>$value){
        if(!is_string($value)&&!is_int($value))throw new InvalidArgumentException('Use scalar filter values');
        $f[$k]=trim((string)$value);
        if(strlen($f[$k])>64)throw new InvalidArgumentException('Filter value is too long');
    }
    if($f['school_year']!==''&&(!preg_match('/^(\d{4})-(\d{4})$/D',$f['school_year'],$m)||(int)$m[2]!==((int)$m[1]+1)))throw new InvalidArgumentException('Use a recorded consecutive school year');
    foreach(['semester'=>['1','2','3'],'year_level'=>['1','2','3','4'],'case_status'=>['needs_review','action_taken','acknowledged','closed'],'decision'=>['awaiting_review','issued','reviewed_without_notice','deferred']] as $key=>$values){if($f[$key]!==''&&!in_array($f[$key],$values,true))throw new InvalidArgumentException('Invalid '.$key.' filter');}
    foreach(['subject','faculty'] as $key){if($f[$key]!==''&&(!preg_match('/^[1-9][0-9]{0,9}$/D',$f[$key])||(int)$f[$key]>2147483647))throw new InvalidArgumentException('Invalid record filter');}
    if($f['section']!==''&&!preg_match('/^[\pL\pN -]{1,50}$/uD',$f['section']))throw new InvalidArgumentException('Invalid section filter');
    if(($user['role']??'')==='faculty'){
        if($f['faculty']!==''&&(int)$f['faculty']!==(int)$user['id'])throw new DomainException('Another Faculty record is outside your scope');
        $f['faculty']=(string)(int)$user['id'];
    }
    return $f;
}

function analyticsFilterGrades(array $rows,array $f):array {
    return array_values(array_filter($rows,static function($r)use($f){
        foreach(['school_year'=>'school_year','semester'=>'semester','year_level'=>'curriculum_year','subject'=>'subject_id','section'=>'section'] as $filter=>$column){if($f[$filter]!==''&&(string)($r[$column]??'')!==$f[$filter])return false;}
        return $f['faculty']===''||in_array((int)$f['faculty'],$r['faculty_ids']??[],true);
    }));
}

function analyticsOptions(array $data,array $user):array {
    $values=array_fill_keys(['school_year','semester','year_level','subject','section','faculty','case_status','decision'],[]);
    foreach($data['grades'] as $r){
        foreach(['school_year'=>'school_year','semester'=>'semester','year_level'=>'curriculum_year','section'=>'section'] as $key=>$field){if(($r[$field]??'')!==' '&&($r[$field]??null)!==null)$values[$key][(string)$r[$field]]=(string)$r[$field];}
        $values['subject'][(string)$r['subject_id']]=$r['code'].' · '.$r['title'];
        foreach($r['faculty_ids'] as $id){if(isset($data['faculty'][$id]))$values['faculty'][(string)$id]=$data['faculty'][$id];}
    }
    // Authorized referral history can remain relevant after its grade/load disappears.
    if(($user['role']??'')!=='student'){
        foreach($data['cases'] as $case){foreach(['school_year','semester'] as $key){if(isset($case[$key])&&(string)$case[$key]!=='')$values[$key][(string)$case[$key]]=(string)$case[$key];}}
        foreach($data['referrals'] as $ref){
            if(!empty($ref['section']))$values['section'][(string)$ref['section']]=(string)$ref['section'];
            if(!empty($ref['curriculum_year']))$values['year_level'][(string)$ref['curriculum_year']]=(string)$ref['curriculum_year'];
            $values['subject'][(string)$ref['subject_id']]=$ref['code'].' · '.$ref['title'];
            if(isset($data['faculty'][(int)$ref['faculty_id']]))$values['faculty'][(string)$ref['faculty_id']]=$data['faculty'][(int)$ref['faculty_id']];
        }
    }
    if(($user['role']??'')==='faculty')$values['faculty']=[(string)$user['id']=>$data['faculty'][(int)$user['id']]??'Your assigned classes'];
    $values['case_status']=['needs_review'=>'Needs review','action_taken'=>'Action taken','acknowledged'=>'Acknowledged','closed'=>'Closed'];
    $values['decision']=['awaiting_review'=>'Awaiting review','issued'=>'Notice issued','reviewed_without_notice'=>'Reviewed without recorded notice','deferred'=>'Deferred decision (unavailable)'];
    if(($user['role']??'')==='student'){foreach(['year_level','subject','section','faculty','case_status','decision'] as $key)$values[$key]=[];}
    $result=[];foreach($values as $key=>$items){ksort($items,SORT_NATURAL);$result[$key]=[];foreach($items as $value=>$label){if((string)$value!=='')$result[$key][]=['value'=>(string)$value,'label'=>$label];}}
    return $result;
}

function analyticsBuildReport(array $data,array $f,array $user):array {
    $role=$user['role'];$rows=analyticsFilterGrades($data['grades'],$f);$summary=analyticsAggregate($rows);$trend=analyticsTrend($rows);
    // Previous recorded comparable term can lie outside the selected school year.
    if($trend['current']!==null){
        $comparisonFilters=$f;$comparisonFilters['school_year']='';
        $candidates=analyticsGroup(analyticsFilterGrades($data['grades'],$comparisonFilters),'semester');$previous=analyticsPreviousComparable($candidates,$trend['current']);
        $trend['previous']=$previous;$trend['change']=analyticsChange($previous['gwa']??null,$trend['current']['gwa'],!$trend['current']['provisional']&&$previous!==null);
    }
    $dimensions=$role==='admin'?['semester','school_year','year_level','subject','section','faculty_subject']:($role==='faculty'?['subject','section']:[]);
    $groups=[];foreach($dimensions as $dimension)$groups[$dimension]=analyticsGroup($rows,$dimension);
    $studentIds=array_values(array_unique(array_map('intval',array_column($rows,'student_id'))));
    $coverage=['student_count'=>count($studentIds),'available_count'=>0,'model_count'=>0,'calculation_count'=>0,'provisional_count'=>0,'coverage_pct'=>null];
    foreach($studentIds as $id){$p=analyticsPrediction($data['predictions'][$id]??[]);if($p['estimated_gwa']!==null){$coverage['available_count']++;if($p['source_family']==='model')$coverage['model_count']++;if($p['source_family']==='calculation')$coverage['calculation_count']++;if($p['is_provisional'])$coverage['provisional_count']++;}}
    if($studentIds)$coverage['coverage_pct']=round($coverage['available_count']/count($studentIds)*100,2);
    $support=null;$drilldowns=[];$visibleCases=[];$visibleRefs=[];
    if($role!=='student'){
        $refsByCase=[];
        foreach($data['referrals'] as $r){
            if($f['subject']!==''&&(string)$r['subject_id']!==$f['subject'])continue;
            if($f['section']!==''&&$r['section']!==$f['section'])continue;
            if($f['faculty']!==''&&(string)$r['faculty_id']!==$f['faculty'])continue;
            if($f['year_level']!==''&&(string)$r['curriculum_year']!==$f['year_level'])continue;
            $issued=analyticsNoticeHours($r)!==null;$reviewed=in_array($r['status'],['action_taken','acknowledged','closed'],true)||$issued;
            if(($f['decision']==='issued'&&!$issued)||($f['decision']==='awaiting_review'&&$reviewed)||($f['decision']==='reviewed_without_notice'&&(!$reviewed||$issued))||$f['decision']==='deferred')continue;
            $refsByCase[(int)$r['case_id']][]=$r;
        }
        $needsRef=$role==='faculty'||$f['subject']!==''||$f['section']!==''||$f['faculty']!==''||$f['year_level']!==''||$f['decision']!=='';
        foreach($data['cases'] as $c){
            if(($f['school_year']!==''&&$c['school_year']!==$f['school_year'])||($f['semester']!==''&&(string)$c['semester']!==$f['semester'])||($f['case_status']!==''&&$c['status']!==$f['case_status']))continue;
            $refs=$refsByCase[(int)$c['id']]??[];if($needsRef&&!$refs)continue;
            $visibleCases[]=$c;$visibleRefs=array_merge($visibleRefs,$refs);
            if($role==='admin')$drilldowns[]=['record_id'=>(int)$c['id'],'label'=>'Case '.(int)$c['id'],'status'=>$c['status'],'href'=>'../admin/activity.php?tab=support&analytics_case='.(int)$c['id']];
            else foreach($refs as $r)$drilldowns[]=['record_id'=>(int)$r['id'],'label'=>'Referral '.(int)$r['id'].' · '.$r['code'],'status'=>$r['status'],'href'=>'../faculty/feedback.php?tab=support&analytics_referral='.(int)$r['id']];
        }
        $support=analyticsSupportSummary($visibleCases,$visibleRefs,$data['actions']);
    }
    $review=[];
    foreach($rows as $r){$canon=canonicalizeFinalGrade($r['final_grade']??null);if($canon!==null&&is_numeric($canon)&&computeRiskFromAvg((float)$canon)!=='LOW')$review[(int)$r['student_id']]=true;}
    $comparableSubjects=analyticsComparableSubjects($data,$f,$user);
    return ['role'=>$role,'filters'=>$f,'options'=>analyticsOptions($data,$user),'summary'=>$summary,'trend'=>$trend,'groups'=>$groups,
        'prediction'=>$role==='student'?analyticsPrediction($data['predictions'][(int)$user['id']]??[]):null,'prediction_coverage'=>$coverage,
        'support'=>$support,'drilldowns'=>$drilldowns,'students_requiring_review'=>count($review),
        'faculty_comparison'=>['change'=>analyticsChange(null,null),'reason'=>'Prior class change unavailable: current teaching loads do not contain dated historical assignments.'],
        'comparable_subjects'=>$comparableSubjects,
        'disclosure'=>'Prototype performance on synthetic academic records',
        'fairness'=>'Comparative academic patterns support authorized review and do not independently measure teaching performance. Differences may reflect Student composition, prior achievement, subject difficulty, class size, record completeness, and semester conditions.',
        'warnings'=>[
            'Coverage describes recorded subject slots, not unrecorded enrollments. GWA uses eligible numeric points and credit units; legacy zero and textual outcomes retain the canonical exclusions.',
            'Historical sections and Faculty assignments are unavailable without dated records. Section and Faculty academic filters therefore cover verified current assignments only. Year level means subject curriculum year.',
            'Predictions are the latest stored snapshots, separate from the selected historical period; their timestamp does not prove subsequent grades were incorporated.',
            'Subject, section, Faculty, curriculum-year and decision filters restrict support summaries to matching recorded referrals. Cases without that referral evidence are not attributed.',
            'Counts and before/after patterns do not prove that an intervention caused a grade change.',
        ]];
}

function analyticsComparableSubjects(array $data,array $f,array $user):array {
    if(($user['role']??'')!=='admin')return [];
    $currentTerm=$data['current_term']??(function_exists('getCurrentTerm')?getCurrentTerm():['school_year'=>'2026-2027','semester'=>1]);
    $targetSY=$f['school_year']!==''?$f['school_year']:$currentTerm['school_year'];
    $targetSem=$f['semester']!==''?$f['semester']:(string)$currentTerm['semester'];
    if($targetSY!==$currentTerm['school_year']||(int)$targetSem!==(int)$currentTerm['semester'])return [];

    $grades=array_filter($data['grades']??[],static function($g)use($currentTerm){
        $isCur=((int)($g['is_current']??0)===1||empty($g['is_current']));
        return $isCur&&
               ($g['school_year']??'')===$currentTerm['school_year']&&
               (int)($g['semester']??0)===(int)$currentTerm['semester']&&
               !empty($g['faculty_id']);
    });

    $bySubj=[];
    foreach($grades as $g){
        $sid=(string)$g['subject_id'];$fid=(int)$g['faculty_id'];$sec=(string)($g['section']??'');
        if(!isset($bySubj[$sid])){
            $bySubj[$sid]=[
                'subject_id'=>$sid,
                'code'=>$g['code']??'Subject',
                'title'=>$g['title']??'',
                'school_year'=>$currentTerm['school_year'],
                'semester'=>(string)$currentTerm['semester'],
                'faculty'=>[]
            ];
        }
        if(!isset($bySubj[$sid]['faculty'][$fid])){
            $bySubj[$sid]['faculty'][$fid]=[
                'id'=>$fid,
                'name'=>$g['faculty_name']??'Faculty',
                'sections'=>[],
                'students'=>[],
                'numeric_grades'=>0,
                'total_grades'=>0
            ];
        }
        if($sec!=='')$bySubj[$sid]['faculty'][$fid]['sections'][$sec]=true;
        $bySubj[$sid]['faculty'][$fid]['students'][(string)$g['student_id']]=true;
        $bySubj[$sid]['faculty'][$fid]['total_grades']++;
        $grade=$g['final_grade']??null;
        if(!isExcludedFromGwa($grade)&&isNumericFinalGrade($grade)){
            $bySubj[$sid]['faculty'][$fid]['numeric_grades']++;
        }
    }

    $out=[];
    foreach($bySubj as $sid=>$info){
        $facCount=count($info['faculty']);
        if($facCount<2)continue;

        $allSections=[];
        $metricCounts=['student_count'=>0,'gwa'=>0,'numeric_coverage_pct'=>0,'final_coverage_pct'=>0,'prediction_coverage_pct'=>0,'risk_distribution'=>0];
        foreach($info['faculty'] as $fData){
            foreach(array_keys($fData['sections']) as $s)$allSections[$s]=true;
            if(count($fData['students'])>0){
                $metricCounts['student_count']++;
                $metricCounts['numeric_coverage_pct']++;
                $metricCounts['final_coverage_pct']++;
            }
            if($fData['numeric_grades']>0)$metricCounts['gwa']++;
        }

        $secCount=count($allSections);
        $secList=array_keys($allSections);
        sort($secList,SORT_NATURAL);

        $out[]=[
            'subject_id'=>$sid,
            'code'=>$info['code'],
            'title'=>$info['title'],
            'school_year'=>$info['school_year'],
            'semester'=>$info['semester'],
            'distinct_faculty_count'=>$facCount,
            'eligible_section_count'=>$secCount,
            'sections'=>$secList,
            'eligible_group_count_by_metric'=>$metricCounts,
            'has_group_c'=>($facCount>=3),
            'label'=>"{$info['code']} · {$info['title']} ({$facCount} comparable Faculty · {$secCount} sections)",
            'compact_label'=>"{$info['code']} · {$facCount} Faculty · {$secCount} sections",
            'value'=>$sid
        ];
    }
    usort($out,static fn($a,$b)=>strcmp($a['code'],$b['code']));
    return $out;
}

/** Optional term is trusted application context; never populated from GET/POST. */
function analyticsReport(PDO $db,array $user,array $raw,?array $currentTerm=null):array {
    $f=analyticsFilters($raw,$user);$owned=!$db->inTransaction();
    if($owned){if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();}
    try{$data=analyticsReadData($db,$user,$currentTerm);$result=analyticsBuildReport($data,$f,$user);if($owned)$db->commit();return $result;}
    catch(Throwable $e){if($owned&&$db->inTransaction())$db->rollBack();throw $e;}
}
