<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../includes/analytics/service.php';
$raw=json_decode($argv[1]??'{}',true,512,JSON_THROW_ON_ERROR);
$scenario=$argv[2]??'complete';
$rows=[];$id=0;
foreach ([['2025-2026',2],['2026-2027',1]] as [$year,$semester]) {
    foreach ([11=>'2.50',12=>'3.00',13=>'3.50'] as $faculty=>$grade) {
        foreach ([1,2] as $student) {
            $rows[]=['id'=>++$id,'student_id'=>$faculty*10+$student,'subject_id'=>1,'school_year'=>$year,'semester'=>$semester,
                'final_grade'=>$scenario==='unavailable'?'INC':($scenario==='zero'?'0.00':$grade),'units'=>3,'curriculum_year'=>3,
                'code'=>'SYN101','title'=>'Synthetic programming','faculty_id'=>$faculty,'faculty_ids'=>[$faculty],
                'faculty_name'=>'Synthetic Faculty '.$faculty,'section'=>'SYN-'.$faculty];
        }
    }
    $rows[]=array_replace($rows[count($rows)-1],['id'=>++$id,'student_id'=>111,'subject_id'=>2,'code'=>'SYN202','title'=>'Synthetic systems',
        'faculty_id'=>11,'faculty_ids'=>[11],'faculty_name'=>'Synthetic Faculty 11','final_grade'=>$scenario==='unavailable'?'INC':($scenario==='zero'?'0.00':'2.00')]);
}
if($scenario==='empty')$rows=[];
$actor=['id'=>1,'role'=>'admin'];
echo json_encode(analyticsBuildReport(['grades'=>$rows,'predictions'=>[],'faculty'=>[11=>'Synthetic Faculty 11',12=>'Synthetic Faculty 12',13=>'Synthetic Faculty 13'],
    'cases'=>[],'referrals'=>[],'actions'=>[]],analyticsFilters($raw,$actor),$actor),JSON_THROW_ON_ERROR);
