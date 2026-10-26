# Portal change handoff template

Issue after completed package, or explicitly requested blocked handoff.
Package ID/date:
Actual documentation and implementation statuses:
Outcome and owner-supplied phrase:

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


## Exact change record

| File | Implementer/custodian | Reason and contract | Source candidate SHA | Tests | Remaining issue |
| --- | --- | --- | --- | --- | --- |

Git layers reviewed separately: committed / unstaged / staged / untracked / combined.
Actual `git status --short --branch`:
Clean status confirmed by:
Authorized final full immutable SHA, or no-candidate reason:
Remote/PR state (do not imply publication if none):

## Evidence and owner retest

Attach [categorized evidence](UI_TEST_EVIDENCE_TEMPLATE.md); report actual passed/failed/skipped totals by category and commands. List three desktop sizes × both themes × owned portals; auth negatives, chart/table parity, missing/unavailable/noncomparable cases, shared regressions and keyboard/focus.
Owner tested workflow/candidate/date:
Findings fixed by original implementer:
Outstanding required evidence and acceptance gate:

## Safety and delivery

Protected-file/hash/diff results:
Database access/target/provenance/cleanup:
Migration 011/schema/models/dependencies/auth/genuine-record checks:
Frozen PR #63 and Antigravity boundaries:
Publication permissions actually granted, each separately:
Next exact owner action and safe continuation:
Cross-review author/fix disclosure and no unapproved scope expansion:

## Retained limits

Cohort numeric series, MTR-07 bounds, canonical MTR-21 and historical Faculty attribution remain BLOCKED without their approved evidence/contracts. Member 2 owns Student UI and its eleven-item cohort proposal; no pooled Admin mean, fabricated number or invented privacy threshold. Keep “Your recorded GWA” and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.”

MTR-19 is a bounded receipt count; limited parent-case median is not MTR-21. MTR-20 remains approved arithmetic mean, displayed “Average time before the first notice” with human-readable duration and actual eligible n. No ranking/teaching-quality/effectiveness or official-standing inference. Use “Prototype performance on synthetic academic records”.

Current readiness preparation authorizes no production/DB/migration/Migration 011/model/dependency/auth edits, mobile tests, screenshots or publication. Frozen PR #63 files remain unchanged. Future runtime targets/tests require their own explicit scope and side-effect review. These amendments await local-commit approval; earlier `ee4ddf4b5e001328a1beeeea3471d58dd87c1614` does not include them.

See [ownership](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [Definition of Done and matrix](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md), [workflow](../08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md), [roadmap](../12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) and [handoff standard](../14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md).
