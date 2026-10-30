# Student portal combined regression report

Date: 29 October 2026 (Asia/Manila). Branch: `feat/member2-student-portal`.

**Result: PASS for the executed automated regression scope.** Owner acceptance, original-account password verification and PDF/export certification are separate gates. PDF testing was explicitly set aside by the user.

The tested code is a dirty local candidate on HEAD `94550c75268d103def200723b78e9d39b64edcba`, not a new committed/published version. Application fingerprints and raw run evidence are retained in ignored `logs/student-regression/` and `logs/student-trend/`.

## Current results

| Category | Passed | Failed | Skipped |
|---|---:|---:|---:|
| GWA helper PHP checks | 108 | 0 | 0 |
| Final-grade helper PHP checks | 271 | 0 | 0 |
| Grade-history PHP checks | 26 | 0 | 0 |
| Goal Calculator contract PHP checks | 53 | 0 | 0 |
| Prediction explanation PHP checks | 32 | 0 | 0 |
| Student Dashboard state PHP checks | 31 | 0 | 0 |
| Student trend model/source PHP checks | 29 | 0 | 0 |
| **PHP checks total** | **550** | **0** | **0** |
| Calculator JavaScript tests | 16 | 0 | 0 |
| Authenticated Student browser tests, final complete run | 25 | 0 | 0 |
| Syntax checks: 8 PHP files, 6 JavaScript/module files | 14 | 0 | 0 |

Categories measure different things and must not be combined into a software/model accuracy percentage. Some PHP checks render extracted source fragments; two trend checks inspect source structure. They are not authenticated application tests.

Pinned Playwright: **1.63.0**. Installed Edge: **156.0.4314.50**. No browser/dependency downloads, screenshots, videos or traces were produced by this run. The real Chart.js asset was served from the existing local cache for deterministic transport; chart data and application endpoints were not mocked.

## Authenticated coverage

- Three desktop sizes, 1440×900, 1280×600 and 1024×768, in light and dark themes.
- Actual synthetic fixture logins, Student session identity, logged-out denial, logout invalidation and cross-role denial.
- Foreign Student identifier and analytics/support-filter rejection without another Student's records.
- Chart/table payload parity, null gaps, current observations versus completed history versus estimate, heading collision assertions, visible keyboard focus, Enter/Escape disclosure interaction, larger text and horizontal-overflow checks.
- Empty/no-grade, preliminary-only, history-only, estimate-only and incomplete-final scenarios.
- Chart-library-unavailable fallback preserving the exact evidence table.
- Grades tooltip, calculator target locking/Reset/Close behavior, Home and Feedback page navigation.
- Dashboard/Academic Review risk consistency for a saved 2.15/stale LOW case and deterministic latest-prediction timestamp ties.
- Enrollment Planning GET, recorded-subject disclosure, pending-grade state, desktop/theme geometry and rejection of a foreign Student identifier.

This run does not certify every manually selected checkbox scenario, feedback submission, correction workflow, private workbook, preview POST, PDF download, native print output or every account in the installed 291-Student dataset. Separate focused chart geometry/mobile runs are recorded in the progress ledger; they were not counted as new tests in this combined run.

## Failure investigation and correction

First complete browser run: **19 passed, 6 failed, 0 skipped, 0 flaky**. All six failures were the same desktop/theme assertion that expected a keyboard focus outline after a mouse click followed by JavaScript `.focus()`.

The application uses `:focus-visible`. Pointer-triggered programmatic focus does not reliably activate that keyboard-specific selector. Changed the test to use actual **Tab → Tab** navigation from the evidence disclosure, first entering the scroll region and then the first period summary. The existing focused-element, visible-outline and Enter/Escape assertions remain enforced. No application styling or authorization was weakened to make the tests pass.

Reran the entire suite, not just the failing cases: **25 passed, 0 failed, 0 skipped, 0 flaky**, approximately 67.8 seconds. First-run JSON, environment and isolation evidence are preserved in `logs/student-regression/first-run/`.

## Database isolation and cleanup

Only new, random, token-owned fixture databases were created and mutated. The runner passes an explicit database environment override; the loopback router verifies namespace and ownership token before dispatch, permits only allowlisted routes, and blocks non-login POSTs. Cleanup verifies ownership before dropping its exact generated name.

- First run: `udm_radar_analytics_mvp_5e1dec4ecdc4ee38` — deletion verified.
- Final run: `udm_radar_analytics_mvp_86762b44104001d4` — deletion verified.
- Final fixture contains 18 application tables plus its ownership marker. No owner dataset was imported into it.
- Final fixture before/after data hashes are identical: `007a79a953e483af2ae663771e88cac7d54e8d5ec39d231c2ca6ba446b832a4a`.
- Canonical bootstrap file SHA-256 remains `adc6786008fa9e153fafd102b62061cb0c1e2cf97fde8f8a644e27db15c91427`.
- No test connected to or mutated the installed `udm_radar`, `udm_radar_large_seed_demo` or retained owner-review databases.

Protected/shared-path diff checks found no current changes in the canonical schema/migrations, grading constants, shared analytics helpers, auth/header/sidebar, model artifacts or dependency manifests. Staging was empty. Whitespace checks passed. Existing unrelated work and `.a` were preserved.

## Commands and evidence

The seven PHP scripts under `tests/unit/` were executed with `php`; calculator JavaScript used `node tests/unit/goal_calculator_feasibility_test.cjs`. Per-script outputs and exit codes are in `logs/student-regression/`.

Authenticated browser command: `node tests/fixtures/run_student_trend.mjs`. Final results, code fingerprints, actual browser version, unchanged fixture hashes and cleanup proof are in `logs/student-trend/browser.json`, `environment.json`, `browser-version.txt`, `data-integrity.json` and `lifecycle.json`.

## Remaining gates

1. Manual original-account login with a confirmed original password, or a separately authorized account-specific reset. Existing password hashes were not altered by these checks.
2. Owner retest of the latest actual localhost candidate and its installed records, including any remaining selection and write-workflow scenarios under approved isolation.
3. Manual accessibility checks beyond the demonstrated keyboard/label/geometry behaviors, such as actual screen-reader and comprehensive contrast review.
4. PDF workflow remains set aside and blocked by the approved private workbook/storage prerequisites.
5. Owner acceptance and separately authorized commit/push/PR/merge. No publication or implementation of Academic Review/Dashboard proposals was performed.

## Subsequent scoped refinement — 29 October 2026

After the full regression run documented above, the user approved a Student-only Academic Review historical-chart spacing adjustment in shared analytics JS/CSS. Twelve focused browser checks passed for Student desktop/theme geometry, compact period labels/full tooltips, unchanged Admin chart configuration and Faculty empty-data fallback. The earlier full 25-test result is not a new certification of these later shared asset bytes. Syntax/whitespace passed. A separately approved college-only correction on mock account 23-22-016 restored provisional planning eligibility without changing grades or passwords; see OWNER_DATASET_SETUP_REPORT.md. No preview/PDF result is claimed.

## Latest complete browser rerun after Academic Review spacing — 29 October 2026

User requested rerunning the complete Student suite after the shared analytics JS/CSS refinement. Command: node tests/fixtures/run_student_trend.mjs. Exit 0. Final result: 25 passed, 0 failed, 0 skipped, 0 flaky; duration approximately 73.2 seconds. This supersedes the previous note that the complete suite had not been rerun for the new Academic Review spacing assets. The earlier focused 12 shared-role checks remain separate evidence.

Target udm_radar_analytics_mvp_ddaeeec5c89b4297 was newly owned and disposable. Fixture before/after hashes match, database deletion was verified and canonical schema remained byte-identical. Installed udm_radar was not used. Current analytics JS/CSS and chart/test fingerprints were additionally recorded in logs/student-regression/latest-academic-spacing-files.json because the runner's original environment list does not include those shared analytics assets. Final report and cleanup evidence remain in logs/student-trend; prior browser evidence was preserved in logs/student-regression/pre-academic-spacing-rerun. Current JS syntax and whitespace passed. PHP/JavaScript unit suites were not rerun during this browser-only checkpoint; their earlier passing results above remain categorized separately.

No source changes were needed for this rerun. No commit, push, password change or new database correction was performed. Original-password/manual owner acceptance, PDF deferral and explicit publication gates remain.
