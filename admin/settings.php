<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $success = 'Password updated successfully.';
    }
}

$pageTitle = 'Settings';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Students',           'students.php',  '👥'],
    ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Activity & Inbox',   'activity.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header"><div><h1>Settings</h1><p>Manage your account security.</p></div></div>

    <div class="card" style="max-width:480px;">
        <div class="table-title">Change Password</div>
        <?php if ($error): ?>
            <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <form method="POST" action="settings.php">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Current Password</label>
                <input type="password" name="current_password" required style="width:100%; padding:12px 14px; border:1px solid #ddd; border-radius:8px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">New Password</label>
                <input type="password" name="new_password" required minlength="8" style="width:100%; padding:12px 14px; border:1px solid #ddd; border-radius:8px;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8" style="width:100%; padding:12px 14px; border:1px solid #ddd; border-radius:8px;">
            </div>
            <button type="submit" style="width:100%; padding:14px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Update Password</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
