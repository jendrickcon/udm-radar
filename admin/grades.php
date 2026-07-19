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

// ── PROPOSE a grade correction ────────────────────────────────────────────
// UdM-RADAR does not officially own grade data — the faculty portal /
// registrar does. An admin here can only PROPOSE a correction; nothing in
// `grades` changes until it's separately confirmed as officially reflected
// (standing in for "ICTO/registrar confirmed this"). No direct edit, no
// delete — grades are amended via proposal, never silently overwritten.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'propose_grade') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh and try again.';
    } else {
        $gradeId = (int) ($_POST['grade_id'] ?? 0);
        $reason  = trim($_POST['reason'] ?? '');
        $openSec = trim($_POST['open_section'] ?? '');
        $openStu = (int) ($_POST['open_student'] ?? 0);
        
        // Capture the linked feedback report if this originated from the inbox shortcut
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
                $fields = [
                    'prelim'      => $_POST['edit_prelim']   !== '' ? $_POST['edit_prelim']   : null,
                    'midterm'     => $_POST['edit_midterm']  !== '' ? $_POST['edit_midterm']  : null,
                    'prefinal'    => $_POST['edit_prefinal'] !== '' ? $_POST['edit_prefinal'] : null,
                    'final_grade' => $_POST['edit_final']    !== '' ? $_POST['edit_final']    : null,
                ];

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

        if (!$error) {
            header('Location: grades.php?open_section=' . urlencode($openSec) . '&open_student=' . $openStu . '&msg=' . urlencode($success ?? ''));
            exit;
        }
    }
}

// ── CONFIRM / REJECT a pending correction ────────────────────────────────
// Confirming is the manual stand-in for "ICTO/registrar has now officially
// reflected this" — only at this point does the real `grades` row change,
// with risk_level recomputed the same way a direct edit would have, and an
// admin_change_log entry written since the change is now actually real.
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
            // Confirm: apply to the real grades row, recompute risk, log it
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

                $db->prepare("UPDATE grades SET `$field` = ? WHERE id = ?")->execute([$newVal, $corr['target_id']]);

                // Recompute risk from whichever value is authoritative
                $stmt = $db->prepare("SELECT prelim, final_grade FROM grades WHERE id = ?");
                $stmt->execute([$corr['target_id']]);
                $fresh = $stmt->fetch();
                if ($fresh['final_grade'] !== null) {
                    $newRisk = computeRiskFromAvg(normalizePointGrade($fresh['final_grade']));
                } elseif ($fresh['prelim'] !== null) {
                    $newRisk = computeRiskFromAvg(normalizeTermGrade($fresh['prelim']));
                } else {
                    $newRisk = $gradeRow['risk_level'];
                }
                $db->prepare("UPDATE grades SET risk_level = ? WHERE id = ?")->execute([$newRisk, $corr['target_id']]);

                $db->prepare("UPDATE pending_corrections SET status='confirmed', resolved_by=?, resolved_at=NOW() WHERE id=?")
                   ->execute([$user['id'], $corrId]);

                // Auto-resolve linked feedback report if this correction came from the inbox
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

// ── Section summary cards — cheap aggregate, not the full grade table ────
$sections = $db->query("
    SELECT sp.section,
           COUNT(*) AS student_count,
           AVG(sp.current_gwa) AS avg_gwa,
           SUM(CASE WHEN p.risk_level IN ('MODERATE','HIGH') THEN 1 ELSE 0 END) AS at_risk_count
    FROM student_profiles sp
    LEFT JOIN predictions p ON p.student_id = sp.user_id
        AND p.generated_at = (SELECT MAX(p2.generated_at) FROM predictions p2 WHERE p2.student_id = sp.user_id)
    WHERE sp.section IS NOT NULL AND sp.section != ''
    GROUP BY sp.section
    ORDER BY sp.section
")->fetchAll();

// ── Pending grade corrections awaiting confirmation ───────────────────────
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
.section-card { padding:0; overflow:hidden; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer; transition:box-shadow .15s; }
.section-card:hover { box-shadow:0 4px 14px rgba(0,0,0,0.08); }
.section-card-head { background:#0f172a; color:white; padding:14px 18px; font-weight:600; font-size:1.05rem; display:flex; justify-content:space-between; }
.section-card-body { padding:24px; text-align:center; }
.section-card-body h2 { font-size:2.4rem !important; }

.roster-panel { display:none; border:2px solid var(--teal); margin-bottom:24px; }
.row-clickable { cursor:pointer; }
.row-clickable:hover { background:#f8fafc; }

.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:flex-start; justify-content:center; padding:40px 16px; overflow-y:auto; }
.modal-overlay.open { display:flex; }
.modal-box { background:white; border-radius:12px; max-width:760px; width:100%; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.25); }
.modal-close { float:right; background:#e2e8f0; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600; font-family:inherit; }

.grade-row-form { display:grid; grid-template-columns: repeat(4, 70px) 1fr auto; gap:6px; align-items:center; }
.grade-row-form input[type=number] { width:100%; padding:5px 6px; border:1px solid #ddd; border-radius:5px; font-size:0.82rem; }
.small-btn { padding:5px 10px; border-radius:5px; border:none; font-weight:600; font-size:0.78rem; cursor:pointer; font-family:inherit; }

.pending-badge { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:700; }
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Grades</h1><p>UdM-RADAR does not own official grade records. Corrections are proposed here, then confirmed once officially reflected in the registrar's system.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <?php if (!empty($pending)): ?>
    <div class="card" style="border-left:4px solid #d97706; margin-bottom:24px;">
        <div class="table-title" style="display:flex; align-items:center; gap:8px;">
            Pending Grade Corrections
            <span class="pending-badge"><?= count($pending) ?> awaiting confirmation</span>
        </div>
        <table>
            <thead><tr><th>Student</th><th>Subject</th><th>Field</th><th>Was</th><th>Proposed</th><th>Reason</th><th>Proposed By</th><th></th></tr></thead>
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
                    <td style="font-weight:700; color:#d97706;"><?= htmlspecialchars($p['new_value']) ?></td>
                    <td style="font-size:0.82rem; color:#64748b;"><?= htmlspecialchars($p['reason']) ?></td>
                    <td style="font-size:0.82rem;"><?= htmlspecialchars($adminName) ?></td>
                    <td style="white-space:nowrap;">
                        <form method="POST" action="grades.php" style="display:inline;" onsubmit="return confirm('Mark this correction as officially reflected? This will update the live grade record.');">
                            <input type="hidden" name="action" value="confirm_correction">
                            <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" class="small-btn" style="background:#059669; color:white;">Confirm</button>
                        </form>
                        <form method="POST" action="grades.php" style="display:inline;" onsubmit="return confirm('Reject this proposed correction?');">
                            <input type="hidden" name="action" value="reject_correction">
                            <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" class="small-btn" style="background:#fee2e2; color:#b91c1c;">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="stat-grid" style="grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px;">
        <?php foreach ($sections as $sec):
            $avg = $sec['avg_gwa'] !== null ? number_format($sec['avg_gwa'], 2) : '—';
            $riskColor = $sec['at_risk_count'] > 0 ? '#b91c1c' : '#059669';
        ?>
        <div class="section-card" onclick="openSection('<?= htmlspecialchars($sec['section']) ?>')">
            <div class="section-card-head">
                <span><?= htmlspecialchars($sec['section']) ?></span>
                <span style="font-size:0.8rem; color:#94a3b8;"><?= $sec['student_count'] ?> students</span>
            </div>
            <div class="section-card-body">
                <h2 style="color:var(--teal); font-size:1.8rem; margin-bottom:2px;"><?= $avg ?></h2>
                <p style="color:#64748b; font-size:0.8rem; margin-bottom:8px;">Avg GWA</p>
                <div style="font-size:0.8rem; color:<?= $riskColor ?>; font-weight:600;">
                    <?= $sec['at_risk_count'] ?> at-risk
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card roster-panel" id="roster-panel">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 id="roster-title" style="color:var(--sidebar-bg);"></h3>
            <button onclick="closeRoster()" style="background:#e2e8f0; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-family:inherit;">✕ Close</button>
        </div>
        <table>
            <thead><tr><th>Student No.</th><th>Name</th><th>Status</th><th>GWA</th><th>Risk</th></tr></thead>
            <tbody id="roster-body"></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="history-modal-overlay" onclick="if(event.target===this) closeHistoryModal();">
    <div class="modal-box">
        <button class="modal-close" onclick="closeHistoryModal()">✕ Close</button>
        <h2 id="history-name" style="color:var(--sidebar-bg); margin-bottom:2px;"></h2>
        <p style="color:#94a3b8; font-size:0.78rem; margin-bottom:16px;">
            Current semester only. Grades are not directly editable here — submitting proposes a correction that only takes effect once confirmed as officially reflected.
        </p>
        <div id="history-body"></div>
    </div>
</div>

<script>
let openSectionName = <?= json_encode($openSection) ?>;
let openStudentId   = <?= json_encode($openStudent) ?>;
let openFeedbackId  = <?= json_encode($openFeedbackId) ?>;
const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

function riskColor(risk) {
    if (risk === 'HIGH') return '#b91c1c';
    if (risk === 'MODERATE') return '#d97706';
    if (risk === 'LOW') return '#059669';
    return '#94a3b8';
}

function openSection(section) {
    openSectionName = section;
    fetch('grades_data.php?action=students&section=' + encodeURIComponent(section))
        .then(r => r.json())
        .then(students => {
            document.getElementById('roster-title').innerText = 'Section ' + section + ' — Student Roster';
            const body = document.getElementById('roster-body');
            if (!students.length) {
                body.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:16px;">No students in this section.</td></tr>';
            } else {
                body.innerHTML = students.map(s => `
                    <tr class="row-clickable" onclick="openHistory(${s.userId}, '${s.name.replace(/'/g,"\\'")}')">
                        <td>${s.studentNo}</td>
                        <td style="font-weight:600; color:var(--teal);">${s.name}</td>
                        <td>${s.status || 'Regular'}</td>
                        <td style="font-weight:600;">${s.currentGwa !== null ? s.currentGwa.toFixed(2) : '—'}</td>
                        <td><span style="background:${riskColor(s.risk)}; color:white; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700;">${s.risk || 'N/A'}</span></td>
                    </tr>`).join('');
            }
            document.getElementById('roster-panel').style.display = 'block';
            document.getElementById('roster-panel').scrollIntoView({ behavior: 'smooth' });

            if (openStudentId) {
                const target = students.find(s => s.userId === openStudentId);
                if (target) openHistory(openStudentId, target.name);
                openStudentId = 0;
            }
        });
}

function closeRoster() {
    document.getElementById('roster-panel').style.display = 'none';
}

function openHistory(studentId, name) {
    fetch('grades_data.php?action=history&student_id=' + studentId)
        .then(r => r.json())
        .then(rows => {
            document.getElementById('history-name').innerText = name;

            if (!rows.length) {
                document.getElementById('history-body').innerHTML = '<p style="color:#94a3b8; text-align:center; padding:20px;">No current-term grades for this student.</p>';
            } else {
                document.getElementById('history-body').innerHTML = rows.map(g => renderGradeRow(g, studentId)).join('');
            }

            document.getElementById('history-modal-overlay').classList.add('open');
        });
}

function renderGradeRow(g, studentId) {
    const risk = g.risk || 'LOW';
    return `
    <form method="POST" action="grades.php" class="grade-row-form" style="padding:8px 0; border-bottom:1px solid #f1f5f9;"
          onsubmit="return confirmProposal(this)">
        <input type="hidden" name="action" value="propose_grade">
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="grade_id" value="${g.gradeId}">
        <input type="hidden" name="open_section" value="${openSectionName}">
        <input type="hidden" name="open_student" value="${studentId}">
        <input type="hidden" name="linked_feedback_id" value="${openFeedbackId || ''}">

        <input type="number" step="0.01" name="edit_prelim" value="${g.prelim ?? ''}" placeholder="Prelim %" title="Prelim (%)">
        <input type="number" step="0.01" name="edit_midterm" value="${g.midterm ?? ''}" placeholder="Mid %" title="Midterm (%)">
        <input type="number" step="0.01" name="edit_prefinal" value="${g.prefinal ?? ''}" placeholder="Pre-F %" title="Pre-Final (%)">
        <input type="number" step="0.01" min="0" max="4" name="edit_final" value="${g.finalGrade ?? ''}" placeholder="Final" title="Final Grade (1.00-4.00)">

        <span style="font-size:0.8rem;" title="${g.title}">
            <strong>${g.code}</strong>
            <span style="background:${riskColor(risk)}; color:white; padding:1px 7px; border-radius:4px; font-size:0.68rem; font-weight:700; margin-left:6px;">${risk}</span>
            ${g.hasPending ? '<span class="pending-badge" style="margin-left:6px;">Pending</span>' : ''}
        </span>

        <button type="submit" class="small-btn" style="background:var(--teal); color:white;">Propose Correction</button>
    </form>`;
}

function confirmProposal(form) {
    const reason = prompt('Reason for this proposed correction (required — this will NOT change the live grade until confirmed):');
    if (!reason || !reason.trim()) return false;
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = 'reason'; input.value = reason.trim();
    form.appendChild(input);
    return true;
}

function closeHistoryModal() {
    document.getElementById('history-modal-overlay').classList.remove('open');
}

if (openSectionName) {
    openSection(openSectionName);
}
</script>

<?php require_once '../includes/footer.php'; ?>