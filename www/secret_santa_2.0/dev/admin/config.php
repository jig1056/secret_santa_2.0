<?php
// ============================================================
// admin/config.php
// View and edit SS_CONFIG key/value pairs. Admin only.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo      = getDB();
$msg      = '';
$msgType  = '';
$editing  = null;
$addMode  = isset($_GET['add']); // explicit add mode flag

// ------------------------------------------------------------
// Handle POST actions: update, add, delete
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- UPDATE existing key --
    if ($action === 'update') {
        $configId = (int)($_POST['config_id'] ?? 0);
        $value    = trim($_POST['config_value']       ?? '');
        $desc     = trim($_POST['config_description'] ?? '');
        $stmt     = $pdo->prepare("UPDATE SS_CONFIG SET CONFIG_VALUE = ?, CONFIG_DESCRIPTION = ?, UPDATED_AT = NOW() WHERE CONFIG_ID = ?");
        $stmt->execute([$value, $desc ?: null, $configId]);
        $msg     = 'Configuration updated successfully.';
        $msgType = 'success';
        // form closes on success ($editing stays null)

    // -- ADD new key --
    } elseif ($action === 'add') {
        $key   = strtoupper(trim($_POST['config_key']         ?? ''));
        $value = trim($_POST['config_value']                  ?? '');
        $desc  = trim($_POST['config_description']            ?? '');

        if (!$key || $value === '') {
            $msg     = 'Both key and value are required.';
            $msgType = 'error';
            $addMode = true;
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM SS_CONFIG WHERE CONFIG_KEY = ?");
            $chk->execute([$key]);
            if ($chk->fetchColumn() > 0) {
                $msg     = "Key \"{$key}\" already exists. Click it in the table to edit it.";
                $msgType = 'error';
                $addMode = true;
            } else {
                $pdo->prepare("INSERT INTO SS_CONFIG (CONFIG_KEY, CONFIG_VALUE, CONFIG_DESCRIPTION) VALUES (?, ?, ?)")
                    ->execute([$key, $value, $desc ?: null]);
                $msg     = "Config key \"{$key}\" added.";
                $msgType = 'success';
                $addMode = false; // close the form on success
            }
        }

    // -- DELETE key --
    } elseif ($action === 'delete') {
        $configId = (int)($_POST['config_id'] ?? 0);
        $row = $pdo->prepare("SELECT CONFIG_KEY FROM SS_CONFIG WHERE CONFIG_ID = ?");
        $row->execute([$configId]);
        $keyName = $row->fetchColumn();
        $pdo->prepare("DELETE FROM SS_CONFIG WHERE CONFIG_ID = ?")->execute([$configId]);
        $msg     = "Config key \"{$keyName}\" deleted.";
        $msgType = 'success';

    // -- INITIALIZE new season --
    } elseif ($action === 'initialize') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if (strtolower($confirm) !== 'yes') {
            $msg     = 'Initialization cancelled — you must type YES to confirm.';
            $msgType = 'error';
        } else {
            $newYear = (int)date('Y');
            $pdo->prepare("UPDATE SS_CONFIG SET CONFIG_VALUE = ?, UPDATED_AT = NOW() WHERE CONFIG_KEY = 'XMAS_YEAR'")
                ->execute([$newYear]);
            $pdo->prepare("DELETE FROM SS_MATCHES WHERE YEAR = ?")->execute([$newYear]);
            $pdo->exec("DELETE FROM SS_GIFTS");
            $msg     = "✅ Season initialized! XMAS_YEAR set to {$newYear}, all gifts cleared, and {$newYear} matches removed.";
            $msgType = 'success';
        }
    }
}

// Load edit target from GET — but not after a successful POST
if (!$editing && isset($_GET['edit']) && $msgType !== 'success') {
    $stmt = $pdo->prepare("SELECT * FROM SS_CONFIG WHERE CONFIG_ID = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Fetch all config rows
$configs = $pdo->query("SELECT * FROM SS_CONFIG ORDER BY CONFIG_KEY ASC")->fetchAll();

// formOpen: true if editing a record OR explicitly in add mode with an error
$formOpen     = $editing ? 'true' : 'false';
$formOpenAdd  = ($addMode) ? 'true' : 'false';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🔧 Configuration</h1>
    <a href="?add=1" class="btn btn-primary" id="addBtn">➕ Add New Key</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- Edit Form (shown when editing a key) -->
<?php if ($editing): ?>
<div class="card" id="configForm">
    <div class="card-title">✏️ Edit Config: <code class="key-code"><?= h($editing['CONFIG_KEY']) ?></code></div>
    <form method="POST" action="">
        <input type="hidden" name="action"    value="update">
        <input type="hidden" name="config_id" value="<?= $editing['CONFIG_ID'] ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Key</label>
                <input type="text" value="<?= h($editing['CONFIG_KEY']) ?>" disabled class="input-disabled">
            </div>
            <div class="form-group">
                <label for="config_value">Value <span class="required">*</span></label>
                <input type="text" id="config_value" name="config_value" required maxlength="500"
                       value="<?= h($editing['CONFIG_VALUE']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="config_description">Description <span class="optional">(optional)</span></label>
            <input type="text" id="config_description" name="config_description" maxlength="500"
                   placeholder="What does this config key do?"
                   value="<?= h($editing['CONFIG_DESCRIPTION'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/config.php" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Delete config key &quot;<?= h($editing['CONFIG_KEY']) ?>&quot;? This cannot be undone.')) document.getElementById('delCfg<?= $editing['CONFIG_ID'] ?>').submit()">
                Delete
            </button>
        </div>
    </form>
    <form id="delCfg<?= $editing['CONFIG_ID'] ?>" method="POST" action="" style="display:none;">
        <input type="hidden" name="action"    value="delete">
        <input type="hidden" name="config_id" value="<?= $editing['CONFIG_ID'] ?>">
    </form>
</div>
<?php endif; ?>

<!-- Add Form (shown when ?add=1 or add error) -->
<?php if ($addMode && !$editing): ?>
<div class="card" id="configForm">
    <div class="card-title">➕ Add New Config Key</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">

        <div class="form-row">
            <div class="form-group">
                <label for="config_key">Key <span class="required">*</span></label>
                <input type="text" id="config_key" name="config_key" required maxlength="100"
                       placeholder="e.g. GIFT_BUDGET"
                       value="<?= h($_POST['config_key'] ?? '') ?>">
                <div class="field-hint">Uppercase letters and underscores only.</div>
            </div>
            <div class="form-group">
                <label for="config_value">Value <span class="required">*</span></label>
                <input type="text" id="config_value" name="config_value" required maxlength="500"
                       value="<?= h($_POST['config_value'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="config_description">Description <span class="optional">(optional)</span></label>
            <input type="text" id="config_description" name="config_description" maxlength="500"
                   placeholder="What does this config key do?"
                   value="<?= h($_POST['config_description'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Key</button>
            <a href="<?= APP_URL ?>/admin/config.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Initialize Season Card -->
<div class="card init-card">
    <div class="card-title">🎄 Initialize New Season</div>
    <p class="init-desc">
        This will set <code class="key-code">XMAS_YEAR</code> to the current calendar year
        (<strong><?= date('Y') ?></strong>), clear all gift lists, and remove any existing
        matches for that year. <strong>This cannot be undone.</strong>
    </p>

    <div id="initToggleArea">
        <button type="button" class="btn btn-danger" onclick="showInitForm()">
            ⚠️ Initialize <?= date('Y') ?> Season
        </button>
    </div>

    <div id="initForm" style="display:none;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="initialize">
            <div class="init-confirm-row">
                <label for="confirm_text" class="init-label">
                    Type <strong>YES</strong> to confirm you want to wipe all gifts and reset the season:
                </label>
                <input type="text" id="confirm_text" name="confirm_text"
                       placeholder="Type YES here"
                       autocomplete="off"
                       oninput="checkConfirm(this)">
                <button type="submit" id="initSubmitBtn" class="btn btn-danger" disabled>
                    Initialize Season
                </button>
                <button type="button" class="btn btn-secondary" onclick="hideInitForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Config table -->
<div class="card">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">⚙️ All Config Keys (<?= count($configs) ?>)</div>
        <input type="text" id="configSearch" placeholder="🔍 Search keys..." oninput="filterConfig()" class="dash-search">
    </div>
    <div class="table-wrap" style="margin-top:1rem;">
        <table>
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Description</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configs as $cfg): ?>
                <tr class="config-row <?= $editing && $editing['CONFIG_ID'] == $cfg['CONFIG_ID'] ? 'row-active' : '' ?>"
                    data-search="<?= strtolower(h($cfg['CONFIG_KEY'] . ' ' . $cfg['CONFIG_VALUE'] . ' ' . ($cfg['CONFIG_DESCRIPTION'] ?? ''))) ?>">
                    <td>
                        <a href="?edit=<?= $cfg['CONFIG_ID'] ?>" class="key-link">
                            <code class="key-code"><?= h($cfg['CONFIG_KEY']) ?></code>
                        </a>
                    </td>
                    <td><strong><?= h($cfg['CONFIG_VALUE']) ?></strong></td>
                    <td class="desc-col"><?= $cfg['CONFIG_DESCRIPTION'] ? h($cfg['CONFIG_DESCRIPTION']) : '<span class="muted">—</span>' ?></td>
                    <td class="nowrap date-col"><?= date('M j, Y g:ia', strtotime($cfg['UPDATED_AT'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.page-header  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-header .page-title { margin-bottom: 0; }

.form-row     { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.required { color: #c0392b; }
.optional { color: #999; font-weight: 400; font-size: 0.85rem; }
.form-actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; align-items: center; }

.input-disabled { background: #f4f6f8; color: #888; cursor: not-allowed; width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
.field-hint     { font-size: 0.8rem; color: #999; margin-top: 0.3rem; }

.key-code   { background: #f4f6f8; border: 1px solid #ddd; border-radius: 4px; padding: 0.15rem 0.45rem; font-size: 0.88rem; font-family: monospace; color: #c0392b; white-space: nowrap; }
.key-link   { text-decoration: none; }
.key-link:hover .key-code { background: #fde8e8; border-color: #c0392b; }

.desc-col   { font-size: 0.85rem; color: #666; max-width: 280px; }
.date-col   { font-size: 0.82rem; color: #999; }
.muted      { color: #aaa; }
.nowrap     { white-space: nowrap; }

.row-active td { background: #fff8f0; }

.btn-danger { background: #c0392b; color: #fff; }
.btn-danger:hover { opacity: 0.85; }
.card-header-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; }
.dash-search { padding: 0.4rem 0.75rem; border: 1px solid #ccc; border-radius: 8px; font-size: 0.9rem; min-width: 200px; }

/* Initialize card */
.init-card      { border-left: 4px solid #c0392b; }
.init-desc      { font-size: 0.95rem; color: #555; margin-bottom: 1rem; line-height: 1.6; }
.init-confirm-row { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
.init-label     { font-size: 0.9rem; color: #555; width: 100%; margin-bottom: 0.25rem; }
.init-confirm-row input { padding: 0.5rem 0.75rem; border: 2px solid #c0392b; border-radius: 8px; font-size: 1rem; width: 180px; }
.init-confirm-row input:focus { outline: none; box-shadow: 0 0 0 3px rgba(192,57,43,0.2); }
</style>

<script>
function showInitForm() {
    document.getElementById('initToggleArea').style.display = 'none';
    document.getElementById('initForm').style.display = 'block';
    document.getElementById('confirm_text').focus();
}

function hideInitForm() {
    document.getElementById('initToggleArea').style.display = 'block';
    document.getElementById('initForm').style.display = 'none';
    document.getElementById('confirm_text').value = '';
    document.getElementById('initSubmitBtn').disabled = true;
}

function checkConfirm(input) {
    const btn = document.getElementById('initSubmitBtn');
    btn.disabled = input.value.trim().toLowerCase() !== 'yes';
}
</script>

<script>
function filterConfig() {
    const q    = document.getElementById('configSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.config-row');
    rows.forEach(row => {
        row.style.display = !q || row.dataset.search.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>