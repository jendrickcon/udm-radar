<?php
/**
 * Unit Test Suite: Final Grade Helpers & Term Percentage Validation Matrix
 * 
 * Verifies canonical Final Grade vocabulary, normalization, predicate helpers,
 * historical legacy zero compatibility, and raw term-percentage boundaries.
 * 
 * Run via CLI: php tests/unit/final_grade_helpers_test.php
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

echo "========================================================================\n";
echo "UDM-RADAR: CANONICAL FINAL GRADE & TERM PERCENTAGE TEST MATRIX\n";
echo "========================================================================\n\n";

// -------------------------------------------------------------------------
// 1. Canonical Point Input (1.00 - 4.00 point scale in 0.25 steps)
// -------------------------------------------------------------------------
echo "=== 1. Canonical Point Input ===\n";
$points = ['4.00', '3.75', '3.50', '3.25', '3.00', '2.75', '2.50', '2.25', '2.00', '1.75', '1.50', '1.25', '1.00'];
foreach ($points as $pt) {
    assertEqual(isValidFinalGradeEntry($pt), true, "isValidFinalGradeEntry('$pt')");
    assertEqual(isValidHistoricalFinalGrade($pt), true, "isValidHistoricalFinalGrade('$pt')");
    assertEqual(isNumericFinalGrade($pt), true, "isNumericFinalGrade('$pt')");
    assertEqual(isExcludedFromGwa($pt), false, "isExcludedFromGwa('$pt')");
    assertEqual(normalizeFinalGradeInput($pt), $pt, "normalizeFinalGradeInput('$pt')");
    assertEqual(formatFinalGrade($pt), $pt, "formatFinalGrade('$pt')");
    $expectedFail = false;
    assertEqual(isFailingFinalGrade($pt), $expectedFail, "isFailingFinalGrade('$pt') (expected " . ($expectedFail ? 'true' : 'false') . ")");
}

// -------------------------------------------------------------------------
// 2. Normalization Aliases (point shorthand & status case/alias)
// -------------------------------------------------------------------------
echo "\n=== 2. Normalization Aliases ===\n";
assertEqual(normalizeFinalGradeInput('4'), '4.00', "normalizeFinalGradeInput('4') -> '4.00'");
assertEqual(normalizeFinalGradeInput('4.0'), '4.00', "normalizeFinalGradeInput('4.0') -> '4.00'");
assertEqual(normalizeFinalGradeInput('3.5'), '3.50', "normalizeFinalGradeInput('3.5') -> '3.50'");
assertEqual(normalizeFinalGradeInput(' inc '), 'INC', "normalizeFinalGradeInput(' inc ') -> 'INC'");
assertEqual(normalizeFinalGradeInput('passed'), 'P', "normalizeFinalGradeInput('passed') -> 'P'");
assertEqual(normalizeFinalGradeInput('  3.75  '), '3.75', "whitespace around point");
assertEqual(normalizeFinalGradeInput('  drp  '), 'DRP', "whitespace around status");

// -------------------------------------------------------------------------
// 3. Canonical Non-Numeric Statuses
// -------------------------------------------------------------------------
echo "\n=== 3. Canonical Statuses ===\n";
$statuses = ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD'];
foreach ($statuses as $st) {
    assertEqual(isValidFinalGradeEntry($st), true, "isValidFinalGradeEntry('$st')");
    assertEqual(isValidHistoricalFinalGrade($st), true, "isValidHistoricalFinalGrade('$st')");
    assertEqual(isNumericFinalGrade($st), false, "isNumericFinalGrade('$st')");
    assertEqual(isExcludedFromGwa($st), true, "isExcludedFromGwa('$st')");
    assertEqual(formatFinalGrade($st), $st, "formatFinalGrade('$st')");
    assertEqual(normalizeFinalGradeInput($st), $st, "normalizeFinalGradeInput('$st')");
}

// -------------------------------------------------------------------------
// 4. P and DRP Specific Behaviors (Non-Failing, Excluded from GWA)
// -------------------------------------------------------------------------
echo "\n=== 4. P and DRP Specific Behaviors ===\n";
assertEqual(isFailingFinalGrade('P'), false, "isFailingFinalGrade('P') -> false");
assertEqual(isFailingFinalGrade('passed'), false, "isFailingFinalGrade('passed') -> false");
assertEqual(isFailingFinalGrade('DRP'), false, "isFailingFinalGrade('DRP') -> false");
assertEqual(isFailingFinalGrade('drp'), false, "isFailingFinalGrade('drp') -> false");

// -------------------------------------------------------------------------
// 5. Failing Statuses Specific Behaviors
// -------------------------------------------------------------------------
echo "\n=== 5. Failing Statuses Specific Behaviors ===\n";
$failingStatuses = ['DO', 'DU', 'FA', 'UD'];
foreach ($failingStatuses as $fst) {
    assertEqual(isFailingFinalGrade($fst), true, "isFailingFinalGrade('$fst') -> true");
}

// -------------------------------------------------------------------------
// 6. Legacy 0.00 Behaviors (Rejected on Entry, Recognized in History)
// -------------------------------------------------------------------------
echo "\n=== 6. Legacy 0.00 Behaviors ===\n";
assertEqual(isValidFinalGradeEntry('0.00'), false, "isValidFinalGradeEntry('0.00') -> false");
assertEqual(isValidFinalGradeEntry('0.0'), false, "isValidFinalGradeEntry('0.0') -> false");
assertEqual(isValidFinalGradeEntry('0'), false, "isValidFinalGradeEntry('0') -> false");

assertEqual(isValidHistoricalFinalGrade('0.00'), true, "isValidHistoricalFinalGrade('0.00') -> true");
assertEqual(isValidHistoricalFinalGrade('0.0'), true, "isValidHistoricalFinalGrade('0.0') -> true");
assertEqual(isValidHistoricalFinalGrade('0'), true, "isValidHistoricalFinalGrade('0') -> true");

assertEqual(isNumericFinalGrade('0.00'), false, "isNumericFinalGrade('0.00') -> false");
assertEqual(isExcludedFromGwa('0.00'), true, "isExcludedFromGwa('0.00') -> true");
assertEqual(isFailingFinalGrade('0.00'), true, "isFailingFinalGrade('0.00') -> true");
assertEqual(formatFinalGrade('0.00'), '0.00', "formatFinalGrade('0.00') -> '0.00'");
assertEqual(formatFinalGrade('0'), '0.00', "formatFinalGrade('0') -> '0.00'");

// -------------------------------------------------------------------------
// 7. Invalid New Final Grade Entries
// -------------------------------------------------------------------------
echo "\n=== 7. Invalid New Entries ===\n";
$invalidEntries = ['0', '0.0', '0.00', '0.50', '0.75', '1.10', '5.00', '3.60', '3.6', '75', 'abc', '', '   ', null];
foreach ($invalidEntries as $inv) {
    $label = var_export($inv, true);
    assertEqual(normalizeFinalGradeInput($inv), null, "normalizeFinalGradeInput($label) -> null");
    assertEqual(isValidFinalGradeEntry($inv), false, "isValidFinalGradeEntry($label) -> false");
}

// -------------------------------------------------------------------------
// 8. formatFinalGrade Edge Cases
// -------------------------------------------------------------------------
echo "\n=== 8. formatFinalGrade Edge Cases ===\n";
assertEqual(formatFinalGrade(null), '—', "formatFinalGrade(null) -> '—'");
assertEqual(formatFinalGrade(''), '—', "formatFinalGrade('') -> '—'");
assertEqual(formatFinalGrade('4'), '4.00', "formatFinalGrade('4') -> '4.00'");
assertEqual(formatFinalGrade('3.5'), '3.50', "formatFinalGrade('3.5') -> '3.50'");
assertEqual(formatFinalGrade('1.75'), '1.75', "formatFinalGrade('1.75') -> '1.75'");
assertEqual(formatFinalGrade('passed'), 'P', "formatFinalGrade('passed') -> 'P'");
assertEqual(formatFinalGrade('inc'), 'INC', "formatFinalGrade('inc') -> 'INC'");

// -------------------------------------------------------------------------
// 9. canonicalizeFinalGrade & Historical Canonicalization
// -------------------------------------------------------------------------
echo "\n=== 9. canonicalizeFinalGrade & Historical Canonicalization ===\n";
assertEqual(canonicalizeFinalGrade(0), '0.00', "canonicalizeFinalGrade(0) -> '0.00'");
assertEqual(canonicalizeFinalGrade('0'), '0.00', "canonicalizeFinalGrade('0') -> '0.00'");
assertEqual(canonicalizeFinalGrade('0.0'), '0.00', "canonicalizeFinalGrade('0.0') -> '0.00'");
assertEqual(canonicalizeFinalGrade('0.00'), '0.00', "canonicalizeFinalGrade('0.00') -> '0.00'");
assertEqual(canonicalizeFinalGrade(0.0), '0.00', "canonicalizeFinalGrade(0.0) -> '0.00'");
assertEqual(canonicalizeFinalGrade(3.5), '3.50', "canonicalizeFinalGrade(3.5) -> '3.50'");
assertEqual(canonicalizeFinalGrade('3.5'), '3.50', "canonicalizeFinalGrade('3.5') -> '3.50'");
assertEqual(canonicalizeFinalGrade('3.50'), '3.50', "canonicalizeFinalGrade('3.50') -> '3.50'");
assertEqual(canonicalizeFinalGrade(3.50), '3.50', "canonicalizeFinalGrade(3.50) -> '3.50'");
assertEqual(canonicalizeFinalGrade('passed'), 'P', "canonicalizeFinalGrade('passed') -> 'P'");
assertEqual(canonicalizeFinalGrade('PASSED'), 'P', "canonicalizeFinalGrade('PASSED') -> 'P'");
assertEqual(canonicalizeFinalGrade('p'), 'P', "canonicalizeFinalGrade('p') -> 'P'");
assertEqual(canonicalizeFinalGrade('P'), 'P', "canonicalizeFinalGrade('P') -> 'P'");
assertEqual(canonicalizeFinalGrade('inc'), 'INC', "canonicalizeFinalGrade('inc') -> 'INC'");
assertEqual(canonicalizeFinalGrade(' inc '), 'INC', "canonicalizeFinalGrade(' inc ') -> 'INC'");
assertEqual(canonicalizeFinalGrade('1'), '1.00', "canonicalizeFinalGrade('1') -> '1.00'");
assertEqual(canonicalizeFinalGrade(1), '1.00', "canonicalizeFinalGrade(1) -> '1.00'");
assertEqual(canonicalizeFinalGrade(1.0), '1.00', "canonicalizeFinalGrade(1.0) -> '1.00'");
assertEqual(canonicalizeFinalGrade('1.0'), '1.00', "canonicalizeFinalGrade('1.0') -> '1.00'");
assertEqual(canonicalizeFinalGrade('1.00'), '1.00', "canonicalizeFinalGrade('1.00') -> '1.00'");
assertEqual(canonicalizeFinalGrade('5.00'), null, "canonicalizeFinalGrade('5.00') -> null");
assertEqual(canonicalizeFinalGrade('3.60'), null, "canonicalizeFinalGrade('3.60') -> null");
assertEqual(canonicalizeFinalGrade('abc'), null, "canonicalizeFinalGrade('abc') -> null");
assertEqual(canonicalizeFinalGrade(''), null, "canonicalizeFinalGrade('') -> null");
assertEqual(canonicalizeFinalGrade(null), null, "canonicalizeFinalGrade(null) -> null");

// -------------------------------------------------------------------------
// 10. isFailingFinalGrade Normalized Handling
// -------------------------------------------------------------------------
echo "\n=== 10. isFailingFinalGrade Normalized Handling ===\n";
assertEqual(isFailingFinalGrade('1'), false, "isFailingFinalGrade('1') = false");
assertEqual(isFailingFinalGrade('1.0'), false, "isFailingFinalGrade('1.0') = false");
assertEqual(isFailingFinalGrade('1.00'), false, "isFailingFinalGrade('1.00') = false");
assertEqual(isFailingFinalGrade(1), false, "isFailingFinalGrade(1) = false");
assertEqual(isFailingFinalGrade(1.0), false, "isFailingFinalGrade(1.0) = false");
assertEqual(isFailingFinalGrade('1.50'), false, "isFailingFinalGrade('1.50') = false");
assertEqual(isFailingFinalGrade('1.75'), false, "isFailingFinalGrade('1.75') = false");
assertEqual(isFailingFinalGrade(' inc '), false, "isFailingFinalGrade(' inc ') = false");
assertEqual(isFailingFinalGrade('passed'), false, "isFailingFinalGrade('passed') = false");
assertEqual(isFailingFinalGrade('0'), true, "isFailingFinalGrade('0') = true");
assertEqual(isFailingFinalGrade('0.00'), true, "isFailingFinalGrade('0.00') = true");
assertEqual(isFailingFinalGrade('DRP'), false, "isFailingFinalGrade('DRP') = false");
assertEqual(isFailingFinalGrade('drp'), false, "isFailingFinalGrade('drp') = false");
assertEqual(isFailingFinalGrade('P'), false, "isFailingFinalGrade('P') = false");

// -------------------------------------------------------------------------
// 11. isValidTermPercentage (Numeric Percentages [0, 100] & Decimal Bounds)
// -------------------------------------------------------------------------
echo "\n=== 11. isValidTermPercentage ===\n";
// Accepted standard percentages (integers, 1 decimal, 2 decimals)
assertEqual(isValidTermPercentage(0), true, "isValidTermPercentage(0) = true");
assertEqual(isValidTermPercentage('0'), true, "isValidTermPercentage('0') = true");
assertEqual(isValidTermPercentage(75), true, "isValidTermPercentage(75) = true");
assertEqual(isValidTermPercentage('75'), true, "isValidTermPercentage('75') = true");
assertEqual(isValidTermPercentage('88.5'), true, "isValidTermPercentage('88.5') = true");
assertEqual(isValidTermPercentage(88.5), true, "isValidTermPercentage(88.5) = true");
assertEqual(isValidTermPercentage('88.50'), true, "isValidTermPercentage('88.50') = true");
assertEqual(isValidTermPercentage(88.50), true, "isValidTermPercentage(88.50) = true");
assertEqual(isValidTermPercentage('87.5'), true, "isValidTermPercentage('87.5') = true");
assertEqual(isValidTermPercentage(100), true, "isValidTermPercentage(100) = true");
assertEqual(isValidTermPercentage('100'), true, "isValidTermPercentage('100') = true");
assertEqual(isValidTermPercentage('100.0'), true, "isValidTermPercentage('100.0') = true");
assertEqual(isValidTermPercentage('100.00'), true, "isValidTermPercentage('100.00') = true");

// Rejected: Range violations
assertEqual(isValidTermPercentage(101), false, "isValidTermPercentage(101) = false");
assertEqual(isValidTermPercentage('101'), false, "isValidTermPercentage('101') = false");
assertEqual(isValidTermPercentage(-1), false, "isValidTermPercentage(-1) = false");
assertEqual(isValidTermPercentage('-1'), false, "isValidTermPercentage('-1') = false");
assertEqual(isValidTermPercentage('100.01'), false, "isValidTermPercentage('100.01') = false");
assertEqual(isValidTermPercentage('-0.01'), false, "isValidTermPercentage('-0.01') = false");

// Rejected: Decimal precision exceeding 2 places
assertEqual(isValidTermPercentage('88.500'), false, "isValidTermPercentage('88.500') = false");
assertEqual(isValidTermPercentage('100.000'), false, "isValidTermPercentage('100.000') = false");

// Rejected: Scientific notation
assertEqual(isValidTermPercentage('8.8e1'), false, "isValidTermPercentage('8.8e1') = false");
assertEqual(isValidTermPercentage('1e2'), false, "isValidTermPercentage('1e2') = false");

// Rejected: Textual Final Grade statuses must all be strictly rejected
assertEqual(isValidTermPercentage('INC'), false, "isValidTermPercentage('INC') = false");
assertEqual(isValidTermPercentage('DRP'), false, "isValidTermPercentage('DRP') = false");
assertEqual(isValidTermPercentage('P'), false, "isValidTermPercentage('P') = false");
assertEqual(isValidTermPercentage('DO'), false, "isValidTermPercentage('DO') = false");
assertEqual(isValidTermPercentage('DU'), false, "isValidTermPercentage('DU') = false");
assertEqual(isValidTermPercentage('FA'), false, "isValidTermPercentage('FA') = false");
assertEqual(isValidTermPercentage('UD'), false, "isValidTermPercentage('UD') = false");
assertEqual(isValidTermPercentage('PASSED'), false, "isValidTermPercentage('PASSED') = false");
assertEqual(isValidTermPercentage('inc'), false, "isValidTermPercentage('inc') = false");
assertEqual(isValidTermPercentage('passed'), false, "isValidTermPercentage('passed') = false");
assertEqual(isValidTermPercentage('drp'), false, "isValidTermPercentage('drp') = false");

// Rejected: Blanks, nulls, and arbitrary text
assertEqual(isValidTermPercentage(null), false, "isValidTermPercentage(null) = false");
assertEqual(isValidTermPercentage(''), false, "isValidTermPercentage('') = false");
assertEqual(isValidTermPercentage('   '), false, "isValidTermPercentage('   ') = false");
assertEqual(isValidTermPercentage('abc'), false, "isValidTermPercentage('abc') = false");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
exit(0);
