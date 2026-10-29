// Checks the CLI runner's failure paths and database-isolation guards without connecting to the application DB.
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { resolve } = require('node:path');

const php = process.env.PHP_BIN || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const script = resolve('tests/integration/admin_faculty_read_only_acceptance_test.php');

let passed = 0;
let failed = 0;

const cases = [
    {
        name: 'missing or empty database target refuses execution',
        env: { DB_HOST: '127.0.0.1;port=1' },
        expectedRegex: /missing or empty/i,
    },
    {
        name: 'protected database udm_radar is rejected before connection',
        env: { DB_HOST: '127.0.0.1;port=1', PHASE_C_DISPOSABLE_DB: 'udm_radar' },
        expectedRegex: /Target 'udm_radar' is strictly prohibited/i,
    },
    {
        name: 'protected database udm_radar_scratch is rejected before connection',
        env: { DB_HOST: '127.0.0.1;port=1', PHASE_C_DISPOSABLE_DB: 'udm_radar_scratch' },
        expectedRegex: /Target 'udm_radar_scratch' is strictly prohibited/i,
    },
    {
        name: 'database target without approved disposable prefix is rejected before connection',
        env: { DB_HOST: '127.0.0.1;port=1', PHASE_C_DISPOSABLE_DB: 'arbitrary_test_db' },
        expectedRegex: /approved disposable prefix/i,
    },
    {
        name: 'database target missing unique suffix is rejected before connection',
        env: { DB_HOST: '127.0.0.1;port=1', PHASE_C_DISPOSABLE_DB: 'udm_radar_admin_faculty_acceptance_' },
        expectedRegex: /unique suffix/i,
    },
    {
        name: 'unavailable database with valid disposable target cannot report CLI success',
        env: { DB_HOST: '127.0.0.1;port=1', PHASE_C_DISPOSABLE_DB: 'udm_radar_admin_faculty_acceptance_probe' },
        expectedRegex: /Database connection unavailable/i,
    },
];

for (const c of cases) {
    const baseEnv = { ...process.env };
    delete baseEnv.PHASE_C_DISPOSABLE_DB;
    delete baseEnv.PHASE_C_READ_ONLY_DB;
    const testEnv = { ...baseEnv, ...c.env };

    const result = spawnSync(php, [script], { env: testEnv, encoding: 'utf8', timeout: 12000 });
    try {
        assert.equal(result.error, undefined, 'Runner must exit, not time out');
        assert.ok(Number.isInteger(result.status) && result.status !== 0, 'Refused or incomplete run must exit nonzero');
        const output = (result.stdout || '') + (result.stderr || '');
        assert.match(output, c.expectedRegex, `Output must match expected pattern ${c.expectedRegex}`);
        assert.doesNotMatch(result.stdout || '', /RESULT \d+ passed/, 'Must not report success');
        passed++;
        process.stdout.write(`PASS: ${c.name}\n`);
    } catch (error) {
        failed++;
        process.stderr.write(`FAIL: ${c.name}: ${error.message}; exit=${result.status}\n`);
    }
}

process.stdout.write(`RESULT ${passed} passed, ${failed} failed; database isolation guards and failure probes verified\n`);
process.exitCode = failed ? 1 : 0;
