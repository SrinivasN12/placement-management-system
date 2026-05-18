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
// index.php → root URL redirect to landing page
header('Location: landing.php');
exit();

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
<meta name="description" content="PMS Login — JSS DVH-IMSR College Placement Management System">
<title>PMS — Login | JSS DVH-IMSR College</title>
<link rel="stylesheet" href="assets/pms-theme.css">
</head>
<body>
<div class="auth-page-bg">
  <div class="auth-split-card">

    <!-- Left Panel -->
    <div class="auth-split-left">
      <div style="margin-bottom:28px">
        <img src="assets/logo.jpg" alt="JSS DVH-IMSR" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid rgba(201,168,76,.4);background:#fff;margin-bottom:16px">
        <div style="font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:700;color:#fff;line-height:1.2">JSS DVH-IMSR<br>Placement Portal</div>
        <p class="split-sub" style="margin-top:8px">JSS Shri Manjunatheshwara MCA Institute, Vidyagiri, Dharwad</p>
      </div>
      <ul class="split-feature-list">
        <li><span class="chk">✓</span> Student profile &amp; pre-placement tracking</li>
        <li><span class="chk">✓</span> Auto eligibility checking for drives</li>
        <li><span class="chk">✓</span> Resume ATS score evaluation</li>
        <li><span class="chk">✓</span> Real-time job notifications</li>
        <li><span class="chk">✓</span> Alumni job recommendations</li>
        <li><span class="chk">✓</span> Analytics &amp; placement reports</li>
      </ul>
    </div>

    <!-- Right Panel -->
    <div class="auth-split-right">
      <div class="auth-logo-wrap">
        <img src="assets/logo.jpg" alt="JSS Logo" class="auth-logo-img">
        <span class="auth-role-badge">Placement Portal Login</span>
      </div>
      <h1 class="auth-title" style="font-size:26px">Welcome Back</h1>
      <p class="auth-sub">Sign in to your PMS account</p>

      <?php if ($error): ?>
        <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group-sm">
          <label class="form-label-sm" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-ctrl"
                 placeholder="your@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group-sm" style="margin-bottom:24px">
          <label class="form-label-sm" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-ctrl"
                 placeholder="Enter your password" required>
        </div>
        <div class="register-links" style="margin-top:10px">
            <a href="landing.php">← Back to Home</a>
        </div>
        <p class="hint">🔑 Test accounts — Password: <strong>password123</strong><br>
            student@pms.com &nbsp;|&nbsp; officer@pms.com &nbsp;|&nbsp; alumni@pms.com
        </p>
    </div>

  </div>
</div>
</body>
</html>
