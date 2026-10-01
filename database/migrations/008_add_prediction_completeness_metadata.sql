-- 008_add_prediction_completeness_metadata.sql
-- Work Package 7: Durably preserve generation-time prediction completeness and provisional metadata
--
-- Adds machine-readable completeness metadata to predictions table:
--   - data_completeness      — 'complete', 'historical_only', 'prelim_only', 'legacy_unknown'
--   - is_provisional         — TINYINT(1) flag (1 = provisional estimate, 0 = complete/standard)
--   - provisional_basis      — 'historical_gwa', 'current_prelim_avg', or NULL
--   - input_subject_count    — Count of current subjects contributing preliminary grades (NULL for legacy)
--   - expected_subject_count — Count of enrolled subjects in current term (NULL for legacy)
--
-- Architecture & Data Integrity Invariants:
--   1. Completeness Domain & Distinction:
--      - Complete predictions: data_completeness = 'complete', is_provisional = 0, provisional_basis IS NULL.
--      - Historical-only: data_completeness = 'historical_only', is_provisional = 1, provisional_basis = 'historical_gwa'.
--      - Current prelim-only: data_completeness = 'prelim_only', is_provisional = 1, provisional_basis = 'current_prelim_avg'.
--      - Missing-all: Strict failsafe, generates ZERO prediction rows.
--   2. Honest Historical Classification:
--      - Existing baseline rows (405 rows in scratch) are classified as 'legacy_unknown' with is_provisional = 0.
--      - Historical subject counts are set to NULL (never inventing unrecorded historical numbers).
--   3. Elimination of Permanent Default on data_completeness:
--      - Added with temporary default 'legacy_unknown' to populate existing rows, then immediately DROPPED.
--      - All future INSERTs must explicitly supply data_completeness.
--   4. Hardened Table-Level CHECK Constraints:
--      - chk_predictions_completeness_valid: domain verification with BINARY exact comparison.
--      - chk_predictions_is_provisional_valid: ensures 0 or 1.
--      - chk_predictions_provisional_basis_valid: verifies basis domain or NULL.
--      - chk_predictions_completeness_consistency: mathematically binds completeness to provisional basis.
--      - chk_predictions_subject_counts: ensures input_subject_count <= expected_subject_count.
--   5. DDL Transactional Safety Disclosure:
--      - In MySQL/MariaDB, DDL statements cause implicit commits.
--      - Idempotent execution is guaranteed via stored procedure logic and DROP CONSTRAINT IF EXISTS.
--
-- Apply:
--   mysql -u root udm_radar_scratch < database/migrations/008_add_prediction_completeness_metadata.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_008_add_prediction_completeness_metadata$$

CREATE PROCEDURE migrate_008_add_prediction_completeness_metadata()
BEGIN
    DECLARE v_col_exists INT DEFAULT 0;
    DECLARE v_invalid_count INT DEFAULT 0;

    -- -------------------------------------------------------------------------
    -- 1. Pre-Migration Audit: predictions table
    -- -------------------------------------------------------------------------
    SELECT COUNT(*) INTO v_col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'predictions'
      AND COLUMN_NAME = 'data_completeness';

    IF v_col_exists > 0 THEN
        -- Column exists: check for values outside canonical domain
        SELECT COUNT(*) INTO v_invalid_count
        FROM `predictions`
        WHERE `data_completeness` IS NULL
           OR BINARY `data_completeness` NOT IN ('complete', 'historical_only', 'prelim_only', 'legacy_unknown');

        IF v_invalid_count > 0 THEN
            SIGNAL SQLSTATE '45000'
              SET MESSAGE_TEXT = 'Migration 008 aborted: found predictions.data_completeness values outside approved domain.';
        END IF;
    END IF;

    -- -------------------------------------------------------------------------
    -- 2. Schema Alteration: Add completeness metadata columns
    -- -------------------------------------------------------------------------
    IF v_col_exists = 0 THEN
        -- Add data_completeness with temporary default 'legacy_unknown' to classify existing rows
        ALTER TABLE `predictions`
        ADD COLUMN `data_completeness` VARCHAR(20) NOT NULL DEFAULT 'legacy_unknown'
        COMMENT 'Generation completeness: complete, historical_only, prelim_only, legacy_unknown.'
        AFTER `prediction_source`,

        ADD COLUMN `is_provisional` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Flag: 1 if provisional estimate based on partial inputs, 0 if complete or standard.'
        AFTER `data_completeness`,

        ADD COLUMN `provisional_basis` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Input basis when provisional: historical_gwa, current_prelim_avg, or NULL.'
        AFTER `is_provisional`,

        ADD COLUMN `input_subject_count` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Count of contributing subjects with grades at generation time (NULL for legacy).'
        AFTER `provisional_basis`,

        ADD COLUMN `expected_subject_count` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Count of enrolled subjects in current term at generation time (NULL for legacy).'
        AFTER `input_subject_count`;

        -- Immediately remove default on data_completeness so future INSERTs must supply it explicitly
        ALTER TABLE `predictions`
        ALTER COLUMN `data_completeness` DROP DEFAULT;
    ELSE
        -- Ensure data_completeness is VARCHAR(20) NOT NULL with no default
        ALTER TABLE `predictions`
        MODIFY COLUMN `data_completeness` VARCHAR(20) NOT NULL
        COMMENT 'Generation completeness: complete, historical_only, prelim_only, legacy_unknown.';

        ALTER TABLE `predictions`
        ALTER COLUMN `data_completeness` DROP DEFAULT;
    END IF;

    -- -------------------------------------------------------------------------
    -- 3. Constraint Enforcement: predictions
    -- -------------------------------------------------------------------------
    ALTER TABLE `predictions`
    DROP CONSTRAINT IF EXISTS `chk_predictions_completeness_valid`;

    ALTER TABLE `predictions`
    ADD CONSTRAINT `chk_predictions_completeness_valid`
    CHECK (BINARY `data_completeness` IN ('complete', 'historical_only', 'prelim_only', 'legacy_unknown'));

    ALTER TABLE `predictions`
    DROP CONSTRAINT IF EXISTS `chk_predictions_is_provisional_valid`;

    ALTER TABLE `predictions`
    ADD CONSTRAINT `chk_predictions_is_provisional_valid`
    CHECK (`is_provisional` IN (0, 1));

    ALTER TABLE `predictions`
    DROP CONSTRAINT IF EXISTS `chk_predictions_provisional_basis_valid`;

    ALTER TABLE `predictions`
    ADD CONSTRAINT `chk_predictions_provisional_basis_valid`
    CHECK (`provisional_basis` IS NULL OR BINARY `provisional_basis` IN ('historical_gwa', 'current_prelim_avg'));

    ALTER TABLE `predictions`
    DROP CONSTRAINT IF EXISTS `chk_predictions_completeness_consistency`;

    ALTER TABLE `predictions`
    ADD CONSTRAINT `chk_predictions_completeness_consistency`
    CHECK (
        (BINARY `data_completeness` IN ('complete', 'legacy_unknown') AND `is_provisional` = 0 AND `provisional_basis` IS NULL)
        OR
        (BINARY `data_completeness` = 'historical_only' AND `is_provisional` = 1 AND BINARY `provisional_basis` = 'historical_gwa')
        OR
        (BINARY `data_completeness` = 'prelim_only' AND `is_provisional` = 1 AND BINARY `provisional_basis` = 'current_prelim_avg')
    );

    ALTER TABLE `predictions`
    DROP CONSTRAINT IF EXISTS `chk_predictions_subject_counts`;

    ALTER TABLE `predictions`
    ADD CONSTRAINT `chk_predictions_subject_counts`
    CHECK (
        `input_subject_count` IS NULL 
        OR `expected_subject_count` IS NULL 
        OR `input_subject_count` <= `expected_subject_count`
    );

END$$

DELIMITER ;

-- Execute stored migration procedure
CALL migrate_008_add_prediction_completeness_metadata();

-- Clean up temporary procedure
DROP PROCEDURE IF EXISTS migrate_008_add_prediction_completeness_metadata;

-- =============================================================================
-- SAFE COMPENSATORY ROLLBACK GUIDANCE
-- =============================================================================
-- MariaDB DDL statements cause implicit commits and cannot be undone via ROLLBACK.
-- If manual rollback is required:
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_completeness_valid`;
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_is_provisional_valid`;
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_provisional_basis_valid`;
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_completeness_consistency`;
--   ALTER TABLE `predictions` DROP CONSTRAINT IF EXISTS `chk_predictions_subject_counts`;
--   ALTER TABLE `predictions` DROP COLUMN IF EXISTS `expected_subject_count`;
--   ALTER TABLE `predictions` DROP COLUMN IF EXISTS `input_subject_count`;
--   ALTER TABLE `predictions` DROP COLUMN IF EXISTS `provisional_basis`;
--   ALTER TABLE `predictions` DROP COLUMN IF EXISTS `is_provisional`;
--   ALTER TABLE `predictions` DROP COLUMN IF EXISTS `data_completeness`;
