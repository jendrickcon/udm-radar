<?php
/**
 * Shared Grade History Repository and View Model Helper
 *
 * Extracts and unifies academic history querying and grouping across:
 * - admin/students.php (Student profile directory modal)
 * - admin/grades.php / admin/grades_data.php (Admin grades modal read-only history)
 *
 * Enforces institutional grading rules from config/constants.php:
 * - Read-only historical grades from completed terms (is_current = 0)
 * - Excludes null final grades
 * - Groups by School Year and Curriculum Year / Semester
 * - Formats final grades and textual outcomes accurately without coercion
 * - Computes optional Term GWA via canonical computeWeightedGWA()
 */

require_once __DIR__ . '/../config/constants.php';

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