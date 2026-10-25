<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/constants.php';

/** Recorded grade slots, never a cross-product or an inferred enrollment population. */
function analyticsAggregate(array $rows):array {
    $seen=[];$students=[];$contributors=[];$eligible=[];$recorded=0;$resolved=0;$recognized=0;$pending=0;$invalidUnits=0;
    foreach($rows as $index=>$r){
        $key=(string)($r['id']??'row-'.$index);
        if(isset($seen[$key]))continue;
        $seen[$key]=true;
        $sid=(string)($r['student_id']??'row-'.$index);$students[$sid]=true;
        $grade=$r['final_grade']??null;$canon=canonicalizeFinalGrade($grade);
        if($canon!==null){$recorded++;if($canon!=='INC')$resolved++;}
        if($canon===null||$canon==='INC')$pending++;
        if(isPassingFinalGrade($grade)||isFailingFinalGrade($grade))$recognized++;
        if(!isExcludedFromGwa($grade)&&isNumericFinalGrade($grade)){
            $units=is_numeric($r['units']??null)?(int)$r['units']:0;
            if($units>0){$eligible[]=['grade'=>$grade,'units'=>$units];$contributors[$sid]=true;}else{$invalidUnits++;}
        }
    }
    $expected=count($seen);$unitCount=array_sum(array_column($eligible,'units'));
    return ['gwa'=>computeWeightedGWA($eligible),'student_count'=>count($students),'contributing_student_count'=>count($contributors),
        'expected_grade_count'=>$expected,'eligible_grade_count'=>count($eligible),'eligible_units'=>$unitCount,
        'recorded_outcome_count'=>$recorded,'resolved_final_count'=>$resolved,'recognized_outcome_count'=>$recognized,
        'numeric_coverage_pct'=>$expected?round(count($eligible)/$expected*100,2):null,
        'final_coverage_pct'=>$expected?round($resolved/$expected*100,2):null,
        'provisional'=>$expected>0&&($pending>0||$invalidUnits>0),'pending_grade_count'=>$pending,'invalid_unit_count'=>$invalidUnits];
}

function analyticsChange(?float $previous,?float $current,bool $comparable=true):array {
    $empty=['point_change'=>null,'relative_change_pct'=>null,'direction'=>'Unavailable'];
    if(!$comparable||$previous===null||$current===null||!is_finite($previous)||!is_finite($current)||$previous<=0)return $empty;
    $delta=round($current-$previous,2);
    return ['point_change'=>$delta,'relative_change_pct'=>round(($current-$previous)/$previous*100,2),
        'direction'=>$delta>0?'Improving':($delta<0?'Declining':'Stable')];
}

function analyticsGroup(array $rows,string $dimension):array {
    $buckets=[];$labels=[];
    foreach($rows as $r){
        [$key,$label]=match($dimension){
            'semester'=>[(string)($r['school_year']??'Unknown').'|'.($r['semester']??''),($r['school_year']??'Unknown').' · Semester '.($r['semester']??'Unknown')],
            'school_year'=>[(string)($r['school_year']??'Unknown'),(string)($r['school_year']??'Unknown')],
            'year_level'=>[(string)($r['curriculum_year']??'Unknown'),'Curriculum year '.($r['curriculum_year']??'Unknown')],
            'subject'=>[(string)$r['subject_id'],($r['code']??'Subject').' · '.($r['title']??'')],
            'section'=>[(string)($r['section']??''),$r['section']?:'Historical section unavailable'],
            'faculty_subject'=>[($r['faculty_id']??'unknown').'|'.$r['subject_id'],($r['faculty_name']??'Assignment unavailable').' · '.($r['code']??'Subject')],
            default=>throw new InvalidArgumentException('Unknown analytics grouping'),
        };
        $buckets[$key][]=$r;$labels[$key]=$label;
    }
    ksort($buckets,SORT_NATURAL);$out=[];
    foreach($buckets as $key=>$group){$out[]=['key'=>(string)$key,'label'=>$labels[$key]]+analyticsAggregate($group);}
    return $out;
}

/** Latest selected term versus the most recent earlier complete recorded term. */
function analyticsTrend(array $rows):array {
    $series=analyticsGroup($rows,'semester');$current=$series?end($series):null;$previous=null;
    if($current!==null){for($i=count($series)-2;$i>=0;$i--){if(!$series[$i]['provisional']&&$series[$i]['gwa']!==null){$previous=$series[$i];break;}}}
    return ['series'=>$series,'current'=>$current,'previous'=>$previous,
        'change'=>analyticsChange($previous['gwa']??null,$current['gwa']??null,$current!==null&&!$current['provisional']&&$previous!==null)];
}
