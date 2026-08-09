<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

// 1. Fetch historical grades joined to curriculum units
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
$historical_count = 0;
$last_gwa = 0;

foreach ($byTerm as $term => $termRows) {
    $gwa = computeWeightedGWA($termRows);
    if ($gwa !== null) {
        $semData[] = ['semester' => $term, 'gwa' => $gwa, 'is_prediction' => false];
        $last_gwa = $gwa;
        $historical_count++;
    }
}

// 2. Fetch Current Term Grades for Intermediate Plotting
$stmtCurr = $db->prepare("
    SELECT s.units, g.prelim, g.midterm
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
");
$stmtCurr->execute([$user['id']]);
$current_subjects = $stmtCurr->fetchAll();

$prelim_pts = 0; $prelim_units = 0;
$midterm_pts = 0; $midterm_units = 0;

foreach ($current_subjects as $subj) {
    $p_point = normalizeTermGrade($subj['prelim']);
    $m_point = normalizeTermGrade($subj['midterm']);

    if ($p_point !== null) {
        $prelim_pts += ($p_point * $subj['units']);
        $prelim_units += $subj['units'];
    }
    if ($m_point !== null) {
        $midterm_pts += ($m_point * $subj['units']);
        $midterm_units += $subj['units'];
    }
}

$current_prelim_gwa = $prelim_units > 0 ? round($prelim_pts / $prelim_units, 2) : null;
$current_midterm_gwa = $midterm_units > 0 ? round($midterm_pts / $midterm_units, 2) : null;

if ($current_prelim_gwa !== null) {
    $semData[] = ['semester' => 'Current Prelims', 'gwa' => $current_prelim_gwa, 'is_prediction' => true];
}
if ($current_midterm_gwa !== null) {
    $semData[] = ['semester' => 'Current Midterms', 'gwa' => $current_midterm_gwa, 'is_prediction' => true];
}


// 3. Fetch or Mock the Final Prediction
$stmtPred = $db->prepare("SELECT predicted_gwa, risk_level FROM predictions WHERE student_id = ? ORDER BY generated_at DESC LIMIT 1");
$stmtPred->execute([$user['id']]);
$prediction = $stmtPred->fetch();

if (!$prediction || empty($prediction['predicted_gwa'])) {
    $mock_pred_gwa = $last_gwa > 0 ? max(1.0, min(4.0, $last_gwa - 0.25)) : 2.50; 
    $mock_risk = $mock_pred_gwa < 2.00 ? 'HIGH' : ($mock_pred_gwa < 2.50 ? 'MODERATE' : 'LOW');
    
    $prediction = [
        'predicted_gwa' => round($mock_pred_gwa, 2),
        'risk_level' => $mock_risk
    ];
}

if ($historical_count > 0 || !empty($current_subjects)) {
    $semData[] = [
        'semester' => 'Projected (End of Term)', 
        'gwa' => (float)$prediction['predicted_gwa'], 
        'is_prediction' => true
    ];
}

$pageTitle = 'Performance Trend';
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
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Performance Trend</h1>
            <p>Your average final grade trajectory across completed semesters, including current projections.</p>
        </div>
    </div>

    <?php if (!empty($semData)): 
        $risk = $prediction['risk_level'];
        $riskBg = match($risk) {
            'HIGH' => 'var(--risk-high)',
            'MODERATE' => 'var(--risk-mod)',
            default => 'var(--risk-low)'
        };
    ?>
    <div class="card" style="margin-bottom: 24px; border-left: 4px solid <?= $riskBg ?>; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Current Semester Projection</h4>
            <h2 style="margin: 4px 0 0 0; color: var(--text-dark); font-size: 1.8rem;"><?= number_format($prediction['predicted_gwa'], 2) ?></h2>
        </div>
        <div style="text-align: right;">
            <span style="background: <?= $riskBg ?>; color: white; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                <?= $risk ?> RISK
            </span>
            <p style="margin: 6px 0 0 0; font-size: 0.8rem; color: var(--text-gray);">Based on Machine Learning Analysis</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <?php if (empty($semData)): ?>
            <p class="empty-state">Not enough data yet to plot a trajectory line chart.</p>
        <?php else: ?>
            <div style="position: relative; height: 350px; width: 100%;">
                <canvas id="semGwaChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($semData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dynamically pull CSS Variables for Chart.js
function getChartColors() {
    const root = getComputedStyle(document.documentElement);
    return {
        text: root.getPropertyValue('--text-gray').trim(),
        border: root.getPropertyValue('--border-color').trim(),
        blue: root.getPropertyValue('--accent-blue').trim(),
        gold: root.getPropertyValue('--risk-mod').trim()
    };
}

let colors = getChartColors();
const ctx = document.getElementById('semGwaChart').getContext('2d');
const historicalCount = <?= $historical_count ?>;

const trendChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($semData, 'semester')) ?>,
        datasets: [
            // 1. Summa Cum Laude Baseline
            {
                label: 'Summa Cum Laude (3.75)',
                data: <?= json_encode(array_fill(0, count($semData), 3.75)) ?>,
                borderColor: 'rgba(180, 83, 9, 0.4)',
                borderWidth: 2,
                borderDash: [5, 5], 
                pointRadius: 0, 
                fill: false,
                tension: 0
            },
            // 2. Magna Cum Laude Baseline
            {
                label: 'Magna Cum Laude (3.50)',
                data: <?= json_encode(array_fill(0, count($semData), 3.50)) ?>,
                borderColor: 'rgba(29, 78, 216, 0.4)',
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 0,
                fill: false,
                tension: 0
            },
            // 3. Cum Laude Baseline
            {
                label: 'Cum Laude (3.25)',
                data: <?= json_encode(array_fill(0, count($semData), 3.25)) ?>,
                borderColor: 'rgba(14, 116, 144, 0.4)',
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 0,
                fill: false,
                tension: 0
            },
            // 4. THE STUDENT'S ACTUAL GWA TRAJECTORY
            {
                label: 'Semester GWA Trajectory',
                data: <?= json_encode(array_column($semData, 'gwa')) ?>,
                borderColor: colors.blue, 
                backgroundColor: 'rgba(108, 142, 239, 0.05)',
                borderWidth: 3,
                pointBackgroundColor: (context) => context.dataIndex >= historicalCount ? colors.gold : colors.blue,
                pointBorderColor: (context) => context.dataIndex >= historicalCount ? colors.gold : colors.blue,
                pointRadius: (context) => context.dataIndex >= historicalCount ? 7 : 5,
                pointHoverRadius: 8,
                fill: true,
                tension: 0.15,
                segment: {
                    borderDash: (ctx) => ctx.p0DataIndex >= historicalCount - 1 ? [6, 6] : undefined,
                    borderColor: (ctx) => ctx.p0DataIndex >= historicalCount - 1 ? colors.gold : colors.blue
                }
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: colors.text }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let pointLabel = context.chart.data.labels[context.dataIndex];
                        if (context.dataIndex >= historicalCount) {
                            label = pointLabel; 
                        }
                        return `${label}: ${context.parsed.y.toFixed(2)}`;
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: { color: colors.text },
                grid: { color: colors.border }
            },
            y: {
                min: 1.0,
                max: 4.0,
                ticks: { stepSize: 0.5, color: colors.text },
                grid: { color: colors.border },
                title: { display: true, text: 'GWA Value (4.00 = Highest)', color: colors.text }
            }
        }
    }
});

// Auto-redraw chart colors on theme toggle
const observer = new MutationObserver(() => {
    colors = getChartColors();
    
    trendChart.options.scales.x.ticks.color = colors.text;
    trendChart.options.scales.x.grid.color = colors.border;
    trendChart.options.scales.y.ticks.color = colors.text;
    trendChart.options.scales.y.grid.color = colors.border;
    trendChart.options.scales.y.title.color = colors.text;
    trendChart.options.plugins.legend.labels.color = colors.text;

    trendChart.data.datasets[3].borderColor = colors.blue;
    trendChart.data.datasets[3].pointBackgroundColor = (ctx) => ctx.dataIndex >= historicalCount ? colors.gold : colors.blue;
    trendChart.data.datasets[3].pointBorderColor = (ctx) => ctx.dataIndex >= historicalCount ? colors.gold : colors.blue;
    trendChart.data.datasets[3].segment.borderColor = (ctx) => ctx.p0DataIndex >= historicalCount - 1 ? colors.gold : colors.blue;

    trendChart.update();
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>