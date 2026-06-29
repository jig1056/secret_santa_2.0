<?php
// ============================================================
// home.php
// Role-aware dashboard.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$xmasYear = getConfig('XMAS_YEAR', date('Y'));
$pdo      = getDB();

// Banner message per role
if (hasRole('admin') || hasRole('secret_santa')) {
    $bannerMsg = getConfig('HOME_MSG_SECRET_SANTA', 'Spread some holiday cheer!');
} elseif (hasRole('wishlist_only')) {
    $bannerMsg = getConfig('HOME_MSG_WISHLIST_ONLY', 'Add your wish list items so your family knows what to get you!');
} else {
    $bannerMsg = getConfig('HOME_MSG_WISHLIST_GIFTER', 'View and manage the wish lists of your loved ones!');
}

// Gift count (users with their own wish list)
$giftCount = 0;
if (hasRole('admin') || hasRole('secret_santa') || hasRole('wishlist_only')) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM SS_GIFTS WHERE USER_ID = ? AND YEAR = ?");
    $stmt->execute([currentUserId(), $xmasYear]);
    $giftCount = (int) $stmt->fetchColumn();
}

// SS match
$match = null;
if (hasRole('admin') || hasRole('secret_santa')) {
    $match = matchesGenerated() ? getMatchForUser(currentUserId()) : null;
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome heading -->
<div class="home-welcome">
    <span class="section-label">✦ Welcome Back ✦</span>
    <h1 class="home-h1">Welcome, <em><?= h($_SESSION['FIRST_NAME']) ?></em>!</h1>
    <p class="home-subtitle">The season of giving is here — let's make it magical.</p>
</div>

<!-- Hero banner -->
<div class="hero-card">
    <span class="hero-santa">🎅🏾</span>
    <div class="hero-body">
        <div class="hero-label">✦ SECRET SANTA <?= h($xmasYear) ?> ✦</div>
        <div class="hero-title"><?= h($bannerMsg) ?></div>
    </div>
</div>

<!-- Dashboard card grid -->
<div class="home-grid">

    <?php if (hasRole('admin') || hasRole('secret_santa') || hasRole('wishlist_only')): ?>
    <!-- Wish List card -->
    <div class="dash-card">
        <div class="dash-card-label">✦ Your Wish List</div>
        <div class="dash-card-title">🎁 Your Wish List</div>
        <p class="dash-card-desc">
            <?php if ($giftCount > 0): ?>
                You have <strong><?= $giftCount ?></strong> gift<?= $giftCount !== 1 ? 's' : '' ?> on your list.
            <?php else: ?>
                You haven't added any gifts yet. Let <?= hasRole('wishlist_only') ? 'your family' : 'your Secret Santa' ?> know what you want!
            <?php endif; ?>
        </p>
        <div style="margin-top:auto;padding-top:1rem;">
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-primary">
                <?= $giftCount > 0 ? 'Manage My Wish List' : 'Add Gifts' ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasRole('admin') || hasRole('secret_santa')): ?>
    <!-- Secret Santa match card -->
    <div class="dash-card">
        <div class="dash-card-label">✦ Your Secret Match</div>
        <div class="dash-card-title">🤫 Your Secret Santa Match</div>
        <p class="dash-card-desc">
            <?php if ($match): ?>
                You are gifting <strong><?= h($match['FIRST_NAME']) ?></strong> this year!
            <?php else: ?>
                Matches haven't been generated yet. Check back soon!
            <?php endif; ?>
        </p>
        <?php if ($match): ?>
        <div style="margin-top:auto;padding-top:1rem;">
            <a href="<?= APP_URL ?>/pages/giftee_list.php" class="btn btn-primary">
                View <?= ucfirst(pronoun($match['SEX'] ?? null, 'possessive')) ?> Wish List
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php if (hasRole('wishlist_gifter')): ?>
<!-- Kids Christmas Lists — dash-card with gold accent -->
<div class="dash-card dash-card-gold" style="margin-top:1rem;">
    <div class="dash-card-label">✦ Kid's Christmas Lists</div>
    <div class="dash-card-title">🦌 Kids' Christmas Lists</div>
    <p class="dash-card-desc">View and manage the Christmas lists of your loved ones — mark items as you purchase them.</p>
    <div style="margin-top:auto;padding-top:1rem;">
        <a href="<?= APP_URL ?>/pages/wishlists.php" class="btn btn-primary">View Kids' Lists →</a>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
