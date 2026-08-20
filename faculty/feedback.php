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

// =========================================================
// Handle Form Submissions
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    try {
        if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
            throw new Exception("Security validation failed. Please refresh the page and try again.");
        }

        if ($action === 'resolve_ticket') {
            $ticketId = (int)$_POST['ticket_id'];
            
            $stmtCheck = $db->prepare("
                SELECT f.category, f.status
                FROM feedback_reports f JOIN student_profiles sp ON sp.user_id = f.submitted_by JOIN faculty_class_loads fcl ON fcl.subject_id = f.subject_id AND fcl.section = sp.section
                WHERE f.id = ? AND fcl.faculty_user_id = ?
            ");
            $stmtCheck->execute([$ticketId, $user['id']]);
            $ticket = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) throw new Exception("Unauthorized: Ticket does not belong to your assigned classes.");
            if (in_array($ticket['category'], ['grade_concern', 'grade_dispute'])) throw new Exception("Grade disputes cannot be manually resolved. Propose a grade correction to route it to Administration.");

            $db->beginTransaction();
            $db->prepare("UPDATE feedback_reports SET status = 'resolved', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ticketId]);
            $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, 'resolved', 'Faculty manually resolved the query.')")->execute([$ticketId, $user['id'], $ticket['status']]);
            $db->commit();
            
            $success = "Ticket #$ticketId marked as resolved.";
        } 
        elseif ($action === 'reply_ticket') {
            $ticketId = (int)$_POST['ticket_id'];
            $replyMessage = trim($_POST['reply_message'] ?? '');
            
            if (empty($replyMessage)) throw new Exception("Reply message cannot be empty.");
            if (mb_strlen($replyMessage) > 2000) throw new Exception("Replies must not exceed 2,000 characters.");

            // RATE LIMIT: Faculty Replies
            $stmtHourly = $db->prepare("SELECT COUNT(*) FROM feedback_messages WHERE sender_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmtHourly->execute([$user['id']]);
            if ((int)$stmtHourly->fetchColumn() >= 30) throw new Exception("You have reached the hourly reply limit (30).");
            
            $stmtReply = $db->prepare("INSERT INTO feedback_messages (feedback_id, sender_id, message) VALUES (?, ?, ?)");
            $stmtReply->execute([$ticketId, $user['id'], $replyMessage]);
            $success = "Reply posted successfully.";
        }
        elseif ($action === 'issue_notice') {
            $caseId = (int)$_POST['case_id'];
            $message = trim($_POST['message_to_student']);
            
            if (empty($message)) throw new Exception("A message to the student is required.");
            
            // SECURITY & DUPLICATION: Verify ownership and ensure status is exactly needs_review
            $stmtOwner = $db->prepare("
                SELECT asc_case.status
                FROM academic_support_cases asc_case JOIN student_profiles sp ON sp.user_id = asc_case.student_id JOIN grades g ON g.student_id = asc_case.student_id AND g.is_current = 1 JOIN faculty_class_loads fcl ON fcl.subject_id = g.subject_id AND fcl.section = sp.section
                WHERE asc_case.id = ? AND fcl.faculty_user_id = ?
            ");
            $stmtOwner->execute([$caseId, $user['id']]);
            $caseStatus = $stmtOwner->fetchColumn();
            
            if ($caseStatus === false) throw new Exception("Unauthorized: Support case is not tied to your assigned class load.");
            if ($caseStatus !== 'needs_review') throw new Exception("This case has already been processed or closed.");
            
            $db->beginTransaction();
            $db->prepare("INSERT INTO support_actions (case_id, actor_id, action_type, message_to_student) VALUES (?, ?, 'academic_notice_sent', ?)")->execute([$caseId, $user['id'], $message]);
            $db->prepare("INSERT INTO support_status_history (case_id, changed_by, old_status, new_status, note) VALUES (?, ?, 'needs_review', 'action_taken', 'Faculty issued an academic notice')")->execute([$caseId, $user['id']]);
            $db->prepare("UPDATE academic_support_cases SET status = 'action_taken', assigned_faculty_id = ? WHERE id = ?")->execute([$user['id'], $caseId]);
            $db->commit();
            
            $success = "Academic support notice sent to student successfully.";
        }
        elseif ($action === 'submit_report') {
            $category  = $_POST['category'] ?? '';
            $subjectId = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
            $title     = trim($_POST['title'] ?? '');
            $message   = trim($_POST['message'] ?? '');

            // RATE LIMIT: Faculty Reports
            $stmtOpen = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND status NOT IN ('resolved', 'rejected')");
            $stmtOpen->execute([$user['id']]);
            if ((int) $stmtOpen->fetchColumn() >= 5) throw new Exception('You already have five active reports pending Admin review.');

            $stmtRecent = $db->prepare("SELECT COUNT(*) FROM feedback_reports WHERE submitted_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $stmtRecent->execute([$user['id']]);
            if ((int) $stmtRecent->fetchColumn() > 0) throw new Exception('Please wait 5 minutes before submitting another report.');

            if (!in_array($category, ['data_issue', 'general', 'other']) || empty($message) || empty($title)) {
                throw new Exception("Title, category, and message are all required.");
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

// =========================================================
// Fetch Data
// =========================================================
$stmtInbox = $db->prepare("
    SELECT f.*, s.code AS subj_code, u.first_name, u.last_name, u.user_id AS student_no, sp.section AS student_section
    FROM feedback_reports f LEFT JOIN subjects s ON s.id = f.subject_id JOIN users u ON u.id = f.submitted_by JOIN student_profiles sp ON sp.user_id = u.id JOIN faculty_class_loads fcl ON fcl.subject_id = f.subject_id AND fcl.section = sp.section
    WHERE fcl.faculty_user_id = ? AND u.role = 'student' GROUP BY f.id ORDER BY f.status ASC, f.created_at DESC
");
$stmtInbox->execute([$user['id']]);
$inbox = $stmtInbox->fetchAll(PDO::FETCH_ASSOC);

$stmtCases = $db->prepare("
    SELECT asc_case.*, u.first_name, u.last_name, u.user_id AS student_no, sp.course, sp.year_level, sp.section AS student_section
    FROM academic_support_cases asc_case JOIN users u ON u.id = asc_case.student_id JOIN student_profiles sp ON sp.user_id = u.id JOIN grades g ON g.student_id = u.id AND g.is_current = 1 JOIN faculty_class_loads fcl ON fcl.subject_id = g.subject_id AND fcl.section = sp.section
    WHERE fcl.faculty_user_id = ? GROUP BY asc_case.id ORDER BY asc_case.status ASC, asc_case.created_at DESC
");
$stmtCases->execute([$user['id']]);
$support_cases = $stmtCases->fetchAll(PDO::FETCH_ASSOC);

$stmtMyReports = $db->prepare("
    SELECT f.*, s.code AS subj_code 
    FROM feedback_reports f LEFT JOIN subjects s ON s.id = f.subject_id 
    WHERE f.submitted_by = ? ORDER BY f.created_at DESC
");
$stmtMyReports->execute([$user['id']]);
$my_submissions = $stmtMyReports->fetchAll(PDO::FETCH_ASSOC);

$messages_by_ticket = [];
$stmtMsgs = $db->prepare("SELECT fm.*, u.first_name, u.last_name, u.role FROM feedback_messages fm JOIN users u ON fm.sender_id = u.id ORDER BY fm.created_at ASC");
$stmtMsgs->execute();
foreach ($stmtMsgs->fetchAll(PDO::FETCH_ASSOC) as $msg) {
    $messages_by_ticket[$msg['feedback_id']][] = $msg;
}

$stmtSubj = $db->prepare("SELECT DISTINCT s.id, s.code, s.title FROM faculty_class_loads fcl JOIN subjects s ON s.id = fcl.subject_id WHERE fcl.faculty_user_id = ?");
$stmtSubj->execute([$user['id']]);
$my_subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Concerns, Support & Reports';
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
.tab-btn { background: none; border: none; padding: 12px 24px; font-size: 0.95rem; font-weight: 700; color: var(--text-gray); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
.tab-btn.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.action-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; white-space: nowrap; }
.btn-resolve { background: rgba(5, 150, 105, 0.1); color: var(--risk-low); border-color: rgba(5, 150, 105, 0.2); }
.btn-route { background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); border-color: rgba(30, 77, 183, 0.2); }
.btn-intervene { background: rgba(217, 119, 6, 0.1); color: var(--risk-mod); border-color: rgba(217, 119, 6, 0.2); }
.btn-reply { background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); border-color: rgba(30, 77, 183, 0.2); font-size: 0.75rem; padding: 4px 10px; }

.msg-thread { background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; margin-top: 12px; display: none; }
.msg-bubble { margin-bottom: 12px; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; }
.msg-mine { background: rgba(30, 77, 183, 0.05); border: 1px solid rgba(30, 77, 183, 0.1); border-left: 3px solid var(--accent-blue); }
.msg-theirs { background: rgba(217, 119, 6, 0.05); border: 1px solid rgba(217, 119, 6, 0.1); border-left: 3px solid var(--risk-mod); }
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
        <button class="tab-btn active" onclick="switchTab('inbox')">Student Concerns</button>
        <button class="tab-btn" onclick="switchTab('support')">Academic Support Cases</button>
        <button class="tab-btn" onclick="switchTab('reports')">My Reports to Admin</button>
    </div>

    <!-- TAB 1: Student Concerns Inbox -->
    <div id="tab-inbox" class="tab-content active">
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px;">Student Triage</h3>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Route grade disputes to rosters or resolve general queries directly.</p>
            </div>
            
            <?php if (empty($inbox)): ?>
                <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No student concerns reported for your assigned class loads.</p>
            <?php else: ?>
                <div style="padding: 12px 24px 24px;">
                    <?php foreach ($inbox as $msg): 
                        $isGradeConcern = in_array($msg['category'], ['grade_dispute', 'grade_concern']);
                        $isClosed = in_array($msg['status'], ['resolved', 'rejected']);
                    ?>
                    <div style="border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; overflow: hidden;">
                        <div style="padding: 16px; background: var(--bg-color);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 12px;">
                                <div>
                                    <div style="font-weight: 600; color: var(--text-dark); font-size: 0.95rem;">"<?= htmlspecialchars($msg['title'] ?? 'Concern') ?>"</div>
                                    <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">
                                        <?= date('M j, Y', strtotime($msg['created_at'])) ?> · 
                                        <span style="font-weight:600;"><?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?></span> 
                                        (<?= htmlspecialchars($msg['student_section'] ?? '') ?>)
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <?php if ($msg['status'] === 'open' || $msg['status'] === 'faculty_review'): ?>
                                        <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">IN REVIEW</span>
                                    <?php elseif ($msg['status'] === 'awaiting_admin'): ?>
                                        <span style="background:rgba(30, 77, 183, 0.1); color:var(--accent-blue); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">AWAITING ADMIN</span>
                                    <?php elseif ($msg['status'] === 'resolved'): ?>
                                        <span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">RESOLVED</span>
                                    <?php else: ?>
                                        <span style="background:var(--bg-color); border:1px solid var(--border-color); color:var(--text-gray); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($msg['status']) ?></span>
                                    <?php endif; ?>
                                    <button onclick="toggleThread(<?= $msg['id'] ?>)" class="action-btn btn-reply">💬 View Thread</button>
                                </div>
                            </div>
                            
                            <?php if ($msg['status'] === 'open' || $msg['status'] === 'faculty_review'): ?>
                                <div style="display:flex; justify-content: flex-end; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.05);">
                                    <?php if ($isGradeConcern && $msg['subject_id']): ?>
                                        <a href="grades.php?class=<?= $msg['subject_id'] ?>_<?= urlencode($msg['student_section'] ?? '') ?>&term=<?= urlencode($msg['grade_period'] ?? 'prelim') ?>&feedback_id=<?= $msg['id'] ?>" class="action-btn btn-route">📋 Route to Roster</a>
                                    <?php else: ?>
                                        <form method="POST" class="safe-submit-form" style="margin: 0;">
                                            <input type="hidden" name="action" value="resolve_ticket">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="ticket_id" value="<?= $msg['id'] ?>">
                                            <button type="submit" class="action-btn btn-resolve submit-btn">✔️ Mark Resolved</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="thread_<?= $msg['id'] ?>" class="msg-thread" style="margin: 0; border: none; border-top: 1px solid var(--border-color); border-radius: 0;">
                            <div class="msg-bubble msg-theirs">
                                <strong style="color: var(--risk-mod);"><?= htmlspecialchars($msg['first_name']) ?>:</strong><br>
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            </div>
                            
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
                            <?php endif; ?>
                            
                            <?php if (!$isClosed): ?>
                                <form method="POST" class="safe-submit-form" style="margin-top: 12px; display: flex; gap: 8px;">
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="ticket_id" value="<?= $msg['id'] ?>">
                                    <input type="text" name="reply_message" required maxlength="2000" placeholder="Type your reply to the student..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-dark); font-family: inherit; font-size: 0.85rem;">
                                    <button type="submit" class="action-btn btn-reply submit-btn" style="padding: 8px 16px;">Post Reply</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: Academic Support Cases -->
    <div id="tab-support" class="tab-content">
        <div class="card" style="padding:0; overflow:hidden;">
            <div style="padding: 24px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin-bottom: 4px;">Academic Support Cases</h3>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Human-in-the-Loop review for students system-flagged by performance trajectories or heuristics.</p>
            </div>
            
            <?php if (empty($support_cases)): ?>
                <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No prediction-based academic support cases currently require your review.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="background: var(--table-header-bg); text-align: left; border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px 24px; color:var(--text-dark); font-weight:600;">Triggered</th>
                                <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Student Context</th>
                                <th style="padding: 12px; color:var(--text-dark); font-weight:600;">System Snapshot</th>
                                <th style="padding: 12px; color:var(--text-dark); font-weight:600;">Status</th>
                                <th style="padding: 12px 24px; color:var(--text-dark); font-weight:600; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($support_cases as $c): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 24px; color: var(--text-gray); vertical-align: top;">
                                    <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">S.Y. <?= $c['school_year'] ?> S<?= $c['semester'] ?></div>
                                    <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                </td>
                                <td style="padding: 12px; color: var(--text-dark); vertical-align: top;">
                                    <div style="font-weight: 700;"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 2px;">
                                        <?= htmlspecialchars($c['course']) ?> (<?= htmlspecialchars($c['year_level']) ?> - <?= htmlspecialchars($c['student_section']) ?>)
                                    </div>
                                </td>
                                <td style="padding: 12px; vertical-align: top;">
                                    <span style="background: rgba(220, 38, 38, 0.1); color: var(--risk-high); padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                        <?= htmlspecialchars($c['trigger_risk_level']) ?>
                                    </span>
                                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark); margin-top: 6px;">
                                        Est. GWA: <?= number_format($c['trigger_predicted_gwa'], 2) ?>
                                    </div>
                                </td>
                                <td style="padding: 12px; vertical-align: top;">
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
                                <td style="padding: 12px 24px; vertical-align: top; text-align: right;">
                                    <?php if ($c['status'] === 'needs_review'): ?>
                                        <button onclick="openInterventionModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['first_name'] . ' ' . $c['last_name'])) ?>')" class="action-btn btn-intervene">⚠️ Issue Notice</button>
                                    <?php else: ?>
                                        <span style="color:var(--text-gray); font-size: 0.85rem;">—</span>
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

    <!-- TAB 3: My Reports to Admin -->
    <div id="tab-reports" class="tab-content">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr)); gap: 24px; align-items: start;">
            
            <!-- Submit Form -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">Contact Administration</h3>
                    <a href="grades.php" class="action-btn btn-route">📋 Propose Grade Correction</a>
                </div>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 16px;">Use the form below for general or system issues. Rate limit: 1 report every 5 minutes.</p>
                
                <form method="POST" action="feedback.php" class="safe-submit-form">
                    <input type="hidden" name="action" value="submit_report">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
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
                        <input type="text" name="title" required placeholder="Brief summary" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                    </div>

                    <div style="margin-bottom: 12px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Message</label>
                        <textarea name="message" required rows="4" style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical; font-family: inherit;"></textarea>
                    </div>

                    <button type="submit" class="submit-btn" style="background:var(--accent-blue); color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; font-family: inherit;">Send to Admin</button>
                </form>
            </div>

            <!-- Sent History -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="padding: 20px 24px 12px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0;">My Sent Reports</h3>
                </div>
                <?php if (empty($my_submissions)): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">You have not sent any reports.</p>
                <?php else: ?>
                    <div style="max-height: 500px; overflow-y: auto; padding: 12px 24px 24px;">
                        <?php foreach ($my_submissions as $h): 
                            $isClosed = in_array($h['status'], ['resolved', 'rejected']);
                        ?>
                            <div style="border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; overflow: hidden;">
                                <div style="padding: 16px; background: var(--bg-color);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-dark); font-size: 0.95rem;">"<?= htmlspecialchars($h['title']) ?>"</div>
                                            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">
                                                <?= date('M j, Y', strtotime($h['created_at'])) ?>
                                                <?php if ($h['subj_code']): ?>
                                                    · <span style="color: var(--accent-blue); font-weight: 600;"><?= htmlspecialchars($h['subj_code']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($h['status'] === 'open' || $h['status'] === 'faculty_review'): ?>
                                                <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">OPEN</span>
                                            <?php elseif ($h['status'] === 'resolved'): ?>
                                                <span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700;">RESOLVED</span>
                                            <?php else: ?>
                                                <span style="background:var(--bg-color); border:1px solid var(--border-color); color:var(--text-gray); padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($h['status']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick="toggleThread(<?= $h['id'] ?>)" class="action-btn btn-reply">💬 View Thread</button>
                                </div>
                                
                                <div id="thread_<?= $h['id'] ?>" class="msg-thread" style="margin: 0; border: none; border-top: 1px solid var(--border-color); border-radius: 0;">
                                    <div class="msg-bubble msg-mine">
                                        <strong style="color: var(--accent-blue);">You:</strong><br>
                                        <?= nl2br(htmlspecialchars($h['message'] ?? $h['initial_message'])) ?>
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
                                        <form method="POST" class="safe-submit-form" style="margin-top: 12px; display: flex; gap: 8px;">
                                            <input type="hidden" name="action" value="reply_ticket">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="ticket_id" value="<?= $h['id'] ?>">
                                            <input type="text" name="reply_message" required maxlength="2000" placeholder="Type your reply to Admin..." style="flex: 1; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-dark); font-family: inherit; font-size: 0.85rem;">
                                            <button type="submit" class="action-btn btn-reply submit-btn" style="padding: 8px 16px;">Post Reply</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Intervention Modal -->
<div id="interventionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 24px;">
        <h3 style="margin-top: 0; color: var(--text-dark);">Issue Academic Support Notice</h3>
        <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 20px;">Sending a notice to <strong id="modalStudentName" style="color: var(--accent-blue);"></strong>. This requires the student to acknowledge receipt upon their next login.</p>
        
        <form method="POST" action="feedback.php" class="safe-submit-form">
            <input type="hidden" name="action" value="issue_notice">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="case_id" id="modalCaseId">
            
            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:6px;">Message to Student</label>
            <textarea name="message_to_student" required rows="4" placeholder="e.g., Please schedule an advising session with me this week to discuss your academic trajectory." style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark); resize:vertical; font-family: inherit; margin-bottom: 16px;"></textarea>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('interventionModal').style.display='none'" style="background: none; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 6px; cursor: pointer; color: var(--text-dark); font-weight: 600;">Cancel</button>
                <button type="submit" class="submit-btn" style="background: var(--accent-blue); border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; color: white; font-weight: 600;">Send Notice</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
}
function toggleThread(ticketId) {
    const thread = document.getElementById('thread_' + ticketId);
    thread.style.display = (thread.style.display === 'none' || thread.style.display === '') ? 'block' : 'none';
}
document.querySelectorAll('.safe-submit-form').forEach(f => {
    f.addEventListener('submit', function() {
        const btn = this.querySelector('.submit-btn');
        if(btn) { setTimeout(() => { btn.style.pointerEvents = 'none'; btn.style.opacity = '0.7'; btn.innerHTML = 'Processing...'; }, 10); }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>