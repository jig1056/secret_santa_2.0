<?php
// ============================================================
// helpers.php
// General utility functions used across the app.
// ============================================================

// ------------------------------------------------------------
// Safely output a string to HTML (prevents XSS)
// ------------------------------------------------------------
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------
// Generate a unique USER_ID in the format FirstNameX_1234
// Retries on collision (extremely rare with 15 users)
// ------------------------------------------------------------
function generateUserId(string $firstName, string $lastName, PDO $pdo): string {
    do {
        $id = $firstName . strtoupper($lastName[0]) . '_' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM SS_USERS WHERE USER_ID = ?");
        $stmt->execute([$id]);
    } while ($stmt->fetchColumn() > 0);

    return $id;
}

// ------------------------------------------------------------
// Get a config value from SS_CONFIG by key.
// Returns $default if the key doesn't exist.
// ------------------------------------------------------------
function getConfig(string $key, string $default = ''): string {
    static $cache = [];

    if (!isset($cache[$key])) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT CONFIG_VALUE FROM SS_CONFIG WHERE CONFIG_KEY = ?");
        $stmt->execute([$key]);
        $row  = $stmt->fetch();
        $cache[$key] = $row ? $row['CONFIG_VALUE'] : $default;
    }

    return $cache[$key];
}

// ------------------------------------------------------------
// Check whether Secret Santa matches have been generated
// for the current year.
// ------------------------------------------------------------
function matchesGenerated(): bool {
    $pdo  = getDB();
    $year = getConfig('XMAS_YEAR', date('Y'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM SS_MATCHES WHERE YEAR = ?");
    $stmt->execute([$year]);
    return $stmt->fetchColumn() > 0;
}

// ------------------------------------------------------------
// Get the match (receiver) for a given giver USER_ID.
// Returns the receiver's row from SS_USERS, or null.
// ------------------------------------------------------------
function getMatchForUser(string $userId): ?array {
    $pdo  = getDB();
    $year = getConfig('XMAS_YEAR', date('Y'));
    $stmt = $pdo->prepare("
        SELECT u.USER_ID, u.FIRST_NAME, u.LAST_NAME, u.SEX
        FROM SS_MATCHES m
        JOIN SS_USERS u ON u.USER_ID = m.RECEIVER_USER_ID
        WHERE m.GIVER_USER_ID = ? AND m.YEAR = ?
    ");
    $stmt->execute([$userId, $year]);
    return $stmt->fetch() ?: null;
}

// ------------------------------------------------------------
// Return a pronoun for a given SEX value and grammatical case.
// $case: 'subject' (he/she/they), 'object' (him/her/them),
//        'possessive' (his/her/their)
// Falls back to "their/them/they" if SEX is null/unset.
// ------------------------------------------------------------
function pronoun(?string $sex, string $case = 'possessive'): string {
    $sex = strtoupper((string) $sex);

    $map = [
        'MALE'   => ['subject' => 'he',   'object' => 'him',  'possessive' => 'his'],
        'FEMALE' => ['subject' => 'she',  'object' => 'her',  'possessive' => 'her'],
    ];

    return $map[$sex][$case] ?? ['subject' => 'they', 'object' => 'them', 'possessive' => 'their'][$case];
}