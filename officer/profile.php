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
    $officer = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT *, '' AS department, '' AS phone FROM user WHERE user_id=$user_id"));
}

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $new_name  = trim($_POST['name']);
        $new_email = trim($_POST['email']);
        $new_dept  = trim($_POST['department'] ?? '');
        $new_phone = trim($_POST['phone'] ?? '');

        if (strlen($new_name) < 2) {
            $error = "Name must be at least 2 characters.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $esc_email = mysqli_real_escape_string($conn, $new_email);
            $dup = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT user_id FROM user WHERE email='$esc_email' AND user_id != $user_id"));
            if ($dup) {
                $error = "That email address is already in use by another account.";
            } else {
                $esc_name  = mysqli_real_escape_string($conn, $new_name);
                $esc_dept  = mysqli_real_escape_string($conn, $new_dept);
                $esc_phone = mysqli_real_escape_string($conn, $new_phone);

                mysqli_query($conn, "UPDATE user SET email='$esc_email' WHERE user_id=$user_id");
                mysqli_query($conn,
                    "UPDATE placement_officer
                     SET name='$esc_name', department='$esc_dept', phone='$esc_phone'
                     WHERE officer_id=$officer_id");

                $_SESSION['name']  = $new_name;
                $_SESSION['email'] = $new_email;
                $success = "Profile updated successfully!";

                $officer = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT o.*, u.email
                     FROM placement_officer o
                     JOIN user u ON o.user_id = u.user_id
                     WHERE o.officer_id = $officer_id"));
            }
        }

    } elseif ($action === 'password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        $u = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT password FROM user WHERE user_id=$user_id"));

        if (!password_verify($cur, $u['password'])) {
            $error = "Current password is incorrect.";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new)) {
            $error = "New password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.";
        } elseif ($new !== $conf) {
            $error = "New passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt_u = mysqli_prepare($conn, "UPDATE user SET password=? WHERE user_id=?");
            mysqli_stmt_bind_param($stmt_u, "si", $hash, $user_id);
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);
            $success = "Password changed successfully!";
        }
    }
}

// Check / add department & phone columns
$has_dept  = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM placement_officer LIKE 'department'")) > 0;
$has_phone = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM placement_officer LIKE 'phone'")) > 0;
if (!$has_dept)  { mysqli_query($conn, "ALTER TABLE placement_officer ADD COLUMN department VARCHAR(120) DEFAULT ''"); }
if (!$has_phone) { mysqli_query($conn, "ALTER TABLE placement_officer ADD COLUMN phone VARCHAR(30) DEFAULT ''"); }

// Determine which tab to activate on page load
$post_action = $_POST['action'] ?? '';
$active_tab  = 'info';
if ($error   && $post_action === 'profile')  $active_tab = 'edit';
if ($error   && $post_action === 'password') $active_tab = 'password';
if ($success && $post_action === 'password') $active_tab = 'password';

$page_title  = 'My Profile — TPO Portal';
$active_page = 'profile';

$extra_head = <<<HTML
<style>
  .profile-hero {
    background: linear-gradient(135deg, var(--pms-green) 0%, var(--pms-green-mid) 60%, var(--pms-green-light) 100%);
    border-radius: 18px;
    padding: 2rem;
    position: relative;
    overflow: hidden;
    color: #fff;
  }
  .profile-hero::after {
    content: '⚙';
    position: absolute;
    right: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 120px;
    opacity: .06;
    pointer-events: none;
  }
  .avatar-circle {
    width: 76px; height: 76px;
    border-radius: 50%;
    background: #ffffff;
    border: 3px solid rgba(255,255,255,.9);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800; color: #222;
    flex-shrink: 0;
  }
  .hero-badge {
    display: inline-block;
    background: rgba(255,255,255,.2);
    color: #fff;
    padding: .25rem .75rem;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 600;
    margin-top: .5rem;
    margin-right: .3rem;
  }
  /* Tab pills */
  .profile-tabs .nav-link {
    color: #555;
    font-size: .82rem;
    font-weight: 600;
    border-radius: 8px;
    padding: .55rem 1.1rem;
    transition: all .15s;
  }
  .profile-tabs .nav-link.active {
    background: var(--pms-green);
    color: #fff;
  }
  .profile-tabs .nav-link:hover:not(.active) { background: #f0f0f0; }
  /* Info grid */
  .info-lbl { font-size: .7rem; font-weight: 700; color: #aaa; text-transform: uppercase; letter-spacing: .4px; }
  .info-val  { font-size: .9rem; font-weight: 600; color: #222; }
  /* Strength bar */
  #strengthBar { height: 4px; border-radius: 2px; transition: width .3s, background .3s; width: 0; }
</style>
HTML;

require_once '_nav.php';
?>

<div class="page-content">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a></li>
      <li class="breadcrumb-item active">My Profile</li>
    </ol>
  </nav>

  <!-- Alerts -->
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">

      <!-- Profile Hero -->
      <div class="profile-hero mb-4 d-flex align-items-center gap-4 flex-wrap">
        <div class="avatar-circle">
          <?= strtoupper(substr($officer['name'] ?? $name, 0, 1)) ?>
        </div>
        <div>
          <h2 class="fw-800 mb-1" style="font-size:1.4rem">
            <?= htmlspecialchars($officer['name'] ?? $name) ?>
          </h2>
          <div class="opacity-75 small"><?= htmlspecialchars($officer['email'] ?? $email) ?></div>

        </div>
      </div>

      <!-- Tab Nav -->
      <ul class="nav profile-tabs mb-3 bg-white rounded-3 p-2 shadow-sm" id="profileTabs" role="tablist">
        <li class="nav-item">
          <button class="nav-link <?= $active_tab === 'info' ? 'active' : '' ?>"
                  id="tab-info-btn" data-bs-toggle="tab" data-bs-target="#tab-info" type="button">
            <i class="bi bi-person me-1"></i>Profile Info
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link <?= $active_tab === 'edit' ? 'active' : '' ?>"
                  id="tab-edit-btn" data-bs-toggle="tab" data-bs-target="#tab-edit" type="button">
            <i class="bi bi-pencil me-1"></i>Edit Details
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link <?= $active_tab === 'password' ? 'active' : '' ?>"
                  id="tab-pwd-btn" data-bs-toggle="tab" data-bs-target="#tab-pwd" type="button">
            <i class="bi bi-lock me-1"></i>Change Password
          </button>
        </li>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content">

        <!-- TAB: Info -->
        <div class="tab-pane fade <?= $active_tab === 'info' ? 'show active' : '' ?>" id="tab-info">
          <div class="card pms-card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-info-circle"></i> Account Information
            </div>
            <div class="card-body p-0">
              <div class="row g-0">
                <?php
                $fields = [
                  ['Full Name',    $officer['name']       ?? $name,   'bi-person'],
                  ['Email',        $officer['email']      ?? $email,  'bi-envelope'],
                  ['Department',   $officer['department'] ?? '',      'bi-diagram-3'],
                  ['Phone',        $officer['phone']      ?? '',      'bi-telephone'],
                  ['Role',         'Placement Officer',               'bi-briefcase'],
                  ['Officer ID',   '#' . $officer_id,                 'bi-hash'],
                ];
                foreach ($fields as [$lbl, $val, $icon]):
                ?>
                <div class="col-12 col-sm-6">
                  <div class="p-3 border-bottom border-end-sm d-flex align-items-start gap-3">
                    <div class="mt-1">
                      <i class="bi <?= $icon ?> text-success fs-5"></i>
                    </div>
                    <div>
                      <div class="info-lbl"><?= $lbl ?></div>
                      <div class="info-val">
                        <?= $val ? htmlspecialchars($val) : '<span class="text-muted fst-italic small">Not set</span>' ?>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="card-footer bg-white text-center border-0 py-3">
              <a href="#" onclick="document.getElementById('tab-edit-btn').click();return false;"
                 class="text-success fw-semibold small text-decoration-none">
                <i class="bi bi-pencil me-1"></i>Edit your details →
              </a>
            </div>
          </div>
        </div>

        <!-- TAB: Edit Profile -->
        <div class="tab-pane fade <?= $active_tab === 'edit' ? 'show active' : '' ?>" id="tab-edit">
          <div class="card pms-card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-pencil-square"></i> Edit Profile Details
            </div>
            <div class="card-body p-4">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="profile">

                <div class="row g-3 mb-3">
                  <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-person me-1 text-success"></i>Full Name
                    </label>
                    <input type="text" class="form-control" name="name"
                           value="<?= htmlspecialchars($officer['name'] ?? $name) ?>"
                           required minlength="2" maxlength="100">
                  </div>
                  <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-envelope me-1 text-success"></i>Email Address
                    </label>
                    <input type="email" class="form-control" name="email"
                           value="<?= htmlspecialchars($officer['email'] ?? $email) ?>" required>
                  </div>
                  <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-diagram-3 me-1 text-success"></i>Department
                    </label>
                    <input type="text" class="form-control" name="department"
                           value="<?= htmlspecialchars($officer['department'] ?? '') ?>"
                           placeholder="e.g. Training & Placement Cell" maxlength="120">
                  </div>
                  <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-telephone me-1 text-success"></i>Phone Number
                    </label>
                    <input type="tel" class="form-control" name="phone"
                           value="<?= htmlspecialchars($officer['phone'] ?? '') ?>"
                           placeholder="e.g. +91 98765 43210" maxlength="30">
                  </div>
                </div>

                <button type="submit" class="btn btn-pms px-4">
                  <i class="bi bi-floppy me-1"></i>Save Changes
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- TAB: Change Password -->
        <div class="tab-pane fade <?= $active_tab === 'password' ? 'show active' : '' ?>" id="tab-pwd">
          <div class="card pms-card">
            <div class="card-header d-flex align-items-center gap-2">
              <i class="bi bi-lock"></i> Change Password
            </div>
            <div class="card-body p-4">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="password">

                <div class="mb-3">
                  <label class="form-label fw-semibold small">
                    <i class="bi bi-key me-1 text-success"></i>Current Password
                  </label>
                  <input type="password" class="form-control" name="current_password"
                         placeholder="Enter your current password" required>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-lock me-1 text-success"></i>New Password
                    </label>
                    <div class="input-group">
                      <input type="password" class="form-control" name="new_password" id="newPwd"
                             placeholder="Min 8 characters" required minlength="8"
                             oninput="updateStrength(this.value)">
                      <button class="btn btn-outline-secondary" type="button" onclick="togglePw('newPwd', this)">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                    <div id="strengthBar" class="mt-2"></div>
                    <small class="text-muted d-block mt-1" id="strengthTxt"></small>
                    <ul class="list-unstyled mt-2 ps-0" id="reqs" style="font-size: 0.75rem;">
                      <li id="r-len" class="text-muted"><i class="bi bi-x-circle-fill me-1 text-danger"></i> At least 8 characters</li>
                      <li id="r-upper" class="text-muted"><i class="bi bi-x-circle-fill me-1 text-danger"></i> One uppercase letter (A–Z)</li>
                      <li id="r-lower" class="text-muted"><i class="bi bi-x-circle-fill me-1 text-danger"></i> One lowercase letter (a–z)</li>
                      <li id="r-digit" class="text-muted"><i class="bi bi-x-circle-fill me-1 text-danger"></i> One digit (0–9)</li>
                      <li id="r-sym" class="text-muted"><i class="bi bi-x-circle-fill me-1 text-danger"></i> One special character (!@#$…)</li>
                    </ul>
                  </div>
                  <div class="col-12 col-sm-6">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-lock-fill me-1 text-success"></i>Confirm New Password
                    </label>
                    <div class="input-group">
                      <input type="password" class="form-control" name="confirm_password" id="confirmPwd"
                             placeholder="Repeat new password" required minlength="8"
                             oninput="checkMatch(this.value)">
                      <button class="btn btn-outline-secondary" type="button" onclick="togglePw('confirmPwd', this)">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                    <small class="d-block mt-1" id="matchTxt"></small>
                  </div>
                </div>

                <button type="submit" class="btn btn-pms px-4">
                  <i class="bi bi-shield-lock me-1"></i>Update Password
                </button>
              </form>
            </div>
          </div>
        </div>

      </div><!-- /tab-content -->
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateStrength(pw) {
  const bar = document.getElementById('strengthBar');
  const txt = document.getElementById('strengthTxt');
  
  const reqs = {
    len: pw.length >= 8,
    upper: /[A-Z]/.test(pw),
    lower: /[a-z]/.test(pw),
    digit: /[0-9]/.test(pw),
    sym: /[\W_]/.test(pw)
  };
  
  const updateReq = (id, met) => {
    const li = document.getElementById(id);
    if (!li) return;
    const icon = li.querySelector('i');
    if (met) {
      li.className = 'text-success fw-semibold';
      if (icon) {
        icon.className = 'bi bi-check-circle-fill text-success me-1';
      }
    } else {
      li.className = 'text-muted';
      if (icon) {
        icon.className = 'bi bi-x-circle-fill text-danger me-1';
      }
    }
  };
  
  updateReq('r-len', reqs.len);
  updateReq('r-upper', reqs.upper);
  updateReq('r-lower', reqs.lower);
  updateReq('r-digit', reqs.digit);
  updateReq('r-sym', reqs.sym);
  
  const score = Object.values(reqs).filter(Boolean).length; // 0..5
  const levels = [
    {w:'0%', bg:'#e0e0e0', t:''},
    {w:'20%', bg:'#ef5350', t:'Very weak'},
    {w:'40%', bg:'#ff7043', t:'Weak'},
    {w:'60%', bg:'#ffa726', t:'Fair'},
    {w:'80%', bg:'#66bb6a', t:'Strong'},
    {w:'100%', bg:'#2e7d32', t:'Very strong'},
  ];
  
  const l = levels[pw ? score : 0];
  bar.style.width = pw ? l.w : '0';
  bar.style.background = l.bg;
  txt.textContent = pw ? l.t : '';
  txt.style.color = l.bg;
  
  checkMatch(document.getElementById('confirmPwd').value);
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

function togglePw(id, btn) {
  const inp = document.getElementById(id);
  if (!inp) return;
  const icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    if (icon) {
      icon.className = 'bi bi-eye-slash';
    }
  } else {
    inp.type = 'password';
    if (icon) {
      icon.className = 'bi bi-eye';
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const pwdFormInput = document.querySelector('form input[name="action"][value="password"]');
  if (pwdFormInput) {
    const pwdForm = pwdFormInput.closest('form');
    if (pwdForm) {
      pwdForm.addEventListener('submit', function(e) {
        const pw = document.getElementById('newPwd').value;
        const conf = document.getElementById('confirmPwd').value;
        
        const complexRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
        if (!complexRegex.test(pw)) {
          e.preventDefault();
          alert('New password must be at least 8 characters and include an uppercase letter, lowercase letter, digit, and special character.');
        } else if (pw !== conf) {
          e.preventDefault();
          alert('Confirm password must match the new password.');
        }
      });
    }
  }
});
</script>
</body>
</html>
