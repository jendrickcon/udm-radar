# Member 2 UI Browser Accessibility Audit

> [!NOTE]
> **Historical Contribution Snapshot:** This audit reports Member 2's browser accessibility work (`UI-A11Y-01`, `UI-RESP-01`), which was merged through PR #21 and subsequently incorporated into `main` via PR #23.
> For current project status and active roadmap, see:
> - **Canonical Roadmap:** [docs/ROADMAP.md](../ROADMAP.md)
> - **Implementation History:** [docs/IMPLEMENTATION_HISTORY.md](../IMPLEMENTATION_HISTORY.md)

## Status

- Branch: `member2/ui-browser-accessibility`
- Base: `origin/feat/ui-evaluation-readiness` at `01670c39edfecf9b9184edda383e4b019e0ab7c0`
- Intended PR base: `feat/ui-evaluation-readiness`; `main` is not a target.
- Implementation/evidence commits: `a254df7`, `3413a62`, `32cac54`, `9f96009`.
- Push: successful; `member2/ui-browser-accessibility` tracks `origin/member2/ui-browser-accessibility` and is up to date. Report-only updates are documented in the branch history.
- PR: not created yet. GitKraken requires sign-in before the PR request can complete.

## Findings Addressed

### UI-A11Y-01: Academic Guide focus containment

The open Guide now traps Tab and Shift+Tab between its first and last visible focusable controls. The existing behavior remains: Enter opens the Guide, Space activates a tab, Escape closes it, and focus returns to the floating trigger. Drawer content still scrolls independently and the mobile tab strip remains horizontally scrollable.

### UI-RESP-01: Mobile floating-control overlap

At widths up to 600px, `.main-content` reserves a 76px right gutter and the floating button observes safe-area insets. The Guide remains visible; the mobile dashboard screenshot confirms section cards do not intersect it while scrolled.

## Files

Modified:

- `.gitignore`
- `assets/css/dashboard.css`
- `includes/glossary_modal.php`

Created:

- `package.json`
- `package-lock.json`
- `playwright.config.js`
- `tests/browser/faculty-academic-guide.spec.js`
- `docs/audits/member2-ui-browser-accessibility.md`
- `docs/audits/evidence/member2/` screenshots listed below

The ignored `.env.playwright.local` contains local-only synthetic Faculty credentials and is not staged. `node_modules`, Playwright reports, test results, auth state, and traces are ignored. Trace and video recording are disabled.

## Playwright Setup

- Node.js: `v24.21.0`, extracted user-locally after official SHA-256 verification
- npm: `11.19.0`
- `@playwright/test`: exact version `1.63.0`
- Browser: Chromium `153.0.8010.12` (Playwright build `1243`); Firefox and WebKit were not installed
- Browser tests are read-only against the configured local Faculty dashboard; no write-capable workflow is exercised.

## Verification

Focused Playwright command:

```powershell
.\node_modules\.bin\playwright.cmd test --config=playwright.config.js --timeout=30000
```

Result: 5 passed, 0 failed. The tests cover Faculty dashboard loading, named/keyboard-operable Guide control, dialog semantics, Tab and Shift+Tab wrapping, Escape and focus restoration, all three requested viewport sizes, mobile card/control non-overlap, light/dark switching, and browser health.

Other successful checks:

```powershell
php -l includes/glossary_modal.php
node --check playwright.config.js
node --check tests/browser/faculty-academic-guide.spec.js
npm.cmd ls @playwright/test --depth=0
```

Browser health was captured for each test. Across all five tests: 0 console errors, 0 uncaught page errors, 0 failed requests, 0 HTTP error responses, and 0 optional-favicon 404s. Local and external failures are classified separately in the test diagnostics.

Viewports verified: 1440×900, 1024×768, and 390×844. Light and dark mode both passed.

Existing database-free PHP unit tests: 479 assertions, 0 failures across five unit files, run by iterating `tests/unit/*_test.php` with PHP. Database-backed PHP integration tests were not run because neither approved `udm_radar_scratch` nor `udm_radar_demo` is available.

## Screenshot Evidence

All captures use the approved synthetic demonstration session. Student table cells are masked; no password, token, or credential is present.

- [Faculty dashboard, 1440×900](evidence/member2/faculty-dashboard-1440x900.png)
- [Faculty dashboard, 1024×768](evidence/member2/faculty-dashboard-1024x768.png)
- [Faculty dashboard, 390×844](evidence/member2/faculty-dashboard-390x844.png)
- [Academic Guide open, desktop 1440×900](evidence/member2/academic-guide-desktop-1440x900.png)
- [Academic Guide open, desktop 1024×768](evidence/member2/academic-guide-desktop-1024x768.png)
- [Academic Guide open, mobile 390×844](evidence/member2/academic-guide-mobile-390x844.png)
- [Mobile dashboard scrolled to section cards, no overlap](evidence/member2/faculty-dashboard-mobile-clearance.png)

Image dimensions were verified against the filenames. Evidence was committed in `9f96009`.

## Git and Protected Scope

- `database/udm_radar.sql`, the supplied Downloads dump, `python_ml/model.pkl`, and `python_ml/model_metrics.json` are unchanged.
- Academic grading logic, GWA calculations, risk thresholds, prediction behavior, schema, migrations, and backend workflows were not modified.
- `git diff --check` passed. No credential file, `node_modules`, report, test result, trace, or browser output is staged.
- Four scoped commits were pushed only to `member2/ui-browser-accessibility`; `main` and the feature base were not modified.
- The PR is not open yet; no merge has been performed.

Proposed PR title: `fix(ui): contain Academic Guide focus and prevent mobile overlap`

Proposed PR description:

> Adds keyboard focus containment to the shared Academic Guide while preserving Escape close and trigger focus restoration. Reserves a mobile content gutter so the floating control does not cover Faculty section cards. Adds focused Chromium coverage, browser-health diagnostics, and redacted evidence. Five Playwright tests and 479 database-free PHP unit assertions pass. The PR base must be `feat/ui-evaluation-readiness`.

## Member Walkthrough

1. **Problem:** Shift+Tab could move focus from the Guide into the page behind its modal dialog, and the floating help button could overlap Faculty content on a narrow screen.
2. **Why it mattered:** Keyboard users should remain inside an active modal, and controls/content should not be obscured on mobile.
3. **Changes:** The Guide now wraps focus at its boundaries; mobile content reserves a clear strip beside the still-visible floating button.
4. **How focus containment works:** When the Guide is open, Tab from the last control moves to the first; Shift+Tab from the first moves to the last. Escape still closes the Guide and restores focus to its trigger.
5. **Playwright verification:** The focused suite logged in with the ignored synthetic-account env file and exercised five dashboard, drawer, viewport, theme, and browser-health tests.
6. **Reproduce the old keyboard issue:** Before the fix, open the Guide, focus “Close guide,” then press Shift+Tab; focus escaped to the page behind it.
7. **Verify the fix manually:** Open the Guide, use Tab and Shift+Tab at both ends, activate a tab with Space, press Escape, and confirm focus returns to the floating trigger. At 390×844, scroll to the section cards and confirm the help control does not cover them.
8. **Relevant files:** Focus behavior is in `includes/glossary_modal.php`; mobile spacing is in `assets/css/dashboard.css`; browser checks are in `tests/browser/faculty-academic-guide.spec.js`.
9. **Rerun browser tests:** From the repository root, run `npm.cmd run test:browser` or `.\node_modules\.bin\playwright.cmd test --config=playwright.config.js` after ensuring local Node/npm are available.
10. **Review evidence:** Open the seven PNGs under `docs/audits/evidence/member2/`; review `playwright-report/` locally (it is ignored).
11. **Confirm branch:** Check `git status -sb`; the branch should be `member2/ui-browser-accessibility`. Compare its base with `origin/feat/ui-evaluation-readiness` before pushing.
12. **Open the PR:** Set base to `feat/ui-evaluation-readiness` and compare to `member2/ui-browser-accessibility`. Do not target or merge into `main` as part of this work.
13. **Defense explanation:** “I fixed a modal keyboard focus escape and a mobile overlap using a small focus loop and reserved content gutter. I verified keyboard behavior, themes, three sizes, and browser health with a focused Playwright suite.”
14. **Safe follow-ups:** Add a separately approved scratch database for any future write-capable browser flows; consider broader keyboard and screen-reader checks in a later scoped task.

## Pull Request Gate

The implementation, tests, screenshots, and diff were approved before commits. The branch was pushed without rebasing or force-pushing. To finish, authenticate the GitKraken integration in VS Code and retry PR creation with base `feat/ui-evaluation-readiness` and compare `member2/ui-browser-accessibility`. Do not merge the PR automatically.