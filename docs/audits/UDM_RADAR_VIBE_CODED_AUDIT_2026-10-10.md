# UDM-RADAR UI/UX Heuristic Audit: Institutional Workbench vs. Generic AI Patterns

> [!NOTE]
> Historical design and source audit dated October 10, 2026.
>
> This report is a qualitative review, not a validated usability score,
> accessibility certification, or complete authenticated-browser audit.
>
> Some findings were established through source inspection, generated
> fixtures, and available screenshots rather than normal authenticated
> portal walkthroughs.
>
> For current approved UI direction and implementation priorities, see:
>
> - `docs/UI_UX_GOVERNANCE.md`
> - `docs/ROADMAP.md`
> - `docs/IMPLEMENTATION_HISTORY.md`

---

## 1. Executive Summary

This qualitative review evaluates Universidad de Manila — Risk Analytics & Decision-support for Academic Records (UDM-RADAR) against the design patterns commonly found in generic, AI-generated ("vibe-coded") dashboard templates versus those required for a durable, calm, institutional academic workbench.

### Overall Qualitative Assessment
* **Qualitative Heuristic Rating:** **32% "Vibe-Coded" Appearance Factor** / **68% Domain-Specific Institutional Substance**.
  *(Notice: This 32% figure is an informal qualitative heuristic used to organize audit observations; it is not a validated usability metric, standardized software-quality index, or formal project KPI.)*
* **Core Finding:** UDM-RADAR is **not** a generic AI template. The underlying system exhibits deep domain substance: deliberate role boundaries, institutional grading scale semantics ($4.00$ to $0.00$), weighted GWA math, non-punitive Academic Support referral loops, prediction-source transparency, and synthetic data protections.
* **Surface Dilemma:** Despite solid academic foundations, several visual and copy patterns create the visual impression of an auto-generated dashboard: repetitive rounded card containers, deficit-oriented student projection language, interchangeable KPI walls across all roles, and inline style overrides that cause visual drift.

---

## 2. Audit Scope & Verification Protocol

### Evidence Classification
Because authenticated browser walkthroughs across all roles were not available during this audit sprint, each observation is classified by its actual verification level:
1. **Source-Confirmed:** Directly traced in repository PHP view templates, includes, or CSS stylesheets.
2. **Screenshot-Supported:** Observed in captured Playwright screenshots or static review captures.
3. **Fixture-Verified:** Confirmed via deterministic static HTML fixtures.
4. **Authenticated-Browser Verified:** Verified through an active, live logged-in session in a browser runner.
5. **Not Visually Verified (Requires Verification):** Inferred from code structure, but unverified under live browser rendering.

---

## 3. Detailed Audit Findings

### 3.1 Student-Facing Language & Deficit Phrasing (High Severity)
* **Status:** Source-Confirmed (`student/dashboard.php`).
* **Issue:** Several alert messages and risk descriptions employ punitive, blaming, or deterministic phrasing that directly violates the project's non-punitive ethical mandate.
* **Concrete Examples in Source:**
  * `student/dashboard.php#L53`: `"Historical: Irregular enrollment status detected, which statistically increases graduation delay risk."` — Unqualified graduation-delay claim without curriculum context.
  * `student/dashboard.php#L78`: `"A significant intervention is required."` — High-pressure deficit phrasing.
  * `student/dashboard.php#L84`: `"This is dragging down your projected GWA."` — Blaming and discourages student agency.
  * `student/dashboard.php#L186`: `"your projected GWA is critically low (below 1.75)"` — Punitive framing for an advisory prototype.
* **Evaluation:** This is the most critical usability and ethical finding. Student interfaces must provide supportive guidance, missing-data context, and suggested actions rather than surveillance warnings.

### 3.2 Interchangeable Card Treatment ("Card Fatigue") (Medium Severity)
* **Status:** Source-Confirmed and Screenshot-Supported.
* **Issue:** Almost every piece of information across the application is wrapped in an identical rounded `.card` container.
* **Observed Elements Using Identical Cards:**
  * Top KPI metrics
  * Advisory notices and banners
  * Filter controls
  * Primary data tables
  * Reusable guidance and help panels
  * Action buttons
* **Impact:** When everything sits in an equally prominent card with identical borders, shadows, and radii, nothing stands out. Visual hierarchy is flattened, making it difficult for users to scan for primary actions or urgent review queues.

### 3.3 Styling Fragmentation & Visual Drift (Medium Severity)
* **Status:** Source-Confirmed (`assets/css/dashboard.css`, `assets/css/style.css`).
* **Issue:** The repository maintains overlapping CSS architectures:
  * `assets/css/dashboard.css` (active shared modern layout rules and theme tokens)
  * `assets/css/style.css` (legacy prototype stylesheet)
  * Page-local `<style>` blocks and inline `style="..."` attributes on tables, headers, and KPI grids.
* **Impact:** Shared patterns drift over time. Small layout corrections (such as table padding, button heights, or badge colors) have required localized overrides rather than inheriting centralized tokens.

### 3.4 Symmetrical, KPI-First Hierarchy Across Distinct Roles (Medium Severity)
* **Status:** Source-Confirmed (`student/dashboard.php`, `faculty/dashboard.php`, `admin/index.php`).
* **Issue:** Student, Faculty, and Administrator dashboards all lead with an identical four-card KPI grid followed by a wide table and chart canvas.
* **Role Mismatch:**
  * **Students** do not need operational KPIs first; they need an academic snapshot, supportive guidance, plain-language explanation of missing data, and concrete next steps.
  * **Faculty** need action queues first: students requiring review, sections with pending grade batches, and pending support referrals.
  * **Administrators** need workload overview, data coverage, approval pipelines, and system governance visibility.

### 3.5 Prediction Source & Uncertainty Transparency (Low–Medium Severity)
* **Status:** Source-Confirmed and Fixture-Verified.
* **Issue:** Machine learning predictions, heuristic estimates, and incomplete-feature fallbacks have historically shared identical badge placements and generic "Predicted GWA" labels.
* **Requirement:** Users must easily differentiate:
  * Decision Tree model inference vs. calculation-based backup estimates.
  * Generation timestamp vs. prediction freshness (grades may have been edited after the prediction was produced).
  * Missing grade data (blank / `NULL`) vs. numeric zero ($0.00$ / failed course).
  * Prototype coverage within UDM-RADAR vs. official Registrar encoding completion.

### 3.6 Typography, Badges, and Visual Clutter (Low Severity)
* **Status:** Source-Confirmed and Screenshot-Supported.
* **Issue:** Heavy bolding across table cells, redundant status pills (e.g., displaying `8 of 8 Preliminary` immediately beside a `[ Complete ]` badge), and high-contrast dark buttons occupying substantial vertical space.
* **Evaluation:** Replacing the typeface (Inter) is unnecessary; Inter is legible and compact for dense data. The real solution is calmer weight distribution (regular/medium weight), removing redundant status badges, and standardizing table action button dimensions.

---

## 4. Observations by Screen & Verification State

| Screen | Primary Observations | Verification Level | Follow-Up Action |
| :--- | :--- | :--- | :--- |
| **Student Dashboard** (`student/dashboard.php`) | Pressure-heavy copy ("dragging down", "critically low"); graduation delay claims; KPI-first structure. | Source-Confirmed | `STUDENT-COPY-WP1` |
| **Faculty Dashboard** (`faculty/dashboard.php`) | Attention banner layout isolation resolved; section drilldowns functional; KPI empty space under review. | Authenticated-Browser Verified | Layout maintained |
| **Admin Dashboard** (`admin/index.php`) | Workload banner functional; data table has empty vertical scroll range needing containment. | Screenshot-Supported | `ADMIN-UI-HOTFIX-WP1` |
| **Admin Student Directory** (`admin/students.php`) | Table contains an unnecessary `Program` column (all students are in BSIT); redundant filter space. | Source-Confirmed | `ADMIN-UI-HOTFIX-WP1` |
| **Admin Grade Coverage** (`admin/coverage.php`) | Read-only coverage review, calm typography, empty-state in tbody, prediction sources clearly labeled. | Authenticated-Browser Verified | Completed in `ADMIN-WP1A` |
| **Academic Guide Drawer** (`includes/glossary_modal.php`) | Multi-tab reference drawer, keyboard-trapped, focus-restored; admin gets Data Coverage tab. | Authenticated-Browser Verified | Maintained |
| **Shared Layout CSS** (`assets/css/dashboard.css`) | Dual-token systems with legacy `style.css`; localized overrides across views. | Source-Confirmed | `UI-SYSTEM-WP1` |

---

## 5. Strategic Roadmap Recommendations

Rather than attempting an undisciplined, full-system visual rewrite, the findings should be extracted into modular, verified packages:

1. **`STUDENT-COPY-WP1` (High Priority):** Audit and rewrite all student-facing alerts, risk explanations, and triage text into supportive, pressure-safe academic guidance.
2. **`ADMIN-UI-HOTFIX-WP1` (Immediate Hotfix):** Remove the redundant `Program` column in `admin/students.php` and eliminate unnecessary vertical scroll height on `admin/index.php`.
3. **`UI-SYSTEM-WP1` (Medium-Term Maintenance):** Audit `dashboard.css` vs. `style.css`, declare a single shared stylesheet baseline, and consolidate visual tokens.
4. **`UI-ROLE-WP1` (Future Architecture):** Redesign dashboard information hierarchy to align with role-specific workflows (guidance for students, review queues for faculty, governance for admin).
