<?php
// =============================================================
//  student/parse_resume.php
//  Receives a PDF resume via POST, sends it to Google Gemini
//  API (gemini-1.5-flash), and returns extracted profile
//  fields as a JSON object.
//
//  Called by: JS fetch() in resume_upload_block.php
//  Returns  : JSON { name, specialization, cgpa, ... }
// =============================================================

// ── Auth guard ───────────────────────────────────────────────
require_once '../config/auth.php';
if ($role_id != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// ── Gemini API key — replace with your actual key ────────────
$geminiApiKey = 'AIzaSyAjTtX-YHN3AS4k3yXXj6oi7eYC8Sy6f3c';

// ── Gemini model endpoint ─────────────────────────────────────
$geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/'
    . 'models/gemini-1.5-flash:generateContent?key=' . $geminiApiKey;

// ── JSON response helper ──────────────────────────────────────
header('Content-Type: application/json');

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

// Check file size
if ($file['size'] > $maxBytes) {
    echo json_encode(['error' => 'File exceeds 2 MB limit.']);
    exit();
}

// ── Read PDF and convert to base64 ───────────────────────────
$pdfContent = file_get_contents($file['tmp_name']);
if ($pdfContent === false) {
    echo json_encode(['error' => 'Could not read uploaded file.']);
    exit();
}
$pdfBase64 = base64_encode($pdfContent);

// ── Build Gemini API prompt ───────────────────────────────────
// We ask Gemini to extract exactly the fields we need for the
// profile form. The specialization field should match one of
// our MBA options: Finance, Marketing, HR, Operations, IT, General.
$prompt = 'Extract details from this MBA student resume and return '
    . 'ONLY a JSON object with these exact fields: '
    . 'name (full name of student), '
    . 'contact_number (10-digit phone number, digits only), '
    . 'specialization (one of: Finance, Marketing, HR, Operations, IT, General — best match), '
    . 'cgpa (MBA or PG CGPA on 10-point scale, number only), '
    . 'tenth_percentage (10th class / SSC percentage, number only), '
    . 'twelfth_percentage (12th class / HSC / Intermediate percentage, number only), '
    . 'degree_cgpa (undergraduate degree CGPA on 10-point scale, number only), '
    . 'state (Indian state of residence), '
    . 'city (city of residence), '
    . 'country (country, default India). '
    . 'If a field is not found return empty string. '
    . 'Return ONLY the JSON object, no explanation, no markdown, no backticks.';

// ── Build the Gemini API request payload ──────────────────────
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
        'temperature' => 0.1, // low temperature for factual extraction
        'maxOutputTokens' => 512,
    ],
]);

// ── Send request to Gemini via cURL ──────────────────────────
$ch = curl_init($geminiEndpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $requestBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($requestBody),
    ],
    CURLOPT_TIMEOUT => 60, // 60-second timeout for large PDFs
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ── Handle cURL / network errors ─────────────────────────────
if ($response === false || $curlError) {
    echo json_encode(['error' => 'Could not connect to Gemini API. ' . $curlError]);
    exit();
}

// ── Parse Gemini response ─────────────────────────────────────
$geminiResponse = json_decode($response, true);

if ($httpCode !== 200 || !isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
    // Log the raw Gemini error for debugging (not sent to client)
    error_log('Gemini API error (' . $httpCode . '): ' . $response);
    echo json_encode(['error' => 'Could not read resume. Please fill manually.']);
    exit();
}

// ── Extract text from Gemini response ────────────────────────
$rawText = trim($geminiResponse['candidates'][0]['content']['parts'][0]['text']);

// Strip any markdown code fences Gemini might add despite instructions
$rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
$rawText = preg_replace('/\s*```\s*$/', '', $rawText);
$rawText = trim($rawText);

// ── Parse extracted JSON ──────────────────────────────────────
$extracted = json_decode($rawText, true);

if (!is_array($extracted)) {
    error_log('Gemini returned non-JSON: ' . $rawText);
    echo json_encode(['error' => 'Could not read resume. Please fill manually.']);
    exit();
}

// ── Sanitize and normalise values before returning ────────────
// Keep only expected fields; strip anything else.
$allowed = [
    'name',
    'contact_number',
    'specialization',
    'cgpa',
    'tenth_percentage',
    'twelfth_percentage',
    'degree_cgpa',
    'state',
    'city',
    'country',
];

$safe = [];
foreach ($allowed as $field) {
    $val = trim($extracted[$field] ?? '');

    // For numeric fields, ensure we have a valid positive number
    if (in_array($field, ['cgpa', 'tenth_percentage', 'twelfth_percentage', 'degree_cgpa'])) {
        $num = filter_var($val, FILTER_VALIDATE_FLOAT);
        $safe[$field] = ($num !== false && $num >= 0) ? (string) $num : '';
    } elseif ($field === 'contact_number') {
        // Strip non-digit characters, keep last 10 digits
        $digits = preg_replace('/\D/', '', $val);
        $safe[$field] = (strlen($digits) >= 10) ? substr($digits, -10) : '';
    } else {
        $safe[$field] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
}

// ── Return clean JSON to the client ──────────────────────────
echo json_encode(['success' => true, 'data' => $safe]);
exit();
