<?php
/** Read-only calculator presentation contract; never bootstraps a DB/session. */
require_once dirname(__DIR__) . '/config/constants.php';

function goalCalculatorConfig(): array {
    static $config;
    if ($config !== null) return $config;
    $minimums = [];
    // Derive the inverse from the canonical conversion, at supported input precision.
    for ($hundredths = 0; $hundredths <= 10000; $hundredths++) {
        $point = formatFinalGrade(convertPercentageToPoint($hundredths / 100));
        if (in_array($point, FINAL_GRADE_POINTS, true) && !isset($minimums[$point])) {
            $minimums[$point] = $hundredths / 100;
        }
    }
    $targets = [];
    foreach (FINAL_GRADE_POINTS as $point) {
        if (!isset($minimums[$point])) throw new LogicException('Calculator conversion is unavailable.');
        $targets[] = ['point' => (float) $point, 'minimumPercentage' => $minimums[$point]];
    }
    return $config = [
        'weights' => [WEIGHT_PRELIM, WEIGHT_MIDTERM, WEIGHT_PREFINAL],
        'maximumPercentage' => 100,
        'targets' => $targets,
        'gwaPoints' => array_values(array_map('floatval', array_filter(FINAL_GRADE_POINTS, fn($point) => !isExcludedFromGwa($point)))),
    ];
}

function goalCalculatorPeriod(mixed $raw): array {
    if ($raw === null || $raw === '') return ['value' => '', 'state' => 'remaining'];
    if (is_bool($raw) || !isValidTermPercentage($raw)) return ['value' => '', 'state' => 'unavailable'];
    return ['value' => (string) (float) $raw, 'state' => 'recorded'];
}
