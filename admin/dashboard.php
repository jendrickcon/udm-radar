<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = 'index.php' . ($qs !== '' ? '?' . $qs : '');
header('Location: ' . $target, true, 302);
exit;

