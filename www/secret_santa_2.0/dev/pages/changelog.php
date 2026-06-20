<?php
// ============================================================
// changelog.php
// Version history and feature log.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">📋 What's New</h1>

<div class="changelog">

    <div class="cl-version">
        <div class="cl-version-header">
            <span class="cl-badge">v2.5</span>
            <span class="cl-version-title">Roles &amp; Wishlists</span>
            <span class="cl-version-date">2026</span>
        </div>
        <ul class="cl-items">
            <li>
                <strong>User Roles</strong> — Users can now have one or more roles: Admin, Secret Santa, Wishlist Only, and Wishlist Gifter. Each role controls what a person can see and do in the app.
            </li>
            <li>
                <strong>Wishlist Only</strong> — A new role for kids and younger family members. They can log in and build their own wish list without being included in the Secret Santa exchange. Their menu is simplified to just what they need.
            </li>
            <li>
                <strong>Wishlist Gifter</strong> — A new role for parents and grandparents. Gifters can view the wish lists of the Wishlist Only users assigned to them, mark items as purchased, add items to the list, and email the list to themselves.
            </li>
            <li>
                <strong>Purchase Tracking</strong> — Wishlist Gifters can mark items as purchased. The item shows who claimed it so gifters can coordinate. The Wishlist Only user never sees this information.
            </li>
            <li>
                <strong>Wishlist Access Management</strong> — Admins can control exactly which Wishlist Only users each Gifter has access to, right from the User Management page.
            </li>
            <li>
                <strong>Role-Based Messaging</strong> — Messages now have Allowed Roles, so a message can be restricted to specific roles. When sending, you can target by role, by individual users, or both. A user with multiple roles only receives the message once.
            </li>
            <li>
                <strong>Personalized Home Page</strong> — The home page now shows different content and a different greeting message depending on your role. The greeting messages are configurable by an admin in the Config page.
            </li>
            <li>
                <strong>New Dev URL</strong> — The dev site is now available at its own clean address instead of a subfolder path.
            </li>
            <li>
                <strong>Version Footer</strong> — The version number now appears in the footer of every page and links to this changelog.
            </li>
        </ul>
    </div>

    <div class="cl-version cl-version-older">
        <div class="cl-version-header">
            <span class="cl-badge cl-badge-old">v2.0</span>
            <span class="cl-version-title">Full Rebuild</span>
            <span class="cl-version-date">2025</span>
        </div>
        <ul class="cl-items">
            <li>Complete rewrite of the app from scratch with a cleaner design and better structure.</li>
            <li>User accounts with login, remember me, and password reset by email.</li>
            <li>Personal wish lists — add, edit, and delete gift ideas for your Secret Santa to browse.</li>
            <li>Secret Santa matching — the admin generates the matches and reveals them when ready.</li>
            <li>View your giftee's wish list once matches are announced.</li>
            <li>Admin tools: user management, match generation, message center, and app configuration.</li>
            <li>Message templates with placeholders, send by email or SMS, and a send log.</li>
            <li>Pronoun-aware copy throughout — set your pronoun preference in your profile.</li>
            <li>Secrets managed securely via a self-hosted Infisical vault — no passwords in config files.</li>
        </ul>
    </div>

</div>

<style>
.changelog { max-width: 780px; }

.cl-version {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.25rem;
    border-left: 5px solid #c0392b;
}
.cl-version-older { border-left-color: #ccc; }

.cl-version-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.1rem;
    flex-wrap: wrap;
}

.cl-badge {
    background: #c0392b;
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.25rem 0.7rem;
    border-radius: 20px;
    white-space: nowrap;
}
.cl-badge-old {
    background: #aaa;
}

.cl-version-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #212529;
}

.cl-version-date {
    font-size: 0.88rem;
    color: #999;
    margin-left: auto;
}

.cl-items {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.cl-items li {
    font-size: 0.95rem;
    color: #444;
    line-height: 1.55;
    padding-left: 1.25rem;
    position: relative;
}

.cl-items li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #c0392b;
    font-weight: 700;
}

.cl-version-older .cl-items li::before {
    color: #aaa;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
