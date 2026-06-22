<?php
// ============================================================
// admin/backup.php
// Generate a full SQL backup (structure + data) for all SS_
// tables in the current environment's database.
// Admin only. Backup is served as a .sql file download.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireAdmin();

$tables = [
    'SS_CONFIG',
    'SS_GIFTS',
    'SS_MATCHES',
    'SS_MESSAGE_LOG',
    'SS_MESSAGE_ROLES',
    'SS_MESSAGES',
    'SS_PASSWORD_RESETS',
    'SS_REMEMBER_TOKENS',
    'SS_ROLES',
    'SS_USER_ROLES',
    'SS_USERS',
    'SS_WISHLIST_ACCESS',
];

// ============================================================
// DOWNLOAD handler — generate SQL and stream to browser
// ============================================================
if (isset($_GET['download'])) {
    $pdo      = getDB();
    $dbName   = DB_NAME;
    $env      = APP_ENV;
    $filename = 'ss_backup_' . $env . '_' . date('Ymd_His') . '.sql';

    // Build SQL in memory
    $sql  = "-- ============================================================\n";
    $sql .= "-- Secret Santa Database Backup\n";
    $sql .= "-- Environment : {$env} ({$dbName})\n";
    $sql .= "-- Generated   : " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- ============================================================\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    foreach ($tables as $table) {
        // -- Table structure --
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $createSql  = $createStmt['Create Table'] ?? '';

        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "-- Table: {$table}\n";
        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createSql . ";\n\n";

        // -- Table data --
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            // Get column names from first row
            $cols    = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $cols) . '`';

            $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";

            $valueLines = [];
            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } else {
                        // Escape via PDO quote (handles special chars, encoding)
                        $vals[] = $pdo->quote($val);
                    }
                }
                $valueLines[] = '(' . implode(', ', $vals) . ')';
            }

            // Write in batches of 100 rows for readability / safety
            $batches = array_chunk($valueLines, 100);
            foreach ($batches as $i => $batch) {
                if ($i > 0) {
                    $sql .= ";\nINSERT INTO `{$table}` ({$colList}) VALUES\n";
                }
                $sql .= implode(",\n", $batch) . ";\n";
            }
        } else {
            $sql .= "-- (no rows)\n";
        }

        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "-- End of backup\n";

    // Stream as download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache');
    echo $sql;
    exit;
}

// ============================================================
// Page UI
// ============================================================
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">🗄️ Database Backup</h1>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
</div>

<div class="card">
    <div class="card-title">Backup: <?= h(DB_NAME) ?> <span class="env-badge env-<?= APP_ENV ?>"><?= strtoupper(APP_ENV) ?></span></div>

    <p style="margin-bottom:1.25rem;color:#555;line-height:1.7;">
        This will generate a complete SQL backup of all Secret Santa tables in the
        <strong><?= h(DB_NAME) ?></strong> database — including table structures and all data.
        The file will download to your computer as a <code>.sql</code> file.
    </p>

    <div class="backup-table-list">
        <div class="backup-table-label">Tables included:</div>
        <div class="backup-tags">
            <?php foreach ($tables as $t): ?>
            <span class="backup-tag"><?= h($t) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="margin-top:1.5rem;">
        <a href="?download=1" class="btn btn-primary btn-lg">
            ⬇️ Download SQL Backup
        </a>
    </div>
</div>

<div class="card" style="border-left:4px solid #e67e22;">
    <div class="card-title" style="color:#e67e22;">⚠️ Before Running Migrations</div>
    <ol style="color:#555;line-height:1.9;padding-left:1.25rem;margin:0;">
        <li>Download a backup using the button above.</li>
        <li>Verify the <code>.sql</code> file looks correct before proceeding.</li>
        <li>Run your migration SQL against <strong>HLDEV</strong> first and test thoroughly.</li>
        <li>When satisfied, repeat on <strong>HLPRD</strong>.</li>
        <li>If anything goes wrong, restore by running the backup <code>.sql</code> file against the affected database.</li>
    </ol>
</div>

<style>
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; }

.env-badge {
    display:inline-block; font-size:0.75rem; font-weight:700;
    padding:0.15rem 0.55rem; border-radius:20px; vertical-align:middle;
    margin-left:0.5rem; letter-spacing:0.05em;
}
.env-dev { background:#d4edda; color:#155724; }
.env-prd { background:#f8d7da; color:#721c24; }

.backup-table-label { font-size:0.82rem; font-weight:700; color:#888; text-transform:uppercase;
    letter-spacing:0.04em; margin-bottom:0.5rem; }
.backup-tags { display:flex; flex-wrap:wrap; gap:0.4rem; }
.backup-tag  { background:#f4f6f8; border:1px solid #dde; border-radius:6px;
    font-size:0.82rem; padding:0.2rem 0.6rem; font-family:monospace; color:#333; }

.btn-lg { font-size:1rem; padding:0.65rem 1.4rem; }

ol li { margin-bottom:0.1rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
