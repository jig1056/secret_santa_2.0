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
// Enforces session timeout. Redirects to login if not.
// ------------------------------------------------------------
function requireLogin(): void {
    if (empty($_SESSION['USER_ID'])) {
        redirect('/index.php');
    }

    // Timeout check
    if (!empty($_SESSION['LAST_ACTIVITY'])) {
        if ((time() - $_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT) {
            logoutUser();
            redirect('/index.php?reason=timeout');
        }
    }

    $_SESSION['LAST_ACTIVITY'] = time();
}

// ------------------------------------------------------------
// Check if the current user is an admin.
// Redirects to home if not.
// ------------------------------------------------------------
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['USER_TYPE'] ?? '') !== 'ADMIN') {
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
    return ($_SESSION['USER_TYPE'] ?? '') === 'ADMIN';
}

// ------------------------------------------------------------
// Log a user in by storing their info in the session.
// ------------------------------------------------------------
function loginUser(array $user): void {
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['USER_ID']    = $user['USER_ID'];
    $_SESSION['FIRST_NAME'] = $user['FIRST_NAME'];
    $_SESSION['LAST_NAME']  = $user['LAST_NAME'];
    $_SESSION['EMAIL']      = $user['EMAIL'];
    $_SESSION['USER_TYPE']  = $user['USER_TYPE'];
    $_SESSION['LAST_ACTIVITY'] = time();
}

// ------------------------------------------------------------
// Log the current user out and destroy the session.
// ------------------------------------------------------------
function logoutUser(): void {
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
