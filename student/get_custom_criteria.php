<?php
// =============================================================
//  student/get_custom_criteria.php
//  Returns custom criteria for a drive as JSON.
//  Called by: JS fetch() in drives.php before showing modal.
//  GET  ?drive_id=X
//  Returns: { has_criteria: bool, criteria: [...] }
// =============================================================
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$drive_id = (int)($_GET['drive_id'] ?? 0);
if (!$drive_id) {
    echo json_encode(['has_criteria' => false, 'criteria' => []]);
    exit();
}

$result = mysqli_query($conn,
    "SELECT criteria_id, criteria_label, criteria_type, criteria_value, is_mandatory
     FROM drive_custom_criteria
     WHERE drive_id = $drive_id
     ORDER BY criteria_id ASC");

$criteria = [];
while ($row = mysqli_fetch_assoc($result)) {
    $criteria[] = [
        'criteria_id'    => (int)$row['criteria_id'],
        'criteria_label' => $row['criteria_label'],
        'criteria_type'  => $row['criteria_type'],
        'criteria_value' => $row['criteria_value'],
        'is_mandatory'   => (int)$row['is_mandatory'],
    ];
}

echo json_encode([
    'has_criteria' => count($criteria) > 0,
    'criteria'     => $criteria,
]);
exit();
