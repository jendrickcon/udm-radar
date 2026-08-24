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
$error = '';
$success = '';

// --- ADMIN VALIDATION & RECALCULATION HELPERS ---
function validateAdminGrade($termType, $val) {
    if ($val === null || trim((string)$val) === '') return true;
    $valStr = strtoupper(trim((string)$val));
    
    if (in_array($termType, ['prelim', 'midterm', 'prefinal'])) {
        if (is_numeric($val)) {
            $f = (float)$val;
            if ($f >= 0 && $f <= 100) return true;
        }
        if (in_array($valStr, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD'])) return true;
        return false;
    } elseif ($termType === 'final_grade') {
        if (in_array($valStr, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD', '0', '0.00'])) return true;
        if (is_numeric($val)) {
            $f = (float)$val;
            $formatted = number_format($f, 2);
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
        if (is_numeric($g)) {
            $sum += (float)$g;
            $count++;
        }
    }
    $gwa = $count > 0 ? round($sum / $count, 2) : null;
    $db->prepare("UPDATE student_profiles SET current_gwa = ? WHERE user_id = ?")->execute([$gwa, $studentId]);
}
// ------------------------------------------------

// =========================================================
// Handle Form Submissions (Admin Approvals & Resolutions)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if (in_array($action, ['resolve_feedback', 'reject_feedback'])) {
            $fid = (int)$_POST['feedback_id'];
            $newStatus = ($action === 'resolve_feedback') ? 'resolved' : 'rejected';
            
            $db->beginTransaction();
            try {
                $stmtOld = $db->prepare("SELECT status FROM feedback_reports WHERE id = ?");
                $stmtOld->execute([$fid]);
                $oldStatus = $stmtOld->fetchColumn() ?: 'open';
                
                $db->prepare("UPDATE feedback_reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                   ->execute([$newStatus, $user['id'], $fid]);
                   
                $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$fid, $user['id'], $oldStatus, $newStatus, "Admin $newStatus the report."]);
                   
                $db->commit();
                $success = $newStatus === 'resolved' ? 'Report marked as resolved.' : 'Report rejected.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to update report: " . $e->getMessage();
            }
        }
        elseif (in_array($action, ['confirm_correction', 'reject_correction'])) {
            $corrId = (int)$_POST['correction_id'];
            
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT * FROM pending_corrections WHERE id = ? AND status = 'pending' FOR UPDATE");
                $stmt->execute([$corrId]);
                $corr = $stmt->fetch();

                if (!$corr) {
                    throw new Exception('Correction not found or already processed.');
                } 
                
                if ($action === 'reject_correction') {
                    $db->prepare("UPDATE pending_corrections SET status='rejected', resolved_by=?, resolved_at=NOW() WHERE id=?")
                       ->execute([$user['id'], $corrId]);
                       
                    if (!empty($corr['feedback_id'])) {
                        $stmtOld = $db->prepare("SELECT status FROM feedback_reports WHERE id = ?");
                        $stmtOld->execute([$corr['feedback_id']]);
                        $oldStatus = $stmtOld->fetchColumn() ?: 'awaiting_admin';
                        
                        $db->prepare("UPDATE feedback_reports SET status = 'faculty_review' WHERE id = ?")->execute([$corr['feedback_id']]);
                        $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, 'faculty_review', 'Admin rejected grade correction.')")->execute([$corr['feedback_id'], $user['id'], $oldStatus]);
                    }
                    $success = 'Correction rejected — no change applied.';
                } else {
                    $column = $corr['field_changed']; 
                    $allowedTerms = ['prelim', 'midterm', 'prefinal', 'final_grade'];
                    $logTargetId = $corr['target_id'];

                    if (in_array($corr['target_type'], ['student_section', 'student_year_level'])) {
                        $db->prepare("UPDATE student_profiles SET `$column` = ? WHERE user_id = ?")->execute([$corr['new_value'], $corr['target_id']]);
                    } elseif (strpos($corr['target_type'], 'grade') !== false) {
                        if (!in_array($column, $allowedTerms)) throw new Exception("Invalid grading period.");
                        
                        // FIXED: Strict Grading Scale Validation 
                        if (!validateAdminGrade($column, $corr['new_value'])) {
                            throw new Exception("Validation Error: Invalid value '{$corr['new_value']}' for {$column}. Percentages must be 0-100; Final Grades must use the 1.00-4.00 scale or a valid completion status.");
                        }
                        
                        $db->prepare("UPDATE grades SET `$column` = ? WHERE id = ?")->execute([$corr['new_value'], $corr['target_id']]);
                        $gradeRow = $db->query("SELECT student_id FROM grades WHERE id = " . (int)$corr['target_id'])->fetch();
                        if ($gradeRow) {
                            $logTargetId = $gradeRow['student_id'];
                            
                            // FIXED: Automatically recalculate Risk, GWA, and mark Prediction stale
                            $newRisk = recalculateGradeRowRisk($db, $corr['target_id']);
                            $db->prepare("UPDATE grades SET risk_level = ? WHERE id = ?")->execute([$newRisk, $corr['target_id']]);
                            recalculateStudentGWA($db, $logTargetId);
                            $db->prepare("DELETE FROM predictions WHERE student_id = ?")->execute([$logTargetId]);
                        }
                    }

                    $db->prepare("UPDATE pending_corrections SET status='confirmed', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $corrId]);
                        
                    $note = 'Confirmed correction' . ($corr['reason'] ? ': ' . $corr['reason'] : '');
                    $db->prepare("INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value, note) VALUES (?, 'student', ?, ?, ?, ?, ?)")
                       ->execute([$user['id'], $logTargetId, $column, $corr['old_value'], $corr['new_value'], $note]);
                    
                    if (!empty($corr['feedback_id'])) {
                        $stmtOld = $db->prepare("SELECT status FROM feedback_reports WHERE id = ?");
                        $stmtOld->execute([$corr['feedback_id']]);
                        $oldStatus = $stmtOld->fetchColumn() ?: 'awaiting_admin';
                        
                        $db->prepare("UPDATE feedback_reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?")->execute([$user['id'], $corr['feedback_id']]);
                        $db->prepare("INSERT INTO feedback_status_history (feedback_id, changed_by, old_status, new_status, note) VALUES (?, ?, ?, 'resolved', 'Admin confirmed grade correction.')")->execute([$corr['feedback_id'], $user['id'], $oldStatus]);
                    }
                    $success = 'Correction confirmed and officially reflected.';
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Action failed: ' . $e->getMessage();
            }
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
                    $success = 'Grade batch rejected. No grades were updated.';
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
                        
                        // FIXED: Strict Grading Scale Validation for Batch Items
                        if (!validateAdminGrade($termType, $finalGrade)) {
                            throw new Exception("Validation Error: Invalid value '{$finalGrade}' for student #{$sid}. Percentages must be 0-100; Final Grades must use the 1.00-4.00 scale or valid status.");
                        }

                        $getGradeStmt->execute([$sid, $batch['subject_id']]);
                        $gradeRow = $getGradeStmt->fetch();
                        
                        if ($gradeRow && $finalGrade !== '') {
                            $oldVal = $gradeRow[$termType] ?? '(empty)';
                            $updateGradeStmt->execute([$finalGrade, $gradeRow['id']]);
                            
                            // FIXED: Automatically recalculate Risk, GWA, and mark Prediction stale
                            $newRisk = recalculateGradeRowRisk($db, $gradeRow['id']);
                            $updateRiskStmt->execute([$newRisk, $gradeRow['id']]);
                            recalculateStudentGWA($db, $sid);
                            $db->prepare("DELETE FROM predictions WHERE student_id = ?")->execute([$sid]);
                            
                            $note = 'Batch Approval: ' . $reason;
                            if ($finalGrade !== $facultyProposed) {
                                $note .= " (Admin modified from proposed $facultyProposed)";
                            }
                            $logStmt->execute([$user['id'], $sid, "grade_{$termType}", $oldVal, $finalGrade, $note]);
                        }
                    }
                    
                    $db->prepare("UPDATE pending_grade_batches SET status='approved', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $batchId]);
                    $success = 'Batch successfully approved and grades have been updated.';
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Batch processing failed: ' . $e->getMessage();
            }
        }
    }
}

// ---------------------------------------------------------
// DATA FETCHING
// ---------------------------------------------------------
$allStudents = $db->query("SELECT u.id, sp.student_number, u.first_name, u.middle_name, u.last_name FROM users u JOIN student_profiles sp ON u.id = sp.user_id")->fetchAll();
$studentDict = [];
foreach ($allStudents as $st) {
    $studentDict[$st['id']] = ['no' => $st['student_number'], 'name' => formatNameLastFirst($st['first_name'], $st['middle_name'], $st['last_name'])];
}

$feedback = $db->query("
    SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, s.code AS subj_code, sp.section
    FROM feedback_reports f JOIN users u ON u.id = f.submitted_by LEFT JOIN subjects s ON s.id = f.subject_id LEFT JOIN student_profiles sp ON sp.user_id = f.submitted_by
    WHERE f.status IN ('open', 'faculty_review', 'awaiting_admin') ORDER BY f.created_at DESC
")->fetchAll();

$historyFeedback = $db->query("
    SELECT f.*, u.first_name, u.last_name, u.role, u.user_id AS identifier, s.code AS subj_code, ru.first_name AS r_first, ru.last_name AS r_last
    FROM feedback_reports f JOIN users u ON u.id = f.submitted_by LEFT JOIN subjects s ON s.id = f.subject_id LEFT JOIN users ru ON ru.id = f.resolved_by
    WHERE f.status IN ('resolved', 'rejected') ORDER BY f.resolved_at DESC
")->fetchAll();

$pending = $db->query("
    SELECT pc.*, u.first_name, u.last_name, au.first_name AS a_first, au.last_name AS a_last
    FROM pending_corrections pc JOIN users au ON au.id = pc.proposed_by LEFT JOIN users u ON u.id = pc.target_id AND pc.target_type != 'grade'
    WHERE pc.status = 'pending' ORDER BY pc.proposed_at ASC
")->fetchAll();

$pendingBatches = $db->query("
    SELECT pgb.*, u.first_name, u.last_name, s.code AS subj_code, s.title AS subj_title
    FROM pending_grade_batches pgb JOIN users u ON u.id = pgb.faculty_id JOIN subjects s ON s.id = pgb.subject_id
    WHERE pgb.status = 'pending' ORDER BY pgb.submitted_at ASC
")->fetchAll();

$logs = $db->query("
    SELECT acl.*, au.first_name AS a_first, au.last_name AS a_last
    FROM admin_change_log acl JOIN users au ON au.id = acl.admin_id
    ORDER BY acl.created_at DESC LIMIT 50
")->fetchAll();

$supportCases = $db->query("
    SELECT asc_case.*, u.first_name, u.last_name, sp.course, sp.year_level
    FROM academic_support_cases asc_case JOIN users u ON asc_case.student_id = u.id JOIN student_profiles sp ON sp.user_id = u.id
    ORDER BY asc_case.created_at DESC LIMIT 50
")->fetchAll();

$countInbox = count($feedback);
$countApprovals = count($pending) + count($pendingBatches);
$countSupportReviews = count(array_filter($supportCases, fn($c) => $c['status'] === 'needs_review'));
$countAwaitingAck = count(array_filter($supportCases, fn($c) => $c['status'] === 'action_taken'));

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

<style>
.tab-btn { background: none; border: none; padding: 12px 24px; font-size: 0.95rem; font-weight: 700; color: var(--text-gray); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; font-family: inherit; }
.tab-btn.active { color: var(--accent-blue); border-bottom-color: var(--accent-blue); }
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Drill-down Hover Animations */
.kpi-drilldown { cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.kpi-drilldown:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
[data-theme="dark"] .kpi-drilldown:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.4); }

.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 40px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; backdrop-filter: blur(4px); }
.modal-overlay.open { opacity: 1; visibility: visible; }
.modal-box { background: var(--card-bg); padding: 24px; border-radius: 12px; width: 100%; max-width: 800px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; margin: auto; transform: scale(0.95) translateY(15px); transition: transform 0.3s ease; border: 1px solid var(--border-color); }
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
.modal-close { position: absolute; top: 20px; right: 20px; background: none; border: 1px solid var(--border-color); color: var(--text-dark); cursor: pointer; font-size: 0.85rem; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-family: inherit; transition: background 0.2s; }
.modal-close:hover { background: var(--bg-color); }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Activity, Approvals & Audit</h1>
            <p style="color: var(--text-gray);">Review incoming reports, process academic-record changes, monitor support cases, and inspect permanent system history.</p>
        </div>
    </div>

    <?php if ($error): ?><p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight: 600;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight: 600;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <!-- KPI STAT GRID -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--accent-blue) !important;" onclick="switchTab('inbox', true)" title="Click to open Inbox">
            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Open Reports</h4>
            <h2 style="margin: 8px 0 0; color: var(--text-dark); font-size: 2rem;"><?= $countInbox ?></h2>
            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Incoming queries</div>
        </div>
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--risk-mod) !important;" onclick="switchTab('approvals', true)" title="Click to open Approvals">
            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Pending Approvals</h4>
            <h2 style="margin: 8px 0 0; color: var(--risk-mod); font-size: 2rem;"><?= $countApprovals ?></h2>
            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Batches & corrections</div>
        </div>
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--risk-high) !important;" onclick="switchTab('support', true)" title="Click to review Academic Support">
            <h4>Support Reviews</h4>
            <h2 style="color: var(--risk-high);"><?= $countSupportReviews ?></h2>
            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Action required</div>
        </div>
        <div class="stat-card kpi-drilldown" style="border-left-color: var(--risk-low) !important;" onclick="switchTab('support', true)" title="Click to view Acknowledged Cases">
            <h4>Awaiting Acknowledgment</h4>
            <h2 style="color: var(--risk-low);"><?= $countAwaitingAck ?></h2>
            <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Notice sent to student</div>
        </div>
    </div>

    <!-- WORKSPACE TABS -->
    <div id="workspace-tabs" style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; overflow-x: auto;">
        <button id="btn-inbox" class="tab-btn active" onclick="switchTab('inbox')">Inbox (<?= $countInbox ?>)</button>
        <button id="btn-approvals" class="tab-btn" onclick="switchTab('approvals')">Approvals (<?= $countApprovals ?>)</button>
        <button id="btn-support" class="tab-btn" onclick="switchTab('support')">Academic Support (<?= $countSupportReviews ?>)</button>
        <button id="btn-history" class="tab-btn" onclick="switchTab('history')">History & Audit</button>
    </div>

    <!-- TAB 1: INBOX -->
    <div id="tab-inbox" class="tab-content active">
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin:0 0 4px 0;">Open Feedback Reports</h3>
                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Student system queries, faculty reports, and unresolved general concerns.</p>
                </div>
            </div>
            <?php if (empty($feedback)): ?>
                <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">✓ All caught up! No open reports.</p>
            <?php else: ?>
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
                                <td style="padding: 12px 24px; font-weight: 600; color: var(--text-dark); vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?><br>
                                    <span style="font-size: 0.75rem; color:var(--text-gray); font-weight:normal;"><?= ucfirst($f['role']) ?></span>
                                </td>
                                <td style="padding: 12px 24px; font-weight: 600; color: var(--accent-blue); vertical-align: top; border-bottom: 1px solid var(--border-color);"><?= ucwords(str_replace('_', ' ', $f['category'])) ?></td>
                                <td style="padding: 12px 24px; color: var(--text-gray); max-width: 250px; vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <div style="font-weight: 600; color: var(--text-dark); font-size: 0.85rem; margin-bottom: 4px;">"<?= htmlspecialchars($f['title'] ?? 'Report') ?>"</div>
                                    <div style="font-size: 0.85rem;"><?= nl2br(htmlspecialchars($f['message'] ?? $f['initial_message'] ?? '')) ?></div>
                                </td>
                                <td style="padding: 12px 24px; text-align:right; vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <div style="display:flex; justify-content: flex-end; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <?php if ($f['status'] === 'awaiting_admin'): ?>
                                            <span style="color: var(--accent-blue); font-size: 0.8rem; font-weight: 600;">Awaiting Fix</span>
                                        <?php elseif ($f['status'] === 'faculty_review'): ?>
                                            <span style="color: var(--risk-mod); font-size: 0.8rem; font-weight: 600;">With Faculty</span>
                                        <?php else: ?>
                                            <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Mark this report as resolved?');" style="margin:0;">
                                                <input type="hidden" name="action" value="resolve_feedback">
                                                <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <button type="submit" style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); border:1px solid rgba(5, 150, 105, 0.3); padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer;">Resolve</button>
                                            </form>
                                            <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Reject this report?');" style="margin:0;">
                                                <input type="hidden" name="action" value="reject_feedback">
                                                <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <button type="submit" style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); border:1px solid rgba(220, 38, 38, 0.3); padding:6px 12px; border-radius:6px; font-weight:600; font-size:0.8rem; cursor:pointer;">Reject</button>
                                            </form>
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
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Grade Batches</h3>
                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Review and officially publish faculty-submitted grades.</p>
                </div>
                <div style="max-height: 400px; overflow-y: auto; padding: 0;">
                    <?php if (empty($pendingBatches)): ?>
                        <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No faculty grade batches pending.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <tbody>
                                <?php foreach ($pendingBatches as $b): ?>
                                <tr>
                                    <td style="padding: 16px 24px; border-bottom: 1px solid var(--border-color);">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <div>
                                                <span style="font-weight:600; color:var(--accent-blue); font-size: 0.95rem;"><?= htmlspecialchars($b['subj_code']) ?> — <?= htmlspecialchars($b['section']) ?></span><br>
                                                <span style="color:var(--text-dark); font-size: 0.9rem;"><strong><?= ucfirst($b['term_type']) ?></strong> Grades</span>
                                            </div>
                                            <div style="text-align: right;">
                                                <button onclick="document.getElementById('batch-modal-<?= $b['id'] ?>').classList.add('open')" style="background:rgba(30, 77, 183, 0.1); color:var(--accent-blue); border:1px solid rgba(30, 77, 183, 0.2); padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer;">Review Batch</button>
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
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Individual Corrections</h3>
                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Approve or reject faculty-submitted grade fixes.</p>
                </div>
                <div style="max-height: 400px; overflow-y: auto; padding: 0;">
                    <?php if (empty($pending)): ?>
                        <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No individual corrections pending.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <tbody>
                                <?php foreach ($pending as $p): ?>
                                <tr>
                                    <td style="padding: 16px 24px; border-bottom: 1px solid var(--border-color);">
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <div>
                                                <span style="font-weight:600; color:var(--risk-mod); font-size: 0.9rem;"><?= htmlspecialchars(strtoupper($p['target_type'])) ?>: <?= htmlspecialchars(strtoupper($p['field_changed'])) ?></span><br>
                                                <span style="color:var(--text-gray); font-size: 0.9rem;"><?= htmlspecialchars($p['old_value'] ?: '(empty)') ?> &rarr; <strong style="color:var(--text-dark);"><?= htmlspecialchars($p['new_value']) ?></strong></span>
                                            </div>
                                            <div style="display:flex; gap: 8px;">
                                                <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Confirm this correction?');" style="margin:0;">
                                                    <input type="hidden" name="action" value="confirm_correction">
                                                    <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <button type="submit" style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); border:1px solid rgba(5, 150, 105, 0.3); padding:6px 12px; border-radius:6px; font-weight:600;">Confirm</button>
                                                </form>
                                                <form method="POST" action="activity.php" class="safe-submit-form" onsubmit="return confirm('Reject this correction?');" style="margin:0;">
                                                    <input type="hidden" name="action" value="reject_correction">
                                                    <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <button type="submit" style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); border:1px solid rgba(220, 38, 38, 0.3); padding:6px 12px; border-radius:6px; font-weight:600;">Reject</button>
                                                </form>
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
        </div>
    </div>

    <!-- TAB 3: ACADEMIC SUPPORT -->
    <div id="tab-support" class="tab-content">
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                <div>
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Academic Support Oversight</h3>
                    <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Read-only oversight of system-flagged cases and faculty interventions.</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="export_intervention_audit.php" style="background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); padding: 8px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Export CSV
                    </a>
                    <a href="export_intervention_audit_pdf.php" style="background: var(--accent-blue); color: white; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; transition: opacity 0.2s;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        Download PDF
                    </a>
                </div>
            </div>
            <div style="max-height: 500px; overflow-y: auto; padding: 0;">
                <?php if (empty($supportCases)): ?>
                    <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No support cases have been generated.</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th style="position: sticky; top: 0; background: var(--card-bg); z-index: 5; box-shadow: inset 0 -2px 0 var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">Status</th>
                                <th style="position: sticky; top: 0; background: var(--card-bg); z-index: 5; box-shadow: inset 0 -2px 0 var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">Student</th>
                                <th style="position: sticky; top: 0; background: var(--card-bg); z-index: 5; box-shadow: inset 0 -2px 0 var(--border-color); padding: 12px 24px; text-align: left; color: var(--text-dark);">System Context</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supportCases as $sc): ?>
                            <tr>
                                <td style="padding: 12px 24px; vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <?php if ($sc['status'] === 'needs_review'): ?><span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding:4px 8px; border-radius:6px; font-weight:700;">NEEDS REVIEW</span>
                                    <?php elseif ($sc['status'] === 'action_taken'): ?><span style="background:rgba(30, 77, 183, 0.1); color:var(--accent-blue); padding:4px 8px; border-radius:6px; font-weight:700;">NOTICE SENT</span>
                                    <?php elseif ($sc['status'] === 'acknowledged'): ?><span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:4px 8px; border-radius:6px; font-weight:700;">ACKNOWLEDGED</span>
                                    <?php else: ?><span style="background:var(--bg-color); border: 1px solid var(--border-color); color:var(--text-gray); padding:4px 8px; border-radius:6px; font-weight:700;">CLOSED</span><?php endif; ?>
                                </td>
                                <td style="padding: 12px 24px; font-weight: 600; color: var(--text-dark); vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <?= htmlspecialchars($sc['first_name'] . ' ' . $sc['last_name']) ?>
                                </td>
                                <td style="padding: 12px 24px; vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                    <span style="font-weight: 600; color: var(--risk-high);"><?= htmlspecialchars($sc['trigger_risk_level']) ?> Risk</span>
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
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">Closed Feedback History</h3>
                </div>
                <div style="max-height: 350px; overflow-y: auto; padding: 0;">
                    <?php if (empty($historyFeedback)): ?>
                        <p style="color: var(--text-gray); font-size: 0.9rem; padding: 20px 24px;">No closed feedback records found.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <tbody>
                                <?php foreach ($historyFeedback as $hf): ?>
                                <tr>
                                    <td style="padding: 12px 24px; vertical-align: top; border-bottom: 1px solid var(--border-color);">
                                        <?php if ($hf['status'] === 'resolved'): ?><span style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:3px 8px; border-radius:6px; font-weight:700;">Resolved</span>
                                        <?php else: ?><span style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:3px 8px; border-radius:6px; font-weight:700;">Rejected</span><?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 24px; color: var(--text-gray); border-bottom: 1px solid var(--border-color);"><?= date('M j, Y', strtotime($hf['resolved_at'] ?? $hf['updated_at'])) ?></td>
                                    <td style="padding: 12px 24px; font-weight: 600; color: var(--accent-blue); border-bottom: 1px solid var(--border-color);"><?= ucwords(str_replace('_', ' ', $hf['category'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h3 style="color: var(--text-dark); font-size: 1.05rem; font-weight: 700; margin: 0 0 4px 0;">System Audit Log</h3>
                        <p style="font-size: 0.85rem; color: var(--text-gray); margin: 0;">Permanent administrative record of all data modifications.</p>
                    </div>
                </div>
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

<!-- Modals for Batch Reviews -->
<?php foreach ($pendingBatches as $b): 
    $payloadData = json_decode($b['payload'], true);
?>
<div class="modal-overlay" id="batch-modal-<?= $b['id'] ?>" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="document.getElementById('batch-modal-<?= $b['id'] ?>').classList.remove('open')">✕ Close</button>
        <h2 style="color:var(--text-dark); margin-top:0; margin-bottom:4px;">Review Grade Batch</h2>
        <form method="POST" action="activity.php" class="safe-submit-form">
            <input type="hidden" name="action" value="approve_batch">
            <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="background:var(--bg-color); border:1px solid var(--border-color); border-radius:8px; overflow:hidden; margin-bottom:20px; max-height:400px; overflow-y:auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <tbody>
                        <?php foreach ($payloadData as $item): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px 16px;">
                                <input type="text" name="approved_grades[<?= $item['student_id'] ?>]" value="<?= htmlspecialchars($item['grade']) ?>" style="width: 70px; padding: 6px; border: 1px solid var(--border-color); border-radius: 4px; text-align: center; font-weight: bold; font-family: inherit;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content: flex-end; gap: 12px;">
                <button type="submit" formaction="activity.php" name="action" value="reject_batch" style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); border:1px solid rgba(220, 38, 38, 0.3); padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Reject Batch</button>
                <button type="submit" style="background:var(--accent-blue); color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Approve & Apply</button>
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

    if (allowedTabs.includes(requestedTab)) {
        switchTab(requestedTab, false);
    }
});

document.querySelectorAll('.safe-submit-form').forEach(f => {
    f.addEventListener('submit', function() {
        const btns = this.querySelectorAll('button[type="submit"]');
        setTimeout(() => btns.forEach(b => { b.style.pointerEvents = 'none'; b.style.opacity = '0.7'; }), 10);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>