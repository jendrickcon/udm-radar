<?php
/**
 * tests/integration/batch_predict_missing_grade_test.php
 * Integration Test: Missing Prelim Grade Handling in Automatic Support Referrals (SUPPORT-HOTFIX-WP1)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Source code invariant:
 *    - `api/batch_predict.php` does NOT fabricate 70.00 for NULL prelims.
 *    - `g.prelim IS NOT NULL` is enforced in query.
 *    - `is_numeric` check distinguishes genuine numbers from null/empty.
 *    - Parent case creation is guarded against empty valid loads (prevents orphan cases).
 * 2. Contract A & B: NULL prelim does not become 70.00 and generates NO referral.
 * 3. Contract C: Legitimate numeric 70.00 is preserved accurately.
 * 4. Contract D: Legitimate numeric 0.00 is preserved accurately (not treated as NULL).
 * 5. Contract E: Risk level boundaries:
 *    - grade < 75.00 => HIGH
 *    - 75.00 <= grade < 82.00 => MODERATE
 *    - grade >= 82.00 => LOW
 * 6. Contract F: Mixed loads (1 encoded + 3 NULL) produces exactly 1 referral.
 * 7. Contract G: All NULL prelims produces 0 parent cases and 0 referrals.
 * 8. Complete fixture cleanup in finally block.
 *
 * Run via CLI: php tests/integration/batch_predict_missing_grade_test.php
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');

if (!defined('BATCH_PREDICT_LIB_ONLY')) {
    define('BATCH_PREDICT_LIB_ONLY', true);
}
require_once dirname(__DIR__, 2) . '/api/batch_predict.php';
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

// Strict isolation guard
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
echo "UDM-RADAR: BATCH PREDICT MISSING GRADE REFERRAL INTEGRATION TESTS\n";
echo "Target Database: udm_radar_scratch\n";
echo "========================================================================\n\n";

// Track fixtures for robust teardown
$fixtureUserIds = [];
$fixtureCaseIds = [];

/**
 * Executes the referral & case creation logic by directly calling the production helper from api/batch_predict.php.
 */
function runReferralGeneration(PDO $db, int $studentId, int $predictionId, array $predResult, string $sy, int $sem): int {
    return createSupportCaseWithReferrals($db, $studentId, $predictionId, $predResult, $sy, $sem);
}

try {
    // -------------------------------------------------------------------------
    // 1. Source Code Invariant Checks
    // -------------------------------------------------------------------------
    echo "=== 1. Source Code Invariant Checks ===\n";
    $batchPredictSource = file_get_contents(dirname(__DIR__, 2) . '/api/batch_predict.php');

    assertCondition(
        strpos($batchPredictSource, "70.00") === false,
        "api/batch_predict.php contains no fabricated default 70.00 grade"
    );
    assertCondition(
        strpos($batchPredictSource, 'g.prelim IS NOT NULL') !== false,
        "api/batch_predict.php filters out NULL prelims in SQL (g.prelim IS NOT NULL)"
    );
    assertCondition(
        strpos($batchPredictSource, 'is_numeric($ld[\'prelim\'])') !== false,
        "api/batch_predict.php validates prelim is numeric in PHP"
    );
    assertCondition(
        strpos($batchPredictSource, 'empty($validLoads)') !== false,
        "api/batch_predict.php guards parent case creation against empty valid loads (empty(\$validLoads))"
    );
    assertCondition(
        function_exists('createSupportCaseWithReferrals'),
        "Shared production function createSupportCaseWithReferrals is loaded directly from api/batch_predict.php"
    );
    assertCondition(
        strpos($batchPredictSource, '$db->beginTransaction()') !== false,
        "api/batch_predict.php transactionalizes parent case and referral creation"
    );

    // -------------------------------------------------------------------------
    // Setup Test Fixtures in Scratch Database
    // -------------------------------------------------------------------------
    echo "\n=== Setup Test Fixtures in Scratch Database ===\n";

    $term = getCurrentTerm();
    $sy = $term['school_year'];
    $sem = (int)$term['semester'];
    $testSection = 'TST-99';

    // 1. Create a dedicated test faculty user
    $facCode = 'FAC-HOTFIX-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $stmtFac = $db->prepare("
        INSERT INTO users (user_id, password_hash, role, first_name, last_name, is_active)
        VALUES (?, 'hash', 'faculty', 'Hotfix', 'Faculty', 1)
    ");
    $stmtFac->execute([$facCode]);
    $facultyId = (int)$db->lastInsertId();
    $fixtureUserIds[] = $facultyId;

    // 2. Fetch or create test subjects
    $stmtSubjs = $db->query("SELECT id FROM subjects LIMIT 5");
    $subjectIds = $stmtSubjs->fetchAll(PDO::FETCH_COLUMN);
    assertCondition(count($subjectIds) >= 4, "Available test subjects in scratch DB");

    // Assign faculty to test subjects in testSection
    $stmtLoad = $db->prepare("
        INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section)
        VALUES (?, ?, ?)
    ");
    foreach ($subjectIds as $sId) {
        $stmtLoad->execute([$facultyId, $sId, $testSection]);
    }

    // Helper to create test student
    $createTestStudent = function(string $uname) use ($db, $testSection, &$fixtureUserIds): int {
        $userCode = 'STU-' . substr(bin2hex(random_bytes(6)), 0, 10);
        $stmtU = $db->prepare("
            INSERT INTO users (user_id, password_hash, role, first_name, last_name, is_active)
            VALUES (?, 'hash', 'student', 'Test', 'Student', 1)
        ");
        $stmtU->execute([$userCode]);
        $uId = (int)$db->lastInsertId();
        $fixtureUserIds[] = $uId;

        $studentNumber = 'SN-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $stmtP = $db->prepare("
            INSERT INTO student_profiles (user_id, student_number, course, section, status, record_status)
            VALUES (?, ?, 'BSIT - Software Development', ?, 'Regular', 'Active')
        ");
        $stmtP->execute([$uId, $studentNumber, $testSection]);
        return $uId;
    };

    // Helper to create test prediction
    $createTestPrediction = function(int $studentId) use ($db): int {
        $stmtPred = $db->prepare("
            INSERT INTO predictions (student_id, predicted_gwa, risk_level, prediction_source, data_completeness, is_provisional, generated_at)
            VALUES (?, 1.70, 'HIGH', 'decision_tree', 'complete', 0, NOW())
        ");
        $stmtPred->execute([$studentId]);
        return (int)$db->lastInsertId();
    };

    // -------------------------------------------------------------------------
    // 2. Test Scenario 1: Student with ALL NULL prelim grades (Unencoded)
    // -------------------------------------------------------------------------
    echo "\n=== 2. Scenario 1: Student with ALL NULL Prelims ===\n";
    $studentAllNull = $createTestStudent('stu_all_null');
    $predId1 = $createTestPrediction($studentAllNull);

    // Enroll in 4 subjects, all prelim = NULL
    $stmtGrade = $db->prepare("
        INSERT INTO grades (student_id, subject_id, prelim, is_current, school_year, semester)
        VALUES (?, ?, NULL, 1, ?, ?)
    ");
    for ($i = 0; $i < 4; $i++) {
        $stmtGrade->execute([$studentAllNull, $subjectIds[$i], $sy, $sem]);
    }

    $createdCases1 = runReferralGeneration($db, $studentAllNull, $predId1, [
        'risk_level' => 'HIGH',
        'predicted_gwa' => 1.70,
        'prediction_source' => 'decision_tree'
    ], $sy, $sem);

    assertCondition($createdCases1 === 0, "No case created for student with 100% unencoded (NULL) prelims");

    $casesCount1 = $db->query("SELECT COUNT(*) FROM academic_support_cases WHERE student_id = $studentAllNull")->fetchColumn();
    assertCondition((int)$casesCount1 === 0, "0 parent cases in database for all-NULL student (no orphan case)");

    $refCount1 = $db->query("
        SELECT COUNT(*) FROM support_case_referrals scr
        JOIN academic_support_cases asc_case ON asc_case.id = scr.case_id
        WHERE asc_case.student_id = $studentAllNull
    ")->fetchColumn();
    assertCondition((int)$refCount1 === 0, "0 referrals created for all-NULL student");

    // -------------------------------------------------------------------------
    // 3. Test Scenario 2: Mixed Loads (1 Encoded + 3 NULL)
    // -------------------------------------------------------------------------
    echo "\n=== 3. Scenario 2: Mixed Loads (1 Encoded + 3 NULL) ===\n";
    $studentMixed = $createTestStudent('stu_mixed');
    $predId2 = $createTestPrediction($studentMixed);

    // Subject 0 has prelim = 72.50; Subjects 1, 2, 3 have prelim = NULL
    $stmtGrade->execute([$studentMixed, $subjectIds[0], $sy, $sem]);
    $db->prepare("UPDATE grades SET prelim = 72.50 WHERE student_id = ? AND subject_id = ?")
       ->execute([$studentMixed, $subjectIds[0]]);
    for ($i = 1; $i < 4; $i++) {
        $stmtGrade->execute([$studentMixed, $subjectIds[$i], $sy, $sem]);
    }

    $createdCases2 = runReferralGeneration($db, $studentMixed, $predId2, [
        'risk_level' => 'HIGH',
        'predicted_gwa' => 1.70,
        'prediction_source' => 'decision_tree'
    ], $sy, $sem);

    assertCondition($createdCases2 === 1, "Exactly 1 case created for student with 1 encoded grade");

    $caseRow2 = $db->query("SELECT id FROM academic_support_cases WHERE student_id = $studentMixed")->fetch();
    $fixtureCaseIds[] = (int)$caseRow2['id'];

    $refRows2 = $db->query("SELECT * FROM support_case_referrals WHERE case_id = {$caseRow2['id']}")->fetchAll();
    assertCondition(count($refRows2) === 1, "Exactly 1 referral created for the 1 encoded subject");
    assertCondition((int)$refRows2[0]['subject_id'] === (int)$subjectIds[0], "Referral belongs to the encoded subject");
    assertCondition((float)$refRows2[0]['latest_term_grade'] === 72.50, "Referral preserves exact encoded grade (72.50)");
    assertCondition($refRows2[0]['subject_risk_level'] === 'HIGH', "Subject risk level correctly categorized as HIGH (72.50 < 75.0)");

    // Verify NULL subjects were NOT assigned 70.00 or created as referrals
    $refSubjIds2 = array_column($refRows2, 'subject_id');
    assertCondition(!in_array($subjectIds[1], $refSubjIds2), "Subject with NULL prelim has NO referral");
    assertCondition(!in_array($subjectIds[2], $refSubjIds2), "Subject with NULL prelim has NO referral");
    assertCondition(!in_array($subjectIds[3], $refSubjIds2), "Subject with NULL prelim has NO referral");

    // -------------------------------------------------------------------------
    // 4. Test Scenario 3: Legitimate Numeric 70.00 Preserved
    // -------------------------------------------------------------------------
    echo "\n=== 4. Scenario 3: Legitimate Numeric 70.00 Preserved ===\n";
    $student70 = $createTestStudent('stu_real_70');
    $predId3 = $createTestPrediction($student70);

    $stmtGrade->execute([$student70, $subjectIds[0], $sy, $sem]);
    $db->prepare("UPDATE grades SET prelim = 70.00 WHERE student_id = ? AND subject_id = ?")
       ->execute([$student70, $subjectIds[0]]);

    $createdCases3 = runReferralGeneration($db, $student70, $predId3, [
        'risk_level' => 'HIGH',
        'predicted_gwa' => 1.70,
        'prediction_source' => 'decision_tree'
    ], $sy, $sem);

    assertCondition($createdCases3 === 1, "Parent case created for legitimate numeric 70.00");
    $caseRow3 = $db->query("SELECT id FROM academic_support_cases WHERE student_id = $student70")->fetch();
    $fixtureCaseIds[] = (int)$caseRow3['id'];

    $refRows3 = $db->query("SELECT * FROM support_case_referrals WHERE case_id = {$caseRow3['id']}")->fetchAll();
    assertCondition(count($refRows3) === 1, "Referral created for legitimate 70.00");
    assertCondition((float)$refRows3[0]['latest_term_grade'] === 70.00, "Genuine numeric 70.00 preserved accurately");
    assertCondition($refRows3[0]['subject_risk_level'] === 'HIGH', "Subject risk level is HIGH for 70.00");

    // -------------------------------------------------------------------------
    // 5. Test Scenario 4: Legitimate Numeric 0.00 (Failing Grade) Preserved
    // -------------------------------------------------------------------------
    echo "\n=== 5. Scenario 4: Legitimate Numeric 0.00 Preserved ===\n";
    $studentZero = $createTestStudent('stu_real_zero');
    $predId4 = $createTestPrediction($studentZero);

    $stmtGrade->execute([$studentZero, $subjectIds[0], $sy, $sem]);
    $db->prepare("UPDATE grades SET prelim = 0.00 WHERE student_id = ? AND subject_id = ?")
       ->execute([$studentZero, $subjectIds[0]]);

    $createdCases4 = runReferralGeneration($db, $studentZero, $predId4, [
        'risk_level' => 'HIGH',
        'predicted_gwa' => 1.70,
        'prediction_source' => 'decision_tree'
    ], $sy, $sem);

    assertCondition($createdCases4 === 1, "Parent case created for legitimate numeric 0.00");
    $caseRow4 = $db->query("SELECT id FROM academic_support_cases WHERE student_id = $studentZero")->fetch();
    $fixtureCaseIds[] = (int)$caseRow4['id'];

    $refRows4 = $db->query("SELECT * FROM support_case_referrals WHERE case_id = {$caseRow4['id']}")->fetchAll();
    assertCondition(count($refRows4) === 1, "Referral created for numeric 0.00");
    assertCondition((float)$refRows4[0]['latest_term_grade'] === 0.00, "Genuine numeric 0.00 preserved (not treated as null)");
    assertCondition($refRows4[0]['subject_risk_level'] === 'HIGH', "Subject risk level is HIGH for 0.00");

    // -------------------------------------------------------------------------
    // 6. Test Scenario 5: Subject Risk Thresholds Parity
    // -------------------------------------------------------------------------
    echo "\n=== 6. Scenario 5: Subject Risk Thresholds Parity ===\n";
    $studentCutoffs = $createTestStudent('stu_cutoffs');
    $predId5 = $createTestPrediction($studentCutoffs);

    // Test 4 distinct boundary values across 4 subjects
    $boundaryTests = [
        ['grade' => 74.99, 'expected_risk' => 'HIGH'],
        ['grade' => 75.00, 'expected_risk' => 'MODERATE'],
        ['grade' => 81.99, 'expected_risk' => 'MODERATE'],
        ['grade' => 82.00, 'expected_risk' => 'LOW'],
    ];

    for ($i = 0; $i < 4; $i++) {
        $stmtGrade->execute([$studentCutoffs, $subjectIds[$i], $sy, $sem]);
        $db->prepare("UPDATE grades SET prelim = ? WHERE student_id = ? AND subject_id = ?")
           ->execute([$boundaryTests[$i]['grade'], $studentCutoffs, $subjectIds[$i]]);
    }

    $createdCases5 = runReferralGeneration($db, $studentCutoffs, $predId5, [
        'risk_level' => 'HIGH',
        'predicted_gwa' => 1.70,
        'prediction_source' => 'decision_tree'
    ], $sy, $sem);

    assertCondition($createdCases5 === 1, "Parent case created for multi-subject boundary tests");
    $caseRow5 = $db->query("SELECT id FROM academic_support_cases WHERE student_id = $studentCutoffs")->fetch();
    $fixtureCaseIds[] = (int)$caseRow5['id'];

    $refRows5 = $db->query("
        SELECT subject_id, latest_term_grade, subject_risk_level
        FROM support_case_referrals
        WHERE case_id = {$caseRow5['id']}
        ORDER BY subject_id ASC
    ")->fetchAll();

    assertCondition(count($refRows5) === 4, "All 4 encoded subjects generated referrals");
    for ($i = 0; $i < 4; $i++) {
        $actualGrade = (float)$refRows5[$i]['latest_term_grade'];
        $actualRisk = $refRows5[$i]['subject_risk_level'];
        $expGrade = $boundaryTests[$i]['grade'];
        $expRisk = $boundaryTests[$i]['expected_risk'];

        assertCondition(
            abs($actualGrade - $expGrade) < 0.01 && $actualRisk === $expRisk,
            "Boundary grade $expGrade correctly mapped to risk '$expRisk' (got grade $actualGrade, risk '$actualRisk')"
        );
    }

    // -------------------------------------------------------------------------
    // 7. Test Scenario 6: Faculty Workload Isolation
    // -------------------------------------------------------------------------
    echo "\n=== 7. Scenario 6: Faculty Workload Isolation ===\n";
    $pendingForFaculty = countFacultyPendingReferrals($db, $facultyId);
    // StudentMixed: 1, Student70: 1, StudentZero: 1, StudentCutoffs: 4 => total 7 pending referrals
    assertCondition(
        $pendingForFaculty === 7,
        "countFacultyPendingReferrals accurately counts only encoded referrals (expected 7, got $pendingForFaculty)"
    );

    // Verify no orphan cases exist anywhere in scratch database
    $orphanCount = (int)$db->query("
        SELECT COUNT(*)
        FROM academic_support_cases asc_case
        LEFT JOIN support_case_referrals scr ON scr.case_id = asc_case.id
        WHERE scr.id IS NULL
    ")->fetchColumn();
    assertCondition($orphanCount === 0, "Zero orphan support cases exist in scratch DB (orphan prevention confirmed)");

} finally {
    // -------------------------------------------------------------------------
    // Teardown Test Fixtures
    // -------------------------------------------------------------------------
    echo "\n=== Teardown Fixtures from Scratch Database ===\n";

    if (!empty($fixtureUserIds)) {
        $inUsers = implode(',', array_map('intval', $fixtureUserIds));
        $db->exec("DELETE scr FROM support_case_referrals scr JOIN academic_support_cases asc_case ON asc_case.id = scr.case_id WHERE asc_case.student_id IN ($inUsers)");
        $db->exec("DELETE FROM academic_support_cases WHERE student_id IN ($inUsers)");
        $db->exec("DELETE FROM faculty_class_loads WHERE faculty_user_id IN ($inUsers)");
        $db->exec("DELETE FROM grades WHERE student_id IN ($inUsers)");
        $db->exec("DELETE FROM predictions WHERE student_id IN ($inUsers)");
        $db->exec("DELETE FROM student_profiles WHERE user_id IN ($inUsers)");
        $db->exec("DELETE FROM users WHERE id IN ($inUsers)");
    }

    echo "Teardown complete. Cleaned up " . count($fixtureUserIds) . " users and " . count($fixtureCaseIds) . " cases.\n";
}

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun assertions. Failures: $failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
exit(0);
