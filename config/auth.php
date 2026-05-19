<?php
// config/auth.php — Session guard for protected pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Prevent browser from caching authenticated pages ──────────
// This stops the back-button from showing protected content after logout.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role_id = (int)($_SESSION['role_id'] ?? 0);
$name    = $_SESSION['name']    ?? 'User';
$email   = $_SESSION['email']   ?? '';

// ── CSRF helpers ──────────────────────────────────────────────
/**
 * Returns the per-session CSRF token, generating it on first call.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token submitted in a POST request.
 * Terminates with HTTP 403 if the token is missing or does not match.
 */
function validate_csrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        exit('403 Forbidden — CSRF token mismatch. Please go back and try again.');
    }
}
?>
