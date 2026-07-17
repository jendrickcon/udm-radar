<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

$stmt = $db->prepare("
    SELECT g.*, s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
    ORDER BY s.code
");
$stmt->execute([$user['id']]);
$grades = $stmt->fetchAll();

$riskBadgeClass = fn(string $risk) => match (strtoupper($risk)) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'low',
};

$pageTitle = 'Grades';
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
            <h1>Current Semester Grades</h1>
            <p>Full grade breakdown — Prelim, Midterm, Pre-Final, and Final where available.</p>
        </div>
    </div>

    <div class="card">
        <?php if (empty($grades)): ?>
            <p class="empty-state">No current-semester grades have been encoded yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th>Units</th>
                    <th>Prelim</th>
                    <th>Midterm</th>
                    <th>Pre-Final</th>
                    <th>Final Grade</th>
                    <th>Risk</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g): ?>
                <tr>
                    <td><?= htmlspecialchars($g['code']) ?></td>
                    <td><?= htmlspecialchars($g['title']) ?></td>
                    <td><?= htmlspecialchars($g['units']) ?></td>
                    <td><?= $g['prelim']      !== null ? number_format($g['prelim'], 2)      : '—' ?></td>
                    <td><?= $g['midterm']     !== null ? number_format($g['midterm'], 2)     : '—' ?></td>
                    <td><?= $g['prefinal']    !== null ? number_format($g['prefinal'], 2)    : '—' ?></td>
                    <td><?= $g['final_grade'] !== null ? number_format($g['final_grade'], 2) : 'In Progress' ?></td>
                    <td><span class="badge <?= $riskBadgeClass($g['risk_level'] ?? 'LOW') ?>"><?= htmlspecialchars(ucfirst(strtolower($g['risk_level'] ?? 'LOW'))) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
