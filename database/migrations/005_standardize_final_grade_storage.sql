-- 005_standardize_final_grade_storage.sql
-- Work Package 3: Standardize final_grade column storage to VARCHAR(10) with Hardened Constraint
--
-- Enables reproducible storage of both canonical numeric point grades ('4.00'..'1.00')
-- and canonical textual statuses ('INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD'), plus
-- legacy '0.00' and NULL.
--
-- Target Requirements:
--   - Pre-constraint audit: verifies all existing non-null records belong to approved domain.
--     If any invalid value exists, migration aborts with SQLSTATE 45000 and preserves data.
--   - Modify grades.final_grade from DECIMAL(4,2) NULL to VARCHAR(10) NULL DEFAULT NULL.
--   - Preserve all existing numeric values and precision exactly ('3.50', '1.00', '0.00').
--   - Set column comment documenting mixed point/status storage, legacy 0.00, and NULL.
--   - Add hardened CHECK constraint chk_grades_final_grade_domain using BINARY comparison
--     to strictly reject case variants (inc, Inc, drp) and padding/trailing spaces.
--   - Safe to apply to:
--       1. Baseline schema after migrations 000 through 004;
--       2. A database where final_grade is already VARCHAR(10);
--       3. Scratch database after a fresh rebuild.
--
-- Idempotency Strategy:
--   - Temporary stored procedure inspects information_schema.COLUMNS.
--   - Pre-migration guard checks existing data before altering.
--   - MariaDB 10.4 DROP CONSTRAINT IF EXISTS followed by ADD CONSTRAINT ensures
--     idempotent application of the hardened BINARY constraint definition.
--   - Stored procedure is executed and dropped immediately.
--
-- Apply:
--   mysql -u root udm_radar_scratch < database/migrations/005_standardize_final_grade_storage.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_005_standardize_final_grade$$

CREATE PROCEDURE migrate_005_standardize_final_grade()
BEGIN
    DECLARE v_col_type VARCHAR(50);
    DECLARE v_col_len INT;
    DECLARE v_invalid_count INT DEFAULT 0;

    -- 1. Pre-constraint data audit:
    -- Verify every existing non-null final_grade belongs to the canonical domain.
    -- BINARY comparison prevents collation from treating invalid cases as valid.
    SELECT COUNT(*) INTO v_invalid_count
    FROM `grades`
    WHERE `final_grade` IS NOT NULL
      AND BINARY `final_grade` NOT IN (
        '4.00', '3.75', '3.50', '3.25', '3.00', '2.75', '2.50', '2.25', '2.00', '1.75', '1.50', '1.25', '1.00',
        'INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD', '0.00'
      );

    IF v_invalid_count > 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Migration 005 aborted: found grades.final_grade records outside canonical domain. Values preserved without modification.';
    END IF;

    -- 2. Inspect current data type and length of grades.final_grade
    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
      INTO v_col_type, v_col_len
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grades'
      AND COLUMN_NAME = 'final_grade';

    -- 3. Conditionally alter column to VARCHAR(10) with descriptive comment
    IF v_col_type IS NULL OR LOWER(v_col_type) != 'varchar' OR v_col_len != 10 THEN
        SET @sql_alter = 'ALTER TABLE `grades` MODIFY COLUMN `final_grade` VARCHAR(10) NULL DEFAULT NULL COMMENT \'Mixed point (4.00-1.00), textual status (INC, DRP, P, DO, DU, FA, UD), legacy 0.00, or NULL if in-progress\'';
        PREPARE stmt_alter FROM @sql_alter;
        EXECUTE stmt_alter;
        DEALLOCATE PREPARE stmt_alter;
    ELSE
        -- Ensure column comment is updated even if column is already VARCHAR(10)
        SET @sql_comment = 'ALTER TABLE `grades` MODIFY COLUMN `final_grade` VARCHAR(10) NULL DEFAULT NULL COMMENT \'Mixed point (4.00-1.00), textual status (INC, DRP, P, DO, DU, FA, UD), legacy 0.00, or NULL if in-progress\'';
        PREPARE stmt_comment FROM @sql_comment;
        EXECUTE stmt_comment;
        DEALLOCATE PREPARE stmt_comment;
    END IF;

    -- 4. Idempotently attach hardened CHECK constraint with BINARY exact-match comparison.
    -- Replaces any prior case-insensitive constraint to strictly reject lowercase (inc),
    -- mixed-case (Inc), and space-padded (INC , 3.50 ) inputs.
    SET @sql_drop_chk = 'ALTER TABLE `grades` DROP CONSTRAINT IF EXISTS `chk_grades_final_grade_domain`';
    PREPARE stmt_drop_chk FROM @sql_drop_chk;
    EXECUTE stmt_drop_chk;
    DEALLOCATE PREPARE stmt_drop_chk;

    SET @sql_add_chk = 'ALTER TABLE `grades` ADD CONSTRAINT `chk_grades_final_grade_domain` CHECK (`final_grade` IS NULL OR BINARY `final_grade` IN (\'4.00\', \'3.75\', \'3.50\', \'3.25\', \'3.00\', \'2.75\', \'2.50\', \'2.25\', \'2.00\', \'1.75\', \'1.50\', \'1.25\', \'1.00\', \'INC\', \'DRP\', \'P\', \'DO\', \'DU\', \'FA\', \'UD\', \'0.00\'))';
    PREPARE stmt_add_chk FROM @sql_add_chk;
    EXECUTE stmt_add_chk;
    DEALLOCATE PREPARE stmt_add_chk;
END$$

DELIMITER ;

CALL migrate_005_standardize_final_grade();
DROP PROCEDURE IF EXISTS migrate_005_standardize_final_grade;

-- ---------------------------------------------------------------------------
-- Verification Queries:
--   SHOW FULL COLUMNS FROM grades LIKE 'final_grade';
--   SELECT * FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grades';
--   SELECT final_grade, COUNT(*) FROM grades GROUP BY final_grade ORDER BY final_grade;
--
-- ---------------------------------------------------------------------------
-- ROLLBACK POLICY:
--
-- Reverting grades.final_grade from VARCHAR(10) back to DECIMAL(4,2) is only safe
-- if no non-numeric textual statuses have been written to the table.
--
-- Pre-Rollback Guard Query:
--   SELECT COUNT(*) AS non_numeric_count
--   FROM grades
--   WHERE final_grade IS NOT NULL
--     AND final_grade NOT REGEXP '^[0-9]+([.][0-9]+)?$';
--
-- If non_numeric_count > 0:
--   DO NOT run ALTER TABLE ... MODIFY COLUMN ... DECIMAL(4,2).
--   Converting columns with textual values ('INC', 'DRP', 'P', etc.) into DECIMAL
--   causes data loss or silent coercion into 0.00.
--   Instead, restore from an approved database backup.
--
-- If non_numeric_count = 0 (only numeric point grades exist):
--   ALTER TABLE `grades` DROP CONSTRAINT IF EXISTS `chk_grades_final_grade_domain`;
--   ALTER TABLE `grades` MODIFY COLUMN `final_grade` DECIMAL(4,2) NULL DEFAULT NULL;
