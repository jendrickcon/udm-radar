<?php
// api/predict.php — Calls the Python Flask API and saves prediction to DB
header('Content-Type: application/json');
session_start();
require_once '../includes/auth.php';
requireLogin();
require_once '../config/db.php';

$db         = getDB();
$student_id = $_SESSION['user_id'];

// Gather input data for the prediction
$input = json_decode(file_get_contents('php://input'), true);

// If not passed directly, build from DB
if (empty($input)) {
  $stmt = $db->prepare(
    'SELECT AVG(prelim) AS prelim_avg,
            SUM(CASE WHEN final_grade = 0.00 THEN 1 ELSE 0 END) AS failed_count
     FROM grades WHERE student_id = ? AND is_current = 1'
  );
  $stmt->execute([$student_id]);
  $stats = $stmt->fetch();

  $profStmt = $db->prepare('SELECT current_gwa FROM student_profiles WHERE user_id = ?');
  $profStmt->execute([$student_id]);
  $profile = $profStmt->fetch();

  $input = [
    'historical_gwa'       => $profile['current_gwa'] ?? 3.00,
    'current_prelim_avg'   => $stats['prelim_avg']    ?? 2.50,
    'failed_subjects_count'=> $stats['failed_count']  ?? 0,
    'irregular_semesters'  => 0,
  ];
}

// Call Python Flask API
$ch = curl_init('http://127.0.0.1:5000/predict');
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => json_encode($input),
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 5,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
  echo json_encode(['error' => 'ML service unavailable']);
  exit;
}

$result = json_decode($response, true);

// Save prediction to DB
$stmt = $db->prepare(
  'INSERT INTO predictions (student_id, predicted_gwa, risk_level, latin_honor, irregular_prob)
   VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
  $student_id,
  $result['predicted_gwa'],
  $result['risk_level'],
  $result['latin_honor'],
  $result['irregular_prob'],
]);

echo json_encode($result);
?>
