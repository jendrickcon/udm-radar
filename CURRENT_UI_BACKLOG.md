# UDM-RADAR — Verified Current UI/UX Backlog

This backlog registers all verified findings on branch `feat/cohort-scaffolding` (`f4009d0`). Every item is classified by exact current evidence, severity, user impact, suggested correction, target work package, and formal-evaluation blocking status.

> [!NOTE]
> This report was produced primarily through static interface inspection. Visual, responsive, accessibility, and interaction findings require later browser or Playwright revalidation.

---

## 1. Functional & Technical Bugs (`BUG-CUR`)

### BUG-CUR-01: Admin "Run Predictions" Button Fails CSRF Validation
- **Classification:** Current defect candidate; batch prediction CSRF failure must be reproduced before implementation.
- **Affected Role:** Administrator
- **Current Evidence:** In `admin/index.php:431–435`, the `runBatchPredictions()` JavaScript function executes a POST request via `fetch('../api/batch_predict.php')` with an empty JSON body and no `X-CSRF-TOKEN` header. In `api/batch_predict.php:20–26`, strict CSRF verification is enforced. The call terminates immediately with an HTTP 403 response.
- **Severity:** Critical (P1)
- **User Impact:** Administrator cannot refresh or generate student risk predictions from the dashboard command center.
- **Suggested Correction:** Reproduce with safe HTTP test first. If confirmed, inject `$_SESSION['csrf_token']` into `admin/index.php` and append it as `headers: { 'X-CSRF-TOKEN': csrfToken }` in the fetch payload.
- **Target Phase:** Requires WP-7 (reproduce first) / WP-8
- **Formal-Evaluation Blocking:** **YES** (Core feature non-functional once reproduced)

### MAINT-LIVE-COURSE: Live Database Course Values Require Controlled Maintenance
- **Classification:** Controlled live-development maintenance; not a normal application defect; do not apply migration 006 to live without an approved maintenance window.
- **Affected Role:** Administrator, Faculty
- **Current Evidence:** Live database `udm_radar` has 290 of 291 student profiles with empty strings in `student_profiles.course`. Migration 006 (`006_repair_student_course_values.sql`) was executed against scratch `udm_radar_scratch`, but live remains unmigrated.
- **Severity:** High (Maintenance Task)
- **User Impact:** Admin program analytics and faculty course filters display blank/null program tracks for 99.6% of the student cohort.
- **Suggested Correction:** Prepare separate maintenance procedure with backup, rehearsal, and rollback verification. Do NOT apply migration 006 to live automatically.
- **Target Phase:** Post-Audit Controlled Maintenance / WP-9
- **Formal-Evaluation Blocking:** Pending controlled maintenance window

### MAINT-LIVE-GWA: Cached Live Current GWA Values Require Controlled Maintenance
- **Classification:** Controlled live-development maintenance; do not backfill live `current_gwa` values automatically.
- **Affected Role:** Student, Faculty, Administrator
- **Current Evidence:** Historical cached `student_profiles.current_gwa` values in `udm_radar` may reflect older unweighted or stale calculations. Parity audit script exists with `--backfill` flag, but live data writes are strictly prohibited during normal audits.
- **Severity:** Medium (Maintenance Task)
- **User Impact:** Student and administrator profiles may display slightly divergent historical GWA until live maintenance recalculation is performed.
- **Suggested Correction:** Schedule controlled maintenance window with audit log verification. Do NOT run `--backfill` on live development database.
- **Target Phase:** Post-Audit Controlled Maintenance / WP-9
- **Formal-Evaluation Blocking:** No

### BUG-CUR-03: Missing Domain CHECK Constraint on Live `grades.final_grade`
- **Affected Role:** All Roles (Integrity)
- **Current Evidence:** Live database `udm_radar.grades` lacks the table-level constraint `chk_grades_final_grade_domain`, which is present in `udm_radar_scratch`.
- **Severity:** Medium (P2)
- **User Impact:** Application-level validation prevents invalid input, but database does not strictly enforce canonical grade domain.
- **Suggested Correction:** Execute migration 005 schema patch on live database during approved maintenance window.
- **Target Phase:** Requires WP-8 / WP-9
- **Formal-Evaluation Blocking:** No (Application-level guards prevent malformed writes)

---

## 2. Demonstration & Test Data Scenarios (`DEMO-CUR`)

### DEMO-SUPPORT-SCENARIO: Support Referral Workflow Scenario Staging
- **Classification:** Demonstration preparation; not a confirmed product defect; do not change a real-looking case only for favorable demo evidence.
- **Affected Role:** Student, Faculty, Capstone Presenter
- **Current Evidence:** All 11 `support_case_referrals` in `udm_radar` have status `'needs_review'`. The student query in `student/feedback.php:183–193` filters strictly for `r.status IN ('action_taken', 'acknowledged', 'closed')`. Consequently, the student "Academic Support Notices" tab is permanently empty in current test data.
- **Severity:** Medium (Demo Preparation)
- **User Impact:** Presenter cannot demonstrate student notice receipt and acknowledgment without dedicated demo scenario staging.
- **Suggested Correction:** In a disposable test database or designated synthetic scenario, stage one parent case, at least two subject referrals, one Faculty-issued notice (`action_taken`), one Student acknowledgment, and one resolved referral. Do NOT alter real-looking cases in live development database.
- **Target Phase:** Requires Demonstration Preparation
- **Formal-Evaluation Blocking:** Demo staging required before live presentation

### DEMO-CUR-02: Zero Pending Items in Admin Activity Queues
- **Affected Role:** Administrator, Presenter
- **Current Evidence:** Current database contains 0 pending grade batches and 0 pending corrections.
- **Severity:** Medium (P2)
- **User Impact:** Approvals tab in `admin/activity.php` displays empty state ("All caught up!"); presenter cannot demonstrate batch diff review or confirmation dialogs.
- **Suggested Correction:** Prepare a scripted staging seed with 1 pending batch and 1 pending correction.
- **Target Phase:** Requires Demonstration Preparation
- **Formal-Evaluation Blocking:** No (Empty states render gracefully)

### DEMO-CUR-03: Absence of Persistent "Prototype / Demonstration Mode" Banner
- **Affected Role:** All Roles, Capstone Panel
- **Current Evidence:** Application contains no header or footer notice disclosing that current records and ML models are synthetic prototypes.
- **Severity:** Medium (P2)
- **User Impact:** Panelists may mistake test data for live student privacy violations, or assume the system claims live registrar authority.
- **Suggested Correction:** Add a persistent demonstration banner: *"UDM-RADAR Capstone Prototype — Operating on Synthetic Demonstration Records"*.
- **Target Phase:** Requires Demonstration Preparation / UX Remediation
- **Formal-Evaluation Blocking:** No

---

## 3. Navigation & Task Flow (`NAV-CUR`)

### NAV-CUR-01: Student Dashboard Lacks Direct Link to Grade Concerns from Subject Triage
- **Affected Role:** Student
- **Current Evidence:** In `student/dashboard.php:380–420`, the "Subject Triage" table flags failing subjects with a red "Action Required" badge. The only action button is "Calculator" (opens Grade Goal Calculator). There is no button to ask the instructor a question or file a concern.
- **Severity:** High (P1)
- **User Impact:** A student with a failing prelim must guess that they need to open "Feedback & Support" from the sidebar, find the ticket form, and manually re-select the subject.
- **Suggested Correction:** Add an "Inquire" button in the table row that routes to `student/feedback.php?action=new&subject_id=X&period=prelim`.
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

### NAV-CUR-02: Page Sprawl in Student and Faculty Portals
- **Affected Role:** Student, Faculty
- **Current Evidence:** Student navigation has 6 items (Home, Dashboard, Grades, Trend, Feedback, Settings). Faculty has 7 items. Dashboard and Trend represent overlapping views of performance trajectory.
- **Severity:** Medium (P2)
- **User Impact:** Users must jump between separate top-level pages to answer basic questions about current vs historical standing.
- **Suggested Correction:** Merge "Performance Trend" into a secondary tab on the Dashboard page.
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

---

## 4. UI Copy & Terminology (`COPY-CUR`)

### COPY-CUR-01: Misleading "Encode Grades" Label on Faculty Sidebar
- **Affected Role:** Faculty
- **Current Evidence:** In `includes/sidebar.php` and `faculty/index.php`, the grade submission page is titled "Encode Grades" and sidebar label is "Encode Grades".
- **Severity:** High (P1)
- **User Impact:** Faculty assume this is the official institutional grading portal, causing anxiety over duplicate work and legal authority.
- **Suggested Correction:** Rename sidebar label and page title to **"Term Grade Monitoring"** or **"Term Grade Submission"**, with subtitle: *"Submit Preliminary, Midterm, or Pre-Final percentages for departmental monitoring. Does not alter official university records."*
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

### COPY-CUR-02: "AI-Based Prediction" Headline Overstates Maturity
- **Affected Role:** Student, Faculty, Capstone Panel
- **Current Evidence:** `student/dashboard.php:252` displays a prominent badge titled `AI-Based Prediction`.
- **Severity:** Medium (P2)
- **User Impact:** Induces unwarranted authority for a 4-feature Decision Tree trained on 150 synthetic rows. Panelists will aggressively probe model validity.
- **Suggested Correction:** Re-title badge to **"Projected Semester GWA (Model-Assisted)"** with tooltip: *"Estimated trajectory based on historical GWA, current prelim average, and past course attempts."*
- **Target Phase:** Requires WP-5
- **Formal-Evaluation Blocking:** No

### COPY-CUR-03: Student Dashboard Missing Prediction Coverage and Freshness
- **Affected Role:** Student
- **Current Evidence:** `student/dashboard.php:278` shows a static predicted GWA number without indicating how many subjects contributed or when it was computed.
- **Severity:** Medium (P2)
- **User Impact:** If only 2 of 5 prelim grades are encoded, the student does not realize the prediction is based on partial data.
- **Suggested Correction:** Add subtitle beneath prediction card: *"Estimated from X of Y enrolled subjects as of [Date]"*.
- **Target Phase:** Requires WP-7
- **Formal-Evaluation Blocking:** No

### COPY-CUR-04: Confusing "Current Academic Standing" Label on Cumulative GWA Card
- **Affected Role:** Student
- **Current Evidence:** In `student/dashboard.php:275`, the "Cumulative GWA" card has subtitle *"Current Academic Standing"*.
- **Severity:** Medium (P2)
- **User Impact:** Students assume "Current Academic Standing" includes their current semester grades, when it strictly reflects completed historical semesters.
- **Suggested Correction:** Change subtitle to *"Historical Cumulative GWA (Completed Semesters)"*.
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

### COPY-CUR-05: Missing Monitoring-Scope Notice on Confirmation Messages
- **Affected Role:** Administrator, Faculty
- **Current Evidence:** Flash banners in `admin/activity.php` simply say *"Batch successfully approved"* or *"Correction confirmed"*.
- **Severity:** Medium (P2)
- **User Impact:** Administrator is not reminded that approving a batch only updates UDM-RADAR monitoring tables and does not transmit data to the registrar.
- **Suggested Correction:** Update message: *"Batch approved and applied to UDM-RADAR monitoring records. Cumulative historical GWA remains unchanged."*
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

---

## 5. Visual Hierarchy & Emotional Tone (`VIS-CUR` / `UX-CUR`)

### UX-CUR-01: Honor Track Proximity Gauge Demotivates At-Risk Students
- **Affected Role:** Student
- **Current Evidence:** `student/dashboard.php:304–323` renders a prominent Honor Track Gauge showing Cum Laude (3.25), Magna (3.50), and Summa (3.75) cutoffs.
- **Severity:** Medium (P2)
- **User Impact:** For students with GWAs below 2.00 or multiple failing prelims, displaying Latin Honor cutoffs is psychologically counterproductive and clinically unhelpful.
- **Suggested Correction:** Conditionally hide the gauge or replace it with a "Passing Trajectory Tracker" (target 3.00) when student GWA is below 2.50.
- **Target Phase:** Requires UX Remediation / Institutional Decision
- **Formal-Evaluation Blocking:** No

### UX-CUR-02: Absence of Centralized Glossary or Help Center
- **Affected Role:** All Roles
- **Current Evidence:** No Help or Glossary page exists. Acronyms (GWA, INC, DRP, FA, UD) and grading concepts are dispersed across disparate tooltips.
- **Severity:** Medium (P2)
- **User Impact:** First-time students and faculty have no self-service orientation resource.
- **Suggested Correction:** Implement a modal or dedicated Help tab in `settings.php` providing a comprehensive grading terminology glossary and workflow explanation.
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

### VIS-CUR-01: Discrepancy Between Dashboard and Python Fallback Heuristics
- **Classification:** Reclassified to WP-5 (Prediction Source and Feature Contract Harmonization). It is a prediction-contract issue, not a visual-design issue.
- **Affected Role:** Student, Administrator
- **Current Evidence:** PHP dashboard heuristic (`constants.php:predictFinalGradeHeuristic`) blends 50% prelim average and 50% historical GWA. Python fallback (`decision_tree.py`) blends 70% historical GWA and 30% prelim average.
- **Severity:** High (P1)
- **User Impact:** A student viewing their dashboard estimate will see a different projection than if the background prediction pipeline runs calculation fallback.
- **Suggested Correction:** Reproduce first. Reconcile both heuristic/fallback implementations to the canonical contract under WP-5 without retraining model.pkl.
- **Target Phase:** Requires WP-5
- **Formal-Evaluation Blocking:** No

---

## 6. Accessibility & Inclusivity (`A11Y-CUR`)

### A11Y-CUR-01: Contrast Ratio on Amber Warning Banners in Dark Mode
- **Affected Role:** All Roles (Low Vision)
- **Current Evidence:** In `faculty/dashboard.php:204`, `.warning-banner` uses `background: rgba(245, 158, 11, 0.12)` with muted text colors in dark mode.
- **Severity:** Low (P3)
- **User Impact:** Reduced legibility in high-ambient-light or low-contrast environments.
- **Suggested Correction:** Increase border stroke width to 4px solid `#f59e0b` and set text color to `#fbbf24`.
- **Target Phase:** Requires UX Remediation
- **Formal-Evaluation Blocking:** No

---

## 7. Institutional Policy Dependencies (`INST-CUR`)

### INST-CUR-01: Long-Term Institutional Position of Grade Batching
- **Affected Role:** College Leadership, ICTO, Registrar
- **Question:** Does CCS intend for faculty to encode grades twice, or will UDM-RADAR transition to a read-only synchronization model from official university databases?
- **Recommendation:** Position as a **Model A Prototype** for capstone defense, migrating to **Model D (Authorized Import)** or **Model B (Read-Only Integration)** in production.

### INST-CUR-02: Institutional Authority for Academic Support Case Closure
- **Affected Role:** Program Head, Academic Advisers
- **Question:** Who holds institutional authority to close an Academic Support Case? Currently, any administrative user can close cases with arbitrary notes.
- **Recommendation:** Formalize closure criteria: requires documented faculty feedback or student meeting confirmation.
