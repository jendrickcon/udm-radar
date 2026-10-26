<?php
declare(strict_types=1);

function analyticsMedian(array $values):?float {
    $values=array_values(array_filter($values,static fn($v)=>is_numeric($v)&&is_finite((float)$v)&&$v>=0));
    if(!$values)return null;
    sort($values,SORT_NUMERIC);$n=count($values);$mid=intdiv($n,2);
    return round($n%2?(float)$values[$mid]:($values[$mid-1]+$values[$mid])/2,2);
}

function analyticsHours(?string $start,?string $end):?float {
    if(!$start||!$end)return null;
    $times=[];
    foreach([$start,$end] as $value){
        if(!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?$/D',$value))return null;
        try{$dt=new DateTimeImmutable($value,new DateTimeZone('Asia/Manila'));$errors=DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count']))return null;$times[]=$dt->getTimestamp();}catch(Throwable){return null;}
    }
    return $times[1]>=$times[0]?($times[1]-$times[0])/3600:null;
}

function analyticsNoticeHours(array $referral):?float {
    return analyticsHours($referral['created_at']??null,$referral['notice_issued_at']??null);
}

function analyticsSupportSummary(array $cases,array $referrals,array $actions):array {
    $caseMap=[];foreach($cases as $c){$caseMap[(int)$c['id']]=$c;}
    $refs=[];foreach($referrals as $r){if(isset($caseMap[(int)$r['case_id']]))$refs[(int)$r['id']]=$r;}
    $issued=0;$reviewed=0;$ack=0;$closed=0;$first=[];$closure=[];
    foreach($refs as $r){
        $hours=analyticsNoticeHours($r);$validNotice=$hours!==null;
        if($validNotice){$issued++;$first[]=$hours;}
        if(in_array($r['status']??'', ['action_taken','acknowledged','closed'],true)||$validNotice)$reviewed++;
        $receipt=$r['student_acknowledged_at']??null;
        if(analyticsHours($receipt,$receipt)!==null&&analyticsHours($r['created_at']??$receipt,$receipt)!==null&&(!$validNotice||analyticsHours($r['notice_issued_at'],$receipt)!==null))$ack++;
    }
    foreach($caseMap as $id=>$c){
        $isClosed=($c['status']??'')==='closed';if($isClosed)$closed++;
        $duration=$isClosed?analyticsHours($c['created_at']??null,$c['closed_at']??null):null;
        if($duration!==null)$closure[]=$duration;
    }
    return ['eligible_cases'=>count($caseMap),'referrals_created'=>count($refs),'referrals_reviewed'=>$reviewed,
        'notices_issued'=>$issued,'notices_deferred'=>null,'student_acknowledgments'=>$ack,
        'open_cases'=>count($caseMap)-$closed,'closed_cases'=>$closed,
        'mean_first_action_hours'=>$first?round(array_sum($first)/count($first),2):null,'first_action_sample'=>count($first),
        'median_closure_hours'=>analyticsMedian($closure),'closure_sample'=>count($closure),
        'deferral_reason'=>'Unavailable: existing records do not contain an explicit notice-deferred decision. Closure without a notice is not assumed to mean deferral.'];
}
