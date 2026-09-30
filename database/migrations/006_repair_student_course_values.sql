-- 006_repair_student_course_values.sql
-- Work Package 6: Student Course Value and Curriculum-Track Repair
--
-- Repairs invalid and blank student_profiles.course values to the canonical
-- synthetic track 'BSIT - Software Development', standardizes the column to
-- the approved 3-track ENUM, and attaches a CHECK constraint to strictly prevent
-- future writes of generic or empty strings even in non-strict SQL mode.
--
-- Target Requirements:
--   1. Pre-migration audit: verify existing values. Abort if any unexpected non-blank
--      value outside the known legacy/canonical set exists.
--   2. Back up affected records to _backup_student_profiles_course_wp6.
--   3. Update legacy/blank values to 'BSIT - Software Development'.
--   4. Preserve already valid tracks ('BSIT - Data Science', 'BSIT - Cyber Security').
--   5. Standardize column to ENUM('BSIT - Software Development','BSIT - Data Science','BSIT - Cyber Security')
--      NOT NULL DEFAULT 'BSIT - Software Development'.
--   6. Attach CHECK constraint chk_student_profiles_course_valid.
--   7. Safe to rerun (idempotent).
--
-- Apply:
--   mysql -u root udm_radar_scratch < database/migrations/006_repair_student_course_values.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS migrate_006_repair_student_course_values$$

CREATE PROCEDURE migrate_006_repair_student_course_values()
BEGIN
    DECLARE v_unexpected_count INT DEFAULT 0;

    -- 1. Pre-migration audit:
    -- Verify no unknown non-blank values exist.
    -- Allowed values to repair or preserve:
    --   - 'BSIT - Software Development'
    --   - 'BSIT - Data Science'
    --   - 'BSIT - Cyber Security'
    --   - 'Bachelor of Science in Information Technology'
    --   - 'BSIT (Software Development)'
    --   - '' (blank string from prior unvalidated enum insert)
    --   - NULL
    SELECT COUNT(*) INTO v_unexpected_count
    FROM `student_profiles`
    WHERE `course` IS NOT NULL
      AND `course` != ''
      AND `course` NOT IN (
        'BSIT - Software Development',
        'BSIT - Data Science',
        'BSIT - Cyber Security',
        'Bachelor of Science in Information Technology',
        'BSIT (Software Development)'
      );

    IF v_unexpected_count > 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Migration 006 aborted: unexpected course values found. Existing data preserved without modification.';
    END IF;

    -- 2. Create backup table to preserve before-migration state
    CREATE TABLE IF NOT EXISTS `_backup_student_profiles_course_wp6` (
      `user_id` INT NOT NULL,
      `student_number` VARCHAR(20) NOT NULL,
      `course` VARCHAR(100) DEFAULT NULL,
      `backed_up_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    -- Populate backup only for records not already backed up
    INSERT IGNORE INTO `_backup_student_profiles_course_wp6` (`user_id`, `student_number`, `course`)
      SELECT `user_id`, `student_number`, `course`
      FROM `student_profiles`;

    -- 3. Update legacy/blank records to canonical Software Development track.
    -- Preserves legitimate Data Science and Cyber Security records if present.
    UPDATE `student_profiles`
      SET `course` = 'BSIT - Software Development'
      WHERE `course` IN (
        'Bachelor of Science in Information Technology',
        'BSIT (Software Development)',
        ''
      )
      OR `course` IS NULL;

    -- 4. Standardize column definition to the approved 3-track ENUM
    ALTER TABLE `student_profiles`
      MODIFY COLUMN `course` ENUM('BSIT - Software Development', 'BSIT - Data Science', 'BSIT - Cyber Security')
        NOT NULL DEFAULT 'BSIT - Software Development'
        COMMENT 'Curriculum track: BSIT - Software Development, BSIT - Data Science, or BSIT - Cyber Security';

    -- 5. Attach CHECK constraint to strictly disallow empty strings (even under non-strict SQL mode)
    ALTER TABLE `student_profiles` DROP CONSTRAINT IF EXISTS `chk_student_profiles_course_valid`;
    ALTER TABLE `student_profiles` ADD CONSTRAINT `chk_student_profiles_course_valid`
      CHECK (`course` IN ('BSIT - Software Development', 'BSIT - Data Science', 'BSIT - Cyber Security'));

END$$

DELIMITER ;

CALL migrate_006_repair_student_course_values();
DROP PROCEDURE IF EXISTS migrate_006_repair_student_course_values;

-- ---------------------------------------------------------------------------
-- Verification Queries:
--   SHOW FULL COLUMNS FROM student_profiles LIKE 'course';
--   SELECT course, COUNT(*) FROM student_profiles GROUP BY course;
--   SELECT COUNT(*) FROM student_profiles WHERE course = '' OR course IS NULL;
--   SELECT * FROM _backup_student_profiles_course_wp6 LIMIT 5;
--
-- ---------------------------------------------------------------------------
-- ROLLBACK POLICY:
--
-- Restores original course values from `_backup_student_profiles_course_wp6`:
--
--   ALTER TABLE `student_profiles` DROP CONSTRAINT IF EXISTS `chk_student_profiles_course_valid`;
--   ALTER TABLE `student_profiles` MODIFY COLUMN `course` VARCHAR(100) DEFAULT NULL;
--
--   UPDATE `student_profiles` sp
--   JOIN `_backup_student_profiles_course_wp6` b ON b.user_id = sp.user_id
--   SET sp.course = b.course;
--
-- Drop backup table only when rollback window has closed:
--   DROP TABLE IF EXISTS `_backup_student_profiles_course_wp6`;
