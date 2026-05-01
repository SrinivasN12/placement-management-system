<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 3) { header("Location: ../index.php"); exit(); }
$alumni_id = (int)($_SESSION['alumni_id'] ?? 0);
$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $company  = trim($_POST['company_name']);
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($company)) { $error = "Company name is required."; }
    elseif ($new_pass && $new_pass !== $confirm) { $error = "Passwords do not match."; }
    else {
        $co_esc = mysqli_real_escape_string($conn, $company);
        mysqli_query($conn, "UPDATE alumni SET company_name='$co_esc' WHERE alumni_id=$alumni_id");
        if ($new_pass) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $uid  = (int)$_SESSION['user_id'];
            mysqli_query($conn, "UPDATE user SET password='".mysqli_real_escape_string($conn,$hash)."' WHERE user_id=$uid");
        }
        $success = "Profile updated successfully!";
    }
}

$a = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT a.*, u.email,
            (SELECT COUNT(*) FROM alumni_recommendation WHERE alumni_id=a.alumni_id) AS total_recs
     FROM alumni a JOIN user u ON a.user_id=u.user_id WHERE a.alumni_id=$alumni_id"));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Profile — PMS Alumni</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#4a148c;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.content{padding:32px;max-width:680px;margin:0 auto}
h2{color:#4a148c;margin-bottom:6px;font-size:20px}
.sub{color:#666;font-size:13px;margin-bottom:24px}
.card{background:#fff;border-radius:12px;padding:24px 28px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
.card h3{font-size:15px;color:#4a148c;margin-bottom:14px}
.info-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f5f5f5;font-size:13px}
.info-row:last-child{border-bottom:none}
.info-row .lbl{color:#888}.info-row .val{color:#333;font-weight:600}
.kpi{text-align:center;padding:16px;background:#f3e5f5;border-radius:10px}
.kpi .num{font-size:32px;font-weight:700;color:#4a148c}.kpi .lbl{font-size:12px;color:#888;margin-top:3px}
.form-group{margin-bottom:16px}
label{display:block;font-size:13px;font-weight:600;color:#444;margin-bottom:5px}
input{width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;outline:none}
input:focus{border-color:#4a148c}
.btn{background:#4a148c;color:#fff;border:none;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#6a1b9a}
.success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.back{display:inline-block;margin-bottom:18px;color:#4a148c;text-decoration:none;font-size:13px;font-weight:600}
@media(max-width:600px){
  .navbar{padding:12px 16px;}
  .content{padding:20px 16px;}
  .card{padding:18px 16px;}
  div[style*='display:flex']{flex-direction:column;}
  .btn{width:100%;}
}
</style></head><body>
<div class="navbar"><h1>🌐 PMS — Alumni Portal</h1><a href="../logout.php">Logout</a></div>
<div class="content">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<h2>👤 My Profile</h2>
<p class="sub">Your alumni account information.</p>
<?php if ($success): ?><div class="success">✅ <?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="kpi"><div class="num"><?= $a['total_recs'] ?></div><div class="lbl">Jobs Recommended to Students</div></div>
<br>
<div class="card">
  <h3>Account Information</h3>
  <div class="info-row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($a['name']) ?></span></div>
  <div class="info-row"><span class="lbl">Email</span><span class="val"><?= htmlspecialchars($a['email']) ?></span></div>
  <div class="info-row"><span class="lbl">Current Company</span><span class="val"><?= htmlspecialchars($a['company_name']) ?></span></div>
</div>
<div class="card">
  <h3>Edit Profile</h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="form-group">
      <label>Current Company</label>
      <input type="text" name="company_name" value="<?= htmlspecialchars($a['company_name']) ?>" required>
    </div>
    <div style="display:flex;gap:16px">
      <div class="form-group" style="flex:1">
        <label>New Password <span style="font-weight:400;color:#aaa">(leave blank to keep)</span></label>
        <input type="password" name="new_password" placeholder="New password">
      </div>
      <div class="form-group" style="flex:1">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Repeat password">
      </div>
    </div>
    <button type="submit" class="btn">Save Changes</button>
  </form>
</div>
</div></body></html>
