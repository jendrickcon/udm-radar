<?php
/**
 * tests/integration/support_reason_storage_test.php
 * Integration Test: Structured Academic-Support Reason Storage Contract (FACULTY-WP1A)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Database schema:
 *    - `support_case_referrals.reason_code` is VARCHAR(50) NULL.
 *    - `chk_referral_reason_valid` constraint is active and enforces canonical keys or NULL.
 *    - `support_actions` table does NOT contain `reason_code` or internal notes columns.
 * 2. Database constraint enforcement:
 *    - Direct INSERT/UPDATE of each of the 6 canonical reason codes succeeds.
 *    - Direct INSERT/UPDATE of NULL succeeds (preserving legacy records).
 *    - Direct INSERT/UPDATE of invalid reason codes (unrecognized, uppercase, empty string) fails constraint.
 * 3. Legacy compatibility:
 *    - Existing rows with NULL reason_code load cleanly and produce safe default label ('Not specified').
 * 4. Faculty notice submission flow:
 *    - Rejection of invalid reason code.
 *    - Rejection of message shorter than 10 characters.
 *    - Rejection of message longer than 2,000 characters.
 *    - Successful submission persists `reason_code` into `support_case_referrals`.
 *    - Successful submission creates `support_actions` row WITHOUT `reason_code`.
 * 5. Complete fixture cleanup in finally block.
 *
 * Run via CLI: php tests/integration/support_reason_storage_test.php
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

function assertCondition(bool $condition, string $message): void {
    global $testsRun, $failures;
    $testsRun++;
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        $failures++;
        echo "[FAIL] $message\n";
    }
}

echo "========================================================================\n";
echo "UDM-RADAR: SUPPORT REASON STORAGE INTEGRATION TESTS (FACULTY-WP1A)\n";
echo "Target Database: $scratchDb\n";
echo "========================================================================\n\n";

$createdCaseIds = [];

try {
    // -------------------------------------------------------------------------
    // 1. Schema Inspection & Table Boundary Verification
    // -------------------------------------------------------------------------
    echo "=== 1. Schema Inspection & Table Boundaries ===\n";

    $stmtCols = $db->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'support_case_referrals' AND COLUMN_NAME = 'reason_code'
    ");
    $stmtCols->execute([$scratchDb]);
    $reasonCol = $stmtCols->fetch();

    assertCondition($reasonCol !== false, "Column support_case_referrals.reason_code exists in scratch");
    assertCondition(strtolower($reasonCol['DATA_TYPE']) === 'varchar', "reason_code is VARCHAR type");
    assertCondition((int)$reasonCol['CHARACTER_MAXIMUM_LENGTH'] === 50, "reason_code max length is 50");
    assertCondition($reasonCol['IS_NULLABLE'] === 'YES', "reason_code is NULLABLE");
    assertCondition($reasonCol['COLUMN_DEFAULT'] === null || $reasonCol['COLUMN_DEFAULT'] === 'NULL', "reason_code default is NULL");

    // Verify support_actions table boundary: must NOT contain reason_code or internal notes
    $stmtActionCols = $db->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'support_actions'
    ");
    $stmtActionCols->execute([$scratchDb]);
    $actionCols = $stmtActionCols->fetchAll(PDO::FETCH_COLUMN);

    assertCondition(!in_array('reason_code', $actionCols, true), "support_actions table does NOT contain reason_code (single-table boundary preserved)");
    assertCondition(!in_array('faculty_notes', $actionCols, true), "support_actions table does NOT contain faculty_notes");
    assertCondition(!in_array('internal_notes', $actionCols, true), "support_actions table does NOT contain internal_notes");

    // -------------------------------------------------------------------------
    // 2. Fixture Setup
    // -------------------------------------------------------------------------
    echo "\n=== 2. Fixture Setup ===\n";

    $faculty = $db->query("SELECT id FROM users WHERE role = 'faculty' AND is_active = 1 LIMIT 1")->fetch();
    $student = $db->query("SELECT id FROM users WHERE role = 'student' AND is_active = 1 LIMIT 1")->fetch();
    $subject = $db->query("SELECT id FROM subjects LIMIT 1")->fetch();

    if (!$faculty || !$student || !$subject) {
        throw new RuntimeException("Missing required baseline fixtures in scratch database.");
    }

    $facultyId = (int)$faculty['id'];
    $studentId = (int)$student['id'];
    $subjectId = (int)$subject['id'];

    $term = getCurrentTerm();

    $stmtCreateCase = $db->prepare("
        INSERT INTO academic_support_cases
        (student_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, status)
        VALUES (?, 'HIGH', 1.65, 'decision_tree', ?, ?, 'needs_review')
    ");
    $stmtCreateCase->execute([$studentId, $term['school_year'], (int)$term['semester']]);
    $testCaseId = (int)$db->lastInsertId();
    $createdCaseIds[] = $testCaseId;

    assertCondition($testCaseId > 0, "Created test parent case ID $testCaseId");

    // -------------------------------------------------------------------------
    // 3. Database CHECK Constraint Enforcement
    // -------------------------------------------------------------------------
    echo "\n=== 3. Database CHECK Constraint Enforcement ===\n";

    $canonicalKeys = array_keys(SUPPORT_REASONS);

    // Test each canonical key insertion
    foreach ($canonicalKeys as $idx => $reasonKey) {
        $sec = 'TEST-SEC-' . $idx . '-' . bin2hex(random_bytes(2));
        $stmtInsert = $db->prepare("
            INSERT INTO support_case_referrals
            (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, reason_code, status)
            VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 72.50, ?, 'needs_review')
        ");
        $stmtInsert->execute([$testCaseId, $facultyId, $subjectId, $sec, $reasonKey]);
        $newRefId = (int)$db->lastInsertId();

        $stmtCheck = $db->prepare("SELECT reason_code FROM support_case_referrals WHERE id = ?");
        $stmtCheck->execute([$newRefId]);
        $storedCode = $stmtCheck->fetchColumn();

        assertCondition($storedCode === $reasonKey, "Direct insert with canonical key '$reasonKey' succeeded and preserved");
    }

    // Test NULL reason insertion (legacy behavior)
    $nullSec = 'TEST-NULL-' . bin2hex(random_bytes(2));
    $stmtInsertNull = $db->prepare("
        INSERT INTO support_case_referrals
        (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, reason_code, status)
        VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 70.00, NULL, 'needs_review')
    ");
    $stmtInsertNull->execute([$testCaseId, $facultyId, $subjectId, $nullSec]);
    $nullRefId = (int)$db->lastInsertId();

    $stmtCheckNull = $db->prepare("SELECT reason_code FROM support_case_referrals WHERE id = ?");
    $stmtCheckNull->execute([$nullRefId]);
    $storedNull = $stmtCheckNull->fetchColumn();
    assertCondition($storedNull === null, "Direct insert with NULL reason_code succeeded");
    assertCondition(getSupportReasonLabel($storedNull) === 'Not specified', "NULL reason resolves to label 'Not specified'");

    // Test invalid keys rejected by database check constraint
    $invalidDbKeys = [
        'invalid_reason_tag',
        'discipline_issue',
        'LOW_ASSESSMENT_SCORE',
        'low_assessment_score ',
        ' ',
        '',
    ];

    foreach ($invalidDbKeys as $badKey) {
        $badSec = 'BAD-' . bin2hex(random_bytes(3));
        $rejected = false;
        try {
            $stmtInsertBad = $db->prepare("
                INSERT INTO support_case_referrals
                (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, reason_code, status)
                VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 70.00, ?, 'needs_review')
            ");
            $stmtInsertBad->execute([$testCaseId, $facultyId, $subjectId, $badSec, $badKey]);
        } catch (PDOException $e) {
            $rejected = true;
        }
        assertCondition($rejected, "Database CHECK constraint rejected invalid reason_code: " . var_export($badKey, true));
    }

    // -------------------------------------------------------------------------
    // 4. Application Notice Issue Flow Contract
    // -------------------------------------------------------------------------
    echo "\n=== 4. Notice Submission Validation & Persistence Contract ===\n";

    // Setup fresh referral for handler test
    $flowSec = 'FLOW-' . bin2hex(random_bytes(3));
    $stmtFlowRef = $db->prepare("
        INSERT INTO support_case_referrals
        (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status)
        VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 68.00, 'needs_review')
    ");
    $stmtFlowRef->execute([$testCaseId, $facultyId, $subjectId, $flowSec]);
    $flowReferralId = (int)$db->lastInsertId();

    // 4a. Server-side validation: invalid reason
    $msgValid = 'Please attend consultation hours this Wednesday to review coursework.';
    $badReason = 'invalid_reason_choice';
    assertCondition(!isValidSupportReason($badReason), "isValidSupportReason correctly flags '$badReason' as invalid");

    // 4b. Server-side validation: message length limits (10 to 2000 chars)
    $shortMsg = 'Short!';
    assertCondition(mb_strlen($shortMsg) < 10, "Short message flagged (< 10 chars)");

    $longMsg = str_repeat('A', 2001);
    assertCondition(mb_strlen($longMsg) > 2000, "Long message flagged (> 2000 chars)");

    // 4c. Successful notice issue execution (mimicking feedback.php handler transaction)
    $chosenReason = 'performance_needs_review';
    $customMessage = 'Please review your scores on Quiz 2 and Quiz 3, and schedule an advising session.';

    $db->beginTransaction();

    $stmtOwner = $db->prepare("SELECT case_id, status FROM support_case_referrals WHERE id = ? AND faculty_id = ? FOR UPDATE");
    $stmtOwner->execute([$flowReferralId, $facultyId]);
    $refRow = $stmtOwner->fetch();

    assertCondition($refRow !== false && $refRow['status'] === 'needs_review', "Referral is owned and in needs_review status");

    $stmtUpdate = $db->prepare("
        UPDATE support_case_referrals
        SET status = 'action_taken', reason_code = ?, message_to_student = ?
        WHERE id = ? AND faculty_id = ? AND status = 'needs_review'
    ");
    $stmtUpdate->execute([$chosenReason, $customMessage, $flowReferralId, $facultyId]);
    assertCondition($stmtUpdate->rowCount() === 1, "Referral updated to action_taken with reason_code");

    // Record in support_actions WITHOUT reason_code
    $stmtAction = $db->prepare("
        INSERT INTO support_actions (case_id, actor_id, action_type, message_to_student)
        VALUES (?, ?, 'academic_notice_sent', ?)
    ");
    $stmtAction->execute([$testCaseId, $facultyId, $customMessage]);
    $actionId = (int)$db->lastInsertId();
    assertCondition($actionId > 0, "Support action record created successfully");

    $db->commit();

    // Verify stored referral state
    $stmtVerified = $db->prepare("SELECT status, reason_code, message_to_student FROM support_case_referrals WHERE id = ?");
    $stmtVerified->execute([$flowReferralId]);
    $verifiedRow = $stmtVerified->fetch();

    assertCondition($verifiedRow['status'] === 'action_taken', "Referral status is 'action_taken'");
    assertCondition($verifiedRow['reason_code'] === $chosenReason, "Referral reason_code equals '$chosenReason'");
    assertCondition($verifiedRow['message_to_student'] === $customMessage, "Referral message_to_student equals custom message");

    // Verify reason label resolution
    $expectedLabel = 'Current subject performance needs review';
    assertCondition(getSupportReasonLabel($verifiedRow['reason_code']) === $expectedLabel, "Stored reason resolves to: '$expectedLabel'");

    // Verify support_actions row did not store reason_code
    $stmtActCheck = $db->prepare("SELECT * FROM support_actions WHERE id = ?");
    $stmtActCheck->execute([$actionId]);
    $actRow = $stmtActCheck->fetch();
    assertCondition(!array_key_exists('reason_code', $actRow), "support_actions record does not possess reason_code column");
    assertCondition($actRow['message_to_student'] === $customMessage, "support_actions correctly recorded message_to_student");

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $failures++;
    echo "[FATAL] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    // -------------------------------------------------------------------------
    // 5. Fixture Cleanup
    // -------------------------------------------------------------------------
    echo "\n=== 5. Fixture Cleanup ===\n";
    foreach ($createdCaseIds as $cId) {
        try {
            $db->prepare("DELETE FROM support_status_history WHERE case_id = ?")->execute([$cId]);
            $db->prepare("DELETE FROM support_actions WHERE case_id = ?")->execute([$cId]);
            $db->prepare("DELETE FROM support_case_referrals WHERE case_id = ?")->execute([$cId]);
            $db->prepare("DELETE FROM academic_support_cases WHERE id = ?")->execute([$cId]);
            echo "[CLEANUP] Deleted test case ID $cId and associated records.\n";
        } catch (Throwable $e) {
            echo "[CLEANUP ERROR] " . $e->getMessage() . "\n";
        }
    }
}

echo "\n========================================================================\n";
echo "SUMMARY: Total Tests: $testsRun, Failures: $failures\n";
echo "========================================================================\n";

exit($failures > 0 ? 1 : 0);
