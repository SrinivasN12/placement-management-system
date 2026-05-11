<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';

// CSRF helpers for pre-auth pages
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        http_response_code(403);
        exit('403 Forbidden — CSRF token mismatch.');
    }
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $company = trim($_POST['company']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (empty($name)||empty($email)||empty($company)||empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $chk = mysqli_prepare($conn, "SELECT user_id FROM user WHERE email=?");
        mysqli_stmt_bind_param($chk,"s",$email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = "This email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s1 = mysqli_prepare($conn,"INSERT INTO user (email,password,role_id,status) VALUES (?,?,3,'Active')");
            mysqli_stmt_bind_param($s1,"ss",$email,$hash);
            mysqli_stmt_execute($s1);
            $uid = mysqli_insert_id($conn);
            mysqli_stmt_close($s1);
            $s2 = mysqli_prepare($conn,"INSERT INTO alumni (user_id,name,company_name) VALUES (?,?,?)");
            mysqli_stmt_bind_param($s2,"iss",$uid,$name,$company);
            mysqli_stmt_execute($s2);
            mysqli_stmt_close($s2);
            $success = "Registration successful! <a href='../index.php'>Login here</a>";
        }
        mysqli_stmt_close($chk);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Registration — PMS</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',sans-serif; background:linear-gradient(135deg,#4a148c,#6a1b9a); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px; }
        .card { background:#fff; border-radius:14px; padding:36px 40px; width:100%; max-width:460px; box-shadow:0 16px 48px rgba(0,0,0,.3); }
        h2 { color:#4a148c; margin-bottom:6px; font-size:20px; }
        .sub { font-size:13px; color:#888; margin-bottom:24px; }
        .form-group { margin-bottom:16px; }
        label { display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:5px; }
        input { width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:8px; font-size:14px; outline:none; }
        input:focus { border-color:#6a1b9a; }
        .btn { width:100%; padding:13px; background:linear-gradient(135deg,#4a148c,#6a1b9a); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; margin-top:6px; }
        .btn:hover { opacity:.9; }
        .error   { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
        .success { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
        .login-link { text-align:center; margin-top:18px; font-size:13px; color:#666; }
        .login-link a { color:#4a148c; font-weight:600; text-decoration:none; }
        /* ── Mobile ── */
        @media(max-width:600px) {
            body { padding: 0; align-items: flex-start; }
            .card { border-radius:0; min-height:100vh; padding:36px 22px 40px; box-shadow:none; max-width:100%; }
            .btn { font-size:16px; padding:14px; }
        }
    </style>
</head>
<body>
<div class="card">
    <h2>🌐 Alumni Registration</h2>
    <p class="sub">Join the PMS Alumni Network</p>
    <?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
    <?php if (!$success): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" placeholder="e.g. Priya Sharma" value="<?= htmlspecialchars($_POST['name']??'') ?>" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
        </div>
        <div class="form-group">
            <label>Current Company</label>
            <input type="text" name="company" placeholder="e.g. Infosys" value="<?= htmlspecialchars($_POST['company']??'') ?>" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Min 6 chars" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn">Register →</button>
    </form>
    <?php endif; ?>
    <div class="login-link">Already have an account? <a href="../index.php">Login</a></div>
</div>
</body>
</html>
