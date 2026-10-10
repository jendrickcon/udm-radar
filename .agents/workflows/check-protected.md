# Protected Invariants Check Workflow (`/check-protected`)

This workflow audits the working tree and branch diffs against canonical assets, structural layout contracts, baseline table schemas, and secret containment rules.

---

## Workflow Sequence

### Step 1: Canonical Asset Diff Audit
Inspect git diffs for protected repository assets against `origin/main`:
```bash
git diff origin/main -- database/udm_radar.sql python_ml/model.pkl python_ml/model_metrics.json
```
* **Authorization Check:**
  * If changes exist, verify whether the active work package explicitly authorizes modifying canonical assets (e.g., WP-9 canonicalization or formal model promotion).
  * If unapproved, flag as an invariant violation and display rollback command:
    ```bash
    git checkout origin/main -- database/udm_radar.sql python_ml/model.pkl python_ml/model_metrics.json
    ```

---

### Step 2: Faculty Dashboard Structural Layout Inspection
Inspect `faculty/dashboard.php` to verify the attention banner contract:
* Confirm the opening `<?php if ($facultyActionCount > 0): ?>` / `<div class="card warning-banner">` or `.dashboard-banner` has its corresponding closing `</div>` and `<?php endif; ?>` **before** the Advisory cards, KPI stat grid (`.stat-grid`), and student rosters.
* If modified, ensure `faculty-dashboard-layout-regression.spec.js` is scheduled for execution.

---

### Step 3: Database Schema Baseline Audit
If database migrations or schema files were modified:
* Inspect `database/migrations/` and verify that the target table count matches the current verified baseline of **18 application base tables** (excluding temporary `_backup_%` tables).
* If the table count differs, confirm that an approved migration explicitly explains the structural schema change.

---

### Step 4: Untracked Files and Secret Containment Audit
Inspect untracked and staged files for accidental credential or artifact exposure:
```bash
git status --short
```
Verify that no forbidden patterns are present:
* Private environment files (`.env`, `.env.playwright.local`)
* Test run reports (`playwright-report/`, `test-results/`)
* Temporary database dumps or `.sql` backups outside authorized migration folders
* Secret keys, certificates, or tokens (`.pem`, `.key`, `id_rsa`)

---

### Step 5: Decision & Report
Output an invariant health table:
1. **Canonical Assets:** Unmodified / Authorized / VIOLATION
2. **Faculty Banner Layout:** Structurally Compliant / Needs Check
3. **Database Schema Baseline:** Verified 18 Base Tables / Migration Authorized
4. **Credential Containment:** Clean / Violations Detected

---

## Strict Operating Rules

* **Never Modify Model Files to Test:** Do not edit or touch `model_metrics.json` or `model.pkl` to test this workflow. Test decision logic using existing git history or non-tracked test fixtures.
* **Never Suppress Warnings:** Always report any discrepancy to the user.
* **Never Stage, Commit, or Push:** This workflow is purely analytical and read-only.

