<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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
        http_response_code(403); exit('403 Forbidden — CSRF mismatch.');
    }

    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];
    $uucms    = strtoupper(trim($_POST['uucms_number']));
    $batch    = trim($_POST['batch']);
    $spec     = trim($_POST['specialization']);

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($batch) || empty($spec)) {
        $error = "All required fields must be filled.";
    } elseif (empty($uucms)) {
        $error = "UUCMS number is required.";
    } elseif (!preg_match('/^[A-Z0-9\-\/]+$/', $uucms)) {
        $error = "UUCMS number may only contain letters, digits, hyphens, and slashes.";
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
                // Insert user — status 'Pending' until officer approves
                $s1 = mysqli_prepare($conn, "INSERT INTO user (email,password,role_id,status) VALUES (?,?,1,'Pending')");
                mysqli_stmt_bind_param($s1, "ss", $email, $hash);
                mysqli_stmt_execute($s1);
                $uid = mysqli_insert_id($conn);
                mysqli_stmt_close($s1);

                // Insert student — remaining fields use column defaults (filled later via profile)
                $s2 = mysqli_prepare($conn,
                    "INSERT INTO student (user_id,uucms_number,name,department,batch,specialization,preplacement_status)
                     VALUES (?,?,?,'MBA',?,?,'Not Ready')");
                mysqli_stmt_bind_param($s2, "issss", $uid, $uucms, $name, $batch, $spec);
                mysqli_stmt_execute($s2);
                mysqli_stmt_close($s2);
                $success = "Registration submitted! Your account is pending approval by the Placement Officer. You will be able to login once approved.";
            }
        }
        mysqli_stmt_close($chk);
    }
}

$specializations = mbaSpecializations();
$batches         = mbaBatches();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="MBA Student Registration — JSS DVH-IMSR Placement Management System">
<title>Student Registration — MBA PMS</title>
<link rel="stylesheet" href="../assets/pms-theme.css">
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

    <?php if ($error): ?><div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-section-hd">Account Details</div>
        <div class="form-row-2">
            <div class="form-group-sm">
                <label class="form-label-sm">Full Name *</label>
                <input type="text" name="name" class="form-ctrl" placeholder="e.g. Aditya Kumar"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group-sm">
                <label class="form-label-sm">Email Address *</label>
                <input type="email" name="email" class="form-ctrl" placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-group-sm">
            <label class="form-label-sm">UUCMS Number * <span style="font-size:10px;text-transform:none;font-weight:400">(University Unique Candidate Management System ID)</span></label>
            <input type="text" name="uucms_number" class="form-ctrl uucms-val" placeholder="e.g. UUCMS2024001"
                value="<?= htmlspecialchars(strtoupper($_POST['uucms_number'] ?? '')) ?>" required>
        </div>

        <div class="form-section-hd">MBA Programme</div>
        <div class="form-row-2">
            <div class="form-group-sm">
                <label class="form-label-sm">Batch *</label>
                <select name="batch" class="form-ctrl" required>
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($batches as $b): ?>
                    <option value="<?= $b ?>" <?= ($_POST['batch'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-sm">
                <label class="form-label-sm">Specialization *</label>
                <select name="specialization" class="form-ctrl" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($specializations as $s): ?>
                    <option value="<?= $s ?>" <?= ($_POST['specialization'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-section-hd">Password</div>
        <div class="form-row-2">
            <div class="form-group-sm">
                <label class="form-label-sm">Password *</label>
                <div class="pw-wrap">
                    <input type="password" id="pw" name="password" class="form-ctrl" placeholder="Min 8 characters" required autocomplete="new-password">
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
            </div>
            <div class="form-group-sm">
                <label class="form-label-sm">Confirm Password *</label>
                <div class="pw-wrap">
                    <input type="password" id="pw2" name="confirm" class="form-ctrl" placeholder="Repeat password" required autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)" tabindex="-1">👁</button>
                </div>
                <div class="strength-label" id="matchlabel" style="margin-top:6px"></div>
            </div>
        </div>

        <button type="submit" class="btn-gold" id="submitBtn" style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:10px">Register →</button>
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
    // toggle requirement items
    document.getElementById('r-len').classList.toggle('ok', reqs.len);
    document.getElementById('r-upper').classList.toggle('ok', reqs.upper);
    document.getElementById('r-lower').classList.toggle('ok', reqs.lower);
    document.getElementById('r-digit').classList.toggle('ok', reqs.digit);
    document.getElementById('r-sym').classList.toggle('ok', reqs.sym);
    // strength score
    const score = Object.values(reqs).filter(Boolean).length - 1; // 0..4
    const bars = ['sb1','sb2','sb3','sb4'];
    bars.forEach((id,i) => {
        document.getElementById(id).style.background = (v.length === 0) ? '#e0e0e0' : (i <= score ? colors[Math.min(score,3)] : '#e0e0e0');
    });
    const lbl = document.getElementById('slabel');
    if (v.length === 0) { lbl.textContent=''; lbl.style.color=''; }
    else { lbl.textContent = labels[Math.min(score,3)]; lbl.style.color = colors[Math.min(score,3)]; }
    checkMatch();
}
function checkMatch() {
    const v1 = document.getElementById('pw').value;
    const v2 = document.getElementById('pw2').value;
    const ml = document.getElementById('matchlabel');
    if (!v2) { ml.textContent=''; return; }
    if (v1 === v2) { ml.textContent='✓ Passwords match'; ml.style.color='#2e7d32'; }
    else           { ml.textContent='✗ Passwords do not match'; ml.style.color='#c62828'; }
}
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
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
</div>
</div>
</body>
</html>
