<?php
// =============================================================
//  student/save_resume_file.php
//  Saves the uploaded PDF to /uploads/resumes/ and updates
//  the student's resume record in the database.
//
//  Called by: JS after Gemini auto-fill is complete and the
//             student clicks the main "Save Changes" button,
//             OR directly when the student just wants to save
//             the file without auto-fill.
//
//  POST params: resume_file (file)
//  Returns    : JSON { success, path, original_name, uploaded_at }
// =============================================================

// ── Auth guard ───────────────────────────────────────────────
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$student_id = (int)($_SESSION['student_id'] ?? 0);
if ($student_id === 0) {
    echo json_encode(['error' => 'Student session not found.']);
    exit();
}

// ── Validate file upload ──────────────────────────────────────
if (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error.']);
    exit();
}

$file     = $_FILES['resume_file'];
$maxBytes = 2 * 1024 * 1024; // 2 MB

// Verify MIME type — PDF only
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    echo json_encode(['error' => 'Only PDF files are accepted.']);
    exit();
}

if ($file['size'] > $maxBytes) {
    echo json_encode(['error' => 'File exceeds 2 MB limit.']);
    exit();
}

// ── Define upload directory (server root relative) ────────────
// We store under the project root so it's accessible via URL.
$uploadDir = dirname(__DIR__) . '/uploads/resumes/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['error' => 'Could not create upload directory.']);
        exit();
    }
}

// ── Check for and delete old resume file ──────────────────────
// Fetch the student's current resume record (text-based table)
$oldRow = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT resume_id, file_path FROM resume
     WHERE student_id = $student_id
     ORDER BY uploaded_date DESC LIMIT 1"));

if ($oldRow && !empty($oldRow['file_path'])) {
    $oldFilePath = dirname(__DIR__) . '/' . ltrim($oldRow['file_path'], '/');
    if (file_exists($oldFilePath)) {
        @unlink($oldFilePath); // Delete old physical file
    }
}

// ── Build unique filename ─────────────────────────────────────
$timestamp      = time();
$safeOrigName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
$newFilename    = 'resume_' . $student_id . '_' . $timestamp . '.pdf';
$destPath       = $uploadDir . $newFilename;
$relPath        = 'uploads/resumes/' . $newFilename; // relative to project root

// ── Move uploaded file ────────────────────────────────────────
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['error' => 'Failed to save file. Check server write permissions.']);
    exit();
}

// ── Update database ───────────────────────────────────────────
// Strategy: upsert into the resume table (update file_path).
// If a resume record exists for this student, update it.
// If not, insert a new record.
$uploadedAt = date('Y-m-d');
$safeOrig   = mysqli_real_escape_string($conn, $safeOrigName);
$safePath   = mysqli_real_escape_string($conn, $relPath);

if ($oldRow && $oldRow['resume_id']) {
    // Update existing record with new file path
    $resumeId = (int)$oldRow['resume_id'];
    mysqli_query($conn,
        "UPDATE resume
         SET file_path     = '$safePath',
             uploaded_date = '$uploadedAt'
         WHERE resume_id   = $resumeId");
} else {
    // Insert new resume record (resume_text can be empty — PDF is the source)
    mysqli_query($conn,
        "INSERT INTO resume (student_id, resume_text, file_path, uploaded_date)
         VALUES ($student_id, '', '$safePath', '$uploadedAt')");
}

// ── Also update the 3 extra columns on student table ─────────
// These columns are added by the SQL ALTER in the setup guide.
$safeOrig2 = mysqli_real_escape_string($conn, $safeOrigName);
mysqli_query($conn,
    "UPDATE student
     SET resume_path          = '$safePath',
         resume_original_name = '$safeOrig2',
         resume_uploaded_at   = NOW()
     WHERE student_id = $student_id");

// ── Return success ────────────────────────────────────────────
echo json_encode([
    'success'       => true,
    'path'          => $relPath,
    'original_name' => $safeOrigName,
    'uploaded_at'   => date('d M Y, h:i A'),
]);
exit();
