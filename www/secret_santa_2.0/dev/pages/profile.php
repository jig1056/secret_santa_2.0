<?php
// ============================================================
// pages/profile.php
// Allows the logged-in user to update their name, email,
// phone, and password.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$pdo    = getDB();
$userId = currentUserId();
$msg    = '';
$msgType= '';

// Load current user record
$stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// ------------------------------------------------------------
// Handle POST
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName   = trim($_POST['first_name']       ?? '');
    $lastName    = trim($_POST['last_name']        ?? '');
    $email       = trim($_POST['email']            ?? '');
    $phone       = trim($_POST['phone']            ?? '');
    $newPass     = trim($_POST['new_password']     ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');
    $currentPass = trim($_POST['current_password'] ?? '');

    // -- Validate required fields --
    if (!$firstName || !$lastName || !$email) {
        $msg     = 'First name, last name and email are required.';
        $msgType = 'error';

    // -- Check email not taken by someone else --
    } elseif ((function() use ($pdo, $email, $userId) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM SS_USERS WHERE EMAIL = ? AND USER_ID != ?");
        $chk->execute([$email, $userId]);
        return $chk->fetchColumn() > 0;
    })()) {
        $msg     = 'That email address is already in use by another account.';
        $msgType = 'error';

    // -- If changing password, validate current password first --
    } elseif ($newPass && !password_verify($currentPass, $user['PASSWORD_HASH'])) {
        $msg     = 'Your current password is incorrect.';
        $msgType = 'error';

    } elseif ($newPass && $newPass !== $confirmPass) {
        $msg     = 'New password and confirmation do not match.';
        $msgType = 'error';

    } elseif ($newPass && strlen($newPass) < 8) {
        $msg     = 'New password must be at least 8 characters.';
        $msgType = 'error';

    } else {
        // -- Save changes --
        if ($newPass) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE SS_USERS SET FIRST_NAME=?, LAST_NAME=?, EMAIL=?, PHONE=?, PASSWORD_HASH=?, UPDATED_AT=NOW() WHERE USER_ID=?")
                ->execute([$firstName, $lastName, $email, $phone ?: null, $hash, $userId]);
        } else {
            $pdo->prepare("UPDATE SS_USERS SET FIRST_NAME=?, LAST_NAME=?, EMAIL=?, PHONE=?, UPDATED_AT=NOW() WHERE USER_ID=?")
                ->execute([$firstName, $lastName, $email, $phone ?: null, $userId]);
        }

        // Update session with new name/email
        $_SESSION['FIRST_NAME'] = $firstName;
        $_SESSION['LAST_NAME']  = $lastName;
        $_SESSION['EMAIL']      = $email;

        // Reload user record
        $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $msg     = 'Your profile has been updated successfully!';
        $msgType = 'success';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">👤 My Profile</h1>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="profile-grid">

    <!-- Profile Info Form -->
    <div class="card">
        <div class="card-title">📝 Personal Information</div>
        <form method="POST" action="">

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" required maxlength="50"
                           value="<?= h($user['FIRST_NAME']) ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" required maxlength="50"
                           value="<?= h($user['LAST_NAME']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="150"
                           value="<?= h($user['EMAIL']) ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone <span class="optional">(optional)</span></label>
                    <input type="tel" id="phone" name="phone" maxlength="20"
                           placeholder="813-555-0100"
                           value="<?= h($user['PHONE'] ?? '') ?>">
                </div>
            </div>

            <div class="card-title" style="margin-top:1.5rem;">🔒 Change Password</div>
            <p class="section-note">Leave all password fields blank to keep your current password.</p>

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password"
                       placeholder="Required only if changing password"
                       autocomplete="current-password">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           minlength="8" placeholder="Min 8 characters"
                           autocomplete="new-password"
                           oninput="checkPasswordMatch()">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter new password"
                           autocomplete="new-password"
                           oninput="checkPasswordMatch()">
                    <div id="matchMsg" class="match-msg" style="display:none;"></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= APP_URL ?>/pages/home.php" class="btn btn-secondary">Cancel</a>
            </div>

        </form>
    </div>

    <!-- Account Info sidebar -->
    <div class="sidebar">
        <div class="card">
            <div class="card-title">🪪 Account Details</div>
            <div class="detail-row">
                <span class="detail-label">User ID</span>
                <code class="detail-value"><?= h($user['USER_ID']) ?></code>
            </div>
            <div class="detail-row">
                <span class="detail-label">Account Type</span>
                <span class="badge <?= $user['USER_TYPE'] === 'ADMIN' ? 'badge-admin' : 'badge-standard' ?>">
                    <?= h($user['USER_TYPE']) ?>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="badge badge-active">ACTIVE</span>
            </div>
        </div>

        <div class="card tip-card">
            <div class="card-title">💡 Tips</div>
            <ul class="tip-list">
                <li>Keep your email up to date so you can receive Secret Santa notifications.</li>
                <li>Use a strong password with at least 8 characters.</li>
                <li>Your User ID cannot be changed.</li>
            </ul>
        </div>
    </div>

</div>

<style>
.profile-grid { display: grid; grid-template-columns: 1fr 280px; gap: 1.25rem; align-items: start; }
@media (max-width: 768px) { .profile-grid { grid-template-columns: 1fr; } }

.sidebar { display: flex; flex-direction: column; gap: 1.25rem; }

.form-row    { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.required    { color: #c0392b; }
.optional    { color: #999; font-weight: 400; font-size: 0.85rem; }
.section-note{ font-size: 0.88rem; color: #888; margin-bottom: 0.75rem; }
.form-actions{ display: flex; gap: 0.75rem; margin-top: 0.75rem; flex-wrap: wrap; }

.detail-row   { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; }
.detail-row:last-child { border-bottom: none; }
.detail-label { font-size: 0.85rem; color: #888; }
.detail-value { font-size: 0.82rem; background: #f4f6f8; border: 1px solid #ddd; border-radius: 4px; padding: 0.15rem 0.45rem; font-family: monospace; color: #c0392b; }

.badge          { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 20px; }
.badge-admin    { background: #922b21; color: #fff; }
.badge-standard { background: #e8e8e8; color: #444; }
.badge-active   { background: #d4edda; color: #155724; }

.tip-card   { background: #f9fdf9; border-left: 4px solid #1e8449; }
.tip-list   { padding-left: 1.1rem; font-size: 0.88rem; color: #555; line-height: 1.8; }

.match-msg  { font-size: 0.82rem; margin-top: 0.3rem; font-weight: 600; }
.match-ok   { color: #1e8449; }
.match-fail { color: #c0392b; }
</style>

<script>
function checkPasswordMatch() {
    const np  = document.getElementById('new_password').value;
    const cp  = document.getElementById('confirm_password').value;
    const msg = document.getElementById('matchMsg');

    if (!np && !cp) { msg.style.display = 'none'; return; }

    msg.style.display = 'block';
    if (cp && np === cp) {
        msg.textContent  = '✓ Passwords match';
        msg.className    = 'match-msg match-ok';
    } else if (cp) {
        msg.textContent  = '✗ Passwords do not match';
        msg.className    = 'match-msg match-fail';
    } else {
        msg.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>