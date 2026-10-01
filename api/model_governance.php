<?php
/**
 * api/model_governance.php — Protected Administrative Gateway for ML Model Governance
 *
 * Enforces:
 * 1. Administrator role authorization (requireRole('admin')).
 * 2. POST-only HTTP method policy.
 * 3. Centralized CSRF token verification.
 * 4. Strict action allowlist: ['status', 'train_candidate', 'cancel_candidate', 'promote_candidate'].
 * 5. Server-side shared secret management via getMlGovernanceSecret():
 *    - Fails closed with HTTP 503 if unconfigured.
 *    - Injects secret into internal Python ML requests via server-side X-API-Key header.
 *    - Never exposes secret to browser JavaScript, logs, or network responses.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/constants.php';

header('Content-Type: application/json');

// 1. Role Authorization: Admin only
requireRole('admin');

// 2. HTTP Method: POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method Not Allowed. Must be POST.']));
}

// 3. Centralized CSRF Verification
// Support tokens submitted via JSON body, $_POST, or X-CSRF-Token header
$rawInput = file_get_contents('php://input');
if (($rawInput === '' || $rawInput === false) && php_sapi_name() === 'cli') {
    $rawInput = @file_get_contents('php://stdin');
}
$inputData = json_decode($rawInput ?: '', true) ?? [];
$submittedToken = $inputData['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

if (!validateCsrfToken($submittedToken)) {
    http_response_code(403);
    exit(json_encode(['error' => 'CSRF validation failed.']));
}

// 4. Action Validation Against Strict Whitelist
$allowedActions = ['status', 'train_candidate', 'cancel_candidate', 'promote_candidate'];
$action = $_GET['action'] ?? $inputData['action'] ?? $_POST['action'] ?? '';

if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid governance action requested.']));
}

// 5. Fail-Closed Shared Secret Verification
$secret = getMlGovernanceSecret();
if ($secret === null || $secret === '') {
    http_response_code(503);
    exit(json_encode([
        'error' => 'ML governance secret is not configured on the server. Governance actions are disabled.'
    ]));
}

// 6. Handle Governance Actions
$mlBaseUrl = defined('PYTHON_ML_BASE_URL') ? PYTHON_ML_BASE_URL : 'http://127.0.0.1:5000';

if ($action === 'status') {
    http_response_code(200);
    // Provide sanitized status overview
    $metricsPath = dirname(__DIR__) . '/python_ml/model_metrics.json';
    $candidateMetricsPath = dirname(__DIR__) . '/python_ml/candidate_metrics.json';
    $candidateModelPath = dirname(__DIR__) . '/python_ml/candidate_model.pkl';

    $activeMetrics = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
    $candidateMetrics = file_exists($candidateMetricsPath) ? json_decode(file_get_contents($candidateMetricsPath), true) : null;
    $hasCandidate = file_exists($candidateModelPath) && $candidateMetrics !== null;

    echo json_encode([
        'success' => true,
        'has_candidate' => $hasCandidate,
        'active_metrics' => $activeMetrics,
        'candidate_metrics' => $candidateMetrics
    ]);
    exit;
}

// Actions proxying directly to Python Flask service
$targetEndpoint = $mlBaseUrl . '/api/' . $action;
$ch = curl_init($targetEndpoint);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$headers = [
    'X-API-Key: ' . $secret
];

if ($action === 'train_candidate') {
    // Validate uploaded file
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'No CSV file uploaded.']));
    }

    $uploadedFile = $_FILES['file'];
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit(json_encode(['error' => 'File upload error code: ' . (int) $uploadedFile['error']]));
    }

    $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        http_response_code(400);
        exit(json_encode(['error' => 'Upload Rejected: File must be a valid .csv format.']));
    }

    if ($uploadedFile['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        exit(json_encode(['error' => 'Upload Rejected: Dataset exceeds the 5MB size limit.']));
    }

    // Attach multipart file for cURL
    $cFile = new CURLFile($uploadedFile['tmp_name'], 'text/csv', $uploadedFile['name']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cFile]);
} else {
    // JSON / empty payload for cancel_candidate or promote_candidate
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['requested_by' => 'admin_proxy']));
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    exit(json_encode([
        'error' => 'ML microservice connection failure. Ensure the Python ML service is running.'
    ]));
}

http_response_code($httpCode ?: 200);
$decoded = json_decode($response, true);
if ($decoded !== null) {
    echo json_encode($decoded);
} else {
    echo $response;
}
