<?php
require_once '../config/auth.php';
require_once '../config/db.php';
if ($role_id != 2) { header("Location: ../index.php"); exit(); }

$officer_id = (int)($_SESSION['officer_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id']    ?? 0);
$success = $error = "";

// ── Load officer + user data ───────────────────────────────────
$officer = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT o.*, u.email
     FROM placement_officer o
     JOIN user u ON o.user_id = u.user_id
     WHERE o.officer_id = $officer_id"));

if (!$officer) {
    // Fallback: load from user only
    $officer = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT *, '' AS department, '' AS phone FROM user WHERE user_id=$user_id"));
}

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        // ── Update profile info ────────────────────────────────
        $new_name   = trim($_POST['name']);
        $new_email  = trim($_POST['email']);
        $new_dept   = trim($_POST['department'] ?? '');
        $new_phone  = trim($_POST['phone'] ?? '');

        if (strlen($new_name) < 2) {
            $error = "Name must be at least 2 characters.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check email not taken by another user
            $esc_email = mysqli_real_escape_string($conn, $new_email);
            $dup = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT user_id FROM user WHERE email='$esc_email' AND user_id != $user_id"));
            if ($dup) {
                $error = "That email address is already in use by another account.";
            } else {
                $esc_name  = mysqli_real_escape_string($conn, $new_name);
                $esc_dept  = mysqli_real_escape_string($conn, $new_dept);
                $esc_phone = mysqli_real_escape_string($conn, $new_phone);

                // Update user table — email only (user has no name column)
                mysqli_query($conn,
                    "UPDATE user SET email='$esc_email' WHERE user_id=$user_id");

                // Update placement_officer — name, department, phone
                mysqli_query($conn,
                    "UPDATE placement_officer
                     SET name='$esc_name', department='$esc_dept', phone='$esc_phone'
                     WHERE officer_id=$officer_id");

                // Refresh session
                $_SESSION['name']  = $new_name;
                $_SESSION['email'] = $new_email;

                $success = "Profile updated successfully!";

                // Reload to show fresh values
                $officer = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT o.*, u.email
                     FROM placement_officer o
                     JOIN user u ON o.user_id = u.user_id
                     WHERE o.officer_id = $officer_id"));
            }
        }

    } elseif ($action === 'password') {
        // ── Change password ────────────────────────────────────
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        // Load current hash
        $u = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT password FROM user WHERE user_id=$user_id"));

        if (!password_verify($cur, $u['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new !== $conf) {
            $error = "New passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $esc  = mysqli_real_escape_string($conn, $hash);
            mysqli_query($conn, "UPDATE user SET password='$esc' WHERE user_id=$user_id");
            $success = "Password changed successfully!";
        }
    }
}

// Check if department/phone columns exist in placement_officer
$has_dept  = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM placement_officer LIKE 'department'")) > 0;
$has_phone = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM placement_officer LIKE 'phone'")) > 0;

// Add columns if missing (safe one-time migration)
if (!$has_dept) {
    mysqli_query($conn, "ALTER TABLE placement_officer ADD COLUMN department VARCHAR(120) DEFAULT ''");
    $has_dept = true;
}
if (!$has_phone) {
    mysqli_query($conn, "ALTER TABLE placement_officer ADD COLUMN phone VARCHAR(30) DEFAULT ''");
    $has_phone = true;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — TPO Portal</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5}
.navbar{background:#1b5e20;color:#fff;padding:14px 32px;display:flex;justify-content:space-between;align-items:center}
.navbar h1{font-size:18px}.navbar a{color:#fff;text-decoration:none;font-size:13px}
.page-wrap{max-width:860px;margin:0 auto;padding:32px 24px}
.back{display:inline-block;margin-bottom:20px;color:#1b5e20;text-decoration:none;font-size:13px;font-weight:600}

/* Profile hero */
.profile-hero{background:linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#388e3c 100%);border-radius:16px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;gap:24px;position:relative;overflow:hidden}
.profile-hero::after{content:'⚙';position:absolute;right:24px;top:50%;transform:translateY(-50%);font-size:100px;opacity:.06;pointer-events:none}
.avatar-circle{width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.25);border:3px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:800;color:#fff;flex-shrink:0}
.hero-info h2{color:#fff;font-size:20px;font-weight:800;margin-bottom:3px}
.hero-info p{color:rgba(255,255,255,.75);font-size:13px}
.hero-badge{display:inline-block;background:rgba(255,255,255,.2);color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;margin-top:6px}

/* Tabs */
.tabs{display:flex;gap:0;margin-bottom:20px;background:#fff;border-radius:12px;padding:5px;box-shadow:0 2px 8px rgba(0,0,0,.07)}
.tab-btn{flex:1;padding:10px 16px;border:none;background:none;border-radius:9px;font-size:13px;font-weight:600;color:#555;cursor:pointer;transition:all .15s}
.tab-btn.active{background:#1b5e20;color:#fff}
.tab-btn:hover:not(.active){background:#f5f5f5}

/* Cards */
.card{background:#fff;border-radius:14px;padding:26px 30px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
.card h3{font-size:15px;font-weight:700;color:#1b5e20;margin-bottom:18px;padding-bottom:10px;border-bottom:2px solid #e8f5e9;display:flex;align-items:center;gap:8px}

/* Form */
.form-row{display:flex;gap:16px;margin-bottom:0}
.form-row .form-group{flex:1}
.form-group{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:700;color:#555;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
input[type=text],input[type=email],input[type=tel],input[type=password]{
    width:100%;padding:11px 14px;border:1.5px solid #e0e0e0;border-radius:8px;
    font-size:14px;outline:none;font-family:inherit;transition:border-color .15s;background:#fafafa
}
input:focus{border-color:#1b5e20;background:#fff}
.input-icon-wrap{position:relative}
.input-icon-wrap input{padding-left:38px}
.input-icon-wrap .icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;pointer-events:none}
.hint{font-size:11px;color:#aaa;margin-top:4px}
.btn-save{background:#1b5e20;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s,transform .1s}
.btn-save:hover{background:#2e7d32;transform:translateY(-1px)}

/* Info display */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.info-row{display:flex;flex-direction:column;padding:12px 0;border-bottom:1px solid #f5f5f5}
.info-row:last-child{border-bottom:none}
.info-lbl{font-size:11px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.info-val{font-size:14px;color:#333;font-weight:600}

/* Alerts */
.alert-success{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-error{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* Password strength */
.strength-bar{height:4px;border-radius:2px;margin-top:6px;transition:width .3s,background .3s;width:0}

/* Tab panels */
.tab-panel{display:none}.tab-panel.active{display:block}

@media(max-width:600px){
  .navbar{padding:12px 16px}
  .page-wrap{padding:20px 14px}
  .profile-hero{flex-direction:column;text-align:center;padding:22px 18px}
  .form-row{flex-direction:column;gap:0}
  .info-grid{grid-template-columns:1fr}
  .tabs{flex-direction:column}
  .btn-save{width:100%}
}
</style></head><body>
<div class="navbar"><h1>🏢 PMS — Placement Officer Portal</h1><a href="../logout.php">Logout</a></div>
<div class="page-wrap">
<a class="back" href="dashboard.php">← Back to Dashboard</a>

<?php if ($success): ?>
<div class="alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Profile Hero -->
<div class="profile-hero">
  <div class="avatar-circle"><?= strtoupper(substr($officer['name'] ?? $name, 0, 1)) ?></div>
  <div class="hero-info">
    <h2><?= htmlspecialchars($officer['name'] ?? $name) ?></h2>
    <p><?= htmlspecialchars($officer['email'] ?? $email) ?></p>
    <span class="hero-badge">🏢 Placement Officer</span>
    <?php if (!empty($officer['department'])): ?>
      <span class="hero-badge"><?= htmlspecialchars($officer['department']) ?></span>
    <?php endif; ?>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" onclick="switchTab('info', this)">👤 Profile Info</button>
  <button class="tab-btn" onclick="switchTab('edit', this)">✏️ Edit Details</button>
  <button class="tab-btn" onclick="switchTab('password', this)">🔒 Change Password</button>
</div>

<!-- TAB: Account Info (read-only) -->
<div class="tab-panel active" id="tab-info">
  <div class="card">
    <h3>📋 Account Information</h3>
    <div class="info-grid">
      <div class="info-row">
        <span class="info-lbl">Full Name</span>
        <span class="info-val"><?= htmlspecialchars($officer['name'] ?? $name) ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Email</span>
        <span class="info-val"><?= htmlspecialchars($officer['email'] ?? $email) ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Department</span>
        <span class="info-val"><?= htmlspecialchars($officer['department'] ?? '') ?: '<span style="color:#aaa">Not set</span>' ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Phone</span>
        <span class="info-val"><?= htmlspecialchars($officer['phone'] ?? '') ?: '<span style="color:#aaa">Not set</span>' ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Role</span>
        <span class="info-val">Placement Officer</span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Officer ID</span>
        <span class="info-val">#<?= $officer_id ?></span>
      </div>
    </div>
  </div>
  <p style="text-align:center;font-size:13px;color:#aaa">
    <a href="#" onclick="switchTab('edit', document.querySelectorAll('.tab-btn')[1]);return false;" style="color:#1b5e20;font-weight:600">✏️ Edit your details →</a>
  </p>
</div>

<!-- TAB: Edit Profile -->
<div class="tab-panel" id="tab-edit">
  <div class="card">
    <h3>✏️ Edit Profile Details</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="profile">
      <div class="form-row">
        <div class="form-group">
          <label>Full Name</label>
          <div class="input-icon-wrap">
            <span class="icon">👤</span>
            <input type="text" name="name" value="<?= htmlspecialchars($officer['name'] ?? $name) ?>" required minlength="2" maxlength="100">
          </div>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <div class="input-icon-wrap">
            <span class="icon">✉️</span>
            <input type="email" name="email" value="<?= htmlspecialchars($officer['email'] ?? $email) ?>" required>
          </div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Department</label>
          <div class="input-icon-wrap">
            <span class="icon">🏢</span>
            <input type="text" name="department" value="<?= htmlspecialchars($officer['department'] ?? '') ?>" placeholder="e.g. Training & Placement Cell" maxlength="120">
          </div>
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <div class="input-icon-wrap">
            <span class="icon">📞</span>
            <input type="tel" name="phone" value="<?= htmlspecialchars($officer['phone'] ?? '') ?>" placeholder="e.g. +91 98765 43210" maxlength="30">
          </div>
        </div>
      </div>
      <button type="submit" class="btn-save">💾 Save Changes</button>
    </form>
  </div>
</div>

<!-- TAB: Change Password -->
<div class="tab-panel" id="tab-password">
  <div class="card">
    <h3>🔒 Change Password</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="password">
      <div class="form-group">
        <label>Current Password</label>
        <div class="input-icon-wrap">
          <span class="icon">🔑</span>
          <input type="password" name="current_password" placeholder="Enter your current password" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>New Password</label>
          <div class="input-icon-wrap">
            <span class="icon">🔒</span>
            <input type="password" name="new_password" id="newPwd" placeholder="Min 6 characters" required minlength="6"
              oninput="updateStrength(this.value)">
          </div>
          <div class="strength-bar" id="strengthBar"></div>
          <p class="hint" id="strengthTxt"></p>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="input-icon-wrap">
            <span class="icon">🔒</span>
            <input type="password" name="confirm_password" placeholder="Repeat new password" required minlength="6"
              oninput="checkMatch(this.value)">
          </div>
          <p class="hint" id="matchTxt"></p>
        </div>
      </div>
      <button type="submit" class="btn-save">🔒 Update Password</button>
    </form>
  </div>
</div>

</div>

<script>
function switchTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + id).classList.add('active');
  btn.classList.add('active');
}

// Auto-open edit or password tab based on server-side error/action
<?php
$post_action = $_POST['action'] ?? '';
if ($error && $post_action === 'profile')  echo "switchTab('edit', document.querySelectorAll('.tab-btn')[1]);";
if ($error && $post_action === 'password') echo "switchTab('password', document.querySelectorAll('.tab-btn')[2]);";
if ($success && $post_action === 'password') echo "switchTab('password', document.querySelectorAll('.tab-btn')[2]);";
?>

function updateStrength(pw) {
  const bar = document.getElementById('strengthBar');
  const txt = document.getElementById('strengthTxt');
  let score = 0;
  if (pw.length >= 6)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const levels = [
    {w:'20%', bg:'#ef5350', t:'Very weak'},
    {w:'40%', bg:'#ff7043', t:'Weak'},
    {w:'60%', bg:'#ffa726', t:'Fair'},
    {w:'80%', bg:'#66bb6a', t:'Strong'},
    {w:'100%',bg:'#2e7d32', t:'Very strong'},
  ];
  const l = levels[Math.min(score, 4)];
  bar.style.width = pw ? l.w : '0';
  bar.style.background = l.bg;
  txt.textContent = pw ? l.t : '';
  txt.style.color = l.bg;
}

function checkMatch(val) {
  const orig = document.getElementById('newPwd').value;
  const txt  = document.getElementById('matchTxt');
  if (!val) { txt.textContent = ''; return; }
  if (val === orig) {
    txt.textContent = '✅ Passwords match';
    txt.style.color = '#2e7d32';
  } else {
    txt.textContent = '❌ Passwords do not match';
    txt.style.color = '#c62828';
  }
}
</script>
</body></html>
