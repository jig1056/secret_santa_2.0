<?php
// ============================================================
// includes/mailer.php
// PHPMailer wrapper. Call sendMail() to send an email.
// Credentials are pulled from SS_CONFIG.
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// ------------------------------------------------------------
// sendMail()
// $to      - recipient email address
// $toName  - recipient display name
// $subject - email subject line
// $body    - plain text email body
//
// Returns true on success, error string on failure.
// ------------------------------------------------------------
function sendMail(string $to, string $toName, string $subject, string $body): bool|string {
    $mail = new PHPMailer(true);

    try {
        // -- Server settings --
        if (IS_DEV) {
            $mail->SMTPDebug = 0; // Set to 2 to see full SMTP debug output in dev
        }

        $mail->isSMTP();
        $mail->Host       = getConfig('MAIL_HOST',       'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = getConfig('MAIL_USERNAME',   '');
        $mail->Password   = getConfig('MAIL_PASSWORD',   '');
        $mail->SMTPSecure = getConfig('MAIL_ENCRYPTION', 'tls');
        $mail->Port       = (int) getConfig('MAIL_PORT', '587');
        $mail->Encoding   = '7bit';

        // -- Recipients --
        $mail->setFrom(
            getConfig('MAIL_FROM_EMAIL', 'noreply@example.com'),
            getConfig('MAIL_FROM_NAME',  'Secret Santa Admin')
        );
        $mail->addAddress($to, $toName);
        $mail->addReplyTo(
            getConfig('MAIL_REPLY_TO', getConfig('MAIL_FROM_EMAIL', 'noreply@example.com')),
            getConfig('MAIL_FROM_NAME', 'Secret Santa Admin')
        );

        // -- Content --
        $mail->isHTML(false); // Plain text
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error to ' . $to . ': ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}