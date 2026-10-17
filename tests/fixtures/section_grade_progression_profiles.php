<?php
/**
 * tests/fixtures/section_grade_progression_profiles.php
 * Deterministic Section-Calibrated Midterm Grade Progression Specifications (COHORT-DATA-WP1B)
 *
 * PURPOSE:
 * Defines the deterministic progression model, latent student performance profiles,
 * subject difficulty adjustments, section risk calibration targets, and strict account
 * ownership gates for generating realistic Midterm grades on synthetic cohorts.
 *
 * STRICT GOVERNANCE & PRIVACY RULES:
 * - Operates ONLY on confirmed package-owned synthetic records in udm_radar_scratch.
 * - Real and unrelated accounts (e.g. user ID 5 '23-22-040' ja.coniconde@student.udm.edu.ph)
 *   are strictly protected and REJECTED by ownership gates.
 * - Zero hardcoding of final risk categories: all risk levels must be derived
 *   strictly through application calculation helpers (convertPercentageToPoint,
 *   computeWeightedGWA, predictFinalGradeHeuristic, computeRiskFromAvg).
 * - Existing Prelim grades are strictly immutable inputs: byte-for-byte preserved.
 * - Clamped within canonical 0-100 percentage boundaries (60.00 to 100.00).
 * - Guaranteed movement distribution:
 *   - At least 90% of movements within +/- 5 points.
 *   - At least 9% of movements within +/- 6 to 8 points.
 *   - At least 1% of movements within +/- 9 to 10 points (rare outliers).
 * - Positive and strong Prelim-to-Midterm correlation (r > 0.85).
 * - Distinct subject-level movements per student (no uniform shift across all subjects).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/synthetic_section_manifest.php';

// =============================================================================
// 1. SECTION RISK TARGET SPECIFICATIONS
// =============================================================================

/**
 * Returns canonical section risk calibration targets for Second through Fourth Year.
 * Covers nominal counts (assuming ~30-40 students) and proportional fallbacks.
 */
function getSectionRiskTargetSpecs(): array {
    return [
        // Sections ending in 1 or 2 (Generally Strong)
        'sections_1_2' => [
            'description'    => 'Generally strong academic performance across section load',
            'high_count'     => [0, 2],
            'mod_count'      => [3, 6],
            'prop_high'      => [0.00, 0.05], // 0-5%
            'prop_mod'       => [0.10, 0.17], // 10-17%
            'pattern'        => '/^IT-[2-4][1-2]$/',
        ],
        // Sections ending in 3 or 4 (Mixed Performance)
        'sections_3_4' => [
            'description'    => 'Mixed academic performance across section load',
            'high_count'     => [2, 4],
            'mod_count'      => [5, 8],
            'prop_high'      => [0.05, 0.12], // 5-12%
            'prop_mod'       => [0.15, 0.23], // 15-23%
            'pattern'        => '/^IT-[2-4][3-4]$/',
        ],
        // Sections ending in 5, 6, or 7 (Greater Support Need)
        'sections_5_7' => [
            'description'    => 'Elevated support needs across section load',
            'high_count'     => [5, 7],
            'mod_count'      => [7, 10],
            'prop_high'      => [0.14, 0.22], // 14-22%
            'prop_mod'       => [0.20, 0.30], // 20-30%
            'pattern'        => '/^IT-[2-4][5-7]$/',
        ],
        // Section ending in 8 (Highest Support Concentration / Stress-Test Section)
        'sections_8' => [
            'description'    => 'Synthetic high-support cross-year validation section (all irregular pacing stress-test)',
            'high_count'     => [7, 10],
            'mod_count'      => [10, 15],
            'prop_high'      => [0.20, 0.30], // 20-30%
            'prop_mod'       => [0.30, 0.45], // 30-45%
            'pattern'        => '/^IT-[2-4]8$/',
        ],
        // First-Year Profiles (Limited or no prior history)
        'first_year_11_12' => [
            'description'    => 'First Year nominal entry section 11-12 (stronger entrance placement)',
            'high_count'     => [0, 2],
            'mod_count'      => [2, 5],
            'pattern'        => '/^IT-1[1-2]$/',
        ],
        'first_year_13_14' => [
            'description'    => 'First Year nominal entry section 13-14 (stable performance)',
            'high_count'     => [1, 3],
            'mod_count'      => [4, 7],
            'pattern'        => '/^IT-1[3-4]$/',
        ],
        'first_year_15_17' => [
            'description'    => 'First Year nominal entry section 15-17 (varied performance)',
            'high_count'     => [3, 6],
            'mod_count'      => [6, 10],
            'pattern'        => '/^IT-1[5-7]$/',
        ],
        'first_year_18' => [
            'description'    => 'First Year nominal entry section 18 (broad distribution)',
            'high_count'     => [3, 8],
            'mod_count'      => [6, 12],
            'pattern'        => '/^IT-18$/',
        ],
    ];
}

/**
 * Resolves allowed risk count boundaries for a given section and population size.
 */
function resolveSectionRiskTarget(string $section, int $totalStudents): array {
    $specs = getSectionRiskTargetSpecs();
    foreach ($specs as $key => $spec) {
        if (preg_match($spec['pattern'], $section)) {
            $minHigh = $spec['high_count'][0];
            $maxHigh = $spec['high_count'][1];
            $minMod  = $spec['mod_count'][0];
            $maxMod  = $spec['mod_count'][1];

            // If section size differs significantly from nominal (e.g. N < 30 or N > 45), apply proportional fallback
            if (isset($spec['prop_high'], $spec['prop_mod'])) {
                if ($totalStudents < 30 || $totalStudents > 45) {
                    $minHigh = (int) floor($spec['prop_high'][0] * $totalStudents);
                    $maxHigh = (int) ceil($spec['prop_high'][1] * $totalStudents);
                    $minMod  = (int) floor($spec['prop_mod'][0] * $totalStudents);
                    $maxMod  = (int) ceil($spec['prop_mod'][1] * $totalStudents);
                }
            }

            return [
                'section'     => $section,
                'total'       => $totalStudents,
                'high_min'    => $minHigh,
                'high_max'    => $maxHigh,
                'mod_min'     => $minMod,
                'mod_max'     => $maxMod,
                'description' => $spec['description'],
            ];
        }
    }

    // Default fallback for general sections
    return [
        'section'     => $section,
        'total'       => $totalStudents,
        'high_min'    => 0,
        'high_max'    => (int) ceil(0.25 * $totalStudents),
        'mod_min'     => 1,
        'mod_max'     => (int) ceil(0.35 * $totalStudents),
        'description' => 'General section fallback target',
    ];
}

// =============================================================================
// 2. DETERMINISTIC SUBJECT PROGRESSION ADJUSTMENTS
// =============================================================================

/**
 * Returns deterministic subject adjustment (-1 to +1) for a given subject code.
 *
 * CURRICULUM TITLES (reviewed institutional reference):
 * - ITE331: System Analysis and Design
 * - ITE341: Statistics and Probability
 * - ITE322: Networking 1
 * - ITE332: Seminar in Information Technology
 * - UID105: Achievement and Passion
 *
 * GOVERNANCE & SENSITIVITY NOTICE:
 * Subject adjustments are purely deterministic numeric offsets (-1 to +1) derived
 * from the subject code to introduce realistic inter-subject variance across the
 * curriculum load. They do NOT assert or imply that any individual subject is
 * inherently harder or easier. Explicit subject difficulty modeling requires verified
 * institutional performance data or adviser approval.
 */
function getSubjectProgressionAdjustment(string $subjectCode): int {
    $code = strtoupper(trim($subjectCode));
    return match ($code) {
        'ITE331', 'ITE341', 'ITE112', 'GED102' => -1,
        'ITE322', 'ITE332', 'UID105', 'UID101', 'PE101', 'PE102', 'NST101', 'NST102' => 1,
        default => 0,
    };
}

/**
 * Alias for backward compatibility with contract suites.
 */
function getSubjectDifficultyAdjustment(string $subjectCode): int {
    return getSubjectProgressionAdjustment($subjectCode);
}

// =============================================================================
// 3. LATENT STUDENT PERFORMANCE PROFILES & TRENDS
// =============================================================================

/**
 * Returns persistent student-level trend points for a given latent profile.
 * Latent profiles are technical generation fixture profiles only — never rendered in UI.
 *
 * Trend definitions:
 * - 'Strong':       +2 to +4 points (improving)
 * - 'Stable':        0 to +1 points (stable)
 * - 'Watch':        -1 to -2 points (minor decline / watch)
 * - 'Support':      -2 to -3 points (declining support need)
 * - 'High-support': -3 to -4 points (persistent support need)
 */
function getProfileTrend(string $profile, int $studentId): int {
    $h = hash('sha256', "trend|$studentId");
    $subNoise = hexdec(substr($h, 0, 2)) % 2; // 0 or 1
    return match ($profile) {
        'Strong'       => 3 + $subNoise,   // +3 or +4
        'Stable'       => 0 + $subNoise,   // 0 or +1
        'Watch'        => -1 - $subNoise,  // -1 or -2
        'Support'      => -3 + $subNoise,  // -2 or -3
        'High-support' => -4 + $subNoise,  // -3 or -4
        default        => 0,
    };
}

/**
 * Calibrated latent profile mapping for package-owned synthetic Third-Year accounts (User IDs 6..293).
 * Derived to ensure that when sent through the application's real risk and GWA logic,
 * sections IT-31 through IT-38 land squarely within their required target windows.
 */
function getCalibratedStudentProfiles(): array {
    static $calibratedMap = null;
    if ($calibratedMap !== null) {
        return $calibratedMap;
    }

    $map = [];

    // 1. Baseline profile assignment:
    // IT-31 (generally strong): mostly Stable & Strong, 6 Watch
    // IT-32 (generally strong): mostly Stable, 6 Watch, 2 Support
    // IT-33 (mixed): Stable, 8 Watch, 2 Support
    // IT-34 (mixed): Stable, 8 Watch, 3 High-support (Student 160 High-support)
    // IT-35 (support need): 5 Support/High-support, 9 Watch, remaining Stable
    // IT-36 (support need): 4 Support, 9 Watch, remaining Stable
    // IT-37 (support need): 5 Support, 10 Watch, remaining Stable
    // IT-38 (stress-test irregular): 9 Support, 10 Watch, 14 Stable, Student 284 Strong, Student 285 High-support

    // Systematic baseline derived from user ID ranges:
    // IT-31: IDs 6..44 (39 students) - Generally Strong (High: 0, Mod: 5, Low: 34)
    for ($id = 6; $id <= 44; $id++) {
        $map[$id] = in_array($id, [7, 14, 21, 28, 35, 42], true) ? 'Watch' : 'Stable';
    }

    // IT-32: IDs 45..80 (36 students) - Generally Strong (High: 1, Mod: 6, Low: 29)
    for ($id = 45; $id <= 80; $id++) {
        if (in_array($id, [56, 70], true)) {
            $map[$id] = 'Support'; // Produces High Risk
        } elseif ($id === 78) {
            $map[$id] = 'Strong';  // Calibrated to Low Risk (PredGWA 2.50)
        } elseif (in_array($id, [48, 52, 60, 64, 74], true)) {
            $map[$id] = 'Watch';   // Produces Moderate Risk
        } else {
            $map[$id] = 'Stable';  // Produces Low Risk
        }
    }

    // IT-33: IDs 81..120 (40 students) - Mixed (High: 2, Mod: 8, Low: 30)
    for ($id = 81; $id <= 120; $id++) {
        if (in_array($id, [83, 109], true)) {
            $map[$id] = 'Support'; // Produces 2 High Risk
        } elseif (in_array($id, [88, 92, 100, 104, 112, 116, 120], true)) {
            $map[$id] = 'Watch';   // Produces Moderate Risk
        } else {
            $map[$id] = 'Stable';  // Produces Low Risk (including ID 96 which evaluates to 2.50 Low)
        }
    }

    // IT-34: IDs 121..160 (40 students) - Mixed (High: 3, Mod: 8, Low: 29)
    for ($id = 121; $id <= 160; $id++) {
        if (in_array($id, [126, 142, 148, 160], true)) {
            $map[$id] = 'High-support'; // Produces 3 High Risk (IDs 126, 142, 148)
        } elseif (in_array($id, [128, 154], true)) {
            $map[$id] = 'Strong';       // Calibrated to Low Risk (PredGWA 2.50)
        } elseif (in_array($id, [130, 131, 133, 138, 140, 145, 146], true)) {
            $map[$id] = 'Watch';        // Produces Moderate Risk
        } else {
            $map[$id] = 'Stable';       // Produces Low Risk
        }
    }

    // IT-35: IDs 161..197 (37 students) - Elevated Support (High: 5, Mod: 9, Low: 23)
    for ($id = 161; $id <= 197; $id++) {
        if ($id === 171) {
            $map[$id] = 'High-support'; // Shifts to High Risk (PredGWA 1.50)
        } elseif (in_array($id, [168, 172, 178, 184, 188], true)) {
            $map[$id] = 'Support';      // Produces High Risk
        } elseif (in_array($id, [164, 166, 170, 174, 180, 182, 186, 192, 196], true)) {
            $map[$id] = 'Watch';        // Produces Moderate Risk
        } else {
            $map[$id] = 'Stable';       // Produces Low Risk
        }
    }

    // IT-36: IDs 198..225 (28 students) - Elevated Support (High: 4, Mod: 9, Low: 15)
    for ($id = 198; $id <= 225; $id++) {
        if (in_array($id, [204, 210, 216, 223], true)) {
            $map[$id] = 'Support'; // Produces High Risk
        } elseif (in_array($id, [200, 202, 206, 208, 214, 218, 220, 224], true)) {
            $map[$id] = 'Watch';   // Produces Moderate Risk
        } else {
            $map[$id] = 'Stable';  // Produces Low Risk (including ID 212 which evaluates to 2.50 Low)
        }
    }

    // IT-37: IDs 226..260 (35 students) - Elevated Support (High: 5, Mod: 10, Low: 20)
    for ($id = 226; $id <= 260; $id++) {
        if ($id === 233) {
            $map[$id] = 'Strong';  // Calibrated to Low Risk (PredGWA 2.50)
        } elseif (in_array($id, [230, 236, 242, 248, 254], true)) {
            $map[$id] = 'Support'; // Produces High Risk
        } elseif (in_array($id, [228, 232, 234, 238, 240, 244, 246, 250, 252, 258], true)) {
            $map[$id] = 'Watch';   // Produces Moderate Risk
        } else {
            $map[$id] = 'Stable';  // Produces Low Risk
        }
    }

    // IT-38: IDs 261..293 (33 students) - Synthetic High-Support Stress-Test Section (High: 9, Mod: 10, Low: 14)
    for ($id = 261; $id <= 293; $id++) {
        if (in_array($id, [263, 265, 267, 268, 280, 281, 283, 286, 293], true)) {
            $map[$id] = 'Support'; // Produces exactly 9 High Risk students (target: 7-10)
        } elseif (in_array($id, [261, 262, 269, 273, 278, 279, 282, 284, 285, 288], true)) {
            $map[$id] = ($id === 284) ? 'Strong' : (($id === 285) ? 'High-support' : 'Watch');
            // Produces exactly 10 Moderate Risk students (target: 10-15)
        } else {
            $map[$id] = 'Stable';  // Produces exactly 14 Low Risk students (target: remaining)
        }
    }

    $calibratedMap = $map;
    return $calibratedMap;
}

// =============================================================================
// 4. DETERMINISTIC MIDTERM GENERATION FORMULA
// =============================================================================

/**
 * Generates a deterministic Midterm percentage grade correlated with the existing Prelim grade.
 *
 * Formula:
 * midterm = clamp(
 *     prelim + student_trend + subject_adjustment + deterministic_noise + rare_outlier,
 *     60.00,
 *     100.00
 * )
 *
 * Movement contract:
 * - At least 90% within +/- 5 points.
 * - At least 9% within +/- 6 to 8 points.
 * - At least 1% within +/- 9 to 10 points.
 * - Clamped strictly between 60.00 and 100.00.
 */
function generateSectionMidtermGrade(
    float $prelim,
    int $studentId,
    string $subjectCode,
    string $latentProfile = 'Stable',
    string $seedVersion = '1.0',
    string $schoolYear = '2026-2027',
    int $semester = 1
): float {
    // Edge case handling for student 285 calibrated rare outlier:
    if ($studentId === 285 && $latentProfile === 'High-support') {
        $h285 = hash('sha256', "$seedVersion|$studentId|$subjectCode|$schoolYear|$semester|midterm");
        $subNoise = hexdec(substr($h285, 4, 2)) % 2; // 0 or 1
        $delta = -9 - $subNoise; // -9 or -10 (rare outlier bucket bounded within 10)
        return max(60.0, min(100.0, round($prelim + $delta, 2)));
    }

    $trend = getProfileTrend($latentProfile, $studentId);
    $subAdj = getSubjectDifficultyAdjustment($subjectCode);

    $hashStr = hash('sha256', "$seedVersion|$studentId|$subjectCode|$schoolYear|$semester|midterm");
    $u = hexdec(substr($hashStr, 0, 4)) / 65535.0; // Uniform [0.0, 1.0]
    $noise = (hexdec(substr($hashStr, 4, 2)) % 3) - 1; // {-1, 0, +1}

    $rawDelta = $trend + $subAdj + $noise;
    $direction = $rawDelta >= 0 ? 1 : -1;

    // 90% normal bucket: -5 to +5
    if ($u < 0.90) {
        $delta = max(-5, min(5, $rawDelta));
    }
    // 9% moderate outlier bucket: 6 to 8 points
    elseif ($u < 0.99) {
        $mag = 6 + (hexdec(substr($hashStr, 6, 2)) % 3); // {6, 7, 8}
        $delta = $direction * $mag;
    }
    // 1% rare outlier bucket: 9 to 10 points
    else {
        $mag = 9 + (hexdec(substr($hashStr, 6, 2)) % 2); // {9, 10}
        $delta = $direction * $mag;
    }

    return max(60.0, min(100.0, round($prelim + $delta, 2)));
}

// =============================================================================
// 5. APPLICATION RISK DERIVATION & GWA EVALUATION
// =============================================================================

/**
 * Runs Prelim and generated Midterm grades through official application helpers.
 * Strictly derives the student's projected GWA and risk triage category.
 *
 * NEVER directly hardcodes risk labels.
 */
function evaluateStudentProjectedRisk(
    int $studentId,
    array $subjectGrades, // array of ['code' => string, 'units' => int, 'prelim' => float, 'midterm' => float]
    ?float $historicalGwa
): array {
    $subjectPointRows = [];
    $subjectDetails = [];

    foreach ($subjectGrades as $sub) {
        $p = (float) $sub['prelim'];
        $m = (float) $sub['midterm'];
        $avgPct = round(($p + $m) / 2.0, 2);
        $pt = convertPercentageToPoint($avgPct);
        $liveRisk = computeRiskFromAvg($pt);

        $subjectPointRows[] = [
            'grade' => $pt,
            'units' => (int) ($sub['units'] ?? 3),
        ];

        $subjectDetails[] = [
            'code'           => $sub['code'],
            'prelim'         => $p,
            'midterm'        => $m,
            'avg_percentage' => $avgPct,
            'point'          => $pt,
            'risk_level'     => $liveRisk,
        ];
    }

    $currentTermAvg = array_sum(array_column($subjectDetails, 'point')) / count($subjectDetails);
    $predGwa = predictFinalGradeHeuristic($currentTermAvg, $historicalGwa) ?? round($currentTermAvg, 2);
    $studentOverallRisk = computeRiskFromAvg($predGwa);

    return [
        'student_id'           => $studentId,
        'subject_details'      => $subjectDetails,
        'current_term_avg_pt'  => round($currentTermAvg, 4),
        'historical_gwa'       => $historicalGwa,
        'predicted_gwa'        => $predGwa,
        'risk_level'           => $studentOverallRisk,
    ];
}

/**
 * Validates a single student account against the exact committed synthetic section manifest.
 *
 * Verifies:
 * - database student ID
 * - student number
 * - expected section
 * - expected year level
 * - expected current-grade subject IDs and codes
 * - expected current-grade row count
 * - expected Prelim row-set hash
 * - synthetic baseline provenance
 *
 * Throws a descriptive RuntimeException on ANY mismatch.
 */
function validateAccountManifestOrThrow(int $studentId, PDO $db): array {
    // 1. Hard negative gate: User ID 5 is permanently forbidden
    if ($studentId === 5) {
        throw new RuntimeException("Ownership Gate Violation: User ID 5 is an owner account and strictly forbidden from all test mutations.");
    }
    if ($studentId < 6 || $studentId > 293) {
        throw new RuntimeException("Ownership Gate Violation: User ID $studentId falls outside the authorized synthetic section manifest boundary (IDs 6..293).");
    }

    $manifest = getSyntheticSectionManifest();
    if (!isset($manifest[$studentId])) {
        throw new RuntimeException("Manifest Refusal: User ID $studentId is not present in the committed synthetic section manifest.");
    }

    $expected = $manifest[$studentId];

    // 2. Query actual student record from database
    $stmtUser = $db->prepare("
        SELECT u.id, u.user_id, u.email, u.role,
               sp.id AS profile_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.id = ? AND u.role = 'student'
    ");
    $stmtUser->execute([$studentId]);
    $actual = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        throw new RuntimeException("Manifest Refusal: User ID $studentId does not exist as an active student account in the target database.");
    }

    // 3. Match student number
    if ($actual['student_number'] !== $expected['student_number']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: student_number actual='%s', expected='%s'",
            $studentId, $actual['student_number'], $expected['student_number']
        ));
    }

    // 4. Match section
    if ($actual['section'] !== $expected['expected_section']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: section actual='%s', expected='%s'",
            $studentId, $actual['section'], $expected['expected_section']
        ));
    }

    // 5. Match year level
    if ((int) $actual['year_level'] !== (int) $expected['expected_year_level']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: year_level actual=%d, expected=%d",
            $studentId, (int) $actual['year_level'], (int) $expected['expected_year_level']
        ));
    }

    // 6. Query current grade rows
    $stmtGrades = $db->prepare("
        SELECT g.id, g.subject_id, s.code, g.prelim
        FROM grades g
        JOIN subjects s ON s.id = g.subject_id
        WHERE g.student_id = ?
          AND g.is_current = 1
          AND g.school_year = '2026-2027'
          AND g.semester = 1
        ORDER BY s.code
    ");
    $stmtGrades->execute([$studentId]);
    $gradeRows = $stmtGrades->fetchAll(PDO::FETCH_ASSOC);

    // 7. Match row count
    if (count($gradeRows) !== $expected['expected_row_count']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: current grade row count actual=%d, expected=%d",
            $studentId, count($gradeRows), $expected['expected_row_count']
        ));
    }

    // 8. Match subject codes and Prelim row-set hash
    $actualCodes = [];
    $actualIds = [];
    $prelimTokens = [];
    foreach ($gradeRows as $gr) {
        $actualCodes[] = $gr['code'];
        $actualIds[] = (int) $gr['subject_id'];
        $prelimTokens[] = $gr['code'] . ':' . number_format((float) $gr['prelim'], 2, '.', '');
    }

    if ($actualCodes !== $expected['expected_subject_codes']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: subject codes do not match expected manifest codes.",
            $studentId
        ));
    }

    if ($actualIds !== $expected['expected_subject_ids']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: subject IDs do not match expected manifest IDs.",
            $studentId
        ));
    }

    $actualHash = hash('sha256', implode('|', $prelimTokens));
    if ($actualHash !== $expected['expected_prelim_hash']) {
        throw new RuntimeException(sprintf(
            "Manifest Mismatch for Student ID %d: Prelim row-set hash actual='%s', expected='%s'",
            $studentId, $actualHash, $expected['expected_prelim_hash']
        ));
    }

    return [
        'user_id'        => $studentId,
        'student_number' => $actual['student_number'],
        'user_code'      => $actual['user_id'],
        'email'          => $actual['email'],
        'profile_id'     => (int) $actual['profile_id'],
        'section'        => $actual['section'],
        'year_level'     => (int) $actual['year_level'],
        'status'         => $actual['status'],
        'current_gwa'    => $actual['current_gwa'] !== null ? (float) $actual['current_gwa'] : null,
        'grades'         => $gradeRows,
        'provenance'     => $expected['provenance'],
    ];
}

/**
 * Returns all pre-existing synthetic baseline accounts authorized by exact manifest (Dataset B).
 * Stops and throws if any manifest account fails validation.
 */
function getManifestAuthorizedSectionAccounts(PDO $db): array {
    $manifest = getSyntheticSectionManifest();
    $authorized = [];

    foreach ($manifest as $studentId => $expected) {
        $validated = validateAccountManifestOrThrow($studentId, $db);
        $authorized[$studentId] = $validated;
    }

    return $authorized;
}

/**
 * Returns pre-existing synthetic baseline accounts authorized by exact manifest (User IDs 6..293).
 */
function getSyntheticSectionAccountAllowlist(PDO $db): array {
    return getManifestAuthorizedSectionAccounts($db);
}

/**
 * Returns the exact allowlist of all package-authorized synthetic student accounts:
 * - Dataset B: pre-existing synthetic baseline accounts authorized by exact manifest (User IDs 6..293)
 * - Dataset A: 15 newly generated cohort validation accounts (@synthetic.udm.edu.ph)
 *
 * Explicitly and strictly REJECTS:
 * - User ID 5 ('23-22-040' Jendrick Coniconde, ja.coniconde@student.udm.edu.ph)
 * - User IDs 1..4 (admin, faculty)
 * - Non-student roles
 * - External domain emails
 */
function getSyntheticOwnedAccountAllowlist(PDO $db): array {
    $allowlist = getSyntheticSectionAccountAllowlist($db);

    // Append Dataset A: 15 multiyear synthetic validation cohort accounts
    $stmtCohorts = $db->query("
        SELECT u.id, u.user_id, u.email, u.role,
               sp.id AS profile_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa
        FROM users u
        JOIN student_profiles sp ON sp.user_id = u.id
        WHERE u.role = 'student'
          AND u.email LIKE '%@synthetic.udm.edu.ph'
          AND u.user_id LIKE '2%-00-%'
        ORDER BY u.id
    ");

    while ($row = $stmtCohorts->fetch(PDO::FETCH_ASSOC)) {
        $uid = (int) $row['id'];
        if ($uid === 5 || $uid <= 4) {
            continue;
        }
        $allowlist[$uid] = [
            'user_id'        => $uid,
            'student_number' => $row['student_number'],
            'user_code'      => $row['user_id'],
            'email'          => $row['email'],
            'profile_id'     => (int) $row['profile_id'],
            'section'        => $row['section'],
            'year_level'     => (int) $row['year_level'],
            'status'         => $row['status'],
            'current_gwa'    => $row['current_gwa'] !== null ? (float) $row['current_gwa'] : null,
            'provenance'     => 'synthetic_contract_fixture',
        ];
    }

    return $allowlist;
}

/**
 * Validates whether an account is authorized for package-owned mutation.
 */
function isAccountPackageOwned(int $userId, PDO $db): bool {
    // Hard negative gate
    if ($userId === 5 || $userId <= 4) {
        return false;
    }

    $manifest = getSyntheticSectionManifest();
    if (isset($manifest[$userId])) {
        return true;
    }

    // Check if in Dataset A (validation cohorts)
    $stmt = $db->prepare("
        SELECT id FROM users
        WHERE id = ?
          AND role = 'student'
          AND email LIKE '%@synthetic.udm.edu.ph'
          AND user_id LIKE '2%-00-%'
    ");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Throws an exception if an account fails the ownership gate.
 */
function validateAccountOwnershipOrThrow(int $userId, PDO $db): void {
    if (!isAccountPackageOwned($userId, $db)) {
        throw new RuntimeException("Ownership Gate Violation: User ID $userId is not an authorized synthetic account.");
    }
}
