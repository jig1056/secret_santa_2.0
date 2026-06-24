-- ============================================================
-- migrate_config_id_to_varchar.sql
--
-- Makes CONFIG_KEY the primary key of SS_CONFIG,
-- removing the CONFIG_ID INT AUTO_INCREMENT column.
--
-- SS_CONFIG has NO child tables referencing CONFIG_ID,
-- so no FK propagation is needed — this is a single-table change.
--
-- Run on HLDEV first, verify, then run on HLPRD.
-- Take a backup before running: admin/backup.php
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop the INT primary key and its column
ALTER TABLE SS_CONFIG
    DROP PRIMARY KEY,
    DROP COLUMN CONFIG_ID;

-- Promote CONFIG_KEY to primary key
-- (the UNIQUE KEY on CONFIG_KEY is automatically dropped when
--  it becomes the PRIMARY KEY in MySQL)
ALTER TABLE SS_CONFIG
    ADD PRIMARY KEY (CONFIG_KEY);

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Verify:
-- SHOW CREATE TABLE SS_CONFIG;
-- SELECT CONFIG_KEY, CONFIG_VALUE FROM SS_CONFIG ORDER BY CONFIG_KEY;
-- ------------------------------------------------------------

-- Bump APP_VERSION to 2.7
UPDATE SS_CONFIG SET CONFIG_VALUE = '2.7', UPDATED_AT = NOW() WHERE CONFIG_KEY = 'APP_VERSION';
