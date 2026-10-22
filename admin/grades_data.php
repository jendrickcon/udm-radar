<?php
// admin/grades_data.php 
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/grade_history.php';

requireRole('admin');
$db = getDB();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'students') {
    $section = trim($_GET['section'] ?? '');
    if ($section === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing section']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT sp.user_id, sp.student_number, sp.status, sp.year_level, sp.section, sp.record_status, sp.current_gwa,
               u.first_name, u.middle_name, u.last_name,
               p.risk_level, p.predicted_gwa
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        LEFT JOIN predictions p ON p.id = (
            SELECT p2.id FROM predictions p2
            WHERE p2.student_id = sp.user_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
        WHERE sp.section = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$section]);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $out[] = [
            'userId'       => (int) $s['user_id'],
            'studentNo'    => $s['student_number'],
            'name'         => formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']),
            'status'       => $s['status'] ?? 'Regular',
            'yearLevel'    => $s['year_level'],
            'section'      => $s['section'],
            'currentGwa'   => $s['current_gwa'] !== null ? (float) $s['current_gwa'] : null,
            'predictedGwa' => $s['predicted_gwa'] !== null ? (float) $s['predicted_gwa'] : null,
            'risk'         => $s['risk_level'],
        ];
    }
    echo json_encode($out);
    exit;
}

if ($action === 'history' || $action === 'student_detail' || $action === 'current_grades') {
    $studentId = filter_var($_GET['student_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$studentId || $studentId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid student_id']);
        exit;
    }

    // Verify student exists and fetch profile
    $stmtStu = $db->prepare("
        SELECT sp.user_id, sp.student_number, sp.status, sp.year_level, sp.section, sp.record_status, sp.current_gwa,
               u.first_name, u.middle_name, u.last_name,
               p.risk_level, p.predicted_gwa
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        LEFT JOIN predictions p ON p.id = (
            SELECT p2.id FROM predictions p2
            WHERE p2.student_id = sp.user_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
        WHERE sp.user_id = ?
    ");
    $stmtStu->execute([$studentId]);
    $student = $stmtStu->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student record not found']);
        exit;
    }

    // Fetch current semester grades (is_current = 1)
    $stmtGrades = $db->prepare("
        SELECT g.id, g.school_year, g.semester, g.prelim, g.midterm, g.prefinal,
               g.final_grade, g.risk_level, s.code, s.title, s.units,
               (SELECT COUNT(*) FROM pending_corrections pc
                WHERE pc.target_type = 'grade' AND pc.target_id = g.id AND pc.status = 'pending') AS pending_count
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ? AND g.is_current = 1
        ORDER BY s.code
    ");
    $stmtGrades->execute([$studentId]);

    $grades = [];
    foreach ($stmtGrades->fetchAll(PDO::FETCH_ASSOC) as $g) {
        $grades[] = [
            'gradeId'    => (int) $g['id'],
            'code'       => $g['code'],
            'title'      => cleanSubjectTitle($g['title']),
            'units'      => isset($g['units']) ? (int) $g['units'] : null,
            'prelim'     => $g['prelim']     !== null ? (float) $g['prelim']     : null,
            'midterm'    => $g['midterm']    !== null ? (float) $g['midterm']    : null,
            'prefinal'   => $g['prefinal']   !== null ? (float) $g['prefinal']   : null,
            'finalGrade' => $g['final_grade'] !== null ? formatFinalGrade($g['final_grade']) : null,
            'risk'       => $g['risk_level'],
            'hasPending' => (int) $g['pending_count'] > 0,
        ];
    }

    $studentData = [
        'userId'       => (int) $student['user_id'],
        'studentNo'    => $student['student_number'],
        'name'         => formatNameLastFirst($student['first_name'], $student['middle_name'], $student['last_name']),
        'status'       => $student['status'] ?? 'Regular',
        'yearLevel'    => $student['year_level'],
        'section'      => $student['section'],
        'recordStatus' => $student['record_status'] ?? 'Active',
        'currentGwa'   => $student['current_gwa'] !== null ? (float) $student['current_gwa'] : null,
        'predictedGwa' => $student['predicted_gwa'] !== null ? (float) $student['predicted_gwa'] : null,
        'risk'         => $student['risk_level'],
    ];

    echo json_encode([
        'student' => $studentData,
        'grades'  => $grades,
    ]);
    exit;
}

if ($action === 'academic_history' || $action === 'grade_history') {
    $studentId = filter_var($_GET['student_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$studentId || $studentId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid student_id']);
        exit;
    }

    // Verify student exists
    $stmtCheck = $db->prepare("SELECT user_id FROM student_profiles WHERE user_id = ?");
    $stmtCheck->execute([$studentId]);
    if (!$stmtCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Student record not found']);
        exit;
    }

    $historyTerms = getStudentAcademicHistory($db, $studentId);
    $termsList = array_values($historyTerms);

    echo json_encode([
        'studentId' => $studentId,
        'terms'     => $termsList,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
