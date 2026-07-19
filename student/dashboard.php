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
    // Preserve the raw 0-100 percentage for display and calculator
    $subj['prelim_raw'] = $subj['prelim'];
    $subj['midterm_raw'] = $subj['midterm'];
    $subj['prefinal_raw'] = $subj['prefinal'];
    
    // Normalize to Point Grade for the backend Math & Risk Analysis.
    // prelim is ALWAYS a raw percentage while encoding — normalizeTermGrade()
    // correctly treats an officially-encoded 0 as Failed (0.00), not as
    // "ungraded" (only a true NULL means ungraded).
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
        // CHANGED: Swapped $subj['code'] to $subj['title'] and ensured 2-decimal formatting for the point grade
        if ($subjRisk === 'HIGH') {
            $triage_alerts[] = "🚨 <strong>High Risk:</strong> Your grade in <strong>{$subj['title']}</strong> is " . round($subj['prelim_raw']) . "% (" . number_format($prelimPoint, 2) . "). A significant intervention is required.";
            $at_risk_count++;
        } elseif ($subjRisk === 'MODERATE') {
            $triage_alerts[] = "⚠️ <strong>Moderate Risk:</strong> Your grade in <strong>{$subj['title']}</strong> is " . round($subj['prelim_raw']) . "% (" . number_format($prelimPoint, 2) . "). This is dragging down your projected GWA.";
            $at_risk_count++;
        }
    }
}
unset($subj);

$predicted_gwa = computeWeightedGWA($prediction_rows) ?? $historical_gwa ?? 0.0;
$overall_risk  = computeRiskFromAvg($predicted_gwa);

if ($at_risk_count > 0) {
    $risk_factors[] = ['type' => 'warning', 'text' => "Current Term: You are below the Very Satisfactory threshold (< 2.50) in {$at_risk_count} current subject(s)."];
}
if ($historical_gwa !== null && $predicted_gwa < $historical_gwa) {
    $drop = number_format($historical_gwa - $predicted_gwa, 2);
    $risk_factors[] = ['type' => 'warning', 'text' => "Trajectory: Heuristic estimate projects a {$drop} drop in your GWA based on current pacing."];
}

// Subject Recommendation / Optimization — identifies the subject(s) currently 
// dragging the student down the most.
$gradedSubjects = array_filter($current_subjects, fn($s) => $s['prelim_point'] !== null);
if (!empty($gradedSubjects)) {
    $lowestGrade = min(array_column($gradedSubjects, 'prelim_point'));
    $weakestSubjects = array_values(array_filter(
        $gradedSubjects,
        fn($s) => $s['prelim_point'] == $lowestGrade
    ));

    // CHANGE: Extract 'title' instead of 'code'
    $titles = array_column($weakestSubjects, 'title');
    $subjectList = count($titles) > 1
        ? implode(', ', array_slice($titles, 0, -1)) . ' and ' . end($titles)
        : $titles[0];
    $plural = count($titles) > 1 ? 'these subjects' : 'this subject';

// Get the raw percentage of the weakest subject(s) to display in the text
    $lowestRaw = min(array_column($weakestSubjects, 'prelim_raw'));

    if (computeRiskFromAvg($lowestGrade) !== 'LOW') {
        // Scenario A: The weakest subject is actually at risk (< 2.50)
        $risk_factors[] = [
            'type' => 'info',
            'text' => "Focus Recommendation: Your weakest current grade is in <strong>{$subjectList}</strong> at <strong>" . round($lowestRaw) . "%</strong>. Prioritize study time on {$plural} first! Improving your lowest grade raises your GWA more than equal effort spread across subjects already doing well.",
        ];
    } else {
        // Scenario B: The student is safe, but this is the area with the most room for growth
        $risk_factors[] = [
            'type' => 'info',
            'text' => "Optimization Strategy: You are performing safely across the board! However, your lowest grade is in <strong>{$subjectList}</strong> at <strong>" . round($lowestRaw) . "%</strong>. To boost your GWA even higher, direct your extra effort toward {$plural}.",
        ];
    }
}

if (empty($risk_factors)) {
    $risk_factors[] = ['type' => 'success', 'text' => 'Positive: No immediate risk factors detected. Consistent performance maintained.'];
}

$honor_text = getLatinHonor($current_gwa);
$honor_color = match ($honor_text) {
    'Summa Cum Laude' => '#b45309',
    'Magna Cum Laude'  => '#1d4ed8',
    'Cum Laude'        => '#0e7490',
    default            => '#94a3b8',
};
$honor_text = $honor_text === 'Not Eligible' ? '—' : $honor_text . ' Track';

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
    ['Grades',             'grades.php',    '📝'],
    ['Academic History',   'history.php',   '📚'],
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
            <h1>Analytics Dashboard</h1>
            <p style="color: var(--text-gray); font-size: 0.95rem;">Decision-support center and heuristic academic estimation.</p>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 24px;">
        <div class="stat-card" style="border-top: 4px solid #0f172a;">
            <h4>Cumulative GWA</h4>
            <h2 style="color: #0f172a;"><?= $current_gwa > 0 ? number_format($current_gwa, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px; font-weight: 600;"><span style="color: <?= $honor_color ?>;">●</span> <?= $honor_text ?></p>
        </div>
        <div class="stat-card" style="border-top: 4px solid <?= $honor_color ?>;">
            <h4>Projected End-of-Term GWA</h4>
            <h2 style="color: <?= $honor_color ?>;"><?= number_format($predicted_gwa, 2) ?></h2>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Heuristic Estimate — pending Decision Tree model</p>
        </div>
        <?php $riskBg = getRiskColor($overall_risk); ?>
        <div class="stat-card" style="border-top: 4px solid <?= $riskBg ?>;">
            <h4>Overall Academic Risk</h4>
            <h2 style="color: <?= $riskBg ?>;"><?= $overall_risk ?></h2>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Trajectory Classification</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <div class="card" style="text-align: center;">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; text-align: left;">🎯 Honor Track Proximity</h3>
            <p style="text-align: left; color: #64748b; font-size: 0.8rem; margin: 0 0 16px;">
                Shows where your current GWA (<?= number_format($current_gwa, 2) ?>) falls on the 1.00–4.00 scale relative to each Latin Honor cutoff. The needle marks your exact standing.
            </p>
            <div style="position: relative; height: 180px; width: 100%; display: flex; justify-content: center; align-items: center;">
                <canvas id="honorGauge"></canvas>
            </div>
            <div style="margin-top: 4px;">
                <span style="font-size: 2rem; font-weight: 800; color: #0f172a;"><?= number_format($current_gwa, 2) ?></span>
                <br><span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Current GWA</span>
            </div>
            <div style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; font-size: 0.75rem; font-weight: 600;">
                <span style="color: #0e7490;">● Cum Laude (<?= number_format(CUM_LAUDE, 2) ?>)</span>
                <span style="color: #1d4ed8;">● Magna (<?= number_format(MAGNA_CUM_LAUDE, 2) ?>)</span>
                <span style="color: #b45309;">● Summa (<?= number_format(SUMMA_CUM_LAUDE, 2) ?>)</span>
            </div>
        </div>

        <div class="card">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">🧠 Risk Factor Analysis</h3>
            <div style="margin-bottom: 20px;">
                <?php foreach ($risk_factors as $factor): 
                    $icon = match ($factor['type']) {
                        'danger'  => '🔴',
                        'warning' => '🟠',
                        'info'    => '🎯',
                        default   => '🟢',
                    };
                ?>
                <div style="background: #f8fafc; border-left: 3px solid <?= match ($factor['type']) {
                        'danger'  => '#b91c1c',
                        'warning' => '#d97706',
                        'info'    => '#0e7490',
                        default   => '#059669',
                    } ?>; padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; font-size: 0.85rem; color: #334155;">
                    <?= $icon ?> <?= $factor['text'] ?>
                </div>
                <?php endforeach; ?>
            </div>

            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 12px;">📊 Subject Triage (Focus Areas)</h3>
            <?php if (empty($triage_alerts)): ?>
                <p style="font-size: 0.85rem; color: #059669; font-weight: 600;">✓ All current subjects are within safe thresholds.</p>
            <?php else: ?>
                <?php foreach ($triage_alerts as $alert): ?>
                    <div style="background: #fef2f2; padding: 10px 14px; margin-bottom: 8px; border-radius: 4px; font-size: 0.85rem; color: #7f1d1d; border: 1px solid #fecaca;">
                        <?= $alert ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700;">Current Subjects & Predictions</h3>
            <button onclick="toggleCalculator()" style="background: #0e7490; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit;">
                🎯 Open Grade Goal Calculator
            </button>
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 12px; text-align: left;">Code</th>
                    <th style="padding: 12px; text-align: left;">Subject Title</th>
                    <th style="padding: 12px; text-align: center;">Units</th>
                    <th style="padding: 12px; text-align: center;">Current Prelim</th>
                    <th style="padding: 12px; text-align: center; color: #0e7490;">Estimated Final</th>
                    <th style="padding: 12px; text-align: center;">Subject Risk</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_subjects as $subj): 
                    // Get exact risk strings to match the 3-tier colors
                    $pRisk = $subj['prelim_point'] !== null ? computeRiskFromAvg($subj['prelim_point']) : 'LOW';
                    $fRisk = $subj['predicted_final'] !== null ? computeRiskFromAvg($subj['predicted_final']) : 'LOW';
                    
                    // Apply exact colors: Red for HIGH, Orange for MODERATE, Green for LOW
                    $prelimCol = $pRisk === 'HIGH' ? '#b91c1c' : ($pRisk === 'MODERATE' ? '#d97706' : '#059669');
                    $finalCol  = $fRisk === 'HIGH' ? '#b91c1c' : ($fRisk === 'MODERATE' ? '#d97706' : '#059669');
                    
                    $riskBg    = getRiskColor($subj['final_risk']);
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 12px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($subj['code']) ?></td>
                    <td style="padding: 12px; color: #475569;"><?= htmlspecialchars($subj['title']) ?></td>
                    <td style="padding: 12px; text-align: center;"><?= htmlspecialchars($subj['units']) ?></td>
                    
                    <td style="padding: 12px; text-align: center; font-weight: 600; color: <?= $prelimCol ?>;">
                        <?php if ($subj['prelim_raw'] !== null): ?>
                            <?= round($subj['prelim_raw']) ?>% <br>
                            <span style="font-size: 0.75rem; color: #64748b;">(<?= number_format($subj['prelim_point'], 2) ?>)</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td style="padding: 12px; text-align: center; font-weight: 700; color: <?= $finalCol ?>;">
                        <?= $subj['predicted_final'] !== null ? number_format($subj['predicted_final'], 2) : '—' ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="background: <?= $riskBg ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                            <?= $subj['predicted_final'] !== null ? $subj['final_risk'] : 'N/A' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="calculator-section" class="card" style="display: none; border: 2px solid #0e7490; background-color: #f8fafc;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700;">🎯 Grade Goal Calculator</h3>
                <p style="color: #64748b; font-size: 0.85rem; margin: 0;">Input percentage grades to compute your final point grade, or pick a Target to back-calculate.</p>
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <div style="background: white; padding: 10px 20px; border-radius: 8px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <span style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Projected Semester GWA</span><br>
                    <span id="projected-gwa" style="font-size: 1.8rem; font-weight: 800; color: #0e7490;">0.00</span>
                </div>
                <button onclick="toggleCalculator()" style="background: #e2e8f0; color: #334155; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; font-family: inherit;">
                    ✕ Close
                </button>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: center; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background: #e2e8f0; border-bottom: 2px solid #cbd5e1;">
                    <th style="padding: 12px; text-align: left;">Code</th>
                    <th style="padding: 12px; text-align: left;">Subject Title</th>
                    <th style="padding: 12px;">Units</th>
                    <th style="padding: 12px; color: #475569;">Prelim % (30%)</th>
                    <th style="padding: 12px; color: #475569;">Midterm % (30%)</th>
                    <th style="padding: 12px; color: #475569;">Pre-Final % (40%)</th>
                    <th style="padding: 12px; width: 140px; color: #0e7490;">Target Final Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($current_subjects as $idx => $subj): 
                    $p_val  = $subj['prelim_raw'] !== null ? round((float)$subj['prelim_raw']) : '';
                    $m_val  = $subj['midterm_raw'] !== null ? round((float)$subj['midterm_raw']) : '';
                    $pf_val = $subj['prefinal_raw'] !== null ? round((float)$subj['prefinal_raw']) : '';

                    // Lock if exists
                    $p_locked  = $p_val  !== '' ? 'disabled style="background:#e2e8f0;"' : 'style="background:white;"';
                    $m_locked  = $m_val  !== '' ? 'disabled style="background:#e2e8f0;"' : 'style="background:white;"';
                    $pf_locked = $pf_val !== '' ? 'disabled style="background:#e2e8f0;"' : 'style="background:white;"';
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9;" class="calc-row">
                    <td style="padding: 12px; font-weight: 600; color: #0f172a; text-align: left;"><?= htmlspecialchars($subj['code']) ?></td>
                    <td style="padding: 12px; color: #475569; text-align: left;"><?= htmlspecialchars($subj['title']) ?></td>
                    <td style="padding: 12px;" class="calc-units"><?= htmlspecialchars($subj['units']) ?></td>
                    
                    <td style="padding: 12px;">
                        <input type="number" min="0" max="100" class="calc-term-input calc-prelim" value="<?= $p_val ?>" <?= $p_locked ?> oninput="computeRowFinal(this)" style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-family: inherit; text-align: center;">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" min="0" max="100" class="calc-term-input calc-midterm" value="<?= $m_val ?>" <?= $m_locked ?> oninput="computeRowFinal(this)" style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-family: inherit; text-align: center;">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" min="0" max="100" class="calc-term-input calc-prefinal" value="<?= $pf_val ?>" <?= $pf_locked ?> oninput="computeRowFinal(this)" style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-family: inherit; text-align: center;">
                    </td>

                    <td style="padding: 12px; border-left: 2px dashed #e2e8f0; background: #f8fafc;">
                        <select class="calc-final-input" style="width: 100%; padding: 8px; border: 1px solid #0e7490; background: white; color: #0e7490; border-radius: 4px; font-family: inherit; font-weight: 700;" onchange="seekGrades(this)">
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
// Grading scale variables
const WEIGHT_PRELIM = <?= WEIGHT_PRELIM ?? 0.30 ?>;
const WEIGHT_MIDTERM = <?= WEIGHT_MIDTERM ?? 0.30 ?>;
const WEIGHT_PREFINAL = <?= WEIGHT_PREFINAL ?? 0.40 ?>;

function toggleCalculator() {
    const calcDiv = document.getElementById('calculator-section');
    if (calcDiv.style.display === 'none') {
        calcDiv.style.display = 'block';
        calcDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Initialize existing rows
        document.querySelectorAll('.calc-prelim').forEach(el => computeRowFinal(el));
    } else {
        calcDiv.style.display = 'none';
    }
}

// Convert 0-100 Percentage to UdM Point Grade
function convertPercentageToPoint(pct) {
    if (pct >= 99) return 4.00;
    if (pct >= 97) return 3.75;
    if (pct >= 95) return 3.50;
    if (pct >= 92) return 3.25;
    if (pct >= 90) return 3.00;
    if (pct >= 88) return 2.75;
    if (pct >= 86) return 2.50;
    if (pct >= 84) return 2.25;
    if (pct >= 82) return 2.00;
    if (pct >= 80) return 1.75;
    if (pct >= 78) return 1.50;
    if (pct >= 76) return 1.25;
    if (pct >= 75) return 1.00;
    return 0.00;
}

// Get required raw percentage (Lower Bound) for a Target Point Grade
function getMinPercentageForPoint(point) {
    if (point >= 4.00) return 99;
    if (point >= 3.75) return 97;
    if (point >= 3.50) return 95;
    if (point >= 3.25) return 92;
    if (point >= 3.00) return 90;
    if (point >= 2.75) return 88;
    if (point >= 2.50) return 86;
    if (point >= 2.25) return 84;
    if (point >= 2.00) return 82;
    if (point >= 1.75) return 80;
    if (point >= 1.50) return 78;
    if (point >= 1.25) return 76;
    if (point >= 1.00) return 75;
    return 0;
}

// Bottom-Up mode: Terms compute the Final Grade
function computeRowFinal(inputElem) {
    const row = inputElem.closest('.calc-row');
    const pStr = row.querySelector('.calc-prelim').value;
    const mStr = row.querySelector('.calc-midterm').value;
    const pfStr = row.querySelector('.calc-prefinal').value;
    const finalSel = row.querySelector('.calc-final-input');

    if (pStr !== '' && mStr !== '' && pfStr !== '') {
        const totalPct = (parseFloat(pStr) * WEIGHT_PRELIM) + (parseFloat(mStr) * WEIGHT_MIDTERM) + (parseFloat(pfStr) * WEIGHT_PREFINAL);
        finalSel.value = convertPercentageToPoint(totalPct).toFixed(2);
    } else {
        finalSel.value = '';
    }
    calculateOverallGwa();
}

// Top-Down mode (Goal Seek): Algebraically back-calculates missing percentages
function seekGrades(targetSelect) {
    const row = targetSelect.closest('.calc-row');
    const pInput = row.querySelector('.calc-prelim');
    const mInput = row.querySelector('.calc-midterm');
    const pfInput = row.querySelector('.calc-prefinal');
    const targetPoint = parseFloat(targetSelect.value);

    // If cleared, clear all unlocked term inputs
    if (isNaN(targetPoint)) {
        if (!pInput.hasAttribute('disabled')) pInput.value = '';
        if (!mInput.hasAttribute('disabled')) mInput.value = '';
        if (!pfInput.hasAttribute('disabled')) pfInput.value = '';
        calculateOverallGwa();
        return;
    }

    const pLocked = pInput.hasAttribute('disabled');
    const mLocked = mInput.hasAttribute('disabled');
    const pfLocked = pfInput.hasAttribute('disabled');

    const pVal = pLocked ? parseFloat(pInput.value) : 0;
    const mVal = mLocked ? parseFloat(mInput.value) : 0;
    const pfVal = pfLocked ? parseFloat(pfInput.value) : 0;

    // ALGEBRA: Get required total percentage
    const targetPercent = getMinPercentageForPoint(targetPoint);
    
    // Find what we already have
    let currentTotal = 0;
    let missingWeight = 0;

    if (pLocked) currentTotal += (pVal * WEIGHT_PRELIM); else missingWeight += WEIGHT_PRELIM;
    if (mLocked) currentTotal += (mVal * WEIGHT_MIDTERM); else missingWeight += WEIGHT_MIDTERM;
    if (pfLocked) currentTotal += (pfVal * WEIGHT_PREFINAL); else missingWeight += WEIGHT_PREFINAL;

    if (missingWeight === 0) {
        alert("All terms are locked. Cannot reverse-calculate.");
        targetSelect.value = '';
        return;
    }

    // Required average for the missing terms to hit the goal
    const pointsNeeded = targetPercent - currentTotal;
    const requiredGrade = Math.ceil(pointsNeeded / missingWeight);

    if (requiredGrade > 100) {
        alert("Mathematically impossible! You would need higher than 100% on remaining terms.");
        targetSelect.value = '';
        if (!pLocked) pInput.value = '';
        if (!mLocked) mInput.value = '';
        if (!pfLocked) pfInput.value = '';
    } else {
        const finalRequired = Math.max(0, requiredGrade);
        if (!pLocked) pInput.value = finalRequired;
        if (!mLocked) mInput.value = finalRequired;
        if (!pfLocked) pfInput.value = finalRequired;
    }
    
    calculateOverallGwa();
}

function calculateOverallGwa() {
    const rows = document.querySelectorAll('.calc-row');
    let totalUnits = 0;
    let totalGradePoints = 0;

    rows.forEach(row => {
        const unitsVal = parseFloat(row.querySelector('.calc-units').innerText);
        const finalVal = parseFloat(row.querySelector('.calc-final-input').value);

        if (!isNaN(finalVal) && !isNaN(unitsVal)) {
            totalUnits += unitsVal;
            totalGradePoints += (finalVal * unitsVal);
        }
    });

    const outputElement = document.getElementById('projected-gwa');
    if (totalUnits > 0) {
        const projected = (totalGradePoints / totalUnits).toFixed(2);
        outputElement.innerText = projected;
        
        if (projected >= 3.25) outputElement.style.color = '#059669'; 
        else if (projected >= 2.50) outputElement.style.color = '#d97706'; 
        else outputElement.style.color = '#b91c1c'; 
    } else {
        outputElement.innerText = '0.00';
        outputElement.style.color = '#0e7490';
    }
}

// Honor Track Gauge — with an actual pointer, not just a static legend.
// The gauge spans the full 0.00-4.00 point scale across a 180° arc
// (rotation: 270 = starts at top, circumference: 180 = sweeps clockwise to
// bottom). The needle angle uses that exact same rotation/circumference so
// it always lines up with the colored bands beneath it, even if those
// threshold values ever change.
const currentGwaForGauge = <?= json_encode(round((float) $current_gwa, 2)) ?>;
const GAUGE_ROTATION = 270;
const GAUGE_CIRCUMFERENCE = 180;
const GAUGE_MAX = 4.00;

const needlePlugin = {
    id: 'gwaNeedle',
    afterDraw(chart) {
        const meta = chart.getDatasetMeta(0);
        const arc = meta.data[0];
        if (!arc) return;

        // Chart.js v3/v4 tracks element geometry through an internal
        // animation system — reading .outerRadius directly can return
        // undefined mid-animation. getProps(..., true) forces the final,
        // settled value instead.
        const { x: cx, y: cy, outerRadius } = arc.getProps(['x', 'y', 'outerRadius'], true);
        const needleLength = outerRadius * 0.88;

        const clamped = Math.max(0, Math.min(GAUGE_MAX, currentGwaForGauge));
        const fraction = clamped / GAUGE_MAX;

        // Chart.js's `rotation` is measured from the TOP (12 o'clock),
        // clockwise — NOT from the right/east like raw canvas angles.
        // Convert before using Math.cos/sin, which expect the canvas
        // convention (0° = east, clockwise positive).
        const chartJsAngleDeg = GAUGE_ROTATION + (GAUGE_CIRCUMFERENCE * fraction);
        const canvasAngleDeg = chartJsAngleDeg - 90;
        const angleRad = canvasAngleDeg * Math.PI / 180;

        const tipX = cx + needleLength * Math.cos(angleRad);
        const tipY = cy + needleLength * Math.sin(angleRad);

        const { ctx } = chart;
        ctx.save();

        // Needle
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(tipX, tipY);
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#0f172a';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Pivot dot
        ctx.beginPath();
        ctx.arc(cx, cy, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#0f172a';
        ctx.fill();

        ctx.restore();
    }
};

const ctxGauge = document.getElementById('honorGauge').getContext('2d');
new Chart(ctxGauge, {
    type: 'doughnut',
    data: {
        labels: ['Below', 'Cum Laude', 'Magna', 'Summa'],
        datasets: [{
            data: [3.25, 0.25, 0.25, 0.25],
            backgroundColor: ['#cbd5e1', '#0e7490', '#1d4ed8', '#b45309'],
            borderWidth: 0,
            circumference: GAUGE_CIRCUMFERENCE,
            rotation: GAUGE_ROTATION
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        animation: { animateRotate: true, animateScale: false }
    },
    plugins: [needlePlugin]
});
</script>

<?php require_once '../includes/footer.php'; ?>