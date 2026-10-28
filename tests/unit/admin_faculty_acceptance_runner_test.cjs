// Checks the CLI runner's failure paths without connecting to the application DB.
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { resolve } = require('node:path');
const php = process.env.PHP_BIN || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const script = resolve('tests/integration/admin_faculty_read_only_acceptance_test.php');
let passed = 0, failed = 0;
for (const optIn of [false, true]) {
    const env = { ...process.env, DB_HOST: '127.0.0.1;port=1', DB_NAME: 'acceptance_unavailable_probe', DB_USER: 'acceptance_unavailable_probe', DB_PASS: '' };
    if (optIn) env.PHASE_C_READ_ONLY_DB = 'udm_radar'; else delete env.PHASE_C_READ_ONLY_DB;
    const result = spawnSync(php, [script], { env, encoding: 'utf8', timeout: 12000 });
    const name = optIn ? 'unavailable database cannot report CLI success' : 'missing read-only opt-in refuses execution';
    try {
        assert.equal(result.error, undefined, 'Runner must exit, not time out');
        assert.ok(Number.isInteger(result.status) && result.status !== 0, 'Incomplete run must exit nonzero');
        assert.match(result.stdout + result.stderr, optIn ? /Database connection unavailable/ : /NOT RUN/);
        assert.doesNotMatch(result.stdout, /RESULT \d+ passed/);
        passed++; process.stdout.write(`PASS: ${name}\n`);
    } catch (error) { failed++; process.stderr.write(`FAIL: ${name}: ${error.message}; exit=${result.status}\n`); }
}
process.stdout.write(`RESULT ${passed} passed, ${failed} failed; local failure probes only, no application DB access\n`);
process.exitCode = failed ? 1 : 0;
