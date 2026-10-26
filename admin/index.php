<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

// --- FETCH ADMINISTRATIVE WORKLOAD COUNTS ---
$openReports = (int) $db->query("SELECT COUNT(*) FROM feedback_reports WHERE status IN ('open', 'awaiting_admin')")->fetchColumn();
$pendingBatches = (int) $db->query("SELECT COUNT(*) FROM pending_grade_batches WHERE status = 'pending'")->fetchColumn();
$pendingCorrections = (int) $db->query("SELECT COUNT(*) FROM pending_corrections WHERE status = 'pending'")->fetchColumn();
$supportReviews = (int) $db->query("SELECT COUNT(*) FROM academic_support_cases WHERE status = 'needs_review'")->fetchColumn();

$pendingApprovals = $pendingBatches + $pendingCorrections;
$totalAdminActions = $openReports + $pendingApprovals + $supportReviews;

// Fetch latest prediction timestamp
$latestPredQuery = $db->query("SELECT MAX(generated_at) FROM predictions")->fetchColumn();
$lastRunText = $latestPredQuery ? date('F j, Y \a\t g:i A', strtotime($latestPredQuery)) : 'No predictions generated yet.';

// --- FILTERS & DIRECTORY ---
$yearFilter    = trim($_GET['year'] ?? '');
$sectionFilter = trim($_GET['section'] ?? '');

// Schema check with graceful fallback for pre-migration databases
$hasRecordStatus = (bool) $db->query("SHOW COLUMNS FROM student_profiles LIKE 'record_status'")->fetch();
$activeClause    = $hasRecordStatus ? "record_status = 'Active'" : "status != 'Archived'";
$spActiveClause  = $hasRecordStatus ? "sp.record_status = 'Active'" : "sp.status != 'Archived'";

// FIXED: Exclude Archived/Graduated students from filter dropdowns
$years = array_column($db->query("SELECT DISTINCT year_level FROM student_profiles WHERE {$activeClause} ORDER BY year_level")->fetchAll(), 'year_level');

if ($yearFilter !== '') {
    $sectionsStmt = $db->prepare("SELECT DISTINCT section FROM student_profiles WHERE section IS NOT NULL AND {$activeClause} AND year_level = ? ORDER BY section");
    $sectionsStmt->execute([$yearFilter]);
} else {
    $sectionsStmt = $db->query("SELECT DISTINCT section FROM student_profiles WHERE section IS NOT NULL AND {$activeClause} ORDER BY section");
}
$sections = array_column($sectionsStmt->fetchAll(), 'section');

if ($sectionFilter !== '' && !in_array($sectionFilter, $sections, true)) {
    $sectionFilter = '';
}

// FIXED: Exclude Archived students from the main dashboard population query
$sql = "
    SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa,
           u.first_name, u.middle_name, u.last_name, p.risk_level, p.predicted_gwa
    FROM student_profiles sp
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id
        FROM predictions p2
        WHERE p2.student_id = sp.user_id
        ORDER BY p2.generated_at DESC, p2.id DESC
        LIMIT 1
    )
    WHERE {$spActiveClause}
";
$params = [];
if ($yearFilter !== '')    { $sql .= " AND sp.year_level = ?"; $params[] = $yearFilter; }
if ($sectionFilter !== '') { $sql .= " AND sp.section = ?";    $params[] = $sectionFilter; }
$sql .= " ORDER BY sp.section, u.last_name, u.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$total     = count($students);
$atRisk    = count(array_filter($students, fn($s) => $s['risk_level'] !== null && strtoupper($s['risk_level']) !== 'LOW'));
$highRisk  = count(array_filter($students, fn($s) => strtoupper($s['risk_level'] ?? '') === 'HIGH'));
$modRisk   = count(array_filter($students, fn($s) => strtoupper($s['risk_level'] ?? '') === 'MODERATE'));
$lowRisk   = count(array_filter($students, fn($s) => strtoupper($s['risk_level'] ?? '') === 'LOW'));
$noPredict = count(array_filter($students, fn($s) => $s['risk_level'] === null));
$irregular = count(array_filter($students, fn($s) => ($s['status'] ?? 'Regular') === 'Irregular'));
$gwas      = array_filter(array_column($students, 'current_gwa'), fn($g) => $g !== null);
$avgGwa    = count($gwas) ? array_sum($gwas) / count($gwas) : null;

$sectionGwas = [];
foreach ($students as $s) {
    $sec = $s['section'];
    if ($sec && $s['current_gwa'] !== null) {
        $sectionGwas[$sec][] = (float)$s['current_gwa'];
    }
}
ksort($sectionGwas); 
$chartSectionLabels = [];
$chartSectionAverages = [];
foreach ($sectionGwas as $sec => $grades) {
    $chartSectionLabels[] = $sec;
    $chartSectionAverages[] = round(array_sum($grades) / count($grades), 2);
}

$riskBadgeClass = fn(?string $risk) => match ($risk !== null ? strtoupper($risk) : null) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'na',
};

// Compact Program Analytics Snapshot (WP1)
require_once __DIR__ . '/../includes/analytics/service.php';
require_once __DIR__ . '/../includes/analytics/presentation.php';
try {
    $analyticsReport = analyticsReport($db, $user, []);
    $analyticsSummary = $analyticsReport['summary'];
    $analyticsTrend = $analyticsReport['trend'];
    $analyticsCoverage = $analyticsReport['prediction_coverage'];
    $analyticsSupport = $analyticsReport['support'];
} catch (Throwable $e) {
    error_log('Admin dashboard analytics snapshot unavailable: ' . $e->getMessage());
    $analyticsSummary = ['gwa' => null, 'student_count' => 0, 'eligible_grade_count' => 0, 'expected_grade_count' => 0, 'numeric_coverage_pct' => null];
    $analyticsTrend = ['previous' => null, 'change' => ['point_change' => null, 'relative_change_pct' => null, 'direction' => 'Unavailable']];
    $analyticsCoverage = ['coverage_pct' => null, 'available_count' => 0, 'student_count' => 0];
    $analyticsSupport = ['open_cases' => 0, 'eligible_cases' => 0, 'mean_first_action_hours' => null, 'first_action_sample' => 0];
}
$mtr20Hours = $analyticsSupport['mean_first_action_hours'] ?? null;
$mtr20Sample = $analyticsSupport['first_action_sample'] ?? 0;
$mtr20Formatted = formatAnalyticsDurationHours($mtr20Hours);
$mtr20SampleText = formatAnalyticsSampleSize($mtr20Sample);
$mtr20Warning = analyticsSampleWarning($mtr20Sample);

$pageTitle = 'Admin Dashboard';
$navItems = [
    ['Dashboard',          'index.php',     ''],
    ['Students',           'students.php',  ''],
    ['Faculty',            'faculty.php',   ''],
    ['Grades',             'grades.php',    ''],
    ['Program Analytics',  'analytics.php', ''],
    ['Activity & Inbox',   'activity.php',  ''],
    ['Settings',           'settings.php',  ''],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.admin-dashboard .dashboard-charts > * { min-width: 0; }
.admin-dashboard .roster-controls { display: flex; flex-wrap: wrap; gap: 8px; min-width: 0; }
.admin-dashboard .roster-controls > * { max-width: 100%; min-height: 44px; }
@media (max-width: 600px) {
    .admin-dashboard .roster-controls { width: 100%; }
    .admin-dashboard .roster-controls > * { flex: 1 1 100%; }
}

@keyframes batchSpin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .batch-spinner { animation: none !important; } }
#table-card { min-width: 0; max-width: 100%; overflow-x: auto; }

/* Dashboard Specific Elements */
.sortable-col { cursor: pointer; user-select: none; transition: background 0.15s; }
.sortable-col:hover { background: var(--table-header-bg) !important; }
.sort-arrow { font-size: 0.78rem; color: var(--text-gray); margin-left: 5px; transition: color 0.15s; }
.filter-select { padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-dark); }
.table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.search-box { padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 280px; max-width: 100%; background-color: var(--bg-color); color: var(--text-dark); }
.search-box:focus { border-color: var(--teal); outline: none; background-color: var(--card-bg); }
.pagination-bar { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
.page-btn { padding: 6px 12px; border: 1px solid var(--border-color); background: var(--card-bg); border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); font-family: inherit; transition: all 0.2s; }
.page-btn:hover { background: var(--bg-color); }
.page-btn.active { background: var(--teal); color: white; border-color: var(--teal); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.control-btn { transition: opacity 0.2s ease, transform 0.1s ease; }
.control-btn:hover { opacity: 0.85; }
.control-btn:active { transform: scale(0.98); }

.warning-banner { background: rgba(217, 119, 6, 0.08); }
[data-theme="dark"] .warning-banner { background: rgba(245, 158, 11, 0.12); }

/* BROWSER CACHE BYPASS: Explicitly define link styles locally */
.action-link { color: inherit !important; text-decoration: none !important; transition: opacity 0.2s; }
.action-link:hover { opacity: 0.7; text-decoration: underline !important; text-underline-offset: 2px; }

.dashboard-banner-btn {
    display: inline-block;
    padding: 10px 20px;
    background: var(--risk-mod) !important; 
    color: #FFFFFF !important;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none !important;
    font-size: 0.9rem;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(217, 119, 6, 0.2);
    white-space: nowrap;
}
.dashboard-banner-btn:hover {
    opacity: 0.9;
}

.kpi-drilldown { cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.kpi-drilldown:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
[data-theme="dark"] .kpi-drilldown:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.4); }
</style>

<div class="main-content admin-dashboard">
    <div class="header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <h1 style="margin: 0 0 4px 0;">Admin Dashboard</h1>
            <p style="margin: 0; color: var(--text-gray);">College-wide overview — College of Computing Studies.</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- Keep the CSV Option -->
            <button type="button" onclick="triggerSnapshotExportCsv()" style="padding: 10px 16px; background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export CSV
            </button>
            
            <!-- The new Branded PDF Option -->
            <button type="button" onclick="triggerSnapshotExportPdf()" style="padding: 10px 16px; background: var(--accent-blue); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(30, 77, 183, 0.2); transition: all 0.2s;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Download PDF Report
            </button>
        </div>
    </div>

    <!-- TIER 1: MACRO CONTROLS -->
    <?php if ($totalAdminActions > 0): ?>
        <div class="card warning-banner" style="margin-bottom: 20px; padding: 16px 24px; border-left: 4px solid var(--risk-mod); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 style="margin: 0 0 6px 0; color: var(--text-dark); font-size: 1.1rem; font-weight: 700;">Action Required</h3>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; color: var(--text-gray); font-size: 0.85rem;">
                    <?php if ($openReports > 0): ?>
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="activity.php?tab=inbox" class="action-link"><strong><?= $openReports ?></strong> open report<?= $openReports === 1 ? '' : 's' ?></a></span>
                    <?php endif; ?>
                    <?php if ($pendingApprovals > 0): ?>
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="activity.php?tab=approvals" class="action-link"><strong><?= $pendingApprovals ?></strong> pending approval<?= $pendingApprovals === 1 ? '' : 's' ?></a></span>
                    <?php endif; ?>
                    <?php if ($supportReviews > 0): ?>
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="activity.php?tab=support" class="action-link"><strong><?= $supportReviews ?></strong> support review<?= $supportReviews === 1 ? '' : 's' ?></a></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php $activityTarget = $pendingApprovals > 0 ? 'approvals' : ($openReports > 0 ? 'inbox' : 'support'); ?>
            <a href="activity.php?tab=<?= $activityTarget ?>" class="dashboard-banner-btn control-btn">
                Open Activity Workspace
            </a>
        </div>
    <?php endif; ?>

    <!-- SYSTEM-WIDE PREDICTION UPDATE -->
    <div class="card" style="margin-bottom: 24px; padding: 16px 24px; border-left: 4px solid var(--border-color); display: flex; flex-direction: column; gap: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="flex: 1; min-width: 200px;">
                <h3 style="margin: 0 0 6px 0; color: var(--text-dark); font-size: 1.1rem; font-weight: 700;">System-Wide Prediction Update</h3>
                <p style="margin: 0; color: var(--text-gray); font-size: 0.85rem; line-height: 1.4;">
                    Recalculate academic predictions and derived risk classifications for all active students. Decision Tree results are used when the trained model is available.
                </p>
                <p style="margin: 6px 0 0 0; color: var(--text-gray); font-size: 0.8rem; line-height: 1.4;">
                    Prediction basis: recorded academic history and current Prelim grades. Midterm records are available for academic review but are not included in the current trained prediction model.
                </p>
                <p style="margin: 6px 0 0 0; color: var(--text-gray); font-size: 0.8rem; font-weight: 600;">
                    Last run: <?= htmlspecialchars($lastRunText) ?>
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <button id="runBatchBtn" class="control-btn" onclick="runBatchPredictions()" style="padding: 10px 20px; background: var(--accent-blue); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; white-space: nowrap; box-shadow: 0 2px 4px rgba(30, 77, 183, 0.2); display: inline-flex; align-items: center; gap: 8px;">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                    <span>Run Predictions</span>
                </button>
            </div>
        </div>
        <div id="batchPredictStatus" role="status" aria-live="polite" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; line-height: 1.4;"></div>
    </div>

    <!-- PROGRAM ANALYTICS SNAPSHOT -->
    <div class="card" style="margin-bottom: 24px; padding: 20px 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0 0 4px 0; color: var(--text-dark); font-size: 1.1rem; font-weight: 700;">Program Academic Analytics Snapshot</h3>
                <p style="margin: 0; color: var(--text-gray); font-size: 0.85rem;">College-wide recorded patterns, data coverage, and support timeline metrics.</p>
            </div>
            <a href="analytics.php" class="btn btn--secondary" style="min-height: 40px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 600;">
                View Program Analytics
            </a>
        </div>
        <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(min(170px, 100%), 1fr)); gap: 16px; margin-bottom: 0;">
            <div class="stat-card" style="border-left-color: var(--teal) !important;">
                <h4 style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Recorded Mean GWA</h4>
                <h2 style="margin: 0; font-size: 1.6rem; color: var(--text-dark);" data-dashboard-metric="mean-gwa"><?= $analyticsSummary['gwa'] !== null ? number_format($analyticsSummary['gwa'], 2) : 'Unavailable' ?></h2>
                <div class="stat-card-subtext" style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">Weighted eligible grades</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--accent-blue) !important;">
                <h4 style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Point Change</h4>
                <h2 style="margin: 0; font-size: 1.6rem; color: var(--text-dark);" data-dashboard-metric="point-change"><?= $analyticsTrend['change']['point_change'] !== null ? ($analyticsTrend['change']['point_change'] > 0 ? '+' : '') . number_format($analyticsTrend['change']['point_change'], 2) : 'Unavailable' ?></h2>
                <div class="stat-card-subtext" style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">vs. comparable term</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--gold) !important;">
                <h4 style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Prediction Coverage</h4>
                <h2 style="margin: 0; font-size: 1.6rem; color: var(--text-dark);" data-dashboard-metric="prediction-coverage"><?= $analyticsCoverage['coverage_pct'] !== null ? number_format($analyticsCoverage['coverage_pct'], 1) . '%' : 'Unavailable' ?></h2>
                <div class="stat-card-subtext" style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;"><?= $analyticsCoverage['available_count'] ?> of <?= $analyticsCoverage['student_count'] ?> students</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--accent-blue) !important;">
                <h4 style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Grade Coverage</h4>
                <h2 style="margin: 0; font-size: 1.6rem; color: var(--text-dark);" data-dashboard-metric="grade-coverage"><?= $analyticsSummary['numeric_coverage_pct'] !== null ? number_format($analyticsSummary['numeric_coverage_pct'], 1) . '%' : 'Unavailable' ?></h2>
                <div class="stat-card-subtext" style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;"><?= $analyticsSummary['eligible_grade_count'] ?> / <?= $analyticsSummary['expected_grade_count'] ?> grades</div>
            </div>
            <div class="stat-card" style="border-left-color: var(--risk-mod) !important;">
                <h4 style="font-size: 0.78rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Open Support Cases</h4>
                <h2 style="margin: 0; font-size: 1.6rem; color: var(--risk-mod);" data-dashboard-metric="open-cases"><?= $analyticsSupport['open_cases'] ?? 0 ?></h2>
                <div class="stat-card-subtext" style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;"><?= $analyticsSupport['eligible_cases'] ?? 0 ?> total eligible</div>
            </div>
        </div>
        <div style="margin-top: 16px; padding: 14px 18px; background: var(--bg-hover, #f8fafc); border: 1px solid var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-dark); letter-spacing: 0.02em;">Average time before the first notice</span>
                        <span style="font-size: 0.7rem; font-weight: 600; padding: 2px 6px; background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); border-radius: 4px;">MTR-20</span>
                    </div>
                    <p style="margin: 2px 0 0 0; font-size: 0.8rem; color: var(--text-gray);">From referral creation to the first recorded notice.</p>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="text-align: right;">
                    <div style="font-size: 1.35rem; font-weight: 700; color: var(--text-dark); line-height: 1.2;" data-dashboard-metric="mean-time-action"><?= htmlspecialchars($mtr20Formatted) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 2px;"><?= htmlspecialchars($mtr20SampleText) ?></div>
                </div>
                <?php if ($mtr20Warning): ?>
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #fffbeb; border: 1px solid #fef3c7; color: #b45309; border-radius: 6px; font-size: 0.75rem; font-weight: 600;" role="status">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <?= htmlspecialchars($mtr20Warning) ?>
                    </span>
                <?php endif; ?>
                <div style="cursor: help; color: var(--text-gray); display: flex; align-items: center;" title="Measures the elapsed time from when an academic-support referral was created to when the first notice was recorded. Referrals without a recorded notice are not included. A smaller value generally means earlier documented outreach, but the result should be interpreted with the number of eligible referrals." aria-label="Help explanation for first notice timing">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- TIER 2: POPULATION DATA & FILTERS -->
    <div class="card">
        <form method="GET" action="index.php" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
            <label style="font-weight:600; font-size:0.9rem; color: var(--text-dark);">Year Level:</label>
            <select name="year" onchange="this.form.submit()" class="filter-select">
                <option value="">All Years</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= htmlspecialchars($y) ?>" <?= $yearFilter == $y ? 'selected' : '' ?>>Year <?= htmlspecialchars($y) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label style="font-weight:600; font-size:0.9rem; color: var(--text-dark);">Section:</label>
            <select name="section" onchange="this.form.submit()" class="filter-select">
                <option value="">All Sections</option>
                <?php foreach ($sections as $sec): ?>
                <option value="<?= htmlspecialchars($sec) ?>" <?= $sectionFilter === $sec ? 'selected' : '' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>
            
            <span style="color:var(--text-gray); font-size:0.9rem; margin-left: auto; font-weight: 600;">
                <strong style="color:var(--text-dark);"><?= $total ?></strong> students shown
            </span>
        </form>
    </div>

    <!-- STAT GRID WITH DRILL-DOWN CAPABILITIES -->
    <div class="stat-grid kpi-grid--five">
        <button type="button" class="stat-card kpi-card--action" style="border-left-color: var(--accent-blue) !important;" onclick="applyTableFilter('risk', ''); applyTableFilter('status', '');" aria-controls="table-card" aria-label="Filter student table: view all students">
            <h4>Total Students</h4>
            <h2 style="color: var(--text-dark);"><?= $total ?></h2>
            <div class="stat-card-subtext kpi-card__description">Active cohort population</div>
            <span class="kpi-action-cue">View all students &rarr;</span>
        </button>
        
        <button type="button" class="stat-card kpi-card--action <?= $atRisk > 0 ? 'red' : 'green' ?>" style="border-left-color: <?= $atRisk > 0 ? 'var(--risk-high)' : 'var(--risk-low)' ?> !important;" onclick="applyTableFilter('risk', 'AT_RISK');" aria-controls="table-card" aria-label="Filter student table: At-Risk students">
            <h4>At-Risk</h4>
            <h2 style="margin-bottom: 2px; color: <?= $atRisk > 0 ? 'var(--risk-high)' : 'var(--risk-low)' ?>;"><?= $atRisk ?></h2>
            <?php if ($atRisk > 0): ?>
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--risk-high); margin-top: 4px;">
                    <?= $highRisk ?> High <span style="color: var(--text-gray); font-weight: normal; margin: 0 4px;">|</span> <span style="color: var(--risk-mod);"><?= $modRisk ?> Moderate</span>
                </div>
            <?php else: ?>
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--risk-low); margin-top: 4px;">All clear</div>
            <?php endif; ?>
            <span class="kpi-action-cue">Filter at-risk &rarr;</span>
        </button>

        <button type="button" class="stat-card kpi-card--action" style="border-left-color: var(--gold) !important;" onclick="applyTableFilter('status', 'Irregular');" aria-controls="table-card" aria-label="Filter student table: Irregular students">
            <h4>Irregular</h4>
            <h2 style="color: var(--gold);"><?= $irregular ?></h2>
            <div class="stat-card-subtext kpi-card__description">Non-standard curriculum progression</div>
            <span class="kpi-action-cue">Filter irregular &rarr;</span>
        </button>
        
        <div class="stat-card" style="border-left-color: var(--teal) !important;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <h4>Avg Cumulative GWA</h4>
                <span class="custom-tooltip tooltip-top-right" tabindex="0" aria-label="Calculation Scope">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-gray);"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span class="tooltip-text" role="tooltip">Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.</span>
                </span>
            </div>
            <h2 style="color: var(--text-dark);"><?= $avgGwa !== null ? number_format($avgGwa, 2) : '—' ?></h2>
            <div class="stat-card-subtext kpi-card__description">Historical cumulative cohort metric</div>
        </div>
        
        <?php if ($noPredict > 0): ?>
        <button type="button" class="stat-card kpi-card--action" style="border-left-color: var(--text-gray) !important;" onclick="applyTableFilter('risk', 'N/A');" aria-controls="table-card" aria-label="Filter student table: students missing predictions">
            <h4>No Prediction Yet</h4>
            <h2 style="color:var(--text-gray);"><?= $noPredict ?></h2>
            <div class="stat-card-subtext kpi-card__description">Excluded from At-Risk count</div>
            <span class="kpi-action-cue">Filter unpredicted &rarr;</span>
        </button>
        <?php endif; ?>
    </div>

    <!-- CHARTS -->
    <div class="dashboard-charts" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(400px, 100%), 1fr)); gap: 24px; margin-bottom: 32px;">
        <div class="card" style="position: relative; height: 320px; margin-bottom: 0;">
            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">College-Wide Risk Distribution</div>
            <div style="position: relative; height: 240px; width: 100%;">
                <canvas id="riskDonutChart" role="img" aria-label="College-wide risk distribution donut chart" aria-describedby="risk-donut-summary"></canvas>
                <div id="risk-donut-summary" class="sr-only">
                    <table>
                        <caption>College-Wide Risk Distribution Summary</caption>
                        <thead><tr><th scope="col">Risk Level</th><th scope="col">Student Count</th></tr></thead>
                        <tbody>
                            <tr><td>High Risk</td><td><?= (int)$highRisk ?></td></tr>
                            <tr><td>Moderate Risk</td><td><?= (int)$modRisk ?></td></tr>
                            <tr><td>Low Risk</td><td><?= (int)$lowRisk ?></td></tr>
                            <tr><td>No Prediction</td><td><?= (int)$noPredict ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card" style="position: relative; height: 320px; margin-bottom: 0;">
            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">Average GWA by Section</div>
            <div style="position: relative; height: 240px; width: 100%;">
                <canvas id="sectionGwaBarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- TIER 3: MICRO ACTION (Database & Directory) -->
    <div class="card scroll-target" id="table-card" style="margin-bottom: 0;">
        <div class="table-toolbar">
            <input type="text" id="dashboard-search" class="search-box" placeholder="Search by name, student no., or section…">
            <div class="roster-controls">
                <select id="filter-risk" class="filter-select" onchange="currentPage=1; renderPage();">
                    <option value="">All Risks</option>
                    <option value="AT_RISK">At-Risk (High & Mod)</option>
                    <option value="HIGH">High Risk</option>
                    <option value="MODERATE">Moderate Risk</option>
                    <option value="LOW">Low Risk</option>
                    <option value="N/A">No Data</option>
                </select>
                <select id="filter-status" class="filter-select" onchange="currentPage=1; renderPage();">
                    <option value="">All Statuses</option>
                    <option value="Regular">Regular</option>
                    <option value="Irregular">Irregular</option>
                </select>
                <button type="button" onclick="triggerRosterExport()" style="background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; white-space: nowrap;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Export Roster CSV
                </button>
            </div>
        </div>

        <div class="data-table-scroll" role="region" aria-label="Student Cohort Roster" tabindex="0">
            <table id="admin-table" style="min-width: 900px;">
                <caption class="sr-only">Student Cohort Roster</caption>
                <thead>
                    <tr>
                        <th scope="col" class="sortable-col" data-type="string" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(0)">
                                <span>Student No.</span> <span class="sort-arrow" id="sort-arrow-0" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="string" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(1)">
                                <span>Name</span> <span class="sort-arrow" id="sort-arrow-1" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="string" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(2)">
                                <span>Section</span> <span class="sort-arrow" id="sort-arrow-2" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="number" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(3)">
                                <span>Year</span> <span class="sort-arrow" id="sort-arrow-3" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="number" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(4)">
                                <span>Cumulative GWA (Historical)</span> <span class="sort-arrow" id="sort-arrow-4" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="number" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(5)">
                                <span>Projected Term GWA</span> <span class="sort-arrow" id="sort-arrow-5" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="string" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(6)">
                                <span>Status</span> <span class="sort-arrow" id="sort-arrow-6" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                        <th scope="col" class="sortable-col" data-type="risk" aria-sort="none">
                            <button type="button" class="table-sort-button" onclick="sortTable(7)">
                                <span>Risk</span> <span class="sort-arrow" id="sort-arrow-7" aria-hidden="true">⇅</span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody id="admin-tbody">
                    <?php if (empty($students)): ?>
                    <tr id="admin-table-empty-row">
                        <td colspan="8" style="text-align: center; padding: 36px 16px; color: var(--text-gray);">
                            <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 0.95rem; color: var(--text-dark);">No students match this filter.</p>
                            <p style="margin: 0; font-size: 0.85rem; color: var(--text-gray);">Try adjusting the year or section filter above.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($students as $s):
                        $riskRaw = $s['risk_level'] !== null ? strtoupper($s['risk_level']) : 'N/A';
                        $searchBlob = strtolower($s['student_number'] . ' ' . formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']) . ' ' . ($s['section'] ?? ''));
                        
                        $adminTooltip = "";
                        if ($riskRaw === 'HIGH') {
                            $adminTooltip = "High Risk: Student is statistically likely to face academic delays based on historical failures or a low GWA trajectory.";
                        } elseif ($riskRaw === 'MODERATE') {
                            $adminTooltip = "Moderate Risk: Student is approaching delay thresholds and should be monitored.";
                        } elseif ($riskRaw === 'LOW') {
                            $adminTooltip = "Low Risk: Student is currently maintaining a safe academic trajectory.";
                        } else {
                            $adminTooltip = "No Prediction Yet: The model requires more data to generate a risk profile.";
                        }
                    ?>
                    <tr data-search="<?= htmlspecialchars($searchBlob) ?>" data-risk="<?= htmlspecialchars($riskRaw) ?>" data-status="<?= htmlspecialchars($s['status'] ?? 'Regular') ?>">
                        <td data-sort="<?= htmlspecialchars($s['student_number']) ?>"><?= htmlspecialchars($s['student_number']) ?></td>
                        <td data-sort="<?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?>" style="font-weight: 500; color: var(--teal);"><?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?></td>
                        <td data-sort="<?= htmlspecialchars($s['section'] ?? '') ?>"><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                        <td data-sort="<?= $s['year_level'] !== null ? (int) $s['year_level'] : '' ?>"><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                        <td data-sort="<?= $s['current_gwa'] !== null ? (float) $s['current_gwa'] : '' ?>" style="font-weight: 600;"><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                        <td data-sort="<?= $s['predicted_gwa'] !== null ? (float) $s['predicted_gwa'] : '' ?>" style="font-weight: 600;"><?= $s['predicted_gwa'] !== null ? number_format($s['predicted_gwa'], 2) : '<span style="color: var(--text-gray);">N/A</span>' ?></td>
                        <td data-sort="<?= htmlspecialchars($s['status'] ?? 'Regular') ?>"><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                        
                        <td data-sort="<?= $riskRaw ?>" style="padding: 12px; text-align: left;">
                            <span class="badge custom-tooltip <?= $riskBadgeClass($s['risk_level']) ?>" style="display: inline-flex; align-items: center; gap: 5px;" tabindex="0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                <?= $s['risk_level'] !== null ? htmlspecialchars(ucfirst(strtolower($s['risk_level']))) : 'N/A' ?>
                                <span class="tooltip-text" role="tooltip"><?= htmlspecialchars($adminTooltip) ?></span>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-bar" id="pagination-bar"<?= empty($students) ? ' style="display:none;"' : '' ?>></div>
    </div>
</div>

<script>
function applyTableFilter(type, val) {
    const el = document.getElementById('filter-' + type);
    if (el) el.value = val;
    currentPage = 1;
    renderPage();
    if (window.UDM && typeof window.UDM.scrollToTarget === 'function') {
        window.UDM.scrollToTarget('table-card', { focusTarget: 'dashboard-search' });
    } else {
        const target = document.getElementById('table-card') || document.getElementById('admin-table');
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function triggerRosterExport() {
    const riskVal = document.getElementById('filter-risk')?.value || '';
    const statusVal = document.getElementById('filter-status')?.value || ''; 
    const searchVal = document.getElementById('dashboard-search')?.value || '';

    const params = new URLSearchParams();
    if (riskVal) {
        params.append('risk', riskVal); 
    }
    if (searchVal) {
        params.append('search', searchVal);
    }

    window.location.href = 'export_risk_roster.php?' + params.toString();
}

// FIXED: Hooked up exactly to the updated PHP export files created in the prior step
function triggerSnapshotExportCsv() {
    window.location.href = 'export_program_snapshot.php' + window.location.search;
}

function triggerSnapshotExportPdf() {
    window.location.href = 'export_program_snapshot_pdf.php' + window.location.search;
}

const csrfToken = <?= json_encode(getCsrfToken()) ?>;

let isBatchRunning = false;

function runBatchPredictions() {
    if (isBatchRunning) {
        return;
    }

    const btn = document.getElementById('runBatchBtn');
    const statusDiv = document.getElementById('batchPredictStatus');
    const originalText = btn.innerHTML;
    
    isBatchRunning = true;
    btn.innerHTML = '<span class="batch-spinner" style="display:inline-block; width:12px; height:12px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:batchSpin 0.8s linear infinite; vertical-align:middle; margin-right:6px;" aria-hidden="true"></span><span>Running Batch Predict…</span>';
    btn.style.opacity = '0.7';
    btn.disabled = true;
    btn.setAttribute('aria-disabled', 'true');

    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.style.background = 'var(--bg-light, #f8f9fa)';
        statusDiv.style.border = '1px solid var(--border-color, #e2e8f0)';
        statusDiv.style.color = 'var(--text-dark, #2d3748)';
        statusDiv.innerHTML = '<strong>Batch prediction is running.</strong> Keep this page open while UDM-RADAR processes the eligible student records.';
    }

    fetch('../api/batch_predict.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            csrf_token: csrfToken
        })
    })
    .then(async response => {
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            const serverMsg = data && data.error ? data.error : ('Server error (HTTP ' + response.status + ')');
            throw new Error(serverMsg);
        }
        return data;
    })
    .then(data => {
        if (data && data.status === 'success') {
            const metrics = data.metrics || {};
            const total = metrics.total_processed ?? 0;
            const success = metrics.success_count ?? 0;
            const skipped = metrics.fail_count ?? 0;
            const cases = metrics.cases_created ?? 0;

            if (statusDiv) {
                statusDiv.style.background = '#e6f4ea';
                statusDiv.style.border = '1px solid #137333';
                statusDiv.style.color = '#137333';
                statusDiv.innerHTML = `<strong>Batch Prediction Complete:</strong> Processed ${total} student${total === 1 ? '' : 's'} (${success} evaluated, ${skipped} skipped for insufficient data). Created ${cases} support case${cases === 1 ? '' : 's'}. Refreshing view…`;
            }

            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            throw new Error(data && data.error ? data.error : 'Unexpected response format.');
        }
    })
    .catch(error => {
        console.error('Batch Predict Error:', error);
        if (statusDiv) {
            statusDiv.style.background = '#fce8e6';
            statusDiv.style.border = '1px solid #c5221f';
            statusDiv.style.color = '#c5221f';

            let message = 'The request did not finish in this page. Before running it again, check the latest prediction results to avoid creating a duplicate batch.';
            if (error.message && error.message.includes('already running')) {
                message = 'A batch prediction is already running. Please wait for the current job to finish.';
            } else if (error.message && !error.message.includes('Server error') && !error.message.includes('Failed to fetch')) {
                message = error.message;
            }
            statusDiv.innerHTML = `<strong>Notice:</strong> ${message}`;
        }
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.disabled = false;
        btn.removeAttribute('aria-disabled');
        isBatchRunning = false;
    });
}

// --- Dynamic Theme Resolution for Chart.js ---
function getChartThemeColors() {
    const root = getComputedStyle(document.documentElement);
    return {
        high: root.getPropertyValue('--risk-high').trim(),
        mod: root.getPropertyValue('--risk-mod').trim(),
        low: root.getPropertyValue('--risk-low').trim(),
        na: root.getPropertyValue('--text-gray').trim(),
        teal: root.getPropertyValue('--teal').trim(),
        text: root.getPropertyValue('--text-dark').trim(),
        border: root.getPropertyValue('--border-color').trim()
    };
}

let themeColors = getChartThemeColors();

// --- Chart.js Implementations ---
const ctxDonut = document.getElementById('riskDonutChart').getContext('2d');
const donutChart = new Chart(ctxDonut, {
    type: 'doughnut',
    data: {
        labels: ['High Risk', 'Moderate Risk', 'Low Risk', 'No Prediction Yet'],
        datasets: [{
            data: [<?= $highRisk ?>, <?= $modRisk ?>, <?= $lowRisk ?>, <?= $noPredict ?>],
            backgroundColor: [themeColors.high, themeColors.mod, themeColors.low, themeColors.na],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        cutout: '70%', 
        plugins: { 
            legend: { 
                position: 'right', 
                labels: { color: themeColors.text, usePointStyle: true, boxWidth: 8, font: { size: 11 } } 
            } 
        } 
    }
});

const ctxBar = document.getElementById('sectionGwaBarChart').getContext('2d');
const barChart = new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartSectionLabels) ?>,
        datasets: [{
            label: 'Average GWA',
            data: <?= json_encode($chartSectionAverages) ?>,
            backgroundColor: themeColors.teal,
            borderRadius: 4,
            barPercentage: 0.6
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: false } }, 
        scales: { 
            x: { ticks: { color: themeColors.text }, grid: { color: themeColors.border } },
            y: { min: 1.0, max: 4.0, ticks: { stepSize: 0.5, color: themeColors.text }, grid: { color: themeColors.border }, title: { display: true, text: 'GWA (4.00 = Highest)', color: themeColors.text } } 
        } 
    }
});

// --- Dynamic Chart Recolor & Accessibility Observer ---
const observer = new MutationObserver(() => {
    themeColors = getChartThemeColors();
    const isReducedMotion = document.documentElement.getAttribute('data-motion') === 'reduce';
    
    // Update Donut
    donutChart.options.animation = !isReducedMotion;
    donutChart.data.datasets[0].backgroundColor = [themeColors.high, themeColors.mod, themeColors.low, themeColors.na];
    donutChart.options.plugins.legend.labels.color = themeColors.text;
    donutChart.update('none');
    
    // Update Bar Chart
    barChart.options.animation = !isReducedMotion;
    barChart.data.datasets[0].backgroundColor = themeColors.teal;
    barChart.options.scales.x.ticks.color = themeColors.text;
    barChart.options.scales.x.grid.color = themeColors.border;
    barChart.options.scales.y.ticks.color = themeColors.text;
    barChart.options.scales.y.grid.color = themeColors.border;
    barChart.options.scales.y.title.color = themeColors.text;
    barChart.update('none');
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-color-style', 'data-theme', 'data-motion'] });


// --- Search & Pagination Engine ---
function clearDashboardFilters() {
    const search = document.getElementById('dashboard-search');
    const risk = document.getElementById('filter-risk');
    const status = document.getElementById('filter-status');
    if (search) search.value = '';
    if (risk) risk.value = '';
    if (status) status.value = '';
    currentPage = 1;
    renderPage();
}

const ROWS_PER_PAGE = 25;
let currentPage = 1;

function getVisibleRows() {
    const term = document.getElementById('dashboard-search')?.value.trim().toLowerCase() || '';
    const riskFilter = document.getElementById('filter-risk')?.value || '';
    const statusFilter = document.getElementById('filter-status')?.value || '';
    
    const allRows = Array.from(document.querySelectorAll('#admin-tbody tr:not(#admin-table-empty-row)'));
    
    return allRows.filter(row => {
        const matchSearch = !term || (row.dataset.search && row.dataset.search.includes(term));
        
        let matchRisk = true;
        if (riskFilter === 'AT_RISK') {
            matchRisk = row.dataset.risk === 'HIGH' || row.dataset.risk === 'MODERATE';
        } else if (riskFilter) {
            matchRisk = row.dataset.risk === riskFilter;
        }

        let matchStatus = !statusFilter || row.dataset.status === statusFilter;
        
        return matchSearch && matchRisk && matchStatus;
    });
}

function renderPage() {
    const allRows = Array.from(document.querySelectorAll('#admin-tbody tr:not(#admin-table-empty-row)'));
    const visible = getVisibleRows();
    
    allRows.forEach(r => r.style.display = 'none'); 

    const bar = document.getElementById('pagination-bar');
    let emptyRow = document.getElementById('admin-table-empty-row');

    if (visible.length === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'admin-table-empty-row';
            emptyRow.innerHTML = `
                <td colspan="8" style="text-align: center; padding: 36px 16px; color: var(--text-gray);">
                    <p style="margin: 0 0 6px 0; font-weight: 600; font-size: 0.95rem; color: var(--text-dark);">No students match the selected filters.</p>
                    <p style="margin: 0 0 16px 0; font-size: 0.85rem; color: var(--text-gray);">Try changing the search term or clearing the active filters.</p>
                    <button type="button" class="page-btn" onclick="clearDashboardFilters()">Clear filters</button>
                </td>
            `;
            document.getElementById('admin-tbody')?.appendChild(emptyRow);
        }
        emptyRow.style.display = '';
        if (bar) bar.style.display = 'none';
        return;
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
    if (bar) bar.style.display = 'flex';

    const totalPages = Math.max(1, Math.ceil(visible.length / ROWS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    
    const start = (currentPage - 1) * ROWS_PER_PAGE;
    visible.slice(start, start + ROWS_PER_PAGE).forEach(r => r.style.display = '');

    if (!bar) return;

    let html = '';
    html += `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="goToPage(${currentPage-1})">‹ Prev</button>`;
    
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button class="page-btn" onclick="goToPage(1)">1</button>`;
        if (startPage > 2) html += `<span style="color:var(--text-gray);">...</span>`;
    }
    
    for (let p = startPage; p <= endPage; p++) {
        html += `<button class="page-btn ${p===currentPage?'active':''}" onclick="goToPage(${p})">${p}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span style="color:var(--text-gray);">...</span>`;
        html += `<button class="page-btn" onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }

    html += `<button class="page-btn" ${currentPage===totalPages?'disabled':''} onclick="goToPage(${currentPage+1})">Next ›</button>`;
    html += `<span style="color:var(--text-gray); font-size:0.8rem; margin-left:10px;">Showing ${visible.length} results</span>`;
    
    bar.innerHTML = html;
}

function goToPage(p) { currentPage = p; renderPage(); }

if (document.getElementById('dashboard-search')) {
    document.getElementById('dashboard-search').addEventListener('input', () => { currentPage = 1; renderPage(); });
}

renderPage();

// --- Table Sorting Script ---
let currentSortCol = -1;
let currentSortDir = 'asc';

function sortTable(colIndex) {
    const tbody = document.getElementById('admin-tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr:not(#admin-table-empty-row)'));
    if (!rows.length) return;

    if (currentSortCol === colIndex) {
        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortCol = colIndex;
        currentSortDir = 'asc';
    }

    const sortableCols = document.querySelectorAll('#admin-table th.sortable-col');
    sortableCols.forEach((th, idx) => {
        if (idx === colIndex) {
            th.setAttribute('aria-sort', currentSortDir === 'asc' ? 'ascending' : 'descending');
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
        activeArrow.textContent = currentSortDir === 'asc' ? ' ↑' : ' ↓';
        activeArrow.style.color = 'var(--teal)'; 
    }

    const colType = document.querySelectorAll('#admin-table th')[colIndex]?.dataset.type || 'string';
    const getSort = (row, idx) => row.querySelectorAll('td')[idx]?.dataset.sort ?? '';

    rows.sort((a, b) => {
        let rawA = getSort(a, colIndex);
        let rawB = getSort(b, colIndex);
        const emptyA = rawA === '';
        const emptyB = rawB === '';
        if (emptyA && emptyB) return 0;
        if (emptyA) return 1;
        if (emptyB) return -1;
        let diff = 0;

        if (colType === 'risk') {
            const riskMap = { 'HIGH': 3, 'MODERATE': 2, 'LOW': 1, 'NA': 0 };
            diff = (riskMap[rawA] ?? -1) - (riskMap[rawB] ?? -1);
        } else if (colType === 'number') {
            diff = parseFloat(rawA) - parseFloat(rawB);
        } else {
            diff = rawA.localeCompare(rawB);
        }

        if (diff === 0 && colIndex === 2) {
            diff = getSort(a, 1).localeCompare(getSort(b, 1));
            if (currentSortDir === 'desc') diff = -diff;
        }

        if (diff === 0 && colIndex === 7) {
            let gwaA = parseFloat(getSort(a, 4));
            let gwaB = parseFloat(getSort(b, 4));
            gwaA = isNaN(gwaA) ? Infinity : gwaA;
            gwaB = isNaN(gwaB) ? Infinity : gwaB;
            diff = gwaA - gwaB;
            if (diff === 0) diff = getSort(a, 1).localeCompare(getSort(b, 1));
            if (currentSortDir === 'desc') diff = -diff;
        }

        return currentSortDir === 'asc' ? diff : -diff;
    });

    rows.forEach(row => tbody.appendChild(row));
    const emptyRow = document.getElementById('admin-table-empty-row');
    if (emptyRow) tbody.appendChild(emptyRow);
    currentPage = 1;
    renderPage();
}
</script>

<?php require_once '../includes/footer.php'; ?>