<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

$error = '';
$success = '';

// Handle form submission
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

// Fetch current term subjects for the dropdown
$stmtSubj = $db->prepare("
    SELECT s.id, s.code, s.title 
    FROM grades g 
    JOIN subjects s ON s.id = g.subject_id 
    WHERE g.student_id = ? AND g.is_current = 1
");
$stmtSubj->execute([$user['id']]);
$subjects = $stmtSubj->fetchAll();

// Fetch student's own feedback history
$stmtHist = $db->prepare("
    SELECT f.*, s.code AS subj_code 
    FROM feedback_reports f 
    LEFT JOIN subjects s ON s.id = f.subject_id 
    WHERE f.submitted_by = ? 
    ORDER BY f.created_at DESC
");
$stmtHist->execute([$user['id']]);
$history = $stmtHist->fetchAll();

$pageTitle = 'Feedback & Reports';
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
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Feedback & Reports</h1>
            <p>Submit grade concerns, data issues, or general feedback directly to the administration.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high);"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low);"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 24px;">
        <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">Submit a Report</h3>
        <form method="POST" action="feedback.php">
            <input type="hidden" name="action" value="submit_feedback">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Category</label>
                    <select name="category" id="cat-select" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark);" onchange="toggleSubject()">
                        <option value="">— Select Category —</option>
                        <option value="grade_concern">Grade Concern / Dispute</option>
                        <option value="data_issue">Profile / Data Issue</option>
                        <option value="general">General Feedback</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Subject (Optional)</label>
                    <select name="subject_id" id="subj-select" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark);" disabled>
                        <option value="">— Select a Subject (If applicable) —</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code'] . ' - ' . $s['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Message</label>
                <textarea name="message" required rows="4" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical;"></textarea>
            </div>

            <button type="submit" style="background:var(--accent-blue); color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Submit Feedback</button>
        </form>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--border-color);">
            <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">Your Submission History</h3>
        </div>

        <?php if (empty($history)): ?>
            <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">You have not submitted any reports yet.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="background: var(--table-header-bg); text-align: left;">
                        <th style="padding: 12px 24px; color:var(--text-dark); font-weight:600;">Date</th>
                        <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Category</th>
                        <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Subject</th>
                        <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Message</th>
                        <th style="padding: 12px 24px; color:var(--text-dark); font-weight:600;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 12px 24px; color: var(--text-gray);"><?= date('M j, Y', strtotime($h['created_at'])) ?></td>
                        <td style="padding: 12px; font-weight: 600; color: var(--text-dark);"><?= ucwords(str_replace('_', ' ', $h['category'])) ?></td>
                        <td style="padding: 12px; color: var(--accent-blue); font-weight:600;"><?= htmlspecialchars($h['subj_code'] ?? '—') ?></td>
                        <td style="padding: 12px; color: var(--text-gray); max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($h['message']) ?>"><?= htmlspecialchars($h['message']) ?></td>
                        <td style="padding: 12px 24px;">
                            <?php if ($h['status'] === 'open'): ?>
                                <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">OPEN</span>
                            <?php elseif ($h['status'] === 'resolved'): ?>
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

<script>
function toggleSubject() {
    const cat = document.getElementById('cat-select').value;
    const subj = document.getElementById('subj-select');
    if (cat === 'grade_concern') {
        subj.disabled = false;
        subj.required = true;
        subj.style.opacity = '1';
    } else {
        subj.disabled = true;
        subj.required = false;
        subj.value = '';
        subj.style.opacity = '0.6';
    }
}
</script>
<?php require_once '../includes/footer.php'; ?>