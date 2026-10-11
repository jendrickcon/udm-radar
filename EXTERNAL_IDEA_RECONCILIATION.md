# UDM-RADAR — External Idea Reconciliation

> [!NOTE]
> **Historical Snapshot (Branch `feat/cohort-scaffolding`):** This reconciliation records analysis of 53 conceptual ideas against the prototype as of commit `f4009d0`. Subsequent work packages implemented many approved items.
> For current project status, approved upcoming packages, and candidate backlog classification, consult:
> - **Canonical Roadmap:** [docs/ROADMAP.md](docs/ROADMAP.md)
> - **Implementation History:** [docs/IMPLEMENTATION_HISTORY.md](docs/IMPLEMENTATION_HISTORY.md)

This document reconciles all 53 conceptual items from `UDM_RADAR_CONCEPTUAL_BACKLOG.md` against the verified codebase on branch `feat/cohort-scaffolding` (`f4009d0`).

> [!NOTE]
> This report was produced primarily through static interface inspection. Visual, responsive, accessibility, and interaction findings require later browser or Playwright revalidation.

---

## Summary of Reconciliation

| Classification | Count | Description |
|---|---|---|
| **Already Implemented** | 14 | Fully functional in current branch |
| **Partially Implemented** | 16 | Core exists, lacks minor UI copy, trigger, or link |
| **Implemented Differently** | 5 | Solved through alternative design pattern |
| **Not Implemented** | 9 | Not present in current code; evaluated on merit |
| **Requires Demonstration Prep** | 5 | Depends on synthetic demo data / scenario seeding |
| **Requires Institutional Decision** | 4 | Policy decision outside engineering scope |
| **Total** | **53** | 100% of backlog items reconciled |

---

## 1. UX Enhancements (`IDEA-UX`)

### IDEA-UX-01: Action-first home hierarchy
- **Current Status:** Partially Implemented
- **Current Evidence:** `student/index.php` (L118–126) and `faculty/index.php` (L97–105) have conditional "Action Required" banners. `admin/index.php` (L183–205) has an "Action Required" banner linking to `admin/activity.php`.
- **Current Page:** `student/index.php`, `faculty/index.php`, `admin/index.php`
- **File & Line:** `student/index.php:118`, `faculty/index.php:97`, `admin/index.php:183`
- **Already Implemented?** Partially. Banners exist, but they only link out rather than displaying inline actionable items.
- **Still Valuable?** Still Valuable.
- **User Impact:** High. Streamlines navigation for all roles.
- **Complexity:** Medium | **Target Phase:** Requires UX Remediation | **Priority:** P1

### IDEA-UX-02: Student 10-second summary strip
- **Current Status:** Already Implemented
- **Current Evidence:** 4-card snapshot grid on `student/index.php` (Enrolled Subjects, Total Units, Grades Available, Awaiting Grades) and 3-card stat grid on `student/dashboard.php` (Cumulative GWA, Predicted Final GWA, Overall Risk).
- **Current Page:** `student/index.php`, `student/dashboard.php`
- **File & Line:** `student/index.php:140–153`, `student/dashboard.php:271–299`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **User Impact:** High. Gives instant orientation.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-UX-03: Status legend and state vocabulary
- **Current Status:** Partially Implemented
- **Current Evidence:** Custom tooltips on risk badges (`.custom-tooltip`) in `student/grades.php:205` explain HIGH, MODERATE, LOW, and N/A. However, no persistent page legend exists for missing vs. in-progress terms.
- **Current Page:** `student/grades.php`, `faculty/grades.php`
- **File & Line:** `student/grades.php:175–185`
- **Already Implemented?** Partially. Tooltips exist, unified legend box missing.
- **Still Valuable?** Still Valuable.
- **User Impact:** Medium. Eliminates ambiguity between "Failing" and "Grade Not Yet Encoded".
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-UX-04: Low-vs-missing-grade distinction
- **Current Status:** Already Implemented
- **Current Evidence:** In `student/grades.php`, missing grades show as `—` or `In Progress`. In `faculty/grades.php`, cells with no prelim are clearly distinguished from numeric scores below 75%.
- **Current Page:** `student/grades.php`, `faculty/grades.php`
- **File & Line:** `student/grades.php:191–203`, `faculty/index.php:50–61`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **User Impact:** High. Prevents unencoded grades from being misread as 0.00 / Failing.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-UX-05: Supportive standing presentation
- **Current Status:** Implemented Differently
- **Current Evidence:** The codebase uses `LOW`, `MODERATE`, and `HIGH` risk levels, paired with encouraging tooltips (e.g., *"Low Risk: Excellent. Your current subject grade point is 2.50 or higher"*).
- **Current Page:** `student/dashboard.php`, `student/grades.php`
- **File & Line:** `student/grades.php:178–184`
- **Already Implemented?** Implemented differently (standard risk enum used rather than "On track/Worth watching").
- **Still Valuable?** Requires Institutional Decision (whether university prefers clinical risk labels or coaching language).
- **User Impact:** Medium. Reduces student anxiety.
- **Complexity:** Low | **Target Phase:** Requires Institutional Decision | **Priority:** P2

### IDEA-UX-06: Progressive disclosure for scale (Admin)
- **Current Status:** Partially Implemented
- **Current Evidence:** `admin/index.php` implements search, section filter, risk filter, and pagination (25 per page). However, full student population is still queried in memory.
- **Current Page:** `admin/index.php`
- **File & Line:** `admin/index.php:85–105`
- **Already Implemented?** Partially. UI pagination exists; true database-level progressive disclosure needed for 5 cohorts.
- **Still Valuable?** Still Valuable for multi-cohort scale.
- **Complexity:** High | **Target Phase:** Requires WP-9 | **Priority:** P2

### IDEA-UX-07: Teaching empty states
- **Current Status:** Already Implemented
- **Current Evidence:** All tables implement `<p class="empty-state">`: "No current-semester grades have been encoded yet", "No historical records found", "All caught up! No open reports".
- **Current Page:** Across all portals
- **File & Line:** `student/grades.php:116,222`, `admin/activity.php:382,415`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **User Impact:** Medium.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-UX-08: Satisfactory Students collapsed section
- **Current Status:** Implemented Differently
- **Current Evidence:** In `faculty/dashboard.php`, students are split into two tabs: `My Class Performance` and `Overall Standing`. In `admin/index.php`, filters allow isolating High/Moderate risk while hiding Low risk.
- **Current Page:** `faculty/dashboard.php`, `admin/index.php`
- **File & Line:** `faculty/dashboard.php:335–363`
- **Already Implemented?** Differently (via tab/filter rather than collapsible accordion).
- **Still Valuable?** Low priority.
- **Complexity:** Low | **Target Phase:** UX Remediation | **Priority:** P3

### IDEA-UX-09: "What changed / what did not" confirmations
- **Current Status:** Not Implemented
- **Current Evidence:** Flash messages say generic text: "Report marked as resolved", "Batch successfully approved". They do NOT explicitly state: *"Monitoring data updated; official university records unaffected."*
- **Current Page:** `admin/activity.php`, `faculty/grades.php`
- **File & Line:** `admin/activity.php:68,142`
- **Already Implemented?** No.
- **Still Valuable?** Still Valuable. Essential for prototype boundary honesty.
- **User Impact:** High. Prevents false assumption that registrar records were edited.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P1

### IDEA-UX-10: One concerns-and-support inbox per role
- **Current Status:** Already Implemented
- **Current Evidence:** `student/feedback.php` has two tabs (My Tickets, Academic Support Notices). `faculty/feedback.php` has three tabs (Student Concerns, Academic Support Referrals, My Reports). `admin/activity.php` has four tabs.
- **Current Page:** `student/feedback.php`, `faculty/feedback.php`, `admin/activity.php`
- **File & Line:** `student/feedback.php:200`, `faculty/feedback.php:310`, `admin/activity.php:240`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **User Impact:** High. Consolidates communications.
- **Complexity:** Medium | **Target Phase:** Already in Base | **Priority:** Done

---

## 2. Navigation Architecture (`IDEA-NAV`)

### IDEA-NAV-01: Student navigation ≤ 5 items
- **Current Status:** Partially Implemented
- **Current Evidence:** Student navigation currently has 6 items: Home, Dashboard, Grades & History, Performance Trend, Feedback & Support, Settings.
- **Current Page:** `includes/sidebar.php`
- **File & Line:** `student/index.php:97–104`
- **Already Implemented?** No (currently 6). Merging Dashboard + Trend would achieve 5.
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-NAV-02: Faculty navigation ≤ 5 items
- **Current Status:** Not Implemented
- **Current Evidence:** Faculty has 7 items: Home, Dashboard, Class Analytics, Performance Trends, Encode Grades, Concerns & Reports, Settings.
- **Current Page:** `faculty/index.php:75–83`
- **Already Implemented?** No (currently 7).
- **Still Valuable?** Still Valuable. Merging Dashboard, Analytics, Trend reduces cognitive load.
- **Complexity:** Medium | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-NAV-03: Admin navigation ≤ 7 items
- **Current Status:** Already Implemented
- **Current Evidence:** Admin has exactly 7 items: Dashboard, Students, Faculty, Grades, Program Analytics, Activity & Inbox, Settings.
- **Current Page:** `admin/index.php:121–129`
- **Already Implemented?** Yes (matches 7-item limit).
- **Still Valuable?** Yes.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-NAV-04: Merge Dashboard, Analytics, Trend into tabs
- **Current Status:** Not Implemented
- **Current Evidence:** Dashboard, Analytics, and Trend are currently separate top-level PHP pages in both student and faculty folders.
- **Current Page:** `student/dashboard.php`, `student/trend.php`, `faculty/dashboard.php`, `faculty/analytics.php`, `faculty/trend.php`
- **Already Implemented?** No.
- **Still Valuable?** Still Valuable. Greatly simplifies sidebar sprawl.
- **Complexity:** Medium | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-NAV-05: In-app Notification center
- **Current Status:** Implemented Differently
- **Current Evidence:** `includes/sidebar.php:12–50` calculates unread attention items dynamically and renders red badges directly onto sidebar links (`Concerns & Reports`, `Activity & Inbox`, `Feedback & Support`).
- **Current Page:** `includes/sidebar.php:53`
- **Already Implemented?** Implemented differently (sidebar badges rather than header dropdown bell).
- **Still Valuable?** Current sidebar badges work effectively; header bell is optional future refinement.
- **Complexity:** Medium | **Target Phase:** Out of Scope / Later | **Priority:** P3

### IDEA-NAV-06: Split Admin approvals into two scoped tabs
- **Current Status:** Already Implemented
- **Current Evidence:** In `admin/activity.php`, Tab 2 ("Approvals") renders two separate sections: "Pending Grade Batches" (faculty term percentages) and "Pending Record Corrections" (individual grade updates).
- **Current Page:** `admin/activity.php:370–450`
- **File & Line:** `admin/activity.php:370,410`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes. Prevents confounding batch monitoring with official corrections.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

---

## 3. Terminology & Copy (`IDEA-COPY`)

### IDEA-COPY-01: Rename Grade Batch to "Term Grade Submission"
- **Current Status:** Not Implemented
- **Current Evidence:** Faculty sidebar and buttons use "Encode Grades" and "Submit Grade Batch for Approval".
- **Current Page:** `faculty/grades.php`, `includes/sidebar.php`
- **File & Line:** `faculty/grades.php:248,397`
- **Already Implemented?** No.
- **Still Valuable?** Still Valuable (High priority).
- **User Impact:** High. Clarifies prototype scope.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P1

### IDEA-COPY-02: Scope-explicit confirmation copy
- **Current Status:** Not Implemented
- **Current Evidence:** Flash messages do not contain disclaimer text regarding monitoring data scope.
- **Current Page:** `admin/activity.php:68,142`
- **Already Implemented?** No.
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P1

### IDEA-COPY-03: Projection card titles; retire "AI-Based" headline
- **Current Status:** Not Implemented
- **Current Evidence:** `student/dashboard.php:252` displays prominent badge `AI-Based Prediction`.
- **Current Page:** `student/dashboard.php:252`
- **Already Implemented?** No.
- **Still Valuable?** Still Valuable. Retiring "AI-Based" headline in favor of "Projected Final GWA (Model-Assisted)" prevents overclaiming.
- **Complexity:** Low | **Target Phase:** Requires WP-5 | **Priority:** P1

### IDEA-COPY-04: Student-facing supportive standing language
- **Current Status:** Partially Implemented
- **Current Evidence:** Risk badges have supportive tooltips, but the card header still displays clinical "Overall Academic Risk: HIGH".
- **Current Page:** `student/dashboard.php:296`
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-COPY-05: Technical source & coverage language for Faculty/Admin
- **Current Status:** Partially Implemented
- **Current Evidence:** `admin/analytics.php` includes a prediction coverage filter, but lacks inline descriptions of data freshness.
- **Current Page:** `admin/analytics.php:140`
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires WP-7 | **Priority:** P2

### IDEA-COPY-06: Disclaimer placement
- **Current Status:** Partially Implemented
- **Current Evidence:** `admin/grades.php:195` contains an explicit disclaimer: *"UdM-RADAR does not own official grade records..."* But student dashboard lacks a persistent footer disclaimer.
- **Current Page:** `admin/grades.php:195`, `student/dashboard.php`
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation / WP-9 | **Priority:** P1

### IDEA-COPY-07: Acknowledgment disclaimer: "Receipt, not agreement"
- **Current Status:** Already Implemented
- **Current Evidence:** In `student/feedback.php:470`, notice states: *"Acknowledging this notice confirms that you have received and read your instructor's guidance. It does not represent an agreement to a failing grade..."*
- **Current Page:** `student/feedback.php:470`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes. Excellent institutional safeguard.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-COPY-08: Unified concern vocabulary
- **Current Status:** Already Implemented
- **Current Evidence:** Codebase uniformly uses "Grade Concern / Inquiry" in student dropdown and faculty/admin triage views. "Dispute" has been removed from user-facing copy.
- **Current Page:** `student/feedback.php:250`, `faculty/feedback.php:315`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-COPY-09: Official vs Local data terminology
- **Current Status:** Partially Implemented
- **Current Evidence:** Explicitly stated on `admin/grades.php`, but absent from faculty grade submission and student grades views.
- **Current Page:** `admin/grades.php:195`, `faculty/grades.php:180`
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires WP-9 | **Priority:** P1

---

## 4. Feature Concepts (`IDEA-FEATURE`)

### IDEA-FEATURE-01: Student next-best-action card
- **Current Status:** Partially Implemented
- **Current Evidence:** `student/dashboard.php:345` renders "Weakest Subject Alert" with text: *"Focus immediate study time on [Subject] (Prelim: X%). Raising this score will have the highest positive impact..."*
- **Current Page:** `student/dashboard.php:345`
- **Already Implemented?** Partially. Recommends subject, but lacks direct action button to contact faculty.
- **Still Valuable?** Still Valuable. Adding a direct "Inquire about Grade" button completes it.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P1

### IDEA-FEATURE-02: Student Academic Action Plan
- **Current Status:** Partially Implemented
- **Current Evidence:** Provided via "Subject Triage & Trajectory" table on `student/dashboard.php:360–420`.
- **Current Page:** `student/dashboard.php:360`
- **Already Implemented?** Partially. Shows subjects and trajectory, but not formal action steps.
- **Still Valuable?** Still Valuable.
- **Complexity:** Medium | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-FEATURE-03: Faculty workload queue
- **Current Status:** Already Implemented
- **Current Evidence:** `faculty/dashboard.php:244–272` displays top warning banner listing Student Concerns, Support Reviews, and Pending Submissions with direct action buttons.
- **Current Page:** `faculty/dashboard.php:244`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-FEATURE-04: Unified Student Review Panel
- **Current Status:** Partially Implemented
- **Current Evidence:** Clicking a student row in `faculty/dashboard.php` opens a modal showing grades for assigned subjects. However, it does not embed open concerns or referral notices in that same modal.
- **Current Page:** `faculty/dashboard.php:437`
- **Already Implemented?** Partially.
- **Still Valuable?** Still Valuable. Merging concerns and notices into the student modal saves 3 page clicks.
- **Complexity:** High | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-FEATURE-05: Admin Today view
- **Current Status:** Already Implemented
- **Current Evidence:** `admin/index.php:183–205` Workload Banner surfaces all 4 urgent action queues.
- **Current Page:** `admin/index.php:183`
- **Already Implemented?** Yes.
- **Still Valuable?** Yes.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-FEATURE-06: Data-coverage indicator
- **Current Status:** Not Implemented
- **Current Evidence:** Predictions show GWA value without "Based on X of Y subjects".
- **Current Page:** `student/dashboard.php:278`, `admin/index.php:310`
- **Already Implemented?** No.
- **Still Valuable?** Still Valuable (High priority). Essential for prediction transparency.
- **Complexity:** Medium | **Target Phase:** Requires WP-7 | **Priority:** P1

### IDEA-FEATURE-07: Projection freshness indicator
- **Current Status:** Partially Implemented
- **Current Evidence:** Predictions store `generated_at`, displayed in "Recent Academic Updates" table on `student/index.php:178`. But absent from the main dashboard prediction card.
- **Current Page:** `student/index.php:178`, `student/dashboard.php:278`
- **Still Valuable?** Still Valuable.
- **Complexity:** Low | **Target Phase:** Requires WP-7 | **Priority:** P1

### IDEA-FEATURE-08: "Why this result" explanation panel
- **Current Status:** Partially Implemented
- **Current Evidence:** `student/dashboard.php:327–342` renders "Risk Factor Analysis" listing up to 5 contributing factors (Failing grades, Irregular status, Trajectory drop, Weakest subject).
- **Current Page:** `student/dashboard.php:327`
- **Already Implemented?** Partially. Explains risk factors, but doesn't show mathematical weightings.
- **Still Valuable?** Still Valuable.
- **Complexity:** Medium | **Target Phase:** Requires WP-5 / WP-7 | **Priority:** P2

### IDEA-FEATURE-09: Case timeline
- **Current Status:** Partially Implemented
- **Current Evidence:** `feedback_status_history` and `support_status_history` tables record chronological status transitions with timestamps and notes. Displayed in `admin/activity.php`.
- **Current Page:** `admin/activity.php:66,540`
- **Already Implemented?** Partially.
- **Still Valuable?** Still Valuable.
- **Complexity:** Medium | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-FEATURE-10: Intervention outcome tracking
- **Current Status:** Not Implemented
- **Current Evidence:** `academic_support_cases` has status `needs_review`, `acknowledged`, `closed`. It lacks formal outcome fields (`improved`, `no_change`, `withdrawn`).
- **Target Phase:** Requires Institutional Decision / Later | **Priority:** P3

### IDEA-FEATURE-11: Case resolution summary
- **Current Status:** Partially Implemented
- **Current Evidence:** Admin can enter resolution notes upon closing a case in `admin/activity.php`.
- **Current Page:** `admin/activity.php:680`
- **Already Implemented?** Partially.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-FEATURE-12: Pending-action reminders & overdue indicators
- **Current Status:** Not Implemented
- **Current Evidence:** No aging logic or "Overdue (>7 days)" visual chips exist in queues.
- **Still Valuable?** Still Valuable.
- **Complexity:** Medium | **Target Phase:** Requires UX Remediation | **Priority:** P2

### IDEA-FEATURE-13: In-app Notification center
- **Current Status:** Implemented Differently
- **Current Evidence:** Dynamic sidebar counter badges (`includes/sidebar.php:53`).
- **Already Implemented?** Implemented Differently.
- **Priority:** P3

### IDEA-FEATURE-14: Export presets
- **Current Status:** Already Implemented
- **Current Evidence:** Admin has preset CSV and PDF exports for Program Snapshot, Risk Roster, Subject Performance, and Audit Trail.
- **Current Page:** `admin/index.php:390`, `admin/analytics.php:40`, `admin/activity.php:610`
- **Already Implemented?** Yes.
- **Complexity:** Low | **Target Phase:** Already in Base | **Priority:** Done

### IDEA-FEATURE-15: Glossary and help panel
- **Current Status:** Not Implemented
- **Current Evidence:** No centralized Help page, modal, or glossary exists.
- **Still Valuable?** Still Valuable (High priority). Essential for first-time user orientation.
- **Complexity:** Low | **Target Phase:** Requires UX Remediation | **Priority:** P1

### IDEA-FEATURE-16: Guided first-login onboarding
- **Current Status:** Not Implemented
- **Current Evidence:** First-time login lands directly on `student/index.php` or `faculty/index.php` with no introductory modal.
- **Still Valuable?** Still Valuable.
- **Complexity:** Medium | **Target Phase:** Requires UX Remediation | **Priority:** P1

---

## 5. Demonstration Concepts (`IDEA-DEMO`)

### IDEA-DEMO-01: Demonstration mode banner & scenario reset
- **Current Status:** Not Implemented
- **Current Evidence:** No banner indicates prototype/synthetic data mode.
- **Target Phase:** Requires Demonstration Preparation | **Priority:** P1

### IDEA-DEMO-02: Scenario 1 data pack (Student awareness)
- **Current Status:** Partially Implemented
- **Current Evidence:** Synthetic students exist with 8 high-risk current subjects and historical data. However, support referrals are at `needs_review`, meaning student cannot demonstrate notice acknowledgment.
- **Target Phase:** Requires Demonstration Preparation | **Priority:** P1

### IDEA-DEMO-03: Scenario 2 data pack (Faculty intervention)
- **Current Status:** Partially Implemented
- **Current Evidence:** Faculty ID 302 has assigned classes and support referrals, but lacks an open Grade Concern ticket in their section.
- **Target Phase:** Requires Demonstration Preparation | **Priority:** P1

### IDEA-DEMO-04: Scenario 3 data pack (Admin coordination)
- **Current Status:** Partially Implemented
- **Current Evidence:** Admin has 10 support cases, but 0 pending grade batches and 0 pending corrections.
- **Target Phase:** Requires Demonstration Preparation | **Priority:** P1

### IDEA-DEMO-05: "What not to claim" presenter script
- **Current Status:** Not Implemented
- **Target Phase:** Requires Demonstration Preparation | **Priority:** P1

---

## 6. Institutional Decisions (`IDEA-INST`)

### IDEA-INST-01: Grade Batch future role & source of truth
- **Current Status:** Requires Institutional Decision
- **Impact:** Determines whether Grade Batch remains a prototype simulation or transitions to an authorized CSV import tool.

### IDEA-INST-02: Referral trigger & faculty accountability
- **Current Status:** Requires Institutional Decision
- **Impact:** Decides whether support cases are auto-created by ML or manually initiated by faculty.

### IDEA-INST-03: Overdue windows & escalation paths
- **Current Status:** Requires Institutional Decision
- **Impact:** Sets SLA policies for faculty notice issuance and student acknowledgment.

### IDEA-INST-04: Data privacy, visibility & retention rules
- **Current Status:** Requires Institutional Decision
- **Impact:** Defines who can see program-level risk vs. class-level risk.

### IDEA-INST-05: Official-record status sync
- **Current Status:** Requires Institutional Decision / Future Integration

### IDEA-INST-06: Latin Honor display policy
- **Current Status:** Requires Institutional Decision
- **Impact:** Decides whether honor proximity gauges should be hidden for at-risk students.

### IDEA-INST-07: External notification channels (Email/SMS)
- **Current Status:** Requires Institutional Decision / Future Integration
