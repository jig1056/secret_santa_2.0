-- ============================================================
-- migrate_message_id_continue.sql
--
-- HLDEV RECOVERY — run this after migrate_message_id_to_varchar.sql
-- failed at Step 5 with:
--   "Duplicate entry 'secret_santa' for key 'SS_MESSAGE_ROLES.PRIMARY'"
--
-- State when this script starts:
--   SS_MESSAGES     — MESSAGE_ID dropped, NEW_MESSAGE_ID populated, NO PK
--   SS_MESSAGE_ROLES — MESSAGE_ID still present, NEW_MESSAGE_ID populated,
--                      PK=(MESSAGE_ID,ROLE_ID), FK_MR_MESSAGE already dropped
--   SS_MESSAGE_LOG  — MESSAGE_ID still present, NEW_MESSAGE_ID populated,
--                      FK_LOG_MESSAGE already dropped
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Step 5 (resumed): Drop old INT columns
-- SS_MESSAGE_ROLES has a composite PK (MESSAGE_ID, ROLE_ID) —
-- must drop the PK explicitly before dropping the column,
-- otherwise MySQL shrinks it to just (ROLE_ID) which fails
-- on duplicate ROLE_ID values.
-- ------------------------------------------------------------

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
-- Verify:
-- SELECT MESSAGE_ID, MESSAGE_NAME FROM SS_MESSAGES ORDER BY MESSAGE_ID;
-- SELECT * FROM SS_MESSAGE_ROLES;
-- SELECT MESSAGE_ID, USER_ID, STATUS FROM SS_MESSAGE_LOG LIMIT 20;
-- ------------------------------------------------------------
