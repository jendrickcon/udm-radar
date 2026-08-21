<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// 1. Fetch Full Profile
$stmt = $db->prepare("
    SELECT u.first_name, u.middle_name, u.last_name, u.email, u.user_id AS student_number, 
           sp.course, sp.college, sp.year_level, sp.section, sp.status
    FROM users u
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$p = $stmt->fetch() ?: [];

$fName = $p['first_name'] ?? $user['first_name'] ?? 'Student';
$lName = $p['last_name'] ?? $user['last_name'] ?? '';
$displayName = formatNameLastFirst($fName, $p['middle_name'] ?? null, $lName);

// 2. Fetch Current Term Snapshot & Attention Items
$currentSy = '2026-2027'; // Based on established timeline
$currentSem = '1';

$stmtGrades = $db->prepare("
    SELECT g.prelim, g.midterm, g.prefinal, g.final_grade, s.code, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
");
$stmtGrades->execute([$user['id']]);
$currentGrades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

$enrolledSubjects = count($currentGrades);
$totalUnits = array_sum(array_column($currentGrades, 'units'));
$encodedCount = 0;
$missingCount = 0;
$attentionSubjects = 0;

foreach ($currentGrades as $g) {
    // Check if at least one grade period has been entered
    if ($g['prelim'] !== null || $g['midterm'] !== null || $g['prefinal'] !== null || $g['final_grade'] !== null) {
        $encodedCount++;
        
        // Find latest grade for attention logic
        $latest = $g['final_grade'] ?? $g['prefinal'] ?? $g['midterm'] ?? $g['prelim'];
        if (in_array(strtoupper(trim((string)$latest)), ['INC', 'DO', 'DU', 'FA', 'UD', '0', '0.00'])) {
            $attentionSubjects++;
        } else {
            $pt = normalizeTermGrade($latest);
            if ($pt !== null && computeRiskFromAvg($pt) !== 'LOW') {
                $attentionSubjects++;
            }
        }
    } else {
        $missingCount++;
    }
}

// 3. Fetch Recent Activity (Latest Predictions)
$stmtPred = $db->prepare("
    SELECT predicted_gwa, risk_level, prediction_source, generated_at 
    FROM predictions 
    WHERE student_id = ? 
    ORDER BY generated_at DESC LIMIT 3
");
$stmtPred->execute([$user['id']]);
$recentActivities = $stmtPred->fetchAll(PDO::FETCH_ASSOC);

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

    <?php if ($attentionSubjects > 0): ?>
    <div style="background: rgba(220, 38, 38, 0.08); border-left: 4px solid var(--risk-high); padding: 16px 20px; border-radius: 6px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 style="margin: 0 0 4px 0; color: var(--risk-high); font-size: 1rem;">Action Required</h4>
            <p style="margin: 0; color: var(--text-dark); font-size: 0.9rem;">You have <strong><?= $attentionSubjects ?> current subject<?= $attentionSubjects > 1 ? 's' : '' ?></strong> requiring closer academic attention.</p>
        </div>
        <a href="dashboard.php" class="btn-primary" style="text-decoration: none; padding: 8px 16px; background: var(--risk-high); font-size: 0.85rem;">View Dashboard</a>
    </div>
    <?php endif; ?>

    <div class="card" style="display:flex; align-items:center; gap:24px; padding:28px; margin-bottom: 24px;">
        <div style="width:80px; height:80px; border-radius:50%; background:var(--bg-color); border: 2px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:2rem; flex-shrink:0;">🎓</div>
        <div style="flex:1; display:grid; grid-template-columns: 140px 1fr; gap:8px; font-size:0.95rem; color: var(--text-dark);">
            <span style="font-weight:700;">Name</span><span style="color: var(--text-gray);">: <?= htmlspecialchars($displayName) ?></span>
            <span style="font-weight:700;">Student No.</span><span style="color: var(--text-gray);">: <?= htmlspecialchars($p['student_number'] ?? '—') ?></span>
            <span style="font-weight:700;">Program</span><span style="color: var(--text-gray);">: <?= htmlspecialchars($p['course'] ?? '—') ?> (<?= htmlspecialchars($p['year_level'] ?? '') ?> — <?= htmlspecialchars($p['section'] ?? '') ?>)</span>
        </div>
    </div>

    <h2 style="font-size: 1.15rem; color: var(--text-dark); margin-bottom: 12px; font-weight: 700; text-transform: uppercase;">Current Term Snapshot</h2>
    <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 16px;">S.Y. <?= $currentSy ?>, <?= $currentSem === '1' ? 'First' : 'Second' ?> Semester</p>
    
    <div class="stat-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 32px;">
        <div class="stat-card" style="border-left-color: var(--text-dark);">
            <h4>Enrolled Subjects</h4><h2 style="color: var(--text-dark);"><?= $enrolledSubjects ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Total Term Units</h4><h2 style="color: var(--accent-blue);"><?= $totalUnits ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-low);">
            <h4>Grades Available</h4><h2 style="color: var(--risk-low);"><?= $encodedCount ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--text-gray);">
            <h4>Awaiting Grades</h4><h2 style="color: var(--text-gray);"><?= $missingCount ?></h2>
        </div>
    </div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; align-items: start;">
        <div class="card">
            <div class="table-title" style="margin-bottom: 16px;">Quick Actions</div>
            <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                <a href="dashboard.php" style="display: block; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; transition: border-color 0.2s;">
                    📊 View Analytics Dashboard
                </a>
                <a href="grades.php" style="display: block; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; transition: border-color 0.2s;">
                    📝 View Grades and History
                </a>
                <a href="trend.php" style="display: block; padding: 16px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600; transition: border-color 0.2s;">
                    📈 Open Performance Trend
                </a>
            </div>
        </div>

        <div class="card">
            <div class="table-title" style="margin-bottom: 16px;">Recent Academic Updates</div>
            <?php if (empty($recentActivities)): ?>
                <p class="empty-state">No recent system updates found.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach($recentActivities as $act): 
                        $date = date('M d, Y', strtotime($act['generated_at']));
                        $src = $act['prediction_source'] === 'decision_tree' ? 'AI Model Update' : 'Heuristic Estimate';
                    ?>
                    <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="font-weight: 600; color: var(--accent-blue); font-size: 0.9rem;"><?= $src ?></span>
                            <span style="color: var(--text-gray); font-size: 0.8rem;"><?= $date ?></span>
                        </div>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-dark);">
                            Academic trajectory evaluated. Current risk level classified as <strong><?= htmlspecialchars(ucfirst(strtolower($act['risk_level']))) ?></strong>.
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>