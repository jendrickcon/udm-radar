<?php
declare(strict_types=1);

function analyticsPage(string $role): void {
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../auth.php';
    requireRole($role);
    $user = currentUser();

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
                    <section aria-labelledby="analytics-summary-heading">
                        <h2 id="analytics-summary-heading">Recorded academic results</h2>
                        <div id="analytics-summary"></div>
                        <div id="analytics-secondary-summary" class="analytics-secondary-metrics"></div>
                    </section>

                    <section id="analytics-prediction" aria-label="Estimated GWA and advisory meaning"></section>

                    <?php if ($role === 'admin'): ?>
                    <section aria-labelledby="analytics-section-summary-heading">
                        <h2 id="analytics-section-summary-heading">Section comparison summary</h2>
                        <p id="section-summary-description" style="color: var(--text-gray); font-size: 0.875rem; margin-bottom: 12px;"></p>
                        <div class="data-table-scroll" role="region" aria-label="Section comparison data" tabindex="0">
                            <table id="section-summary-table"></table>
                        </div>
                    </section>
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
                    <section aria-labelledby="analytics-trend-heading">
                        <h2 id="analytics-trend-heading">Historical semester trend</h2>
                        <p id="trend-description"></p>
                        <div id="trend-chart" class="analytics-chart"></div>
                        <div class="data-table-scroll" role="region" aria-label="Historical semester data" tabindex="0">
                            <table id="trend-table"></table>
                        </div>
                    </section>

                    <section aria-labelledby="analytics-comparison-heading">
                        <h2 id="analytics-comparison-heading">Academic comparisons</h2>
                        <div style="margin-bottom: 14px;">
                            <label for="comparison-group" style="font-weight: 600; font-size: 0.9rem;">Comparison view</label>
                            <select id="comparison-group" class="filter-select" style="max-width: 320px;"></select>
                        </div>
                        <p id="comparison-description"></p>
                        <div id="comparison-chart" class="analytics-chart"></div>
                        <div class="data-table-scroll" role="region" aria-label="Comparison data" tabindex="0">
                            <table id="comparison-table"></table>
                        </div>
                        <p class="analytics-fairness">
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
    <script src="<?= $escape(BASE_URL) ?>assets/js/academic-analytics.js" defer></script>
    <?php
    require __DIR__ . '/../footer.php';
}
