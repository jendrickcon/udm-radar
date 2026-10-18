<?php
/** Recommended cadence: every five minutes, with a ten-minute age threshold. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/pef_cc_export_service.php';
if (array_diff(array_slice($argv, 1), ['--delete', '--dry-run']) || (in_array('--delete', $argv, true) && in_array('--dry-run', $argv, true))) {
    fwrite(STDERR, "Usage: php scripts/cleanup_pef_cc_exports.php [--dry-run | --delete]\n");
    exit(2);
}
try {
    $delete = in_array('--delete', $argv, true);
    echo json_encode(['mode' => $delete ? 'delete' : 'dry-run'] + pefCcCleanupExports(pefCcConfig(), $delete), JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Private export cleanup is unavailable; check deployment and permissions.\n");
    exit(1);
}
