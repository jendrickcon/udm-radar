<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

// `predictions` is an append-only log — a student CAN have multiple rows
// over time (one per prediction run). Every query against it here is
// scoped to each student's MOST RECENT row via a correlated subquery,
// same pattern as admin/index.php, so a student is never counted twice
// even once the real ML pipeline starts inserting new rows over time
// instead of the UPDATE-in-place patches used so far.

// College-wide risk distribution — recomputed live from predicted_gwa via
// computeRiskFromAvg(), rather than trusting predictions.risk_level as
// stored. That stored value already drifted out of sync with reality once
// (before the section-tier recompute); recomputing live means it can't
// happen silently again.
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

// Per-section averages
$sections = $db->query("
    SELECT sp.section, COUNT(*) AS total, AVG(sp.current_gwa) AS avg_gwa,
           SUM(CASE WHEN sp.status = 'Irregular' THEN 1 ELSE 0 END) AS irregular
    FROM student_profiles sp
    WHERE sp.section IS NOT NULL
    GROUP BY sp.section
    ORDER BY sp.section
")->fetchAll();

// Latin honor track distribution — same latest-per-student scoping, plus
// explicitly ordered Summa -> Magna -> Cum Laude -> Not Eligible
// (GROUP BY alone has no defined order).
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