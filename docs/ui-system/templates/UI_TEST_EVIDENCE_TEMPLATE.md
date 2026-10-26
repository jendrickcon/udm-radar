# Portal self-verification evidence template

Member/portal/package:
Tested full candidate SHA or honest dirty-state fingerprint:
Date/runtime/pinned runner version:
Implementation and documentation statuses:
Do not prefill passing totals.

## Required package context

- Product goal: coherent, understandable, role-appropriate analytics across Administrator, Faculty and Student; cite the [ten goals](../01_VISUAL_DIRECTION_AND_BOUNDARIES.md).
- User problem and visible outcome:
- Portal owner: Vincent = Administrator/Faculty; Member 2 = Student. Record actual name.
- Assigned branch: `feat/member1-admin-faculty-ui` or `feat/member2-student-portal`.
- Exact scope, literal files and non-goals:
- Visual contract: chart/details hierarchy, exact table below, real periods, scalar versus history, two groups/optional third/no fourth, visible coverage/n/limits:
- Calculation contract: approved source/API/helper, unit/population/eligibility/exclusions/denominator/weighting/comparability:
- Growth/change: exact start/end periods, point and supporting relative change, coverage/sample-size change; “Change unavailable” + reason when noncomparable:
- Complete/empty/provisional/unavailable/error states and table fallback:
- Shared-file integration owner per exact path, editing order and API impacts:
- Self-verification matrix and cross-portal regressions, with actual results or required BLOCKED evidence:
- Baseline/current main/HEAD full SHAs, clone path, branch and actual status:
- Immutable candidate when explicitly authorized; otherwise state no candidate and reason:
- Owner visible retest, unresolved findings and acceptance decision:
- Separate commit, push, PR, merge and deployment approvals; auto-merge off:

Neither member is universal UI/backend owner or tester-only. Each implements, tests, verifies and fixes its own portals. Cross-review supplements self-verification; original implementer retains fixes unless shared-contract ownership or explicit owner transfer applies. One clone and assigned long-lived branch per human; same-clone AI task branches start from own assigned HEAD; extra worktrees require owner exceptions.


## Executions — keep categories separate

| Category | Exact command/method | Runtime/target/side effects | Actual passed | Actual failed | Actual skipped | Result / exit | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Unit / PHP regression checks | | | | | | Not Run | |
| Isolated SQL/API integration | | | | | | Not Run | |
| Authenticated desktop | | | | | | Not Run | |
| Structural/source | | | | | | Not Run | |
| Generated fixture browser | | | | | | Not Run | |
| Mocked transport/error/race | | | | | | Not Run | |
| Authorization negatives | | | | | | Not Run | |
| Accessibility/keyboard/themes | | | | | | Not Run | |
| Cross-portal regressions | | | | | | Not Run | |
| Lint/syntax | | | | | | Not Run | |
| Git/protected/temporary cleanup | | | | | | Not Run | |

Result vocabulary: Passed, Failed, Skipped, Not Run, Unavailable. Explain every required unrun category and mark its acceptance gate BLOCKED. Static source, fixture, mock and checksums cannot establish authenticated runtime behavior. Never combine categories into model accuracy or a single quality count.

## Desktop and state matrix

For each owned portal, document actual results at 1440×900,1280×600,1024×768 in light and dark. Check cards/controls/tables/canvas/axes/legend/tooltips/badges/empty/details, keyboard and visible focus, enlarged text/reduced motion, no page horizontal overflow, one main scroll owner, chart/table values, genuine period labels, ≤4 primary filters where possible, two/three group limits and unavailable/noncomparable states.

Vincent: Admin/Faculty scopes, unassigned/cross-Faculty denial, own-referrals, support count/mean/parent-median labels. Member 2: own Student/other-Student denial, canonical GWA/comparable change/coverage/missing/summer, prediction clarity, Goal Calculator/Grades/history/Enrollment regressions. Each runs affected other-portal/shared-auth/header/sidebar/API/PEF/CC/Activity/Inbox/focus regressions. Faculty shared layout requires its structural regression.

## Target and integrity

Pre-execution connection/include/subprocess/auth/mutation/lock/cleanup review:
Exact separately authorized isolated target and provenance:
No existing-DB fallback or genuine records:
Temporary cleanup verified:
Protected dump/models/dependencies/schema/Migration 011 diff:
Git committed/unstaged/staged/untracked/combined inspection:
Required failure/blocker and retest candidate:
Owner visible retest and publication decisions:

## Retained limits

Cohort numeric series, MTR-07 bounds, canonical MTR-21 and historical Faculty attribution remain BLOCKED without their approved evidence/contracts. Member 2 owns Student UI and its eleven-item cohort proposal; no pooled Admin mean, fabricated number or invented privacy threshold. Keep “Your recorded GWA” and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.”

MTR-19 is a bounded receipt count; limited parent-case median is not MTR-21. MTR-20 remains approved arithmetic mean, displayed “Average time before the first notice” with human-readable duration and actual eligible n. No ranking/teaching-quality/effectiveness or official-standing inference. Use “Prototype performance on synthetic academic records”.

Current readiness preparation authorizes no production/DB/migration/Migration 011/model/dependency/auth edits, mobile tests, screenshots or publication. Frozen PR #63 files remain unchanged. Future runtime targets/tests require their own explicit scope and side-effect review. These amendments await local-commit approval; earlier `ee4ddf4b5e001328a1beeeea3471d58dd87c1614` does not include them.

See [ownership](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [Definition of Done and matrix](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md), [workflow](../08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md), [roadmap](../12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) and [handoff standard](../14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md).
