<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once '../config/db.php';
require_once '../config/db_helpers.php';

// CSRF for pre-auth pages
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('403 Forbidden — CSRF mismatch.');
    }

    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $password = $_POST['password']     ?? '';
    $confirm  = $_POST['confirm']      ?? '';
    $uucms   = strtoupper(trim($_POST['uucms_number'] ?? ''));
    $batch   = trim($_POST['batch']    ?? '');
    $spec    = trim($_POST['specialization'] ?? '');

    // ── Server-side validation ────────────────────────────────────
    if (empty($name) || empty($email) || empty($password) || empty($confirm) || empty($batch) || empty($spec)) {
        $error = "All required fields must be filled.";
    } elseif (!preg_match('/^[A-Za-z\s\'\-]{2,60}$/', $name)) {
        $error = "Full name must be 2–60 characters and contain only letters, spaces, hyphens, or apostrophes.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($uucms)) {
        $error = "UUCMS number is required.";
    } elseif (!preg_match('/^[A-Z0-9\-\/]{4,30}$/', $uucms)) {
        $error = "UUCMS number must be 4–30 characters and contain only letters, digits, hyphens, or slashes.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character (e.g. @, #, !).";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Duplicate email check
        $chk = mysqli_prepare($conn, "SELECT user_id FROM user WHERE email=?");
        mysqli_stmt_bind_param($chk, "s", $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = "This email is already registered.";
        } else {
            // Duplicate UUCMS check
            $chku = mysqli_prepare($conn, "SELECT student_id FROM student WHERE uucms_number=?");
            mysqli_stmt_bind_param($chku, "s", $uucms);
            mysqli_stmt_execute($chku);
            mysqli_stmt_store_result($chku);
            $uucms_exists = mysqli_stmt_num_rows($chku) > 0;
            mysqli_stmt_close($chku);
            if ($uucms_exists) {
                $error = "This UUCMS number is already registered.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $s1 = mysqli_prepare($conn, "INSERT INTO user (email,password,role_id,status) VALUES (?,?,1,'Pending')");
                mysqli_stmt_bind_param($s1, "ss", $email, $hash);
                mysqli_stmt_execute($s1);
                $uid = mysqli_insert_id($conn);
                mysqli_stmt_close($s1);

                $s2 = mysqli_prepare(
                    $conn,
                    "INSERT INTO student (user_id,uucms_number,name,department,batch,specialization,preplacement_status)
                     VALUES (?,?,?,'MBA',?,?,'Not Ready')"
                );
                mysqli_stmt_bind_param($s2, "issss", $uid, $uucms, $name, $batch, $spec);
                mysqli_stmt_execute($s2);
                mysqli_stmt_close($s2);
                $success = "Registration submitted! Your account is pending approval from the Placement Officer. You will be able to login once approved.";
            }
        }
        mysqli_stmt_close($chk);
    }
}

$specializations = mbaSpecializations();
$batches = mbaBatches();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MBA Student Registration — JSS DVH-IMSR Placement Management System">
    <title>Student Registration — MBA PMS</title>
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
        .field-ok {
            color: #2e7d32;
            font-size: 11px;
            margin-top: 4px;
            display: none;
        }
        .field-ok.show { display: block; }
    </style>
</head>

<body>
    <div class="auth-page-bg">
        <div class="auth-card" style="max-width:580px">
            <div class="auth-card-accent"></div>
            <div class="auth-card-body">
                <div class="auth-logo-wrap">
                    <img src="../assets/logo.jpg" alt="JSS DVH-IMSR" class="auth-logo-img">
                    <span class="auth-role-badge">🎓 Student Registration</span>
                </div>
                <h1 class="auth-title">MBA Student Registration</h1>
                <p class="auth-sub">Create your PMS account — JSS DVH-IMSR College</p>

                <?php if ($error): ?>
                    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

                <?php if (!$success): ?>
                    <form method="POST" id="regForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="form-section-hd">Account Details</div>
                        <div class="form-row-2">
                            <!-- Full Name -->
                            <div class="form-group-sm">
                                <label class="form-label-sm" for="name">Full Name *</label>
                                <input type="text" id="name" name="name" class="form-ctrl"
                                    placeholder="e.g. Aditya Kumar"
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
                        </div>

                        <!-- UUCMS -->
                        <div class="form-group-sm">
                            <label class="form-label-sm" for="uucms_number">UUCMS Number *
                                <span style="font-size:10px;text-transform:none;font-weight:400">
                                    (University Unique Candidate Management System ID)
                                </span>
                            </label>
                            <input type="text" id="uucms_number" name="uucms_number" class="form-ctrl uucms-val"
                                placeholder="e.g. UUCMS2024001"
                                value="<?= htmlspecialchars(strtoupper($_POST['uucms_number'] ?? '')) ?>"
                                maxlength="30" style="text-transform:uppercase" required>
                            <div class="field-error" id="err-uucms">⚠ UUCMS number must be 4–30 characters: letters, digits, hyphens, or slashes only.</div>
                        </div>

                        <div class="form-section-hd">MBA Programme</div>
                        <div class="form-row-2">
                            <!-- Batch -->
                            <div class="form-group-sm">
                                <label class="form-label-sm" for="batch">Batch *</label>
                                <select id="batch" name="batch" class="form-ctrl" required>
                                    <option value="">-- Select Batch --</option>
                                    <?php foreach ($batches as $b): ?>
                                        <option value="<?= $b ?>" <?= ($_POST['batch'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-error" id="err-batch">⚠ Please select your batch.</div>
                            </div>
                            <!-- Specialization -->
                            <div class="form-group-sm">
                                <label class="form-label-sm" for="specialization">Specialization *</label>
                                <select id="specialization" name="specialization" class="form-ctrl" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($specializations as $s): ?>
                                        <option value="<?= $s ?>" <?= ($_POST['specialization'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-error" id="err-spec">⚠ Please select your specialization.</div>
                            </div>
                        </div>

                        <div class="form-section-hd">Password</div>
                        <div class="form-row-2">
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
                                <ul class="req-list" id="reqs">
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
                        </div>

                        <button type="submit" class="btn-gold" id="submitBtn"
                            style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:10px">
                            Register →
                        </button>
                    </form>
                <?php endif; ?>
                <div class="auth-link-row">Already have an account? <a href="../login.php">Login</a></div>

                <script>
                // ── Helpers ──────────────────────────────────────────────
                function showErr(id, show) {
                    document.getElementById(id).classList.toggle('show', show);
                }
                function markField(el, valid) {
                    el.classList.toggle('valid',   valid);
                    el.classList.toggle('invalid', !valid);
                }

                // ── Field rules ─────────────────────────────────────────
                const nameEl  = document.getElementById('name');
                const emailEl = document.getElementById('email');
                const uucmsEl = document.getElementById('uucms_number');
                const batchEl = document.getElementById('batch');
                const specEl  = document.getElementById('specialization');
                const pwEl    = document.getElementById('pw');
                const pw2El   = document.getElementById('pw2');

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

                function validateUucms() {
                    const v = uucmsEl.value.trim().toUpperCase();
                    uucmsEl.value = v;
                    const ok = /^[A-Z0-9\-\/]{4,30}$/.test(v);
                    markField(uucmsEl, ok);
                    showErr('err-uucms', !ok && v.length > 0);
                    return ok;
                }

                function validateBatch() {
                    const ok = batchEl.value !== '';
                    markField(batchEl, ok);
                    showErr('err-batch', !ok);
                    return ok;
                }

                function validateSpec() {
                    const ok = specEl.value !== '';
                    markField(specEl, ok);
                    showErr('err-spec', !ok);
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
                nameEl.addEventListener('input',  validateName);
                nameEl.addEventListener('blur',   validateName);
                emailEl.addEventListener('input', validateEmail);
                emailEl.addEventListener('blur',  validateEmail);
                uucmsEl.addEventListener('input', validateUucms);
                uucmsEl.addEventListener('blur',  validateUucms);
                batchEl.addEventListener('change', validateBatch);
                specEl.addEventListener('change',  validateSpec);
                pwEl.addEventListener('input',  validatePw);
                pw2El.addEventListener('input', validatePw2);

                // ── Submit guard ─────────────────────────────────────────
                document.getElementById('regForm').addEventListener('submit', function(e) {
                    const ok = [
                        validateName(),
                        validateEmail(),
                        validateUucms(),
                        validateBatch(),
                        validateSpec(),
                        validatePw(),
                        validatePw2()
                    ].every(Boolean);

                    if (!ok) {
                        e.preventDefault();
                        // Scroll to first error
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