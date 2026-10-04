<?php
declare(strict_types=1);

if (($argv[1] ?? '') === '--worker') {
    putenv('DB_NAME=udm_radar_scratch');

    $root = dirname(__DIR__, 2);
    $referralId = (int)($argv[2] ?? 0);
    $facultyId = (int)($argv[3] ?? 0);
    $message = base64_decode((string)($argv[4] ?? ''), true);
    if ($referralId <= 0 || $facultyId <= 0 || $message === false) {
        fwrite(STDERR, "Invalid worker arguments.\n");
        exit(2);
    }

    session_id(bin2hex(random_bytes(16)));
    session_start();
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION = [
        'user_id' => $facultyId,
        'role' => 'faculty',
        'first_name' => 'Test',
        'last_name' => 'Faculty',
        'csrf_token' => $csrfToken,
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['SCRIPT_NAME'] = '/faculty/feedback.php';
    $_POST = [
        'action' => 'issue_notice',
        'referral_id' => (string)$referralId,
        'message_to_student' => $message,
        'csrf_token' => $csrfToken,
    ];

    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    ob_start();
    require $root . '/faculty/feedback.php';
    ob_end_clean();

    echo json_encode([
        'success' => isset($success) && $success !== '',
        'error' => $error ?? '',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

putenv('DB_NAME=udm_radar_scratch');

require_once dirname(__DIR__, 2) . '/config/constants.php';

$scratchDb = 'udm_radar_scratch';
$testsRun = 0;
$failures = 0;
$caseId = 0;
$locker = null;
$workers = [];

function assertCondition(bool $condition, string $message): void
{
    global $testsRun, $failures;
    $testsRun++;
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        $failures++;
        echo "[FAIL] $message\n";
    }
}

function startNoticeWorker(int $referralId, int $facultyId, string $message): array
{
    $command = '"' . PHP_BINARY . '" "' . __FILE__ . '" --worker '
        . $referralId . ' ' . $facultyId . ' ' . base64_encode($message);
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'faculty');

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start a notice worker.');
    }

    fclose($pipes[0]);
    $ready = fgets($pipes[1]);
    if (trim((string)$ready) !== 'READY') {
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new RuntimeException('Notice worker did not become ready: ' . $stderr);
    }

    return ['process' => $process, 'pipes' => $pipes];
}

function finishNoticeWorker(array &$worker): array
{
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    $exitCode = proc_close($worker['process']);
    $worker = ['process' => null, 'pipes' => []];

    return [
        'result' => json_decode(trim($stdout), true),
        'stderr' => $stderr,
        'exit_code' => $exitCode,
    ];
}

try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=' . $scratchDb . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== $scratchDb) {
        throw new RuntimeException('Connected database is not the approved scratch database.');
    }

    $handlerPath = dirname(__DIR__, 2) . '/faculty/feedback.php';
    $handlerSource = file_get_contents($handlerPath);
    $actionStart = strpos($handlerSource, "elseif (\$action === 'issue_notice')");
    $actionEnd = $actionStart === false
        ? false
        : strpos($handlerSource, "elseif (\$action === 'submit_report')", $actionStart);
    $actionSource = ($actionStart !== false && $actionEnd !== false)
        ? substr($handlerSource, $actionStart, $actionEnd - $actionStart)
        : '';
    $transactionPosition = strpos($actionSource, '$db->beginTransaction();');
    $lockedReadPosition = strpos($actionSource, 'SELECT case_id, status FROM support_case_referrals');
    $hasConditionalUpdate = strpos($actionSource, "AND status = 'needs_review'") !== false;
    $hasRowCountGuard = strpos($actionSource, '$stmtUpdate->rowCount() !== 1') !== false;

    assertCondition(
        $transactionPosition !== false
            && $lockedReadPosition !== false
            && $transactionPosition < $lockedReadPosition
            && $hasConditionalUpdate
            && $hasRowCountGuard,
        'Notice handler locks and conditionally updates the referral inside one transaction'
    );
    if ($failures > 0) {
        exit(1);
    }

    $faculty = $db->query("SELECT id FROM users WHERE role = 'faculty' AND is_active = 1 LIMIT 1")->fetch();
    $student = $db->query("SELECT id FROM users WHERE role = 'student' AND is_active = 1 LIMIT 1")->fetch();
    $subject = $db->query('SELECT id FROM subjects LIMIT 1')->fetch();
    if (!$faculty || !$student || !$subject) {
        throw new RuntimeException('Scratch database is missing active faculty/student or subject fixtures.');
    }

    $term = getCurrentTerm();
    $stmtCase = $db->prepare("INSERT INTO academic_support_cases
        (student_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, status)
        VALUES (?, 'HIGH', 1.72, 'concurrency_test', ?, ?, 'needs_review')");
    $stmtCase->execute([(int)$student['id'], $term['school_year'], (int)$term['semester']]);
    $caseId = (int)$db->lastInsertId();

    $stmtReferral = $db->prepare("INSERT INTO support_case_referrals
        (case_id, faculty_id, subject_id, section, subject_risk_level, latest_term_checked, latest_term_grade, status)
        VALUES (?, ?, ?, ?, 'HIGH', 'prelim', 70.00, 'needs_review')");
    $section = 'CONCURRENCY-' . strtoupper(bin2hex(random_bytes(4)));
    $facultyId = (int)$faculty['id'];
    $stmtReferral->execute([$caseId, $facultyId, (int)$subject['id'], $section]);
    $referralId = (int)$db->lastInsertId();

    $locker = new PDO('mysql:host=127.0.0.1;dbname=' . $scratchDb . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $locker->beginTransaction();
    $lockReferral = $locker->prepare('SELECT id FROM support_case_referrals WHERE id = ? FOR UPDATE');
    $lockReferral->execute([$referralId]);

    echo "Submitting two overlapping faculty notices on $scratchDb.\n";
    $workers[] = startNoticeWorker($referralId, $facultyId, 'Concurrent notice A');
    $workers[] = startNoticeWorker($referralId, $facultyId, 'Concurrent notice B');
    usleep(250000);
    assertCondition(
        proc_get_status($workers[0]['process'])['running']
            && proc_get_status($workers[1]['process'])['running'],
        'Both notice requests are waiting while the referral row is locked'
    );
    $locker->commit();

    $firstResult = finishNoticeWorker($workers[0]);
    $secondResult = finishNoticeWorker($workers[1]);
    assertCondition($firstResult['exit_code'] === 0 && $secondResult['exit_code'] === 0, 'Both concurrent handler requests complete');
    assertCondition(
        is_array($firstResult['result'])
            && is_array($secondResult['result'])
            && ((bool)$firstResult['result']['success'] !== (bool)$secondResult['result']['success']),
        'Exactly one overlapping notice submission succeeds'
    );

    $stmtState = $db->prepare('SELECT status FROM support_case_referrals WHERE id = ?');
    $stmtState->execute([$referralId]);
    assertCondition($stmtState->fetchColumn() === 'action_taken', 'Referral transitions to action_taken once');

    $stmtActionCount = $db->prepare("SELECT COUNT(*) FROM support_actions WHERE case_id = ? AND action_type = 'academic_notice_sent'");
    $stmtActionCount->execute([$caseId]);
    $actionCount = (int)$stmtActionCount->fetchColumn();
    assertCondition($actionCount === 1, 'One support action is recorded for the overlapping submissions');

    $stmtHistoryCount = $db->prepare("SELECT COUNT(*) FROM support_status_history WHERE case_id = ? AND old_status = 'needs_review' AND new_status = 'action_taken'");
    $stmtHistoryCount->execute([$caseId]);
    $historyCount = (int)$stmtHistoryCount->fetchColumn();
    assertCondition($historyCount === 1, 'One parent-case transition is recorded');

    $replay = startNoticeWorker($referralId, $facultyId, 'Replay notice');
    $replayResult = finishNoticeWorker($replay);
    assertCondition(
        $replayResult['exit_code'] === 0
            && is_array($replayResult['result'])
            && !$replayResult['result']['success']
            && str_contains($replayResult['result']['error'], 'already been processed'),
        'Replaying an already-processed referral is rejected'
    );

    $stmtActionCount->execute([$caseId]);
    assertCondition((int)$stmtActionCount->fetchColumn() === 1, 'Replay creates no additional support action');
    $stmtHistoryCount->execute([$caseId]);
    assertCondition((int)$stmtHistoryCount->fetchColumn() === 1, 'Replay creates no additional parent-case transition');
} catch (Throwable $e) {
    $failures++;
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . PHP_EOL);
} finally {
    if ($locker instanceof PDO && $locker->inTransaction()) {
        $locker->rollBack();
    }

    foreach ($workers as &$worker) {
        if (is_resource($worker['process'] ?? null)) {
            proc_terminate($worker['process']);
            foreach ($worker['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($worker['process']);
        }
    }
    unset($worker);

    if (isset($db) && $caseId > 0) {
        try {
            foreach (['support_status_history', 'support_actions', 'support_case_referrals'] as $table) {
                $stmtCleanup = $db->prepare("DELETE FROM $table WHERE case_id = ?");
                $stmtCleanup->execute([$caseId]);
            }
            $stmtCleanup = $db->prepare('DELETE FROM academic_support_cases WHERE id = ?');
            $stmtCleanup->execute([$caseId]);
        } catch (Throwable $e) {
            $failures++;
            fwrite(STDERR, 'CLEANUP ERROR: ' . $e->getMessage() . PHP_EOL);
        }
    }
}

echo "\nFaculty notice concurrency scenarios: $testsRun; failures: $failures\n";
exit($failures > 0 ? 1 : 0);