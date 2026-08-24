<?php
// admin/export_subject_performance_pdf.php
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

$dbSys = $db->query("SELECT DISTINCT school_year FROM grades WHERE school_year IS NOT NULL ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$syFilter = $_GET['sy'] ?? ($dbSys[0] ?? '2026-2027');
$semFilter = $_GET['semester'] ?? '1';
$yearFilter = $_GET['year_level'] ?? '';
$subjectFilter = $_GET['subject_id'] ?? '';
$sectionFilter = $_GET['section'] ?? '';

$allowedPeriods = ['prelim' => 'Preliminary', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Final'];
$periodFilter = $_GET['period'] ?? 'prelim';
if (!array_key_exists($periodFilter, $allowedPeriods)) $periodFilter = 'prelim';
$periodName = $allowedPeriods[$periodFilter];

// FIXED: Reduced to 12 to guarantee a clean 1-page layout without orphaned rows
$TOP_N = 12;
$COVERAGE_THRESHOLD = 0.8;

if (!($syFilter === '2026-2027' && $semFilter === '1')) {
    exit("Error: Subject Performance Export is currently scoped to the active term.");
}

$sqlSubjects = "
    SELECT s.id, s.code, s.title, sp.section, sp.year_level, g.{$periodFilter} AS term_score
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE g.school_year = ? AND g.semester = ? AND sp.status != 'Archived'
";
$paramsSubjects = [$syFilter, $semFilter];

if ($yearFilter !== '') { $sqlSubjects .= " AND sp.year_level = ?"; $paramsSubjects[] = $yearFilter; }
if ($sectionFilter !== '') { $sqlSubjects .= " AND sp.section = ?"; $paramsSubjects[] = $sectionFilter; }
if ($subjectFilter !== '') { $sqlSubjects .= " AND s.id = ?"; $paramsSubjects[] = $subjectFilter; }

$stmtSubjects = $db->prepare($sqlSubjects);
$stmtSubjects->execute($paramsSubjects);
$rawGrades = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

$subjectSectionStats = [];
foreach ($rawGrades as $r) {
    $id = $r['id'];
    $sec = $r['section'] ?? 'Unassigned';
    $secKey = $id . '_' . $sec;

    if (!isset($subjectSectionStats[$secKey])) {
        $subjectSectionStats[$secKey] = [
            'id' => $id, 'code' => $r['code'], 'title' => $r['title'], 'section' => $sec,
            'enrolled' => 0, 'graded' => 0, 'raw_sum' => 0, 'passed' => 0, 'high_risk' => 0, 'mod_risk' => 0
        ];
    }
    $subjectSectionStats[$secKey]['enrolled']++;

    if ($r['term_score'] !== null && trim((string)$r['term_score']) !== '') {
        $val = (float)$r['term_score'];
        $subjectSectionStats[$secKey]['graded']++;
        $subjectSectionStats[$secKey]['raw_sum'] += $val;

        $pt = normalizeTermGrade($r['term_score']);
        if ($pt !== null) {
            $risk = computeRiskFromAvg($pt);
            if ($risk === 'HIGH') $subjectSectionStats[$secKey]['high_risk']++;
            elseif ($risk === 'MODERATE') $subjectSectionStats[$secKey]['mod_risk']++;
        }
    }
}

$totalCombinations = count($subjectSectionStats);

usort($subjectSectionStats, function($a, $b) {
    $rateA = $a['graded'] > 0 ? (($a['high_risk'] + $a['mod_risk']) / $a['graded']) : 0;
    $rateB = $b['graded'] > 0 ? (($b['high_risk'] + $b['mod_risk']) / $b['graded']) : 0;
    if ($rateA === $rateB) return $b['graded'] <=> $a['graded'];
    return $rateB <=> $rateA;
});

$topRows = array_slice($subjectSectionStats, 0, $TOP_N, true);
$limitedDataCount = 0;
foreach ($subjectSectionStats as $s) {
    if ($s['enrolled'] > 0 && $s['graded'] > 0 && ($s['graded'] / $s['enrolled']) < $COVERAGE_THRESHOLD) {
        $limitedDataCount++;
    }
}

// Calculate how many limited-data rows actually made it into the Top N
$limitedInTopN = 0;
foreach ($topRows as $s) {
    if ($s['enrolled'] > 0 && $s['graded'] > 0 && ($s['graded'] / $s['enrolled']) < $COVERAGE_THRESHOLD) {
        $limitedInTopN++;
    }
}

$logoPath = '../assets/img/logo_sidebar.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
$logoHtml = $logoBase64 ? '<img src="' . $logoBase64 . '" style="width: 70px; height: auto;" alt="Logo">' : '';

$scopeParts = [];
if ($yearFilter !== '') $scopeParts[] = "Year $yearFilter";
if ($sectionFilter !== '') $scopeParts[] = "Section $sectionFilter";
if ($subjectFilter !== '') {
    foreach ($subjectSectionStats as $s) { if ($s['id'] == $subjectFilter) { $scopeParts[] = $s['code']; break; } }
}
$scopeText = $scopeParts ? implode(', ', $scopeParts) : 'the full program';

$topName = '';
$topRate = 0;
if (!empty($topRows)) {
    $first = reset($topRows);
    $topName = $first['code'] . ' (' . $first['section'] . ')';
    $topRate = $first['graded'] > 0 ? round((($first['high_risk'] + $first['mod_risk']) / $first['graded']) * 100, 1) : 0;
}

// FIXED: Adaptive Limited-Data Narrative
$narrative = "Of the <strong>$totalCombinations</strong> subject-section combinations analyzed across $scopeText for the $periodName grading period, "
    . "the top <strong>" . count($topRows) . "</strong> by attention rate are ranked below. "
    . ($topName ? "<strong>$topName</strong> has the highest concentration of records requiring attention ($topRate%). " : "");

if ($limitedDataCount > 0) {
    $narrative .= "<strong>$limitedDataCount</strong> combination(s) in the full dataset are running on limited data coverage (below " . ($COVERAGE_THRESHOLD * 100) . "%). ";
    if ($limitedInTopN === 0) {
        $narrative .= "None appear in the Top " . count($topRows) . " shown below.";
    } else {
        $narrative .= "<strong>$limitedInTopN</strong> of these appear in the Top " . count($topRows) . " shown below and should be interpreted cautiously.";
    }
}

logExportAudit($db, $user['id'], 'Subject Performance Bottleneck', 'PDF', [
    'period' => $periodFilter, 'year_level' => $yearFilter, 'section' => $sectionFilter, 'subject_id' => $subjectFilter,
], count($topRows));

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subject Performance and Potential Bottleneck Report</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        table { width: 100%; border-collapse: collapse; }
        .header-table { border-bottom: 2px solid #1e4dd8; padding-bottom: 10px; margin-bottom: 15px; }
        .meta-table { margin-bottom: 15px; background-color: #f8fafc; font-size: 10px; }
        .meta-table td { padding: 6px 10px; border: 1px solid #e2e8f0; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 8px; margin-top: 15px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .narrative-box { background-color: #f8fafc; border-left: 3px solid #1e4dd8; padding: 10px 14px; margin-bottom: 15px; font-size: 12px; line-height: 1.5; color: #334155; }

        .rank-table { margin-bottom: 15px; font-size: 10px; }
        .rank-table th { background-color: #f1f5f9; text-align: left; padding: 6px 8px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #334155; }
        .rank-table td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .rank-num { font-weight: bold; color: #64748b; width: 18px; text-align: center; }

        .text-high { color: #dc2626; font-weight: bold; }
        .text-mod { color: #d97706; font-weight: bold; }
        .text-na { color: #94a3b8; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 80px; vertical-align: middle;">' . $logoHtml . '</td>
            <td style="vertical-align: middle;">
                <h2 style="margin: 0; color: #111827; font-size: 18px; text-transform: uppercase;">Universidad de Manila</h2>
                <p style="margin: 2px 0 0 0; color: #475569; font-size: 12px; font-weight: bold;">UDM-RADAR: Academic Decision Support System</p>
                <p style="margin: 4px 0 0 0; color: #1e4dd8; font-size: 15px; font-weight: bold; text-transform: uppercase;">Subject Performance and Potential Bottleneck Report</p>
            </td>
            <td style="text-align: right; vertical-align: bottom; width: 150px;">
                <p style="margin: 0; color: #64748b; font-size: 10px;">Date Generated:<br><strong style="color: #111827; font-size: 12px;">' . date('F j, Y') . '</strong></p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 20%;"><strong>Generated By:</strong></td>
            <td style="width: 30%;">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' (Admin)</td>
            <td style="width: 20%;"><strong>Grading Period:</strong></td>
            <td style="width: 30%;">' . htmlspecialchars($periodName) . ' (' . htmlspecialchars($syFilter) . ', Sem ' . htmlspecialchars($semFilter) . ')</td>
        </tr>
        <tr>
            <td><strong>Active Filters:</strong></td>
            <td colspan="3">Year: ' . ($yearFilter ?: 'All') . ' | Section: ' . ($sectionFilter ?: 'All') . ' | Subject: ' . ($subjectFilter ? htmlspecialchars($scopeText) : 'All') . '</td>
        </tr>
    </table>

    <div class="narrative-box">' . $narrative . '</div>

    <div class="section-title">Top ' . count($topRows) . ' Combinations by Attention Rate</div>
    <table class="rank-table">
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th>Subject</th>
                <th>Section</th>
                <th style="text-align: center;">Coverage</th>
                <th style="text-align: center;">Mean Score</th>
                <th style="width: 30%;">Attention Rate & Risk Breakdown</th>
            </tr>
        </thead>
        <tbody>';

$rank = 1;
foreach ($topRows as $s) {
    $meanGrade = $s['graded'] > 0 ? round($s['raw_sum'] / $s['graded'], 1) . '%' : 'N/A';
    $coveragePct = $s['enrolled'] > 0 ? round(($s['graded'] / $s['enrolled']) * 100, 1) : 0;
    $isLimited = $s['graded'] > 0 && $coveragePct < ($COVERAGE_THRESHOLD * 100);
    
    $attentionCount = $s['high_risk'] + $s['mod_risk'];
    $attentionRate = $s['graded'] > 0 ? round(($attentionCount / $s['graded']) * 100, 1) : 0;
    
    // FIXED: True Segmented Bar logic for Dompdf
    $highRate = $s['graded'] > 0 ? ($s['high_risk'] / $s['graded']) * 100 : 0;
    $modRate  = $s['graded'] > 0 ? ($s['mod_risk'] / $s['graded']) * 100 : 0;
    $remRate  = 100 - $highRate - $modRate;
    
    $barHtml = '<table style="width: 100%; height: 10px; border-collapse: collapse; margin-bottom: 3px;"><tr>';
    if ($highRate > 0) $barHtml .= '<td style="width: '.$highRate.'%; background-color: #dc2626; padding: 0;"></td>';
    if ($modRate > 0) $barHtml .= '<td style="width: '.$modRate.'%; background-color: #f59e0b; padding: 0;"></td>';
    if ($remRate > 0) $barHtml .= '<td style="width: '.$remRate.'%; background-color: #e2e8f0; padding: 0;"></td>';
    $barHtml .= '</tr></table>';
    
    $barHtml .= '<span style="font-size: 8.5px; color: #475569;"><strong>' . $attentionRate . '%</strong> (' . $s['high_risk'] . ' High | ' . $s['mod_risk'] . ' Mod)</span>';

    $html .= '<tr>
        <td class="rank-num">' . $rank . '</td>
        <td><strong>' . htmlspecialchars($s['code']) . '</strong><br><span style="color:#64748b; font-size:9px;">' . htmlspecialchars($s['title']) . '</span></td>
        <td>' . htmlspecialchars($s['section']) . '</td>
        <td style="text-align: center;"><span class="' . ($isLimited ? 'text-mod' : '') . '">' . $coveragePct . '%</span>' . ($isLimited ? '<br><span style="font-size:8px; color:#d97706;">Limited Data</span>' : '') . '</td>
        <td style="text-align: center;">' . $meanGrade . '</td>
        <td>' . $barHtml . '</td>
    </tr>';
    $rank++;
}

$html .= '
        </tbody>
    </table>

    <div style="margin-top: 20px; border-top: 1px solid #cbd5e1; padding-top: 10px; text-align: center; color: #64748b; font-size: 9px; position: absolute; bottom: 10px; width: 100%;">
        <strong>Methodology Note:</strong> Elevated attention rates identify performance patterns for further academic review. Attention rate is calculated as (High Risk + Moderate Risk) divided by graded records for the selected grading period, not total enrollment, since ungraded records cannot yet be classified. This report does not independently establish the cause of student outcomes or evaluate Faculty performance.<br>
        <strong>Confidentiality Notice:</strong> This report supports academic review and does not represent an automatic disciplinary decision. Contains sensitive student data.
    </div>

</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "UDM-RADAR_Subject_Bottleneck_" . str_replace(' ', '', $periodName) . "_" . date('Ymd_Hi') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;