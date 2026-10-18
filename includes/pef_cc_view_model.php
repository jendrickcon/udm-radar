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

function pefCcSemesterLabel(int $semester): string
{
    return $semester === 1 ? 'First Semester' : 'Second Semester';
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
        $errors = [];
        if (!pefCcValidYear($year) || $semester === null) {
            $errors[] = 'The evaluation term is invalid.';
        }
        if (!pefCcValidYear($currentTerm['school_year'] ?? null) || pefCcSemester($currentTerm['semester'] ?? null) === null) {
            $errors[] = 'The current academic term cannot be verified.';
        } elseif (pefCcValidYear($year) && $semester !== null
            && [(int) substr($year, 0, 4), $semester] >= [(int) substr($currentTerm['school_year'], 0, 4), (int) $currentTerm['semester']]) {
            $errors[] = 'The evaluation term is current or future.';
        }
        $ids = [];
        $codes = [];
        foreach ($group['rows'] as $row) {
            if (!in_array($row['is_current'] ?? null, [0, '0'], true)) {
                $errors[] = 'Every row in a completed evaluation term must be historical.';
            }
            $subjectId = pefCcPositiveId($row['subject_id'] ?? null);
            $code = $row['code'] ?? null;
            if ($subjectId !== null) {
                if (isset($ids[$subjectId])) {
                    $errors[] = 'Duplicate subject records require review.';
                }
                $ids[$subjectId] = true;
            }
            if (is_string($code) && $code !== '') {
                if (isset($codes[$code])) {
                    $errors[] = 'Duplicate or ambiguous subject codes require review.';
                }
                $codes[$code] = true;
            }
            $errors = array_merge($errors, pefCcRecordErrors($row));
        }
        // Capacity affects the form, not whether sound historical evidence has
        // a reviewable term GWA. Source/completion conflicts invalidate it.
        $gwaEligible = $errors === [];
        if (count($group['rows']) > 13) {
            $errors[] = 'This term exceeds the 13 recorded-subject capacity. No records can be omitted.';
        }
        $errors = array_values(array_unique($errors));
        $choices[] = [
            'school_year' => $year,
            'semester' => $semester ?? $group['semester'],
            'label' => (is_scalar($year) ? (string) $year : 'Invalid school year') . ' · ' . ($semester === null ? 'Unsupported semester' : pefCcSemesterLabel($semester)),
            'count' => count($group['rows']), 'eligible' => $errors === [], 'gwa_eligible' => $gwaEligible, 'errors' => $errors,
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
        $grade = null;
        $state = '';
        if (count($matches) > 1) {
            $state = 'REVIEW';
        } elseif (count($matches) === 1) {
            $match = $matches[0];
            $raw = $match['grade'] ?? null;
            $canonical = canonicalizeFinalGrade($raw);
            if (pefCcRecordErrors($match) !== [] || !in_array($match['is_current'] ?? null, [0, '0', 1, '1'], true)
                || !pefCcValidYear($match['school_year'] ?? null) || pefCcSemester($match['semester'] ?? null) === null) {
                $state = 'REVIEW';
            } elseif (in_array($match['is_current'], [1, '1'], true)) {
                $state = 'CUR';
            } else {
                $state = match (true) {
                    $raw === null, is_string($raw) && trim($raw) === '' => 'NA',
                    is_bool($raw), $canonical === null => 'REVIEW',
                    isIncompleteFinalGrade($raw) => 'INC',
                    $canonical === LEGACY_FINAL_GRADE_STORED => 'FAIL',
                    isPassingFinalGrade($raw) => 'CMP',
                    default => 'REVIEW',
                };
            }
            // A current/unknown source never acquires a manufactured final mark.
            if (!in_array($state, ['REVIEW', 'CUR', 'NA'], true)) {
                $grade = $canonical !== null && is_numeric($canonical) ? (float) $canonical : $canonical;
            }
        }
        $row['grade'] = $grade;
        $row['state'] = $state;
        // Retain all attempts for the signed digest, even when the visible
        // annotation is CUR/REVIEW and deliberately chooses no final grade.
        $row['evidence'] = $evidence;
        $rows[] = $row;
    }
    return ['label' => 'Reference / Requires Review', 'version' => (string) ($reference['version'] ?? 'v0.1'), 'rows' => $rows];
}

function pefCcBuildViewModel(array $context, array $input, array $reference, array $config, ?array $currentTerm = null): PefCcViewModel
{
    $planningInput = pefCcNormalizeInputs($input, $config);
    $currentTerm ??= getCurrentTerm();
    $policy = pefCcPlanningPolicy($context, $planningInput, $currentTerm);
    $errors = $policy['errors'];
    $fieldErrors = $policy['field_errors'];
    if ($policy['mode'] === 'regular') {
        if ($planningInput['subject_ids'] !== $policy['nominal_ids']) {
            $errors[] = 'Regular students must retain the complete nominal subject plan. Review the updated list and preview again.';
            $fieldErrors['subject_ids'] = 'The nominal subject plan is read-only; insertion, deletion and replacement are not permitted.';
        }
        $planningInput['subject_ids'] = $policy['nominal_ids'];
    } elseif ($policy['mode'] !== 'irregular') {
        $planningInput['subject_ids'] = [];
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
    $termGwaEligible = false;
    foreach (pefCcTermChoices($records, $currentTerm) as $choice) {
        if ($choice['school_year'] === $planningInput['evaluation_year'] && $choice['semester'] === $planningInput['evaluation_semester']) {
            $errors = array_merge($errors, $choice['errors']);
            $termGwaEligible = $choice['gwa_eligible'];
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
    $gwa = $termGwaEligible ? computeWeightedGWA($gwaRows) : null;
    return new PefCcViewModel([
        'identity' => $identity,
        'policy' => $policy,
        'evaluation' => ['school_year' => $planningInput['evaluation_year'], 'semester' => $planningInput['evaluation_semester'], 'label' => pefCcSemesterLabel($planningInput['evaluation_semester'])],
        'recorded' => $recordedRows,
        'gwa' => $gwa,
        'gwa_state' => !$termGwaEligible ? 'requires_review' : ($gwa === null ? 'unavailable' : 'available'),
        'coverage' => $coverage,
        'planning' => ['target_year' => $planningInput['target_year'], 'target_semester' => $planningInput['target_semester'], 'section' => $planningInput['section'], 'subject_ids' => $planningInput['subject_ids']],
        'proposed' => $proposed,
        'recorded_count' => count($recordedRows), 'proposed_count' => count($planningInput['subject_ids']),
        'recorded_capacity' => (int) $config['max_recorded'], 'proposed_capacity' => (int) $config['max_proposed'],
        'cc' => pefCcBuildReferenceCc($records, $reference),
        'disclosure' => $config['disclosure'], 'prototype_disclosure' => $config['prototype_disclosure'],
        'template_version' => $config['template_version'], 'template_hash' => $config['template_sha256'],
        'print_contract' => $config['printable'],
        'exportable' => $errors === [], 'errors' => $errors, 'field_errors' => $fieldErrors,
    ]);
}
