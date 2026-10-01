-- 007_harmonize_prediction_source.sql
-- Work Package 5: Harmonize prediction_source provenance contract and constraints
--
-- Harmonizes the stored prediction provenance vocabulary across the database:
--   - 'decision_tree'        — Machine learning model projection (Decision Tree)
--   - 'heuristic'            — Preserved legacy heuristic provenance (baseline 50/50 blend)
--   - 'calculation_fallback' — Deterministic calculation fallback from current grades
--
-- Architecture & Data Integrity Design:
--   1. Pre-constraint data audit:
--      - If predictions.prediction_source already exists, audits all existing records.
--      - Audits academic_support_cases.prediction_source records.
--      - Aborts with SQLSTATE 45000 before any DDL if unexpected values, blanks, or NULLs exist.
--   2. Explicit Provenance & Elimination of Permanent Defaults:
--      - Existing baseline rows (405 rows) are explicitly populated with 'heuristic'.
--      - The column is defined as VARCHAR(30) NOT NULL with NO permanent default.
--      - Any future INSERT that omits prediction_source is strictly REJECTED by MariaDB
--        (violating chk_predictions_source_valid in non-strict mode, and Error 1364 in strict mode).
--   3. Idempotent schema evolution:
--      - Safely adds or standardizes column definition without data loss.
--      - Drops default immediately after populating existing rows.
--   4. Hardened domain integrity:
--      - Adds table-level CHECK constraint chk_predictions_source_valid using BINARY exact comparison.
--      - Adds table-level CHECK constraint chk_support_cases_source_valid on academic_support_cases.
--      - Strictly rejects blank strings, lowercase variants, and unmapped 'fallback_blend'.
--   5. DDL Transactional Safety Disclosure:
--      - In MySQL and MariaDB, DDL statements (ALTER TABLE, ADD CONSTRAINT) cause implicit commits.
--      - DDL cannot be rolled back atomically via a standard SQL TRANSACTION block.
--      - Safety is guaranteed by:
--        a) Non-destructive pre-audit queries that terminate execution before any DDL is executed.
--        b) Idempotent DDL clauses (DROP CONSTRAINT IF EXISTS).
--        c) Documented compensatory rollback steps in case manual recovery is needed.
--
-- Apply:
--   mysql -u root udm_radar_scratch < database/migrations/007_harmonize_prediction_source.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_007_harmonize_prediction_source$$

CREATE PROCEDURE migrate_007_harmonize_prediction_source()
BEGIN
    DECLARE v_col_exists INT DEFAULT 0;
    DECLARE v_invalid_pred_count INT DEFAULT 0;
    DECLARE v_cases_col_exists INT DEFAULT 0;
    DECLARE v_invalid_cases_count INT DEFAULT 0;

    -- -------------------------------------------------------------------------
    -- 1. Pre-Migration Audit: predictions table
    -- -------------------------------------------------------------------------
    SELECT COUNT(*) INTO v_col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'predictions'
      AND COLUMN_NAME = 'prediction_source';

    IF v_col_exists > 0 THEN
        -- Column exists: check for unrecognized, blank, or invalid provenance
        SELECT COUNT(*) INTO v_invalid_pred_count
        FROM `predictions`
        WHERE `prediction_source` IS NULL
           OR BINARY `prediction_source` = ''
           OR BINARY `prediction_source` NOT IN ('heuristic', 'decision_tree', 'calculation_fallback');

        IF v_invalid_pred_count > 0 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'Migration 007 aborted: found predictions.prediction_source records outside canonical vocabulary. Values preserved without modification.';
        END IF;
    END IF;

    -- -------------------------------------------------------------------------
    -- 2. Pre-Migration Audit: academic_support_cases table
    -- -------------------------------------------------------------------------
    SELECT COUNT(*) INTO v_cases_col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'academic_support_cases'
      AND COLUMN_NAME = 'prediction_source';

    IF v_cases_col_exists > 0 THEN
        SELECT COUNT(*) INTO v_invalid_cases_count
        FROM `academic_support_cases`
        WHERE `prediction_source` IS NULL
           OR BINARY `prediction_source` = ''
           OR BINARY `prediction_source` NOT IN ('heuristic', 'decision_tree', 'calculation_fallback');

        IF v_invalid_cases_count > 0 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'Migration 007 aborted: found academic_support_cases.prediction_source records outside canonical vocabulary. Values preserved without modification.';
        END IF;
    END IF;

    -- -------------------------------------------------------------------------
    -- 3. Schema Alteration: predictions.prediction_source
    --    Uses VARCHAR(30) NOT NULL without a permanent default so that omission
    --    on INSERT is strictly rejected rather than silently defaulting.
    -- -------------------------------------------------------------------------
    IF v_col_exists = 0 THEN
        -- Baseline schema without prediction_source: add column with temporary default 'heuristic'
        -- to populate existing historical baseline rows (405 rows) cleanly.
        ALTER TABLE `predictions`
        ADD COLUMN `prediction_source` VARCHAR(30) NOT NULL DEFAULT 'heuristic'
        COMMENT 'Provenance of prediction: decision_tree (ML model), heuristic (legacy 50/50 blend), calculation_fallback (deterministic grade calculation).'
        AFTER `irregular_prob`;

        -- Immediately remove default so all future inserts must supply provenance explicitly
        ALTER TABLE `predictions`
        ALTER COLUMN `prediction_source` DROP DEFAULT;
    ELSE
        -- Column already exists (e.g. live development DB enum): convert to VARCHAR(30) NOT NULL
        ALTER TABLE `predictions`
        MODIFY COLUMN `prediction_source` VARCHAR(30) NOT NULL
        COMMENT 'Provenance of prediction: decision_tree (ML model), heuristic (legacy 50/50 blend), calculation_fallback (deterministic grade calculation).';

        -- Ensure no default remains
        ALTER TABLE `predictions`
        ALTER COLUMN `prediction_source` DROP DEFAULT;
    END IF;

    -- -------------------------------------------------------------------------
    -- 4. Constraint Enforcement: predictions
    -- -------------------------------------------------------------------------
    ALTER TABLE `predictions`
    DROP CONSTRAINT IF EXISTS `chk_predictions_source_valid`;

    ALTER TABLE `predictions`
    ADD CONSTRAINT `chk_predictions_source_valid`
    CHECK (BINARY `prediction_source` IN ('heuristic', 'decision_tree', 'calculation_fallback'));

    -- -------------------------------------------------------------------------
    -- 5. Constraint Enforcement: academic_support_cases
    -- -------------------------------------------------------------------------
    IF v_cases_col_exists > 0 THEN
        ALTER TABLE `academic_support_cases`
        DROP CONSTRAINT IF EXISTS `chk_support_cases_source_valid`;

        ALTER TABLE `academic_support_cases`
        ADD CONSTRAINT `chk_support_cases_source_valid`
        CHECK (BINARY `prediction_source` IN ('heuristic', 'decision_tree', 'calculation_fallback'));
    END IF;

END$$

DELIMITER ;

-- Execute stored migration procedure
CALL migrate_007_harmonize_prediction_source();

-- Clean up temporary procedure
DROP PROCEDURE IF EXISTS migrate_007_harmonize_prediction_source;

-- =============================================================================
-- POST-MIGRATION VERIFICATION QUERIES (Reference / Verification)
-- =============================================================================
-- 1. Verify predictions column definition (should show DATA_TYPE=varchar, COLUMN_DEFAULT=NULL):
--    SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
--    FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'predictions' AND COLUMN_NAME = 'prediction_source';
--
-- 2. Verify check constraints:
--    SELECT CONSTRAINT_NAME, CHECK_CLAUSE
--    FROM information_schema.CHECK_CONSTRAINTS
--    WHERE CONSTRAINT_SCHEMA = DATABASE()
--      AND CONSTRAINT_NAME IN ('chk_predictions_source_valid', 'chk_support_cases_source_valid');
--
-- 3. Verify provenance distribution:
--    SELECT prediction_source, COUNT(*) AS total
--    FROM predictions
--    GROUP BY prediction_source;
--
-- =============================================================================
-- SAFE COMPENSATORY ROLLBACK GUIDANCE
-- =============================================================================
-- MariaDB DDL statements cause implicit commits and cannot be undone via ROLLBACK.
-- If manual rollback is required:
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_source_valid`;
--   ALTER TABLE `academic_support_cases` DROP CONSTRAINT IF EXISTS `chk_support_cases_source_valid`;
--   -- If returning to pre-007 state where prediction_source was 2-member enum:
--   -- ALTER TABLE `predictions` MODIFY COLUMN `prediction_source` ENUM('heuristic', 'decision_tree') NOT NULL DEFAULT 'heuristic';
--   -- If returning to baseline schema where prediction_source did not exist:
--   -- ALTER TABLE `predictions` DROP COLUMN `prediction_source`;
