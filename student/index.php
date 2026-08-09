<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// 1. Fetch Full Profile
$stmt = $db->prepare("
    SELECT u.first_name, u.middle_name, u.last_name, u.email, u.user_id AS student_number, u.created_at,
           sp.course, sp.college, sp.year_level, sp.section, sp.status, sp.current_gwa
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$p = $stmt->fetch() ?: [];

$fName = $p['first_name'] ?? $user['first_name'] ?? 'Student';
$lName = $p['last_name'] ?? $user['last_name'] ?? '';
$mName = $p['middle_name'] ?? null;
$displayName = formatNameLastFirst($fName, $mName, $lName);

// 2. Fetch Quick Glance Stats (Current Subjects & Units)
$stmtStats = $db->prepare("
    SELECT COUNT(s.id) as subject_count, SUM(s.units) as total_units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
");
$stmtStats->execute([$user['id']]);
$stats = $stmtStats->fetch();

$pageTitle = 'Home';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Grades & History',   'grades.php',    '📝'],
    ['Performance Trend',  'trend.php',     '📈'],
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Welcome, <?= htmlspecialchars($fName) ?></h1>
            <p>Student Portal — <?= htmlspecialchars($p['college'] ?? 'College of Computing Studies') ?></p>
        </div>
    </div>

    <div class="card" style="display:flex; align-items:center; gap:24px; padding:28px; margin-bottom: 24px;">
        <div style="width:96px; height:96px; border-radius:50%; background:var(--bg-color); border: 2px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:2.5rem; flex-shrink:0;">
            🎓
        </div>
        <div style="flex:1;">
            <div style="display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:0.95rem; color: var(--text-dark);">
                <span style="font-weight:700;">Name</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($displayName) ?></span>

                <span style="font-weight:700;">Student No.</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($p['student_number'] ?? '—') ?></span>

                <span style="font-weight:700;">Course</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($p['course'] ?? '—') ?></span>

                <span style="font-weight:700;">Year & Section</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($p['year_level'] ?? '—') ?> — <?= htmlspecialchars($p['section'] ?? '—') ?></span>

                <span style="font-weight:700;">Email</span>
                <span style="color: var(--text-gray);">: <?= htmlspecialchars($p['email'] ?? '—') ?></span>
            </div>
        </div>
    </div>

    <!-- Swapped border-top for border-left-color so it uses your clean, global dashboard styles -->
    <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card" style="border-left-color: var(--text-dark);">
            <h4>Enrollment Status</h4>
            <h2 style="color: var(--text-dark);"><?= htmlspecialchars($p['status'] ?? 'Regular') ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Current Subjects</h4>
            <h2 style="color: var(--accent-blue);"><?= $stats['subject_count'] ?? 0 ?> Classes</h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Total Term Units</h4>
            <h2 style="color: var(--risk-mod);"><?= $stats['total_units'] ?? 0 ?> Units</h2>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>