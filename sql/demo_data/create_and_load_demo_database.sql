-- ============================================================
-- Secret Santa 2.0 — Demo Data
-- ============================================================
-- Loads the full schema + realistic demo data so you can
-- explore the app right away.
--
-- All demo accounts use the password:  changeme
--
-- USERS AT A GLANCE:
--   AliceA_0001  alice@example.com   Admin + Secret Santa
--   BobB_0002    bob@example.com     Secret Santa
--   CarolC_0003  carol@example.com   Secret Santa
--   DavidD_0004  david@example.com   Secret Santa
--   EmmaE_0005   emma@example.com    Secret Santa
--   FrankF_0006  frank@example.com   Secret Santa
--   GraceK_0007  grace@example.com   Wishlist Only (kid)
--   HenryK_0008  henry@example.com   Wishlist Only (kid)
--   IsabelG_0009 isabel@example.com  Wishlist Gifter (parent)
--
-- HOW TO LOAD:
--   mysql -u root -p YOUR_DATABASE < sql/demo_data/demo_data.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- ============================================================
-- SS_USERS
-- Password hash = bcrypt("changeme", cost=12)
-- ============================================================
DROP TABLE IF EXISTS `SS_USERS`;
CREATE TABLE `SS_USERS` (
  `USER_ID`       varchar(20)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `FIRST_NAME`    varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `LAST_NAME`     varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `SEX`           enum('MALE','FEMALE') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EMAIL`         varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PASSWORD_HASH` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PHONE`         varchar(20)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `USER_TYPE`     enum('STANDARD','ADMIN') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'STANDARD',
  `STATUS`        enum('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `CREATED_AT`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `EMAIL` (`EMAIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All passwords: changeme
INSERT INTO `SS_USERS` (`USER_ID`, `FIRST_NAME`, `LAST_NAME`, `SEX`, `EMAIL`, `PASSWORD_HASH`, `PHONE`, `USER_TYPE`, `STATUS`, `CREATED_AT`, `UPDATED_AT`) VALUES
('AliceA_0001',  'Alice',  'Anderson', 'FEMALE', 'alice@example.com',  '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0001', 'ADMIN',    'ACTIVE', '2026-01-01 09:00:00', '2026-01-01 09:00:00'),
('BobB_0002',    'Bob',    'Baker',    'MALE',   'bob@example.com',    '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0002', 'STANDARD', 'ACTIVE', '2026-01-01 09:01:00', '2026-01-01 09:01:00'),
('CarolC_0003',  'Carol',  'Chen',     'FEMALE', 'carol@example.com',  '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0003', 'STANDARD', 'ACTIVE', '2026-01-01 09:02:00', '2026-01-01 09:02:00'),
('DavidD_0004',  'David',  'Davis',    'MALE',   'david@example.com',  '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0004', 'STANDARD', 'ACTIVE', '2026-01-01 09:03:00', '2026-01-01 09:03:00'),
('EmmaE_0005',   'Emma',   'Evans',    'FEMALE', 'emma@example.com',   '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0005', 'STANDARD', 'ACTIVE', '2026-01-01 09:04:00', '2026-01-01 09:04:00'),
('FrankF_0006',  'Frank',  'Foster',   'MALE',   'frank@example.com',  '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0006', 'STANDARD', 'ACTIVE', '2026-01-01 09:05:00', '2026-01-01 09:05:00'),
('GraceK_0007',  'Grace',  'Kim',      'FEMALE', 'grace@example.com',  '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', NULL,           'STANDARD', 'ACTIVE', '2026-01-01 09:06:00', '2026-01-01 09:06:00'),
('HenryK_0008',  'Henry',  'Kim',      'MALE',   'henry@example.com',  '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', NULL,           'STANDARD', 'ACTIVE', '2026-01-01 09:07:00', '2026-01-01 09:07:00'),
('IsabelG_0009', 'Isabel', 'Garcia',   'FEMALE', 'isabel@example.com', '$2y$12$p.8LMHcpXkSt3VPNpBCTEuEWpq0RjVPSSuMPkUVAhTOdQVJQ.ZtHu', '555-100-0009', 'STANDARD', 'ACTIVE', '2026-01-01 09:08:00', '2026-01-01 09:08:00');


-- ============================================================
-- SS_ROLES
-- ============================================================
DROP TABLE IF EXISTS `SS_ROLES`;
CREATE TABLE `SS_ROLES` (
  `ROLE_ID`    varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ROLE_NAME`  varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ROLE_DESC`  varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SORT_ORDER` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ROLE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_ROLES` (`ROLE_ID`, `ROLE_NAME`, `ROLE_DESC`, `SORT_ORDER`) VALUES
('all_roles',       'All Roles',       'Pseudo-role: message can be sent to any user. Never assigned to a user.',   0),
('admin',           'Admin',           'Full administrative access.',                                                1),
('secret_santa',    'Secret Santa',    'Participates in the Secret Santa gift exchange.',                            2),
('wishlist_only',   'Wishlist Only',   'Can manage their own wish list only. Not included in SS matches.',           3),
('wishlist_gifter', 'Wishlist Gifter', 'Can view and purchase from assigned Wishlist Only users.',                   4);


-- ============================================================
-- SS_USER_ROLES
-- ============================================================
DROP TABLE IF EXISTS `SS_USER_ROLES`;
CREATE TABLE `SS_USER_ROLES` (
  `USER_ID`     varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ROLE_ID`     varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ASSIGNED_AT` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`USER_ID`, `ROLE_ID`),
  KEY `FK_UR_ROLE` (`ROLE_ID`),
  CONSTRAINT `FK_UR_ROLE` FOREIGN KEY (`ROLE_ID`) REFERENCES `SS_ROLES` (`ROLE_ID`) ON DELETE CASCADE,
  CONSTRAINT `FK_UR_USER` FOREIGN KEY (`USER_ID`) REFERENCES `SS_USERS` (`USER_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_USER_ROLES` (`USER_ID`, `ROLE_ID`, `ASSIGNED_AT`) VALUES
('AliceA_0001',  'admin',           '2026-01-01 09:00:00'),
('AliceA_0001',  'secret_santa',    '2026-01-01 09:00:00'),
('AliceA_0001',  'wishlist_gifter', '2026-01-01 09:00:00'),
('BobB_0002',    'secret_santa',    '2026-01-01 09:01:00'),
('CarolC_0003',  'secret_santa',    '2026-01-01 09:02:00'),
('DavidD_0004',  'secret_santa',    '2026-01-01 09:03:00'),
('EmmaE_0005',   'secret_santa',    '2026-01-01 09:04:00'),
('FrankF_0006',  'secret_santa',    '2026-01-01 09:05:00'),
('GraceK_0007',  'wishlist_only',   '2026-01-01 09:06:00'),
('HenryK_0008',  'wishlist_only',   '2026-01-01 09:07:00'),
('IsabelG_0009', 'wishlist_gifter', '2026-01-01 09:08:00');


-- ============================================================
-- SS_CONFIG
-- ============================================================
DROP TABLE IF EXISTS `SS_CONFIG`;
CREATE TABLE `SS_CONFIG` (
  `CONFIG_KEY`         varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CONFIG_VALUE`       varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CONFIG_DESCRIPTION` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UPDATED_AT`         datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CONFIG_KEY`),
  UNIQUE KEY `CONFIG_KEY` (`CONFIG_KEY`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_CONFIG` (`CONFIG_KEY`, `CONFIG_VALUE`, `CONFIG_DESCRIPTION`, `UPDATED_AT`) VALUES
('APP_VERSION',              '2.7',                         'Current application version number.',                                                      NOW()),
('DEBUG_FLAG',               'false',                       'Set to "true" to enable debug output (dev only). Set to "false" in production.',            NOW()),
('EMAIL_FROM',               'santa@example.com',           'The "from" email address used when sending messages to users.',                             NOW()),
('GIFT_DEADLINE',            'Friday, December 12th',       'Gift purchase deadline. Used in message templates as {GIFT_DEADLINE}.',                     NOW()),
('HOME_MSG_SECRET_SANTA',    'Spread some holiday cheer!',  'Message shown on the home page for Secret Santa participants.',                             NOW()),
('HOME_MSG_WISHLIST_GIFTER', 'View and manage the wish lists of your loved ones!', 'Message shown for Wishlist Gifter role on home page.',              NOW()),
('HOME_MSG_WISHLIST_ONLY',   'Add your wish list items so your family knows what to get you!', 'Message shown for Wishlist Only role on home page.',   NOW()),
('MAIL_ENCRYPTION',          'tls',                         'SMTP encryption method: tls or ssl',                                                       NOW()),
('MAIL_FROM_EMAIL',          'santa@example.com',           'The From email address shown to recipients.',                                              NOW()),
('MAIL_FROM_NAME',           'Secret Santa - Chief Elf',    'The From name shown to recipients.',                                                       NOW()),
('MAIL_HOST',                'smtp.gmail.com',              'SMTP server hostname.',                                                                    NOW()),
('MAIL_PORT',                '587',                         'SMTP port (587 for TLS, 465 for SSL).',                                                    NOW()),
('MAIL_REPLY_TO',            'noreply@example.com',         'Reply-To address.',                                                                        NOW()),
('MAIL_SUBJECT',             'Secret Santa',                'Subject line prefix for outgoing emails.',                                                 NOW()),
('MAIL_USERNAME',            'santa@example.com',           'SMTP login username.',                                                                     NOW()),
('MESSAGE_LOG_DISPLAY_COUNT','25',                          'How many recent entries to show in the Message Center send log.',                           NOW()),
('RESET_TOKEN_EXPIRY_MINS',  '60',                          'How long a password reset link is valid, in minutes.',                                     NOW()),
('ROLE_DESC_ADMIN',          'Full access to all admin tools, user management, messaging, and app settings.',                                        NULL, NOW()),
('ROLE_DESC_SECRET_SANTA',   'Participates in the Secret Santa gift exchange and can view their assigned giftee''s wish list once matches are revealed.', NULL, NOW()),
('ROLE_DESC_WISHLIST_GIFTER','Can view wish lists of assigned Wishlist Only users, mark items as purchased, add items, and email the list. Intended for parents or grandparents.', NULL, NOW()),
('ROLE_DESC_WISHLIST_ONLY',  'Can build and manage their own wish list but is not included in the Secret Santa draw. Intended for kids or younger family members.', NULL, NOW()),
('SANTA_MATCH_DATE',         'November 15, 2026',           'Date Secret Santa assignments will be revealed. Used in templates as {SANTA_MATCH_DATE}.', NOW()),
('SITE_NAME',                'Secret Santa',                'The name of the site shown in the header and emails.',                                     NOW()),
('SITE_URL',                 'https://secretsanta.example.com', 'Site URL used in email messages.',                                                    NOW()),
('SMS_ENABLED',              'false',                       'Set to "true" to allow SMS messages. Set to "false" to disable.',                          NOW()),
('XMAS_YEAR',                '2026',                        'The year used for match generation and displayed throughout the site.',                     NOW());


-- ============================================================
-- SS_MESSAGES
-- ============================================================
DROP TABLE IF EXISTS `SS_MESSAGES`;
CREATE TABLE `SS_MESSAGES` (
  `MESSAGE_ID`   varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `MESSAGE_NAME` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MESSAGE_BODY` text         COLLATE utf8mb4_unicode_ci NOT NULL,
  `IS_INTERNAL`  tinyint(1)   NOT NULL DEFAULT '0',
  `CREATED_AT`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`MESSAGE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_MESSAGES` (`MESSAGE_ID`, `MESSAGE_NAME`, `MESSAGE_BODY`, `IS_INTERNAL`, `CREATED_AT`, `UPDATED_AT`) VALUES
('password_reset',          'Password Reset',          'Hi {FIRST_NAME},\r\n\r\nA password reset was requested for your Secret Santa {YEAR} account.\r\n\r\nClick the link below to set a new password. This link will expire in {RESET_TOKEN_EXPIRY_MINS} minutes.\r\n\r\n{PASSWORD_RESET_LINK}\r\n\r\nIf you did not request this, you can ignore this email.\r\n\r\n— Chief Elf', 1, NOW(), NOW()),
('ss_welcome_message',      'Welcome Message',         'Hi {FIRST_NAME}, welcome to Secret Santa {YEAR}!\r\n\r\nLog in to add your wish list and get ready for the holiday fun.\r\n\r\n{WEB_SITE_URL}', 0, NOW(), NOW()),
('ss_gift_reminder',        'Gift Reminder',           'Hi {FIRST_NAME},\r\n\r\nJust a reminder to add some gifts to your wish list before {GIFT_DEADLINE} so your Secret Santa knows what to get you!\r\n\r\nLog in and add your ideas:\r\n\r\n{WEB_SITE_URL}', 0, NOW(), NOW()),
('ss_santa_pairs_announced','Matches Announced',       'Hi {FIRST_NAME}, the Secret Santa matches are in!\r\n\r\nLog in to find out who you are gifting this year. Matching took place on {SANTA_MATCH_DATE}.\r\n\r\n{WEB_SITE_URL}', 0, NOW(), NOW()),
('wl_email_header',         'Wishlist Email Header',   'Here is {FIRST_NAME}''s {YEAR} wish list. Items marked as purchased show who has already claimed them — please coordinate with other gifters to avoid duplicates!', 1, NOW(), NOW()),
('wl_wish_giver_msg',       'Wishlist Gifter Reminder','Hi {FIRST_NAME},\r\n\r\nDon''t forget to check the wish list and pick up a gift before {GIFT_DEADLINE}!\r\n\r\n{WEB_SITE_URL}', 0, NOW(), NOW());


-- ============================================================
-- SS_MESSAGE_ROLES
-- ============================================================
DROP TABLE IF EXISTS `SS_MESSAGE_ROLES`;
CREATE TABLE `SS_MESSAGE_ROLES` (
  `ROLE_ID`    varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `MESSAGE_ID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`MESSAGE_ID`, `ROLE_ID`),
  KEY `FK_MR_ROLE` (`ROLE_ID`),
  CONSTRAINT `FK_MR_MESSAGE` FOREIGN KEY (`MESSAGE_ID`) REFERENCES `SS_MESSAGES`  (`MESSAGE_ID`) ON DELETE CASCADE,
  CONSTRAINT `FK_MR_ROLE`    FOREIGN KEY (`ROLE_ID`)    REFERENCES `SS_ROLES`     (`ROLE_ID`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_MESSAGE_ROLES` (`ROLE_ID`, `MESSAGE_ID`) VALUES
('admin',           'password_reset'),
('secret_santa',    'password_reset'),
('wishlist_only',   'password_reset'),
('wishlist_gifter', 'password_reset'),
('secret_santa',    'ss_welcome_message'),
('secret_santa',    'ss_gift_reminder'),
('secret_santa',    'ss_santa_pairs_announced'),
('wishlist_gifter', 'wl_email_header'),
('wishlist_gifter', 'wl_wish_giver_msg');


-- ============================================================
-- SS_GIFTS
-- ============================================================
DROP TABLE IF EXISTS `SS_GIFTS`;
CREATE TABLE `SS_GIFTS` (
  `GIFT_ID`      int          NOT NULL AUTO_INCREMENT,
  `USER_ID`      varchar(20)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `YEAR`         year         NOT NULL DEFAULT '2026',
  `NAME`         varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DESCRIPTION`  text         COLLATE utf8mb4_unicode_ci,
  `URL`          varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PURCHASED_BY` varchar(20)  COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `PURCHASED_AT` datetime     DEFAULT NULL,
  `CREATED_AT`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`GIFT_ID`),
  KEY `IDX_GIFTS_USER_YEAR` (`USER_ID`, `YEAR`),
  KEY `FK_GIFT_PURCHASER`   (`PURCHASED_BY`),
  CONSTRAINT `FK_GIFT_PURCHASER` FOREIGN KEY (`PURCHASED_BY`) REFERENCES `SS_USERS` (`USER_ID`) ON DELETE SET NULL,
  CONSTRAINT `FK_GIFT_USER`      FOREIGN KEY (`USER_ID`)      REFERENCES `SS_USERS` (`USER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_GIFTS` (`USER_ID`, `YEAR`, `NAME`, `DESCRIPTION`, `URL`, `PURCHASED_BY`, `PURCHASED_AT`, `CREATED_AT`, `UPDATED_AT`) VALUES
-- Alice's list
('AliceA_0001', '2026', 'Kindle Paperwhite',        'The newest model with warm light', 'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('AliceA_0001', '2026', 'Yoga Mat',                 'Extra thick, non-slip',             'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('AliceA_0001', '2026', 'Scented Candle Set',       'Vanilla and cedar scents',          NULL,                   NULL,           NULL,                 NOW(), NOW()),
-- Bob's list
('BobB_0002',   '2026', 'Wireless Earbuds',         'Noise cancelling preferred',        'https://bestbuy.com',  NULL,           NULL,                 NOW(), NOW()),
('BobB_0002',   '2026', 'Coffee Grinder',           'Burr grinder for espresso',         'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('BobB_0002',   '2026', 'hiking Boots',             'Size 11, waterproof',               'https://rei.com',      NULL,           NULL,                 NOW(), NOW()),
-- Carol's list
('CarolC_0003', '2026', 'Instant Pot',              '6-quart, 7-in-1',                   'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('CarolC_0003', '2026', 'Running Shoes',            'Size 8, blue or grey',              'https://nike.com',     NULL,           NULL,                 NOW(), NOW()),
-- David's list
('DavidD_0004', '2026', 'Mechanical Keyboard',      'TKL form factor, brown switches',   'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('DavidD_0004', '2026', 'Smart Watch',              'Samsung or Garmin',                 'https://bestbuy.com',  NULL,           NULL,                 NOW(), NOW()),
-- Emma's list
('EmmaE_0005',  '2026', 'Air Fryer',                '5.8-quart, black',                  'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('EmmaE_0005',  '2026', 'Book: Atomic Habits',      NULL,                                'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
-- Frank's list
('FrankF_0006', '2026', 'Camping Chair',            'Lightweight folding chair',         'https://rei.com',      NULL,           NULL,                 NOW(), NOW()),
('FrankF_0006', '2026', 'Portable Bluetooth Speaker', 'Waterproof, JBL or Anker',        'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
-- Grace's wish list (wishlist_only kid)
('GraceK_0007', '2026', 'LEGO Friends Set',         '41731 Heartlake International School', 'https://lego.com', 'AliceA_0001',  '2026-11-20 10:00:00', NOW(), NOW()),
('GraceK_0007', '2026', 'Art Supply Kit',           'Colored pencils and sketchbook',    'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('GraceK_0007', '2026', 'Roller Skates',            'Size 4, pink',                      NULL,                   NULL,           NULL,                 NOW(), NOW()),
-- Henry's wish list (wishlist_only kid)
('HenryK_0008', '2026', 'Nintendo Switch Game',     'Mario Kart 8 Deluxe',              'https://nintendo.com', 'IsabelG_0009', '2026-11-22 14:30:00', NOW(), NOW()),
('HenryK_0008', '2026', 'NERF Blaster',             'The big one',                       'https://amazon.com',   NULL,           NULL,                 NOW(), NOW()),
('HenryK_0008', '2026', 'Minecraft Lego Set',       'Any set over 500 pieces',           'https://lego.com',     NULL,           NULL,                 NOW(), NOW());


-- ============================================================
-- SS_MATCHES  (2026 Secret Santa ring: Alice→Bob→Carol→David→Emma→Frank→Alice)
-- ============================================================
DROP TABLE IF EXISTS `SS_MATCHES`;
CREATE TABLE `SS_MATCHES` (
  `MATCH_ID`        int         NOT NULL AUTO_INCREMENT,
  `GIVER_USER_ID`   varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `RECEIVER_USER_ID`varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `YEAR`            year        NOT NULL,
  `CREATED_AT`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MATCH_ID`),
  UNIQUE KEY `UQ_MATCH_GIVER_YEAR`    (`GIVER_USER_ID`,    `YEAR`),
  UNIQUE KEY `UQ_MATCH_RECEIVER_YEAR` (`RECEIVER_USER_ID`, `YEAR`),
  CONSTRAINT `FK_MATCH_GIVER`    FOREIGN KEY (`GIVER_USER_ID`)    REFERENCES `SS_USERS` (`USER_ID`),
  CONSTRAINT `FK_MATCH_RECEIVER` FOREIGN KEY (`RECEIVER_USER_ID`) REFERENCES `SS_USERS` (`USER_ID`),
  CONSTRAINT `CHK_NO_SELF_MATCH` CHECK (`GIVER_USER_ID` <> `RECEIVER_USER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_MATCHES` (`GIVER_USER_ID`, `RECEIVER_USER_ID`, `YEAR`, `CREATED_AT`) VALUES
('AliceA_0001', 'BobB_0002',   '2026', '2026-11-15 12:00:00'),
('BobB_0002',   'CarolC_0003', '2026', '2026-11-15 12:00:00'),
('CarolC_0003', 'DavidD_0004', '2026', '2026-11-15 12:00:00'),
('DavidD_0004', 'EmmaE_0005',  '2026', '2026-11-15 12:00:00'),
('EmmaE_0005',  'FrankF_0006', '2026', '2026-11-15 12:00:00'),
('FrankF_0006', 'AliceA_0001', '2026', '2026-11-15 12:00:00');


-- ============================================================
-- SS_WISHLIST_ACCESS  (Isabel and Alice both look after Grace and Henry)
-- ============================================================
DROP TABLE IF EXISTS `SS_WISHLIST_ACCESS`;
CREATE TABLE `SS_WISHLIST_ACCESS` (
  `GIFTER_USER_ID`   varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `WISHLIST_USER_ID` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ASSIGNED_AT`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`GIFTER_USER_ID`, `WISHLIST_USER_ID`),
  KEY `FK_WA_WISHLIST` (`WISHLIST_USER_ID`),
  CONSTRAINT `FK_WA_GIFTER`   FOREIGN KEY (`GIFTER_USER_ID`)   REFERENCES `SS_USERS` (`USER_ID`) ON DELETE CASCADE,
  CONSTRAINT `FK_WA_WISHLIST` FOREIGN KEY (`WISHLIST_USER_ID`) REFERENCES `SS_USERS` (`USER_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_WISHLIST_ACCESS` (`GIFTER_USER_ID`, `WISHLIST_USER_ID`, `ASSIGNED_AT`) VALUES
('AliceA_0001',  'GraceK_0007', '2026-01-01 09:00:00'),
('AliceA_0001',  'HenryK_0008', '2026-01-01 09:00:00'),
('IsabelG_0009', 'GraceK_0007', '2026-01-01 09:08:00'),
('IsabelG_0009', 'HenryK_0008', '2026-01-01 09:08:00');


-- ============================================================
-- SS_USER_PREFS
-- ============================================================
DROP TABLE IF EXISTS `SS_USER_PREFS`;
CREATE TABLE `SS_USER_PREFS` (
  `USER_ID`    varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PREF_KEY`   varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `XMAS_YEAR`  varchar(4)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `PREF_VALUE` varchar(100)COLLATE utf8mb4_unicode_ci NOT NULL,
  `UPDATED_AT` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`USER_ID`, `PREF_KEY`, `XMAS_YEAR`),
  KEY `IDX_UP_USER` (`USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (no demo prefs — users will set their own on first use)


-- ============================================================
-- SS_MESSAGE_LOG  (a few sample sent-email records)
-- ============================================================
DROP TABLE IF EXISTS `SS_MESSAGE_LOG`;
CREATE TABLE `SS_MESSAGE_LOG` (
  `LOG_ID`     int  NOT NULL AUTO_INCREMENT,
  `USER_ID`    varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CHANNEL`    enum('EMAIL','SMS','BOTH') COLLATE utf8mb4_unicode_ci NOT NULL,
  `STATUS`     enum('SENT','FAILED')      COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SENT',
  `XMAS_YEAR`  varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `SENT_AT`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `MESSAGE_ID` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`LOG_ID`),
  KEY `FK_LOG_USER`    (`USER_ID`),
  KEY `FK_LOG_MESSAGE` (`MESSAGE_ID`),
  CONSTRAINT `FK_LOG_MESSAGE` FOREIGN KEY (`MESSAGE_ID`) REFERENCES `SS_MESSAGES` (`MESSAGE_ID`) ON DELETE SET NULL,
  CONSTRAINT `FK_LOG_USER`    FOREIGN KEY (`USER_ID`)    REFERENCES `SS_USERS`    (`USER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `SS_MESSAGE_LOG` (`USER_ID`, `CHANNEL`, `STATUS`, `XMAS_YEAR`, `SENT_AT`, `MESSAGE_ID`) VALUES
('AliceA_0001',  'EMAIL', 'SENT', '2026', '2026-11-01 09:00:00', 'ss_welcome_message'),
('BobB_0002',    'EMAIL', 'SENT', '2026', '2026-11-01 09:01:00', 'ss_welcome_message'),
('CarolC_0003',  'EMAIL', 'SENT', '2026', '2026-11-01 09:02:00', 'ss_welcome_message'),
('DavidD_0004',  'EMAIL', 'SENT', '2026', '2026-11-01 09:03:00', 'ss_welcome_message'),
('EmmaE_0005',   'EMAIL', 'SENT', '2026', '2026-11-01 09:04:00', 'ss_welcome_message'),
('FrankF_0006',  'EMAIL', 'SENT', '2026', '2026-11-01 09:05:00', 'ss_welcome_message'),
('AliceA_0001',  'EMAIL', 'SENT', '2026', '2026-11-15 12:01:00', 'ss_santa_pairs_announced'),
('BobB_0002',    'EMAIL', 'SENT', '2026', '2026-11-15 12:02:00', 'ss_santa_pairs_announced'),
('CarolC_0003',  'EMAIL', 'SENT', '2026', '2026-11-15 12:03:00', 'ss_santa_pairs_announced'),
('DavidD_0004',  'EMAIL', 'SENT', '2026', '2026-11-15 12:04:00', 'ss_santa_pairs_announced'),
('EmmaE_0005',   'EMAIL', 'SENT', '2026', '2026-11-15 12:05:00', 'ss_santa_pairs_announced'),
('FrankF_0006',  'EMAIL', 'SENT', '2026', '2026-11-15 12:06:00', 'ss_santa_pairs_announced');


-- ============================================================
-- SS_PASSWORD_RESETS  (empty — no active reset tokens in demo)
-- ============================================================
DROP TABLE IF EXISTS `SS_PASSWORD_RESETS`;
CREATE TABLE `SS_PASSWORD_RESETS` (
  `RESET_ID`  int          NOT NULL AUTO_INCREMENT,
  `USER_ID`   varchar(20)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `TOKEN`     varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EXPIRES_AT`datetime     NOT NULL,
  `USED_AT`   datetime     DEFAULT NULL,
  PRIMARY KEY (`RESET_ID`),
  UNIQUE KEY `TOKEN` (`TOKEN`),
  KEY `FK_RESET_USER` (`USER_ID`),
  CONSTRAINT `FK_RESET_USER` FOREIGN KEY (`USER_ID`) REFERENCES `SS_USERS` (`USER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (no rows)


-- ============================================================
-- SS_REMEMBER_TOKENS  (empty — no active sessions in demo)
-- ============================================================
DROP TABLE IF EXISTS `SS_REMEMBER_TOKENS`;
CREATE TABLE `SS_REMEMBER_TOKENS` (
  `TOKEN_ID`  int          NOT NULL AUTO_INCREMENT,
  `USER_ID`   varchar(20)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `TOKEN_HASH`varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `EXPIRES_AT`datetime     NOT NULL,
  `CREATED_AT`datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TOKEN_ID`),
  KEY `FK_REMEMBER_USER` (`USER_ID`),
  CONSTRAINT `FK_REMEMBER_USER` FOREIGN KEY (`USER_ID`) REFERENCES `SS_USERS` (`USER_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (no rows)


SET FOREIGN_KEY_CHECKS=1;
-- End of demo data
