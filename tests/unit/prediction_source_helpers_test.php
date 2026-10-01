<?php
/**
 * Unit Test Suite: Prediction Source Vocabulary, Boundary Normalization & Provenance Helpers (WP-5)
 *
 * Verifies:
 * 1. Stored prediction source vocabulary ('decision_tree', 'heuristic', 'calculation_fallback').
 * 2. isValidPredictionSource() validation matrix (positive and negative cases).
 * 3. normalizePredictionSourceBoundary() service boundary normalization:
 *    - Maps 'fallback_blend' -> 'calculation_fallback'.
 *    - Rejects null, blank, whitespace, and unknown sources.
 * 4. predictionSourceFamily() grouping:
 *    - 'decision_tree' -> 'model'
 *    - 'heuristic' -> 'calculation'
 *    - 'calculation_fallback' -> 'calculation'
 * 5. predictionSourceLabel() user-facing strings:
 *    - 'decision_tree' -> 'AI-Based Projection'
 *    - 'heuristic' -> 'Calculation-Based Estimate'
 *    - 'calculation_fallback' -> 'Calculation-Based Estimate'
 * 6. predictionSourceDiagnosticLabel() technical reporting strings:
 *    - 'decision_tree' -> 'Decision Tree'
 *    - 'heuristic' -> 'Legacy Heuristic'
 *    - 'calculation_fallback' -> 'Current Calculation Fallback'
 * 7. Canonical predictFinalGradeHeuristic() 50/50 blend and .25 snapping.
 * 8. Missing-feature 4-case matrix behavior.
 *
 * Run via CLI: php tests/unit/prediction_source_helpers_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/constants.php';

$totalTests = 0;
$failedTests = 0;

function assertTest(bool $condition, string $message): void {
    global $totalTests, $failedTests;
    $totalTests++;
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        $failedTests++;
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: PREDICTION SOURCE VOCABULARY & CONTRACT UNIT TESTS (WP-5)\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Approved Stored Source Vocabulary
// -----------------------------------------------------------------------------
echo "=== 1. Approved Stored Source Constants ===\n";
assertTest(defined('PREDICTION_SOURCE_DECISION_TREE') && PREDICTION_SOURCE_DECISION_TREE === 'decision_tree', "PREDICTION_SOURCE_DECISION_TREE is 'decision_tree'");
assertTest(defined('PREDICTION_SOURCE_HEURISTIC') && PREDICTION_SOURCE_HEURISTIC === 'heuristic', "PREDICTION_SOURCE_HEURISTIC is 'heuristic'");
assertTest(defined('PREDICTION_SOURCE_CALCULATION_FALLBACK') && PREDICTION_SOURCE_CALCULATION_FALLBACK === 'calculation_fallback', "PREDICTION_SOURCE_CALCULATION_FALLBACK is 'calculation_fallback'");
assertTest(count(ALLOWED_PREDICTION_SOURCES) === 3, "ALLOWED_PREDICTION_SOURCES contains exactly 3 approved sources");
assertTest(in_array('decision_tree', ALLOWED_PREDICTION_SOURCES, true), "'decision_tree' in ALLOWED_PREDICTION_SOURCES");
assertTest(in_array('heuristic', ALLOWED_PREDICTION_SOURCES, true), "'heuristic' in ALLOWED_PREDICTION_SOURCES");
assertTest(in_array('calculation_fallback', ALLOWED_PREDICTION_SOURCES, true), "'calculation_fallback' in ALLOWED_PREDICTION_SOURCES");
assertTest(!in_array('fallback_blend', ALLOWED_PREDICTION_SOURCES, true), "'fallback_blend' is NOT in ALLOWED_PREDICTION_SOURCES (boundary only)");

// -----------------------------------------------------------------------------
// 2. isValidPredictionSource Validation
// -----------------------------------------------------------------------------
echo "\n=== 2. isValidPredictionSource Validation ===\n";
assertTest(isValidPredictionSource('decision_tree') === true, "isValidPredictionSource('decision_tree') = true");
assertTest(isValidPredictionSource('heuristic') === true, "isValidPredictionSource('heuristic') = true");
assertTest(isValidPredictionSource('calculation_fallback') === true, "isValidPredictionSource('calculation_fallback') = true");
assertTest(isValidPredictionSource('fallback_blend') === false, "isValidPredictionSource('fallback_blend') = false (must normalize before storage)");
assertTest(isValidPredictionSource('') === false, "isValidPredictionSource('') = false");
assertTest(isValidPredictionSource('   ') === false, "isValidPredictionSource('   ') = false");
assertTest(isValidPredictionSource(null) === false, "isValidPredictionSource(null) = false");
assertTest(isValidPredictionSource('unknown') === false, "isValidPredictionSource('unknown') = false");
assertTest(isValidPredictionSource('DECISION_TREE') === false, "isValidPredictionSource('DECISION_TREE') = false (case-sensitive exact contract)");

// -----------------------------------------------------------------------------
// 3. Boundary Normalization
// -----------------------------------------------------------------------------
echo "\n=== 3. Boundary Normalization: normalizePredictionSourceBoundary ===\n";
assertTest(normalizePredictionSourceBoundary('fallback_blend') === 'calculation_fallback', "normalize('fallback_blend') -> 'calculation_fallback'");
assertTest(normalizePredictionSourceBoundary('FALLBACK_BLEND') === 'calculation_fallback', "normalize('FALLBACK_BLEND') -> 'calculation_fallback'");
assertTest(normalizePredictionSourceBoundary('  fallback_blend  ') === 'calculation_fallback', "normalize('  fallback_blend  ') -> 'calculation_fallback'");
assertTest(normalizePredictionSourceBoundary('calculation_fallback') === 'calculation_fallback', "normalize('calculation_fallback') -> 'calculation_fallback'");
assertTest(normalizePredictionSourceBoundary('heuristic') === 'heuristic', "normalize('heuristic') -> 'heuristic'");
assertTest(normalizePredictionSourceBoundary('decision_tree') === 'decision_tree', "normalize('decision_tree') -> 'decision_tree'");
assertTest(normalizePredictionSource('fallback_blend') === 'calculation_fallback', "normalizePredictionSource alias works");
assertTest(normalizePredictionSourceBoundary(null) === null, "normalize(null) -> null");
assertTest(normalizePredictionSourceBoundary('') === null, "normalize('') -> null");
assertTest(normalizePredictionSourceBoundary('   ') === null, "normalize('   ') -> null");
assertTest(normalizePredictionSourceBoundary('random_ai') === null, "normalize('random_ai') -> null (rejects unknown)");

// -----------------------------------------------------------------------------
// 4. Source Family Grouping (model vs calculation)
// -----------------------------------------------------------------------------
echo "\n=== 4. Source Family Grouping: predictionSourceFamily ===\n";
assertTest(getPredictionSourceFamily('decision_tree') === 'model', "getPredictionSourceFamily('decision_tree') -> 'model'");
assertTest(predictionSourceFamily('decision_tree') === 'model', "predictionSourceFamily('decision_tree') -> 'model'");
assertTest(getPredictionSourceFamily('heuristic') === 'calculation', "getPredictionSourceFamily('heuristic') -> 'calculation'");
assertTest(predictionSourceFamily('heuristic') === 'calculation', "predictionSourceFamily('heuristic') -> 'calculation'");
assertTest(getPredictionSourceFamily('calculation_fallback') === 'calculation', "getPredictionSourceFamily('calculation_fallback') -> 'calculation'");
assertTest(predictionSourceFamily('calculation_fallback') === 'calculation', "predictionSourceFamily('calculation_fallback') -> 'calculation'");
assertTest(predictionSourceFamily(null) === 'unknown', "predictionSourceFamily(null) -> 'unknown'");
assertTest(predictionSourceFamily('unrecognized') === 'unknown', "predictionSourceFamily('unrecognized') -> 'unknown'");

// -----------------------------------------------------------------------------
// 5. User-Facing Display Labels
// -----------------------------------------------------------------------------
echo "\n=== 5. Display Labels: predictionSourceLabel ===\n";
assertTest(getPredictionSourceDisplayLabel('decision_tree') === 'AI-Based Projection', "display('decision_tree') -> 'AI-Based Projection'");
assertTest(predictionSourceLabel('decision_tree') === 'AI-Based Projection', "predictionSourceLabel('decision_tree') -> 'AI-Based Projection'");
assertTest(getPredictionSourceDisplayLabel('heuristic') === 'Calculation-Based Estimate', "display('heuristic') -> 'Calculation-Based Estimate'");
assertTest(predictionSourceLabel('heuristic') === 'Calculation-Based Estimate', "predictionSourceLabel('heuristic') -> 'Calculation-Based Estimate'");
assertTest(getPredictionSourceDisplayLabel('calculation_fallback') === 'Calculation-Based Estimate', "display('calculation_fallback') -> 'Calculation-Based Estimate'");
assertTest(predictionSourceLabel('calculation_fallback') === 'Calculation-Based Estimate', "predictionSourceLabel('calculation_fallback') -> 'Calculation-Based Estimate'");
assertTest(predictionSourceLabel(null) === 'Insufficient Data', "predictionSourceLabel(null) -> 'Insufficient Data'");
assertTest(predictionSourceLabel('invalid') === 'Insufficient Data', "predictionSourceLabel('invalid') -> 'Insufficient Data'");

// -----------------------------------------------------------------------------
// 6. Diagnostic Labels
// -----------------------------------------------------------------------------
echo "\n=== 6. Diagnostic Labels: predictionSourceDiagnosticLabel ===\n";
assertTest(getPredictionSourceDiagnosticLabel('decision_tree') === 'Decision Tree', "diagnostic('decision_tree') -> 'Decision Tree'");
assertTest(predictionSourceDiagnosticLabel('decision_tree') === 'Decision Tree', "predictionSourceDiagnosticLabel('decision_tree') -> 'Decision Tree'");
assertTest(getPredictionSourceDiagnosticLabel('heuristic') === 'Legacy Heuristic', "diagnostic('heuristic') -> 'Legacy Heuristic'");
assertTest(predictionSourceDiagnosticLabel('heuristic') === 'Legacy Heuristic', "predictionSourceDiagnosticLabel('heuristic') -> 'Legacy Heuristic'");
assertTest(getPredictionSourceDiagnosticLabel('calculation_fallback') === 'Current Calculation Fallback', "diagnostic('calculation_fallback') -> 'Current Calculation Fallback'");
assertTest(predictionSourceDiagnosticLabel('calculation_fallback') === 'Current Calculation Fallback', "predictionSourceDiagnosticLabel('calculation_fallback') -> 'Current Calculation Fallback'");
assertTest(predictionSourceDiagnosticLabel(null) === 'No Prediction Data', "diagnostic(null) -> 'No Prediction Data'");

// -----------------------------------------------------------------------------
// 7. Canonical Fallback Heuristic Math & Snapping
// -----------------------------------------------------------------------------
echo "\n=== 7. Fallback Heuristic Math & Increments ===\n";
// Equal blend: 0.5 * 2.50 + 0.5 * 3.50 = 3.00
assertTest(predictFinalGradeHeuristic(2.50, 3.50) === 3.00, "50/50 blend: (2.50, 3.50) -> 3.00");
// Unequal blend with 0.25 snap: 0.5 * 2.00 + 0.5 * 2.75 = 2.375 -> snaps to 2.50
assertTest(predictFinalGradeHeuristic(2.00, 2.75) === 2.50, "50/50 blend with .25 snap: (2.00, 2.75) -> 2.50");
// Upper clamp
assertTest(predictFinalGradeHeuristic(5.00, 4.50) === 4.00, "Upper clamp: (5.00, 4.50) -> 4.00");
// Lower clamp
assertTest(predictFinalGradeHeuristic(0.50, 0.75) === 1.00, "Lower clamp: (0.50, 0.75) -> 1.00");

// -----------------------------------------------------------------------------
// 8. Missing-Feature 4-Case Matrix
// -----------------------------------------------------------------------------
echo "\n=== 8. Missing-Feature 4-Case Matrix ===\n";
// Case A: Both present -> normal blend
assertTest(predictFinalGradeHeuristic(3.00, 2.00) === 2.50, "Case A (Both present): 50/50 blend succeeds");

// Case B: Historical GWA present, Prelim missing -> returns historical GWA rounded (partial data)
assertTest(predictFinalGradeHeuristic(null, 2.85) === 2.85, "Case B (Historical present, Prelim null): returns historical GWA");
assertTest(predictFinalGradeHeuristic(null, 2.85) !== 0.0, "Case B does NOT inject 0.0");

// Case C: Historical GWA missing, Prelim present -> returns prelim rounded (partial data)
assertTest(predictFinalGradeHeuristic(3.12, null) === 3.12, "Case C (Historical null, Prelim present): returns prelim");
assertTest(predictFinalGradeHeuristic(3.12, null) !== 0.0, "Case C does NOT inject 0.0");

// Case D: Both missing -> returns null (insufficient data, never 0.0)
assertTest(predictFinalGradeHeuristic(null, null) === null, "Case D (Both missing): returns null");
assertTest(predictFinalGradeHeuristic(null, null) !== 0.0, "Case D does NOT produce 0.0");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $totalTests tests, $failedTests failures.\n";
echo "========================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
