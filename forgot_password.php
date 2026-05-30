<?php
// =============================================================
//  forgot_password.php — Password Reset via Email OTP
//
//  Step 1: Enter registered email  → validate email exists in DB
//  Step 2: Enter 6-digit OTP       → verify OTP, check expiry
//  Step 3: Set new password        → update DB, clear OTP
// =============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';
require_once 'config/email_helpers.php'; // includes mailer.php

// ── CSRF Token ───────────────────────────────────────────────
if (empty($_SESSION['fp_csrf'])) {
    $_SESSION['fp_csrf'] = bin2hex(random_bytes(32));
}

// ── OTP constants ────────────────────────────────────────────
const OTP_EXPIRY_MINUTES = 10;
const OTP_RATE_LIMIT_SEC = 60; // minimum seconds between OTP requests

// ── Clear stale forgot-password session if user visits page fresh ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['step'])) {
    unset($_SESSION['fp_email'], $_SESSION['fp_verified'], $_SESSION['fp_name']);
}

$step    = !empty($_SESSION['fp_verified']) ? 3 : (isset($_SESSION['fp_email']) ? 2 : 1);
$error   = '';
$success = '';

// =============================================================
//  POST Handler
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $submitted = $_POST['fp_csrf'] ?? '';
    if (!hash_equals($_SESSION['fp_csrf'], $submitted)) {
        http_response_code(403);
        exit('403 Forbidden — CSRF token mismatch.');
    }

    $action = $_POST['action'] ?? '';

    // ── Step 1: Validate email & send OTP ────────────────────
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email exists in user table (Student role_id=1 or Alumni role_id=3)
            $stmt = mysqli_prepare($conn,
                "SELECT u.user_id, u.email, u.role_id, u.status,
                        COALESCE(s.name, a.name) AS name
                 FROM user u
                 LEFT JOIN student s ON s.user_id = u.user_id
                 LEFT JOIN alumni  a ON a.user_id = u.user_id
                 WHERE u.email = ?
                   AND u.role_id IN (1, 3)");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$row) {
                $error = '⚠ No student or alumni account found with that email address.';
            } elseif ($row['status'] === 'Rejected') {
                $error = 'Your account has been rejected. Contact the placement cell.';
            } else {
                // Rate limit: block if OTP was sent less than OTP_RATE_LIMIT_SEC ago
                $stmt2 = mysqli_prepare($conn,
                    "SELECT created_at FROM password_reset_otp
                     WHERE email = ? AND used = 0
                     ORDER BY created_at DESC LIMIT 1");
                mysqli_stmt_bind_param($stmt2, 's', $email);
                mysqli_stmt_execute($stmt2);
                $last = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
                mysqli_stmt_close($stmt2);

                if ($last) {
                    $secondsAgo = time() - strtotime($last['created_at']);
                    if ($secondsAgo < OTP_RATE_LIMIT_SEC) {
                        $wait = OTP_RATE_LIMIT_SEC - $secondsAgo;
                        $error = "⏳ Please wait {$wait} second(s) before requesting a new OTP.";
                    }
                }

                if (!$error) {
                    // Invalidate all previous OTPs for this email
                    $del = mysqli_prepare($conn,
                        "UPDATE password_reset_otp SET used = 1 WHERE email = ?");
                    mysqli_stmt_bind_param($del, 's', $email);
                    mysqli_stmt_execute($del);
                    mysqli_stmt_close($del);

                    // Generate new OTP
                    $otp       = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

                    $ins = mysqli_prepare($conn,
                        "INSERT INTO password_reset_otp (email, otp, expires_at) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($ins, 'sss', $email, $otp, $expiresAt);
                    mysqli_stmt_execute($ins);
                    mysqli_stmt_close($ins);

                    // Send OTP email
                    $userName  = $row['name'] ?: 'User';
                    $emailBody = buildOtpEmail($userName, $otp, OTP_EXPIRY_MINUTES);
                    $sent      = sendEmail($email, '🔐 Your PMS Password Reset OTP', $emailBody);

                    if ($sent) {
                        $_SESSION['fp_email'] = $email;
                        $_SESSION['fp_name']  = $userName;
                        unset($_SESSION['fp_verified']);
                        $step    = 2;
                        $success = "✅ OTP sent to <strong>" . htmlspecialchars($email) . "</strong>. Check your inbox.";
                    } else {
                        $error = '❌ Failed to send OTP email. Please try again or contact support.';
                    }
                }
            }
        }
    }

    // ── Step 2: Verify OTP ───────────────────────────────────
    elseif ($action === 'verify_otp') {
        $email      = $_SESSION['fp_email'] ?? '';
        $enteredOtp = trim($_POST['otp'] ?? '');

        if (!$email) {
            $error = 'Session expired. Please start again.';
            $step  = 1;
        } elseif (!preg_match('/^\d{6}$/', $enteredOtp)) {
            $error = 'Please enter a valid 6-digit OTP.';
            $step  = 2;
        } else {
            // Fetch the latest unused, unexpired OTP for this email
            $stmt = mysqli_prepare($conn,
                "SELECT id, otp, expires_at FROM password_reset_otp
                 WHERE email = ? AND used = 0
                 ORDER BY created_at DESC LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$row) {
                $error = 'No valid OTP found. Please request a new one.';
                $step  = 2;
            } elseif (strtotime($row['expires_at']) < time()) {
                $error = '⏰ OTP has expired. Please request a new one.';
                $step  = 2;
            } elseif ($row['otp'] !== $enteredOtp) {
                $error = '❌ Incorrect OTP. Please check and try again.';
                $step  = 2;
            } else {
                // Mark OTP as used
                $upd = mysqli_prepare($conn,
                    "UPDATE password_reset_otp SET used = 1 WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'i', $row['id']);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);

                $_SESSION['fp_verified'] = true;
                $step    = 3;
                $success = '✅ OTP verified! Please set your new password.';
            }
        }
    }

    // ── Step 3: Reset Password ───────────────────────────────
    elseif ($action === 'reset_password') {
        $email   = $_SESSION['fp_email'] ?? '';
        $newPw   = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$email || empty($_SESSION['fp_verified'])) {
            $error = 'Session expired. Please start over.';
            $step  = 1;
        } elseif (strlen($newPw) < 8) {
            $error = 'Password must be at least 8 characters.';
            $step  = 3;
        } elseif ($newPw !== $confirm) {
            $error = 'Passwords do not match.';
            $step  = 3;
        } else {
            $hashed = password_hash($newPw, PASSWORD_DEFAULT);

            $upd = mysqli_prepare($conn,
                "UPDATE user SET password = ? WHERE email = ? AND role_id IN (1, 3)");
            mysqli_stmt_bind_param($upd, 'ss', $hashed, $email);
            mysqli_stmt_execute($upd);
            $affected = mysqli_stmt_affected_rows($upd);
            mysqli_stmt_close($upd);

            if ($affected > 0) {
                // Clean up any remaining OTPs for this email
                $cleanup = mysqli_prepare($conn,
                    "DELETE FROM password_reset_otp WHERE email = ?");
                mysqli_stmt_bind_param($cleanup, 's', $email);
                mysqli_stmt_execute($cleanup);
                mysqli_stmt_close($cleanup);

                // Clear session data
                unset($_SESSION['fp_email'], $_SESSION['fp_verified'], $_SESSION['fp_name'], $_SESSION['fp_csrf']);

                // Redirect to login with success message
                $_SESSION['login_success'] = '🎉 Password reset successfully! Please log in with your new password.';
                header('Location: login.php');
                exit();
            } else {
                $error = 'Could not update password. Please contact support.';
                $step  = 3;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Forgot Password — JSS DVH-IMSR Placement Portal">
  <title>Forgot Password — JSS DVH-IMSR PMS</title>
  <link rel="stylesheet" href="assets/pms-theme.css">
  <style>
    /* ── OTP digit input group ──────────────────────────── */
    .otp-group {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 20px 0;
    }
    .otp-digit {
      width: 52px; height: 60px;
      text-align: center;
      font-size: 24px; font-weight: 700;
      font-family: 'Courier New', monospace;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      background: var(--surface);
      color: var(--primary);
      outline: none;
      transition: .2s;
      caret-color: var(--accent);
    }
    .otp-digit:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(201,168,76,.18);
    }
    .otp-digit.filled { border-color: var(--accent); background: rgba(201,168,76,.06); }

    /* ── Step indicator ─────────────────────────────────── */
    .step-indicators {
      display: flex; align-items: center; justify-content: center;
      gap: 0; margin-bottom: 28px;
    }
    .step-dot {
      width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700;
      border: 2px solid var(--border); color: var(--text-muted);
      background: var(--surface); transition: .3s;
      position: relative; z-index: 1;
    }
    .step-dot.active  { border-color: var(--accent); color: var(--primary); background: rgba(201,168,76,.12); }
    .step-dot.done    { border-color: var(--accent); background: var(--accent); color: var(--primary); }
    .step-line {
      flex: 1; height: 2px; max-width: 60px;
      background: var(--border); transition: .3s;
    }
    .step-line.done { background: var(--accent); }

    /* ── Resend link ─────────────────────────────────────── */
    .resend-wrap { text-align: center; margin-top: 14px; }
    .resend-wrap a { color: var(--accent); font-size: 13px; font-weight: 600;
                     cursor: pointer; text-decoration: none; }
    .resend-wrap a:hover { text-decoration: underline; }

    /* ── Email display badge ─────────────────────────────── */
    .email-badge {
      display: inline-flex; align-items: center; gap: 7px;
      background: rgba(201,168,76,.08);
      border: 1px solid rgba(201,168,76,.3);
      border-radius: 30px; padding: 6px 16px;
      font-size: 13px; font-weight: 600; color: var(--primary);
      margin: 8px auto 20px; max-width: 100%; word-break: break-all;
    }

    /* ── Success info box ────────────────────────────────── */
    .alert-info {
      background: #eff6ff; color: #1e40af;
      border: 1px solid #93c5fd; border-radius: var(--radius);
      padding: 11px 16px; font-size: 13px; margin-bottom: 18px;
    }
  </style>
</head>

<body>
  <div class="auth-page-bg">
    <div class="auth-card" style="max-width:480px">
      <div class="auth-card-accent"></div>
      <div class="auth-card-body">

        <!-- Logo -->
        <div class="auth-logo-wrap">
          <img src="assets/logo.jpg" alt="JSS Logo" class="auth-logo-img"
               onerror="this.style.display='none'">
          <span class="auth-role-badge">🔐 Password Reset</span>
        </div>

        <!-- Step Indicators -->
        <div class="step-indicators">
          <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>" id="sd1">
            <?= $step > 1 ? '✓' : '1' ?>
          </div>
          <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
          <div class="step-dot <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>" id="sd2">
            <?= $step > 2 ? '✓' : '2' ?>
          </div>
          <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
          <div class="step-dot <?= $step === 3 ? 'active' : '' ?>" id="sd3">3</div>
        </div>

        <!-- Page title -->
        <h1 class="auth-title" style="font-size:24px">
          <?php if ($step === 1): ?>Forgot Password
          <?php elseif ($step === 2): ?>Enter OTP
          <?php else: ?>Set New Password
          <?php endif; ?>
        </h1>
        <p class="auth-sub">
          <?php if ($step === 1): ?>Enter your registered email to receive a reset OTP.
          <?php elseif ($step === 2): ?>We sent a 6-digit code to your email.
          <?php else: ?>Create a strong new password for your account.
          <?php endif; ?>
        </p>

        <!-- Alerts -->
        <?php if ($error): ?>
          <div class="alert-error">⚠ <?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert-info"><?= $success ?></div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════
             STEP 1 — Enter Email
        ════════════════════════════════════════════════════ -->
        <?php if ($step === 1): ?>
        <form method="POST" action="forgot_password.php" id="form-step1" novalidate>
          <input type="hidden" name="fp_csrf" value="<?= htmlspecialchars($_SESSION['fp_csrf']) ?>">
          <input type="hidden" name="action"  value="send_otp">

          <div class="form-group-sm">
            <label class="form-label-sm" for="fp-email">Registered Email Address</label>
            <input type="email" id="fp-email" name="email" class="form-ctrl"
                   placeholder="your@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autocomplete="email">
          </div>

          <button type="submit" class="btn-gold"
                  style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:6px"
                  id="btn-send-otp">
            Send OTP →
          </button>
        </form>

        <!-- ═══════════════════════════════════════════════════
             STEP 2 — Enter OTP
        ════════════════════════════════════════════════════ -->
        <?php elseif ($step === 2): ?>
        <div style="text-align:center">
          <span class="email-badge">
            📧 <?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?>
          </span>
        </div>

        <form method="POST" action="forgot_password.php" id="form-step2" novalidate>
          <input type="hidden" name="fp_csrf" value="<?= htmlspecialchars($_SESSION['fp_csrf']) ?>">
          <input type="hidden" name="action"  value="verify_otp">
          <!-- Hidden field to collect assembled OTP -->
          <input type="hidden" name="otp" id="otp-hidden">

          <label class="form-label-sm" style="display:block;text-align:center;margin-bottom:4px">
            Enter 6-Digit OTP
          </label>

          <!-- Split digit inputs -->
          <div class="otp-group" id="otp-group">
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" id="d0" autocomplete="one-time-code">
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" id="d1">
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" id="d2">
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" id="d3">
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" id="d4">
            <input class="otp-digit" type="text" maxlength="1" inputmode="numeric"
                   pattern="[0-9]" id="d5">
          </div>

          <!-- Expiry countdown -->
          <p style="text-align:center;font-size:12px;color:var(--text-muted);margin-bottom:16px">
            ⏱ Code expires in <strong id="otp-countdown" style="color:var(--accent)"><?= OTP_EXPIRY_MINUTES ?>:00</strong>
          </p>

          <button type="submit" class="btn-gold"
                  style="width:100%;justify-content:center;padding:13px;font-size:15px"
                  id="btn-verify-otp">
            Verify OTP →
          </button>
        </form>

        <!-- Resend link -->
        <div class="resend-wrap">
          <form method="POST" action="forgot_password.php" style="display:inline">
            <input type="hidden" name="fp_csrf" value="<?= htmlspecialchars($_SESSION['fp_csrf']) ?>">
            <input type="hidden" name="action"  value="send_otp">
            <input type="hidden" name="email"   value="<?= htmlspecialchars($_SESSION['fp_email'] ?? '') ?>">
            Didn't receive it? <button type="submit"
              style="background:none;border:none;padding:0;color:var(--accent);font-weight:600;
                     font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif">
              Resend OTP
            </button>
          </form>
        </div>

        <!-- ═══════════════════════════════════════════════════
             STEP 3 — Set New Password
        ════════════════════════════════════════════════════ -->
        <?php else: ?>
        <form method="POST" action="forgot_password.php" id="form-step3" novalidate>
          <input type="hidden" name="fp_csrf" value="<?= htmlspecialchars($_SESSION['fp_csrf']) ?>">
          <input type="hidden" name="action"  value="reset_password">

          <div class="form-group-sm">
            <label class="form-label-sm" for="new-password">New Password</label>
            <div class="pw-wrap">
              <input type="password" id="new-password" name="new_password"
                     class="form-ctrl" placeholder="Minimum 8 characters"
                     required autocomplete="new-password" oninput="checkStrength(this.value)">
              <button type="button" class="pw-toggle" onclick="togglePw('new-password', this)">👁</button>
            </div>
            <!-- Strength bar -->
            <div class="strength-bar" id="str-bar">
              <span id="sb1"></span><span id="sb2"></span>
              <span id="sb3"></span><span id="sb4"></span>
            </div>
            <p class="strength-label" id="str-label" style="color:var(--text-muted)"></p>
            <ul class="req-list" id="req-list">
              <li id="req-len">At least 8 characters</li>
              <li id="req-upper">One uppercase letter</li>
              <li id="req-num">One number</li>
              <li id="req-sym">One special character</li>
            </ul>
          </div>

          <div class="form-group-sm">
            <label class="form-label-sm" for="confirm-password">Confirm New Password</label>
            <div class="pw-wrap">
              <input type="password" id="confirm-password" name="confirm_password"
                     class="form-ctrl" placeholder="Re-enter your new password"
                     required autocomplete="new-password">
              <button type="button" class="pw-toggle" onclick="togglePw('confirm-password', this)">👁</button>
            </div>
          </div>

          <button type="submit" class="btn-gold"
                  style="width:100%;justify-content:center;padding:13px;font-size:15px;margin-top:8px"
                  id="btn-reset-pw">
            Reset Password →
          </button>
        </form>
        <?php endif; ?>

        <!-- Footer links -->
        <div class="auth-link-row" style="margin-top:22px">
          <a href="login.php" style="color:var(--text-muted);font-size:12px;font-weight:400">← Back to Login</a>
        </div>

      </div><!-- /auth-card-body -->
    </div><!-- /auth-card -->
  </div><!-- /auth-page-bg -->

  <script>
    // ── OTP digit navigation ──────────────────────────────
    (function () {
      const digits  = Array.from(document.querySelectorAll('.otp-digit'));
      const hidden  = document.getElementById('otp-hidden');
      const form    = document.getElementById('form-step2');
      if (!digits.length || !form) return;

      function syncHidden() {
        hidden.value = digits.map(d => d.value).join('');
      }

      digits.forEach((el, i) => {
        el.addEventListener('input', function () {
          this.value = this.value.replace(/\D/g, '').slice(-1);
          if (this.value) {
            this.classList.add('filled');
            if (i < digits.length - 1) digits[i + 1].focus();
          } else {
            this.classList.remove('filled');
          }
          syncHidden();
        });

        el.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !this.value && i > 0) {
            digits[i - 1].value = '';
            digits[i - 1].classList.remove('filled');
            digits[i - 1].focus();
            syncHidden();
          }
          if (e.key === 'ArrowLeft'  && i > 0) digits[i - 1].focus();
          if (e.key === 'ArrowRight' && i < digits.length - 1) digits[i + 1].focus();
        });

        // Handle paste of full code
        el.addEventListener('paste', function (e) {
          const pasted = (e.clipboardData || window.clipboardData)
            .getData('text').replace(/\D/g, '').slice(0, 6);
          if (pasted.length === 6) {
            digits.forEach((d, j) => {
              d.value = pasted[j] || '';
              d.classList.toggle('filled', !!d.value);
            });
            digits[5].focus();
            syncHidden();
            e.preventDefault();
          }
        });
      });

      form.addEventListener('submit', function (e) {
        syncHidden();
        if (hidden.value.length !== 6) {
          e.preventDefault();
          alert('Please enter all 6 digits of the OTP.');
        }
      });

      // Focus first digit on page load
      digits[0] && digits[0].focus();
    })();

    // ── OTP countdown timer ───────────────────────────────
    (function () {
      const el = document.getElementById('otp-countdown');
      if (!el) return;
      let total = <?= OTP_EXPIRY_MINUTES * 60 ?>;
      function tick() {
        if (total <= 0) {
          el.textContent = 'Expired';
          el.style.color = '#b91c1c';
          return;
        }
        const m = String(Math.floor(total / 60)).padStart(2, '0');
        const s = String(total % 60).padStart(2, '0');
        el.textContent = `${m}:${s}`;
        if (total <= 60) el.style.color = '#b91c1c';
        total--;
        setTimeout(tick, 1000);
      }
      tick();
    })();

    // ── Password toggle ───────────────────────────────────
    function togglePw(id, btn) {
      const inp = document.getElementById(id);
      inp.type = inp.type === 'password' ? 'text' : 'password';
      btn.textContent = inp.type === 'password' ? '👁' : '🙈';
    }

    // ── Password strength meter ───────────────────────────
    function checkStrength(val) {
      const bars  = [1,2,3,4].map(n => document.getElementById('sb' + n));
      const label = document.getElementById('str-label');

      const checks = {
        len:   val.length >= 8,
        upper: /[A-Z]/.test(val),
        num:   /[0-9]/.test(val),
        sym:   /[^A-Za-z0-9]/.test(val)
      };

      // Requirement list feedback
      document.getElementById('req-len')  .classList.toggle('ok', checks.len);
      document.getElementById('req-upper').classList.toggle('ok', checks.upper);
      document.getElementById('req-num')  .classList.toggle('ok', checks.num);
      document.getElementById('req-sym')  .classList.toggle('ok', checks.sym);

      const score = Object.values(checks).filter(Boolean).length;
      const colors = ['', '#ef5350', '#ff9800', '#66bb6a', '#43a047'];
      const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

      bars.forEach((b, i) => {
        b.style.background = (i < score) ? colors[score] : 'var(--border)';
      });
      label.textContent = val.length ? labels[score] : '';
      label.style.color = colors[score] || 'var(--text-muted)';
    }
  </script>
</body>
</html>
