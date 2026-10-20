<?php
declare(strict_types=1);

// Buffer bootstrap output so an error can never be mistaken for an XLSX download.
ini_set('display_errors', '0');
$pefCcDownloadBufferLevel = ob_get_level();
$pefCcDownloadFatalReserve = str_repeat(' ', 65536);
ob_start();
register_shutdown_function(static function () use ($pefCcDownloadBufferLevel, &$pefCcDownloadFatalReserve): void {
    $pefCcDownloadFatalReserve = '';
    $lastError = error_get_last();
    if ($lastError === null || !in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true) || headers_sent()) {
        return;
    }
    while (ob_get_level() > $pefCcDownloadBufferLevel) {
        ob_end_clean();
    }
    http_response_code(503);
    header('HTTP/1.1 503 Service Unavailable', true, 503);
    header_remove('Content-Disposition');
    header_remove('Content-Length');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo '{"error":"The planning copy could not be prepared. Please refresh your preview and try again."}';
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Planning export unavailable · UDM-RADAR</title></head><body><main><h1>Planning copy could not be prepared</h1><p>Please refresh your preview and try again.</p><a href="enrolment_planning.php">Refresh planning preview</a></main></body></html>';
    }
    // Commit the safe status before the SAPI's default fatal-error response.
    flush();
    // Do not exit: the export service registers its own shutdown file cleanup.
});
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pef_cc.php';
require_once __DIR__ . '/../includes/pef_cc_repository.php';
require_once __DIR__ . '/../includes/pef_cc_view_model.php';
require_once __DIR__ . '/../includes/pef_cc_snapshot.php';
require_once __DIR__ . '/../includes/pef_cc_export_service.php';

requireRole('student');
$user = currentUser();
$config = pefCcConfig();
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

function pefCcDownloadError(int $status, string $message, array $details = []): never
{
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($status);
    header_remove('Content-Disposition');
    header_remove('Content-Length');
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => $message, 'details' => $details], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Planning export · UDM-RADAR</title><link rel="stylesheet" href="' . $escape(BASE_URL) . 'assets/css/dashboard.css"><link rel="stylesheet" href="' . $escape(BASE_URL) . 'assets/css/pef-cc.css"></head><body><main class="pef-cc-page pef-cc-export-error"><section class="card" role="alert"><h1>Planning copy could not be downloaded</h1><p>' . $escape($message) . '</p>';
        if ($details !== []) {
            echo '<ul>';
            foreach ($details as $detail) {
                echo '<li>' . $escape((string) $detail) . '</li>';
            }
            echo '</ul>';
        }
        echo '<a class="btn btn--primary" href="enrolment_planning.php">Refresh planning preview</a></section></main></body></html>';
    }
    exit;
}

// Section 10: Fail closed on obsolete template
$activeReviewedContract = pefCcTemplateContract('v0.2-r6');
if (($config['template_version'] ?? '') !== 'v0.2-r6'
    || !hash_equals($activeReviewedContract['sha256'], (string) ($config['template_sha256'] ?? ''))) {
    error_log(sprintf(
        'PEF/CC export rejected obsolete template: expected v0.2-r6 (%s), resolved %s (%s)',
        $activeReviewedContract['sha256'],
        $config['template_version'] ?? 'null',
        $config['template_sha256'] ?? 'null'
    ));
    pefCcDownloadError(409, 'The planning workbook template changed. Refresh the preview before downloading.');
}

/** Never depend on a possibly absent Content-Length for the request budget. */
function pefCcDownloadBodyWithinLimit(int $limit): bool
{
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        return false;
    }
    $bytes = 0;
    try {
        while (!feof($stream) && $bytes <= $limit) {
            $chunk = fread($stream, min(8192, $limit + 1 - $bytes));
            if ($chunk === false) {
                return false;
            }
            $bytes += strlen($chunk);
            if ($chunk === '') {
                break;
            }
        }
        return $bytes <= $limit;
    } finally {
        fclose($stream);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    pefCcDownloadError(405, 'Download your planning copy using its preview form.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $config['max_request_bytes']
    || !pefCcDownloadBodyWithinLimit($config['max_request_bytes'])) {
    pefCcDownloadError(413, 'The request is too large. Refresh the planning preview.');
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
    pefCcDownloadError(415, 'Use the supplied planning preview form to download your copy.');
}
if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
    pefCcDownloadError(403, 'Your session could not verify this request. Reload the planning page.');
}
$allowed = ['csrf_token', 'action', 'preview', 'evaluation_year', 'evaluation_semester', 'target_year', 'target_semester', 'section', 'subject_ids'];
if ($_GET !== [] || $_FILES !== [] || array_diff(array_keys($_POST), $allowed) !== []
    || ($_POST['action'] ?? '') !== 'download') {
    pefCcDownloadError(422, 'Unexpected planning fields. Refresh the preview using only the supplied controls.');
}
if (!is_string($_POST['preview'] ?? null) || $_POST['preview'] === '' || strlen($_POST['preview']) > 4096) {
    pefCcDownloadError(409, 'The preview is missing or no longer valid. Refresh it before downloading.');
}
$signingKey = $_SESSION['pef_cc_signing_key'] ?? null;
$sessionBinding = session_id();
session_write_close();
if (!is_string($signingKey) || strlen($signingKey) !== 32) {
    pefCcDownloadError(409, 'This preview belongs to another or expired session. Refresh it before downloading.');
}
$inputs = array_intersect_key($_POST, array_flip(['evaluation_year', 'evaluation_semester', 'target_year', 'target_semester', 'section', 'subject_ids']));
$output = null;
$pdf = null;
$handle = null;
$previousAbort = ignore_user_abort(true);
try {
    try {
        $inputs = pefCcNormalizeInputs($inputs, $config);
    } catch (PefCcValidationException $e) {
        pefCcDownloadError(422, 'Review the planning inputs before downloading.', $e->errors);
    }
    try {
        $reference = pefCcTemplateReference($config);
    } catch (Throwable $e) {
        error_log('PEF/CC download template check failed: ' . $e->getMessage());
        pefCcDownloadError(503, 'The approved planning template is unavailable. Please try again later.');
    }
    $db = getDB();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $context = pefCcReadContext($db, (int) $user['id']);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
    try {
        $model = pefCcBuildViewModel($context, $inputs, $reference, $config, getCurrentTerm());
    } catch (PefCcValidationException $e) {
        // Inputs valid syntactically but their authoritative catalog/records changed.
        pefCcDownloadError(409, 'Your planning records or choices changed. Refresh the preview before downloading.');
    }
    if (!$model->data['exportable'] || !pefCcVerifySnapshot($_POST['preview'], $model, $signingKey, (int) $user['id'], $sessionBinding, $config)) {
        pefCcDownloadError(409, 'Your academic records or planning details changed after preview. Refresh the preview before downloading.');
    }
    $output = pefCcGenerateWorkbook($model, $config);
    $pdf = pefCcRenderPdfFromWorkbook($output['path'], $config);
    $output = null;
    if (!is_file($pdf['path']) || !is_readable($pdf['path']) || filesize($pdf['path']) !== $pdf['bytes']
        || $pdf['bytes'] <= 0 || $pdf['bytes'] > $config['max_output_bytes']) {
        throw new RuntimeException('Generated planning PDF did not pass the delivery size check.');
    }
    $handle = fopen($pdf['path'], 'rb');
    if ($handle === false) {
        throw new RuntimeException('Generated planning PDF could not be opened.');
    }
    // Headers are emitted only after generation, verification, and opening succeeded.
    ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="UDM-RADAR-PEF-CC.pdf"');
    header('Content-Length: ' . $pdf['bytes']);
    while (!feof($handle) && !connection_aborted()) {
        $chunk = fread($handle, 65536);
        if ($chunk === false) {
            throw new RuntimeException('PDF stream interrupted.');
        }
        echo $chunk;
        flush();
    }
} catch (Throwable $e) {
    error_log('PEF/CC export failed: ' . $e->getMessage());
    if (!headers_sent()) {
        // exit() bypasses PHP finally blocks: remove any completed output before rendering this error.
        if (is_resource($handle)) {
            fclose($handle);
            $handle = null;
        }
        if (is_array($output) && isset($output['path'])) {
            pefCcRemoveOutput($output['path'], $config);
            $output = null;
        }
        if (is_array($pdf) && isset($pdf['path'])) {
            pefCcRemoveOutput($pdf['path'], $config);
            $pdf = null;
        }
        $msg = ($e->getMessage() === 'The planning PDF is currently being prepared. Try again shortly.')
            ? 'The planning PDF is currently being prepared. Try again shortly.'
            : 'The planning copy could not be prepared. Please refresh your preview and try again.';
        pefCcDownloadError(503, $msg);
    }
} finally {
    if (is_resource($handle)) {
        fclose($handle);
    }
    if (is_array($output) && isset($output['path'])) {
        pefCcRemoveOutput($output['path'], $config);
    }
    if (is_array($pdf) && isset($pdf['path'])) {
        pefCcRemoveOutput($pdf['path'], $config);
    }
    ignore_user_abort((bool) $previousAbort);
}
