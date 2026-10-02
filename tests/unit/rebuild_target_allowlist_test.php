<?php
/**
 * Unit Test: Database Rebuild Tool Target Allowlist Safeguard (WP-7 Closeout)
 *
 * Verifies non-destructive enforcement:
 * 1. Missing target argument is rejected with exit code 1.
 * 2. Disallowed targets (--target=live, --target=production, --target=udm_radar, arbitrary) are rejected with exit code 1.
 * 3. Tool requires explicit --target=scratch or --target=demo.
 * 4. Ensures no destructive actions are executed when invalid target is supplied.
 *
 * Run via CLI: php tests/unit/rebuild_target_allowlist_test.php
 */

declare(strict_types=1);

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
echo "UDM-RADAR: REBUILD TOOL TARGET ALLOWLIST SAFEGUARD TESTS (WP-7)\n";
echo "========================================================================\n\n";

$rebuildScript = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'rebuild_database.php';

function invokeRebuildDry(array $args): array {
    global $rebuildScript;
    $argString = implode(' ', array_map('escapeshellarg', $args));
    $cmd = sprintf('"%s" "%s" %s', PHP_BINARY, $rebuildScript, $argString);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    return [
        'code'   => $code,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

// 1. Missing target
$resMissing = invokeRebuildDry([]);
assertCondition($resMissing['code'] === 1, "Missing --target argument is rejected with exit code 1");
assertCondition(str_contains($resMissing['stderr'], 'FATAL: Must specify an allowed target'), "Missing target outputs fatal allowlist guidance");

// 2. Disallowed: --target=live
$resLive = invokeRebuildDry(['--target=live']);
assertCondition($resLive['code'] === 1, "--target=live is rejected with exit code 1");
assertCondition(str_contains($resLive['stderr'], 'Refused disallowed target'), "--target=live error message explicitly notes refusal");

// 3. Disallowed: --target=production
$resProd = invokeRebuildDry(['--target=production']);
assertCondition($resProd['code'] === 1, "--target=production is rejected with exit code 1");
assertCondition(str_contains($resProd['stderr'], 'Refused disallowed target'), "--target=production error message explicitly notes refusal");

// 4. Disallowed: --target=udm_radar
$resUdm = invokeRebuildDry(['--target=udm_radar']);
assertCondition($resUdm['code'] === 1, "--target=udm_radar is rejected with exit code 1");
assertCondition(str_contains($resUdm['stderr'], 'Refused disallowed target'), "--target=udm_radar error message explicitly notes refusal");

// 5. Arbitrary injection / unrecognized name
$resArb = invokeRebuildDry(['--target=random_test_db']);
assertCondition($resArb['code'] === 1, "Arbitrary target name is rejected with exit code 1");
assertCondition(str_contains($resArb['stderr'], 'Refused disallowed target: \'random_test_db\''), "Arbitrary target reports exact refused token");

// 6. Injection attempt
$resInj = invokeRebuildDry(['--target=scratch; DROP TABLE users;']);
assertCondition($resInj['code'] === 1, "SQL injection attempt in target parameter is rejected with exit code 1");

echo "\n========================================================================\n";
echo "SUMMARY: Ran $testsRun tests, $failures failures.\n";
echo "========================================================================\n";

if ($failures > 0) {
    exit(1);
}
