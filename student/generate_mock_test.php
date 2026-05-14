<?php
// =============================================================
//  student/generate_mock_test.php
//  Accepts POST: drive_id, difficulty
//  Returns JSON: { mock_test_id, questions[] }
//  Questions returned to client EXCLUDE correct/explanation
//  (those are revealed only after submission).
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

// ── Input validation ──────────────────────────────────────────
$drive_id   = (int)($_POST['drive_id']   ?? 0);
$difficulty = trim($_POST['difficulty']  ?? 'medium');

if (!$drive_id) {
    echo json_encode(['error' => 'Invalid drive ID.']);
    exit();
}

$allowed_difficulties = ['easy', 'medium', 'hard'];
if (!in_array($difficulty, $allowed_difficulties, true)) {
    $difficulty = 'medium';
}

// ── Fetch drive details ───────────────────────────────────────
$esc_did  = (int)$drive_id;
$drive_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT drive_id, job_title, company_name, description
     FROM placement_drive
     WHERE drive_id = $esc_did AND drive_status != 'Closed'"
));

if (!$drive_row) {
    echo json_encode(['error' => 'Drive not found or is closed.']);
    exit();
}

$company_name  = $drive_row['company_name'];
$job_role      = $drive_row['job_title'];
$job_desc      = trim($drive_row['description'] ?? '');
$required_skills = $job_desc; // derive skills context from description

// Fallback if description is empty
if (strlen($job_desc) < 10) {
    $job_desc        = "$job_role position at $company_name. MBA graduates preferred.";
    $required_skills = "Management, Business Analytics, Leadership, Communication, Finance, Marketing";
}

// ── Check cache ───────────────────────────────────────────────
$esc_diff = mysqli_real_escape_string($conn, $difficulty);
$cached   = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT mock_test_id, questions
     FROM mock_tests
     WHERE drive_id = $esc_did AND difficulty = '$esc_diff'
     ORDER BY created_at DESC LIMIT 1"
));

if ($cached) {
    $all_questions = json_decode($cached['questions'], true);
    // Strip answers before returning to client
    $client_questions = array_map(function($q, $i) {
        return [
            'index'    => $i,
            'question' => $q['question'],
            'options'  => $q['options'],
            'category' => $q['category'] ?? '',
        ];
    }, $all_questions, array_keys($all_questions));

    echo json_encode([
        'success'      => true,
        'cached'       => true,
        'mock_test_id' => (int)$cached['mock_test_id'],
        'questions'    => array_values($client_questions),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// ── Build Groq prompt ─────────────────────────────────────────
$groq_api_key  = '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';
$groq_endpoint = 'https://api.groq.com/openai/v1/chat/completions';
$groq_model    = 'llama-3.3-70b-versatile';

$system_prompt = 'You are an expert placement test designer for MBA students in India. Respond ONLY with a valid JSON array. No markdown, no explanation, no preamble.';

$user_prompt = <<<PROMPT
Generate exactly 20 MCQ questions for an MBA placement mock test.
DRIVE DETAILS:
- Company: {$company_name}
- Role: {$job_role}
- Required Skills: {$required_skills}
- Job Description: {$job_desc}
- Difficulty: {$difficulty}

QUESTION DISTRIBUTION (4 questions each):
1. Domain/Technical Knowledge - relevant to {$job_role}
2. Aptitude & Quantitative - Data Interpretation, logical reasoning
3. Situational Judgment - workplace scenarios for {$job_role}
4. Company/Industry Awareness - about {$company_name} and its industry
5. Communication & Case-based - MBA problem solving scenarios

RULES:
- Questions must be specific to {$job_role} at {$company_name}
- Options must be plausible, no obviously wrong distractors
- Explanations must be 1-2 sentences, educational and insightful
- Return ONLY a JSON array of exactly 20 objects, no other text

Format (return ONLY this, no wrapper object):
[
  {
    "question": "string",
    "options": ["A. text", "B. text", "C. text", "D. text"],
    "correct": 0,
    "category": "string",
    "explanation": "string"
  }
]

Where "correct" is the 0-based index of the correct option (0=A, 1=B, 2=C, 3=D).
PROMPT;

$request_body = json_encode([
    'model'       => $groq_model,
    'messages'    => [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user',   'content' => $user_prompt],
    ],
    'temperature' => 0.65,
    'max_tokens'  => 4000,
]);

// ── Call Groq API ─────────────────────────────────────────────
$ch = curl_init($groq_endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $request_body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groq_api_key,
    ],
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response   = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curl_error) {
    error_log("MockTest Groq cURL error: $curl_error");
    echo json_encode(['error' => 'AI service is temporarily unavailable. Please try again in a moment.']);
    exit();
}

if ($http_code === 429) {
    echo json_encode(['error' => 'AI service is busy (rate limit). Please wait 30 seconds and try again.']);
    exit();
}

if ($http_code !== 200) {
    error_log("MockTest Groq HTTP $http_code: " . substr($response, 0, 300));
    echo json_encode(['error' => "AI service returned an unexpected error (HTTP $http_code). Please try again."]);
    exit();
}

// ── Parse Groq response ───────────────────────────────────────
$g_json   = json_decode($response, true);
$raw_text = trim($g_json['choices'][0]['message']['content'] ?? '');

// Strip markdown fences if present
$raw_text = preg_replace('/^```(?:json)?\s*/im', '', $raw_text);
$raw_text = preg_replace('/\s*```\s*$/im',       '', $raw_text);
$raw_text = trim($raw_text);

// Extract JSON array boundaries
$fa = strpos($raw_text, '[');
$la = strrpos($raw_text, ']');
if ($fa !== false && $la !== false && $la > $fa) {
    $raw_text = substr($raw_text, $fa, $la - $fa + 1);
}

// Clean common LLM JSON issues
$raw_text = preg_replace('/,\s*([\}\]])/m', '$1', $raw_text); // trailing commas

$questions_full = json_decode($raw_text, true);

if (!is_array($questions_full) || count($questions_full) < 10) {
    error_log("MockTest Groq bad JSON: " . substr($raw_text, 0, 500));
    echo json_encode(['error' => 'AI generated invalid questions. Please try again.']);
    exit();
}

// Normalize: ensure exactly 20 questions, sanitize fields
$questions_full = array_slice($questions_full, 0, 20);
foreach ($questions_full as &$q) {
    $q['question']    = htmlspecialchars_decode(trim($q['question']    ?? 'Question unavailable'));
    $q['options']     = array_slice((array)($q['options']     ?? []), 0, 4);
    $q['correct']     = max(0, min(3, (int)($q['correct']     ?? 0)));
    $q['category']    = htmlspecialchars_decode(trim($q['category']    ?? 'General'));
    $q['explanation'] = htmlspecialchars_decode(trim($q['explanation'] ?? ''));
}
unset($q);

// ── Save to mock_tests ────────────────────────────────────────
$json_to_save = mysqli_real_escape_string($conn, json_encode($questions_full, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Use INSERT ... ON DUPLICATE KEY UPDATE to handle race conditions
$insert_ok = mysqli_query($conn,
    "INSERT INTO mock_tests (drive_id, questions, difficulty)
     VALUES ($esc_did, '$json_to_save', '$esc_diff')
     ON DUPLICATE KEY UPDATE
         questions  = VALUES(questions),
         created_at = CURRENT_TIMESTAMP"
);

$mock_test_id = (int)mysqli_insert_id($conn);

// If ON DUPLICATE KEY triggered (insert_id = 0), fetch the existing id
if (!$mock_test_id) {
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT mock_test_id FROM mock_tests WHERE drive_id=$esc_did AND difficulty='$esc_diff'"
    ));
    $mock_test_id = (int)($row['mock_test_id'] ?? 0);
}

if (!$mock_test_id) {
    error_log("MockTest insert error: " . mysqli_error($conn));
    echo json_encode(['error' => 'Failed to save test. Please try again.']);
    exit();
}

// ── Return questions (without answers) to client ──────────────
$client_questions = array_map(function($q, $i) {
    return [
        'index'    => $i,
        'question' => $q['question'],
        'options'  => $q['options'],
        'category' => $q['category'],
    ];
}, $questions_full, array_keys($questions_full));

echo json_encode([
    'success'      => true,
    'cached'       => false,
    'mock_test_id' => $mock_test_id,
    'questions'    => array_values($client_questions),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
