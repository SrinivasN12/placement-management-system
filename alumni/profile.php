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

$pageTitle  = 'My Profile';
$activePage = 'profile';
require '_layout.php';
?>

<style>
.profile-container {
  max-width: 800px;
  margin: 0 auto;
}
.info-row {
  display: flex;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
  font-size: 14px;
}
.info-row:last-child {
  border-bottom: none;
}
.info-row .lbl {
  color: var(--muted);
  font-weight: 500;
}
.info-row .val {
  color: var(--text);
  font-weight: 600;
}
.kpi-card {
  background: var(--accent-soft);
  color: var(--primary);
  border-radius: var(--card-radius);
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}
.kpi-icon {
  width: 52px; height: 52px;
  border-radius: 12px;
  background: rgba(74, 20, 140, 0.12);
  display: flex; align-items: center; justify-content: center;
  color: var(--primary);
}
.kpi-icon .material-icons-round { font-size: 28px; }
.kpi-val { font-size: 26px; font-weight: 800; }
.kpi-lbl { font-size: 12px; color: var(--muted); font-weight: 500; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}
.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
}
.form-group-full {
  grid-column: span 2;
}

@media(max-width: 600px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .form-group-full {
    grid-column: span 1;
  }
}
</style>

<div class="profile-container">
  <div class="page-header">
    <h1>👤 My Profile</h1>
    <p>Manage your alumni partner account information.</p>
  </div>

  <?php if ($success): ?>
    <div class="success"><span class="material-icons-round">check_circle</span><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="error"><span class="material-icons-round">error</span><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="kpi-card">
    <div class="kpi-icon"><span class="material-icons-round">list_alt</span></div>
    <div>
      <div class="kpi-val"><?= (int)$a['total_recs'] ?></div>
      <div class="kpi-lbl">Jobs Recommended to MBA Students</div>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span class="material-icons-round">info</span>Account Information</div>
    <div class="info-row">
      <span class="lbl">Name</span>
      <span class="val"><?= htmlspecialchars($a['name']) ?></span>
    </div>
    <div class="info-row">
      <span class="lbl">Email Address</span>
      <span class="val"><?= htmlspecialchars($a['email']) ?></span>
    </div>
    <div class="info-row">
      <span class="lbl">Current Company</span>
      <span class="val"><?= htmlspecialchars($a['company_name']) ?></span>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span class="material-icons-round">edit</span>Edit Profile Details</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      
      <div class="form-group">
        <label>Current Company</label>
        <input type="text" name="company_name" value="<?= htmlspecialchars($a['company_name']) ?>" required>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label>New Password <span style="font-weight:400;color:var(--muted)">(leave blank to keep)</span></label>
          <input type="password" name="new_password" placeholder="New password">
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" placeholder="Repeat password">
        </div>
      </div>

      <button type="submit" class="btn">
        <span class="material-icons-round">save</span>
        Save Changes
      </button>
    </form>
  </div>
</div>

<?php require '_layout_end.php'; ?>
