<?php
// =============================================================
//  student/autofill_profile.php
//  Receives AI-parsed resume data (JSON body) and persists it
//  directly to the student row in MySQL.
//  Only overwrites a DB field when it is empty/zero UNLESS
//  the client sends force_overwrite = 1.
//
//  POST body (raw JSON):
//    { "data": { ...parsed fields... }, "force_overwrite": 0|1 }
//  Returns:
//    { "success": true, "saved": [...], "skipped": [...], "message": "..." }
// =============================================================

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

// ── Parse incoming JSON body ──────────────────────────────────
$body    = file_get_contents('php://input');
$payload = json_decode($body, true);

if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
    echo json_encode(['error' => 'Invalid payload — expected { data:{...}, force_overwrite:0|1 }.']);
    exit();
}

$data           = $payload['data'];
$forceOverwrite = !empty($payload['force_overwrite']);

// ── Fetch current student row so we know what is already set ──
$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT specialization, cgpa, tenth_percentage, twelfth_percentage,
            degree_cgpa, contact_number, country, state, city
     FROM student WHERE student_id = $student_id"));

if (!$row) {
    echo json_encode(['error' => 'Student record not found.']);
    exit();
}

// ── Helper: is a DB value "empty / zero"? ─────────────────────
function isBlank($v): bool {
    if ($v === null || $v === '') return true;
    $f = (float) $v;
    return $f === 0.0;
}

// ── Build the SET clause dynamically ─────────────────────────
$sets   = [];
$params = [];
$types  = '';
$saved  = [];
$skip   = [];

// ── specialization ────────────────────────────────────────────
$newSpec    = trim($data['specialization'] ?? '');
$validSpecs = ['Finance','Marketing','HR','Operations','IT','General'];
if ($newSpec !== '' && in_array($newSpec, $validSpecs)) {
    if ($forceOverwrite || isBlank($row['specialization'])) {
        $sets[] = 'specialization = ?'; $params[] = $newSpec; $types .= 's';
        $saved[] = 'Specialization';
    } else { $skip[] = 'Specialization (already set)'; }
}

// ── cgpa (MBA CGPA, 0–10) ─────────────────────────────────────
$newCgpa = trim($data['cgpa'] ?? '');
if ($newCgpa !== '' && is_numeric($newCgpa)) {
    $v = (float)$newCgpa;
    if ($v > 0 && $v <= 10) {
        if ($forceOverwrite || isBlank($row['cgpa'])) {
            $sets[] = 'cgpa = ?'; $params[] = $v; $types .= 'd';
            $saved[] = 'MBA CGPA';
        } else { $skip[] = 'MBA CGPA (already set)'; }
    }
}

// ── tenth_percentage ──────────────────────────────────────────
$newTenth = trim($data['tenth_percent'] ?? '');
if ($newTenth !== '' && is_numeric($newTenth)) {
    $v = (float)$newTenth;
    if ($v >= 30 && $v <= 100) {
        if ($forceOverwrite || isBlank($row['tenth_percentage'])) {
            $sets[] = 'tenth_percentage = ?'; $params[] = $v; $types .= 'd';
            $saved[] = '10th %';
        } else { $skip[] = '10th % (already set)'; }
    }
}

// ── twelfth_percentage ────────────────────────────────────────
$newTwelfth = trim($data['twelfth_percent'] ?? '');
if ($newTwelfth !== '' && is_numeric($newTwelfth)) {
    $v = (float)$newTwelfth;
    if ($v >= 30 && $v <= 100) {
        if ($forceOverwrite || isBlank($row['twelfth_percentage'])) {
            $sets[] = 'twelfth_percentage = ?'; $params[] = $v; $types .= 'd';
            $saved[] = '12th %';
        } else { $skip[] = '12th % (already set)'; }
    }
}

// ── degree_cgpa (graduation_percent from Gemini is 0-10 scale) ─
$newDeg = trim($data['graduation_percent'] ?? '');
if ($newDeg !== '' && is_numeric($newDeg)) {
    $v = (float)$newDeg;
    if ($v > 0 && $v <= 10) {
        if ($forceOverwrite || isBlank($row['degree_cgpa'])) {
            $sets[] = 'degree_cgpa = ?'; $params[] = $v; $types .= 'd';
            $saved[] = 'Degree CGPA';
        } else { $skip[] = 'Degree CGPA (already set)'; }
    }
}

// ── contact_number ────────────────────────────────────────────
$newPhone = preg_replace('/\D/', '', trim($data['phone'] ?? ''));
if (strlen($newPhone) === 10 && preg_match('/^[6-9]\d{9}$/', $newPhone)) {
    if ($forceOverwrite || isBlank($row['contact_number'])) {
        $sets[] = 'contact_number = ?'; $params[] = $newPhone; $types .= 's';
        $saved[] = 'Contact Number';
    } else { $skip[] = 'Contact Number (already set)'; }
}

// ── country ───────────────────────────────────────────────────
$newCountry = trim($data['country'] ?? '');
if ($newCountry !== '') {
    if ($forceOverwrite || isBlank($row['country'])) {
        $sets[] = 'country = ?'; $params[] = $newCountry; $types .= 's';
        $saved[] = 'Country';
    } else { $skip[] = 'Country (already set)'; }
}

// ── state ─────────────────────────────────────────────────────
$newState = trim($data['state'] ?? '');
if ($newState !== '') {
    if ($forceOverwrite || isBlank($row['state'])) {
        $sets[] = 'state = ?'; $params[] = $newState; $types .= 's';
        $saved[] = 'State';
    } else { $skip[] = 'State (already set)'; }
}

// ── city ──────────────────────────────────────────────────────
$newCity = trim($data['city'] ?? '');
if ($newCity !== '') {
    if ($forceOverwrite || isBlank($row['city'])) {
        $sets[] = 'city = ?'; $params[] = $newCity; $types .= 's';
        $saved[] = 'City';
    } else { $skip[] = 'City (already set)'; }
}



// ── Execute UPDATE only if we have something to save ──────────
if (!empty($sets)) {
    $sql      = 'UPDATE student SET ' . implode(', ', $sets) . ' WHERE student_id = ?';
    $params[] = $student_id;
    $types   .= 'i';

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$ok) {
            echo json_encode(['error' => 'DB update failed: ' . mysqli_error($conn)]);
            exit();
        }
    } else {
        echo json_encode(['error' => 'DB prepare failed: ' . mysqli_error($conn)]);
        exit();
    }
}

// ── Respond ───────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'saved'   => $saved,
    'skipped' => $skip,
    'message' => empty($saved)
        ? 'No new fields updated (all already filled, or no matching data found).'
        : count($saved) . ' field(s) saved to your profile: ' . implode(', ', $saved) . '.',
]);
exit();
