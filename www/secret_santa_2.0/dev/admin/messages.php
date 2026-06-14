<?php
// ============================================================
// admin/messages.php
// Create/edit message templates and send via email or SMS.
// Admin only.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo     = getDB();
$msg     = '';
$msgType = '';
$editing = null;
$addMode = isset($_GET['add']);

// ------------------------------------------------------------
// Handle POST actions
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- ADD message template --
    if ($action === 'add') {
        $name = trim($_POST['message_name'] ?? '');
        $body = trim($_POST['message_body'] ?? '');
        if (!$name || !$body) {
            $msg = 'Message name and body are required.';
            $msgType = 'error';
            $addMode = true;
        } else {
            $pdo->prepare("INSERT INTO SS_MESSAGES (MESSAGE_NAME, MESSAGE_BODY) VALUES (?, ?)")
                ->execute([$name, $body]);
            $msg = "Message template \"{$name}\" created.";
            $msgType = 'success';
        }

    // -- UPDATE message template --
    } elseif ($action === 'update') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $name      = trim($_POST['message_name'] ?? '');
        $body      = trim($_POST['message_body'] ?? '');
        if (!$name || !$body) {
            $msg = 'Message name and body are required.';
            $msgType = 'error';
        } else {
            $pdo->prepare("UPDATE SS_MESSAGES SET MESSAGE_NAME = ?, MESSAGE_BODY = ?, UPDATED_AT = NOW() WHERE MESSAGE_ID = ?")
                ->execute([$name, $body, $messageId]);
            $msg = 'Message template updated.';
            $msgType = 'success';
        }
        $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
        $stmt->execute([$messageId]);
        $editing = $stmt->fetch() ?: null;

    // -- DELETE message template --
    } elseif ($action === 'delete') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $row = $pdo->prepare("SELECT MESSAGE_NAME FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
        $row->execute([$messageId]);
        $name = $row->fetchColumn();
        $pdo->prepare("DELETE FROM SS_MESSAGES WHERE MESSAGE_ID = ?")->execute([$messageId]);
        $msg = "Message template \"{$name}\" deleted.";
        $msgType = 'success';

    // -- SEND message --
    } elseif ($action === 'send') {
        $messageId  = (int)($_POST['message_id']  ?? 0);
        $sendTo     = $_POST['send_to']            ?? 'all';
        $userId     = $_POST['user_id']            ?? '';
        $channel    = $_POST['channel']            ?? 'EMAIL';

        // Load the message template
        $tplStmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
        $tplStmt->execute([$messageId]);
        $template = $tplStmt->fetch();

        if (!$template) {
            $msg = 'Message template not found.';
            $msgType = 'error';
        } else {
            // Get recipients
            if ($sendTo === 'all') {
                $recipients = $pdo->query("SELECT * FROM SS_USERS WHERE STATUS = 'ACTIVE'")->fetchAll();
            } else {
                $stmt = $pdo->prepare("SELECT * FROM SS_USERS WHERE USER_ID = ? AND STATUS = 'ACTIVE'");
                $stmt->execute([$userId]);
                $recipients = $stmt->fetchAll();
            }

            $sent = 0;
            $logStmt = $pdo->prepare("INSERT INTO SS_MESSAGE_LOG (MESSAGE_ID, USER_ID, CHANNEL, STATUS, SENT_AT) VALUES (?, ?, ?, 'SENT', NOW())");

            foreach ($recipients as $recipient) {
                // Substitute placeholders
                $body = str_replace(
                    ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}'],
                    [$recipient['FIRST_NAME'], $recipient['LAST_NAME'], getConfig('XMAS_YEAR', date('Y'))],
                    $template['MESSAGE_BODY']
                );

                // In a real app: send email/SMS here
                // mail($recipient['EMAIL'], $template['MESSAGE_NAME'], $body);

                // Log the send
                $logStmt->execute([$messageId, $recipient['USER_ID'], $channel]);
                $sent++;
            }

            $msg = "✅ Message sent to {$sent} recipient" . ($sent !== 1 ? 's' : '') . " via {$channel}.";
            $msgType = 'success';

            // Keep send panel open on the same message
            $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
            $stmt->execute([$messageId]);
            $editing = $stmt->fetch() ?: null;
        }
    }
}

// Load edit target from GET
if (!$editing && isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM SS_MESSAGES WHERE MESSAGE_ID = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

// Fetch all templates
$templates = $pdo->query("SELECT * FROM SS_MESSAGES ORDER BY MESSAGE_NAME ASC")->fetchAll();

// Fetch active users for the send-to dropdown
$users = $pdo->query("SELECT USER_ID, FIRST_NAME, LAST_NAME FROM SS_USERS WHERE STATUS = 'ACTIVE' ORDER BY FIRST_NAME ASC")->fetchAll();

// Send log (last 20)
$logs = $pdo->query("
    SELECT l.*, m.MESSAGE_NAME, u.FIRST_NAME, u.LAST_NAME
    FROM SS_MESSAGE_LOG l
    JOIN SS_MESSAGES m ON m.MESSAGE_ID = l.MESSAGE_ID
    JOIN SS_USERS u ON u.USER_ID = l.USER_ID
    ORDER BY l.SENT_AT DESC
    LIMIT 20
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">✉️ Message Center</h1>
    <a href="?add=1" class="btn btn-primary">➕ New Template</a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- ADD Template Form -->
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
            <div class="field-hint">Available placeholders: <code>{FIRST_NAME}</code> <code>{LAST_NAME}</code> <code>{YEAR}</code></div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Template</button>
            <a href="<?= APP_URL ?>/admin/messages.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- EDIT + SEND Form -->
<?php if ($editing): ?>
<div class="card">
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
            <div class="field-hint">Available placeholders: <code>{FIRST_NAME}</code> <code>{LAST_NAME}</code> <code>{YEAR}</code></div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= APP_URL ?>/admin/messages.php" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-danger"
                    onclick="if(confirm('Delete this message template?')) document.getElementById('delMsg<?= $editing['MESSAGE_ID'] ?>').submit()">
                Delete
            </button>
        </div>
    </form>
    <form id="delMsg<?= $editing['MESSAGE_ID'] ?>" method="POST" action="" style="display:none;">
        <input type="hidden" name="action"     value="delete">
        <input type="hidden" name="message_id" value="<?= $editing['MESSAGE_ID'] ?>">
    </form>
</div>

<!-- SEND Panel -->
<div class="card send-card">
    <div class="card-title">📤 Send This Message</div>
    <form method="POST" action="">
        <input type="hidden" name="action"     value="send">
        <input type="hidden" name="message_id" value="<?= $editing['MESSAGE_ID'] ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="send_to">Send To <span class="required">*</span></label>
                <select id="send_to" name="send_to" onchange="toggleUserSelect(this)">
                    <option value="all">All Active Users (<?= count($users) ?>)</option>
                    <option value="one">Specific User</option>
                </select>
            </div>
            <div class="form-group" id="userSelectGroup" style="display:none;">
                <label for="user_id">Select User <span class="required">*</span></label>
                <select id="user_id" name="user_id">
                    <?php foreach ($users as $u): ?>
                    <option value="<?= h($u['USER_ID']) ?>"><?= h($u['FIRST_NAME']) ?> <?= h($u['LAST_NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
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
                ['{FIRST_NAME}', '{LAST_NAME}', '{YEAR}'],
                ['Chanda', 'Williams', getConfig('XMAS_YEAR', date('Y'))],
                $editing['MESSAGE_BODY']
            ))) ?></div>
        </div>

        <div class="form-actions" style="margin-top:1rem;">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Send this message now?')">
                📤 Send Message
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Templates Table -->
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
                    <th>Preview</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $tpl): ?>
                <tr class="<?= $editing && $editing['MESSAGE_ID'] == $tpl['MESSAGE_ID'] ? 'row-active' : '' ?>">
                    <td>
                        <a href="?edit=<?= $tpl['MESSAGE_ID'] ?>" class="name-link">
                            <?= h($tpl['MESSAGE_NAME']) ?>
                        </a>
                    </td>
                    <td class="preview-col"><?= h(mb_substr($tpl['MESSAGE_BODY'], 0, 80)) ?>...</td>
                    <td class="nowrap date-col"><?= date('M j, Y g:ia', strtotime($tpl['UPDATED_AT'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Send Log -->
<?php if (!empty($logs)): ?>
<div class="card">
    <div class="card-title">📜 Recent Send Log (last 20)</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Sent At</th>
                    <th>Template</th>
                    <th>Recipient</th>
                    <th>Channel</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="nowrap date-col"><?= date('M j, Y g:ia', strtotime($log['SENT_AT'])) ?></td>
                    <td><?= h($log['MESSAGE_NAME']) ?></td>
                    <td><?= h($log['FIRST_NAME']) ?> <?= h($log['LAST_NAME']) ?></td>
                    <td><span class="badge badge-channel"><?= h($log['CHANNEL']) ?></span></td>
                    <td><span class="badge <?= $log['STATUS'] === 'SENT' ? 'badge-active' : 'badge-inactive' ?>"><?= h($log['STATUS']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.page-header  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
.page-header .page-title { margin-bottom: 0; }

.form-row     { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
@media (max-width: 700px) { .form-row { grid-template-columns: 1fr; } }

.required { color: #c0392b; }
.form-actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; align-items: center; }
.field-hint { font-size: 0.8rem; color: #999; margin-top: 0.3rem; }
.field-hint code { background: #f4f6f8; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.82rem; color: #c0392b; }

.name-link  { font-weight: 600; color: #c0392b; text-decoration: none; }
.name-link:hover { text-decoration: underline; }

.send-card  { border-left: 4px solid #1e8449; }

.preview-box   { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; margin-top: 0.5rem; }
.preview-label { font-size: 0.82rem; font-weight: 700; color: #888; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.04em; }
.preview-note  { font-weight: 400; text-transform: none; font-style: italic; }
.preview-body  { font-size: 0.95rem; color: #333; line-height: 1.6; }

.preview-col { font-size: 0.85rem; color: #777; max-width: 300px; }
.date-col    { font-size: 0.82rem; color: #999; white-space: nowrap; }
.nowrap      { white-space: nowrap; }
.empty-state { color: #999; padding: 1rem 0; }
.row-active td { background: #fff8f0; }

.badge         { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 20px; white-space: nowrap; }
.badge-channel { background: #dce8f8; color: #1a5276; }
.badge-active  { background: #d4edda; color: #155724; }
.badge-inactive{ background: #f8d7da; color: #721c24; }

.btn-success { background: #1e8449; color: #fff; }
.btn-danger  { background: #c0392b; color: #fff; }
</style>

<script>
function toggleUserSelect(sel) {
    document.getElementById('userSelectGroup').style.display =
        sel.value === 'one' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>