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

// Safe fallback for names depending on your schema
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
    ['Grades',             'grades.php',    '📝'],
    ['Academic History',   'history.php',   '📚'],
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
        <div style="width:96px; height:96px; border-radius:50%; background:var(--pale-teal, #e0f2fe); color: #0284c7; display:flex; align-items:center; justify-content:center; font-size:2.5rem; flex-shrink:0;">
            🎓
        </div>
        <div style="flex:1;">
            <div style="display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:0.95rem;">
                <span style="font-weight:700; color:var(--sidebar-bg, #0f172a);">Name</span>
                <span>: <?= htmlspecialchars($displayName) ?></span>

                <span style="font-weight:700; color:var(--sidebar-bg, #0f172a);">Student No.</span>
                <span>: <?= htmlspecialchars($p['student_number'] ?? '—') ?></span>

                <span style="font-weight:700; color:var(--sidebar-bg, #0f172a);">Course</span>
                <span>: <?= htmlspecialchars($p['course'] ?? '—') ?></span>

                <span style="font-weight:700; color:var(--sidebar-bg, #0f172a);">Year & Section</span>
                <span>: <?= htmlspecialchars($p['year_level'] ?? '—') ?> — <?= htmlspecialchars($p['section'] ?? '—') ?></span>

                <span style="font-weight:700; color:var(--sidebar-bg, #0f172a);">Email</span>
                <span>: <?= htmlspecialchars($p['email'] ?? '—') ?></span>
            </div>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card" style="border-top:4px solid #0f172a;">
            <h4>Enrollment Status</h4>
            <h2 style="color: #0f172a;"><?= htmlspecialchars($p['status'] ?? 'Regular') ?></h2>
        </div>
        <div class="stat-card" style="border-top:4px solid #0e7490;">
            <h4>Current Subjects</h4>
            <h2 style="color: #0e7490;"><?= $stats['subject_count'] ?? 0 ?> Classes</h2>
        </div>
        <div class="stat-card" style="border-top:4px solid #d97706;">
            <h4>Total Term Units</h4>
            <h2 style="color: #d97706;"><?= $stats['total_units'] ?? 0 ?> Units</h2>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>