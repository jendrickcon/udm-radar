<?php
require_once '../includes/auth.php';
require_once '../includes/grade_coverage.php';
require_once '../config/db.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
$user = currentUser();
$term = getCurrentTerm(); $records = []; $loadError = false;
try { $records = loadGradeCoverage(getDB(), $term); }
catch (Throwable $e) { error_log('Grade coverage query failed'); $loadError = true; http_response_code(503); }
$pageTitle = 'Grade Record Review';
$navItems = [['Dashboard','index.php',''],['Students','students.php',''],['Faculty','faculty.php',''],['Grades','grades.php',''],['Program Analytics','analytics.php',''],['Activity & Inbox','activity.php',''],['Settings','settings.php','']];
require_once '../includes/header.php';
if (!$loadError) require_once '../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/grade-coverage.css">
<?php renderGradeCoverage($records, $term, $loadError); ?>
<script src="<?= BASE_URL ?>assets/js/grade-coverage.js" defer></script>
<?php require_once '../includes/footer.php'; ?>
