<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once __DIR__ . '/performance_trend_model.php';
requireRole('student');
$user = currentUser();
$db = getDB();
$stmt = $db->prepare('SELECT g.id,g.student_id,g.school_year,g.semester,g.final_grade,g.prelim,g.midterm,g.prefinal,g.is_current,s.units FROM grades g JOIN subjects s ON s.id=g.subject_id WHERE g.student_id=? ORDER BY g.school_year,g.semester,g.id');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$metaCols = getPredictionCompletenessSqlSelect($db);
$stmtPred = $db->prepare("SELECT predicted_gwa,risk_level,prediction_source,generated_at,$metaCols FROM predictions WHERE student_id=? ORDER BY generated_at DESC,id DESC LIMIT 1");
$stmtPred->execute([$user['id']]);
$model = studentTrendModel($rows, $stmtPred->fetch(PDO::FETCH_ASSOC) ?: []);
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
    <div class="header"><div><h1>Performance Trend</h1><p>Your completed results, current term progress, and estimated GWA.</p></div></div>
    <div class="stat-grid kpi-grid--three">
        <?php foreach (['Latest completed term'=>$model['latest'],'Current term progress'=>$model['snapshot'],'Estimated GWA'=>$model['estimate']] as $label=>$value): ?>
        <div class="stat-card"><h2><?= $escape($label) ?></h2><p class="trend-value"><?= $valueText($value) ?></p></div>
        <?php endforeach; ?>
    </div>
    <p class="trend-change"><?= $escape($model['change']) ?></p>
    <section class="card" aria-labelledby="trend-chart-heading">
        <h2 id="trend-chart-heading">Your performance over time</h2>
        <p id="trend-summary" class="trend-note">Current progress and estimates are not final grades.</p>
        <div class="trend-chart-toolbar">
            <ul class="trend-legend" aria-label="Evidence types"><li>● Recorded</li><li>■ Current progress</li><li>◆ Estimated GWA</li></ul>
            <label class="trend-honors-toggle"><input type="checkbox" id="trend-honors-toggle" aria-controls="trend-honors-legend semGwaChart" disabled> Honors guides</label>
        </div>
        <ul id="trend-honors-legend" class="trend-distinction-legend" aria-label="Academic distinction reference thresholds" hidden>
        <?php foreach ($distinctionGuides as $guide): ?>
            <li><span class="trend-guide-swatch trend-guide-<?= (int)$guide['style'] ?>" aria-hidden="true"></span><span><?= $escape(implode(' / ', $guide['names'])) ?>: <?= number_format($guide['value'], 2) ?></span></li>
        <?php endforeach; ?>
        </ul>
        <div class="trend-chart"><canvas id="semGwaChart" role="img" aria-label="GWA evidence chart. Higher GWA is better; 4.00 is highest. The axis expands to include results below 1.00 when present." aria-describedby="trend-summary trend-connector-note trend-table-caption">Read the exact evidence table below for every value and its limitations.</canvas></div>
        <p id="trend-chart-status" role="status">The exact evidence table remains available if the chart cannot load.</p>
        <details class="trend-about"><summary>About this chart</summary>
            <p id="trend-connector-note">The dashed connector links different evidence stages for visual reading only, not completed-term growth or a forecast path. Missing historical terms remain gaps in the recorded series.</p>
            <p>Honors guides are reference thresholds; eligibility requires institutional review.</p>
            <p>Estimates are not official academic standing. Forecast range unavailable.</p>
            <p>Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.</p>
        </details>
    </section>
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
    <p class="trend-note">Prototype performance on synthetic academic records.</p>
</main>
<script id="student-trend-data" type="application/json"><?= json_encode($model['points'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script id="student-trend-guides" type="application/json"><?= json_encode($distinctionGuides, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>assets/js/student-performance-trend.js?v=<?= substr(hash_file('sha256', __DIR__.'/../assets/js/student-performance-trend.js'),0,12) ?>"></script>
<?php require_once '../includes/footer.php'; ?>
