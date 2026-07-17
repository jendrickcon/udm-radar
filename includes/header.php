<?php
// includes/header.php — opens <html>/<head>, links the ONE shared stylesheet.
// Expects (set by the calling page before include):
//   $pageTitle   string  e.g. "Student Portal"
// Requires config/constants.php to already be loaded for BASE_URL/APP_NAME.
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
</head>
<body>
