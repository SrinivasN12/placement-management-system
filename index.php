<?php
// index.php — Login Page
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/db.php';

// Generate CSRF token for the login form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Already logged in?
if (isset($_SESSION['user_id'])) {
    header("Location: " . getRoleDashboard($_SESSION['role_id']));
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF before touching credentials
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        http_response_code(403);
        exit('403 Forbidden — CSRF token mismatch.');
    }

    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, password, role_id, status FROM user WHERE email = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user   = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'Pending') {
                    $error = "⏳ Your registration is pending approval by the Placement Officer. Please wait for confirmation.";
                } elseif ($user['status'] !== 'Active') {
                    $error = "Your account is inactive. Contact the administrator.";
                } else {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['email']   = $email;
                    setUserSession($conn, $user['role_id'], $user['user_id']);
                    header("Location: " . getRoleDashboard($user['role_id']));
                    exit();
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}

function setUserSession($conn, $role_id, $user_id) {
    switch ($role_id) {
        case 1: $q = "SELECT name, student_id  FROM student            WHERE user_id=?"; break;
        case 2: $q = "SELECT name, officer_id  FROM placement_officer  WHERE user_id=?"; break;
        case 3: $q = "SELECT name, alumni_id   FROM alumni             WHERE user_id=?"; break;
        default: return;
    }
    $stmt = mysqli_prepare($conn, $q);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($data) {
            foreach ($data as $k => $v) $_SESSION[$k] = $v;
        }
    }
}

function getRoleDashboard($role_id) {
    switch ($role_id) {
        case 1: return "student/dashboard.php";
        case 2: return "officer/dashboard.php";
        case 3: return "alumni/dashboard.php";
        default: return "index.php";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS — Login | JSS MCA Institute</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Segoe UI',sans-serif;
            background:linear-gradient(135deg,#1a237e 0%,#283593 50%,#3949ab 100%);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
        }
        .login-wrapper {
            display:flex; width:860px; min-height:480px;
            border-radius:16px; overflow:hidden;
            box-shadow:0 20px 60px rgba(0,0,0,0.4);
        }
        .left-panel {
            background:rgba(255,255,255,0.08); backdrop-filter:blur(10px);
            width:45%; padding:48px 36px; display:flex; flex-direction:column;
            justify-content:center; color:#fff;
            border-right:1px solid rgba(255,255,255,0.15);
        }
        .left-panel h1 { font-size:22px; font-weight:700; line-height:1.4; margin-bottom:12px; }
        .left-panel p  { font-size:13px; opacity:.75; line-height:1.6; margin-bottom:28px; }
        .feature-list  { list-style:none; }
        .feature-list li {
            font-size:13px; opacity:.85; margin-bottom:10px;
            display:flex; align-items:center; gap:10px;
        }
        .feature-list li::before {
            content:"✓"; background:#42a5f5; color:#fff;
            width:20px; height:20px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:11px; flex-shrink:0;
        }
        .right-panel {
            background:#fff; width:55%; padding:48px 44px;
            display:flex; flex-direction:column; justify-content:center;
        }
        .logo-area { text-align:center; margin-bottom:28px; }
        .logo-area h2 { font-size:20px; color:#1a237e; font-weight:700; }
        .logo-area p  { font-size:12px; color:#888; margin-top:4px; }
        .form-group   { margin-bottom:18px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px; }
        .form-group input {
            width:100%; padding:11px 14px; border:1.5px solid #ddd;
            border-radius:8px; font-size:14px; color:#333;
            transition:border-color .2s; outline:none;
        }
        .form-group input:focus { border-color:#3949ab; }
        .error-msg {
            background:#ffebee; color:#c62828;
            border:1px solid #ef9a9a; border-radius:8px;
            padding:10px 14px; font-size:13px; margin-bottom:16px;
        }
        .btn-login {
            width:100%; padding:13px;
            background:linear-gradient(135deg,#1a237e,#3949ab);
            color:#fff; border:none; border-radius:8px;
            font-size:15px; font-weight:600; cursor:pointer; transition:opacity .2s;
        }
        .btn-login:hover { opacity:.9; }
        .register-links { margin-top:20px; text-align:center; font-size:13px; color:#666; }
        .register-links a { color:#3949ab; text-decoration:none; font-weight:600; }
        .register-links a:hover { text-decoration:underline; }
        .divider { border:none; border-top:1px solid #eee; margin:18px 0 14px; }
        .hint { font-size:11.5px; color:#aaa; text-align:center; margin-top:10px; }

        /* ── Mobile ─────────────────────────────────────────── */
        @media(max-width:700px) {
            body { padding: 0; align-items: flex-start; }
            .login-wrapper {
                flex-direction: column;
                width: 100%;
                min-height: 100vh;
                border-radius: 0;
                box-shadow: none;
            }
            .left-panel { display: none; }
            .right-panel {
                width: 100%;
                padding: 48px 28px 36px;
                min-height: 100vh;
            }
            .logo-area { margin-bottom: 24px; }
            .logo-area h2 { font-size: 22px; }
            .btn-login { font-size: 16px; padding: 14px; }
        }
        @media(max-width:400px) {
            .right-panel { padding: 36px 20px 28px; }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="left-panel">
        <h1>Automated Placement Management System</h1>
        <p>JSS Shri Manjunatheshwara MCA Institute, Vidyagiri, Dharwad</p>
        <ul class="feature-list">
            <li>Student profile &amp; pre-placement tracking</li>
            <li>Auto eligibility checking for drives</li>
            <li>Resume ATS score evaluation</li>
            <li>Real-time job notifications</li>
            <li>Alumni job recommendations</li>
            <li>Analytics &amp; placement reports</li>
        </ul>
    </div>
    <div class="right-panel">
        <div class="logo-area">
            <h2>🎓 PMS Login</h2>
            <p>Sign in to your account</p>
        </div>
        <?php if ($error): ?>
            <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-login">Login →</button>
        </form>
        <hr class="divider">
        <div class="register-links">
            New student? <a href="student/register.php">Register here</a>
            &nbsp;|&nbsp;
            Alumni? <a href="alumni/register.php">Register here</a>
        </div>
        <p class="hint">🔑 Test accounts — Password: <strong>password123</strong><br>
            student@pms.com &nbsp;|&nbsp; officer@pms.com &nbsp;|&nbsp; alumni@pms.com
        </p>
    </div>
</div>
</body>
</html>
