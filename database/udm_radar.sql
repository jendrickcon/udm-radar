
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_change_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `target_type` enum('student','faculty') NOT NULL,
  `target_id` int(11) NOT NULL,
  `field_changed` varchar(50) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL COMMENT 'Optional context for this log entry ??? e.g. "Confirmed correction: <reason>" when the change originated from a pending_corrections approval. NULL for direct Phase 3 edits (name/email/status/class loads), which have no separate reason field.',
  `linked_feedback_id` int(11) DEFAULT NULL,
  `status` enum('unreviewed','acknowledged') NOT NULL DEFAULT 'unreviewed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `target` (`target_type`,`target_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for admin-made edits to student/faculty records. One row per field changed, never deleted, only marked acknowledged.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_class_loads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_user_id` int(11) NOT NULL COMMENT 'FK -> users.id (role=faculty).',
  `subject_id` int(11) NOT NULL COMMENT 'FK -> subjects.id.',
  `section` varchar(20) NOT NULL COMMENT 'Free-text section string, same convention (and same lack of a real sections table) as student_profiles.section.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_class_load` (`faculty_user_id`,`subject_id`,`section`),
  KEY `faculty_user_id` (`faculty_user_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `faculty_class_loads_ibfk_1` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_class_loads_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One row per (faculty, subject, section) teaching assignment. This is the ONLY source of truth for "who teaches what to whom" ??? supersedes the old faculty_sections/faculty_subjects design, which could not express "teaches ITE331 to IT-31 and IT-32, but ITE332 only to IT-33."';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback_messages` (
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
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submitted_by` int(11) NOT NULL,
  `category` enum('grade_concern','data_issue','general') NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `grade_period` enum('prelim','midterm','prefinal','final_grade') DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('open','faculty_review','correction_proposed','awaiting_admin','resolved','rejected') DEFAULT 'open',
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `submitted_by` (`submitted_by`),
  KEY `resolved_by` (`resolved_by`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `feedback_reports_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `feedback_reports_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `feedback_reports_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback_status_history` (
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT 'FK -> users.id (NOT student_profiles.id ??? there is no direct link to student_profiles from this table; join through users.id = student_profiles.user_id if section/course/etc. is needed).',
  `subject_id` int(11) NOT NULL COMMENT 'FK -> subjects.id.',
  `school_year` varchar(15) DEFAULT NULL COMMENT 'e.g. "2026-2027".',
  `semester` tinyint(4) DEFAULT NULL COMMENT '1 or 2.',
  `prelim` decimal(5,2) DEFAULT NULL COMMENT 'Raw 0-100 percentage, NEVER a 1.00-4.00 point value. Convert via normalizeTermGrade() before any math or display.',
  `midterm` decimal(5,2) DEFAULT NULL COMMENT 'Raw 0-100 percentage, same rule as prelim.',
  `prefinal` decimal(5,2) DEFAULT NULL COMMENT 'Raw 0-100 percentage, same rule as prelim.',
  `final_grade` varchar(10) DEFAULT NULL COMMENT 'Mixed point (4.00-1.00), textual status (INC, DRP, P, DO, DU, FA, UD), legacy 0.00, or NULL if in-progress',
  `risk_level` enum('LOW','MODERATE','HIGH') DEFAULT 'LOW' COMMENT 'Snapshot at time of last encode/patch ??? NOT guaranteed to match live computeRiskFromAvg() on the current prelim/final value if thresholds changed since. Treat as a cache, recompute live where correctness matters.',
  `is_current` tinyint(1) DEFAULT 0 COMMENT 'Marks "this is the in-progress term to show on dashboards." Not enforced by any constraint ??? nothing stops two different terms from both being flagged 1 for the same student (this happened once; see fix_duplicate_semester.sql). getCurrentTerm() in config/constants.php can cross-check this flag against the actual calendar date.',
  `encoded_by` int(11) DEFAULT NULL COMMENT 'FK -> users.id (the faculty who entered this row). No _id suffix, unlike every other FK column in this schema ??? naming inconsistency, kept as-is to avoid touching every PHP reference to $g[''encoded_by''].',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade_grain` (`student_id`,`subject_id`,`school_year`,`semester`),
  KEY `subject_id` (`subject_id`),
  KEY `encoded_by` (`encoded_by`),
  KEY `fk_grades_user_id` (`student_id`),
  CONSTRAINT `fk_grades_user_id` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`encoded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_grades_final_grade_domain` CHECK (`final_grade` is null or cast(`final_grade` as char charset binary) in ('4.00','3.75','3.50','3.25','3.00','2.75','2.50','2.25','2.00','1.75','1.50','1.25','1.00','INC','DRP','P','DO','DU','FA','UD','0.00'))
) ENGINE=InnoDB AUTO_INCREMENT=52101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One row per student per subject per term. prelim/midterm/prefinal are ALWAYS raw 0-100 percentages; final_grade is ALWAYS already point-scale (1.00-4.00) once set. See normalizeTermGrade()/normalizePointGrade() in config/constants.php ??? never guess the scale from magnitude.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'FK -> users.id.',
  `token` varchar(10) NOT NULL COMMENT 'The OTP code itself.',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Expiry time.',
  `used` tinyint(1) DEFAULT 0 COMMENT 'Whether this token has already been consumed.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One-time password / verification tokens. Table exists but is not currently used by login.php ??? likely scaffolding for a future MFA feature.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_corrections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback_id` int(11) DEFAULT NULL,
  `proposed_by` int(11) NOT NULL,
  `target_type` enum('grade','student_section','student_year_level') NOT NULL,
  `target_id` int(11) NOT NULL COMMENT 'grade.id for grade type; student user_id for section/year_level',
  `field_changed` varchar(50) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `linked_feedback_id` int(11) DEFAULT NULL,
  `status` enum('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `proposed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `target` (`target_type`,`target_id`),
  KEY `status` (`status`),
  KEY `pending_corrections_ibfk_1` (`proposed_by`),
  KEY `pending_corrections_ibfk_2` (`resolved_by`),
  KEY `linked_feedback_id` (`linked_feedback_id`),
  KEY `fk_pending_feedback` (`feedback_id`),
  CONSTRAINT `fk_pending_feedback` FOREIGN KEY (`feedback_id`) REFERENCES `feedback_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pending_corrections_ibfk_1` FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `pending_corrections_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `pending_corrections_ibfk_3` FOREIGN KEY (`linked_feedback_id`) REFERENCES `feedback_reports` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Proposed corrections to registrar-owned values (grades, section, year level) ??? inert until manually confirmed as officially reflected.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_grade_batches` (
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `predictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT 'FK -> users.id, same convention as grades.student_id.',
  `predicted_gwa` decimal(4,2) DEFAULT NULL COMMENT 'Currently produced by predictFinalGradeHeuristic() (a documented 50/50 blend, NOT machine learning) ??? see config/constants.php. Will eventually come from the real Decision Tree model once python_ml/ is built.',
  `risk_level` enum('LOW','MODERATE','HIGH') DEFAULT NULL COMMENT 'Should equal computeRiskFromAvg(predicted_gwa) at time of generation ??? same "cache, not live truth" caveat as grades.risk_level.',
  `latin_honor` varchar(50) DEFAULT NULL COMMENT 'Should equal getLatinHonor(predicted_gwa) at time of generation.',
  `irregular_prob` decimal(5,2) DEFAULT NULL COMMENT '0-100. Currently a seeded heuristic (higher if status=Irregular), not a real model output.',
  `prediction_source` varchar(30) NOT NULL COMMENT 'Provenance of prediction: decision_tree (ML model), heuristic (legacy 50/50 blend), calculation_fallback (deterministic grade calculation).',
  `data_completeness` varchar(20) NOT NULL COMMENT 'Generation completeness: complete, historical_only, prelim_only, legacy_unknown.',
  `is_provisional` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag: 1 if provisional estimate based on partial inputs, 0 if complete or standard.',
  `provisional_basis` varchar(30) DEFAULT NULL COMMENT 'Input basis when provisional: historical_gwa, current_prelim_avg, or NULL.',
  `input_subject_count` int(10) unsigned DEFAULT NULL COMMENT 'Count of contributing subjects with grades at generation time (NULL for legacy).',
  `expected_subject_count` int(10) unsigned DEFAULT NULL COMMENT 'Count of enrolled subjects in current term at generation time (NULL for legacy).',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'The disambiguator for this being an append-only log ??? always sort/filter by this to find the current prediction for a student.',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `predictions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_predictions_completeness_valid` CHECK (cast(`data_completeness` as char charset binary) in ('complete','historical_only','prelim_only','legacy_unknown')),
  CONSTRAINT `chk_predictions_is_provisional_valid` CHECK (`is_provisional` in (0,1)),
  CONSTRAINT `chk_predictions_provisional_basis_valid` CHECK (`provisional_basis` is null or cast(`provisional_basis` as char charset binary) in ('historical_gwa','current_prelim_avg')),
  CONSTRAINT `chk_predictions_completeness_consistency` CHECK (cast(`data_completeness` as char charset binary) in ('complete','legacy_unknown') and `is_provisional` = 0 and `provisional_basis` is null or cast(`data_completeness` as char charset binary) = 'historical_only' and `is_provisional` = 1 and cast(`provisional_basis` as char charset binary) = 'historical_gwa' or cast(`data_completeness` as char charset binary) = 'prelim_only' and `is_provisional` = 1 and cast(`provisional_basis` as char charset binary) = 'current_prelim_avg'),
  CONSTRAINT `chk_predictions_subject_counts` CHECK (`input_subject_count` is null or `expected_subject_count` is null or `input_subject_count` <= `expected_subject_count`),
  CONSTRAINT `chk_predictions_source_valid` CHECK (cast(`prediction_source` as char charset binary) in ('heuristic','decision_tree','calculation_fallback'))
) ENGINE=InnoDB AUTO_INCREMENT=26406 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='APPEND-ONLY LOG: a student can have MULTIPLE rows over time, one per prediction run. There is no "latest" flag ??? always query ORDER BY generated_at DESC LIMIT 1 to get the current one. Querying this table without that ORDER BY/LIMIT will silently include stale historical predictions.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'FK -> users.id. This is the join key linking a student profile back to their login account.',
  `student_number` varchar(20) NOT NULL COMMENT 'Duplicates users.user_id for this student ??? same value, stored twice. Kept for query convenience (avoids a join in places that only need the student number), not a separate identifier.',
  `course` enum('BSIT - Software Development','BSIT - Data Science','BSIT - Cyber Security') NOT NULL DEFAULT 'BSIT - Software Development' COMMENT 'Curriculum track: BSIT - Software Development, BSIT - Data Science, or BSIT - Cyber Security',
  `college` varchar(100) DEFAULT NULL COMMENT 'e.g. "CCS". Always CCS in current seed data; column exists for multi-college scalability.',
  `year_level` tinyint(4) DEFAULT NULL COMMENT '1-4. System scope is 3rd-year students only (see Chapter 1 delimitations) ??? 1st/2nd/4th-year rows exist only for the one reference account (Jendrick).',
  `section` varchar(10) DEFAULT NULL COMMENT 'e.g. "IT-31". Free-text, not a foreign key to any sections table ??? there is no sections table; section is just a string students/faculty_class_loads happen to share.',
  `status` enum('Regular','Irregular') DEFAULT 'Regular' COMMENT 'Irregular = has at least one failed subject on record requiring a retake.',
  `record_status` enum('Active','Archived','Graduated') NOT NULL DEFAULT 'Active',
  `current_gwa` decimal(4,2) DEFAULT NULL COMMENT 'Cumulative weighted GWA (see computeWeightedGWA() in config/constants.php) from COMPLETED semesters only ??? does not include the in-progress current term. Must be derived from actual grades rows; never hand-set directly.',
  `predicted_gwa` decimal(4,2) DEFAULT NULL COMMENT 'DEPRECATED / UNUSED ??? no page in the application reads this column. The real predicted GWA lives in the predictions table instead. Safe to ignore; candidate for removal once confirmed nothing future needs it.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_record_status` (`record_status`),
  KEY `idx_status_record` (`status`,`record_status`),
  CONSTRAINT `fk_profile_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_student_profiles_course_valid` CHECK (`course` in ('BSIT - Software Development','BSIT - Data Science','BSIT - Cyber Security'))
) ENGINE=InnoDB AUTO_INCREMENT=1798993934 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One row per student. Extends a users row with academic-specific fields. There is no equivalent extension table for faculty/admin.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT 'e.g. "ITE331". Elective slots use a track-specific code (SD351/CY351/DS351 = Machine Learning under the Software Dev/Cybersecurity/Data Science track) rather than a generic "Elective 1" label.',
  `title` varchar(150) NOT NULL,
  `units` tinyint(4) NOT NULL COMMENT 'Credit units. GWA math weights every grade by this ??? a 1-unit and 3-unit subject do NOT count equally (see computeWeightedGWA()).',
  `year_level` tinyint(4) DEFAULT NULL COMMENT 'Which curriculum year this subject belongs to (1-4) ??? independent of any individual student''s current year.',
  `semester` tinyint(4) DEFAULT NULL COMMENT '1 or 2 ??? which semester of that curriculum year.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Full BSIT curriculum, Year 1 through Year 4, both semesters.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_actions` (
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
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_case_referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `subject_risk_level` varchar(20) NOT NULL,
  `latest_term_checked` varchar(20) NOT NULL,
  `latest_term_grade` decimal(5,2) NOT NULL,
  `reason_code` varchar(50) DEFAULT NULL COMMENT 'Structured faculty-observed reason for support notice.',
  `notice_issued_at` datetime DEFAULT NULL COMMENT 'Timestamp when faculty issued the academic notice to the student.',
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
  CONSTRAINT `support_case_referrals_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_referral_reason_valid` CHECK (`reason_code` is null or cast(`reason_code` as char charset binary) in ('low_assessment_score','missing_course_requirement','performance_needs_review','incomplete_grade_data','consultation_recommended','other_academic_concern'))
) ENGINE=InnoDB AUTO_INCREMENT=318 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_status_history` (
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
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(30) NOT NULL COMMENT 'Login username ??? a student number (synthetic student identifier), a faculty ID (e.g. faculty01), or "admin". NOT the same thing as users.id.',
  `password_hash` varchar(255) NOT NULL COMMENT 'bcrypt hash, never plaintext.',
  `role` enum('student','faculty','admin') NOT NULL COMMENT 'Determines which portal (student/, faculty/, admin/) this account can log into.',
  `first_name` varchar(50) NOT NULL COMMENT 'Real stored column.',
  `middle_name` varchar(50) DEFAULT NULL COMMENT 'Real stored column, optional.',
  `last_name` varchar(50) NOT NULL COMMENT 'Real stored column.',
  `name` varchar(160) GENERATED ALWAYS AS (trim(concat_ws(' ',`first_name`,`middle_name`,`last_name`))) VIRTUAL COMMENT 'GENERATED column (CONCAT_WS of first/middle/last) ??? read-only, cannot appear in an INSERT column list. Kept so pages that only need a display name do not have to know about the split columns.',
  `email` varchar(150) DEFAULT NULL COMMENT 'Optional; students seeded from masterlist data have no real email on file.',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Soft-disable flag. Not currently enforced anywhere in login.php ??? an inactive account can still log in unless that check is added.',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Account creation time, not enrollment date.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifier` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1795050442 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='One row per login (student, faculty, or admin). Role-specific detail for students lives in student_profiles; faculty/admin have no extension table.';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_support_cases` (
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
  CONSTRAINT `academic_support_cases_ibfk_4` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `chk_support_cases_source_valid` CHECK (cast(`prediction_source` as char charset binary) in ('heuristic','decision_tree','calculation_fallback'))
) ENGINE=InnoDB AUTO_INCREMENT=277 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `export_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `format` varchar(20) NOT NULL,
  `filters_json` text DEFAULT NULL,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eal_user` (`user_id`),
  KEY `idx_eal_type` (`report_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
