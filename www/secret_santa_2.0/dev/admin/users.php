<?php
// ============================================================
// admin/users.php
// Add, edit, and manage users. Admin only.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$pdo     = getDB();
$msg     = '';
$msgType = '';
$editing = null;
$addMode = isset($_GET['add']);

// ------------------------------------------------------------
// All assignable roles (exclude all_roles — never for users)
// ------------------------------------------------------------
$allRoles = $pdo->query("
    SELECT ROLE_ID, ROLE_KEY, ROLE_NAME
    FROM SS_ROLES
    WHERE ROLE_KEY != 'all_roles'
    ORDER BY SORT_ORDER ASC
")->fetchAll();

// ------------------------------------------------------------
// Role descriptions from SS_CONFIG (ROLE_DESC_* keys)
// ------------------------------------------------------------
$roleDescRows = $pdo->query("
    SELECT CONFIG_KEY, CONFIG_VALUE FROM SS_CONFIG
    WHERE CONFIG_KEY LIKE 'ROLE_DESC_%'
")->fetchAll();
$roleDescs = [];  // keyed by lowercase role_key, e.g. 'admin', 'secret_santa'
foreach ($roleDescRows as $rd) {
    $key = strtolower(str_replace('ROLE_DESC_', '', $rd['CONFIG_KEY']));
    $roleDescs[$key] = $rd['CONFIG_VALUE'];
}

// ------------------------------------------------------------
// Helper: sync role assignments for a user
// ------------------------------------------------------------
function saveUserRoles(string $userId, array $selectedRoleIds, PDO $pdo): void {
    $pdo->prepare("DELETE FROM SS_USER_ROLES WHERE USER_ID = ?")->execute([$userId]);
    $ins = $pdo->prepare("INSERT IGNORE INTO SS_USER_ROLES (USER_ID, ROLE_ID) VALUES (?, ?)");
    foreach ($selectedRoleIds as $roleId) {
        $ins->execute([$userId, (int)$roleId]);
    }
}

// Helper: derive USER_TYPE from selected role IDs (keep legacy column in sync)
function deriveUserType(array $selectedRoleIds, array $allRoles): string {
    foreach ($allRoles as $r) {
        if ($r['ROLE_KEY'] === 'admin' && in_array($r['ROLE_ID'], $selectedRoleIds)) {
            return 'ADMIN';
        }
    }
    return 'STANDARD';
}

// Helper: save wishlist access for a gifter
function saveWishlistAccess(string $gifterUserId, array $wishlistUserIds, PDO $pdo): void {
    $pdo->prepare("DELETE FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ?")->execute([$gifterUserId]);
    $ins = $pdo->prepare("INSERT IGNORE INTO SS_WISHLIST_ACCESS (GIFTER_USER_ID, WISHLIST_USER_ID) VALUES (?, ?)");
    foreach ($wishlistUserIds as $wuid) {
        if ($wuid) $ins->execute([$gifterUserId, $wuid]);
    }
}

// ------------------------------------------------------------
// POST handlers
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- ADD --
    if ($action === 'add') {
        $firstName       = trim($_POST['first_name'] ?? '');
        $lastName        = trim($_POST['last_name']  ?? '');
        $email           = trim($_POST['email']      ?? '');
        $phone           = trim($_POST['phone']      ?? '');
        $sex             = in_array($_POST['sex'] ?? '', ['MALE', 'FEMALE']) ? $_POST['sex'] : null;
        $selectedRoleIds = array_map('intval', (array)($_POST['roles'] ?? []));
        $password        = trim($_POST['password']   ?? '');

        if (!$firstName || !$lastName || !$email || !$password) {
            $msg     = 'First name, last name, email and password are required.';
            $msgType = 'error';
            $addMode = true;
        } elseif (empty($selectedRoleIds)) {
            $msg     = 'Please assign at least one role.';
            $msgType = 'error';
            $addMode = true;
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM SS_USERS WHERE EMAIL = ?");
            $chk->execute([$email]);
            if ($chk->fetchColumn() > 0) {
                $msg     = 'A user with that email already exists.';
                $msgType = 'error';
                $addMode = true;
            } else {
                $userId   = generateUserId($firstName, $lastName, $pdo);
                $hash     = password_hash($password, PASSWORD_BCRYPT);
                $userType = deriveUserType($selectedRoleIds, $allRoles);
                $pdo->prepare("
                    INSERT INTO SS_USERS (USER_ID, FIRST_NAME, LAST_NAME, SEX, EMAIL, PASSWORD_HASH, PHONE, USER_TYPE, STATUS)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')
                ")->execute([$userId, $firstName, $lastName, $sex, $email, $hash, $phone ?: null, $userType]);
                saveUserRoles($userId, $selectedRoleIds, $pdo);
                $msg     = "User {$firstName} {$lastName} added (ID: {$userId}).";
                $msgType = 'success';
                $addMode = false;
            }
        }

    // -- UPDATE --
    } elseif ($action === 'update') {
        $userId          = $_POST['user_id']         ?? '';
        $firstName       = trim($_POST['first_name'] ?? '');
        $lastName        = trim($_POST['last_name']  ?? '');
        $email           = trim($_POST['email']      ?? '');
        $phone           = trim($_POST['phone']      ?? '');
        $sex             = in_array($_POST['sex'] ?? '', ['MALE', 'FEMALE']) ? $_POST['sex'] : null;
        $selectedRoleIds = array_map('intval', (array)($_POST['roles'] ?? []));
        $newPass         = trim($_POST['password']   ?? '');
        $wishlistAccess  = (array)($_POST['wishlist_access'] ?? []);

        if (!$firstName || !$lastName || !$email) {
            $msg     = 'First name, last name and email are required.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
            $stmt->execute([$userId]);
            $editing = $stmt->fetch();
        } elseif (empty($selectedRoleIds)) {
            $msg     = 'Please assign at least one role.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
            $stmt->execute([$userId]);
            $editing = $stmt->fetch();
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM SS_USERS WHERE EMAIL = ? AND USER_ID != ?");
            $chk->execute([$email, $userId]);
            if ($chk->fetchColumn() > 0) {
                $msg     = 'That email is already used by another user.';
                $msgType = 'error';
                $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
                $stmt->execute([$userId]);
                $editing = $stmt->fetch();
            } else {
                $userType = deriveUserType($selectedRoleIds, $allRoles);
                if ($newPass) {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE SS_USERS SET FIRST_NAME=?,LAST_NAME=?,SEX=?,EMAIL=?,PHONE=?,USER_TYPE=?,PASSWORD_HASH=?,UPDATED_AT=NOW() WHERE USER_ID=?")
                        ->execute([$firstName, $lastName, $sex, $email, $phone ?: null, $userType, $hash, $userId]);
                } else {
                    $pdo->prepare("UPDATE SS_USERS SET FIRST_NAME=?,LAST_NAME=?,SEX=?,EMAIL=?,PHONE=?,USER_TYPE=?,UPDATED_AT=NOW() WHERE USER_ID=?")
                        ->execute([$firstName, $lastName, $sex, $email, $phone ?: null, $userType, $userId]);
                }
                saveUserRoles($userId, $selectedRoleIds, $pdo);

                // Save wishlist access if gifter role is selected
                $hasGifterRole = false;
                foreach ($allRoles as $r) {
                    if ($r['ROLE_KEY'] === 'wishlist_gifter' && in_array($r['ROLE_ID'], $selectedRoleIds)) {
                        $hasGifterRole = true;
                        break;
                    }
                }
                if ($hasGifterRole) {
                    saveWishlistAccess($userId, $wishlistAccess, $pdo);
                } else {
                    // Role removed — clean up access
                    $pdo->prepare("DELETE FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ?")->execute([$userId]);
                }

                $msg     = 'User updated successfully.';
                $msgType = 'success';
            }
        }

    // -- TOGGLE STATUS --
    } elseif ($action === 'toggle_status') {
        $userId    = $_POST['user_id']    ?? '';
        $newStatus = $_POST['new_status'] === 'ACTIVE' ? 'ACTIVE' : 'INACTIVE';
        $pdo->prepare("UPDATE SS_USERS SET STATUS=?,UPDATED_AT=NOW() WHERE USER_ID=?")->execute([$newStatus, $userId]);
        $msg     = 'User status updated.';
        $msgType = 'success';

    // -- RESET PASSWORD --
    } elseif ($action === 'reset_password') {
        $userId = $_POST['user_id'] ?? '';
        $stmt   = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ? AND STATUS = 'ACTIVE'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $result = sendPasswordReset($user, $pdo);
            if ($result === true) {
                $msg     = "Password reset email sent to {$user['FIRST_NAME']} {$user['LAST_NAME']} ({$user['EMAIL']}).";
                $msgType = 'success';
            } else {
                $msg     = "Failed to send reset email: {$result}";
                $msgType = 'error';
            }
        }
    }
}

// Load edit target from GET
if (!$editing && isset($_GET['edit']) && $msgType !== 'success') {
    $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Current roles for the user being edited
$editingRoleIds = [];
if ($editing) {
    $stmt = $pdo->prepare("SELECT ROLE_ID FROM SS_USER_ROLES WHERE USER_ID = ?");
    $stmt->execute([$editing['USER_ID']]);
    $editingRoleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $editingRoleIds = array_map('intval', $editingRoleIds);
}

// Wishlist-only users (for gifter access assignment)
$wishlistOnlyUsers = $pdo->query("
    SELECT u.USER_ID, u.FIRST_NAME, u.LAST_NAME
    FROM SS_USERS u
    JOIN SS_USER_ROLES ur ON ur.USER_ID = u.USER_ID
    JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
    WHERE r.ROLE_KEY = 'wishlist_only' AND u.STATUS = 'ACTIVE'
    ORDER BY u.FIRST_NAME ASC
")->fetchAll();

// Current wishlist access for user being edited
$editingWishlistAccess = [];
if ($editing) {
    $stmt = $pdo->prepare("SELECT WISHLIST_USER_ID FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ?");
    $stmt->execute([$editing['USER_ID']]);
    $editingWishlistAccess = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Check if editing user has gifter role
$editingIsGifter = false;
foreach ($allRoles as $r) {
    if ($r['ROLE_KEY'] === 'wishlist_gifter' && in_array($r['ROLE_ID'], $editingRoleIds)) {
        $editingIsGifter = true;
        break;
    }
}

// All users for the table
$users = $pdo->query("SELECT * FROM SS_USERS ORDER BY STATUS ASC, LAST_NAME ASC, FIRST_NAME ASC")->fetchAll();

// Roles per user for table display
$userRolesMap = [];
$urStmt = $pdo->query("
    SELECT ur.USER_ID, r.ROLE_KEY, r.ROLE_NAME
    FROM SS_USER_ROLES ur
    JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
    WHERE r.ROLE_KEY != 'all_roles'
    ORDER BY r.SORT_ORDER ASC
");
foreach ($urStmt->fetchAll() as $row) {
    $userRolesMap[$row['USER_ID']][] = $row;
}

// Gift counts (current year)
$xmasYear   = getConfig('XMAS_YEAR', date('Y'));
$giftCounts = [];
$gcStmt = $pdo->prepare("SELECT USER_ID, COUNT(*) as CNT FROM SS_GIFTS WHERE YEAR = ? GROUP BY USER_ID");
$gcStmt->execute([$xmasYear]);
foreach ($gcStmt->fetchAll() as $row) {
    $giftCounts[$row['USER_ID']] = $row['CNT'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">👥 User Management</h1>
    <a href="?add=1" class="btn btn-primary">➕ Add New User</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<?php
// ============================================================
// EDIT FORM
// ============================================================
if ($editing):
?>
<div class="card form-card" id="userForm">
    <div class="card-title">✏️ Edit User</div>
    <form method="POST" action="">
        <input type="hidden" name="action"  value="update">
        <input type="hidden" name="user_id" value="<?= h($editing['USER_ID']) ?>">

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
                <label for="sex">Sex <span class="optional">(optional)</span></label>
                <select id="sex" name="sex">
                    <option value="">— Not specified —</option>
                    <option value="MALE"   <?= ($editing['SEX'] ?? '') === 'MALE'   ? 'selected' : '' ?>>Male</option>
                    <option value="FEMALE" <?= ($editing['SEX'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                </select>
                <div class="field-hint">Used to personalize messages (e.g. "his" / "her" list)</div>
            </div>
            <div class="form-group">
                <label for="password">New Password <span class="optional">(leave blank to keep current)</span></label>
                <input type="password" id="password" name="password" minlength="8"
                       placeholder="Leave blank to keep current">
            </div>
        </div>

        <!-- Roles -->
        <div class="form-group">
            <label>Roles <span class="required">*</span></label>
            <div class="role-grid" id="roleGrid_edit"></div>
            <button type="button" class="btn btn-sm btn-add-role" id="addRoleBtn_edit"
                    onclick="addRoleRow('edit')">+ Add Role</button>
        </div>

        <!-- Wishlist Access (shown when wishlist_gifter is checked) -->
        <?php
        // Find the gifter role's checkbox ID for JS reference
        $gifterRoleId = null;
        foreach ($allRoles as $r) {
            if ($r['ROLE_KEY'] === 'wishlist_gifter') { $gifterRoleId = $r['ROLE_ID']; break; }
        }
        ?>
        <div id="wishlistAccessPanel" class="wishlist-access-panel" style="display:<?= $editingIsGifter ? 'block' : 'none' ?>;">
            <div class="form-group">
                <label>Wishlist Access</label>
                <div class="field-hint" style="margin-bottom:0.6rem;">
                    Select which Wishlist Only users this person can view and purchase for.
                </div>
                <?php if (empty($wishlistOnlyUsers)): ?>
                <p class="muted" style="font-size:0.9rem;">No Wishlist Only users exist yet.</p>
                <?php else: ?>
                <div class="role-checkboxes">
                    <?php foreach ($wishlistOnlyUsers as $wu): ?>
                    <label class="role-check-label">
                        <input type="checkbox" name="wishlist_access[]" value="<?= h($wu['USER_ID']) ?>"
                               <?= in_array($wu['USER_ID'], $editingWishlistAccess) ? 'checked' : '' ?>>
                        <span class="role-check-name"><?= h($wu['FIRST_NAME']) ?> <?= h($wu['LAST_NAME']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="user-id-note">
            User ID: <strong><?= h($editing['USER_ID']) ?></strong>
            &nbsp;|&nbsp; Status: <strong><?= h($editing['STATUS']) ?></strong>
            &nbsp;|&nbsp; Gifts added: <strong><?= $giftCounts[$editing['USER_ID']] ?? 0 ?></strong>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
            <button type="button"
                    class="btn <?= $editing['STATUS'] === 'ACTIVE' ? 'btn-warning' : 'btn-success' ?>"
                    onclick="if(confirm('<?= $editing['STATUS'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?> <?= h($editing['FIRST_NAME']) ?>?')) document.getElementById('frmToggle').submit()">
                <?= $editing['STATUS'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?> User
            </button>
            <button type="button" class="btn btn-info"
                    onclick="if(confirm('Send a password reset email to <?= h($editing['FIRST_NAME']) ?>?')) document.getElementById('frmReset').submit()">
                Reset Password
            </button>
        </div>
    </form>
</div>

<form id="frmToggle" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"     value="toggle_status">
    <input type="hidden" name="user_id"    value="<?= h($editing['USER_ID']) ?>">
    <input type="hidden" name="new_status" value="<?= $editing['STATUS'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
</form>
<form id="frmReset" method="POST" action="" style="display:none;">
    <input type="hidden" name="action"  value="reset_password">
    <input type="hidden" name="user_id" value="<?= h($editing['USER_ID']) ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    initRoleGrid('edit', <?= json_encode($editingRoleIds) ?>);
});
</script>
<?php endif; ?>

<?php
// ============================================================
// ADD FORM
// ============================================================
if ($addMode && !$editing):
?>
<div class="card">
    <div class="card-title">➕ Add New User</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">

        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required maxlength="50"
                       value="<?= h($_POST['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required maxlength="50"
                       value="<?= h($_POST['last_name'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required maxlength="150"
                       value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone <span class="optional">(optional)</span></label>
                <input type="tel" id="phone" name="phone" maxlength="20"
                       placeholder="813-555-0100"
                       value="<?= h($_POST['phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sex">Sex <span class="optional">(optional)</span></label>
                <select id="sex" name="sex">
                    <option value="">— Not specified —</option>
                    <option value="MALE"   <?= ($_POST['sex'] ?? '') === 'MALE'   ? 'selected' : '' ?>>Male</option>
                    <option value="FEMALE" <?= ($_POST['sex'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                </select>
                <div class="field-hint">Used to personalize messages (e.g. "his" / "her" list)</div>
            </div>
            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" required minlength="8"
                       placeholder="Min 8 characters">
            </div>
        </div>

        <!-- Roles -->
        <div class="form-group">
            <label>Roles <span class="required">*</span></label>
            <div class="role-grid" id="roleGrid_add"></div>
            <button type="button" class="btn btn-sm btn-add-role" id="addRoleBtn_add"
                    onclick="addRoleRow('add')">+ Add Role</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add User</button>
            <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initRoleGrid('add', <?= json_encode(array_map('intval', (array)($_POST['roles'] ?? []))) ?>);
});
</script>
<?php endif; ?>

<!-- ============================================================ -->
<!-- USERS TABLE                                                   -->
<!-- ============================================================ -->
<div class="card">
    <div class="card-title">👥 All Users (<?= count($users) ?>)</div>

    <div class="filter-bar">
        <input type="text" id="filterText" placeholder="🔍 Search name, email, phone..." oninput="applyFilters()">
        <select id="filterRole" onchange="applyFilters()">
            <option value="">All Roles</option>
            <?php foreach ($allRoles as $role): ?>
            <option value="<?= h($role['ROLE_KEY']) ?>"><?= h($role['ROLE_NAME']) ?></option>
            <?php endforeach; ?>
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
                    <th>Roles</th>
                    <th class="sortable" data-col="4" onclick="sortTable(4)">Status <span class="sort-icon">↕</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <?php
                    $roles     = $userRolesMap[$user['USER_ID']] ?? [];
                    $roleKeys  = implode(' ', array_column($roles, 'ROLE_KEY'));
                ?>
                <tr class="user-row <?= $user['STATUS'] === 'INACTIVE' ? 'row-inactive' : '' ?>"
                    data-name="<?= strtolower(h($user['FIRST_NAME'] . ' ' . $user['LAST_NAME'])) ?>"
                    data-email="<?= strtolower(h($user['EMAIL'])) ?>"
                    data-phone="<?= h($user['PHONE'] ?? '') ?>"
                    data-roles="<?= h($roleKeys) ?>"
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
                        <?php if (empty($roles)): ?>
                        <span class="muted">—</span>
                        <?php else: ?>
                        <div class="role-badge-list">
                            <?php foreach ($roles as $r): ?>
                            <span class="badge badge-role-<?= h($r['ROLE_KEY']) ?>"><?= h($r['ROLE_NAME']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
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
.page-header  { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; }
.page-header .page-title { margin-bottom:0; }

.form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media (max-width:600px) { .form-row { grid-template-columns:1fr; } }

.required { color:#c0392b; }
.optional  { color:#999; font-weight:400; font-size:0.85rem; }
.muted     { color:#aaa; }
.nowrap    { white-space:nowrap; }

.form-actions  { display:flex; gap:0.75rem; margin-top:0.5rem; flex-wrap:wrap; }
.user-id-note  { font-size:0.85rem; color:#666; background:#f4f6f8; padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:1rem; }
.user-id-small { font-size:0.75rem; color:#999; margin-top:0.15rem; }
.field-hint    { font-size:0.82rem; color:#888; margin-top:0.3rem; }

/* Role grid */
.role-grid { display:flex; flex-direction:column; gap:0.4rem; margin-top:0.3rem; margin-bottom:0.5rem; }
.role-grid-row {
    display:flex; align-items:center; gap:0.5rem;
    background:#fff; border:1px solid #e0e0e0; border-radius:8px;
    padding:0.35rem 0.5rem;
    border-left:4px solid #ccc;
    transition:border-color 0.15s;
}
.role-grid-row select {
    flex:0 0 190px; border:none; background:transparent; font-size:0.9rem;
    font-family:inherit; cursor:pointer; outline:none; color:#212529;
    padding:0.15rem 0;
}
.role-desc {
    flex:1; font-size:0.8rem; color:#888; font-style:italic;
    padding:0 0.5rem; line-height:1.35;
    border-left:1px solid #e0e0e0; margin-left:0.25rem;
}
.role-grid-row .remove-role-btn {
    background:none; border:none; color:#aaa; font-size:1rem; font-weight:700;
    cursor:pointer; padding:0 0.25rem; line-height:1; border-radius:4px;
    transition:color 0.15s, background 0.15s;
}
.role-grid-row .remove-role-btn:hover { color:#c0392b; background:#fdecea; }
.btn-add-role {
    background:#f4f6f8; color:#444; border:1px dashed #bbb;
    font-size:0.85rem; padding:0.3rem 0.8rem; border-radius:8px;
    cursor:pointer; transition:background 0.15s, border-color 0.15s;
}
.btn-add-role:hover { background:#e8f0fb; border-color:#5b9bd5; color:#1a5276; }
.btn-add-role:disabled { opacity:0.45; cursor:not-allowed; }

/* Wishlist access checkboxes (still used in access panel) */
.role-checkboxes { display:flex; flex-wrap:wrap; gap:0.5rem 1rem; margin-top:0.3rem; }
.role-check-label { display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.9rem; }
.role-check-label input { cursor:pointer; }
.role-check-name { font-weight:500; }

/* Row accent colors per role */
.role-grid-row[data-role="admin"]           { border-left-color:#922b21; }
.role-grid-row[data-role="secret_santa"]    { border-left-color:#1a5276; }
.role-grid-row[data-role="wishlist_only"]   { border-left-color:#6c3483; }
.role-grid-row[data-role="wishlist_gifter"] { border-left-color:#1e8449; }

/* Wishlist access panel */
.wishlist-access-panel {
    background:#f4f6f8;
    border-left:3px solid #c0392b;
    border-radius:0 6px 6px 0;
    padding:0.9rem 1rem;
    margin-bottom:1rem;
}

/* Badges */
.badge { display:inline-block; font-size:0.72rem; font-weight:700; padding:0.18rem 0.5rem; border-radius:20px; white-space:nowrap; }
.badge-active   { background:#d4edda; color:#155724; }
.badge-inactive { background:#f8d7da; color:#721c24; }

.role-badge-list { display:flex; flex-wrap:wrap; gap:0.3rem; }
.badge-role-admin           { background:#922b21; color:#fff; }
.badge-role-secret_santa    { background:#1a5276; color:#fff; }
.badge-role-wishlist_only   { background:#6c3483; color:#fff; }
.badge-role-wishlist_gifter { background:#1e8449; color:#fff; }

.row-inactive td { opacity:0.55; }
.name-link { font-weight:700; color:#c0392b; text-decoration:none; }
.name-link:hover { text-decoration:underline; }

.btn-warning { background:#e67e22; color:#fff; }
.btn-info    { background:#2980b9; color:#fff; }

/* Filtering */
.filter-bar { display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem; }
.filter-bar input  { flex:1; min-width:200px; padding:0.4rem 0.75rem; border:1px solid #ccc; border-radius:8px; font-size:0.9rem; }
.filter-bar select { padding:0.4rem 0.6rem; border:1px solid #ccc; border-radius:8px; font-size:0.9rem; }
.filter-count { font-size:0.85rem; color:#888; white-space:nowrap; }

th.sortable { cursor:pointer; user-select:none; white-space:nowrap; }
th.sortable:hover { background:#e8e8e8; }
.sort-icon { font-size:0.75rem; color:#999; }
th.sort-asc .sort-icon, th.sort-desc .sort-icon { color:#c0392b; }
</style>

<script>
// ── Role grid data ────────────────────────────────────────────
const ALL_ROLES      = <?= json_encode(array_values($allRoles)) ?>;
const GIFTER_ROLE_ID = '<?= $gifterRoleId ?>';
const ROLE_DESCS     = <?= json_encode($roleDescs) ?>;  // keyed by role_key

// Key role data by ID for fast lookup
const ROLE_BY_ID = {};
ALL_ROLES.forEach(r => { ROLE_BY_ID[String(r.ROLE_ID)] = r; });

// ── Role grid init ────────────────────────────────────────────
function initRoleGrid(gridKey, preselected) {
    if (!preselected || preselected.length === 0) {
        addRoleRow(gridKey, '');  // start with one empty row
    } else {
        preselected.forEach(id => addRoleRow(gridKey, String(id)));
    }
}

// ── Add a row ─────────────────────────────────────────────────
function addRoleRow(gridKey, selectedValue) {
    selectedValue = selectedValue !== undefined ? String(selectedValue) : '';
    const grid = document.getElementById('roleGrid_' + gridKey);
    if (!grid) return;

    const row = document.createElement('div');
    row.className = 'role-grid-row';
    if (selectedValue && ROLE_BY_ID[selectedValue]) {
        row.dataset.role = ROLE_BY_ID[selectedValue].ROLE_KEY;
    }

    const sel = document.createElement('select');
    sel.name = 'roles[]';
    sel.className = 'role-select';

    // Placeholder option
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = '— Select a role —';
    sel.appendChild(ph);

    ALL_ROLES.forEach(role => {
        const opt = document.createElement('option');
        opt.value = role.ROLE_ID;
        opt.textContent = role.ROLE_NAME;
        if (String(role.ROLE_ID) === selectedValue) opt.selected = true;
        sel.appendChild(opt);
    });

    sel.addEventListener('change', function () {
        const role = ROLE_BY_ID[this.value];
        const key  = role ? role.ROLE_KEY : '';
        row.dataset.role = key;
        descSpan.textContent = key && ROLE_DESCS[key] ? ROLE_DESCS[key] : '';
        refreshDropdowns(gridKey);
        updateAddBtn(gridKey);
        toggleWishlistAccess();
    });

    // Description span — shows the role's purpose text
    const descSpan = document.createElement('span');
    descSpan.className = 'role-desc';
    const initRole = ROLE_BY_ID[selectedValue];
    descSpan.textContent = initRole && ROLE_DESCS[initRole.ROLE_KEY]
        ? ROLE_DESCS[initRole.ROLE_KEY] : '';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'remove-role-btn';
    removeBtn.innerHTML = '&times;';
    removeBtn.title = 'Remove';
    removeBtn.addEventListener('click', function () {
        row.remove();
        refreshDropdowns(gridKey);
        updateAddBtn(gridKey);
        toggleWishlistAccess();
    });

    row.appendChild(sel);
    row.appendChild(descSpan);
    row.appendChild(removeBtn);
    grid.appendChild(row);

    refreshDropdowns(gridKey);
    updateAddBtn(gridKey);
    toggleWishlistAccess();
}

// ── Refresh all dropdowns in a grid (disable already-used options) ──
function refreshDropdowns(gridKey) {
    const grid = document.getElementById('roleGrid_' + gridKey);
    if (!grid) return;
    const selects = Array.from(grid.querySelectorAll('select.role-select'));
    const used = selects.map(s => s.value).filter(v => v !== '');

    selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;  // keep placeholder
            opt.disabled = used.includes(opt.value) && opt.value !== cur;
        });
    });
}

// ── Enable/disable the Add Role button ────────────────────────
function updateAddBtn(gridKey) {
    const btn  = document.getElementById('addRoleBtn_' + gridKey);
    if (!btn) return;
    const grid = document.getElementById('roleGrid_' + gridKey);
    const used = Array.from(grid.querySelectorAll('select.role-select'))
        .map(s => s.value).filter(v => v !== '');
    btn.disabled = used.length >= ALL_ROLES.length;
}

// ── Show/hide wishlist access panel ───────────────────────────
function toggleWishlistAccess() {
    const panel = document.getElementById('wishlistAccessPanel');
    if (!panel) return;
    const gifterSelected = Array.from(document.querySelectorAll('select.role-select'))
        .some(s => s.value === GIFTER_ROLE_ID);
    panel.style.display = gifterSelected ? 'block' : 'none';
}

// ── Strip unselected role rows before submit ───────────────────
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function () {
        this.querySelectorAll('select.role-select').forEach(sel => {
            if (!sel.value) sel.disabled = true;
        });
    });
});

// ── Sorting ──────────────────────────────────────────────────
let sortCol = -1, sortAsc = true;
function sortTable(col) {
    const table = document.getElementById('usersTable');
    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr.user-row'));
    if (sortCol === col) { sortAsc = !sortAsc; } else { sortCol = col; sortAsc = true; }
    table.querySelectorAll('th[data-col]').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (parseInt(th.dataset.col) === col) th.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');
    });
    rows.sort((a, b) => {
        const aVal = a.cells[col]?.innerText.trim().toLowerCase() || '';
        const bVal = b.cells[col]?.innerText.trim().toLowerCase() || '';
        return sortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    rows.forEach(r => tbody.appendChild(r));
    updateCount();
}

// ── Filtering ─────────────────────────────────────────────────
function applyFilters() {
    const text   = document.getElementById('filterText').value.toLowerCase();
    const role   = document.getElementById('filterRole').value;
    const status = document.getElementById('filterStatus').value;
    const rows   = document.querySelectorAll('#usersTable tbody tr.user-row');
    let visible  = 0;
    rows.forEach(row => {
        const matchText   = !text   || row.dataset.name.includes(text) || row.dataset.email.includes(text) || row.dataset.phone.includes(text);
        const matchRole   = !role   || row.dataset.roles.split(' ').includes(role);
        const matchStatus = !status || row.dataset.status === status;
        const show = matchText && matchRole && matchStatus;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    updateCount(visible, rows.length);
}

function clearFilters() {
    document.getElementById('filterText').value   = '';
    document.getElementById('filterRole').value   = '';
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
