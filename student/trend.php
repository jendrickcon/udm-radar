<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// --- OFFICIAL UDM POINT CONVERSION (Applied ONLY to final percentage averages) ---
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

$stmt = $db->prepare("
    SELECT g.school_year, g.semester, g.final_grade, s.units
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 0 AND g.final_grade IS NOT NULL
    ORDER BY g.school_year ASC, g.semester ASC
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$byTerm = [];
foreach ($rows as $r) {
    $key = ($r['school_year'] ?? '') . ' S' . ($r['semester'] ?? '');
    $byTerm[$key][] = ['grade' => $r['final_grade'], 'units' => $r['units']];
}

$semData = [];
$semDataTypes = [];
$last_gwa = 0;

foreach ($byTerm as $term => $termRows) {
    $gwa = computeWeightedGWA($termRows);
    if ($gwa !== null) {
        $semData[] = ['semester' => $term, 'gwa' => $gwa];
        $semDataTypes[] = 'historical';
        $last_gwa = $gwa;
    }
}

$stmtCurr = $db->prepare("
    SELECT s.units, g.prelim, g.midterm
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
");
$stmtCurr->execute([$user['id']]);
$current_subjects = $stmtCurr->fetchAll();

// FIXED: Calculate average based purely on raw percentages BEFORE converting to points
$prelim_pct_sum = 0; $prelim_units = 0; $encoded_prelim_count = 0;
$midterm_pct_sum = 0; $midterm_units = 0; $encoded_midterm_count = 0;
$total_current = count($current_subjects);

foreach ($current_subjects as $subj) {
    if ($subj['prelim'] !== null && trim((string)$subj['prelim']) !== '') {
        $prelim_pct_sum += ((float)$subj['prelim'] * $subj['units']);
        $prelim_units += $subj['units'];
        $encoded_prelim_count++;
    }
    if ($subj['midterm'] !== null && trim((string)$subj['midterm']) !== '') {
        $midterm_pct_sum += ((float)$subj['midterm'] * $subj['units']);
        $midterm_units += $subj['units'];
        $encoded_midterm_count++;
    }
}

$current_prelim_gwa = null;
if ($prelim_units > 0) {
    $avg_prelim_pct = $prelim_pct_sum / $prelim_units;
    $current_prelim_gwa = convertPercentageToPoint($avg_prelim_pct);
}

$current_midterm_gwa = null;
if ($midterm_units > 0) {
    $avg_midterm_pct = $midterm_pct_sum / $midterm_units;
    $current_midterm_gwa = convertPercentageToPoint($avg_midterm_pct);
}

if ($current_prelim_gwa !== null) {
    $semData[] = ['semester' => 'Current Prelims', 'gwa' => $current_prelim_gwa];
    $semDataTypes[] = 'current';
}
if ($current_midterm_gwa !== null) {
    $semData[] = ['semester' => 'Current Midterms', 'gwa' => $current_midterm_gwa];
    $semDataTypes[] = 'current';
}

$stmtPred = $db->prepare("
    SELECT predicted_gwa, risk_level, prediction_source, generated_at 
    FROM predictions 
    WHERE student_id = ? 
    ORDER BY generated_at DESC, id DESC 
    LIMIT 1
");
$stmtPred->execute([$user['id']]);
$prediction = $stmtPred->fetch(PDO::FETCH_ASSOC);

if ($prediction && !empty($prediction['predicted_gwa'])) {
    $semData[] = ['semester' => 'Predicted End-of-Term', 'gwa' => (float)$prediction['predicted_gwa']];
    $semDataTypes[] = 'prediction';
}

$latest_hist = $last_gwa > 0 ? $last_gwa : null;
$latest_curr = $current_midterm_gwa ?? $current_prelim_gwa ?? null;
$pred_gwa = $prediction['predicted_gwa'] ?? null;

// Dynamic coverage text
if ($current_midterm_gwa !== null) {
    $curr_coverage_text = "Based on {$encoded_midterm_count} of {$total_current} subjects";
} elseif ($current_prelim_gwa !== null) {
    $curr_coverage_text = "Based on {$encoded_prelim_count} of {$total_current} subjects";
} else {
    $curr_coverage_text = "Active term snapshot";
}

$diff_text = "";
if ($latest_hist !== null && $latest_curr !== null) {
    $diff = round($latest_curr - $latest_hist, 2);
    if ($diff > 0) $diff_text = "Current period performance is <strong>" . number_format(abs($diff), 2) . " points above</strong> the latest completed-semester GWA.";
    elseif ($diff < 0) $diff_text = "Current period performance is <strong>" . number_format(abs($diff), 2) . " points below</strong> the latest completed-semester GWA.";
    else $diff_text = "Current period performance <strong>perfectly matches</strong> the latest completed-semester GWA.";
} elseif ($latest_hist !== null && $latest_curr === null) {
    $diff_text = "No current-period grades have been encoded yet to compare against historical performance.";
} else {
    $diff_text = "Insufficient historical or current data to generate an active trajectory comparison.";
}

$pageTitle = 'Performance Trend';
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

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Performance Trend</h1>
            <p style="color: var(--text-gray);">Longitudinal tracking of completed semesters, current grading snapshots, and projected outcomes.</p>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 16px;">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Latest Completed Semester</h4>
            <h2 style="color: var(--accent-blue);"><?= $latest_hist !== null ? number_format($latest_hist, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Most recent completed-term GWA</p>
        </div>
        <div class="stat-card" style="border-left-color: #0d9488;">
            <h4>Latest Current Period</h4>
            <h2 style="color: #0d9488;"><?= $latest_curr !== null ? number_format($latest_curr, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;"><?= $curr_coverage_text ?></p>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Predicted End-of-Term</h4>
            <h2 style="color: var(--risk-mod);"><?= $pred_gwa !== null ? number_format($pred_gwa, 2) : 'N/A' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">
                <?= $prediction ? 'Generated on ' . date('M j, Y', strtotime($prediction['generated_at'])) : 'No Prediction Available' ?>
            </p>
        </div>
    </div>

    <div style="background: var(--card-bg); border: 1px solid var(--border-color); padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; color: var(--text-dark); font-size: 0.9rem;">
        <?= $diff_text ?>
    </div>

    <div class="card">
        <?php if (empty($semData)): ?>
            <p class="empty-state">Not enough data recorded yet to plot a trajectory line chart.</p>
        <?php else: ?>
            <div style="display: flex; gap: 20px; margin-bottom: 16px; font-size: 0.8rem; font-weight: 600; color: var(--text-gray); flex-wrap: wrap;">
                <span style="display: flex; align-items: center; gap: 6px;"><div style="width: 12px; height: 12px; border-radius: 50%; background: var(--accent-blue);"></div> Completed Semester GWA</span>
                <span style="display: flex; align-items: center; gap: 6px;"><div style="width: 12px; height: 12px; border-radius: 50%; background: #0d9488;"></div> Current Term Snapshot</span>
                <span style="display: flex; align-items: center; gap: 6px;"><div style="width: 12px; height: 12px; border-radius: 50%; background: var(--risk-mod);"></div> Predicted End-of-Term</span>
            </div>
            
            <div style="position: relative; height: 350px; width: 100%;">
                <canvas id="semGwaChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); font-size: 0.8rem; color: var(--text-gray);">
        <em>UDM-RADAR currently uses a Decision Tree regression prototype trained on synthetic baseline data for workflow testing. Predictions are decision-support estimates and are not guaranteed academic outcomes.</em>
    </div>
</div>

<?php if (!empty($semData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function getChartColors() {
    const root = getComputedStyle(document.documentElement);
    return {
        text: root.getPropertyValue('--text-gray').trim(),
        border: root.getPropertyValue('--border-color').trim(),
        historical: root.getPropertyValue('--accent-blue').trim(),
        current: '#0d9488',
        predicted: root.getPropertyValue('--risk-mod').trim()
    };
}

let colors = getChartColors();
const ctx = document.getElementById('semGwaChart').getContext('2d');
const semDataTypes = <?= json_encode($semDataTypes) ?>;

const trendChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($semData, 'semester')) ?>,
        datasets: [
            { label: 'Summa Cum Laude (3.75)', data: <?= json_encode(array_fill(0, count($semData), 3.75)) ?>, borderColor: 'rgba(180, 83, 9, 0.4)', borderWidth: 1.5, borderDash: [4, 4], pointRadius: 0, fill: false, tension: 0 },
            { label: 'Magna Cum Laude (3.50)', data: <?= json_encode(array_fill(0, count($semData), 3.50)) ?>, borderColor: 'rgba(29, 78, 216, 0.4)', borderWidth: 1.5, borderDash: [4, 4], pointRadius: 0, fill: false, tension: 0 },
            { label: 'Cum Laude (3.25)', data: <?= json_encode(array_fill(0, count($semData), 3.25)) ?>, borderColor: 'rgba(14, 116, 144, 0.4)', borderWidth: 1.5, borderDash: [4, 4], pointRadius: 0, fill: false, tension: 0 },
            {
                label: 'GWA Trajectory',
                data: <?= json_encode(array_column($semData, 'gwa')) ?>,
                backgroundColor: 'rgba(108, 142, 239, 0.05)',
                borderWidth: 3,
                pointBackgroundColor: (context) => {
                    const type = semDataTypes[context.dataIndex];
                    if (type === 'prediction') return colors.predicted;
                    if (type === 'current') return colors.current;
                    return colors.historical;
                },
                pointBorderColor: (context) => {
                    const type = semDataTypes[context.dataIndex];
                    if (type === 'prediction') return colors.predicted;
                    if (type === 'current') return colors.current;
                    return colors.historical;
                },
                pointRadius: (context) => semDataTypes[context.dataIndex] === 'historical' ? 5 : 7,
                pointHoverRadius: 8,
                fill: true,
                tension: 0.15,
                segment: {
                    borderDash: (ctx) => semDataTypes[ctx.p1DataIndex] === 'prediction' ? [6, 6] : undefined,
                    borderColor: (ctx) => {
                        if (semDataTypes[ctx.p1DataIndex] === 'prediction') return colors.predicted;
                        if (semDataTypes[ctx.p1DataIndex] === 'current' || semDataTypes[ctx.p0DataIndex] === 'current') return colors.current;
                        return colors.historical;
                    }
                }
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let type = semDataTypes[context.dataIndex];
                        let prefix = type === 'historical' ? 'Official Term GWA: ' : (type === 'current' ? 'Active Snapshot GWA: ' : 'Predicted GWA: ');
                        return `${prefix}${context.parsed.y.toFixed(2)}`;
                    }
                }
            }
        },
        scales: {
            x: { ticks: { color: colors.text }, grid: { color: colors.border } },
            y: { min: 1.0, max: 4.0, ticks: { stepSize: 0.5, color: colors.text }, grid: { color: colors.border }, title: { display: true, text: 'GWA Value (4.00 = Highest)', color: colors.text } }
        }
    }
});

const observer = new MutationObserver(() => {
    colors = getChartColors();
    trendChart.options.scales.x.ticks.color = colors.text;
    trendChart.options.scales.x.grid.color = colors.border;
    trendChart.options.scales.y.ticks.color = colors.text;
    trendChart.options.scales.y.grid.color = colors.border;
    trendChart.options.scales.y.title.color = colors.text;

    trendChart.data.datasets[3].pointBackgroundColor = (ctx) => {
        const t = semDataTypes[ctx.dataIndex];
        return t === 'prediction' ? colors.predicted : (t === 'current' ? colors.current : colors.historical);
    };
    trendChart.data.datasets[3].pointBorderColor = trendChart.data.datasets[3].pointBackgroundColor;
    trendChart.data.datasets[3].segment.borderColor = (ctx) => {
        if (semDataTypes[ctx.p1DataIndex] === 'prediction') return colors.predicted;
        if (semDataTypes[ctx.p1DataIndex] === 'current' || semDataTypes[ctx.p0DataIndex] === 'current') return colors.current;
        return colors.historical;
    };
    trendChart.update();
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>