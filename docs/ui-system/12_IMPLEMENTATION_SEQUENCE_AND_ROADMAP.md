# Implementation sequence and roadmap

## Goal and status authority

> Deliver a coherent, understandable, role-appropriate academic analytics experience across Administrator, Faculty, and Student portals, where charts and summaries explain current results, comparable periods, growth or change, coverage, sample size, support activity, and unavailable evidence without ranking Faculty, fabricating comparisons, or overwhelming users with technical text and controls.

Canonical accepted runtime/onboarding baseline: `bdb4a267f30aa31ea36983304a03b01dc28f0fcd`, merged PR #64 and owner-confirmed local deployment. Current documentation main: `c29d68a7ef961c0be61ffbf296a10918303d5fea`, after the owner-authorized PR63 merge and canonical fast-forward. Preserved readiness commit `648207bc563f2bb1aa280d08ab9699f07a4f39c7` remains an ancestor. This pass commits reconciliation locally only; publication requires a separate owner decision.

Allowed phase/readiness statuses: COMPLETE, IN PROGRESS, READY FOR OWNER REVIEW, PLANNED, BLOCKED, DEFERRED, NOT STARTED. Test outcomes remain separate. Documentation readiness does not complete member implementation.

## Current Vincent checkpoint — 29 October 2026

| Phase | Current checkpoint | Remaining gate |
| --- | --- | --- |
| C — Vincent Admin/Faculty | IN PROGRESS; approved implementation/self-verification READY FOR OWNER REVIEW | Team-leader/owner visible acceptance; no new feature backlog is invented from accepted PR64 scope |
| E — Shared integration | Existing approved-main/Student seams verified; latest Joshua-branch integration DEFERRED at owner request | Resume only when owner supplies/authorizes that integration |
| F — Institutional readiness | PLANNED; academic evidence contracts remain BLOCKED | Separate policy/data/owner packages; no Migration 011 |

The owner authorized a verified checkpoint commit/push for testing. The final delivery returns the exact immutable/remote SHA. No PR, merge, deployment or human acceptance is claimed. Final evidence and remaining gates: [acceptance](../../VINCENT_PHASE_C_ACCEPTANCE.md), [visible retest](../../VINCENT_TEAM_LEADER_RETEST.md).

The dated preparation tables below are historical; their NOT STARTED/sidebar statements do not describe Vincent's current local implementation. Joshua's broader Student status has not been independently re-evaluated during the requested hold.

## Historical verified status — 27 October 2026

| Item | Documentation/review status | Implementation/deployment status |
| --- | --- | --- |
| PR #62 bounded analytics | COMPLETE as accepted historical implementation | COMPLETE; merged at edef1b382b7874af770feb5713c20e9b68979241 |
| PR #64 analytics UI and onboarding refresh | COMPLETE as merged source; local deployment supplied by owner | COMPLETE at bdb4a267f30aa31ea36983304a03b01dc28f0fcd; local prototype deployment, not institutional UAT |
| PR #63 status documentation | COMPLETE; reviewed head96aa4d7a91616c31d38687f2e5851721fa7def82 merged normally | COMPLETE at c29d68a7ef961c0be61ffbf296a10918303d5fea; canonical checkout fast-forwarded |
| Preserved readiness package | COMPLETE as accepted local commit 648207bc563f2bb1aa280d08ab9699f07a4f39c7 | NOT STARTED for member improvements |
| Current documentation reconciliation | READY FOR OWNER REVIEW; local commit authorized | NOT STARTED for remote publication |
| Vincent Administrator/Faculty post-MVP package | PLANNED | NOT STARTED |
| Joshua Student post-MVP package | PLANNED | NOT STARTED |
| MTR-07, canonical MTR-21, historical Faculty attribution, Student cohort, Migration 011 | BLOCKED | BLOCKED |

PR #64 final head: `9f6ed109d968c47fe439eecc816f68ec0167694d`; merged tree `bdb4a267f30aa31ea36983304a03b01dc28f0fcd`. PR #63 reviewed head: `96aa4d7a91616c31d38687f2e5851721fa7def82`; merge: `c29d68a7ef961c0be61ffbf296a10918303d5fea`. Recheck live refs before publication; old moving Antigravity checkpoints are superseded by the merged commit.

## Historical phases A–F — 27 October 2026

| Phase | Status | Responsible party | Delivered baseline or remaining gate |
| --- | --- | --- | --- |
| A — Analytics UI MVP publication | COMPLETE | Antigravity implementation; owner accepted merged/deployed outcome | PR64 delivered single Admin Program Analytics/tabs/legacy redirects, original Overview, comparison cards and chart/details/table, growth/real periods, support flow, Faculty review parity, dashboard summaries and theme/layout work. Local deployment at bdb4a267f30aa31ea36983304a03b01dc28f0fcd is owner-confirmed. Do not reassign these as new member implementation. |
| B — Repository/member-readiness consolidation | IN PROGRESS | Sol prepares docs; owner decides readiness publication | PR63 resolution and canonical documentation fast-forward are COMPLETE. Align this existing branch locally with c29d68a7ef961c0be61ffbf296a10918303d5fea, preserve afb3bd8cc49abc0d51e49b45367c286f7b24e7a5, and rerun readiness checks. Readiness push/PR/merge remains pending separate owner authorization; shared custodians remain an implementation entry gate. |
| C — Vincent Admin/Faculty post-MVP refinements | NOT STARTED | Vincent | Approved file-specific refinements only: future collapsible desktop sidebar, measured density/spacing/usability improvements and verified portal defects. Preserve accepted MVP charts/tabs/support/filter behavior as regression scope. Reuse formulas; own unit/integration/auth/negative/theme/accessibility/layout tests and fixes, owner retest/handoff. Sidebar remains unimplemented. |
| D — Joshua Student post-MVP refinements | NOT STARTED | Joshua | Refine existing own-record Academic review/Dashboard/GWA history/change/prediction explanations only under approved package. Maintain Goal Calculator/Grades/history/Enrollment regressions; future Student sidebar/chart-label/legend improvements and cohort unavailable interface are proposals. Numeric cohort remains BLOCKED; existing SVG trend is not a new assignment. Own calculation/privacy verification, authorization, themes/accessibility/tests/fixes required. |
| E — Shared cross-portal integration | PLANNED | Both; one named custodian per exact shared file | Sequential changes to global CSS/JS/header/sidebar/API/fixtures, no duplicate formulas, cross-portal/theme/keyboard regressions and final owner retest. Accepted MVP components are preserved rather than rebuilt. |
| F — Broader institutional readiness | PLANNED | Separately authorized future owners | Genuine-record validation, institutional UAT, broader accessibility and mobile only if reauthorized; accepted MTR07 residuals, MTR21 lifecycle, dated assignments, cohort privacy/calculation approval and separate Migration011 approval. Blocked dependencies are not promoted by prototype delivery. |

The ten [product goals](01_VISUAL_DIRECTION_AND_BOUNDARIES.md) remain requirements and regression expectations. They do not automatically authorize further work on features already shipped.

## Definition of Done — both future portal packages

1. Approved post-MVP refinement implemented on assigned branch.
2. Passing portal unit checks.
3. Relevant integration checks under separately authorized isolated targets.
4. Authenticated desktop workflows.
5. Negative authorization tests.
6. Light/dark themes across all surfaces.
7. Keyboard and visible focus.
8. No page horizontal overflow at 1440×900,1280×600,1024×768; one primary scroll owner.
9. Exact accessible table/plot parity.
10. Complete, empty, provisional, unavailable and noncomparable states.
11. Affected cross-portal regressions.
12. Protected-file integrity.
13. No unauthorized database/schema/migration work.
14. Clean clone and complete diff review.
15. Authorized immutable candidate and full evidence handoff.
16. Owner visible retest and defect closure.
17. Separate push, PR, merge and deployment authorization.

Missing required evidence keeps acceptance BLOCKED. Cross-review supplements own tests; original portal implementer fixes defects unless a shared custodian or explicit owner transfer applies. Standalone PHP scripts are PHP regression checks, not PHPUnit. Report unit/integration/authenticated/structural/generated/mocked/authorization/accessibility/syntax/integrity evidence separately.

## Member starting baseline and blocked contracts

Vincent: `feat/member1-admin-faculty-ui`, Administrator/Faculty. Joshua: `feat/member2-student-portal`, Student. One clone each, optional same-clone AI task branch from own assigned HEAD; extra worktrees only owner exceptions. Start from current approved main containing bdb4a267f30aa31ea36983304a03b01dc28f0fcd and the later accepted documentation merges, rather than cloning an old readiness or implementation branch. See [onboarding workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md).

Schema bootstrap is separate from synthetic seed. Read [database/README.md](../../database/README.md); use fresh member-owned local setup only. No import, DB connection or migrations are executed by this pass.

MTR-07, canonical MTR-21, historical Faculty attribution, Student cohort and Migration 011 remain BLOCKED. MTR19 stays bounded receipt count, MTR20 arithmetic mean and parent-case median separate. Joshua's eleven cohort gates plus owner approval are required; no pooled Admin-mean substitution.

PR63 is merged and the readiness branch now integrates its accepted main. Next, obtain separate owner authorization for readiness push and one reviewed documentation PR. No push/PR, deployment, branch deletion or cleanup occurs in this alignment. See [current publication plan](16_REPOSITORY_ROADMAP_RECONCILIATION_PLAN.md).
