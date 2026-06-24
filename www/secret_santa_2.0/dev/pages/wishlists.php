<?php
// ============================================================
// wishlists.php
// Wishlist Gifter view: see, manage, and purchase items from
// assigned Wishlist Only users' lists.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireRole('wishlist_gifter');

$pdo          = getDB();
$gifterUserId = currentUserId();
$xmasYear     = getConfig('XMAS_YEAR', date('Y'));
$msg          = '';
$msgType      = '';
$addMode      = false;

// ------------------------------------------------------------
// Fetch all wishlist-only users this gifter can see
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT u.USER_ID, u.FIRST_NAME, u.LAST_NAME, u.SEX,
           COUNT(g.GIFT_ID)                                      AS GIFT_COUNT,
           SUM(g.PURCHASED_BY IS NOT NULL)                       AS PURCHASED_COUNT
    FROM SS_WISHLIST_ACCESS wa
    JOIN SS_USERS u ON u.USER_ID = wa.WISHLIST_USER_ID
    LEFT JOIN SS_GIFTS g ON g.USER_ID = u.USER_ID AND g.YEAR = ?
    WHERE wa.GIFTER_USER_ID = ? AND u.STATUS = 'ACTIVE'
    GROUP BY u.USER_ID, u.FIRST_NAME, u.LAST_NAME, u.SEX
    ORDER BY u.FIRST_NAME ASC
");
$stmt->execute([$xmasYear, $gifterUserId]);
$assignedUsers = $stmt->fetchAll();

// ------------------------------------------------------------
// Detail view: ?user=USER_ID
// ------------------------------------------------------------
$selectedUserId = $_GET['user'] ?? null;
$wishlistUser   = null;
$gifts          = [];

if ($selectedUserId) {
    // Verify this gifter actually has access to the requested user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM SS_WISHLIST_ACCESS
        WHERE GIFTER_USER_ID = ? AND WISHLIST_USER_ID = ?
    ");
    $stmt->execute([$gifterUserId, $selectedUserId]);
    if (!$stmt->fetchColumn()) {
        redirect('/pages/wishlists.php');
    }

    // Load wishlist user record
    $stmt = $pdo->prepare("SELECT USER_ID, FIRST_NAME, LAST_NAME, SEX, EMAIL FROM SS_USERS WHERE USER_ID = ? AND STATUS = 'ACTIVE'");
    $stmt->execute([$selectedUserId]);
    $wishlistUser = $stmt->fetch();
    if (!$wishlistUser) {
        redirect('/pages/wishlists.php');
    }

    // --------------------------------------------------------
    // Handle POST actions
    // --------------------------------------------------------
    // Normalize a URL: prepend https:// if no protocol, then validate.
    // Returns normalized URL, empty string if blank, or false if invalid.
    function normalizeUrl(string $url): string|false {
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // -- Mark as purchased --
        if ($action === 'mark_purchased') {
            $giftId = (int)($_POST['gift_id'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE SS_GIFTS
                SET PURCHASED_BY = ?, PURCHASED_AT = NOW()
                WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ? AND PURCHASED_BY IS NULL
            ");
            $stmt->execute([$gifterUserId, $giftId, $selectedUserId, $xmasYear]);
            if ($stmt->rowCount()) {
                $msg     = 'Item marked as purchased!';
                $msgType = 'success';
            } else {
                $msg     = 'That item has already been claimed.';
                $msgType = 'error';
            }

        // -- Unmark purchased --
        } elseif ($action === 'unmark_purchased') {
            $giftId = (int)($_POST['gift_id'] ?? 0);
            // Only the person who claimed it (or an admin) can unmark
            $where = isAdmin()
                ? "GIFT_ID = ? AND USER_ID = ? AND YEAR = ?"
                : "GIFT_ID = ? AND USER_ID = ? AND YEAR = ? AND PURCHASED_BY = ?";
            $params = isAdmin()
                ? [$giftId, $selectedUserId, $xmasYear]
                : [$giftId, $selectedUserId, $xmasYear, $gifterUserId];
            $pdo->prepare("UPDATE SS_GIFTS SET PURCHASED_BY = NULL, PURCHASED_AT = NULL WHERE $where")
                ->execute($params);
            $msg     = 'Purchase unmarked.';
            $msgType = 'success';

        // -- Add gift to wishlist user's list --
        } elseif ($action === 'add_gift') {
            $name   = trim($_POST['name']        ?? '');
            $desc   = trim($_POST['description'] ?? '');
            $rawUrl = trim($_POST['url']         ?? '');
            $url    = normalizeUrl($rawUrl);

            if ($name === '') {
                $msg     = 'Gift name is required.';
                $msgType = 'error';
                $addMode = true;
            } elseif ($url === false) {
                $msg     = 'The URL doesn\'t look valid. Try something like nike.com or https://nike.com.';
                $msgType = 'error';
                $addMode = true;
            } else {
                $pdo->prepare("INSERT INTO SS_GIFTS (USER_ID, YEAR, NAME, DESCRIPTION, URL) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$selectedUserId, $xmasYear, $name, $desc ?: null, $url ?: null]);
                $msg     = h($wishlistUser['FIRST_NAME']) . "'s list updated!";
                $msgType = 'success';
            }

        // -- Email the list --
        } elseif ($action === 'email_list') {
            require_once __DIR__ . '/../includes/mailer.php';

            // Fetch the email header text from a message template if one exists
            $tmplStmt = $pdo->prepare("SELECT MESSAGE_BODY FROM SS_MESSAGES WHERE MESSAGE_NAME = 'Wishlist Email Header' LIMIT 1");
            $tmplStmt->execute();
            $headerText = $tmplStmt->fetchColumn() ?: 'Here is the wish list you requested.';

            // Replace basic placeholders
            $headerText = str_replace(
                ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}'],
                [h($wishlistUser['FIRST_NAME']), h($wishlistUser['LAST_NAME']), h($xmasYear)],
                $headerText
            );

            // Fetch gifts fresh for the email (with purchaser names)
            $emailStmt = $pdo->prepare("
                SELECT g.*, u.FIRST_NAME AS PURCHASED_BY_NAME
                FROM SS_GIFTS g
                LEFT JOIN SS_USERS u ON u.USER_ID = g.PURCHASED_BY
                WHERE g.USER_ID = ? AND g.YEAR = ?
                ORDER BY g.CREATED_AT ASC
            ");
            $emailStmt->execute([$selectedUserId, $xmasYear]);
            $emailGifts = $emailStmt->fetchAll();

            // Build HTML email body
            $rows = '';
            foreach ($emailGifts as $i => $g) {
                $purchased = $g['PURCHASED_BY']
                    ? '<span style="color:#1e8449;font-weight:bold;">✓ Purchased by ' . h($g['PURCHASED_BY_NAME']) . '</span>'
                    : '<span style="color:#999;">Available</span>';
                $link = $g['URL']
                    ? '<a href="' . h($g['URL']) . '" style="color:#c0392b;">View Online</a>'
                    : '—';
                $bg   = ($i % 2 === 0) ? '#f9f9f9' : '#ffffff';
                $rows .= "
                <tr style=\"background:{$bg};\">
                    <td style=\"padding:10px 12px;font-weight:600;\">" . h($g['NAME']) . "</td>
                    <td style=\"padding:10px 12px;color:#555;\">" . ($g['DESCRIPTION'] ? h($g['DESCRIPTION']) : '—') . "</td>
                    <td style=\"padding:10px 12px;\">{$link}</td>
                    <td style=\"padding:10px 12px;\">{$purchased}</td>
                </tr>";
            }

            $emailBody = "
            <div style=\"font-family:Arial,sans-serif;max-width:680px;margin:0 auto;\">
                <div style=\"background:#c0392b;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0;\">
                    <h2 style=\"margin:0;\">🎁 " . h($wishlistUser['FIRST_NAME']) . " " . h($wishlistUser['LAST_NAME']) . "'s Wish List</h2>
                    <p style=\"margin:6px 0 0;opacity:0.85;\">" . h($xmasYear) . " Holiday Wish List</p>
                </div>
                <div style=\"padding:20px 24px;background:#fff;\">
                    <p style=\"margin:0 0 20px;color:#444;\">" . nl2br($headerText) . "</p>
                    <table style=\"width:100%;border-collapse:collapse;font-size:0.95rem;\">
                        <thead>
                            <tr style=\"background:#922b21;color:#fff;\">
                                <th style=\"padding:10px 12px;text-align:left;\">Gift</th>
                                <th style=\"padding:10px 12px;text-align:left;\">Details</th>
                                <th style=\"padding:10px 12px;text-align:left;\">Link</th>
                                <th style=\"padding:10px 12px;text-align:left;\">Status</th>
                            </tr>
                        </thead>
                        <tbody>{$rows}</tbody>
                    </table>
                    " . (empty($emailGifts) ? '<p style="color:#999;text-align:center;padding:20px 0;">No gifts on this list yet.</p>' : '') . "
                </div>
                <div style=\"background:#f5f5f5;padding:14px 24px;border-radius:0 0 8px 8px;font-size:0.85rem;color:#888;\">
                    Sent from " . h(APP_NAME) . " &bull; " . h($xmasYear) . "
                </div>
            </div>";

            $currentUserEmail = $_SESSION['EMAIL']      ?? '';
            $currentUserName  = ($_SESSION['FIRST_NAME'] ?? '') . ' ' . ($_SESSION['LAST_NAME'] ?? '');
            $subject          = h($wishlistUser['FIRST_NAME']) . "'s " . h($xmasYear) . " Wish List";

            $result = sendMail($currentUserEmail, trim($currentUserName), $subject, $emailBody, true);
            if ($result === true) {
                $msg     = 'List emailed to ' . h($currentUserEmail) . '!';
                $msgType = 'success';
            } else {
                $msg     = 'Email failed: ' . h($result);
                $msgType = 'error';
            }
        }
    }

    // Load gifts with purchaser name for display
    $stmt = $pdo->prepare("
        SELECT g.*, u.FIRST_NAME AS PURCHASED_BY_NAME
        FROM SS_GIFTS g
        LEFT JOIN SS_USERS u ON u.USER_ID = g.PURCHASED_BY
        WHERE g.USER_ID = ? AND g.YEAR = ?
        ORDER BY g.CREATED_AT ASC
    ");
    $stmt->execute([$selectedUserId, $xmasYear]);
    $gifts = $stmt->fetchAll();
}

if (isset($_GET['add'])) {
    $addMode = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($wishlistUser): ?>
<!-- ============================================================ -->
<!-- DETAIL VIEW: a specific wishlist user's list                 -->
<!-- ============================================================ -->

<div class="page-header">
    <div>
        <a href="<?= APP_URL ?>/pages/wishlists.php" class="back-link">← Kid's Christmas List</a>
        <h1 class="page-title" style="margin-top:0.25rem;">
            🎀 <?= h($wishlistUser['FIRST_NAME']) ?> <?= h($wishlistUser['LAST_NAME']) ?>'s Wish List
        </h1>
    </div>
    <div class="page-header-actions">
        <a href="?user=<?= h($selectedUserId) ?>&add=1" class="btn btn-secondary">➕ Add Gift</a>
        <form method="POST" action="?user=<?= h($selectedUserId) ?>" style="display:inline;">
            <input type="hidden" name="action" value="email_list">
            <button type="submit" class="btn btn-primary">📧 Email This List</button>
        </form>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- Add Gift Form -->
<?php if ($addMode): ?>
<div class="card">
    <div class="card-title">➕ Add a Gift for <?= h($wishlistUser['FIRST_NAME']) ?></div>
    <form method="POST" action="?user=<?= h($selectedUserId) ?>">
        <input type="hidden" name="action" value="add_gift">
        <div class="form-group">
            <label for="name">Gift Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required maxlength="200"
                   placeholder="e.g. LEGO Star Wars Set"
                   value="<?= h($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="description">Description <span class="optional">(optional)</span></label>
            <textarea id="description" name="description" maxlength="1000"
                      placeholder="Size, color, any other details..."><?= h($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="url">Link / URL <span class="optional">(optional)</span></label>
            <input type="text" id="url" name="url" maxlength="500"
                   placeholder="e.g. nike.com or https://www.amazon.com/..."
                   value="<?= h($_POST['url'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Gift</button>
            <a href="?user=<?= h($selectedUserId) ?>" class="btn btn-secondary">Cancel</a>
            <a href="?user=<?= h($selectedUserId) ?>" class="btn btn-secondary">↩ Return to List</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!$addMode): ?>
<!-- Gift List -->
<div class="card">
    <div class="card-title">
        🎄 <?= h($wishlistUser['FIRST_NAME']) ?>'s Gifts
        (<?= count($gifts) ?> item<?= count($gifts) !== 1 ? 's' : '' ?>)
    </div>

    <?php if (empty($gifts)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <p><?= h($wishlistUser['FIRST_NAME']) ?> hasn't added anything yet.</p>
        <p style="margin-top:0.5rem;">You can add something for <?= pronoun($wishlistUser['SEX'] ?? null, 'object') ?> using the button above!</p>
    </div>
    <?php else: ?>
    <div class="gift-grid">
        <?php foreach ($gifts as $gift): ?>
        <?php $isPurchased = !empty($gift['PURCHASED_BY']); ?>
        <?php $isMine      = $gift['PURCHASED_BY'] === $gifterUserId; ?>
        <div class="gift-card <?= $isPurchased ? 'gift-purchased' : '' ?>">
            <div class="gift-top">
                <div class="gift-icon">
                    <img src="<?= APP_URL ?>/assets/images/img_gift01.png" alt="gift" />
                </div>
                <div class="gift-body">
                    <div class="gift-name"><?= h($gift['NAME']) ?></div>
                    <?php if ($gift['DESCRIPTION']): ?>
                    <div class="gift-desc"><?= h($gift['DESCRIPTION']) ?></div>
                    <?php endif; ?>
                    <?php if ($gift['URL']): ?>
                    <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="gift-link">View Online ↗</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gift-footer">
                <?php if ($isPurchased): ?>
                    <div class="purchased-badge">
                        ✓ Purchased by <strong><?= h($gift['PURCHASED_BY_NAME']) ?></strong>
                    </div>
                    <?php if ($isMine || isAdmin()): ?>
                    <form method="POST" action="?user=<?= h($selectedUserId) ?>" style="display:inline;">
                        <input type="hidden" name="action"  value="unmark_purchased">
                        <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                        <button type="submit" class="btn btn-sm btn-ghost"
                                onclick="return confirm('Unmark this item as purchased?')">
                            Unmark
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="available-badge">Available</div>
                    <form method="POST" action="?user=<?= h($selectedUserId) ?>">
                        <input type="hidden" name="action"  value="mark_purchased">
                        <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                        <button type="submit" class="btn btn-sm btn-success"
                                onclick="return confirm('Mark this item as purchased by you?')">
                            Mark as Purchased
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; // end !$addMode ?>

<?php else: ?>
<!-- ============================================================ -->
<!-- LIST VIEW: all assigned wishlist users                        -->
<!-- ============================================================ -->

<h1 class="page-title">🎄 Kid's Christmas List</h1>

<?php if (empty($assignedUsers)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <p>No wish lists have been assigned to you yet.</p>
        <p style="margin-top:0.5rem;">Check back after the holidays are closer or contact an admin.</p>
    </div>
</div>

<?php else: ?>
<div class="wishlist-user-grid">
    <?php foreach ($assignedUsers as $wu): ?>
    <?php
        $total     = (int)$wu['GIFT_COUNT'];
        $purchased = (int)$wu['PURCHASED_COUNT'];
        $remaining = $total - $purchased;
    ?>
    <a href="?user=<?= h($wu['USER_ID']) ?>" class="wishlist-user-card">
        <div class="wuc-avatar">🎁</div>
        <div class="wuc-body">
            <div class="wuc-name"><?= h($wu['FIRST_NAME']) ?> <?= h($wu['LAST_NAME']) ?></div>
            <?php if ($total > 0): ?>
            <div class="wuc-meta">
                <?= $total ?> gift<?= $total !== 1 ? 's' : '' ?>
                &bull;
                <?php if ($remaining > 0): ?>
                    <span class="wuc-remaining"><?= $remaining ?> still needed</span>
                <?php else: ?>
                    <span class="wuc-done">All purchased ✓</span>
                <?php endif; ?>
            </div>
            <div class="wuc-progress-bar">
                <div class="wuc-progress-fill" style="width:<?= $total > 0 ? round(($purchased / $total) * 100) : 0 ?>%"></div>
            </div>
            <?php else: ?>
            <div class="wuc-meta wuc-empty-meta">No gifts added yet</div>
            <?php endif; ?>
        </div>
        <div class="wuc-arrow">→</div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
/* ---- Page header ---- */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.75rem; }
.page-header .page-title { margin-bottom:0; }
.page-header-actions { display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap; }
.back-link { font-size:0.9rem; color:#c0392b; text-decoration:none; font-weight:600; }
.back-link:hover { text-decoration:underline; }

/* ---- Form ---- */
.required { color:#c0392b; }
.optional  { color:#999; font-weight:400; font-size:0.85rem; }
.form-actions { display:flex; gap:0.75rem; align-items:center; margin-top:0.5rem; flex-wrap:wrap; }

/* ---- Gift grid (detail view) ---- */
.gift-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:1rem; margin-bottom:1.25rem; }

.gift-card {
    background:#fff;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
    border-left:4px solid #c0392b;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
.gift-card.gift-purchased { border-left-color:#1e8449; opacity:0.85; }

.gift-top  { display:flex; gap:1rem; align-items:flex-start; padding:1rem 1.1rem; flex:1; }
.gift-icon { flex-shrink:0; }
.gift-icon img { width:44px; height:44px; object-fit:contain; }

.gift-body  { flex:1; }
.gift-name  { font-weight:700; font-size:1rem; color:#212529; margin-bottom:0.3rem; }
.gift-desc  { font-size:0.88rem; color:#555; margin-bottom:0.35rem; line-height:1.5; }
.gift-link  { font-size:0.85rem; color:#1e8449; font-weight:600; text-decoration:none; }
.gift-link:hover { text-decoration:underline; }

.gift-footer {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0.6rem 1.1rem;
    background:#f8f8f8;
    border-top:1px solid #eee;
    flex-wrap:wrap;
    gap:0.5rem;
}

.purchased-badge { color:#1e8449; font-size:0.88rem; }
.available-badge { color:#999; font-size:0.88rem; }

.btn-ghost   { background:transparent; color:#c0392b; border:1px solid #c0392b; padding:0.3rem 0.7rem; border-radius:5px; cursor:pointer; font-size:0.82rem; }
.btn-ghost:hover { background:#fdf0ef; }
.btn-success { background:#1e8449; color:#fff; border:none; padding:0.3rem 0.8rem; border-radius:5px; cursor:pointer; font-size:0.82rem; }
.btn-success:hover { opacity:0.88; }

/* ---- Wishlist user cards (list view) ---- */
.wishlist-user-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:1rem; }

.wishlist-user-card {
    display:flex;
    align-items:center;
    gap:1rem;
    background:#fff;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
    padding:1.1rem 1.25rem;
    text-decoration:none;
    color:inherit;
    border-left:4px solid #c0392b;
    transition:box-shadow 0.15s;
}
.wishlist-user-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.14); }

.wuc-avatar { font-size:2rem; flex-shrink:0; }
.wuc-body   { flex:1; }
.wuc-name   { font-weight:700; font-size:1rem; color:#212529; margin-bottom:0.25rem; }
.wuc-meta   { font-size:0.85rem; color:#666; margin-bottom:0.5rem; }
.wuc-empty-meta { color:#bbb; }
.wuc-remaining { color:#c0392b; font-weight:600; }
.wuc-done      { color:#1e8449; font-weight:600; }
.wuc-arrow  { color:#ccc; font-size:1.2rem; flex-shrink:0; }

.wuc-progress-bar  { height:5px; background:#eee; border-radius:3px; overflow:hidden; }
.wuc-progress-fill { height:100%; background:#1e8449; border-radius:3px; transition:width 0.3s; }

/* ---- Empty state ---- */
.empty-state { text-align:center; padding:2rem 1rem; color:#777; }
.empty-icon  { font-size:3rem; margin-bottom:0.75rem; }

@media (max-width:600px) {
    .gift-grid, .wishlist-user-grid { grid-template-columns:1fr; }
    .page-header { flex-direction:column; }
    .page-header-actions { width:100%; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
