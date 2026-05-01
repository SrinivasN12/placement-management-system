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

    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm'];
    $batch      = trim($_POST['batch']);
    $spec       = trim($_POST['specialization']);
    $cgpa       = (float)$_POST['cgpa'];
    $backlogs   = (int)$_POST['backlogs'];
    $tenth      = (float)$_POST['tenth_percentage'];
    $twelfth    = (float)$_POST['twelfth_percentage'];
    $deg_cgpa   = (float)$_POST['degree_cgpa'];
    $contact    = trim($_POST['contact_number']);
    $country    = trim($_POST['country']);
    $state      = trim($_POST['state']);
    $city       = trim($_POST['city']);

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($batch) || empty($spec)) {
        $error = "All required fields must be filled.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif ($cgpa < 0 || $cgpa > 10) {
        $error = "MBA CGPA must be between 0 and 10.";
    } elseif ($deg_cgpa < 0 || $deg_cgpa > 10) {
        $error = "Degree CGPA must be between 0 and 10.";
    } elseif ($tenth < 0 || $tenth > 100) {
        $error = "10th percentage must be between 0 and 100.";
    } elseif ($twelfth < 0 || $twelfth > 100) {
        $error = "12th percentage must be between 0 and 100.";
    } elseif (!preg_match('/^\d{10}$/', $contact)) {
        $error = "Contact number must be exactly 10 digits.";
    } else {
        // Duplicate email check
        $chk = mysqli_prepare($conn, "SELECT user_id FROM user WHERE email=?");
        mysqli_stmt_bind_param($chk, "s", $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = "This email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Insert user
            $s1 = mysqli_prepare($conn, "INSERT INTO user (email,password,role_id,status) VALUES (?,?,1,'Active')");
            mysqli_stmt_bind_param($s1, "ss", $email, $hash);
            mysqli_stmt_execute($s1);
            $uid = mysqli_insert_id($conn);
            mysqli_stmt_close($s1);

            // Insert student (department locked to MBA)
            $s2 = mysqli_prepare($conn,
                "INSERT INTO student
                 (user_id,name,department,batch,specialization,cgpa,backlogs,
                  tenth_percentage,twelfth_percentage,degree_cgpa,
                  contact_number,country,state,city,preplacement_status)
                 VALUES (?,?,'MBA',?,?,?,?,?,?,?,?,?,?,?,'Not Ready')");
            mysqli_stmt_bind_param($s2, "isssdidddssss",
                $uid, $name, $batch, $spec, $cgpa, $backlogs,
                $tenth, $twelfth, $deg_cgpa,
                $contact, $country, $state, $city);
            mysqli_stmt_execute($s2);
            mysqli_stmt_close($s2);
            $success = "Registration successful! <a href='../index.php'>Login here</a>";
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
        .card { background: #fff; border-radius: 14px; padding: 36px 40px; width: 100%; max-width: 680px; box-shadow: 0 16px 48px rgba(0,0,0,.3); }
        h2 { color: #1a237e; margin-bottom: 4px; font-size: 20px; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 24px; }
        .section-title { font-size: 12px; font-weight: 700; color: #1a237e; text-transform: uppercase; letter-spacing: .6px; margin: 22px 0 14px; padding-bottom: 6px; border-bottom: 2px solid #e8eaf6; }
        .form-row { display: flex; gap: 14px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #444; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 13px; outline: none; background: #fff; }
        input:focus, select:focus { border-color: #3949ab; }
        .locked { background: #f5f7ff; color: #555; cursor: default; }
        .btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #1a237e, #3949ab); color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn:hover { opacity: .9; }
        .error   { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .login-link { text-align: center; margin-top: 18px; font-size: 13px; color: #666; }
        .login-link a { color: #3949ab; font-weight: 600; text-decoration: none; }
        .hint { font-size: 11px; color: #aaa; margin-top: 2px; }
    </style>
</head>
<body>
<div class="card">
    <h2>🎓 MBA Student Registration</h2>
    <p class="subtitle">Create your PMS account — JSS Shri Manjunatheshwara Institute</p>

    <?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="section-title">Account Details</div>
        <div class="form-row">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" placeholder="e.g. Aditya Kumar" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
        </div>
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

        <div class="section-title">MBA Programme Details</div>
        <div class="form-row">
            <div class="form-group">
                <label>Department</label>
                <input type="text" value="MBA" class="locked" readonly>
            </div>
            <div class="form-group">
                <label>Batch *</label>
                <select name="batch" required>
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($batches as $b): ?>
                    <option value="<?= $b ?>" <?= ($_POST['batch'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Specialization *</label>
                <select name="specialization" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($specializations as $s): ?>
                    <option value="<?= $s ?>" <?= ($_POST['specialization'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>MBA CGPA (current)</label>
                <input type="number" name="cgpa" step="0.01" min="0" max="10" placeholder="e.g. 8.50" value="<?= htmlspecialchars($_POST['cgpa'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Active Backlogs</label>
                <input type="number" name="backlogs" min="0" placeholder="0" value="<?= htmlspecialchars($_POST['backlogs'] ?? '0') ?>">
            </div>
        </div>

        <div class="section-title">Academic Background</div>
        <div class="form-row">
            <div class="form-group">
                <label>10th Percentage * <span class="hint">(0–100)</span></label>
                <input type="number" name="tenth_percentage" step="0.01" min="0" max="100" placeholder="e.g. 82.50" value="<?= htmlspecialchars($_POST['tenth_percentage'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>12th Percentage * <span class="hint">(0–100)</span></label>
                <input type="number" name="twelfth_percentage" step="0.01" min="0" max="100" placeholder="e.g. 78.00" value="<?= htmlspecialchars($_POST['twelfth_percentage'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Degree CGPA * <span class="hint">(0–10)</span></label>
                <input type="number" name="degree_cgpa" step="0.01" min="0" max="10" placeholder="e.g. 7.50" value="<?= htmlspecialchars($_POST['degree_cgpa'] ?? '') ?>" required>
            </div>
        </div>

        <div class="section-title">Contact & Location</div>
        <div class="form-row">
            <div class="form-group">
                <label>Contact Number *</label>
                <input type="tel" name="contact_number" pattern="\d{10}" maxlength="10" placeholder="10-digit mobile" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Country *</label>
                <input type="text" name="country" placeholder="e.g. India" value="<?= htmlspecialchars($_POST['country'] ?? 'India') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>State *</label>
                <input type="text" name="state" placeholder="e.g. Karnataka" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>City *</label>
                <input type="text" name="city" placeholder="e.g. Dharwad" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
            </div>
        </div>

        <button type="submit" class="btn">Register →</button>
    </form>
    <?php endif; ?>
    <div class="login-link">Already have an account? <a href="../index.php">Login</a></div>
</div>
</body>
</html>
