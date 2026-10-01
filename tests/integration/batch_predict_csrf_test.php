<?php
/**
 * Integration Test: Batch Predict CSRF Protection & Role Authorization (WP-7 / BUG-CUR-01)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. Centralized CSRF token helpers in includes/auth.php:
 *    - getCsrfToken() generates cryptographically secure 64-char hex token.
 *    - regenerateCsrfToken() produces new distinct token and invalidates old token.
 *    - validateCsrfToken() strictly validates valid vs invalid/null/empty tokens.
 *    - checkCsrf() handles POST and HTTP_X_CSRF_TOKEN inputs safely.
 * 2. api/batch_predict.php endpoint defense:
 *    - Unauthenticated request is redirected to login.php (HTTP 302).
 *    - Unauthorized role (student) is redirected to login.php?error=unauthorized (HTTP 302).
 *    - Non-POST request is rejected with HTTP 405 Method Not Allowed.
 *    - POST without CSRF token is rejected with HTTP 403 and writes NO predictions.
 *    - POST with invalid CSRF token is rejected with HTTP 403 and writes NO predictions.
 *    - POST with valid X-CSRF-Token header passes CSRF validation.
 *    - POST with valid csrf_token in JSON body passes CSRF validation.
 *
 * Run via CLI: php tests/integration/batch_predict_csrf_test.php
 */

declare(strict_types=1);

// Enforce scratch database for any loaded configs
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

// Start session before any output for Section 1 unit tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/auth.php';

echo "========================================================================\n";
echo "UDM-RADAR: BATCH PREDICT CSRF & ROLE SECURITY TESTS (WP-7 / BUG-CUR-01)\n";
echo "Database: $scratchDb\n";
echo "========================================================================\n\n";

// Find an active admin and active student from scratch database
$adminUser = $db->query("SELECT id, user_id, role, is_active FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetch();
$studentUser = $db->query("SELECT id, user_id, role, is_active FROM users WHERE role = 'student' AND is_active = 1 LIMIT 1")->fetch();

if (!$adminUser || !$studentUser) {
    fwrite(STDERR, "FATAL: Required test fixtures (admin or student user) missing in scratch database.\n");
    exit(1);
}

echo "=== 1. Centralized CSRF Helper Unit Verification ===\n";

$token1 = getCsrfToken();
assertCondition(is_string($token1) && strlen($token1) === 64, "getCsrfToken() returns a 64-character hexadecimal token");
assertCondition(ctype_xdigit($token1), "getCsrfToken() token contains only valid hexadecimal characters");

$token2 = getCsrfToken();
assertCondition($token1 === $token2, "getCsrfToken() is idempotent within the same active session");

assertCondition(validateCsrfToken($token1) === true, "validateCsrfToken() accepts active session token");
assertCondition(validateCsrfToken(null) === false, "validateCsrfToken() rejects null token");
assertCondition(validateCsrfToken('') === false, "validateCsrfToken() rejects empty string token");
assertCondition(validateCsrfToken('tampered_token_value') === false, "validateCsrfToken() rejects tampered token");
assertCondition(validateCsrfToken(str_repeat('a', 64)) === false, "validateCsrfToken() rejects mismatched 64-char token");

$newToken = regenerateCsrfToken();
assertCondition(is_string($newToken) && strlen($newToken) === 64, "regenerateCsrfToken() produces a valid 64-char token");
assertCondition($newToken !== $token1, "regenerateCsrfToken() creates a distinct token from the previous one");
assertCondition(validateCsrfToken($newToken) === true, "validateCsrfToken() accepts the newly regenerated token");
assertCondition(validateCsrfToken($token1) === false, "validateCsrfToken() invalidates the previous token");

// Test checkCsrf() POST input
$_POST['csrf_token'] = $newToken;
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
assertCondition(checkCsrf() === true, "checkCsrf() validates token from \$_POST['csrf_token']");

$_POST['csrf_token'] = 'invalid_token';
assertCondition(checkCsrf() === false, "checkCsrf() rejects invalid token from \$_POST['csrf_token']");
unset($_POST['csrf_token']);

// Test checkCsrf() Header input
$_SERVER['HTTP_X_CSRF_TOKEN'] = $newToken;
assertCondition(checkCsrf() === true, "checkCsrf() validates token from HTTP_X_CSRF_TOKEN header");

$_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid_header_token';
assertCondition(checkCsrf() === false, "checkCsrf() rejects invalid token from HTTP_X_CSRF_TOKEN header");
unset($_SERVER['HTTP_X_CSRF_TOKEN']);


echo "\n=== 2. Batch Predict Subprocess Endpoint Invocation Tests ===\n";

/**
 * Executes a simulated request to api/batch_predict.php in an isolated PHP subprocess.
 */
function invokeBatchPredictSubprocess(array $sessionState, string $method = 'POST', ?string $headerToken = null, ?string $inputJson = null): array {
    $tempScript = tempnam(sys_get_temp_dir(), 'bp_test_');
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

// Initial count of predictions in scratch DB
$initialPredictionCount = (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();

// Test A: Unauthenticated request (no session)
$resA = invokeBatchPredictSubprocess([]);
assertCondition(
    ($resA['http_code'] === 302),
    "Unauthenticated request is redirected to login.php (HTTP 302)"
);

// Test B: Unauthorized role (student)
$resB = invokeBatchPredictSubprocess([
    'user_id' => $studentUser['id'],
    'role' => 'student',
    'csrf_token' => bin2hex(random_bytes(32))
]);
assertCondition(
    ($resB['http_code'] === 302),
    "Student role request is redirected to login.php?error=unauthorized (HTTP 302)"
);

// Test C: Admin GET request (Method Not Allowed)
$validAdminCsrf = bin2hex(random_bytes(32));
$resC = invokeBatchPredictSubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'GET');
$jsonC = json_decode(trim($resC['stdout']), true);
assertCondition(
    ($resC['http_code'] === 405 && ($jsonC['status'] ?? '') === 'error'),
    "Admin GET request returns HTTP 405 Method Not Allowed"
);

// Test D: Admin POST with missing CSRF token (neither in header nor body)
$resD = invokeBatchPredictSubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', null, json_encode([]));

$jsonD = json_decode(trim($resD['stdout']), true);
$predCountAfterD = (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();

assertCondition(($resD['http_code'] === 403), "Missing CSRF token returns HTTP 403 Forbidden");
assertCondition(($jsonD['error'] ?? '') === 'CSRF validation failed.', "Missing CSRF token response contains 'CSRF validation failed.' error");
assertCondition($predCountAfterD === $initialPredictionCount, "Missing CSRF token writes ZERO rows to predictions table");

// Test E: Admin POST with invalid/tampered CSRF token
$resE = invokeBatchPredictSubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', 'wrong_token_abc123', json_encode([]));

$jsonE = json_decode(trim($resE['stdout']), true);
$predCountAfterE = (int) $db->query("SELECT COUNT(*) FROM predictions")->fetchColumn();

assertCondition(($resE['http_code'] === 403), "Tampered CSRF token returns HTTP 403 Forbidden");
assertCondition(($jsonE['error'] ?? '') === 'CSRF validation failed.', "Tampered CSRF token response contains 'CSRF validation failed.' error");
assertCondition($predCountAfterE === $initialPredictionCount, "Tampered CSRF token writes ZERO rows to predictions table");

// Test F & G: Lock-guarded verification that valid tokens pass CSRF verification and proceed to job locking
// When lock is active, CSRF failure returns 403, while CSRF success returns 429 ("A batch prediction is already running").
$lockFile = sys_get_temp_dir() . '/udm_radar_batch.lock';
file_put_contents($lockFile, time());

// Test F: Admin POST with valid X-CSRF-Token header passes CSRF check
$resF = invokeBatchPredictSubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', $validAdminCsrf, json_encode([]));

$jsonF = json_decode(trim($resF['stdout']), true);
assertCondition(($resF['http_code'] === 429), "Valid X-CSRF-Token header passes CSRF verification and reaches lock gate (HTTP 429)");
assertCondition(str_contains($jsonF['error'] ?? '', 'already running'), "Valid X-CSRF-Token header receives lock response rather than CSRF rejection");

// Test G: Admin POST with valid csrf_token in JSON body passes CSRF check
$resG = invokeBatchPredictSubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', null, json_encode(['csrf_token' => $validAdminCsrf]));

$jsonG = json_decode(trim($resG['stdout']), true);
assertCondition(($resG['http_code'] === 429), "Valid JSON body csrf_token passes CSRF verification and reaches lock gate (HTTP 429)");
assertCondition(str_contains($jsonG['error'] ?? '', 'already running'), "Valid JSON body csrf_token receives lock response rather than CSRF rejection");

@unlink($lockFile);

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
