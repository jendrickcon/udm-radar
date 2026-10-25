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

// --- ADMIN VALIDATION & RECALCULATION HELPERS ---
function validateAdminGrade($termType, $val): bool {
    if ($val === null || trim((string)$val) === '') return true;
    if (in_array($termType, ['prelim', 'midterm', 'prefinal'], true)) {
        return isValidTermPercentage($val);
    } elseif ($termType === 'final_grade') {
        return isValidFinalGradeEntry($val);
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
            if (isFailingFinalGrade($latestVal)) return 'HIGH';
            if (isNumericFinalGrade($latestVal)) return computeRiskFromAvg((float)$latestVal);
        } else {
            if (is_numeric($latestVal)) {
                $pt = normalizeTermGrade((float)$latestVal);
                if ($pt !== null) return computeRiskFromAvg($pt);
            }
        }
    }
    return null;
}

// ------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'propose_grade') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh and try again.';
    } else {
        $gradeId = (int) ($_POST['grade_id'] ?? 0);
        $reason  = trim($_POST['reason'] ?? '');
        $openSec = trim($_POST['open_section'] ?? '');
        $openStu = (int) ($_POST['open_student'] ?? 0);
        $linkedFeedbackId = !empty($_POST['linked_feedback_id']) ? (int) $_POST['linked_feedback_id'] : null;

        if ($reason === '') {
            $error = 'A reason is required to propose a grade correction.';
        } else {
            $stmt = $db->prepare("SELECT * FROM grades WHERE id = ?");
            $stmt->execute([$gradeId]);
            $old = $stmt->fetch();

            if (!$old) {
                $error = 'Grade record not found.';
            } else {
                $rawFinal = $_POST['edit_final'] !== '' ? trim($_POST['edit_final']) : null;
                $normFinal = $rawFinal !== null ? normalizeFinalGradeInput($rawFinal) : null;
                $fields = [
                    'prelim'      => $_POST['edit_prelim']   !== '' ? $_POST['edit_prelim']   : null,
                    'midterm'     => $_POST['edit_midterm']  !== '' ? $_POST['edit_midterm']  : null,
                    'prefinal'    => $_POST['edit_prefinal'] !== '' ? $_POST['edit_prefinal'] : null,
                    'final_grade' => $normFinal !== null ? $normFinal : $rawFinal,
                ];
                
                // FIXED: Validation of Admin Proposals BEFORE entering the database
                foreach ($fields as $field => $newVal) {
                    if ($newVal !== null && !validateAdminGrade($field, $newVal)) {
                        $error = "Validation Error: Invalid value provided for $field. Percentages must be 0-100; Final Grades must use the 1.00-4.00 scale or valid status.";
                        break;
                    }
                }

                if (!$error) {
                    $logStmt = $db->prepare("
                        INSERT INTO pending_corrections (proposed_by, target_type, target_id, field_changed, old_value, new_value, reason, linked_feedback_id)
                        VALUES (?, 'grade', ?, ?, ?, ?, ?, ?)
                    ");
                    $proposedCount = 0;
                    foreach ($fields as $field => $newVal) {
                        $oldVal = $old[$field];
                        if ((string) $oldVal !== (string) $newVal) {
                            $logStmt->execute([$user['id'], $gradeId, $field, $oldVal, $newVal, $reason, $linkedFeedbackId]);
                            $proposedCount++;
                        }
                    }

                    $success = $proposedCount > 0
                        ? "{$proposedCount} field(s) proposed for correction — pending confirmation."
                        : 'No changes proposed.';
                }
            }
        }

        if (!$error) {
            header('Location: grades.php?open_section=' . urlencode($openSec) . '&open_student=' . $openStu . '&msg=' . urlencode($success ?? ''));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['confirm_correction', 'reject_correction'])) {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh and try again.';
    } else {
        $corrId = (int) ($_POST['correction_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM pending_corrections WHERE id = ? AND status = 'pending'");
        $stmt->execute([$corrId]);
        $corr = $stmt->fetch();

        if (!$corr) {
            $error = 'Correction not found or already resolved.';
        } elseif (($_POST['action'] ?? '') === 'reject_correction') {
            $db->prepare("UPDATE pending_corrections SET status='rejected', resolved_by=?, resolved_at=NOW() WHERE id=?")
               ->execute([$user['id'], $corrId]);
            $success = 'Correction rejected — no change applied.';
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("SELECT * FROM grades WHERE id = ?");
                $stmt->execute([$corr['target_id']]);
                $gradeRow = $stmt->fetch();

                if (!$gradeRow) {
                    throw new Exception('Underlying grade record no longer exists.');
                }

                $field  = $corr['field_changed'];
                $newVal = $corr['new_value'];
                
                // FIXED: Strict Grade Validation upon confirmation
                if (!validateAdminGrade($field, $newVal)) {
                    throw new Exception("Validation Error: Invalid value '{$newVal}'. Percentages must be 0-100; Final Grades must use the 1.00-4.00 scale or valid status.");
                }

                $valToStore = $field === 'final_grade' ? normalizeFinalGradeInput($newVal) : $newVal;
                $db->prepare("UPDATE grades SET `$field` = ? WHERE id = ?")->execute([$valToStore, $corr['target_id']]);
                
                // FIXED: Safely recalculate risk based on the latest available term for this row
                $newRisk = recalculateGradeRowRisk($db, $corr['target_id']);
                if ($newRisk !== null) {
                    $db->prepare("UPDATE grades SET risk_level = ? WHERE id = ?")->execute([$newRisk, $corr['target_id']]);
                }

                // Automatically calculate new GWA only when final_grade is modified
                if ($field === 'final_grade') {
                    recalculateStudentGwa($db, (int) $gradeRow['student_id']);
                }
                $db->prepare("DELETE FROM predictions WHERE student_id = ?")->execute([$gradeRow['student_id']]);

                $db->prepare("UPDATE pending_corrections SET status='confirmed', resolved_by=?, resolved_at=NOW() WHERE id=?")
                   ->execute([$user['id'], $corrId]);

                if ($corr['linked_feedback_id'] !== null) {
                    $db->prepare("UPDATE feedback_reports SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE id=?")
                       ->execute([$user['id'], $corr['linked_feedback_id']]);
                }

                $db->prepare("
                    INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value, note, linked_feedback_id)
                    VALUES (?, 'grade', ?, ?, ?, ?, ?, ?)
                ")->execute([$user['id'], $corr['target_id'], $field, $corr['old_value'], $newVal, 'Confirmed correction: ' . $corr['reason'], $corr['linked_feedback_id']]);

                $db->commit();
                $success = 'Correction confirmed and officially reflected.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Could not confirm correction: ' . $e->getMessage();
            }
        }
    }
}


$success = $_GET['msg'] ?? $success ?? '';
$openSection = trim($_GET['open_section'] ?? '');
$openStudent = (int) ($_GET['open_student'] ?? 0);
$openFeedbackId = (int) ($_GET['feedback_id'] ?? 0);

$sections = $db->query("
    SELECT sp.section,
           COUNT(*) AS student_count,
           AVG(sp.current_gwa) AS avg_gwa,
           SUM(CASE WHEN p.risk_level IN ('MODERATE','HIGH') THEN 1 ELSE 0 END) AS at_risk_count
    FROM student_profiles sp
    LEFT JOIN predictions p ON p.student_id = sp.user_id
        AND p.generated_at = (SELECT MAX(p2.generated_at) FROM predictions p2 WHERE p2.student_id = sp.user_id)
    WHERE sp.section IS NOT NULL AND sp.section != '' AND sp.record_status = 'Active'
    GROUP BY sp.section
    ORDER BY sp.section
")->fetchAll();

$pending = $db->query("
    SELECT pc.*, g.student_id, s.code AS subj_code,
           su.first_name, su.middle_name, su.last_name,
           au.first_name AS a_first, au.middle_name AS a_middle, au.last_name AS a_last
    FROM pending_corrections pc
    JOIN grades g ON g.id = pc.target_id AND pc.target_type = 'grade'
    JOIN subjects s ON s.id = g.subject_id
    JOIN users su ON su.id = g.student_id
    JOIN users au ON au.id = pc.proposed_by
    WHERE pc.status = 'pending'
    ORDER BY pc.proposed_at DESC
")->fetchAll();

$pageTitle = 'Grades';
$navItems = [
    ['Dashboard',          'index.php',     ''],
    ['Students',           'students.php',  ''],
    ['Faculty',            'faculty.php',   ''],
    ['Grades',             'grades.php',    ''],
    ['Program Analytics',  'analytics.php', ''],
    ['Activity & Inbox',   'activity.php',  ''],
    ['Settings',           'settings.php',  ''],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.section-card { padding:0; overflow:hidden; border:1px solid var(--border-color); background: var(--card-bg); border-radius:8px; cursor:pointer; transition:box-shadow .15s; }
.section-card:hover { box-shadow:0 4px 14px rgba(0,0,0,0.08); }
.section-card-head { background:var(--sidebar-bg); color:white; padding:14px 18px; font-weight:600; font-size:1.05rem; display:flex; justify-content:space-between; }
.section-card-body { padding:24px; text-align:center; }
.section-card-body h2 { font-size:2.4rem !important; }

.roster-panel { display:none; }
.row-clickable { cursor:pointer; }
.row-clickable:hover { background:var(--bg-color); }

.pending-badge { background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:700; white-space:nowrap; }

/* Reworked Student Modal */
.history-modal-box {
    width: min(920px, calc(100vw - 32px)) !important;
    max-width: 920px !important;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    box-sizing: border-box;
    padding: 24px;
}
.modal-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: 16px 0;
}
.modal-stat {
    background: var(--bg-color);
    border-radius: 8px;
    padding: 10px 12px;
    text-align: center;
    border: 1px solid var(--border-color);
}
.modal-stat span {
    display: block;
    font-size: 0.72rem;
    color: var(--text-gray);
    margin-bottom: 4px;
}
.modal-stat strong {
    font-size: 1.15rem;
    color: var(--text-dark);
}
.grades-modal-scroll {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    overscroll-behavior-inline: contain;
    margin-bottom: 6px;
}
.grades-table-header {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) repeat(3, 68px) 74px 96px 145px;
    gap: 8px;
    align-items: center;
    padding: 8px 10px;
    background: var(--table-header-bg, var(--bg-color));
    border-bottom: 2px solid var(--border-color);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-dark);
    min-width: 710px;
}
.grade-row-form {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) repeat(3, 68px) 74px 96px 145px;
    gap: 8px;
    align-items: center;
    padding: 8px 10px;
    border-bottom: 1px solid var(--border-color);
    min-width: 710px;
}
.grade-row-form input[type=text] {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    font-size: 0.82rem;
    background: var(--bg-color);
    color: var(--text-dark);
    text-align: center;
    box-sizing: border-box;
}
.grade-row-form input[type=text]:focus {
    border-color: var(--accent-blue);
    outline: none;
    background: var(--card-bg);
}
.grade-row-subject {
    min-width: 0;
}
.grade-row-subject strong {
    display: inline-block;
    color: var(--text-dark);
    font-size: 0.85rem;
}
.grade-row-subject .subject-title {
    display: block;
    font-size: 0.78rem;
    color: var(--text-gray);
    line-height: 1.25;
    word-break: break-word;
}
.grade-row-risk {
    text-align: center;
    white-space: nowrap;
}
.grade-row-risk .risk-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
    line-height: 1;
}
.grade-row-action {
    text-align: right;
    white-space: nowrap;
}
.grade-row-action .small-btn {
    padding: 6px 12px;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    font-size: 0.78rem;
    cursor: pointer;
    background: var(--accent-blue);
    color: white;
    white-space: nowrap;
    transition: opacity 0.15s;
    font-family: inherit;
}
.grade-row-action .small-btn:hover {
    opacity: 0.9;
}
.history-toggle-btn {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    color: var(--accent-blue);
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
}
.history-toggle-btn:hover {
    border-color: var(--accent-blue);
    background: var(--card-bg);
}
.history-wrapper {
    overflow: hidden;
    transition: max-height 0.3s ease;
}
@media (max-width: 600px) {
    .modal-stat-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .history-modal-box {
        padding: 16px !important;
        width: calc(100vw - 20px) !important;
    }
}
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Grades</h1><p>UdM-RADAR does not own official grade records. Corrections are proposed here, then confirmed once officially reflected in the registrar's system.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high);"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low);"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (!empty($pending)): ?>
    <div class="card" style="border-left:4px solid var(--risk-mod); margin-bottom:24px;">
        <div class="table-title" style="display:flex; align-items:center; gap:8px;">
            Pending Grade Corrections
            <span class="pending-badge"><?= count($pending) ?> awaiting confirmation</span>
        </div>
        <div class="data-table-scroll" role="region" aria-label="Pending Grade Corrections Table" tabindex="0">
            <table style="width: 100%; border-collapse: collapse;">
                <caption class="sr-only">Pending Grade Corrections Table</caption>
                <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Field</th>
                        <th scope="col">Was</th>
                        <th scope="col">Proposed</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Proposed By</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p):
                        $studentName = formatNameLastFirst($p['first_name'], $p['middle_name'], $p['last_name']);
                        $adminName   = formatNameLastFirst($p['a_first'], $p['a_middle'], $p['a_last']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($studentName) ?></td>
                        <td><?= htmlspecialchars($p['subj_code']) ?></td>
                        <td><?= htmlspecialchars($p['field_changed']) ?></td>
                        <td><?= htmlspecialchars($p['old_value'] ?? '—') ?></td>
                        <td style="font-weight:700; color:var(--risk-mod);"><?= htmlspecialchars($p['new_value']) ?></td>
                        <td style="font-size:0.82rem; color:var(--text-gray);"><?= htmlspecialchars($p['reason']) ?></td>
                        <td style="font-size:0.82rem;"><?= htmlspecialchars($adminName) ?></td>
                        <td style="white-space:nowrap;">
                            <form method="POST" action="grades.php" style="display:inline;" onsubmit="return confirm('Mark this correction as officially reflected? This will update the live grade record.');">
                                <input type="hidden" name="action" value="confirm_correction">
                                <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="small-btn" style="background:var(--risk-low); color:white;">Confirm</button>
                            </form>
                            <form method="POST" action="grades.php" style="display:inline;" onsubmit="return confirm('Reject this proposed correction?');">
                                <input type="hidden" name="action" value="reject_correction">
                                <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="small-btn" style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high);">Reject</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="stat-grid section-card-grid">
        <?php foreach ($sections as $sec):
            $avg = $sec['avg_gwa'] !== null ? number_format($sec['avg_gwa'], 2) : '—';
            $riskColor = $sec['at_risk_count'] > 0 ? 'var(--risk-high)' : 'var(--risk-low)';
        ?>
        <button type="button" class="section-card" data-section="<?= htmlspecialchars($sec['section']) ?>" aria-controls="roster-panel" aria-expanded="false" onclick="openSection('<?= htmlspecialchars($sec['section']) ?>', this)">
            <div class="section-card-head">
                <span><?= htmlspecialchars($sec['section']) ?></span>
                <span style="font-size:0.8rem; color:var(--text-gray);"><?= $sec['student_count'] ?> students</span>
            </div>
            <div class="section-card-body">
                <h2 style="color:var(--accent-blue); font-size:1.8rem; margin-bottom:2px;"><?= $avg ?></h2>
                <p style="color:var(--text-gray); font-size:0.8rem; margin-bottom:8px;">Avg GWA</p>
                <div style="font-size:0.8rem; color:<?= $riskColor ?>; font-weight:600;">
                    <?= $sec['at_risk_count'] ?> at-risk
                </div>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="data-table-card data-table-card--active roster-panel" id="roster-panel">
        <div class="data-table-header">
            <div class="data-table-header__intro">
                <h3 class="data-table-title" id="roster-title" tabindex="-1"></h3>
            </div>
            <div class="data-table-actions">
                <button type="button" onclick="closeRoster()" class="btn btn--secondary btn--sm" id="roster-close-btn">Close</button>
            </div>
        </div>
        <div id="roster-status-container" style="display:none;"></div>
        <div class="data-table-scroll" id="roster-table-scroll" role="region" aria-label="Section Student Roster" tabindex="0">
            <table class="data-table table-density--standard">
                <caption class="sr-only">Section Student Roster</caption>
                <thead>
                    <tr>
                        <th scope="col">Student No.</th>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">GWA</th>
                        <th scope="col">Risk</th>
                    </tr>
                </thead>
                <tbody id="roster-body"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- STUDENT GRADES & HISTORY MODAL -->
<div class="modal-overlay" id="history-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="history-name" onclick="if(event.target===this) closeHistoryModal();">
    <div class="modal-box history-modal-box">
        <button class="modal-close" id="history-modal-close-btn" onclick="closeHistoryModal()" aria-label="Close dialog">Close</button>
        <h2 id="history-name" style="color:var(--text-dark); margin-bottom:2px;"></h2>
        <p id="history-subline" style="color:var(--text-gray); font-size:0.88rem; margin-bottom:12px;"></p>

        <div class="modal-stat-grid">
            <div class="modal-stat">
                <span>Cumulative GWA (Historical)</span>
                <strong id="history-modal-gwa">—</strong>
            </div>
            <div class="modal-stat">
                <span>Projected Term GWA</span>
                <strong id="history-modal-predicted">—</strong>
            </div>
            <div class="modal-stat">
                <span>Advisory Risk Level</span>
                <strong id="history-modal-risk">—</strong>
            </div>
        </div>

        <div style="margin-top:16px;">
            <h3 style="color:var(--text-dark); font-size:0.95rem; margin-bottom:6px; font-weight:700;">
                Current Semester Grades and Correction Proposals
            </h3>
            <p style="color:var(--text-gray); font-size:0.8rem; margin-bottom:10px;">
                Grades are not directly editable in this view. Submitting a correction proposal applies the change only after confirmation and audit recording.
            </p>

            <div class="data-table-scroll grades-modal-scroll" role="region" aria-label="Current Semester Grades and Correction Proposals" tabindex="0">
                <div class="grades-table-header">
                    <div>Subject</div>
                    <div style="text-align:center;">Prelim</div>
                    <div style="text-align:center;">Midterm</div>
                    <div style="text-align:center;">Pre-Final</div>
                    <div style="text-align:center;">Official Final</div>
                    <div style="text-align:center;">Advisory Risk</div>
                    <div style="text-align:right;"><span class="sr-only">Actions</span></div>
                </div>
                <div id="history-body"></div>
            </div>
            <p style="font-size:0.78rem; color:var(--text-gray); margin-top:8px; margin-bottom:0;">
                Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.
            </p>
        </div>

        <div style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:14px;">
            <button type="button" id="modal-history-toggle" class="history-toggle-btn" aria-expanded="false" aria-controls="modal-history-container" onclick="toggleGradeHistory()">
                <span id="history-toggle-icon" aria-hidden="true" style="display:inline-block; transition:transform 0.2s ease; margin-right:4px;">▶</span>
                <span id="history-toggle-text">View Grade History</span>
            </button>

            <div id="modal-history-wrapper" class="history-wrapper" style="max-height:0px; overflow:hidden; transition:max-height 0.3s ease;">
                <div id="modal-history-container" style="padding-top:12px;"></div>
            </div>
        </div>

        <div style="margin-top:16px; padding-top:12px; border-top:1px dashed var(--border-color); display:flex; justify-content:flex-end;">
            <a id="modal-full-record-link" href="#" class="btn btn--quiet btn--sm" style="display:inline-flex; align-items:center; gap:6px;">
                Open Full Student Record &rarr;
            </a>
        </div>
    </div>
</div>

<script>
let openSectionName = <?= json_encode($openSection) ?>;
let openStudentId   = <?= json_encode($openStudent) ?>;
let openFeedbackId  = <?= json_encode($openFeedbackId) ?>;
const csrfToken     = <?= json_encode($_SESSION['csrf_token']) ?>;

let activeTriggerEl       = null;
let currentRosterSection  = null;
let rosterAbortController = null;
let rosterRequestId       = 0;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function riskColor(risk) {
    if (risk === 'HIGH') return 'var(--risk-high)';
    if (risk === 'MODERATE') return 'var(--risk-mod)';
    if (risk === 'LOW') return 'var(--risk-low)';
    return 'var(--text-gray)';
}

function openSection(section, triggerEl) {
    openSectionName = section;

    if (!triggerEl) {
        try {
            triggerEl = document.querySelector('.section-card[data-section="' + CSS.escape(section) + '"]')
                     || document.querySelector('.section-card[data-section="' + section + '"]');
        } catch (e) {
            triggerEl = document.querySelector('.section-card[data-section="' + section + '"]');
        }
    }

    if (activeTriggerEl && activeTriggerEl !== triggerEl) {
        activeTriggerEl.setAttribute('aria-expanded', 'false');
        activeTriggerEl.classList.remove('section-card--active');
        const prevBadge = activeTriggerEl.querySelector('.section-card-active-indicator');
        if (prevBadge) prevBadge.remove();
    }

    activeTriggerEl = triggerEl;
    currentRosterSection = section;

    if (activeTriggerEl) {
        activeTriggerEl.setAttribute('aria-expanded', 'true');
        activeTriggerEl.classList.add('section-card--active');
        if (!activeTriggerEl.querySelector('.section-card-active-indicator')) {
            const head = activeTriggerEl.querySelector('.section-card-head');
            if (head) {
                const indicator = document.createElement('span');
                indicator.className = 'section-card-active-indicator';
                indicator.setAttribute('aria-hidden', 'true');
                indicator.textContent = '● Open';
                head.appendChild(indicator);
            }
        }
    }

    if (rosterAbortController) {
        rosterAbortController.abort();
    }
    const thisRequestId = ++rosterRequestId;
    rosterAbortController = new AbortController();

    const panel = document.getElementById('roster-panel');
    const title = document.getElementById('roster-title');
    const statusContainer = document.getElementById('roster-status-container');
    const tableScroll = document.getElementById('roster-table-scroll');

    panel.style.display = 'block';
    title.textContent = 'Section ' + section + ' — Student Roster';

    statusContainer.style.display = 'block';
    statusContainer.innerHTML = `
        <div class="roster-state roster-state--loading" role="status" aria-live="polite">
            <span class="roster-state-spinner" aria-hidden="true"></span>
            <span>Loading Section ${escapeHtml(section)} student roster...</span>
        </div>
    `;
    tableScroll.style.display = 'none';

    if (window.UDM && window.UDM.announce) {
        window.UDM.announce('Loading Section ' + section + ' student roster');
    }

    if (window.UDM && window.UDM.scrollToTarget) {
        window.UDM.scrollToTarget(panel, { focusTarget: title });
    }

    fetch('grades_data.php?action=students&section=' + encodeURIComponent(section), {
        signal: rosterAbortController.signal
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(students => {
        if (thisRequestId !== rosterRequestId) return;

        statusContainer.style.display = 'none';
        tableScroll.style.display = 'block';

        const body = document.getElementById('roster-body');
        if (!students || !students.length) {
            body.innerHTML = '<tr><td colspan="5" class="roster-empty-cell">No students are currently available for this section.</td></tr>';
            if (window.UDM && window.UDM.announce) {
                window.UDM.announce('Section ' + section + ' roster is empty. No students available.');
            }
        } else {
            body.innerHTML = students.map(s => {
                const escapedName = (s.name || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return `
                <tr>
                    <td>${escapeHtml(s.studentNo)}</td>
                    <td style="font-weight:600;">
                        <button type="button" class="table-record-link" onclick="openHistory(${s.userId}, '${(s.name || '').replace(/'/g,"\\'")}')" aria-label="View grade history for ${escapedName}">
                            ${escapeHtml(s.name)}
                        </button>
                    </td>
                    <td>${escapeHtml(s.status || 'Regular')}</td>
                    <td style="font-weight:600;">${s.currentGwa !== null && s.currentGwa !== undefined ? Number(s.currentGwa).toFixed(2) : '—'}</td>
                    <td><span style="background:${riskColor(s.risk)}; color:white; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700;">${escapeHtml(s.risk || 'N/A')}</span></td>
                </tr>`;
            }).join('');

            if (window.UDM && window.UDM.announce) {
                window.UDM.announce('Section ' + section + ' student roster loaded with ' + students.length + ' students');
            }
        }

        if (openStudentId) {
            const target = students.find(s => s.userId === openStudentId);
            if (target) openHistory(openStudentId, target.name);
            openStudentId = 0;
        }
    })
    .catch(err => {
        if (err.name === 'AbortError') return;
        if (thisRequestId !== rosterRequestId) return;

        statusContainer.style.display = 'block';
        tableScroll.style.display = 'none';
        statusContainer.innerHTML = `
            <div class="roster-state roster-state--error" role="alert">
                <p class="roster-state-msg">The roster could not be loaded.</p>
                <button type="button" class="btn btn--secondary btn--sm roster-retry-btn" id="roster-retry-btn" onclick="retrySection()">Retry</button>
            </div>
        `;

        if (window.UDM && window.UDM.announce) {
            window.UDM.announce('The roster could not be loaded. A retry option is available.', 'assertive');
        }
    });
}

function retrySection() {
    if (currentRosterSection) {
        openSection(currentRosterSection, activeTriggerEl);
    }
}

function closeRoster() {
    if (rosterAbortController) {
        rosterAbortController.abort();
        rosterAbortController = null;
    }
    rosterRequestId++;

    const panel = document.getElementById('roster-panel');
    panel.style.display = 'none';

    const statusContainer = document.getElementById('roster-status-container');
    if (statusContainer) statusContainer.style.display = 'none';

    const returningTrigger = activeTriggerEl;
    if (activeTriggerEl) {
        activeTriggerEl.setAttribute('aria-expanded', 'false');
        activeTriggerEl.classList.remove('section-card--active');
        const badge = activeTriggerEl.querySelector('.section-card-active-indicator');
        if (badge) badge.remove();
        activeTriggerEl = null;
    }
    currentRosterSection = null;

    if (returningTrigger && typeof returningTrigger.focus === 'function') {
        try {
            returningTrigger.focus({ preventScroll: true });
        } catch (e) {
            returningTrigger.focus();
        }
    }
}

let currentModalStudentId = null;
let currentStudentHistory = null;
let isHistoryLoading      = false;
let modalActiveTrigger    = null;
let modalFocusFrame       = null;

function openHistory(studentId, name) {
    currentModalStudentId = studentId;
    currentStudentHistory = null;
    isHistoryLoading      = false;
    modalActiveTrigger    = document.activeElement;

    // Reset history toggle and container
    const wrapper = document.getElementById('modal-history-wrapper');
    const container = document.getElementById('modal-history-container');
    const toggle = document.getElementById('modal-history-toggle');
    const icon = document.getElementById('history-toggle-icon');
    const text = document.getElementById('history-toggle-text');

    if (wrapper) wrapper.style.maxHeight = '0px';
    if (container) container.innerHTML = '';
    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.removeAttribute('disabled');
    }
    if (icon) icon.textContent = '▶';
    if (text) text.textContent = 'View Grade History';

    // Initial placeholder while fetching fresh data
    document.getElementById('history-name').textContent = name || 'Student Grades';
    document.getElementById('history-subline').textContent = 'Loading student record...';
    document.getElementById('history-modal-gwa').textContent = '—';
    document.getElementById('history-modal-predicted').textContent = '—';
    const riskEl = document.getElementById('history-modal-risk');
    riskEl.textContent = '—';
    riskEl.style.color = 'var(--text-gray)';

    document.getElementById('history-body').innerHTML = `
        <div class="roster-state roster-state--loading" role="status" aria-live="polite" style="padding:20px; text-align:center;">
            <span class="roster-state-spinner" aria-hidden="true"></span>
            <span>Loading current semester grades...</span>
        </div>
    `;

    const modal = document.getElementById('history-modal-overlay');
    modal.classList.add('open');
    const closeBtn = document.getElementById('history-modal-close-btn');
    if (modalFocusFrame !== null) cancelAnimationFrame(modalFocusFrame);
    if (closeBtn) {
        // Opening transitions can leave the control hidden for several frames.
        // Focus only once it can receive focus, and stop if the dialog closes.
        const focusWhenVisible = () => {
            modalFocusFrame = null;
            if (!modal.classList.contains('open')) return;
            if (getComputedStyle(closeBtn).visibility === 'visible' && closeBtn.getClientRects().length) {
                if (!modal.contains(document.activeElement)) closeBtn.focus();
            } else {
                modalFocusFrame = requestAnimationFrame(focusWhenVisible);
            }
        };
        modalFocusFrame = requestAnimationFrame(focusWhenVisible);
    }

    fetch('grades_data.php?action=student_detail&student_id=' + studentId)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);

            const stu = data.student;
            document.getElementById('history-name').textContent = stu.name;
            document.getElementById('history-subline').textContent = `${stu.studentNo} · Section ${stu.section || '—'} · Year ${stu.yearLevel || '—'} · ${stu.status || 'Regular'}`;

            document.getElementById('history-modal-gwa').textContent = stu.currentGwa !== null ? Number(stu.currentGwa).toFixed(2) : '—';
            document.getElementById('history-modal-predicted').textContent = stu.predictedGwa !== null ? Number(stu.predictedGwa).toFixed(2) : '—';

            const stuRisk = (stu.risk || '').toUpperCase();
            if (stuRisk === 'HIGH') {
                riskEl.textContent = 'HIGH';
                riskEl.style.color = 'var(--risk-high)';
            } else if (stuRisk === 'MODERATE') {
                riskEl.textContent = 'MODERATE';
                riskEl.style.color = 'var(--risk-mod)';
            } else if (stuRisk === 'LOW') {
                riskEl.textContent = 'LOW';
                riskEl.style.color = 'var(--risk-low)';
            } else {
                riskEl.textContent = 'No Data';
                riskEl.style.color = 'var(--text-gray)';
            }

            const recordLink = document.getElementById('modal-full-record-link');
            if (recordLink) {
                recordLink.href = 'students.php?search=' + encodeURIComponent(stu.studentNo);
            }

            const grades = data.grades || [];
            if (!grades.length) {
                document.getElementById('history-body').innerHTML = '<p style="color:var(--text-gray); text-align:center; padding:20px;">No current-term grades for this student.</p>';
            } else {
                document.getElementById('history-body').innerHTML = grades.map(g => renderGradeRow(g, studentId)).join('');
            }
        })
        .catch(err => {
            document.getElementById('history-body').innerHTML = `
                <div class="roster-state roster-state--error" role="alert" style="padding:16px;">
                    <p class="roster-state-msg">The student grade records could not be loaded.</p>
                    <button type="button" class="btn btn--secondary btn--sm" onclick="openHistory(${studentId}, '${escapeHtml(name).replace(/'/g, "\\'")}')">Retry</button>
                </div>
            `;
        });
}

function renderGradeRow(g, studentId) {
    const rawRisk = g.risk ? String(g.risk).toUpperCase() : '';
    const risk = ['HIGH', 'MODERATE', 'LOW'].includes(rawRisk) ? rawRisk : 'N/A';
    const riskLabel = risk === 'N/A' ? 'Not classified' : risk;
    return `
    <form method="POST" action="grades.php" class="grade-row-form" onsubmit="return confirmProposal(this)">
        <input type="hidden" name="action" value="propose_grade">
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="grade_id" value="${g.gradeId}">
        <input type="hidden" name="open_section" value="${escapeHtml(openSectionName)}">
        <input type="hidden" name="open_student" value="${studentId}">
        <input type="hidden" name="linked_feedback_id" value="${openFeedbackId || ''}">

        <div class="grade-row-subject">
            <strong>${escapeHtml(g.code)}</strong>
            ${g.hasPending ? '<span class="pending-badge" style="margin-left:4px;">Pending</span>' : ''}
            <span class="subject-title" title="${escapeHtml(g.title)}">${escapeHtml(g.title)}</span>
        </div>

        <input type="text" name="edit_prelim" value="${g.prelim !== null && g.prelim !== undefined ? escapeHtml(g.prelim) : ''}" placeholder="Prelim" title="Prelim (0-100)" aria-label="Prelim for ${escapeHtml(g.code)}">
        <input type="text" name="edit_midterm" value="${g.midterm !== null && g.midterm !== undefined ? escapeHtml(g.midterm) : ''}" placeholder="Mid" title="Midterm (0-100)" aria-label="Midterm for ${escapeHtml(g.code)}">
        <input type="text" name="edit_prefinal" value="${g.prefinal !== null && g.prefinal !== undefined ? escapeHtml(g.prefinal) : ''}" placeholder="Pre-F" title="Pre-Final (0-100)" aria-label="Pre-Final for ${escapeHtml(g.code)}">
        <input type="text" name="edit_final" value="${g.finalGrade !== null && g.finalGrade !== undefined ? escapeHtml(g.finalGrade) : ''}" placeholder="Final" title="Final Grade (1.00-4.00 or Status)" aria-label="Final Grade for ${escapeHtml(g.code)}">

        <div class="grade-row-risk">
            <span class="risk-badge" style="background:${riskColor(risk)}; color:white;" title="${riskLabel}">${escapeHtml(risk)}</span>
        </div>

        <div class="grade-row-action">
            <button type="submit" class="small-btn">Propose Correction</button>
        </div>
    </form>`;
}

function fetchGradeHistory() {
    const wrapper   = document.getElementById('modal-history-wrapper');
    const container = document.getElementById('modal-history-container');
    const toggle    = document.getElementById('modal-history-toggle');
    const icon      = document.getElementById('history-toggle-icon');
    const text      = document.getElementById('history-toggle-text');

    if (isHistoryLoading) return;
    isHistoryLoading = true;
    if (toggle) toggle.setAttribute('disabled', 'disabled');

    if (container) {
        container.innerHTML = `
            <div class="roster-state roster-state--loading" role="status" aria-live="polite" style="padding:16px; text-align:center;">
                <span class="roster-state-spinner" aria-hidden="true"></span>
                <span>Loading academic history...</span>
            </div>
        `;
    }
    if (wrapper) wrapper.style.maxHeight = '120px';

    fetch('grades_data.php?action=academic_history&student_id=' + currentModalStudentId)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            isHistoryLoading = false;
            if (toggle) toggle.removeAttribute('disabled');
            currentStudentHistory = data.terms || [];
            renderHistoricalTerms(currentStudentHistory);
            if (wrapper) wrapper.style.maxHeight = (container.scrollHeight + 60) + 'px';
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            if (icon) icon.textContent = '▼';
            if (text) text.textContent = 'Hide Grade History';
        })
        .catch(err => {
            isHistoryLoading = false;
            if (toggle) toggle.removeAttribute('disabled');
            if (container) {
                container.innerHTML = `
                    <div class="roster-state roster-state--error" role="alert" style="padding:14px;">
                        <p class="roster-state-msg">The grade history could not be loaded.</p>
                        <button type="button" class="btn btn--secondary btn--sm" onclick="retryFetchHistory()">Retry</button>
                    </div>
                `;
            }
            if (wrapper) wrapper.style.maxHeight = (container.scrollHeight + 40) + 'px';
        });
}

function toggleGradeHistory() {
    const wrapper   = document.getElementById('modal-history-wrapper');
    const container = document.getElementById('modal-history-container');
    const toggle    = document.getElementById('modal-history-toggle');
    const icon      = document.getElementById('history-toggle-icon');
    const text      = document.getElementById('history-toggle-text');

    const isExpanded = toggle && toggle.getAttribute('aria-expanded') === 'true';

    if (isExpanded) {
        // Collapse
        if (wrapper) wrapper.style.maxHeight = '0px';
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (icon) icon.textContent = '▶';
        if (text) text.textContent = 'View Grade History';
    } else {
        // Expand
        if (currentStudentHistory !== null) {
            if (wrapper) wrapper.style.maxHeight = (container.scrollHeight + 50) + 'px';
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            if (icon) icon.textContent = '▼';
            if (text) text.textContent = 'Hide Grade History';
        } else {
            fetchGradeHistory();
        }
    }
}

function retryFetchHistory() {
    currentStudentHistory = null;
    isHistoryLoading = false;
    fetchGradeHistory();
}

function renderHistoricalTerms(terms) {
    const container = document.getElementById('modal-history-container');
    if (!terms || !terms.length) {
        container.innerHTML = '<p style="color:var(--text-gray); font-size:0.85rem; text-align:center; padding:16px;">No historical grades on record.</p>';
        return;
    }

    container.innerHTML = terms.map(term => `
        <div class="history-term-block" style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:4px;">
                <div style="font-weight:700; color:var(--text-dark); font-size:0.88rem;">${escapeHtml(term.termKey)}</div>
                ${term.termGwa ? `<div style="font-size:0.8rem; color:var(--text-gray);">Term GWA: <strong style="color:var(--text-dark); font-weight:700;">${escapeHtml(term.termGwa)}</strong></div>` : ''}
            </div>
            <div class="data-table-scroll" role="region" aria-label="Historical Grades for ${escapeHtml(term.termKey)}" tabindex="0">
                <table style="width:100%; border-collapse:collapse;">
                    <caption class="sr-only">Historical Grades for ${escapeHtml(term.termKey)}</caption>
                    <thead>
                        <tr style="background:var(--table-header-bg); border-bottom:1px solid var(--border-color);">
                            <th scope="col" style="text-align:left; padding:8px 10px; color:var(--text-dark); font-size:0.78rem;">Code</th>
                            <th scope="col" style="text-align:left; padding:8px 10px; color:var(--text-dark); font-size:0.78rem;">Subject Title</th>
                            <th scope="col" style="text-align:center; padding:8px 10px; color:var(--text-dark); font-size:0.78rem;">Units</th>
                            <th scope="col" style="text-align:center; padding:8px 10px; color:var(--text-dark); font-size:0.78rem;">Official Final Grade</th>
                            <th scope="col" style="text-align:center; padding:8px 10px; color:var(--text-dark); font-size:0.78rem;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${term.subjects.map(s => `
                            <tr style="border-bottom:1px solid var(--border-color);">
                                <td style="font-weight:600; color:var(--text-dark); padding:8px 10px; font-size:0.82rem;">${escapeHtml(s.code)}</td>
                                <td style="color:var(--text-dark); padding:8px 10px; font-size:0.82rem;">${escapeHtml(s.title)}</td>
                                <td style="text-align:center; color:var(--text-gray); padding:8px 10px; font-size:0.82rem;">${escapeHtml(s.units || '—')}</td>
                                <td style="text-align:center; font-weight:700; color:var(--accent-blue); padding:8px 10px; font-size:0.85rem;">${escapeHtml(s.grade)}</td>
                                <td style="text-align:center; font-size:0.78rem; padding:8px 10px; color:var(--text-dark);">${escapeHtml(s.outcome)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `).join('');
}

function confirmProposal(form) {
    const reason = prompt('Reason for this proposed correction (required — this will NOT change the live grade until confirmed):');
    if (!reason || !reason.trim()) return false;
    let input = form.querySelector('input[name="reason"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'reason';
        form.appendChild(input);
    }
    input.value = reason.trim();
    return true;
}

function closeHistoryModal() {
    if (modalFocusFrame !== null) cancelAnimationFrame(modalFocusFrame);
    modalFocusFrame = null;
    const modal = document.getElementById('history-modal-overlay');
    modal.classList.remove('open');
    if (modalActiveTrigger && typeof modalActiveTrigger.focus === 'function') {
        try {
            modalActiveTrigger.focus({ preventScroll: true });
        } catch (e) {
            modalActiveTrigger.focus();
        }
    }
}

// Trap focus and close on Escape
document.getElementById('history-modal-overlay')?.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeHistoryModal();
        return;
    }
    if (e.key === 'Tab') {
        const focusable = this.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            last.focus();
            e.preventDefault();
        } else if (!e.shiftKey && document.activeElement === last) {
            first.focus();
            e.preventDefault();
        }
    }
});

document.addEventListener('DOMContentLoaded', function() {
    if (openSectionName) {
        openSection(openSectionName);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>