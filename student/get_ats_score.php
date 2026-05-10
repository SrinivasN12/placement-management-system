<?php
// =============================================================
//  student/get_ats_score.php
//  AI-Powered ATS Score via Groq — compares student resume_skills
//  with the placement drive's job description.
//  Called via AJAX from resume.php
// =============================================================
ob_start(); // catch any PHP warnings/notices before JSON output

require_once '../config/auth.php';
require_once '../config/db.php';

if ($role_id != 1) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

ob_end_clean(); // discard any output so far
header('Content-Type: application/json');

$student_id = (int)($_SESSION['student_id'] ?? 0);
$drive_id   = (int)($_GET['drive_id'] ?? 0);

if (!$student_id || !$drive_id) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit();
}

// ── One-time schema migrations (safe, never crashes) ─────────
mysqli_report(MYSQLI_REPORT_OFF); // disable exception throwing for these DDL statements
try { mysqli_query($conn, "ALTER TABLE ats_score MODIFY COLUMN resume_id INT NULL DEFAULT NULL"); } catch (Exception $e) {}
try { mysqli_query($conn, "ALTER TABLE ats_score DROP FOREIGN KEY ats_score_ibfk_2"); }           catch (Exception $e) {}
try { mysqli_query($conn, "ALTER TABLE ats_score ADD COLUMN IF NOT EXISTS matched_skills TEXT DEFAULT NULL"); }  catch (Exception $e) {}
try { mysqli_query($conn, "ALTER TABLE ats_score ADD COLUMN IF NOT EXISTS missing_skills TEXT DEFAULT NULL"); }  catch (Exception $e) {}
try { mysqli_query($conn, "ALTER TABLE ats_score ADD COLUMN IF NOT EXISTS recommendation TEXT DEFAULT NULL"); }   catch (Exception $e) {}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // restore for the rest of the file

// ── Recheck flag — bypass cache when explicitly requested ─────
$recheck = isset($_GET['recheck']) && $_GET['recheck'] === '1';

// ── Check cache (same student + drive, scored today) ─────────
if (!$recheck) {
    $cache = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT * FROM ats_score
         WHERE student_id=$student_id AND drive_id=$drive_id
           AND checked_date = CURDATE()
         ORDER BY ats_id DESC LIMIT 1"
    ));
    if ($cache && (int)$cache['score'] > 0 && !empty($cache['recommendation'])) {
        echo json_encode([
            'success'        => true,
            'cached'         => true,
            'score'          => (int)$cache['score'],
            'matched_skills' => array_values(array_filter(array_map('trim', explode(',', $cache['matched_skills'] ?? '')))),
            'missing_skills' => array_values(array_filter(array_map('trim', explode(',', $cache['missing_skills'] ?? '')))),
            'recommendation' => $cache['recommendation'] ?? '',
        ]);
        exit();
    }
}

// ── Fetch student skills from DB ──────────────────────────────
$stu = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT resume_skills FROM student WHERE student_id=$student_id"
));
$studentSkills = trim($stu['resume_skills'] ?? '');

if (empty($studentSkills)) {
    echo json_encode([
        'error'   => 'no_skills',
        'message' => 'No resume skills found. Please upload and parse your resume first.'
    ]);
    exit();
}

// ── Fetch drive info ──────────────────────────────────────────
$drive = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT drive_id, job_title, company_name, description
     FROM placement_drive
     WHERE drive_id=$drive_id AND drive_status != 'Closed'"
));

if (!$drive) {
    echo json_encode(['error' => 'Drive not found or closed']);
    exit();
}

// Build a rich JD string from job title + company + description
$jdText = trim(
    $drive['job_title'] . ' at ' . $drive['company_name'] . '. ' .
    ($drive['description'] ?? '')
);
if (strlen($jdText) < 15) {
    $jdText = $drive['job_title'] . ' ' . $drive['company_name'] .
              ' MBA graduate management business analytics leadership finance marketing';
}

// ── Groq API config ───────────────────────────────────────────
// Separate API key from resume parser — avoids shared rate-limit exhaustion
$groqApiKey   = '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';
$groqEndpoint = 'https://api.groq.com/openai/v1/chat/completions';
$groqModel    = 'llama-3.3-70b-versatile';

// ── Prompt ────────────────────────────────────────────────────
$systemPrompt = 'You are an expert ATS (Applicant Tracking System) scoring engine for MBA placements. Respond with a single valid JSON object only. No markdown, no explanation, no extra text.';

$userPrompt = <<<PROMPT
STUDENT SKILLS (extracted from resume):
$studentSkills

JOB DESCRIPTION:
$jdText

Carefully analyze how well the student's skills match this specific job.
Return ONLY this JSON structure:
{
  "score": <integer 0-100>,
  "matched_skills": ["skill1", "skill2"],
  "missing_skills": ["skill3", "skill4"],
  "recommendation": "<2-3 sentences of actionable advice for this specific role>"
}

Scoring rules:
- 70-100 = Strong match (student has most required skills)
- 40-69  = Moderate match (partial skill overlap)
- 0-39   = Weak match (significant skill gaps)
- matched_skills: up to 10 skills from student list that are relevant to this JD
- missing_skills: up to 8 important skills mentioned in JD that student lacks
- recommendation: specific, practical advice on what to learn or highlight for THIS role
PROMPT;

$requestBody = json_encode([
    'model'           => $groqModel,
    'messages'        => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ],
    'temperature'     => 0.2,
    'max_tokens'      => 600,
    'response_format' => ['type' => 'json_object'],
]);

// ── Call Groq API ─────────────────────────────────────────────
$ch = curl_init($groqEndpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response  = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlError || $httpCode !== 200) {
    // Log error detail
    $errDetail = $curlError ?: ('HTTP ' . $httpCode . ': ' . substr($response, 0, 200));
    error_log("ATS Groq error: $errDetail");
    // Use keyword-based fallback
    $result = fallbackATS($studentSkills, $jdText);
} else {
    $gJson   = json_decode($response, true);
    $rawText = trim($gJson['choices'][0]['message']['content'] ?? '');

    // Strip markdown fences if Groq wraps output
    $rawText = preg_replace('/^```(?:json)?\s*/im', '', $rawText);
    $rawText = preg_replace('/\s*```\s*$/im',       '', $rawText);
    $rawText = trim($rawText);

    // Extract JSON boundaries
    $fb = strpos($rawText, '{');
    $lb = strrpos($rawText, '}');
    if ($fb !== false && $lb !== false && $lb > $fb) {
        $rawText = substr($rawText, $fb, $lb - $fb + 1);
    }
    // Remove trailing commas (common LLM mistake)
    $rawText = preg_replace('/,\s*([\}\]])/m', '$1', $rawText);

    $parsed = json_decode($rawText, true);
    if (!is_array($parsed) || !isset($parsed['score'])) {
        $result = fallbackATS($studentSkills, $jdText);
    } else {
        $result = [
            'score'          => max(0, min(100, (int)($parsed['score'] ?? 0))),
            'matched_skills' => array_values(array_filter((array)($parsed['matched_skills'] ?? []))),
            'missing_skills' => array_values(array_filter((array)($parsed['missing_skills'] ?? []))),
            'recommendation' => trim((string)($parsed['recommendation'] ?? '')),
        ];
    }
}

// ── Save to ats_score table ───────────────────────────────────
$score      = (int)$result['score'];
$matchedEsc = mysqli_real_escape_string($conn, implode(', ', $result['matched_skills']));
$missingEsc = mysqli_real_escape_string($conn, implode(', ', $result['missing_skills']));
$recEsc     = mysqli_real_escape_string($conn, $result['recommendation']);

// Delete any existing score for this student+drive today
mysqli_query($conn,
    "DELETE FROM ats_score
     WHERE student_id=$student_id AND drive_id=$drive_id AND checked_date=CURDATE()"
);

// Insert new score (resume_id is NULL — we use resume_skills, not the resume table)
$insertOk = mysqli_query($conn,
    "INSERT INTO ats_score
        (student_id, resume_id, drive_id, score, checked_date, matched_skills, missing_skills, recommendation)
     VALUES
        ($student_id, NULL, $drive_id, $score, CURDATE(), '$matchedEsc', '$missingEsc', '$recEsc')"
);

if (!$insertOk) {
    error_log("ATS insert error: " . mysqli_error($conn));
}

// ── Return result ─────────────────────────────────────────────
echo json_encode([
    'success'        => true,
    'cached'         => false,
    'score'          => $result['score'],
    'matched_skills' => $result['matched_skills'],
    'missing_skills' => $result['missing_skills'],
    'recommendation' => $result['recommendation'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit();

// ── Fallback: simple keyword overlap ─────────────────────────
function fallbackATS(string $skills, string $jd): array
{
    $skillArr = array_filter(array_map('trim', explode(',', strtolower($skills))));
    $jdLower  = strtolower($jd);
    $matched  = [];

    foreach ($skillArr as $sk) {
        if ($sk && strpos($jdLower, $sk) !== false) {
            $matched[] = ucwords($sk);
        }
    }

    $total = max(1, count($skillArr));
    $score = min(100, max(10, (int)round((count($matched) / $total) * 100)));

    return [
        'score'          => $score,
        'matched_skills' => array_slice($matched, 0, 10),
        'missing_skills' => [],
        'recommendation' => 'AI analysis unavailable. Parse your resume using the AI parser for a more accurate ATS score.',
    ];
}
