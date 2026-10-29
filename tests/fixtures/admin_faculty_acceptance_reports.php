<?php
declare(strict_types=1);
// Pure, identifier-free report fixtures. No config/db.php, PDO, bootstrap or writes.
require_once __DIR__ . '/../../includes/analytics/service.php';

function acceptanceReport(string $role, string $state): array {
    if (!in_array($role, ['admin', 'faculty'], true) || !in_array($state, ['complete', 'partial', 'provisional', 'unavailable', 'empty', 'single', 'gap', 'small-one', 'small-two'], true)) {
        throw new InvalidArgumentException('Unknown acceptance fixture');
    }
    $make = static fn(int $id, int $student, int $subject, string $year, int $semester, ?string $grade, ?int $faculty, ?string $section) => [
        'id'=>$id, 'student_id'=>$student, 'subject_id'=>$subject, 'school_year'=>$year, 'semester'=>$semester,
        'final_grade'=>$grade, 'units'=>3, 'curriculum_year'=>3, 'code'=>'SYN'.$subject, 'title'=>'Synthetic subject '.$subject,
        'faculty_id'=>$faculty, 'faculty_ids'=>$faculty === null ? [] : [$faculty], 'faculty_name'=>$faculty === null ? 'Assignment unavailable' : 'Synthetic Faculty '.$faculty, 'section'=>$section,
    ];
    $rows = [
        $make(1, 101, 1, '2025-2026', 2, '2.00', null, null),
        $make(2, 102, 1, '2025-2026', 2, '3.00', null, null),
        $make(3, 101, 1, '2026-2027', 1, '2.50', 11, 'SYN-A'),
        $make(4, 102, 1, '2026-2027', 1, '3.50', 12, 'SYN-B'),
        $make(5, 101, 2, '2026-2027', 1, '3.00', 11, 'SYN-A'),
    ];
    if ($state === 'partial') $rows[4]['final_grade'] = null;
    if ($state === 'provisional') $rows[4]['final_grade'] = 'INC';
    if ($state === 'unavailable') foreach ($rows as &$row) $row['final_grade'] = 'P';
    unset($row);
    if ($state === 'single') $rows = array_slice($rows, 2);
    if ($state === 'gap') $rows[] = $make(6, 101, 1, '2025-2026', 3, null, null, null);
    if ($role === 'faculty') $rows = array_values(array_filter($rows, static fn($r) => in_array(11, $r['faculty_ids'], true)));
    $cases = [
        ['id'=>1,'student_id'=>101,'school_year'=>'2026-2027','semester'=>1,'status'=>'needs_review','created_at'=>'2026-09-01 00:00:00','closed_at'=>null],
        ['id'=>2,'student_id'=>101,'school_year'=>'2026-2027','semester'=>1,'status'=>'action_taken','created_at'=>'2026-09-01 00:00:00','closed_at'=>null],
        ['id'=>3,'student_id'=>102,'school_year'=>'2026-2027','semester'=>1,'status'=>'closed','created_at'=>'2026-09-01 00:00:00','closed_at'=>'2026-09-03 00:00:00'],
    ];
    $refs = [];
    foreach ([1,2,9] as $i=>$hours) $refs[] = [
        'id'=>11+$i,'case_id'=>1+$i,'faculty_id'=>11,'subject_id'=>1,'section'=>'SYN-A','curriculum_year'=>3,
        'code'=>'SYN1','title'=>'Synthetic subject 1','status'=>$i === 2 ? 'closed' : 'action_taken',
        'created_at'=>'2026-09-01 00:00:00','notice_issued_at'=>sprintf('2026-09-01 %02d:00:00',$hours),
        'student_acknowledged_at'=>$i < 2 ? sprintf('2026-09-01 %02d:00:00',$hours+1) : null,
    ];
    $refs[] = array_replace($refs[0], ['id'=>14,'status'=>'needs_review','notice_issued_at'=>null,'student_acknowledged_at'=>null]);
    $refs[] = array_replace($refs[0], ['id'=>15,'status'=>'needs_review','created_at'=>'2026-09-01 10:00:00','notice_issued_at'=>'2026-09-01 09:00:00','student_acknowledged_at'=>null]);
    if (str_starts_with($state, 'small-')) $refs = array_slice($refs, 0, $state === 'small-one' ? 1 : 2);
    if ($state === 'empty') $rows = $cases = $refs = [];
    $actor = ['id'=>$role === 'faculty' ? 11 : 1, 'role'=>$role];
    return analyticsBuildReport([
        'grades'=>$rows,'predictions'=>[],'faculty'=>$role === 'faculty' ? [11=>'Synthetic Faculty 11'] : [11=>'Synthetic Faculty 11',12=>'Synthetic Faculty 12'],
        'cases'=>$cases,'referrals'=>$refs,'actions'=>[],
    ], analyticsFilters([], $actor), $actor);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $reports = [];
    foreach (['admin','faculty'] as $role) foreach (['complete','partial','provisional','unavailable','empty','single','gap','small-one','small-two'] as $state) $reports[$role][$state] = acceptanceReport($role, $state);
    echo json_encode($reports, JSON_THROW_ON_ERROR);
}
