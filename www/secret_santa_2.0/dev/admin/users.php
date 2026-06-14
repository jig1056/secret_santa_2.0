<?php
// ============================================================
// admin/users.php
// Add, edit, and manage users. Admin only.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo     = getDB();
$msg     = '';
$msgType = '';
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $userType  = $_POST['user_type'] === 'ADMIN' ? 'ADMIN' : 'STANDARD';
        $password  = trim($_POST['password']   ?? '');

        if (!$firstName || !$lastName || !$email || !$password) {
            $msg = 'First name, last name, email and password are required.';
            $msgType = 'error';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM SS_USERS WHERE EMAIL = ?");
            $chk->execute([$email]);
            if ($chk->fetchColumn() > 0) {
                $msg = 'A user with that email already exists.';
                $msgType = 'error';
            } else {
                $userId = generateUserId($firstName, $lastName, $pdo);
                $hash   = password_hash($password, PASSWORD_BCRYPT);
                $stmt   = $pdo->prepare("
                    INSERT INTO SS_USERS (USER_ID, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD_HASH, PHONE, USER_TYPE, STATUS)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')
                ");
                $stmt->execute([$userId, $firstName, $lastName, $email, $hash, $phone ?: null, $userType]);
                $msg = "User {$firstName} {$lastName} added successfully (ID: {$userId}).";
                $msgType = 'success';
            }
        }

    } elseif ($action === 'update') {
        $userId    = $_POST['user_id']         ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $userType  = $_POST['user_type'] === 'ADMIN' ? 'ADMIN' : 'STANDARD';
        $newPass   = trim($_POST['password']   ?? '');

        if (!$firstName || !$lastName || !$email) {
            $msg = 'First name, last name and email are required.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
            $stmt->execute([$userId]);
            $editing = $stmt->fetch();
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM SS_USERS WHERE EMAIL = ? AND USER_ID != ?");
            $chk->execute([$email, $userId]);
            if ($chk->fetchColumn() > 0) {
                $msg = 'That email is already used by another user.';
                $msgType = 'error';
                $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
                $stmt->execute([$userId]);
                $editing = $stmt->fetch();
            } else {
                if ($newPass) {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE SS_USERS SET FIRST_NAME=?, LAST_NAME=?, EMAIL=?, PHONE=?, USER_TYPE=?, PASSWORD_HASH=?, UPDATED_AT=NOW() WHERE USER_ID=?");
                    $stmt->execute([$firstName, $lastName, $email, $phone ?: null, $userType, $hash, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE SS_USERS SET FIRST_NAME=?, LAST_NAME=?, EMAIL=?, PHONE=?, USER_TYPE=?, UPDATED_AT=NOW() WHERE USER_ID=?");
                    $stmt->execute([$firstName, $lastName, $email, $phone ?: null, $userType, $userId]);
                }
                $msg = 'User updated successfully.';
                $msgType = 'success';
            }
        }

    } elseif ($action === 'toggle_status') {
        $userId    = $_POST['user_id']    ?? '';
        $newStatus = $_POST['new_status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE';
        $pdo->prepare("UPDATE SS_USERS SET STATUS=?, UPDATED_AT=NOW() WHERE USER_ID=?")->execute([$newStatus, $userId]);
        $msg = 'User status updated.';
        $msgType = 'success';

    } elseif ($action === 'reset_password') {
        $userId = $_POST['user_id'] ?? '';
        $stmt   = $pdo->prepare("SELECT EMAIL, FIRST_NAME FROM SS_USERS WHERE USER_ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
            $pdo->prepare("DELETE FROM SS_PASSWORD_RESETS WHERE USER_ID = ?")->execute([$userId]);
            $pdo->prepare("INSERT INTO SS_PASSWORD_RESETS (USER_ID, TOKEN, EXPIRES_AT) VALUES (?,?,?)")->execute([$userId, $token, $expires]);
            $resetLink = APP_URL . '/reset_password.php?token=' . $token;
            $msg = 'Password reset link for ' . h($user['FIRST_NAME']) . ': <a href="' . h($resetLink) . '" target="_blank" style="color:#155724;font-weight:600;">Copy Link ↗</a>';
            $msgType = 'success';
        }
    }
}

// Load edit target
if (!$editing && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Fetch all users
$users = $pdo->query("SELECT * FROM SS_USERS ORDER BY STATUS ASC, LAST_NAME ASC, FIRST_NAME ASC")->fetchAll();

// Gift counts
$giftCounts = [];
foreach ($pdo->query("SELECT USER_ID, COUNT(*) as CNT FROM SS_GIFTS GROUP BY USER_ID")->fetchAll() as $row) {
    $giftCounts[$row['USER_ID']] = $row['CNT'];
}

// Form should be open if editing or there was a form error
$formOpen = $editing || ($msgType === 'error') ? 'true' : 'false';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">👥 User Management</h1>
    <button class="btn btn-primary" id="toggleFormBtn" onclick="toggleForm()">➕ Add New User</button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= $msg ?></div>
<?php endif; ?>

<!-- Add / Edit Form (hidden by default) -->
<div class="card form-card" id="userForm" style="display:none;">
    <div class="card-title"><?= $editing ? '✏️ Edit User' : '➕ Add New User' ?></div>
    <form method="POST" action="">
        <input type="hidden" name="action"  value="<?= $editing ? 'update' : 'add' ?>">
        <?php if ($editing): ?>
        <input type="hidden" name="user_id" value="<?= h($editing['USER_ID']) ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required maxlength="50"
                       value="<?= h($editing['FIRST_NAME'] ?? $_POST['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required maxlength="50"
                       value="<?= h($editing['LAST_NAME'] ?? $_POST['last_name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required maxlength="150"
                       value="<?= h($editing['EMAIL'] ?? $_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone <span class="optional">(optional)</span></label>
                <input type="tel" id="phone" name="phone" maxlength="20"
                       placeholder="813-555-0100"
                       value="<?= h($editing['PHONE'] ?? $_POST['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="user_type">User Type <span class="required">*</span></label>
                <select id="user_type" name="user_type">
                    <option value="STANDARD" <?= ($editing['USER_TYPE'] ?? 'STANDARD') === 'STANDARD' ? 'selected' : '' ?>>Standard</option>
                    <option value="ADMIN"    <?= ($editing['USER_TYPE'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">
                    <?= $editing ? 'New Password <span class="optional">(leave blank to keep current)</span>' : 'Password <span class="required">*</span>' ?>
                </label>
                <input type="password" id="password" name="password"
                       <?= $editing ? '' : 'required' ?> minlength="8"
                       placeholder="<?= $editing ? 'Leave blank to keep current' : 'Min 8 characters' ?>">
            </div>
        </div>

        <?php if ($editing): ?>
        <div class="user-id-note">
            User ID: <strong><?= h($editing['USER_ID']) ?></strong>
            &nbsp;|&nbsp; Status: <strong><?= h($editing['STATUS']) ?></strong>
            &nbsp;|&nbsp; Gifts added: <strong><?= $giftCounts[$editing['USER_ID']] ?? 0 ?></strong>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add User' ?></button>
            <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancel</button>
            <?php if ($editing): ?>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-secondary">Clear Form</a>
            <button type="button"
                    class="btn <?= $editing['STATUS'] === 'ACTIVE' ? 'btn-warning' : 'btn-success' ?>"
                    onclick="if(confirm('<?= $editing['STATUS'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?> <?= h($editing['FIRST_NAME']) ?>?')) document.getElementById('frmToggle').submit()">
                <?= $editing['STATUS'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?> User
            </button>
            <button type="button" class="btn btn-info"
                    onclick="if(confirm('Generate a password reset link for <?= h($editing['FIRST_NAME']) ?>?')) document.getElementById('frmReset').submit()">
                Reset Password
            </button>
            <?php endif; ?>
        </div>
    </form>
</div><!-- end .card form-card #userForm -->

<?php if ($editing): ?>
<!-- Hidden forms for toggle status and reset password -->
<form id="frmToggle" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"     value="toggle_status">
    <input type="hidden" name="user_id"    value="<?= h($editing['USER_ID']) ?>">
    <input type="hidden" name="new_status" value="<?= $editing['STATUS'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
</form>
<form id="frmReset" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"  value="reset_password">
    <input type="hidden" name="user_id" value="<?= h($editing['USER_ID']) ?>">
</form>
<?php endif; ?>

<!-- Users Table -->
<div class="card">
    <div class="card-title">👥 All Users (<?= count($users) ?>)</div>
    <!-- Filter bar -->
    <div class="filter-bar">
        <input type="text" id="filterText" placeholder="🔍 Search name, email, phone..." oninput="applyFilters()">
        <select id="filterType" onchange="applyFilters()">
            <option value="">All Types</option>
            <option value="ADMIN">Admin</option>
            <option value="STANDARD">Standard</option>
        </select>
        <select id="filterStatus" onchange="applyFilters()">
            <option value="">All Statuses</option>
            <option value="ACTIVE">Active</option>
            <option value="INACTIVE">Inactive</option>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
        <span id="filterCount" class="filter-count"></span>
    </div>

    <div class="table-wrap">
        <table id="usersTable">
            <thead>
                <tr>
                    <th class="sortable" data-col="0" onclick="sortTable(0)">Name <span class="sort-icon">↕</span></th>
                    <th class="sortable" data-col="1" onclick="sortTable(1)">Email <span class="sort-icon">↕</span></th>
                    <th>Phone</th>
                    <th class="sortable" data-col="3" onclick="sortTable(3)">Type <span class="sort-icon">↕</span></th>
                    <th class="sortable" data-col="4" onclick="sortTable(4)">Status <span class="sort-icon">↕</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr class="user-row <?= $user['STATUS'] === 'INACTIVE' ? 'row-inactive' : '' ?>"
                    data-name="<?= strtolower(h($user['FIRST_NAME'] . ' ' . $user['LAST_NAME'])) ?>"
                    data-email="<?= strtolower(h($user['EMAIL'])) ?>"
                    data-phone="<?= h($user['PHONE'] ?? '') ?>"
                    data-type="<?= h($user['USER_TYPE']) ?>"
                    data-status="<?= h($user['STATUS']) ?>">
                    <td>
                        <a href="?edit=<?= urlencode($user['USER_ID']) ?>" class="name-link">
                            <?= h($user['FIRST_NAME']) ?> <?= h($user['LAST_NAME']) ?>
                        </a>
                        <div class="user-id-small"><?= h($user['USER_ID']) ?></div>
                    </td>
                    <td class="nowrap"><?= h($user['EMAIL']) ?></td>
                    <td class="nowrap"><?= $user['PHONE'] ? h($user['PHONE']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <span class="badge <?= $user['USER_TYPE'] === 'ADMIN' ? 'badge-admin' : 'badge-standard' ?>">
                            <?= h($user['USER_TYPE']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $user['STATUS'] === 'ACTIVE' ? 'badge-active' : 'badge-inactive' ?>">
                            <?= h($user['STATUS']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.page-header  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-header .page-title { margin-bottom: 0; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.required { color: #c0392b; }
.optional { color: #999; font-weight: 400; font-size: 0.85rem; }
.muted    { color: #aaa; }
.nowrap   { white-space: nowrap; }

.form-actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; }
.user-id-note  { font-size: 0.85rem; color: #666; background: #f4f6f8; padding: 0.5rem 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
.user-id-small { font-size: 0.75rem; color: #999; margin-top: 0.15rem; }

.badge          { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 20px; white-space: nowrap; }
.badge-admin    { background: #922b21; color: #fff; }
.badge-standard { background: #e8e8e8; color: #444; }
.badge-active   { background: #d4edda; color: #155724; }
.badge-inactive { background: #f8d7da; color: #721c24; }

.row-inactive td { opacity: 0.55; }

.name-link { font-weight: 700; color: #c0392b; text-decoration: none; }
.name-link:hover { text-decoration: underline; }

.btn-warning { background: #e67e22; color: #fff; }
.btn-info    { background: #2980b9; color: #fff; }

/* Sorting & filtering */
.filter-bar { display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
.filter-bar input  { flex: 1; min-width: 200px; padding: 0.4rem 0.75rem; border: 1px solid #ccc; border-radius: 8px; font-size: 0.9rem; }
.filter-bar select { padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 8px; font-size: 0.9rem; }
.filter-count { font-size: 0.85rem; color: #888; white-space: nowrap; }

th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
th.sortable:hover { background: #e8e8e8; }
th.sort-asc  .sort-icon::after { content: " ▲"; }
th.sort-desc .sort-icon::after { content: " ▼"; }
.sort-icon { font-size: 0.75rem; color: #999; }
th.sort-asc .sort-icon, th.sort-desc .sort-icon { color: #c0392b; }
</style>

<script>
const formOpen = <?= $formOpen ?>;

function toggleForm() {
    const form = document.getElementById('userForm');
    const btn  = document.getElementById('toggleFormBtn');
    const open = form.style.display !== 'none';
    form.style.display = open ? 'none' : 'block';
    btn.textContent    = open ? '➕ Add New User' : '✖ Close Form';
}

if (formOpen) {
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('userForm');
        const btn  = document.getElementById('toggleFormBtn');
        form.style.display = 'block';
        btn.textContent    = '✖ Close Form';
    });
}

// ── Sorting ──────────────────────────────────────────────
let sortCol = -1, sortAsc = true;

function sortTable(col) {
    const table = document.getElementById('usersTable');
    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr.user-row'));

    if (sortCol === col) { sortAsc = !sortAsc; } else { sortCol = col; sortAsc = true; }

    // Update header icons
    table.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });
    const ths = table.querySelectorAll('th.sortable');
    // find the th with matching data-col
    table.querySelectorAll('th[data-col]').forEach(th => {
        if (parseInt(th.dataset.col) === col) {
            th.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');
        }
    });

    rows.sort((a, b) => {
        const aVal = a.cells[col]?.innerText.trim().toLowerCase() || '';
        const bVal = b.cells[col]?.innerText.trim().toLowerCase() || '';
        return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });

    rows.forEach(r => tbody.appendChild(r));
    updateCount();
}

// ── Filtering ─────────────────────────────────────────────
function applyFilters() {
    const text   = document.getElementById('filterText').value.toLowerCase();
    const type   = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;

    const rows = document.querySelectorAll('#usersTable tbody tr.user-row');
    let visible = 0;

    rows.forEach(row => {
        const name   = row.dataset.name   || '';
        const email  = row.dataset.email  || '';
        const phone  = row.dataset.phone  || '';
        const rType  = row.dataset.type   || '';
        const rStatus= row.dataset.status || '';

        const matchText   = !text   || name.includes(text) || email.includes(text) || phone.includes(text);
        const matchType   = !type   || rType === type;
        const matchStatus = !status || rStatus === status;

        const show = matchText && matchType && matchStatus;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    updateCount(visible, rows.length);
}

function clearFilters() {
    document.getElementById('filterText').value   = '';
    document.getElementById('filterType').value   = '';
    document.getElementById('filterStatus').value = '';
    applyFilters();
}

function updateCount(visible, total) {
    const el = document.getElementById('filterCount');
    if (visible === undefined) {
        const rows = document.querySelectorAll('#usersTable tbody tr.user-row');
        visible = Array.from(rows).filter(r => r.style.display !== 'none').length;
        total   = rows.length;
    }
    el.textContent = visible < total ? `Showing ${visible} of ${total} users` : '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>