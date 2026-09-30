-- 000_create_export_audit_logs.sql
-- Baseline schema definition for export_audit_logs.
--
-- Creates the export_audit_logs table expected by csv_export_helpers.php
-- prior to migration 003.
--
-- Table design matches the live database structure:
--   - format: VARCHAR(20) NOT NULL (enforced/validated in application code)
--   - failure_reason: omitted here; added in migration 003
--   - Foreign keys: none (preserves loose coupling with users)
--
-- Idempotent: creates the table only IF NOT EXISTS.
--
-- Apply:
--   mysql -u root udm_radar < database/migrations/000_create_export_audit_logs.sql

CREATE TABLE IF NOT EXISTS `export_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `format` varchar(20) NOT NULL,
  `filters_json` text DEFAULT NULL,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eal_user` (`user_id`),
  KEY `idx_eal_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Verification queries:
--   SHOW TABLES LIKE 'export_audit_logs';
--   SHOW COLUMNS FROM export_audit_logs;

-- ---------------------------------------------------------------------------
-- ROLLBACK (safe only if table contains no operational records):
--   DROP TABLE IF EXISTS export_audit_logs;
