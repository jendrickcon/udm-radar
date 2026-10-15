<?php
/**
 * CLI-only test fixture runner for Faculty navigation and grade submission.
 * Operates in isolated CLI context with authenticated synthetic faculty session.
 * Targets udm_radar_scratch exclusively.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
chdir($root);

// Enforce target database isolation: target only udm_radar_scratch
putenv('DB_NAME=udm_radar_scratch');
$_ENV['DB_NAME'] = 'udm_radar_scratch';
if (!defined('DB_NAME')) {
    define('DB_NAME', 'udm_radar_scratch');
}

// Parse input arguments
$inputJson = $argv[1] ?? '{}';
$config = json_decode($inputJson, true) ?: [];

$userId = (int)($config['user_id'] ?? 2);
$page = $config['page'] ?? 'grades.php';
$getParams = $config['get'] ?? [];
$postParams = $config['post'] ?? [];

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['user_id'] = $userId;
$_SESSION['role'] = 'faculty';
$_SESSION['first_name'] = $config['first_name'] ?? 'SyntheticFaculty';
$_SESSION['last_name'] = $config['last_name'] ?? 'Tester';
$_SESSION['identifier'] = 'SYN-FAC-' . $userId;
$_SESSION['csrf_token'] = 'test-fixture-csrf-token';

// Setup environment and superglobals
$_SERVER['REQUEST_METHOD'] = !empty($postParams) ? 'POST' : 'GET';
$_SERVER['PHP_SELF'] = '/faculty/' . $page;
$_SERVER['REQUEST_URI'] = '/faculty/' . $page;
$_GET = $getParams;
$_POST = $postParams;

if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

chdir($root . '/faculty');
ob_start();
try {
    require $page;
    $output = ob_get_clean();
    echo $output;
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, 'FIXTURE_ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
