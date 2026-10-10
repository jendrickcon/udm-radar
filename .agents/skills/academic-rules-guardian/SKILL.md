---
name: academic-rules-guardian
description: Review UDM subject outcomes, GWA, honors, risk boundaries, and analytics denominators when academic calculations or grading consumers change.
---

# Academic Rules Guardian

Read `AGENTS.md`, `docs/ROADMAP.md`, `docs/IMPLEMENTATION_HISTORY.md`, and `CONTRIBUTING.md` before applying this guidance. Read the grading vocabulary and helpers directly from `config/constants.php`; do not duplicate outcome lists or invent policy.

This skill guides behavior; it does not enforce filesystem, shell, network, Git, or database permissions. Enforcement comes from sandbox, approval, host, and network policy. It grants no additional authorization.

## Independent academic contracts

- Canonical numeric final grades 1.00 through 4.00 pass a subject. Numeric 0.00 fails.
- INC is unresolved: neither passed nor failed. Exclude it from pass-rate numerators and denominators and numeric GWA. Do not expire or convert it automatically.
- P is a passing textual outcome where applicable. Read aliases and supported statuses from the shared helpers.
- Subject outcomes and prototype risk are independent. A subject grade of 1.50 can pass while remaining High Risk. The 1.75 boundary is not a subject pass mark.
- Preserve risk thresholds: High below 1.75, Moderate 1.75 through 2.49, Low 2.50 or above. Risk does not determine official standing, pacing, enrollment, subsidy, or allowance eligibility.
- Preserve the GWA formula and current zero exclusion. Whether failed zero grades and their units should contribute remains unresolved institutional policy; do not decide it during an outcome fix.
- DO, DU, DRP, FA, and UD analytics semantics remain deferred. Preserve existing behavior pending separate approved policy changes.
- Honors eligibility and disqualification are separate from subject outcomes. Use the independent honors predicate; preserve existing results and thresholds unless specifically authorized otherwise.
- Preliminary, Midterm, and Pre-Final are raw numeric percentages; Final Grade is a canonical point or textual outcome. Never coerce text into numeric zero.
- Missing eligible GWA is unavailable, not numeric zero or an inferred risk category. Display N/A; retain actual numeric zero as a distinct value wherever the preserved policy supplies it.

## Aggregation and preservation

Pass-rate denominator is `recognized_outcome_count = passed + failed`. Exclude INC, DRP, missing and unrecognized outcomes. Use eligible numeric contributors for numeric means, distinct from encoded-record coverage. Empty denominators produce unavailable results, not division errors or a fabricated zero.

GWA remains unit-weighted using eligible point grades and units through the shared helpers. Exclude textual outcomes from both weighted sums and units. Compare screen and PDF rules and check mixed, all-INC, numeric-zero and no-record cases. Preserve stored grades, GWA caches, prediction history, support cases and model artifacts; an academic helper review does not authorize rescoring or retraining.

## Verification and safety

Select applicable database-free regression checks, such as `final_grade_helpers_test.php`, `gwa_helpers_test.php`, and `academic_outcome_contract_test.php` under `tests/unit/`. Use the package verification workflow to classify the change and select evidence rather than assuming a permanent expected test total.

Before any database-backed check, inspect direct connections, included handlers, subprocesses, database overrides, setup/writes/cleanup, locks and temporary files. Prove every mutation path uses `udm_radar_scratch`. Stop if a path can fall back to `udm_radar`. Transaction rollback is cleanup, not evidence that a test is read-only.

Record actual commands, targets, exit codes and results as Passed, Failed, Skipped, Not Run or Unavailable. Keep unavailable evidence explicit. This diagnostic skill must not automatically stage, commit, push, merge, force-push, alter branches, restore files or suppress failures. Recovery guidance requires exact-diff review and owner authorization before discarding or overwriting work.
