// ============================================================
// app.js -- Secret Santa global JS
// ============================================================

// ------------------------------------------------------------
// Session auto-logout
// Reads the timeout (seconds) from the meta tag set in header.php.
// On every page load requireLogin() resets LAST_ACTIVITY, so the
// timer is always exactly SESSION_TIMEOUT seconds from now.
// When it fires it requests home.php — if remember-me is active
// the server silently re-logs them in; otherwise they're
// redirected to the login page.
// ------------------------------------------------------------
(function () {
    const appUrl      = (document.querySelector('meta[name="app-url"]')      || {}).content || '';
    const timeoutSecs = parseInt((document.querySelector('meta[name="session-timeout"]') || {}).content || '0', 10);
    if (timeoutSecs > 0) {
        setTimeout(function () {
            window.location.href = appUrl + '/pages/home.php';
        }, timeoutSecs * 1000);
    }
})();

// Mobile nav toggle
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('navToggle');
    const links  = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            links.classList.toggle('open');
        });
    }

    // Auto-dismiss alerts: success after 4s, errors after 30s
    document.querySelectorAll('.alert').forEach(function (el) {
        var delay = el.classList.contains('alert-error') ? 30000 : 4000;
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, delay);
    });
});
