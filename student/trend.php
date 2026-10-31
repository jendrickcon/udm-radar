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
            <p class="trend-value"><?= $escape($model['current_progress']['primary_value']) ?></p>
            <p class="trend-card-sub">
                <?= $escape($model['current_progress']['subtitle']) ?>
                <?php if ($model['current_progress']['has_recorded_stage']): ?>
                    · <?= $escape($model['current_progress']['coverage_text']) ?>
                <?php endif; ?>
            </p>
            <?php if (!empty($model['current_progress']['earlier_context'])): ?>
                <p class="trend-card-earlier" style="font-size: 0.72rem; color: var(--text-gray); margin-top: 4px; font-weight: 500;">
                    <?= $escape($model['current_progress']['earlier_context']) ?>
                </p>
            <?php endif; ?>
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
    <section class="card trend-chart-card" aria-labelledby="trend-chart-heading">
        <div class="trend-chart-header">
            <div class="trend-chart-title-area">
                <h2 id="trend-chart-heading">Your performance over time</h2>
                <p id="trend-summary" class="trend-chart-subtitle">Recorded results, current progress, and estimate.</p>
                <ul class="trend-series-legend" aria-label="Evidence types">
                    <li><span class="legend-marker legend-marker--circle" aria-hidden="true"></span> Completed GWA</li>
                    <li><span class="legend-marker legend-marker--circle-outline" aria-hidden="true"></span> Current assessment point</li>
                    <li><span class="legend-marker legend-marker--diamond" aria-hidden="true"></span> Estimated GWA</li>
                </ul>
            </div>
            <div class="trend-honors-header-block" aria-label="Academic distinction reference thresholds">
                <p class="trend-honors-disclaimer">
                    <span class="trend-disclaimer-line">Honors guides are reference thresholds.</span>
                    <span class="trend-disclaimer-line">Official eligibility remains subject to institutional review.</span>
                </p>
                <div class="trend-honors-guide-row">
                <?php foreach ($distinctionGuides as $guide): ?>
                    <span class="trend-guide-item">
                        <span class="trend-guide-swatch trend-guide-<?= (int)$guide['style'] ?>" aria-hidden="true"></span>
                        <span><?= $escape(implode(' / ', $guide['names'])) ?> <strong><?= number_format($guide['value'], 2) ?></strong></span>
                    </span>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="trend-chart"><canvas id="semGwaChart" role="img" aria-label="GWA evidence chart. Higher GWA is better; 4.00 is highest. The axis expands to include results below 1.00 when present." aria-describedby="trend-summary trend-table-caption">Read the exact values table below for every value and its limitations.</canvas></div>
        <p id="trend-chart-status" role="status" class="trend-assistive-status">Open the exact values table to explore each period.</p>
    </section>

    <!-- Compact Current-Term Assessment Progress (0–100% Scale) -->
    <section class="card trend-progress-card" aria-labelledby="trend-progress-heading">
        <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
            <div>
                <h2 id="trend-progress-heading" style="font-size: 1.05rem; font-weight: 700; color: var(--text-dark); margin: 0 0 2px 0;">Current Term Assessment Progress</h2>
                <p style="font-size: 0.80rem; color: var(--text-gray); margin: 0;">SY <?= $escape($model['active_term']['school_year']) ?> · Semester <?= (int)$model['active_term']['semester'] ?></p>
            </div>
            <p style="font-size: 0.78rem; color: var(--text-gray); margin: 0;">0–100% assessment scale · Not converted to GWA</p>
        </div>
        <div class="trend-stages-strip">
            <?php foreach ($model['current_term_stages'] as $stage):
                $isLatest = ($stage['field'] === ($model['current_progress']['latest_stage_field'] ?? null));
            ?>
            <div class="trend-stage-item<?= $isLatest ? ' trend-stage-item--latest' : '' ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <span style="font-size: 0.82rem; font-weight: 600; color: var(--text-dark);">
                        <?= $escape($stage['label']) ?>
                        <?php if ($isLatest): ?>
                            <span class="trend-latest-badge">Latest</span>
                        <?php endif; ?>
                    </span>
                    <span class="status-pill <?= $stage['status'] === 'Recorded' ? 'status-pill-success' : 'status-pill-muted' ?>" style="font-size: 0.70rem; padding: 1px 7px;">
                        <?= $escape($stage['status']) ?>
                    </span>
                </div>
                <div style="font-size: 1.35rem; font-weight: 700; color: <?= $stage['percentage'] !== null ? 'var(--accent-blue)' : 'var(--text-gray)' ?>; line-height: 1.2;">
                    <?= $escape($stage['formatted_percentage']) ?>
                </div>
                <div style="font-size: 0.74rem; color: var(--text-gray); margin-top: 4px;">
                    <?php if ($stage['status'] === 'Recorded'): ?>
                        <?= $escape($stage['recorded_count']) ?> of <?= $escape($stage['total_count']) ?> subjects
                    <?php else: ?>
                        Assessment pending
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- View Exact Values Disclosure -->
    <details id="trend-evidence" class="trend-disclosure portal-info-disclosure">
        <summary class="trend-disclosure-summary trend-evidence-toggle" aria-controls="trend-table-region">
            <svg class="trend-disclosure-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="3" y1="9" x2="21" y2="9"></line>
                <line x1="3" y1="15" x2="21" y2="15"></line>
                <line x1="9" y1="3" x2="9" y2="21"></line>
            </svg>
            <span class="trend-disclosure-title">View exact values</span>
        </summary>
        <div id="trend-table-region" class="trend-disclosure-content">
            <div class="trend-table-scroll" role="region" aria-label="Academic performance table" tabindex="0">
                <table class="trend-exact-table">
                    <caption id="trend-table-caption">Academic performance summary across completed terms, current assessment progress, and advisory estimates.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Academic period</th>
                            <th scope="col">Result type</th>
                            <th scope="col">Value</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($model['accessible_summary'] as $row): ?>
                        <tr<?= !empty($row['is_current_group']) ? ' class="trend-current-group-row"' : '' ?>>
                            <td>
                                <?php if (!empty($row['hide_period_visually'])): ?>
                                    <span class="sr-only"><?= $escape($row['period']) ?></span>
                                <?php else: ?>
                                    <?= $escape($row['period']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $escape($row['result_type']) ?></td>
                            <td><strong><?= $escape($row['value']) ?></strong></td>
                            <td>
                                <?php
                                $statusClass = str_contains($row['status'], 'Converted')
                                    ? 'status-pill-teal'
                                    : ($row['status'] === 'Recorded' ? 'status-pill-success' : ($row['status'] === 'Provisional' ? 'status-pill-warning' : 'status-pill-muted'));
                                ?>
                                <span class="status-pill <?= $statusClass ?>" style="font-size: 0.72rem; padding: 2px 8px; border-radius: 4px;">
                                    <?= $escape($row['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($model['latest'] === null): ?><p style="margin-top: 10px; color: var(--text-gray); font-size: 0.85rem;">No complete recorded GWA is available for the selected terms.</p><?php endif; ?>
            <p class="trend-table-note" style="font-size: 0.80rem; color: var(--text-gray); margin-top: 12px; margin-bottom: 0; line-height: 1.45;">
                Completed-term GWA uses finalized results. Current progress and estimated GWA are shown separately. The current assessment point is converted from recorded assessment percentages. It is not a completed-term GWA.
            </p>
        </div>
    </details>

    <!-- Compact Help Disclosure -->
    <details class="trend-disclosure portal-info-disclosure">
        <summary class="trend-disclosure-summary" aria-controls="trend-help-content">
            <svg class="trend-disclosure-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span class="trend-disclosure-title">About these results and reference guides</span>
        </summary>
        <div id="trend-help-content" class="trend-disclosure-content">
            <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem; color: var(--text-gray); line-height: 1.6;">
                <li>Honors guides are reference thresholds; official eligibility requires institutional review.</li>
                <li>Estimates provide provisional decision support and do not replace official registrar evaluations.</li>
                <li>Growth reflects point differences and relative change between consecutive comparable completed terms.</li>
                <li>Preliminary and Midterm progress represent raw assessment stages on a 0–100% scale.</li>
                <li>The current assessment point is converted from recorded assessment percentages. It is not a completed-term GWA.</li>
            </ul>
        </div>
    </details>
</main>
<script id="student-trend-data" type="application/json"><?= json_encode($model['points'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script id="student-trend-current-progress" type="application/json"><?= json_encode($model['current_progress'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script id="student-trend-active-term" type="application/json"><?= json_encode($model['active_term'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script id="student-trend-guides" type="application/json"><?= json_encode($distinctionGuides, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>assets/js/student-performance-trend.js?v=<?= substr(hash_file('sha256', __DIR__.'/../assets/js/student-performance-trend.js'),0,12) ?>"></script>
<?php require_once '../includes/footer.php'; ?>
