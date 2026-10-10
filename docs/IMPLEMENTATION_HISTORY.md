# UDM-RADAR Implementation History Ledger

> [!NOTE]
> **Repository Implementation-History Ledger:** This document provides a synchronized engineering summary of completed Work Packages, PR merges, core deliverables, and test evidence.
> Git history, approved database migrations, automated regression checks, and current repository implementation remain the primary technical evidence.
> For current project status, active package boundaries, and forward-looking plans, consult the canonical planning source: [ROADMAP.md](ROADMAP.md).

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
  * Corrected historical pass-rate misclassification in `admin/analytics.php` (evaluating `< 1.75` as failure rather than `$pt > 0`).
  * Corrected pass-rate denominator: strictly excluded dropped courses (`DRP`) (`recognized_outcome_count = passed + failed`), correcting reported pass rates.
  * Removed duplicate GWA recalculations from raw term-batch submission approvals.
  * Created reproducible parity audit tool (`tools/audit_gwa_parity.php`).
* **Test Evidence:** 108 assertions in `tests/integration/gwa_parity_test.php`; verified 100% GWA parity across 289 baseline profiles.

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
