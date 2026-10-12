<?php
/**
 * tests/unit/support_reasons_test.php
 * Unit Test Suite: Structured Academic-Support Reasons Contract (FACULTY-WP1A)
 *
 * Verifies:
 * 1. SUPPORT_REASONS dictionary contains exactly 6 approved canonical keys and labels.
 * 2. getSupportReasons() returns the complete dictionary.
 * 3. isValidSupportReason() correctly accepts canonical keys and rejects invalid, empty, or whitespace inputs.
 * 4. getSupportReasonLabel() returns expected labels, safe fallback for NULL/empty ('Not specified'), and 'Unknown reason' for unknown codes.
 * 5. Student-safe and non-punitive vocabulary compliance across all reason labels.
 *
 * Run via CLI: php tests/unit/support_reasons_test.php
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
echo "UDM-RADAR: STRUCTURED SUPPORT REASONS CONTRACT UNIT TESTS (FACULTY-WP1A)\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Approved Canonical Reasons Dictionary
// -----------------------------------------------------------------------------
echo "=== 1. Approved Canonical Reasons Dictionary ===\n";

assertTest(defined('SUPPORT_REASONS'), "SUPPORT_REASONS constant is defined");
assertTest(is_array(SUPPORT_REASONS), "SUPPORT_REASONS is an associative array");
assertTest(count(SUPPORT_REASONS) === 6, "SUPPORT_REASONS contains exactly 6 approved canonical keys");

$expectedReasons = [
    'low_assessment_score'       => 'Low recorded assessment score',
    'missing_course_requirement' => 'Missing course requirement or deliverable',
    'performance_needs_review'   => 'Current subject performance needs review',
    'incomplete_grade_data'      => 'Incomplete or provisional grade information',
    'consultation_recommended'   => 'Faculty consultation recommended',
    'other_academic_concern'     => 'Other Faculty-observed academic concern',
];

foreach ($expectedReasons as $key => $label) {
    assertTest(isset(SUPPORT_REASONS[$key]), "SUPPORT_REASONS contains key '$key'");
    assertTest(SUPPORT_REASONS[$key] === $label, "Key '$key' matches expected label: '$label'");
}

assertTest(getSupportReasons() === SUPPORT_REASONS, "getSupportReasons() returns exact SUPPORT_REASONS array");

// -----------------------------------------------------------------------------
// 2. Validation Matrix (isValidSupportReason)
// -----------------------------------------------------------------------------
echo "\n=== 2. Validation Matrix (isValidSupportReason) ===\n";

foreach (array_keys($expectedReasons) as $validKey) {
    assertTest(isValidSupportReason($validKey) === true, "isValidSupportReason('$validKey') returns true");
}

$invalidCases = [
    ['', 'empty string'],
    ['   ', 'whitespace string'],
    ['LOW_ASSESSMENT_SCORE', 'uppercase variant'],
    ['Low_Assessment_Score', 'title-case variant'],
    ['low_assessment_score ', 'trailing whitespace'],
    [' low_assessment_score', 'leading whitespace'],
    ['unknown_reason', 'unregistered key'],
    ['discipline_issue', 'punitive key'],
    ['strike_warning', 'forbidden strike vocabulary'],
    ['academic_probation', 'probation key'],
    ['1', 'numeric string "1"'],
    ['0', 'numeric string "0"'],
    ['other', 'truncated other key'],
    [null, 'null value'],
];

foreach ($invalidCases as [$case, $desc]) {
    $repr = $case === null ? 'null' : var_export($case, true);
    assertTest(isValidSupportReason($case) === false, "isValidSupportReason rejects $desc ($repr)");
}

// -----------------------------------------------------------------------------
// 3. Label Resolution and Safe Fallbacks (getSupportReasonLabel)
// -----------------------------------------------------------------------------
echo "\n=== 3. Label Resolution and Fallbacks (getSupportReasonLabel) ===\n";

foreach ($expectedReasons as $key => $label) {
    assertTest(getSupportReasonLabel($key) === $label, "getSupportReasonLabel('$key') returns '$label'");
}

assertTest(getSupportReasonLabel(null) === 'Not specified', "getSupportReasonLabel(null) returns 'Not specified'");
assertTest(getSupportReasonLabel('') === 'Not specified', "getSupportReasonLabel('') returns 'Not specified'");
assertTest(getSupportReasonLabel('   ') === 'Not specified', "getSupportReasonLabel('   ') returns 'Not specified'");
assertTest(getSupportReasonLabel('non_existent_code') === 'Unknown reason', "getSupportReasonLabel('non_existent_code') returns 'Unknown reason'");
assertTest(getSupportReasonLabel('unrecognized') === 'Unknown reason', "getSupportReasonLabel('unrecognized') returns 'Unknown reason'");

// -----------------------------------------------------------------------------
// 4. Supportive, Non-Punitive Vocabulary Safeguard
// -----------------------------------------------------------------------------
echo "\n=== 4. Supportive & Non-Punitive Vocabulary Safeguard ===\n";

$forbiddenWords = ['strike', 'punish', 'disciplinary', 'probation', 'dismiss', 'fail', 'failing', 'penalty', 'guilty', 'fault'];

foreach (SUPPORT_REASONS as $code => $text) {
    $lowerText = strtolower($text);
    $foundForbidden = false;
    foreach ($forbiddenWords as $badWord) {
        if (str_contains($lowerText, $badWord)) {
            $foundForbidden = true;
            break;
        }
    }
    assertTest(!$foundForbidden, "Reason '$code' ('$text') contains no punitive or forbidden terms");
}

echo "\n========================================================================\n";
echo "SUMMARY: Total: $totalTests, Failed: $failedTests\n";
echo "========================================================================\n";

exit($failedTests > 0 ? 1 : 0);
