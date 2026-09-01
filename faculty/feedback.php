<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!checkCsrf()) {
            throw new Exception("Security validation failed. Please refresh the page and try again.");
        }

        if ($action === 'resolve_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId <= 0) throw new Exception("Invalid ticket.");
            
            $stmtCheck = $db->prepare("
                SELECT f.category, f.status
                FROM feedback_reports f 
                JOIN student_profiles sp ON sp.user_id = f.submitted_by 
                WHERE f.id = ? AND EXISTS (
                    SELECT 1 FROM faculty_class_loads fcl 
                    WHERE fcl.subject_id = f.subject_id 
                    AND fcl.section = sp.section 
                    AND fcl.faculty_user_id = ?
                )
            ");
            $stmtCheck->execute([$ticketId, $user['id']]);
            $ticket = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) throw new Exception("Unauthorized: Ticket does not belong to your assigned classes.");
            if (in_array($ticket['status'], ['resolved', 'rejected'], true)) {
                throw new Exception("This ticket has already been closed.");
            }
            
            $ticketCategory = strtolower(trim((string)$ticket['category']));
            if (in_array($ticketCategory, ['grade_concern', 'grade_dispute'], true)) {
                throw new Exception("Grade concerns cannot be manually resolved. Review the Student grade in the roster and submit a correction for Administration approval.");
            }

            $db->beginTransaction();
            $db->prepare("UPDATE feedback_reports SET status = 'resolved', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
            $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, 'resolved', 'Faculty manually resolved the query.')")->execute([$ticketId, $user['id'], $ticket['status']]);
            $db->commit();
            
            $success = "Ticket #$ticketId marked as resolved.";
        } 
        elseif ($action === 'reply_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            if ($ticketId <= 0) throw new Exception("Invalid ticket.");
            
            $replyMessage = trim($_POST['reply_message'] ?? '');
            if (empty($replyMessage)) throw new Exception("Reply message cannot be empty.");
            if (mb_strlen($replyMessage) > 2000) throw new Exception("Replies must not exceed 2,000 characters.");

            $stmtAuth = $db->prepare("
                SELECT f.status 
                FROM feedback_reports f
                LEFT JOIN student_profiles sp ON sp.user_id = f.submitted_by
                WHERE f.id = ? AND (f.submitted_by = ? OR EXISTS (
                    SELECT 1 FROM faculty_class_loads fcl 
                    WHERE fcl.subject_id = f.subject_id 
                    AND fcl.section = sp.section 
                    AND fcl.faculty_user_id = ?
                ))
            ");
            $stmtAuth->execute([$ticketId, $user['id'], $user['id']]);
            $ticketStatus = $stmtAuth->fetchColumn();

            if ($ticketStatus === false) throw new Exception("Unauthorized: You do not have access to this ticket.");
            if (in_array($ticketStatus, ['resolved', 'rejected'], true)) throw new Exception("Cannot reply to a closed ticket.");

            $stmtHourly = $db->prepare("SELECT COUNT(*) FROM feedback_messages WHERE sender_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmtHourly->execute([$user['id']]);
            if ((int)$stmtHourly->fetchColumn() >= 30) throw new Exception("You have reached the hourly reply limit (30).");
            
            $db->beginTransaction();
            $stmtReply = $db->prepare("INSERT INTO feedback_messages (feedback_id, sender_id, message) VALUES (?, ?, ?)");
            $stmtReply->execute([$ticketId, $user['id'], $replyMessage]);
            $db->prepare("UPDATE feedback_reports SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
            $db->commit();

            $success = "Reply posted successfully.";
        }
        elseif ($action === 'issue_notice') {
            $referralId = (int)($_POST['referral_id'] ?? 0);
            if ($referralId <= 0) throw new Exception("Invalid support referral.");
            
            $message = trim($_POST['message_to_student'] ?? '');
            if (empty($message)) throw new Exception("A message to the student is required.");
            
            $stmtOwner = $db->prepare("SELECT status FROM support_case_referrals WHERE id = ? AND faculty_id = ? FOR UPDATE");
            $stmtOwner->execute([$referralId, $user['id']]);
            $refStatus = $stmtOwner->fetchColumn();
            
            if ($refStatus === false) throw new Exception("Unauthorized: Support referral does not belong to you.");
            if ($refStatus !== 'needs_review') throw new Exception("This referral has already been processed.");
            
            $db->beginTransaction();
            $db->prepare("UPDATE support_case_referrals SET status = 'action_taken', message_to_student = ? WHERE id = ?")->execute([$message, $referralId]);
            $db->commit();
            
            $success = "Subject-specific academic notice sent to student successfully.";
        }
        elseif ($action === 'submit_report') {
            $category  = $_POST['category'] ?? '';
            $subjectId = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
            $title     = trim($_POST['title'] ?? '');
            $message   = trim($_POST['message'] ?? '');

            $stmtOpen = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND status NOT IN ('resolved', 'rejected')");
            $stmtOpen->execute([$user['id']]);
            if ((int) $stmtOpen->fetchColumn() >= 5) throw new Exception('You already have five active reports pending Admin review.');

            $stmtRecent = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $stmtRecent->execute([$user['id']]);
            if ((int) $stmtRecent->fetchColumn() > 0) throw new Exception('Please wait 5 minutes before submitting another report.');

            if (!in_array($category, ['data_issue', 'general', 'other'], true) || empty($message) || empty($title)) {
                throw new Exception("Title, category, and message are all required.");
            }
            if (mb_strlen($title) > 150) throw new Exception("The report title must not exceed 150 characters.");
            if (mb_strlen($message) > 5000) throw new Exception("The report message must not exceed 5,000 characters.");

            if ($subjectId) {
                $stmtCheck = $db->prepare("SELECT id FROM faculty_class_loads WHERE faculty_user_id = ? AND subject_id = ?");
                $stmtCheck->execute([$user['id'], $subjectId]);
                if (!$stmtCheck->fetchColumn()) {
                    throw new Exception("Unauthorized: You do not teach the selected subject.");
                }
            }

            $stmt = $db->prepare("INSERT INTO feedback_reports (submitted_by, category, subject_id, title, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $category, $subjectId, $title, $message]);
            $success = "Report submitted securely to Administration.";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

$stmtInbox = $db->prepare("
    SELECT f.*, s.code AS subj_code, s.title AS subj_title, u.first_name, u.last_name, u.user_id AS student_no, sp.section AS student_section
    FROM feedback_reports f 
    LEFT JOIN subjects s ON s.id = f.subject_id 
    JOIN users u ON u.id = f.submitted_by AND u.role = 'student'
    JOIN student_profiles sp ON sp.user_id = u.id 
    WHERE EXISTS (
        SELECT 1 FROM faculty_class_loads fcl 
        WHERE fcl.subject_id = f.subject_id 
        AND fcl.section = sp.section 
        AND fcl.faculty_user_id = ?
    )
    ORDER BY 
        CASE 
            WHEN f.status = 'open' THEN 0 
            WHEN f.status = 'faculty_review' THEN 1 
            WHEN f.status = 'awaiting_admin' THEN 2 
            WHEN f.status = 'resolved' THEN 3 
            WHEN f.status = 'rejected' THEN 4 
            ELSE 5 
        END,
        COALESCE(f.updated_at, f.created_at) DESC, 
        f.id DESC
");
$stmtInbox->execute([$user['id']]);
$inbox = $stmtInbox->fetchAll(PDO::FETCH_ASSOC);

// FIXED: Now queries child referrals, displaying subject context first
$stmtCases = $db->prepare("
    SELECT r.*, c.trigger_risk_level AS program_risk, c.school_year, c.semester,
           u.first_name, u.last_name, u.user_id AS student_no,
           s.code AS subj_code, s.title AS subj_title
    FROM support_case_referrals r
    JOIN academic_support_cases c ON r.case_id = c.id
    JOIN users u ON c.student_id = u.id
    JOIN subjects s ON r.subject_id = s.id
    WHERE r.faculty_id = ?
    ORDER BY 
        CASE WHEN r.status = 'needs_review' THEN 0 WHEN r.status = 'action_taken' THEN 1 ELSE 2 END,
        r.created_at DESC
");
$stmtCases->execute([$user['id']]);
$support_cases = $stmtCases->fetchAll(PDO::FETCH_ASSOC);
// $c['section'] used at display time below comes from r.* (support_case_referrals.section
// is already an own column, selected via the r.* wildcard above — a snapshot of the
// section at the time this referral was created, which is preferred over a live join
// to student_profiles since a student may have since moved sections).

$stmtMyReports = $db->prepare("
    SELECT f.*, s.code AS subj_code 
    FROM feedback_reports f LEFT JOIN subjects s ON s.id = f.subject_id 
    WHERE f.submitted_by = ? ORDER BY f.created_at DESC
");
$stmtMyReports->execute([$user['id']]);
$my_submissions = $stmtMyReports->fetchAll(PDO::FETCH_ASSOC);

$accessibleTicketIds = [];
foreach ($inbox as $msg) $accessibleTicketIds[] = $msg['id'];
foreach ($my_submissions as $msg) $accessibleTicketIds[] = $msg['id'];
$accessibleTicketIds = array_values(array_unique($accessibleTicketIds));

$messages_by_ticket = [];
if (!empty($accessibleTicketIds)) {
    $inClause = implode(',', array_fill(0, count($accessibleTicketIds), '?'));
    $stmtMsgs = $db->prepare("
        SELECT fm.*, u.first_name, u.last_name, u.role 
        FROM feedback_messages fm 
        JOIN users u ON fm.sender_id = u.id 
        WHERE fm.feedback_id IN ($inClause)
        ORDER BY fm.created_at ASC
    ");
    $stmtMsgs->execute($accessibleTicketIds);
    foreach ($stmtMsgs->fetchAll(PDO::FETCH_ASSOC) as $msg) {
        $messages_by_ticket[$msg['feedback_id']][] = $msg;
    }
}

$stmtSubj = $db->prepare("SELECT DISTINCT s.id, s.code, s.title FROM faculty_class_loads fcl JOIN subjects s ON s.id = fcl.subject_id WHERE fcl.faculty_user_id = ?");
$stmtSubj->execute([$user['id']]);
$my_subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Concerns & Reports';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Encode Grades',      'grades.php',    '📝'],
    ['Concerns & Reports', 'feedback.php',  '💬'], 
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.tab-btn { background: none; border: none; padding: 12px 24px; font-size: 0.95rem; font-weight: 700; color: var(--text-gray); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; font-family: inherit;}
.tab-btn.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* --- UNIFIED CARD COMPONENT (Content Rail Design) --- */
.status-card { border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; background: linear-gradient(135deg, rgba(30, 77, 183, 0.03), transparent); overflow: hidden; display: flex; flex-direction: column; }
[data-theme="dark"] .status-card { background: linear-gradient(135deg, rgba(30, 77, 183, 0.1), rgba(15, 23, 42, 0.4)); }
.status-card-header { padding: 16px 20px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
.status-card-body { padding: 16px 20px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.015); font-size: 0.9rem; color: var(--text-dark); }
[data-theme="dark"] .status-card-body { background: rgba(255,255,255,0.02); }
.status-card-footer { padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; font-size: 0.85rem; color: var(--text-gray); background: transparent; }

/* Left Accent Stripes */
.strip-blue { border-left: 4px solid var(--accent-blue); }
.strip-amber { border-left: 4px solid var(--risk-mod); }
.strip-red { border-left: 4px solid var(--risk-high); }
.strip-gray { border-left: 4px solid var(--text-gray); }
.strip-green { border-left: 4px solid var(--risk-low); }

/* Compact Pills */
.purpose-pill { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
.pill-blue { background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); }
.pill-amber { background: rgba(217, 119, 6, 0.1); color: var(--risk-mod); }
.pill-red { background: rgba(220, 38, 38, 0.1); color: var(--risk-high); }
.pill-gray { background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-gray); }
.pill-green { background: rgba(5, 150, 105, 0.1); color: var(--risk-low); }

/* Buttons */
.btn-primary { background: var(--accent-blue); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; font-family: inherit; transition: opacity 0.2s; text-decoration: none;}
.btn-primary:hover { opacity: 0.9; }
.btn-success { background: rgba(5, 150, 105, 0.1); color: var(--risk-low); border: 1px solid rgba(5, 150, 105, 0.3); padding: 8px 16px; border-radius: 6px; font-weight: 600; font-family: inherit; cursor: pointer; transition: all 0.2s; }
.btn-success:hover { background: rgba(5, 150, 105, 0.2); }
.btn-danger-outline { background: transparent; color: var(--risk-high); border: 1px solid rgba(220, 38, 38, 0.3); padding: 8px 16px; border-radius: 6px; font-weight: 600; font-family: inherit; cursor: pointer; transition: all 0.2s; }
.btn-danger-outline:hover { background: rgba(220, 38, 38, 0.1); }
.btn-secondary { background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.75rem; font-family: inherit; transition: opacity 0.2s; }

/* Thread */
.msg-thread { background: var(--bg-color); padding: 16px 20px; display: none; }
.msg-bubble { margin-bottom: 12px; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; }
.msg-mine { background: rgba(30, 77, 183, 0.05); border: 1px solid rgba(30, 77, 183, 0.1); border-left: 3px solid var(--accent-blue); }
.msg-theirs { background: rgba(217, 119, 6, 0.05); border: 1px solid rgba(217, 119, 6, 0.1); border-left: 3px solid var(--risk-mod); }

.history-separator { margin: 32px 0 16px 0; padding-bottom: 8px; border-bottom: 2px solid var(--border-color); color: var(--text-dark); font-size: 1.05rem; font-weight: 700; }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Concerns, Support & Reports</h1>
            <p style="color: var(--text-gray);">Manage student concerns, issue academic support notices, and communicate with Administration.</p>
        </div>
    </div>

    <?php if ($error): ?><p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight:600;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight:600;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
        <button id="btn-inbox" class="tab-btn active" onclick="switchTab('inbox')">Student Concerns</button>
        <button id="btn-support" class="tab-btn" onclick="switchTab('support')">Academic Support Referrals</button>
        <button id="btn-reports" class="tab-btn" onclick="switchTab('reports')">My Reports to Admin</button>
    </div>

    <!-- TAB 1: Student Concerns Inbox -->
    <div id="tab-inbox" class="tab-content active">
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px;">Student Triage</h3>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Review grade concerns in class rosters, or manually resolve general queries directly.</p>
            </div>
            
            <div style="max-height: 600px; overflow-y: auto; padding: 16px 24px;">
                <?php if (empty($inbox)): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">No student concerns reported for your assigned class loads.</p>
                <?php else: ?>
                    <?php 
                    $hasShownClosedHeader = false;
                    foreach ($inbox as $msg): 
                        $category = strtolower(trim((string)($msg['category'] ?? '')));
                        $isGradeConcern = in_array($category, ['grade_concern', 'grade_dispute'], true);
                        $canViewRoster = !empty($msg['subject_id']);
                        $isClosed = in_array($msg['status'], ['resolved', 'rejected'], true);

                        if ($isClosed && !$hasShownClosedHeader) {
                            $hasShownClosedHeader = true;
                            echo '<div class="history-separator">Closed & Resolved History</div>';
                        }
                        
                        $stripClass = $isGradeConcern ? 'strip-red' : 'strip-blue';
                    ?>
                    
                    <div class="status-card <?= $stripClass ?>">
                        <div class="status-card-header">
                            <div>
                                <strong style="color:var(--text-dark); font-size: 1.05rem;">"<?= htmlspecialchars($msg['title'] ?? 'Concern') ?>"</strong><br>
                                <span style="font-size: 0.85rem; color: var(--text-gray);">
                                    <strong style="color:var(--text-dark);"><?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?></strong> 
                                    (<?= htmlspecialchars($msg['student_no']) ?> · <?= htmlspecialchars($msg['student_section'] ?? '') ?>)
                                </span>
                            </div>
                            <div style="text-align: right; font-size: 0.85rem; color: var(--text-gray);">
                                <?= date('M j, Y \a\t g:i A', strtotime($msg['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="status-card-body">
                            <?php if (!empty($msg['subj_code'])): ?>
                                <div style="font-size: 0.8rem; margin-bottom: 8px;">
                                    <span style="font-weight: 600; color: var(--accent-blue);"><?= htmlspecialchars($msg['subj_code']) ?></span> 
                                    <span style="color: var(--text-gray);">· <?= htmlspecialchars($msg['subj_title']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div style="color: var(--text-dark);">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            </div>
                        </div>
                        
                        <div class="status-card-footer">
                            <div style="display:flex; gap: 8px;">
                                <?php if ($isGradeConcern): ?>
                                    <span class="purpose-pill pill-red">GRADE CONCERN</span>
                                <?php else: ?>
                                    <span class="purpose-pill pill-gray">GENERAL QUERY</span>
                                <?php endif; ?>

                                <?php if ($msg['status'] === 'open' || $msg['status'] === 'faculty_review'): ?>
                                    <span class="purpose-pill pill-amber">IN REVIEW</span>
                                <?php elseif ($msg['status'] === 'awaiting_admin'): ?>
                                    <span class="purpose-pill pill-blue">AWAITING ADMIN</span>
                                <?php elseif ($msg['status'] === 'resolved'): ?>
                                    <span class="purpose-pill pill-green">RESOLVED</span>
                                <?php else: ?>
                                    <span class="purpose-pill pill-gray"><?= htmlspecialchars(strtoupper($msg['status'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; gap: 8px; align-items: center;">
                                <button onclick="toggleThread(<?= $msg['id'] ?>)" class="btn-primary" style="background: transparent; color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.3);">💬 View Thread</button>
                                
                                <?php if (!$isClosed): ?>
                                    <!-- Primary Actions First -->
                                    <?php if ($canViewRoster): ?>
                                        <a href="grades.php?feedback_id=<?= (int)$msg['id'] ?>" class="btn-primary">
                                            📋 <?= $isGradeConcern ? 'Review Grade in Roster' : 'View Roster' ?>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Secondary/Manual Resolution Last -->
                                    <?php if (!$isGradeConcern): ?>
                                        <form method="POST" class="safe-submit-form" style="margin: 0;">
                                            <input type="hidden" name="action" value="resolve_ticket">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="ticket_id" value="<?= (int)$msg['id'] ?>">
                                            <button type="submit" class="btn-success">✔️ Mark Resolved</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div id="thread_<?= $msg['id'] ?>" class="msg-thread">
                            <?php if (!empty($messages_by_ticket[$msg['id']])): ?>
                                <?php foreach ($messages_by_ticket[$msg['id']] as $m): 
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
                            <?php else: ?>
                                <p style="font-size: 0.85rem; color: var(--text-gray); text-align:center;">No replies yet.</p>
                            <?php endif; ?>
                            
                            <?php if (!$isClosed): ?>
                                <form method="POST" class="safe-submit-form" style="margin-top: 12px; display: flex; gap: 8px;">
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="ticket_id" value="<?= (int)$msg['id'] ?>">
                                    <input type="text" name="reply_message" required maxlength="2000" placeholder="Type your reply to the student..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-dark); font-family: inherit; font-size: 0.85rem;">
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">Post Reply</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 2: Academic Support Referrals -->
    <div id="tab-support" class="tab-content">
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px;">Subject-Level Referrals</h3>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">These students have an active program-level risk. Please provide an intervention specifically for your assigned subject.</p>
            </div>
            
            <div style="max-height: 600px; overflow-y: auto; padding: 0;">
                <?php if (empty($support_cases)): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px; margin: 0;">No active subject referrals require your attention.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 10px 24px; text-align: left; color:var(--text-dark); font-weight:600;">Student Context</th>
                                <th style="padding: 10px 24px; text-align: left; color:var(--text-dark); font-weight:600; width: 25%;">Your Subject</th>
                                <th style="padding: 10px 24px; text-align: left; color:var(--text-dark); font-weight:600;">Subject Snapshot</th>
                                <th style="padding: 10px 24px; text-align: left; color:var(--text-dark); font-weight:600;">Status</th>
                                <th style="padding: 10px 24px; text-align: center; color:var(--text-dark); font-weight:600; width: 15%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($support_cases as $c): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 24px; vertical-align: middle;">
                                    <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 4px; font-size: 1.05rem;"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                    <div style="font-size: 0.85rem; color: var(--text-gray); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span><span style="font-family: monospace; color: var(--text-dark);"><?= htmlspecialchars($c['student_no']) ?></span> • <?= htmlspecialchars($c['section']) ?></span>
                                        <span class="purpose-pill <?= ($c['program_risk'] === 'HIGH' ? 'pill-red' : 'pill-amber') ?>" style="font-size: 0.65rem; padding: 2px 6px;">
                                            PROGRAM RISK: <?= strtoupper(htmlspecialchars($c['program_risk'])) ?>
                                        </span>
                                    </div>
                                </td>
                                
                                <td style="padding: 10px 24px; vertical-align: middle;">
                                    <div style="font-weight: 600; color: var(--accent-blue); margin-bottom: 2px;"><?= htmlspecialchars($c['subj_code']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= htmlspecialchars($c['subj_title']) ?>
                                    </div>
                                </td>
                                
                                <td style="padding: 10px 24px; vertical-align: middle;">
                                    <div style="margin-bottom: 2px; font-size: 0.85rem; color: var(--text-gray);">
                                        Subject Risk: <strong style="color: var(--risk-high);"><?= strtoupper(htmlspecialchars($c['subject_risk_level'])) ?></strong>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-gray);">
                                        Score: <strong style="color: var(--text-dark);"><?= number_format($c['latest_term_grade'], 0) ?>%</strong> 
                                        <span style="font-size: 0.75rem;">(<?= ucfirst($c['latest_term_checked']) ?>)</span>
                                    </div>
                                </td>
                                
                                <td style="padding: 10px 24px; vertical-align: middle;">
                                    <?php if ($c['status'] === 'needs_review'): ?>
                                        <span style="background: rgba(217, 119, 6, 0.1); color: var(--risk-mod); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">NEEDS REVIEW</span>
                                    <?php elseif ($c['status'] === 'action_taken'): ?>
                                        <span style="background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">NOTICE SENT</span>
                                    <?php elseif ($c['status'] === 'acknowledged'): ?>
                                        <span style="background: rgba(5, 150, 105, 0.1); color: var(--risk-low); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">ACKNOWLEDGED</span>
                                    <?php else: ?>
                                        <span style="background: var(--bg-color); color: var(--text-gray); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">CLOSED</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="padding: 10px 24px; vertical-align: middle; text-align: center;">
                                    <?php if ($c['status'] === 'needs_review'): ?>
                                        <button onclick="openInterventionModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['first_name'] . ' ' . $c['last_name'])) ?>', '<?= htmlspecialchars(addslashes($c['section'])) ?>', '<?= htmlspecialchars(addslashes($c['subj_title'])) ?>')" style="background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.3); padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.75rem; font-family: inherit; transition: all 0.2s; white-space: nowrap;">
                                            Review & Issue
                                        </button>
                                    <?php else: ?>
                                        <button onclick="openViewNoticeModal(<?= htmlspecialchars(json_encode($c['message_to_student'] ?? 'No message found.')) ?>)" class="btn-secondary" style="white-space: nowrap;">View Notice</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 3: My Reports to Admin -->
    <div id="tab-reports" class="tab-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr)); gap: 24px; align-items: start;">
            
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">Contact Administration</h3>
                    <a href="grades.php" class="btn-primary" style="background: transparent; color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.3);">📋 Propose Grade Correction</a>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 16px;">Use the form below for general or system issues. Rate limit: 1 report every 5 minutes.</p>
                
                <form method="POST" action="feedback.php?tab=reports" class="safe-submit-form">
                    <input type="hidden" name="action" value="submit_report">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Category</label>
                        <select name="category" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                            <option value="">— Select Category —</option>
                            <option value="data_issue">System / Data Issue</option>
                            <option value="general">Class Load / Assignment Problem</option>
                            <option value="other">General Inquiry</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Subject (Optional)</label>
                        <select name="subject_id" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                            <option value="">— Not specific to a subject —</option>
                            <?php foreach($my_subjects as $subj): ?>
                                <option value="<?= $subj['id'] ?>"><?= htmlspecialchars($subj['code'] . ' - ' . $subj['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Title</label>
                        <input type="text" name="title" required maxlength="150" placeholder="Brief summary" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Message</label>
                        <textarea name="message" required rows="4" maxlength="5000" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical; font-family: inherit;"></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%;">Send to Admin</button>
                </form>
            </div>

            <div class="card" style="padding:0; overflow:hidden;">
                <div style="padding: 20px 24px 12px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">My Sent Reports</h3>
                </div>
                <div style="max-height: 500px; overflow-y: auto; padding: 16px 24px;">
                    <?php if (empty($my_submissions)): ?>
                        <p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">You have not sent any reports.</p>
                    <?php else: ?>
                        <?php foreach ($my_submissions as $h): 
                            $isClosed = in_array($h['status'], ['resolved', 'rejected'], true);
                            $stripClass = $isClosed ? 'strip-gray' : 'strip-blue';
                        ?>
                            <div class="status-card <?= $stripClass ?>">
                                <div class="status-card-header">
                                    <div>
                                        <strong style="color:var(--text-dark); font-size: 1.05rem;">"<?= htmlspecialchars($h['title']) ?>"</strong><br>
                                        <span style="font-size: 0.85rem; color: var(--text-gray);"><?= date('M j, Y \a\t g:i A', strtotime($h['created_at'])) ?></span>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if ($h['status'] === 'open' || $h['status'] === 'faculty_review'): ?>
                                            <span class="purpose-pill pill-amber">OPEN</span>
                                        <?php elseif ($h['status'] === 'resolved'): ?>
                                            <span class="purpose-pill pill-green">RESOLVED</span>
                                        <?php else: ?>
                                            <span class="purpose-pill pill-gray"><?= htmlspecialchars(strtoupper($h['status'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="status-card-body">
                                    <?php if ($h['subj_code']): ?>
                                        <span style="color: var(--accent-blue); font-weight: 600; font-size: 0.85rem; display:block; margin-bottom: 8px;"><?= htmlspecialchars($h['subj_code']) ?></span>
                                    <?php endif; ?>
                                    <div style="color: var(--text-dark);">
                                        <?= nl2br(htmlspecialchars($h['message'] ?? $h['initial_message'])) ?>
                                    </div>
                                </div>
                                <div class="status-card-footer">
                                    <div><button onclick="toggleThread(<?= $h['id'] ?>)" class="btn-primary" style="background: transparent; color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.3);">💬 View Thread</button></div>
                                </div>
                                
                                <div id="thread_<?= $h['id'] ?>" class="msg-thread">
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
                                    <?php else: ?>
                                        <p style="font-size: 0.85rem; color: var(--text-gray); text-align:center;">No replies yet.</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isClosed): ?>
                                        <form method="POST" class="safe-submit-form" style="margin-top: 12px; display: flex; gap: 8px;">
                                            <input type="hidden" name="action" value="reply_ticket">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="ticket_id" value="<?= $h['id'] ?>">
                                            <input type="text" name="reply_message" required maxlength="2000" placeholder="Type your reply to Admin..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-dark); font-family: inherit; font-size: 0.85rem;">
                                            <button type="submit" class="btn-primary" style="padding: 8px 16px;">Post Reply</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Issue Notice -->
<div id="interventionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 500px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; color: var(--text-dark);">Issue Subject Notice</h3>
        <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 20px;">Sending a notice to <strong id="modalStudentName" style="color: var(--accent-blue);"></strong> of <strong id="modalSection" style="color: var(--text-dark);"></strong> regarding <strong id="modalSubjTitle" style="color: var(--text-dark);"></strong>. This requires the student to acknowledge receipt.</p>
        
        <form method="POST" action="feedback.php?tab=support" class="safe-submit-form">
            <input type="hidden" name="action" value="issue_notice">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <!-- FIXED: name was "case_id" but the backend (issue_notice, above)
                 reads $_POST['referral_id']. That mismatch meant every
                 submission arrived with no referral_id at all, so the
                 handler's $referralId <= 0 check failed every single time —
                 "Invalid support referral" regardless of what was actually
                 selected. id kept as modalCaseId to avoid renaming the JS
                 function's DOM lookup; only the submitted field name changed. -->
            <input type="hidden" name="referral_id" id="modalCaseId">
            
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:6px;">Message to Student</label>
            <textarea name="message_to_student" required rows="4" placeholder="e.g., Please schedule an advising session with me this week to discuss your trajectory in this class." style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical; font-family: inherit; margin-bottom: 16px;"></textarea>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('interventionModal').style.display='none'" class="btn-danger-outline" style="border-color: transparent; color: var(--text-dark);">Cancel</button>
                <button type="submit" class="btn-primary">Send Notice</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Sent Notice -->
<div id="viewNoticeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 500px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; color: var(--text-dark);">Issued Notice Details</h3>
        <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 20px;">This is the exact message you transmitted to the student.</p>
        <div id="viewNoticeText" style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 16px; border-radius: 6px; font-size: 0.9rem; color: var(--text-dark); margin-bottom: 16px; white-space: pre-wrap; line-height: 1.4;"></div>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="document.getElementById('viewNoticeModal').style.display='none'" class="btn-primary">Close</button>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    const targetContent = document.getElementById('tab-' + tabId);
    const activeBtn = document.getElementById('btn-' + tabId);
    
    if (targetContent) targetContent.classList.add('active');
    if (activeBtn) activeBtn.classList.add('active');

    // Keep the URL in sync with the visible tab. Without this, a form with
    // no explicit action="" (there are a few on this page) posts back to
    // whatever URL the page happened to load with, which reset to the
    // default "inbox" tab after every submission on this page regardless of
    // which tab the user was actually working in — not just on errors.
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const requestedTab = params.get('tab');
    const allowedTabs = ['inbox', 'support', 'reports'];

    if (allowedTabs.includes(requestedTab)) {
        switchTab(requestedTab);
    }
});

function openInterventionModal(caseId, studentName, section, subjTitle) {
    document.getElementById('modalCaseId').value = caseId;
    document.getElementById('modalStudentName').innerText = studentName;
    document.getElementById('modalSection').innerText = section;
    document.getElementById('modalSubjTitle').innerText = subjTitle;
    document.getElementById('interventionModal').style.display = 'flex';
}

function openViewNoticeModal(message) {
    document.getElementById('viewNoticeText').textContent = message;
    document.getElementById('viewNoticeModal').style.display = 'flex';
}

function toggleThread(ticketId) {
    const thread = document.getElementById('thread_' + ticketId);
    thread.style.display = (thread.style.display === 'none' || thread.style.display === '') ? 'block' : 'none';
}

document.querySelectorAll('.safe-submit-form').forEach(f => {
    f.addEventListener('submit', function() {
        const btns = this.querySelectorAll('button[type="submit"]');
        if(btns) { setTimeout(() => { btns.style.pointerEvents = 'none'; btns.style.opacity = '0.7'; btns.innerHTML = 'Processing...'; }, 10); }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>