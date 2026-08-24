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
    ORDER BY s.title, fcl.section
");
$stmtLoads->execute([$user['id']]);
$raw_loads = $stmtLoads->fetchAll();

$stmtGrades = $db->prepare("
    SELECT g.prelim, g.midterm, g.prefinal, g.final_grade, u.first_name, u.middle_name, u.last_name, sp.student_number
    FROM grades g
    JOIN student_profiles sp ON sp.user_id = g.student_id
    JOIN users u ON u.id = sp.user_id
    WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1
    ORDER BY u.last_name, u.first_name
");

$class_loads = [];
$atRiskGrouped = [];

$total_students_portfolio = 0;
$total_encoded_portfolio = 0;
$total_at_risk_portfolio = 0;

foreach ($raw_loads as $load) {
    $stmtGrades->execute([$load['subj_id'], $load['section']]);
    $rawRows = $stmtGrades->fetchAll();

    $points_pct = [];
    $points_pt = [];
    $atRiskGrouped[$load['load_id']] = [];

    foreach ($rawRows as $r) {
        $latestVal = null;
        $latestType = '';
        if ($r['final_grade'] !== null && trim((string)$r['final_grade']) !== '') { $latestVal = $r['final_grade']; $latestType = 'final_grade'; }
        elseif ($r['prefinal'] !== null && trim((string)$r['prefinal']) !== '') { $latestVal = $r['prefinal']; $latestType = 'prefinal'; }
        elseif ($r['midterm'] !== null && trim((string)$r['midterm']) !== '') { $latestVal = $r['midterm']; $latestType = 'midterm'; }
        elseif ($r['prelim'] !== null && trim((string)$r['prelim']) !== '') { $latestVal = $r['prelim']; $latestType = 'prelim'; }

        if ($latestVal !== null) {
            $pt = ($latestType === 'final_grade') ? normalizePointGrade($latestVal) : normalizeTermGrade($latestVal);
            
            if ($pt !== null) {
                if ($latestType !== 'final_grade') {
                    $points_pct[] = (float)$latestVal;
                }
                $points_pt[] = $pt;
                
                $risk = computeRiskFromAvg($pt);
                if ($risk !== 'LOW') {
                    $r['latest_raw'] = $latestVal;
                    $r['latest_type'] = $latestType;
                    $r['risk_level'] = $risk;
                    $atRiskGrouped[$load['load_id']][] = $r;
                }
            }
        }
    }

    $load['class_avg_pct'] = !empty($points_pct) ? array_sum($points_pct) / count($points_pct) : 0;
    $load['total_encoded'] = count($points_pt);
    $load['at_risk_count'] = count($atRiskGrouped[$load['load_id']]);
    
    $total_encoded_portfolio += $load['total_encoded'];
    $total_at_risk_portfolio += $load['at_risk_count'];
    $total_students_portfolio += $load['total_students'];
    
    $class_loads[] = $load;
}

$completeness_pct = $total_students_portfolio > 0 ? round(($total_encoded_portfolio / $total_students_portfolio) * 100) : 0;
$total_assigned_classes = count($class_loads);

$stmtUnique = $db->prepare("
    SELECT COUNT(DISTINCT sp.user_id) 
    FROM student_profiles sp
    JOIN faculty_class_loads fcl ON sp.section = fcl.section
    WHERE fcl.faculty_user_id = ?
");
$stmtUnique->execute([$user['id']]);
$unique_students = $stmtUnique->fetchColumn() ?: 0;

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
.accordion-wrapper { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.accordion-wrapper.open { grid-template-rows: 1fr; }
.accordion-inner { min-height: 0; overflow: hidden; opacity: 0; transform: translateY(-5px); transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); pointer-events: none; }
.accordion-wrapper.open .accordion-inner { opacity: 1; transform: translateY(0); pointer-events: auto; }

.student-identity { display: flex; align-items: baseline; gap: 12px; min-width: 0; }
.student-name { color: var(--text-dark); font-weight: 600; }
.student-number { color: var(--text-gray); font-family: monospace; font-size: 0.78rem; white-space: nowrap; }
.risk-student-grid { display: grid; grid-template-columns: minmax(280px, 1fr) 120px 110px; gap: 20px; align-items: center; }

@media (max-width: 650px) {
    .risk-student-grid { grid-template-columns: 1fr auto; }
    .student-identity { align-items: flex-start; flex-direction: column; gap: 2px; }
    .mobile-hide { display: none; }
}
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Class Analytics</h1>
            <p style="color: var(--text-gray); font-size: 0.95rem;">Assigned subject and section performance breakdown.</p>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card" style="border-left: 4px solid var(--text-dark);">
            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Assigned Class Loads</h4>
            <h2 style="margin: 8px 0 0; color: var(--text-dark); font-size: 2rem;"><?= $total_assigned_classes ?></h2>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--accent-blue);">
            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Unique Students Reached</h4>
            <h2 style="margin: 8px 0 0; color: var(--text-dark); font-size: 2rem;"><?= $unique_students ?></h2>
            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Grade Coverage: <span style="color: var(--accent-blue);"><?= $completeness_pct ?>% of assigned class records</span></div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--risk-high);">
            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">At-Risk Subject Enrollments</h4>
            <h2 style="margin: 8px 0 0; color: var(--risk-high); font-size: 2rem;"><?= $total_at_risk_portfolio ?></h2>
        </div>
    </div>

    <?php if (empty($class_loads)): ?>
        <div class="card"><p class="empty-state">No class loads assigned to your account.</p></div>
    <?php else: ?>
        <h3 style="color: var(--text-dark); font-size: 1.1rem; margin: 32px 0 16px 0; font-weight: 700;">Individual Class Reports</h3>
        
        <?php foreach ($class_loads as $load): 
            $load_id = $load['load_id']; 
            $class_avg_pct = (float)$load['class_avg_pct'];
            $encoded_total = (int)$load['total_encoded'];
            $total_stu = (int)$load['total_students'];
            $at_risk_total = (int)$load['at_risk_count'];
            $risk_students = $atRiskGrouped[$load_id] ?? [];
        ?>
        <div class="card" style="padding: 24px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div style="max-width: 70%;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--accent-blue); margin-bottom: 4px;"><?= htmlspecialchars($load['section']) ?></div>
                    <!-- FIXED: Using title only for the card header -->
                    <h3 style="color: var(--text-dark); font-size: 1.15rem; margin: 0 0 6px 0; font-weight: 700;"><?= htmlspecialchars($load['title']) ?></h3>
                    
                    <p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">
                        <strong style="color: var(--text-dark);"><?= $encoded_total ?></strong> encoded &middot; 
                        Mean Score <strong style="color: var(--text-dark);"><?= $class_avg_pct > 0 ? round($class_avg_pct) . '%' : '—' ?></strong> &middot; 
                        <strong style="color: <?= $at_risk_total > 0 ? 'var(--risk-mod)' : 'var(--text-dark)' ?>;"><?= $at_risk_total ?></strong> requiring attention
                        
                        <?php if ($encoded_total < $total_stu): ?>
                            <span style="color: var(--risk-mod); font-weight: 600; margin-left: 8px;">(<?= $encoded_total ?> of <?= $total_stu ?> grades encoded)</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <button onclick="toggleRisk(<?= $load_id ?>)" id="toggle-btn-<?= $load_id ?>" style="background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-dark); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-family: inherit; transition: background 0.2s; min-width: 130px;">
                        View students
                    </button>
                </div>
            </div>
            
            <div id="risk-wrapper-<?= $load_id ?>" class="accordion-wrapper">
                <div class="accordion-inner">
                    <div style="border-top: 1px solid var(--border-color); margin-top: 20px; padding-top: 12px;">
                        <?php if (empty($risk_students)): ?>
                            <p style="color: var(--risk-low); font-size: 0.9rem; margin: 0; padding: 8px 0; font-weight: 600;">✓ No students currently flagged as At-Risk for this class load.</p>
                        <?php else: ?>
                            <div class="risk-student-grid" style="padding: 12px 14px 8px 14px; color: var(--text-gray); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border-color);">
                                <span>Student</span>
                                <span style="text-align: center;">Latest Grade</span>
                                <span class="mobile-hide" style="text-align: center;">Risk Status</span>
                            </div>
                            
                            <?php foreach($risk_students as $stu): 
                                $s_col = $stu['risk_level'] === 'HIGH' ? 'var(--risk-high)' : 'var(--risk-mod)';
                                $valDisp = $stu['latest_type'] === 'final_grade' && in_array(strtoupper(trim($stu['latest_raw'])), ['INC', 'DO', 'DU', 'FA', 'UD']) 
                                           ? strtoupper(trim($stu['latest_raw'])) 
                                           : ($stu['latest_type'] === 'final_grade' ? number_format((float)$stu['latest_raw'], 2) : round((float)$stu['latest_raw']) . '%');
                            ?>
                            <div class="risk-student-grid" style="padding: 12px 14px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem;">
                                <div class="student-identity">
                                    <span class="student-name">
                                        <?= htmlspecialchars(formatNameLastFirst($stu['first_name'], $stu['middle_name'], $stu['last_name'])) ?>
                                    </span>
                                    <span class="student-number">
                                        <?= htmlspecialchars($stu['student_number']) ?>
                                    </span>
                                </div>
                                
                                <span style="font-weight: 600; color: var(--text-dark); text-align: center; font-variant-numeric: tabular-nums;">
                                    <?= $valDisp ?> <span style="color: var(--text-gray); font-size: 0.75rem; font-weight: 400;"><?= $stu['latest_type'] === 'final_grade' ? '(FIN)' : '' ?></span>
                                </span>
                                
                                <span style="background: <?= $s_col ?>; color: white; padding: 4px 0; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-align: center; display: block; width: 100%;">
                                    <?= $stu['risk_level'] ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleRisk(id) {
    const wrapper = document.getElementById('risk-wrapper-' + id);
    const btn = document.getElementById('toggle-btn-' + id);
    
    if (wrapper.classList.contains('open')) {
        wrapper.classList.remove('open');
        btn.innerText = 'View students';
    } else {
        wrapper.classList.add('open');
        btn.innerText = 'Hide students';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>