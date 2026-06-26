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
// Wraps email content in the Secret Santa chrome:
//   dark-red header → warm-cream body → near-black footer.
//
// $title      - main heading shown in the cream body
// $subtitle   - gold label shown above the app name in the red header
//               (e.g. "MATCH NOTIFICATION", "PASSWORD RESET")
// $bodyText   - message body; plain text unless $bodyIsHtml = true
// $year       - year shown in the footer
// $bodyIsHtml - set true when $bodyText is pre-built HTML
// $firstName  - when provided, adds a "HELLO, {NAME}!" greeting
//
// Spark/iOS-Mail rules applied here:
//   • always set bgcolor attribute alongside style background-color
//   • no opacity (Spark silently drops the element)
//   • no border-radius
//   • no CSS background shorthand — use background-color
// ------------------------------------------------------------
function wrapHtmlEmail(string $title, string $subtitle, string $bodyText, string $year, bool $bodyIsHtml = false, string $firstName = ''): string {
    $appName   = defined('APP_NAME') ? APP_NAME : 'Secret Santa';
    $safeApp   = htmlspecialchars($appName,               ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title,                 ENT_QUOTES, 'UTF-8');
    $safeSub   = htmlspecialchars(strtoupper($subtitle),  ENT_QUOTES, 'UTF-8');
    $safeYear  = htmlspecialchars($year,                  ENT_QUOTES, 'UTF-8');
    $safeBody  = $bodyIsHtml ? $bodyText : nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'));

    // Optional supertitle in red header (e.g. "✦ MATCH NOTIFICATION ✦")
    $supertitleHtml = $safeSub ? "
            <p style=\"margin:0 0 10px 0;color:#d4a843;font-family:Arial,Helvetica,sans-serif;font-size:10px;letter-spacing:4px;text-align:center;\">
              &#10022; &nbsp; {$safeSub} &nbsp; &#10022;
            </p>" : '';

    // Optional personalised greeting ("HELLO, MARK!")
    $greetingHtml = $firstName ? "
            <p style=\"margin:0 0 14px 0;color:#c9a227;font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;text-align:center;\">
              HELLO, " . htmlspecialchars(strtoupper($firstName), ENT_QUOTES, 'UTF-8') . "!
            </p>" : '';

    return "
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#f0e9d8\"
       style=\"background-color:#f0e9d8;font-family:Arial,Helvetica,sans-serif;\">
  <tr>
    <td align=\"center\" style=\"padding:24px 16px;\">

      <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"
             style=\"width:100%;max-width:600px;\">

        <!-- ── RED HEADER ── -->
        <tr>
          <td bgcolor=\"#b22020\" align=\"center\"
              style=\"background-color:#b22020;padding:28px 32px 24px 32px;\">{$supertitleHtml}
            <h1 style=\"margin:0;color:#ffffff;font-family:Georgia,'Times New Roman',serif;font-size:28px;font-weight:bold;line-height:1.2;\">
              &#127873; {$safeApp}
            </h1>
          </td>
        </tr>

        <!-- ── CREAM BODY ── -->
        <tr>
          <td bgcolor=\"#f0e9d8\" align=\"center\"
              style=\"background-color:#f0e9d8;padding:36px 40px 32px 40px;\">
            <p style=\"margin:0 0 20px 0;color:#c9a227;font-size:20px;text-align:center;letter-spacing:10px;\">&#10052; &#10052; &#10052;</p>
            {$greetingHtml}
            <h2 style=\"margin:0 0 24px 0;color:#1c0f0c;font-family:Georgia,'Times New Roman',serif;font-size:24px;line-height:1.3;text-align:center;\">{$safeTitle}</h2>
            <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
              <tr>
                <td style=\"border-top:1px solid #d9ceb8;padding-top:24px;color:#444444;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;text-align:left;\">
                  {$safeBody}
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ── DARK FOOTER ── -->
        <tr>
          <td bgcolor=\"#1c0f0c\" align=\"center\"
              style=\"background-color:#1c0f0c;padding:18px 32px;\">
            <p style=\"margin:0;color:#d4a843;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:1px;\">
              Sent from {$safeApp} &bull; {$safeYear}
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>";
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

    $xmasYear = getConfig('XMAS_YEAR', date('Y'));

    if ($template) {
        $plainBody = str_replace(
            ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}', '{PASSWORD_RESET_LINK}', '{RESET_EXPIRY_MINS}', '{RESET_TOKEN_EXPIRY_MINS}', '{GIFT_DEADLINE}', '{SANTA_MATCH_DATE}'],
            [$user['FIRST_NAME'], $user['LAST_NAME'], $xmasYear, $resetLink, $expiryMins, $expiryMins, getConfig('GIFT_DEADLINE', 'TBD'), getConfig('SANTA_MATCH_DATE', 'TBD')],
            $template['MESSAGE_BODY']
        );
        $subject = $template['MESSAGE_NAME'] . ' - ' . getConfig('MAIL_SUBJECT', 'Secret Santa');
    } else {
        // Fallback body if template not found
        $plainBody = "Hi {$user['FIRST_NAME']},\n\nClick the link below to reset your password (expires in {$expiryMins} minutes):\n\n{$resetLink}\n\nIf you did not request this, you can ignore this email.";
        $subject   = 'Password Reset - ' . getConfig('MAIL_SUBJECT', 'Secret Santa');
    }

    // Build HTML body: escape text, nl2br, then make any URLs clickable links
    $htmlBody = nl2br(htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8'));
    $htmlBody = preg_replace(
        '~(https?://[^\s<]+)~',
        '<a href="$1" style="color:#c0392b;word-break:break-all;">$1</a>',
        $htmlBody
    );

    $wrappedBody = wrapHtmlEmail(
        getConfig('MAIL_SUBJECT', 'Secret Santa'),
        $template['MESSAGE_NAME'] ?? 'Password Reset',
        $htmlBody,
        $xmasYear,
        true,               // body is already HTML
        $user['FIRST_NAME']
    );

    return sendMail($user['EMAIL'], $user['FIRST_NAME'] . ' ' . $user['LAST_NAME'], $subject, $wrappedBody, true);
}

// ------------------------------------------------------------
// sendMail()
// Single-send helper. Opens and closes one SMTP connection.
// Use for individual transactional emails (password reset, etc.)
// For bulk sending use createBulkMailer() + sendMailBulk().
//
// Returns true on success, error string on failure.
// ------------------------------------------------------------
function sendMail(string $to, string $toName, string $subject, string $body, bool $isHtml = false): bool|string {
    $mail = new PHPMailer(true);

    try {
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

        $mail->setFrom(
            getConfig('MAIL_FROM_EMAIL', 'noreply@example.com'),
            getConfig('MAIL_FROM_NAME',  'Secret Santa Admin')
        );
        $mail->addAddress($to, $toName);
        $mail->addReplyTo(
            getConfig('MAIL_REPLY_TO', getConfig('MAIL_FROM_EMAIL', 'noreply@example.com')),
            getConfig('MAIL_FROM_NAME', 'Secret Santa Admin')
        );

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer error to ' . $to . ': ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}

// ------------------------------------------------------------
// createBulkMailer()
// Returns a pre-configured PHPMailer instance with SMTP
// keepalive enabled. The SMTP connection is opened once and
// reused for every message — call smtpClose() when done.
// ------------------------------------------------------------
function createBulkMailer(): PHPMailer {
    $mail = new PHPMailer(true);

    if (IS_DEV) {
        $mail->SMTPDebug = 0;
    }

    $mail->isSMTP();
    $mail->Host          = getConfig('MAIL_HOST',       'smtp.gmail.com');
    $mail->SMTPAuth      = true;
    $mail->Username      = getConfig('MAIL_USERNAME',   '');
    $mail->Password      = defined('MAIL_PASSWORD_SECRET') && MAIL_PASSWORD_SECRET ? MAIL_PASSWORD_SECRET : getConfig('MAIL_PASSWORD', '');
    $mail->SMTPSecure    = getConfig('MAIL_ENCRYPTION', 'tls');
    $mail->Port          = (int) getConfig('MAIL_PORT', '587');
    $mail->Encoding      = '7bit';
    $mail->SMTPKeepAlive = true; // keep connection open across multiple sends

    $mail->setFrom(
        getConfig('MAIL_FROM_EMAIL', 'noreply@example.com'),
        getConfig('MAIL_FROM_NAME',  'Secret Santa Admin')
    );

    return $mail;
}

// ------------------------------------------------------------
// sendMailBulk()
// Send one email using a keepalive mailer created by
// createBulkMailer(). Clears addresses between calls so the
// same instance can be reused in a loop.
//
// Returns true on success, error string on failure.
// ------------------------------------------------------------
function sendMailBulk(PHPMailer $mail, string $to, string $toName, string $subject, string $body, bool $isHtml = false): bool|string {
    try {
        $mail->clearAddresses();
        $mail->clearReplyTos();

        $mail->addAddress($to, $toName);
        $mail->addReplyTo(
            getConfig('MAIL_REPLY_TO', getConfig('MAIL_FROM_EMAIL', 'noreply@example.com')),
            getConfig('MAIL_FROM_NAME', 'Secret Santa Admin')
        );

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('PHPMailer bulk error to ' . $to . ': ' . $mail->ErrorInfo);
        return $mail->ErrorInfo;
    }
}