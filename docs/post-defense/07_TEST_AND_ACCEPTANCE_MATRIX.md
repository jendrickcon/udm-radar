# Post-Defense Test and Acceptance Matrix — UDM-RADAR

**Document Version:** 1.1.0 (Post-Defense Planning & Granular Verification)
**Accepted Baseline SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Status Taxonomy:** `NOT IMPLEMENTED` | `TEST PLANNED` | `TEST EXISTS` | `PASSING` | `FAILING` | `BLOCKED` | `NOT APPLICABLE`

---

> [!IMPORTANT]
> **EVIDENCE MANDATE:**
> 1. A blank cell must **never** be interpreted as passing.
> 2. No test suite or row may be marked `PASSING` without recorded, reproducible execution evidence (exact terminal command, timestamp, database target, exit code, and artifact log path).
> 3. Features not yet written must remain `NOT IMPLEMENTED` or `TEST PLANNED`.
> 4. Tests dependent on unmerged schema changes (e.g. closure timestamps) must be explicitly marked `BLOCKED`.

---

## 1. Feature Verification & Acceptance Matrix

| Req ID | Target Role | Analytical Metric / Workflow | Unit Test Status & File | Integration Test Status & File | Browser Spec Status & File | Privacy / Boundary Check | Required Verification Evidence | Overall Status |
|---|---|---|---|---|---|---|---|:---:|
| **FR-01** | Student, Admin | Continuous Estimated GWA & Prediction Range (`MTR-06`, `MTR-07`) | `[TEST PLANNED]` `tests/unit/prediction_range_test.php` | `[TEST PLANNED]` `tests/integration/prediction_provenance_test.php` | `[TEST PLANNED]` `tests/browser/prediction-range-display.spec.js` | Zero discrete-risk claim on continuous output; non-guarantee subline present. | Terminal test log, bounded value assertion $[1.00, 4.00]$, active-model MAE verification. | `NOT IMPLEMENTED` |
| **FR-02** | Student | Point Delta ($\Delta\text{GWA}$) & Longitudinal Progression (`MTR-01` to `MTR-04`) | `[TEST PLANNED]` `tests/unit/gwa_progression_test.php` | `[TEST PLANNED]` `tests/integration/student_history_query_test.php` | `[TEST PLANNED]` `tests/browser/student-progression-trend.spec.js` | Directional point delta (higher is better on $4.00\text{--}1.00$ scale); log change excluded from student view. | Assertion log for regular, irregular, and summer terms. | `NOT IMPLEMENTED` |
| **FR-03** | Admin | Multi-Dimensional Analytics Filter Bar | `[TEST PLANNED]` `tests/unit/analytics_filter_sanitize_test.php` | `[TEST PLANNED]` `tests/integration/admin_analytics_query_test.php` | `[TEST PLANNED]` `tests/browser/admin-analytics-filters.spec.js` | Strict SQL injection prevention; parameterized query validation. | Playwright trace, network query inspection. | `NOT IMPLEMENTED` |
| **FR-04** | Admin | Year-Level & Semester-Level Comparative Aggregates (`MTR-09` to `MTR-11`, `MTR-15`) | `[TEST PLANNED]` `tests/unit/cohort_aggregates_test.php` | `[TEST PLANNED]` `tests/integration/multiyear_aggregate_test.php` | `[TEST PLANNED]` `tests/browser/admin-cohort-comparisons.spec.js` | Prohibit "Total GWA"; exclude non-numeric outcomes (`INC`, `DRP`, `P`) from denominators. | Query plan log, denominator parity check ($N = \text{Passed} + \text{Failed}$). | `NOT IMPLEMENTED` |
| **FR-05** | Admin | Same-Subject Cross-Section & Faculty Comparisons (`MTR-12` to `MTR-14`) | `[TEST PLANNED]` `tests/unit/subject_benchmark_test.php` | `[TEST PLANNED]` `tests/integration/faculty_load_analytics_test.php` | `[TEST PLANNED]` `tests/browser/admin-subject-comparisons.spec.js` | **Mandatory non-evaluative disclaimer present; NO faculty ranking or performance scoring.** | Browser DOM inspection asserting disclaimer presence. | `NOT IMPLEMENTED` |
| **FR-06A** | Admin, Faculty | Support Case Overview (`MTR-17` to `MTR-19`) | `[TEST PLANNED]` `tests/unit/support_lifecycle_test.php` | `[TEST PLANNED]` `tests/integration/intervention_timeline_test.php` | `[TEST PLANNED]` `tests/browser/admin-intervention-dashboard.spec.js` | Receipt-only acknowledgment wording; no fault admission. | Audit log query assertions; count verification against `support_case_referrals`. | `NOT IMPLEMENTED` |
| **FR-06B** | Admin, Faculty | Faculty Responsiveness — Time to First Action (`MTR-20`) | `[TEST PLANNED]` `tests/unit/mttfa_calculation_test.php` | `[TEST PLANNED]` `tests/integration/mttfa_query_test.php` | `[TEST PLANNED]` `tests/browser/mttfa-display.spec.js` | Operational metric only; unacted open cases excluded from denominator (never assigned 0). | Unit test verifying unacted cases are omitted from mean. | `NOT IMPLEMENTED` |
| **FR-06C** | Admin, Faculty | Case Resolution Duration — Time to Closure (`MTR-21`) | `[BLOCKED]` `tests/unit/mttc_calculation_test.php` | `[BLOCKED]` `tests/integration/mttc_query_test.php` | `[BLOCKED]` `tests/browser/mttc-display.spec.js` | Resolution duration; unclosed open cases excluded from denominator (never assigned 0). | Schema verification of dedicated closure timestamp column in baseline. | `BLOCKED` |
| **FR-07** | DevOps, Testing | Synthetic Longitudinal Dataset Generator (Years 1–4) | `[TEST PLANNED]` `tests/unit/synthetic_cohort_contract_test.php` | `[TEST PLANNED]` `tests/integration/longitudinal_seed_test.php` | `[NOT APPLICABLE]` (CLI Script) | **Runs strictly on `udm_radar_scratch`; refuses execution if targeted against `udm_radar`.** | Row count fingerprint before and after seed on scratch. | `NOT IMPLEMENTED` |
| **FR-08** | All | Accessible Chart Visuals & Table Fallbacks | `[NOT APPLICABLE]` | `[NOT APPLICABLE]` | `[TEST PLANNED]` `tests/browser/chart-accessibility-contrast.spec.js` | WCAG 2.1 AA contrast ratio ($\ge 4.5:1$); colorblind-safe palettes; textual table fallbacks. | Lighthouse / Axe accessibility audit output. | `NOT IMPLEMENTED` |

---

## 2. Regression Gate Invariants (Must Never Fail)

Before any post-defense pull request is accepted, the following pre-existing gates must be executed and confirmed passing:
1. `tests/unit/rebuild_target_allowlist_test.php` — Rebuild tool target isolation (11/11 passed).
2. `tests/unit/grade_history_test.php` — Shared academic history helper (16/16 passed).
3. `tests/unit/academic_outcome_contract_test.php` — Prototype grade-point scale contracts.
4. `database/verify/verify_schema.sql` — Schema check asserting exactly 18 base tables and check constraints.
5. PHP syntax linting (`php -l`) — 0 errors across all modified files.
6. `git diff --check` — Clean whitespace and no forbidden carriage return conflicts.
