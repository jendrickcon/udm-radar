<?php
// config/constants.php — Academic logic

// App identity
define('APP_NAME',     'UDM-RADAR');
define('APP_SUBTITLE', 'Risk Analytics & Decision-support for Academic Records');

// FIXED: Aligned with the repository setup instructions
if (!defined('BASE_URL')) {
    define('BASE_URL', '/udm-radar/'); 
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

function convertPercentageToPoint(float $pct): float {
    if ($pct >= 99) return 4.00;
    if ($pct >= 97) return 3.75;
    if ($pct >= 95) return 3.50;
    if ($pct >= 92) return 3.25;
    if ($pct >= 90) return 3.00;
    if ($pct >= 88) return 2.75;
    if ($pct >= 86) return 2.50;
    if ($pct >= 84) return 2.25;
    if ($pct >= 82) return 2.00;
    if ($pct >= 80) return 1.75;
    if ($pct >= 78) return 1.50;
    if ($pct >= 76) return 1.25;
    if ($pct >= 75) return 1.00;
    return 0.00;
}

// --- Grade normalization --------------------------------------------------

function normalizeTermGrade($rawPercentage): ?float {
    if ($rawPercentage === null || $rawPercentage < 0) return null;
    return convertPercentageToPoint((float) $rawPercentage);
}

// FIXED: Gracefully rejects text statuses to prevent converting INC or DO into 0.00
function normalizePointGrade($grade): ?float {
    if ($grade === null || trim((string) $grade) === '') {
        return null;
    }
    if (!is_numeric($grade)) {
        return null;
    }
    $point = round((float) $grade, 2);
    return $point >= 0.00 && $point <= 4.00 ? $point : null;
}

// --- Validation Helpers --------------------------------------------------

// FIXED: Explicitly validates raw percentages for Prelim/Midterm/Pre-Final
function isValidTermPercentage($value): bool {
    if ($value === null || trim((string) $value) === '') {
        return false;
    }
    return is_numeric($value) && (float) $value >= 0 && (float) $value <= 100;
}

// FIXED: Explicitly validates the 1.00-4.00 scale AND official textual statuses for Final Grades
function isValidFinalGrade($value): bool {
    $value = strtoupper(trim((string) $value));
    
    if (in_array($value, ['INC', 'DO', 'DU', 'FA', 'UD'], true)) {
        return true;
    }
    if (!is_numeric($value)) {
        return false;
    }
    $allowed = [
        0.00, 1.00, 1.25, 1.50, 1.75, 2.00, 2.25,
        2.50, 2.75, 3.00, 3.25, 3.50, 3.75, 4.00,
    ];
    return in_array(round((float) $value, 2), $allowed, true);
}

/**
 * @deprecated Ambiguous validator. Use isValidTermPercentage() or isValidFinalGrade() instead.
 */
function isValidGrade($val) {
    if ($val === '') return false;
    $valStr = strtoupper(trim((string)$val));
    if (in_array($valStr, ['INC', 'DO', 'DU', 'FA', 'UD'])) return true;
    
    if (is_numeric($val)) {
        $f = (float)$val;
        if ($f >= 1.00 && $f <= 4.00) {
            $step = (int)round($f * 100);
            if ($step % 25 === 0) return true;
        }
    }
    return false;
}

// --- Risk & Honors Evaluation --------------------------------------------------

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
    if ($hasDisqualifyingGrade) return 'Not Eligible';
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
    default    => '#64748B', // FIXED: Unknown or missing risk implies no data, not safety.
  };
}

function computeWeightedGWA(array $rows): ?float {
    $specialStatuses = ['INC', 'DO', 'DU', 'FA', 'UD'];
    $totalPoints = 0.0;
    $totalUnits  = 0;
    foreach ($rows as $r) {
        if ($r['grade'] === null || $r['units'] === null) continue;
        $gradeStr = strtoupper(trim((string) $r['grade']));
        if (in_array($gradeStr, $specialStatuses, true)) continue;
        if (!is_numeric($gradeStr)) continue; 
        $totalPoints += (float) $gradeStr * (int) $r['units'];
        $totalUnits  += (int) $r['units'];
    }
    if ($totalUnits === 0) return null;
    return round($totalPoints / $totalUnits, 2);
}

function computeRiskFromAvg(float $avg): string {
    if ($avg < 1.75) return 'HIGH';
    if ($avg < 2.50) return 'MODERATE';
    return 'LOW';
}

// FIXED: Updated Context. The actual ML Decision Tree is live in python_ml/. 
// This function now serves as the calculation-based fallback estimate when the ML 
// service is unavailable or the student lacks necessary historical features.
function predictFinalGradeHeuristic(?float $currentPrelim, ?float $historicalGWA): ?float {
    if ($currentPrelim === null && $historicalGWA === null) return null;
    if ($currentPrelim === null) return round($historicalGWA, 2);
    if ($historicalGWA === null) return round($currentPrelim, 2);
    $blend = (0.5 * $currentPrelim) + (0.5 * $historicalGWA);
    $blend = max(1.00, min(4.00, $blend));
    return round($blend * 4) / 4; 
}

// FIXED: Unicode-safe string parsing via mb_substr
function formatNameLastFirst(string $first, ?string $middle, string $last): string {
    $mi = $middle ? ' ' . mb_strtoupper(mb_substr(trim($middle), 0, 1)) . '.' : '';
    return trim($last) . ', ' . trim($first) . $mi;
}

function formatNameShort(string $first, string $last): string {
    $initial = $first !== '' ? mb_strtoupper(mb_substr(trim($first), 0, 1)) . '.' : '';
    return trim($initial . ' ' . $last);
}

// Canonical "what semester is it right now" resolver
function getCurrentTerm(?DateTimeInterface $asOf = null): array {
    $now = $asOf ?? new DateTime();
    $month = (int) $now->format('n');
    $year  = (int) $now->format('Y');

    if ($month >= 7) {
        $semester = 1;
        $schoolYear = $year . '-' . ($year + 1);
    } else {
        $semester = 2;
        $schoolYear = ($year - 1) . '-' . $year;
    }

    return ['school_year' => $schoolYear, 'semester' => $semester];
}

// Clean prefixes like "SD353 Software Development" -> "Software Development"
function cleanSubjectTitle($rawTitle) {
    return trim(preg_replace('/^(SD|CY|DS|ITE)\d{3}\s*/i', '', $rawTitle));
}

// Extract track code if embedded in title (e.g. "SD353 Software Development" -> "SD353")
function extractTrackCode($rawTitle) {
    if (preg_match('/^(SD|CY|DS|ITE)\d{3}/i', trim($rawTitle), $m)) {
        return strtoupper($m[0]);
    }
    return null;
}