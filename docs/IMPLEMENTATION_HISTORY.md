# UDM-RADAR Implementation History Ledger

> [!NOTE]
> **Repository Implementation-History Ledger:** This document provides a synchronized engineering summary of completed Work Packages, PR merges, core deliverables, and test evidence.
> Git history, approved database migrations, automated regression checks, and current repository implementation remain the primary technical evidence.
> For current project status, active package boundaries, and forward-looking plans, consult the canonical planning source: [ROADMAP.md](ROADMAP.md).

---

## Post-defense analytics MVPs — local feature implementation (2026-10-26)

- **Base/branch:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`, `feat/post-defense-analytics-mvp`; not merged or deployed. Five work packages were established before implementation against the approved v3 guide.
- **Behavior:** one scoped read-only backend/API and three role views; canonical weighted means, explicit recorded-slot coverage, safe point/relative changes, separate stored-estimate/source/provisional/rule interpretation, six Administrator comparison dimensions, support count/time evidence and authorized existing-workspace drill-down. No invented numerical range, historical assignment or deferred decision.
- **Verification:** 80 new unit + 44 owned disposable SQL checks; 512 existing academic/prediction PHP checks; 29 feature desktop + six Faculty layout/source + 49 existing generated browser cases passed, no failures/skips. Independent review was unavailable at the account usage limit; author self-review and local delivery are recorded; exact final results and limitations live in [10_IMPLEMENTATION_STATUS.md](post-defense/10_IMPLEMENTATION_STATUS.md).
- **Owner continuation directive:** “from now on skip all mobile testing and screenshotting and focus on desktops”; no new mobile/screenshot work. The test fixture creates and removes only a provenance-marked temporary synthetic database; live/scratch, Apache, canonical checkout, active model/dump, starter seed, Migration 011, private responses, manuscript and recovery/handoff are outside scope.

---

## Admin Grades Student Modal with Read-Only Academic History — Implementation Ready for Review (2026-10-22)

- **Package:** `ADMIN-GRADES-STUDENT-MODAL-HISTORY`, base `8e43e6ebbee41d3f86bf4412b8a31873f0e4edda`, branch `fix/admin-grades-modal-history`.
- **Visual Baseline & Layout:** Aligned with the Administrator Students modal (`admin/students.php`) visual hierarchy: header, context subline (`studentNo · Section · Year · Status`), 3 summary cards (Cumulative GWA Historical, Projected Term GWA, Advisory Risk Level), and expanded desktop width `width: min(920px, calc(100vw - 32px))` reserving stable column width for the MODERATE risk badge without crowding. Contained horizontal scroll via `.grades-modal-scroll` ensures no page-level overflow across all viewports.
- **Current-Semester Proposal Governance:** Current-semester grade corrections remain strictly proposal-based and audited via `pending_corrections` with CSRF protection, admin role authorization, and mandatory correction reason prompt. Direct database editing is prohibited.
- **Read-Only Academic History:** Historical grades covering First Year through the latest recorded academic term are accessible inside the same modal via a collapsed `View Grade History` control. Historical grades are strictly read-only (no edit or proposal controls).
- **Lazy Loading & Performance:** Historical grades are lazy-loaded on demand upon first activating `View Grade History` via `grades_data.php?action=academic_history`. Re-expanding uses cached modal data; opening another student clears the cache. Initial page load remains lightweight.
- **Shared Query & View Model:** Extracted and unified academic history queries and curriculum-year grouping into shared helper `includes/grade_history.php`, reused by `admin/grades.php`, `admin/grades_data.php`, and `admin/students.php`.
- **Academic Rules Integrity:** Advisory risk levels remain non-punitive decision support. Missing grades are rendered as neutral indicators (`—` or `In Progress`) and are not treated as zero or failure. Non-numeric outcomes are excluded from unit-weighted Term GWA calculations via canonical `computeWeightedGWA()`.
- **Boundary:** Decision-support prototype operating on synthetic academic records. No database schema changes, migrations, or genuine record modifications applied. No unrestricted editing, registrar replacement, institutional certification, or production deployment claim.

---

## Goal Calculator feasible targets — owner-accepted integration (2026-10-22)

- **Package:** `GOAL-CALCULATOR-FEASIBLE-GWA-OPTIONS-SOL-WP1`, base `0642417025aa558a3cb3b215f594d58e01e27aa5`, branch `codex/goal-calculator-feasible-gwa-options`. Owner accepted the implementation report and authorized commit, push and PR creation after final focused verification on 22 October 2026. Merge remains a separate owner action.
- **Behavior:** Disable unreachable per-subject final-point targets using canonical percentage conversion and 30/30/40 weights; preserve valid goals, clear invalid goals/stale suggestions and guard manipulated controls. Unit-weighted scenario GWA and existing zero/status exclusions retained. No aggregate-GWA goal selector added.
- **Owner UI revision:** Keep original option text and compact layout; no extra visible notes. Hover titles are supplied for disabled options where the native browser supports them. Accessible descriptions/status remain visually hidden.
- **Boundary:** Pure identifier-free math and controlled generated HTML; no app/database/Flask call or stored-grade change. Existing dashboard GWA fixture has an unrelated pre-existing markup-boundary failure, reported separately. No official-policy, authenticated-UAT or deployment claim.
- **Tracking:** UX-011 in [UI_UX_FINDINGS_REGISTER.md](UI_UX_FINDINGS_REGISTER.md). Verification logs/screenshots are ignored local evidence, not repository deliverables.

---

## PEF/CC PDF/state continuation — owner acceptance and final gate (2026-10-20)

- **Delivery:** Initial WP1 PR #51 is merged; continuation PR #54 is open and unmerged. Published commit `0a52b3c` is preserved. Follow-up commits are conditional on the required validation gate; no history rewrite or automatic merge is authorized.
- **Owner manual acceptance:** 20 October 2026, including safe State C. Normal, incomplete-grade Provisional and safely verified Review-required PDFs are accepted; unsafe output remains blocked. This is not formal UAT, institutional approval, genuine-record validation or Azure/IIS production renderer validation.
- **Current contract:** Public PDF only; private XLSX engine; r6 SHA-256 `5941b7b36c5bbee585f32162397de2cd18e0279171cdd6470578e389b18e43ec`. Selected-term GWA is numeric only when complete/calculable, otherwise Pending/blank. No invented section/subjects, partial GWA substitution or public XLSX fallback. Existing previous standing and CC Remarks/Status/clean signatories remain.
- **Fresh validation:** Six PHP scripts: 915 assertions passed/0 failed. JavaScript contract:19 passed/0 failed. Pinned Playwright:53 passed/0 failed/59 authenticated skips. Native r6 audit:4,434 checks/4,378 passed/56 font-floor failures, exit1; active CC text6.12pt. Guarded read-integration preflight:1 assertion/1 schema prerequisite failure, exit1. HTTP integration unavailable without a valid guarded state. No fixtures inserted, migrations, follow-up commits, pushes or PR updates. Automated final State C coverage was deferred before this gate and is not inferred from manual review. Generated/static and mocked browser checks are distinct from authenticated scratch/native rendering. A scratch DECIMAL final_grade column is a blocking textual-outcome prerequisite; no automatic migration is permitted. Native Folio geometry and the unchanged 8pt operational floor remain required.
- **Blocking UI correction:** Fresh generated manual-control tests failed before and passed after a minimal `form.elements` listener correction; the controls are associated with a hidden transport form through the form attribute. The approved A/B/C/D policy and private geometry remain unchanged.
- **Deployment:** Windows Excel COM is the current local prototype renderer. Apache/IIS identity, Excel/printer availability, private ACLs, cleanup and production renderer operation need host-specific validation.
- **Historical boundary:** The dated entry below describes the 18 October review state. Its unmerged/Draft/public-XLSX wording is historical and does not override the current PDF contract or PR status.

---

## PEF-CC-WP1 — unmerged draft review record (2026-10-18)

- **Status:** Draft software review candidate; release blocked on maximum-text print layout and owner paper acceptance. **Not merged.** The current PR and final delivery commit are recorded in the external delivery report.
- **Branch:** `feat/student-pef-cc-planning-export`.
- **Verified integration base / remote main:** `22fc6adbfefec449f634bb4d72187b7fcd503288` (PR #50), retained through conflict-free reviewed normal merges, most recently `2acc7170bb08d9ebe7be919b168b6df470d7ece0`. PR #49 (`a52962f`) and PR #50 were inspected for overlap before integration. Their balanced Midterm, Batch Predict and visibility implementations remain unchanged by this package.
- **Scope:** Original 25 paths plus four curriculum/policy/print-test paths and two focused print-contract test files: 31 total. Active-Student completed-term preview, SELECT-only owned queries, readonly model, HMAC snapshot, private PhpSpreadsheet 5.10.0 writer and Student navigation remain. Approved SD mapping/current-term policy is followed by a narrow printable-input contract. Composer lock versions remain intentional; no dependency upgrade occurs during continuation.
- **Academic boundary:** Canonical selected-term GWA, unavailable as blank, full-row coverage, 13/12 capacity rejection and reference-only CC. Approved SD 2022–2023 nominal subjects are read-only for coherent Regular contexts. Supported Irregular manual planning is preserved; historical CY/DS require review. No official pacing/standing, prerequisite approval, offering decision or multi-term Study Plan is added.
- **Template input:** Preserved sanitized v0.1 SHA-256 `0e42f6ecbdd386d35e25a658dee0cda4edf48d14d1cdc0b2754a544746160d4c`. Private candidate v0.2-r2 SHA-256 `c1895897710d29672960a7414155627409dd847260236c1774f1f444d9bfed02` changes only A51 wrapping and row 51 height to 96 pt. Other original ZIP parts remain byte-identical. Neither private binary enters Git. Folio 8.5 × 13 inches and retained supplied artwork are owner-authorized; final print acceptance is pending.
- **Verification contract:** New production-bound unit checks, scratch-only owned fixtures and real-login HTTP/browser tests; academic/navigation regressions; offline native Excel checks. Execution results and commands accompany the migration evidence. No live `udm_radar` mutation is authorized.
- **Prior delivery (`4e5bff4`) results:** PEF PHP 960 assertions passed (537 unit, 320 owned-read integration, 103 real-login HTTP/download); seven existing PHP scripts 658 assertions passed. Pinned Playwright 1.63.0 preservation-safe run: 510 passed, six credential skips, zero failures, including all 45 authenticated PEF cases. Of 534 discovered browser cases, 18 cohort/fixed-account mutators were not run. Earlier unbounded native matrix: 554 checks / 106 failures preserved. Additional read-only Midterm: 19 passed / 11 failed for absent fixed-cohort context. These are historical execution results and are not reused for the print-contract phase.
- **Printable-contract review (2026-10-18):** All 58 sanitized active SD subjects have codes ≤6 and titles ≤46 characters. Proposed bounds: display name 60, student number 20, recorded/proposed code 6 / title 46, section 96, single-line text. Model and writer reject extremes without truncation, state mutation or output; safe inputs remain visible with accessible field highlights. Current software checks: 702 PHP assertions and 16 generated-static-HTML browser cases passed. The 11-case real-catalogue native matrix retains 22 single-page Folio PDFs and full PEF code/title containment; 1,328 checks / 228 failures are all font-floor failures (PEF 7.44 pt; inspected active CC text 6.12 pt). Seven defensive cases reject before export. No template, database, Batch Predict, Midterm or model change occurred. No authenticated/full browser or DB test rerun is claimed. Prior Midterm failures remain an environmental fixture limitation. PR #51 remains Draft; owner Excel/physical print acceptance is unperformed.
- **Delivery boundary:** Validated commits are pushed only to the dedicated branch, without automatic merge. The verified recovery folder/transfer ZIP include an incremental branch Git bundle, binary patch, source copies, sanitized template and manifest. Final implementation HEAD is recorded there to avoid a self-referential commit hash in this file.
- **Pending release gates:** The 8 pt floor for operational PEF/CC text, owner actual-size Excel Print Preview/paper acceptance, and deployment-specific Apache ACL/runtime/cleanup scheduling. WP2/WP3 remain deferred.
- **Validation limits:** Native Excel PDF and raster review confirm normal/96-character section visibility, one Folio page per sheet and retained capacity. Current operational codes/titles stay contained; font-floor failures remain. Extreme historical 20/150-character stress cases now reject without a file. The 8 pt floor is not claimed as institutional policy. Prior broad/browser/DB totals are not rerun or reused in this print-only phase. Composer publication-metadata limitations remain documented without dependency changes.

---

## Foundation Remediation & Intelligence Engine (WP-0 to WP-7)

All foundational packages (WP-0 through WP-6 and WP-7) were consolidated on feature branch `feat/cohort-scaffolding` and merged into `main` via **Pull Request #16** (Merge commit `f408e62`).

### WP-0: Baseline Recovery and Verification
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `f408e62`
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `1ee0b9e`, `d989c1b`
* **Major Delivered Scope:**
  * Restored overwritten CLI regression training script (`python_ml/train_model.py`).
  * Added preliminary feature fallback in `decision_tree.py` to prevent inference exceptions.
  * Preserved graduated student records in longitudinal analytics queries.
* **Safety Invariant:** `python_ml/model.pkl` and `model_metrics.json` preserved byte-identical.

---

### WP-1: Database Migrations and Workflow Tables
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `f408e62`
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `7563584`, `7e2c6d4`
* **Major Delivered Scope:**
  * Migrations 000 & 003: Added `export_audit_logs` tracking administrative PDF and CSV exports.
  * Migration 001: Added `student_profiles.record_status` (`Active`, `Archived`, `Graduated`), separating account lifecycle from academic pacing (`Regular` vs `Irregular`).
  * Migration 002: Standardized feedback status values (`pending`, `reviewed`, `resolved`).
  * Migration 004: Created core intervention workflow tables (`academic_support_cases`, `support_case_referrals`, `support_actions`, `support_status_history`, `admin_change_log`).
* **Test Evidence:** Migrations executed idempotently on `udm_radar_scratch`.

---

### WP-2: Canonical Grade Vocabulary and Helpers
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `f408e62`
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `b16282d`, `8f3d14e`, `401ac94`
* **Major Delivered Scope:**
  * Centralized official grading constants and conversions in `config/constants.php`.
  * Enforced raw numeric percentages ($0\text{--}100$) for Prelim, Midterm, and Pre-Final.
  * Canonicalized helper functions: `convertPercentageToPoint()`, `isPassingFinalGrade()`, `isFailingFinalGrade()`, `isValidFinalGrade()`.
* **Test Evidence:** 266-assertion unit test matrix in `tests/unit/final_grade_helpers_test.php`.

---

### WP-4: GWA Parity and Historical Analytics Reconciliation
*(Executed prior to WP-3 to establish baseline calculation standards)*
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `f408e62`
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `44e788a`, `f077fa4`, `882f2fd`, `6e9c939`
* **Major Delivered Scope:**
  * Historical implementation used `< 1.75` as subject failure. **Correction:** this conflated prototype risk with subject outcomes; owner-approved ACADEMIC-HOTFIX-WP1 corrects numeric 1.00–4.00 to passing and INC to unresolved. WP-4's merge history remains historical evidence, not policy validation.
  * Corrected pass-rate denominator: strictly excluded dropped courses (`DRP`) (`recognized_outcome_count = passed + failed`), correcting reported pass rates.
  * Removed duplicate GWA recalculations from raw term-batch submission approvals.
  * Created reproducible parity audit tool (`tools/audit_gwa_parity.php`).
* **Test Evidence:** 108 assertions in `tests/integration/gwa_parity_test.php`; verified 100% GWA parity across 289 baseline profiles.

### ACADEMIC-HOTFIX-WP1: Subject Outcome and Incomplete-Grade Semantics
* **Status:** Complete and Merged.
* **PR Number:** [PR #28](https://github.com/jendrickcon/udm-radar/pull/28).
* **Merge Commit:** `c3dee0e8d8b414b67299840bfdccb945b6b0ba2e` (normal merge into `main`).
* **Branch:** `fix/academic-subject-outcome-semantics`; based on DOCS-WP1 PR #27 (`81c6303`).
* **Implementation Commits:** `34255bc`, `8942930`, `560cba4`, `fb05255`.
* **Scope:** Subject outcomes and incomplete counts, screen/PDF parity, textual-grade coercion prevention, and corrected future failure-derived prediction features.
* **Preserved:** Numeric GWA functions and legacy-zero treatment; separate legacy honors-disqualification behavior and thresholds; deferred DO/DU/DRP/FA/UD semantics; stored grades, predictions, cases, SQL dump and model artifacts.
* **Evidence boundary:** Passing numeric grades may remain HIGH risk. INC is unresolved and is not automatically expired or converted. Existing prediction outputs retain the logic and inputs used when generated.
* **Policy:** [UDM Student Manual 2025](https://udmwebsite.udm.edu.ph/wp-content/uploads/2026/01/Student-Manual-2025-FINAL.pdf), Part II D, printed pages 15–18; separate honors guidelines and failed-unit GWA policy remain pending verification.
* **Merge-time evidence:** 616 PHP regression assertions, 141 scratch integration assertions, and 101 browser tests passed using pinned Playwright 1.63.0 and installed Edge; six credential-dependent tests skipped, no active failures. Browser evidence included generated fixtures, mocks, and source inspection; authenticated walkthroughs were unavailable. Academic scratch checksums, dependency declarations and protected assets were preserved. These historical totals are not permanent future acceptance counts.

---

### WP-3: Final-Grade Storage Migration (`VARCHAR(10)`)
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `f408e62`
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `f0bc2c0`, `ed7b13c`, `12ba47d`
* **Major Delivered Scope:**
  * Migration 005: Migrated `grades.final_grade` from `DECIMAL(4,2)` to `VARCHAR(10) NULL` to safely store official non-numeric outcomes (`INC`, `DO`, `DU`, `DRP`, `PASSED`) without numeric zero coercion.
  * Added `chk_grades_final_grade_domain` with exact-match `BINARY` byte comparison rejecting lowercase variants or whitespace padding.
* **Test Evidence:** 53 assertions in `tests/integration/final_grade_storage_test.php`.

---

### WP-6: Course and Curriculum-Track Repair
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `f408e62`
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `fa21e7d`, `6087663`, `9085ae5`
* **Major Delivered Scope:**
  * Migration 006: Standardized synthetic student profile courses to `'BSIT - Software Development'` and enforced `chk_student_profiles_course_valid` on `ENUM('BSIT - Software Development', 'BSIT - Data Science', 'BSIT - Cyber Security')`.
  * Added application locks (`LOCKED_COURSE`) in `admin/students.php` and `isValidCourse()` helper.
* **Test Evidence:** 60 assertions in `tests/integration/course_repair_test.php`.

---

### WP-5: Prediction Contracts and Provenance
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `92cbc05` (tag `wp5-approved` on feature branch); `f408e62` (into `main`)
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `03c78c1`, `e1dc6d0`, `1d30e5c`, `efc7db3`
* **Major Delivered Scope:**
  * Fixed silent feature zero injection: canonicalized PHP/Python feature contract to `current_prelim_point_avg` (with `current_prelim_avg` alias).
  * Standardized stored prediction sources via Migration 007 (`decision_tree`, `heuristic`, `calculation_fallback`).
  * Reconciled Python 70/30 fallback formula to canonical PHP 50/50 blend snapped to $0.25$.
  * Preserved 405 legacy baseline prediction rows with provenance as `heuristic`.
* **Test Evidence:** 62 assertions in `tests/unit/prediction_source_helpers_test.php`; 29 assertions in `tests/integration/prediction_contract_test.php`.

---

### WP-7: Prediction Completeness, Governance, and Support Workflow
* **Status:** Complete and Merged
* **PR Number:** PR #16 (via `feat/cohort-scaffolding`)
* **Merge Commit:** `8f65d9a` (tag `wp7-approved` on feature branch); `f408e62` (into `main`)
* **Feature Branch:** `feat/cohort-scaffolding`
* **Implementation Commits:** `6230270`
* **Major Delivered Scope:**
  * Migration 008: Added prediction completeness metadata (`data_completeness`, `is_provisional`, `provisional_basis`, `input_subject_count`, `expected_subject_count`).
  * Enforced 4-Case Partial Data Matrix (Case A: complete; Case B: historical only; Case C: prelim only; Case D: missing all fails closed).
  * Prevented provisional predictions from creating automated Academic Support cases.
  * Isolated Flask microservice secret via server-side HMAC proxy gateway (`api/model_governance.php`).
  * Added CSRF protection to Admin batch predictions.
  * Safeguarded database rebuild tool with target allowlist (`tools/rebuild_database.php`).
* **Test Evidence:** 844 passing automated assertions across 14 regression suites; 100% GWA parity across 289 profiles.

---

## Evaluation Readiness & Modern Data-UI Sprints

### UI Evaluation Readiness & Grading Alignment
* **Status:** Complete and Merged
* **PR Number:** PR #17 (initial merge into `main`), PR #20 (consolidated readiness merge into `main`)
* **Merge Commit:** `aa61cfe` (PR #17), `07608e0` (PR #20)
* **Feature Branch:** `feat/ui-evaluation-readiness`
* **Implementation Commits:** `01670c3`
* **Major Delivered Scope:**
  * Replaced inverted grading table with authentic UDM 2025 Student Manual scale ($4.00\text{--}0.00$, 30/30/40 formula, Latin honors).
  * Re-architected sidebar navigation: removed bulky prototype notice and replaced with floating help trigger and slide-in drawer (`includes/glossary_modal.php`).
  * Completely eliminated emojis across Student, Faculty, and Admin portals in favor of clean inline SVGs and `.status-dot` badges (0 emojis remaining).
  * Moved lengthy KPI descriptive text into hover tooltips (`.custom-tooltip`).

---

### Member 1: Support Notice Concurrency Hardening (AUD-01)
* **Status:** Complete and Merged
* **PR Number:** PR #18 (merged into `feat/ui-evaluation-readiness`); incorporated into `main` in PR #20 (`07608e0`)
* **Merge Commit:** `30670b1` (into `feat/ui-evaluation-readiness`)
* **Feature Branch:** `member1/backend-security-hardening`
* **Implementation Commits:** `08d7d71`
* **Major Delivered Scope:**
  * Resolved race condition in faculty support-notice issuance (AUD-01) in `faculty/feedback.php`.
* **Test Evidence:** 266 assertions in `tests/integration/faculty_notice_concurrency_test.php`.

---

### Member 2: Browser Accessibility & Focus Containment (UI-A11Y-01, UI-RESP-01)
* **Status:** Complete and Merged
* **PR Number:** PR #19 (merged into `feat/ui-evaluation-readiness`); incorporated into `main` in PR #20 (`07608e0`)
* **Merge Commit:** `ea02081` (into `feat/ui-evaluation-readiness`)
* **Feature Branch:** `member2/ui-browser-accessibility`
* **Implementation Commits:** `a254df7`, `3413a62`, `32cac54`, `9f96009`, `2b8efd8`, `e0314a2`
* **Major Delivered Scope:**
  * Contained keyboard focus inside Academic Guide modal dialog (UI-A11Y-01).
  * Reserved 76px mobile content gutter on screens $\le 600\text{px}$ to prevent floating help trigger overlap (UI-RESP-01).
  * Introduced Playwright browser test framework (`@playwright/test` 1.63.0) and established `tests/browser/faculty-academic-guide.spec.js`.

---

### UI-WP1A & DATA-UI-WP1A: Interaction & Table Accessibility Foundation
* **Status:** Complete and Merged
* **PR Number:** PR #21
* **Merge Commit:** `5d44917` (into `main`)
* **Feature Branch:** `feat/ui-wp1a-interaction-foundation`
* **Implementation Commits:** `ddb7f20`, `fc90ef6`, `7cd56bb`
* **Major Delivered Scope:**
  * Improved KPI card interaction states and decision-support context in `assets/js/ui.js`.
  * Added accessible record actions, semantic table sorting indicators, and mobile table containment across Admin and Faculty views.
* **Test Evidence:** Browser specs in `tests/browser/ui-wp1a-interaction-foundation.spec.js` and `tests/browser/data-ui-wp1a-table-accessibility.spec.js`.

---

### DATA-UI-WP1B: Visual Table Foundation
* **Status:** Complete and Merged
* **PR Number:** PR #22
* **Merge Commit:** `f00379c` (into `main`)
* **Feature Branch:** `feat/data-ui-wp1b-visual-table-foundation`
* **Implementation Commits:** `a20acee`, `951ae2c`
* **Major Delivered Scope:**
  * Standardized full-bleed table layouts across `admin/activity.php`, `admin/faculty.php`, `admin/grades.php`, and `admin/students.php`.
  * Unified sticky table headers, action column widths, and badge alignments in `assets/css/dashboard.css`.
* **Test Evidence:** Browser spec in `tests/browser/data-ui-wp1b-visual-table-foundation.spec.js`.

---

### DATA-UI-WP2: Active Drilldowns and Roster Behavior
* **Status:** Complete and Merged
* **PR Number:** PR #23
* **Merge Commit:** `0147b4f` (into `main`)
* **Feature Branch:** `feat/data-ui-wp2-active-drilldowns`
* **Implementation Commits:** `0e66d9f`, `178bf0b`
* **Major Delivered Scope:**
  * Implemented client-side roster drilldowns for student cohorts from KPI cards and section views in `admin/grades.php` and `faculty/dashboard.php`.
  * Standardized animated loading states, keyboard focus management, empty states, and active blue indicator borders (`var(--accent-blue)`).
* **Test Evidence:** Browser spec in `tests/browser/data-ui-wp2-active-drilldowns.spec.js`.

---

### Fix: KPI Dark-Mode Text & Action Hint Cleanup
* **Status:** Complete and Merged
* **PR Number:** PR #24
* **Merge Commit:** `01505da` (into `main`)
* **Feature Branch:** `fix/kpi-dark-mode-text`
* **Implementation Commits:** `f7da700`, `ad7dc97`
* **Major Delivered Scope:**
  * Resolved low text contrast on KPI cards under dark mode across Admin portal views.
  * Cleaned up duplicate action hint text in favor of subtle tooltips.
* **Test Evidence:** Browser spec in `tests/browser/kpi-dark-mode-contrast.spec.js`.

---

### Fix: Faculty Dashboard Warning-Banner Layout Hotfix
* **Status:** Complete and Merged
* **PR Number:** PR #25
* **Merge Commit:** `839e67f` (into `main`)
* **Feature Branch:** `fix/faculty-dashboard-warning-banner-layout`
* **Implementation Commits:** `813d26d`, `45873d3`
* **Major Delivered Scope:**
  * Restored missing closing `</div>` on line 274 of `faculty/dashboard.php`, preventing the entire dashboard (KPIs, sections, rosters) from being swallowed inside the brown attention banner (`.warning-banner`).
* **Test Evidence:** Layout regression spec in `tests/browser/faculty-dashboard-layout-regression.spec.js` verifying bounding box containment and isolation.

---

## AI-Assisted Development Governance

### DEV-AI-WP1: AI-Assisted Development Governance and Workflows
* **Status:** Complete and Merged
* **PR Number:** PR #26
* **Merge Commit:** `3eed894` (into `main`)
* **Feature Branch:** `feat/antigravity-developer-tooling`
* **Implementation Commits:** `f4b1eaf`, `de3730c`
* **Major Delivered Scope:**
  * **Portable Agent Instructions:** Root [`AGENTS.md`](../AGENTS.md) defining continuous directives for Codex, Antigravity, and standard coding agents.
  * **Workspace Rules:** Always-on canonical asset protector ([`protected-files.md`](../.agents/rules/protected-files.md)) and non-punitive language standards ([`student-safe-language.md`](../.agents/rules/student-safe-language.md)).
  * **Procedural Skills:** `academic-rules-guardian`, `playwright-verification`, `pr-preparation`.
  * **Interactive Workflows:** Non-mutating `/check-protected` and `/verify-package` slash commands.
* **Safety Invariant:** 100% repository-local; zero external MCP servers, third-party plugins, or Git hooks added.


---

### DEV-AI-WP1A: Cross-Agent Governance Safety Corrections
* **Status:** Complete and Merged.
* **PR Number:** [PR #29](https://github.com/jendrickcon/udm-radar/pull/29).
* **Merge Commit:** `528dde76a12ac031daa66ad2b30fc007fc671f6c` (normal merge into `main`).
* **Feature Branch:** `docs/ai-governance-safety-corrections`.
* **Implementation Commits:** `1a92340`, `98023e9`.
* **Delivered Scope:** Six guidance files covering independent academic contracts, complete diff layers, scratch mutation-path inspection, installed pinned Playwright checks, advisory recovery, evidence classification and skill-versus-permission boundaries. No runtime, model, test or dependency changes.
* **Verification:** Reviewed six-file allowlist, Markdown and three skill frontmatters using an existing YAML parser; repository entrypoints present. Bundled quick validator was unavailable without PyYAML; cross-host automatic discovery was not claimed. Application baseline verification was reported separately, not as testing instruction behavior.

### Owner-Approved Temporary Resequencing (October 10, 2026)
* **Planning status:** Approved change of order, not an implementation-completion claim.
* **UI-WP-MOBILE:** Approved but `Blocked: original implementation unavailable for recovery`; not abandoned, cancelled, reconstructed, or completed. The current checkout has no mobile stash or recovered implementation.
* **Authorization:** Independent packages may advance temporarily if they avoid mobile navigation, responsive grid architecture, and expected mobile-package files. DATA-UI-WP3 permits only narrowly scoped operational-table CSS with overlap review; no broad shared CSS changes.
* **Temporary order:** DOMAIN-AUDIT-WP1 → ADMIN-WP1A → FACULTY-WP1A → DATA-UI-WP3 → recover/complete UI-WP-MOBILE → UI-WP1B → DATA-UI-WP4 → Final Cross-Portal Browser Validation.
* **Audit gate:** DOMAIN-AUDIT-WP1 is read-only. Implementation packages require supported data, reviewed scope, and their own branches/PRs; finish and merge one before starting the next. Proposed Student planning and curriculum features do not become approved commitments through resequencing.
* **Recovery gate:** Once mobile is recovered, re-evaluate whether to finish it immediately or finish the active independent package first. Preserve the original stash and review integration against current main.
* **Canonical plan:** See [ROADMAP.md](ROADMAP.md), Sections 8 and 13.

---

## Evaluation Readiness & Modern Data-UI Phase (PR #31 to PR #35)

### ADMIN-WP1A: Data Coverage and Prediction Provenance Review
* **Status:** Complete and Merged
* **PR Number:** [PR #31](https://github.com/jendrickcon/udm-radar/pull/31)
* **Merge Commit:** `c0c0712` (into `main`)
* **Feature Branch:** `feat/admin-grade-coverage-provenance`
* **Implementation Commits:** `802fc83`, `e926a0e`
* **Major Delivered Scope:**
  * Added `admin/coverage.php` displaying section-level grade-encoding completion rates across Prelim, Midterm, and Pre-Final terms.
  * Added prediction completeness and provenance breakdown (complete, provisional, calculation fallback, insufficient data).
  * Introduced Data Coverage tab in the slide-in Academic Guide drawer.
  * Created dedicated helper functions in `includes/grade_coverage.php` and scoped stylesheet `assets/css/grade-coverage.css`.
* **Test Evidence:** 34 passing browser tests in `tests/browser/admin-grade-coverage.spec.js`; 28 assertions in `tests/unit/grade_coverage_test.php`.

---

### DOCS-UX-WP1: UI/UX Governance and Findings Register
* **Status:** Complete and Merged
* **PR Number:** [PR #32](https://github.com/jendrickcon/udm-radar/pull/32)
* **Merge Commit:** `1804315` (into `main`)
* **Feature Branch:** `docs/ui-ux-governance`
* **Implementation Commits:** `b606bda`, `492cd40`
* **Major Delivered Scope:**
  * Established living interface standards in `docs/UI_UX_GOVERNANCE.md` (calm workbench thesis, card discipline, student-safe copy).
  * Preserved qualitative heuristic review in `docs/audits/UDM_RADAR_VIBE_CODED_AUDIT_2026-10-10.md`.
  * Initialized usability findings tracking ledger in `docs/UI_UX_FINDINGS_REGISTER.md` (seeded issues UX-001 through UX-010).

---

### ADMIN-UI-HOTFIX-WP1: Student Directory and Dashboard Table Cleanup
* **Status:** Complete and Merged
* **PR Number:** [PR #33](https://github.com/jendrickcon/udm-radar/pull/33)
* **Merge Commit:** `5621352` (into `main`)
* **Feature Branch:** `fix/admin-table-layout-cleanup`
* **Implementation Commits:** `7dabc33`, `bb6b2c4`, `460d0ee`
* **Major Delivered Scope:**
  * Reclaimed table width in student directory (`admin/students.php`) by removing static `Program: BSIT` column (reduced from 10 to 9 columns).
  * Eliminated artificial vertical scroll overflow in the Admin Dashboard primary overview table (`admin/index.php`).
  * Implemented centered in-table empty-state rows (`colspan="9"` and `colspan="8"`) with filter-reset controls (`clearStudentDirectoryFilters()`, `clearDashboardFilters()`).
* **Test Evidence:** 9 passing browser tests in `tests/browser/admin-table-layout-cleanup.spec.js`.

---

### UI-WP-MOBILE: Mobile Navigation and Responsive Card Grids
* **Status:** Complete and Merged
* **PR Number:** [PR #34](https://github.com/jendrickcon/udm-radar/pull/34)
* **Merge Commit:** `17e8898` (into `main`)
* **Feature Branch:** `feat/ui-wp-mobile-navigation-grids`
* **Implementation Commits:** `08d1ed3`, `4b35179`, `6d369b3`
* **Major Delivered Scope:**
  * Recovered and delivered accessible mobile top bar ($\le 900\text{px}$) with sticky header and $\ge 44\times 44\text{px}$ touch targets.
  * Off-canvas mobile navigation drawer with focus trapping, Escape dismissal, overlay backdrop click closure, and background scroll locking.
  * Short-height ($390\times 700$) and landscape ($844\times 390$) scrolling support.
  * Responsive five-card KPI grid on Admin Dashboard ($5\text{ col} \rightarrow 3+2 \rightarrow 2\text{ col} \rightarrow 1\text{ col}$).
  * Responsive four-card KPI grid on Admin Students ($4\text{ col} \rightarrow 2\text{ col} \rightarrow 1\text{ col}$).
  * Responsive three-column section grid on Admin Grades ($3\text{ col} \rightarrow 2\text{ col} \rightarrow 1\text{ col}$).
* **Test Evidence:** 25 passing browser tests in `tests/browser/ui-wp-mobile-navigation-grids.spec.js`.

---

### SUPPORT-WP1: Structured Reasons, Notice Timeline, and Actionable Badges
* **Status:** Complete and Merged
* **PR Number:** [PR #35](https://github.com/jendrickcon/udm-radar/pull/35)
* **Merge Commit:** `3cb99c2` (into `main`)
* **Feature Branch:** `feat/faculty-support-reason-capture`
* **Implementation Commits:** `e5bb0b0`, `6eabdee`, `5178661`, `79e0dd2`
* **Major Delivered Scope:**
  * Migration 009: Added `reason_code` with binary database CHECK constraint enforcing 6 approved supportive reasons.
  * Migration 010: Added `notice_issued_at DATETIME NULL` to track exact notice issuance timestamp.
  * Notice Timeline: Faculty modal displays "Issued" and "Student acknowledgment"; student notice cards display "Received" and "Acknowledged".
  * Per-Tab Alert Badges: Actionable counts on Faculty "Student Concerns" and "Academic Support Referrals", and Student "Academic Support Notices".
  * Large Count Scaling: Badges expand cleanly without clipping for high counts ($99, 100, 125$) while preserving accessible `aria-label` descriptions.
* **Test Evidence:** 212 passed assertions across unit, integration, and browser test suites (14 browser tests in `tests/browser/faculty-support-notice-reasons.spec.js`, 53 unit tests in `tests/unit/support_reasons_test.php`, 46 unit tests in `tests/unit/notice_timeline_and_tab_badges_test.php`, 35 integration tests in `tests/integration/support_reason_storage_test.php`, 28 integration tests in `tests/integration/notice_timeline_storage_test.php`, 10 integration tests in `tests/integration/faculty_notice_concurrency_test.php`, 26 integration tests in `tests/integration/support_workflow_reconciliation_test.php`).
