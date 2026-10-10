# UDM-RADAR Audit Findings Governance and Post-WP Plan

> [!NOTE]
> **Historical Governance Snapshot:** This document captures post-audit governance planning from earlier sprints.
> For current active statuses, resolved items, and approved upcoming work packages, see:
> - **Canonical Roadmap:** [docs/ROADMAP.md](../ROADMAP.md)
> - **Implementation History:** [docs/IMPLEMENTATION_HISTORY.md](../IMPLEMENTATION_HISTORY.md)

**Purpose:** Preserve the four current UI/UX audit documents as traceable evidence, prevent static findings from being mistaken for browser-verified facts, and map confirmed or provisional findings to the correct future work packages.

**Source reports already stored in the repository:**

- `CURRENT_UI_VALIDATION_REPORT.md`
- `EXTERNAL_IDEA_RECONCILIATION.md`
- `CURRENT_UI_BACKLOG.md`
- `CURRENT_DEMO_PLAYBOOK.md`

## 1. Evidence Warning

The current UI audit was primarily a **static interface walkthrough**, based on PHP view logic, DOM structure, CSS declarations, database inspection, and workflow tracing. It was not a complete live Playwright audit.

Therefore:

- Static findings must be labeled **Static Evidence, Browser Revalidation Required**.
- Technical findings must be directly reproduced before implementation when possible.
- Visual, responsive, keyboard, focus, and interaction claims remain provisional until a real browser audit is performed.
- The four source reports remain valuable and should not be deleted, but they are evidence drafts rather than automatic implementation instructions.

## 2. Governance Classification

### Current technical defect candidates

These require current-branch reproduction before implementation:

- `BUG-CUR-01`: Admin batch prediction may omit the required CSRF token.
- Hardcoded Flask API key or credential-like configuration.
- Two interpolated SQL queries identified by static inspection.
- Dark-mode warning-banner contrast.

### Maintenance tasks, not ordinary code defects

- `MAINT-LIVE-COURSE`: The verified course migration exists, but the live development database has not been migrated. Any live application requires backup, authorization, rehearsal, controlled execution, rollback evidence, and verification.
- `MAINT-LIVE-GWA`: Cached live `current_gwa` values may remain stale. Any backfill requires a separately approved maintenance window and audit evidence.
- `MAINT-LIVE-SCHEMA`: Migrations verified on scratch must not be applied to the live development database automatically.

### Demonstration preparation, not product defects

- `DEMO-SUPPORT-SCENARIO`: Prepare a safe synthetic or disposable scenario containing one parent case, at least two subject referrals, one Faculty-issued notice, one Student acknowledgment, and one resolved referral.
- Do not alter a real-looking case merely to create favorable evidence.

### UX findings requiring later browser verification

- Clarify historical cumulative GWA versus projected semester GWA.
- Add contextual action from Student subject triage to Grade Concern, when appropriate.
- Clarify Grade Batch terminology and prototype scope.
- Add prediction data-coverage and freshness information after contracts are finalized.
- Evaluate a compact Faculty Student Review summary before building a large unified drawer.
- Keep Dashboard and Performance Trends separate unless browser evidence proves harmful duplication.
- Evaluate glossary, onboarding, status legends, aging indicators, and action-first hierarchy.

## 3. Work-Package Mapping

## WP-5: Prediction Source and Feature Contract Harmonization

### Confirmed scope

- Resolve `current_prelim_point_avg` versus `current_prelim_avg` contract drift.
- Standardize stored sources: `decision_tree`, `heuristic`, and `calculation_fallback`.
- Map Flask `fallback_blend` to `calculation_fallback` at the service boundary rather than storing it.
- Preserve historical `heuristic` provenance.
- Prevent missing required prediction features from silently becoming valid `0.0` values at the contract boundary.
- Establish canonical source families and user-facing labels.
- Document that the present `model_metrics.json` is mismatched with the active `model.pkl` and must not be presented as active-model evidence.

### Requires verification during WP-5

- Reproduce and reconcile the dashboard 50/50 versus Python 70/30 fallback formulas. This is a prediction-contract issue, not a visual-design issue.
- Audit every remaining source string and feature name before declaring the contract harmonized.
- Determine whether training CSV and metrics metadata can be renamed safely without implying retraining. Otherwise defer regeneration to WP-7.

### UI dependency

Do not finalize prediction-card copy until WP-5 establishes the source contract. Preferred Student-facing primary title:

> Projected Semester GWA

Supporting copy should explain that the value is an estimate based on available data and is not an official academic result. Technical source details should be secondary.

## WP-7: Prediction Workflow, Remediation, and Demonstration Readiness

### Confirmed or previously registered scope

- Identify predictions created during the Preliminary-feature mismatch period.
- Recalculate eligible affected synthetic Students only after the feature and missing-data contracts are finalized.
- Review potentially stale Academic Support parent cases and subject referrals.
- Preserve historical evidence; do not silently delete predictions or intervention records.
- Reconcile support cases using an auditable rule.
- Complete missing-data policy, model governance, retraining, candidate evaluation, promotion, and artifact pairing.
- Review prediction explanations for Students, Faculty, and Admin.
- Move protected service secrets, including the Flask API key if confirmed, out of hardcoded source configuration.

### Must be verified before acceptance as a current bug

- Reproduce the Admin batch-prediction CSRF failure with a safe browser or HTTP test. If confirmed, fix it in WP-7 or a focused pre-evaluation bug-fix commit.
- Verify the complete support workflow from parent case to referral, Faculty action, Student notice, acknowledgment, and closure.
- Verify whether duplicate or inconsistent fallback calculations remain after WP-5.

### Demonstration preparation

Track the complete support example under `DEMO-SUPPORT-SCENARIO`, not as a product defect. Prefer a disposable demonstration database or approved synthetic scenario.

## WP-8: Strict SQL Mode, Security, Authorization, and Deployment Readiness

### Still relevant

- Verify CSRF protection for all Admin and Faculty write actions.
- Review hardcoded credentials, API keys, and protected configuration.
- Review statically identified interpolated SQL queries even if values are cast.
- Verify invalid values fail consistently under strict SQL mode.
- Test Student, Faculty, and Admin authorization boundaries using non-destructive methods.
- Confirm deactivated accounts cannot continue operating.
- Confirm Archived Students are excluded from operational workflows.
- Review accessibility findings that affect safe operation, including focus, keyboard access, dialog behavior, and warning contrast.

### Evidence requirement

The previous UI audit did not execute a full Playwright run. Security and accessibility conclusions require direct testing before closure.

## WP-9: Canonical Database, Documentation, and Manuscript Synchronization

### Still relevant

- Build the final canonical database from the committed baseline plus all approved migrations.
- Remove every temporary `_backup_%` table before export.
- Verify exactly 18 application base tables remain before canonical dump generation.
- Ensure the canonical dump contains the Final Grade storage constraint, course repair, prediction-source migration, and all later approved schema changes.
- Distinguish the prototype database, live development database, and proposed university deployment architecture.
- Describe Grade Batch as a prototype simulation or configurable workflow dependent on institutional integration, not an established UDM production process.
- Update ERD, Data Dictionary, SETUP, architecture ledger, audit documentation, and manuscript consistently.
- Avoid official UDM curriculum claims without authorized verification.
- Preserve the synthetic-data limitation and avoid presenting synthetic model metrics as proof of real institutional validity.

## Separate UX Evaluation-Readiness Phase

After WP-5 and the necessary WP-7 decisions, create a dedicated UI branch.

Candidate changes for verification and possible implementation:

1. Rename Faculty `Encode Grades` to `Term Grade Submission` or another approved term-monitoring label.
2. Add scope copy stating that Preliminary, Midterm, and Pre-Final percentages are submitted for departmental monitoring and do not update official university records.
3. Rename the prominent Student prediction card to `Projected Semester GWA`.
4. Rename or clarify `Cumulative GWA` as historical cumulative GWA based on completed semesters.
5. Add a direct `Ask About This Grade` action from subject triage, with subject and grading period preselected.
6. Add projection coverage and freshness after WP-5 and WP-7 define reliable data.
7. Add a compact Faculty Student Review summary connecting grade, concern, referral, previous action, acknowledgment, and next step.
8. Add a glossary or short first-login guide.
9. Add status legends and useful empty states where current browser testing confirms confusion.
10. Evaluate aging indicators only after institutional response-time policy exists.

### Explicit non-decisions

- Do not merge Dashboard and Performance Trends merely because an external concept suggested it. Keep them separate unless real browser evidence shows harmful duplication.
- Do not add a header notification bell when sidebar counters already provide adequate discoverability.
- Do not implement SMS or email integrations during the current prototype phase.
- Do not expose technical Decision Tree internals to Students.

## 4. Post-WP Process

After WP-5 and the necessary WP-7 work:

1. Rebuild or prepare a disposable demonstration database.
2. Prepare complete Student, Faculty, and Admin scenarios without altering real-looking records for favorable results.
3. Create `feat/ui-evaluation-readiness` from the latest approved code branch.
4. Implement only the approved low-risk UI, copy, navigation, onboarding, and accessibility improvements.
5. Run a real Playwright audit at desktop, laptop/tablet, and mobile viewports.
6. Test keyboard navigation, focus order, dialog trapping, contrast, network failures, and console errors.
7. Reconcile the four original audit reports against direct browser evidence.
8. Update the current UI backlog, marking static findings as confirmed, rejected, or changed.
9. Run a capstone demonstration rehearsal using the approved playbook.
10. Continue WP-8 and WP-9 after the UI and demonstration behavior are stable.

## 5. Branch Plan

- `audit/current-ui-reconciliation`: Preserve and sanitize audit reports and this governance file.
- `feat/wp5-prediction-contract`: Implement WP-5 only.
- `feat/wp7-prediction-remediation`: Implement WP-7 only.
- `feat/ui-evaluation-readiness`: Implement approved UI and accessibility improvements after prediction behavior is stable.
- WP-8 and WP-9 should remain isolated in their own focused branches or work-package commits.

## 6. Current Priority Order

1. Preserve and sanitize the four audit reports.
2. Use this file as the governance index and mapping document.
3. Complete WP-5.
4. Complete required WP-7 remediation and demonstration preparation.
5. Implement the approved UI evaluation-readiness backlog.
6. Run a real Playwright audit.
7. Complete WP-8 security and strict-mode validation.
8. Complete WP-9 canonicalization and documentation synchronization.

## 7. Status Summary

- WP-0: Approved
- WP-1: Approved
- WP-2: Approved
- WP-3: Approved
- WP-4: Approved
- WP-6: Approved
- WP-5: Next implementation package
- WP-7: Paused until WP-5 review
- WP-8: Paused
- WP-9: Paused
- UI evaluation-readiness phase: Planned after WP-5 and required WP-7 decisions
- Cohort generator: Blocked
