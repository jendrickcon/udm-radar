<?php
// admin/export_risk_roster.php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/csv_export_helpers.php'; 

requireRole('admin');
$user = currentUser();
$db = getDB();

$rawRisk    = trim($_GET['risk'] ?? '');
$rawSupport = trim($_GET['support'] ?? '');
$rawSearch  = trim($_GET['search'] ?? '');

$allowedRisks = ['HIGH', 'MODERATE', 'LOW', 'N/A'];
$riskFilter = in_array($rawRisk, $allowedRisks, true) ? $rawRisk : '';
$allowedSupport = ['active', 'needs_review', 'action_taken', 'acknowledged'];
$supportFilter = in_array($rawSupport, $allowedSupport, true) ? $rawSupport : '';
$searchQuery = mb_substr($rawSearch, 0, 100); 

$sql = "
    SELECT sp.student_number, u.first_name, u.middle_name, u.last_name, 
           sp.section, sp.year_level, sp.status, sp.current_gwa,
           p.predicted_gwa, p.risk_level AS latest_risk, p.generated_at AS prediction_date, p.prediction_source,
           asc_case.status AS support_status
    FROM student_profiles sp 
    JOIN users u ON u.id = sp.user_id 
    LEFT JOIN predictions p ON p.id = (
        SELECT p2.id FROM predictions p2 
        WHERE p2.student_id = sp.user_id 
        ORDER BY p2.generated_at DESC, p2.id DESC LIMIT 1
    )
    LEFT JOIN academic_support_cases asc_case ON asc_case.id = (
        SELECT asc2.id FROM academic_support_cases asc2 
        WHERE asc2.student_id = sp.user_id 
        AND asc2.status IN ('needs_review', 'action_taken', 'acknowledged')
        ORDER BY asc2.created_at DESC, asc2.id DESC LIMIT 1
    )
    WHERE sp.status != 'Archived'
";

$params = [];

if ($riskFilter !== '') {
    if ($riskFilter === 'N/A') {
        $sql .= " AND (p.risk_level IS NULL OR p.risk_level = '')";
    } else {
        $sql .= " AND p.risk_level = ?";
        $params[] = $riskFilter;
    }
}

if ($supportFilter === 'active') {
    $sql .= " AND asc_case.status IN ('needs_review', 'action_taken', 'acknowledged')";
} elseif ($supportFilter !== '') {
    $sql .= " AND asc_case.status = ?";
    $params[] = $supportFilter;
}

if ($searchQuery !== '') {
    $sql .= " AND (sp.student_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR sp.section LIKE ?)";
    $likeTerm = '%' . $searchQuery . '%';
    array_push($params, $likeTerm, $likeTerm, $likeTerm, $likeTerm);
}

// FIXED: Corrected sorting direction using a CASE statement so HIGH is definitively first
$sql .= " ORDER BY CASE p.risk_level WHEN 'HIGH' THEN 1 WHEN 'MODERATE' THEN 2 WHEN 'LOW' THEN 3 ELSE 4 END ASC, sp.year_level ASC, sp.section ASC, u.last_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

logExportAudit($db, $user['id'], 'Program Risk Roster', 'CSV', [
    'risk' => $riskFilter, 'support' => $supportFilter, 'search' => $searchQuery
], count($students));

$output = startCsvDownload("UDM-RADAR_Program_Risk_Roster");

writeCsvMetadata($output, $user, 'Program Risk Roster', [
    'risk' => $riskFilter ?: 'All',
    'support case' => $supportFilter ?: 'All',
    'search query' => $searchQuery ?: 'None'
], count($students));

fputcsv($output, [
    'Student Number', 'Last Name', 'First Name', 'Middle Name',
    'Year Level', 'Section', 'Current GWA', 'Latest Predicted GWA',
    'Risk Classification', 'Prediction Source', 'Prediction Date', 'Active Support Status'
]);

foreach ($students as $s) {
    fputcsv($output, [
        sanitize_csv_field($s['student_number']),
        sanitize_csv_field($s['last_name']),
        sanitize_csv_field($s['first_name']),
        sanitize_csv_field($s['middle_name']),
        sanitize_csv_field($s['year_level']),
        sanitize_csv_field($s['section'] ?? 'N/A'),
        sanitize_csv_field($s['current_gwa'] !== null ? number_format((float)$s['current_gwa'], 2) : 'N/A'),
        sanitize_csv_field($s['predicted_gwa'] !== null ? number_format((float)$s['predicted_gwa'], 2) : 'N/A'),
        sanitize_csv_field($s['latest_risk'] ?? 'N/A'),
        sanitize_csv_field($s['prediction_source'] ?? 'N/A'),
        sanitize_csv_field($s['prediction_date'] ? date('Y-m-d H:i', strtotime($s['prediction_date'])) : 'N/A'),
        sanitize_csv_field($s['support_status'] ? ucwords(str_replace('_', ' ', $s['support_status'])) : 'None')
    ]);
}

fclose($output);
exit;