<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../includes/goal_calculator.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// 1. Fetch Student Profile & Cumulative GWA
$stmtProf = $db->prepare("
    SELECT sp.student_number, sp.course, sp.year_level, sp.section, sp.status, sp.current_gwa, u.first_name, u.last_name
    FROM student_profiles sp
    JOIN users u ON u.id = sp.user_id
    WHERE u.id = ?
");
$stmtProf->execute([$user['id']]);
$profile = $stmtProf->fetch();

$current_gwa = computeStudentGwa($db, $user['id']);
$historical_gwa = $current_gwa;
$hasCurrentGwa = $current_gwa !== null;
$currentGwaDisplay = $hasCurrentGwa ? number_format($current_gwa, 2) : 'N/A';

// --- Fetch ML Prediction from Database ---
$metaCols = getPredictionFullCompletenessSqlSelect($db);
$stmtPred = $db->prepare("
    SELECT predicted_gwa, risk_level, latin_honor, prediction_source, generated_at,
           $metaCols
    FROM predictions 
    WHERE student_id = ? 
    ORDER BY generated_at DESC, id DESC
    LIMIT 1
");
$stmtPred->execute([$user['id']]);
$ml_prediction = $stmtPred->fetch(PDO::FETCH_ASSOC);

// 2. Fetch Current Semester Subjects & All Grades
$stmtCurr = $db->prepare("
    SELECT s.id AS subject_id, s.code, s.title, s.units, g.prelim, g.midterm, g.prefinal, g.final_grade
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
");
$stmtCurr->execute([$user['id']]);
$current_subjects = $stmtCurr->fetchAll();

// 3. Heuristic prediction & Subject Triage
$prediction_rows = [];
$triage_alerts = [];
$risk_factors = [];
$at_risk_count = 0;

if (($profile['status'] ?? 'Regular') === 'Irregular') {
    $risk_factors[] = ['type' => 'danger', 'text' => 'Historical: Your record shows an irregular enrollment pattern. Your Faculty adviser can help you plan your remaining terms.'];
}

foreach ($current_subjects as &$subj) {
    $subj['prelim_raw'] = $subj['prelim'];
    $subj['midterm_raw'] = $subj['midterm'];
    $subj['prefinal_raw'] = $subj['prefinal'];
    
    $prelimPoint = normalizeTermGrade($subj['prelim']);
    $predicted_final = predictFinalGradeHeuristic($prelimPoint, $historical_gwa);
    
    $subj['prelim_point'] = $prelimPoint;
    $subj['predicted_final'] = $predicted_final;
    $subj['final_risk'] = $predicted_final !== null ? computeRiskFromAvg($predicted_final) : null;
    
    if ($predicted_final !== null) {
        $prediction_rows[] = ['grade' => $predicted_final, 'units' => (int) $subj['units']];
    }

    if ($prelimPoint !== null) {
        $subjRisk = computeRiskFromAvg($prelimPoint);
        $displayTitle = displaySubjectTitle($subj['code'], $subj['title']);
        if ($subjRisk === 'HIGH') {
            $triage_alerts[] = [
                'level' => 'HIGH',
                'text' => "<strong>High Risk indicator:</strong> Your current grade in <strong>{$displayTitle}</strong> is " . round((float)$subj['prelim_raw']) . "%. Additional grades may change this estimate. Consider discussing it with your Faculty adviser.",
            ];
            $at_risk_count++;
        } elseif ($subjRisk === 'MODERATE') {
            $triage_alerts[] = [
                'level' => 'MODERATE',
                'text' => "<strong>Moderate Risk indicator:</strong> Your current grade in <strong>{$displayTitle}</strong> is " . round((float)$subj['prelim_raw']) . "%. This may affect your current estimate. Reviewing this subject may help.",
            ];
            $at_risk_count++;
        }
    }
}
unset($subj);

$heuristic_gwa = computeWeightedGWA($prediction_rows) ?? $historical_gwa;
$has_fallback_data = $heuristic_gwa !== null;
$heuristic_risk = $has_fallback_data ? computeRiskFromAvg($heuristic_gwa) : null;

$has_ai_prediction = $ml_prediction && $ml_prediction['predicted_gwa'] !== null;

if ($has_ai_prediction) {
    $display_predicted_gwa = (float) $ml_prediction['predicted_gwa'];
    // Advisory risk must describe the estimate shown, even if its saved risk label is stale.
    $display_risk = computeRiskFromAvg($display_predicted_gwa);
    $display_honor = $ml_prediction['latin_honor'] ?? getLatinHonor($display_predicted_gwa, hasDisqualifyingGrade($user['id'], $db));
    $prediction_source = normalizePredictionSourceBoundary($ml_prediction['prediction_source'] ?? PREDICTION_SOURCE_DECISION_TREE) ?? PREDICTION_SOURCE_DECISION_TREE;
} elseif ($has_fallback_data) {
    $display_predicted_gwa = $heuristic_gwa;
    $display_risk = $heuristic_risk;
    $display_honor = getLatinHonor($display_predicted_gwa, hasDisqualifyingGrade($user['id'], $db));
    $prediction_source = PREDICTION_SOURCE_CALCULATION_FALLBACK;
} else {
    $display_predicted_gwa = null;
    $display_risk = null;
    $display_honor = null;
    $prediction_source = 'insufficient_data';
}

$explanation = getPredictionExplanationMetadata($ml_prediction ?: [
    'prediction_source' => $prediction_source,
    'risk_level'        => $display_risk,
    'generated_at'      => null,
    'data_completeness' => $has_fallback_data ? 'complete' : 'insufficient_data',
    'is_partial'        => empty($current_subjects),
], 'student');

if ($at_risk_count > 0) {
    $risk_factors[] = ['type' => 'warning', 'text' => "Current Term: {$at_risk_count} current subject(s) have an estimate below 2.50. Additional grades may change this estimate."];
}
if ($historical_gwa !== null && $display_predicted_gwa !== null) {
    $diff = round($display_predicted_gwa - $historical_gwa, 2);
    
    if ($diff < 0) { 
        $drop = number_format(abs($diff), 2);
        $risk_factors[] = ['type' => 'warning', 'text' => "Trajectory: The current estimate is {$drop} lower than your historical GWA. Additional grades may change this estimate."];
    } elseif ($diff > 0) {
        $gain = number_format($diff, 2);
        $risk_factors[] = ['type' => 'success', 'text' => "Trajectory: The current estimate is {$gain} higher than your historical GWA."];
    } else {
        $risk_factors[] = ['type' => 'info', 'text' => "Trajectory: The current estimate is close to your historical GWA."];
    }
}

$gradedSubjects = array_filter($current_subjects, fn($s) => $s['prelim_point'] !== null);
if (!empty($gradedSubjects)) {
    $lowestGrade = min(array_column($gradedSubjects, 'prelim_point')); 
    $weakestSubjects = array_values(array_filter(
        $gradedSubjects,
        fn($s) => $s['prelim_point'] == $lowestGrade
    ));

    $titles = array_map(
        fn($s) => displaySubjectTitle($s['code'], $s['title']),
        $weakestSubjects
    );
    $subjectList = count($titles) > 1
        ? implode(', ', array_slice($titles, 0, -1)) . ' and ' . end($titles)
        : $titles[0];
    $plural = count($titles) > 1 ? 'these subjects' : 'this subject';
    $lowestRaw = min(array_column($weakestSubjects, 'prelim_raw')); 

    if (computeRiskFromAvg($lowestGrade) !== 'LOW') {
        $risk_factors[] = [
            'type' => 'info',
            'text' => count($titles) > 1
                ? "Focus suggestion: Your lowest current grades are in <strong>{$subjectList}</strong> at <strong>" . round((float)$lowestRaw) . "%</strong>. Spending some extra study time there may help."
                : "Focus suggestion: Your lowest current grade is in <strong>{$subjectList}</strong> at <strong>" . round((float)$lowestRaw) . "%</strong>. Spending some extra study time there may help.",
        ];
    } else {
        $risk_factors[] = [
            'type' => 'info',
            'text' => count($titles) > 1
                ? "Suggestion: Your current grades look steady. Your lowest grades are in <strong>{$subjectList}</strong> at <strong>" . round((float)$lowestRaw) . "%</strong>."
                : "Suggestion: Your current grades look steady. Your lowest grade is in <strong>{$subjectList}</strong> at <strong>" . round((float)$lowestRaw) . "%</strong>.",
        ];
    }
}

if (empty($risk_factors)) {
    $risk_factors[] = ['type' => 'success', 'text' => 'Positive: No risk factors are showing in your current records.'];
}

$riskBg = match($display_risk) {
    'HIGH' => 'var(--risk-high)',
    'MODERATE' => 'var(--risk-mod)',
    'LOW' => 'var(--risk-low)',
    default => 'var(--text-gray)'
}; 

$riskTooltip = "";
$isAiSource = getPredictionSourceFamily($prediction_source) === 'model';
if ($display_risk === 'HIGH') {
    $riskTooltip = $isAiSource
        ? "Estimate is in the High Risk range and may change as more grades are recorded."
        : "Current estimate is below 1.75 and may change as more grades are recorded.";
} elseif ($display_risk === 'MODERATE') {
    $riskTooltip = "Current estimate is in the Moderate Risk range, from 1.75 to 2.49.";
} elseif ($display_risk === 'LOW') {
    $riskTooltip = "Current estimate is in the Low Risk range, 2.50 and above.";
} else {
    $riskTooltip = "There isn't enough recorded academic data yet to generate a prediction.";
}

$shortRiskSummary = match($display_risk) {
    'HIGH' => $isAiSource
        ? "High Risk indicator: The AI-based projection places your current estimate in the High Risk range. Past records and current grades can both affect it, and additional grades may change it. Consider discussing it with your Faculty adviser."
        : "High Risk indicator: Based on your current recorded grades, your current estimate is below 1.75. Additional grades may change this estimate. Consider discussing next steps with your Faculty adviser.",
    'MODERATE' => "Moderate Risk indicator: Your current estimate is between 1.75 and 2.49. Reviewing your subjects may help. Additional grades may change this estimate.",
    'LOW' => "Low Risk indicator: Your current estimate is 2.50 or above. Additional grades may change this estimate.",
    default => 'Insufficient academic data to determine trajectory.'
};

$honor_color = match ($display_honor) {
    'Summa Cum Laude' => '#b45309',
    'Magna Cum Laude'  => '#1d4ed8',
    'Cum Laude'        => 'var(--accent-blue)',
    default            => 'var(--text-gray)',
};

$valid_grades = FINAL_GRADE_POINTS;
function renderTargetOptions($valid_grades) {
    $html = '<option value="">—</option>';
    foreach ($valid_grades as $g) {
        $valStr = formatFinalGrade($g);
        $html .= "<option value=\"$valStr\">$valStr</option>";
    }
    return $html;
}
$calculatorConfig = goalCalculatorConfig();

$pageTitle = 'Dashboard';
$navItems = [
    ['Home',               'index.php',     ''],
    ['Dashboard',          'dashboard.php', ''],
    ['Grades & History',   'grades.php',    ''],
    ['Performance Trend',  'trend.php',     ''],
    ['Feedback & Support', 'feedback.php', ''],
    ['Settings',           'settings.php',  ''],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.student-dashboard > .header { flex-wrap: wrap; gap: 16px; }
.student-dashboard .analysis-panels { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.student-dashboard .analysis-panels > * { min-width: 0; }
.student-dashboard .stat-card-heading-with-info { position: relative; flex-wrap: wrap; }
.student-dashboard .risk-info-icon { position: static; }
.student-dashboard .risk-info-icon .tooltip-text { left: 0; right: auto; max-width: 100%; }
.student-dashboard .subject-heading, .student-dashboard .calculator-heading,
.student-dashboard .calculator-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.student-dashboard .subject-heading button, .student-dashboard .calculator-actions button { min-height: 44px; }
.student-dashboard .calculator-actions > * { min-width: 0; }
.student-dashboard .data-table-scroll { max-width: 100%; }
.student-dashboard #calculator-section input, .student-dashboard #calculator-section select { min-height: 44px; }
@media (max-width: 1100px) {
    .student-dashboard .analysis-panels { grid-template-columns: minmax(0, 1fr); }
}
.distinction-label-compact { display: none; }
@media (max-width: 420px) {
    .distinction-label-full { display: none !important; }
    .distinction-label-compact { display: inline !important; }
}
@media (max-width: 600px) {
    .student-dashboard .calculator-actions { width: 100%; }
    .student-dashboard .calculator-actions > div { flex: 1 1 100%; }
    .student-dashboard .card { padding: 16px; }

    .student-subjects-table {
        min-width: 0 !important;
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0;
    }
    .student-subjects-table thead {
        display: none;
    }
    .student-subjects-table tbody {
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }
    .student-subjects-table tr {
        display: flex;
        flex-direction: column;
        background: var(--bg-color);
        border: 1px solid var(--border-color) !important;
        border-radius: 8px;
        padding: 12px 14px;
        box-sizing: border-box;
        width: 100%;
    }
    .student-subjects-table td {
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
    .student-subjects-table td:last-child {
        border-bottom: none !important;
    }
    .student-subjects-table td::before {
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
    .student-subjects-table td.card-cell-code {
        display: block;
        padding: 0 0 2px 0 !important;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--accent-blue) !important;
        text-align: left !important;
        border: none !important;
    }
    .student-subjects-table td.card-cell-code::before {
        display: none;
    }
    .student-subjects-table td.card-cell-title {
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
    .student-subjects-table td.card-cell-title::before {
        display: none;
    }
}

/* CSS for the Scroll Fade Indicator */
.triage-scroll { scrollbar-width: thin; scrollbar-color: var(--border-color) transparent; }
.triage-scroll::-webkit-scrollbar { width: 6px; }
.triage-scroll::-webkit-scrollbar-track { background: transparent; }
.triage-scroll::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 999px; }
.triage-scroll::-webkit-scrollbar-thumb:hover { background: var(--text-gray); }

.triage-fade {
    position: absolute;
    left: 0; right: 4px; bottom: 0;
    height: 40px;
    background: linear-gradient(to bottom, transparent, var(--card-bg));
    pointer-events: none;
    opacity: 1;
    transition: opacity 0.2s ease;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}
.triage-fade.hidden { opacity: 0; }
</style>

<div class="main-content student-dashboard">
    <div class="header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Analytics Dashboard</h1>
            <p style="color: var(--text-gray); font-size: 0.95rem;">Decision-support center and academic estimation.</p>
            <p><a href="academic_analytics.php" class="btn btn--secondary">Review GWA trends and estimate details</a></p>
        </div>
        <div>
            <?php if (getPredictionSourceFamily($prediction_source) === 'model'): ?>
                <span class="status-pill custom-tooltip tooltip-bottom-right" tabindex="0" aria-label="AI-based prediction is active">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <?= htmlspecialchars(getPredictionSourceDisplayLabel($prediction_source)) ?>
                    <span class="tooltip-text" role="tooltip">The displayed estimates were generated using the UDM-RADAR AI model based on your available academic inputs.</span>
                </span>
            <?php elseif (getPredictionSourceFamily($prediction_source) === 'calculation'): ?>
                <span class="status-pill status-pill-muted custom-tooltip tooltip-bottom-right" tabindex="0" aria-label="Estimate based on current grades">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <?= htmlspecialchars(getPredictionSourceDisplayLabel($prediction_source)) ?>
                    <span class="tooltip-text" role="tooltip">This is a deterministic calculation from your recorded grades. The AI-based prediction model hasn't run for your account yet.</span>
                </span>
            <?php else: ?>
                <span class="status-pill status-pill-muted custom-tooltip tooltip-bottom-right" tabindex="0" aria-label="Insufficient data for a prediction">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Insufficient Data
                    <span class="tooltip-text" role="tooltip">There aren't enough recorded grades yet to generate a prediction or estimate.</span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; font-size: 0.82rem; color: var(--text-gray); display: flex; align-items: flex-start; gap: 12px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-blue); flex-shrink: 0; margin-top: 2px;" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 4px;">
                <span style="color: var(--text-dark); font-weight: 700; font-size: 0.85rem;">Decision-Support Advisory Notice</span>
                <?php if (!empty($explanation['is_provisional'])): ?>
                    <span style="background: rgba(217, 119, 6, 0.12); color: var(--risk-mod); font-size: 0.72rem; padding: 2px 8px; border-radius: 4px; font-weight: 600; border: 1px solid rgba(217, 119, 6, 0.25);">Provisional Estimate</span>
                <?php endif; ?>
            </div>
            <div style="line-height: 1.45; margin-bottom: 6px; color: var(--text-dark);"><?= htmlspecialchars($explanation['disclaimer']) ?></div>
            <div style="font-size: 0.76rem; color: var(--text-gray);">
                <strong>Coverage:</strong> <?= htmlspecialchars($explanation['coverage_summary']) ?>
                <span style="margin: 0 6px;">•</span>
                <strong>Freshness:</strong> <?= htmlspecialchars($explanation['freshness_label']) ?>
            </div>
        </div>
    </div>

    <div class="stat-grid dashboard-stat-grid" style="margin-bottom: 24px;">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Cumulative GWA</h4>
            <h2 id="gwa-card-value" style="color: var(--text-dark);"><?= $currentGwaDisplay ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Historical Cumulative GWA (Completed Semesters)</p>
        </div>
        <div class="stat-card" style="border-left-color: <?= $honor_color ?>;">
            <h4>Projected Semester GWA <?= !empty($explanation['is_provisional']) ? '<span style="font-size: 0.72rem; color: var(--risk-mod); font-weight: 700;">(Provisional)</span>' : '' ?></h4>
            <h2 style="color: <?= $honor_color ?>;"><?= $display_predicted_gwa !== null ? number_format($display_predicted_gwa, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">
                <?php if ($prediction_source !== null): ?>
                    Projection source: <?= htmlspecialchars(getPredictionSourceDisplayLabel($prediction_source)) ?><?= !empty($explanation['is_provisional']) ? ' (Provisional)' : '' ?><br>
                <?php endif; ?>
                <?php if ($display_honor !== null): ?>
                    Latin Honor Status: <strong style="color: <?= $honor_color ?>;"><?= htmlspecialchars($display_honor) ?></strong>
                <?php else: ?>
                    Latin Honor Status: <strong style="color: var(--text-gray);">N/A</strong>
                <?php endif; ?>
            </p>
        </div>
        <div class="stat-card" style="border-left-color: <?= $riskBg ?>;">
            <div class="stat-card-heading-with-info">
                <h4 style="margin: 0;">Overall Academic Risk</h4>
                <span class="custom-tooltip tooltip-top-left risk-info-icon" tabindex="0" aria-label="Risk explanation: <?= htmlspecialchars($riskTooltip) ?>" title="View risk details">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span class="tooltip-text" role="tooltip"><?= htmlspecialchars($riskTooltip) ?></span>
                </span>
            </div>
            <h2 style="color: <?= $riskBg ?>; font-size: 2rem; font-weight: 700; margin: 0;"><?= htmlspecialchars($display_risk ?? 'N/A') ?></h2>
            <div style="font-size: 0.76rem; color: var(--text-dark); margin-top: 6px; font-weight: 600; line-height: 1.35;">
                <?= htmlspecialchars($shortRiskSummary) ?>
            </div>
            <p style="font-size: 0.72rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Current Classification</p>
        </div>
    </div>

    <?php
    $isAtRiskGauge = $hasCurrentGwa && ($display_risk === 'HIGH' || $display_risk === 'MODERATE' || $current_gwa < 2.50);
    $currentPositionStatement = '';
    $accessibleGaugeSummary = '';
    if ($hasCurrentGwa) {
        $valFormatted = number_format($current_gwa, 2);
        if ($current_gwa < 3.25) {
            $bandStatement = "Your historical cumulative GWA is below the first configured academic-distinction range.";
        } elseif ($current_gwa < 3.50) {
            $bandStatement = "Your historical cumulative GWA is within the configured Dean’s List range.";
        } elseif ($current_gwa < 3.75) {
            $bandStatement = "Your historical cumulative GWA is within the configured Magna Cum Laude range.";
        } else {
            $bandStatement = "Your historical cumulative GWA is within the configured Summa Cum Laude range.";
        }
        $currentPositionStatement = $bandStatement . " This is guidance only. Official qualification requires complete institutional review.";
        $accessibleGaugeSummary = "Historical cumulative GWA: {$valFormatted} on a scale from 1.00 to 4.00. The configured Dean’s List range begins at 3.25, Magna Cum Laude begins at 3.50, and Summa Cum Laude begins at 3.75. {$bandStatement} This is guidance only. Official qualification requires complete institutional review.";
    } else {
        $currentPositionStatement = "No numeric final grades are currently recorded.";
        $accessibleGaugeSummary = "Historical cumulative GWA is not available because no numeric final grades have been recorded.";
    }
    ?>
    <div class="analysis-panels" style="gap: 24px; margin-bottom: 24px; align-items: stretch;">
        <div class="card" style="text-align: center; display: flex; flex-direction: column;">
            <div>
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; text-align: left; display: flex; align-items: center; gap: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-blue);"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
                    <span><?= !$hasCurrentGwa ? 'Cumulative GWA' : 'Academic Distinction' ?></span>
                </h3>
                <p id="gwa-gauge-description" style="text-align: left; color: var(--text-gray); font-size: 0.8rem; margin: 0;">
                    <?= !$hasCurrentGwa ? 'No numeric final grades are currently available.' : 'Shows your historical cumulative GWA in relation to the configured academic-distinction ranges. Official qualification remains subject to complete institutional criteria and authorized review.' ?>
                </p>
            </div>
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                <div style="position: relative; margin-top: 16px; height: 180px; width: 100%; display: flex; justify-content: center; align-items: center;">
                    <canvas id="honorGauge" role="img" aria-label="Cumulative GWA: <?= $currentGwaDisplay ?>" aria-describedby="gwa-gauge-description gwa-gauge-accessible-summary"></canvas>
                </div>
                <div id="gwa-gauge-accessible-summary" class="sr-only"><?= htmlspecialchars($accessibleGaugeSummary) ?></div>
                <div style="margin-top: 4px;">
                    <span id="gwa-gauge-value" style="font-size: 2rem; font-weight: 800; color: var(--text-dark);"><?= $currentGwaDisplay ?></span>
                    <br><span style="font-size: 0.8rem; color: var(--text-gray); font-weight: 600;">Historical Cumulative GWA</span>
                </div>
                <div style="display: flex; justify-content: center; gap: 12px 18px; margin-top: 14px; font-size: 0.78rem; font-weight: 600; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-dark);">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background-color: var(--primary-light, #2D9CDB); border: 1px solid rgba(0,0,0,0.1);" aria-hidden="true"></span>
                        Dean’s List · starts at 3.25
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-dark);">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background-color: var(--honor-magna, #1E4DB7);" aria-hidden="true"></span>
                        Magna Cum Laude · starts at 3.50
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-dark);">
                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background-color: var(--gold, #C9A227);" aria-hidden="true"></span>
                        Summa Cum Laude · starts at 3.75
                    </span>
                </div>
            </div>
        </div>

        <!-- FIXED: Removed padding-bottom: 0 and added position: relative for the scroll fade effect -->
        <div class="card" style="display: flex; flex-direction: column; overflow: hidden; position: relative; padding-bottom: 0;">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-blue);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                <span>Risk Factor Analysis</span>
            </h3>
            
            <div id="triage-scroll" class="triage-scroll" style="max-height: 400px; overflow-y: auto; padding-bottom: 24px; padding-right: 8px;">
                <div style="margin-bottom: 20px;">
                    <?php foreach ($risk_factors as $factor): 
                        $dotClass = match ($factor['type']) { 'danger' => 'risk-high', 'warning' => 'risk-mod', 'info' => 'accent', default => 'risk-low' };
                        $borderColor = match ($factor['type']) { 'danger' => 'var(--risk-high)', 'warning' => 'var(--risk-mod)', 'info' => 'var(--accent-blue)', default => 'var(--risk-low)' };
                        $bgTint = match ($factor['type']) { 'danger' => 'rgba(220, 38, 38, 0.1)', 'warning' => 'rgba(217, 119, 6, 0.1)', 'info' => 'var(--table-header-bg)', default => 'rgba(5, 150, 105, 0.1)' };
                    ?>
                    <div style="background: <?= $bgTint ?>; border-left: 3px solid <?= $borderColor ?>; padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; font-size: 0.85rem; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
                        <span class="status-dot <?= $dotClass ?>"></span>
                        <span><?= $factor['text'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-blue);"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span>Subject Triage (Focus Areas)</span>
                </h3>
                <?php if (empty($triage_alerts)): ?>
                    <p style="font-size: 0.85rem; color: var(--risk-low); font-weight: 600; display: flex; align-items: center; gap: 6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>No current subjects are showing a risk indicator.</span>
                    </p>
                <?php else: ?>
                    <div>
                        <?php foreach ($triage_alerts as $alert):
                            $alertBg = $alert['level'] === 'HIGH' ? 'rgba(220, 38, 38, 0.1)' : 'rgba(217, 119, 6, 0.1)';
                            $alertBorder = $alert['level'] === 'HIGH' ? 'rgba(220, 38, 38, 0.3)' : 'rgba(217, 119, 6, 0.3)';
                            $alertColor = $alert['level'] === 'HIGH' ? 'var(--risk-high)' : 'var(--risk-mod)';
                        ?>
                            <div style="background: <?= $alertBg ?>; padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; font-size: 0.85rem; color: <?= $alertColor ?>; border: 1px solid <?= $alertBorder ?>; display: flex; align-items: flex-start; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <div><?= $alert['text'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Dynamic Fade Indicator -->
            <div id="triage-fade" class="triage-fade"></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div class="subject-heading" style="margin-bottom: 16px;">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700;">Current Subjects & Predictions</h3>
            <button type="button" onclick="toggleCalculator()" style="background: var(--accent-blue); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 6px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="16" y1="14" x2="16" y2="18"></line><path d="M16 10h.01"></path><path d="M12 10h.01"></path><path d="M8 10h.01"></path><path d="M12 14h.01"></path><path d="M8 14h.01"></path><path d="M12 18h.01"></path><path d="M8 18h.01"></path></svg>
                <span>Open Grade Goal Calculator</span>
            </button>
        </div>
        <div class="data-table-scroll" role="region" aria-label="Current subjects and predictions" tabindex="0">
        <table class="student-subjects-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Code</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Subject Title</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Units</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Prelim</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Midterm</th>
                    <th style="padding: 12px; text-align: center; color: var(--accent-blue);">
                        Projected Subject Grade
                        <span class="custom-tooltip" tabindex="0" aria-label="A calculation based on your current Preliminary score and prior academic performance. Separate from the overall AI-based prediction." style="margin-left: 4px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: text-bottom;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            <span class="tooltip-text" role="tooltip">This is a calculation based on the currently recorded Preliminary score and prior academic performance, not the AI model — the AI model does not produce subject-by-subject predictions.</span>
                        </span>
                    </th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Subject Risk</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_subjects as $subj): 
                    $pRisk = $subj['prelim_point'] !== null ? computeRiskFromAvg($subj['prelim_point']) : null;
                    $mPoint = normalizeTermGrade($subj['midterm_raw']);
                    $mRisk = $mPoint !== null ? computeRiskFromAvg($mPoint) : null;
                    $fRisk = $subj['predicted_final'] !== null ? computeRiskFromAvg($subj['predicted_final']) : null;
                    
                    $prelimCol = match($pRisk) { 'HIGH' => 'var(--risk-high)', 'MODERATE' => 'var(--risk-mod)', 'LOW' => 'var(--risk-low)', default => 'var(--text-gray)' };
                    $midtermCol = match($mRisk) { 'HIGH' => 'var(--risk-high)', 'MODERATE' => 'var(--risk-mod)', 'LOW' => 'var(--risk-low)', default => 'var(--text-gray)' };
                    $finalCol  = match($fRisk) { 'HIGH' => 'var(--risk-high)', 'MODERATE' => 'var(--risk-mod)', 'LOW' => 'var(--risk-low)', default => 'var(--text-gray)' };
                    
                    $rowRiskBg = match($subj['final_risk']) { 'HIGH' => 'var(--risk-high)', 'MODERATE' => 'var(--risk-mod)', 'LOW' => 'var(--risk-low)', default => 'var(--text-gray)' };
                    $subjInquirePeriod = $subj['prefinal_raw'] !== null ? 'prefinal' : ($subj['midterm_raw'] !== null ? 'midterm' : 'prelim');
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td class="card-cell-code" data-label="Code" style="padding: 12px; font-weight: 600; color: var(--accent-blue);"><?= htmlspecialchars($subj['code']) ?></td>
                    <td class="card-cell-title" data-label="Subject Title" style="padding: 12px; font-weight: 500; color: var(--text-dark);"><?= htmlspecialchars(displaySubjectTitle($subj['code'], $subj['title'])) ?></td>
                    <td data-label="Units" style="padding: 12px; text-align: center; color: var(--text-dark);"><?= htmlspecialchars($subj['units']) ?></td>
                    
                    <td data-label="Prelim" style="padding: 12px; text-align: center; font-weight: 600; color: <?= $prelimCol ?>;">
                        <?php if ($subj['prelim_raw'] !== null): ?>
                            <?= round((float)$subj['prelim_raw']) ?>%
                        <?php else: ?>
                            <span style="color: var(--text-gray);">—</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Midterm" style="padding: 12px; text-align: center; font-weight: 600; color: <?= $midtermCol ?>;">
                        <?php if ($subj['midterm_raw'] !== null): ?>
                            <?= round((float)$subj['midterm_raw']) ?>%
                        <?php else: ?>
                            <span style="color: var(--text-gray);">—</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="Projected Grade" style="padding: 12px; text-align: center; font-weight: 700; color: <?= $finalCol ?>;">
                        <?= $subj['predicted_final'] !== null ? number_format($subj['predicted_final'], 2) : '<span style="color: var(--text-gray);">—</span>' ?>
                    </td>
                    <td data-label="Subject Risk" style="padding: 12px; text-align: center;">
                        <span style="background: <?= $rowRiskBg ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                            <?= $subj['predicted_final'] !== null ? $subj['final_risk'] : 'N/A' ?>
                        </span>
                    </td>
                    <td data-label="Action" style="padding: 12px; text-align: center;">
                        <?php if (!empty($subj['subject_id'])): ?>
                            <a href="feedback.php?action=new&category=grade_concern&subject_id=<?= (int)$subj['subject_id'] ?>&period=<?= $subjInquirePeriod ?>"
                               style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; background: rgba(30, 77, 183, 0.08); color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.25); border-radius: 6px; font-size: 0.78rem; font-weight: 600; text-decoration: none; transition: all 0.2s;"
                               title="Inquire about this grade in Feedback & Support">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
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
        <p style="font-size: 0.85rem; color: var(--text-gray); margin-top: 12px; margin-bottom: 0;">
            Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.
        </p>
    </div>

    <div id="calculator-section" class="card" style="display: none; border: 2px solid var(--accent-blue); background-color: var(--card-bg);">
        <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/goal-calculator.css?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/css/goal-calculator.css'), 0, 12) ?>">
        <div class="calculator-heading" style="margin-bottom: 16px;">
            <div>
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-blue);" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="16" y1="14" x2="16" y2="18"></line><path d="M16 10h.01"></path><path d="M12 10h.01"></path><path d="M8 10h.01"></path><path d="M12 14h.01"></path><path d="M8 14h.01"></path><path d="M12 18h.01"></path><path d="M8 18h.01"></path></svg>
                    <span>Grade Goal Calculator</span>
                </h3>
                <p style="color: var(--text-gray); font-size: 0.85rem; margin: 0;">Input percentage grades to compute your final point grade, or pick a Target to back-calculate.</p>
            </div>
            <div class="calculator-actions">
                <div style="background: var(--bg-color); padding: 10px 20px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <span style="font-size: 0.75rem; color: var(--text-gray); font-weight: 700; text-transform: uppercase;">Scenario Semester GWA</span><br>
                    <span id="projected-gwa" style="font-size: 1.8rem; font-weight: 800; color: var(--accent-blue);">N/A</span>
                </div>
                <button type="button" onclick="resetCalculator()" title="Clear all temporary scenarios and restore recorded grades" style="background: var(--border-color); color: var(--text-dark); border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; font-family: inherit;">Reset</button>
                <button type="button" onclick="toggleCalculator()" style="background: var(--border-color); color: var(--text-dark); border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; font-family: inherit; display: inline-flex; align-items: center; gap: 4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    <span>Close</span>
                </button>
            </div>
        </div>

        <p id="calculator-goal-help">Target final-grade options above the highest mathematically reachable point grade are disabled using the available percentages and remaining period weights. Scenario Semester GWA is unit-weighted. Prototype performance on synthetic academic records.</p>
        <div class="data-table-scroll" role="region" aria-label="Grade goal calculator" tabindex="0">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: center; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Code</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Subject Title</th>
                    <th style="padding: 12px; color: var(--text-dark);">Units</th>
                    <th style="padding: 12px; color: var(--text-gray);">Prelim % (30%)</th>
                    <th style="padding: 12px; color: var(--text-gray);">Midterm % (30%)</th>
                    <th style="padding: 12px; color: var(--text-gray);">Pre-Final % (40%)</th>
                    <th style="padding: 12px; width: 140px; color: var(--accent-blue);">Target Final Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_subjects as $idx => $subj): 
                    $p_period = goalCalculatorPeriod($subj['prelim_raw']);
                    $m_period = goalCalculatorPeriod($subj['midterm_raw']);
                    $pf_period = goalCalculatorPeriod($subj['prefinal_raw']);
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);" class="calc-row">
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark); text-align: left;"><?= htmlspecialchars($subj['code']) ?></td>
                    <td style="padding: 12px; font-weight: 500; color: var(--text-dark); text-align: left;"><?= htmlspecialchars(displaySubjectTitle($subj['code'], $subj['title'])) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);" class="calc-units"><?= htmlspecialchars($subj['units']) ?></td>
                    
                    <?php foreach (['prelim'=>$p_period, 'midterm'=>$m_period, 'prefinal'=>$pf_period] as $periodName=>$period): ?>
                    <td style="padding: 12px;"><input type="number" min="0" max="100" step="0.01" class="form-input calc-term-input calc-<?= $periodName ?>" aria-label="<?= htmlspecialchars(ucfirst($periodName) . ' percentage for ' . $subj['code']) ?>" value="<?= htmlspecialchars($period['value']) ?>" data-period-state="<?= $period['state'] ?>" <?= $period['state'] !== 'remaining' ? 'disabled' : '' ?> <?= $period['state'] === 'unavailable' ? 'aria-invalid="true"' : '' ?> oninput="computeRowFinal(this)" style="background: var(--card-bg); color: var(--text-dark); text-align: center; padding: 6px;"></td>
                    <?php endforeach; ?>
                    <td style="padding: 12px; border-left: 2px dashed var(--border-color); background: var(--bg-color);">
                        <select class="calc-final-input" aria-label="Target final grade for <?= htmlspecialchars($subj['code']) ?>" aria-describedby="calculator-goal-help calc-status-<?= (int)$idx ?>" style="width: 100%; padding: 8px; border: 1px solid var(--accent-blue); background: var(--card-bg); color: var(--accent-blue); border-radius: 4px; font-family: inherit; font-weight: 700;" onchange="seekGrades(this)">
                            <?= renderTargetOptions($valid_grades) ?>
                        </select>
                        <div class="calc-goal-status" id="calc-status-<?= (int)$idx ?>" role="status" aria-live="polite" aria-atomic="true"><span class="calc-goal-limit"></span><output class="calc-row-result"></output></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script id="goal-calculator-config" type="application/json"><?= json_encode($calculatorConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= BASE_URL ?>assets/js/goal-calculator.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/js/goal-calculator.js'), 0, 12) ?>"></script>
<script src="<?= BASE_URL ?>assets/js/goal-calculator-ui.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/js/goal-calculator-ui.js'), 0, 12) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function getThemeColors() {
    const root = getComputedStyle(document.documentElement);
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
        // General distinction range arc (visible and lighter slate-blue)
        base: isDark ? '#334E68' : '#CBD5E1',
        // Dean's List: Light blue
        deansList: root.getPropertyValue('--primary-light').trim() || (isDark ? '#7DD3FC' : '#2D9CDB'),
        // Magna Cum Laude: Deep royal blue
        magna: root.getPropertyValue('--honor-magna').trim() || (isDark ? '#818CF8' : '#1E4DB7'),
        // Summa Cum Laude: Restrained gold or orange-gold
        summa: root.getPropertyValue('--gold').trim() || (isDark ? '#F4C95D' : '#C9A227'),
        text: root.getPropertyValue('--text-dark').trim() || (isDark ? '#F4FAFC' : '#102A43')
    };
}

let themeColors = getThemeColors();
const currentGwaForGauge = <?= json_encode($hasCurrentGwa ? round($current_gwa, 2) : null) ?>;
const GAUGE_MIN = 1.00;
const GAUGE_MAX = 4.00;
const GAUGE_ROTATION = 270;
const GAUGE_CIRCUMFERENCE = 180;
const THRESHOLDS = [3.25, 3.50, 3.75];

const needlePlugin = {
    id: 'gwaNeedle',
    afterDraw(chart) {
        if (currentGwaForGauge === null) return;
        const meta = chart.getDatasetMeta(0);
        if (!meta.data || !meta.data[0]) return;
        const arc = meta.data[0];
        const { x: cx, y: cy, outerRadius } = arc.getProps(['x', 'y', 'outerRadius'], true);
        const needleLength = outerRadius * 0.88;
        const clamped = Math.max(GAUGE_MIN, Math.min(GAUGE_MAX, currentGwaForGauge));
        const fraction = (clamped - GAUGE_MIN) / (GAUGE_MAX - GAUGE_MIN);
        const chartJsAngleDeg = GAUGE_ROTATION + (GAUGE_CIRCUMFERENCE * fraction);
        const canvasAngleDeg = chartJsAngleDeg - 90;
        const angleRad = canvasAngleDeg * Math.PI / 180;
        const tipX = cx + needleLength * Math.cos(angleRad);
        const tipY = cy + needleLength * Math.sin(angleRad);
        const { ctx } = chart;
        ctx.save();
        const needleColor = getComputedStyle(document.documentElement).getPropertyValue('--text-dark').trim() || '#102A43';
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(tipX, tipY);
        ctx.lineWidth = 3;
        ctx.strokeStyle = needleColor;
        ctx.lineCap = 'round';
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(cx, cy, 6, 0, Math.PI * 2);
        ctx.fillStyle = needleColor;
        ctx.fill();
        ctx.restore();
    }
};

const thresholdPlugin = {
    id: 'distinctionThresholdTicks',
    afterDraw(chart) {
        const meta = chart.getDatasetMeta(0);
        if (!meta.data || !meta.data[0]) return;
        const arc = meta.data[0];
        const { x: cx, y: cy, innerRadius, outerRadius } = arc.getProps(['x', 'y', 'innerRadius', 'outerRadius'], true);
        const { ctx } = chart;
        const strokeColor = getComputedStyle(document.documentElement).getPropertyValue('--text-dark').trim() || '#102A43';

        THRESHOLDS.forEach(thresh => {
            const fraction = (thresh - GAUGE_MIN) / (GAUGE_MAX - GAUGE_MIN);
            const chartJsAngleDeg = GAUGE_ROTATION + (GAUGE_CIRCUMFERENCE * fraction);
            const canvasAngleDeg = chartJsAngleDeg - 90;
            const angleRad = canvasAngleDeg * Math.PI / 180;

            const innerX = cx + (innerRadius - 3) * Math.cos(angleRad);
            const innerY = cy + (innerRadius - 3) * Math.sin(angleRad);
            const outerX = cx + (outerRadius + 5) * Math.cos(angleRad);
            const outerY = cy + (outerRadius + 5) * Math.sin(angleRad);

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(innerX, innerY);
            ctx.lineTo(outerX, outerY);
            ctx.lineWidth = 2;
            ctx.strokeStyle = strokeColor;
            ctx.lineCap = 'round';
            ctx.stroke();

            ctx.beginPath();
            ctx.arc(outerX, outerY, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = strokeColor;
            ctx.fill();
            ctx.restore();
        });
    }
};

const ctxGauge = document.getElementById('honorGauge').getContext('2d');
const honorGaugeChart = new Chart(ctxGauge, {
    type: 'doughnut',
    data: {
        labels: ['Muted arc', 'Dean’s List', 'Magna Cum Laude', 'Summa Cum Laude'],
        datasets: [{
            data: [2.25, 0.25, 0.25, 0.25], // 1.00-3.25 (75%), 3.25-3.50 (8.33%), 3.50-3.75 (8.33%), 3.75-4.00 (8.33%)
            backgroundColor: [themeColors.base, themeColors.deansList, themeColors.magna, themeColors.summa],
            borderWidth: 0,
            circumference: GAUGE_CIRCUMFERENCE,
            rotation: GAUGE_ROTATION
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
        },
        animation: { animateRotate: true, animateScale: false }
    },
    plugins: [needlePlugin, thresholdPlugin]
});

const observer = new MutationObserver(() => {
    themeColors = getThemeColors();
    const isReducedMotion = document.documentElement.getAttribute('data-motion') === 'reduce';
    honorGaugeChart.options.animation = !isReducedMotion;
    honorGaugeChart.data.datasets[0].backgroundColor[0] = themeColors.base;
    honorGaugeChart.data.datasets[0].backgroundColor[1] = themeColors.deansList;
    honorGaugeChart.data.datasets[0].backgroundColor[2] = themeColors.magna;
    honorGaugeChart.data.datasets[0].backgroundColor[3] = themeColors.summa;
    honorGaugeChart.update('none');
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-color-style', 'data-theme', 'data-motion'] });

// Scrolling Fade Observer Script
(function () {
    const scrollEl = document.getElementById('triage-scroll');
    const fadeEl = document.getElementById('triage-fade');
    if (!scrollEl || !fadeEl) return; 

    function updateFade() {
        const distanceFromBottom = scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight;
        const atBottom = distanceFromBottom < 2; 
        const overflowing = scrollEl.scrollHeight > scrollEl.clientHeight;
        fadeEl.classList.toggle('hidden', atBottom || !overflowing);
    }

    scrollEl.addEventListener('scroll', updateFade, { passive: true });
    window.addEventListener('resize', updateFade);
    updateFade();
})();
</script>

<?php require_once '../includes/footer.php'; ?>
