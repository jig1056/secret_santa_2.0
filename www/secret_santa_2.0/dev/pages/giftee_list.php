<?php
// ============================================================
// giftee_list.php
// Shows the wish list of the logged-in user's Secret Santa
// match (their giftee). Only visible after matches are generated.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$pdo    = getDB();
$userId = currentUserId();

// Redirect home if matches haven't been generated yet
if (!matchesGenerated()) {
    redirect('/pages/home.php');
}

// Get the match for this user
$match = getMatchForUser($userId);
if (!$match) {
    redirect('/pages/home.php');
}

// Fetch the giftee's wish list
$stmt = $pdo->prepare("
    SELECT * FROM SS_GIFTS
    WHERE USER_ID = ?
    ORDER BY CREATED_AT ASC
");
$stmt->execute([$match['USER_ID']]);
$gifts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎁 <?= h($match['FIRST_NAME']) ?>'s Wish List</h1>

<div class="card match-banner">
    <div class="match-inner">
        <div class="match-icon">🤫</div>
        <div>
            <div class="match-title">You are <strong><?= h($match['FIRST_NAME']) ?>'s</strong> Secret Santa this year!</div>
            <div class="match-sub">Remember — $50 budget. Keep it a secret! 🎄</div>
        </div>
    </div>
</div>

<?php if (empty($gifts)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🎀</div>
        <p><strong><?= h($match['FIRST_NAME']) ?></strong> hasn't added any gifts to their list yet.</p>
        <p style="margin-top:0.5rem;">Check back later, or surprise them with something thoughtful!</p>
    </div>
</div>

<?php else: ?>

<!-- Gift count summary -->
<div class="gift-summary">
    <?= h($match['FIRST_NAME']) ?> has <strong><?= count($gifts) ?></strong> gift<?= count($gifts) !== 1 ? 's' : '' ?> on their list.
</div>

<!-- Gift cards -->
<div class="gift-grid">
    <?php foreach ($gifts as $gift): ?>
    <div class="gift-card">
        <div class="gift-icon">
            <img src="<?= APP_URL ?>/assets/images/img_gift01.png" alt="gift" />
        </div>
        <div class="gift-body">
            <div class="gift-name"><?= h($gift['NAME']) ?></div>
            <?php if ($gift['DESCRIPTION']): ?>
            <div class="gift-desc"><?= h($gift['DESCRIPTION']) ?></div>
            <?php endif; ?>
            <?php if ($gift['URL']): ?>
            <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="gift-link">
                View Online ↗
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<div style="margin-top:1rem;">
    <a href="<?= APP_URL ?>/pages/home.php" class="btn btn-secondary">← Back to Home</a>
</div>

<style>
.match-banner { background: linear-gradient(135deg, #1e8449, #145a32); color: #fff; margin-bottom: 1.25rem; }
.match-inner  { display: flex; align-items: center; gap: 1rem; }
.match-icon   { font-size: 2.5rem; flex-shrink: 0; }
.match-title  { font-size: 1.05rem; margin-bottom: 0.2rem; }
.match-sub    { font-size: 0.9rem; opacity: 0.85; }

.gift-summary { color: #555; margin-bottom: 1rem; font-size: 0.95rem; }

.gift-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }

.gift-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 1.1rem 1.25rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    border-left: 4px solid #c0392b;
}

.gift-icon { flex-shrink: 0; }
.gift-icon img { width: 48px; height: 48px; object-fit: contain; }

.gift-body { flex: 1; }
.gift-name { font-weight: 700; font-size: 1rem; color: #212529; margin-bottom: 0.3rem; }
.gift-desc { font-size: 0.9rem; color: #555; margin-bottom: 0.4rem; line-height: 1.5; }
.gift-link { font-size: 0.88rem; color: #1e8449; font-weight: 600; text-decoration: none; }
.gift-link:hover { text-decoration: underline; }

.empty-state { text-align: center; padding: 2rem 1rem; color: #777; }
.empty-icon  { font-size: 3rem; margin-bottom: 0.75rem; }

@media (max-width: 480px) {
    .gift-grid { grid-template-columns: 1fr; }
    .match-inner { flex-direction: column; text-align: center; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?> 