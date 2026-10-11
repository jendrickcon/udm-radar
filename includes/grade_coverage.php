<?php
require_once __DIR__ . '/../config/constants.php';

function gradeCoverageRecord(array $student, array $current, array $history, ?array $prediction): array {
    $counts = array_fill_keys(['prelim', 'midterm', 'prefinal'], 0);
    $missing = $counts;
    $prelim = []; $numeric = []; $outcomes = [];
    foreach ($current as $grade) {
        foreach ($counts as $period => $_) {
            if (($grade[$period] ?? null) === null) $missing[$period]++;
            if (isValidTermPercentage($grade[$period] ?? null)) {
                $counts[$period]++;
                $point = normalizeTermGrade($grade[$period]);
                $numeric[] = ['grade' => $point, 'units' => $grade['units']];
                if ($period === 'prelim') $prelim[] = ['grade' => $point, 'units' => $grade['units']];
            }
        }
        $numeric[] = ['grade' => $grade['final_grade'] ?? null, 'units' => $grade['units']];
        $label = formatFinalGrade($grade['final_grade'] ?? null);
        $outcomes[$label] = ($outcomes[$label] ?? 0) + 1;
    }
    $historicalRows = array_map(fn($g) => ['grade' => $g['final_grade'] ?? null, 'units' => $g['units']], $history);
    $historicalGwa = computeWeightedGWA($historicalRows);
    $prelimGwa = computeWeightedGWA($prelim);
    $inputs = $historicalGwa !== null && $prelimGwa !== null ? 'complete' :
        ($historicalGwa !== null || $prelimGwa !== null ? 'partial' : 'insufficient');
    $source = $prediction === null ? 'no_prediction' :
        (normalizePredictionSourceBoundary($prediction['prediction_source'] ?? null) ?? 'unknown');
    $meta = $prediction['data_completeness'] ?? null;
    $state = $prediction === null ? 'absent' :
        (!empty($prediction['is_provisional']) ? 'provisional' :
            ($meta === 'complete' && isset($prediction['is_provisional']) ? 'complete' : 'unknown'));

    $sourcePlainLabels = [
        'decision_tree' => 'Decision Tree model',
        'calculation_fallback' => 'Calculation-based estimate',
        'heuristic' => 'Earlier calculation method',
        'unknown' => 'Source not recognized',
        'no_prediction' => 'No saved prediction',
    ];
    $sourceLabel = $sourcePlainLabels[$source] ?? 'Source not recognized';

    $metaPlainLabels = [
        'complete' => 'Complete details',
        'historical_only' => 'Historical grades only',
        'prelim_only' => 'Current Preliminary scores only',
        'legacy_unknown' => 'Details unavailable',
        'unknown' => 'Details unavailable',
    ];
    $metaDisplay = $meta === null ? 'Details unavailable' : ($metaPlainLabels[$meta] ?? 'Details unavailable');

    $snapshot = 'Not available';
    if ($prediction !== null && isset($prediction['input_subject_count'], $prediction['expected_subject_count'])) {
        $expected = (int)$prediction['expected_subject_count']; $input = (int)$prediction['input_subject_count'];
        if ($expected > 0 && $input >= 0 && $input <= $expected) $snapshot = "$input of $expected subjects counted";
    }
    $basis = match ($prediction['provisional_basis'] ?? null) {
        PROVISIONAL_BASIS_HISTORICAL_GWA => 'Historical GWA only',
        PROVISIONAL_BASIS_CURRENT_PRELIM_AVG => 'Current Preliminary GWA only',
        null => 'Not recorded',
        default => 'Basis not recognized',
    };
    return array_merge($student, [
        'expected' => count($current), 'counts' => $counts, 'missing_counts' => $missing, 'inputs' => $inputs,
        'has_numeric' => computeWeightedGWA(array_merge($numeric, $historicalRows)) !== null,
        'source' => $source, 'source_label' => $sourceLabel, 'prediction_state' => $state,
        'prediction_metadata' => $metaDisplay, 'snapshot' => $snapshot,
        'basis' => $basis,
        'generated' => $prediction['generated_at'] ?? 'Not recorded', 'outcomes' => $outcomes,
    ]);
}

function gradeCoveragePercent(int $count, int $denominator): string {
    return $denominator > 0 ? number_format(100 * $count / $denominator, 1) . '%' : 'N/A';
}

// Only SELECT queries; no prediction/approval handlers are included or invoked.
function loadGradeCoverage(PDO $db, array $term): array {
    $metadata = getPredictionFullCompletenessSqlSelect($db, 'p');
    $students = $db->query("SELECT sp.user_id AS id, sp.student_number, sp.section,
        u.first_name, u.middle_name, u.last_name, p.id AS prediction_id,
        p.prediction_source, p.generated_at, $metadata
        FROM student_profiles sp JOIN users u ON u.id=sp.user_id AND u.role='student'
        LEFT JOIN predictions p ON p.id=(SELECT p2.id FROM predictions p2
            WHERE p2.student_id=sp.user_id ORDER BY p2.generated_at DESC,p2.id DESC LIMIT 1)
        WHERE sp.record_status='Active' ORDER BY sp.section,u.last_name,u.first_name,u.id")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->prepare("SELECT g.student_id, g.prelim,g.midterm,g.prefinal,g.final_grade,s.units,g.is_current
        FROM grades g JOIN subjects s ON s.id=g.subject_id
        JOIN student_profiles sp ON sp.user_id=g.student_id AND sp.record_status='Active'
        WHERE g.is_current=0 OR (g.is_current=1 AND g.school_year=? AND g.semester=?)");
    $stmt->execute([$term['school_year'], $term['semester']]);
    $current = $history = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $grade) {
        if ($grade['is_current']) $current[$grade['student_id']][] = $grade;
        else $history[$grade['student_id']][] = $grade;
    }
    $records = [];
    foreach ($students as $student) {
        $id = (int)$student['id'];
        $identity = ['id' => $id, 'name' => formatNameLastFirst($student['first_name'], $student['middle_name'], $student['last_name']),
            'student_number' => $student['student_number'], 'section' => $student['section'] ?? 'No section recorded'];
        $records[] = gradeCoverageRecord($identity, $current[$id] ?? [], $history[$id] ?? [],
            $student['prediction_id'] === null ? null : $student);
    }
    return $records;
}

// Shared renderer lets tests exercise the real page content without login or DB writes.
function renderGradeCoverage(array $records, array $term, bool $error = false): void {
    $escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sections = array_values(array_unique(array_column($records, 'section'))); sort($sections);
    $sources = [
        'decision_tree' => ['Decision Tree model', 'A system-generated prediction using the trained Decision Tree model.'],
        'calculation_fallback' => ['Calculation-based estimate', 'A calculation used when the Decision Tree model could not be used with the available information.'],
        'heuristic' => ['Earlier calculation method', 'A result saved using an earlier version of the system’s calculation process.'],
        'unknown' => ['Source not recognized', 'The saved source does not match the system’s current source labels.'],
        'no_prediction' => ['No saved prediction', 'No prediction has been saved for this student.'],
    ];
    $compactStates = [
        'complete' => 'Complete details',
        'provisional' => 'Preliminary',
        'unknown' => 'Details unavailable',
        'absent' => 'No saved prediction'
    ];
    $coverageBadgeLabel = function(array $r): string {
        $n = $r['expected'];
        $valid = $r['counts']['prelim'];
        if ($n === 0) return 'No current subjects';
        if ($valid === $n) return 'Complete';
        if ($valid > 0) return ($n - $valid) . ' missing';
        return 'No results recorded';
    };
    $inputLabels = [
        'complete' => 'Enough grade information',
        'partial' => 'Some grade information',
        'insufficient' => 'Not enough grade information',
    ];
    ?>
    <main class="main-content coverage-page" id="coverage-page">
        <header class="header coverage-heading">
            <div>
                <h1>Grade Record Review</h1>
                <p>Review available grade records, missing results, and saved prediction sources.</p>
            </div>
            <a href="analytics.php" class="btn btn--quiet coverage-target coverage-back">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>
                Back to Program Analytics
            </a>
        </header>
        <aside class="card coverage-notice" aria-labelledby="coverage-notice-title">
            <div class="coverage-notice-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            </div>
            <div class="coverage-notice-content">
                <h2 class="coverage-notice-title" id="coverage-notice-title">Prototype data</h2>
                <p class="coverage-notice-body">Prototype performance on synthetic academic records. Information shown is limited to records currently available in UDM-RADAR and does not confirm official encoding completion or Registrar finalization.</p>
                <p class="coverage-notice-scope" aria-label="Records included in this view: <?= $escape($term['school_year']) ?>, Semester <?= $escape($term['semester']) ?>, Active student profiles"><?= $escape($term['school_year']) ?> · Semester <?= $escape($term['semester']) ?> · Active student profiles</p>
            </div>
        </aside>
        <?php if ($error): ?>
        <div class="card roster-state roster-state--error coverage-error" role="alert">
            <h2 class="data-table-title">Coverage records unavailable</h2>
            <p class="roster-state-msg">Records could not be loaded. No academic data was changed.</p>
            <a class="btn btn--secondary coverage-target" href="coverage.php">Retry loading records</a>
        </div>
        <?php else: ?>
        <?php
        $reviewedCount = count($records);
        $completeCount = count(array_filter($records, fn($r) => $r['expected'] > 0 && $r['counts']['prelim'] === $r['expected']));
        $partialCount = count(array_filter($records, fn($r) => $r['counts']['prelim'] > 0 && $r['counts']['prelim'] < $r['expected']));
        $unavailableCount = count(array_filter($records, fn($r) => $r['counts']['prelim'] === 0));
        ?>
        <div class="coverage-kpi-context-strip">
            <h3 class="coverage-kpi-context-heading" id="coverage-kpi-context" aria-label="Current-term result summary for the selected Preliminary grading period.">
                <span class="coverage-kpi-context-text">Current-term results</span>
                <span class="coverage-kpi-context-sep" aria-hidden="true">·</span>
                <span class="coverage-kpi-period-label" id="coverage-kpi-period-label">Preliminary</span>
            </h3>
        </div>
        <section aria-label="Available grade records for selected period" class="stat-grid coverage-metrics">
            <div class="stat-card teal" id="coverage-card-reviewed" aria-label="<?= $reviewedCount ?> students reviewed">
                <div class="coverage-kpi-heading-row">
                    <h4 id="coverage-reviewed-title">Students reviewed</h4>
                </div>
                <h2 id="coverage-reviewed-count"><?= $reviewedCount ?></h2>
            </div>
            <div class="stat-card green" id="coverage-card-complete" aria-label="<?= $completeCount ?> students have results recorded for every current subject row in the selected Preliminary period.">
                <div class="coverage-kpi-heading-row">
                    <h4 id="coverage-complete-title">Complete</h4>
                </div>
                <h2 id="coverage-complete-count"><?= $completeCount ?></h2>
            </div>
            <div class="stat-card coverage-partial" id="coverage-card-partial" aria-label="<?= $partialCount ?> students have at least one recorded Preliminary result and at least one missing Preliminary result.">
                <div class="coverage-kpi-heading-row">
                    <h4 id="coverage-partial-title">Some missing</h4>
                </div>
                <h2 id="coverage-partial-count"><?= $partialCount ?></h2>
            </div>
            <div class="stat-card coverage-unavailable" id="coverage-card-needs-review" aria-label="<?= $unavailableCount ?> students have no Preliminary results recorded or no current subjects found.">
                <button type="button" class="table-header-help custom-tooltip tooltip-bottom coverage-kpi-help" id="help-tooltip-needs-review" aria-describedby="tooltip-text-needs-review" aria-label="Help: Needs review">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span class="tooltip-text" id="tooltip-text-needs-review" role="tooltip">Includes students with <span id="coverage-unavailable-subtext">no Preliminary results recorded or no current subjects found</span>.</span>
                </button>
                <div class="coverage-kpi-heading-row">
                    <h4 id="coverage-unavailable-title">Needs review</h4>
                </div>
                <h2 id="coverage-unavailable-count"><?= $unavailableCount ?></h2>
            </div>
        </section>
        <form id="coverage-filters" class="card coverage-filter-card" aria-label="Filter grade records">
            <div class="data-table-header coverage-filter-heading">
                <h2 class="data-table-title">Filter records</h2>
                <button type="reset" class="btn btn--quiet coverage-target">Clear filters</button>
            </div>
            <div class="data-table-toolbar coverage-filters">
                <label class="data-table-subtitle">Recorded period
                    <select class="form-input coverage-target" name="period" id="coverage-period">
                        <option value="prelim">Preliminary</option>
                        <option value="midterm">Midterm</option>
                        <option value="prefinal">Pre-Final</option>
                    </select>
                </label>
                <label class="data-table-subtitle">Section
                    <select class="form-input coverage-target" name="section" id="coverage-section">
                        <option value="">All sections</option>
                        <?php foreach($sections as $section): ?>
                        <option><?= $escape($section) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="data-table-subtitle">Records requiring review
                    <select class="form-input coverage-target" name="triage" id="coverage-triage">
                        <option value="">All records</option>
                        <option value="missing_prelim">Missing Preliminary result</option>
                        <option value="missing_midterm">Missing Midterm result</option>
                        <option value="missing_prefinal">Missing Pre-Final result</option>
                        <option value="no_numeric">No eligible numeric grades</option>
                        <option value="partial_inputs">Some grade information available</option>
                        <option value="insufficient">Not enough grade information</option>
                        <option value="provisional">Preliminary prediction</option>
                        <option value="no_records">No current subjects found</option>
                    </select>
                </label>
                <label class="data-table-subtitle">Prediction source
                    <select class="form-input coverage-target" name="source" id="coverage-source">
                        <option value="">All sources</option>
                        <?php foreach($sources as $key => [$label]): ?>
                        <option value="<?= $key ?>"><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="data-table-subtitle">Rows per page
                    <select class="form-input coverage-target" name="page_size" id="coverage-page-size">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
                <button type="submit" class="btn btn--primary coverage-target">Apply filters</button>
            </div>
        </form>
        <div id="coverage-loading" class="roster-state roster-state--loading" hidden><span class="roster-state-spinner" aria-hidden="true"></span><p class="roster-state-msg">Applying filters…</p></div>
        <div id="coverage-filter-error" class="card roster-state roster-state--error coverage-error" role="alert" hidden>Filters could not be applied. <button type="button" id="coverage-retry" class="btn btn--secondary coverage-target">Retry filters</button></div>
        <section id="coverage-results" class="data-table-card" aria-label="Coverage results" aria-busy="false">
            <div class="data-table-header coverage-results-heading">
                <div class="data-table-header__intro">
                    <h2 class="data-table-title">Student records</h2>
                    <p id="coverage-status" class="data-table-subtitle" role="status" aria-live="polite" aria-atomic="true" tabindex="-1"><?= count($records) > 0 ? ('Showing 1–' . min(25, count($records)) . ' of ' . count($records) . ' students') : 'No students match the selected filters.' ?></p>
                    <p id="coverage-period-summary" class="data-table-subtitle"></p>
                    <p id="coverage-active-filters" class="data-table-subtitle" hidden></p>
                </div>
            </div>
            <div class="data-table-scroll coverage-table-region" tabindex="0" role="region" aria-label="Grade Record Review table, scroll horizontally for more columns">
                <table class="data-table table-density--compact">
                    <caption class="sr-only">Available grade records and saved prediction sources</caption>
                    <thead>
                        <tr>
                            <th scope="col">Student</th>
                            <th scope="col">
                                <span class="table-header-title">Available results</span>
                                <button type="button" class="table-header-help custom-tooltip tooltip-bottom" id="help-tooltip-results" aria-describedby="tooltip-text-results" aria-label="Help: Available results">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span class="tooltip-text" id="tooltip-text-results" role="tooltip">Shows how many existing subjects have a recorded result for the selected grading period. This is separate from final grade records.</span>
                                </button>
                            </th>
                            <th scope="col">
                                <span class="table-header-title">Grade information</span>
                                <button type="button" class="table-header-help custom-tooltip tooltip-bottom" id="help-tooltip-info" aria-describedby="tooltip-text-info" aria-label="Help: Grade information">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span class="tooltip-text" id="tooltip-text-info" role="tooltip">Shows whether enough numeric grade information is available for the system’s current calculations. Missing information is not treated as zero or failure.</span>
                                </button>
                            </th>
                            <th scope="col">
                                <span class="table-header-title">Saved prediction</span>
                                <button type="button" class="table-header-help custom-tooltip tooltip-bottom" id="help-tooltip-prediction" aria-describedby="tooltip-text-prediction" aria-label="Help: Saved prediction">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span class="tooltip-text" id="tooltip-text-prediction" role="tooltip">Shows how the saved result was produced and whether detailed information about the original inputs was saved.</span>
                                </button>
                            </th>
                            <th scope="col" class="table-col-action">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($records as $record): $n = $record['expected']; $available = $record['counts']['prelim']; ?>
                    <tr data-coverage-record="<?= $escape(json_encode($record, JSON_THROW_ON_ERROR)) ?>" id="coverage-row-<?= $record['id'] ?>">
                        <th scope="row"><?= $escape($record['name']) ?><br><small><?= $escape($record['student_number']) ?> · <?= $escape($record['section']) ?></small></th>
                        <td data-period-coverage>
                            <?php if ($n === 0): ?>
                            <span class="coverage-count">No current subjects</span>
                            <?php elseif ($available === $n): ?>
                            <span class="coverage-count"><?= $available ?> of <?= $n ?> Preliminary</span>
                            <?php elseif ($available > 0): ?>
                            <span class="coverage-count"><?= $available ?> of <?= $n ?> Preliminary</span><br>
                            <small class="coverage-subtext"><?= ($n - $available) ?> missing</small>
                            <?php else: ?>
                            <span class="coverage-count">0 of <?= $n ?> Preliminary</span><br>
                            <small class="coverage-subtext">No results recorded</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="coverage-input-status"><?= $escape($inputLabels[$record['inputs']] ?? $record['inputs']) ?></span>
                        </td>
                        <td>
                            <span class="coverage-source-label"><?= $escape($record['source_label']) ?></span>
                            <?php if ($record['source'] !== 'no_prediction' && $record['prediction_state'] !== 'absent'): ?>
                            <br><small class="coverage-subtext"><?= $escape(match($record['prediction_state']) {
                                'provisional' => 'Preliminary result',
                                'complete' => 'Complete input details',
                                default => 'Input details unavailable',
                            }) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="table-col-action">
                            <button type="button" class="btn btn--secondary coverage-detail-toggle" id="coverage-toggle-<?= $record['id'] ?>" aria-expanded="false" aria-controls="coverage-detail-<?= $record['id'] ?>" aria-label="View details for <?= $escape($record['name']) ?>">View details</button>
                        </td>
                    </tr>
                    <tr id="coverage-detail-<?= $record['id'] ?>" class="coverage-detail-row" aria-labelledby="coverage-toggle-<?= $record['id'] ?>" hidden>
                        <td colspan="5" class="coverage-detail-cell">
                            <div class="coverage-detail-grid">
                                <div class="coverage-detail-section">
                                    <h3 class="coverage-detail-heading">Final Grade Records</h3>
                                    <?php
                                    $cleanOutcomes = array_filter($record['outcomes'], fn($key) => $key !== '—', ARRAY_FILTER_USE_KEY);
                                    $finalCount = array_sum($cleanOutcomes);
                                    $unrecordedFinals = $record['outcomes']['—'] ?? 0;
                                    ?>
                                    <?php if ($finalCount > 0): ?>
                                    <p class="coverage-outcome-summary">Final grades recorded for <?= $finalCount ?> of <?= $record['expected'] ?> subjects</p>
                                    <ul class="coverage-outcome-list">
                                        <?php foreach ($cleanOutcomes as $outcome => $count): ?>
                                        <li><span class="coverage-outcome-name"><?= $escape($outcome) ?></span>: <strong><?= $count ?></strong> <?= $count === 1 ? 'subject' : 'subjects' ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ($unrecordedFinals > 0): ?>
                                    <p class="coverage-outcome-pending"><?= $unrecordedFinals ?> <?= $unrecordedFinals === 1 ? 'subject has' : 'subjects have' ?> no final grade recorded yet</p>
                                    <?php endif; ?>
                                    <?php elseif ($record['expected'] > 0): ?>
                                    <p class="coverage-outcome-pending"><strong>No final grades recorded yet</strong><br>0 of <?= $record['expected'] ?> subjects have a final grade</p>
                                    <?php else: ?>
                                    <p class="coverage-outcome-pending">No current subjects found</p>
                                    <?php endif; ?>
                                </div>
                                <div class="coverage-detail-section">
                                    <h3 class="coverage-detail-heading">Saved Prediction Information</h3>
                                    <dl class="coverage-detail-meta">
                                        <dt>Prediction source</dt>
                                        <dd><?= $escape($record['source_label']) ?></dd>
                                        <dt>Saved data status</dt>
                                        <dd><?= $escape($record['prediction_metadata']) ?></dd>
                                        <dt>Why preliminary</dt>
                                        <dd><?= $escape($record['basis']) ?></dd>
                                        <dt>Subjects counted</dt>
                                        <dd><?= $escape($record['snapshot']) ?></dd>
                                        <dt>Generated on</dt>
                                        <dd><?= $record['generated'] === 'Not recorded' ? 'Not recorded' : $escape($record['generated']) ?></dd>
                                    </dl>
                                </div>
                                <div class="coverage-detail-section">
                                    <h3 class="coverage-detail-heading">About This Result</h3>
                                    <p class="coverage-detail-note"><?= $escape($sources[$record['source']][1] ?? 'Saved prediction information.') ?></p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="coverage-empty-row" id="coverage-empty" <?= $records ? 'hidden' : '' ?>>
                        <td colspan="5" class="coverage-empty-cell">
                            <div class="coverage-empty-content">
                                <h3 class="coverage-empty-title">No students match the selected filters.</h3>
                                <p class="coverage-empty-desc">Try changing the filters or clear them to view all students.</p>
                                <button type="button" class="btn btn--secondary coverage-target coverage-empty-clear" id="coverage-empty-clear">Clear filters</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Bottom result pagination" class="data-table-footer coverage-pagination coverage-pagination-bottom" data-pagination hidden>
                <button type="button" class="btn btn--secondary coverage-target" data-page-action="previous" disabled>Previous</button>
                <span data-page-label>Page 1 of 1</span>
                <button type="button" class="btn btn--secondary coverage-target" data-page-action="next" disabled>Next</button>
            </nav>
        </section>

        <section aria-labelledby="coverage-sources" class="coverage-sources-section">
            <div class="coverage-sources-header">
                <h2 class="data-table-title" id="coverage-sources">Saved Prediction Sources</h2>
                <p class="data-table-subtitle">Shows how each saved result was produced. Source counts and percentages include all <?= count($records) ?> active students before filters.</p>
            </div>
            <div class="coverage-sources-strip">
            <?php foreach ($sources as $key => [$label, $description]): $count = count(array_filter($records, fn($r) => $r['source'] === $key)); ?>
                <button type="button" class="btn btn--secondary coverage-source-item" aria-pressed="false" data-source-filter="<?= $key ?>" aria-controls="coverage-results" aria-description="<?= $escape($description) ?>">
                    <span class="coverage-source-header"><span class="badge na"><?= $escape($label) ?></span></span>
                    <div class="coverage-source-body">
                        <strong class="coverage-source-count"><?= $count ?> students</strong>
                        <span class="coverage-source-percent"><?= gradeCoveragePercent($count, count($records)) ?></span>
                    </div>
                    <span class="kpi-action-cue" data-source-cue><?= $count === 0 ? 'No matching students' : 'View students' ?></span>
                </button>
            <?php endforeach; ?>
            </div>
        </section>

        <div class="coverage-guide-cue">
            <button type="button" class="coverage-guide-link" id="coverage-guide-trigger" onclick="openGlossaryModal('coverage', this)">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                Learn about coverage and prediction sources
            </button>
        </div>

        <details class="card coverage-methodology"><summary class="data-table-title">How this view counts results</summary>
            <div class="coverage-methodology-content">
                <ul class="coverage-methodology-rules">
                    <li>Coverage uses the current subject rows found for the selected term.</li>
                    <li>A recorded numeric zero counts as an available result.</li>
                    <li>A blank result counts as missing.</li>
                    <li>Selected-period coverage is separate from final grade records.</li>
                    <li>Saved prediction information reflects what was stored when the result was generated.</li>
                    <li>Generation time does not prove that the result uses the latest grades.</li>
                </ul>
            </div>
        </details>
        <noscript><style>.coverage-page .coverage-detail-row { display: table-row !important; } .coverage-page .coverage-detail-toggle { display: none !important; }</style></noscript>
        <p id="coverage-enhancement-note">JavaScript is needed for interactive filters and pagination. All recorded results are shown; View details remains available.</p>
        <?php endif; ?>
    </main>
    <?php
}
