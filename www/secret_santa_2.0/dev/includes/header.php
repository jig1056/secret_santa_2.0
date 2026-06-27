<?php
// ============================================================
// header.php
// Shared HTML header and navigation bar.
// Include at the top of every page AFTER requireLogin().
// ============================================================

require_once __DIR__ . '/helpers.php';

$xmasYear    = getConfig('XMAS_YEAR', date('Y'));
$currentUser = currentUserId();

// Only check matches for users who participate in Secret Santa
$userMatch = null;
if (hasRole('secret_santa') || hasRole('admin')) {
    $matchesDone = matchesGenerated();
    $userMatch   = $matchesDone ? getMatchForUser($currentUser) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?> <?= h($xmasYear) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= APP_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/images/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/images/apple-touch-icon.png">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <meta name="app-url"         content="<?= APP_URL ?>">
    <meta name="session-timeout" content="<?= SESSION_TIMEOUT ?>">
</head>
<body>

<nav class="navbar" style="position:relative;overflow:hidden;">
    <canvas id="navSnow" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:0;"></canvas>
    <div class="nav-brand" style="position:relative;z-index:1;">
        <a href="<?= APP_URL ?>/pages/home.php" class="nav-brand-link">
            <span class="santa-emoji">🎅🏾</span> <?= h(APP_NAME) ?> <?= h($xmasYear) ?>
        </a>
    </div>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" style="position:relative;z-index:1;">&#9776;</button>

    <ul class="nav-links" id="navLinks" style="position:relative;z-index:1;">
        <li><a href="<?= APP_URL ?>/pages/home.php">Home</a></li>

        <?php if (hasRole('secret_santa') || hasRole('admin') || hasRole('wishlist_only')): ?>
        <li><a href="<?= APP_URL ?>/pages/gift_list.php">My Wish List</a></li>
        <?php endif; ?>

        <?php if ($userMatch && (hasRole('secret_santa') || hasRole('admin'))): ?>
        <li>
            <a href="<?= APP_URL ?>/pages/giftee_list.php">
                <?= h($userMatch['FIRST_NAME']) ?>'s Wish List
            </a>
        </li>
        <?php endif; ?>

        <?php if (hasRole('wishlist_gifter')): ?>
        <li><a href="<?= APP_URL ?>/pages/wishlists.php">Kid's Christmas List</a></li>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <li class="nav-divider"></li>
        <li><a href="<?= APP_URL ?>/admin/users.php">Users</a></li>
        <li><a href="<?= APP_URL ?>/admin/messages.php">Messages</a></li>
        <li><a href="<?= APP_URL ?>/admin/generate.php">Generate Matches</a></li>
        <li><a href="<?= APP_URL ?>/admin/dashboard.php">Dashboard</a></li>
        <li><a href="<?= APP_URL ?>/admin/config_admin.php">Config</a></li>
        <?php endif; ?>

        <li class="nav-divider"></li>
        <li><a href="<?= APP_URL ?>/pages/profile.php">👤 <?= h($_SESSION['FIRST_NAME']) ?></a></li>
        <li><a href="<?= APP_URL ?>/logout.php">Logout</a></li>
    </ul>
</nav>
<script>
(function () {
    const canvas = document.getElementById('navSnow');
    const ctx    = canvas.getContext('2d');
    const COUNT  = 30;
    let W, H, flakes;

    function resize() {
        W = canvas.width  = canvas.offsetWidth;
        H = canvas.height = canvas.offsetHeight;
    }

    function randomFlake(scatter) {
        const size = Math.random() * 8 + 4;
        return {
            x:       Math.random() * W,
            y:       scatter ? Math.random() * H : -20,
            size:    size,
            speed:   Math.random() * 0.4 + 0.15,
            drift:   Math.random() * 0.4 - 0.2,
            sway:    Math.random() * Math.PI * 2,
            swaySpd: Math.random() * 0.008 + 0.003,
            rot:     Math.random() * Math.PI / 6,
            rotSpd:  (Math.random() - 0.5) * 0.004,
            opacity: Math.random() * 0.3 + 0.15,
        };
    }

    function drawFlake(f) {
        ctx.save();
        ctx.translate(f.x, f.y);
        ctx.rotate(f.rot);
        ctx.strokeStyle = 'rgba(255,255,255,' + f.opacity + ')';
        ctx.lineWidth   = Math.max(0.7, f.size * 0.08);
        ctx.lineCap     = 'round';
        for (let i = 0; i < 6; i++) {
            ctx.save();
            ctx.rotate((Math.PI / 3) * i);
            ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(0, -f.size); ctx.stroke();
            [0.55, 0.78].forEach(function(pct) {
                const bLen = f.size * 0.3, py = -f.size * pct;
                ctx.beginPath(); ctx.moveTo(0, py); ctx.lineTo( bLen * 0.6, py - bLen * 0.6); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(0, py); ctx.lineTo(-bLen * 0.6, py - bLen * 0.6); ctx.stroke();
            });
            ctx.restore();
        }
        ctx.restore();
    }

    function init() {
        resize();
        flakes = Array.from({ length: COUNT }, function() { return randomFlake(true); });
    }

    function tick() {
        ctx.clearRect(0, 0, W, H);
        flakes.forEach(function(f) {
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

<main class="container">