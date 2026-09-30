<?php
// config/constants.php — Academic logic (replaces theme.py)

// App identity
define('APP_NAME',     'UDM-RADAR');
define('APP_SUBTITLE', 'Risk Analytics & Decision-support for Academic Records');

// Base URL — every include/sidebar link and asset path is prefixed with this,
// so pages work the same whether loaded from /login.php or /student/index.php.
if (!defined('BASE_URL')) {
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? trim(dirname($_SERVER['SCRIPT_NAME']), '/\\') : '';
    $firstFolder = explode('/', str_replace('\\', '/', $scriptDir))[0] ?? '';
    define('BASE_URL', $firstFolder ? '/' . $firstFolder . '/' : '/udm-radar/');
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

// =========================================================================
// CANONICAL FINAL GRADE VOCABULARY & STATUSES
// =========================================================================

// Canonical numerical point values for new final grade entries (1.00 - 4.00, stepped by 0.25)
const FINAL_GRADE_POINTS = [
    '4.00',
    '3.75',
    '3.50',
    '3.25',
    '3.00',
    '2.75',
    '2.50',
    '2.25',
    '2.00',
    '1.75',
    '1.50',
    '1.25',
    '1.00',
];

// Canonical textual statuses
const FINAL_GRADE_STATUSES = [
    'INC',
    'DRP',
    'P',
    'DO',
    'DU',
    'FA',
    'UD',
];

// Canonical non-passing / disqualifying statuses
const FINAL_GRADE_FAILING_STATUSES = [
    'INC',
    'DO',
    'DU',
    'FA',
    'UD',
];

// Legacy final grade values recognized only for historical compatibility
const LEGACY_FINAL_GRADE_VALUES = [
    '0',
    '0.0',
    '0.00',
];

// Canonical stored representation of a legacy value
const LEGACY_FINAL_GRADE_STORED = '0.00';

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

// =========================================================================
// CANONICAL FINAL GRADE HELPERS
// =========================================================================

/**
 * Canonicalizes any valid Final Grade (both canonical point/status and historical legacy forms).
 * - Maps valid numeric points (e.g. '3.5', 3.5, '3.50', '1', 1.0) to canonical 'X.XX' (e.g. '3.50', '1.00').
 * - Maps legacy zero forms ('0', '0.0', '0.00', 0, 0.0) to canonical '0.00'.
 * - Maps 'PASSED' (and lowercase 'passed', 'p') to 'P'.
 * - Maps canonical statuses (e.g. 'inc', 'drp') to uppercase ('INC', 'DRP').
 * - Returns null for any invalid, out-of-scale, or unparseable input.
 */
function canonicalizeFinalGrade(mixed $value): ?string {
    if ($value === null || !is_scalar($value)) {
        return null;
    }
    $valStr = trim((string) $value);
    if ($valStr === '') {
        return null;
    }
    // Check legacy zero representations
    if (in_array($valStr, LEGACY_FINAL_GRADE_VALUES, true) || (is_numeric($valStr) && (float) $valStr == 0.0)) {
        return LEGACY_FINAL_GRADE_STORED; // '0.00'
    }
    $upper = strtoupper($valStr);
    if ($upper === 'PASSED') {
        return 'P';
    }
    if (in_array($upper, FINAL_GRADE_STATUSES, true)) {
        return $upper;
    }
    // Check numeric point scale (1.00 - 4.00, stepped by 0.25)
    if (is_numeric($valStr)) {
        $f = (float) $valStr;
        $fmt = number_format($f, 2, '.', '');
        if (abs($f - (float) $fmt) < 0.0001 && in_array($fmt, FINAL_GRADE_POINTS, true)) {
            return $fmt;
        }
    }
    return null;
}

/**
 * Trims input, uppercases statuses, maps PASSED to P, normalizes valid point
 * shorthand to two decimals (e.g. '4' -> '4.00', '3.5' -> '3.50'), and returns
 * null for empty, legacy (0, 0.0, 0.00), or invalid values.
 */
function normalizeFinalGradeInput(mixed $value): ?string {
    $canon = canonicalizeFinalGrade($value);
    if ($canon === null || $canon === LEGACY_FINAL_GRADE_STORED) {
        return null;
    }
    return $canon;
}

/**
 * Validates new official Final Grade inputs.
 * Accepts canonical points and statuses. Rejects 0, 0.0, 0.00, and out-of-range values.
 */
function isValidFinalGradeEntry(mixed $value): bool {
    return normalizeFinalGradeInput($value) !== null;
}

/**
 * Recognizes canonical points, canonical statuses, and legacy 0.00.
 * Rejects all other values.
 */
function isValidHistoricalFinalGrade(mixed $value): bool {
    return canonicalizeFinalGrade($value) !== null;
}

/**
 * Returns true only for canonical numeric point grades (1.00 - 4.00).
 * Returns false for textual statuses and legacy 0.00.
 */
function isNumericFinalGrade(mixed $value): bool {
    $canon = canonicalizeFinalGrade($value);
    return $canon !== null && in_array($canon, FINAL_GRADE_POINTS, true);
}

/**
 * Returns true for canonical numeric point grades below 1.75 (e.g. 1.50, 1.25, 1.00).
 * Returns true for failing statuses: INC, DO, DU, FA, UD.
 * Returns true for legacy 0.00.
 * Returns false for P and DRP.
 * Operates on normalized/canonicalized input.
 */
function isFailingFinalGrade(mixed $value): bool {
    $canon = canonicalizeFinalGrade($value);
    if ($canon === null) {
        return false;
    }
    if ($canon === LEGACY_FINAL_GRADE_STORED) {
        return true;
    }
    if (in_array($canon, FINAL_GRADE_FAILING_STATUSES, true)) {
        return true;
    }
    if ($canon === 'P' || $canon === 'DRP') {
        return false;
    }
    if (in_array($canon, FINAL_GRADE_POINTS, true)) {
        return (float) $canon < 1.75;
    }
    return false;
}

/**
 * Returns true for passing Final Grades:
 * - Canonical numeric point grades 1.75 - 4.00
 * - Status 'P' (Passed)
 * Returns false for failing grades (points < 1.75, failing statuses, legacy 0.00),
 * DRP, null, or invalid values.
 */
function isPassingFinalGrade(mixed $value): bool {
    $canon = canonicalizeFinalGrade($value);
    if ($canon === null) {
        return false;
    }
    if ($canon === 'P') {
        return true;
    }
    if (in_array($canon, FINAL_GRADE_POINTS, true)) {
        return (float) $canon >= 1.75;
    }
    return false;
}

/**
 * Returns true for all textual statuses and legacy 0.00.
 * Returns false for canonical numeric point grades.
 */
function isExcludedFromGwa(mixed $value): bool {
    $canon = canonicalizeFinalGrade($value);
    if ($canon === null) {
        return true;
    }
    if ($canon === LEGACY_FINAL_GRADE_STORED) {
        return true;
    }
    if (in_array($canon, FINAL_GRADE_STATUSES, true) || $canon === 'P') {
        return true;
    }
    if (in_array($canon, FINAL_GRADE_POINTS, true)) {
        return false;
    }
    return true;
}

/**
 * Formats a Final Grade value for display:
 * - Two decimal places for canonical numeric values (e.g. '3.5' -> '3.50', '1' -> '1.00')
 * - Uppercase for textual statuses (e.g. 'inc' -> 'INC', 'passed' -> 'P')
 * - Preserves legacy 0.00 visibly ('0.00')
 * - Returns '—' for null or empty values
 */
function formatFinalGrade(mixed $value): string {
    $canon = canonicalizeFinalGrade($value);
    if ($canon !== null) {
        return $canon;
    }
    if ($value === null || !is_scalar($value)) {
        return '—';
    }
    $valStr = trim((string) $value);
    return $valStr === '' ? '—' : $valStr;
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
        if (isFailingFinalGrade($grade)) {
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
/**
 * Canonical GWA math — UdM uses CREDIT-UNIT-WEIGHTED averages, not simple averages.
 * Formula: sum(Final Grade Point * Subject Units) / sum(Subject Units).
 * 
 * Inclusion/Exclusion Rules:
 * - Included: Canonical numeric point grades 1.00 - 4.00 (including failing points 1.00, 1.25, 1.50).
 * - Excluded: Non-numeric statuses (INC, DRP, P, DO, DU, FA, UD, PASSED), legacy 0.00, null, blanks.
 * - Missing/non-positive units are safely ignored.
 * - Returns null if total valid units is 0.
 *
 * @param array<int, array{grade: mixed, units: mixed}> $rows
 */
function computeWeightedGWA(array $rows): ?float {
    $totalPoints = 0.0;
    $totalUnits  = 0;
    foreach ($rows as $r) {
        if (!isset($r['grade'], $r['units']) || $r['grade'] === null || $r['units'] === null) {
            continue;
        }
        $units = is_numeric($r['units']) ? (int) $r['units'] : 0;
        if ($units <= 0) {
            continue;
        }
        if (isExcludedFromGwa($r['grade'])) {
            continue;
        }
        if (!isNumericFinalGrade($r['grade'])) {
            continue;
        }
        $pt = (float) canonicalizeFinalGrade($r['grade']);
        $totalPoints += $pt * $units;
        $totalUnits  += $units;
    }
    if ($totalUnits === 0) {
        return null;
    }
    return round($totalPoints / $totalUnits, 2);
}

/**
 * Computes official cumulative unit-weighted GWA for a single student from database historical records.
 * Queries completed terms (is_current = 0) and applies canonical credit-unit weighting.
 */
function computeStudentGwa(PDO $db, int $studentId): ?float {
    $stmt = $db->prepare("
        SELECT g.final_grade AS grade, s.units
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ?
          AND g.is_current = 0
          AND g.final_grade IS NOT NULL
    ");
    $stmt->execute([$studentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return computeWeightedGWA($rows);
}

/**
 * Recalculates and persists official cumulative GWA to student_profiles.current_gwa.
 * Returns the computed GWA (or null if no valid historical grades exist).
 */
function recalculateStudentGwa(PDO $db, int $studentId): ?float {
    $gwa = computeStudentGwa($db, $studentId);
    $stmt = $db->prepare("UPDATE student_profiles SET current_gwa = ? WHERE user_id = ?");
    $stmt->execute([$gwa, $studentId]);
    return $gwa;
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

// ACADEMIC status vocabulary for student_profiles.status.
//
// This column describes CURricular PROGRESS ONLY:
//   Regular   — regular curricular progression
//   Irregular — delayed, repeated, or mixed-load progression
//
// It must NOT be used to represent whether a record is retained, archived, or
// a graduate. Those are lifecycle concerns and belong to record_status below.
// Mixing the two is what produced the silent-coercion defect this replaced:
// 'Archived' was written into a column whose enum only permitted
// 'Regular'/'Irregular', and MySQL (running with a non-strict sql_mode)
// coerced it to 'Regular' instead of raising an error, quietly un-archiving
// every record that was ever archived.
const STUDENT_STATUSES = ['Regular', 'Irregular'];

// Canonical grade_concern workflow states for feedback_reports.status.
//
// FEEDBACK_STATUSES is the full set a ticket can legitimately pass through;
// it must be verified against the live workflows (student submission,
// faculty review, admin routing, resolution) before any CHECK constraint is
// added. FEEDBACK_TERMINAL_STATUSES is only the states Admin resolution sets —
// it is deliberately a subset, not the whole vocabulary.
const FEEDBACK_STATUSES = [
    'open',
    'faculty_review',
    'awaiting_admin',
    'resolved',
    'rejected',
];
const FEEDBACK_TERMINAL_STATUSES = ['resolved', 'rejected'];

// Canonical LIFECYCLE vocabulary for student_profiles.record_status.
//
//   Active    — currently enrolled Student record
//   Archived  — retained historical record, no longer active
//   Graduated — completed program record
//
// Deliberately a separate concept from academic status. A Student's academic
// status (Regular/Irregular) never changes merely because their record is
// archived or because they graduate.
//
// Note: record_status is the DATABASE AUTHORITY on lifecycle. It is not copied
// into the session; session state would go stale the moment an Admin archives
// a logged-in Student. `users.is_active` is the account-access flag and is what
// authentication enforces (1 = may authenticate, 0 = may not).
const RECORD_STATUSES       = ['Active', 'Archived', 'Graduated'];
const RECORD_STATUS_DEFAULT = 'Active';

// Canonical program vocabulary, matching student_profiles.course's enum.
// The column is a fixed enum, so any value written into it — including a
// proposed correction — must be checked against this list first.
const COURSE_PROGRAMS = [
    'BSIT - Software Development',
    'BSIT - Data Science',
    'BSIT - Cyber Security',
];

function allowedCourses(): array {
    return COURSE_PROGRAMS;
}

function isValidCourse(?string $course): bool {
    return $course !== null && in_array(trim($course), COURSE_PROGRAMS, true);
}

// Shared ordinal label for a curriculum year (1 -> "1st Year"). Lives here
// rather than in one admin page so the student directory, the analytics
// filters, and any export that prints a year level all render it identically.
function ordinalYearLabel(int|string|null $year): string {
    return match ((int) $year) {
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        default => ($year === null || $year === '' ? '—' : $year . 'th Year'),
    };
}

// Curriculum year levels that exist in the BSIT curriculum. Used by analytics
// filters and section labelling so "year 4" is a known scope rather than an
// assumption baked into each page.
const CURRICULUM_YEAR_LEVELS = [1, 2, 3, 4];

// Canonical "what semester is it right now" resolver — UdM's academic
// calendar: July-December = 1st semester, January-May = 2nd semester.
//
// NOTE: the semester label below is the label of the term *containing* the
// given date. A student's ongoing term is the one they are currently sitting
// in, so callers that need "the term in progress" (grade encoding, the
// student home snapshot) must use this rather than hardcoding a school year
// and semester pair, which silently pins the whole system to one term.
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
function isValidGrade($val): bool {
    return isValidFinalGradeEntry($val);
}

// Validates a raw 0-100 term percentage for Prelim/Midterm/Pre-Final entry.
// Accepts only numeric percentages in the range [0.00, 100.00] with up to two decimal places.
// Textual statuses (INC, DRP, P, DO, DU, FA, UD, PASSED) belong ONLY to final_grade
// and are strictly rejected here.
function isValidTermPercentage(mixed $val): bool {
    if ($val === null || !is_scalar($val)) {
        return false;
    }
    $valStr = trim((string) $val);
    if ($valStr === '') {
        return false;
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $valStr)) {
        return false;
    }
    $f = (float) $valStr;
    return $f >= 0.0 && $f <= 100.0;
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