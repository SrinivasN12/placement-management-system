<?php
// =============================================================
//  student/smart_parse_resume.php
//  SMART RESUME PARSER — Gemini first, local fallback second.
//
//  FLOW:
//   1. Student uploads PDF resume
//   2. Try Gemini API (gemini-2.0-flash) with strict JSON prompt
//   3. If Gemini succeeds → return parsed JSON fields
//   4. If Gemini fails (any reason) → fall back to smalot/pdfparser
//      + regex to extract fields from PDF text
//   5. Return JSON: { success, method, data, [debug info] }
//
//  Called by: JS fetch() in resume_upload_block.php
//  Debug mode: Add ?debug=1 to URL for full diagnostic output.
// =============================================================

// ── Auth guard ───────────────────────────────────────────────
require_once '../config/auth.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// ── JSON response header ──────────────────────────────────────
header('Content-Type: application/json');

// ── Debug mode flag ───────────────────────────────────────────
$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$debugLog = [];

// ── Gemini configuration ──────────────────────────────────────
$geminiApiKey = 'AIzaSyBY7d18U0h7xjxstr8iDvETa8picvF-rrU';
$geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/'
    . 'models/gemini-2.0-flash:generateContent?key=' . $geminiApiKey;

// ── Validate file upload ──────────────────────────────────────
if (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error occurred.']);
    exit();
}

$file = $_FILES['resume_file'];
$maxBytes = 2 * 1024 * 1024; // 2 MB

// Check MIME type — PDF only
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

// ── Read PDF bytes ────────────────────────────────────────────
$pdfContent = file_get_contents($file['tmp_name']);
if ($pdfContent === false) {
    echo json_encode(['error' => 'Could not read uploaded file.']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
//  STEP 1 — Try Gemini API
// ═══════════════════════════════════════════════════════════════

$geminiRawResponse = '';
$geminiCleaned = '';
$geminiError = '';
$parsedData = null;
$method = 'gemini';

$pdfBase64 = base64_encode($pdfContent);

// ── Exact Gemini prompt as specified ─────────────────────────
$prompt = 'You are a resume parser.
Extract information from the provided resume PDF.
Return a JSON object with EXACTLY these keys:
{
  name: full name,
  email: email address,
  phone: phone number with country code if present,
  linkedin: linkedin URL if found else empty string,
  cgpa: CGPA numbers only like 7.5,
  specialization: MBA specialization like Finance HR Marketing Operations,
  tenth_percent: 10th percentage numbers only like 85.5,
  twelfth_percent: 12th percentage numbers only,
  graduation_percent: graduation percentage numbers only,
  skills: comma separated skills list,
  work_experience: brief work experience summary in 2-3 sentences,
  certifications: comma separated certifications,
  languages: comma separated languages known
}
STRICT RULES:
- Return ONLY the JSON object
- No markdown formatting
- No backticks before or after
- No explanation text
- No text before or after JSON
- If information not found use empty string
- Numeric fields return numbers only';

// ── Build Gemini request ──────────────────────────────────────
$requestBody = json_encode([
    'contents' => [
        [
            'parts' => [
                [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data' => $pdfBase64,
                    ],
                ],
                [
                    'text' => $prompt,
                ],
            ],
        ],
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'maxOutputTokens' => 1024,
    ],
]);

// ── Call Gemini via cURL ──────────────────────────────────────
$ch = curl_init($geminiEndpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($requestBody),
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$debugLog[] = "Gemini HTTP code: $httpCode";
$debugLog[] = "Gemini cURL error: " . ($curlError ?: 'none');

if ($response !== false && !$curlError && $httpCode === 200) {
    $geminiRawResponse = $response;
    $geminiJson = json_decode($response, true);

    if (isset($geminiJson['candidates'][0]['content']['parts'][0]['text'])) {
        $rawText = $geminiJson['candidates'][0]['content']['parts'][0]['text'];
        $rawText = trim($rawText);
        $debugLog[] = "Gemini raw text (first 200): " . substr($rawText, 0, 200);

        // ── JSON Cleaning (Problem 2) ─────────────────────────
        // Remove ```json and ``` markdown blocks
        $cleaned = preg_replace('/^```(?:json)?\s*/im', '', $rawText);
        $cleaned = preg_replace('/\s*```\s*$/im', '', $cleaned);

        // Remove any text before the first {
        $firstBrace = strpos($cleaned, '{');
        if ($firstBrace !== false) {
            $cleaned = substr($cleaned, $firstBrace);
        }

        // Remove any text after the last }
        $lastBrace = strrpos($cleaned, '}');
        if ($lastBrace !== false) {
            $cleaned = substr($cleaned, 0, $lastBrace + 1);
        }

        // Fix single quotes to double quotes (simple heuristic)
        // Only convert 'key': pattern common in Gemini responses
        $cleaned = preg_replace("/([{,]\s*)'([^']+)'\s*:/", '$1"$2":', $cleaned);

        // Remove trailing commas before } or ]
        $cleaned = preg_replace('/,\s*([\}\]])/m', '$1', $cleaned);

        // Trim whitespace
        $cleaned = trim($cleaned);
        $geminiCleaned = $cleaned;
        $debugLog[] = "Gemini cleaned (first 200): " . substr($cleaned, 0, 200);

        // ── Parse cleaned JSON ────────────────────────────────
        $extracted = json_decode($cleaned, true);
        if (is_array($extracted)) {
            $parsedData = sanitizeGeminiData($extracted);
            $method = 'gemini';
            $debugLog[] = "Gemini parsing: SUCCESS";
        } else {
            $geminiError = 'Gemini returned non-parseable JSON after cleaning. json_last_error: ' . json_last_error_msg();
            $debugLog[] = $geminiError;
        }
    } else {
        $geminiError = 'Gemini response missing expected candidates structure.';
        $debugLog[] = $geminiError;
        if ($debugMode) {
            $debugLog[] = 'Raw Gemini body: ' . substr($response, 0, 500);
        }
    }
} else {
    $geminiError = "Gemini API call failed. HTTP: $httpCode, cURL: $curlError";
    $debugLog[] = $geminiError;
}

// ═══════════════════════════════════════════════════════════════
//  STEP 2 — Local Fallback if Gemini failed
// ═══════════════════════════════════════════════════════════════

$pdfTextExtracted = '';

if ($parsedData === null) {
    $method = 'local';
    $debugLog[] = "Falling back to local parser (smalot/pdfparser + regex)";

    // ── Try smalot/pdfparser ──────────────────────────────────
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseContent($pdfContent);
            $pdfTextExtracted = $pdf->getText();
            $debugLog[] = "smalot/pdfparser: extracted " . strlen($pdfTextExtracted) . " chars";
        } catch (\Exception $e) {
            $debugLog[] = "smalot/pdfparser error: " . $e->getMessage();
            // Fall through — pdfTextExtracted stays empty
        }
    } else {
        $debugLog[] = "smalot/pdfparser not installed (vendor/autoload.php not found)";
    }

    // ── If smalot didn't work, try basic text extraction ──────
    if (empty(trim($pdfTextExtracted))) {
        $debugLog[] = "Trying basic PDF text extraction fallback";
        $pdfTextExtracted = extractTextFromPdfBasic($pdfContent);
        $debugLog[] = "Basic extraction: " . strlen($pdfTextExtracted) . " chars";
    }

    // ── Now run regex local parser ────────────────────────────
    if (!empty(trim($pdfTextExtracted))) {
        $parsedData = localParseResume($pdfTextExtracted, $debugLog);
        $debugLog[] = "Local parse complete. Fields found: " . count(array_filter($parsedData));
    } else {
        $debugLog[] = "No text extracted from PDF — cannot parse locally";
        $parsedData = getEmptyData();
    }
}

// ═══════════════════════════════════════════════════════════════
//  STEP 3 — Return response
// ═══════════════════════════════════════════════════════════════

if ($debugMode) {
    echo json_encode([
        'success' => true,
        'method' => $method,
        'data' => $parsedData,
        'gemini_raw_response' => substr($geminiRawResponse, 0, 2000),
        'gemini_cleaned' => $geminiCleaned,
        'pdf_text_extracted' => substr($pdfTextExtracted, 0, 500),
        'errors' => $debugLog,
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => true,
        'method' => $method,
        'data' => $parsedData,
    ]);
}
exit();

// ═══════════════════════════════════════════════════════════════
//  HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════

/**
 * Sanitizes and normalizes Gemini-extracted data.
 * Ensures all expected keys are present and values are clean.
 */
function sanitizeGeminiData(array $raw): array
{
    $result = [];

    // String fields
    $stringFields = [
        'name',
        'email',
        'phone',
        'linkedin',
        'specialization',
        'skills',
        'work_experience',
        'certifications',
        'languages'
    ];
    foreach ($stringFields as $f) {
        $val = isset($raw[$f]) ? trim((string) $raw[$f]) : '';
        // Normalize: remove surrounding quotes if Gemini added them
        $val = trim($val, '"\'');
        $result[$f] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }

    // Numeric fields — extract digits + decimal only
    $numFields = ['cgpa', 'tenth_percent', 'twelfth_percent', 'graduation_percent'];
    foreach ($numFields as $f) {
        $raw_val = isset($raw[$f]) ? trim((string) $raw[$f]) : '';
        // Extract first float-like number from the value
        if (preg_match('/(\d+\.?\d*)/', $raw_val, $m)) {
            $num = (float) $m[1];
            // Sanity range check
            if ($f === 'cgpa' && ($num < 0 || $num > 10))
                $num = 0;
            if (in_array($f, ['tenth_percent', 'twelfth_percent', 'graduation_percent']) && ($num < 0 || $num > 100))
                $num = 0;
            $result[$f] = $num > 0 ? (string) $num : '';
        } else {
            $result[$f] = '';
        }
    }

    // Normalize specialization to match our MBA options
    $result['specialization'] = normalizeSpecialization($result['specialization']);

    // Normalize phone — extract 10-digit Indian number
    $result['phone'] = normalizePhone($result['phone']);

    return $result;
}

/**
 * Local regex-based resume parser using extracted PDF text.
 */
function localParseResume(string $text, array &$log): array
{
    $data = getEmptyData();
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $text)),
        fn($l) => strlen($l) > 0
    ));

    // ── Name — first non-empty line that looks like a name ───
    foreach ($lines as $line) {
        $line = trim($line);
        // Letters and spaces only, 3–50 chars, no @ or 10+ digit runs
        if (
            strlen($line) >= 3 &&
            strlen($line) <= 50 &&
            preg_match('/^[A-Za-z\s\.]+$/', $line) &&
            strpos($line, '@') === false &&
            !preg_match('/\d{10,}/', $line)
        ) {
            $data['name'] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            $log[] = "Name found: {$line}";
            break;
        }
    }

    // ── Email ─────────────────────────────────────────────────
    if (preg_match('/[\w.\-]+@[\w.\-]+\.\w+/', $text, $m)) {
        $data['email'] = htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8');
        $log[] = "Email found: {$m[0]}";
    }

    // ── Phone ─────────────────────────────────────────────────
    if (preg_match('/(\+91[\s\-]?)?[6-9]\d{9}/', $text, $m)) {
        $data['phone'] = normalizePhone($m[0]);
        $log[] = "Phone found: {$m[0]}";
    }

    // ── LinkedIn ──────────────────────────────────────────────
    if (preg_match('/linkedin\.com\/in\/[\w\-]+/i', $text, $m)) {
        $data['linkedin'] = 'https://www.' . $m[0];
        $log[] = "LinkedIn found: {$m[0]}";
    }

    // ── CGPA ──────────────────────────────────────────────────
    if (preg_match('/cgpa[\s:]+([0-9]\.[0-9]{1,2})/i', $text, $m)) {
        $data['cgpa'] = $m[1];
        $log[] = "CGPA found: {$m[1]}";
    } elseif (preg_match('/([0-9]\.[0-9]{1,2})\s*\/\s*10/i', $text, $m)) {
        $data['cgpa'] = $m[1];
        $log[] = "CGPA (x/10 format) found: {$m[1]}";
    }

    // ── 10th Percentage ───────────────────────────────────────
    if (preg_match('/10th[^\d]*(\d{2,3}\.?\d*)\s*%/i', $text, $m)) {
        $data['tenth_percent'] = $m[1];
        $log[] = "10th %: {$m[1]}";
    } elseif (preg_match('/(?:ssc|tenth|10th|x(?:th)?)[^\d]{0,20}(\d{2,3}\.?\d*)\s*%/i', $text, $m)) {
        $data['tenth_percent'] = $m[1];
        $log[] = "10th % (alt): {$m[1]}";
    }

    // ── 12th Percentage ───────────────────────────────────────
    if (preg_match('/12th[^\d]*(\d{2,3}\.?\d*)\s*%/i', $text, $m)) {
        $data['twelfth_percent'] = $m[1];
        $log[] = "12th %: {$m[1]}";
    } elseif (preg_match('/(?:hsc|twelfth|12th|xii(?:th)?|intermediate)[^\d]{0,20}(\d{2,3}\.?\d*)\s*%/i', $text, $m)) {
        $data['twelfth_percent'] = $m[1];
        $log[] = "12th % (alt): {$m[1]}";
    }

    // ── Graduation Percentage ─────────────────────────────────
    if (preg_match('/(b\.?com|bba|bsc|b\.?tech|b\.?e|graduation|undergraduate)[^\d]{0,30}(\d{2,3}\.?\d*)\s*%/i', $text, $m)) {
        $data['graduation_percent'] = $m[2];
        $log[] = "Graduation %: {$m[2]}";
    } elseif (preg_match('/(?:degree|ug)[^\d]{0,20}(\d{2,3}\.?\d*)\s*%/i', $text, $m)) {
        $data['graduation_percent'] = $m[1];
        $log[] = "Graduation % (alt): {$m[1]}";
    }

    // ── Specialization ────────────────────────────────────────
    $specKeywords = [
        'Finance' => 'Finance',
        'Marketing' => 'Marketing',
        'Human Resource' => 'HR',
        'HR' => 'HR',
        'Operations' => 'Operations',
        'Business Analytics' => 'General',
        'IT' => 'IT',
        'Information Technology' => 'IT',
        'International Business' => 'General',
        'Supply Chain' => 'Operations',
        'General Management' => 'General',
    ];
    foreach ($specKeywords as $keyword => $mapped) {
        if (stripos($text, $keyword) !== false) {
            $data['specialization'] = $mapped;
            $log[] = "Specialization found via keyword '$keyword': $mapped";
            break;
        }
    }

    // ── Skills ────────────────────────────────────────────────
    $skillKeywords = [
        'Excel',
        'PowerPoint',
        'Tally',
        'SAP',
        'Python',
        'SQL',
        'Tableau',
        'Power BI',
        'Financial Analysis',
        'Digital Marketing',
        'SEO',
        'Google Analytics',
        'Leadership',
        'Communication',
        'Team Management',
        'Supply Chain',
        'CRM',
        'ERP',
        'Java',
        'PHP',
        'HTML',
        'Market Research',
        'Business Development',
        'Negotiation',
        'Presentation',
        'AutoCAD',
        'SPSS',
        'MS Office',
        'MS Excel',
        'Canva',
        'R Programming',
    ];
    // Short/ambiguous keywords that need word-boundary matching
    $strictKeywords = ['R', 'Word', 'SAP', 'ERP', 'CRM', 'SEO', 'PHP', 'SQL', 'HTML'];

    $foundSkills = [];
    foreach ($skillKeywords as $skill) {
        // Use word-boundary regex to avoid false positives (e.g. 'R' in 'Rahul')
        $pattern = '/\b' . preg_quote($skill, '/') . '\b/i';
        if (preg_match($pattern, $text)) {
            $foundSkills[] = $skill;
        }
    }
    // Also check strict single-word skills with word boundaries
    foreach ($strictKeywords as $skill) {
        $pattern = '/\b' . preg_quote($skill, '/') . '\b/i';
        if (preg_match($pattern, $text)) {
            $foundSkills[] = $skill;
        }
    }
    // Deduplicate (case-insensitive)
    $foundSkills = array_values(array_unique(array_map('strtolower', $foundSkills)));
    $foundSkills = array_map('ucwords', $foundSkills);
    $data['skills'] = implode(', ', $foundSkills);
    if ($data['skills'])
        $log[] = "Skills found: {$data['skills']}";

    // ── Certifications ────────────────────────────────────────
    preg_match_all('/(certified|certification|certificate|course)[^\n]{5,80}/i', $text, $certMatches);
    if (!empty($certMatches[0])) {
        $certs = array_slice($certMatches[0], 0, 5);
        $certs = array_map(function ($c) {
            return trim(preg_replace('/\s+/', ' ', $c)); }, $certs);
        $data['certifications'] = htmlspecialchars(implode(', ', $certs), ENT_QUOTES, 'UTF-8');
        $log[] = "Certifications found: " . count($certs);
    }

    // ── Languages ─────────────────────────────────────────────
    $langKeywords = [
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
        'Assamese',
    ];
    $foundLangs = [];
    foreach ($langKeywords as $lang) {
        if (stripos($text, $lang) !== false) {
            $foundLangs[] = $lang;
        }
    }
    $data['languages'] = implode(', ', $foundLangs);
    if ($data['languages'])
        $log[] = "Languages found: {$data['languages']}";

    // ── Work Experience ───────────────────────────────────────
    $expPos = stripos($text, 'experience');
    if ($expPos !== false) {
        $expChunk = substr($text, $expPos, 400);
        // Clean whitespace
        $expChunk = preg_replace('/\s+/', ' ', $expChunk);
        $expChunk = trim($expChunk);
        $data['work_experience'] = htmlspecialchars($expChunk, ENT_QUOTES, 'UTF-8');
        $log[] = "Work experience extracted (" . strlen($expChunk) . " chars)";
    }

    return $data;
}

/**
 * Returns empty data array with all expected keys.
 */
function getEmptyData(): array
{
    return [
        'name' => '',
        'email' => '',
        'phone' => '',
        'linkedin' => '',
        'cgpa' => '',
        'specialization' => '',
        'tenth_percent' => '',
        'twelfth_percent' => '',
        'graduation_percent' => '',
        'skills' => '',
        'work_experience' => '',
        'certifications' => '',
        'languages' => '',
    ];
}

/**
 * Normalize specialization to our MBA dropdown values.
 */
function normalizeSpecialization(string $val): string
{
    if (!$val)
        return '';
    $lower = strtolower($val);
    if (strpos($lower, 'financ') !== false)
        return 'Finance';
    if (strpos($lower, 'market') !== false)
        return 'Marketing';
    if (strpos($lower, 'hr') !== false || strpos($lower, 'human') !== false)
        return 'HR';
    if (strpos($lower, 'operat') !== false || strpos($lower, 'supply') !== false)
        return 'Operations';
    if (strpos($lower, 'it') !== false || strpos($lower, 'information') !== false || strpos($lower, 'tech') !== false)
        return 'IT';
    if ($val)
        return 'General';
    return '';
}

/**
 * Normalize phone number — extract 10-digit Indian mobile number.
 */
function normalizePhone(string $val): string
{
    if (!$val)
        return '';
    // Strip non-digit characters
    $digits = preg_replace('/\D/', '', $val);
    // Remove +91 prefix if present (91 followed by 10 digits)
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        $digits = substr($digits, 2);
    }
    // Return last 10 digits if valid length
    if (strlen($digits) >= 10) {
        return substr($digits, -10);
    }
    return '';
}

/**
 * Basic PDF text extraction fallback (no library).
 * Extracts readable ASCII text from raw PDF bytes.
 * Less accurate than smalot/pdfparser but works without composer.
 */
function extractTextFromPdfBasic(string $pdfContent): string
{
    // Extract text between stream and endstream markers
    $text = '';
    preg_match_all('/stream(.*?)endstream/s', $pdfContent, $streams);
    foreach ($streams[1] as $stream) {
        // Try to decode if gzip/flate compressed
        $decoded = @gzuncompress($stream);
        if ($decoded !== false) {
            $text .= $decoded . "\n";
        } else {
            $text .= $stream . "\n";
        }
    }

    // Extract readable ASCII characters, preserve spaces and newlines
    $readable = '';
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($text[$i]);
        if ($ord >= 32 && $ord <= 126) {
            $readable .= $text[$i];
        } elseif ($ord === 10 || $ord === 13) {
            $readable .= "\n";
        }
    }

    // Remove PDF operators and control characters
    $readable = preg_replace('/\s+/', ' ', $readable);
    $readable = preg_replace('/[<>]\s*[0-9a-fA-F]+\s*[<>]/', ' ', $readable); // hex strings
    $readable = preg_replace('/\/\w+/', ' ', $readable); // PDF names like /Font /Page

    return trim($readable);
}
