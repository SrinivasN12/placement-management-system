<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) { header("Location: ../index.php"); exit(); }

$student_id = (int)($_SESSION['student_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $app_id = (int)($_POST['application_id'] ?? 0);

    // Security: confirm this application belongs to this student and is still 'Applied'
    $app = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT a.application_id, a.application_status, d.job_title, d.company_name
         FROM application a
         JOIN placement_drive d ON a.drive_id = d.drive_id
         WHERE a.application_id=$app_id AND a.student_id=$student_id"));

    if (!$app) {
        header("Location: applications.php?err=notfound"); exit();
    }

    if ($app['application_status'] !== 'Applied') {
        header("Location: applications.php?err=cannotwithdraw"); exit();
    }

    // Delete the application
    mysqli_query($conn, "DELETE FROM application WHERE application_id=$app_id AND student_id=$student_id");

    // Log a notification for the student
    $msg = "You withdrew your application for {$app['job_title']} at {$app['company_name']}.";
    $msg_esc = mysqli_real_escape_string($conn, $msg);
    mysqli_query($conn, "INSERT INTO notification (student_id, message, is_read, date) VALUES ($student_id, '$msg_esc', 0, CURDATE())");

    header("Location: applications.php?withdrawn=1"); exit();
}

header("Location: applications.php"); exit();
?>
