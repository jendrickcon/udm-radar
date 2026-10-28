# Vincent Admin/Faculty acceptance checkpoint

29 October 2026, Asia/Manila. Package `MEMBER1-VINCENT-ADMIN-FACULTY-PORTALS-WP2`.

**Status: READY FOR OWNER REVIEW.** Automated implementation/self-verification is complete for the approved refinements. Overall Phase C remains IN PROGRESS until team-leader/owner visible acceptance. Joshua's latest-branch integration is **DEFERRED at the owner's explicit request**.

## Delivered scope

The current design is preserved. This checkpoint includes the approved three-portal collapsing sidebar, active-stripe removal and Faculty person-with-board icon; the earlier dashboard theme correction; Admin/Faculty role-appropriate comparison cards; keyboard/focus/empty-state fixes; accurate filter status and reload persistence; theme contrast and chart preference synchronization; and exact overview chart data/fallbacks. See the [first refinement report](VINCENT_PHASE_C_REFINEMENT_REPORT.md) for its historical 69-check checkpoint.

The acceptance closure found and fixed two additional defects:

1. **Faculty comparisons across different subjects calculated an incompatible difference.** The page displayed a warning but still showed a point/relative difference. A failing controlled report reproduction now passes: different subjects show “Change unavailable”; a valid same-subject comparison retains its correct difference. No academic formula changed.
2. **Anonymous access to the legacy comparison route failed before redirecting to login.** `BASE_URL` was undefined in the auth guard because constants had not loaded. Initial response was HTTP 200 containing a fatal error instead of the expected redirect. Loading existing constants first restores the login redirect; auth rules and the auth implementation are unchanged.

An existing academic-outcome regression also targeted a historical aggregation block removed from the Admin page in PR64. It now checks the current canonical analytics denominator against the unchanged PDF outcome counts, preserves the expected pass/fail/INC assertions and Faculty trend checks, and reports missing extraction boundaries clearly. This was a test compatibility repair, not a change to academic semantics.

## Acceptance matrix

| Requirement | Outcome | Evidence and boundary |
| --- | --- | --- |
| Existing setup/schema gate | Passed | Historical setup PASS; fresh read-only fingerprints confirm the same 18 application tables and 3 synthetic students |
| Admin/Faculty normal login and role checks | Passed | Real synthetic UI logins, anonymous API denial, foreign workspace redirects and actual-account role verification |
| Current assigned subjects/sections | Passed | Both Faculty accounts; exact Admin-filtered summary parity; concrete foreign-only subjects and nonexistent section return no records |
| Own-referral and safe drill-down | Passed | Admin case and Faculty01 own referral opened through actual analytics links; Faculty02 cannot view that referral; malformed IDs rejected |
| Comparison/chart/table parity | Passed | Actual trend/overview tables and Chart.js integration; controlled canonical reports verify GWA cards/tables and support flow/table values |
| Comparable growth/change | Passed | Complete +0.50/+20.0% fixture, incomplete/unavailable change, mixed scopes withheld, valid same-subject difference, no historical Faculty attribution |
| Support metric semantics | Passed | Hand-checked 1/2/9-hour durations mean 4.0h at n=3; invalid/unacted/reversed exclusions; visible n=1/2 caution; bounded receipts and distinct limited parent median/MTR21 wording |
| Complete/partial/provisional/empty/unavailable/single/gap states | Passed | Pure in-memory service reports rendered in authenticated pages; real empty scopes also checked; fixture evidence does not establish live authorization |
| Keyboard/focus and reduced motion | Passed | Hover/toggle/Escape, tab arrows/Home/End, panel reachability, selector replacement focus, unavailable-card removal/recovery, existing reduced-motion behavior |
| Themes and desktop geometry | Passed | Light/dark at 1440×900, 1280×600, 1024×768; CCS/Classic control contrast; enlarged text at 1024; one main page scroll owner |
| Async/error/cache behavior | Passed | Real cache reproduction; controlled abort/latest-response, failed-load retry and missing-chart fallback; URL publishes successful scope only |
| Shared Student regression | Passed | Existing sidebar/navigation and own-record analytics seam only; Student redesign/cohort work is outside this checkpoint |
| Database/protected integrity | Passed | All 18 tables' row counts/content hashes unchanged across the acceptance run; canonical SQL/model fingerprints unchanged; pre-existing absent model-metrics file remains absent |
| Unit/integration/browser/source/syntax checks | Passed | Actual categorized totals below; no screenshot claims |
| Disposable DB creation/cleanup | Not applicable | No database was created, seeded or mutated; integration uses an explicit read-only transaction against the authorized synthetic local target |
| Desktop preference/ResizeObserver proposals | Not applicable to chosen implementation | Owner chose a default minimized hover rail with transient state. Existing toggle/drawer, single initializer, settled resize event and cleanup are verified; no new persistence/observer contract is claimed |
| Joshua's latest branch integration | Deferred | Owner said “hold that off for now”; previously approved-main/shared Student regressions remain available, no teammate merge attempted |
| Team-leader/owner manual acceptance | Pending | [Concrete retest checklist](VINCENT_TEAM_LEADER_RETEST.md); automated checks do not represent the human's acceptance |
| PR/merge/deployment | Not run | Separate owner decisions; this checkpoint delivers only the authorized verified branch |

## Final verification

**Final affected browser result: 100 passed, 0 failed, 0 skipped (3.7 minutes).**

| Evidence category | Executed browser cases |
| --- | ---: |
| Fully live authenticated/read-only | 34 |
| Authenticated pages with controlled reports, timing or failures | 31 |
| Generated component/browser fixtures | 33 |
| Faculty source-structure checks | 2 |

PHP helper/contract regression checks: **385 passed, 0 failed** across six existing scripts; includes source-extracted PDF/Faculty blocks, not only function unit assertions. JavaScript checks: **21 passed, 0 failed** (19 display-preference contracts and 2 CLI failure-path checks). Read-only actual repository integration: **31 passed, 0 failed**. Syntax, whitespace and protected-file/diff checks: Passed.

The database integration explicitly opts into `udm_radar`, sets the session transaction read-only and refuses other targets. It cannot run through HTTP, performs no writes, imports, migrations or cleanup, and hashes application rows without exporting their values. The older random disposable-database suite was not run because its mutation target is outside this package's authorization.

The existing Faculty screenshot case is deliberately excluded. No screenshots, videos, traces, academic POSTs or exports were generated. Existing previously authorized generated sidebar/Faculty responsive regressions are retained; no new live mobile validation is claimed.

## Safe developer reproduction

Run from the repository root with PHP/Node on PATH, installed Edge and the repository-pinned Playwright 1.63.0. Use only the existing synthetic local setup. Stop on missing tools/credentials/database; do not substitute automatic installs or a database rebuild. Replace the password placeholders from the documented seed; these environment values apply only to the current process and children.

```powershell
node node_modules/@playwright/test/cli.js --version
$env:PHASE_C_BASE_URL='http://localhost/udm-radar/'
$env:PLAYWRIGHT_BASE_URL=$env:PHASE_C_BASE_URL
$env:SIDEBAR_LIVE_BASE_URL=$env:PHASE_C_BASE_URL
$env:PHASE_C_ADMIN_ID='admin'
$env:PHASE_C_ADMIN_PASSWORD='<documented synthetic Admin password>'
$env:PHASE_C_FACULTY_ID='faculty01'
$env:PHASE_C_FACULTY_PASSWORD='<documented synthetic Faculty password>'
$env:PHASE_C_FACULTY_OTHER_ID='faculty02'
$env:PHASE_C_FACULTY_OTHER_PASSWORD=$env:PHASE_C_FACULTY_PASSWORD
$env:PHASE_C_STUDENT_ID='24-00-001'
$env:PHASE_C_STUDENT_PASSWORD='<documented synthetic Student password>'
$env:SIDEBAR_ADMIN_ID=$env:PHASE_C_ADMIN_ID
$env:SIDEBAR_ADMIN_PASSWORD=$env:PHASE_C_ADMIN_PASSWORD
$env:SIDEBAR_FACULTY_ID=$env:PHASE_C_FACULTY_ID
$env:SIDEBAR_FACULTY_PASSWORD=$env:PHASE_C_FACULTY_PASSWORD
$env:SIDEBAR_STUDENT_ID=$env:PHASE_C_STUDENT_ID
$env:SIDEBAR_STUDENT_PASSWORD=$env:PHASE_C_STUDENT_PASSWORD
node node_modules/@playwright/test/cli.js test tests/browser/faculty-dashboard-layout-regression.spec.js tests/browser/admin-faculty-refinements.spec.js tests/browser/admin-faculty-acceptance.spec.js tests/browser/desktop-sidebar.spec.js --grep-invert 'Capture required screenshots' --reporter=line
if($LASTEXITCODE){throw 'Browser acceptance failed'}
```

Run the approved nonmutation contracts separately:

```powershell
@('post_defense_analytics_test.php','analytics_review_regression_test.php','analytics_fixture_contract_test.php','academic_outcome_contract_test.php','gwa_helpers_test.php','prediction_source_helpers_test.php') | ForEach-Object {
    php "tests/unit/$_"
    if($LASTEXITCODE){throw "PHP contract failed: $_"}
}
node tests/unit/display_preferences_contract_test.js
if($LASTEXITCODE){throw 'Display preference contracts failed'}
node tests/unit/admin_faculty_acceptance_runner_test.cjs
if($LASTEXITCODE){throw 'CLI failure guards failed'}
$env:PHASE_C_READ_ONLY_DB='udm_radar'
php tests/integration/admin_faculty_read_only_acceptance_test.php
if($LASTEXITCODE){throw 'Read-only repository acceptance failed'}
```

The failure probes use an unavailable loopback endpoint and dummy database/user values; they never query application records. The actual read-only test requires its explicit opt-in, sets the session read-only and confirms the exact database before reading. A completion guard returns nonzero even when shared DB bootstrap terminates early. Successful fingerprint mode is explicitly completed as well.

## Independent review closure

One fresh source reviewer found no production blocker, one Important future false-green CLI path, and a Minor missing reproduction-command block. The CLI issue was reproduced as exit 0 on unavailable DB, then fixed with a completion/shutdown guard without changing shared DB behavior. Both negative runner checks passed and the healthy 31-check integration passed after the repair. The promised command block above completes the handoff documentation. No second review or independent runtime claim is substituted for this verification.

Reviewer exclusions remain explicit: human/WCAG/institutional acceptance, Joshua's held integration, blocked academic contracts and final executor-owned Git delivery are not certified by source review.

## Review and delivery

Branch: `feat/member1-admin-faculty-ui`. Starting committed SHA: `3ccbc89ad2ca462c211464db83a6c7f22afff8af`. Approved-main baseline: `60a6f7614b594cd8759a3c9211e060c3b8d7ab62`.

The owner authorized finishing the checklist, including a verified checkpoint commit/push. The exact final commit SHA, verified remote match and final Git status are returned in the delivery message/report after Git operations; they cannot be embedded as this commit's own SHA. A final reviewer checks the combined implementation and this closure package before staging. No main edit, force-push, PR, merge or deployment is included.

Known boundaries remain: the original Admin overview is a visibly labeled page-open snapshot; full WCAG/institutional/genuine-record certification is not claimed; numeric cohort, empirical prediction ranges, undated historical Faculty attribution, canonical referral closure and Migration 011 remain blocked. Local chart-library/offline availability improvements are additional scope, not unfinished work invented by this checkpoint.

**VINCENT ADMIN AND FACULTY PORTAL PACKAGE READY FOR OWNER REVIEW**
