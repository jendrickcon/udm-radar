<?php
// config/constants.php — Academic logic (replaces theme.py)

// App identity
define('APP_NAME',     'UDM-RADAR');
define('APP_SUBTITLE', 'Risk Analytics & Decision-support for Academic Records');

// Base URL — every include/sidebar link and asset path is prefixed with this,
// so pages work the same whether loaded from /login.php or /student/index.php.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/capstone/'); // change if your XAMPP folder name differs
}

// Latin honor thresholds
define('SUMMA_CUM_LAUDE', 3.75);
define('MAGNA_CUM_LAUDE', 3.50);
define('CUM_LAUDE',       3.25);
define('DEANS_LISTER',    3.25);

// Grade Computation Weights (Official Formula)
define('WEIGHT_PRELIM', 0.30);
define('WEIGHT_MIDTERM', 0.30);
define('WEIGHT_PREFINAL', 0.40);

// UdM Grade Scale array
function getGradeDescription(float $grade): array {
  $scale = [
    4.00 => ['99-100',       'Excellent'],
    3.75 => ['97-98',        'Outstanding'],
    3.50 => ['95-96',        'Outstanding'],
    3.25 => ['92-94',        'Outstanding'],
    3.00 => ['90-91',        'Very Satisfactory'],
    2.75 => ['88-89',        'Very Satisfactory'],
    2.50 => ['86-87',        'Very Satisfactory'],
    2.25 => ['84-85',        'Satisfactory'],
    2.00 => ['82-83',        'Satisfactory'],
    1.75 => ['80-81',        'Satisfactory'],
    1.50 => ['78-79',        'Fair'],
    1.25 => ['76-77',        'Fair'],
    1.00 => ['75',           'Passed'],
    0.00 => ['74 and below', 'Failed'],
  ];
  return $scale[$grade] ?? ['—', 'Unknown'];
}

// Converts a raw 0-100 percentage into the UdM 1.00 - 4.00 Point Grade
function convertPercentageToPoint(float $pct): float {
    if ($pct >= 99) return 4.00; // 99-100
    if ($pct >= 97) return 3.75; // 97-98
    if ($pct >= 95) return 3.50; // 95-96
    if ($pct >= 92) return 3.25; // 92-94
    if ($pct >= 90) return 3.00; // 90-91
    if ($pct >= 88) return 2.75; // 88-89
    if ($pct >= 86) return 2.50; // 86-87
    if ($pct >= 84) return 2.25; // 84-85
    if ($pct >= 82) return 2.00; // 82-83
    if ($pct >= 80) return 1.75; // 80-81
    if ($pct >= 78) return 1.50; // 78-79 
    if ($pct >= 76) return 1.25; // 76-77 
    if ($pct >= 75) return 1.00; // 75 
    return 0.00; // 74 and below (Failing)
}

// --- Grade normalization --------------------------------------------------
// Domain rule (confirmed): prelim/midterm/prefinal are ALWAYS raw 0-100
// percentages as faculty encode them — students never see these as point
// grades mid-term. Only `final_grade`, once computed/released, is ever on
// the 1.00-4.00 point scale. Because the column's identity already tells you
// which kind of value it holds, there's no need to guess from magnitude —
// that guess was the actual fragility, not the values themselves.
//
// Both functions distinguish NULL (not yet encoded/finalized) from 0
// (an officially encoded zero — a genuine Failed / no-submission result,
// per getGradeDescription()'s own "0.00 = Failed" entry). Only NULL means
// "nothing here yet"; 0 is a real, valid, alarm-worthy grade.

// For prelim / midterm / prefinal columns — always raw percentages.
function normalizeTermGrade($rawPercentage): ?float {
    if ($rawPercentage === null || $rawPercentage < 0) return null;
    return convertPercentageToPoint((float) $rawPercentage);
}

// For the final_grade column — already on the point scale once set.
function normalizePointGrade($grade): ?float {
    if ($grade === null || $grade < 0) return null;
    return round((float) $grade, 2);
}

// DEPRECATED — kept only so any older call site that hasn't been migrated
// yet still works. New code should call normalizeTermGrade() or
// normalizePointGrade() directly instead of guessing by magnitude.
function normalizeGrade($grade): ?float {
    if ($grade === null || $grade < 0) return null;
    if ($grade > 4.00) {
        return convertPercentageToPoint((float) $grade);
    }
    return (float) $grade;
}

function getLatinHonor(float $gwa): string {
  if ($gwa >= SUMMA_CUM_LAUDE) return 'Summa Cum Laude';
  if ($gwa >= MAGNA_CUM_LAUDE) return 'Magna Cum Laude';
  if ($gwa >= CUM_LAUDE)       return 'Cum Laude';
  return 'Not Eligible';
}

function getDeansLister(float $gwa): string {
  return $gwa >= DEANS_LISTER ? "Dean's Lister" : 'Not Qualified';
}

function getRiskColor(string $risk): string {
  return match(strtoupper($risk)) {
    'LOW'      => '#1B7A3E',
    'MODERATE' => '#C97A00',
    'HIGH'     => '#C62828',
    default    => '#1B7A3E',
  };
}



// Canonical GWA math — UdM uses CREDIT-UNIT-WEIGHTED averages, not simple
// averages. A 1-unit UID subject and a 3-unit ITE subject do NOT count
// equally toward GWA. Every page that aggregates grades across more than
// one subject must go through this instead of AVG().
// $rows: array of ['grade' => float, 'units' => int]
function computeWeightedGWA(array $rows): ?float {
    $totalPoints = 0.0;
    $totalUnits  = 0;
    foreach ($rows as $r) {
        if ($r['grade'] === null || $r['units'] === null) continue;
        $totalPoints += (float) $r['grade'] * (int) $r['units'];
        $totalUnits  += (int) $r['units'];
    }
    if ($totalUnits === 0) return null;
    return round($totalPoints / $totalUnits, 2);
}

// Canonical risk-level thresholds, 1.0(worst)-4.0(best) scale.
// Anchored to UdM's own official Point Equivalent / Description table, not
// arbitrary round numbers:
//   2.50-4.00 = "Very Satisfactory" and above  -> LOW risk
//   1.75-2.25 = "Satisfactory"                 -> MODERATE risk
//   1.00-1.50 = "Fair" / barely "Passed"        -> HIGH risk
// Single source of truth — encode_grades.php, student/dashboard.php, and the
// seed generator all use these exact cutoffs so a HIGH badge means the same
// thing everywhere.
function computeRiskFromAvg(float $avg): string {
    if ($avg < 1.75) return 'HIGH';
    if ($avg < 2.50) return 'MODERATE';
    return 'LOW';
}

// TEMPORARY heuristic placeholder for the real Decision Tree model (not yet
// built — see python_ml/). Blends current-term prelim performance with the
// student's historical weighted GWA 50/50, so one early exam can't swing the
// prediction wildly on its own — prelim is only ~30% of a real final grade.
// This is NOT machine learning; label it honestly wherever it's shown
// ("Heuristic Estimate — pending Decision Tree model"), never as ML output.
function predictFinalGradeHeuristic(?float $currentPrelim, ?float $historicalGWA): ?float {
    if ($currentPrelim === null && $historicalGWA === null) return null;
    if ($currentPrelim === null) return round($historicalGWA, 2);
    if ($historicalGWA === null) return round($currentPrelim, 2);
    $blend = (0.5 * $currentPrelim) + (0.5 * $historicalGWA);
    $blend = max(1.00, min(4.00, $blend));
    return round($blend * 4) / 4; // snap to the .25 grading increments
}

// Canonical name formatters — every faculty page that displays a split name
// goes through these, so "Lastname, Firstname" and "F. Lastname" look
// identical everywhere instead of each page rolling its own substr/explode.
function formatNameLastFirst(string $first, ?string $middle, string $last): string {
    $mi = $middle ? ' ' . strtoupper(substr(trim($middle), 0, 1)) . '.' : '';
    return trim($last) . ', ' . trim($first) . $mi;
}

function formatNameShort(string $first, string $last): string {
    $initial = $first !== '' ? strtoupper(substr($first, 0, 1)) . '.' : '';
    return trim($initial . ' ' . $last);
}

// Canonical "what semester is it right now" resolver — UdM's academic
// calendar: July-December = 1st semester, January-May = 2nd semester.
// June is treated as the tail end of 2nd sem (finals/graduation month).
// School year label follows the semester that's starting in August, e.g.
// August 2026 - May 2027 is school_year "2026-2027".
//
// This exists so "current semester" can be verified against the actual
// calendar instead of relying solely on a manually-set grades.is_current
// flag, which can drift out of sync (e.g. two semesters both left flagged
// current after a bulk import).
function getCurrentTerm(?DateTimeInterface $asOf = null): array {
    $now = $asOf ?? new DateTime();
    $month = (int) $now->format('n');
    $year  = (int) $now->format('Y');

    if ($month >= 7) {
        // Jul-Dec: 1st sem of the school year starting this August
        $semester = 1;
        $schoolYear = $year . '-' . ($year + 1);
    } else {
        // Jan-Jun: 2nd sem of the school year that started last August
        $semester = 2;
        $schoolYear = ($year - 1) . '-' . $year;
    }

    return ['school_year' => $schoolYear, 'semester' => $semester];
}
?>
