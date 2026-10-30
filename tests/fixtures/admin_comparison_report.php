<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../includes/analytics/service.php';
$raw=json_decode($argv[1]??'{}',true,512,JSON_THROW_ON_ERROR);
$scenario=$argv[2]??'complete';
$rows=[];$id=0;
if ($scenario === 'ite331') {
    $sections2 = ['IT-31', 'IT-32', 'IT-33'];
    for ($i = 1; $i <= 115; $i++) {
        $sec = $sections2[($i - 1) % 3];
        $rows[] = [
            'id' => ++$id, 'student_id' => 1000 + $i, 'subject_id' => 39,
            'school_year' => '2026-2027', 'semester' => 1, 'final_grade' => null,
            'units' => 3, 'curriculum_year' => 3, 'code' => 'ITE331',
            'title' => 'System Analysis and Design', 'faculty_id' => 2,
            'faculty_ids' => [2], 'faculty_name' => 'Ronald Fernandez',
            'section' => $sec, 'is_current' => 1
        ];
    }
    $sections302 = ['IT-37', 'IT-38'];
    for ($i = 1; $i <= 68; $i++) {
        $sec = $sections302[($i - 1) % 2];
        $rows[] = [
            'id' => ++$id, 'student_id' => 2000 + $i, 'subject_id' => 39,
            'school_year' => '2026-2027', 'semester' => 1, 'final_grade' => null,
            'units' => 3, 'curriculum_year' => 3, 'code' => 'ITE331',
            'title' => 'System Analysis and Design', 'faculty_id' => 302,
            'faculty_ids' => [302], 'faculty_name' => 'John Rey Consulta',
            'section' => $sec, 'is_current' => 1
        ];
    }
} else {
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
}
if($scenario==='empty')$rows=[];
$actor=['id'=>1,'role'=>'admin'];
echo json_encode(analyticsBuildReport(['grades'=>$rows,'predictions'=>[],'faculty'=>[11=>'Synthetic Faculty 11',12=>'Synthetic Faculty 12',13=>'Synthetic Faculty 13',2=>'Ronald Fernandez',302=>'John Rey Consulta'],
    'cases'=>[],'referrals'=>[],'actions'=>[],'current_term'=>['school_year'=>'2026-2027','semester'=>1]],analyticsFilters($raw,$actor),$actor),JSON_THROW_ON_ERROR);
