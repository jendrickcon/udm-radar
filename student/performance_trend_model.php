<?php
declare(strict_types=1);
// Student-only presentation model. No connection, session, or persistence.
require_once __DIR__ . '/../includes/analytics/calculations.php';
require_once __DIR__ . '/../includes/analytics/predictions.php';

function studentTrendPeriod(string $key): string {
    [$year, $semester] = array_pad(explode('|', $key), 2, '');
    return 'SY ' . $year . ' — ' . match ($semester) {
        '1' => 'First Semester', '2' => 'Second Semester', '3' => 'Summer', default => 'Period unavailable'
    };
}

function studentTrendAdjacent(string $before, string $after): bool {
    [$yearA, $semA] = explode('|', $before);
    [$yearB, $semB] = explode('|', $after);
    if (!preg_match('/^\d{4}-\d{4}$/', $yearA) || !preg_match('/^\d{4}-\d{4}$/', $yearB)) return false;
    return ($yearA === $yearB && (int)$semB === (int)$semA + 1)
        || ((int)$yearB === (int)$yearA + 1 && in_array($semA, ['2', '3'], true) && $semB === '1');
}

function studentTrendModel(array $rows, array $prediction): array {
    // Keep incomplete current terms as explicit nulls in the recorded-term series.
    $series = analyticsGroup($rows, 'semester');
    $points = []; $latest = null; $latestChange = 'Change unavailable — two comparable completed terms are required.';
    foreach ($series as $index => $term) {
        $value = !$term['provisional'] ? $term['gwa'] : null;
        $previous = analyticsPreviousComparable($series, $term);
        $comparable = $value !== null && $previous !== null && studentTrendAdjacent($previous['key'], $term['key']);
        // Do not silently skip a known incomplete period, even across Summer.
        if ($previous) foreach ($series as $between) {
            if (strnatcmp($between['key'], $previous['key']) > 0 && strnatcmp($between['key'], $term['key']) < 0
                && ($between['provisional'] || $between['gwa'] === null)) $comparable = false;
        }
        $change = analyticsChange($previous['gwa'] ?? null, $value, $comparable);
        $comparison = $change['point_change'] !== null
            ? sprintf('%+.2f points: %s to %s.', $change['point_change'], studentTrendPeriod($previous['key']), studentTrendPeriod($term['key']))
            : 'Change unavailable — incomplete, missing, nonadjacent, or noncomparable completed terms.';
        if ($change['relative_change_pct'] !== null) {
            $comparison .= sprintf(' Supporting relative change: %+.2f%% (relative to previous completed-term GWA).', $change['relative_change_pct']);
        }
        $points[] = ['period' => studentTrendPeriod($term['key']), 'key' => $term['key'], 'zone' => 'history',
            'type' => 'Recorded completed-term GWA', 'value' => $value,
            'state' => $value !== null ? 'Recorded' : ($term['provisional'] ? 'Pending' : 'Unavailable'),
            'detail' => $term['resolved_final_count'] . ' of ' . $term['expected_grade_count'] . ' recorded subject slots have resolved final outcomes; '
                . $term['eligible_units'] . ' eligible numeric credit units. Not cumulative GWA.',
            'comparison' => $comparison, 'connect' => $index > 0 && studentTrendAdjacent($series[$index - 1]['key'], $term['key'])];
        if ($value !== null) { $latest = $value; $latestChange = $comparison; }
    }
    $current = array_values(array_filter($rows, fn($row) => (int)$row['is_current'] === 1));
    $currentTerms = [];
    foreach ($current as $row) $currentTerms[$row['school_year'] . '|' . $row['semester']][] = $row;
    ksort($currentTerms, SORT_NATURAL);
    $snapshot = null;
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
    return ['points' => $points, 'latest' => $latest, 'snapshot' => $snapshot, 'estimate' => $estimate['estimated_gwa'], 'change' => $latestChange];
}
