<?php
// admin/export_program_snapshot_pdf.php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/csv_export_helpers.php'; 
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requireRole('admin');
$user = currentUser();
$db = getDB();

// 1. Capture Filters & Scope
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

// 2. Fetch Latest Prediction Run Date
$lastRunStmt = $db->query("SELECT MAX(generated_at) as last_run FROM predictions");
$lastRunDate = $lastRunStmt->fetchColumn();
$lastRunText = $lastRunDate ? date('F j, Y, g:i A', strtotime($lastRunDate)) : 'No predictions generated yet';

// 3. Fetch Overall Program Population & Risk Summary
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
$highCount = (int)$summary['high_risk'];
$modCount  = (int)$summary['mod_risk'];
$lowCount  = (int)$summary['low_risk'];
$naCount   = (int)$summary['na_risk'];
$atRiskCount = $highCount + $modCount;
$irregularCount = (int)$summary['irregular_count'];
$overallAvgGwa = $summary['overall_avg_gwa'] !== null ? (float)$summary['overall_avg_gwa'] : null;

$pctHigh = $total > 0 ? round(($highCount / $total) * 100, 1) : 0;
$pctMod  = $total > 0 ? round(($modCount / $total) * 100, 1) : 0;
$pctLow  = $total > 0 ? round(($lowCount / $total) * 100, 1) : 0;
$pctNA   = $total > 0 ? round(($naCount / $total) * 100, 1) : 0;
$pctAtRisk = $total > 0 ? round(($atRiskCount / $total) * 100, 1) : 0;
$coveredCount = $total - $naCount;
$pctCoverage = $total > 0 ? round(($coveredCount / $total) * 100, 1) : 0;

// 3b. Fetch Prediction Source Breakdown — how many current predictions came
// from the trained Decision Tree Regressor vs. the calculation-based
// fallback. This is a genuinely new statement, not just a re-display of
// something already on the dashboard: it tells the reader how much of the
// report is model-driven right now vs. rule-based estimate, which matters
// for evaluating the system as predictive analytics rather than just a
// rules engine with an AI label on it.
$sqlSource = "
    SELECT p.prediction_source, COUNT(*) AS cnt
    FROM student_profiles sp
    JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2
        WHERE p2.student_id = sp.user_id
        ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    $whereClause
    GROUP BY p.prediction_source
";
$stmtSource = $db->prepare($sqlSource);
$stmtSource->execute($params);
$sourceRows = $stmtSource->fetchAll(PDO::FETCH_KEY_PAIR); // prediction_source => cnt

$dtCount = (int)($sourceRows['decision_tree'] ?? 0);
$fallbackCount = $coveredCount - $dtCount; // everything covered that isn't decision_tree
$pctDt = $coveredCount > 0 ? round(($dtCount / $coveredCount) * 100, 1) : 0;
$pctFallback = $coveredCount > 0 ? round(($fallbackCount / $coveredCount) * 100, 1) : 0;


// 4. Fetch Administrative Workload (Parity with index.php, no silent degradation)
$openReports = (int) $db->query("SELECT COUNT(*) FROM feedback_reports WHERE status IN ('open', 'awaiting_admin')")->fetchColumn();
$pendingBatches = (int) $db->query("SELECT COUNT(*) FROM pending_grade_batches WHERE status = 'pending'")->fetchColumn();
$pendingCorrections = (int) $db->query("SELECT COUNT(*) FROM pending_corrections WHERE status = 'pending'")->fetchColumn();
$supportReviews = (int) $db->query("SELECT COUNT(*) FROM academic_support_cases WHERE status = 'needs_review'")->fetchColumn();
$pendingApprovals = $pendingBatches + $pendingCorrections;
$totalAdminActions = $openReports + $pendingApprovals + $supportReviews;

// 5. Fetch Section & Year-Level Breakdown
$sqlSections = "
    SELECT 
        sp.section,
        sp.year_level,
        COUNT(sp.user_id) AS section_total,
        SUM(CASE WHEN p.risk_level = 'HIGH' THEN 1 ELSE 0 END) AS high_risk,
        SUM(CASE WHEN p.risk_level = 'MODERATE' THEN 1 ELSE 0 END) AS mod_risk,
        SUM(CASE WHEN p.risk_level = 'LOW' THEN 1 ELSE 0 END) AS low_risk,
        SUM(CASE WHEN p.risk_level IS NULL OR p.risk_level = '' THEN 1 ELSE 0 END) AS na_risk,
        SUM(CASE WHEN sp.current_gwa IS NOT NULL THEN 1 ELSE 0 END) AS gwa_count,
        AVG(sp.current_gwa) AS avg_gwa
    FROM student_profiles sp
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2 
        WHERE p2.student_id = sp.user_id 
        ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    $whereClause
    GROUP BY sp.section, sp.year_level
    ORDER BY sp.year_level ASC, sp.section ASC
";
$stmtSections = $db->prepare($sqlSections);
$stmtSections->execute($params);
$sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);

// 6. Generate Dynamic Executive Narrative
$worstSection = '';
$worstRate = -1;
foreach ($sections as $s) {
    $rate = $s['section_total'] > 0 ? (($s['high_risk'] + $s['mod_risk']) / $s['section_total']) : 0;
    if ($rate > $worstRate) { 
        $worstRate = $rate; 
        $worstSection = $s['section'] ?? 'Unassigned'; 
    }
}

$narrative = "Of the <strong>{$total}</strong> students in scope, <strong>{$atRiskCount}</strong> are currently classified as High or Moderate Risk, representing <strong>{$pctAtRisk}%</strong> of the population. ";
if ($worstRate > 0) {
    $narrative .= "<strong>{$worstSection}</strong> has the highest concentration of records requiring attention (" . round($worstRate * 100, 1) . "%). ";
}
if ($totalAdminActions > 0) {
    $narrative .= "Institutionally, there are <strong>{$totalAdminActions}</strong> administrative actions awaiting resolution, including {$supportReviews} academic support review(s).";
} else {
    $narrative .= "There are currently no administrative actions awaiting resolution in the system queue.";
}

// 7. Base64 Encode Logo
$logoPath = '../assets/img/logo_sidebar.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
$logoHtml = $logoBase64 ? '<img src="' . $logoBase64 . '" style="width: 70px; height: auto;" alt="Logo">' : '';

// NOTE on the Section Breakdown table's page-break-inside: avoid (below):
// without it, Dompdf lays out table rows one at a time and only discovers a
// row does not fit once it is already past the point where the page could
// still be reflowed — producing an orphaned tail of rows on the next page
// (this happened in practice: 6 rows stayed on page 1, 5 spilled to page 2).
// With the rule applied, the table is treated as one unbreakable block: it
// either fits entirely on the current page, or the whole table moves to the
// next page as a unit. The <thead> still repeats correctly if the table
// itself is ever long enough to need genuine internal pagination.
// (Kept as a PHP comment, not an inline HTML comment inside the $html
// string, after an apostrophe in this same explanation broke the
// single-quoted PHP string it was previously embedded in.)

// 8. Build the HTML Template
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Program Analytics Snapshot</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        table { width: 100%; border-collapse: collapse; }
        .header-table { border-bottom: 2px solid #1e4dd8; padding-bottom: 10px; margin-bottom: 15px; }
        .meta-table { margin-bottom: 15px; background-color: #f8fafc; font-size: 10px; }
        .meta-table td { padding: 6px 10px; border: 1px solid #e2e8f0; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 8px; margin-top: 20px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .narrative-box { background-color: #f8fafc; border-left: 3px solid #1e4dd8; padding: 10px 14px; margin-bottom: 20px; font-size: 12px; line-height: 1.5; color: #334155; }
        
        /* KPI Cards */
        .kpi-table { margin-bottom: 20px; }
        .kpi-td { border: 1px solid #cbd5e1; text-align: center; padding: 12px 6px; width: 20%; }
        .kpi-val { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .kpi-lbl { font-size: 9px; color: #64748b; text-transform: uppercase; font-weight: bold; }
        
        /* Risk Bar */
        .risk-bar { width: 100%; height: 16px; margin-bottom: 6px; }
        .risk-legend td { font-size: 9px; text-align: center; color: #475569; }
        
        /* Data Tables */
        .data-table { margin-bottom: 20px; font-size: 10px; }
        .data-table th { background-color: #f1f5f9; text-align: left; padding: 8px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #334155; }
        .data-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        
        .text-high { color: #dc2626; font-weight: bold; }
        .text-mod { color: #d97706; font-weight: bold; }
        .text-low { color: #059669; font-weight: bold; }
        .text-na { color: #94a3b8; }
    </style>
</head>
<body>

    <!-- BRANDED HEADER -->
    <table class="header-table">
        <tr>
            <td style="width: 80px; vertical-align: middle;">' . $logoHtml . '</td>
            <td style="vertical-align: middle;">
                <h2 style="margin: 0; color: #111827; font-size: 18px; text-transform: uppercase;">Universidad de Manila</h2>
                <p style="margin: 2px 0 0 0; color: #475569; font-size: 12px; font-weight: bold;">UDM-RADAR: Academic Decision Support System</p>
                <p style="margin: 4px 0 0 0; color: #1e4dd8; font-size: 15px; font-weight: bold; text-transform: uppercase;">Program Analytics Snapshot</p>
            </td>
            <td style="text-align: right; vertical-align: bottom; width: 150px;">
                <p style="margin: 0; color: #64748b; font-size: 10px;">Date Generated:<br><strong style="color: #111827; font-size: 12px;">' . date('F j, Y') . '</strong></p>
            </td>
        </tr>
    </table>

    <!-- METADATA -->
    <!-- Restructured so provenance (who/when) and scope (which population)
         are visually separate — they answer different questions, and cramming
         them into one four-cell row forced uneven column widths regardless of
         actual content length. -->
    <table class="meta-table">
        <tr>
            <td style="width: 20%;"><strong>Generated By:</strong></td>
            <td style="width: 30%;">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' (Admin)</td>
            <td style="width: 20%;"><strong>Latest Model Run:</strong></td>
            <td style="width: 30%;">' . htmlspecialchars($lastRunText) . '</td>
        </tr>
        <tr>
            <td><strong>Active Filters:</strong></td>
            <td colspan="3">Year: ' . ($yearFilter ?: 'All') . ' | Section: ' . ($sectionFilter ?: 'All') . '</td>
        </tr>
    </table>

    <!-- EXECUTIVE NARRATIVE -->
    <div class="narrative-box">' . $narrative . '</div>

    <!-- KPI CARDS -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-td"><div class="kpi-val" style="color: #0f172a;">' . $total . '</div><div class="kpi-lbl">Students in Scope</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #dc2626;">' . $highCount . '</div><div class="kpi-lbl">High Risk</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #d97706;">' . $modCount . '</div><div class="kpi-lbl">Moderate Risk</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #059669;">' . $lowCount . '</div><div class="kpi-lbl">Low Risk</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #94a3b8;">' . $naCount . '</div><div class="kpi-lbl">No Prediction</div></td>
        </tr>
    </table>

    <!-- SECOND KPI ROW: population-health metrics already shown on the live
         dashboard (Overall Avg GWA, Irregular count) but previously missing
         from this report, plus prediction coverage — same visual treatment,
         separated into its own row since these describe the population
         generally rather than the risk classification specifically. -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-td"><div class="kpi-val" style="color: #0f172a;">' . ($overallAvgGwa !== null ? number_format($overallAvgGwa, 2) : 'N/A') . '</div><div class="kpi-lbl">Overall Avg GWA</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #b45309;">' . $irregularCount . '</div><div class="kpi-lbl">Irregular Students</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #0f172a;">' . $pctCoverage . '%</div><div class="kpi-lbl">Prediction Coverage</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #1e4dd8;">' . $pctDt . '%</div><div class="kpi-lbl">From Decision Tree</div></td>
            <td class="kpi-td"><div class="kpi-val" style="color: #64748b;">' . $pctFallback . '%</div><div class="kpi-lbl">From Fallback Estimate</div></td>
        </tr>
    </table>

    <!-- RISK DISTRIBUTION CHART (CSS-Based) -->
    <div style="margin-bottom: 24px;">
        <div style="font-size: 10px; font-weight: bold; color: #64748b; margin-bottom: 4px; text-transform: uppercase;">Population Risk Distribution</div>
        <table class="risk-bar">
            <tr>';
            if ($pctHigh > 0) $html .= '<td style="width: ' . $pctHigh . '%; background-color: #dc2626;"></td>';
            if ($pctMod > 0)  $html .= '<td style="width: ' . $pctMod . '%; background-color: #f59e0b;"></td>';
            if ($pctLow > 0)  $html .= '<td style="width: ' . $pctLow . '%; background-color: #10b981;"></td>';
            if ($pctNA > 0)   $html .= '<td style="width: ' . $pctNA . '%; background-color: #cbd5e1;"></td>';
$html .= '  </tr>
        </table>
        <table class="risk-legend">
            <tr>
                <td style="width: 25%;"><span style="color:#dc2626;">■</span> High (' . $pctHigh . '%)</td>
                <td style="width: 25%;"><span style="color:#f59e0b;">■</span> Mod (' . $pctMod . '%)</td>
                <td style="width: 25%;"><span style="color:#10b981;">■</span> Low (' . $pctLow . '%)</td>
                <td style="width: 25%;"><span style="color:#94a3b8;">■</span> N/A (' . $pctNA . '%)</td>
            </tr>
        </table>
    </div>

    <!-- ADMINISTRATIVE WORKLOAD -->
    <div class="section-title">Administrative Workload Summary</div>
    <p style="font-size: 9px; color: #64748b; margin-top: -4px; margin-bottom: 10px;">Note: These metrics reflect the global administrative queue and are not bound by the population filters above.</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>Queue / Action Type</th>
                <th style="text-align: center;">Pending Count</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Open Feedback Reports</td>
                <td style="text-align: center; font-weight: bold;">' . $openReports . '</td>
            </tr>
            <tr>
                <td>Pending Grade Batches</td>
                <td style="text-align: center; font-weight: bold;">' . $pendingBatches . '</td>
            </tr>
            <tr>
                <td>Pending Student Profile Corrections</td>
                <td style="text-align: center; font-weight: bold;">' . $pendingCorrections . '</td>
            </tr>
            <tr>
                <td>Academic Support Reviews Awaiting Action</td>
                <td style="text-align: center; font-weight: bold;">' . $supportReviews . '</td>
            </tr>
            <tr style="background-color: #f8fafc;">
                <td style="font-weight: bold;">Total Administrative Actions Required</td>
                <td style="text-align: center; font-weight: bold; color: #1e4dd8;">' . $totalAdminActions . '</td>
            </tr>
        </tbody>
    </table>

    <!-- SECTION BREAKDOWN: table below uses page-break-inside: avoid so it
         moves to the next page as a whole unit instead of splitting rows
         across a page boundary. See PHP comment above the $html assignment
         for the full explanation (kept out of this string to avoid
         apostrophes inside a single-quoted PHP string, which previously
         caused a syntax error here). -->

<!-- SECTION BREAKDOWN -->
    <!-- Wrapped both the title and the table in the avoid-break div -->
    <div style="page-break-inside: avoid;">
        <div class="section-title">Section and Year-Level Breakdown</div>
        <table class="data-table">
            <!-- THEAD ensures column headers repeat on page breaks -->
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Section</th>
                    <th style="text-align: center;">Total</th>
                    <th style="text-align: center;">High</th>
                    <th style="text-align: center;">Mod</th>
                    <th style="text-align: center;">Low</th>
                    <th style="text-align: center;">N/A</th>
                    <th style="text-align: center;">At-Risk Rate</th>
                    <th style="text-align: center;">Avg GWA (Coverage)</th>
                </tr>
            </thead>
            <tbody>';
            
    foreach ($sections as $sec) {
        $secAtRisk = $sec['high_risk'] + $sec['mod_risk'];
        $secRate = $sec['section_total'] > 0 ? round(($secAtRisk / $sec['section_total']) * 100, 1) . '%' : '0%';
        $avgGwaStr = $sec['avg_gwa'] !== null ? number_format((float)$sec['avg_gwa'], 2) : 'N/A';
        
        // GWA Coverage (e.g. 2.83 (36/40))
        $coverage = '<span style="color:#64748b; font-size:8px;">(' . $sec['gwa_count'] . '/' . $sec['section_total'] . ')</span>';

        $html .= '<tr>
            <td>' . htmlspecialchars($sec['year_level'] ?? 'N/A') . '</td>
            <td><strong>' . htmlspecialchars($sec['section'] ?? 'Unassigned') . '</strong></td>
            <td style="text-align: center;">' . $sec['section_total'] . '</td>
            <td style="text-align: center;"><span class="' . ($sec['high_risk'] > 0 ? 'text-high' : 'text-na') . '">' . $sec['high_risk'] . '</span></td>
            <td style="text-align: center;"><span class="' . ($sec['mod_risk'] > 0 ? 'text-mod' : 'text-na') . '">' . $sec['mod_risk'] . '</span></td>
            <td style="text-align: center;"><span class="' . ($sec['low_risk'] > 0 ? 'text-low' : 'text-na') . '">' . $sec['low_risk'] . '</span></td>
            <td style="text-align: center;"><span class="text-na">' . $sec['na_risk'] . '</span></td>
            <td style="text-align: center; font-weight: ' . ($secAtRisk > 0 ? 'bold' : 'normal') . ';">' . $secRate . '</td>
            <td style="text-align: center;">' . $avgGwaStr . ' ' . $coverage . '</td>
        </tr>';
    }

    $html .= '
            </tbody>
        </table>
    </div> <!-- Closes the page-break-inside wrapper -->

    <div style="margin-top: 40px; border-top: 1px solid #cbd5e1; padding-top: 10px; text-align: center; color: #64748b; font-size: 9px;">
        <strong>Methodology Note:</strong> Risk classifications are derived from the latest available academic prediction and the system\'s defined risk thresholds. The prediction model estimates overall GWA, while risk classifications are assigned from the resulting projected academic standing. Averages exclude records missing official final grades.<br>
        <strong>Confidentiality Notice:</strong> This report supports academic review and does not represent an automatic disciplinary decision. Contains sensitive student data.
    </div>

</body>
</html>';

// 9. Initialize and Render Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

// Generate the PDF in memory
$dompdf->render();

// Automatically inject page numbers at the bottom right
$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->get_font("sans-serif", "normal");
$canvas->page_text(520, 810, "Page {PAGE_NUM} of {PAGE_COUNT}", $font, 9, array(0.4, 0.4, 0.4));

// 10. Safely Record Export Audit Log (Only executes if rendering succeeds)
logExportAudit($db, $user['id'], 'Program Analytics Snapshot', 'PDF', [
    'year' => $yearFilter,
    'section' => $sectionFilter
], 1);

// Output the generated PDF
$filename = "UDM-RADAR_Program_Snapshot_" . date('Ymd_Hi') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;