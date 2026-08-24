<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// --- OFFICIAL UDM POINT CONVERSION FALLBACK ---
if (!function_exists('convertPercentageToPoint')) {
    function convertPercentageToPoint($pct) {
        $pct = round((float)$pct);
        if ($pct >= 99) return 4.00;
        if ($pct >= 97) return 3.75;
        if ($pct >= 95) return 3.50;
        if ($pct >= 92) return 3.25;
        if ($pct >= 90) return 3.00;
        if ($pct >= 88) return 2.75;
        if ($pct >= 86) return 2.50;
        if ($pct >= 84) return 2.25;
        if ($pct >= 82) return 2.00;
        if ($pct >= 80) return 1.75;
        if ($pct >= 78) return 1.50;
        if ($pct >= 76) return 1.25;
        if ($pct >= 75) return 1.00;
        return 0.00;
    }
}

// FIXED: Ensures integer display without arbitrary decimals for percentages
if (!function_exists('formatPercentage')) {
    function formatPercentage(float|int|string|null $val): string {
        if ($val === null || $val === '') return '—';
        return round((float)$val) . '%';
    }
}

if (!function_exists('formatFinalGrade')) {
    function formatFinalGrade(float|int|string|null $val): string {
        if ($val === null || $val === '') return '—';
        $str = strtoupper(trim((string)$val));
        // Special academic statuses are not numeric grades — display them
        // as-is rather than letting (float) silently turn them into 0.00.
        if (in_array($str, ['INC', 'DO', 'DU', 'FA', 'UD'], true)) return $str;
        if (!is_numeric($str)) return $str; // defensive fallback for anything unexpected
        return number_format((float)$val, 2);
    }
}

$stmtCurrent = $db->prepare("
    SELECT g.*, s.code, s.title, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
    ORDER BY s.code
");
$stmtCurrent->execute([$user['id']]);
$currentGrades = $stmtCurrent->fetchAll();

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
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'na',
};

$pageTitle = 'Grades & History';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Grades & History',   'grades.php',    '📝'],
    ['Performance Trend',  'trend.php',     '📈'],
    ['Feedback & Support', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.grades-page { min-width: 0; }
.grades-card { min-width: 0; }
.grades-table-scroll { width: 100%; overflow-x: auto; overflow-y: visible; overscroll-behavior-inline: contain; scrollbar-width: thin; scrollbar-color: #475569 transparent; }
.grades-table-scroll::-webkit-scrollbar { height: 7px; }
.grades-table-scroll::-webkit-scrollbar-track { background: transparent; }
.grades-table-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 999px; }
.grades-table-scroll::-webkit-scrollbar-thumb:hover { background: var(--accent-blue); }
.current-grades-table, .history-grades-table { width: 100%; min-width: 900px; border-collapse: collapse; }
.history-grades-table { min-width: 620px; }
.current-grades-table .subject-cell, .history-grades-table .subject-cell { min-width: 230px; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.current-grades-table th, .current-grades-table td, .history-grades-table th, .history-grades-table td { vertical-align: middle; }
.current-grades-table .compact-cell, .history-grades-table .compact-cell { white-space: nowrap; }
@media (max-width: 900px) {
    .grades-page { padding-left: 20px !important; padding-right: 20px !important; }
    .grades-card { padding: 16px; }
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
                        <th class="compact-cell" style="padding: 12px; text-align: left; color: var(--text-dark);">Code</th>
                        <th style="padding: 12px; text-align: left; color: var(--text-dark);">Subject</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Units</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Prelim</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Midterm</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Pre-Final</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Final Grade</th>
                        <th class="compact-cell" style="padding: 12px; text-align: left; color: var(--text-dark);">Current Subject Risk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentGrades as $g): 
                        $prelim   = $g['prelim'] !== null ? (float)$g['prelim'] : null;
                        $midterm  = $g['midterm'] !== null ? (float)$g['midterm'] : null;
                        $prefinal = $g['prefinal'] !== null ? (float)$g['prefinal'] : null;

                        // final_grade can be a real point-scale number OR a
                        // special status (INC/DO/DU/FA/UD). Casting straight
                        // to (float) turns a status into 0.00 silently, which
                        // then feeds computeRiskFromAvg() as if the student
                        // were failing outright. Detect the status first.
                        $finalGradeRaw = $g['final_grade'];
                        $finalGradeStatus = null;
                        $finalGrade = null;
                        if ($finalGradeRaw !== null) {
                            $fgStr = strtoupper(trim((string)$finalGradeRaw));
                            if (in_array($fgStr, ['INC', 'DO', 'DU', 'FA', 'UD'], true)) {
                                $finalGradeStatus = $fgStr;
                            } elseif (is_numeric($fgStr)) {
                                $finalGrade = (float) $fgStr;
                            }
                        }

                        $sum = 0; $count = 0;
                        if ($prelim !== null)   { $sum += $prelim; $count++; }
                        if ($midterm !== null)  { $sum += $midterm; $count++; }
                        if ($prefinal !== null) { $sum += $prefinal; $count++; }

                        $liveRisk = 'N/A';
                        if ($finalGrade !== null) {
                            $liveRisk = computeRiskFromAvg($finalGrade);
                        } elseif ($finalGradeStatus !== null) {
                            // A special status isn't a graded average to run
                            // risk math on — surface it as its own state
                            // rather than falling through to a stale
                            // prelim/midterm/prefinal-based projection.
                            $liveRisk = 'N/A';
                        } elseif ($count > 0) {
                            $avgPercent = $sum / $count;
                            $projectedGrade = convertPercentageToPoint($avgPercent);
                            $liveRisk = computeRiskFromAvg($projectedGrade);
                        }

                        $subjTooltip = "No Data: Insufficient grading data to calculate risk.";
                        if ($finalGradeStatus !== null) {
                            $subjTooltip = "Status: $finalGradeStatus. This is a recorded academic status, not a numeric grade — risk is not calculated for this subject until it's resolved.";
                        } elseif ($liveRisk === 'HIGH') {
                            $subjTooltip = "High Risk: Your current subject grade point is below 1.75 (79% or lower). Significant focus is required to prevent failure.";
                        } elseif ($liveRisk === 'MODERATE') {
                            $subjTooltip = "Moderate Risk: Your current subject grade point is between 1.75 and 2.25 (80% - 85%). Improvement is recommended.";
                        } elseif ($liveRisk === 'LOW') {
                            $subjTooltip = "Low Risk: Excellent. Your current subject grade point is 2.50 (86%) or higher.";
                        }
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="compact-cell" style="padding: 12px; font-weight: 600; color: var(--accent-blue);"><?= htmlspecialchars($g['code']) ?></td>
                        <td class="subject-cell" title="<?= htmlspecialchars($g['title']) ?>" style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-gray);"><?= htmlspecialchars($g['units']) ?></td>
                        
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($prelim) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($midterm) ?></td>
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($prefinal) ?></td>
                        
                        <td class="compact-cell" style="padding: 12px; text-align: center; font-weight: 700; color: var(--text-dark);">
                            <?php if ($finalGrade !== null): ?>
                                <?= formatFinalGrade($finalGrade) ?>
                            <?php elseif ($finalGradeStatus !== null): ?>
                                <span style="color:var(--text-gray); font-size:0.85rem;"><?= htmlspecialchars($finalGradeStatus) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-gray); font-size:0.85rem;">In Progress</span>
                            <?php endif; ?>
                        </td>
                        <td class="compact-cell" style="padding: 12px; text-align: left;">
                            <span class="badge custom-tooltip tooltip-top-right <?= $riskBadgeClass($liveRisk) ?>" style="display: inline-flex; align-items: center; gap: 5px;" tabindex="0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
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
            <div class="grades-table-scroll" role="region" tabindex="0">
            <table class="history-grades-table">
                <thead>
                    <tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);">
                        <th class="compact-cell" style="padding: 10px; text-align: left; color: var(--text-dark);">Code</th>
                        <th style="padding: 10px; text-align: left; color: var(--text-dark);">Subject</th>
                        <th class="compact-cell" style="padding: 10px; text-align: center; color: var(--text-dark);">Units</th>
                        <th class="compact-cell" style="padding: 10px; text-align: center; color: var(--text-dark);">Final Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($termGrades as $g): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="compact-cell" style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($g['code']) ?></td>
                        <td class="subject-cell" title="<?= htmlspecialchars($g['title']) ?>" style="padding: 10px; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                        <td class="compact-cell" style="padding: 10px; text-align: center; color: var(--text-gray);"><?= htmlspecialchars($g['units']) ?></td>
                        <td class="compact-cell" style="padding: 10px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatFinalGrade($g['final_grade']) ?></td>
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