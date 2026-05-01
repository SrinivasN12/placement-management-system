<?php
// =============================================================
//  officer/delete_student.php
//  Placement Officer only — delete a student and all their data.
//  Cascades: preplacement_evaluation → ats_score →
//            eligibility_result → application → notification →
//            resume → student → user
// =============================================================
require_once '../config/auth.php';
require_once '../config/db.php';

// ── Guard: officer only ───────────────────────────────────────
if ($role_id != 2) {
    header("Location: ../index.php");
    exit();
}

// ── Only POST requests ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: students.php");
    exit();
}

validate_csrf();

$student_id = (int) ($_POST['student_id'] ?? 0);
if ($student_id <= 0) {
    header("Location: students.php?error=invalid");
    exit();
}

// ── Fetch student name + user_id before deletion (for confirmation message) ──
$row = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT s.name, s.user_id FROM student s WHERE s.student_id = $student_id")
);
if (!$row) {
    header("Location: students.php?error=notfound");
    exit();
}
$student_name = $row['name'];
$user_id = (int) $row['user_id'];

// ── Cascade delete using transactions ────────────────────────
mysqli_begin_transaction($conn);

try {
    // 1. Pre-placement evaluation
    $s = mysqli_prepare($conn, "DELETE FROM preplacement_evaluation WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 2. ATS scores
    $s = mysqli_prepare($conn, "DELETE FROM ats_score WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 3. Eligibility results
    $s = mysqli_prepare($conn, "DELETE FROM eligibility_result WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 4. Applications
    $s = mysqli_prepare($conn, "DELETE FROM application WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 5. Notifications
    $s = mysqli_prepare($conn, "DELETE FROM notification WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 6. Resumes
    $s = mysqli_prepare($conn, "DELETE FROM resume WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 7. Student record
    $s = mysqli_prepare($conn, "DELETE FROM student WHERE student_id = ?");
    mysqli_stmt_bind_param($s, "i", $student_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    // 8. User account
    $s = mysqli_prepare($conn, "DELETE FROM user WHERE user_id = ?");
    mysqli_stmt_bind_param($s, "i", $user_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_close($s);

    mysqli_commit($conn);

    $safe_name = urlencode($student_name);
    header("Location: students.php?deleted=$safe_name");
    exit();

} catch (\Throwable $e) {
    mysqli_rollback($conn);
    header("Location: students.php?error=dberror");
    exit();
}
