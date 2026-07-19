<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

// 1. Fetch assigned subject-section class loads
$stmtLoads = $db->prepare("
    SELECT fcl.id AS load_id, s.id AS subj_id, s.code, s.title, fcl.section,
           (SELECT COUNT(user_id) FROM student_profiles WHERE section = fcl.section) AS total_students
    FROM faculty_class_loads fcl
    JOIN subjects s ON fcl.subject_id = s.id
    WHERE fcl.faculty_user_id = ?
    ORDER BY fcl.section, s.code
");
$stmtLoads->execute([$user['id']]);
$raw_loads = $stmtLoads->fetchAll();

// 2. Prepare the query to fetch raw grades per load
$stmtGrades = $db->prepare("
    SELECT g.prelim, u.first_name, u.middle_name, u.last_name, sp.student_number
    FROM grades g
    JOIN student_profiles sp ON sp.user_id = g.student_id
    JOIN users u ON u.id = sp.user_id
    WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1
    ORDER BY u.last_name, u.first_name
");

$class_loads = [];
$atRiskGrouped = [];

// 3. Process grades through normalizeTermGrade() in PHP
foreach ($raw_loads as $load) {
    $stmtGrades->execute([$load['subj_id'], $load['section']]);
    $rawRows = $stmtGrades->fetchAll();

    $points = [];
    $atRiskGrouped[$load['load_id']] = [];

    foreach ($rawRows as $r) {
        $pointGrade = normalizeTermGrade($r['prelim']);
        if ($pointGrade !== null) {
            $points[] = $pointGrade;
            $risk = computeRiskFromAvg($pointGrade);
            
            // Only group them if they are actually at risk
            if ($risk !== 'LOW') {
                $r['prelim_point'] = $pointGrade;
                $r['risk_level'] = $risk;
                $atRiskGrouped[$load['load_id']][] = $r;
            }
        }
    }

    $load['class_avg'] = !empty($points) ? array_sum($points) / count($points) : 0;
    $load['at_risk_count'] = count($atRiskGrouped[$load['load_id']]);
    $class_loads[] = $load;
}

$pageTitle = 'Class Analytics';
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

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Class Analytics</h1>
            <p style="color: var(--text-gray); font-size: 0.95rem;">Assigned subject and section performance breakdown.</p>
        </div>
    </div>

    <div style="max-width: 1000px;">
        <?php if (empty($class_loads)): ?>
            <div class="card"><p class="empty-state">No class loads assigned to your account.</p></div>
        <?php else: ?>
            <?php foreach ($class_loads as $load): 
                $load_id = $load['load_id']; 
                $class_avg = (float)($load['class_avg'] ?? 0);
                $at_risk_total = (int)$load['at_risk_count'];
                
                // Use the centralized helper for the overall class trajectory
                $risk_label = $class_avg > 0 ? computeRiskFromAvg($class_avg) : 'LOW';
                $risk_color = $risk_label === 'HIGH' ? 'var(--risk-high)' : ($risk_label === 'MODERATE' ? 'var(--risk-mod)' : 'var(--teal)');
                $fill_pct = $class_avg > 0 ? min(100, max(6, ($class_avg / 4.0) * 100)) : 0;
                $risk_students = $atRiskGrouped[$load_id] ?? [];
            ?>
            <div class="card" style="padding: 0; margin-bottom: 15px; border: 1px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                <div style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="flex: 1;">
                        <span style="background:#0f172a; color:white; padding:4px 12px; border-radius:6px; font-size:0.9rem; font-weight:700; display:inline-block; margin-bottom:6px; letter-spacing: 0.5px;"><?= htmlspecialchars($load['section']) ?></span>
                        <h3 style="color: var(--sidebar-bg); font-size: 1.1rem; margin: 6px 0 4px 0; font-weight: 700;"><?= htmlspecialchars($load['code'] . ' — ' . $load['title']) ?></h3>
                        <?php 
                            $total_stu = (int)$load['total_students'];
                            $risk_pct = $total_stu > 0 ? round(($at_risk_total / $total_stu) * 100) : 0;
                        ?>
                        <p style="color: var(--text-gray); font-size: 0.85rem; margin: 0;">
                            Class avg: <strong style="color: var(--text-dark);"><?= $class_avg > 0 ? number_format($class_avg, 2) : 'No Grades' ?></strong> &nbsp;|&nbsp; 
                            At-risk: <strong style="color: <?= $at_risk_total > 0 ? 'var(--risk-mod)' : 'var(--text-dark)' ?>;"><?= $at_risk_total ?> / <?= $total_stu ?></strong> students (<?= $risk_pct ?>%)
                        </p>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 15px; width: 440px; justify-content: flex-end;">
                        <div style="width: 160px; height: 14px; background: var(--pale_teal, #f1f5f9); border-radius: 6px; overflow: hidden;">
                            <div style="width: <?= $fill_pct ?>%; height: 100%; background: <?= $risk_color ?>; border-radius: 6px;"></div>
                        </div>
                        <span style="background: <?= $risk_color ?>; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; width: 85px; text-align: center;">
                            <?= $risk_label ?>
                        </span>
                        <span id="toggle-btn-<?= $load_id ?>" onclick="toggleRisk(<?= $load_id ?>)" style="color: var(--teal); font-size: 0.85rem; cursor: pointer; font-weight: 500; width: 140px;">
                            ▶ Show at-risk students
                        </span>
                    </div>
                </div>
                
                <div id="risk-list-<?= $load_id ?>" style="display: none; padding: 0 20px 20px 20px; border-top: 1px solid #f0f0f0;">
                    <?php if (empty($risk_students)): ?>
                        <p style="color: var(--risk-low); font-size: 0.9rem; padding-top: 15px; margin: 0; font-weight: 500;">✓ No at-risk students for this class load.</p>
                    <?php else: ?>
                        <div style="padding-top: 15px;">
                            <?php foreach($risk_students as $stu): 
                                // Pull the exact risk level and point grade we calculated in the PHP block
                                $s_risk = $stu['risk_level'];
                                $s_col = $s_risk === 'HIGH' ? 'var(--risk-high)' : 'var(--risk-mod)';
                            ?>
                            <div style="display: flex; align-items: center; background: #f8f9fa; padding: 10px 14px; margin-bottom: 4px; border-radius: 6px; font-size: 0.9rem;">
                                <span style="flex: 1; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars(formatNameLastFirst($stu['first_name'], $stu['middle_name'], $stu['last_name'])) ?></span>
                                <span style="width: 130px; color: var(--text-gray); font-family: monospace;"><?= htmlspecialchars($stu['student_number']) ?></span>
                                <span style="width: 110px; font-weight: 700; color: <?= $s_col ?>;">Grade: <?= number_format($stu['prelim_point'], 2) ?></span>
                                <span style="background: <?= $s_col ?>; color: white; padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; width: 80px; text-align: center;">
                                    <?= $s_risk ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleRisk(id) {
    var el = document.getElementById('risk-list-' + id);
    var btn = document.getElementById('toggle-btn-' + id);
    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
        btn.innerHTML = '▼ Hide students';
    } else {
        el.style.display = 'none';
        btn.innerHTML = '▶ Show at-risk students';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>