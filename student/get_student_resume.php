<?php
// =============================================================
//  student/get_student_resume.php
//  Returns the currently saved resume info for the logged-in
//  student. Called by JS on profile page load.
//
//  Returns JSON:
//  { found: true/false, path, original_name, uploaded_at }
// =============================================================

require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['found' => false]);
    exit();
}

header('Content-Type: application/json');

$student_id = (int)($_SESSION['student_id'] ?? 0);

// ── Fetch from student table (the 3 extra columns) ────────────
$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT resume_path, resume_original_name, resume_uploaded_at
     FROM student
     WHERE student_id = $student_id"));

if ($row && !empty($row['resume_path'])) {
    // Verify the file actually exists on disk
    $fullPath = dirname(__DIR__) . '/' . ltrim($row['resume_path'], '/');
    if (!file_exists($fullPath)) {
        // File was deleted from disk — clear DB record
        mysqli_query($conn,
            "UPDATE student
             SET resume_path=NULL, resume_original_name=NULL, resume_uploaded_at=NULL
             WHERE student_id=$student_id");
        echo json_encode(['found' => false]);
        exit();
    }

    echo json_encode([
        'found'         => true,
        'path'          => $row['resume_path'],
        'original_name' => $row['resume_original_name'] ?? 'resume.pdf',
        'uploaded_at'   => $row['resume_uploaded_at']
                           ? date('d M Y, h:i A', strtotime($row['resume_uploaded_at']))
                           : 'Unknown',
    ]);
} else {
    echo json_encode(['found' => false]);
}
exit();
