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

// Shared disqualifying-grade check for honors eligibility — a student with
// any past failed subject or any final_grade below 1.75 is disqualified
// regardless of GWA. Previously this was only computed inline in
// api/predict.php, so any other caller of getLatinHonor() silently defaulted
// to "no disqualifying grade" and could show honors eligibility a student
// doesn't actually qualify for. Both predict.php and student/dashboard.php's
// local fallback should call this instead of recomputing or omitting it.
function hasDisqualifyingGrade(int $studentId, PDO $db): bool {
    $stmt = $db->prepare("
        SELECT final_grade FROM grades
        WHERE student_id = ? AND is_current = 0 AND final_grade IS NOT NULL
    ");
    $stmt->execute([$studentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $grade) {
        $gStr = strtoupper(trim((string) $grade));
        if (in_array($gStr, ['0', '0.00', 'INC', 'DO', 'DU', 'FA', 'UD'])) {
            return true;
        }
        if (is_numeric($gStr) && (float) $gStr > 0 && (float) $gStr < 1.75) {
            return true;
        }
    }
    return false;
}

function getLatinHonor(float $gwa, bool $hasDisqualifyingGrade = false): string {
    // Immediate academic disqualification (e.g., any grade < 1.75 or 80%)
    if ($hasDisqualifyingGrade) {
        return 'Not Eligible';
    }

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
// $rows: array of ['grade' => float|string, 'units' => int]
//
// $r['grade'] may be a special academic status (INC, DO, DU, FA, UD) instead
// of a numeric point grade — these are not zero and must not be averaged in
// as zero. Casting them with (float) silently turns them into 0.00, which
// then drags down GWA and can push computeRiskFromAvg() into HIGH for a
// subject that isn't actually graded yet. Skip them entirely, the same way
// a null grade is already skipped, rather than let them corrupt the average.
function computeWeightedGWA(array $rows): ?float {
    $specialStatuses = ['INC', 'DO', 'DU', 'FA', 'UD'];
    $totalPoints = 0.0;
    $totalUnits  = 0;
    foreach ($rows as $r) {
        if ($r['grade'] === null || $r['units'] === null) continue;
        $gradeStr = strtoupper(trim((string) $r['grade']));
        if (in_array($gradeStr, $specialStatuses, true)) continue;
        if (!is_numeric($gradeStr)) continue; // defensive: skip anything else non-numeric too
        $totalPoints += (float) $gradeStr * (int) $r['units'];
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

// --- Grade Validation & Formatting ----------------------------------------
// isValidGrade() validates POINT-SCALE values (1.00-4.00, stepped by 0.25,
// or a special status). This is correct for a field that genuinely holds a
// point grade (final_grade). It must NOT be used for prelim/midterm/prefinal
// — those are raw 0-100 percentages, and isValidGrade() would reject every
// realistic percentage a faculty member could actually enter (e.g. 87 fails
// the 1.00-4.00 range check outright). Use isValidTermPercentage() for those.
function isValidGrade($val) {
    if ($val === '') return false;
    $valStr = strtoupper(trim((string)$val));
    if (in_array($valStr, ['P', 'PASSED', 'INC', 'DRP'])) return true;
    
    if (is_numeric($val)) {
        $f = (float)$val;
        if ($f >= 1.00 && $f <= 4.00) {
            $step = (int)round($f * 100);
            if ($step % 25 === 0) return true;
        }
    }
    return false;
}

// Validates a raw 0-100 term percentage for Prelim/Midterm/Pre-Final entry,
// or a recognized special academic status. Whole-number percentages are
// expected (grades.php rounds/stores them as such), but this accepts one
// decimal place too since faculty may reasonably type e.g. 87.5.
function isValidTermPercentage($val): bool {
    if ($val === '' || $val === null) return false;
    $valStr = strtoupper(trim((string)$val));
    if (in_array($valStr, ['INC', 'DO', 'DU', 'DRP', 'P', 'PASSED'], true)) return true;

    if (is_numeric($valStr)) {
        $f = (float)$valStr;
        return $f >= 0 && $f <= 100;
    }
    return false;
}

// Clean prefixes like "SD353 Software Development" -> "Software Development"
function cleanSubjectTitle($rawTitle) {
    return trim(preg_replace('/^(SD|CY|DS|ITE)\d{3}\s*/i', '', $rawTitle));
}

// Student-facing subject display name. Two things the raw (code, title) pair
// doesn't do on its own, both requested to match how students actually
// think about these subjects rather than how the curriculum sheet lists them:
//   - Electives: drop the trailing "(Elective N)" marker entirely.
//     e.g. "Machine Learning (Elective 1)" -> "Machine Learning"
//     The elective slot number is curriculum bookkeeping, not something a
//     student needs repeated back to them in a risk alert.
//   - University Identity subjects (code UID10X): prefix with "UID {X}: "
//     instead of leaving them as an unprefixed generic title.
//     e.g. code UID104, title "Unity and Collaboration"
//          -> "UID 4: Unity and Collaboration"
//     Deliberately NOT the full official-portal phrasing
//     ("University Identity 4: ... (Seminar on Core Values 3)") — just the
//     short form, since that's all that's asked for and all the stored
//     title data actually contains.
function displaySubjectTitle(string $code, string $rawTitle): string {
    $title = trim($rawTitle);

    if (preg_match('/^UID10(\d)$/i', trim($code), $m)) {
        return 'UID ' . $m[1] . ': ' . $title;
    }

    $title = trim(preg_replace('/\s*\(Elective\s*\d+\)\s*$/i', '', $title));
    return $title;
}

// Extract track code if embedded in title (e.g. "SD353 Software Development" -> "SD353")
function extractTrackCode($rawTitle) {
    if (preg_match('/^(SD|CY|DS|ITE)\d{3}/i', trim($rawTitle), $m)) {
        return strtoupper($m[0]);
    }
    return null;
}