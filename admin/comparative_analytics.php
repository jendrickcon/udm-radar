<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$params = $_GET;
if (!isset($params['tab']) || $params['tab'] === '') {
    $params['tab'] = 'comparisons';
}

$target = 'analytics.php?' . http_build_query($params);
header('Location: ' . $target, true, 302);
exit;
