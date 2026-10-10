<?php
require_once dirname(__DIR__) . '/fixtures/student_gwa_render.php';
$checks = $failures = 0;
function checkDashboard(bool $condition, string $label): void {
    global $checks, $failures;
    $checks++; if (!$condition) $failures++;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}
foreach (['all-inc' => 'N/A', 'no-records' => 'N/A', 'mixed' => '2.50', 'zero' => 'N/A', 'numeric-zero-result' => '0.00'] as $scenario => $expected) {
    $fixture = renderStudentGwaFixture($scenario);
    foreach (['card', 'gauge'] as $component) {
        preg_match('/id="gwa-' . $component . '-value"[^>]*>(.*?)<\//s', $fixture['html'], $match);
        checkDashboard(($match[1] ?? null) === $expected, "$scenario $component displays $expected");
    }
    $unavailable = $fixture['gwa'] === null;
    checkDashboard(str_contains($fixture['script'], 'const currentGwaForGauge = ' . ($unavailable ? 'null' : (string)$fixture['gwa']) . ';'), "$scenario JS preserves availability");
    checkDashboard(str_contains($fixture['html'], 'aria-label="Cumulative GWA: ' . $expected . '"'), "$scenario accessible gauge value");
    if ($unavailable) {
        checkDashboard(str_contains($fixture['html'], 'No numeric final grades are currently available.'), "$scenario unavailable description");
        checkDashboard(!str_contains($fixture['html'], 'High Risk'), "$scenario no risk inferred from missing GWA");
        checkDashboard(!str_contains($fixture['html'], '>0.00<'), "$scenario no zero fallback");
    }
}
checkDashboard(isFailingFinalGrade('0.00') && isExcludedFromGwa('0.00'), 'Actual zero remains failed and excluded under preserved GWA policy');
checkDashboard(str_contains(renderStudentGwaFixture('all-inc')['script'], 'if (currentGwaForGauge === null) return;'), 'Missing GWA does not draw a zero needle');
echo "SUMMARY: Ran $checks tests, $failures failures.\n";
exit($failures ? 1 : 0);
