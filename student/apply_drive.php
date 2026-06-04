<?php
// =============================================================
//  student/apply_drive.php
//  Handles full eligibility check + application save.
//  Called via fetch() POST from drives.php JS.
//  POST: drive_id, csrf_token, custom_answers (JSON string)
//  Returns JSON: {success, application_id?, message?, failures?}
// =============================================================
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'failures' => ['Unauthorized access.']]);
    exit();
}

header('Content-Type: application/json');

// ── CSRF ────────────────────────────────────────────────────
$submitted = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'failures' => ['Invalid CSRF token.']]);
    exit();
}

$student_id = (int)($_SESSION['student_id'] ?? 0);
$drive_id   = (int)($_POST['drive_id'] ?? 0);

if (!$student_id || !$drive_id) {
    echo json_encode(['success' => false, 'failures' => ['Invalid request parameters.']]);
    exit();
}

// ── Load drive ───────────────────────────────────────────────
$drv = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM placement_drive WHERE drive_id = $drive_id"));

if (!$drv) {
    echo json_encode(['success' => false, 'failures' => ['Drive not found.']]);
    exit();
}
if ($drv['drive_status'] === 'Closed') {
    echo json_encode(['success' => false, 'failures' => ['This drive is no longer accepting applications.']]);
    exit();
}
if ($drv['last_date'] < date('Y-m-d')) {
    echo json_encode(['success' => false, 'failures' => ['The last date to apply has passed.']]);
    exit();
}

// ── Check duplicate application ──────────────────────────────
$existing = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT application_id FROM application
     WHERE student_id=$student_id AND drive_id=$drive_id"));
if ($existing) {
    echo json_encode(['success' => false, 'failures' => ['You have already applied to this drive.']]);
    exit();
}

// ── Load student ─────────────────────────────────────────────
$stu = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM student WHERE student_id=$student_id"));

// ── Preplacement gate (server-side enforcement) ──────────────
// Must have an actual record in preplacement_evaluation with status 'Ready'
$pe = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT overall_status FROM preplacement_evaluation WHERE student_id=$student_id"));
if (!$pe || $pe['overall_status'] !== 'Ready') {
    echo json_encode([
        'success'  => false,
        'failures' => ['⚠ You must complete your Pre-Placement Evaluation and be marked as "Ready" before applying for any placement drive. Please contact your Placement Officer.']
    ]);
    exit();
}


// ── Standard eligibility check ───────────────────────────────
$failures = [];

if ((float)$stu['tenth_percentage']   < (float)$drv['min_tenth_pct'])
    $failures[] = "10th %: Your {$stu['tenth_percentage']}% is below required {$drv['min_tenth_pct']}%";

if ((float)$stu['twelfth_percentage'] < (float)$drv['min_twelfth_pct'])
    $failures[] = "12th %: Your {$stu['twelfth_percentage']}% is below required {$drv['min_twelfth_pct']}%";

if ((float)$stu['degree_cgpa']        < (float)$drv['min_degree_cgpa'])
    $failures[] = "Degree CGPA: Your {$stu['degree_cgpa']} is below required {$drv['min_degree_cgpa']}";

if ((float)$stu['cgpa']              < (float)$drv['min_cgpa'])
    $failures[] = "MBA CGPA: Your {$stu['cgpa']} is below required {$drv['min_cgpa']}";

if ((int)$stu['backlogs']            > (int)$drv['max_backlogs'])
    $failures[] = "Backlogs: You have {$stu['backlogs']} active backlogs, limit is {$drv['max_backlogs']}";

// ── Parse custom answers from POST ───────────────────────────
$raw     = $_POST['custom_answers'] ?? '[]';
$answers = json_decode($raw, true);
if (!is_array($answers)) $answers = [];

// ── Custom criteria eligibility check ────────────────────────
$criteriaEligibility = []; // criteria_id => [response, is_eligible]

foreach ($answers as $a) {
    $cid      = (int)($a['criteria_id'] ?? 0);
    $response = trim($a['response'] ?? '');
    if (!$cid) continue;

    $crit = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM drive_custom_criteria
         WHERE criteria_id=$cid AND drive_id=$drive_id"));
    if (!$crit) continue;

    $is_elig = 1;

    if ((int)$crit['is_mandatory'] === 1) {
        switch ($crit['criteria_type']) {
            case 'yes_no':
                if (strtolower($response) !== strtolower($crit['criteria_value'])) {
                    $failures[] = "{$crit['criteria_label']}: Required '{$crit['criteria_value']}', your answer: '{$response}'";
                    $is_elig = 0;
                }
                break;

            case 'number':
                if (!is_numeric($response) || (float)$response < (float)$crit['criteria_value']) {
                    $failures[] = "{$crit['criteria_label']}: Minimum required {$crit['criteria_value']}, your answer: '{$response}'";
                    $is_elig = 0;
                }
                break;

            case 'text_match':
                // Pass if response contains the required keyword, or student says "yes"
                $hasKeyword = stripos($response, $crit['criteria_value']) !== false;
                $saidYes    = strtolower($response) === 'yes';
                if (!$hasKeyword && !$saidYes) {
                    $failures[] = "{$crit['criteria_label']}: Required knowledge of '{$crit['criteria_value']}', your answer: '{$response}'";
                    $is_elig = 0;
                }
                break;

            case 'checkbox':
                if (strtolower($response) !== 'yes') {
                    $failures[] = "{$crit['criteria_label']}: This requirement is mandatory. You must confirm.";
                    $is_elig = 0;
                }
                break;
        }
    }

    $criteriaEligibility[$cid] = ['response' => $response, 'is_eligible' => $is_elig];
}

// ── Return failures if any ───────────────────────────────────
if (!empty($failures)) {
    echo json_encode(['success' => false, 'failures' => $failures]);
    exit();
}

// ── All checks passed → save application ─────────────────────
mysqli_query($conn,
    "INSERT INTO application (student_id, drive_id, application_status, applied_at)
     VALUES ($student_id, $drive_id, 'Applied', NOW())");
$app_id = (int)mysqli_insert_id($conn);

// ── Save custom criteria responses ───────────────────────────
foreach ($criteriaEligibility as $cid => $info) {
    $resp_esc = mysqli_real_escape_string($conn, $info['response']);
    $is_elig  = (int)$info['is_eligible'];
    mysqli_query($conn,
        "INSERT INTO student_custom_criteria_response
         (student_id, drive_id, criteria_id, student_response, is_eligible)
         VALUES ($student_id, $drive_id, $cid, '$resp_esc', $is_elig)");
}

// ── Notification ─────────────────────────────────────────────
$msg = "You have successfully applied for {$drv['job_title']} at {$drv['company_name']}. Application ID: $app_id";
$msg_esc = mysqli_real_escape_string($conn, $msg);
mysqli_query($conn,
    "INSERT INTO notification (student_id, message, is_read, date)
     VALUES ($student_id, '$msg_esc', 0, CURDATE())");

echo json_encode([
    'success'        => true,
    'application_id' => $app_id,
    'message'        => "Applied Successfully! Application ID: $app_id",
]);
exit();
