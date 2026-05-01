<?php
// =============================================================
//  test_email.php — Quick smoke-test for PHPMailer setup
//  Visit: http://localhost/new_pms_ag_new/test_email.php
//
//  DELETE THIS FILE before going to production!
// =============================================================

// Check Composer autoload exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("<h2 style='color:red;font-family:sans-serif;padding:20px'>
        ❌ <b>vendor/autoload.php not found.</b><br><br>
        You need to install PHPMailer first.<br>
        Open <b>Command Prompt</b> and run:<br><br>
        <code style='background:#eee;padding:6px 12px;border-radius:4px;font-size:15px'>
            cd C:\\xampp\\htdocs\\new_pms_ag_new<br>
            composer require phpmailer/phpmailer
        </code>
    </h2>");
}

require_once __DIR__ . '/config/mailer.php';

// ── Send a test email to the FROM address itself ──────────────
$to      = SMTP_FROM_EMAIL;          // sends to yourself
$subject = '✅ PMS Email Test — ' . date('d M Y H:i:s');
$body    = '
<!DOCTYPE html><html><body style="font-family:sans-serif;padding:30px">
  <h2 style="color:#1a3c5e">✅ PHPMailer is working!</h2>
  <p>This test email was sent from your <b>PMS (Placement Management System)</b> on ' . date('d M Y \a\t H:i:s') . '.</p>
  <p>SMTP Host: <b>' . SMTP_HOST . ':' . SMTP_PORT . '</b><br>
     From: <b>' . SMTP_FROM_NAME . ' &lt;' . SMTP_FROM_EMAIL . '&gt;</b></p>
  <hr>
  <p style="color:#888;font-size:12px">JSS SMC MCA Institute, Dharwad — Placement Management System</p>
</body></html>';

$result = sendEmail($to, $subject, $body);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PMS Email Test</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5;
           display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card { background: #fff; border-radius: 14px; padding: 40px 48px;
            box-shadow: 0 6px 28px rgba(0,0,0,0.10); max-width: 560px; width: 95%; text-align: center; }
    .icon { font-size: 52px; margin-bottom: 16px; }
    h2 { font-size: 22px; margin-bottom: 10px; }
    p  { font-size: 14px; color: #555; line-height: 1.7; }
    .meta { background: #f8f9fb; border-radius: 8px; padding: 14px 18px;
            margin: 20px 0; font-size: 13px; color: #444; text-align: left; }
    .meta span { display: block; margin: 4px 0; }
    .warn { background: #fff3e0; border: 1px solid #ffb74d; border-radius: 8px;
            padding: 12px 16px; font-size: 12px; color: #e65100; margin-top: 18px; }
    code { background: #eee; padding: 2px 7px; border-radius: 4px; font-size: 12px; }
  </style>
</head>
<body>
<div class="card">

<?php if ($result): ?>
  <div class="icon">✅</div>
  <h2 style="color:#2e7d32">Email sent successfully!</h2>
  <p>Check your inbox at <b><?= htmlspecialchars(SMTP_FROM_EMAIL) ?></b>.<br>
     It may take up to 60 seconds to arrive.</p>
  <div class="meta">
    <span>📮 <b>To:</b> <?= htmlspecialchars($to) ?></span>
    <span>📝 <b>Subject:</b> <?= htmlspecialchars($subject) ?></span>
    <span>🔌 <b>SMTP:</b> <?= SMTP_HOST ?>:<?= SMTP_PORT ?> (STARTTLS)</span>
    <span>👤 <b>From:</b> <?= htmlspecialchars(SMTP_FROM_NAME) ?> &lt;<?= htmlspecialchars(SMTP_FROM_EMAIL) ?>&gt;</span>
  </div>

<?php else: ?>
  <div class="icon">❌</div>
  <h2 style="color:#c62828">Email failed to send</h2>
  <p>Check <code>logs/mail_errors.log</code> for the exact SMTP error.</p>
  <div class="meta">
    <span>🔌 <b>SMTP:</b> <?= SMTP_HOST ?>:<?= SMTP_PORT ?></span>
    <span>👤 <b>Username:</b> <?= htmlspecialchars(SMTP_USERNAME) ?></span>
  </div>
  <div style="text-align:left;margin-top:16px">
    <p style="font-size:13px;font-weight:600;color:#333;margin-bottom:8px">Common fixes:</p>
    <p style="font-size:13px;color:#555">
      1. <b>SMTP_USERNAME</b> must be the full Gmail address (e.g. <code>you@gmail.com</code>)<br><br>
      2. <b>SMTP_PASSWORD</b> must be a Gmail <b>App Password</b> (16 chars, no spaces)<br>
         → Google Account → Security → App Passwords → Mail<br><br>
      3. <b>2-Step Verification</b> must be enabled on the Google account<br><br>
      4. Make sure <b>XAMPP Apache</b> can reach the internet (firewall / antivirus)
    </p>
  </div>
<?php endif; ?>

  <div class="warn">
    ⚠ <b>Delete <code>test_email.php</code> before deploying to production.</b>
  </div>
</div>
</body>
</html>
