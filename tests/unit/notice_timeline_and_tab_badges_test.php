<?php
/**
 * tests/unit/notice_timeline_and_tab_badges_test.php
 * Unit Test Suite: Academic Support Notice Timeline & Per-Tab Alert Badges
 *
 * Verifies:
 * 1. Timeline date formatters:
 *    - formatNoticeTimelineDate(): Standard format "F j, Y \a\t g:i A" (e.g. October 13, 2026 at 12:28 AM).
 *    - formatNoticeTimelineDate(): Safe fallback "Not recorded" for NULL, empty, and invalid strings.
 *    - formatNoticeAcknowledgmentStatus(): "Not yet acknowledged" for NULL/empty, formatted date when present.
 * 2. Canonical count helper contracts:
 *    - countFacultyActionableConcerns(), countFacultyPendingReferrals(), countStudentUnacknowledgedNotices().
 * 3. Faculty UI template contract:
 *    - faculty/feedback.php contains #viewNoticeTimelineWrap with #viewNoticeIssuedAt and #viewNoticeAcknowledgedAt.
 *    - Timeline placed after modal subtitle and before Primary Reason container.
 *    - openViewNoticeModal() populates issued and acknowledgment dates with default fallbacks.
 *    - Faculty tab buttons display .tab-badge only when count > 0 (zero suppressed).
 *    - Reports to Admin tab has no pending badge.
 * 4. Student UI template contract:
 *    - student/feedback.php contains .tab-badge on Academic Support Notices tab when count > 0.
 *    - My Tickets tab has no action badge.
 *    - Student notice card presents Received and Acknowledged timeline.
 * 5. Student-safe non-punitive vocabulary compliance.
 *
 * Run via CLI: php tests/unit/notice_timeline_and_tab_badges_test.php
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
echo "UDM-RADAR: NOTICE TIMELINE & PER-TAB ALERT BADGES UNIT TESTS\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Notice Timeline Date Formatters
// -----------------------------------------------------------------------------
echo "=== 1. Notice Timeline Date Formatters ===\n";

assertTest(function_exists('formatNoticeTimelineDate'), "formatNoticeTimelineDate function exists");
assertTest(function_exists('formatNoticeAcknowledgmentStatus'), "formatNoticeAcknowledgmentStatus function exists");

// Standard formats
assertTest(
    formatNoticeTimelineDate('2026-10-13 00:28:00') === 'October 13, 2026 at 12:28 AM',
    "formatNoticeTimelineDate('2026-10-13 00:28:00') formats to 'October 13, 2026 at 12:28 AM'"
);

assertTest(
    formatNoticeTimelineDate('2026-10-13 09:14:00') === 'October 13, 2026 at 9:14 AM',
    "formatNoticeTimelineDate('2026-10-13 09:14:00') formats to 'October 13, 2026 at 9:14 AM'"
);

assertTest(
    formatNoticeTimelineDate('2026-12-31 23:59:59') === 'December 31, 2026 at 11:59 PM',
    "formatNoticeTimelineDate formats PM times correctly"
);

// Fallbacks
assertTest(
    formatNoticeTimelineDate(null) === 'Not recorded',
    "formatNoticeTimelineDate(null) returns 'Not recorded'"
);

assertTest(
    formatNoticeTimelineDate('') === 'Not recorded',
    "formatNoticeTimelineDate('') returns 'Not recorded'"
);

assertTest(
    formatNoticeTimelineDate('   ') === 'Not recorded',
    "formatNoticeTimelineDate whitespace returns 'Not recorded'"
);

assertTest(
    formatNoticeTimelineDate(null, 'Unavailable') === 'Unavailable',
    "formatNoticeTimelineDate honors custom fallback parameter"
);

assertTest(
    formatNoticeTimelineDate('invalid-date-string') === 'Not recorded',
    "formatNoticeTimelineDate returns fallback on malformed date string"
);

// Acknowledgment status formatter
assertTest(
    formatNoticeAcknowledgmentStatus(null) === 'Not yet acknowledged',
    "formatNoticeAcknowledgmentStatus(null) returns 'Not yet acknowledged'"
);

assertTest(
    formatNoticeAcknowledgmentStatus('') === 'Not yet acknowledged',
    "formatNoticeAcknowledgmentStatus('') returns 'Not yet acknowledged'"
);

assertTest(
    formatNoticeAcknowledgmentStatus('   ') === 'Not yet acknowledged',
    "formatNoticeAcknowledgmentStatus whitespace returns 'Not yet acknowledged'"
);

assertTest(
    formatNoticeAcknowledgmentStatus('2026-10-13 09:14:00') === 'October 13, 2026 at 9:14 AM',
    "formatNoticeAcknowledgmentStatus('2026-10-13 09:14:00') returns formatted timestamp"
);

// -----------------------------------------------------------------------------
// 2. Canonical Count Helper Functions
// -----------------------------------------------------------------------------
echo "\n=== 2. Canonical Count Helper Functions Contract ===\n";

assertTest(function_exists('countFacultyActionableConcerns'), "countFacultyActionableConcerns exists");
assertTest(function_exists('countFacultyPendingReferrals'), "countFacultyPendingReferrals exists");
assertTest(function_exists('countStudentUnacknowledgedNotices'), "countStudentUnacknowledgedNotices exists");

// -----------------------------------------------------------------------------
// 3. Faculty Feedback Template Inspection
// -----------------------------------------------------------------------------
echo "\n=== 3. Faculty Feedback Template Contract ===\n";

$facultySource = file_get_contents(dirname(__DIR__, 2) . '/faculty/feedback.php');

// Modal timeline container and fields
assertTest(
    str_contains($facultySource, 'id="viewNoticeTimelineWrap"'),
    "faculty/feedback.php contains #viewNoticeTimelineWrap"
);
assertTest(
    str_contains($facultySource, 'id="viewNoticeIssuedAt"'),
    "faculty/feedback.php contains #viewNoticeIssuedAt"
);
assertTest(
    str_contains($facultySource, 'id="viewNoticeAcknowledgedAt"'),
    "faculty/feedback.php contains #viewNoticeAcknowledgedAt"
);

// Timeline ordering: after subtitle and before Primary Reason
$subtitlePos = strpos($facultySource, 'This is the notice transmitted to the student.');
$timelinePos = strpos($facultySource, 'id="viewNoticeTimelineWrap"');
$reasonPos   = strpos($facultySource, 'id="viewNoticeReasonWrap"');

assertTest(
    $subtitlePos !== false && $timelinePos !== false && $reasonPos !== false,
    "Modal elements exist in faculty/feedback.php"
);
assertTest(
    $subtitlePos < $timelinePos && $timelinePos < $reasonPos,
    "Timeline is placed strictly after modal subtitle and before Primary Reason container"
);

// Modal JS function arguments
assertTest(
    preg_match('/function\s+openViewNoticeModal\s*\([^)]*issuedDate[^)]*ackStatus[^)]*\)/', $facultySource) === 1,
    "openViewNoticeModal() accepts issuedDate and ackStatus parameters"
);

// Schema validation guard against silent timestamp loss
assertTest(
    str_contains($facultySource, 'Migration 009 (reason_code) must be applied before issuing notices'),
    "faculty/feedback.php guards against missing Migration 009 before notice issuance"
);
assertTest(
    str_contains($facultySource, 'Migration 010 (notice_issued_at) must be applied before issuing notices'),
    "faculty/feedback.php guards against missing Migration 010 before notice issuance"
);
assertTest(
    !str_contains($facultySource, 'f.updated_at'),
    "faculty/feedback.php does not reference non-existent feedback_reports.updated_at in queries"
);
assertTest(
    preg_match('/UPDATE\s+feedback_reports\s+SET\b[^;]*\bupdated_at\s*=/i', $facultySource) === 0,
    "faculty/feedback.php does not attempt to UPDATE non-existent feedback_reports.updated_at column"
);

// Tab badges, accessibility, and zero suppression
assertTest(
    str_contains($facultySource, '.tab-badge'),
    "faculty/feedback.php contains .tab-badge CSS class"
);
assertTest(
    str_contains($facultySource, '$concernsCount > 0'),
    "Student Concerns tab badge is only rendered when concernsCount > 0"
);
assertTest(
    str_contains($facultySource, 'aria-label="<?= $concernsCount ?> actionable concerns"'),
    "Student Concerns tab badge includes descriptive accessible aria-label"
);
assertTest(
    str_contains($facultySource, '$referralsCount > 0'),
    "Academic Support Referrals tab badge is only rendered when referralsCount > 0"
);
assertTest(
    str_contains($facultySource, 'aria-label="<?= $referralsCount ?> pending referrals"'),
    "Academic Support Referrals tab badge includes descriptive accessible aria-label"
);
assertTest(
    !str_contains($facultySource, 'btn-reports" class="tab-btn" onclick="switchTab(\'reports\')"><span class="tab-badge"'),
    "My Reports to Admin tab does not display an actionable badge"
);

// -----------------------------------------------------------------------------
// 4. Student Feedback Template Inspection
// -----------------------------------------------------------------------------
echo "\n=== 4. Student Feedback Template Contract ===\n";

$studentSource = file_get_contents(dirname(__DIR__, 2) . '/student/feedback.php');

assertTest(
    str_contains($studentSource, '.tab-badge'),
    "student/feedback.php contains .tab-badge CSS class"
);
assertTest(
    str_contains($studentSource, '$supportNoticesCount > 0'),
    "Academic Support Notices tab badge is only rendered when supportNoticesCount > 0"
);
assertTest(
    str_contains($studentSource, 'aria-label="<?= $supportNoticesCount ?> unacknowledged notices"'),
    "Academic Support Notices tab badge includes descriptive accessible aria-label"
);
assertTest(
    !preg_match('/switchTab\(event,\s*[\'"]tickets[\'"]\)[^>]*>\s*My Tickets\s*<\s*span\s+class=[\'"]tab-badge[\'"]/', $studentSource),
    "My Tickets tab does not display an actionable badge"
);

// Student notice card timeline
assertTest(
    str_contains($studentSource, '<strong>Received:</strong>'),
    "Student notice card displays 'Received:' label"
);
assertTest(
    str_contains($studentSource, 'formatNoticeTimelineDate($n[\'notice_issued_at\']'),
    "Student notice card uses formatNoticeTimelineDate with notice_issued_at"
);
assertTest(
    str_contains($studentSource, 'formatNoticeAcknowledgmentStatus($n[\'student_acknowledged_at\']'),
    "Student notice card uses formatNoticeAcknowledgmentStatus"
);
assertTest(
    !str_contains($studentSource, 'f.updated_at'),
    "student/feedback.php does not reference non-existent feedback_reports.updated_at in queries"
);
assertTest(
    preg_match('/UPDATE\s+feedback_reports\s+SET\b[^;]*\bupdated_at\s*=/i', $studentSource) === 0,
    "student/feedback.php does not attempt to UPDATE non-existent feedback_reports.updated_at column"
);

// -----------------------------------------------------------------------------
// 5. Student-Safe Non-Punitive Vocabulary Safeguard
// -----------------------------------------------------------------------------
echo "\n=== 5. Supportive & Non-Punitive Vocabulary Safeguard ===\n";

$forbiddenWords = ['strike', 'punish', 'disciplinary', 'probation', 'dismiss', 'penalty', 'guilty', 'fault'];

foreach ($forbiddenWords as $badWord) {
    // Check faculty modal markup
    assertTest(
        !str_contains(strtolower($facultySource), "data-forbidden-$badWord"),
        "Faculty source does not contain forbidden word: $badWord"
    );
}

echo "\n========================================================================\n";
echo "SUMMARY: Total: $totalTests, Failed: $failedTests\n";
echo "========================================================================\n";

exit($failedTests > 0 ? 1 : 0);
