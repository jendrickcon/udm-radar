<?php
declare(strict_types=1);
require_once __DIR__ . '/faculty_assessment.php';

/** SELECT-only, current configured period and verified session account; never infer past teaching. */
function facultyAssessmentRead(PDO $db, int $faculty, array $term): array {
    $q = $db->prepare("SELECT role,is_active FROM users WHERE id=?"); $q->execute([$faculty]); $account = $q->fetch();
    if (!$account || $account['role'] !== 'faculty' || (int)$account['is_active'] !== 1) throw new DomainException('Faculty assessment access denied');
    $q = $db->prepare('SELECT f.id AS load_id,f.subject_id,f.section,s.code,s.title,s.units FROM faculty_class_loads f JOIN subjects s ON s.id=f.subject_id WHERE f.faculty_user_id=? ORDER BY s.title,f.section,f.id');
    $q->execute([$faculty]); $loads = $q->fetchAll(PDO::FETCH_ASSOC);
    $q = $db->prepare("SELECT g.id,g.student_id,g.subject_id,g.prelim,g.midterm,g.prefinal,g.final_grade,g.school_year,g.semester,
        sp.section,s.units,u.first_name,u.middle_name,u.last_name
        FROM grades g JOIN student_profiles sp ON sp.user_id=g.student_id
        JOIN users u ON u.id=g.student_id AND u.role='student' AND u.is_active=1
        JOIN subjects s ON s.id=g.subject_id
        WHERE g.is_current=1 AND g.school_year=? AND g.semester=? AND sp.record_status='Active'
        AND EXISTS(SELECT 1 FROM faculty_class_loads f WHERE f.faculty_user_id=? AND f.subject_id=g.subject_id AND f.section=sp.section)
        ORDER BY g.subject_id,sp.section,g.student_id,g.id");
    $q->execute([$term['school_year'],(int)$term['semester'],$faculty]); $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) $row['name'] = formatNameLastFirst($row['first_name'],$row['middle_name'],$row['last_name']); unset($row);
    return ['loads' => $loads, 'rows' => $rows];
}

function facultyAssessmentSelect(array $loads, array $query, array $term): array {
    foreach ($query as $key => $value) if (!in_array($key,['load','sy','sem','semester'],true) || !is_string($value)) throw new InvalidArgumentException('Unexpected assessment filter');
    $year = $query['sy'] ?? $term['school_year']; $semester = $query['sem'] ?? $query['semester'] ?? (string)$term['semester'];
    if (!preg_match('/^(\d{4})-(\d{4})$/D',$year,$m) || (int)$m[2] !== (int)$m[1]+1 || !in_array($semester,['1','2','3'],true)) throw new InvalidArgumentException('Invalid academic period');
    if (isset($query['sem'],$query['semester']) && $query['sem'] !== $query['semester']) throw new InvalidArgumentException('Conflicting academic period');
    $selected = $query['load'] ?? '';
    if ($selected === '' && $loads) $selected = $loads[0]['subject_id'].'|'.$loads[0]['section'];
    $load = null;
    foreach ($loads as $item) if ($selected === $item['subject_id'].'|'.$item['section']) $load = $item;
    if ($selected !== '' && !$load) throw new DomainException('Selected class is not assigned to this account');
    return ['load' => $load, 'key' => $selected, 'school_year' => $year, 'semester' => (int)$semester,
        'current' => $year === $term['school_year'] && (int)$semester === (int)$term['semester']];
}
