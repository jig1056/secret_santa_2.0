-- Migration: add XMAS_YEAR to SS_MESSAGE_LOG
-- Run against HLDEV and HLPRD
-- Existing rows will have NULL (displayed as "—" in the UI)

ALTER TABLE SS_MESSAGE_LOG
    ADD COLUMN XMAS_YEAR VARCHAR(10) NULL AFTER STATUS;
