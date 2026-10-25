-- =============================================================================
-- database/verify/verify_schema.sql
-- UDM-RADAR: Read-Only Schema Verification Script
--
-- PURPOSE:
-- Verifies that the active database matches the canonical 18-table post-010 schema.
-- Checks table presence, critical column types, and mandatory CHECK constraints.
--
-- USAGE:
--   mysql -u root udm_radar < database/verify/verify_schema.sql
-- =============================================================================

SELECT '==================================================' AS '';
SELECT 'UDM-RADAR: READ-ONLY SCHEMA VERIFICATION' AS '';
SELECT CONCAT('Active Database: ', DATABASE()) AS '';
SELECT '==================================================' AS '';

-- 1. Table Count Verification (Expected: exactly 18 official tables)
SELECT
    COUNT(*) AS total_tables,
    18 AS expected_tables,
    CASE WHEN COUNT(*) = 18 THEN 'PASS' ELSE 'FAIL' END AS table_count_status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME NOT LIKE '_backup_%';

-- 2. Verify Presence of All 18 Application Tables
SELECT
    expected.table_name,
    CASE WHEN t.TABLE_NAME IS NOT NULL THEN 'PRESENT' ELSE 'MISSING' END AS presence_status
FROM (
    SELECT 'academic_support_cases' AS table_name UNION ALL
    SELECT 'admin_change_log' UNION ALL
    SELECT 'export_audit_logs' UNION ALL
    SELECT 'faculty_class_loads' UNION ALL
    SELECT 'feedback_messages' UNION ALL
    SELECT 'feedback_reports' UNION ALL
    SELECT 'feedback_status_history' UNION ALL
    SELECT 'grades' UNION ALL
    SELECT 'otp_tokens' UNION ALL
    SELECT 'pending_corrections' UNION ALL
    SELECT 'pending_grade_batches' UNION ALL
    SELECT 'predictions' UNION ALL
    SELECT 'student_profiles' UNION ALL
    SELECT 'subjects' UNION ALL
    SELECT 'support_actions' UNION ALL
    SELECT 'support_case_referrals' UNION ALL
    SELECT 'support_status_history' UNION ALL
    SELECT 'users'
) AS expected
LEFT JOIN information_schema.TABLES t
    ON t.TABLE_SCHEMA = DATABASE()
   AND t.TABLE_NAME = expected.table_name;

-- 3. Verify Critical Columns and Storage Types
SELECT
    'grades.final_grade' AS column_check,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    CASE WHEN DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH = 10 THEN 'PASS' ELSE 'FAIL' END AS status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'grades'
  AND COLUMN_NAME = 'final_grade';

SELECT
    'support_case_referrals.notice_issued_at' AS column_check,
    DATA_TYPE,
    IS_NULLABLE,
    CASE WHEN DATA_TYPE = 'datetime' THEN 'PASS' ELSE 'FAIL' END AS status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'support_case_referrals'
  AND COLUMN_NAME = 'notice_issued_at';

SELECT
    'support_case_referrals.reason_code' AS column_check,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    CASE WHEN DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH = 50 THEN 'PASS' ELSE 'FAIL' END AS status
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'support_case_referrals'
  AND COLUMN_NAME = 'reason_code';

-- 4. Verify Table-Level CHECK Constraints
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    'VERIFIED' AS status
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND CONSTRAINT_TYPE = 'CHECK'
  AND CONSTRAINT_NAME IN (
      'chk_grades_final_grade_domain',
      'chk_student_profiles_course_valid',
      'chk_predictions_source_valid',
      'chk_predictions_completeness_valid',
      'chk_support_cases_source_valid',
      'chk_referral_reason_valid'
  );

SELECT '==================================================' AS '';
SELECT 'SCHEMA VERIFICATION COMPLETED' AS '';
SELECT '==================================================' AS '';
