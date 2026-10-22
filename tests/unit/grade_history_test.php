<?php
/**
 * Unit Test: Shared Grade History Repository and Helper
 *
 * Verifies that includes/grade_history.php:
 * 1. Accurately groups historical terms by curriculum year level and semester.
 * 2. Formats final grades without coercion.
 * 3. Categorizes final grade outcomes according to institutional rules.
 * 4. Calculates credit-unit-weighted Term GWA via computeWeightedGWA().
 * 5. Safely handles empty records and missing values.
 *
 * Run via CLI: php tests/unit/grade_history_test.php
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/grade_history.php';

function assertEqual(mixed $actual, mixed $expected, string $message = ''): void {
    if ($actual !== $expected) {
        $msg = $message ?: "Assertion failed";
        $exportActual = var_export($actual, true);
        $exportExpected = var_export($expected, true);
        echo "[FAIL] $msg: Expected $exportExpected, got $exportActual\n";
        exit(1);
    }
    echo "[PASS] $message\n";
}

echo "========================================================================\n";
echo "UDM-RADAR: SHARED GRADE HISTORY HELPER UNIT TESTS\n";
echo "========================================================================\n";

// --- 1. Outcome Label Mapping ---
echo "\n=== 1. Outcome Label Mapping ===\n";
assertEqual(getFinalGradeOutcomeLabel(null), 'In Progress', 'Null grade maps to In Progress');
assertEqual(getFinalGradeOutcomeLabel(''), 'In Progress', 'Empty string grade maps to In Progress');
assertEqual(getFinalGradeOutcomeLabel('4.00'), 'Passed', '4.00 maps to Passed');
assertEqual(getFinalGradeOutcomeLabel('3.00'), 'Passed', '3.00 maps to Passed');
assertEqual(getFinalGradeOutcomeLabel('1.00'), 'Passed', '1.00 maps to Passed');
assertEqual(getFinalGradeOutcomeLabel('P'), 'Passed', 'P maps to Passed');
assertEqual(getFinalGradeOutcomeLabel('p'), 'Passed', 'Lowercase p maps to Passed');
assertEqual(getFinalGradeOutcomeLabel('0.00'), 'Failed', '0.00 maps to Failed');
assertEqual(getFinalGradeOutcomeLabel('0'), 'Failed', '0 maps to Failed');
assertEqual(getFinalGradeOutcomeLabel('INC'), 'Incomplete', 'INC maps to Incomplete');
assertEqual(getFinalGradeOutcomeLabel('inc'), 'Incomplete', 'Lowercase inc maps to Incomplete');
assertEqual(getFinalGradeOutcomeLabel('DRP'), 'Dropped', 'DRP maps to Dropped');
assertEqual(getFinalGradeOutcomeLabel('DO'), 'Dropped Official', 'DO maps to Dropped Official');
assertEqual(getFinalGradeOutcomeLabel('DU'), 'Dropped Unofficial', 'DU maps to Dropped Unofficial');
assertEqual(getFinalGradeOutcomeLabel('FA'), 'Failure (Absences)', 'FA maps to Failure (Absences)');
assertEqual(getFinalGradeOutcomeLabel('UD'), 'Unofficial Drop', 'UD maps to Unofficial Drop');

// --- 2. Term Grouping and Curriculum Year Resolution ---
echo "\n=== 2. Term Grouping and Curriculum Year Resolution ===\n";
$mockRows = [
    // 1st Year, 1st Sem
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'GED101', 'title' => 'Purposive Communications', 'units' => 3, 'final_grade' => '3.00'],
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'ITE111', 'title' => 'Introduction to Computing', 'units' => 3, 'final_grade' => '2.50'],
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'PE101', 'title' => 'PATHFIT 1', 'units' => 2, 'final_grade' => '3.50'],
    // 1st Year, 2nd Sem
    ['school_year' => '2024-2025', 'semester' => 2, 'year_level' => 1, 'code' => 'ITE113', 'title' => 'Intermediate Programming', 'units' => 3, 'final_grade' => '2.75'],
    ['school_year' => '2024-2025', 'semester' => 2, 'year_level' => 1, 'code' => 'GED103', 'title' => 'STS', 'units' => 3, 'final_grade' => '3.00'],
    // 2nd Year, 1st Sem with a retaken 1st year subject
    ['school_year' => '2025-2026', 'semester' => 1, 'year_level' => 2, 'code' => 'ITE221', 'title' => 'DBMS 1', 'units' => 3, 'final_grade' => '3.25'],
    ['school_year' => '2025-2026', 'semester' => 1, 'year_level' => 1, 'code' => 'NST101', 'title' => 'NSTP 1', 'units' => 3, 'final_grade' => '2.75'],
    // 2nd Year, Summer term
    ['school_year' => '2025-2026', 'semester' => 3, 'year_level' => 2, 'code' => 'ITE299', 'title' => 'Special Topics', 'units' => 3, 'final_grade' => '3.50'],
];

$grouped = groupStudentHistoryByTerm($mockRows);

assertEqual(count($grouped), 4, '4 terms were created');

$termKeys = array_keys($grouped);
assertEqual($termKeys[0], '2025-2026 | 2nd Year, Summer', 'Descending order: Summer term first');
assertEqual($termKeys[1], '2025-2026 | 2nd Year, 1st Sem', 'Descending order: 2025-2026 1st Sem second');
assertEqual($termKeys[2], '2024-2025 | 1st Year, 2nd Sem', 'Descending order: 2024-2025 2nd Sem third');
assertEqual($termKeys[3], '2024-2025 | 1st Year, 1st Sem', 'Descending order: 2024-2025 1st Sem fourth');

// Verify retake folding into dominant year
$term2 = $grouped['2025-2026 | 2nd Year, 1st Sem'];
assertEqual($term2['yearLevel'], 2, 'Dominant year level resolves to highest (2nd Year)');
assertEqual(count($term2['subjects']), 2, 'Contains both subjects (regular and retake)');

// --- 3. Term GWA Calculation ---
echo "\n=== 3. Term GWA Calculation ===\n";
// Term 1 (2024-2025 1st Sem):
// 3.00*3 + 2.50*3 + 3.50*2 = 9.0 + 7.5 + 7.0 = 23.5 / 8 units = 2.9375 -> 2.94
$term1 = $grouped['2024-2025 | 1st Year, 1st Sem'];
assertEqual($term1['termGwa'], '2.94', 'Term GWA correctly computed and formatted to 2 decimals');

// --- 4. Non-numeric and Failing Grade Handling in GWA ---
echo "\n=== 4. Non-numeric and Failing Grade Handling in GWA ===\n";
$mockExcludedRows = [
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'SUBJ1', 'title' => 'Subject 1', 'units' => 3, 'final_grade' => '3.00'],
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'SUBJ2', 'title' => 'Subject 2', 'units' => 3, 'final_grade' => '0.00'],
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'SUBJ3', 'title' => 'Subject 3', 'units' => 3, 'final_grade' => 'INC'],
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'SUBJ4', 'title' => 'Subject 4', 'units' => 3, 'final_grade' => 'DRP'],
    ['school_year' => '2024-2025', 'semester' => 1, 'year_level' => 1, 'code' => 'SUBJ5', 'title' => 'Subject 5', 'units' => 3, 'final_grade' => 'P'],
];
$groupedExcluded = groupStudentHistoryByTerm($mockExcludedRows);
$termEx = $groupedExcluded['2024-2025 | 1st Year, 1st Sem'];
// Only SUBJ1 (3.00 @ 3u) counts in GWA. 0.00, INC, DRP, P are excluded from GWA per UDM policy.
assertEqual($termEx['termGwa'], '3.00', 'Term GWA excludes 0.00, INC, DRP, and P');

// --- 5. Empty Records ---
echo "\n=== 5. Empty Records ===\n";
assertEqual(groupStudentHistoryByTerm([]), [], 'Empty history rows produce empty array');

echo "\n========================================================================\n";
echo "SUMMARY: All grade history unit tests passed!\n";
echo "========================================================================\n";
