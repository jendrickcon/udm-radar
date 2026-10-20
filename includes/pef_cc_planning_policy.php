<?php
declare(strict_types=1);

const PEF_CC_PLANNING_POLICY_VERSION = 'sd-2022-2023-wp1.5';
const PEF_CC_SECTION_MESSAGE = 'The suggested section is not available for this planning context. Choose one of the approved section options before previewing or downloading.';
const PEF_CC_NOMINAL_REVIEW = 'The nominal subject plan could not be resolved from the available curriculum record. Academic evaluator review is required.';
const PEF_CC_LEGACY_REVIEW = 'This historical curriculum track requires academic evaluator review and is not included in the current Software Development planning workflow.';
const PEF_CC_TERM_REVIEW = 'The student’s current academic term could not be resolved consistently. Academic evaluator review is required before generating a nominal subject plan.';

if (!function_exists('pefCcSemesterLabel')) {
    function pefCcSemesterLabel(int $semester): string
    {
        return $semester === 1 ? 'First Semester' : 'Second Semester';
    }
}

/** Immediate predecessor term of any target term. */
function pefCcPredecessorTerm(string $targetYear, int $targetSemester): ?array
{
    if (!pefCcValidYear($targetYear) || !in_array($targetSemester, [1, 2], true)) {
        return null;
    }
    if ($targetSemester === 2) {
        return ['school_year' => $targetYear, 'semester' => 1];
    }
    $start = (int) substr($targetYear, 0, 4);
    $end = (int) substr($targetYear, 5, 4);
    return ['school_year' => ($start - 1) . '-' . ($end - 1), 'semester' => 2];
}

/** Immediate successor term of any term. */
function pefCcNextTerm(string $schoolYear, int $semester): ?array
{
    if (!pefCcValidYear($schoolYear) || !in_array($semester, [1, 2], true)) {
        return null;
    }
    if ($semester === 1) {
        return ['school_year' => $schoolYear, 'semester' => 2];
    }
    $start = (int) substr($schoolYear, 0, 4);
    $end = (int) substr($schoolYear, 5, 4);
    return ['school_year' => ($start + 1) . '-' . ($end + 1), 'semester' => 1];
}

/**
 * Term completion and record finality evaluation.
 * Separates:
 * A. Record finality: canonical final grade recorded (non-null, non-blank).
 * B. Passing status: 1.00..4.00 and P pass; 0.00, FA, UD fail; INC is unresolved.
 * C. Term GWA eligibility: numeric grades calculate weighted GWA; 0.00 and textual outcomes excluded.
 * D. GS/WS standing: unverified institutional calculation; displayed as pending evaluator review.
 * E. Planning uses the identified predecessor; incomplete grades qualify a copy, not prohibit it.
 */
function pefCcTermCompletion(array $records, string $schoolYear, int $semester): array
{
    $termRows = [];
    foreach ($records as $row) {
        if (($row['school_year'] ?? null) === $schoolYear && pefCcSemester($row['semester'] ?? null) === $semester) {
            $termRows[] = $row;
        }
    }
    if ($termRows === []) {
        return [
            'is_complete' => false,
            'reason' => 'missing_records',
            'message' => 'No recorded subjects exist for this term.',
            'rows' => [],
        ];
    }
    $incomplete = [];
    $unresolved = [];
    foreach ($termRows as $row) {
        $grade = $row['grade'] ?? null;
        if ($grade === null) {
            $incomplete[] = 'null';
        } elseif (is_string($grade) && trim($grade) === '') {
            $incomplete[] = 'blank';
        } elseif (isIncompleteFinalGrade($grade)) {
            $unresolved[] = 'inc';
        } elseif (canonicalizeFinalGrade($grade) === null) {
            $unresolved[] = 'unrecognized';
        } elseif (!pefCcUsableUnits($row['units'] ?? null)) {
            $unresolved[] = 'invalid_units';
        }
    }
    if ($incomplete !== []) {
        return [
            'is_complete' => false,
            'reason' => 'incomplete_grades',
            'message' => 'The term contains incomplete or unrecorded final grades.',
            'rows' => $termRows,
        ];
    }
    if ($unresolved !== []) {
        return [
            'is_complete' => false,
            'reason' => 'unresolved_outcomes',
            'message' => 'The term contains unresolved Incomplete (INC) grades or unconfirmed outcomes.',
            'rows' => $termRows,
        ];
    }
    return [
        'is_complete' => true,
        'reason' => 'completed',
        'message' => 'Term records are complete.',
        'rows' => $termRows,
    ];
}

/** Trusted local configuration only. Never accept an upload or request path. */
function pefCcCurriculum(): array
{
    $file = dirname(__DIR__) . '/config/bsit_software_development_curriculum_2022_2023.php';
    $mapping = require $file;
    return ['mapping' => $mapping, 'sha256' => hash_file('sha256', $file)];
}

function pefCcSupportedSection(mixed $value): ?array
{
    // Current repository convention: IT-<nominal year 1..4><block 1..9>.
    if (!is_string($value) || preg_match('/\AIT-([1-4])([1-9])\z/D', $value, $m) !== 1) {
        return null;
    }
    return ['year_level' => (int) $m[1], 'block' => (int) $m[2]];
}

/** Membership is generated from server records; spelling is never repaired. */
function pefCcSectionEvidenceMatches(array $row, string $year, int $semester): bool
{
    $parsed = pefCcSupportedSection($row['section'] ?? null);
    return $parsed !== null && in_array($row['year_level'] ?? null, [$parsed['year_level'], (string) $parsed['year_level']], true)
        && ($row['school_year'] ?? null) === $year && pefCcSemester($row['semester'] ?? null) === $semester
        && ($row['source'] ?? null) === 'active_sd_current_records'
        && is_string($row['evidence_sha256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/D', $row['evidence_sha256']) === 1;
}

function pefCcSectionIsApproved(array $policy, mixed $section, ?array $planning = null): bool
{
    if (!is_string($section) || !in_array($section, $policy['section_options'] ?? [], true)) {
        return false;
    }
    $targetYear = $policy['target_year'] ?? null;
    $targetSemester = pefCcSemester($policy['target_semester'] ?? null);
    if (!pefCcValidYear($targetYear) || $targetSemester === null
        || ($planning !== null && (($planning['target_year'] ?? null) !== $targetYear
            || pefCcSemester($planning['target_semester'] ?? null) !== $targetSemester))) {
        return false;
    }
    if (($policy['mode'] ?? null) === 'review_copy') {
        $evaluation = $policy['review_evaluation'] ?? [];
        $predecessor = pefCcPredecessorTerm($targetYear, $targetSemester);
        return $section === '' && ($policy['section_options'] ?? []) === ['']
            && ($evaluation['source'] ?? null) === 'recorded_evaluation_term'
            && ($evaluation['school_year'] ?? null) === ($predecessor['school_year'] ?? null)
            && ($evaluation['semester'] ?? null) === ($predecessor['semester'] ?? null)
            && ($policy['review_concerns'] ?? []) !== [];
    }
    $hasEvidence = static function () use ($policy, $section, $targetYear, $targetSemester): bool {
        foreach ($policy['section_evidence'] ?? [] as $row) {
            if (($row['section'] ?? null) === $section && pefCcSectionEvidenceMatches($row, $targetYear, $targetSemester)) {
                return true;
            }
        }
        return false;
    };
    if (($policy['mode'] ?? null) === 'irregular' && ($policy['recorded_status'] ?? null) === 'Irregular') {
        return $section === '' || ($section === 'IT-IRREG' && ($policy['recorded_section'] ?? null) === 'IT-IRREG')
            || (pefCcSupportedSection($section) !== null && $hasEvidence());
    }
    $recorded = pefCcSupportedSection($policy['recorded_section'] ?? null);
    $selected = pefCcSupportedSection($section);
    $anchor = $policy['profile_term'] ?? [];
    $semester = pefCcSemester($anchor['semester'] ?? null);
    $year = filter_var($policy['recorded_year_level'] ?? null, FILTER_VALIDATE_INT);
    if (($policy['mode'] ?? null) !== 'regular' || ($policy['recorded_status'] ?? null) !== 'Regular'
        || $recorded === null || $selected === null || is_bool($policy['recorded_year_level'] ?? null)
        || !in_array($year, [1, 2, 3, 4], true) || $recorded['year_level'] !== $year
        || ($anchor['year_level'] ?? null) !== $year || empty($anchor['records'])
        || ($anchor['source'] ?? null) !== 'coherent_recorded_current_term'
        || !pefCcValidYear($anchor['school_year'] ?? null) || $semester === null) {
        return false;
    }
    $expectedYear = $semester === 1 ? $anchor['school_year']
        : ((int) substr($anchor['school_year'], 0, 4) + 1) . '-' . ((int) substr($anchor['school_year'], 5, 4) + 1);
    $nominalYear = $year + ($semester === 2 ? 1 : 0);
    if ($targetYear !== $expectedYear || $targetSemester !== ($semester === 1 ? 2 : 1)
        || ($policy['target_year_level'] ?? null) !== $nominalYear || $selected['year_level'] !== $nominalYear) {
        return false;
    }
    return $semester === 1
        ? ($policy['horizon'] ?? null) === 'same_school_year' && $section === $policy['recorded_section']
        : ($policy['horizon'] ?? null) === 'next_school_year' && abs($selected['block'] - $recorded['block']) <= 1 && $hasEvidence();
}

/** Defaults for the initial GET only; submitted POST values must be validated unchanged. */
function pefCcInitialPlanningInputs(array $context, array $input, array $currentTerm): array
{
    $policy = pefCcPlanningPolicy($context, $input, $currentTerm);
    if ($policy['predecessor_term'] !== null) {
        $input['evaluation_year'] = (string) $policy['predecessor_term']['school_year'];
        $input['evaluation_semester'] = (string) $policy['predecessor_term']['semester'];
    }
    if (in_array($policy['mode'], ['regular', 'review_copy'], true)) {
        // GET defaults are server-derived; POST values are checked unchanged.
        $input['target_year'] = $policy['target_year'] ?? $input['target_year'];
        $input['target_semester'] = $policy['target_semester'] ?? $input['target_semester'];
        $input['section'] = $policy['section_default'];
        $input['subject_ids'] = $policy['nominal_ids'];
    }
    return $input;
}

/** A controlled copy may omit unresolved planning fields, never authorize arbitrary input. */
function pefCcControlledReviewPolicy(array $policy, array $context, array $input, array $currentTerm, string $concern): array
{
    $evaluationYear = $input['evaluation_year'] ?? null;
    $evaluationSemester = pefCcSemester($input['evaluation_semester'] ?? null);
    $choice = null;
    foreach (pefCcTermChoices($context['records'] ?? [], $currentTerm) as $candidate) {
        if ($candidate['school_year'] === $evaluationYear && $candidate['semester'] === $evaluationSemester) {
            $choice = $candidate;
            break;
        }
    }
    $isCurrent = pefCcValidYear($currentTerm['school_year'] ?? null)
        && pefCcSemester($currentTerm['semester'] ?? null) !== null
        && $evaluationYear === $currentTerm['school_year']
        && $evaluationSemester === pefCcSemester($currentTerm['semester']);
    if ($choice === null || $choice['integrity_errors'] !== []
        || (!$isCurrent && !$choice['gwa_eligible'])) {
        $policy['errors'][] = $concern;
        return $policy;
    }
    $target = $policy['target_year'] !== null && $policy['target_semester'] !== null
        ? ['school_year' => $policy['target_year'], 'semester' => $policy['target_semester']]
        : pefCcNextTerm($evaluationYear, $evaluationSemester);
    $predecessor = $target === null ? null : pefCcPredecessorTerm($target['school_year'], $target['semester']);
    if ($predecessor === null || $predecessor['school_year'] !== $evaluationYear
        || $predecessor['semester'] !== $evaluationSemester) {
        $policy['errors'][] = 'The evaluation term must be the immediate predecessor of the verified target term.';
        return $policy;
    }
    $policy['mode'] = 'review_copy';
    $policy['status'] = 'evaluator_review_required';
    $policy['review_concerns'][] = $concern;
    $policy['review_evaluation'] = ['school_year' => $evaluationYear, 'semester' => $evaluationSemester,
        'source' => 'recorded_evaluation_term'];
    $policy['target_year'] = $target['school_year'];
    $policy['target_semester'] = $target['semester'];
    $policy['predecessor_term'] = $predecessor;
    $completion = pefCcTermCompletion($context['records'] ?? [], $evaluationYear, $evaluationSemester);
    $policy['predecessor_status'] = $completion;
    $policy['predecessor_complete'] = $completion['is_complete'];
    $policy['section_options'] = [''];
    $policy['section_default'] = '';
    $policy['section_evidence'] = [];
    $policy['section_notice'] = 'The proposed section is left blank for academic evaluator review.';
    foreach (['target_year' => $target['school_year'], 'target_semester' => $target['semester']] as $field => $expected) {
        $actual = $field === 'target_semester' ? pefCcSemester($input[$field] ?? null) : ($input[$field] ?? null);
        if ($actual !== $expected) {
            $policy['errors'][] = 'The target term must match the verified evaluation-term successor. Refresh the planning preview.';
            $policy['field_errors'][$field] = 'Use the verified target term.';
        }
    }
    if (($input['section'] ?? '') !== '') {
        $policy['errors'][] = PEF_CC_SECTION_MESSAGE;
        $policy['field_errors']['section'] = PEF_CC_SECTION_MESSAGE;
    }
    return $policy;
}

/** A coherent own current-term record set corroborates the current profile year. */
function pefCcProfileTerm(array $records, array $currentTerm, int $year, array $mapping, array $catalog): ?array
{
    $terms = [];
    $evidence = [];
    $recordIds = $subjectIds = $codes = [];
    $semester = pefCcSemester($currentTerm['semester'] ?? null);
    if (!pefCcValidYear($currentTerm['school_year'] ?? null) || $semester === null) {
        return null;
    }
    $curriculumTerms = array_values(array_filter($mapping['terms'] ?? [], static fn(array $t): bool =>
        ($t['year_level'] ?? null) === $year && ($t['semester'] ?? null) === $semester));
    if (count($curriculumTerms) !== 1 || empty($curriculumTerms[0]['subjects'])) {
        return null;
    }
    foreach ($records as $row) {
        if (!in_array($row['is_current'] ?? null, [0, '0', 1, '1'], true)) {
            return null;
        }
        if (!in_array($row['is_current'] ?? null, [1, '1'], true)) {
            continue;
        }
        if (!pefCcValidYear($row['school_year'] ?? null) || pefCcSemester($row['semester'] ?? null) === null) {
            return null;
        }
        $id = pefCcPositiveId($row['id'] ?? null);
        $subjectId = pefCcPositiveId($row['subject_id'] ?? null);
        $code = $row['code'] ?? null;
        $nominal = array_values(array_filter($curriculumTerms[0]['subjects'], static fn(array $s): bool => ($s['code'] ?? null) === $code));
        $catalogMatches = array_values(array_filter($catalog, static fn(array $s): bool => pefCcPositiveId($s['id'] ?? null) === $subjectId));
        $codeMatches = array_values(array_filter($catalog, static fn(array $s): bool => ($s['code'] ?? null) === $code));
        if ($id === null || $subjectId === null || !is_string($code)
            || isset($recordIds[$id]) || isset($subjectIds[$subjectId]) || isset($codes[$code])
            || count($nominal) !== 1 || count($catalogMatches) !== 1 || count($codeMatches) !== 1) {
            return null;
        }
        $subject = $catalogMatches[0];
        if (($subject['code'] ?? null) !== $code
            || !pefCcSafeText($row['title'] ?? null, true) || ($row['title'] ?? null) !== ($subject['title'] ?? null)
            || trim(str_replace("\u{00a0}", ' ', (string) $subject['title'])) !== $nominal[0]['title']
            || !pefCcUsableUnits($row['units'] ?? null) || (float) $row['units'] !== (float) $nominal[0]['units']
            || !pefCcUsableUnits($subject['units'] ?? null) || (float) $subject['units'] !== (float) $nominal[0]['units']
            || !in_array($subject['year_level'] ?? null, [$year, (string) $year], true)
            || pefCcSemester($subject['semester'] ?? null) !== $semester) {
            return null;
        }
        $recordIds[$id] = $subjectIds[$subjectId] = $codes[$code] = true;
        $evidence[] = ['id' => $id, 'subject_id' => $subjectId, 'code' => $code, 'title' => $row['title'],
            'units' => $row['units'], 'school_year' => $row['school_year'], 'semester' => (int) $row['semester'],
            'is_current' => 1, 'grade' => $row['grade'] ?? null,
            'catalog_year_level' => (int) $subject['year_level'], 'catalog_semester' => (int) $subject['semester']];
        $terms[$row['school_year'] . '/' . (int) $row['semester']] = [
            'school_year' => $row['school_year'], 'semester' => (int) $row['semester'],
            'source' => 'coherent_recorded_current_term',
        ];
    }
    if (count($terms) !== 1) {
        return null;
    }
    $term = reset($terms);
    if ($term['school_year'] !== ($currentTerm['school_year'] ?? null)
        || $term['semester'] !== pefCcSemester($currentTerm['semester'] ?? null)) {
        return null;
    }
    usort($evidence, static fn(array $a, array $b): int => [$a['subject_id'], $a['id']] <=> [$b['subject_id'], $b['id']]);
    return $term + ['year_level' => $year, 'records' => $evidence];
}

/** Reduce active profile contexts to nonpersonal, actually observed term evidence. */
function pefCcVerifiedSectionEvidence(array $contexts, array $catalog, array $currentTerm): array
{
    $mapping = pefCcCurriculum()['mapping'];
    $sections = [];
    foreach ($contexts as $context) {
        $identity = $context['identity'] ?? [];
        $year = filter_var($identity['year_level'] ?? null, FILTER_VALIDATE_INT);
        $section = pefCcSupportedSection($identity['section'] ?? null);
        if (($identity['program'] ?? null) !== 'BSIT - Software Development'
            || ($identity['role'] ?? null) !== 'student' || !in_array($identity['is_active'] ?? null, [1, '1'], true)
            || ($identity['record_status'] ?? null) !== 'Active' || !in_array($year, [1, 2, 3, 4], true)
            || is_bool($identity['year_level'] ?? null) || $section === null || $section['year_level'] !== $year) {
            continue;
        }
        $term = pefCcProfileTerm($context['records'] ?? [], $currentTerm, $year, $mapping, $catalog);
        if ($term === null) {
            continue;
        }
        $row = ['section' => $identity['section'], 'year_level' => $year,
            'school_year' => $term['school_year'], 'semester' => $term['semester']];
        $key = implode('/', $row);
        $sections[$key]['row'] = $row;
        $sections[$key]['hashes'][] = hash('sha256', json_encode($term, JSON_THROW_ON_ERROR));
    }
    ksort($sections, SORT_STRING);
    return array_values(array_map(static function (array $item): array {
        sort($item['hashes'], SORT_STRING);
        return $item['row'] + ['source' => 'active_sd_current_records',
            'evidence_sha256' => hash('sha256', implode('/', $item['hashes']))];
    }, $sections));
}

/** Pure policy; callers supply server-loaded records and nonpersonal section evidence. */
function pefCcPlanningPolicy(array $context, array $input, array $currentTerm, ?array $curriculum = null): array
{
    $loaded = $curriculum ?? pefCcCurriculum();
    $mapping = $loaded['mapping'];
    $identity = $context['identity'] ?? [];
    $policy = [
        'version' => PEF_CC_PLANNING_POLICY_VERSION, 'mode' => 'requires_review', 'status' => 'evaluator_review_required',
        'curriculum_version' => $mapping['curriculum_version'] ?? null,
        'curriculum_sha256' => $loaded['sha256'],
        'curriculum_digest' => hash('sha256', json_encode($mapping, JSON_THROW_ON_ERROR)),
        'recorded_section' => $identity['section'] ?? null,
        'recorded_year_level' => $identity['year_level'] ?? null,
        'recorded_status' => $identity['status'] ?? null,
        'recorded_lifecycle' => $identity['record_status'] ?? null,
        'profile_term' => null, 'predecessor_term' => null, 'predecessor_complete' => false, 'predecessor_status' => null,
        'provisional_allowed' => false, 'provisional_warning' => '', 'incomplete_warning' => '',
        'horizon' => null, 'target_year_level' => null, 'target_year' => null, 'target_semester' => null,
        'section_options' => [], 'section_evidence' => [], 'section_default' => '', 'nominal' => [], 'nominal_ids' => [],
        'section_notice' => 'Available section assignments require evaluator confirmation.',
        'disclosure' => 'Suggested sections are planning preferences and remain subject to availability and evaluator review.',
        'errors' => [], 'field_errors' => [], 'review_concerns' => [],
    ];
    $fail = static function (string $message, ?string $field = null) use (&$policy): void {
        $policy['errors'][] = $message;
        if ($field !== null) {
            $policy['field_errors'][$field] = $message;
        }
    };
    if (in_array($identity['program'] ?? null, ['BSIT - Cyber Security', 'BSIT - Data Science'], true)) {
        $policy['mode'] = 'unsupported_legacy_track_requires_review';
        $policy['status'] = 'unsupported_legacy_track_requires_review';
        $fail(PEF_CC_LEGACY_REVIEW);
        return $policy;
    }
    if (($identity['program'] ?? null) !== 'BSIT - Software Development'
        || ($identity['record_status'] ?? null) !== 'Active') {
        $fail(PEF_CC_NOMINAL_REVIEW);
        return $policy;
    }
    if (($identity['status'] ?? null) === 'Irregular') {
        $policy['mode'] = 'irregular';
        $policy['status'] = 'manual_review_required';
        $policy['target_year'] = $input['target_year'] ?? null;
        $policy['target_semester'] = pefCcSemester($input['target_semester'] ?? null);
        $pred = pefCcValidYear($policy['target_year']) && $policy['target_semester'] !== null
            ? pefCcPredecessorTerm($policy['target_year'], $policy['target_semester'])
            : null;
        $policy['predecessor_term'] = $pred;
        if ($pred !== null) {
            $predCompletion = pefCcTermCompletion($context['records'] ?? [], $pred['school_year'], $pred['semester']);
            $policy['predecessor_complete'] = $predCompletion['is_complete'];
            $policy['predecessor_status'] = $predCompletion;
            if (!$predCompletion['is_complete']) {
                if ($predCompletion['reason'] === 'missing_records') {
                    $policy['status'] = 'incomplete_term_blocked';
                    $policy['incomplete_warning'] = 'No recorded subjects exist for the immediate predecessor term (' . $pred['school_year'] . ' ' . pefCcSemesterLabel($pred['semester']) . ').';
                    $fail($policy['incomplete_warning'], 'target_year');
                } else {
                    $policy['status'] = 'incomplete_term_provisional';
                    $policy['provisional_allowed'] = true;
                    $targetLabel = 'SY ' . str_replace('-', '–', (string) $policy['target_year']) . ' ' . pefCcSemesterLabel((int) $policy['target_semester']);
                    $predLabel = 'SY ' . str_replace('-', '–', (string) $pred['school_year']) . ' ' . pefCcSemesterLabel((int) $pred['semester']);
                    $policy['provisional_warning'] = 'You may preview and download a provisional planning workbook for ' . $targetLabel
                        . '. The current ' . $predLabel . ' record is incomplete, so grades, GWA, proposed subjects, and section may change after final results are recorded and academic evaluator review is completed.';
                    $policy['incomplete_warning'] = $policy['provisional_warning'];
                }
            }
        }
        $policy['section_options'] = ['']; // Existing optional, unassigned planning preference.
        $recordedSection = $identity['section'] ?? null;
        if ($recordedSection === 'IT-IRREG') {
            // A pacing marker is permitted only for an already recorded Irregular context.
            $policy['section_options'][] = $recordedSection;
        }
        foreach ($context['sections'] ?? [] as $available) {
            $parsed = pefCcSupportedSection($available['section'] ?? null);
            if ($parsed !== null && pefCcValidYear($policy['target_year']) && $policy['target_semester'] !== null
                && pefCcSectionEvidenceMatches($available, $policy['target_year'], $policy['target_semester'])) {
                $policy['section_options'][] = $available['section'];
                $policy['section_evidence'][] = array_intersect_key($available, array_flip([
                    'section', 'year_level', 'school_year', 'semester', 'source', 'evidence_sha256',
                ]));
            }
        }
        $policy['section_options'] = array_values(array_unique($policy['section_options']));
        sort($policy['section_options'], SORT_STRING);
        $policy['section_default'] = in_array($recordedSection, $policy['section_options'], true) ? $recordedSection : '';
        $policy['section_notice'] = 'Section and subject suggestions require review because the proposed plan may differ from the nominal curriculum sequence.';
        if (!pefCcSectionIsApproved($policy, $input['section'] ?? null)) {
            $fail(PEF_CC_SECTION_MESSAGE, 'section');
        }
        return $policy;
    }
    if (($identity['status'] ?? null) !== 'Regular') {
        $fail(PEF_CC_NOMINAL_REVIEW);
        return $policy;
    }
    $policy['mode'] = 'regular';
    if (($mapping['curriculum_version'] ?? null) !== '2022-2023' || ($mapping['program'] ?? null) !== 'BSIT'
        || ($mapping['track'] ?? null) !== 'Software Development'
        || ($mapping['supported_course_value'] ?? null) !== $identity['program']) {
        return pefCcControlledReviewPolicy($policy, $context, $input, $currentTerm, PEF_CC_NOMINAL_REVIEW);
    }
    $year = filter_var($identity['year_level'] ?? null, FILTER_VALIDATE_INT);
    $section = pefCcSupportedSection($identity['section'] ?? null);
    if (is_bool($identity['year_level'] ?? null) || !in_array($year, [1, 2, 3, 4], true)) {
        $fail(PEF_CC_TERM_REVIEW);
        return $policy;
    }
    $anchor = pefCcProfileTerm($context['records'] ?? [], $currentTerm, $year, $mapping, $context['catalog'] ?? []);
    if ($anchor === null) {
        return pefCcControlledReviewPolicy($policy, $context, $input, $currentTerm, PEF_CC_TERM_REVIEW);
    }
    $policy['profile_term'] = $anchor;
    $targetYear = $anchor['semester'] === 1 ? $anchor['school_year']
        : ((int) substr($anchor['school_year'], 0, 4) + 1) . '-' . ((int) substr($anchor['school_year'], 5, 4) + 1);
    $targetSemester = $anchor['semester'] === 1 ? 2 : 1;
    $targetNominal = $year + ($anchor['semester'] === 2 ? 1 : 0);
    if ($targetNominal > 4) {
        return pefCcControlledReviewPolicy($policy, $context, $input, $currentTerm, PEF_CC_TERM_REVIEW);
    }
    $policy['horizon'] = $anchor['semester'] === 1 ? 'same_school_year' : 'next_school_year';
    $policy['target_year'] = $targetYear;
    $policy['target_semester'] = $targetSemester;
    $policy['target_year_level'] = $targetNominal;

    $pred = pefCcPredecessorTerm($targetYear, $targetSemester);
    $policy['predecessor_term'] = $pred;
    $predCompletion = pefCcTermCompletion($context['records'] ?? [], $pred['school_year'], $pred['semester']);
    $policy['predecessor_complete'] = $predCompletion['is_complete'];
    $policy['predecessor_status'] = $predCompletion;
    if (!$predCompletion['is_complete']) {
        if ($predCompletion['reason'] === 'missing_records') {
            $policy['status'] = 'incomplete_term_blocked';
            $policy['incomplete_warning'] = 'No recorded subjects exist for the immediate predecessor term (' . $pred['school_year'] . ' ' . pefCcSemesterLabel($pred['semester']) . ').';
            $fail($policy['incomplete_warning'], 'target_year');
            $policy['section_notice'] = 'Enrolment planning is not yet available because the predecessor semester record is missing.';
        } else {
            $policy['status'] = 'incomplete_term_provisional';
            $policy['provisional_allowed'] = true;
            $targetLabel = 'SY ' . str_replace('-', '–', (string) $targetYear) . ' ' . pefCcSemesterLabel((int) $targetSemester);
            $predLabel = 'SY ' . str_replace('-', '–', (string) $anchor['school_year']) . ' ' . pefCcSemesterLabel((int) $anchor['semester']);
            $policy['provisional_warning'] = 'You may preview and download a provisional planning workbook for ' . $targetLabel
                . '. The current ' . $predLabel . ' record is incomplete, so grades, GWA, proposed subjects, and section may change after final results are recorded and academic evaluator review is completed.';
            $policy['incomplete_warning'] = $policy['provisional_warning'];
            $policy['section_notice'] = ($policy['horizon'] === 'same_school_year')
                ? 'Your current section is retained for the next semester within the same school year. Proposed section remains subject to academic evaluator review.'
                : 'Section suggestions remain subject to availability and academic evaluator review.';
        }
    }

    if ($targetYear !== ($input['target_year'] ?? null)) {
        $fail('The target school year is determined by your verified current academic term. Refresh the nominal plan.', 'target_year');
    }
    if ($targetSemester !== pefCcSemester($input['target_semester'] ?? null)) {
        $fail('The target semester is determined by your verified current academic term. Refresh the nominal plan.', 'target_semester');
    }
    if ($section === null || $section['year_level'] !== $year) {
        $fail(PEF_CC_NOMINAL_REVIEW, 'section');
        return $policy;
    }
    $terms = array_values(array_filter($mapping['terms'] ?? [], static fn(array $t): bool =>
        ($t['year_level'] ?? null) === $targetNominal && ($t['semester'] ?? null) === $targetSemester));
    if (count($terms) !== 1 || empty($terms[0]['subjects'])) {
        return pefCcControlledReviewPolicy($policy, $context, $input, $currentTerm, PEF_CC_NOMINAL_REVIEW);
    }
    foreach ($terms[0]['subjects'] as $subject) {
        $matches = array_values(array_filter($context['catalog'] ?? [], static fn(array $row): bool => ($row['code'] ?? null) === $subject['code']));
        $id = pefCcPositiveId($matches[0]['id'] ?? null);
        $idMatches = array_values(array_filter($context['catalog'] ?? [], static fn(array $row): bool => pefCcPositiveId($row['id'] ?? null) === $id));
        if (count($matches) !== 1 || $id === null || count($idMatches) !== 1
            || !pefCcUsableUnits($matches[0]['units'] ?? null) || (float) $matches[0]['units'] !== (float) $subject['units']
            || !in_array($matches[0]['year_level'] ?? null, [$targetNominal, (string) $targetNominal], true)
            || pefCcSemester($matches[0]['semester'] ?? null) !== $targetSemester
            || trim(str_replace("\u{00a0}", ' ', (string) ($matches[0]['title'] ?? ''))) !== $subject['title']) {
            $policy['nominal'] = $policy['nominal_ids'] = [];
            return pefCcControlledReviewPolicy($policy, $context, $input, $currentTerm, PEF_CC_NOMINAL_REVIEW);
        }
        $policy['nominal'][] = ['id' => (int) $matches[0]['id'], 'code' => $subject['code'], 'title' => $subject['title'],
            'units' => $subject['units'], 'prerequisites' => $subject['prerequisites'], 'elective_slot' => $subject['elective_slot']];
        $policy['nominal_ids'][] = (int) $matches[0]['id'];
    }
    if ($policy['horizon'] === 'same_school_year') {
        if ($section['year_level'] !== $targetNominal) {
            $fail(PEF_CC_NOMINAL_REVIEW, 'section');
        } else {
            $policy['section_options'] = [$identity['section']];
            $policy['section_default'] = $identity['section'];
        }
        $policy['section_notice'] = 'Your current section is retained for the next semester within the same school year.';
        if (($input['section'] ?? '') !== $identity['section']) {
            $fail('Regular students retain their current section for the next semester within the same school year.', 'section');
        }
    } else {
        foreach ($context['sections'] ?? [] as $available) {
            $parsed = pefCcSupportedSection($available['section'] ?? null);
            if ($parsed !== null && $parsed['year_level'] === $targetNominal
                && pefCcSectionEvidenceMatches($available, $targetYear, $targetSemester)
                && (int) ($available['year_level'] ?? 0) === $targetNominal
                && ($available['school_year'] ?? null) === $targetYear
                && pefCcSemester($available['semester'] ?? null) === $targetSemester
                && abs($parsed['block'] - $section['block']) <= 1) {
                $policy['section_options'][] = $available['section'];
                $policy['section_evidence'][] = array_intersect_key($available, array_flip([
                    'section', 'year_level', 'school_year', 'semester', 'source', 'evidence_sha256',
                ]));
            }
        }
        $policy['section_options'] = array_values(array_unique($policy['section_options']));
        sort($policy['section_options'], SORT_STRING);
        $sameBlock = 'IT-' . $targetNominal . $section['block'];
        if (in_array($sameBlock, $policy['section_options'], true)) {
            $policy['section_default'] = $sameBlock;
        }
        if ($policy['section_options'] !== []) {
            $policy['section_notice'] = 'You may suggest your corresponding next-year block, one block below, or one block above. Final section assignment remains subject to availability and evaluator review.';
        } elseif ($section['year_level'] === $targetNominal) {
            // A valid retained current-year section is a preference only.
            $policy['section_options'] = [$identity['section']];
            $policy['section_default'] = $identity['section'];
        }
        if ($policy['section_options'] === [] && ($input['section'] ?? '') === '') {
            return pefCcControlledReviewPolicy($policy, $context, $input, $currentTerm,
                'No verified section is available for this target term. Academic evaluator review is required.');
        }
        if (!in_array($input['section'] ?? '', $policy['section_options'], true)) {
            $fail('The selected section is outside the available planning range. Choose the current block, one block below, or one block above for the next school year.', 'section');
        }
    }
    if (!in_array($policy['status'], ['incomplete_term_blocked', 'incomplete_term_provisional'], true)) {
        $policy['status'] = $policy['errors'] === [] ? 'resolved' : 'evaluator_review_required';
    }
    return $policy;
}
