<?php
// =============================================================
//  config/mailer.php — Reusable PHPMailer wrapper
//
//  Usage:
//    require_once __DIR__ . '/mailer.php';
//    $ok = sendEmail('student@example.com', 'Subject', '<p>HTML body</p>');
//
//  Returns true on success, false on failure.
//  All errors are logged to logs/mail_errors.log (project root).
// =============================================================

require_once __DIR__ . '/email_config.php';

// Autoload PHPMailer via Composer
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    error_log('[PHPMailer] vendor/autoload.php not found — run: composer require phpmailer/phpmailer');
    if (!function_exists('sendEmail')) {
        function sendEmail(string $to, string $subject, string $body): bool { return false; }
    }
    return;
}
require_once $composerAutoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an HTML email via Gmail SMTP.
 *
 * @param  string $to      Recipient email address.
 * @param  string $subject Email subject.
 * @param  string $body    Full HTML email body (inline CSS only).
 * @return bool            true on success, false on failure.
 */
function sendEmail(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true); // true = enable exceptions

    try {
        // ── Server settings ──────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587

        // ── Sender / recipient ───────────────────────────────
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // ── Content ──────────────────────────────────────────
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Log the error — never expose to the end user
        $logDir  = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/mail_errors.log';
        $entry   = sprintf(
            "[%s] TO=%s | SUBJECT=%s | ERROR=%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $mail->ErrorInfo
        );
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        return false;
    }
}
