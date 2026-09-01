<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

// Safety fallback with explicit type-hints
if (!function_exists('normalizePointGrade')) {
    function normalizePointGrade(mixed $val): ?float {
        if ($val === null || trim((string)$val) === '') return null;
        return (float)$val;
    }
}

$currentTerm = getCurrentTerm();
$currentSy = $currentTerm['school_year'];
$currentSem = (string) $currentTerm['semester'];

$stmtLoads = $db->prepare("
    SELECT fcl.subject_id, fcl.section, s.code, s.title 
    FROM faculty_class_loads fcl 
    JOIN subjects s ON s.id = fcl.subject_id 
    WHERE fcl.faculty_user_id = ?
    ORDER BY s.title, fcl.section
");
$stmtLoads->execute([$user['id']]);
$myLoads = $stmtLoads->fetchAll(PDO::FETCH_ASSOC);

$uniqueStudents = [];
$overallAttention = 0;
$overallExpected = 0; $overallEncoded = 0;
$portfolioTable = [];

$overallPctSum = 0; $overallPctCount = 0;
$overallPtSum = 0; $overallPtCount = 0;

foreach ($myLoads as $load) {
    $stmtClassGrades = $db->prepare("
        SELECT g.student_id, g.prelim, g.midterm, g.prefinal, g.final_grade
        FROM grades g
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.subject_id = ? AND sp.section = ? AND g.school_year = ? AND g.semester = ? AND g.is_current = 1
    ");
    $stmtClassGrades->execute([$load['subject_id'], $load['section'], $currentSy, $currentSem]);
    $grades = $stmtClassGrades->fetchAll(PDO::FETCH_ASSOC);

    $classStudents = count($grades);
    $overallExpected += $classStudents;

    $classPctSum = 0; $classPctCount = 0;
    $classPtSum = 0; $classPtCount = 0;
    $classAttention = 0;

    foreach ($grades as $g) {
        $uniqueStudents[$g['student_id']] = true;

        $latestVal = null;
        $latestType = '';
        if ($g['final_grade'] !== null && trim((string)$g['final_grade']) !== '') { $latestVal = $g['final_grade']; $latestType = 'final_grade'; }
        elseif ($g['prefinal'] !== null && trim((string)$g['prefinal']) !== '') { $latestVal = $g['prefinal']; $latestType = 'prefinal'; }
        elseif ($g['midterm'] !== null && trim((string)$g['midterm']) !== '') { $latestVal = $g['midterm']; $latestType = 'midterm'; }
        elseif ($g['prelim'] !== null && trim((string)$g['prelim']) !== '') { $latestVal = $g['prelim']; $latestType = 'prelim'; }

        if ($latestVal !== null) {
            $overallEncoded++;

            if ($latestType === 'final_grade') {
                if (in_array(strtoupper(trim((string)$latestVal)), ['INC', 'DO', 'DU', 'FA', 'UD', '0', '0.00'])) {
                    $classAttention++;
                    $overallAttention++;
                } else {
                    $pt = normalizePointGrade($latestVal);
                    if ($pt !== null) {
                        $classPtSum += $pt; $classPtCount++;
                        $overallPtSum += $pt; $overallPtCount++;
                        $risk = computeRiskFromAvg($pt);
                        if ($risk === 'HIGH' || $risk === 'MODERATE') {
                            $classAttention++;
                            $overallAttention++;
                        }
                    }
                }
            } else {
                $pct = (float)$latestVal;
                $classPctSum += $pct; $classPctCount++;
                $overallPctSum += $pct; $overallPctCount++;
                
                $pt = normalizeTermGrade($pct);
                if ($pt !== null) {
                    $risk = computeRiskFromAvg($pt);
                    if ($risk === 'HIGH' || $risk === 'MODERATE') {
                        $classAttention++;
                        $overallAttention++;
                    }
                }
            }
        }
    }

    $meanStr = '—';
    if ($classPtCount > 0 && $classPctCount > 0) {
        $meanStr = number_format($classPtSum / $classPtCount, 2) . ' / ' . round($classPctSum / $classPctCount) . '%';
    } elseif ($classPtCount > 0) {
        $meanStr = number_format($classPtSum / $classPtCount, 2);
    } elseif ($classPctCount > 0) {
        $meanStr = round($classPctSum / $classPctCount) . '%';
    }

    // FIXED: Use Title instead of Code for the Comparison Table
    $portfolioTable[] = [
        'title' => $load['title'],
        'section' => $load['section'],
        'students' => $classStudents,
        'mean' => $meanStr,
        'attention' => $classAttention,
        'completeness' => $classStudents > 0 ? (($classPtCount + $classPctCount) / $classStudents) * 100 : 0
    ];
}

$uniqueStudentsCount = count($uniqueStudents);
$overallCompletenessPct = $overallExpected > 0 ? ($overallEncoded / $overallExpected) * 100 : 0;

$overallMeanStr = '—';
if ($overallPtCount > 0 && $overallPctCount > 0) {
    $overallMeanStr = number_format($overallPtSum / $overallPtCount, 2) . ' / ' . round($overallPctSum / $overallPctCount) . '%';
} elseif ($overallPtCount > 0) {
    $overallMeanStr = number_format($overallPtSum / $overallPtCount, 2);
} elseif ($overallPctCount > 0) {
    $overallMeanStr = round($overallPctSum / $overallPctCount) . '%';
}

$loadFilter = $_GET['load'] ?? '';
$subjFilter = null; $secFilter = null;

if (!empty($myLoads)) {
    if ($loadFilter === '') {
        $subjFilter = $myLoads[0]['subject_id'];
        $secFilter = $myLoads[0]['section'];
        $loadFilter = $subjFilter . '|' . $secFilter;
    } else {
        $parts = explode('|', $loadFilter);
        if (count($parts) === 2) {
            $subjFilter = $parts[0];
            $secFilter = $parts[1];
        }
    }
}

$enrolledCount = 0; $latestMean = null; $latestPeriodName = 'Preliminary';
$attentionCount = 0; $completenessPct = 0;
$periods = ['Prelim', 'Midterm', 'Pre-Final', 'Final'];

$periodMeans = ['Prelim' => null, 'Midterm' => null, 'Pre-Final' => null, 'Final' => null];
$chartMeans  = ['Prelim' => null, 'Midterm' => null, 'Pre-Final' => null, 'Final' => null];
$periodEncoded = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];

$riskMovement = [
    'Prelim'    => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
    'Midterm'   => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
    'Pre-Final' => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
    'Final'     => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0]
];
$significantChanges = [];

if ($subjFilter && $secFilter) {
    $stmtGrades = $db->prepare("
        SELECT g.student_id, u.first_name, u.last_name,
               g.prelim, g.midterm, g.prefinal, g.final_grade
        FROM grades g
        JOIN users u ON u.id = g.student_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.subject_id = ? AND sp.section = ? AND g.school_year = ? AND g.semester = ? AND g.is_current = 1
    ");
    $stmtGrades->execute([$subjFilter, $secFilter, $currentSy, $currentSem]);
    $grades = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

    $enrolledCount = count($grades);
    
    $rawSums = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];
    $ptSums  = ['Prelim' => 0, 'Midterm' => 0, 'Pre-Final' => 0, 'Final' => 0];

    foreach ($grades as $g) {
        $stuName = formatNameLastFirst($g['first_name'], '', $g['last_name']);

        $pMap = ['Prelim' => 'prelim', 'Midterm' => 'midterm', 'Pre-Final' => 'prefinal', 'Final' => 'final_grade'];
        foreach ($pMap as $pName => $dbCol) {
            if ($g[$dbCol] !== null && trim((string)$g[$dbCol]) !== '') {
                $periodEncoded[$pName]++;
                
                if ($dbCol === 'final_grade') {
                    $val = trim(strtoupper($g[$dbCol]));
                    if (in_array($val, ['INC', 'DO', 'DU', 'FA', 'UD', '0', '0.00'])) {
                        $riskMovement[$pName]['HIGH']++;
                        continue;
                    }
                    $pt = normalizePointGrade($val);
                    if ($pt !== null) {
                        $rawSums[$pName] += $pt;
                        $ptSums[$pName] += $pt;
                        $riskMovement[$pName][computeRiskFromAvg($pt)]++;
                    }
                } else {
                    $pct = (float)$g[$dbCol];
                    $rawSums[$pName] += $pct;
                    $pt = normalizeTermGrade($pct);
                    if ($pt !== null) {
                        $ptSums[$pName] += $pt;
                        $riskMovement[$pName][computeRiskFromAvg($pt)]++;
                    }
                }
            } else {
                $riskMovement[$pName]['NONE']++;
            }
        }

        if ($g['prelim'] !== null && $g['midterm'] !== null && trim((string)$g['prelim']) !== '' && trim((string)$g['midterm']) !== '') {
            $pPct = (float)$g['prelim'];
            $mPct = (float)$g['midterm'];
            $diff = $mPct - $pPct;
            
            if (abs($diff) >= 5.0) { 
                $ptTerm = normalizeTermGrade($mPct) ?? 0.0;
                $significantChanges[] = [
                    'name' => $stuName,
                    'diff' => $diff,
                    'current_risk' => computeRiskFromAvg($ptTerm)
                ];
            }
        }
    }

    foreach ($periods as $p) {
        if ($periodEncoded[$p] > 0) {
            $periodMeans[$p] = $rawSums[$p] / $periodEncoded[$p];
            $chartMeans[$p]  = round($ptSums[$p] / $periodEncoded[$p], 2);
        }
    }

    if ($periodEncoded['Final'] > 0) {
        $latestPeriodName = 'Final';
        $latestMean = number_format((float) $periodMeans['Final'], 2);
        $attentionCount = $riskMovement['Final']['HIGH'] + $riskMovement['Final']['MODERATE'];
        $completenessPct = ($periodEncoded['Final'] / $enrolledCount) * 100;
    } elseif ($periodEncoded['Pre-Final'] > 0) {
        $latestPeriodName = 'Pre-Final';
        $latestMean = round((float) $periodMeans['Pre-Final']) . '%';
        $attentionCount = $riskMovement['Pre-Final']['HIGH'] + $riskMovement['Pre-Final']['MODERATE'];
        $completenessPct = ($periodEncoded['Pre-Final'] / $enrolledCount) * 100;
    } elseif ($periodEncoded['Midterm'] > 0) {
        $latestPeriodName = 'Midterm';
        $latestMean = round((float) $periodMeans['Midterm']) . '%';
        $attentionCount = $riskMovement['Midterm']['HIGH'] + $riskMovement['Midterm']['MODERATE'];
        $completenessPct = ($periodEncoded['Midterm'] / $enrolledCount) * 100;
    } elseif ($periodEncoded['Prelim'] > 0) {
        $latestPeriodName = 'Preliminary';
        $latestMean = round((float) $periodMeans['Prelim']) . '%';
        $attentionCount = $riskMovement['Prelim']['HIGH'] + $riskMovement['Prelim']['MODERATE'];
        $completenessPct = ($periodEncoded['Prelim'] / $enrolledCount) * 100;
    }

    usort($significantChanges, fn($a, $b) => $a['diff'] <=> $b['diff']);
}

$chartLineData = [
    $chartMeans['Prelim'] ?? null, 
    $chartMeans['Midterm'] ?? null, 
    $chartMeans['Pre-Final'] ?? null, 
    $chartMeans['Final'] ?? null
];

$pageTitle = 'Class Performance Trends';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Encode Grades',      'grades.php',    '📝'],
    ['Concerns & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.analytics-stat-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
.class-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
.analytics-chart-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-bottom: 24px; }
@media (max-width: 1400px) { .analytics-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1200px) { .class-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .analytics-chart-grid { grid-template-columns: 1fr; } }
@media (max-width: 800px) { .analytics-stat-grid { grid-template-columns: 1fr; } .class-stat-grid { grid-template-columns: 1fr; } }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="text-transform: uppercase; letter-spacing: 0.5px;">Class Performance Trends</h1>
            <p style="color: var(--text-gray);">Performance progression and risk movement across grading periods for the selected class.</p>
        </div>
        <div style="background: var(--card-bg); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 8px; text-align: center;">
            <div style="font-size: 0.75rem; color: var(--text-gray); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Current Academic Term</div>
            <div style="font-size: 1rem; color: var(--text-dark); font-weight: 700;">S.Y. <?= htmlspecialchars($currentSy) ?>, <?= $currentSem === '1' ? 'First' : 'Second' ?> Semester</div>
        </div>
    </div>

    <?php if (empty($myLoads)): ?>
        <div class="card"><p class="empty-state">No section class loads assigned to your account for the current term.</p></div>
    <?php else: ?>

    <h2 style="font-size: 1.15rem; color: var(--text-dark); margin-bottom: 24px; font-weight: 700; text-transform: uppercase;">Assigned Classes Overview</h2>

    <div class="analytics-stat-grid">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Class Loads</h4>
            <h2 style="color: var(--text-dark);"><?= count($myLoads) ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Assigned subject-sections</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--text-dark);">
            <h4>Unique Students</h4>
            <h2 style="color: var(--text-dark);"><?= $uniqueStudentsCount ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Distinct learners reached</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-low);">
            <h4>Overall Mean Grade</h4>
            <h2 style="color: var(--risk-low);"><?= $overallMeanStr ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Across latest encoded periods</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Requiring Attention</h4>
            <h2 style="color: <?= $overallAttention > 0 ? 'var(--risk-mod)' : 'var(--text-dark)' ?>;"><?= $overallAttention ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Students at Moderate/High risk</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--text-gray);">
            <h4>Grade Completeness</h4>
            <h2 style="color: var(--text-dark);"><?= number_format($overallCompletenessPct, 1) ?>%</h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Latest period encoded</p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 40px;">
        <div class="table-title" style="margin-bottom: 16px; color: var(--text-dark);">Assigned Class-Load Comparison</div>
        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                <thead>
                    <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Subject</th>
                        <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Students</th>
                        <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Latest Mean</th>
                        <th style="padding: 12px; text-align: center; color: var(--risk-mod); white-space: nowrap;">Attention Required</th>
                        <th style="padding: 12px; text-align: center; color: var(--accent-blue); white-space: nowrap;">Completeness</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($portfolioTable as $pt): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <!-- FIXED: Table uses title instead of code, limits width to prevent stretching -->
                        <td style="padding: 12px; font-weight: 700; color: var(--text-dark); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($pt['title']) ?>"><?= htmlspecialchars($pt['title']) ?> <span style="color: var(--text-gray); font-weight: normal; margin-left: 6px;">— <?= htmlspecialchars($pt['section']) ?></span></td>
                        <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?= $pt['students'] ?></td>
                        <td style="padding: 12px; text-align: center; color: var(--text-dark); font-weight: 600;"><?= $pt['mean'] ?></td>
                        <td style="padding: 12px; text-align: center; color: var(--text-dark);"><?php if($pt['attention']>0): ?><span class="badge" style="background: var(--risk-mod);"><?= $pt['attention'] ?></span><?php else: ?><span style="color: var(--text-gray);">0</span><?php endif; ?></td>
                        <td style="padding: 12px; text-align: center; color: var(--text-dark); font-weight: 600;"><?= number_format($pt['completeness'], 1) ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 32px 0;">

    <h2 style="font-size: 1.15rem; color: var(--text-dark); margin-bottom: 16px; font-weight: 700; text-transform: uppercase;">Selected Class Analysis</h2>

    <form method="GET" action="trend.php" class="card" style="display: flex; gap: 16px; align-items: flex-end; margin-bottom: 24px; padding: 16px 24px; flex-wrap: wrap;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <label style="font-size: 0.8rem; font-weight: 600; color: var(--text-gray);">Assigned Class Load</label>
            <select name="load" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-dark); font-family: inherit; min-width: 250px;">
                <?php foreach($myLoads as $l): 
                    $val = $l['subject_id'] . '|' . $l['section'];
                    // FIXED: Dropdown uses Title instead of Code
                    $label = $l['title'] . ' — ' . $l['section'];
                ?>
                    <option value="<?= htmlspecialchars($val) ?>" <?= $loadFilter === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; height: 100%;">
            <button type="submit" style="background: var(--accent-blue); color: white; border: none; padding: 9px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; transition: opacity 0.2s;">
                Analyze Class
            </button>
        </div>
    </form>

    <div class="class-stat-grid">
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Students Enrolled</h4>
            <h2 style="color: var(--text-dark);"><?= $enrolledCount ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">In selected class</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-low);">
            <h4>Latest Period Mean</h4>
            <h2 style="color: var(--risk-low);"><?= $latestMean !== null ? $latestMean : '—' ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;"><?= htmlspecialchars($latestPeriodName) ?> grades</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Requiring Attention</h4>
            <h2 style="color: <?= $attentionCount > 0 ? 'var(--risk-mod)' : 'var(--text-dark)' ?>;"><?= $attentionCount ?></h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Moderate & High Risk</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--text-gray);">
            <h4>Data Completeness</h4>
            <h2 style="color: var(--text-dark);"><?= number_format($completenessPct, 1) ?>%</h2>
            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;"><?= htmlspecialchars($latestPeriodName) ?> period encoded</p>
        </div>
    </div>

    <?php if ($periodEncoded['Midterm'] == 0 && $periodEncoded['Prelim'] > 0): ?>
        <div style="background: rgba(13, 110, 253, 0.1); border-left: 4px solid var(--accent-blue); padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; color: var(--text-dark); font-size: 0.9rem;">
            <strong>Baseline Analysis:</strong> Trend analysis will become available when at least two grading periods have been encoded. Current Preliminary performance is shown as the baseline.
        </div>
    <?php endif; ?>

    <div class="analytics-chart-grid">
        <div class="card" style="position: relative; height: 380px;">
            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">Estimated Grade Point Trajectory</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Tracking the cohort's academic projection by converting percentages to their final point equivalents.</p>
            <div style="position: relative; height: 280px; width: 100%;"><canvas id="progressionChart"></canvas></div>
        </div>
        
        <div class="card" style="position: relative; height: 380px;">
            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">Risk Distribution Across Grading Periods</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Monitoring whether the class is accumulating academic risk over time.</p>
            <div style="position: relative; height: 280px; width: 100%;"><canvas id="riskMovementChart"></canvas></div>
        </div>
    </div>

    <div class="analytics-chart-grid">
        <div class="card">
            <div class="table-title" style="margin-bottom: 16px; color: var(--text-dark);">Grade Encoding Progress</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Total encoded records per period out of <?= $enrolledCount ?> enrolled students.</p>
            
            <?php foreach ($periods as $p): 
                $pct = $enrolledCount > 0 ? ($periodEncoded[$p] / $enrolledCount) * 100 : 0;
                $color = $pct === 100 ? 'var(--risk-low)' : ($pct > 0 ? 'var(--risk-mod)' : 'var(--border-color)');
            ?>
            <div style="display: flex; align-items: center; margin-bottom: 12px; font-size: 0.9rem;">
                <span style="width: 80px; font-weight: 600; color: var(--text-dark);"><?= $p ?></span>
                <div style="flex: 1; max-width: 280px; height: 12px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 4px; margin: 0 16px; overflow: hidden;">
                    <div style="width: <?= $pct ?>%; height: 100%; background: <?= $color ?>; border-radius: 4px;"></div>
                </div>
                <span style="color: var(--text-dark); font-weight: 600; font-size: 0.85rem;">
                    <?= $periodEncoded[$p] ?> / <?= $enrolledCount ?> <span style="color: var(--text-gray); font-weight: normal;">encoded</span>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="table-title" style="margin-bottom: 16px; color: var(--text-dark);">Students With Significant Changes</div>
            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Students dropping or improving by ≥ 5% between Prelim and Midterm.</p>
            
            <?php if ($periodEncoded['Midterm'] == 0): ?>
                <p class="empty-state" style="margin-top: 40px;">Requires Midterm data to compare.</p>
            <?php elseif (empty($significantChanges)): ?>
                <p class="empty-state" style="margin-top: 40px;">No students shifted by ≥ 5%.</p>
            <?php else: ?>
            <div style="overflow-y: auto; max-height: 220px; padding-right: 8px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 8px; text-align: left; color: var(--text-dark); position: sticky; top: 0; background: var(--card-bg);">Student</th>
                            <th style="padding: 8px; text-align: center; color: var(--text-dark); position: sticky; top: 0; background: var(--card-bg);">Change</th>
                            <th style="padding: 8px; text-align: left; color: var(--text-dark); position: sticky; top: 0; background: var(--card-bg);">Current Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($significantChanges as $stu): 
                            $diffColor = $stu['diff'] > 0 ? 'var(--risk-low)' : 'var(--risk-high)';
                            $diffSign = $stu['diff'] > 0 ? '↑ +' : '↓ '; 
                            
                            $riskRaw = $stu['current_risk'];
                            $riskColor = $riskRaw === 'HIGH' ? 'var(--risk-high)' : ($riskRaw === 'MODERATE' ? 'var(--risk-mod)' : 'var(--risk-low)');
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 8px; color: var(--text-dark); font-weight: 600;"><?= htmlspecialchars($stu['name']) ?></td>
                            <td style="padding: 10px 8px; text-align: center; font-weight: 700; color: <?= $diffColor ?>;">
                                <?= $diffSign . number_format(abs($stu['diff']), 1) ?>%
                            </td>
                            <td style="padding: 10px 8px; color: <?= $riskColor ?>; font-weight: 600; font-size: 0.85rem;">
                                <?= htmlspecialchars($riskRaw) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php if (!empty($myLoads)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function getChartTheme() {
    const root = getComputedStyle(document.documentElement);
    return {
        text: root.getPropertyValue('--text-gray').trim(),
        border: root.getPropertyValue('--border-color').trim(),
        blue: root.getPropertyValue('--accent-blue').trim(),
        none: '#64748B', high: '#DC2626', mod: '#D97706', low: '#059669'
    };
}
let theme = getChartTheme();

const ctxProg = document.getElementById('progressionChart').getContext('2d');
const progChart = new Chart(ctxProg, {
    type: 'line',
    data: {
        labels: <?= json_encode($periods) ?>,
        datasets: [{
            label: 'Estimated Grade Point',
            data: <?= json_encode($chartLineData) ?>,
            borderColor: theme.blue,
            backgroundColor: 'rgba(108, 142, 239, 0.1)',
            borderWidth: 3,
            pointBackgroundColor: theme.blue,
            pointRadius: 6,
            pointHoverRadius: 8,
            fill: true,
            tension: 0.1,
            spanGaps: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: theme.text }, grid: { color: theme.border } },
            y: { 
                min: 1.0, max: 4.0, 
                ticks: { stepSize: 0.5, color: theme.text }, 
                grid: { color: theme.border },
                title: { display: true, text: 'Grade Point Equivalent (1.00-4.00)', color: theme.text }
            }
        }
    }
});

const rawRiskData = <?= json_encode(array_values($riskMovement)) ?>;
const ctxRisk = document.getElementById('riskMovementChart').getContext('2d');
const riskChart = new Chart(ctxRisk, {
    type: 'bar',
    data: {
        labels: <?= json_encode($periods) ?>,
        datasets: [
            { label: 'High Risk', data: rawRiskData.map(r => r.HIGH), backgroundColor: theme.high, stack: 'Stack 0' },
            { label: 'Moderate Risk', data: rawRiskData.map(r => r.MODERATE), backgroundColor: theme.mod, stack: 'Stack 0' },
            { label: 'Low Risk', data: rawRiskData.map(r => r.LOW), backgroundColor: theme.low, stack: 'Stack 0' },
            { label: 'No Data', data: rawRiskData.map(r => r.NONE), backgroundColor: theme.none, stack: 'Stack 0' }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: theme.text, usePointStyle: true, boxWidth: 8 } } },
        scales: {
            x: { stacked: true, ticks: { color: theme.text }, grid: { display: false } },
            y: { stacked: true, ticks: { stepSize: 1, color: theme.text }, grid: { color: theme.border }, title: { display: true, text: 'Number of Students', color: theme.text } }
        }
    }
});

const observer = new MutationObserver(() => {
    theme = getChartTheme();
    
    progChart.options.scales.x.ticks.color = theme.text;
    progChart.options.scales.x.grid.color = theme.border;
    progChart.options.scales.y.ticks.color = theme.text;
    progChart.options.scales.y.grid.color = theme.border;
    progChart.options.scales.y.title.color = theme.text;
    progChart.data.datasets[0].borderColor = theme.blue;
    progChart.data.datasets[0].pointBackgroundColor = theme.blue;
    progChart.update();

    riskChart.options.scales.x.ticks.color = theme.text;
    riskChart.options.scales.y.ticks.color = theme.text;
    riskChart.options.scales.y.grid.color = theme.border;
    riskChart.options.scales.y.title.color = theme.text;
    riskChart.options.plugins.legend.labels.color = theme.text;
    riskChart.update();
});
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>