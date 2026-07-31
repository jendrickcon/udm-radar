<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

$yearFilter    = trim($_GET['year'] ?? '');
$sectionFilter = trim($_GET['section'] ?? '');

$sql = "
    SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa,
           u.first_name, u.middle_name, u.last_name, p.risk_level, p.predicted_gwa
    FROM student_profiles sp
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN predictions p ON p.student_id = sp.user_id
        AND p.generated_at = (
            SELECT MAX(p2.generated_at)
            FROM predictions p2
            WHERE p2.student_id = sp.user_id
        )
    WHERE 1=1
";
$params = [];
if ($yearFilter !== '')    { $sql .= " AND sp.year_level = ?"; $params[] = $yearFilter; }
if ($sectionFilter !== '') { $sql .= " AND sp.section = ?";    $params[] = $sectionFilter; }
$sql .= " ORDER BY sp.section, u.last_name, u.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$total     = count($students);
$atRisk    = count(array_filter($students, fn($s) => $s['risk_level'] !== null && strtoupper($s['risk_level']) !== 'LOW'));
$highRisk  = count(array_filter($students, fn($s) => strtoupper($s['risk_level'] ?? '') === 'HIGH'));
$modRisk   = count(array_filter($students, fn($s) => strtoupper($s['risk_level'] ?? '') === 'MODERATE'));
$lowRisk   = count(array_filter($students, fn($s) => strtoupper($s['risk_level'] ?? '') === 'LOW'));
$noPredict = count(array_filter($students, fn($s) => $s['risk_level'] === null));
$irregular = count(array_filter($students, fn($s) => ($s['status'] ?? 'Regular') === 'Irregular'));
$gwas      = array_filter(array_column($students, 'current_gwa'), fn($g) => $g !== null);
$avgGwa    = count($gwas) ? array_sum($gwas) / count($gwas) : null;

$sectionGwas = [];
foreach ($students as $s) {
    $sec = $s['section'];
    if ($sec && $s['current_gwa'] !== null) {
        $sectionGwas[$sec][] = (float)$s['current_gwa'];
    }
}
ksort($sectionGwas); 
$chartSectionLabels = [];
$chartSectionAverages = [];
foreach ($sectionGwas as $sec => $grades) {
    $chartSectionLabels[] = $sec;
    $chartSectionAverages[] = round(array_sum($grades) / count($grades), 2);
}

$years    = array_column($db->query("SELECT DISTINCT year_level FROM student_profiles ORDER BY year_level")->fetchAll(), 'year_level');
$sections = array_column($db->query("SELECT DISTINCT section FROM student_profiles WHERE section IS NOT NULL ORDER BY section")->fetchAll(), 'section');

$riskBadgeClass = fn(?string $risk) => match ($risk !== null ? strtoupper($risk) : null) {
    'LOW' => 'low', 'MODERATE' => 'mod', 'HIGH' => 'high', default => 'na',
};

$pageTitle = 'Admin Dashboard';
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.sortable-col { cursor: pointer; user-select: none; transition: background 0.15s; }
.sortable-col:hover { background: var(--table-header-bg) !important; }
.sort-arrow { font-size: 0.78rem; color: var(--text-gray); margin-left: 5px; transition: color 0.15s; }
.filter-select {
    padding: 8px 12px; border-radius: 6px; 
    border: 1px solid var(--border-color); 
    background-color: var(--bg-color); 
    color: var(--text-dark);
}

/* Pagination & Search Styles */
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
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Admin Dashboard</h1>
            <p>College-wide overview — College of Computing Studies.</p>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="index.php" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
            <label style="font-weight:600; font-size:0.9rem; color: var(--text-dark);">Year Level:</label>
            <select name="year" onchange="this.form.submit()" class="filter-select">
                <option value="">All Years</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= htmlspecialchars($y) ?>" <?= $yearFilter == $y ? 'selected' : '' ?>>Year <?= htmlspecialchars($y) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label style="font-weight:600; font-size:0.9rem; color: var(--text-dark);">Section:</label>
            <select name="section" onchange="this.form.submit()" class="filter-select">
                <option value="">All Sections</option>
                <?php foreach ($sections as $sec): ?>
                <option value="<?= htmlspecialchars($sec) ?>" <?= $sectionFilter === $sec ? 'selected' : '' ?>><?= htmlspecialchars($sec) ?></option>
                <?php endforeach; ?>
            </select>
            
            <span style="color:var(--accent-blue); font-size:0.9rem; margin-left: auto; font-weight: 600;">
                <?= $total ?> student<?= $total !== 1 ? 's' : '' ?> shown
            </span>
        </form>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><h4>Total Students</h4><h2><?= $total ?></h2></div>
        
        <div class="stat-card <?= $atRisk > 0 ? 'red' : 'green' ?>">
            <h4>At-Risk</h4>
            <h2 style="margin-bottom: 2px;"><?= $atRisk ?></h2>
            <?php if ($atRisk > 0): ?>
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--risk-high); margin-top: 4px;">
                    <?= $highRisk ?> High <span style="color: var(--text-gray); font-weight: normal; margin: 0 4px;">|</span> <span style="color: var(--risk-mod);"><?= $modRisk ?> Moderate</span>
                </div>
            <?php else: ?>
                <div style="font-size: 0.8rem; font-weight: 600; color: var(--risk-low); margin-top: 4px;">All clear</div>
            <?php endif; ?>
        </div>

        <div class="stat-card gold"><h4>Irregular</h4><h2><?= $irregular ?></h2></div>
        <div class="stat-card teal"><h4>Overall Avg GWA</h4><h2><?= $avgGwa !== null ? number_format($avgGwa, 2) : '—' ?></h2></div>
        <?php if ($noPredict > 0): ?>
        <div class="stat-card">
            <h4>No Prediction Yet</h4>
            <h2 style="color:var(--text-gray);"><?= $noPredict ?></h2>
            <div style="font-size:0.8rem; color:var(--text-gray); margin-top:4px;">Excluded from At-Risk count</div>
        </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px;">
        <div class="card" style="position: relative; height: 320px;">
            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">College-Wide Risk Distribution</div>
            <div style="position: relative; height: 240px; width: 100%;">
                <canvas id="riskDonutChart"></canvas>
            </div>
        </div>
        
        <div class="card" style="position: relative; height: 320px;">
            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 16px;">Average GWA by Section</div>
            <div style="position: relative; height: 240px; width: 100%;">
                <canvas id="sectionGwaBarChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-toolbar">
            <div class="table-title" style="margin:0;">Students Database</div>
            <input type="text" id="dashboard-search" class="search-box" placeholder="Search by name, student no., or section…">
        </div>

        <?php if (empty($students)): ?>
            <p class="empty-state">No students match this filter.</p>
        <?php else: ?>
        <table id="admin-table">
            <thead>
                <tr>
                    <th class="sortable-col" data-type="string" onclick="sortTable(0)">Student No. <span class="sort-arrow" id="sort-arrow-0">⇅</span></th>
                    <th class="sortable-col" data-type="string" onclick="sortTable(1)">Name <span class="sort-arrow" id="sort-arrow-1">⇅</span></th>
                    <th class="sortable-col" data-type="string" onclick="sortTable(2)">Section <span class="sort-arrow" id="sort-arrow-2">⇅</span></th>
                    <th class="sortable-col" data-type="number" onclick="sortTable(3)">Year <span class="sort-arrow" id="sort-arrow-3">⇅</span></th>
                    <th class="sortable-col" data-type="number" onclick="sortTable(4)">GWA <span class="sort-arrow" id="sort-arrow-4">⇅</span></th>
                    <th class="sortable-col" data-type="number" onclick="sortTable(5)">Predicted GWA <span class="sort-arrow" id="sort-arrow-5">⇅</span></th>
                    <th class="sortable-col" data-type="string" onclick="sortTable(6)">Status <span class="sort-arrow" id="sort-arrow-6">⇅</span></th>
                    <th class="sortable-col" data-type="risk" onclick="sortTable(7)">Risk <span class="sort-arrow" id="sort-arrow-7">⇅</span></th>
                </tr>
            </thead>
            <tbody id="admin-tbody">
                <?php foreach ($students as $s):
                    $riskRaw = $s['risk_level'] !== null ? strtoupper($s['risk_level']) : 'NA';
                    $searchBlob = strtolower($s['student_number'] . ' ' . formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']) . ' ' . ($s['section'] ?? ''));
                ?>
                <tr data-search="<?= htmlspecialchars($searchBlob) ?>">
                    <td data-sort="<?= htmlspecialchars($s['student_number']) ?>"><?= htmlspecialchars($s['student_number']) ?></td>
                    <td data-sort="<?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?>" style="font-weight: 500; color: var(--accent-blue);"><?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?></td>
                    <td data-sort="<?= htmlspecialchars($s['section'] ?? '') ?>"><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                    <td data-sort="<?= $s['year_level'] !== null ? (int) $s['year_level'] : '' ?>"><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                    <td data-sort="<?= $s['current_gwa'] !== null ? (float) $s['current_gwa'] : '' ?>" style="font-weight: 600;"><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                    <td data-sort="<?= $s['predicted_gwa'] !== null ? (float) $s['predicted_gwa'] : '' ?>" style="font-weight: 600;"><?= $s['predicted_gwa'] !== null ? number_format($s['predicted_gwa'], 2) : '<span style="color: var(--text-gray);">N/A</span>' ?></td>
                    <td data-sort="<?= htmlspecialchars($s['status'] ?? 'Regular') ?>"><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                    <td data-sort="<?= $riskRaw ?>"><span class="badge <?= $riskBadgeClass($s['risk_level']) ?>"><?= $s['risk_level'] !== null ? htmlspecialchars(ucfirst(strtolower($s['risk_level']))) : 'N/A' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination-bar" id="pagination-bar"></div>
        <?php endif; ?>
    </div>
</div>

<script>
// --- Chart.js Implementations ---
const ctxDonut = document.getElementById('riskDonutChart').getContext('2d');
new Chart(ctxDonut, {
    type: 'doughnut',
    data: {
        labels: ['High Risk', 'Moderate Risk', 'Low Risk', 'No Prediction Yet'],
        datasets: [{
            data: [<?= $highRisk ?>, <?= $modRisk ?>, <?= $lowRisk ?>, <?= $noPredict ?>],
            backgroundColor: ['#DC2626', '#D97706', '#059669', '#64748B'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } }
        }
    }
});

const ctxBar = document.getElementById('sectionGwaBarChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartSectionLabels) ?>,
        datasets: [{
            label: 'Average GWA',
            data: <?= json_encode($chartSectionAverages) ?>,
            backgroundColor: '#1E4DB7',
            borderRadius: 4,
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { min: 1.0, max: 4.0, ticks: { stepSize: 0.5 }, title: { display: true, text: 'GWA (4.00 = Highest)' } }
        }
    }
});

// --- Search & Pagination Engine ---
const ROWS_PER_PAGE = 25;
let currentPage = 1;

function getVisibleRows() {
    const term = document.getElementById('dashboard-search')?.value.trim().toLowerCase() || '';
    const allRows = Array.from(document.querySelectorAll('#admin-tbody tr'));
    return allRows.filter(row => !term || row.dataset.search.includes(term));
}

function renderPage() {
    const allRows = Array.from(document.querySelectorAll('#admin-tbody tr'));
    const visible = getVisibleRows();
    
    allRows.forEach(r => r.style.display = 'none'); // Hide all

    const totalPages = Math.max(1, Math.ceil(visible.length / ROWS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    
    const start = (currentPage - 1) * ROWS_PER_PAGE;
    visible.slice(start, start + ROWS_PER_PAGE).forEach(r => r.style.display = '');

    const bar = document.getElementById('pagination-bar');
    if (!bar) return;

    let html = '';
    html += `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="goToPage(${currentPage-1})">‹ Prev</button>`;
    
    // Smart page numbering for large datasets
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

if (document.getElementById('dashboard-search')) {
    document.getElementById('dashboard-search').addEventListener('input', () => {
        currentPage = 1;
        renderPage();
    });
}

// Initial render
renderPage();

// --- Table Sorting Script ---
let currentSortCol = -1;
let currentSortDir = 'asc';

function sortTable(colIndex) {
    const tbody = document.getElementById('admin-tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) return;

    if (currentSortCol === colIndex) {
        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortCol = colIndex;
        currentSortDir = 'asc';
    }

    document.querySelectorAll('.sort-arrow').forEach(el => {
        el.textContent = '⇅';
        el.style.color = 'var(--text-gray)';
    });
    const activeArrow = document.getElementById('sort-arrow-' + colIndex);
    if (activeArrow) {
        activeArrow.textContent = currentSortDir === 'asc' ? ' ↑' : ' ↓';
        activeArrow.style.color = 'var(--accent-blue)'; 
    }

    const colType = document.querySelectorAll('#admin-table th')[colIndex]?.dataset.type || 'string';
    const getSort = (row, idx) => row.querySelectorAll('td')[idx]?.dataset.sort ?? '';

    rows.sort((a, b) => {
        let rawA = getSort(a, colIndex);
        let rawB = getSort(b, colIndex);
        const emptyA = rawA === '';
        const emptyB = rawB === '';
        if (emptyA && emptyB) return 0;
        if (emptyA) return 1;
        if (emptyB) return -1;
        let diff = 0;

        if (colType === 'risk') {
            const riskMap = { 'HIGH': 3, 'MODERATE': 2, 'LOW': 1, 'NA': 0 };
            diff = (riskMap[rawA] ?? -1) - (riskMap[rawB] ?? -1);
        } else if (colType === 'number') {
            diff = parseFloat(rawA) - parseFloat(rawB);
        } else {
            diff = rawA.localeCompare(rawB);
        }

        if (diff === 0 && colIndex === 2) {
            diff = getSort(a, 1).localeCompare(getSort(b, 1));
            if (currentSortDir === 'desc') diff = -diff;
        }

        if (diff === 0 && colIndex === 7) {
            let gwaA = parseFloat(getSort(a, 4));
            let gwaB = parseFloat(getSort(b, 4));
            gwaA = isNaN(gwaA) ? Infinity : gwaA;
            gwaB = isNaN(gwaB) ? Infinity : gwaB;
            diff = gwaA - gwaB;
            if (diff === 0) diff = getSort(a, 1).localeCompare(getSort(b, 1));
            if (currentSortDir === 'desc') diff = -diff;
        }

        return currentSortDir === 'asc' ? diff : -diff;
    });

    rows.forEach(row => tbody.appendChild(row));
    
    // Important: Reset to page 1 and re-render so pagination applies to the newly sorted array
    currentPage = 1;
    renderPage();
}
</script>

<?php require_once '../includes/footer.php'; ?>