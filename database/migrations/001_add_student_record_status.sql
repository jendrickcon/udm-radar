-- 001_add_student_record_status.sql
-- Remediation P3: separate RECORD LIFECYCLE from ACADEMIC status.
--
-- student_profiles.status is enum('Regular','Irregular') and describes
-- curricular progression only. Lifecycle (active / archived / graduated) had
-- no home, so code wrote 'Archived' into the academic enum; with a non-strict
-- sql_mode MySQL silently coerced it to 'Regular', quietly un-archiving every
-- record that was ever archived.
--
-- This migration ADDS the lifecycle column. It does NOT rename or modify the
-- academic column: a repository-wide audit found 49 references to
-- student_profiles.status, too many to rename atomically.
--
-- Apply manually in phpMyAdmin or:
--   mysql -u root udm_radar < database/migrations/001_add_student_record_status.sql
--
-- Rollback: see the comment block at the bottom (NOT lossless once lifecycle
-- values have been written — export them first).

ALTER TABLE student_profiles
  ADD COLUMN record_status ENUM('Active','Archived','Graduated')
    NOT NULL DEFAULT 'Active' AFTER status;

-- All existing rows are Active: no row can currently hold 'Archived'
-- (the enum coerced such writes to 'Regular'), and there is no graduation
-- workflow in the live system. The DEFAULT covers them; this UPDATE is
-- explicit so the intent is visible in the migration record.
UPDATE student_profiles SET record_status = 'Active';

-- Active-population queries are the hot path (dashboards, batch prediction,
-- rosters, exports) and all filter on record_status.
ALTER TABLE student_profiles
  ADD INDEX idx_record_status (record_status),
  ADD INDEX idx_status_record (status, record_status);

-- ---------------------------------------------------------------------------
-- ROLLBACK (only safe before any Archived/Graduated values have been written):
--
--   ALTER TABLE student_profiles DROP INDEX idx_status_record;
--   ALTER TABLE student_profiles DROP INDEX idx_record_status;
--   ALTER TABLE student_profiles DROP COLUMN record_status;
--
-- Once lifecycle values exist, dropping the column destroys that information.
-- First preserve it, then restore account states per the rollback policy,
-- and only then drop:
--
--   CREATE TABLE student_profiles_record_status_rollback AS
--     SELECT user_id, record_status FROM student_profiles;
--   -- restore users.is_active from the saved mapping
--   ALTER TABLE student_profiles DROP COLUMN record_status;
