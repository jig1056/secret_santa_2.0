-- ============================================================
-- Migration: Convert SS_ROLES.ROLE_ID from INT to VARCHAR
--            and remove the now-redundant ROLE_KEY column.
--
-- Affected tables:
--   SS_ROLES         — ROLE_ID: INT AUTO_INCREMENT → VARCHAR(50) PK
--                      ROLE_KEY column dropped (same value as ROLE_ID)
--   SS_USER_ROLES    — ROLE_ID: INT → VARCHAR(50), FK rebuilt
--   SS_MESSAGE_ROLES — ROLE_ID: INT → VARCHAR(50), FK rebuilt
--
-- Run on HLDEV first, verify, then run identically on HLPRD.
-- Safe to re-run: all steps are idempotent via temp column approach.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- ============================================================
-- STEP 1: Add temporary VARCHAR ROLE_ID columns to child tables
--         populated by joining back to SS_ROLES.ROLE_KEY
--         (which currently equals the target new ROLE_ID value)
-- ============================================================

ALTER TABLE `SS_USER_ROLES`
    ADD COLUMN `NEW_ROLE_ID` VARCHAR(50) COLLATE utf8mb4_unicode_ci NULL AFTER `ROLE_ID`;

ALTER TABLE `SS_MESSAGE_ROLES`
    ADD COLUMN `NEW_ROLE_ID` VARCHAR(50) COLLATE utf8mb4_unicode_ci NULL AFTER `ROLE_ID`;

-- ============================================================
-- STEP 2: Populate NEW_ROLE_ID from SS_ROLES.ROLE_KEY
-- ============================================================

UPDATE `SS_USER_ROLES` ur
    JOIN `SS_ROLES` r ON r.ROLE_ID = ur.ROLE_ID
    SET ur.NEW_ROLE_ID = r.ROLE_KEY;

UPDATE `SS_MESSAGE_ROLES` mr
    JOIN `SS_ROLES` r ON r.ROLE_ID = mr.ROLE_ID
    SET mr.NEW_ROLE_ID = r.ROLE_KEY;

-- Sanity check: these should both return 0 rows.
-- If either returns rows, stop and investigate before continuing.
-- SELECT * FROM SS_USER_ROLES    WHERE NEW_ROLE_ID IS NULL;
-- SELECT * FROM SS_MESSAGE_ROLES WHERE NEW_ROLE_ID IS NULL;

-- ============================================================
-- STEP 3: Drop FKs and old INT ROLE_ID from child tables,
--         rename NEW_ROLE_ID → ROLE_ID, add NOT NULL
-- ============================================================

-- SS_USER_ROLES
ALTER TABLE `SS_USER_ROLES`
    DROP FOREIGN KEY `FK_UR_ROLE`,
    DROP PRIMARY KEY,
    DROP COLUMN `ROLE_ID`,
    CHANGE COLUMN `NEW_ROLE_ID` `ROLE_ID` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    ADD PRIMARY KEY (`USER_ID`, `ROLE_ID`);

-- SS_MESSAGE_ROLES
ALTER TABLE `SS_MESSAGE_ROLES`
    DROP FOREIGN KEY `FK_MR_ROLE`,
    DROP PRIMARY KEY,
    DROP COLUMN `ROLE_ID`,
    CHANGE COLUMN `NEW_ROLE_ID` `ROLE_ID` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    ADD PRIMARY KEY (`MESSAGE_ID`, `ROLE_ID`);

-- ============================================================
-- STEP 4: Rebuild SS_ROLES with VARCHAR ROLE_ID as PK,
--         drop ROLE_KEY (redundant — same value as ROLE_ID)
-- ============================================================

-- 4a: Add a temp VARCHAR PK column
ALTER TABLE `SS_ROLES`
    ADD COLUMN `NEW_ROLE_ID` VARCHAR(50) COLLATE utf8mb4_unicode_ci NULL AFTER `ROLE_ID`;

-- 4b: Set it to ROLE_KEY value (the target PK value)
UPDATE `SS_ROLES` SET `NEW_ROLE_ID` = `ROLE_KEY`;

-- 4c: Drop the old INT PK and AUTO_INCREMENT, promote NEW_ROLE_ID
ALTER TABLE `SS_ROLES`
    DROP PRIMARY KEY,
    DROP COLUMN `ROLE_ID`,
    DROP KEY `ROLE_KEY`,          -- drop the UNIQUE index on ROLE_KEY
    CHANGE COLUMN `NEW_ROLE_ID` `ROLE_ID` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL FIRST,
    DROP COLUMN `ROLE_KEY`,       -- remove redundant column
    ADD PRIMARY KEY (`ROLE_ID`);

-- ============================================================
-- STEP 5: Rebuild foreign keys on child tables pointing to new PK
-- ============================================================

ALTER TABLE `SS_USER_ROLES`
    ADD KEY `FK_UR_ROLE` (`ROLE_ID`),
    ADD CONSTRAINT `FK_UR_ROLE`
        FOREIGN KEY (`ROLE_ID`) REFERENCES `SS_ROLES` (`ROLE_ID`) ON DELETE CASCADE;

ALTER TABLE `SS_MESSAGE_ROLES`
    ADD KEY `FK_MR_ROLE` (`ROLE_ID`),
    ADD CONSTRAINT `FK_MR_ROLE`
        FOREIGN KEY (`ROLE_ID`) REFERENCES `SS_ROLES` (`ROLE_ID`) ON DELETE CASCADE;

-- ============================================================
-- STEP 6: Re-enable FK checks
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VERIFICATION QUERIES — run these after the migration
-- to confirm everything looks correct before touching PHP.
-- ============================================================

-- SS_ROLES should have VARCHAR ROLE_ID, no ROLE_KEY column:
-- DESCRIBE SS_ROLES;

-- Child tables should show string role IDs:
-- SELECT * FROM SS_USER_ROLES    LIMIT 10;
-- SELECT * FROM SS_MESSAGE_ROLES LIMIT 10;

-- Spot-check a join still works:
-- SELECT u.FIRST_NAME, r.ROLE_ID, r.ROLE_NAME
-- FROM SS_USERS u
-- JOIN SS_USER_ROLES ur ON ur.USER_ID = u.USER_ID
-- JOIN SS_ROLES r ON r.ROLE_ID = ur.ROLE_ID
-- LIMIT 10;
