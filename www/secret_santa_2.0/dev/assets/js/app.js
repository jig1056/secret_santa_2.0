// ============================================================
// app.js -- Secret Santa global JS
// ============================================================

// Mobile nav toggle
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('navToggle');
    const links  = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            links.classList.toggle('open');
        });
    }

    // Auto-dismiss alerts after 4 seconds
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });
});
