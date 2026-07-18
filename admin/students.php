<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

// ── Locked defaults — server is the source of truth, never trust the form ──
const LOCKED_COURSE   = 'Bachelor of Science in Information Technology';
const LOCKED_PASSWORD = 'default1!';

// ── CSRF token (session-based) ─────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function checkCsrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

$error   = '';
$success = '';

// ── CREATE ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $firstName  = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $studentNo  = trim($_POST['student_number'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $college    = trim($_POST['college'] ?? 'CCS');
        $yearLevel  = (int) ($_POST['year_level'] ?? 0);
        $section    = trim($_POST['section'] ?? '');

        // Course and password are ALWAYS the locked constants, regardless
        // of what the client sent — the readonly attribute on the form
        // fields is a UX cue only, not a security boundary.
        $course   = LOCKED_COURSE;
        $password = LOCKED_PASSWORD;

        if (!$firstName || !$lastName || !$studentNo) {
            $error = 'First name, last name, and student number are required.';
        } else {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("
                    INSERT INTO users (user_id, password_hash, role, first_name, middle_name, last_name, email)
                    VALUES (?, ?, 'student', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentNo,
                    password_hash($password, PASSWORD_DEFAULT),
                    $firstName,
                    $middleName !== '' ? $middleName : null,
                    $lastName,
                    $email !== '' ? $email : null,
                ]);
                $newUserId = $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO student_profiles (user_id, student_number, course, college, year_level, section) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$newUserId, $studentNo, $course, $college, $yearLevel, $section]);
                $db->commit();
                $success = "Student \"" . formatNameLastFirst($firstName, $middleName, $lastName) . "\" added successfully. Default password: " . LOCKED_PASSWORD;
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Could not add student: ' . $e->getMessage();
            }
        }
    }
}

// ── EDIT — only enrollment facts, never grades ───────────────────────────
// Grades stay faculty-sourced; anything academic-record-shaped is
// deliberately NOT editable here. See admin_change_log for the audit trail
// every one of these writes leaves behind.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $uid = (int) ($_POST['edit_id'] ?? 0);

        $newFirst   = trim($_POST['edit_first_name'] ?? '');
        $newMiddle  = trim($_POST['edit_middle_name'] ?? '');
        $newLast    = trim($_POST['edit_last_name'] ?? '');
        $newSection = trim($_POST['edit_section'] ?? '');
        $newYear    = (int) ($_POST['edit_year_level'] ?? 0);
        $newStatus  = trim($_POST['edit_status'] ?? 'Regular');

        // Pull current values so we only log fields that actually changed
        $stmt = $db->prepare("
            SELECT u.first_name, u.middle_name, u.last_name, sp.section, sp.year_level, sp.status
            FROM users u JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$uid]);
        $old = $stmt->fetch();

        if (!$old) {
            $error = 'Student not found.';
        } else {
            $fieldsToCheck = [
                'first_name' => [$old['first_name'], $newFirst],
                'middle_name'=> [$old['middle_name'], $newMiddle !== '' ? $newMiddle : null],
                'last_name'  => [$old['last_name'], $newLast],
                'section'    => [$old['section'], $newSection],
                'year_level' => [$old['year_level'], $newYear],
                'status'     => [$old['status'], $newStatus],
            ];

            try {
                $db->beginTransaction();

                $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id=?")
                   ->execute([$newFirst, $newMiddle !== '' ? $newMiddle : null, $newLast, $uid]);

                $db->prepare("UPDATE student_profiles SET section=?, year_level=?, status=? WHERE user_id=?")
                   ->execute([$newSection, $newYear, $newStatus, $uid]);

                $logStmt = $db->prepare("
                    INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value)
                    VALUES (?, 'student', ?, ?, ?, ?)
                ");
                $changedCount = 0;
                foreach ($fieldsToCheck as $field => [$oldVal, $newVal]) {
                    // Loose comparison: '3' vs 3 shouldn't count as a change
                    if ((string) $oldVal !== (string) $newVal) {
                        $logStmt->execute([$user['id'], $uid, $field, $oldVal, $newVal]);
                        $changedCount++;
                    }
                }

                $db->commit();
                $success = $changedCount > 0
                    ? "Student updated — {$changedCount} field(s) changed and logged."
                    : "No changes were made.";
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Could not update student: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $delId = (int) ($_POST['delete_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$delId]);
        $success = 'Student removed.';
    }
}

// ── Main student list (table rows) ──────────────────────────────────────
$students = $db->query("
    SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa,
           u.first_name, u.middle_name, u.last_name, u.email
    FROM student_profiles sp JOIN users u ON u.id = sp.user_id
    ORDER BY sp.section, u.last_name, u.first_name
")->fetchAll();

// ── Per-student current-term grades + latest prediction, for the modal ──
// Embedded as JSON keyed by user_id so opening a profile needs no reload.
$gradeStmt = $db->query("
    SELECT g.student_id, s.code, s.title, g.prelim, g.risk_level
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.is_current = 1
    ORDER BY g.student_id, s.code
");
$gradesByStudent = [];
foreach ($gradeStmt->fetchAll() as $g) {
    $gradesByStudent[$g['student_id']][] = [
        'code'  => $g['code'],
        'title' => $g['title'],
        'prelim'=> $g['prelim'] !== null ? (float) $g['prelim'] : null,
        'risk'  => $g['risk_level'],
    ];
}

$predStmt = $db->query("
    SELECT p.student_id, p.predicted_gwa, p.risk_level, p.latin_honor
    FROM predictions p
    JOIN (SELECT student_id, MAX(generated_at) mx FROM predictions GROUP BY student_id) latest
        ON latest.student_id = p.student_id AND latest.mx = p.generated_at
");
$predByStudent = [];
foreach ($predStmt->fetchAll() as $p) {
    $predByStudent[$p['student_id']] = $p;
}

// ── Historical (Y1-Y2, is_current=0) grades, grouped by school year + sem ──
// Loaded lazily in the modal via a "View History" toggle rather than shown
// by default, since most profile views only need the current-term snapshot.
$histStmt = $db->query("
    SELECT g.student_id, g.school_year, g.semester, s.code, s.title, g.final_grade
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.is_current = 0 AND g.final_grade IS NOT NULL
    ORDER BY g.student_id, g.school_year, g.semester, s.code
");
$historyByStudent = [];
foreach ($histStmt->fetchAll() as $h) {
    $termKey = $h['school_year'] . ' — Sem ' . $h['semester'];
    $historyByStudent[$h['student_id']][$termKey][] = [
        'code'  => $h['code'],
        'title' => $h['title'],
        'grade' => (float) $h['final_grade'],
    ];
}

$modalData = [];
foreach ($students as $s) {
    $uid = $s['user_id'];
    $modalData[$uid] = [
        'name'       => formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']),
        'firstName'  => $s['first_name'],
        'middleName' => $s['middle_name'],
        'lastName'   => $s['last_name'],
        'studentNo'  => $s['student_number'],
        'email'      => $s['email'],
        'section'    => $s['section'],
        'yearLevel'  => $s['year_level'],
        'status'     => $s['status'],
        'currentGwa' => $s['current_gwa'] !== null ? (float) $s['current_gwa'] : null,
        'predicted'  => $predByStudent[$uid]['predicted_gwa'] ?? null,
        'risk'       => $predByStudent[$uid]['risk_level'] ?? null,
        'honor'      => $predByStudent[$uid]['latin_honor'] ?? null,
        'grades'     => $gradesByStudent[$uid] ?? [],
        'history'    => $historyByStudent[$uid] ?? [],
    ];
}

$pageTitle = 'Students';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Students',           'students.php',  '👥'],
    ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.locked-field {
    background: #f1f5f9 !important;
    color: #64748b !important;
    cursor: not-allowed;
}
.locked-hint {
    grid-column: span 1;
    font-size: 0.72rem;
    color: #94a3b8;
    margin-top: -8px;
}

.table-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
}
.search-box {
    padding: 9px 14px; border: 1px solid #ddd; border-radius: 8px;
    font-size: 0.9rem; width: 280px; max-width: 100%;
}
.pagination-bar {
    display: flex; justify-content: center; align-items: center;
    gap: 6px; margin-top: 16px; flex-wrap: wrap;
}
.page-btn {
    padding: 6px 12px; border: 1px solid #ddd; background: white;
    border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;
    color: #334155; font-family: inherit;
}
.page-btn:hover { background: #f1f5f9; }
.page-btn.active { background: var(--sidebar-bg); color: white; border-color: var(--sidebar-bg); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.row-clickable { cursor: pointer; }
.row-clickable:hover { background: #f8fafc; }
.row-clickable td:first-child + td { color: var(--teal); font-weight: 600; }

.modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55);
    z-index: 1000; align-items: flex-start; justify-content: center;
    padding: 40px 16px; overflow-y: auto;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: white; border-radius: 12px; max-width: 640px; width: 100%;
    padding: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}
.modal-close {
    float: right; background: #e2e8f0; border: none; padding: 6px 12px;
    border-radius: 6px; cursor: pointer; font-weight: 600; font-family: inherit;
}
.modal-stat-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0;
}
.modal-stat {
    background: #f8fafc; border-radius: 8px; padding: 10px 12px; text-align: center;
}
.modal-stat span { display: block; font-size: 0.72rem; color: #64748b; margin-bottom: 4px; }
.modal-stat strong { font-size: 1.15rem; color: var(--sidebar-bg); }
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Students</h1><p>Add or remove student accounts, and view individual profiles.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card">
        <div class="table-title">Add New Student</div>
        <form method="POST" action="students.php" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px;">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input type="text" name="first_name" placeholder="First Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="middle_name" placeholder="Middle Name (optional)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="last_name" placeholder="Last Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">

            <input type="text" name="student_number" placeholder="Student No. (e.g. 23-22-041)" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="email" name="email" placeholder="Email" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="section" placeholder="Section (e.g. IT-33)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">

            <select name="year_level" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                <option value="3" selected>3rd Year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="4">4th Year</option>
            </select>

            <div>
                <input type="text" name="course" value="<?= htmlspecialchars(LOCKED_COURSE) ?>" readonly class="locked-field" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; width:100%;">
                <div class="locked-hint">Fixed — all students are BSIT</div>
            </div>
            <div>
                <input type="text" name="password" value="<?= htmlspecialchars(LOCKED_PASSWORD) ?>" readonly class="locked-field" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; width:100%;">
                <div class="locked-hint">Default password — student changes it on first login</div>
            </div>

            <button type="submit" style="padding:10px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; align-self:start;">Add Student</button>
        </form>
    </div>

    <div class="card">
        <div class="table-toolbar">
            <div class="table-title" style="margin:0;">All Students</div>
            <input type="text" id="student-search" class="search-box" placeholder="Search by name, student no., or section…">
        </div>

        <?php if (empty($students)): ?>
            <p class="empty-state">No students on record.</p>
        <?php else: ?>
        <table id="students-table">
            <thead><tr><th>Student No.</th><th>Name</th><th>Email</th><th>Section</th><th>Year</th><th>GWA</th><th>Status</th><th></th></tr></thead>
            <tbody id="students-tbody">
                <?php foreach ($students as $s):
                    $uid = $s['user_id'];
                    $searchBlob = strtolower($s['student_number'] . ' ' . formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']) . ' ' . ($s['section'] ?? ''));
                ?>
                <tr class="row-clickable" data-search="<?= htmlspecialchars($searchBlob) ?>" onclick="openStudentModal(<?= $uid ?>)">
                    <td><?= htmlspecialchars($s['student_number']) ?></td>
                    <td><?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?></td>
                    <td><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                    <td><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                    <td><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                    <td onclick="event.stopPropagation();">
                        <button type="button" onclick="openEditModal(<?= $uid ?>)"
                            style="background:none; border:none; color:var(--teal); font-weight:600; cursor:pointer; font-family:inherit; padding:0; margin-right:10px;">Edit</button>
                        <form method="POST" action="students.php" onsubmit="return confirm('Remove this student account? This cannot be undone.');" style="display:inline; margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_id" value="<?= $uid ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" style="background:none; border:none; color:var(--risk-high); font-weight:600; cursor:pointer; font-family:inherit; padding:0;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination-bar" id="pagination-bar"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Student profile modal -->
<div class="modal-overlay" id="student-modal-overlay" onclick="if(event.target===this) closeStudentModal();">
    <div class="modal-box">
        <button class="modal-close" onclick="closeStudentModal()">✕ Close</button>
        <h2 id="modal-name" style="color:var(--sidebar-bg); margin-bottom:2px;"></h2>
        <p id="modal-subline" style="color:#64748b; font-size:0.88rem; margin-bottom:12px;"></p>

        <div class="modal-stat-grid">
            <div class="modal-stat"><span>Current GWA</span><strong id="modal-gwa">—</strong></div>
            <div class="modal-stat"><span>Predicted GWA</span><strong id="modal-predicted">—</strong></div>
            <div class="modal-stat"><span>Risk Level</span><strong id="modal-risk">—</strong></div>
        </div>

        <h4 style="color:var(--sidebar-bg); font-size:0.95rem; margin-bottom:8px;">Current Semester Grades</h4>
        <table style="width:100%;">
            <thead><tr><th>Subject</th><th>Prelim</th><th>Risk</th></tr></thead>
            <tbody id="modal-grades-body"></tbody>
        </table>

        <div style="margin-top:18px; border-top:1px solid #f1f5f9; padding-top:14px;">
            <button type="button" id="modal-history-toggle" onclick="toggleHistory()"
                style="background:none; border:1px solid #ddd; color:var(--teal); padding:7px 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.85rem; font-family:inherit;">
                ▶ View Y1–Y2 Grade History
            </button>
            <div id="modal-history-container" style="display:none; margin-top:14px;"></div>
        </div>
    </div>
</div>

<!-- Edit modal — enrollment facts only, never grades -->
<div class="modal-overlay" id="edit-modal-overlay" onclick="if(event.target===this) closeEditModal();">
    <div class="modal-box">
        <button class="modal-close" onclick="closeEditModal()">✕ Close</button>
        <h2 style="color:var(--sidebar-bg); margin-bottom:4px;">Edit Student</h2>
        <p style="color:#94a3b8; font-size:0.78rem; margin-bottom:16px;">
            Changes here are logged for audit — grades are not editable and always come from the faculty portal.
        </p>

        <form method="POST" action="students.php" id="edit-form" style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input type="text" name="edit_first_name" id="edit-first-name" placeholder="First Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="edit_middle_name" id="edit-middle-name" placeholder="Middle Name (optional)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="edit_last_name" id="edit-last-name" placeholder="Last Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; grid-column:span 2;">

            <input type="text" name="edit_section" id="edit-section" placeholder="Section" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <select name="edit_year_level" id="edit-year-level" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>

            <select name="edit_status" id="edit-status" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; grid-column:span 2;">
                <option value="Regular">Regular</option>
                <option value="Irregular">Irregular</option>
            </select>

            <button type="submit" style="grid-column:span 2; padding:10px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Save Changes</button>
        </form>
    </div>
</div>

<script>
const studentModalData = <?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

// ── Search + pagination (client-side, operates on already-rendered rows) ──
const ROWS_PER_PAGE = 25;
let currentPage = 1;

function getVisibleRows() {
    const term = document.getElementById('student-search').value.trim().toLowerCase();
    const allRows = Array.from(document.querySelectorAll('#students-tbody tr'));
    return allRows.filter(row => !term || row.dataset.search.includes(term));
}

function renderPage() {
    const allRows = Array.from(document.querySelectorAll('#students-tbody tr'));
    const visible = getVisibleRows();
    allRows.forEach(r => r.style.display = 'none');

    const totalPages = Math.max(1, Math.ceil(visible.length / ROWS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    const start = (currentPage - 1) * ROWS_PER_PAGE;
    visible.slice(start, start + ROWS_PER_PAGE).forEach(r => r.style.display = '');

    const bar = document.getElementById('pagination-bar');
    let html = '';
    html += `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="goToPage(${currentPage-1})">‹ Prev</button>`;
    for (let p = 1; p <= totalPages; p++) {
        html += `<button class="page-btn ${p===currentPage?'active':''}" onclick="goToPage(${p})">${p}</button>`;
    }
    html += `<button class="page-btn" ${currentPage===totalPages?'disabled':''} onclick="goToPage(${currentPage+1})">Next ›</button>`;
    html += `<span style="color:#94a3b8; font-size:0.8rem; margin-left:10px;">${visible.length} student${visible.length!==1?'s':''}</span>`;
    bar.innerHTML = html;
}

function goToPage(p) {
    currentPage = p;
    renderPage();
}

document.getElementById('student-search').addEventListener('input', () => {
    currentPage = 1;
    renderPage();
});

renderPage();

// ── Modal ───────────────────────────────────────────────────────────────
function riskColor(risk) {
    if (risk === 'HIGH') return '#b91c1c';
    if (risk === 'MODERATE') return '#d97706';
    if (risk === 'LOW') return '#059669';
    return '#94a3b8';
}

function openStudentModal(uid) {
    const d = studentModalData[uid];
    if (!d) return;

    currentModalUid = uid; // remember for the history toggle

    document.getElementById('modal-name').innerText = d.name;
    document.getElementById('modal-subline').innerText =
        `${d.studentNo} · ${d.section || '—'} · Year ${d.yearLevel || '—'} · ${d.status || 'Regular'}`;

    document.getElementById('modal-gwa').innerText = d.currentGwa !== null ? d.currentGwa.toFixed(2) : '—';
    document.getElementById('modal-predicted').innerText = d.predicted !== null ? parseFloat(d.predicted).toFixed(2) : 'N/A';

    const riskEl = document.getElementById('modal-risk');
    riskEl.innerText = d.risk || 'N/A';
    riskEl.style.color = riskColor(d.risk);

    const body = document.getElementById('modal-grades-body');
    if (!d.grades.length) {
        body.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:16px;">No current-term grades on record.</td></tr>';
    } else {
        body.innerHTML = d.grades.map(g => {
            const prelim = g.prelim !== null ? parseFloat(g.prelim).toFixed(2) : '—';
            const rc = riskColor(g.risk);
            return `<tr>
                <td title="${g.title}">${g.code}</td>
                <td style="font-weight:600;">${prelim}</td>
                <td><span style="background:${rc}; color:white; padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:700;">${g.risk || '—'}</span></td>
            </tr>`;
        }).join('');
    }

    // Reset history panel to collapsed every time a new profile opens
    document.getElementById('modal-history-container').style.display = 'none';
    document.getElementById('modal-history-container').innerHTML = '';
    document.getElementById('modal-history-toggle').innerText = '▶ View Y1–Y2 Grade History';

    document.getElementById('student-modal-overlay').classList.add('open');
}

let currentModalUid = null;

function toggleHistory() {
    const container = document.getElementById('modal-history-container');
    const btn = document.getElementById('modal-history-toggle');
    const isOpen = container.style.display !== 'none';

    if (isOpen) {
        container.style.display = 'none';
        btn.innerText = '▶ View Y1–Y2 Grade History';
        return;
    }

    // Build the history table content once, on first expand
    if (!container.innerHTML) {
        const d = studentModalData[currentModalUid];
        const terms = Object.keys(d.history || {});
        if (!terms.length) {
            container.innerHTML = '<p style="color:#94a3b8; font-size:0.85rem; text-align:center; padding:12px;">No historical (Y1–Y2) grades on record for this student.</p>';
        } else {
            container.innerHTML = terms.map(term => {
                const rows = d.history[term].map(g => `
                    <tr>
                        <td title="${g.title}">${g.code}</td>
                        <td style="font-weight:600;">${g.grade.toFixed(2)}</td>
                    </tr>`).join('');
                return `
                    <div style="margin-bottom:14px;">
                        <div style="font-weight:700; color:var(--sidebar-bg); font-size:0.85rem; margin-bottom:6px;">${term}</div>
                        <table style="width:100%;">
                            <thead><tr><th>Subject</th><th>Final Grade</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`;
            }).join('');
        }
    }

    container.style.display = 'block';
    btn.innerText = '▼ Hide Y1–Y2 Grade History';
}

function closeStudentModal() {
    document.getElementById('student-modal-overlay').classList.remove('open');
}

function openEditModal(uid) {
    const d = studentModalData[uid];
    if (!d) return;

    document.getElementById('edit-id').value = uid;
    document.getElementById('edit-first-name').value = d.firstName || '';
    document.getElementById('edit-middle-name').value = d.middleName || '';
    document.getElementById('edit-last-name').value = d.lastName || '';
    document.getElementById('edit-section').value = d.section || '';
    document.getElementById('edit-year-level').value = d.yearLevel || '3';
    document.getElementById('edit-status').value = d.status || 'Regular';

    document.getElementById('edit-modal-overlay').classList.add('open');
}

function closeEditModal() {
    document.getElementById('edit-modal-overlay').classList.remove('open');
}
</script>

<?php require_once '../includes/footer.php'; ?>