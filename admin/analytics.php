<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

// ---------------------------------------------------------
// Helper: Ordinal Year Labels (Type-Hinted)
// ---------------------------------------------------------
function ordinalYearLabel(int|string $year): string {
    return match ((int) $year) {
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        default => $year . 'th Year',
    };
}

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

$dbYears = $db->query("SELECT DISTINCT year_level FROM student_profiles WHERE year_level IS NOT NULL ORDER BY year_level ASC")->fetchAll(PDO::FETCH_COLUMN);
$yearFilter = $_GET['year_level'] ?? '';
if ($yearFilter !== '' && !in_array($yearFilter, $dbYears, true)) $yearFilter = '';

// Determine Mode
$isCurrentTerm = ($syFilter === '2026-2027' && $semFilter === '1');

// ---------------------------------------------------------
// Default values to prevent IDE warnings (P1116)
// ---------------------------------------------------------
$totalStudents = 0; $sectionData = []; $meanGwa = null; $gwaSum = 0; $gwaCount = 0;
$atRiskPct = 0.0; $coveragePct = 0.0;
$riskTotals = ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0];
$sourceTotals = ['decision_tree' => 0, 'heuristic' => 0, 'none' => 0];
$distinctions = [
    'Summa-level threshold' => 0, 'Magna-level threshold' => 0,
    'Cum Laude-level threshold' => 0, 'Not currently within a distinction threshold' => 0,
    'Insufficient Data' => 0
];
$chartSecLabels = []; $chartSecHigh = []; $chartSecMod = []; $chartSecLow = []; $chartSecNone = [];
$subjectStats = []; $histSubjects = [];
$totalHistStudents = 0; $meanHistGrade = null; $histGradesSum = 0; $histGradesCount = 0; 
$histPassedCount = 0; $overallHistPassRate = 0.0;

// ---------------------------------------------------------
// MODE A: CURRENT TERM ANALYTICS (Progress & Predictions)
// ---------------------------------------------------------
if ($isCurrentTerm) {
    // 1. Fetch Current Students & Predictions
    $sqlStudents = "
        SELECT sp.user_id, sp.section, sp.current_gwa, sp.status, sp.year_level,
               p.predicted_gwa, p.risk_level, p.latin_honor, p.prediction_source
        FROM student_profiles sp
        LEFT JOIN predictions p ON p.id = (
            SELECT id FROM predictions p2 
            WHERE p2.student_id = sp.user_id 
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
        WHERE sp.section IS NOT NULL
    ";
    $paramsStudents = [];
    if ($yearFilter !== '') {
        $sqlStudents .= " AND sp.year_level = ?";
        $paramsStudents[] = $yearFilter;
    }
    $stmtStudents = $db->prepare($sqlStudents);
    $stmtStudents->execute($paramsStudents);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    // 2. Aggregate Current Data
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

        $src = $s['prediction_source'] ?? 'none';
        if (isset($sourceTotals[$src])) $sourceTotals[$src]++;

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

    // Chart Data
    $chartSecLabels = array_keys($sectionData);
    foreach ($chartSecLabels as $sec) {
        $t = $sectionData[$sec]['total'];
        $chartSecHigh[] = $t > 0 ? round(($sectionData[$sec]['risks']['HIGH'] / $t) * 100, 1) : 0;
        $chartSecMod[]  = $t > 0 ? round(($sectionData[$sec]['risks']['MODERATE'] / $t) * 100, 1) : 0;
        $chartSecLow[]  = $t > 0 ? round(($sectionData[$sec]['risks']['LOW'] / $t) * 100, 1) : 0;
        $chartSecNone[] = $t > 0 ? round(($sectionData[$sec]['risks']['NONE'] / $t) * 100, 1) : 0;
    }

    // 3. Subject Prelim Overview
    $sqlSubjects = "
        SELECT s.id, s.code, s.title, g.prelim
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.school_year = ? AND g.semester = ?
    ";
    $paramsSubjects = [$syFilter, $semFilter];
    if ($yearFilter !== '') {
        $sqlSubjects .= " AND sp.year_level = ?";
        $paramsSubjects[] = $yearFilter;
    }
    $stmtSubjects = $db->prepare($sqlSubjects);
    $stmtSubjects->execute($paramsSubjects);
    $rawGrades = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawGrades as $r) {
        $id = $r['id'];
        if (!isset($subjectStats[$id])) {
            $subjectStats[$id] = ['code' => $r['code'], 'title' => $r['title'], 'enrolled' => 0, 'graded' => 0, 'raw_sum' => 0, 'passed' => 0, 'high_risk' => 0, 'mod_risk' => 0];
        }
        $subjectStats[$id]['enrolled']++;
        if ($r['prelim'] !== null) {
            $subjectStats[$id]['graded']++;
            $subjectStats[$id]['raw_sum'] += (float)$r['prelim'];
            $pt = normalizeTermGrade($r['prelim']);
            if ($pt !== null) {
                $risk = computeRiskFromAvg($pt);
                if ($risk === 'HIGH') $subjectStats[$id]['high_risk']++;
                elseif ($risk === 'MODERATE') $subjectStats[$id]['mod_risk']++;
                if ($pt > 0) $subjectStats[$id]['passed']++; // Correct passing logic
            }
        }
    }
    usort($subjectStats, fn($a, $b) => $b['high_risk'] <=> $a['high_risk']);
}

// ---------------------------------------------------------
// MODE B: HISTORICAL TERM ANALYTICS (Completed Outcomes)
// ---------------------------------------------------------
else {
    $sqlHistorical = "
        SELECT s.id, s.code, s.title, g.final_grade, g.student_id
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.school_year = ? AND g.semester = ?
    ";
    $paramsHistorical = [$syFilter, $semFilter];
    if ($yearFilter !== '') {
        $sqlHistorical .= " AND sp.year_level = ?";
        $paramsHistorical[] = $yearFilter;
    }
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
                
                if ($pt > 0) { // Correct passing logic
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

$pageTitle = 'Program Analytics';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Students',           'students.php',  '👥'],
    ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Activity & Inbox',   'activity.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.analytics-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
.analytics-chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-bottom: 24px; }
@media (max-width: 1200px) { .analytics-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .analytics-chart-grid { grid-template-columns: 1fr; } }
@media (max-width: 700px) { .analytics-stat-grid { grid-template-columns: 1fr; } }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1 style="text-transform: uppercase; letter-spacing: 0.5px;">BSIT Program Analytics</h1>
            <p style="color: var(--text-gray);">Program-level academic patterns and decision-support indicators.</p>
        </div>
    </div>

    <!-- Wired Interactive Filters -->
    <form method="GET" action="analytics.php" class="card" style="display: flex; gap: 16px; align-items: flex-end; margin-bottom: 24px; padding: 16px 24px; flex-wrap: wrap;">
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-gray);">Academic Term Performance</label>
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
        
        <div style="display: flex; flex-direction: column; gap: 4px; border-left: 2px solid var(--border-color); padding-left: 16px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-gray);">Current Profile Year Level</label>
            <select name="year_level" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                <option value="">All Available Year Levels</option>
                <?php foreach($dbYears as $yl): ?>
                    <option value="<?= htmlspecialchars($yl) ?>" <?= $yearFilter === (string)$yl ? 'selected' : '' ?>><?= htmlspecialchars(ordinalYearLabel($yl)) ?></option>
                <?php endforeach; ?>
            </select>
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

        <!-- Current 4-Card Analytical Overview -->
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
                <h4>Prediction Coverage</h4>
                <h2 style="color: var(--text-dark);"><?= number_format($coveragePct, 1) ?>%</h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">
                    <?= $sourceTotals['decision_tree'] ?> ML | <?= $sourceTotals['heuristic'] ?> Heur. | <?= $sourceTotals['none'] ?> None
                </p>
            </div>
        </div>

        <!-- Visual Analytics Charts -->
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

        <!-- Section Comparison Table -->
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

        <!-- Subject Performance Overview (Current) -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="table-title" style="margin-bottom: 8px; color: var(--text-dark);">Current Preliminary Performance</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Identifying subjects with concentrated preliminary risk and detecting missing grade data.</p>
            <?php if (empty($subjectStats)): ?>
                <p class="empty-state">No subjects found matching the current filter scope.</p>
            <?php else: ?>
            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                    <thead>
                        <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap; width: 1%;">Subject</th>
                            <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Title</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">Students</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">Mean Prelim Grade</th>
                            <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">Current Prelim Pass Rate</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-mod); white-space: nowrap; width: 1%;">Moderate Prelim Risk</th>
                            <th style="padding: 12px; text-align: center; color: var(--risk-high); white-space: nowrap; width: 1%;">High Prelim Risk</th>
                            <th style="padding: 12px; text-align: center; color: var(--accent-blue); white-space: nowrap; width: 1%;">Prelim Data Completeness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectStats as $id => $s): 
                            $meanGrade = $s['graded'] > 0 ? round($s['raw_sum'] / $s['graded']) . '%' : '—';
                            $passRate = $s['graded'] > 0 ? number_format(($s['passed'] / $s['graded']) * 100, 1) . '%' : '—';
                            $completeness = $s['enrolled'] > 0 ? number_format(($s['graded'] / $s['enrolled']) * 100, 1) . '%' : '0%';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px; font-weight: 700; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= htmlspecialchars($s['code']) ?></td>
                            <td title="<?= htmlspecialchars($s['title']) ?>" style="padding: 12px; color: var(--text-gray); max-width: 0; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($s['title']) ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= $s['enrolled'] ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= $meanGrade ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-dark); white-space: nowrap; width: 1%;"><?= $passRate ?></td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">
                                <?php if($s['mod_risk'] > 0): ?><span class="badge" style="background: var(--risk-mod);"><?= $s['mod_risk'] ?></span><?php else: ?><span style="color: var(--text-gray);">0</span><?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap; width: 1%;">
                                <?php if($s['high_risk'] > 0): ?><span class="badge" style="background: var(--risk-high);"><?= $s['high_risk'] ?></span><?php else: ?><span style="color: var(--text-gray);">0</span><?php endif; ?>
                            </td>
                            <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--accent-blue); white-space: nowrap; width: 1%;"><?= $completeness ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
    <!-- ========================================== -->
    <!-- MODE B: HISTORICAL TERM (Completed Analytics)-->
    <!-- ========================================== -->
    <?php else: ?>
        
        <div style="background: rgba(13, 110, 253, 0.1); border-left: 4px solid var(--accent-blue); padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; color: var(--text-dark); font-size: 0.9rem;">
            <strong>Historical outcome view:</strong> Section and year-level comparisons are unavailable because the current database does not preserve each student’s section and year level for prior academic terms. The results below summarize official subject outcomes for the selected school year and semester.
        </div>

        <!-- Historical 4-Card Overview -->
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
                <h2 style="color: var(--text-dark);"><?= number_format($overallHistPassRate, 1) ?>%</h2>
                <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;"><?= $histPassedCount ?> passing grades recorded</p>
            </div>
        </div>

        <!-- Subject Performance Overview (Historical) -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="table-title" style="margin-bottom: 8px; color: var(--text-dark);">Completed Subject Outcomes</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Historical analysis based on official Final Grades.</p>
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
                            $meanGrade = $s['graded'] > 0 ? number_format($s['raw_sum'] / $s['graded'], 2) : '—';
                            $passRate = $s['graded'] > 0 ? number_format(($s['passed'] / $s['graded']) * 100, 1) . '%' : '—';
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

<?php if ($isCurrentTerm && !empty($sectionData)): ?>
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