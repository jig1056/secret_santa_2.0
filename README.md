# Secret Santa 2.0

A private, self-hosted web app for managing a family or group Secret Santa gift exchange. Built with PHP 8.4, MySQL 8.0, and PHPMailer. Features user management, wish lists, automated Santa matching, email notifications, and an admin dashboard.

---

## Features

- **User roles** — Admin, Secret Santa participant, Wishlist Only (kids), Wishlist Gifter (parents)
- **Wish lists** — Users build and manage their own gift lists with links, notes, and budget hints
- **Santa matching** — Admin triggers randomized matching with configurable exclusion rules
- **Email notifications** — Transactional emails via Gmail SMTP (PHPMailer); Message Center for custom bulk emails with template placeholders
- **Christmas list sharing** — Email a formatted gift list to a gifter
- **Admin dashboard** — User management, config settings, message center, database backup
- **Secrets management** — All credentials pulled from a self-hosted [Infisical](https://infisical.com) instance at runtime; nothing hardcoded

---

## Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `curl`, `mbstring`, `openssl`
- MySQL 8.0+
- Composer
- A Gmail account with an [App Password](https://support.google.com/accounts/answer/185833) for SMTP
- A self-hosted [Infisical](https://infisical.com) instance (for secrets management)
- A web server — nginx or Apache

---

## Project Structure

```
secret_santa_2.0/
├── manconfig/
│   └── infisical.php          # Infisical secret fetcher (shared config — lives outside web root)
├── sql/
│   ├── secret_santa_create_database.sql   # Full schema + seed data (gitignored)
│   ├── migrate_v2.5.sql                   # v2.0 → v2.5 migration
│   └── *.sql                              # Incremental migrations (run in order)
├── www/secret_santa_2.0/dev/
│   ├── index.php              # Login page
│   ├── forgot_password.php
│   ├── reset_password.php
│   ├── includes/
│   │   ├── config.php         # Loads env file + Infisical secrets, defines constants
│   │   ├── db.php             # PDO singleton — call getDB()
│   │   ├── auth.php           # Session management, login, password reset
│   │   ├── mailer.php         # PHPMailer wrapper + email templates
│   │   ├── helpers.php        # Utility functions (h(), redirect(), getUserPref(), etc.)
│   │   ├── header.php
│   │   └── footer.php
│   ├── pages/
│   │   ├── home.php           # Dashboard / landing page after login
│   │   ├── gift_list.php      # User's own wish list
│   │   ├── giftee_list.php    # View your assigned giftee's wish list
│   │   ├── wishlists.php      # Email a formatted gift list
│   │   ├── profile.php        # Edit profile
│   │   └── set_pref.php       # AJAX endpoint for user preferences
│   ├── admin/
│   │   ├── dashboard.php      # Admin overview
│   │   ├── users.php          # User management (add, edit, roles)
│   │   ├── messages.php       # Message Center — template editor + bulk sender
│   │   ├── generate.php       # Run Santa matching
│   │   ├── config_admin.php   # App config settings (deadlines, URLs, etc.)
│   │   └── backup.php         # Download SQL backup
│   └── assets/
│       ├── css/style.css
│       └── js/app.js
└── .gitignore
```

---

## Setup

### 1. Clone the repo

```bash
git clone https://github.com/your-username/secret_santa_2.0.git
cd secret_santa_2.0
```

### 2. Set up Infisical

This app pulls all secrets (DB credentials, Gmail password, Twilio keys) from a self-hosted Infisical instance using Universal Auth.

1. Create a project in Infisical and add the following secrets:

| Key | Description |
|---|---|
| `HLDEV_MYSQL_DB_USER` | MySQL user for the dev database |
| `HLDEV_MYSQL_DB_PWD` | MySQL password for the dev database |
| `HLPRD_MYSQL_DB_USER` | MySQL user for the prod database |
| `HLPRD_MYSQL_DB_PWD` | MySQL password for the prod database |
| `SS_GMAIL_MAILER_PASSWORD` | Gmail App Password for sending email |
| `SS_TWILIO_ACCOUNT_SID` | Twilio Account SID (optional, for SMS) |
| `SS_TWILIO_AUTH_TOKEN` | Twilio Auth Token (optional, for SMS) |
| `SS_TWILIO_FROM_NUMBER` | Twilio From Number (optional, for SMS) |

2. Create a Machine Identity with Universal Auth and note the **Client ID** and **Client Secret**.

3. Create the Infisical env file at `/config/manconfig/.env_infisical` (outside the web root):

```ini
INFISICAL_HOST=https://your-infisical-host.com
INFISICAL_CLIENT_ID=your-client-id
INFISICAL_CLIENT_SECRET=your-client-secret
INFISICAL_PROJECT_ID=your-project-id
INFISICAL_ENVIRONMENT=dev
INFISICAL_SECRET_PATH=/
```

> The `manconfig/infisical.php` helper reads this file and fetches secrets on each session. It is deployed to `/config/manconfig/` on the server so it can be shared across multiple apps.

### 3. Create the env file for the app

Create `www/secret_santa_2.0/dev/secret_santa_env.conf` (this file is gitignored):

```ini
APP_ENV=dev
APP_URL=https://your-dev-domain.com
DB_HOST=your-db-host
DB_PORT=3306
DB_NAME=HLDEV
```

For production, deploy a matching file with `APP_ENV=prd`, `APP_URL`, and the prod database details.

### 4. Set up the database

Run the full schema script against your MySQL server:

```bash
mysql -u root -p HLDEV < sql/secret_santa_create_database.sql
```

If you're upgrading from v2.0, run the migrations in order instead:

```bash
mysql -u root -p HLDEV < sql/migrate_v2.5.sql
mysql -u root -p HLDEV < sql/migrate_role_id_to_varchar.sql
mysql -u root -p HLDEV < sql/migrate_config_id_to_varchar.sql
mysql -u root -p HLDEV < sql/migrate_message_id_to_varchar.sql
mysql -u root -p HLDEV < sql/migrate_message_id_continue.sql
mysql -u root -p HLDEV < sql/add_user_prefs_table.sql
mysql -u root -p HLDEV < sql/add_xmas_year_to_message_log.sql
mysql -u root -p HLDEV < sql/add_is_internal_to_messages.sql
mysql -u root -p HLDEV < sql/seed_role_descriptions.sql
```

### 5. Configure nginx (or Apache)

Point your server root to `www/secret_santa_2.0/dev/` and ensure PHP is enabled. A basic nginx block:

```nginx
server {
    listen 443 ssl;
    server_name your-dev-domain.com;

    root /path/to/www/secret_santa_2.0/dev;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. First login

Create your first admin user directly in the database:

```sql
-- Replace values as needed; password hash below is "changeme" via bcrypt
INSERT INTO SS_USERS (USER_ID, FIRST_NAME, LAST_NAME, EMAIL, PASSWORD_HASH, USER_TYPE, STATUS)
VALUES ('Admin_0001', 'Your', 'Name', 'you@example.com',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSWkbHpSG',
        'ADMIN', 'ACTIVE');

INSERT INTO SS_USER_ROLES (USER_ID, ROLE_ID)
SELECT 'Admin_0001', ROLE_ID FROM SS_ROLES WHERE ROLE_KEY IN ('admin', 'secret_santa');
```

Then log in and change your password immediately via Profile.

---

## User Roles

| Role | Description |
|---|---|
| **Admin** | Full access — users, messaging, matching, settings |
| **Secret Santa** | Participates in the gift draw; can view their giftee's wish list after matching |
| **Wishlist Only** | Builds a wish list but is not included in the draw (e.g. kids) |
| **Wishlist Gifter** | Views and purchases from assigned Wishlist Only users (e.g. parents) |

Users can hold multiple roles (e.g. Admin + Secret Santa).

---

## Email Template Placeholders

When composing messages in the Message Center, these placeholders are replaced per recipient:

| Placeholder | Value |
|---|---|
| `{FIRST_NAME}` | Recipient's first name |
| `{LAST_NAME}` | Recipient's last name |
| `{YEAR}` | Current Christmas year |
| `{GIFT_DEADLINE}` | Gift deadline from app config |
| `{SANTA_MATCH_DATE}` | Match reveal date from app config |
| `{PASSWORD_RESET_LINK}` | One-time password reset URL |
| `{RESET_EXPIRY_MINS}` | Token expiry in minutes |
| `{WEB_SITE_URL}` | App URL (from `APP_URL` env variable) |

---

## Deployment

The project uses rsync for deployment. A deploy script reads `.rsync-exclude` to skip dev-only files. Run from the project root:

```bash
rsync -avz --exclude-from=.rsync-exclude ./www/secret_santa_2.0/dev/ user@server:/var/www/secret_santa_2.0/dev/
```

---

## Security Notes

- All secrets are managed by Infisical — no credentials are hardcoded or committed to the repo
- The `secret_santa_env.conf` file is gitignored and must be deployed manually
- Database backups (downloadable from the admin panel) contain real user data — store them securely and never commit them
- The git history may contain old rotated credentials if the repo was previously private; run `git filter-repo` to scrub before making it public
- `display_errors` is automatically disabled in production (`APP_ENV=prd`)

---

## License

MIT — free to use and adapt for personal or family use.
