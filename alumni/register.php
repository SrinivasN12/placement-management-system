<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
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

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $company  = trim($_POST['company']  ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    // ── Server-side validation ─────────────────────────────────────
    if (empty($name) || empty($email) || empty($company) || empty($password) || empty($confirm)) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[A-Za-z\s\'\-]{2,60}$/', $name)) {
        $error = "Full name must be 2–60 characters and contain only letters, spaces, hyphens, or apostrophes.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($company) < 2 || strlen($company) > 100) {
        $error = "Company name must be between 2 and 100 characters.";
    } elseif (!preg_match('/^[A-Za-z0-9\s\.\,\&\-\'\/\(\)]{2,100}$/', $company)) {
        $error = "Company name contains invalid characters.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character (e.g. @, #, !).";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $chk = mysqli_prepare($conn, "SELECT user_id FROM user WHERE email=?");
        mysqli_stmt_bind_param($chk, "s", $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = "This email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $s1 = mysqli_prepare($conn, "INSERT INTO user (email,password,role_id,status) VALUES (?,?,3,'Active')");
            mysqli_stmt_bind_param($s1, "ss", $email, $hash);
            mysqli_stmt_execute($s1);
            $uid = mysqli_insert_id($conn);
            mysqli_stmt_close($s1);
            $s2 = mysqli_prepare($conn, "INSERT INTO alumni (user_id,name,company_name) VALUES (?,?,?)");
            mysqli_stmt_bind_param($s2, "iss", $uid, $name, $company);
            mysqli_stmt_execute($s2);
            mysqli_stmt_close($s2);
            $success = "Registration successful! <a href='../login.php'>Login here</a>";
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
    <meta name="description" content="Alumni Registration — JSS DVH-IMSR Placement Management System">
    <title>Alumni Registration — PMS</title>
    <link rel="stylesheet" href="../assets/pms-theme.css">
    <style>
        .field-error {
            color: #c62828;
            font-size: 11.5px;
            margin-top: 4px;
            display: none;
            align-items: center;
            gap: 4px;
        }
        .field-error.show { display: flex; }
        .form-ctrl.invalid { border-color: #c62828 !important; background: #fff8f8; }
        .form-ctrl.valid   { border-color: #2e7d32 !important; background: #f9fff9; }
    </style>
</head>

<body>
    <div class="auth-page-bg">
        <div class="auth-card">
            <div class="auth-card-accent"></div>
            <div class="auth-card-body">
                <div class="auth-logo-wrap">
                    <img src="../assets/logo.jpg" alt="JSS DVH-IMSR" class="auth-logo-img">
                    <span class="auth-role-badge">🌐 Alumni Registration</span>
                </div>
                <h1 class="auth-title">Alumni Registration</h1>
                <p class="auth-sub">Join the JSS DVH-IMSR Alumni Network</p>

                <?php if ($error): ?>
                    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert-success">✅ <?= $success ?></div><?php endif; ?>

                <?php if (!$success): ?>
                    <form method="POST" id="alumniForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <!-- Full Name -->
                        <div class="form-group-sm">
                            <label class="form-label-sm" for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-ctrl"
                                placeholder="e.g. Priya Sharma"
                                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                maxlength="60" autocomplete="name" required>
                            <div class="field-error" id="err-name">⚠ Name must be 2–60 letters, spaces, hyphens, or apostrophes only.</div>
                        </div>

                        <!-- Email -->
                        <div class="form-group-sm">
                            <label class="form-label-sm" for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-ctrl"
                                placeholder="your@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                autocomplete="email" required>
                            <div class="field-error" id="err-email">⚠ Please enter a valid email address.</div>
                        </div>

                        <!-- Company -->
                        <div class="form-group-sm">
                            <label class="form-label-sm" for="company">Current Company *</label>
                            <input type="text" id="company" name="company" class="form-ctrl"
                                placeholder="e.g. Infosys"
                                value="<?= htmlspecialchars($_POST['company'] ?? '') ?>"
                                maxlength="100" required>
                            <div class="field-error" id="err-company">⚠ Company name must be 2–100 characters (letters, digits, spaces, and . , & - ' / ( ) allowed).</div>
                        </div>

                        <div class="form-section-hd">Password</div>

                        <!-- Password -->
                        <div class="form-group-sm">
                            <label class="form-label-sm" for="pw">Password *</label>
                            <div class="pw-wrap">
                                <input type="password" id="pw" name="password" class="form-ctrl"
                                    placeholder="Min 8 characters" required autocomplete="new-password">
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
                            <div class="field-error" id="err-pw">⚠ Password does not meet all requirements.</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group-sm">
                            <label class="form-label-sm" for="pw2">Confirm Password *</label>
                            <div class="pw-wrap">
                                <input type="password" id="pw2" name="confirm" class="form-ctrl"
                                    placeholder="Repeat password" required autocomplete="new-password">
                                <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)" tabindex="-1">👁</button>
                            </div>
                            <div class="strength-label" id="matchlabel" style="margin-top:6px"></div>
                            <div class="field-error" id="err-pw2">⚠ Passwords do not match.</div>
                        </div>

                        <button type="submit" class="btn-gold"
                            style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:8px">
                            Register →
                        </button>
                    </form>
                <?php endif; ?>
                <div class="auth-link-row" style="margin-top:18px">
                    Already have an account? <a href="../login.php">Login</a>
                </div>

                <script>
                // ── Helpers ──────────────────────────────────────────────
                function showErr(id, show) {
                    document.getElementById(id).classList.toggle('show', show);
                }
                function markField(el, valid) {
                    el.classList.toggle('valid',   valid);
                    el.classList.toggle('invalid', !valid);
                }

                const nameEl    = document.getElementById('name');
                const emailEl   = document.getElementById('email');
                const companyEl = document.getElementById('company');
                const pwEl      = document.getElementById('pw');
                const pw2El     = document.getElementById('pw2');

                function validateName() {
                    const v = nameEl.value.trim();
                    const ok = /^[A-Za-z\s'\-]{2,60}$/.test(v);
                    markField(nameEl, ok);
                    showErr('err-name', !ok && v.length > 0);
                    return ok;
                }

                function validateEmail() {
                    const v = emailEl.value.trim();
                    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
                    markField(emailEl, ok);
                    showErr('err-email', !ok && v.length > 0);
                    return ok;
                }

                function validateCompany() {
                    const v = companyEl.value.trim();
                    const ok = v.length >= 2 && v.length <= 100 && /^[A-Za-z0-9\s\.\,\&\-\'\/\(\)]{2,100}$/.test(v);
                    markField(companyEl, ok);
                    showErr('err-company', !ok && v.length > 0);
                    return ok;
                }

                // ── Password strength ────────────────────────────────────
                const colors = ['#ef5350', '#ff9800', '#ffeb3b', '#4caf50'];
                const strengthLabels = ['Weak', 'Fair', 'Good', 'Strong'];

                function validatePw() {
                    const v = pwEl.value;
                    const reqs = {
                        len:   v.length >= 8,
                        upper: /[A-Z]/.test(v),
                        lower: /[a-z]/.test(v),
                        digit: /[0-9]/.test(v),
                        sym:   /[\W_]/.test(v)
                    };
                    document.getElementById('r-len').classList.toggle('ok',   reqs.len);
                    document.getElementById('r-upper').classList.toggle('ok', reqs.upper);
                    document.getElementById('r-lower').classList.toggle('ok', reqs.lower);
                    document.getElementById('r-digit').classList.toggle('ok', reqs.digit);
                    document.getElementById('r-sym').classList.toggle('ok',   reqs.sym);

                    const score = Object.values(reqs).filter(Boolean).length - 1;
                    ['sb1','sb2','sb3','sb4'].forEach((id, i) => {
                        document.getElementById(id).style.background =
                            v.length === 0 ? '#e0e0e0' : (i <= score ? colors[Math.min(score,3)] : '#e0e0e0');
                    });
                    const lbl = document.getElementById('slabel');
                    if (v.length === 0) { lbl.textContent = ''; lbl.style.color = ''; }
                    else { lbl.textContent = strengthLabels[Math.min(score,3)]; lbl.style.color = colors[Math.min(score,3)]; }

                    const allMet = Object.values(reqs).every(Boolean);
                    markField(pwEl, allMet);
                    showErr('err-pw', !allMet && v.length > 0);
                    validatePw2();
                    return allMet;
                }

                function validatePw2() {
                    const v1 = pwEl.value, v2 = pw2El.value;
                    const ml = document.getElementById('matchlabel');
                    if (!v2) { ml.textContent = ''; showErr('err-pw2', false); return false; }
                    const match = v1 === v2;
                    ml.textContent = match ? '✓ Passwords match' : '✗ Passwords do not match';
                    ml.style.color = match ? '#2e7d32' : '#c62828';
                    markField(pw2El, match);
                    showErr('err-pw2', !match);
                    return match;
                }

                function togglePw(id, btn) {
                    const inp = document.getElementById(id);
                    inp.type = inp.type === 'password' ? 'text' : 'password';
                    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
                }

                // ── Live listeners ───────────────────────────────────────
                nameEl.addEventListener('input',    validateName);
                nameEl.addEventListener('blur',     validateName);
                emailEl.addEventListener('input',   validateEmail);
                emailEl.addEventListener('blur',    validateEmail);
                companyEl.addEventListener('input', validateCompany);
                companyEl.addEventListener('blur',  validateCompany);
                pwEl.addEventListener('input',  validatePw);
                pw2El.addEventListener('input', validatePw2);

                // ── Submit guard ─────────────────────────────────────────
                document.getElementById('alumniForm').addEventListener('submit', function(e) {
                    const ok = [
                        validateName(),
                        validateEmail(),
                        validateCompany(),
                        validatePw(),
                        validatePw2()
                    ].every(Boolean);

                    if (!ok) {
                        e.preventDefault();
                        const firstInvalid = document.querySelector('.form-ctrl.invalid');
                        if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                </script>
            </div>
        </div>
    </div>
</body>

</html>