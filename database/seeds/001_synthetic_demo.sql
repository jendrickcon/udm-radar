-- =============================================================================
-- database/seeds/001_synthetic_demo.sql
-- UDM-RADAR: Starter Synthetic Demonstration Seed
--
-- PURPOSE:
-- Populates a clean, freshly imported development database with minimal
-- synthetic accounts and curriculum records needed to access and test the
-- Administrator, Faculty, and Student portals.
--
-- INSTITUTIONAL PRIVACY & GOVERNANCE:
-- - Prototype decision support on synthetic academic records.
-- - Contains 100% synthetic identities; ZERO genuine student numbers, real
--   names, faculty identities, or registrar evaluations.
-- - Student namespace: '24-00-xxx' (@synthetic.udm.edu.ph).
-- - Demonstrative only: does not constitute official academic standing.
--
-- TEMPORARY LOGIN CREDENTIALS (CHANGE IN SHARED/TEAM ENVIRONMENTS):
-- - Administrator: admin       / AdminPassword2026!
-- - Faculty:       faculty01   / FacultyPassword2026!
-- - Faculty:       faculty02   / FacultyPassword2026!
-- - Student:       24-00-001   / StudentPassword2026!
-- - Student:       24-00-002   / StudentPassword2026!
-- - Student:       24-00-003   / StudentPassword2026!
--
-- SAFETY INVARIANT:
-- - Refuses execution if target database already contains user accounts.
-- - Does NOT drop tables, truncate records, or alter schema.
-- =============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS seed_001_synthetic_demo$$

CREATE PROCEDURE seed_001_synthetic_demo()
BEGIN
    DECLARE v_user_count INT DEFAULT 0;

    -- Safety Gate: Refuse execution if users table already contains accounts
    SELECT COUNT(*) INTO v_user_count FROM `users`;
    IF v_user_count > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Safety refusal: target database already contains user accounts. Seed execution aborted to prevent overwriting existing data.';
    END IF;

    -- -------------------------------------------------------------------------
    -- 1. USERS (Admin, Faculty, Students)
    -- Passwords hashed via standard password_hash(..., PASSWORD_BCRYPT)
    -- -------------------------------------------------------------------------
    INSERT INTO `users` (`id`, `user_id`, `password_hash`, `role`, `first_name`, `middle_name`, `last_name`, `email`, `is_active`) VALUES
    (1, 'admin',     '$2y$10$u1FJUF7laDHMP2g9YxBYPOLoiAvtqdnOy0eJZ7Q/PKqUuODusjUZO', 'admin',   'Demo',         'System',    'Administrator', 'admin@synthetic.udm.edu.ph',     1),
    (2, 'faculty01', '$2y$10$qgVMNOYircuNQIJSpWd5muvLit1rBBDDzFt1rQOAMyI8XeO3M78gy', 'faculty', 'DemoFaculty',  'Academic',  'Santos',        'faculty01@synthetic.udm.edu.ph', 1),
    (3, 'faculty02', '$2y$10$qgVMNOYircuNQIJSpWd5muvLit1rBBDDzFt1rQOAMyI8XeO3M78gy', 'faculty', 'DemoFaculty',  'Research',  'Mendoza',       'faculty02@synthetic.udm.edu.ph', 1),
    (4, '24-00-001', '$2y$10$739fxrUAs.3s7oW4BT2Uzubefid07nmuKV.so2qHLz4MEJkx9Zj4a', 'student', 'DemoStudent',  'Alpha',     'Reyes',         'student01@synthetic.udm.edu.ph', 1),
    (5, '24-00-002', '$2y$10$739fxrUAs.3s7oW4BT2Uzubefid07nmuKV.so2qHLz4MEJkx9Zj4a', 'student', 'DemoStudent',  'Beta',      'Cruz',          'student02@synthetic.udm.edu.ph', 1),
    (6, '24-00-003', '$2y$10$739fxrUAs.3s7oW4BT2Uzubefid07nmuKV.so2qHLz4MEJkx9Zj4a', 'student', 'DemoStudent',  'Gamma',     'Del Rosario',   'student03@synthetic.udm.edu.ph', 1);

    -- -------------------------------------------------------------------------
    -- 2. STUDENT PROFILES
    -- -------------------------------------------------------------------------
    INSERT INTO `student_profiles` (`id`, `user_id`, `student_number`, `course`, `college`, `year_level`, `section`, `status`, `record_status`, `current_gwa`, `predicted_gwa`) VALUES
    (1, 4, '24-00-001', 'BSIT - Software Development', 'CCS', 3, 'IT-31', 'Regular',   'Active', 2.10, NULL),
    (2, 5, '24-00-002', 'BSIT - Software Development', 'CCS', 3, 'IT-31', 'Irregular', 'Active', 1.85, NULL),
    (3, 6, '24-00-003', 'BSIT - Software Development', 'CCS', 3, 'IT-31', 'Regular',   'Active', 1.65, NULL);

    -- -------------------------------------------------------------------------
    -- 3. CURRICULUM SUBJECTS (BSIT Software Development - Years 1-3)
    -- -------------------------------------------------------------------------
    INSERT INTO `subjects` (`id`, `code`, `title`, `units`, `year_level`, `semester`) VALUES
    -- Year 1 Semester 1
    (1,  'CC101',  'Introduction to Computing',                 3, 1, 1),
    (2,  'CC102',  'Fundamentals of Programming',               3, 1, 1),
    (3,  'GED101', 'Purposive Communication',                   3, 1, 1),
    (4,  'MATH101','Mathematics in the Modern World',           3, 1, 1),
    -- Year 1 Semester 2
    (5,  'CC103',  'Intermediate Programming',                  3, 1, 2),
    (6,  'CC104',  'Data Structures and Algorithms',            3, 1, 2),
    (7,  'IT101',  'Discrete Mathematics',                      3, 1, 2),
    -- Year 2 Semester 1
    (8,  'IT201',  'Object-Oriented Programming',               3, 2, 1),
    (9,  'IT202',  'Database Systems 1',                        3, 2, 1),
    (10, 'IT203',  'Platform Technologies',                     3, 2, 1),
    -- Year 2 Semester 2
    (11, 'IT204',  'Advanced Database Systems',                 3, 2, 2),
    (12, 'IT205',  'Web Development 1',                         3, 2, 2),
    (13, 'IT206',  'Networking Basics',                         3, 2, 2),
    -- Year 3 Semester 1 (Current Active Academic Evaluation Period)
    (14, 'ITE321', 'Information and Security Assurance 1',       3, 3, 1),
    (15, 'ITE322', 'Networking 1',                               3, 3, 1),
    (16, 'ITE323', 'Human Computer Interaction',                 3, 3, 1),
    (17, 'ITE331', 'System Analysis and Design',                 3, 3, 1),
    (18, 'ITE332', 'Seminar in Information Technology',          3, 3, 1),
    (19, 'ITE341', 'Statistics and Probability',                 3, 3, 1),
    (20, 'SD351',  'Machine Learning (Elective 1)',              3, 3, 1),
    (21, 'UID105', 'Achievement and Passion',                    1, 3, 1);

    -- -------------------------------------------------------------------------
    -- 4. FACULTY CLASS LOADS (Teaching Assignments for Section IT-31)
    -- -------------------------------------------------------------------------
    INSERT INTO `faculty_class_loads` (`faculty_user_id`, `subject_id`, `section`) VALUES
    (2, 14, 'IT-31'), -- faculty01 teaches ITE321
    (2, 15, 'IT-31'), -- faculty01 teaches ITE322
    (2, 17, 'IT-31'), -- faculty01 teaches ITE331
    (3, 16, 'IT-31'), -- faculty02 teaches ITE323
    (3, 19, 'IT-31'), -- faculty02 teaches ITE341
    (3, 20, 'IT-31'); -- faculty02 teaches SD351

    -- -------------------------------------------------------------------------
    -- 5. HISTORICAL & CURRENT GRADES
    -- -------------------------------------------------------------------------
    -- Historical completed terms for student 4 (24-00-001):
    INSERT INTO `grades` (`student_id`, `subject_id`, `school_year`, `semester`, `prelim`, `midterm`, `prefinal`, `final_grade`, `risk_level`, `is_current`, `encoded_by`) VALUES
    (4, 1,  '2024-2025', 1, 88.00, 90.00, 89.00, '2.25', 'LOW', 0, 2),
    (4, 2,  '2024-2025', 1, 86.00, 87.00, 88.00, '2.00', 'LOW', 0, 2),
    (4, 3,  '2024-2025', 1, 92.00, 91.00, 93.00, '2.75', 'LOW', 0, 2),
    (4, 4,  '2024-2025', 1, 85.00, 84.00, 86.00, '2.00', 'LOW', 0, 2),
    (4, 5,  '2024-2025', 2, 87.00, 88.00, 89.00, '2.25', 'LOW', 0, 2),
    (4, 6,  '2024-2025', 2, 84.00, 85.00, 86.00, '2.00', 'LOW', 0, 2),
    (4, 7,  '2024-2025', 2, 89.00, 90.00, 90.00, '2.50', 'LOW', 0, 2),
    (4, 8,  '2025-2026', 1, 86.00, 87.00, 88.00, '2.00', 'LOW', 0, 2),
    (4, 9,  '2025-2026', 1, 88.00, 89.00, 89.00, '2.25', 'LOW', 0, 2),
    (4, 10, '2025-2026', 1, 85.00, 86.00, 86.00, '2.00', 'LOW', 0, 2),
    (4, 11, '2025-2026', 2, 87.00, 88.00, 88.00, '2.25', 'LOW', 0, 2),
    (4, 12, '2025-2026', 2, 89.00, 90.00, 91.00, '2.50', 'LOW', 0, 2),
    (4, 13, '2025-2026', 2, 84.00, 85.00, 85.00, '2.00', 'LOW', 0, 2);

    -- Current In-Progress term for student 4 (24-00-001) - Low Risk:
    INSERT INTO `grades` (`student_id`, `subject_id`, `school_year`, `semester`, `prelim`, `midterm`, `prefinal`, `final_grade`, `risk_level`, `is_current`, `encoded_by`) VALUES
    (4, 14, '2026-2027', 1, 89.00, 90.00, NULL, NULL, 'LOW', 1, 2),
    (4, 15, '2026-2027', 1, 87.00, 88.00, NULL, NULL, 'LOW', 1, 2),
    (4, 16, '2026-2027', 1, 91.00, 92.00, NULL, NULL, 'LOW', 1, 3),
    (4, 17, '2026-2027', 1, 86.00, 87.00, NULL, NULL, 'LOW', 1, 2),
    (4, 18, '2026-2027', 1, 90.00, 90.00, NULL, NULL, 'LOW', 1, 2),
    (4, 19, '2026-2027', 1, 88.00, 89.00, NULL, NULL, 'LOW', 1, 3),
    (4, 20, '2026-2027', 1, 85.00, 86.00, NULL, NULL, 'LOW', 1, 3),
    (4, 21, '2026-2027', 1, 92.00, 93.00, NULL, NULL, 'LOW', 1, 2);

    -- Current In-Progress term for student 5 (24-00-002) - Moderate Risk:
    INSERT INTO `grades` (`student_id`, `subject_id`, `school_year`, `semester`, `prelim`, `midterm`, `prefinal`, `final_grade`, `risk_level`, `is_current`, `encoded_by`) VALUES
    (5, 14, '2026-2027', 1, 80.00, 81.00, NULL, NULL, 'MODERATE', 1, 2),
    (5, 15, '2026-2027', 1, 78.00, 79.00, NULL, NULL, 'MODERATE', 1, 2),
    (5, 16, '2026-2027', 1, 82.00, 83.00, NULL, NULL, 'LOW',      1, 3),
    (5, 17, '2026-2027', 1, 76.00, 77.00, NULL, NULL, 'MODERATE', 1, 2),
    (5, 18, '2026-2027', 1, 80.00, 80.00, NULL, NULL, 'MODERATE', 1, 2),
    (5, 19, '2026-2027', 1, 77.00, 78.00, NULL, NULL, 'MODERATE', 1, 3),
    (5, 20, '2026-2027', 1, 79.00, 80.00, NULL, NULL, 'MODERATE', 1, 3),
    (5, 21, '2026-2027', 1, 85.00, 86.00, NULL, NULL, 'LOW',      1, 2);

    -- Current In-Progress term for student 6 (24-00-003) - High Risk / Support Trigger:
    INSERT INTO `grades` (`student_id`, `subject_id`, `school_year`, `semester`, `prelim`, `midterm`, `prefinal`, `final_grade`, `risk_level`, `is_current`, `encoded_by`) VALUES
    (6, 14, '2026-2027', 1, 74.00, 74.00, NULL, NULL, 'HIGH', 1, 2),
    (6, 15, '2026-2027', 1, 75.00, 75.00, NULL, NULL, 'HIGH', 1, 2),
    (6, 16, '2026-2027', 1, 78.00, 78.00, NULL, NULL, 'MODERATE', 1, 3),
    (6, 17, '2026-2027', 1, 73.00, 72.00, NULL, NULL, 'HIGH', 1, 2),
    (6, 18, '2026-2027', 1, 80.00, 80.00, NULL, NULL, 'MODERATE', 1, 2),
    (6, 19, '2026-2027', 1, 74.00, 73.00, NULL, NULL, 'HIGH', 1, 3),
    (6, 20, '2026-2027', 1, 76.00, 75.00, NULL, NULL, 'MODERATE', 1, 3),
    (6, 21, '2026-2027', 1, 82.00, 82.00, NULL, NULL, 'LOW', 1, 2);

    -- -------------------------------------------------------------------------
    -- 6. PREDICTIONS (Complies with Migration 007 & 008 constraints)
    -- -------------------------------------------------------------------------
    INSERT INTO `predictions` (`id`, `student_id`, `predicted_gwa`, `risk_level`, `latin_honor`, `irregular_prob`, `prediction_source`, `data_completeness`, `is_provisional`, `provisional_basis`, `input_subject_count`, `expected_subject_count`) VALUES
    (1, 4, 2.15, 'LOW',      NULL,  5.00, 'calculation_fallback', 'complete', 0, NULL, 8, 8),
    (2, 5, 1.85, 'MODERATE', NULL, 65.00, 'calculation_fallback', 'complete', 0, NULL, 8, 8),
    (3, 6, 1.65, 'HIGH',     NULL, 40.00, 'calculation_fallback', 'complete', 0, NULL, 8, 8);

    -- -------------------------------------------------------------------------
    -- 7. ACADEMIC SUPPORT CASE & REFERRAL (For High-Risk Student 6)
    -- -------------------------------------------------------------------------
    INSERT INTO `academic_support_cases` (`id`, `student_id`, `prediction_id`, `trigger_risk_level`, `trigger_predicted_gwa`, `prediction_source`, `school_year`, `semester`, `assigned_faculty_id`, `status`) VALUES
    (1, 6, 3, 'HIGH', 1.65, 'calculation_fallback', '2026-2027', 1, 2, 'needs_review');

    INSERT INTO `support_case_referrals` (`id`, `case_id`, `faculty_id`, `subject_id`, `section`, `subject_risk_level`, `latest_term_checked`, `latest_term_grade`, `reason_code`, `notice_issued_at`, `status`, `message_to_student`) VALUES
    (1, 1, 2, 17, 'IT-31', 'HIGH', 'midterm', 72.00, 'low_assessment_score', NULL, 'needs_review', 'Please consult during office hours regarding System Analysis deliverable.');

    INSERT INTO `support_status_history` (`case_id`, `changed_by`, `old_status`, `new_status`, `note`) VALUES
    (1, 2, 'needs_review', 'needs_review', 'Initial support case auto-generated from high-risk triage');

END$$

DELIMITER ;

CALL seed_001_synthetic_demo();
DROP PROCEDURE IF EXISTS seed_001_synthetic_demo;
