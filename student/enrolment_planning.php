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

function pefCcPagePrintIssue(string $field, array $fieldErrors): string
{
    return isset($fieldErrors[$field]) ? ' aria-invalid="true" aria-describedby="pef-print-limit-explanation" class="pef-cc-print-invalid" title="'
        . pefCcPageEscape($fieldErrors[$field]) . '"' : '';
}

function pefCcTranslateError(string $error): string
{
    return match ($error) {
        'evaluation_term_mismatch' => 'The academic record changed. Refresh the plan before downloading.',
        'section_not_allowed' => 'Choose one of the available sections.',
        'stale_preview' => 'The academic record or plan changed after preview. Refresh the workbook preview before downloading.',
        default => $error,
    };
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
            // Section 10: Fail closed on obsolete template
            $activeReviewedContract = pefCcTemplateContract('v0.2-r6');
            if (($config['template_version'] ?? '') !== 'v0.2-r6'
                || !hash_equals($activeReviewedContract['sha256'], (string) ($config['template_sha256'] ?? ''))) {
                error_log(sprintf(
                    'PEF/CC planning rejected obsolete template: expected v0.2-r6 (%s), resolved %s (%s)',
                    $activeReviewedContract['sha256'],
                    $config['template_version'] ?? 'null',
                    $config['template_sha256'] ?? 'null'
                ));
                http_response_code(409);
                $errors[] = 'The planning workbook template changed. Refresh the preview before downloading.';
                $reference = null;
            }
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
$hasCompletePredecessor = ($formPolicy['predecessor_complete'] ?? false);
$isProvisional = ($data !== null ? (($data['gwa_state'] ?? '') === 'pending') : ($formPolicy['provisional_allowed'] ?? false));
$incompleteWarning = ($formPolicy['incomplete_warning'] ?? '');
$formUnavailable = $data === null || !($data['exportable'] ?? false);
if ($model === null && $requestValid && $context['identity'] !== []) {
    $errors = array_merge($errors, $formPolicy['errors']);
}
$history = $data['history'] ?? [];
$summaryStatus = $isProvisional ? 'Provisional' : 'Ready for normal planning';
$summaryEvalTerm = ($data['evaluation']['school_year'] ?? $inputs['evaluation_year']) . ' · ' . ($data['evaluation']['label'] ?? pefCcSemesterLabel((int) ($inputs['evaluation_semester'] ?? 1)));
$summaryTargetTerm = ($data['planning']['target_year'] ?? $inputs['target_year']) . ' · ' . ((int) ($data['planning']['target_semester'] ?? $inputs['target_semester']) === 1 ? 'First Semester' : 'Second Semester');
$summarySection = ($data['planning']['section'] ?? $inputs['section']) !== ''
    ? ($data['planning']['section'] ?? $inputs['section'])
    : (($formPolicy['section_default'] ?? '') !== '' ? $formPolicy['section_default'] : 'Not assigned — evaluator review');
$summaryProposedCount = count($data['planning']['subject_ids'] ?? $inputs['subject_ids'] ?? []);
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
<?php
$hasMultipleSections = $regularPlanning && count($formPolicy['section_options'] ?? []) > 1;
$hasEditableChoices = $manualPlanning || $hasMultipleSections;
$totalRecorded = count($data['recorded'] ?? []);
$recordedGrades = count(array_filter($data['recorded'] ?? [], static fn(array $r): bool => !in_array($r['gwa_reason'] ?? '', ['null', 'blank'], true) && ($r['grade'] ?? null) !== null && ($r['grade'] ?? null) !== ''));
$termGwaDisplay = $isProvisional ? 'Pending' : (($data['gwa'] ?? null) === null ? 'Unavailable' : number_format((float) $data['gwa'], 2));
$totalProposedUnits = array_sum(array_map(static fn(array $s): int => (int) ($s['units'] ?? 0), $data['proposed'] ?? []));

// Section 3: Four mutually exclusive planning workflow states
// Priority 1 (State D): Export blocked (truly unsafe or unresolvable)
// Priority 2 (State C): Review-required provisional PDF available
// Priority 3 (State B): Provisional PDF available (incomplete evaluation grades)
// Priority 4 (State A): Normal PDF available (complete grades, valid plan)
$mode = $model->data['mode'] ?? ($isProvisional ? 'provisional' : 'normal');
$exportable = $model->data['exportable'] ?? false;

if ($formUnavailable || !$requestValid || $choices === [] || $reference === null || $mode === 'invalid' || !$exportable) {
    $planningState = 'D';
} elseif ($mode === 'review') {
    $planningState = 'C';
} elseif ($mode === 'provisional' || $isProvisional) {
    $planningState = 'B';
} else {
    $planningState = 'A';
}

$summaryStatus = match ($planningState) {
    'B' => 'Provisional',
    'C' => 'Review required',
    'D' => 'Blocked',
    default => 'Normal',
};
?>
<main class="main-content pef-cc-page" id="main-content">
    <div class="header">
        <div>
            <h1>Enrolment Planning</h1>
            <p class="pef-cc-lead">Review your current semester, check the proposed next-semester plan, and create your planning workbook.</p>
            <nav class="pef-cc-stepper" aria-label="Planning progress">
                <ol class="pef-cc-steps">
                    <li class="pef-cc-step"><span class="pef-cc-step-num">1</span> <span class="pef-cc-step-label">Current semester</span></li>
                    <li class="pef-cc-step-sep" aria-hidden="true">→</li>
                    <li class="pef-cc-step"><span class="pef-cc-step-num">2</span> <span class="pef-cc-step-label">Next-term plan</span></li>
                    <li class="pef-cc-step-sep" aria-hidden="true">→</li>
                    <li class="pef-cc-step"><span class="pef-cc-step-num">3</span> <span class="pef-cc-step-label">Workbook</span></li>
                </ol>
            </nav>
        </div>
        <a class="btn btn--quiet" href="grades.php">Back to Grades</a>
    </div>

    <!-- Plan-Status Banner (Section 3: Four mutually exclusive states, single primary banner) -->
    <?php if ($planningState === 'D'): ?>
    <div class="pef-cc-banner pef-cc-banner--blocked" role="region" aria-label="Planning status" data-testid="blocked-planning-banner">
        <h2>Planning information could not be prepared</h2>
        <div class="pef-cc-banner-alert" role="alert">
            <h3 class="pef-cc-banner-alert-title">A safe planning PDF is not available</h3>
            <?php if ($errors !== []): ?>
            <ul class="pef-cc-error-list">
                <?php foreach (array_unique($errors) as $err): ?>
                    <li><?= pefCcPageEscape(pefCcTranslateError((string) $err)) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="pef-cc-banner-alert-desc">The academic record or term configuration could not be resolved safely. Academic evaluator review is required.</p>
            <?php endif; ?>
            <div class="pef-cc-banner-alert-actions">
                <a class="btn btn--secondary" href="enrolment_planning.php">Reload planning records</a>
            </div>
        </div>
    </div>
    <?php elseif ($planningState === 'C'): ?>
    <div class="pef-cc-banner pef-cc-banner--review" role="region" aria-label="Planning status" data-testid="evaluator-review-banner">
        <h2>Planning PDF available for evaluator review</h2>
        <dl class="pef-cc-banner-grid">
            <div><dt>Current term</dt><dd><?= pefCcPageEscape($summaryEvalTerm) ?></dd></div>
            <div><dt>Next term</dt><dd><?= pefCcPageEscape($summaryTargetTerm) ?></dd></div>
            <div><dt>Final grades</dt><dd><span data-testid="final-grades-count"><?= (int) $recordedGrades ?> of <?= (int) $totalRecorded ?></span></dd></div>
            <div><dt>Term GWA</dt><dd data-testid="term-gwa"><?= pefCcPageEscape($termGwaDisplay) ?></dd></div>
            <div><dt>Planning status</dt><dd data-testid="planning-status">Review required</dd></div>
        </dl>
        <p class="pef-cc-banner-note">Some planning information requires academic evaluator review. The PDF will include only verified information and will be marked as a provisional planning copy.</p>
    </div>
    <?php elseif ($planningState === 'B'): ?>
    <div class="pef-cc-banner pef-cc-banner--provisional" role="region" aria-label="Planning status" data-testid="incomplete-term-warning">
        <h2>Provisional planning PDF available</h2>
        <dl class="pef-cc-banner-grid">
            <div><dt>Current term</dt><dd><?= pefCcPageEscape($summaryEvalTerm) ?></dd></div>
            <div><dt>Next term</dt><dd><?= pefCcPageEscape($summaryTargetTerm) ?></dd></div>
            <div><dt>Final grades</dt><dd><span data-testid="final-grades-count"><?= (int) $recordedGrades ?> of <?= (int) $totalRecorded ?></span></dd></div>
            <div><dt>Term GWA</dt><dd data-testid="term-gwa"><?= pefCcPageEscape($termGwaDisplay) ?></dd></div>
            <div><dt>Status</dt><dd data-testid="planning-status">Provisional</dd></div>
        </dl>
        <p class="pef-cc-banner-note">Some current-semester final grades are incomplete. A provisional planning PDF can still be prepared for academic evaluator review.</p>
    </div>
    <?php else: ?>
    <div class="pef-cc-banner pef-cc-banner--normal" role="region" aria-label="Planning status">
        <h2>Planning PDF available</h2>
        <dl class="pef-cc-banner-grid">
            <div><dt>Current term</dt><dd><?= pefCcPageEscape($summaryEvalTerm) ?></dd></div>
            <div><dt>Next term</dt><dd><?= pefCcPageEscape($summaryTargetTerm) ?></dd></div>
            <div><dt>Final grades</dt><dd><span data-testid="final-grades-count"><?= (int) $recordedGrades ?> of <?= (int) $totalRecorded ?></span></dd></div>
            <div><dt>Term GWA</dt><dd data-testid="term-gwa"><?= pefCcPageEscape($termGwaDisplay) ?></dd></div>
            <div><dt>Status</dt><dd data-testid="planning-status">Normal</dd></div>
        </dl>
        <p class="pef-cc-banner-note">The current evaluation term is complete. The planning PDF is ready to be prepared.</p>
    </div>
    <?php endif; ?>

    <?php if (in_array(PEF_CC_PRINT_LIMIT_MESSAGE, $errors, true)): ?>
    <div class="pef-cc-notice pef-cc-notice--warning" data-testid="preview-errors">
        <p id="pef-print-limit-explanation"><?= pefCcPageEscape(PEF_CC_PRINT_LIMIT_MESSAGE) ?> Read-only record values require record review; your safe planning choices are retained.<?php if (isset($fieldErrors['identity.name'])): ?><span class="sr-only"<?= pefCcPagePrintIssue('identity.name', $fieldErrors) ?>><?= pefCcPageEscape($data['identity']['name'] ?? '') ?></span><?php endif; ?><?php if (isset($fieldErrors['identity.student_number'])): ?><span class="sr-only"<?= pefCcPagePrintIssue('identity.student_number', $fieldErrors) ?>><?= pefCcPageEscape($data['identity']['student_number'] ?? '') ?></span><?php endif; ?></p>
    </div>
    <?php endif; ?>

    <!-- Step 1: Current semester (Section 4) -->
    <section class="card pef-cc-section" id="step-current-semester" aria-labelledby="heading-current-semester" data-testid="panel-current-semester">
        <div class="pef-cc-section-header">
            <h2 id="heading-current-semester">1. Current semester</h2>
            <button type="button" class="btn btn--secondary pef-cc-toggle-btn" id="btn-view-current-semester" aria-expanded="false" aria-controls="current-semester-details" data-testid="view-current-semester-btn">View recorded subjects</button>
        </div>
        <dl class="pef-cc-meta-row">
            <div><dt>Academic term</dt><dd><?= pefCcPageEscape($summaryEvalTerm) ?></dd></div>
            <div><dt>Recorded subjects</dt><dd><span data-testid="recorded-count"><?= (int) ($data['recorded_count'] ?? $totalRecorded) ?></span></dd></div>
            <div><dt>Final grades</dt><dd data-testid="exclusion-summary"><span data-testid="final-grades-meta"><?= (int) $recordedGrades ?> of <?= (int) $totalRecorded ?></span></dd></div>
        </dl>
        <div id="current-semester-details" class="pef-cc-collapsible" hidden data-testid="current-semester-details">
            <div class="data-table-scroll" tabindex="0" role="region" aria-label="Current semester subjects">
                <table class="data-table">
                    <caption class="pef-cc-table-caption">Recorded subjects</caption>
                    <thead>
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Units</th>
                            <th scope="col">Final outcome</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($data['recorded'] ?? []) as $index => $row): ?>
                        <tr>
                            <td<?= pefCcPagePrintIssue('recorded.' . $index . '.code', $fieldErrors) ?>><?= pefCcPageEscape($row['code'] ?? '—') ?></td>
                            <td<?= pefCcPagePrintIssue('recorded.' . $index . '.title', $fieldErrors) ?>><?= pefCcPageEscape($row['title'] ?? 'Subject requires review') ?></td>
                            <td><?= pefCcPageEscape($row['units'] ?? '—') ?></td>
                            <td><?= pefCcPageEscape(pefCcPageGrade($row['grade'] ?? null)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (($data['recorded'] ?? []) === []): ?>
                        <tr><td colspan="4" class="empty-state">No records for the current semester.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="pef-cc-step-footer">
                <div class="pef-cc-stat-cell pef-cc-stat-cell--footer">
                    <span class="pef-cc-stat-label">Term GWA</span>
                    <span class="pef-cc-stat-val" data-testid="term-gwa-footer"><?= pefCcPageEscape($termGwaDisplay) ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 2: Next-semester plan (Sections 5 & 6) -->
    <section class="card pef-cc-section" id="step-next-plan" aria-labelledby="heading-next-plan" data-testid="panel-next-plan">
        <div class="pef-cc-section-header">
            <h2 id="heading-next-plan">2. Next-semester plan</h2>
            <button type="button" class="btn btn--secondary pef-cc-toggle-btn" id="btn-review-next-plan" aria-expanded="false" aria-controls="next-plan-details" data-testid="review-next-plan-btn">View proposed subjects</button>
        </div>
        <dl class="pef-cc-meta-row">
            <div><dt>Target term</dt><dd><?= pefCcPageEscape($summaryTargetTerm) ?></dd></div>
            <div><dt>Suggested section</dt><dd data-testid="suggested-section-display"><?= pefCcPageEscape($summarySection) ?></dd></div>
            <div><dt>Proposed subjects</dt><dd data-testid="proposed-subjects-display"><?= (int) $summaryProposedCount ?></dd></div>
            <div><dt>Total units</dt><dd><span data-testid="total-units-display"><?= (int) $totalProposedUnits ?></span></dd></div>
            <div><dt>Planning mode</dt><dd data-testid="plan-mode-display"><?= pefCcPageEscape($summaryStatus) ?></dd></div>
        </dl>
        <?php if ($regularPlanning && !$hasMultipleSections): ?>
            <p class="pef-cc-note">The current section is retained for this planning copy.</p>
        <?php endif; ?>
        <div id="next-plan-details" class="pef-cc-collapsible" hidden data-testid="next-plan-details">
            <div class="data-table-scroll" tabindex="0" role="region" aria-label="Proposed next-semester subjects">
                <table class="data-table" data-testid="nominal-subjects">
                    <caption class="pef-cc-table-caption">Proposed subjects</caption>
                    <thead>
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Units</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($data['proposed'] ?? []) as $index => $row): ?>
                        <tr>
                            <td<?= pefCcPagePrintIssue('proposed.' . $index . '.code', $fieldErrors) ?>><?= pefCcPageEscape($row['code'] ?? '—') ?></td>
                            <td<?= pefCcPagePrintIssue('proposed.' . $index . '.title', $fieldErrors) ?>><?= pefCcPageEscape($row['title'] ?? 'Subject requires review') ?></td>
                            <td><?= pefCcPageEscape($row['units'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (($data['proposed'] ?? []) === []): ?>
                        <tr><td colspan="3" class="empty-state"><?= $manualPlanning ? 'No proposed subjects selected.' : 'Nominal subject plan unavailable. Evaluator review is required.' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="pef-cc-note">Prerequisite information requires evaluator confirmation. Proposed subjects are suggested from the applicable curriculum and remain subject to academic evaluator review.</p>
        </div>

        <?php if ($hasEditableChoices): ?>
        <div class="pef-cc-choices-subsection" id="pef-cc-choices" data-testid="planning-choices-panel">
            <h3>Your planning choices</h3>
            <?php if ($manualPlanning): ?>
                <p class="pef-cc-meta"><strong>Planning context:</strong> IT-IRREG</p>
                <p class="pef-cc-help">Academic evaluator review required. IT-IRREG is a curriculum pacing marker, not a disciplinary standing or academic risk classification.</p>
                <div class="pef-cc-fields">
                    <div>
                        <label for="proposed-section">Suggested section</label>
                        <select class="form-input" id="proposed-section" name="section" form="pef-cc-planning-form" aria-describedby="section-help<?= isset($fieldErrors['section']) ? ' section-error' : '' ?>"<?= pefCcPageInvalid('section', $fieldErrors) ?>>
                            <?php if (!in_array($inputs['section'], $formPolicy['section_options'], true)): ?>
                                <option value="<?= pefCcPageEscape($inputs['section']) ?>" selected data-approved="false">Unavailable choice — choose an approved option</option>
                            <?php endif; ?>
                            <?php foreach ($formPolicy['section_options'] as $option): ?>
                                <option value="<?= pefCcPageEscape($option) ?>"<?= $inputs['section'] === $option ? ' selected' : '' ?>><?= pefCcPageEscape($option === '' ? 'Not assigned — evaluator review' : ($option === 'IT-IRREG' ? 'IT-IRREG — pacing marker; evaluator review' : $option)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="section-help" class="pef-cc-help">Suggested sections are planning preferences and remain subject to availability and evaluator review.</p>
                    </div>
                </div>
                <div class="pef-cc-field-group">
                    <label for="pef-cc-subjects">Select proposed subjects (up to 12):</label>
                    <select class="form-input pef-cc-subjects" id="pef-cc-subjects" name="subject_ids[]" form="pef-cc-planning-form" multiple size="8" aria-describedby="proposed-counter" data-max="12"<?= pefCcPageInvalid('subject_ids', $fieldErrors) ?>>
                        <?php $knownIds = []; foreach ($context['catalog'] as $subject): if (preg_match('/^(CY|DS)/i', (string) $subject['code'])) { continue; } $knownIds[] = (string) $subject['id']; ?>
                            <option value="<?= (int) $subject['id'] ?>"<?= in_array((string) $subject['id'], $selectedIds, true) ? ' selected' : '' ?>><?= pefCcPageEscape($subject['code'] . ' · ' . $subject['title'] . ' · ' . $subject['units'] . ' units') ?></option>
                        <?php endforeach; ?>
                        <?php foreach (array_diff($selectedIds, $knownIds) as $unknownId): ?>
                            <option value="<?= pefCcPageEscape($unknownId) ?>" selected>Unrecognized selection (<?= pefCcPageEscape($unknownId) ?>) — remove before preview</option>
                        <?php endforeach; ?>
                    </select>
                    <p id="proposed-counter" class="pef-cc-counter" aria-live="polite">Proposed subjects: <span data-testid="proposed-count"><?= count($inputs['subject_ids']) ?></span> of 12</p>
                </div>
            <?php elseif ($hasMultipleSections): ?>
                <div class="pef-cc-fields">
                    <div>
                        <label for="proposed-section">Suggested section</label>
                        <select class="form-input" id="proposed-section" name="section" form="pef-cc-planning-form" aria-describedby="section-help<?= isset($fieldErrors['section']) ? ' section-error' : '' ?>"<?= pefCcPageInvalid('section', $fieldErrors) ?>>
                            <?php foreach ($formPolicy['section_options'] as $option): ?>
                                <option value="<?= pefCcPageEscape($option) ?>"<?= $inputs['section'] === $option ? ' selected' : '' ?>><?= pefCcPageEscape($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="section-help" class="pef-cc-help">Choose one of the verified sections available for your program.</p>
                    </div>
                </div>
                <p class="pef-cc-help">Subjects are suggested from the applicable curriculum and remain subject to academic evaluator review.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Step 3: Create planning workbook (Sections 7, 8, 9) -->
    <section class="card pef-cc-section" id="step-workbook" aria-labelledby="heading-workbook" data-testid="panel-workbook">
        <h2 id="heading-workbook">3. Create planning workbook</h2>
        <p class="pef-cc-meta"><strong>Workbook contents:</strong> Pre-Enrolment Form and Curriculum Checklist</p>
        <p class="pef-cc-help">The planning PDF uses the current record and the proposed next-term plan.</p>

        <?php if ($preview === ''): ?>
            <div class="pef-cc-actions-bar">
                <button type="submit" class="btn btn--primary" form="pef-cc-planning-form" id="btn-generate-preview" data-testid="preview-button"<?= ($planningState === 'D') ? ' disabled' : '' ?>><?= match ($planningState) {
                    'B' => 'Generate provisional planning preview',
                    'C' => 'Generate review planning preview',
                    default => 'Generate planning preview',
                } ?></button>
            </div>
            <?php if ($planningState === 'D'): ?>
                <p class="pef-cc-help pef-cc-help--state" data-testid="planning-state-help">Resolve the blocking issue above, then reload the planning records or correct the available choices.</p>
            <?php endif; ?>
        <?php else: ?>
            <div class="pef-cc-preview-card" data-testid="pef-preview">
                <span class="pef-cc-preview-ready-badge">Preview ready</span>
                <dl class="pef-cc-preview-grid">
                    <div><dt>Student</dt><dd><span<?= pefCcPagePrintIssue('identity.name', $fieldErrors) ?>><?= pefCcPageEscape($data['identity']['name']) ?></span></dd></div>
                    <div><dt>Evaluation term</dt><dd><?= pefCcPageEscape($summaryEvalTerm) ?></dd></div>
                    <div><dt>Next planning term</dt><dd><?= pefCcPageEscape($summaryTargetTerm) ?></dd></div>
                    <div><dt>Suggested section</dt><dd><?= pefCcPageEscape($summarySection) ?></dd></div>
                    <div><dt>Proposed subjects</dt><dd><?= (int) $summaryProposedCount ?></dd></div>
                    <div><dt>Planning mode</dt><dd><?= match ($planningState) { 'B' => 'Provisional', 'C' => 'Review required', default => 'Normal' } ?></dd></div>
                    <div><dt>Workbook sheets</dt><dd>Pre-Enrolment Form, Curriculum Checklist</dd></div>
                </dl>
            </div>
            <div class="pef-cc-actions-bar">
                <button class="btn btn--primary" type="submit" form="pef-cc-download-form" id="pef-cc-download" data-testid="download-button"><?= match ($planningState) {
                    'B' => 'Download provisional planning PDF',
                    'C' => 'Download review planning PDF',
                    default => 'Download planning PDF',
                } ?></button>
                <button type="submit" class="btn btn--secondary" form="pef-cc-planning-form" id="btn-refresh-preview" data-testid="refresh-preview-button">Refresh preview</button>
            </div>
            <div id="pef-cc-loading-panel" class="pef-cc-loading-panel" role="status" aria-live="polite" aria-atomic="true" hidden>
                <div class="pef-cc-loading-content">
                    <span class="pef-cc-loading-spinner" aria-hidden="true"></span>
                    <div class="pef-cc-loading-text">
                        <strong class="pef-cc-loading-title">Preparing your planning PDF</strong>
                        <p class="pef-cc-loading-desc">Creating the Pre-Enrolment Form and Curriculum Checklist. Please keep this page open.</p>
                        <p class="pef-cc-loading-note">The download will begin automatically when the PDF is ready.</p>
                    </div>
                </div>
            </div>
            <div id="pef-cc-download-feedback" class="pef-cc-notice pef-cc-notice--warning" role="alert" hidden></div>
        <?php endif; ?>

        <p id="preview-invalidated" data-testid="preview-invalidated" class="pef-cc-notice pef-cc-notice--warning" role="status" hidden>The academic record or plan changed. Refresh the preview before downloading.</p>
        <p id="download-status" data-testid="download-status" role="status" aria-live="polite"></p>
        <div id="pef-cc-live-announcer" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

        <p class="pef-cc-disclosure" data-testid="single-disclosure">This workbook is a planning aid for academic evaluator review and does not constitute official enrolment approval.<?= $isProvisional ? ' Final grades are incomplete, so the proposed subjects and section may change.' : '' ?></p>
    </section>

    <!-- Planning Form Data Transport -->
    <form method="post" action="enrolment_planning.php" id="pef-cc-planning-form" data-has-preview="<?= $preview !== '' ? 'true' : 'false' ?>">
        <input type="hidden" name="csrf_token" value="<?= pefCcPageEscape($csrf) ?>">
        <input type="hidden" name="action" value="preview">
        <input type="hidden" name="evaluation_year" value="<?= pefCcPageEscape($inputs['evaluation_year']) ?>">
        <input type="hidden" name="evaluation_semester" value="<?= pefCcPageEscape($inputs['evaluation_semester']) ?>">
        <input type="hidden" name="target_year" value="<?= pefCcPageEscape($inputs['target_year']) ?>">
        <input type="hidden" name="target_semester" value="<?= pefCcPageEscape($inputs['target_semester']) ?>">
        <?php if (!$hasMultipleSections && !$manualPlanning): ?>
            <input type="hidden" name="section" id="proposed-section" value="<?= pefCcPageEscape($inputs['section'] ?: ($formPolicy['section_default'] ?? '')) ?>">
        <?php endif; ?>
        <?php if (!$manualPlanning): ?>
            <?php foreach (($formPolicy['nominal_ids'] ?? []) as $subjectId): ?>
                <input type="hidden" name="subject_ids[]" value="<?= (int) $subjectId ?>">
            <?php endforeach; ?>
        <?php endif; ?>
    </form>

    <?php if ($preview !== ''): ?>
    <form method="post" action="export_pef_cc.php" id="pef-cc-download-form" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= pefCcPageEscape($csrf) ?>">
        <input type="hidden" name="action" value="download">
        <input type="hidden" name="preview" value="<?= pefCcPageEscape($preview) ?>">
        <?php foreach (['evaluation_year', 'evaluation_semester', 'target_year', 'target_semester', 'section'] as $key): ?>
            <input type="hidden" name="<?= $key ?>" value="<?= pefCcPageEscape($inputs[$key]) ?>">
        <?php endforeach; ?>
        <?php foreach (($data['planning']['subject_ids'] ?? []) as $subjectId): ?>
            <input type="hidden" name="subject_ids[]" value="<?= (int) $subjectId ?>">
        <?php endforeach; ?>
    </form>
    <?php endif; ?>

    <!-- Previous Academic History (Section 10: Collapsed disclosure row) -->
    <?php if ($history !== []): ?>
    <details class="pef-cc-disclosure-row" id="pef-cc-history" data-testid="pef-cc-history">
        <summary class="pef-cc-disclosure-summary">
            <span>Previous academic history</span>
            <span class="pef-cc-disclosure-meta"><?= count($history) ?> completed terms · View history</span>
        </summary>
        <div class="pef-cc-disclosure-body">
            <p class="pef-cc-note">Standing is derived using the approved UDM non-board academic-standing rule.</p>
            <div class="data-table-scroll" tabindex="0" role="region" aria-label="Previous academic terms">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Term</th>
                            <th scope="col">Term GWA</th>
                            <th scope="col">Standing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $histItem): ?>
                        <tr>
                            <td><?= pefCcPageEscape($histItem['label']) ?></td>
                            <td><?= pefCcPageEscape($histItem['gwa_display']) ?></td>
                            <td><?= pefCcPageEscape($histItem['standing']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <!-- Review another recorded term (Section 11: Collapsed secondary disclosure row) -->
    <?php if (count($choices) > 1): ?>
    <details class="pef-cc-disclosure-row pef-cc-disclosure-row--secondary" id="review-other-term">
        <summary class="pef-cc-disclosure-summary">
            <span>Review another recorded term</span>
            <span class="pef-cc-disclosure-meta">View options</span>
        </summary>
        <div class="pef-cc-disclosure-body">
            <div class="pef-cc-fields">
                <div>
                    <label for="evaluation-year">Evaluation school year</label>
                    <select class="form-input" id="evaluation-year" name="evaluation_year" form="pef-cc-planning-form">
                        <?php foreach ($years as $year): ?>
                            <option value="<?= pefCcPageEscape($year) ?>"<?= $inputs['evaluation_year'] === $year ? ' selected' : '' ?>><?= pefCcPageEscape($year) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="evaluation-semester">Evaluation semester</label>
                    <select class="form-input" id="evaluation-semester" name="evaluation_semester" form="pef-cc-planning-form">
                        <option value="1"<?= (string) $inputs['evaluation_semester'] === '1' ? ' selected' : '' ?>>First Semester</option>
                        <option value="2"<?= (string) $inputs['evaluation_semester'] === '2' ? ' selected' : '' ?>>Second Semester</option>
                    </select>
                </div>
            </div>
            <button type="submit" form="pef-cc-planning-form" class="btn btn--secondary btn--sm">Review selected term</button>
        </div>
    </details>
    <?php endif; ?>

    <!-- Audit and institutional disclosures contract -->
    <div class="pef-cc-audit-meta" hidden aria-hidden="true">
        <?= pefCcPageEscape($config['disclosure']) ?>
        <?= pefCcPageEscape($config['prototype_disclosure']) ?>
    </div>
</main>
<script src="<?= pefCcPageEscape(BASE_URL) ?>assets/js/pef-cc.js?v=<?= filemtime(__DIR__ . '/../assets/js/pef-cc.js') ?>" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
