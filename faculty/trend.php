<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/faculty_assessment_repository.php';
requireRole('faculty');
$user = currentUser(); $db = getDB(); $currentTerm = getCurrentTerm();
try {
    $data = facultyAssessmentRead($db, (int)$user['id'], $currentTerm);
    $scope = facultyAssessmentSelect($data['loads'], $_GET, $currentTerm);
} catch (InvalidArgumentException $e) { http_response_code(422); exit('Invalid assessment filters. Choose an assigned class and a valid academic period.');
} catch (DomainException $e) { http_response_code(403); exit('Assessment access denied. Choose a class assigned to your account.'); }
$myLoads = $data['loads']; $rows = $scope['current'] ? $data['rows'] : [];
$overall = facultyAssessmentSummary($rows); $selectedRows = []; $portfolio = [];
foreach ($myLoads as $load) {
    $classRows = array_values(array_filter($rows, fn($r) => (int)$r['subject_id'] === (int)$load['subject_id'] && $r['section'] === $load['section']));
    $portfolio[] = ['load' => $load, 'summary' => facultyAssessmentSummary($classRows)];
    if ($scope['load'] && (int)$scope['load']['load_id'] === (int)$load['load_id']) $selectedRows = $classRows;
}
$selected = facultyAssessmentSummary($selectedRows); $assessment = $selected['assessment']; $change = $selected['change'];
$prior = facultyAssessmentPriorStatus();
$escape = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$pct = fn($v) => $v === null ? 'Unavailable' : number_format($v, 1) . '%';
$signed = fn($v) => ($v > 0 ? '+' : '') . number_format($v, 2);
$periodLabel = 'SY ' . $scope['school_year'] . ' Â· ' . ([1=>'First Semester',2=>'Second Semester',3=>'Summer'][$scope['semester']]);
$pageTitle = 'Class Performance Trends';
$navItems = [['Home','index.php',''],['Dashboard','dashboard.php',''],['Class Analytics','analytics.php',''],['Performance Trends','trend.php',''],['Term Grade Submission','grades.php',''],['Concerns & Reports','feedback.php',''],['Settings','settings.php','']];
require_once '../includes/header.php'; require_once '../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/faculty-assessment.css?v=<?= substr(hash_file('sha256',__DIR__.'/../assets/css/faculty-assessment.css'),0,12) ?>">
<main class="main-content faculty-assessment">
  <div class="header"><div><h1>Class Performance Trends</h1><p>Recorded assessment progress in your assigned classes.</p></div><p class="faculty-period"><?= $escape($periodLabel) ?></p></div>
  <?php if (!$scope['current']): ?><p class="faculty-note" role="status">Historical Faculty comparison is unavailable without verified dated assignments. <a href="trend.php">Return to the current term</a>.</p><?php endif; ?>
  <?php if (!$myLoads): ?><section class="card"><h2>No assigned classes</h2><p>No current class loads are assigned to your account.</p></section><?php else: ?>
  <section aria-labelledby="faculty-overview-heading">
    <h2 id="faculty-overview-heading">Assigned classes overview</h2>
    <div class="faculty-kpis">
      <?php $latest = $overall['assessment']; $cards = [
        ['Class Loads',count($myLoads),'Assigned subject-sections','blue','class-loads'],
        ['Unique Students',$overall['students'],'Distinct Students with current assigned records','neutral','unique-students'],
        ['Latest Assessment Mean',$pct($latest['mean'] ?? null),$latest ? $latest['label'].' percentage average' : 'No recorded assessment percentages','teal','assessment-mean'],
        ['Requiring Attention',$overall['attention'],'Distinct Students with Moderate or High advisory risk','amber','attention'],
        ['Assessment Completeness',$latest ? $pct($latest['coverage']) : 'Unavailable',$latest ? $latest['count'].' of '.$latest['expected'].' '.$latest['label'].' scores recorded' : 'No recorded assessment stage','violet','assessment-coverage']
      ]; foreach ($cards as [$title,$value,$description,$accent,$metric]): ?>
      <div class="stat-card faculty-kpi faculty-kpi--<?= $accent ?>"><h3><?= $escape($title) ?></h3><p class="faculty-value" data-faculty-overview="<?= $metric ?>"><?= $escape($value) ?></p><p class="faculty-subtext"><?= $escape($description) ?></p></div>
      <?php endforeach; ?>
    </div>
    <details class="faculty-help"><summary>About these assessment values</summary><p>Percentage averages use valid recorded scores from one named stage, with equal weight per assigned Studentâ€“subject record. Coverage counts these recorded slots, not an inferred enrollment population. Missing scores remain unavailable; numeric zero is recorded. Attention uses each Student's latest recorded assessment or finalized outcome in your assignments, counted once across classes.</p><p>Advisory risk is not official academic standing. Class differences do not measure teaching quality.</p></details>
    <details class="faculty-help"><summary>Assigned-class details</summary><div class="faculty-table-scroll" role="region" aria-label="Assigned-class assessment details" tabindex="0"><table><caption>Current assigned classes. Each mean identifies its own assessment stage.</caption><thead><tr><th scope="col">Subject and section</th><th scope="col">Students with records</th><th scope="col">Latest assessment</th><th scope="col">Requiring attention</th><th scope="col">Assessment completeness</th></tr></thead><tbody>
      <?php foreach ($portfolio as $item): $s=$item['summary'];$a=$s['assessment']; ?><tr><th scope="row"><?= $escape($item['load']['title'].' â€” '.$item['load']['section']) ?></th><td><?= $s['students'] ?></td><td><?= $pct($a['mean'] ?? null) ?><br><span class="faculty-subtext"><?= $escape($a['label'] ?? 'Not recorded') ?></span></td><td><?= $s['attention'] ?></td><td><?= $a ? $a['count'].' of '.$a['expected'].' Â· '.$pct($a['coverage']) : 'Unavailable' ?></td></tr><?php endforeach; ?>
    </tbody></table></div></details>
  </section>
  <section aria-labelledby="faculty-selected-heading">
    <h2 id="faculty-selected-heading">Selected class analysis</h2>
    <form method="GET" action="trend.php" class="faculty-class-filter card"><div><label for="faculty-load">Assigned class</label><select id="faculty-load" name="load"><?php foreach ($myLoads as $load): $key=$load['subject_id'].'|'.$load['section']; ?><option value="<?= $escape($key) ?>"<?= $scope['key']===$key ? ' selected' : '' ?>><?= $escape($load['title'].' â€” '.$load['section']) ?></option><?php endforeach; ?></select></div><button type="submit" class="btn btn--primary">Analyze class</button><a class="btn btn--secondary" href="grades.php?class=<?= $escape($scope['load']['subject_id'].'_'.$scope['load']['section']) ?>">Open term grade submission</a></form>
    <p id="faculty-class-scope" class="faculty-subtext"><?= $escape($scope['load']['title'].' â€” '.$scope['load']['section'].' Â· '.$periodLabel) ?></p>
    <div class="faculty-kpis">
      <?php $cards = [
        ['Students Enrolled',$selected['students'],'Students with current records in the selected class','blue','students'],
        ['Latest Assessment Mean',$pct($assessment['mean'] ?? null),$assessment ? $assessment['label'].' percentage average' : 'No recorded assessment percentages','teal','assessment-mean'],
        ['Requiring Attention',$selected['attention'],'Moderate or High advisory risk','amber','attention'],
        ['Assessment Completeness',$assessment ? $pct($assessment['coverage']) : 'Unavailable',$assessment ? $assessment['count'].' of '.$assessment['expected'].' '.$assessment['label'].' scores recorded' : 'No recorded assessment stage','violet','assessment-coverage'],
        ['Class change',$change['point_change'] === null ? 'Unavailable' : $signed($change['point_change']).' pp',$change['point_change'] === null ? $change['reason'] : $change['from'].' to '.$change['to'].' Â· '.$change['sample'].' matched Students'.($change['provisional'] ? ' Â· Provisional subset' : ''),'blue','class-change']
      ]; foreach ($cards as [$title,$value,$description,$accent,$metric]): ?>
      <div class="stat-card faculty-kpi faculty-kpi--<?= $accent ?>"><h3><?= $escape($title) ?></h3><p class="faculty-value" data-faculty-selected="<?= $metric ?>"><?= $escape($value) ?></p><p class="faculty-subtext"><?= $escape($description) ?></p><?php if ($metric==='class-change' && $change['point_change']!==null): ?><span class="faculty-subtext">Percentage points<?php if($change['relative_change']!==null): ?> Â· <?= $signed($change['relative_change']) ?>% relative change<?php endif; ?></span><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <details class="faculty-help" id="faculty-metric-help"><summary>Finalized outcomes and calculation details</summary><dl class="faculty-final-status"><div><dt>Final numeric-grade coverage</dt><dd data-faculty-selected="final-coverage"><?= $pct($selected['final']['coverage']) ?> Â· <?= $selected['final']['count'] ?> of <?= $selected['expected'] ?> eligible numeric outcomes</dd></div><div><dt>Assigned-class finalized mean</dt><dd data-faculty-selected="final-mean"><?= $selected['final']['mean'] === null ? 'Unavailable â€” no eligible finalized numeric outcomes are recorded.' : number_format($selected['final']['mean'],2).' finalized eligible grade points' ?></dd></div></dl><p>Class change is the later assessment mean minus the earlier assessment mean, using only the same Students recorded at both adjacent stages. Unmatched Students are excluded from both comparison means; any partial matched set is labeled provisional. Relative change divides by the earlier matched mean and is unavailable when that mean is zero.</p><p>Final points, cumulative GWA and estimates are separate from raw percentages. Finalized mean preserves canonical unit weighting and exclusions. Class change does not measure teaching quality or establish official standing.</p></details>
    <section class="faculty-prior" aria-labelledby="faculty-prior-heading"><h3 id="faculty-prior-heading">Previous-semester comparison</h3><p><strong>Unavailable</strong> â€” <?= $escape($prior['reason']) ?></p></section>
    <div class="faculty-chart-grid">
      <section class="card" aria-labelledby="assessment-chart-heading"><h3 id="assessment-chart-heading">Class assessment trend</h3><p id="assessment-chart-summary">Recorded percentage averages by grading period for the selected class. Missing stages are gaps; Final is recorded separately in grade points.</p><div class="faculty-chart-wrap"><canvas id="progressionChart" role="img" aria-label="Recorded assessment percentage averages, zero to one hundred" aria-describedby="assessment-chart-summary faculty-stage-caption">Exact values appear in the assessment table below.</canvas></div><p id="faculty-chart-status" role="status">Exact values remain available below if charts cannot load.</p></section>
      <section class="card" aria-labelledby="risk-chart-heading"><h3 id="risk-chart-heading">Advisory risk by recorded stage</h3><p>High, Moderate, Low or No Data within recorded stages. Unrecorded stages have no distribution bars.</p><div class="faculty-chart-wrap"><canvas id="riskMovementChart" role="img" aria-label="Advisory risk counts for recorded stages" aria-describedby="faculty-risk-caption">Exact counts appear in the risk table below.</canvas></div><p class="faculty-subtext">Pre-Final and Final are not classified until records exist. Risk is not official standing.</p></section>
    </div>
    <section class="card" aria-labelledby="faculty-encoding-heading"><h3 id="faculty-encoding-heading">Assessment recording progress</h3><div class="faculty-table-scroll" role="region" aria-label="Assessment recording and exact averages" tabindex="0"><table><caption id="faculty-stage-caption">Recorded current Studentâ€“subject slots. Final outcomes use a separate point scale; they are never plotted as percentages.</caption><thead><tr><th scope="col">Stage</th><th scope="col">Recorded</th><th scope="col">Coverage</th><th scope="col">Average and scale</th><th scope="col">Status</th></tr></thead><tbody>
      <?php foreach ($selected['stages'] as $field=>$stage): ?><tr data-assessment-stage="<?= $field ?>"><th scope="row"><?= $escape($stage['label']) ?></th><td><?= $stage['count'] ?> of <?= $stage['expected'] ?></td><td><?= $pct($stage['coverage']) ?></td><td><?= $stage['mean']===null ? 'Unavailable' : number_format($stage['mean'],2).'%' ?></td><td><?= $stage['count']===0 ? 'Not recorded' : ($stage['count']<$stage['expected'] ? 'Partially recorded' : 'Recorded') ?></td></tr><?php endforeach; ?>
      <tr data-assessment-stage="final"><th scope="row">Final</th><td><?= $selected['final']['recorded'] ?> of <?= $selected['expected'] ?> outcomes</td><td><?= $selected['expected'] ? $pct($selected['final']['recorded']/$selected['expected']*100) : 'Unavailable' ?></td><td><?= $selected['final']['mean']===null ? 'Unavailable Â· point scale' : number_format($selected['final']['mean'],2).' eligible grade points (unit-weighted)' ?></td><td><?= $selected['final']['recorded'] ? 'Final entries recorded' : 'Not recorded' ?></td></tr>
    </tbody></table></div>
    <details class="faculty-help"><summary>Exact advisory risk counts</summary><div class="faculty-table-scroll" tabindex="0" role="region" aria-label="Advisory risk counts"><table><caption id="faculty-risk-caption">Risk for recorded stages; No Data includes missing or unclassifiable records only within an observed stage.</caption><thead><tr><th scope="col">Stage</th><th scope="col">High Risk</th><th scope="col">Moderate Risk</th><th scope="col">Low Risk</th><th scope="col">No Data</th></tr></thead><tbody><?php $risks=[];foreach($selected['stages'] as $s)$risks[$s['label']]=$s['risk'];$risks['Final']=$selected['final']['risk']; foreach($risks as $label=>$risk): ?><tr><th scope="row"><?= $escape($label) ?></th><?php if($risk===null): ?><td colspan="4">Not recorded</td><?php else: foreach($risk as $count): ?><td><?= $count ?></td><?php endforeach; endif; ?></tr><?php endforeach; ?></tbody></table></div></details></section>
    <section class="card" aria-labelledby="faculty-notable-heading"><h3 id="faculty-notable-heading">Students with notable assessment changes</h3><p>Students whose recorded percentage changed by at least 5 percentage points between Preliminary and Midterm. This is a review threshold, not statistical significance.</p><div class="faculty-table-scroll faculty-notable-scroll" role="region" aria-label="Notable assessment changes in selected class" tabindex="0"><table><thead><tr><th scope="col">Student</th><th scope="col">Preliminary</th><th scope="col">Midterm</th><th scope="col">Change</th><th scope="col">Current advisory risk and source</th></tr></thead><tbody><?php foreach($selected['notable'] as $row): ?><tr><th scope="row"><?= $escape($row['name']) ?></th><td><?= number_format($row['prelim'],2) ?>%</td><td><?= number_format($row['midterm'],2) ?>%</td><td><?= $signed($row['change']) ?> percentage points</td><td><?= $escape($row['current_risk']==='NONE' ? 'No Data' : $row['current_risk']) ?> Â· <?= $escape($row['risk_stage']) ?></td></tr><?php endforeach; ?><?php if(!$selected['notable']): ?><tr><td colspan="5"><?= $selected['stages']['prelim']['count'] && $selected['stages']['midterm']['count'] ? 'No matched Student records changed by at least 5 percentage points.' : 'Recorded Preliminary and Midterm values are required.' ?></td></tr><?php endif; ?></tbody></table></div></section>
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
