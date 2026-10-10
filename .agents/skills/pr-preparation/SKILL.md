---
name: pr-preparation
description: Prepares clean, reviewable pull request documentation adhering to CONTRIBUTING.md standards, validating diffs and test results.
---

# Pull Request Preparation Skill

This skill formats standardized, evidence-backed Pull Request descriptions and conducts pre-submission Git audits.

## When to Use
Use this skill whenever:
* Completing a feature branch, hotfix, or refactoring package.
* Preparing PR titles, descriptions, and verification summaries for project-owner review.
* Auditing git status and staged diffs before opening a pull request.

---

## 1. Pre-Submission Audit Sequence

Before formatting a PR proposal, execute these non-mutating checks:

1. **Verify Branch Base:** Confirm the branch was cut from and targets the approved base branch:
   ```bash
   git status --short --branch
   ```
2. **Inspect Changed Files:**
   ```bash
   git diff --name-status origin/main
   git diff --stat origin/main
   ```
3. **Verify Protected Invariants:** Ensure canonical assets (`database/udm_radar.sql`, `python_ml/model.pkl`, `python_ml/model_metrics.json`) are untouched unless authorized.
4. **Run PHP Syntax Checks:** For all changed PHP files:
   ```powershell
   php -l path/to/changed-file.php
   ```
5. **Collate Current Test Evidence:** Run applicable tests and capture exact numbers (do not use hardcoded or assumed totals).

---

## 2. Standard PR Description Schema

Format the PR description using this standard template (derived from `CONTRIBUTING.md`):

```markdown
## Summary of Changes
- [Concise bulleted list of what changed]

## Motivation & Rationale
- [Why this change was necessary; problem statement and design decisions]

## Affected Portals & Components
- **Portals:** Admin / Faculty / Student
- **Files:** [Key modified files]
- **Database Migrations:** None / [Migration number and description]

## Verification & Test Results
- **PHP Regression Checks:** [Passed count] passed, [Failed count] failed across [File count] suites.
- **Playwright Browser Tests:** [Passed count] passed, [Failed count] failed, [Skipped count] skipped across [Spec count] specs.
- **Layout Invariants:** Faculty Dashboard layout regression verified ([Passed / N/A]).
- **GWA Parity Audit:** [100% / Not applicable to this change].

## Safety & Invariant Confirmation
- [x] Canonical assets (udm_radar.sql, model.pkl, model_metrics.json) remain untouched / authorized.
- [x] Automated tests and migrations targeted scratch/demo, leaving udm_radar untouched.
- [x] Zero credentials, secrets, or temporary logs staged.
- [x] Working tree is clean.

## Known Limitations & Follow-Up Work
- [Any deferred tasks, pending mobile recovery, or next roadmap phase]
```

---

## 3. Strict Operating Boundaries

* **Never Stage or Commit Automatically:** Present the drafted PR title and description for user authorization.
* **Never Push or Merge:** The agent must not execute `git push` or `git merge` unless explicitly directed.
* **Never Exaggerate Test Results:** Accurately differentiate between live application tests and static source-inspection checks. Report skipped tests transparently.

