<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once __DIR__ . '/performance_trend_model.php';
requireRole('student');
$user = currentUser();
$db = getDB();
$currentTerm = getCurrentTerm();

$stmt = $db->prepare('SELECT g.id,g.student_id,g.school_year,g.semester,g.final_grade,g.prelim,g.midterm,g.prefinal,g.is_current,s.units FROM grades g JOIN subjects s ON s.id=g.subject_id WHERE g.student_id=? ORDER BY g.school_year,g.semester,g.id');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$metaCols = getPredictionCompletenessSqlSelect($db);
$stmtPred = $db->prepare("SELECT predicted_gwa,risk_level,prediction_source,generated_at,$metaCols FROM predictions WHERE student_id=? ORDER BY generated_at DESC,id DESC LIMIT 1");
$stmtPred->execute([$user['id']]);
$model = studentTrendModel($rows, $stmtPred->fetch(PDO::FETCH_ASSOC) ?: [], $currentTerm);
$escape = fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$valueText = fn($value) => $value === null ? 'Unavailable' : number_format($value, 2);
$distinctions = ["Dean's Lister" => DEANS_LISTER, 'Cum Laude' => CUM_LAUDE, 'Magna Cum Laude' => MAGNA_CUM_LAUDE, 'Summa Cum Laude' => SUMMA_CUM_LAUDE];
$guideGroups = [];
foreach ($distinctions as $name => $threshold) {
    $key = number_format($threshold, 2, '.', '');
    if (!isset($guideGroups[$key])) $guideGroups[$key] = ['value' => $threshold, 'names' => [], 'style' => count($guideGroups)];
    $guideGroups[$key]['names'][] = $name;
}
$distinctionGuides = array_values($guideGroups);
$pageTitle = 'Performance Trend';
$navItems = [['Home','index.php',''],['Dashboard','dashboard.php',''],['Grades & History','grades.php',''],['Performance Trend','trend.php',''],['Feedback & Support','feedback.php',''],['Settings','settings.php','']];
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/student-performance-trend.css?v=<?= substr(hash_file('sha256', __DIR__.'/../assets/css/student-performance-trend.css'),0,12) ?>">
<main class="main-content student-performance-trend">
    <div class="header">
        <div>
            <h1>Performance Trend</h1>
            <p>Your completed results, current term progress, and estimated GWA.</p>
            <p class="trend-active-term" style="font-size: 0.85rem; color: var(--text-gray); margin-top: 4px;">Active Academic Period: <strong><?= htmlspecialchars($currentTerm['school_year']) ?> · Semester <?= (int)$currentTerm['semester'] ?></strong></p>
        </div>
    </div>

    <!-- 4 Primary KPI Cards with Restrained Accent Borders -->
    <div class="stat-grid kpi-grid--four">
        <div class="stat-card kpi-card--blue">
            <h2>Latest completed term</h2>
            <p class="trend-value"><?= $valueText($model['latest']) ?></p>
            <p class="trend-card-sub">Recorded completed-term GWA</p>
        </div>
        <div class="stat-card kpi-card--teal">
            <h2>Current term progress</h2>
            <p class="trend-value"><?= !empty($model['current_term_stages']['prelim']['percentage']) ? number_format($model['current_term_stages']['prelim']['percentage'], 2) . '%' : ($model['snapshot'] !== null ? number_format($model['snapshot'], 2) : 'Not recorded') ?></p>
            <p class="trend-card-sub"><?= !empty($model['current_term_stages']['prelim']['percentage']) ? 'Preliminary average · Recorded' : 'Assessment progress pending' ?></p>
        </div>
        <div class="stat-card kpi-card--amber">
            <h2>Estimated GWA <span class="provisional-badge">Provisional</span></h2>
            <p class="trend-value"><?= $valueText($model['estimate']) ?></p>
            <p class="trend-card-sub">Advisory estimate only</p>
        </div>
        <div class="stat-card kpi-card--growth">
            <h2>Growth</h2>
            <p class="trend-value"><?= $escape($model['growth']['formatted_points']) ?></p>
            <p class="trend-card-sub"><?= $escape($model['growth']['formatted_relative']) ?></p>
        </div>
    </div>

    <p class="trend-change"><?= $escape($model['change']) ?></p>

    <!-- Main Historical GWA Chart (Completed Terms & Estimate) -->
    <section class="card" aria-labelledby="trend-chart-heading">
        <h2 id="trend-chart-heading">Your performance over time</h2>
        <p id="trend-summary" class="trend-note">Current progress and estimates are not final grades.</p>
        <div class="trend-chart-toolbar">
            <ul class="trend-legend" aria-label="Evidence types">
                <li>● Recorded completed-term GWA</li>
                <li>◆ Estimated GWA (Provisional)</li>
            </ul>
        </div>
        <p class="trend-honors-caption" style="font-size: 0.8rem; color: var(--text-gray); margin: 6px 0 8px 0;">Honors guides are reference thresholds. Official eligibility remains subject to institutional review.</p>
        <ul id="trend-honors-legend" class="trend-distinction-legend" aria-label="Academic distinction reference thresholds">
        <?php foreach ($distinctionGuides as $guide): ?>
            <li><span class="trend-guide-swatch trend-guide-<?= (int)$guide['style'] ?>" aria-hidden="true"></span><span><?= $escape(implode(' / ', $guide['names'])) ?>: <?= number_format($guide['value'], 2) ?></span></li>
        <?php endforeach; ?>
        </ul>
        <div class="trend-chart"><canvas id="semGwaChart" role="img" aria-label="GWA evidence chart. Higher GWA is better; 4.00 is highest. The axis expands to include results below 1.00 when present." aria-describedby="trend-summary trend-table-caption">Read the exact evidence table below for every value and its limitations.</canvas></div>
        <p id="trend-chart-status" role="status">Open the evidence table to explore each period.</p>
    </section>

    <!-- Compact Current-Term Assessment Progress (0–100% Scale) -->
    <section class="card trend-progress-card" aria-labelledby="trend-progress-heading">
        <h2 id="trend-progress-heading" style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0 0 4px 0;">Current Term Assessment Progress (0–100% Scale)</h2>
        <p style="font-size: 0.82rem; color: var(--text-gray); margin-bottom: 14px;">Raw assessment percentages are evaluated separately and not converted into point-scale GWA.</p>
        <div class="trend-stages-strip">
            <?php foreach ($model['current_term_stages'] as $stage): ?>
            <div class="trend-stage-item">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-dark);"><?= $escape($stage['label']) ?></span>
                    <span class="status-pill <?= $stage['status'] === 'Recorded' ? '' : 'status-pill-muted' ?>" style="font-size: 0.72rem; padding: 2px 8px;">
                        <?= $escape($stage['status']) ?>
                    </span>
                </div>
                <div style="font-size: 1.5rem; font-weight: 700; color: <?= $stage['percentage'] !== null ? 'var(--accent-blue)' : 'var(--text-gray)' ?>;">
                    <?= $escape($stage['formatted_percentage']) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px;">
                    Recorded subjects: <?= $escape($stage['coverage']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Accessible Value Summary (Clean Disclosure) -->
    <section class="card" aria-labelledby="trend-table-heading">
        <details id="trend-evidence">
        <summary class="trend-evidence-toggle"><h2 id="trend-table-heading">Exact evidence and point details</h2><span class="trend-hide-table" aria-hidden="true">Hide table</span><span class="trend-show-table" aria-hidden="true">Show table</span></summary>
        <div class="trend-table-scroll" role="region" aria-label="Academic evidence table" tabindex="0">
        <table><caption id="trend-table-caption">Values share the chart's data model and two-decimal precision. Expand a period for its source and completeness.</caption>
            <thead><tr><th scope="col">Period and details</th><th scope="col">Evidence type</th><th scope="col">GWA</th><th scope="col">Eligible change</th></tr></thead>
            <tbody><?php foreach ($model['points'] as $index=>$point): ?>
            <tr><th scope="row"><details><summary data-trend-point="<?= $index ?>"><?= $escape(str_replace('Latest stored estimate', 'Latest GWA estimate', $point['period'])) ?></summary><p><?= $escape($point['detail']) ?></p></details></th>
                <td><?= $escape(str_replace(' snapshot', ' progress', $point['type'])) ?><br><span><?= $escape($point['state']) ?></span></td>
                <td><?= $point['value'] === null ? $escape($point['state'] === 'Pending' ? 'Pending' : 'Unavailable') : number_format($point['value'],2) ?></td>
                <td><?= $escape($point['comparison']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php if ($model['latest'] === null): ?><p>No complete recorded GWA is available for the selected terms.</p><?php endif; ?>
        </details>
    </section>

    <!-- Compact Help Disclosure -->
    <section class="card" style="padding: 14px 18px;">
        <details class="trend-help-disclosure">
            <summary style="font-weight: 600; color: var(--text-dark); cursor: pointer; font-size: 0.88rem;">[?] About these results and reference guides</summary>
            <ul style="margin-top: 10px; padding-left: 20px; font-size: 0.84rem; color: var(--text-gray); line-height: 1.5;">
                <li>Honors guides are reference thresholds; official eligibility requires institutional review.</li>
                <li>Estimates provide provisional decision support and do not replace official registrar evaluations.</li>
                <li>Growth reflects point differences and relative change between consecutive comparable completed terms.</li>
                <li>Preliminary and Midterm progress represent raw assessment stages on a 0–100% scale.</li>
            </ul>
        </details>
    </section>
</main>
<script id="student-trend-data" type="application/json"><?= json_encode($model['points'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script id="student-trend-guides" type="application/json"><?= json_encode($distinctionGuides, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>assets/js/student-performance-trend.js?v=<?= substr(hash_file('sha256', __DIR__.'/../assets/js/student-performance-trend.js'),0,12) ?>"></script>
<?php require_once '../includes/footer.php'; ?>
