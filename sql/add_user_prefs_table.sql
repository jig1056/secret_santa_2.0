-- ============================================================
-- add_user_prefs_table.sql
--
-- Creates SS_USER_PREFS to store per-user, per-season UI
-- preferences (e.g. list vs grid view).
--
-- PK: (USER_ID, PREF_KEY, XMAS_YEAR) — one value per user
-- per preference per season. Automatically resets each new
-- season because the year is part of the key.
--
-- Run on HLDEV first, verify, then run on HLPRD.
-- ============================================================

CREATE TABLE IF NOT EXISTS SS_USER_PREFS (
    USER_ID    VARCHAR(50)  NOT NULL,
    PREF_KEY   VARCHAR(50)  NOT NULL,
    XMAS_YEAR  VARCHAR(4)   NOT NULL,
    PREF_VALUE VARCHAR(100) NOT NULL,
    UPDATED_AT DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (USER_ID, PREF_KEY, XMAS_YEAR),
    INDEX IDX_UP_USER (USER_ID)
);

-- ------------------------------------------------------------
-- Verify:
-- SHOW CREATE TABLE SS_USER_PREFS;
-- ------------------------------------------------------------
