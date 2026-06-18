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
        $name = trim($_POST['name']        ?? '');
        $desc = trim($_POST['description'] ?? '');
        $url  = trim($_POST['url']         ?? '');

        if ($name === '') {
            $msg = 'Gift name is required.';
            $msgType = 'error';
            $addMode = true;
        } else {
            $pdo->prepare("INSERT INTO SS_GIFTS (USER_ID, YEAR, NAME, DESCRIPTION, URL) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $xmasYear, $name, $desc ?: null, $url ?: null]);
            $msg = 'Gift added to your list!';
            $msgType = 'success';
            $addMode = false; // close form on success
        }

    // -- UPDATE --
    } elseif ($action === 'update') {
        $giftId = (int)($_POST['gift_id'] ?? 0);
        $name   = trim($_POST['name']        ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $url    = trim($_POST['url']         ?? '');

        if ($name === '') {
            $msg = 'Gift name is required.';
            $msgType = 'error';
            // Keep edit form open on error
            $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
            $stmt->execute([$giftId, $userId, $xmasYear]);
            $editing = $stmt->fetch() ?: null;
        } else {
            $pdo->prepare("UPDATE SS_GIFTS SET NAME = ?, DESCRIPTION = ?, URL = ?, UPDATED_AT = NOW() WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?")
                ->execute([$name, $desc ?: null, $url ?: null, $giftId, $userId, $xmasYear]);
            $msg = 'Gift updated!';
            $msgType = 'success';
            // Form closes on success (editing stays null)
        }
    }
}

// Load edit target from GET — but not after a successful POST
if (!$editing && isset($_GET['edit']) && $msgType !== 'success') {
    $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ? AND YEAR = ?");
    $stmt->execute([(int)$_GET['edit'], $userId, $xmasYear]);
    $editing = $stmt->fetch() ?: null;
}

// Fetch all gifts
$stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE USER_ID = ? AND YEAR = ? ORDER BY CREATED_AT ASC");
$stmt->execute([$userId, $xmasYear]);
$gifts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🎁 My Wish List</h1>
    <a href="?add=1" class="btn btn-primary">➕ Add Gift</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- ADD Form -->
<?php if ($addMode && !$editing): ?>
<div class="card">
    <div class="card-title">➕ Add a Gift</div>
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
            <input type="url" id="url" name="url" maxlength="500"
                   placeholder="https://www.amazon.com/..."
                   value="<?= h($_POST['url'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Gift</button>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- EDIT Form -->
<?php if ($editing): ?>
<div class="card">
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
            <input type="url" id="url" name="url" maxlength="500"
                   value="<?= h($editing['URL'] ?? '') ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Remove this gift from your list?')) document.getElementById('delGift<?= $editing['GIFT_ID'] ?>').submit()">
                Delete
            </button>
        </div>
    </form>
    <form id="delGift<?= $editing['GIFT_ID'] ?>" method="POST" action="" style="display:none;">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="gift_id" value="<?= $editing['GIFT_ID'] ?>">
    </form>
</div>
<?php endif; ?>

<!-- Gift List Table -->
<div class="card">
    <div class="card-title">🎄 Your Wish List (<?= count($gifts) ?> gift<?= count($gifts) !== 1 ? 's' : '' ?>)</div>

    <?php if (empty($gifts)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <p>Your list is empty! Click <strong>➕ Add Gift</strong> to get started.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Gift</th>
                    <th>Description</th>
                    <th>Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                <tr class="<?= $editing && $editing['GIFT_ID'] == $gift['GIFT_ID'] ? 'row-active' : '' ?>">
                    <td>
                        <a href="?edit=<?= $gift['GIFT_ID'] ?>" class="gift-link">
                            <?= h($gift['NAME']) ?>
                        </a>
                    </td>
                    <td><?= $gift['DESCRIPTION'] ? h($gift['DESCRIPTION']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <?php if ($gift['URL']): ?>
                        <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="view-link">View Online ↗</a>
                        <?php else: ?>
                        <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.page-header  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-header .page-title { margin-bottom: 0; }

.required { color: #c0392b; }
.optional { color: #999; font-weight: 400; font-size: 0.85rem; }
.muted    { color: #aaa; }

.form-actions { display: flex; gap: 0.75rem; align-items: center; margin-top: 0.5rem; flex-wrap: wrap; }

.empty-state { text-align: center; padding: 2rem 1rem; color: #777; }
.empty-icon  { font-size: 3rem; margin-bottom: 0.75rem; }

.gift-link  { font-weight: 600; color: #c0392b; text-decoration: none; }
.gift-link:hover { text-decoration: underline; }

.view-link  { color: #1e8449; font-weight: 600; text-decoration: none; }
.view-link:hover { text-decoration: underline; }

.row-active td { background: #fff8f0; }

.btn-danger { background: #c0392b; color: #fff; }
.btn-danger:hover { opacity: 0.85; }

@media (max-width: 600px) {
    table, thead, tbody, th, td, tr { display: block; }
    thead { display: none; }
    tr { border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 0.75rem; padding: 0.75rem; }
    td { border: none; padding: 0.25rem 0; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>