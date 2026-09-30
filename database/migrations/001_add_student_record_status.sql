-- 001_add_student_record_status.sql
-- Remediation P3: separate RECORD LIFECYCLE from ACADEMIC status.
--
-- student_profiles.status is enum('Regular','Irregular') and describes
-- curricular progression only. Lifecycle (Active / Archived / Graduated) is
-- stored in record_status.
--
-- This migration ADDS the lifecycle column and indexes. It does NOT modify
-- the academic progression status column.
--
-- Idempotent:
--   - Uses IF NOT EXISTS for column and index creation.
--   - Preserves existing 'Archived' or 'Graduated' values if re-run.
--
-- Apply:
--   mysql -u root udm_radar < database/migrations/001_add_student_record_status.sql

ALTER TABLE student_profiles
  ADD COLUMN IF NOT EXISTS record_status ENUM('Active','Archived','Graduated')
    NOT NULL DEFAULT 'Active' AFTER status;

-- Safely initialize rows that do not yet have a lifecycle status.
-- Guarded to avoid overwriting existing 'Archived' or 'Graduated' values on re-run.
UPDATE student_profiles
  SET record_status = 'Active'
  WHERE record_status IS NULL OR record_status = '';

-- Indexes for active-population query performance (dashboards, rosters, exports)
ALTER TABLE student_profiles
  ADD INDEX IF NOT EXISTS idx_record_status (record_status),
  ADD INDEX IF NOT EXISTS idx_status_record (status, record_status);

-- Verification queries:
--   SHOW COLUMNS FROM student_profiles LIKE 'record_status';
--   SHOW INDEX FROM student_profiles WHERE Key_name IN ('idx_record_status', 'idx_status_record');
--   SELECT record_status, COUNT(*) FROM student_profiles GROUP BY record_status;

-- ---------------------------------------------------------------------------
-- ROLLBACK (only safe before any Archived/Graduated values have been written):
--
--   ALTER TABLE student_profiles DROP INDEX IF EXISTS idx_status_record;
--   ALTER TABLE student_profiles DROP INDEX IF EXISTS idx_record_status;
--   ALTER TABLE student_profiles DROP COLUMN IF EXISTS record_status;
--
-- Once lifecycle values exist, dropping the column destroys that information.
-- First preserve it, then restore account states per the rollback policy:
--
--   CREATE TABLE IF NOT EXISTS student_profiles_record_status_rollback AS
--     SELECT user_id, record_status FROM student_profiles;
--   ALTER TABLE student_profiles DROP COLUMN IF EXISTS record_status;
