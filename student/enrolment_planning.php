<?php
declare(strict_types=1);

// Keep deployment errors in the server log, including fatal errors before bootstrap completes.
ini_set('display_errors', '0');
$pefCcPageBufferLevel = ob_get_level();
$pefCcPageFatalReserve = str_repeat(' ', 65536);
ob_start();
register_shutdown_function(static function () use ($pefCcPageBufferLevel, &$pefCcPageFatalReserve): void {
    $pefCcPageFatalReserve = '';
    $lastError = error_get_last();
    if ($lastError === null || !in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true) || headers_sent()) {
        return;
    }
    while (ob_get_level() > $pefCcPageBufferLevel) {
        ob_end_clean();
    }
    http_response_code(503);
    header('HTTP/1.1 503 Service Unavailable', true, 503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Planning unavailable · UDM-RADAR</title></head><body><main><h1>Planning is temporarily unavailable</h1><p>Please try again later or ask the system administrator to check the service.</p><a href="enrolment_planning.php">Reload Enrolment Planning</a></main></body></html>';
    flush();
    // Do not exit: later shutdown callbacks may still own cleanup work.
});

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pef_cc.php';
require_once __DIR__ . '/../includes/pef_cc_repository.php';
require_once __DIR__ . '/../includes/pef_cc_view_model.php';
require_once __DIR__ . '/../includes/pef_cc_snapshot.php';
require_once __DIR__ . '/../includes/pef_cc_export_service.php';

requireRole('student');
$user = currentUser();
$config = pefCcConfig();
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

function pefCcPageEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pefCcPageGrade(mixed $grade): string
{
    if ($grade === null || $grade === '') {
        return '—';
    }
    return is_numeric($grade) ? number_format((float) $grade, 2) : (string) $grade;
}

function pefCcPageInvalid(string $field, array $fieldErrors): string
{
    return isset($fieldErrors[$field]) ? ' aria-invalid="true"' : '';
}

/** Bound actual bytes as well as Content-Length, including chunked form requests. */
function pefCcPageBodyWithinLimit(int $limit): bool
{
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        return false;
    }
    $bytes = 0;
    try {
        while (!feof($stream) && $bytes <= $limit) {
            $chunk = fread($stream, min(8192, $limit + 1 - $bytes));
            if ($chunk === false) {
                return false;
            }
            $bytes += strlen($chunk);
            if ($chunk === '') {
                break;
            }
        }
        return $bytes <= $limit;
    } finally {
        fclose($stream);
    }
}

$errors = [];
$fieldErrors = [];
$context = ['identity' => [], 'records' => [], 'catalog' => []];
$choices = [];
$model = null;
$reference = null;
$cc = null;
$preview = '';
$showPreview = false;
$currentTerm = getCurrentTerm();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestValid = true;
$raw = [];
$inputs = [
    'evaluation_year' => '', 'evaluation_semester' => '1',
    'target_year' => $currentTerm['school_year'], 'target_semester' => (string) $currentTerm['semester'],
    'section' => '', 'subject_ids' => [],
];
$transport = ['csrf_token', 'action', 'preview'];
$planningKeys = ['evaluation_year', 'evaluation_semester', 'target_year', 'target_semester', 'section', 'subject_ids'];

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    $errors[] = 'Use the planning form to preview your copy.';
    $requestValid = false;
} elseif ($_GET !== [] || $_FILES !== []) {
    http_response_code(422);
    $errors[] = 'Unexpected request fields. Open Enrolment Planning again and use the form.';
    $requestValid = false;
} elseif ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $config['max_request_bytes']
    || !pefCcPageBodyWithinLimit($config['max_request_bytes'])) {
    http_response_code(413);
    $errors[] = 'The planning request is too large. Please choose at most twelve subjects.';
    $requestValid = false;
} elseif ($method === 'POST' && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/x-www-form-urlencoded') {
    http_response_code(415);
    $errors[] = 'Use the supplied planning form to submit these details.';
    $requestValid = false;
} elseif ($method === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !validateCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        $errors[] = 'Your session could not verify this request. Reload the page and try again.';
        $requestValid = false;
    } elseif (array_diff(array_keys($_POST), array_merge($transport, $planningKeys)) !== []
        || ($_POST['action'] ?? '') !== 'preview'
        || (isset($_POST['preview']) && (!is_string($_POST['preview']) || strlen($_POST['preview']) > 4096))) {
        http_response_code(422);
        $errors[] = 'Unexpected planning fields. Use only the controls provided on this page.';
        $requestValid = false;
    } else {
        $raw = array_intersect_key($_POST, array_flip($planningKeys));
        // Retain scalar input for correction; the model performs the authoritative validation.
        foreach ($planningKeys as $key) {
            if ($key === 'subject_ids') {
                $inputs[$key] = is_array($raw[$key] ?? null) ? array_values(array_filter($raw[$key], 'is_scalar')) : [];
            } elseif (isset($raw[$key]) && is_string($raw[$key])) {
                $inputs[$key] = $raw[$key];
            }
        }
    }
}

$csrf = getCsrfToken();
if (!isset($_SESSION['pef_cc_signing_key']) || !is_string($_SESSION['pef_cc_signing_key'])
    || strlen($_SESSION['pef_cc_signing_key']) !== 32) {
    $_SESSION['pef_cc_signing_key'] = random_bytes(32);
}
$signingKey = $_SESSION['pef_cc_signing_key'];
$sessionBinding = session_id();
session_write_close();

if ($requestValid) {
    try {
        $db = getDB();
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');
        $db->beginTransaction();
        try {
            $context = pefCcReadContext($db, (int) $user['id']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $choices = pefCcTermChoices($context['records'], $currentTerm);
        if ($method === 'GET' && $choices !== []) {
            $preferred = $choices[0];
            foreach ($choices as $choice) {
                if ($choice['eligible']) {
                    $preferred = $choice;
                    break;
                }
            }
            $inputs['evaluation_year'] = (string) $preferred['school_year'];
            $inputs['evaluation_semester'] = (string) $preferred['semester'];
        }
        if ($method === 'GET') {
            $inputs = pefCcInitialPlanningInputs($context, $inputs, $currentTerm);
        }
        try {
            $reference = pefCcTemplateReference($config);
            $cc = pefCcBuildReferenceCc($context['records'], $reference);
        } catch (Throwable $e) {
            error_log('PEF/CC template check failed: ' . $e->getMessage());
            http_response_code(503);
            $errors[] = 'The approved planning template is unavailable. Please try again later or ask the system administrator to check deployment.';
        }
        if ($reference !== null && ($method === 'POST' || $choices !== [])) {
            try {
                $model = pefCcBuildViewModel($context, $method === 'POST' ? $raw : $inputs, $reference, $config, $currentTerm);
                $inputs = array_merge($inputs, [
                    'evaluation_year' => $model->data['evaluation']['school_year'],
                    'evaluation_semester' => (string) $model->data['evaluation']['semester'],
                    'target_year' => $model->data['planning']['target_year'],
                    'target_semester' => (string) $model->data['planning']['target_semester'],
                    'section' => $model->data['planning']['section'],
                    'subject_ids' => $model->data['planning']['subject_ids'],
                ]);
                $cc = $model->data['cc'];
                $errors = array_merge($errors, $model->data['errors']);
                $fieldErrors = $model->data['field_errors'];
                $showPreview = $method === 'POST';
                if ($showPreview && $model->data['exportable']) {
                    $preview = pefCcSignSnapshot($model, $signingKey, (int) $user['id'], $sessionBinding, $config);
                } elseif ($showPreview) {
                    http_response_code(422);
                }
            } catch (PefCcValidationException $e) {
                http_response_code(422);
                $errors = array_merge($errors, $e->errors);
                $fieldErrors = $e->fieldErrors;
            }
        }
    } catch (Throwable $e) {
        error_log('PEF/CC planning read failed: ' . $e->getMessage());
        http_response_code(503);
        $errors[] = 'Planning records could not be loaded. Please reload this page and try again.';
    }
}

$data = $model?->data;
$formPolicy = $data['policy'] ?? pefCcPlanningPolicy($context, $inputs, $currentTerm);
$manualPlanning = $formPolicy['mode'] === 'irregular';
$regularPlanning = $formPolicy['mode'] === 'regular';
$formUnavailable = !$manualPlanning && ($formPolicy['nominal'] === []
    || $formPolicy['target_year'] === null || $formPolicy['section_options'] === []);
if ($model === null && $requestValid && $context['identity'] !== []) {
    $errors = array_merge($errors, $formPolicy['errors']);
}
$selectedIds = array_map('strval', $inputs['subject_ids']);
$hasCompletedTerm = count(array_filter($choices, static fn(array $choice): bool => $choice['eligible'])) > 0;
$years = array_values(array_unique(array_map(static fn(array $choice): string => (string) $choice['school_year'], $choices)));
if ($inputs['evaluation_year'] !== '' && !in_array($inputs['evaluation_year'], $years, true)) {
    $years[] = $inputs['evaluation_year'];
}
$pageTitle = 'Enrolment Planning';
$navItems = [
    ['Home', 'index.php', ''], ['Dashboard', 'dashboard.php', ''],
    ['Grades & History', 'grades.php', ''], ['Performance Trend', 'trend.php', ''],
    ['Feedback & Support', 'feedback.php', ''], ['Settings', 'settings.php', ''],
];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= pefCcPageEscape(BASE_URL) ?>assets/css/pef-cc.css?v=<?= filemtime(__DIR__ . '/../assets/css/pef-cc.css') ?>">
<main class="main-content pef-cc-page" id="main-content">
    <div class="header">
        <div><h1>Enrolment Planning</h1><p>PEF &amp; Curriculum Checklist Planning Export</p></div>
        <a class="btn btn--quiet" href="grades.php">Back to Grades</a>
    </div>
    <p class="pef-cc-boundary"><?= pefCcPageEscape($config['disclosure']) ?></p>
    <p class="pef-cc-muted">Planning copy only. This is not an official PEF, Curriculum Checklist, approved enrolment decision, or Study Plan.</p>
    <p class="pef-cc-muted"><?= pefCcPageEscape($config['prototype_disclosure']) ?></p>

    <?php if ($errors !== []): ?>
    <section class="card pef-cc-errors" role="alert" tabindex="-1" data-testid="preview-errors">
        <h2>Review before exporting</h2>
        <ul><?php foreach (array_unique($errors) as $error): ?><li><?= pefCcPageEscape($error) ?></li><?php endforeach; ?></ul>
        <a class="btn btn--secondary" href="enrolment_planning.php">Reload planning records</a>
    </section>
    <?php endif; ?>

    <form method="post" action="enrolment_planning.php" id="pef-cc-planning-form" class="card" data-has-preview="<?= $preview !== '' ? 'true' : 'false' ?>">
        <input type="hidden" name="csrf_token" value="<?= pefCcPageEscape($csrf) ?>">
        <input type="hidden" name="action" value="preview">
        <h2>1. Recorded evaluation term</h2>
        <p class="pef-cc-muted">Choose the whole completed term to review. Recorded identity, units, grades, and Term GWA cannot be edited here.</p>
        <?php if (!$hasCompletedTerm): ?>
            <p class="pef-cc-notice" data-testid="no-completed-term">No completed evaluation term is available. PEF export is unavailable; the reference checklist can still be reviewed below.</p>
        <?php endif; ?>
        <div class="pef-cc-fields">
            <div><label for="evaluation-year">Evaluation school year</label>
                <select class="form-input" id="evaluation-year" name="evaluation_year" required aria-describedby="term-choices"<?= pefCcPageInvalid('evaluation_year', $fieldErrors) ?><?= $choices === [] ? ' disabled' : '' ?>>
                    <?php if ($years === []): ?><option value="">No recorded terms</option><?php endif; ?>
                    <?php foreach ($years as $year): ?><option value="<?= pefCcPageEscape($year) ?>"<?= $inputs['evaluation_year'] === $year ? ' selected' : '' ?>><?= pefCcPageEscape($year) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label for="evaluation-semester">Evaluation semester</label>
                <select class="form-input" id="evaluation-semester" name="evaluation_semester" required<?= pefCcPageInvalid('evaluation_semester', $fieldErrors) ?><?= $choices === [] ? ' disabled' : '' ?>>
                    <option value="1"<?= (string) $inputs['evaluation_semester'] === '1' ? ' selected' : '' ?>>First Semester</option>
                    <option value="2"<?= (string) $inputs['evaluation_semester'] === '2' ? ' selected' : '' ?>>Second Semester</option>
                </select>
            </div>
        </div>
        <details id="term-choices" class="pef-cc-details"><summary>Recorded terms and review status</summary>
            <ul><?php foreach ($choices as $choice): ?><li><?= pefCcPageEscape($choice['label']) ?> · <?= (int) $choice['count'] ?> records · <?= $choice['eligible'] ? 'Completed term' : 'Requires record review' ?><?php if (!$choice['eligible']): ?>: <?= pefCcPageEscape(implode('; ', $choice['errors'])) ?><?php endif; ?></li><?php endforeach; ?><?php if ($choices === []): ?><li>No recorded terms.</li><?php endif; ?></ul>
        </details>

        <h2 class="pef-cc-divider">2. Planning details</h2>
        <p class="pef-cc-muted">These fields affect this copy only. Subject selection does not confirm prerequisites, availability, curriculum applicability, or an approved load.</p>
        <p class="pef-cc-help" data-testid="recorded-section">Recorded current section: <?= pefCcPageEscape($formPolicy['recorded_section'] ?? 'Unavailable') ?>.</p>
        <div class="pef-cc-fields">
            <?php if ($manualPlanning): ?>
            <div><label for="target-year">Target school year</label><input class="form-input" id="target-year" name="target_year" value="<?= pefCcPageEscape($inputs['target_year']) ?>" pattern="[0-9]{4}-[0-9]{4}" maxlength="9" placeholder="YYYY-YYYY" required aria-describedby="target-year-help"<?= pefCcPageInvalid('target_year', $fieldErrors) ?>><p id="target-year-help" class="pef-cc-help">Two consecutive years, for example <?= pefCcPageEscape($currentTerm['school_year']) ?>.</p></div>
            <div><label for="target-semester">Target semester</label><select class="form-input" id="target-semester" name="target_semester" required<?= pefCcPageInvalid('target_semester', $fieldErrors) ?>><option value="1"<?= (string) $inputs['target_semester'] === '1' ? ' selected' : '' ?>>First Semester</option><option value="2"<?= (string) $inputs['target_semester'] === '2' ? ' selected' : '' ?>>Second Semester</option></select></div>
            <div><label for="proposed-section">Proposed section <span class="pef-cc-muted">(optional)</span></label><input class="form-input" id="proposed-section" name="section" value="<?= pefCcPageEscape($inputs['section']) ?>" data-max-characters="96" aria-describedby="section-help"<?= pefCcPageInvalid('section', $fieldErrors) ?>><p id="section-help" class="pef-cc-help">Single line, up to 96 Unicode characters. Professor and approval fields remain blank.</p></div>
            <?php else: ?>
            <div><label for="target-year">Target school year</label><input class="form-input" id="target-year" name="target_year" value="<?= pefCcPageEscape($formPolicy['target_year'] ?? '') ?>" readonly placeholder="Requires review" aria-describedby="target-year-help"<?= pefCcPageInvalid('target_year', $fieldErrors) ?>><p id="target-year-help" class="pef-cc-help">Determined from your verified current academic term and recorded year level.</p></div>
            <div><label for="target-semester">Target semester</label><input class="form-input" id="target-semester" value="<?= pefCcPageEscape($formPolicy['target_semester'] === null ? 'Requires review' : pefCcSemesterLabel($formPolicy['target_semester'])) ?>" readonly><input type="hidden" name="target_semester" value="<?= pefCcPageEscape($formPolicy['target_semester'] ?? '') ?>"></div>
            <div><label for="proposed-section">Suggested section</label>
                <?php if ($regularPlanning && $formPolicy['horizon'] === 'next_school_year' && $formPolicy['section_options'] !== []): ?>
                <select class="form-input" id="proposed-section" name="section" required aria-describedby="section-help"<?= pefCcPageInvalid('section', $fieldErrors) ?>>
                    <option value=""<?= !in_array($inputs['section'], $formPolicy['section_options'], true) ? ' selected' : '' ?>>Choose a verified section</option>
                    <?php foreach ($formPolicy['section_options'] as $option): ?><option value="<?= pefCcPageEscape($option) ?>"<?= $inputs['section'] === $option ? ' selected' : '' ?>><?= pefCcPageEscape($option) ?></option><?php endforeach; ?>
                </select>
                <?php else: ?>
                <input class="form-input" id="proposed-section" name="section" value="<?= pefCcPageEscape($formPolicy['section_default']) ?>" readonly placeholder="Requires review" aria-describedby="section-help"<?= pefCcPageInvalid('section', $fieldErrors) ?>>
                <?php endif; ?>
                <p id="section-help" class="pef-cc-help"><?= pefCcPageEscape($formPolicy['section_notice']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <p class="pef-cc-help"><?= pefCcPageEscape($formPolicy['disclosure']) ?></p>
        <?php if ($manualPlanning): ?>
        <label for="pef-cc-subjects">Manually selected proposed subjects</label>
        <p id="subject-help" class="pef-cc-help">Choose zero to twelve distinct subjects from the catalog. On a keyboard, hold Ctrl (Windows) or Command (Mac) to select multiple items; use Ctrl/Command-click to clear one. Nothing is recommended automatically.</p>
        <select class="form-input pef-cc-subjects" id="pef-cc-subjects" name="subject_ids[]" multiple size="8" aria-describedby="subject-help proposed-counter" data-max="12"<?= pefCcPageInvalid('subject_ids', $fieldErrors) ?>>
            <?php $knownIds = []; foreach ($context['catalog'] as $subject): if (preg_match('/^(CY|DS)/i', (string) $subject['code'])) { continue; } $knownIds[] = (string) $subject['id']; ?>
                <option value="<?= (int) $subject['id'] ?>"<?= in_array((string) $subject['id'], $selectedIds, true) ? ' selected' : '' ?>><?= pefCcPageEscape($subject['code'] . ' · ' . $subject['title'] . ' · ' . $subject['units'] . ' units') ?></option>
            <?php endforeach; ?>
            <?php foreach (array_diff($selectedIds, $knownIds) as $unknownId): ?><option value="<?= pefCcPageEscape($unknownId) ?>" selected>Unrecognized selection (<?= pefCcPageEscape($unknownId) ?>) — remove before preview</option><?php endforeach; ?>
        </select>
        <p id="proposed-counter" class="pef-cc-counter" aria-live="polite">Proposed subjects: <span data-testid="proposed-count"><?= count($inputs['subject_ids']) ?></span> of 12</p>
        <?php if ($context['catalog'] === []): ?><p class="pef-cc-muted">No catalog subjects are available to select.</p><?php endif; ?>
        <?php elseif ($regularPlanning && $formPolicy['nominal'] !== []): ?>
        <div class="data-table-scroll" tabindex="0" role="region" aria-label="Read-only nominal subject plan"><table class="data-table" data-testid="nominal-subjects"><caption>Nominal subject plan · Software Development · read-only</caption><thead><tr><th scope="col">Code</th><th scope="col">Subject</th><th scope="col">Units</th><th scope="col">Prerequisite reference</th></tr></thead><tbody>
            <?php foreach ($formPolicy['nominal'] as $subject): ?><tr><td><?= pefCcPageEscape($subject['code']) ?></td><td><?= pefCcPageEscape($subject['title']) ?></td><td><?= (int) $subject['units'] ?></td><td><?= pefCcPageEscape($subject['prerequisites']['state'] === 'unknown' ? 'Unconfirmed' : str_replace("\n", ' / ', (string) $subject['prerequisites']['raw'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php foreach ($formPolicy['nominal_ids'] as $subjectId): ?><input type="hidden" name="subject_ids[]" value="<?= (int) $subjectId ?>"><?php endforeach; ?>
        <p id="proposed-counter" class="pef-cc-counter">Proposed subjects: <span data-testid="proposed-count"><?= count($formPolicy['nominal']) ?></span> of 12</p>
        <p class="pef-cc-help">This nominal list cannot be edited. Prerequisites are informational; final compliance, availability, and submission require academic evaluator review.</p>
        <?php else: ?>
        <p class="pef-cc-notice" data-testid="nominal-unavailable"><?= pefCcPageEscape($formPolicy['errors'][0] ?? PEF_CC_NOMINAL_REVIEW) ?></p>
        <?php endif; ?>
        <div class="pef-cc-actions"><button class="btn <?= $preview !== '' ? 'btn--secondary' : 'btn--primary' ?>" type="submit" data-testid="preview-button"<?= ($choices === [] || $reference === null || !$requestValid || $formUnavailable) ? ' disabled' : '' ?>><?= $preview !== '' ? 'Update preview' : 'Preview planning copy' ?></button><span class="pef-cc-help" id="preview-progress" role="status" aria-live="polite"></span></div>
    </form>

    <?php if ($data !== null): ?>
    <section class="card" aria-labelledby="evidence-heading">
        <h2 id="evidence-heading">Recorded academic evidence</h2>
        <p class="pef-cc-muted"><?= pefCcPageEscape($data['evaluation']['school_year'] . ' · ' . $data['evaluation']['label']) ?> · <?= pefCcPageEscape($data['identity']['name']) ?></p>
        <dl class="pef-cc-coverage">
            <div><dt>Recorded subjects</dt><dd><span data-testid="recorded-count"><?= (int) $data['recorded_count'] ?></span> of 13</dd></div>
            <div><dt>Recorded Term GWA</dt><dd data-testid="term-gwa"><?= $data['gwa'] === null ? 'Unavailable' : pefCcPageEscape(number_format($data['gwa'], 2)) ?></dd></div>
            <div><dt>Eligible numeric grades</dt><dd data-testid="eligible-count"><?= (int) $data['coverage']['eligible_count'] ?></dd></div>
            <div><dt>Eligible units</dt><dd data-testid="eligible-units"><?= pefCcPageEscape($data['coverage']['eligible_units']) ?></dd></div>
        </dl>
        <p data-testid="exclusion-summary"><strong>Excluded:</strong> <?php $exclusionParts = []; foreach ($data['coverage']['excluded'] as $reason => $count) { if ($count > 0) { $exclusionParts[] = str_replace('_', ' ', (string) $reason) . ': ' . $count; } } echo pefCcPageEscape($exclusionParts === [] ? 'None' : implode(' · ', $exclusionParts)); ?></p>
        <p class="pef-cc-help">Term GWA follows the current recorded-grade calculation. Blank, NULL, textual outcomes, numeric zero, and unusable units are excluded. An unavailable GWA exports as a blank cell, never 0.00.</p>
        <p class="table-scroll-hint">Scroll the table horizontally to review every column.</p>
        <div class="data-table-scroll" tabindex="0" role="region" aria-label="Recorded evaluation subjects"><table class="data-table pef-cc-recorded-table"><caption>All selected-term records, including unavailable results</caption><thead><tr><th scope="col">Code</th><th scope="col">Subject</th><th scope="col">Units</th><th scope="col">Final grade</th><th scope="col">Outcome</th><th scope="col">GWA coverage</th></tr></thead><tbody>
        <?php foreach ($data['recorded'] as $row): ?><tr><td><?= pefCcPageEscape($row['code'] ?? 'Unavailable') ?></td><td><?= pefCcPageEscape($row['title'] ?? 'Subject requires review') ?></td><td><?= pefCcPageEscape($row['units'] ?? '—') ?></td><td><?= pefCcPageEscape(pefCcPageGrade($row['grade'] ?? null)) ?></td><td><?= pefCcPageEscape($row['outcome']) ?></td><td><?= pefCcPageEscape(str_replace('_', ' ', $row['gwa_reason'])) ?></td></tr><?php endforeach; ?>
        <?php if ($data['recorded'] === []): ?><tr><td colspan="6" class="empty-state">No records for the selected term.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>

    <?php if ($showPreview && $data !== null): ?>
    <section class="card" id="pef-cc-preview" data-testid="pef-preview" aria-labelledby="pef-preview-heading">
        <h2 id="pef-preview-heading">PEF preview · planning copy</h2>
        <p class="pef-cc-muted">The workbook uses these same reviewed values and all the recorded rows shown above.</p>
        <dl class="pef-cc-preview-fields">
            <div><dt>Student</dt><dd><?= pefCcPageEscape($data['identity']['name']) ?></dd></div>
            <div><dt>Student number</dt><dd><?= pefCcPageEscape($data['identity']['student_number']) ?></dd></div>
            <div><dt>College / program</dt><dd><?= pefCcPageEscape($data['identity']['college'] . ' / ' . $data['identity']['program']) ?></dd></div>
            <div><dt>Evaluation term</dt><dd><?= pefCcPageEscape($data['evaluation']['school_year'] . ' · ' . $data['evaluation']['label']) ?></dd></div>
            <div><dt>Target term</dt><dd><?= pefCcPageEscape($data['planning']['target_year']) ?> · <?= $data['planning']['target_semester'] === 1 ? 'First Semester' : 'Second Semester' ?></dd></div>
            <div><dt>Recorded current section</dt><dd><?= pefCcPageEscape($data['policy']['recorded_section'] ?? 'Unavailable') ?></dd></div>
            <div><dt>Proposed section</dt><dd><?= pefCcPageEscape($data['planning']['section'] === '' ? 'Blank' : $data['planning']['section']) ?></dd></div>
        </dl>
        <div class="data-table-scroll" tabindex="0" role="region" aria-label="Proposed subjects"><table class="data-table"><caption><?= $data['policy']['mode'] === 'regular' ? 'Nominal subjects · read-only' : 'Manually proposed subjects' ?> · <?= (int) $data['proposed_count'] ?> of 12</caption><thead><tr><th scope="col">Code</th><th scope="col">Subject</th><th scope="col">Units</th></tr></thead><tbody><?php foreach ($data['proposed'] as $row): ?><tr><td><?= pefCcPageEscape($row['code']) ?></td><td><?= pefCcPageEscape($row['title']) ?></td><td><?= pefCcPageEscape($row['units']) ?></td></tr><?php endforeach; ?><?php if ($data['proposed'] === []): ?><tr><td colspan="3" class="empty-state"><?= $data['policy']['mode'] === 'irregular' ? 'No proposed subjects selected. This copy may still be exported.' : 'Nominal subject plan unavailable. Evaluator review is required before export.' ?></td></tr><?php endif; ?></tbody></table></div>
        <p class="pef-cc-help">Academic Standing, Previous Academic Standing, Evaluator, Dean, signatures, and approval dates remain blank.</p>
        <p><?= pefCcPageEscape($data['disclosure']) ?></p>
        <?php if ($preview !== ''): ?>
        <form method="post" action="export_pef_cc.php" id="pef-cc-download-form">
            <input type="hidden" name="csrf_token" value="<?= pefCcPageEscape($csrf) ?>"><input type="hidden" name="action" value="download"><input type="hidden" name="preview" value="<?= pefCcPageEscape($preview) ?>">
            <?php foreach (['evaluation_year', 'evaluation_semester', 'target_year', 'target_semester', 'section'] as $key): ?><input type="hidden" name="<?= $key ?>" value="<?= pefCcPageEscape($inputs[$key]) ?>"><?php endforeach; ?>
            <?php foreach ($data['planning']['subject_ids'] as $subjectId): ?><input type="hidden" name="subject_ids[]" value="<?= (int) $subjectId ?>"><?php endforeach; ?>
            <div class="pef-cc-actions"><button class="btn btn--primary" type="submit" id="pef-cc-download" data-testid="download-button">Download Excel</button><span class="pef-cc-help">Preview expires in 10 minutes. Records are checked again before download.</span></div>
            <p id="preview-invalidated" data-testid="preview-invalidated" class="pef-cc-notice" role="status" hidden>Planning details changed. Update the preview before downloading.</p>
            <p id="download-status" data-testid="download-status" role="status" aria-live="polite"></p>
        </form>
        <?php else: ?><p class="pef-cc-notice">Export is unavailable until the record-review messages are resolved.</p><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($cc !== null): ?>
    <section class="card" data-testid="cc-preview" aria-labelledby="cc-heading">
        <h2 id="cc-heading">Curriculum Checklist reference</h2>
        <p class="pef-cc-reference-label"><?= pefCcPageEscape($cc['label']) ?></p>
        <p class="pef-cc-muted">Reference curriculum only. Applicability to your records requires academic review. Matching codes annotate recorded evidence; they do not decide enrolment, prerequisites, equivalencies, or completion.</p>
        <dl class="pef-cc-legend" aria-label="Checklist status legend"><div><dt>CMP</dt><dd>Recorded passing outcome</dd></div><div><dt>FAIL</dt><dd>Recorded numeric failed outcome</dd></div><div><dt>INC</dt><dd>Incomplete or unresolved</dd></div><div><dt>CUR</dt><dd>Current subject</dd></div><div><dt>NA</dt><dd>Final result unavailable</dd></div><div><dt>REVIEW</dt><dd>Another recorded status or ambiguous record requiring review</dd></div><div><dt>Blank</dt><dd>No matching record; not a direction to enrol</dd></div></dl>
        <p class="table-scroll-hint">Scroll the table horizontally to see units, prerequisite reference, grade, and status.</p>
        <div class="data-table-scroll" tabindex="0" role="region" aria-label="Reference curriculum checklist"><table class="data-table pef-cc-checklist-table"><caption>Exact-code recorded evidence · Reference / Requires Review</caption><thead><tr><th scope="col">Code</th><th scope="col">Reference subject</th><th scope="col">Units</th><th scope="col">Prerequisite reference</th><th scope="col">Grade</th><th scope="col">Status</th></tr></thead><tbody><?php foreach ($cc['rows'] as $row): ?><tr><td><?= pefCcPageEscape($row['code']) ?></td><td><?= pefCcPageEscape($row['title']) ?></td><td><?= pefCcPageEscape($row['units']) ?></td><td><?= pefCcPageEscape($row['prereq']) ?></td><td><?= pefCcPageEscape(pefCcPageGrade($row['grade'])) ?></td><td><?= pefCcPageEscape($row['state'] === '' ? '—' : $row['state']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <p class="pef-cc-help">ITE252 remains unresolved reference text. No prerequisite eligibility or automatic Study Plan is generated.</p>
    </section>
    <?php endif; ?>
</main>
<script src="<?= pefCcPageEscape(BASE_URL) ?>assets/js/pef-cc.js?v=<?= filemtime(__DIR__ . '/../assets/js/pef-cc.js') ?>" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
