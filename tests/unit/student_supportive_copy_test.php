<?php
/**
 * tests/unit/student_supportive_copy_test.php
 * Unit Test Suite: Student Supportive Copy and Risk Guidance Contract (STUDENT-COPY-WP1A)
 *
 * Verifies:
 * 1. Prohibited deficit, blaming, and punitive phrasing is completely absent across all 7 approved files.
 * 2. Approved student-safe, constructive, and agency-oriented copy is present across all triage points, tooltips, and shared modals.
 * 3. Strict separation of Subject Outcomes, Prototype Risk Triage, and Official Academic Standing.
 * 4. Verification of COPY-001 through COPY-023 canonical contracts across:
 *    - student/dashboard.php
 *    - student/grades.php
 *    - student/index.php
 *    - student/trend.php
 *    - student/feedback.php
 *    - config/constants.php
 *    - includes/glossary_modal.php
 *
 * Run via CLI: php tests/unit/student_supportive_copy_test.php
 */

declare(strict_types=1);

$totalTests = 0;
$failedTests = 0;

function assertCheck(bool $condition, string $message): void {
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
echo "UDM-RADAR: STUDENT SUPPORTIVE COPY COMPREHENSIVE TESTS (STUDENT-COPY-WP1A)\n";
echo "========================================================================\n\n";

$repoRoot = dirname(__DIR__, 2);
$files = [
    'dashboard'  => $repoRoot . '/student/dashboard.php',
    'grades'     => $repoRoot . '/student/grades.php',
    'index'      => $repoRoot . '/student/index.php',
    'trend'      => $repoRoot . '/student/trend.php',
    'feedback'   => $repoRoot . '/student/feedback.php',
    'constants'  => $repoRoot . '/config/constants.php',
    'glossary'   => $repoRoot . '/includes/glossary_modal.php',
];

$contents = [];
foreach ($files as $key => $path) {
    $c = file_get_contents($path);
    if ($c === false) {
        echo "[FAIL] Unable to read $path\n";
        exit(1);
    }
    $contents[$key] = $c;
}

// -----------------------------------------------------------------------------
// 1. Prohibited Deficit, Fatalistic, and Punitive Phrasing Absence
// -----------------------------------------------------------------------------
echo "=== 1. Prohibited Phrasing Absence Across All Approved Files ===\n";

$prohibitedPatterns = [
    'critically low',
    'dragging down',
    'significant intervention',
    'graduation delay',
    'prevent failure',
    'strongly encourage',
    'secure your standing',
    'retention threshold',
    'safe standing',
    'Official Term GWA',
    'ACTION REQUIRED',
    'advisory standing benchmarks',
    'Latin Honor eligibility',
    'You are below the Very Satisfactory threshold',
    'Model projects a',
    'perfectly maintain',
    'weakest current grade',
    'Prioritize study time',
    'performing safely',
    'No immediate risk factors detected',
    'Consistent performance maintained',
    'All current subjects are within safe thresholds',
    'All terms are locked',
    'Cannot reverse-calculate',
    'Mathematically impossible',
    'Academic trajectory evaluated',
    'Critical vulnerability',
    'failing history',
    'barely Passed',
    'Strong, stable academic trajectory',
    'retention support',
    'Individual course passing (1.00 or above)',
    'term pacing',
];

foreach ($contents as $fileKey => $text) {
    foreach ($prohibitedPatterns as $prohibited) {
        $absent = !str_contains($text, $prohibited);
        assertCheck($absent, "[$fileKey] Prohibited phrase absent: '$prohibited'");
    }
}

// -----------------------------------------------------------------------------
// 2. Canonical Required Supportive Terms Presence
// -----------------------------------------------------------------------------
echo "\n=== 2. Approved Canonical Terminology Presence ===\n";

$requiredTerms = [
    'High Risk indicator',
    'Moderate Risk indicator',
    'Low Risk indicator',
    'Additional grades may change this estimate',
    'Recorded Term GWA',
    'Current Snapshot GWA',
    'Estimated GWA',
    'NOT YET ACKNOWLEDGED',
    'Reason shared by Faculty',
    'Acknowledge Receipt',
    'consultation hours',
    'midterm',
    '1.75',
    '2.50',
    'Academic Distinction',
];

$combinedApproved = implode("\n", $contents);
foreach ($requiredTerms as $term) {
    assertCheck(str_contains($combinedApproved, $term), "Required canonical term present: '$term'");
}

// -----------------------------------------------------------------------------
// 3. Exact Canonical Findings Checks: COPY-001 through COPY-023
// -----------------------------------------------------------------------------
echo "\n=== 3. Canonical Finding Map Assertions (COPY-001 through COPY-023) ===\n";

// COPY-001: student/dashboard.php - Irregular pattern text with 'type' => 'danger'
assertCheck(
    str_contains($contents['dashboard'], "\$risk_factors[] = ['type' => 'danger', 'text' => 'Historical: Your record shows an irregular enrollment pattern. Your Faculty adviser can help you plan your remaining terms.'];"),
    "COPY-001: Irregular enrollment factor uses exact approved text and retains danger alert type"
);
assertCheck(
    !str_contains($contents['dashboard'], 'statistically increases graduation delay risk') &&
    !str_contains($contents['dashboard'], 'Irregular enrollment status detected'),
    "COPY-001: Graduation delay risk and detected phrasing are absent"
);

// COPY-002: student/dashboard.php - High Risk subject triage
assertCheck(
    str_contains($contents['dashboard'], "<strong>High Risk indicator:</strong> Your current grade in <strong>{\$displayTitle}</strong> is \" . round((float)\$subj['prelim_raw']) . \"%. Additional grades may change this estimate. Consider discussing it with your Faculty adviser."),
    "COPY-002: High Risk subject triage matches exact approved text"
);

// COPY-003: student/dashboard.php - Moderate Risk subject triage
assertCheck(
    str_contains($contents['dashboard'], "<strong>Moderate Risk indicator:</strong> Your current grade in <strong>{\$displayTitle}</strong> is \" . round((float)\$subj['prelim_raw']) . \"%. This may affect your current estimate. Reviewing this subject may help."),
    "COPY-003: Moderate Risk subject triage matches exact approved text"
);

// COPY-004: student/dashboard.php - Fallback High Risk stat-card summary & tooltip
assertCheck(
    str_contains($contents['dashboard'], '$riskTooltip = $isAiSource') &&
    str_contains($contents['dashboard'], '"Current estimate is below 1.75 and may change as more grades are recorded.";'),
    "COPY-004: High Risk fallback tooltip matches exact approved text"
);
assertCheck(
    str_contains($contents['dashboard'], '"High Risk indicator: Based on your current recorded grades, your current estimate is below 1.75. Additional grades may change this estimate. Consider discussing next steps with your Faculty adviser."'),
    "COPY-004: High Risk stat-card fallback summary matches exact approved text"
);

// COPY-005: student/dashboard.php - Current Term wording below 2.50
assertCheck(
    str_contains($contents['dashboard'], "\$risk_factors[] = ['type' => 'warning', 'text' => \"Current Term: {\$at_risk_count} current subject(s) have an estimate below 2.50. Additional grades may change this estimate.\"];"),
    "COPY-005: Current Term below 2.50 count uses exact approved text"
);

// COPY-006: student/dashboard.php - Trajectory comparison wording
assertCheck(
    str_contains($contents['dashboard'], "\$risk_factors[] = ['type' => 'warning', 'text' => \"Trajectory: The current estimate is {\$drop} lower than your historical GWA. Additional grades may change this estimate.\"];"),
    "COPY-006: Trajectory drop uses exact approved wording with change disclaimer"
);
assertCheck(
    str_contains($contents['dashboard'], "\$risk_factors[] = ['type' => 'success', 'text' => \"Trajectory: The current estimate is {\$gain} higher than your historical GWA.\"];"),
    "COPY-006: Trajectory gain uses exact approved wording"
);
assertCheck(
    str_contains($contents['dashboard'], "\$risk_factors[] = ['type' => 'info', 'text' => \"Trajectory: The current estimate is close to your historical GWA.\"];"),
    "COPY-006: Trajectory neutral uses exact approved wording"
);

// COPY-007: student/dashboard.php - Focus suggestion and steady suggestion
assertCheck(
    str_contains($contents['dashboard'], "Focus suggestion: Your lowest current grade is in <strong>{\$subjectList}</strong> at <strong>\" . round((float)\$lowestRaw) . \"%</strong>. Spending some extra study time there may help."),
    "COPY-007: Focus suggestion matches exact approved wording"
);
assertCheck(
    str_contains($contents['dashboard'], "Suggestion: Your current grades look steady. Your lowest grade is in <strong>{\$subjectList}</strong> at <strong>\" . round((float)\$lowestRaw) . \"%</strong>."),
    "COPY-007: Steady suggestion matches exact approved wording"
);

// COPY-008: student/dashboard.php - Positive no risk factors wording
assertCheck(
    str_contains($contents['dashboard'], "\$risk_factors[] = ['type' => 'success', 'text' => 'Positive: No risk factors are showing in your current records.'];"),
    "COPY-008: Positive empty state uses exact approved text"
);

// COPY-009: student/dashboard.php - AI-path High Risk explanation
assertCheck(
    str_contains($contents['dashboard'], '"High Risk indicator: The AI-based projection places your current estimate in the High Risk range. Past records and current grades can both affect it, and additional grades may change it. Consider discussing it with your Faculty adviser."'),
    "COPY-009: AI-path High Risk summary matches exact approved wording"
);
assertCheck(
    str_contains($contents['dashboard'], '"Estimate is in the High Risk range and may change as more grades are recorded."'),
    "COPY-009: AI-path High Risk tooltip matches exact approved wording"
);

// COPY-010: student/dashboard.php - Moderate Risk stat-card summary and tooltip
assertCheck(
    str_contains($contents['dashboard'], '$riskTooltip = "Current estimate is in the Moderate Risk range, from 1.75 to 2.49.";'),
    "COPY-010: Moderate Risk tooltip matches exact approved text"
);
assertCheck(
    str_contains($contents['dashboard'], "'MODERATE' => \"Moderate Risk indicator: Your current estimate is between 1.75 and 2.49. Reviewing your subjects may help. Additional grades may change this estimate.\""),
    "COPY-010: Moderate Risk stat-card summary matches exact approved text"
);

// COPY-011: student/dashboard.php - Low Risk stat-card summary and tooltip
assertCheck(
    str_contains($contents['dashboard'], '$riskTooltip = "Current estimate is in the Low Risk range, 2.50 and above.";'),
    "COPY-011: Low Risk tooltip matches exact approved text"
);
assertCheck(
    str_contains($contents['dashboard'], "'LOW' => \"Low Risk indicator: Your current estimate is 2.50 or above. Additional grades may change this estimate.\""),
    "COPY-011: Low Risk stat-card summary matches exact approved text"
);

// COPY-012: student/dashboard.php - Academic Distinction
assertCheck(
    str_contains($contents['dashboard'], "<span><?= !\$hasCurrentGwa ? 'Cumulative GWA' : 'Academic Distinction' ?></span>"),
    "COPY-012: Gauge title is 'Academic Distinction' when GWA exists"
);
assertCheck(
    str_contains($contents['dashboard'], "'Shows your historical cumulative GWA in relation to the configured academic-distinction ranges. Official qualification remains subject to complete institutional criteria and authorized review.'"),
    "COPY-012: Gauge description matches exact approved guidance text"
);
assertCheck(
    str_contains($contents['dashboard'], 'Dean’s List · starts at 3.25') && str_contains($contents['dashboard'], 'Magna Cum Laude · starts at 3.50') && str_contains($contents['dashboard'], 'Summa Cum Laude · starts at 3.75'),
    "COPY-012: Gauge legend renders approved three distinction bands"
);

// COPY-013: student/dashboard.php - Empty triage state
assertCheck(
    str_contains($contents['dashboard'], '<span>No current subjects are showing a risk indicator.</span>'),
    "COPY-013: Empty subject triage uses exact approved text"
);

// COPY-014: student/dashboard.php - Grade Goal Calculator alerts
assertCheck(
    str_contains($contents['dashboard'], 'alert("All terms already have grades, so there is nothing left to calculate.");'),
    "COPY-014: Calculator all terms locked alert matches approved wording"
);
assertCheck(
    str_contains($contents['dashboard'], 'alert("This target is not reachable with the remaining terms, because it would need more than 100%. Try a lower target.");'),
    "COPY-014: Calculator target unreachable alert matches approved wording"
);

// COPY-015: student/grades.php - High Risk subject tooltip
assertCheck(
    str_contains($contents['grades'], '$subjTooltip = "High Risk indicator: The current grade point is below 1.75 (79% or lower). This advisory indicator is separate from the subject’s recorded pass/fail result. Consider discussing the record with your Faculty adviser.";'),
    "COPY-015: High Risk subject tooltip matches exact approved text with pass/fail separation"
);

// COPY-016: student/grades.php - Moderate & Low Risk subject tooltips
assertCheck(
    str_contains($contents['grades'], '$subjTooltip = "Moderate Risk indicator: Your current subject grade point is between 1.75 and 2.49 (80% - 85%). Reviewing this subject may help. Additional grades may change this estimate.";'),
    "COPY-016: Moderate Risk subject tooltip matches exact approved text"
);
assertCheck(
    str_contains($contents['grades'], '$subjTooltip = "Low Risk indicator: Your current subject grade point is 2.50 (86%) or higher. Additional grades may change this estimate.";'),
    "COPY-016: Low Risk subject tooltip matches exact approved text"
);

// COPY-017: student/index.php - Attention banner heading and body
assertCheck(
    str_contains($contents['index'], '<h4 style="margin: 0 0 4px 0; color: var(--risk-high); font-size: 1rem;">For Your Review</h4>'),
    "COPY-017: Attention card heading changed to 'For Your Review'"
);
assertCheck(
    str_contains($contents['index'], '<?= $attentionSubjects ?> current subject<?= $attentionSubjects > 1 ? \'s\' : \'\' ?> have a risk indicator. Open your dashboard to see the details and suggested next steps.'),
    "COPY-017: Attention card body matches exact approved supportive wording"
);
assertCheck(
    !str_contains($contents['index'], 'Academic Advisory'),
    "COPY-017: 'Academic Advisory' is absent from student home"
);

// COPY-018: student/index.php - Recent academic updates
assertCheck(
    str_contains($contents['index'], 'A new estimate was recorded. Current risk indicator: <strong><?= htmlspecialchars(ucfirst(strtolower($act[\'risk_level\']))) ?></strong>.'),
    "COPY-018: Activity list frames update as 'A new estimate was recorded. Current risk indicator: {X}'"
);

// COPY-019: student/trend.php - GWA naming in cards, legend, and tooltip
assertCheck(
    str_contains($contents['trend'], 'Recorded Term GWA') &&
    str_contains($contents['trend'], 'Current Snapshot GWA') &&
    str_contains($contents['trend'], 'Estimated GWA'),
    "COPY-019: Performance Trend cards and legend use Recorded Term GWA, Current Snapshot GWA, Estimated GWA"
);
assertCheck(
    str_contains($contents['trend'], "let prefix = type === 'historical' ? 'Recorded Term GWA: ' : (type === 'current' ? 'Current Snapshot GWA: ' : 'Estimated GWA: ');"),
    "COPY-019: Trend chart tooltip callback uses approved prefix vocabulary"
);

// COPY-020: config/constants.php - Student actionable next steps
assertCheck(
    str_contains($contents['constants'], "'You may benefit from meeting with your subject instructors or visiting the academic support office during consultation hours.'"),
    "COPY-020: High risk student advice matches exact approved text"
);
assertCheck(
    str_contains($contents['constants'], "'You may want to focus on upcoming midterm deliverables and consider joining peer review sessions in subjects with a risk indicator.'"),
    "COPY-020: Moderate risk student advice matches exact approved text"
);

// COPY-021: includes/glossary_modal.php - Course Results vs. Risk Indicators
assertCheck(
    str_contains($contents['glossary'], '<li><strong>Course Results and Risk Indicators:</strong> A recorded course result and a UDM-RADAR risk indicator describe different things. Under the prototype’s current thresholds, a passing result may still display a High Risk indicator below 1.75. The indicator is advisory and does not change the recorded subject result.</li>'),
    "COPY-021: Glossary modal matches exact approved text for Course Results and Risk Indicators"
);

// COPY-022: includes/glossary_modal.php - Academic Guide descriptions & footer
assertCheck(
    str_contains($contents['glossary'], "Corresponds to Very Satisfactory and higher."),
    "COPY-022: Low risk guide description matches exact approved text"
);
assertCheck(
    str_contains($contents['glossary'], "Corresponds to the Fair range or below. Faculty mentoring and support may help."),
    "COPY-022: High risk guide description matches exact approved text"
);
assertCheck(
    str_contains($contents['glossary'], "facilitate early mentoring and tutoring support."),
    "COPY-022: Guide footer ends in 'early mentoring and tutoring support.'"
);

// COPY-023: student/feedback.php - Student notice presentation
assertCheck(
    str_contains($contents['feedback'], 'NOT YET ACKNOWLEDGED'),
    "COPY-023: Unacknowledged notice badge renders 'NOT YET ACKNOWLEDGED'"
);
assertCheck(
    str_contains($contents['feedback'], '<strong>Reason shared by Faculty:</strong>'),
    "COPY-023: Referral reason rendered as 'Reason shared by Faculty:'"
);
assertCheck(
    str_contains($contents['feedback'], 'Acknowledge Receipt'),
    "COPY-023: Student button uses 'Acknowledge Receipt'"
);
assertCheck(
    str_contains($contents['feedback'], 'Acknowledgment confirms receipt of this notice. It does not indicate agreement with the academic classification.'),
    "COPY-023: Receipt-only disclaimer remains intact"
);

echo "\n========================================================================\n";
echo "SUMMARY: Ran $totalTests tests, $failedTests failures.\n";
echo "========================================================================\n";

exit($failedTests > 0 ? 1 : 0);
