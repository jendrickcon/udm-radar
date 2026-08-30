<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$error = ''; $success = '';

// --- ADMIN VALIDATION HELPER ---
function validateAdminGrade($termType, $val) {
    if ($val === null || trim((string)$val) === '') return true;
    $valStr = strtoupper(trim((string)$val));
    if (in_array($termType, ['prelim', 'midterm', 'prefinal'])) {
        if (is_numeric($val)) { $f = (float)$val; if ($f >= 0 && $f <= 100) return true; }
        if (in_array($valStr, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD'])) return true;
        return false;
    } elseif ($termType === 'final_grade') {
        if (in_array($valStr, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD', '0', '0.00'])) return true;
        if (is_numeric($val)) {
            $f = (float)$val; $formatted = number_format($f, 2);
            $validPoints = ['4.00','3.75','3.50','3.25','3.00','2.75','2.50','2.25','2.00','1.75','1.50','1.25','1.00'];
            if (in_array($formatted, $validPoints, true)) return true;
        }
        return false;
    }
    return false;
}

function recalculateGradeRowRisk($db, $gradeId) {
    $row = $db->query("SELECT prelim, midterm, prefinal, final_grade FROM grades WHERE id = " . (int)$gradeId)->fetch();
    if (!$row) return null;
    $latestVal = null; $latestType = '';
    if ($row['final_grade'] !== null && trim((string)$row['final_grade']) !== '') { $latestVal = $row['final_grade']; $latestType = 'final_grade'; }
    elseif ($row['prefinal'] !== null && trim((string)$row['prefinal']) !== '') { $latestVal = $row['prefinal']; $latestType = 'prefinal'; }
    elseif ($row['midterm'] !== null && trim((string)$row['midterm']) !== '') { $latestVal = $row['midterm']; $latestType = 'midterm'; }
    elseif ($row['prelim'] !== null && trim((string)$row['prelim']) !== '') { $latestVal = $row['prelim']; $latestType = 'prelim'; }

    if ($latestVal !== null) {
        $valStr = strtoupper(trim((string)$latestVal));
        if ($latestType === 'final_grade') {
            if (in_array($valStr, ['INC', 'DO', 'DU', 'FA', 'UD'])) return 'HIGH';
            if (is_numeric($latestVal)) return computeRiskFromAvg((float)$latestVal);
        } else {
            if (is_numeric($latestVal)) {
                $pt = normalizeTermGrade((float)$latestVal);
                if ($pt !== null) return computeRiskFromAvg($pt);
            }
        }
    }
    return null;
}

function recalculateStudentGWA($db, $studentId) {
    $stmt = $db->prepare("SELECT final_grade FROM grades WHERE student_id = ? AND final_grade IS NOT NULL AND final_grade != ''");
    $stmt->execute([$studentId]);
    $grades = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $sum = 0; $count = 0;
    foreach ($grades as $g) {
        $valStr = strtoupper(trim((string)$g));
        if (in_array($valStr, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD'])) continue;
        if (is_numeric($g)) { $sum += (float)$g; $count++; }
    }
    $gwa = $count > 0 ? round($sum / $count, 2) : null;
    $db->prepare("UPDATE student_profiles SET current_gwa = ? WHERE user_id = ?")->execute([$gwa, $studentId]);
}
// ------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) { $error = 'Session expired — please refresh the page and try again.'; } 
    else {
        $action = $_POST['action'] ?? '';
        
        if (in_array($action, ['resolve_feedback', 'reject_feedback'])) {
            $fid = (int)$_POST['feedback_id'];
            $newStatus = ($action === 'resolve_feedback') ? 'resolved' : 'rejected';
            
            $db->beginTransaction();
            try {
                $stmtOld = $db->prepare("SELECT status FROM feedback_reports WHERE id = ?");
                $stmtOld->execute([$fid]);
                $oldStatus = $stmtOld->fetchColumn() ?: 'open';
                
                $db->prepare("UPDATE feedback_reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?")->execute([$newStatus, $user['id'], $fid]);
                $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)")->execute([$fid, $user['id'], $oldStatus, $newStatus, "Admin $newStatus the report."]);
                $db->commit();
                $success = $newStatus === 'resolved' ? 'Report marked as resolved.' : 'Report rejected.';
            } catch (Exception $e) { $db->rollBack(); $error = "Failed to update report: " . $e->getMessage(); }
        }
        elseif (in_array($action, ['confirm_correction', 'reject_correction'])) {
            $corrId = (int)$_POST['correction_id'];
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT * FROM pending_corrections WHERE id = ? AND status = 'pending' FOR UPDATE");
                $stmt->execute([$corrId]);
                $corr = $stmt->fetch();
                if (!$corr) throw new Exception('Correction not found or already processed.');
                
                if ($action === 'reject_correction') {
                    $db->prepare("UPDATE pending_corrections SET status='rejected', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $corrId]);
                    if (!empty($corr['feedback_id'])) {
                        $stmtOld = $db->prepare("SELECT status FROM feedback_reports WHERE id = ?"); $stmtOld->execute([$corr['feedback_id']]); $oldStatus = $stmtOld->fetchColumn() ?: 'awaiting_admin';
                        $db->prepare("UPDATE feedback_reports SET status = 'faculty_review' WHERE id = ?")->execute([$corr['feedback_id']]);
                        $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, 'faculty_review', 'Admin rejected grade correction.')")->execute([$corr['feedback_id'], $user['id'], $oldStatus]);
                    }
                    $success = 'Correction rejected — no change applied.';
                } else {
                    $column = $corr['field_changed']; $allowedTerms = ['prelim', 'midterm', 'prefinal', 'final_grade']; $logTargetId = $corr['target_id'];
                    if (in_array($corr['target_type'], ['student_section', 'student_year_level'])) {
                        $db->prepare("UPDATE student_profiles SET `$column` = ? WHERE user_id = ?")->execute([$corr['new_value'], $corr['target_id']]);
                    } elseif ($corr['target_type'] === 'grade') {
                        if (!in_array($column, $allowedTerms)) throw new Exception("Invalid grading period.");
                        if (!validateAdminGrade($column, $corr['new_value'])) throw new Exception("Validation Error.");
                        $db->prepare("UPDATE grades SET `$column` = ? WHERE id = ?")->execute([$corr['new_value'], $corr['target_id']]);
                        $logTargetId = $db->query("SELECT student_id FROM grades WHERE id = " . (int)$corr['target_id'])->fetchColumn() ?: $corr['target_id'];
                    }
                    $db->prepare("UPDATE pending_corrections SET status='confirmed', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $corrId]);
                    $db->prepare("INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value, note) VALUES (?, 'student', ?, ?, ?, ?, ?)")->execute([$user['id'], $logTargetId, $column, $corr['old_value'], $corr['new_value'], 'Confirmed correction']);
                    if (!empty($corr['feedback_id'])) {
                        $stmtOld = $db->prepare("SELECT status FROM feedback_reports WHERE id = ?"); $stmtOld->execute([$corr['feedback_id']]); $oldStatus = $stmtOld->fetchColumn() ?: 'awaiting_admin';
                        $db->prepare("UPDATE feedback_reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?")->execute([$user['id'], $corr['feedback_id']]);
                        $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, 'resolved', 'Admin confirmed grade correction.')")->execute([$corr['feedback_id'], $user['id'], $oldStatus]);
                    }
                    $success = 'Correction confirmed.';
                }
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); $error = 'Action failed: ' . $e->getMessage(); }
        }
        elseif ($action === 'approve_batch' || $action === 'reject_batch') {
            $batchId = (int)$_POST['batch_id'];
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT * FROM pending_grade_batches WHERE id = ? AND status = 'pending' FOR UPDATE");
                $stmt->execute([$batchId]);
                $batch = $stmt->fetch();
                if (!$batch) throw new Exception('Grade batch not found or already processed.');
                
                if ($action === 'reject_batch') {
                    $db->prepare("UPDATE pending_grade_batches SET status='rejected', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $batchId]);
                    $success = 'Grade batch rejected.';
                } else {
                    $approvedGrades = $_POST['approved_grades'] ?? []; 
                    $payload = json_decode($batch['payload'], true); 
                    $termType = $batch['term_type'];
                    $allowedTerms = ['prelim', 'midterm', 'prefinal', 'final_grade'];
                    
                    if (!in_array($termType, $allowedTerms)) throw new Exception("Invalid batch grading period.");
                    
                    $getGradeStmt = $db->prepare("SELECT id, `$termType` FROM grades WHERE student_id = ? AND subject_id = ? AND is_current = 1");
                    $updateGradeStmt = $db->prepare("UPDATE grades SET `$termType` = ? WHERE id = ?");
                    $updateRiskStmt = $db->prepare("UPDATE grades SET risk_level = ? WHERE id = ?");
                    $logStmt = $db->prepare("INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value, note) VALUES (?, 'student', ?, ?, ?, ?, ?)");

                    foreach ($payload as $item) {
                        $sid = (int)$item['student_id'];
                        $facultyProposed = $item['grade'];
                        $reason = $item['reason'];
                        $finalGrade = trim($approvedGrades[$sid] ?? $facultyProposed);
                        
                        if (!validateAdminGrade($termType, $finalGrade)) throw new Exception("Validation Error: Invalid value '{$finalGrade}' for student #{$sid}.");

                        $getGradeStmt->execute([$sid, $batch['subject_id']]);
                        $gradeRow = $getGradeStmt->fetch();
                        
                        if ($gradeRow && $finalGrade !== '') {
                            $oldVal = $gradeRow[$termType] ?? '(empty)';
                            
                            // 1. Update the actual grade
                            $updateGradeStmt->execute([$finalGrade, $gradeRow['id']]);
                            
                            // 2. Recalculate Risk & GWA, delete old predictions
                            $newRisk = recalculateGradeRowRisk($db, $gradeRow['id']);
                            $updateRiskStmt->execute([$newRisk, $gradeRow['id']]);
                            recalculateStudentGWA($db, $sid);
                            $db->prepare("DELETE FROM predictions WHERE student_id = ?")->execute([$sid]);
                            
                            // 3. Log the change to the System Audit Log
                            $note = 'Batch Approval: ' . $reason;
                            if ($finalGrade !== $facultyProposed) { $note .= " (Admin modified from proposed $facultyProposed)"; }
                            $logStmt->execute([$user['id'], $sid, "grade_{$termType}", $oldVal, $finalGrade, $note]);
                        }
                    }
                    $db->prepare("UPDATE pending_grade_batches SET status='approved', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $batchId]);
                    $success = 'Batch successfully approved and logged in System Audit.';
                }
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); $error = 'Batch processing failed: ' . $e->getMessage(); }
        }
        elseif ($action === 'close_support_case') {
            $caseId = (int)$_POST['case_id'];
            $db->prepare("UPDATE academic_support_cases SET status = 'closed' WHERE id = ?")->execute([$caseId]);
            $success = "Overall Academic Support Case has been formally closed.";
        }
    }
}

$allStudents = $db->query("SELECT u.id, sp.student_number, u.first_name, u.middle_name, u.last_name FROM users u JOIN student_profiles sp ON u.id = sp.user_id")->fetchAll();
$studentDict = []; foreach ($allStudents as $st) { $studentDict[$st['id']] = ['no' => $st['student_number'], 'name' => formatNameLastFirst($st['first_name'], $st['middle_name'], $st['last_name'])]; }

$feedback = $db->query("SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, s.code AS subj_code, sp.section FROM feedback_reports f JOIN users u ON u.id = f.submitted_by LEFT JOIN subjects s ON s.id = f.subject_id LEFT JOIN student_profiles sp ON sp.user_id = f.submitted_by WHERE f.status IN ('open', 'faculty_review', 'awaiting_admin') ORDER BY f.created_at DESC")->fetchAll();

$historyFeedback = $db->query("
    SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, sp.section, s.code AS subj_code, s.title AS subj_title, ru.first_name AS r_first, ru.last_name AS r_last,
           (SELECT note FROM feedback_status_history fsh WHERE fsh.feedback_id = f.id ORDER BY created_at DESC LIMIT 1) as resolution_note
    FROM feedback_reports f 
    JOIN users u ON u.id = f.submitted_by 
    LEFT JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN subjects s ON s.id = f.subject_id 
    LEFT JOIN users ru ON ru.id = f.resolved_by 
    WHERE f.status IN ('resolved', 'rejected') 
    ORDER BY f.resolved_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$allFeedbackIds = array_unique(array_merge(array_column($feedback, 'id'), array_column($historyFeedback, 'id')));
$messages_by_ticket = [];
if (!empty($allFeedbackIds)) {
    $inClause = implode(',', array_fill(0, count($allFeedbackIds), '?'));
    $stmtMsgs = $db->prepare("
        SELECT fm.*, u.first_name, u.last_name, u.role 
        FROM feedback_messages fm 
        JOIN users u ON fm.sender_id = u.id 
        WHERE fm.feedback_id IN ($inClause)
        ORDER BY fm.created_at ASC
    ");
    $stmtMsgs->execute($allFeedbackIds);
    foreach ($stmtMsgs->fetchAll(PDO::FETCH_ASSOC) as $msg) {
        $msg['formatted_date'] = date('M j, Y \a\t g:i a', strtotime($msg['created_at']));
        $messages_by_ticket[$msg['feedback_id']][] = $msg;
    }
}

$pending = $db->query("SELECT pc.*, au.first_name AS a_first, au.last_name AS a_last, au.role AS a_role, COALESCE(u.first_name, gu.first_name) AS t_first, COALESCE(u.last_name, gu.last_name) AS t_last, COALESCE(u.user_id, gu.user_id) AS t_identifier, s.code AS subj_code, s.title AS subj_title FROM pending_corrections pc JOIN users au ON au.id = pc.proposed_by LEFT JOIN users u ON u.id = pc.target_id AND pc.target_type != 'grade' LEFT JOIN grades g ON g.id = pc.target_id AND pc.target_type = 'grade' LEFT JOIN users gu ON gu.id = g.student_id LEFT JOIN subjects s ON s.id = g.subject_id WHERE pc.status = 'pending' ORDER BY pc.proposed_at DESC, pc.id DESC")->fetchAll();
$pendingBatches = $db->query("SELECT pgb.*, u.first_name, u.last_name, s.code AS subj_code, s.title AS subj_title FROM pending_grade_batches pgb JOIN users u ON u.id = pgb.faculty_id JOIN subjects s ON s.id = pgb.subject_id WHERE pgb.status = 'pending' ORDER BY pgb.submitted_at ASC")->fetchAll();
$logs = $db->query("SELECT acl.*, au.first_name AS a_first, au.last_name AS a_last FROM admin_change_log acl JOIN users au ON au.id = acl.admin_id ORDER BY acl.created_at DESC LIMIT 50")->fetchAll();

$supportCases = $db->query("
    SELECT c.*, u.first_name, u.last_name, u.user_id AS student_no, sp.course, sp.year_level, sp.section AS student_section
    FROM academic_support_cases c 
    JOIN users u ON c.student_id = u.id 
    JOIN student_profiles sp ON sp.user_id = u.id
    ORDER BY c.status ASC, c.created_at DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$caseIds = array_column($supportCases, 'id');
$referralsByCase = [];
if (!empty($caseIds)) {
    $in = str_repeat('?,', count($caseIds) - 1) . '?';
    $refs = $db->prepare("
        SELECT r.*, s.code AS subj_code, s.title AS subj_title, u.first_name AS fac_first, u.last_name AS fac_last
        FROM support_case_referrals r
        JOIN subjects s ON r.subject_id = s.id
        JOIN users u ON r.faculty_id = u.id
        WHERE r.case_id IN ($in)
    ");
    $refs->execute($caseIds);
    foreach ($refs->fetchAll(PDO::FETCH_ASSOC) as $ref) {
        $referralsByCase[$ref['case_id']][] = $ref;
    }
}

$countInbox = count($feedback);
$countApprovals = count($pending) + count($pendingBatches);
$countSupportReviews = count(array_filter($supportCases, fn($c) => $c['status'] !== 'closed'));

$pageTitle = 'Activity & Inbox';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'], ['Students',           'students.php',  '👥'], ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'], ['Program Analytics',  'analytics.php', '📊'], ['Activity & Inbox',   'activity.php',  '💬'], ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php'; require_once '../includes/sidebar.php';
?>

<style>
.tab-btn { background: none; border: none; padding: 12px 24px; font-size: 0.95rem; font-weight: 700; color: var(--text-gray); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; font-family: inherit; }
.tab-btn.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
.kpi-drilldown { cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.kpi-drilldown:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }

.approval-card { border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 16px; display: flex; flex-direction: column; overflow: hidden; background: transparent; }
.approval-card-header { padding: 16px 20px; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.approval-card-rail { padding: 14px 20px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.015); }
[data-theme="dark"] .approval-card-rail { background: rgba(255,255,255,0.02); }
.approval-card-footer { padding: 16px 20px; background: transparent; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; font-size: 0.85rem; color: var(--text-gray); }

.strip-blue { border-left: 4px solid var(--accent-blue); }
.strip-amber { border-left: 4px solid var(--risk-mod); }
.strip-red { border-left: 4px solid var(--risk-high); }
.strip-blue-light { border-left: 4px solid #3b82f6; }

.purpose-pill { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
.pill-blue { background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); }
.pill-amber { background: rgba(217, 119, 6, 0.1); color: var(--risk-mod); }
.pill-red { background: rgba(220, 38, 38, 0.1); color: var(--risk-high); }
.pill-gray { background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-gray); }
.pill-green { background: rgba(5, 150, 105, 0.1); color: var(--risk-low); }

/* Buttons */
.btn-primary { display: inline-block; text-decoration: none; text-align: center; background: var(--accent-blue); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; font-family: inherit; transition: opacity 0.2s; }
.btn-primary:hover { opacity: 0.9; }
.btn-primary-outline { display: inline-block; text-decoration: none; text-align: center; background: transparent; color: var(--accent-blue); border: 1px solid rgba(30, 77, 183, 0.3); padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; font-family: inherit; transition: all 0.2s; }
.btn-primary-outline:hover { background: rgba(30, 77, 183, 0.05); }
.btn-success { display: inline-block; text-decoration: none; text-align: center; background: rgba(5, 150, 105, 0.1); color: var(--risk-low); border: 1px solid rgba(5, 150, 105, 0.3); padding: 8px 16px; border-radius: 6px; font-weight: 600; font-family: inherit; cursor: pointer; transition: all 0.2s; }
.btn-success:hover { background: rgba(5, 150, 105, 0.2); }
.btn-danger-outline { display: inline-block; text-decoration: none; text-align: center; background: transparent; color: var(--risk-high); border: 1px solid rgba(220, 38, 38, 0.3); padding: 8px 16px; border-radius: 6px; font-weight: 600; font-family: inherit; cursor: pointer; transition: all 0.2s; }
.btn-danger-outline:hover { background: rgba(220, 38, 38, 0.1); }
.btn-secondary { display: inline-block; text-decoration: none; text-align: center; background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.75rem; font-family: inherit; transition: opacity 0.2s; }

/* Message Bubbles for Threads */
.msg-bubble { margin-bottom: 12px; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; }
.msg-mine { background: rgba(30, 77, 183, 0.05); border: 1px solid rgba(30, 77, 183, 0.1); border-left: 3px solid var(--accent-blue); }
.msg-theirs { background: rgba(217, 119, 6, 0.05); border: 1px solid rgba(217, 119, 6, 0.1); border-left: 3px solid var(--risk-mod); }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 40px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; backdrop-filter: blur(4px); }
.modal-overlay.open { opacity: 1; visibility: visible; }
.modal-box { background: var(--card-bg); padding: 24px; border-radius: 12px; width: 100%; max-width: 800px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; margin: auto; transform: scale(0.95) translateY(15px); transition: transform 0.3s ease; border: 1px solid var(--border-color); }
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
.modal-close { position: absolute; top: 20px; right: 20px; background: none; border: 1px solid var(--border-color); color: var(--text-dark); cursor: pointer; font-size: 0.85rem; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-family: inherit; transition: background 0.2s; }
.modal-close:hover { background: var(--bg-color); }

.review-table th { padding: 10px 14px; text-align: left; background: rgba(0,0,0,0.02); color: var(--text-gray); font-weight: 600; border-bottom: 2px solid var(--border-color); }
.review-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-dark); }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div><h1>Activity, Approvals & Audit</h1><p style="color: var(--text-gray);">Review incoming reports, process academic-record changes, monitor support cases, and inspect permanent system history.</p></div>
    </div>

    <?php if ($error): ?><p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight: 600;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight: 600;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--accent-blue) !important;" onclick="switchTab('inbox', true)"><h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Open Reports</h4><h2 style="margin: 8px 0 0; color: var(--text-dark); font-size: 2rem;"><?= $countInbox ?></h2><div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Incoming queries</div></div>
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--risk-mod) !important;" onclick="switchTab('approvals', true)"><h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Pending Approvals</h4><h2 style="margin: 8px 0 0; color: var(--risk-mod); font-size: 2rem;"><?= $countApprovals ?></h2><div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Batches & corrections</div></div>
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--risk-high) !important;" onclick="switchTab('support', true)"><h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Support Reviews</h4><h2 style="margin: 8px 0 0; color: var(--risk-high); font-size: 2rem;"><?= $countSupportReviews ?></h2><div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Active program cases</div></div>
    </div>

    <div id="workspace-tabs" style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
        <button id="btn-inbox" class="tab-btn active" onclick="switchTab('inbox')">Inbox (<?= $countInbox ?>)</button>
        <button id="btn-approvals" class="tab-btn" onclick="switchTab('approvals')">Approvals (<?= $countApprovals ?>)</button>
        <button id="btn-support" class="tab-btn" onclick="switchTab('support')">Academic Support (<?= $countSupportReviews ?>)</button>
        <button id="btn-history" class="tab-btn" onclick="switchTab('history')">History & Audit</button>
    </div>

    <!-- TAB 1: INBOX -->
    <div id="tab-inbox" class="tab-content active">
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);"><div><h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin:0 0 4px 0;">Open Feedback Reports</h3></div></div>
            <?php if (empty($feedback)): ?><p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">✓ All caught up! No open reports.</p><?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                        <thead>
                            <tr style="background: var(--table-header-bg);">
                                <th style="border-bottom: 1px solid var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">Submitted</th>
                                <th style="border-bottom: 1px solid var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">From</th>
                                <th style="border-bottom: 1px solid var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">Category</th>
                                <th style="border-bottom: 1px solid var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">Message</th>
                                <th style="border-bottom: 1px solid var(--border-color); padding: 12px 24px; text-align: right; color: var(--text-dark);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback as $f): ?>
                            <tr>
                                <td style="padding: 12px 24px; color: var(--text-gray); white-space:nowrap; vertical-align: top; border-bottom: 1px solid var(--border-color);"><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
                                <td style="padding: 12px 24px; font-weight: 600; color: var(--text-dark); vertical-align: top; border-bottom: 1px solid var(--border-color);"><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?><br><span style="font-size: 0.75rem; color:var(--text-gray); font-weight:normal;"><?= ucfirst($f['role']) ?></span></td>
                                <td style="padding: 12px 24px; font-weight: 600; color: var(--accent-blue); vertical-align: top; border-bottom: 1px solid var(--border-color);"><?= ucwords(str_replace('_', ' ', $f['category'])) ?></td>
                                <td style="padding: 12px 24px; color: var(--text-gray); max-width: 250px; vertical-align: top; border-bottom: 1px solid var(--border-color);"><div style="font-weight: 600; color: var(--text-dark); font-size: 0.85rem; margin-bottom: 4px;">"<?= htmlspecialchars($f['title'] ?? 'Report') ?>"</div><div style="font-size: 0.85rem;"><?= nl2br(htmlspecialchars($f['message'] ?? $f['initial_message'] ?? '')) ?></div></td>
                                <td style="padding: 12px 24px; text-align:right; vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <div style="display:flex; justify-content: flex-end; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <?php if ($f['status'] === 'awaiting_admin'): ?><span style="color: var(--accent-blue); font-size: 0.8rem; font-weight: 600;">Awaiting Fix</span>
                                        <?php elseif ($f['status'] === 'faculty_review'): ?><span style="color: var(--risk-mod); font-size: 0.8rem; font-weight: 600;">With Faculty</span>
                                        <?php else: ?>
                                            <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Mark this report as resolved?');" style="margin:0;"><input type="hidden" name="action" value="resolve_feedback"><input type="hidden" name="feedback_id" value="<?= $f['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit" style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); border:1px solid rgba(5, 150, 105, 0.3); padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer;">Resolve</button></form>
                                            <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Reject this report?');" style="margin:0;"><input type="hidden" name="action" value="reject_feedback"><input type="hidden" name="feedback_id" value="<?= $f['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit" style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); border:1px solid rgba(220, 38, 38, 0.3); padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer;">Reject</button></form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: APPROVALS -->
    <div id="tab-approvals" class="tab-content">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);"><h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Grade Batches</h3></div>
                <div style="max-height: 500px; overflow-y: auto; padding: 16px 24px;">
                    <?php if (empty($pendingBatches)): ?><p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">No faculty grade batches pending.</p><?php else: ?>
                        <?php foreach ($pendingBatches as $b): 
                            $payloadData = json_decode($b['payload'], true); $recordCount = count($payloadData);
                            $purposeLabel = match($b['submission_type']) { 'initial_encoding' => 'Initial Grade Encoding', 'bulk_correction' => 'Bulk Grade Correction', 'grade_concern' => 'Student Grade Concern', default => ucwords(str_replace('_', ' ', $b['submission_type'])) };
                            if ($b['submission_type'] === 'bulk_correction') { $stripClass = 'strip-amber'; $pillClass = 'pill-amber'; } elseif ($b['submission_type'] === 'grade_concern') { $stripClass = 'strip-red'; $pillClass = 'pill-red'; } else { $stripClass = 'strip-blue'; $pillClass = 'pill-blue'; }
                        ?>
                        <div class="approval-card <?= $stripClass ?>">
                            <div class="approval-card-header">
                                <div><strong style="color:var(--accent-blue); font-size: 1.05rem;"><?= htmlspecialchars($b['subj_code']) ?> • <?= htmlspecialchars($b['subj_title']) ?></strong><br><span style="font-size: 0.85rem; color: var(--text-dark);">Section <strong><?= htmlspecialchars($b['section']) ?></strong> • <strong><?= ucfirst($b['term_type']) ?></strong> Grades</span></div>
                                <div style="text-align: right; font-size: 0.85rem; color: var(--text-gray);">Submitted by: <strong style="color: var(--text-dark);"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></strong><br><?= date('M j, Y \a\t g:i A', strtotime($b['submitted_at'])) ?></div>
                            </div>
                            <div class="approval-card-rail" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <span class="purpose-pill <?= $pillClass ?>"><?= $purposeLabel ?></span><span class="purpose-pill pill-gray"><?= $recordCount ?> Record(s)</span>
                                <?php if ($b['feedback_id']): ?><span class="purpose-pill pill-blue">Linked Ticket #<?= htmlspecialchars($b['feedback_id']) ?></span><?php endif; ?>
                            </div>
                            <div class="approval-card-footer">
                                <div style="font-size: 0.85rem; color: var(--text-gray); flex: 1;"><?php if ($b['batch_note']): ?><strong style="color: var(--text-dark);">Note:</strong> <?= htmlspecialchars($b['batch_note']) ?><?php else: ?><em>No shared note provided.</em><?php endif; ?></div>
                                <div><button onclick="document.getElementById('batch-modal-<?= $b['id'] ?>').classList.add('open')" class="btn-primary">Review Batch</button></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);"><h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Pending Record Corrections</h3></div>
                <div style="max-height: 500px; overflow-y: auto; padding: 16px 24px;">
                    <?php if (empty($pending)): ?><p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">No record corrections pending.</p><?php else: ?>
                        <?php foreach ($pending as $p): $stripClass = ($p['target_type'] === 'grade') ? 'strip-amber' : 'strip-blue-light'; ?>
                        <div class="approval-card <?= $stripClass ?>">
                            <div class="approval-card-header">
                                <div><strong style="color:var(--text-dark); font-size: 1.05rem;"><?= htmlspecialchars($p['t_first'] . ' ' . $p['t_last']) ?></strong><br><span style="font-size: 0.85rem; color: var(--text-gray);">Student No. <?= htmlspecialchars($p['t_identifier']) ?></span></div>
                                <div style="text-align: right; font-size: 0.85rem; color: var(--text-gray);">Submitted by: <strong style="color: var(--text-dark);"><?= htmlspecialchars($p['a_first'] . ' ' . $p['a_last']) ?> (<?= ucfirst($p['a_role']) ?>)</strong><br><?= date('M j, Y \a\t g:i A', strtotime($p['proposed_at'])) ?></div>
                            </div>
                            <div class="approval-card-rail">
                                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-gray); margin-bottom: 8px;">
                                    <?php if ($p['target_type'] === 'grade'): ?><span style="color: var(--text-dark);"><?= htmlspecialchars($p['subj_code'] . ' • ' . $p['subj_title']) ?></span> (<?= ucfirst(str_replace('_', ' ', $p['field_changed'])) ?>)
                                    <?php else: ?><span style="color: var(--text-dark);">Enrollment Information</span> (<?= ucfirst(str_replace('_', ' ', $p['field_changed'])) ?>)<?php endif; ?>
                                </div>
                                <div style="font-size: 0.95rem; display: flex; align-items: center; gap: 12px;"><span class="diff-old"><?= htmlspecialchars($p['old_value'] ?: 'Not assigned') ?></span><span style="color: var(--text-gray); font-size: 1.2rem;">➔</span><span class="diff-new"><?= htmlspecialchars($p['new_value']) ?></span></div>
                            </div>
                            <div class="approval-card-footer">
                                <div style="font-size: 0.85rem; color: var(--text-gray); flex: 1; min-width: 200px;">
                                    <?php if ($p['feedback_id']): ?><span class="purpose-pill pill-blue" style="margin-bottom: 4px;">Linked Ticket #<?= htmlspecialchars($p['feedback_id']) ?></span><br><?php endif; ?>
                                    <strong style="color: var(--text-dark);">Reason:</strong> <?= htmlspecialchars($p['reason'] ?: 'No reason provided') ?>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Reject this correction?');" style="margin:0;"><input type="hidden" name="action" value="reject_correction"><input type="hidden" name="correction_id" value="<?= $p['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit" class="btn-danger-outline">Reject</button></form>
                                    <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Confirm this correction?');" style="margin:0;"><input type="hidden" name="action" value="confirm_correction"><input type="hidden" name="correction_id" value="<?= $p['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit" class="btn-success">Confirm</button></form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: ACADEMIC SUPPORT -->
    <div id="tab-support" class="tab-content">
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <div><h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Academic Support Oversight</h3><p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Administer program-level cases and monitor subject-specific faculty referrals.</p></div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;"><a href="export_intervention_audit.php" class="btn-secondary">Export CSV</a><a href="export_intervention_audit_pdf.php" class="btn-primary">Download PDF</a></div>
            </div>
            <div style="max-height: 500px; overflow-y: auto; padding: 0;">
                <?php if (empty($supportCases)): ?><p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No active program-level support cases.</p><?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 10px 24px; text-align: left; color: var(--text-dark); font-weight: 600;">Student Context</th>
                                <th style="padding: 10px 24px; text-align: left; color: var(--text-dark); font-weight: 600;">Overall Academic Context</th>
                                <th style="padding: 10px 24px; text-align: center; color: var(--text-dark); font-weight: 600;">Subject Referrals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supportCases as $sc): 
                                $refs = $referralsByCase[$sc['id']] ?? [];
                                $totalRefs = count($refs);
                                $pendingRefs = count(array_filter($refs, fn($r) => $r['status'] === 'needs_review'));
                                $riskLevel = strtoupper(htmlspecialchars($sc['trigger_risk_level']));
                                $riskColor = $riskLevel === 'HIGH' ? 'var(--risk-high)' : 'var(--risk-mod)';
                                $gwa = number_format($sc['trigger_predicted_gwa'], 2);
                                $date = date('M j, Y', strtotime($sc['created_at']));
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px 24px; vertical-align: middle;">
                                    <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 4px; font-size:1.05rem;">
                                        <?= htmlspecialchars($sc['first_name'] . ' ' . $sc['last_name']) ?>
                                        <?php if ($sc['status'] === 'closed'): ?>
                                            <span style="background:var(--bg-color); border: 1px solid var(--border-color); color:var(--text-gray); padding:2px 6px; border-radius:4px; font-size:0.65rem; font-weight:700; margin-left:8px; vertical-align:middle;">CLOSED</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-gray);">
                                        <span style="font-family: monospace; color: var(--text-dark);"><?= htmlspecialchars($sc['student_no']) ?></span> • <?= htmlspecialchars($sc['student_section']) ?> • Active since <?= $date ?>
                                    </div>
                                </td>
                                
                                <td style="padding: 10px 24px; vertical-align: middle;">
                                    <div style="font-size: 0.9rem; color: var(--text-gray);">
                                        <strong style="color: <?= $riskColor ?>;"><?= $riskLevel ?> Risk</strong> • 
                                        Predicted GWA: <strong style="color: var(--text-dark);"><?= $gwa ?></strong>
                                    </div>
                                </td>

                                <td style="padding: 10px 24px; vertical-align: middle; text-align: center;">
                                    <div style="display: inline-flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--text-gray);">
                                        <span><strong style="color: var(--text-dark);"><?= $totalRefs ?></strong> referral<?= $totalRefs !== 1 ? 's' : '' ?> • <strong><?= $pendingRefs ?></strong> pending</span>
                                        <button onclick='openReferralsModal(<?= json_encode($refs, JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($sc['id']) ?>, <?= json_encode($sc['status']) ?>)' class="btn-primary-outline" style="padding: 4px 12px; font-size: 0.75rem;">View</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB 4: HISTORY & AUDIT -->
    <div id="tab-history" class="tab-content">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);"><h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Closed Feedback History</h3><p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Comprehensive audit log of resolved and rejected tickets.</p></div>
                <div style="max-height: 600px; overflow-y: auto; padding: 0;">
                    <?php if (empty($historyFeedback)): ?><p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No closed feedback records found.</p><?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: 10px 24px; text-align: left; color: var(--text-dark); font-weight: 600; width: 30%;">Ticket</th>
                                    <th style="padding: 10px 24px; text-align: left; color: var(--text-dark); font-weight: 600; width: 10%;">Decision</th>
                                    <th style="padding: 10px 24px; text-align: left; color: var(--text-dark); font-weight: 600; width: 20%;">Submitted By</th>
                                    <th style="padding: 10px 24px; text-align: left; color: var(--text-dark); font-weight: 600; width: 30%;">Resolution Details</th>
                                    <th style="padding: 10px 24px; text-align: right; color: var(--text-dark); font-weight: 600; width: 10%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historyFeedback as $hf): 
                                    $pillClass = $hf['status'] === 'resolved' ? 'pill-green' : 'pill-red';
                                    $handledBy = ($hf['r_first'] && $hf['r_last']) ? ($hf['r_first'] . ' ' . $hf['r_last']) : 'Administrator';
                                    $catStr = ucwords(str_replace('_', ' ', $hf['category']));
                                    
                                    $contextArr = [$catStr];
                                    if ($hf['subj_code']) $contextArr[] = htmlspecialchars($hf['subj_code']);
                                    if ($hf['grade_period']) $contextArr[] = ucfirst(htmlspecialchars($hf['grade_period']));
                                    if ($hf['section']) $contextArr[] = htmlspecialchars($hf['section']);
                                    $contextStr = implode(' • ', $contextArr);
                                ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 16px 24px; vertical-align: middle;">
                                        <div style="font-size: 0.75rem; color: var(--text-gray); margin-bottom: 4px;">Ticket #<?= $hf['id'] ?></div>
                                        <div style="font-weight: 700; color: var(--text-dark); font-size: 0.95rem; margin-bottom: 4px;"><?= htmlspecialchars($hf['title']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);"><?= $contextStr ?></div>
                                    </td>
                                    
                                    <td style="padding: 16px 24px; vertical-align: middle;">
                                        <span class="purpose-pill <?= $pillClass ?>" style="font-size: 0.65rem; padding: 4px 8px;"><?= strtoupper($hf['status']) ?></span>
                                    </td>
                                    
                                    <td style="padding: 16px 24px; vertical-align: middle;">
                                        <div style="font-weight: 600; color: var(--text-dark); margin-bottom: 4px;"><?= htmlspecialchars($hf['first_name'] . ' ' . $hf['last_name']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-gray); font-family: monospace;"><?= htmlspecialchars($hf['identifier']) ?></div>
                                    </td>
                                    
                                    <td style="padding: 16px 24px; vertical-align: middle;">
                                        <div style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 4px;">Handled by: <strong style="color: var(--text-dark);"><?= htmlspecialchars($handledBy) ?></strong></div>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">Closed: <strong style="color: var(--text-dark);"><?= date('M j, Y \a\t g:i A', strtotime($hf['resolved_at'])) ?></strong></div>
                                    </td>
                                    
                                    <td style="padding: 16px 24px; vertical-align: middle; text-align: right;">
                                        <button onclick="toggleHistoryDetails(<?= $hf['id'] ?>, this)" class="btn-secondary" style="white-space: nowrap;">View</button>
                                    </td>
                                </tr>
                                
                                <!-- EXPANDABLE DETAILS ROW -->
                                <tr id="history_details_<?= $hf['id'] ?>" style="display: none; background: rgba(0,0,0,0.015);">
                                    <td colspan="5" style="padding: 16px 24px; border-bottom: 1px solid var(--border-color); box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                                            
                                            <!-- Left: Original Submission -->
                                            <div>
                                                <h4 style="margin: 0 0 6px 0; color: var(--text-gray); font-size: 0.75rem; text-transform: uppercase;">Original Submission</h4>
                                                <div style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; color: var(--text-dark);">
                                                    <div id="msg_trunc_<?= $hf['id'] ?>" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-wrap;"><?= htmlspecialchars($hf['message']) ?></div>
                                                    <div id="msg_full_<?= $hf['id'] ?>" style="display: none; white-space: pre-wrap;"><?= htmlspecialchars($hf['message']) ?></div>
                                                    <?php if (substr_count($hf['message'], "\n") > 2 || strlen($hf['message']) > 150): ?>
                                                        <button type="button" id="msg_btn_<?= $hf['id'] ?>" onclick="toggleMsgText(<?= $hf['id'] ?>)" style="background: none; border: none; color: var(--accent-blue); padding: 0; font-size: 0.8rem; font-weight: 600; cursor: pointer; margin-top: 6px;">Show more</button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($messages_by_ticket[$hf['id']])): ?>
                                                    <div style="margin-top: 12px;">
                                                        <button onclick='openThreadModal(<?= json_encode($messages_by_ticket[$hf['id']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= $hf['id'] ?>)' class="btn-primary-outline" style="font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;">
                                                            💬 View conversation (<?= count($messages_by_ticket[$hf['id']]) ?> replies)
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Right: Final Resolution Note -->
                                            <div>
                                                <h4 style="margin: 0 0 6px 0; color: var(--text-gray); font-size: 0.75rem; text-transform: uppercase;">Final Resolution Note</h4>
                                                <div style="background: var(--bg-color); border: 1px dashed var(--border-color); padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; color: var(--text-dark); white-space: pre-wrap; max-height: 120px; overflow-y: auto;"><?= htmlspecialchars($hf['resolution_note'] ?: 'No formal resolution note was recorded for this ticket.') ?></div>
                                            </div>

                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;"><div><h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">System Audit Log</h3><p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Permanent administrative record of all data modifications.</p></div></div>
                <div style="max-height: 400px; overflow-y: auto; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="padding: 16px 24px; width: 100px; color: var(--text-gray); vertical-align: top; border-bottom: 1px solid var(--border-color); font-weight: 600;"><?= date('M j, Y', strtotime($l['created_at'])) ?></td>
                                <td style="padding: 16px 24px; border-bottom: 1px solid var(--border-color);">
                                    <span style="font-weight:600; color:var(--text-dark);"><?= htmlspecialchars($l['a_first'] . ' ' . $l['a_last']) ?></span> modified <span style="font-weight:600; color:var(--accent-blue); text-transform: uppercase;"><?= htmlspecialchars($l['target_type']) ?></span><br>
                                    <span style="color:var(--text-gray); display: inline-block; margin-top: 4px;"><?= htmlspecialchars($l['field_changed']) ?>: <span style="text-decoration: line-through;"><?= htmlspecialchars($l['old_value'] ?? '(empty)') ?></span> &rarr; <strong style="color:var(--text-dark);"><?= htmlspecialchars($l['new_value']) ?></strong></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: View Child Referrals -->
<div id="referralsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; padding-top: 50px; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 800px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <div>
                <h3 style="margin-top: 0; color: var(--text-dark); margin-bottom: 4px;">Subject-Level Referrals</h3>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Monitor faculty interventions for this program-level case.</p>
            </div>
            <button type="button" onclick="document.getElementById('referralsModal').style.display='none'" class="btn-secondary">✕ Close</button>
        </div>
        
        <div id="referralsList" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 20px;"></div>
        
        <div id="closeCaseWrapper" style="display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-color);">
            <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Are you sure you want to officially close this student\'s program case?');" style="margin:0;">
                <input type="hidden" name="action" value="close_support_case">
                <input type="hidden" name="case_id" id="closeModalCaseId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <button type="submit" class="btn-success">✔️ Close Program Case</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View Sent Notice Detail (From within Referrals) -->
<div id="viewNoticeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.4);">
        <h3 style="margin-top: 0; color: var(--text-dark);">Issued Notice Details</h3>
        <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 20px;">
            Issued by: <strong id="viewNoticeFaculty" style="color: var(--accent-blue);"></strong><br>
            This is the exact message that was securely transmitted to the student.
        </p>
        <div id="viewNoticeText" style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 16px; border-radius: 6px; font-size: 0.9rem; color: var(--text-dark); margin-bottom: 16px; white-space: pre-wrap; line-height: 1.4;"></div>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="document.getElementById('viewNoticeModal').style.display='none'" class="btn-primary">Back to Referrals</button>
        </div>
    </div>
</div>

<!-- NEW Modal: View History Conversation Thread -->
<div id="historyThreadModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: flex-start; padding-top: 50px; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 600px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <div>
                <h3 style="margin-top: 0; color: var(--text-dark); margin-bottom: 4px;">Conversation Thread</h3>
                <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Ticket #<span id="threadModalTicketId"></span></p>
            </div>
            <button type="button" onclick="document.getElementById('historyThreadModal').style.display='none'" class="btn-secondary">✕ Close</button>
        </div>
        <div id="historyThreadList" style="max-height: 400px; overflow-y: auto; background: var(--bg-color); border: 1px solid var(--border-color); padding: 16px; border-radius: 8px;"></div>
    </div>
</div>

<!-- Modals for Batch Reviews -->
<?php foreach ($pendingBatches as $b): 
    $payloadData = json_decode($b['payload'], true);
    $purposeLabel = match($b['submission_type']) { 'initial_encoding' => 'Initial Grade Encoding', 'bulk_correction' => 'Bulk Grade Correction', 'grade_concern' => 'Student Grade Concern', default => ucwords(str_replace('_', ' ', $b['submission_type'])) };
?>
<div class="modal-overlay" id="batch-modal-<?= $b['id'] ?>" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="modal-box" style="max-width: 900px;">
        <button type="button" class="modal-close" onclick="document.getElementById('batch-modal-<?= $b['id'] ?>').classList.remove('open')">✕ Close</button>
        <h2 style="color:var(--text-dark); margin-top:0; margin-bottom:4px;">Review Grade Batch</h2>
        
        <div style="background: var(--bg-color); padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 20px;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                <div>
                    <div style="font-weight: 700; color: var(--accent-blue); font-size: 1.1rem; margin-bottom: 4px;"><?= htmlspecialchars($b['subj_code']) ?> • <?= htmlspecialchars($b['subj_title']) ?></div>
                    <div style="color: var(--text-dark); font-size: 0.95rem;">Section <strong><?= htmlspecialchars($b['section']) ?></strong> • <strong><?= ucfirst($b['term_type']) ?></strong></div>
                </div>
                <div style="font-size: 0.85rem; color: var(--text-gray); text-align:right;">
                    Submitted by: <strong style="color: var(--text-dark);"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></strong><br>
                    Date: <?= date('M j, Y \a\t g:i A', strtotime($b['submitted_at'])) ?>
                </div>
            </div>
            
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--border-color); display:flex; gap:24px; flex-wrap:wrap;">
                <div style="flex:1;">
                    <span style="font-size:0.8rem; color:var(--text-gray); text-transform:uppercase; font-weight:700;">Submission Purpose</span><br>
                    <strong style="color:var(--risk-mod); font-size: 0.95rem;"><?= $purposeLabel ?></strong>
                </div>
                <div style="flex:2;">
                    <span style="font-size:0.8rem; color:var(--text-gray); text-transform:uppercase; font-weight:700;">Shared Context / Note</span><br>
                    <?php if ($b['feedback_id']): ?>
                        <strong style="color: var(--accent-blue);">Linked to Ticket #<?= htmlspecialchars($b['feedback_id']) ?></strong>
                    <?php else: ?>
                        <span style="color:var(--text-dark);"><?= htmlspecialchars($b['batch_note'] ?: 'No shared note provided.') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="POST" action="activity.php" class="safe-submit-form">
            <input type="hidden" name="action" value="approve_batch">
            <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="background:var(--bg-color); border:1px solid var(--border-color); border-radius:8px; overflow:hidden; margin-bottom:20px; max-height:400px; overflow-y:auto;">
                <table class="review-table" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th style="text-align: center;">Current</th>
                            <th style="text-align: center;">Proposed</th>
                            <th style="text-align: center;">Change</th>
                            <th>Individual Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $newCount = 0; $modCount = 0;
                        foreach ($payloadData as $item): 
                            $sid = $item['student_id'];
                            $student = $studentDict[$sid] ?? ['no' => 'Unknown', 'name' => 'Unknown Student'];
                            
                            $old = $item['old_value'] ?? null;
                            $new = $item['grade'];
                            
                            $changeStr = '—';
                            $isNumericOld = ($old !== null && $old !== '' && is_numeric($old));
                            if ($isNumericOld && is_numeric($new)) {
                                $diff = (float)$new - (float)$old;
                                $changeStr = ($diff > 0 ? '+' : '') . $diff;
                                $modCount++;
                            } elseif ($old === null || $old === '') {
                                $changeStr = '<span style="color:var(--risk-low); font-weight:700;">NEW</span>';
                                $newCount++;
                            } else {
                                $changeStr = '<span style="color:var(--risk-mod); font-weight:700;">UPDATE</span>';
                                $modCount++;
                            }
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--text-dark);"><?= htmlspecialchars($student['name']) ?></strong><br>
                                <span style="font-size:0.75rem; color:var(--text-gray); font-family:monospace;"><?= htmlspecialchars($student['no']) ?></span>
                            </td>
                            <td style="text-align: center; color:var(--text-gray); font-weight:600;">
                                <?= htmlspecialchars($old !== null && $old !== '' ? $old : 'Empty') ?>
                            </td>
                            <td style="text-align: center;">
                                <input type="text" name="approved_grades[<?= $sid ?>]" value="<?= htmlspecialchars($new) ?>" style="width: 60px; padding: 6px; border: 1px solid var(--border-color); border-radius: 4px; text-align: center; font-weight: bold; font-family: inherit; background: var(--card-bg); color: var(--text-dark);">
                            </td>
                            <td style="text-align: center; font-weight:600;"><?= $changeStr ?></td>
                            <td style="color:var(--text-dark); max-width: 200px;"><?= htmlspecialchars($item['reason'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content: space-between; align-items: center; margin-top: 16px;">
                <div style="font-size: 0.85rem; color: var(--text-gray);">
                    <strong><?= count($payloadData) ?> total records</strong> (<?= $newCount ?> new, <?= $modCount ?> modified)
                </div>
                <div style="display:flex; gap: 12px;">
                    <button type="submit" formaction="activity.php" name="action" value="reject_batch" class="btn-danger-outline">Reject Batch</button>
                    <button type="submit" class="btn-success">Approve & Apply</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script>
function switchTab(tabId, shouldScroll = false) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    const targetContent = document.getElementById('tab-' + tabId);
    const activeBtn = document.getElementById('btn-' + tabId);
    
    if (targetContent) targetContent.classList.add('active');
    if (activeBtn) activeBtn.classList.add('active');

    if (shouldScroll) {
        const tabsEl = document.getElementById('workspace-tabs');
        if (tabsEl) {
            const y = tabsEl.getBoundingClientRect().top + window.scrollY - 20;
            window.scrollTo({top: y, behavior: 'smooth'});
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const requestedTab = params.get('tab');
    const allowedTabs = ['inbox', 'approvals', 'support', 'history'];

    if (allowedTabs.includes(requestedTab)) { switchTab(requestedTab, false); }
});

function openReferralsModal(referrals, caseId, caseStatus) {
    document.getElementById('closeModalCaseId').value = caseId;
    
    const wrapper = document.getElementById('closeCaseWrapper');
    if (caseStatus === 'closed') {
        wrapper.style.display = 'none';
    } else {
        wrapper.style.display = 'flex';
    }
    
    const list = document.getElementById('referralsList');
    if (!referrals || referrals.length === 0) {
        list.innerHTML = '<div style="padding: 24px; text-align: center; color: var(--text-gray);">No subject referrals linked to this case.</div>';
    } else {
        let html = '<table style="width:100%; border-collapse: collapse; font-size: 0.85rem;">';
        html += '<thead style="background: rgba(0,0,0,0.02);"><tr style="border-bottom: 2px solid var(--border-color);"><th style="padding: 12px; text-align: left; color: var(--text-gray);">Subject</th><th style="padding: 12px; text-align: left; color: var(--text-gray);">Faculty</th><th style="padding: 12px; text-align: left; color: var(--text-gray);">Risk Context</th><th style="padding: 12px; text-align: right; color: var(--text-gray);">Status</th></tr></thead><tbody>';
        
        referrals.forEach(r => {
            let statusPill = '';
            if (r.status === 'needs_review') statusPill = '<span style="background:rgba(217,119,6,0.1); color:var(--risk-mod); padding:4px 8px; border-radius:6px; font-weight:700;">NEEDS REVIEW</span>';
            else if (r.status === 'action_taken') statusPill = `<button onclick="openViewNoticeModal('${r.message_to_student ? r.message_to_student.replace(/'/g, "\\'") : ''}', '${r.fac_first} ${r.fac_last}')" class="btn-secondary" style="padding: 4px 8px; font-size: 0.7rem;">View Sent Notice</button>`;
            else if (r.status === 'acknowledged') statusPill = `<button onclick="openViewNoticeModal('${r.message_to_student ? r.message_to_student.replace(/'/g, "\\'") : ''}', '${r.fac_first} ${r.fac_last}')" class="btn-success" style="padding: 4px 8px; font-size: 0.7rem;">Student Acknowledged</button>`;
            
            html += `
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; vertical-align: top;"><strong style="color:var(--accent-blue);">${r.subj_code}</strong><br><span style="color:var(--text-gray); font-size:0.8rem;">${r.subj_title}</span></td>
                    <td style="padding: 12px; vertical-align: top; color:var(--text-dark); font-weight:600;">Prof. ${r.fac_last}</td>
                    <td style="padding: 12px; vertical-align: top; color:var(--text-gray);">Risk: <strong style="color:var(--risk-high);">${r.subject_risk_level}</strong><br>Grade: <strong style="color:var(--text-dark);">${parseFloat(r.latest_term_grade).toFixed(0)}%</strong></td>
                    <td style="padding: 12px; vertical-align: top; text-align: right;">${statusPill}</td>
                </tr>
            `;
        });
        html += '</tbody></table>';
        list.innerHTML = html;
    }
    
    document.getElementById('referralsModal').style.display = 'flex';
}

function openViewNoticeModal(message, facultyName) {
    document.getElementById('viewNoticeFaculty').textContent = facultyName;
    document.getElementById('viewNoticeText').textContent = message;
    document.getElementById('viewNoticeModal').style.display = 'flex';
}

function toggleHistoryDetails(id, btn) {
    const row = document.getElementById('history_details_' + id);
    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        btn.innerText = 'Hide';
    } else {
        row.style.display = 'none';
        btn.innerText = 'View';
    }
}

function toggleMsgText(id) {
    const trunc = document.getElementById('msg_trunc_' + id);
    const full = document.getElementById('msg_full_' + id);
    const btn = document.getElementById('msg_btn_' + id);
    
    if (trunc.style.display !== 'none') {
        trunc.style.display = 'none';
        full.style.display = 'block';
        btn.innerText = 'Show less';
    } else {
        trunc.style.display = '-webkit-box';
        full.style.display = 'none';
        btn.innerText = 'Show more';
    }
}

function openThreadModal(messages, ticketId) {
    document.getElementById('threadModalTicketId').textContent = ticketId;
    const list = document.getElementById('historyThreadList');
    
    let html = '';
    messages.forEach(m => {
        const isAdmin = m.role === 'admin';
        const bClass = isAdmin ? 'msg-mine' : 'msg-theirs';
        const sName = isAdmin ? 'Administrator (' + m.first_name + ')' : m.first_name + ' ' + m.last_name;
        const sColor = isAdmin ? 'var(--accent-blue)' : 'var(--risk-mod)';
        const safeMsg = m.message.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, '<br>');
        
        html += `
            <div class="msg-bubble ${bClass}" style="padding: 10px 14px; margin-bottom: 10px;">
                <strong style="color: ${sColor}; font-size: 0.8rem;">${sName}:</strong> 
                <span style="font-size: 0.7rem; color: var(--text-gray); margin-left: 8px;">${m.formatted_date}</span><br>
                <span style="font-size: 0.85rem; display: block; margin-top: 4px;">${safeMsg}</span>
            </div>
        `;
    });
    
    list.innerHTML = html;
    document.getElementById('historyThreadModal').style.display = 'flex';
}

document.querySelectorAll('.safe-submit-form').forEach(f => {
    f.addEventListener('submit', function() {
        const btns = this.querySelectorAll('button[type="submit"]');
        setTimeout(() => btns.forEach(b => { b.style.pointerEvents = 'none'; b.style.opacity = '0.7'; }), 10);
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>