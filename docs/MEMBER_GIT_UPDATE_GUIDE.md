# Member Git Update Guide — UDM-RADAR

**Purpose:** Safe synchronization with `origin/main` without code loss, merge conflicts, or repository corruption.
**Audience:** All capstone developers and researchers.

---

## 1. Golden Rules of Repository Synchronization

1. **NEVER pull over uncommitted work.** A pull into a dirty working tree risks overwriting code or creating messy conflict markers.
2. **NEVER use `git reset --hard` to resolve update issues.** Hard resets destroy uncommitted work irreversibly.
3. **ALWAYS use `--ff-only` when pulling `main`.** This guarantees that your local `main` only advances if it can cleanly fast-forward, preventing accidental local merge commits.
4. **Git updates files on disk, NOT your database.** Always check if migrations need to be applied after pulling.

---

## 2. Standard Safe Update Flow (Clean Working Tree)

If you have no pending local modifications:

```powershell
# 1. Check working tree state
git status

# 2. Switch to main branch
git switch main

# 3. Fetch latest changes from GitHub
git fetch origin

# 4. Fast-forward pull
git pull --ff-only origin main
```

If the pull succeeds cleanly:
- Check `git log -n 3 --oneline` to see the new commits.
- Review if `composer.json`, `package.json`, or `database/migrations/` changed.

---

## 3. Scenario-Based Troubleshooting

### Scenario A: You Have Uncommitted Local Changes
If `git status` shows modified or untracked files:
```text
On branch main
Changes not staged for commit:
  modified:   student/dashboard.php
Untracked files:
  test_scratch.php
```

**DO NOT run `git pull` or `git checkout -f`.** Follow these steps:

#### Option 1: Preserve Work in a Feature Branch (Recommended if work is in progress)
```powershell
# 1. Create and switch to a dedicated branch for your work
git switch -c feat/my-feature-work

# 2. Stage and commit your changes
git add .
git commit -m "wip: save local progress before updating main"

# 3. Switch back to clean main and pull
git switch main
git fetch origin
git pull --ff-only origin main

# 4. Return to your feature branch and rebase on fresh main
git switch feat/my-feature-work
git rebase main
```

#### Option 2: Temporarily Stash Work (If changes are small adjustments)
```powershell
# 1. Save uncommitted changes to git stash
git stash push -m "WIP before origin/main pull"

# 2. Verify tree is clean
git status

# 3. Pull main
git switch main
git pull --ff-only origin main

# 4. Re-apply your stashed changes
git stash pop
```

---

### Scenario B: You Are on an Old Feature Branch
If you are working on a feature branch (e.g., `feat/my-feature`) and need latest changes from `main`:

```powershell
# 1. Commit all current work on your feature branch
git add .
git commit -m "wip: checkpoint before syncing with main"

# 2. Switch to main and update it
git switch main
git fetch origin
git pull --ff-only origin main

# 3. Switch back to your feature branch
git switch feat/my-feature

# 4. Rebase your feature branch on the updated main
git rebase main
```
*If merge conflicts occur during rebase, resolve them file by file, run `git add <file>`, then `git rebase --continue`.*

---

### Scenario C: The Database Is Older Than the Code
**Symptoms:**
- Fatal PHP PDO errors: `Base table or view not found`
- Unknown column errors (e.g. `Unknown column 'notice_issued_at' in 'field list'`)
- Inconsistent portal counts or broken views

**Solution:**
1. Back up your current database:
   ```powershell
   mysqldump -u root udm_radar > backup_before_mig.sql
   ```
2. Check `database/migrations/` for migration files not yet executed.
3. Apply the missing migrations in ascending numerical sequence:
   ```powershell
   Get-Content database\migrations\<MIGRATION_FILE>.sql | mysql -u root udm_radar
   ```
4. Verify table and column integrity:
   ```powershell
   Get-Content database\verify\verify_schema.sql | mysql -u root udm_radar
   ```

---

### Scenario D: Composer Dependencies Changed
**Symptoms:**
- Fatal error: `Class 'Dompdf\Dompdf' not found` or `PhpOffice\PhpSpreadsheet not found`

**Solution:**
Run Composer in the repository root:
```powershell
composer install --no-dev
```

---

### Scenario E: Node / Playwright Dependencies Changed
**Symptoms:**
- Error: `@playwright/test` package mismatch or missing browser binaries

**Solution:**
```powershell
npm install
```

---

### Scenario F: Python ML Requirements Changed
**Symptoms:**
- Python errors: `ModuleNotFoundError: No module named 'sklearn'` or `ModuleNotFoundError: No module named 'flask'`

**Solution:**
Activate your virtual environment and install dependencies:
```powershell
cd python_ml
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

---

### Scenario G: Python Flask Service Is Unavailable
**Symptoms:**
- Prediction cards show fallback notices.
- Batch prediction generates heuristic fallback.

**Diagnosis:**
- The UDM-RADAR architecture is intentionally non-blocking: when the Python Flask service (`localhost:5000`) is offline, PHP gracefully uses `calculation_fallback` or `heuristic` models.
- If you need to test machine learning Decision Tree predictions specifically:
  1. Open a new terminal.
  2. Navigate to `python_ml/`.
  3. Activate virtual environment.
  4. Ensure `python_ml/.env.local` exists.
  5. Run `python app.py`.
  6. Confirm output displays `* Running on http://127.0.0.1:5000`.
