# Member portal work package template

Package ID:
Owner decision/date/evidence:
Documentation status: PLANNED / READY FOR OWNER REVIEW as actually observed.
Implementation status: NOT STARTED until authorized work begins.
Choose the approved exact package:
- Vincent: `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION`.
- Joshua: `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION`.

## Required package context

- Product goal: coherent, understandable, role-appropriate analytics across Administrator, Faculty and Student; cite the [ten goals](../01_VISUAL_DIRECTION_AND_BOUNDARIES.md).
- User problem and visible outcome:
- Portal owner: Vincent = Administrator/Faculty; Joshua = Student. Record actual name.
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


## Exact allowlist and shared custody

| Literal path | Portal | Sole editor/custodian | Approved change | Candidate/integration order | Regression evidence |
| --- | --- | --- | --- | --- | --- |
| Fill from verified source | | | | | |

No simultaneous shared CSS/JS/header/sidebar/API/fixture/doc edits. New backend field needs a documented presentation contract, one technical owner and downstream regressions.

## Self-verification and acceptance plan

| Category | Exact command/method | Side effects/authorized target | Required expected behavior | Actual totals/result | Evidence |
| --- | --- | --- | --- | --- | --- |
| Unit / PHP regression | | | | Not Run | |
| Isolated integration | | | | Not Run | |
| Authenticated desktop | | | | Not Run | |
| Authorization negatives | | | | Not Run | |
| Structural / mocked error-race | | | | Not Run | |
| Accessibility / keyboard / themes | | | | Not Run | |
| Cross-portal regressions | | | | Not Run | |
| Lint/syntax / Git/protected / cleanup | | | | Not Run | |

1440×900, 1280×600, 1024×768; light and dark for each owned portal. Verify one main scroll owner, no page horizontal overflow, chart/table parity and critical limits. Missing required evidence blocks acceptance. Include full 17-item Definition of Done by reference; no test-total accuracy percentage.

## Delivery and decisions

Implement → self-verify → authorized immutable local commit → handoff → owner retest/fixes → separately authorized push → one reviewed PR against current main → authorized merge → authorized deployment. Both members update main after merge. No cleanup/branch deletion by default.

Unresolved decisions, stop conditions, safe continuation:
Owner-supplied completion phrase:
Owner-supplied blocked-handoff phrase:

## Retained limits

Cohort numeric series, MTR-07 bounds, canonical MTR-21 and historical Faculty attribution remain BLOCKED without their approved evidence/contracts. Joshua owns Student UI and its eleven-item cohort proposal; no pooled Admin mean, fabricated number or invented privacy threshold. Keep “Your recorded GWA” and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.”

MTR-19 is a bounded receipt count; limited parent-case median is not MTR-21. MTR-20 remains approved arithmetic mean, displayed “Average time before the first notice” with human-readable duration and actual eligible n. No ranking/teaching-quality/effectiveness or official-standing inference. Use “Prototype performance on synthetic academic records”.

Current readiness preparation authorizes no production/DB/migration/Migration 011/model/dependency/auth edits, mobile tests, screenshots or publication. Frozen PR #63 files remain unchanged. Future runtime targets/tests require their own explicit scope and side-effect review. Preserved readiness `648207bc563f2bb1aa280d08ab9699f07a4f39c7` is owner-accepted. This final reconciliation is authorized for a local documentation commit only; no push or PR.

See [ownership](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [Definition of Done and matrix](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md), [workflow](../08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md), [roadmap](../12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) and [handoff standard](../14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md).

## Accepted MVP and refinement entry gate

Merged/deployed baseline: bdb4a267f30aa31ea36983304a03b01dc28f0fcd, PR64. Name the specific post-MVP refinement or verified defect and its accepted behavior before editing. Existing tabs/comparison cards/growth/real terms/exact tables/support/dashboard summaries/Student own trend are regression scope, not work to implement again. The collapsible desktop sidebar is still NOT STARTED.

Vincent owns Administrator/Faculty; Joshua owns Student. Both self-verify. Start from approved current main containing PR64 and accepted docs; read [database onboarding](../../../database/README.md). Bootstrap schema and synthetic seed are separate; fresh member-owned setup only, no SQL execution by documentation preparation. Migration011, MTR07 empirical range, canonical MTR21, historical Faculty attribution and numeric Student cohort remain BLOCKED.

PR63 remains open; its three status files are outside this package. The current final reconciliation is explicitly authorized for local commit; no push/PR or automatic PR63 merge/closure. Future implementation/publication permissions remain separate.
