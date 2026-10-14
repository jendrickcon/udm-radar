<?php
/**
 * TEST-DB-PREREQ-WP1: scratch-only Migration 007 and provenance regression.
 * Runs the existing migration twice; DDL commits implicitly. Synthetic legacy
 * and Decision Tree fixtures are committed for DDL testing, then explicitly
 * deleted. Other write probes are rolled back. Never rebuild the database.
 * Evidence is saved outside the checkout before any fixture or migration write.
 * Run: php tests/integration/prediction_contract_test.php
 */
declare(strict_types=1);

putenv('DB_NAME=udm_radar_scratch');
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

$totalTests = $failedTests = $skippedTests = 0;
function assertTest(bool $condition, string $message): void {
    global $totalTests, $failedTests;
    $totalTests++;
    if (!$condition) $failedTests++;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $message . PHP_EOL;
}
function sourceRequire(bool $condition, string $message): void {
    assertTest($condition, $message);
    if (!$condition) throw new RuntimeException($message);
}
function sourceScratchGuard(PDO $db): void {
    if (getenv('DB_NAME') !== 'udm_radar_scratch' || $db->query('SELECT DATABASE()')->fetchColumn() !== 'udm_radar_scratch')
        throw new RuntimeException('DATABASE SAFETY BLOCKED: scratch connection required');
}
function sourceIdentities(PDO $db, string $table): array {
    // Callers supply fixed table identifiers, never request data.
    return $db->query("SELECT id,prediction_source FROM `$table` ORDER BY id")->fetchAll();
}
function sourceDefinition(PDO $db, string $table): string {
    return $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
}
function sourceRecordDigests(PDO $db, array $tables): array {
    $digests = [];
    foreach ($tables as $table) {
        $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        $fields = implode(',', array_map(static fn($c) => '`' . $c . '`', $columns));
        // Export only hashes, not Student records, credentials or messages.
        $hashes = $db->query("SELECT SHA2(JSON_ARRAY($fields),256) FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        sort($hashes, SORT_STRING);
        $digests[$table] = ['count' => count($hashes), 'sha256' => hash('sha256', implode("\n", $hashes))];
    }
    return $digests;
}
function sourceEvidence(string $directory, string $name, array $value): void {
    if (file_put_contents($directory . DIRECTORY_SEPARATOR . $name . '.json',
        json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false)
        throw new RuntimeException('Could not preserve external evidence');
}

/** Interpret only the reviewed delimiter format used by Migration 007. */
function sourceApplyMigration(PDO $db): void {
    sourceScratchGuard($db);
    if ($db->inTransaction()) throw new RuntimeException('Migration DDL must run outside fixture transactions');
    $file = dirname(__DIR__, 2) . '/database/migrations/007_harmonize_prediction_source.sql';
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) throw new RuntimeException('Migration 007 unavailable');
    $delimiter = ';'; $buffer = ''; $statements = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line) || trim($line) === '') continue;
        if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', trim($line), $match)) {
            if (trim($buffer) !== '') throw new RuntimeException('Unexpected delimiter inside a statement');
            $delimiter = $match[1]; continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($buffer), $delimiter)) {
            $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            if (preg_match('/\bUSE\s|\b(?:CREATE|DROP)\s+DATABASE\b/i', $statement))
                throw new RuntimeException('Migration must not change the selected database');
            $statements[] = $statement; $buffer = '';
        }
    }
    if (trim($buffer) !== '' || !$statements) throw new RuntimeException('Unsupported Migration 007 format');
    foreach ($statements as $statement) {
        sourceScratchGuard($db);
        $db->exec($statement);
    }
}

function sourceSchemaContract(PDO $db, string $phase): void {
    $column = $db->query("SELECT DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='predictions' AND COLUMN_NAME='prediction_source'")->fetch();
    sourceRequire($column !== false, "$phase prediction source column exists");
    assertTest($column['DATA_TYPE'] === 'varchar', "$phase prediction source DATA_TYPE is varchar");
    assertTest($column['COLUMN_TYPE'] === 'varchar(30)', "$phase prediction source COLUMN_TYPE is varchar(30)");
    assertTest($column['IS_NULLABLE'] === 'NO', "$phase prediction source is NOT NULL");
    assertTest($column['COLUMN_DEFAULT'] === null, "$phase prediction source has no permanent default");
    $case = $db->query("SELECT DATA_TYPE,IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='academic_support_cases' AND COLUMN_NAME='prediction_source'")->fetch();
    assertTest($case !== false && $case['DATA_TYPE'] === 'varchar' && $case['IS_NULLABLE'] === 'NO'
        && (int)$case['CHARACTER_MAXIMUM_LENGTH'] >= strlen('calculation_fallback'), "$phase support-case source has compatible VARCHAR type");
    foreach (['predictions'=>'chk_predictions_source_valid', 'academic_support_cases'=>'chk_support_cases_source_valid'] as $table=>$name) {
        $statement = $db->prepare('SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
        $statement->execute([$table, $name]); $clauses = $statement->fetchAll(PDO::FETCH_COLUMN);
        assertTest(count($clauses) === 1, "$phase $name exists exactly once");
    }
}

function sourceRejectedWrite(PDO $db, string $table, array $row, string $constraint, string $label): void {
    sourceScratchGuard($db);
    $before = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $db->exec('SAVEPOINT source_invalid_probe');
    $correctError = false;
    try {
        $fields = implode(',', array_keys($row));
        $marks = implode(',', array_fill(0, count($row), '?'));
        $db->prepare("INSERT INTO `$table` ($fields) VALUES ($marks)")->execute(array_values($row));
    } catch (PDOException $error) {
        $code = (int)($error->errorInfo[1] ?? 0);
        $correctError = ($code === 4025 && ($error->errorInfo[0] ?? '') === '23000' && str_contains($error->getMessage(), $constraint))
            || (!array_key_exists('prediction_source', $row) && $code === 1364 && str_contains($error->getMessage(), 'prediction_source'));
        if (!$correctError) echo '[DIAGNOSTIC] Unexpected rejection: ' . $error->getMessage() . PHP_EOL;
    } finally {
        $after = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        // Count before rollback: accepted invalid rows cannot masquerade as a pass.
        assertTest($correctError, "$label rejected by intended source constraint or required-column rule");
        assertTest($after === $before, "$label inserted no row");
        $db->exec('ROLLBACK TO SAVEPOINT source_invalid_probe');
        $db->exec('RELEASE SAVEPOINT source_invalid_probe');
    }
}

function sourceStorageContract(PDO $db, int $studentId, string $phase): void {
    sourceScratchGuard($db);
    $db->beginTransaction();
    try {
        $term = getCurrentTerm();
        $rows = [
            'predictions' => ['student_id'=>$studentId, 'predicted_gwa'=>'2.75', 'risk_level'=>'LOW', 'data_completeness'=>'complete', 'is_provisional'=>0],
            'academic_support_cases' => ['student_id'=>$studentId, 'trigger_risk_level'=>'HIGH', 'trigger_predicted_gwa'=>'1.50',
                'school_year'=>$term['school_year'], 'semester'=>(int)$term['semester'], 'status'=>'needs_review'],
        ];
        foreach ($rows as $table=>$base) {
            foreach (['heuristic','decision_tree','calculation_fallback'] as $source) {
                $row = $base + ['prediction_source'=>$source];
                $fields = implode(',', array_keys($row)); $marks = implode(',', array_fill(0, count($row), '?'));
                $db->prepare("INSERT INTO `$table` ($fields) VALUES ($marks)")->execute(array_values($row));
                $id = (int)$db->lastInsertId();
                assertTest($id > 0, "$phase $table accepts $source");
                $read = $db->prepare("SELECT prediction_source FROM `$table` WHERE id=?"); $read->execute([$id]);
                assertTest($read->fetchColumn() === $source, "$phase $table stores $source exactly without coercion");
            }
            $constraint = $table === 'predictions' ? 'chk_predictions_source_valid' : 'chk_support_cases_source_valid';
            foreach (['fallback_blend', '', 'random_neural_net', 'DECISION_TREE', 'decision_tree '] as $source)
                sourceRejectedWrite($db, $table, $base + ['prediction_source'=>$source], $constraint, "$phase $table " . var_export($source, true));
            sourceRejectedWrite($db, $table, $base, $constraint, "$phase $table omitted source");
        }
    } finally { if ($db->inTransaction()) $db->rollBack(); }
}

$db = null; $before = []; $originalSources = []; $tables = [];
$fixtureStudentId = 0; $fixtureIds = []; $fixtureLogin = ''; $evidence = null;
try {
    // All connections, including future getDB() calls, are explicitly scratch.
    $db = new PDO('mysql:host=127.0.0.1;dbname=udm_radar_scratch;charset=utf8mb4', 'root', '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    sourceScratchGuard($db);
    $server = $db->query('SELECT DATABASE() AS target,VERSION() AS version,@@sql_mode AS sql_mode,@@foreign_key_checks AS foreign_keys,@@check_constraint_checks AS checks')->fetch();
    echo 'Target/runtime: ' . json_encode($server, JSON_THROW_ON_ERROR) . PHP_EOL;
    sourceRequire((int)$server['foreign_keys'] === 1 && (int)$server['checks'] === 1, 'Foreign keys and CHECK enforcement enabled');
    $nonTransactional = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('users','predictions','academic_support_cases') AND ENGINE<>'InnoDB'")->fetchColumn();
    sourceRequire($nonTransactional === 0, 'Fixture tables use InnoDB');
    $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $before = sourceRecordDigests($db, $tables);
    $schema = [];
    foreach ($tables as $table) $schema[$table] = sourceDefinition($db, $table);
    foreach (['predictions','academic_support_cases'] as $table) {
        $originalSources[$table] = sourceIdentities($db, $table);
        $invalid = (int)$db->query("SELECT COUNT(*) FROM `$table` WHERE prediction_source IS NULL
            OR BINARY prediction_source NOT IN ('heuristic','decision_tree','calculation_fallback')")->fetchColumn();
        sourceRequire($invalid === 0, "$table preflight has zero invalid source values");
    }
    $root = getenv('PREDICTION_CONTRACT_EVIDENCE_DIR') ?: sys_get_temp_dir();
    if (!is_dir($root) || realpath($root) === false) throw new RuntimeException('External evidence root unavailable');
    $normalizedRoot = strtolower(str_replace('\\', '/', realpath($root)));
    $checkout = strtolower(str_replace('\\', '/', realpath(dirname(__DIR__, 2))));
    if ($normalizedRoot === $checkout || str_starts_with($normalizedRoot, $checkout . '/')) throw new RuntimeException('Evidence must be outside the checkout');
    $evidence = $root . DIRECTORY_SEPARATOR . 'prediction-source-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    if (!mkdir($evidence, 0700)) throw new RuntimeException('External evidence directory could not be created');
    sourceEvidence($evidence, 'before', ['server'=>$server, 'schema'=>$schema, 'sources'=>$originalSources, 'records'=>$before]);
    echo "External schema/provenance snapshot: $evidence\n";

    // DDL implicitly commits: these owned fixtures must survive both migration
    // executions. The finally block deletes only their exact IDs and login.
    sourceScratchGuard($db); $db->beginTransaction();
    $fixtureLogin = 'SRC-CONTRACT-' . bin2hex(random_bytes(6));
    $user = $db->prepare("INSERT INTO users (user_id,password_hash,role,first_name,last_name,is_active)
        VALUES (?, 'synthetic-unusable-password', 'student', 'Synthetic', 'Source Contract Fixture', 0)");
    $user->execute([$fixtureLogin]); $fixtureStudentId = (int)$db->lastInsertId();
    $insert = $db->prepare("INSERT INTO predictions (student_id,predicted_gwa,risk_level,prediction_source,data_completeness,is_provisional)
        VALUES (?,2.75,'LOW',?,'complete',0)");
    foreach (['heuristic','decision_tree'] as $source) {
        $insert->execute([$fixtureStudentId,$source]); $fixtureIds[$source] = (int)$db->lastInsertId();
    }
    $db->commit();
    $preMigrationSources = ['predictions'=>sourceIdentities($db,'predictions'), 'academic_support_cases'=>sourceIdentities($db,'academic_support_cases')];
    sourceEvidence($evidence, 'before-migration-with-fixtures', $preMigrationSources);
    $firstDefinitions = [];
    foreach (['First application','Rerun'] as $phase) {
        sourceApplyMigration($db);
        sourceSchemaContract($db, $phase);
        foreach (['predictions','academic_support_cases'] as $table) {
            $actual = sourceIdentities($db, $table);
            assertTest(array_column($actual,'id') === array_column($preMigrationSources[$table],'id'), "$phase $table exact primary-key set preserved");
            assertTest(count($actual) === count($preMigrationSources[$table]), "$phase $table count preserved");
            assertTest($actual === $preMigrationSources[$table], "$phase $table every existing source value preserved");
            $definition = sourceDefinition($db, $table);
            // Rolled-back probes can advance InnoDB identity counters.
            $stableDefinition = preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $definition);
            if ($phase === 'Rerun') assertTest($stableDefinition === preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $firstDefinitions[$table]), "$phase $table definition unchanged (identity sequence excluded)");
            else $firstDefinitions[$table] = $definition;
        }
        foreach ($fixtureIds as $source=>$id) {
            $read = $db->prepare('SELECT prediction_source FROM predictions WHERE id=?'); $read->execute([$id]);
            assertTest($read->fetchColumn() === $source, "$phase controlled $source fixture provenance preserved");
        }
        sourceStorageContract($db, $fixtureStudentId, $phase);
    }
    sourceEvidence($evidence, 'post-migration', ['schema'=>$firstDefinitions,
        'sources'=>['predictions'=>sourceIdentities($db,'predictions'), 'academic_support_cases'=>sourceIdentities($db,'academic_support_cases')]]);

    // Preserve the existing optional Python contract check; no model is trained
    // or written. Its absence is explicitly counted as a skip, never a pass.
    $pythonExe = dirname(__DIR__, 2) . '/python_ml/venv/Scripts/python.exe';
    if (file_exists($pythonExe)) {
        $pyTestScript = <<<'PYCODE'
import sys
sys.path.insert(0, 'python_ml')
from decision_tree import predict, extract_features, _fallback_predict
r1 = predict({'historical_gwa': 2.75, 'current_prelim_point_avg': 3.0, 'failed_subjects_count': 0, 'irregular_semesters': 0})
assert r1['source'] == 'decision_tree'
r2 = predict({'historical_gwa': 2.75, 'current_prelim_avg': 3.0, 'failed_subjects_count': 0, 'irregular_semesters': 0})
assert r2['source'] == 'decision_tree'
try:
    predict({'current_prelim_point_avg': 3.0, 'failed_subjects_count': 0, 'irregular_semesters': 0})
    sys.exit(101)
except ValueError:
    pass
try:
    predict({'historical_gwa': 2.50, 'failed_subjects_count': 0, 'irregular_semesters': 0})
    sys.exit(102)
except ValueError:
    pass
f1 = _fallback_predict(2.50, 3.50)
assert f1['predicted_gwa'] == 3.00 and f1['source'] == 'fallback_blend'
print('PY_OK')
PYCODE;
        $temporary = tempnam(sys_get_temp_dir(), 'py_contract_');
        if ($temporary === false) throw new RuntimeException('Python temporary file unavailable');
        try {
            file_put_contents($temporary, $pyTestScript);
            $process = proc_open([$pythonExe,$temporary], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__,2));
            if (!is_resource($process)) throw new RuntimeException('Python process unavailable');
            fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
            if ($exit !== 0) echo '[DIAGNOSTIC] Python: ' . $error . PHP_EOL;
            assertTest($exit === 0 && str_contains($output,'PY_OK'), 'Python canonical payload, alias, missing-feature and fallback contracts');
        } finally { unlink($temporary); }
    } else { $skippedTests++; echo "[SKIP] Python virtual environment unavailable at $pythonExe\n"; }
} catch (Throwable $error) {
    assertTest(false, 'Prediction source integration: ' . $error->getMessage());
} finally {
    if ($db !== null) {
        if ($db->inTransaction()) $db->rollBack();
        try {
            sourceScratchGuard($db);
            if ($fixtureStudentId > 0) {
                $db->beginTransaction();
                $remove = $db->prepare('DELETE FROM predictions WHERE id=? AND student_id=?');
                foreach ($fixtureIds as $id) $remove->execute([$id,$fixtureStudentId]);
                $db->prepare('DELETE FROM users WHERE id=? AND user_id=?')->execute([$fixtureStudentId,$fixtureLogin]);
                $db->commit();
                $remaining = $db->prepare('SELECT COUNT(*) FROM users WHERE id=? AND user_id=?'); $remaining->execute([$fixtureStudentId,$fixtureLogin]);
                assertTest((int)$remaining->fetchColumn() === 0, 'Committed synthetic fixture user cleaned up');
            }
            foreach ($originalSources as $table=>$sources)
                assertTest(sourceIdentities($db,$table) === $sources, "$table final exact identity/provenance snapshot restored");
            $after = sourceRecordDigests($db, $tables);
            foreach ($before as $table=>$digest) assertTest(($after[$table] ?? null) === $digest, "$table all pre-existing records preserved and fixtures cleaned up");
            if ($evidence !== null) sourceEvidence($evidence,'after-cleanup',['records'=>$after,
                'sources'=>['predictions'=>sourceIdentities($db,'predictions'),'academic_support_cases'=>sourceIdentities($db,'academic_support_cases')]]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            assertTest(false, 'Cleanup/preservation: ' . $error->getMessage());
        }
    }
}
echo 'SUMMARY: Passed: ' . ($totalTests-$failedTests) . ", Failed: $failedTests, Skipped: $skippedTests\n";
exit($failedTests ? 1 : 0);
