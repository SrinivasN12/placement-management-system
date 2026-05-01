<?php
require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 2) { header("Location: ../index.php"); exit(); }
validate_csrf();

function getGrade($marks) {
    if ($marks >= 80) return 'A';
    if ($marks >= 60) return 'B';
    if ($marks >= 40) return 'C';
    return 'D';
}

$student_id   = (int)$_POST['student_id'];
$student_name = $_POST['student_name'] ?? 'Student';
$aptitude     = (int)$_POST['aptitude'];
$gd           = (int)$_POST['gd'];
$english      = (int)$_POST['english'];

// Clamp values 0–100
$aptitude = max(0, min(100, $aptitude));
$gd       = max(0, min(100, $gd));
$english  = max(0, min(100, $english));

$apt_grade = getGrade($aptitude);
$gd_grade  = getGrade($gd);
$eng_grade = getGrade($english);
$overall   = ($aptitude >= 50 && $gd >= 50 && $english >= 50) ? 'Ready' : 'Not Ready';

// Upsert into preplacement_evaluation
$query = "INSERT INTO preplacement_evaluation
          (student_id, aptitude_marks, aptitude_grade, gd_marks, gd_grade, english_marks, english_grade, overall_status)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
          aptitude_marks=VALUES(aptitude_marks), aptitude_grade=VALUES(aptitude_grade),
          gd_marks=VALUES(gd_marks),             gd_grade=VALUES(gd_grade),
          english_marks=VALUES(english_marks),   english_grade=VALUES(english_grade),
          overall_status=VALUES(overall_status)";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "iisisiss",
    $student_id,
    $aptitude, $apt_grade,
    $gd, $gd_grade,
    $english, $eng_grade,
    $overall
);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Also sync overall_status back to student table
mysqli_query($conn, "UPDATE student SET preplacement_status='$overall' WHERE student_id=$student_id");

header("Location: preplacement.php?saved=" . urlencode($student_name));
exit();
?>
