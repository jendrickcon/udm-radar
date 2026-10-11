<?php
// Generated static HTML from the actual dashboard fragments; no DB/session bootstrap.
require_once dirname(__DIR__, 2) . '/config/constants.php';
function renderStudentGwaFixture(string $scenario): array {
    $rows = match ($scenario) {
        'all-inc' => [['grade' => 'INC', 'units' => 3]],
        'mixed' => [['grade' => 'INC', 'units' => 3], ['grade' => '1.00', 'units' => 3], ['grade' => '4.00', 'units' => 3]],
        'zero' => [['grade' => '0.00', 'units' => 3]],
        'no-records', 'numeric-zero-result' => [],
        default => throw new InvalidArgumentException('Unknown fixture'),
    };
    $current_gwa = $scenario === 'numeric-zero-result' ? 0.0 : computeWeightedGWA($rows);
    $display_risk = null;
    $source = file_get_contents(dirname(__DIR__, 2) . '/student/dashboard.php');
    $start = strpos($source, '$hasCurrentGwa =');
    $end = strpos($source, '// --- Fetch ML', $start);
    if ($start === false || $end === false) throw new RuntimeException('Dashboard state boundaries missing');
    eval(substr($source, $start, $end - $start));
    preg_match('/<h2 id="gwa-card-value".*?<\/h2>/s', $source, $card);
    $start = strpos($source, '    <?php $isAtRiskGauge =');
    $end = strpos($source, '        <!-- FIXED: Removed padding-bottom', $start);
    if (empty($card) || $start === false || $end === false) throw new RuntimeException('Dashboard markup boundaries missing');
    $markup = $card[0] . substr($source, $start, $end - $start) . '</div>';
    ob_start(); eval('?>' . $markup); $html = ob_get_clean();
    $start = strpos($source, 'const currentGwaForGauge =');
    $end = strpos($source, 'const ctxGauge =', $start);
    if ($start === false || $end === false) throw new RuntimeException('Gauge script boundaries missing');
    ob_start(); eval('?>' . substr($source, $start, $end - $start)); $script = ob_get_clean();
    return ['gwa' => $current_gwa, 'html' => $html, 'script' => $script];
}
if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    echo json_encode(renderStudentGwaFixture($argv[1] ?? 'all-inc'), JSON_THROW_ON_ERROR);
}
