<?php
// admin/export_subject_performance.php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/csv_export_helpers.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

// 1. Capture Filters (Mirrors analytics.php)
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

// Ensure we are in current-term mode (Historical exports handled separately if needed)
if (!($syFilter === '2026-2027' && $semFilter === '1')) {
    exit("Error: Subject Performance Export is currently scoped to the active term. For historical terms, use the Historical Outcomes report.");
}

// 2. Fetch Selected Grading Period Performance
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

// Sort by Attention Rate Descending
usort($subjectSectionStats, function($a, $b) {
    $rateA = $a['graded'] > 0 ? (($a['high_risk'] + $a['mod_risk']) / $a['graded']) : 0;
    $rateB = $b['graded'] > 0 ? (($b['high_risk'] + $b['mod_risk']) / $b['graded']) : 0;
    if ($rateA === $rateB) return $b['graded'] <=> $a['graded'];
    return $rateB <=> $rateA;
});

// 3. Output CSV Stream
logExportAudit($db, $user['id'], 'Subject Performance Bottleneck', 'CSV', [
    'period' => $periodFilter,
    'year_level' => $yearFilter,
    'section' => $sectionFilter,
    'subject_id' => $subjectFilter
], count($subjectSectionStats));

$output = startCsvDownload("UDM-RADAR_Subject_Performance_" . $periodName);

writeCsvMetadata($output, $user, 'Subject Performance Report', [
    'academic term' => $syFilter . ' (Sem ' . $semFilter . ')',
    'grading period' => $periodName,
    'year level filter' => $yearFilter ?: 'All',
    'section filter' => $sectionFilter ?: 'All',
    'subject filter' => $subjectFilter ?: 'All'
], count($subjectSectionStats));

fputcsv($output, [
    'Subject Code',
    'Subject Title',
    'Section',
    'Enrolled Students',
    'Records Graded',
    'Data Coverage Rate',
    'Mean Score',
    'Moderate Risk Count',
    'High Risk Count',
    'Total Requiring Attention',
    'Attention Rate'
]);

foreach ($subjectSectionStats as $s) {
    $meanGrade = $s['graded'] > 0 ? round($s['raw_sum'] / $s['graded'], 1) . '%' : 'N/A';
    $coverage = $s['enrolled'] > 0 ? number_format(($s['graded'] / $s['enrolled']) * 100, 1) . '%' : '0%';
    $attentionCount = $s['high_risk'] + $s['mod_risk'];
    $attentionRate = $s['graded'] > 0 ? number_format(($attentionCount / $s['graded']) * 100, 1) . '%' : 'N/A';

    fputcsv($output, [
        sanitize_csv_field($s['code']),
        sanitize_csv_field($s['title']),
        sanitize_csv_field($s['section']),
        $s['enrolled'],
        $s['graded'],
        $coverage,
        $meanGrade,
        $s['mod_risk'],
        $s['high_risk'],
        $attentionCount,
        $attentionRate
    ]);
}

fclose($output);
exit;