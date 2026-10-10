---
name: playwright-verification
description: Discovers and executes Playwright browser test specs across viewports and themes, classifying execution results and verifying layout invariants.
---

# Playwright Verification Skill

This skill guides the discovery, execution, and classified reporting of Playwright browser tests.

## When to Use
Use this skill whenever:
* Modifying UI templates, navigation, tables, modals, or CSS stylesheets.
* Verifying responsive grid layouts across desktop, tablet, and mobile viewports.
* Testing dark/light mode contrast, keyboard navigation, or dialog focus trapping.
* Verifying the Faculty Dashboard warning banner layout contract.

---

## 1. Dynamic Discovery and Execution Sequence

Do not assume a static number of browser tests. Discover applicable specs dynamically in `tests/browser/`:

### Step 1: Layout Regression Check (Mandatory for Layout/Faculty Edits)
When modifying `faculty/dashboard.php` or shared layout CSS (`assets/css/dashboard.css`), always run the layout regression first:
```powershell
npx playwright test tests/browser/faculty-dashboard-layout-regression.spec.js
```

### Step 2: Feature-Specific Browser Suite
Run the spec targeting the current feature (e.g., active drilldowns, tables, or navigation):
```powershell
npx playwright test tests/browser/<feature-spec>.spec.js
```

### Step 3: Full Browser Suite
Execute all discovered specs:
```powershell
npm run test:browser
```
*(Or `npx playwright test --config=playwright.config.js`)*

---

## 2. Standard Viewport and Theme Matrix

When manual or focused automated verification is required, test across these standard viewports:
* **Large Desktop:** $1440 \times 900$
* **Medium Desktop / Laptop:** $1280 \times 720$
* **Standard Tablet / Small Laptop:** $1024 \times 768$
* **Portrait Tablet:** $768 \times 1024$
* **Narrow Mobile:** $390 \times 844$ (and short height $390 \times 700$ / landscape $844 \times 390$)

Verify both **Light** and **Dark** themes. Ensure touch targets meet $\ge 44 \times 44\text{px}$ requirements on mobile viewports.

---

## 3. Mandatory Test Classification in Reports

When reporting Playwright test results, explicitly categorize every check:
1. **Live Authenticated Application:** Tests executing real sessions against local Apache/MySQL (`localhost/udm-radar/`).
2. **Generated Static HTML:** Tests loading exported DOM snapshots or static component fixtures.
3. **Mocked Browser:** Tests utilizing route mocks or injected JSON fixtures.
4. **Repository Source Inspection:** Static file or AST checks performed via test runners.

Clearly report the count of tests **skipped due to missing local credentials** (e.g., when `.env.playwright.local` is not configured). Do not claim source-inspection checks are end-to-end tests.

---

## 4. Strict Safety Constraints

* **Never Modify Test Config to Mask Failures:** Never adjust timeouts, disable assertions, or switch to headless modes solely to hide an active failure.
* **Never Stage Ephemeral Artifacts:**
  * Do not stage `test-results/`
  * Do not stage `playwright-report/`
  * Do not stage `.env.playwright.local`
  * Do not stage screenshots, video captures, or traces unless explicitly designated as tracked documentation.

