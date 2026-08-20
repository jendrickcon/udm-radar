<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
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

$stmtHist = $db->prepare("
    SELECT g.final_grade, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 0 AND g.final_grade IS NOT NULL
");
$stmtHist->execute([$user['id']]);
$historical_rows = array_map(
    fn($r) => ['grade' => (float) $r['final_grade'], 'units' => (int) $r['units']],
    $stmtHist->fetchAll()
);
$historical_gwa = computeWeightedGWA($historical_rows); 
$current_gwa = $historical_gwa ?? (float) ($profile['current_gwa'] ?? 0);

// --- Fetch ML Prediction from Database ---
$stmtPred = $db->prepare("
    SELECT predicted_gwa, risk_level, latin_honor, prediction_source 
    FROM predictions 
    WHERE student_id = ? 
    ORDER BY generated_at DESC 
    LIMIT 1
");
$stmtPred->execute([$user['id']]);
$ml_prediction = $stmtPred->fetch(PDO::FETCH_ASSOC);

// 2. Fetch Current Semester Subjects & All Grades
$stmtCurr = $db->prepare("
    SELECT s.code, s.title, s.units, g.prelim, g.midterm, g.prefinal, g.final_grade
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
    $risk_factors[] = ['type' => 'danger', 'text' => 'Historical: Irregular enrollment status detected, which statistically increases graduation delay risk.'];
}

foreach ($current_subjects as &$subj) {
    $subj['prelim_raw'] = $subj['prelim'];
    $subj['midterm_raw'] = $subj['midterm'];
    $subj['prefinal_raw'] = $subj['prefinal'];
    
    $prelimPoint = normalizeTermGrade($subj['prelim']);
    $predicted_final = predictFinalGradeHeuristic($prelimPoint, $historical_gwa);
    
    $subj['prelim_point'] = $prelimPoint;
    $subj['predicted_final'] = $predicted_final;
    $subj['final_risk'] = $predicted_final !== null ? computeRiskFromAvg($predicted_final) : 'LOW';
    
    if ($predicted_final !== null) {
        $prediction_rows[] = ['grade' => $predicted_final, 'units' => (int) $subj['units']];
    }

    if ($prelimPoint !== null) {
        $subjRisk = computeRiskFromAvg($prelimPoint);
        if ($subjRisk === 'HIGH') {
            $triage_alerts[] = "🚨 <strong>High Risk:</strong> Your grade in <strong>{$subj['title']}</strong> is " . round((float)$subj['prelim_raw']) . "% (" . number_format($prelimPoint, 2) . "). A significant intervention is required.";
            $at_risk_count++;
        } elseif ($subjRisk === 'MODERATE') {
            $triage_alerts[] = "⚠️ <strong>Moderate Risk:</strong> Your grade in <strong>{$subj['title']}</strong> is " . round((float)$subj['prelim_raw']) . "% (" . number_format($prelimPoint, 2) . "). This is dragging down your projected GWA.";
            $at_risk_count++;
        }
    }
}
unset($subj);

// --- OVERRIDE LOGIC: Prefer ML data over local heuristics ---
$heuristic_gwa = computeWeightedGWA($prediction_rows) ?? $historical_gwa ?? 0.0;
$heuristic_risk = computeRiskFromAvg($heuristic_gwa);

$display_predicted_gwa = $ml_prediction && $ml_prediction['predicted_gwa'] !== null ? (float)$ml_prediction['predicted_gwa'] : $heuristic_gwa;
$display_risk = $ml_prediction && $ml_prediction['risk_level'] !== null ? $ml_prediction['risk_level'] : $heuristic_risk;
$display_honor = $ml_prediction && $ml_prediction['latin_honor'] !== null ? $ml_prediction['latin_honor'] : getLatinHonor($display_predicted_gwa, hasDisqualifyingGrade($user['id'], $db));
$prediction_source = $ml_prediction ? $ml_prediction['prediction_source'] : 'heuristic (local)';
// -------------------------------------------------------------

if ($at_risk_count > 0) {
    $risk_factors[] = ['type' => 'warning', 'text' => "Current Term: You are below the Very Satisfactory threshold (< 2.50) in {$at_risk_count} current subject(s)."];
}
if ($historical_gwa !== null && $display_predicted_gwa > 0) {
    $diff = round($display_predicted_gwa - $historical_gwa, 2);
    
    // In UDM, 4.00 is highest. Positive diff is improvement.
    if ($diff < 0) { 
        $drop = number_format(abs($diff), 2);
        $risk_factors[] = ['type' => 'warning', 'text' => "Trajectory: Model projects a {$drop} point drop in your GWA based on current pacing."];
    } elseif ($diff > 0) {
        $gain = number_format($diff, 2);
        $risk_factors[] = ['type' => 'success', 'text' => "Trajectory: Excellent pacing! Model projects a {$gain} point increase over your historical GWA."];
    } else {
        $risk_factors[] = ['type' => 'info', 'text' => "Trajectory: Consistent pacing. You are projected to perfectly maintain your historical GWA."];
    }
}

$gradedSubjects = array_filter($current_subjects, fn($s) => $s['prelim_point'] !== null);
if (!empty($gradedSubjects)) {
    $lowestGrade = min(array_column($gradedSubjects, 'prelim_point')); 
    $weakestSubjects = array_values(array_filter(
        $gradedSubjects,
        fn($s) => $s['prelim_point'] == $lowestGrade
    ));

    $titles = array_column($weakestSubjects, 'title');
    $subjectList = count($titles) > 1
        ? implode(', ', array_slice($titles, 0, -1)) . ' and ' . end($titles)
        : $titles[0];
    $plural = count($titles) > 1 ? 'these subjects' : 'this subject';
    $lowestRaw = min(array_column($weakestSubjects, 'prelim_raw')); 

    if (computeRiskFromAvg($lowestGrade) !== 'LOW') {
        $risk_factors[] = [
            'type' => 'info',
            'text' => "Focus Recommendation: Your weakest current grade is in <strong>{$subjectList}</strong> at <strong>" . round((float)$lowestRaw) . "%</strong>. Prioritize study time on {$plural} first!",
        ];
    } else {
        $risk_factors[] = [
            'type' => 'info',
            'text' => "Optimization Strategy: You are performing safely across the board! However, your lowest grade is in <strong>{$subjectList}</strong> at <strong>" . round((float)$lowestRaw) . "%</strong>. Direct your extra effort toward {$plural}.",
        ];
    }
}

if (empty($risk_factors)) {
    $risk_factors[] = ['type' => 'success', 'text' => 'Positive: No immediate risk factors detected. Consistent performance maintained.'];
}

$riskBg = match($display_risk) {
    'HIGH' => 'var(--risk-high)',
    'MODERATE' => 'var(--risk-mod)',
    'LOW' => 'var(--risk-low)',
    default => 'var(--text-gray)'
}; 

// Tooltips accurately reflect classification, not fake probability
$riskTooltip = "";
if ($display_risk === 'HIGH') {
    $riskTooltip = $ml_prediction 
        ? "High Risk: The AI model evaluated your trajectory and classified it as High Risk, typically driven by historical failed subjects or a low GWA trajectory."
        : "High Risk: Heuristic analysis flagged your projected GWA as critically low (< 1.75) or detected multiple past failures.";
} elseif ($display_risk === 'MODERATE') {
    $riskTooltip = $ml_prediction 
        ? "Moderate Risk: The AI model evaluated your trajectory as Moderate Risk. Minor interventions and focus are recommended to secure your standing."
        : "Moderate Risk: Heuristic analysis shows your projected GWA is hovering near the safe threshold. Consistent effort is needed.";
} elseif ($display_risk === 'LOW') {
    $riskTooltip = $ml_prediction 
        ? "Low Risk: Excellent. The AI model projects a highly stable trajectory."
        : "Low Risk: Heuristic analysis shows your projected GWA is well within the safe, highly satisfactory threshold.";
} else {
    $riskTooltip = "No prediction data available yet.";
}

$honor_color = match ($display_honor) {
    'Summa Cum Laude' => '#b45309',
    'Magna Cum Laude'  => '#1d4ed8',
    'Cum Laude'        => 'var(--accent-blue)',
    default            => 'var(--text-gray)',
};

$valid_grades = [4.00, 3.75, 3.50, 3.25, 3.00, 2.75, 2.50, 2.25, 2.00, 1.75, 1.50, 1.25, 1.00];
function renderTargetOptions($valid_grades) {
    $html = '<option value="">—</option>';
    foreach ($valid_grades as $g) {
        $valStr = number_format($g, 2);
        $html .= "<option value=\"$valStr\">$valStr</option>";
    }
    return $html;
}

$pageTitle = 'Dashboard';
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
    <div class="header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Analytics Dashboard</h1>
            <p style="color: var(--text-gray); font-size: 0.95rem;">Decision-support center and academic estimation.</p>
        </div>
        <div>
            <?php if ($prediction_source === 'decision_tree'): ?>
                <span class="status-pill custom-tooltip tooltip-bottom-right" tabindex="0" aria-label="Decision Tree prediction is active">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    AI Decision Tree Active
                    <span class="tooltip-text" role="tooltip">The displayed estimates were generated using the UDM-RADAR Decision Tree model based on the available academic inputs.</span>
                </span>
            <?php else: ?>
                <span class="status-pill status-pill-muted custom-tooltip tooltip-bottom-right" tabindex="0" aria-label="Heuristic analysis is active">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Heuristic Analysis Active
                    <span class="tooltip-text" role="tooltip">The displayed estimates were generated using the system's rule-based academic calculations because no current Decision Tree prediction was available.</span>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-grid dashboard-stat-grid" style="margin-bottom: 24px;">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Cumulative GWA</h4>
            <h2 style="color: var(--text-dark);"><?= $current_gwa > 0 ? number_format($current_gwa, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Current Academic Standing</p>
        </div>
        <div class="stat-card" style="border-left-color: <?= $honor_color ?>;">
            <h4>Predicted Final GWA</h4>
            <h2 style="color: <?= $honor_color ?>;"><?= $display_predicted_gwa > 0 ? number_format($display_predicted_gwa, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">
                Latin Honor Status: <strong style="color: <?= $honor_color ?>;"><?= htmlspecialchars($display_honor) ?></strong>
            </p>
        </div>
        <div class="stat-card" style="border-left-color: <?= $riskBg ?>;">
            <div class="stat-card-heading-with-info">
                <h4 style="margin: 0;">Overall Academic Risk</h4>
                <span class="custom-tooltip tooltip-top-left risk-info-icon" tabindex="0" aria-label="<?= htmlspecialchars($riskTooltip) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span class="tooltip-text" role="tooltip"><?= htmlspecialchars($riskTooltip) ?></span>
                </span>
            </div>
            <h2 style="color: <?= $riskBg ?>; font-size: 2rem; font-weight: 700; margin: 0;"><?= htmlspecialchars($display_risk) ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Current Classification</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="text-align: center;">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; text-align: left;">🎯 Honor Track Proximity</h3>
            <p style="text-align: left; color: var(--text-gray); font-size: 0.8rem; margin: 0 0 16px;">
                Shows where your current GWA falls on the 1.00–4.00 scale relative to Latin Honor cutoffs.
            </p>
            <div style="position: relative; height: 180px; width: 100%; display: flex; justify-content: center; align-items: center;">
                <canvas id="honorGauge"></canvas>
            </div>
            <div style="margin-top: 4px;">
                <span style="font-size: 2rem; font-weight: 800; color: var(--text-dark);"><?= $current_gwa > 0 ? number_format($current_gwa, 2) : '0.00' ?></span>
                <br><span style="font-size: 0.8rem; color: var(--text-gray); font-weight: 600;">Current GWA</span>
            </div>
            <div style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; font-size: 0.75rem; font-weight: 600;">
                <span style="color: var(--accent-blue);">● Cum Laude (<?= number_format(CUM_LAUDE, 2) ?>)</span>
                <span style="color: #1d4ed8;">● Magna (<?= number_format(MAGNA_CUM_LAUDE, 2) ?>)</span>
                <span style="color: #b45309;">● Summa (<?= number_format(SUMMA_CUM_LAUDE, 2) ?>)</span>
            </div>
        </div>

        <div class="card">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">🧠 Risk Factor Analysis</h3>
            <div style="margin-bottom: 20px;">
                <?php foreach ($risk_factors as $factor): 
                    $icon = match ($factor['type']) { 'danger' => '🔴', 'warning' => '🟠', 'info' => '🎯', default => '🟢' };
                    $borderColor = match ($factor['type']) { 'danger' => 'var(--risk-high)', 'warning' => 'var(--risk-mod)', 'info' => 'var(--accent-blue)', default => 'var(--risk-low)' };
                    $bgTint = match ($factor['type']) { 'danger' => 'rgba(220, 38, 38, 0.1)', 'warning' => 'rgba(217, 119, 6, 0.1)', 'info' => 'var(--table-header-bg)', default => 'rgba(5, 150, 105, 0.1)' };
                ?>
                <div style="background: <?= $bgTint ?>; border-left: 3px solid <?= $borderColor ?>; padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; font-size: 0.85rem; color: var(--text-dark);">
                    <?= $icon ?> <?= $factor['text'] ?>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 12px;">📊 Subject Triage (Focus Areas)</h3>
            <?php if (empty($triage_alerts)): ?>
                <p style="font-size: 0.85rem; color: var(--risk-low); font-weight: 600;">✓ All current subjects are within safe thresholds.</p>
            <?php else: ?>
                <?php foreach ($triage_alerts as $alert): ?>
                    <div style="background: rgba(220, 38, 38, 0.1); padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; font-size: 0.85rem; color: var(--risk-high); border: 1px solid rgba(220, 38, 38, 0.3);">
                        <?= $alert ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700;">Current Subjects & Predictions</h3>
            <button onclick="toggleCalculator()" style="background: var(--accent-blue); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit;">
                🎯 Open Grade Goal Calculator
            </button>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Code</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Subject Title</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Units</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Current Prelim</th>
                    <th style="padding: 12px; text-align: center; color: var(--accent-blue);">
                        Heuristic Subject Estimate
                        <span class="custom-tooltip" tabindex="0" aria-label="Uses current Preliminary performance and historical GWA. Separate from the overall ML prediction." style="margin-left: 4px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align: text-bottom;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            <span class="tooltip-text" role="tooltip">This subject-level estimate uses the student's current Preliminary performance and historical weighted GWA. It is separate from the overall Decision Tree GWA prediction.</span>
                        </span>
                    </th>
                    <th style="padding: 12px; text-align: center; color: var(--text-dark);">Subject Risk</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_subjects as $subj): 
                    $pRisk = $subj['prelim_point'] !== null ? computeRiskFromAvg($subj['prelim_point']) : 'LOW';
                    $fRisk = $subj['predicted_final'] !== null ? computeRiskFromAvg($subj['predicted_final']) : 'LOW';
                    
                    $prelimCol = $pRisk === 'HIGH' ? 'var(--risk-high)' : ($pRisk === 'MODERATE' ? 'var(--risk-mod)' : 'var(--risk-low)');
                    $finalCol  = $fRisk === 'HIGH' ? 'var(--risk-high)' : ($fRisk === 'MODERATE' ? 'var(--risk-mod)' : 'var(--risk-low)');
                    
                    $rowRiskBg = match($subj['final_risk']) { 'HIGH' => 'var(--risk-high)', 'MODERATE' => 'var(--risk-mod)', 'LOW' => 'var(--risk-low)', default => 'var(--text-gray)' };
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($subj['code']) ?></td>
                    <td style="padding: 12px; font-weight: 500; color: var(--text-dark);"><?= htmlspecialchars($subj['title']) ?></td>
                    <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= htmlspecialchars($subj['units']) ?></td>
                    
                    <td style="padding: 12px; text-align: center; font-weight: 600; color: <?= $prelimCol ?>;">
                        <?php if ($subj['prelim_raw'] !== null): ?>
                            <?= round((float)$subj['prelim_raw']) ?>% <br>
                            <span style="font-size: 0.75rem; color: var(--text-gray);">(<?= number_format($subj['prelim_point'], 2) ?>)</span>
                        <?php else: ?>
                            <span style="color: var(--text-gray);">—</span>
                        <?php endif; ?>
                    </td>

                    <td style="padding: 12px; text-align: center; font-weight: 700; color: <?= $finalCol ?>;">
                        <?= $subj['predicted_final'] !== null ? number_format($subj['predicted_final'], 2) : '<span style="color: var(--text-gray);">—</span>' ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="background: <?= $rowRiskBg ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                            <?= $subj['predicted_final'] !== null ? $subj['final_risk'] : 'N/A' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="calculator-section" class="card" style="display: none; border: 2px solid var(--accent-blue); background-color: var(--card-bg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700;">🎯 Grade Goal Calculator</h3>
                <p style="color: var(--text-gray); font-size: 0.85rem; margin: 0;">Input percentage grades to compute your final point grade, or pick a Target to back-calculate.</p>
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <div style="background: var(--bg-color); padding: 10px 20px; border-radius: 8px; text-align: center; border: 1px solid var(--border-color);">
                    <span style="font-size: 0.75rem; color: var(--text-gray); font-weight: 700; text-transform: uppercase;">Scenario Semester GWA</span><br>
                    <span id="projected-gwa" style="font-size: 1.8rem; font-weight: 800; color: var(--accent-blue);">0.00</span>
                </div>
                <button onclick="toggleCalculator()" style="background: var(--border-color); color: var(--text-dark); border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; font-family: inherit;">
                    ✕ Close
                </button>
            </div>
        </div>

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
                    $p_val  = $subj['prelim_raw'] !== null ? round((float)$subj['prelim_raw']) : '';
                    $m_val  = $subj['midterm_raw'] !== null ? round((float)$subj['midterm_raw']) : '';
                    $pf_val = $subj['prefinal_raw'] !== null ? round((float)$subj['prefinal_raw']) : '';
                    $p_bg  = $p_val  !== '' ? 'var(--bg-color)' : 'var(--card-bg)';
                    $m_bg  = $m_val  !== '' ? 'var(--bg-color)' : 'var(--card-bg)';
                    $pf_bg = $pf_val !== '' ? 'var(--bg-color)' : 'var(--card-bg)';
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);" class="calc-row">
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark); text-align: left;"><?= htmlspecialchars($subj['code']) ?></td>
                    <td style="padding: 12px; font-weight: 500; color: var(--text-dark); text-align: left;"><?= htmlspecialchars($subj['title']) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);" class="calc-units"><?= htmlspecialchars($subj['units']) ?></td>
                    
                    <td style="padding: 12px;"><input type="number" min="0" max="100" class="form-input calc-term-input calc-prelim" value="<?= $p_val ?>" <?= $p_val !== '' ? 'disabled' : '' ?> oninput="computeRowFinal(this)" style="background: <?= $p_bg ?>; color: var(--text-dark); text-align: center; padding: 6px;"></td>
                    <td style="padding: 12px;"><input type="number" min="0" max="100" class="form-input calc-term-input calc-midterm" value="<?= $m_val ?>" <?= $m_val !== '' ? 'disabled' : '' ?> oninput="computeRowFinal(this)" style="background: <?= $m_bg ?>; color: var(--text-dark); text-align: center; padding: 6px;"></td>
                    <td style="padding: 12px;"><input type="number" min="0" max="100" class="form-input calc-term-input calc-prefinal" value="<?= $pf_val ?>" <?= $pf_val !== '' ? 'disabled' : '' ?> oninput="computeRowFinal(this)" style="background: <?= $pf_bg ?>; color: var(--text-dark); text-align: center; padding: 6px;"></td>
                    <td style="padding: 12px; border-left: 2px dashed var(--border-color); background: var(--bg-color);">
                        <select class="calc-final-input" style="width: 100%; padding: 8px; border: 1px solid var(--accent-blue); background: var(--card-bg); color: var(--accent-blue); border-radius: 4px; font-family: inherit; font-weight: 700;" onchange="seekGrades(this)">
                            <?= renderTargetOptions($valid_grades) ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const WEIGHT_PRELIM = <?= WEIGHT_PRELIM ?? 0.30 ?>;
const WEIGHT_MIDTERM = <?= WEIGHT_MIDTERM ?? 0.30 ?>;
const WEIGHT_PREFINAL = <?= WEIGHT_PREFINAL ?? 0.40 ?>;

function toggleCalculator() {
    const calcDiv = document.getElementById('calculator-section');
    if (calcDiv.style.display === 'none') {
        calcDiv.style.display = 'block';
        calcDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelectorAll('.calc-prelim').forEach(el => computeRowFinal(el));
    } else {
        calcDiv.style.display = 'none';
    }
}

function convertPercentageToPoint(pct) {
    if (pct >= 99) return 4.00; if (pct >= 97) return 3.75; if (pct >= 95) return 3.50; if (pct >= 92) return 3.25;
    if (pct >= 90) return 3.00; if (pct >= 88) return 2.75; if (pct >= 86) return 2.50; if (pct >= 84) return 2.25;
    if (pct >= 82) return 2.00; if (pct >= 80) return 1.75; if (pct >= 78) return 1.50; if (pct >= 76) return 1.25;
    if (pct >= 75) return 1.00; return 0.00;
}

function getMinPercentageForPoint(point) {
    if (point >= 4.00) return 99; if (point >= 3.75) return 97; if (point >= 3.50) return 95; if (point >= 3.25) return 92;
    if (point >= 3.00) return 90; if (point >= 2.75) return 88; if (point >= 2.50) return 86; if (point >= 2.25) return 84;
    if (point >= 2.00) return 82; if (point >= 1.75) return 80; if (point >= 1.50) return 78; if (point >= 1.25) return 76;
    if (point >= 1.00) return 75; return 0;
}

function computeRowFinal(inputElem) {
    const row = inputElem.closest('.calc-row');
    const pStr = row.querySelector('.calc-prelim').value;
    const mStr = row.querySelector('.calc-midterm').value;
    const pfStr = row.querySelector('.calc-prefinal').value;
    const finalSel = row.querySelector('.calc-final-input');

    if (pStr !== '' && mStr !== '' && pfStr !== '') {
        const totalPct = (parseFloat(pStr) * WEIGHT_PRELIM) + (parseFloat(mStr) * WEIGHT_MIDTERM) + (parseFloat(pfStr) * WEIGHT_PREFINAL);
        finalSel.value = convertPercentageToPoint(totalPct).toFixed(2);
    } else { finalSel.value = ''; }
    calculateOverallGwa();
}

function seekGrades(targetSelect) {
    const row = targetSelect.closest('.calc-row');
    const pInput = row.querySelector('.calc-prelim');
    const mInput = row.querySelector('.calc-midterm');
    const pfInput = row.querySelector('.calc-prefinal');
    const targetPoint = parseFloat(targetSelect.value);

    if (isNaN(targetPoint)) {
        if (!pInput.hasAttribute('disabled')) pInput.value = '';
        if (!mInput.hasAttribute('disabled')) mInput.value = '';
        if (!pfInput.hasAttribute('disabled')) pfInput.value = '';
        calculateOverallGwa(); return;
    }

    const pLocked = pInput.hasAttribute('disabled'); const mLocked = mInput.hasAttribute('disabled'); const pfLocked = pfInput.hasAttribute('disabled');
    const pVal = pLocked ? parseFloat(pInput.value) : 0; const mVal = mLocked ? parseFloat(mInput.value) : 0; const pfVal = pfLocked ? parseFloat(pfInput.value) : 0;
    const targetPercent = getMinPercentageForPoint(targetPoint);
    
    let currentTotal = 0; let missingWeight = 0;
    if (pLocked) currentTotal += (pVal * WEIGHT_PRELIM); else missingWeight += WEIGHT_PRELIM;
    if (mLocked) currentTotal += (mVal * WEIGHT_MIDTERM); else missingWeight += WEIGHT_MIDTERM;
    if (pfLocked) currentTotal += (pfVal * WEIGHT_PREFINAL); else missingWeight += WEIGHT_PREFINAL;

    if (missingWeight === 0) {
        alert("All terms are locked. Cannot reverse-calculate.");
        targetSelect.value = ''; return;
    }

    const pointsNeeded = targetPercent - currentTotal;
    const requiredGrade = Math.ceil(pointsNeeded / missingWeight);

    if (requiredGrade > 100) {
        alert("Mathematically impossible! You would need higher than 100% on remaining terms.");
        targetSelect.value = '';
        if (!pLocked) pInput.value = ''; if (!mLocked) mInput.value = ''; if (!pfLocked) pfInput.value = '';
    } else {
        const finalRequired = Math.max(0, requiredGrade);
        if (!pLocked) pInput.value = finalRequired; if (!mLocked) mInput.value = finalRequired; if (!pfLocked) pfInput.value = finalRequired;
    }
    calculateOverallGwa();
}

function calculateOverallGwa() {
    const rows = document.querySelectorAll('.calc-row');
    let totalUnits = 0; let totalGradePoints = 0;

    rows.forEach(row => {
        const unitsVal = parseFloat(row.querySelector('.calc-units').innerText);
        const finalVal = parseFloat(row.querySelector('.calc-final-input').value);
        if (!isNaN(finalVal) && !isNaN(unitsVal)) {
            totalUnits += unitsVal; totalGradePoints += (finalVal * unitsVal);
        }
    });

    const outputElement = document.getElementById('projected-gwa');
    if (totalUnits > 0) {
        const projected = (totalGradePoints / totalUnits).toFixed(2);
        outputElement.innerText = projected;
        if (projected >= 3.25) outputElement.style.color = 'var(--risk-low)'; 
        else if (projected >= 2.50) outputElement.style.color = 'var(--risk-mod)'; 
        else outputElement.style.color = 'var(--risk-high)'; 
    } else {
        outputElement.innerText = '0.00'; outputElement.style.color = 'var(--accent-blue)';
    }
}

function getThemeColors() {
    const root = getComputedStyle(document.documentElement);
    return { base: root.getPropertyValue('--border-color').trim(), blue: root.getPropertyValue('--accent-blue').trim(), text: root.getPropertyValue('--text-dark').trim() };
}

let themeColors = getThemeColors();
const currentGwaForGauge = <?= json_encode(round((float) $current_gwa, 2)) ?>;
const GAUGE_ROTATION = 270; const GAUGE_CIRCUMFERENCE = 180; const GAUGE_MAX = 4.00;

const needlePlugin = {
    id: 'gwaNeedle',
    afterDraw(chart) {
        const meta = chart.getDatasetMeta(0); const arc = meta.data[0];
        if (!arc) return;
        const { x: cx, y: cy, outerRadius } = arc.getProps(['x', 'y', 'outerRadius'], true);
        const needleLength = outerRadius * 0.88;
        const clamped = Math.max(0, Math.min(GAUGE_MAX, currentGwaForGauge));
        const fraction = clamped / GAUGE_MAX;
        const chartJsAngleDeg = GAUGE_ROTATION + (GAUGE_CIRCUMFERENCE * fraction);
        const canvasAngleDeg = chartJsAngleDeg - 90;
        const angleRad = canvasAngleDeg * Math.PI / 180;
        const tipX = cx + needleLength * Math.cos(angleRad);
        const tipY = cy + needleLength * Math.sin(angleRad);
        const { ctx } = chart;
        ctx.save();
        const needleColor = getComputedStyle(document.documentElement).getPropertyValue('--text-dark').trim();
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(tipX, tipY); ctx.lineWidth = 3; ctx.strokeStyle = needleColor; ctx.lineCap = 'round'; ctx.stroke();
        ctx.beginPath(); ctx.arc(cx, cy, 6, 0, Math.PI * 2); ctx.fillStyle = needleColor; ctx.fill();
        ctx.restore();
    }
};

const ctxGauge = document.getElementById('honorGauge').getContext('2d');
const honorGaugeChart = new Chart(ctxGauge, {
    type: 'doughnut',
    data: { labels: ['Below', 'Cum Laude', 'Magna', 'Summa'], datasets: [{ data: [3.25, 0.25, 0.25, 0.25], backgroundColor: [themeColors.base, themeColors.blue, '#1d4ed8', '#b45309'], borderWidth: 0, circumference: GAUGE_CIRCUMFERENCE, rotation: GAUGE_ROTATION }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false }, tooltip: { enabled: false } }, animation: { animateRotate: true, animateScale: false } },
    plugins: [needlePlugin]
});

const observer = new MutationObserver(() => {
    themeColors = getThemeColors();
    honorGaugeChart.data.datasets[0].backgroundColor[0] = themeColors.base; honorGaugeChart.data.datasets[0].backgroundColor[1] = themeColors.blue; honorGaugeChart.update();
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
</script>

<?php require_once '../includes/footer.php'; ?>