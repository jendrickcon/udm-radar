<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__, 2) . '/config/pef_cc.php';
require_once dirname(__DIR__, 2) . '/includes/pef_cc_view_model.php';
require_once dirname(__DIR__, 2) . '/includes/pef_cc_snapshot.php';

// Database-free, synthetic contexts. No workbook, account or dependency writes.
$loaded = pefCcCurriculum();
$mapping = $loaded['mapping'];
$config = pefCcConfig();
$current = static fn(int $semester): array => ['school_year' => '2026-2027', 'semester' => $semester];
$build = static fn(array $c, array $i, int $sem = 1): PefCcViewModel =>
    pefCcBuildViewModel($c, $i, pefTestReference(), $config, $current($sem));
$policy = static fn(array $c, array $i, int $sem = 1): array => pefCcPlanningPolicy($c, $i, $current($sem));

pefTestCheck($mapping['curriculum_version'] === '2022-2023' && $mapping['program'] === 'BSIT'
    && $mapping['track'] === 'Software Development' && $mapping['supported_course_value'] === 'BSIT - Software Development', 'Exact approved SD curriculum metadata');
pefTestCheck(str_contains($mapping['source']['statement'], 'CCS-provided') && strlen($mapping['source']['sha256']) === 64, 'Department source provenance retained');
$counts = [8, 9, 9, 9, 8, 8, 6, 1];
$units = [21, 24, 24, 24, 22, 22, 16, 6];
$all = [];
foreach ($mapping['terms'] as $index => $term) {
    pefTestCheck($term['year_level'] === intdiv($index, 2) + 1 && $term['semester'] === $index % 2 + 1, 'Ordered curriculum term ' . $index);
    pefTestCheck(count($term['subjects']) === $counts[$index] && array_sum(array_column($term['subjects'], 'units')) === $units[$index], 'Source-verified term capacity and units ' . $index);
    foreach ($term['subjects'] as $s) {
        pefTestCheck(is_int($s['units']) && $s['units'] > 0 && $s['year_level'] === $term['year_level']
            && $s['semester'] === $term['semester'] && !preg_match('/^(CY|DS)/', $s['code']), 'Positive active SD subject in correct term: ' . $s['code']);
        pefTestCheck(isset($s['source_cells']['code'], $s['source_cells']['title'], $s['source_cells']['units'], $s['source_cells']['prerequisite']), 'Source cells retained: ' . $s['code']);
        $all[] = $s;
    }
}
pefTestCheck(count($mapping['terms']) === 8 && count($all) === 58 && count(array_unique(array_column($all, 'code'))) === 58
    && array_sum(array_column($all, 'units')) === 159, 'Eight terms, 58 unique subjects and 159 units');
$byCode = array_column($all, null, 'code');
foreach (['SD351' => [3, 1, 'ITE351'], 'SD352' => [3, 2, 'ITE352'], 'SD353' => [3, 2, 'ITE353'], 'SD354' => [4, 1, 'ITE354']] as $code => [$year, $sem, $slot]) {
    $s = $byCode[$code];
    pefTestCheck([$s['year_level'], $s['semester'], $s['units'], $s['elective_slot']] === [$year, $sem, 3, $slot], 'Approved slot-scoped elective ' . $code);
}
pefTestCheck($byCode['SD354']['prerequisites']['raw'] === "ITE252\nITE353"
    && $byCode['SD354']['prerequisites']['state'] === 'unresolved_reference', 'ITE252 preserved without guessed repair');
$states = array_count_values(array_column(array_column($all, 'prerequisites'), 'state'));
pefTestCheck($states === ['explicit_none' => 8, 'unknown' => 27, 'information_only' => 22, 'unresolved_reference' => 1], 'Blank, explicit None and unresolved prerequisites remain distinct');
pefTestCheck($byCode['PE103']['units'] === 3 && $byCode['ITE423']['units'] === 6, 'Verified PE103 and immersion units preserved');
pefTestCheck($byCode['ITE332']['title'] === 'Seminar in Information Technology'
    && $byCode['ITE223']['title'] === "Integrative Prog'g Technologies 1", 'Owner-approved catalogue display titles');
$badKeys = [];
$scan = static function (array $value) use (&$scan, &$badKeys): void {
    foreach ($value as $key => $child) {
        if (preg_match('/^(name|student_number|student_id|grades?|section|standing|signature|remarks?|email|first_name|last_name)$/i', (string) $key)) $badKeys[] = $key;
        if (is_array($child)) $scan($child);
    }
};
$scan($mapping);
pefTestCheck($badKeys === [] && !preg_match('/\b\d{2}-\d{2}-\d{3}\b|[\w.+-]+@[\w.-]+\.[a-z]{2,}/i', json_encode($mapping)), 'Configuration has no personal fields or identifier/email patterns');

foreach ([1, 2, 3, 4] as $year) foreach ([1, 2] as $sem) {
    $c = pefTestRegularContext($year, $sem);
    $i = pefTestRegularInputs($c, $sem);
    $before = serialize($c);
    $m = $build($c, $i, $sem)->data;
    if ($year === 4 && $sem === 2) {
        pefTestCheck(!$m['exportable'] && $m['policy']['status'] === 'evaluator_review_required'
            && $m['policy']['target_year_level'] === null && $m['proposed'] === [], 'Fourth-year Semester 2 never generates Fifth Year');
    } else {
        pefTestCheck($m['exportable'] && $m['policy']['status'] === 'resolved', "Coherent Year $year Semester $sem supports Regular plan");
        pefTestCheck($m['policy']['target_year_level'] === $year + ($sem === 2 ? 1 : 0)
            && $m['policy']['target_semester'] === ($sem === 1 ? 2 : 1)
            && $m['policy']['target_year'] === ($sem === 1 ? '2026-2027' : '2027-2028'), "Current-term progression $year/$sem");
        pefTestCheck($m['proposed'] !== [] && array_column($m['proposed'], 'id') === $i['subject_ids'], "Nonempty readonly nominal subject order $year/$sem");
        pefTestCheck($m['gwa'] === 3.0 && $m['recorded_count'] === 3, "Historical selected-term GWA remains independent $year/$sem");
    }
    pefTestCheck(serialize($c) === $before, "No profile/record mutations for $year/$sem");
}
$c = pefTestRegularContext(4, 1); $m = $build($c, pefTestRegularInputs($c))->data;
pefTestCheck(array_column($m['proposed'], 'code') === ['ITE423'], 'Fourth-year next semester is Industry Immersion only');
$c = pefTestRegularContext(3, 1); $m = $build($c, pefTestRegularInputs($c))->data;
pefTestCheck(array_column($m['proposed'], 'code') === ['ITE324', 'ITE325', 'ITE326', 'ITE327', 'ITE328', 'SD352', 'SD353', 'UID106'], 'Third-year second-semester exact SD nominal sequence');

$base = pefTestRegularContext(); $input = pefTestRegularInputs($base);
foreach (['year_level' => '2bad', 'semester' => '2bad', 'units' => '3bad', 'id' => 1001] as $field => $value) {
    $c = $base;
    foreach ($c['catalog'] as &$subject) { if ($subject['id'] === $input['subject_ids'][0]) { $subject[$field] = $value; break; } } unset($subject);
    $p = $policy($c, $input);
    pefTestCheck($p['errors'] !== [] && $p['nominal_ids'] === [] && !$build($c, $input)->data['exportable'], 'Strict target catalogue rejects malformed/ambiguous ' . $field);
}
$sectionEvidence = pefCcVerifiedSectionEvidence([$base], $base['catalog'], $current(1));
pefTestCheck(count($sectionEvidence) === 1 && array_intersect_key($sectionEvidence[0], array_flip(['section', 'year_level', 'school_year', 'semester']))
    === ['section' => 'IT-22', 'year_level' => 2, 'school_year' => '2026-2027', 'semester' => 1]
    && $sectionEvidence[0]['source'] === 'active_sd_current_records' && strlen($sectionEvidence[0]['evidence_sha256']) === 64,
    'Section evidence retains actual observed term and source digest without identities or grades');
foreach (['null_year', 'duplicate', 'wrong_course', 'wrong_year', 'orphan', 'archived'] as $case) {
    $c = $base;
    if ($case === 'null_year') $c['records'][3]['school_year'] = null;
    if ($case === 'duplicate') $c['records'][] = $c['records'][3];
    if ($case === 'wrong_course') $c['identity']['program'] = 'BSIT - Data Science';
    if ($case === 'wrong_year') $c['identity']['year_level'] = 3;
    if ($case === 'orphan') $c['records'][3]['code'] = null;
    if ($case === 'archived') $c['identity']['record_status'] = 'Archived';
    pefTestCheck(pefCcVerifiedSectionEvidence([$c], $base['catalog'], $current(1)) === [], 'Unverified section source excluded: ' . $case);
}
$p = $policy($base, $input);
pefTestCheck(count($p['profile_term']['records']) === 3 && $p['errors'] === [], 'Partial current subject set and missing current grades do not block binding');
$one = $base; $one['records'] = array_slice($one['records'], 0, 4);
pefTestCheck($policy($one, $input)['errors'] === [], 'One legitimate current record is sufficient without exact complete-term matching');
foreach (['no_current', 'two_years', 'two_semesters', 'duplicate_row', 'duplicate_subject', 'duplicate_code', 'bad_year', 'wrong_subject',
    'wrong_nominal_year', 'bad_catalog_semester', 'bad_current_title', 'bad_catalog_year', 'bad_catalog_units',
    'invalid_flag', 'bad_current_units', 'duplicate_catalog', 'missing_catalog', 'calendar_mismatch'] as $case) {
    $c = $base; $approved = $current(1);
    if ($case === 'no_current') $c['records'] = array_slice($c['records'], 0, 3);
    if ($case === 'two_years') $c['records'][4]['school_year'] = '2025-2026';
    if ($case === 'two_semesters') $c['records'][4]['semester'] = 2;
    if ($case === 'duplicate_row') $c['records'][] = $c['records'][3];
    if ($case === 'duplicate_subject') { $c['records'][] = $c['records'][3]; $c['records'][6]['id']++; }
    if ($case === 'duplicate_code') { $c['records'][4]['code'] = $c['records'][3]['code']; }
    if ($case === 'bad_year') $c['records'][3]['school_year'] = '2026/2027';
    if ($case === 'wrong_subject') $c['records'][3]['code'] = 'CY351';
    if ($case === 'wrong_nominal_year') $c['identity']['year_level'] = 3;
    if ($case === 'bad_catalog_semester') foreach ($c['catalog'] as &$s) { if ($s['id'] === $c['records'][3]['subject_id']) $s['semester'] = 2; } unset($s);
    if ($case === 'bad_current_title') $c['records'][3]['title'] = 'Synthetic mismatched title';
    if ($case === 'bad_catalog_year') foreach ($c['catalog'] as &$s) { if ($s['id'] === $c['records'][3]['subject_id']) $s['year_level'] = '2bad'; } unset($s);
    if ($case === 'bad_catalog_units') foreach ($c['catalog'] as &$s) { if ($s['id'] === $c['records'][3]['subject_id']) $s['units'] = '3bad'; } unset($s);
    if ($case === 'invalid_flag') $c['records'][3]['is_current'] = 2;
    if ($case === 'bad_current_units') $c['records'][3]['units'] = 99;
    if ($case === 'duplicate_catalog') $c['catalog'][] = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['id'] === $c['records'][3]['subject_id']))[0];
    if ($case === 'missing_catalog') $c['catalog'] = array_values(array_filter($c['catalog'], static fn(array $s): bool => $s['id'] !== $c['records'][3]['subject_id']));
    if ($case === 'calendar_mismatch') $approved['semester'] = 2;
    $m = pefCcBuildViewModel($c, $input, pefTestReference(), $config, $approved)->data;
    pefTestCheck(!$m['exportable'] && $m['policy']['status'] === 'evaluator_review_required'
        && in_array(PEF_CC_TERM_REVIEW, $m['errors'], true) && $m['proposed'] === [], 'Ambiguous binding requires review, no empty export: ' . $case);
}
foreach ([null, 0, 5, 'two', true, '2bad'] as $year) {
    $c = $base; $c['identity']['year_level'] = $year;
    pefTestCheck(in_array(PEF_CC_TERM_REVIEW, $policy($c, $input)['errors'], true), 'Malformed/missing profile year requires review: ' . json_encode($year));
}
$c = $base; $c['records'] = array_slice($c['records'], 0, 3);
$c['approved_profile_term'] = ['school_year' => '2026-2027', 'semester' => 1, 'source' => 'approved_term_context'];
pefTestCheck($policy($c, $input)['profile_term'] === null, 'Removed context fallback cannot bypass missing current evidence');
foreach (['curriculum_version', 'supported_course_value', 'track'] as $field) {
    $edited = $loaded; $edited['mapping'][$field] = 'unresolved';
    $p = pefCcPlanningPolicy($base, $input, $current(1), $edited);
    pefTestCheck($p['status'] === 'evaluator_review_required' && $p['nominal'] === [] && $p['errors'] !== [], 'Unresolved curriculum metadata prevents automatic listing: ' . $field);
}
foreach (['BSIT - Cyber Security', 'BSIT - Data Science', '', 'BSIT', ' BSIT - Software Development'] as $course) {
    $c = $base; $c['identity']['program'] = $course; $m = $build($c, $input)->data;
    pefTestCheck(!$m['exportable'] && $m['identity']['program'] === $course && $m['proposed'] === [], 'Unsupported course never relabeled or automatically planned: ' . $course);
    if (in_array($course, ['BSIT - Cyber Security', 'BSIT - Data Science'], true))
        pefTestCheck($m['policy']['status'] === 'unsupported_legacy_track_requires_review', 'Legacy track has explicit review status');
}
foreach ([['target_year' => '2027-2028'], ['target_semester' => 1]] as $change) {
    $m = $build($base, array_replace($input, $change))->data;
    pefTestCheck(!$m['exportable'] && $m['policy']['target_year'] === '2026-2027' && $m['policy']['target_semester'] === 2, 'Target override rejected; server resolution retained');
}
$a = $policy($base, $input); $b = $policy($base, array_replace($input, ['evaluation_year' => '2023-2024', 'evaluation_semester' => 1]));
pefTestCheck($a === $b, 'Selected historical evaluation term does not determine current progression');
foreach ([[], [$input['subject_ids'][0]], array_reverse($input['subject_ids']), array_merge($input['subject_ids'], [1001])] as $ids) {
    $m = $build($base, array_replace($input, ['subject_ids' => $ids]))->data;
    pefTestCheck(!$m['exportable'] && isset($m['field_errors']['subject_ids']), 'Nominal deletion/insertion/replacement/reorder rejected');
}
$m = $build($base, array_replace($input, ['section' => 'IT-21']))->data;
pefTestCheck(!$m['exportable'] && isset($m['field_errors']['section']), 'Same-year Regular section replacement rejected');
$next = pefTestRegularContext(2, 2); $nextInput = pefTestRegularInputs($next, 2);
foreach (['IT-31', 'IT-32', 'IT-33'] as $s) pefTestCheck($build($next, array_replace($nextInput, ['section' => $s]), 2)->data['exportable'], 'Existing next-year adjacent choice: ' . $s);
foreach (['IT-30', 'IT-34', 'IT-22', 'IT-39', 'CYB31', 'DS31', '=1+1', 'arbitrary'] as $s)
    pefTestCheck(!$build($next, array_replace($nextInput, ['section' => $s]), 2)->data['exportable'], 'Invalid/unavailable next-year section rejected: ' . $s);
$none = $next; $none['sections'] = [];
pefTestCheck($policy($none, $nextInput, 2)['section_options'] === [] && !$build($none, $nextInput, 2)->data['exportable'], 'Unavailable future sections are never manufactured');
$lower = $next; $lower['identity']['section'] = 'IT-21';
pefTestCheck($policy($lower, $nextInput, 2)['section_options'] === ['IT-31', 'IT-32'], 'Lower-bound block zero and plus-two omitted');
$irregular = pefTestContext(); $manual = $build($irregular, pefTestInputs(['subject_ids' => [1, 2]]))->data;
pefTestCheck($manual['exportable'] && $manual['policy']['mode'] === 'irregular' && $manual['proposed_count'] === 2, 'Irregular manual workflow needs no current-term binding');

$model = $build($base, $input); $key = random_bytes(32); $now = 2000000000; $student = $base['identity']['id'];
$token = pefCcSignSnapshot($model, $key, $student, 'synthetic-binding', $config, $now);
foreach (['current_grade', 'current_id', 'current_term', 'profile_year', 'profile_section', 'profile_status'] as $case) {
    $c = $base;
    if ($case === 'current_grade') $c['records'][3]['grade'] = '3.00';
    if ($case === 'current_id') $c['records'][3]['id'] += 100;
    if ($case === 'current_term') $c['records'][3]['semester'] = 2;
    if ($case === 'profile_year') $c['identity']['year_level'] = 3;
    if ($case === 'profile_section') $c['identity']['section'] = 'IT-21';
    if ($case === 'profile_status') $c['identity']['status'] = 'Irregular';
    pefTestCheck(!pefCcVerifySnapshot($token, $build($c, $input), $key, $student, 'synthetic-binding', $config, $now + 1), 'Binding evidence invalidates stale preview: ' . $case);
}
$nextModel = $build($next, $nextInput, 2);
$nextToken = pefCcSignSnapshot($nextModel, $key, $student, 'synthetic-binding', $config, $now);
$changed = $next; array_pop($changed['sections']);
pefTestCheck(!pefCcVerifySnapshot($nextToken, $build($changed, $nextInput, 2), $key, $student, 'synthetic-binding', $config, $now + 1), 'Changed section option evidence invalidates still-valid selected section');
$sameOptions = $next; $sameOptions['sections'][0]['evidence_sha256'] = str_repeat('a', 64);
pefTestCheck(!pefCcVerifySnapshot($nextToken, $build($sameOptions, $nextInput, 2), $key, $student, 'synthetic-binding', $config, $now + 1), 'Section provenance changes invalidate preview even with identical option labels');
foreach (['curriculum', 'policy_version'] as $case) {
    $data = $model->data;
    if ($case === 'curriculum') {
        $edited = $loaded; $edited['mapping']['source']['extraction_version'] = 'changed';
        $data['policy'] = pefCcPlanningPolicy($base, $input, $current(1), $edited);
    } else $data['policy']['version'] = 'future-policy';
    pefTestCheck(!pefCcVerifySnapshot($token, new PefCcViewModel($data), $key, $student, 'synthetic-binding', $config, $now + 1), 'Configuration/policy change invalidates signed preview: ' . $case);
}
// Generated static HTML from the real page template, not authenticated HTTP.
// Bootstrap/header/footer are excluded; all context and tokens are synthetic.
$pageSource = file_get_contents(dirname(__DIR__, 2) . '/student/enrolment_planning.php');
$helpersStart = strpos($pageSource, 'function pefCcPageEscape(');
$helpersEnd = strpos($pageSource, '/** Bound actual bytes');
eval(substr($pageSource, $helpersStart, $helpersEnd - $helpersStart));
$templateStart = strpos($pageSource, '<link rel="stylesheet"');
$templateEnd = strpos($pageSource, '<?php require_once __DIR__', $templateStart);
$template = substr($pageSource, $templateStart, $templateEnd - $templateStart);
$template = str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/student', true), $template);
if (!defined('BASE_URL')) define('BASE_URL', '/udm-radar/');
$render = static function (array $context, array $inputs, int $sem = 1) use ($build, $config, $template, $current): DOMXPath {
    $currentTerm = $current($sem);
    $inputs = pefCcInitialPlanningInputs($context, $inputs, $currentTerm);
    $model = $build($context, $inputs, $sem);
    $data = $model->data; $formPolicy = $data['policy'];
    $manualPlanning = $formPolicy['mode'] === 'irregular'; $regularPlanning = $formPolicy['mode'] === 'regular';
    $formUnavailable = !$manualPlanning && ($formPolicy['nominal'] === [] || $formPolicy['target_year'] === null || $formPolicy['section_options'] === []);
    $errors = $data['errors']; $fieldErrors = $data['field_errors']; $selectedIds = array_map('strval', $inputs['subject_ids']);
    $choices = pefCcTermChoices($context['records'], $currentTerm);
    $hasCompletedTerm = count(array_filter($choices, static fn(array $c): bool => $c['eligible'])) > 0;
    $years = array_values(array_unique(array_column($choices, 'school_year')));
    $csrf = 'synthetic-render-token'; $reference = pefTestReference(); $cc = $data['cc']; $requestValid = true; $showPreview = true;
    $preview = $data['exportable'] ? pefCcSignSnapshot($model, random_bytes(32), $data['identity']['id'], 'synthetic-render-session', $config) : '';
    ob_start();
    try { eval('?>' . $template); $html = ob_get_clean(); }
    catch (Throwable $e) { ob_end_clean(); throw $e; }
    $dom = new DOMDocument(); $previous = libxml_use_internal_errors(true); $dom->loadHTML($html); libxml_clear_errors(); libxml_use_internal_errors($previous);
    return new DOMXPath($dom);
};
$getDefaults = pefCcInitialPlanningInputs($base, pefTestInputs(), $current(1));
pefTestCheck($build($base, $getDefaults)->data['exportable'], 'Initial GET loads authoritative target, fixed section and complete nominal IDs');
$regularHtml = $render($base, pefTestInputs());
pefTestCheck($regularHtml->query('//*[@id="target-year" and @readonly and @value="2026-2027"]')->length === 1, 'Regular school year shown read-only');
pefTestCheck($regularHtml->query('//*[@id="target-semester" and @readonly]')->length === 1
    && $regularHtml->query('//*[@id="pef-cc-planning-form"]//input[@name="target_semester" and @value="2"]')->length === 1, 'Resolved semester display and posted value agree');
pefTestCheck($regularHtml->query('//*[@id="proposed-section" and @readonly and @value="IT-22"]')->length === 1, 'Same-year section is prefilled and read-only');
pefTestCheck($regularHtml->query('//*[@id="pef-cc-subjects"]')->length === 0
    && $regularHtml->query('//*[@data-testid="nominal-subjects"]/tbody/tr')->length === 9, 'Regular nominal list has no editable subject selector');
pefTestCheck($regularHtml->query('//*[@id="pef-cc-planning-form"]//input[@name="subject_ids[]"]')->length === 9
    && $regularHtml->query('//*[@id="pef-cc-download-form"]//input[@name="subject_ids[]"]')->length === 9, 'Planning and download carry complete identical-size nominal lists');
$formIds = $downloadIds = [];
foreach ($regularHtml->query('//*[@id="pef-cc-planning-form"]//input[@name="subject_ids[]"]') as $node) $formIds[] = (int) $node->getAttribute('value');
foreach ($regularHtml->query('//*[@id="pef-cc-download-form"]//input[@name="subject_ids[]"]') as $node) $downloadIds[] = (int) $node->getAttribute('value');
pefTestCheck($formIds === $getDefaults['subject_ids'] && $downloadIds === $formIds, 'Rendered form, shared model and download preserve nominal ID order');
$nextHtml = $render($next, pefTestInputs(), 2); $values = [];
foreach ($nextHtml->query('//*[@id="proposed-section"]/option[@value!=""]') as $node) $values[] = $node->getAttribute('value');
pefTestCheck($values === ['IT-31', 'IT-32', 'IT-33'], 'Next-year UI displays only verified allowed sections');
pefTestCheck($nextHtml->query('//*[@id="proposed-section"]/option[@selected and @value="IT-32"]')->length === 1, 'Next-year default retains corresponding block');
pefTestCheck(str_contains($nextHtml->document->textContent, 'Recorded current section: IT-22')
    && str_contains($nextHtml->document->textContent, 'IT-32'), 'Current recorded section remains distinct from suggested next-year section');
$missing = $base; $missing['records'] = array_slice($missing['records'], 0, 3);
$missingDefaults = pefCcInitialPlanningInputs($missing, pefTestInputs(), $current(1));
$missingModel = $build($missing, $missingDefaults)->data;
pefTestCheck(!$missingModel['exportable'] && $missingModel['gwa'] === 3.0 && $missingModel['recorded_count'] === 3, 'Unresolved current context preserves historical GWA and evidence');
$missingHtml = $render($missing, pefTestInputs());
pefTestCheck($missingHtml->query('//*[@data-testid="nominal-unavailable"]')->length === 1
    && $missingHtml->query('//*[@data-testid="preview-button" and @disabled]')->length === 1, 'Unresolved Regular UI visibly requires review and disables preview');
pefTestCheck($missingHtml->query('//*[@id="pef-cc-download-form"]')->length === 0
    && !str_contains($missingHtml->document->textContent, 'This copy may still be exported'), 'No empty Regular export promise or signed download');
$manualHtml = $render($irregular, pefTestInputs(['subject_ids' => [1, 2], 'section' => '<img src=x onerror=bad>']));
pefTestCheck($manualHtml->query('//*[@id="target-year" and not(@readonly)]')->length === 1
    && $manualHtml->query('//*[@id="pef-cc-subjects"]')->length === 1, 'Irregular target and manual subject controls remain editable');
pefTestCheck($manualHtml->query('//*[@id="proposed-section" and @data-max-characters="96" and not(@readonly)]')->length === 1
    && $manualHtml->query('//img[@src="x"]')->length === 0, 'Irregular literal section retains 96-character contract and HTML escaping');
$legacy = $base; $legacy['identity']['program'] = 'BSIT - Data Science'; $legacyHtml = $render($legacy, pefTestInputs());
pefTestCheck($legacyHtml->query('//*[@id="pef-cc-subjects"]')->length === 0
    && $legacyHtml->query('//*[@data-testid="preview-button" and @disabled]')->length === 1
    && str_contains($legacyHtml->document->textContent, PEF_CC_LEGACY_REVIEW), 'Legacy track UI requires review without manual conversion controls');
pefTestFinish();
