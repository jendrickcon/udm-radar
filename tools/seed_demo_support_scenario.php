<?php
/**
 * tools/seed_demo_support_scenario.php — Seed Controlled Academic Support Demo Scenario (WP-7)
 *
 * PURPOSE:
 * Seeds a dedicated, isolated demonstration scenario tagged '[DEMO-SUPPORT-SCENARIO]'
 * strictly on udm_radar_scratch. It never alters real records or touches the live database.
 *
 * SCENARIO STRUCTURE:
 * - Student: Student 6 (Ranna Evangelista, User ID: 24-22-286, Section: IT-31)
 * - Faculty: Faculty 2 (Ronald Fernandez, User ID: faculty01)
 * - Parent Support Case: Status 'action_taken', high-risk trigger (GWA 1.70, calculation_fallback)
 * - Referral 1 (ITE331): Status 'acknowledged' (demonstrating completed faculty notice + student receipt)
 * - Referral 2 (ITE323): Status 'action_taken' (demonstrating active notice awaiting student acknowledgment)
 * - Support Action: 'academic_notice_sent' with timestamped acknowledgment
 * - Status History: Complete audit trail from 'needs_review' to 'action_taken'
 *
 * USAGE:
 *   php tools/seed_demo_support_scenario.php          # Seeds scenario (idempotent)
 *   php tools/seed_demo_support_scenario.php --clean  # Removes demo scenario cleanly
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

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

// Safety guard: ensure we are NOT connected to live production database
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Refusing to run! Connected database is '$currentDb', not 'udm_radar_scratch'.\n");
    exit(1);
}

$isClean = in_array('--clean', $argv, true);

echo "========================================================================\n";
echo "UDM-RADAR: DEMO SUPPORT SCENARIO SEEDER (DEMO-SUPPORT-SCENARIO)\n";
echo "Target Database: $scratchDb [SCRATCH ONLY]\n";
echo "Action: " . ($isClean ? "CLEANUP" : "SEED / RE-SEED") . "\n";
echo "========================================================================\n\n";

// 1. Clean up any existing demo records tagged with [DEMO-SUPPORT-SCENARIO]
$existingCases = $db->query("
    SELECT id FROM academic_support_cases 
    WHERE closure_note LIKE '%[DEMO-SUPPORT-SCENARIO]%'
")->fetchAll(PDO::FETCH_COLUMN);

if (!empty($existingCases)) {
    $inList = implode(',', array_map('intval', $existingCases));
    $db->exec("DELETE FROM support_status_history WHERE case_id IN ($inList)");
    $db->exec("DELETE FROM support_actions WHERE case_id IN ($inList)");
    $db->exec("DELETE FROM support_case_referrals WHERE case_id IN ($inList)");
    $db->exec("DELETE FROM academic_support_cases WHERE id IN ($inList)");
    echo "[CLEAN] Removed " . count($existingCases) . " previous demo support case(s) and associated referrals.\n";
} else {
    echo "[CLEAN] No existing demo scenario records found.\n";
}

if ($isClean) {
    echo "\nCleanup completed successfully.\n";
    exit(0);
}

// 2. Identify fixtures
$student = $db->query("
    SELECT u.id, u.user_id, u.name, sp.section
    FROM users u
    JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.id = 6 AND u.is_active = 1
")->fetch();

$faculty = $db->query("
    SELECT id, user_id, name, email
    FROM users
    WHERE id = 2 AND role = 'faculty' AND is_active = 1
")->fetch();

if (!$student || !$faculty) {
    fwrite(STDERR, "FATAL: Required fixtures (Student 6 or Faculty 2) missing in scratch database.\n");
    exit(1);
}

$term = getCurrentTerm();
$currentSy = $term['school_year'];
$currentSem = (int) $term['semester'];

// Find or assign class loads for demonstration subjects (ITE331 = 39, ITE323 = 38)
$subj1 = 39; // ITE331 System Analysis and Design
$subj2 = 38; // ITE323 Human Computer Interaction

// Ensure class loads exist for Faculty 2 in student section
$stmtLoadCheck = $db->prepare("SELECT COUNT(*) FROM faculty_class_loads WHERE faculty_user_id = ? AND subject_id = ? AND section = ?");
$stmtLoadCheck->execute([(int)$faculty['id'], $subj1, $student['section']]);
if ($stmtLoadCheck->fetchColumn() == 0) {
    $db->prepare("INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section) VALUES (?, ?, ?)")
       ->execute([(int)$faculty['id'], $subj1, $student['section']]);
}
$stmtLoadCheck->execute([(int)$faculty['id'], $subj2, $student['section']]);
if ($stmtLoadCheck->fetchColumn() == 0) {
    $db->prepare("INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section) VALUES (?, ?, ?)")
       ->execute([(int)$faculty['id'], $subj2, $student['section']]);
}

// 3. Create Parent Academic Support Case
$stmtParent = $db->prepare("
    INSERT INTO academic_support_cases
    (student_id, prediction_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, assigned_faculty_id, status, closure_note, created_at, updated_at)
    VALUES (?, NULL, 'HIGH', 1.70, 'calculation_fallback', ?, ?, ?, 'action_taken', '[DEMO-SUPPORT-SCENARIO] Capstone Demonstration Support Case', NOW(), NOW())
");
$stmtParent->execute([(int)$student['id'], $currentSy, $currentSem, (int)$faculty['id']]);
$caseId = (int)$db->lastInsertId();

// 4. Create Referral A (Subject 39 - ITE331: Already Acknowledged)
$stmtRefA = $db->prepare("
    INSERT INTO support_case_referrals
    (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status, message_to_student, student_acknowledged_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 72.00, 'acknowledged', '[DEMO-SUPPORT-SCENARIO] Please schedule a weekly peer tutoring session for System Analysis and Design.', NOW(), NOW(), NOW())
");
$stmtRefA->execute([$caseId, (int)$faculty['id'], $subj1, $student['section']]);
$refAId = (int)$db->lastInsertId();

// 5. Create Referral B (Subject 38 - ITE323: Action Taken, Ready for Student to Acknowledge)
$stmtRefB = $db->prepare("
    INSERT INTO support_case_referrals
    (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status, message_to_student, student_acknowledged_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 70.00, 'action_taken', '[DEMO-SUPPORT-SCENARIO] HCI consultation is scheduled for Thursday at 3:00 PM. Please confirm receipt.', NULL, NOW(), NOW())
");
$stmtRefB->execute([$caseId, (int)$faculty['id'], $subj2, $student['section']]);
$refBId = (int)$db->lastInsertId();

// 6. Record Support Action
$stmtAction = $db->prepare("
    INSERT INTO support_actions
    (case_id, actor_id, action_type, message_to_student, student_acknowledged_at, created_at)
    VALUES (?, ?, 'academic_notice_sent', '[DEMO-SUPPORT-SCENARIO] Academic Notice issued for System Analysis and Design.', NOW(), NOW())
");
$stmtAction->execute([$caseId, (int)$faculty['id']]);

// 7. Record Status Transition History
$stmtHist = $db->prepare("
    INSERT INTO support_status_history
    (case_id, changed_by, old_status, new_status, note, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmtHist->execute([$caseId, (int)$faculty['id'], 'needs_review', 'action_taken', '[DEMO-SUPPORT-SCENARIO] Faculty issued academic notices for enrolled subjects.']);

echo "[SUCCESS] Demo scenario seeded successfully!\n\n";
echo "=== Demonstration Details ===\n";
echo "  - Case ID:       $caseId (Status: action_taken)\n";
echo "  - Student:       {$student['name']} (ID: {$student['id']}, Student No: {$student['user_id']}, Section: {$student['section']})\n";
echo "  - Faculty:       {$faculty['name']} (ID: {$faculty['id']}, User ID: {$faculty['user_id']})\n";
echo "  - Referral 1:    ID $refAId | ITE331 | Status: acknowledged (Receipt confirmed)\n";
echo "  - Referral 2:    ID $refBId | ITE323 | Status: action_taken (Awaiting Student Acknowledgment in UI)\n";

echo "\n=== Demonstration Playbook Walkthrough ===\n";
echo "1. Faculty Portal (Login as '{$faculty['user_id']}' / password):\n";
echo "   - Navigate to 'Concerns & Reports' (faculty/feedback.php).\n";
echo "   - View the active referrals under 'Academic Support Referrals'.\n";
echo "2. Student Portal (Login as '{$student['user_id']}' / password):\n";
echo "   - Navigate to 'Feedback & Support' (student/feedback.php).\n";
echo "   - View the pending notice for ITE323 in 'Academic Support Notices'.\n";
echo "   - Click 'Acknowledge Receipt' to transition it live to 'acknowledged'!\n";
echo "3. Admin Portal (Login as 'admin' / password):\n";
echo "   - Navigate to 'Activity & Inbox' (admin/activity.php).\n";
echo "   - View Case #$caseId with both referrals and status audit history.\n";
echo "========================================================================\n";
