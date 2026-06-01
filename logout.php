<?php
// logout.php — Completely destroy the session and redirect to login
session_start();

// Wipe all session variables
$_SESSION = [];

// Expire the session cookie immediately
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destroy the session on the server
session_destroy();

// Prevent this response from being cached
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

header("Location: index.php");
exit();
?>
