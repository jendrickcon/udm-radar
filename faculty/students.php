<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

$sectionFilter = trim($_GET['section'] ?? '');

// Faculty's own sections, from the canonical faculty_class_loads table —
// NOT g.encoded_by (that was the pre-refactor model and no longer reflects
// who is actually assigned to teach whom).
$stmtSec = $db->prepare("SELECT DISTINCT section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section");
$stmtSec->execute([$user['id']]);
$sections = $stmtSec->fetchAll(PDO::FETCH_COLUMN);

$students = [];
if (!empty($sections)) {
    $targetSections = $sectionFilter !== '' ? [$sectionFilter] : $sections;
    $inQuery = implode(',', array_fill(0, count($targetSections), '?'));
    $stmt = $db->prepare("
        SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa,
               u.first_name, u.middle_name, u.last_name, u.email,
               p.risk_level, p.predicted_gwa, p.latin_honor
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        LEFT JOIN predictions p ON p.student_id = sp.user_id
            AND p.generated_at = (SELECT MAX(p2.generated_at) FROM predictions p2 WHERE p2.student_id = sp.user_id)
        WHERE sp.section IN ($inQuery) AND u.role = 'student'
        ORDER BY sp.section, u.last_name, u.first_name
    ");
    $stmt->execute($targetSections);
    $students = $stmt->fetchAll();
    foreach ($students as &$s) {
        $s['full_name'] = formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']);
    }
    unset($s);
}

$riskBadgeClass = fn(string $risk) => match (strtoupper($risk)) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'low',
};

$pageTitle = 'My Students';
$navItems = [
    ['Home',               'index.php',         '🏠'],
    ['Dashboard',          'dashboard.php',     '📊'],
    ['Class Analytics',    'analytics.php',     '📋'],
    ['Performance Trends', 'trend.php',         '📈'],
    ['Settings',           'settings.php',      '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>My Students</h1>
            <p>Full roster across the sections assigned to you via your class loads.</p>
        </div>
    </div>

    <?php if (!empty($sections)): ?>
    <div class="card">
        <form method="GET" action="students.php" style="display:flex; gap:12px; align-items:center;">
            <label style="font-weight:600; font-size:0.9rem;">Filter by section:</label>
            <select name="section" onchange="this.form.submit()" style="padding:8px 12px; border-radius:6px; border:1px solid #ddd;">
                <option value="">All My Sections</option>
                <?php foreach ($sections as $sec): ?>
                <option value="<?= htmlspecialchars($sec) ?>" <?= $sectionFilter === $sec ? 'selected' : '' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <?php if (empty($sections)): ?>
            <p class="empty-state">No class loads assigned to your account yet.</p>
        <?php elseif (empty($students)): ?>
            <p class="empty-state">No students found for this filter.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Student No.</th><th>Name</th><th>Section</th><th>Year</th><th>Status</th><th>GWA</th><th>Honor Track</th><th>Risk</th></tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['student_number']) ?></td>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                    <td><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                    <td><?= htmlspecialchars($s['latin_honor'] ?? 'Not Eligible') ?></td>
                    <td><span class="badge <?= $riskBadgeClass($s['risk_level'] ?? 'LOW') ?>"><?= htmlspecialchars(ucfirst(strtolower($s['risk_level'] ?? 'LOW'))) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
