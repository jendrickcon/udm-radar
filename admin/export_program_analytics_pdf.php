<?php
// admin/export_program_analytics_pdf.php
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
if (empty($dbSys)) $dbSys = ['2026-2027'];
$syFilter = $_GET['sy'] ?? $dbSys[0];
if (!in_array($syFilter, $dbSys, true)) $syFilter = $dbSys[0];

$allowedSemesters = ['1', '2'];
$semFilter = $_GET['semester'] ?? '1';
if (!in_array($semFilter, $allowedSemesters, true)) $semFilter = '1';

$dbYears = $db->query("SELECT DISTINCT year_level FROM student_profiles WHERE year_level IS NOT NULL ORDER BY year_level ASC")->fetchAll(PDO::FETCH_COLUMN);
$yearFilter = $_GET['year_level'] ?? '';
if ($yearFilter !== '' && !in_array($yearFilter, $dbYears, true)) $yearFilter = '';

$allowedPeriods = ['prelim' => 'Preliminary', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Final'];
$periodFilter = $_GET['period'] ?? 'prelim';
if (!array_key_exists($periodFilter, $allowedPeriods)) $periodFilter = 'prelim';
$periodName = $allowedPeriods[$periodFilter];

$subjectFilter = $_GET['subject_id'] ?? '';
$sectionFilter = $_GET['section'] ?? '';

$isCurrentTerm = ($syFilter === '2026-2027' && $semFilter === '1');

$distinctions = [
    'Summa-level threshold' => 0, 'Magna-level threshold' => 0,
    'Cum Laude-level threshold' => 0, 'Not currently within a distinction threshold' => 0,
    'Insufficient Data' => 0
];
$subjectWideStats = [];
$histSubjects = [];
$totalHistStudents = 0; $meanHistGrade = null; $histGradesSum = 0; $histGradesCount = 0;
$histPassedCount = 0; $overallHistPassRate = 0.0;
$totalStudentsScope = 0;

if ($isCurrentTerm) {
    $sqlStudents = "
        SELECT sp.user_id, p.predicted_gwa, p.risk_level, p.latin_honor
        FROM student_profiles sp
        LEFT JOIN predictions p ON p.id = (
            SELECT id FROM predictions p2
            WHERE p2.student_id = sp.user_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
        WHERE sp.section IS NOT NULL AND sp.status != 'Archived'
    ";
    $paramsStudents = [];
    if ($yearFilter !== '') { $sqlStudents .= " AND sp.year_level = ?"; $paramsStudents[] = $yearFilter; }
    if ($sectionFilter !== '') { $sqlStudents .= " AND sp.section = ?"; $paramsStudents[] = $sectionFilter; }

    $stmtStudents = $db->prepare($sqlStudents);
    $stmtStudents->execute($paramsStudents);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
    $totalStudentsScope = count($students);

    foreach ($students as $s) {
        if ($s['risk_level'] === null || $s['predicted_gwa'] === null) {
            $distinctions['Insufficient Data']++;
        } else {
            $honorRaw = $s['latin_honor'] ?? 'Not Eligible';
            if ($honorRaw === 'Summa Cum Laude') $distinctions['Summa-level threshold']++;
            elseif ($honorRaw === 'Magna Cum Laude') $distinctions['Magna-level threshold']++;
            elseif ($honorRaw === 'Cum Laude') $distinctions['Cum Laude-level threshold']++;
            else $distinctions['Not currently within a distinction threshold']++;
        }
    }

    $sqlSubjects = "
        SELECT s.id, s.code, s.title, sp.section, g.{$periodFilter} AS term_score
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

    foreach ($rawGrades as $r) {
        $id = $r['id'];
        if (!isset($subjectWideStats[$id])) {
            $subjectWideStats[$id] = ['code' => $r['code'], 'title' => $r['title'], 'enrolled' => 0, 'graded' => 0, 'raw_sum' => 0, 'passed' => 0, 'high_risk' => 0, 'mod_risk' => 0];
        }
        $subjectWideStats[$id]['enrolled']++;

        if ($r['term_score'] !== null && trim((string)$r['term_score']) !== '') {
            $val = (float)$r['term_score'];
            $subjectWideStats[$id]['graded']++;
            $subjectWideStats[$id]['raw_sum'] += $val;

            $pt = normalizeTermGrade($r['term_score']);
            if ($pt !== null) {
                $risk = computeRiskFromAvg($pt);
                if ($risk === 'HIGH') $subjectWideStats[$id]['high_risk']++;
                elseif ($risk === 'MODERATE') $subjectWideStats[$id]['mod_risk']++;
                if ($pt > 0) $subjectWideStats[$id]['passed']++;
            }
        }
    }
    usort($subjectWideStats, fn($a, $b) => $b['high_risk'] <=> $a['high_risk']);

} else {
    $sqlHistorical = "
        SELECT s.id, s.code, s.title, g.final_grade, g.student_id
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.school_year = ? AND g.semester = ? AND sp.status != 'Archived'
    ";
    $paramsHistorical = [$syFilter, $semFilter];
    if ($yearFilter !== '') { $sqlHistorical .= " AND sp.year_level = ?"; $paramsHistorical[] = $yearFilter; }
    if ($subjectFilter !== '') { $sqlHistorical .= " AND s.id = ?"; $paramsHistorical[] = $subjectFilter; }

    $stmtHistorical = $db->prepare($sqlHistorical);
    $stmtHistorical->execute($paramsHistorical);
    $rawHistGrades = $stmtHistorical->fetchAll(PDO::FETCH_ASSOC);

    $histStudents = [];
    foreach ($rawHistGrades as $r) {
        $histStudents[$r['student_id']] = true;
        $id = $r['id'];
        if (!isset($histSubjects[$id])) {
            $histSubjects[$id] = ['code' => $r['code'], 'title' => $r['title'], 'enrolled' => 0, 'graded' => 0, 'raw_sum' => 0, 'passed' => 0, 'failed' => 0];
        }
        $histSubjects[$id]['enrolled']++;

        if ($r['final_grade'] !== null && trim($r['final_grade']) !== '') {
            $histSubjects[$id]['graded']++;
            $val = trim(strtoupper($r['final_grade']));

            if (in_array($val, ['0', '0.00', 'INC', 'DO', 'DU', 'FA', 'UD'])) {
                $histSubjects[$id]['failed']++;
            } elseif (is_numeric($val)) {
                $pt = (float)$val;
                $histSubjects[$id]['raw_sum'] += $pt;
                $histGradesSum += $pt;
                $histGradesCount++;
                if ($pt > 0) {
                    $histSubjects[$id]['passed']++;
                    $histPassedCount++;
                } else {
                    $histSubjects[$id]['failed']++;
                }
            }
        }
    }
    usort($histSubjects, fn($a, $b) => $b['failed'] <=> $a['failed']);

    $totalHistStudents = count($histStudents);
    $meanHistGrade = $histGradesCount > 0 ? $histGradesSum / $histGradesCount : null;
    $overallHistPassRate = $histGradesCount > 0 ? ($histPassedCount / $histGradesCount) * 100 : 0.0;
}

$logoPath = '../assets/img/logo_sidebar.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';
$logoHtml = $logoBase64 ? '<img src="' . $logoBase64 . '" style="width: 70px; height: auto;" alt="Logo">' : '';

$scopeParts = [];
if ($yearFilter !== '') $scopeParts[] = "Year $yearFilter";
if ($isCurrentTerm && $sectionFilter !== '') $scopeParts[] = "Section $sectionFilter";
if ($subjectFilter !== '') {
    $subjLookup = $isCurrentTerm ? $subjectWideStats : $histSubjects;
    foreach ($subjLookup as $sid => $sub) { if ($sid == $subjectFilter) { $scopeParts[] = $sub['code']; break; } }
}
$scopeText = $scopeParts ? implode(', ', $scopeParts) : 'the full program';

logExportAudit($db, $user['id'], 'Program Analytics Report', 'PDF', [
    'sy' => $syFilter, 'semester' => $semFilter, 'period' => $periodFilter,
    'year_level' => $yearFilter, 'section' => $sectionFilter, 'subject_id' => $subjectFilter,
], $isCurrentTerm ? count($subjectWideStats) : count($histSubjects));

if ($isCurrentTerm) {
    $distinctionEligible = $distinctions['Summa-level threshold'] + $distinctions['Magna-level threshold'] + $distinctions['Cum Laude-level threshold'];
    $narrative = "Of the <strong>$totalStudentsScope</strong> students in scope ($scopeText), <strong>$distinctionEligible</strong> are currently projected within a Latin honors distinction threshold based on their latest available prediction. "
        . "The curriculum-wide overview below reflects <strong>" . count($subjectWideStats) . "</strong> subjects for the <strong>$periodName</strong> grading period ($syFilter, Sem $semFilter).";
} else {
    $narrative = "This is a historical outcome report for <strong>$syFilter, Semester $semFilter</strong>, scoped to $scopeText. "
        . "<strong>$totalHistStudents</strong> students have at least one completed subject outcome on record, with an overall pass rate of <strong>" . number_format($overallHistPassRate, 1) . "%</strong> across <strong>$histGradesCount</strong> graded Final Grade records.";
}

// FIXED: Font sizes scaled slightly to safely constrain tables to a single page
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Program Analytics Report</title>
    <style>
        body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
        table { width: 100%; border-collapse: collapse; }
        .header-table { border-bottom: 2px solid #1e4dd8; padding-bottom: 8px; margin-bottom: 12px; }
        .meta-table { margin-bottom: 12px; background-color: #f8fafc; font-size: 9px; }
        .meta-table td { padding: 4px 8px; border: 1px solid #e2e8f0; }
        .section-title { font-size: 12px; font-weight: bold; color: #0f172a; margin-bottom: 6px; margin-top: 15px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .narrative-box { background-color: #f8fafc; border-left: 3px solid #1e4dd8; padding: 8px 12px; margin-bottom: 15px; font-size: 11px; line-height: 1.4; color: #334155; }

        .honor-bar { width: 100%; height: 14px; margin-bottom: 6px; border-radius: 2px; }
        .honor-legend td { font-size: 8.5px; text-align: left; color: #475569; vertical-align: middle; padding-bottom: 4px; }
        .swatch { display: inline-block; width: 8px; height: 8px; margin-right: 4px; border-radius: 2px; vertical-align: middle; }

        .data-table { margin-bottom: 15px; font-size: 9px; }
        .data-table th { background-color: #f1f5f9; text-align: left; padding: 6px; border-bottom: 2px solid #cbd5e1; font-weight: bold; color: #334155; }
        .data-table td { padding: 6px; border-bottom: 1px solid #e2e8f0; }

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
                <h2 style="margin: 0; color: #111827; font-size: 16px; text-transform: uppercase;">Universidad de Manila</h2>
                <p style="margin: 2px 0 0 0; color: #475569; font-size: 11px; font-weight: bold;">UDM-RADAR: Academic Decision Support System</p>
                <p style="margin: 4px 0 0 0; color: #1e4dd8; font-size: 14px; font-weight: bold; text-transform: uppercase;">Program Analytics Report</p>
            </td>
            <td style="text-align: right; vertical-align: bottom; width: 150px;">
                <p style="margin: 0; color: #64748b; font-size: 9px;">Date Generated:<br><strong style="color: #111827; font-size: 11px;">' . date('F j, Y') . '</strong></p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 20%;"><strong>Generated By:</strong></td>
            <td style="width: 30%;">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ' (Admin)</td>
            <td style="width: 20%;"><strong>Academic Term:</strong></td>
            <td style="width: 30%;">' . htmlspecialchars($syFilter) . ', Semester ' . htmlspecialchars($semFilter) . ' (' . ($isCurrentTerm ? 'Current Term' : 'Historical') . ')</td>
        </tr>
        <tr>
            <td><strong>Active Filters:</strong></td>
            <td colspan="3">Year: ' . ($yearFilter ?: 'All') . ($isCurrentTerm ? ' | Section: ' . ($sectionFilter ?: 'All') : '') . ' | Subject: ' . ($subjectFilter ? htmlspecialchars($scopeText) : 'All') . ($isCurrentTerm ? ' | Grading Period: ' . htmlspecialchars($periodName) : '') . '</td>
        </tr>
    </table>

    <div class="narrative-box">' . $narrative . '</div>';

if ($isCurrentTerm) {
    $totalForPct = $totalStudentsScope > 0 ? $totalStudentsScope : 1;
    $pctSumma = round(($distinctions['Summa-level threshold'] / $totalForPct) * 100, 1);
    $pctMagna = round(($distinctions['Magna-level threshold'] / $totalForPct) * 100, 1);
    $pctCum = round(($distinctions['Cum Laude-level threshold'] / $totalForPct) * 100, 1);
    $pctNone = round(($distinctions['Not currently within a distinction threshold'] / $totalForPct) * 100, 1);
    $pctInsuff = round(($distinctions['Insufficient Data'] / $totalForPct) * 100, 1);

    $html .= '
    <div class="section-title">Academic Distinction Readiness</div>
    <p style="font-size: 8.5px; color: #64748b; margin-top: -4px; margin-bottom: 8px;">Based on each student\'s latest available prediction. Does not represent official Latin honors eligibility, which is determined at graduation.</p>
    <table class="honor-bar"><tr>';
    if ($pctSumma > 0)  $html .= '<td style="width: ' . $pctSumma . '%; background-color: #b45309;"></td>';
    if ($pctMagna > 0)  $html .= '<td style="width: ' . $pctMagna . '%; background-color: #1d4ed8;"></td>';
    if ($pctCum > 0)    $html .= '<td style="width: ' . $pctCum . '%; background-color: #1e4dd8;"></td>';
    if ($pctNone > 0)   $html .= '<td style="width: ' . $pctNone . '%; background-color: #cbd5e1;"></td>';
    if ($pctInsuff > 0) $html .= '<td style="width: ' . $pctInsuff . '%; background-color: #94a3b8;"></td>';
    $html .= '</tr></table>
    
    <!-- FIXED: Added explicit student counts to the percentage legend -->
    <table class="honor-legend"><tr>
        <td><div class="swatch" style="background-color:#b45309;"></div>Summa (' . $distinctions['Summa-level threshold'] . ' students, ' . $pctSumma . '%)</td>
        <td><div class="swatch" style="background-color:#1d4ed8;"></div>Magna (' . $distinctions['Magna-level threshold'] . ' students, ' . $pctMagna . '%)</td>
        <td><div class="swatch" style="background-color:#1e4dd8;"></div>Cum Laude (' . $distinctions['Cum Laude-level threshold'] . ' students, ' . $pctCum . '%)</td>
        <td><div class="swatch" style="background-color:#cbd5e1;"></div>Not Currently (' . $distinctions['Not currently within a distinction threshold'] . ' students, ' . $pctNone . '%)</td>
    </tr>
    <tr>
        <td colspan="4" style="text-align:left;"><div class="swatch" style="background-color:#94a3b8;"></div>Insufficient Data (' . $distinctions['Insufficient Data'] . ' students, ' . $pctInsuff . '%)</td>
    </tr>
    </table>

    <div class="section-title">Curriculum-Wide Overview (' . htmlspecialchars($periodName) . ')</div>
    <table class="data-table">
        <!-- FIXED: Shortened table headers -->
        <thead><tr>
            <th>Subject</th>
            <th style="text-align: center;">Graded</th>
            <th style="text-align: center;">Mean Score</th>
            <th style="text-align: center;">Pass Rate</th>
            <th style="text-align: center;">Requiring Attention</th>
        </tr></thead>
        <tbody>';

    if (empty($subjectWideStats)) {
        $html .= '<tr><td colspan="5" style="text-align:center; color:#94a3b8;">No subjects found matching the current filter scope.</td></tr>';
    }
    foreach ($subjectWideStats as $s) {
        $meanGrade = $s['graded'] > 0 ? round($s['raw_sum'] / $s['graded'], 1) . '%' : 'N/A';
        $passRate = $s['graded'] > 0 ? number_format(($s['passed'] / $s['graded']) * 100, 1) . '%' : 'N/A';
        
        $atRisk = $s['high_risk'] + $s['mod_risk'];
        $atRiskRate = $s['graded'] > 0 ? number_format(($atRisk / $s['graded']) * 100, 1) . '%' : 'N/A';
        
        // FIXED: Replaced raw count with comprehensive "X of Y (Z%)" representation
        $atRiskDisplay = $atRisk > 0 ? "{$atRisk} of {$s['graded']} ({$atRiskRate})" : "0";

        // FIXED: Visible 'Limited Data' flag rule (80% threshold)
        $coverageVal = $s['enrolled'] > 0 ? ($s['graded'] / $s['enrolled']) : 0;
        $isLimited = $s['graded'] > 0 && $coverageVal < 0.8;
        $limitedHtml = $isLimited ? '<br><span style="font-size:8px; color:#d97706;">Limited Data</span>' : '';

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($s['code']) . '</strong><br><span style="color:#64748b; font-size:8px;">' . htmlspecialchars($s['title']) . '</span></td>
            <td style="text-align: center;">' . $s['graded'] . ' / ' . $s['enrolled'] . $limitedHtml . '</td>
            <td style="text-align: center;">' . $meanGrade . '</td>
            <td style="text-align: center;">' . $passRate . '</td>
            <td style="text-align: center;"><span class="' . ($atRisk > 0 ? 'text-high' : 'text-na') . '">' . $atRiskDisplay . '</span></td>
        </tr>';
    }
    $html .= '</tbody></table>';

} else {
    $html .= '
    <div class="section-title">Completed Subject Outcomes</div>
    <p style="font-size: 8.5px; color: #64748b; margin-top: -4px; margin-bottom: 8px;">Section and year-level comparisons are unavailable for historical terms because the database does not preserve each student\'s section and year level for prior academic terms.</p>
    <table class="data-table">
        <thead><tr>
            <th>Subject</th>
            <th style="text-align: center;">Students</th>
            <th style="text-align: center;">Mean Official Final Grade</th>
            <th style="text-align: center;">Official Pass Rate</th>
            <th style="text-align: center;">Failed Students</th>
            <th style="text-align: center;">Completeness</th>
        </tr></thead>
        <tbody>';

    if (empty($histSubjects)) {
        $html .= '<tr><td colspan="6" style="text-align:center; color:#94a3b8;">No subject outcomes found for the selected historical term.</td></tr>';
    }
    foreach ($histSubjects as $s) {
        $meanGrade = $s['graded'] > 0 ? number_format($s['raw_sum'] / $s['graded'], 2) : 'N/A';
        $passRate = $s['graded'] > 0 ? number_format(($s['passed'] / $s['graded']) * 100, 1) . '%' : 'N/A';
        
        $coverageVal = $s['enrolled'] > 0 ? ($s['graded'] / $s['enrolled']) : 0;
        $isLimitedHist = $s['graded'] > 0 && $coverageVal < 0.8;
        $completeness = $s['enrolled'] > 0 ? number_format($coverageVal * 100, 1) . '%' : '0%';
        if ($isLimitedHist) $completeness .= '<br><span style="font-size:8px; color:#d97706;">Limited Data</span>';

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($s['code']) . '</strong></td>
            <td style="text-align: center;">' . $s['enrolled'] . '</td>
            <td style="text-align: center;">' . $meanGrade . '</td>
            <td style="text-align: center;">' . $passRate . '</td>
            <td style="text-align: center;"><span class="' . ($s['failed'] > 0 ? 'text-high' : 'text-na') . '">' . $s['failed'] . '</span></td>
            <td style="text-align: center;">' . $completeness . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '
    <div style="margin-top: 15px; border-top: 1px solid #cbd5e1; padding-top: 8px; text-align: center; color: #64748b; font-size: 8px; position: absolute; bottom: 10px; width: 100%;">
        <!-- FIXED: Added explanatory note regarding passing threshold vs. requiring attention -->
        <strong>Methodology Note:</strong> Distinction readiness and risk classifications are derived from each student\'s latest available prediction and the system\'s defined thresholds; they do not represent an official determination and are not a substitute for the registrar\'s final academic standing evaluation. A student may be classified as requiring attention while still currently meeting the minimum passing threshold. Historical outcomes reflect officially recorded Final Grades only.<br>
        <strong>Confidentiality Notice:</strong> This report supports academic review and does not represent an automatic disciplinary decision. Contains sensitive student data.
    </div>

</body>
</html>';

// 8. Render
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "UDM-RADAR_Program_Analytics_" . ($isCurrentTerm ? 'Current' : 'Historical') . "_" . date('Ymd_Hi') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;