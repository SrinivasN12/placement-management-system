<?php
// =============================================================
//  student/smart_parse_resume.php
//  SMART RESUME PARSER — Groq first, local fallback second.
//  Fixed: htmlspecialchars corruption, JSON mode, model upgrade.
// =============================================================

require_once '../config/auth.php';
require_once '../config/db.php';   // needed to save skills for ATS
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$debugLog = [];

// ── Groq config (free tier — sign up at console.groq.com) ────
$groqApiKey = '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';
$groqEndpoint = 'https://api.groq.com/openai/v1/chat/completions';
$groqModel = 'llama-3.3-70b-versatile'; // free, fast, accurate

// ── Validate upload ───────────────────────────────────────────
if (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error occurred.']);
    exit();
}

$file = $_FILES['resume_file'];
$maxBytes = 2 * 1024 * 1024;

$finfo = finfo_open(FILEINFO_MIME_TYPE);
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

$pdfContent = file_get_contents($file['tmp_name']);
if ($pdfContent === false) {
    echo json_encode(['error' => 'Could not read uploaded file.']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
//  STEP 1 — Extract PDF text locally (saves  token quota)
// ═══════════════════════════════════════════════════════════════
$pdfTextForGemini = '';

// Try smalot first (most accurate)
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseContent($pdfContent);
        $pdfTextForGemini = $pdf->getText();
        $debugLog[] = 'smalot: extracted ' . strlen($pdfTextForGemini) . ' chars';
    } catch (\Exception $e) {
        $debugLog[] = 'smalot error: ' . $e->getMessage();
    }
}

// Fallback: basic stream extraction
if (empty(trim($pdfTextForGemini))) {
    $pdfTextForGemini = extractTextFromPdfBasic($pdfContent);
    $debugLog[] = 'basic extraction: ' . strlen($pdfTextForGemini) . ' chars';
}

// Trim to 4000 chars max — more than enough for any resume, keeps token count low
$pdfTextForGemini = trim(substr($pdfTextForGemini, 0, 4000));

// ═══════════════════════════════════════════════════════════════
//  STEP 2 — Gemini API (text-only, NOT PDF binary)
// ═══════════════════════════════════════════════════════════════
$geminiRawResponse = '';
$geminiCleaned = '';
$geminiError = '';
$parsedData = null;
$method = 'gemini';

$prompt = <<<'PROMPT'
Extract resume data and return ONLY a valid JSON object with these exact keys. No markdown, no explanation.

{"name":"","email":"","phone":"","cgpa":"","specialization":"","tenth_percent":"","twelfth_percent":"","graduation_percent":"","skills":"","work_experience":"","certifications":"","languages":"","city":"","state":"","country":""}

RULES:
- name: Full name from top of resume, Title Case.
- email: First valid email.
- phone: Indian mobile, exactly 10 digits, strip +91/0. Start with 6-9. Empty if none.
- cgpa: MBA/PG CGPA on 0-10 scale as decimal string (e.g. "8.75"). Not percentage. Empty if none.
- specialization: ONE of: Finance|Marketing|HR|Operations|IT|General. Map by keywords.
- tenth_percent: Class X/SSC percentage as decimal string. Convert CGPA×9.5 if needed.
- twelfth_percent: Class XII/HSC percentage as decimal string.
- graduation_percent: Bachelor's CGPA×10 or percentage as decimal string.
- skills: Comma-separated, max 30, deduplicated.
- work_experience: 2-3 sentence summary of all jobs/internships with role, company, duration.
- certifications: Comma-separated courses/certs or empty.
- languages: Comma-separated languages or empty.
- city: City of residence extracted from address/contact section. Empty if not found.
- state: Indian state extracted from address/contact section. Empty if not found.
- country: Country of residence (usually "India"). Empty if not found.

All values must be strings. Missing fields use "". JSON must be valid.

RESUME TEXT:
PROMPT;

$fullPrompt = $prompt . "\n" . $pdfTextForGemini;

$requestBody = json_encode([
    'model' => $groqModel,
    'messages' => [
        ['role' => 'system', 'content' => 'You are a resume data extraction engine. Always respond with a single valid JSON object only. No markdown, no explanation.'],
        ['role' => 'user', 'content' => $fullPrompt],
    ],
    'temperature' => 0.0,
    'max_tokens' => 1024,
    'response_format' => ['type' => 'json_object'],
]);

// ── Call Groq API ─────────────────────────────────────────────
$ch = curl_init($groqEndpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groqApiKey,
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$debugLog[] = "Groq HTTP: $httpCode" . ($curlError ? ", cURL: $curlError" : '');

if ($response !== false && !$curlError && $httpCode === 200) {
    $geminiRawResponse = $response;
    $gJson = json_decode($response, true);

    // Groq uses OpenAI-compatible format: choices[0].message.content
    $rawText = trim($gJson['choices'][0]['message']['content'] ?? '');
    $debugLog[] = 'Groq raw (first 300): ' . substr($rawText, 0, 300);

    // Strip markdown fences if Groq wraps in ```json
    $cleaned = preg_replace('/^```(?:json)?\s*/im', '', $rawText);
    $cleaned = preg_replace('/\s*```\s*$/im', '', $cleaned);
    $cleaned = trim($cleaned);

    // Extract JSON object boundaries
    $fb = strpos($cleaned, '{');
    $lb = strrpos($cleaned, '}');
    if ($fb !== false && $lb !== false && $lb > $fb) {
        $cleaned = substr($cleaned, $fb, $lb - $fb + 1);
    }
    // Remove trailing commas
    $cleaned = preg_replace('/,\s*([\}\]])/m', '$1', $cleaned);
    $geminiCleaned = $cleaned;

    $extracted = json_decode($cleaned, true);
    if (is_array($extracted)) {
        $parsedData = sanitizeData($extracted);
        $method = 'gemini'; // keep method name for badge compatibility
        $debugLog[] = 'Groq SUCCESS — ' . implode(', ', array_keys(array_filter($parsedData)));
    } else {
        $geminiError = 'JSON parse failed: ' . json_last_error_msg();
        $debugLog[] = $geminiError . ' | cleaned: ' . $cleaned;
    }
} else {
    if ($httpCode === 429) {
        $geminiError = 'Groq quota exceeded. Please wait a moment and try again.';
    } elseif ($httpCode === 401) {
        $geminiError = 'Groq API key invalid or missing. Check your key in smart_parse_resume.php.';
    } elseif ($httpCode === 0 && $curlError) {
        $geminiError = 'Network error: ' . $curlError;
    } else {
        $geminiError = 'Groq API failed. HTTP:' . $httpCode . ($curlError ? ' cURL:' . $curlError : '');
    }
    $debugLog[] = $geminiError;
    if ($debugMode && $response)
        $debugLog[] = 'Error body: ' . substr($response, 0, 400);
}

// ═══════════════════════════════════════════════════════════════
//  STEP 3 — Local fallback (reuses text already extracted above)
// ═══════════════════════════════════════════════════════════════
if ($parsedData === null) {
    $method = 'local';
    $debugLog[] = 'Gemini unavailable (' . $geminiError . '). Using local regex parser.';

    // Reuse text extracted in STEP 1 (no need to parse PDF again)
    $textForLocal = $pdfTextForGemini;

    if (!empty(trim($textForLocal))) {
        $parsedData = localParseResume($textForLocal, $debugLog);
        $debugLog[] = 'Local parse done. Fields: ' . count(array_filter($parsedData));
    } else {
        $debugLog[] = 'No text extracted — returning empty';
        $parsedData = getEmptyData();
    }
}

// ═══════════════════════════════════════════════════════════════
//  STEP 3 — Save skills to DB for ATS scoring (silent, non-blocking)
// ═══════════════════════════════════════════════════════════════
$skillsToSave = trim($parsedData['skills'] ?? '');
if ($skillsToSave && isset($conn)) {
    // Auto-create column if not yet present
    @mysqli_query($conn, "ALTER TABLE student ADD COLUMN IF NOT EXISTS resume_skills TEXT DEFAULT NULL AFTER city");
    $sid = (int) $_SESSION['student_id'];
    $stmt = mysqli_prepare($conn, "UPDATE student SET resume_skills=? WHERE student_id=?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $skillsToSave, $sid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $debugLog[] = "Skills saved to DB: $skillsToSave";
    }
}

// ═══════════════════════════════════════════════════════════════
//  STEP 4 — Return JSON
// ═══════════════════════════════════════════════════════════════
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
if ($debugMode) {
    echo json_encode([
        'success' => true,
        'method' => $method,
        'data' => $parsedData,
        'gemini_raw_response' => substr($geminiRawResponse, 0, 2000),
        'gemini_cleaned' => $geminiCleaned,
        'errors' => $debugLog,
    ], JSON_PRETTY_PRINT | $flags);
} else {
    echo json_encode(['success' => true, 'method' => $method, 'data' => $parsedData], $flags);
}
exit();

// ═══════════════════════════════════════════════════════════════
/**
 * Sanitize Gemini data.
 * IMPORTANT: Do NOT use htmlspecialchars — these values go into JS form
 * fields via JSON, not into HTML. HTML-encoding breaks the values.
 */
function sanitizeData(array $raw): array
{
    $result = [];

    $stringFields = [
        'name',
        'email',
        'phone',
        'specialization',
        'skills',
        'work_experience',
        'certifications',
        'languages',
        'city',
        'state',
        'country',
    ];
    foreach ($stringFields as $f) {
        $val = isset($raw[$f]) ? (string) $raw[$f] : '';
        $val = trim($val, " \t\n\r\0\x0B\"'");
        $result[$f] = $val;
    }

    $numFields = ['cgpa', 'tenth_percent', 'twelfth_percent', 'graduation_percent'];
    foreach ($numFields as $f) {
        $raw_val = trim((string) ($raw[$f] ?? ''));
        if (preg_match('/(\d+\.?\d*)/', $raw_val, $m)) {
            $num = (float) $m[1];
            if ($f === 'cgpa' && ($num < 0 || $num > 10))
                $num = 0;
            if (
                in_array($f, ['tenth_percent', 'twelfth_percent', 'graduation_percent'])
                && ($num < 0 || $num > 100)
            )
                $num = 0;
            $result[$f] = $num > 0 ? (string) $num : '';
        } else {
            $result[$f] = '';
        }
    }

    $result['specialization'] = normalizeSpecialization($result['specialization']);
    $result['phone'] = normalizePhone($result['phone']);

    return $result;
}

function localParseResume(string $text, array &$log): array
{
    $data = getEmptyData();
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $text)),
        fn($l) => strlen($l) > 0
    ));

    // ── Name ───────────────────────────────────────────────────────
    // Names always appear in the first ~15 lines. Scan only there to avoid
    // picking up phrases like "MBA Finance" or "Skills Summary" deeper in text.
    $nameBlacklist = [
        'resume',
        'cv',
        'curriculum',
        'vitae',
        'profile',
        'summary',
        'objective',
        'career',
        'education',
        'experience',
        'skills',
        'certifications',
        'languages',
        'contact',
        'references',
        'declaration',
        'projects',
        'achievements',
        'interests',
        'personal',
        'details',
        'information',
        'address',
        'mobile',
        'phone',
        'email',
        'professional',
        'about',
        'introduction',
        'bachelor',
        'master',
        'mba',
        'pgdm',
        'bcom',
        'bsc',
        'btech',
        'bca',
        'bba',
        'engineering',
        'commerce',
        'science',
        'arts',
        'management',
        'university',
        'college',
        'institute',
        'school',
        'finance',
        'marketing',
        'operations',
        'human resource',
        'technology',
        'first class',
        'distinction',
        'division',
        'pass',
        'grade',
        'gpa',
        'cgpa',
        'india',
        'karnataka',
        'maharashtra',
        'gujarat',
        'delhi',
        'mumbai',
        'bangalore',
    ];
    $firstLines = array_slice($lines, 0, 15);
    foreach ($firstLines as $line) {
        $line = trim($line);
        if (strlen($line) < 3 || strlen($line) > 50)
            continue;
        // Only letters, spaces, dots, hyphens
        if (!preg_match('/^[A-Za-z][A-Za-z\s\.\-]+$/', $line))
            continue;
        $wordCount = str_word_count($line);
        // Names are 2-4 words
        if ($wordCount < 2 || $wordCount > 4)
            continue;
        // Reject sentences (ends with period)
        if (substr($line, -1) === '.')
            continue;
        // Each word must start uppercase (Title Case) OR line is ALL CAPS
        $isAllCaps = (strtoupper($line) === $line);
        $isTitleCase = preg_match('/^([A-Z][a-z]*\.?\s*)+$/', $line);
        if (!$isAllCaps && !$isTitleCase)
            continue;
        // Check blacklist
        $ll = strtolower($line);
        $bad = false;
        foreach ($nameBlacklist as $bw) {
            if (strpos($ll, $bw) !== false) {
                $bad = true;
                break;
            }
        }
        if ($bad)
            continue;
        $data['name'] = $isAllCaps ? ucwords(strtolower($line)) : $line;
        $log[] = "Name: {$data['name']}";
        break;
    }

    // ── Email ──────────────────────────────────────────────────────
    if (preg_match('/[\w.\-+]+@[\w.\-]+\.\w{2,}/', $text, $m)) {
        $data['email'] = strtolower($m[0]);
    }

    // ── Phone ──────────────────────────────────────────────────────
    if (preg_match('/(?:\+91[\s\-]?|0)?([6-9]\d{9})/', $text, $m)) {
        $data['phone'] = normalizePhone($m[0]);
    }

    // ── City / State / Country ─────────────────────────────────────
    $indianStates = [
        'Andhra Pradesh',
        'Arunachal Pradesh',
        'Assam',
        'Bihar',
        'Chhattisgarh',
        'Goa',
        'Gujarat',
        'Haryana',
        'Himachal Pradesh',
        'Jharkhand',
        'Karnataka',
        'Kerala',
        'Madhya Pradesh',
        'Maharashtra',
        'Manipur',
        'Meghalaya',
        'Mizoram',
        'Nagaland',
        'Odisha',
        'Punjab',
        'Rajasthan',
        'Sikkim',
        'Tamil Nadu',
        'Telangana',
        'Tripura',
        'Uttar Pradesh',
        'Uttarakhand',
        'West Bengal',
        'Delhi',
        'Jammu and Kashmir',
        'Ladakh',
        'Chandigarh',
        'Puducherry',
    ];
    foreach ($indianStates as $st) {
        if (stripos($text, $st) !== false) {
            $data['state'] = $st;
            break;
        }
    }
    if (preg_match('/(?:city|location|address)\s*[:\-,]\s*([A-Z][a-z]{2,}(?:\s[A-Z][a-z]{2,})?)/i', $text, $m)) {
        $data['city'] = trim($m[1]);
    }
    if (stripos($text, 'india') !== false || !empty($data['state'])) {
        $data['country'] = 'India';
    }

    // ── MBA CGPA ───────────────────────────────────────────────────
    $cgpaPats = [
        '/(?:mba|pgdm|pg)\b[^\n]{0,80}?(\d\.\d{1,2})\s*(?:\/\s*10)?/i',
        '/(?:cgpa|c\.g\.p\.a)\s*[:=\-]?\s*(\d\.\d{1,2})/i',
        '/(\d\.\d{1,2})\s*(?:cgpa|c\.g\.p\.a)/i',
        '/(\d\.\d{1,2})\s*\/\s*10(?:\s|,|$)/i',
        '/gpa\s*[:=]?\s*(\d\.\d{1,2})/i',
    ];
    foreach ($cgpaPats as $pat) {
        if (preg_match($pat, $text, $m)) {
            $v = (float) $m[1];
            if ($v > 0 && $v <= 10) {
                $data['cgpa'] = number_format($v, 2);
                break;
            }
        }
    }

    // ── 10th % ─────────────────────────────────────────────────────
    $tenthPats = [
        '/(?:10th|x(?:th)?|ssc|matricul)[^\n]{0,60}?(\d{2,3}(?:\.\d{1,2})?)\s*%/i',
        '/(?:10th|x(?:th)?|ssc|matricul)[^\n]{0,60}?(\d{2,3}(?:\.\d{1,2})?)\s*(?:marks|percent)/i',
        '/class\s*x\b[^\n]{0,80}?(\d{2,3}(?:\.\d{1,2})?)/i',
        '/(\d{2,3}(?:\.\d{1,2})?)\s*%[^\n]{0,40}(?:10th|ssc)/i',
    ];
    foreach ($tenthPats as $pat) {
        if (preg_match($pat, $text, $m)) {
            $v = (float) $m[1];
            if ($v >= 30 && $v <= 100) {
                $data['tenth_percent'] = number_format($v, 2);
                break;
            }
        }
    }

    // ── 12th % ─────────────────────────────────────────────────────
    $twelfthPats = [
        '/(?:12th|xii(?:th)?|hsc|intermediate|higher secondary|pu[c]?)\b[^\n]{0,60}?(\d{2,3}(?:\.\d{1,2})?)\s*%/i',
        '/(?:12th|xii(?:th)?|hsc|intermediate)\b[^\n]{0,60}?(\d{2,3}(?:\.\d{1,2})?)\s*(?:marks|percent)/i',
        '/class\s*xii\b[^\n]{0,80}?(\d{2,3}(?:\.\d{1,2})?)/i',
        '/(\d{2,3}(?:\.\d{1,2})?)\s*%[^\n]{0,40}(?:12th|hsc|xii)/i',
    ];
    foreach ($twelfthPats as $pat) {
        if (preg_match($pat, $text, $m)) {
            $v = (float) $m[1];
            if ($v >= 30 && $v <= 100) {
                $data['twelfth_percent'] = number_format($v, 2);
                break;
            }
        }
    }

    // ── Graduation % ───────────────────────────────────────────────
    // ── Degree CGPA (0–10 scale — maps to degree_cgpa form field) ───────────────
    // NOTE: The DB field `degree_cgpa` stores on 0-10 scale, NOT percentage.
    // If resume shows a %, divide by 10 to get CGPA. If already CGPA, use as-is.
    $gradPats = [
        // Pattern: CGPA value explicitly stated
        '/(?:b\.?com|bba|b\.?sc|b\.?tech|b\.?e\.?|bca|b\.?a\.?|b\.?arch)\b[^\n]{0,60}?(\d\.\d{1,2})\s*(?:cgpa|\/\s*10)/i',
        '/(?:bachelor|graduation|undergraduate|ug\b)\b[^\n]{0,60}?(\d\.\d{1,2})\s*(?:cgpa|\/\s*10)/i',
        '/(?:degree|graduation)\s*cgpa\s*[:=]?\s*(\d\.\d{1,2})/i',
        // Pattern: percentage stated — convert to CGPA by dividing by 10
        '/(?:b\.?com|bba|b\.?sc|b\.?tech|b\.?e\.?|bca|b\.?a\.?|b\.?arch)\b[^\n]{0,80}?(\d{2,3}(?:\.\d{1,2})?)\s*%/i',
        '/(?:bachelor|graduation|undergraduate|ug\b)\b[^\n]{0,60}?(\d{2,3}(?:\.\d{1,2})?)\s*%/i',
    ];
    foreach ($gradPats as $i => $pat) {
        if (preg_match($pat, $text, $m)) {
            $v = (float) $m[1];
            if ($i >= 3) {
                // These patterns found a percentage — convert to CGPA (% / 10)
                if ($v >= 30 && $v <= 100)
                    $v = round($v / 10, 2);
                else
                    continue;
            }
            if ($v > 0 && $v <= 10) {
                $data['graduation_percent'] = number_format($v, 2);
                break;
            }
        }
    }

    // ── Specialization ─────────────────────────────────────────────
    $specMap = [
        'Finance' => [
            'finance',
            'financial',
            'accounting',
            'accounts',
            'banking',
            'investment',
            'taxation',
            'insurance',
            'fintech',
            'treasury'
        ],
        'Marketing' => [
            'marketing',
            'market research',
            'digital marketing',
            'brand',
            'sales',
            'retail',
            'advertising',
            'seo',
            'sem',
            'social media'
        ],
        'HR' => [
            'human resource',
            'hr management',
            'hrd',
            'people management',
            'talent acquisition',
            'recruitment',
            'hrm'
        ],
        'Operations' => [
            'operations',
            'supply chain',
            'logistics',
            'scm',
            'procurement',
            'project management',
            'lean',
            'six sigma',
            'production'
        ],
        'IT' => [
            'information technology',
            'it management',
            'mis',
            'systems',
            'business analytics',
            'data analytics',
            'data science',
            'erp'
        ],
        'General' => ['general management', 'strategy', 'entrepreneurship'],
    ];
    $textL = strtolower($text);
    foreach ($specMap as $spec => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($textL, $kw) !== false) {
                $data['specialization'] = $spec;
                break 2;
            }
        }
    }

    // ── Skills ─────────────────────────────────────────────────────
    $skillList = [
        'MS Excel',
        'MS Word',
        'MS PowerPoint',
        'MS Office',
        'Tally',
        'SAP',
        'ERP',
        'CRM',
        'Python',
        'R Programming',
        'SQL',
        'MySQL',
        'SPSS',
        'SAS',
        'MATLAB',
        'Tableau',
        'Power BI',
        'Google Analytics',
        'Java',
        'PHP',
        'HTML',
        'CSS',
        'JavaScript',
        'AutoCAD',
        'Canva',
        'Figma',
        'Photoshop',
        'Financial Analysis',
        'Financial Modelling',
        'Business Development',
        'Business Analytics',
        'Market Research',
        'Digital Marketing',
        'SEO',
        'SEM',
        'Supply Chain Management',
        'Inventory Management',
        'Project Management',
        'Data Analysis',
        'Data Visualization',
        'Statistical Analysis',
        'Leadership',
        'Communication',
        'Teamwork',
        'Team Management',
        'Problem Solving',
        'Critical Thinking',
        'Time Management',
        'Presentation',
        'Negotiation',
        'Decision Making',
        'Adaptability',
        'Creativity',
    ];
    $found = [];
    foreach ($skillList as $skill) {
        if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text))
            $found[] = $skill;
    }
    $data['skills'] = implode(', ', array_unique($found));

    // ── Certifications ─────────────────────────────────────────────
    $certPats = [
        '/(certified\s+in|certification\s+in|certificate\s+(?:in|of))[^\n]{5,100}/i',
        '/(?:coursera|udemy|nptel|linkedin learning|edx|swayam|google|microsoft|aws)[^\n]{5,80}/i',
        '/(?:diploma\s+in|workshop\s+on|training\s+in|course\s+(?:in|on))[^\n]{5,80}/i',
    ];
    $certs = [];
    foreach ($certPats as $pat) {
        preg_match_all($pat, $text, $cm);
        foreach ($cm[0] as $c) {
            $c = trim(preg_replace('/\s+/', ' ', $c));
            if (strlen($c) > 8 && strlen($c) < 120)
                $certs[] = $c;
        }
    }
    if ($certs)
        $data['certifications'] = implode(', ', array_unique(array_slice($certs, 0, 5)));

    // ── Languages ──────────────────────────────────────────────────
    $langs = [
        'English',
        'Hindi',
        'Marathi',
        'Tamil',
        'Telugu',
        'Kannada',
        'Bengali',
        'Gujarati',
        'Malayalam',
        'Punjabi',
        'Urdu',
        'Odia',
        'French',
        'German'
    ];
    $fl = [];
    $lp = stripos($text, 'language');
    $lchunk = $lp !== false ? substr($text, $lp, 300) : $text;
    foreach ($langs as $l) {
        if (stripos($lchunk, $l) !== false)
            $fl[] = $l;
    }
    if (empty($fl))
        foreach ($langs as $l) {
            if (stripos($text, $l) !== false)
                $fl[] = $l;
        }
    $data['languages'] = implode(', ', array_unique($fl));

    // ── Work Experience ─────────────────────────────────────────────
    $expStart = -1;
    foreach (['experience', 'internship', 'intern', 'employment'] as $kw) {
        $pos = stripos($text, $kw);
        if ($pos !== false && ($expStart === -1 || $pos < $expStart))
            $expStart = $pos;
    }
    if ($expStart !== -1) {
        $block = preg_replace('/\s+/', ' ', substr($text, $expStart, 600));
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($block), -1, PREG_SPLIT_NO_EMPTY);
        $summary = implode(' ', array_slice($sentences, 0, 3));
        if (strlen($summary) > 20)
            $data['work_experience'] = $summary;
    }

    return $data;
}

function getEmptyData(): array
{
    return [
        'name' => '',
        'email' => '',
        'phone' => '',
        'cgpa' => '',
        'specialization' => '',
        'tenth_percent' => '',
        'twelfth_percent' => '',
        'graduation_percent' => '',
        'skills' => '',
        'work_experience' => '',
        'certifications' => '',
        'languages' => '',
        'city' => '',
        'state' => '',
        'country' => '',
    ];
}

function normalizeSpecialization(string $val): string
{
    if (!$val)
        return '';
    $l = strtolower(trim($val));
    if (strpos($l, 'financ') !== false)
        return 'Finance';
    if (strpos($l, 'market') !== false)
        return 'Marketing';
    if (strpos($l, 'hr') !== false || strpos($l, 'human') !== false)
        return 'HR';
    if (strpos($l, 'operat') !== false || strpos($l, 'supply') !== false)
        return 'Operations';
    if (strpos($l, 'info') !== false || strpos($l, 'tech') !== false)
        return 'IT';
    return 'General';
}

function normalizePhone(string $val): string
{
    if (!$val)
        return '';
    $d = preg_replace('/\D/', '', $val);
    if (strlen($d) === 12 && substr($d, 0, 2) === '91')
        $d = substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0')
        $d = substr($d, 1);
    return strlen($d) >= 10 ? substr($d, -10) : '';
}

function extractTextFromPdfBasic(string $pdfContent): string
{
    $text = '';
    preg_match_all('/stream(.*?)endstream/s', $pdfContent, $streams);
    foreach ($streams[1] as $stream) {
        $dec = @gzuncompress(ltrim($stream));
        $text .= ($dec !== false ? $dec : $stream) . "\n";
    }
    $readable = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $o = ord($text[$i]);
        if ($o >= 32 && $o <= 126)
            $readable .= $text[$i];
        elseif ($o === 10 || $o === 13)
            $readable .= "\n";
    }
    $readable = preg_replace('/[<>]\s*[0-9a-fA-F]+\s*[<>]/', ' ', $readable);
    $readable = preg_replace('/\/\w+/', ' ', $readable);
    $readable = preg_replace('/\s+/', ' ', $readable);
    return trim($readable);
}
