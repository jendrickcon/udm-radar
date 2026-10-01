<?php
/**
 * tools/seed_demo_support_scenario.php — Seed Controlled Synthetic Demo Scenario (WP-7 Closeout)
 *
 * PURPOSE:
 * Seeds a dedicated, isolated demonstration scenario tagged '[DEMO-SUPPORT-SCENARIO]'
 * strictly on udm_radar_demo (or optionally udm_radar_scratch with explicit protected override).
 * It NEVER alters real records or touches the live production database (udm_radar).
 *
 * CONTROLLED SYNTHETIC DEMONSTRATION STRUCTURE:
 * - Student: Demo Student A (User ID 6, Section IT-31)
 * - Faculty: Demo Faculty A (User ID 2)
 * - Parent Support Case: Status 'action_taken', high-risk trigger (GWA 1.70, calculation_fallback)
 * - Referral A (ITE331): Status 'acknowledged' (demonstrating completed faculty notice + student receipt)
 * - Referral B (ITE323): Status 'action_taken' (demonstrating active notice awaiting student acknowledgment)
 * - Support Action: 'academic_notice_sent' with timestamped student acknowledgment
 * - Status History: Complete audit trail from 'needs_review' to 'action_taken'
 *
 * USAGE:
 *   php tools/seed_demo_support_scenario.php                  # Seeds udm_radar_demo (default, idempotent)
 *   php tools/seed_demo_support_scenario.php --clean          # Removes demo scenario cleanly from udm_radar_demo
 *   php tools/seed_demo_support_scenario.php --allow-scratch  # Seeds udm_radar_scratch with protected override
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

// 1. Determine target database: default is udm_radar_demo
$targetDb = getenv('DEMO_DB_NAME') ?: 'udm_radar_demo';

// Override to scratch only if explicitly flagged
if (in_array('--allow-scratch', $argv, true)) {
    $targetDb = 'udm_radar_scratch';
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';

// Security guard: STRICTLY REFUSE live production database
if ($targetDb === 'udm_radar') {
    fwrite(STDERR, "FATAL: Refusing to run! Target cannot be production database 'udm_radar'.\n");
    exit(1);
}

// Security guard: Refuse udm_radar_scratch unless explicit --allow-scratch override is given
if ($targetDb === 'udm_radar_scratch' && !in_array('--allow-scratch', $argv, true)) {
    fwrite(STDERR, "FATAL: Refusing to seed into 'udm_radar_scratch' without explicit --allow-scratch override flag. Scratch database is reserved for clean migration and parity baseline.\n");
    exit(1);
}

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$targetDb;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not connect to target database '$targetDb': " . $e->getMessage() . "\n");
    exit(1);
}

$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== $targetDb) {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', expected '$targetDb'. Refusing to continue.\n");
    exit(1);
}

$isClean = in_array('--clean', $argv, true);

echo "========================================================================\n";
echo "UDM-RADAR: SYNTHETIC DEMONSTRATION SCENARIO SEEDER\n";
echo "Classification: Controlled Synthetic Demonstration Lifecycle\n";
echo "Target Database: $targetDb\n";
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
    echo "[CLEAN] Removed " . count($existingCases) . " previous synthetic demo case(s) and associated referrals.\n";
} else {
    echo "[CLEAN] No existing synthetic demo scenario records found.\n";
}

if ($isClean) {
    echo "\nControlled synthetic demonstration cleanup completed successfully.\n";
    exit(0);
}

// 2. Identify required synthetic test fixtures (pseudonymous student 6 and faculty 2)
$student = $db->query("
    SELECT u.id, sp.section
    FROM users u
    JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.id = 6 AND u.is_active = 1
")->fetch();

$faculty = $db->query("
    SELECT id
    FROM users
    WHERE id = 2 AND role = 'faculty' AND is_active = 1
")->fetch();

if (!$student || !$faculty) {
    fwrite(STDERR, "FATAL: Required synthetic fixtures (Student ID 6 or Faculty ID 2) missing in target database '$targetDb'.\n");
    exit(1);
}

$term = getCurrentTerm();
$currentSy = $term['school_year'];
$currentSem = (int)$term['semester'];

// 3. Find enrolled current subjects for Demo Student A
$stmtSubj = $db->prepare("
    SELECT g.subject_id, s.code, s.title
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.student_id = ? AND g.is_current = 1
    LIMIT 2
");
$stmtSubj->execute([$student['id']]);
$subjects = $stmtSubj->fetchAll();

if (count($subjects) < 2) {
    $anySubjects = $db->query("SELECT id, code, title FROM subjects LIMIT 2")->fetchAll();
    $subj1 = (int)$anySubjects[0]['id'];
    $subj2 = (int)$anySubjects[1]['id'];
} else {
    $subj1 = (int)$subjects[0]['subject_id'];
    $subj2 = (int)$subjects[1]['subject_id'];
}

// 4. Create Parent Academic Support Case
$stmtCase = $db->prepare("
    INSERT INTO academic_support_cases
    (student_id, prediction_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, assigned_faculty_id, status, closure_note, created_at, updated_at)
    VALUES (?, NULL, 'HIGH', 1.70, 'calculation_fallback', ?, ?, ?, 'action_taken', '[DEMO-SUPPORT-SCENARIO] Controlled synthetic demonstration case for academic intervention workflow.', NOW(), NOW())
");
$stmtCase->execute([
    (int)$student['id'],
    $currentSy,
    $currentSem,
    (int)$faculty['id']
]);
$caseId = (int)$db->lastInsertId();

// 5. Create Child Referral A (Completed: Notice sent + Student acknowledged)
$stmtRefA = $db->prepare("
    INSERT INTO support_case_referrals
    (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status, message_to_student, student_acknowledged_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 72.00, 'acknowledged', '[DEMO-SUPPORT-SCENARIO] Scheduled consultation completed. Study plan established.', NOW(), NOW(), NOW())
");
$stmtRefA->execute([$caseId, (int)$faculty['id'], $subj1, $student['section']]);
$refAId = (int)$db->lastInsertId();

// 6. Create Child Referral B (Pending: Notice sent, awaiting Student acknowledgment)
$stmtRefB = $db->prepare("
    INSERT INTO support_case_referrals
    (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status, message_to_student, student_acknowledged_at, created_at, updated_at)
    VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 70.00, 'action_taken', '[DEMO-SUPPORT-SCENARIO] Subject consultation scheduled. Please confirm receipt in your student portal.', NULL, NOW(), NOW())
");
$stmtRefB->execute([$caseId, (int)$faculty['id'], $subj2, $student['section']]);
$refBId = (int)$db->lastInsertId();

// 7. Record Support Action
$stmtAction = $db->prepare("
    INSERT INTO support_actions
    (case_id, actor_id, action_type, message_to_student, student_acknowledged_at, created_at)
    VALUES (?, ?, 'academic_notice_sent', '[DEMO-SUPPORT-SCENARIO] Academic Notice issued for enrolled course.', NOW(), NOW())
");
$stmtAction->execute([$caseId, (int)$faculty['id']]);

// 8. Record Status Transition History
$stmtHist = $db->prepare("
    INSERT INTO support_status_history
    (case_id, changed_by, old_status, new_status, note, created_at)
    VALUES (?, ?, 'needs_review', 'action_taken', '[DEMO-SUPPORT-SCENARIO] Faculty issued academic notices for enrolled subjects.', NOW())
");
$stmtHist->execute([$caseId, (int)$faculty['id']]);

echo "[SUCCESS] Controlled synthetic demonstration scenario seeded successfully in '$targetDb'!\n\n";
echo "=== Synthetic Demonstration Details ===\n";
echo "  - Case ID:       $caseId (Status: action_taken)\n";
echo "  - Student:       Demo Student A (Internal ID: {$student['id']}, Section: {$student['section']})\n";
echo "  - Faculty:       Demo Faculty A (Internal ID: {$faculty['id']})\n";
echo "  - Referral A:    ID $refAId (Status: acknowledged - student receipt confirmed)\n";
echo "  - Referral B:    ID $refBId (Status: action_taken - awaiting student acknowledgment)\n";
echo "\nClassification: Synthetic presentation scenario. Not natural institutional records.\n";
echo "========================================================================\n";
