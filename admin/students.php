<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

const LOCKED_COURSE   = 'Bachelor of Science in Information Technology';
const LOCKED_PASSWORD = 'default1!';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function checkCsrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
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
        $studentNo  = trim($_POST['student_number'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $college    = trim($_POST['college'] ?? 'CCS');
        $yearLevel  = (int) ($_POST['year_level'] ?? 0);
        $section    = trim($_POST['section'] ?? '');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $uid = (int) ($_POST['edit_id'] ?? 0);

        $newFirst   = trim($_POST['edit_first_name'] ?? '');
        $newMiddle  = trim($_POST['edit_middle_name'] ?? '');
        $newLast    = trim($_POST['edit_last_name'] ?? '');
        $newStatus  = trim($_POST['edit_status'] ?? 'Regular');
        $propSection = trim($_POST['propose_section'] ?? '');
        $propYear    = trim($_POST['propose_year_level'] ?? '');
        $reason      = trim($_POST['propose_reason'] ?? '');

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
                'status'     => [$old['status'], $newStatus],
            ];

            $wantsSectionChange = ($propSection !== '' && $propSection !== $old['section']);
            $wantsYearChange    = ($propYear !== '' && (int) $propYear !== (int) $old['year_level']);

            if (($wantsSectionChange || $wantsYearChange) && $reason === '') {
                $error = 'A reason is required to propose a section or year level correction.';
            } else {
                try {
                    $db->beginTransaction();

                    $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id=?")
                       ->execute([$newFirst, $newMiddle !== '' ? $newMiddle : null, $newLast, $uid]);

                    $db->prepare("UPDATE student_profiles SET status=? WHERE user_id=?")
                       ->execute([$newStatus, $uid]);

                    $logStmt = $db->prepare("
                        INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value)
                        VALUES (?, 'student', ?, ?, ?, ?)
                    ");
                    $changedCount = 0;
                    foreach ($fieldsToCheck as $field => [$oldVal, $newVal]) {
                        if ((string) $oldVal !== (string) $newVal) {
                            $logStmt->execute([$user['id'], $uid, $field, $oldVal, $newVal]);
                            $changedCount++;
                        }
                    }

                    $proposedCount = 0;
                    $propStmt = $db->prepare("
                        INSERT INTO pending_corrections (proposed_by, target_type, target_id, field_changed, old_value, new_value, reason)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    if ($wantsSectionChange) {
                        $propStmt->execute([$user['id'], 'student_section', $uid, 'section', $old['section'], $propSection, $reason]);
                        $proposedCount++;
                    }
                    if ($wantsYearChange) {
                        $propStmt->execute([$user['id'], 'student_year_level', $uid, 'year_level', $old['year_level'], $propYear, $reason]);
                        $proposedCount++;
                    }

                    $db->commit();
                    $parts = [];
                    if ($changedCount > 0)  $parts[] = "{$changedCount} field(s) updated";
                    if ($proposedCount > 0) $parts[] = "{$proposedCount} correction(s) proposed — pending confirmation";
                    $success = $parts ? implode(', ', $parts) . '.' : 'No changes were made.';
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = 'Could not update student: ' . $e->getMessage();
                }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['confirm_correction', 'reject_correction'])) {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
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
                $column = $corr['field_changed']; 
                $db->prepare("UPDATE student_profiles SET `$column` = ? WHERE user_id = ?")
                   ->execute([$corr['new_value'], $corr['target_id']]);

                $db->prepare("UPDATE pending_corrections SET status='confirmed', resolved_by=?, resolved_at=NOW() WHERE id=?")
                   ->execute([$user['id'], $corrId]);

                $db->prepare("
                    INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value)
                    VALUES (?, 'student', ?, ?, ?, ?)
                ")->execute([$user['id'], $corr['target_id'], $column, $corr['old_value'], $corr['new_value']]);

                $db->commit();
                $success = 'Correction confirmed and officially reflected.';
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Could not confirm correction: ' . $e->getMessage();
            }
        }
    }
}

$students = $db->query("
    SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa,
           u.first_name, u.middle_name, u.last_name, u.email
    FROM student_profiles sp JOIN users u ON u.id = sp.user_id
    ORDER BY sp.section, u.last_name, u.first_name
")->fetchAll();

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

$pending = $db->query("
    SELECT pc.*, u.first_name, u.middle_name, u.last_name, sp.student_number,
           au.first_name AS a_first, au.middle_name AS a_middle, au.last_name AS a_last
    FROM pending_corrections pc
    JOIN users u ON u.id = pc.target_id
    JOIN student_profiles sp ON sp.user_id = pc.target_id
    JOIN users au ON au.id = pc.proposed_by
    WHERE pc.status = 'pending' AND pc.target_type IN ('student_section','student_year_level')
    ORDER BY pc.proposed_at DESC
")->fetchAll();

$pageTitle = 'Students';
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
.locked-field {
    background: var(--bg-color) !important;
    color: var(--text-gray) !important;
    border-color: var(--border-color) !important;
    cursor: not-allowed;
}
.locked-hint {
    grid-column: span 1;
    font-size: 0.72rem;
    color: var(--text-gray);
    margin-top: -8px;
}

.table-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
}
.search-box {
    padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 8px;
    font-size: 0.9rem; width: 280px; max-width: 100%;
    background-color: var(--bg-color); color: var(--text-dark);
}
.search-box:focus {
    border-color: var(--accent-blue); outline: none; background-color: var(--card-bg);
}
.pagination-bar {
    display: flex; justify-content: center; align-items: center;
    gap: 6px; margin-top: 16px; flex-wrap: wrap;
}
.page-btn {
    padding: 6px 12px; border: 1px solid var(--border-color); background: var(--card-bg);
    border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;
    color: var(--text-dark); font-family: inherit; transition: all 0.2s;
}
.page-btn:hover { background: var(--bg-color); }
.page-btn.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.row-clickable { cursor: pointer; }
.row-clickable:hover { background: var(--bg-color); }
.row-clickable td:first-child + td { color: var(--accent-blue); font-weight: 600; }

.modal-stat-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0;
}
.modal-stat {
    background: var(--bg-color); border-radius: 8px; padding: 10px 12px; text-align: center;
    border: 1px solid var(--border-color);
}
.modal-stat span { display: block; font-size: 0.72rem; color: var(--text-gray); margin-bottom: 4px; }
.modal-stat strong { font-size: 1.15rem; color: var(--text-dark); }
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Students</h1><p style="color: var(--text-gray);">Add or remove student accounts, and view individual profiles.</p></div>
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
            Pending Section / Year Level Corrections
            <span style="background:rgba(217, 119, 6, 0.1); color:var(--risk-mod); padding:2px 8px; border-radius:4px; font-size:0.7rem; font-weight:700;"><?= count($pending) ?> awaiting confirmation</span>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px; text-align: left; color: var(--text-dark);">Student</th><th style="padding: 12px; text-align: left; color: var(--text-dark);">Field</th><th style="padding: 12px; text-align: left; color: var(--text-dark);">Was</th><th style="padding: 12px; text-align: left; color: var(--text-dark);">Proposed</th><th style="padding: 12px; text-align: left; color: var(--text-dark);">Reason</th><th style="padding: 12px; text-align: left; color: var(--text-dark);">Proposed By</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($pending as $p):
                    $studentName = formatNameLastFirst($p['first_name'], $p['middle_name'], $p['last_name']);
                    $adminName   = formatNameLastFirst($p['a_first'], $p['a_middle'], $p['a_last']);
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($p['student_number'] . ' — ' . $studentName) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($p['field_changed']) ?></td>
                    <td style="padding: 12px; color: var(--text-gray);"><?= htmlspecialchars($p['old_value'] ?? '—') ?></td>
                    <td style="padding: 12px; font-weight:700; color:var(--risk-mod);"><?= htmlspecialchars($p['new_value']) ?></td>
                    <td style="padding: 12px; font-size:0.82rem; color:var(--text-gray);"><?= htmlspecialchars($p['reason']) ?></td>
                    <td style="padding: 12px; font-size:0.82rem; color: var(--text-dark);"><?= htmlspecialchars($adminName) ?></td>
                    <td style="padding: 12px; white-space:nowrap; text-align: right;">
                        <form method="POST" action="students.php" style="display:inline;" onsubmit="return confirm('Mark this correction as officially reflected?');">
                            <input type="hidden" name="action" value="confirm_correction">
                            <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" style="background:var(--risk-low); color:white; border:none; padding:5px 10px; border-radius:5px; font-weight:600; font-size:0.78rem; cursor:pointer;">Confirm</button>
                        </form>
                        <form method="POST" action="students.php" style="display:inline;" onsubmit="return confirm('Reject this proposed correction?');">
                            <input type="hidden" name="action" value="reject_correction">
                            <input type="hidden" name="correction_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" style="background:rgba(220,38,38,0.1); color:var(--risk-high); border:none; padding:5px 10px; border-radius:5px; font-weight:600; font-size:0.78rem; cursor:pointer;">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-title">Add New Student</div>
        <form method="POST" action="students.php" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px;">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input type="text" name="first_name" placeholder="First Name" required class="form-input">
            <input type="text" name="middle_name" placeholder="Middle Name (optional)" class="form-input">
            <input type="text" name="last_name" placeholder="Last Name" required class="form-input">

            <input type="text" name="student_number" placeholder="Student No. (e.g. 23-22-041)" required class="form-input">
            <input type="email" name="email" placeholder="Email" class="form-input">
            <input type="text" name="section" placeholder="Section (e.g. IT-33)" class="form-input">

            <select name="year_level" class="form-input">
                <option value="3" selected>3rd Year</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="4">4th Year</option>
            </select>

            <div>
                <input type="text" name="course" value="<?= htmlspecialchars(LOCKED_COURSE) ?>" readonly class="form-input locked-field">
            </div>
            <div>
                <input type="text" name="password" value="<?= htmlspecialchars(LOCKED_PASSWORD) ?>" readonly class="form-input locked-field">
            </div>

            <button type="submit" style="padding:10px; background:var(--accent-blue); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; align-self:start;">Add Student</button>
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
        <table id="students-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Student No.</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Name</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Email</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Section</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Year</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">GWA</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="students-tbody">
                <?php foreach ($students as $s):
                    $uid = $s['user_id'];
                    $searchBlob = strtolower($s['student_number'] . ' ' . formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']) . ' ' . ($s['section'] ?? ''));
                ?>
                <tr class="row-clickable" data-search="<?= htmlspecialchars($searchBlob) ?>" onclick="openStudentModal(<?= $uid ?>)" style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['student_number']) ?></td>
                    <td style="padding: 12px; font-weight:600; color:var(--accent-blue);"><?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?></td>
                    <td style="padding: 12px; color: var(--text-gray);"><?= htmlspecialchars($s['email'] ?? '—') ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                    <td style="padding: 12px; color: var(--text-dark); font-weight: 600;"><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                    <td onclick="event.stopPropagation();" style="padding: 12px; text-align: right;">
                        <button type="button" onclick="openEditModal(<?= $uid ?>)"
                            style="background:none; border:none; color:var(--accent-blue); font-weight:600; cursor:pointer; font-family:inherit; padding:0; margin-right:10px;">Edit</button>
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
        <h2 id="modal-name" style="color:var(--text-dark); margin-bottom:2px;"></h2>
        <p id="modal-subline" style="color:var(--text-gray); font-size:0.88rem; margin-bottom:12px;"></p>

        <div class="modal-stat-grid">
            <div class="modal-stat"><span>Current GWA</span><strong id="modal-gwa">—</strong></div>
            <div class="modal-stat"><span>Predicted GWA</span><strong id="modal-predicted">—</strong></div>
            <div class="modal-stat"><span>Risk Level</span><strong id="modal-risk">—</strong></div>
        </div>

        <h4 style="color:var(--text-dark); font-size:0.95rem; margin-bottom:8px;">Current Semester Grades</h4>
        <table style="width:100%; border-collapse: collapse;">
            <thead><tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);"><th style="padding: 8px; text-align: left; color: var(--text-dark);">Subject</th><th style="padding: 8px; text-align: left; color: var(--text-dark);">Prelim</th><th style="padding: 8px; text-align: left; color: var(--text-dark);">Risk</th></tr></thead>
            <tbody id="modal-grades-body"></tbody>
        </table>

        <!-- Smooth Expanding History Accordion -->
        <div style="margin-top:18px; border-top:1px solid var(--border-color); padding-top:14px;">
            <button type="button" id="modal-history-toggle" onclick="toggleHistory()"
                style="background:var(--bg-color); border:1px solid var(--border-color); color:var(--accent-blue); padding:8px 14px; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.85rem; font-family:inherit; transition:all 0.2s ease;">
                <span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); margin-right:4px;">▶</span> View Y1–Y2 Grade History
            </button>
            <div id="modal-history-wrapper" class="history-wrapper">
                <div id="modal-history-container" class="history-inner"></div>
            </div>
        </div>
    </div>
</div>

<!-- Edit modal -->
<div class="modal-overlay" id="edit-modal-overlay" onclick="if(event.target===this) closeEditModal();">
    <div class="modal-box">
        <button class="modal-close" onclick="closeEditModal()">✕ Close</button>
        <h2 style="color:var(--text-dark); margin-bottom:4px;">Edit Student</h2>
        <p style="color:var(--text-gray); font-size:0.78rem; margin-bottom:16px;">
            Name and status save immediately. Grades are not editable here at all.
        </p>

        <form method="POST" action="students.php" id="edit-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:18px;">
                <input type="text" name="edit_first_name" id="edit-first-name" placeholder="First Name" required class="form-input">
                <input type="text" name="edit_middle_name" id="edit-middle-name" placeholder="Middle Name (optional)" class="form-input">
                <input type="text" name="edit_last_name" id="edit-last-name" placeholder="Last Name" required class="form-input" style="grid-column:span 2;">
                <select name="edit_status" id="edit-status" class="form-input" style="grid-column:span 2;">
                    <option value="Regular">Regular</option>
                    <option value="Irregular">Irregular</option>
                </select>
            </div>

            <div style="background:rgba(217, 119, 6, 0.1); border:1px solid var(--risk-mod); border-radius:8px; padding:14px; margin-bottom:16px;">
                <p style="font-size:0.8rem; font-weight:700; color:var(--risk-mod); margin-bottom:2px;">Section &amp; Year Level</p>
                <p style="font-size:0.75rem; color:var(--risk-mod); margin-bottom:12px;">
                    UdM-RADAR does not officially own enrollment placement. Changing these proposes a correction — it will not take effect until separately confirmed as officially reflected by the registrar.
                </p>

                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:10px;">
                    <input type="text" name="propose_section" id="propose-section" placeholder="Current section" class="form-input">
                    <select name="propose_year_level" id="propose-year-level" class="form-input">
                        <option value="">— No change —</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <input type="text" name="propose_reason" id="propose-reason" placeholder="Reason (required only if proposing a section/year change)" class="form-input" style="width:100%;">
            </div>

            <button type="submit" style="width:100%; padding:10px; background:var(--accent-blue); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Save Changes</button>
        </form>
    </div>
</div>

<script>
const studentModalData = <?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

// --- Smart Pagination Engine ---
const ROWS_PER_PAGE = 25;
let currentPage = 1;

function getVisibleRows() {
    const term = document.getElementById('student-search')?.value.trim().toLowerCase() || '';
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

function goToPage(p) {
    currentPage = p;
    renderPage();
}

if (document.getElementById('student-search')) {
    document.getElementById('student-search').addEventListener('input', () => {
        currentPage = 1;
        renderPage();
    });
}
renderPage();

// ── Modal ───────────────────────────────────────────────────────────────
function riskColor(risk) {
    if (risk === 'HIGH') return 'var(--risk-high)';
    if (risk === 'MODERATE') return 'var(--risk-mod)';
    if (risk === 'LOW') return 'var(--risk-low)';
    return 'var(--text-gray)';
}

function openStudentModal(uid) {
    const d = studentModalData[uid];
    if (!d) return;

    currentModalUid = uid; 

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
        body.innerHTML = '<tr><td colspan="3" style="text-align:center; color:var(--text-gray); padding:16px;">No current-term grades on record.</td></tr>';
    } else {
        body.innerHTML = d.grades.map(g => {
            const prelim = g.prelim !== null ? parseFloat(g.prelim).toFixed(2) : '—';
            const rc = riskColor(g.risk);
            return `<tr style="border-bottom: 1px solid var(--border-color);">
                <td title="${g.title}" style="color: var(--text-dark); padding: 8px;">${g.code}</td>
                <td style="font-weight:600; color: var(--text-dark); padding: 8px;">${prelim}</td>
                <td style="padding: 8px;"><span style="background:${rc}; color:white; padding:4px 10px; border-radius:4px; font-size:0.72rem; font-weight:700;">${g.risk || '—'}</span></td>
            </tr>`;
        }).join('');
    }

    document.getElementById('modal-history-wrapper').classList.remove('open');
    document.getElementById('modal-history-container').innerHTML = '';
    document.getElementById('modal-history-toggle').innerHTML = '<span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); margin-right:4px;">▶</span> View Y1–Y2 Grade History';

    document.getElementById('student-modal-overlay').classList.add('open');
}

let currentModalUid = null;

function toggleHistory() {
    const wrapper = document.getElementById('modal-history-wrapper');
    const container = document.getElementById('modal-history-container');
    const btn = document.getElementById('modal-history-toggle');
    const isOpen = wrapper.classList.contains('open');

    if (isOpen) {
        wrapper.classList.remove('open');
        btn.innerHTML = '<span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); margin-right:4px;">▶</span> View Y1–Y2 Grade History';
        return;
    }

    if (!container.innerHTML) {
        const d = studentModalData[currentModalUid];
        const terms = Object.keys(d.history || {});
        if (!terms.length) {
            container.innerHTML = '<p style="color:var(--text-gray); font-size:0.85rem; text-align:center; padding:12px;">No historical (Y1–Y2) grades on record for this student.</p>';
        } else {
            container.innerHTML = terms.map(term => {
                const rows = d.history[term].map(g => `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td title="${g.title}" style="color:var(--text-dark); padding: 8px;">${g.code}</td>
                        <td style="font-weight:600; color:var(--text-dark); padding: 8px;">${g.grade.toFixed(2)}</td>
                    </tr>`).join('');
                return `
                    <div style="margin-bottom:14px; margin-top:14px;">
                        <div style="font-weight:700; color:var(--text-dark); font-size:0.85rem; margin-bottom:6px;">${term}</div>
                        <table style="width:100%; border-collapse: collapse;">
                            <thead><tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);">
                                <th style="text-align:left; padding: 8px; color:var(--text-dark);">Subject</th>
                                <th style="text-align:left; padding: 8px; color:var(--text-dark);">Final Grade</th>
                            </tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`;
            }).join('');
        }
    }

    wrapper.classList.add('open');
    btn.innerHTML = '<span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); margin-right:4px; transform: rotate(90deg);">▶</span> Hide Y1–Y2 Grade History';
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
    document.getElementById('edit-status').value = d.status || 'Regular';

    document.getElementById('propose-section').value = d.section || '';
    document.getElementById('propose-year-level').value = '';
    document.getElementById('propose-reason').value = '';

    document.getElementById('edit-modal-overlay').classList.add('open');
}

function closeEditModal() {
    document.getElementById('edit-modal-overlay').classList.remove('open');
}
</script>

<?php require_once '../includes/footer.php'; ?>