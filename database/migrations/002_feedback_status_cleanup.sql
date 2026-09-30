-- 002_feedback_status_cleanup.sql
-- Remediation P5: narrowly scoped repair of two feedback_reports rows whose
-- status is an empty string despite a populated resolved_at.
--
-- Evidence (verified 2026-09-30, sanitized inspection):
--   * Both rows are Student-submitted, category data_issue.
--   * title, subject_id, grade_period are all NULL; no messages, no status
--     history, no linked corrections.
--   * Both carry a populated resolved_at (2026-07-30 and 2026-08-06).
--   * The only canonical terminal states in live data are resolved/rejected;
--     there is no rejection evidence for either row.
--
-- Approved classification: resolved for both. Categories (data_issue) are
-- deliberately NOT touched.
--
-- The guard makes this idempotent: re-running after the fix updates nothing.
--
-- NOTE: a CHECK constraint on feedback_reports.status is deliberately NOT
-- added here. The full workflow vocabulary (open, faculty_review,
-- awaiting_admin, resolved, rejected) still needs a repository-wide audit of
-- every routing path before it is constrained. See config/constants.php
-- FEEDBACK_STATUSES / FEEDBACK_TERMINAL_STATUSES.

UPDATE feedback_reports
SET status = 'resolved'
WHERE id IN (5, 6)
  AND status = ''
  AND resolved_at IS NOT NULL;

-- Verification query (run after applying):
--   SELECT id, status, resolved_at FROM feedback_reports WHERE id IN (5, 6);
-- Expected: both rows show status = 'resolved'.
