# Contributing to UDM-RADAR

This guide defines the Git workflow, code-quality expectations, data rules, and review checklist for UDM-RADAR.

## Core Rule

**Do not push directly to `main`.**

All work must be completed on a branch and merged through a pull request reviewed by the repository maintainer.

## Before Starting Work

```bash
git checkout main
git pull origin main
```

Create a focused branch:

```bash
git checkout -b yourname/short-task-description
```

Examples:

```text
jendrick/intervention-audit-fix
joshua/faculty-trend-scale
vincent/model-governance-ui
```

Use lowercase names and hyphens. Keep one logical concern per branch whenever practical.

## Commit Practices

Make small, meaningful commits:

```bash
git add admin/activity.php admin/export_intervention_audit.php
git commit -m "Fix intervention audit lifecycle timestamps"
```

Good messages describe the outcome:

```text
Validate Admin-approved term percentages
Preserve textual Final Grade statuses
Add Faculty ownership check to roster endpoint
Align Program Snapshot CSV with PDF metrics
```

Avoid vague messages such as:

```text
more fixes
updates
final version
```

## Required Data Rules

Every contribution must preserve these conventions.

### Term grades

```text
prelim, midterm, prefinal = raw 0-100 percentages
```

Do not store point grades or textual outcomes in term-score fields.

### Final Grade

```text
final_grade = official discrete point grade or supported textual outcome
```

Do not cast `INC`, `DO`, `DU`, or another supported textual status to numeric zero.

### Missing data

Missing records must remain `NULL`, `N/A`, or No Data. Do not default missing academic data to:

```text
0.00
LOW
```

### Latest prediction

Always select the latest prediction deterministically:

```sql
ORDER BY generated_at DESC, id DESC
LIMIT 1
```

### Scale-aware calculations

- Average Preliminary, Midterm, and Pre-Final using raw percentages.
- Average official completed outcomes using valid point grades.
- Do not combine percentage values and point-grade values in one unlabeled mean.
- Use shared grading helpers rather than duplicating conversion rules.

## Security Requirements

Every state-changing handler must include:

- role authorization;
- CSRF validation;
- server-side allowlists;
- record ownership or scope checking;
- prepared statements;
- safe error handling;
- a transaction for multi-table changes;
- history or audit logging when the action changes academic or governance data.

Never trust:

- hidden record IDs;
- disabled fields;
- client-side validation;
- URL parameters;
- JavaScript-only restrictions.

AJAX and JSON endpoints require the same authorization checks as full-page POST handlers.

## Grade Workflow Requirements

Faculty term-grade submissions must:

1. accept only `0-100` percentages;
2. be limited to assigned subject-section loads;
3. enter `pending_grade_batches`;
4. remain unofficial until Admin approval;
5. prevent duplicate pending batches for the same scope;
6. preserve reasons for modifications.

Admin approval handlers must revalidate every approved value. Never assume a pending payload is safe merely because it was validated earlier.

## Prediction and Intervention Requirements

Automated predictions may create a support case, but must not automatically send a Student notice.

The required flow is:

```text
Prediction
-> Support case
-> Faculty review
-> Notice, when warranted
-> Student acknowledgment
-> Admin oversight
```

Acknowledgment means receipt only. User-facing wording must not describe acknowledgment as agreement or admission.

## Export Requirements

CSV and PDF exports must:

- enforce role authorization;
- respect active filters;
- export the full matching dataset rather than the visible page only;
- use deterministic prediction queries;
- preserve percentage and point-scale labels;
- neutralize spreadsheet-formula injection in CSV text fields;
- avoid unsupported causal claims;
- include scope, generation time, and requesting user;
- log successful exports;
- avoid exposing unnecessary personal or internal notes.

## Model Governance Requirements

Candidate models must not overwrite the active model during training.

Expected lifecycle:

```text
Dataset upload
-> Schema validation
-> Candidate training
-> Regression evaluation
-> Derived risk-class evaluation
-> Admin comparison
-> Controlled promotion
-> Rollback availability
```

Use versioned artifacts rather than relying on one untracked `model.pkl`.

Do not commit:

- real graduate datasets with unnecessary identifiers;
- production model-training exports;
- private student reports;
- local virtual environments;
- credentials or API secrets;
- temporary model artifacts unless explicitly approved.

## Local Validation Before a Pull Request

### PHP syntax

Run against all changed PHP files:

```bash
php -l path/to/changed-file.php
```

### Manual role checks

Verify the affected workflow as the relevant role:

- Student
- Faculty
- Admin

### Security checks

Test:

- missing CSRF token;
- invalid CSRF token;
- tampered record ID;
- wrong-role access;
- out-of-scope Faculty subject or section;
- duplicate form submission.

### Academic checks

Test representative values:

```text
0, 74, 75, 76, 82, 86, 99, 100
```

Also test:

```text
NULL, INC, DO, DU
```

where supported.

### Export checks

Confirm:

- downloaded row count matches the filtered record count;
- pagination does not limit the export;
- metadata reflects active filters;
- no broken glyphs or clipped content appears in PDFs;
- CSV files open safely in Excel-compatible applications.

## Opening a Pull Request

Push the branch:

```bash
git push -u origin yourname/short-task-description
```

Then open a pull request on GitHub or use GitHub CLI:

```bash
gh pr create
```

The pull request should include:

- what changed;
- why the change was necessary;
- affected portals and files;
- database migration requirements;
- screenshots for interface changes;
- test cases performed;
- known limitations or follow-up work.

## Responding to Review

Make requested changes on the same branch:

```bash
git add .
git commit -m "Address review feedback"
git push
```

The existing pull request updates automatically.

## After Merge

```bash
git checkout main
git pull origin main
git branch -d yourname/short-task-description
```

Delete the remote branch through GitHub when it is no longer needed.

## Merge Conflicts

Update the branch before resolving conflicts:

```bash
git checkout yourname/short-task-description
git fetch origin
git merge origin/main
```

Resolve marked files manually, then:

```bash
git add path/to/resolved-file.php
git commit -m "Resolve merge conflict"
git push
```

Do not blindly choose one side when the conflict involves grading rules, authorization, migrations, or audit logic. Review the full workflow before committing the resolution.

## Pull Request Checklist

- [ ] Branch is up to date with `main`
- [ ] PHP syntax checks pass
- [ ] No credentials or sensitive exports are committed
- [ ] CSRF and role checks are present
- [ ] Ownership constraints are enforced server-side
- [ ] Term percentages remain `0-100`
- [ ] Final Grades remain official point values or supported statuses
- [ ] Missing data does not become `0.00` or Low Risk
- [ ] Latest prediction ordering is deterministic
- [ ] Multi-table changes use a transaction
- [ ] Audit or history records are written where required
- [ ] Light and dark themes were checked for UI changes
- [ ] Responsive layouts were checked
- [ ] CSV/PDF outputs were verified when affected
- [ ] Pull-request description documents tests and limitations
