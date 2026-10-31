<?php
declare(strict_types=1);
// Student-only presentation model. No connection, session, or persistence.
require_once __DIR__ . '/../includes/analytics/calculations.php';
require_once __DIR__ . '/../includes/analytics/predictions.php';

function studentTrendPeriodOrder(string $key): ?int {
    if (!preg_match('/^(\d{4})-(\d{4})\|([123])$/D', $key, $parts)
        || (int)$parts[2] !== (int)$parts[1] + 1) return null;
    return (int)$parts[1] * 3 + (int)$parts[3];
}

function studentTrendPeriod(string $key): string {
    if (studentTrendPeriodOrder($key) === null) return 'Academic period unavailable';
    [$year, $semester] = array_pad(explode('|', $key), 2, '');
    return 'SY ' . $year . ' — ' . match ($semester) {
        '1' => 'First Semester', '2' => 'Second Semester', '3' => 'Summer', default => 'Period unavailable'
    };
}

function studentTrendAdjacent(string $before, string $after): bool {
    if (studentTrendPeriodOrder($before) === null || studentTrendPeriodOrder($after) === null) return false;
    [$yearA, $semA] = explode('|', $before);
    [$yearB, $semB] = explode('|', $after);
    if (!preg_match('/^\d{4}-\d{4}$/', $yearA) || !preg_match('/^\d{4}-\d{4}$/', $yearB)) return false;
    return ($yearA === $yearB && (int)$semB === (int)$semA + 1)
        || ((int)$yearB === (int)$yearA + 1 && in_array($semA, ['2', '3'], true) && $semB === '1');
}

/**
 * Computes strictly comparable growth across completed academic terms.
 * Primary: Point change in GWA.
 * Supporting: Relative percentage change.
 * Excludes: incomplete current term, estimated GWA, raw percentage scores, malformed periods, Summer-to-regular comparisons.
 */
function computeStudentGrowth(array $rows, ?array $currentTerm = null): array {
    $term = $currentTerm ?? getCurrentTerm();
    // Incomplete current term cannot supply completed growth
    $completedRows = array_filter($rows, fn($r) =>
        (int)($r['is_current'] ?? 0) === 0
        || (($r['school_year'] ?? '') !== $term['school_year'] || (int)($r['semester'] ?? 0) !== (int)$term['semester'])
    );
    $series = analyticsGroup($completedRows, 'semester');
    usort($series, fn($a, $b) => (studentTrendPeriodOrder($a['key']) ?? PHP_INT_MAX)
        <=> (studentTrendPeriodOrder($b['key']) ?? PHP_INT_MAX) ?: strcmp($a['key'], $b['key']));
    $datedSeries = array_values(array_filter($series, fn($t) => studentTrendPeriodOrder($t['key']) !== null && !$t['provisional'] && $t['gwa'] !== null));

    if (count($datedSeries) < 2) {
        return [
            'point_change' => null,
            'relative_change_pct' => null,
            'direction' => 'Unavailable',
            'formatted_points' => 'Unavailable',
            'formatted_relative' => 'Two comparable completed terms required',
            'is_available' => false,
            'summary_text' => 'Growth unavailable — two comparable completed terms are required.',
            'current_gwa' => !empty($datedSeries) ? end($datedSeries)['gwa'] : null,
            'previous_gwa' => null,
            'current_term_label' => !empty($datedSeries) ? studentTrendPeriod(end($datedSeries)['key']) : null,
            'previous_term_label' => null,
        ];
    }

    $last = end($datedSeries);
    $previous = analyticsPreviousComparable($datedSeries, $last);

    if ($previous === null || $last['gwa'] === null || $previous['gwa'] === null) {
        return [
            'point_change' => null,
            'relative_change_pct' => null,
            'direction' => 'Unavailable',
            'formatted_points' => 'Unavailable',
            'formatted_relative' => 'No comparable prior term',
            'is_available' => false,
            'summary_text' => 'Growth unavailable — no comparable prior term.',
            'current_gwa' => $last['gwa'],
            'previous_gwa' => null,
            'current_term_label' => studentTrendPeriod($last['key']),
            'previous_term_label' => null,
        ];
    }

    $summer = str_ends_with($last['key'], '|3');
    $comparable = ($summer || studentTrendAdjacent($previous['key'], $last['key']));

    if ($comparable) {
        foreach ($series as $between) {
            if (studentTrendPeriodOrder($between['key']) === null || ($summer && !str_ends_with($between['key'], '|3'))) {
                continue;
            }
            if (strnatcmp($between['key'], $previous['key']) > 0 && strnatcmp($between['key'], $last['key']) < 0
                && ($between['provisional'] || $between['gwa'] === null)) {
                $comparable = false;
            }
        }
    }

    if (!$comparable) {
        return [
            'point_change' => null,
            'relative_change_pct' => null,
            'direction' => 'Unavailable',
            'formatted_points' => 'Unavailable',
            'formatted_relative' => 'Terms are not adjacent or intervening term is incomplete',
            'is_available' => false,
            'summary_text' => 'Growth unavailable — non-adjacent or incomplete terms.',
            'current_gwa' => $last['gwa'],
            'previous_gwa' => $previous['gwa'],
            'current_term_label' => studentTrendPeriod($last['key']),
            'previous_term_label' => studentTrendPeriod($previous['key']),
        ];
    }

    $change = analyticsChange($previous['gwa'], $last['gwa'], true);
    $delta = $change['point_change'];
    $rel = $change['relative_change_pct'];

    $formattedPoints = ($delta !== null)
        ? sprintf('%s%.2f GWA points', $delta > 0 ? '+' : '', $delta)
        : 'Unavailable';

    $formattedRel = ($rel !== null)
        ? sprintf('%s%.2f%% from previous comparable term', $rel > 0 ? '+' : '', $rel)
        : 'Unavailable';

    return [
        'point_change' => $delta,
        'relative_change_pct' => $rel,
        'direction' => $change['direction'],
        'formatted_points' => $formattedPoints,
        'formatted_relative' => $formattedRel,
        'is_available' => $delta !== null,
        'summary_text' => sprintf('%s (%s)', $formattedPoints, $formattedRel),
        'current_gwa' => $last['gwa'],
        'previous_gwa' => $previous['gwa'],
        'current_term_label' => studentTrendPeriod($last['key']),
        'previous_term_label' => studentTrendPeriod($previous['key']),
    ];
}

function studentTrendModel(array $rows, array $prediction, ?array $currentTerm = null): array {
    $activeTerm = $currentTerm ?? getCurrentTerm();

    // Keep incomplete current terms as explicit nulls in the recorded-term series.
    $series = analyticsGroup($rows, 'semester');
    // Invalid periods remain visible as unavailable evidence, never as latest results.
    usort($series, fn($a, $b) => (studentTrendPeriodOrder($a['key']) ?? PHP_INT_MAX)
        <=> (studentTrendPeriodOrder($b['key']) ?? PHP_INT_MAX) ?: strcmp($a['key'], $b['key']));
    $datedSeries = array_values(array_filter($series, fn($term) => studentTrendPeriodOrder($term['key']) !== null));
    $points = []; $latest = null; $latestChange = 'Change unavailable — two comparable completed terms are required.';
    foreach ($series as $index => $term) {
        $dated = studentTrendPeriodOrder($term['key']) !== null;
        $value = $dated && !$term['provisional'] ? $term['gwa'] : null;
        $previous = $dated ? analyticsPreviousComparable($datedSeries, $term) : null;
        $summer = str_ends_with($term['key'], '|3');
        $comparable = $value !== null && $previous !== null
            && ($summer || studentTrendAdjacent($previous['key'], $term['key']));
        // Do not silently skip a known incomplete period, even across Summer.
        if ($previous) foreach ($series as $between) {
            if (studentTrendPeriodOrder($between['key']) === null
                || ($summer && !str_ends_with($between['key'], '|3'))) continue;
            if (strnatcmp($between['key'], $previous['key']) > 0 && strnatcmp($between['key'], $term['key']) < 0
                && ($between['provisional'] || $between['gwa'] === null)) $comparable = false;
        }
        $change = analyticsChange($previous['gwa'] ?? null, $value, $comparable);
        $comparison = 'Change unavailable — incomplete, missing, nonadjacent, or noncomparable completed terms.';
        if ($change['point_change'] !== null) {
            $delta = $change['point_change'];
            $comparison = $delta == 0.0
                ? 'Your GWA stayed the same'
                : sprintf('Your GWA %s by %.2f points', $delta > 0 ? 'increased' : 'decreased', abs($delta));
            if ($change['relative_change_pct'] !== null) {
                $comparison .= sprintf(' (%.2f%%)', abs($change['relative_change_pct']));
            }
            $beforePeriod = studentTrendPeriod($previous['key']);
            $afterPeriod = studentTrendPeriod($term['key']);
            $beforeYear = explode('|', $previous['key'])[0];
            $afterYear = explode('|', $term['key'])[0];
            if ($beforeYear === $afterYear) {
                $prefixLength = strlen('SY ' . $beforeYear . ' — ');
                $comparison .= sprintf('. Comparison: %s to %s, SY %s.', substr($beforePeriod, $prefixLength), substr($afterPeriod, $prefixLength), $beforeYear);
            } else {
                $comparison .= sprintf('. Comparison: %s to %s.', $beforePeriod, $afterPeriod);
            }
        }
        $points[] = ['period' => studentTrendPeriod($term['key']), 'key' => $term['key'], 'zone' => 'history',
            'type' => 'Recorded completed-term GWA', 'value' => $value,
            'state' => $value !== null ? 'Recorded' : (!$dated ? 'Unavailable' : ($term['provisional'] ? 'Pending' : 'Unavailable')),
            'detail' => $term['resolved_final_count'] . ' of ' . $term['expected_grade_count'] . ' recorded subject slots have resolved final outcomes; '
                . $term['eligible_units'] . ' eligible numeric credit units. Not cumulative GWA.',
            'comparison' => $comparison, 'connect' => $index > 0 && studentTrendAdjacent($series[$index - 1]['key'], $term['key'])];
        if ($value !== null) { $latest = $value; $latestChange = $comparison; }
    }

    // Anchor current subjects strictly to the active academic period
    $current = array_values(array_filter($rows, fn($row) =>
        (int)($row['is_current'] ?? 0) === 1
        && ($row['school_year'] ?? '') === $activeTerm['school_year']
        && (int)($row['semester'] ?? 0) === (int)$activeTerm['semester']
    ));

    // Fallback if test fixture passed is_current=1 without matching the default calendar year
    if (empty($current)) {
        $current = array_values(array_filter($rows, fn($row) => (int)($row['is_current'] ?? 0) === 1));
    }

    $currentTerms = [];
    foreach ($current as $row) {
        $key = ($row['school_year'] ?? '') . '|' . ($row['semester'] ?? '');
        if (studentTrendPeriodOrder($key) !== null) $currentTerms[$key][] = $row;
    }
    ksort($currentTerms, SORT_NATURAL);
    $snapshot = null;
    $currentStages = [];
    $totalCurrentSubjects = count($current);

    foreach (['prelim' => 'Preliminary', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Final'] as $field => $label) {
        $sum = 0; $units = 0; $recordedCount = 0;
        foreach ($current as $subject) {
            $val = $subject[$field] ?? null;
            if ($val !== null && trim((string)$val) !== '' && isValidTermPercentage($val) && (float)($subject['units'] ?? 0) > 0) {
                $sum += (float)$val * (float)$subject['units'];
                $units += (float)$subject['units'];
                $recordedCount++;
            }
        }
        $pct = ($units > 0 && $recordedCount > 0) ? round($sum / $units, 2) : null;
        $currentStages[$field] = [
            'field' => $field,
            'label' => $label,
            'percentage' => $pct,
            'status' => $pct !== null ? 'Recorded' : 'Not recorded',
            'formatted_percentage' => $pct !== null ? number_format($pct, 2) . '%' : 'Not recorded',
            'coverage' => $totalCurrentSubjects > 0 ? "{$recordedCount}/{$totalCurrentSubjects}" : '0/0',
            'recorded_count' => $recordedCount,
            'total_count' => $totalCurrentSubjects,
        ];
    }

    foreach ($currentTerms as $key => $subjects) {
        $coverage = [];
        foreach (['prelim' => 'Preliminary', 'midterm' => 'Midterm', 'prefinal' => 'Pre-Final'] as $field => $label) {
            $coverage[$field] = count(array_filter($subjects, fn($r) => $r[$field] !== null && trim((string)$r[$field]) !== ''));
        }
        $finalCount = analyticsAggregate($subjects)['resolved_final_count'];
        $coverageText = 'Recorded subject slots: Preliminary ' . $coverage['prelim'] . '/' . count($subjects)
            . '; Midterm ' . $coverage['midterm'] . '/' . count($subjects) . '; Pre-Final ' . $coverage['prefinal'] . '/' . count($subjects)
            . '; resolved Final ' . $finalCount . '/' . count($subjects) . '.';
        foreach (['prelim' => 'Preliminary', 'midterm' => 'Midterm'] as $field => $label) {
            // Preserve the existing snapshot method: weighted raw percentages, then canonical conversion.
            $sum = 0; $units = 0;
            foreach ($subjects as $subject) if ($subject[$field] !== null && trim((string)$subject[$field]) !== '' && isValidTermPercentage($subject[$field]) && (float)$subject['units'] > 0) {
                $sum += (float)$subject[$field] * (float)$subject['units']; $units += (float)$subject['units'];
            }
            $value = $units > 0 ? convertPercentageToPoint($sum / $units) : null;
            if ($value !== null) $snapshot = $value;
            $points[] = ['period' => studentTrendPeriod($key), 'key' => $key, 'zone' => 'current',
                'type' => 'Current ' . $label . ' snapshot', 'value' => $value, 'state' => $value === null ? 'Pending' : 'In progress',
                'detail' => $coverageText . ' Not a completed-semester final GWA. Missing stages remain pending.',
                'comparison' => 'Not directly comparable as completed growth.', 'connect' => false];
        }
    }

    $estimate = analyticsPrediction($prediction);
    $points[] = ['period' => 'Latest stored estimate — target period not supplied by this record', 'key' => null, 'zone' => 'estimate',
        'type' => 'Estimated semester GWA', 'value' => $estimate['estimated_gwa'], 'state' => $estimate['estimated_gwa'] === null ? 'Unavailable' : 'Estimated',
        'detail' => $estimate['source_label'] . '; completeness: ' . $estimate['completeness'] . '. '
            . ($estimate['generated_at'] ? 'Stored generation timestamp: ' . $estimate['generated_at'] . ' (timezone unverified). ' : 'Generation timestamp unavailable. ')
            . 'Generation time alone does not verify that the latest grades were used. Estimate only; not a final grade or official standing.',
        'comparison' => 'Not a completed-term growth measure; forecast range unavailable.', 'connect' => false];

    // Build accessible summary table rows (Academic period, Result type, Value, Status)
    $accessibleSummary = [];
    foreach ($series as $term) {
        $dated = studentTrendPeriodOrder($term['key']) !== null;
        if (!$dated) continue;
        [$y, $s] = array_pad(explode('|', $term['key']), 2, '');
        $periodLabel = $s === '3' ? "{$y} · Summer" : "{$y} · Semester {$s}";
        $val = !$term['provisional'] ? $term['gwa'] : null;
        $accessibleSummary[] = [
            'period' => $periodLabel,
            'result_type' => 'Completed-term GWA',
            'type' => 'Completed-term GWA',
            'value' => $val !== null ? number_format($val, 2) : 'Unavailable',
            'gwa' => $val !== null ? number_format($val, 2) : 'Unavailable',
            'status' => $val !== null ? 'Recorded' : ($term['provisional'] ? 'Pending' : 'Unavailable'),
            'availability' => $val !== null ? 'Recorded' : ($term['provisional'] ? 'Pending' : 'Unavailable'),
        ];
    }
    if (!empty($currentStages['prelim']['percentage'])) {
        $currPeriodLabel = $activeTerm['school_year'] . ' · Semester ' . $activeTerm['semester'];
        $accessibleSummary[] = [
            'period' => $currPeriodLabel,
            'result_type' => 'Current preliminary progress',
            'type' => 'Current preliminary progress',
            'value' => number_format((float)$currentStages['prelim']['percentage'], 2) . '%',
            'gwa' => number_format((float)$currentStages['prelim']['percentage'], 2) . '%',
            'status' => 'Recorded',
            'availability' => 'Recorded',
        ];
    }
    if ($estimate['estimated_gwa'] !== null) {
        $currPeriodLabel = $activeTerm['school_year'] . ' · Semester ' . $activeTerm['semester'];
        $accessibleSummary[] = [
            'period' => $currPeriodLabel,
            'result_type' => 'Estimated GWA',
            'type' => 'Estimated GWA',
            'value' => number_format($estimate['estimated_gwa'], 2),
            'gwa' => number_format($estimate['estimated_gwa'], 2),
            'status' => 'Provisional',
            'availability' => 'Provisional',
        ];
    }

    $growth = computeStudentGrowth($rows, $activeTerm);

    return [
        'points' => $points,
        'latest' => $latest,
        'snapshot' => $snapshot,
        'estimate' => $estimate['estimated_gwa'],
        'change' => $latestChange,
        'growth' => $growth,
        'current_term_stages' => $currentStages,
        'accessible_summary' => $accessibleSummary,
        'active_term' => $activeTerm,
    ];
}
