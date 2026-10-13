<?php
/**
 * CLI-only, controlled view fixture. No authentication, DB connection or handler
 * runs. Extracts actual templates after their bootstrap; Chart.js is mocked by
 * the browser spec. Explicit synthetic values keep missing fixture data fatal.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$root = dirname(__DIR__, 2);
define('BASE_URL', '/');
require_once $root . '/config/constants.php';
function getCsrfToken() { return 'fixture-only-no-session'; }
function viewFragment(string $source, string $start, string $end): string {
    $a = strpos($source, $start);
    $b = $a === false ? false : strpos($source, $end, $a + strlen($start));
    if ($a === false || $b === false) throw new RuntimeException('Template boundaries missing');
    return substr($source, $a + strlen($start), $b - $a - strlen($start));
}
$route = $argv[1] ?? '';
$allowed = ['login.php', 'student/index.php', 'faculty/index.php', 'admin/index.php',
    'student/dashboard.php', 'student/grades.php', 'faculty/analytics.php', 'student/trend.php', 'admin/settings.php', 'faculty/feedback.php'];
if (!in_array($route, $allowed, true)) throw new InvalidArgumentException('Unknown fixture route');
$source = file_get_contents($root . '/' . $route);
$error = $success = '';
if ($route === 'login.php') {
    $start = strpos($source, '<!DOCTYPE html>');
    if ($start === false) throw new RuntimeException('Login template missing');
    eval('?>' . substr($source, $start));
    exit;
}
$user = ['id' => 1, 'first_name' => 'Synthetic', 'role' => explode('/', $route)[0]];
$_SESSION = ['csrf_token' => getCsrfToken()];
$_SERVER['PHP_SELF'] = '/' . $route;
$navBadges = [];
$navItems = [['Home', 'index.php', ''], ['Dashboard', 'dashboard.php', ''], ['Grades', 'grades.php', ''], ['Settings', 'settings.php', '']];
$term = getCurrentTerm();
$currentSy = $term['school_year'];
$currentSem = (string)$term['semester'];
$displayName = 'Synthetic Maria Alexandra Dela Cruz San Buenaventura';
$fName = 'Maria Alexandra';
$p = ['first_name' => 'Maria Alexandra', 'student_number' => 'SYN-00001', 'user_id' => 'SYN-FACULTY-001', 'college' => 'College of Computing Studies', 'course' => 'BS Information Technology', 'year_level' => '3', 'section' => 'SYN-3A'];
$attentionSubjects = $attentionClasses = 1;
$enrolledSubjects = 6; $totalUnits = 18; $encodedCount = 4; $missingCount = 2;
$recentActivities = [];
$myLoads = [1, 2]; $uniqueSections = ['SYN-3A']; $uniqueStudents = [1, 2]; $pendingRecordsCount = 2;
$classPreview = [['title' => 'Synthetic Advanced Information Systems and Application Development', 'section' => 'SYN-3A', 'students' => 35, 'pct' => 75]];
$totalAdminActions = $openReports = $pendingApprovals = $supportReviews = 1;
$lastRunText = 'No run in this controlled fixture';
$years = ['1', '2', '3', '4']; $sections = ['SYN-3A']; $yearFilter = $sectionFilter = '';
$total = 3; $atRisk = $highRisk = $modRisk = $lowRisk = $irregular = $noPredict = 1; $avgGwa = 2.5;
$students = []; $chartSectionLabels = ['SYN-3A']; $chartSectionAverages = [2.5];
$current_gwa = 2.5; $currentGwaDisplay = '2.50'; $hasCurrentGwa = true;
$prediction_source = null; $display_honor = null; $display_predicted_gwa = null; $display_risk = null;
$honor_color = $riskBg = 'var(--text-gray)'; $riskTooltip = 'Synthetic layout fixture'; $shortRiskSummary = 'Insufficient data';
$explanation = ['disclaimer' => 'Prototype performance on synthetic academic records', 'coverage_summary' => 'Synthetic layout fixture only', 'freshness_label' => 'Not a live prediction'];
$risk_factors = [['type' => 'info', 'text' => 'Synthetic context for layout verification.']]; $triage_alerts = [];
$current_subjects = [
    ['subject_id' => 1, 'code' => 'SYN101', 'title' => 'Synthetic Advanced Application Development and System Architecture', 'units' => 3, 'prelim_point' => 3.00, 'predicted_final' => 3.00, 'final_risk' => 'LOW', 'prelim_raw' => null, 'midterm_raw' => null, 'prefinal_raw' => null],
];
$currentGrades = [
    ['subject_id' => 1, 'code' => 'ITE421', 'title' => 'Social and Professional Issues in Computing', 'units' => 3, 'prelim' => 90.0, 'midterm' => null, 'prefinal' => null, 'final_grade' => null],
    ['subject_id' => 2, 'code' => 'ITE431', 'title' => 'System Integration and Architecture', 'units' => 3, 'prelim' => 70.0, 'midterm' => null, 'prefinal' => null, 'final_grade' => null],
];
$historyTerms = [
    '2025-2026 — Sem 1' => [
        ['subject_id' => 3, 'code' => 'ITE321', 'title' => 'Information and Security Assurance 2', 'units' => 3, 'final_grade' => 3.75],
        ['subject_id' => 4, 'code' => 'ITE322', 'title' => 'Methods of Research', 'units' => 3, 'final_grade' => 3.00],
        ['subject_id' => 5, 'code' => 'ITE323', 'title' => 'Special Topics in Software Development', 'units' => 3, 'final_grade' => 'INC'],
        ['subject_id' => 6, 'code' => 'ITE324', 'title' => 'Failed Prerequisite Subject Test', 'units' => 3, 'final_grade' => 0.00],
    ]
];
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
if (!function_exists('formatPercentage')) {
    function formatPercentage(float|int|string|null $val): string {
        if ($val === null || $val === '') return '—';
        return round((float)$val) . '%';
    }
}
$riskBadgeClass = fn(string $risk) => match (strtoupper($risk)) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'na',
};
$valid_grades = FINAL_GRADE_POINTS;
if ($route === 'student/dashboard.php') {
    // Reuse the real options renderer, with no database/bootstrap execution.
    eval(viewFragment($source, '$valid_grades = FINAL_GRADE_POINTS;', "$" . "pageTitle ="));
}
$total_assigned_classes = 2; $unique_students = 35; $completeness_pct = 75; $total_at_risk_portfolio = 3;
$class_loads = []; $atRiskGrouped = [];
$latest_hist = 2.5; $latest_curr = 2.75; $pred_gwa = null; $prediction = null;
$curr_coverage_text = '4 of 6 subjects recorded'; $diff_text = 'Synthetic trend context';
$semData = [['semester' => $currentSy, 'gwa' => 2.5]]; $semDataTypes = ['historical'];
$activeMetrics = ['mae' => '0.1234', 'rmse' => '0.2345', 'r2' => '0.8765'];

echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>';
echo file_get_contents($root . '/assets/css/dashboard.css');
echo '</style></head><body>';
$sidebar = file_get_contents($root . '/includes/sidebar.php');
eval('?>' . substr($sidebar, strpos($sidebar, '?>') + 2));
if ($route === 'faculty/feedback.php') {
    echo '<main class="main-content" style="min-height:1800px"><h1>Controlled notice fixture</h1><button id="issue-trigger" onclick="openInterventionModal(42, \'Synthetic Maria Alexandra Dela Cruz San Buenaventura\', \'SYN-3A\', \'Advanced Application Development\')">Issue notice</button><button id="view-trigger" onclick="openViewNoticeModal(\'Please review the following topics and contact your instructor. \'.repeat(30), \'Low recorded assessment score\')">View notice</button></main>';
    $start = strpos($source, '<!-- Modal: Issue Notice -->');
    if ($start === false) throw new RuntimeException('Notice markup missing');
    eval('?>' . substr($source, $start, strpos($source, "<?php require_once '../includes/footer.php';", $start) - $start));
} else {
    eval(viewFragment($source, "require_once '../includes/sidebar.php';", "<?php require_once '../includes/footer.php';"));
}
// The real Guide preserves its existing overlay clearance; no Guide change is tested here.
require $root . '/includes/glossary_modal.php';
echo '<script>' . file_get_contents($root . '/assets/js/ui.js') . '</script></body></html>';
