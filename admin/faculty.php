<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

const LOCKED_FACULTY_PASSWORD = 'CCSdefault!';

// --- FIXED: Function to securely generate the next Faculty ID ---
function getNextFacultyId(PDO $db): string {
    $stmt = $db->query("
        SELECT COALESCE(
            MAX(CAST(SUBSTRING(user_id, 3) AS UNSIGNED)),
            2200
        ) + 1
        FROM users
        WHERE role = 'faculty'
        AND user_id REGEXP '^FA[0-9]+$'
    ");
    $nextNumber = (int) $stmt->fetchColumn();
    return 'FA' . $nextNumber;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $firstName  = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName   = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');

        // FIXED: The server forces the ID generation here, ignoring whatever the browser sends
        $facId = getNextFacultyId($db);
        $password = LOCKED_FACULTY_PASSWORD;

        // FIXED: Removed facId from validation since it is perfectly generated
        if (!$firstName || !$lastName) {
            $error = 'First name and last name are required.';
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
                    $conflictStmt = $db->prepare("
                        SELECT u.first_name, u.last_name 
                        FROM faculty_class_loads fcl 
                        JOIN users u ON u.id = fcl.faculty_user_id 
                        WHERE fcl.subject_id = ? AND fcl.section = ? AND fcl.faculty_user_id != ?
                    ");
                    $conflictStmt->execute([$newSubjectId, $newSection, $fid]);
                    $conflict = $conflictStmt->fetch();

                    if ($conflict) {
                        throw new Exception("Class Load Blocked: That subject and section is already assigned to " . formatNameLastFirst($conflict['first_name'], '', $conflict['last_name']) . ".");
                    } else {
                        $checkSelf = $db->prepare("SELECT id FROM faculty_class_loads WHERE faculty_user_id = ? AND subject_id = ? AND section = ?");
                        $checkSelf->execute([$fid, $newSubjectId, $newSection]);
                        if (!$checkSelf->fetchColumn()) {
                            $db->prepare("INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section) VALUES (?, ?, ?)")
                               ->execute([$fid, $newSubjectId, $newSection]);
                            $logStmt->execute([$user['id'], $fid, 'class_load_added', null, "subject {$newSubjectId} / {$newSection}"]);
                            $changedCount++;
                        }
                    }
                }

                $db->commit();
                $success = $changedCount > 0
                    ? "Faculty updated — {$changedCount} change(s) logged."
                    : "No changes were made.";
                    
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $delId = (int) ($_POST['delete_id'] ?? 0);
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'faculty'");
        $stmt->execute([$delId]);
        $success = 'Faculty account deactivated. Teaching history preserved.';
    }
}

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

// Fetch subjects ordered by Year, then Semester, then alphabetically by Code
$allSubjectsRaw = $db->query("
    SELECT id, code, title, year_level, semester 
    FROM subjects 
    ORDER BY COALESCE(year_level, 99) ASC, COALESCE(semester, 99) ASC, code ASC
")->fetchAll();

// Group the subjects cleanly for the UI dropdown
$groupedSubjects = [];
foreach ($allSubjectsRaw as $subj) {
    $yr = (int)$subj['year_level'];
    $sem = (int)$subj['semester'];
    
    $yrText = match($yr) { 1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year', default => 'Any Year' };
    $semText = match($sem) { 1 => '1st Semester', 2 => '2nd Semester', 3 => 'Summer', default => 'Any Semester' };
    
    $groupName = "{$yrText}, {$semText}";
    $groupedSubjects[$groupName][] = $subj;
}

$sectionsRaw = $db->query("SELECT DISTINCT year_level, section FROM student_profiles WHERE section IS NOT NULL AND status != 'Archived' ORDER BY section")->fetchAll();
$sectionsByYear = [];
foreach ($sectionsRaw as $sr) {
    $sectionsByYear[$sr['year_level']][] = $sr['section'];
}

$takenRaw = $db->query("
    SELECT fcl.subject_id, fcl.section, u.first_name, u.last_name 
    FROM faculty_class_loads fcl 
    JOIN users u ON u.id = fcl.faculty_user_id
")->fetchAll();
$takenLoads = [];
foreach ($takenRaw as $tr) {
    $takenLoads[$tr['subject_id']][$tr['section']] = formatNameShort($tr['first_name'], $tr['last_name']);
}

// FIXED: Generate the UI preview of the new ID
$nextFacultyId = getNextFacultyId($db);

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
.locked-field { background: var(--bg-color) !important; color: var(--text-gray) !important; border-color: var(--border-color) !important; cursor: not-allowed; }
.table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.search-box { padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 280px; max-width: 100%; background-color: var(--bg-color); color: var(--text-dark); }
.search-box:focus { border-color: var(--accent-blue); outline: none; background-color: var(--card-bg); }
.pagination-bar { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
.page-btn { padding: 6px 12px; border: 1px solid var(--border-color); background: var(--card-bg); border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); font-family: inherit; transition: all 0.2s; }
.page-btn:hover { background: var(--bg-color); }
.page-btn.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.row-clickable { cursor: pointer; }
.row-clickable:hover { background: var(--bg-color); }
.row-clickable td:first-child + td { color: var(--accent-blue); font-weight: 600; }
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Faculty</h1><p style="color: var(--text-gray);">Add or remove faculty accounts, and view individual teaching loads.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight:600;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight:600;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card">
        <div class="table-title">Add New Faculty</div>
        <form method="POST" action="faculty.php" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px; align-items: start;">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Top Row -->
            <div>
                <label style="display:block; margin-bottom:4px; color:var(--text-gray); font-size:0.8rem; font-weight:600;">First Name</label>
                <input type="text" name="first_name" placeholder="First Name" required class="form-input" style="width: 100%;">
            </div>
            <div>
                <label style="display:block; margin-bottom:4px; color:var(--text-gray); font-size:0.8rem; font-weight:600;">Middle Name</label>
                <input type="text" name="middle_name" placeholder="Middle Name (optional)" class="form-input" style="width: 100%;">
            </div>
            <div>
                <label style="display:block; margin-bottom:4px; color:var(--text-gray); font-size:0.8rem; font-weight:600;">Last Name</label>
                <input type="text" name="last_name" placeholder="Last Name" required class="form-input" style="width: 100%;">
            </div>

            <!-- Bottom Row -->
            <div>
                <label style="display:block; margin-bottom:4px; color:var(--text-gray); font-size:0.8rem; font-weight:600;">Faculty ID</label>
                <input type="text" value="<?= htmlspecialchars($nextFacultyId) ?>" readonly class="form-input locked-field" aria-label="Automatically generated Faculty ID" style="width: 100%;">
            </div>
            <div>
                <label style="display:block; margin-bottom:4px; color:var(--text-gray); font-size:0.8rem; font-weight:600;">Email Address</label>
                <input type="email" name="email" placeholder="Email" class="form-input" style="width: 100%;">
            </div>
            <div>
                <label style="display:block; margin-bottom:4px; color:var(--text-gray); font-size:0.8rem; font-weight:600;">Default Password</label>
                <input type="text" name="password" value="<?= htmlspecialchars(LOCKED_FACULTY_PASSWORD) ?>" readonly class="form-input locked-field" style="width: 100%;">
            </div>

            <button type="submit" style="padding:10px; background:var(--accent-blue); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; grid-column: 1 / -1; justify-self: start;">Add Faculty</button>
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
        <table id="faculty-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Faculty ID</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Name</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Email</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Sections</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Subjects</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="faculty-tbody">
                <?php foreach ($faculty as $f):
                    $fid = $f['id'];
                    $searchBlob = strtolower($f['user_id'] . ' ' . formatNameLastFirst($f['first_name'], $f['middle_name'], $f['last_name']));
                ?>
                <tr class="row-clickable" data-search="<?= htmlspecialchars($searchBlob) ?>" onclick="openFacultyModal(<?= $fid ?>)" style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($f['user_id']) ?></td>
                    <td style="padding: 12px; font-weight:600; color:var(--accent-blue);">
                        <?= htmlspecialchars(formatNameLastFirst($f['first_name'], $f['middle_name'], $f['last_name'])) ?>
                        <?php if (!$f['is_active']): ?>
                            <span style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:2px 6px; border-radius:4px; font-size:0.7rem; margin-left:6px; vertical-align: middle;">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; color: var(--text-gray);"><?= htmlspecialchars($f['email'] ?? '—') ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= (int) $f['sections_handled'] ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= (int) $f['subjects_handled'] ?></td>
                    <td onclick="event.stopPropagation();" style="padding: 12px; text-align: right;">
                        <button type="button" onclick="openEditModal(<?= $fid ?>)"
                            style="background:none; border:none; color:var(--accent-blue); font-weight:600; cursor:pointer; font-family:inherit; padding:0; margin-right:10px;">Edit</button>
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

<div class="modal-overlay" id="faculty-modal-overlay" onclick="if(event.target===this) closeFacultyModal();">
    <div class="modal-box">
        <button class="modal-close" onclick="closeFacultyModal()">✕ Close</button>
        <h2 id="modal-name" style="color:var(--text-dark); margin-bottom:2px;"></h2>
        <p id="modal-subline" style="color:var(--text-gray); font-size:0.88rem; margin-bottom:12px;"></p>

        <h4 style="color:var(--text-dark); font-size:0.95rem; margin-bottom:8px;">Class Loads</h4>
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);">
                    <th style="padding: 8px; text-align: left; color: var(--text-dark);">Section</th>
                    <th style="padding: 8px; text-align: left; color: var(--text-dark);">Subject</th>
                    <th style="padding: 8px; text-align: left; color: var(--text-dark);">Prelim Avg</th>
                    <th style="padding: 8px; text-align: left; color: var(--text-dark);">At-Risk</th>
                </tr>
            </thead>
            <tbody id="modal-loads-body"></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="edit-modal-overlay" onclick="if(event.target===this) closeEditModal();">
    <div class="modal-box" style="max-width:680px;">
        <button class="modal-close" onclick="closeEditModal()">✕ Close</button>
        <h2 style="color:var(--text-dark); margin-bottom:4px;">Edit Faculty</h2>
        <p style="color:var(--text-gray); font-size:0.78rem; margin-bottom:16px;">Changes here are logged for audit.</p>

        <form method="POST" action="faculty.php" id="edit-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px;">
                <input type="text" name="edit_first_name" id="edit-first-name" placeholder="First Name" required class="form-input">
                <input type="text" name="edit_middle_name" id="edit-middle-name" placeholder="Middle Name (optional)" class="form-input">
                <input type="text" name="edit_last_name" id="edit-last-name" placeholder="Last Name" required class="form-input">
                <input type="email" name="edit_email" id="edit-email" placeholder="Email" class="form-input" style="grid-column:span 3;">
            </div>

            <h4 style="color:var(--text-dark); font-size:0.9rem; margin-bottom:8px;">Current Class Loads</h4>
            <div id="edit-loads-list" style="margin-bottom:14px; max-height: 150px; overflow-y: auto;"></div>

            <h4 style="color:var(--text-dark); font-size:0.9rem; margin-bottom:8px;">Add a Class Load</h4>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:10px; margin-bottom:18px;">
                <select name="new_subject_id" id="new_subject_id" class="form-input" onchange="updateSectionDropdown()">
                    <option value="">— Select subject —</option>
                    <?php foreach ($groupedSubjects as $groupName => $subjects): ?>
                        <optgroup label="[ <?= htmlspecialchars($groupName) ?> ]">
                            <?php foreach ($subjects as $subj): ?>
                                <option value="<?= $subj['id'] ?>" data-year="<?= $subj['year_level'] ?>">
                                    <?= htmlspecialchars($subj['code'] . ' — ' . $subj['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <select name="new_section" id="new_section" class="form-input" disabled>
                    <option value="">— Select subject first —</option>
                </select>
            </div>

            <button type="submit" style="width:100%; padding:10px; background:var(--accent-blue); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Save Changes</button>
        </form>
    </div>
</div>

<script>
const sectionsByYear = <?= json_encode($sectionsByYear) ?>;
const takenLoads = <?= json_encode($takenLoads) ?>;

function updateSectionDropdown() {
    const subjSelect = document.getElementById('new_subject_id');
    const secSelect = document.getElementById('new_section');
    const selectedOption = subjSelect.options[subjSelect.selectedIndex];
    
    if (!selectedOption.value) {
        secSelect.innerHTML = '<option value="">— Select subject first —</option>';
        secSelect.disabled = true;
        return;
    }
    
    secSelect.disabled = false;
    secSelect.innerHTML = '<option value="">— Select Section —</option>';
    
    const year = selectedOption.getAttribute('data-year');
    const subjId = selectedOption.value;
    const availableSections = sectionsByYear[year] || [];
    const takenForThisSubject = takenLoads[subjId] || {};

    if (availableSections.length === 0) {
        secSelect.innerHTML += '<option disabled>No active sections for this Year Level</option>';
        return;
    }

    availableSections.forEach(sec => {
        const option = document.createElement('option');
        option.value = sec;
        
        if (takenForThisSubject[sec]) {
            option.disabled = true;
            option.textContent = `${sec} (Assigned to ${takenForThisSubject[sec]})`;
        } else {
            option.textContent = sec;
        }
        secSelect.appendChild(option);
    });
}

const facultyModalData = <?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const ROWS_PER_PAGE = 25;
let currentPage = 1;

function getVisibleRows() {
    const term = document.getElementById('faculty-search')?.value.trim().toLowerCase() || '';
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
    if (!bar) return;

    let html = '';
    html += `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="goToPage(${currentPage-1})">‹ Prev</button>`;
    
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button class="page-btn" onclick="goToPage(1)">1</button>`;
        if (startPage > 2) html += `<span style="color:var(--text-gray);">...</span>`;
    }
    
    for (let p = startPage; p <= endPage; p++) {
        html += `<button class="page-btn ${p===currentPage?'active':''}" onclick="goToPage(${p})">${p}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span style="color:var(--text-gray);">...</span>`;
        html += `<button class="page-btn" onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }

    html += `<button class="page-btn" ${currentPage===totalPages?'disabled':''} onclick="goToPage(${currentPage+1})">Next ›</button>`;
    html += `<span style="color:var(--text-gray); font-size:0.8rem; margin-left:10px;">Showing ${visible.length} results</span>`;
    
    bar.innerHTML = html;
}

function goToPage(p) { currentPage = p; renderPage(); }

if (document.getElementById('faculty-search')) {
    document.getElementById('faculty-search').addEventListener('input', () => { currentPage = 1; renderPage(); });
}
renderPage();

function openFacultyModal(fid) {
    const d = facultyModalData[fid];
    if (!d) return;

    document.getElementById('modal-name').innerText = d.name;
    document.getElementById('modal-subline').innerText = `${d.facId} · ${d.email || 'No email on file'}`;

    const body = document.getElementById('modal-loads-body');
    if (!d.loads.length) {
        body.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-gray); padding:16px;">No class loads assigned.</td></tr>';
    } else {
        body.innerHTML = d.loads.map(l => {
            const avg = l.classAvg !== null ? l.classAvg.toFixed(2) + '%' : '—';
            const atRiskColor = l.atRisk > 0 ? 'var(--risk-high)' : 'var(--risk-low)';
            return `<tr style="border-bottom: 1px solid var(--border-color);">
                <td style="font-weight:600; color:var(--text-dark); padding: 12px; vertical-align: top;">${l.section}</td>
                <td style="color:var(--text-gray); padding: 12px; vertical-align: top;"><span style="font-weight:600; color:var(--accent-blue);">${l.code}</span><br><span style="font-size: 0.8rem;">${l.title}</span></td>
                <td style="font-weight:600; color:var(--text-dark); padding: 12px; vertical-align: top;">${avg}</td>
                <td style="color:${atRiskColor}; font-weight:600; padding: 12px; vertical-align: top;">${l.atRisk} / ${l.students}</td>
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

    document.getElementById('new_subject_id').value = '';
    updateSectionDropdown();

    const list = document.getElementById('edit-loads-list');
    if (!d.loads.length) {
        list.innerHTML = '<p style="color:var(--text-gray); font-size:0.85rem;">No class loads assigned yet.</p>';
    } else {
        list.innerHTML = d.loads.map(l => `
            <label style="display:flex; align-items:center; gap:8px; padding:8px 0; font-size:0.88rem; border-bottom:1px solid var(--border-color);">
                <input type="checkbox" name="removed_${l.loadId}" value="1">
                <span style="color:var(--risk-high); font-size:0.78rem; font-weight:600;">Remove</span>
                <span style="margin-left:auto; font-weight:600; color:var(--text-dark); min-width: 50px;">${l.section}</span>
                <span style="color:var(--text-gray); flex: 1; text-align: right; font-size: 0.8rem;" title="${l.title}">${l.code} &bull; ${l.title}</span>
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