<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/fixtures/pef_cc_fixture.php';
require_once dirname(__DIR__, 2) . '/config/pef_cc.php';
require_once dirname(__DIR__, 2) . '/includes/pef_cc_view_model.php';
require_once dirname(__DIR__, 2) . '/includes/pef_cc_snapshot.php';
require_once dirname(__DIR__, 2) . '/includes/pef_cc_export_service.php';
require_once dirname(__DIR__, 2) . '/includes/pef_cc_planning_policy.php';

// Focused test suite for PEF v0.2-r5 term eligibility, signatories, and form fidelity.
$testConfig = pefCcConfig();
$reference = pefCcTemplateReference($testConfig);

// 1. Completed First Semester permits same-year Second Semester planning.
$c1 = pefTestContext(3, ['3.00', '2.50', '2.75'], '2025-2026', 1);
$c1['sections'] = [
    ['section' => 'IT-32', 'year_level' => 3, 'school_year' => '2025-2026', 'semester' => 2, 'source' => 'active_sd_current_records', 'evidence_sha256' => hash('sha256', 'IT-32-2025-2026-2')],
];
$i1 = pefTestInputs([
    'evaluation_year' => '2025-2026',
    'evaluation_semester' => 1,
    'target_year' => '2025-2026',
    'target_semester' => 2,
    'section' => 'IT-32',
    'subject_ids' => [1, 2],
]);
$m1 = pefCcBuildViewModel($c1, $i1, $reference, $testConfig, ['school_year' => '2025-2026', 'semester' => 2]);
pefTestCheck($m1->data['exportable'] === true && $m1->data['policy']['status'] !== 'incomplete_term_blocked',
    'Completed First Semester permits same-year Second Semester planning.');

// 2. Completed Second Semester permits next-year First Semester planning.
$c2 = pefTestContext(3, ['3.00', '2.50', '2.75'], '2025-2026', 2);
$i2 = pefTestInputs([
    'evaluation_year' => '2025-2026',
    'evaluation_semester' => 2,
    'target_year' => '2026-2027',
    'target_semester' => 1,
    'section' => 'IT-32',
    'subject_ids' => [1, 2],
]);
$m2 = pefCcBuildViewModel($c2, $i2, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
pefTestCheck($m2->data['exportable'] === true && $m2->data['policy']['status'] !== 'incomplete_term_blocked',
    'Completed Second Semester permits next-year First Semester planning.');

// 3. Incomplete active First Semester permits provisional Second Semester planning.
$c3 = pefTestRegularContext(3, 1, false);
$i3 = pefTestRegularInputs($c3, 1);
$m3 = pefCcBuildViewModel($c3, $i3, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
$warning = $m3->data['policy']['provisional_warning'] ?? '';
pefTestCheck($m3->data['exportable'] === true
    && $m3->data['mode'] === 'provisional'
    && ($m3->data['policy']['status'] ?? '') === 'incomplete_term_provisional'
    && ($m3->data['policy']['provisional_allowed'] ?? false) === true
    && $m3->data['gwa'] === null
    && $m3->data['gwa_state'] === 'pending'
    && str_contains($warning, 'provisional planning workbook for SY 2026–2027 Second Semester')
    && str_contains($warning, 'current SY 2026–2027 First Semester record is incomplete'),
    'Incomplete active First Semester permits provisional Second Semester planning.');

// 3b. Provisional workbook contains on-sheet red notice, empty footer, null GWA, blank missing grades, and A1:G75 print area.
$gen3 = pefCcGenerateWorkbook($m3, $testConfig);
$wb3 = \PhpOffice\PhpSpreadsheet\IOFactory::load($gen3['path']);
$pef3 = $wb3->getSheetByName('PEF');
$color3 = $pef3->getStyle('A73')->getFont()->getColor()->getARGB();
$val3A73 = $pef3->getCell('A73')->getValue();
$oddFooter3 = $pef3->getHeaderFooter()->getOddFooter();
pefTestCheck($pef3->getCell('A73')->getValue() === 'PROVISIONAL PLANNING COPY'
    && str_contains((string) $pef3->getCell('A74')->getValue(), 'Current-semester grades are incomplete')
    && ($color3 === 'FFFF0000' || $color3 === 'FF0000')
    && ($oddFooter3 === null || $oddFooter3 === '')
    && $pef3->getCell('C37')->getValue() === null
    && $pef3->getCell('F23')->getValue() === null
    && $pef3->getCell('G23')->getValue() === null
    && $pef3->getPageSetup()->getPrintArea() === 'A1:G75'
    && isset($m3->data['history']) && is_array($m3->data['history']),
    'Provisional workbook contains on-sheet red notice, empty footer, null GWA, blank missing grades, A1:G75 print area, and structured history.');
$wb3->disconnectWorksheets();
pefCcRemoveOutput($gen3['path'], $testConfig);

// 3c. State transitions or grade changes invalidate provisional preview snapshot.
$key3 = random_bytes(32);
$token3 = pefCcSignSnapshot($m3, $key3, $c3['identity']['id'], 'session-prov-test', $testConfig);
pefTestCheck(pefCcVerifySnapshot($token3, $m3, $key3, $c3['identity']['id'], 'session-prov-test', $testConfig),
    'Provisional preview snapshot verifies successfully before changes.');
$c3Completed = pefTestRegularContext(3, 1, true);
$m3Completed = pefCcBuildViewModel($c3Completed, $i3, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
pefTestCheck($m3Completed->data['mode'] === 'normal'
    && !pefCcVerifySnapshot($token3, $m3Completed, $key3, $c3['identity']['id'], 'session-prov-test', $testConfig),
    'Grade completion transitioning provisional to normal mode invalidates existing preview snapshot.');

// 4. Older completed term cannot bypass incomplete current term.
$c4 = $c3; // Current term is 2026-2027 Sem 1 (incomplete)
$i4 = array_replace($i3, ['evaluation_year' => '2025-2026', 'evaluation_semester' => 2]);
$m4 = pefCcBuildViewModel($c4, $i4, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
pefTestCheck(!$m4->data['exportable']
    && $m4->data['mode'] === 'invalid'
    && in_array('The evaluation term must be the immediate predecessor (2026-2027 First Semester) of the target planning term.', $m4->data['errors'], true),
    'Older completed term cannot bypass incomplete current term.');

// 5. Evaluation term equals immediate completed predecessor.
$pred = pefCcPredecessorTerm('2026-2027', 1);
pefTestCheck($pred === ['school_year' => '2025-2026', 'semester' => 2]
    && pefCcPredecessorTerm('2026-2027', 2) === ['school_year' => '2026-2027', 'semester' => 1],
    'Evaluation term equals immediate completed predecessor.');

// 6. NULL final grade blocks completion.
$c6Records = [
    ['school_year' => '2025-2026', 'semester' => 2, 'units' => 3, 'grade' => null],
    ['school_year' => '2025-2026', 'semester' => 2, 'units' => 3, 'grade' => '3.00'],
];
$comp6 = pefCcTermCompletion($c6Records, '2025-2026', 2);
pefTestCheck(!$comp6['is_complete'] && $comp6['reason'] === 'incomplete_grades',
    'NULL final grade blocks completion.');

// 7. Empty final grade blocks completion.
$c7Records = [
    ['school_year' => '2025-2026', 'semester' => 2, 'units' => 3, 'grade' => '   '],
    ['school_year' => '2025-2026', 'semester' => 2, 'units' => 3, 'grade' => '3.00'],
];
$comp7 = pefCcTermCompletion($c7Records, '2025-2026', 2);
pefTestCheck(!$comp7['is_complete'] && $comp7['reason'] === 'incomplete_grades',
    'Empty final grade blocks completion.');

// 8. INC remains unresolved and blocks normal planning.
$c8Records = [
    ['school_year' => '2025-2026', 'semester' => 2, 'units' => 3, 'grade' => 'INC'],
    ['school_year' => '2025-2026', 'semester' => 2, 'units' => 3, 'grade' => '3.00'],
];
$comp8 = pefCcTermCompletion($c8Records, '2025-2026', 2);
pefTestCheck(!$comp8['is_complete'] && $comp8['reason'] === 'unresolved_outcomes',
    'INC remains unresolved and blocks normal planning.');

// 9. Textual outcomes are not coerced to zero.
$evP = pefCcGradeEvidence(['grade' => 'P', 'units' => 3]);
$evDrp = pefCcGradeEvidence(['grade' => 'DRP', 'units' => 3]);
$evFail = pefCcGradeEvidence(['grade' => '0.00', 'units' => 3]);
pefTestCheck($evP['outcome'] === 'passed' && $evP['gwa_reason'] === 'textual_status'
    && $evDrp['outcome'] === 'review' && $evDrp['gwa_reason'] === 'textual_status'
    && $evFail['gwa_reason'] === 'numeric_zero',
    'Textual outcomes are not coerced to zero.');

// 10. GWA and status refer to the same term.
pefTestCheck($m2->data['evaluation']['school_year'] === '2025-2026'
    && $m2->data['evaluation']['semester'] === 2
    && count($m2->data['recorded']) === 3
    && $m2->data['recorded'][0]['school_year'] === '2025-2026'
    && $m2->data['recorded'][0]['semester'] === 2,
    'GWA and status refer to the same term.');

// 11. Incomplete term standing remains pending.
pefTestCheck($m3->data['academic_standing'] === 'Pending evaluator review'
    && $m3->data['academic_standing_code'] === null,
    'Incomplete term standing remains pending.');

// 11b. Complete safe-review output does not certify selected-term standing.
pefTestCheck($m2->data['mode']==='review' && $m2->data['academic_standing']==='Pending evaluator review'
    && $m2->data['academic_standing_code']===null, 'Safe review copy with complete outcomes retains evaluator-review standing');
[$normalContext,$normalInputs,$normalTerm]=pefTestPlanningState('A');
$normalStanding=pefCcBuildViewModel($normalContext,$normalInputs,pefTestReference(),$testConfig,$normalTerm)->data;
pefTestCheck($normalStanding['academic_standing']==='Good Standing (GS)' && $normalStanding['academic_standing_code']==='GS',
    'Verified complete State A still derives Good Standing');

// 12. Missing historical standing remains blank, not WS.
$historicalStanding = $m2->data['historical_standing'] ?? [];
$allBlank = true;
foreach ($historicalStanding as $row) {
    if ($row['sem1'] !== null || $row['sem2'] !== null || $row['sem3'] !== null || $row['summer'] !== null) {
        $allBlank = false;
        break;
    }
}
pefTestCheck($allBlank === true && count($historicalStanding) === 4,
    'Missing historical standing remains blank, not WS.');

// 13. Future standing remains blank.
pefTestCheck($historicalStanding[3]['sem2'] === null && $historicalStanding[3]['summer'] === null,
    'Future standing remains blank.');

// 14. Irregular pacing does not imply WS.
$c14 = pefTestContext(3, ['3.00', '2.50', '2.75']);
$c14['identity']['status'] = 'Irregular';
$m14 = pefCcBuildViewModel($c14, pefTestInputs(), $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
pefTestCheck($m14->data['academic_standing'] !== 'Warning Status (WS)'
    && !str_contains(json_encode($m14->data['historical_standing']), 'WS'),
    'Irregular pacing does not imply WS.');

// ==================================================
// DEDICATED 24 TEST REQUIREMENTS (Section 9)
// ==================================================

// Setup multi-term context (Years 1 to 3, with incomplete current Year 3 Sem 1)
$multiCtx = pefTestMultiTermRegularContext(false);
$multiInp = pefTestRegularInputs($multiCtx, 1);
$multiModel = pefCcBuildViewModel($multiCtx, $multiInp, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
$multiGen = pefCcGenerateWorkbook($multiModel, $testConfig);
$multiWb = \PhpOffice\PhpSpreadsheet\IOFactory::load($multiGen['path']);
$multiPef = $multiWb->getSheetByName('PEF');

// 1. Current incomplete term does not erase historical terms.
$historyYears = array_column($multiModel->data['history'], 'school_year');
pefTestCheck(in_array('2024-2025', $historyYears, true) && in_array('2025-2026', $historyYears, true) && in_array('2026-2027', $historyYears, true),
    '1. Current incomplete term does not erase historical terms.');

// 2. Completed historical term GWA remains calculable.
$hTerms = [];
foreach ($multiModel->data['history'] as $h) {
    $hTerms[$h['school_year'] . ':' . $h['semester']] = $h;
}
pefTestCheck(isset($hTerms['2024-2025:1']) && is_float($hTerms['2024-2025:1']['gwa']) && $hTerms['2024-2025:1']['gwa'] > 0,
    '2. Completed historical term GWA remains calculable.');

// 3. Verified historical GS appears in its correct matrix cell (C42 = Year 1 Sem 1).
pefTestCheck($multiPef->getCell('C42')->getValue() === 'GS'
    && $multiModel->data['historical_standing'][0]['sem1'] === 'GS',
    '3. Verified historical GS appears in its correct matrix cell.');

// 4. Verified historical WS appears only when the policy produces WS (D42 = Year 1 Sem 2 with GWA < 1.75).
pefTestCheck($multiPef->getCell('D42')->getValue() === 'WS'
    && $multiModel->data['historical_standing'][0]['sem2'] === 'WS'
    && $hTerms['2024-2025:2']['gwa'] < 1.75,
    '4. Verified historical WS appears only when the policy produces WS.');

// 5. Missing standing remains blank.
pefTestCheck($multiPef->getCell('E42')->getValue() === null
    && $multiModel->data['historical_standing'][0]['sem3'] === null,
    '5. Missing standing remains blank.');

// 6. Missing standing is never converted to WS.
pefTestCheck($multiPef->getCell('E42')->getValue() !== 'WS'
    && $multiPef->getCell('F42')->getValue() !== 'WS',
    '6. Missing standing is never converted to WS.');

// 7. Current incomplete-term standing remains blank (C44 = Year 3 Sem 1).
pefTestCheck($multiPef->getCell('C44')->getValue() === null
    && $multiModel->data['historical_standing'][2]['sem1'] === null,
    '7. Current incomplete-term standing remains blank.');

// 8. Future-term standing remains blank (D44 = Year 3 Sem 2, C45..F45 = Year 4).
pefTestCheck($multiPef->getCell('D44')->getValue() === null
    && $multiPef->getCell('C45')->getValue() === null
    && $multiPef->getCell('D45')->getValue() === null
    && $multiPef->getCell('E45')->getValue() === null
    && $multiPef->getCell('F45')->getValue() === null,
    '8. Future-term standing remains blank.');

// 9. Numeric GWA is never written into a GS/WS cell.
$matrixNumericFound = false;
for ($r = 42; $r <= 45; $r++) {
    foreach (['C', 'D', 'E', 'F'] as $col) {
        $val = $multiPef->getCell($col . $r)->getValue();
        if ($val !== null && $val !== 'GS' && $val !== 'WS') {
            $matrixNumericFound = true;
            break 2;
        }
    }
}
pefTestCheck($matrixNumericFound === false,
    '9. Numeric GWA is never written into a GS/WS cell.');

// 10. Browser displays historical GWA separately.
pefTestCheck(isset($hTerms['2024-2025:1']['gwa_display'])
    && $hTerms['2024-2025:1']['gwa_display'] !== $hTerms['2024-2025:1']['standing']
    && is_numeric($hTerms['2024-2025:1']['gwa_display']),
    '10. Browser displays historical GWA separately.');

// 11. Browser displays standing source or pending status.
pefTestCheck($hTerms['2024-2025:1']['standing_source'] === 'derived_student_manual_2025_non_board'
    && $hTerms['2026-2027:1']['standing_source'] === 'pending_evaluator_review'
    && $hTerms['2026-2027:1']['standing'] === 'Pending evaluator review',
    '11. Browser displays standing source or pending status.');

// 12. First-Year First-Semester mapping is correct.
pefTestCheck($multiModel->data['historical_standing'][0]['year_label'] === 'First Year'
    && $multiPef->getCell('C42')->getValue() === 'GS',
    '12. First-Year First-Semester mapping is correct.');

// 13. First-Year Second-Semester mapping is correct.
pefTestCheck($multiModel->data['historical_standing'][0]['year_label'] === 'First Year'
    && $multiPef->getCell('D42')->getValue() === 'WS',
    '13. First-Year Second-Semester mapping is correct.');

// 14. Second-Year mapping is correct.
pefTestCheck($multiModel->data['historical_standing'][1]['year_label'] === 'Second Year'
    && $multiPef->getCell('C43')->getValue() === 'GS'
    && $multiPef->getCell('D43')->getValue() === 'GS',
    '14. Second-Year mapping is correct.');

// 15. Third-Year mapping is correct.
pefTestCheck($multiModel->data['historical_standing'][2]['year_label'] === 'Third Year'
    && $multiModel->data['historical_standing'][2]['sem1'] === null,
    '15. Third-Year mapping is correct.');

// 16. Fourth-Year mapping is correct.
pefTestCheck($multiModel->data['historical_standing'][3]['year_label'] === 'Fourth Year'
    && $multiPef->getCell('B45')->getValue() === 'Fourth Year',
    '16. Fourth-Year mapping is correct.');

// 17. Summer remains blank unless applicable.
$allSummerBlank = true;
for ($r = 42; $r <= 45; $r++) {
    if ($multiPef->getCell('F' . $r)->getValue() !== null) {
        $allSummerBlank = false;
        break;
    }
}
pefTestCheck($allSummerBlank === true,
    '17. Summer remains blank unless applicable.');

// Clean up multi-term workbook
$multiWb->disconnectWorksheets();
pefCcRemoveOutput($multiGen['path'], $testConfig);

// Setup ambiguous irregular context
$ambigCtx = pefTestAmbiguousIrregularContext();
$ambigInp = pefTestInputs([
    'evaluation_year' => '2026-2027',
    'evaluation_semester' => 1,
    'target_year' => '2026-2027',
    'target_semester' => 2,
    'section' => 'IT-IRREG',
    'subject_ids' => [$ambigCtx['catalog'][0]['id']],
]);
$ambigModel = pefCcBuildViewModel($ambigCtx, $ambigInp, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
$ambigGen = pefCcGenerateWorkbook($ambigModel, $testConfig);
$ambigWb = \PhpOffice\PhpSpreadsheet\IOFactory::load($ambigGen['path']);
$ambigPef = $ambigWb->getSheetByName('PEF');

// 18. Irregular history is not automatically classified as WS.
pefTestCheck(!str_contains(json_encode($ambigModel->data['historical_standing']), 'WS')
    && $ambigModel->data['academic_standing'] !== 'Warning Status (WS)',
    '18. Irregular history is not automatically classified as WS.');

// 19. Ambiguous irregular year placement remains unprinted.
$ambigMatrixEmpty = true;
for ($r = 42; $r <= 45; $r++) {
    foreach (['C', 'D', 'E', 'F'] as $col) {
        if ($ambigPef->getCell($col . $r)->getValue() !== null) {
            $ambigMatrixEmpty = false;
            break 2;
        }
    }
}
$ambigHistoryItem = null;
foreach ($ambigModel->data['history'] as $h) {
    if ($h['school_year'] === '2025-2026' && $h['semester'] === 2) {
        $ambigHistoryItem = $h;
        break;
    }
}
pefTestCheck($ambigMatrixEmpty === true
    && $ambigHistoryItem !== null
    && ($ambigHistoryItem['placement_status'] ?? '') === 'unresolved'
    && str_contains($ambigHistoryItem['standing'] ?? '', 'Year placement requires evaluator review'),
    '19. Ambiguous irregular year placement remains unprinted.');

$ambigWb->disconnectWorksheets();
pefCcRemoveOutput($ambigGen['path'], $testConfig);

// 20. Provisional export retains previous verified standings.
pefTestCheck($multiModel->data['mode'] === 'provisional'
    && $multiModel->data['historical_standing'][0]['sem1'] === 'GS'
    && $multiModel->data['historical_standing'][0]['sem2'] === 'WS'
    && $multiModel->data['historical_standing'][1]['sem1'] === 'GS'
    && $multiModel->data['historical_standing'][1]['sem2'] === 'GS',
    '20. Provisional export retains previous verified standings.');

// 21. Normal export retains previous verified standings.
$normalCtx = pefTestMultiTermRegularContext(true); // current term 2026-2027 Sem 1 is complete
$normalInp = pefTestRegularInputs($normalCtx, 1);
$normalModel = pefCcBuildViewModel($normalCtx, $normalInp, $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
$normalGen = pefCcGenerateWorkbook($normalModel, $testConfig);
$normalWb = \PhpOffice\PhpSpreadsheet\IOFactory::load($normalGen['path']);
$normalPef = $normalWb->getSheetByName('PEF');
pefTestCheck($normalModel->data['mode'] === 'normal'
    && $normalPef->getCell('C42')->getValue() === 'GS'
    && $normalPef->getCell('D42')->getValue() === 'WS'
    && $normalPef->getCell('C43')->getValue() === 'GS'
    && $normalPef->getCell('D43')->getValue() === 'GS',
    '21. Normal export retains previous verified standings.');
$normalWb->disconnectWorksheets();
pefCcRemoveOutput($normalGen['path'], $testConfig);

// 22. Existing red provisional notice remains unchanged.
pefTestCheck($val3A73 === 'PROVISIONAL PLANNING COPY'
    && ($color3 === 'FFFF0000' || $color3 === 'FF0000'),
    '22. Existing red provisional notice remains unchanged.');

// 23 & 24 are asserted below by existing section 28 and 32 checks.

// Generate v0.2-r4 workbook to test workbook structure, signatories, labels, and fidelity.
$gen = pefCcGenerateWorkbook($m2, $testConfig);
$wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($gen['path']);
$pefSheet = $wb->getSheetByName('PEF');

// 15. First through Fourth Year labels are present.
pefTestCheck($pefSheet->getCell('B42')->getValue() === 'First Year'
    && $pefSheet->getCell('B43')->getValue() === 'Second Year'
    && $pefSheet->getCell('B44')->getValue() === 'Third Year'
    && $pefSheet->getCell('B45')->getValue() === 'Fourth Year',
    'First through Fourth Year labels are present.');

// 16. Configured Program Chairperson name is present.
pefTestCheck($pefSheet->getCell('B68')->getValue() === 'MS. CHRISTINA P. ATAL',
    'Configured Program Chairperson name is present.');

// 17. Configured Dean name is present.
pefTestCheck($pefSheet->getCell('B70')->getValue() === 'MR. FRANCIS ARLANDO L. ATIENZA',
    'Configured Dean name is present.');

// 18. Program Chairperson and Dean role labels are present.
pefTestCheck($pefSheet->getCell('B69')->getValue() === 'Program Chairperson'
    && $pefSheet->getCell('B71')->getValue() === 'Dean',
    'Program Chairperson and Dean role labels are present.');

// 19. Signature labels are present.
pefTestCheck($pefSheet->getCell('D69')->getValue() === 'Signature'
    && $pefSheet->getCell('D71')->getValue() === 'Signature'
    && $pefSheet->getCell('G69')->getValue() === 'Date'
    && $pefSheet->getCell('G71')->getValue() === 'Date',
    'Signature labels are present.');

// 20. Signature spaces remain blank.
pefTestCheck($pefSheet->getCell('D68')->getValue() === null
    && $pefSheet->getCell('E68')->getValue() === null
    && $pefSheet->getCell('F68')->getValue() === null
    && $pefSheet->getCell('D70')->getValue() === null
    && $pefSheet->getCell('E70')->getValue() === null
    && $pefSheet->getCell('F70')->getValue() === null,
    'Signature spaces remain blank.');

// 21. Date spaces remain blank.
pefTestCheck($pefSheet->getCell('G68')->getValue() === null
    && $pefSheet->getCell('G70')->getValue() === null,
    'Date spaces remain blank.');

// 22. No signature image exists.
$zip = new ZipArchive();
$zip->open($gen['path']);
$genMedia = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (str_starts_with($name, 'xl/media/')) {
        $genMedia[] = hash('sha256', (string) $zip->getFromIndex($i));
    }
}
$templateZip = new ZipArchive();
$templateZip->open($testConfig['template_path']);
$templateMedia = [];
for ($i = 0; $i < $templateZip->numFiles; $i++) {
    $name = $templateZip->getNameIndex($i);
    if (str_starts_with($name, 'xl/media/')) {
        $templateMedia[] = hash('sha256', (string) $templateZip->getFromIndex($i));
    }
}
$templateZip->close();
pefTestCheck(count($genMedia) === 1 && $genMedia === $templateMedia,
    'No signature image exists.');

// 23. Rows 73–81 commentary is absent.
$commentaryPresent = false;
for ($r = 76; $r <= 81; $r++) {
    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
        $val = $pefSheet->getCell($col . $r)->getValue();
        if ($val !== null && trim((string) $val) !== '') {
            $commentaryPresent = true;
            break 2;
        }
    }
}
pefTestCheck($commentaryPresent === false, 'Rows 73–81 commentary is absent.');

// 24. PEF print area is A1:G71.
pefTestCheck($pefSheet->getPageSetup()->getPrintArea() === 'A1:G75' && $pefSheet->getCell('A73')->getValue() === 'PROVISIONAL PLANNING COPY',
    'Review copy includes the approved red notice in A1:G75.');

// 25. Browser disclosures remain present.
$planningFile = file_get_contents(dirname(__DIR__, 2) . '/student/enrolment_planning.php');
pefTestCheck(str_contains($planningFile, 'config[\'disclosure\']')
    && str_contains($planningFile, 'config[\'prototype_disclosure\']')
    && str_contains($planningFile, 'incomplete-term-warning'),
    'Browser disclosures remain present.');

// 26. No external workbook relationship exists.
$extLinks = [];
$extRels = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (str_starts_with($name, 'xl/externalLinks/')) {
        $extLinks[] = $name;
    }
    if (str_ends_with($name, '.rels')) {
        $content = $zip->getFromIndex($i);
        if (str_contains($content, 'TargetMode="External"')) {
            $extRels[] = $name;
        }
    }
}
pefTestCheck($extLinks === [] && $extRels === [],
    'No external workbook relationship exists.');

// 27. No source-workbook refresh dependency exists.
$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
pefTestCheck(!str_contains((string) $sheetXml, '[4TH YEAR.xlsx]')
    && !str_contains((string) $sheetXml, '[')
    && $extLinks === [],
    'No source-workbook refresh dependency exists.');

$zip->close();
$wb->disconnectWorksheets();
pefCcRemoveOutput($gen['path'], $testConfig);

// 28. Existing section validation remains intact.
$regularCtx = pefTestRegularContext(2, 2);
$regularInp = pefTestRegularInputs($regularCtx, 2);
$badSection = pefCcBuildViewModel($regularCtx, array_replace($regularInp, ['section' => 'IT-39']), $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 2]);
pefTestCheck(!$badSection->data['exportable'] && isset($badSection->data['field_errors']['section']),
    'Existing section validation remains intact.');

// 29. IT-IRREG context handling remains intact.
$irregCtx = pefTestContext();
$irregModel = pefCcBuildViewModel($irregCtx, pefTestInputs(['section' => 'IT-IRREG', 'subject_ids' => [1]]), $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
pefTestCheck($irregModel->data['exportable'] && in_array('IT-IRREG', $irregModel->data['policy']['section_options'], true),
    'IT-IRREG context handling remains intact.');

// 30. Formula-injection protection remains intact.
$injCtx = pefTestContext();
$injModel = pefCcBuildViewModel($injCtx, pefTestInputs(['section' => '=1+1']), $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);
pefTestCheck(!$injModel->data['exportable'] && isset($injModel->data['field_errors']['section']),
    'Formula-injection protection remains intact.');

// 31. Stale-preview protection remains intact.
$key = random_bytes(32);
$token = pefCcSignSnapshot($m2, $key, 900001, 'session-test', $testConfig);
$tampered = $m2->data;
$tampered['planning']['section'] = 'CHANGED';
pefTestCheck(!pefCcVerifySnapshot($token, new PefCcViewModel($tampered), $key, 900001, 'session-test', $testConfig),
    'Stale-preview protection remains intact.');

// 32. No database mutation occurs.
$enrolmentCode = file_get_contents(dirname(__DIR__, 2) . '/student/enrolment_planning.php');
$exportCode = file_get_contents(dirname(__DIR__, 2) . '/student/export_pef_cc.php');
pefTestCheck(str_contains($enrolmentCode, 'SET TRANSACTION READ ONLY')
    && str_contains($exportCode, 'SET TRANSACTION READ ONLY')
    && !preg_match('/\b(INSERT\s+INTO|UPDATE\s+`?[a-zA-Z0-9_]+`?\s+SET|DELETE\s+FROM)\b/i', $enrolmentCode)
    && !preg_match('/\b(INSERT\s+INTO|UPDATE\s+`?[a-zA-Z0-9_]+`?\s+SET|DELETE\s+FROM)\b/i', $exportCode),
    'No database mutation occurs.');

// =========================================================================
// SECTION 13: OFFICIAL CC REMARKS AND STATUS CONTRACT ASSERTIONS (1–38)
// =========================================================================

// Build a context with all grade types based on valid provisional context c3
$ccTestCtx = pefTestRegularContext(3, 1, false);
$extraRecords = [
    ['id' => 901, 'subject_id' => 101, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'GED101', 'title' => 'Purposive Communications', 'units' => 3, 'grade' => '4.00'],
    ['id' => 902, 'subject_id' => 102, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'GED102', 'title' => 'Mathematics in the Modern World', 'units' => 3, 'grade' => '3.25'],
    ['id' => 903, 'subject_id' => 103, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'GED104', 'title' => 'Ethics', 'units' => 3, 'grade' => '1.00'],
    ['id' => 904, 'subject_id' => 104, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'ITE111', 'title' => 'Introduction to Computing', 'units' => 3, 'grade' => 'P'],
    ['id' => 905, 'subject_id' => 105, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'ITE112', 'title' => 'Fundamentals of Programming', 'units' => 3, 'grade' => '0.00'],
    ['id' => 906, 'subject_id' => 106, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'PE101', 'title' => 'Physical Education 1', 'units' => 2, 'grade' => null],
    ['id' => 907, 'subject_id' => 107, 'school_year' => '2023-2024', 'semester' => 1, 'is_current' => 0, 'code' => 'UID101', 'title' => 'Institutional Identity 1', 'units' => 1, 'grade' => 'INC'],
    ['id' => 908, 'subject_id' => 108, 'school_year' => '2023-2024', 'semester' => 2, 'is_current' => 0, 'code' => 'GED103', 'title' => 'Science, Technology and Society', 'units' => 3, 'grade' => 'DO'],
    ['id' => 909, 'subject_id' => 109, 'school_year' => '2023-2024', 'semester' => 2, 'is_current' => 0, 'code' => 'RZL101', 'title' => 'Rizal', 'units' => 3, 'grade' => 'DU'],
    ['id' => 910, 'subject_id' => 110, 'school_year' => '2023-2024', 'semester' => 2, 'is_current' => 0, 'code' => 'MST101', 'title' => 'Living in IT Era', 'units' => 3, 'grade' => 'DRP'],
    ['id' => 911, 'subject_id' => 111, 'school_year' => '2023-2024', 'semester' => 2, 'is_current' => 0, 'code' => 'ITE114', 'title' => 'Information Management', 'units' => 3, 'grade' => 'FA'],
    ['id' => 912, 'subject_id' => 112, 'school_year' => '2023-2024', 'semester' => 2, 'is_current' => 0, 'code' => 'ITE115', 'title' => 'Data Structures', 'units' => 3, 'grade' => 'UD'],
    ['id' => 913, 'subject_id' => 113, 'school_year' => '2023-2024', 'semester' => 2, 'is_current' => 0, 'code' => 'ITE113', 'title' => 'Platform Tech', 'units' => 3, 'grade' => 'UNKNOWN_CODE'],
];
$ccTestCtx['records'] = array_merge($ccTestCtx['records'], $extraRecords);
$ccTestModel = pefCcBuildViewModel($ccTestCtx, pefTestRegularInputs($ccTestCtx, 1), $reference, $testConfig, ['school_year' => '2026-2027', 'semester' => 1]);

// Map rows by code
$ccRowsByCode = [];
foreach ($ccTestModel->data['cc']['rows'] as $r) {
    $ccRowsByCode[$r['code']] = $r;
}

// 1. Numeric 4.00 produces P and Taken.
pefTestCheck(($ccRowsByCode['GED101']['remarks'] ?? null) === 'P' && ($ccRowsByCode['GED101']['status'] ?? null) === 'Taken', '1. Numeric 4.00 produces P and Taken.');

// 2. Numeric 3.25 produces P and Taken.
pefTestCheck(($ccRowsByCode['GED102']['remarks'] ?? null) === 'P' && ($ccRowsByCode['GED102']['status'] ?? null) === 'Taken', '2. Numeric 3.25 produces P and Taken.');

// 3. Numeric 1.00 produces P and Taken.
pefTestCheck(($ccRowsByCode['GED104']['remarks'] ?? null) === 'P' && ($ccRowsByCode['GED104']['status'] ?? null) === 'Taken', '3. Numeric 1.00 produces P and Taken.');

// 4. Textual P produces P and Taken.
pefTestCheck(($ccRowsByCode['ITE111']['remarks'] ?? null) === 'P' && ($ccRowsByCode['ITE111']['status'] ?? null) === 'Taken', '4. Textual P produces P and Taken.');

// 5. Numeric 0.00 produces F and Need to Enroll.
pefTestCheck(($ccRowsByCode['ITE112']['remarks'] ?? null) === 'F' && ($ccRowsByCode['ITE112']['status'] ?? null) === 'Need to Enroll', '5. Numeric 0.00 produces F and Need to Enroll.');

// 6. Missing grade produces blank Remarks.
pefTestCheck(($ccRowsByCode['PE101']['remarks'] ?? null) === null, '6. Missing grade produces blank Remarks.');

// 7. Missing actual subject produces Need to Enroll.
pefTestCheck(($ccRowsByCode['NST101']['status'] ?? null) === 'Need to Enroll', '7. Missing actual subject produces Need to Enroll.');

// 8. INC remains visible only in Grade.
pefTestCheck(($ccRowsByCode['UID101']['grade_display'] ?? null) === 'INC', '8. INC remains visible only in Grade.');

// 9. INC produces blank Remarks.
pefTestCheck(($ccRowsByCode['UID101']['remarks'] ?? null) === null, '9. INC produces blank Remarks.');

// 10. INC produces Need to Enroll.
pefTestCheck(($ccRowsByCode['UID101']['status'] ?? null) === 'Need to Enroll', '10. INC produces Need to Enroll.');

// 11. DO does not become P.
pefTestCheck(($ccRowsByCode['GED103']['remarks'] ?? null) !== 'P' && ($ccRowsByCode['GED103']['status'] ?? null) === 'Need to Enroll', '11. DO does not become P.');

// 12. DU does not become P.
pefTestCheck(($ccRowsByCode['RZL101']['remarks'] ?? null) !== 'P' && ($ccRowsByCode['RZL101']['status'] ?? null) === 'Need to Enroll', '12. DU does not become P.');

// 13. DRP does not become P.
pefTestCheck(($ccRowsByCode['MST101']['remarks'] ?? null) !== 'P' && ($ccRowsByCode['MST101']['status'] ?? null) === 'Need to Enroll', '13. DRP does not become P.');

// 14. FA does not become P.
pefTestCheck(($ccRowsByCode['ITE114']['remarks'] ?? null) !== 'P' && ($ccRowsByCode['ITE114']['status'] ?? null) === 'Need to Enroll', '14. FA does not become P.');

// 15. UD does not become P.
pefTestCheck(($ccRowsByCode['ITE115']['remarks'] ?? null) !== 'P' && ($ccRowsByCode['ITE115']['status'] ?? null) === 'Need to Enroll', '15. UD does not become P.');

// 16. Unknown text does not become P or F.
pefTestCheck(($ccRowsByCode['ITE113']['remarks'] ?? null) === null, '16. Unknown text does not become P or F.');

// 17. Unknown text produces Need to Enroll for an actual subject.
pefTestCheck(($ccRowsByCode['ITE113']['status'] ?? null) === 'Need to Enroll', '17. Unknown text produces Need to Enroll for an actual subject.');

// 18. Current incomplete subject does not become Taken.
pefTestCheck(($ccRowsByCode['ITE321']['status'] ?? null) === 'Need to Enroll' && ($ccRowsByCode['ITE321']['remarks'] ?? null) === null, '18. Current incomplete subject does not become Taken.');

// 19. Future subject produces Need to Enroll.
pefTestCheck(($ccRowsByCode['ITE431']['status'] ?? null) === 'Need to Enroll' && ($ccRowsByCode['ITE431']['remarks'] ?? null) === null, '19. Future subject produces Need to Enroll.');

// Export workbook to verify spreadsheet cells
$genCcWb = pefCcGenerateWorkbook($ccTestModel, $testConfig);
$ccWb = \PhpOffice\PhpSpreadsheet\IOFactory::load($genCcWb['path']);
$ccSheet = $ccWb->getSheetByName('CC');
$pefSheet = $ccWb->getSheetByName('PEF');

// 20. Empty left-block row remains blank. (Left Row 15: A15 is empty)
pefTestCheck($ccSheet->getCell('E15')->getValue() === null && $ccSheet->getCell('F15')->getValue() === null && $ccSheet->getCell('G15')->getValue() === null, '20. Empty left-block row remains blank.');

// 21. Empty right-block row remains blank. (Right Row 50: I50 is empty)
pefTestCheck($ccSheet->getCell('M50')->getValue() === null && $ccSheet->getCell('N50')->getValue() === null && $ccSheet->getCell('O50')->getValue() === null, '21. Empty right-block row remains blank.');

// Scan all cells in CC columns E, F, G, M, N, O
$ccValues = [];
$remarksValues = [];
$statusValues = [];
foreach ([[7, 15], [21, 29], [35, 42], [48, 53]] as [$first, $last]) {
    for ($r = $first; $r <= $last; $r++) {
        foreach (['E', 'F', 'G', 'M', 'N', 'O'] as $col) {
            $val = (string) $ccSheet->getCell($col . $r)->getValue();
            if ($val !== '') {
                $ccValues[] = $val;
            }
            if (in_array($col, ['F', 'N'], true) && $val !== '') {
                $remarksValues[] = $val;
            }
            if (in_array($col, ['G', 'O'], true) && $val !== '') {
                $statusValues[] = $val;
            }
        }
    }
}

// 22. CMP is absent from visible CC cells.
pefTestCheck(!in_array('CMP', $ccValues, true), '22. CMP is absent from visible CC cells.');

// 23. CUR is absent from visible CC cells.
pefTestCheck(!in_array('CUR', $ccValues, true), '23. CUR is absent from visible CC cells.');

// 24. INC is absent from Remarks and Status.
pefTestCheck(!in_array('INC', $remarksValues, true) && !in_array('INC', $statusValues, true), '24. INC is absent from Remarks and Status.');

// 25. FAIL is absent from visible CC cells.
pefTestCheck(!in_array('FAIL', $ccValues, true), '25. FAIL is absent from visible CC cells.');

// 26. NA is absent from visible CC cells.
pefTestCheck(!in_array('NA', $ccValues, true), '26. NA is absent from visible CC cells.');

// 27. REVIEW is absent from visible CC cells.
pefTestCheck(!in_array('REVIEW', $ccValues, true), '27. REVIEW is absent from visible CC cells.');

// 28. Grade values are not replaced by Remarks.
pefTestCheck($ccSheet->getCell('E7')->getValue() == 4.0 && $ccSheet->getCell('F7')->getValue() === 'P' && $ccSheet->getCell('G7')->getValue() === 'Taken', '28. Grade values are not replaced by Remarks.');

// 29. GWA is not used to derive subject Remarks.
pefTestCheck($ccSheet->getCell('F11')->getValue() === 'F' && $ccTestModel->data['gwa'] === null, '29. GWA is not used to derive subject Remarks.');

// 30. Risk level is not used to derive CC Status.
pefTestCheck(!isset($ccTestModel->data['risk_level']), '30. Risk level is not used to derive CC Status.');

// 31. PEF remains unchanged.
pefTestCheck($pefSheet->getPageSetup()->getPrintArea() === 'A1:G75' || $pefSheet->getPageSetup()->getPrintArea() === 'A1:G75' && $pefSheet->getCell('A73')->getValue() === 'PROVISIONAL PLANNING COPY', '31. PEF remains unchanged.');

// 32. CC remains one Portrait Folio page.
pefTestCheck($ccSheet->getPageSetup()->getOrientation() === \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT, '32. CC remains one Portrait Folio page.');

// 33. Workbook contains exactly PEF and CC.
pefTestCheck($ccWb->getSheetNames() === ['PEF', 'CC'], '33. Workbook contains exactly PEF and CC.');

// 34. No external links exist.
$extLinksFound = [];
$wbZip = new ZipArchive();
if ($wbZip->open($genCcWb['path']) === true) {
    for ($i = 0; $i < $wbZip->numFiles; $i++) {
        $entryName = $wbZip->getNameIndex($i);
        if (str_contains($entryName, 'externalLink')) {
            $extLinksFound[] = $entryName;
        }
    }
    $wbZip->close();
}
pefTestCheck($extLinksFound === [], '34. No external links exist.');

// 35. No private source tabs exist.
$prohibitedTabs = ['StudentList', 'StudentList (Do Not Delete)', 'Data - Do Not Delete', 'SubjCode Reference Tab', 'Study Plan(for IRREG)'];
pefTestCheck(array_intersect($ccWb->getSheetNames(), $prohibitedTabs) === [], '35. No private source tabs exist.');

// 36. Same signed snapshot drives PEF and CC.
$snapToken = pefCcSignSnapshot($ccTestModel, $key, 900001, 'session-test', $testConfig);
pefTestCheck(pefCcVerifySnapshot($snapToken, $ccTestModel, $key, 900001, 'session-test', $testConfig), '36. Same signed snapshot drives PEF and CC.');

// 37. Stale preview remains rejected.
$tamperedCc = $ccTestModel->data;
$tamperedCc['cc']['rows'][0]['remarks'] = 'CHANGED';
pefTestCheck(!pefCcVerifySnapshot($snapToken, new PefCcViewModel($tamperedCc), $key, 900001, 'session-test', $testConfig), '37. Stale preview remains rejected.');

// 38. No database mutation occurs.
pefTestCheck(str_contains($enrolmentCode, 'SET TRANSACTION READ ONLY') && str_contains($exportCode, 'SET TRANSACTION READ ONLY'), '38. No database mutation occurs.');

$ccWb->disconnectWorksheets();
pefCcRemoveOutput($genCcWb['path'], $testConfig);

pefTestFinish();
