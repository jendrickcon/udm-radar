<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// ---------------------------------------------------------
// Helper Functions
// ---------------------------------------------------------
if (!function_exists('convertPercentageToGrade')) {
    function convertPercentageToGrade(float $p): float {
        if ($p >= 98) return 1.00;
        if ($p >= 95) return 1.25;
        if ($p >= 92) return 1.50;
        if ($p >= 89) return 1.75;
        if ($p >= 86) return 2.00;
        if ($p >= 83) return 2.25;
        if ($p >= 80) return 2.50;
        if ($p >= 77) return 2.75;
        if ($p >= 75) return 3.00;
        return 5.00;
    }
}

if (!function_exists('computeRiskFromAvg')) {
    function computeRiskFromAvg(float $grade): string {
        if ($grade <= 2.25) return 'LOW';
        if ($grade <= 2.75) return 'MODERATE';
        return 'HIGH';
    }
}

// Smart formatter for 0-100 percentages: drops ".00"
if (!function_exists('formatPercentage')) {
    function formatPercentage($val) {
        if ($val === null) return '—';
        return str_replace('.00', '', number_format((float)$val, 2));
    }
}

// Strict formatter for 1.00-5.00 scale: always keeps 2 decimal places
if (!function_exists('formatFinalGrade')) {
    function formatFinalGrade($val) {
        if ($val === null) return '—';
        return number_format((float)$val, 2);
    }
}

// ---------------------------------------------------------
// 1. Fetch Current Semester Grades
// ---------------------------------------------------------
$stmtCurrent = $db->prepare("
    SELECT g.*, s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
    ORDER BY s.code
");
$stmtCurrent->execute([$user['id']]);
$currentGrades = $stmtCurrent->fetchAll();

// ---------------------------------------------------------
// 2. Fetch Academic History
// ---------------------------------------------------------
$stmtHistory = $db->prepare("
    SELECT g.*, s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 0
    ORDER BY g.school_year DESC, g.semester DESC, s.code
");
$stmtHistory->execute([$user['id']]);
$historyRows = $stmtHistory->fetchAll();

$historyTerms = [];
foreach ($historyRows as $r) {
    $key = ($r['school_year'] ?? 'Unknown') . ' — Sem ' . ($r['semester'] ?? '?');
    $historyTerms[$key][] = $r;
}

$riskBadgeClass = fn(string $risk) => match (strtoupper($risk)) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'low',
};

$pageTitle = 'Grades & History';
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

    <div class="header">
        <div>
            <h1>Grades & Academic History</h1>
            <p style="color: var(--text-gray);">Your current semester progress and full academic history.</p>
        </div>
    </div>

    <!-- CURRENT SEMESTER SECTION -->
    <h2 style="margin-bottom: 16px; font-size: 1.25rem; color: var(--text-dark);">Current Semester Grades</h2>
    <div class="card" style="margin-bottom: 32px;">
        <?php if (empty($currentGrades)): ?>
            <p class="empty-state">No current-semester grades have been encoded yet.</p>
        <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Code</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Subject</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Units</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Prelim</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Midterm</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Pre-Final</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Final Grade</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Live Risk</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($currentGrades as $g): 
                    $prelim   = $g['prelim'] !== null ? (float)$g['prelim'] : null;
                    $midterm  = $g['midterm'] !== null ? (float)$g['midterm'] : null;
                    $prefinal = $g['prefinal'] !== null ? (float)$g['prefinal'] : null;
                    $finalGrade = $g['final_grade'] !== null ? (float)$g['final_grade'] : null;

                    // Compute risk live by averaging the raw percentages first, then converting to 1.0-5.0 scale
                    $sum = 0; $count = 0;
                    if ($prelim !== null)   { $sum += $prelim; $count++; }
                    if ($midterm !== null)  { $sum += $midterm; $count++; }
                    if ($prefinal !== null) { $sum += $prefinal; $count++; }

                    $liveRisk = 'LOW';
                    if ($finalGrade !== null) {
                        $liveRisk = computeRiskFromAvg($finalGrade);
                    } elseif ($count > 0) {
                        $avgPercent = $sum / $count;
                        $projectedGrade = convertPercentageToGrade($avgPercent);
                        $liveRisk = computeRiskFromAvg($projectedGrade);
                    }
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; font-weight: 600; color: var(--accent-blue);"><?= htmlspecialchars($g['code']) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                    <td style="padding: 12px; color: var(--text-gray);"><?= htmlspecialchars($g['units']) ?></td>
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($prelim) ?></td>
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($midterm) ?></td>
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($prefinal) ?></td>
                    <td style="padding: 12px; font-weight: 700; color: var(--text-dark);"><?= $finalGrade !== null ? formatFinalGrade($finalGrade) : '<span style="color:var(--text-gray); font-size:0.85rem;">In Progress</span>' ?></td>
                    <td style="padding: 12px;"><span class="badge <?= $riskBadgeClass($liveRisk) ?>"><?= htmlspecialchars(ucfirst(strtolower($liveRisk))) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ACADEMIC HISTORY SECTION -->
    <h2 style="margin-bottom: 16px; font-size: 1.25rem; color: var(--text-dark);">Academic History</h2>
    <?php if (empty($historyTerms)): ?>
        <div class="card"><p class="empty-state">No completed-semester records found yet.</p></div>
    <?php else: ?>
        <?php foreach ($historyTerms as $termLabel => $termGrades): ?>
        <div class="card" style="margin-bottom: 24px;">
            <div class="table-title" style="margin-bottom: 12px; color: var(--text-dark); font-size: 1.1rem; border-bottom: 2px solid var(--border-color); padding-bottom: 8px;">
                <?= htmlspecialchars($termLabel) ?>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 10px; text-align: left; color: var(--text-dark);">Code</th>
                        <th style="padding: 10px; text-align: left; color: var(--text-dark);">Subject</th>
                        <th style="padding: 10px; text-align: left; color: var(--text-dark);">Units</th>
                        <th style="padding: 10px; text-align: left; color: var(--text-dark);">Final Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($termGrades as $g): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($g['code']) ?></td>
                        <td style="padding: 10px; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                        <td style="padding: 10px; color: var(--text-gray);"><?= htmlspecialchars($g['units']) ?></td>
                        <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= formatFinalGrade($g['final_grade']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>