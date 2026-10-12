-- 010_add_notice_issued_at.sql
-- Work Package FACULTY-WP1A: Academic-Support Notice Timeline Recording
--
-- Adds `notice_issued_at` DATETIME NULL to `support_case_referrals` to track
-- the exact moment faculty issued the academic-support notice to the student.
--
-- Rationale:
--   - `created_at` records when the referral was originally generated during
--     automated batch risk triage before faculty action.
--   - `updated_at` is volatile and updates on every row mutation (including
--     student acknowledgment).
--   - `notice_issued_at` provides an immutable timestamp of notice issuance,
--     shared identically between faculty "Issued" and student "Received" views.
--
-- Architecture & Data Integrity Invariants:
--   1. Single Nullable Column:
--      - Adds `notice_issued_at` DATETIME NULL DEFAULT NULL to `support_case_referrals`.
--      - Historical records remain NULL; no fabricated issuance timestamps.
--   2. Idempotent Schema Evolution:
--      - Stored procedure checks information_schema before altering.
--   3. Safety & Target Isolation:
--      - Prepared for testing on `udm_radar_scratch`.
--      - Do not execute automatically on production `udm_radar` without approval.
--
-- Apply:
--   mysql -u root udm_radar_scratch < database/migrations/010_add_notice_issued_at.sql
--
-- Rollback:
--   ALTER TABLE `support_case_referrals` DROP COLUMN IF EXISTS `notice_issued_at`;

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_010_add_notice_issued_at$$

CREATE PROCEDURE migrate_010_add_notice_issued_at()
BEGIN
    DECLARE v_col_exists INT DEFAULT 0;

    -- -------------------------------------------------------------------------
    -- 1. Pre-Migration Audit: support_case_referrals.notice_issued_at
    -- -------------------------------------------------------------------------
    SELECT COUNT(*) INTO v_col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_case_referrals'
      AND COLUMN_NAME = 'notice_issued_at';

    -- -------------------------------------------------------------------------
    -- 2. Schema Alteration: Add notice_issued_at column if missing
    -- -------------------------------------------------------------------------
    IF v_col_exists = 0 THEN
        ALTER TABLE `support_case_referrals`
        ADD COLUMN `notice_issued_at` DATETIME NULL DEFAULT NULL
        COMMENT 'Timestamp when faculty issued the academic notice to the student.'
        AFTER `reason_code`;
    ELSE
        ALTER TABLE `support_case_referrals`
        MODIFY COLUMN `notice_issued_at` DATETIME NULL DEFAULT NULL
        COMMENT 'Timestamp when faculty issued the academic notice to the student.';
    END IF;

END$$

DELIMITER ;

CALL migrate_010_add_notice_issued_at();
DROP PROCEDURE IF EXISTS migrate_010_add_notice_issued_at;
