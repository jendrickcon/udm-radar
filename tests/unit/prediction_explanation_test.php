<?php
/**
 * Unit Test: Prediction Explanation & Metadata Support (WP-7 Step 8)
 *
 * Verifies:
 * 1. Audience-tailored source display labels (Student/Faculty vs Admin diagnostic)
 * 2. Audience-tailored non-punitive decision support disclaimers
 * 3. Completeness labels and human-readable coverage summaries
 * 4. Freshness formatting
 * 5. Risk-based action advice for students and faculty
 * 6. Non-technical terminology enforcement in student/faculty interfaces
 *
 * Run via CLI: php tests/unit/prediction_explanation_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/constants.php';

$testsRun = 0;
$failures = 0;

function assertCondition(bool $cond, string $msg): void {
    global $testsRun, $failures;
    $testsRun++;
    if ($cond) {
        echo "[PASS] $msg\n";
    } else {
        $failures++;
        echo "[FAIL] $msg\n";
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: PREDICTION EXPLANATION & METADATA UNIT TESTS (WP-7)\n";
echo "========================================================================\n\n";

echo "=== 1. Student Audience Metadata Contract ===\n";
$predModel = [
    'prediction_source' => PREDICTION_SOURCE_DECISION_TREE,
    'data_completeness' => 'complete',
    'risk_level'        => 'HIGH',
    'generated_at'      => '2026-10-01 14:30:00',
    'is_partial'        => false
];
$studentMeta = getPredictionExplanationMetadata($predModel, 'student');

assertCondition($studentMeta['source_label'] === 'AI-Based Projection', "Student sees 'AI-Based Projection' for decision_tree");
assertCondition(strpos($studentMeta['source_label'], 'Decision Tree') === false, "Student label NEVER exposes technical 'Decision Tree'");
assertCondition($studentMeta['completeness_label'] === 'Complete Records', "Student sees 'Complete Records' for complete dataset");
assertCondition($studentMeta['is_provisional'] === false, "Complete prediction is not provisional");
assertCondition(strpos($studentMeta['coverage_summary'], 'historical') !== false, "Coverage summary explains historical input");
assertCondition(strpos($studentMeta['coverage_summary'], 'preliminary') !== false, "Coverage summary explains preliminary input");
assertCondition(strpos($studentMeta['freshness_label'], 'Oct 1, 2026') !== false, "Freshness displays formatted human-readable date");
assertCondition(strpos($studentMeta['disclaimer'], 'advisory estimate') !== false, "Disclaimer states projection is an advisory estimate");
assertCondition(strpos($studentMeta['disclaimer'], 'not an official grade') !== false, "Disclaimer affirms it is not an official grade");
assertCondition(strpos($studentMeta['action_advice'], 'consultation hours') !== false, "High risk gives student consultative advice");

echo "\n=== 2. Fallback & Provisional Estimate Explanation ===\n";
$predHistOnly = [
    'prediction_source' => PREDICTION_SOURCE_CALCULATION_FALLBACK,
    'data_completeness' => 'historical_only',
    'risk_level'        => 'MODERATE',
    'generated_at'      => null,
    'is_partial'        => true
];
$histMeta = getPredictionExplanationMetadata($predHistOnly, 'student');

assertCondition($histMeta['source_label'] === 'Calculation-Based Estimate', "Student sees 'Calculation-Based Estimate' for fallback");
assertCondition(strpos($histMeta['source_label'], 'Fallback') === false, "Student label NEVER exposes 'Fallback' terminology");
assertCondition($histMeta['is_provisional'] === true, "Historical-only estimate is marked provisional");
assertCondition($histMeta['completeness_label'] === 'Historical Records Only (Provisional)', "Historical-only label indicates provisional nature");
assertCondition($histMeta['freshness_label'] === 'Current Session', "Unpersisted/null timestamp defaults to 'Current Session'");
assertCondition(strpos($histMeta['action_advice'], 'midterm') !== false, "Moderate risk gives student midterm focus advice");

echo "\n=== 3. Faculty Audience Metadata Contract ===\n";
$facMeta = getPredictionExplanationMetadata($predModel, 'faculty');

assertCondition($facMeta['source_label'] === 'AI-Based Projection', "Faculty sees 'AI-Based Projection'");
assertCondition(strpos($facMeta['disclaimer'], 'decision-support indicators') !== false, "Faculty disclaimer emphasizes decision support");
assertCondition(strpos($facMeta['disclaimer'], 'do not replace faculty evaluation') !== false, "Faculty disclaimer affirms faculty autonomy");
assertCondition(strpos($facMeta['action_advice'], 'academic referral') !== false, "Faculty high-risk advice suggests academic referral");

$predLow = [
    'prediction_source' => PREDICTION_SOURCE_DECISION_TREE,
    'data_completeness' => 'complete',
    'risk_level'        => 'LOW',
];
$facLowMeta = getPredictionExplanationMetadata($predLow, 'faculty');
assertCondition(strpos($facLowMeta['action_advice'], 'meeting academic benchmarks') !== false, "Faculty low-risk advice confirms student is meeting benchmarks");

echo "\n=== 4. Administrator Diagnostic Audience Contract ===\n";
$adminMeta = getPredictionExplanationMetadata($predModel, 'admin');
assertCondition($adminMeta['source_label'] === 'Decision Tree', "Admin diagnostic sees 'Decision Tree'");

$adminFallbackMeta = getPredictionExplanationMetadata($predHistOnly, 'admin');
assertCondition($adminFallbackMeta['source_label'] === 'Current Calculation Fallback', "Admin diagnostic sees 'Current Calculation Fallback'");
assertCondition(strpos($adminMeta['disclaimer'], 'Governance Notice') !== false, "Admin disclaimer is a governance notice");

echo "\n=== 5. Insufficient Data Edge Cases ===\n";
$emptyPred = [];
$emptyMeta = getPredictionExplanationMetadata($emptyPred, 'student');
assertCondition($emptyMeta['source_label'] === 'Insufficient Data', "Empty prediction defaults to 'Insufficient Data'");
assertCondition($emptyMeta['completeness_label'] === 'Insufficient Data', "Empty completeness defaults to 'Insufficient Data'");
assertCondition($emptyMeta['source_family'] === 'unknown', "Empty source family is 'unknown'");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
