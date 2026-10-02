<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

$currentTerm = getCurrentTerm();
$currentSy = $currentTerm['school_year']; 
$currentSem = (string) $currentTerm['semester'];

// 1. Fetch Faculty Basic Info
$stmt = $db->prepare("SELECT first_name, middle_name, last_name, email, user_id FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$p = $stmt->fetch() ?: [];
$displayName = formatNameLastFirst($p['first_name'] ?? $user['first_name'], $p['middle_name'] ?? null, $p['last_name'] ?? $user['last_name']);

// 2. Fetch Assigned Class Loads & Snapshot Data
$stmtLoads = $db->prepare("
    SELECT fcl.subject_id, fcl.section, s.code, s.title 
    FROM faculty_class_loads fcl 
    JOIN subjects s ON s.id = fcl.subject_id 
    WHERE fcl.faculty_user_id = ?
    ORDER BY s.title, fcl.section
");
$stmtLoads->execute([$user['id']]);
$myLoads = $stmtLoads->fetchAll(PDO::FETCH_ASSOC);

$uniqueSections = [];
$uniqueStudents = [];
$prelimEncodedCount = 0;
$pendingRecordsCount = 0;
$classPreview = [];
$attentionClasses = 0;

foreach ($myLoads as $load) {
    $uniqueSections[$load['section']] = true;
    
    $stmtGrades = $db->prepare("
        SELECT g.student_id, g.prelim 
        FROM grades g 
        JOIN student_profiles sp ON sp.user_id = g.student_id 
        WHERE g.subject_id = ? AND sp.section = ? AND g.school_year = ? AND g.semester = ? AND g.is_current = 1 AND sp.record_status = 'Active'
    ");
    $stmtGrades->execute([$load['subject_id'], $load['section'], $currentSy, $currentSem]);
    $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);
    
    $classTotal = count($grades);
    $classEncoded = 0;

    foreach ($grades as $g) {
        $uniqueStudents[$g['student_id']] = true;
        if ($g['prelim'] !== null && trim((string)$g['prelim']) !== '') {
            $classEncoded++;
            $prelimEncodedCount++;
        } else {
            $pendingRecordsCount++;
        }
    }
    
    $classPct = $classTotal > 0 ? round(($classEncoded / $classTotal) * 100) : 0;
    if ($classTotal > 0 && $classEncoded < $classTotal) $attentionClasses++;

    $classPreview[] = [
        'title' => $load['title'], // FIXED: Use title instead of code
        'section' => $load['section'],
        'students' => $classTotal,
        'pct' => $classPct
    ];
}

$pageTitle = 'Home';
$navItems = [
    ['Home',               'index.php',     ''],
    ['Dashboard',          'dashboard.php', ''],
    ['Class Analytics',    'analytics.php', ''],
    ['Performance Trends', 'trend.php',     ''],
    ['Term Grade Submission', 'grades.php', ''],
    ['Concerns & Reports', 'feedback.php',  ''],
    ['Settings',           'settings.php',  ''],
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

    <?php if ($attentionClasses > 0): ?>
    <div style="background: rgba(217, 119, 6, 0.08); border-left: 4px solid var(--risk-mod); padding: 16px 20px; border-radius: 6px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 style="margin: 0 0 4px 0; color: var(--risk-mod); font-size: 1rem;">Work Requiring Attention</h4>
            <p style="margin: 0; color: var(--text-dark); font-size: 0.9rem;"><strong><?= $attentionClasses ?> assigned class<?= $attentionClasses > 1 ? 'es have' : ' has' ?></strong> incomplete Preliminary grade records.</p>
        </div>
        <a href="grades.php" class="btn-primary" style="text-decoration: none; padding: 8px 16px; background: var(--risk-mod); font-size: 0.85rem;">Submit Term Grades</a>
    </div>
    <?php endif; ?>

    <div class="card" style="display:flex; align-items:center; gap:24px; padding:28px; margin-bottom: 24px;">
        <div style="width:80px; height:80px; border-radius:50%; background:var(--bg-color); border: 2px solid var(--border-color); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--accent-blue);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        </div>
        <div style="flex:1; display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:0.95rem; color: var(--text-dark);">
            <span style="font-weight:700;">Name</span><span style="color: var(--text-gray);">: <?= htmlspecialchars($displayName) ?></span>
            <span style="font-weight:700;">Faculty ID</span><span style="color: var(--text-gray);">: <?= htmlspecialchars($p['user_id'] ?? '—') ?></span>
            <span style="font-weight:700;">Department</span><span style="color: var(--text-gray);">: College of Computing Studies</span>
        </div>
    </div>

    <h2 style="font-size: 1.15rem; color: var(--text-dark); margin-bottom: 12px; font-weight: 700; text-transform: uppercase;">Current Teaching Snapshot</h2>
    <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 16px;">S.Y. <?= $currentSy ?>, <?= $currentSem === '1' ? 'First' : 'Second' ?> Semester</p>
    
    <div class="stat-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 32px;">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Assigned Class Loads</h4><h2 style="color: var(--text-dark);"><?= count($myLoads) ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--text-dark);">
            <h4>Unique Sections</h4><h2 style="color: var(--text-dark);"><?= count($uniqueSections) ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-low);">
            <h4>Unique Students Reached</h4><h2 style="color: var(--risk-low);"><?= count($uniqueStudents) ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Pending Grade Records</h4><h2 style="color: var(--risk-mod);"><?= $pendingRecordsCount ?></h2>
        </div>
    </div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; align-items: start;">        <div class="card">
            <div class="table-title" style="margin-bottom: 16px;">Quick Actions</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <a href="dashboard.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; text-align: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                    <span>Dashboard</span>
                </a>
                <a href="grades.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; text-align: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span>Submit Term Grades</span>
                </a>
                <a href="analytics.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; text-align: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span>Class Analytics</span>
                </a>
                <a href="trend.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; text-align: center;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    <span>Performance Trends</span>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="table-title" style="margin-bottom: 16px;">Assigned Classes Preview</div>
            <?php if (empty($classPreview)): ?>
                <p class="empty-state">No active classes assigned.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach($classPreview as $cp): ?>
                    <a href="grades.php" style="text-decoration: none; color: inherit; display: block;">
                        <div style="border: 1px solid var(--border-color); border-radius: 6px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;">
                            <!-- FIXED: Styling ensures long titles get truncated with ellipsis if they overflow -->
                            <div style="flex: 1; min-width: 0; display: flex; align-items: baseline;">
                                <span style="font-weight: 700; color: var(--accent-blue); margin-right: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" title="<?= htmlspecialchars($cp['title']) ?>"><?= htmlspecialchars($cp['title']) ?></span>
                                <span style="color: var(--text-dark); font-weight: 600; flex-shrink: 0;">— <?= htmlspecialchars($cp['section']) ?></span>
                            </div>
                            <div style="text-align: right; flex-shrink: 0; margin-left: 12px;">
                                <span style="font-size: 0.85rem; color: var(--text-gray); margin-right: 12px;"><?= $cp['students'] ?> students</span>
                                <span style="font-size: 0.85rem; font-weight: 600; color: <?= $cp['pct'] === 100 ? 'var(--risk-low)' : 'var(--risk-mod)' ?>;"><?= $cp['pct'] ?>% Prelim encoded</span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.card a:hover div { background: var(--table-header-bg); border-color: var(--accent-blue); }
</style>

<?php require_once '../includes/footer.php'; ?>