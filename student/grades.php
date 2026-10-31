<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/grade_history.php';

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

$currentTerm = getCurrentTerm();

// Current semester subjects anchored strictly to active academic term
$currentGrades = fetchStudentCurrentGrades($db, (int)$user['id'], $currentTerm);

// Completed historical periods strictly earlier than the active term with finalized grades
$historyTerms = fetchStudentCompletedAcademicHistory($db, (int)$user['id'], $currentTerm);

$riskBadgeClass = fn(string $risk) => match (strtoupper($risk)) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'na',
};

$pageTitle = 'Grades & History';
$navItems = [
    ['Home',               'index.php',     ''],
    ['Dashboard',          'dashboard.php', ''],
    ['Grades & History',   'grades.php',    ''],
    ['Performance Trend',  'trend.php',     ''],
    ['Feedback & Support', 'feedback.php',  ''],
    ['Settings',           'settings.php',  ''],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
/* Tooltips are portaled to body so the table's scrollport cannot clip them. */
.grades-risk-tooltip {
    position: fixed; z-index: 3000; box-sizing: border-box;
    width: max-content; max-width: min(280px, calc(100vw - 24px));
    max-height: calc(100vh - 24px); overflow-y: auto;
    padding: 10px 12px; border: 1px solid #334155; border-radius: 8px;
    color: #F8FAFC; background: #0F172A; box-shadow: 0 10px 25px rgba(0,0,0,.25);
    font-size: .75rem; font-weight: 500; line-height: 1.4;
    white-space: normal; text-align: left;
}
.grades-page .custom-tooltip:focus-visible { outline: 2px solid var(--accent-blue); outline-offset: 3px; }
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
@media (max-width: 600px) {
    .grades-page { padding: 16px !important; padding-bottom: calc(96px + env(safe-area-inset-bottom, 0px)) !important; }
    .grades-card { padding: 14px; border-radius: 10px; }
    .grades-table-scroll {
        overflow-x: visible;
        width: 100%;
    }
    .current-grades-table, .history-grades-table {
        min-width: 0 !important;
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0;
    }
    .current-grades-table thead, .history-grades-table thead {
        display: none;
    }
    .current-grades-table tbody, .history-grades-table tbody {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }
    .current-grades-table tr, .history-grades-table tr {
        display: flex;
        flex-direction: column;
        background: var(--bg-color);
        border: 1px solid var(--border-color) !important;
        border-radius: 8px;
        padding: 12px 14px;
        box-sizing: border-box;
        width: 100%;
    }
    .current-grades-table td, .history-grades-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0 !important;
        border: none !important;
        border-bottom: 1px dashed var(--border-color) !important;
        text-align: right !important;
        width: 100%;
        box-sizing: border-box;
        font-size: 0.88rem;
    }
    .current-grades-table td:last-child, .history-grades-table td:last-child {
        border-bottom: none !important;
    }
    .current-grades-table td::before, .history-grades-table td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.78rem;
        color: var(--text-gray);
        text-transform: uppercase;
        letter-spacing: 0.03em;
        text-align: left;
        margin-right: 12px;
        flex-shrink: 0;
    }
    .current-grades-table td.card-cell-code, .history-grades-table td.card-cell-code {
        display: block;
        padding: 0 0 2px 0 !important;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--accent-blue) !important;
        text-align: left !important;
        border: none !important;
    }
    .current-grades-table td.card-cell-code::before, .history-grades-table td.card-cell-code::before {
        display: none;
    }
    .current-grades-table td.card-cell-title, .history-grades-table td.card-cell-title {
        display: block;
        padding: 0 0 8px 0 !important;
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--text-dark) !important;
        text-align: left !important;
        border-bottom: 1px solid var(--border-color) !important;
        margin-bottom: 4px;
        white-space: normal !important;
        max-width: 100% !important;
        overflow: visible !important;
        text-overflow: clip !important;
        line-height: 1.35;
    }
    .current-grades-table td.card-cell-title::before, .history-grades-table td.card-cell-title::before {
        display: none;
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
    <h2 style="margin-bottom: 4px; font-size: 1.25rem; color: var(--text-dark);">Current Semester Grades</h2>
    <p style="margin-bottom: 16px; font-size: 0.85rem; color: var(--text-gray);">Academic Year <?= htmlspecialchars($currentTerm['school_year']) ?> · Semester <?= (int)$currentTerm['semester'] ?></p>
    
    <div class="card grades-card" style="margin-bottom: 32px;">
        <?php if (empty($currentGrades)): ?>
            <p class="empty-state">No current-semester subjects are available.</p>
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
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Status / Final Grade</th>
                        <th class="compact-cell" style="padding: 12px; text-align: left; color: var(--text-dark);">Current Subject Risk</th>
                        <th class="compact-cell" style="padding: 12px; text-align: center; color: var(--text-dark);">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentGrades as $g): 
                        $subjStatus = determineCurrentSubjectStatus($g);
                        $prelim   = ($g['prelim'] !== null && trim((string)$g['prelim']) !== '' && isValidTermPercentage($g['prelim'])) ? (float)$g['prelim'] : null;
                        $midterm  = ($g['midterm'] !== null && trim((string)$g['midterm']) !== '' && isValidTermPercentage($g['midterm'])) ? (float)$g['midterm'] : null;
                        $prefinal = ($g['prefinal'] !== null && trim((string)$g['prefinal']) !== '' && isValidTermPercentage($g['prefinal'])) ? (float)$g['prefinal'] : null;

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
                            if (in_array($fgStr, FINAL_GRADE_STATUSES, true)) {
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
                            $subjTooltip = "High Risk indicator: The current grade point is below 1.75 (79% or lower). This advisory indicator is separate from the subject’s recorded pass/fail result. Consider discussing the record with your Faculty adviser.";
                        } elseif ($liveRisk === 'MODERATE') {
                            $subjTooltip = "Moderate Risk indicator: Your current subject grade point is between 1.75 and 2.49 (80% - 85%). Reviewing this subject may help. Additional grades may change this estimate.";
                        } elseif ($liveRisk === 'LOW') {
                            $subjTooltip = "Low Risk indicator: Your current subject grade point is 2.50 (86%) or higher. Additional grades may change this estimate.";
                        }
                        $inquirePeriod = $prefinal !== null ? 'prefinal' : ($midterm !== null ? 'midterm' : 'prelim');
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td class="compact-cell card-cell-code" data-label="Code" style="padding: 12px; font-weight: 600; color: var(--accent-blue);"><?= htmlspecialchars($g['code']) ?></td>
                        <td class="subject-cell card-cell-title" data-label="Subject Title" title="<?= htmlspecialchars($g['title']) ?>" style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                        <td class="compact-cell" data-label="Units" style="padding: 12px; text-align: center; color: var(--text-gray);"><?= htmlspecialchars($g['units']) ?></td>
                        
                        <td class="compact-cell" data-label="Prelim" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($prelim) ?></td>
                        <td class="compact-cell" data-label="Midterm" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($midterm) ?></td>
                        <td class="compact-cell" data-label="Pre-Final" style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= formatPercentage($prefinal) ?></td>
                        
                        <td class="compact-cell" data-label="Status / Final Grade" style="padding: 12px; text-align: center; font-weight: 700; color: var(--text-dark);">
                            <?php if ($subjStatus['is_finalized']): ?>
                                <?php if ($subjStatus['type'] === 'finalized_numeric'): ?>
                                    <?= formatFinalGrade($subjStatus['value']) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-gray); font-size:0.85rem; font-weight: 600;"><?= htmlspecialchars((string)$subjStatus['label']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-gray); font-size:0.85rem; font-weight: 500;"><?= htmlspecialchars((string)$subjStatus['label']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="compact-cell" data-label="Subject Risk" style="padding: 12px; text-align: left;">
                            <span class="badge custom-tooltip tooltip-top-right <?= $riskBadgeClass($liveRisk) ?>" style="display: inline-flex; align-items: center; gap: 5px;" tabindex="0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                <?= htmlspecialchars(ucfirst(strtolower($liveRisk))) ?>
                                <span class="tooltip-text" role="tooltip"><?= htmlspecialchars($subjTooltip) ?></span>
                            </span>
                        </td>
                        <td class="compact-cell" data-label="Action" style="padding: 12px; text-align: center;">
                            <?php if (!empty($g['subject_id'])): ?>
                                <a href="feedback.php?action=new&category=grade_concern&subject_id=<?= (int)$g['subject_id'] ?>&period=<?= $inquirePeriod ?>"
                                   style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: rgba(30, 77, 183, 0.08); color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.25); border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-decoration: none; transition: all 0.2s;"
                                   title="Inquire about this grade in Feedback & Support">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    <span>Inquire</span>
                                </a>
                            <?php else: ?>
                                <span style="color: var(--text-gray);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div style="margin-top: 12px;">
                <?php
                $allFinalGradesPending = true;
                $hasAnyInterim = false;
                foreach ($currentGrades as $cg) {
                    $st = determineCurrentSubjectStatus($cg);
                    if ($st['is_finalized']) {
                        $allFinalGradesPending = false;
                    }
                    if (($cg['prelim'] !== null && trim((string)$cg['prelim']) !== '')
                        || ($cg['midterm'] !== null && trim((string)$cg['midterm']) !== '')
                        || ($cg['prefinal'] !== null && trim((string)$cg['prefinal']) !== '')) {
                        $hasAnyInterim = true;
                    }
                }
                ?>
                <?php if ($allFinalGradesPending): ?>
                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0 0 4px 0;">
                        Final grades have not yet been recorded for the current semester.
                    </p>
                <?php endif; ?>
                <?php if ($hasAnyInterim): ?>
                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0 0 4px 0;">
                        Recorded Preliminary or Midterm results are available in <a href="trend.php" style="color: var(--accent-blue); text-decoration: underline;">Performance Trend</a>.
                    </p>
                <?php endif; ?>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">
                    Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.
                </p>
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
                        <td class="compact-cell card-cell-code" data-label="Code" style="padding: 10px; font-weight: 600; color: var(--accent-blue);"><?= htmlspecialchars($g['code']) ?></td>
                        <td class="subject-cell card-cell-title" data-label="Subject Title" title="<?= htmlspecialchars($g['title']) ?>" style="padding: 10px; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                        <td class="compact-cell" data-label="Units" style="padding: 10px; text-align: center; color: var(--text-gray);"><?= htmlspecialchars($g['units']) ?></td>
                        <td class="compact-cell" data-label="Final Grade" style="padding: 10px; text-align: center; font-weight: 700; color: var(--text-dark);"><?= formatFinalGrade($g['final_grade']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(() => {
    let active = null;
    let closeTimer;
    const close = () => {
        clearTimeout(closeTimer);
        if (!active) return;
        active.tooltip.hidden = true;
        active.badge.append(active.tooltip);
        active.tooltip.classList.remove('grades-risk-tooltip');
        active.tooltip.classList.add('tooltip-text');
        active.tooltip.style.removeProperty('left');
        active.tooltip.style.removeProperty('top');
        active = null;
    };
    const scheduleClose = () => {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
            if (active && !active.badge.matches(':hover, :focus-within') && !active.tooltip.matches(':hover')) close();
        }, 150);
    };
    document.querySelectorAll('.grades-page .custom-tooltip').forEach((badge, index) => {
        const tooltip = badge.querySelector('.tooltip-text');
        if (!tooltip) return;
        tooltip.hidden = true;
        tooltip.id = `grades-risk-description-${index}`;
        badge.setAttribute('aria-describedby', tooltip.id);
        const open = () => {
            clearTimeout(closeTimer);
            if (active?.badge === badge) return;
            close();
            tooltip.classList.remove('tooltip-text');
            tooltip.classList.add('grades-risk-tooltip');
            tooltip.hidden = false;
            document.body.append(tooltip);
            active = { badge, tooltip };
            const anchor = badge.getBoundingClientRect();
            const box = tooltip.getBoundingClientRect();
            const left = Math.max(12, Math.min(anchor.right - box.width, window.innerWidth - box.width - 12));
            const above = anchor.top - box.height - 10;
            const top = above >= 12 ? above : Math.max(12, Math.min(anchor.bottom + 10, window.innerHeight - box.height - 12));
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        };
        badge.addEventListener('mouseenter', open);
        badge.addEventListener('focus', open);
        badge.addEventListener('mouseleave', scheduleClose);
        badge.addEventListener('blur', scheduleClose);
        tooltip.addEventListener('mouseenter', () => clearTimeout(closeTimer));
        tooltip.addEventListener('mouseleave', scheduleClose);
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    document.addEventListener('scroll', event => {
        if (active && event.target !== active.tooltip) close();
    }, true);
    window.addEventListener('resize', close);
})();
</script>
<?php require_once '../includes/footer.php'; ?>