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
// sendPasswordReset()
// Generates a reset token, stores it, finds the Password Reset
// message template, substitutes placeholders, and emails the user.
// Returns true on success or an error string on failure.
// ------------------------------------------------------------
function sendPasswordReset(array $user, PDO $pdo): bool|string {
    // Get expiry from config
    $expiryMins = (int) getConfig('RESET_TOKEN_EXPIRY_MINS', PASSWORD_RESET_EXPIRY_FALLBACK);

    // Clear old tokens for this user
    $pdo->prepare("DELETE FROM SS_PASSWORD_RESETS WHERE USER_ID = ?")
        ->execute([$user['USER_ID']]);

    // Generate token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + ($expiryMins * 60));
    $pdo->prepare("INSERT INTO SS_PASSWORD_RESETS (USER_ID, TOKEN, EXPIRES_AT) VALUES (?, ?, ?)")
        ->execute([$user['USER_ID'], $token, $expires]);

    $resetLink = APP_URL . '/reset_password.php?token=' . $token;

    // Load the Password Reset message template
    $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_NAME = 'Password Reset' LIMIT 1");
    $stmt->execute();
    $template = $stmt->fetch();

    if ($template) {
        $body = str_replace(
            ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}', '{PASSWORD_RESET_LINK}', '{RESET_EXPIRY_MINS}', '{GIFT_DEADLINE}', '{SANTA_MATCH_DATE}'],
            [$user['FIRST_NAME'], $user['LAST_NAME'], getConfig('XMAS_YEAR', date('Y')), $resetLink, $expiryMins, getConfig('GIFT_DEADLINE', 'TBD'), getConfig('SANTA_MATCH_DATE', 'TBD')],
            $template['MESSAGE_BODY']
        );
        $subject = $template['MESSAGE_NAME'] . ' — ' . getConfig('MAIL_FROM_NAME', 'Secret Santa');
    } else {
        // Fallback body if template not found
        $body    = "Hi {$user['FIRST_NAME']},

Click the link below to reset your password (expires in {$expiryMins} minutes):

{$resetLink}

— Secret Santa Admin";
        $subject = 'Password Reset — ' . getConfig('MAIL_FROM_NAME', 'Secret Santa');
    }

    return sendMail($user['EMAIL'], $user['FIRST_NAME'] . ' ' . $user['LAST_NAME'], $subject, $body);
}

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