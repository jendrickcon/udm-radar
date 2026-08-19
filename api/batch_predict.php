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
    
    // Fetch all active student IDs
    $stmt = $db->query("SELECT id FROM users WHERE role = 'student'");
    $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $successCount = 0;
    $failCount = 0;

    // Loop through every student and recalculate their AI risk profile
    foreach ($students as $studentId) {
        $result = getStudentPrediction((int)$studentId, $db);
        if (isset($result['error'])) {
            $failCount++;
        } else {
            $successCount++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => "System-Wide AI Analysis complete. Successfully processed " . count($students) . " students."
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}