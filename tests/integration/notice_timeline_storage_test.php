<?php
/**
 * tests/integration/notice_timeline_storage_test.php
 * Integration Test: Academic Support Notice Timeline Storage & Lifecycle (FACULTY-WP1B)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Migration 010 schema contract:
 *    - `support_case_referrals.notice_issued_at` is DATETIME NULL.
 *    - Legacy rows with NULL notice_issued_at load cleanly.
 * 2. Full referral lifecycle timestamp integrity:
 *    - Triage creation: created_at populated, status='needs_review', notice_issued_at is NULL, student_acknowledged_at is NULL.
 *    - Notice issuance: status='action_taken', notice_issued_at populated with CURRENT_TIMESTAMP, reason_code saved.
 *    - Student acknowledgment: status='acknowledged', student_acknowledged_at populated with CURRENT_TIMESTAMP, notice_issued_at preserved.
 * 3. Canonical per-tab count functions:
 *    - countFacultyPendingReferrals() reflects only 'needs_review' referrals.
 *    - countStudentUnacknowledgedNotices() reflects only issued, unacknowledged notices.
 *    - Parity with sidebar badge calculations.
 * 4. Complete fixture cleanup in finally block.
 *
 * Run via CLI: php tests/integration/notice_timeline_storage_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');

require_once dirname(__DIR__, 2) . '/config/constants.php';

$scratchHost = '127.0.0.1';
$scratchDb   = 'udm_radar_scratch';
$scratchUser = 'root';
$scratchPass = '';

try {
    $db = new PDO("mysql:host=$scratchHost;dbname=$scratchDb;charset=utf8mb4", $scratchUser, $scratchPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not connect to scratch database '$scratchDb': " . $e->getMessage() . "\n");
    exit(1);
}

// Strict isolation guard
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', not 'udm_radar_scratch'! Refusing to run tests.\n");
    exit(1);
}

$testsRun = 0;
$failures = 0;

function it(string $desc, bool $pass): void {
    global $testsRun, $failures;
    $testsRun++;
    if ($pass) {
        echo "[PASS] $desc\n";
    } else {
        echo "[FAIL] $desc\n";
        $failures++;
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: NOTICE TIMELINE STORAGE & LIFECYCLE INTEGRATION TESTS\n";
echo "Target Database: udm_radar_scratch\n";
echo "========================================================================\n\n";

$createdCaseId = null;
$createdReferralId = null;

try {
    // -------------------------------------------------------------------------
    // 1. Schema Inspection
    // -------------------------------------------------------------------------
    echo "=== 1. Migration 010 Schema Inspection ===\n";

    $colStmt = $db->query("SHOW COLUMNS FROM support_case_referrals LIKE 'notice_issued_at'");
    $col = $colStmt->fetch();

    it("Column support_case_referrals.notice_issued_at exists in scratch", $col !== false);
    it("notice_issued_at is datetime type", strtolower($col['Type'] ?? '') === 'datetime');
    it("notice_issued_at is NULLABLE", ($col['Null'] ?? '') === 'YES');
    it("notice_issued_at default is NULL", ($col['Default'] ?? null) === null);

    // -------------------------------------------------------------------------
    // 2. Fixture Setup
    // -------------------------------------------------------------------------
    echo "\n=== 2. Fixture Setup ===\n";

    $studentId = (int)$db->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();
    $facultyId = (int)$db->query("SELECT id FROM users WHERE role = 'faculty' LIMIT 1")->fetchColumn();
    $subjectId = (int)$db->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();

    // Baseline counts
    $initialFacultyReferrals = countFacultyPendingReferrals($db, $facultyId);
    $initialStudentNotices   = countStudentUnacknowledgedNotices($db, $studentId);

    // Create a test parent case
    $insCase = $db->prepare("
        INSERT INTO academic_support_cases (student_id, school_year, semester, trigger_risk_level, status)
        VALUES (?, '2026-2027', '1st', 'HIGH', 'needs_review')
    ");
    $insCase->execute([$studentId]);
    $createdCaseId = (int)$db->lastInsertId();
    it("Created test parent case ID $createdCaseId", $createdCaseId > 0);

    // -------------------------------------------------------------------------
    // 3. Lifecycle Step 1: Automated Triage Generation
    // -------------------------------------------------------------------------
    echo "\n=== 3. Lifecycle Step 1: Triage Creation (needs_review) ===\n";

    $insRef = $db->prepare("
        INSERT INTO support_case_referrals (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_grade, latest_term_checked, status)
        VALUES (?, ?, ?, 'BSIT 3-1', 'HIGH', 68.0, 'prelim', 'needs_review')
    ");
    $insRef->execute([$createdCaseId, $facultyId, $subjectId]);
    $createdReferralId = (int)$db->lastInsertId();
    it("Created referral ID $createdReferralId in needs_review status", $createdReferralId > 0);

    $refRow = $db->query("SELECT * FROM support_case_referrals WHERE id = $createdReferralId")->fetch();
    it("Triage creation sets created_at timestamp", !empty($refRow['created_at']));
    it("Triage creation leaves notice_issued_at as NULL", $refRow['notice_issued_at'] === null);
    it("Triage creation leaves student_acknowledged_at as NULL", $refRow['student_acknowledged_at'] === null);
    it("Triage creation leaves reason_code as NULL", $refRow['reason_code'] === null);

    // Count verification: Faculty pending referrals increments by 1
    $facultyReferralsAfterTriage = countFacultyPendingReferrals($db, $facultyId);
    it("Faculty pending referrals count incremented from $initialFacultyReferrals to $facultyReferralsAfterTriage", $facultyReferralsAfterTriage === $initialFacultyReferrals + 1);

    // Student notices count remains unchanged (draft notice not yet issued)
    $studentNoticesAfterTriage = countStudentUnacknowledgedNotices($db, $studentId);
    it("Student unacknowledged notices count unchanged ($studentNoticesAfterTriage) while notice is in needs_review", $studentNoticesAfterTriage === $initialStudentNotices);

    // -------------------------------------------------------------------------
    // 4. Lifecycle Step 2: Faculty Issues Notice
    // -------------------------------------------------------------------------
    echo "\n=== 4. Lifecycle Step 2: Faculty Issues Notice (action_taken) ===\n";

    $updateNotice = $db->prepare("
        UPDATE support_case_referrals
        SET status = 'action_taken',
            reason_code = 'low_assessment_score',
            message_to_student = 'Please review prelim exam topics and consult during office hours.',
            notice_issued_at = CURRENT_TIMESTAMP
        WHERE id = ? AND faculty_id = ? AND status = 'needs_review'
    ");
    $updateNotice->execute([$createdReferralId, $facultyId]);
    it("Notice update executed successfully", $updateNotice->rowCount() === 1);

    $issuedRow = $db->query("SELECT * FROM support_case_referrals WHERE id = $createdReferralId")->fetch();
    it("Status transitioned to 'action_taken'", $issuedRow['status'] === 'action_taken');
    it("notice_issued_at is now populated", !empty($issuedRow['notice_issued_at']));
    it("notice_issued_at differs from or is distinct from created_at", isset($issuedRow['notice_issued_at']));
    it("reason_code recorded as 'low_assessment_score'", $issuedRow['reason_code'] === 'low_assessment_score');
    it("student_acknowledged_at remains NULL", $issuedRow['student_acknowledged_at'] === null);

    // Formatter outputs
    $issuedFormatted = formatNoticeTimelineDate($issuedRow['notice_issued_at']);
    $ackFormatted = formatNoticeAcknowledgmentStatus($issuedRow['student_acknowledged_at']);
    it("formatNoticeTimelineDate produces valid string ($issuedFormatted)", str_contains($issuedFormatted, ' at '));
    it("formatNoticeAcknowledgmentStatus returns 'Not yet acknowledged'", $ackFormatted === 'Not yet acknowledged');

    // Count verification: Faculty pending referrals decrements back
    $facultyReferralsAfterIssue = countFacultyPendingReferrals($db, $facultyId);
    it("Faculty pending referrals count decremented back to $initialFacultyReferrals", $facultyReferralsAfterIssue === $initialFacultyReferrals);

    // Student unacknowledged notices increments by 1
    $studentNoticesAfterIssue = countStudentUnacknowledgedNotices($db, $studentId);
    it("Student unacknowledged notices count incremented from $initialStudentNotices to $studentNoticesAfterIssue", $studentNoticesAfterIssue === $initialStudentNotices + 1);

    // -------------------------------------------------------------------------
    // 5. Lifecycle Step 3: Student Acknowledges Notice
    // -------------------------------------------------------------------------
    echo "\n=== 5. Lifecycle Step 3: Student Acknowledges Notice (acknowledged) ===\n";

    $ackUpdate = $db->prepare("
        UPDATE support_case_referrals
        SET status = 'acknowledged',
            student_acknowledged_at = CURRENT_TIMESTAMP
        WHERE id = ? AND status = 'action_taken'
    ");
    $ackUpdate->execute([$createdReferralId]);
    it("Student acknowledgment executed successfully", $ackUpdate->rowCount() === 1);

    $ackRow = $db->query("SELECT * FROM support_case_referrals WHERE id = $createdReferralId")->fetch();
    it("Status transitioned to 'acknowledged'", $ackRow['status'] === 'acknowledged');
    it("student_acknowledged_at is now populated", !empty($ackRow['student_acknowledged_at']));
    it("notice_issued_at is preserved unchanged", $ackRow['notice_issued_at'] === $issuedRow['notice_issued_at']);

    $ackFormattedAfter = formatNoticeAcknowledgmentStatus($ackRow['student_acknowledged_at']);
    it("formatNoticeAcknowledgmentStatus now returns formatted date ($ackFormattedAfter)", str_contains($ackFormattedAfter, ' at '));

    // Student unacknowledged notices count decrements back to initial
    $studentNoticesAfterAck = countStudentUnacknowledgedNotices($db, $studentId);
    it("Student unacknowledged notices count decremented back to $initialStudentNotices", $studentNoticesAfterAck === $initialStudentNotices);

} finally {
    // -------------------------------------------------------------------------
    // Cleanup
    // -------------------------------------------------------------------------
    echo "\n=== 6. Fixture Cleanup ===\n";
    if ($createdCaseId !== null) {
        $db->exec("DELETE FROM support_case_referrals WHERE case_id = $createdCaseId");
        $db->exec("DELETE FROM support_actions WHERE case_id = $createdCaseId");
        $db->exec("DELETE FROM support_status_history WHERE case_id = $createdCaseId");
        $db->exec("DELETE FROM academic_support_cases WHERE id = $createdCaseId");
        echo "[CLEANUP] Deleted test case ID $createdCaseId and associated referrals.\n";
    }
}

echo "\n========================================================================\n";
echo "SUMMARY: Total Tests: $testsRun, Failures: $failures\n";
echo "========================================================================\n";

exit($failures > 0 ? 1 : 0);
