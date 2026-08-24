<?php
// admin/export_intervention_audit.php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/csv_export_helpers.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

// FIXED: Dynamically pulls lifecycle timestamps from the history log and identifies the assigned faculty
$sql = "
    SELECT 
        asc_case.id AS case_id,
        sp.student_number,
        u.first_name AS student_first,
        u.last_name AS student_last,
        asc_case.trigger_risk_level,
        asc_case.created_at AS creation_date,
        asc_case.status,
        f.first_name AS faculty_first,
        f.last_name AS faculty_last,
        (SELECT MAX(created_at) FROM support_status_history WHERE case_id = asc_case.id AND new_status = 'action_taken') AS notice_sent_at,
        (SELECT MAX(created_at) FROM support_status_history WHERE case_id = asc_case.id AND new_status = 'acknowledged') AS acknowledged_at,
        (SELECT MAX(created_at) FROM support_status_history WHERE case_id = asc_case.id AND new_status IN ('resolved', 'closed')) AS resolved_at
    FROM academic_support_cases asc_case
    JOIN users u ON asc_case.student_id = u.id
    JOIN student_profiles sp ON sp.user_id = u.id
    LEFT JOIN users f ON asc_case.assigned_faculty_id = f.id
    ORDER BY asc_case.created_at DESC
";
$cases = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

logExportAudit($db, $user['id'], 'Intervention Audit Trail', 'CSV', [], count($cases));

$output = startCsvDownload("UDM-RADAR_Intervention_Audit_Trail");

writeCsvMetadata($output, $user, 'Intervention Audit Trail', ['scope' => 'Institution-Wide (All Time)'], count($cases));

fputcsv($output, [
    'Case ID', 'Student Number', 'Student Name', 'Risk Classification', 
    'Case Created', 'Reviewing Faculty', 'Current Status', 
    'Notice Sent Date', 'Acknowledgment Date', 'Closure Date'
]);

foreach ($cases as $c) {
    $studentName = $c['student_last'] . ', ' . $c['student_first'];
    $facultyName = $c['faculty_last'] ? ($c['faculty_last'] . ', ' . $c['faculty_first']) : 'Unassigned';
    
    $statusMap = [
        'needs_review' => 'Awaiting Review',
        'action_taken' => 'Notice Sent',
        'acknowledged' => 'Acknowledged',
        'resolved' => 'Closed'
    ];
    $statusLabel = $statusMap[$c['status']] ?? $c['status'];

    fputcsv($output, [
        $c['case_id'],
        sanitize_csv_field($c['student_number']),
        sanitize_csv_field($studentName),
        $c['trigger_risk_level'],
        $c['creation_date'] ? date('Y-m-d H:i', strtotime($c['creation_date'])) : 'N/A',
        sanitize_csv_field($facultyName),
        $statusLabel,
        $c['notice_sent_at'] ? date('Y-m-d H:i', strtotime($c['notice_sent_at'])) : 'N/A', 
        $c['acknowledged_at'] ? date('Y-m-d H:i', strtotime($c['acknowledged_at'])) : 'N/A',
        $c['resolved_at'] ? date('Y-m-d H:i', strtotime($c['resolved_at'])) : 'N/A'
    ]);
}

fclose($output);
exit;