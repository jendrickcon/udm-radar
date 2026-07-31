<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

$riskCounts = ['LOW' => 0, 'MODERATE' => 0, 'HIGH' => 0];
$latestPredictions = $db->query("
    SELECT p.predicted_gwa
    FROM predictions p
    WHERE p.predicted_gwa IS NOT NULL
      AND p.generated_at = (
          SELECT MAX(p2.generated_at) FROM predictions p2 WHERE p2.student_id = p.student_id
      )
")->fetchAll();
foreach ($latestPredictions as $r) {
    $riskCounts[computeRiskFromAvg((float) $r['predicted_gwa'])]++;
}

$sections = $db->query("
    SELECT sp.section, COUNT(*) AS total, AVG(sp.current_gwa) AS avg_gwa,
           SUM(CASE WHEN sp.status = 'Irregular' THEN 1 ELSE 0 END) AS irregular
    FROM student_profiles sp
    WHERE sp.section IS NOT NULL
    GROUP BY sp.section
    ORDER BY sp.section
")->fetchAll();

$honors = $db->query("
    SELECT COALESCE(p.latin_honor, 'Not Eligible') AS honor, COUNT(*) AS c
    FROM predictions p
    WHERE p.generated_at = (
        SELECT MAX(p2.generated_at) FROM predictions p2 WHERE p2.student_id = p.student_id
    )
    GROUP BY honor
    ORDER BY FIELD(honor, 'Summa Cum Laude', 'Magna Cum Laude', 'Cum Laude', 'Not Eligible')
")->fetchAll();

$pageTitle = 'Program Analytics';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Students',           'students.php',  '👥'],
    ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Activity & Inbox',   'activity.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div><h1>Program Analytics</h1><p style="color: var(--text-gray);">College-wide risk distribution and per-section performance.</p></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card" style="border-left-color: var(--risk-low);">
            <h4>Low Risk</h4><h2 style="color: var(--risk-low);"><?= $riskCounts['LOW'] ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Moderate Risk</h4><h2 style="color: var(--risk-mod);"><?= $riskCounts['MODERATE'] ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-high);">
            <h4>High Risk</h4><h2 style="color: var(--risk-high);"><?= $riskCounts['HIGH'] ?></h2>
        </div>
    </div>

    <div class="card">
        <div class="table-title">Section Performance</div>
        <?php if (empty($sections)): ?>
            <p class="empty-state" style="color: var(--text-gray);">No section data available yet.</p>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Section</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Total Students</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Avg GWA</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Irregular</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $s): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($s['section']) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= (int) $s['total'] ?></td>
                    <td style="padding: 12px; color: var(--accent-blue); font-weight: 600;"><?= $s['avg_gwa'] !== null ? number_format($s['avg_gwa'], 2) : '—' ?></td>
                    <td style="padding: 12px; color: var(--text-gray);"><?= (int) $s['irregular'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="table-title">Latin Honor Track Distribution</div>
        <?php if (empty($honors)): ?>
            <p class="empty-state" style="color: var(--text-gray);">No prediction data available yet.</p>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Honor Track</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Student Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($honors as $h): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($h['honor']) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= (int) $h['c'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>