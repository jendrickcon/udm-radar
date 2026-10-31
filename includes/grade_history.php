<?php
/**
 * Shared Grade History Repository and View Model Helper
 *
 * Extracts and unifies academic history querying and grouping across:
 * - admin/students.php (Student profile directory modal)
 * - admin/grades.php / admin/grades_data.php (Admin grades modal read-only history)
 * - student/grades.php (Student portal Grades & History view)
 *
 * Enforces institutional grading rules and chronological period boundaries:
 * - Strict period normalization and chronological ranking
 * - Future period exclusion and active-term separation
 * - Read-only historical grades from completed terms (rank < activeRank)
 * - Excludes null, empty, or placeholder final grades from academic history
 * - Formats final grades and textual outcomes accurately without coercion
 * - Computes optional Term GWA via canonical computeWeightedGWA()
 */

require_once __DIR__ . '/../config/constants.php';

/**
 * Normalize a semester representation to an integer:
 * 1 => First Semester
 * 2 => Second Semester
 * 3 => Summer / Midyear
 * Returns null if unrecognized or invalid.
 */
function normalizeSemester(mixed $sem): ?int {
    if ($sem === null) {
        return null;
    }
    $s = strtolower(trim((string)$sem));
    if ($s === '') {
        return null;
    }
    return match ($s) {
        '1', 'sem 1', 'semester 1', 'first semester', '1st sem' => 1,
        '2', 'sem 2', 'semester 2', 'second semester', '2nd sem' => 2,
        '3', 'summer', 'midyear', 'sem 3', 'semester 3', '3rd sem' => 3,
        default => null,
    };
}

/**
 * Normalize a school year representation to 'YYYY-YYYY'.
 * Strict requirement: End year must equal start year + 1.
 * Returns null if unrecognized or invalid.
 */
function normalizeSchoolYear(mixed $sy): ?string {
    if ($sy === null) {
        return null;
    }
    $s = trim((string)$sy);
    if (!preg_match('/^(\d{4})-(\d{4})$/', $s, $m)) {
        return null;
    }
    $start = (int)$m[1];
    $end = (int)$m[2];
    if ($end !== $start + 1) {
        return null;
    }
    return sprintf('%04d-%04d', $start, $end);
}

/**
 * Compute an integer chronological rank for an academic period.
 * Formula: (start_year * 3) + normalized_semester
 * Returns null if either component is invalid.
 */
function periodChronologicalRank(?string $schoolYear, mixed $semester): ?int {
    $sy = normalizeSchoolYear($schoolYear);
    $sem = normalizeSemester($semester);
    if ($sy === null || $sem === null) {
        return null;
    }
    $startYear = (int)explode('-', $sy)[0];
    return ($startYear * 3) + $sem;
}

/**
 * Determine whether a given period occurred strictly before the active term.
 */
function isHistoricalPeriod(?string $schoolYear, mixed $semester, ?array $activeTerm = null): bool {
    $term = $activeTerm ?? getCurrentTerm();
    $rank = periodChronologicalRank($schoolYear, $semester);
    $activeRank = periodChronologicalRank($term['school_year'] ?? null, $term['semester'] ?? null);
    if ($rank === null || $activeRank === null) {
        return false;
    }
    return $rank < $activeRank;
}

/**
 * Determine whether a given period matches the active term.
 */
function isCurrentPeriod(?string $schoolYear, mixed $semester, ?array $activeTerm = null): bool {
    $term = $activeTerm ?? getCurrentTerm();
    $sy = normalizeSchoolYear($schoolYear);
    $sem = normalizeSemester($semester);
    $activeSy = normalizeSchoolYear($term['school_year'] ?? null);
    $activeSem = normalizeSemester($term['semester'] ?? null);
    if ($sy === null || $sem === null || $activeSy === null || $activeSem === null) {
        return false;
    }
    return ($sy === $activeSy && $sem === $activeSem);
}

/**
 * Determine whether a given period is strictly after the active term.
 */
function isFuturePeriod(?string $schoolYear, mixed $semester, ?array $activeTerm = null): bool {
    $term = $activeTerm ?? getCurrentTerm();
    $rank = periodChronologicalRank($schoolYear, $semester);
    $activeRank = periodChronologicalRank($term['school_year'] ?? null, $term['semester'] ?? null);
    if ($rank === null || $activeRank === null) {
        return false;
    }
    return $rank > $activeRank;
}

/**
 * Validate whether a grade represents a finalized historical outcome.
 * Accepts numeric points (0.00 - 4.00) or recognized official statuses (INC, DRP, P, etc.).
 * Null, empty string, and placeholder em-dash are strictly rejected.
 */
function isFinalizedHistoricalGrade(mixed $val): bool {
    if ($val === null) {
        return false;
    }
    $s = trim((string)$val);
    if ($s === '' || $s === '—') {
        return false;
    }
    if (is_numeric($s)) {
        $num = (float)$s;
        return $num >= 0.00 && $num <= 4.00;
    }
    $upper = strtoupper($s);
    if (defined('FINAL_GRADE_STATUSES') && in_array($upper, FINAL_GRADE_STATUSES, true)) {
        return true;
    }
    return in_array($upper, ['INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD', 'PASSED'], true);
}

/**
 * Determine the current subject status and stage descriptor for current-term tables.
 * Returns an array:
 * [
 *   'type' => 'finalized_numeric' | 'finalized_status' | 'prefinal_recorded' | 'midterm_recorded' | 'prelim_recorded' | 'pending',
 *   'label' => string,
 *   'value' => float | string | null,
 *   'is_finalized' => bool,
 * ]
 */
function determineCurrentSubjectStatus(array $row): array {
    $finalGradeRaw = $row['final_grade'] ?? null;
    $finalGradeStatus = null;
    $finalGrade = null;
    if ($finalGradeRaw !== null) {
        $fgStr = strtoupper(trim((string)$finalGradeRaw));
        if ($fgStr !== '' && $fgStr !== '—') {
            if (defined('FINAL_GRADE_STATUSES') && in_array($fgStr, FINAL_GRADE_STATUSES, true)) {
                $finalGradeStatus = $fgStr;
            } elseif (is_numeric($fgStr)) {
                $finalGrade = (float)$fgStr;
            }
        }
    }

    if ($finalGrade !== null) {
        return [
            'type' => 'finalized_numeric',
            'label' => formatFinalGrade($finalGrade),
            'value' => $finalGrade,
            'is_finalized' => true,
        ];
    }
    if ($finalGradeStatus !== null) {
        return [
            'type' => 'finalized_status',
            'label' => $finalGradeStatus,
            'value' => $finalGradeStatus,
            'is_finalized' => true,
        ];
    }

    $prefinal = (isset($row['prefinal']) && $row['prefinal'] !== null && trim((string)$row['prefinal']) !== '' && isValidTermPercentage($row['prefinal']))
        ? (float)$row['prefinal'] : null;
    $midterm = (isset($row['midterm']) && $row['midterm'] !== null && trim((string)$row['midterm']) !== '' && isValidTermPercentage($row['midterm']))
        ? (float)$row['midterm'] : null;
    $prelim = (isset($row['prelim']) && $row['prelim'] !== null && trim((string)$row['prelim']) !== '' && isValidTermPercentage($row['prelim']))
        ? (float)$row['prelim'] : null;

    if ($prefinal !== null) {
        return [
            'type' => 'prefinal_recorded',
            'label' => 'Pre-Final recorded',
            'value' => $prefinal,
            'is_finalized' => false,
        ];
    }
    if ($midterm !== null) {
        return [
            'type' => 'midterm_recorded',
            'label' => 'Midterm recorded',
            'value' => $midterm,
            'is_finalized' => false,
        ];
    }
    if ($prelim !== null) {
        return [
            'type' => 'prelim_recorded',
            'label' => 'Preliminary recorded',
            'value' => $prelim,
            'is_finalized' => false,
        ];
    }

    return [
        'type' => 'pending',
        'label' => 'Final grade pending',
        'value' => null,
        'is_finalized' => false,
    ];
}

/**
 * Fetch student current-term grades strictly anchored to the active period.
 */
function fetchStudentCurrentGrades(PDO $db, int $studentId, ?array $activeTerm = null): array {
    if ($studentId <= 0) {
        return [];
    }
    $term = $activeTerm ?? getCurrentTerm();

    $stmt = $db->prepare("
        SELECT g.*, s.code, s.title, s.units
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ? AND g.school_year = ? AND g.semester = ?
        ORDER BY s.code ASC
    ");
    $stmt->execute([$studentId, $term['school_year'], $term['semester']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch and organize student academic history strictly for completed historical periods.
 * Excludes active period, future periods, and rows without a finalized grade.
 * Groups by normalized term and sorts descending by chronological rank.
 */
function fetchStudentCompletedAcademicHistory(PDO $db, int $studentId, ?array $activeTerm = null): array {
    if ($studentId <= 0) {
        return [];
    }
    $term = $activeTerm ?? getCurrentTerm();

    $stmt = $db->prepare("
        SELECT g.*, s.code, s.title, s.units
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ?
        ORDER BY s.code ASC
    ");
    $stmt->execute([$studentId]);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $historyTerms = [];
    $termRanks = [];

    foreach ($allRows as $r) {
        if (!isHistoricalPeriod($r['school_year'] ?? null, $r['semester'] ?? null, $term)) {
            continue;
        }
        if (!isFinalizedHistoricalGrade($r['final_grade'] ?? null)) {
            continue;
        }

        $normSy = normalizeSchoolYear($r['school_year']);
        $normSem = normalizeSemester($r['semester']);
        $rank = periodChronologicalRank($normSy, $normSem);

        $semLabel = match ($normSem) {
            1 => 'Sem 1',
            2 => 'Sem 2',
            3 => 'Summer',
            default => 'Sem ' . $normSem,
        };
        $termLabel = $normSy . ' — ' . $semLabel;

        $historyTerms[$termLabel][] = $r;
        $termRanks[$termLabel] = $rank;
    }

    uksort($historyTerms, function ($a, $b) use ($termRanks) {
        return ($termRanks[$b] ?? 0) <=> ($termRanks[$a] ?? 0);
    });

    return $historyTerms;
}

/**
 * Fetch raw historical grade records for a single student.
 *
 * @param PDO $db
 * @param int $studentId
 * @return array
 */
function fetchStudentHistoricalGrades(PDO $db, int $studentId): array {
    if ($studentId <= 0) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT g.student_id, g.school_year, g.semester, s.code, s.title, s.year_level, s.units, g.final_grade
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ? AND g.is_current = 0 AND g.final_grade IS NOT NULL
        ORDER BY g.school_year ASC, g.semester ASC, s.code ASC
    ");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Group raw historical grade rows for a single student into terms.
 *
 * Follows the trusted curriculum year resolution logic established in admin/students.php:
 * - A term is labelled by the highest curriculum year it contains.
 * - Groups rows under terms ordered descending (krsort).
 *
 * @param array $historyRows
 * @return array List of term structures with termKey, schoolYear, semester, yearLevel, termGwa, subjects
 */
function groupStudentHistoryByTerm(array $historyRows): array {
    if (empty($historyRows)) {
        return [];
    }

    $tempHistory = [];
    foreach ($historyRows as $h) {
        $sy = $h['school_year'] ?? 'Unknown Year';
        $sem = $h['semester'];

        $curriculumYear = ($h['year_level'] !== null && (int) $h['year_level'] > 0)
            ? (int) $h['year_level']
            : 0;

        $tempHistory[$sy][$sem][$curriculumYear][] = [
            'code'     => $h['code'],
            'title'    => cleanSubjectTitle($h['title']),
            'units'    => isset($h['units']) && $h['units'] !== null ? (int) $h['units'] : 0,
            'grade'    => formatFinalGrade($h['final_grade']),
            'rawGrade' => $h['final_grade'],
            'outcome'  => getFinalGradeOutcomeLabel($h['final_grade']),
        ];
    }

    $terms = [];
    foreach ($tempHistory as $sy => $sems) {
        $termYears = [];
        foreach ($sems as $subjectsByYear) {
            foreach ($subjectsByYear as $yl => $subjects) {
                if ($yl > 0) {
                    $termYears[$yl] = true;
                }
            }
        }

        $termYearLevel = !empty($termYears) ? (int) max(array_keys($termYears)) : 0;
        $labelYear = $termYearLevel > 0 ? $termYearLevel : 1;

        foreach ($sems as $sem => $subjectsByYear) {
            $yearLevel = [];
            foreach ($subjectsByYear as $subjects) {
                foreach ($subjects as $entry) {
                    $yearLevel[] = $entry;
                }
            }
            if (empty($yearLevel)) {
                continue;
            }

            $ordinalYear = match ($labelYear) {
                1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th',
                default => $labelYear . 'th',
            };
            $ordinalSem = ($sem == 1) ? '1st Sem' : (($sem == 2) ? '2nd Sem' : (($sem == 3 || strtolower((string)$sem) === 'summer') ? 'Summer' : 'Unknown Sem'));
            $termKey = "$sy | {$ordinalYear} Year, $ordinalSem";

            // Calculate optional term GWA
            $termGwaRows = array_map(fn($item) => [
                'grade' => $item['rawGrade'],
                'units' => $item['units'],
            ], $yearLevel);
            $computedGwa = computeWeightedGWA($termGwaRows);

            $terms[$termKey] = [
                'termKey'    => $termKey,
                'schoolYear' => $sy,
                'semester'   => $sem,
                'yearLevel'  => $labelYear,
                'termGwa'    => $computedGwa !== null ? number_format($computedGwa, 2) : null,
                'subjects'   => array_map(function($e) {
                    return [
                        'code'    => $e['code'],
                        'title'   => $e['title'],
                        'units'   => $e['units'],
                        'grade'   => $e['grade'],
                        'outcome' => $e['outcome'],
                    ];
                }, $yearLevel),
            ];
        }
    }

    krsort($terms, SORT_NATURAL);
    return $terms;
}

/**
 * Fetch and group academic history for a single student.
 *
 * @param PDO $db
 * @param int $studentId
 * @return array
 */
function getStudentAcademicHistory(PDO $db, int $studentId): array {
    $rows = fetchStudentHistoricalGrades($db, $studentId);
    return groupStudentHistoryByTerm($rows);
}

/**
 * Human-readable outcome description for Final Grades.
 *
 * @param mixed $value
 * @return string
 */
function getFinalGradeOutcomeLabel(mixed $value): string {
    if ($value === null || trim((string)$value) === '') {
        return 'In Progress';
    }
    $canon = canonicalizeFinalGrade($value);
    if ($canon === null) {
        return 'â€”';
    }
    if ($canon === 'P') {
        return 'Passed';
    }
    if ($canon === 'INC') {
        return 'Incomplete';
    }
    if ($canon === 'DRP') {
        return 'Dropped';
    }
    if ($canon === 'DO') {
        return 'Dropped Official';
    }
    if ($canon === 'DU') {
        return 'Dropped Unofficial';
    }
    if ($canon === 'FA') {
        return 'Failure (Absences)';
    }
    if ($canon === 'UD') {
        return 'Unofficial Drop';
    }
    if (isPassingFinalGrade($canon)) {
        return 'Passed';
    }
    if (isFailingFinalGrade($canon)) {
        return 'Failed';
    }
    return $canon;
}

/**
 * Batch fetch and group academic history for all students.
 * Used by admin/students.php to maintain identical grouping and presentation.
 *
 * @param PDO $db
 * @return array Keyed by student_id -> termKey -> list of subjects
 */
function fetchAllStudentsGroupedHistory(PDO $db): array {
    $histStmt = $db->query("
        SELECT g.student_id, g.school_year, g.semester, s.code, s.title, s.year_level, s.units, g.final_grade
        FROM grades g JOIN subjects s ON s.id = g.subject_id
        WHERE g.is_current = 0 AND g.final_grade IS NOT NULL
        ORDER BY g.student_id, g.school_year ASC, g.semester ASC, s.code
    ");

    $tempHistory = [];
    foreach ($histStmt->fetchAll(PDO::FETCH_ASSOC) as $h) {
        $sid = $h['student_id'];
        $sy = $h['school_year'] ?? 'Unknown Year';
        $sem = $h['semester'];

        $curriculumYear = ($h['year_level'] !== null && (int) $h['year_level'] > 0)
            ? (int) $h['year_level']
            : 0;

        $tempHistory[$sid][$sy][$sem][$curriculumYear][] = [
            'code'  => $h['code'],
            'title' => cleanSubjectTitle($h['title']),
            'grade' => formatFinalGrade($h['final_grade']),
        ];
    }

    $historyByStudent = [];
    foreach ($tempHistory as $sid => $years) {
        foreach ($years as $sy => $sems) {
            $termYears = [];
            foreach ($sems as $subjectsByYear) {
                foreach ($subjectsByYear as $yl => $subjects) {
                    if ($yl > 0) $termYears[$yl] = true;
                }
            }

            $termYearLevel = !empty($termYears) ? (int) max(array_keys($termYears)) : 0;
            $labelYear = $termYearLevel > 0 ? $termYearLevel : 1;

            foreach ($sems as $sem => $subjectsByYear) {
                $yearLevel = [];
                foreach ($subjectsByYear as $subjects) {
                    foreach ($subjects as $entry) { $yearLevel[] = $entry; }
                }
                if (empty($yearLevel)) continue;

                $ordinalYear = match ($labelYear) {
                    1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th',
                    default => $labelYear . 'th',
                };
                $ordinalSem = ($sem == 1) ? '1st Sem' : (($sem == 2) ? '2nd Sem' : 'Unknown Sem');
                $termKey = "$sy | {$ordinalYear} Year, $ordinalSem";

                foreach ($yearLevel as $entry) {
                    $historyByStudent[$sid][$termKey][] = $entry;
                }
            }
        }
        krsort($historyByStudent[$sid], SORT_NATURAL);
    }

    return $historyByStudent;
}