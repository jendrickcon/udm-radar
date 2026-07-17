<?php
// config/db.example.php — MySQL connection TEMPLATE
//
// Copy this file to config/db.php and fill in your real local values.
// config/db.php itself is gitignored and will never be committed —
// this .example file is the one that ships in the repo.

define('DB_HOST',   'localhost');
define('DB_USER',   'root');        // default XAMPP user
define('DB_PASS',   '');            // default XAMPP has no password
define('DB_NAME',   'udm_radar');

function getDB(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    try {
      $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (PDOException $e) {
      die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
    }
  }
  return $pdo;
}
?>
