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
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $company = trim($_POST['company']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if (empty($name) || empty($email) || empty($company) || empty($password)) {
        $error = "All fields are required.";
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
    <link rel="stylesheet" href="../assets/pms-theme.css?v=<?= time() ?>">
    <style>
        /* Form Validation States */
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 5px;
            font-size: 11px;
            color: #ef5350;
            font-weight: 500;
            line-height: 1.4;
            text-transform: none;
        }
        .form-ctrl.is-invalid ~ .invalid-feedback,
        .pw-wrap.is-invalid ~ .invalid-feedback {
            display: block;
        }
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
                    <form method="POST" id="registerForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="form-group-sm">
                            <label class="form-label-sm">Full Name</label>
                            <input type="text" id="name" name="name" class="form-ctrl" placeholder="e.g. Priya Sharma"
                                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                            <div class="invalid-feedback">Full name is required (minimum 3 characters, letters and spaces only).</div>
                        </div>
                        <div class="form-group-sm">
                            <label class="form-label-sm">Email Address</label>
                            <input type="email" id="email" name="email" class="form-ctrl" placeholder="your@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="form-group-sm">
                            <label class="form-label-sm">Current Company</label>
                            <input type="text" id="company" name="company" class="form-ctrl" placeholder="e.g. Infosys"
                                value="<?= htmlspecialchars($_POST['company'] ?? '') ?>" required>
                            <div class="invalid-feedback">Company name is required (minimum 2 characters).</div>
                        </div>
                        <div class="form-section-hd">Password</div>
                        <div class="form-group-sm">
                            <label class="form-label-sm">Password</label>
                            <div class="pw-wrap" id="pw-wrap">
                                <input type="password" id="pw" name="password" class="form-ctrl"
                                    placeholder="Min 8 characters" required autocomplete="new-password">
                                <button type="button" class="pw-toggle" onclick="togglePw('pw',this)"
                                    tabindex="-1" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password must be at least 8 characters and include uppercase, lowercase, digit, and special char.</div>
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
                        <div class="form-group-sm">
                            <label class="form-label-sm">Confirm Password</label>
                            <div class="pw-wrap" id="pw2-wrap">
                                <input type="password" id="pw2" name="confirm" class="form-ctrl"
                                    placeholder="Repeat password" required autocomplete="new-password">
                                <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)"
                                    tabindex="-1" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <div class="invalid-feedback">Passwords do not match.</div>
                            <div class="strength-label" id="matchlabel" style="margin-top:6px"></div>
                        </div>
                        <button type="submit" class="btn-gold"
                            style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:8px">Register
                            →</button>
                    </form>
                <?php endif; ?>
                <div class="auth-link-row" style="margin-top:18px">Already have an account? <a href="../login.php">Login</a></div>

                <script>
                    const colors = ['#ef5350', '#ff9800', '#ffeb3b', '#4caf50'];
                    const labels = ['Weak', 'Fair', 'Good', 'Strong'];

                    // Validation functions
                    const validators = {
                        name: (val) => val.trim().length >= 3 && /^[A-Za-z\s.]+$/.test(val),
                        email: (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val),
                        company: (val) => val.trim().length >= 2,
                        pw: (val) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(val),
                        pw2: (val) => val === document.getElementById('pw').value
                    };

                    function validateField(inputEl, feedbackEl, validatorFn, wrapperEl = null) {
                        const isValid = validatorFn(inputEl.value);
                        if (isValid) {
                            inputEl.classList.remove('is-invalid');
                            inputEl.classList.add('is-valid');
                            if (wrapperEl) {
                                wrapperEl.classList.remove('is-invalid');
                                wrapperEl.classList.add('is-valid');
                            }
                        } else {
                            inputEl.classList.remove('is-valid');
                            inputEl.classList.add('is-invalid');
                            if (wrapperEl) {
                                wrapperEl.classList.remove('is-valid');
                                wrapperEl.classList.add('is-invalid');
                            }
                        }
                        return isValid;
                    }

                    // Attach events to fields
                    const nameInp = document.getElementById('name');
                    const emailInp = document.getElementById('email');
                    const companyInp = document.getElementById('company');
                    const pwInp = document.getElementById('pw');
                    const pw2Inp = document.getElementById('pw2');

                    const pwWrap = document.getElementById('pw-wrap');
                    const pw2Wrap = document.getElementById('pw2-wrap');

                    nameInp.addEventListener('input', () => validateField(nameInp, null, validators.name));
                    nameInp.addEventListener('blur', () => validateField(nameInp, null, validators.name));

                    emailInp.addEventListener('input', () => validateField(emailInp, null, validators.email));
                    emailInp.addEventListener('blur', () => validateField(emailInp, null, validators.email));

                    companyInp.addEventListener('input', () => validateField(companyInp, null, validators.company));
                    companyInp.addEventListener('blur', () => validateField(companyInp, null, validators.company));

                    function checkPw() {
                        const v = pwInp.value;
                        const reqs = {
                            len: v.length >= 8,
                            upper: /[A-Z]/.test(v),
                            lower: /[a-z]/.test(v),
                            digit: /[0-9]/.test(v),
                            sym: /[\W_]/.test(v)
                        };
                        document.getElementById('r-len').classList.toggle('ok', reqs.len);
                        document.getElementById('r-upper').classList.toggle('ok', reqs.upper);
                        document.getElementById('r-lower').classList.toggle('ok', reqs.lower);
                        document.getElementById('r-digit').classList.toggle('ok', reqs.digit);
                        document.getElementById('r-sym').classList.toggle('ok', reqs.sym);
                        const score = Object.values(reqs).filter(Boolean).length - 1;
                        ['sb1', 'sb2', 'sb3', 'sb4'].forEach((id, i) => {
                            document.getElementById(id).style.background = (v.length === 0) ? '#e0e0e0' : (i <= score ? colors[Math.min(score, 3)] : '#e0e0e0');
                        });
                        const lbl = document.getElementById('slabel');
                        if (v.length === 0) { lbl.textContent = ''; lbl.style.color = ''; }
                        else { lbl.textContent = labels[Math.min(score, 3)]; lbl.style.color = colors[Math.min(score, 3)]; }
                        
                        validateField(pwInp, null, validators.pw, pwWrap);
                        checkMatch();
                    }

                    function checkMatch() {
                        const v1 = pwInp.value;
                        const v2 = pw2Inp.value;
                        const ml = document.getElementById('matchlabel');
                        if (!v2) {
                            ml.textContent = '';
                            pw2Inp.classList.remove('is-invalid', 'is-valid');
                            pw2Wrap.classList.remove('is-invalid', 'is-valid');
                            return;
                        }
                        if (v1 === v2) {
                            ml.textContent = '✓ Passwords match';
                            ml.style.color = '#2e7d32';
                            pw2Inp.classList.remove('is-invalid');
                            pw2Inp.classList.add('is-valid');
                            pw2Wrap.classList.remove('is-invalid');
                            pw2Wrap.classList.add('is-valid');
                        } else {
                            ml.textContent = '✗ Passwords do not match';
                            ml.style.color = '#c62828';
                            pw2Inp.classList.remove('is-valid');
                            pw2Inp.classList.add('is-invalid');
                            pw2Wrap.classList.remove('is-valid');
                            pw2Wrap.classList.add('is-invalid');
                        }
                    }

                    const EYE_SHOW = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
                    const EYE_HIDE = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
                    function togglePw(id, btn) {
                        const inp = document.getElementById(id);
                        inp.type = inp.type === 'password' ? 'text' : 'password';
                        btn.innerHTML = inp.type === 'password' ? EYE_SHOW : EYE_HIDE;
                    }

                    pwInp.addEventListener('input', checkPw);
                    pw2Inp.addEventListener('input', checkMatch);

                    document.getElementById('registerForm').addEventListener('submit', function (e) {
                        const isNameValid = validateField(nameInp, null, validators.name);
                        const isEmailValid = validateField(emailInp, null, validators.email);
                        const isCompanyValid = validateField(companyInp, null, validators.company);
                        const isPwValid = validateField(pwInp, null, validators.pw, pwWrap);
                        
                        checkMatch();
                        const isPw2Valid = (pwInp.value === pw2Inp.value) && (pw2Inp.value !== '');
                        if (!isPw2Valid) {
                            pw2Inp.classList.add('is-invalid');
                            pw2Wrap.classList.add('is-invalid');
                        }

                        if (!isNameValid || !isEmailValid || !isCompanyValid || !isPwValid || !isPw2Valid) {
                            e.preventDefault();
                            
                            // Focus first invalid input
                            const firstInvalid = document.querySelector('.form-ctrl.is-invalid');
                            if (firstInvalid) {
                                firstInvalid.focus();
                            }
                        }
                    });
                </script>
            </div>
        </div>
    </div>
</body>

</html>
