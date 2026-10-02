<?php
// includes/auth.php — Session guard
// Include at the top of EVERY protected page: require_once '../includes/auth.php';
/** @var string BASE_URL */

// FIXED: Configure secure session-cookie settings before starting the session
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', 
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Centralized CSRF token initialization: Ensure cryptographically secure token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Re-verifies the account behind the session against the database, and
// terminates the session if it is no longer permitted to authenticate.
//
// This exists because session authentication state can become stale after the
// underlying account is deactivated or archived — PHP stores session data
// server-side, but nothing in an ordinary session re-reads the account, so an
// already-authenticated session would keep working. The database is the
// authority, so every protected page revalidates `users.is_active` rather than
// trusting what was true at login time.
//
// record_status is deliberately NOT consulted here. Lifecycle state stays in
// the database; `users.is_active` is the single account-access flag, and this
// is the one place it is enforced per request.
function isSessionAccountActive(): bool {
  static $checked = null; // one check per request, not one per call
  if ($checked !== null) return $checked;

  $userId = $_SESSION['user_id'] ?? null;
  if (!$userId) return $checked = false;

  try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $isActive = $stmt->fetchColumn();

    // No row at all also fails closed.
    return $checked = ($isActive !== false && (int) $isActive === 1);
  } catch (Throwable $e) {
    // Fail closed on an infrastructure failure rather than granting access on
    // an unverifiable session, and record why server-side.
    error_log('Session account-state check failed: ' . $e->getMessage());
    return $checked = false;
  }
}

function requireLogin(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
  }

  if (!isSessionAccountActive()) {
    // Destroy rather than merely redirect, so the stale session cannot be
    // replayed against another endpoint.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?error=deactivated');
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

// ============================================================================
// CENTRALIZED CSRF DEFENSE HELPERS
// ============================================================================

/**
 * Returns the active session CSRF token, initializing one if absent.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Regenerates the CSRF token on privilege escalation / login.
 */
function regenerateCsrfToken(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Timing-safe validation of a submitted CSRF token against the session token.
 */
function validateCsrfToken(?string $token): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '' || $token === null || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

/**
 * Checks CSRF token submitted via POST or HTTP_X_CSRF_TOKEN header.
 */
function checkCsrf(): bool {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    return validateCsrfToken($submittedToken);
}
?>