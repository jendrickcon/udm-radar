<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

$error = '';
$success = '';

// CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $firstName  = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $studentNo  = trim($_POST['student_number'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $course     = trim($_POST['course'] ?? '');
    $college    = trim($_POST['college'] ?? 'CCS');
    $yearLevel  = (int) ($_POST['year_level'] ?? 0);
    $section    = trim($_POST['section'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$firstName || !$lastName || !$studentNo || !$password) {
        $error = 'First name, last name, student number, and password are required.';
    } else {
        try {
            $db->beginTransaction();
            // NOTE: `name` is a GENERATED column (CONCAT_WS of first/middle/last)
            // — it cannot appear in an INSERT column list, only first/middle/last can.
            $stmt = $db->prepare("
                INSERT INTO users (user_id, password_hash, role, first_name, middle_name, last_name, email)
                VALUES (?, ?, 'student', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $studentNo,
                password_hash($password, PASSWORD_DEFAULT),
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $email !== '' ? $email : null,
            ]);
            $newUserId = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO student_profiles (user_id, student_number, course, college, year_level, section) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$newUserId, $studentNo, $course, $college, $yearLevel, $section]);
            $db->commit();
            $success = "Student \"" . formatNameLastFirst($firstName, $middleName, $lastName) . "\" added successfully.";
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Could not add student: ' . $e->getMessage();
        }
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$delId]);
    $success = 'Student removed.';
}

$students = $db->query("
    SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa,
           u.first_name, u.middle_name, u.last_name, u.email
    FROM student_profiles sp JOIN users u ON u.id = sp.user_id
    ORDER BY sp.section, u.last_name, u.first_name
")->fetchAll();

$pageTitle = 'Manage Students';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Manage Students',    'students.php',  '👥'],
    ['Manage Faculty',     'faculty.php',   '👨‍🏫'],
    ['Manage Grades',      'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div><h1>Manage Students</h1><p>Full CRUD access — add or remove student accounts.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card">
        <div class="table-title">Add New Student</div>
        <form method="POST" action="students.php" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px;">
            <input type="hidden" name="action" value="add">
            <input type="text" name="first_name" placeholder="First Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="middle_name" placeholder="Middle Name (optional)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="last_name" placeholder="Last Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="student_number" placeholder="Student No. (e.g. 23-22-041)" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="email" name="email" placeholder="Email" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="course" placeholder="Course" value="Bachelor of Science in Information Technology" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="section" placeholder="Section (e.g. IT-33)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <select name="year_level" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                <option value="3" selected>3rd Year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="4">4th Year</option>
            </select>
            <input type="password" name="password" placeholder="Initial Password" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" style="grid-column: span 1; padding:10px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Add Student</button>
        </form>
    </div>

    <div class="card">
        <div class="table-title">All Students</div>
        <?php if (empty($students)): ?>
            <p class="empty-state">No students on record.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Student No.</th><th>Name</th><th>Email</th><th>Section</th><th>Year</th><th>GWA</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['student_number']) ?></td>
                    <td><?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?></td>
                    <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                    <td><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                    <td><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                    <td><a href="students.php?delete=<?= $s['user_id'] ?>" onclick="return confirm('Remove this student account? This cannot be undone.')" style="color:var(--risk-high); font-weight:600; text-decoration:none;">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
