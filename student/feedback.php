<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('student');
$user = currentUser();
$db = getDB();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

$valid_categories = ['grade_concern', 'data_issue', 'general'];
$valid_periods = ['prelim', 'midterm', 'prefinal', 'final_grade'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    try {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($sessionToken === '' || $csrf === '' || !hash_equals($sessionToken, $csrf)) {
            throw new Exception("Security validation failed. Please refresh the page and try again.");
        }

        if ($action === 'submit_ticket') {
            $stmtOpen = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND status NOT IN ('resolved', 'rejected')");
            $stmtOpen->execute([$user['id']]);
            if ((int) $stmtOpen->fetchColumn() >= 3) {
                throw new Exception('You already have three active tickets. Please wait for an existing ticket to be reviewed before submitting another.');
            }

            $stmtRecent = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            $stmtRecent->execute([$user['id']]);
            if ((int) $stmtRecent->fetchColumn() > 0) {
                throw new Exception('Please wait 10 minutes before submitting another ticket.');
            }

            $category    = strtolower(trim((string)($_POST['category'] ?? '')));
            $subjectId   = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
            $gradePeriod = !empty($_POST['grade_period']) ? $_POST['grade_period'] : null;
            $title       = trim($_POST['title'] ?? '');
            $message     = trim($_POST['message'] ?? '');
            
            if (!in_array($category, $valid_categories, true)) throw new Exception("Invalid category selected.");
            if (mb_strlen($title) > 150) throw new Exception("The report title must not exceed 150 characters.");
            if (mb_strlen($message) > 5000) throw new Exception("The report message must not exceed 5,000 characters.");
            if (empty($title) || empty($message)) throw new Exception("Title and message are required.");

            if ($category === 'grade_concern') {
                if (!$subjectId || !in_array($gradePeriod, $valid_periods, true)) {
                    throw new Exception("Grade disputes require a valid Subject and Grading Period.");
                }
                
                $stmtDup = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND category IN ('grade_concern', 'grade_dispute') AND subject_id = ? AND grade_period = ? AND status NOT IN ('resolved', 'rejected')");
                $stmtDup->execute([$user['id'], $subjectId, $gradePeriod]);
                if ((int)$stmtDup->fetchColumn() > 0) {
                    throw new Exception('You already have an active grade dispute for this exact subject and grading period.');
                }

                $stmtS = $db->prepare("SELECT sp.section, g.school_year, g.semester FROM student_profiles sp JOIN grades g ON sp.user_id = g.student_id WHERE sp.user_id = ? AND g.subject_id = ? AND g.is_current = 1 LIMIT 1");
                $stmtS->execute([$user['id'], $subjectId]);
                $ctx = $stmtS->fetch(PDO::FETCH_ASSOC);
                if (!$ctx) {
                    throw new Exception("You are not currently enrolled in the selected subject.");
                }
                $section = $ctx['section']; $sy = $ctx['school_year']; $sem = $ctx['semester'];
            } else {
                $subjectId = null; 
                $gradePeriod = null;
                $section = null; $sy = null; $sem = null;
            }

            $stmt = $db->prepare("INSERT INTO feedback_reports (submitted_by, category, subject_id, section, school_year, semester, grade_period, title, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')");
            $stmt->execute([$user['id'], $category, $subjectId, $section, $sy, $sem, $gradePeriod, $title, $message]);
            $success = "Ticket submitted successfully.";
        }
        elseif ($action === 'reply_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId <= 0) throw new Exception("Invalid ticket.");
            
            $replyMessage = trim($_POST['reply_message'] ?? '');
            if (empty($replyMessage)) throw new Exception("Reply message cannot be empty.");
            if (mb_strlen($replyMessage) > 1500) throw new Exception("Replies must not exceed 1,500 characters.");

            $stmtLastReply = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM feedback_messages WHERE sender_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmtLastReply->execute([$user['id']]);
            $secondsSinceLast = $stmtLastReply->fetchColumn();
            
            if ($secondsSinceLast !== false && (int)$secondsSinceLast < 30) {
                throw new Exception('Please wait 30 seconds before posting another reply.');
            }

            $stmtHourlyReplies = $db->prepare("SELECT COUNT(*) FROM feedback_messages WHERE sender_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmtHourlyReplies->execute([$user['id']]);
            if ((int) $stmtHourlyReplies->fetchColumn() >= 5) {
                throw new Exception('You have reached the hourly reply limit (5). Please try again later.');
            }
            
            $stmtCheck = $db->prepare("SELECT status FROM feedback_reports WHERE id = ? AND submitted_by = ?");
            $stmtCheck->execute([$ticketId, $user['id']]);
            $ticketStatus = $stmtCheck->fetchColumn();
            
            if (!$ticketStatus) throw new Exception("Ticket not found or unauthorized access.");
            if (in_array($ticketStatus, ['resolved', 'rejected'], true)) throw new Exception("Cannot reply to a closed ticket.");
            
            $db->beginTransaction();
            $stmtReply = $db->prepare("INSERT INTO feedback_messages (feedback_id, sender_id, message) VALUES (?, ?, ?)");
            $stmtReply->execute([$ticketId, $user['id'], $replyMessage]);
            $db->prepare("UPDATE feedback_reports SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
            $db->commit();

            $success = "Reply posted successfully.";
        }
        elseif ($action === 'acknowledge_notice') {
            $referralId = (int)($_POST['referral_id'] ?? 0);
            if ($referralId <= 0) throw new Exception("Invalid notice.");
            
            $db->beginTransaction();
            $stmtCheck = $db->prepare("
                SELECT r.id FROM support_case_referrals r 
                JOIN academic_support_cases c ON r.case_id = c.id 
                WHERE r.id = ? AND c.student_id = ? AND r.status = 'action_taken' FOR UPDATE
            ");
            $stmtCheck->execute([$referralId, $user['id']]);
            if (!$stmtCheck->fetchColumn()) {
                $db->rollBack();
                throw new Exception("This notice has already been acknowledged or is unauthorized.");
            }
            
            $db->prepare("UPDATE support_case_referrals SET status = 'acknowledged', student_acknowledged_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$referralId]);
            $db->commit();
            $success = "Receipt of academic notice successfully acknowledged.";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// 1. My Tickets (Sorted by workflow priority)
$stmtTickets = $db->prepare("
    SELECT f.*, s.code AS subj_code 
    FROM feedback_reports f 
    LEFT JOIN subjects s ON s.id = f.subject_id 
    WHERE f.submitted_by = ? 
    ORDER BY 
        CASE 
            WHEN f.status = 'open' THEN 0 
            WHEN f.status = 'faculty_review' THEN 1 
            WHEN f.status = 'awaiting_admin' THEN 2 
            WHEN f.status = 'resolved' THEN 3 
            WHEN f.status = 'rejected' THEN 4 
            ELSE 5 
        END,
        f.updated_at DESC, 
        f.id DESC
");
$stmtTickets->execute([$user['id']]);
$my_tickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

$messages_by_ticket = [];
if (!empty($my_tickets)) {
    $stmtMsgs = $db->prepare("
        SELECT fm.*, u.first_name, u.last_name, u.role 
        FROM feedback_messages fm
        JOIN users u ON fm.sender_id = u.id
        JOIN feedback_reports f ON fm.feedback_id = f.id
        WHERE f.submitted_by = ?
        ORDER BY fm.created_at ASC
    ");
    $stmtMsgs->execute([$user['id']]);
    foreach ($stmtMsgs->fetchAll(PDO::FETCH_ASSOC) as $msg) {
        $messages_by_ticket[$msg['feedback_id']][] = $msg;
    }
}

// FIXED: Now queries the specific support_case_referrals
$stmtSupport = $db->prepare("
    SELECT r.*, c.created_at AS case_date, u.first_name AS fac_fname, u.last_name AS fac_lname, s.code AS subj_code, s.title AS subj_title
    FROM support_case_referrals r
    JOIN academic_support_cases c ON r.case_id = c.id
    JOIN users u ON r.faculty_id = u.id
    JOIN subjects s ON r.subject_id = s.id
    WHERE c.student_id = ? AND r.status IN ('action_taken', 'acknowledged', 'closed')
    ORDER BY r.updated_at DESC
");
$stmtSupport->execute([$user['id']]);
$support_notices = $stmtSupport->fetchAll(PDO::FETCH_ASSOC);

$stmtSubj = $db->prepare("SELECT s.id, s.code, s.title FROM grades g JOIN subjects s ON g.subject_id = s.id WHERE g.student_id = ? AND g.is_current = 1");
$stmtSubj->execute([$user['id']]);
$my_subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Feedback & Support';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Grades & History',   'grades.php',    '📝'],
    ['Performance Trend',  'trend.php',     '📈'],
    ['Feedback & Support', 'feedback.php', '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
    .tab-btn { background: none; border: none; padding: 12px 24px; font-size: 0.95rem; font-weight: 700; color: var(--text-gray); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
    .tab-btn.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }
    .tab-content { display: none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    .action-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; white-space: nowrap; }
    .btn-resolve { background: rgba(5, 150, 105, 0.1); color: var(--risk-low); border-color: rgba(5, 150, 105, 0.2); }
    .btn-reply { background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); border-color: rgba(30, 77, 183, 0.2); font-size: 0.75rem; padding: 4px 10px; }
    .tickets-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 450px), 1fr)); gap: 24px; align-items: start; }
    .msg-thread { background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; margin-top: 12px; display: none; }
    .msg-bubble { margin-bottom: 12px; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; }
    .msg-mine { background: rgba(30, 77, 183, 0.05); border: 1px solid rgba(30, 77, 183, 0.1); border-left: 3px solid var(--accent-blue); }
    .msg-theirs { background: rgba(217, 119, 6, 0.05); border: 1px solid rgba(217, 119, 6, 0.1); border-left: 3px solid var(--risk-mod); }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Feedback & Support</h1>
            <p style="color: var(--text-gray);">Submit grade disputes and view academic support notices.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight:600;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight:600;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
        <button class="tab-btn active" onclick="switchTab(event, 'tickets')">My Tickets</button>
        <button class="tab-btn" onclick="switchTab(event, 'support')">Academic Support Notices</button>
    </div>

    <!-- TAB 1: My Tickets -->
    <div id="tab-tickets" class="tab-content active">
        <div class="tickets-grid">
            <div class="card">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 16px;">Submit New Ticket</h3>
                <form method="POST" action="feedback.php" class="safe-submit-form">
                    <input type="hidden" name="action" value="submit_ticket">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Category</label>
                        <select name="category" id="categorySelect" required onchange="toggleGradeFields()" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                            <option value="grade_concern">Grade Dispute / Inquiry</option>
                            <option value="data_issue">System / Data Issue</option>
                            <option value="general">General Inquiry</option>
                        </select>
                    </div>

                    <div id="gradeFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Subject</label>
                            <select name="subject_id" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;" required>
                                <option value="">— Select Subject —</option>
                                <?php foreach($my_subjects as $subj): ?>
                                    <option value="<?= $subj['id'] ?>"><?= htmlspecialchars($subj['code'] . ' - ' . $subj['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Grading Period</label>
                            <select name="grade_period" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;" required>
                                <option value="">— Period —</option>
                                <option value="prelim">Prelim</option>
                                <option value="midterm">Midterm</option>
                                <option value="prefinal">Pre-Final</option>
                                <option value="final_grade">Final Grade</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Title</label>
                        <input type="text" name="title" required maxlength="150" placeholder="Brief summary of concern" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Message</label>
                        <textarea name="message" required rows="4" maxlength="5000" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical; font-family: inherit;"></textarea>
                    </div>

                    <button type="submit" class="submit-btn" style="background:var(--accent-blue); color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; font-family: inherit;">Submit Ticket</button>
                </form>
            </div>

            <!-- History -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="padding: 20px 24px 12px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">My Submissions & Conversations</h3>
                </div>
                <?php if (empty($my_tickets)): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No tickets submitted yet.</p>
                <?php else: ?>
                    <div style="max-height: 600px; overflow-y: auto; padding: 12px 24px 24px;">
                        <?php foreach ($my_tickets as $h): 
                            $isClosed = in_array($h['status'], ['resolved', 'rejected'], true);
                        ?>
                            <div style="border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; overflow: hidden;">
                                <div style="padding: 16px; background: var(--bg-color);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-dark); font-size: 0.95rem;">"<?= htmlspecialchars($h['title']) ?>"</div>
                                            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">
                                                <?= date('M j, Y \a\t g:i A', strtotime($h['created_at'])) ?>
                                                <?php if ($h['subj_code']): ?>
                                                    · <span style="color: var(--accent-blue); font-weight: 600;"><?= htmlspecialchars($h['subj_code']) ?> (<?= htmlspecialchars($h['grade_period']) ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($h['status'] === 'open' || $h['status'] === 'faculty_review'): ?>
                                                <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">IN REVIEW</span>
                                            <?php elseif ($h['status'] === 'resolved'): ?>
                                                <span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">RESOLVED</span>
                                            <?php else: ?>
                                                <span style="background:var(--bg-color); border:1px solid var(--border-color); color:var(--text-gray); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($h['status']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick="toggleThread(<?= $h['id'] ?>)" class="action-btn btn-reply">💬 View Conversation</button>
                                </div>
                                
                                <div id="thread_<?= $h['id'] ?>" class="msg-thread">
                                    <div class="msg-bubble msg-mine">
                                        <strong style="color: var(--accent-blue);">You:</strong><br>
                                        <?= nl2br(htmlspecialchars($h['message'])) ?>
                                    </div>
                                    
                                    <?php if (!empty($messages_by_ticket[$h['id']])): ?>
                                        <?php foreach ($messages_by_ticket[$h['id']] as $m): 
                                            $isMe = $m['sender_id'] == $user['id'];
                                            $bClass = $isMe ? 'msg-mine' : 'msg-theirs';
                                            $sName = $isMe ? 'You' : htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' (' . ucfirst($m['role']) . ')');
                                            $sColor = $isMe ? 'var(--accent-blue)' : 'var(--risk-mod)';
                                        ?>
                                        <div class="msg-bubble <?= $bClass ?>">
                                            <strong style="color: <?= $sColor ?>;"><?= $sName ?>:</strong> 
                                            <span style="font-size: 0.7rem; color: var(--text-gray); margin-left: 8px;"><?= date('M j, g:i a', strtotime($m['created_at'])) ?></span><br>
                                            <?= nl2br(htmlspecialchars($m['message'])) ?>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isClosed): ?>
                                        <form method="POST" action="feedback.php" class="safe-submit-form" style="margin-top: 12px; display: flex; gap: 8px;">
                                            <input type="hidden" name="action" value="reply_ticket">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="ticket_id" value="<?= $h['id'] ?>">
                                            <input type="text" name="reply_message" required maxlength="1500" placeholder="Type your reply..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-dark); font-family: inherit; font-size: 0.85rem;">
                                            <button type="submit" class="action-btn btn-reply submit-btn" style="padding: 8px 16px;">Post Reply</button>
                                        </form>
                                    <?php else: ?>
                                        <p style="font-size: 0.8rem; color: var(--text-gray); margin: 12px 0 0; text-align: center;"><em>This ticket is closed and cannot receive new replies.</em></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 2: Academic Support -->
    <div id="tab-support" class="tab-content">
        <?php if (empty($support_notices)): ?>
            <div class="card"><p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">No active academic support notices.</p></div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                <?php foreach ($support_notices as $n): ?>
                    <div class="card" style="<?= !$n['student_acknowledged_at'] ? 'border: 2px solid var(--risk-mod);' : '' ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <div>
                                <h3 style="margin: 0 0 6px 0; color: var(--text-dark); font-size: 1.05rem;">
                                    Academic Notice: <span style="color: var(--accent-blue);"><?= htmlspecialchars($n['subj_code']) ?></span>
                                </h3>
                                <div style="font-size: 0.85rem; color: var(--text-gray);">
                                    <?= htmlspecialchars($n['subj_title']) ?>
                                </div>
                                <div style="margin-top: 8px; font-size: 0.85rem; color: var(--text-gray);">
                                    Issued by <strong style="color: var(--accent-blue); font-size: 0.95rem;"><?= htmlspecialchars($n['fac_fname'] . ' ' . $n['fac_lname']) ?></strong> 
                                    on <?= date('F j, Y', strtotime($n['updated_at'])) ?>
                                </div>
                            </div>
                            <?php if ($n['student_acknowledged_at']): ?>
                                <span style="background: rgba(5, 150, 105, 0.1); color: var(--risk-low); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">ACKNOWLEDGED</span>
                            <?php else: ?>
                                <span style="background: rgba(217, 119, 6, 0.1); color: var(--risk-mod); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">ACTION REQUIRED</span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 16px; border-radius: 6px; font-size: 0.9rem; color: var(--text-dark); margin-bottom: 16px;">
                            <?= nl2br(htmlspecialchars($n['message_to_student'])) ?>
                        </div>
                        
                        <?php if (!$n['student_acknowledged_at']): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(30, 77, 183, 0.05); padding: 12px; border-radius: 6px; border: 1px dashed rgba(30, 77, 183, 0.3); flex-wrap: wrap; gap: 12px;">
                                <p style="margin: 0; font-size: 0.8rem; color: var(--text-gray); flex: 1; min-width: 250px;"><em>Acknowledgment confirms receipt of this notice. It does not indicate agreement with the academic classification.</em></p>
                                <form method="POST" action="feedback.php" class="safe-submit-form" style="margin: 0;">
                                    <input type="hidden" name="action" value="acknowledge_notice">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="referral_id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="action-btn btn-resolve submit-btn">Acknowledge Receipt</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <p style="margin: 0; font-size: 0.8rem; color: var(--text-gray);"><em>Acknowledged on <?= date('F j, Y, g:i a', strtotime($n['student_acknowledged_at'])) ?></em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function switchTab(event, tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
}

function toggleGradeFields() {
    const cat = document.getElementById('categorySelect').value;
    const fields = document.getElementById('gradeFields');
    const selects = fields.querySelectorAll('select');
    
    if (cat === 'grade_concern') {
        fields.style.display = 'grid';
        selects.forEach(s => s.required = true);
    } else {
        fields.style.display = 'none';
        selects.forEach(s => {
            s.required = false;
            s.value = '';
        });
    }
}

function toggleThread(ticketId) {
    const thread = document.getElementById('thread_' + ticketId);
    if (thread.style.display === 'none' || thread.style.display === '') {
        thread.style.display = 'block';
    } else {
        thread.style.display = 'none';
    }
}

document.querySelectorAll('.safe-submit-form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('.submit-btn');
        if(btn) {
            setTimeout(() => {
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.7';
                btn.innerHTML = 'Processing...';
            }, 10);
        }
    });
});

document.addEventListener('DOMContentLoaded', toggleGradeFields);
</script>

<?php require_once '../includes/footer.php'; ?>