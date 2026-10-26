# Member UI work package — [ID and title]

Template only. Unfilled fields are not approval. Future UI defaults to `NOT IMPLEMENTED` / `PLANNED`; no prechecked acceptance or production permission is supplied.

## Approval and identity

| Field | Required value |
|---|---|
| Owner approval / date / exact instruction | [Evidence; pending until supplied] |
| Member | [Vincent, or unnamed Member 2 once owner names them] |
| Package | [Vincent: UI/UX, Sidebar, Dashboard and Chart Experience; or Member 2: Analytics Calculations, Verification, Testing and Bug Fixes] |
| Baseline / origin/main / HEAD | [Full SHAs, verification time, reviewed later commits] |
| Assigned branch / current local branch / repository clone | [Vincent: feat/member1-ui-ux; Member 2: feat/member2-analytics-verification; actual local branch; discovered absolute clone path] |
| Repository status / preserved local work | [Actual status; unknown dirty work stops edits] |
| Integration custodian / status writer | [One named primary per shared file] |

Read [context](../00_UI_SYSTEM_CONTEXT.md), [workflow](../08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md), [agent protocol](../09_AI_AGENT_UI_IMPLEMENTATION_PROTOCOL.md), [ownership map](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), and the root [AGENTS.md](../../../AGENTS.md) plus canonical roadmap/history/governance before execution. Revalidate current PRs and frozen files; do not copy historical baseline/status as a fresh observation.

## Objective and approved boundary

- Current behavior and concrete problem: [Observed evidence and SHA].
- Desired result: [Small reviewable behavior change].
- Requirements / panel mapping: [Only supported links and IDs].
- Explicit non-goals and protected systems: [Include schema, models, dependencies, private templates, authentication/API/formula changes unless separately approved].
- Anticipated literal files before edits: [One file per row below; no broad globs].

| Exact path | Sole primary | Approved change | Reviewer / dependency SHA |
|---|---|---|---|
| [Path] | [Name] | [Edit] | [Evidence] |

Vincent owns every shared UI/sidebar CSS/JS, token, dashboard presentation/layout/spacing, chart presentation/style, legend, tooltip, chart card, filter/advanced control, theme, keyboard/accessibility, desktop behavior, and visual integration. Member 2 verifies analytics, data/privacy, authorization/backend/API, regressions, review and bugs; backend/integration fixes require explicit owner-approved scope. Member 2 may propose failing UI tests/findings but cannot independently restyle or modify Vincent's files. Record a coordinated owner-approved minimal integration fix and explicit temporary transfer if needed. Shared entry-page/fixture changes use one custodian and sequential handoff.

## Data and academic contract

Use `Prototype performance on synthetic academic records`. Preserve canonical scale/GWA/zero/status rules and separate risk, subject outcome, official standing, pacing, and benefits. MTR-07 bounds remain `BLOCKED`; canonical MTR-21 referral closure remains `PLANNED` / `BLOCKED`, separate from the limited parent-case closed-duration median; MTR-19 is count, not canonical rate.

Cohort data/privacy status: `BLOCKED`. Vincent's approved unavailable interface uses `Your recorded GWA` and exactly `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No average calculation, placeholder average, invented threshold or pooled-mean relabeling. Member 2 verifies all eleven contract items in the [chart contract](../05_GWA_TREND_COMPARISON_CHART_CONTRACT.md), and final owner approval follows the eleven verification items.

Database access: [Default: none authorized]. Any database-backed check needs separate owner approval and proven scratch-only mutation paths, including handlers/subprocesses/cleanup with no live fallback. Generic scratch instructions do not authorize mutation. No migrations or Migration 011 by default.

## Verification and acceptance

| Requirement | Current implementation status | Planned exact check | Evidence category / target | Acceptance condition |
|---|---|---|---|---|
| [Requirement] | [NOT IMPLEMENTED / PLANNED / BLOCKED] | [Command] | [Category] | [Observable result] |

Use [acceptance matrix](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) and [test evidence](UI_TEST_EVIDENCE_TEMPLATE.md). Focused checks precede broad relevant checks. Inspect side effects and installed Playwright pin/version first. Faculty dashboard/shared-layout changes require the Faculty layout regression. Reference assets are not production ready. No mobile tests or screenshots under the retained owner directive.

## Permissions, stop conditions, and delivery

- Verified checkpoint commits/pushes on assigned branch: [Exact authority in the approved package; scope and verification conditions].
- One complete PR after owner review: [Approved publication workflow; exact reviewed head; auto-merge disabled].
- Merge/deploy/migrate/cleanup/branch or worktree removal: [Separate authority; none by default].
- Stop conditions: [Dirty/unknown work; overlap; owner conflict; unapproved data/privacy; scope expansion; unavailable required tests].
- Exact success phrase: [Owner-supplied phrase for this future package].
- Exact blocked-handoff phrase: [Owner-supplied phrase; handoff only when explicitly requested].
- Next owner action: [Concrete decision].

No hard reset, forced checkout, automatic stash, broad staging, direct main work/push, or unilateral publication. Preserve local work. Report actual results, not assumed passing counts. Complete the [handoff](UI_CHANGE_HANDOFF_TEMPLATE.md) only after completion or explicit blocked-handoff request.

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
