# UDM-RADAR Project Roadmap

> [!IMPORTANT]
> **Current Planning Authority:** This file is the current planning source for implementation status and future Work Packages. Historical audit documents describe the state at the time they were written and do not override this roadmap.

---

## 1. Project Boundaries

UDM-RADAR (*Risk Analytics & Decision-support for Academic Records*) is an academic decision-support prototype developed for the Universidad de Manila (UDM).

* **Decision-Support Prototype:** The system provides non-punitive, human-reviewed advisory information for faculty and administration. Predictive outputs do not replace faculty evaluation or official university deliberations.
* **Synthetic Academic Records:** Authorized by the Capstone Adviser following Registrar data-privacy restrictions. User-facing views and reports carry the notice: `Prototype performance on synthetic academic records`.
* **Institutional Grading Direction (UDM 2025 Manual):**
  * $4.00$ is highest / excellent ($99\text{--}100\%$).
  * $1.00$ is the minimum passing point grade ($75\%$).
  * $0.00$ is numeric failing ($74\%$ and below).
* **Term Weighting:** $\text{Final Percentage} = (\text{Prelim} \times 0.30) + (\text{Midterm} \times 0.30) + (\text{Pre-Final} \times 0.40)$.
* **Prototype Risk Triage Categories:**
  * **High Risk:** GWA below $1.75$
  * **Moderate Risk:** GWA $1.75$ through $2.49$
  * **Low Risk:** GWA $2.50$ and above
* **Strict Concept Separation:** Prototype risk categories do **not** independently determine:
  1. Passing or failing a subject (courses with grades between $1.00$ and $1.50$ are passing marks).
  2. Official Academic Standing (probation, warning, or dean's list status).
  3. Warning Status or dismissal.
  4. Program pacing (regular vs. irregular status).
  5. Enrollment, tuition subsidy (UniFAST), or allowance eligibility.
* **Scope Exclusion:** Academic Standing tracking and punitive strike systems are **not** part of the approved roadmap.

*For detailed architectural and agent rules, refer to [AGENTS.md](../AGENTS.md). For interface standards and usability issues, see [docs/UI_UX_GOVERNANCE.md](UI_UX_GOVERNANCE.md) and [docs/UI_UX_FINDINGS_REGISTER.md](UI_UX_FINDINGS_REGISTER.md).*

---

## 2. Status Vocabulary

The following lifecycle statuses are used consistently across this roadmap:

* **Complete and Merged:** Implemented, verified with automated tests, reviewed, and merged into `main`.
* **In Progress:** Actively being developed on an isolated feature branch.
* **Blocked:** Awaiting an external dependency, original machine recovery, or prerequisite asset before work can proceed.
* **Planned and Approved:** Formally scoped and approved by project maintainers for upcoming execution.
* **Proposed:** Candidate concepts or domain ideas under discovery; not approved implementation commitments.
* **Paused:** Intentionally deferred to a later phase (e.g., awaiting prerequisite completion).
* **Rejected:** Explicitly evaluated and excluded from project scope.
* **Requires Policy Verification:** Awaiting verified institutional documentation or official administrative authority.

---

## 3. Completed Academic and Data Foundation

The core academic and database foundation was remediated across Work Packages WP-0 through WP-7:

* **WP-0: Baseline Recovery and Verification** (`Merged`) — Restored Python CLI training script, added prelim feature fallbacks in the Decision Tree, and preserved historical graduated student reporting.
* **WP-1: Database Migrations and Workflow Tables** (`Merged`) — Established idempotent migrations 000–004 covering export audits, student lifecycle status (`Active`, `Archived`, `Graduated`), feedback states, and intervention workflow tables.
* **WP-2: Canonical Grade Vocabulary and Helpers** (`Merged`) — Centralized grading helpers in `config/constants.php`, enforced numeric term percentages ($0\text{--}100$), and established a 266-assertion unit test validation matrix.
* **WP-3: Final-Grade Storage Migration** (`Merged`) — Migrated `grades.final_grade` to `VARCHAR(10)` with exact-match binary CHECK constraints to preserve official textual marks (`INC`, `DO`, `DU`, `DRP`, `PASSED`) without zero coercion.
* **WP-4: GWA Parity and Historical Analytics** (`Merged`) — Centralized unit-weighted GWA calculations, excluded dropped courses (`DRP`) from the pass-rate denominator (`passed + failed`), and verified 100% GWA parity across all 289 baseline student profiles.
* **WP-5: Prediction Contracts and Provenance** (`Merged`) — Harmonized feature contracts (`current_prelim_point_avg`), removed silent zero-injection, aligned 50/50 fallback blend, and standardized stored sources (`decision_tree`, `heuristic`, `calculation_fallback`) via Migration 007.
* **WP-6: Course and Curriculum Repair** (`Merged`) — Standardized synthetic student course strings to `'BSIT - Software Development'` via Migration 006, locked canonical programs via enum CHECK constraints, and audited track elective consistency.
* **WP-7: Prediction Completeness, Governance, and Support Workflow** (`Merged`) — Introduced Migration 008 with a 4-case partial data matrix, isolated Flask ML governance secrets via server-side HMAC proxy, protected batch prediction with CSRF tokens, and reconciled the end-to-end Academic Support case lifecycle.

*For complete commit hashes, PR numbers, and assertion evidence, see [docs/IMPLEMENTATION_HISTORY.md](IMPLEMENTATION_HISTORY.md).*

---

## 4. Completed Evaluation-Readiness Work

* **UI Evaluation Readiness (PR #17 & #20):** Aligned the student and faculty glossaries with the official UDM Student Manual 2025 scale ($4.00\text{--}0.00$), eliminated all emojis in favor of accessible SVGs, moved long KPI descriptions into hover tooltips, and introduced the slide-in Academic Guide drawer.
* **Member 1: Backend Security Hardening (PR #18):** Fixed concurrency race conditions in faculty support-notice issuance (AUD-01) with dedicated integration testing.
* **Member 2: Browser Accessibility (PR #19):** Added keyboard focus trapping inside the Academic Guide drawer (UI-A11Y-01), reserved mobile floating-control clearance gutters (UI-RESP-01), and established Playwright browser testing fixtures.

---

## 5. Completed Modern UI Packages

* **UI-WP1A: Interaction Foundation (PR #21):** Enhanced KPI card decision-support context in `assets/js/ui.js` and added accessible record actions and mobile overflow containment.
* **DATA-UI-WP1A: Table Accessibility Foundation (PR #21):** Added keyboard-navigable table actions, semantic sorting indicators, and mobile table wrappers.
* **DATA-UI-WP1B: Visual Table Foundation (PR #22):** Standardized full-bleed table layouts, sticky headers, uniform action button styling, and status badge designs in `assets/css/dashboard.css`.
* **DATA-UI-WP2: Active Drilldowns and Roster Behavior (PR #23):** Implemented client-side roster drilldowns with loading spinners, drawer focus management, empty states, and active blue indicator borders.
* **KPI Dark-Mode and Action-Hint Cleanup (PR #24):** Corrected low text contrast on KPI cards under dark mode and removed duplicate action hint text.
* **Faculty Dashboard Warning-Banner Layout Hotfix (PR #25):** Restored missing closing `</div>` on line 274 of `faculty/dashboard.php`, ensuring KPI cards and section grids remain outside the brown attention banner (`.warning-banner`).

---

## 6. AI-Assisted Development Governance

* **DEV-AI-WP1: AI-Assisted Development Governance and Workflows (PR #26):**
  * **Status:** `Complete and Merged` (`3eed894`).
  * **Two-Layer Architecture:**
    1. *Portable Cross-Agent Governance:* Root [`AGENTS.md`](../AGENTS.md) providing continuous repository instructions, UDM grading directions, dynamic 18-table schema baselines, and database isolation boundaries. Readable by Codex, Antigravity, and standard coding agents.
    2. *Agentic Workspace Extensions:* Modular rules ([`protected-files.md`](../.agents/rules/protected-files.md), [`student-safe-language.md`](../.agents/rules/student-safe-language.md)), procedural skills (`academic-rules-guardian`, `playwright-verification`, `pr-preparation`), and non-mutating slash workflows (`/check-protected`, `/verify-package`).
  * **Safety Invariant:** Zero external MCP servers enabled, zero third-party plugins installed, zero machine-bound Git hooks added, and zero application code altered.

---

## 7. Verified Completions and Current Work

### ACADEMIC-HOTFIX-WP1: Subject Outcome and Incomplete-Grade Semantics
* **Status:** Complete and Merged — [PR #28](https://github.com/jendrickcon/udm-radar/pull/28), normal merge `c3dee0e8d8b414b67299840bfdccb945b6b0ba2e`.
* **Owner-approved scope:** Discrete numeric final grades 1.00–4.00 pass; legacy 0.00 fails; unresolved INC is neither passed nor failed and is excluded from the pass-rate denominator. P remains a passing textual outcome.
* **Independent boundaries:** 1.75 remains the prototype High/Moderate risk boundary and the manual's non-board standing benchmark; the prototype does not determine official standing. Preserve GWA computation, zero entry restrictions, honors results/thresholds, and existing DO/DU/DRP/FA/UD behavior pending policy verification.
* **Historical outputs:** Corrected failure features apply to future predictions only. Existing predictions and support cases remain historical outputs; no automatic rescoring, case changes or INC expiry/conversion is authorized.
* **Policy evidence:** [UDM Student Manual 2025](https://udmwebsite.udm.edu.ph/wp-content/uploads/2026/01/Student-Manual-2025-FINAL.pdf), Part II D, printed pages 15–18. Honors require separate guidelines; failed-unit GWA and attendance-related dropping remain unresolved.
* **Governance follow-up:** DEV-AI-WP1A is complete and merged in PR #29 (see below). The next approved work is DOMAIN-AUDIT-WP1 under the temporary resequencing decision.

### DEV-AI-WP1A: Cross-Agent Governance Safety Corrections
* **Status:** Complete and Merged — [PR #29](https://github.com/jendrickcon/udm-radar/pull/29), normal merge `528dde76a12ac031daa66ad2b30fc007fc671f6c`.
* **Scope:** Six guidance files; corrected academic contracts, complete Git-layer inspection, scratch mutation-path safety, installed pinned Playwright verification, advisory recovery, and guidance-versus-permission clarification. No runtime or dependency changes.

### UI-WP-MOBILE: Mobile Navigation and Responsive Card Grids
* **Status:** `Blocked: original implementation unavailable for recovery`
* **Temporarily bypassed:** Independent packages may advance if they do not modify mobile navigation, responsive grid architecture, or the expected mobile-package files. This package remains approved, not cancelled or abandoned.
* **Current Situation:**
  * The implementation was previously created and tested.
  * It exists only in a machine-local stash on the original computer.
  * It is not present on the current machine or GitHub.
  * It must not be reconstructed from memory or approximated.
  * Recovery requires converting the original stash to a branch on that machine or exporting a complete patch including untracked files.
  * *(A machine-local stash identifier is recorded here solely as an operational recovery pointer, not a permanent architectural dependency.)*
* **Planned Deliverables:**
  1. Accessible mobile top bar ($\le 900\text{px}$) with minimum $44\times 44\text{px}$ touch targets.
  2. Off-canvas navigation drawer with focus containment, Escape dismissal, and overlay click closure.
  3. Background scroll locking while drawer is open.
  4. Short-height ($390\times 700$) and landscape ($844\times 390$) scrolling support.
  5. Responsive Admin Dashboard five-card KPI grid ($5\text{ col} \rightarrow 3+2 \rightarrow 2\text{ col} \rightarrow 1\text{ col}$).
  6. Responsive Admin Students four-card KPI grid ($4\text{ col} \rightarrow 2\text{ col} \rightarrow 1\text{ col}$).
  7. Responsive Admin Grades section-card grid ($3\text{ col} \rightarrow 2\text{ col} \rightarrow 1\text{ col}$).
  8. Seamless coexistence with the floating Academic Guide.

---

## 8. Approved Packages During the Temporary Mobile Bypass

On October 10, 2026, the project owner approved temporary resequencing around the unavailable mobile implementation. The order in Section 13 supersedes the previous mobile-first dependency. Each implementation package needs a reviewed scope/data contract and its own branch and PR; finish and merge one before starting the next implementation branch. No simultaneous implementation agents may edit shared files.

### DOMAIN-AUDIT-WP1: Role Needs and Feature Selection
* **Status:** Planned and Approved — read-only product/repository audit; no runtime implementation.
* **Deliverables:** Capability and role-problem inventories, feature gaps, data-availability matrix, institutional-policy dependencies, complexity/risk estimates, recommended MVP additions, rejected/deferred features, and a reviewed backlog.
* **Evidence boundary:** Repository observations and synthetic data establish technical availability; user needs remain hypotheses until stakeholder validation. Do not invent prerequisites, deadlines, official status, consultation schedules, or institutional procedures.

### ADMIN-WP1A: Data Coverage and Encoding Freshness
* **Status:** Planned and Approved, subject to DOMAIN-AUDIT-WP1 data/scope findings and a separate reviewed implementation package.
* **Scope candidates:** Current-term encoding coverage, missing-data triage, prediction-source/completeness/freshness summaries, and stale-prediction indicators where reliable timestamps support them.
* **Safeguards:** Missing data is not student failure. No public Faculty ranking, inferred deadlines, disciplinary monitoring, or automatic prediction regeneration. Counts must distinguish current records from prediction-time snapshots and unknown provenance.

### FACULTY-WP1A: Structured Intervention Reasons and Follow-Up
* **Status:** Planned and Approved, subject to audit and a reviewed storage/workflow contract after ADMIN-WP1A merges.
* **Scope candidates:** Optional Faculty-observed academic reason tags plus custom explanation; clarification, consultation, subject-review, and calculator next actions only where supported by approved data/workflows.
* **Safeguards:** Faculty review remains individual; no diagnoses or notices sent automatically from model output. Acknowledgment means receipt only. Do not claim observations about missing submissions or prerequisites without supporting evidence.

### Mobile-overlap boundary
Preserve `admin/grades.php`, `admin/index.php`, `admin/students.php`, `assets/js/ui.js`, `includes/sidebar.php`, and `tests/browser/ui-wp-mobile-navigation-grids.spec.js` during independent packages. No mobile top bar/drawer selectors, navigation behavior, KPI cardinalities, or Section-card breakpoints may be reconstructed or changed. For DATA-UI-WP3 only, `assets/css/dashboard.css` may receive narrowly scoped operational-table classes after selector-overlap review; broad shared/mobile/grid changes remain excluded. Any other overlap requires owner approval.

STUDENT-WP1A remains an audit candidate, not an added execution commitment in this temporary eight-step order. Grade-goal presets and transparent suggested attention must use supported numeric data, disclose coverage, avoid guarantees or punitive standing/benefit language, and never invent prerequisite effects.

### UI-WP-MOBILE: Mobile Navigation and Responsive Card Grids
* **Status:** `Blocked: original implementation unavailable for recovery`
* **Goal:** Deliver fully responsive mobile navigation, off-canvas drawers, and balanced KPI/section grids.
* **Dependencies:** Branch or patch export recovered from the original development machine.

### DATA-UI-WP3: Operational Tables and Workflow Actions
* **Status:** `Planned and Approved`
* **Goal:** Standardize operational table workflows, bulk actions, and multi-row data grids across Admin and Faculty portals.
* **Scope:**
  * Sticky headers for internally scrolling workflow tables
  * Academic Support Oversight
  * Approval queues
  * Open concerns and reports
  * Audit-history tables
  * Consistent operational-table density
  * Result counts and pagination
  * Loading, empty, and error states
  * Remaining workflow action-button inconsistencies
* **Non-Goals:** Modifying backend SQL queries, adding new database columns, or altering export data formats.
* **Dependencies:** DOMAIN-AUDIT-WP1 scope findings and preceding ADMIN-WP1A/FACULTY-WP1A merges. May precede mobile only within the overlap boundary above.
* **Completion Criteria:** Visual and keyboard navigation across all operational tables passing Playwright checks.

### UI-WP1B: Tooltip, SVG, and Academic Guide Accessibility
* **Status:** `Planned and Approved`
* **Goal:** Polish tooltip accessibility, SVG screen-reader descriptions, and Academic Guide drawer tabs.
* **Scope:**
  * Tooltip IDs
  * `aria-describedby` associations
  * Escape key dismissal
  * Hover persistence
  * Touch disclosure
  * Viewport collision handling
  * Academic Guide arrow-key tabs
  * Roving tabindex
  * `aria-controls` bindings
  * Correct hidden-panel behavior
  * Dynamic viewport height (`100dvh`) support
  * Reduced-motion support
  * Decorative SVG cleanup
  * Remaining icon inconsistencies
* **Non-Goals:** Redesigning Academic Guide content or altering grading constants.
* **Dependencies:** `DATA-UI-WP3` and recovered `UI-WP-MOBILE` merged.
* **Completion Criteria:** Zero accessibility warnings on tooltips, icons, and drawer controls across viewports.

### DATA-UI-WP4: Charts and Visualizations
* **Status:** `Planned and Approved`
* **Goal:** Modernize Chart.js visualizations, legends, and summary data cards across student and faculty trend views.
* **Scope:**
  * Accessible chart names
  * Visible chart summaries
  * Data alternatives (data tables or textual equivalents)
  * Shared HTML legends
  * Reduced-motion chart rendering
  * Pinned Chart.js version
  * Chart-card consistency
  * Coverage and freshness explanations
  * Loading, empty, and insufficient-data states
* **Non-Goals:** Changing ML prediction targets or altering historical longitudinal records.
* **Dependencies:** `UI-WP1B` merged.
* **Completion Criteria:** Charts render legibly in light and dark modes with textual data alternatives.

### Final Cross-Portal Browser Validation
* **Status:** `Planned and Approved`
* **Goal:** Comprehensive end-to-end browser audit across Student, Faculty, and Admin portals.
* **Scope:** Automated verification across 5 standard viewports ($1440\times 900$, $1280\times 720$, $1024\times 768$, $768\times 1024$, $390\times 844$), light and dark themes, keyboard tab traversal, and console error audits.
* **Dependencies:** `DATA-UI-WP4` merged.
* **Completion Criteria:** 100% passing Playwright suites, zero console errors, zero layout overlaps.

---

## 9. Domain Discovery and Candidate Features

> [!NOTE]
> **Proposed Features Only:** The following concepts represent prospective user-centered improvements discovered during audits. They are **not** approved implementation commitments and will not be scheduled until prerequisite UI packages and institutional policies are finalized.

### Student Candidates (`Status: Proposed`)
* **Program Pacing Overview:** Shows completed vs. remaining curriculum units across degree tracks. (Value: Curricular clarity; Dependency: Static checklist data).
* **Prerequisite Impact Map:** Visual indicator showing which subsequent courses require passing current enrollments. (Value: Prerequisite awareness; Dependency: Prerequisite mapping verification).
* **Subject Attention Guide:** Highlights subjects where prelim scores require midterm recovery. (Value: Timely academic focus; Dependency: Raw term percentage availability).
* **Grade Goal Calculator Presets:** Pre-fills required final target scores for Dean's List or passing points. (Value: Reduces calculation friction; Dependency: Term formula helpers).
* **Irregular Student Planning View:** Custom view displaying off-sequence curriculum loads. (Value: Supports non-standard pacing; Dependency: Section loading policy).
* **Student Support Next Actions:** Contextual contact links embedded inside High Risk notices. (Value: Direct intervention action; Dependency: Faculty consultation hours data).
* **Data Coverage and Freshness Indicator:** Displays whether predictions are based on complete or provisional term grades. (Value: Transparency; Dependency: Migration 008 metadata).
* **Support Notice History:** Chronological log of acknowledged Academic Support notices. (Value: Student tracking; Dependency: `support_status_history` table).

### Faculty Candidates (`Status: Proposed`)
* **Quick-Select Referral Reasons:** Standardized dropdown tags when initiating support referrals. (Value: Speeds up referral filing; Dependency: Department counseling categories).
* **Editable Referral Templates:** Pre-drafted supportive message templates for faculty review. (Value: Promotes supportive language; Dependency: `student-safe-language` rules).
* **Faculty Review Queue:** Focused inbox view showing pending support referrals awaiting notice decisions. (Value: Prevents overlooked cases; Dependency: `support_case_referrals` lifecycle).
* **Section Triage Filters:** Sorts active class loads by proportion of students in High Risk categories. (Value: Class-wide intervention targeting; Dependency: Section prediction aggregations).
* **Follow-Up Tracker:** Shows whether referred students have acknowledged notices. (Value: Closes feedback loop; Dependency: Student acknowledgment timestamps).
* **One-Click Section Export:** Quick CSV download for assigned section rosters. (Value: Departmental reporting; Dependency: Roster export security checks).
* **Encoding Completeness by Section:** Visual meter showing prelim/midterm/prefinal submission completion. (Value: Department tracking; Dependency: Grade batch status).

### Administrator Candidates (`Status: Proposed`)
* **Grade-Encoding Completion Meter:** Real-time dashboard gauge tracking overall faculty submission progress. (Value: Executive deadline oversight; Dependency: Class load aggregations).
* **Missing-Data Triage:** Filterable directory of student profiles missing historical or preliminary grades. (Value: Data quality oversight; Dependency: Partial data matrix).
* **Prediction Coverage and Freshness:** Program-wide breakdown of complete vs. provisional predictions. (Value: Model reliability visibility; Dependency: Migration 008 metadata).
* **Department Intervention Overview:** Longitudinal chart showing support cases opened, notices sent, and cases closed. (Value: Retention program evaluation; Dependency: Support workflow history).
* **Curriculum Bottleneck Indicators:** Analytics ranking courses by multi-year failure and incomplete rates. (Value: Curriculum review insights; Dependency: Historical grade records).
* **Data-Quality Dashboard:** Administrative tool auditing unassigned courses or blank elective tracks. (Value: Prototype data integrity; Dependency: Schema verification tools).

### Advanced Analytics & Interface Exploration Candidates (`Status: Proposed`)
* **Advanced Analytics & Distribution Visualizations:** Longitudinal dispersion curves, multi-cohort score distributions, and risk-migration models. (Value: Macro research visualization; Dependency: 5-cohort longitudinal dataset).
* **Administrative Audit-Log Usability Enhancements:** Enhanced date range filtering, faceted search, and columnar formatting for administrative audit history beyond baseline DATA-UI-WP3 operational table requirements. (Value: Advanced audit inspection; Dependency: Operational table completion).
* **Table Micro-Interactions & Customization:** Interactive table column reordering, visual density toggles, and animated row preview popovers. (Value: User interface personalization; Dependency: Base table accessibility standards).

---

## 10. Rejected or Removed Concepts

The following concepts were formally evaluated and **rejected** from the approved implementation scope:

1. **Academic Standing & Strike Tracking:**
   * *Status:* `Rejected`
   * *Rationale:* The prototype risk-triage categories (High, Moderate, Low) provide advisory decision support, not official institutional standing. Implementing "strikes", "probation countdowns", or "delinquency" flags creates unvetted academic pressure, conflates advisory risk with official registrar standing, and violates the non-punitive boundary.
2. **Automated Dismissal or Disqualification Warnings:**
   * *Status:* `Rejected`
   * *Rationale:* Academic retention and dismissal at UDM are governed strictly by the University Registrar, College Dean, and Faculty Committee. Automated systems must never issue binding status conclusions.
3. **Scholarship and Allowance-Risk Claims:**
   * *Status:* `Rejected`
   * *Rationale:* UniFAST tuition subsidies and student allowance rules depend on institutional policies outside the prototype's authority. Predicting allowance forfeiture based on preliminary scores is factually unverified and ethically inappropriate.
4. **Unilateral Automated Student Notices:**
   * *Status:* `Rejected`
   * *Rationale:* System predictions may generate candidate support cases, but notices to students must **always require human faculty review and discretion**.

*Reconsideration Constraint:* These concepts will not be reconsidered without official institutional policy documentation, formal adviser authorization, and explicit project-owner approval.

---

## 11. Paused Larger Packages

* **WP-8: Security, Strict SQL Mode, and Operational Hardening:**
  * *Status:* `Paused`
  * *Scope:* Comprehensive audit of Admin/Faculty POST handlers under strict SQL mode, non-destructive role authorization testing, and CSRF validation for remaining endpoints.
  * *Resume Trigger:* Scheduled following frontend UI closeout.
* **WP-9: Canonical Database Consolidation and Manuscript Synchronization:**
  * *Status:* `Paused`
  * *Scope:* Pre-export cleanup of temporary `_backup_%` tables, verification of exactly 18 base tables, regeneration of canonical `database/udm_radar.sql`, and manuscript technical alignment.
  * *Resume Trigger:* Final pre-demonstration phase.
* **5-Cohort Longitudinal Synthetic Data Expansion:**
  * *Status:* `Paused`
  * *Scope:* Synthesizing multi-year cohorts (Cohorts 2022–2026) across Years 1 to 4 to demonstrate curricular topology across 58 subjects.
  * *Resume Trigger:* Blocked pending formal verification of official UDM curriculum track allocations and elective offerings.

---

## 12. Manuscript Synchronization

* **Research Artifact Status:** The capstone manuscript is an in-progress academic document. The active repository implementation, test suites, and database migrations represent the primary technical source of truth.
* **No Unreviewed Manuscript Edits:** The manuscript DOCX must not be edited casually during technical Work Packages.
* **Consistency Register:** A dedicated tracking register (`docs/MANUSCRIPT_SYNC_REGISTER.md`) is planned for creation during WP-9 to document technical distinctions (e.g., prototype scale direction, synthetic data parameters, Latin honor thresholds) without fabricating empirical evaluation metrics.

---

## 13. Execution Order

ACADEMIC-HOTFIX-WP1 and DEV-AI-WP1A are complete and merged. The owner-approved temporary order while mobile recovery is blocked is:

1. DOMAIN-AUDIT-WP1 — read-only role needs and feature selection.
2. ADMIN-WP1A — data coverage and encoding freshness, subject to audit findings.
3. FACULTY-WP1A — structured intervention reasons and follow-up, subject to audit findings.
4. DATA-UI-WP3 — operational tables and workflow actions, with table-only overlap controls.
5. Recover and complete UI-WP-MOBILE from the original implementation.
6. UI-WP1B — tooltip, SVG, and Academic Guide accessibility.
7. DATA-UI-WP4 — charts and visualizations.
8. Final Cross-Portal Browser Validation.

If the mobile implementation becomes available, re-evaluate with the owner whether to finish it immediately or finish the active independent package first. Preserve its stash/backup and integrate current main through reviewed recovery; never reconstruct it from memory. Keep packages separately reviewable and merge one implementation package before starting the next.

The former post-mobile roadmap synchronization gate no longer blocks this owner-approved planning update. Later completion records must still use actual verified merges; mobile is not marked complete. WP-8, WP-9/manuscript synchronization, and five-cohort expansion remain paused under their existing policy/approval dependencies.

---

## 14. Planning Decision Log

* **October 2026 — Synthetic Data Boundary:** Registrar data access denied under privacy regulations; Capstone Adviser formally authorized synthetic academic records for prototype evaluation.
* **October 2026 — Human-in-the-Loop Interventions:** Automated predictions generate support cases for faculty review, but never unilateral student notices. Student acknowledgment signifies receipt, not admission.
* **October 2026 — Removal of Academic Standing & Strikes:** Removed strike countdowns, academic standing flags, and scholarship predictions to avoid punitive framing.
* **October 2026 — Blue Indicator Border Contract:** Reserved active blue borders (`var(--accent-blue)`) strictly for opened roster drilldowns.
* **October 2026 — DEV-AI-WP1 Adoption:** Adopted two-tier AI development governance (`AGENTS.md` for portable cross-agent guidance; `.agents/` for Antigravity extensions). Rejected third-party MCP servers and external plugins.
* **October 2026 — Mobile Recovery Isolation:** UI-WP-MOBILE recovery paused safely until branch/patch transfer from the original PC; reconstruction from memory strictly barred.
* **October 2026 — Historical Document Duplicates Retention:** The repository currently preserves root and `docs/audits/` copies of four historical planning documents (`CURRENT_DEMO_PLAYBOOK.md`, `CURRENT_UI_BACKLOG.md`, `CURRENT_UI_VALIDATION_REPORT.md`, `EXTERNAL_IDEA_RECONCILIATION.md`) annotated with historical snapshot banners. Their canonical location will be determined in a subsequent documentation-cleanup package before removing duplicates; they must not continue to be edited concurrently without review.



* **October 10, 2026 — Owner-Approved Mobile Bypass:** UI-WP-MOBILE remains approved but blocked because the original implementation is unavailable for recovery. Independent audit, coverage, Faculty support, and operational-table packages may proceed in the Section 13 order with explicit mobile-overlap controls; no abandonment or reconstruction is authorized. This replaces the earlier requirement to finish mobile before all independent work.
