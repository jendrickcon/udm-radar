<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/faculty_assessment_repository.php';

requireRole('faculty');
header('Content-Type: text/html; charset=UTF-8');
$user = currentUser();
$db   = getDB();
$currentTerm = getCurrentTerm();

$stmtConcernCount = $db->prepare("
    SELECT COUNT(DISTINCT f.id)
    FROM feedback_reports f
    JOIN users student_user ON student_user.id = f.submitted_by AND student_user.role = 'student'
    JOIN student_profiles sp ON sp.user_id = f.submitted_by
    JOIN faculty_class_loads fcl ON fcl.subject_id = f.subject_id AND fcl.section = sp.section
    WHERE fcl.faculty_user_id = ? AND f.status IN ('open', 'faculty_review') AND sp.record_status = 'Active'
");
$stmtConcernCount->execute([$user['id']]);
$openConcernCount = (int) $stmtConcernCount->fetchColumn();

$stmtPendingBatchCount = $db->prepare("
    SELECT COUNT(*)
    FROM pending_grade_batches
    WHERE faculty_id = ? AND status = 'pending'
");
$stmtPendingBatchCount->execute([$user['id']]);
$pendingBatchCount = (int) $stmtPendingBatchCount->fetchColumn();

$stmtSupportReviewCount = $db->prepare("
    SELECT COUNT(DISTINCT support_case.id)
    FROM academic_support_cases support_case
    JOIN student_profiles sp ON sp.user_id = support_case.student_id
    JOIN grades g ON g.student_id = support_case.student_id AND g.is_current = 1
    JOIN faculty_class_loads fcl ON fcl.subject_id = g.subject_id AND fcl.section = sp.section
    WHERE fcl.faculty_user_id = ? AND support_case.status = 'needs_review' AND sp.record_status = 'Active'
");
$stmtSupportReviewCount->execute([$user['id']]);
$supportReviewCount = (int) $stmtSupportReviewCount->fetchColumn();

$facultyActionCount = $openConcernCount + $pendingBatchCount + $supportReviewCount;

$stmt = $db->prepare("SELECT DISTINCT section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section");
$stmt->execute([$user['id']]);
$my_sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $db->prepare("
    SELECT s.id, s.code, s.title, fcl.section
    FROM faculty_class_loads fcl
    JOIN subjects s ON fcl.subject_id = s.id
    WHERE fcl.faculty_user_id = ?
    ORDER BY fcl.section, s.code
");
$stmt->execute([$user['id']]);
$my_class_loads = $stmt->fetchAll();

$section_subject_map = [];
foreach ($my_class_loads as $load) {
    $sec     = $load['section'];
    $already = array_column($section_subject_map[$sec] ?? [], 'id');
    if (!in_array($load['id'], $already)) {
        $section_subject_map[$sec][] = [
            'id'    => $load['id'],
            'code'  => $load['code'],
            'title' => $load['title'],
        ];
    }
}

$students = [];
if (!empty($my_sections)) {
    $inSec = implode(',', array_fill(0, count($my_sections), '?'));
    $predMetaCols = getPredictionCompletenessSqlSelect($db, 'p');
    $stmt  = $db->prepare("
        SELECT u.id AS user_id, u.first_name, u.middle_name, u.last_name, sp.section, sp.student_number,
               sp.status, sp.current_gwa,
               p.risk_level, p.latin_honor, $predMetaCols
        FROM users u
        JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN predictions p ON p.id = (
            SELECT p2.id
            FROM predictions p2
            WHERE p2.student_id = u.id
            ORDER BY p2.generated_at DESC, p2.id DESC
            LIMIT 1
        )
        WHERE sp.section IN ($inSec) AND u.role = 'student' AND u.is_active=1 AND sp.record_status = 'Active'
        AND EXISTS(SELECT 1 FROM grades own_grade JOIN faculty_class_loads own_load ON own_load.subject_id=own_grade.subject_id AND own_load.section=sp.section
            WHERE own_grade.student_id=u.id AND own_load.faculty_user_id=? AND own_grade.is_current=1 AND own_grade.school_year=? AND own_grade.semester=?)
        ORDER BY sp.section, u.last_name, u.first_name
    ");
    $stmt->execute([...$my_sections,$user['id'],$currentTerm['school_year'],(int)$currentTerm['semester']]);
    $students = $stmt->fetchAll();
    foreach ($students as &$s) {
        $s['full_name']  = formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']);
        $s['short_name'] = formatNameShort($s['first_name'], $s['last_name']);
    }
    unset($s);
}

$subject_grades = []; 
if (!empty($my_class_loads)) {
    $conds  = [];
    $params = [];
    foreach ($my_class_loads as $load) {
        $conds[]  = "(sp.section = ? AND g.subject_id = ?)";
        $params[] = $load['section'];
        $params[] = $load['id'];
    }
    $where = implode(' OR ', $conds);
    
    $stmt  = $db->prepare("
        SELECT g.student_id, g.subject_id, 
               g.prelim, g.midterm, g.prefinal, g.final_grade, 
               g.risk_level
        FROM grades g
        JOIN student_profiles sp ON g.student_id = sp.user_id
        WHERE ($where) AND g.is_current = 1 AND g.school_year=? AND g.semester=? AND sp.record_status = 'Active'
    ");
    $stmt->execute([...$params,$currentTerm['school_year'],(int)$currentTerm['semester']]);
    foreach ($stmt->fetchAll() as $gr) {
        $subject_grades[$gr['student_id']][$gr['subject_id']] = [
            'prelim'      => $gr['prelim'],
            'midterm'     => $gr['midterm'],
            'prefinal'    => $gr['prefinal'],
            'final_grade' => $gr['final_grade'],
            'risk'        => facultyAssessmentRisk($gr)['risk'],
        ];
    }
}

$section_data    = [];
foreach ($my_sections as $sec) {
    // FIXED: gwa_count added to dictionary to track valid math
    $section_data[$sec] = ['students' => [], 'gwa_sum' => 0, 'gwa_count' => 0, 'at_risk' => 0, 'irregular' => 0];
}

$at_risk_total   = 0;
$irregular_total = 0;
$gwa_sum         = 0;
$gwa_count       = 0;

foreach ($students as &$s) {
    $sec = $s['section'];
    $uid = $s['user_id'];
    $gwa = (float)($s['current_gwa'] ?? 0);

    $s['subject_grades'] = $subject_grades[$uid] ?? [];

    $subj_at_risk = false;
    foreach ($s['subject_grades'] as $sg) {
        if (in_array($sg['risk'], ['HIGH', 'MODERATE'])) { $subj_at_risk = true; break; }
    }
    $s['subj_at_risk'] = $subj_at_risk;

    if ($subj_at_risk)                { $at_risk_total++;   $section_data[$sec]['at_risk']++; }
    if ($s['status'] === 'Irregular') { $irregular_total++; $section_data[$sec]['irregular']++; }
    if ($gwa > 0) {
        $gwa_sum  += $gwa; $gwa_count++;
        $section_data[$sec]['gwa_sum'] += $gwa;
        $section_data[$sec]['gwa_count']++;
    }
    $section_data[$sec]['students'][] = $s;
}
unset($s);

$overall_avg_gwa = $gwa_count > 0 ? round($gwa_sum / $gwa_count, 2) : 0;
$total_students  = count($students);

function getHonorBadge(float $gwa): array {
    if ($gwa >= 3.75) return ['text' => 'Summa-level threshold',     'bg' => 'var(--gold)'];
    if ($gwa >= 3.50) return ['text' => 'Magna-level threshold',     'bg' => 'var(--honor-magna)'];
    if ($gwa >= 3.25) return ['text' => 'Cum Laude-level threshold', 'bg' => 'var(--accent-blue)'];
    return ['text' => '—', 'bg' => 'var(--bg-color)', 'color' => 'var(--text-gray)'];
}

// Compact Assigned-Class Analytics Snapshot (WP1)
require_once __DIR__ . '/../includes/analytics/service.php';
try {
    $facAnalytics = analyticsReport($db, $user, []);
    $facSummary = $facAnalytics['summary'];
    $facSectionCount = count($facAnalytics['groups']['section'] ?? []);
    $facReviewCount = $facAnalytics['students_requiring_review'] ?? 0;
    $facReferrals = count(array_filter($facAnalytics['drilldowns'] ?? [], static fn($r) => $r['status'] !== 'closed'));
    $facNotices = $facAnalytics['support']['notices_issued'] ?? 0;
    $facAssessmentData = facultyAssessmentRead($db,(int)$user['id'],$currentTerm);
    $facOverview = facultyAssessmentOverview($facAssessmentData['loads'], $facAssessmentData['rows']);
    $facAssessment = $facOverview['summary'];
    $facSectionCount = count(array_unique(array_column($facAssessmentData['loads'],'section')));
} catch (Throwable $e) {
    error_log('Faculty dashboard analytics snapshot unavailable: ' . $e->getMessage());
    $facSummary = ['gwa' => null, 'student_count' => 0, 'eligible_grade_count' => 0, 'expected_grade_count' => 0, 'numeric_coverage_pct' => null];
    $facSectionCount = count($section_data ?? []);
    $facReviewCount = 0;
    $facReferrals = 0;
    $facNotices = 0;
    $facAssessmentData = ['loads'=>[], 'rows'=>[]];
    $facOverview = facultyAssessmentOverview([], []);
    $facAssessment = $facOverview['summary'];
}

$pageTitle = 'Dashboard';
$navItems = [
    ['Home',               'index.php',     ''],
    ['Dashboard',          'dashboard.php', ''],
    ['Class Analytics',    'analytics.php', ''],
    ['Performance Trends', 'trend.php',     ''],
    ['Term Grade Submission', 'grades.php', ''],
    ['Concerns & Reports', 'feedback.php',  ''],
    ['Settings',           'settings.php',  ''],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/faculty-assessment.css?v=<?= substr(hash_file('sha256',__DIR__.'/../assets/css/faculty-assessment.css'),0,12) ?>">

<style>
.tab-bar  { display:flex; gap:6px; margin-bottom:18px; border-bottom:2px solid var(--border-color); }
.tab-btn  { padding:8px 18px; border:none; background:transparent; color:var(--text-gray); font-size:0.88rem; font-weight:600; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all 0.18s; font-family:inherit; }
.tab-btn:hover  { color:var(--accent-blue); }
.tab-btn.active { color:var(--accent-blue); border-bottom-color:var(--accent-blue); }
.grade-cell { font-weight:600; }
.grade-low  { color:var(--risk-low); }
.grade-mod  { color:var(--risk-mod); }
.grade-high { color:var(--risk-high); }
.grade-none { color:var(--text-gray); }
.sortable-col { cursor:pointer; user-select:none; color:var(--text-dark); }
.sortable-col:hover { background:var(--table-header-bg) !important; }
.sort-arrow { font-size:0.78rem; color:var(--text-gray); margin-left:5px; transition:color 0.15s; }
.row-clickable { cursor:pointer; transition: background 0.15s; }
.row-clickable:hover { background: var(--bg-color); }
.warning-banner { background: rgba(217, 119, 6, 0.08); border-left: 4px solid var(--risk-mod); }
[data-theme="dark"] .warning-banner { background: rgba(245, 158, 11, 0.16); border-left: 4px solid #f59e0b; color: #fbbf24; }
[data-theme="dark"] .warning-banner h3 { color: #fef3c7 !important; }
[data-theme="dark"] .warning-banner .action-link { color: #fde68a !important; }
.control-btn { transition: opacity 0.2s ease, transform 0.1s ease; }
.control-btn:hover { opacity: 0.85; }
.control-btn:active { transform: scale(0.98); }

.action-link { color: inherit !important; text-decoration: none !important; transition: opacity 0.2s; }
.action-link:hover { opacity: 0.7; text-decoration: underline !important; text-underline-offset: 2px; }

.dashboard-banner-btn {
    display: inline-block;
    padding: 10px 16px;
    background: var(--btn-bg) !important; 
    color: var(--risk-moderate) !important;
    border: 1px solid var(--risk-moderate);
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none !important;
    font-size: 0.9rem;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(217, 119, 6, 0.2);
    white-space: nowrap;
}
.dashboard-banner-btn:hover {
    background: var(--risk-moderate) !important;
    color: white !important;
}
</style>

<div class="main-content faculty-assessment">

    <div class="header" style="margin-bottom:24px;">
        <div>
            <h1>Faculty Dashboard</h1>
            <p style="color:var(--text-gray); font-size:0.95rem;">
                Review current results across your assigned classes.
            </p>
        </div>
    </div>

    <?php if ($facultyActionCount > 0): ?>
        <div class="card warning-banner" style="margin-bottom: 24px; padding: 16px 24px; border-left: 4px solid var(--risk-mod); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 style="margin: 0 0 6px 0; color: var(--text-dark); font-size: 1.1rem; font-weight: 700;">Work requiring your attention</h3>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; color: var(--text-gray); font-size: 0.85rem;">
                    <?php if ($openConcernCount > 0): ?>
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="feedback.php?tab=inbox" class="action-link"><strong><?= $openConcernCount ?></strong> student concern<?= $openConcernCount === 1 ? '' : 's' ?></a></span>
                    <?php endif; ?>
                    <?php if ($supportReviewCount > 0): ?>
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="feedback.php?tab=support" class="action-link"><strong><?= $supportReviewCount ?></strong> support review<?= $supportReviewCount === 1 ? '' : 's' ?></a></span>
                    <?php endif; ?>
                    <?php if ($pendingBatchCount > 0): ?>
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="grades.php" class="action-link"><strong><?= $pendingBatchCount ?></strong> grade submission<?= $pendingBatchCount === 1 ? '' : 's' ?> awaiting Admin review</a></span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php if ($openConcernCount > 0): ?>
                    <a href="feedback.php?tab=inbox" class="dashboard-banner-btn control-btn">Open Concerns</a>
                <?php endif; ?>
                <?php if ($pendingBatchCount > 0): ?>
                    <a href="grades.php" class="dashboard-banner-btn control-btn" style="background: var(--card-bg) !important; color: var(--accent-blue) !important; border-color: var(--accent-blue) !important;">View Grade Submissions</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; font-size: 0.82rem; color: var(--text-gray); display: flex; align-items: flex-start; gap: 12px;">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--accent-blue); flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <div>
            <div style="color: var(--text-dark); font-weight: 700; font-size: 0.85rem; margin-bottom: 4px;">Faculty Decision-Support Advisory</div>
            <div style="line-height: 1.45; color: var(--text-dark);">Student risk and projected outcomes support academic review. They do not replace official grades or Faculty evaluation.</div><p class="faculty-subtext">Prototype performance on synthetic academic records.</p>
        </div>
    </div>

    <?php $a=$facOverview['assessment']; $escape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $pct=fn($v)=>$v===null?'Unavailable':number_format($v,1).'%'; $cards=[
        ['Assigned Classes',count($facAssessmentData['loads']),'Current assigned subject-sections','blue','assigned-classes'],
        ['Unique Students',$facAssessment['students'],'Distinct Students with current assigned records','neutral','unique-students'],
        ['Overall Latest Assessment Mean',$a?$pct($a['mean']):$facOverview['status'],$a?$facOverview['detail']:'Review class-level results in Performance Trends.','teal','assessment-mean'],
        ['Assessment Coverage',$a?$pct($a['coverage']):'Unavailable',$a?'Across '.count($facAssessmentData['loads']).' assigned classes':'See class coverage in Performance Trends','violet','assessment-coverage'],
        ['Requiring Attention',$facAssessment['attention'],'Distinct Students with Moderate or High advisory risk','amber','attention']
    ]; ?>
    <div class="faculty-kpis" aria-label="Primary Faculty summary"><?php foreach($cards as [$title,$value,$detail,$accent,$metric]): ?><div class="stat-card faculty-kpi faculty-kpi--<?= $accent ?>"><h3><?= $escape($title) ?></h3><p class="faculty-value" data-dashboard-metric="<?= $metric ?>"><?= $escape($value) ?></p><p class="faculty-subtext"><?= $escape($detail) ?></p><?php if($metric==='assessment-mean'): ?><p class="faculty-subtext">Across <?= count($facAssessmentData['loads']) ?> assigned classes</p><?php endif; ?></div><?php endforeach; ?></div>

    <div class="faculty-summary-grid">
        <section class="card faculty-summary-card" aria-labelledby="faculty-snapshot-heading">
            <h2 id="faculty-snapshot-heading">Assigned classes</h2>
            <p class="faculty-subtext">SY <?= $escape($currentTerm['school_year']) ?> · <?= (int)$currentTerm['semester']===3?'Summer':'Semester '.(int)$currentTerm['semester'] ?><?= $a?' · '.$escape($a['label']):'' ?></p>
            <ul class="faculty-summary-values">
                <li><strong data-faculty-summary="assigned-classes"><?= count($facAssessmentData['loads']) ?></strong> subject-sections</li>
                <li><strong data-faculty-summary="unique-students"><?= $facAssessment['students'] ?></strong> Students</li>
                <li><?php if($a): ?><strong data-faculty-summary="assessment-mean"><?= $pct($a['mean']) ?></strong> latest assessment mean<?php else: ?>Latest assessment mean: <strong data-faculty-summary="assessment-mean"><?= $escape($facOverview['status']) ?></strong><?php endif; ?></li>
                <li><strong data-faculty-summary="assessment-coverage"><?= $a?$pct($a['coverage']):'Unavailable' ?></strong> assessment coverage</li>
                <li><strong data-faculty-summary="attention"><?= $facAssessment['attention'] ?></strong> requiring attention</li>
            </ul>
            <div class="faculty-summary-action"><a href="trend.php" class="btn btn--secondary">View performance trends</a></div>
        </section>
        <section class="card faculty-summary-card" aria-labelledby="faculty-support-heading">
            <h2 id="faculty-support-heading">Support work</h2>
            <ul class="faculty-summary-values">
                <li><strong data-dashboard-metric="active-referrals"><?= $facReferrals ?></strong> active referral<?= $facReferrals===1?'':'s' ?></li>
                <li><strong data-dashboard-metric="notices-issued"><?= $facNotices ?></strong> notice<?= $facNotices===1?'':'s' ?> issued</li>
                <li><strong data-dashboard-metric="pending-support"><?= $openConcernCount+$supportReviewCount ?></strong> concerns and support reviews</li>
            </ul>
            <div class="faculty-summary-action"><a href="feedback.php?tab=support" class="btn btn--secondary">Review support cases</a></div>
        </section>
    </div>

    <h2>Student review by section</h2>
    <p class="faculty-subtext">Cumulative GWA below describes Student history, separately from current class assessment percentages.</p>
    <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); margin-bottom:24px;">
        <?php foreach ($section_data as $sec_name => $data):
            $count    = count($data['students']);
            $gwaCount = $data['gwa_count'] ?? 0;
            // FIXED: Using gwaCount denominator
            $avg      = $gwaCount > 0 ? round($data['gwa_sum'] / $gwaCount, 2) : 0;
            $risk_col = $data['at_risk'] > 0 ? 'var(--risk-high)' : 'var(--risk-low)';
        ?>
        <div class="card" style="padding:0;overflow:hidden;border:1px solid var(--border-color);border-radius:8px;">
            <div style="background:var(--table-header-bg);color:var(--text-dark);padding:12px 16px;display:flex;justify-content:space-between;font-weight:700; border-bottom:1px solid var(--border-color);">
                <span><?= htmlspecialchars($sec_name) ?></span>
                <span style="font-size:0.8rem;color:var(--text-gray);"><?= $count ?> students</span>
            </div>
            <div style="padding:16px;text-align:center;">
                <h2 style="color:var(--accent-blue);font-size:2rem;margin-bottom:2px;"><?= $avg > 0 ? number_format($avg, 2) : '—' ?></h2>
                <p style="color:var(--text-gray);font-size:0.8rem;margin-bottom:12px;">Average cumulative GWA</p>
                <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:12px;border-top:1px solid var(--border-color);padding-top:8px;">
                    <span style="color:<?= $risk_col ?>;font-weight:600;">At-Risk: <?= $data['at_risk'] ?></span>
                    <span style="color:var(--text-gray);font-weight:600;">Irregular: <?= $data['irregular'] ?></span>
                </div>
                <button type="button"
                    class="btn btn--secondary view-students-btn"
                    data-section="<?= htmlspecialchars($sec_name) ?>"
                    aria-controls="section-roster-card"
                    aria-expanded="false"
                    onclick="openSection('<?= htmlspecialchars($sec_name) ?>', this)"
                    style="width:100%; justify-content:center;">
                    View Students →
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="section-roster-card" class="data-table-card data-table-card--active roster-panel" style="display:none; margin-bottom:24px;">
        <div class="data-table-header">
            <div class="data-table-header__intro">
                <h3 class="data-table-title" id="roster-title" tabindex="-1">Student List</h3>
                <p class="data-table-subtitle">Click on a student row in 'My Class Performance' to view their full term breakdown.</p>
            </div>
            <div class="data-table-actions">
                <button type="button" onclick="closeRoster()" class="btn btn--secondary btn--sm" id="faculty-roster-close-btn">
                    Close
                </button>
            </div>
        </div>

        <div class="tab-bar" style="padding:12px 24px 0; border-bottom:1px solid var(--border-color); background:var(--card-bg);">
            <button class="tab-btn active" id="tab-btn-class" onclick="switchTab('class')" style="display:inline-flex; align-items:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                <span>My Class Performance</span>
            </button>
            <button class="tab-btn" id="tab-btn-overall" onclick="switchTab('overall')" style="display:inline-flex; align-items:center; gap:6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                <span>Overall Standing</span>
            </button>
        </div>

        <div id="tab-content-class" class="data-table-scroll table-matrix-wrapper" role="region" aria-label="Class Performance Roster" tabindex="0">
            <div class="table-scroll-hint" aria-hidden="true">&larr; Swipe or scroll horizontally to view matrix &rarr;</div>
            <table class="table-matrix data-table" style="width: 100%; border-collapse: collapse;">
                <caption class="sr-only">Class Performance Roster</caption>
                <thead><tr id="class-thead-row" style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color);"></tr></thead>
                <tbody id="roster-body-class"></tbody>
            </table>
        </div>

        <div id="tab-content-overall" class="data-table-scroll table-matrix-wrapper" role="region" aria-label="Overall Section Standing" tabindex="0" style="display:none;">
            <div class="table-scroll-hint" aria-hidden="true">&larr; Swipe or scroll horizontally to view matrix &rarr;</div>
            <table class="table-matrix data-table" style="width: 100%; border-collapse: collapse;">
                <caption class="sr-only">Overall Section Standing</caption>
                <thead>
                    <tr style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color); color:var(--text-dark);">
                        <th scope="col" style="width:50px;text-align:center; padding:12px;">Rank</th>
                        <th scope="col" style="text-align:left; padding:12px;">Student Name</th>
                        <!-- FIXED: Removed redundant 'Student No.' column -->
                        <th scope="col" style="text-align:center; padding:12px;">Cumulative GWA (Historical)</th>
                        <th scope="col" style="text-align:center; padding:12px;">Distinction Threshold</th>
                        <th scope="col" style="text-align:center; padding:12px;">Overall Risk</th>
                        <th scope="col" style="text-align:center; padding:12px;">Status</th>
                    </tr>
                </thead>
                <tbody id="roster-body-overall"></tbody>
            </table>
        </div>

        <p style="font-size: 0.85rem; color: var(--text-gray); margin: 12px 24px; padding-bottom: 12px;">
            Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.
        </p>
    </div>

    <div class="card">
        <div style="margin-bottom:16px;">
            <h3 style="color:var(--text-dark);font-weight:700; font-size:1.1rem; margin:0 0 4px 0;">Top Academic Performers in Your Assigned Sections</h3>
            <p style="color:var(--text-gray); font-size: 0.85rem; margin: 0 0 12px 0;">Highlights students with the highest officially recorded current GWA for possible academic opportunities, subject to additional student interest and eligibility.</p>
            <div style="background: rgba(30, 77, 183, 0.05); border-left: 3px solid var(--accent-blue); padding: 10px 14px; border-radius: 4px; font-size: 0.8rem; color: var(--text-dark);">
                <strong>Note:</strong> This ranking is an academic reference only. Selection for programs or events should also consider eligibility, interest, availability, conduct, and program-specific requirements.
            </div>
        </div>
        <div class="data-table-scroll table-matrix-wrapper" role="region" aria-label="Top Academic Performers Ranking" tabindex="0">
            <div class="table-scroll-hint" aria-hidden="true">&larr; Swipe or scroll horizontally to view matrix &rarr;</div>
            <table class="table-matrix data-table" style="width: 100%; border-collapse: collapse; min-width: 600px;">
                <caption class="sr-only">Top Academic Performers Ranking</caption>
                <thead>
                    <tr style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color); color:var(--text-dark);">
                        <th scope="col" style="width:50px;text-align:center; padding:12px;">Rank</th>
                        <th scope="col" style="text-align:left; padding:12px;">Student</th>
                        <th scope="col" style="text-align:center; padding:12px;">Cumulative GWA (Completed Semesters)</th>
                        <th scope="col" style="text-align:center; padding:12px;">Distinction Threshold</th>
                        <th scope="col" style="text-align:center; padding:12px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $eligibleTopStudents = array_values(array_filter(
                        $students,
                        fn($student) => $student['current_gwa'] !== null && (float) $student['current_gwa'] > 0
                    ));

                    usort(
                        $eligibleTopStudents,
                        fn($a, $b) => (float) $b['current_gwa'] <=> (float) $a['current_gwa']
                    );

                    $topStudents = array_slice($eligibleTopStudents, 0, 10);
                    
                    $currentRank = 1;
                    $actualIndex = 1;
                    $previousGwa = null;

                    foreach ($topStudents as $ts):
                        if ($ts['current_gwa'] !== $previousGwa) {
                            $currentRank = $actualIndex;
                        }
                        $previousGwa = $ts['current_gwa'];
                        $actualIndex++;

                        $honor = getHonorBadge((float)$ts['current_gwa']);
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="text-align:center;font-weight:700; color:var(--text-dark); padding:12px; font-size: 1.1rem;"><?= $currentRank ?></td>
                        <td style="padding:12px;">
                            <div style="font-weight:600; color:var(--text-dark); margin-bottom: 2px;"><?= htmlspecialchars($ts['full_name']) ?></div>
                            <div style="color:var(--text-gray); font-size: 0.8rem;"><?= htmlspecialchars($ts['student_number']) ?> · <?= htmlspecialchars($ts['section']) ?></div>
                        </td>
                        <td style="font-weight:700; color:var(--accent-blue); text-align:center; padding:12px; font-size: 1.05rem;"><?= number_format($ts['current_gwa'], 2) ?></td>
                        <td style="text-align:center; padding:12px;">
                            <span style="background:<?= $honor['bg'] ?>;color:<?= $honor['color'] ?? 'white' ?>;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">
                                <?= $honor['text'] ?>
                            </span>
                        </td>
                        <td style="text-align:center; padding:12px;">
                            <span style="background:<?= $ts['status'] === 'Irregular' ? 'var(--risk-mod)' : 'var(--risk-low)' ?>;color:white;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">
                                <?= htmlspecialchars($ts['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="modal-overlay" id="grade-modal-overlay" onclick="if(event.target===this) closeGradeModal();">
    <div class="modal-box" style="max-width: 650px;">
        <button class="modal-close" onclick="closeGradeModal()">Close</button>
        <h2 id="grade-modal-name" style="color:var(--text-dark); margin-bottom:2px;"></h2>
        <p style="color:var(--text-gray); font-size:0.88rem; margin-bottom:16px;">
            Detailed grade breakdown for your assigned subjects. Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.
        </p>
        <div class="data-table-scroll table-matrix-wrapper" role="region" aria-label="Student Grade Breakdown" tabindex="0">
            <div class="table-scroll-hint" aria-hidden="true">&larr; Swipe or scroll horizontally to view matrix &rarr;</div>
            <table class="table-matrix data-table" style="width:100%; border-collapse: collapse;">
                <caption class="sr-only">Student Grade Breakdown</caption>
                <thead>
                    <tr style="background:var(--table-header-bg); border-bottom:1px solid var(--border-color);">
                        <th scope="col" style="padding:10px; text-align:left; color:var(--text-dark);">Subject</th>
                        <th scope="col" style="padding:10px; text-align:center; color:var(--text-dark);">Prelim</th>
                        <th scope="col" style="padding:10px; text-align:center; color:var(--text-dark);">Midterm</th>
                        <th scope="col" style="padding:10px; text-align:center; color:var(--text-dark);">Pre-Final</th>
                        <th scope="col" style="padding:10px; text-align:center; color:var(--accent-blue);">Final</th>
                    </tr>
                </thead>
                <tbody id="grade-modal-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
const sectionDataMap     = <?= json_encode($section_data,       JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const sectionSubjectMap  = <?= json_encode($section_subject_map, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

let activeSection = null;
let activeFacultyTrigger = null;

function openSection(secName, triggerEl) {
    if (!triggerEl) {
        try {
            triggerEl = document.querySelector('.view-students-btn[data-section="' + CSS.escape(secName) + '"]')
                     || document.querySelector('.view-students-btn[data-section="' + secName + '"]');
        } catch (e) {
            triggerEl = document.querySelector('.view-students-btn[data-section="' + secName + '"]');
        }
    }

    if (activeFacultyTrigger && activeFacultyTrigger !== triggerEl) {
        activeFacultyTrigger.setAttribute('aria-expanded', 'false');
        activeFacultyTrigger.classList.remove('view-students-btn--active');
        activeFacultyTrigger.textContent = 'View Students →';
    }

    activeFacultyTrigger = triggerEl;
    activeSection = secName;

    if (activeFacultyTrigger) {
        activeFacultyTrigger.setAttribute('aria-expanded', 'true');
        activeFacultyTrigger.classList.add('view-students-btn--active');
        activeFacultyTrigger.textContent = 'Active Roster ↓';
    }

    const title = document.getElementById('roster-title');
    title.textContent = 'Section ' + secName + ' — Student Roster';

    switchTab('class'); 

    const panel = document.getElementById('section-roster-card');
    panel.style.display = 'block';

    if (window.UDM && window.UDM.announce) {
        window.UDM.announce('Section ' + secName + ' student roster opened');
    }

    if (window.UDM && window.UDM.scrollToTarget) {
        window.UDM.scrollToTarget(panel, { focusTarget: title });
    }
}

function closeRoster() {
    const panel = document.getElementById('section-roster-card');
    panel.style.display = 'none';

    const returningTrigger = activeFacultyTrigger;
    if (activeFacultyTrigger) {
        activeFacultyTrigger.setAttribute('aria-expanded', 'false');
        activeFacultyTrigger.classList.remove('view-students-btn--active');
        activeFacultyTrigger.textContent = 'View Students →';
        activeFacultyTrigger = null;
    }
    activeSection = null;

    if (returningTrigger && typeof returningTrigger.focus === 'function') {
        try {
            returningTrigger.focus({ preventScroll: true });
        } catch (e) {
            returningTrigger.focus();
        }
    }
}

function switchTab(tab) {
    document.getElementById('tab-content-class').style.display   = tab === 'class'   ? 'block' : 'none';
    document.getElementById('tab-content-overall').style.display = tab === 'overall' ? 'block' : 'none';
    document.getElementById('tab-btn-class').classList.toggle('active',   tab === 'class');
    document.getElementById('tab-btn-overall').classList.toggle('active', tab === 'overall');
    if (!activeSection) return;
    if (tab === 'class')   renderClassTab(activeSection);
    if (tab === 'overall') renderOverallTab(activeSection);
}

let classSortCol = -1;
let classSortDir = 'desc';

function renderClassTab(secName) {
    classSortCol = -1;
    classSortDir = 'desc';

    const students = sectionDataMap[secName]?.students || [];
    const subjects = sectionSubjectMap[secName] || [];

    const maxWidth = Math.max(120, 600 / (subjects.length || 1)); 

    const thead = document.getElementById('class-thead-row');
    let thHtml = '<th scope="col" class="sortable-col" aria-sort="none" id="col-h-0" style="padding:12px; text-align:left;"><button type="button" class="table-sort-button" onclick="sortClassTab(0)"><span>Student Name</span><span class="sort-arrow" id="sort-arrow-0" aria-hidden="true">⇅</span></button></th>';
    
    subjects.forEach((subj, i) => {
        const colIdx = i + 1;
        thHtml += `<th scope="col" class="sortable-col" aria-sort="none" id="col-h-${colIdx}" style="padding:12px; text-align:center;">
            <button type="button" class="table-sort-button" onclick="sortClassTab(${colIdx})" title="${subj.code} — ${subj.title}">
                <span style="max-width: ${maxWidth}px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: bottom;">${subj.title}</span><span class="sort-arrow" id="sort-arrow-${colIdx}" aria-hidden="true">⇅</span>
            </button>
        </th>`;
    });
    
    const avgColIdx = subjects.length + 1;
    thHtml += `<th scope="col" class="sortable-col" aria-sort="none" id="col-h-${avgColIdx}" style="padding:12px; text-align:center;">
        <button type="button" class="table-sort-button" onclick="sortClassTab(${avgColIdx})">
            <span>Current Grade (Avg)</span><span class="sort-arrow" id="sort-arrow-${avgColIdx}" aria-hidden="true">⇅</span>
        </button>
    </th>`;
    thHtml += '<th scope="col" style="padding:12px; text-align:center; color:var(--text-dark);">Subject Risk</th>';
    thead.innerHTML = thHtml;

    const tbody = document.getElementById('roster-body-class');
    if (!students.length) {
        tbody.innerHTML = `<tr><td colspan="${subjects.length + 3}" style="text-align:center;color:var(--text-gray);padding:24px;">No students in this section.</td></tr>`;
        return;
    }

    let html = '';
    students.forEach(s => {
        const grades = s.subject_grades || {};
        let gradeSum = 0, gradeCount = 0, worstRisk = 'LOW';
        let gradeCells = '';

        subjects.forEach(subj => {
            const g = grades[subj.id];
            
            let hasFinal = (g && g.final_grade !== null && g.final_grade !== undefined && g.final_grade !== '');
            let hasPrefinal = (g && g.prefinal !== null && g.prefinal !== undefined && g.prefinal !== '');
            let hasMidterm = (g && g.midterm !== null && g.midterm !== undefined && g.midterm !== '');
            let hasPrelim = (g && g.prelim !== null && g.prelim !== undefined && g.prelim !== '');

            if (hasFinal) {
                let currentGradeStr = ['INC','DRP','P','DO','DU','FA','UD'].includes(String(g.final_grade).toUpperCase())
                    ? String(g.final_grade).toUpperCase()
                    : parseFloat(g.final_grade).toFixed(2);
                const cls = g.risk === 'HIGH' ? 'grade-high' : (g.risk === 'MODERATE' ? 'grade-mod' : 'grade-low');
                gradeCells += `<td class="grade-cell ${cls}" style="padding:12px; text-align:center; vertical-align:middle;">
                    ${currentGradeStr}<br>
                    <span style="font-size:0.65rem; color:var(--text-gray); font-weight:normal;">FIN</span>
                </td>`;
                if (!['INC','DRP','P','DO','DU','FA','UD'].includes(currentGradeStr)) {
                    gradeSum += parseFloat(g.final_grade);
                    gradeCount++;
                }
            } else if (hasPrefinal) {
                let currentGradeStr = Math.round(parseFloat(g.prefinal)) + '%';
                const cls = g.risk === 'HIGH' ? 'grade-high' : (g.risk === 'MODERATE' ? 'grade-mod' : 'grade-low');
                gradeCells += `<td class="grade-cell ${cls}" style="padding:12px; text-align:center; vertical-align:middle;">
                    ${currentGradeStr}<br>
                    <span style="font-size:0.65rem; color:var(--text-gray); font-weight:normal;">PRE-F</span>
                </td>`;
                let pct = parseFloat(currentGradeStr);
                let pointEquivalent = 0;
                if (pct >= 99) pointEquivalent = 4.00; else if (pct >= 97) pointEquivalent = 3.75; else if (pct >= 95) pointEquivalent = 3.50; else if (pct >= 92) pointEquivalent = 3.25;
                else if (pct >= 90) pointEquivalent = 3.00; else if (pct >= 88) pointEquivalent = 2.75; else if (pct >= 86) pointEquivalent = 2.50; else if (pct >= 84) pointEquivalent = 2.25;
                else if (pct >= 82) pointEquivalent = 2.00; else if (pct >= 80) pointEquivalent = 1.75; else if (pct >= 78) pointEquivalent = 1.50; else if (pct >= 76) pointEquivalent = 1.25;
                else if (pct >= 75) pointEquivalent = 1.00; else pointEquivalent = 0.00;
                gradeSum += pointEquivalent;
                gradeCount++;
            } else if (hasPrelim || hasMidterm) {
                let pStr = hasPrelim ? Math.round(parseFloat(g.prelim)) + '%' : '—';
                let mStr = hasMidterm ? Math.round(parseFloat(g.midterm)) + '%' : '—';
                const cls = g.risk === 'HIGH' ? 'grade-high' : (g.risk === 'MODERATE' ? 'grade-mod' : 'grade-low');
                gradeCells += `<td class="grade-cell ${cls}" style="padding:8px; text-align:center; vertical-align:middle; font-size:0.85rem; line-height:1.3;">
                    <div><span style="font-size:0.7rem; color:var(--text-gray); font-weight:600;">P:</span> ${pStr}</div>
                    <div><span style="font-size:0.7rem; color:var(--text-gray); font-weight:600;">M:</span> ${mStr}</div>
                </td>`;

                let activePct = hasMidterm ? parseFloat(g.midterm) : parseFloat(g.prelim);
                let pointEquivalent = 0;
                let pct = activePct;
                if (pct >= 99) pointEquivalent = 4.00; else if (pct >= 97) pointEquivalent = 3.75; else if (pct >= 95) pointEquivalent = 3.50; else if (pct >= 92) pointEquivalent = 3.25;
                else if (pct >= 90) pointEquivalent = 3.00; else if (pct >= 88) pointEquivalent = 2.75; else if (pct >= 86) pointEquivalent = 2.50; else if (pct >= 84) pointEquivalent = 2.25;
                else if (pct >= 82) pointEquivalent = 2.00; else if (pct >= 80) pointEquivalent = 1.75; else if (pct >= 78) pointEquivalent = 1.50; else if (pct >= 76) pointEquivalent = 1.25;
                else if (pct >= 75) pointEquivalent = 1.00; else pointEquivalent = 0.00;
                gradeSum += pointEquivalent;
                gradeCount++;
            } else {
                gradeCells += `<td class="grade-none" style="padding:12px; text-align:center; vertical-align:middle;">—</td>`;
            }

            if (g) {
                if (g.risk === 'HIGH') worstRisk = 'HIGH';
                else if (g.risk === 'MODERATE' && worstRisk !== 'HIGH') worstRisk = 'MODERATE';
            }
        });

        const avg     = gradeCount > 0 ? (gradeSum / gradeCount).toFixed(2) : '—';
        const avgCls  = gradeCount > 0
            ? (parseFloat(avg) < 2.00 ? 'grade-high' : (parseFloat(avg) < 2.50 ? 'grade-mod' : 'grade-low'))
            : 'grade-none';
        
        const riskBg  = gradeCount === 0 ? 'var(--bg-color)' : (worstRisk === 'HIGH' ? 'var(--risk-high)' : (worstRisk === 'MODERATE' ? 'var(--risk-mod)' : 'var(--risk-low)'));
        const riskClr = gradeCount === 0 ? 'var(--text-gray)' : 'white';
        const riskLbl = gradeCount === 0 ? 'No Data' : worstRisk;

        const escapedName = s.full_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        html += `<tr style="border-bottom:1px solid var(--border-color);">
            <td class="table-col--sticky" style="font-weight:600; padding:12px;">
                <button type="button" class="table-record-link" onclick="openGradeModal('${s.user_id}', '${secName}')" aria-label="View grade breakdown for ${escapedName}">
                    ${s.full_name}
                </button>
            </td>
            ${gradeCells}
            <td class="grade-cell ${avgCls}" style="padding:12px; text-align:center; vertical-align:middle;">${avg}</td>
            <td style="padding:12px; text-align:center; vertical-align:middle;"><span style="background:${riskBg};color:${riskClr};padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;border:1px solid var(--border-color);">${riskLbl}</span></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function openGradeModal(uid, secName) {
    const student = sectionDataMap[secName].students.find(s => s.user_id == uid);
    const subjects = sectionSubjectMap[secName];
    const grades = student.subject_grades || {};

    document.getElementById('grade-modal-name').innerText = student.full_name;

    let html = '';
    const formatPct = (val) => val !== null && val !== undefined && val !== '' ? Math.round(parseFloat(val)) + '%' : '<span style="color:var(--text-gray);">—</span>';
    const formatPt = (val) => {
        if (val === null || val === undefined || val === '') return '<span style="color:var(--text-gray);">—</span>';
        if (['INC','DRP','P','DO','DU','FA','UD'].includes(String(val).toUpperCase())) return String(val).toUpperCase();
        return parseFloat(val).toFixed(2);
    };
    
    subjects.forEach(subj => {
        const g = grades[subj.id];
        if (g) {
            html += `<tr style="border-bottom:1px solid var(--border-color);">
                <td style="padding:12px; color:var(--text-dark); font-weight:600; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${subj.code} — ${subj.title}">${subj.title}</td>
                <td style="padding:12px; text-align:center; font-weight:600; color:var(--text-dark);">${formatPct(g.prelim)}</td>
                <td style="padding:12px; text-align:center; font-weight:600; color:var(--text-dark);">${formatPct(g.midterm)}</td>
                <td style="padding:12px; text-align:center; font-weight:600; color:var(--text-dark);">${formatPct(g.prefinal)}</td>
                <td style="padding:12px; text-align:center; font-weight:700; color:var(--accent-blue);">${formatPt(g.final_grade)}</td>
            </tr>`;
        }
    });

    document.getElementById('grade-modal-body').innerHTML = html || '<tr><td colspan="5" style="text-align:center; padding:16px; color:var(--text-gray);">No grades encoded yet.</td></tr>';
    document.getElementById('grade-modal-overlay').classList.add('open');
}

function closeGradeModal() {
    document.getElementById('grade-modal-overlay').classList.remove('open');
}

function renderOverallTab(secName) {
    const students = (sectionDataMap[secName]?.students || [])
        .slice()
        .sort((a, b) => parseFloat(b.current_gwa || 0) - parseFloat(a.current_gwa || 0));

    const tbody = document.getElementById('roster-body-overall');
    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-gray);padding:24px;">No students.</td></tr>';
        return;
    }

    function honorBadge(gwa) {
        if (gwa >= 3.75) return { text: 'Summa-level threshold',     bg: 'var(--gold)' };
        if (gwa >= 3.50) return { text: 'Magna-level threshold',     bg: 'var(--honor-magna)' };
        if (gwa >= 3.25) return { text: 'Cum Laude-level threshold', bg: 'var(--accent-blue)' };
        return { text: '—', bg: 'var(--bg-color)', color: 'var(--text-gray)' };
    }

    let html = '';
    let currentRank = 1;
    let actualIndex = 1;
    let previousGwa = null;

    students.forEach((s) => {
        const gwa = parseFloat(s.current_gwa || 0);
        
        if (gwa !== previousGwa) {
            currentRank = actualIndex;
        }
        previousGwa = gwa;
        actualIndex++;

        const honor  = honorBadge(gwa);
        const risk   = (s.risk_level || 'N/A').toUpperCase();
        const riskBg = risk === 'HIGH' ? 'var(--risk-high)' : (risk === 'MODERATE' ? 'var(--risk-mod)' : (risk === 'LOW' ? 'var(--risk-low)' : 'var(--text-gray)'));
        const statBg = s.status === 'Irregular' ? 'var(--risk-mod)' : 'var(--risk-low)';

        html += `<tr style="border-bottom:1px solid var(--border-color);">
            <td style="text-align:center;font-weight:700; color:var(--text-dark); padding:12px;">${currentRank}</td>
            <td style="padding:12px;">
                <div style="font-weight:600; color:var(--text-dark); margin-bottom: 2px;">${s.full_name}</div>
                <div style="color:var(--text-gray); font-size: 0.8rem;">${s.student_number || '—'}</div>
            </td>
            <td style="font-weight:700;color:var(--accent-blue); text-align:center; padding:12px;">${gwa > 0 ? gwa.toFixed(2) : '—'}</td>
            <td style="text-align:center; padding:12px;"><span style="background:${honor.bg};color:${honor.color ?? 'white'};padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;border:1px solid var(--border-color);">${honor.text}</span></td>
            <td style="text-align:center; padding:12px;"><span style="background:${riskBg};color:white;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">${risk}</span></td>
            <td style="text-align:center; padding:12px;"><span style="background:${statBg};color:white;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">${s.status || 'Regular'}</span></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function sortClassTab(colIndex) {
    const tbody = document.getElementById('roster-body-class');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length || rows[0].querySelectorAll('td').length <= 1) return;

    if (classSortCol === colIndex) {
        classSortDir = classSortDir === 'desc' ? 'asc' : 'desc';
    } else {
        classSortCol = colIndex;
        classSortDir = 'desc';
    }

    const sortableCols = document.querySelectorAll('#class-thead-row th.sortable-col');
    sortableCols.forEach((th, idx) => {
        if (idx === colIndex) {
            th.setAttribute('aria-sort', classSortDir === 'desc' ? 'descending' : 'ascending');
        } else {
            th.setAttribute('aria-sort', 'none');
        }
    });

    document.querySelectorAll('.sort-arrow').forEach(el => {
        el.textContent = '⇅';
        el.style.color = 'var(--text-gray)';
    });
    const activeArrow = document.getElementById('sort-arrow-' + colIndex);
    if (activeArrow) {
        activeArrow.textContent = classSortDir === 'desc' ? ' ↓' : ' ↑';
        activeArrow.style.color = 'var(--accent-blue)';
    }

    rows.sort((a, b) => {
        let cellA = a.querySelectorAll('td')[colIndex]?.innerText.trim() || '';
        let cellB = b.querySelectorAll('td')[colIndex]?.innerText.trim() || '';
        
        cellA = cellA.split('\n')[0].trim();
        cellB = cellB.split('\n')[0].trim();

        const emptyA = cellA === '—' || cellA === '';
        const emptyB = cellB === '—' || cellB === '';
        if (emptyA && emptyB) return 0;
        if (emptyA) return 1;
        if (emptyB) return -1;

        const matchA = cellA.match(/[-+]?\d*\.?\d+/);
        const matchB = cellB.match(/[-+]?\d*\.?\d+/);
        const numA = matchA ? parseFloat(matchA[0]) : NaN;
        const numB = matchB ? parseFloat(matchB[0]) : NaN;

        if (!isNaN(numA) && !isNaN(numB)) {
            return classSortDir === 'desc' ? numB - numA : numA - numB;
        }
        return classSortDir === 'desc'
            ? cellB.localeCompare(cellA)
            : cellA.localeCompare(cellB);
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>

<?php require_once '../includes/footer.php'; ?>
