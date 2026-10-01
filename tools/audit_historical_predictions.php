<?php
/**
 * tools/audit_historical_predictions.php — Audit Historical Prediction Provenance & Staleness (WP-7)
 *
 * READ-ONLY audit tool that analyzes prediction records in a specified database:
 * 1. Provenance inventory (prediction_source breakdown, nulls, invalid sources).
 * 2. Prediction freshness vs staleness:
 *    - Superseded historical rows vs latest active row per student.
 *    - Profile sync (student_profiles.predicted_gwa vs latest predictions.predicted_gwa).
 *    - Formula drift / recalculation eligibility (stored vs newly computed prediction).
 * 3. Feature completeness classification (Cases A, B, C, D):
 *    - Case A: Complete features (both historical GWA and current prelim average available)
 *    - Case B: Historical GWA only (no prelim grades in current term)
 *    - Case C: Prelim average only (no historical coursework recorded)
 *    - Case D: Missing both predictive features
 * 4. Value distribution and risk level consistency.
 * 5. Academic Support Case association and provenance propagation.
 *
 * Usage:
 *   php tools/audit_historical_predictions.php [scratch|live]
 * Default: scratch (udm_radar_scratch)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

$targetEnv = ($argc > 1 && strtolower($argv[1]) === 'live') ? 'live' : 'scratch';
$targetDb = ($targetEnv === 'live') ? 'udm_radar' : 'udm_radar_scratch';

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$targetDb;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "Error connecting to $targetDb: " . $e->getMessage() . "\n");
    exit(1);
}

echo "========================================================================\n";
echo "UDM-RADAR: HISTORICAL PREDICTION PROVENANCE & STALENESS AUDIT\n";
echo "Target Database: $targetDb [" . strtoupper($targetEnv) . "]\n";
echo "Mode: STRICTLY READ-ONLY\n";
echo "========================================================================\n\n";

// 1. Total & Provenance Breakdown
$totalPreds = (int) $pdo->query("SELECT COUNT(*) FROM predictions")->fetchColumn();
$provenanceStmt = $pdo->query("
    SELECT COALESCE(prediction_source, '<NULL>') AS source, COUNT(*) AS cnt,
           MIN(generated_at) AS earliest, MAX(generated_at) AS latest,
           ROUND(AVG(predicted_gwa), 4) AS avg_gwa
    FROM predictions
    GROUP BY prediction_source
    ORDER BY cnt DESC
");
$provenances = $provenanceStmt->fetchAll();

echo "=== 1. Prediction Provenance Breakdown ===\n";
echo "Total Prediction Rows: $totalPreds\n";
foreach ($provenances as $p) {
    printf("  - %-25s : %5d rows (Earliest: %s, Latest: %s, Avg GWA: %s)\n",
        $p['source'], $p['cnt'], $p['earliest'], $p['latest'], $p['avg_gwa']
    );
}

// 2. Risk Level Alignment Check
echo "\n=== 2. Risk Level Alignment Audit ===\n";
$riskStmt = $pdo->query("
    SELECT risk_level, COUNT(*) AS cnt,
           MIN(predicted_gwa) AS min_gwa, MAX(predicted_gwa) AS max_gwa
    FROM predictions
    GROUP BY risk_level
    ORDER BY FIELD(risk_level, 'LOW', 'MODERATE', 'HIGH')
");
$riskRows = $riskStmt->fetchAll();
foreach ($riskRows as $r) {
    printf("  - Risk: %-10s : %5d rows (GWA Range: %s to %s)\n",
        $r['risk_level'] ?? '<NULL>', $r['cnt'], $r['min_gwa'] ?? 'N/A', $r['max_gwa'] ?? 'N/A'
    );
}

// Check for misclassified risk levels under UDM-RADAR scale:
// GWA < 1.75 => HIGH, 1.75 <= GWA < 2.50 => MODERATE, GWA >= 2.50 => LOW
$misalignedCount = (int) $pdo->query("
    SELECT COUNT(*) FROM predictions
    WHERE (risk_level = 'HIGH' AND predicted_gwa >= 1.75)
       OR (risk_level = 'MODERATE' AND (predicted_gwa < 1.75 OR predicted_gwa >= 2.50))
       OR (risk_level = 'LOW' AND predicted_gwa < 2.50)
")->fetchColumn();
echo "Misaligned Risk Rows: $misalignedCount\n";

// 3. Freshness vs Staleness
echo "\n=== 3. Prediction Freshness & Staleness Analysis ===\n";

$studentsWithPreds = (int) $pdo->query("SELECT COUNT(DISTINCT student_id) FROM predictions")->fetchColumn();
$multiPredStudents = (int) $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT student_id FROM predictions GROUP BY student_id HAVING COUNT(*) > 1
    ) AS multi
")->fetchColumn();

echo "Distinct Students with Predictions: $studentsWithPreds\n";
echo "Students with Multiple Historical Predictions: $multiPredStudents\n";

// Superseded historical rows
$supersededCount = (int) $pdo->query("
    SELECT COUNT(*) FROM predictions p
    WHERE p.id NOT IN (
        SELECT MAX(id) FROM predictions GROUP BY student_id
    )
")->fetchColumn();
echo "Superseded Historical (Non-Latest) Prediction Rows: $supersededCount\n";
echo "Active Latest Prediction Rows: " . ($totalPreds - $supersededCount) . "\n";

// Check synchronization between student_profiles.predicted_gwa and latest predictions row
$profileSyncDiscrepancies = (int) $pdo->query("
    SELECT COUNT(*)
    FROM student_profiles sp
    JOIN (
        SELECT student_id, predicted_gwa AS latest_pred_gwa
        FROM predictions
        WHERE id IN (SELECT MAX(id) FROM predictions GROUP BY student_id)
    ) lp ON sp.user_id = lp.student_id
    WHERE ABS(COALESCE(sp.predicted_gwa, 0.0) - COALESCE(lp.latest_pred_gwa, 0.0)) > 0.001
")->fetchColumn();
echo "Student Profiles with predicted_gwa differing from latest prediction: $profileSyncDiscrepancies\n";

// Check grade batch approvals occurring after latest prediction
$batchesAfterPred = (int) $pdo->query("
    SELECT COUNT(DISTINCT p.student_id)
    FROM predictions p
    JOIN (
        SELECT student_id, MAX(id) AS latest_id, MAX(generated_at) AS latest_time
        FROM predictions
        GROUP BY student_id
    ) lp ON p.id = lp.latest_id
    JOIN pending_grade_batches b ON b.status = 'approved' AND b.resolved_at > lp.latest_time
")->fetchColumn();
echo "Approved Grade Batches resolved after student's latest prediction: $batchesAfterPred\n";

// 4. Feature Completeness Classification (Cases A, B, C, D)
echo "\n=== 4. Feature Completeness Audit (Active Student Cohort) ===\n";

$term = getCurrentTerm();
$currentSy = $term['school_year'];
$currentSem = $term['semester'];

$cohortStmt = $pdo->query("
    SELECT sp.user_id, sp.current_gwa,
           (SELECT AVG(g.prelim) FROM grades g 
            WHERE g.student_id = sp.user_id 
              AND g.school_year = '{$currentSy}' 
              AND g.semester = '{$currentSem}' 
              AND g.prelim IS NOT NULL) AS current_prelim_avg,
           (SELECT lp.predicted_gwa FROM predictions lp 
            WHERE lp.id = (SELECT MAX(p.id) FROM predictions p WHERE p.student_id = sp.user_id)) AS stored_latest_pred
    FROM student_profiles sp
    JOIN users u ON sp.user_id = u.id
    WHERE u.is_active = 1
");
$cohort = $cohortStmt->fetchAll();

$caseA = 0; // Complete (both)
$caseB = 0; // Hist GWA only
$caseC = 0; // Prelim only
$caseD = 0; // Missing both

$recalculationEligible = 0;

foreach ($cohort as $s) {
    $histGwa = $s['current_gwa'] !== null ? (float)$s['current_gwa'] : null;
    $prelimAvg = $s['current_prelim_avg'] !== null ? (float)$s['current_prelim_avg'] : null;

    $hasHist = ($histGwa !== null && $histGwa > 0);
    $hasPrelim = ($prelimAvg !== null);

    if ($hasHist && $hasPrelim) {
        $caseA++;
        // Recalculate using current reconciled 50/50 fallback blend snapped to 0.25
        $recalculated = predictFinalGradeHeuristic($prelimAvg, $histGwa);
        $stored = $s['stored_latest_pred'] !== null ? (float)$s['stored_latest_pred'] : null;
        if ($stored === null || abs($recalculated - $stored) > 0.001) {
            $recalculationEligible++;
        }
    } elseif ($hasHist && !$hasPrelim) {
        $caseB++;
    } elseif (!$hasHist && $hasPrelim) {
        $caseC++;
    } else {
        $caseD++;
    }
}

echo "Active Student Cohort Feature Completeness Distribution (Total: " . count($cohort) . "):\n";
echo "  - Case A (Complete: Hist GWA + Prelim Avg) : $caseA students (Eligible for ML Model / Fallback)\n";
echo "  - Case B (Partial: Hist GWA only)          : $caseB students (Provisional historical estimate)\n";
echo "  - Case C (Partial: Prelim Avg only)         : $caseC students (Provisional term-only estimate)\n";
echo "  - Case D (Missing Both Features)           : $caseD students (No prediction possible)\n";
echo "Case A Students with recalculation delta vs stored prediction: $recalculationEligible\n";

// 5. Academic Support Case Association
echo "\n=== 5. Academic Support Case Association ===\n";
$supportCases = $pdo->query("
    SELECT prediction_source, status, COUNT(*) AS cnt
    FROM academic_support_cases
    GROUP BY prediction_source, status
    ORDER BY cnt DESC
")->fetchAll();

if (empty($supportCases)) {
    echo "  (No academic support cases found in $targetDb)\n";
} else {
    foreach ($supportCases as $sc) {
        printf("  - Source: %-22s | Status: %-12s | Count: %d\n",
            $sc['prediction_source'] ?? '<NULL>',
            $sc['status'],
            $sc['cnt']
        );
    }
}

// 6. Summary Classification
echo "\n=== 6. Historical Prediction Categorization Summary ===\n";
echo "Category 1: Valid Active Predictions (Current Contract): " . ($totalPreds - $supersededCount) . " rows\n";
echo "Category 2: Legacy Heuristic Rows (Historical Provenance): " . $totalPreds . " rows\n";
echo "Category 3: Superseded Historical Logs (Pre-WP Audits):   " . $supersededCount . " rows\n";
echo "Category 4: Recalculation Eligible Due to Formula/Grades:  " . $recalculationEligible . " students\n";

echo "\n========================================================================\n";
echo "AUDIT COMPLETE: All checks performed safely in READ-ONLY mode.\n";
echo "========================================================================\n";
