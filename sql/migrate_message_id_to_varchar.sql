-- ============================================================
-- migrate_message_id_to_varchar.sql
--
-- Changes SS_MESSAGES.MESSAGE_ID from INT AUTO_INCREMENT
-- to VARCHAR(50) using stable string keys.
--
-- Child tables updated: SS_MESSAGE_ROLES, SS_MESSAGE_LOG
--
-- ID mapping (INT → VARCHAR):
--   1  → ss_welcome_message
--   2  → ss_santa_pairs_announced
--   3  → ss_gift_reminder
--   8  → password_reset
--   10 → wl_email_header
--   12 → wl_wish_giver_msg
--
-- Run on HLDEV first, verify, then run on HLPRD.
-- Take a backup before running: admin/backup.php
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Step 1: Add temporary VARCHAR columns to hold new IDs
-- ------------------------------------------------------------

ALTER TABLE SS_MESSAGES
    ADD COLUMN NEW_MESSAGE_ID VARCHAR(50) NULL AFTER MESSAGE_ID;

ALTER TABLE SS_MESSAGE_ROLES
    ADD COLUMN NEW_MESSAGE_ID VARCHAR(50) NULL;

ALTER TABLE SS_MESSAGE_LOG
    ADD COLUMN NEW_MESSAGE_ID VARCHAR(50) NULL;

-- ------------------------------------------------------------
-- Step 2: Populate NEW_MESSAGE_ID in SS_MESSAGES
-- If any unknown INT IDs exist they get a fallback like 'message_99'
-- ------------------------------------------------------------

UPDATE SS_MESSAGES SET NEW_MESSAGE_ID = CASE MESSAGE_ID
    WHEN 1  THEN 'ss_welcome_message'
    WHEN 2  THEN 'ss_santa_pairs_announced'
    WHEN 3  THEN 'ss_gift_reminder'
    WHEN 8  THEN 'password_reset'
    WHEN 10 THEN 'wl_email_header'
    WHEN 12 THEN 'wl_wish_giver_msg'
    ELSE CONCAT('message_', MESSAGE_ID)
END;

-- ------------------------------------------------------------
-- Step 3: Propagate to child tables via JOIN
-- ------------------------------------------------------------

UPDATE SS_MESSAGE_ROLES mr
JOIN SS_MESSAGES m ON m.MESSAGE_ID = mr.MESSAGE_ID
SET mr.NEW_MESSAGE_ID = m.NEW_MESSAGE_ID;

UPDATE SS_MESSAGE_LOG ml
JOIN SS_MESSAGES m ON m.MESSAGE_ID = ml.MESSAGE_ID
SET ml.NEW_MESSAGE_ID = m.NEW_MESSAGE_ID;

-- Verify propagation before proceeding:
-- SELECT 'SS_MESSAGES'     AS tbl, COUNT(*) AS total, SUM(NEW_MESSAGE_ID IS NULL) AS nulls FROM SS_MESSAGES;
-- SELECT 'SS_MESSAGE_ROLES' AS tbl, COUNT(*) AS total, SUM(NEW_MESSAGE_ID IS NULL) AS nulls FROM SS_MESSAGE_ROLES;
-- SELECT 'SS_MESSAGE_LOG'  AS tbl, COUNT(*) AS total, SUM(NEW_MESSAGE_ID IS NULL) AS nulls FROM SS_MESSAGE_LOG;

-- ------------------------------------------------------------
-- Step 4: Drop foreign keys that reference SS_MESSAGES.MESSAGE_ID
-- FK_MR_MESSAGE: SS_MESSAGE_ROLES.MESSAGE_ID → SS_MESSAGES.MESSAGE_ID
-- FK_LOG_MESSAGE: SS_MESSAGE_LOG.MESSAGE_ID  → SS_MESSAGES.MESSAGE_ID
-- ------------------------------------------------------------

ALTER TABLE SS_MESSAGE_ROLES DROP FOREIGN KEY FK_MR_MESSAGE;
ALTER TABLE SS_MESSAGE_LOG   DROP FOREIGN KEY FK_LOG_MESSAGE;

-- ------------------------------------------------------------
-- Step 5: Drop the old INT MESSAGE_ID columns
-- ------------------------------------------------------------

ALTER TABLE SS_MESSAGES
    DROP PRIMARY KEY,
    DROP COLUMN MESSAGE_ID;

-- SS_MESSAGE_ROLES has a composite PK (MESSAGE_ID, ROLE_ID).
-- Must drop the PK explicitly — otherwise MySQL shrinks it to just
-- (ROLE_ID) which immediately fails on duplicate ROLE_ID values.
ALTER TABLE SS_MESSAGE_ROLES
    DROP PRIMARY KEY,
    DROP COLUMN MESSAGE_ID;

ALTER TABLE SS_MESSAGE_LOG
    DROP COLUMN MESSAGE_ID;

-- ------------------------------------------------------------
-- Step 6: Rename NEW_MESSAGE_ID → MESSAGE_ID on all tables
-- ------------------------------------------------------------

ALTER TABLE SS_MESSAGES
    CHANGE NEW_MESSAGE_ID MESSAGE_ID VARCHAR(50) NOT NULL;

ALTER TABLE SS_MESSAGE_ROLES
    CHANGE NEW_MESSAGE_ID MESSAGE_ID VARCHAR(50) NOT NULL;

ALTER TABLE SS_MESSAGE_LOG
    CHANGE NEW_MESSAGE_ID MESSAGE_ID VARCHAR(50) NULL;

-- ------------------------------------------------------------
-- Step 7: Rebuild primary keys
-- ------------------------------------------------------------

ALTER TABLE SS_MESSAGES
    ADD PRIMARY KEY (MESSAGE_ID);

ALTER TABLE SS_MESSAGE_ROLES
    ADD PRIMARY KEY (MESSAGE_ID, ROLE_ID);

-- ------------------------------------------------------------
-- Step 8: Rebuild foreign keys
-- ------------------------------------------------------------

ALTER TABLE SS_MESSAGE_ROLES
    ADD CONSTRAINT FK_MR_MESSAGE
    FOREIGN KEY (MESSAGE_ID) REFERENCES SS_MESSAGES (MESSAGE_ID) ON DELETE CASCADE;

ALTER TABLE SS_MESSAGE_LOG
    ADD CONSTRAINT FK_LOG_MESSAGE
    FOREIGN KEY (MESSAGE_ID) REFERENCES SS_MESSAGES (MESSAGE_ID) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Verify final state:
-- SELECT MESSAGE_ID, MESSAGE_NAME FROM SS_MESSAGES ORDER BY MESSAGE_ID;
-- SELECT * FROM SS_MESSAGE_ROLES LIMIT 20;
-- SELECT MESSAGE_ID, USER_ID, STATUS FROM SS_MESSAGE_LOG LIMIT 20;
-- ------------------------------------------------------------
