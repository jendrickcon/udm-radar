<?php
// config/db.php — MySQL connection (replaces config.py)
// Place this file inside C:\xampp\htdocs\udm-radar\config\

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
