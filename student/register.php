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
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
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
    <title>Student Registration — MBA PMS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #1a237e, #3949ab); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px; }
        .card { background: #fff; border-radius: 14px; padding: 36px 40px; width: 100%; max-width: 560px; box-shadow: 0 16px 48px rgba(0,0,0,.3); }
        h2 { color: #1a237e; margin-bottom: 4px; font-size: 20px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 24px; }
        .section-title { font-size: 12px; font-weight: 700; color: #1a237e; text-transform: uppercase; letter-spacing: .6px; margin: 22px 0 14px; padding-bottom: 6px; border-bottom: 2px solid #e8eaf6; }
        .form-row { display: flex; gap: 14px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #444; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 13px; outline: none; background: #fff; }
        input:focus, select:focus { border-color: #3949ab; }
        .btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #1a237e, #3949ab); color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn:hover { opacity: .9; }
        .error   { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .login-link { text-align: center; margin-top: 18px; font-size: 13px; color: #666; }
        .login-link a { color: #3949ab; font-weight: 600; text-decoration: none; }
        .hint { font-size: 11px; color: #aaa; margin-top: 2px; }
        /* ── UUCMS shared style ── */
        .uucms-val {
          font-family: 'Courier New', Courier, monospace;
          font-weight: 700;
          font-size: 15px;
          letter-spacing: 1.2px;
          color: #1a237e;
          text-transform: uppercase;
        }
        /* ── Mobile ── */
        @media(max-width:600px) {
            body { padding: 0; align-items: flex-start; }
            .card {
                border-radius: 0;
                min-height: 100vh;
                padding: 32px 20px 40px;
                box-shadow: none;
                max-width: 100%;
            }
            .form-row { flex-direction: column; gap: 0; }
            .btn { font-size: 16px; padding: 14px; }
        }
    </style>
</head>
<body>
<div class="card">
    <h2>🎓 MBA Student Registration</h2>
    <p class="subtitle">Create your PMS account — JSS Shri Manjunatheshwara Institute</p>

    <?php if ($error): ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="section-title">Account Details</div>
        <div class="form-row">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" placeholder="e.g. Aditya Kumar"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label>UUCMS Number * <span class="hint">(University Unique Candidate Management System ID)</span></label>
            <input type="text" name="uucms_number" class="uucms-val" placeholder="e.g. UUCMS2024001"
                value="<?= htmlspecialchars(strtoupper($_POST['uucms_number'] ?? '')) ?>" required>
        </div>

        <div class="section-title">MBA Programme</div>
        <div class="form-row">
            <div class="form-group">
                <label>Batch *</label>
                <select name="batch" required>
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($batches as $b): ?>
                    <option value="<?= $b ?>" <?= ($_POST['batch'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Specialization *</label>
                <select name="specialization" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($specializations as $s): ?>
                    <option value="<?= $s ?>" <?= ($_POST['specialization'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="section-title">Password</div>
        <div class="form-row">
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Min 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm" placeholder="Repeat password" required>
            </div>
        </div>

        <button type="submit" class="btn">Register →</button>
    </form>
    <?php endif; ?>
    <div class="login-link">Already have an account? <a href="../index.php">Login</a></div>
</div>
</body>
</html>
