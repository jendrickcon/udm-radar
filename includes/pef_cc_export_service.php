<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/pef_cc.php';
require_once __DIR__ . '/pef_cc_view_model.php';

/** Normalize only server-configured paths, never request input. */
function pefCcPathKey(string $path): string
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
}

function pefCcInside(string $path, string $root): bool
{
    $path = pefCcPathKey($path);
    $root = pefCcPathKey($root);
    return $path === $root || str_starts_with($path, $root . '/');
}

/** Both configured and resolved locations must remain private. */
function pefCcPrivatePaths(array $config, bool $requireTemplate = true): array
{
    $paths = [];
    foreach (['private_root', 'template_path', 'temp_path'] as $key) {
        if ($key === 'template_path' && !$requireTemplate) { continue; }
        $value = $config[$key] ?? null;
        if (!is_string($value) || str_contains($value, "\0") || preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~', $value)) {
            throw new RuntimeException('Private export storage is unavailable.');
        }
        $real = realpath($value);
        if ($real === false) {
            throw new RuntimeException('Private export storage is unavailable.');
        }
        $paths[$key] = $real;
    }
    if (!is_dir($paths['private_root']) || !is_dir($paths['temp_path'])
        || ($requireTemplate && (!is_file($paths['template_path']) || !is_readable($paths['template_path'])
            || !pefCcInside($paths['template_path'], $paths['private_root'])))
        || !is_writable($paths['temp_path'])
        || !pefCcInside($paths['temp_path'], $paths['private_root'])) {
        throw new RuntimeException('Private export storage is unavailable.');
    }
    foreach ($config['web_roots'] as $root) {
        foreach (array_unique([$root, realpath($root) ?: $root]) as $webRoot) {
            foreach ($paths as $key => $real) {
                if (pefCcInside($real, $webRoot) || pefCcInside($config[$key], $webRoot)) {
                    throw new RuntimeException('Export storage must be outside the web root.');
                }
            }
        }
    }
    // Walk each existing ancestor as well: a junction may resolve before a child
    // is opened. ACLs must prevent untrusted replacement of these directories.
    foreach ($requireTemplate ? ['template_path', 'temp_path'] : ['temp_path'] as $key) {
        $cursor = $config[$key];
        while (pefCcInside($cursor, $config['private_root']) && pefCcPathKey($cursor) !== pefCcPathKey($config['private_root'])) {
            $resolved = realpath($cursor);
            if ($resolved === false || !pefCcInside($resolved, $paths['private_root'])) {
                throw new RuntimeException('Private export storage cannot follow an escaping link.');
            }
            $cursor = dirname($cursor);
        }
    }
    return $paths;
}

function pefCcWriterDependencies(): void
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('The Excel export dependency is unavailable.');
    }
    require_once $autoload;
}

/** Fixed formula contract, shared by template and completed-output inspection. */
function pefCcAllowedFormulas(): array
{
    return [
        'PEF!G66' => 'SUM(G54:G65)',
        'CC!C16' => 'SUM(C7:C15)', 'CC!K16' => 'SUM(K7:K15)',
        'CC!C30' => 'SUM(C21:C29)', 'CC!K30' => 'SUM(K21:K29)',
        'CC!C43' => 'SUM(C35:C42)', 'CC!K43' => 'SUM(K35:K42)',
        'CC!C54' => 'SUM(C48:C53)', 'CC!K54' => 'SUM(K48:K53)',
    ];
}

/** Inspect the OOXML package before any workbook reader handles it. */
function pefCcInspectPackage(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('The planning workbook is invalid.');
    }
    $formulas = [];
    $images = [];
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || preg_match('~(?:externalLinks|vbaProject|embeddings|connections)~i', $name)) {
                throw new RuntimeException('Unsupported workbook content.');
            }
            if (str_ends_with($name, '.rels') || preg_match('~^xl/worksheets/sheet[12]\.xml$~D', $name)) {
                $xml = $zip->getFromIndex($i);
                $doc = new DOMDocument();
                $previous = libxml_use_internal_errors(true);
                try {
                    if (!is_string($xml) || str_contains(strtoupper($xml), '<!DOCTYPE') || !$doc->loadXML($xml, LIBXML_NONET)) {
                        throw new RuntimeException('The planning workbook is invalid.');
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                if (str_ends_with($name, '.rels')) {
                    foreach ($doc->getElementsByTagName('Relationship') as $relationship) {
                        if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0) {
                            throw new RuntimeException('External workbook relationships are not permitted.');
                        }
                    }
                } else {
                    $sheet = str_contains($name, 'sheet1.xml') ? 'PEF' : 'CC';
                    foreach ($doc->getElementsByTagName('c') as $cell) {
                        foreach ($cell->getElementsByTagName('f') as $formula) {
                            $formulas[$sheet . '!' . $cell->getAttribute('r')] = $formula->textContent;
                        }
                    }
                }
            }
            if (str_starts_with($name, 'xl/media/')) {
                $images[] = hash('sha256', $zip->getFromIndex($i));
            }
        }
    } finally {
        $zip->close();
    }
    $expected = pefCcAllowedFormulas();
    ksort($formulas);
    ksort($expected);
    if ($formulas !== $expected || $images !== ['e9ccd4e624b88a3423f1f6d6e5ba1e5a772e7c155762451c793a3a9b4003538a']) {
        throw new RuntimeException('The planning workbook contract does not match.');
    }
    return ['formulas' => $formulas, 'image_hashes' => $images];
}

function pefCcValidateTemplate(array $config): array
{
    $paths = pefCcPrivatePaths($config);
    $approvedHash = 'c1895897710d29672960a7414155627409dd847260236c1774f1f444d9bfed02';
    if (($config['template_version'] ?? null) !== 'v0.2-r2' || ($config['template_sha256'] ?? null) !== $approvedHash
        || !hash_equals($approvedHash, hash_file('sha256', $paths['template_path']))) {
        throw new RuntimeException('The approved planning template is unavailable.');
    }
    $package = pefCcInspectPackage($paths['template_path']);
    pefCcWriterDependencies();
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($paths['template_path']);
    try {
        if ($book->getSheetNames() !== ['PEF', 'CC']) {
            throw new RuntimeException('The planning template sheets do not match.');
        }
        $pef = $book->getSheetByName('PEF');
        $cc = $book->getSheetByName('CC');
        foreach ([$pef, $cc] as $sheet) {
            if ($sheet->getSheetState() !== 'visible' || $sheet->getPageSetup()->getOrientation() !== 'portrait'
                || $sheet->getPageSetup()->getPaperSize() !== 14 || $sheet->getPageSetup()->getFitToWidth() !== 1
                || $sheet->getPageSetup()->getFitToHeight() !== 1) {
                throw new RuntimeException('The planning template print contract does not match.');
            }
        }
        if ($pef->getPageSetup()->getPrintArea() !== 'A1:G80' || $cc->getPageSetup()->getPrintArea() !== 'A1:O73'
            || count($pef->getMergeCells()) !== 65 || count($cc->getMergeCells()) !== 22
            || count($pef->getDataValidationCollection()) !== 1 || count($cc->getDataValidationCollection()) !== 4
            || (string) $pef->getCell('A73')->getValue() !== $config['disclosure']
            || (string) $cc->getCell('A71')->getValue() !== $config['disclosure']
            || (string) $pef->getCell('A79')->getValue() !== $config['prototype_disclosure']
            || (string) $cc->getCell('A73')->getValue() !== $config['prototype_disclosure']
            || (string) $cc->getCell('A3')->getValue() !== '2022-2023 Curriculum Checklist - REFERENCE ONLY'
            || (string) $cc->getCell('D51')->getValue() !== "ITE252\nITE353") {
            throw new RuntimeException('The planning template structure does not match.');
        }
        if (!$pef->getStyle('A51')->getAlignment()->getWrapText() || $pef->getRowDimension(51)->getRowHeight() !== 96.0) {
            throw new RuntimeException('The planning section print layout does not match.');
        }
        foreach (['A10:G10', 'B23:D23', 'B35:D35', 'B54:F54', 'B65:F65', 'A73:G75', 'A79:G80'] as $merge) {
            if (!in_array($merge, $pef->getMergeCells(), true)) {
                throw new RuntimeException('The planning template merge contract does not match.');
            }
        }
        $rows = [];
        foreach ([[7, 15], [21, 29], [35, 42], [48, 53]] as [$first, $last]) {
            foreach (range($first, $last) as $row) {
                foreach ([['A', 'B', 'C', 'D', 'E', 'F', 'G'], ['I', 'J', 'K', 'L', 'M', 'N', 'O']] as $columns) {
                    [$code, $title, $units, $prereq, $grade, $remark, $status] = $columns;
                    $value = (string) $cc->getCell($code . $row)->getValue();
                    if (trim($value) === '') {
                        continue;
                    }
                    $rows[] = ['code' => $value, 'title' => (string) $cc->getCell($title . $row)->getValue(),
                        'units' => $cc->getCell($units . $row)->getValue(), 'prereq' => (string) $cc->getCell($prereq . $row)->getValue(),
                        'grade_cell' => $grade . $row, 'remark_cell' => $remark . $row, 'status_cell' => $status . $row];
                }
            }
        }
        if (count($rows) !== 58 || count(array_unique(array_column($rows, 'code'))) !== 58) {
            throw new RuntimeException('The planning template reference grid does not match.');
        }
        return ['version' => 'v0.2-r2', 'sha256' => $approvedHash, 'rows' => $rows] + $package;
    } finally {
        $book->disconnectWorksheets();
    }
}

function pefCcTemplateReference(array $config): array
{
    return pefCcValidateTemplate($config);
}

/** A literal writer: no string, including database text, becomes a formula. */
function pefCcWriteCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $address, mixed $value): void
{
    $type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NULL;
    if (is_string($value)) {
        if (!pefCcSafeText($value)) {
            throw new RuntimeException('The planning copy contains unsupported text.');
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
    } elseif (is_int($value) || is_float($value)) {
        if (!is_finite((float) $value)) {
            throw new RuntimeException('The planning copy contains an invalid number.');
        }
        $type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC;
    } elseif ($value !== null) {
        throw new RuntimeException('The planning copy contains an invalid value.');
    }
    $sheet->setCellValueExplicit($address, $value, $type);
}

function pefCcGenerateWorkbook(PefCcViewModel $model, array $config): array
{
    $data = $model->data;
    if (($data['exportable'] ?? false) !== true || count($data['recorded'] ?? []) > 13 || count($data['proposed'] ?? []) > 12
        || ($data['recorded_count'] ?? -1) !== count($data['recorded'] ?? [])
        || ($data['proposed_count'] ?? -1) !== count($data['proposed'] ?? [])
        || ($data['template_hash'] ?? null) !== $config['template_sha256']
        || ($data['template_version'] ?? null) !== $config['template_version']) {
        throw new RuntimeException('Refresh a valid planning preview before downloading.');
    }
    $started = hrtime(true);
    $checkDeadline = static function () use ($started, $config): void {
        if ((hrtime(true) - $started) / 1e9 >= $config['generation_timeout']) {
            throw new RuntimeException('Planning copy generation timed out. Please retry.');
        }
    };
    $path = '';
    $book = null;
    $previousExecutionLimit = (int) ini_get('max_execution_time');
    // PHP counts wall time on the approved Windows/XAMPP deployment. The
    // shutdown callback removes a partial output if execution times out.
    if (PHP_OS_FAMILY === 'Windows') {
        set_time_limit((int) $config['generation_timeout']);
    }
    try {
        $reference = pefCcValidateTemplate($config);
        $paths = pefCcPrivatePaths($config);
        $checkDeadline();
        $jobId = bin2hex(random_bytes(16));
        $path = $paths['temp_path'] . DIRECTORY_SEPARATOR . 'pefcc-' . $jobId . '.xlsx';
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Private export storage is unavailable.');
        }
        fclose($handle);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($path, 0600);
        }
        register_shutdown_function(static function () use ($path, $config): void {
            try { pefCcRemoveOutput($path, $config); } catch (Throwable) { /* CLI reaper handles a later retry. */ }
        });
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($paths['template_path']);
        $pef = $book->getSheetByName('PEF');
        $cc = $book->getSheetByName('CC');
        foreach (['A10' => $data['identity']['name'], 'A12' => $data['identity']['student_number'],
            'C14' => $data['identity']['program'], 'A19' => $data['evaluation']['label'], 'C19' => $data['evaluation']['school_year'],
            'C37' => $data['gwa'], 'A49' => pefCcSemesterLabel($data['planning']['target_semester']),
            'C49' => $data['planning']['target_year'], 'A51' => $data['planning']['section']] as $address => $value) {
            pefCcWriteCell($pef, $address, $value);
        }
        for ($slot = 0; $slot < 13; $slot++) {
            $row = $data['recorded'][$slot] ?? null;
            $grade = $row['grade'] ?? null;
            // Preserve a missing value as blank, numeric zero as a number, and
            // recognized textual outcomes as literal text. No academic rewrite.
            if (is_string($grade) && trim($grade) === '') {
                $grade = null;
            } elseif ($grade !== null && is_numeric($grade)) {
                $grade = (float) $grade;
            }
            foreach (['A' => $row['code'] ?? null, 'B' => $row['title'] ?? null, 'E' => isset($row['units']) ? (float) $row['units'] : null,
                'F' => $grade, 'G' => $row === null ? null : ucfirst($row['outcome'])] as $column => $value) {
                pefCcWriteCell($pef, $column . (23 + $slot), $value);
            }
        }
        for ($slot = 0; $slot < 12; $slot++) {
            $row = $data['proposed'][$slot] ?? null;
            foreach (['A' => $row['code'] ?? null, 'B' => $row['title'] ?? null, 'G' => isset($row['units']) ? (float) $row['units'] : null] as $column => $value) {
                pefCcWriteCell($pef, $column . (54 + $slot), $value);
            }
        }
        // Withheld institutional fields stay genuinely blank, even if a future
        // accidental template population is introduced (hash still must match).
        foreach (['C38', 'B68', 'B70', 'G68', 'G70'] as $address) { pefCcWriteCell($pef, $address, null); }
        for ($r = 42; $r <= 45; $r++) {
            foreach (['C', 'D', 'E', 'F'] as $c) { pefCcWriteCell($pef, $c . $r, null); }
        }
        foreach (range(68, 71) as $r) {
            foreach (['D', 'E', 'F'] as $c) { pefCcWriteCell($pef, $c . $r, null); }
        }
        $coverage = $data['coverage'];
        $excluded = [];
        $reasonLabels = ['numeric_zero' => 'zero', 'textual_status' => 'status', 'invalid_units' => 'units', 'unsupported_value' => 'unsupported', 'inc' => 'INC'];
        foreach ($coverage['excluded'] as $reason => $count) { $excluded[] = ($reasonLabels[$reason] ?? $reason) . ': ' . $count; }
        pefCcWriteCell($pef, 'A78', 'Eligible grades: ' . $coverage['eligible_count'] . '/' . $coverage['recorded_count'] . '; units: ' . $coverage['eligible_units']
            . '; excluded: ' . ($excluded === [] ? 'none' : implode(', ', $excluded)) . '.');
        pefCcWriteCell($cc, 'B1', $data['identity']['name']);
        // The blank v0.1 template says REFERENCE ONLY. The generated copy states
        // the owner's exact applicability label in this fixed title destination.
        pefCcWriteCell($cc, 'A3', '2022-2023 Curriculum Checklist - Reference / Requires Review');
        if (count($data['cc']['rows']) !== count($reference['rows']) || $data['cc']['label'] !== 'Reference / Requires Review') {
            throw new RuntimeException('The checklist reference does not match the preview.');
        }
        foreach ($reference['rows'] as $index => $ref) {
            $annotation = $data['cc']['rows'][$index];
            if (($annotation['code'] ?? null) !== $ref['code'] || !in_array($annotation['state'] ?? null, ['', 'CMP', 'FAIL', 'INC', 'CUR', 'NA', 'REVIEW'], true)) {
                throw new RuntimeException('The checklist reference does not match the preview.');
            }
            // Only the trusted template supplies these destinations.
            pefCcWriteCell($cc, $ref['grade_cell'], $annotation['grade']);
            pefCcWriteCell($cc, $ref['remark_cell'], null);
            pefCcWriteCell($cc, $ref['status_cell'], $annotation['state'] === '' ? null : $annotation['state']);
        }
        $checkDeadline();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
        $writer->setPreCalculateFormulas(true);
        $writer->save($path);
        $checkDeadline();
        clearstatcache(true, $path);
        $bytes = filesize($path);
        if ($bytes === false || $bytes < 1 || $bytes > $config['max_output_bytes']) {
            throw new RuntimeException('The generated planning copy exceeds its output limit.');
        }
        pefCcInspectPackage($path);
        $checkDeadline();
        return ['path' => $path, 'bytes' => $bytes, 'sha256' => hash_file('sha256', $path), 'job_id' => $jobId];
    } catch (Throwable $error) {
        if ($path !== '') { pefCcRemoveOutput($path, $config); }
        throw $error;
    } finally {
        $book?->disconnectWorksheets();
        if (PHP_OS_FAMILY === 'Windows') {
            set_time_limit($previousExecutionLimit);
        }
    }
}

function pefCcRemoveOutput(string $path, array $config): void
{
    $paths = pefCcPrivatePaths($config, false);
    if (preg_match('/\Apefcc-[0-9a-f]{32}\.(?:tmp|xlsx)\z/D', basename($path)) !== 1
        || pefCcPathKey(dirname($path)) !== pefCcPathKey($paths['temp_path']) || is_link($path)) {
        throw new RuntimeException('Refusing to remove an unowned export path.');
    }
    clearstatcache(true, $path);
    if (!file_exists($path)) { return; }
    if (!is_file($path) || pefCcPathKey(realpath(dirname($path)) ?: '') !== pefCcPathKey($paths['temp_path'])
        || pefCcPathKey(realpath($path) ?: '') !== pefCcPathKey($path)) {
        throw new RuntimeException('Refusing to remove an unowned export path.');
    }
    if (!unlink($path)) { throw new RuntimeException('Export cleanup requires another attempt.'); }
}

function pefCcCleanupExports(array $config, bool $delete = false, ?int $now = null): array
{
    $paths = pefCcPrivatePaths($config, false);
    $now ??= time();
    $result = ['scanned' => 0, 'eligible' => 0, 'deleted' => 0];
    foreach (new DirectoryIterator($paths['temp_path']) as $entry) {
        if ($entry->isDot() || $entry->isLink() || !$entry->isFile()
            || preg_match('/\Apefcc-[0-9a-f]{32}\.(?:tmp|xlsx)\z/D', $entry->getFilename()) !== 1) { continue; }
        $result['scanned']++;
        if ($entry->getMTime() > $now - $config['cleanup_age_seconds']) { continue; }
        $result['eligible']++;
        if ($delete) { pefCcRemoveOutput($entry->getPathname(), $config); $result['deleted']++; }
    }
    return $result;
}
