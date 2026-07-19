<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

// 1. Get assigned sections via precise class loads table
$stmt = $db->prepare("SELECT DISTINCT section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section");
$stmt->execute([$user['id']]);
$my_sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$section_stats = [];
$top_students = [];

if (!empty($my_sections)) {
    $inQuery = implode(',', array_fill(0, count($my_sections), '?'));
    
    // 2. Fetch Section Averages (Cumulative GWA is already 1.00-4.00)
    $stmtStats = $db->prepare("
        SELECT sp.section, 
               COUNT(sp.user_id) AS total,
               AVG(sp.current_gwa) AS avg_gwa
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        WHERE sp.section IN ($inQuery) AND u.role = 'student'
        GROUP BY sp.section
        ORDER BY sp.section
    ");
    $stmtStats->execute($my_sections);
    $section_stats_raw = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    // Initialize stats array
    foreach ($section_stats_raw as $row) {
        $row['high_risk'] = 0;
        $row['mod_risk'] = 0;
        $section_stats[$row['section']] = $row;
    }

    // 3. Calculate At-Risk strictly for THIS faculty's classes using normalization
    $stmtGrades = $db->prepare("
        SELECT sp.section, g.student_id, g.prelim
        FROM grades g
        JOIN student_profiles sp ON sp.user_id = g.student_id
        JOIN faculty_class_loads fcl ON fcl.subject_id = g.subject_id AND fcl.section = sp.section
        WHERE fcl.faculty_user_id = ? AND g.is_current = 1
    ");
    $stmtGrades->execute([$user['id']]);
    $gradeRows = $stmtGrades->fetchAll();

    // Track unique worst-case risk per student per section
    $student_risk_map = []; 
    foreach ($gradeRows as $r) {
        $sec = $r['section'];
        $sid = $r['student_id'];
        
        // Convert raw percentage to point grade safely!
        $point = normalizeTermGrade($r['prelim']);
        
        if ($point !== null) {
            $risk = computeRiskFromAvg($point);
            if ($risk !== 'LOW') {
                if (!isset($student_risk_map[$sec][$sid])) {
                    $student_risk_map[$sec][$sid] = $risk;
                } else {
                    // Upgrade to HIGH if they are already tracked as MODERATE
                    if ($risk === 'HIGH') $student_risk_map[$sec][$sid] = 'HIGH';
                }
            }
        }
    }

    // Tally the unique at-risk students into the main stats array
    foreach ($student_risk_map as $sec => $students) {
        foreach ($students as $sid => $worstRisk) {
            if ($worstRisk === 'HIGH') {
                $section_stats[$sec]['high_risk']++;
            } else {
                $section_stats[$sec]['mod_risk']++;
            }
        }
    }

    // 4. Top 10 Students by GWA
    $stmtTop = $db->prepare("
        SELECT u.name, sp.section, sp.current_gwa
        FROM users u
        JOIN student_profiles sp ON u.id = sp.user_id
        WHERE sp.section IN ($inQuery) AND u.role = 'student' AND sp.current_gwa IS NOT NULL
        ORDER BY sp.current_gwa DESC
        LIMIT 10
    ");
    $stmtTop->execute($my_sections);
    $top_students = $stmtTop->fetchAll();

    // 5. My Subject Averages — current term only. NOTE: this is a single-term
    // snapshot, not a multi-term trend — the seed data only has one current
    // term of grades tied to faculty_class_loads, so there's nothing to plot
    // a trajectory against yet. Once a second term exists, this same query
    // extends naturally into a real per-load time series.
    $stmt = $db->prepare("SELECT id AS load_id, subject_id, section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section, subject_id");
    $stmt->execute([$user['id']]);
    $my_loads = $stmt->fetchAll();

    $subjectStmt = $db->prepare("SELECT title FROM subjects WHERE id = ?");
    $gradeStmt = $db->prepare("
        SELECT g.prelim
        FROM grades g
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1
    ");

    $subject_load_stats = [];
    foreach ($my_loads as $load) {
        $subjectStmt->execute([$load['subject_id']]);
        $title = $subjectStmt->fetchColumn();

        $gradeStmt->execute([$load['subject_id'], $load['section']]);
        $points = array_filter(
            array_map(fn($r) => normalizeTermGrade($r['prelim']), $gradeStmt->fetchAll()),
            fn($p) => $p !== null
        );
        if (empty($points)) continue;

        $subject_load_stats[] = [
            'label' => $title . ' (' . $load['section'] . ')',
            'avg'   => round(array_sum($points) / count($points), 2),
        ];
    }
}

$subject_load_stats = $subject_load_stats ?? [];

// Prepare Data for Chart.js
$chart1_labels = [];
$chart1_data = [];
$chart1_colors = [];

foreach ($section_stats as $stat) {
    $chart1_labels[] = $stat['section'];
    $gwa = round((float)$stat['avg_gwa'], 2);
    $chart1_data[] = $gwa;
    $chart1_colors[] = $gwa >= 3.25 ? '#059669' : ($gwa >= 2.50 ? '#d97706' : '#b91c1c');
}

$chart2_labels = [];
$chart2_data = [];
$chart2_colors = [];

foreach ($top_students as $stu) {
    // Professional initial/surname formatting for high chart clarity
    $nameParts = explode(' ', trim($stu['name']));
    if (count($nameParts) > 1) {
        $displayName = substr($nameParts[0], 0, 1) . '. ' . end($nameParts);
    } else {
        $displayName = $nameParts[0];
    }
    
    $chart2_labels[] = $displayName . ' (' . $stu['section'] . ')';
    $gwa = round((float)$stu['current_gwa'], 2);
    $chart2_data[] = $gwa;
    
    if ($gwa >= 3.75) $chart2_colors[] = '#b45309'; 
    elseif ($gwa >= 3.50) $chart2_colors[] = '#1d4ed8'; 
    elseif ($gwa >= 3.25) $chart2_colors[] = '#0e7490'; 
    else $chart2_colors[] = '#475569'; 
}

$pageTitle = 'Performance Trends';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation"></script>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Performance Trends</h1>
            <p style="color: var(--text-gray); font-size: 0.95rem;">Visualized metrics and trajectory analysis across assigned section cohorts.</p>
        </div>
    </div>

    <?php if (empty($my_sections)): ?>
        <div class="card"><p class="empty-state">No section class loads assigned to your account.</p></div>
    <?php else: ?>

    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 6px;">
            <div>
                <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700;">Section Average GWA Comparison</h3>
                <p style="color: #64748b; font-size: 0.85rem; margin: 0;">S.Y. 2026-2027, 1st Semester</p>
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 18px; font-size: 0.85rem; color: #334155;">
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#059669; margin-right:6px; vertical-align:middle;"></span>Cum Laude+ (≥3.25)</span>
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#d97706; margin-right:6px; vertical-align:middle;"></span>Very Satisfactory (≥2.50)</span>
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#b91c1c; margin-right:6px; vertical-align:middle;"></span>Below 2.50</span>
            </div>
        </div>
        <p style="color: #94a3b8; font-size: 0.75rem; margin: 0 0 12px;">Bar color reflects each section's average tier; dashed lines mark the exact GWA cutoffs.</p>
        <div style="position: relative; height: 280px; width: 100%;">
            <canvas id="sectionChart"></canvas>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 6px;">
            <div>
                <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 6px;">🏆 Top Students — Honor Tier Breakdown</h3>
                <span style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">Projected Honor Eligibility</span>
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 18px; font-size: 0.85rem; color: #334155;">
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#b45309; margin-right:6px; vertical-align:middle;"></span>Summa (≥3.75)</span>
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#1d4ed8; margin-right:6px; vertical-align:middle;"></span>Magna (≥3.50)</span>
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#0e7490; margin-right:6px; vertical-align:middle;"></span>Dean's Lister (≥3.25)</span>
                <span><span style="display:inline-block; width:10px; height:10px; border-radius:2px; background:#475569; margin-right:6px; vertical-align:middle;"></span>Below 3.25</span>
            </div>
        </div>
        <p style="color: #94a3b8; font-size: 0.75rem; margin: 0 0 12px;">Bar color shows each student's own projected tier; dashed lines mark the exact GWA cutoffs.</p>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="topStudentsChart"></canvas>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div style="margin-bottom: 4px;">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700;">My Subject Averages</h3>
        </div>
        <p style="color: #64748b; font-size: 0.8rem; margin: 0 0 16px;">
            Average prelim grade in the subjects <em>you specifically teach</em>, per section — unlike the chart above, this reflects only your own class loads, not students' overall standing across all their subjects.
            Current-term snapshot only; becomes a real trend line once a second term of grades exists.
        </p>
        <?php if (empty($subject_load_stats)): ?>
            <p class="empty-state">No current-term grades encoded yet for your class loads.</p>
        <?php else: ?>
        <div style="position: relative; height: <?= max(200, count($subject_load_stats) * 40) ?>px; width: 100%;">
            <canvas id="myLoadsChart"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">At-Risk Count per Section <span style="font-size: 0.8rem; color: #64748b; font-weight: 400;">(In your assigned subjects)</span></h3>
        
        <?php foreach ($section_stats as $stat): 
            $total_risk = (int)$stat['high_risk'] + (int)$stat['mod_risk'];
            $risk_pct = $stat['total'] > 0 ? ($total_risk / (int)$stat['total']) * 100 : 0;
            $fill_color = (int)$stat['high_risk'] > 0 ? '#b91c1c' : '#d97706';
            if ($total_risk == 0) { $fill_color = '#059669'; $risk_pct = 0; }
        ?>
        <div style="display: flex; align-items: center; margin-bottom: 12px; font-size: 0.9rem;">
            <span style="width: 70px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($stat['section']) ?></span>
            <span style="width: 80px; color: #0e7490; font-weight: 500;">Avg: <?= number_format($stat['avg_gwa'] ?? 0, 2) ?></span>
            
            <div style="flex: 1; max-width: 280px; height: 12px; background: #f1f5f9; border-radius: 4px; margin: 0 16px; overflow: hidden;">
                <?php if($total_risk > 0): ?>
                    <div style="width: <?= $risk_pct ?>%; height: 100%; background: <?= $fill_color ?>; border-radius: 4px;"></div>
                <?php endif; ?>
            </div>
            
            <span style="color: <?= $total_risk > 0 ? $fill_color : '#059669' ?>; font-weight: 500; font-size: 0.85rem;">
                <?= $total_risk ?> at-risk 
                <span style="color: #64748b; font-weight: normal;">(<?= (int)$stat['high_risk'] ?> HIGH, <?= (int)$stat['mod_risk'] ?> MODERATE)</span>
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<script>
const ctx1 = document.getElementById('sectionChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart1_labels) ?>,
        datasets: [{
            label: 'Average GWA',
            data: <?= json_encode($chart1_data) ?>,
            backgroundColor: <?= json_encode($chart1_colors) ?>,
            borderRadius: 6,
            barPercentage: 0.45
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            annotation: {
                annotations: {
                    summaLine: {
                        type: 'line', yMin: 3.75, yMax: 3.75,
                        borderColor: '#b45309', borderWidth: 2, borderDash: [4, 4]
                    },
                    magnaLine: {
                        type: 'line', yMin: 3.50, yMax: 3.50,
                        borderColor: '#1d4ed8', borderWidth: 2, borderDash: [4, 4]
                    },
                    cumLine: {
                        type: 'line', yMin: 3.25, yMax: 3.25,
                        borderColor: '#0e7490', borderWidth: 2, borderDash: [4, 4]
                    }
                }
            }
        },
        scales: {
            y: { min: 0, max: 4.0, ticks: { stepSize: 0.5, callback: v => v.toFixed(2) }, title: { display: true, text: 'Average GWA (4.00 = Highest)' } }
        }
    }
});

const ctx2 = document.getElementById('topStudentsChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart2_labels) ?>,
        datasets: [{
            label: 'Projected GWA',
            data: <?= json_encode($chart2_data) ?>,
            backgroundColor: <?= json_encode($chart2_colors) ?>,
            borderRadius: 6,
            barPercentage: 0.55
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            annotation: {
                annotations: {
                    summaLine: {
                        type: 'line', yMin: 3.75, yMax: 3.75,
                        borderColor: '#b45309', borderWidth: 1.5, borderDash: [3, 3]
                    },
                    magnaLine: {
                        type: 'line', yMin: 3.50, yMax: 3.50,
                        borderColor: '#1d4ed8', borderWidth: 1.5, borderDash: [3, 3]
                    },
                    dlLine: {
                        type: 'line', yMin: 3.25, yMax: 3.25,
                        borderColor: '#0e7490', borderWidth: 1.5, borderDash: [5, 5]
                    }
                }
            }
        },
        scales: {
            y: { min: 2.0, max: 4.0, ticks: { stepSize: 0.25, callback: v => v.toFixed(2) }, title: { display: true, text: 'GWA (4.00 = Highest)' } },
            x: { ticks: { font: { size: 9 } } }
        }
    }
});

const loadLabels = <?= json_encode(array_column($subject_load_stats, 'label')) ?>;
const loadData = <?= json_encode(array_column($subject_load_stats, 'avg')) ?>;
const loadColors = loadData.map(v => v >= 2.50 ? '#059669' : (v >= 1.75 ? '#d97706' : '#b91c1c'));

if (document.getElementById('myLoadsChart')) {
    new Chart(document.getElementById('myLoadsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: loadLabels,
            datasets: [{
                label: 'Average Prelim (Point Scale)',
                data: loadData,
                backgroundColor: loadColors,
                borderRadius: 4,
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { min: 0, max: 4.0, ticks: { stepSize: 0.5 }, title: { display: true, text: 'Average Prelim Grade' } }
            }
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>