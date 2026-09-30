-- 003_export_audit_failure_info.sql
-- Remediation P1: enable the two-level export-audit failure model.
--
-- Verified 2026-09-30: export_audit_logs exists with the columns the helper
-- inserts (user_id, report_type, format, filters_json, row_count, success) and
-- a probe INSERT succeeds. Zero rows is NOT a schema mismatch — no export has
-- ever run on this database. All eight exporters call logExportAudit().
--
-- What IS missing for the approved failure model:
--   * failure_reason — sanitized, user-safe description of a failed audit
--     insert or a failed export generation.
--   * format as a constrained enum — the helper only ever writes 'CSV'/'PDF'.
--
-- Level 1 (audit insert fails after successful export): helper logs technical
--   detail to the server error log and returns false; the export completes.
-- Level 2 (export generation itself fails): exporters record a failed audit
--   event while the database is still available, storing a sanitized reason.
--
-- Rollback:
--   ALTER TABLE export_audit_logs MODIFY format VARCHAR(20) NOT NULL;
--   ALTER TABLE export_audit_logs DROP COLUMN failure_reason;

ALTER TABLE export_audit_logs
  ADD COLUMN failure_reason VARCHAR(255) DEFAULT NULL
    AFTER success;

ALTER TABLE export_audit_logs
  MODIFY format ENUM('CSV','PDF') NOT NULL;
