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
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character (e.g. @, #, !).";
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
        /* ── Password Strength ── */
        .pw-wrap { position: relative; }
        .pw-toggle { position: absolute; right: 11px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #999; font-size: 15px; padding: 0; line-height: 1; }
        .strength-bar { display: flex; gap: 4px; margin-top: 6px; }
        .strength-bar span { flex: 1; height: 4px; border-radius: 4px; background: #e0e0e0; transition: background .25s; }
        .strength-label { font-size: 11px; margin-top: 4px; font-weight: 600; }
        .req-list { margin-top: 6px; list-style: none; }
        .req-list li { font-size: 11px; color: #bbb; display: flex; align-items: center; gap: 5px; margin-bottom: 2px; transition: color .2s; }
        .req-list li.ok { color: #2e7d32; }
        .req-list li::before { content: '✗'; font-size: 10px; }
        .req-list li.ok::before { content: '✓'; }
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
            <div class="pw-wrap">
                <input type="password" id="pw" name="password" placeholder="Min 8 characters" required autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePw('pw',this)" tabindex="-1">👁</button>
            </div>
            <div class="strength-bar">
                <span id="sb1"></span><span id="sb2"></span>
                <span id="sb3"></span><span id="sb4"></span>
            </div>
            <div class="strength-label" id="slabel"></div>
            <ul class="req-list">
                <li id="r-len">At least 8 characters</li>
                <li id="r-upper">One uppercase letter (A–Z)</li>
                <li id="r-lower">One lowercase letter (a–z)</li>
                <li id="r-digit">One digit (0–9)</li>
                <li id="r-sym">One special character (!@#$…)</li>
            </ul>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <div class="pw-wrap">
                <input type="password" id="pw2" name="confirm" placeholder="Repeat password" required autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)" tabindex="-1">👁</button>
            </div>
            <div class="strength-label" id="matchlabel" style="margin-top:6px"></div>
        </div>
        <button type="submit" class="btn">Register →</button>
    </form>
    <?php endif; ?>
    <div class="login-link">Already have an account? <a href="../login.php">Login</a></div>

<script>
const colors = ['#ef5350','#ff9800','#ffeb3b','#4caf50'];
const labels = ['Weak','Fair','Good','Strong'];
function checkPw() {
    const v = document.getElementById('pw').value;
    const reqs = {
        len:   v.length >= 8,
        upper: /[A-Z]/.test(v),
        lower: /[a-z]/.test(v),
        digit: /[0-9]/.test(v),
        sym:   /[\W_]/.test(v)
    };
    document.getElementById('r-len').classList.toggle('ok', reqs.len);
    document.getElementById('r-upper').classList.toggle('ok', reqs.upper);
    document.getElementById('r-lower').classList.toggle('ok', reqs.lower);
    document.getElementById('r-digit').classList.toggle('ok', reqs.digit);
    document.getElementById('r-sym').classList.toggle('ok', reqs.sym);
    const score = Object.values(reqs).filter(Boolean).length - 1;
    ['sb1','sb2','sb3','sb4'].forEach((id,i) => {
        document.getElementById(id).style.background = (v.length===0) ? '#e0e0e0' : (i<=score ? colors[Math.min(score,3)] : '#e0e0e0');
    });
    const lbl = document.getElementById('slabel');
    if (v.length===0) { lbl.textContent=''; lbl.style.color=''; }
    else { lbl.textContent=labels[Math.min(score,3)]; lbl.style.color=colors[Math.min(score,3)]; }
    checkMatch();
}
function checkMatch() {
    const v1=document.getElementById('pw').value;
    const v2=document.getElementById('pw2').value;
    const ml=document.getElementById('matchlabel');
    if (!v2) { ml.textContent=''; return; }
    ml.textContent = v1===v2 ? '✓ Passwords match' : '✗ Passwords do not match';
    ml.style.color = v1===v2 ? '#2e7d32' : '#c62828';
}
function togglePw(id, btn) {
    const inp=document.getElementById(id);
    inp.type = inp.type==='password' ? 'text' : 'password';
    btn.textContent = inp.type==='password' ? '👁' : '🙈';
}
document.getElementById('pw').addEventListener('input', checkPw);
document.getElementById('pw2').addEventListener('input', checkMatch);
document.querySelector('form').addEventListener('submit', function(e) {
    const v = document.getElementById('pw').value;
    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(v)) {
        e.preventDefault();
        alert('Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.');
    } else if (v !== document.getElementById('pw2').value) {
        e.preventDefault();
        alert('Passwords do not match.');
    }
});
</script>
</div>
</body>
</html>
