<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_feedback') {
    $category  = $_POST['category'] ?? '';
    $subjectId = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    $message   = trim($_POST['message'] ?? '');

    if (!in_array($category, ['grade_concern', 'data_issue', 'general']) || empty($message)) {
        $error = 'Category and message are required.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO feedback_reports (submitted_by, category, subject_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $category, $subjectId, $message]);
            $success = 'Feedback submitted successfully.';
        } catch (PDOException $e) {
            $error = 'Could not submit feedback: ' . $e->getMessage();
        }
    }
}

// Subjects faculty currently teaches
$stmtSubj = $db->prepare("
    SELECT DISTINCT s.id, s.code, s.title 
    FROM faculty_class_loads fcl 
    JOIN subjects s ON s.id = fcl.subject_id 
    WHERE fcl.faculty_user_id = ?
");
$stmtSubj->execute([$user['id']]);
$subjects = $stmtSubj->fetchAll();

// Faculty's own submissions
$stmtHist = $db->prepare("
    SELECT f.*, s.code AS subj_code 
    FROM feedback_reports f 
    LEFT JOIN subjects s ON s.id = f.subject_id 
    WHERE f.submitted_by = ? 
    ORDER BY f.created_at DESC
");
$stmtHist->execute([$user['id']]);
$my_submissions = $stmtHist->fetchAll();

// Inbox: Feedback from students tagged to subjects this faculty teaches
$stmtInbox = $db->prepare("
    SELECT f.*, s.code AS subj_code, u.first_name, u.last_name, u.user_id AS student_no
    FROM feedback_reports f
    JOIN subjects s ON s.id = f.subject_id
    JOIN users u ON u.id = f.submitted_by
    JOIN faculty_class_loads fcl ON fcl.subject_id = f.subject_id
    WHERE fcl.faculty_user_id = ? AND u.role = 'student'
    GROUP BY f.id
    ORDER BY f.created_at DESC
");
$stmtInbox->execute([$user['id']]);
$inbox = $stmtInbox->fetchAll();

$pageTitle = 'Feedback & Reports';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Feedback & Reports</h1>
            <p>Submit system issues to Admin, or read student concerns regarding your assigned subjects.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high);"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low);"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Left: Submit Form -->
        <div class="card">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">Contact Administration</h3>
            <form method="POST" action="feedback.php">
                <input type="hidden" name="action" value="submit_feedback">
                
                <div style="margin-bottom: 12px;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Category</label>
                    <select name="category" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark);">
                        <option value="">— Select Category —</option>
                        <option value="data_issue">Data / System Issue</option>
                        <option value="general">General Inquiry</option>
                    </select>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Message</label>
                    <textarea name="message" required rows="4" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical;"></textarea>
                </div>

                <button type="submit" style="background:var(--accent-blue); color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Send to Admin</button>
            </form>
        </div>

        <!-- Right: My Submissions -->
        <div class="card">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">My Sent Reports</h3>
            <?php if (empty($my_submissions)): ?>
                <p style="color: var(--text-gray); font-size: 0.9rem;">You have not sent any reports.</p>
            <?php else: ?>
                <div style="max-height: 250px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <tbody>
                            <?php foreach ($my_submissions as $h): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px; width: 80px; color: var(--text-gray);"><?= date('M j', strtotime($h['created_at'])) ?></td>
                                <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= ucwords(str_replace('_', ' ', $h['category'])) ?></td>
                                <td style="padding: 10px;">
                                    <?php if ($h['status'] === 'open'): ?>
                                        <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">OPEN</span>
                                    <?php elseif ($h['status'] === 'resolved'): ?>
                                        <span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">RESOLVED</span>
                                    <?php else: ?>
                                        <span style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">REJECTED</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Student Feedback Inbox -->
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--border-color);">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px;">Student Concerns Inbox</h3>
            <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Read-only view. Only Administration can formally resolve these reports.</p>
        </div>
        
        <?php if (empty($inbox)): ?>
            <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No student feedback reported for your subjects.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--table-header-bg); text-align: left;">
                        <th style="padding: 12px 24px; color:var(--text-dark); font-weight:600;">Date</th>
                        <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Student</th>
                        <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Subject</th>
                        <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Concern</th>
                        <th style="padding: 12px 24px; color:var(--text-dark); font-weight:600;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inbox as $msg): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 12px 24px; color: var(--text-gray); vertical-align: top;"><?= date('M j, Y', strtotime($msg['created_at'])) ?></td>
                        <td style="padding: 12px; font-weight: 600; color: var(--text-dark); vertical-align: top;">
                            <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?><br>
                            <span style="font-size: 0.75rem; color:var(--text-gray); font-weight:normal;"><?= htmlspecialchars($msg['student_no']) ?></span>
                        </td>
                        <td style="padding: 12px; color: var(--accent-blue); font-weight: 600; vertical-align: top;"><?= htmlspecialchars($msg['subj_code']) ?></td>
                        <td style="padding: 12px; color: var(--text-gray); max-width: 300px; vertical-align: top;"><?= nl2br(htmlspecialchars($msg['message'])) ?></td>
                        <td style="padding: 12px 24px; vertical-align: top;">
                            <?php if ($msg['status'] === 'open'): ?>
                                <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">OPEN</span>
                            <?php elseif ($msg['status'] === 'resolved'): ?>
                                <span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">RESOLVED</span>
                            <?php else: ?>
                                <span style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">REJECTED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>