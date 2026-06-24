-- ============================================================
-- SECRET SANTA — MIGRATION v2.0 → v2.5
-- Run against: HLDEV (then HLPRD when ready)
-- Safe to run once only — uses IF NOT EXISTS / INSERT IGNORE
-- ============================================================


-- ------------------------------------------------------------
-- 1. SS_ROLES — role definitions
--    'all_roles' is a pseudo-role used on messages only;
--    it is never assigned to a user.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS SS_ROLES (
    ROLE_ID    INT          AUTO_INCREMENT PRIMARY KEY,
    ROLE_KEY   VARCHAR(50)  NOT NULL UNIQUE,
    ROLE_NAME  VARCHAR(100) NOT NULL,
    ROLE_DESC  VARCHAR(255) NULL,
    SORT_ORDER INT          NOT NULL DEFAULT 0
);

INSERT IGNORE INTO SS_ROLES (ROLE_KEY, ROLE_NAME, ROLE_DESC, SORT_ORDER) VALUES
('all_roles',       'All Roles',       'Pseudo-role: message can be sent to any user. Never assigned to a user.',   0),
('admin',           'Admin',           'Full administrative access.',                                                1),
('secret_santa',    'Secret Santa',    'Participates in the Secret Santa gift exchange.',                            2),
('wishlist_only',   'Wishlist Only',   'Can manage their own wish list only. Not included in SS matches.',           3),
('wishlist_gifter', 'Wishlist Gifter', 'Can view and purchase from assigned Wishlist Only users.',                   4);


-- ------------------------------------------------------------
-- 2. SS_USER_ROLES — many-to-many users ↔ roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS SS_USER_ROLES (
    USER_ID     VARCHAR(20) NOT NULL,
    ROLE_ID     INT         NOT NULL,
    ASSIGNED_AT DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (USER_ID, ROLE_ID),
    CONSTRAINT FK_UR_USER FOREIGN KEY (USER_ID) REFERENCES SS_USERS(USER_ID) ON DELETE CASCADE,
    CONSTRAINT FK_UR_ROLE FOREIGN KEY (ROLE_ID) REFERENCES SS_ROLES(ROLE_ID) ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- 3. Migrate existing users into SS_USER_ROLES
--    ADMIN     → admin + secret_santa  (admins participate too)
--    STANDARD  → secret_santa
-- ------------------------------------------------------------

-- ADMIN → admin role
INSERT IGNORE INTO SS_USER_ROLES (USER_ID, ROLE_ID)
SELECT u.USER_ID, r.ROLE_ID
FROM SS_USERS u
JOIN SS_ROLES r ON r.ROLE_KEY = 'admin'
WHERE u.USER_TYPE = 'ADMIN';

-- ADMIN → secret_santa role (admins participate in the exchange)
INSERT IGNORE INTO SS_USER_ROLES (USER_ID, ROLE_ID)
SELECT u.USER_ID, r.ROLE_ID
FROM SS_USERS u
JOIN SS_ROLES r ON r.ROLE_KEY = 'secret_santa'
WHERE u.USER_TYPE = 'ADMIN';

-- STANDARD → secret_santa role
INSERT IGNORE INTO SS_USER_ROLES (USER_ID, ROLE_ID)
SELECT u.USER_ID, r.ROLE_ID
FROM SS_USERS u
JOIN SS_ROLES r ON r.ROLE_KEY = 'secret_santa'
WHERE u.USER_TYPE = 'STANDARD';


-- ------------------------------------------------------------
-- 4. SS_WISHLIST_ACCESS
--    Admin-managed: which wishlist_gifter users can see
--    which wishlist_only users' lists.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS SS_WISHLIST_ACCESS (
    GIFTER_USER_ID   VARCHAR(20) NOT NULL,
    WISHLIST_USER_ID VARCHAR(20) NOT NULL,
    ASSIGNED_AT      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (GIFTER_USER_ID, WISHLIST_USER_ID),
    CONSTRAINT FK_WA_GIFTER   FOREIGN KEY (GIFTER_USER_ID)   REFERENCES SS_USERS(USER_ID) ON DELETE CASCADE,
    CONSTRAINT FK_WA_WISHLIST FOREIGN KEY (WISHLIST_USER_ID) REFERENCES SS_USERS(USER_ID) ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- 5. SS_MESSAGE_ROLES
--    Which roles a message is allowed to be sent to.
--    If 'all_roles' is present → no role restriction.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS SS_MESSAGE_ROLES (
    MESSAGE_ID INT NOT NULL,
    ROLE_ID    INT NOT NULL,
    PRIMARY KEY (MESSAGE_ID, ROLE_ID),
    CONSTRAINT FK_MR_MESSAGE FOREIGN KEY (MESSAGE_ID) REFERENCES SS_MESSAGES(MESSAGE_ID) ON DELETE CASCADE,
    CONSTRAINT FK_MR_ROLE    FOREIGN KEY (ROLE_ID)    REFERENCES SS_ROLES(ROLE_ID)       ON DELETE CASCADE
);

-- Assign 'all_roles' to all existing messages so nothing breaks
INSERT IGNORE INTO SS_MESSAGE_ROLES (MESSAGE_ID, ROLE_ID)
SELECT m.MESSAGE_ID, r.ROLE_ID
FROM SS_MESSAGES m
CROSS JOIN SS_ROLES r
WHERE r.ROLE_KEY = 'all_roles';


-- ------------------------------------------------------------
-- 6. SS_GIFTS — add purchased-by tracking
--    Gifters mark items purchased; wishlist_only user can't see it.
--    Run each ALTER only if the column doesn't already exist.
-- ------------------------------------------------------------
ALTER TABLE SS_GIFTS
    ADD COLUMN PURCHASED_BY VARCHAR(20) NULL DEFAULT NULL AFTER URL,
    ADD COLUMN PURCHASED_AT DATETIME    NULL DEFAULT NULL AFTER PURCHASED_BY,
    ADD CONSTRAINT FK_GIFT_PURCHASER
        FOREIGN KEY (PURCHASED_BY) REFERENCES SS_USERS(USER_ID) ON DELETE SET NULL;


-- ------------------------------------------------------------
-- 7. New SS_CONFIG keys
-- ------------------------------------------------------------
INSERT IGNORE INTO SS_CONFIG (CONFIG_KEY, CONFIG_VALUE) VALUES
('HOME_MSG_SECRET_SANTA',    'Spread some holiday cheer — $50 budget!'),
('HOME_MSG_WISHLIST_ONLY',   'Add your wish list items so your family knows what to get you!'),
('HOME_MSG_WISHLIST_GIFTER', 'View and manage the wish lists of your loved ones!'),
('APP_VERSION',              '2.5');


-- ------------------------------------------------------------
-- 8. Seed "Wishlist Email Header" message template
--    Used as the intro paragraph when a gifter emails a wish list.
--    Supports {FIRST_NAME}, {LAST_NAME}, {YEAR} placeholders.
-- ------------------------------------------------------------
INSERT IGNORE INTO SS_MESSAGES (MESSAGE_NAME, MESSAGE_BODY) VALUES
('Wishlist Email Header',
'Here is {FIRST_NAME}\'s {YEAR} wish list. Items marked as purchased show who has already claimed them — please coordinate with other gifters to avoid duplicates!');


-- ------------------------------------------------------------
-- DONE
-- USER_TYPE column is intentionally left on SS_USERS for now.
-- It can be dropped in a future migration once all code paths
-- read roles from SS_USER_ROLES instead.
-- ------------------------------------------------------------
