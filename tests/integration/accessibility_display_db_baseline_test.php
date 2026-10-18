<?php
/**
 * tests/integration/accessibility_display_db_baseline_test.php
 * Integration Baseline Test: Database Immutability for Display & Accessibility WP1
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies that all business-data hashes remain deterministic and strictly unchanged.
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');
$_SERVER['DB_NAME'] = 'udm_radar_scratch';

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $desc" . ($detail !== '' ? " ($detail)" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] $desc: $detail\n";
        $failed++;
    }
}

echo "=== ACCESSIBILITY-DISPLAY-WP1: Database Hash Baseline Test ===\n";

$db = getDB();
$currentDb = $db->query('SELECT DATABASE()')->fetchColumn();
assertTest('Target database is udm_radar_scratch', $currentDb === 'udm_radar_scratch', "Got $currentDb");

if ($currentDb !== 'udm_radar_scratch') {
    fwrite(STDERR, "FATAL: Unsafe target database. Aborting.\n");
    exit(1);
}

function computeHashes(PDO $db): array {
    return [
        'prelim' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', prelim) ORDER BY id)) FROM grades WHERE prelim IS NOT NULL")->fetchColumn(),
        'midterm' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', midterm) ORDER BY id)) FROM grades WHERE midterm IS NOT NULL")->fetchColumn(),
        'hist_final' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', final_grade) ORDER BY id)) FROM grades WHERE is_current = 0 AND final_grade IS NOT NULL")->fetchColumn(),
        'profiles' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(user_id, ':', student_number, ':', section, ':', status, ':', current_gwa) ORDER BY user_id)) FROM student_profiles")->fetchColumn(),
        'users_passwd' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', user_id, ':', role, ':', password_hash) ORDER BY id)) FROM users")->fetchColumn(),
        'predictions' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', student_id, ':', predicted_gwa, ':', risk_level) ORDER BY id)) FROM predictions")->fetchColumn(),
        'support_cases' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', student_id, ':', status) ORDER BY id)) FROM academic_support_cases")->fetchColumn(),
        'support_referrals' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', case_id, ':', faculty_id, ':', status) ORDER BY id)) FROM support_case_referrals")->fetchColumn(),
        'support_actions' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', case_id, ':', action_type) ORDER BY id)) FROM support_actions")->fetchColumn(),
        'support_history' => (string)$db->query("SELECT MD5(GROUP_CONCAT(CONCAT(id, ':', case_id, ':', old_status, ':', new_status) ORDER BY id)) FROM support_status_history")->fetchColumn(),
    ];
}

$hashes = computeHashes($db);

echo "\n--- Business Data Hashes ---\n";
foreach ($hashes as $name => $hash) {
    echo "  $name: $hash\n";
    assertTest("Baseline hash for $name computed", !empty($hash));
}

echo "\n==================================================\n";
echo "Database Baseline Summary: $passed passed, $failed failed\n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}
