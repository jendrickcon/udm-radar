# UDM-RADAR UI/UX Findings Register

This register converts qualitative audits, static inspection findings, and usability observations into discrete, trackable engineering issues.

> [!NOTE]
> **Governance Notice:**
> The canonical project planning document remains [docs/ROADMAP.md](ROADMAP.md).
> This register tracks usability findings and recommends candidate packages, but does not independently authorize roadmap scope or resequence releases without Capstone Adviser and Owner approval.

---

## 1. Register Status Definitions

* **Observed:** Identified in static inspection or preliminary review; awaiting formal technical verification.
* **Verified:** Confirmed via code path analysis, deterministic fixture, or browser test evidence.
* **Proposed:** Recommended as a future work package candidate; awaiting roadmap approval.
* **Approved:** Formally approved by project owner for implementation.
* **In Progress:** Currently under active development in an authorized feature branch.
* **Blocked:** Implementation or verification halted due to an external dependency or unavailable hardware.
* **Resolved:** Completed, validated, and merged into the active branch history.
* **Rejected:** Evaluated and determined to be out of scope, counter to policy, or invalid.
* **Requires Verification:** Finding requires live authenticated browser inspection before action can be determined.

---

## 2. Active & Seeded Findings

| ID | Title | Role | Severity | Status | Planned Package | Verification Level |
| :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| **UX-001** | Pressure-heavy student projection and risk copy | Student | High | Approved | `STUDENT-COPY-WP1` | Source-Confirmed |
| **UX-002** | Irrelevant Program column in Student Directory | Admin | Low | Approved | `ADMIN-UI-HOTFIX-WP1` | Source-Confirmed |
| **UX-003** | Admin Dashboard table empty vertical scroll range | Admin | Medium | Requires Verification | `ADMIN-UI-HOTFIX-WP1` | Screenshot-Supported |
| **UX-004** | Overlapping `dashboard.css` and `style.css` systems | Cross-portal | Medium | Proposed | `UI-SYSTEM-WP1` | Source-Confirmed |
| **UX-005** | Repetitive interchangeable card hierarchy | Cross-portal | Medium | Proposed | `UI-ROLE-WP1` | Screenshot-Supported |
| **UX-006** | Inline and page-local style drift | Cross-portal | Medium | Proposed | `UI-SYSTEM-WP1` | Source-Confirmed |
| **UX-007** | Prediction explanation discoverability & transparency | Admin | Medium | Resolved | `ADMIN-WP1A` | Authenticated-Browser Verified |
| **UX-008** | Role-specific dashboard workflow differentiation | Cross-portal | Medium | Proposed | `UI-ROLE-WP1` | Source-Confirmed |
| **UX-009** | Authenticated mobile-verification gap | Cross-portal | High | Blocked | `UI-WP-MOBILE` | Blocked (Hardware Unavailable) |
| **UX-010** | External Chart.js CDN dependency review | Cross-portal | Low | Proposed | `UI-SYSTEM-WP1` | Source-Confirmed |

---

## 3. Finding Detail Records

### UX-001: Pressure-Heavy Student Projection and Risk Copy
* **Description:** Student-facing dashboard alerts and risk messages employ deficit, blaming, or deterministic phrasing that induces panic rather than academic agency.
* **Evidence:**
  * `student/dashboard.php#L53`: `"Historical: Irregular enrollment status detected, which statistically increases graduation delay risk."`
  * `student/dashboard.php#L78`: `"A significant intervention is required."`
  * `student/dashboard.php#L84`: `"This is dragging down your projected GWA."`
  * `student/dashboard.php#L186`: `"your projected GWA is critically low (below 1.75)"`
* **Affected Role:** Student.
* **Severity:** High | **Confidence:** High | **Verification Level:** Source-Confirmed.
* **Approved Action:** Audit every student-facing alert and tooltip; replace with supportive guidance, missing-data context, and optional support suggestions per [docs/UI_UX_GOVERNANCE.md](UI_UX_GOVERNANCE.md).
* **Package:** `STUDENT-COPY-WP1` | **Status:** Approved.
* **Resolution Evidence:** Pending package implementation.

---

### UX-002: Irrelevant Program Column in Student Directory
* **Description:** The student directory table in `admin/students.php` displays a dedicated `Program` column that repeats `"BSIT"` for every student record in the cohort, wasting horizontal table width.
* **Evidence:** `admin/students.php` table header and row rendering.
* **Affected Role:** Administrator.
* **Severity:** Low | **Confidence:** High | **Verification Level:** Source-Confirmed.
* **Approved Action:** Remove the static `Program` column; reclaim column width for student number, year level, and academic standing.
* **Package:** `ADMIN-UI-HOTFIX-WP1` | **Status:** Approved.
* **Resolution Evidence:** Pending hotfix execution.

---

### UX-003: Admin Dashboard Table Empty Vertical Scroll Range
* **Description:** The primary activity/overview table on `admin/index.php` exhibits an internal vertical scroll container with excess blank vertical space that induces awkward nested scrolling.
* **Evidence:** Observed in administrator interface screenshots.
* **Affected Role:** Administrator.
* **Severity:** Medium | **Confidence:** Medium | **Verification Level:** Requires Verification (Screenshot-Supported).
* **Approved Action:** Inspect bounding boxes and CSS overflow rules in `admin/index.php`; eliminate artificial minimum height reservations and ensure the table height derives naturally from record count.
* **Package:** `ADMIN-UI-HOTFIX-WP1` | **Status:** Requires Verification.
* **Resolution Evidence:** Pending hotfix execution.

---

### UX-004: Overlapping `dashboard.css` and `style.css` Systems
* **Description:** Two distinct global stylesheets coexist in the repository (`assets/css/dashboard.css` and legacy `assets/css/style.css`), creating conflicting token definitions and duplicate class names.
* **Evidence:** File inventory and inspection of includes in `header.php`.
* **Affected Role:** Cross-portal.
* **Severity:** Medium | **Confidence:** High | **Verification Level:** Source-Confirmed.
* **Approved Action:** Audit all stylesheet consumers; migrate legacy rules into `dashboard.css`; declare `dashboard.css` as canonical; retire `style.css`.
* **Package:** `UI-SYSTEM-WP1` | **Status:** Proposed.
* **Resolution Evidence:** Pending roadmap approval.

---

### UX-005: Repetitive Interchangeable Card Hierarchy
* **Description:** Excessive reliance on identical `.card` containers for KPIs, notices, filters, tables, and buttons flattens visual hierarchy and creates "card fatigue."
* **Evidence:** Visual audit across all three portal dashboards.
* **Affected Role:** Cross-portal.
* **Severity:** Medium | **Confidence:** Medium | **Verification Level:** Screenshot-Supported.
* **Approved Action:** Restrict `.card` styling to primary logical boundaries; use section titles, subtle border dividers, and whitespace for secondary blocks per [docs/UI_UX_GOVERNANCE.md](UI_UX_GOVERNANCE.md).
* **Package:** `UI-ROLE-WP1` | **Status:** Proposed.
* **Resolution Evidence:** Pending roadmap approval.

---

### UX-006: Inline and Page-Local Style Drift
* **Description:** Individual PHP view files contain embedded `<style>` blocks and inline `style="..."` attributes that override global rules, causing design drift and patch fragility.
* **Evidence:** Traced in `student/dashboard.php`, `admin/analytics.php`, and `faculty/dashboard.php`.
* **Affected Role:** Cross-portal.
* **Severity:** Medium | **Confidence:** High | **Verification Level:** Source-Confirmed.
* **Approved Action:** Extract inline CSS into scoped component classes; enforce token reuse across all views.
* **Package:** `UI-SYSTEM-WP1` | **Status:** Proposed.
* **Resolution Evidence:** Pending roadmap approval.

---

### UX-007: Prediction Explanation Discoverability & Transparency
* **Description:** Users had no centralized screen to review grade completeness, missing term scores, or identify whether predictions stemmed from trained Decision Tree inference or fallback calculations.
* **Evidence:** Resolved under package `ADMIN-WP1A`.
* **Affected Role:** Administrator.
* **Severity:** Medium | **Confidence:** High | **Verification Level:** Authenticated-Browser Verified.
* **Approved Action:** Created `admin/coverage.php` (Grade Record Review) and added Tab 5 (*Data Coverage & Prediction Sources Reference*) to `includes/glossary_modal.php`.
* **Package:** `ADMIN-WP1A` | **Status:** Resolved.
* **Resolution Evidence:** Commits `802fc83` and `e926a0e`, verified by 34 Playwright tests in `tests/browser/admin-grade-coverage.spec.js`.

---

### UX-008: Role-Specific Dashboard Workflow Differentiation
* **Description:** Student, Faculty, and Admin dashboards currently share an identical KPI-first layout rather than leading with role-tailored workflows (academic guidance for students, review queues for faculty, operational governance for admins).
* **Evidence:** Layout inspection across portal entry views.
* **Affected Role:** Cross-portal.
* **Severity:** Medium | **Confidence:** High | **Verification Level:** Source-Confirmed.
* **Approved Action:** Conduct role-specific discovery; redesign dashboard homepages to lead with role-appropriate priorities.
* **Package:** `UI-ROLE-WP1` | **Status:** Proposed.
* **Resolution Evidence:** Pending roadmap approval.

---

### UX-009: Authenticated Mobile-Verification Gap
* **Description:** Comprehensive authenticated mobile device verification remains unperformed because physical test devices are unavailable in the development environment.
* **Evidence:** Documented in PR #30 roadmap resequencing.
* **Affected Role:** Mobile / Cross-portal.
* **Severity:** High | **Confidence:** High | **Verification Level:** Blocked (Hardware Unavailable).
* **Approved Action:** Package `UI-WP-MOBILE` is placed on hold; desktop and tablet work proceed independently until hardware verification is available.
* **Package:** `UI-WP-MOBILE` | **Status:** Blocked.
* **Resolution Evidence:** Awaiting physical device availability.

---

### UX-010: External Chart.js CDN Dependency Review
* **Description:** Performance trend visualizations rely on external CDN script loading (`https://cdn.jsdelivr.net/npm/chart.js`), creating an offline execution and network dependency.
* **Evidence:** `includes/header.php` script tags.
* **Affected Role:** Cross-portal.
* **Severity:** Low | **Confidence:** High | **Verification Level:** Source-Confirmed.
* **Approved Action:** Evaluate whether Chart.js should be vendor-bundled locally or replaced with accessible CSS/SVG representations for institutional durability.
* **Package:** `UI-SYSTEM-WP1` | **Status:** Proposed.
* **Resolution Evidence:** Pending roadmap approval.
