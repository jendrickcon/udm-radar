# UDM-RADAR — Current UI/UX Validation Report

> [!NOTE]
> **Historical Snapshot (Branch `feat/cohort-scaffolding`, October 1, 2026):** This report documents initial static UI/UX validation findings from earlier sprints. Subsequent work packages (UI-WP1A, DATA-UI-WP1A/B, DATA-UI-WP2) have already addressed many of these usability and data presentation findings.
> For current project status and active roadmap, see:
> - **Canonical Roadmap:** [docs/ROADMAP.md](../ROADMAP.md)
> - **Implementation History:** [docs/IMPLEMENTATION_HISTORY.md](../IMPLEMENTATION_HISTORY.md)

**Audit Mode:** Independent Usability Auditor, Product-Idea Reconciler, Academic Workflow Analyst  
**Date:** October 1, 2026  
**Branch:** `feat/cohort-scaffolding` (`f4009d0`)  
**Scope Context:** Departmental academic monitoring & decision-support prototype for the College of Computing Studies (CCS). Not an official registrar system, LMS, or student portal.

> [!NOTE]
> This report was produced primarily through static interface inspection. Visual, responsive, accessibility, and interaction findings require later browser or Playwright revalidation.

---

## 1. Executive Summary

This usability audit evaluates the current checked-out branch of UDM-RADAR from the perspective of realistic first-time users (Student, Faculty, Administrator, Scope-Aware Professor, and Capstone Evaluator). The evaluation assesses whether users can understand their academic standing, navigate intervention workflows, and trust displayed projections without relying on developer knowledge or institutional assumptions.

### Key Audit Findings
1. **Product Direction:** UDM-RADAR is currently **Balanced Decision-Support (Concept C)** in architecture, but **Monitoring-First** in prominent visual hierarchy. While the database and backend support a complete "Concern → Referral → Notice → Acknowledgment" intervention loop, the primary dashboard entry points prioritize passive metrics and charts over active workflow queues.
2. **First-Time Orientation:**
   - **Student:** Can determine immediate risk within 10 seconds via dashboard stat cards, but confuses "Predicted Final GWA" with an official semester grade. Critical intervention features (Grade Concerns, Support Notices) are separated onto `feedback.php` with no visible callout from the dashboard subject triage table.
   - **Faculty:** Sees assigned classes and encoding progress immediately, but experiences ambiguity regarding whether UDM-RADAR grade submission is mandatory or replaces the official university portal. The distinction between class-specific risk and program-level risk requires onboarding.
   - **Administrator:** Receives an actionable Workload Banner on `admin/index.php`, but faces workflow confusion because batch prediction fails via CSRF (`Finding A-1`), and the live database lacks populated program/course values (`Finding A-2`).
3. **Terminology & Trust:**
   - Prominent badge text "AI-Based Prediction" induces unwarranted authority for a model trained on 150 synthetic records.
   - Grade Batching is labeled "Encode Grades" on the faculty sidebar, misleading faculty into believing it is official registrar encoding rather than departmental monitoring.
   - Historical GWA and Current Term Percentages are properly segregated in the backend, but the student dashboard lacks explicit "freshness" and "coverage" indicators (e.g., "Estimated from 3 of 5 Prelim grades as of Oct 01").

---

## 2. Playwright & Static Validation Charter

### 2.1 Audit Methodology & Protocol
Because the local environment operates in an offline/restricted runtime without an active node/browser daemon, the audit executed a **Static Interface Walkthrough protocol (`[Statically Inferred]`)** verified against exact HTML/PHP view templates, CSS rule declarations, and live database constraints.

### 2.2 Testing Dimensions & Target Viewports
- **Desktop Primary (1440 × 900):** Evaluated sidebar navigation, multi-column dashboard card grids, Chart.js canvas elements, and modal overlays.
- **Laptop / Tablet Landscape (1024 × 768):** Evaluated responsive reflow for stat-grids (4-column to 2-column), table horizontal scroll wrappers (`.grades-table-scroll`), and sidebar collapse behavior.
- **Mobile Device (390 × 844 - iPhone 12/13/14):** Inspected mobile layout fallbacks, hamburger menus, font scaling, touch targets, and table clipping.

### 2.3 Non-Destructive Inspection Rules
- **Prohibited:** Form submission, Grade Batch submission, Grade Concern posting, Record Correction approval, Support Notice acknowledgment, prediction execution, database writes.
- **Permitted:** Route inspection, session flow tracing, DOM structure review, CSS inspection, field placeholder analysis, empty-state text verification, keyboard focus tab-order tracing, accessibility attribute checking (`aria-*`, `role`).

---

## 3. Current Product Direction Analysis

The external conceptual package identified three candidate product directions:
- **Concept A: Monitoring-First** (Standing, trends, passive charts)
- **Concept B: Intervention-First** (Inbox-centric, case queues, action-dominated)
- **Concept C: Balanced Decision-Support** (Action-first homepage leading to standing and follow-up)

### Current System Assessment: Hybrid C (Architecturally Balanced, Visually Monitoring-First)
- **Home Pages:** Both `student/index.php` and `faculty/index.php` incorporate an `Action Required` banner when conditions are met (`$attentionSubjects > 0` or `$attentionClasses > 0`). However, these banners only link to secondary dashboards rather than offering immediate, inline resolutions.
- **Dashboards:** Both `student/dashboard.php` and `faculty/dashboard.php` open with high-level KPI cards and Chart.js visualizations (Honor Track gauge, Section averages) before displaying actionable rosters or intervention queues.
- **Recommendation:** Adopt **Concept C** fully. Convert top dashboard bands into explicit, task-based work queues while retaining trend analytics in dedicated secondary tabs.

---

## 4. First-Time Student Experience

### 4.1 First Impression & Discovery (10-Second Test)
When a first-time student logs in, the landing page (`student/index.php`) displays:
1. Welcome header with student name and college.
2. "Action Required" banner (if low subjects exist) with a red border: *"You have N current subjects requiring closer academic attention."*
3. Student profile card (Name, Student No., Program, Year & Section).
4. "Current Term Snapshot" cards: Enrolled Subjects, Total Units, Grades Available, Awaiting Grades.
5. Quick action cards linking to Dashboard, Grades, and Trend.
6. "Recent Academic Updates" showing latest prediction source and risk level.

**What the Student Understands in 10 Seconds:**
- ✅ "The system knows who I am and what subjects I am taking."
- ✅ "It tells me how many grades my teachers have submitted."
- ❌ "It is not immediately obvious whether this is my official UDM portal or a special CCS departmental system."

### 4.2 Dashboard Walkthrough (`student/dashboard.php`)
- **Cumulative GWA vs Predicted GWA:** The student sees two adjacent cards:
  - `Cumulative GWA`: displays historical average (e.g., `2.75`), labeled *"Current Academic Standing"*.
  - `Predicted Final GWA`: displays end-of-term projection (e.g., `2.50`), labeled *"Latin Honor Status: N/A"*.
  - *Usability Friction:* A first-time student frequently confuses "Cumulative GWA" (which excludes current in-progress subjects) with their current semester standing. The label *"Current Academic Standing"* exacerbates this confusion because it implies current-term inclusion.
- **Honor Track Proximity:** A large Chart.js doughnut gauge visualizes cutoffs for Cum Laude (3.25), Magna (3.50), and Summa (3.75).
  - *Skeptical Feedback:* For a student with a 2.10 GWA and 2 failing prelims, displaying Latin Honor cutoffs is demotivating and irrelevant.
- **Subject Triage Table:** Displays enrolled subjects with raw Prelim percentages, a "Projected Subject Grade" (point scale), trajectory arrow, and status badge ("Action Required" vs "Normal").
  - *Missing Link:* The only action button in each row is a "Calculator" button opening the Grade Goal Calculator. There is **no link or button to ask the instructor about a questionable grade** (Grade Concern).

### 4.3 Support & Feedback Discovery (`student/feedback.php`)
- To dispute a grade or view support notices, the student must recognize that "Feedback & Support" in the sidebar is where academic interventions live.
- **Grade Concern Workflow:** Form requires selecting Category ("Grade Concern / Inquiry"), Subject (from dropdown), and Grading Period (Prelim/Midterm/Pre-Final). Clear and structured.
- **Academic Support Notices:** The "Academic Support Notices" tab displays instructor messages with an "Acknowledge Receipt" button. The disclaimer explicitly clarifies: *"Acknowledging this notice confirms receipt... It does not represent an agreement to a failing grade."* Excellent transparency.

---

## 5. First-Time Faculty Experience

### 5.1 First Impression & Workload Orientation
When a faculty member logs in (`faculty/index.php`):
1. Header greeting and College identification.
2. If incomplete prelims exist: amber warning banner *"Work Requiring Attention: N assigned classes have incomplete Preliminary grade records"* with an "Encode Grades" button.
3. Teaching Snapshot: Assigned Class Loads, Unique Sections, Unique Students Reached, Pending Grade Records.
4. Assigned Classes Preview: Table showing class title, section, student count, and `% Prelim encoded` progress bar.

**What Faculty Understands in 10 Seconds:**
- ✅ "I can see all sections assigned to me this semester."
- ✅ "I can immediately see how many grades I haven't submitted yet."
- ❌ "Why is the system asking me to 'Encode Grades' here if I already encoded them in the University Registrar portal?"

### 5.2 Faculty Dashboard (`faculty/dashboard.php`)
- **Section Overview:** Section cards display Section Name, Student Count, Class Avg GWA, and At-Risk count.
- **Work Requiring Your Attention Banner:** If students have open concerns or referrals, a prominent warning banner appears at the top:
  - *N student concerns* (links to `feedback.php?tab=inbox`)
  - *N support reviews* (links to `feedback.php?tab=support`)
  - *N grade submissions awaiting Admin review* (links to `grades.php`)
- **Roster Modal:** Clicking "View Students" opens an interactive roster with two tabs:
  - `My Class Performance`: Displays Prelim, Midterm, Pre-Final, Final Grade, and Risk for the faculty's own subject.
  - `Overall Standing`: Displays the student's Cumulative GWA and Overall Program Risk across all college subjects.

### 5.3 Grade Encoding & Batch Submission (`faculty/grades.php`)
- Faculty selects Class & Section, then Term Period (Prelim, Midterm, Pre-Final).
- **Critical Distinction:** Faculty CANNOT enter `final_grade`. The UI only accepts numeric percentages (0–100).
- Submission types: Initial Encoding, Bulk Correction, or Grade Concern Resolution.
- Submitting sends a payload to `pending_grade_batches` with status `pending`.
- **Friction Point:** The UI button says "Submit Grade Batch for Approval". First-time faculty are unsure who approves it (Department Chair? Admin? Registrar?) and whether this grade is now visible to students.

---

## 6. First-Time Administrator Experience

### 6.1 Administrator Command Center (`admin/index.php`)
- **Action Required Banner:** Prominently aggregates actionable queues:
  - Open Feedback Reports (count)
  - Pending Grade Batches (count)
  - Pending Grade Corrections (count)
  - Academic Support Cases (count)
  - Direct button: "Open Activity Workspace" (`admin/activity.php`).
- **Population Analytics:** Displays KPI stat cards, risk distribution donut, and section GWA bar chart.
- **Directory Table:** Searchable student list with GWA, Predicted GWA, Risk Level, and Record Status.
- **Defect Identified (`BUG-CUR-01`):** The "Run Predictions" button calls `../api/batch_predict.php` without an active CSRF token, causing an immediate 403 HTTP rejection in real execution.

### 6.2 Activity Workspace (`admin/activity.php`)
The activity center is structured into four distinct workflow tabs:
1. **Open Reports:** Displays student tickets (Grade Concerns, Data Issues). Admin can resolve, reject, or post replies.
2. **Approvals:** Divided into two distinct sections:
   - *Pending Grade Batches:* Faculty term percentage submissions. Admin reviews student roster diffs and clicks "Approve Batch" or "Reject Batch".
   - *Pending Record Corrections:* Individual grade corrections proposed on `admin/grades.php`.
3. **Academic Support Oversight:** Displays parent cases (`academic_support_cases`) triggered by high-risk predictions. Lists assigned faculty referrals and student acknowledgment timestamps.
4. **History & Audit:** Chronological log of all administrative actions with CSV/PDF export.

---

## 7. Grade Batching: Reality vs Perception

| Dimension | User Perception | Actual Implementation |
|---|---|---|
| **Role of Submission** | Official university grade submission | Departmental term percentage monitoring only |
| **Accepted Grade Types** | Prelim, Midterm, Pre-Final, Final Grade | Raw 0–100 percentages for Prelim/Midterm/Pre-Final only |
| **Final Grade Entry** | Faculty submits final grade at end of semester | Excluded from Batch flow; handled via Registrar/Admin corrections |
| **GWA Impact** | Immediately updates student Cumulative GWA | Does NOT alter Cumulative GWA (only affects projections/risk) |
| **Approval Scope** | Approved by Department Head/Dean | Approved by local Admin user in `admin/activity.php` |

**Usability Verdict:** The terminology "Encode Grades" and "Grade Batch" is misleading. It should be renamed to **"Term Grade Submission"** with explicit subtitle: *"Submit Preliminary, Midterm, or Pre-Final percentages for departmental monitoring. Does not alter official university records."*

---

## 8. Prediction Transparency & Trust Findings

### 8.1 Labeling & Authority
- In `student/dashboard.php`, the header displays a pill badge: `AI-Based Prediction`.
- Hovering reveals: *"The displayed estimates were generated using the UDM-RADAR AI model based on your available academic inputs."*
- **Concern:** The badge title creates an illusion of algorithmic infallibility. For a Decision Tree model with depth 4 trained on 150 synthetic records, calling this "AI-Based Prediction" overstates institutional maturity.

### 8.2 Coverage & Freshness Gaps
- The prediction displays a static value (e.g., `2.35`) without stating:
  - How many subjects contributed to this estimate (e.g., "Based on 3 of 5 Prelim grades").
  - When the prediction was last computed (e.g., "Generated Oct 01, 2026").
  - What inputs triggered the risk level (e.g., historical GWA drop vs failing prelim).

---

## 9. Accessibility, Responsiveness & Design Patterns

### 9.1 Responsive Reflow
- **Stat Grids:** Handled well via CSS `grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))`. Collapses smoothly on tablet and mobile viewports.
- **Tables:** Wrapped in `.grades-table-scroll` with `overflow-x: auto` and custom scrollbars. Prevents page blowout on narrow screens.
- **Sidebar:** On viewports under 768px, requires a hamburger toggle. Fixed headers ensure consistent branding.

### 9.2 Accessibility & Keyboard Navigation
- **Tooltips:** Implemented with CSS `.custom-tooltip` and `tabindex="0"`. Accessible via keyboard focus. Includes `role="tooltip"`.
- **Color Independence:** Risk badges use text labels (`HIGH`, `MODERATE`, `LOW`) alongside color fills. Never relies on color alone.
- **Form Controls:** All form inputs have explicit `<label>` tags and descriptive placeholders.
