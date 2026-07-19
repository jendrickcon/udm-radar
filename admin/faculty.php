<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

const LOCKED_FACULTY_PASSWORD = 'CCSdefault!';

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
        $facId      = trim($_POST['faculty_id'] ?? '');
        $email      = trim($_POST['email'] ?? '');

        // Password is always the locked constant — readonly on the form is
        // a UX cue only, the server never trusts the submitted value.
        $password = LOCKED_FACULTY_PASSWORD;

        if (!$firstName || !$lastName || !$facId) {
            $error = 'First name, last name, and faculty ID are required.';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO users (user_id, password_hash, role, first_name, middle_name, last_name, email)
                    VALUES (?, ?, 'faculty', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $facId,
                    password_hash($password, PASSWORD_DEFAULT),
                    $firstName,
                    $middleName !== '' ? $middleName : null,
                    $lastName,
                    $email !== '' ? $email : null,
                ]);
                $success = "Faculty \"" . formatNameLastFirst($firstName, $middleName, $lastName) . "\" added successfully. Default password: " . LOCKED_FACULTY_PASSWORD;
            } catch (PDOException $e) {
                $error = 'Could not add faculty: ' . $e->getMessage();
            }
        }
    }
}

// ── EDIT — profile fields + class load assignment ────────────────────────
// Class loads are administrative assignment, not academic record, so they're
// fair game to edit here (unlike grades). Every change writes to
// admin_change_log alongside the real update.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $fid = (int) ($_POST['edit_id'] ?? 0);
        $newFirst  = trim($_POST['edit_first_name'] ?? '');
        $newMiddle = trim($_POST['edit_middle_name'] ?? '');
        $newLast   = trim($_POST['edit_last_name'] ?? '');
        $newEmail  = trim($_POST['edit_email'] ?? '');

        $stmt = $db->prepare("SELECT first_name, middle_name, last_name, email FROM users WHERE id = ?");
        $stmt->execute([$fid]);
        $old = $stmt->fetch();

        if (!$old) {
            $error = 'Faculty not found.';
        } else {
            $fieldsToCheck = [
                'first_name' => [$old['first_name'], $newFirst],
                'middle_name'=> [$old['middle_name'], $newMiddle !== '' ? $newMiddle : null],
                'last_name'  => [$old['last_name'], $newLast],
                'email'      => [$old['email'], $newEmail !== '' ? $newEmail : null],
            ];

            try {
                $db->beginTransaction();

                $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, email=? WHERE id=?")
                   ->execute([$newFirst, $newMiddle !== '' ? $newMiddle : null, $newLast, $newEmail !== '' ? $newEmail : null, $fid]);

                $logStmt = $db->prepare("
                    INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value)
                    VALUES (?, 'faculty', ?, ?, ?, ?)
                ");
                $changedCount = 0;
                foreach ($fieldsToCheck as $field => [$oldVal, $newVal]) {
                    if ((string) $oldVal !== (string) $newVal) {
                        $logStmt->execute([$user['id'], $fid, $field, $oldVal, $newVal]);
                        $changedCount++;
                    }
                }

                // ── Class load add/remove ──────────────────────────────
                // Existing loads arrive as removed_<id>=1 checkboxes;
                // a new load arrives as new_subject_id + new_section, if filled.
                foreach ($_POST as $key => $val) {
                    if (str_starts_with($key, 'removed_') && $val === '1') {
                        $loadId = (int) substr($key, 8);
                        $loadStmt = $db->prepare("SELECT subject_id, section FROM faculty_class_loads WHERE id = ? AND faculty_user_id = ?");
                        $loadStmt->execute([$loadId, $fid]);
                        $load = $loadStmt->fetch();
                        if ($load) {
                            $db->prepare("DELETE FROM faculty_class_loads WHERE id = ?")->execute([$loadId]);
                            $logStmt->execute([$user['id'], $fid, 'class_load_removed',
                                "subject {$load['subject_id']} / {$load['section']}", null]);
                            $changedCount++;
                        }
                    }
                }

                $newSubjectId = (int) ($_POST['new_subject_id'] ?? 0);
                $newSection   = trim($_POST['new_section'] ?? '');
                if ($newSubjectId && $newSection !== '') {
                    $db->prepare("INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section) VALUES (?, ?, ?)")
                       ->execute([$fid, $newSubjectId, $newSection]);
                    $logStmt->execute([$user['id'], $fid, 'class_load_added',
                        null, "subject {$newSubjectId} / {$newSection}"]);
                    $changedCount++;
                }

                $db->commit();
                $success = $changedCount > 0
                    ? "Faculty updated — {$changedCount} change(s) logged."
                    : "No changes were made.";
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Could not update faculty: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $delId = (int) ($_POST['delete_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
        $stmt->execute([$delId]);
        $success = 'Faculty account removed.';
    }
}

// ── Faculty list ─────────────────────────────────────────────────────────
// NOTE: this used to derive "students/subjects handled" from grades.encoded_by,
// which this project moved away from — encoded_by only tells you who typed a
// grade in, not who's actually assigned to teach that class. faculty_class_loads
// is the canonical source (see faculty/dashboard.php, analytics.php).
$faculty = $db->query("
    SELECT u.id, u.user_id, u.first_name, u.middle_name, u.last_name, u.email, u.is_active,
           COUNT(DISTINCT fcl.section)    AS sections_handled,
           COUNT(DISTINCT fcl.subject_id) AS subjects_handled
    FROM users u
    LEFT JOIN faculty_class_loads fcl ON fcl.faculty_user_id = u.id
    WHERE u.role = 'faculty'
    GROUP BY u.id
    ORDER BY u.last_name, u.first_name
")->fetchAll();

// ── Per-faculty class load breakdown, for the modal ─────────────────────
// Each load = one (subject, section) pair. Class avg computed the same way
// analytics.php does it: prelim average of current-term grades for students
// in that exact section, for that exact subject.
$loadStmt = $db->query("
    SELECT fcl.id AS load_id, fcl.faculty_user_id, fcl.section, s.code, s.title,
           AVG(g.prelim) AS class_avg,
           SUM(CASE WHEN g.risk_level IN ('MODERATE','HIGH') THEN 1 ELSE 0 END) AS at_risk_count,
           COUNT(g.id) AS student_count
    FROM faculty_class_loads fcl
    JOIN subjects s ON s.id = fcl.subject_id
    LEFT JOIN grades g ON g.subject_id = fcl.subject_id AND g.is_current = 1
                       AND g.student_id IN (SELECT user_id FROM student_profiles WHERE section = fcl.section)
    GROUP BY fcl.faculty_user_id, fcl.section, s.code, s.title
    ORDER BY fcl.section, s.code
");
$loadsByFaculty = [];
foreach ($loadStmt->fetchAll() as $l) {
    $loadsByFaculty[$l['faculty_user_id']][] = [
        'loadId'     => $l['load_id'],
        'section'    => $l['section'],
        'code'       => $l['code'],
        'title'      => $l['title'],
        'classAvg'   => $l['class_avg'] !== null ? round((float) $l['class_avg'], 2) : null,
        'atRisk'     => (int) $l['at_risk_count'],
        'students'   => (int) $l['student_count'],
    ];
}

$modalData = [];
foreach ($faculty as $f) {
    $fid = $f['id'];
    $modalData[$fid] = [
        'name'       => formatNameLastFirst($f['first_name'], $f['middle_name'], $f['last_name']),
        'firstName'  => $f['first_name'],
        'middleName' => $f['middle_name'],
        'lastName'   => $f['last_name'],
        'facId'   => $f['user_id'],
        'email'   => $f['email'],
        'loads'   => $loadsByFaculty[$fid] ?? [],
    ];
}

// Full subject list, for the "add a new class load" dropdown in the edit modal
$allSubjects = $db->query("SELECT id, code, title FROM subjects ORDER BY code")->fetchAll();

$pageTitle = 'Faculty';
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
.locked-field { background:#f1f5f9 !important; color:#64748b !important; cursor:not-allowed; }
.locked-hint { font-size:0.72rem; color:#94a3b8; margin-top:-8px; }

.table-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
.search-box { padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:0.9rem; width:280px; max-width:100%; }
.pagination-bar { display:flex; justify-content:center; align-items:center; gap:6px; margin-top:16px; flex-wrap:wrap; }
.page-btn { padding:6px 12px; border:1px solid #ddd; background:white; border-radius:6px; cursor:pointer; font-size:0.85rem; font-weight:600; color:#334155; font-family:inherit; }
.page-btn:hover { background:#f1f5f9; }
.page-btn.active { background:var(--sidebar-bg); color:white; border-color:var(--sidebar-bg); }
.page-btn:disabled { opacity:0.4; cursor:not-allowed; }
.row-clickable { cursor:pointer; }
.row-clickable:hover { background:#f8fafc; }

.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:1000; align-items:flex-start; justify-content:center; padding:40px 16px; overflow-y:auto; }
.modal-overlay.open { display:flex; }
.modal-box { background:white; border-radius:12px; max-width:640px; width:100%; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.25); }
.modal-close { float:right; background:#e2e8f0; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600; font-family:inherit; }
.modal-stat-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:16px 0; }
.modal-stat { background:#f8fafc; border-radius:8px; padding:10px 12px; text-align:center; }
.modal-stat span { display:block; font-size:0.72rem; color:#64748b; margin-bottom:4px; }
.modal-stat strong { font-size:1.15rem; color:var(--sidebar-bg); }
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Faculty</h1><p>Add or remove faculty accounts, and view individual teaching loads.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #c62828;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card">
        <div class="table-title">Add New Faculty</div>
        <form method="POST" action="faculty.php" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px;">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input type="text" name="first_name" placeholder="First Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="middle_name" placeholder="Middle Name (optional)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="text" name="last_name" placeholder="Last Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">

            <input type="text" name="faculty_id" placeholder="Faculty ID (e.g. faculty05)" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
            <input type="email" name="email" placeholder="Email" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">

            <div>
                <input type="text" name="password" value="<?= htmlspecialchars(LOCKED_FACULTY_PASSWORD) ?>" readonly class="locked-field" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; width:100%;">
                <div class="locked-hint">Default password — faculty changes it on first login</div>
            </div>

            <button type="submit" style="padding:10px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; align-self:start;">Add Faculty</button>
        </form>
    </div>

    <div class="card">
        <div class="table-toolbar">
            <div class="table-title" style="margin:0;">All Faculty</div>
            <input type="text" id="faculty-search" class="search-box" placeholder="Search by name or faculty ID…">
        </div>

        <?php if (empty($faculty)): ?>
            <p class="empty-state">No faculty on record.</p>
        <?php else: ?>
        <table id="faculty-table">
            <thead><tr><th>Faculty ID</th><th>Name</th><th>Email</th><th>Sections</th><th>Subjects</th><th></th></tr></thead>
            <tbody id="faculty-tbody">
                <?php foreach ($faculty as $f):
                    $fid = $f['id'];
                    $searchBlob = strtolower($f['user_id'] . ' ' . formatNameLastFirst($f['first_name'], $f['middle_name'], $f['last_name']));
                ?>
                <tr class="row-clickable" data-search="<?= htmlspecialchars($searchBlob) ?>" onclick="openFacultyModal(<?= $fid ?>)">
                    <td><?= htmlspecialchars($f['user_id']) ?></td>
                    <td style="font-weight:600; color:var(--teal);"><?= htmlspecialchars(formatNameLastFirst($f['first_name'], $f['middle_name'], $f['last_name'])) ?></td>
                    <td><?= htmlspecialchars($f['email'] ?? '—') ?></td>
                    <td><?= (int) $f['sections_handled'] ?></td>
                    <td><?= (int) $f['subjects_handled'] ?></td>
                    <td onclick="event.stopPropagation();">
                        <button type="button" onclick="openEditModal(<?= $fid ?>)"
                            style="background:none; border:none; color:var(--teal); font-weight:600; cursor:pointer; font-family:inherit; padding:0; margin-right:10px;">Edit</button>
                        <form method="POST" action="faculty.php" onsubmit="return confirm('Remove this faculty account? This cannot be undone.');" style="display:inline; margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_id" value="<?= $fid ?>">
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

<!-- Faculty profile modal -->
<div class="modal-overlay" id="faculty-modal-overlay" onclick="if(event.target===this) closeFacultyModal();">
    <div class="modal-box">
        <button class="modal-close" onclick="closeFacultyModal()">✕ Close</button>
        <h2 id="modal-name" style="color:var(--sidebar-bg); margin-bottom:2px;"></h2>
        <p id="modal-subline" style="color:#64748b; font-size:0.88rem; margin-bottom:12px;"></p>

        <h4 style="color:var(--sidebar-bg); font-size:0.95rem; margin-bottom:8px;">Class Loads</h4>
        <table style="width:100%;">
            <thead><tr><th>Section</th><th>Subject</th><th>Class Avg</th><th>At-Risk</th></tr></thead>
            <tbody id="modal-loads-body"></tbody>
        </table>
    </div>
</div>

<!-- Edit modal — profile fields + class load add/remove -->
<div class="modal-overlay" id="edit-modal-overlay" onclick="if(event.target===this) closeEditModal();">
    <div class="modal-box" style="max-width:680px;">
        <button class="modal-close" onclick="closeEditModal()">✕ Close</button>
        <h2 style="color:var(--sidebar-bg); margin-bottom:4px;">Edit Faculty</h2>
        <p style="color:#94a3b8; font-size:0.78rem; margin-bottom:16px;">
            Changes here are logged for audit.
        </p>

        <form method="POST" action="faculty.php" id="edit-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px;">
                <input type="text" name="edit_first_name" id="edit-first-name" placeholder="First Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                <input type="text" name="edit_middle_name" id="edit-middle-name" placeholder="Middle Name (optional)" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                <input type="text" name="edit_last_name" id="edit-last-name" placeholder="Last Name" required style="padding:10px 12px; border:1px solid #ddd; border-radius:8px;">
                <input type="email" name="edit_email" id="edit-email" placeholder="Email" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; grid-column:span 3;">
            </div>

            <h4 style="color:var(--sidebar-bg); font-size:0.9rem; margin-bottom:8px;">Current Class Loads</h4>
            <div id="edit-loads-list" style="margin-bottom:14px;"></div>

            <h4 style="color:var(--sidebar-bg); font-size:0.9rem; margin-bottom:8px;">Add a Class Load</h4>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:10px; margin-bottom:18px;">
                <select name="new_subject_id" style="padding:9px 12px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">— Select subject —</option>
                    <?php foreach ($allSubjects as $subj): ?>
                        <option value="<?= $subj['id'] ?>"><?= htmlspecialchars($subj['code'] . ' — ' . $subj['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="new_section" placeholder="Section (e.g. IT-33)" style="padding:9px 12px; border:1px solid #ddd; border-radius:8px;">
            </div>

            <button type="submit" style="width:100%; padding:10px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Save Changes</button>
        </form>
    </div>
</div>

<script>
const facultyModalData = <?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

const ROWS_PER_PAGE = 25;
let currentPage = 1;

function getVisibleRows() {
    const term = document.getElementById('faculty-search').value.trim().toLowerCase();
    const allRows = Array.from(document.querySelectorAll('#faculty-tbody tr'));
    return allRows.filter(row => !term || row.dataset.search.includes(term));
}

function renderPage() {
    const allRows = Array.from(document.querySelectorAll('#faculty-tbody tr'));
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
    html += `<span style="color:#94a3b8; font-size:0.8rem; margin-left:10px;">${visible.length} faculty</span>`;
    bar.innerHTML = html;
}

function goToPage(p) { currentPage = p; renderPage(); }

document.getElementById('faculty-search').addEventListener('input', () => {
    currentPage = 1;
    renderPage();
});

renderPage();

function openFacultyModal(fid) {
    const d = facultyModalData[fid];
    if (!d) return;

    document.getElementById('modal-name').innerText = d.name;
    document.getElementById('modal-subline').innerText = `${d.facId} · ${d.email || 'No email on file'}`;

    const body = document.getElementById('modal-loads-body');
    if (!d.loads.length) {
        body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:16px;">No class loads assigned.</td></tr>';
    } else {
        body.innerHTML = d.loads.map(l => {
            const avg = l.classAvg !== null ? l.classAvg.toFixed(2) : '—';
            const atRiskColor = l.atRisk > 0 ? '#b91c1c' : '#059669';
            return `<tr>
                <td style="font-weight:600;">${l.section}</td>
                <td title="${l.title}">${l.code}</td>
                <td style="font-weight:600;">${avg}</td>
                <td style="color:${atRiskColor}; font-weight:600;">${l.atRisk} / ${l.students}</td>
            </tr>`;
        }).join('');
    }

    document.getElementById('faculty-modal-overlay').classList.add('open');
}

function closeFacultyModal() {
    document.getElementById('faculty-modal-overlay').classList.remove('open');
}

function openEditModal(fid) {
    const d = facultyModalData[fid];
    if (!d) return;

    document.getElementById('edit-id').value = fid;
    document.getElementById('edit-first-name').value = d.firstName || '';
    document.getElementById('edit-middle-name').value = d.middleName || '';
    document.getElementById('edit-last-name').value = d.lastName || '';
    document.getElementById('edit-email').value = d.email || '';

    const list = document.getElementById('edit-loads-list');
    if (!d.loads.length) {
        list.innerHTML = '<p style="color:#94a3b8; font-size:0.85rem;">No class loads assigned yet.</p>';
    } else {
        list.innerHTML = d.loads.map(l => `
            <label style="display:flex; align-items:center; gap:8px; padding:6px 0; font-size:0.88rem; border-bottom:1px solid #f1f5f9;">
                <input type="checkbox" name="removed_${l.loadId}" value="1">
                <span style="color:#b91c1c; font-size:0.78rem; font-weight:600;">Remove</span>
                <span style="margin-left:auto; font-weight:600;">${l.section}</span>
                <span style="color:#64748b;" title="${l.title}">${l.code}</span>
            </label>
        `).join('');
    }

    document.getElementById('edit-modal-overlay').classList.add('open');
}

function closeEditModal() {
    document.getElementById('edit-modal-overlay').classList.remove('open');
}
</script>

<?php require_once '../includes/footer.php'; ?>