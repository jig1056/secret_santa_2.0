<?php
// ============================================================
// admin/dashboard.php
// Shows overview stats and gift count per user. Admin only.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$pdo      = getDB();
$xmasYear = getConfig('XMAS_YEAR', date('Y'));

// -- Summary stats --
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM SS_USERS WHERE STATUS = 'ACTIVE'")->fetchColumn();
$totalGiftsStmt = $pdo->prepare("SELECT COUNT(*) FROM SS_GIFTS WHERE YEAR = ?");
$totalGiftsStmt->execute([$xmasYear]);
$totalGifts    = $totalGiftsStmt->fetchColumn();
$matchesDone   = matchesGenerated();
$totalMatches  = $pdo->query("SELECT COUNT(*) FROM SS_MATCHES WHERE YEAR = " . (int)$xmasYear)->fetchColumn();

// -- Users with gift counts --
$stmt = $pdo->prepare("
    SELECT
        u.USER_ID,
        u.FIRST_NAME,
        u.LAST_NAME,
        u.STATUS,
        u.USER_TYPE,
        COUNT(g.GIFT_ID) AS GIFT_COUNT
    FROM SS_USERS u
    LEFT JOIN SS_GIFTS g ON g.USER_ID = u.USER_ID AND g.YEAR = ?
    WHERE u.STATUS = 'ACTIVE'
    GROUP BY u.USER_ID, u.FIRST_NAME, u.LAST_NAME, u.STATUS, u.USER_TYPE
    ORDER BY GIFT_COUNT ASC, u.LAST_NAME ASC
");
$stmt->execute([$xmasYear]);
$userGifts = $stmt->fetchAll();

// -- Who has 0 gifts --
$noGifts = array_filter($userGifts, fn($u) => $u['GIFT_COUNT'] == 0);

// -- Average gifts per user --
$avgGifts = $totalUsers > 0 ? round($totalGifts / $totalUsers, 1) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">📊 Dashboard — <?= h($xmasYear) ?></h1>

<!-- Summary stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= $totalUsers ?></div>
        <div class="stat-label">Active Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎁</div>
        <div class="stat-value"><?= $totalGifts ?></div>
        <div class="stat-label">Total Gifts Added</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-value"><?= $avgGifts ?></div>
        <div class="stat-label">Avg Gifts / User</div>
    </div>
    <div class="stat-card <?= $matchesDone ? 'stat-card-green' : 'stat-card-gold' ?>">
        <div class="stat-icon"><?= $matchesDone ? '✅' : '⏳' ?></div>
        <div class="stat-value"><?= $matchesDone ? $totalMatches : '—' ?></div>
        <div class="stat-label"><?= $matchesDone ? 'Matches Made' : 'Matches Pending' ?></div>
    </div>
</div>

<?php if (!empty($noGifts)): ?>
<!-- Warning: users with no gifts -->
<div class="alert alert-info" style="display:flex;align-items:center;gap:0.75rem;">
    <span style="font-size:1.3rem;">⚠️</span>
    <span>
        <strong><?= count($noGifts) ?> user<?= count($noGifts) !== 1 ? 's have' : ' has' ?> no gifts yet:</strong>
        <?= implode(', ', array_map(fn($u) => h($u['FIRST_NAME'] . ' ' . $u['LAST_NAME']), $noGifts)) ?>
    </span>
</div>
<?php endif; ?>

<!-- Per-user gift table -->
<div class="card">
    <div class="card-header-row">
        <div class="card-title" style="margin-bottom:0;">🎄 Gift List Status by User</div>
        <input type="text" id="dashSearch" placeholder="🔍 Search..." oninput="filterDash()" class="dash-search">
    </div>

    <div class="table-wrap" style="margin-top:1rem;">
        <table id="dashTable">
            <thead>
                <tr>
                    <th class="sortable" onclick="sortDash(0)">Name <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortDash(1)">Type <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortDash(2)">Gifts Added <span class="sort-icon">↕</span></th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userGifts as $user):

                    $pct = min(100, round($user['GIFT_COUNT'] / 5 * 100)); // 5 gifts = 100%
                    $barColor = $user['GIFT_COUNT'] == 0 ? '#e74c3c' : ($user['GIFT_COUNT'] < 3 ? '#e67e22' : '#1e8449');
                ?>
                <tr data-name="<?= strtolower(h($user['FIRST_NAME'] . ' ' . $user['LAST_NAME'])) ?>">
                    <td>
                        <strong><?= h($user['FIRST_NAME']) ?> <?= h($user['LAST_NAME']) ?></strong>
                        <div class="user-id-small"><?= h($user['USER_ID']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $user['USER_TYPE'] === 'ADMIN' ? 'badge-admin' : 'badge-standard' ?>">
                            <?= h($user['USER_TYPE']) ?>
                        </span>
                    </td>
                    <td class="gift-count <?= $user['GIFT_COUNT'] == 0 ? 'count-zero' : '' ?>">
                        <?= $user['GIFT_COUNT'] ?>
                    </td>
                    <td>
                        <div class="progress-wrap">
                            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                        </div>
                        <div class="progress-label"><?= $user['GIFT_COUNT'] ?> gift<?= $user['GIFT_COUNT'] !== 1 ? 's' : '' ?></div>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>



<style>
/* Stat cards */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
@media (max-width: 700px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 1.25rem;
    text-align: center;
    border-top: 4px solid #c0392b;
}
.stat-card-green { border-top-color: #1e8449; }
.stat-card-gold  { border-top-color: #d4ac0d; }
.stat-icon  { font-size: 1.8rem; margin-bottom: 0.4rem; }
.stat-value { font-size: 2rem; font-weight: 700; color: #212529; line-height: 1; }
.stat-label { font-size: 0.85rem; color: #777; margin-top: 0.3rem; }

/* Dashboard table */
.card-header-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; }
.dash-search { padding: 0.4rem 0.75rem; border: 1px solid #ccc; border-radius: 8px; font-size: 0.9rem; min-width: 200px; }

.gift-count      { font-weight: 700; font-size: 1.05rem; }
.count-zero      { color: #c0392b; }

.progress-wrap   { background: #eee; border-radius: 20px; height: 8px; width: 120px; overflow: hidden; }
.progress-bar    { height: 100%; border-radius: 20px; transition: width 0.3s; }
.progress-label  { font-size: 0.75rem; color: #888; margin-top: 0.2rem; }

.badge          { display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 20px; white-space: nowrap; }
.badge-admin    { background: #922b21; color: #fff; }
.badge-standard { background: #e8e8e8; color: #444; }
.user-id-small  { font-size: 0.75rem; color: #999; margin-top: 0.1rem; }

th.sortable { cursor: pointer; user-select: none; white-space: nowrap; }
th.sortable:hover { background: #e8e8e8; }
.sort-icon { font-size: 0.75rem; color: #999; }
th.sort-asc .sort-icon::after  { content: " ▲"; color: #c0392b; }
th.sort-desc .sort-icon::after { content: " ▼"; color: #c0392b; }

.quick-links { display: flex; flex-wrap: wrap; gap: 0.65rem; }
</style>

<script>
let dashSortCol = -1, dashSortAsc = true;

function sortDash(col) {
    const tbody = document.querySelector('#dashTable tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));
    if (dashSortCol === col) { dashSortAsc = !dashSortAsc; } else { dashSortCol = col; dashSortAsc = true; }

    document.querySelectorAll('#dashTable th.sortable').forEach((th, i) => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (i === col) th.classList.add(dashSortAsc ? 'sort-asc' : 'sort-desc');
    });

    rows.sort((a, b) => {
        const aVal = a.cells[col]?.innerText.trim().toLowerCase() || '';
        const bVal = b.cells[col]?.innerText.trim().toLowerCase() || '';
        // Numeric sort for gift count column
        if (col === 2) return dashSortAsc ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
        return dashSortAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });

    rows.forEach(r => tbody.appendChild(r));
}

function filterDash() {
    const q    = document.getElementById('dashSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#dashTable tbody tr');
    rows.forEach(row => {
        row.style.display = !q || row.dataset.name.includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
