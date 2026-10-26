# AI agent portal implementation protocol

Documentation: READY FOR OWNER REVIEW. Future implementation: NOT STARTED. This protocol grants no production or publication authority.

## Read before editing

Read applicable [AGENTS](../../AGENTS.md), [contribution policy](../../CONTRIBUTING.md), canonical [roadmap](../ROADMAP.md), [history](../IMPLEMENTATION_HISTORY.md), [UI governance](../UI_UX_GOVERNANCE.md), [findings](../UI_UX_FINDINGS_REGISTER.md), [metric reconciliation](../post-defense/14_METRIC_RECONCILIATION.md), this [context](00_UI_SYSTEM_CONTEXT.md), the exact approved package and relevant token/sidebar/chart/data/accessibility/acceptance documents. Discover which skills actually loaded; do not assume another host's skill discovery.

## Required startup report

Before editing, report all twelve items:
1. Member name.
2. Assigned portal scope and package approval.
3. Assigned long-lived branch.
4. Actual local branch, absolute clone/served paths and full HEAD.
5. Full current origin/main SHA.
6. Actual Git status and dirty-state disclosure.
7. Expected literal changed files.
8. Shared files and one named integration custodian per file.
9. Protected systems and non-goals.
10. Required self-verification tests.
11. Cross-portal regression tests.
12. Stop conditions.

Confirm correct branch and shared ownership before editing. Unknown dirty work, conflicting ownership, out-of-scope fixes or an unaccepted base stops the affected work. Preserve changes; no automatic destructive recovery. See [member workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md) for clean-main update and same-clone task branches.

## Portal ownership and self-verification
Vincent owns Administrator and Faculty revisions on `feat/member1-admin-faculty-ui`, package `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION`.
Member 2 owns Student revisions on `feat/member2-student-portal`, package `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION`.
Both implement, test, verify, fix and document their own portals before handoff. Cross-review supplements self-verification. Neither member is the universal UI owner, universal backend owner or sole tester.
Shared cross-portal CSS/JS, header/sidebar, analytics contracts, fixtures and status files require one owner-assigned integration custodian per exact file, sequential edits and cross-portal regressions. A review finding does not transfer ownership.

## Workflow and authorization
One local clone and assigned long-lived branch per human member. Optional AI task branches start from that member's own assigned branch in the same clone; compatible task-suffixed names avoid Git ref file/directory conflicts. Extra worktrees require explicit owner-authorized exceptions.
Save approved local work, inspect status, fetch, update clean main fast-forward, return assigned branch, integrate latest approved main by the package-approved method, resolve conflicts and record the starting SHA before editing.
After self-verification, an approved local immutable candidate and complete handoff precede owner retest. Push only after separate owner authorization, then one reviewed PR against verified current main with auto-merge off; merge and deployment require their own authorization. Both members update main after approved merges. No broad staging, destructive recovery, branch deletion or cleanup by default.
The existing local readiness commit `ee4ddf4b5e001328a1beeeea3471d58dd87c1614` records the earlier package. These portal/roadmap amendments are a new uncommitted revision awaiting owner review and explicit local-commit authorization.

## Protected boundaries
No production edits, database access, migrations/Migration 011, genuine records, model/dependency/authentication changes, mobile tests or screenshots are authorized by readiness preparation.
MTR-07 and canonical MTR-21 remain BLOCKED. MTR-19 remains a bounded receipt count; limited parent-case duration is not MTR-21. Historical Faculty attribution remains BLOCKED without dated evidence; no Faculty ranking/teaching-quality inference.
Student cohort average remains BLOCKED until Member 2's eleven technical/privacy items and owner approval; no fabricated comparison, privacy threshold, pooled Admin-mean substitution or enabling flag.
Freeze PR #63 files: `docs/ROADMAP.md`, `docs/IMPLEMENTATION_HISTORY.md`, `docs/post-defense/10_IMPLEMENTATION_STATUS.md`. Reconcile later using the separate plan; no silent overwrite.


## Execute approved portal work

Vincent implements Administrator/Faculty visual analytics, dashboard summaries, support visuals, sidebar, filters, themes and accessibility, and tests calculations as displayed plus authorization. Member 2 implements Student UI and verifies own-record GWA/change/coverage/missing/summer behavior, prediction clarity and privacy. Reuse approved helpers/services; no duplicate calculation engines.

Agree metric unit, scope, denominator, exclusions, periods and availability before a new display. Point change needs comparable start/end periods; relative change is supporting context only with an approved nonzero baseline. Incompatible evidence shows “Change unavailable” and a reason. One-period values use scalar bars/dots; lines require at least two valid ordered historical values. Preserve exact tables below charts.

Critical synthetic label, coverage/n, limitations and unavailable explanations remain visible; long mechanics may use accessible disclosures. Two comparison groups default, optional third, no fourth; context cards do not automatically authorize direct deltas. Keep one primary page scroll owner and themes across every surface.

## Academic and blocked invariants

Use “Prototype performance on synthetic academic records”. Preserve higher-is-better 0–4 point direction, canonical GWA zero/status exclusions, incomplete handling, honors predicate and dynamic academic periods. Subject passing, advisory risk, official standing, program pacing and benefits are separate. No punitive copy or automatic Student notice.

MTR-20 remains the arithmetic mean of eligible distinct-referral durations from creation to first valid notice; display “Average time before the first notice” with actual n and human-readable duration. Exclude unacted, invalid/reversed timestamps; avoid duplicate joins. Do not change its formula. MTR-19 is a bounded receipt count. Parent closed-case median is limited parent duration, not canonical MTR-21.

MTR-07 requires verified active-model residual evidence. Historical Faculty attribution needs dated evidence; no rankings/teaching-quality claims. Cohort is BLOCKED: Member 2 owns its Student unavailable UI and eleven-item proposal in the [chart contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md), then owner approval. Never invent numbers/privacy thresholds or substitute pooled administrative means.

## Verification and handoff

Both members run their own focused unit, authorized integration, authenticated desktop, negative authorization, structural, mocked race/error, theme and keyboard checks as relevant. Classify each separately. Review runner versions and all test side effects before execution. No broad suite may override no-DB/no-mobile/no-screenshot scope. Report required unrun evidence as BLOCKED with reason, never a pass.

For future faculty/dashboard.php or shared dashboard CSS edits, run `tests/browser/faculty-dashboard-layout-regression.spec.js`; preserve warning-banner containment. Run Goal Calculator, Grades/history, Enrollment Planning, PEF/CC, Activity/Inbox, Admin modal-focus and shared-auth/navigation regressions when affected.

Use the [Definition of Done](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md). Self-verification precedes cross-review; disclose authorship and never call self-review independent acceptance. Fix only verified in-scope defects, with no design expansion. Original implementer fixes portal defects; shared-contract exceptions require an assigned owner or explicit transfer.

A final handoff follows completed scope or an explicitly requested blocked handoff. Include exact SHA/status/files, actual categorized results, cleanup/integrity, limitations, owner retest and separate publication gates. Do not commit these new readiness amendments without owner approval.

## Desktop-only instruction

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is historical, not a current measurement. No mobile tests or screenshots are authorized. Future desktop acceptance covers 1440×900, 1280×600 and 1024×768, light and dark.
