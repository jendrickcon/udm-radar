# System History and Accepted Baseline Ledger — UDM-RADAR

**Document Version:** 1.0.0 (Post-Defense Baseline)
**Accepted Baseline Git SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Purpose:** Chronological milestone register of architectural decisions, database evolutions, and accepted pull requests.

---

## 1. Accepted Milestone History (Completed & Merged Work Only)

The following milestones represent verified, approved, and merged codebases on `origin/main` up to the accepted baseline SHA (`a79fb4838c5e2bc163cf943cda20afba9946d26b`):

| # | Milestone & Work Package | Date | Commit / PR | Primary Deliverables & Files | Verification Suites | Status |
|---|---|---|---|---|---|:---:|
| **M-01** | **Database Migrations & Workflow Tables (WP-1)** | 2026-10-01 | PR #24 | Migrations `000` to `004`: Added `export_audit_logs`, `student_profiles.record_status` (`Active`, `Archived`, `Graduated`), feedback message threads, and intervention tables (`academic_support_cases`, `support_actions`, `support_case_referrals`, `support_status_history`, `admin_change_log`). | Integration tests on scratch; idempotency verification. | **MERGED** |
| **M-02** | **Final-Grade Storage Migration (`VARCHAR(10)`) (WP-3)** | 2026-10-05 | PR #28 | Migration `005`: Converted `grades.final_grade` from `DECIMAL(4,2)` to `VARCHAR(10) NULL` with hardened binary CHECK constraint `chk_grades_final_grade_domain`. Allowed official non-numeric outcomes (`INC`, `DRP`, `P`, `DO`, `DU`, `FA`, `UD`) without numeric zero coercion. | Unit: `academic_outcome_contract_test.php`, `final_grade_helpers_test.php`. | **MERGED** |
| **M-03** | **Course Value & Curriculum-Track Repair (WP-6)** | 2026-10-08 | PR #32 | Migration `006`: Standardized student profiles to canonical tracks (`BSIT - Software Development`, `BSIT - Data Science`, `BSIT - Cyber Security`) with CHECK constraint `chk_student_profiles_course_valid`. | Data pre-audit assertions; temporary migration backup table. | **MERGED** |
| **M-04** | **Prediction Contracts & Provenance Harmonization (WP-5)** | 2026-10-12 | PR #37 | Migration `007`: Added `prediction_source` (`decision_tree`, `heuristic`, `calculation_fallback`) to `predictions` and `academic_support_cases` with binary check constraints. Removed silent zero-injections in feature vectors. | Unit: `prediction_source_helpers_test.php`; batch prediction smoke tests. | **MERGED** |
| **M-05** | **Prediction Completeness Metadata & Support Workflow (WP-7)** | 2026-10-15 | PR #42 | Migration `008`: Introduced 4-tier generation metadata (`data_completeness`, `is_provisional`, `provisional_basis`, `input_subject_count`, `expected_subject_count`) with 5 database CHECK constraints. Server-side HMAC proxy for Flask ML secrets. | Unit: `prediction_explanation_test.php`; scratch rebuild verification. | **MERGED** |
| **M-06** | **Multi-Year Synthetic Cohorts & Midterms (COHORT-DATA-WP1/WP1B)** | 2026-10-17 | PR #48 | Seed scripts `tools/seed_multiyear_cohorts.php` (15 multi-year validation cases) and `tools/seed_section_midterms.php` (calibrated midterm grade progressions across IT-31 through IT-38). Strict privacy boundaries (@synthetic.udm.edu.ph). | Integration: `multiyear_cohort_seed_test.php`, `section_midterm_seed_test.php`. | **MERGED** |
| **M-07** | **Faculty Support Reasons & Timeline (FACULTY-WP1A)** | 2026-10-18 | PR #50 | Migrations `009` & `010`: Added structured `reason_code` (6 canonical options) and immutable `notice_issued_at` datetime to `support_case_referrals`. Per-tab alert badges for faculty. | Unit: `support_reasons_test.php`, `notice_timeline_and_tab_badges_test.php`. | **MERGED** |
| **M-08** | **Student PEF/CC Planning and PDF Export (WP-PEF-CC)** | 2026-10-20 | PR #51, PR #54 | Implemented Pre-Enrollment Form & Curriculum Checklist engine (`config/pef_cc.php`, `student/enrolment_planning.php`). Supported States A, B (Provisional with red banner), C (Review-Required), D (Ineligible). Enforced strict 8pt minimum font floor on legal folio geometry. | Unit: 6 focused PHP contracts (915 assertions); browser: `student-pef-cc-*.spec.js`. | **MERGED** |
| **M-09** | **Goal Calculator Feasible GWA Options (UX-011)** | 2026-10-22 | PR #56 | Disabled mathematically unreachable per-subject final point targets in `student/calculator.php` using canonical 30/30/40 weighting. Preserved accessible tooltips and clear scenario GWA math without extra visual clutter. | Unit: `goal_calculator_contract_test.php`; browser: `goal-calculator-feasibility.spec.js` (29 cases). | **MERGED** |
| **M-10** | **Administrator Grades Modal with Read-Only History** | 2026-10-22 | PR #57 | Upgraded student review modal in `admin/grades.php` with visual hierarchy, stable `MODERATE` badge column, and lazy-loaded read-only First Year through Third Year academic history accordion via shared helper `includes/grade_history.php`. Current-semester corrections remain proposal-based. | Unit: `grade_history_test.php` (16 checks); browser: `admin-grades-student-modal-history.spec.js` (15 tests). | **MERGED** |
| **M-11** | **Enrollment Terminology Standardization** | 2026-10-24 | PR #58 | Unified user-facing navigation, headers, and action buttons to standard institutional spelling ("Enrollment Planning" / "Enrollment Clearance"). Preserved legacy routing compatibility. | Textual inspection; browser navigation checks. | **MERGED** |
| **M-12** | **Final Deployment Blocker Resolutions** | 2026-10-25 | Commit `75c5caa` | Resolved modal close button focus timing, desktop modal width constraint, and test fixture syntax quirks. Verified 66 component browser checks clean. Sol established canonical main checkout at `C:\xampp\htdocs\capstone`. | Full regression test suite; Apache serving canonical checkout. | **MERGED** |
| **M-13** | **Member Database Onboarding & Portability** | 2026-10-26 | PR #60 (`a79fb48`) | Standardized `config/db.example.php` with `getenv('DB_NAME')`, hardened `.gitignore` against accidental root SQL exports, corrected rebuild tool docs to migrations 000-010, created starter seed `001_synthetic_demo.sql` with safety refusal guard, and added `database/verify/verify_schema.sql`. | Disposable DB verification (`udm_radar_val_temp`); Composer platform reqs (16/16). | **MERGED** |

---

## 2. Active Work and Candidate Packages (Not in Accepted Baseline)

The following work items are currently active or awaiting review. They must **not** be treated as accepted baseline code until merged into `main`:

| Candidate Work Item | Author / Owner | Branch / Worktree | Deliverables & Scope | Current Status |
|---|---|---|---|:---:|
| **Post-Defense Continuity & Agent Knowledge Package** | Antigravity | `docs/post-defense-continuity-package` | 18 documentation files and templates under `docs/post-defense/`, offline continuity ZIP, analytics definitions, and single-writer governance. | **COMPLETED ON BRANCH, PR #61 PENDING MERGE** |
| **Post-Defense Analytics Implementation (Phases 1–6)** | Sol | `feat/post-defense-*` | Implementation of FR-01 through FR-08, multi-dimensional analytics filtering, and Chart.js UI modernization. | **ACTIVE WORK IN PROGRESS (NOT YET AN ACCEPTED MILESTONE)** |

---

## 3. Superseded or Excluded Historical Assets

1. **Superseded Monolithic Baseline Dump:**
   - Pre-WP-9 `database/udm_radar.sql` (14,224 lines mixing legacy schema and developer records) was completely superseded in commit `b7a24a3` by a reconciled, clean 397-line schema-only baseline.
2. **Excluded Migration 011:**
   - Migration 011 is excluded from the accepted migrations 000 to 010 baseline and remains unapproved.
3. **Public `.xlsx` PEF/CC Export:**
   - Early proposals considered direct student spreadsheet download. This was superseded by the approved public PDF contract with an internal private Excel COM rendering engine to protect template formulas and formatting integrity.
