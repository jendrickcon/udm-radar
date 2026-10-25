# Post-Defense Test and Acceptance Matrix — UDM-RADAR

**Document Version:** 1.0.0 (Post-Defense Planning)
**Accepted Baseline SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Status Taxonomy:** `NOT STARTED` | `PLANNED` | `IN PROGRESS` | `VERIFIED` | `ACCEPTED`
**Rule:** Tests must NOT be marked `VERIFIED` or `ACCEPTED` without recorded, reproducible test execution logs.

---

## 1. Feature Verification & Acceptance Matrix

| Req ID | Target Role | Analytical Metric / Workflow | Planned Unit Test | Planned Integration Test | Planned Browser Spec | Privacy / Boundary Check | Required Verification Evidence | MVP Acceptance Criteria | Current Status |
|---|---|---|---|---|---|---|---|---|:---:|
| **FR-01** | Student, Admin | Continuous Estimated GWA & Prediction Range | `tests/unit/prediction_range_test.php` | `tests/integration/prediction_provenance_test.php` | `tests/browser/prediction-range-display.spec.js` | Zero discrete-risk claim on continuous output. | Terminal test log, bounded value assertion $[1.00, 4.00]$. | Displays Estimated GWA $\pm \text{MAE}$ with non-guarantee subline. | `PLANNED` |
| **FR-02** | Student | Point Delta ($\Delta\text{GWA}$) & Longitudinal Trend | `tests/unit/gwa_progression_test.php` | `tests/integration/student_history_query_test.php` | `tests/browser/student-progression-trend.spec.js` | Directional point delta (higher is better). | Assertion log for regular, irregular, and summer terms. | Point delta renders with upward/downward indicator and trend line. | `PLANNED` |
| **FR-03** | Admin | Multi-Dimensional Analytics Filter Bar | `tests/unit/analytics_filter_sanitize_test.php` | `tests/integration/admin_analytics_query_test.php` | `tests/browser/admin-analytics-filters.spec.js` | Strict SQL injection prevention; parameterization. | Playwright trace, network query inspection. | Selecting filters dynamically updates charts and summaries. | `PLANNED` |
| **FR-04** | Admin | Year-Level & Semester-Level Comparative Aggregates | `tests/unit/cohort_aggregates_test.php` | `tests/integration/multiyear_aggregate_test.php` | `tests/browser/admin-cohort-comparisons.spec.js` | Prohibit "Total GWA"; exclude non-numeric outcomes. | Query plan log, denominator parity check ($N = \text{Passed} + \text{Failed}$). | Year-level and semester average bar charts render accurately. | `PLANNED` |
| **FR-05** | Admin | Same-Subject Cross-Section & Faculty Comparisons | `tests/unit/subject_benchmark_test.php` | `tests/integration/faculty_load_analytics_test.php` | `tests/browser/admin-subject-comparisons.spec.js` | **Mandatory non-evaluative disclaimer present; NO faculty ranking.** | Browser DOM inspection asserting disclaimer presence. | Subject comparison table displays class avg and benchmark delta. | `PLANNED` |
| **FR-06** | Admin, Faculty | Intervention History & Responsiveness Logs | `tests/unit/support_lifecycle_test.php` | `tests/integration/intervention_timeline_test.php` | `tests/browser/admin-intervention-dashboard.spec.js` | Receipt-only acknowledgment wording; no fault admission. | Audit log query assertions; business-day difference check. | Dashboard displays notice issuance and acknowledgment latency. | `PLANNED` |
| **FR-07** | DevOps, Testing | Synthetic Longitudinal Dataset (Years 1-4) | `tests/unit/synthetic_cohort_contract_test.php` | `tests/integration/longitudinal_seed_test.php` | N/A (CLI Tool) | **Runs strictly on `udm_radar_scratch`; refuses `udm_radar`.** | Row count fingerprint before and after seed on scratch. | Generates 10 controlled scenarios across 4 years and 3 school years. | `PLANNED` |
| **FR-08** | All | Accessible Chart Visuals & Table Fallbacks | N/A | N/A | `tests/browser/chart-accessibility-contrast.spec.js` | WCAG 2.1 AA contrast ratio ($\ge 4.5:1$); colorblind-safe palettes. | Lighthouse / Axe accessibility audit output. | Confusing radar charts removed; line/bar charts accessible. | `PLANNED` |

---

## 2. Regression Gate Invariants (Must Never Fail)

Before any post-defense pull request is accepted, the following pre-existing gates must be executed and confirmed passing:
1. `tests/unit/rebuild_target_allowlist_test.php` — Rebuild tool target isolation (11/11 passed).
2. `tests/unit/grade_history_test.php` — Shared academic history helper (16/16 passed).
3. `tests/unit/academic_outcome_contract_test.php` — UDM discrete point grading scale contracts.
4. `database/verify/verify_schema.sql` — Schema check asserting exactly 18 base tables and check constraints.
5. PHP syntax linting (`php -l`) — 0 errors across all modified files.
6. `git diff --check` — Clean whitespace and no forbidden carriage return conflicts.
