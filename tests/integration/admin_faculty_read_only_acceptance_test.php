<?php
declare(strict_types=1);

// Guarded disposable-database acceptance runner for Admin and Faculty portals.
// Strictly rejects live ('udm_radar') and scratch ('udm_radar_scratch') databases.
// Automatically provisions an isolated disposable schema, loads synthetic fixtures,
// runs the 31 read-only acceptance checks, and guarantees drop and cleanup assertion.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const DISPOSABLE_DB_PREFIX = 'udm_radar_admin_faculty_acceptance_';

/**
 * Validates the database target name strictly BEFORE any network or DB connection.
 */
function validateDisposableTarget(?string $raw): string {
    $target = trim((string)$raw);
    if ($target === '') {
        fwrite(STDERR, "FATAL: Database target is missing or empty. An approved disposable database target is required.\n");
        exit(2);
    }
    if ($target === 'udm_radar') {
        fwrite(STDERR, "FATAL: Target 'udm_radar' is strictly prohibited. Live database access is forbidden.\n");
        exit(2);
    }
    if ($target === 'udm_radar_scratch') {
        fwrite(STDERR, "FATAL: Target 'udm_radar_scratch' is strictly prohibited. Automated acceptance requires a disposable target.\n");
        exit(2);
    }
    if (!str_starts_with($target, DISPOSABLE_DB_PREFIX)) {
        fwrite(STDERR, "FATAL: Target '$target' rejected. Must start with approved disposable prefix '" . DISPOSABLE_DB_PREFIX . "'.\n");
        exit(2);
    }
    if ($target === DISPOSABLE_DB_PREFIX) {
        fwrite(STDERR, "FATAL: Target must include a unique suffix, e.g. '" . DISPOSABLE_DB_PREFIX . "<unique-suffix>'.\n");
        exit(2);
    }
    if (!preg_match('/^udm_radar_admin_faculty_acceptance_[a-zA-Z0-9_-]+$/D', $target)) {
        fwrite(STDERR, "FATAL: Target '$target' contains invalid characters. Alphanumeric, underscore, and hyphen only.\n");
        exit(2);
    }
    return $target;
}

// Resolve target from environment or CLI argument
$cliArg = $argv[1] ?? '';
$isSnapshot = ($cliArg === '--snapshot');
$rawTarget = getenv('PHASE_C_DISPOSABLE_DB') ?: getenv('PHASE_C_READ_ONLY_DB') ?: ($isSnapshot ? '' : $cliArg);

// Support explicit --auto generation
if ($rawTarget === '--auto' || $rawTarget === 'auto') {
    $rawTarget = DISPOSABLE_DB_PREFIX . bin2hex(random_bytes(6));
}

// 1. Strict validation BEFORE connection
$targetDb = validateDisposableTarget($rawTarget);

$acceptanceCompleted = false;
$serverPdo = null;

register_shutdown_function(static function() use (&$acceptanceCompleted, &$serverPdo, $targetDb): void {
    if ($serverPdo instanceof PDO) {
        try {
            $serverPdo->exec("DROP DATABASE IF EXISTS `$targetDb`");
        } catch (Throwable) {}
    }
    if (!$acceptanceCompleted) {
        fwrite(STDERR, "FAIL: Read-only acceptance terminated before completing its checks.\n");
        exit(1);
    }
});

// 2. Connect to MySQL server instance
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $serverPdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "FATAL: Database connection unavailable: " . $e->getMessage() . "\n");
    exit(1);
}

// 3. Provision guarded disposable database
function importSqlFile(string $dbName, string $filePath, string $host, string $user, string $pass): void {
    if (!file_exists($filePath)) {
        throw new RuntimeException("SQL file not found: $filePath");
    }
    $mysqlBin = file_exists('C:\\xampp\\mysql\\bin\\mysql.exe') ? 'C:\\xampp\\mysql\\bin\\mysql.exe' : 'mysql';
    $cmd = sprintf('"%s" -h %s -u %s %s', $mysqlBin, escapeshellarg($host), escapeshellarg($user), escapeshellarg($dbName));
    if ($pass !== '') {
        $cmd .= ' -p' . escapeshellarg($pass);
    }
    $descriptors = [
        0 => ['file', $filePath, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException("Failed to spawn mysql process to import $filePath");
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        throw new RuntimeException("Failed importing $filePath (exit code $code): " . trim($stderr));
    }
}

try {
    $serverPdo->exec("DROP DATABASE IF EXISTS `$targetDb`");
    $serverPdo->exec("CREATE DATABASE `$targetDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $repoRoot = dirname(__DIR__, 2);
    importSqlFile($targetDb, $repoRoot . '/database/udm_radar.sql', $dbHost, $dbUser, $dbPass);
    importSqlFile($targetDb, $repoRoot . '/database/seeds/001_synthetic_demo.sql', $dbHost, $dbUser, $dbPass);

    // Bind application connection to disposable database
    putenv("DB_NAME=$targetDb");
    $_ENV['DB_NAME'] = $targetDb;
    if (!defined('DB_NAME')) {
        define('DB_NAME', $targetDb);
    }

    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/analytics/service.php';

    $db = getDB();
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $targetDb) {
        throw new RuntimeException('Target database mismatch');
    }
    $db->exec('SET SESSION TRANSACTION READ ONLY');

    function acceptanceFingerprint(PDO $db): array {
        $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($tables as $table) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
                throw new RuntimeException('Unsafe table identifier');
            }
            $rows = array_map(static fn($row) => json_encode($row, JSON_THROW_ON_ERROR), $db->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC));
            sort($rows, SORT_STRING);
            $out[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
        }
        return $out;
    }

    $before = acceptanceFingerprint($db);
    if ($isSnapshot) {
        echo json_encode($before, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        $acceptanceCompleted = true;
        exit;
    }

    $passed = 0;
    $failed = 0;
    function acceptanceCheck(bool $ok, string $label): void {
        global $passed, $failed;
        $ok ? $passed++ : $failed++;
        echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    }

    $adminId = (int)$db->query("SELECT id FROM users WHERE role='admin' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    $faculty = $db->query("SELECT id FROM users WHERE role='faculty' AND is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    acceptanceCheck($adminId > 0 && count($faculty) >= 2, 'Documented synthetic Admin and two Faculty accounts are available');

    $db->beginTransaction();
    try {
        $all = analyticsReport($db, ['id' => $adminId, 'role' => 'admin'], []);
        foreach ($faculty as $facultyId) {
            $id = (int)$facultyId;
            $actor = ['id' => $id, 'role' => 'faculty'];
            $report = analyticsReport($db, $actor, []);
            $loads = $db->prepare('SELECT subject_id, section FROM faculty_class_loads WHERE faculty_user_id=?');
            $loads->execute([$id]);
            $assigned = $loads->fetchAll(PDO::FETCH_ASSOC);
            $subjects = array_map('strval', array_column($assigned, 'subject_id'));
            $sections = array_column($assigned, 'section');

            acceptanceCheck(!array_diff(array_column($report['groups']['subject'], 'key'), $subjects), 'Faculty ' . $id . ' academic subject groups are assigned only');
            acceptanceCheck(!array_diff(array_column($report['groups']['section'], 'key'), $sections), 'Faculty ' . $id . ' section groups are assigned only');
            acceptanceCheck(array_column($report['options']['faculty'], 'value') === [(string)$id], 'Faculty ' . $id . ' options reveal only self');

            $adminSubset = analyticsReport($db, ['id' => $adminId, 'role' => 'admin'], ['faculty' => (string)$id]);
            acceptanceCheck($report['summary'] === $adminSubset['summary'], 'Faculty ' . $id . ' and Admin filtered summary have exact parity');

            $own = $db->prepare('SELECT id FROM support_case_referrals WHERE faculty_id=? ORDER BY id');
            $own->execute([$id]);
            $ownIds = array_map('intval', $own->fetchAll(PDO::FETCH_COLUMN));
            acceptanceCheck(!array_diff(array_column($report['drilldowns'], 'record_id'), $ownIds), 'Faculty ' . $id . ' drill-down IDs belong to own referrals');
            acceptanceCheck($report['support']['referrals_created'] === count($ownIds), 'Faculty ' . $id . ' own-referral count matches existing synthetic records');

            $foreign = (int)array_values(array_filter($faculty, static fn($other) => (int)$other !== $id))[0];
            try {
                analyticsReport($db, $actor, ['faculty' => (string)$foreign]);
                acceptanceCheck(false, 'Foreign Faculty filter denied');
            } catch (DomainException) {
                acceptanceCheck(true, 'Faculty ' . $id . ' foreign Faculty filter denied');
            }

            $missing = analyticsReport($db, $actor, ['section' => 'UNASSIGNED-ACCEPTANCE']);
            acceptanceCheck($missing['summary']['student_count'] === 0 && $missing['support']['referrals_created'] === 0 && $missing['drilldowns'] === [], 'Faculty ' . $id . ' unassigned section reveals no grades/referrals');

            $foreignSubjects = $db->prepare('SELECT subject_id FROM faculty_class_loads WHERE faculty_user_id=?');
            $foreignSubjects->execute([$foreign]);
            $unassigned = array_values(array_diff(array_map('strval', $foreignSubjects->fetchAll(PDO::FETCH_COLUMN)), $subjects));
            acceptanceCheck(count($unassigned) > 0, 'Faculty ' . $id . ' has a concrete foreign-only subject to test');
            if ($unassigned) {
                $denied = analyticsReport($db, $actor, ['subject' => $unassigned[0]]);
                acceptanceCheck($denied['summary']['expected_grade_count'] === 0 && $denied['drilldowns'] === [], 'Faculty ' . $id . ' foreign-only subject reveals no records');
            }

            acceptanceCheck($report['faculty_comparison']['change']['direction'] === 'Unavailable', 'Faculty ' . $id . ' historical attribution remains unavailable');
            try {
                analyticsReport($db, ['id' => $id, 'role' => 'admin'], []);
                acceptanceCheck(false, 'Tampered actual role denied');
            } catch (DomainException) {
                acceptanceCheck(true, 'Faculty ' . $id . ' actual account role verified');
            }
        }

        foreach ([['student_id' => '999'], ['subject' => ['1']], ['semester' => '9'], ['school_year' => '2026-2029']] as $filters) {
            try {
                analyticsReport($db, ['id' => $adminId, 'role' => 'admin'], $filters);
                acceptanceCheck(false, 'Malformed/unsupported filter rejected');
            } catch (InvalidArgumentException) {
                acceptanceCheck(true, 'Malformed/unsupported filter rejected');
            }
        }

        acceptanceCheck(!str_contains(json_encode($all), 'student_id'), 'Aggregate report excludes raw Student IDs');
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $after = acceptanceFingerprint($db);
    acceptanceCheck($before === $after, 'All application tables preserve row counts and SHA-256 contents');
    echo "RESULT $passed passed, $failed failed; read-only acceptance on disposable database; schema and fixtures clean\n";
    $acceptanceCompleted = true;
} finally {
    unset($db);
    if ($serverPdo instanceof PDO) {
        $serverPdo->exec("DROP DATABASE IF EXISTS `$targetDb`");
        $remaining = (int)$serverPdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " . $serverPdo->quote($targetDb))->fetchColumn();
        if ($remaining !== 0) {
            fwrite(STDERR, "CRITICAL: Cleanup failed: disposable database '$targetDb' still exists!\n");
            exit(1);
        }
        echo "CLEANUP: Disposable database '$targetDb' dropped successfully (existence check: 0 remaining).\n";
    }
}

exit($failed ? 1 : 0);
