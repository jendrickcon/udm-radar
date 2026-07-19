<?php
// admin/grades_data.php — JSON data endpoint for the grades.php drill-down.
// Loading all ~10,000+ historical grade rows on one page load is exactly
// what this redesign is trying to avoid, so student rosters and individual
// grade histories are fetched on demand instead of embedded upfront.

require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ── List students in a section, with a lightweight risk summary ──────────
if ($action === 'students') {
    $section = trim($_GET['section'] ?? '');
    if ($section === '') { echo json_encode(['error' => 'Missing section']); exit; }

    $stmt = $db->prepare("
        SELECT sp.user_id, sp.student_number, sp.status, sp.current_gwa,
               u.first_name, u.middle_name, u.last_name,
               p.risk_level
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        LEFT JOIN predictions p ON p.student_id = sp.user_id
            AND p.generated_at = (SELECT MAX(p2.generated_at) FROM predictions p2 WHERE p2.student_id = sp.user_id)
        WHERE sp.section = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$section]);

    $out = [];
    foreach ($stmt->fetchAll() as $s) {
        $out[] = [
            'userId'     => (int) $s['user_id'],
            'studentNo'  => $s['student_number'],
            'name'       => formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']),
            'status'     => $s['status'],
            'currentGwa' => $s['current_gwa'] !== null ? (float) $s['current_gwa'] : null,
            'risk'       => $s['risk_level'],
        ];
    }
    echo json_encode($out);
    exit;
}

// ── Full grade history for one student, grouped by term ──────────────────
if ($action === 'history') {
    $studentId = (int) ($_GET['student_id'] ?? 0);
    if (!$studentId) { echo json_encode(['error' => 'Missing student_id']); exit; }

    // Only the current term is ever shown/editable here — historical
    // (Y1-Y4 closed) records aren't relevant to a correction workflow and
    // were making this modal unreadable with every past term expanded.
    $stmt = $db->prepare("
        SELECT g.id, g.school_year, g.semester, g.prelim, g.midterm, g.prefinal,
               g.final_grade, g.risk_level, s.code, s.title,
               (SELECT COUNT(*) FROM pending_corrections pc
                WHERE pc.target_type = 'grade' AND pc.target_id = g.id AND pc.status = 'pending') AS pending_count
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ? AND g.is_current = 1
        ORDER BY s.code
    ");
    $stmt->execute([$studentId]);

    $out = [];
    foreach ($stmt->fetchAll() as $g) {
        $out[] = [
            'gradeId'    => (int) $g['id'],
            'code'       => $g['code'],
            'title'      => $g['title'],
            'prelim'     => $g['prelim']     !== null ? (float) $g['prelim']     : null,
            'midterm'    => $g['midterm']    !== null ? (float) $g['midterm']    : null,
            'prefinal'   => $g['prefinal']   !== null ? (float) $g['prefinal']   : null,
            'finalGrade' => $g['final_grade']!== null ? (float) $g['final_grade']: null,
            'risk'       => $g['risk_level'],
            'hasPending' => (int) $g['pending_count'] > 0,
        ];
    }
    echo json_encode($out);
    exit;
}

echo json_encode(['error' => 'Unknown action']);