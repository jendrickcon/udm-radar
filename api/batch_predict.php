<?php
// api/batch_predict.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/predict.php'; // Reuse our robust prediction logic

// CRITICAL: Prevent PHP from timing out when processing hundreds of students
set_time_limit(0); 

header('Content-Type: application/json');

// Security check: Only admins can run system-wide recalculations
$user = currentUser();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized access. Admins only.']);
    exit;
}

try {
    $db = getDB();
    
    // Get the current academic term context to anchor the support cases
    $term = getCurrentTerm();
    $currentSy = $term['school_year'];
    $currentSem = $term['semester'];
    
    // Fetch all active student IDs
    $stmt = $db->query("SELECT id FROM users WHERE role = 'student'");
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $successCount = 0;
    $failCount = 0;
    $casesCreated = 0;

    // Loop through every student and recalculate their AI risk profile
    foreach ($students as $studentId) {
        $result = getStudentPrediction((int)$studentId, $db);
        
        if (isset($result['error'])) {
            $failCount++;
        } else {
            $successCount++;
            
            // --- INTERVENTION LOOP: AI TRIGGER ---
            // We only trigger support cases for students classified as HIGH risk
            if ($result['risk_level'] === 'HIGH') {
                
                // 1. Fetch the exact Prediction ID we just inserted
                $stmtPredId = $db->prepare("SELECT id FROM predictions WHERE student_id = ? ORDER BY generated_at DESC, id DESC LIMIT 1");
                $stmtPredId->execute([$studentId]);
                $predictionId = $stmtPredId->fetchColumn();

                // 2. Safeguard: Ensure we don't spam duplicate cases for the same student in the same term
                $stmtCheck = $db->prepare("SELECT id FROM academic_support_cases WHERE student_id = ? AND school_year = ? AND semester = ?");
                $stmtCheck->execute([$studentId, $currentSy, $currentSem]);
                $existingCase = $stmtCheck->fetchColumn();

                if (!$existingCase && $predictionId) {
                    // 3. Create the Human-in-the-Loop Review Case
                    // Notice how we lock in the immutable trigger stats so historical context is preserved
                    $stmtCreateCase = $db->prepare("
                        INSERT INTO academic_support_cases 
                        (student_id, prediction_id, trigger_risk_level, trigger_predicted_gwa, prediction_source, school_year, semester, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'needs_review')
                    ");
                    $stmtCreateCase->execute([
                        $studentId,
                        $predictionId,
                        $result['risk_level'],
                        $result['predicted_gwa'],
                        $result['prediction_source'],
                        $currentSy,
                        $currentSem
                    ]);
                    $casesCreated++;
                }
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => "System-Wide AI Analysis complete. Processed " . count($students) . " students. Triggered $casesCreated new academic support cases for review."
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}