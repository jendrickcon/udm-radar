<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$db = getDB();

$success = '';

// DELETE a grade record (admin override — e.g. an encoding mistake)
if (isset($_GET['delete'])) {
    $stmt = $db->prepare("DELETE FROM grades WHERE id = ?");
    $stmt->execute([(int) $_GET['delete']]);
    $success = 'Grade record removed.';
}

$grades = $db->query("
    SELECT g.*, s.code, s.title,
           su.first_name AS s_first, su.middle_name AS s_middle, su.last_name AS s_last, su.user_id AS student_number,
           fu.first_name AS f_first, fu.middle_name AS f_middle, fu.last_name AS f_last
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN users su ON su.id = g.student_id
    LEFT JOIN users fu ON fu.id = g.encoded_by
    ORDER BY g.school_year DESC, g.semester DESC, su.last_name, su.first_name
")->fetchAll();

$riskBadgeClass = fn(string $risk) => match (strtoupper($risk)) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'low',
};

$pageTitle = 'Manage Grades';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Manage Students',    'students.php',  '👥'],
    ['Manage Faculty',     'faculty.php',   '👨‍🏫'],
    ['Manage Grades',      'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div><h1>Manage Grades</h1><p>College-wide grade records — admin override for encoding corrections.</p></div>
    </div>

    <?php if ($success): ?>
        <p style="background:#e8f5e9; color:#1B7A3E; padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid #1B7A3E;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card">
        <?php if (empty($grades)): ?>
            <p class="empty-state">No grade records yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Student</th><th>Subject</th><th>Term</th><th>Prelim</th><th>Midterm</th><th>Pre-Final</th><th>Final</th><th>Risk</th><th>Encoded By</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g):
                    // prelim/midterm/prefinal are ALWAYS raw percentages while encoding;
                    // final_grade is ALWAYS already point-scale once set. Normalizing
                    // through the explicit, non-ambiguous helpers rather than displaying
                    // the raw stored numbers directly (that was showing "92.00" instead
                    // of the 3.25 it should read as).
                    $prelimPoint   = normalizeTermGrade($g['prelim']);
                    $midtermPoint  = normalizeTermGrade($g['midterm']);
                    $prefinalPoint = normalizeTermGrade($g['prefinal']);
                    $finalPoint    = normalizePointGrade($g['final_grade']);
                    $studentName   = formatNameLastFirst($g['s_first'], $g['s_middle'], $g['s_last']);
                    $facultyName   = $g['f_first'] !== null ? formatNameLastFirst($g['f_first'], $g['f_middle'], $g['f_last']) : '—';
                ?>
                <tr>
                    <td><?= htmlspecialchars($g['student_number'] . ' — ' . $studentName) ?></td>
                    <td><?= htmlspecialchars($g['code']) ?></td>
                    <td><?= htmlspecialchars(($g['school_year'] ?? '—') . ' S' . ($g['semester'] ?? '?')) ?></td>
                    <td><?= $g['prelim']   !== null ? round($g['prelim']) . '% (' . number_format($prelimPoint, 2) . ')'   : '—' ?></td>
                    <td><?= $g['midterm']  !== null ? round($g['midterm']) . '% (' . number_format($midtermPoint, 2) . ')' : '—' ?></td>
                    <td><?= $g['prefinal'] !== null ? round($g['prefinal']) . '% (' . number_format($prefinalPoint, 2) . ')' : '—' ?></td>
                    <td><?= $finalPoint !== null ? number_format($finalPoint, 2) : '—' ?></td>
                    <td><span class="badge <?= $riskBadgeClass($g['risk_level'] ?? 'LOW') ?>"><?= htmlspecialchars(ucfirst(strtolower($g['risk_level'] ?? 'LOW'))) ?></span></td>
                    <td><?= htmlspecialchars($facultyName) ?></td>
                    <td><a href="grades.php?delete=<?= $g['id'] ?>" onclick="return confirm('Delete this grade record?')" style="color:var(--risk-high); font-weight:600; text-decoration:none;">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
