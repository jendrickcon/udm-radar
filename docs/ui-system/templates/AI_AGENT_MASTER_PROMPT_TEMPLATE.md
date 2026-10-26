# AI agent master prompt template

Use only after owner approves the exact member work package. This prompt is preparation, not permission to execute future production work.

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


## Startup — report before editing

Read actual applicable AGENTS, contribution rules, canonical roadmap/history, UI governance/findings, this package and relevant discovered skills. Report:
1. Member name.
2. Assigned portal scope.
3. Assigned branch.
4. Actual local branch, absolute clone/served path and HEAD.
5. Current origin/main full SHA.
6. Actual Git status.
7. Expected literal changed files.
8. Each shared file and assigned integration custodian.
9. Protected systems/non-goals.
10. Own self-verification tests.
11. Cross-portal regressions.
12. Stop conditions.

Confirm correct branch and shared custody before editing. Save only authorized work; unexpected dirty work blocks switching. Fetch/update clean main fast-forward, return/integrate through the approved method, record clean base. No automatic stash/reset/restore/clean/force-push.

## Execute and verify

Implement only assigned scope and literal approved files, reuse accepted helpers/API, and introduce no new design scope during bug fixes. Vincent self-tests Admin/Faculty; Member 2 self-tests Student. Use focused tests first, pinned installed runner, inspect all requests/includes/connections/cleanup; required unauthorized evidence stays BLOCKED. Do not run a broad suite that accesses existing DBs, mobile or screenshots.

Review each Git change layer. Record actual unit/integration/authenticated/structural/mocked/authorization/accessibility/syntax/integrity results separately. Inspect themes, keyboard/focus, exact table/plot parity, states and three desktop sizes. Cross-review is supplemental; findings do not transfer edit authority.

Optional AI task branches use own assigned HEAD in the same clone, e.g. `feat/member1-admin-faculty-ui--sidebar` or `feat/member2-student-portal--gwa-chart`; no slash child under an existing parent ref, other-member base or extra worktree without exception.

## Finish safely

After self-verification and explicit commit authority, produce immutable candidate and complete handoff for owner visible retest. Address verified in-scope defects through original implementer/custodian. Push/PR only after separate owner authorization, auto-merge disabled; merge and deploy separate. Both members update approved main after merge. No final completion report while required work remains unless a blocked handoff was explicitly requested.

## Retained limits

Cohort numeric series, MTR-07 bounds, canonical MTR-21 and historical Faculty attribution remain BLOCKED without their approved evidence/contracts. Member 2 owns Student UI and its eleven-item cohort proposal; no pooled Admin mean, fabricated number or invented privacy threshold. Keep “Your recorded GWA” and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.”

MTR-19 is a bounded receipt count; limited parent-case median is not MTR-21. MTR-20 remains approved arithmetic mean, displayed “Average time before the first notice” with human-readable duration and actual eligible n. No ranking/teaching-quality/effectiveness or official-standing inference. Use “Prototype performance on synthetic academic records”.

Current readiness preparation authorizes no production/DB/migration/Migration 011/model/dependency/auth edits, mobile tests, screenshots or publication. Frozen PR #63 files remain unchanged. Future runtime targets/tests require their own explicit scope and side-effect review. These amendments await local-commit approval; earlier `ee4ddf4b5e001328a1beeeea3471d58dd87c1614` does not include them.

See [ownership](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [Definition of Done and matrix](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md), [workflow](../08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md), [roadmap](../12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) and [handoff standard](../14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md).
