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

// ── AUTO-CLEANUP: Delete resolved OR rejected feedback older than 30 days ──
try {
    $db->query("DELETE FROM feedback_reports WHERE status IN ('resolved', 'rejected') AND resolved_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
} catch (PDOException $e) {
    // Fail silently, it's just a maintenance task
}

// Handle manual Inbox actions (Resolve or Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $error = 'Session expired.';
    } else {
        $action = $_POST['action'] ?? '';
        $fid = (int)$_POST['feedback_id'];
        
        if ($action === 'resolve_feedback') {
            $db->prepare("UPDATE feedback_reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
               ->execute([$user['id'], $fid]);
            $success = 'Report marked as resolved.';
        } elseif ($action === 'reject_feedback') {
            $db->prepare("UPDATE feedback_reports SET status = 'rejected', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
               ->execute([$user['id'], $fid]);
            $success = 'Report rejected.';
        }
    }
}

// 1. Fetch Feedback Inbox (Open Reports)
$stmtFeed = $db->query("
    SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, s.code AS subj_code,
           sp.section
    FROM feedback_reports f
    JOIN users u ON u.id = f.submitted_by
    LEFT JOIN subjects s ON s.id = f.subject_id
    LEFT JOIN student_profiles sp ON sp.user_id = f.submitted_by
    WHERE f.status = 'open'
    ORDER BY f.created_at DESC
");
$feedback = $stmtFeed->fetchAll();

// 2. Fetch Feedback History (Resolved & Rejected from Last 30 Days)
$stmtHistory = $db->query("
    SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, s.code AS subj_code,
           ru.first_name AS r_first, ru.last_name AS r_last
    FROM feedback_reports f
    JOIN users u ON u.id = f.submitted_by
    LEFT JOIN subjects s ON s.id = f.subject_id
    LEFT JOIN users ru ON ru.id = f.resolved_by
    WHERE f.status IN ('resolved', 'rejected')
    ORDER BY f.resolved_at DESC
");
$historyFeedback = $stmtHistory->fetchAll();

// 3. Fetch Pending Corrections (Admin proposed, awaiting confirm)
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

// 4. Fetch Admin Change Log (Permanent Audit Trail)
$stmtLog = $db->query("
    SELECT acl.*, au.first_name AS a_first, au.last_name AS a_last
    FROM admin_change_log acl
    JOIN users au ON au.id = acl.admin_id
    ORDER BY acl.created_at DESC LIMIT 50
");
$logs = $stmtLog->fetchAll();

$pageTitle = 'Activity & Inbox';
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
            <p>Process incoming feedback, track pending corrections, and review the administrative audit log. <br><span style="font-size: 0.8rem; color: var(--text-gray);">Note: Closed feedback reports are automatically purged after 30 days for privacy.</span></p>
        </div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high);"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low);"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <!-- Feedback Inbox Panel -->
    <div class="card" style="margin-bottom: 24px; padding: 0; overflow: hidden;">
        <div style="display:flex; align-items:center; gap:8px; padding: 20px 24px 16px;">
            <h3 style="color: var(--text-dark); font-size: 1.1rem; font-weight: 700; margin:0;">Inbox: Open Feedback Reports</h3>
            <?php if (count($feedback) > 0): ?>
                <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:700;"><?= count($feedback) ?> Action Required</span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($feedback)): ?>
            <p style="color: var(--risk-low); font-weight: 600; font-size: 0.9rem; padding: 0 24px 24px;">✓ All caught up! No open reports.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr>
                            <th style="border-bottom: 1px solid var(--border-color);">Submitted</th>
                            <th style="border-bottom: 1px solid var(--border-color);">From</th>
                            <th style="border-bottom: 1px solid var(--border-color);">Category / Subject</th>
                            <th style="border-bottom: 1px solid var(--border-color);">Message</th>
                            <th style="text-align:right; border-bottom: 1px solid var(--border-color);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedback as $f): ?>
                        <tr>
                            <td style="color: var(--text-gray); white-space:nowrap; vertical-align: top;"><?= date('M j, g:i a', strtotime($f['created_at'])) ?></td>
                            <td style="font-weight: 600; color: var(--text-dark); vertical-align: top;">
                                <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?><br>
                                <span style="font-size: 0.75rem; color:var(--text-gray); font-weight:normal;"><?= ucfirst($f['role']) ?>: <?= htmlspecialchars($f['identifier']) ?></span>
                            </td>
                            <td style="font-weight: 600; color: var(--accent-blue); vertical-align: top;">
                                <?= ucwords(str_replace('_', ' ', $f['category'])) ?><br>
                                <span style="font-size: 0.75rem; color:var(--text-gray); font-weight:normal;"><?= htmlspecialchars($f['subj_code'] ?? '') ?></span>
                            </td>
                            <td style="color: var(--text-gray); max-width: 250px; vertical-align: top;"><?= nl2br(htmlspecialchars($f['message'])) ?></td>
                            <td style="text-align:right; white-space:nowrap; vertical-align: top;">
                                <?php if ($f['category'] === 'grade_concern'): ?>
                                    <a href="grades.php?open_section=<?= urlencode($f['section'] ?? '') ?>&open_student=<?= $f['submitted_by'] ?>&feedback_id=<?= $f['id'] ?>" style="background:var(--accent-blue); color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:0.8rem; font-weight:600; display:inline-block; margin-bottom:8px; transition: opacity 0.2s;">Propose Fix</a><br>
                                <?php endif; ?>
                                <div style="display:flex; justify-content: flex-end; gap: 8px;">
                                    <form method="POST" action="activity.php" onsubmit="return confirm('Mark this report as resolved?');">
                                        <input type="hidden" name="action" value="resolve_feedback">
                                        <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); border:1px solid rgba(5, 150, 105, 0.3); padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer; font-family:inherit; transition: all 0.2s;">Resolve</button>
                                    </form>
                                    <form method="POST" action="activity.php" onsubmit="return confirm('Reject this report? It will be marked as invalid.');">
                                        <input type="hidden" name="action" value="reject_feedback">
                                        <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); border:1px solid rgba(220, 38, 38, 0.3); padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer; font-family:inherit; transition: all 0.2s;">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Closed Feedback History (30 Days) -->
    <div class="card" style="margin-bottom: 24px; padding: 0; overflow: hidden; display: flex; flex-direction: column; max-height: 350px;">
        <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">Closed Feedback History</h3>
            <span style="font-size: 0.8rem; color: var(--text-gray);">Last 30 Days</span>
        </div>
        <div style="overflow-y: auto; padding: 0;">
            <?php if (empty($historyFeedback)): ?>
                <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No closed feedback in the last 30 days.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th style="border-bottom: 1px solid var(--border-color);">Status</th>
                            <th style="border-bottom: 1px solid var(--border-color);">Closed On</th>
                            <th style="border-bottom: 1px solid var(--border-color);">From</th>
                            <th style="border-bottom: 1px solid var(--border-color);">Category</th>
                            <th style="border-bottom: 1px solid var(--border-color);">Message</th>
                            <th style="border-bottom: 1px solid var(--border-color);">Handled By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyFeedback as $hf): ?>
                        <tr>
                            <td style="vertical-align: top;">
                                <?php if ($hf['status'] === 'resolved'): ?>
                                    <span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">Resolved</span>
                                <?php else: ?>
                                    <span style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-gray); white-space:nowrap; vertical-align: top;"><?= date('M j, g:i a', strtotime($hf['resolved_at'])) ?></td>
                            <td style="font-weight: 600; color: var(--text-dark); vertical-align: top;">
                                <?= htmlspecialchars($hf['first_name'] . ' ' . $hf['last_name']) ?>
                            </td>
                            <td style="font-weight: 600; color: var(--accent-blue); vertical-align: top;">
                                <?= ucwords(str_replace('_', ' ', $hf['category'])) ?>
                            </td>
                            <td style="color: var(--text-gray); max-width: 200px; vertical-align: top;"><?= nl2br(htmlspecialchars($hf['message'])) ?></td>
                            <td style="font-weight: 600; color: var(--text-dark); vertical-align: top;">
                                <?= htmlspecialchars(($hf['r_first'] ?? 'Auto') . ' ' . ($hf['r_last'] ?? 'System')) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
        <!-- Pending Corrections Panel -->
        <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; max-height: 500px;">
            <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">Pending Corrections</h3>
            </div>
            <div style="overflow-y: auto; padding: 0;">
                <?php if (empty($pending)): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No pending corrections.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <tbody>
                            <?php foreach ($pending as $p): ?>
                            <tr>
                                <td style="border-bottom: 1px solid var(--border-color);">
                                    <span style="font-weight:600; color:var(--risk-mod);"><?= htmlspecialchars($p['target_type']) ?>: <?= htmlspecialchars($p['field_changed']) ?></span><br>
                                    <span style="color:var(--text-gray);"><?= htmlspecialchars($p['old_value']) ?> → <strong style="color:var(--text-dark);"><?= htmlspecialchars($p['new_value']) ?></strong></span><br>
                                    <span style="font-size:0.75rem; color:var(--text-gray);">Proposed by <?= htmlspecialchars($p['a_first'] . ' ' . $p['a_last']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Audit Log Panel (Permanent) -->
        <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; max-height: 500px;">
            <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">System Audit Log</h3>
                <span style="font-size: 0.8rem; color: var(--risk-high); font-weight: 600;">Permanent Record</span>
            </div>
            <div style="overflow-y: auto; padding: 0;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="width: 70px; color: var(--text-gray); vertical-align: top; border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);"><?= date('M j', strtotime($l['created_at'])) ?></td>
                            <td style="border-bottom: 1px solid var(--border-color);">
                                <span style="font-weight:600; color:var(--text-dark);"><?= htmlspecialchars($l['a_first'] . ' ' . $l['a_last']) ?></span> modified <span style="font-weight:600; color:var(--accent-blue);"><?= htmlspecialchars($l['target_type']) ?></span><br>
                                <span style="color:var(--text-gray);"><?= htmlspecialchars($l['field_changed']) ?>: <?= htmlspecialchars($l['old_value'] ?? 'null') ?> → <strong style="color:var(--text-dark);"><?= htmlspecialchars($l['new_value']) ?></strong></span>
                                <?php if ($l['note']): ?>
                                    <br><span style="font-size:0.75rem; color:var(--text-gray); display: inline-block; margin-top: 4px; padding: 4px 8px; background: var(--bg-color); border-radius: 4px;">Note: <?= htmlspecialchars($l['note']) ?></span>
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