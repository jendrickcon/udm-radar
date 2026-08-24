<?php
// admin/export_program_snapshot.php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/csv_export_helpers.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

$yearFilter    = trim($_GET['year'] ?? '');
$sectionFilter = trim($_GET['section'] ?? '');

$params = [];
$whereClause = "WHERE sp.status != 'Archived'";

if ($yearFilter !== '') {
    $whereClause .= " AND sp.year_level = ?";
    $params[] = $yearFilter;
}
if ($sectionFilter !== '') {
    $whereClause .= " AND sp.section = ?";
    $params[] = $sectionFilter;
}

$lastRunStmt = $db->query("SELECT MAX(generated_at) as last_run FROM predictions");
$lastRunDate = $lastRunStmt->fetchColumn();
$lastRunText = $lastRunDate ? date('F j, Y, g:i A', strtotime($lastRunDate)) : 'No predictions generated yet';

$sqlSummary = "
    SELECT 
        COUNT(sp.user_id) AS total_students,
        SUM(CASE WHEN p.risk_level = 'HIGH' THEN 1 ELSE 0 END) AS high_risk,
        SUM(CASE WHEN p.risk_level = 'MODERATE' THEN 1 ELSE 0 END) AS mod_risk,
        SUM(CASE WHEN p.risk_level = 'LOW' THEN 1 ELSE 0 END) AS low_risk,
        SUM(CASE WHEN p.risk_level IS NULL OR p.risk_level = '' THEN 1 ELSE 0 END) AS na_risk,
        SUM(CASE WHEN sp.status = 'Irregular' THEN 1 ELSE 0 END) AS irregular_count,
        AVG(sp.current_gwa) AS overall_avg_gwa
    FROM student_profiles sp
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2 
        WHERE p2.student_id = sp.user_id 
        ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    $whereClause
";
$stmtSummary = $db->prepare($sqlSummary);
$stmtSummary->execute($params);
$summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

$total = (int)$summary['total_students'];
$pctHigh = $total > 0 ? round(($summary['high_risk'] / $total) * 100, 1) : 0;
$pctMod  = $total > 0 ? round(($summary['mod_risk'] / $total) * 100, 1) : 0;
$pctLow  = $total > 0 ? round(($summary['low_risk'] / $total) * 100, 1) : 0;
$pctNA   = $total > 0 ? round(($summary['na_risk'] / $total) * 100, 1) : 0;
$overallAvgGwa = $summary['overall_avg_gwa'] !== null ? number_format((float)$summary['overall_avg_gwa'], 2) : 'N/A';

// Prediction Source Breakdown
$sqlSource = "
    SELECT p.prediction_source, COUNT(*) AS cnt
    FROM student_profiles sp
    JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2 WHERE p2.student_id = sp.user_id ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    $whereClause GROUP BY p.prediction_source
";
$stmtSource = $db->prepare($sqlSource);
$stmtSource->execute($params);
$sourceRows = $stmtSource->fetchAll(PDO::FETCH_KEY_PAIR);
$dtCount = (int)($sourceRows['decision_tree'] ?? 0);
$coveredCount = $total - $summary['na_risk'];
$fallbackCount = $coveredCount - $dtCount; 
$pctDt = $coveredCount > 0 ? round(($dtCount / $coveredCount) * 100, 1) : 0;
$pctFallback = $coveredCount > 0 ? round(($fallbackCount / $coveredCount) * 100, 1) : 0;
$pctCoverage = $total > 0 ? round(($coveredCount / $total) * 100, 1) : 0;

$openReports = (int) $db->query("SELECT COUNT(*) FROM feedback_reports WHERE status IN ('open', 'awaiting_admin')")->fetchColumn();
$pendingBatches = (int) $db->query("SELECT COUNT(*) FROM pending_grade_batches WHERE status = 'pending'")->fetchColumn();
$pendingCorrections = (int) $db->query("SELECT COUNT(*) FROM pending_corrections WHERE status = 'pending'")->fetchColumn();
$supportReviews = (int) $db->query("SELECT COUNT(*) FROM academic_support_cases WHERE status = 'needs_review'")->fetchColumn();
$totalAdminActions = $openReports + $pendingBatches + $pendingCorrections + $supportReviews;

$sqlSections = "
    SELECT sp.section, sp.year_level, COUNT(sp.user_id) AS section_total,
        SUM(CASE WHEN p.risk_level = 'HIGH' THEN 1 ELSE 0 END) AS high_risk,
        SUM(CASE WHEN p.risk_level = 'MODERATE' THEN 1 ELSE 0 END) AS mod_risk,
        SUM(CASE WHEN p.risk_level = 'LOW' THEN 1 ELSE 0 END) AS low_risk,
        SUM(CASE WHEN p.risk_level IS NULL OR p.risk_level = '' THEN 1 ELSE 0 END) AS na_risk,
        SUM(CASE WHEN sp.current_gwa IS NOT NULL THEN 1 ELSE 0 END) AS gwa_count,
        AVG(sp.current_gwa) AS avg_gwa
    FROM student_profiles sp
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2 WHERE p2.student_id = sp.user_id ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    $whereClause
    GROUP BY sp.section, sp.year_level ORDER BY sp.year_level ASC, sp.section ASC
";
$stmtSections = $db->prepare($sqlSections);
$stmtSections->execute($params);
$sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

logExportAudit($db, $user['id'], 'Program Analytics Snapshot', 'CSV', ['year' => $yearFilter, 'section' => $sectionFilter], 1);

$output = startCsvDownload("UDM-RADAR_Program_Snapshot");
writeCsvMetadata($output, $user, 'Program Analytics Snapshot', ['year level' => $yearFilter ?: 'All', 'section' => $sectionFilter ?: 'All'], $total);

fputcsv($output, ['Latest Prediction Run:', sanitize_csv_field($lastRunText)]);
fputcsv($output, []);

fputcsv($output, ['--- PROGRAM POPULATION & RISK SUMMARY ---']);
fputcsv($output, ['Classification', 'Student Count', 'Percentage of Total']);
fputcsv($output, ['High Risk', $summary['high_risk'], $pctHigh . '%']);
fputcsv($output, ['Moderate Risk', $summary['mod_risk'], $pctMod . '%']);
fputcsv($output, ['Low Risk', $summary['low_risk'], $pctLow . '%']);
fputcsv($output, ['No Prediction Data', $summary['na_risk'], $pctNA . '%']);
fputcsv($output, ['TOTAL IN SCOPE', $total, '100%']);
fputcsv($output, []);

fputcsv($output, ['--- KPI METRICS ---']);
fputcsv($output, ['Metric', 'Value']);
fputcsv($output, ['Overall Average GWA', $overallAvgGwa]);
fputcsv($output, ['Irregular Students', $summary['irregular_count']]);
fputcsv($output, ['Total Prediction Coverage', $pctCoverage . '%']);
fputcsv($output, ['Predictions from Decision Tree Model', $pctDt . '%']);
fputcsv($output, ['Predictions from Fallback Estimate', $pctFallback . '%']);
fputcsv($output, []);

fputcsv($output, ['--- ADMINISTRATIVE WORKLOAD (INSTITUTION-WIDE) ---']);
fputcsv($output, ['Note:', 'These metrics reflect the global administrative queue and are not bound by the year/section filters above.']);
fputcsv($output, ['Open Feedback Reports', $openReports]);
fputcsv($output, ['Pending Grade Batches', $pendingBatches]);
fputcsv($output, ['Pending Student Profile Corrections', $pendingCorrections]);
fputcsv($output, ['Academic Support Reviews Awaiting Action', $supportReviews]);
fputcsv($output, ['Total Actions Required', $totalAdminActions]);
fputcsv($output, []);

fputcsv($output, ['--- SECTION & YEAR-LEVEL BREAKDOWN ---']);
fputcsv($output, [
    'Year Level', 'Section', 'Total Students', 'High Risk Count', 'Moderate Risk Count', 
    'Low Risk Count', 'N/A Risk Count', 'At-Risk Rate', 'Average Section GWA', 'GWA Data Coverage'
]);

foreach ($sections as $sec) {
    $secAtRisk = $sec['high_risk'] + $sec['mod_risk'];
    $secRate = $sec['section_total'] > 0 ? round(($secAtRisk / $sec['section_total']) * 100, 1) . '%' : '0%';
    $coverageStr = '(' . $sec['gwa_count'] . '/' . $sec['section_total'] . ')';

    fputcsv($output, [
        sanitize_csv_field($sec['year_level'] ?? 'N/A'),
        sanitize_csv_field($sec['section'] ?? 'Unassigned'),
        $sec['section_total'],
        $sec['high_risk'],
        $sec['mod_risk'],
        $sec['low_risk'],
        $sec['na_risk'],
        $secRate,
        sanitize_csv_field($sec['avg_gwa'] !== null ? number_format((float)$sec['avg_gwa'], 2) : 'N/A'),
        $coverageStr
    ]);
}

fclose($output);
exit;