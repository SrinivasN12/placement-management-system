<?php
// Quick Gemini API diagnostic — DELETE after debugging
// Access at: http://localhost/new_pms_ag_new/student/test_gemini.php
header('Content-Type: text/plain; charset=UTF-8');

$apiKey   = 'AIzaSyBUrNQ_GJwmq4usNQDPDGqYiRRV0qTeEm4';
$model    = 'gemini-2.0-flash-lite';
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$body = json_encode([
    'contents' => [['parts' => [['text' =>
        'Return ONLY this exact JSON, nothing else: {"status":"ok","model":"gemini-2.0-flash"}'
    ]]]],
    'generationConfig' => [
        'temperature'      => 0.0,
        'maxOutputTokens'  => 64,
        'responseMimeType' => 'application/json',
    ],
]);

echo "=== Gemini API Diagnostic ===\n";
echo "Endpoint : $endpoint\n";
echo "Model    : $model\n\n";

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp      = curl_exec($ch);
$err       = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL error: " . ($err ?: 'none') . "\n\n";

if ($err || $resp === false) {
    echo "FAIL — cURL error: $err\n";
    exit;
}

echo "--- Raw Response ---\n$resp\n\n";

$json = json_decode($resp, true);
if (!$json) {
    echo "FAIL — Could not parse response as JSON\n";
    exit;
}

if ($httpCode !== 200) {
    echo "FAIL — Non-200 HTTP code\n";
    echo "Error message: " . ($json['error']['message'] ?? 'unknown') . "\n";
    exit;
}

$text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
if (!$text) {
    echo "FAIL — No text in candidates\n";
    echo "Full JSON: " . json_encode($json, JSON_PRETTY_PRINT) . "\n";
    exit;
}

echo "--- Model Reply ---\n$text\n\n";

$parsed = json_decode(trim($text), true);
if (is_array($parsed)) {
    echo "SUCCESS ✓ — Gemini is working and returned valid JSON.\n";
    echo "status : " . ($parsed['status'] ?? '?') . "\n";
    echo "model  : " . ($parsed['model']  ?? '?') . "\n";
} else {
    echo "PARTIAL — Gemini responded but JSON parsing failed: " . json_last_error_msg() . "\n";
}
