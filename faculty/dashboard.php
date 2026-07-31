<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('faculty');
$user = currentUser();
$db   = getDB();

// ── 1. Faculty's assigned sections ────────────────────────────────────────
$stmt = $db->prepare("SELECT DISTINCT section FROM faculty_class_loads WHERE faculty_user_id = ? ORDER BY section");
$stmt->execute([$user['id']]);
$my_sections = $stmt->fetchAll(PDO::FETCH_COLUMN);

// ── 2. Class loads: each row is one (subject → section) assignment ─────────
$stmt = $db->prepare("
    SELECT s.id, s.code, s.title, fcl.section
    FROM faculty_class_loads fcl
    JOIN subjects s ON fcl.subject_id = s.id
    WHERE fcl.faculty_user_id = ?
    ORDER BY fcl.section, s.code
");
$stmt->execute([$user['id']]);
$my_class_loads = $stmt->fetchAll();

// ── 3. Per-section subject list (for tab column headers) ──────────────────
$section_subject_map = []; // [section] => [ ['id','code','title'], ... ]
foreach ($my_class_loads as $load) {
    $sec     = $load['section'];
    $already = array_column($section_subject_map[$sec] ?? [], 'id');
    if (!in_array($load['id'], $already)) {
        $section_subject_map[$sec][] = [
            'id'    => $load['id'],
            'code'  => $load['code'],
            'title' => $load['title'],
        ];
    }
}

// ── 4. All students in faculty's assigned sections ─────────────────────────
$students = [];
if (!empty($my_sections)) {
    $inSec = implode(',', array_fill(0, count($my_sections), '?'));
    $stmt  = $db->prepare("
        SELECT u.id AS user_id, u.first_name, u.middle_name, u.last_name, sp.section, sp.student_number,
               sp.status, sp.current_gwa,
               p.risk_level, p.latin_honor
        FROM users u
        JOIN student_profiles sp ON u.id = sp.user_id
        LEFT JOIN predictions p ON u.id = p.student_id
            AND p.generated_at = (
                SELECT MAX(p2.generated_at)
                FROM predictions p2
                WHERE p2.student_id = u.id
            )
        WHERE sp.section IN ($inSec) AND u.role = 'student'
        ORDER BY sp.section, u.last_name, u.first_name
    ");
    $stmt->execute($my_sections);
    $students = $stmt->fetchAll();
    foreach ($students as &$s) {
        $s['full_name']  = formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']);
        $s['short_name'] = formatNameShort($s['first_name'], $s['last_name']);
    }
    unset($s);
}

// ── 5. Grades scoped to exact (subject, section) load pairs ───────────────
$subject_grades = []; // [user_id][subject_id] = ['prelim' => x, 'risk' => y]
if (!empty($my_class_loads)) {
    $conds  = [];
    $params = [];
    foreach ($my_class_loads as $load) {
        $conds[]  = "(sp.section = ? AND g.subject_id = ?)";
        $params[] = $load['section'];
        $params[] = $load['id'];
    }
    $where = implode(' OR ', $conds);
    $stmt  = $db->prepare("
        SELECT g.student_id, g.subject_id, g.prelim, g.risk_level
        FROM grades g
        JOIN student_profiles sp ON g.student_id = sp.user_id
        WHERE ($where) AND g.is_current = 1
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $gr) {
        $subject_grades[$gr['student_id']][$gr['subject_id']] = [
            'prelim' => $gr['prelim'],
            'risk'   => $gr['risk_level'],
        ];
    }
}

// ── 6. Build section_data (at-risk = subject-specific) ────────────────────
$section_data    = [];
foreach ($my_sections as $sec) {
    $section_data[$sec] = ['students' => [], 'gwa_sum' => 0, 'at_risk' => 0, 'irregular' => 0];
}

$at_risk_total   = 0;
$irregular_total = 0;
$gwa_sum         = 0;
$gwa_count       = 0;

foreach ($students as &$s) {
    $sec = $s['section'];
    $uid = $s['user_id'];
    $gwa = (float)($s['current_gwa'] ?? 0);

    $s['subject_grades'] = $subject_grades[$uid] ?? [];

    $subj_at_risk = false;
    foreach ($s['subject_grades'] as $sg) {
        if (in_array($sg['risk'], ['HIGH', 'MODERATE'])) { $subj_at_risk = true; break; }
    }
    $s['subj_at_risk'] = $subj_at_risk;

    if ($subj_at_risk)                { $at_risk_total++;   $section_data[$sec]['at_risk']++; }
    if ($s['status'] === 'Irregular') { $irregular_total++; $section_data[$sec]['irregular']++; }
    if ($gwa > 0) {
        $gwa_sum  += $gwa; $gwa_count++;
        $section_data[$sec]['gwa_sum'] += $gwa;
    }
    $section_data[$sec]['students'][] = $s;
}
unset($s);

$overall_avg_gwa = $gwa_count > 0 ? round($gwa_sum / $gwa_count, 2) : 0;
$total_students  = count($students);

function getHonorBadge(float $gwa): array {
    if ($gwa >= 3.75) return ['text' => 'Summa Cum Laude track',     'bg' => '#b45309'];
    if ($gwa >= 3.50) return ['text' => 'Magna Cum Laude track',     'bg' => '#1d4ed8'];
    if ($gwa >= 3.25) return ['text' => "Dean's Lister / Cum Laude", 'bg' => 'var(--accent-blue)'];
    return ['text' => '—', 'bg' => 'var(--bg-color)', 'color' => 'var(--text-gray)'];
}

$pageTitle = 'Dashboard';
$navItems = [
    ['Home',               'index.php',     '🏠'],
    ['Dashboard',          'dashboard.php', '📊'],
    ['Class Analytics',    'analytics.php', '📋'],
    ['Performance Trends', 'trend.php',     '📈'],
    ['Feedback & Reports', 'feedback.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.tab-bar  { display:flex; gap:6px; margin-bottom:18px; border-bottom:2px solid var(--border-color); }
.tab-btn  { padding:8px 18px; border:none; background:transparent; color:var(--text-gray); font-size:0.88rem; font-weight:600; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all 0.18s; font-family:inherit; }
.tab-btn:hover  { color:var(--accent-blue); }
.tab-btn.active { color:var(--accent-blue); border-bottom-color:var(--accent-blue); }
.grade-cell { font-weight:600; }
.grade-low  { color:var(--risk-low); }
.grade-mod  { color:var(--risk-mod); }
.grade-high { color:var(--risk-high); }
.grade-none { color:var(--text-gray); }
.sortable-col { cursor:pointer; user-select:none; color:var(--text-dark); }
.sortable-col:hover { background:var(--table-header-bg) !important; }
.sort-arrow { font-size:0.78rem; color:var(--text-gray); margin-left:5px; transition:color 0.15s; }
</style>

<div class="main-content">

    <div class="header" style="margin-bottom:24px;">
        <div>
            <h1>Section Overview — S.Y. 2026-2027, 1st Semester</h1>
            <p style="color:var(--text-gray); font-size:0.95rem;">
                Click a section card to open its student roster.
                <em style="color:var(--text-gray); font-size:0.82rem;">At-risk counts reflect performance in your assigned class loads only.</em>
            </p>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:24px;">
        <div class="stat-card" style="border-left-color: var(--text-dark);">
            <h4>Total Students</h4>
            <h2 style="color: var(--text-dark);"><?= $total_students ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-high);">
            <h4>At-Risk <span style="font-size:0.65rem;color:var(--text-gray);font-weight:400;">(your classes)</span></h4>
            <h2 style="color: var(--risk-high);"><?= $at_risk_total ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--risk-mod);">
            <h4>Irregular</h4>
            <h2 style="color: var(--risk-mod);"><?= $irregular_total ?></h2>
        </div>
        <div class="stat-card" style="border-left-color: var(--accent-blue);">
            <h4>Overall Avg GWA</h4>
            <h2 style="color: var(--accent-blue);"><?= number_format($overall_avg_gwa, 2) ?></h2>
        </div>
    </div>

    <!-- Section cards -->
    <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); margin-bottom:24px;">
        <?php foreach ($section_data as $sec_name => $data):
            $count    = count($data['students']);
            $avg      = $count > 0 ? round($data['gwa_sum'] / $count, 2) : 0;
            $risk_col = $data['at_risk'] > 0 ? 'var(--risk-high)' : 'var(--risk-low)';
        ?>
        <div class="card" style="padding:0;overflow:hidden;border:1px solid var(--border-color);border-radius:8px;">
            <div style="background:var(--table-header-bg);color:var(--text-dark);padding:12px 16px;display:flex;justify-content:space-between;font-weight:700; border-bottom:1px solid var(--border-color);">
                <span><?= htmlspecialchars($sec_name) ?></span>
                <span style="font-size:0.8rem;color:var(--text-gray);"><?= $count ?> students</span>
            </div>
            <div style="padding:16px;text-align:center;">
                <h2 style="color:var(--accent-blue);font-size:2rem;margin-bottom:2px;"><?= number_format($avg, 2) ?></h2>
                <p style="color:var(--text-gray);font-size:0.8rem;margin-bottom:12px;">Avg GWA</p>
                <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:12px;border-top:1px solid var(--border-color);padding-top:8px;">
                    <span style="color:<?= $risk_col ?>;font-weight:600;">At-Risk: <?= $data['at_risk'] ?></span>
                    <span style="color:var(--text-gray);font-weight:600;">Irregular: <?= $data['irregular'] ?></span>
                </div>
                <button onclick="openSection('<?= $sec_name ?>')"
                    style="width:100%;padding:10px;background:var(--accent-blue);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-family:inherit;">
                    View Students →
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Roster panel -->
    <div id="section-roster-card" class="card" style="display:none;border:2px solid var(--accent-blue);margin-bottom:24px; padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 id="roster-title" style="color:var(--text-dark);font-weight:700;font-size:1.1rem; margin:0;">Student List</h3>
            <button onclick="closeRoster()"
                style="background:var(--bg-color);color:var(--text-dark);border:1px solid var(--border-color);padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:600;font-family:inherit;">
                ✕ Close
            </button>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" id="tab-btn-class"   onclick="switchTab('class')">📚 My Class Performance</button>
            <button class="tab-btn"        id="tab-btn-overall" onclick="switchTab('overall')">🏆 Overall Standing</button>
        </div>

        <!-- Tab 1: columns built dynamically by renderClassTab() -->
        <div id="tab-content-class" style="overflow-x:auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead><tr id="class-thead-row" style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color);"></tr></thead>
                <tbody id="roster-body-class"></tbody>
            </table>
        </div>

        <!-- Tab 2: overall GWA ranking for the opened section -->
        <div id="tab-content-overall" style="display:none;overflow-x:auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color); color:var(--text-dark);">
                        <th style="width:50px;text-align:center; padding:12px;">Rank</th>
                        <th style="text-align:left; padding:12px;">Student Name</th>
                        <th style="text-align:left; padding:12px;">Student No.</th>
                        <th style="text-align:center; padding:12px;">Cumulative GWA</th>
                        <th style="text-align:center; padding:12px;">Honor Track</th>
                        <th style="text-align:center; padding:12px;">Overall Risk</th>
                        <th style="text-align:center; padding:12px;">Status</th>
                    </tr>
                </thead>
                <tbody id="roster-body-overall"></tbody>
            </table>
        </div>
    </div>

    <!-- Top 10 across all sections -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="color:var(--text-dark);font-weight:700; margin:0;">🏆 Top Students — GWA Ranking</h3>
            <span style="background:rgba(217, 119, 6, 0.1);color:var(--risk-mod);padding:4px 10px;border-radius:4px;font-size:0.8rem;font-weight:600;">
                Projected — based on cumulative GWA
            </span>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color); color:var(--text-dark);">
                    <th style="width:50px;text-align:center; padding:12px;">Rank</th>
                    <th style="text-align:left; padding:12px;">Student Name</th>
                    <th style="text-align:left; padding:12px;">Section</th>
                    <th style="text-align:center; padding:12px;">GWA</th>
                    <th style="text-align:center; padding:12px;">Honor</th>
                    <th style="text-align:center; padding:12px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sorted = $students;
                usort($sorted, fn($a, $b) => (float)$b['current_gwa'] <=> (float)$a['current_gwa']);
                $medals = ['🥇', '🥈', '🥉'];
                foreach (array_slice($sorted, 0, 10) as $i => $ts):
                    $honor = getHonorBadge((float)$ts['current_gwa']);
                ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="text-align:center;font-weight:700; color:var(--text-dark); padding:12px;"><?= ($medals[$i] ?? '') . ' ' . ($i + 1) ?></td>
                    <td style="font-weight:600; color:var(--text-dark); padding:12px;"><?= htmlspecialchars($ts['full_name']) ?></td>
                    <td style="color:var(--text-gray); padding:12px;"><?= htmlspecialchars($ts['section']) ?></td>
                    <td style="font-weight:700; color:var(--accent-blue); text-align:center; padding:12px;"><?= $ts['current_gwa'] ? number_format($ts['current_gwa'], 2) : '—' ?></td>
                    <td style="text-align:center; padding:12px;">
                        <span style="background:<?= $honor['bg'] ?>;color:<?= $honor['color'] ?? 'white' ?>;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">
                            <?= $honor['text'] ?>
                        </span>
                    </td>
                    <td style="text-align:center; padding:12px;">
                        <span style="background:<?= $ts['status'] === 'Irregular' ? 'var(--risk-mod)' : 'var(--risk-low)' ?>;color:white;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">
                            <?= htmlspecialchars($ts['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const sectionDataMap     = <?= json_encode($section_data,       JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const sectionSubjectMap  = <?= json_encode($section_subject_map, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

let activeSection = null;

function openSection(secName) {
    activeSection = secName;
    document.getElementById('roster-title').innerText = 'Section ' + secName + ' — Student Roster';
    switchTab('class'); 
    document.getElementById('section-roster-card').style.display = 'block';
    document.getElementById('section-roster-card').scrollIntoView({ behavior: 'smooth' });
}

function closeRoster() {
    document.getElementById('section-roster-card').style.display = 'none';
    activeSection = null;
}

function switchTab(tab) {
    document.getElementById('tab-content-class').style.display   = tab === 'class'   ? 'block' : 'none';
    document.getElementById('tab-content-overall').style.display = tab === 'overall' ? 'block' : 'none';
    document.getElementById('tab-btn-class').classList.toggle('active',   tab === 'class');
    document.getElementById('tab-btn-overall').classList.toggle('active', tab === 'overall');
    if (!activeSection) return;
    if (tab === 'class')   renderClassTab(activeSection);
    if (tab === 'overall') renderOverallTab(activeSection);
}

let classSortCol = -1;
let classSortDir = 'desc';

function renderClassTab(secName) {
    classSortCol = -1;
    classSortDir = 'desc';

    const students = sectionDataMap[secName]?.students || [];
    const subjects = sectionSubjectMap[secName] || [];

    const thead = document.getElementById('class-thead-row');
    let thHtml = '<th class="sortable-col" onclick="sortClassTab(0)" id="col-h-0" style="padding:12px; text-align:left;">Student Name<span class="sort-arrow" id="sort-arrow-0">⇅</span></th>';
    subjects.forEach((subj, i) => {
        const colIdx = i + 1;
        thHtml += `<th class="sortable-col" onclick="sortClassTab(${colIdx})" title="${subj.code}" id="col-h-${colIdx}" style="padding:12px; text-align:center;">
            ${subj.title}<span class="sort-arrow" id="sort-arrow-${colIdx}">⇅</span>
        </th>`;
    });
    const avgColIdx = subjects.length + 1;
    thHtml += `<th class="sortable-col" onclick="sortClassTab(${avgColIdx})" id="col-h-${avgColIdx}" style="padding:12px; text-align:center;">
        Avg (My Subj.)<span class="sort-arrow" id="sort-arrow-${avgColIdx}">⇅</span>
    </th>`;
    thHtml += '<th style="padding:12px; text-align:center; color:var(--text-dark);">Subject Risk</th>';
    thead.innerHTML = thHtml;

    const tbody = document.getElementById('roster-body-class');
    if (!students.length) {
        tbody.innerHTML = `<tr><td colspan="${subjects.length + 3}" style="text-align:center;color:var(--text-gray);padding:24px;">No students in this section.</td></tr>`;
        return;
    }

    let html = '';
    students.forEach(s => {
        const grades = s.subject_grades || {};
        let prelimSum = 0, prelimCount = 0, worstRisk = 'LOW';
        let gradeCells = '';

        subjects.forEach(subj => {
            const g = grades[subj.id];
            if (g && g.prelim !== null && g.prelim !== undefined) {
                const val = parseFloat(g.prelim);
                const cls = g.risk === 'HIGH' ? 'grade-high' : (g.risk === 'MODERATE' ? 'grade-mod' : 'grade-low');
                gradeCells += `<td class="grade-cell ${cls}" style="padding:12px; text-align:center;">${val.toFixed(2)}</td>`;
                prelimSum  += val;
                prelimCount++;
                if      (g.risk === 'HIGH')                              worstRisk = 'HIGH';
                else if (g.risk === 'MODERATE' && worstRisk !== 'HIGH') worstRisk = 'MODERATE';
            } else {
                gradeCells += `<td class="grade-none" style="padding:12px; text-align:center;">—</td>`;
            }
        });

        const avg     = prelimCount > 0 ? (prelimSum / prelimCount).toFixed(2) : '—';
        const avgCls  = prelimCount > 0
            ? (parseFloat(avg) < 2.00 ? 'grade-high' : (parseFloat(avg) < 2.50 ? 'grade-mod' : 'grade-low'))
            : 'grade-none';
        
        const riskBg  = prelimCount === 0 ? 'var(--bg-color)' : (worstRisk === 'HIGH' ? 'var(--risk-high)' : (worstRisk === 'MODERATE' ? 'var(--risk-mod)' : 'var(--risk-low)'));
        const riskClr = prelimCount === 0 ? 'var(--text-gray)' : 'white';
        const riskLbl = prelimCount === 0 ? 'No Data' : worstRisk;

        html += `<tr style="border-bottom:1px solid var(--border-color);">
            <td style="font-weight:600; color:var(--text-dark); padding:12px;">${s.full_name}</td>
            ${gradeCells}
            <td class="grade-cell ${avgCls}" style="padding:12px; text-align:center;">${avg}</td>
            <td style="padding:12px; text-align:center;"><span style="background:${riskBg};color:${riskClr};padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;border:1px solid var(--border-color);">${riskLbl}</span></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function renderOverallTab(secName) {
    const students = (sectionDataMap[secName]?.students || [])
        .slice()
        .sort((a, b) => parseFloat(b.current_gwa || 0) - parseFloat(a.current_gwa || 0));

    const tbody = document.getElementById('roster-body-overall');
    if (!students.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-gray);padding:24px;">No students.</td></tr>';
        return;
    }

    const medals = ['🥇', '🥈', '🥉'];

    function honorBadge(gwa) {
        if (gwa >= 3.75) return { text: 'Summa Cum Laude track',     bg: '#b45309' };
        if (gwa >= 3.50) return { text: 'Magna Cum Laude track',     bg: '#1d4ed8' };
        if (gwa >= 3.25) return { text: "Dean's Lister / Cum Laude", bg: 'var(--accent-blue)' };
        return { text: '—', bg: 'var(--bg-color)', color: 'var(--text-gray)' };
    }

    let html = '';
    students.forEach((s, i) => {
        const gwa    = parseFloat(s.current_gwa || 0);
        const honor  = honorBadge(gwa);
        const risk   = (s.risk_level || 'N/A').toUpperCase();
        const riskBg = risk === 'HIGH' ? 'var(--risk-high)' : (risk === 'MODERATE' ? 'var(--risk-mod)' : (risk === 'LOW' ? 'var(--risk-low)' : 'var(--text-gray)'));
        const statBg = s.status === 'Irregular' ? 'var(--risk-mod)' : 'var(--risk-low)';

        html += `<tr style="border-bottom:1px solid var(--border-color);">
            <td style="text-align:center;font-weight:700; color:var(--text-dark); padding:12px;">${(medals[i] ?? '')} ${i + 1}</td>
            <td style="font-weight:600; color:var(--text-dark); padding:12px;">${s.full_name}</td>
            <td style="color:var(--text-gray); padding:12px;">${s.student_number || '—'}</td>
            <td style="font-weight:700;color:var(--accent-blue); text-align:center; padding:12px;">${gwa > 0 ? gwa.toFixed(2) : '—'}</td>
            <td style="text-align:center; padding:12px;"><span style="background:${honor.bg};color:${honor.color ?? 'white'};padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;border:1px solid var(--border-color);">${honor.text}</span></td>
            <td style="text-align:center; padding:12px;"><span style="background:${riskBg};color:white;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">${risk}</span></td>
            <td style="text-align:center; padding:12px;"><span style="background:${statBg};color:white;padding:4px 10px;border-radius:4px;font-size:0.75rem;font-weight:700;">${s.status || 'Regular'}</span></td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function sortClassTab(colIndex) {
    const tbody = document.getElementById('roster-body-class');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length || rows[0].querySelectorAll('td').length <= 1) return;

    if (classSortCol === colIndex) {
        classSortDir = classSortDir === 'desc' ? 'asc' : 'desc';
    } else {
        classSortCol = colIndex;
        classSortDir = 'desc';
    }

    document.querySelectorAll('.sort-arrow').forEach(el => {
        el.textContent = '⇅';
        el.style.color = 'var(--text-gray)';
    });
    const activeArrow = document.getElementById('sort-arrow-' + colIndex);
    if (activeArrow) {
        activeArrow.textContent = classSortDir === 'desc' ? ' ↓' : ' ↑';
        activeArrow.style.color = 'var(--accent-blue)';
    }

    rows.sort((a, b) => {
        const cellA = a.querySelectorAll('td')[colIndex]?.innerText.trim() || '';
        const cellB = b.querySelectorAll('td')[colIndex]?.innerText.trim() || '';

        const emptyA = cellA === '—' || cellA === '';
        const emptyB = cellB === '—' || cellB === '';
        if (emptyA && emptyB) return 0;
        if (emptyA) return 1;
        if (emptyB) return -1;

        const numA = parseFloat(cellA);
        const numB = parseFloat(cellB);

        if (!isNaN(numA) && !isNaN(numB)) {
            return classSortDir === 'desc' ? numB - numA : numA - numB;
        }
        return classSortDir === 'desc'
            ? cellB.localeCompare(cellA)
            : cellA.localeCompare(cellB);
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>

<?php require_once '../includes/footer.php'; ?>