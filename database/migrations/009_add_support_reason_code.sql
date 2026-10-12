-- 009_add_support_reason_code.sql
-- Work Package FACULTY-WP1A: Structured Academic-Support Reason Capture
--
-- Adds a structured, human-selected academic reason code to subject-level
-- support referrals:
--   - 'low_assessment_score'       — Low recorded assessment score
--   - 'missing_course_requirement' — Missing course requirement or deliverable
--   - 'performance_needs_review'   — Current subject performance needs review
--   - 'incomplete_grade_data'      — Incomplete or provisional grade information
--   - 'consultation_recommended'   — Faculty consultation recommended
--   - 'other_academic_concern'     — Other Faculty-observed academic concern
--
-- Architecture & Data Integrity Invariants:
--   1. Single Nullable Column:
--      - Adds `reason_code` VARCHAR(50) NULL DEFAULT NULL to `support_case_referrals`.
--      - Preserves legacy notices with NULL without historical rewriting or backfilling.
--      - No default reason is invented.
--   2. Domain Integrity:
--      - Adds table-level CHECK constraint `chk_referral_reason_valid` permitting NULL
--        or exact BINARY match against the six canonical reason keys.
--   3. Idempotent Schema Evolution:
--      - Uses stored procedure with information_schema check to safely apply or re-run.
--   4. Isolation & Safety:
--      - TARGET: udm_radar_scratch ONLY.
--      - DO NOT run against production `udm_radar`.
--
-- Apply:
--   mysql -u root udm_radar_scratch < database/migrations/009_add_support_reason_code.sql
--
-- Rollback:
--   ALTER TABLE `support_case_referrals` DROP CONSTRAINT IF EXISTS `chk_referral_reason_valid`;
--   ALTER TABLE `support_case_referrals` DROP COLUMN IF EXISTS `reason_code`;

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_009_add_support_reason_code$$

CREATE PROCEDURE migrate_009_add_support_reason_code()
BEGIN
    DECLARE v_col_exists INT DEFAULT 0;
    DECLARE v_invalid_count INT DEFAULT 0;

    -- -------------------------------------------------------------------------
    -- 1. Pre-Migration Audit: support_case_referrals
    -- -------------------------------------------------------------------------
    SELECT COUNT(*) INTO v_col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'support_case_referrals'
      AND COLUMN_NAME = 'reason_code';

    IF v_col_exists > 0 THEN
        -- Column already exists: verify that existing values conform to canonical domain or are NULL
        SELECT COUNT(*) INTO v_invalid_count
        FROM `support_case_referrals`
        WHERE `reason_code` IS NOT NULL
          AND BINARY `reason_code` NOT IN (
              'low_assessment_score',
              'missing_course_requirement',
              'performance_needs_review',
              'incomplete_grade_data',
              'consultation_recommended',
              'other_academic_concern'
          );

        IF v_invalid_count > 0 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'Migration 009 aborted: found support_case_referrals.reason_code values outside canonical domain.';
        END IF;
    END IF;

    -- -------------------------------------------------------------------------
    -- 2. Schema Alteration: Add reason_code column if missing
    -- -------------------------------------------------------------------------
    IF v_col_exists = 0 THEN
        ALTER TABLE `support_case_referrals`
        ADD COLUMN `reason_code` VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'Structured faculty-observed reason for support notice.'
        AFTER `latest_term_grade`;
    ELSE
        ALTER TABLE `support_case_referrals`
        MODIFY COLUMN `reason_code` VARCHAR(50) NULL DEFAULT NULL
        COMMENT 'Structured faculty-observed reason for support notice.';
    END IF;

    -- -------------------------------------------------------------------------
    -- 3. Constraint Enforcement: support_case_referrals
    -- -------------------------------------------------------------------------
    ALTER TABLE `support_case_referrals`
    DROP CONSTRAINT IF EXISTS `chk_referral_reason_valid`;

    ALTER TABLE `support_case_referrals`
    ADD CONSTRAINT `chk_referral_reason_valid`
    CHECK (
        `reason_code` IS NULL
        OR BINARY `reason_code` IN (
            'low_assessment_score',
            'missing_course_requirement',
            'performance_needs_review',
            'incomplete_grade_data',
            'consultation_recommended',
            'other_academic_concern'
        )
    );

END$$

DELIMITER ;

CALL migrate_009_add_support_reason_code();
DROP PROCEDURE IF EXISTS migrate_009_add_support_reason_code;
