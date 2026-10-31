<?php
declare(strict_types=1);

function formatAnalyticsDurationHours(?float $hours): string {
    if ($hours === null || $hours < 0) {
        return 'Unavailable';
    }
    if ($hours < 24) {
        return (round($hours, 1) == (int)$hours ? (int)$hours : number_format($hours, 1)) . ' hours';
    }
    $days = (int) floor($hours / 24);
    $remHours = (int) round(fmod($hours, 24));
    if ($remHours >= 24) {
        $days++;
        $remHours = 0;
    }
    if ($remHours === 0) {
        return $days . ' ' . ($days === 1 ? 'day' : 'days');
    }
    return $days . ' ' . ($days === 1 ? 'day' : 'days') . ' ' . $remHours . ' ' . ($remHours === 1 ? 'hour' : 'hours');
}

function formatAnalyticsSampleSize(?int $n): string {
    if ($n === null || $n <= 0) {
        return 'No referrals have a recorded first notice for this scope.';
    }
    if ($n === 1) {
        return 'Based on 1 referral with a recorded notice';
    }
    return "Based on {$n} referrals with recorded notices";
}

function analyticsSampleWarning(?int $n): ?string {
    if ($n !== null && $n > 0 && $n < 3) {
        return 'Very limited sample. Interpret cautiously.';
    }
    return null;
}

function getLegacyProgramOverviewData(PDO $db, string $yearFilter = '', string $sectionFilter = ''): array {
    $sqlStudents = "
        SELECT sp.user_id, sp.section, sp.current_gwa, sp.status, sp.year_level,
               p.predicted_gwa, p.risk_level, p.latin_honor, p.prediction_source
        FROM student_profiles sp
        LEFT JOIN predictions p ON p.id = (
            SELECT id FROM predictions p2
            WHERE p2.student_id = sp.user_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
        WHERE sp.section IS NOT NULL AND sp.record_status = 'Active'
    ";
    $params = [];
    if ($yearFilter !== '') {
        $sqlStudents .= " AND sp.year_level = ?";
        $params[] = (int) $yearFilter;
    }
    if ($sectionFilter !== '') {
        $sqlStudents .= " AND sp.section = ?";
        $params[] = $sectionFilter;
    }

    $stmt = $db->prepare($sqlStudents);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStudents = count($students);
    $sectionData = [];
    $gwaSum = 0;
    $gwaCount = 0;
    $riskTotals = ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0];
    $distinctions = [
        'Summa-level threshold' => 0,
        'Magna-level threshold' => 0,
        'Cum Laude-level threshold' => 0,
        'Not currently within a distinction threshold' => 0,
        'Insufficient Data' => 0
    ];

    foreach ($students as $s) {
        $sec = $s['section'];
        if (!isset($sectionData[$sec])) {
            $sectionData[$sec] = [
                'total' => 0,
                'gwa_sum' => 0,
                'gwa_count' => 0,
                'irregular' => 0,
                'risks' => ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0],
                'coverage_count' => 0
            ];
        }

        $sectionData[$sec]['total']++;
        if ($s['status'] === 'Irregular') $sectionData[$sec]['irregular']++;

        if ($s['current_gwa'] !== null && (float)$s['current_gwa'] > 0) {
            $gwaSum += (float)$s['current_gwa'];
            $gwaCount++;
            $sectionData[$sec]['gwa_sum'] += (float)$s['current_gwa'];
            $sectionData[$sec]['gwa_count']++;
        }

        $risk = $s['risk_level'] !== null ? strtoupper($s['risk_level']) : 'NONE';
        $riskTotals[$risk]++;
        $sectionData[$sec]['risks'][$risk]++;
        if ($risk !== 'NONE') $sectionData[$sec]['coverage_count']++;

        if ($s['risk_level'] === null || $s['predicted_gwa'] === null) {
            $distinctions['Insufficient Data']++;
        } else {
            $honorRaw = $s['latin_honor'] ?? 'Not Eligible';
            if ($honorRaw === 'Summa Cum Laude') $distinctions['Summa-level threshold']++;
            elseif ($honorRaw === 'Magna Cum Laude') $distinctions['Magna-level threshold']++;
            elseif ($honorRaw === 'Cum Laude') $distinctions['Cum Laude-level threshold']++;
            else $distinctions['Not currently within a distinction threshold']++;
        }
    }
    ksort($sectionData);

    $meanGwa = $gwaCount > 0 ? $gwaSum / $gwaCount : null;
    $coverageCount = $totalStudents - $riskTotals['NONE'];
    $coveragePct = $totalStudents > 0 ? ($coverageCount / $totalStudents) * 100 : 0.0;
    $atRiskCount = $riskTotals['HIGH'] + $riskTotals['MODERATE'];
    $atRiskPct = $coverageCount > 0 ? ($atRiskCount / $coverageCount) * 100 : 0.0;

    $chartSecLabels = array_keys($sectionData);
    $chartSecHigh = [];
    $chartSecMod = [];
    $chartSecLow = [];
    $chartSecNone = [];
    foreach ($chartSecLabels as $sec) {
        $t = $sectionData[$sec]['total'];
        $chartSecHigh[] = $t > 0 ? round(($sectionData[$sec]['risks']['HIGH'] / $t) * 100, 1) : 0;
        $chartSecMod[]  = $t > 0 ? round(($sectionData[$sec]['risks']['MODERATE'] / $t) * 100, 1) : 0;
        $chartSecLow[]  = $t > 0 ? round(($sectionData[$sec]['risks']['LOW'] / $t) * 100, 1) : 0;
        $chartSecNone[] = $t > 0 ? round(($sectionData[$sec]['risks']['NONE'] / $t) * 100, 1) : 0;
    }

    return [
        'totalStudents' => $totalStudents,
        'meanGwa' => $meanGwa,
        'atRiskPct' => $atRiskPct,
        'coveragePct' => $coveragePct,
        'sectionData' => $sectionData,
        'chartSecLabels' => $chartSecLabels,
        'chartSecHigh' => $chartSecHigh,
        'chartSecMod' => $chartSecMod,
        'chartSecLow' => $chartSecLow,
        'chartSecNone' => $chartSecNone,
        'distinctions' => $distinctions
    ];
}


/** Presentation of existing advisory counts only; never recalculates a grade or band. */
function formatAdminRiskInterpretation(int $high, int $moderate, int $low, int $missing): string {
    $total = $high + $moderate + $low + $missing;
    if ($total === 0) return 'No Students are displayed for this scope.';
    $prefix = $low > $total / 2 ? 'Most displayed Students are currently classified as Low Risk; ' : '';
    return $prefix . ($high + $moderate) . ' of ' . $total . ' displayed Students are in Moderate or High advisory-risk bands; ' . $missing . ' have no prediction. Advisory bands do not determine official standing.';
}

function renderAnalyticsTrendSection(): void {
    ?>
                    <section aria-labelledby="analytics-trend-heading">
                        <h2 id="analytics-trend-heading">Historical semester trend</h2>
                        <p id="trend-description"></p>
                        <div id="trend-chart" class="analytics-chart"></div>
                        <div id="trend-details-card" class="card trend-details-card" style="margin-top: 14px; margin-bottom: 16px; padding: 14px 18px; border: 1px solid var(--border-color); border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0; font-size: 0.85rem; font-weight: 700;  color: var(--text-dark);">Historical trend details</h4>
                            <div id="trend-details-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(160px, 100%), 1fr)); gap: 12px; font-size: 0.82rem;"></div>
                        </div>
                        <div class="data-table-scroll" role="region" aria-label="Historical semester data" tabindex="0">
                            <table id="trend-table"></table>
                        </div>
                    </section>

    <?php
}

function analyticsPage(string $role): void {
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../auth.php';
    requireRole($role);
    $user = currentUser();
    $db = getDB();

    $overview = ($role === 'admin')
        ? getLegacyProgramOverviewData($db, (string)($_GET['year_level'] ?? ''), (string)($_GET['section'] ?? ''))
        : null;

    $pageTitle = match ($role) {
        'student' => 'Academic review',
        'admin' => 'BSIT Program Analytics',
        'faculty' => 'Assigned-class review',
        default => throw new DomainException('Unsupported role')
    };

    $navItems = match ($role) {
        'student' => [
            ['Home', 'index.php', ''],
            ['Dashboard', 'dashboard.php', ''],
            ['Grades & History', 'grades.php', ''],
            ['Performance Trend', 'trend.php', ''],
            ['Academic review', 'academic_analytics.php', ''],
            ['Feedback & Support', 'feedback.php', ''],
            ['Settings', 'settings.php', ''],
        ],
        'faculty' => [
            ['Home', 'index.php', ''],
            ['Dashboard', 'dashboard.php', ''],
            ['Class Analytics', 'analytics.php', ''],
            ['Assigned-class review', 'assigned_analytics.php', ''],
            ['Performance Trends', 'trend.php', ''],
            ['Term Grade Submission', 'grades.php', ''],
            ['Concerns & Reports', 'feedback.php', ''],
            ['Settings', 'settings.php', ''],
        ],
        'admin' => [
            ['Dashboard', 'index.php', ''],
            ['Students', 'students.php', ''],
            ['Faculty', 'faculty.php', ''],
            ['Grades', 'grades.php', ''],
            ['Program Analytics', 'analytics.php', ''],
            ['Activity & Inbox', 'activity.php', ''],
            ['Settings', 'settings.php', ''],
        ],
    };

    require __DIR__ . '/../header.php';
    require __DIR__ . '/../sidebar.php';

    $escape = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $labels = [
        'school_year' => 'School year',
        'semester' => 'Semester',
        'year_level' => 'Subject curriculum year',
        'subject' => 'Subject',
        'section' => 'Section',
        'faculty' => 'Current Faculty assignment',
        'case_status' => 'Case status',
        'decision' => 'Referral decision',
    ];

    $primaryKeys = match ($role) {
        'student' => ['school_year', 'semester'],
        default => ['school_year', 'semester', 'subject', 'section']
    };

    $advancedKeys = match ($role) {
        'admin' => ['year_level', 'faculty'],
        default => []
    };

    $supportKeys = match ($role) {
        'student' => [],
        default => ['case_status', 'decision']
    };

    $tabs = match ($role) {
        'admin' => [
            ['id' => 'overview', 'label' => 'Overview'],
            ['id' => 'comparisons', 'label' => 'Academic Comparisons'],
            ['id' => 'support', 'label' => 'Support & Interventions'],
        ],
        'faculty' => [
            ['id' => 'overview', 'label' => 'Overview'],
            ['id' => 'trends', 'label' => 'Academic Trends'],
            ['id' => 'support', 'label' => 'Referrals & Interventions'],
        ],
        default => []
    };
    ?>
    <link rel="stylesheet" href="<?= $escape(BASE_URL) ?>assets/css/academic-analytics.css?v=<?= filemtime(__DIR__ . '/../../assets/css/academic-analytics.css') ?>">
    <?php if ($role === 'admin'): ?>
    <link rel="stylesheet" href="<?= $escape(BASE_URL) ?>assets/css/admin-academic-comparisons.css?v=<?= filemtime(__DIR__.'/../../assets/css/admin-academic-comparisons.css') ?>">
    <?php endif; ?>
    <main class="main-content analytics-page" id="academic-analytics" data-role="<?= $escape($role) ?>" data-endpoint="<?= $escape(BASE_URL) ?>api/academic_analytics.php">
        <header class="header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
            <div>
                <h1 style="text-transform: uppercase; letter-spacing: 0.5px;"><?= $escape($pageTitle) ?></h1>
                <p style="color: var(--text-gray);">
                    <?= $role === 'admin'
                        ? 'Program-level academic patterns, comparative review, and support follow-up.'
                        : ($role === 'faculty'
                            ? 'Understand recorded academic patterns, data coverage, and support referrals for your assigned classes.'
                            : 'Understand recorded academic patterns, data coverage, and the next review step.') ?>
                </p>
            </div>
            <?php if ($role === 'admin'): ?>
            <div class="analytics-header-actions" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                <a href="coverage.php" class="btn btn--secondary" aria-label="Review Data Coverage: recorded-grade availability and prediction sources" style="min-height: 44px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg>
                    Review Data Coverage
                </a>
            </div>
            <?php endif; ?>
        </header>

        <?php if ($role !== 'student'): ?>
        <p class="analytics-disclosure">Prototype performance on synthetic academic records</p>
        <?php endif; ?>

        <?php if (!empty($tabs)): ?>
        <!-- Accessible Tab Navigation Bar -->
        <div class="analytics-tabs-wrapper">
            <div role="tablist" aria-label="Analytics Views" class="analytics-tablist">
                <?php foreach ($tabs as $index => $tab): ?>
                    <button type="button"
                            role="tab"
                            id="tab-btn-<?= $escape($tab['id']) ?>"
                            class="tab-btn<?= $index === 0 ? ' active' : '' ?>"
                            aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                            aria-controls="tab-panel-<?= $escape($tab['id']) ?>"
                            tabindex="<?= $index === 0 ? '0' : '-1' ?>"
                            data-tab="<?= $escape($tab['id']) ?>">
                        <?= $escape($tab['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Redesigned Filter Form with Progressive Disclosure -->
        <form id="analytics-filters" class="card" aria-label="Academic review filters" style="margin-bottom: 24px;">
            <fieldset disabled>
                <legend class="sr-only">Review scope filters</legend>

                <!-- Primary Always-Visible Filters (Max 4) -->
                <div class="analytics-filter-grid analytics-primary-filters">
                    <?php foreach ($primaryKeys as $key): ?>
                        <div>
                            <label for="analytics-<?= $escape($key) ?>"><?= $escape($labels[$key]) ?></label>
                            <select id="analytics-<?= $escape($key) ?>" name="<?= $escape($key) ?>" class="filter-select">
                                <option value="">All recorded values</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($advancedKeys)): ?>
                <!-- Advanced Academic Filters Disclosure -->
                <details id="analytics-advanced-details" class="analytics-advanced-disclosure">
                    <summary id="analytics-advanced-summary">
                        <span class="accordion-arrow" aria-hidden="true">&#9656;</span>
                        <span>Advanced filters</span>
                        <span id="analytics-advanced-badge" class="analytics-advanced-badge" style="display: none;"></span>
                    </summary>
                    <div class="analytics-filter-grid" style="margin-top: 14px;">
                        <?php foreach ($advancedKeys as $key): ?>
                            <div>
                                <label for="analytics-<?= $escape($key) ?>"><?= $escape($labels[$key]) ?></label>
                                <select id="analytics-<?= $escape($key) ?>" name="<?= $escape($key) ?>" class="filter-select">
                                    <option value="">All recorded values</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endif; ?>

                <?php if (!empty($supportKeys)): ?>
                <!-- Support-Only Filters (Visible only in Support & Interventions tab) -->
                <div id="support-only-filters" class="support-filters-container" style="display: none;">
                    <div class="analytics-filter-grid">
                        <?php foreach ($supportKeys as $key): ?>
                            <div>
                                <label for="analytics-<?= $escape($key) ?>"><?= $escape($labels[$key]) ?></label>
                                <select id="analytics-<?= $escape($key) ?>" name="<?= $escape($key) ?>" class="filter-select">
                                    <option value="">All recorded values</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </fieldset>

            <div class="analytics-actions">
                <button type="submit" class="btn btn--primary">Apply filters</button>
                <button type="button" id="analytics-clear" class="btn btn--secondary">Clear filters</button>
            </div>

            <?php if ($role !== 'student'): ?>
            <p class="analytics-note">
                Case status and referral decision apply to the support summary. Academic section and Faculty comparisons use verified current assignments; undated history is not attributed to a teacher.
            </p>
            <?php endif; ?>
        </form>

        <p id="analytics-state" role="status" aria-live="polite" style="font-size: 0.82rem; color: var(--text-gray); margin: 6px 0 16px 0;">Showing all authorized recorded values.</p>

        <div id="analytics-results" hidden aria-busy="true">
            <p id="active-filters" class="analytics-context"></p>

            <?php if ($role === 'student'): ?>
                <!-- Student View: Concise Decision Summary -->
                <section aria-labelledby="analytics-summary-heading">
                    <h2 id="analytics-summary-heading" style="font-size: 1.15rem; font-weight: 700; margin-bottom: 12px; color: var(--text-dark);">Academic Results & Growth Summary</h2>
                    <div id="analytics-summary"></div>
                </section>
                <section id="analytics-prediction" aria-label="Advisory classification and next step" style="margin: 20px 0;"></section>
                <section aria-labelledby="analytics-method-heading" style="margin-top: 20px;">
                    <div id="analytics-method"></div>
                </section>

            <?php else: ?>
                <!-- Multi-Tab Workspaces (Admin & Faculty) -->

                <!-- TAB PANEL 1: OVERVIEW -->
                <div role="tabpanel"
                     id="tab-panel-overview"
                     aria-labelledby="tab-btn-overview"
                     class="analytics-tab-panel" tabindex="0">

                    <?php if ($role === 'admin' && $overview !== null): ?>
                    <p class="analytics-note" id="student-overview-scope">Current active-student snapshot<?= !empty($_GET['section']) ? ' · Section ' . $escape($_GET['section']) : '' ?><?= !empty($_GET['year_level']) ? ' · Curriculum year ' . $escape($_GET['year_level']) : '' ?>. Academic filters refresh the comparison and support results; this student overview reflects the scope when the page opened.</p>
                    <!-- RESTORED: Program Overview Primary 4-Card Analytical KPI Grid -->
                    <div class="analytics-stat-grid">
                        <div class="stat-card" style="border-left-color: var(--accent-blue);">
                            <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Students Analyzed</h4>
                            <h2 style="color: var(--text-dark); font-size: 1.8rem; margin: 0;" data-metric="total-students" data-overview-metric="total-students"><?= $overview['totalStudents'] ?></h2>
                            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Across <?= count($overview['sectionData']) ?> active sections</p>
                        </div>
                        <div class="stat-card" style="border-left-color: var(--risk-low);">
                            <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Mean Student GWA</h4>
                            <h2 style="color: var(--risk-low); font-size: 1.8rem; margin: 0;" data-metric="mean-student-gwa" data-overview-metric="mean-student-gwa"><?= $overview['meanGwa'] !== null ? number_format($overview['meanGwa'], 2) : 'N/A' ?></h2>
                            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Enrolled student cohorts</p>
                        </div>
                        <div class="stat-card" style="border-left-color: var(--risk-mod);">
                            <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">At-Risk Proportion</h4>
                            <h2 style="color: var(--risk-mod); font-size: 1.8rem; margin: 0;" data-metric="at-risk-pct" data-overview-metric="at-risk-pct"><?= $overview['coveragePct'] > 0 ? number_format($overview['atRiskPct'], 1) . '%' : 'N/A' ?></h2>
                            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">High &amp; Moderate risk triage</p>
                        </div>
                        <div class="stat-card" style="border-left-color: var(--gold);">
                            <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-gray); margin: 0 0 4px 0;">Prediction Coverage</h4>
                            <h2 style="color: var(--text-dark); font-size: 1.8rem; margin: 0;" data-metric="prediction-coverage-pct" data-overview-metric="prediction-coverage-pct"><?= number_format($overview['coveragePct'], 1) ?>%</h2>
                            <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 4px; font-weight: 600;">Active student predictions</p>
                        </div>
                    </div>

                    <!-- Secondary Comparative Progress & Coverage Row -->
                    <div class="card" style="margin-bottom: 24px; padding: 18px 20px;">
                        <h4 style="margin: 0 0 12px 0; font-size: 0.85rem; font-weight: 700; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px;">Comparative Progress &amp; Coverage Context</h4>
                        <div id="analytics-secondary-summary" class="analytics-secondary-metrics" style="margin-top: 0; padding-top: 0; border-top: none;"></div>
                    </div>

                    <p id="overview-mode-status" class="sr-only" role="status" aria-live="polite">Showing both charts.</p>
                    <!-- RESTORED: Visual Analytics Charts Grid -->
                    <div data-overview-charts class="analytics-chart-grid admin-overview-charts">
                        <div class="card" data-chart-panel="risk" style="position: relative;">
                            <h3 id="risk-chart-heading">Risk Distribution by Section</h3>
                            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Percentage-based risk comparison enabling section-to-section analysis.</p>
                            <?php if (empty($overview['sectionData'])): ?>
                                <p class="empty-state" style="margin-top: 60px;">No data matches your current filter.</p>
                            <?php else: ?>
                                <div class="overview-chart-plot overview-chart-plot--risk">
                                    <canvas id="sectionRiskChart" role="img" aria-label="Risk distribution by section" aria-describedby="section-risk-chart-summary"></canvas>
                                </div>
                                <ul class="overview-chart-legend" aria-label="Risk bands">
                                    <li><span style="background:var(--risk-high)"></span>High Risk</li><li><span style="background:var(--risk-mod)"></span>Moderate Risk</li><li><span style="background:var(--risk-low)"></span>Low Risk</li><li><span style="background:var(--border-color)"></span>No Prediction</li>
                                </ul>
                                <p class="analytics-chart-fallback" data-chart-fallback="sectionRiskChart">Chart unavailable. Use Exact values to read the available records.</p>
                                <p class="chart-interpretation-text" style="font-size: 0.82rem; color: var(--text-gray); margin: 12px 0 8px 0; line-height: 1.4;">
                                    <?= count($overview['chartSecLabels']) ?> displayed sections; percentages use all active Students within each section. No Prediction remains separate from advisory-risk bands.
                                </p>
                                <div class="chart-panel-actions"><button type="button" class="btn btn--secondary" id="risk-exact-values" data-focused-chart="risk" aria-describedby="risk-chart-heading" aria-expanded="false" aria-pressed="false" aria-controls="section-risk-data">Exact values</button></div>
                                <section id="section-risk-data" class="card focused-chart-details" role="region" aria-labelledby="section-risk-heading" hidden>
                                    <h3 id="section-risk-heading" tabindex="-1">Focused view: Risk Distribution</h3>
                                    <div class="focused-chart-details__body" tabindex="0" role="region" aria-label="Scrollable Risk Distribution details">
                                        <p>Population: active Students in each displayed section. Denominator: all active Students in that section, including those without predictions.</p>
                                        <p>High: below 1.75; Moderate: 1.75–2.49; Low: 2.50 or above. These are advisory bands, separate from subject outcomes and official standing.</p>
                                        <p><?= count($overview['chartSecLabels']) ?> sections are displayed. Differences do not establish Faculty quality or causes.</p>
                                    <div class="data-table-scroll section-risk-table-scroll" role="region" aria-label="Risk distribution chart data" tabindex="0">
                                        <table id="section-risk-chart-summary">
                                            <caption>Risk distribution by section; percentages of all active students in each section. No Prediction means no stored prediction.</caption>
                                            <thead><tr><th scope="col">Section</th><th scope="col">High Risk (%)</th><th scope="col">Moderate Risk (%)</th><th scope="col">Low Risk (%)</th><th scope="col">No Prediction (%)</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($overview['chartSecLabels'] as $i => $section): ?>
                                                <tr><th scope="row"><?= $escape($section) ?></th><td><?= number_format($overview['chartSecHigh'][$i], 1) ?></td><td><?= number_format($overview['chartSecMod'][$i], 1) ?></td><td><?= number_format($overview['chartSecLow'][$i], 1) ?></td><td><?= number_format($overview['chartSecNone'][$i], 1) ?></td></tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </div>

                        <div class="card" data-chart-panel="distinction" style="position: relative;">
                            <h3 id="distinction-chart-heading">Academic Distinction Readiness</h3>
                            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Based on each student's latest available estimate or prediction. Does not represent official eligibility.</p>
                            <?php if (empty($overview['sectionData'])): ?>
                                <p class="empty-state" style="margin-top: 60px;">No data matches your current filter.</p>
                            <?php else: ?>
                                <div class="overview-chart-plot overview-chart-plot--distinction">
                                    <canvas id="honorChart" role="img" aria-label="Academic Distinction Readiness Chart" aria-describedby="honor-chart-summary"></canvas>
                                </div>
                                <p class="analytics-chart-fallback" data-chart-fallback="honorChart">Chart unavailable. Use Exact values to read the available records.</p>

                                <ul id="honorChartLegend" class="honor-chart-legend" role="list" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 8px 16px; margin-top: 14px; padding: 0; list-style: none;">
                                    <?php foreach ($overview['distinctions'] as $label => $count):
                                        $dotColor = match(true) {
                                            str_contains($label, 'Summa') => 'var(--gold, #E8B923)',
                                            str_contains($label, 'Magna') => 'var(--honor-magna, #265597)',
                                            str_contains($label, 'Cum Laude') => 'var(--primary-light, #2D9CDB)',
                                            str_contains($label, 'Insufficient') => 'var(--text-gray, #64748B)',
                                            default => 'var(--border-color, #94A3B8)',
                                        };
                                    ?>
                                    <li style="display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-dark);">
                                        <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?= $dotColor ?>;"></span>
                                        <span><?= $escape($label) ?>: <strong><?= (int)$count ?></strong></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="chart-interpretation-text" style="font-size: 0.82rem; color: var(--text-gray); margin: 12px 0 8px 0; line-height: 1.4;">
                                    <?= (int)array_sum($overview['distinctions']) ?> displayed Students across <?= count($overview['distinctions']) ?> recorded categories, based on latest stored estimates or predictions. This does not confer official eligibility.
                                </p>
                                <div class="chart-panel-actions"><button type="button" class="btn btn--secondary" id="distinction-exact-values" data-focused-chart="distinction" aria-describedby="distinction-chart-heading" aria-expanded="false" aria-pressed="false" aria-controls="honor-chart-data">Exact values</button></div>
                                <section id="honor-chart-data" class="card focused-chart-details" role="region" aria-labelledby="distinction-details-heading" hidden>
                                    <h3 id="distinction-details-heading" tabindex="-1">Focused view: Academic Distinction Readiness</h3>
                                    <div class="focused-chart-details__body" tabindex="0" role="region" aria-label="Scrollable Academic Distinction details">
                                        <p>Population: <?= (int)$overview['totalStudents'] ?> active Students in this opening scope. Categories use each Student's latest stored estimate or prediction.</p>
                                        <p><?= (int)array_sum($overview['distinctions']) ?> Students are represented across the displayed categories.</p>
                                        <p><strong>This result does not confer official eligibility.</strong> Missing estimates remain Insufficient Data. Recorded snapshots do not prove that later grades were incorporated.</p>
                                    <div class="data-table-scroll distinction-table-scroll" role="region" aria-label="Academic distinction chart data" tabindex="0">
                                    <table id="honor-chart-summary">
                                        <caption>Academic Distinction Readiness Summary</caption>
                                        <thead><tr><th scope="col">Category</th><th scope="col">Count</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($overview['distinctions'] as $label => $count): ?>
                                                <tr><th scope="row"><?= $escape($label) ?></th><td><?= (int)$count ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- RESTORED: Section Comparison Table -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="table-title" style="margin-bottom: 8px; font-size: 1.1rem; font-weight: 700; color: var(--text-dark);">Section Comparison Table</div>
                        <p style="font-size: 0.82rem; color: var(--text-gray); margin-bottom: 14px;">Cross-sectional comparison enabling load and academic distribution analysis.</p>
                        <?php if (empty($overview['sectionData'])): ?>
                            <p class="empty-state">No section data matches your current filter.</p>
                        <?php else: ?>
                        <div class="data-table-scroll table-matrix-wrapper" role="region" aria-label="Section Comparison Table" tabindex="0">
                            <table class="table-matrix data-table" style="width: 100%; border-collapse: collapse; min-width: 800px;">
                                <thead>
                                    <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                                        <th style="padding: 12px; text-align: left; color: var(--text-dark); white-space: nowrap;">Section</th>
                                        <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Students</th>
                                        <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Mean GWA</th>
                                        <th style="padding: 12px; text-align: center; color: var(--risk-low); white-space: nowrap;">Low</th>
                                        <th style="padding: 12px; text-align: center; color: var(--risk-mod); white-space: nowrap;">Moderate</th>
                                        <th style="padding: 12px; text-align: center; color: var(--risk-high); white-space: nowrap;">High</th>
                                        <th style="padding: 12px; text-align: center; color: var(--text-dark); white-space: nowrap;">Coverage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overview['sectionData'] as $sec => $d):
                                        $secMean = $d['gwa_count'] > 0 ? number_format($d['gwa_sum'] / $d['gwa_count'], 2) : 'N/A';
                                        $secCov = $d['total'] > 0 ? number_format(($d['coverage_count'] / $d['total']) * 100, 1) . '%' : '0%';
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px; font-weight: 700; color: var(--accent-blue); white-space: nowrap;"><?= $escape($sec) ?></td>
                                        <td style="padding: 12px; text-align: center; font-weight: 600;"><?= $d['total'] ?></td>
                                        <td style="padding: 12px; text-align: center; font-weight: 600;"><?= $secMean ?></td>
                                        <td style="padding: 12px; text-align: center; color: var(--risk-low); font-weight: 600;"><?= $d['risks']['LOW'] ?></td>
                                        <td style="padding: 12px; text-align: center; color: var(--risk-mod); font-weight: 600;"><?= $d['risks']['MODERATE'] ?></td>
                                        <td style="padding: 12px; text-align: center; color: var(--risk-high); font-weight: 600;"><?= $d['risks']['HIGH'] ?></td>
                                        <td style="padding: 12px; text-align: center;"><?= $secCov ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <!-- Faculty Overview Workspace -->
                    <section aria-labelledby="analytics-summary-heading">
                        <h2 id="analytics-summary-heading">Recorded academic results</h2>
                        <div id="analytics-summary"></div>
                        <div id="analytics-secondary-summary" class="analytics-secondary-metrics"></div>
                    </section>
                    <section id="analytics-prediction" aria-label="Estimated GWA and advisory meaning"></section>
                    <?php endif; ?>

                    <?php if ($role === 'admin'): ?>
                    <section aria-labelledby="analytics-summary-heading">
                        <h2 id="analytics-summary-heading">Recorded academic results</h2>
                        <div id="analytics-summary"></div>
                    </section>
                    <section id="analytics-prediction" aria-label="Estimated GWA and advisory meaning"></section>
                    <?php endif; ?>
                    <?php if ($role === 'admin') renderAnalyticsTrendSection(); ?>

                    <section aria-labelledby="analytics-method-heading">
                        <h2 id="analytics-method-heading">Reading these results</h2>
                        <div id="analytics-method"></div>
                    </section>
                </div>

                <!-- TAB PANEL 2: ACADEMIC COMPARISONS / TRENDS -->
                <div role="tabpanel"
                     id="tab-panel-<?= $role === 'admin' ? 'comparisons' : 'trends' ?>"
                     aria-labelledby="tab-btn-<?= $role === 'admin' ? 'comparisons' : 'trends' ?>"
                     class="analytics-tab-panel" tabindex="0"
                     hidden>



                    <?php if ($role !== 'admin') renderAnalyticsTrendSection(); ?>

                    <?php if ($role === 'admin'): ?>
                    <section id="admin-comparisons" aria-labelledby="admin-comparison-heading">
                        <div class="card ac-workspace">
                            <h2 id="admin-comparison-heading">Academic Comparisons</h2>
                            <p class="ac-intro">Compare academic groups using the same scope, period, and metric.</p>
                            <div id="ac-controls"></div>
                        </div>
                        <div id="ac-notice" role="status" class="ac-notice" hidden></div>
                        <div id="ac-result"></div>
                        <div class="ac-history"><p id="ac-history-summary"></p><button type="button" class="btn btn--quiet" id="ac-view-history">View historical trend</button></div>
                    </section>
                    <?php else: ?>
                    <section aria-labelledby="analytics-comparison-heading" class="comparison-builder-section">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <h2 id="analytics-comparison-heading" style="margin: 0 0 4px 0;">Academic comparisons</h2>
                                <p style="color: var(--text-gray); font-size: 0.9rem; margin: 0;">
                                    Side-by-side comparison cards evaluating cohorts, sections, subjects, or verified faculty assignments.
                                </p>
                            </div>
                            <div class="comparison-toolbar-controls" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div class="comparison-mode-segmented-control" role="group" aria-label="Comparison display mode">
                                        <button type="button" id="btn-mode-overlay" class="segmented-btn active" aria-pressed="true">
                                            <span class="mode-check" aria-hidden="true">✓ </span>Overlay chart
                                        </button>
                                        <button type="button" id="btn-mode-cards" class="segmented-btn" aria-pressed="false">
                                            <span class="mode-check" aria-hidden="true" style="display: none;">✓ </span>Separate cards
                                        </button>
                                    </div>
                                    <p id="comparison-mode-helper" class="comparison-mode-helper">
                                        Show selected groups together on one shared scale.
                                    </p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <label for="comparison-metric" style="font-weight: 600; font-size: 0.85rem; color: var(--text-dark); margin: 0;">Metric:</label>
                                    <select id="comparison-metric" class="filter-select" aria-label="Comparison metric" style="min-width: 190px;">
                                        <option value="gwa">Recorded-grade average</option>
                                        <option value="student_count">Student count</option>
                                        <option value="numeric_coverage_pct">Numeric grade coverage</option>
                                        <option value="final_coverage_pct">Final-outcome coverage</option>
                                        <option value="prediction_coverage_pct">Prediction coverage</option>
                                        <option value="risk_distribution">Advisory-risk distribution</option>
                                    </select>
                                </div>
                                <div class="add-comparison-container" style="display: flex; align-items: center; gap: 8px;">
                                    <button type="button" id="add-comparison-card-btn" class="btn btn--secondary compact-add-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                        + Add comparison
                                    </button>
                                    <span id="add-comparison-limit-notice" role="status" style="display: none; font-size: 0.78rem; color: var(--text-gray); font-style: italic;">
                                        Maximum of 3 comparisons
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- OTHER COMPARISON OPTIONS DISCLOSURE (Advanced options & backward compatibility) -->
                        <details id="other-comparisons-disclosure" class="card" style="padding: 12px 18px; margin-bottom: 20px; border: 1px solid var(--border-color); border-radius: 8px;">
                            <summary style="font-weight: 600; font-size: 0.85rem; color: var(--accent-blue); cursor: pointer; outline: none; user-select: none;">
                                Other comparison options &amp; global view
                            </summary>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); gap: 14px; margin-top: 14px; align-items: flex-end;">
                                <div>
                                    <label for="comparison-group" style="display: block; font-weight: 600; font-size: 0.8rem; margin-bottom: 4px; color: var(--text-dark);">
                                        Quick comparison preset
                                    </label>
                                    <select id="comparison-group" class="filter-select" aria-label="Comparison view" style="width: 100%;">
                                        <option value="faculty_subject">Faculty assignment</option>
                                        <option value="subject">Subject</option>
                                        <option value="section">Section</option>
                                        <option value="year_level">Curriculum year</option>
                                        <option value="semester">Semester</option>
                                        <option value="school_year">School year</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="compare-a" style="display: block; font-weight: 600; font-size: 0.8rem; margin-bottom: 4px; color: var(--text-dark);">
                                        Compare group A
                                    </label>
                                    <select id="compare-a" class="filter-select" aria-label="Compare group A" style="width: 100%;"></select>
                                </div>
                                <div>
                                    <label for="compare-b" style="display: block; font-weight: 600; font-size: 0.8rem; margin-bottom: 4px; color: var(--text-dark);">
                                        Compare group B
                                    </label>
                                    <select id="compare-b" class="filter-select" aria-label="Compare group B" style="width: 100%;"></select>
                                </div>
                            </div>
                            <p id="comparison-description" style="font-size: 0.8rem; color: var(--text-gray); margin: 10px 0 0 0;"></p>
                        </details>

                        <!-- VALIDATION / NOTICE BANNER -->
                        <div id="comparison-validation-notice" role="alert" style="display: none; margin-bottom: 20px; padding: 12px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 500;"></div>

                        <div id="comparisons-content-wrapper" class="mode-overlay">
                            <!-- OVERLAID VISUAL CHART CARD (Prominent in overlay mode) -->
                            <div id="comparison-chart-card" class="card visual-comparison-card" style="padding: 20px 24px; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 10px;">
                                <div class="visual-comparison-layout">
                                    <div class="visual-comparison-chart-pane">
                                        <h3 style="margin: 0 0 6px 0; font-size: 1.05rem; font-weight: 700; color: var(--text-dark);">Visual Comparison</h3>
                                        <p style="margin: 0 0 14px 0; font-size: 0.82rem; color: var(--text-gray);">Overlay comparison rendered on identical axes and scale.</p>
                                        <div id="comparison-chart" class="analytics-chart" style="margin-bottom: 8px;"></div>
                                        <div id="comparison-legend" class="comparison-legend-container" style="display: flex; gap: 14px; flex-wrap: wrap; font-size: 0.82rem; color: var(--text-gray); margin-top: 6px; justify-content: center;"></div>
                                    </div>
                                    <aside id="comparison-details-pane" class="visual-comparison-details-pane" aria-labelledby="comparison-details-heading">
                                        <h4 id="comparison-details-heading" class="comparison-details-title">Comparison Details</h4>
                                        <div id="comparison-details-content" class="comparison-details-body"></div>
                                    </aside>
                                </div>
                            </div>

                            <!-- COMPARISON PANELS / CARDS GRID (2 default, 3 max) -->
                            <div id="comparison-panels-grid" class="comparison-cards-grid" style="margin-bottom: 24px;">
                                <div id="panel-compare-a" class="comparison-panel comparison-card panel-a card-a"></div>
                                <div id="panel-compare-b" class="comparison-panel comparison-card panel-b card-b"></div>
                                <div id="panel-compare-c" class="comparison-panel comparison-card panel-c card-c" style="display: none;"></div>
                            </div>

                            <!-- EXACT VALUES TABLE FOR SELECTED GROUPS -->
                            <div class="card comparison-evidence-card" style="padding: 20px 24px; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 10px;">
                                <h3 style="margin: 0 0 6px 0; font-size: 1.05rem; font-weight: 700; color: var(--text-dark);">Comparison Evidence</h3>
                                <p style="margin: 0 0 14px 0; font-size: 0.82rem; color: var(--text-gray);">Exact metrics for the selected comparison cards.</p>
                                <div class="data-table-scroll" role="region" aria-label="Two group comparison evidence" tabindex="0">
                                    <table id="side-by-side-table" class="data-table" style="width: 100%;"></table>
                                </div>
                            </div>
                        </div>

                        <!-- ALL ELIGIBLE GROUPS DISCLOSURE (Contains full #comparison-table) -->
                        <details id="all-groups-disclosure" class="card" style="padding: 16px 20px; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 10px;">
                            <summary style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark); cursor: pointer; outline: none;">
                                View all eligible groups (<span id="all-groups-count">0</span> total)
                            </summary>
                            <div style="margin-top: 16px;">
                                <p style="font-size: 0.82rem; color: var(--text-gray); margin-bottom: 12px;">Full group breakdown for the active comparison type.</p>
                                <div class="data-table-scroll" role="region" aria-label="Comparison data" tabindex="0">
                                    <table id="comparison-table" class="data-table" style="width: 100%;"></table>
                                </div>
                            </div>
                        </details>

                        <p class="analytics-fairness" style="border-left: 3px solid var(--accent-blue); padding: 10px 16px; background: var(--bg-hover, #f8fafc); border-radius: 0 6px 6px 0; font-size: 0.82rem; color: var(--text-gray); line-height: 1.5; margin-top: 16px;">
                            Comparative academic patterns support authorized review and do not independently measure teaching performance. Differences may reflect Student composition, prior achievement, subject difficulty, class size, record completeness, and semester conditions.
                        </p>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- TAB PANEL 3: SUPPORT & INTERVENTIONS -->
                <div role="tabpanel"
                     id="tab-panel-support"
                     aria-labelledby="tab-btn-support"
                     class="analytics-tab-panel" tabindex="0"
                     hidden>
                    <section aria-labelledby="analytics-support-heading">
                        <h2 id="analytics-support-heading" style="margin-bottom: 4px;">Support review summary</h2>
                        <p style="color: var(--text-gray); font-size: 0.9rem; margin-top: 0; margin-bottom: 20px;">
                            Recorded cases and referrals support follow-up. Counts do not prove that an intervention caused a grade change.
                        </p>

                        <!-- VISUAL SUPPORT CHARTS (Part 7) -->
                        <div class="support-charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr)); gap: 20px; margin-bottom: 24px;">
                            <!-- Chart 1: Case & Referral Status -->
                            <div class="card" style="padding: 18px 20px; border: 1px solid var(--border-color); border-radius: 10px; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <h3 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">Case &amp; Referral Status</h3>
                                    <p style="margin: 0 0 12px 0; font-size: 0.78rem; color: var(--text-gray);">Parent cases and referrals represent separate record units.</p>
                                </div>
                                <div id="chart-support-status" class="analytics-chart" style="padding: 0;"></div>
                            </div>

                            <!-- Chart 2: Notice & Acknowledgment Flow -->
                            <div class="card" style="padding: 18px 20px; border: 1px solid var(--border-color); border-radius: 10px; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <h3 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">Notice &amp; Acknowledgment Flow</h3>
                                    <p style="margin: 0 0 12px 0; font-size: 0.78rem; color: var(--text-gray);">Bounded count of recorded receipt acknowledgments.</p>
                                </div>
                                <div id="chart-notice-flow" class="analytics-chart" style="padding: 0;"></div>
                            </div>

                            <!-- Chart 3: First-Notice Timing -->
                            <div class="card" style="padding: 18px 20px; border: 1px solid var(--border-color); border-radius: 10px; display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <h3 style="margin: 0 0 4px 0; font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">First-Notice Timing</h3>
                                    <p style="margin: 0 0 12px 0; font-size: 0.78rem; color: var(--text-gray);">From referral creation to the first recorded notice.</p>
                                </div>
                                <div id="chart-first-notice" style="padding: 0;"></div>
                            </div>
                        </div>

                        <div class="data-table-scroll" role="region" aria-label="Support summary data" tabindex="0">
                            <table id="support-table"></table>
                        </div>
                        <p id="support-limits"></p>
                        <h3 style="margin-top: 24px;">Open authorized records</h3>
                        <p>These links open existing review workspaces; this view creates no notice or decision.</p>
                        <div class="data-table-scroll" role="region" aria-label="Authorized case links" tabindex="0">
                            <table id="drilldown-table"></table>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php if ($role === 'admin' && !empty($overview['sectionData'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Chart === 'undefined') {
            for (const id of ['sectionRiskChart', 'honorChart']) {
                const canvas = document.getElementById(id);
                if (canvas) canvas.parentElement.hidden = true;
            }
            return;
        }
        function getChartTheme() {
            const root = getComputedStyle(document.documentElement);
            return {
                text: root.getPropertyValue('--text-dark').trim() || '#1e293b',
                surface: root.getPropertyValue('--card-bg').trim() || '#ffffff',
                border: root.getPropertyValue('--border-color').trim() || '#cbd5e1',
                high: root.getPropertyValue('--risk-high').trim() || '#ef4444',
                mod: root.getPropertyValue('--risk-mod').trim() || '#f59e0b',
                low: root.getPropertyValue('--risk-low').trim() || '#10b981',
                none: root.getPropertyValue('--border-color').trim() || '#94a3b8',
                summa: root.getPropertyValue('--gold').trim() || '#e8b923',
                magna: root.getPropertyValue('--honor-magna').trim() || '#265597',
                cumLaude: root.getPropertyValue('--primary-light').trim() || '#2d9cdb',
                insufficient: root.getPropertyValue('--text-gray').trim() || '#64748b'
            };
        }
        const theme = getChartTheme();
        const reducedMotion = () => document.documentElement.dataset.motion === 'reduce' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const tooltipTheme = t => ({ backgroundColor: t.surface, titleColor: t.text, bodyColor: t.text, borderColor: t.border, borderWidth: 1 });
        let sectionChart = null, honorChart = null;
        const secEl = document.getElementById('sectionRiskChart');
        if (secEl) {
            sectionChart = new Chart(secEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($overview['chartSecLabels']) ?>,
                    datasets: [
                        { label: 'High Risk', data: <?= json_encode($overview['chartSecHigh']) ?>, backgroundColor: theme.high, stack: 'Stack 0' },
                        { label: 'Moderate Risk', data: <?= json_encode($overview['chartSecMod']) ?>, backgroundColor: theme.mod, stack: 'Stack 0' },
                        { label: 'Low Risk', data: <?= json_encode($overview['chartSecLow']) ?>, backgroundColor: theme.low, stack: 'Stack 0' },
                        { label: 'No Prediction', data: <?= json_encode($overview['chartSecNone']) ?>, backgroundColor: theme.none, stack: 'Stack 0' }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: reducedMotion() ? false : { duration: 150 },
                    plugins: { legend: { display: false, labels: { color: theme.text } }, tooltip: tooltipTheme(theme) },
                    scales: {
                        x: { stacked: true, max: 100, ticks: { callback: v => v + '%', color: theme.text }, grid: { color: theme.border } },
                        y: { stacked: true, ticks: { color: theme.text }, grid: { color: theme.border } }
                    }
                }
            });
        }
        const honorEl = document.getElementById('honorChart');
        if (honorEl) {
            const hLabels = <?= json_encode(array_keys($overview['distinctions'])) ?>;
            const hCounts = <?= json_encode(array_values($overview['distinctions'])) ?>;
            const hColors = hLabels.map(l => {
                if (l.includes('Summa')) return theme.summa;
                if (l.includes('Magna')) return theme.magna;
                if (l.includes('Cum Laude')) return theme.cumLaude;
                if (l.includes('Insufficient')) return theme.insufficient;
                return theme.none;
            });
            honorChart = new Chart(honorEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: hLabels,
                    datasets: [{ data: hCounts, backgroundColor: hColors, borderWidth: 0, hoverOffset: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: reducedMotion() ? false : { duration: 150 },
                    cutout: '65%',
                    plugins: { legend: { display: false }, tooltip: tooltipTheme(theme) }
                }
            });
        }
        for (const canvas of [secEl, honorEl]) {
            const fallback = canvas && document.querySelector('[data-chart-fallback="' + canvas.id + '"]');
            if (fallback) fallback.hidden = true;
        }
        // Reuse the existing preferences event; keep axes, tooltips and datasets in sync.
        window.addEventListener('udm-radar:display-preferences-changed', function() {
            const current = getChartTheme();
            if (sectionChart) {
                sectionChart.data.datasets.forEach((dataset, i) => dataset.backgroundColor = [current.high, current.mod, current.low, current.none][i]);
                for (const axis of ['x', 'y']) {
                    sectionChart.options.scales[axis].ticks.color = current.text;
                    sectionChart.options.scales[axis].grid.color = current.border;
                }
                sectionChart.options.plugins.legend.labels.color = current.text;
            }
            if (honorChart) {
                honorChart.data.datasets[0].backgroundColor = honorChart.data.labels.map(label => label.includes('Summa') ? current.summa : label.includes('Magna') ? current.magna : label.includes('Cum Laude') ? current.cumLaude : label.includes('Insufficient') ? current.insufficient : current.none);
            }
            for (const chart of [sectionChart, honorChart]) {
                if (!chart) continue;
                chart.options.animation = reducedMotion() ? false : { duration: 150 };
                chart.options.plugins.tooltip = tooltipTheme(current);
                chart.update('none');
            }
        });
    });
    </script>
    <?php endif; ?>
    <?php if ($role === 'admin'): ?><script src="<?= $escape(BASE_URL) ?>assets/js/admin-academic-comparisons.js?v=<?= filemtime(__DIR__.'/../../assets/js/admin-academic-comparisons.js') ?>" defer></script><?php endif; ?>
    <script src="<?= $escape(BASE_URL) ?>assets/js/academic-analytics.js?v=<?= filemtime(__DIR__ . '/../../assets/js/academic-analytics.js') ?>" defer></script>
    <?php
    if ($role === 'admin') { ?><script src="<?= $escape(BASE_URL) ?>assets/js/admin-analytics-interpretability.js?v=<?= filemtime(__DIR__.'/../../assets/js/admin-analytics-interpretability.js') ?>" defer></script><?php }
    require __DIR__ . '/../footer.php';
}
