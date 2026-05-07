<?php
// Groq API health check — open in browser while logged in as student
require_once '../config/auth.php';
if ($role_id != 1) { echo 'Login as student first.'; exit(); }

$key = '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'; // Groq key
$url = 'https://api.groq.com/openai/v1/chat/completions';

$body = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => [['role' => 'user', 'content' => 'Reply with exactly the word: OK']],
    'max_tokens'  => 5,
    'temperature' => 0.0,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

$dec  = json_decode($resp, true);
$text = $dec['choices'][0]['message']['content'] ?? '(no reply)';

echo "<pre style='font-family:monospace;font-size:14px;padding:20px;background:#f9f9f9'>";
echo "HTTP Code  : $http\n";
echo "cURL Error : " . ($err ?: 'none') . "\n";
echo "API Reply  : $text\n\n";

if ($http === 200) {
    echo "✅ Groq API is WORKING — AI resume parsing will be active.\n";
} elseif ($http === 401) {
    echo "❌ API KEY INVALID — paste your key from console.groq.com.\n";
} elseif ($http === 429) {
    echo "⚠️ RATE LIMITED — wait 1 minute and try again.\n";
} elseif ($http === 0) {
    echo "❌ NETWORK ERROR — XAMPP can't reach groq.com. Check internet/firewall.\n";
} else {
    echo "❌ Error HTTP $http\n" . htmlspecialchars(substr($resp, 0, 500));
}
echo "</pre>";
