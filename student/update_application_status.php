<?php
// student/update_application_status.php
// Allows a student to self-report their application outcome (Selected / Rejected).
// Only the student who owns the application can update it.
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }

validate_csrf();

$student_id    = (int)($_SESSION['student_id'] ?? 0);
$application_id = (int)($_POST['application_id'] ?? 0);
$new_status    = trim($_POST['status'] ?? '');

// Only allow these two values
$allowed = ['Selected', 'Rejected'];
if (!in_array($new_status, $allowed, true) || $application_id === 0) {
    header("Location: applications.php?err=invalid");
    exit();
}

// Verify the application belongs to this student
$check = mysqli_prepare($conn,
    "SELECT application_id, application_status FROM application
     WHERE application_id = ? AND student_id = ?");
mysqli_stmt_bind_param($check, 'ii', $application_id, $student_id);
mysqli_stmt_execute($check);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
mysqli_stmt_close($check);

if (!$row) {
    header("Location: applications.php?err=notfound");
    exit();
}

// Update status
$sel_date = ($new_status === 'Selected') ? date('Y-m-d') : null;

$stmt = mysqli_prepare($conn,
    "UPDATE application SET application_status=?, selection_date=? WHERE application_id=?");
mysqli_stmt_bind_param($stmt, 'ssi', $new_status, $sel_date, $application_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: applications.php?updated=1");
exit();
