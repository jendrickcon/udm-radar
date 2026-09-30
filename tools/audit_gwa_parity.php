<?php
/**
 * UDM-RADAR: GWA Parity Audit & Scratch Backfill Tool
 * 
 * Verifies mathematical parity between cached student_profiles.current_gwa
 * and canonical credit-unit-weighted computeStudentGwa().
 * 
 * Safety Contract:
 * - Defaults exclusively to 'udm_radar_scratch'.
 * - Refuses production database 'udm_radar' unless explicit flag --allow-live-read-only is passed.
 * - Backfill mode (--backfill) is STRICTLY PROHIBITED on production database.
 * - Read-only by default; never writes unless --backfill is explicitly passed on scratch DB.
 * - Sanitized output only (no names, emails, hashes, or PII).
 * 
 * Usage:
 *   php tools/audit_gwa_parity.php                       (Audit scratch DB in read-only mode)
 *   php tools/audit_gwa_parity.php --backfill            (Recalculate & persist GWA on scratch DB)
 *   php tools/audit_gwa_parity.php --export-csv          (Export sanitized audit CSV)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/constants.php';

// Parse CLI options
$options = getopt('', [
    'db:',
    'backfill',
    'export-csv',
    'allow-live-read-only',
    'help'
]);

if (isset($options['help'])) {
    echo "Usage: php tools/audit_gwa_parity.php [options]\n";
    echo "Options:\n";
    echo "  --db=<name>               Target database (default: udm_radar_scratch)\n";
    echo "  --backfill                Execute recalculateStudentGwa() (SCRATCH DB ONLY)\n";
    echo "  --export-csv              Export sanitized audit report CSV to backups/\n";
    echo "  --allow-live-read-only    Allow read-only comparison on live production DB\n";
    echo "  --help                    Show this help message\n";
    exit(0);
}

$targetDb = $options['db'] ?? 'udm_radar_scratch';
$isLive = ($targetDb === 'udm_radar');
$doBackfill = isset($options['backfill']);
$exportCsv = isset($options['export-csv']);

// Safety enforcement
if ($isLive) {
    if ($doBackfill) {
        fwrite(STDERR, "\n[FATAL ERROR] Backfill mode (--backfill) is STRICTLY PROHIBITED on the live database 'udm_radar'!\n");
        fwrite(STDERR, "Live database modifications require formal stakeholder approval and change freeze lift.\n");
        exit(2);
    }
    if (!isset($options['allow-live-read-only'])) {
        fwrite(STDERR, "\n[GUARD ERROR] Refusing to connect to live database 'udm_radar' without --allow-live-read-only flag.\n");
        fwrite(STDERR, "Default target is 'udm_radar_scratch'.\n");
        exit(2);
    }
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$targetDb;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "Could not connect to database '$targetDb': " . $e->getMessage() . "\n");
    exit(2);
}

// Double check active database
$activeDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($activeDb !== $targetDb) {
    fwrite(STDERR, "Database mismatch: expected '$targetDb', got '$activeDb'. Aborting.\n");
    exit(2);
}

echo "========================================================================\n";
echo "UDM-RADAR: GWA PARITY AUDIT TOOL\n";
echo "Target Database: $activeDb " . ($isLive ? "[LIVE PRODUCTION - READ-ONLY]" : "[SCRATCH TESTBED]") . "\n";
echo "Mode: " . ($doBackfill ? "WRITE-ENABLED (Backfill)" : "READ-ONLY (Audit)") . "\n";
echo "========================================================================\n\n";

// Execute pre-audit check
$stmtStudents = $db->query("
    SELECT sp.user_id, sp.student_number, sp.current_gwa, sp.record_status,
           EXISTS (
               SELECT 1 FROM grades g 
               WHERE g.student_id = sp.user_id 
                 AND g.is_current = 0 
                 AND g.final_grade = '0.00'
           ) AS has_legacy_zero
    FROM student_profiles sp 
    ORDER BY sp.user_id
");
$students = $stmtStudents->fetchAll();

$total = count($students);
$exactMatches = 0;
$discrepancies = 0;
$legacyZeroDiscrepancies = 0;
$otherDiscrepancies = 0;
$maxDelta = 0.0;
$sumAbsDelta = 0.0;
$results = [];

foreach ($students as $stu) {
    $uid = (int) $stu['user_id'];
    $stored = $stu['current_gwa'] !== null ? (float) $stu['current_gwa'] : null;
    $computed = computeStudentGwa($db, $uid);
    $hasZero = (bool) $stu['has_legacy_zero'];

    $diff = abs(($stored ?? 0.0) - ($computed ?? 0.0));
    $delta = ($computed ?? 0.0) - ($stored ?? 0.0);

    if ($diff < 0.0001) {
        $exactMatches++;
    } else {
        $discrepancies++;
        $sumAbsDelta += $diff;
        if ($diff > $maxDelta) {
            $maxDelta = $diff;
        }
        if ($hasZero) {
            $legacyZeroDiscrepancies++;
        } else {
            $otherDiscrepancies++;
        }
    }

    $results[] = [
        'user_id' => $uid,
        'student_number' => $stu['student_number'],
        'record_status' => $stu['record_status'],
        'stored_gwa' => $stored,
        'computed_gwa' => $computed,
        'delta' => round($delta, 4),
        'has_legacy_zero' => $hasZero ? 'YES' : 'NO',
        'is_match' => ($diff < 0.0001) ? 'MATCH' : 'DISCREPANCY'
    ];
}

$meanDelta = $discrepancies > 0 ? ($sumAbsDelta / $discrepancies) : 0.0;

echo "Audit Findings:\n";
echo "  Total Profiles Examined:      $total\n";
echo "  Exact Parity (Delta < 0.0001): $exactMatches (" . round(($exactMatches / max(1, $total)) * 100, 2) . "%)\n";
echo "  Discrepancies:                $discrepancies (" . round(($discrepancies / max(1, $total)) * 100, 2) . "%)\n";
if ($discrepancies > 0) {
    echo "    - Caused by Legacy 0.00:    $legacyZeroDiscrepancies (" . round(($legacyZeroDiscrepancies / $discrepancies) * 100, 2) . "% of discrepancies)\n";
    echo "    - Other Root Causes:        $otherDiscrepancies\n";
    echo "    - Mean Absolute Delta:      " . round($meanDelta, 4) . "\n";
    echo "    - Max Absolute Delta:       " . round($maxDelta, 4) . "\n";
}
echo "\n";

// Execute Backfill if requested and allowed
if ($doBackfill) {
    echo "Executing Backfill on scratch database '$activeDb'...\n";
    
    // Create backup table
    $backupTable = '_backup_student_profiles_gwa_wp4';
    $db->exec("DROP TABLE IF EXISTS `$backupTable`");
    $db->exec("CREATE TABLE `$backupTable` AS SELECT user_id, current_gwa FROM student_profiles");
    echo "  Backup snapshot preserved in `$backupTable`.\n";

    $db->beginTransaction();
    $updated = 0;
    foreach ($students as $stu) {
        $uid = (int) $stu['user_id'];
        recalculateStudentGwa($db, $uid);
        $updated++;
    }
    $db->commit();
    echo "  Successfully updated $updated student profiles.\n\n";

    // Verify 100% post-backfill parity
    $stmtRecheck = $db->query("SELECT user_id, current_gwa FROM student_profiles ORDER BY user_id");
    $postMismatches = 0;
    while ($r = $stmtRecheck->fetch()) {
        $uid = (int) $r['user_id'];
        $stored = (float) $r['current_gwa'];
        $computed = computeStudentGwa($db, $uid);
        if (abs($stored - ($computed ?? 0.0)) >= 0.0001) {
            $postMismatches++;
        }
    }
    echo "Post-Backfill Verification:\n";
    echo "  Post-Backfill Mismatches:     $postMismatches\n";
    if ($postMismatches === 0) {
        echo "  [SUCCESS] 100% Parity Achieved across all profiles!\n\n";
    } else {
        echo "  [FAIL] $postMismatches discrepancies remain!\n\n";
    }
}

// Export sanitized CSV if requested
if ($exportCsv) {
    $backupDir = dirname(__DIR__) . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    $csvFile = $backupDir . '/gwa_parity_audit_' . $activeDb . '_' . date('Ymd_His') . '.csv';
    $fp = fopen($csvFile, 'w');
    if ($fp) {
        fputcsv($fp, ['user_id', 'student_number', 'record_status', 'stored_gwa', 'computed_gwa', 'delta', 'has_legacy_zero', 'status']);
        foreach ($results as $row) {
            fputcsv($fp, [
                $row['user_id'],
                $row['student_number'],
                $row['record_status'],
                $row['stored_gwa'] !== null ? number_format($row['stored_gwa'], 2) : 'NULL',
                $row['computed_gwa'] !== null ? number_format($row['computed_gwa'], 2) : 'NULL',
                number_format($row['delta'], 4),
                $row['has_legacy_zero'],
                $row['is_match'],
            ]);
        }
        fclose($fp);
        echo "Sanitized CSV report exported to: $csvFile\n\n";
    }
}

// Exit code based on parity status
if ($discrepancies === 0 || ($doBackfill && $postMismatches === 0)) {
    echo "STATUS: PASS (100% Parity Verified)\n";
    exit(0);
} else {
    echo "STATUS: DISCREPANCIES DETECTED (Run with --backfill on scratch database to remediate)\n";
    exit(1);
}
