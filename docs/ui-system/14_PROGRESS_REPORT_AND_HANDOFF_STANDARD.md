# Progress report and handoff standard

Status: preparation standard only; future UI is `NOT IMPLEMENTED` / `PLANNED`. Completed documentation is not a completed sidebar or chart implementation. The cohort data/privacy gate remains `BLOCKED`.

## Progress updates

Use the [progress template](templates/UI_PROGRESS_UPDATE_TEMPLATE.md). During active work, send short, meaningful updates at milestones and avoid more than about 60 seconds of silence. Include the current task, completed items, early findings, blockers, next action, and branch/SHA if changed. Do not repeat the entire plan or expose a stream of low-level tool calls. Report a finding early while continuing independent authorized work. Put long work instructions in Markdown artifacts and link them.

Keep completed, pending, blocked, and deferred items separate. `BLOCKED` means a required dependency or decision prevents progress; `DEFERRED` means a consciously excluded later item. The cohort aggregate/privacy contract is blocked, not deferred. Vincent owns the complete UI experience; unnamed Member 2 verifies analytics and tests and may report UI findings without independently editing Vincent's UI files. A usage-limit message or time pressure does not permit fabricated evidence or an unsupported completion claim.

## Evidence categories and result labels

Use the [test evidence template](templates/UI_TEST_EVIDENCE_TEMPLATE.md). For every execution, record its exact command, date/time, reviewed SHA or dirty-state identity, runtime/version, environment/target, exit code, actual passed/failed/skipped totals, and evidence location. Preserve failures. Do not reuse historical counts or report discovery/source inspection as test execution.

| Evidence category | What it can establish | Required limit to state |
|---|---|---|
| Documentation/path/terminology checks | Links, files, instruction consistency, formatting | No production behavior proven |
| PHP syntax and standalone PHP regression checks | Syntax or asserted PHP behavior | Not PHPUnit; identify real database effects if any |
| JavaScript syntax/contract checks | Parseability or asserted module behavior | Reference-only checks do not prove app integration |
| Integration | Exercised handlers/data interactions | State exact authorized database and side effects |
| Live authenticated desktop browser | Actual tested role/page/viewport behavior | Name authentication, data, and untested roles/states |
| Generated static HTML browser | Rendered fixture interaction/layout | No authenticated application or live database claim |
| Mocked browser | Tested fixture plus mocked network responses | Mocked data is not source-data validation |
| Structural/source inspection | Inspected code, markup, selectors or contracts | Not end-to-end or executed accessibility coverage |
| Accessibility/manual review | Specific keyboard, focus, contrast, labels/table evidence | State method and scope; no blanket conformance claim |
| Owner review | Acceptance of a specified candidate and limitations | Not institutional acceptance, merge, or deployment |

Execution results use `Passed`, `Failed`, `Skipped`, `Not Run`, or `Unavailable`, with reasons. Keep actual totals by category; do not sum assertions, tests, source checks, screenshots, or review comments into an accuracy percentage. A checksum establishes file identity or unchanged bytes; it does not replace functional or accessibility tests.

The [acceptance matrix](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) uses a separate implementation vocabulary: `NOT IMPLEMENTED`, `PLANNED`, `TEST EXISTS`, `PASSING`, `FAILING`, `BLOCKED`, `NOT APPLICABLE`. `TEST EXISTS` does not imply execution. This preparation must not mark future implementation requirements `PASSING`.

## Final handoff

Use the [handoff template](templates/UI_CHANGE_HANDOFF_TEMPLATE.md). Issue the final handoff after completion, or after the owner explicitly requests a blocked handoff. Ordinary progress updates do not transfer ownership or release unfinished files. Include:

1. Member, objective, approved scope, non-goals, baseline/current main/HEAD SHAs, assigned/local branches, absolute repository clone path, status, expected files, shared-file owners, required tests, stop conditions and owner approvals. Vincent's assigned branch is `feat/member1-ui-ux`; Member 2's is `feat/member2-analytics-verification`.
2. Exact changed files, file owners, implementation details, accepted contracts, and any source candidate SHAs used by the integration custodian.
3. Full change-layer review: committed, unstaged, staged, untracked, and combined contents against the allowlist. State dirty files honestly; do not prefill a clean claim.
4. Actual commands and results by category, desktop widths/roles/states, accessibility evidence, failed/skipped/unrun checks, and limitations.
5. Protected-system evidence, with checks and results rather than prechecked confirmations: production changes, protected assets, dependencies, genuine records, database access/writes, migrations/Migration 011, reference asset loading, and publication/cleanup actions.
6. Exact final immutable SHA when an authorized commit exists, verified checkpoint push state and PR state. Otherwise state the actual reason no commit exists. For this current Sol preparation, state `Review commit pending owner review` until owner review authorizes its commit; do not invent a delivery SHA. A mutable working tree is not an immutable candidate.
7. Completed, pending, blocked, and deferred items; exact next owner action; decision or publication boundaries; and the package-specific completion phrase.

Future members use one local clone and their assigned long-lived branch, with regular meaningful commits and verified push checkpoints under the approved package. One complete PR follows owner review under the approved workflow, with exact reviewed head, categorized evidence and auto-merge disabled. Merge needs owner authorization; no deployment, migration, branch/worktree cleanup or force-push follows automatically. This Sol preparation is stricter: commit only after owner review, with no preparation push/PR authorized. Do not end with a completion phrase while required work is incomplete; use the owner-defined blocked phrase only for an explicitly requested blocked handoff.

AI child branches stay in the same clone and start at the member's own assigned HEAD. Use compatible names such as `feat/member1-ui-ux--sidebar` or `feat/member2-analytics-verification--ui-review`; slash descendants under an existing assigned branch cause a Git ref file/directory conflict. No branch from the other member, no extra worktree without explicit owner exception approval. After approved merge, record the merge SHA and both members pull clean main fast-forward-only, verify the served repository, and use current main for the next approved package. GitHub main is authoritative; copied folders/ZIP files are not development truth.

## Findings and decisions

Use the [review findings template](templates/UI_REVIEW_FINDINGS_TEMPLATE.md) for ID, severity, literal file/line or function, expected behavior, actual behavior, impact, required fix, regression test, and publication-blocker status. Distinguish observed defects from untested risks. Cite exact evidence and SHA. A finding is not resolved merely because a fix is proposed.

Use the [owner decision template](templates/OWNER_DECISION_TEMPLATE.md) for unresolved cohort semantics, file ownership, scope, and permissions. Record who decided, exact decision text, affected files/contracts, permitted actions, still-prohibited actions, and revalidation trigger. Do not infer approval from silence or from an agent-generated plan.

## Required continuity statements

Retain the synthetic prototype label: `Prototype performance on synthetic academic records`. Preserve MTR-07 bounds as `BLOCKED` without verified active-model held-out residual evidence; canonical MTR-21 referral closure as `PLANNED` / `BLOCKED`, separate from the limited parent-case closed-duration median; and MTR-19 as bounded receipt count rather than canonical rate.

For the Student chart, use `Your recorded GWA`; the unavailable cohort label is `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No populated cohort series until Member 2 verifies all eleven [contract items](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md) and the owner gives final approval. Eligible and excluded records are separate items; school-year/semester comparability has its own item. Vincent may prepare the unavailable presentation, not its value.

Include this owner instruction verbatim in progress/final report artifacts when carrying the instruction forward:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is a historical quote, not a measured current balance. Under the current directive, mobile tests and screenshots are `Not Run` with that reason. Desktop checks remain subject to the work package's authorization; this preparation does not run production browser tests.

The readiness preparation's successful final phrase is `FUTURE UI MEMBER AND AGENT READINESS PACKAGE PREPARED FOR OWNER REVIEW`; its blocked phrase is `FUTURE UI READINESS PREPARATION BLOCKED`. These phrases describe preparation only. Future member templates require their own owner-supplied completion and blocked phrases and must not reuse preparation wording to imply production implementation completion.
