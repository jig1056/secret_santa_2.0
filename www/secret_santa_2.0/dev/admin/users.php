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
    SELECT ROLE_ID, ROLE_NAME
    FROM SS_ROLES
    WHERE ROLE_ID != 'all_roles'
    ORDER BY SORT_ORDER ASC
")->fetchAll();

// ------------------------------------------------------------
// Role descriptions from SS_CONFIG (ROLE_DESC_* keys)
// ------------------------------------------------------------
$roleDescRows = $pdo->query("
    SELECT CONFIG_KEY, CONFIG_VALUE FROM SS_CONFIG
    WHERE CONFIG_KEY LIKE 'ROLE_DESC_%'
")->fetchAll();
$roleDescs = [];
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
        $ins->execute([$userId, $roleId]);
    }
}

// Helper: derive USER_TYPE from selected role IDs (keep legacy column in sync)
function deriveUserType(array $selectedRoleIds, array $allRoles): string {
    foreach ($allRoles as $r) {
        if ($r['ROLE_ID'] === 'admin' && in_array($r['ROLE_ID'], $selectedRoleIds)) {
            return 'ADMIN';
        }
    }
    return 'STANDARD';
}

// Helper: save wishlist access for a gifter
function saveWishlistAccess(string $gifterUserId, array $wishlistUserIds, array $canEditIds, PDO $pdo): void {
    $pdo->prepare("DELETE FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ?")->execute([$gifterUserId]);
    $ins = $pdo->prepare("INSERT IGNORE INTO SS_WISHLIST_ACCESS (GIFTER_USER_ID, WISHLIST_USER_ID, CAN_EDIT) VALUES (?, ?, ?)");
    foreach ($wishlistUserIds as $wuid) {
        if ($wuid) $ins->execute([$gifterUserId, $wuid, in_array($wuid, $canEditIds) ? 'Y' : 'N']);
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
        $selectedRoleIds = (array)($_POST['roles'] ?? []);
        $password        = trim($_POST['password']   ?? '');

        if (!$firstName || !$lastName || !$email || !$password) {
            $msg     = 'First name, last name, email and password are required.';
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
                header('Location: ?edit=' . urlencode($userId) . '&saved=1');
                exit;
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
        $selectedRoleIds = (array)($_POST['roles'] ?? []);
        $newPass         = trim($_POST['password']   ?? '');
        $wishlistAccess  = (array)($_POST['wishlist_access'] ?? []);
        $wishlistCanEdit = (array)($_POST['wishlist_can_edit'] ?? []);

        if (!$firstName || !$lastName || !$email) {
            $msg     = 'First name, last name and email are required.';
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
                    if ($r['ROLE_ID'] === 'wishlist_gifter' && in_array($r['ROLE_ID'], $selectedRoleIds)) {
                        $hasGifterRole = true;
                        break;
                    }
                }
                if ($hasGifterRole) {
                    saveWishlistAccess($userId, $wishlistAccess, $wishlistCanEdit, $pdo);
                } else {
                    // Role removed — clean up access
                    $pdo->prepare("DELETE FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ?")->execute([$userId]);
                }

                $msg     = 'User updated successfully.';
                $msgType = 'success';
                // Reload the user so the edit form stays open after save
                $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
                $stmt->execute([$userId]);
                $editing = $stmt->fetch() ?: null;
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

// Flash message from redirect after create
if (isset($_GET['saved']) && !$msg) {
    $msg     = 'User created successfully.';
    $msgType = 'success';
}

// Load edit target from GET
if (!$editing && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Track where the user came from for the back button
$fromPage = $_GET['from'] ?? $_POST['from'] ?? 'list';

// Current roles for the user being edited
$editingRoleIds = [];
if ($editing) {
    $stmt = $pdo->prepare("SELECT ROLE_ID FROM SS_USER_ROLES WHERE USER_ID = ?");
    $stmt->execute([$editing['USER_ID']]);
    $editingRoleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Wishlist-only users (for gifter access assignment)
$wishlistOnlyUsers = $pdo->query("
    SELECT u.USER_ID, u.FIRST_NAME, u.LAST_NAME
    FROM SS_USERS u
    JOIN SS_USER_ROLES ur ON ur.USER_ID = u.USER_ID
    JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
    WHERE r.ROLE_ID = 'wishlist_only' AND u.STATUS = 'ACTIVE'
    ORDER BY u.FIRST_NAME ASC
")->fetchAll();

// Current wishlist access for user being edited
$editingWishlistAccess  = [];
$editingWishlistCanEdit = [];
if ($editing) {
    $stmt = $pdo->prepare("SELECT WISHLIST_USER_ID, CAN_EDIT FROM SS_WISHLIST_ACCESS WHERE GIFTER_USER_ID = ?");
    $stmt->execute([$editing['USER_ID']]);
    foreach ($stmt->fetchAll() as $row) {
        $editingWishlistAccess[] = $row['WISHLIST_USER_ID'];
        if ($row['CAN_EDIT'] === 'Y') $editingWishlistCanEdit[] = $row['WISHLIST_USER_ID'];
    }
}

// Check if editing user has gifter role
$editingIsGifter = false;
foreach ($allRoles as $r) {
    if ($r['ROLE_ID'] === 'wishlist_gifter' && in_array($r['ROLE_ID'], $editingRoleIds)) {
        $editingIsGifter = true;
        break;
    }
}

// Gifter role ID (needed in JS for both add and edit modes)
$gifterRoleId = null;
foreach ($allRoles as $r) {
    if ($r['ROLE_ID'] === 'wishlist_gifter') { $gifterRoleId = $r['ROLE_ID']; break; }
}

// Wishlist access map (gifter → list of wishlist user names)
$wishlistAccessMap = [];
$waStmt = $pdo->query("
    SELECT wa.GIFTER_USER_ID, u.FIRST_NAME, u.LAST_NAME
    FROM SS_WISHLIST_ACCESS wa
    JOIN SS_USERS u ON u.USER_ID = wa.WISHLIST_USER_ID
    ORDER BY u.FIRST_NAME ASC
");
foreach ($waStmt->fetchAll() as $row) {
    $wishlistAccessMap[$row['GIFTER_USER_ID']][] = $row['FIRST_NAME'] . ' ' . $row['LAST_NAME'];
}

// All users for the table
$users = $pdo->query("SELECT * FROM SS_USERS ORDER BY STATUS ASC, LAST_NAME ASC, FIRST_NAME ASC")->fetchAll();

// Roles per user for table display
$userRolesMap = [];
$urStmt = $pdo->query("
    SELECT ur.USER_ID, r.ROLE_ID, r.ROLE_NAME
    FROM SS_USER_ROLES ur
    JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
    WHERE r.ROLE_ID != 'all_roles'
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
    <h1 class="page-title">👥 Users</h1>
    <div style="display:flex;gap:0.5rem;">
        <a href="?report=1" class="btn btn-secondary">📋 User Report</a>
        <a href="?add=1" class="btn btn-primary">+ Add New User</a>
    </div>
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
        <input type="hidden" name="from"    value="<?= h($fromPage) ?>">

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

        <!-- Wishlist Access (shown when wishlist_gifter is selected) -->
        <div id="wishlistAccessPanel" class="wishlist-access-panel" style="display:<?= $editingIsGifter ? 'block' : 'none' ?>;">
            <div class="form-group">
                <label>Wishlist Access</label>
                <div class="field-hint" style="margin-bottom:0.6rem;">
                    Select which Wishlist Only users this person can view and purchase for.
                </div>
                <?php if (empty($wishlistOnlyUsers)): ?>
                <p class="muted" style="font-size:0.9rem;">No Wishlist Only users exist yet.</p>
                <?php else: ?>
                <div class="role-grid" id="wishlistGrid"></div>
                <button type="button" class="btn btn-sm btn-add-role" id="addWishlistBtn"
                        onclick="addWishlistRow()">+ Add Giftee</button>
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
            <?php
            $backUrl  = $fromPage === 'dashboard'
                ? APP_URL . '/admin/dashboard.php'
                : APP_URL . '/admin/users.php';
            $backLabel = $fromPage === 'dashboard' ? '↩ Return to Dashboard' : '↩ Return to List';
            ?>
            <a href="<?= $backUrl ?>" class="btn btn-secondary">Cancel</a>
            <button type="button"
                    class="btn <?= $editing['STATUS'] === 'ACTIVE' ? 'btn-warning' : 'btn-success' ?>"
                    onclick="if(confirm('<?= $editing['STATUS'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?> <?= h($editing['FIRST_NAME']) ?>?')) document.getElementById('frmToggle').submit()">
                <?= $editing['STATUS'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?> User
            </button>
            <button type="button" class="btn btn-info"
                    onclick="if(confirm('Send a password reset email to <?= h($editing['FIRST_NAME']) ?>?')) document.getElementById('frmReset').submit()">
                Reset Password
            </button>
            <a href="<?= $backUrl ?>" class="btn btn-secondary"><?= $backLabel ?></a>
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
    initWishlistGrid(<?= json_encode($editingWishlistAccess) ?>, <?= json_encode($editingWishlistCanEdit) ?>);
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
            <label>Roles <span class="optional">(optional)</span></label>
            <div class="field-hint" style="margin-bottom:0.5rem;">⚠️ Without a role, this user won't be able to log in or access any features.</div>
            <div class="role-grid" id="roleGrid_add"></div>
            <button type="button" class="btn btn-sm btn-add-role" id="addRoleBtn_add"
                    onclick="addRoleRow('add')">+ Add Role</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save User</button>
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
<!-- USER REPORT                                                   -->
<!-- ============================================================ -->
<?php if (!$editing && !$addMode && isset($_GET['report'])): ?>
<a href="<?= APP_URL ?>/admin/users.php" class="back-link">← Return to List</a>
<div class="card">
    <div class="card-title">📋 User Report (<?= count($users) ?> users)</div>
    <div class="table-wrap">
        <table class="report-table">
            <thead>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Roles</th>
                    <th>Wishlist Access</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <?php
                    $roles  = $userRolesMap[$user['USER_ID']] ?? [];
                    $access = $wishlistAccessMap[$user['USER_ID']] ?? [];
                ?>
                <tr class="<?= $user['STATUS'] === 'INACTIVE' ? 'row-inactive' : '' ?>">
                    <td><?= h($user['FIRST_NAME']) ?></td>
                    <td><?= h($user['LAST_NAME']) ?></td>
                    <td class="nowrap"><?= h($user['EMAIL']) ?></td>
                    <td class="nowrap"><?= $user['PHONE'] ? h($user['PHONE']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <span class="badge <?= $user['STATUS'] === 'ACTIVE' ? 'badge-active' : 'badge-inactive' ?>">
                            <?= h($user['STATUS']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (empty($roles)): ?>
                        <span class="muted">—</span>
                        <?php else: ?>
                        <div class="role-badge-list">
                            <?php foreach ($roles as $r): ?>
                            <span class="badge badge-role-<?= h($r['ROLE_ID']) ?>"><?= h($r['ROLE_NAME']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (empty($access)): ?>
                        <span class="muted">—</span>
                        <?php else: ?>
                        <?= h(implode(', ', $access)) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<a href="<?= APP_URL ?>/admin/users.php" class="back-link">← Return to List</a>
<?php endif; ?>

<!-- ============================================================ -->
<!-- USERS TABLE (hidden while editing or adding)                  -->
<!-- ============================================================ -->
<?php if (!$editing && !$addMode && !isset($_GET['report'])): ?>
<div class="card">
    <div class="card-title">👥 All Users (<?= count($users) ?>)</div>

    <div class="filter-bar">
        <input type="text" id="filterText" placeholder="🔍 Search name, email, phone..." oninput="applyFilters()">
        <select id="filterRole" onchange="applyFilters()">
            <option value="">All Roles</option>
            <?php foreach ($allRoles as $role): ?>
            <option value="<?= h($role['ROLE_ID']) ?>"><?= h($role['ROLE_NAME']) ?></option>
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
                    <th class="sortable" data-col="3" onclick="sortTable(3)">Status <span class="sort-icon">↕</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <?php
                    $roles     = $userRolesMap[$user['USER_ID']] ?? [];
                    $roleKeys  = implode(' ', array_column($roles, 'ROLE_ID'));
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

<?php endif; // end !$editing && !$addMode ?>

<style>
.page-header  { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; }
.page-header .page-title { margin-bottom:0; }

.form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
@media (max-width:480px) { .form-row { grid-template-columns:1fr; } }

.required { color:#c0392b; }
.optional  { color:#999; font-weight:400; font-size:0.85rem; }
.muted     { color:#aaa; }
.nowrap    { white-space:nowrap; }

.form-actions  { margin-top:0.5rem; }
.user-id-note  { font-size:0.85rem; color:#666; background:#f4f6f8; padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:1rem; }
.user-id-small { font-size:0.75rem; color:#999; margin-top:0.15rem; }
.field-hint    { font-size:0.82rem; color:#888; margin-top:0.3rem; }

/* Role / Giftee dynamic grid */
.role-grid { display:flex; flex-direction:column; gap:0.4rem; margin-top:0.3rem; margin-bottom:0.5rem; }
.role-grid-row {
    display:flex; align-items:center; gap:0.5rem;
    background:#fff; border:1px solid #e0e0e0; border-radius:8px;
    padding:0.35rem 0.5rem; border-left:4px solid #ccc;
    transition:border-color 0.15s;
}
.role-grid-row select {
    flex:0 0 190px; border:none; background:transparent; font-size:0.9rem;
    font-family:inherit; cursor:pointer; outline:none; color:#212529; padding:0.15rem 0;
}
.role-desc {
    flex:1; font-size:0.8rem; color:#888; font-style:italic;
    padding:0 0.5rem; line-height:1.35; border-left:1px solid #e0e0e0; margin-left:0.25rem;
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

/* Role row accent colors */
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

/* Back link */
.back-link { display:inline-block; font-size:0.9rem; color:#c0392b; text-decoration:none; font-weight:600; margin-bottom:0.6rem; }
.back-link:hover { text-decoration:underline; }

/* Report table */
.report-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
.report-table th { background:#f4f6f8; text-align:left; padding:0.5rem 0.75rem; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.04em; color:#666; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
.report-table td { padding:0.5rem 0.75rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.report-table tr:last-child td { border-bottom:none; }
.report-table tr.row-inactive td { opacity:0.55; }
</style>

<script>
// ── Shared data ───────────────────────────────────────────────
const ALL_ROLES           = <?= json_encode(array_values($allRoles)) ?>;
const GIFTER_ROLE_ID      = '<?= $gifterRoleId ?>';
const ROLE_DESCS          = <?= json_encode($roleDescs) ?>;
const ALL_WISHLIST_USERS  = <?= json_encode(array_values($wishlistOnlyUsers)) ?>;

const ROLE_BY_ID = {};
ALL_ROLES.forEach(r => { ROLE_BY_ID[String(r.ROLE_ID)] = r; });

// ══ ROLE GRID ════════════════════════════════════════════════

function initRoleGrid(gridKey, preselected) {
    if (!preselected || preselected.length === 0) {
        addRoleRow(gridKey, '');
    } else {
        preselected.forEach(id => addRoleRow(gridKey, String(id)));
    }
}

function addRoleRow(gridKey, selectedValue) {
    selectedValue = selectedValue !== undefined ? String(selectedValue) : '';
    const grid = document.getElementById('roleGrid_' + gridKey);
    if (!grid) return;

    const row = document.createElement('div');
    row.className = 'role-grid-row';
    if (selectedValue && ROLE_BY_ID[selectedValue]) {
        row.dataset.role = ROLE_BY_ID[selectedValue].ROLE_ID;
    }

    const sel = document.createElement('select');
    sel.name = 'roles[]';
    sel.className = 'role-select';
    const ph = document.createElement('option');
    ph.value = ''; ph.textContent = '— Select a role —';
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
        const key  = role ? role.ROLE_ID : '';
        row.dataset.role = key;
        descSpan.textContent = key && ROLE_DESCS[key] ? ROLE_DESCS[key] : '';
        refreshRoleDropdowns(gridKey);
        updateRoleBtn(gridKey);
        toggleWishlistAccess();
    });

    const descSpan = document.createElement('span');
    descSpan.className = 'role-desc';
    const initRole = ROLE_BY_ID[selectedValue];
    descSpan.textContent = initRole && ROLE_DESCS[initRole.ROLE_ID]
        ? ROLE_DESCS[initRole.ROLE_ID] : '';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button'; removeBtn.className = 'remove-role-btn';
    removeBtn.innerHTML = '&times;'; removeBtn.title = 'Remove';
    removeBtn.addEventListener('click', function () {
        row.remove();
        refreshRoleDropdowns(gridKey);
        updateRoleBtn(gridKey);
        toggleWishlistAccess();
    });

    row.appendChild(sel); row.appendChild(descSpan); row.appendChild(removeBtn);
    grid.appendChild(row);
    refreshRoleDropdowns(gridKey);
    updateRoleBtn(gridKey);
    toggleWishlistAccess();
}

function refreshRoleDropdowns(gridKey) {
    const grid = document.getElementById('roleGrid_' + gridKey);
    if (!grid) return;
    const selects = Array.from(grid.querySelectorAll('select.role-select'));
    const used = selects.map(s => s.value).filter(v => v !== '');
    selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            opt.disabled = used.includes(opt.value) && opt.value !== cur;
        });
    });
}

function updateRoleBtn(gridKey) {
    const btn  = document.getElementById('addRoleBtn_' + gridKey);
    if (!btn) return;
    const grid = document.getElementById('roleGrid_' + gridKey);
    const used = Array.from(grid.querySelectorAll('select.role-select'))
        .map(s => s.value).filter(v => v !== '');
    btn.disabled = used.length >= ALL_ROLES.length;
}

function toggleWishlistAccess() {
    const panel = document.getElementById('wishlistAccessPanel');
    if (!panel) return;
    const gifterSelected = Array.from(document.querySelectorAll('select.role-select'))
        .some(s => s.value === GIFTER_ROLE_ID);
    panel.style.display = gifterSelected ? 'block' : 'none';
}

// ══ WISHLIST ACCESS GRID ══════════════════════════════════════

function initWishlistGrid(preselected, canEditIds) {
    if (!preselected || preselected.length === 0) return;
    canEditIds = canEditIds || [];
    preselected.forEach(uid => addWishlistRow(uid, canEditIds.includes(uid)));
}

function addWishlistRow(selectedValue, canEdit) {
    selectedValue = selectedValue || '';
    canEdit = canEdit || false;
    const grid = document.getElementById('wishlistGrid');
    if (!grid) return;

    const row = document.createElement('div');
    row.className = 'role-grid-row';
    row.style.alignItems = 'center';
    row.style.gap = '0.6rem';

    const sel = document.createElement('select');
    sel.name = 'wishlist_access[]';
    sel.className = 'wishlist-select';
    const ph = document.createElement('option');
    ph.value = ''; ph.textContent = '— Select a person —';
    sel.appendChild(ph);
    ALL_WISHLIST_USERS.forEach(user => {
        const opt = document.createElement('option');
        opt.value = user.USER_ID;
        opt.textContent = user.FIRST_NAME + ' ' + user.LAST_NAME;
        if (user.USER_ID === String(selectedValue)) opt.selected = true;
        sel.appendChild(opt);
    });
    sel.addEventListener('change', function () {
        chk.value = sel.value;
        refreshWishlistDropdowns();
        updateWishlistBtn();
    });

    // Editable checkbox
    const chkLabel = document.createElement('label');
    chkLabel.style.cssText = 'display:flex;align-items:center;gap:0.3rem;font-size:0.88rem;color:var(--text-mid);white-space:nowrap;cursor:pointer;';
    const chk = document.createElement('input');
    chk.type = 'checkbox';
    chk.name = 'wishlist_can_edit[]';
    chk.value = String(selectedValue);
    chk.checked = canEdit;
    // Keep checkbox value in sync with the select
    sel.addEventListener('change', function () { chk.value = sel.value; });
    chkLabel.appendChild(chk);
    chkLabel.appendChild(document.createTextNode(' Editable'));

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button'; removeBtn.className = 'remove-role-btn';
    removeBtn.innerHTML = '&times;'; removeBtn.title = 'Remove';
    removeBtn.addEventListener('click', function () {
        row.remove();
        refreshWishlistDropdowns();
        updateWishlistBtn();
    });

    row.appendChild(sel);
    row.appendChild(chkLabel);
    row.appendChild(removeBtn);
    grid.appendChild(row);
    refreshWishlistDropdowns();
    updateWishlistBtn();
}

function refreshWishlistDropdowns() {
    const grid = document.getElementById('wishlistGrid');
    if (!grid) return;
    const selects = Array.from(grid.querySelectorAll('select.wishlist-select'));
    const used = selects.map(s => s.value).filter(v => v !== '');
    selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            opt.disabled = used.includes(opt.value) && opt.value !== cur;
        });
    });
}

function updateWishlistBtn() {
    const btn  = document.getElementById('addWishlistBtn');
    if (!btn) return;
    const grid = document.getElementById('wishlistGrid');
    const used = Array.from(grid.querySelectorAll('select.wishlist-select'))
        .map(s => s.value).filter(v => v !== '');
    btn.disabled = used.length >= ALL_WISHLIST_USERS.length;
}

// ── Auto-dismiss success alert after 5 seconds ───────────────
(function () {
    const alert = document.querySelector('.alert-success');
    if (alert) setTimeout(() => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 5000);
})();

// ── Strip blank selects before submit ─────────────────────────
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function () {
        this.querySelectorAll('select.role-select, select.wishlist-select').forEach(sel => {
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
