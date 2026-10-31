<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/constants.php';

/** Faculty percentage evidence only. Never convert a displayed assessment mean to GWA. */
function facultyAssessmentStages(): array {
    return ['prelim' => 'Preliminary', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Final'];
}

function facultyAssessmentValue(mixed $value): ?float {
    return !is_bool($value) && isValidTermPercentage($value) ? (float)$value : null;
}

function facultyAssessmentRisk(array $row): array {
    $final = canonicalizeFinalGrade($row['final_grade'] ?? null);
    if ($final !== null) {
        if (isFailingFinalGrade($final)) return ['risk' => 'HIGH', 'stage' => 'Final'];
        return ['risk' => isNumericFinalGrade($final) ? computeRiskFromAvg((float)$final) : 'NONE', 'stage' => 'Final'];
    }
    foreach (array_reverse(facultyAssessmentStages(), true) as $field => $label) {
        $value = facultyAssessmentValue($row[$field] ?? null);
        if ($value !== null) return ['risk' => computeRiskFromAvg(normalizeTermGrade($value)), 'stage' => $label];
    }
    return ['risk' => 'NONE', 'stage' => 'Not recorded'];
}

function facultyAssessmentSummary(array $input): array {
    // One current Student/subject slot; repository scope is enforced before this pure model.
    $rows = [];
    foreach ($input as $row) {
        $key = (string)$row['student_id'] . '|' . (string)$row['subject_id'];
        if (isset($rows[$key])) throw new DomainException('Duplicate assigned assessment record');
        $rows[$key] = $row;
    }
    $expected = count($rows); $stages = []; $latest = null;
    foreach (facultyAssessmentStages() as $field => $label) {
        $values = []; $risk = ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0];
        foreach ($rows as $key => $row) {
            $value = facultyAssessmentValue($row[$field] ?? null);
            if ($value === null) { $risk['NONE']++; continue; }
            $values[$key] = $value;
            // Existing deterministic conversion is used only for advisory risk, not the chart/mean.
            $risk[computeRiskFromAvg(normalizeTermGrade($value))]++;
        }
        $count = count($values);
        $stages[$field] = ['label' => $label, 'count' => $count, 'expected' => $expected,
            'mean' => $count ? array_sum($values) / $count : null,
            'coverage' => $expected ? $count / $expected * 100 : null,
            'risk' => $count ? $risk : null, 'values' => $values];
        if ($count) $latest = $field;
    }
    $students = []; $attention = []; $finals = []; $finalRecorded = 0; $finalNumeric = 0;
    $finalRisk = ['HIGH' => 0, 'MODERATE' => 0, 'LOW' => 0, 'NONE' => 0];
    foreach ($rows as $row) {
        $students[(string)$row['student_id']] = true;
        $risk = facultyAssessmentRisk($row);
        if (in_array($risk['risk'], ['HIGH', 'MODERATE'], true)) $attention[(string)$row['student_id']] = true;
        $final = canonicalizeFinalGrade($row['final_grade'] ?? null);
        if ($final === null) { $finalRisk['NONE']++; continue; }
        $finalRecorded++;
        $finalRisk[isFailingFinalGrade($final) ? 'HIGH' : (isNumericFinalGrade($final) ? computeRiskFromAvg((float)$final) : 'NONE')]++;
        if (isNumericFinalGrade($final) && !isExcludedFromGwa($final) && (float)($row['units'] ?? 0) > 0) {
            $finalNumeric++; $finals[] = ['grade' => $final, 'units' => $row['units']];
        }
    }
    $change = ['point_change' => null, 'relative_change' => null, 'from' => null, 'to' => null,
        'sample' => 0, 'expected' => $expected, 'provisional' => false,
        'reason' => 'Preliminary and Midterm values are not both recorded.'];
    $fields = array_keys(facultyAssessmentStages());
    if ($latest !== null && ($index = array_search($latest, $fields, true)) > 0) {
        $from = $fields[$index - 1]; $to = $fields[$index];
        $paired = array_intersect_key($stages[$from]['values'], $stages[$to]['values']);
        $n = count($paired);
        $change['from'] = $stages[$from]['label']; $change['to'] = $stages[$to]['label'];
        $change['sample'] = $n;
        $change['reason'] = $change['from'] . ' and ' . $change['to'] . ' have no matched recorded values.';
        if ($n) {
            $a = array_sum($paired) / $n;
            $b = array_sum(array_intersect_key($stages[$to]['values'], $paired)) / $n;
            $change += ['previous_mean' => $a, 'current_mean' => $b];
            $change['point_change'] = $b - $a;
            $change['relative_change'] = $a > 0 ? ($b - $a) / $a * 100 : null;
            $change['provisional'] = $n < $expected; $change['reason'] = null;
        }
    }
    $notable = [];
    foreach ($rows as $row) {
        $a = facultyAssessmentValue($row['prelim'] ?? null); $b = facultyAssessmentValue($row['midterm'] ?? null);
        if ($a === null || $b === null) continue;
        // Stored percentages have two decimals; remove binary-float noise at the inclusive 5 pp boundary.
        $difference = round($b - $a, 2);
        if (abs($difference) < 5) continue;
        $risk = facultyAssessmentRisk($row);
        $notable[] = ['student_id' => $row['student_id'], 'name' => $row['name'] ?? '',
            'prelim' => $a, 'midterm' => $b, 'change' => $difference,
            'current_risk' => $risk['risk'], 'risk_stage' => $risk['stage']];
    }
    usort($notable, static fn($a, $b) => ($a['change'] <=> $b['change']) ?: ($a['student_id'] <=> $b['student_id']));
    foreach ($stages as &$stage) unset($stage['values']); unset($stage);
    return ['expected' => $expected, 'students' => count($students), 'attention' => count($attention),
        'stages' => $stages, 'latest' => $latest, 'assessment' => $latest === null ? null : $stages[$latest],
        'final' => ['count' => $finalNumeric, 'recorded' => $finalRecorded, 'expected' => $expected,
            'coverage' => $expected ? $finalNumeric / $expected * 100 : null, 'mean' => computeWeightedGWA($finals),
            'risk' => $finalRecorded ? $finalRisk : null], 'change' => $change, 'notable' => $notable];
}

function facultyAssessmentPriorStatus(): array {
    // No dated assignment/membership source exists in the accepted schema. Never infer from current loads.
    return ['status' => 'No dated prior assignment', 'available' => false,
        'reason' => 'No verified dated assignment is available for a comparable previous period.'];
}

/** Presentation availability only: never pool different latest class stages or average class means. */
function facultyAssessmentOverview(array $loads, array $rows): array {
    $summary = facultyAssessmentSummary($rows);
    $classes = []; $latestStages = [];
    foreach ($loads as $load) {
        $classRows = array_values(array_filter($rows, static fn($row) =>
            (int)$row['subject_id'] === (int)$load['subject_id'] && $row['section'] === $load['section']));
        $classSummary = facultyAssessmentSummary($classRows);
        $classes[] = ['load' => $load, 'summary' => $classSummary];
        $latestStages[] = $classSummary['latest'];
    }
    $recordedStages = array_unique(array_filter($latestStages, static fn($stage) => $stage !== null));
    $mixed = count($recordedStages) > 1;
    $shared = count($loads) > 0 && count($recordedStages) === 1 && !in_array(null, $latestStages, true);
    // Reuse the existing equal-weight Student-subject stage aggregate, including missing slots in coverage.
    $assessment = $shared ? $summary['stages'][reset($recordedStages)] : null;
    return ['summary' => $summary, 'classes' => $classes, 'assessment' => $assessment,
        'mixed' => $mixed, 'status' => $mixed ? 'Mixed latest stages' : 'Unavailable',
        'detail' => $assessment ? $assessment['label'].' · '.$assessment['count'].' of '.$assessment['expected'].' recorded'
            : (count($recordedStages) ? 'Review class-level results below.' : 'No recorded assessment stage')];
}
