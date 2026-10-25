<?php
// config/db.php — MySQL connection
// Note: Ensure this file is added to .gitignore in your repository.

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');        // default XAMPP user
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''); // default XAMPP has no password
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'udm_radar');

function getDB(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // FIXED: Enforces strict native prepared statements
      ]);
    } catch (PDOException $e) {
      // FIXED: Logs the actual error securely to the server, but returns a generic 500 to the user
      error_log('Database connection failed: ' . $e->getMessage());
      http_response_code(500);
      exit('Database connection unavailable.');
    }
  }
  return $pdo;
}
?>