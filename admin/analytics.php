<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

// ordinalYearLabel() now lives in config/constants.php so the student
// directory, the export headers, and this page all render a year level the
// same way. The local copy that used to sit here was identical for years 1-4
// and differed only in the fallback, which is exactly the kind of drift a
// single shared definition prevents.

// ---------------------------------------------------------
// 0. Dynamic Filter Population & Strict Validation
// ---------------------------------------------------------
$dbSys = $db->query("SELECT DISTINCT school_year FROM grades WHERE school_year IS NOT NULL ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($dbSys)) $dbSys = ['2026-2027'];
$syFilter = $_GET['sy'] ?? $dbSys[0];
if (!in_array($syFilter, $dbSys, true)) $syFilter = $dbSys[0];

$allowedSemesters = ['1', '2'];
$semFilter = $_GET['semester'] ?? '1';
if (!in_array($semFilter, $allowedSemesters, true)) $semFilter = '1';

// The filter offers every curriculum year (1-4), not only the years that
// currently have a student row. Deriving the list from the data meant a year
// with no enrolled students simply vanished from the control, so an operator
// could not select an empty cohort to confirm it was in fact empty, and the
// control's shape changed as the population changed.
$dbYears = CURRICULUM_YEAR_LEVELS;
$yearFilter = $_GET['year_level'] ?? '';
if ($yearFilter !== '' && !in_array((int) $yearFilter, $dbYears, true)) $yearFilter = '';

// New Filters for Path B
$allowedPeriods = ['prelim' => 'Preliminary', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Final'];
$periodFilter = $_GET['period'] ?? 'prelim';
if (!array_key_exists($periodFilter, $allowedPeriods)) $periodFilter = 'prelim';
$periodName = $allowedPeriods[$periodFilter];

$dbSubjects = $db->query("SELECT id, code, title FROM subjects ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$subjectFilter = $_GET['subject_id'] ?? '';

$dbSections = $db->query("SELECT DISTINCT section FROM student_profiles WHERE section IS NOT NULL ORDER BY section")->fetchAll(PDO::FETCH_COLUMN);
$sectionFilter = $_GET['section'] ?? '';

// Determine Mode
// "Current" is whatever term the calendar says we are in, not a hardcoded
// school-year/semester pair. Pinning it meant the page stayed in progress-mode
// for one specific term forever: once the calendar moved on, a genuinely
// finished term kept rendering as if its grades were still in flight.
$currentTerm = getCurrentTerm();
$isCurrentTerm = ($syFilter === $currentTerm['school_year'] && (int) $semFilter === (int) $currentTerm['semester']);

// ---------------------------------------------------------
// Default values to prevent IDE warnings (P1116)
// ---------------------------------------------------------
$totalStudents = 0; $sectionData = []; $meanGwa = null; $gwaSum = 0; $gwaCount = 0;
$atRiskPct = 0.0; $coveragePct = 0.0;
$riskTotals = ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0];
// prediction_source has TWO calculation-based vocabularies in the stored data:
//   'heuristic'            — legacy rows written before the source was renamed
//   'calculation_fallback' — what the current fallback path writes
// Both mean the same thing to a reader (an estimate derived from current
// grades, not model output), so they are grouped for display under one
// user-facing category while the stored value itself is left untouched — the
// 502 legacy rows are provenance and are not rewritten.
//
// The tally previously keyed only on 'heuristic' and was guarded by isset(),
// so it did not error; it silently reported 0 for every row written under the
// newer vocabulary. The diagnostic breakdown is retained so the split between
// the two vocabularies stays visible rather than being averaged away.
$sourceTotals = [
    'decision_tree'        => 0, // machine-learning projection
    'calculation_fallback' => 0, // grouped display total (both fallback vocabularies)
    'heuristic'            => 0, // diagnostic: legacy fallback rows only
    'calc_fallback_new'    => 0, // diagnostic: current fallback rows only
    'none'                 => 0,
];
$distinctions = [
    'Summa-level threshold' => 0, 'Magna-level threshold' => 0,
    'Cum Laude-level threshold' => 0, 'Not currently within a distinction threshold' => 0,
    'Insufficient Data' => 0
];
$chartSecLabels = []; $chartSecHigh = []; $chartSecMod = []; $chartSecLow = []; $chartSecNone = [];
$subjectWideStats = []; $subjectSectionStats = []; $histSubjects = [];
$totalHistStudents = 0; $meanHistGrade = null; $histGradesSum = 0; $histGradesCount = 0;
$histPassedCount = 0; $histFailedCount = 0; $histDroppedCount = 0; $histTotalGradedCount = 0; $histRecognizedOutcomeCount = 0;
$overallHistPassRate = null;

// ---------------------------------------------------------
// MODE A: CURRENT TERM ANALYTICS (Progress & Predictions)
// ---------------------------------------------------------
if ($isCurrentTerm) {
    // 1. Fetch Current Students & Predictions (Restored for Visual Analytics)
    $sqlStudents = "
        SELECT sp.user_id, sp.section, sp.current_gwa, sp.status, sp.year_level,
               p.predicted_gwa, p.risk_level, p.latin_honor, p.prediction_source
        FROM student_profiles sp
        LEFT JOIN predictions p ON p.id = (
            SELECT id FROM predictions p2
            WHERE p2.student_id = sp.user_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
        WHERE sp.section IS NOT NULL AND sp.record_status = 'Active'
    ";
    $paramsStudents = [];
    if ($yearFilter !== '') { $sqlStudents .= " AND sp.year_level = ?"; $paramsStudents[] = (int) $yearFilter; }
    if ($sectionFilter !== '') { $sqlStudents .= " AND sp.section = ?"; $paramsStudents[] = $sectionFilter; }

    $stmtStudents = $db->prepare($sqlStudents);
    $stmtStudents->execute($paramsStudents);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    // 2. Aggregate Current Data for KPIs and Charts
    $totalStudents = count($students);

    foreach ($students as $s) {
        $sec = $s['section'];
        if (!isset($sectionData[$sec])) {
            $sectionData[$sec] = [
                'total' => 0, 'gwa_sum' => 0, 'gwa_count' => 0, 'irregular' => 0,
                'risks' => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
                'coverage_count' => 0
            ];
        }

        $sectionData[$sec]['total']++;
        if ($s['status'] === 'Irregular') $sectionData[$sec]['irregular']++;

        if ($s['current_gwa'] !== null && (float)$s['current_gwa'] > 0) {
            $gwaSum += (float)$s['current_gwa'];
            $gwaCount++;
            $sectionData[$sec]['gwa_sum'] += (float)$s['current_gwa'];
            $sectionData[$sec]['gwa_count']++;
        }

        $risk = $s['risk_level'] !== null ? strtoupper($s['risk_level']) : 'NONE';
        $riskTotals[$risk]++;
        $sectionData[$sec]['risks'][$risk]++;
        if ($risk !== 'NONE') $sectionData[$sec]['coverage_count']++;

        // Normalize before counting. An unrecognized or empty source is
        // counted as 'none' rather than dropped, so the categories always sum
        // to the population being described instead of quietly under-reporting.
        $src = strtolower(trim((string) ($s['prediction_source'] ?? '')));

        if ($src === 'decision_tree') {
            $sourceTotals['decision_tree']++;
        } elseif (in_array($src, ['heuristic', 'calculation_fallback'], true)) {
            $sourceTotals['calculation_fallback']++;
            $sourceTotals[$src === 'heuristic' ? 'heuristic' : 'calc_fallback_new']++;
        } else {
            $sourceTotals['none']++;
        }

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
    ksort($sectionData);

    $meanGwa = $gwaCount > 0 ? $gwaSum / $gwaCount : null;
    $coverageCount = $totalStudents - $riskTotals['NONE'];
    $coveragePct = $totalStudents > 0 ? ($coverageCount / $totalStudents) * 100 : 0.0;

    // Accurate At-Risk Denominator: Only count students with predictions
    $atRiskCount = $riskTotals['HIGH'] + $riskTotals['MODERATE'];
    $atRiskPct = $coverageCount > 0 ? ($atRiskCount / $coverageCount) * 100 : 0.0;

    // Chart Data Generation
    $chartSecLabels = array_keys($sectionData);
    foreach ($chartSecLabels as $sec) {
        $t = $sectionData[$sec]['total'];
        $chartSecHigh[] = $t > 0 ? round(($sectionData[$sec]['risks']['HIGH'] / $t) * 100, 1) : 0;
        $chartSecMod[]  = $t > 0 ? round(($sectionData[$sec]['risks']['MODERATE'] / $t) * 100, 1) : 0;
        $chartSecLow[]  = $t > 0 ? round(($sectionData[$sec]['risks']['LOW'] / $t) * 100, 1) : 0;
        $chartSecNone[] = $t > 0 ? round(($sectionData[$sec]['risks']['NONE'] / $t) * 100, 1) : 0;
    }

    // 3. Subject Bottleneck Queries (The new Path B Operational Tables)
    $sqlSubjects = "
        SELECT s.id, s.code, s.title, sp.section, sp.year_level, g.{$periodFilter} AS term_score
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.school_year = ? AND g.semester = ? AND sp.record_status = 'Active'
    ";
    $paramsSubjects = [$syFilter, $semFilter];

    if ($yearFilter !== '') { $sqlSubjects .= " AND sp.year_level = ?"; $paramsSubjects[] = (int) $yearFilter; }
    if ($sectionFilter !== '') { $sqlSubjects .= " AND sp.section = ?"; $paramsSubjects[] = $sectionFilter; }
    if ($subjectFilter !== '') { $sqlSubjects .= " AND s.id = ?"; $paramsSubjects[] = $subjectFilter; }

    $stmtSubjects = $db->prepare($sqlSubjects);
    $stmtSubjects->execute($paramsSubjects);
    $rawGrades = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawGrades as $r) {
        $id = $r['id'];
        $sec = $r['section'] ?? 'Unassigned';
        $secKey = $id . '_' . $sec;

        // Subject-Wide Aggregation
        if (!isset($subjectWideStats[$id])) {
            $subjectWideStats[$id] = ['code' => $r['code'], 'title' => $r['title'], 'enrolled' => 0, 'graded' => 0, 'raw_sum' => 0, 'passed' => 0, 'high_risk' => 0, 'mod_risk' => 0];
        }
        $subjectWideStats[$id]['enrolled']++;

        // Subject + Section Aggregation (Path B Requirement)
        if (!isset($subjectSectionStats[$secKey])) {
            $subjectSectionStats[$secKey] = [
                'id' => $id, 'code' => $r['code'], 'title' => $r['title'], 'section' => $sec, 'year_level' => $r['year_level'],
                'enrolled' => 0, 'graded' => 0, 'raw_sum' => 0, 'passed' => 0, 'high_risk' => 0, 'mod_risk' => 0
            ];
        }
        $subjectSectionStats[$secKey]['enrolled']++;

        if ($r['term_score'] !== null && trim((string)$r['term_score']) !== '') {
            $val = (float)$r['term_score'];
            $subjectWideStats[$id]['graded']++;
            $subjectWideStats[$id]['raw_sum'] += $val;

            $subjectSectionStats[$secKey]['graded']++;
            $subjectSectionStats[$secKey]['raw_sum'] += $val;

            $pt = normalizeTermGrade($r['term_score']);
            if ($pt !== null) {
                $risk = computeRiskFromAvg($pt);
                if ($risk === 'HIGH') {
                    $subjectWideStats[$id]['high_risk']++;
                    $subjectSectionStats[$secKey]['high_risk']++;
                } elseif ($risk === 'MODERATE') {
                    $subjectWideStats[$id]['mod_risk']++;
                    $subjectSectionStats[$secKey]['mod_risk']++;
                }
                if ($pt > 0) {
                    $subjectWideStats[$id]['passed']++;
                    $subjectSectionStats[$secKey]['passed']++;
                }
            }
        }
    }

    // Sort Subject-Wide by High Risk
    usort($subjectWideStats, fn($a, $b) => $b['high_risk'] <=> $a['high_risk']);

    // Sort Subject-Section by Attention Rate (High + Mod / Graded)
    usort($subjectSectionStats, function($a, $b) {
        $rateA = $a['graded'] > 0 ? (($a['high_risk'] + $a['mod_risk']) / $a['graded']) : 0;
        $rateB = $b['graded'] > 0 ? (($b['high_risk'] + $b['mod_risk']) / $b['graded']) : 0;
        if ($rateA === $rateB) return $b['graded'] <=> $a['graded']; // Fallback to volume
        return $rateB <=> $rateA;
    });
}

// ---------------------------------------------------------
// MODE B: HISTORICAL TERM (Completed Outcomes)
// ---------------------------------------------------------
else {
    $sqlHistorical = "
        SELECT s.id, s.code, s.title, g.final_grade, g.student_id
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.school_year = ? AND g.semester = ? AND sp.record_status IN ('Active', 'Graduated')
    ";
    $paramsHistorical = [$syFilter, $semFilter];

    if ($yearFilter !== '') { $sqlHistorical .= " AND sp.year_level = ?"; $paramsHistorical[] = (int) $yearFilter; }
    if ($subjectFilter !== '') { $sqlHistorical .= " AND s.id = ?"; $paramsHistorical[] = $subjectFilter; }

    $stmtHistorical = $db->prepare($sqlHistorical);
    $stmtHistorical->execute($paramsHistorical);
    $rawHistGrades = $stmtHistorical->fetchAll(PDO::FETCH_ASSOC);

    $histStudents = [];

    foreach ($rawHistGrades as $r) {
        $histStudents[$r['student_id']] = true;
        $id = $r['id'];
        if (!isset($histSubjects[$id])) {
             $histSubjects[$id] = [
                 'code' => $r['code'],
                 'title' => $r['title'],
                 'enrolled' => 0,
                 'graded' => 0,
                 'raw_sum' => 0.0,
                 'numeric_count' => 0,
                 'numeric_pass_count' => 0,
                 'nonnumeric_pass_count' => 0,
                 'failing_count' => 0,
                 'dropped_count' => 0,
                 'missing_or_invalid_count' => 0,
                 'recognized_outcome_count' => 0,
                 'passed' => 0,
                 'failed' => 0,
             ];
        }
        $histSubjects[$id]['enrolled']++;

        $rawGrade = $r['final_grade'];
        if ($rawGrade === null || trim((string)$rawGrade) === '') {
            $histSubjects[$id]['missing_or_invalid_count']++;
            continue;
        }

        $canon = canonicalizeFinalGrade($rawGrade);
        if ($canon === null) {
            $histSubjects[$id]['missing_or_invalid_count']++;
            continue;
        }

        if ($canon === 'DRP') {
            $histSubjects[$id]['dropped_count']++;
            $histSubjects[$id]['graded']++;
            $histDroppedCount++;
            $histTotalGradedCount++;
            continue;
        }

        // Recognized pass/fail outcome
        $histSubjects[$id]['graded']++;
        $histTotalGradedCount++;

        if (isNumericFinalGrade($canon)) {
            $pt = (float)$canon;
            $histSubjects[$id]['raw_sum'] += $pt;
            $histSubjects[$id]['numeric_count']++;
            $histGradesSum += $pt;
            $histGradesCount++;

            if (isPassingFinalGrade($canon)) {
                $histSubjects[$id]['numeric_pass_count']++;
                $histSubjects[$id]['passed']++;
                $histPassedCount++;
            } elseif (isFailingFinalGrade($canon)) {
                $histSubjects[$id]['failing_count']++;
                $histSubjects[$id]['failed']++;
                $histFailedCount++;
            }
        } else {
            if (isPassingFinalGrade($canon)) {
                $histSubjects[$id]['nonnumeric_pass_count']++;
                $histSubjects[$id]['passed']++;
                $histPassedCount++;
            } elseif (isFailingFinalGrade($canon)) {
                $histSubjects[$id]['failing_count']++;
                $histSubjects[$id]['failed']++;
                $histFailedCount++;
            }
        }
        $histSubjects[$id]['recognized_outcome_count'] = $histSubjects[$id]['passed'] + $histSubjects[$id]['failed'];
    }
    usort($histSubjects, fn($a, $b) => $b['failed'] <=> $a['failed']);

    $histRecognizedOutcomeCount = $histPassedCount + $histFailedCount;
    $totalHistStudents = count($histStudents);
    $meanHistGrade = $histGradesCount > 0 ? $histGradesSum / $histGradesCount : null;
    $overallHistPassRate = $histRecognizedOutcomeCount > 0 ? ($histPassedCount / $histRecognizedOutcomeCount) * 100 : null;
}

$pageTitle = 'Program Analytics';
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

<style>
.analytics-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }

/* Subject & Section accordion: smooth rotating arrow + fade-in content,
   matching the fadeIn keyframe used in activity.php's tab system rather
   than an instant glyph swap. */
.accordion-arrow {
    display: inline-block;
    color: var(--text-gray);
    font-size: 0.9rem;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
#subject-section-details summary::-webkit-details-marker { display: none; }
#subject-section-details[open] .accordion-arrow { transform: rotate(90deg); }
#subject-section-details .accordion-fade { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.analytics-chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-bottom: 24px; }
.table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
@media (max-width: 1200px) { .analytics-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .analytics-chart-grid { grid-template-columns: 1fr; } }
@media (max-width: 700px) { .analytics-stat-grid { grid-template-columns: 1fr; } }
</style>

<div class="main-content">
    <div class="header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <h1 style="text-transform: uppercase; letter-spacing: 0.5px;">BSIT Program Analytics</h1>
            <p style="color: var(--text-gray);">Program-level academic patterns and curriculum bottleneck identification.</p>
        </div>
        <div>
            <!--
                Page-wide export, placed at the top matching the Program
                Snapshot button on admin/index.php, not buried near one table.
                Endpoint/scope still pending: whether this folds in the
                Subject & Section Bottleneck data (replacing
                export_subject_performance_pdf.php) or sits alongside it as a
                separate broader document covering Honors Distribution,
                Subject-level data, and Historical Outcomes — the parts of
                this page that aren't already covered by the Program Snapshot
                PDF on the dashboard.
            -->
            <button type="button" onclick="triggerProgramAnalyticsExportPdf()" style="padding: 10px 16px; background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-family: inherit; white-space: nowrap;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                Download PDF Report
            </button>
        </div>
    </div>

    <!-- Wired Interactive Filters -->
    <form method="GET" action="analytics.php" class="card" style="display: flex; gap: 16px; align-items: flex-end; margin-bottom: 24px; padding: 16px 24px; flex-wrap: wrap;">

        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-gray);">Academic Scope</label>
            <div style="display: flex; gap: 8px;">
                <select name="sy" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <?php foreach($dbSys as $sy): ?>
                        <option value="<?= htmlspecialchars($sy) ?>" <?= $syFilter === $sy ? 'selected' : '' ?>>S.Y. <?= htmlspecialchars($sy) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="semester" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="1" <?= $semFilter === '1' ? 'selected' : '' ?>>Semester 1</option>
                    <option value="2" <?= $semFilter === '2' ? 'selected' : '' ?>>Semester 2</option>
                </select>
            </div>
        </div>

        <?php if ($isCurrentTerm): ?>
        <div style="display: flex; flex-direction: column; gap: 4px; border-left: 2px solid var(--border-color); padding-left: 16px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-gray);">Grading Period</label>
            <select name="period" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                <?php foreach($allowedPeriods as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= $periodFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 4px; border-left: 2px solid var(--border-color); padding-left: 16px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-gray);">Curriculum Isolation</label>
            <div style="display: flex; gap: 8px;">
                <select name="year_level" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="">All Years</option>
                    <?php foreach($dbYears as $yl): ?>
                        <option value="<?= (int) $yl ?>" <?= $yearFilter === (string) $yl ? 'selected' : '' ?>><?= htmlspecialchars(ordinalYearLabel($yl)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="subject_id" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="">All Subjects</option>
                    <?php foreach($dbSubjects as $subj): ?>
                        <option value="<?= htmlspecialchars($subj['id']) ?>" <?= $subjectFilter === (string)$subj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($subj['code']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isCurrentTerm): ?>
                <select name="section" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="">All Sections</option>
                    <?php foreach($dbSections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>" <?= $sectionFilter === (string)$sec ? 'selected' : '' ?>><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-left: auto; display: flex; gap: 8px; align-items: center; height: 100%;">
            <a href="analytics.php" style="padding: 9px 16px; color: var(--text-gray); text-decoration: none; font-weight: 600; font-size: 0.9rem;">Reset</a>
            <button type="submit" style="background: var(--accent-blue); color: white; border: none; padding: 9px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; transition: opacity 0.2s;">
                Apply Filters
            </button>
        </div>
    </form>

    <div style="margin-bottom: 24px; font-size: 0.85rem; color: var(--text-gray);">
        <em>Note: School Year and Semester dictate term performance mode. Year Level applies to current profiles and joins against matching historical records.</em>
    </div>

    <!-- ========================================== -->
    <!-- MODE A: CURRENT TERM (Progress Analysis)   -->
    <!-- ========================================== -->
    <?php if ($isCurrentTerm): ?>

        <?php if ($totalStudents > 0 && $totalStudents < 10): ?>
        <div style="background: rgba(217, 119, 6, 0.1); border-left: 4px solid var(--risk-mod); padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; color: var(--text-dark); font-size: 0.9rem;">
            <strong>Small filtered population:</strong> This view contains only <?= $totalStudents ?> student records. Percentages and section comparisons should be interpreted cautiously.
        </div>
        <?php endif; ?>

        <!-- RESTORED: Current 4-Card Analytical Overview -->
        <div class="analytics-stat-grid">
            <div class="stat-card" style="border-left-color: var(--accent-blue);">
                <h4>Students Analyzed</h4>
                <h2 style="color: var(--text-dark);"><?= $totalStudents ?></h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Across <?= count($sectionData) ?> active sections</p>
            </div>
            <div class="stat-card" style="border-left-color: var(--risk-low);">
                <h4>Mean Student GWA</h4>
                <h2 style="color: var(--risk-low);"><?= $meanGwa !== null ? number_format($meanGwa, 2) : 'N/A' ?></h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Based on <?= $gwaCount ?> available records</p>
            </div>
            <div class="stat-card" style="border-left-color: var(--risk-high);">
                <h4>At Risk Among Predicted</h4>
                <h2 style="color: var(--risk-high);"><?= number_format($atRiskPct, 1) ?>%</h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">
                    <span style="color: var(--risk-high);"><?= $riskTotals['HIGH'] ?> High</span> |
                    <span style="color: var(--risk-mod);"><?= $riskTotals['MODERATE'] ?> Moderate</span>
                </p>
            </div>
            <div class="stat-card" style="border-left-color: var(--text-gray);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h4>Prediction Coverage</h4>
                    <span class="custom-tooltip tooltip-top-right" tabindex="0" aria-label="Coverage Breakdown">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-gray);"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <span class="tooltip-text" role="tooltip"><?= $sourceTotals['decision_tree'] ?> AI-Based &bull; <?= $sourceTotals['calculation_fallback'] ?> Calculation-Based &bull; <?= $sourceTotals['none'] ?> Insufficient Data</span>
                    </span>
                </div>
                <h2 style="color: var(--text-dark);"><?= number_format($coveragePct, 1) ?>%</h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">
                    <?= $coverageCount ?> of <?= $totalStudents ?> students active
                </p>
            </div>
        </div>

        <!-- RESTORED: Visual Analytics Charts -->
        <div class="analytics-chart-grid">
            <div class="card" style="position: relative; height: 380px;">
                <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">Risk Distribution by Section</div>
                <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Percentage-based risk comparison enabling section-to-section analysis.</p>
                <?php if (empty($sectionData)): ?>
                    <p class="empty-state" style="margin-top: 60px;">No data matches your current filter.</p>
                <?php else: ?>
                    <div style="position: relative; height: 280px; width: 100%;"><canvas id="sectionRiskChart"></canvas></div>
                <?php endif; ?>
            </div>
            
            <div class="card" style="position: relative; height: 380px;">
                <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">Academic Distinction Readiness</div>
                <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Based on each student's latest available estimate or prediction. Does not represent official eligibility.</p>
                <?php if (empty($sectionData)): ?>
                    <p class="empty-state" style="margin-top: 60px;">No data matches your current filter.</p>
                <?php else: ?>
                    <div style="position: relative; height: 280px; width: 100%;"><canvas id="honorChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RESTORED: Section Comparison Table -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="table-title" style="margin-bottom: 16px; color: var(--text-dark);">Section Comparison Table</div>
            <?php if (empty($sectionData)): ?>
                <p class="empty-state">No section data matches your current filter.</p>
            <?php else: ?>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                    <thead>
                        <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Section</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Students</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Mean GWA</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-low); white-space: nowrap;">Low</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-mod); white-space: nowrap;">Moderate</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-high); white-space: nowrap;">High</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-gray); white-space: nowrap;">No Prediction</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionData as $sec => $d): 
                            $secMean = $d['gwa_count'] > 0 ? number_format($d['gwa_sum'] / $d['gwa_count'], 2) : 'N/A';
                            $secCov = $d['total'] > 0 ? number_format(($d['coverage_count'] / $d['total']) * 100, 1) . '%' : '0%';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px; font-weight: 700; color: var(--accent-blue); white-space: nowrap;"><?= htmlspecialchars($sec) ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $d['total'] ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); font-weight: 700;"><?= $secMean ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $d['risks']['LOW'] ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $d['risks']['MODERATE'] ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $d['risks']['HIGH'] ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-gray);"><?= $d['risks']['NONE'] ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); font-weight: 600;"><?= $secCov ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Compact Subject-Wide Overview — shown first: this is the
             summary-level table most visits want. The detailed
             Subject & Section table below is a deliberate drill-down. -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="table-title" style="margin-bottom: 4px; color: var(--text-dark);">Curriculum-Wide Overview (<?= $periodName ?>)</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Consolidated subject performance across all matching sections.</p>
            <?php if (empty($subjectWideStats)): ?>
                <p class="empty-state">No subjects found matching the current filter scope.</p>
            <?php else: ?>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
                    <thead>
                        <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Subject</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Total Graded</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Mean Score</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Pass Rate</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Total At Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectWideStats as $id => $s): 
                            $meanGrade = $s['graded'] > 0 ? round($s['raw_sum'] / $s['graded'], 1) . '%' : '—';
                            $passRate = $s['graded'] > 0 ? number_format(($s['passed'] / $s['graded']) * 100, 1) . '%' : '—';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px; font-weight: 700; color: var(--text-dark); white-space: nowrap;"><?= htmlspecialchars($s['code']) ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $s['graded'] ?> / <?= $s['enrolled'] ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= $meanGrade ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= $passRate ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--risk-high); font-weight: 700;"><?= ($s['high_risk'] + $s['mod_risk']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detailed Subject & Section Bottleneck Table — collapsed by
             default. With ~10 sections this can run 60-80 rows on an
             unfiltered page load; defaulting it open forced every visit to
             scroll past the longest table on the page before reaching
             anything else. It's still one click away, and the row count is
             shown up front so nothing is hidden, just not force-expanded. -->
        <div class="card" style="margin-bottom: 24px;">
            <details id="subject-section-details" ontoggle="onSubjectSectionToggle(this)">
                <summary style="cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <div class="table-title" style="margin-bottom: 4px; color: var(--text-dark); display: inline;">Subject & Section Performance Report</div>
                        <span style="font-size: 0.8rem; color: var(--text-gray); margin-left: 8px;">(<?= count($subjectSectionStats) ?> combinations — click to expand)</span>
                        <p style="font-size: 0.8rem; color: var(--text-gray); margin: 4px 0 0;">Identifying class-specific bottlenecks requiring attention based on <?= $periodName ?> scores.</p>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <button type="button" onclick="event.preventDefault(); event.stopPropagation(); triggerSubjectExportCsv();" style="background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Export Subject Report (CSV)
                        </button>
                        <button type="button" onclick="event.preventDefault(); event.stopPropagation(); triggerSubjectExportPdf();" style="background: var(--accent-blue); color: white; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: opacity 0.2s; white-space: nowrap;" title="Top 15 bottleneck combinations, ranked">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            Download PDF (Top 15)
                        </button>
                        <!-- Arrow lives here now, next to the buttons as its own
                             flex item, instead of as ::after on <summary> itself
                             — previously that made it a 4th flex child that
                             space-between shoved away from the title it was
                             meant to sit beside, and put visible daylight
                             between the two buttons in the process. -->
                        <span class="accordion-arrow" aria-hidden="true">▸</span>
                    </div>
                </summary>

                <div class="accordion-fade" style="margin-top: 16px;">
            <?php if (empty($subjectSectionStats)): ?>
                <p class="empty-state">No section bottlenecks found matching the current filter scope.</p>
            <?php else: ?>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; min-width: 1100px;">
                    <thead>
                        <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Subject</th>
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Section</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Enrolled</th>
                            <th style="padding: 12px; text-align: center; color: var(--accent-blue); white-space: nowrap;">Data Coverage</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Mean Score</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-mod); white-space: nowrap;">Mod Risk</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-high); white-space: nowrap;">High Risk</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-high); white-space: nowrap;">Attention Count</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Attention Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectSectionStats as $secKey => $s): 
                            $meanGrade = $s['graded'] > 0 ? round($s['raw_sum'] / $s['graded'], 1) . '%' : '—';
                            $coverage = $s['enrolled'] > 0 ? number_format(($s['graded'] / $s['enrolled']) * 100, 1) . '%' : '0%';
                            $attentionCount = $s['high_risk'] + $s['mod_risk'];
                            $attentionRate = $s['graded'] > 0 ? number_format(($attentionCount / $s['graded']) * 100, 1) . '%' : '—';
                            $isLimData = ($s['enrolled'] > 0 && ($s['graded'] / $s['enrolled']) < 0.8) && $s['graded'] > 0;
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px; font-weight: 700; color: var(--text-dark); white-space: nowrap;">
                                <div style="line-height: 1.2;">
                                    <?= htmlspecialchars($s['code']) ?><br>
                                    <span style="font-weight: 400; font-size: 0.8rem; color: var(--text-gray);"><?= htmlspecialchars($s['title']) ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px; font-weight: 700; color: var(--accent-blue); white-space: nowrap;"><?= htmlspecialchars($s['section']) ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $s['enrolled'] ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: <?= $isLimData ? 'var(--risk-mod)' : 'var(--accent-blue)' ?>;">
                                <?= $coverage ?>
                                <?= $isLimData ? '<br><span style="font-size: 0.7rem;">(Limited Data)</span>' : '' ?>
                            </td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark);"><?= $meanGrade ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);">
                                <?php if($s['mod_risk'] > 0): ?><span class="badge" style="background: var(--risk-mod);"><?= $s['mod_risk'] ?></span><?php else: ?><span style="color: var(--text-gray);">0</span><?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark);">
                                <?php if($s['high_risk'] > 0): ?><span class="badge" style="background: var(--risk-high);"><?= $s['high_risk'] ?></span><?php else: ?><span style="color: var(--text-gray);">0</span><?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center; font-weight: 700; color: var(--text-dark);"><?= $attentionCount ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 700; color: <?= $attentionCount > 0 ? 'var(--risk-high)' : 'var(--risk-low)' ?>;"><?= $attentionRate ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
                </div>
            </details>
        </div>
        
    <!-- ========================================== -->
    <!-- MODE B: HISTORICAL TERM (Completed Analytics)-->
    <!-- ========================================== -->
    <?php else: ?>
        
        <div style="background: rgba(13, 110, 253, 0.1); border-left: 4px solid var(--accent-blue); padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; color: var(--text-dark); font-size: 0.9rem;">
            <strong>Historical outcome view:</strong> Section and year-level comparisons are unavailable because the current database does not preserve each student’s section and year level for prior academic terms. The results below summarize official subject outcomes for the selected school year and semester.
        </div>

        <!-- RESTORED: Historical 4-Card Overview -->
        <div class="analytics-stat-grid">
            <div class="stat-card" style="border-left-color: var(--accent-blue);">
                <h4>Students Represented</h4>
                <h2 style="color: var(--text-dark);"><?= $totalHistStudents ?></h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Based on historical grade records</p>
            </div>
            <div class="stat-card" style="border-left-color: var(--text-dark);">
                <h4>Subjects Analyzed</h4>
                <h2 style="color: var(--text-dark);"><?= count($histSubjects) ?></h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Unique courses in term</p>
            </div>
            <div class="stat-card" style="border-left-color: var(--risk-low);">
                <h4>Mean Official Final Grade</h4>
                <h2 style="color: var(--risk-low);"><?= $meanHistGrade !== null ? number_format($meanHistGrade, 2) : 'N/A' ?></h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Across <?= $histGradesCount ?> encoded final grades</p>
            </div>
            <div class="stat-card" style="border-left-color: var(--text-gray);">
                <h4>Overall Pass Rate</h4>
                <h2 style="color: var(--text-dark);"><?= $overallHistPassRate !== null ? number_format($overallHistPassRate, 1) . '%' : 'N/A' ?></h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;"><?= $histPassedCount ?> passing grades recorded</p>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px;">
            <div class="table-toolbar">
                <div>
                    <div class="table-title" style="margin-bottom: 4px; color: var(--text-dark);">Completed Subject Outcomes</div>
                    <p style="font-size: 0.8rem; color: var(--text-gray); margin: 0;">Historical analysis based on official Final Grades.</p>
                </div>
            </div>
            <?php if (empty($histSubjects)): ?>
                <p class="empty-state">No subject outcomes found for the selected historical term.</p>
            <?php else: ?>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
                    <thead>
                        <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap; width: 1%;">Subject</th>
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Title</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">Students</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">Mean Official Final Grade</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">Official Pass Rate</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-high); white-space: nowrap; width: 1%;">Failed Students</th>
                            <th style="padding: 12px; text-align: center; color: var(--accent-blue); white-space: nowrap; width: 1%;">Final-grade Completeness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($histSubjects as $id => $s): 
                            $meanGrade = ($s['numeric_count'] ?? $s['graded']) > 0 ? number_format($s['raw_sum'] / ($s['numeric_count'] ?? $s['graded']), 2) : '—';
                            $passRate = $s['recognized_outcome_count'] > 0 ? number_format(($s['passed'] / $s['recognized_outcome_count']) * 100, 1) . '%' : '—';
                            $completeness = $s['enrolled'] > 0 ? number_format(($s['graded'] / $s['enrolled']) * 100, 1) . '%' : '0%';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px; font-weight: 700; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= htmlspecialchars($s['code']) ?></td>
                            <td title="<?= htmlspecialchars($s['title']) ?>" style="padding: 12px; color: var(--text-gray); max-width: 0; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($s['title']) ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= $s['enrolled'] ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= $meanGrade ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= $passRate ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">
                                <?php if($s['failed'] > 0): ?><span class="badge" style="background: var(--risk-high);"><?= $s['failed'] ?></span><?php else: ?><span style="color: var(--text-gray);">0</span><?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--accent-blue); white-space: nowrap; width: 1%;"><?= $completeness ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function triggerSubjectExportCsv() {
    window.location.href = 'export_subject_performance.php' + window.location.search;
}
function triggerSubjectExportPdf() {
    window.location.href = 'export_subject_performance_pdf.php' + window.location.search;
}
function triggerProgramAnalyticsExportPdf() {
    // Endpoint name/scope pending — see comment near the header button.
    window.location.href = 'export_program_analytics_pdf.php' + window.location.search;
}

// Auto-scroll the expanded table into view, matching the plain
// scrollIntoView pattern used in grades.php's roster panel — my earlier
// version added a manual getBoundingClientRect threshold check that ended
// up skipping the scroll almost every time, since a clicked summary is
// usually already near the top of the viewport. Keeping this simple.
function onSubjectSectionToggle(detailsEl) {
    if (!detailsEl.open) return;
    detailsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<?php if ($isCurrentTerm && !empty($sectionData)): ?>
<!-- RESTORED: Chart.js Logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function getChartTheme() {
    const root = getComputedStyle(document.documentElement);
    return {
        text: root.getPropertyValue('--text-gray').trim(),
        border: root.getPropertyValue('--border-color').trim(),
        summa: '#b45309', magna: '#1d4ed8', cumLaude: '#0e7490',
        none: '#64748B', high: '#DC2626', mod: '#D97706', low: '#059669', insufficient: '#9CA3AF'
    };
}
let theme = getChartTheme();

// 1. Horizontal Stacked Risk Bar Chart (Normalized to Percentages)
const ctxSection = document.getElementById('sectionRiskChart').getContext('2d');
const sectionChart = new Chart(ctxSection, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartSecLabels) ?>,
        datasets: [
            { label: 'High Risk', data: <?= json_encode($chartSecHigh) ?>, backgroundColor: theme.high, stack: 'Stack 0' },
            { label: 'Moderate Risk', data: <?= json_encode($chartSecMod) ?>, backgroundColor: theme.mod, stack: 'Stack 0' },
            { label: 'Low Risk', data: <?= json_encode($chartSecLow) ?>, backgroundColor: theme.low, stack: 'Stack 0' },
            { label: 'No Prediction', data: <?= json_encode($chartSecNone) ?>, backgroundColor: theme.none, stack: 'Stack 0' }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { position: 'bottom', labels: { color: theme.text, usePointStyle: true, boxWidth: 8 } },
            tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + context.parsed.x + '%'; } } }
        },
        scales: {
            x: { stacked: true, max: 100, ticks: { color: theme.text, callback: function(value) { return value + "%" } }, grid: { color: theme.border } },
            y: { stacked: true, ticks: { color: theme.text }, grid: { display: false } }
        }
    }
});

// 2. Academic Distinction Readiness Doughnut Chart
const honorLabels = <?= json_encode(array_keys($distinctions)) ?>;
const honorCounts = <?= json_encode(array_values($distinctions)) ?>;
const honorColors = honorLabels.map(label => {
    if(label.includes('Summa')) return theme.summa;
    if(label.includes('Magna')) return theme.magna;
    if(label.includes('Cum Laude')) return theme.cumLaude;
    if(label.includes('Insufficient Data')) return theme.insufficient;
    return theme.none;
});

const ctxHonor = document.getElementById('honorChart').getContext('2d');
const honorChart = new Chart(ctxHonor, {
    type: 'doughnut',
    data: {
        labels: honorLabels,
        datasets: [{ data: honorCounts, backgroundColor: honorColors, borderWidth: 0, hoverOffset: 4 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: theme.text, font: {size: 10} } } }
    }
});

const observer = new MutationObserver(() => {
    theme = getChartTheme();
    sectionChart.options.scales.x.ticks.color = theme.text;
    sectionChart.options.scales.x.grid.color = theme.border;
    sectionChart.options.scales.y.ticks.color = theme.text;
    sectionChart.options.plugins.legend.labels.color = theme.text;
    sectionChart.update();

    honorChart.options.plugins.legend.labels.color = theme.text;
    honorChart.data.datasets[0].backgroundColor = honorLabels.map(label => {
        if(label.includes('Summa')) return theme.summa;
        if(label.includes('Magna')) return theme.magna;
        if(label.includes('Cum Laude')) return theme.cumLaude;
        if(label.includes('Insufficient Data')) return theme.insufficient;
        return theme.none;
    });
    honorChart.update();
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>