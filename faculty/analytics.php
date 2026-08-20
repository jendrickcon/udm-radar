<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

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
            if ($risk !== 'LOW') {
                $r['prelim_point'] = $pointGrade;
                $r['risk_level'] = $risk;
                $atRiskGrouped[$load['load_id']][] = $r;
            }
        }
    }

    $load['class_avg'] = !empty($points) ? array_sum($points) / count($points) : 0;
    $load['total_encoded'] = count($points);
    $load['at_risk_count'] = count($atRiskGrouped[$load['load_id']]);
    $class_loads[] = $load;
}

$pageTitle = 'Class Analytics';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Encode Grades',      'grades.php',    '📝'],
    ['Concerns & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.accordion-wrapper { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.accordion-wrapper.open { grid-template-rows: 1fr; }
.accordion-inner { min-height: 0; overflow: hidden; opacity: 0; transform: translateY(-10px); transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.accordion-wrapper.open .accordion-inner { opacity: 1; transform: translateY(0); }
</style>

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
                $class_avg = (float)$load['class_avg'];
                $encoded_total = (int)$load['total_encoded'];
                $at_risk_total = (int)$load['at_risk_count'];
                
                $risk_label = $encoded_total > 0 ? computeRiskFromAvg($class_avg) : 'N/A';
                $risk_color = $risk_label === 'HIGH' ? 'var(--risk-high)' : ($risk_label === 'MODERATE' ? 'var(--risk-mod)' : ($risk_label === 'N/A' ? 'var(--text-gray)' : 'var(--accent-blue)'));
                $fill_pct = $class_avg > 0 ? min(100, max(6, ($class_avg / 4.0) * 100)) : 0;
                $risk_students = $atRiskGrouped[$load_id] ?? [];
            ?>
            <div class="card" style="padding: 0; margin-bottom: 20px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.02); overflow: hidden;">
                <div style="padding: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div style="flex: 1; min-width: 280px;">
                        <span style="background:var(--table-header-bg); color:var(--text-dark); padding:6px 14px; border-radius:6px; font-size:0.9rem; font-weight:700; display:inline-block; margin-bottom:10px; border: 1px solid var(--border-color);"><?= htmlspecialchars($load['section']) ?></span>
                        <h3 style="color: var(--text-dark); font-size: 1.1rem; margin: 0 0 6px 0; font-weight: 700;"><?= htmlspecialchars($load['code'] . ' — ' . $load['title']) ?></h3>
                        <?php $risk_pct = $encoded_total > 0 ? round(($at_risk_total / $encoded_total) * 100) : 0; ?>
                        <p style="color: var(--text-gray); font-size: 0.85rem; margin: 0;">
                            Mean Prelim Grade: <strong style="color: var(--text-dark);"><?= $encoded_total > 0 ? number_format($class_avg, 2) : 'No Grades Encoded' ?></strong> &nbsp;|&nbsp; 
                            At-risk: <strong style="color: <?= $at_risk_total > 0 ? 'var(--risk-mod)' : 'var(--text-dark)' ?>;"><?= $at_risk_total ?> / <?= $encoded_total ?></strong> encoded students (<?= $risk_pct ?>%)
                        </p>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 15px; flex: 1; min-width: 250px; justify-content: flex-end; flex-wrap: wrap;">
                        <div style="width: 160px; height: 14px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden;">
                            <div style="width: <?= $fill_pct ?>%; height: 100%; background: <?= $risk_color ?>; border-radius: 6px;"></div>
                        </div>
                        <span style="background: <?= $risk_color ?>; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; width: 85px; text-align: center;">
                            <?= $risk_label ?>
                        </span>
                        
                        <button onclick="toggleRisk(<?= $load_id ?>)" style="background: none; border: none; color: var(--accent-blue); font-size: 0.85rem; cursor: pointer; font-weight: 600; width: 145px; display: flex; align-items: center; gap: 4px; padding: 4px; font-family: inherit;">
                            <span id="toggle-icon-<?= $load_id ?>" style="display: inline-block; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);">▶</span> 
                            <span id="toggle-text-<?= $load_id ?>">Show at-risk students</span>
                        </button>
                    </div>
                </div>
                
                <div id="risk-wrapper-<?= $load_id ?>" class="accordion-wrapper">
                    <div class="accordion-inner">
                        <div style="padding: 0 20px 20px 20px; border-top: 1px solid var(--border-color);">
                            <?php if (empty($risk_students)): ?>
                                <p style="color: var(--risk-low); font-size: 0.9rem; padding-top: 15px; margin: 0; font-weight: 600;">✓ No at-risk students for this class load.</p>
                            <?php else: ?>
                                <div style="padding-top: 15px;">
                                    <?php foreach($risk_students as $stu): 
                                        $s_col = $stu['risk_level'] === 'HIGH' ? 'var(--risk-high)' : 'var(--risk-mod)';
                                    ?>
                                    <div style="display: flex; align-items: center; background: var(--bg-color); border: 1px solid var(--border-color); padding: 10px 14px; margin-bottom: 6px; border-radius: 6px; font-size: 0.9rem;">
                                        <span style="flex: 1; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars(formatNameLastFirst($stu['first_name'], $stu['middle_name'], $stu['last_name'])) ?></span>
                                        <span style="width: 130px; color: var(--text-gray); font-family: monospace;"><?= htmlspecialchars($stu['student_number']) ?></span>
                                        <span style="width: 110px; font-weight: 700; color: <?= $s_col ?>;">Grade: <?= number_format($stu['prelim_point'], 2) ?></span>
                                        <span style="background: <?= $s_col ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; width: 80px; text-align: center;">
                                            <?= $stu['risk_level'] ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleRisk(id) {
    const wrapper = document.getElementById('risk-wrapper-' + id);
    const icon = document.getElementById('toggle-icon-' + id);
    const text = document.getElementById('toggle-text-' + id);
    
    if (wrapper.classList.contains('open')) {
        wrapper.classList.remove('open');
        icon.style.transform = 'rotate(0deg)';
        text.innerText = 'Show at-risk students';
    } else {
        wrapper.classList.add('open');
        icon.style.transform = 'rotate(90deg)';
        text.innerText = 'Hide students';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>