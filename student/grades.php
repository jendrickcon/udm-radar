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
if (!function_exists('formatPercentage')) {
    function formatPercentage($val) {
        if ($val === null) return '—';
        return str_replace('.00', '', number_format((float)$val, 2));
    }
}

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

<style>
/* Grades page: preserve full table structure at narrow browser widths. */
.grades-page {
    min-width: 0;
}

.grades-card {
    min-width: 0;
}

.grades-table-scroll {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    overscroll-behavior-inline: contain;
    scrollbar-width: thin;
    scrollbar-color: #475569 transparent;
}

.grades-table-scroll::-webkit-scrollbar {
    height: 7px;
}

.grades-table-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.grades-table-scroll::-webkit-scrollbar-thumb {
    background: #475569;
    border-radius: 999px;
}

.grades-table-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--accent-blue);
}

.current-grades-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

.history-grades-table {
    width: 100%;
    min-width: 620px;
    border-collapse: collapse;
}

.current-grades-table .subject-cell,
.history-grades-table .subject-cell {
    min-width: 230px;
    max-width: 340px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.current-grades-table th,
.current-grades-table td,
.history-grades-table th,
.history-grades-table td {
    vertical-align: middle;
}

.current-grades-table .compact-cell,
.history-grades-table .compact-cell {
    white-space: nowrap;
}

/* Reduce page padding only after the browser becomes narrow.
   The tables remain complete inside their own horizontal scrollers. */
@media (max-width: 900px) {
    .grades-page {
        padding-left: 20px !important;
        padding-right: 20px !important;
    }

    .grades-card {
        padding: 16px;
    }
}
</style>

<div class="main-content grades-page">

    <div class="header">
        <div>
            <h1>Grades & Academic History</h1>
            <p style="color: var(--text-gray);">Your current semester progress and full academic history.</p>
        </div>
    </div>

    <!-- CURRENT SEMESTER SECTION -->
    <h2 style="margin-bottom: 16px; font-size: 1.25rem; color: var(--text-dark);">Current Semester Grades</h2>
    <div class="card grades-card" style="margin-bottom: 32px;">
        <?php if (empty($currentGrades)): ?>
            <p class="empty-state">No current-semester grades have been encoded yet.</p>
        <?php else: ?>
            <div class="grades-table-scroll" role="region" aria-label="Current semester grades" tabindex="0">
            <table class="current-grades-table">
                <thead>
                    <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                        <!-- Added width: 1% to force shrink-to-fit -->
                        <th class="compact-cell" style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Code</th>
                        <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Subject</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Units</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Prelim</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Midterm</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Pre-Final</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Final Grade</th>
                        <th class="compact-cell" style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Live Risk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentGrades as $g): 
                        $prelim   = $g['prelim'] !== null ? (float)$g['prelim'] : null;
                        $midterm  = $g['midterm'] !== null ? (float)$g['midterm'] : null;
                        $prefinal = $g['prefinal'] !== null ? (float)$g['prefinal'] : null;
                        $finalGrade = $g['final_grade'] !== null ? (float)$g['final_grade'] : null;

                        $sum = 0; $count = 0;
                        if ($prelim !== null)   { $sum += $prelim; $count++; }
                        if ($midterm !== null)  { $sum += $midterm; $count++; }
                        if ($prefinal !== null) { $sum += $prefinal; $count++; }

                        $liveRisk = 'LOW';
                        if ($finalGrade !== null) {
                            $liveRisk = computeRiskFromAvg($finalGrade);
                        } elseif ($count > 0) {
                            $avgPercent = $sum / $count;
                            $projectedGrade = convertPercentageToPoint($avgPercent);
                            $liveRisk = computeRiskFromAvg($projectedGrade);
                        }

                        $subjTooltip = "";
                        if ($liveRisk === 'HIGH') {
                            $subjTooltip = "High Risk: Your current subject grade point is below 1.75 (79% or lower). Significant focus is required to prevent failure.";
                        } elseif ($liveRisk === 'MODERATE') {
                            $subjTooltip = "Moderate Risk: Your current subject grade point is between 1.75 and 2.25 (80% - 85%). Improvement is recommended.";
                        } else {
                            $subjTooltip = "Low Risk: Excellent. Your current subject grade point is 2.50 (86%) or higher.";
                        }
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="compact-cell" style="padding: 12px; font-weight: 600; color: var(--accent-blue); white-space: nowrap;"><?= htmlspecialchars($g['code']) ?></td>
                        
                        <td class="subject-cell" title="<?= htmlspecialchars($g['title']) ?>" style="padding: 12px; color: var(--text-dark);">
                            <?= htmlspecialchars($g['title']) ?>
                        </td>
                        
                        <td class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-gray); white-space: nowrap;"><?= htmlspecialchars($g['units']) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap;"><?= formatPercentage($prelim) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap;"><?= formatPercentage($midterm) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap;"><?= formatPercentage($prefinal) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 700; color: var(--text-dark); white-space: nowrap;">
                            <?= $finalGrade !== null ? formatFinalGrade($finalGrade) : '<span style="color:var(--text-gray); font-size:0.85rem;">In Progress</span>' ?>
                        </td>
                        <td class="compact-cell" style="padding: 12px; text-align: left; white-space: nowrap;">
                            <span class="badge custom-tooltip tooltip-top-right <?= $riskBadgeClass($liveRisk) ?>" style="display: inline-flex; align-items: center; gap: 5px;" tabindex="0" aria-label="<?= htmlspecialchars($subjTooltip) ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <?= htmlspecialchars(ucfirst(strtolower($liveRisk))) ?>
                                <span class="tooltip-text" role="tooltip"><?= htmlspecialchars($subjTooltip) ?></span>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ACADEMIC HISTORY SECTION -->
    <h2 style="margin-bottom: 16px; font-size: 1.25rem; color: var(--text-dark);">Academic History</h2>
    <?php if (empty($historyTerms)): ?>
        <div class="card grades-card"><p class="empty-state">No completed-semester records found yet.</p></div>
    <?php else: ?>
        <?php foreach ($historyTerms as $termLabel => $termGrades): ?>
        <div class="card grades-card" style="margin-bottom: 24px;">
            <div class="table-title" style="margin-bottom: 12px; color: var(--text-dark); font-size: 1.1rem; border-bottom: 2px solid var(--border-color); padding-bottom: 8px;">
                <?= htmlspecialchars($termLabel) ?>
            </div>
            <div class="grades-table-scroll" role="region" aria-label="Academic history grades" tabindex="0">
            <table class="history-grades-table">
                <thead>
                    <tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);">
                        <th class="compact-cell" style="padding: 10px; text-align: left; color: var(--text-dark); white-space: nowrap;">Code</th>
                        <th style="padding: 10px; text-align: left; color: var(--text-dark); white-space: nowrap;">Subject</th>
                        <th class="compact-cell" style="padding: 10px; text-align: center; color: var(--text-dark); white-space: nowrap;">Units</th>
                        <th class="compact-cell" style="padding: 10px; text-align: center; color: var(--text-dark); white-space: nowrap;">Final Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($termGrades as $g): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="compact-cell" style="padding: 10px; font-weight: 600; color: var(--text-dark); white-space: nowrap;"><?= htmlspecialchars($g['code']) ?></td>
                        
                        <td class="subject-cell" title="<?= htmlspecialchars($g['title']) ?>" style="padding: 10px; color: var(--text-dark);">
                            <?= htmlspecialchars($g['title']) ?>
                        </td>
                        
                        <td class="compact-cell" style="padding: 10px; text-align: center; color: var(--text-gray); white-space: nowrap;"><?= htmlspecialchars($g['units']) ?></td>
                        <td class="compact-cell" style="padding: 10px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap;"><?= formatFinalGrade($g['final_grade']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>