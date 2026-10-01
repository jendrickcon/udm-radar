-- 007_harmonize_prediction_source.sql
-- Work Package 5: Harmonize prediction_source provenance contract and constraints
--
-- Harmonizes the stored prediction provenance vocabulary across the database:
--   - 'decision_tree'        — Machine learning model projection (Decision Tree)
--   - 'heuristic'            — Preserved legacy heuristic provenance (baseline 50/50 blend)
--   - 'calculation_fallback' — Deterministic calculation fallback from current grades
--
-- Target Requirements:
--   1. Pre-constraint data audit:
--      - If predictions.prediction_source already exists, audits all existing records.
--      - Audits academic_support_cases.prediction_source records.
--      - Aborts with SQLSTATE 45000 if blank, NULL, 'fallback_blend', or unknown values exist.
--   2. Preserves all existing prediction rows and historical provenance:
--      - Legacy rows retain 'heuristic' provenance without rewrite or deletion.
--      - Machine learning rows retain 'decision_tree' provenance.
--   3. Idempotent schema evolution:
--      - Adds predictions.prediction_source ENUM('heuristic', 'decision_tree', 'calculation_fallback')
--        if the column is absent (e.g., baseline dump / fresh scratch), defaulting to 'heuristic'.
--      - Modifies predictions.prediction_source to the 3-value ENUM if already present.
--   4. Hardened domain integrity:
--      - Adds table-level CHECK constraint chk_predictions_source_valid using BINARY exact comparison.
--      - Adds table-level CHECK constraint chk_support_cases_source_valid on academic_support_cases.
--      - Strictly rejects blank strings, lowercase variants, and unmapped 'fallback_blend'.
--   5. Verifiable and safe to execute multiple times.
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
    -- -------------------------------------------------------------------------
    IF v_col_exists = 0 THEN
        -- Baseline schema without prediction_source: add column with default 'heuristic'
        -- Existing rows are historical heuristic baseline predictions.
        ALTER TABLE `predictions`
        ADD COLUMN `prediction_source` ENUM('heuristic', 'decision_tree', 'calculation_fallback')
        NOT NULL DEFAULT 'heuristic'
        COMMENT 'Provenance of prediction: decision_tree (ML model), heuristic (legacy 50/50 blend), calculation_fallback (deterministic grade calculation).'
        AFTER `irregular_prob`;
    ELSE
        -- Column already exists (e.g. live development DB): standardize enum members
        ALTER TABLE `predictions`
        MODIFY COLUMN `prediction_source` ENUM('heuristic', 'decision_tree', 'calculation_fallback')
        NOT NULL DEFAULT 'heuristic'
        COMMENT 'Provenance of prediction: decision_tree (ML model), heuristic (legacy 50/50 blend), calculation_fallback (deterministic grade calculation).';
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
-- 1. Verify predictions column definition:
--    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
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
-- SAFE ROLLBACK GUIDANCE
-- =============================================================================
-- If rollback is required:
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_source_valid`;
--   ALTER TABLE `academic_support_cases` DROP CONSTRAINT IF EXISTS `chk_support_cases_source_valid`;
--   -- If returning to pre-007 state where prediction_source was 2-member enum:
--   -- ALTER TABLE `predictions` MODIFY COLUMN `prediction_source` ENUM('heuristic', 'decision_tree') NOT NULL DEFAULT 'heuristic';
--   -- If returning to baseline schema where prediction_source did not exist:
--   -- ALTER TABLE `predictions` DROP COLUMN `prediction_source`;
