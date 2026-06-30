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
$editingGift  = null;

// Fetch all wishlist-only users this gifter can see
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

// Detail view: ?user=USER_ID
$selectedUserId = $_GET['user'] ?? null;
$wishlistUser   = null;
$gifts          = [];

if ($selectedUserId) {
    // Verify access
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ? AND WISHLIST_USER_ID = ?");
    $stmt->execute([$gifterUserId, $selectedUserId]);
    if (!$stmt->fetchColumn()) {
        redirect('/pages/wishlists.php');
    }

    // Load wishlist user and CAN_EDIT flag
    $stmt = $pdo->prepare("SELECT USER_ID, FIRST_NAME, LAST_NAME, SEX, EMAIL FROM SS_USERS WHERE USER_ID = ? AND STATUS = 'ACTIVE'");
    $stmt->execute([$selectedUserId]);
    $wishlistUser = $stmt->fetch();
    if (!$wishlistUser) {
        redirect('/pages/wishlists.php');
    }

    $canEditStmt = $pdo->prepare("SELECT CAN_EDIT FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ? AND WISHLIST_USER_ID = ?");
    $canEditStmt->execute([$gifterUserId, $selectedUserId]);
    $canEditWishlist = ($canEditStmt->fetchColumn() === 'Y');

    function normalizeUrl(string $url): string|false {
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_purchased') {
            $giftId = (int)($_POST['gift_id'] ?? 0);
            $stmt = $pdo->prepare("
                UPDATE SS_GIFTS SET PURCHASED_BY = ?, PURCHASED_AT = NOW()
                WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ? AND PURCHASED_BY IS NULL
            ");
            $stmt->execute([$gifterUserId, $giftId, $selectedUserId, $xmasYear]);
            $msg     = $stmt->rowCount() ? 'Item marked as purchased!' : 'That item has already been claimed.';
            $msgType = $stmt->rowCount() ? 'success' : 'error';

        } elseif ($action === 'unmark_purchased') {
            $giftId = (int)($_POST['gift_id'] ?? 0);
            $where  = isAdmin()
                ? "GIFT_ID = ? AND USER_ID = ? AND YEAR = ?"
                : "GIFT_ID = ? AND USER_ID = ? AND YEAR = ? AND PURCHASED_BY = ?";
            $params = isAdmin()
                ? [$giftId, $selectedUserId, $xmasYear]
                : [$giftId, $selectedUserId, $xmasYear, $gifterUserId];
            $pdo->prepare("UPDATE SS_GIFTS SET PURCHASED_BY = NULL, PURCHASED_AT = NULL WHERE $where")->execute($params);
            $msg     = 'Purchase unmarked.';
            $msgType = 'success';

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

        } elseif ($action === 'update_gift') {
            $giftId = (int)($_POST['gift_id'] ?? 0);
            $name   = trim($_POST['name']        ?? '');
            $desc   = trim($_POST['description'] ?? '');
            $rawUrl = trim($_POST['url']         ?? '');
            $url    = normalizeUrl($rawUrl);

            if ($name === '') {
                $msg     = 'Gift name is required.';
                $msgType = 'error';
                $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
                $stmt->execute([$giftId, $selectedUserId, $xmasYear]);
                $editingGift = $stmt->fetch() ?: null;
            } elseif ($url === false) {
                $msg     = 'The URL doesn\'t look valid. Try something like nike.com or https://nike.com.';
                $msgType = 'error';
                $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
                $stmt->execute([$giftId, $selectedUserId, $xmasYear]);
                $editingGift = $stmt->fetch() ?: null;
            } else {
                $pdo->prepare("UPDATE SS_GIFTS SET NAME = ?, DESCRIPTION = ?, URL = ?, UPDATED_AT = NOW() WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?")
                    ->execute([$name, $desc ?: null, $url ?: null, $giftId, $selectedUserId, $xmasYear]);
                $msg     = 'Gift updated!';
                $msgType = 'success';
                // Keep edit form open after save
                $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
                $stmt->execute([$giftId, $selectedUserId, $xmasYear]);
                $editingGift = $stmt->fetch() ?: null;
            }

        } elseif ($action === 'delete_gift') {
            $giftId = (int)($_POST['gift_id'] ?? 0);
            $pdo->prepare("DELETE FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?")->execute([$giftId, $selectedUserId, $xmasYear]);
            $msg     = 'Gift removed from the list.';
            $msgType = 'success';

        } elseif ($action === 'email_list') {
            require_once __DIR__ . '/../includes/mailer.php';

            $tmplStmt = $pdo->prepare("SELECT MESSAGE_BODY FROM SS_MESSAGES WHERE MESSAGE_NAME = 'Wishlist Email Header' LIMIT 1");
            $tmplStmt->execute();
            $headerText = $tmplStmt->fetchColumn() ?: 'Here is the wish list you requested.';
            $headerText = str_replace(
                ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}'],
                [h($wishlistUser['FIRST_NAME']), h($wishlistUser['LAST_NAME']), h($xmasYear)],
                $headerText
            );

            $emailStmt = $pdo->prepare("
                SELECT g.*, u.FIRST_NAME AS PURCHASED_BY_NAME
                FROM SS_GIFTS g
                LEFT JOIN SS_USERS u ON u.USER_ID = g.PURCHASED_BY
                WHERE g.USER_ID = ? AND g.YEAR = ?
                ORDER BY g.CREATED_AT ASC
            ");
            $emailStmt->execute([$selectedUserId, $xmasYear]);
            $emailGifts = $emailStmt->fetchAll();

            $rows = '';
            foreach ($emailGifts as $g) {
                $purchased = $g['PURCHASED_BY']
                    ? '<span style="color:#1e8449;font-weight:bold;">Yes</span>'
                    : '<span style="color:#999;">No</span>';
                $link = $g['URL']
                    ? '<a href="' . h($g['URL']) . '" style="color:#B5271C;">View</a>'
                    : '';
                $rows .= "
                <tr style=\"background-color:#FDF8F0;border-bottom:1px solid #E8D8C0;\">
                    <td style=\"padding:10px 12px;font-weight:600;text-align:left;\">" . h($g['NAME']) . "</td>
                    <td style=\"padding:10px 12px;color:#5A4030;text-align:left;\">" . ($g['DESCRIPTION'] ? h($g['DESCRIPTION']) : '') . "</td>
                    <td style=\"padding:10px 12px;text-align:left;\">{$link}</td>
                    <td style=\"padding:10px 12px;text-align:center;\">{$purchased}</td>
                </tr>";
            }

            $innerBody =
                '<p style="margin:0 0 20px;color:#5A4030;text-align:center;">' . nl2br(htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8')) . '</p>' .
                '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:0.95rem;">' .
                    '<thead><tr style="background-color:#B5271C;color:#ffffff;">' .
                        '<th style="padding:10px 12px;text-align:left;">Gift</th>' .
                        '<th style="padding:10px 12px;text-align:left;">Details</th>' .
                        '<th style="padding:10px 12px;text-align:left;">Link</th>' .
                        '<th style="padding:10px 12px;text-align:center;">Purchased</th>' .
                    '</tr></thead>' .
                    '<tbody>' . $rows . '</tbody>' .
                '</table>' .
                (empty($emailGifts) ? '<p style="color:#999;text-align:center;padding:20px 0;">No gifts on this list yet.</p>' : '');

            $emailBody = wrapHtmlEmail(
                h($wishlistUser['FIRST_NAME']) . "'s Christmas List",
                h($xmasYear) . ' Christmas List',
                $innerBody, $xmasYear, true,
                $_SESSION['FIRST_NAME'] ?? ''
            );

            $currentUserEmail = $_SESSION['EMAIL']      ?? '';
            $currentUserName  = ($_SESSION['FIRST_NAME'] ?? '') . ' ' . ($_SESSION['LAST_NAME'] ?? '');
            $subject          = h($wishlistUser['FIRST_NAME']) . "'s " . h($xmasYear) . " Christmas List";
            $result = sendMail($currentUserEmail, trim($currentUserName), $subject, $emailBody, true);
            $msg     = $result === true ? 'List emailed to ' . h($currentUserEmail) . '!' : 'Email failed: ' . h($result);
            $msgType = $result === true ? 'success' : 'error';
        }
    }

    // Reload gifts after POST
    $stmt = $pdo->prepare("
        SELECT g.*, u.FIRST_NAME AS PURCHASED_BY_NAME
        FROM SS_GIFTS g
        LEFT JOIN SS_USERS u ON u.USER_ID = g.PURCHASED_BY
        WHERE g.USER_ID = ? AND g.YEAR = ?
        ORDER BY g.CREATED_AT ASC
    ");
    $stmt->execute([$selectedUserId, $xmasYear]);
    $gifts = $stmt->fetchAll();

    // Load edit target from GET
    if (!$editingGift && isset($_GET['edit']) && $msgType !== 'success') {
        $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
        $stmt->execute([(int)$_GET['edit'], $selectedUserId, $xmasYear]);
        $editingGift = $stmt->fetch() ?: null;
    }
}

if (isset($_GET['add'])) {
    $addMode = true;
}

$viewPref = getUserPref($gifterUserId, 'cl_view', $xmasYear);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($wishlistUser): ?>
<!-- ============================================================ -->
<!-- DETAIL VIEW: a specific child's list                         -->
<!-- ============================================================ -->

<div class="page-header">
    <div>
        <a href="<?= APP_URL ?>/pages/wishlists.php" class="back-link">← Kid's Christmas List</a>
        <span class="section-label" style="margin-top:0.25rem;">✦ <?= h($wishlistUser['FIRST_NAME']) ?>'s List</span>
        <h1 class="page-title" style="margin-bottom:0;">🎄 <?= h($wishlistUser['FIRST_NAME']) ?>'s Christmas List</h1>
    </div>
    <div class="page-header-actions">
        <?php if ($canEditWishlist): ?>
        <button class="btn btn-primary" onclick="toggleAddForm()">+ Add Gift</button>
        <?php endif; ?>
        <form id="emailListForm" method="POST" action="?user=<?= h($selectedUserId) ?>" style="display:inline;">
            <input type="hidden" name="action" value="email_list">
            <button type="button" class="btn btn-ghost-neutral" onclick="submitEmailList()">📧 Email This List</button>
        </form>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- Add Gift Form (toggle) -->
<?php if ($canEditWishlist): ?>
<div class="card card-accent-gold" id="addFormPanel" style="<?= $addMode ? '' : 'display:none;' ?>">
    <div class="card-title"><span style="color:var(--gold);">+</span> Add a Gift for <?= h($wishlistUser['FIRST_NAME']) ?></div>
    <form method="POST" action="?user=<?= h($selectedUserId) ?>">
        <input type="hidden" name="action" value="add_gift">
        <div class="form-group">
            <label for="add_name">Gift Name <span class="required">*</span></label>
            <input type="text" id="add_name" name="name" required maxlength="200"
                   placeholder="e.g. LEGO Star Wars Set"
                   value="<?= h($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="add_description">Description <span class="optional">(optional)</span></label>
            <textarea id="add_description" name="description" maxlength="1000"
                      placeholder="Size, color, any other details..."><?= h($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="add_url">Link / URL <span class="optional">(optional)</span></label>
            <input type="text" id="add_url" name="url" maxlength="500"
                   placeholder="e.g. nike.com or https://www.amazon.com/..."
                   value="<?= h($_POST['url'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Gift</button>
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
            <a href="?user=<?= h($selectedUserId) ?>" class="btn btn-ghost-neutral">↩ Return to List</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Edit Gift Form -->
<?php if ($editingGift && $canEditWishlist): ?>
<div class="card card-accent-gold">
    <div class="card-title">✏️ Edit Gift</div>
    <form method="POST" action="?user=<?= h($selectedUserId) ?>">
        <input type="hidden" name="action"  value="update_gift">
        <input type="hidden" name="gift_id" value="<?= $editingGift['GIFT_ID'] ?>">
        <div class="form-group">
            <label for="edit_name">Gift Name <span class="required">*</span></label>
            <input type="text" id="edit_name" name="name" required maxlength="200"
                   value="<?= h($editingGift['NAME']) ?>">
        </div>
        <div class="form-group">
            <label for="edit_description">Description <span class="optional">(optional)</span></label>
            <textarea id="edit_description" name="description" maxlength="1000"><?= h($editingGift['DESCRIPTION'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="edit_url">Link / URL <span class="optional">(optional)</span></label>
            <input type="text" id="edit_url" name="url" maxlength="500"
                   placeholder="e.g. nike.com or https://www.amazon.com/..."
                   value="<?= h($editingGift['URL'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="?user=<?= h($selectedUserId) ?>" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Remove this gift from the list?')) document.getElementById('delGift<?= $editingGift['GIFT_ID'] ?>').submit()">
                Delete
            </button>
        </div>
    </form>
    <form id="delGift<?= $editingGift['GIFT_ID'] ?>" method="POST" action="?user=<?= h($selectedUserId) ?>" style="display:none;">
        <input type="hidden" name="action"  value="delete_gift">
        <input type="hidden" name="gift_id" value="<?= $editingGift['GIFT_ID'] ?>">
    </form>
</div>
<?php endif; ?>

<!-- Gift List card -->
<div class="card card-accent-gold" id="giftListCard" style="position:relative;<?= $editingGift ? 'display:none;' : '' ?>">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">
            🎄 <?= h($wishlistUser['FIRST_NAME']) ?>'s Gifts
            <span style="font-size:0.85rem;font-weight:400;color:var(--muted);font-family:'Lato',sans-serif;margin-left:0.4rem;">
                (<?= count($gifts) ?> item<?= count($gifts) !== 1 ? 's' : '' ?>)
            </span>
        </div>
        <?php if (!empty($gifts)): ?>
        <div class="view-toggle">
            <button id="btnList" class="toggle-btn <?= $viewPref === 'list' ? 'active' : '' ?>" onclick="setView('list')">☰ List</button>
            <button id="btnGrid" class="toggle-btn <?= $viewPref !== 'list' ? 'active' : '' ?>"  onclick="setView('grid')">⊞ Grid</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($gifts)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <p><?= h($wishlistUser['FIRST_NAME']) ?> hasn't added anything yet.</p>
        <p class="mt-1">You can add something for <?= pronoun($wishlistUser['SEX'] ?? null, 'object') ?> using the button above!</p>
    </div>
    <?php else: ?>

    <!-- TABLE VIEW -->
    <div id="viewList" class="table-wrap" <?= $viewPref !== 'list' ? 'style="display:none;"' : '' ?>>
        <table>
            <thead>
                <tr><th>Gift</th><th>Description</th><th>Link</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                <?php $isPurchased = !empty($gift['PURCHASED_BY']); ?>
                <?php $isMine      = $gift['PURCHASED_BY'] === $gifterUserId; ?>
                <tr>
                    <td>🎁 <?php if ($canEditWishlist): ?><a href="?user=<?= h($selectedUserId) ?>&edit=<?= $gift['GIFT_ID'] ?>" class="link-edit"><?= h($gift['NAME']) ?></a><?php else: ?><?= h($gift['NAME']) ?><?php endif; ?></td>
                    <td><?= $gift['DESCRIPTION'] ? h($gift['DESCRIPTION']) : '' ?></td>
                    <td>
                        <?php if ($gift['URL']): ?>
                        <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="link-online" style="display:inline;">View Online ↗</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isPurchased): ?>
                            <span class="purchased-badge">✓ <?= h($gift['PURCHASED_BY_NAME']) ?></span>
                            <?php if ($isMine || isAdmin()): ?>
                            <form method="POST" action="?user=<?= h($selectedUserId) ?>" style="display:inline;margin-left:6px;">
                                <input type="hidden" name="action"  value="unmark_purchased">
                                <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                                <button type="submit" class="btn btn-sm btn-ghost"
                                        onclick="return confirm('Unmark this item as purchased?')">Unmark</button>
                            </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="POST" action="?user=<?= h($selectedUserId) ?>">
                                <input type="hidden" name="action"  value="mark_purchased">
                                <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                                <button type="submit" class="btn btn-sm btn-success"
                                        onclick="return confirm('Mark this item as purchased by you?')">Mark as Purchased</button>
                            </form>
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
        <?php $isPurchased = !empty($gift['PURCHASED_BY']); ?>
        <?php $isMine      = $gift['PURCHASED_BY'] === $gifterUserId; ?>
        <div class="gift-item <?= $isPurchased ? 'purchased' : '' ?>">
            <span class="gift-item-icon">🎁</span>
            <div class="gift-item-body">
                <div class="gift-item-name <?= $isPurchased ? 'name-purchased' : '' ?>">
                    <?php if ($canEditWishlist): ?>
                    <a href="?user=<?= h($selectedUserId) ?>&edit=<?= $gift['GIFT_ID'] ?>"
                       style="color:inherit;text-decoration:none;font-family:inherit;font-weight:inherit;"><?= h($gift['NAME']) ?></a>
                    <?php else: ?>
                    <?= h($gift['NAME']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($gift['DESCRIPTION']): ?>
                <div class="gift-item-desc"><?= h($gift['DESCRIPTION']) ?></div>
                <?php endif; ?>
                <?php if ($gift['URL']): ?>
                <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="link-online">View Online ↗</a>
                <?php endif; ?>
                <div class="gift-item-status">
                    <?php if ($isPurchased): ?>
                        <span class="purchased-badge">✓ Purchased by <strong><?= h($gift['PURCHASED_BY_NAME']) ?></strong></span>
                        <?php if ($isMine || isAdmin()): ?>
                        <form method="POST" action="?user=<?= h($selectedUserId) ?>" style="display:inline;">
                            <input type="hidden" name="action"  value="unmark_purchased">
                            <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-ghost"
                                    onclick="return confirm('Unmark this item as purchased?')">Unmark</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="POST" action="?user=<?= h($selectedUserId) ?>" style="width:100%;">
                            <input type="hidden" name="action"  value="mark_purchased">
                            <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.4rem;"
                                    onclick="return confirm('Mark this item as purchased by you?')">Mark as Purchased</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <!-- Email sending overlay -->
    <div id="emailOverlay" style="display:none;">
        <div class="sending-spinner"></div>
        <div class="sending-title">Sending email…</div>
        <div class="sending-sub">Please wait — do not close or refresh this page.</div>
    </div>
</div>

<?php else: ?>
<!-- ============================================================ -->
<!-- LIST VIEW: all assigned children                             -->
<!-- ============================================================ -->

<div class="page-header" style="margin-bottom:2rem;">
    <div>
        <span class="section-label">✦ Kid's Christmas Lists</span>
        <h1 class="page-title" style="margin-bottom:0;">🎄 Kid's Christmas List</h1>
    </div>
</div>

<?php if (empty($assignedUsers)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <p>No wish lists have been assigned to you yet.</p>
        <p class="mt-1">Check back after the holidays are closer or contact an admin.</p>
    </div>
</div>

<?php else: ?>
<div class="child-card-grid">
    <?php foreach ($assignedUsers as $wu): ?>
    <?php
        $total     = (int)$wu['GIFT_COUNT'];
        $purchased = (int)$wu['PURCHASED_COUNT'];
        $remaining = $total - $purchased;
        $pct       = $total > 0 ? round(($purchased / $total) * 100) : 0;
        $isActive  = $remaining > 0;
    ?>
    <a href="?user=<?= h($wu['USER_ID']) ?>" class="child-card <?= $isActive ? 'active' : '' ?>">
        <span class="child-card-emoji">🎁</span>
        <div class="child-card-body">
            <div class="child-card-name"><?= h($wu['FIRST_NAME']) ?></div>
            <?php if ($total > 0): ?>
            <div class="child-card-meta">
                <?= $total ?> gift<?= $total !== 1 ? 's' : '' ?>
                <?php if ($remaining === 0): ?>
                    &bull; <span class="child-card-done">All purchased ✓</span>
                <?php endif; ?>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="child-card-caption"><?= $purchased ?> of <?= $total ?> purchased</div>
            <?php else: ?>
            <div class="child-card-meta" style="color:var(--muted);">No gifts added yet</div>
            <?php endif; ?>
        </div>
        <span class="child-card-arrow">→</span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
function toggleAddForm() {
    var panel = document.getElementById('addFormPanel');
    var list  = document.getElementById('giftListCard');
    if (!panel) return;
    var opening = panel.style.display === 'none';
    panel.style.display = opening ? '' : 'none';
    if (list) list.style.display = opening ? 'none' : '';
}
function setView(v) {
    var list = document.getElementById('viewList');
    var grid = document.getElementById('viewGrid');
    if (list) list.style.display = v === 'list' ? '' : 'none';
    if (grid) grid.style.display = v === 'grid' ? '' : 'none';
    var btnList = document.getElementById('btnList');
    var btnGrid = document.getElementById('btnGrid');
    if (btnList) btnList.classList.toggle('active', v === 'list');
    if (btnGrid) btnGrid.classList.toggle('active', v === 'grid');
    fetch('<?= APP_URL ?>/pages/set_pref.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'pref_key=cl_view&pref_value=' + v + '&xmas_year=<?= h($xmasYear) ?>'
    });
}
function submitEmailList() {
    var overlay = document.getElementById('emailOverlay');
    if (overlay) overlay.style.display = 'flex';
    document.getElementById('emailListForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
