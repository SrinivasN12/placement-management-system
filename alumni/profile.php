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
    elseif ($new_pass && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_pass)) {
        $error = "Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.";
    }
    elseif ($new_pass && $new_pass !== $confirm) { $error = "Passwords do not match."; }
    else {
        $stmt_al = mysqli_prepare($conn, "UPDATE alumni SET company_name=? WHERE alumni_id=?");
        mysqli_stmt_bind_param($stmt_al, "si", $company, $alumni_id);
        mysqli_stmt_execute($stmt_al);
        mysqli_stmt_close($stmt_al);
        if ($new_pass) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $uid  = (int)$_SESSION['user_id'];
            $stmt_u = mysqli_prepare($conn, "UPDATE user SET password=? WHERE user_id=?");
            mysqli_stmt_bind_param($stmt_u, "si", $hash, $uid);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);
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
.req-list{margin-top:7px;list-style:none;padding-left:0}
.req-list li{font-size:11px;color:#b0b8c8;display:flex;align-items:center;gap:5px;margin-bottom:3px;transition:color .2s}
.req-list li.ok{color:#15803d}
.req-list li::before{content:'✗';font-size:10px}
.req-list li.ok::before{content:'✓'}
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
          <div class="pw-wrap" style="position:relative">
            <input type="password" id="pw" name="new_password" placeholder="New password">
            <button type="button" class="pw-toggle" onclick="togglePw('pw',this)" tabindex="-1" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:15px;padding:0;line-height:1">👁</button>
          </div>
          <div class="strength-bar" style="display:flex;gap:4px;margin-top:6px">
            <span id="sb1" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
            <span id="sb2" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
            <span id="sb3" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
            <span id="sb4" style="flex:1;height:3px;border-radius:3px;background:#e0e0e0;transition:background .25s"></span>
          </div>
          <div class="strength-label" id="slabel" style="font-size:11px;margin-top:4px;font-weight:600"></div>
          <ul class="req-list" id="reqs" style="margin-top:7px;list-style:none;padding-left:0">
            <li id="r-len">At least 8 characters</li>
            <li id="r-upper">One uppercase letter (A–Z)</li>
            <li id="r-lower">One lowercase letter (a–z)</li>
            <li id="r-digit">One digit (0–9)</li>
            <li id="r-sym">One special character (!@#$…)</li>
          </ul>
        </div>
        <div class="form-group" style="align-self:flex-start">
          <label>Confirm Password</label>
          <div class="pw-wrap" style="position:relative">
            <input type="password" id="pw2" name="confirm_password" placeholder="Repeat password">
            <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)" tabindex="-1" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:15px;padding:0;line-height:1">👁</button>
          </div>
          <div class="strength-label" id="matchlabel" style="margin-top:6px"></div>
        </div>
      </div>

      <button type="submit" class="btn">
        <span class="material-icons-round">save</span>
        Save Changes
      </button>
    </form>
  </div>
</div>

<script>
const colors = ['#ef5350', '#ff9800', '#ffeb3b', '#4caf50'];
const labels = ['Weak', 'Fair', 'Good', 'Strong'];
function checkPw() {
  const v = document.getElementById('pw').value;
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

  const score = Object.values(reqs).filter(Boolean).length - 1; // 0..4
  const bars = ['sb1', 'sb2', 'sb3', 'sb4'];
  bars.forEach((id, i) => {
    document.getElementById(id).style.background = (v.length === 0) ? '#e0e0e0' : (i <= score ? colors[Math.min(score, 3)] : '#e0e0e0');
  });
  const lbl = document.getElementById('slabel');
  if (v.length === 0) { lbl.textContent = ''; lbl.style.color = ''; }
  else { lbl.textContent = labels[Math.min(score, 3)]; lbl.style.color = colors[Math.min(score, 3)]; }
  checkMatch();
}
function checkMatch() {
  const v1 = document.getElementById('pw').value;
  const v2 = document.getElementById('pw2').value;
  const ml = document.getElementById('matchlabel');
  if (!v2) { ml.textContent = ''; return; }
  if (v1 === v2) { ml.textContent = '✓ Passwords match'; ml.style.color = '#2e7d32'; }
  else { ml.textContent = '✗ Passwords do not match'; ml.style.color = '#c62828'; }
}
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
document.getElementById('pw').addEventListener('input', checkPw);
document.getElementById('pw2').addEventListener('input', checkMatch);
document.querySelector('form').addEventListener('submit', function (e) {
  const v = document.getElementById('pw').value;
  if (v) {
    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(v)) {
      e.preventDefault();
      alert('Password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.');
    } else if (v !== document.getElementById('pw2').value) {
      e.preventDefault();
      alert('Passwords do not match.');
    }
  }
});
</script>
<?php require '_layout_end.php'; ?>
