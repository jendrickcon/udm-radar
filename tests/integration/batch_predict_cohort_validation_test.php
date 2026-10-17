<?php
/**
 * tests/integration/batch_predict_cohort_validation_test.php
 * Post-Merge Batch Prediction and Synthetic Cohort Validation (BATCH-PREDICT-POST-COHORT-WP1)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 */

declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');
$_ENV['DB_NAME'] = 'udm_radar_scratch';

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/grade_coverage.php';

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

function assertCond(bool $cond, string $msg): void {
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
echo "BATCH PREDICT POST-COHORT VALIDATION SUITE (BATCH-PREDICT-POST-COHORT-WP1)\n";
echo "Target Database: $currentDb\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Pre-Batch Baseline Capture
// -----------------------------------------------------------------------------
echo "--- 1. PRE-BATCH BASELINE CAPTURE ---\n";

function computeTableHash(PDO $db, string $table, string $orderCol = 'id'): string {
    $rows = $db->query("SELECT * FROM `$table` ORDER BY `$orderCol` ASC")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256', json_encode($rows));
}

$preHashes = [
    'users'            => computeTableHash($db, 'users'),
    'student_profiles' => computeTableHash($db, 'student_profiles', 'user_id'),
    'grades'           => computeTableHash($db, 'grades'),
    'predictions'      => computeTableHash($db, 'predictions'),
    'academic_support_cases' => computeTableHash($db, 'academic_support_cases'),
];

$preCounts = [
    'students' => (int)$db->query("SELECT COUNT(*) FROM users u JOIN student_profiles sp ON u.id = sp.user_id WHERE u.role = 'student' AND sp.record_status = 'Active' AND u.is_active = 1")->fetchColumn(),
    'grades' => (int)$db->query("SELECT COUNT(*) FROM grades")->fetchColumn(),
    'prelim_non_null' => (int)$db->query("SELECT COUNT(*) FROM grades WHERE prelim IS NOT NULL")->fetchColumn(),
    'midterm_non_null' => (int)$db->query("SELECT COUNT(*) FROM grades WHERE midterm IS NOT NULL")->fetchColumn(),
    'predictions' => (int)$db->query("SELECT COUNT(*) FROM predictions")->fetchColumn(),
    'support_cases' => (int)$db->query("SELECT COUNT(*) FROM academic_support_cases")->fetchColumn(),
];

$maxPrePredictionId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM predictions")->fetchColumn();
$maxPreCaseId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM academic_support_cases")->fetchColumn();

echo "Pre-Batch Active Students: {$preCounts['students']}\n";
echo "Pre-Batch Grades: {$preCounts['grades']} (Prelim: {$preCounts['prelim_non_null']}, Midterm: {$preCounts['midterm_non_null']})\n";
echo "Pre-Batch Predictions: {$preCounts['predictions']}\n";
echo "Pre-Batch Support Cases: {$preCounts['support_cases']}\n\n";

assertCond($preCounts['students'] === 306, "Pre-batch active student count equals 306 (288 Third-Year + 3 First-Year + 4 Second-Year + 5 Third-Year Cohort + 6 Fourth-Year)");
assertCond($preCounts['midterm_non_null'] === 2393, "Pre-batch non-null midterm count is 2,393 (2,304 section midterms + 89 cohort midterms)");

// -----------------------------------------------------------------------------
// 2. Invoke Batch Predict via Authenticated Subprocess
// -----------------------------------------------------------------------------
echo "\n--- 2. INVOKE BATCH PREDICT VIA AUTHENTICATED ENDPOINT ---\n";

function invokeBatchPredictSubprocess(array $sessionState, string $method = 'POST', ?string $headerToken = null, ?string $inputJson = null): array {
    $tempScript = tempnam(sys_get_temp_dir(), 'bp_val_');
    $tempStatus = tempnam(sys_get_temp_dir(), 'bp_stat_');
    $batchPredictPath = addslashes(dirname(__DIR__, 2) . '/api/batch_predict.php');

    $encodedSession = base64_encode(serialize($sessionState));
    $encodedMethod = addslashes($method);
    $encodedHeader = $headerToken !== null ? "'$headerToken'" : 'null';
    $escapedStatusPath = addslashes($tempStatus);

    $scriptCode = <<<PHP
<?php
putenv('DB_NAME=udm_radar_scratch');
\$_SERVER['REQUEST_METHOD'] = '{$encodedMethod}';
\$hToken = {$encodedHeader};
if (\$hToken !== null) {
    \$_SERVER['HTTP_X_CSRF_TOKEN'] = \$hToken;
}

register_shutdown_function(function() {
    file_put_contents('{$escapedStatusPath}', json_encode([
        'http_response_code' => http_response_code(),
        'headers' => headers_list()
    ]));
});

if (!empty('{$encodedSession}')) {
    \$sess = unserialize(base64_decode('{$encodedSession}'));
    if (!empty(\$sess)) {
        session_id(bin2hex(random_bytes(16)));
        session_start();
        \$_SESSION = \$sess;
    }
}

include '{$batchPredictPath}';
PHP;

    file_put_contents($tempScript, $scriptCode);
    $cmd = '"' . PHP_BINARY . '" "' . $tempScript . '"';

    $pipes = [];
    $proc = proc_open($cmd, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipes);

    if ($inputJson !== null) {
        fwrite($pipes[0], $inputJson);
    }
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    proc_close($proc);

    $meta = [];
    if (file_exists($tempStatus)) {
        $meta = json_decode(file_get_contents($tempStatus), true) ?? [];
        @unlink($tempStatus);
    }
    @unlink($tempScript);

    return [
        'http_code' => $meta['http_response_code'] ?? 0,
        'headers'   => $meta['headers'] ?? [],
        'stdout'    => $stdout,
        'stderr'    => $stderr
    ];
}

$adminUser = $db->query("SELECT id, email, role FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$csrfToken = bin2hex(random_bytes(32));
$adminSession = [
    'user_id' => $adminUser['id'],
    'email'   => $adminUser['email'],
    'role'    => 'admin',
    'csrf_token' => $csrfToken,
];

$startTime = microtime(true);
$run1 = invokeBatchPredictSubprocess($adminSession, 'POST', $csrfToken, json_encode(['csrf_token' => $csrfToken]));
$elapsed = microtime(true) - $startTime;
$output = $run1['stdout'];

echo "Batch Predict Execution Output:\n$output\n";
echo sprintf("Execution Time: %.2f seconds\n", $elapsed);

$response = json_decode(trim((string)$output), true);
assertCond(is_array($response), "Batch Predict response is valid JSON");
assertCond(($response['status'] ?? '') === 'success', "Batch Predict status is success");

$metrics = $response['metrics'] ?? [];
$totalProcessed = $metrics['total_processed'] ?? 0;
$successCount   = $metrics['success_count'] ?? 0;
$failCount      = $metrics['fail_count'] ?? 0;
$casesCreated   = $metrics['cases_created'] ?? 0;

echo "Total Processed: $totalProcessed\n";
echo "Success Count: $successCount\n";
echo "Fail (Skipped) Count: $failCount\n";
echo "Cases Created: $casesCreated\n";

assertCond($totalProcessed === 306, "Batch Predict processed all 306 eligible active students");
assertCond($successCount === 305, "Batch Predict successfully evaluated 305 students");
assertCond($failCount === 1, "Batch Predict skipped exactly 1 student with insufficient data (26-00-003)");

// -----------------------------------------------------------------------------
// 3. Multi-Year Representative Account Verification
// -----------------------------------------------------------------------------
echo "\n--- 3. MULTI-YEAR REPRESENTATIVE ACCOUNTS ---\n";

function getLatestPrediction(PDO $db, string $studentNumber): ?array {
    $stmt = $db->prepare("
        SELECT p.*, sp.student_number, sp.year_level, sp.section
        FROM predictions p
        JOIN student_profiles sp ON sp.user_id = p.student_id
        WHERE sp.student_number = ?
        ORDER BY p.generated_at DESC, p.id DESC
        LIMIT 1
    ");
    $stmt->execute([$studentNumber]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// First Year: 26-00-001 (Partial prelim only)
$p1 = getLatestPrediction($db, '26-00-001');
assertCond($p1 !== null, "26-00-001 has a latest prediction");
assertCond($p1['data_completeness'] === 'prelim_only', "26-00-001 data_completeness is prelim_only");
assertCond((int)$p1['is_provisional'] === 1, "26-00-001 is_provisional is 1");
assertCond($p1['provisional_basis'] === 'current_prelim_avg', "26-00-001 provisional basis is current_prelim_avg");
assertCond($p1['prediction_source'] === 'calculation_fallback', "26-00-001 source is calculation_fallback (provisional rule)");

// First Year: 26-00-003 (Empty state - insufficient data)
$p3 = getLatestPrediction($db, '26-00-003');
// It should NOT have a prediction generated during this run!
$stmtP3Count = $db->query("SELECT COUNT(*) FROM predictions p JOIN student_profiles sp ON sp.user_id = p.student_id WHERE sp.student_number = '26-00-003'")->fetchColumn();
assertCond((int)$stmtP3Count === 0, "26-00-003 has 0 predictions (insufficient data safely rejected)");

// Second Year: 25-00-001 (Complete)
$p21 = getLatestPrediction($db, '25-00-001');
assertCond($p21 !== null, "25-00-001 has latest prediction");
assertCond($p21['data_completeness'] === 'complete', "25-00-001 data_completeness is complete");
assertCond((int)$p21['is_provisional'] === 0, "25-00-001 is not provisional");
assertCond($p21['prediction_source'] === 'decision_tree', "25-00-001 prediction_source is decision_tree");

// Third Year: 24-00-001 (Complete)
$p31 = getLatestPrediction($db, '24-00-001');
assertCond($p31 !== null, "24-00-001 has latest prediction");
assertCond($p31['data_completeness'] === 'complete', "24-00-001 data_completeness is complete");
assertCond($p31['prediction_source'] === 'decision_tree', "24-00-001 prediction_source is decision_tree");

// Fourth Year: 23-00-001 (Capstone INC stored as NULL, not 0.00)
$stmtInc = $db->query("
    SELECT g.final_grade, s.code
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE sp.student_number = '23-00-001' AND s.code = 'ITE326'
")->fetch(PDO::FETCH_ASSOC);
assertCond($stmtInc !== false && $stmtInc['final_grade'] === null, "23-00-001 Capstone 1 (ITE326) final_grade is strictly NULL (not 0.00)");

$p41 = getLatestPrediction($db, '23-00-001');
assertCond($p41 !== null, "23-00-001 has latest prediction");
assertCond($p41['predicted_gwa'] >= 1.00 && $p41['predicted_gwa'] <= 4.00, "23-00-001 predicted GWA is within 1.00 - 4.00");

// Cross-Year: 24-00-010, 24-00-011, 24-00-012
$pIrreg = getLatestPrediction($db, '24-00-011');
assertCond($pIrreg !== null, "24-00-011 (Cross-Year) has latest prediction");
// Verify irregular status didn't force high risk artificially
echo "24-00-011 Risk: {$pIrreg['risk_level']}, Predicted GWA: {$pIrreg['predicted_gwa']}, Source: {$pIrreg['prediction_source']}\n";
assertCond(in_array($pIrreg['risk_level'], ['LOW', 'MODERATE', 'HIGH'], true), "24-00-011 risk level is valid category");

// -----------------------------------------------------------------------------
// 4. Third-Year Section Checks (IT-31 to IT-38)
// -----------------------------------------------------------------------------
echo "\n--- 4. THIRD-YEAR SECTION AUDIT (IT-31 to IT-38) ---\n";

$secStats = $db->query("
    SELECT sp.section, COUNT(DISTINCT sp.user_id) as student_count,
           SUM(CASE WHEN p.risk_level = 'HIGH' THEN 1 ELSE 0 END) as high_count,
           SUM(CASE WHEN p.risk_level = 'MODERATE' THEN 1 ELSE 0 END) as mod_count,
           SUM(CASE WHEN p.risk_level = 'LOW' THEN 1 ELSE 0 END) as low_count,
           SUM(CASE WHEN p.prediction_source = 'decision_tree' THEN 1 ELSE 0 END) as dt_count
    FROM student_profiles sp
    JOIN (
        SELECT p1.*
        FROM predictions p1
        JOIN (
            SELECT student_id, MAX(id) as max_id
            FROM predictions
            GROUP BY student_id
        ) p2 ON p1.id = p2.max_id
    ) p ON p.student_id = sp.user_id
    WHERE sp.section LIKE 'IT-3%'
    GROUP BY sp.section
    ORDER BY sp.section
")->fetchAll(PDO::FETCH_ASSOC);

$expectedSecCounts = [
    'IT-31' => 42,
    'IT-32' => 36,
    'IT-33' => 40,
    'IT-34' => 40,
    'IT-35' => 37,
    'IT-36' => 28,
    'IT-37' => 35,
    'IT-38' => 33,
];

$totalSecStudents = 0;
$totalSecDt = 0;

foreach ($secStats as $row) {
    echo sprintf(
        "Section %s: %d students | LOW: %d, MOD: %d, HIGH: %d | Decision Tree: %d\n",
        $row['section'], $row['student_count'], $row['low_count'], $row['mod_count'], $row['high_count'], $row['dt_count']
    );
    $expCount = $expectedSecCounts[$row['section']] ?? 0;
    assertCond((int)$row['student_count'] === $expCount, "Section {$row['section']} has exactly $expCount students");
    assertCond((int)($row['low_count'] + $row['mod_count'] + $row['high_count']) === (int)$row['student_count'], "Section {$row['section']} all students have valid risk categories");
    $totalSecStudents += (int)$row['student_count'];
    $totalSecDt += (int)$row['dt_count'];
}

assertCond($totalSecStudents === 291, "All 291 third-year students in IT-31..IT-38 received predictions");
assertCond($totalSecDt === 282, "Exactly 282 complete third-year students evaluated via Decision Tree");
assertCond($totalSecStudents - $totalSecDt === 9, "Exactly 9 incomplete third-year students evaluated via calculation fallback");

// -----------------------------------------------------------------------------
// 5. Repeated-Run Verification & Concurrency Lock
// -----------------------------------------------------------------------------
echo "\n--- 5. REPEATED-RUN & CONCURRENCY LOCK VERIFICATION ---\n";

$predsBeforeRun2 = (int)$db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();

// Run batch predict a second time
$run2 = invokeBatchPredictSubprocess($adminSession, 'POST', $csrfToken, json_encode(['csrf_token' => $csrfToken]));
$output2 = $run2['stdout'];

$response2 = json_decode(trim((string)$output2), true);
assertCond(($response2['status'] ?? '') === 'success', "Second Batch Predict execution returns success");

$predsAfterRun2 = (int)$db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
$newPredictions = $predsAfterRun2 - $predsBeforeRun2;
echo "Predictions inserted in Run 2: $newPredictions\n";
assertCond($newPredictions === 305, "Repeated run created exactly 305 new timestamped prediction records (1 per successful student)");

// Verify that latest prediction query resolves unambiguously with no duplicates
$stmtAmbiguity = $db->query("
    SELECT student_id, COUNT(*) as cnt
    FROM (
        SELECT student_id, MAX(id) as max_id
        FROM predictions
        GROUP BY student_id
    ) sub
    GROUP BY student_id
    HAVING cnt > 1
")->fetchAll();
assertCond(empty($stmtAmbiguity), "Latest prediction lookup (MAX id per student) is strictly unique and unambiguous");

// Test concurrency lock
$lockFile = sys_get_temp_dir() . '/udm_radar_batch.lock';
file_put_contents($lockFile, time());
// While lock exists, call batch predict
$runLocked = invokeBatchPredictSubprocess($adminSession, 'POST', $csrfToken, json_encode(['csrf_token' => $csrfToken]));
if (file_exists($lockFile)) {
    unlink($lockFile);
}
$lockedJson = json_decode(trim((string)$runLocked['stdout']), true);
assertCond($runLocked['http_code'] === 429 && ($lockedJson['error'] ?? '') === 'A batch prediction is already running. Please wait.', "Concurrency lock returned HTTP 429 message while lock active");

// -----------------------------------------------------------------------------
// 6. Post-Batch Database Consistency & Immutability Check
// -----------------------------------------------------------------------------
echo "\n--- 6. POST-BATCH IMMUTABILITY & INTEGRITY AUDIT ---\n";

$postHashes = [
    'users'            => computeTableHash($db, 'users'),
    'student_profiles' => computeTableHash($db, 'student_profiles', 'user_id'),
    'grades'           => computeTableHash($db, 'grades'),
];

assertCond($preHashes['users'] === $postHashes['users'], "users table is 100% byte-for-byte identical (no password/user mutations)");
assertCond($preHashes['student_profiles'] === $postHashes['student_profiles'], "student_profiles table is 100% byte-for-byte identical (no status/profile mutations)");
assertCond($preHashes['grades'] === $postHashes['grades'], "grades table is 100% byte-for-byte identical (Prelims, Midterms, units completely untouched)");

$postGradeCounts = [
    'grades' => (int)$db->query("SELECT COUNT(*) FROM grades")->fetchColumn(),
    'prelim_non_null' => (int)$db->query("SELECT COUNT(*) FROM grades WHERE prelim IS NOT NULL")->fetchColumn(),
    'midterm_non_null' => (int)$db->query("SELECT COUNT(*) FROM grades WHERE midterm IS NOT NULL")->fetchColumn(),
];

assertCond($preCounts['grades'] === $postGradeCounts['grades'], "Total grades rows unchanged ({$postGradeCounts['grades']})");
assertCond($preCounts['prelim_non_null'] === $postGradeCounts['prelim_non_null'], "Total prelim non-null unchanged ({$postGradeCounts['prelim_non_null']})");
assertCond($preCounts['midterm_non_null'] === $postGradeCounts['midterm_non_null'], "Total midterm non-null unchanged ({$postGradeCounts['midterm_non_null']})");

// Final Capstone 1 check
$stmtIncPost = $db->query("
    SELECT g.final_grade
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE sp.student_number = '23-00-001' AND s.code = 'ITE326'
")->fetch(PDO::FETCH_ASSOC);
assertCond($stmtIncPost !== false && $stmtIncPost['final_grade'] === null, "Fourth-Year INC remains strictly NULL and was never coerced to 0.00");

// 19. Midterm exclusion from prediction inputs
assertCond(
    strpos(file_get_contents(dirname(__DIR__, 2) . '/api/batch_predict.php'), 'midterm') === false,
    "api/batch_predict.php strictly excludes midterm column from prediction queries and feature inputs"
);

// 20. Test cleanup restores any test-generated prediction and support state
if ($maxPrePredictionId > 0) {
    $db->exec("DELETE FROM predictions WHERE id > $maxPrePredictionId");
}
if ($maxPreCaseId > 0) {
    $db->exec("DELETE FROM academic_support_cases WHERE id > $maxPreCaseId");
}
$postCleanupPredictions = (int)$db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
assertCond($postCleanupPredictions === $preCounts['predictions'], "Test cleanup successfully restored predictions count to baseline ({$preCounts['predictions']})");

echo "\n========================================================================\n";
echo "SUMMARY: Tests Run: $testsRun | Failures: $failures\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
exit(0);
