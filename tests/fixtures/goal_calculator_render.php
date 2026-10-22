<?php
// CLI-only real calculator fragment, identifier-free examples; no DB/auth bootstrap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/includes/goal_calculator.php';
$calculatorConfig = goalCalculatorConfig();
if (($argv[1] ?? '') === 'config') { echo json_encode($calculatorConfig, JSON_THROW_ON_ERROR); exit; }
$case = $argv[1] ?? 'initial';
$periods = match ($case) {
    'initial' => [null,null,null], 'limited' => [50,null,null],
    'boundary' => [90,null,null], 'completed' => [90,90,90],
    'unresolved' => ['INC',null,null], 'no-goals' => [0,0,null],
    'decimal' => [89.99,null,null], default => throw new InvalidArgumentException('Unknown case'),
};
$current_subjects = [['code'=>'EXAMPLE','title'=>'Example subject','units'=>3,'prelim_raw'=>$periods[0],'midterm_raw'=>$periods[1],'prefinal_raw'=>$periods[2]]];
$valid_grades = FINAL_GRADE_POINTS;
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/student/dashboard.php');
$start = strpos($source, 'function renderTargetOptions(');
$end = strpos($source, '$pageTitle =', $start);
eval(substr($source, $start, $end - $start));
$start = strpos($source, '    <div id="calculator-section"');
$end = strpos($source, '\n</div>\n\n<script src=', $start);
if ($end === false) $end = strpos($source, "\n</div>\n\n<script", $start);
if ($start === false || $end === false) throw new RuntimeException('Calculator boundaries missing');
ob_start(); eval('?>' . substr($source, $start, $end - $start)); $html = ob_get_clean();
$html=preg_replace('~<link[^>]+goal-calculator\.css[^>]*>~','',$html);
echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . file_get_contents($root.'/assets/css/dashboard.css');
if (file_exists($root.'/assets/css/goal-calculator.css')) echo file_get_contents($root.'/assets/css/goal-calculator.css');
echo '</style></head><body><main class="main-content"><button onclick="toggleCalculator()">Open Grade Goal Calculator</button>' . $html . '</main>';
echo '<script id="goal-calculator-config" type="application/json">' . json_encode($calculatorConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
if (file_exists($root.'/assets/js/goal-calculator-ui.js')) {
    echo '<script>' . file_get_contents($root.'/assets/js/goal-calculator.js') . '</script><script>' . file_get_contents($root.'/assets/js/goal-calculator-ui.js') . '</script>';
} else {
    $start = strpos($source, 'const WEIGHT_PRELIM ='); $end = strpos($source, 'function getThemeColors()', $start);
    ob_start(); eval('?>'.substr($source,$start,$end-$start)); $script=ob_get_clean();
    echo '<script>'.$script.'</script>';
}
echo '</body></html>';
