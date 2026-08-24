<?php
// includes/auth.php — Session guard
// Include at the top of EVERY protected page: require_once '../includes/auth.php';
/** @var string BASE_URL */

// FIXED: Configure secure session-cookie settings before starting the session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', 
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

function requireLogin(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
  }
}

function requireRole(string ...$roles): void {
  requireLogin();
  // FIXED: Strict role comparison
  if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
    header('Location: ' . BASE_URL . 'login.php?error=unauthorized');
    exit;
  }
}

// Helper: get current user data
function currentUser(): array {
  return [
    'id'         => $_SESSION['user_id']    ?? null,
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name'  => $_SESSION['last_name']  ?? '',
    'role'       => $_SESSION['role']       ?? '',
    'identifier' => $_SESSION['identifier'] ?? '',
  ];
}

// FIXED: Safe CSRF check that resolves gracefully if the token is missing entirely
function checkCsrf(): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    return $sessionToken !== '' 
        && $submittedToken !== '' 
        && hash_equals($sessionToken, $submittedToken);
}
?>