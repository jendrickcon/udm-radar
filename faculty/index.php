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
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Welcome, <?= htmlspecialchars($p['first_name'] ?? $user['first_name']) ?></h1>
            <p>Faculty Portal — College of Computing Studies</p>
        </div>
    </div>

    <div class="card" style="display:flex; align-items:center; gap:24px; padding:28px;">
        <div style="width:96px; height:96px; border-radius:50%; background:var(--pale-teal); display:flex; align-items:center; justify-content:center; font-size:2.5rem; flex-shrink:0;">👨‍🏫</div>
        <div style="flex:1;">
            <div style="display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:0.95rem;">
                <span style="font-weight:700; color:var(--dark_blue, var(--sidebar-bg));">Name</span>
                <span>: <?= htmlspecialchars($displayName) ?></span>

                <span style="font-weight:700; color:var(--dark_blue, var(--sidebar-bg));">Faculty ID</span>
                <span>: <?= htmlspecialchars($p['user_id'] ?? '—') ?></span>

                <span style="font-weight:700; color:var(--dark_blue, var(--sidebar-bg));">Department</span>
                <span>: College of Computing Studies</span>

                <span style="font-weight:700; color:var(--dark_blue, var(--sidebar-bg));">Email</span>
                <span>: <?= htmlspecialchars($p['email'] ?? '—') ?></span>

                <span style="font-weight:700; color:var(--dark_blue, var(--sidebar-bg));">Sections</span>
                <span>: <?= !empty($sections) ? htmlspecialchars(implode(', ', $sections)) : '—' ?></span>
            </div>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr); max-width: 600px;">
        <div class="stat-card teal">
            <h4>Assigned Sections</h4>
            <h2><?= count($sections) ?> Section<?= count($sections) !== 1 ? 's' : '' ?></h2>
        </div>
        <div class="stat-card gold">
            <h4>Total Roster Size</h4>
            <h2><?= $total_assigned_students ?> Student<?= $total_assigned_students !== 1 ? 's' : '' ?></h2>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>