<?php
// tests/fixtures/render_student_grades.php
// Controlled renderer for student/grades.php with configurable student ID and theme

$studentId = isset($argv[1]) ? (int)$argv[1] : 5;
$theme = isset($argv[2]) ? $argv[2] : 'light';

chdir(__DIR__ . '/../../student');
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = $studentId;
$_SESSION['role'] = 'student';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Student';

require_once __DIR__ . '/../../includes/auth.php';

ob_start();
include 'grades.php';
$html = ob_get_clean();

if ($theme !== 'light') {
    $html = preg_replace('/<html([^>]*)data-theme="[^"]*"/', '<html$1data-theme="' . htmlspecialchars($theme) . '"', $html);
}

echo $html;
