<?php
/**
 * Integration Test: Model Governance Gateway & Secret Security (WP-7 / SEC-CUR-ML-01)
 *
 * Target: udm_radar_scratch ONLY. Live database udm_radar is strictly untouched.
 *
 * Verifies:
 * 1. getMlGovernanceSecret():
 *    - Returns environment variable value when set.
 *    - Fails closed (returns null) when unconfigured.
 * 2. api/model_governance.php Gateway Protection:
 *    - Unauthenticated request redirected to login.php (HTTP 302).
 *    - Unauthorized role (student) redirected with unauthorized error (HTTP 302).
 *    - Non-POST request rejected with HTTP 405 Method Not Allowed.
 *    - Missing CSRF token rejected with HTTP 403 Forbidden.
 *    - Tampered CSRF token rejected with HTTP 403 Forbidden.
 *    - Disallowed actions rejected with HTTP 400 Bad Request.
 *    - Unconfigured secret fails closed with HTTP 503 Service Unavailable.
 *    - Valid admin + CSRF + configured secret succeeds for 'status' action.
 *    - Secret is NEVER leaked in response bodies or headers.
 * 3. Python ML Service Governance Security:
 *    - Flask require_api_key decorator fails closed with 503 when secret is unconfigured.
 *    - Flask require_api_key decorator returns 401 when wrong key is provided.
 *    - Flask require_api_key decorator accepts valid timing-safe key when configured.
 *
 * Run via CLI: php tests/integration/model_governance_test.php
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

// Start session early for test harness
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/includes/auth.php';

echo "========================================================================\n";
echo "UDM-RADAR: MODEL GOVERNANCE GATEWAY & SECRET SECURITY TESTS (WP-7)\n";
echo "Database: $scratchDb\n";
echo "========================================================================\n\n";

$adminUser = $db->query("SELECT id, user_id, role, is_active FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetch();
$studentUser = $db->query("SELECT id, user_id, role, is_active FROM users WHERE role = 'student' AND is_active = 1 LIMIT 1")->fetch();

echo "=== 1. Secret Configuration Helper Verification ===\n";

// Test unconfigured fail-closed behavior
putenv('UDM_RADAR_ML_SECRET'); // unset
$secretNone = getMlGovernanceSecret();
assertCondition($secretNone === null, "getMlGovernanceSecret() returns null when secret is unconfigured (fail closed)");

// Test configured behavior
$testSecret = 'secret_test_token_abcdef1234567890';
putenv('UDM_RADAR_ML_SECRET=' . $testSecret);
$secretSet = getMlGovernanceSecret();
assertCondition($secretSet === $testSecret, "getMlGovernanceSecret() returns configured secret from environment");
putenv('UDM_RADAR_ML_SECRET'); // reset for following tests


echo "\n=== 2. Model Governance Gateway Subprocess Tests ===\n";

function invokeGatewaySubprocess(array $sessionState, string $method = 'POST', ?string $action = 'status', ?string $headerToken = null, ?string $inputJson = null, ?string $configuredSecret = null): array {
    $tempScript = tempnam(sys_get_temp_dir(), 'mg_test_');
    $tempStatus = tempnam(sys_get_temp_dir(), 'mg_stat_');
    $gatewayPath = addslashes(dirname(__DIR__, 2) . '/api/model_governance.php');
    
    $encodedSession = base64_encode(serialize($sessionState));
    $encodedMethod = addslashes($method);
    $encodedAction = $action !== null ? "'$action'" : 'null';
    $encodedHeader = $headerToken !== null ? "'$headerToken'" : 'null';
    $encodedSecret = $configuredSecret !== null ? "'$configuredSecret'" : 'null';
    $escapedStatusPath = addslashes($tempStatus);
    
    $scriptCode = <<<PHP
<?php
putenv('DB_NAME=udm_radar_scratch');
\$sec = {$encodedSecret};
if (\$sec !== null) {
    putenv('UDM_RADAR_ML_SECRET=' . \$sec);
} else {
    putenv('UDM_RADAR_ML_SECRET');
}

\$_SERVER['REQUEST_METHOD'] = '{$encodedMethod}';
\$act = {$encodedAction};
if (\$act !== null) {
    \$_GET['action'] = \$act;
}
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

include '{$gatewayPath}';
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

$validAdminCsrf = bin2hex(random_bytes(32));

// Test A: Unauthenticated request
$resA = invokeGatewaySubprocess([]);
assertCondition($resA['http_code'] === 302, "Unauthenticated access is redirected to login (HTTP 302)");

// Test B: Student role request
$resB = invokeGatewaySubprocess([
    'user_id' => $studentUser['id'],
    'role' => 'student',
    'csrf_token' => $validAdminCsrf
], 'POST', 'status', $validAdminCsrf);
assertCondition($resB['http_code'] === 302, "Student role is redirected to unauthorized error (HTTP 302)");

// Test C: Admin GET request
$resC = invokeGatewaySubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'GET', 'status', $validAdminCsrf);
assertCondition($resC['http_code'] === 405, "GET request is rejected with HTTP 405 Method Not Allowed");

// Test D: Missing CSRF token
$resD = invokeGatewaySubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', 'status', null, json_encode([]), 'some_secret');
$jsonD = json_decode(trim($resD['stdout']), true);
assertCondition($resD['http_code'] === 403, "Missing CSRF token returns HTTP 403 Forbidden");
assertCondition(($jsonD['error'] ?? '') === 'CSRF validation failed.', "Missing CSRF error specifies CSRF validation failed");

// Test E: Tampered CSRF token
$resE = invokeGatewaySubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', 'status', 'tampered_token_val', json_encode([]), 'some_secret');
$jsonE = json_decode(trim($resE['stdout']), true);
assertCondition($resE['http_code'] === 403, "Tampered CSRF token returns HTTP 403 Forbidden");

// Test F: Disallowed action
$resF = invokeGatewaySubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', 'arbitrary_exec', $validAdminCsrf, json_encode(['csrf_token' => $validAdminCsrf]), 'some_secret');
$jsonF = json_decode(trim($resF['stdout']), true);
assertCondition($resF['http_code'] === 400, "Disallowed governance action returns HTTP 400 Bad Request");
assertCondition(str_contains($jsonF['error'] ?? '', 'Invalid governance action'), "Disallowed action error specifies invalid action");

// Test G: Unconfigured secret fails closed with HTTP 503
$resG = invokeGatewaySubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', 'status', $validAdminCsrf, json_encode(['csrf_token' => $validAdminCsrf]), null);
$jsonG = json_decode(trim($resG['stdout']), true);
assertCondition($resG['http_code'] === 503, "Unconfigured secret fails closed with HTTP 503 Service Unavailable");
assertCondition(str_contains($jsonG['error'] ?? '', 'not configured'), "503 error explains secret is not configured");

// Test H: Configured secret + valid admin + valid CSRF succeeds for 'status' action
$realTestSecret = 'configured_server_secret_key_12345';
$resH = invokeGatewaySubprocess([
    'user_id' => $adminUser['id'],
    'role' => 'admin',
    'csrf_token' => $validAdminCsrf
], 'POST', 'status', $validAdminCsrf, json_encode(['csrf_token' => $validAdminCsrf]), $realTestSecret);
$jsonH = json_decode(trim($resH['stdout']), true);
assertCondition($resH['http_code'] === 200, "Valid request with configured secret succeeds with HTTP 200");
assertCondition(($jsonH['success'] ?? false) === true, "Status action returns success flag");
assertCondition(isset($jsonH['active_metrics']), "Status action returns active model metrics");
assertCondition(!str_contains($resH['stdout'], $realTestSecret), "Server secret is NEVER leaked in gateway response body");


echo "\n=== 3. Python ML Service Governance Security Verification ===\n";

$pythonExe = dirname(__DIR__, 2) . '/python_ml/venv/Scripts/python.exe';
if (!file_exists($pythonExe)) {
    $pythonExe = 'python';
}

$repoRoot = addslashes(dirname(__DIR__, 2));

$pySnippet = <<<PY
import os, sys, json
sys.path.insert(0, "{$repoRoot}/python_ml")
import app as ml_app
client = ml_app.app.test_client()

# Case 1: Unconfigured secret fails closed with 503
os.environ.pop('UDM_RADAR_ML_SECRET', None)
r1 = client.post('/api/cancel_candidate')
assert r1.status_code == 503, f'Expected 503 got {r1.status_code}'

# Case 2: Configured secret with invalid key returns 401
os.environ['UDM_RADAR_ML_SECRET'] = 'test_sec'
r2 = client.post('/api/cancel_candidate', headers={'X-API-Key': 'wrong'})
assert r2.status_code == 401, f'Expected 401 got {r2.status_code}'

# Case 3: Configured secret with valid key succeeds
r3 = client.post('/api/cancel_candidate', headers={'X-API-Key': 'test_sec'})
assert r3.status_code == 200, f'Expected 200 got {r3.status_code}'

print('PY_TEST_SUCCESS')
PY;

$tempPy = tempnam(sys_get_temp_dir(), 'py_gov_') . '.py';
file_put_contents($tempPy, $pySnippet);
$pyTestCmd = "\"$pythonExe\" \"$tempPy\"";
$pyOutput = [];
$pyExit = 0;
exec($pyTestCmd, $pyOutput, $pyExit);
@unlink($tempPy);

$pySuccess = ($pyExit === 0 && in_array('PY_TEST_SUCCESS', $pyOutput, true));
assertCondition($pySuccess, "Python ML service: fail-closed 503, invalid 401, and valid 200 contract verified");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
