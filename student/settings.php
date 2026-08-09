<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

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
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $user['id']]);
        $success = 'Password updated successfully.';
    }
}

$pageTitle = 'Settings';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Grades & History',   'grades.php',    '📝'],
    ['Performance Trend',  'trend.php',     '📈'],
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="header">
        <div>
            <h1>Settings</h1>
            <p>Manage your account security.</p>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: 1fr 1fr; align-items: start;">

        <div class="card">
            <div class="table-title">Change Password</div>

            <?php if ($error): ?>
                <p style="background:rgba(220,38,38,0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high);"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p style="background:rgba(5,150,105,0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low);"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>

            <form method="POST" action="settings.php">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px; color:var(--text-dark);">Current Password</label>
                    <input type="password" name="current_password" required
                           style="width:100%; padding:12px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:1rem; background:var(--card-bg); color:var(--text-dark);">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px; color:var(--text-dark);">New Password</label>
                    <input type="password" name="new_password" required minlength="8"
                           style="width:100%; padding:12px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:1rem; background:var(--card-bg); color:var(--text-dark);">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px; color:var(--text-dark);">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8"
                           style="width:100%; padding:12px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:1rem; background:var(--card-bg); color:var(--text-dark);">
                </div>
                <button type="submit" style="width:100%; padding:14px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                    Update Password
                </button>
            </form>
        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>



