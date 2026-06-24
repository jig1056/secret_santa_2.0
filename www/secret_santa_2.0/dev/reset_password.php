<?php
// ============================================================
// reset_password.php
// User clicks the link from their email and sets a new password.
// ============================================================ 
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Session already started by config.php — just check if logged in
if (!empty($_SESSION['USER_ID'])) {
    header('Location: ' . APP_URL . '/pages/home.php');
    exit;
}

$pdo     = getDB();
$msg     = '';
$msgType = '';
$token   = trim($_GET['token'] ?? '');
$valid   = false;
$reset   = null;

// -- Validate token --
if ($token) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.FIRST_NAME, u.LAST_NAME, u.EMAIL
        FROM SS_PASSWORD_RESETS r
        JOIN SS_USERS u ON u.USER_ID = r.USER_ID
        WHERE r.TOKEN = ?
          AND r.USED_AT IS NULL
          AND r.EXPIRES_AT > NOW()
          AND u.STATUS = 'ACTIVE'
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    $valid = (bool)$reset;
}

if (!$token || !$valid) {
    $msg     = 'This password reset link is invalid or has expired. Please request a new one.';
    $msgType = 'error';
}

// -- Handle POST: set new password --
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $newPass     = trim($_POST['new_password']     ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if (!$newPass || !$confirmPass) {
        $msg     = 'Please enter and confirm your new password.';
        $msgType = 'error';
    } elseif (strlen($newPass) < 8) {
        $msg     = 'Password must be at least 8 characters.';
        $msgType = 'error';
    } elseif ($newPass !== $confirmPass) {
        $msg     = 'Passwords do not match.';
        $msgType = 'error';
    } else {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);

        // Update password
        $pdo->prepare("UPDATE SS_USERS SET PASSWORD_HASH = ?, UPDATED_AT = NOW() WHERE USER_ID = ?")
            ->execute([$hash, $reset['USER_ID']]);

        // Mark token as used
        $pdo->prepare("UPDATE SS_PASSWORD_RESETS SET USED_AT = NOW() WHERE TOKEN = ?")
            ->execute([$token]);

        $msg     = 'Your password has been reset successfully. You can now sign in.';
        $msgType = 'success';
        $valid   = false; // hide the form
    }
}

$xmasYear = getConfig('XMAS_YEAR', date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= h(APP_NAME) ?> <?= h($xmasYear) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        body { justify-content: center; align-items: center; background: #7b1212; overflow: hidden; }
        #snowCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        .login-card { position: relative; z-index: 1; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.35); padding: 2rem; width: 100%; max-width: 400px; margin: 2rem auto; }
        .login-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: #922b21; margin-bottom: 0.25rem; }
        .login-sub   { text-align: center; color: #6c757d; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .back-link   { text-align: center; margin-top: 1rem; font-size: 0.85rem; }
        .back-link a { color: #c0392b; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .match-msg  { font-size: 0.82rem; margin-top: 0.3rem; font-weight: 600; }
        .match-ok   { color: #1e8449; }
        .match-fail { color: #c0392b; }
    </style>
</head>
<body>
<canvas id="snowCanvas"></canvas>
<div class="login-card">
    <div class="login-title"><span style="font-size:175%;vertical-align:middle;line-height:1;">🎅🏾</span> Reset Password</div>

    <?php if ($reset && $valid): ?>
    <div class="login-sub">Hi <?= h($reset['FIRST_NAME']) ?>, set your new password below.</div>
    <?php else: ?>
    <div class="login-sub">Secret Santa <?= h($xmasYear) ?></div>
    <?php endif; ?>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
    <?php endif; ?>

    <?php if ($valid): ?>
    <form method="POST" action="">
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password"
                   required minlength="8" placeholder="Min 8 characters"
                   autocomplete="new-password"
                   oninput="checkMatch()">
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required placeholder="Re-enter new password"
                   autocomplete="new-password"
                   oninput="checkMatch()">
            <div id="matchMsg" class="match-msg" style="display:none;"></div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
            Set New Password
        </button>
    </form>
    <?php endif; ?>

    <div class="back-link">
        <?php if ($msgType === 'success'): ?>
        <a href="<?= APP_URL ?>/index.php">Sign In with your new password →</a>
        <?php else: ?>
        <a href="<?= APP_URL ?>/forgot_password.php">Request a new reset link</a>
        &nbsp;·&nbsp;
        <a href="<?= APP_URL ?>/index.php">← Back to Sign In</a>
        <?php endif; ?>
    </div>
</div>

<script>
function checkMatch() {
    const np  = document.getElementById('new_password').value;
    const cp  = document.getElementById('confirm_password').value;
    const msg = document.getElementById('matchMsg');
    if (!cp) { msg.style.display = 'none'; return; }
    msg.style.display = 'block';
    if (np === cp) {
        msg.textContent = '✓ Passwords match';
        msg.className   = 'match-msg match-ok';
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.className   = 'match-msg match-fail';
    }
}
</script>

<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
<script>
// ── Snow animation ────────────────────────────────────────────
(function () {
    const canvas = document.getElementById('snowCanvas');
    const ctx    = canvas.getContext('2d');
    const COUNT  = 60;
    let W, H, flakes;

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function randomFlake(scatterY) {
        const size = Math.random() * 10 + 6;
        return {
            x:       Math.random() * W,
            y:       scatterY ? Math.random() * H : -20,
            size:    size,
            speed:   Math.random() * 0.6 + 0.25,
            drift:   Math.random() * 0.4 - 0.2,
            sway:    Math.random() * Math.PI * 2,
            swaySpd: Math.random() * 0.008 + 0.003,
            rot:     Math.random() * Math.PI / 6,
            rotSpd:  (Math.random() - 0.5) * 0.004,
            opacity: Math.random() * 0.45 + 0.35,
        };
    }

    function drawFlake(f) {
        const { x, y, size, rot, opacity } = f;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(rot);
        ctx.strokeStyle = `rgba(255,255,255,${opacity})`;
        ctx.lineWidth   = Math.max(0.8, size * 0.08);
        ctx.lineCap     = 'round';

        for (let i = 0; i < 6; i++) {
            ctx.save();
            ctx.rotate((Math.PI / 3) * i);

            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -size);
            ctx.stroke();

            [0.55, 0.78].forEach(pct => {
                const bLen = size * 0.3;
                const py   = -size * pct;
                ctx.beginPath();
                ctx.moveTo(0, py);
                ctx.lineTo( bLen * 0.6, py - bLen * 0.6);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(0, py);
                ctx.lineTo(-bLen * 0.6, py - bLen * 0.6);
                ctx.stroke();
            });

            ctx.restore();
        }
        ctx.restore();
    }

    function init() {
        resize();
        flakes = Array.from({ length: COUNT }, () => randomFlake(true));
    }

    function tick() {
        ctx.clearRect(0, 0, W, H);
        flakes.forEach(f => {
            drawFlake(f);

            f.sway += f.swaySpd;
            f.x    += Math.sin(f.sway) * 0.7 + f.drift;
            f.y    += f.speed;
            f.rot  += f.rotSpd;

            if (f.y > H + 20) Object.assign(f, randomFlake(false));
            if (f.x > W + 20) f.x = -20;
            if (f.x < -20)    f.x = W + 20;
        });
        requestAnimationFrame(tick);
    }

    window.addEventListener('resize', resize);
    init();
    tick();
})();
</script>
</body>
</html>
