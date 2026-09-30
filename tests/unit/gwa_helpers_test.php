<?php
/**
 * Unit Test Suite: Canonical Credit-Unit-Weighted GWA & Grade Predicates
 * 
 * Verifies computeWeightedGWA() mathematical accuracy, unit weighting,
 * exclusion of non-numeric statuses & legacy 0.00, inclusion of failing point grades,
 * and passing/failing predicate consistency.
 * 
 * Run via CLI: php tests/unit/gwa_helpers_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/constants.php';

$failures = 0;
$testsRun = 0;

function assertEqual(mixed $actual, mixed $expected, string $message = ''): void {
    global $failures, $testsRun;
    $testsRun++;
    if ($actual !== $expected) {
        $failures++;
        echo "[FAIL] $message - Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
    } else {
        echo "[PASS] $message\n";
    }
}

function assertNullValue(mixed $actual, string $message = ''): void {
    global $failures, $testsRun;
    $testsRun++;
    if ($actual !== null) {
        $failures++;
        echo "[FAIL] $message - Expected null, Got: " . var_export($actual, true) . "\n";
    } else {
        echo "[PASS] $message\n";
    }
}

function assertFloatEqual(float $actual, float $expected, string $message = '', float $delta = 0.001): void {
    global $failures, $testsRun;
    $testsRun++;
    if (abs($actual - $expected) > $delta) {
        $failures++;
        echo "[FAIL] $message - Expected: $expected, Got: $actual (Delta: " . abs($actual - $expected) . ")\n";
    } else {
        echo "[PASS] $message\n";
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: CANONICAL WEIGHTED GWA & ANALYTICS HELPER UNIT TESTS\n";
echo "========================================================================\n\n";

// -------------------------------------------------------------------------
// 1. Equal-Unit Weighting
// -------------------------------------------------------------------------
echo "=== 1. Equal-Unit Weighting ===\n";
$rowsEqual = [
    ['grade' => '3.00', 'units' => 3],
    ['grade' => '2.50', 'units' => 3],
    ['grade' => '2.00', 'units' => 3],
];
// (3*3 + 2.5*3 + 2*3) / 9 = (9 + 7.5 + 6) / 9 = 22.5 / 9 = 2.50
assertFloatEqual(computeWeightedGWA($rowsEqual) ?? 0.0, 2.50, "Equal units (3x3): [3.00, 2.50, 2.00] -> 2.50");

// -------------------------------------------------------------------------
// 2. Unequal-Unit Weighting (Credit Weights Matter)
// -------------------------------------------------------------------------
echo "\n=== 2. Unequal-Unit Weighting ===\n";
// A 1-unit UID subject and a 3-unit ITE subject do NOT count equally
$rowsUnequal = [
    ['grade' => '4.00', 'units' => 1], // UID: 1 unit * 4.00 = 4.0
    ['grade' => '2.00', 'units' => 3], // ITE: 3 units * 2.00 = 6.0
];
// Sum points = 10.0, sum units = 4. GWA = 2.50.
// Unweighted simple average would be (4.00 + 2.00) / 2 = 3.00 (INCORRECT).
assertFloatEqual(computeWeightedGWA($rowsUnequal) ?? 0.0, 2.50, "Unequal units (1-unit 4.00, 3-unit 2.00) -> 2.50 (not unweighted 3.00)");

$rowsUnequal2 = [
    ['grade' => '1.75', 'units' => 5], // 5 * 1.75 = 8.75
    ['grade' => '3.50', 'units' => 2], // 2 * 3.50 = 7.00
    ['grade' => '2.25', 'units' => 3], // 3 * 2.25 = 6.75
];
// Sum points = 22.50, sum units = 10. GWA = 2.25.
assertFloatEqual(computeWeightedGWA($rowsUnequal2) ?? 0.0, 2.25, "Unequal units (5u@1.75, 2u@3.50, 3u@2.25) -> 2.25");

// -------------------------------------------------------------------------
// 3. Inclusion of Failing Point Grades (1.00, 1.25, 1.50)
// -------------------------------------------------------------------------
echo "\n=== 3. Inclusion of Failing Point Grades ===\n";
// Failing points 1.00, 1.25, 1.50 MUST be included in cumulative GWA
$rowsWithFailingPoints = [
    ['grade' => '3.00', 'units' => 3], // 3 * 3.00 = 9.0
    ['grade' => '1.50', 'units' => 3], // 3 * 1.50 = 4.5
    ['grade' => '1.00', 'units' => 3], // 3 * 1.00 = 3.0
];
// Sum points = 16.5, sum units = 9. GWA = 16.5 / 9 = 1.83.
assertFloatEqual(computeWeightedGWA($rowsWithFailingPoints) ?? 0.0, 1.83, "Failing points included: [3.00, 1.50, 1.00] (all 3u) -> 1.83");

$rowsSingleFailing = [
    ['grade' => '1.25', 'units' => 3],
];
assertFloatEqual(computeWeightedGWA($rowsSingleFailing) ?? 0.0, 1.25, "Single failing point: 1.25 @ 3u -> 1.25");

// -------------------------------------------------------------------------
// 4. Exclusion of Textual Statuses (INC, DRP, P, DO, DU, FA, UD, PASSED)
// -------------------------------------------------------------------------
echo "\n=== 4. Exclusion of Textual Statuses ===\n";
$rowsStatuses = [
    ['grade' => '3.00', 'units' => 3],
    ['grade' => 'INC',  'units' => 3],
    ['grade' => 'DRP',  'units' => 3],
    ['grade' => 'P',    'units' => 3],
    ['grade' => 'DO',   'units' => 3],
    ['grade' => 'DU',   'units' => 3],
    ['grade' => 'FA',   'units' => 3],
    ['grade' => 'UD',   'units' => 3],
    ['grade' => 'PASSED', 'units' => 3],
];
// Only 3.00 @ 3u counts. All statuses excluded. GWA = 3.00.
assertFloatEqual(computeWeightedGWA($rowsStatuses) ?? 0.0, 3.00, "Statuses excluded: only 3.00 @ 3u counts -> 3.00");

// Statuses with whitespace or lowercase
$rowsDirtyStatuses = [
    ['grade' => '2.50', 'units' => 3],
    ['grade' => ' inc ', 'units' => 3],
    ['grade' => 'drp',   'units' => 2],
    ['grade' => ' passed ', 'units' => 3],
];
assertFloatEqual(computeWeightedGWA($rowsDirtyStatuses) ?? 0.0, 2.50, "Lowercase & trimmed statuses excluded -> 2.50");

// -------------------------------------------------------------------------
// 5. Exclusion of Legacy 0.00
// -------------------------------------------------------------------------
echo "\n=== 5. Exclusion of Legacy 0.00 ===\n";
$rowsLegacyZero = [
    ['grade' => '3.00', 'units' => 3], // 3 * 3 = 9.0
    ['grade' => '0.00', 'units' => 3], // Excluded
    ['grade' => '0',    'units' => 3], // Excluded
    ['grade' => 0.0,    'units' => 3], // Excluded
];
// Only 3.00 @ 3u counts. GWA = 3.00. (If 0.00 was included, it would drag down to 0.75).
assertFloatEqual(computeWeightedGWA($rowsLegacyZero) ?? 0.0, 3.00, "Legacy 0.00 excluded: [3.00, '0.00', '0', 0.0] -> 3.00 (not 0.75)");

// -------------------------------------------------------------------------
// 6. Handling of Empty, Null, Blanks, and Invalid Units
// -------------------------------------------------------------------------
echo "\n=== 6. Edge Cases: Empty, Nulls, Invalid Units ===\n";
assertNullValue(computeWeightedGWA([]), "Empty array -> null");

$rowsAllNull = [
    ['grade' => null, 'units' => 3],
    ['grade' => '',   'units' => 3],
    ['grade' => '   ', 'units' => 3],
];
assertNullValue(computeWeightedGWA($rowsAllNull), "All null/blank grades -> null");

$rowsAllStatuses = [
    ['grade' => 'INC', 'units' => 3],
    ['grade' => 'DRP', 'units' => 3],
    ['grade' => '0.00', 'units' => 3],
];
assertNullValue(computeWeightedGWA($rowsAllStatuses), "Only statuses & legacy 0.00 -> null");

$rowsZeroUnits = [
    ['grade' => '3.00', 'units' => 0],
    ['grade' => '2.50', 'units' => -1],
    ['grade' => '2.00', 'units' => null],
    ['grade' => '1.75', 'units' => 'invalid'],
];
assertNullValue(computeWeightedGWA($rowsZeroUnits), "Non-positive or invalid units ignored -> null");

$rowsMixedValidInvalid = [
    ['grade' => '3.00', 'units' => 3],
    ['grade' => '2.00', 'units' => 0], // ignored
    ['grade' => null,   'units' => 3], // ignored
    ['grade' => 'xyz',  'units' => 3], // ignored
];
assertFloatEqual(computeWeightedGWA($rowsMixedValidInvalid) ?? 0.0, 3.00, "Mixed valid and invalid rows -> 3.00");

// -------------------------------------------------------------------------
// 7. Shorthand Normalization in GWA (e.g. '3.5' or 3.5 float)
// -------------------------------------------------------------------------
echo "\n=== 7. Shorthand Normalization in GWA ===\n";
$rowsShorthand = [
    ['grade' => '3.5', 'units' => 3], // 3.50 * 3 = 10.5
    ['grade' => 4,     'units' => 1], // 4.00 * 1 = 4.0
    ['grade' => '2',   'units' => 2], // 2.00 * 2 = 4.0
];
// Sum points = 18.5, sum units = 6. GWA = 18.5 / 6 = 3.08.
assertFloatEqual(computeWeightedGWA($rowsShorthand) ?? 0.0, 3.08, "Shorthand inputs ('3.5', 4, '2') normalized -> 3.08");

// -------------------------------------------------------------------------
// 8. Passing vs Failing Final Grade Predicates
// -------------------------------------------------------------------------
echo "\n=== 8. Passing vs Failing Predicates ===\n";
$allPoints = ['4.00', '3.75', '3.50', '3.25', '3.00', '2.75', '2.50', '2.25', '2.00', '1.75', '1.50', '1.25', '1.00'];
foreach ($allPoints as $pt) {
    $f = (float) $pt;
    if ($f >= 1.75) {
        assertEqual(isPassingFinalGrade($pt), true, "isPassingFinalGrade('$pt') = true");
        assertEqual(isFailingFinalGrade($pt), false, "isFailingFinalGrade('$pt') = false");
    } else {
        assertEqual(isPassingFinalGrade($pt), false, "isPassingFinalGrade('$pt') = false");
        assertEqual(isFailingFinalGrade($pt), true, "isFailingFinalGrade('$pt') = true");
    }
}

// Statuses
assertEqual(isPassingFinalGrade('P'), true, "isPassingFinalGrade('P') = true");
assertEqual(isPassingFinalGrade('passed'), true, "isPassingFinalGrade('passed') = true");
assertEqual(isFailingFinalGrade('P'), false, "isFailingFinalGrade('P') = false");

assertEqual(isPassingFinalGrade('DRP'), false, "isPassingFinalGrade('DRP') = false (Drop is non-passing)");
assertEqual(isFailingFinalGrade('DRP'), false, "isFailingFinalGrade('DRP') = false (Drop is non-failing)");

foreach (['INC', 'DO', 'DU', 'FA', 'UD'] as $failStat) {
    assertEqual(isPassingFinalGrade($failStat), false, "isPassingFinalGrade('$failStat') = false");
    assertEqual(isFailingFinalGrade($failStat), true, "isFailingFinalGrade('$failStat') = true");
}

// Legacy 0.00
assertEqual(isPassingFinalGrade('0.00'), false, "isPassingFinalGrade('0.00') = false");
assertEqual(isFailingFinalGrade('0.00'), true, "isFailingFinalGrade('0.00') = true");

// Invalid
assertEqual(isPassingFinalGrade('invalid'), false, "isPassingFinalGrade('invalid') = false");
assertEqual(isFailingFinalGrade('invalid'), false, "isFailingFinalGrade('invalid') = false");
assertEqual(isPassingFinalGrade(null), false, "isPassingFinalGrade(null) = false");
assertEqual(isFailingFinalGrade(null), false, "isFailingFinalGrade(null) = false");

// Exclusion from GWA
foreach ($allPoints as $pt) {
    assertEqual(isExcludedFromGwa($pt), false, "isExcludedFromGwa('$pt') = false (Numeric points included)");
}
foreach (['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD', 'PASSED', '0.00', null, 'xyz'] as $ex) {
    assertEqual(isExcludedFromGwa($ex), true, "isExcludedFromGwa(" . var_export($ex, true) . ") = true");
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
