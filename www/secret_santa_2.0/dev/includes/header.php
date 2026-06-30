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

// Active page detection
$currentScript = basename($_SERVER['SCRIPT_NAME']);

function navActive(string $script): string {
    global $currentScript;
    return $currentScript === $script ? ' active' : '';
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
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=10">
    <meta name="app-url"         content="<?= APP_URL ?>">
    <meta name="session-timeout" content="<?= SESSION_TIMEOUT ?>">
</head>
<body>

<!-- Snowfall overlay — CSS animated, fixed over full page -->
<div class="snowfall" aria-hidden="true">
<?php
$snowChars = ['❄','❅','❆','✦'];
for ($i = 0; $i < 28; $i++) {
    $char  = $snowChars[$i % 4];
    $left  = ($i * 3 + 7) % 100;
    $dur   = 10 + ($i % 13);
    $delay = -(($i * 7) % 21);
    echo "    <span style=\"left:{$left}%;--dur:{$dur}s;--delay:{$delay}s;\">{$char}</span>\n";
}
?>
</div>

<header class="site-header">
    <div class="nav-ribbon"></div>

    <nav class="navbar">
        <!-- Brand -->
        <a href="<?= APP_URL ?>/pages/home.php" class="nav-brand-link">
            <span class="santa-emoji">🎅🏾</span>
            <span class="nav-brand-text"><?= h(APP_NAME) ?> <?= h($xmasYear) ?></span>
        </a>

        <!-- Hamburger (mobile) -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>

        <div class="nav-collapse" id="navCollapse">
            <!-- Main page links -->
            <ul class="nav-main">
                <li><a href="<?= APP_URL ?>/pages/home.php" class="nav-link<?= navActive('home.php') ?>">Home</a></li>

                <?php if (hasRole('secret_santa') || hasRole('admin') || hasRole('wishlist_only')): ?>
                <li><a href="<?= APP_URL ?>/pages/gift_list.php" class="nav-link<?= navActive('gift_list.php') ?>">My Wish List</a></li>
                <?php endif; ?>

                <?php if ($userMatch && (hasRole('secret_santa') || hasRole('admin'))): ?>
                <li>
                    <a href="<?= APP_URL ?>/pages/giftee_list.php" class="nav-link<?= navActive('giftee_list.php') ?>">
                        <?= h($userMatch['FIRST_NAME']) ?>'s Wish List
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasRole('wishlist_gifter')): ?>
                <li><a href="<?= APP_URL ?>/pages/wishlists.php" class="nav-link<?= navActive('wishlists.php') ?>">Kid's Christmas List</a></li>
                <?php endif; ?>
            </ul>

            <!-- Right side: admin links + user section -->
            <ul class="nav-end">
                <?php if (isAdmin()): ?>
                <li><a href="<?= APP_URL ?>/admin/users.php"        class="nav-link nav-link-sm<?= navActive('users.php') ?>">Users</a></li>
                <li><a href="<?= APP_URL ?>/admin/messages.php"     class="nav-link nav-link-sm<?= navActive('messages.php') ?>">Messages</a></li>
                <li><a href="<?= APP_URL ?>/admin/generate.php"     class="nav-link nav-link-sm<?= navActive('generate.php') ?>">Generate Matches</a></li>
                <li><a href="<?= APP_URL ?>/admin/dashboard.php"    class="nav-link nav-link-sm<?= navActive('dashboard.php') ?>">Dashboard</a></li>
                <li><a href="<?= APP_URL ?>/admin/config_admin.php" class="nav-link nav-link-sm<?= navActive('config_admin.php') ?>">Config</a></li>
                <li class="nav-divider"></li>
                <?php endif; ?>

                <li>
                    <a href="<?= APP_URL ?>/pages/profile.php" class="nav-avatar-wrap">
                        <span class="nav-avatar-circle"><?= strtoupper(substr($_SESSION['FIRST_NAME'] ?? '?', 0, 1)) ?></span>
                        <span class="nav-avatar-name"><?= h($_SESSION['FIRST_NAME'] ?? '') ?></span>
                    </a>
                </li>
                <li><a href="<?= APP_URL ?>/logout.php" class="nav-link nav-link-sm">Logout</a></li>
            </ul>
        </div>
    </nav>
</header>

<main class="container">
