<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/faculty_assessment_repository.php';
requireRole('faculty');
header('Content-Type: text/html; charset=UTF-8');
$user = currentUser(); $db = getDB(); $currentTerm = getCurrentTerm();
try {
    $data = facultyAssessmentRead($db, (int)$user['id'], $currentTerm);
    $scope = facultyAssessmentSelect($data['loads'], $_GET, $currentTerm);
} catch (InvalidArgumentException $e) { http_response_code(422); exit('Invalid assessment filters. Choose an assigned class and a valid academic period.');
} catch (DomainException $e) { http_response_code(403); exit('Assessment access denied. Choose a class assigned to your account.'); }
$myLoads = $data['loads']; $rows = $scope['current'] ? $data['rows'] : [];
$overview = facultyAssessmentOverview($myLoads, $rows);
$overall = $overview['summary']; $portfolio = $overview['classes']; $selectedRows = [];
foreach ($portfolio as $item) {
    if ($scope['load'] && (int)$scope['load']['load_id'] === (int)$item['load']['load_id']) {
        $selectedRows = array_values(array_filter($rows, fn($r) => (int)$r['subject_id'] === (int)$item['load']['subject_id'] && $r['section'] === $item['load']['section']));
    }
}
$selected = facultyAssessmentSummary($selectedRows); $assessment = $selected['assessment']; $change = $selected['change'];
$prior = facultyAssessmentPriorStatus();
$escape = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$pct = fn($v) => $v === null ? 'Unavailable' : number_format($v, 1) . '%';
$signed = fn($v) => ($v > 0 ? '+' : '') . number_format($v, 2);
$periodLabel = 'SY ' . $scope['school_year'] . ' · ' . ([1=>'First Semester',2=>'Second Semester',3=>'Summer'][$scope['semester']]);
$pageTitle = 'Class Performance Trends';
$navItems = [['Home','index.php',''],['Dashboard','dashboard.php',''],['Class Analytics','analytics.php',''],['Performance Trends','trend.php',''],['Term Grade Submission','grades.php',''],['Concerns & Reports','feedback.php',''],['Settings','settings.php','']];
require_once '../includes/header.php'; require_once '../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/faculty-assessment.css?v=<?= substr(hash_file('sha256',__DIR__.'/../assets/css/faculty-assessment.css'),0,12) ?>">
<main class="main-content faculty-assessment">
  <div class="header"><div><h1>Class Performance Trends</h1><p>Recorded assessment progress in your assigned classes.</p></div><p class="faculty-period"><?= $escape($periodLabel) ?></p></div>
  <p class="faculty-subtext">Student risk and projected outcomes support academic review. They do not replace official grades or Faculty evaluation.</p>
  <?php if (!$scope['current']): ?><p class="faculty-note" role="status">Historical Faculty comparison is unavailable without verified dated assignments. <a href="trend.php">Return to the current term</a>.</p><?php endif; ?>
  <?php if (!$myLoads): ?><section class="card"><h2>No assigned classes</h2><p>No current class loads are assigned to your account.</p></section><?php else: ?>
  <section aria-labelledby="faculty-overview-heading">
    <h2 id="faculty-overview-heading">Assigned classes overview</h2>
    <div class="faculty-kpis">
      <?php $latest = $overview['assessment']; $cards = [
        ['Assigned Classes',count($myLoads),'Current assigned subject-sections','blue','assigned-classes'],
        ['Unique Students',$overall['students'],'Distinct Students with current assigned records','neutral','unique-students'],
        ['Overall Latest Assessment Mean',$latest ? $pct($latest['mean']) : $overview['status'],$overview['detail'],'teal','assessment-mean'],
        ['Requiring Attention',$overall['attention'],'Distinct Students with Moderate or High advisory risk','amber','attention'],
        ['Assessment Coverage',$latest ? $pct($latest['coverage']) : 'Unavailable',$latest ? 'Across '.count($myLoads).' assigned classes' : 'See coverage for each class below','violet','assessment-coverage']
      ]; foreach ($cards as [$title,$value,$description,$accent,$metric]): ?>
      <div class="stat-card faculty-kpi faculty-kpi--<?= $accent ?>"><h3><?= $escape($title) ?></h3><p class="faculty-value" data-faculty-overview="<?= $metric ?>"><?= $escape($value) ?></p><p class="faculty-subtext"><?= $escape($description) ?></p><?php if($metric==='assessment-mean'): ?><p class="faculty-subtext">Across <?= count($myLoads) ?> assigned classes</p><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <section class="faculty-table-panel">
    <h3 id="faculty-classes-heading">Assigned-class comparison</h3>
    <p class="faculty-subtext">Latest recorded assessment results for your current assigned classes.</p>
    <div class="faculty-table-scroll" role="region" aria-labelledby="faculty-classes-heading" tabindex="0"><table class="faculty-class-table"><caption class="sr-only">Latest recorded stage, assessment mean, coverage and review count for each current assigned subject-section.</caption><thead><tr><th scope="col">Subject and section</th><th scope="col">Students</th><th scope="col">Latest stage</th><th scope="col">Assessment mean</th><th scope="col">Coverage</th><th scope="col">Requiring attention</th><th scope="col">Action</th></tr></thead><tbody>
      <?php foreach ($portfolio as $item): $s=$item['summary'];$a=$s['assessment'];$key=$item['load']['subject_id'].'|'.$item['load']['section']; ?><tr><th scope="row"><span class="faculty-cell-main"><?= $escape($item['load']['title']) ?></span><span class="faculty-subtext faculty-cell-sub"><?= $escape($item['load']['section']) ?></span></th><td><?= $s['students'] ?></td><td><?= $escape($a['label'] ?? 'Not recorded') ?></td><td><?= $pct($a['mean'] ?? null) ?></td><td><?php if($a): ?><span class="faculty-cell-main"><?= $a['count'].' of '.$a['expected'] ?></span><span class="faculty-subtext faculty-cell-sub"><?= $pct($a['coverage']) ?></span><?php else: ?>Unavailable<?php endif; ?></td><td><?= $s['attention'] ?></td><td><a class="btn btn--secondary btn--sm" aria-label="<?= $escape('Review class: '.$item['load']['title'].' — '.$item['load']['section']) ?>" href="trend.php?load=<?= $escape(rawurlencode($key)) ?>#faculty-selected-heading">Review class</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="faculty-subtext">Class differences support review and do not measure teaching quality.</p>
    </section>
  </section>
  <section aria-labelledby="faculty-selected-heading">
    <h2 id="faculty-selected-heading" tabindex="-1">Selected class analysis</h2>
    <form method="GET" action="trend.php" class="faculty-class-filter card"><div><label for="faculty-load">Assigned class</label><select id="faculty-load" name="load"><?php foreach ($myLoads as $load): $key=$load['subject_id'].'|'.$load['section']; ?><option value="<?= $escape($key) ?>"<?= $scope['key']===$key ? ' selected' : '' ?>><?= $escape($load['title'].' — '.$load['section']) ?></option><?php endforeach; ?></select></div><button type="submit" class="btn btn--primary">Analyze class</button><a class="btn btn--secondary" href="grades.php?class=<?= $escape($scope['load']['subject_id'].'_'.$scope['load']['section']) ?>">Open term grade submission</a></form>
    <p id="faculty-class-scope" class="faculty-subtext"><?= $escape($scope['load']['title'].' — '.$scope['load']['section'].' · '.$periodLabel) ?></p>
    <?php
    $hasChange=$change['point_change']!==null;
    $spoken=fn($v)=>($v<0?'minus ':($v>0?'plus ':'')).number_format(abs($v),2);
    $changeLabel=$hasChange?'Class change from '.$change['from'].' to '.$change['to'].' based on '.$change['sample'].' matched Students: '.$spoken($change['point_change']).' percentage points'.($change['relative_change']!==null?', '.$spoken($change['relative_change']).' percent relative change':'; relative change unavailable because the earlier matched mean is zero').($change['provisional']?'; provisional matched subset.':'.'):'Class change unavailable. '.$change['reason'];
    $shortStage=fn($stage)=>$stage==='Preliminary'?'Prelim':$stage;
    ?>
    <section class="faculty-table-panel">
    <h3 id="faculty-selected-summary-heading">Selected class summary</h3>
    <div class="faculty-table-scroll" role="region" aria-labelledby="faculty-selected-summary-heading" tabindex="0"><table class="faculty-selected-table"><caption class="sr-only">Recorded assessment results for the selected assigned class. Class change uses percentage points between the same matched Students.</caption><thead><tr><th scope="col">Students</th><th scope="col">Latest stage</th><th scope="col">Assessment mean</th><th scope="col">Coverage</th><th scope="col">Requiring attention</th><th scope="col">Class change (<abbr class="faculty-subtext" title="Percentage points">pp</abbr>)</th></tr></thead><tbody><tr>
      <td data-faculty-selected="students"><?= $selected['students'] ?></td><td><?= $escape($assessment['label']??'Not recorded') ?></td><td data-faculty-selected="assessment-mean"><?= $pct($assessment['mean']??null) ?></td><td><?php if($assessment): ?><span class="faculty-cell-main"><?= $assessment['count'].' of '.$assessment['expected'] ?></span><span class="faculty-subtext faculty-cell-sub" data-faculty-selected="assessment-coverage"><?= $pct($assessment['coverage']) ?></span><?php else: ?><span data-faculty-selected="assessment-coverage">Unavailable</span><?php endif; ?></td><td data-faculty-selected="attention"><?= $selected['attention'] ?></td>
      <td class="faculty-change-cell" aria-label="<?= $escape($changeLabel) ?>"><div aria-hidden="true"><strong data-faculty-selected="class-change"><?= $hasChange?$signed($change['point_change']).' pp':'Unavailable' ?></strong><?php if($hasChange): ?>
        <span class="faculty-change-line"><span class="faculty-copy-wide"><?= $escape($change['from'].' to '.$change['to'].' · '.$change['sample'].' matched Students'.($change['provisional']?' · Provisional subset':'')) ?></span><span class="faculty-copy-compact"><?= $escape($shortStage($change['from']).' to '.$shortStage($change['to']).' · '.$change['sample'].' matched'.($change['provisional']?' · subset':'')) ?></span></span>
        <span class="faculty-change-line"><span class="faculty-copy-wide">Percentage points · <?= $change['relative_change']!==null?$signed($change['relative_change']).'% relative change':'relative change unavailable' ?></span><span class="faculty-copy-compact">Percentage points · <?= $change['relative_change']!==null?$signed($change['relative_change']).'% relative':'relative unavailable' ?></span></span>
        <?php else: ?><span class="faculty-change-line">No matched stage pair available.</span><?php endif; ?></div></td>
    </tr></tbody></table></div>
    </section>
    <?php if ($selected['final']['count'] > 0 && $selected['final']['mean'] !== null): ?>
    <p class="faculty-subtext faculty-final-note"><strong>Assigned-Class Finalized Mean:</strong> <span data-faculty-selected="final-mean"><?= number_format($selected['final']['mean'],2) ?> grade points</span> · unit-weighted, 0.00–4.00 point scale · <span data-faculty-selected="final-coverage"><?= $selected['final']['count'] ?> of <?= $selected['expected'] ?> eligible numeric outcomes (<?= $pct($selected['final']['coverage']) ?>)</span></p>
    <?php else: ?><p class="faculty-subtext faculty-final-note">Final-grade averages will appear after eligible final outcomes are recorded.</p><?php endif; ?>
    <p class="faculty-subtext faculty-prior"><strong>Previous-semester comparison: Unavailable.</strong> <?= $escape($prior['reason']) ?></p>
    <div class="faculty-chart-grid">
      <section class="card" aria-labelledby="assessment-chart-heading"><h3 id="assessment-chart-heading">Class assessment trend</h3><p id="assessment-chart-summary">Recorded percentage averages by grading period for the selected class. Missing stages are gaps; Final is recorded separately in grade points.</p><div class="faculty-chart-wrap"><canvas id="progressionChart" role="img" aria-label="Recorded assessment percentage averages, zero to one hundred" aria-describedby="assessment-chart-summary faculty-stage-caption">Exact values appear in the assessment table below.</canvas></div><p id="faculty-chart-status" role="status">Exact values remain available below if charts cannot load.</p></section>
      <section class="card" aria-labelledby="risk-chart-heading"><h3 id="risk-chart-heading">Advisory risk by recorded stage</h3><p>High, Moderate, Low or No Data within recorded stages. Unrecorded stages have no distribution bars.</p><div class="faculty-chart-wrap"><canvas id="riskMovementChart" role="img" aria-label="Advisory risk counts for recorded stages" aria-describedby="faculty-risk-caption">Exact counts appear in the risk table below.</canvas></div><p class="faculty-subtext">Unrecorded stages remain unavailable.</p></section>
    </div>
    <section class="card" aria-labelledby="faculty-encoding-heading"><h3 id="faculty-encoding-heading">Assessment recording progress</h3><div class="faculty-table-scroll" role="region" aria-label="Assessment recording and exact averages" tabindex="0"><table><caption id="faculty-stage-caption">Recorded current Student–subject slots. Final outcomes use a separate point scale; they are never plotted as percentages.</caption><thead><tr><th scope="col">Stage</th><th scope="col">Recorded</th><th scope="col">Coverage</th><th scope="col">Average and scale</th><th scope="col">Status</th></tr></thead><tbody>
      <?php foreach ($selected['stages'] as $field=>$stage): ?><tr data-assessment-stage="<?= $field ?>"><th scope="row"><?= $escape($stage['label']) ?></th><td><?= $stage['count'] ?> of <?= $stage['expected'] ?></td><td><?= $pct($stage['coverage']) ?></td><td><?= $stage['mean']===null ? 'Unavailable' : number_format($stage['mean'],2).'%' ?></td><td><?= $stage['count']===0 ? 'Not recorded' : ($stage['count']<$stage['expected'] ? 'Partially recorded' : 'Recorded') ?></td></tr><?php endforeach; ?>
      <tr data-assessment-stage="final"><th scope="row">Final</th><td><?= $selected['final']['recorded'] ?> of <?= $selected['expected'] ?> outcomes</td><td><?= $selected['expected'] ? $pct($selected['final']['recorded']/$selected['expected']*100) : 'Unavailable' ?></td><td><?= $selected['final']['mean']===null ? 'Unavailable · point scale' : number_format($selected['final']['mean'],2).' eligible grade points (unit-weighted)' ?></td><td><?= $selected['final']['recorded'] ? 'Final entries recorded' : 'Not recorded' ?></td></tr>
    </tbody></table></div>
    <details class="faculty-info-disclosure"><summary><svg data-disclosure-icon="table" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M3 9h18M3 14h18M10 9v11"></path></svg><span>View risk-band counts</span></summary><div class="faculty-table-scroll" tabindex="0" role="region" aria-label="Advisory risk counts"><table><caption id="faculty-risk-caption">Risk for recorded stages; No Data includes missing or unclassifiable records only within an observed stage.</caption><thead><tr><th scope="col">Stage</th><th scope="col">High</th><th scope="col">Moderate</th><th scope="col">Low</th><th scope="col">No data</th></tr></thead><tbody><?php $risks=[];foreach($selected['stages'] as $s)$risks[$s['label']]=$s['risk'];$risks['Final']=$selected['final']['risk']; foreach($risks as $label=>$risk): if($risk===null)continue; ?><tr><th scope="row"><?= $escape($label) ?></th><?php foreach($risk as $count): ?><td><?= $count ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php if(!array_filter($risks,fn($risk)=>$risk!==null)): ?><p class="faculty-subtext faculty-risk-empty">No recorded stage data are available.</p><?php endif; ?></details></section>
    <?php if ($selected['notable']): ?>
    <section class="card" aria-labelledby="faculty-notable-heading"><h3 id="faculty-notable-heading">Notable assessment changes</h3><p>Students whose recorded score changed by at least 5 percentage points between recorded stages.</p><p class="faculty-subtext">Preliminary to Midterm · Review threshold, not statistical significance.</p><div class="faculty-table-scroll faculty-notable-scroll" role="region" aria-label="Notable assessment changes in selected class" tabindex="0"><table><caption>Selected-class Preliminary and Midterm changes of at least 5 percentage points.</caption><thead><tr><th scope="col">Student</th><th scope="col">Preliminary</th><th scope="col">Midterm</th><th scope="col">Change</th><th scope="col">Current advisory risk and source</th></tr></thead><tbody><?php foreach($selected['notable'] as $row): ?><tr><th scope="row"><?= $escape($row['name']) ?></th><td><?= number_format($row['prelim'],2) ?>%</td><td><?= number_format($row['midterm'],2) ?>%</td><td><?= $signed($row['change']) ?> percentage points</td><td><?= $escape($row['current_risk']==='NONE' ? 'No Data' : $row['current_risk']) ?> · <?= $escape($row['risk_stage']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <p class="faculty-subtext">Prototype performance on synthetic academic records.</p>
</main>
<?php if ($myLoads): ?>
<script id="faculty-assessment-data" type="application/json"><?= json_encode(['stages'=>$selected['stages'],'final'=>$selected['final'],'expected'=>$selected['expected']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>assets/js/faculty-assessment.js?v=<?= substr(hash_file('sha256',__DIR__.'/../assets/js/faculty-assessment.js'),0,12) ?>"></script>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>
