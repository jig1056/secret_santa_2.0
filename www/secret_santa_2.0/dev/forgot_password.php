<?php
// ============================================================
// forgot_password.php
// User enters their email, gets a password reset link sent.
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/mailer.php';

// Already logged in? Go home — session already started by config.php
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
        body { justify-content: center; align-items: center; background: #7b1212; overflow: hidden; }
        #snowCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        .login-card { position: relative; z-index: 1; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.35); padding: 2rem; width: 100%; max-width: 400px; margin: 2rem auto; }
        .login-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: #922b21; margin-bottom: 0.25rem; }
        .login-sub   { text-align: center; color: #6c757d; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .back-link   { text-align: center; margin-top: 1rem; font-size: 0.85rem; }
        .back-link a { color: #c0392b; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<canvas id="snowCanvas"></canvas>
<div class="login-card">
    <div class="login-title"><span style="font-size:175%;vertical-align:middle;line-height:1;">🎅🏾</span> Forgot Password</div>
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
