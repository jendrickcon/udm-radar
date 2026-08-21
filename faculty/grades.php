<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db = getDB();

// 1. AJAX Endpoint for fetching Roster without page refresh
if (($_GET['action'] ?? '') === 'fetch_roster') {
    header('Content-Type: application/json');
    $selectedClass = $_GET['class'] ?? '';
    $selectedTerm  = $_GET['term'] ?? 'prelim';
    
    $selectedSubjId = 0;
    $selectedSection = '';
    if ($selectedClass) {
        $parts = explode('_', $selectedClass);
        if (count($parts) === 2) {
            $selectedSubjId = (int)$parts[0];
            $selectedSection = $parts[1];
        }
    }
    
    $students = [];
    $hasPendingBatch = false;
    
    if ($selectedSubjId && $selectedSection) {
        $stmtRoster = $db->prepare("
            SELECT g.student_id, g.prelim, g.midterm, g.prefinal, g.final_grade,
                   sp.student_number, sp.section, u.first_name, u.middle_name, u.last_name,
                   s.code AS subj_code, s.title AS subj_title
            FROM grades g
            JOIN student_profiles sp ON sp.user_id = g.student_id
            JOIN users u ON u.id = g.student_id
            JOIN subjects s ON s.id = g.subject_id
            WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1
            ORDER BY u.last_name, u.first_name
        ");
        $stmtRoster->execute([$selectedSubjId, $selectedSection]);
        $students = $stmtRoster->fetchAll();
        
        $stmtPending = $db->prepare("SELECT id FROM pending_grade_batches WHERE subject_id = ? AND section = ? AND term_type = ? AND status = 'pending'");
        $stmtPending->execute([$selectedSubjId, $selectedSection, $selectedTerm]);
        $hasPendingBatch = (bool)$stmtPending->fetch();
    }
    
    echo json_encode([
        'students' => $students,
        'hasPendingBatch' => $hasPendingBatch,
        'subjId' => $selectedSubjId,
        'section' => $selectedSection,
        'term' => $selectedTerm
    ]);
    exit;
}

$error = '';
$success = '';

// 2. Process Standard Batch Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_batch') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Session expired — please refresh and try again.';
    } else {
        $subjId = (int)($_POST['subject_id'] ?? 0);
        $section = trim($_POST['section'] ?? '');
        $termType = trim($_POST['term_type'] ?? '');
        $newGrades = $_POST['grades'] ?? [];
        $reasons = $_POST['reasons'] ?? [];
        $batchNote = trim($_POST['batch_note'] ?? '');

        // STRICT ALLOWLIST: Removed 'final_grade' to prevent direct manual overrides
        if (!$subjId || !$section || !in_array($termType, ['prelim', 'midterm', 'prefinal'])) {
            $error = 'Invalid class or term selection.';
        } else {
            // SECURITY: Verify the faculty actually teaches this load
            $stmtOwn = $db->prepare("SELECT id FROM faculty_class_loads WHERE faculty_user_id = ? AND subject_id = ? AND section = ?");
            $stmtOwn->execute([$user['id'], $subjId, $section]);
            if (!$stmtOwn->fetchColumn()) {
                $error = 'Unauthorized: You are not assigned to this class load.';
            } else {
                // Fetch current grades to accurately separate initial encodings from modifications
                $stmtCurr = $db->prepare("
                    SELECT g.student_id, g.$termType 
                    FROM grades g 
                    JOIN student_profiles sp ON sp.user_id = g.student_id 
                    WHERE g.subject_id = ? AND sp.section = ? AND g.is_current = 1
                ");
                $stmtCurr->execute([$subjId, $section]);
                $currentGrades = $stmtCurr->fetchAll(PDO::FETCH_KEY_PAIR);

                $payload = [];
                foreach ($newGrades as $studentId => $gradeVal) {
                    $gradeVal = trim((string)$gradeVal);
                    if ($gradeVal !== '') {
                        if (!isValidGrade($gradeVal)) {
                            $error = "Invalid grade format entered: '{$gradeVal}'. Must be 1.00-4.00, INC, DRP, or P.";
                            break;
                        }
                        
                        $oldGrade = $currentGrades[$studentId] ?? null;
                        $isUpdate = ($oldGrade !== null && $oldGrade !== '');
                        
                        // Ignore if the faculty submitted the exact same grade that already exists
                        if ($isUpdate && $gradeVal === $oldGrade) {
                            continue;
                        }
                        // Handle strict float matching for numeric grades (e.g., 1.5 == 1.50)
                        if ($isUpdate && is_numeric($gradeVal) && is_numeric($oldGrade) && number_format((float)$gradeVal, 2) === number_format((float)$oldGrade, 2)) {
                            continue; 
                        }

                        $reason = trim($reasons[$studentId] ?? '');
                        
                        // Rule 1: Changing an existing grade -> Individual Reason STRICTLY REQUIRED
                        if ($isUpdate && $reason === '') {
                            $error = "An individual reason is required when modifying an existing grade for Student #$studentId.";
                            break;
                        }
                        
                        // Rule 2: New grade -> Individual reason optional, default to batch note or generic text
                        if (!$isUpdate && $reason === '') {
                            $reason = $batchNote !== '' ? $batchNote : 'Initial grade encoding';
                        }
                        
                        $payload[] = [
                            'student_id' => (int)$studentId,
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
                            $stmt = $db->prepare("INSERT INTO pending_grade_batches (faculty_id, subject_id, section, term_type, payload) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$user['id'], $subjId, $section, $termType, json_encode($payload)]);
                            $success = 'Grade batch successfully submitted to Administration for review and approval.';
                        } catch (PDOException $e) {
                            $error = 'Database error: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
        
        // Preserve selection after POST so the JS automatically reloads the view
        $_GET['class'] = $subjId . '_' . $section;
        $_GET['term'] = $termType;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Fetch Faculty's Assigned Classes
$stmtClasses = $db->prepare("
    SELECT fcl.subject_id, fcl.section, s.code, s.title 
    FROM faculty_class_loads fcl 
    JOIN subjects s ON s.id = fcl.subject_id 
    WHERE fcl.faculty_user_id = ?
    ORDER BY s.code, fcl.section
");
$stmtClasses->execute([$user['id']]);
$myClasses = $stmtClasses->fetchAll();

// 4. Initial Load State
$selectedClass = $_GET['class'] ?? '';
$selectedTerm  = $_GET['term'] ?? 'prelim';

// Security fallback: if term defaults to final_grade from URL, revert to prelim
if (!in_array($selectedTerm, ['prelim', 'midterm', 'prefinal'])) {
    $selectedTerm = 'prelim';
}

$pageTitle = 'Encode Grades';
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
/* Forces scrollbar to always exist, permanently curing horizontal UI twitch */
html { overflow-y: scroll; }

.animate-fade-up { animation: fadeUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeUp { 0% { opacity: 0; transform: translateY(15px); } 100% { opacity: 1; transform: translateY(0); } }

/* Smooth transition specifically for the table rows */
#roster-tbody { transition: opacity 0.2s ease; }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Encode Grades</h1>
            <p>Select your assigned class and term to securely submit grade encodings and corrections to Administration.</p>
        </div>
    </div>
        
    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight:600;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px 16px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight:600;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <!-- Selection Panel -->
    <div class="card" style="margin-bottom: 24px;">
        <form method="GET" action="grades.php" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
            <div style="display:flex; flex-direction:column; gap:4px; flex-grow:1; max-width: 380px;">
                <label style="font-weight:600; font-size:0.85rem; color: var(--text-gray);">Select Class &amp; Section</label>
                <select id="classSelect" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    <option value="">— Select a class —</option>
                    <?php foreach ($myClasses as $c): 
                        $val = $c['subject_id'] . '_' . $c['section'];
                    ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $selectedClass === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['code'] . ' - ' . $c['section'] . ' (' . $c['title'] . ')') ?>
                        </option>
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

    <!-- Messages Container -->
    <div id="message-container" style="display: none; margin-bottom: 24px;"></div>

    <!-- Main Table Card -->
    <div id="roster-card" class="card animate-fade-up" style="display: none; padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border-color); display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h3 style="color: var(--text-dark); font-size: 1.1rem; font-weight: 700; margin: 0 0 6px 0;"><span id="roster-subj-code"></span> — Class Roster</h3>
                <div style="font-size: 0.85rem; color: var(--text-gray); display:flex; flex-direction:column; gap:4px;">
                    <span style="font-weight:600; color:var(--accent-blue);" id="roster-subj-title"></span>
                    <span>Section: <strong style="color:var(--text-dark);" id="roster-section"></strong></span>
                    <span style="margin-top:4px;">Leave fields blank if no grade update is required.</span>
                </div>
            </div>
            <span style="background:rgba(30, 77, 183, 0.1); color:var(--accent-blue); padding:4px 10px; border-radius:6px; font-size:0.8rem; font-weight:700;">
                <span id="roster-count">0</span> Enrolled
            </span>
        </div>
        
        <form method="POST" action="grades.php" style="margin: 0;" class="safe-submit-form">
            <input type="hidden" name="action" value="submit_batch">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="subject_id" id="form-subject-id" value="">
            <input type="hidden" name="section" id="form-section" value="">
            <input type="hidden" name="term_type" id="form-term-type" value="">
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="background: var(--table-header-bg);">
                            <th style="padding: 14px 24px; text-align: left; color: var(--text-dark); width: 150px;">Student No.</th>
                            <th style="padding: 14px; text-align: left; color: var(--text-dark); width: 280px;">Name</th>
                            <th style="padding: 14px; text-align: center; color: var(--text-dark); width: 100px;">Current</th>
                            <th style="padding: 14px; text-align: left; color: var(--text-dark); width: 140px;">New Grade</th>
                            <th style="padding: 14px 24px; text-align: left; color: var(--text-dark);">Individual Reason</th>
                        </tr>
                    </thead>
                    <tbody id="roster-tbody">
                        <!-- AJAX rows injected here -->
                    </tbody>
                </table>
            </div>
            
            <div style="padding: 20px 24px; background: var(--bg-color); border-top: 1px solid var(--border-color); display: flex; gap: 20px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-gray); margin-bottom:4px;">Batch Note (Optional for new grades)</label>
                    <input type="text" name="batch_note" placeholder="e.g., Routine initial prelim grade encoding" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--card-bg); color: var(--text-dark); font-family: inherit;">
                </div>
                
                <button type="submit" class="submit-btn" style="background:var(--accent-blue); color:white; border:none; padding:12px 24px; border-radius:8px; font-weight:600; cursor:pointer; font-size: 0.95rem; font-family: inherit; transition: opacity 0.2s; white-space: nowrap;">
                    Submit Batch to Admin
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const classSelect = document.getElementById('classSelect');
    const termSelect = document.getElementById('termSelect');
    const msgContainer = document.getElementById('message-container');
    const rosterCard = document.getElementById('roster-card');
    const tbody = document.getElementById('roster-tbody');
    
    let isInitialLoad = true;

    function showMessage(html) {
        rosterCard.style.display = 'none';
        msgContainer.innerHTML = html;
        msgContainer.style.display = 'block';
    }

    function loadRoster(userInitiated = false) {
        if (!classSelect.value) {
            showMessage(`<div class="card animate-fade-up" style="text-align: center; padding: 40px;"><h3 style="color: var(--text-gray);">Please select a class to encode grades.</h3></div>`);
            return;
        }

        if (rosterCard.style.display !== 'none') {
            tbody.style.opacity = '0.4';
            tbody.style.pointerEvents = 'none';
        } else {
            showMessage(`<div class="card animate-fade-up" style="text-align:center; padding: 40px; color: var(--text-gray);"><span style="font-weight:600;">Loading student roster...</span></div>`);
        }

        fetch(`grades.php?action=fetch_roster&class=${encodeURIComponent(classSelect.value)}&term=${encodeURIComponent(termSelect.value)}`)
            .then(res => res.json())
            .then(data => {
                tbody.style.opacity = '1';
                tbody.style.pointerEvents = 'auto';
                
                renderTable(data);
                
                if (userInitiated && rosterCard.style.display !== 'none') {
                    setTimeout(() => {
                        rosterCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 50);
                }
                isInitialLoad = false;
            })
            .catch(err => {
                tbody.style.opacity = '1';
                tbody.style.pointerEvents = 'auto';
                showMessage(`<div class="card animate-fade-up" style="text-align:center; padding: 40px; color: var(--risk-high); font-weight:600;">Error loading roster. Please try again.</div>`);
            });
    }

    function renderTable(data) {
        if (data.hasPendingBatch) {
            showMessage(`<div class="animate-fade-up" style="background:rgba(217, 119, 6, 0.1); border:1px solid var(--risk-mod); border-radius:8px; padding:16px;"><h3 style="color:var(--risk-mod); font-size:1.05rem; margin-bottom:4px; font-weight:700;">Batch Pending Approval</h3><p style="color:var(--text-dark); font-size:0.9rem; margin:0;">You have already submitted a grade batch for this term. Please wait for Administration to review and approve it before submitting another.</p></div>`);
            return;
        }

        if (data.students.length === 0) {
            showMessage(`<div class="card animate-fade-up" style="text-align: center; padding: 40px;"><h3 style="color: var(--text-gray);">No students enrolled in this section for the current term.</h3></div>`);
            return;
        }

        msgContainer.style.display = 'none';
        rosterCard.style.display = 'block';

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
            if (isUpdate) {
                if (['INC','DRP','P'].includes(String(currentRaw).toUpperCase())) {
                    currentDisplay = String(currentRaw).toUpperCase();
                } else {
                    currentDisplay = parseFloat(currentRaw).toFixed(2);
                }
            }
            
            let formattedName = `${s.last_name}, ${s.first_name} ${s.middle_name || ''}`.trim();
            
            // Dynamic JS requirement based on existing vs new grade
            let placeholder = isUpdate ? 'Required for changes...' : 'Optional...';
            let reqLogic = isUpdate ? `oninput="document.getElementById('reason_${s.student_id}').required = (this.value.trim() !== '' && this.value.trim() !== '${currentRaw}');"` : '';
            
            rowsHtml += `
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px 24px; color: var(--text-gray); font-family: monospace;">${s.student_number}</td>
                    <td style="padding: 12px; font-weight: 600; color: var(--text-dark);">${formattedName}</td>
                    <td style="padding: 12px; text-align: center; font-weight: 600; color: var(--text-gray);">${currentDisplay}</td>
                    <td style="padding: 12px;">
                        <input type="text" name="grades[${s.student_id}]" placeholder="e.g. 1.50" 
                               style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-color); color: var(--text-dark); text-align: center; font-family: inherit;"
                               ${reqLogic}>
                    </td>
                    <td style="padding: 12px 24px;">
                        <input type="text" id="reason_${s.student_id}" name="reasons[${s.student_id}]" placeholder="${placeholder}" 
                               style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-color); color: var(--text-dark); font-family: inherit;">
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = rowsHtml;
    }

    classSelect.addEventListener('change', () => loadRoster(true));
    termSelect.addEventListener('change', () => loadRoster(true));

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