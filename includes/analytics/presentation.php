<?php
declare(strict_types=1);

function analyticsPage(string $role):void {
    require_once __DIR__.'/../../config/constants.php';
    require_once __DIR__.'/../auth.php';
    requireRole($role);$user=currentUser();
    $pageTitle=match($role){'student'=>'Academic review','admin'=>'Comparative academic review','faculty'=>'Assigned-class review',default=>throw new DomainException('Unsupported role')};
    $navItems=match($role){
        'student'=>[['Home','index.php',''],['Dashboard','dashboard.php',''],['Grades & History','grades.php',''],['Performance Trend','trend.php',''],['Academic review','academic_analytics.php',''],['Feedback & Support','feedback.php',''],['Settings','settings.php','']],
        'faculty'=>[['Home','index.php',''],['Dashboard','dashboard.php',''],['Class Analytics','analytics.php',''],['Assigned-class review','assigned_analytics.php',''],['Performance Trends','trend.php',''],['Term Grade Submission','grades.php',''],['Concerns & Reports','feedback.php',''],['Settings','settings.php','']],
        'admin'=>[['Dashboard','index.php',''],['Students','students.php',''],['Faculty','faculty.php',''],['Grades','grades.php',''],['Program Analytics','analytics.php',''],['Comparative academic review','comparative_analytics.php',''],['Activity & Inbox','activity.php',''],['Settings','settings.php','']],
    };
    require __DIR__.'/../header.php';require __DIR__.'/../sidebar.php';
    $escape=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    $labels=['school_year'=>'School year','semester'=>'Semester','year_level'=>'Subject curriculum year','subject'=>'Subject','section'=>'Section','faculty'=>'Faculty member','case_status'=>'Case status','decision'=>'Referral decision'];
    $fields=$role==='student'?['school_year','semester']:array_keys($labels);
    ?>
    <link rel="stylesheet" href="<?= $escape(BASE_URL) ?>assets/css/academic-analytics.css">
    <main class="main-content analytics-page" id="academic-analytics" data-role="<?= $escape($role) ?>" data-endpoint="<?= $escape(BASE_URL) ?>api/academic_analytics.php">
        <header class="header"><div><h1><?= $escape($pageTitle) ?></h1><p>Understand recorded academic patterns, data coverage and the next review step.</p></div></header>
        <p class="analytics-disclosure">Prototype performance on synthetic academic records</p>
        <form id="analytics-filters" class="card" aria-label="Academic review filters">
            <fieldset disabled><legend>Review scope</legend><div class="analytics-filter-grid">
                <?php foreach($fields as $key): ?><div><label for="analytics-<?= $escape($key) ?>"><?= $escape($labels[$key]) ?></label><select id="analytics-<?= $escape($key) ?>" name="<?= $escape($key) ?>"><option value="">All recorded values</option></select></div><?php endforeach; ?>
            </div></fieldset>
            <div class="analytics-actions"><button type="submit" class="btn btn--primary">Apply filters</button><button type="button" id="analytics-clear" class="btn btn--secondary">Clear filters</button></div>
            <?php if($role!=='student'): ?><p class="analytics-note">Case status and referral decision apply to the support summary. Academic section and Faculty comparisons use verified current assignments; undated history is not attributed to a teacher.</p><?php endif; ?>
        </form>
        <p id="analytics-state" role="status" aria-live="polite">Loading academic review…</p>
        <div id="analytics-results" hidden aria-busy="true">
            <p id="active-filters" class="analytics-context"></p>
            <section aria-labelledby="analytics-summary-heading"><h2 id="analytics-summary-heading">Recorded academic results</h2><div id="analytics-summary"></div></section>
            <section id="analytics-prediction" aria-label="Estimated GWA and advisory meaning"></section>
            <section aria-labelledby="analytics-trend-heading"><h2 id="analytics-trend-heading">Historical semester trend</h2><p id="trend-description"></p><div id="trend-chart" class="analytics-chart"></div><div class="data-table-scroll" role="region" aria-label="Historical semester data" tabindex="0"><table id="trend-table"></table></div></section>
            <?php if($role!=='student'): ?>
            <section aria-labelledby="analytics-comparison-heading"><h2 id="analytics-comparison-heading">Academic comparisons</h2>
                <label for="comparison-group">Comparison view<select id="comparison-group"></select></label>
                <p id="comparison-description"></p><div id="comparison-chart" class="analytics-chart"></div><div class="data-table-scroll" role="region" aria-label="Comparison data" tabindex="0"><table id="comparison-table"></table></div>
                <p class="analytics-fairness">Comparative academic patterns support authorized review and do not independently measure teaching performance. Differences may reflect Student composition, prior achievement, subject difficulty, class size, record completeness, and semester conditions.</p>
            </section>
            <section aria-labelledby="analytics-support-heading"><h2 id="analytics-support-heading">Support review summary</h2><p>Recorded cases and referrals support follow-up. Counts do not prove that an intervention caused a grade change.</p>
                <div class="data-table-scroll" role="region" aria-label="Support summary data" tabindex="0"><table id="support-table"></table></div><p id="support-limits"></p>
                <h3>Open authorized records</h3><p>These links open existing review workspaces; this view creates no notice or decision.</p><div class="data-table-scroll" role="region" aria-label="Authorized case links" tabindex="0"><table id="drilldown-table"></table></div>
            </section>
            <?php endif; ?>
            <section aria-labelledby="analytics-method-heading"><h2 id="analytics-method-heading">Reading these results</h2><div id="analytics-method"></div></section>
        </div>
    </main>
    <script src="<?= $escape(BASE_URL) ?>assets/js/academic-analytics.js" defer></script>
    <?php require __DIR__.'/../footer.php';
}
