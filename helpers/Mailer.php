<?php
/**
 * ================================================
 * INVESTHOOD IT - PHPMailer Wrapper
 * ================================================
 * Sends emails via SMTP using PHPMailer. Falls
 * back to PHP's mail() if PHPMailer is unavailable
 * or if MAIL_ENABLED is false (logs to file).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Send an email.
     *
     * @param string $to       Recipient email
     * @param string $toName   Recipient name
     * @param string $subject  Subject line
     * @param string $body     HTML body
     * @param string $altBody  Plain-text fallback
     * @return bool
     */
    public static function send(string $to, string $toName, string $subject, string $body, string $altBody = ''): bool
    {
        // If mail is not enabled, log it
        if (!MAIL_ENABLED) {
            self::log($to, $subject, $body);
            return true; // Return true to allow development flow
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            // $mail->SMTPDebug = 2; // Enable for debugging
            $mail->Host       = MAIL_HOST;
            $mail->Port       = MAIL_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('[Mailer] PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an email verification link.
     *
     * @param string $to
     * @param string $name
     * @param string $token
     * @return bool
     */
    public static function sendVerificationEmail(string $to, string $name, string $token): bool
    {
        $link = APP_URL . '/auth/verify_email.php?token=' . urlencode($token);
        $subject = 'Verify your email — ' . APP_NAME;

        $body = self::template(
            'Email Verification',
            'Hello ' . htmlspecialchars($name) . ',',
            'Thank you for registering with ' . APP_NAME . '. Please click the button below to verify your email address and activate your account.',
            'Verify Email Address',
            $link,
            'If you did not create an account, you can safely ignore this email. The link will expire in ' . EMAIL_VERIFY_EXPIRY_HOURS . ' hours.'
        );

        $altBody = "Verify your email: {$link}\n\n"
                 . "Thank you for registering. Please visit the link above to verify your email address.";

        return self::send($to, $name, $subject, $body, $altBody);
    }

    /**
     * Send a password reset link.
     *
     * @param string $to
     * @param string $name
     * @param string $token
     * @return bool
     */
    public static function sendPasswordResetEmail(string $to, string $name, string $token): bool
    {
        $link = APP_URL . '/reset-password.php?token=' . urlencode($token);
        $subject = 'Reset your password — ' . APP_NAME;

        $body = self::template(
            'Password Reset',
            'Hello ' . htmlspecialchars($name) . ',',
            'We received a request to reset your password. Click the button below to choose a new password. The link will expire in ' . PASSWORD_RESET_EXPIRY_HOURS . ' hour(s).',
            'Reset Password',
            $link,
            'If you did not request a password reset, please ignore this email. Your password will remain unchanged.'
        );

        $altBody = "Reset your password: {$link}\n\n"
                 . "We received a request to reset your password. The link expires in " . PASSWORD_RESET_EXPIRY_HOURS . " hour(s).";

        return self::send($to, $name, $subject, $body, $altBody);
    }

    /**
     * Simple HTML email template.
     */
    private static function template(string $title, string $greeting, string $intro, string $buttonText, string $buttonUrl, string $footerNote): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
  body { font-family: 'Segoe UI', Tahoma, sans-serif; background:#f4f7fc; margin:0; padding:0; }
  .container{ max-width:600px; margin:40px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
  .header{ background:#1a56db; padding:32px; text-align:center; }
  .header h1{ color:#fff; margin:0; font-size:24px; }
  .body{ padding:32px; color:#334155; font-size:16px; line-height:1.6; }
  .btn{ display:inline-block; background:#1a56db; color:#fff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; }
  .btn:hover{ background:#1648c0; }
  .footer{ padding:24px 32px; background:#f8fafc; font-size:13px; color:#94a3b8; text-align:center; border-top:1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="container">
  <div class="header"><h1>{$title}</h1></div>
  <div class="body">
    <p>{$greeting}</p>
    <p>{$intro}</p>
    <p style="text-align:center;margin:32px 0;"><a href="{$buttonUrl}" class="btn">{$buttonText}</a></p>
    <p style="font-size:14px;color:#64748b;">{$footerNote}</p>
  </div>
  <div class="footer"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Log email to file when mail is disabled.
     */
    private static function log(string $to, string $subject, string $body): void
    {
        $logDir = __DIR__ . '/../uploads';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/email_log.txt';
        $entry = "[" . date('Y-m-d H:i:s') . "] TO: {$to} | SUBJECT: {$subject}\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
