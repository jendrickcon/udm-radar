---
trigger: always_on
description: Protects canonical assets, baseline database schemas, and critical repository layout contracts from unauthorized modification.
---

# Protected Files and Structural Invariants

This rule continuously guards canonical assets, schema baselines, and layout integrity across all work packages.

## 1. Canonical Assets Protection

Do not edit, overwrite, regenerate, or stage modifications to the following files without explicit, documented work-package authorization (e.g., WP-9 canonical dump consolidation or authorized candidate model promotion):

* `database/udm_radar.sql` — Canonical baseline database dump.
* `python_ml/model.pkl` — Active baseline machine learning model artifact.
* `python_ml/model_metrics.json` — Active baseline model metrics manifest.

### Verification Rule:
Before staging or committing changes, inspect diffs against base `main`:
```bash
git diff origin/main -- database/udm_radar.sql python_ml/model.pkl python_ml/model_metrics.json
```
If any diff is detected, verify that the current work package explicitly authorizes the change. Otherwise, revert the modification immediately.

---

## 2. Faculty Dashboard Structural Layout Contract

In `faculty/dashboard.php`, the attention banner (`.warning-banner` / `.dashboard-banner`) must remain structurally isolated from the rest of the page:

* **Structural Boundary:** The conditional warning container must completely close before the following sections:
  1. Dean's Advisory / Notice cards
  2. KPI metric grid (`.stat-grid`)
  3. Subject and Section load cards
  4. Active student roster containers
  5. Top Academic Performers table
* **Never Nest Content:** Dashboard metric cards, section grids, and tables must never be rendered inside the warning banner.
* **Layout Regression Verification:**
  Whenever `faculty/dashboard.php` or shared layout CSS (`assets/css/dashboard.css`) is modified, execute the dedicated layout regression test:
  ```bash
  npx playwright test tests/browser/faculty-dashboard-layout-regression.spec.js
  ```
  Ensure all bounding box and layout containment checks pass before proceeding.

---

## 3. Database Schema Baseline

* **Current Verified Baseline:** Exactly 18 application base tables in the active schema (excluding temporary migration tables matching `_backup_%`).
* **Schema Evolution Rule:** A change in the table count is not automatically corruption, but requires explicit review. Approved migrations may legitimately update this baseline.
* **Database Isolation Rule:** Automated mutation tests, migrations, and rebuild scripts must target `udm_radar_scratch`, never `udm_radar`.

