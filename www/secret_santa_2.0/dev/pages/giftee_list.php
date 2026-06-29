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

if (!matchesGenerated()) {
    redirect('/pages/home.php');
}

$match = getMatchForUser($userId);
if (!$match) {
    redirect('/pages/home.php');
}

$xmasYear = getConfig('XMAS_YEAR', date('Y'));
$stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE USER_ID = ? AND YEAR = ? ORDER BY CREATED_AT ASC");
$stmt->execute([$match['USER_ID'], $xmasYear]);
$gifts = $stmt->fetchAll();

$viewPref = getUserPref($userId, 'gl_view', $xmasYear);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <span class="section-label">✦ Your Secret Match</span>
        <h1 class="page-title" style="margin-bottom:0;">🎁 <?= h($match['FIRST_NAME']) ?>'s Wish List</h1>
    </div>
</div>

<?php if (empty($gifts)): ?>
<div class="card card-accent-red">
    <div class="empty-state">
        <div class="empty-icon">🎀</div>
        <p><strong><?= h($match['FIRST_NAME']) ?></strong> hasn't added any gifts to <?= pronoun($match['SEX'] ?? null, 'possessive') ?> list yet.</p>
        <p class="mt-1">Check back later, or surprise <?= pronoun($match['SEX'] ?? null, 'object') ?> with something thoughtful!</p>
    </div>
</div>

<?php else: ?>

<div class="card card-accent-red">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">
            🎄 <?= h($match['FIRST_NAME']) ?>'s Gifts
            <span style="font-size:0.85rem;font-weight:400;color:var(--muted);font-family:'Lato',sans-serif;margin-left:0.4rem;">
                (<?= count($gifts) ?> gift<?= count($gifts) !== 1 ? 's' : '' ?>)
            </span>
        </div>
        <div class="view-toggle">
            <button id="btnList" class="toggle-btn <?= $viewPref === 'list' ? 'active' : '' ?>" onclick="setView('list')">☰ List</button>
            <button id="btnGrid" class="toggle-btn <?= $viewPref !== 'list' ? 'active' : '' ?>"  onclick="setView('grid')">⊞ Grid</button>
        </div>
    </div>

    <!-- TABLE VIEW -->
    <div id="viewList" class="table-wrap" <?= $viewPref !== 'list' ? 'style="display:none;"' : '' ?>>
        <table>
            <thead>
                <tr><th>Gift</th><th>Description</th><th>Link</th></tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                <tr>
                    <td style="font-weight:700;color:var(--text);"><?= h($gift['NAME']) ?></td>
                    <td><?= $gift['DESCRIPTION'] ? h($gift['DESCRIPTION']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <?php if ($gift['URL']): ?>
                        <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="link-online" style="display:inline;">View Online ↗</a>
                        <?php else: ?>
                        <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- GRID VIEW -->
    <div id="viewGrid" class="gift-grid" <?= $viewPref === 'list' ? 'style="display:none;"' : '' ?>>
        <?php foreach ($gifts as $gift): ?>
        <div class="gift-item">
            <span class="gift-item-icon">🎁</span>
            <div class="gift-item-body">
                <div class="gift-item-name"><?= h($gift['NAME']) ?></div>
                <?php if ($gift['DESCRIPTION']): ?>
                <div class="gift-item-desc"><?= h($gift['DESCRIPTION']) ?></div>
                <?php endif; ?>
                <?php if ($gift['URL']): ?>
                <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="link-online">View Online ↗</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

<script>
function setView(v) {
    document.getElementById('viewList').style.display = v === 'list' ? '' : 'none';
    document.getElementById('viewGrid').style.display = v === 'grid' ? '' : 'none';
    document.getElementById('btnList').classList.toggle('active', v === 'list');
    document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
    fetch('<?= APP_URL ?>/pages/set_pref.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'pref_key=gl_view&pref_value=' + v + '&xmas_year=<?= h($xmasYear) ?>'
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
