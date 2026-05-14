<?php
// =============================================================
//  student/submit_mock_test.php
//  Accepts: raw JSON body { mock_test_id, answers, time_taken }
//  Returns: JSON { score, total, percentage, time_taken, results[] }
// =============================================================
ob_start();

require_once '../config/auth.php';
require_once '../config/db.php';

// ── Auth guard ────────────────────────────────────────────────
if ($role_id != 1) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

ob_end_clean();
header('Content-Type: application/json');

$student_id = (int)($_SESSION['student_id'] ?? 0);
if (!$student_id) {
    echo json_encode(['error' => 'Session error. Please log in again.']);
    exit();
}

// ── Read raw JSON body ────────────────────────────────────────
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    echo json_encode(['error' => 'Invalid request format.']);
    exit();
}

$mock_test_id = (int)($payload['mock_test_id'] ?? 0);
$answers      = $payload['answers']    ?? [];
$time_taken   = (int)($payload['time_taken'] ?? 0);

if (!$mock_test_id || !is_array($answers)) {
    echo json_encode(['error' => 'Missing required fields.']);
    exit();
}

// Clamp time_taken: 0 ≤ time ≤ 600 (20 × 30s)
$time_taken = max(0, min(600, $time_taken));

// ── Check for duplicate submission ───────────────────────────
$esc_mid = (int)$mock_test_id;
$esc_sid = (int)$student_id;

$existing = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT attempt_id, score, total, time_taken
     FROM mock_test_attempts
     WHERE mock_test_id = $esc_mid AND student_id = $esc_sid
     LIMIT 1"
));

if ($existing) {
    // Already submitted — return cached result
    echo json_encode([
        'success'    => true,
        'duplicate'  => true,
        'message'    => 'You have already submitted this test.',
        'score'      => (int)$existing['score'],
        'total'      => (int)$existing['total'],
        'percentage' => $existing['total'] > 0
                        ? round(($existing['score'] / $existing['total']) * 100, 1)
                        : 0,
        'time_taken' => (int)$existing['time_taken'],
    ]);
    exit();
}

// ── Fetch questions from DB ───────────────────────────────────
$test_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT mock_test_id, questions FROM mock_tests WHERE mock_test_id = $esc_mid LIMIT 1"
));

if (!$test_row) {
    echo json_encode(['error' => 'Mock test not found.']);
    exit();
}

$questions = json_decode($test_row['questions'], true);
if (!is_array($questions) || empty($questions)) {
    echo json_encode(['error' => 'Test data is corrupt. Please start a new test.']);
    exit();
}

// ── Grade answers ─────────────────────────────────────────────
$score   = 0;
$total   = count($questions);
$results = [];

foreach ($questions as $i => $q) {
    $idx           = (string)$i;
    $student_ans   = isset($answers[$idx]) ? (int)$answers[$idx] : -1;
    $correct_ans   = (int)($q['correct'] ?? 0);
    $is_correct    = ($student_ans === $correct_ans);

    if ($is_correct) $score++;

    $results[] = [
        'index'          => $i,
        'question'       => $q['question'],
        'options'        => $q['options'],
        'your_answer'    => $student_ans,
        'correct_answer' => $correct_ans,
        'is_correct'     => $is_correct,
        'category'       => $q['category']    ?? '',
        'explanation'    => $q['explanation']  ?? '',
    ];
}

$percentage = $total > 0 ? round(($score / $total) * 100, 1) : 0;

// ── Save attempt ──────────────────────────────────────────────
$answers_json = mysqli_real_escape_string($conn, json_encode($answers));

$insert_ok = mysqli_query($conn,
    "INSERT INTO mock_test_attempts
        (mock_test_id, student_id, answers, score, total, time_taken)
     VALUES
        ($esc_mid, $esc_sid, '$answers_json', $score, $total, $time_taken)"
);

if (!$insert_ok) {
    // If insert fails due to duplicate key (race condition), still return result
    $dup_err = mysqli_errno($conn) === 1062;
    if (!$dup_err) {
        error_log("MockTest attempt insert error: " . mysqli_error($conn));
    }
}

// ── Return result ─────────────────────────────────────────────
echo json_encode([
    'success'    => true,
    'duplicate'  => false,
    'score'      => $score,
    'total'      => $total,
    'percentage' => $percentage,
    'time_taken' => $time_taken,
    'results'    => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
