<?php
// ============================================================
// header.php
// Shared HTML header and navigation bar.
// Include at the top of every page AFTER requireLogin().
// ============================================================

require_once __DIR__ . '/helpers.php';

$xmasYear     = getConfig('XMAS_YEAR', date('Y'));
$matchesDone  = matchesGenerated();
$currentUser  = currentUserId();
$userMatch    = $matchesDone ? getMatchForUser($currentUser) : null;
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
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <span class="santa-emoji">🎅🏾</span> <?= h(APP_NAME) ?> <?= h($xmasYear) ?>
    </div>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>

    <ul class="nav-links" id="navLinks">
        <li><a href="<?= APP_URL ?>/pages/home.php">Home</a></li>
        <li><a href="<?= APP_URL ?>/pages/gift_list.php">My Wish List</a></li>

        <?php if ($matchesDone && $userMatch): ?>
        <li>
            <a href="<?= APP_URL ?>/pages/giftee_list.php">
                <?= h($userMatch['FIRST_NAME']) ?>'s Gift List
            </a>
        </li>
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

<main class="container">