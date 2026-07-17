<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

$error = '';
$success = '';

// This faculty's actual class loads — subjects list AND student list are now
// both scoped through faculty_class_loads, consistent with dashboard.php /
// analytics.php / students.php, instead of the old "any subject, any
// student" dropdowns.
$stmtSec = $db->prepare("SELECT DISTINCT section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section");
$stmtSec->execute([$user['id']]);
$mySections = $stmtSec->fetchAll(PDO::FETCH_COLUMN);

$subjects = $db->prepare("
    SELECT DISTINCT s.id, s.code, s.title
    FROM faculty_class_loads fcl
    JOIN subjects s ON s.id = fcl.subject_id
    WHERE fcl.faculty_user_id = ?
    ORDER BY s.code
");
$subjects->execute([$user['id']]);
$subjects = $subjects->fetchAll();

$students = [];
if (!empty($mySections)) {
    $inQuery = implode(',', array_fill(0, count($mySections), '?'));
    $stmtStu = $db->prepare("
        SELECT u.id, u.first_name, u.middle_name, u.last_name, sp.student_number, sp.section
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.role = 'student' AND sp.section IN ($inQuery)
        ORDER BY u.last_name, u.first_name
    ");
    $stmtStu->execute($mySections);
    $students = $stmtStu->fetchAll();
    foreach ($students as &$s) {
        $s['display'] = formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']);
    }
    unset($s);
}

// NOTE: risk logic lives in config/constants.php as computeRiskFromAvg()
// so encode_grades.php, the seed generator, and any future page agree.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId  = (int) ($_POST['student_id'] ?? 0);
    $subjectId  = (int) ($_POST['subject_id'] ?? 0);
    $schoolYear = trim($_POST['school_year'] ?? '');
    $semester   = (int) ($_POST['semester'] ?? 0);
    $prelim     = $_POST['prelim']   !== '' ? (float) $_POST['prelim']   : null;
    $midterm    = $_POST['midterm']  !== '' ? (float) $_POST['midterm']  : null;
    $prefinal   = $_POST['prefinal'] !== '' ? (float) $_POST['prefinal'] : null;

    if (!$studentId || !$subjectId || $schoolYear === '' || !$semester) {
        $error = 'Please fill in student, subject, school year, and semester.';
    } else {
        $vals = array_filter([$prelim, $midterm, $prefinal], fn($v) => $v !== null);
        $risk = empty($vals) ? 'LOW' : computeRiskFromAvg(array_sum($vals) / count($vals));

        // One "current" row per student+subject+term — update if it already exists
        $stmt = $db->prepare("
            SELECT id FROM grades
            WHERE student_id = ? AND subject_id = ? AND school_year = ? AND semester = ?
        ");
        $stmt->execute([$studentId, $subjectId, $schoolYear, $semester]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("
                UPDATE grades SET prelim = ?, midterm = ?, prefinal = ?, risk_level = ?, is_current = 1, encoded_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$prelim, $midterm, $prefinal, $risk, $user['id'], $existing['id']]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO grades (student_id, subject_id, school_year, semester, prelim, midterm, prefinal, risk_level, is_current, encoded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$studentId, $subjectId, $schoolYear, $semester, $prelim, $midterm, $prefinal, $risk, $user['id']]);
        }
        $success = 'Grade saved. Risk level computed as ' . $risk . '.';
    }
}

$pageTitle = 'Encode Grades';
$navItems = [
    ['Home',               'index.php',         '🏠'],
    ['Dashboard',          'dashboard.php',     '📊'],
    ['Class Analytics',    'analytics.php',     '📋'],
    ['Encode Grades',      'encode_grades.php', '📝'],
    ['Performance Trends', 'trend.php',         '📈'],
    ['Settings',           'settings.php',      '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Encode Grades</h1>
            <p>Enter Prelim / Midterm / Pre-Final scores as soon as they're available — the earlier this is done, the earlier risk alerts can fire.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (empty($mySections)): ?>
        <div class="card"><p class="empty-state">No class loads assigned to your account yet — nothing to encode.</p></div>
    <?php else: ?>
    <div class="card" style="max-width:600px;">
        <form method="POST" action="encode_grades.php">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Student</label>
                <select name="student_id" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">— Select student —</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['student_number'] . ' — ' . $s['display'] . ' (' . $s['section'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Subject</label>
                <select name="subject_id" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">— Select subject —</option>
                    <?php foreach ($subjects as $subj): ?>
                    <option value="<?= $subj['id'] ?>"><?= htmlspecialchars($subj['code'] . ' — ' . $subj['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:12px; margin-bottom:16px;">
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">School Year</label>
                    <input type="text" name="school_year" placeholder="2026-2027" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Semester</label>
                    <select name="semester" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                        <option value="">—</option>
                        <option value="1">1st</option>
                        <option value="2">2nd</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:12px; margin-bottom:20px;">
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Prelim</label>
                    <input type="number" step="0.01" name="prelim" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Midterm</label>
                    <input type="number" step="0.01" name="midterm" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Pre-Final</label>
                    <input type="number" step="0.01" name="prefinal" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                </div>
            </div>
            <button type="submit" style="width:100%; padding:14px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                Save Grade
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
