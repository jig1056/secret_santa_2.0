<?php
// ============================================================
// admin/generate.php
// Randomly generates Secret Santa matches for the current year.
// Ensures nobody gets themselves. Admin only.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo      = getDB();
$xmasYear = getConfig('XMAS_YEAR', date('Y'));
$msg      = '';
$msgType  = '';
$newMatches = [];

// -- Check if matches already exist --
$matchesDone = matchesGenerated();

// -- Fetch active users --
$stmt  = $pdo->query("SELECT USER_ID, FIRST_NAME, LAST_NAME FROM SS_USERS WHERE STATUS = 'ACTIVE' ORDER BY FIRST_NAME ASC");
$users = $stmt->fetchAll();

// -- Fetch existing matches for this year --
$stmt = $pdo->prepare("
    SELECT m.GIVER_USER_ID, g.FIRST_NAME AS GIVER_FIRST, g.LAST_NAME AS GIVER_LAST,
           m.RECEIVER_USER_ID, r.FIRST_NAME AS RECEIVER_FIRST, r.LAST_NAME AS RECEIVER_LAST
    FROM SS_MATCHES m
    JOIN SS_USERS g ON g.USER_ID = m.GIVER_USER_ID
    JOIN SS_USERS r ON r.USER_ID = m.RECEIVER_USER_ID
    WHERE m.YEAR = ?
    ORDER BY g.LAST_NAME ASC
");
$stmt->execute([$xmasYear]);
$existingMatches = $stmt->fetchAll();

// ------------------------------------------------------------
// Handle POST: generate or clear matches
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -- CLEAR MATCHES --
    if ($action === 'clear') {
        $pdo->prepare("DELETE FROM SS_MATCHES WHERE YEAR = ?")->execute([$xmasYear]);
        $msg     = "All {$xmasYear} matches have been cleared.";
        $msgType = 'success';
        $matchesDone    = false;
        $existingMatches = [];

    // -- GENERATE MATCHES --
    } elseif ($action === 'generate') {
        if (count($users) < 2) {
            $msg     = 'You need at least 2 active users to generate matches.';
            $msgType = 'error';
        } else {
            // Clear existing matches for this year first
            $pdo->prepare("DELETE FROM SS_MATCHES WHERE YEAR = ?")->execute([$xmasYear]);

            // Generate a valid derangement (no one gets themselves)
            $ids      = array_column($users, 'USER_ID');
            $attempts = 0;
            $receivers = [];

            do {
                $receivers = $ids;
                shuffle($receivers);
                $valid = true;
                foreach ($ids as $i => $id) {
                    if ($receivers[$i] === $id) { $valid = false; break; }
                }
                $attempts++;
            } while (!$valid && $attempts < 1000);

            if (!$valid) {
                $msg     = 'Could not generate valid matches after 1000 attempts. Please try again.';
                $msgType = 'error';
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO SS_MATCHES (GIVER_USER_ID, RECEIVER_USER_ID, YEAR) VALUES (?, ?, ?)");
                foreach ($ids as $i => $giverId) {
                    $insertStmt->execute([$giverId, $receivers[$i], $xmasYear]);
                }

                // Reload matches for display
                $stmt = $pdo->prepare("
                    SELECT m.GIVER_USER_ID, g.FIRST_NAME AS GIVER_FIRST, g.LAST_NAME AS GIVER_LAST,
                           m.RECEIVER_USER_ID, r.FIRST_NAME AS RECEIVER_FIRST, r.LAST_NAME AS RECEIVER_LAST
                    FROM SS_MATCHES m
                    JOIN SS_USERS g ON g.USER_ID = m.GIVER_USER_ID
                    JOIN SS_USERS r ON r.USER_ID = m.RECEIVER_USER_ID
                    WHERE m.YEAR = ?
                    ORDER BY g.LAST_NAME ASC
                ");
                $stmt->execute([$xmasYear]);
                $existingMatches = $stmt->fetchAll();
                $matchesDone     = true;

                $msg     = '🎉 ' . count($existingMatches) . ' Secret Santa matches generated successfully for ' . $xmasYear . '!';
                $msgType = 'success';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">🎲 Generate Secret Santa Matches</h1>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<!-- Status card -->
<div class="card status-banner <?= $matchesDone ? 'banner-green' : 'banner-gold' ?>">
    <div class="banner-inner">
        <div class="banner-icon"><?= $matchesDone ? '✅' : '⏳' ?></div>
        <div>
            <div class="banner-title">
                <?= $matchesDone
                    ? $xmasYear . ' matches have been generated (' . count($existingMatches) . ' pairs)'
                    : 'No matches generated yet for ' . $xmasYear ?>
            </div>
            <div class="banner-sub">
                <?= count($users) ?> active user<?= count($users) !== 1 ? 's' : '' ?> in the pool
            </div>
        </div>
    </div>
</div>

<!-- Action buttons -->
<div class="card">
    <div class="card-title">⚙️ Actions</div>

    <?php if (count($users) < 2): ?>
    <div class="alert alert-error">You need at least 2 active users to generate matches.</div>
    <?php else: ?>

    <div class="action-row">
        <?php if (!$matchesDone): ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate">
            <button type="submit" class="btn btn-primary btn-lg"
                    onclick="return confirm('Generate Secret Santa matches for <?= h($xmasYear) ?>? This will assign everyone a recipient.')">
                🎲 Generate <?= h($xmasYear) ?> Matches
            </button>
        </form>
        <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate">
            <button type="submit" class="btn btn-warning btn-lg"
                    onclick="return confirm('Re-generate matches? This will REPLACE all existing <?= h($xmasYear) ?> matches. Everyone will get a new assignment.')">
                🔄 Re-Generate Matches
            </button>
        </form>
        <form method="POST" action="">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-danger btn-lg"
                    onclick="return confirm('Clear all <?= h($xmasYear) ?> matches? Users will no longer be able to see who they are gifting.')">
                🗑️ Clear All Matches
            </button>
        </form>
        <?php endif; ?>
    </div>

    <p class="action-note">
        <?= $matchesDone
            ? '⚠️ Re-generating will replace all current assignments. Users will be notified if you send a new matches announcement.'
            : '✨ Once generated, each user will be able to log in and see who they are gifting.' ?>
    </p>

    <?php endif; ?>
</div>

<!-- Current matches table -->
<?php if ($matchesDone && !empty($existingMatches)): ?>
<div class="card">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">🤫 <?= h($xmasYear) ?> Match Assignments</div>
        <button class="btn btn-secondary btn-sm" id="revealBtn"
                onclick="revealMatches()">👁️ Reveal Matches</button>
    </div>

    <div id="matchesHidden" class="matches-hidden-msg">
        <span>🔒</span> Match assignments are hidden. Click "Reveal Matches" to view them.
    </div>

    <div id="matchesTable" style="display:none;">
        <p class="matches-note">⚠️ Keep this secret! Only share if someone forgets their assignment.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Secret Santa (Giver)</th>
                        <th style="text-align:center;">→</th>
                        <th>Recipient (Receiver)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingMatches as $i => $match): ?>
                    <tr>
                        <td class="match-num"><?= $i + 1 ?></td>
                        <td><strong><?= h($match['GIVER_FIRST']) ?> <?= h($match['GIVER_LAST']) ?></strong></td>
                        <td style="text-align:center;font-size:1.2rem;">🎅🏾</td>
                        <td><strong><?= h($match['RECEIVER_FIRST']) ?> <?= h($match['RECEIVER_LAST']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:0.75rem;">
            <button class="btn btn-secondary btn-sm" onclick="hideMatches()">🔒 Hide Matches</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Active users in pool -->
<div class="card">
    <div class="card-title">👥 Active Users in Pool (<?= count($users) ?>)</div>
    <div class="user-pool">
        <?php foreach ($users as $user): ?>
        <div class="pool-chip">
            <?= h($user['FIRST_NAME']) ?> <?= h($user['LAST_NAME']) ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.status-banner  { padding: 1.25rem 1.5rem; }
.banner-green   { background: linear-gradient(135deg, #1e8449, #145a32); color: #fff; }
.banner-gold    { background: linear-gradient(135deg, #d4ac0d, #9a7d0a); color: #fff; }
.banner-inner   { display: flex; align-items: center; gap: 1rem; }
.banner-icon    { font-size: 2rem; flex-shrink: 0; }
.banner-title   { font-size: 1.05rem; font-weight: 700; }
.banner-sub     { font-size: 0.9rem; opacity: 0.85; margin-top: 0.2rem; }

.action-row     { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin-bottom: 0.75rem; }
.action-note    { font-size: 0.9rem; color: #666; margin-top: 0.25rem; }
.btn-lg         { padding: 0.65rem 1.5rem; font-size: 1rem; }
.btn-danger     { background: #c0392b; color: #fff; }
.btn-warning    { background: #e67e22; color: #fff; }

.matches-note       { font-size: 0.88rem; color: #888; margin-bottom: 0.75rem; font-style: italic; }
.match-num          { color: #aaa; font-size: 0.85rem; width: 30px; }
.card-header-row    { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; }
.matches-hidden-msg { text-align: center; padding: 1.5rem; color: #888; font-size: 1rem; background: #f9f9f9; border-radius: 8px; border: 2px dashed #ddd; }
.matches-hidden-msg span { font-size: 1.5rem; display: block; margin-bottom: 0.4rem; }

.user-pool      { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.pool-chip      { background: #f0f0f0; border-radius: 20px; padding: 0.3rem 0.85rem; font-size: 0.88rem; color: #444; border: 1px solid #ddd; }
</style>

<script>
function revealMatches() {
    if (confirm('Are you sure you want to reveal all match assignments? Remember — Secret Santa is supposed to be a surprise!')) {
        document.getElementById('matchesHidden').style.display = 'none';
        document.getElementById('matchesTable').style.display  = 'block';
        document.getElementById('revealBtn').style.display     = 'none';
    }
}

function hideMatches() {
    document.getElementById('matchesHidden').style.display = 'block';
    document.getElementById('matchesTable').style.display  = 'none';
    document.getElementById('revealBtn').style.display     = 'inline-block';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>