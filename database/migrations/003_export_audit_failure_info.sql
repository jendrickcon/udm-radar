-- 003_export_audit_failure_info.sql
-- Remediation P1: enable the two-level export-audit failure model.
--
-- Adds failure_reason to export_audit_logs to record sanitized, user-safe
-- descriptions of export generation failures or audit write warnings.
--
-- Table requirements:
--   - Requires export_audit_logs (created by migration 000).
--   - format remains VARCHAR(20) NOT NULL (enforced by application code).
--
-- Idempotent:
--   - Uses IF NOT EXISTS to prevent duplicate-column errors on re-run.
--   - Preserves all existing audit records.
--
-- Apply:
--   mysql -u root udm_radar < database/migrations/003_export_audit_failure_info.sql

ALTER TABLE export_audit_logs
  ADD COLUMN IF NOT EXISTS failure_reason VARCHAR(255) DEFAULT NULL
    AFTER success;

-- Verification queries:
--   SHOW COLUMNS FROM export_audit_logs LIKE 'failure_reason';
--   SELECT COUNT(*) FROM export_audit_logs;

-- ---------------------------------------------------------------------------
-- ROLLBACK:
--   ALTER TABLE export_audit_logs DROP COLUMN IF EXISTS failure_reason;
