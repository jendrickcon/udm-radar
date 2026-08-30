<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

// --- SMART ROUTING RESOLVER ---
$selectedClass = trim($_GET['class'] ?? '');
$selectedTerm = trim($_GET['term'] ?? 'prelim');
$targetStudentId = 0;
$feedbackId = (int) ($_GET['feedback_id'] ?? 0);
$routingError = '';
$activeFeedbackId = $feedbackId;

if ($feedbackId > 0) {
    $stmtFeedback = $db->prepare("
        SELECT f.id, f.subject_id, f.submitted_by AS student_id, f.category, f.status, f.grade_period, sp.section
        FROM feedback_reports f
        JOIN users student_user ON student_user.id = f.submitted_by AND student_user.role = 'student'
        JOIN student_profiles sp ON sp.user_id = f.submitted_by
        JOIN faculty_class_loads fcl ON fcl.faculty_user_id = ? AND fcl.subject_id = f.subject_id AND fcl.section = sp.section
        WHERE f.id = ? LIMIT 1
    ");
    $stmtFeedback->execute([$user['id'], $feedbackId]);
    $fb = $stmtFeedback->fetch(PDO::FETCH_ASSOC);

    if (!$fb) {
        $routingError = 'The concern could not be opened because it is unavailable or does not belong to one of your assigned classes.';
    } elseif (empty($fb['subject_id']) || empty($fb['section']) || empty($fb['student_id'])) {
        $routingError = 'The concern does not contain enough class information to open a roster automatically.';
    } else {
        $selectedClass = (int) $fb['subject_id'] . '_' . trim($fb['section']);
        $targetStudentId = (int) $fb['student_id'];

        $allowedTerms = ['prelim', 'midterm', 'prefinal'];
        $ticketTerm = strtolower(trim((string) $fb['grade_period']));
        $selectedTerm = in_array($ticketTerm, $allowedTerms, true) ? $ticketTerm : 'prelim';
    }
}

$error = $routingError;
$success = '';

if (($_GET['action'] ?? '') === 'fetch_roster') {
    header('Content-Type: application/json');
    $reqClass = $_GET['class'] ?? '';
    $reqTerm  = $_GET['term'] ?? 'prelim';
    
    $selectedSubjId = 0; $selectedSection = '';
    if ($reqClass) {
        $parts = explode('_', $reqClass);
        if (count($parts) === 2) { $selectedSubjId = (int)$parts[0]; $selectedSection = $parts[1]; }
    }
    
    $students = []; $hasPendingBatch = false;
    if ($selectedSubjId && $selectedSection) {
        $stmtOwn = $db->prepare("SELECT id FROM faculty_class_loads WHERE faculty_user_id = ? AND subject_id = ? AND section = ?");
        $stmtOwn->execute([$user['id'], $selectedSubjId, $selectedSection]);
        if (!$stmtOwn->fetchColumn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

        $stmtRoster = $db->prepare("
            SELECT g.student_id, g.prelim, g.midterm, g.prefinal, g.final_grade, sp.student_number, sp.section, u.first_name, u.middle_name, u.last_name, s.code AS subj_code, s.title AS subj_title
            FROM grades g
            JOIN student_profiles sp ON sp.user_id = g.student_id JOIN users u ON u.id = g.student_id JOIN subjects s ON s.id = g.subject_id
            WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1 ORDER BY u.last_name, u.first_name
        ");
        $stmtRoster->execute([$selectedSubjId, $selectedSection]);
        $students = $stmtRoster->fetchAll();
        
        $stmtPending = $db->prepare("SELECT id FROM pending_grade_batches WHERE subject_id = ? AND section = ? AND term_type = ? AND status = 'pending'");
        $stmtPending->execute([$selectedSubjId, $selectedSection, $reqTerm]);
        $hasPendingBatch = (bool)$stmtPending->fetch();
    }
    echo json_encode(['students' => $students, 'hasPendingBatch' => $hasPendingBatch, 'subjId' => $selectedSubjId, 'section' => $selectedSection, 'term' => $reqTerm]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_batch') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Session expired — please refresh and try again.';
    } else {
        $subjId = (int)($_POST['subject_id'] ?? 0);
        $section = trim($_POST['section'] ?? '');
        $termType = trim($_POST['term_type'] ?? '');
        $submissionType = $_POST['submission_type'] ?? 'initial_encoding';
        $batchNote = trim($_POST['batch_note'] ?? '');
        $formFeedbackId = (int)($_POST['active_feedback_id'] ?? 0);
        
        $newGrades = $_POST['grades'] ?? [];
        $reasons = $_POST['reasons'] ?? [];

        if (!$subjId || !$section || !in_array($termType, ['prelim', 'midterm', 'prefinal'])) {
            $error = 'Invalid class or term selection.';
        } elseif ($submissionType === 'bulk_correction' && $batchNote === '') {
            $error = 'A Shared Correction Reason is required for Bulk Grade Corrections.';
        } else {
            $stmtOwn = $db->prepare("SELECT id FROM faculty_class_loads WHERE faculty_user_id = ? AND subject_id = ? AND section = ?");
            $stmtOwn->execute([$user['id'], $subjId, $section]);
            if (!$stmtOwn->fetchColumn()) {
                $error = 'Unauthorized: You are not assigned to this class load.';
            } else {
                $stmtCurr = $db->prepare("SELECT g.student_id, g.$termType FROM grades g JOIN student_profiles sp ON sp.user_id = g.student_id WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1");
                $stmtCurr->execute([$subjId, $section]);
                $currentGrades = $stmtCurr->fetchAll(PDO::FETCH_KEY_PAIR);

                $payload = [];
                foreach ($newGrades as $studentId => $gradeVal) {
                    $gradeVal = trim((string)$gradeVal);
                    if ($gradeVal !== '') {
                        $gradeValUpper = strtoupper($gradeVal);
                        $isValid = in_array($gradeValUpper, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD']) || (is_numeric($gradeVal) && (float)$gradeVal >= 0 && (float)$gradeVal <= 100);
                        if (!$isValid) {
                            $error = "Invalid grade format entered: '{$gradeVal}'."; break;
                        }
                        
                        $gradeVal = is_numeric($gradeVal) ? $gradeVal : $gradeValUpper;
                        $oldGrade = $currentGrades[$studentId] ?? null;
                        $isUpdate = ($oldGrade !== null && $oldGrade !== '');
                        
                        if ($isUpdate && $gradeVal === $oldGrade) continue;
                        if ($isUpdate && is_numeric($gradeVal) && is_numeric($oldGrade) && number_format((float)$gradeVal, 2) === number_format((float)$oldGrade, 2)) continue; 

                        $reason = trim($reasons[$studentId] ?? '');
                        
                        // Apply intelligent reason logic based on purpose
                        if ($reason === '') {
                            if ($submissionType === 'bulk_correction') {
                                $reason = $batchNote;
                            } elseif ($isUpdate) {
                                $error = "An individual reason is required for modifying an existing grade unless using Bulk Correction (Student #$studentId)."; break;
                            } else {
                                $reason = $batchNote !== '' ? $batchNote : 'Initial grade encoding';
                            }
                        }
                        
                        // SNAPSHOT: Saving old_value so Admin can see the difference
                        $payload[] = [
                            'student_id' => (int)$studentId,
                            'old_value'  => $oldGrade, 
                            'grade'      => $gradeVal,
                            'reason'     => $reason
                        ];
                    }
                }

                if (!$error) {
                    if (empty($payload)) {
                        $error = 'No new or modified grades were entered to submit.';
                    } else {
                        try {
                            $stmt = $db->prepare("INSERT INTO pending_grade_batches (faculty_id, subject_id, section, term_type, submission_type, batch_note, feedback_id, payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$user['id'], $subjId, $section, $termType, $submissionType, $batchNote ?: null, $formFeedbackId ?: null, json_encode($payload)]);
                            $success = 'Grade batch successfully submitted to Administration for review and approval.';
                        } catch (PDOException $e) {
                            $error = 'Database error: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
        $selectedClass = $subjId . '_' . $section; $selectedTerm = $termType;
    }
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$stmtClasses = $db->prepare("SELECT fcl.subject_id, fcl.section, s.code, s.title FROM faculty_class_loads fcl JOIN subjects s ON s.id = fcl.subject_id WHERE fcl.faculty_user_id = ? ORDER BY s.title, fcl.section");
$stmtClasses->execute([$user['id']]);
$myClasses = $stmtClasses->fetchAll();

if (!in_array($selectedTerm, ['prelim', 'midterm', 'prefinal'])) $selectedTerm = 'prelim';

$pageTitle = 'Encode Grades';
$navItems = [
    ['Home', 'index.php', '🏠'], ['Dashboard', 'dashboard.php', '📊'], ['Class Analytics', 'analytics.php', '📋'],
    ['Performance Trends', 'trend.php', '📈'], ['Encode Grades', 'grades.php', '📝'], ['Concerns & Reports', 'feedback.php', '💬'], ['Settings', 'settings.php', '⚙️'],
];

require_once '../includes/header.php'; require_once '../includes/sidebar.php';
?>

<style>
html { overflow-y: scroll; }
.animate-fade-up { animation: fadeUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeUp { 0% { opacity: 0; transform: translateY(15px); } 100% { opacity: 1; transform: translateY(0); } }
#roster-tbody { transition: opacity 0.2s ease; }
.target-student-row { background-color: rgba(217, 119, 6, 0.16) !important; box-shadow: inset 4px 0 0 var(--risk-mod); }
.target-student-row td { background-color: rgba(217, 119, 6, 0.08); }
.target-student-row.target-student-faded { background-color: transparent !important; transition: background-color 1.5s ease; }
.target-student-row.target-student-faded td { background-color: transparent; transition: background-color 1.5s ease; }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div><h1>Encode Grades</h1><p>Select your assigned class and term to securely submit grade encodings and corrections to Administration.</p></div>
    </div>
        
    <?php if ($error): ?><p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight:600;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight:600;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <div class="card" style="margin-bottom: 24px;">
        <form method="GET" action="grades.php" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
            <div style="display:flex; flex-direction:column; gap:4px; flex-grow:1; max-width: 380px;">
                <label style="font-weight:600; font-size:0.85rem; color: var(--text-gray);">Select Class &amp; Section</label>
                <select id="classSelect" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="">— Select a class —</option>
                    <?php foreach ($myClasses as $c): $val = $c['subject_id'] . '_' . $c['section']; ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $selectedClass === $val ? 'selected' : '' ?>><?= htmlspecialchars($c['title'] . ' — ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; flex-direction:column; gap:4px; flex-grow:1; max-width: 200px;">
                <label style="font-weight:600; font-size:0.85rem; color: var(--text-gray);">Select Term</label>
                <select id="termSelect" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="prelim" <?= $selectedTerm === 'prelim' ? 'selected' : '' ?>>Prelim</option>
                    <option value="midterm" <?= $selectedTerm === 'midterm' ? 'selected' : '' ?>>Midterm</option>
                    <option value="prefinal" <?= $selectedTerm === 'prefinal' ? 'selected' : '' ?>>Pre-Final</option>
                </select>
            </div>
        </form>
    </div>

    <div id="message-container" style="display: none; margin-bottom: 24px;"></div>

    <div id="roster-card" class="card animate-fade-up" style="display: none; padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h3 style="color: var(--text-dark); font-size: 1.1rem; font-weight: 700; margin: 0 0 6px 0;"><span id="roster-subj-code"></span> — Class Roster</h3>
                <div style="font-size: 0.85rem; color: var(--text-gray); display:flex; flex-direction:column; gap:4px;">
                    <span style="font-weight:600; color:var(--accent-blue);" id="roster-subj-title"></span>
                    <span>Section: <strong style="color:var(--text-dark);" id="roster-section"></strong></span>
                    <span style="margin-top:4px;">Leave fields blank if no grade update is required. Term scores must be percentage-based (0-100).</span>
                </div>
            </div>
            <span style="background:rgba(30, 77, 183, 0.1); color:var(--accent-blue); padding:4px 10px; border-radius:6px; font-size:0.8rem; font-weight:700;"><span id="roster-count">0</span> Enrolled</span>
        </div>
        
        <form method="POST" action="grades.php" style="margin: 0;" class="safe-submit-form">
            <input type="hidden" name="action" value="submit_batch">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="subject_id" id="form-subject-id" value="">
            <input type="hidden" name="section" id="form-section" value="">
            <input type="hidden" name="term_type" id="form-term-type" value="">
            <input type="hidden" name="active_feedback_id" value="<?= $activeFeedbackId ?>">
            
            <div style="padding: 16px 24px; background: var(--card-bg); border-bottom: 1px solid var(--border-color); display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Submission Purpose</label>
                    <select name="submission_type" id="submission_type" onchange="updateNoteUI()" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                        <option value="initial_encoding">Initial Grade Encoding</option>
                        <option value="bulk_correction">Bulk Grade Correction</option>
                        <option value="grade_concern">Student Grade Concern</option>
                    </select>
                </div>
                <div style="flex: 2; min-width: 300px;">
                    <label id="note_label" style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Submission Note (Optional)</label>
                    <input type="text" name="batch_note" id="batch_note" placeholder="e.g., Routine initial prelim grade encoding" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: var(--table-header-bg);">
                            <th style="padding: 14px 24px; text-align: left; color: var(--text-dark); width: 150px;">Student No.</th>
                            <th style="padding: 14px; text-align: left; color: var(--text-dark); width: 280px;">Name</th>
                            <th style="padding: 14px; text-align: center; color: var(--text-dark); width: 100px;">Current</th>
                            <th style="padding: 14px; text-align: left; color: var(--text-dark); width: 140px;">New Grade</th>
                            <th style="padding: 14px 24px; text-align: left; color: var(--text-dark);">Individual Reason <span style="font-weight:normal; font-size:0.75rem; color:var(--text-gray);">(Overrides shared note)</span></th>
                        </tr>
                    </thead>
                    <tbody id="roster-tbody"></tbody>
                </table>
            </div>
            
            <div style="padding: 20px 24px; background: var(--bg-color); border-top: 1px solid var(--border-color); text-align: right;">
                <button type="submit" class="submit-btn" style="background:var(--accent-blue); color:white; border:none; padding:12px 24px; border-radius:8px; font-weight:600; cursor:pointer; font-size: 0.95rem; font-family: inherit; transition: opacity 0.2s;">
                    Submit to Administration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const targetStudentId = <?= json_encode($targetStudentId) ?>;
const activeFeedbackId = <?= json_encode($activeFeedbackId) ?>;

function updateNoteUI() {
    const type = document.getElementById('submission_type').value;
    const label = document.getElementById('note_label');
    const input = document.getElementById('batch_note');
    
    if (type === 'bulk_correction') {
        label.innerText = 'Shared Correction Reason (Required)';
        input.placeholder = 'e.g., Fixing incorrect spreadsheet import for entire class';
        input.required = true;
        input.readOnly = false;
    } else if (type === 'grade_concern') {
        label.innerText = 'Grade Concern Reference (Auto-Linked)';
        input.placeholder = activeFeedbackId ? `Linked to Ticket #${activeFeedbackId}` : 'Student Feedback Ticket';
        input.required = false;
        input.readOnly = true;
        if(activeFeedbackId) input.value = `Resolved via Grade Concern Ticket #${activeFeedbackId}`;
    } else {
        label.innerText = 'Submission Note (Optional)';
        input.placeholder = 'e.g., Routine initial prelim grade encoding';
        input.required = false;
        input.readOnly = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const classSelect = document.getElementById('classSelect');
    const termSelect = document.getElementById('termSelect');
    const msgContainer = document.getElementById('message-container');
    const rosterCard = document.getElementById('roster-card');
    const tbody = document.getElementById('roster-tbody');
    
    if (activeFeedbackId) {
        document.getElementById('submission_type').value = 'grade_concern';
    }
    updateNoteUI();

    function showMessage(html) {
        rosterCard.style.display = 'none';
        msgContainer.innerHTML = html;
        msgContainer.style.display = 'block';
    }

    function loadRoster(userInitiated = false) {
        if (!classSelect.value) { showMessage(`<div class="card animate-fade-up" style="text-align: center; padding: 40px;"><h3 style="color: var(--text-gray);">Please select a class to encode grades.</h3></div>`); return; }
        if (rosterCard.style.display !== 'none') { tbody.style.opacity = '0.4'; tbody.style.pointerEvents = 'none'; } else { showMessage(`<div class="card animate-fade-up" style="text-align:center; padding: 40px; color: var(--text-gray);"><span style="font-weight:600;">Loading student roster...</span></div>`); }

        fetch(`grades.php?action=fetch_roster&class=${encodeURIComponent(classSelect.value)}&term=${encodeURIComponent(termSelect.value)}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) { showMessage(`<div class="card animate-fade-up" style="text-align:center; padding: 40px; color: var(--risk-high); font-weight:600;">${data.error}</div>`); return; }
                tbody.style.opacity = '1'; tbody.style.pointerEvents = 'auto';
                renderTable(data);
                if (userInitiated && rosterCard.style.display !== 'none') { setTimeout(() => { rosterCard.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50); }

                if (targetStudentId && !userInitiated) {
                    requestAnimationFrame(() => { requestAnimationFrame(() => {
                        const targetRow = document.getElementById('row_' + targetStudentId);
                        const targetInput = document.getElementById('input_' + targetStudentId);
                        const targetReason = document.getElementById('reason_' + targetStudentId);

                        if (!targetRow) return;
                        if (activeFeedbackId && targetReason) targetReason.value = 'Correction related to ticket #' + activeFeedbackId;

                        targetRow.classList.add('target-student-row');
                        targetRow.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                        setTimeout(() => { if (targetInput) targetInput.focus({ preventScroll: true }); }, 650);
                        setTimeout(() => { targetRow.classList.add('target-student-faded'); }, 5000);
                    });});
                }
            }).catch(err => { tbody.style.opacity = '1'; tbody.style.pointerEvents = 'auto'; showMessage(`<div class="card animate-fade-up" style="text-align:center; padding: 40px; color: var(--risk-high); font-weight:600;">Error loading roster.</div>`); });
    }

    function renderTable(data) {
        if (data.hasPendingBatch) { showMessage(`<div class="animate-fade-up" style="background:rgba(217, 119, 6, 0.1); border:1px solid var(--risk-mod); border-radius:8px; padding:16px;"><h3 style="color:var(--risk-mod); font-size:1.05rem; margin-bottom:4px; font-weight:700;">Batch Pending Approval</h3><p style="color:var(--text-dark); font-size:0.9rem; margin:0;">You have already submitted a grade batch for this term. Please wait for Administration to review and approve it before submitting another.</p></div>`); return; }
        if (data.students.length === 0) { showMessage(`<div class="card animate-fade-up" style="text-align: center; padding: 40px;"><h3 style="color: var(--text-gray);">No students enrolled in this section for the current term.</h3></div>`); return; }

        msgContainer.style.display = 'none'; rosterCard.style.display = 'block';
        document.getElementById('roster-subj-code').textContent = data.students[0].subj_code;
        document.getElementById('roster-subj-title').textContent = data.students[0].subj_title;
        document.getElementById('roster-section').textContent = data.students[0].section;
        document.getElementById('roster-count').textContent = data.students.length;
        document.getElementById('form-subject-id').value = data.subjId;
        document.getElementById('form-section').value = data.section;
        document.getElementById('form-term-type').value = data.term;

        let rowsHtml = '';
        data.students.forEach(s => {
            let currentRaw = s[data.term];
            let isUpdate = (currentRaw !== null && currentRaw !== '' && currentRaw !== undefined);
            let currentDisplay = '—';
            if (isUpdate) { currentDisplay = ['INC','DRP','P','DO','DU','FA','UD'].includes(String(currentRaw).toUpperCase()) ? String(currentRaw).toUpperCase() : Math.round(parseFloat(currentRaw)) + '%'; }
            
            let formattedName = `${s.last_name}, ${s.first_name} ${s.middle_name || ''}`.trim();
            let reqLogic = isUpdate ? `oninput="let r = document.getElementById('reason_${s.student_id}'); let t = document.getElementById('submission_type').value; if(t !== 'bulk_correction') { r.required = (this.value.trim() !== '' && this.value.trim() !== '${currentRaw}'); }"` : '';
            
            rowsHtml += `<tr id="row_${s.student_id}" style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 12px 24px; color: var(--text-gray); font-family: monospace;">${s.student_number}</td>
                <td style="padding: 12px; font-weight: 600; color: var(--text-dark);">${formattedName}</td>
                <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-gray);">${currentDisplay}</td>
                <td style="padding: 12px;"><input type="text" id="input_${s.student_id}" name="grades[${s.student_id}]" placeholder="e.g. 85" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-color); color: var(--text-dark); text-align: center; font-family: inherit;" ${reqLogic}></td>
                <td style="padding: 12px 24px;"><input type="text" id="reason_${s.student_id}" name="reasons[${s.student_id}]" placeholder="Optional..." style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-color); color: var(--text-dark); font-family: inherit;"></td>
            </tr>`;
        });
        tbody.innerHTML = rowsHtml;
    }

    classSelect.addEventListener('change', () => loadRoster(true));
    termSelect.addEventListener('change', () => loadRoster(true));
    document.getElementById('submission_type').addEventListener('change', updateNoteUI);

    document.querySelectorAll('.safe-submit-form').forEach(f => {
        f.addEventListener('submit', function() {
            const btn = this.querySelector('.submit-btn');
            if(btn) { setTimeout(() => { btn.style.pointerEvents = 'none'; btn.style.opacity = '0.7'; btn.innerHTML = 'Processing...'; }, 10); }
        });
    });

    loadRoster(false);
});
</script>
<?php require_once '../includes/footer.php'; ?>