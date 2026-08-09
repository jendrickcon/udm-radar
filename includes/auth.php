<?php
// includes/auth.php — Session guard (replaces App.login() logic in main.py)
// Include at the top of EVERY protected page: require_once '../includes/auth.php';
/** @var string BASE_URL */
session_start();

function requireLogin(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
  }
}

function requireRole(string ...$roles): void {
  requireLogin();
  if (!in_array($_SESSION['role'], $roles)) {
    header('Location: ' . BASE_URL . 'login.php?error=unauthorized');
    exit;
  }
}

// Helper: get current user data
function currentUser(): array {
  return [
    'id'         => $_SESSION['user_id']   ?? null,
    'name'       => $_SESSION['name']       ?? '',       // generated column, still available for pages that weren't touched by the name-split migration
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name'  => $_SESSION['last_name']  ?? '',
    'role'       => $_SESSION['role']       ?? '',
    'identifier' => $_SESSION['identifier'] ?? '',
  ];
}

// CSRF check — was duplicated identically across admin/activity.php,
// students.php, faculty.php, and grades.php. Single source of truth now,
// since every one of those pages already requires this file anyway.
function checkCsrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
?>