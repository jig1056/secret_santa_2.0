<?php
// ============================================================
// index.php -- Login page
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Already logged in? Go to home
if (!empty($_SESSION['USER_ID'])) {
    redirect('/pages/home.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $rememberMe = !empty($_POST['remember_me']);

    if ($email && $password) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE EMAIL = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['PASSWORD_HASH'])) {
            if ($user['STATUS'] !== 'ACTIVE') {
                $error = 'Your account is inactive. Please contact the administrator.';
            } else {
                loginUser($user, $rememberMe);
                redirect('/pages/home.php');
            }
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}

$xmasYear = '2026'; // fallback before DB is available
try {
    $xmasYear = getConfig('XMAS_YEAR', date('Y'));
} catch (Exception $e) {}

$timeout = isset($_GET['reason']) && $_GET['reason'] === 'timeout';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Secret Santa <?= h($xmasYear) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        body { justify-content: center; align-items: center; background: #7b1212; overflow: hidden; }
        #snowCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        .login-card { position: relative; z-index: 1; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.35); padding: 2rem; width: 100%; max-width: 400px; margin: 2rem auto; }
        .login-title { text-align: center; font-size: 1.5rem; font-weight: 700; color: #922b21; margin-bottom: 0.25rem; }
        .login-sub   { text-align: center; color: #6c757d; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .remember-row   { margin: 0.75rem 0 0.25rem; }
        .remember-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: #555; cursor: pointer; }
        .remember-label input { width: auto; margin: 0; }
    </style>
</head>
<body>
<canvas id="snowCanvas"></canvas>
<div class="login-card">
    <div class="login-title"><span style="font-size:175%;vertical-align:middle;line-height:1;">🎅🏾</span> Secret Santa <?= h($xmasYear) ?></div>
    <div class="login-sub">Sign in to get started</div>

    <?php if ($timeout): ?>
    <div class="alert alert-info">Your session expired. Please sign in again.</div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required autocomplete="email"
                   value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <div class="remember-row">
            <label class="remember-label">
                <input type="checkbox" id="remember_me" name="remember_me" value="1">
                Remember me for 60 days
            </label>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">Sign In</button>
    </form>

    <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
        <a href="<?= APP_URL ?>/forgot_password.php" style="color:#c0392b;">Forgot your password?</a>
    </p>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
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
        const size = Math.random() * 10 + 6; // 6–16px arm length
        return {
            x:       Math.random() * W,
            y:       scatterY ? Math.random() * H : -20,
            size:    size,
            speed:   Math.random() * 0.6 + 0.25,
            drift:   Math.random() * 0.4 - 0.2,
            sway:    Math.random() * Math.PI * 2,
            swaySpd: Math.random() * 0.008 + 0.003,
            rot:     Math.random() * Math.PI / 6, // slight initial rotation
            rotSpd:  (Math.random() - 0.5) * 0.004,
            opacity: Math.random() * 0.45 + 0.35,
        };
    }

    // Draw a 6-armed snowflake with small branch ticks
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

            // Main arm
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -size);
            ctx.stroke();

            // Two branch ticks at ~55% and ~80% of the arm
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

            if (f.y > H + 20) {
                Object.assign(f, randomFlake(false));
            }
            if (f.x > W + 20) f.x = -20;
            if (f.x < -20)    f.x = W + 20;
        });
        requestAnimationFrame(tick);
    }

    window.addEventListener('resize', resize);
    init();
    tick();
})();

// ── Auto-dismiss error alerts ─────────────────────────────────
(function () {
    const alert = document.querySelector('.alert-error');
    if (alert) setTimeout(() => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 10000);
})();
</script>
</body>
</html>