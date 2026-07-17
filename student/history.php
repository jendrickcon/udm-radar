<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// Past (non-current) grades, grouped by school_year + semester
$stmt = $db->prepare("
    SELECT g.*, s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 0
    ORDER BY g.school_year DESC, g.semester DESC, s.code
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$terms = [];
foreach ($rows as $r) {
    $key = ($r['school_year'] ?? 'Unknown') . ' — Sem ' . ($r['semester'] ?? '?');
    $terms[$key][] = $r;
}

$pageTitle = 'Academic History';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Grades',             'grades.php',    '📝'],
    ['Academic History',   'history.php',   '📚'],
    ['Performance Trend',  'trend.php',     '📈'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="header">
        <div>
            <h1>Academic History</h1>
            <p>Final grades from completed semesters.</p>
        </div>
    </div>

    <?php if (empty($terms)): ?>
        <div class="card"><p class="empty-state">No completed-semester records found yet.</p></div>
    <?php else: ?>
        <?php foreach ($terms as $termLabel => $termGrades): ?>
        <div class="card">
            <div class="table-title"><?= htmlspecialchars($termLabel) ?></div>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Subject</th>
                        <th>Units</th>
                        <th>Final Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($termGrades as $g): ?>
                    <tr>
                        <td><?= htmlspecialchars($g['code']) ?></td>
                        <td><?= htmlspecialchars($g['title']) ?></td>
                        <td><?= htmlspecialchars($g['units']) ?></td>
                        <td><?= $g['final_grade'] !== null ? number_format($g['final_grade'], 2) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
