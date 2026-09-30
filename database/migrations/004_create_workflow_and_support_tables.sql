-- 004_create_workflow_and_support_tables.sql
-- Baseline schema definition for workflow and support tables:
--   1. feedback_messages
--   2. feedback_status_history
--   3. pending_grade_batches
--   4. academic_support_cases
--   5. support_actions
--   6. support_case_referrals
--   7. support_status_history
--
-- Context:
-- The baseline database dump (database/udm_radar.sql, Sep 29) contains 10 core tables
-- but lacks the 7 workflow and support tables actively used by the application
-- (grade approvals, academic support cases, faculty referrals, and feedback messaging).
-- This migration creates those tables with exact column types, check constraints,
-- and foreign keys matching the operational schema.
--
-- Creation order strictly respects foreign key dependencies:
--   - feedback_messages & feedback_status_history depend on feedback_reports, users
--   - pending_grade_batches depends on users, subjects
--   - academic_support_cases depends on users, predictions
--   - support_actions, support_case_referrals, support_status_history depend on academic_support_cases, users, subjects
--
-- Idempotent:
--   - Uses CREATE TABLE IF NOT EXISTS for all tables.
--
-- Apply:
--   mysql -u root udm_radar < database/migrations/004_create_workflow_and_support_tables.sql

-- 1. feedback_messages: Thread messages between students, faculty, and administrators
CREATE TABLE IF NOT EXISTS `feedback_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `feedback_id` (`feedback_id`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `feedback_messages_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedback_reports` (`id`),
  CONSTRAINT `feedback_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. feedback_status_history: Audit history of status changes on feedback tickets
CREATE TABLE IF NOT EXISTS `feedback_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `feedback_id` (`feedback_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `feedback_status_history_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedback_reports` (`id`),
  CONSTRAINT `feedback_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. pending_grade_batches: Faculty grade encoding submissions awaiting administrator approval
CREATE TABLE IF NOT EXISTS `pending_grade_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section` varchar(20) NOT NULL,
  `term_type` enum('prelim','midterm','prefinal','final_grade') NOT NULL,
  `submission_type` varchar(30) NOT NULL DEFAULT 'initial_encoding',
  `batch_note` text DEFAULT NULL,
  `feedback_id` int(11) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of {student_id, grade, reason, linked_feedback_id}' CHECK (json_valid(`payload`)),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faculty_id` (`faculty_id`),
  KEY `subject_id` (`subject_id`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `pending_grade_batches_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pending_grade_batches_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pending_grade_batches_ibfk_3` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. academic_support_cases: Early intervention and support cases triggered by ML risk predictions
CREATE TABLE IF NOT EXISTS `academic_support_cases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `prediction_id` int(11) DEFAULT NULL,
  `trigger_risk_level` varchar(20) NOT NULL,
  `trigger_predicted_gwa` decimal(3,2) NOT NULL,
  `prediction_source` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `semester` int(11) NOT NULL,
  `assigned_faculty_id` int(11) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closure_note` text DEFAULT NULL,
  `status` enum('needs_review','action_taken','acknowledged','closed') DEFAULT 'needs_review',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `prediction_id` (`prediction_id`),
  KEY `assigned_faculty_id` (`assigned_faculty_id`),
  KEY `closed_by` (`closed_by`),
  CONSTRAINT `academic_support_cases_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `academic_support_cases_ibfk_2` FOREIGN KEY (`prediction_id`) REFERENCES `predictions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_support_cases_ibfk_3` FOREIGN KEY (`assigned_faculty_id`) REFERENCES `users` (`id`),
  CONSTRAINT `academic_support_cases_ibfk_4` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. support_actions: Action records (academic notice, advising, consultation) logged on support cases
CREATE TABLE IF NOT EXISTS `support_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action_type` enum('academic_notice_sent','advising_recommended','consultation_requested','referred_to_support') NOT NULL,
  `notes` text DEFAULT NULL,
  `message_to_student` text DEFAULT NULL,
  `student_acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `support_actions_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `academic_support_cases` (`id`),
  CONSTRAINT `support_actions_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. support_case_referrals: Subject-level faculty referrals linked to an academic support case
CREATE TABLE IF NOT EXISTS `support_case_referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `subject_risk_level` varchar(20) NOT NULL,
  `latest_term_checked` varchar(20) NOT NULL,
  `latest_term_grade` decimal(5,2) NOT NULL,
  `status` enum('needs_review','action_taken','acknowledged','closed') DEFAULT 'needs_review',
  `message_to_student` text DEFAULT NULL,
  `student_acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_referral` (`case_id`,`faculty_id`,`subject_id`,`section`),
  KEY `faculty_id` (`faculty_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `support_case_referrals_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `academic_support_cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_case_referrals_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_case_referrals_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. support_status_history: Status transition audit trail for academic support cases
CREATE TABLE IF NOT EXISTS `support_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `support_status_history_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `academic_support_cases` (`id`),
  CONSTRAINT `support_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- VERIFICATION:
--   SHOW TABLES LIKE 'support_%';
--   SHOW TABLES LIKE 'feedback_%';
--   SHOW TABLES LIKE 'pending_grade_batches';
--   SHOW TABLES LIKE 'academic_support_cases';
--
-- ---------------------------------------------------------------------------
-- ROLLBACK (drop child tables first to respect foreign key constraints):
--
--   DROP TABLE IF EXISTS support_status_history;
--   DROP TABLE IF EXISTS support_case_referrals;
--   DROP TABLE IF EXISTS support_actions;
--   DROP TABLE IF EXISTS academic_support_cases;
--   DROP TABLE IF EXISTS pending_grade_batches;
--   DROP TABLE IF EXISTS feedback_status_history;
--   DROP TABLE IF EXISTS feedback_messages;
