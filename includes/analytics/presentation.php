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
    <link rel="stylesheet" href="<?= $escape(BASE_URL) ?>assets/css/academic-analytics.css">
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

        <p class="analytics-disclosure">Prototype performance on synthetic academic records</p>

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

        <p id="analytics-state" role="status" aria-live="polite">Loading academic review…</p>

        <div id="analytics-results" hidden aria-busy="true">
            <p id="active-filters" class="analytics-context"></p>

            <?php if ($role === 'student'): ?>
                <!-- Student View: Unified Progression -->
                <section aria-labelledby="analytics-summary-heading">
                    <h2 id="analytics-summary-heading">Recorded academic results</h2>
                    <div id="analytics-summary"></div>
                </section>
                <section id="analytics-prediction" aria-label="Estimated GWA and advisory meaning"></section>
                <section aria-labelledby="analytics-trend-heading">
                    <h2 id="analytics-trend-heading">Historical semester trend</h2>
                    <p id="trend-description"></p>
                    <div id="trend-chart" class="analytics-chart"></div>
                    <div class="data-table-scroll" role="region" aria-label="Historical semester data" tabindex="0">
                        <table id="trend-table"></table>
                    </div>
                </section>
                <section aria-labelledby="analytics-method-heading">
                    <h2 id="analytics-method-heading">Reading these results</h2>
                    <div id="analytics-method"></div>
                </section>

            <?php else: ?>
                <!-- Multi-Tab Workspaces (Admin & Faculty) -->

                <!-- TAB PANEL 1: OVERVIEW -->
                <div role="tabpanel"
                     id="tab-panel-overview"
                     aria-labelledby="tab-btn-overview"
                     class="analytics-tab-panel">

                    <?php if ($role === 'admin' && $overview !== null): ?>
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

                    <!-- RESTORED: Visual Analytics Charts Grid -->
                    <div class="analytics-chart-grid">
                        <div class="card" style="position: relative; min-height: 380px;">
                            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 6px; font-size: 1.05rem;">Risk Distribution by Section</div>
                            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Percentage-based risk comparison enabling section-to-section analysis.</p>
                            <?php if (empty($overview['sectionData'])): ?>
                                <p class="empty-state" style="margin-top: 60px;">No data matches your current filter.</p>
                            <?php else: ?>
                                <div style="position: relative; height: 280px; width: 100%;">
                                    <canvas id="sectionRiskChart"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card" style="position: relative; min-height: 380px;">
                            <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 6px; font-size: 1.05rem;">Academic Distinction Readiness</div>
                            <p style="font-size: 0.8rem; color: var(--text-gray); margin-bottom: 16px;">Based on each student's latest available estimate or prediction. Does not represent official eligibility.</p>
                            <?php if (empty($overview['sectionData'])): ?>
                                <p class="empty-state" style="margin-top: 60px;">No data matches your current filter.</p>
                            <?php else: ?>
                                <div style="position: relative; height: 210px; width: 100%;">
                                    <canvas id="honorChart" role="img" aria-label="Academic Distinction Readiness Chart"></canvas>
                                </div>
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
                                <div class="sr-only">
                                    <table id="honor-chart-summary">
                                        <caption>Academic Distinction Readiness Summary</caption>
                                        <thead><tr><th>Category</th><th>Count</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($overview['distinctions'] as $label => $count): ?>
                                                <tr><td><?= $escape($label) ?></td><td><?= (int)$count ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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

                    <section aria-labelledby="analytics-method-heading">
                        <h2 id="analytics-method-heading">Reading these results</h2>
                        <div id="analytics-method"></div>
                    </section>
                </div>

                <!-- TAB PANEL 2: ACADEMIC COMPARISONS / TRENDS -->
                <div role="tabpanel"
                     id="tab-panel-<?= $role === 'admin' ? 'comparisons' : 'trends' ?>"
                     aria-labelledby="tab-btn-<?= $role === 'admin' ? 'comparisons' : 'trends' ?>"
                     class="analytics-tab-panel"
                     hidden>

                    <?php if ($role === 'admin'): ?>
                    <section aria-labelledby="analytics-summary-heading">
                        <h2 id="analytics-summary-heading">Recorded academic results</h2>
                        <div id="analytics-summary"></div>
                    </section>
                    <section id="analytics-prediction" aria-label="Estimated GWA and advisory meaning"></section>
                    <?php endif; ?>

                    <section aria-labelledby="analytics-trend-heading">
                        <h2 id="analytics-trend-heading">Historical semester trend</h2>
                        <p id="trend-description"></p>
                        <div id="trend-chart" class="analytics-chart"></div>
                        <div class="data-table-scroll" role="region" aria-label="Historical semester data" tabindex="0">
                            <table id="trend-table"></table>
                        </div>
                    </section>

                    <section aria-labelledby="analytics-comparison-heading" class="comparison-builder-section">
                        <h2 id="analytics-comparison-heading">Academic comparisons</h2>
                        <p style="color: var(--text-gray); font-size: 0.9rem; margin-top: 0; margin-bottom: 20px;">
                            Side-by-side comparison builder evaluating cohorts, subjects, sections, or faculty assignments on shared scales.
                        </p>

                        <!-- COMPARISON BUILDER CONTROLS -->
                        <div class="card comparison-controls-card" style="padding: 18px 22px; margin-bottom: 24px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr)); gap: 16px; align-items: flex-end;">
                                <div>
                                    <label for="comparison-group" style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; color: var(--text-dark);">
                                        Comparison type
                                    </label>
                                    <select id="comparison-group" class="filter-select" aria-label="Comparison view" style="width: 100%;">
                                        <option value="semester">Semester</option>
                                        <option value="school_year">School year</option>
                                        <option value="year_level">Curriculum year</option>
                                        <option value="subject">Subject</option>
                                        <option value="section">Section</option>
                                        <option value="faculty_subject">Faculty assignment</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="comparison-metric" style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; color: var(--text-dark);">
                                        Metric
                                    </label>
                                    <select id="comparison-metric" class="filter-select" aria-label="Comparison metric" style="width: 100%;">
                                        <option value="gwa">Recorded class average</option>
                                        <option value="student_count">Student count</option>
                                        <option value="numeric_coverage_pct">Numeric grade coverage</option>
                                        <option value="final_coverage_pct">Final-outcome coverage</option>
                                        <option value="prediction_coverage_pct">Prediction coverage</option>
                                        <option value="risk_distribution">Advisory-risk distribution</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="compare-a" style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; color: var(--text-dark);">
                                        Compare A
                                    </label>
                                    <select id="compare-a" class="filter-select" aria-label="Compare group A" style="width: 100%;">
                                    </select>
                                </div>
                                <div>
                                    <label for="compare-b" style="display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 6px; color: var(--text-dark);">
                                        Compare B
                                    </label>
                                    <select id="compare-b" class="filter-select" aria-label="Compare group B" style="width: 100%;">
                                    </select>
                                </div>
                            </div>
                            <p id="comparison-description" style="font-size: 0.82rem; color: var(--text-gray); margin: 12px 0 0 0;"></p>
                        </div>

                        <!-- VALIDATION / SCOPE NOTICE -->
                        <div id="comparison-validation-notice" role="alert" style="display: none; margin-bottom: 20px; padding: 12px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 500;"></div>

                        <!-- SIDE-BY-SIDE PANELS -->
                        <div id="comparison-panels-grid" class="comparison-panels-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr)); gap: 20px; margin-bottom: 24px;">
                            <div id="panel-compare-a" class="card comparison-panel panel-a" style="padding: 20px 24px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card);">
                            </div>
                            <div id="panel-compare-b" class="card comparison-panel panel-b" style="padding: 20px 24px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card);">
                            </div>
                        </div>

                        <!-- NEUTRAL COMPARISON SUMMARY -->
                        <div id="comparison-summary-card" class="comparison-summary-card" style="padding: 18px 24px; margin-bottom: 24px; background: var(--bg-hover, #f8fafc); border: 1px solid var(--border-color); border-radius: 10px;">
                        </div>

                        <!-- VISUAL CHART WITH EQUAL AXIS & SCALE -->
                        <div class="card" style="padding: 20px 24px; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card);">
                            <h3 style="margin: 0 0 6px 0; font-size: 1.05rem; font-weight: 700; color: var(--text-dark);">Visual Comparison</h3>
                            <p style="margin: 0 0 16px 0; font-size: 0.82rem; color: var(--text-gray);">Side-by-side metric comparison rendered on identical axes and scales.</p>
                            <div id="comparison-chart" class="analytics-chart" style="margin-bottom: 12px;"></div>
                            <div id="comparison-legend" style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 0.82rem; color: var(--text-gray); margin-top: 10px;"></div>
                        </div>

                        <!-- EXACT VALUES TABLE FOR SELECTED GROUPS -->
                        <div class="card" style="padding: 20px 24px; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card);">
                            <h3 style="margin: 0 0 6px 0; font-size: 1.05rem; font-weight: 700; color: var(--text-dark);">Comparison Evidence</h3>
                            <p style="margin: 0 0 14px 0; font-size: 0.82rem; color: var(--text-gray);">Exact metrics for the selected comparison subjects.</p>
                            <div class="data-table-scroll" role="region" aria-label="Two group comparison evidence" tabindex="0">
                                <table id="side-by-side-table" class="data-table" style="width: 100%;"></table>
                            </div>
                        </div>

                        <!-- ALL ELIGIBLE GROUPS DISCLOSURE (Contains full #comparison-table) -->
                        <details id="all-groups-disclosure" class="card" style="padding: 16px 20px; margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-card);">
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
                </div>

                <!-- TAB PANEL 3: SUPPORT & INTERVENTIONS -->
                <div role="tabpanel"
                     id="tab-panel-support"
                     aria-labelledby="tab-btn-support"
                     class="analytics-tab-panel"
                     hidden>
                    <section aria-labelledby="analytics-support-heading">
                        <h2 id="analytics-support-heading">Support review summary</h2>
                        <p>Recorded cases and referrals support follow-up. Counts do not prove that an intervention caused a grade change.</p>
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
        if (typeof Chart === 'undefined') return;
        function getChartTheme() {
            const root = getComputedStyle(document.documentElement);
            return {
                text: root.getPropertyValue('--text-dark').trim() || '#1e293b',
                high: root.getPropertyValue('--risk-high').trim() || '#ef4444',
                mod: root.getPropertyValue('--risk-mod').trim() || '#f59e0b',
                low: root.getPropertyValue('--risk-low').trim() || '#10b981',
                none: root.getPropertyValue('--border-color').trim() || '#94a3b8',
                summa: root.getPropertyValue('--gold').trim() || '#e8b923',
                magna: '#265597',
                cumLaude: '#2d9cdb',
                insufficient: '#64748b'
            };
        }
        const theme = getChartTheme();
        const secEl = document.getElementById('sectionRiskChart');
        if (secEl) {
            new Chart(secEl.getContext('2d'), {
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
                    plugins: { legend: { position: 'bottom', labels: { color: theme.text } } },
                    scales: {
                        x: { stacked: true, max: 100, ticks: { callback: v => v + '%', color: theme.text } },
                        y: { stacked: true, ticks: { color: theme.text } }
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
            new Chart(honorEl.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: hLabels,
                    datasets: [{ data: hCounts, backgroundColor: hColors, borderWidth: 0, hoverOffset: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { display: false } }
                }
            });
        }
    });
    </script>
    <?php endif; ?>
    <script src="<?= $escape(BASE_URL) ?>assets/js/academic-analytics.js" defer></script>
    <?php
    require __DIR__ . '/../footer.php';
}
