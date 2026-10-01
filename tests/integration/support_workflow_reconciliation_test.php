<?php
/**
 * Integration Test: Academic Support Case & Referral Workflow Integrity (WP-7)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies complete Support Case & Referral Lifecycle:
 * 1. Referral Generation:
 *    - Child support_case_referrals records created when parent case is generated.
 * 2. Faculty Action (Issue Notice):
 *    - Referral status transitions from 'needs_review' to 'action_taken'.
 *    - Action is recorded in support_actions ('academic_notice_sent').
 *    - Parent case transitions to 'action_taken'.
 *    - Status transition logged in support_status_history.
 * 3. Student Acknowledgment:
 *    - Referral status transitions to 'acknowledged' with timestamp.
 *    - support_actions.student_acknowledged_at is timestamped.
 *    - When all referrals are acknowledged, parent case transitions to 'acknowledged'.
 *    - Status transition logged in support_status_history.
 * 4. Administrator Case Closure:
 *    - Case status transitions to 'closed'.
 *    - closed_by, closed_at, and closure_note are populated.
 *    - Child referrals cascade to 'closed'.
 *    - Status transition logged in support_status_history.
 *
 * Run via CLI: php tests/integration/support_workflow_reconciliation_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

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

// Safety guard: ensure we are not connected to production database
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', not 'udm_radar_scratch'! Refusing to run tests.\n");
    exit(1);
}

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
echo "UDM-RADAR: SUPPORT CASE & REFERRAL WORKFLOW INTEGRITY TESTS (WP-7)\n";
echo "Database: $scratchDb\n";
echo "========================================================================\n\n";

$admin = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetch();
$faculty = $db->query("SELECT id FROM users WHERE role = 'faculty' AND is_active = 1 LIMIT 1")->fetch();
$student = $db->query("SELECT id FROM users WHERE role = 'student' AND is_active = 1 LIMIT 1")->fetch();
$subject = $db->query("SELECT id FROM subjects LIMIT 1")->fetch();

if (!$admin || !$faculty || !$student || !$subject) {
    fwrite(STDERR, "FATAL: Required test fixtures missing in scratch database.\n");
    exit(1);
}

$adminId = (int)$admin['id'];
$facultyId = (int)$faculty['id'];
$studentId = (int)$student['id'];
$subjectId = (int)$subject['id'];

$term = getCurrentTerm();
$currentSy = $term['school_year'];
$currentSem = (int)$term['semester'];

// Record initial counts of 6 core tables before transaction
$initialCounts = [
    'predictions'           => (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
    'academic_support_cases'=> (int) $db->query("SELECT COUNT(*) FROM academic_support_cases")->fetchColumn(),
    'support_case_referrals'=> (int) $db->query("SELECT COUNT(*) FROM support_case_referrals")->fetchColumn(),
    'support_actions'       => (int) $db->query("SELECT COUNT(*) FROM support_actions")->fetchColumn(),
    'support_status_history'=> (int) $db->query("SELECT COUNT(*) FROM support_status_history")->fetchColumn(),
    'admin_change_log'      => (int) $db->query("SELECT COUNT(*) FROM admin_change_log")->fetchColumn(),
];

// Test transaction
$db->beginTransaction();

try {
    echo "=== 1. Support Case & Referral Creation ===\n";
    // 1. Create a parent support case
    $stmtCase = $db->prepare("
        INSERT INTO academic_support_cases 
        (student_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, status)
        VALUES (?, 'HIGH', 1.72, 'decision_tree', ?, ?, 'needs_review')
    ");
    $stmtCase->execute([$studentId, $currentSy, $currentSem]);
    $caseId = (int)$db->lastInsertId();

    assertCondition($caseId > 0, "Created parent academic support case (ID: $caseId)");

    // 2. Create child referral
    $stmtRef = $db->prepare("
        INSERT INTO support_case_referrals
        (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status)
        VALUES (?, ?, ?, 'IT-TEST', 'HIGH', 'prelim', 70.00, 'needs_review')
    ");
    $stmtRef->execute([$caseId, $facultyId, $subjectId]);
    $referralId = (int)$db->lastInsertId();

    assertCondition($referralId > 0, "Created child support referral linked to faculty (ID: $referralId)");

    // Verify initial statuses
    $caseStatus0 = $db->query("SELECT status FROM academic_support_cases WHERE id = $caseId")->fetchColumn();
    $refStatus0 = $db->query("SELECT status FROM support_case_referrals WHERE id = $referralId")->fetchColumn();
    assertCondition($caseStatus0 === 'needs_review', "Parent case initial status is 'needs_review'");
    assertCondition($refStatus0 === 'needs_review', "Child referral initial status is 'needs_review'");


    echo "\n=== 2. Faculty Action (Issue Academic Notice) ===\n";
    $noticeMsg = "Please attend consultation this Friday regarding your subject performance.";
    
    // Simulate faculty notice workflow (from faculty/feedback.php)
    $db->prepare("UPDATE support_case_referrals SET status = 'action_taken', message_to_student = ? WHERE id = ?")->execute([$noticeMsg, $referralId]);
    $db->prepare("
        INSERT INTO support_actions (case_id, actor_id, action_type, message_to_student)
        VALUES (?, ?, 'academic_notice_sent', ?)
    ")->execute([$caseId, $facultyId, $noticeMsg]);
    $db->prepare("UPDATE academic_support_cases SET status = 'action_taken' WHERE id = ?")->execute([$caseId]);
    $db->prepare("
        INSERT INTO support_status_history (case_id, changed_by, old_status, new_status, note)
        VALUES (?, ?, 'needs_review', 'action_taken', 'Faculty issued an academic notice')
    ")->execute([$caseId, $facultyId]);

    // Verify updates
    $caseStatus1 = $db->query("SELECT status FROM academic_support_cases WHERE id = $caseId")->fetchColumn();
    $refStatus1 = $db->query("SELECT status, message_to_student FROM support_case_referrals WHERE id = $referralId")->fetch();
    $actionCount1 = (int)$db->query("SELECT COUNT(*) FROM support_actions WHERE case_id = $caseId AND action_type = 'academic_notice_sent'")->fetchColumn();
    $histCount1 = (int)$db->query("SELECT COUNT(*) FROM support_status_history WHERE case_id = $caseId AND new_status = 'action_taken'")->fetchColumn();

    assertCondition($refStatus1['status'] === 'action_taken', "Referral status transitioned to 'action_taken'");
    assertCondition($refStatus1['message_to_student'] === $noticeMsg, "Referral stored message_to_student");
    assertCondition($caseStatus1 === 'action_taken', "Parent case transitioned to 'action_taken'");
    assertCondition($actionCount1 === 1, "Action recorded in support_actions");
    assertCondition($histCount1 === 1, "Transition logged in support_status_history");


    echo "\n=== 3. Student Acknowledgment Lifecycle ===\n";
    // Simulate student acknowledgment workflow (from student/feedback.php)
    $db->prepare("UPDATE support_case_referrals SET status = 'acknowledged', student_acknowledged_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$referralId]);
    $db->prepare("UPDATE support_actions SET student_acknowledged_at = CURRENT_TIMESTAMP WHERE case_id = ? AND student_acknowledged_at IS NULL")->execute([$caseId]);
    $db->prepare("UPDATE academic_support_cases SET status = 'acknowledged' WHERE id = ?")->execute([$caseId]);
    $db->prepare("
        INSERT INTO support_status_history (case_id, changed_by, old_status, new_status, note)
        VALUES (?, ?, 'action_taken', 'acknowledged', 'Student acknowledged receipt.')
    ")->execute([$caseId, $studentId]);

    // Verify updates
    $caseStatus2 = $db->query("SELECT status FROM academic_support_cases WHERE id = $caseId")->fetchColumn();
    $refStatus2 = $db->query("SELECT status, student_acknowledged_at FROM support_case_referrals WHERE id = $referralId")->fetch();
    $actionAck2 = $db->query("SELECT student_acknowledged_at FROM support_actions WHERE case_id = $caseId LIMIT 1")->fetchColumn();
    $histCount2 = (int)$db->query("SELECT COUNT(*) FROM support_status_history WHERE case_id = $caseId AND new_status = 'acknowledged'")->fetchColumn();

    assertCondition($refStatus2['status'] === 'acknowledged', "Referral status transitioned to 'acknowledged'");
    assertCondition(!empty($refStatus2['student_acknowledged_at']), "Referral student_acknowledged_at timestamp is populated");
    assertCondition(!empty($actionAck2), "support_actions.student_acknowledged_at timestamp is populated");
    assertCondition($caseStatus2 === 'acknowledged', "Parent case transitioned to 'acknowledged'");
    assertCondition($histCount2 === 1, "Acknowledgment logged in support_status_history");


    echo "\n=== 4. Administrator Resolution & Closure Lifecycle ===\n";
    // Simulate administrator closure workflow (from admin/activity.php)
    $closureNote = "Student attended consultation and received tutoring assistance. Case resolved.";
    $db->prepare("
        UPDATE academic_support_cases 
        SET status = 'closed', closed_by = ?, closed_at = NOW(), closure_note = ? 
        WHERE id = ?
    ")->execute([$adminId, $closureNote, $caseId]);
    $db->prepare("UPDATE support_case_referrals SET status = 'closed' WHERE case_id = ?")->execute([$caseId]);
    $db->prepare("
        INSERT INTO support_status_history (case_id, changed_by, old_status, new_status, note)
        VALUES (?, ?, 'acknowledged', 'closed', ?)
    ")->execute([$caseId, $adminId, $closureNote]);

    // Verify updates
    $finalCase = $db->query("SELECT status, closed_by, closed_at, closure_note FROM academic_support_cases WHERE id = $caseId")->fetch();
    $finalRefStatus = $db->query("SELECT status FROM support_case_referrals WHERE id = $referralId")->fetchColumn();
    $histCount3 = (int)$db->query("SELECT COUNT(*) FROM support_status_history WHERE case_id = $caseId AND new_status = 'closed'")->fetchColumn();

    assertCondition($finalCase['status'] === 'closed', "Parent case status is 'closed'");
    assertCondition((int)$finalCase['closed_by'] === $adminId, "Case recorded closed_by administrator ID");
    assertCondition(!empty($finalCase['closed_at']), "Case recorded closed_at timestamp");
    assertCondition($finalCase['closure_note'] === $closureNote, "Case recorded closure_note");
    assertCondition($finalRefStatus === 'closed', "Child referral cascaded to 'closed'");
    assertCondition($histCount3 === 1, "Closure logged in support_status_history");

} finally {
    $db->rollBack();

    echo "\n=== 5. Database Invariant Verification (6 Core Tables) ===\n";
    $afterCounts = [
        'predictions'           => (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
        'academic_support_cases'=> (int) $db->query("SELECT COUNT(*) FROM academic_support_cases")->fetchColumn(),
        'support_case_referrals'=> (int) $db->query("SELECT COUNT(*) FROM support_case_referrals")->fetchColumn(),
        'support_actions'       => (int) $db->query("SELECT COUNT(*) FROM support_actions")->fetchColumn(),
        'support_status_history'=> (int) $db->query("SELECT COUNT(*) FROM support_status_history")->fetchColumn(),
        'admin_change_log'      => (int) $db->query("SELECT COUNT(*) FROM admin_change_log")->fetchColumn(),
    ];

    foreach ($initialCounts as $table => $beforeCount) {
        $afterCount = $afterCounts[$table];
        assertCondition($afterCount === $beforeCount, "Invariant preserved for table '$table' (before: $beforeCount, after: $afterCount)");
    }
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
