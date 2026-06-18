<?php
// officer/selection.php — Handles application status updates by the placement officer.
require_once '../config/auth.php';
require_once '../config/db.php';

// Only placement officers (role_id = 2) may access this endpoint
if ($role_id != 2) {
    header("Location: ../index.php");
    exit();
}

// Must be a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: applications.php");
    exit();
}

// CSRF check
validate_csrf();

$application_id = (int)($_POST['application_id'] ?? 0);
$new_status     = trim($_POST['status'] ?? '');
$redirect       = trim($_POST['redirect'] ?? 'applications.php');

// Whitelist allowed status values
$allowed = ['Applied', 'Selected', 'Rejected'];
if (!in_array($new_status, $allowed, true) || $application_id === 0) {
    header("Location: applications.php?err=invalid");
    exit();
}

// Sanitise the redirect URL — only allow relative paths within this directory
// Strip any leading slashes / protocol to prevent open-redirect
$redirect = ltrim($redirect, '/');
if (empty($redirect) || strpos($redirect, '://') !== false) {
    $redirect = 'applications.php';
}

// Verify the application actually exists before updating
$check = mysqli_prepare($conn,
    "SELECT application_id FROM application WHERE application_id = ?");
mysqli_stmt_bind_param($check, 'i', $application_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    mysqli_stmt_close($check);
    header("Location: applications.php?err=notfound");
    exit();
}
mysqli_stmt_close($check);

// Set selection_date when marking as Selected, clear it otherwise
$sel_date = ($new_status === 'Selected') ? date('Y-m-d') : null;

$stmt = mysqli_prepare($conn,
    "UPDATE application SET application_status = ?, selection_date = ? WHERE application_id = ?");
mysqli_stmt_bind_param($stmt, 'ssi', $new_status, $sel_date, $application_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// ── Notify the student of the outcome ────────────────────────
$appInfo = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT a.student_id, d.job_title, d.company_name
     FROM application a
     JOIN placement_drive d ON a.drive_id = d.drive_id
     WHERE a.application_id = $application_id"));
if ($appInfo) {
    $emoji   = ($new_status === 'Selected') ? '🎉' : '❌';
    $outcome = ($new_status === 'Selected')
        ? 'Congratulations! You have been SELECTED'
        : 'Unfortunately, you were not selected (Rejected)';
    $notifMsg = "{$emoji} {$outcome} for {$appInfo['job_title']} at {$appInfo['company_name']}.";
    $notifEsc = mysqli_real_escape_string($conn, $notifMsg);
    $sid      = (int)$appInfo['student_id'];
    mysqli_query($conn,
        "INSERT INTO notification (student_id, message, is_read, date)
         VALUES ($sid, '$notifEsc', 0, CURDATE())");
}

// Redirect back to wherever the officer came from (preserving filters)
header("Location: " . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . "updated=1");
exit();
