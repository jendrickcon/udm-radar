<?php
/**
 * tools/rebuild_database.php — Clean Rebuild of Scratch or Demo Database from Canonical Dump & Migrations
 *
 * PURPOSE:
 * Rebuilds udm_radar_scratch or udm_radar_demo from the baseline canonical SQL dump
 * (database/udm_radar.sql) and the complete migration sequence (000 through 008).
 *
 * STRICT GOVERNANCE RULES:
 * - Live production database ('udm_radar') is STRICTLY FORBIDDEN and CANNOT be rebuilt.
 * - Requires explicit --target=scratch or --target=demo flag.
 *
 * USAGE:
 *   php tools/rebuild_database.php --target=scratch
 *   php tools/rebuild_database.php --target=demo
 */

declare(strict_types=1);

const ALLOWED_TARGETS = [
    'scratch' => 'udm_radar_scratch',
    'demo'    => 'udm_radar_demo',
];

$target = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, 9);
    }
}

if ($target === null || !array_key_exists($target, ALLOWED_TARGETS)) {
    fwrite(STDERR, "FATAL: Must specify an allowed target database option: --target=scratch or --target=demo\n");
    if ($target !== null) {
        fwrite(STDERR, "Refused disallowed target: '$target'. Live, production, and arbitrary targets are strictly forbidden.\n");
    } else {
        fwrite(STDERR, "Target option missing. Refusing execution.\n");
    }
    exit(1);
}

$dbName = ALLOWED_TARGETS[$target];

// Strict safeguard: live production database cannot be rebuilt under any circumstance
if ($dbName === 'udm_radar' || in_array($target, ['live', 'production', 'udm_radar'], true)) {
    fwrite(STDERR, "FATAL: Live production database cannot be rebuilt.\n");
    exit(1);
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';

echo "========================================================================\n";
echo "UDM-RADAR: DATABASE REBUILD TOOL\n";
echo "Selected Target: $target\n";
echo "Target Database: $dbName\n";
echo "========================================================================\n\n";

try {
    $rootPdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not connect to MySQL server: " . $e->getMessage() . "\n");
    exit(1);
}

// 1. Drop and recreate target database cleanly
echo "[1/4] Dropping and recreating database '$dbName'...\n";
$rootPdo->exec("DROP DATABASE IF EXISTS `$dbName`");
$rootPdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

// 2. Import baseline canonical dump
$mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$dumpFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'udm_radar.sql';

if (!file_exists($dumpFile)) {
    fwrite(STDERR, "FATAL: Canonical dump file not found: $dumpFile\n");
    exit(1);
}

function executeSqlFile(string $mysqlBin, string $dbUser, string $dbName, string $filePath): int {
    $cmd = sprintf('"%s" -u %s %s', $mysqlBin, escapeshellarg($dbUser), escapeshellarg($dbName));
    $descriptors = [
        0 => ['file', $filePath, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return -1;
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 && !empty($stderr)) {
        fwrite(STDERR, "\nMySQL Error: " . trim($stderr) . "\n");
    }
    return $code;
}

echo "[2/4] Importing baseline canonical dump (database/udm_radar.sql)...\n";
$retCode = executeSqlFile($mysqlBin, $dbUser, $dbName, $dumpFile);
if ($retCode !== 0) {
    fwrite(STDERR, "FATAL: Failed to import baseline canonical dump (exit code $retCode).\n");
    exit(1);
}

// 3. Apply all migrations in order
$migrationsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$migrationFiles = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql');
sort($migrationFiles);

echo "[3/4] Applying migration chain (000 through 008)...\n";
foreach ($migrationFiles as $mig) {
    $baseName = basename($mig);
    echo "  -> Applying $baseName... ";
    $retCode = executeSqlFile($mysqlBin, $dbUser, $dbName, $mig);
    if ($retCode !== 0) {
        echo "FAILED\n";
        fwrite(STDERR, "FATAL: Migration failed: $baseName (exit code $retCode)\n");
        exit(1);
    }
    echo "DONE\n";
}

// 3b. Synchronize GWA parity on target database
echo "  -> Synchronizing canonical GWA parity (WP-4 standard)... ";
$parityScript = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'audit_gwa_parity.php';
$parityCmd = sprintf('php "%s" --backfill', $parityScript);
putenv("DB_NAME=$dbName");
exec($parityCmd, $parityOut, $parityRet);
if ($parityRet === 0) {
    echo "DONE (100% Parity)\n";
} else {
    echo "WARNING (exit code $parityRet)\n";
}

// 4. Verify table inventory and constraints
echo "[4/4] Verifying schema and table inventory in '$dbName'...\n";
$targetPdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$allTables = $targetPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$appTables = array_filter($allTables, fn($t) => !str_starts_with($t, '_backup_'));
$backupTables = array_filter($allTables, fn($t) => str_starts_with($t, '_backup_'));

echo "  Total tables: " . count($allTables) . "\n";
echo "  Official application tables: " . count($appTables) . " (Expected: 18)\n";
echo "  Temporary backup tables: " . count($backupTables) . " (" . implode(', ', $backupTables) . ")\n";

if (count($appTables) !== 18) {
    fwrite(STDERR, "ERROR: Expected exactly 18 official application tables, found " . count($appTables) . "!\n");
    exit(1);
}

echo "\n[SUCCESS] Rebuild of '$dbName' completed successfully with 18 official application tables.\n";
echo "========================================================================\n";
