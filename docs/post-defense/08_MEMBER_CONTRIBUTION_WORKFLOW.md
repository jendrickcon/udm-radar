# Member Contribution and Git Workflow Guide — UDM-RADAR

**Document Version:** 1.0.0 (Post-Defense Operational Standard)
**Applicability:** All student proponents, faculty researchers, and contributing software engineers.

---

## 1. Core Principles of Repository Contribution

1. **Never edit directly on `main`.** The `main` branch represents accepted, production-ready, verified code.
2. **Never pull over uncommitted work.** Pulling into a dirty working tree risks accidental code loss and merge conflicts.
3. **Always use fast-forward pulls on `main`:**
   ```powershell
   git pull --ff-only origin main
   ```
4. **Never use `git reset --hard` as your routine troubleshooting step.** Hard resets destroy uncommitted work irreversibly.
5. **Git updates files on disk, NOT MariaDB.** Database schema updates require executing approved migrations.
6. **Never run automated tests or write scripts against `udm_radar`.** Always target `udm_radar_scratch`.

---

## 2. Setting Up Your Work Environment

### Option A: Standard Feature Branch (Single Task)
```powershell
# 1. Switch to clean main and pull latest changes
git switch main
git fetch origin
git pull --ff-only origin main

# 2. Create and switch to a descriptive feature branch
git switch -c feat/analytics-prediction-range
```

### Option B: Isolated Git Worktree (Complex Multi-File / Parallel Tasks)
Git worktrees allow you to work on multiple branches simultaneously in separate directories without touching your primary checkout:
```powershell
# Create a registered worktree from origin/main
git worktree add -b feat/analytics-cohort-filters C:\xampp\htdocs\udm-radar-cohort-filters origin/main
cd C:\xampp\htdocs\udm-radar-cohort-filters
```

---

## 3. Preserving Local Changes Before Updating

If `git status` shows modified files on `main`, **DO NOT PULL**.

### Path 1: Save Progress in a Dedicated Branch (Recommended)
```powershell
# 1. Create a branch and commit your work
git switch -c feat/my-wip-feature
git add .
git commit -m "wip: save local progress before updating main"

# 2. Return to clean main and update
git switch main
git fetch origin
git pull --ff-only origin main

# 3. Rebase your feature branch on fresh main
git switch feat/my-wip-feature
git rebase main
```

### Path 2: Temporary Stash (Small Local Tweaks)
```powershell
git stash push -m "WIP before origin/main pull"
git switch main
git pull --ff-only origin main
git stash pop
```

---

## 4. Running Focused Regression Tests

Before committing any changes, run the appropriate focused tests:

```powershell
# 1. PHP Syntax Linting
php -l path/to/modified_file.php

# 2. Unit Tests
php tests/unit/rebuild_target_allowlist_test.php
php tests/unit/grade_history_test.php

# 3. Schema Verification
mysql -u root udm_radar_scratch < database/verify/verify_schema.sql

# 4. Whitespace & Line Ending Hygiene Check
git diff --check
```

---

## 5. Staging, Committing, and Opening a Pull Request

### Step 1: Explicit Staging
**Never use `git add .` or `git add -A`.** Stage only the files intended for your work package:
```powershell
git add path/to/file1.php path/to/file2.php docs/post-defense/10_IMPLEMENTATION_STATUS.md
```

### Step 2: Verify Staged Boundary
```powershell
git diff --cached --name-status
git diff --cached --check
```
Ensure `git diff --cached --check` exits with code `0`.

### Step 3: Conventional Commit Message
Follow conventional commit formatting:
- `feat(analytics): add continuous prediction range display`
- `fix(grades): correct term delta calculation for irregular students`
- `docs(post-defense): update implementation status ledger`

```powershell
git commit -m "feat(analytics): add continuous prediction range display"
```

### Step 4: Push and Open PR
```powershell
git push -u origin feat/analytics-prediction-range
```
Open a pull request on GitHub targeting `main`. Provide:
- Summary of changes
- Addressed panel feedback item (e.g. `P-2`, `FR-01`)
- Test evidence logs
- Confirmation that no genuine records or database mutations occurred.

---

## 6. Updating Implementation Status & Continuation Handoff

The post-defense engineering roadmap is structured into **seven roadmap phases, consisting of Phase 0 through Phase 6**.

> [!IMPORTANT]
> **SINGLE-WRITER GOVERNANCE RULE FOR STATUS TRACKING:**
> While an active implementation branch is in progress, **Sol owns updates to `docs/post-defense/10_IMPLEMENTATION_STATUS.md`**. Other agents and contributing members may propose status transitions in handoff reports and PR summaries, but must **not** edit `10_IMPLEMENTATION_STATUS.md` directly unless ownership is explicitly transferred or the active implementation branch is merged into `main`. This prevents merge collisions and conflicting progress ledgers.

Whenever completing or transferring work:
1. Provide proposed status updates in your PR description using the canonical status taxonomy (`NOT STARTED`, `IN PROGRESS`, `BLOCKED`, `MVP COMPLETE`, `VERIFIED`, `MERGED`, `DEFERRED`).
2. Fill out the `AGENT_HANDOFF_TEMPLATE.md` in your PR description or handover documentation.
3. If using a worktree, remove it cleanly after PR merge:
   ```powershell
   git worktree remove C:\xampp\htdocs\udm-radar-cohort-filters
   ```
