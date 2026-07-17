<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

// College-wide risk distribution — recomputed live from predicted_gwa via
// computeRiskFromAvg(), rather than trusting predictions.risk_level as
// stored. That stored value already drifted out of sync with reality once
// (before the section-tier recompute); recomputing live means it can't
// happen silently again.
$riskCounts = ['LOW' => 0, 'MODERATE' => 0, 'HIGH' => 0];
foreach ($db->query("SELECT predicted_gwa FROM predictions WHERE predicted_gwa IS NOT NULL")->fetchAll() as $r) {
    $riskCounts[computeRiskFromAvg((float) $r['predicted_gwa'])]++;
}

// Per-section averages
$sections = $db->query("
    SELECT sp.section, COUNT(*) AS total, AVG(sp.current_gwa) AS avg_gwa,
           SUM(CASE WHEN sp.status = 'Irregular' THEN 1 ELSE 0 END) AS irregular
    FROM student_profiles sp
    WHERE sp.section IS NOT NULL
    GROUP BY sp.section
    ORDER BY sp.section
")->fetchAll();

// Latin honor track distribution — explicitly ordered Summa -> Magna ->
// Cum Laude -> Not Eligible (GROUP BY alone has no defined order).
$honors = $db->query("
    SELECT COALESCE(latin_honor, 'Not Eligible') AS honor, COUNT(*) AS c
    FROM predictions GROUP BY honor
    ORDER BY FIELD(honor, 'Summa Cum Laude', 'Magna Cum Laude', 'Cum Laude', 'Not Eligible')
")->fetchAll();

$pageTitle = 'Program Analytics';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Manage Students',    'students.php',  '👥'],
    ['Manage Faculty',     'faculty.php',   '👨‍🏫'],
    ['Manage Grades',      'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div><h1>Program Analytics</h1><p>College-wide risk distribution and per-section performance.</p></div>
    </div>

    <div class="stat-grid">
        <div class="stat-card green"><h4>Low Risk</h4><h2><?= $riskCounts['LOW'] ?></h2></div>
        <div class="stat-card gold"><h4>Moderate Risk</h4><h2><?= $riskCounts['MODERATE'] ?></h2></div>
        <div class="stat-card red"><h4>High Risk</h4><h2><?= $riskCounts['HIGH'] ?></h2></div>
    </div>

    <div class="card">
        <div class="table-title">Section Performance</div>
        <?php if (empty($sections)): ?>
            <p class="empty-state">No section data available yet.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Section</th><th>Total Students</th><th>Avg GWA</th><th>Irregular</th></tr></thead>
            <tbody>
                <?php foreach ($sections as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['section']) ?></td>
                    <td><?= (int) $s['total'] ?></td>
                    <td><?= $s['avg_gwa'] !== null ? number_format($s['avg_gwa'], 2) : '—' ?></td>
                    <td><?= (int) $s['irregular'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="table-title">Latin Honor Track Distribution</div>
        <?php if (empty($honors)): ?>
            <p class="empty-state">No prediction data available yet.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Honor Track</th><th>Student Count</th></tr></thead>
            <tbody>
                <?php foreach ($honors as $h): ?>
                <tr><td><?= htmlspecialchars($h['honor']) ?></td><td><?= (int) $h['c'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
