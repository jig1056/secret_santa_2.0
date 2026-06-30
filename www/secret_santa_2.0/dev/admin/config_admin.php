<?php
// ============================================================
// admin/config_admin.php
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
        $configKey = trim($_POST['config_key'] ?? '');
        $value     = trim($_POST['config_value']       ?? '');
        $desc      = trim($_POST['config_description'] ?? '');
        $stmt      = $pdo->prepare("UPDATE SS_CONFIG SET CONFIG_VALUE = ?, CONFIG_DESCRIPTION = ?, UPDATED_AT = NOW() WHERE CONFIG_KEY = ?");
        $stmt->execute([$value, $desc ?: null, $configKey]);
        $msg     = 'Configuration updated successfully.';
        $msgType = 'success';
        // Reload the record so the form stays open after save
        $stmt    = $pdo->prepare("SELECT * FROM SS_CONFIG WHERE CONFIG_KEY = ?");
        $stmt->execute([$configKey]);
        $editing = $stmt->fetch() ?: null;

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
        $configKey = trim($_POST['config_key'] ?? '');
        $keyName   = $configKey;
        $pdo->prepare("DELETE FROM SS_CONFIG WHERE CONFIG_KEY = ?")->execute([$configKey]);
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
            $pdo->prepare("DELETE FROM SS_GIFTS WHERE YEAR = ?")->execute([$newYear]);
            $msg     = "✅ Season initialized! XMAS_YEAR set to {$newYear}. Any existing {$newYear} gifts and matches were cleared — prior years are untouched.";
            $msgType = 'success';
        }
    }
}

// Load edit target from GET — but not after a successful POST
if (!$editing && isset($_GET['edit']) && $msgType !== 'success') {
    $stmt = $pdo->prepare("SELECT * FROM SS_CONFIG WHERE CONFIG_KEY = ?");
    $stmt->execute([$_GET['edit']]);
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
    <a href="?add=1" class="btn btn-primary" id="addBtn">+ Add New Key</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- Edit Form (shown when editing a key) -->
<?php if ($editing): ?>
<div class="card" id="configForm">
    <div class="card-title">✏️ Edit Config: <code class="key-code"><?= h($editing['CONFIG_KEY']) ?></code></div>
    <form method="POST" action="">
        <input type="hidden" name="action"     value="update">
        <input type="hidden" name="config_key" value="<?= h($editing['CONFIG_KEY']) ?>">

        <div class="form-group">
            <label>Key</label>
            <input type="text" value="<?= h($editing['CONFIG_KEY']) ?>" disabled class="input-disabled">
        </div>

        <div class="form-group">
            <label for="config_value">Value <span class="required">*</span></label>
            <textarea id="config_value" name="config_value" required rows="3"><?= h($editing['CONFIG_VALUE']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="config_description">Description <span class="optional">(optional)</span></label>
            <textarea id="config_description" name="config_description" rows="3"
                      placeholder="What does this config key do?"><?= h($editing['CONFIG_DESCRIPTION'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/config_admin.php" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Delete config key &quot;<?= h($editing['CONFIG_KEY']) ?>&quot;? This cannot be undone.')) document.getElementById('delCfg<?= h($editing['CONFIG_KEY']) ?>').submit()">
                Delete
            </button>
            <a href="<?= APP_URL ?>/admin/config_admin.php" class="btn btn-secondary">↩ Return to List</a>
        </div>
    </form>
    <form id="delCfg<?= h($editing['CONFIG_KEY']) ?>" method="POST" action="" style="display:none;">
        <input type="hidden" name="action"     value="delete">
        <input type="hidden" name="config_key" value="<?= h($editing['CONFIG_KEY']) ?>">
    </form>
</div>
<?php endif; ?>

<!-- Add Form (shown when ?add=1 or add error) -->
<?php if ($addMode && !$editing): ?>
<div class="card" id="configForm">
    <div class="card-title">+ Add New Config Key</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">

        <div class="form-group">
            <label for="config_key">Key <span class="required">*</span></label>
            <input type="text" id="config_key" name="config_key" required maxlength="100"
                   placeholder="e.g. GIFT_BUDGET"
                   value="<?= h($_POST['config_key'] ?? '') ?>">
            <div class="field-hint">Uppercase letters and underscores only.</div>
        </div>

        <div class="form-group">
            <label for="config_value">Value <span class="required">*</span></label>
            <textarea id="config_value" name="config_value" required rows="3"><?= h($_POST['config_value'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="config_description">Description <span class="optional">(optional)</span></label>
            <textarea id="config_description" name="config_description" rows="3"
                      placeholder="What does this config key do?"><?= h($_POST['config_description'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Key</button>
            <a href="<?= APP_URL ?>/admin/config_admin.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!$editing && !$addMode): ?>
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
                    <th class="sortable-th" id="th-key" onclick="sortBy('key')">Key <span class="sort-icon">▲</span></th>
                    <th class="sortable-th" id="th-value" onclick="sortBy('value')">Value <span class="sort-icon"></span></th>
                    <th>Description</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configs as $cfg): ?>
                <tr class="config-row <?= $editing && $editing['CONFIG_KEY'] === $cfg['CONFIG_KEY'] ? 'row-active' : '' ?>"
                    data-search="<?= strtolower(h($cfg['CONFIG_KEY'] . ' ' . $cfg['CONFIG_VALUE'] . ' ' . ($cfg['CONFIG_DESCRIPTION'] ?? ''))) ?>"
                    data-key="<?= strtolower(h($cfg['CONFIG_KEY'])) ?>"
                    data-value="<?= strtolower(h($cfg['CONFIG_VALUE'])) ?>">
                    <td>
                        <a href="?edit=<?= h($cfg['CONFIG_KEY']) ?>" class="key-link">
                            <code class="key-code"><?= h($cfg['CONFIG_KEY']) ?></code>
                        </a>
                    </td>
                    <td><?= h($cfg['CONFIG_VALUE']) ?></td>
                    <td class="desc-col"><?= $cfg['CONFIG_DESCRIPTION'] ? h($cfg['CONFIG_DESCRIPTION']) : '<span class="muted">—</span>' ?></td>
                    <td class="nowrap date-col"><?= date('M j, Y g:ia', strtotime($cfg['UPDATED_AT'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination-row" id="paginationRow" style="display:none;">
        <button class="btn btn-secondary btn-sm" id="prevBtn" onclick="changePage(-1)">← Prev</button>
        <span class="page-info" id="pageInfo"></span>
        <button class="btn btn-secondary btn-sm" id="nextBtn" onclick="changePage(1)">Next →</button>
        <button class="btn btn-secondary btn-sm" id="viewAllBtn" onclick="toggleViewAll()">View All</button>
    </div>
</div>
<!-- Initialize Season Card -->
<div id="initSectionToggle" style="margin-bottom:1.25rem;">
    <button type="button" class="btn btn-secondary" onclick="showInitSection()">
        🎄 Initialize New Season
    </button>
</div>

<div class="card init-card" id="initCard" style="display:none;">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">🎄 Initialize New Season</div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="hideInitSection()">✖ Hide</button>
    </div>
    <p class="init-desc">
        This will set <code class="key-code">XMAS_YEAR</code> to the current calendar year
        (<strong><?= date('Y') ?></strong>) and clear any gifts or matches already entered
        for that year — gifts and matches from prior years are left untouched.
        <strong>This cannot be undone.</strong>
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
<?php endif; // end !$editing && !$addMode ?>

<style>
.page-header  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-header .page-title { margin-bottom: 0; }

.form-row     { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.required { color: #c0392b; }
.optional { color: #999; font-weight: 400; font-size: 0.85rem; }
.form-actions { margin-top: 0.5rem; }
.form-actions .btn { min-width: 140px; text-align: center; }

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

/* Sortable headers */
.sortable-th    { cursor: pointer; user-select: none; white-space: nowrap; }
.sortable-th:hover { color: #c0392b; }
.sort-active    { color: #c0392b; }
.sort-icon      { font-size: 0.75rem; opacity: 0.8; }

/* Pagination */
.pagination-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.75rem 0 0.25rem; flex-wrap: wrap; }
.page-info      { font-size: 0.88rem; color: #666; min-width: 100px; text-align: center; }
.btn-sm         { padding: 0.3rem 0.7rem; font-size: 0.85rem; }

/* Initialize card */
.init-card      { border-left: 4px solid #c0392b; }
.init-desc      { font-size: 0.95rem; color: #555; margin-bottom: 1rem; line-height: 1.6; }
.init-confirm-row { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
.init-label     { font-size: 0.9rem; color: #555; width: 100%; margin-bottom: 0.25rem; }
.init-confirm-row input { padding: 0.5rem 0.75rem; border: 2px solid #c0392b; border-radius: 8px; font-size: 1rem; width: 180px; }
.init-confirm-row input:focus { outline: none; box-shadow: 0 0 0 3px rgba(192,57,43,0.2); }
</style>

<script>
function showInitSection() {
    document.getElementById('initSectionToggle').style.display = 'none';
    document.getElementById('initCard').style.display = 'block';
}

function hideInitSection() {
    document.getElementById('initSectionToggle').style.display = 'block';
    document.getElementById('initCard').style.display = 'none';
    hideInitForm(); // also collapse the confirm form if it was open
}

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
const PAGE_SIZE = 10;
let currentSort = { col: 'key', dir: 'asc' };
let currentPage = 1;
let viewAll = false;

function getAllRows() {
    return Array.from(document.querySelectorAll('.config-row'));
}

function getFilteredRows() {
    const q = document.getElementById('configSearch').value.toLowerCase().trim();
    return getAllRows().filter(row => !q || row.dataset.search.includes(q));
}

function sortRows(rows) {
    return rows.slice().sort((a, b) => {
        const av = a.dataset[currentSort.col] || '';
        const bv = b.dataset[currentSort.col] || '';
        const cmp = av.localeCompare(bv);
        return currentSort.dir === 'asc' ? cmp : -cmp;
    });
}

function renderTable() {
    const filtered  = getFilteredRows();
    const sorted    = sortRows(filtered);
    const total     = sorted.length;
    const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

    // Clamp page
    currentPage = Math.max(1, Math.min(currentPage, totalPages));

    // Hide all rows first
    getAllRows().forEach(r => r.style.display = 'none');

    // Show the right slice
    const showRows = viewAll ? sorted : sorted.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
    showRows.forEach(r => r.style.display = '');

    // Pagination UI
    const paginationRow = document.getElementById('paginationRow');
    const pageInfo      = document.getElementById('pageInfo');
    const prevBtn       = document.getElementById('prevBtn');
    const nextBtn       = document.getElementById('nextBtn');
    const viewAllBtn    = document.getElementById('viewAllBtn');

    if (total > PAGE_SIZE || viewAll) {
        paginationRow.style.display = '';
        if (viewAll) {
            pageInfo.textContent       = 'Showing all ' + total;
            prevBtn.style.display      = 'none';
            nextBtn.style.display      = 'none';
            viewAllBtn.textContent     = '← Paginate';
        } else {
            const start = Math.min((currentPage - 1) * PAGE_SIZE + 1, total);
            const end   = Math.min(currentPage * PAGE_SIZE, total);
            pageInfo.textContent   = total ? start + '–' + end + ' of ' + total : 'No results';
            prevBtn.style.display  = '';
            nextBtn.style.display  = '';
            prevBtn.disabled       = currentPage <= 1;
            nextBtn.disabled       = currentPage >= totalPages;
            viewAllBtn.textContent = 'View All';
        }
    } else {
        paginationRow.style.display = 'none';
    }

    // Sort header indicators
    ['key', 'value'].forEach(col => {
        const th   = document.getElementById('th-' + col);
        const icon = th.querySelector('.sort-icon');
        if (currentSort.col === col) {
            icon.textContent = currentSort.dir === 'asc' ? ' ▲' : ' ▼';
            th.classList.add('sort-active');
        } else {
            icon.textContent = '';
            th.classList.remove('sort-active');
        }
    });
}

function sortBy(col) {
    if (currentSort.col === col) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.col = col;
        currentSort.dir = 'asc';
    }
    currentPage = 1;
    renderTable();
}

function changePage(delta) {
    currentPage += delta;
    renderTable();
}

function toggleViewAll() {
    viewAll = !viewAll;
    currentPage = 1;
    renderTable();
}

function filterConfig() {
    currentPage = 1;
    renderTable();
}

// On load: if an active (editing) row exists, start on its page
(function init() {
    const activeRow = document.querySelector('.config-row.row-active');
    if (activeRow) {
        const sorted = sortRows(getFilteredRows());
        const idx    = sorted.indexOf(activeRow);
        if (idx >= 0) currentPage = Math.floor(idx / PAGE_SIZE) + 1;
    }
    renderTable();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>