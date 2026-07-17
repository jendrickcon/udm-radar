<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $firstName  = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $facId      = trim($_POST['faculty_id'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$firstName || !$lastName || !$facId || !$password) {
        $error = 'First name, last name, faculty ID, and password are required.';
    } else {
        try {
            // NOTE: `name` is a GENERATED column — cannot appear in an INSERT
            // column list, only first_name/middle_name/last_name can.
            $stmt = $db->prepare("
                INSERT INTO users (user_id, password_hash, role, first_name, middle_name, last_name, email)
                VALUES (?, ?, 'faculty', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $facId,
                password_hash($password, PASSWORD_DEFAULT),
                $firstName,
                $middleName !== '' ? $middleName : null,
                $lastName,
                $email !== '' ? $email : null,
            ]);
            $success = "Faculty \"" . formatNameLastFirst($firstName, $middleName, $lastName) . "\" added successfully.";
        } catch (PDOException $e) {
            $error = 'Could not add faculty: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
    $stmt->execute([$delId]);
    $success = 'Faculty account removed.';
}

// Faculty list + how many students/subjects each one has actually graded
$faculty = $db->query("
    SELECT u.id, u.user_id, u.first_name, u.middle_name, u.last_name, u.email, u.is_active,
           COUNT(DISTINCT g.student_id) AS students_handled,
           COUNT(DISTINCT g.subject_id) AS subjects_handled
    FROM users u
    LEFT JOIN grades g ON g.encoded_by = u.id
    WHERE u.role = 'faculty'
    GROUP BY u.id
    ORDER BY u.last_name, u.first_name
")->fetchAll();

$pageTitle = 'Manage Faculty';
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
        <div><h1>Manage Faculty</h1><p>Full CRUD access — add or remove faculty accounts.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card">
        <div class="table-title">Add New Faculty</div>
        <form method="POST" action="faculty.php" style="display:grid; grid-template-columns: repeat(2, 1fr); gap:14px;">
            <input type="hidden" name="action" value="add">
            <input type="text" name="first_name" placeholder="First Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="last_name" placeholder="Last Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="middle_name" placeholder="Middle Name (optional)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="faculty_id" placeholder="Faculty ID (e.g. faculty02)" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="email" name="email" placeholder="Email" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="password" name="password" placeholder="Initial Password" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <button type="submit" style="grid-column: span 2; padding:10px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Add Faculty</button>
        </form>
    </div>

    <div class="card">
        <div class="table-title">All Faculty</div>
        <?php if (empty($faculty)): ?>
            <p class="empty-state">No faculty on record.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Faculty ID</th><th>Name</th><th>Email</th><th>Students Handled</th><th>Subjects Handled</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($faculty as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['user_id']) ?></td>
                    <td><?= htmlspecialchars(formatNameLastFirst($f['first_name'], $f['middle_name'], $f['last_name'])) ?></td>
                    <td><?= htmlspecialchars($f['email'] ?? '—') ?></td>
                    <td><?= (int) $f['students_handled'] ?></td>
                    <td><?= (int) $f['subjects_handled'] ?></td>
                    <td><a href="faculty.php?delete=<?= $f['id'] ?>" onclick="return confirm('Remove this faculty account? This cannot be undone.')" style="color:var(--risk-high); font-weight:600; text-decoration:none;">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
