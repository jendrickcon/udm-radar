# Owner decision template

Decision ID/date/requested by:
Exact decision needed:
Human owner and exact decision text (leave pending until received):
Candidate/contract versions and evidence reviewed:

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


## Scope of authorization

| Action or file | Permitted exact scope | Still prohibited | Condition / expiry / revalidation |
| --- | --- | --- | --- |
| Local commit | | | |
| Push / one reviewed PR | | | |
| Merge / deploy | | | |
| Shared-file custody transfer | | | |
| Isolated test resources | | | |
| Cleanup | | | |

Do not infer permission from silence, self-verification or an agent-generated plan. Current readiness amendments need new local documentation commit approval. No push/PR authorization follows from that commit.

## Shared-file transfer, if needed

Literal paths/current implementer/temporary custodian:
Reason and approved minimal edit:
Immutable source SHA/dirty disclosure:
Sequential integration order:
Cross-portal tests/API effect:
Return condition and original-owner acknowledgment:

Review findings normally return to original portal implementer. Exceptions: assigned shared contract, explicit owner transfer, or unavailable implementer with owner reassignment.

## Unresolved Student cohort gate

Member 2 verifies all eleven items in the [contract](../05_GWA_TREND_COMPARISON_CHART_CONTRACT.md); owner approves the exact data/privacy contract only after evidence. Record each eligible/excluded/missing/threshold/weighting/period/role/synthetic/fairness decision rather than blanket “enable”. A blocked interface approval does not approve numeric values or DB access.

Decision outcome/remaining blockers/owner visible retest:
Next exact action and revalidation trigger:

## Retained limits

Cohort numeric series, MTR-07 bounds, canonical MTR-21 and historical Faculty attribution remain BLOCKED without their approved evidence/contracts. Member 2 owns Student UI and its eleven-item cohort proposal; no pooled Admin mean, fabricated number or invented privacy threshold. Keep “Your recorded GWA” and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.”

MTR-19 is a bounded receipt count; limited parent-case median is not MTR-21. MTR-20 remains approved arithmetic mean, displayed “Average time before the first notice” with human-readable duration and actual eligible n. No ranking/teaching-quality/effectiveness or official-standing inference. Use “Prototype performance on synthetic academic records”.

Current readiness preparation authorizes no production/DB/migration/Migration 011/model/dependency/auth edits, mobile tests, screenshots or publication. Frozen PR #63 files remain unchanged. Future runtime targets/tests require their own explicit scope and side-effect review. These amendments await local-commit approval; earlier `ee4ddf4b5e001328a1beeeea3471d58dd87c1614` does not include them.

See [ownership](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [Definition of Done and matrix](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md), [workflow](../08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md), [roadmap](../12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) and [handoff standard](../14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md).
