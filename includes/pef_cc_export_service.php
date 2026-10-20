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
    $contract = pefCcTemplateContract((string) ($config['template_version'] ?? ''));
    $approvedHash = $contract['sha256'];
    if (($config['template_sha256'] ?? null) !== $approvedHash
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
        $expectedPrintArea = $contract['print_area'] ?? 'A1:G80';
        $expectedPefMerges = in_array($config['template_version'], ['v0.2-r5', 'v0.2-r6'], true) ? 61 : 65;
        $expectedCcPrintArea = $contract['cc_print_area'] ?? (($config['template_version'] === 'v0.2-r6') ? 'A1:O70' : 'A1:O73');
        $expectedCcMerges = ($config['template_version'] === 'v0.2-r6') ? 18 : 22;
        if ($pef->getPageSetup()->getPrintArea() !== $expectedPrintArea || $cc->getPageSetup()->getPrintArea() !== $expectedCcPrintArea
            || count($pef->getMergeCells()) !== $expectedPefMerges || count($cc->getMergeCells()) !== $expectedCcMerges
            || count($pef->getDataValidationCollection()) !== 1 || count($cc->getDataValidationCollection()) !== 4
            || (string) $cc->getCell('A3')->getValue() !== '2022-2023 Curriculum Checklist - REFERENCE ONLY'
            || (string) $cc->getCell('D51')->getValue() !== "ITE252\nITE353") {
            throw new RuntimeException('The planning template structure does not match.');
        }
        if ($config['template_version'] === 'v0.2-r6') {
            if ($cc->getCell('A67')->getValue() !== null || $cc->getCell('A69')->getValue() !== null
                || $cc->getCell('A71')->getValue() !== null || $cc->getCell('A73')->getValue() !== null
                || (string) $cc->getCell('B66')->getValue() !== 'Prepared by'
                || (string) $cc->getCell('J66')->getValue() !== 'Noted by'
                || $cc->getCell('B69')->getValue() !== null
                || (string) $cc->getCell('B70')->getValue() !== 'Evaluator'
                || (string) $cc->getCell('J70')->getValue() !== 'Dean'
                || (string) $cc->getCell('J69')->getValue() !== ($config['signatories']['dean_name'] ?? 'MR. FRANCIS ARLANDO L. ATIENZA')) {
                throw new RuntimeException('The planning template structure does not match.');
            }
        } else {
            if ((string) $cc->getCell('A71')->getValue() !== $config['disclosure']
                || (string) $cc->getCell('A73')->getValue() !== $config['prototype_disclosure']) {
                throw new RuntimeException('The planning template structure does not match.');
            }
        }
        if (in_array($config['template_version'], ['v0.2-r4', 'v0.2-r5', 'v0.2-r6'], true)) {
            if ($pef->getCell('A73')->getValue() !== null || $pef->getCell('A79')->getValue() !== null
                || (string) $pef->getCell('B42')->getValue() !== 'First Year'
                || (string) $pef->getCell('B45')->getValue() !== 'Fourth Year'
                || (string) $pef->getCell('B68')->getValue() !== 'MS. CHRISTINA P. ATAL'
                || (string) $pef->getCell('B70')->getValue() !== 'MR. FRANCIS ARLANDO L. ATIENZA'
                || (string) $pef->getCell('D69')->getValue() !== 'Signature'
                || (string) $pef->getCell('G69')->getValue() !== 'Date'
                || (string) $pef->getCell('D71')->getValue() !== 'Signature'
                || (string) $pef->getCell('G71')->getValue() !== 'Date'
                || $pef->getCell('D68')->getValue() !== null
                || $pef->getCell('G68')->getValue() !== null
                || $pef->getCell('D70')->getValue() !== null
                || $pef->getCell('G70')->getValue() !== null) {
                throw new RuntimeException('The planning template structure does not match.');
            }
        } else {
            if ((string) $pef->getCell('A73')->getValue() !== $config['disclosure']
                || (string) $pef->getCell('A79')->getValue() !== $config['prototype_disclosure']) {
                throw new RuntimeException('The planning template structure does not match.');
            }
        }
        $alignment = $pef->getStyle('A51')->getAlignment();
        if ($alignment->getWrapText() !== $contract['section_wrap']
            || $pef->getRowDimension(51)->getRowHeight() !== $contract['section_height']
            || $alignment->getHorizontal() !== 'center' || $alignment->getVertical() !== 'center'
            || $alignment->getShrinkToFit() || $pef->getStyle('A51')->getFont()->getSize() !== 18.0
            || $pef->getRowDimension(52)->getRowHeight() !== 12.0
            || !isset($pef->getMergeCells()['A51:G51'], $pef->getMergeCells()['A52:G52'])) {
            throw new RuntimeException('The planning section print layout does not match.');
        }
        foreach (range(54, 65) as $row) {
            if ($pef->getRowDimension($row)->getRowHeight() !== 12.0) {
                throw new RuntimeException('The planning subject row capacity does not match.');
            }
        }
        $requiredMerges = in_array($config['template_version'], ['v0.2-r5', 'v0.2-r6'], true)
            ? ['A10:G10', 'B23:D23', 'B35:D35', 'B54:F54', 'B65:F65']
            : ['A10:G10', 'B23:D23', 'B35:D35', 'B54:F54', 'B65:F65', 'A73:G75', 'A79:G80'];
        foreach ($requiredMerges as $merge) {
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
        return ['version' => $config['template_version'], 'sha256' => $approvedHash, 'rows' => $rows] + $package;
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
        || ($data['template_version'] ?? null) !== $config['template_version']
        || ($data['print_contract'] ?? null) !== $config['printable']
        || (($data['evaluation_complete'] ?? false) !== true && ($data['gwa'] ?? null) !== null)
        || (($data['mode'] ?? null) === 'normal'
            && (($data['evaluation_complete'] ?? false) !== true || !is_numeric($data['gwa'] ?? null)))
        || !in_array($data['mode'] ?? null, ['normal', 'provisional', 'review'], true)) {
        throw new RuntimeException('Refresh a valid planning preview before downloading.');
    }
    if (($data['policy']['version'] ?? null) !== PEF_CC_PLANNING_POLICY_VERSION
        || ($data['identity']['role'] ?? null) !== 'student' || ($data['identity']['is_active'] ?? null) !== 1
        || pefCcPositiveId($data['identity']['id'] ?? null) === null
        || ($data['identity']['record_status'] ?? null) !== 'Active'
        || ($data['policy']['recorded_status'] ?? null) !== ($data['identity']['status'] ?? null)
        || ($data['policy']['recorded_section'] ?? null) !== ($data['identity']['section'] ?? null)
        || ($data['policy']['recorded_year_level'] ?? null) !== ($data['identity']['year_level'] ?? null)
        || ($data['policy']['recorded_lifecycle'] ?? null) !== ($data['identity']['record_status'] ?? null)
        || !pefCcSectionIsApproved($data['policy'] ?? [], $data['planning']['section'] ?? null, $data['planning'] ?? [])) {
        throw new PefCcValidationException([PEF_CC_SECTION_MESSAGE], ['section' => PEF_CC_SECTION_MESSAGE]);
    }
    $printErrors = pefCcPrintableErrors($data['identity'], $data['recorded'], $data['proposed'], $data['planning']['section'], $config);
    if ($printErrors !== []) {
        throw new PefCcValidationException([PEF_CC_PRINT_LIMIT_MESSAGE], $printErrors);
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
            $outcome = $row === null ? null : ($row['outcome'] === 'unavailable' ? null : ucfirst($row['outcome']));
            foreach (['A' => $row['code'] ?? null, 'B' => $row['title'] ?? null, 'E' => isset($row['units']) ? (float) $row['units'] : null,
                'F' => $grade, 'G' => $outcome] as $column => $value) {
                pefCcWriteCell($pef, $column . (23 + $slot), $value);
            }
        }
        for ($slot = 0; $slot < 12; $slot++) {
            $row = $data['proposed'][$slot] ?? null;
            foreach (['A' => $row['code'] ?? null, 'B' => $row['title'] ?? null, 'G' => isset($row['units']) ? (float) $row['units'] : null] as $column => $value) {
                pefCcWriteCell($pef, $column . (54 + $slot), $value);
            }
        }
        // Evaluation-term official academic standing in C38:
        // In provisional mode or when withheld, C38 remains strictly blank.
        pefCcWriteCell($pef, 'C38', null);

        // Previous Academic Standing matrix (Rows 42-45, Columns C-F)
        // Clear cells C42:F45 first to guarantee unpopulated cells remain blank
        for ($r = 42; $r <= 45; $r++) {
            foreach (['C', 'D', 'E', 'F'] as $c) {
                pefCcWriteCell($pef, $c . $r, null);
            }
        }
        // Year labels First through Fourth Year in B42:B45
        pefCcWriteCell($pef, 'B42', 'First Year');
        pefCcWriteCell($pef, 'B43', 'Second Year');
        pefCcWriteCell($pef, 'B44', 'Third Year');
        pefCcWriteCell($pef, 'B45', 'Fourth Year');

        // Populate verified historical standing from model data
        $matrixRowMap = [1 => 42, 2 => 43, 3 => 44, 4 => 45];
        $matrixColMap = ['sem1' => 'C', 'sem2' => 'D', 'sem3' => 'E', 'summer' => 'F'];

        foreach ($data['historical_standing'] ?? [] as $row) {
            $yl = (int) ($row['year_level'] ?? 0);
            if (!isset($matrixRowMap[$yl])) {
                continue;
            }
            $targetRow = $matrixRowMap[$yl];
            foreach ($matrixColMap as $key => $colLetter) {
                $val = $row[$key] ?? null;
                // Only write verified institutional codes 'GS' or 'WS'
                // Numeric GWA, pending statuses, and missing terms NEVER write to matrix cells
                if ($val === 'GS' || $val === 'WS') {
                    $cellAddress = $colLetter . $targetRow;
                    pefCcWriteCell($pef, $cellAddress, $val);
                    $pef->getStyle($cellAddress)->getFont()->setName('Arial Narrow')->setSize(9);
                    $pef->getStyle($cellAddress)->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                }
            }
        }

        // Configured signatories in B68 and B70, with titles in B69 and B71
        $signatories = $config['signatories'] ?? [];
        $progHead = $signatories['program_head_name'] ?? 'MS. CHRISTINA P. ATAL';
        $progHeadTitle = $signatories['program_head_title'] ?? 'Program Chairperson';
        $dean = $signatories['dean_name'] ?? 'MR. FRANCIS ARLANDO L. ATIENZA';
        $deanTitle = $signatories['dean_title'] ?? 'Dean';

        pefCcWriteCell($pef, 'B68', $progHead);
        pefCcWriteCell($pef, 'B69', $progHeadTitle);
        pefCcWriteCell($pef, 'B70', $dean);
        pefCcWriteCell($pef, 'B71', $deanTitle);

        // Blank signature and date spaces remain strictly unpopulated:
        // Signature spaces D68:F68 and D70:F70, date spaces G68 and G70
        foreach ([68, 70] as $r) {
            foreach (['D', 'E', 'F'] as $c) { pefCcWriteCell($pef, $c . $r, null); }
            pefCcWriteCell($pef, 'G' . $r, null);
        }
        // Signature and date labels in rows 69 and 71
        pefCcWriteCell($pef, 'D69', 'Signature');
        pefCcWriteCell($pef, 'G69', 'Date');
        pefCcWriteCell($pef, 'D71', 'Signature');
        pefCcWriteCell($pef, 'G71', 'Date');

        // Unconditionally clear below-form rows 73-85 and unmerge any legacy merges in rows 72-85
        $merges = $pef->getMergeCells();
        foreach ($merges as $mergeRange) {
            if (preg_match('/[A-G](7[2-9]|8[0-5])/', $mergeRange)) {
                $pef->unmergeCells($mergeRange);
            }
        }
        for ($r = 73; $r <= 85; $r++) {
            foreach (range('A', 'G') as $c) {
                pefCcWriteCell($pef, $c . $r, null);
            }
        }

        // Apply on-sheet red notice and dynamic print area by mode
        if (in_array($data['mode'] ?? '', ['provisional', 'review'], true)) {
            $pef->mergeCells('A73:G73');
            pefCcWriteCell($pef, 'A73', 'PROVISIONAL PLANNING COPY');
            $pef->getStyle('A73')->getFont()->setName('Arial Narrow')->setSize(10)->setBold(true)->getColor()->setARGB('FFFF0000');
            $pef->getStyle('A73')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $pef->mergeCells('A74:G75');
            $notice = empty($data['evaluation_complete'])
                ? 'Current-semester grades are incomplete. The proposed subjects and section require academic evaluator review and may change after final grades are recorded.'
                : 'Some planning information requires academic evaluator review. This provisional planning copy includes only verified information; unresolved planning fields remain blank.';
            pefCcWriteCell($pef, 'A74', $notice);
            $pef->getStyle('A74')->getFont()->setName('Arial Narrow')->setSize(9)->setItalic(true)->getColor()->setARGB('FFFF0000');
            $pef->getStyle('A74')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $pef->getPageSetup()->setPrintArea('A1:G75');
        } else {
            $pef->getPageSetup()->setPrintArea('A1:G71');
        }

        // Odd footer remains strictly empty in both Normal and Provisional modes
        $pef->getHeaderFooter()->setOddFooter('');
        $pef->getHeaderFooter()->setEvenFooter('');
        $pef->getHeaderFooter()->setFirstFooter('');
        pefCcWriteCell($cc, 'B1', $data['identity']['name']);
        // The blank v0.1 template says REFERENCE ONLY. The generated copy states
        // the owner's exact applicability label in this fixed title destination.
        pefCcWriteCell($cc, 'A3', '2022-2023 Curriculum Checklist - Reference / Requires Review');
        if (count($data['cc']['rows']) !== count($reference['rows']) || $data['cc']['label'] !== 'Reference / Requires Review') {
            throw new RuntimeException('The checklist reference does not match the preview.');
        }
        // Clear target Grade, Remarks, and Status cells across all curriculum row bands
        foreach ([[7, 15], [21, 29], [35, 42], [48, 53]] as [$first, $last]) {
            foreach (range($first, $last) as $row) {
                pefCcWriteCell($cc, 'E' . $row, null);
                pefCcWriteCell($cc, 'F' . $row, null);
                pefCcWriteCell($cc, 'G' . $row, null);
                pefCcWriteCell($cc, 'M' . $row, null);
                pefCcWriteCell($cc, 'N' . $row, null);
                pefCcWriteCell($cc, 'O' . $row, null);
            }
        }
        foreach ($reference['rows'] as $index => $ref) {
            $annotation = $data['cc']['rows'][$index];
            if (($annotation['code'] ?? null) !== $ref['code'] || !in_array($annotation['state'] ?? null, ['', 'CMP', 'FAIL', 'INC', 'CUR', 'NA', 'REVIEW', 'DROPPED'], true)) {
                throw new RuntimeException('The checklist reference does not match the preview.');
            }
            // Only the trusted template supplies these destinations.
            $gradeVal = $annotation['grade_display'] ?? $annotation['grade'] ?? null;
            pefCcWriteCell($cc, $ref['grade_cell'], $gradeVal);
            pefCcWriteCell($cc, $ref['remark_cell'], $annotation['remarks'] ?? null);
            pefCcWriteCell($cc, $ref['status_cell'], $annotation['status'] ?? null);
        }
        // Section 9 Defensive Cleanup: Unmerge obsolete commentary merges intersecting rows 67-73 when present
        $obsoleteCcMerges = ['A67:O68', 'A69:O70', 'A71:O72', 'A73:O73'];
        foreach ($obsoleteCcMerges as $m) {
            if (in_array($m, $cc->getMergeCells(), true)) {
                $cc->unmergeCells($m);
            }
        }
        // Defensively clear rows 67-75 outside of rebuilt signatory cells
        for ($r = 67; $r <= 75; $r++) {
            for ($col = 'A'; $col !== 'P'; $col++) {
                $cell = $cc->getCell($col . $r);
                $cell->setValue(null);
                $cell->getStyle()->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $cell->getStyle()->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $cell->getStyle()->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
                $cell->getStyle()->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
            }
        }
        // Rebuild approved signatory cells
        pefCcWriteCell($cc, 'B66', 'Prepared by');
        $cc->getStyle('B66')->getFont()->setName('Arial Narrow')->setSize(15)->setBold(false);
        pefCcWriteCell($cc, 'J66', 'Noted by');
        $cc->getStyle('J66')->getFont()->setName('Arial Narrow')->setSize(15)->setBold(false);

        pefCcWriteCell($cc, 'B69', null);
        $cc->getStyle('B69')->getFont()->setName('Arial Narrow')->setSize(15)->setBold(false);
        $cc->getStyle('B69')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        pefCcWriteCell($cc, 'J69', $dean);
        $cc->getStyle('J69')->getFont()->setName('Arial Narrow')->setSize(15)->setBold(true);
        $cc->getStyle('J69')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        pefCcWriteCell($cc, 'K69', null);
        $cc->getStyle('K69')->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        pefCcWriteCell($cc, 'B70', 'Evaluator');
        $cc->getStyle('B70')->getFont()->setName('Arial Narrow')->setSize(15)->setBold(false);

        pefCcWriteCell($cc, 'J70', $deanTitle);
        $cc->getStyle('J70')->getFont()->setName('Arial Narrow')->setSize(15)->setBold(false);

        // Row heights
        $cc->getRowDimension(66)->setRowHeight(17.25);
        $cc->getRowDimension(67)->setRowHeight(13.5);
        $cc->getRowDimension(68)->setRowHeight(13.5);
        $cc->getRowDimension(69)->setRowHeight(18.75);
        $cc->getRowDimension(70)->setRowHeight(18.75);

        // CC Print Area set to A1:O70
        $cc->getPageSetup()->setPrintArea('A1:O70');
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
    if (preg_match('/\Apefcc-[0-9a-f]{32}\.(?:tmp|xlsx|pdf)\z/D', basename($path)) !== 1
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

function pefCcValidatePdf(string $pdfPath, array $config): array
{
    $paths = pefCcPrivatePaths($config, false);
    if (preg_match('/\Apefcc-[0-9a-f]{32}\.pdf\z/D', basename($pdfPath)) !== 1
        || pefCcPathKey(dirname($pdfPath)) !== pefCcPathKey($paths['temp_path']) || is_link($pdfPath)) {
        throw new RuntimeException('Refusing to validate an unowned PDF export path.');
    }
    clearstatcache(true, $pdfPath);
    if (!is_file($pdfPath) || !is_readable($pdfPath)) {
        throw new RuntimeException('The planning PDF output could not be read.');
    }
    $bytes = filesize($pdfPath);
    if ($bytes === false || $bytes <= 0 || $bytes > ($config['max_output_bytes'] ?? 5242880)) {
        throw new RuntimeException('The planning PDF output exceeds its size limit.');
    }
    $handle = fopen($pdfPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The planning PDF output could not be opened.');
    }
    $header = fread($handle, 8);
    fclose($handle);
    if (!is_string($header) || !str_starts_with($header, '%PDF-')) {
        throw new RuntimeException('The planning PDF output header is invalid.');
    }
    $content = file_get_contents($pdfPath);
    if ($content === false) {
        throw new RuntimeException('The planning PDF output could not be verified.');
    }
    // Must be exactly 2 pages: Page 1 = PEF, Page 2 = CC
    preg_match_all('~/Type\s*/Page\b~', $content, $matches);
    $pageCount = count($matches[0]);
    if ($pageCount !== 2) {
        throw new RuntimeException('The planning PDF must contain exactly two pages.');
    }
    return [
        'path' => $pdfPath,
        'bytes' => $bytes,
        'pages' => 2,
        'sha256' => hash_file('sha256', $pdfPath),
    ];
}

function pefCcRenderPdfFromWorkbook(string $xlsxPath, array $config): array
{
    $paths = pefCcPrivatePaths($config, false);
    if (preg_match('/\Apefcc-[0-9a-f]{32}\.xlsx\z/D', basename($xlsxPath)) !== 1
        || pefCcPathKey(dirname($xlsxPath)) !== pefCcPathKey($paths['temp_path']) || is_link($xlsxPath)) {
        throw new RuntimeException('Refusing to convert an unowned workbook path.');
    }
    clearstatcache(true, $xlsxPath);
    if (!is_file($xlsxPath) || !is_readable($xlsxPath)) {
        throw new RuntimeException('The source planning workbook is unavailable.');
    }
    $jobId = substr(basename($xlsxPath), 6, 32);
    $pdfPath = $paths['temp_path'] . DIRECTORY_SEPARATOR . 'pefcc-' . $jobId . '.pdf';

    register_shutdown_function(static function () use ($pdfPath, $config): void {
        try { pefCcRemoveOutput($pdfPath, $config); } catch (Throwable) { /* CLI reaper handles a later retry. */ }
    });

    $lockPath = $paths['temp_path'] . DIRECTORY_SEPARATOR . '.pefcc_converter.lock';
    $lockFp = @fopen($lockPath, 'c+');
    if ($lockFp === false) {
        throw new RuntimeException('The converter lock could not be initialized.');
    }
    $locked = false;
    $waitLimit = 20;
    $startWait = microtime(true);
    while (microtime(true) - $startWait < $waitLimit) {
        if (flock($lockFp, LOCK_EX | LOCK_NB)) {
            $locked = true;
            break;
        }
        usleep(100000);
    }
    if (!$locked) {
        fclose($lockFp);
        throw new RuntimeException('The planning PDF is currently being prepared. Try again shortly.');
    }

    try {
        $realXlsx = realpath($xlsxPath);
        if ($realXlsx === false) {
            throw new RuntimeException('The source planning workbook is unavailable.');
        }
        $psXlsx = str_replace('\\', '/', $realXlsx);
        $psPdf = str_replace('\\', '/', $pdfPath);
        $psScript = "\$ErrorActionPreference = 'Stop'; \$excel = New-Object -ComObject Excel.Application; \$excel.Visible = \$false; \$excel.DisplayAlerts = \$false; try { \$book = \$excel.Workbooks.Open('$psXlsx', 0, \$true); \$book.ExportAsFixedFormat(0, '$psPdf', 0, \$false, \$false); \$book.Close(\$false); Write-Output 'SUCCESS' } finally { \$excel.Quit(); [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$excel) | Out-Null }";

        $cmd = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript);
        $output = shell_exec($cmd);

        if (!is_string($output) || !str_contains($output, 'SUCCESS') || !file_exists($pdfPath)) {
            throw new RuntimeException('The planning PDF could not be created.');
        }

        $validated = pefCcValidatePdf($pdfPath, $config);
        $validated['job_id'] = $jobId;
        return $validated;
    } catch (Throwable $e) {
        if (file_exists($pdfPath)) {
            try { pefCcRemoveOutput($pdfPath, $config); } catch (Throwable) {}
        }
        throw $e;
    } finally {
        if ($locked) {
            flock($lockFp, LOCK_UN);
        }
        if (is_resource($lockFp)) {
            fclose($lockFp);
        }
        // Immediate cleanup of temporary intermediate XLSX workbook
        try { pefCcRemoveOutput($xlsxPath, $config); } catch (Throwable) {}
    }
}

function pefCcCleanupExports(array $config, bool $delete = false, ?int $now = null): array
{
    $paths = pefCcPrivatePaths($config, false);
    $now ??= time();
    $result = ['scanned' => 0, 'eligible' => 0, 'deleted' => 0];
    foreach (new DirectoryIterator($paths['temp_path']) as $entry) {
        if ($entry->isDot() || $entry->isLink() || !$entry->isFile()
            || preg_match('/\Apefcc-[0-9a-f]{32}\.(?:tmp|xlsx|pdf)\z/D', $entry->getFilename()) !== 1) { continue; }
        $result['scanned']++;
        if ($entry->getMTime() > $now - $config['cleanup_age_seconds']) { continue; }
        $result['eligible']++;
        if ($delete) { pefCcRemoveOutput($entry->getPathname(), $config); $result['deleted']++; }
    }
    return $result;
}
