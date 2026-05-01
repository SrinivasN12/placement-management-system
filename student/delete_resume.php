<?php
// =============================================================
//  student/delete_resume.php
//  Deletes the student's saved resume from disk and clears
//  the database columns.
//
//  POST: (no body params needed — uses session student_id)
//  Returns JSON: { success: true/false, message }
// =============================================================

require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$student_id = (int)($_SESSION['student_id'] ?? 0);

// ── Fetch current resume path ─────────────────────────────────
$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT resume_path FROM student WHERE student_id = $student_id"));

if (!$row || empty($row['resume_path'])) {
    echo json_encode(['success' => false, 'message' => 'No resume found to delete.']);
    exit();
}

// ── Delete physical file ──────────────────────────────────────
$fullPath = dirname(__DIR__) . '/' . ltrim($row['resume_path'], '/');
if (file_exists($fullPath)) {
    if (!@unlink($fullPath)) {
        echo json_encode(['success' => false, 'message' => 'Could not delete file from server.']);
        exit();
    }
}

// ── Clear DB columns on student table ────────────────────────
mysqli_query($conn,
    "UPDATE student
     SET resume_path          = NULL,
         resume_original_name = NULL,
         resume_uploaded_at   = NULL
     WHERE student_id = $student_id");

// ── Also clear file_path in resume table if present ──────────
mysqli_query($conn,
    "UPDATE resume
     SET file_path = NULL
     WHERE student_id = $student_id");

echo json_encode(['success' => true, 'message' => 'Resume deleted successfully.']);
exit();
