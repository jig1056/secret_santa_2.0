<?php
// ============================================================
// includes/mailer.php
// PHPMailer wrapper. Call sendMail() to send an email.
// Credentials are pulled from SS_CONFIG.
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client as TwilioClient;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// ------------------------------------------------------------
// formatE164()
// Strips non-digits from a phone number and prepends +1
// e.g. "813-555-0101" -> "+18135550101"
// ------------------------------------------------------------
function formatE164(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    // If already has country code (11 digits starting with 1), just prepend +
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return '+' . $digits;
    }
    // Otherwise assume US number, prepend +1
    return '+1' . $digits;
}

// ------------------------------------------------------------
// sendSMS()
// Sends an SMS via Twilio. Credentials pulled from Infisical
// via the session cache (same as email credentials).
//
// $to   - recipient phone number (any format, auto-converted)
// $body - message text
//
// Returns true on success, error string on failure.
// ------------------------------------------------------------
function sendSMS(string $to, string $body): bool|string {
    // Pull Twilio credentials from session (loaded via Infisical)
    $accountSid = $_SESSION['_infisical']['SS_TWILIO_ACCOUNT_SID'] ?? null;
    $authToken  = $_SESSION['_infisical']['SS_TWILIO_AUTH_TOKEN']  ?? null;
    $fromNumber = $_SESSION['_infisical']['SS_TWILIO_FROM_NUMBER'] ?? null;

    if (!$accountSid || !$authToken || !$fromNumber) {
        error_log('Twilio credentials missing from Infisical session cache.');
        return 'Twilio credentials not configured.';
    }

    $toFormatted = formatE164($to);
    if (strlen(preg_replace('/\D/', '', $toFormatted)) < 10) {
        return 'Invalid phone number: ' . $to;
    }

    try {
        $twilio  = new TwilioClient($accountSid, $authToken);
        $message = $twilio->messages->create($toFormatted, [
            'from' => $fromNumber,
            'body' => $body,
        ]);

        return $message->status === 'failed' ? 'Twilio error: ' . $message->errorMessage : true;

    } catch (\Exception $e) {
        error_log('Twilio SMS error to ' . $toFormatted . ': ' . $e->getMessage());
        return $e->getMessage();
    }
}

// ------------------------------------------------------------
// wrapHtmlEmail()
// Wraps a plain-text message body in the standard Secret Santa
// HTML email chrome (red header, white body, grey footer).
//
// $title    - heading text shown in the red banner
// $subtitle - smaller line below the heading
// $bodyText - raw plain text; will be HTML-escaped and nl2br'd
// $year     - year shown in the footer
// ------------------------------------------------------------
function wrapHtmlEmail(string $title, string $subtitle, string $bodyText, string $year): string {
    $appName   = defined('APP_NAME') ? APP_NAME : 'Secret Santa';
    $appUrl    = defined('APP_URL')  ? APP_URL  : '';
    $safeTitle = htmlspecialchars($title,    ENT_QUOTES, 'UTF-8');
    $safeSub   = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
    $safeBody  = nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'));
    $safeApp   = htmlspecialchars($appName,  ENT_QUOTES, 'UTF-8');
    $safeYear  = htmlspecialchars($year,     ENT_QUOTES, 'UTF-8');
    $logoUrl   = htmlspecialchars($appUrl . '/assets/images/apple-touch-icon.png', ENT_QUOTES, 'UTF-8');

    return "
<div style=\"font-family:Arial,sans-serif;max-width:680px;margin:0 auto;\">
    <div style=\"background:#c0392b;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0;display:flex;align-items:center;gap:14px;\">
        <img src=\"{$logoUrl}\" alt=\"\" width=\"48\" height=\"48\"
             style=\"border-radius:8px;flex-shrink:0;display:block;\">
        <div>
            <h2 style=\"margin:0;font-size:1.3rem;\">{$safeTitle}</h2>
            <p style=\"margin:4px 0 0;opacity:0.85;font-size:0.9rem;\">{$safeSub}</p>
        </div>
    </div>
    <div style=\"padding:24px;background:#fff;color:#333;font-size:0.97rem;line-height:1.7;\">
        {$safeBody}
    </div>
    <div style=\"background:#f5f5f5;padding:14px 24px;border-radius:0 0 8px 8px;font-size:0.82rem;color:#888;\">
        Sent from {$safeApp} &bull; {$safeYear}
    </div>
</div>";
}

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
        $subject = $template['MESSAGE_NAME'] . ' — ' . getConfig('MAIL_SUBJECT', 'Secret Santa');
    } else {
        // Fallback body if template not found
        $body    = "Hi {$user['FIRST_NAME']},

Click the link below to reset your password (expires in {$expiryMins} minutes):

{$resetLink}

— Secret Santa Admin";
        $subject = 'Password Reset — ' . getConfig('MAIL_SUBJECT', 'Secret Santa');
    }

    return sendMail($user['EMAIL'], $user['FIRST_NAME'] . ' ' . $user['LAST_NAME'], $subject, $body);
}

// ------------------------------------------------------------
// sendMail()
// $to      - recipient email address
// $toName  - recipient display name
// $subject - email subject line
// $body    - email body (plain text or HTML)
// $isHtml  - set true to send as HTML email (default: false)
//
// Returns true on success, error string on failure.
// ------------------------------------------------------------
function sendMail(string $to, string $toName, string $subject, string $body, bool $isHtml = false): bool|string {
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
        $mail->Password   = defined('MAIL_PASSWORD_SECRET') && MAIL_PASSWORD_SECRET ? MAIL_PASSWORD_SECRET : getConfig('MAIL_PASSWORD', '');
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
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        if ($isHtml) {
            $mail->AltBody = strip_tags($body); // plain-text fallback for non-HTML clients
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error to ' . $to . ': ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}