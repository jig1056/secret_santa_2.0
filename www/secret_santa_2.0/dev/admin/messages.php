<?php
// ============================================================
// admin/messages.php
// Create/edit message templates and send via email or SMS.
// Admin only.
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
// All roles (including all_roles for message targeting)
// ------------------------------------------------------------
$allRoles = $pdo->query("SELECT ROLE_ID, ROLE_KEY, ROLE_NAME FROM SS_ROLES WHERE ROLE_KEY != 'all_roles' ORDER BY SORT_ORDER ASC")->fetchAll();

// Role descriptions from SS_CONFIG (ROLE_DESC_* keys)
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
// Helper: save allowed roles for a message
// ------------------------------------------------------------
function saveMessageRoles(int $messageId, array $selectedRoleIds, PDO $pdo): void {
    $pdo->prepare("DELETE FROM SS_MESSAGE_ROLES WHERE MESSAGE_ID = ?")->execute([$messageId]);
    $ins = $pdo->prepare("INSERT IGNORE INTO SS_MESSAGE_ROLES (MESSAGE_ID, ROLE_ID) VALUES (?, ?)");
    foreach ($selectedRoleIds as $roleId) {
        $ins->execute([$messageId, (int)$roleId]);
    }
}

// ------------------------------------------------------------
// Build a map of USER_ID => [role_keys] for all active users
// Used by the send logic for role-based filtering
// ------------------------------------------------------------
function loadAllUserRoles(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT ur.USER_ID, r.ROLE_KEY
        FROM SS_USER_ROLES ur
        JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
        JOIN SS_USERS u ON u.USER_ID = ur.USER_ID
        WHERE u.STATUS = 'ACTIVE'
    ");
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['USER_ID']][] = $row['ROLE_KEY'];
    }
    return $map;
}

// ------------------------------------------------------------
// Handle POST actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- ADD template --
    if ($action === 'add') {
        $name            = trim($_POST['message_name'] ?? '');
        $body            = trim($_POST['message_body'] ?? '');
        $selectedRoleIds = array_map('intval', (array)($_POST['allowed_roles'] ?? []));

        if (!$name || !$body) {
            $msg     = 'Message name and body are required.';
            $msgType = 'error';
            $addMode = true;
        } elseif (empty($selectedRoleIds)) {
            $msg     = 'Please select at least one allowed role.';
            $msgType = 'error';
            $addMode = true;
        } else {
            $pdo->prepare("INSERT INTO SS_MESSAGES (MESSAGE_NAME, MESSAGE_BODY) VALUES (?, ?)")
                ->execute([$name, $body]);
            $newId = (int)$pdo->lastInsertId();
            saveMessageRoles($newId, $selectedRoleIds, $pdo);
            $msg     = "Message template \"{$name}\" created.";
            $msgType = 'success';
            $addMode = false;
        }

    // -- UPDATE template --
    } elseif ($action === 'update') {
        $messageId       = (int)($_POST['message_id']   ?? 0);
        $name            = trim($_POST['message_name']  ?? '');
        $body            = trim($_POST['message_body']  ?? '');
        $selectedRoleIds = array_map('intval', (array)($_POST['allowed_roles'] ?? []));

        if (!$name || !$body) {
            $msg     = 'Message name and body are required.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$messageId]);
            $editing = $stmt->fetch() ?: null;
        } elseif (empty($selectedRoleIds)) {
            $msg     = 'Please select at least one allowed role.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$messageId]);
            $editing = $stmt->fetch() ?: null;
        } else {
            $pdo->prepare("UPDATE SS_MESSAGES SET MESSAGE_NAME=?,MESSAGE_BODY=?,UPDATED_AT=NOW() WHERE MESSAGE_ID=?")
                ->execute([$name, $body, $messageId]);
            saveMessageRoles($messageId, $selectedRoleIds, $pdo);
            $msg     = 'Message template updated.';
            $msgType = 'success';
            // Reload so the edit form stays open after save
            $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$messageId]);
            $editing = $stmt->fetch() ?: null;
        }

    // -- DELETE template --
    } elseif ($action === 'delete') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $row = $pdo->prepare("SELECT MESSAGE_NAME FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
        $row->execute([$messageId]);
        $name = $row->fetchColumn();
        $pdo->prepare("DELETE FROM SS_MESSAGES WHERE MESSAGE_ID = ?")->execute([$messageId]);
        $msg     = "Message template \"{$name}\" deleted.";
        $msgType = 'success';

    // -- CLEAR log --
    } elseif ($action === 'clear_log') {
        $pdo->exec("DELETE FROM SS_MESSAGE_LOG");
        $msg     = 'Send log cleared.';
        $msgType = 'success';

    // -- SEND --
    } elseif ($action === 'send') {
        $messageId     = (int)($_POST['message_id'] ?? 0);
        $channel       = $_POST['channel']           ?? 'EMAIL';
        $targetType    = $_POST['target_type']        ?? 'all';
        $targetUserIds = (array)($_POST['target_users'] ?? []);

        // Load template
        $tplStmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
        $tplStmt->execute([$messageId]);
        $template = $tplStmt->fetch();

        if (!$template) {
            $msg     = 'Message template not found.';
            $msgType = 'error';
        } elseif ($targetType === 'individual' && empty(array_filter($targetUserIds))) {
            $msg     = 'Please select at least one user.';
            $msgType = 'error';
            $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$messageId]);
            $editing = $stmt->fetch() ?: null;
        } else {
            // ---- Get message's allowed role IDs ----
            $mrStmt = $pdo->prepare("SELECT ROLE_ID FROM SS_MESSAGE_ROLES WHERE MESSAGE_ID = ?");
            $mrStmt->execute([$messageId]);
            $allowedRoleIds = $mrStmt->fetchAll(PDO::FETCH_COLUMN);

            // ---- Build recipient pool (keyed array = no duplicates) ----
            $recipientIds = [];

            if ($targetType === 'all') {
                // Everyone with an allowed role
                if (!empty($allowedRoleIds)) {
                    $ph    = implode(',', array_fill(0, count($allowedRoleIds), '?'));
                    $rStmt = $pdo->prepare("
                        SELECT DISTINCT u.USER_ID FROM SS_USERS u
                        JOIN SS_USER_ROLES ur ON ur.USER_ID = u.USER_ID
                        WHERE ur.ROLE_ID IN ({$ph}) AND u.STATUS = 'ACTIVE'
                    ");
                    $rStmt->execute($allowedRoleIds);
                    foreach ($rStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                        $recipientIds[$uid] = true;
                    }
                }
            } else {
                // Individually selected (UI only shows eligible users, so no secondary filter needed)
                foreach ($targetUserIds as $uid) {
                    if ($uid) $recipientIds[$uid] = true;
                }
            }

            if (empty($recipientIds)) {
                $msg     = 'No eligible recipients found. Make sure this message has Eligible Roles assigned.';
                $msgType = 'error';
                $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
                $stmt->execute([$messageId]);
                $editing = $stmt->fetch() ?: null;
            } else {
                // ---- Fetch full user records ----
                $uidList = array_keys($recipientIds);
                $placeholders = implode(',', array_fill(0, count($uidList), '?'));
                $uStmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID IN ({$placeholders})");
                $uStmt->execute($uidList);
                $recipients = $uStmt->fetchAll();

                $sent   = 0;
                $failed = 0;
                $xmasYear = getConfig('XMAS_YEAR', date('Y'));

                foreach ($recipients as $recipient) {
                    $body = str_replace(
                        ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}', '{GIFT_DEADLINE}', '{SANTA_MATCH_DATE}'],
                        [$recipient['FIRST_NAME'], $recipient['LAST_NAME'], $xmasYear, getConfig('GIFT_DEADLINE', 'TBD'), getConfig('SANTA_MATCH_DATE', 'TBD')],
                        $template['MESSAGE_BODY']
                    );
                    $toName = $recipient['FIRST_NAME'] . ' ' . $recipient['LAST_NAME'];
                    $status = 'SENT';

                    if ($channel === 'EMAIL' || $channel === 'BOTH') {
                        $subject    = getConfig('MAIL_SUBJECT', 'Secret Santa') . ' ' . $xmasYear;
                        $mailResult = sendMail($recipient['EMAIL'], $toName, $subject, $body);
                        if ($mailResult !== true) {
                            error_log("Mail failed to {$recipient['EMAIL']}: {$mailResult}");
                            $status = 'FAILED';
                            $failed++;
                        }
                    }

                    if ($channel === 'SMS' || $channel === 'BOTH') {
                        if (!empty($recipient['PHONE'])) {
                            $smsResult = sendSMS($recipient['PHONE'], $body);
                            if ($smsResult !== true) {
                                error_log("SMS failed to {$recipient['PHONE']}: {$smsResult}");
                                $status = 'FAILED';
                                $failed++;
                            }
                        }
                    }

                    $pdo->prepare("INSERT INTO SS_MESSAGE_LOG (MESSAGE_ID, USER_ID, CHANNEL, STATUS, XMAS_YEAR, SENT_AT) VALUES (?, ?, ?, ?, ?, NOW())")
                        ->execute([$messageId, $recipient['USER_ID'], $channel, $status, $xmasYear]);
                    $sent++;
                }

                $succeeded = $sent - $failed;
                if ($failed > 0) {
                    $msg     = "⚠️ Sent to {$succeeded} of {$sent} recipients. {$failed} failed — check the send log.";
                    $msgType = 'error';
                } else {
                    $msg     = "✅ Message sent to {$sent} recipient" . ($sent !== 1 ? 's' : '') . " via {$channel}.";
                    $msgType = 'success';
                }

                // Keep send panel open
                $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
                $stmt->execute([$messageId]);
                $editing = $stmt->fetch() ?: null;
            }
        }
    }
}

// Load edit target from GET
if (!$editing && isset($_GET['edit']) && $msgType !== 'success') {
    $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// True when arriving directly from the template list via the Send button
$showSend = isset($_GET['show_send']);

// Current allowed roles for the message being edited
$editingAllowedRoleIds = [];
if ($editing) {
    $stmt = $pdo->prepare("SELECT ROLE_ID FROM SS_MESSAGE_ROLES WHERE MESSAGE_ID = ?");
    $stmt->execute([$editing['MESSAGE_ID']]);
    $editingAllowedRoleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// All templates with their allowed roles
$templates = $pdo->query("SELECT * FROM SS_MESSAGES ORDER BY MESSAGE_NAME ASC")->fetchAll();

// Allowed roles per template (for table display)
$templateRolesMap = [];
$trStmt = $pdo->query("
    SELECT mr.MESSAGE_ID, r.ROLE_KEY, r.ROLE_NAME
    FROM SS_MESSAGE_ROLES mr
    JOIN SS_ROLES r ON r.ROLE_ID = mr.ROLE_ID
    ORDER BY r.SORT_ORDER ASC
");
foreach ($trStmt->fetchAll() as $row) {
    $templateRolesMap[$row['MESSAGE_ID']][] = $row;
}

// Eligible users for individual targeting — only those with this message's allowed roles
$eligibleUsers = [];
if ($editing && !empty($editingAllowedRoleIds)) {
    $ph    = implode(',', array_fill(0, count($editingAllowedRoleIds), '?'));
    $euStmt = $pdo->prepare("
        SELECT DISTINCT u.USER_ID, u.FIRST_NAME, u.LAST_NAME
        FROM SS_USERS u
        JOIN SS_USER_ROLES ur ON ur.USER_ID = u.USER_ID
        WHERE ur.ROLE_ID IN ({$ph}) AND u.STATUS = 'ACTIVE'
        ORDER BY u.FIRST_NAME ASC, u.LAST_NAME ASC
    ");
    $euStmt->execute($editingAllowedRoleIds);
    $eligibleUsers = $euStmt->fetchAll();
}

// Send log — no limit, fetch all
$logStmt = $pdo->query("
    SELECT l.*, m.MESSAGE_NAME, u.FIRST_NAME, u.LAST_NAME
    FROM SS_MESSAGE_LOG l
    JOIN SS_MESSAGES m ON m.MESSAGE_ID = l.MESSAGE_ID
    JOIN SS_USERS u ON u.USER_ID = l.USER_ID
    ORDER BY l.SENT_AT DESC
");
$logs = $logStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">✉️ Message Center</h1>
    <a href="?add=1" class="btn btn-primary">➕ New Template</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- ADD Template Form                                             -->
<!-- ============================================================ -->
<?php if ($addMode && !$editing): ?>
<div class="card">
    <div class="card-title">➕ New Message Template</div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="message_name">Template Name <span class="required">*</span></label>
            <input type="text" id="message_name" name="message_name" required maxlength="150"
                   placeholder="e.g. Welcome Message"
                   value="<?= h($_POST['message_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="message_body">Message Body <span class="required">*</span></label>
            <textarea id="message_body" name="message_body" required rows="5"
                      placeholder="Use {FIRST_NAME}, {LAST_NAME}, {YEAR} as placeholders."><?= h($_POST['message_body'] ?? '') ?></textarea>
            <div class="field-hint">Placeholders: <code>{FIRST_NAME}</code> <code>{LAST_NAME}</code> <code>{YEAR}</code> <code>{GIFT_DEADLINE}</code> <code>{SANTA_MATCH_DATE}</code> <code>{PASSWORD_RESET_LINK}</code> <code>{RESET_EXPIRY_MINS}</code></div>
        </div>
        <div class="form-group">
            <label>Eligible Roles <span class="required">*</span></label>
            <div class="field-hint" style="margin-bottom:0.5rem;">This message can only be sent to users who have one of these roles.</div>
            <div class="role-grid" id="roleGrid_add"></div>
            <button type="button" class="btn btn-sm btn-add-role" id="addRoleBtn_add"
                    onclick="addAllowedRoleRow('add')">+ Add Role</button>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Template</button>
            <a href="<?= APP_URL ?>/admin/messages.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initAllowedRoleGrid('add', <?= json_encode(array_map('intval', (array)($_POST['allowed_roles'] ?? []))) ?>);
});
</script>
<?php endif; ?>

<!-- ============================================================ -->
<!-- EDIT Template + SEND Panel                                    -->
<!-- ============================================================ -->
<?php if ($editing): ?>
<div class="card" id="editFormCard">
    <div class="card-title">✏️ Edit Template: <em><?= h($editing['MESSAGE_NAME']) ?></em></div>
    <form method="POST" action="">
        <input type="hidden" name="action"     value="update">
        <input type="hidden" name="message_id" value="<?= $editing['MESSAGE_ID'] ?>">
        <div class="form-group">
            <label for="message_name">Template Name <span class="required">*</span></label>
            <input type="text" id="message_name" name="message_name" required maxlength="150"
                   value="<?= h($editing['MESSAGE_NAME']) ?>">
        </div>
        <div class="form-group">
            <label for="message_body">Message Body <span class="required">*</span></label>
            <textarea id="message_body" name="message_body" required rows="5"><?= h($editing['MESSAGE_BODY']) ?></textarea>
            <div class="field-hint">Placeholders: <code>{FIRST_NAME}</code> <code>{LAST_NAME}</code> <code>{YEAR}</code> <code>{GIFT_DEADLINE}</code> <code>{SANTA_MATCH_DATE}</code> <code>{PASSWORD_RESET_LINK}</code> <code>{RESET_EXPIRY_MINS}</code></div>
        </div>
        <div class="form-group">
            <label>Eligible Roles <span class="required">*</span></label>
            <div class="field-hint" style="margin-bottom:0.5rem;">This message can only be sent to users who have one of these roles.</div>
            <div class="role-grid" id="roleGrid_edit"></div>
            <button type="button" class="btn btn-sm btn-add-role" id="addRoleBtn_edit"
                    onclick="addAllowedRoleRow('edit')">+ Add Role</button>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Delete this message template?')) document.getElementById('delMsg<?= $editing['MESSAGE_ID'] ?>').submit()">
                Delete
            </button>
            <button type="button" class="btn btn-secondary" id="showSendBtn" onclick="toggleSendPanel()">
                📤 Show Send Message
            </button>
            <a href="<?= APP_URL ?>/admin/messages.php" class="btn btn-secondary">↩ Return to List</a>
        </div>
    </form>
    <form id="delMsg<?= $editing['MESSAGE_ID'] ?>" method="POST" action="" style="display:none;">
        <input type="hidden" name="action"     value="delete">
        <input type="hidden" name="message_id" value="<?= $editing['MESSAGE_ID'] ?>">
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initAllowedRoleGrid('edit', <?= json_encode($editingAllowedRoleIds) ?>);
});
</script>

<!-- SEND Panel -->
<?php
// Get this message's allowed roles for display in the send panel
$editingAllowedRoles = $templateRolesMap[$editing['MESSAGE_ID']] ?? [];
$editingHasAllRoles  = !empty(array_filter($editingAllowedRoles, fn($r) => $r['ROLE_KEY'] === 'all_roles'));
?>
<div class="card send-card" id="sendPanel" style="display:none;">
    <div class="card-title">📤 Send This Message</div>

    <?php if (!empty($editingAllowedRoles)): ?>
    <div class="allowed-roles-notice">
        <strong>Eligible roles:</strong>
        <?php foreach ($editingAllowedRoles as $r): ?>
        <span class="badge badge-role-<?= h($r['ROLE_KEY']) ?>"><?= h($r['ROLE_NAME']) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="action"     value="send">
        <input type="hidden" name="message_id" value="<?= $editing['MESSAGE_ID'] ?>">

        <!-- Send To -->
        <div class="form-group" style="max-width:260px;">
            <label for="target_type">Send To <span class="required">*</span></label>
            <select id="target_type" name="target_type" onchange="updateTargetUI()">
                <option value="all">All eligible users</option>
                <option value="individual">Select individuals</option>
            </select>
            <div id="individualPanel" style="display:none; margin-top:0.75rem;">
                <?php if (empty($eligibleUsers)): ?>
                <p class="muted" style="font-size:0.9rem;">No active users have the allowed roles for this message.</p>
                <?php else: ?>
                <select name="target_users[]" multiple size="<?= min(8, count($eligibleUsers)) ?>" class="user-multiselect">
                    <?php foreach ($eligibleUsers as $u): ?>
                    <option value="<?= h($u['USER_ID']) ?>">
                        <?= h($u['FIRST_NAME']) ?> <?= h($u['LAST_NAME']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint">Hold Ctrl / Cmd to select multiple. Only users with this message's allowed roles are listed.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Channel + Preview row -->
        <div class="send-bottom-row">
            <div class="form-group" style="max-width:200px;">
                <label for="channel">Channel <span class="required">*</span></label>
                <select id="channel" name="channel">
                    <option value="EMAIL">Email</option>
                    <option value="SMS">SMS</option>
                    <option value="BOTH">Both</option>
                </select>
            </div>
        </div>

        <!-- Preview -->
        <div class="preview-box">
            <div class="preview-label">Preview <span class="preview-note">(shown with placeholder values)</span></div>
            <div class="preview-body"><?= nl2br(h(str_replace(
                ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}', '{GIFT_DEADLINE}', '{SANTA_MATCH_DATE}'],
                ['Chanda', 'Williams', getConfig('XMAS_YEAR', date('Y')), getConfig('GIFT_DEADLINE', 'TBD'), getConfig('SANTA_MATCH_DATE', 'TBD')],
                $editing['MESSAGE_BODY']
            ))) ?></div>
        </div>

        <div class="form-actions" style="margin-top:1rem;">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Send this message now?')">
                📤 Send Message
            </button>
            <?php if ($showSend): ?>
            <a href="<?= APP_URL ?>/admin/messages.php" class="btn btn-secondary">↩ Return to List</a>
            <?php else: ?>
            <button type="button" class="btn btn-secondary" onclick="toggleSendPanel()">↩ Return to Edit</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!$editing): ?>
<!-- ============================================================ -->
<!-- Templates Table                                               -->
<!-- ============================================================ -->
<div class="card">
    <div class="card-title">📋 Message Templates (<?= count($templates) ?>)</div>
    <?php if (empty($templates)): ?>
    <div class="empty-state">No templates yet. Click "➕ New Template" to create one.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Template Name</th>
                    <th>Eligible Roles</th>
                    <th>Preview</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $tpl): ?>
                <?php $tplRoles = $templateRolesMap[$tpl['MESSAGE_ID']] ?? []; ?>
                <tr class="<?= $editing && $editing['MESSAGE_ID'] == $tpl['MESSAGE_ID'] ? 'row-active' : '' ?>">
                    <td>
                        <a href="?edit=<?= $tpl['MESSAGE_ID'] ?>" class="name-link">
                            <?= h($tpl['MESSAGE_NAME']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if (empty($tplRoles)): ?>
                        <span class="muted">—</span>
                        <?php else: ?>
                        <div class="role-badge-list">
                            <?php foreach ($tplRoles as $r): ?>
                            <span class="badge badge-role-<?= h($r['ROLE_KEY']) ?>"><?= h($r['ROLE_NAME']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="preview-col"><?= h(mb_substr($tpl['MESSAGE_BODY'], 0, 80)) ?>...</td>
                    <td class="nowrap date-col"><?= date('M j, Y g:ia', strtotime($tpl['UPDATED_AT'])) ?></td>
                    <td class="nowrap">
                        <a href="?edit=<?= $tpl['MESSAGE_ID'] ?>&show_send=1" class="btn btn-success btn-sm">
                            📤 Send
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- ============================================================ -->
<!-- Send Log Toggle                                               -->
<!-- ============================================================ -->
<?php if (!$editing): ?>
<div id="logToggleRow" style="margin-bottom:1.25rem;">
    <button type="button" class="btn btn-secondary" onclick="showLog()">
        📜 Show Send Log<?php if (!empty($logs)): ?> (<?= count($logs) ?>)<?php endif; ?>
    </button>
</div>

<!-- ============================================================ -->
<!-- Send Log                                                      -->
<!-- ============================================================ -->
<div class="card" id="logCard" style="display:none;">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">📜 Send Log (<?= count($logs) ?>)</div>
        <div style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap;">
            <input type="text" id="logSearch" placeholder="🔍 Search log..." oninput="filterLog()" class="dash-search">
            <button type="button" class="btn btn-secondary btn-sm" onclick="hideLog()">✖ Hide</button>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="if(confirm('Clear the entire send log? This cannot be undone.')) document.getElementById('frmClearLog').submit()">
                🗑️ Clear Log
            </button>
        </div>
    </div>
    <form id="frmClearLog" method="POST" action="" style="display:none;">
        <input type="hidden" name="action" value="clear_log">
    </form>
    <?php if (empty($logs)): ?>
    <div class="empty-state" style="margin-top:1rem;">No messages have been sent yet.</div>
    <?php else: ?>
    <div class="table-wrap" style="margin-top:1rem;">
        <table>
            <thead>
                <tr>
                    <th>Sent At</th>
                    <th>Year</th>
                    <th>Template</th>
                    <th>Recipient</th>
                    <th>Channel</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="log-row"
                    data-search="<?= strtolower(h($log['MESSAGE_NAME'] . ' ' . $log['FIRST_NAME'] . ' ' . $log['LAST_NAME'] . ' ' . $log['CHANNEL'] . ' ' . $log['STATUS'] . ' ' . ($log['XMAS_YEAR'] ?? ''))) ?>">
                    <td class="nowrap date-col"><?= date('M j, Y g:ia', strtotime($log['SENT_AT'])) ?></td>
                    <td class="year-col"><?= $log['XMAS_YEAR'] ? h($log['XMAS_YEAR']) : '<span class="muted">—</span>' ?></td>
                    <td><?= h($log['MESSAGE_NAME']) ?></td>
                    <td><?= h($log['FIRST_NAME']) ?> <?= h($log['LAST_NAME']) ?></td>
                    <td><span class="badge badge-channel"><?= h($log['CHANNEL']) ?></span></td>
                    <td><span class="badge <?= $log['STATUS'] === 'SENT' ? 'badge-active' : 'badge-inactive' ?>"><?= h($log['STATUS']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination-row" id="logPaginationRow" style="display:none;">
        <button class="btn btn-secondary btn-sm" id="logPrevBtn" onclick="changeLogPage(-1)">← Prev</button>
        <span class="page-info" id="logPageInfo"></span>
        <button class="btn btn-secondary btn-sm" id="logNextBtn" onclick="changeLogPage(1)">Next →</button>
        <button class="btn btn-secondary btn-sm" id="logViewAllBtn" onclick="toggleLogViewAll()">View All</button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.page-header     { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; }
.page-header .page-title { margin-bottom:0; }
.card-header-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.5rem; }

.required { color:#c0392b; }
.optional { color:#999; font-weight:400; font-size:0.85rem; }
.muted    { color:#aaa; }
.nowrap   { white-space:nowrap; }

.form-actions { display:flex; gap:0.75rem; margin-top:0.5rem; flex-wrap:wrap; align-items:center; }
.field-hint   { font-size:0.8rem; color:#999; margin-top:0.3rem; }
.field-hint code { background:#f4f6f8; padding:0.1rem 0.35rem; border-radius:4px; font-size:0.82rem; color:#c0392b; }

/* Role dynamic grid */
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
.btn-add-role:hover  { background:#e8f0fb; border-color:#5b9bd5; color:#1a5276; }
.btn-add-role:disabled { opacity:0.45; cursor:not-allowed; }

/* Role row accent colors */
.role-grid-row[data-role="all_roles"]       { border-left-color:#888; }
.role-grid-row[data-role="admin"]           { border-left-color:#922b21; }
.role-grid-row[data-role="secret_santa"]    { border-left-color:#1a5276; }
.role-grid-row[data-role="wishlist_only"]   { border-left-color:#6c3483; }
.role-grid-row[data-role="wishlist_gifter"] { border-left-color:#1e8449; }

/* Send panel */
.send-card { border-left:4px solid #1e8449; }

.allowed-roles-notice {
    background:#f0faf4;
    border:1px solid #b2dfdb;
    border-radius:6px;
    padding:0.6rem 0.9rem;
    font-size:0.88rem;
    margin-bottom:1rem;
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:0.4rem;
}
.notice-hint { color:#666; font-style:italic; }

.user-multiselect {
    width:100%;
    border:1px solid #ccc;
    border-radius:8px;
    padding:0.35rem;
    font-size:0.9rem;
}

.send-bottom-row { margin-bottom:0.75rem; }

/* Preview */
.preview-box   { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:8px; padding:1rem; margin-top:0.5rem; }
.preview-label { font-size:0.82rem; font-weight:700; color:#888; margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:0.04em; }
.preview-note  { font-weight:400; text-transform:none; font-style:italic; }
.preview-body  { font-size:0.95rem; color:#333; line-height:1.6; }

/* Table */
.name-link   { font-weight:600; color:#c0392b; text-decoration:none; }
.name-link:hover { text-decoration:underline; }
.preview-col { font-size:0.85rem; color:#777; max-width:260px; }
.date-col    { font-size:0.82rem; color:#999; white-space:nowrap; }
.empty-state { color:#999; padding:1rem 0; }
.row-active td { background:#fff8f0; }

/* Badges */
.badge          { display:inline-block; font-size:0.72rem; font-weight:700; padding:0.18rem 0.5rem; border-radius:20px; white-space:nowrap; }
.badge-channel  { background:#dce8f8; color:#1a5276; }
.badge-active   { background:#d4edda; color:#155724; }
.badge-inactive { background:#f8d7da; color:#721c24; }

.role-badge-list { display:flex; flex-wrap:wrap; gap:0.3rem; }
.badge-role-all_roles       { background:#888; color:#fff; font-style:italic; }
.badge-role-admin           { background:#922b21; color:#fff; }
.badge-role-secret_santa    { background:#1a5276; color:#fff; }
.badge-role-wishlist_only   { background:#6c3483; color:#fff; }
.badge-role-wishlist_gifter { background:#1e8449; color:#fff; }

.btn-success { background:#1e8449; color:#fff; }
.btn-danger  { background:#c0392b; color:#fff; }
.btn-sm      { padding:0.3rem 0.7rem; font-size:0.85rem; }

/* Log search + pagination */
.dash-search    { padding:0.4rem 0.75rem; border:1px solid #ccc; border-radius:8px; font-size:0.9rem; min-width:180px; }
.year-col       { font-size:0.85rem; color:#555; white-space:nowrap; }
.pagination-row { display:flex; align-items:center; gap:0.6rem; padding:0.75rem 0 0.25rem; flex-wrap:wrap; }
.page-info      { font-size:0.88rem; color:#666; min-width:100px; text-align:center; }
</style>

<script>
// ── Shared role data ─────────────────────────────────────────
const ALL_ROLES  = <?= json_encode(array_values($allRoles)) ?>;
const ROLE_DESCS = <?= json_encode($roleDescs) ?>;
const ROLE_BY_ID = {};
ALL_ROLES.forEach(r => { ROLE_BY_ID[String(r.ROLE_ID)] = r; });

// ── Allowed Role grid ────────────────────────────────────────
function initAllowedRoleGrid(gridKey, preselected) {
    if (!preselected || preselected.length === 0) {
        addAllowedRoleRow(gridKey, '');
    } else {
        preselected.forEach(id => addAllowedRoleRow(gridKey, String(id)));
    }
}

function addAllowedRoleRow(gridKey, selectedValue) {
    selectedValue = selectedValue !== undefined ? String(selectedValue) : '';
    const grid = document.getElementById('roleGrid_' + gridKey);
    if (!grid) return;

    const row = document.createElement('div');
    row.className = 'role-grid-row';
    if (selectedValue && ROLE_BY_ID[selectedValue]) {
        row.dataset.role = ROLE_BY_ID[selectedValue].ROLE_KEY;
    }

    const sel = document.createElement('select');
    sel.name = 'allowed_roles[]';
    sel.className = 'allowed-role-select';
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
        const key  = role ? role.ROLE_KEY : '';
        row.dataset.role = key;
        descSpan.textContent = key && ROLE_DESCS[key] ? ROLE_DESCS[key] : '';
        refreshAllowedRoleDropdowns(gridKey);
        updateAllowedRoleBtn(gridKey);
    });

    const descSpan = document.createElement('span');
    descSpan.className = 'role-desc';
    const initRole = ROLE_BY_ID[selectedValue];
    descSpan.textContent = initRole && ROLE_DESCS[initRole.ROLE_KEY]
        ? ROLE_DESCS[initRole.ROLE_KEY] : '';

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button'; removeBtn.className = 'remove-role-btn';
    removeBtn.innerHTML = '&times;'; removeBtn.title = 'Remove';
    removeBtn.addEventListener('click', function () {
        row.remove();
        refreshAllowedRoleDropdowns(gridKey);
        updateAllowedRoleBtn(gridKey);
    });

    row.appendChild(sel); row.appendChild(descSpan); row.appendChild(removeBtn);
    grid.appendChild(row);
    refreshAllowedRoleDropdowns(gridKey);
    updateAllowedRoleBtn(gridKey);
}

function refreshAllowedRoleDropdowns(gridKey) {
    const grid = document.getElementById('roleGrid_' + gridKey);
    if (!grid) return;
    const selects = Array.from(grid.querySelectorAll('select.allowed-role-select'));
    const used = selects.map(s => s.value).filter(v => v !== '');
    selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return;
            opt.disabled = used.includes(opt.value) && opt.value !== cur;
        });
    });
}

function updateAllowedRoleBtn(gridKey) {
    const btn  = document.getElementById('addRoleBtn_' + gridKey);
    if (!btn) return;
    const grid = document.getElementById('roleGrid_' + gridKey);
    const used = Array.from(grid.querySelectorAll('select.allowed-role-select'))
        .map(s => s.value).filter(v => v !== '');
    btn.disabled = used.length >= ALL_ROLES.length;
}

// Strip blank selects before submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function () {
        this.querySelectorAll('select.allowed-role-select').forEach(sel => {
            if (!sel.value) sel.disabled = true;
        });
    });
});

// Auto-open send panel when arriving via the Send button in the list
<?php if ($showSend): ?>
document.addEventListener('DOMContentLoaded', function () { toggleSendPanel(); });
<?php endif; ?>

// ---- Send targeting UI ----
function updateTargetUI() {
    const type  = document.getElementById('target_type')?.value;
    const panel = document.getElementById('individualPanel');
    if (panel) panel.style.display = type === 'individual' ? '' : 'none';
}

// ---- Send panel toggle ----
function toggleSendPanel() {
    const panel    = document.getElementById('sendPanel');
    const editCard = document.getElementById('editFormCard');
    const btn      = document.getElementById('showSendBtn');
    const visible  = panel.style.display !== 'none';
    panel.style.display    = visible ? 'none'  : 'block';
    editCard.style.display = visible ? 'block' : 'none';
    btn.textContent = visible ? '📤 Show Send Message' : '📤 Hide Send Message';
}

// ---- Log show/hide ----
function showLog() {
    document.getElementById('logToggleRow').style.display = 'none';
    document.getElementById('logCard').style.display = 'block';
    renderLog();
}
function hideLog() {
    document.getElementById('logToggleRow').style.display = '';
    document.getElementById('logCard').style.display = 'none';
}

// ---- Log pagination + search ----
const LOG_PAGE_SIZE = 25;
let logPage    = 1;
let logViewAll = false;

function getLogRows() {
    return Array.from(document.querySelectorAll('.log-row'));
}

function getFilteredLogRows() {
    const q = (document.getElementById('logSearch')?.value || '').toLowerCase().trim();
    return getLogRows().filter(row => !q || row.dataset.search.includes(q));
}

function renderLog() {
    const filtered   = getFilteredLogRows();
    const total      = filtered.length;
    const totalPages = Math.max(1, Math.ceil(total / LOG_PAGE_SIZE));
    logPage          = Math.max(1, Math.min(logPage, totalPages));

    getLogRows().forEach(r => r.style.display = 'none');
    const showRows = logViewAll
        ? filtered
        : filtered.slice((logPage - 1) * LOG_PAGE_SIZE, logPage * LOG_PAGE_SIZE);
    showRows.forEach(r => r.style.display = '');

    const paginationRow = document.getElementById('logPaginationRow');
    const pageInfo      = document.getElementById('logPageInfo');
    const prevBtn       = document.getElementById('logPrevBtn');
    const nextBtn       = document.getElementById('logNextBtn');
    const viewAllBtn    = document.getElementById('logViewAllBtn');
    if (!paginationRow) return;

    if (total > LOG_PAGE_SIZE || logViewAll) {
        paginationRow.style.display = '';
        if (logViewAll) {
            pageInfo.textContent   = 'Showing all ' + total;
            prevBtn.style.display  = 'none';
            nextBtn.style.display  = 'none';
            viewAllBtn.textContent = '← Paginate';
        } else {
            const start = Math.min((logPage - 1) * LOG_PAGE_SIZE + 1, total);
            const end   = Math.min(logPage * LOG_PAGE_SIZE, total);
            pageInfo.textContent   = total ? start + '–' + end + ' of ' + total : 'No results';
            prevBtn.style.display  = '';
            nextBtn.style.display  = '';
            prevBtn.disabled       = logPage <= 1;
            nextBtn.disabled       = logPage >= totalPages;
            viewAllBtn.textContent = 'View All';
        }
    } else {
        paginationRow.style.display = 'none';
    }
}

function filterLog()      { logPage = 1; renderLog(); }
function changeLogPage(d) { logPage += d; renderLog(); }
function toggleLogViewAll() { logViewAll = !logViewAll; logPage = 1; renderLog(); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
