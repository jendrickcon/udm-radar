<?php
/**
 * tools/seed_multiyear_cohorts.php — Seed Multi-Year Synthetic Testing Cohorts (COHORT-DATA-WP1)
 *
 * PURPOSE:
 * Seeds, verifies, or cleans 15 controlled synthetic student validation cases across:
 * 1. First Year (COHORT-1Y-01 through 03)
 * 2. Second Year (COHORT-2Y-01 through 03)
 * 3. Third Year (COHORT-3Y-01 through 03)
 * 4. Fourth Year (COHORT-4Y-01 through 03)
 * 5. Cross-Year / Irregular (COHORT-XR-01 through 03)
 *
 * STRICT GOVERNANCE RULES:
 * - Live production database ('udm_radar') is STRICTLY FORBIDDEN.
 * - Requires explicit --target=scratch flag.
 * - Namespaced student numbers ('<year>-00-<seq>') and '@synthetic.udm.edu.ph' emails only.
 * - Zero modifications to canonical database dump (database/udm_radar.sql).
 *
 * USAGE:
 *   php tools/seed_multiyear_cohorts.php --target=scratch
 *   php tools/seed_multiyear_cohorts.php --target=scratch --dry-run
 *   php tools/seed_multiyear_cohorts.php --target=scratch --verify
 *   php tools/seed_multiyear_cohorts.php --target=scratch --cleanup
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../tests/fixtures/multiyear_cohort_cases.php';

const ALLOWED_TARGETS = [
    'scratch' => 'udm_radar_scratch',
];

// -----------------------------------------------------------------------------
// 1. Argument Parsing & Safety Gates
// -----------------------------------------------------------------------------
$target = null;
$isDryRun = false;
$isVerify = false;
$isCleanup = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, 9);
    } elseif ($arg === '--dry-run') {
        $isDryRun = true;
    } elseif ($arg === '--verify') {
        $isVerify = true;
    } elseif ($arg === '--cleanup' || $arg === '--clean') {
        $isCleanup = true;
    }
}

if ($target === null || !array_key_exists($target, ALLOWED_TARGETS)) {
    fwrite(STDERR, "FATAL: Must specify an allowed target database option: --target=scratch\n");
    if ($target !== null) {
        fwrite(STDERR, "Refused disallowed target: '$target'. Live, demo, and arbitrary targets are strictly forbidden.\n");
    } else {
        fwrite(STDERR, "Target option missing. Refusing execution.\n");
    }
    exit(1);
}

$dbName = ALLOWED_TARGETS[$target];

// Guard: strictly refuse live production database or demo database
if ($dbName !== 'udm_radar_scratch' || in_array($target, ['live', 'production', 'udm_radar', 'demo', 'udm_radar_demo'], true)) {
    fwrite(STDERR, "FATAL: Target database must strictly be 'udm_radar_scratch'. Refused: '$target'.\n");
    exit(1);
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';

echo "========================================================================\n";
echo "UDM-RADAR: MULTI-YEAR SYNTHETIC COHORT SEEDER (COHORT-DATA-WP1)\n";
echo "Selected Target: $target ($dbName)\n";
$actionLabel = $isVerify ? "VERIFY" : ($isCleanup ? "CLEANUP" : ($isDryRun ? "DRY RUN" : "SEED / RE-SEED"));
echo "Mode: $actionLabel\n";
echo "========================================================================\n\n";

$cases = getMultiyearCohortCases();
$cohortNamespacePattern = 'COHORT-%';

// If dry-run, output simulation and exit without connecting/writing
if ($isDryRun) {
    echo "--- DRY RUN SIMULATION ---\n";
    echo "Total synthetic cases defined: " . count($cases) . "\n";
    $cohortCounts = [];
    $totalHistoricalGrades = 0;
    $totalCurrentGrades = 0;
    $totalPredictions = 0;

    foreach ($cases as $c) {
        $cohortCounts[$c['cohort_name']] = ($cohortCounts[$c['cohort_name']] ?? 0) + 1;
        foreach ($c['historical_terms'] as $term) {
            $totalHistoricalGrades += count($term['subjects']);
        }
        $totalCurrentGrades += count($c['current_term']['subjects']);
        if (!empty($c['prediction'])) {
            $totalPredictions++;
        }
    }

    echo "Cases per cohort:\n";
    foreach ($cohortCounts as $name => $cnt) {
        echo "  - $name: $cnt case(s)\n";
    }
    echo "Simulated record counts:\n";
    echo "  - users: " . count($cases) . "\n";
    echo "  - student_profiles: " . count($cases) . "\n";
    echo "  - historical grades: $totalHistoricalGrades\n";
    echo "  - current grades: $totalCurrentGrades\n";
    echo "  - total grades: " . ($totalHistoricalGrades + $totalCurrentGrades) . "\n";
    echo "  - predictions: $totalPredictions\n";
    echo "\n[DRY RUN] No database writes were performed.\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// 2. Database Connection and Verification
// -----------------------------------------------------------------------------
try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not connect to target database '$dbName': " . $e->getMessage() . "\n");
    exit(1);
}

$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== $dbName) {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', expected '$dbName'. Refusing to continue.\n");
    exit(1);
}

// -----------------------------------------------------------------------------
// 3. Verification Mode (--verify)
// -----------------------------------------------------------------------------
if ($isVerify) {
    echo "--- RUNNING VERIFICATION AGAINST '$dbName' ---\n";
    $userIds = array_column($cases, 'user_id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $foundUsers = $db->prepare("SELECT id, user_id, first_name, last_name, email FROM users WHERE user_id IN ($placeholders)");
    $foundUsers->execute($userIds);
    $userRows = $foundUsers->fetchAll(PDO::FETCH_ASSOC);
    $foundUserIds = array_column($userRows, 'id', 'user_id');

    $allPassed = true;
    echo "Checking " . count($cases) . " synthetic case accounts...\n";

    foreach ($cases as $c) {
        $uidStr = $c['user_id'];
        if (!isset($foundUserIds[$uidStr])) {
            echo "  [FAIL] Missing user record for {$c['case_id']} (user_id: $uidStr)\n";
            $allPassed = false;
            continue;
        }

        $uid = (int)$foundUserIds[$uidStr];
        $profile = $db->query("SELECT * FROM student_profiles WHERE user_id = $uid")->fetch();
        if (!$profile) {
            echo "  [FAIL] Missing student_profile for {$c['case_id']}\n";
            $allPassed = false;
            continue;
        }

        if ($profile['student_number'] !== $c['student_number']) {
            echo "  [FAIL] {$c['case_id']} student_number mismatch (expected {$c['student_number']}, got {$profile['student_number']})\n";
            $allPassed = false;
        }

        if ((int)$profile['year_level'] !== (int)$c['year_level']) {
            echo "  [FAIL] {$c['case_id']} year_level mismatch (expected {$c['year_level']}, got {$profile['year_level']})\n";
            $allPassed = false;
        }

        if ($profile['status'] !== $c['status']) {
            echo "  [FAIL] {$c['case_id']} status mismatch (expected {$c['status']}, got {$profile['status']})\n";
            $allPassed = false;
        }

        $currCount = (int)$db->query("SELECT count(*) FROM grades WHERE student_id = $uid AND is_current = 1")->fetchColumn();
        $expectedCurr = count($c['current_term']['subjects']);
        if ($currCount !== $expectedCurr) {
            echo "  [FAIL] {$c['case_id']} current grades count mismatch (expected $expectedCurr, got $currCount)\n";
            $allPassed = false;
        }
    }

    if ($allPassed && count($userRows) === count($cases)) {
        echo "[VERIFY SUCCESS] All " . count($cases) . " synthetic cohort cases verified in '$dbName'.\n";
        exit(0);
    } else {
        echo "[VERIFY FAILED] Database '$dbName' does not match full synthetic cohort specification.\n";
        exit(1);
    }
}

// -----------------------------------------------------------------------------
// 4. Cleanup Functionality (--cleanup or reseed preparation)
// -----------------------------------------------------------------------------
function cleanCohortRecords(PDO $db): int {
    // Find all users in the synthetic cohort namespace
    $cohortUsers = $db->query("
        SELECT id, user_id FROM users
        WHERE user_id LIKE 'COHORT-%'
           OR user_id LIKE '%-00-%'
           OR email LIKE '%@synthetic.udm.edu.ph'
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cohortUsers)) {
        return 0;
    }

    $uids = array_map(fn($u) => (int)$u['id'], $cohortUsers);
    $inUids = implode(',', $uids);

    // 1. Clean support cases tagged with [COHORT-DATA-WP1] or linked to cohort students
    $supportCases = $db->query("
        SELECT id FROM academic_support_cases
        WHERE student_id IN ($inUids)
           OR closure_note LIKE '%[COHORT-DATA-WP1]%'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($supportCases)) {
        $inCases = implode(',', array_map('intval', $supportCases));
        $db->exec("DELETE FROM support_status_history WHERE case_id IN ($inCases)");
        $db->exec("DELETE FROM support_actions WHERE case_id IN ($inCases)");
        $db->exec("DELETE FROM support_case_referrals WHERE case_id IN ($inCases)");
        $db->exec("DELETE FROM academic_support_cases WHERE id IN ($inCases)");
    }

    // 2. Clean feedback reports and status history if any
    $feedbackReports = $db->query("
        SELECT id FROM feedback_reports WHERE submitted_by IN ($inUids)
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($feedbackReports)) {
        $inFb = implode(',', array_map('intval', $feedbackReports));
        $db->exec("DELETE FROM feedback_status_history WHERE feedback_id IN ($inFb)");
        $db->exec("DELETE FROM feedback_reports WHERE id IN ($inFb)");
    }

    // 3. Clean predictions
    $db->exec("DELETE FROM predictions WHERE student_id IN ($inUids)");

    // 4. Clean grades
    $db->exec("DELETE FROM grades WHERE student_id IN ($inUids)");

    // 5. Clean student profiles
    $db->exec("DELETE FROM student_profiles WHERE user_id IN ($inUids)");

    // 6. Clean user accounts
    $deletedUsers = $db->exec("DELETE FROM users WHERE id IN ($inUids)");

    return $deletedUsers;
}

$cleanedCount = cleanCohortRecords($db);
if ($cleanedCount > 0) {
    echo "[CLEANUP] Removed $cleanedCount previous synthetic cohort account(s) and associated records.\n";
} else {
    echo "[CLEANUP] No previous synthetic cohort records found.\n";
}

if ($isCleanup) {
    echo "\nMulti-year cohort cleanup completed successfully.\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// 5. Seeding Execution (Idempotent Insert)
// -----------------------------------------------------------------------------
echo "--- SEEDING 5 COHORTS (15 SYNTHETIC CASES) ---\n";

// Map subject codes to IDs
$subjectMap = [];
$subjs = $db->query("SELECT id, code, units FROM subjects")->fetchAll(PDO::FETCH_ASSOC);
foreach ($subjs as $s) {
    $subjectMap[$s['code']] = ['id' => (int)$s['id'], 'units' => (int)$s['units']];
}

// Fetch default encoder faculty ID for grade records
$facultyId = (int)($db->query("SELECT id FROM users WHERE role='faculty' LIMIT 1")->fetchColumn() ?: 2);

$defaultPasswordHash = password_hash(COHORT_TEST_PASSWORD, PASSWORD_BCRYPT);

$db->beginTransaction();

try {
    $nextUserId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM users")->fetchColumn() + 1;

    $stmtUser = $db->prepare("
        INSERT INTO users (id, user_id, email, password_hash, role, first_name, last_name, is_active)
        VALUES (?, ?, ?, ?, 'student', ?, ?, 1)
    ");

    $nextProfileId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM student_profiles")->fetchColumn() + 1;

    $stmtProfile = $db->prepare("
        INSERT INTO student_profiles (id, user_id, student_number, course, college, year_level, section, status, record_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtGrade = $db->prepare("
        INSERT INTO grades (student_id, subject_id, school_year, semester, prelim, midterm, prefinal, final_grade, risk_level, is_current, encoded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtPred = $db->prepare("
        INSERT INTO predictions (student_id, predicted_gwa, risk_level, latin_honor, prediction_source,
                                data_completeness, is_provisional, provisional_basis, input_subject_count, expected_subject_count, generated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $totalGradesInserted = 0;
    $totalPredsInserted = 0;

    foreach ($cases as $c) {
        $uid = $nextUserId++;

        // Insert User
        $stmtUser->execute([
            $uid,
            $c['user_id'],
            $c['email'],
            $defaultPasswordHash,
            $c['first_name'],
            $c['last_name'],
        ]);

        // Insert Student Profile
        $stmtProfile->execute([
            $nextProfileId++,
            $uid,
            $c['student_number'],
            $c['course'],
            $c['college'],
            $c['year_level'],
            $c['section'],
            $c['status'],
            $c['record_status'],
        ]);

        // Insert Historical Grades
        foreach ($c['historical_terms'] as $hTerm) {
            foreach ($hTerm['subjects'] as $hSubj) {
                if (!isset($subjectMap[$hSubj['code']])) {
                    throw new Exception("Unknown subject code '{$hSubj['code']}' in historical term for {$c['case_id']}");
                }
                $subjId = $subjectMap[$hSubj['code']]['id'];
                $stmtGrade->execute([
                    $uid,
                    $subjId,
                    $hTerm['school_year'],
                    $hTerm['semester'],
                    null, // prelim is null for completed historical terms
                    null, // midterm is null for completed historical terms
                    null, // prefinal is null
                    $hSubj['final_grade'],
                    $hSubj['risk_level'],
                    0,    // is_current = 0
                    $facultyId,
                ]);
                $totalGradesInserted++;
            }
        }

        // Insert Current Term Grades
        $cTerm = $c['current_term'];
        foreach ($cTerm['subjects'] as $cSubj) {
            if (!isset($subjectMap[$cSubj['code']])) {
                throw new Exception("Unknown subject code '{$cSubj['code']}' in current term for {$c['case_id']}");
            }
            $subjId = $subjectMap[$cSubj['code']]['id'];
            $stmtGrade->execute([
                $uid,
                $subjId,
                $cTerm['school_year'],
                $cTerm['semester'],
                $cSubj['prelim'],
                $cSubj['midterm'],
                $cSubj['prefinal'],
                $cSubj['final_grade'],
                $cSubj['risk_level'],
                1,    // is_current = 1
                $facultyId,
            ]);
            $totalGradesInserted++;
        }

        // Recalculate official GWA from database historical records
        $computedGwa = recalculateStudentGwa($db, $uid);

        // Insert Prediction if defined
        $predId = null;
        if (!empty($c['prediction'])) {
            $p = $c['prediction'];
            $completeness = $p['prediction_completeness'] ?? ($p['data_completeness'] ?? 'complete');
            $isProvisional = ($completeness === 'complete') ? 0 : 1;
            $provisionalBasis = null;
            if ($completeness === 'historical_only') {
                $provisionalBasis = 'historical_gwa';
            } elseif ($completeness === 'prelim_only') {
                $provisionalBasis = 'current_prelim_avg';
            }

            $expectedSubjCount = count($c['current_term']['subjects']);
            $inputSubjCount = 0;
            if ($completeness === 'historical_only') {
                $inputSubjCount = 0;
            } else {
                foreach ($c['current_term']['subjects'] as $cs) {
                    if ($cs['prelim'] !== null) {
                        $inputSubjCount++;
                    }
                }
            }

            $stmtPred->execute([
                $uid,
                $p['predicted_gwa'],
                $p['risk_level'],
                $p['latin_honor'] ?? null,
                $p['prediction_source'],
                $completeness,
                $isProvisional,
                $provisionalBasis,
                $inputSubjCount,
                $expectedSubjCount,
            ]);
            $totalPredsInserted++;
            $predId = (int)$db->lastInsertId();
        }

        // Insert Support Case if defined (e.g. for Case C1)
        if (!empty($c['support_case'])) {
            $sc = $c['support_case'];
            $subjCode = $sc['subject_code'];
            $targetSubjId = $subjectMap[$subjCode]['id'] ?? 39;

            $stmtCase = $db->prepare("
                INSERT INTO academic_support_cases (student_id, prediction_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, assigned_faculty_id, status, closure_note)
                VALUES (?, ?, ?, ?, 'decision_tree', '2026-2027', 1, ?, 'action_taken', ?)
            ");
            $stmtCase->execute([
                $uid,
                $predId,
                $sc['trigger_risk_level'],
                $sc['trigger_predicted_gwa'],
                $facultyId,
                "[COHORT-DATA-WP1] Synthetic support case for {$c['case_id']}",
            ]);
            $caseId = (int)$db->lastInsertId();

            $stmtRef = $db->prepare("
                INSERT INTO support_case_referrals (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status, reason_code, message_to_student, notice_issued_at)
                VALUES (?, ?, ?, ?, ?, 'Prelim', 72.00, 'action_taken', ?, ?, NOW())
            ");
            $stmtRef->execute([
                $caseId,
                $facultyId,
                $targetSubjId,
                $c['section'],
                $sc['trigger_risk_level'],
                $sc['reason_code'],
                $sc['message_to_student'],
            ]);

            $db->prepare("
                INSERT INTO support_actions (case_id, actor_id, action_type, message_to_student, created_at)
                VALUES (?, ?, 'academic_notice_sent', ?, NOW())
            ")->execute([$caseId, $facultyId, $sc['message_to_student']]);
        }

        $gwaLabel = $computedGwa !== null ? number_format($computedGwa, 2) : 'N/A';
        echo "  [OK] Seeded {$c['case_id']} ({$c['cohort_name']}) | Year: {$c['year_level']} | Section: {$c['section']} | GWA: $gwaLabel\n";
    }

    $db->commit();

    echo "\n========================================================================\n";
    echo "SEEDING SUMMARY:\n";
    echo "  - Total Students Seeded: " . count($cases) . "\n";
    echo "  - Total Grades Inserted: $totalGradesInserted\n";
    echo "  - Total Predictions Inserted: $totalPredsInserted\n";
    echo "  - Target: $dbName\n";
    echo "========================================================================\n";
    echo "Multi-year validation cohort seeding completed successfully.\n";
    exit(0);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "FATAL: Seeding failed with error: " . $e->getMessage() . "\n");
    exit(1);
}
