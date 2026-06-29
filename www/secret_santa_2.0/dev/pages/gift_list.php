<?php
// ============================================================
// gift_list.php
// Add, edit, and delete gifts from the logged-in user's list.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$pdo      = getDB();
$userId   = currentUserId();
$xmasYear = getConfig('XMAS_YEAR', date('Y'));
$msg      = '';
$msgType  = '';
$editing  = null;
$addMode  = isset($_GET['add']);

// Normalize a URL: prepend https:// if no protocol given, then validate.
function normalizeUrl(string $url): string|false {
    if ($url === '') return '';
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- DELETE --
    if ($action === 'delete') {
        $giftId = (int)($_POST['gift_id'] ?? 0);
        $pdo->prepare("DELETE FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?")->execute([$giftId, $userId, $xmasYear]);
        $msg = 'Gift removed from your list.';
        $msgType = 'success';

    // -- ADD --
    } elseif ($action === 'add') {
        $name    = trim($_POST['name']        ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $rawUrl  = trim($_POST['url']         ?? '');
        $url     = normalizeUrl($rawUrl);

        if ($name === '') {
            $msg = 'Gift name is required.';
            $msgType = 'error';
            $addMode = true;
        } elseif ($url === false) {
            $msg = 'The URL doesn\'t look valid. Try something like nike.com or https://nike.com.';
            $msgType = 'error';
            $addMode = true;
        } else {
            $pdo->prepare("INSERT INTO SS_GIFTS (USER_ID, YEAR, NAME, DESCRIPTION, URL) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $xmasYear, $name, $desc ?: null, $url ?: null]);
            $msg = 'Gift added to your list!';
            $msgType = 'success';
            $addMode = false;
        }

    // -- UPDATE --
    } elseif ($action === 'update') {
        $giftId  = (int)($_POST['gift_id'] ?? 0);
        $name    = trim($_POST['name']        ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $rawUrl  = trim($_POST['url']         ?? '');
        $url     = normalizeUrl($rawUrl);

        if ($name === '') {
            $msg = 'Gift name is required.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
            $stmt->execute([$giftId, $userId, $xmasYear]);
            $editing = $stmt->fetch() ?: null;
        } elseif ($url === false) {
            $msg = 'The URL doesn\'t look valid. Try something like nike.com or https://nike.com.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
            $stmt->execute([$giftId, $userId, $xmasYear]);
            $editing = $stmt->fetch() ?: null;
        } else {
            $pdo->prepare("UPDATE SS_GIFTS SET NAME = ?, DESCRIPTION = ?, URL = ?, UPDATED_AT = NOW() WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?")
                ->execute([$name, $desc ?: null, $url ?: null, $giftId, $userId, $xmasYear]);
            $msg = 'Gift updated!';
            $msgType = 'success';
        }
    }
}

// Load edit target from GET
if (!$editing && isset($_GET['edit']) && $msgType !== 'success') {
    $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
    $stmt->execute([(int)$_GET['edit'], $userId, $xmasYear]);
    $editing = $stmt->fetch() ?: null;
}

// Fetch all gifts
$stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE USER_ID = ? AND YEAR = ? ORDER BY CREATED_AT ASC");
$stmt->execute([$userId, $xmasYear]);
$gifts = $stmt->fetchAll();

$viewPref = getUserPref($userId, 'wl_view', $xmasYear);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <span class="section-label">✦ Your Wish List</span>
        <h1 class="page-title" style="margin-bottom:0;">🎁 My Wish List</h1>
    </div>
    <?php if (!$editing): ?>
    <button class="btn btn-primary" onclick="toggleAddForm()">+ Add Gift</button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- ADD Form (toggle) -->
<?php if (!$editing): ?>
<div class="card card-accent-red" id="addFormPanel" style="<?= $addMode ? '' : 'display:none;' ?>">
    <div class="card-title"><span style="color:var(--red);">+</span> Add a Gift</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="name">Gift Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required maxlength="200"
                   placeholder="e.g. Philadelphia Eagles Merch"
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
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-ghost-neutral">↩ Return to List</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- EDIT Form -->
<?php if ($editing): ?>
<div class="card card-accent-red">
    <div class="card-title">✏️ Edit Gift</div>
    <form method="POST" action="">
        <input type="hidden" name="action"  value="update">
        <input type="hidden" name="gift_id" value="<?= $editing['GIFT_ID'] ?>">
        <div class="form-group">
            <label for="name">Gift Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required maxlength="200"
                   value="<?= h($editing['NAME']) ?>">
        </div>
        <div class="form-group">
            <label for="description">Description <span class="optional">(optional)</span></label>
            <textarea id="description" name="description" maxlength="1000"><?= h($editing['DESCRIPTION'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="url">Link / URL <span class="optional">(optional)</span></label>
            <input type="text" id="url" name="url" maxlength="500"
                   placeholder="e.g. nike.com or https://www.amazon.com/..."
                   value="<?= h($editing['URL'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Remove this gift from your list?')) document.getElementById('delGift<?= $editing['GIFT_ID'] ?>').submit()">
                Delete
            </button>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-ghost-neutral">↩ Return to List</a>
        </div>
    </form>
    <form id="delGift<?= $editing['GIFT_ID'] ?>" method="POST" action="" style="display:none;">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="gift_id" value="<?= $editing['GIFT_ID'] ?>">
    </form>
</div>
<?php endif; ?>

<?php if (!$editing): ?>
<!-- Gift List card -->
<div class="card card-accent-red" id="giftListCard">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">
            🎄 Your Wish List
            <span style="font-size:0.85rem;font-weight:400;color:var(--muted);font-family:'Lato',sans-serif;margin-left:0.4rem;">
                (<?= count($gifts) ?> gift<?= count($gifts) !== 1 ? 's' : '' ?>)
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
        <p>Your list is empty! Click <strong>+ Add Gift</strong> to get started.</p>
    </div>
    <?php else: ?>

    <!-- TABLE VIEW -->
    <div id="viewList" class="table-wrap" <?= $viewPref !== 'list' ? 'style="display:none;"' : '' ?>>
        <table>
            <thead>
                <tr><th>Gift</th><th>Description</th><th>Link</th></tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                <tr>
                    <td><a href="?edit=<?= $gift['GIFT_ID'] ?>" class="link-edit">🎁 <?= h($gift['NAME']) ?></a></td>
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
                <div class="gift-item-name">
                    <a href="?edit=<?= $gift['GIFT_ID'] ?>" class="link-edit"><?= h($gift['NAME']) ?></a>
                </div>
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

    <?php endif; ?>
</div>
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
    document.getElementById('viewList').style.display = v === 'list' ? '' : 'none';
    document.getElementById('viewGrid').style.display = v === 'grid' ? '' : 'none';
    document.getElementById('btnList').classList.toggle('active', v === 'list');
    document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
    fetch('<?= APP_URL ?>/pages/set_pref.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'pref_key=wl_view&pref_value=' + v + '&xmas_year=<?= h($xmasYear) ?>'
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
