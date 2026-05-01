<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $app_id    = (int)$_POST['application_id'];
    $student_id= (int)$_POST['student_id'];
    $status    = $_POST['status'];
    $redirect  = $_POST['redirect'] ?? 'applications.php';

    $allowed = ['Applied','Selected','Rejected'];
    if (!in_array($status, $allowed)) { header("Location: applications.php"); exit(); }

    $sel_date = $status === 'Selected' ? "'".date('Y-m-d')."'" : "NULL";
    mysqli_query($conn,
        "UPDATE application SET application_status='$status', selection_date=$sel_date
         WHERE application_id=$app_id");

    // Notify student of status change
    $today = date('Y-m-d');
    $app   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT d.job_title,d.company_name FROM application a JOIN placement_drive d ON a.drive_id=d.drive_id WHERE a.application_id=$app_id"));
    $msg = "Your application for {$app['job_title']} at {$app['company_name']} has been updated to: $status.";
    mysqli_query($conn,"INSERT INTO notification (student_id,message,is_read,date) VALUES ($student_id,'".mysqli_real_escape_string($conn,$msg)."',0,'$today')");

    // Append ?updated to redirect
    $sep = strpos($redirect,'?') !== false ? '&' : '?';
    header("Location: $redirect{$sep}updated=1");
    exit();
}

// Direct page visit (GET) — show a selection management page
$apps = mysqli_query($conn,
    "SELECT a.*, s.name AS sname, d.job_title, d.company_name
     FROM application a
     JOIN student s ON a.student_id=s.student_id
     JOIN placement_drive d ON a.drive_id=d.drive_id
     ORDER BY a.applied_at DESC LIMIT 50");
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Selection Status — PMS</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px}
.back{display:inline-block;margin-bottom:18px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}
</style></head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="applications.php">← View Applications to update status</a>
<p style="color:#666;font-size:14px;margin-top:8px">Update selection status directly from the <a href="applications.php" style="color:#1b5e20;font-weight:600">Applications page</a> using the inline dropdowns.</p>
</div></body></html>
