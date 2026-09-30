-- 005_standardize_final_grade_storage.sql
-- Work Package 3: Standardize final_grade column storage to VARCHAR(10)
--
-- Enables reproducible storage of both canonical numeric point grades ('4.00'..'1.00')
-- and canonical textual statuses ('INC', 'DRP', 'P', 'DO', 'DU', 'FA', 'UD'), plus
-- legacy '0.00' and NULL.
--
-- Target Requirements:
--   - Modify grades.final_grade from DECIMAL(4,2) NULL to VARCHAR(10) NULL DEFAULT NULL.
--   - Preserve all existing numeric values and precision exactly ('3.50', '1.00', '0.00').
--   - Add CHECK constraint chk_grades_final_grade_domain for approved domain values.
--   - Safe to apply to:
--       1. Baseline schema after migrations 000 through 004;
--       2. A database where final_grade is already VARCHAR(10);
--       3. Scratch database after a fresh rebuild.
--
-- Idempotency Strategy:
--   - Temporary stored procedure inspects information_schema.COLUMNS.
--   - If data_type is already 'varchar' and length is 10, column alteration is skipped.
--   - MariaDB 10.4 ADD CONSTRAINT IF NOT EXISTS prevents duplicate constraint errors.
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

    -- 1. Inspect current data type and length of grades.final_grade
    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
      INTO v_col_type, v_col_len
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grades'
      AND COLUMN_NAME = 'final_grade';

    -- 2. Conditionally alter column to VARCHAR(10) if not already migrated
    IF v_col_type IS NULL OR LOWER(v_col_type) != 'varchar' OR v_col_len != 10 THEN
        SET @sql_alter = 'ALTER TABLE `grades` MODIFY COLUMN `final_grade` VARCHAR(10) NULL DEFAULT NULL';
        PREPARE stmt_alter FROM @sql_alter;
        EXECUTE stmt_alter;
        DEALLOCATE PREPARE stmt_alter;
    END IF;

    -- 3. Idempotently add CHECK constraint for canonical domain values
    -- Allows canonical point strings, canonical statuses, legacy '0.00', and NULL.
    SET @sql_chk = 'ALTER TABLE `grades` ADD CONSTRAINT IF NOT EXISTS `chk_grades_final_grade_domain` CHECK (`final_grade` IS NULL OR `final_grade` IN (\'4.00\', \'3.75\', \'3.50\', \'3.25\', \'3.00\', \'2.75\', \'2.50\', \'2.25\', \'2.00\', \'1.75\', \'1.50\', \'1.25\', \'1.00\', \'INC\', \'DRP\', \'P\', \'DO\', \'DU\', \'FA\', \'UD\', \'0.00\'))';
    PREPARE stmt_chk FROM @sql_chk;
    EXECUTE stmt_chk;
    DEALLOCATE PREPARE stmt_chk;
END$$

DELIMITER ;

CALL migrate_005_standardize_final_grade();
DROP PROCEDURE IF EXISTS migrate_005_standardize_final_grade;

-- ---------------------------------------------------------------------------
-- Verification Queries:
--   SHOW COLUMNS FROM grades LIKE 'final_grade';
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
