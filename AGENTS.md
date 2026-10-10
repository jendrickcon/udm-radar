# UDM-RADAR Repository Agent Instructions

These instructions define repository guidance for coding agents working on UDM-RADAR. Read the applicable `AGENTS.md` files and consult repository skills under `.agents/skills/` when relevant. Skill and workflow discovery depends on the host; verify what was actually loaded rather than assuming every agent discovers the same files.

Skills guide agent behavior. They do not enforce filesystem, shell, network, Git, or database permissions. Actual enforcement comes from sandbox, approval, host, and network policy. Guidance does not grant authorization beyond the owner's requested scope.

---

## 1. Prototype Scope and Synthetic Data Boundary

* **System Nature:** Universidad de Manila — Risk Analytics & Decision-support for Academic Records (UDM-RADAR) is a decision-support prototype.
* **Synthetic Records:** Due to Registrar data privacy restrictions, the system operates on synthetic academic records authorized by the Capstone Adviser.
* **Non-Punitive Advisory Role:** Predictive outputs provide non-punitive academic decision support. They do not constitute official registrar evaluations or binding disciplinary actions.
* **Transparent Labeling:** User-facing predictions, reports, and analytical summaries must present the label: `Prototype performance on synthetic academic records`.
* **Academic Period Configuration:** Treat the active academic year and period as dynamic repository data or configuration. Do not permanently hardcode an academic year or semester as an agent invariant unless an approved work package explicitly requires a fixed demonstration scenario.
* **Canonical Roadmap & Project Ledger:** Agents must align all work packages, planned tasks, and status reporting with [docs/ROADMAP.md](docs/ROADMAP.md) and [docs/IMPLEMENTATION_HISTORY.md](docs/IMPLEMENTATION_HISTORY.md). Do not rely on superseded or provisional roadmap files.

---

## 2. Institutional Grading Scale and Academic Boundaries

### 2.1 Final Grade Direction (UDM Student Manual 2025)
* **Scale Range:** $4.00$ to $0.00$ (Higher numbers indicate stronger academic performance).
  * **$4.00$**: Highest / Excellent ($99\text{--}100\%$)
  * **$1.00$**: Minimum passing point grade ($75\%$)
  * **$0.00$**: Numeric failing final grade ($74\%$ and below)
* **Term Weighting:**
  $$\text{Final Percentage} = (\text{Prelim} \times 0.30) + (\text{Midterm} \times 0.30) + (\text{Pre-Final} \times 0.40)$$
  * Preliminary, Midterm, and Pre-Final are strictly raw percentages ($0\text{--}100$).
  * Final Grade is an official discrete point ($4.00\text{--}1.00, 0.00$) or an authorized textual status.
* **Source of Truth for Textual Outcomes:**
  * Read recognized final-grade textual outcomes directly from `config/constants.php`.
  * Do not introduce new textual outcomes without approved academic and database-contract changes.
* **Subject Outcomes:** Canonical numeric grades 1.00 through 4.00 pass; 0.00 fails. INC is unresolved, neither passing nor failed, and excluded from pass-rate numerators/denominators and numeric GWA. P is a passing textual outcome where applicable. Do not introduce automatic INC expiry or conversion.
* **Separate Policy Boundaries:** Preserve the current GWA formula and its exclusion of zero pending institutional confirmation of failed-unit treatment. DO, DU, DRP, FA, and UD analytics semantics remain deferred; retain existing behavior. Honors eligibility uses its independent predicate and must not inherit general subject pass/fail changes.

### 2.2 Prototype Risk Triage vs. Academic Standing
* **Current Prototype Risk-Triage Categories:**
  * **High Risk:** GWA below $1.75$
  * **Moderate Risk:** GWA $1.75$ through $2.49$
  * **Low Risk:** GWA $2.50$ and above
* **Strict Terminology Separation:** Agents must never merge or confuse these five distinct concepts:
  1. **Subject Pass/Fail:** Earning a passing point grade ($\ge 1.00$) or recognized passing status versus failing ($0.00$ or failing status). A point grade below $1.75$ in a passing course is not a failed subject grade.
  2. **Prototype Academic Risk Classification:** Advisory triage category (High, Moderate, Low) based on projected or cumulative averages.
  3. **Official Academic Standing:** Official institutional status determined exclusively by university policy, the College Dean, and the Registrar.
  4. **Program Pacing:** Regular versus Irregular curriculum pacing.
  5. **Enrollment and Benefits:** Retention, enrollment qualification, tuition subsidy, or allowance eligibility.
* **Negative Constraint:** Risk categories do **not** independently determine Warning Status, academic probation, dismissal, program pacing, tuition subsidy, or allowance eligibility.

---

## 3. Database Boundaries and Target Isolation

* **Live Development Database (`udm_radar`):**
  * Automated writes, automated rebuilds, migrations, destructive test scripts, and agent-generated mutations must **never** target `udm_radar`.
  * Read-only inspection of `udm_radar` is permitted when diagnosing issues.
  * Any write operation to `udm_radar` requires explicit project-owner authorization.
* **Automated Scratch Testbed (`udm_radar_scratch`):**
  * Target database for all automated migration executions, mutation tests, and rebuilds.
* **Demonstration Testbed (`udm_radar_demo`):**
  * Target database for approved, controlled synthetic demonstration scenarios.
* **Current Baseline Schema Count:**
  * **18 application base tables** is the current verified baseline (excluding temporary `_backup_%` tables).
  * A changed table count requires investigation, not an automatic assumption of corruption; approved migrations may legitimately update this baseline.

---

## 4. Protected Files and Layout Contracts

### 4.1 Protected Assets
Do not modify these files unless an approved work package explicitly requires and authorizes the change:
* `database/udm_radar.sql` (canonical database dump)
* `python_ml/model.pkl` (active ML model artifact)
* `python_ml/model_metrics.json` (active model metrics manifest)

### 4.2 Faculty Dashboard Structural Contract
* **Attention Banner Containment:** In `faculty/dashboard.php`, the conditional `.warning-banner` (or attention banner) must structurally close before the Advisory notice, KPI grid, Section grid, active roster, and Top Academic Performers sections. Subsequent dashboard cards must never be nested inside the warning banner.
* **Layout Regression Verification:** Any modifications to `faculty/dashboard.php` or shared dashboard layout CSS require running `tests/browser/faculty-dashboard-layout-regression.spec.js`.

---

## 5. Student-Safe Language and Intervention Ethics

* **Supportive Wording:** Use constructive, pressure-safe phrasing.
* **Forbidden Punitive Terms:** Never introduce strike systems, dismissal countdowns, scholarship-loss predictions, or allowance-risk warnings.
* **Receipt Acknowledgment:** When students acknowledge an Academic Support notice, copy must describe receipt only, never "agreement", "fault", or "admission".
* **Intervention Flow:** Automated predictions may generate support cases for faculty review, but must never dispatch unilateral notices to students without faculty review and discretion.

---

## 6. Git Workflow, Testing, and Verification Standards

* **Branching Strategy:**
  * Never work directly on `main`.
  * Never push directly to `main`.
  * Never force-push unless explicitly authorized.
  * Never commit or push without explicit user approval.
* **Testing Terminology & Execution:**
  * Standalone PHP scripts in `tests/unit/` and `tests/integration/` are **PHP regression checks**, not PHPUnit.
  * Report actual passed, failed, and skipped totals discovered from the current execution; do not permanently hardcode expected test counts.
* **GWA Parity & Denominators:**
  * Pass-rate denominator is strictly `recognized_outcome_count = passed + failed`. Exclude dropped (`DRP`), incomplete (`INC`), and missing grades.
  * Unit-weighted GWA calculations must exclude non-numeric outcomes from total units and grade sums.
* **Verification Safety:** Before database-backed tests, inspect direct connections, included handlers, subprocesses, overrides, writes/cleanup, locks, and temporary files. Prove every mutation path targets `udm_radar_scratch`; rollback does not make a test read-only. Use the installed repository-pinned Playwright runner, verify its version, inspect authentication/request side effects, and keep generated artifacts ignored.
* **Evidence:** Inspect committed, unstaged, staged, untracked, and combined changes separately. Report Passed, Failed, Skipped, Not Run, or Unavailable from actual evidence; never precheck acceptance claims or describe source inspection as end-to-end coverage.
* **Diagnostic Boundaries:** Skills and diagnostic workflows must not automatically stage, commit, push, merge, force-push, alter branches, restore files, or suppress failures. A separate delivery action requires explicit owner authorization within the approved scope. Recovery is advisory: preserve local changes, show the exact diff and proposed operation, and obtain owner authorization before any action that could discard or overwrite work.

