<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function checkCsrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

$error = '';
$success = '';

// Handle manual "Mark Resolved" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_feedback') {
    if (!checkCsrf()) {
        $error = 'Session expired.';
    } else {
        $fid = (int)$_POST['feedback_id'];
        $db->prepare("UPDATE feedback_reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
           ->execute([$user['id'], $fid]);
        $success = 'Report marked as resolved.';
    }
}

// 1. Fetch Feedback Inbox (Open Reports)
$stmtFeed = $db->query("
    SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, s.code AS subj_code 
    FROM feedback_reports f
    JOIN users u ON u.id = f.submitted_by
    LEFT JOIN subjects s ON s.id = f.subject_id
    WHERE f.status = 'open'
    ORDER BY f.created_at DESC
");
$feedback = $stmtFeed->fetchAll();

// 2. Fetch Pending Corrections (Admin proposed, awaiting confirm)
$stmtPend = $db->query("
    SELECT pc.*, u.first_name, u.last_name, 
           au.first_name AS a_first, au.last_name AS a_last
    FROM pending_corrections pc
    JOIN users au ON au.id = pc.proposed_by
    LEFT JOIN users u ON u.id = pc.target_id AND pc.target_type != 'grade'
    WHERE pc.status = 'pending'
    ORDER BY pc.proposed_at DESC
");
$pending = $stmtPend->fetchAll();

// 3. Fetch Admin Change Log (Audit Trail)
$stmtLog = $db->query("
    SELECT acl.*, au.first_name AS a_first, au.last_name AS a_last
    FROM admin_change_log acl
    JOIN users au ON au.id = acl.admin_id
    ORDER BY acl.created_at DESC LIMIT 50
");
$logs = $stmtLog->fetchAll();

$pageTitle = 'Recent Activity';
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
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Activity & Inbox</h1>
            <p>Resolve incoming feedback, track pending corrections, and review the administrative audit log.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <!-- Feedback Inbox Panel -->
    <div class="card" style="margin-bottom: 24px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin:0;">Inbox: Open Feedback Reports</h3>
            <?php if (count($feedback) > 0): ?>
                <span style="background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:700;"><?= count($feedback) ?> Action Required</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($feedback)): ?>
            <p style="color: #059669; font-weight: 600; font-size: 0.9rem;">✓ All caught up! No open reports.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                        <th style="padding: 12px;">Submitted</th>
                        <th style="padding: 12px;">From</th>
                        <th style="padding: 12px;">Category / Subject</th>
                        <th style="padding: 12px;">Message</th>
                        <th style="padding: 12px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedback as $f): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px; color: #475569; white-space:nowrap;"><?= date('M j, g:i a', strtotime($f['created_at'])) ?></td>
                        <td style="padding: 12px; font-weight: 600; color: #0f172a;">
                            <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?><br>
                            <span style="font-size: 0.75rem; color:#64748b; font-weight:normal;"><?= ucfirst($f['role']) ?>: <?= htmlspecialchars($f['identifier']) ?></span>
                        </td>
                        <td style="padding: 12px; font-weight: 600; color: #0e7490;">
                            <?= ucwords(str_replace('_', ' ', $f['category'])) ?><br>
                            <span style="font-size: 0.75rem; color:#64748b; font-weight:normal;"><?= htmlspecialchars($f['subj_code'] ?? '') ?></span>
                        </td>
                        <td style="padding: 12px; color: #475569; max-width: 250px;"><?= nl2br(htmlspecialchars($f['message'])) ?></td>
                        <td style="padding: 12px; text-align:right; white-space:nowrap;">
                            <?php if ($f['category'] === 'grade_concern'): ?>
                                <a href="grades.php?open_student=<?= $f['submitted_by'] ?>" style="background:var(--teal); color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:0.8rem; font-weight:600; display:inline-block; margin-bottom:4px;">Propose Fix</a><br>
                            <?php endif; ?>
                            <form method="POST" action="activity.php" style="display:inline;" onsubmit="return confirm('Mark this report as resolved?');">
                                <input type="hidden" name="action" value="resolve_feedback">
                                <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" style="background:#e2e8f0; color:#334155; border:none; padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer; font-family:inherit;">Mark Resolved</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Pending Corrections Panel -->
        <div class="card">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">Pending Corrections</h3>
            <?php if (empty($pending)): ?>
                <p style="color: #94a3b8; font-size: 0.9rem;">No pending corrections.</p>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <tbody>
                            <?php foreach ($pending as $p): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px;">
                                    <span style="font-weight:600; color:#d97706;"><?= htmlspecialchars($p['target_type']) ?>: <?= htmlspecialchars($p['field_changed']) ?></span><br>
                                    <span style="color:#64748b;"><?= htmlspecialchars($p['old_value']) ?> → <strong><?= htmlspecialchars($p['new_value']) ?></strong></span><br>
                                    <span style="font-size:0.75rem; color:#94a3b8;">Proposed by <?= htmlspecialchars($p['a_first'] . ' ' . $p['a_last']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Audit Log Panel -->
        <div class="card">
            <h3 style="color: #0f172a; font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">System Audit Log</h3>
            <div style="max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px; width: 70px; color: #64748b; vertical-align: top;"><?= date('M j', strtotime($l['created_at'])) ?></td>
                            <td style="padding: 10px;">
                                <span style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($l['a_first'] . ' ' . $l['a_last']) ?></span> modified <span style="font-weight:600; color:#0e7490;"><?= htmlspecialchars($l['target_type']) ?></span><br>
                                <span style="color:#475569;"><?= htmlspecialchars($l['field_changed']) ?>: <?= htmlspecialchars($l['old_value'] ?? 'null') ?> → <strong><?= htmlspecialchars($l['new_value']) ?></strong></span>
                                <?php if ($l['note']): ?>
                                    <br><span style="font-size:0.75rem; color:#94a3b8;">Note: <?= htmlspecialchars($l['note']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>