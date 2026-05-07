<?php
// =============================================================
//  student/smart_parse_resume.php
//  SMART RESUME PARSER — Gemini first, local fallback second.
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
$debugLog  = [];

// ── Gemini config ─────────────────────────────────────────────
$geminiApiKey   = 'AIzaSyBUrNQ_GJwmq4usNQDPDGqYiRRV0qTeEm4';
// gemini-2.0-flash-lite: lighter model with SEPARATE quota pool — supports PDF + JSON mode
// Switch back to gemini-2.0-flash once quota resets or billing is enabled
$geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/'
    . 'models/gemini-2.0-flash-lite:generateContent?key=' . $geminiApiKey;

// ── Validate upload ───────────────────────────────────────────
if (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error occurred.']);
    exit();
}

$file     = $_FILES['resume_file'];
$maxBytes = 2 * 1024 * 1024;

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

$pdfContent = file_get_contents($file['tmp_name']);
if ($pdfContent === false) {
    echo json_encode(['error' => 'Could not read uploaded file.']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
//  STEP 1 — Gemini API
// ═══════════════════════════════════════════════════════════════
$geminiRawResponse = '';
$geminiCleaned     = '';
$geminiError       = '';
$parsedData        = null;
$method            = 'gemini';
$pdfBase64         = base64_encode($pdfContent);

$prompt = <<<'PROMPT'
You are an expert resume data extraction engine for an MBA Placement Management System used by Indian business schools.
Your ONLY task is to read the attached PDF resume and return a single, valid JSON object.

TASK: Extract exactly the following 13 fields from the resume:

{
  "name":               "<Candidate's full legal name — first non-address, non-company line at top of resume>",
  "email":              "<Primary email address>",
  "phone":              "<Indian mobile number — exactly 10 digits, strip +91, country code, spaces, hyphens>",
  "linkedin":           "<Full LinkedIn profile URL starting with https:// — or empty string if absent>",
  "cgpa":               "<MBA / PG CGPA on a 10-point scale as a decimal string like '8.75' — or empty string>",
  "specialization":     "<Exactly one value from this list: Finance | Marketing | HR | Operations | IT | General>",
  "tenth_percent":      "<10th standard / SSC / CBSE Class X percentage as a decimal string like '85.40' — or empty string>",
  "twelfth_percent":    "<12th standard / HSC / CBSE Class XII percentage as a decimal string — or empty string>",
  "graduation_percent": "<Bachelor's degree CGPA or percentage as a decimal string (convert CGPA×10 if on 10-pt scale) — or empty string>",
  "skills":             "<Comma-separated technical and soft skills found anywhere in the resume>",
  "work_experience":    "<2–3 sentence factual summary of all internships and jobs: roles, companies, durations>",
  "certifications":     "<Comma-separated list of certifications, courses, MOOCs — or empty string>",
  "languages":          "<Comma-separated languages the candidate knows — or empty string>"
}

FIELD-BY-FIELD EXTRACTION RULES:

name:
  - Use the candidate's full name as it appears at the very top of the resume.
  - Skip company names, college names, section headers (Education, Skills, etc.).
  - If the name appears in ALL CAPS, convert to Title Case.

email:
  - Extract the first valid email address (contains @ and a domain).

phone:
  - Find any Indian mobile number. Strip leading +91, 91, or 0.
  - Return exactly 10 digits. Valid Indian mobiles start with 6, 7, 8, or 9.
  - If no valid number found, return empty string.

linkedin:
  - Look for "linkedin.com/in/..." anywhere in the resume.
  - Prepend "https://www." if missing. Return full URL or empty string.

cgpa:
  - Look for MBA / PGDM / M.B.A. CGPA on a 10-point scale.
  - Accept patterns: "CGPA: 8.5", "8.5/10", "8.50 out of 10".
  - Do NOT return percentage (like 85%) for this field.
  - Valid range: 0.0 – 10.0. Return as decimal string or empty string.

specialization:
  - Map the candidate's MBA specialization to exactly one of:
    Finance    → Finance, Accounting, Banking, Investment, Taxation, Insurance
    Marketing  → Marketing, Sales, Brand Management, Digital Marketing, Retail
    HR         → Human Resources, HR, People Management, Talent Acquisition
    Operations → Operations, Supply Chain, Logistics, Project Management, SCM
    IT         → Information Technology, Systems, MIS, Business Analytics, Data Science, IT Management
    General    → General Management, Strategy, Entrepreneurship, or if unclear
  - Return exactly: Finance | Marketing | HR | Operations | IT | General

tenth_percent:
  - Look for 10th / Class X / SSC / Matriculation percentage.
  - If expressed as CGPA (e.g. "9.2 CGPA"), convert: multiply by 9.5 (CBSE standard).
  - Return as decimal string like "85.40" or empty string.

twelfth_percent:
  - Look for 12th / Class XII / HSC / Intermediate / PUC percentage.
  - Same CGPA conversion rule as above.
  - Return as decimal string or empty string.

graduation_percent:
  - Look for Bachelor's degree (B.Com, BBA, B.Tech, B.E., B.Sc, BCA, BA, B.Arch, etc.) result.
  - If CGPA on 10-pt scale (e.g. 7.8/10): multiply by 10 to convert to percentage equivalent.
  - If already a percentage: return as-is.
  - Return as decimal string or empty string.

skills:
  - Collect ALL skills from Skills, Technical Skills, Core Competencies, and Key Skills sections.
  - Also pick up skills mentioned in project and work experience descriptions.
  - Return as a single comma-separated string. Deduplicate. Limit to 30 skills max.

work_experience:
  - Summarize ALL paid work experience AND internships in 2–3 concise sentences.
  - Include: role title, company name, duration (month/year), and key responsibility.
  - If no experience/internship found, return empty string.

certifications:
  - List certifications, online courses (Coursera, NPTEL, Udemy, LinkedIn Learning, etc.), workshops.
  - Return comma-separated or empty string.

languages:
  - List languages the candidate can speak/read/write.
  - Common Indian languages: English, Hindi, Marathi, Tamil, Telugu, Kannada, Bengali, Gujarati, Malayalam, Punjabi, Urdu.

ABSOLUTE OUTPUT RULES:
1. Output ONLY a single JSON object — no markdown, no code fences, no explanation, no preamble, no trailing text.
2. Every key must be present in the output even if value is empty string "".
3. All values MUST be strings (not numbers, not null, not arrays).
4. Numeric values (cgpa, tenth_percent, twelfth_percent, graduation_percent) must be decimal strings like "8.50" or empty string "" — never 0 or null.
5. Do not invent data that is not in the resume. If a field cannot be found, use "".
6. phone must be exactly 10 digits or empty string. Never include country code or spaces.
7. The JSON must be valid — no trailing commas, no single quotes, properly escaped strings.
PROMPT;

$requestBody = json_encode([
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => 'application/pdf', 'data' => $pdfBase64]],
            ['text'        => $prompt],
        ],
    ]],
    'generationConfig' => [
        'temperature'      => 0.0,   // fully deterministic for structured extraction
        'maxOutputTokens'  => 2048,  // room for rich skills + work_experience
        'responseMimeType' => 'application/json',
    ],
]);

// ── Call Gemini with retry on 429 / 503 ──────────────────────
$maxAttempts = 3;
$response    = false;
$curlError   = '';
$httpCode    = 0;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    $ch = curl_init($geminiEndpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $requestBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER         => true,  // include headers to read Retry-After
    ]);
    $rawFull   = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $response = ($rawFull !== false) ? substr($rawFull, $hdrSize) : false;

    $debugLog[] = "Gemini attempt $attempt — HTTP: $httpCode" . ($curlError ? ", cURL: $curlError" : '');

    if ($curlError || $response === false) break; // network failure
    if ($httpCode === 200) break;                 // success

    if (($httpCode === 429 || $httpCode === 503) && $attempt < $maxAttempts) {
        // Parse Retry-After header if present, else use exponential backoff
        $retryAfter = $attempt * 5;  // 5s, 10s
        $rawHeaders = ($rawFull !== false) ? substr($rawFull, 0, $hdrSize) : '';
        if (preg_match('/Retry-After:\s*(\d+)/i', $rawHeaders, $m)) {
            $retryAfter = (int) $m[1];
        }
        $debugLog[] = "Rate-limited. Waiting {$retryAfter}s before retry...";
        sleep($retryAfter);
        continue;
    }

    break; // any other error — don't retry
}

$debugLog[] = "Gemini final — HTTP: $httpCode, attempts: $attempt";

if ($response !== false && !$curlError && $httpCode === 200) {
    $geminiRawResponse = $response;
    $gJson = json_decode($response, true);

    if (isset($gJson['candidates'][0]['content']['parts'][0]['text'])) {
        $rawText        = trim($gJson['candidates'][0]['content']['parts'][0]['text']);
        $debugLog[]     = "Gemini raw text (first 300): " . substr($rawText, 0, 300);

        // Strip markdown fences if any
        $cleaned = preg_replace('/^```(?:json)?\s*/im', '', $rawText);
        $cleaned = preg_replace('/\s*```\s*$/im', '', $cleaned);
        $cleaned = trim($cleaned);

        // Extract just the JSON object
        $fb = strpos($cleaned, '{');
        $lb = strrpos($cleaned, '}');
        if ($fb !== false && $lb !== false && $lb > $fb) {
            $cleaned = substr($cleaned, $fb, $lb - $fb + 1);
        }

        // Remove trailing commas before } or ]
        $cleaned = preg_replace('/,\s*([\}\]])/m', '$1', $cleaned);

        $geminiCleaned = $cleaned;
        $debugLog[]    = "Gemini cleaned (first 300): " . substr($cleaned, 0, 300);

        $extracted = json_decode($cleaned, true);
        if (is_array($extracted)) {
            $parsedData = sanitizeData($extracted);
            $method     = 'gemini';
            $debugLog[] = "Gemini: SUCCESS — " . implode(', ', array_keys(array_filter($parsedData)));
        } else {
            $geminiError = 'JSON parse failed: ' . json_last_error_msg();
            $debugLog[]  = $geminiError . ' | cleaned: ' . $cleaned;
        }
    } else {
        $geminiError = 'Gemini response missing candidates structure.';
        $debugLog[]  = $geminiError;
        if ($debugMode) $debugLog[] = 'Raw body: ' . substr($response, 0, 800);
    }
} else {
    if ($httpCode === 429) {
        $geminiError = 'AI quota temporarily exceeded (too many requests). Please wait 30 seconds and try again.';
    } elseif ($httpCode === 404) {
        $geminiError = 'Gemini model not found (HTTP 404). Please contact support.';
    } elseif ($httpCode === 0 && $curlError) {
        $geminiError = 'Network error: ' . $curlError;
    } else {
        $geminiError = "Gemini API failed. HTTP:$httpCode" . ($curlError ? " cURL:$curlError" : '');
    }
    $debugLog[] = $geminiError;
    if ($debugMode && $response) $debugLog[] = 'Error body: ' . substr($response, 0, 400);
}

// ═══════════════════════════════════════════════════════════════
//  STEP 2 — Local fallback (active when Gemini quota/API fails)
// ═══════════════════════════════════════════════════════════════
$pdfTextExtracted = '';

if ($parsedData === null) {
    $method     = 'local';
    $debugLog[] = 'Gemini unavailable (' . $geminiError . '). Using local parser.';

    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        try {
            $parser           = new \Smalot\PdfParser\Parser();
            $pdf              = $parser->parseContent($pdfContent);
            $pdfTextExtracted = $pdf->getText();
            $debugLog[]       = 'smalot: extracted ' . strlen($pdfTextExtracted) . ' chars';
        } catch (\Exception $e) {
            $debugLog[] = 'smalot error: ' . $e->getMessage();
        }
    }

    if (empty(trim($pdfTextExtracted))) {
        $pdfTextExtracted = extractTextFromPdfBasic($pdfContent);
        $debugLog[]       = 'Basic extraction: ' . strlen($pdfTextExtracted) . ' chars';
    }

    if (!empty(trim($pdfTextExtracted))) {
        $parsedData = localParseResume($pdfTextExtracted, $debugLog);
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
    $sid  = (int)$_SESSION['student_id'];
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
        'success'             => true,
        'method'              => $method,
        'data'                => $parsedData,
        'gemini_raw_response' => substr($geminiRawResponse, 0, 2000),
        'gemini_cleaned'      => $geminiCleaned,
        'errors'              => $debugLog,
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

    $stringFields = ['name', 'email', 'phone', 'linkedin', 'specialization',
                     'skills', 'work_experience', 'certifications', 'languages'];
    foreach ($stringFields as $f) {
        $val        = isset($raw[$f]) ? (string) $raw[$f] : '';
        $val        = trim($val, " \t\n\r\0\x0B\"'");
        $result[$f] = $val;
    }

    $numFields = ['cgpa', 'tenth_percent', 'twelfth_percent', 'graduation_percent'];
    foreach ($numFields as $f) {
        $raw_val = trim((string) ($raw[$f] ?? ''));
        if (preg_match('/(\d+\.?\d*)/', $raw_val, $m)) {
            $num = (float) $m[1];
            if ($f === 'cgpa' && ($num < 0 || $num > 10)) $num = 0;
            if (in_array($f, ['tenth_percent', 'twelfth_percent', 'graduation_percent'])
                && ($num < 0 || $num > 100)) $num = 0;
            $result[$f] = $num > 0 ? (string) $num : '';
        } else {
            $result[$f] = '';
        }
    }

    $result['specialization'] = normalizeSpecialization($result['specialization']);
    $result['phone']          = normalizePhone($result['phone']);

    return $result;
}

function localParseResume(string $text, array &$log): array
{
    $data  = getEmptyData();
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $text)),
        fn($l) => strlen($l) > 0
    ));

    // ── Name ───────────────────────────────────────────────────────
    // Names always appear in the first ~15 lines. Scan only there to avoid
    // picking up phrases like "MBA Finance" or "Skills Summary" deeper in text.
    $nameBlacklist = [
        'resume','cv','curriculum','vitae','profile','summary','objective',
        'career','education','experience','skills','certifications','languages',
        'contact','references','declaration','projects','achievements','interests',
        'personal','details','information','address','mobile','phone','email',
        'professional','about','introduction','bachelor','master','mba','pgdm',
        'bcom','bsc','btech','bca','bba','engineering','commerce','science',
        'arts','management','university','college','institute','school',
        'finance','marketing','operations','human resource','technology',
        'first class','distinction','division','pass','grade','gpa','cgpa',
        'india','karnataka','maharashtra','gujarat','delhi','mumbai','bangalore',
    ];
    $firstLines = array_slice($lines, 0, 15);
    foreach ($firstLines as $line) {
        $line = trim($line);
        if (strlen($line) < 3 || strlen($line) > 50) continue;
        // Only letters, spaces, dots, hyphens
        if (!preg_match('/^[A-Za-z][A-Za-z\s\.\-]+$/', $line)) continue;
        $wordCount = str_word_count($line);
        // Names are 2-4 words
        if ($wordCount < 2 || $wordCount > 4) continue;
        // Reject sentences (ends with period)
        if (substr($line, -1) === '.') continue;
        // Each word must start uppercase (Title Case) OR line is ALL CAPS
        $isAllCaps   = (strtoupper($line) === $line);
        $isTitleCase = preg_match('/^([A-Z][a-z]*\.?\s*)+$/', $line);
        if (!$isAllCaps && !$isTitleCase) continue;
        // Check blacklist
        $ll  = strtolower($line);
        $bad = false;
        foreach ($nameBlacklist as $bw) {
            if (strpos($ll, $bw) !== false) { $bad = true; break; }
        }
        if ($bad) continue;
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

    // ── LinkedIn ───────────────────────────────────────────────────
    if (preg_match('/(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/([\w\-]+)/i', $text, $m)) {
        $data['linkedin'] = 'https://www.linkedin.com/in/' . $m[1];
    }

    // ── City / State / Country ─────────────────────────────────────
    $indianStates = [
        'Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat',
        'Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh',
        'Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab',
        'Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh',
        'Uttarakhand','West Bengal','Delhi','Jammu and Kashmir','Ladakh',
        'Chandigarh','Puducherry',
    ];
    foreach ($indianStates as $st) {
        if (stripos($text, $st) !== false) { $data['state'] = $st; break; }
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
            $v = (float)$m[1];
            if ($v > 0 && $v <= 10) { $data['cgpa'] = number_format($v, 2); break; }
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
            $v = (float)$m[1];
            if ($v >= 30 && $v <= 100) { $data['tenth_percent'] = number_format($v, 2); break; }
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
            $v = (float)$m[1];
            if ($v >= 30 && $v <= 100) { $data['twelfth_percent'] = number_format($v, 2); break; }
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
            $v = (float)$m[1];
            if ($i >= 3) {
                // These patterns found a percentage — convert to CGPA (% / 10)
                if ($v >= 30 && $v <= 100) $v = round($v / 10, 2);
                else continue;
            }
            if ($v > 0 && $v <= 10) { $data['graduation_percent'] = number_format($v, 2); break; }
        }
    }

    // ── Specialization ─────────────────────────────────────────────
    $specMap = [
        'Finance'    => ['finance','financial','accounting','accounts','banking',
                         'investment','taxation','insurance','fintech','treasury'],
        'Marketing'  => ['marketing','market research','digital marketing','brand',
                         'sales','retail','advertising','seo','sem','social media'],
        'HR'         => ['human resource','hr management','hrd','people management',
                         'talent acquisition','recruitment','hrm'],
        'Operations' => ['operations','supply chain','logistics','scm','procurement',
                         'project management','lean','six sigma','production'],
        'IT'         => ['information technology','it management','mis','systems',
                         'business analytics','data analytics','data science','erp'],
        'General'    => ['general management','strategy','entrepreneurship'],
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
        'MS Excel','MS Word','MS PowerPoint','MS Office','Tally','SAP','ERP','CRM',
        'Python','R Programming','SQL','MySQL','SPSS','SAS','MATLAB',
        'Tableau','Power BI','Google Analytics','Java','PHP','HTML','CSS','JavaScript',
        'AutoCAD','Canva','Figma','Photoshop',
        'Financial Analysis','Financial Modelling','Business Development',
        'Business Analytics','Market Research','Digital Marketing','SEO','SEM',
        'Supply Chain Management','Inventory Management','Project Management',
        'Data Analysis','Data Visualization','Statistical Analysis',
        'Leadership','Communication','Teamwork','Team Management','Problem Solving',
        'Critical Thinking','Time Management','Presentation','Negotiation',
        'Decision Making','Adaptability','Creativity',
    ];
    $found = [];
    foreach ($skillList as $skill) {
        if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text)) $found[] = $skill;
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
            if (strlen($c) > 8 && strlen($c) < 120) $certs[] = $c;
        }
    }
    if ($certs) $data['certifications'] = implode(', ', array_unique(array_slice($certs, 0, 5)));

    // ── Languages ──────────────────────────────────────────────────
    $langs = ['English','Hindi','Marathi','Tamil','Telugu','Kannada',
              'Bengali','Gujarati','Malayalam','Punjabi','Urdu','Odia','French','German'];
    $fl = [];
    $lp = stripos($text, 'language');
    $lchunk = $lp !== false ? substr($text, $lp, 300) : $text;
    foreach ($langs as $l) { if (stripos($lchunk, $l) !== false) $fl[] = $l; }
    if (empty($fl)) foreach ($langs as $l) { if (stripos($text, $l) !== false) $fl[] = $l; }
    $data['languages'] = implode(', ', array_unique($fl));

    // ── Work Experience ─────────────────────────────────────────────
    $expStart = -1;
    foreach (['experience','internship','intern','employment'] as $kw) {
        $pos = stripos($text, $kw);
        if ($pos !== false && ($expStart === -1 || $pos < $expStart)) $expStart = $pos;
    }
    if ($expStart !== -1) {
        $block = preg_replace('/\s+/', ' ', substr($text, $expStart, 600));
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($block), -1, PREG_SPLIT_NO_EMPTY);
        $summary = implode(' ', array_slice($sentences, 0, 3));
        if (strlen($summary) > 20) $data['work_experience'] = $summary;
    }

    return $data;
}

function getEmptyData(): array
{
    return [
        'name' => '', 'email' => '', 'phone' => '', 'linkedin' => '',
        'cgpa' => '', 'specialization' => '', 'tenth_percent' => '',
        'twelfth_percent' => '', 'graduation_percent' => '',
        'skills' => '', 'work_experience' => '', 'certifications' => '',
        'languages' => '', 'city' => '', 'state' => '', 'country' => '',
    ];
}

function normalizeSpecialization(string $val): string
{
    if (!$val) return '';
    $l = strtolower(trim($val));
    if (strpos($l, 'financ') !== false) return 'Finance';
    if (strpos($l, 'market') !== false) return 'Marketing';
    if (strpos($l, 'hr') !== false || strpos($l, 'human') !== false) return 'HR';
    if (strpos($l, 'operat') !== false || strpos($l, 'supply') !== false) return 'Operations';
    if (strpos($l, 'info') !== false || strpos($l, 'tech') !== false) return 'IT';
    return 'General';
}

function normalizePhone(string $val): string
{
    if (!$val) return '';
    $d = preg_replace('/\D/', '', $val);
    if (strlen($d) === 12 && substr($d, 0, 2) === '91') $d = substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0') $d = substr($d, 1);
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
        if ($o >= 32 && $o <= 126) $readable .= $text[$i];
        elseif ($o === 10 || $o === 13) $readable .= "\n";
    }
    $readable = preg_replace('/[<>]\s*[0-9a-fA-F]+\s*[<>]/', ' ', $readable);
    $readable = preg_replace('/\/\w+/', ' ', $readable);
    $readable = preg_replace('/\s+/', ' ', $readable);
    return trim($readable);
}
