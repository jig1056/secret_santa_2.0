<?php
// ============================================================
// auth.php
// Session management and authentication helpers.
//
// USAGE: require_once __DIR__ . '/../includes/auth.php';
// ============================================================

require_once __DIR__ . '/config.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ------------------------------------------------------------
// Check if the current user is logged in.
// Enforces session timeout. Falls back to remember-me cookie.
// Redirects to login if neither is valid.
// ------------------------------------------------------------
function requireLogin(): void {
    if (empty($_SESSION['USER_ID'])) {
        // No active session -- try to log in via remember-me cookie
        if (!attemptRememberMeLogin()) {
            redirect('/index.php');
        }
        return;
    }

    // Timeout check
    if (!empty($_SESSION['LAST_ACTIVITY'])) {
        if ((time() - $_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT) {
            // Session timed out -- try remember-me before forcing login
            logoutUser(false); // don't clear remember-me cookie on timeout
            if (!attemptRememberMeLogin()) {
                redirect('/index.php?reason=timeout');
            }
            return;
        }
    }

    // Status check -- kick out deactivated users mid-session
    require_once __DIR__ . '/db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT STATUS FROM SS_USERS WHERE USER_ID = ?");
    $stmt->execute([$_SESSION['USER_ID']]);
    $status = $stmt->fetchColumn();
    if ($status !== 'ACTIVE') {
        logoutUser();
        redirect('/index.php');
    }

    $_SESSION['LAST_ACTIVITY'] = time();
}

// ------------------------------------------------------------
// Check if the current user is an admin.
// Redirects to home if not.
// ------------------------------------------------------------
function requireAdmin(): void {
    requireLogin();
    if (!hasRole('admin')) {
        redirect('/pages/home.php');
    }
}

// ------------------------------------------------------------
// Check if the current user has a specific role.
// $role: role key string e.g. 'admin', 'secret_santa',
//        'wishlist_only', 'wishlist_gifter'
// ------------------------------------------------------------
function hasRole(string $role): bool {
    return in_array($role, $_SESSION['ROLES'] ?? [], true);
}

// ------------------------------------------------------------
// Require a specific role — redirects to home if not met.
// ------------------------------------------------------------
function requireRole(string $role): void {
    requireLogin();
    if (!hasRole($role)) {
        redirect('/pages/home.php');
    }
}

// ------------------------------------------------------------
// Return the logged-in user's ID, or null if not logged in.
// ------------------------------------------------------------
function currentUserId(): ?string {
    return $_SESSION['USER_ID'] ?? null;
}

// ------------------------------------------------------------
// Return true if the current user is an admin.
// ------------------------------------------------------------
function isAdmin(): bool {
    return hasRole('admin');
}

// ------------------------------------------------------------
// Internal: fetch role IDs for a user from SS_USER_ROLES.
// Returns an array of ROLE_ID strings e.g. ['admin','secret_santa']
// ------------------------------------------------------------
function _loadUserRoles(string $userId, PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT r.ROLE_ID
        FROM SS_USER_ROLES ur
        JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
        WHERE ur.USER_ID = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// ------------------------------------------------------------
// Log a user in by storing their info in the session.
// If $rememberMe is true, also sets a 60-day persistent cookie.
// ------------------------------------------------------------
function loginUser(array $user, bool $rememberMe = false): void {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();

    session_regenerate_id(true); // prevent session fixation
    $_SESSION['USER_ID']       = $user['USER_ID'];
    $_SESSION['FIRST_NAME']    = $user['FIRST_NAME'];
    $_SESSION['LAST_NAME']     = $user['LAST_NAME'];
    $_SESSION['EMAIL']         = $user['EMAIL'];
    $_SESSION['USER_TYPE']     = $user['USER_TYPE']; // kept for backwards compat
    $_SESSION['ROLES']         = _loadUserRoles($user['USER_ID'], $pdo);
    $_SESSION['LAST_ACTIVITY'] = time();

    if ($rememberMe) {
        setRememberMeCookie($user['USER_ID']);
    }
}

// ------------------------------------------------------------
// Create a remember-me token, store its hash in the DB, and
// set a secure cookie containing the raw token.
// Valid for REMEMBER_ME_DAYS (default 60 days).
// ------------------------------------------------------------
function setRememberMeCookie(string $userId): void {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();

    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $days      = (int) (defined('REMEMBER_ME_DAYS') ? REMEMBER_ME_DAYS : 60);
    $expires   = date('Y-m-d H:i:s', time() + ($days * 86400));

    $pdo->prepare("INSERT INTO SS_REMEMBER_TOKENS (USER_ID, TOKEN_HASH, EXPIRES_AT) VALUES (?, ?, ?)")
        ->execute([$userId, $tokenHash, $expires]);

    // Cookie value: userId:rawToken (rawToken is never stored in DB, only its hash)
    $cookieValue = $userId . ':' . $rawToken;
    setcookie('ss_remember', $cookieValue, [
        'expires'  => time() + ($days * 86400),
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ------------------------------------------------------------
// Check for a valid remember-me cookie and log the user in if
// one is found. Returns true if login succeeded.
// ------------------------------------------------------------
function attemptRememberMeLogin(): bool {
    if (empty($_COOKIE['ss_remember'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['ss_remember'], 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$userId, $rawToken] = $parts;
    $tokenHash = hash('sha256', $rawToken);

    require_once __DIR__ . '/db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT t.*, u.FIRST_NAME, u.LAST_NAME, u.EMAIL, u.USER_TYPE, u.STATUS
        FROM SS_REMEMBER_TOKENS t
        JOIN SS_USERS u ON u.USER_ID = t.USER_ID
        WHERE t.USER_ID = ? AND t.TOKEN_HASH = ? AND t.EXPIRES_AT > NOW() AND u.STATUS = 'ACTIVE'
    ");
    $stmt->execute([$userId, $tokenHash]);
    $result = $stmt->fetch();

    if (!$result) {
        // Invalid/expired token -- clear the bad cookie
        setcookie('ss_remember', '', time() - 3600, '/');
        return false;
    }

    // Valid -- log the user in (refreshes session, keeps remember-me cookie as is)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['USER_ID']       = $result['USER_ID'];
    $_SESSION['FIRST_NAME']    = $result['FIRST_NAME'];
    $_SESSION['LAST_NAME']     = $result['LAST_NAME'];
    $_SESSION['EMAIL']         = $result['EMAIL'];
    $_SESSION['USER_TYPE']     = $result['USER_TYPE']; // kept for backwards compat
    $_SESSION['ROLES']         = _loadUserRoles($result['USER_ID'], $pdo);
    $_SESSION['LAST_ACTIVITY'] = time();

    return true;
}

// ------------------------------------------------------------
// Revoke all remember-me tokens for the current user (used on
// explicit logout so "remember me" doesn't silently log back in).
// ------------------------------------------------------------
function clearRememberMeCookie(): void {
    if (!empty($_COOKIE['ss_remember'])) {
        $parts = explode(':', $_COOKIE['ss_remember'], 2);
        if (count($parts) === 2) {
            require_once __DIR__ . '/db.php';
            $pdo = getDB();
            $pdo->prepare("DELETE FROM SS_REMEMBER_TOKENS WHERE USER_ID = ?")->execute([$parts[0]]);
        }
    }
    setcookie('ss_remember', '', time() - 3600, '/');
}

// ------------------------------------------------------------
// Log the current user out and destroy the session.
// By default also revokes the remember-me cookie/token.
// Pass false to keep remember-me intact (used internally on
// session timeout, where we want to retry remember-me login).
// ------------------------------------------------------------
function logoutUser(bool $clearRememberMe = true): void {
    if ($clearRememberMe) {
        clearRememberMeCookie();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// ------------------------------------------------------------
// Redirect helper -- always uses APP_URL as the base.
// ------------------------------------------------------------
function redirect(string $path): void {
    header('Location: ' . APP_URL . $path);
    exit;
}