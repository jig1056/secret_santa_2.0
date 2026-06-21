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
            <span class="cl-badge">v2.6</span>
            <span class="cl-version-title">Polished Messaging &amp; Admin Tools</span>
            <span class="cl-version-date">June 2026</span>
        </div>
        <ul class="cl-items">
            <li>
                <strong>HTML Email Branding</strong> — All outbound messages now use a styled HTML email template with a red header, clean body, and branded footer — matching the look of the wishlist email.
            </li>
            <li>
                <strong>Password Reset Email</strong> — The password reset email now uses the same HTML template, with a properly formatted clickable reset link. Fixed an encoding issue that caused garbled subject lines.
            </li>
            <li>
                <strong>Message Center Overhaul</strong> — Editing a message now hides the template list and log toggle to reduce clutter. The Send Message panel is hidden by default and revealed with a dedicated button. The edit form stays open after saving so you can keep making changes.
            </li>
            <li>
                <strong>Eligible Roles Grid</strong> — The Allowed Roles section on message templates now uses the same dynamic role grid as User Management — add roles from a dropdown with descriptions, remove with ×. Renamed to "Eligible Roles" throughout. The internal <em>all_roles</em> role is excluded.
            </li>
            <li>
                <strong>Smarter Send Panel</strong> — Send To is now a dropdown: choose "All eligible users" or "Select individuals." Individual mode shows only users with the message's eligible roles. Duplicate sends are prevented regardless of how many roles a user has.
            </li>
            <li>
                <strong>Send Log Improvements</strong> — The log is now hidden by default with a show/hide toggle. All log entries are fetched (no more 25-row cap) with 25-per-page pagination and a live search. A Secret Santa Year column now tracks which year each message was sent for.
            </li>
            <li>
                <strong>Quick Send from List</strong> — A 📤 Send button in the template list takes you directly to the Send panel. The Return button is context-aware — it goes back to the list or back to the edit form depending on how you got there.
            </li>
            <li>
                <strong>Internal System Use Flag</strong> — Message templates can now be marked as Internal System Use. When set, the Send button is hidden everywhere — useful for templates like Password Reset that are sent automatically by the system and should never be triggered manually.
            </li>
            <li>
                <strong>Config Admin Sorting &amp; Pagination</strong> — The config table now supports click-to-sort on Key and Value columns, with 10-per-page pagination, Prev/Next, and a View All option. Search interacts with sort and pagination.
            </li>
            <li>
                <strong>Error Alert Timing</strong> — Error alerts now stay visible for 30 seconds instead of 4, giving you time to read them before they fade.
            </li>
            <li>
                <strong>Session Fixes</strong> — Fixed PHP session warnings on the Forgot Password and Reset Password pages that appeared when a session was already active.
            </li>
        </ul>
    </div>

    <div class="cl-version cl-version-older">
        <div class="cl-version-header">
            <span class="cl-badge cl-badge-old">v2.5</span>
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
