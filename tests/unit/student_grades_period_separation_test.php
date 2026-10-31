<?php
/**
 * Pure Unit Regression Suite for Academic Period Separation & Classification
 *
 * Verifies:
 * 1. normalizeSemester() handling of all valid aliases and rejection of invalid values
 * 2. normalizeSchoolYear() strict format and consecutive-year validation
 * 3. periodChronologicalRank() ordering across prior, current, and future terms
 * 4. isHistoricalPeriod(), isCurrentPeriod(), and isFuturePeriod() classification
 * 5. isFinalizedHistoricalGrade() validation of numeric points and institutional statuses
 * 6. determineCurrentSubjectStatus() stage descriptions for current-term subjects
 * 7. Academic history isolation: strict exclusion of active term, future terms, and placeholder rows
 * 8. Current term query and status contract without reliance on drifted flags
 * 9. Supportive empty-state and status copy separation
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/grade_history.php';

$testCount = 0;
$failCount = 0;

function assertPeriodCheck(bool $condition, string $message): void {
    global $testCount, $failCount;
    $testCount++;
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        $failCount++;
        echo "[FAIL] $message\n";
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: STUDENT GRADES PERIOD SEPARATION & CLASSIFICATION TESTS\n";
echo "========================================================================\n\n";

$activeTerm = ['school_year' => '2026-2027', 'semester' => 1];

// -----------------------------------------------------------------------------
// 1. Semester Normalization & Aliases
// -----------------------------------------------------------------------------
echo "=== 1. Semester Normalization & Aliases ===\n";

assertPeriodCheck(normalizeSemester(1) === 1, "normalizeSemester: integer 1 -> 1");
assertPeriodCheck(normalizeSemester('1') === 1, "normalizeSemester: string '1' -> 1");
assertPeriodCheck(normalizeSemester('Sem 1') === 1, "normalizeSemester: 'Sem 1' -> 1");
assertPeriodCheck(normalizeSemester('Semester 1') === 1, "normalizeSemester: 'Semester 1' -> 1");
assertPeriodCheck(normalizeSemester('First Semester') === 1, "normalizeSemester: 'First Semester' -> 1");
assertPeriodCheck(normalizeSemester('1st Sem') === 1, "normalizeSemester: '1st Sem' -> 1");
assertPeriodCheck(normalizeSemester('  first semester  ') === 1, "normalizeSemester: whitespace trimming -> 1");

assertPeriodCheck(normalizeSemester(2) === 2, "normalizeSemester: integer 2 -> 2");
assertPeriodCheck(normalizeSemester('2') === 2, "normalizeSemester: string '2' -> 2");
assertPeriodCheck(normalizeSemester('Sem 2') === 2, "normalizeSemester: 'Sem 2' -> 2");
assertPeriodCheck(normalizeSemester('Semester 2') === 2, "normalizeSemester: 'Semester 2' -> 2");
assertPeriodCheck(normalizeSemester('Second Semester') === 2, "normalizeSemester: 'Second Semester' -> 2");
assertPeriodCheck(normalizeSemester('2nd Sem') === 2, "normalizeSemester: '2nd Sem' -> 2");

assertPeriodCheck(normalizeSemester(3) === 3, "normalizeSemester: integer 3 -> 3");
assertPeriodCheck(normalizeSemester('3') === 3, "normalizeSemester: string '3' -> 3");
assertPeriodCheck(normalizeSemester('Summer') === 3, "normalizeSemester: 'Summer' -> 3");
assertPeriodCheck(normalizeSemester('Midyear') === 3, "normalizeSemester: 'Midyear' -> 3");
assertPeriodCheck(normalizeSemester('Sem 3') === 3, "normalizeSemester: 'Sem 3' -> 3");
assertPeriodCheck(normalizeSemester('Semester 3') === 3, "normalizeSemester: 'Semester 3' -> 3");

assertPeriodCheck(normalizeSemester('4') === null, "normalizeSemester: invalid '4' -> null");
assertPeriodCheck(normalizeSemester('Trimester') === null, "normalizeSemester: unrecognized string -> null");
assertPeriodCheck(normalizeSemester('') === null, "normalizeSemester: empty string -> null");
assertPeriodCheck(normalizeSemester(null) === null, "normalizeSemester: null -> null");

// -----------------------------------------------------------------------------
// 2. School Year Normalization
// -----------------------------------------------------------------------------
echo "\n=== 2. School Year Normalization ===\n";

assertPeriodCheck(normalizeSchoolYear('2026-2027') === '2026-2027', "normalizeSchoolYear: '2026-2027' valid");
assertPeriodCheck(normalizeSchoolYear('  2025-2026  ') === '2025-2026', "normalizeSchoolYear: whitespace trimmed");
assertPeriodCheck(normalizeSchoolYear('2026-2028') === null, "normalizeSchoolYear: non-consecutive years -> null");
assertPeriodCheck(normalizeSchoolYear('2026') === null, "normalizeSchoolYear: single year -> null");
assertPeriodCheck(normalizeSchoolYear('invalid') === null, "normalizeSchoolYear: text -> null");
assertPeriodCheck(normalizeSchoolYear('') === null, "normalizeSchoolYear: empty -> null");
assertPeriodCheck(normalizeSchoolYear(null) === null, "normalizeSchoolYear: null -> null");

// -----------------------------------------------------------------------------
// 3. Chronological Ranking & Strict Period Order
// -----------------------------------------------------------------------------
echo "\n=== 3. Chronological Ranking & Strict Period Order ===\n";

$rankPrior1 = periodChronologicalRank('2025-2026', 1);
$rankPrior2 = periodChronologicalRank('2025-2026', 2);
$rankPriorSummer = periodChronologicalRank('2025-2026', 'Summer');
$rankActive = periodChronologicalRank('2026-2027', 1);
$rankFutureSem2 = periodChronologicalRank('2026-2027', 2);
$rankFutureSummer = periodChronologicalRank('2026-2027', 3);
$rankNextYear = periodChronologicalRank('2027-2028', 1);

assertPeriodCheck($rankPrior1 !== null && $rankPrior1 === (2025 * 3 + 1), "periodChronologicalRank: 2025-2026 Sem 1 = 6076");
assertPeriodCheck($rankPrior2 !== null && $rankPrior2 === (2025 * 3 + 2), "periodChronologicalRank: 2025-2026 Sem 2 = 6077");
assertPeriodCheck($rankPriorSummer !== null && $rankPriorSummer === (2025 * 3 + 3), "periodChronologicalRank: 2025-2026 Summer = 6078");
assertPeriodCheck($rankActive !== null && $rankActive === (2026 * 3 + 1), "periodChronologicalRank: 2026-2027 Sem 1 = 6079");
assertPeriodCheck($rankFutureSem2 !== null && $rankFutureSem2 === (2026 * 3 + 2), "periodChronologicalRank: 2026-2027 Sem 2 = 6080");
assertPeriodCheck($rankFutureSummer !== null && $rankFutureSummer === (2026 * 3 + 3), "periodChronologicalRank: 2026-2027 Summer = 6081");
assertPeriodCheck($rankNextYear !== null && $rankNextYear === (2027 * 3 + 1), "periodChronologicalRank: 2027-2028 Sem 1 = 6082");

assertPeriodCheck(
    $rankPrior1 < $rankPrior2 &&
    $rankPrior2 < $rankPriorSummer &&
    $rankPriorSummer < $rankActive &&
    $rankActive < $rankFutureSem2 &&
    $rankFutureSem2 < $rankFutureSummer &&
    $rankFutureSummer < $rankNextYear,
    "Strict monotonic chronological ordering preserved across 7 consecutive periods"
);

assertPeriodCheck(periodChronologicalRank('invalid', 1) === null, "Malformed school year produces null rank");
assertPeriodCheck(periodChronologicalRank('2026-2027', 'invalid') === null, "Malformed semester produces null rank");
assertPeriodCheck(periodChronologicalRank(null, null) === null, "Undated row produces null rank");

// -----------------------------------------------------------------------------
// 4. Period Classification: Historical, Current, Future
// -----------------------------------------------------------------------------
echo "\n=== 4. Period Classification Against Active Term (2026-2027 Sem 1) ===\n";

// Current Semester 1
assertPeriodCheck(isCurrentPeriod('2026-2027', 1, $activeTerm) === true, "Current Sem 1 is current");
assertPeriodCheck(isHistoricalPeriod('2026-2027', 1, $activeTerm) === false, "Current Sem 1 is NOT historical");
assertPeriodCheck(isFuturePeriod('2026-2027', 1, $activeTerm) === false, "Current Sem 1 is NOT future");

// Future Semester 2
assertPeriodCheck(isFuturePeriod('2026-2027', 2, $activeTerm) === true, "Future Sem 2 is future");
assertPeriodCheck(isHistoricalPeriod('2026-2027', 2, $activeTerm) === false, "Future Sem 2 is NOT historical");
assertPeriodCheck(isCurrentPeriod('2026-2027', 2, $activeTerm) === false, "Future Sem 2 is NOT current");

// Future Summer
assertPeriodCheck(isFuturePeriod('2026-2027', 'Summer', $activeTerm) === true, "Future Summer is future");
assertPeriodCheck(isHistoricalPeriod('2026-2027', 'Summer', $activeTerm) === false, "Future Summer is NOT historical");

// Prior Semester 2
assertPeriodCheck(isHistoricalPeriod('2025-2026', 2, $activeTerm) === true, "Prior Sem 2 is historical");
assertPeriodCheck(isCurrentPeriod('2025-2026', 2, $activeTerm) === false, "Prior Sem 2 is NOT current");
assertPeriodCheck(isFuturePeriod('2025-2026', 2, $activeTerm) === false, "Prior Sem 2 is NOT future");

// Prior Semester 1
assertPeriodCheck(isHistoricalPeriod('2025-2026', 1, $activeTerm) === true, "Prior Sem 1 is historical");
assertPeriodCheck(isCurrentPeriod('2025-2026', 1, $activeTerm) === false, "Prior Sem 1 is NOT current");
assertPeriodCheck(isFuturePeriod('2025-2026', 1, $activeTerm) === false, "Prior Sem 1 is NOT future");

// Malformed / Undated rows
assertPeriodCheck(isHistoricalPeriod('invalid', 1, $activeTerm) === false, "Malformed period rejected from historical");
assertPeriodCheck(isHistoricalPeriod(null, null, $activeTerm) === false, "Undated row rejected from historical");
assertPeriodCheck(isCurrentPeriod(null, null, $activeTerm) === false, "Undated row rejected from current");
assertPeriodCheck(isFuturePeriod(null, null, $activeTerm) === false, "Undated row rejected from future");

// -----------------------------------------------------------------------------
// 5. Finalized Historical Grade Outcomes
// -----------------------------------------------------------------------------
echo "\n=== 5. Finalized Historical Grade Outcomes ===\n";

assertPeriodCheck(isFinalizedHistoricalGrade('4.00') === true, "4.00 is finalized numeric grade");
assertPeriodCheck(isFinalizedHistoricalGrade('3.25') === true, "3.25 is finalized numeric grade");
assertPeriodCheck(isFinalizedHistoricalGrade('1.00') === true, "1.00 is finalized numeric grade");
assertPeriodCheck(isFinalizedHistoricalGrade('0.00') === true, "0.00 is finalized failing point grade");
assertPeriodCheck(isFinalizedHistoricalGrade(3.50) === true, "Float 3.50 is finalized numeric grade");

assertPeriodCheck(isFinalizedHistoricalGrade('INC') === true, "INC is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('DRP') === true, "DRP is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('P') === true, "P is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('DO') === true, "DO is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('DU') === true, "DU is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('FA') === true, "FA is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('UD') === true, "UD is recognized textual status");
assertPeriodCheck(isFinalizedHistoricalGrade('PASSED') === true, "PASSED is recognized textual status");

assertPeriodCheck(isFinalizedHistoricalGrade(null) === false, "null is NOT finalized");
assertPeriodCheck(isFinalizedHistoricalGrade('') === false, "empty string is NOT finalized");
assertPeriodCheck(isFinalizedHistoricalGrade('—') === false, "placeholder em-dash is NOT finalized");
assertPeriodCheck(isFinalizedHistoricalGrade('   ') === false, "whitespace string is NOT finalized");
assertPeriodCheck(isFinalizedHistoricalGrade('In Progress') === false, "In Progress is NOT finalized outcome");

// -----------------------------------------------------------------------------
// 6. Current Subject Status Stage Descriptors
// -----------------------------------------------------------------------------
echo "\n=== 6. Current Subject Status Stage Descriptors ===\n";

$stFinalNumeric = determineCurrentSubjectStatus(['final_grade' => '3.25', 'prelim' => 92, 'midterm' => 90]);
assertPeriodCheck($stFinalNumeric['is_finalized'] === true && $stFinalNumeric['label'] === '3.25', "Finalized numeric displays discrete point 3.25");

$stFinalInc = determineCurrentSubjectStatus(['final_grade' => 'INC', 'prelim' => 70]);
assertPeriodCheck($stFinalInc['is_finalized'] === true && $stFinalInc['label'] === 'INC', "Finalized status displays INC");

$stFinalDrp = determineCurrentSubjectStatus(['final_grade' => 'DRP']);
assertPeriodCheck($stFinalDrp['is_finalized'] === true && $stFinalDrp['label'] === 'DRP', "Finalized status displays DRP");

$stPreFinal = determineCurrentSubjectStatus(['final_grade' => null, 'prelim' => 90, 'midterm' => 88, 'prefinal' => 91]);
assertPeriodCheck($stPreFinal['is_finalized'] === false && $stPreFinal['label'] === 'Pre-Final recorded', "Pre-final stage displays 'Pre-Final recorded'");

$stMidterm = determineCurrentSubjectStatus(['final_grade' => null, 'prelim' => 90, 'midterm' => 88, 'prefinal' => null]);
assertPeriodCheck($stMidterm['is_finalized'] === false && $stMidterm['label'] === 'Midterm recorded', "Midterm stage displays 'Midterm recorded'");

$stPrelim = determineCurrentSubjectStatus(['final_grade' => null, 'prelim' => 92.94, 'midterm' => null, 'prefinal' => null]);
assertPeriodCheck($stPrelim['is_finalized'] === false && $stPrelim['label'] === 'Preliminary recorded', "Prelim stage displays 'Preliminary recorded'");

$stPending = determineCurrentSubjectStatus(['final_grade' => null, 'prelim' => null, 'midterm' => null, 'prefinal' => null]);
assertPeriodCheck($stPending['is_finalized'] === false && $stPending['label'] === 'Final grade pending', "No grades stage displays 'Final grade pending'");

// Ensure missing grade never coerced to numerical 0.00
assertPeriodCheck($stPending['label'] !== '0.00' && $stPending['label'] !== '0', "Missing grade is NOT numerical 0.00");
assertPeriodCheck($stPrelim['label'] !== '0.00' && $stPrelim['label'] !== '0', "Prelim pending final is NOT numerical 0.00");

// -----------------------------------------------------------------------------
// 7. Academic History Filtering & Deduplication Simulation
// -----------------------------------------------------------------------------
echo "\n=== 7. Academic History Filtering & Deduplication Simulation ===\n";

$mockGradeRows = [
    // Current semester rows (must be excluded from history)
    ['school_year' => '2026-2027', 'semester' => 1, 'code' => 'ITE421', 'final_grade' => null, 'prelim' => 90],
    ['school_year' => '2026-2027', 'semester' => 1, 'code' => 'ITE422', 'final_grade' => null, 'prelim' => 92],
    ['school_year' => '2026-2027', 'semester' => 1, 'code' => 'ITE431', 'final_grade' => null, 'prelim' => 92],
    // Future semester rows (must be excluded from history)
    ['school_year' => '2026-2027', 'semester' => 2, 'code' => 'ITE423', 'final_grade' => null, 'prelim' => null],
    ['school_year' => '2026-2027', 'semester' => 3, 'code' => 'SUM101', 'final_grade' => null, 'prelim' => null],
    ['school_year' => '2027-2028', 'semester' => 1, 'code' => 'ADV501', 'final_grade' => '3.00', 'prelim' => null],
    // Historical rows with finalized grades (must be included)
    ['school_year' => '2025-2026', 'semester' => 2, 'code' => 'ITE324', 'final_grade' => '3.75', 'prelim' => null],
    ['school_year' => '2025-2026', 'semester' => 2, 'code' => 'ITE325', 'final_grade' => '3.00', 'prelim' => null],
    ['school_year' => '2025-2026', 'semester' => 1, 'code' => 'ITE321', 'final_grade' => '3.00', 'prelim' => null],
    ['school_year' => '2025-2026', 'semester' => 1, 'code' => 'ITE322', 'final_grade' => '2.75', 'prelim' => null],
    // Historical rows with unfinalized / null grades (must be excluded)
    ['school_year' => '2025-2026', 'semester' => 1, 'code' => 'PLACEHOLDER', 'final_grade' => null, 'prelim' => null],
    ['school_year' => '2025-2026', 'semester' => 1, 'code' => 'DASHROW', 'final_grade' => '—', 'prelim' => null],
    // Undated row (must be excluded)
    ['school_year' => null, 'semester' => null, 'code' => 'UNDATED', 'final_grade' => '3.00', 'prelim' => null],
];

$simulatedHistory = [];
$simulatedRanks = [];

foreach ($mockGradeRows as $r) {
    if (!isHistoricalPeriod($r['school_year'], $r['semester'], $activeTerm)) {
        continue;
    }
    if (!isFinalizedHistoricalGrade($r['final_grade'])) {
        continue;
    }
    $normSy = normalizeSchoolYear($r['school_year']);
    $normSem = normalizeSemester($r['semester']);
    $rank = periodChronologicalRank($normSy, $normSem);
    $semLabel = match ($normSem) {
        1 => 'Sem 1', 2 => 'Sem 2', 3 => 'Summer', default => 'Sem ' . $normSem,
    };
    $termLabel = $normSy . ' — ' . $semLabel;
    $simulatedHistory[$termLabel][] = $r;
    $simulatedRanks[$termLabel] = $rank;
}

uksort($simulatedHistory, fn($a, $b) => ($simulatedRanks[$b] ?? 0) <=> ($simulatedRanks[$a] ?? 0));

$termKeys = array_keys($simulatedHistory);
assertPeriodCheck(count($termKeys) === 2, "Exactly 2 completed terms in history (expected 2025-2026 Sem 2 and Sem 1)");
assertPeriodCheck($termKeys[0] === '2025-2026 — Sem 2', "Latest completed term is first: 2025-2026 — Sem 2");
assertPeriodCheck($termKeys[1] === '2025-2026 — Sem 1', "Prior term is second: 2025-2026 — Sem 1");

// Verify exclusions
assertPeriodCheck(!isset($simulatedHistory['2026-2027 — Sem 1']), "Active term 2026-2027 — Sem 1 strictly ABSENT from history");
assertPeriodCheck(!isset($simulatedHistory['2026-2027 — Sem 2']), "Future term 2026-2027 — Sem 2 strictly ABSENT from history");
assertPeriodCheck(!isset($simulatedHistory['2026-2027 — Summer']), "Future summer strictly ABSENT from history");
assertPeriodCheck(!isset($simulatedHistory['2027-2028 — Sem 1']), "Next year term strictly ABSENT from history");

$historySubjectCodes = [];
foreach ($simulatedHistory as $t => $subjs) {
    foreach ($subjs as $s) {
        $historySubjectCodes[] = $s['code'];
    }
}
assertPeriodCheck(!in_array('ITE423', $historySubjectCodes, true), "Future subject ITE423 strictly ABSENT from history");
assertPeriodCheck(!in_array('ITE421', $historySubjectCodes, true), "Active subject ITE421 strictly ABSENT from history");
assertPeriodCheck(!in_array('PLACEHOLDER', $historySubjectCodes, true), "Unfinalized placeholder strictly ABSENT from history");
assertPeriodCheck(!in_array('DASHROW', $historySubjectCodes, true), "Em-dash placeholder row strictly ABSENT from history");
assertPeriodCheck(!in_array('UNDATED', $historySubjectCodes, true), "Undated row strictly ABSENT from history");
assertPeriodCheck(count($historySubjectCodes) === 4, "Only the 4 eligible finalized historical subjects appear in history");

// -----------------------------------------------------------------------------
// 8. Template & Copy Integrity Verification
// -----------------------------------------------------------------------------
echo "\n=== 8. Template & Copy Integrity Verification ===\n";

$gradesPhpContent = file_get_contents(__DIR__ . '/../../student/grades.php');

assertPeriodCheck(
    !str_contains($gradesPhpContent, 'No current-semester grades have been encoded yet.'),
    "Old misleading copy 'No current-semester grades have been encoded yet.' is completely removed"
);

assertPeriodCheck(
    str_contains($gradesPhpContent, 'Final grades have not yet been recorded for the current semester.'),
    "Approved copy 'Final grades have not yet been recorded for the current semester.' is present"
);

assertPeriodCheck(
    str_contains($gradesPhpContent, 'Recorded Preliminary or Midterm results are available in <a href="trend.php"'),
    "Supportive link to Performance Trend is present"
);

assertPeriodCheck(
    str_contains($gradesPhpContent, 'No current-semester subjects are available.'),
    "Empty subjects state 'No current-semester subjects are available.' is present"
);

assertPeriodCheck(
    str_contains($gradesPhpContent, 'Status / Final Grade'),
    "Current table column header is 'Status / Final Grade'"
);

assertPeriodCheck(
    str_contains($gradesPhpContent, 'fetchStudentCurrentGrades'),
    "Current semester queries use fetchStudentCurrentGrades (anchored to active term)"
);

assertPeriodCheck(
    str_contains($gradesPhpContent, 'fetchStudentCompletedAcademicHistory'),
    "Academic history queries use fetchStudentCompletedAcademicHistory (strictly historical periods)"
);

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "SUMMARY: Ran $testCount tests, $failCount failures.\n";
echo "========================================================================\n";

if ($failCount > 0) {
    exit(1);
}
