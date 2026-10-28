# Portal test and acceptance matrix

Documentation preparation baseline: READY FOR OWNER REVIEW as of 27 October. Current Vincent implementation/acceptance is recorded in the dated checkpoint below.
Roadmap statuses use the seven allowed values in the [phase plan](12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md). Test outcomes are recorded separately as Passed, Failed, Skipped, Not Run or Unavailable. A test existing does not mean it ran. No future member self-verification row is marked executed by this documentation pass. Most listed Admin/Faculty/Student analytics components already ship in PR64; those rows are preservation/regression gates for later approved refinements, not assignments to implement them again. Only the desktop collapsible rail and explicitly approved new refinements remain implementation proposals.

## Current Vincent acceptance evidence — 29 October 2026

Approved implementation/self-verification is READY FOR OWNER REVIEW. The future-member rows below are the 27 October preparation matrix, not an assertion that current Vincent code is unimplemented. The [current item-by-item acceptance matrix](../../VINCENT_PHASE_C_ACCEPTANCE.md) records actual outcomes and limits.

- SID-01/02/05, ADM-01–05, SUP-01–03, FAC-01/02, CH-01/02, THEME-01, A11Y-01, ASYNC-01, DESK-01 and affected REG/GOV scope have categorized evidence: 100 passed, 0 failed, 0 skipped, 385 PHP checks, 21 JavaScript checks, 31 read-only integration checks and integrity/syntax results.
- SID-03 persistence and SID-04 observer proposals are not imposed on the owner-chosen transient hover implementation. Existing default rail, named toggle/drawer, one guarded initializer, settled/coalesced resize and cleanup remain tested. The acceptance report records the implementation alternative rather than inventing a storage/observer pass.
- FAC-03, COH-01, EVID-01 remain BLOCKED for their academic contracts; no numeric/historical inference or Migration 011.
- GOV-03 disposable creation/cleanup is not applicable to this read-only closure: no target was created or mutated. The older random-database suite was not run under this package.
- Owner/team-leader visible retest remains pending; the latest Joshua branch integration is DEFERRED at owner request. Commit/push is authorized for the verified checkpoint; PR/merge/deployment are separate owner decisions.

## Historical preparation matrix — 27 October 2026

## Mandatory self-verification

Vincent executes Administrator AND Faculty checks. Joshua executes Student checks. Each also verifies cross-portal seams affected by their work. A cross-reviewer supplements the implementer; do not assign all tests to the other member.

| ID | Requirement and required future evidence | Implementer and self-verifier | Future member verification status |
| --- | --- | --- | --- |
| SID-01 | Expanded icon/label, current route, named toggle, collapsed names/badges/footer, focus remains usable | Vincent Admin/Faculty; Joshua Student; custodian for global files | NOT STARTED |
| SID-02 | Released width, chart resize, no clipped values/controls; one primary page scroll owner | Each portal implementer | NOT STARTED |
| SID-03 | Valid/invalid/denied local preference storage; desktop preference separate from existing drawer; no academic identifiers stored | Each portal implementer | NOT STARTED |
| SID-04 | One initializer, settled event, coalesced ResizeObserver, hidden-panel refresh, cleanup/remount/pagehide | Each portal implementer; one global custodian | NOT STARTED |
| SID-05 | Faculty warning-banner closes before advisory/KPI/section/roster/performer cards; run faculty-dashboard-layout-regression.spec.js for affected layout | Vincent; Joshua also runs when its shared changes affect Faculty layout | PLANNED |
| ADM-01 | Admin login/access; single Program Analytics destination and original Overview; tabs, dashboard summaries and natural drill-down | Vincent | NOT STARTED |
| ADM-02 | ≤4 primary controls where possible, Advanced, active collapsed scope, parent/child filters, Apply/Reset; support controls only in Support | Vincent | NOT STARTED |
| ADM-03 | Two comparison groups by default, third optional, no fourth; overlay/separate cards; year/semester/curriculum/subject/section/verified Faculty scope | Vincent | NOT STARTED |
| ADM-04 | Real ordered histories ≥2 points use line; one-period uses scalar bar/dot. Mixed context cards allow no incompatible delta | Vincent | NOT STARTED |
| ADM-05 | Growth: approved point/relative, coverage/n changes, exact periods, neutral direction; incompatible scopes show “Change unavailable” + reason | Vincent | NOT STARTED |
| SUP-01 | Case/referral status and referral→notice→receipt visual/table parity; authorized read-only drill-down; no unnecessary raw Student identity in aggregates | Vincent | NOT STARTED |
| SUP-02 | MTR-20 “Average time before the first notice”, human-readable mean and actual eligible n; 1,2,9-hour contract mean 4.00h; preserve deduplication, unacted/invalid/reversed exclusions | Vincent consumes approved API; named shared technical custodian if needed | PLANNED |
| SUP-03 | MTR-19 bounded receipt count, no canonical rate claim; parent-case closed-duration median labeled separately from MTR-21; no effectiveness inference | Vincent | NOT STARTED |
| FAC-01 | Faculty login/current assignment; assigned subject/section controls; unassigned section denial, cross-Faculty denial, own-referral scope | Vincent | NOT STARTED |
| FAC-02 | Assigned-Class Analytics/dashboard/support parity, comparable-only trend/change, exact table values and authorized drill-down | Vincent | NOT STARTED |
| FAC-03 | Historical Faculty attribution unavailable without dated records; no ranking or teaching-quality conclusion | Vincent; shared contract custodian | BLOCKED for historical attribution |
| STU-01 | Student login/own-record scope, deny another Student identifier across page/API/parameters | Joshua | NOT STARTED |
| STU-02 | Current recorded-term GWA, previous comparable term, units/exclusions, point direction, relative change/nonzero baseline, missing/incomplete/summer rules | Joshua | NOT STARTED |
| STU-03 | Real school-year/semester labels, chronological gaps, complete/empty/unavailable/incomplete model, no smoothing or fabricated zeros | Joshua | NOT STARTED |
| STU-04 | Prediction numerical estimate/source/coverage, advisory risk distinct from official standing; unsupported range remains unavailable | Joshua | NOT STARTED |
| STU-05 | Dashboard/Academic Analytics, Goal Calculator navigation and regression, Grades/history, Enrollment Planning links/wording | Joshua | NOT STARTED |
| STU-06 | “Your recorded GWA” plus visible blocked cohort explanation; no numeric cohort or enable flag; pooled Admin mean never relabeled | Joshua | NOT STARTED for unavailable interface |
| COH-01 | All eleven Student unit/cohort/eligible/excluded/missing/privacy-size/weighting/period/role/synthetic/fairness items and final owner approval | Joshua proposes and verifies; owner approves | BLOCKED |
| EVID-01 | MTR-07 range without active-model residual contract; canonical MTR-21 referral closure without lifecycle fields; Migration 011 excluded pending separate approval | Separate future approved owners | BLOCKED |
| CH-01 | Exact accessible table below plot: values/precision/population/unit/period/availability match; fallback without Chart.js; full tooltip period names | Each portal implementer | NOT STARTED |
| CH-02 | Visible critical n/coverage/limits and non-color legend; extended formulas/policy in accessible disclosure | Each portal implementer | NOT STARTED |
| THEME-01 | All cards/controls/tables/canvas/axes/legends/tooltips/badges/empty/details in light/dark, no pure-white dark cards; CCS/classic/preferences preserved | Each portal implementer; each portal checked separately | NOT STARTED |
| A11Y-01 | Native labels, truthful expanded/current/tab semantics, no hidden focus, keyboard/visible focus, enlarged text, reduced motion and focus return | Each portal implementer | NOT STARTED |
| ASYNC-01 | Scope busy/status/errors, no stale values, abort/latest response wins, filter race/malformed response/retry behavior | Each portal implementer; evidence category explicit | NOT STARTED |
| DESK-01 | No page horizontal overflow, one main scroll, visible controls/focus at 1440×900,1280×600,1024×768 in both themes | Each portal implementer | NOT STARTED |
| REG-01 | Shared auth/header/sidebar/API; other portal links; Goal Calculator, PEF/CC, Activity/Inbox, Grades/Admin focus, academic outcome/prediction helper regressions as affected | Both self-verify their impact | PLANNED |
| GOV-01 | Correct approved branch/base, literal file allowlist, one shared-file custodian and sequential integration | Each member | PLANNED |
| GOV-02 | Protected dump/models/dependencies/auth/settings/schema unchanged absent explicit scope; no Migration 011, no genuine records | Each member | PLANNED |
| GOV-03 | Authorized disposable integration target only, no existing-DB access/fallback, provenance and verified cleanup | Each member if future authorization exists | BLOCKED pending separate authorization |
| GOV-04 | Separate unit/integration/authenticated/structural/mocked/authorization/accessibility/syntax/integrity results; no combined accuracy claim | Each member | PLANNED |
| GOV-05 | Clean diff, immutable candidate if commit authorized, owner retest, separate push/PR/merge/deployment gates | Each member | PLANNED |

## Definition of Done

## Definition of Done — required for each portal package
A package is not done because its page renders. Both Vincent and Joshua must supply:
1. Implemented approved scope on the assigned branch.
2. Passing portal-specific unit checks.
3. Passing relevant integration checks under separately authorized isolated targets.
4. Passing authenticated desktop workflows.
5. Passing negative authorization tests.
6. Passing light and dark themes, including cards, axes, legends, tooltips and details.
7. Passing keyboard and visible-focus checks.
8. No page horizontal overflow at 1440×900,1280×600,1024×768; one primary page scroll owner.
9. Exact accessible tables matching chart values, populations, units and periods.
10. Complete, empty, provisional, unavailable and noncomparable states tested.
11. Cross-portal regressions for affected shared contracts and navigation.
12. Protected-file integrity evidence.
13. No unauthorized database/schema/migration work.
14. Clean clone/worktree and complete diff review.
15. Full immutable candidate SHA and complete evidence handoff, when commit authority exists.
16. Owner retest of visible workflows, with defects resolved before acceptance.
17. Separate push, PR, merge and deployment authorization; self-verification grants none automatically.
Unavailable required evidence keeps the gate BLOCKED; inapplicable tests require a reason and owner acceptance, not an invented pass. Report PHP/SQL/authenticated/generated/mocked/structural/accessibility/syntax/integrity separately. Cross-review does not replace self-verification; fixes return to the portal implementer unless the owner explicitly transfers them.


## Safe focused execution order for future approved work

Review exact clean candidate/base and all test side effects first. Verify installed repository-pinned Playwright version; no auto-install. Run safe structural/contract checks, authorized isolated integration, authenticated portal and negative authorization, generated/mocked race/error, keyboard/theme/desktop cases separately. Inspect tests selected by name/project; exclude mobile and all screenshot calls.

Run affected cross-portal regressions, PHP lint/JS syntax, whitespace and protected-file/change-layer checks, then verify temporary cleanup. Run broader relevant checks only where authorization and side effects permit; required unrun evidence blocks acceptance with its reason. Source checks do not establish live auth/layout or accessibility.

## Current preparation evidence only

The [reference suite](reference/reference-contract.test.mjs) checks identifier-free model validation, blocked cohort, line versus single-value encoding, gaps, precision and lifecycle stubs. Its actual results live in the external report. JS syntax, links, UTF-8/LF and integrity checks also concern documentation/reference only. No database, authenticated browser, production structural regression, screenshot or mobile test runs in this preparation.

## Accepted MVP versus future verification

Rows ADM/SUP/FAC/STU/CH/THEME/ASYNC describe preservation and verification of merged PR64 behavior during later approved refinements. Their future member verification status does not mean those production features are absent. SID collapsed-rail cases concern a proposed desktop improvement, not a shipped collapsible sidebar.

Current documentation evidence reruns only local links/ownership/status/text integrity, inert reference contracts and JS syntax. Historical PR64 reported test categories are not newly executed or combined with these reference results. Database onboarding is source-inspected only; no fresh-import or authenticated UI pass is claimed.

Accepted runtime baseline: PR64 merge bdb4a267f30aa31ea36983304a03b01dc28f0fcd; see the current context and phase plan.
