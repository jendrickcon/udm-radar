<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

// 1. Fetch Faculty Basic Info — split columns, not the old single `name`
$stmt = $db->prepare("SELECT first_name, middle_name, last_name, email, user_id, created_at FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$p = $stmt->fetch() ?: [];

$displayName = formatNameLastFirst($p['first_name'] ?? $user['first_name'], $p['middle_name'] ?? null, $p['last_name'] ?? $user['last_name']);

// 2. Fetch Assigned Sections via precise faculty_class_loads table
$stmtSec = $db->prepare("SELECT DISTINCT section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section");
$stmtSec->execute([$user['id']]);
$sections = $stmtSec->fetchAll(PDO::FETCH_COLUMN);

// 3. Quick student count across your assigned sections from precise loads
$total_assigned_students = 0;
if (!empty($sections)) {
    $inQuery = implode(',', array_fill(0, count($sections), '?'));
    $stmtCount = $db->prepare("
        SELECT COUNT(*) 
        FROM student_profiles sp 
        JOIN users u ON u.id = sp.user_id 
        WHERE sp.section IN ($inQuery) AND u.role = 'student'
    ");
    $stmtCount->execute($sections);
    $total_assigned_students = (int)$stmtCount->fetchColumn();
}

$pageTitle = 'Home';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Encode Grades',      'grades.php',    '📝'],
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Welcome, <?= htmlspecialchars($p['first_name'] ?? $user['first_name']) ?></h1>
            <p style="color: var(--text-gray);">Faculty Portal — College of Computing Studies</p>
        </div>
    </div>

    <div class="card" style="display:flex; align-items:center; gap:24px; padding:28px; margin-bottom: 24px;">
        <div style="width:96px; height:96px; border-radius:50%; background:var(--bg-color); border: 2px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:2.5rem; flex-shrink:0;">👨‍🏫</div>
        <div style="flex:1;">
            <div style="display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:0.95rem; color: var(--text-dark);">
                <span style="font-weight:700;">Name</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($displayName) ?></span>

                <span style="font-weight:700;">Faculty ID</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($p['user_id'] ?? '—') ?></span>

                <span style="font-weight:700;">Department</span>
                <span style="color: var(--text-gray);">: College of Computing Studies</span>

                <span style="font-weight:700;">Email</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($p['email'] ?? '—') ?></span>

                <span style="font-weight:700;">Sections</span>
                <span style="color: var(--text-gray);">: <?= !empty($sections) ? htmlspecialchars(implode(', ', $sections)) : '—' ?></span>
            </div>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr); max-width: 600px;">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Assigned Sections</h4>
            <h2 style="color: var(--accent-blue);"><?= count($sections) ?> Section<?= count($sections) !== 1 ? 's' : '' ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Total Roster Size</h4>
            <h2 style="color: var(--risk-mod);"><?= $total_assigned_students ?> Student<?= $total_assigned_students !== 1 ? 's' : '' ?></h2>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>