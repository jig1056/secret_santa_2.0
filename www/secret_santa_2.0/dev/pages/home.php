<?php
// ============================================================
// home.php
// Shows welcome message, year, and Secret Santa match info
// once matches have been generated.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$xmasYear    = getConfig('XMAS_YEAR', date('Y'));
$matchesDone = matchesGenerated();
$match       = $matchesDone ? getMatchForUser(currentUserId()) : null;

// Get current user's gift count
$pdo      = getDB();
$stmt     = $pdo->prepare("SELECT COUNT(*) FROM SS_GIFTS WHERE USER_ID = ?");
$stmt->execute([currentUserId()]);
$giftCount = (int) $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎄 Welcome, <?= h($_SESSION['FIRST_NAME']) ?>!</h1>

<!-- Season banner -->
<div class="card banner-card">
    <div class="banner-inner">
        <div class="banner-icon">🎅🏾</div>
        <div>
            <div class="banner-title">Secret Santa <?= h($xmasYear) ?></div>
            <div class="banner-sub">Spread some holiday cheer — $50 budget</div>
        </div>
    </div>
</div>

<!-- Status cards -->
<div class="home-grid">

    <!-- Gift list status -->
    <div class="card status-card">
        <div class="status-icon <?= $giftCount > 0 ? 'green' : 'red' ?>">
            <?= $giftCount > 0 ? '🎁' : '📋' ?>
        </div>
        <div class="status-body">
            <div class="status-title">Your Gift List</div>
            <?php if ($giftCount > 0): ?>
                <p>You have <strong><?= $giftCount ?></strong> gift<?= $giftCount !== 1 ? 's' : '' ?> on your list.</p>
            <?php else: ?>
                <p>You haven't added any gifts yet. Let your Secret Santa know what you want!</p>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;">
                <?= $giftCount > 0 ? 'Manage My List' : 'Add Gifts' ?>
            </a>
        </div>
    </div>

    <!-- Match status -->
    <div class="card status-card">
        <div class="status-icon <?= $match ? 'green' : 'gold' ?>">
            <?= $match ? '🤫' : '⏳' ?>
        </div>
        <div class="status-body">
            <div class="status-title">Your Secret Santa Match</div>
            <?php if ($match): ?>
                <p>You are gifting <strong><?= h($match['FIRST_NAME']) ?> <?= h($match['LAST_NAME']) ?></strong> this year!</p>
                <a href="<?= APP_URL ?>/pages/giftee_list.php" class="btn btn-success btn-sm" style="margin-top:0.75rem;">
                    View Their Wish List
                </a>
            <?php else: ?>
                <p>Matches haven't been generated yet. Check back soon!</p>
            <?php endif; ?>
        </div>
    </div>

</div>



<style>
.banner-card { background: linear-gradient(135deg, #c0392b, #922b21); color: #fff; }
.banner-inner { display: flex; align-items: center; gap: 1rem; }
.banner-icon  { font-size: 3rem; line-height: 1; }
.banner-title { font-size: 1.4rem; font-weight: 700; }
.banner-sub   { font-size: 0.95rem; opacity: 0.85; margin-top: 0.2rem; }

.home-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
@media (max-width: 600px) { .home-grid { grid-template-columns: 1fr; } }

.status-card  { display: flex; align-items: flex-start; gap: 1rem; }
.status-icon  { font-size: 2rem; width: 2.5rem; text-align: center; flex-shrink: 0; }
.status-icon.green { filter: none; }
.status-icon.gold  { filter: none; }
.status-title { font-size: 1rem; font-weight: 700; color: #922b21; margin-bottom: 0.35rem; }
.status-body p { font-size: 0.95rem; color: #444; }

.admin-links { display: flex; flex-wrap: wrap; gap: 0.65rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>