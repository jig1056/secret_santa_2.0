<?php
// ============================================================
// forgot_password.php
// User enters their email, gets a password reset link sent.
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/mailer.php';

// Already logged in? Go home
session_name(SESSION_NAME);
session_start();
if (!empty($_SESSION['USER_ID'])) {
    header('Location: ' . APP_URL . '/pages/home.php');
    exit;
}

$msg     = '';
$msgType = '';
$sent    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $msg     = 'Please enter your email address.';
        $msgType = 'error';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE EMAIL = ? AND STATUS = 'ACTIVE'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show the same success message whether email exists or not
        // (prevents email enumeration)
        // (prevents email enumeration)
        if ($user) {
            sendPasswordReset($user, $pdo);
        }

        $sent    = true;
        $msg     = 'If that email address is in our system you will receive a password reset link shortly.';
        $msgType = 'success';
    }
}

$xmasYear = getConfig('XMAS_YEAR', date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= h(APP_NAME) ?> <?= h($xmasYear) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        body { justify-content: center; align-items: center; background: #b71c1c; }
        .login-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.25); padding: 2rem; width: 100%; max-width: 400px; margin: 2rem auto; }
        .login-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: #922b21; margin-bottom: 0.25rem; }
        .login-sub   { text-align: center; color: #6c757d; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .back-link   { text-align: center; margin-top: 1rem; font-size: 0.85rem; }
        .back-link a { color: #c0392b; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-title">🎅🏾 Forgot Password</div>
    <div class="login-sub">Enter your email and we'll send you a reset link.</div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if (!$sent): ?>
    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required autocomplete="email"
                   value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
            Send Reset Link
        </button>
    </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="<?= APP_URL ?>/index.php">← Back to Sign In</a>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
