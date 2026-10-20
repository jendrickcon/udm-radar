<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/constants.php';
require_once __DIR__ . '/pef_cc_planning_policy.php';

final class PefCcValidationException extends InvalidArgumentException
{
    public function __construct(public array $errors, public array $fieldErrors = [])
    {
        parent::__construct(implode(' ', $errors));
    }
}

/** A detached scalar snapshot. Nested arrays cannot be changed through this readonly property. */
final readonly class PefCcViewModel implements JsonSerializable
{
    public array $data;

    public function __construct(array $data)
    {
        $copy = static function (mixed $value) use (&$copy): mixed {
            if (is_array($value)) {
                $result = [];
                foreach ($value as $key => $child) {
                    $result[$key] = $copy($child);
                }
                return $result;
            }
            if (is_object($value) || is_resource($value) || (is_float($value) && !is_finite($value))) {
                throw new InvalidArgumentException('Planning models contain finite scalar data only.');
            }
            return $value;
        };
        $this->data = $copy($data);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}

function pefCcValidYear(mixed $value): bool
{
    return is_string($value) && preg_match('/\A([0-9]{4})-([0-9]{4})\z/D', $value, $parts) === 1
        && (int) $parts[1] > 0 && (int) $parts[2] === (int) $parts[1] + 1;
}

function pefCcSemester(mixed $value): ?int
{
    return in_array($value, [1, '1', 2, '2'], true) ? (int) $value : null;
}

function pefCcPositiveId(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) {
        return null;
    }
    $converted = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $converted === false ? null : $converted;
}

/** XML 1.0 character and Excel text limits, without repairing stored evidence. */
function pefCcSafeText(mixed $value, bool $required = false, int $max = 32767): bool
{
    return is_string($value) && preg_match('//u', $value) === 1
        && preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $value) === 0
        && mb_strlen($value, 'UTF-8') <= $max && (!$required || trim($value) !== '');
}

const PEF_CC_PRINT_LIMIT_MESSAGE = 'The planning form contains text that exceeds the printable form limit. Review the highlighted value before downloading.';
const PEF_CC_SECTION_PRINT_MESSAGE = 'The selected section cannot be printed safely in the current form. Academic evaluator review is required.';

/** Reject unprintable source text without repairing or truncating academic evidence. */
function pefCcPrintableErrors(array $identity, array $recorded, array $proposed, mixed $section, array $config): array
{
    $limits = $config['printable'];
    $fields = [];
    $check = static function (mixed $value, string $key, string $label, int $limit, bool $required = true) use (&$fields): void {
        if (!pefCcSafeText($value, $required, $limit)
            || preg_match('/[\t\r\n\x{85}\x{2028}\x{2029}]/u', $value) !== 0) {
            $fields[$key] = $label . ' must be one line of at most ' . $limit . ' Unicode characters. Recorded values are not changed.';
        }
    };
    $check($identity['name'] ?? null, 'identity.name', 'Student name', $limits['name']);
    $check($identity['student_number'] ?? null, 'identity.student_number', 'Student number', $limits['student_number']);
    foreach (['recorded' => $recorded, 'proposed' => $proposed] as $type => $rows) {
        foreach ($rows as $index => $row) {
            foreach (['code', 'title'] as $field) {
                $check($row[$field] ?? null, $type . '.' . $index . '.' . $field,
                    ucfirst($type) . ' subject ' . ($index + 1) . ' ' . $field, $limits[$type . '_' . $field]);
            }
        }
    }
    $check($section, 'section', 'Proposed section', $limits['section'], false);
    if (isset($fields['section'])) {
        $fields['section'] = PEF_CC_SECTION_PRINT_MESSAGE;
    }
    return $fields;
}

function pefCcNormalizeInputs(array $input, array $config): array
{
    $allowed = ['evaluation_year', 'evaluation_semester', 'target_year', 'target_semester', 'section', 'subject_ids'];
    $errors = [];
    $fields = [];
    foreach (array_keys($input) as $key) {
        if (!in_array($key, $allowed, true)) {
            $errors[] = 'Unexpected planning input is not permitted.';
            $fields['input'] = 'Only the documented planning fields are accepted.';
        }
    }
    $normalized = [];
    foreach (['evaluation_year', 'target_year'] as $key) {
        $value = is_string($input[$key] ?? null) ? trim($input[$key]) : null;
        if (!pefCcValidYear($value)) {
            $fields[$key] = 'Use a consecutive school-year pair in YYYY-YYYY format.';
        } else {
            $normalized[$key] = $value;
        }
    }
    foreach (['evaluation_semester', 'target_semester'] as $key) {
        $semester = pefCcSemester($input[$key] ?? null);
        if ($semester === null) {
            $fields[$key] = 'Choose First Semester or Second Semester.';
        } else {
            $normalized[$key] = $semester;
        }
    }
    $section = $input['section'] ?? '';
    if (!pefCcSafeText($section) || preg_match('/[\t\r\n\x{85}\x{2028}\x{2029}]/u', $section) !== 0) {
        $fields['section'] = PEF_CC_SECTION_MESSAGE;
    } else {
        $normalized['section'] = $section;
    }
    $ids = $input['subject_ids'] ?? [];
    if (!is_array($ids) || !array_is_list($ids)) {
        $fields['subject_ids'] = 'Choose an ordered list of distinct subjects.';
    } else {
        $normalized['subject_ids'] = [];
        foreach ($ids as $id) {
            $normalizedId = pefCcPositiveId($id);
            if ($normalizedId === null) {
                $fields['subject_ids'] = 'Each proposed subject must have a positive catalogue ID.';
            } elseif (in_array($normalizedId, $normalized['subject_ids'], true)) {
                $fields['subject_ids'] = 'Each proposed subject may be selected only once.';
            } else {
                $normalized['subject_ids'][] = $normalizedId;
            }
        }
    }
    if ($fields !== [] || $errors !== []) {
        throw new PefCcValidationException(array_values(array_unique(array_merge($errors, array_values($fields)))), $fields);
    }
    return $normalized;
}

function pefCcUsableUnits(mixed $units): bool
{
    return !is_bool($units) && is_numeric($units) && is_finite((float) $units)
        && (float) $units > 0 && (float) $units <= PHP_INT_MAX && (int) $units > 0;
}

function pefCcRecordErrors(array $row): array
{
    $errors = [];
    if (pefCcPositiveId($row['id'] ?? null) === null || pefCcPositiveId($row['subject_id'] ?? null) === null) {
        $errors[] = 'A recorded academic source identifier is invalid.';
    }
    if (!pefCcSafeText($row['code'] ?? null, true) || !pefCcSafeText($row['title'] ?? null, true)) {
        $errors[] = 'A subject reference is missing or contains unsupported text.';
    }
    if (!pefCcUsableUnits($row['units'] ?? null) || (float) $row['units'] !== (float) (int) $row['units']) {
        $errors[] = 'Recorded subject units require review.';
    }
    $grade = $row['grade'] ?? null;
    if ($grade !== null && (!is_string($grade) && !is_int($grade) && !is_float($grade))) {
        $errors[] = 'A recorded final grade has an unsupported source type.';
    } elseif (is_string($grade) && !pefCcSafeText($grade)) {
        $errors[] = 'A recorded final grade contains unsupported text.';
    } elseif ($grade !== null && trim((string) $grade) !== '' && canonicalizeFinalGrade($grade) === null) {
        $errors[] = 'A recorded final grade is unsupported.';
    }
    return $errors;
}

/** One primary reason per row; the shared helper remains the GWA authority. */
function pefCcGradeEvidence(array $row): array
{
    $grade = $row['grade'] ?? null;
    $canonical = canonicalizeFinalGrade($grade);
    $outcome = match (true) {
        $grade === null, is_string($grade) && trim($grade) === '' => 'unavailable',
        is_bool($grade), $canonical === null => 'review',
        isIncompleteFinalGrade($grade) => 'incomplete',
        isPassingFinalGrade($grade) => 'passed',
        isFailingFinalGrade($grade) => 'failed',
        default => 'review',
    };
    $reason = match (true) {
        !pefCcUsableUnits($row['units'] ?? null) => 'invalid_units',
        $grade === null => 'null',
        is_string($grade) && trim($grade) === '' => 'blank',
        is_bool($grade), $canonical === null => 'unsupported_value',
        $canonical === LEGACY_FINAL_GRADE_STORED => 'numeric_zero',
        isIncompleteFinalGrade($grade) => 'inc',
        isExcludedFromGwa($grade) => 'textual_status',
        isNumericFinalGrade($grade) => 'included',
        default => 'unsupported_value',
    };
    return ['outcome' => $outcome, 'gwa_reason' => $reason];
}

if (!function_exists('pefCcSemesterLabel')) {
    function pefCcSemesterLabel(int $semester): string
    {
        return $semester === 1 ? 'First Semester' : 'Second Semester';
    }
}

/** Whole groups are reviewed before any row can be treated as completed. */
function pefCcTermChoices(array $records, array $currentTerm): array
{
    $groups = [];
    foreach ($records as $row) {
        $year = $row['school_year'] ?? null;
        $semester = $row['semester'] ?? null;
        $key = json_encode([$year, $semester === null ? null : (string) $semester], JSON_THROW_ON_ERROR);
        $groups[$key]['year'] = $year;
        $groups[$key]['semester'] = $semester;
        $groups[$key]['rows'][] = $row;
    }
    $choices = [];
    foreach ($groups as $group) {
        $year = $group['year'];
        $semester = pefCcSemester($group['semester']);
        $integrityErrors = [];
        $temporalErrors = [];
        if (!pefCcValidYear($year) || $semester === null) {
            $integrityErrors[] = 'The evaluation term is invalid.';
        }
        if (!pefCcValidYear($currentTerm['school_year'] ?? null) || pefCcSemester($currentTerm['semester'] ?? null) === null) {
            $temporalErrors[] = 'The current academic term cannot be verified.';
        } elseif (pefCcValidYear($year) && $semester !== null
            && [(int) substr($year, 0, 4), $semester] >= [(int) substr($currentTerm['school_year'], 0, 4), (int) $currentTerm['semester']]) {
            $temporalErrors[] = 'The evaluation term is current or future.';
        }
        $ids = [];
        $codes = [];
        foreach ($group['rows'] as $row) {
            if (!in_array($row['is_current'] ?? null, [0, '0'], true)) {
                $temporalErrors[] = 'Every row in a completed evaluation term must be historical.';
            }
            $subjectId = pefCcPositiveId($row['subject_id'] ?? null);
            $code = $row['code'] ?? null;
            if ($subjectId !== null) {
                if (isset($ids[$subjectId])) {
                    $integrityErrors[] = 'Duplicate subject records require review.';
                }
                $ids[$subjectId] = true;
            }
            if (is_string($code) && $code !== '') {
                if (isset($codes[$code])) {
                    $integrityErrors[] = 'Duplicate or ambiguous subject codes require review.';
                }
                $codes[$code] = true;
            }
            $integrityErrors = array_merge($integrityErrors, pefCcRecordErrors($row));
        }
        if (count($group['rows']) > 13) {
            $integrityErrors[] = 'This term exceeds the 13 recorded-subject capacity. No records can be omitted.';
        }
        $errors = array_values(array_unique(array_merge($integrityErrors, $temporalErrors)));
        $gwaEligible = $errors === [];
        $completionErrors = [];
        if ($year !== null && $semester !== null && pefCcValidYear($year)) {
            $completion = pefCcTermCompletion($records, (string) $year, (int) $semester);
            if (!$completion['is_complete'] && !in_array($completion['message'], $errors, true)) {
                $completionErrors[] = $completion['message'];
            }
        }
        $allErrors = array_values(array_unique(array_merge($errors, $completionErrors)));
        $choices[] = [
            'school_year' => $year,
            'semester' => $semester ?? $group['semester'],
            'label' => (is_scalar($year) ? (string) $year : 'Invalid school year') . ' · ' . ($semester === null ? 'Unsupported semester' : pefCcSemesterLabel($semester)),
            'count' => count($group['rows']),
            'eligible' => $allErrors === [],
            'gwa_eligible' => $gwaEligible,
            'integrity_errors' => array_values(array_unique($integrityErrors)),
            'temporal_errors' => array_values(array_unique($temporalErrors)),
            'errors' => $allErrors,
        ];
    }
    usort($choices, static fn(array $a, array $b): int => [(string) $b['school_year'], (string) $b['semester']] <=> [(string) $a['school_year'], (string) $a['semester']]);
    return $choices;
}

/** CC history is independent of the selected PEF term and never selects a best attempt. */
function pefCcBuildReferenceCc(array $records, array $reference): array
{
    $byCode = [];
    foreach ($records as $record) {
        if (is_string($record['code'] ?? null)) {
            $byCode[$record['code']][] = $record;
        }
    }
    $rows = [];
    foreach ($reference['rows'] ?? [] as $row) {
        $matches = $byCode[$row['code']] ?? [];
        usort($matches, static fn(array $a, array $b): int => [(string) ($a['school_year'] ?? ''), (string) ($a['semester'] ?? ''), (int) ($a['id'] ?? 0)] <=> [(string) ($b['school_year'] ?? ''), (string) ($b['semester'] ?? ''), (int) ($b['id'] ?? 0)]);
        $evidence = [];
        foreach ($matches as $match) {
            $source = [];
            foreach (['id', 'subject_id', 'school_year', 'semester', 'is_current', 'code', 'title', 'units', 'grade'] as $key) {
                $source[$key] = $match[$key] ?? null;
            }
            $evidence[] = $source;
        }

        $gradeDisplay = null;
        $remarks = null;
        $status = 'Need to Enroll';
        $internalState = '';
        $sourceRecord = null;
        $isCurrent = false;
        $isComplete = false;

        if (count($matches) === 0) {
            // Future unattempted curriculum subject
            $internalState = '';
            $status = 'Need to Enroll';
        } elseif (count($matches) > 1) {
            // Multiple attempts: requires evaluator review
            $internalState = 'REVIEW';
            $status = 'Need to Enroll';
        } else {
            $match = $matches[0];
            $sourceRecord = $match;
            $raw = $match['grade'] ?? null;
            $canonical = canonicalizeFinalGrade($raw);
            $isCurrent = in_array($match['is_current'] ?? null, [1, '1'], true);

            if (pefCcRecordErrors($match) !== [] || !in_array($match['is_current'] ?? null, [0, '0', 1, '1'], true)
                || !pefCcValidYear($match['school_year'] ?? null) || pefCcSemester($match['semester'] ?? null) === null) {
                $internalState = 'REVIEW';
                $status = 'Need to Enroll';
            } elseif ($isCurrent) {
                // Currently enrolled subject in incomplete term
                $internalState = 'CUR';
                $status = 'Need to Enroll';
                // Display verified final outcome if one exists; otherwise blank
                if ($canonical !== null && !in_array($canonical, ['INC', 'DO', 'DU', 'DRP', 'FA', 'UD'], true)) {
                    $gradeDisplay = is_numeric($canonical) ? (float) $canonical : $canonical;
                }
            } else {
                // Historical subject
                if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                    $internalState = 'NA';
                    $status = 'Need to Enroll';
                } elseif (is_bool($raw) || $canonical === null) {
                    $internalState = 'REVIEW';
                    $status = 'Need to Enroll';
                } elseif (isIncompleteFinalGrade($raw)) {
                    $internalState = 'INC';
                    $gradeDisplay = 'INC';
                    $status = 'Need to Enroll';
                } elseif ($canonical === LEGACY_FINAL_GRADE_STORED) {
                    // Failing finalized numeric grade (0.00)
                    $internalState = 'FAIL';
                    $gradeDisplay = 0.0;
                    $remarks = 'F';
                    $status = 'Need to Enroll';
                    $isComplete = true;
                } elseif (isPassingFinalGrade($raw)) {
                    // Passing finalized outcome (1.00 - 4.00 or 'P')
                    $internalState = 'CMP';
                    $gradeDisplay = is_numeric($canonical) ? (float) $canonical : $canonical;
                    $remarks = 'P';
                    $status = 'Taken';
                    $isComplete = true;
                } elseif (in_array($canonical, ['DO', 'DU', 'DRP', 'FA', 'UD'], true)) {
                    $internalState = 'DROPPED';
                    $gradeDisplay = $canonical;
                    $status = 'Need to Enroll';
                } else {
                    $internalState = 'REVIEW';
                    $status = 'Need to Enroll';
                }
            }
        }

        $row['grade_display'] = $gradeDisplay;
        $row['grade'] = $gradeDisplay;
        $row['remarks'] = $remarks;
        $row['status'] = $status;
        $row['internal_state'] = $internalState;
        $row['state'] = $internalState;
        $row['source_record'] = $sourceRecord;
        $row['is_current'] = $isCurrent;
        $row['is_complete'] = $isComplete;
        $row['evidence'] = $evidence;
        $rows[] = $row;
    }
    return ['label' => 'Reference / Requires Review', 'version' => (string) ($reference['version'] ?? 'v0.1'), 'rows' => $rows];
}

/**
 * Derives academic standing for a term based on Student Manual 2025 (Part II.D & II.H).
 * Non-board courses (BSIT) require GWA >= 1.75 for Good Standing (GS); otherwise Warning Status (WS).
 * If the term is incomplete or GWA is not calculable, standing is pending evaluator review.
 */
function pefCcDeriveTermStanding(?float $gwa, bool $isComplete): array
{
    if (!$isComplete || $gwa === null) {
        return [
            'code' => null,
            'label' => 'Pending evaluator review',
            'source' => 'pending_evaluator_review',
            'is_derived' => false,
        ];
    }
    // Student Manual 2025: Scale 4.00 (highest) to 1.00 (passing), 0.00 (fail).
    // GWA >= 1.75 meets the required GPA for non-board courses (80-81% equivalent).
    if ($gwa >= 1.75) {
        return [
            'code' => 'GS',
            'label' => 'Good Standing (GS)',
            'source' => 'derived_student_manual_2025_non_board',
            'is_derived' => true,
        ];
    }
    return [
        'code' => 'WS',
        'label' => 'Warning Status (WS)',
        'source' => 'derived_student_manual_2025_non_board',
        'is_derived' => true,
    ];
}

/**
 * Maps historical terms to the PEF Previous Academic Standing matrix (Rows 42-45, Cols C-F).
 *
 * Algorithm:
 * - Columns: Sem 1 -> sem1 (Col C), Sem 2 -> sem2 (Col D), Sem 3 -> sem3 (Col E), Summer -> summer (Col F).
 * - Rows (Year 1 = Row 42, Year 2 = Row 43, Year 3 = Row 44, Year 4 = Row 45):
 *   - For Regular students: deterministic chronological progression from cohort entry year.
 *     Cohort entry start year = current school year start - (current year level - 1).
 *     Term year level = term school year start - cohort entry start year + 1.
 *     If term year level is 1..4, it is mapped.
 *   - For Irregular students:
 *     Inspect curriculum year levels of recorded subjects for that term via catalogue.
 *     If all subjects in the term belong to the exact same curriculum year level, and no conflicting term
 *     occupies that year-semester slot, map to that year level.
 *     Otherwise, mark as ambiguous ('year_placement_unresolved'):
 *     - Matrix cell remains strictly blank (null).
 *     - Browser history displays term and GWA, labeled 'Year placement requires evaluator review'.
 * - Incomplete current semester, future terms, and unrepresented summer terms remain strictly blank (null).
 * - Numeric GWA is NEVER written to a matrix cell; only 'GS' or 'WS' when verified.
 */
function pefCcBuildHistoricalStandingMatrix(array $records, array $identity, array $catalog, array $currentTerm): array
{
    $matrix = [
        1 => ['year_level' => 1, 'year_label' => 'First Year', 'sem1' => null, 'sem2' => null, 'sem3' => null, 'summer' => null],
        2 => ['year_level' => 2, 'year_label' => 'Second Year', 'sem1' => null, 'sem2' => null, 'sem3' => null, 'summer' => null],
        3 => ['year_level' => 3, 'year_label' => 'Third Year', 'sem1' => null, 'sem2' => null, 'sem3' => null, 'summer' => null],
        4 => ['year_level' => 4, 'year_label' => 'Fourth Year', 'sem1' => null, 'sem2' => null, 'sem3' => null, 'summer' => null],
    ];

    // Group all records by term
    $terms = [];
    foreach ($records as $row) {
        $year = $row['school_year'] ?? null;
        $semester = pefCcSemester($row['semester'] ?? null);
        if ($year === null || $semester === null || !pefCcValidYear($year)) {
            continue;
        }
        $key = $year . ':' . $semester;
        $terms[$key]['school_year'] = $year;
        $terms[$key]['semester'] = $semester;
        $terms[$key]['rows'][] = $row;
    }

    $currentSy = $currentTerm['school_year'] ?? '';
    $currentSem = pefCcSemester($currentTerm['semester'] ?? null) ?? 1;
    $currentSyStart = (int) substr($currentSy, 0, 4);

    $isRegular = ($identity['status'] ?? '') === 'Regular';
    $currentYearLevel = (int) ($identity['year_level'] ?? 1);
    if ($currentYearLevel < 1 || $currentYearLevel > 4) {
        $currentYearLevel = 1;
    }
    $cohortStartYear = $currentSyStart - ($currentYearLevel - 1);

    // Build subject catalogue map: code -> year_level
    $catalogYearMap = [];
    foreach ($catalog as $s) {
        if (isset($s['code'], $s['year_level'])) {
            $catalogYearMap[$s['code']] = (int) $s['year_level'];
        }
    }

    $history = [];

    foreach ($terms as $term) {
        $year = $term['school_year'];
        $sem = $term['semester'];
        $termRows = $term['rows'];
        $termStartYear = (int) substr($year, 0, 4);

        $isCurrentOrFuture = ([$termStartYear, $sem] >= [$currentSyStart, $currentSem]);

        // Completion check
        $completion = pefCcTermCompletion($records, $year, $sem);
        $isComplete = $completion['is_complete'];

        // Compute GWA
        $gwaRows = [];
        $eligibleCount = 0;
        $eligibleUnits = 0;
        foreach ($termRows as $tr) {
            $ev = pefCcGradeEvidence($tr);
            if ($ev['gwa_reason'] === 'included') {
                $gwaRows[] = ['grade' => $tr['grade'], 'units' => $tr['units']];
                $eligibleCount++;
                $eligibleUnits += (int) $tr['units'];
            }
        }
        $termGwa = ($isComplete && $gwaRows !== []) ? computeWeightedGWA($gwaRows) : null;

        // Derive standing
        $standing = pefCcDeriveTermStanding($termGwa, $isComplete && !$isCurrentOrFuture);

        // Determine year level mapping
        $yearLevel = null;
        $placementStatus = 'unresolved';
        $placementLabel = 'Year placement requires evaluator review';

        if ($isRegular) {
            $derivedYl = $termStartYear - $cohortStartYear + 1;
            if ($derivedYl >= 1 && $derivedYl <= 4) {
                $yearLevel = $derivedYl;
                $placementStatus = 'mapped';
                $placementLabel = $matrix[$derivedYl]['year_label'];
            }
        } else {
            // Irregular: inspect subject curriculum year levels
            $subjectYears = [];
            foreach ($termRows as $tr) {
                $code = $tr['code'] ?? '';
                if (isset($catalogYearMap[$code])) {
                    $subjectYears[$catalogYearMap[$code]] = true;
                }
            }
            if (count($subjectYears) === 1) {
                $uniqueYl = array_key_first($subjectYears);
                if ($uniqueYl >= 1 && $uniqueYl <= 4) {
                    $yearLevel = $uniqueYl;
                    $placementStatus = 'mapped';
                    $placementLabel = $matrix[$uniqueYl]['year_label'];
                }
            }
        }

        // Determine column
        $colKey = match ($sem) {
            1 => 'sem1',
            2 => 'sem2',
            3 => 'sem3',
            default => 'summer',
        };

        // Populate matrix ONLY if:
        // 1. Not current or future
        // 2. Completed term
        // 3. Reliably mapped year level
        // 4. Standing code is 'GS' or 'WS'
        if (!$isCurrentOrFuture && $isComplete && $yearLevel !== null && in_array($standing['code'], ['GS', 'WS'], true)) {
            $matrix[$yearLevel][$colKey] = $standing['code'];
        }

        $displayStanding = match (true) {
            $isCurrentOrFuture || !$isComplete => 'Pending evaluator review',
            $placementStatus === 'unresolved' => 'Year placement requires evaluator review',
            default => $standing['label'],
        };

        $historyItem = [
            'school_year' => $year,
            'semester' => $sem,
            'label' => $year . ' · ' . pefCcSemesterLabel($sem),
            'count' => count($termRows),
            'eligible_count' => $eligibleCount,
            'eligible_units' => $eligibleUnits,
            'is_complete' => $isComplete,
            'is_current' => $isCurrentOrFuture,
            'gwa' => $termGwa,
            'gwa_display' => $termGwa !== null ? number_format($termGwa, 2) : 'Pending evaluator review',
            'standing' => $displayStanding,
            'standing_code' => $isCurrentOrFuture ? null : $standing['code'],
            'standing_source' => $isCurrentOrFuture ? 'pending_evaluator_review' : $standing['source'],
            'policy_version' => 'Student Manual 2025',
            'is_derived' => $standing['is_derived'],
            'year_level' => $yearLevel,
            'year_label' => $yearLevel !== null ? $matrix[$yearLevel]['year_label'] : null,
            'placement_status' => $placementStatus,
            'placement_label' => $placementLabel,
        ];
        $history[] = $historyItem;
    }

    // Sort history descending by school year and semester
    usort($history, static fn(array $a, array $b): int => [(string) $b['school_year'], (int) $b['semester']] <=> [(string) $a['school_year'], (int) $a['semester']]);

    return [
        'matrix' => array_values($matrix),
        'history' => $history,
    ];
}

function pefCcBuildViewModel(array $context, array $input, array $reference, array $config, ?array $currentTerm = null): PefCcViewModel
{
    $planningInput = pefCcNormalizeInputs($input, $config);
    $currentTerm ??= getCurrentTerm();
    $policy = pefCcPlanningPolicy($context, $planningInput, $currentTerm);
    $errors = $policy['errors'];
    $fieldErrors = $policy['field_errors'];
    $reviewConcerns = $policy['review_concerns'] ?? [];
    if ($policy['mode'] === 'irregular') {
        $reviewConcerns[] = $policy['section_notice'];
    }
    if (in_array($policy['mode'], ['regular', 'review_copy'], true)) {
        if ($planningInput['subject_ids'] !== $policy['nominal_ids']) {
            $errors[] = 'Regular students must retain the complete nominal subject plan. Review the updated list and preview again.';
            $fieldErrors['subject_ids'] = 'The nominal subject plan is read-only; insertion, deletion and replacement are not permitted.';
        }
        $planningInput['subject_ids'] = $policy['nominal_ids'];
    } elseif ($policy['mode'] !== 'irregular') {
        $planningInput['subject_ids'] = [];
    }
    if (!pefCcSectionIsApproved($policy, $planningInput['section'], $planningInput)) {
        $errors[] = PEF_CC_SECTION_MESSAGE;
        $fieldErrors['section'] = PEF_CC_SECTION_MESSAGE;
    }
    $sourceIdentity = $context['identity'] ?? [];
    $identity = [];
    foreach (['student_number', 'name', 'college', 'program'] as $key) {
        $identity[$key] = $sourceIdentity[$key] ?? null;
        if (!pefCcSafeText($identity[$key], true)) {
            $errors[] = 'Student identity is incomplete or contains unsupported text. Requires record review.';
        }
    }
    // The form has fixed CCS labels. A name display conversion is not a stored
    // identity edit; the exact same string is consumed by HTML and XLSX.
    if (is_string($identity['name']) && pefCcSafeText($identity['name'], true)) {
        $identity['name'] = mb_strtoupper($identity['name'], 'UTF-8');
    }
    if (!is_string($identity['college']) || strtoupper(trim($identity['college'])) !== 'CCS'
        || !is_string($identity['program']) || !isValidCourse($identity['program'])) {
        $errors[] = 'The fixed CCS/BSIT template does not match the recorded college or program. Requires record review.';
    }
    $identity['id'] = pefCcPositiveId($sourceIdentity['id'] ?? null);
    $identity['role'] = $sourceIdentity['role'] ?? null;
    $identity['is_active'] = in_array($sourceIdentity['is_active'] ?? null, [1, '1'], true) ? 1 : 0;
    foreach (['year_level', 'section', 'status', 'record_status'] as $key) {
        $identity[$key] = $sourceIdentity[$key] ?? null;
    }
    if ($identity['id'] === null || $identity['role'] !== 'student' || $identity['is_active'] !== 1) {
        $errors[] = 'An active authenticated Student identity is required.';
    }
    $records = $context['records'] ?? [];
    $recorded = [];
    foreach ($records as $row) {
        if (($row['school_year'] ?? null) === $planningInput['evaluation_year'] && pefCcSemester($row['semester'] ?? null) === $planningInput['evaluation_semester']) {
            $recorded[] = $row;
        }
    }
    usort($recorded, static fn(array $a, array $b): int => [(string) ($a['code'] ?? ''), (int) ($a['subject_id'] ?? 0), (int) ($a['id'] ?? 0)] <=> [(string) ($b['code'] ?? ''), (int) ($b['subject_id'] ?? 0), (int) ($b['id'] ?? 0)]);
    if ($recorded === []) {
        $errors[] = 'No recorded subjects exist for this completed evaluation term.';
        $fieldErrors['evaluation_year'] = 'Select a recorded completed evaluation term.';
    }
    // Find matching choice in pefCcTermChoices
    $evalChoice = null;
    $choices = pefCcTermChoices($records, $currentTerm);
    foreach ($choices as $choice) {
        if ($choice['school_year'] === $planningInput['evaluation_year'] && $choice['semester'] === $planningInput['evaluation_semester']) {
            $evalChoice = $choice;
            break;
        }
    }
    // Enforce term parity; incomplete outcomes qualify the copy below.
    $targetYear = $policy['target_year'] ?? $planningInput['target_year'];
    $targetSemester = $policy['target_semester'] ?? $planningInput['target_semester'];
    $requiredPred = is_string($targetYear) && is_int($targetSemester)
        ? pefCcPredecessorTerm($targetYear, $targetSemester)
        : null;

    $isPredMatch = ($requiredPred !== null
        && $planningInput['evaluation_year'] === $requiredPred['school_year']
        && $planningInput['evaluation_semester'] === $requiredPred['semester']);

    $isPredCurrent = ($requiredPred !== null
        && pefCcValidYear($currentTerm['school_year'] ?? null) && pefCcSemester($currentTerm['semester'] ?? null) !== null
        && $requiredPred['school_year'] === $currentTerm['school_year']
        && $requiredPred['semester'] === pefCcSemester($currentTerm['semester']));

    $predComp = ($requiredPred !== null)
        ? pefCcTermCompletion($records, $requiredPred['school_year'], $requiredPred['semester'])
        : ['is_complete' => false, 'reason' => 'missing_records', 'message' => 'The target academic term is invalid.'];

    if ($requiredPred !== null && !$isPredMatch) {
        $errors[] = 'The evaluation term must be the immediate predecessor ('
            . $requiredPred['school_year'] . ' ' . pefCcSemesterLabel($requiredPred['semester'])
            . ') of the target planning term.';
        $fieldErrors['evaluation_year'] = 'Select the immediate predecessor term.';
    }

    if ($isPredMatch && $predComp['reason'] === 'missing_records') {
        $errors[] = 'No recorded subjects exist for the immediate predecessor term ('
            . $requiredPred['school_year'] . ' ' . pefCcSemesterLabel($requiredPred['semester']) . ').';
        $fieldErrors['evaluation_year'] = 'No recorded subjects exist for this term.';
    }

    $isProvisionalCandidate = ($isPredMatch && !$predComp['is_complete']
        && in_array($predComp['reason'], ['incomplete_grades', 'unresolved_outcomes'], true));

    $evaluationComplete = pefCcTermCompletion($records, $planningInput['evaluation_year'], $planningInput['evaluation_semester'])['is_complete'];
    $termGwaEligible = false;
    if ($evalChoice !== null) {
        $errors = array_merge($errors, $evalChoice['integrity_errors']);
        if ($isPredMatch && $isPredCurrent) {
            foreach ($recorded as $row) {
                if (!in_array($row['is_current'] ?? null, [0, '0', 1, '1'], true)) {
                    $errors[] = 'The evaluation term contains an invalid current-record marker.';
                }
            }
            $termGwaEligible = $evaluationComplete && $evalChoice['integrity_errors'] === [];
        } else {
            $errors = array_merge($errors, $evalChoice['temporal_errors']);
            $termGwaEligible = $evaluationComplete && $evalChoice['gwa_eligible'];
        }
    }
    $coverage = ['recorded_count' => count($recorded), 'eligible_count' => 0, 'eligible_units' => 0, 'excluded' => []];
    $gwaRows = [];
    $recordedRows = [];
    foreach ($recorded as $row) {
        $safeRow = [];
        foreach (['id', 'subject_id', 'school_year', 'semester', 'is_current', 'code', 'title', 'units', 'grade'] as $key) {
            $safeRow[$key] = $row[$key] ?? null;
        }
        $evidence = pefCcGradeEvidence($row);
        $safeRow = array_merge($safeRow, $evidence);
        $recordedRows[] = $safeRow;
        $gwaRows[] = ['grade' => $row['grade'] ?? null, 'units' => $row['units'] ?? null];
        if ($evidence['gwa_reason'] === 'included') {
            $coverage['eligible_count']++;
            $coverage['eligible_units'] += (int) $row['units'];
        } else {
            $reason = $evidence['gwa_reason'];
            $coverage['excluded'][$reason] = ($coverage['excluded'][$reason] ?? 0) + 1;
        }
    }
    ksort($coverage['excluded']);

    $catalogById = [];
    $catalogByCode = [];
    foreach ($context['catalog'] ?? [] as $subject) {
        $id = pefCcPositiveId($subject['id'] ?? null);
        if ($id !== null) {
            $catalogById[$id][] = $subject;
            if (is_string($subject['code'] ?? null)) {
                $catalogByCode[$subject['code']][] = $subject;
            }
        }
    }
    $proposed = [];
    foreach ($planningInput['subject_ids'] as $id) {
        $matches = $catalogById[$id] ?? [];
        if (count($matches) !== 1 || preg_match('/^(CY|DS)/i', (string) ($matches[0]['code'] ?? '')) === 1) {
            $errors[] = 'A proposed subject is unknown or ambiguous in the catalogue.';
            $fieldErrors['subject_ids'] = 'Choose unambiguous subjects from the current catalogue.';
            continue;
        }
        $subject = $matches[0];
        if (!pefCcSafeText($subject['code'] ?? null, true) || !pefCcSafeText($subject['title'] ?? null, true)
            || !pefCcUsableUnits($subject['units'] ?? null) || (float) $subject['units'] !== (float) (int) $subject['units']
            || count($catalogByCode[$subject['code']] ?? []) !== 1) {
            $errors[] = 'A proposed subject has ambiguous or unusable catalogue data.';
            $fieldErrors['subject_ids'] = 'The selected catalogue record requires review.';
        }
        $proposed[] = ['id' => $id, 'code' => $subject['code'] ?? null, 'title' => $subject['title'] ?? null, 'units' => $subject['units'] ?? null];
    }
    if (count($recordedRows) > (int) $config['max_recorded']) {
        $errors[] = 'This term exceeds the 13 recorded-subject capacity. No records can be omitted.';
    }
    if (count($planningInput['subject_ids']) > (int) $config['max_proposed']) {
        $errors[] = 'Proposed subjects exceed the 12-row capacity. Remove subjects before previewing.';
        $fieldErrors['subject_ids'] = 'Choose no more than twelve proposed subjects.';
    }
    // Unavailable input is explained by membership, not by blaming a student for a bad configured option.
    $printErrors = pefCcPrintableErrors($identity, $recordedRows, $proposed,
        pefCcSectionIsApproved($policy, $planningInput['section']) ? $planningInput['section'] : '', $config);
    if ($printErrors !== []) {
        $errors[] = PEF_CC_PRINT_LIMIT_MESSAGE;
        $errors = array_merge($errors, array_values($printErrors));
        $fieldErrors = array_replace($fieldErrors, $printErrors);
    }
    $errors = array_values(array_unique($errors));
    // Completion gates the selected-term calculation; no partial or substitute GWA.
    $gwa = $termGwaEligible ? computeWeightedGWA($gwaRows) : null;
    if ($evaluationComplete && $gwa === null) {
        $reviewConcerns[] = 'The selected-term GWA is unavailable from the verified outcomes. Academic evaluator review is required.';
    }
    $reviewConcerns = array_values(array_unique($reviewConcerns));
    $mode = match (true) {
        $errors !== [] => 'invalid',
        $reviewConcerns !== [] => 'review',
        $isProvisionalCandidate => 'provisional',
        $isPredMatch && $evaluationComplete && $gwa !== null => 'normal',
        default => 'invalid',
    };
    $exportable = $errors === [] && in_array($mode, ['normal', 'provisional', 'review'], true);
    $gwaState = match (true) {
        !$evaluationComplete => 'pending',
        $gwa !== null => 'available',
        !$termGwaEligible => 'requires_review',
        default => 'unavailable',
    };
    $historicalMatrixData = pefCcBuildHistoricalStandingMatrix($records, $identity, $context['catalog'] ?? [], $currentTerm);
    $history = $historicalMatrixData['history'];
    $historicalStanding = $historicalMatrixData['matrix'];
    $evalStanding = ($mode === 'normal' && $gwa !== null) ? pefCcDeriveTermStanding($gwa, true) : ['label' => 'Pending evaluator review', 'code' => null];

    return new PefCcViewModel([
        'mode' => $mode,
        'identity' => $identity,
        'policy' => $policy,
        'evaluation' => ['school_year' => $planningInput['evaluation_year'], 'semester' => $planningInput['evaluation_semester'], 'label' => pefCcSemesterLabel($planningInput['evaluation_semester'])],
        'evaluation_complete' => $evaluationComplete,
        'recorded' => $recordedRows,
        'gwa' => $gwa,
        'gwa_state' => $gwaState,
        'academic_standing' => $evalStanding['label'],
        'academic_standing_code' => $evalStanding['code'],
        'predecessor_term' => $requiredPred,
        'historical_standing' => $historicalStanding,
        'history' => $history,
        'coverage' => $coverage,
        'planning' => ['target_year' => $planningInput['target_year'], 'target_semester' => $planningInput['target_semester'], 'section' => $planningInput['section'], 'subject_ids' => $planningInput['subject_ids']],
        'proposed' => $proposed,
        'recorded_count' => count($recordedRows), 'proposed_count' => count($planningInput['subject_ids']),
        'recorded_capacity' => (int) $config['max_recorded'], 'proposed_capacity' => (int) $config['max_proposed'],
        'cc' => pefCcBuildReferenceCc($records, $reference),
        'disclosure' => $config['disclosure'], 'prototype_disclosure' => $config['prototype_disclosure'],
        'template_version' => $config['template_version'], 'template_hash' => $config['template_sha256'],
        'print_contract' => $config['printable'],
        'exportable' => $exportable,
        'errors' => $errors, 'field_errors' => $fieldErrors, 'review_concerns' => $reviewConcerns,
    ]);
}
