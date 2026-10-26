# Implementation sequence and roadmap

## Goal and status rules

> Deliver a coherent, understandable, role-appropriate academic analytics experience across Administrator, Faculty, and Student portals, where charts and summaries explain current results, comparable periods, growth or change, coverage, sample size, support activity, and unavailable evidence without ranking Faculty, fabricating comparisons, or overwhelming users with technical text and controls.

This [visual direction](01_VISUAL_DIRECTION_AND_BOUNDARIES.md) defines ten goals; this sequence does not authorize execution. Repository authorities remain [roadmap](../ROADMAP.md) and [history](../IMPLEMENTATION_HISTORY.md); changes to those frozen files require the separate [reconciliation plan](16_REPOSITORY_ROADMAP_RECONCILIATION_PLAN.md).

Allowed phase/readiness statuses: **COMPLETE**, **IN PROGRESS**, **READY FOR OWNER REVIEW**, **PLANNED**, **BLOCKED**, **DEFERRED**, **NOT STARTED**. Evidence uses actual Passed/Failed/Skipped/Not Run/Unavailable separately. Completed documentation or passing reference checks never make a production phase COMPLETE.

## Current evidence snapshot — 26 October 2026

| Item | Documentation / review status | Implementation / acceptance status |
| --- | --- | --- |
| Certified PR #62 analytics MVP | COMPLETE, per owner post-merge/deployment acceptance history | COMPLETE; owner-reported MERGED AND DEPLOYED, not re-executed in this package |
| PR #63 status-only documentation | IN PROGRESS; verified OPEN, unmerged, auto-merge off, three frozen files | NOT STARTED for merge; recheck before action |
| Antigravity analytics UI integration, Phase A | IN PROGRESS; owner retest iteration, no final immutable handoff supplied | IN PROGRESS; visible Git metadata is not certification |
| Earlier readiness package | COMPLETE as local documentation commit `ee4ddf4b5e001328a1beeeea3471d58dd87c1614` | NOT STARTED for production/publishing |
| These portal/goal amendments | READY FOR OWNER REVIEW; uncommitted | NOT STARTED |
| Vincent portal package, Phase C | PLANNED | NOT STARTED |
| Member 2 portal package, Phase D | PLANNED | NOT STARTED |
| Numeric Student cohort, MTR-07, canonical MTR-21, historical Faculty attribution | BLOCKED | BLOCKED; required contracts/evidence unresolved |

At 12:12 UTC on 26 October 2026, actual remote main and cached origin/main were `edef1b382b7874af770feb5713c20e9b68979241`; PR #63 base was that SHA, head `6d0ece576c74ac4c143719308865aa0c1cacc367`. Antigravity's metadata showed `497c4c47ae44a28d580d0f35ee371cb7121f4a88`, clean and six commits ahead of origin/main. No final handoff/acceptance was supplied, so this observed checkpoint is not a certified review target and Phase A remains IN PROGRESS. The new member branches were absent from checked refs; none was created. Revalidate all metadata before future work.

## Phases A–F

| Phase | Status | Responsible party | Entry, outputs and acceptance gate |
| --- | --- | --- | --- |
| A — Finish and publish current analytics UI integration | IN PROGRESS | Antigravity until immutable handoff; owner acceptance | One Program Analytics destination; original Overview retained; scroll/width defects corrected; finalized visual comparisons and chart/details hierarchy; growth/change; real periods; Support visuals; Faculty parity; full themes. Owner retest, immutable commit, independent review, reviewed PR and authorized merge required. Keep IN PROGRESS until final candidate accepted and merged; deployment acceptance is a separate exit gate before publication is called complete. |
| B — Repository and member-readiness consolidation | IN PROGRESS | Sol documentation; owner approval | Resolve/merge/reconcile PR #63, review and authorize amended readiness commit, then separately reconcile repository roadmap/history. Finalize portal packages, branches and shared custodians; verify current main for member pulls. Docs ready does not mean implementation started. |
| C — Vincent Administrator/Faculty package | NOT STARTED | Vincent | Approved `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION` on `feat/member1-admin-faculty-ui`. Future collapsible sidebar, both dashboards, chart/layout consistency, filters, support, themes, accessibility, assigned-scope negatives, portal and cross-portal regressions. Self-verification and owner handoff required. Do not repeat accepted Antigravity changes without finding/approved improvement; this future sidebar does not block Phase A. |
| D — Member 2 Student package | NOT STARTED | Member 2 | Approved `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION` on `feat/member2-student-portal`. Dashboard/Academic Analytics, GWA history/change, prediction clarity, Goal Calculator/Grades/history/Enrollment regression, own-record privacy and unavailable cohort interface. Verify Student calculations and propose future cohort contract; numeric cohort stays BLOCKED. Self-verification and owner handoff required. |
| E — Cross-portal shared integration | PLANNED | Both; one assigned custodian per exact shared file | Integrate accepted candidates sequentially; reconcile shared CSS/JS, header/sidebar and analytics API; cross-portal regressions, theme/accessibility parity, no duplicate formulas; final owner retest/handoff. Can coordinate independent portal work after contracts and ownership are approved; no simultaneous shared-file edits. |
| F — Broader validation/institutional readiness | PLANNED | Future separately authorized owners | Approved genuine-record validation, institutional UAT, broad accessibility, mobile only if reauthorized, MTR-07 model residual evidence, MTR-21 referral lifecycle, cohort privacy/calculation approval. No implemented claim; individual unresolved dependencies remain BLOCKED. |

## Non-negotiable member delivery

## Definition of Done — required for each portal package
A package is not done because its page renders. Both Vincent and Member 2 must supply:
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


## Contract blockers and integration order

MTR-07 needs verified active-model test residuals; canonical MTR-21 needs referral-level closure evidence. No invented field/migration/Migration 011. MTR-19 remains a bounded receipt count. Limited parent-case median is separate. Historical Faculty attribution needs dated assignments; current teaching loads cannot substitute.

Member 2's [eleven cohort items](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md) and owner approval precede any numeric series. The blocked interface may be prepared without enabling data. No privacy threshold or administrative pooled-mean substitution.

Agree shared contracts/custodians first; implement portal files independently; each self-verifies; cross-review; owner retest; authorize publication separately. Merge the lesser shared-file package first; the second member updates main before its PR. Keep auto-merge off. No phase authorizes agent messaging, branch creation, commit, push, merge, deployment or database work by itself.
