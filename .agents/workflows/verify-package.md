# Package Verification Workflow (`/verify-package`)

This workflow coordinates multi-tier test execution and diagnostic reporting for work packages before submission.

---

## Workflow Sequence

### Step 1: Detect Changed Files
Inspect current branch changes against base `origin/main`:
```bash
git diff --name-only origin/main
```
Identify whether changes affect:
* PHP backend files
* UI templates / CSS stylesheets
* Database migrations / grade helpers
* Test files

---

### Step 2: PHP Syntax Linting
Run syntax checks **only on modified PHP files**:
```powershell
git diff --name-only origin/main | Select-String "\.php$" | ForEach-Object { php -l $_.Line }
```
*Halt immediately if any syntax error is reported.*

---

### Step 3: Applicable PHP Regression Checks
Execute discovered standalone unit and integration suites:
```powershell
Get-ChildItem tests/unit/*_test.php | ForEach-Object { php $_.FullName }
Get-ChildItem tests/integration/*_test.php | ForEach-Object { php $_.FullName }
```
Record exact assertion counts and verify all exit codes are `0`.

---

### Step 4: Faculty Dashboard Layout Regression (Conditional)
If `faculty/dashboard.php`, `includes/sidebar.php`, or `assets/css/dashboard.css` were modified, execute the layout regression suite:
```powershell
npx playwright test tests/browser/faculty-dashboard-layout-regression.spec.js
```
*Verify that metric cards and tables remain outside the `.warning-banner` container.*

---

### Step 5: Applicable Browser Feature Spec & Full Suite
1. Run the specific browser test matching the changed feature:
   ```powershell
   npx playwright test tests/browser/<feature-spec>.spec.js
   ```
2. Run the full browser suite:
   ```powershell
   npm run test:browser
   ```
Record passed, failed, and credential-dependent skipped totals.

---

### Step 6: Conditional GWA Parity Audit
Determine if GWA parity verification is relevant to the changes (e.g., edits to `config/constants.php`, grade storage, or analytics):
* **Relevance Check:** Only run if grading formulas, helpers, or database grade records were modified.
* **Target Check:** Verify an approved scratch database is available and target is explicit (`--db=udm_radar_scratch`).
* **Execution:**
  ```powershell
  php tools/audit_gwa_parity.php --db=udm_radar_scratch
  ```
* **Guard:** Never silently target or run against the live development database `udm_radar`.

---

### Step 7: Summary Report
Compile all execution results into a structured summary table showing:
* Changed files count
* PHP syntax lint status
* PHP regression checks (passed / failed totals)
* Playwright browser specs (passed / failed / skipped totals)
* Layout regression status
* GWA parity status

---

## Strict Operating Rules

* **Stop on Failure:** Immediately stop on any non-zero exit code. Report the exact error output.
* **Never Mutate Code to Pass:** Never edit application code or test assertions solely to achieve green checks.
* **Never Stage, Commit, or Push:** This workflow is diagnostic and report-only. Never execute `git add`, `git commit`, `git push`, or `git checkout`.

