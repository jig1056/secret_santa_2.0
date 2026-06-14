<?php
// ============================================================
// gift_list.php
// Add, edit, and delete gifts from the logged-in user's list.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$pdo    = getDB();
$userId = currentUserId();
$msg    = '';
$msgType= '';
$editing = null;

// ------------------------------------------------------------
// Handle POST actions: add, update, delete
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- DELETE --
    if ($action === 'delete') {
        $giftId = (int)($_POST['gift_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ?");
        $stmt->execute([$giftId, $userId]);
        $msg = 'Gift removed from your list.';
        $msgType = 'success';

    // -- ADD --
    } elseif ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $url  = trim($_POST['url'] ?? '');

        if ($name === '') {
            $msg = 'Gift name is required.';
            $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO SS_GIFTS (USER_ID, NAME, DESCRIPTION, URL)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $name, $desc ?: null, $url ?: null]);
            $msg = 'Gift added to your list!';
            $msgType = 'success';
        }

    // -- UPDATE --
    } elseif ($action === 'update') {
        $giftId = (int)($_POST['gift_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $url    = trim($_POST['url'] ?? '');

        if ($name === '') {
            $msg = 'Gift name is required.';
            $msgType = 'error';
            // Re-load the gift for the edit form
            $stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ?");
            $stmt->execute([$giftId, $userId]);
            $editing = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare("
                UPDATE SS_GIFTS SET NAME = ?, DESCRIPTION = ?, URL = ?, UPDATED_AT = NOW()
                WHERE GIFT_ID = ? AND USER_ID = ?
            ");
            $stmt->execute([$name, $desc ?: null, $url ?: null, $giftId, $userId]);
            $msg = 'Gift updated!';
            $msgType = 'success';
        }
    }
}

// -- Load edit target from GET --
if (!$editing && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE GIFT_ID = ? AND USER_ID = ?");
    $stmt->execute([$editId, $userId]);
    $editing = $stmt->fetch() ?: null;
}

// -- Fetch all gifts for this user --
$stmt = $pdo->prepare("SELECT * FROM SS_GIFTS WHERE USER_ID = ? ORDER BY CREATED_AT ASC");
$stmt->execute([$userId]);
$gifts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎁 My Gift List</h1>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- Add / Edit Form -->
<div class="card">
    <div class="card-title"><?= $editing ? '✏️ Edit Gift' : '➕ Add a Gift' ?></div>
    <form method="POST" action="">
        <input type="hidden" name="action"  value="<?= $editing ? 'update' : 'add' ?>">
        <?php if ($editing): ?>
        <input type="hidden" name="gift_id" value="<?= $editing['GIFT_ID'] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="name">Gift Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required maxlength="200"
                   placeholder="e.g. Yeti Tumbler 30oz"
                   value="<?= h($editing['NAME'] ?? $_POST['name'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="description">Description <span class="optional">(optional)</span></label>
            <textarea id="description" name="description" maxlength="1000"
                      placeholder="Size, color, any other details..."><?= h($editing['DESCRIPTION'] ?? $_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="url">Link / URL <span class="optional">(optional)</span></label>
            <input type="url" id="url" name="url" maxlength="500"
                   placeholder="https://www.amazon.com/..."
                   value="<?= h($editing['URL'] ?? $_POST['url'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $editing ? 'Save Changes' : 'Add Gift' ?>
            </button>
            <?php if ($editing): ?>
            <a href="<?= APP_URL ?>/pages/gift_list.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Gift List Table -->
<div class="card">
    <div class="card-title">🎄 Your Wish List (<?= count($gifts) ?> gift<?= count($gifts) !== 1 ? 's' : '' ?>)</div>

    <?php if (empty($gifts)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎁</div>
        <p>Your list is empty! Add some gifts above so your Secret Santa knows what to get you.</p>
    </div>
    <?php else: ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Gift</th>
                    <th>Description</th>
                    <th>Link</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gifts as $gift): ?>
                <tr>
                    <td><strong><?= h($gift['NAME']) ?></strong></td>
                    <td><?= $gift['DESCRIPTION'] ? h($gift['DESCRIPTION']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <?php if ($gift['URL']): ?>
                        <a href="<?= h($gift['URL']) ?>" target="_blank" rel="noopener" class="view-link">View Online ↗</a>
                        <?php else: ?>
                        <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?edit=<?= $gift['GIFT_ID'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                        <form method="POST" action="" style="display:inline;"
                              onsubmit="return confirm('Remove this gift from your list?')">
                            <input type="hidden" name="action"  value="delete">
                            <input type="hidden" name="gift_id" value="<?= $gift['GIFT_ID'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.required { color: #c0392b; }
.optional { color: #999; font-weight: 400; font-size: 0.85rem; }
.muted    { color: #aaa; }

.form-actions { display: flex; gap: 0.75rem; align-items: center; margin-top: 0.5rem; }

.empty-state { text-align: center; padding: 2rem 1rem; color: #777; }
.empty-icon  { font-size: 3rem; margin-bottom: 0.75rem; }

.view-link { color: #1e8449; font-weight: 600; text-decoration: none; }
.view-link:hover { text-decoration: underline; }

.btn-danger { background: #c0392b; color: #fff; }
.btn-danger:hover { opacity: 0.85; }

/* Mobile: stack table rows */
@media (max-width: 600px) {
    table, thead, tbody, th, td, tr { display: block; }
    thead { display: none; }
    tr { border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 0.75rem; padding: 0.75rem; }
    td { border: none; padding: 0.25rem 0; }
    td:first-child { font-size: 1rem; margin-bottom: 0.25rem; }
    td:last-child { margin-top: 0.5rem; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>