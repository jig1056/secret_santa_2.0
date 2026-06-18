# Secret Santa 2.0 — Project Context

## Overview
A PHP/MySQL Secret Santa gift exchange web app. Users sign up, add items to a personal
wish list, get matched with another user to gift, and can view their giftee's wish list.
Admins manage users, generate matches, send bulk email messages, and configure app settings.

## Live Environment
- **URL (dev):** https://web-ace.nelsonone.com/secret_santa_2.0/dev/
- **URL (prd):** https://web-ace.nelsonone.com/secret_santa_2.0/prd/
- **Hosting:** Docker container named `nginxwebsrv`, accessed via `docker exec -it nginxwebsrv bash`
- **Server file root:** `/config/www/secret_santa_2.0/{dev,prd}/`
- **PHP version:** 8.4.14, Composer 2.9.2 available inside the container
- **Database:** MySQL/MariaDB at host `prod2.home`, port `3307`
  - Dev database: `HLDEV`
  - Prod database: `HLPRD`
  - All tables prefixed `SS_`, columns UPPERCASE

## Deployment Workflow
There is **no direct file sync** between this build environment and the live server.
Every file change must be:
1. Built/edited here
2. Downloaded by the user
3. Manually uploaded (FTP/file manager) to the correct path on the server
4. Any SQL files manually run against HLDEV and/or HLPRD via mysql client inside the container

This is slow and error-prone — **a major motivation for moving to Cowork** is to get
direct file access to the server and skip the manual upload step.

## Secrets Management — Infisical
- Self-hosted Infisical instance at **https://pwd.nelsonone.com**
- Auth method: **Universal Auth** (Client ID + Client Secret)
- All secrets in **one shared project** across all of Mark's apps
- Helper library lives at **`/config/manconfig/infisical.php`** (intentionally outside
  the web root, shared across multiple future apps, not just this one)
- Secrets currently stored in Infisical and consumed by this app:
  - `HLDEV_MYSQL_DB_USER`, `HLDEV_MYSQL_DB_PWD`
  - `HLPRD_MYSQL_DB_USER`, `HLPRD_MYSQL_DB_PWD`
  - `SS_GMAIL_MAILER_PASSWORD`
  - `SS_TWILIO_ACCOUNT_SID`, `SS_TWILIO_AUTH_TOKEN`, `SS_TWILIO_FROM_NUMBER` (SMS — see note below)
- **Caching strategy:** secrets are fetched from Infisical once per login and cached in
  `$_SESSION['_infisical']` (server-side only, never sent to the browser). This avoids
  hitting the Infisical API on every page load. Cache naturally refreshes on next login
  after the 30-minute session timeout.
- `infisical_get($name, $default)` and `infisical_get_many([$names])` are the two public
  functions. `config.php` calls `infisical_get_many()` once near the top and stores results
  in `$secrets`.

## Environment Configuration
Each environment folder has its **own** `secret_santa_env.conf` file (not shared):
```
/config/www/secret_santa_2.0/dev/secret_santa_env.conf
/config/www/secret_santa_2.0/prd/secret_santa_env.conf
```
Format:
```
APP_ENV=dev
DB_HOST=prod2.home
DB_PORT=3307
DB_NAME=HLDEV
```
(`prd` version has `APP_ENV=prd` and `DB_NAME=HLPRD`, same host/port).

`includes/config.php` reads this file via `__DIR__ . '/../secret_santa_env.conf'` (one
level up from `includes/`), derives the Infisical secret-key prefix from `APP_ENV`
(`dev` → `HLDEV_`, `prd` → `HLPRD_`), and defines constants: `APP_ENV`, `IS_DEV`, `IS_PRD`,
`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`, `DB_CHARSET`, `APP_NAME`, `APP_URL`,
`SESSION_NAME`, `SESSION_TIMEOUT` (1800s), `REMEMBER_ME_DAYS` (60), `MAIL_PASSWORD_SECRET`,
`PASSWORD_RESET_EXPIRY_FALLBACK` (60 min).

**Important quirk:** MySQL host must be `prod2.home` over **TCP**, not `localhost` — using
`localhost` makes PHP try a Unix socket which doesn't exist inside the Docker container and
throws `SQLSTATE[HY000] [2002] No such file or directory`.

## Database Schema (tables, all prefixed `SS_`)
- **SS_USERS** — `USER_ID` (format `FirstNameLastInitial_NNNN`, e.g. `ChandaW_4782`),
  `FIRST_NAME`, `LAST_NAME`, `SEX` (ENUM 'MALE'/'FEMALE', nullable — used for pronoun
  personalization), `EMAIL`, `PASSWORD_HASH`, `PHONE`, `USER_TYPE` (STANDARD/ADMIN),
  `STATUS` (ACTIVE/INACTIVE)
- **SS_GIFTS** — wish list items, scoped per season via `YEAR` column. All gift queries
  (add/edit/delete/list) filter by `YEAR = getConfig('XMAS_YEAR')` so prior years' data
  persists untouched when a new season starts.
- **SS_MATCHES** — Secret Santa pairings, also scoped by `YEAR`. `GIVER_USER_ID` →
  `RECEIVER_USER_ID`. Generated via proper derangement algorithm (no self-matches).
- **SS_MESSAGES** — reusable message/email templates (name + body with placeholders)
- **SS_MESSAGE_LOG** — send history (message, recipient, channel, status, timestamp)
- **SS_CONFIG** — key/value app settings table, `CONFIG_KEY` / `CONFIG_VALUE` /
  `CONFIG_DESCRIPTION` (description is editable in the admin UI)
- **SS_PASSWORD_RESETS** — `USER_ID`, `TOKEN`, `EXPIRES_AT` for forgot-password flow
- **SS_REMEMBER_TOKENS** — `USER_ID`, `TOKEN_HASH` (sha256), `EXPIRES_AT` for the
  60-day "Remember me" persistent login cookie

### Known SS_CONFIG keys currently in use
| Key | Purpose |
|---|---|
| `XMAS_YEAR` | Current season year — gates which YEAR-scoped gifts/matches are shown |
| `GIFT_DEADLINE` | Display text, used as `{GIFT_DEADLINE}` placeholder in messages |
| `SANTA_MATCH_DATE` | Display text, used as `{SANTA_MATCH_DATE}` placeholder |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME` | SMTP settings (password comes from Infisical, not this table) |
| `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`, `MAIL_REPLY_TO` | Email sender identity |
| `MAIL_SUBJECT` | Subject line prefix for outgoing emails (kept separate from `MAIL_FROM_NAME` — see bug history below) |
| `MESSAGE_LOG_DISPLAY_COUNT` | How many rows to show in the Message Center send log (default 20) |
| `RESET_TOKEN_EXPIRY_MINS` | Password reset link validity window (default 60) |
| `SMS_ENABLED` | Toggle for SMS sending (currently functionally blocked — see below) |

## File Structure
```
{dev,prd}/
├── index.php                  # Login page
├── forgot_password.php        # Request password reset email
├── reset_password.php         # Set new password via emailed token
├── logout.php
├── favicon.ico
├── secret_santa_env.conf      # Per-environment config (NOT shared between dev/prd)
├── includes/
│   ├── config.php             # Env + Infisical + constants bootstrap
│   ├── db.php                 # PDO connection (getDB())
│   ├── auth.php               # Session mgmt, login/logout, remember-me, requireLogin/requireAdmin
│   ├── helpers.php            # h(), getConfig(), generateUserId(), matchesGenerated(),
│   │                           getMatchForUser(), pronoun()
│   ├── header.php             # Shared <head> + nav (includes favicon links)
│   ├── footer.php             # Shared footer + auto "Return to Home" button (skipped on home.php)
│   └── mailer.php             # PHPMailer wrapper: sendMail(), sendPasswordReset(),
│                                 sendSMS() [Twilio, currently shelved], formatE164()
├── pages/
│   ├── home.php                # Dashboard: wish list status, Secret Santa match status
│   ├── gift_list.php           # "My Wish List" — add/edit/delete own gifts
│   ├── giftee_list.php         # View matched giftee's wish list (pronoun-aware copy)
│   └── profile.php             # Edit own name/email/phone/password
├── admin/
│   ├── users.php               # User CRUD, separate add/edit forms, SEX dropdown,
│   │                              Reset PW button (emails user directly via sendPasswordReset)
│   ├── dashboard.php           # Stats: user/gift counts (year-scoped), match status
│   ├── generate.php            # Run match generation (derangement algo), reveal/hide, re-generate
│   ├── messages.php            # Template CRUD, send Email/SMS/Both, send log + Clear Log button
│   └── config_admin.php        # Key/value config editor, search box, collapsed
│                                  "Initialize New Season" section (requires typing "YES")
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── images/ (favicon-16.png, favicon-32.png, apple-touch-icon.png, img_gift01.png)
└── vendor/                     # Composer: phpmailer/phpmailer, twilio/sdk
```

**Note:** `admin/config.php` was deliberately renamed to `admin/config_admin.php`
because the user already has an unrelated `config.php` naming convention elsewhere —
all internal references (nav link in header.php, self-referencing links, form actions)
were updated to match.

## Authentication & Sessions
- Session name: `ss_session`, 30-minute idle timeout (`SESSION_TIMEOUT`)
- `requireLogin()` / `requireAdmin()` guard pages
- **Remember Me (60 days):** checkbox on login page. On success, stores a random token's
  SHA-256 hash in `SS_REMEMBER_TOKENS` and sets a secure httponly cookie `ss_remember`
  containing `userId:rawToken`. On session timeout, `requireLogin()` automatically attempts
  `attemptRememberMeLogin()` before forcing a redirect to the login page. Explicit logout
  always revokes the remember-me token (`logoutUser(true)`); session-timeout-triggered
  internal calls use `logoutUser(false)` to preserve remember-me for the retry.
- `loginUser($user, $rememberMe = false)` regenerates the session ID (fixation protection)
  and optionally sets the remember-me cookie.
- **Known fixed bug:** `session_regenerate_id()` must not be called unless a session is
  active — guard with `if (session_status() === PHP_SESSION_NONE) session_start();` first,
  otherwise PHP throws a (non-fatal) warning. This bit us once in `attemptRememberMeLogin()`.

## Email (PHPMailer)
- Installed via Composer inside the container: `composer require phpmailer/phpmailer`
  (v7.1.1), `vendor/` copied from `dev/` to `prd/` manually (not committed/shared automatically)
- Gmail SMTP, password fetched from Infisical (`SS_GMAIL_MAILER_PASSWORD`), other settings
  (`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_FROM_EMAIL`,
  `MAIL_FROM_NAME`, `MAIL_REPLY_TO`) in `SS_CONFIG`, editable from the Config page
- `sendMail($to, $toName, $subject, $body)` — core send function
- `sendPasswordReset($user, $pdo)` — generates reset token, loads the "Password Reset"
  message template from `SS_MESSAGES`, substitutes placeholders, sends via `sendMail()`.
  Used by BOTH the user-facing forgot-password flow AND the admin's "Reset PW" button in
  User Management — single source of truth, no duplicated logic.
- **Bug history (fixed):** subject lines were accidentally built from `MAIL_FROM_NAME`
  (e.g. "Secret Santa - Chief Elf"), which leaked the sender display name into the email
  subject. Fixed by introducing a dedicated `MAIL_SUBJECT` config key, used only for
  subject-line construction, fully decoupled from the From-name/Reply-To identity.

### Message template placeholders (used in SS_MESSAGES.MESSAGE_BODY)
`{FIRST_NAME}`, `{LAST_NAME}`, `{YEAR}`, `{GIFT_DEADLINE}`, `{SANTA_MATCH_DATE}`,
`{PASSWORD_RESET_LINK}` (renamed from earlier `{RESET_LINK}` — note older saved templates
may still reference the old name and would need editing), `{RESET_EXPIRY_MINS}`.
The "Password Reset" template name is special-cased and looked up by name in
`sendPasswordReset()` — don't rename that template without updating the code.

## SMS (Twilio) — Currently Shelved
- Code is fully built: `sendSMS()` and `formatE164()` in `mailer.php`, wired into
  `admin/messages.php`'s send loop (channel = SMS or BOTH), Twilio SDK installed via
  Composer (`composer require twilio/sdk`)
- **Blocked:** Twilio requires A2P 10DLC business/campaign registration before SMS will
  actually deliver to US numbers. User has NOT completed this registration — sends are
  currently rejected by Twilio. No code changes needed once registration clears; it should
  just start working.
- Numbers are auto-formatted to E.164 (`+1XXXXXXXXXX`) via `formatE164()` — no separate
  "carrier" or formatted-number field was added to `SS_USERS`; phone numbers stay in
  whatever format the user typed and get normalized at send time.

## Personalization — Pronoun Helper
`SS_USERS.SEX` (MALE/FEMALE/null) feeds `pronoun($sex, $case)` in `helpers.php`
(`$case` = `subject`/`object`/`possessive`, defaults to they/them/their if null/unset).
Used on `giftee_list.php` and `home.php` to say "his/her wish list", "surprise him/her",
etc. instead of generic "their". Editable per-user via the Sex dropdown in
`admin/users.php` add/edit forms.

## Visual / Branding Notes
- Santa emoji 🎅🏾 (medium-dark skin tone) used throughout: login page, nav brand, home
  page banner icon. Nav brand version is scaled up via `.santa-emoji { font-size: 3.06em }`
  in style.css (originally 175%, then bumped another 75% on top of that per explicit request)
- **Favicon:** replaced a simple PIL-drawn icon with a higher-quality image extracted from
  a user-uploaded Gemini-generated SVG (which had a checkerboard-transparency PNG baked
  into raw pixels, not real alpha — required custom Python/PIL processing to crop and
  flatten onto white before resizing to 16/32/48/180px). Files live at root `favicon.ico`
  plus `assets/images/favicon-{16,32}.png` and `apple-touch-icon.png`, referenced via
  `<link>` tags in the `<head>` of `header.php`, `index.php`, `forgot_password.php`, and
  `reset_password.php` (the four pages that each define their own `<head>` rather than
  including `header.php`).
- **Known gotcha:** `header.php` got reverted to a stale version partway through the
  project (missing the favicon `<link>` tags) when an unrelated edit was uploaded out of
  order. If favicons ever "disappear" again, check that `includes/header.php` on the
  server actually contains the `<link rel="icon"...>` block — view-source the page and
  search for "favicon" to confirm.
- "My Gift List" was renamed to "My Wish List" everywhere (nav, page titles, button text,
  home page status card) per explicit request — if extending the app, keep this naming
  consistent rather than reverting to "Gift List".
- Every page (except `home.php` itself) gets an automatic "🏠 Return to Home" button
  injected by `footer.php` — don't add a second one manually on individual pages.

## UI/UX Conventions Established During Build
- **Forms close on success, stay open on validation error.** This required two related
  fixes applied consistently across `gift_list.php`, `users.php`, `messages.php`,
  `config_admin.php`: (1) on error, reload the editing record and re-render the form;
  on success, leave the editing variable null so the form collapses; (2) — the subtler
  bug — a lingering `?edit=ID` GET parameter in the URL would silently reopen the form
  after a successful POST/redirect-less reload, fixed by adding `&& $msgType !== 'success'`
  to the GET-param edit-detection check.
- **Add vs Edit are separate, non-toggled forms** (especially in `users.php`) — clicking
  "Add New" should ALWAYS show a blank form via `?add=1`, never reuse stale edit-mode state
  via JS toggle. Cancel buttons link to a clean URL rather than calling a JS hide function.
- Destructive admin actions (Clear Log, Initialize Season) require explicit confirmation —
  either a JS `confirm()` dialog or, for the riskiest action (Initialize Season), typing
  the literal word "YES" into a text field before the submit button enables.
- No bullet-point lists or excess bolding in the app's own UI copy — keep tone direct,
  e.g. "View His Wish List" not "→ View Wish List ←".

## Outstanding / Possible Next Steps
- Twilio SMS will start working automatically once A2P 10DLC registration completes —
  no code changes anticipated, but worth a smoke test when that happens.
- No automated tests exist; all verification has been manual (visual checks + the user
  testing live on web-ace.nelsonone.com).
- This project has been built entirely via manual file download/upload between this
  chat environment and the live server — direct file system access via Cowork should
  significantly speed up future iteration.
