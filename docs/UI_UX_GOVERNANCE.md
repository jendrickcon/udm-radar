# UDM-RADAR UI/UX Governance

This living governance document defines durable interface standards, language guidelines, layout contracts, and review requirements for Universidad de Manila — Risk Analytics & Decision-support for Academic Records (UDM-RADAR).

---

## 1. Design Thesis

UDM-RADAR is a **calm academic records and decision-support workbench**.

The interface must emphasize evidence, review, explanation, and supportive human discretion rather than urgency, surveillance, punitive countdowns, or autonomous institutional judgments. Machine learning projections provide non-binding advisory context; they do not replace faculty evaluations, academic dean determinations, or official Registrar records.

---

## 2. Role-Specific Priorities

The application serves three distinct user roles. Dashboards and primary views must reflect these distinct operational needs rather than presenting an identical, symmetrical KPI-first layout across all roles.

### 2.1 Student Role
* **Primary Purpose:** Academic self-awareness, clarity on in-progress coursework, and accessible support pathways.
* **Core Views & Elements:**
  * Current academic snapshot (cumulative GWA, units completed, in-progress term results).
  * Plain-language explanations of standing and predictive estimates.
  * Explicit disclosure of unrecorded or missing grades (never disguised as failures).
  * Suggested, voluntary support actions (e.g., connecting with faculty or filing a grade concern).
  * Student-controlled planning tools (e.g., target grade scenario calculators).

### 2.2 Faculty Role
* **Primary Purpose:** Instructional review, class performance monitoring, and human-in-the-loop support referrals.
* **Core Views & Elements:**
  * Active review queues (students in need of academic attention or support referrals).
  * Section-level grade distributions and encoding status.
  * Direct access to underlying grade evidence before issuing notices.
  * Discretionary referral creation for academic support (not unilateral automated dispatches).
  * Clear tracking of pending grade batch approvals.

### 2.3 Administrator Role
* **Primary Purpose:** Departmental oversight, data coverage auditing, and workflow governance.
* **Core Views & Elements:**
  * Operational workload summaries (pending grade batches, active support cases, correction requests).
  * Data completeness and coverage audit tools across academic terms.
  * Multi-section and curriculum-wide analytics.
  * Prediction provenance, model boundaries, and fallback identification.
  * Read-only export and compliance logging.

---

## 3. Student-Safe Language

Language across all user-facing views, intervention notices, triage alerts, and tooltips must remain supportive, objective, and pressure-safe.

### 3.1 Prohibited Deficit Phrasing
Never introduce terms that imply finality, blame, disciplinary action, or punitive countdowns:
* ❌ `"Critically low"`
* ❌ `"Dragging down"`
* ❌ `"A significant intervention is required"`
* ❌ `"Strike system"` or strike counts
* ❌ `"Dismissal countdown"` or probation threats
* ❌ `"Scholarship-loss prediction"`
* ❌ `"Allowance-risk warning"`
* ❌ Unqualified graduation-delay claims (e.g., claiming irregular pacing automatically delays graduation)

### 3.2 Approved Supportive Alternatives
Always frame academic performance in constructive, actionable terms:
* ✅ `"May benefit from review"`
* ✅ `"Current estimate"`
* ✅ `"Available records for this term"`
* ✅ `"Suggested next step"`
* ✅ `"Consider discussing this coursework with your faculty instructor"`
* ✅ `"Additional grade entries may adjust this estimate"`
* ✅ `"Receipt acknowledged"` (when confirming support notice delivery, never "admission" or "agreement of fault")

---

## 4. Information Hierarchy & Visual Density

### 4.1 Card Usage & Visual Grouping
* **Card Discipline:** Do not wrap every interface element inside an equally prominent `.card` container.
* **Grouping Rule:** Use cards strictly for meaningful logical groupings (e.g., a filter panel, a primary table container, or an actionable alert banner).
* **Dividers & Flow:** Use clean section headings, whitespace, and subtle border dividers for secondary information instead of nesting cards within cards.

### 4.2 Action Hierarchy
Every view must maintain a distinct, unambiguous hierarchy of interactive controls:
* **Primary Action (1 per view maximum):** Prominently styled (e.g., `.btn--primary`). Reserved for the principal forward-progress task (e.g., *Submit grade batch*, *Apply filters*).
* **Secondary Actions:** Visually balanced (e.g., `.btn--secondary`). Used for alternative or drilldown tasks (e.g., *View details*, *Export report*).
* **Tertiary Controls:** Quiet, unobtrusive styling (e.g., `.btn--quiet` or text-only links). Used for clearing state, back navigation, or dismissals.

---

## 5. Tables & Data Display

Tables represent the core working surface of the workbench. They must remain dense, legible, and structurally calm.

### 5.1 Column Relevance & Simplicity
* Display only decision-relevant columns. Remove columns that repeat identical static data across all rows (e.g., do not display a static "Program: BSIT" column when every record in the table belongs to the BSIT program).
* Avoid redundant status badges in table cells. For example, do not place a `[ Complete ]` badge immediately next to `8 of 8 Preliminary`; the count already conveys completion.

### 5.2 Scrolling & Row Geometry
* **Horizontal Overflow:** Wrap tables in a dedicated `.data-table-scroll` container to permit table-only horizontal scrolling on narrow screens without inducing viewport-level horizontal overflow.
* **Vertical Scrolling:** Avoid nested vertical scroll containers with blank or artificial height reservations. Tables should derive their height naturally from their row content and page size.
* **Neutral Rows:** Keep table rows visually neutral. Do not apply whole-row hover highlights or saturated backgrounds that misleadingly imply the entire row is a clickable link. Interactive controls must remain distinct buttons within their own cells.
* **Touch Targets:** Action buttons in rows must preserve an accessible $\ge 44\text{px}$ touch target while maintaining compact visible button geometry (e.g., $36\text{px}$ height with transparent vertical hit padding).

### 5.3 Empty-State Placement
When zero records match active filters:
* Keep the table header (`<thead>`) visible to maintain context.
* Render the empty state as a single row inside the table body (`<tbody>`):
  ```html
  <tr class="coverage-empty-row">
      <td colspan="[number-of-columns]" class="coverage-empty-cell">
          <div class="coverage-empty-content">
              <h3>No [items] match the selected filters.</h3>
              <p>Try changing the filters or clear them to view all records.</p>
              <button type="button" class="btn btn--secondary">Clear filters</button>
          </div>
      </td>
  </tr>
  ```
* Do not place a separate error banner above the column headings followed by an empty table.

---

## 6. KPI Card Discipline

* **Action-Tied Metrics:** Display only summary metrics that inform an active user decision. Avoid decorative KPI walls.
* **Concise Sentence-Case Labels:** Use short, single-line visible headings (e.g., `Students reviewed`, `Complete`, `Some missing`, `Needs review`).
* **Shared Context Placement:** Place shared parameters (such as the active grading period) in a shared context heading above the KPI row (`Current-term results · Preliminary`), rather than repeating the term inside every individual card.
* **Proximity & Spacing:** Keep KPI headings and values visually grouped with a compact $4\text{px}\text{--}6\text{px}$ vertical gap. Avoid `justify-content: space-between` with arbitrary fixed heights that stretches cards unnaturally.
* **Color Independence:** Never communicate status through color alone. Combine color with explicit text labels and accessible ARIA descriptions.

---

## 7. Prediction & Data Vocabulary

UDM-RADAR operates on synthetic academic data and incorporates machine learning models alongside deterministic calculation fallbacks. Vocabulary must strictly reflect these boundaries:

1. **Missing Data vs. Numeric Zero:**
   * A missing or unrecorded score is `NULL` (displayed as a dash `—` or marked `missing`).
   * A numeric zero (`0.00` or `0%`) is an actual recorded failing grade.
   * Unrecorded grades must never be coerced to zero.
2. **Passing Outcomes vs. Academic Risk:**
   * Passing point grades range from $4.00$ down to $1.00$. Grade $0.00$ is failing.
   * Prototype Risk triage (High: $< 1.75$, Moderate: $1.75\text{--}2.49$, Low: $\ge 2.50$) is an advisory triage mechanism. A passing grade below $1.75$ (such as $1.50$ or $1.25$) is a passed course, never a failed subject.
3. **Current Input Availability vs. Stored Prediction Metadata:**
   * Current input coverage reflects grades currently available in the active term.
   * Stored prediction metadata reflects what information was present when the model was originally executed.
4. **Prediction Sources:**
   * **Decision Tree model:** System projection from trained ML model inference.
   * **Calculation-based estimate:** Deterministic fallback calculation used when input data was insufficient for ML inference. Not an AI model projection.
   * **Earlier calculation method:** Projections stored under earlier prototype versions.
5. **Generation Timestamp vs. Freshness:**
   * A prediction timestamp records when the calculation ran. It does not prove that subsequent grade entries or instructor edits have been incorporated.
6. **Prototype Boundary vs. Registrar Completion:**
   * Prototype records indicate data availability within UDM-RADAR, not official Registrar encoding completion or finalized transcript standing.

---

## 8. Help, Guidance & Explanations

* **Visible Meaning:** Essential interpretations and data definitions must remain visible on the page without requiring hover or click interactions.
* **Supplementary Tooltips:** Use tooltips strictly for secondary explanations. Never hide vital decision information behind hover.
* **No Row-by-Row Tooltip Clutter:** Avoid placing repetitive help icons on every table row. Place explanatory help buttons in column headers or section titles.
* **Academic Guide Modal:** Use the centralized Academic Guide (`includes/glossary_modal.php`) for universal institutional policies, grading scales, and risk definitions.
* **Page Methodology:** Use collapsible disclosures (`<details>`) for page-specific counting rules or data collection mechanics.
* **Keyboard Accessibility:** All help triggers, disclosures, and drawers must support full keyboard operation (Enter/Space to toggle, Escape to dismiss) and restore focus to the invoking trigger upon closing.

---

## 9. Motion & Animation Standards

* **Purposeful Motion:** Motion must serve only to reveal state changes, smooth scroll repositioning, or clarify disclosure expansion.
* **No Decorative Card Animations:** Prohibit staggered card pop-ins, bouncy load sequences, or looping visual effects.
* **Restrained Duration:** Transitions must remain brief ($\le 150\text{ms}\text{--}200\text{ms}$) with easing curves optimized for responsiveness (`ease-out`).
* **Conditional Auto-Scroll:** When expanding details panels, trigger auto-scrolling only if the opened panel extends outside the visible viewport. Anchor summary rows below sticky table headers so context is never lost.
* **Reduced Motion:** Fully respect `prefers-reduced-motion: reduce`. When active, disable all transitions, keyframe animations, and smooth scroll behaviors (`behavior: auto`), delivering immediate visual updates.

---

## 10. Shared Stylesheets & Component Architecture

* **Canonical Stylesheet:** Treat `assets/css/dashboard.css` as the active shared UI baseline for layout grids, color tokens, typography, and card containers.
* **No Duplicate Components:** Never redefine standard buttons, badges, tables, or KPI cards inside page-specific stylesheets.
* **Scoped Page Styles:** Page-specific CSS files (e.g., `assets/css/grade-coverage.css`) must be strictly scoped to their container classes (e.g., `.coverage-page ...`).
* **Legacy System Retirement:** The coexistence of legacy `assets/css/style.css` and `assets/css/dashboard.css` is recognized as a technical maintenance debt. Consolidation into a single unified design token file is tracked under package `UI-SYSTEM-WP1`.

---

## 11. Verification & Quality Gates

Every user-interface change package must report verified evidence across these quality dimensions prior to commit review:

1. **Exact Files & Pages Affected:** Complete allowlist of changed runtime, stylesheet, and template files.
2. **Visual Parity:** Confirmed rendering across Light Mode and Dark Mode.
3. **Responsive Breakpoints:** Verified layout across standard desktop ($1440\times 900$), laptop/tablet ($1024\times 768$, $768\times 1024$), mobile ($390\times 844$), and constrained viewport heights ($600\text{px}$).
4. **Keyboard & Focus Integrity:** Complete tab order traversal, visible focus outlines (`:focus-visible`), modal focus trapping, and Escape key dismissal with focus restoration.
5. **State Coverage:** Explicit testing of loading states (`aria-busy`), zero-match empty states, and error fallback states.
6. **Overflow Prevention:** Verified absence of horizontal page scrolling (`scrollWidth <= innerWidth`).
7. **Evidence Classification:** Explicit classification of findings as Authenticated-Browser Verified, Static HTML Verified, Mocked Browser, or Source Inspection.
8. **Credential Skips:** Transparent documentation of any test skips caused by absent live authentication cookies.
9. **Protected Assets:** Zero modifications to canonical database dumps (`database/udm_radar.sql`) or active machine learning models (`python_ml/model.pkl`, `python_ml/model_metrics.json`).
