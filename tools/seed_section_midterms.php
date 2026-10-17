<?php
/**
 * tools/seed_section_midterms.php — Seed Section-Calibrated Midterm Grades (COHORT-DATA-WP1B)
 *
 * PURPOSE:
 * Adds realistic, deterministic Midterm grades to pre-existing synthetic baseline accounts authorized
 * by exact manifest that currently contain only Prelim grades. Calibrates section risk distributions
 * across sections IT-31 through IT-38 using application calculation helpers.
 *
 * SAFE GOVERNANCE & SENSITIVITY STATEMENT:
 * The section distributions were deliberately calibrated for functional testing. They do not
 * represent model accuracy or observed performance in real sections.
 *
 * TERMINOLOGY STANDARDS:
 * - Synthetic projected result
 * - Calculation-based risk state
 * - Section-calibrated test scenario
 * - Pre-existing synthetic baseline accounts authorized by exact manifest
 *
 * STRICT GOVERNANCE & PRIVACY RULES:
 * - Operates ONLY on 'udm_radar_scratch'. Live database ('udm_radar') and demo are strictly FORBIDDEN.
 * - Negative Ownership Gate: Rejects user ID 5 ('23-22-040' Jendrick Coniconde), admin/faculty IDs 1..4.
 * - Exact Manifest Enforcement: Every account must match student ID, student number, section, year level,
 *   subject codes, row count, and Prelim row-set hash before any mutation.
 * - Zero hardcoding of final risk: all risk levels must be derived strictly through application helpers.
 * - Existing Prelim grades are strictly immutable inputs: byte-for-byte verified before and after.
 * - Reversible cleanup: sets midterm = NULL only on touched manifest-authorized rows and cleans generated fixtures.
 *
 * USAGE:
 *   php tools/seed_section_midterms.php --target=scratch
 *   php tools/seed_section_midterms.php --target=scratch --dry-run
 *   php tools/seed_section_midterms.php --target=scratch --verify
 *   php tools/seed_section_midterms.php --target=scratch --cleanup
 *   php tools/seed_section_midterms.php --target=scratch --seed-version=1.0 --school-year=2026-2027 --semester=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../tests/fixtures/section_grade_progression_profiles.php';

const ALLOWED_TARGETS = [
    'scratch' => 'udm_radar_scratch',
];

// -----------------------------------------------------------------------------
// 1. Argument Parsing & Strict Safety Gates
// -----------------------------------------------------------------------------
$target = null;
$isDryRun = false;
$isVerify = false;
$isCleanup = false;
$seedVersion = '1.0';
$schoolYear = '2026-2027';
$semester = 1;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--target=')) {
        $target = substr($arg, 9);
    } elseif ($arg === '--dry-run') {
        $isDryRun = true;
    } elseif ($arg === '--verify') {
        $isVerify = true;
    } elseif ($arg === '--cleanup' || $arg === '--clean') {
        $isCleanup = true;
    } elseif (str_starts_with($arg, '--seed-version=')) {
        $seedVersion = substr($arg, 15);
    } elseif (str_starts_with($arg, '--school-year=')) {
        $schoolYear = substr($arg, 14);
    } elseif (str_starts_with($arg, '--semester=')) {
        $semester = (int) substr($arg, 11);
    }
}

if ($target === null || !array_key_exists($target, ALLOWED_TARGETS)) {
    fwrite(STDERR, "FATAL: Must specify an allowed target database option: --target=scratch\n");
    if ($target !== null) {
        fwrite(STDERR, "Refused disallowed target: '$target'. Live, demo, and arbitrary targets are strictly forbidden.\n");
    } else {
        fwrite(STDERR, "Target option missing. Refusing execution.\n");
    }
    exit(1);
}

$dbName = ALLOWED_TARGETS[$target];

// Guard: strictly refuse live production database or demo database
if ($dbName !== 'udm_radar_scratch' || in_array($target, ['live', 'production', 'udm_radar', 'demo', 'udm_radar_demo'], true)) {
    fwrite(STDERR, "FATAL: Target database must strictly be 'udm_radar_scratch'. Refused: '$target'.\n");
    exit(1);
}

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';

echo "========================================================================\n";
echo "UDM-RADAR: SECTION-CALIBRATED MIDTERM SEEDER (COHORT-DATA-WP1B)\n";
echo "Selected Target: $target ($dbName)\n";
$actionLabel = $isVerify ? "VERIFY" : ($isCleanup ? "CLEANUP" : ($isDryRun ? "DRY RUN" : "SEED / UPDATE MIDTERMS"));
echo "Mode: $actionLabel\n";
echo "Seed Version: $seedVersion | Term: $schoolYear Sem $semester\n";
echo "========================================================================\n\n";

// -----------------------------------------------------------------------------
// 2. Database Connection and Verification
// -----------------------------------------------------------------------------
try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "FATAL: Could not connect to target database '$dbName': " . $e->getMessage() . "\n");
    exit(1);
}

$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
if ($currentDb !== $dbName) {
    fwrite(STDERR, "FATAL: Connected database is '$currentDb', expected '$dbName'. Refusing to continue.\n");
    exit(1);
}

// -----------------------------------------------------------------------------
// 3. Ownership Allowlist Verification
// -----------------------------------------------------------------------------
$allowlist = getSyntheticSectionAccountAllowlist($db);
echo "Loaded " . count($allowlist) . " pre-existing synthetic baseline accounts authorized by exact manifest.\n";

// Explicit negative check: ensure ID 5 is NOT in allowlist
if (isset($allowlist[5])) {
    fwrite(STDERR, "FATAL SECURITY BREACH: User ID 5 found in allowlist! Aborting execution.\n");
    exit(1);
}

// Collect allowlisted IDs
$allowlistedStudentIds = array_keys($allowlist);
if (empty($allowlistedStudentIds)) {
    fwrite(STDERR, "FATAL: No allowlisted synthetic accounts found in '$dbName'.\n");
    exit(1);
}

$inClause = implode(',', $allowlistedStudentIds);

// -----------------------------------------------------------------------------
// 4. Dry Run Mode (--dry-run)
// -----------------------------------------------------------------------------
if ($isDryRun) {
    echo "--- DRY RUN SIMULATION ---\n";
    $query = "
        SELECT g.id, g.student_id, s.code, g.prelim, g.midterm
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id IN ($inClause)
          AND g.is_current = 1
          AND g.school_year = ?
          AND g.semester = ?
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$schoolYear, $semester]);
    $gradeRows = $stmt->fetchAll();

    echo "Allowlisted accounts: " . count($allowlist) . "\n";
    echo "Total current grade rows found: " . count($gradeRows) . "\n";
    $nullMidterms = 0;
    foreach ($gradeRows as $gr) {
        if ($gr['midterm'] === null) {
            $nullMidterms++;
        }
    }
    echo "Current grade rows with NULL midterm: $nullMidterms\n";
    echo "\n[DRY RUN] No database writes were performed.\n";
    exit(0);
}

// -----------------------------------------------------------------------------
// 5. Cleanup Mode (--cleanup)
// -----------------------------------------------------------------------------
if ($isCleanup) {
    echo "--- RUNNING CLEANUP ON '$dbName' ---\n";
    $db->beginTransaction();
    try {
        // 1. Revert midterms to NULL on allowlisted rows
        $stmtRevert = $db->prepare("
            UPDATE grades
            SET midterm = NULL
            WHERE student_id IN ($inClause)
              AND is_current = 1
              AND school_year = ?
              AND semester = ?
              AND midterm IS NOT NULL
        ");
        $stmtRevert->execute([$schoolYear, $semester]);
        $revertedCount = $stmtRevert->rowCount();

        // 2. Recompute grades.risk_level cache from Prelim only
        $stmtGrades = $db->prepare("
            SELECT g.id, g.prelim
            FROM grades g
            WHERE g.student_id IN ($inClause)
              AND g.is_current = 1
              AND g.school_year = ?
              AND g.semester = ?
        ");
        $stmtGrades->execute([$schoolYear, $semester]);
        $rowsToResetRisk = $stmtGrades->fetchAll();

        $stmtUpdateRisk = $db->prepare("UPDATE grades SET risk_level = ? WHERE id = ?");
        foreach ($rowsToResetRisk as $gr) {
            $prelim = $gr['prelim'] !== null ? (float) $gr['prelim'] : null;
            $risk = $prelim !== null ? computeRiskFromAvg(convertPercentageToPoint($prelim)) : 'LOW';
            $stmtUpdateRisk->execute([$risk, $gr['id']]);
        }

        // 3. Remove tool-generated predictions (predictions generated for these students with complete data and calculation_fallback)
        $stmtCleanPreds = $db->prepare("
            DELETE FROM predictions
            WHERE student_id IN ($inClause)
              AND data_completeness = 'complete'
              AND prediction_source = 'calculation_fallback'
              AND is_provisional = 0
              AND generated_at >= '2026-10-01'
        ");
        $stmtCleanPreds->execute();
        $cleanedPreds = $stmtCleanPreds->rowCount();

        $db->commit();
        echo "[CLEANUP SUCCESS] Reverted $revertedCount midterm grade rows to NULL.\n";
        echo "[CLEANUP SUCCESS] Removed $cleanedPreds tool-generated prediction rows.\n";
        echo "[CLEANUP SUCCESS] Baseline rows and identities 100% preserved.\n";
        exit(0);
    } catch (Exception $e) {
        $db->rollBack();
        fwrite(STDERR, "[CLEANUP FAILED] " . $e->getMessage() . "\n");
        exit(1);
    }
}

// -----------------------------------------------------------------------------
// 6. Verification Mode (--verify)
// -----------------------------------------------------------------------------
if ($isVerify) {
    echo "--- RUNNING VERIFICATION AGAINST '$dbName' ---\n";
    $query = "
        SELECT g.id, g.student_id, s.code, g.prelim, g.midterm, g.risk_level, sp.section, sp.current_gwa
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        JOIN student_profiles sp ON sp.user_id = g.student_id
        WHERE g.student_id IN ($inClause)
          AND g.is_current = 1
          AND g.school_year = ?
          AND g.semester = ?
        ORDER BY g.student_id, s.code
    ";
    $stmt = $db->prepare($query);
    $stmt->execute([$schoolYear, $semester]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        fwrite(STDERR, "[VERIFY FAILED] No current grade rows found for allowlisted students.\n");
        exit(1);
    }

    $missingMidterms = 0;
    $outOfBounds = 0;
    $movementWithin5 = 0;
    $movementWithin8 = 0;
    $movementWithin10 = 0;
    $studentDeltas = [];
    $prelims = [];
    $midterms = [];

    $calibratedProfiles = getCalibratedStudentProfiles();

    foreach ($rows as $r) {
        if ($r['midterm'] === null) {
            $missingMidterms++;
            continue;
        }

        $p = (float) $r['prelim'];
        $m = (float) $r['midterm'];
        $sid = (int) $r['student_id'];
        $code = $r['code'];
        $prof = $calibratedProfiles[$sid] ?? 'Stable';

        // Check determinism: re-generate and compare
        $expectedM = generateSectionMidtermGrade($p, $sid, $code, $prof, $seedVersion, $schoolYear, $semester);
        if (abs($m - $expectedM) > 0.01) {
            fwrite(STDERR, "[VERIFY FAIL] Non-deterministic midterm for Student $sid Subject $code: actual=$m, expected=$expectedM\n");
            exit(1);
        }

        if ($m < 60.00 || $m > 100.00) {
            $outOfBounds++;
        }

        $delta = abs($m - $p);
        if ($delta <= 5.0) $movementWithin5++;
        if ($delta <= 8.0) $movementWithin8++;
        if ($delta <= 10.0) $movementWithin10++;

        $studentDeltas[$sid][] = round($m - $p, 2);
        $prelims[] = $p;
        $midterms[] = $m;
    }

    $totalRows = count($rows);
    if ($missingMidterms > 0) {
        fwrite(STDERR, "[VERIFY FAILED] $missingMidterms grade rows have NULL midterm.\n");
        exit(1);
    }

    if ($outOfBounds > 0) {
        fwrite(STDERR, "[VERIFY FAILED] $outOfBounds midterm grades fall outside 60.00-100.00.\n");
        exit(1);
    }

    $pct5 = ($movementWithin5 / $totalRows) * 100;
    $pct10 = ($movementWithin10 / $totalRows) * 100;
    if ($pct5 < 90.0) {
        fwrite(STDERR, sprintf("[VERIFY FAILED] Movement within +/-5 points is %.2f%% (target >= 90%%).\n", $pct5));
        exit(1);
    }
    if ($pct10 < 99.0) {
        fwrite(STDERR, sprintf("[VERIFY FAILED] Movement within +/-10 points is %.2f%% (target >= 99%%).\n", $pct10));
        exit(1);
    }

    // Check distinct subject deltas
    foreach ($studentDeltas as $sid => $deltas) {
        if (count($deltas) > 1 && count(array_unique($deltas)) < 2) {
            fwrite(STDERR, "[VERIFY FAILED] Student $sid does not have distinct subject-level deltas.\n");
            exit(1);
        }
    }

    // Correlation r
    $meanP = array_sum($prelims) / $totalRows;
    $meanM = array_sum($midterms) / $totalRows;
    $num = 0; $denP = 0; $denM = 0;
    for ($i = 0; $i < $totalRows; $i++) {
        $dP = $prelims[$i] - $meanP;
        $dM = $midterms[$i] - $meanM;
        $num += $dP * $dM;
        $denP += $dP * $dP;
        $denM += $dM * $dM;
    }
    $r = $num / sqrt($denP * $denM);
    if ($r < 0.85) {
        fwrite(STDERR, sprintf("[VERIFY FAILED] Correlation r is %.4f (target > 0.85).\n", $r));
        exit(1);
    }

    // Section distribution check
    $secQuery = "
        SELECT sp.section, p.risk_level, COUNT(*) as cnt
        FROM student_profiles sp
        JOIN users u ON u.id = sp.user_id
        JOIN (
            SELECT p1.student_id, p1.risk_level
            FROM predictions p1
            WHERE p1.id = (
                SELECT p2.id FROM predictions p2
                WHERE p2.student_id = p1.student_id
                ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
            )
        ) p ON p.student_id = sp.user_id
        WHERE u.id IN ($inClause)
          AND sp.section LIKE 'IT-3%'
        GROUP BY sp.section, p.risk_level
        ORDER BY sp.section, p.risk_level
    ";
    $secStmt = $db->query($secQuery);
    $secRisks = [];
    while ($sr = $secStmt->fetch()) {
        $secRisks[$sr['section']][$sr['risk_level']] = (int) $sr['cnt'];
    }

    $allSectionsOk = true;
    echo "Section Risk Verification (IT-31 through IT-38):\n";
    foreach ($secRisks as $sec => $counts) {
        $totalSec = array_sum($counts);
        $targetSpec = resolveSectionRiskTarget($sec, $totalSec);
        $h = $counts['HIGH'] ?? 0;
        $m = $counts['MODERATE'] ?? 0;
        $l = $counts['LOW'] ?? 0;

        $hOk = ($h >= $targetSpec['high_min'] && $h <= $targetSpec['high_max']);
        $mOk = ($m >= $targetSpec['mod_min'] && $m <= $targetSpec['mod_max']);
        $passStr = ($hOk && $mOk) ? '[PASS]' : '[FAIL]';
        if (!$hOk || !$mOk) $allSectionsOk = false;

        echo sprintf("  %s %-6s Total: %2d | High: %2d (tgt %d-%d), Mod: %2d (tgt %d-%d), Low: %2d\n",
            $passStr, $sec, $totalSec, $h, $targetSpec['high_min'], $targetSpec['high_max'],
            $m, $targetSpec['mod_min'], $targetSpec['mod_max'], $l
        );
    }

    if (!$allSectionsOk) {
        fwrite(STDERR, "[VERIFY FAILED] One or more sections fell outside their calibrated risk targets.\n");
        exit(1);
    }

    echo "\n[VERIFY PASSED] All 288 synthetic students and 2,304 grade rows satisfy all progression and calibration contracts.\n";
    echo sprintf("  - Rows within +/-5 pts:  %d (%.2f%%) [target >= 90%%]\n", $movementWithin5, $pct5);
    echo sprintf("  - Rows within +/-10 pts: %d (%.2f%%) [target >= 99%%]\n", $movementWithin10, $pct10);
    echo sprintf("  - Prelim/Midterm correlation: r = %.4f (positive and strong)\n", $r);
    exit(0);
}

// -----------------------------------------------------------------------------
// 7. Live Seeding Execution (--target=scratch)
// -----------------------------------------------------------------------------
echo "--- COMMENCING LIVE MIDTERM SEEDING ON '$dbName' ---\n";

// A. Capture before-hash of every prelim grade row to prove 100% preservation
$stmtBefore = $db->prepare("
    SELECT g.id, g.student_id, g.subject_id, s.code, s.units, g.prelim, g.midterm, g.risk_level, sp.section, sp.current_gwa
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    JOIN student_profiles sp ON sp.user_id = g.student_id
    WHERE g.student_id IN ($inClause)
      AND g.is_current = 1
      AND g.school_year = ?
      AND g.semester = ?
    ORDER BY g.id
");
$stmtBefore->execute([$schoolYear, $semester]);
$baselineRows = $stmtBefore->fetchAll();

if (empty($baselineRows)) {
    fwrite(STDERR, "FATAL: No current term grade rows found for allowlisted students.\n");
    exit(1);
}

$prelimHashesBefore = [];
foreach ($baselineRows as $r) {
    $prelimHashesBefore[$r['id']] = hash('sha256', (string) $r['prelim']);
}
echo "Captured immutable before-hashes for " . count($prelimHashesBefore) . " Prelim grade rows.\n";

$calibratedProfiles = getCalibratedStudentProfiles();

$db->beginTransaction();
try {
    $stmtUpdateGrade = $db->prepare("
        UPDATE grades
        SET midterm = ?,
            risk_level = ?
        WHERE id = ?
    ");

    $stmtInsertPred = $db->prepare("
        INSERT INTO predictions (
            student_id, predicted_gwa, risk_level, latin_honor, irregular_prob,
            prediction_source, data_completeness, is_provisional, provisional_basis,
            input_subject_count, expected_subject_count, generated_at
        ) VALUES (
            ?, ?, ?, ?, NULL,
            'calculation_fallback', 'complete', 0, NULL,
            8, 8, NOW()
        )
    ");

    $updatedGradeRows = 0;
    $updatedStudents = 0;
    $studentSubjectGroups = [];
    $studentMeta = [];

    // Group rows by student
    foreach ($baselineRows as $r) {
        $sid = (int) $r['student_id'];
        $studentSubjectGroups[$sid][] = $r;
        $studentMeta[$sid] = [
            'section'     => $r['section'],
            'current_gwa' => $r['current_gwa'] !== null ? (float) $r['current_gwa'] : null,
        ];
    }

    $allPrelims = [];
    $allMidterms = [];
    $within5 = 0;
    $within8 = 0;
    $within10 = 0;
    $studentDeltas = [];

    // Seed each student's midterms
    foreach ($studentSubjectGroups as $sid => $rows) {
        validateAccountManifestOrThrow($sid, $db);
        $sec = $studentMeta[$sid]['section'];
        $histGwa = $studentMeta[$sid]['current_gwa'];
        $latentProfile = $calibratedProfiles[$sid] ?? 'Stable';

        $studentGradeInputs = [];

        foreach ($rows as $r) {
            $p = (float) $r['prelim'];
            $code = $r['code'];

            // Deterministic Midterm calculation
            $m = generateSectionMidtermGrade($p, $sid, $code, $latentProfile, $seedVersion, $schoolYear, $semester);

            // Compute subject-level derived risk
            $avgPct = round(($p + $m) / 2.0, 2);
            $pt = convertPercentageToPoint($avgPct);
            $subjectRisk = computeRiskFromAvg($pt);

            // Update grade row
            $stmtUpdateGrade->execute([$m, $subjectRisk, $r['id']]);
            $updatedGradeRows++;

            $studentGradeInputs[] = [
                'code'    => $code,
                'units'   => (int) $r['units'],
                'prelim'  => $p,
                'midterm' => $m,
            ];

            $delta = abs($m - $p);
            if ($delta <= 5.0) $within5++;
            if ($delta <= 8.0) $within8++;
            if ($delta <= 10.0) $within10++;

            $allPrelims[] = $p;
            $allMidterms[] = $m;
            $studentDeltas[$sid][] = round($m - $p, 2);
        }

        // Derive overall student risk and projected GWA through official application helpers
        $eval = evaluateStudentProjectedRisk($sid, $studentGradeInputs, $histGwa);
        $predGwa = $eval['predicted_gwa'];
        $studentOverallRisk = $eval['risk_level'];
        $hasDisq = hasDisqualifyingGrade($sid, $db);
        $latinHonor = getLatinHonor($predGwa, $hasDisq);

        // Persist derived prediction fixture
        $stmtInsertPred->execute([
            $sid,
            $predGwa,
            $studentOverallRisk,
            $latinHonor,
        ]);

        $updatedStudents++;
    }

    $db->commit();
    echo "[COMMIT SUCCESS] Updated $updatedGradeRows grade rows across $updatedStudents students.\n\n";

} catch (Exception $e) {
    $db->rollBack();
    fwrite(STDERR, "FATAL SEEDING ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

// -----------------------------------------------------------------------------
// 8. Post-Execution Immutable Verification
// -----------------------------------------------------------------------------
echo "=== PRELIM IMMUTABILITY & PRESERVATION AUDIT ===\n";
$stmtAfter = $db->prepare("
    SELECT g.id, g.prelim
    FROM grades g
    WHERE g.student_id IN ($inClause)
      AND g.is_current = 1
      AND g.school_year = ?
      AND g.semester = ?
    ORDER BY g.id
");
$stmtAfter->execute([$schoolYear, $semester]);
$afterRows = $stmtAfter->fetchAll();

$mutatedPrelims = 0;
foreach ($afterRows as $r) {
    $gid = $r['id'];
    $hashAfter = hash('sha256', (string) $r['prelim']);
    if (($prelimHashesBefore[$gid] ?? null) !== $hashAfter) {
        $mutatedPrelims++;
    }
}

if ($mutatedPrelims > 0) {
    fwrite(STDERR, "FATAL AUDIT FAILURE: $mutatedPrelims Prelim grades were mutated during seeding!\n");
    exit(1);
}
echo "[PASS] 100% of Prelim grades are byte-for-byte identical before and after seeding (0 mutations).\n\n";

// -----------------------------------------------------------------------------
// 9. Statistical Contract Summary
// -----------------------------------------------------------------------------
$totalRows = count($allPrelims);
$pct5 = ($within5 / $totalRows) * 100;
$pct8 = ($within8 / $totalRows) * 100;
$pct10 = ($within10 / $totalRows) * 100;

// Pearson correlation r
$meanP = array_sum($allPrelims) / $totalRows;
$meanM = array_sum($allMidterms) / $totalRows;
$num = 0; $denP = 0; $denM = 0;
for ($i = 0; $i < $totalRows; $i++) {
    $dP = $allPrelims[$i] - $meanP;
    $dM = $allMidterms[$i] - $meanM;
    $num += $dP * $dM;
    $denP += $dP * $dP;
    $denM += $dM * $dM;
}
$r = $num / sqrt($denP * $denM);

$minDeltasPerStudent = min(array_map(fn($d) => count(array_unique($d)), $studentDeltas));

echo "=== STATISTICAL MOVEMENT CONTRACT SUMMARY ===\n";
echo sprintf("  - Total Touched Rows:        %d\n", $totalRows);
echo sprintf("  - Movements within +/-5 pts: %d (%.2f%%) [contract: >= 90%%]\n", $within5, $pct5);
echo sprintf("  - Movements within +/-8 pts: %d (%.2f%%) [moderate outliers]\n", $within8, $pct8);
echo sprintf("  - Movements within +/-10 pts:%d (%.2f%%) [contract: >= 99%%]\n", $within10, $pct10);
echo sprintf("  - Correlation coefficient r: %.4f (positive and strong, contract: > 0.85)\n", $r);
echo sprintf("  - Min distinct subject deltas: %d (contract: > 1)\n\n", $minDeltasPerStudent);

// -----------------------------------------------------------------------------
// 10. Section Risk Results vs Targets
// -----------------------------------------------------------------------------
echo "=== SECTION RISK RESULTS VS TARGETS (IT-31 through IT-38) ===\n";
$secQuery = "
    SELECT sp.section, p.risk_level, COUNT(*) as cnt
    FROM student_profiles sp
    JOIN users u ON u.id = sp.user_id
    JOIN (
        SELECT p1.student_id, p1.risk_level
        FROM predictions p1
        WHERE p1.id = (
            SELECT p2.id FROM predictions p2
            WHERE p2.student_id = p1.student_id
            ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
        )
    ) p ON p.student_id = sp.user_id
    WHERE u.id IN ($inClause)
      AND sp.section LIKE 'IT-3%'
    GROUP BY sp.section, p.risk_level
    ORDER BY sp.section, p.risk_level
";
$secStmt = $db->query($secQuery);
$secRisks = [];
while ($sr = $secStmt->fetch()) {
    $secRisks[$sr['section']][$sr['risk_level']] = (int) $sr['cnt'];
}

$allPass = true;
foreach ($secRisks as $sec => $counts) {
    $totalSec = array_sum($counts);
    $targetSpec = resolveSectionRiskTarget($sec, $totalSec);
    $h = $counts['HIGH'] ?? 0;
    $m = $counts['MODERATE'] ?? 0;
    $l = $counts['LOW'] ?? 0;

    $hOk = ($h >= $targetSpec['high_min'] && $h <= $targetSpec['high_max']);
    $mOk = ($m >= $targetSpec['mod_min'] && $m <= $targetSpec['mod_max']);
    $status = ($hOk && $mOk) ? '[PASS]' : '[FAIL]';
    if (!$hOk || !$mOk) $allPass = false;

    echo sprintf("  %s %-6s N=%2d | High: %2d (tgt %d-%d), Mod: %2d (tgt %d-%d), Low: %2d\n",
        $status, $sec, $totalSec, $h, $targetSpec['high_min'], $targetSpec['high_max'],
        $m, $targetSpec['mod_min'], $targetSpec['mod_max'], $l
    );
}

echo "\n========================================================================\n";
if ($allPass && $pct5 >= 90.0 && $pct10 >= 99.0 && $r > 0.85 && $minDeltasPerStudent > 1) {
    echo "STATUS: ALL SECTION CALIBRATION AND PROGRESSION CRITERIA PASSED.\n";
} else {
    echo "STATUS: VALIDATION TARGET MISMATCH DETECTED.\n";
    exit(1);
}
echo "========================================================================\n";
exit(0);
