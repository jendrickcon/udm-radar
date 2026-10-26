# UI change handoff — [WP-ID]

Template only. Do not prefill success, a clean tree, a commit, or protected-system confirmations. Final handoff follows completion or an explicitly requested blocked handoff; a progress update does not transfer ownership.

## Objective and immutable candidate

- Owner / member / receiver: [Names; Vincent unified UI or unnamed Member 2 analytics verification role].
- Objective / approved scope / non-goals: [Exact approved result].
- Approval evidence and date: [Scope; local commit; any publication permission separately].
- Baseline / current origin/main / candidate HEAD: [Full SHAs and verification time].
- Assigned branch / current local branch / absolute repository clone path: [Actual values; one member clone].
- Immutable review commit: [Full SHA only after authorized commit; otherwise actual reason no commit].
- Repository status: [Actual status, including untracked/dirty work and owner].
- Contract/source candidate SHAs used: [Exact accepted dependencies].

## Changed files and ownership

| Exact path | Sole primary | Change and reason | Integration/transfer evidence |
|---|---|---|---|
| [Path] | [Name] | [Behavior] | [Approval and source SHA] |

Vincent retains all unified UI/sidebar/dashboard/chart presentation/styles/tokens ownership. Member 2 supplies analytics verification/tests/findings and separately approved backend fixes, not independent restyling. Record any minimal coordinated integration fix and explicit temporary transfer/return. Shared production entry-page/fixture edits follow one custodian and sequential handoff.

## Review of every Git layer

| Layer | Exact inspection command | Result / files / full-diff review |
|---|---|---|
| Committed since baseline | [Command] | [Observed] |
| Unstaged | [Command] | [Observed] |
| Staged | [Command] | [Observed] |
| Untracked | [Command and content review] | [Observed] |
| Combined candidate / allowlist | [Method] | [Observed; unexpected paths] |

## Verification by category

| Category | Exact command / environment / target / runtime | Exit | Actual passed / failed / skipped | Result / evidence / limits |
|---|---|---|---|---|
| Documentation / paths / terminology | [Execution] | [Code] | [Actual or not applicable] | [Status] |
| PHP syntax / PHP regression checks | [Execution] | [Code] | [Actual] | [Status] |
| JavaScript syntax / contracts | [Execution] | [Code] | [Actual] | [Status] |
| Integration | [Authorized target and side effects] | [Code] | [Actual] | [Status] |
| Desktop browser | [Live authenticated / generated static / mocked; installed pin/version] | [Code] | [Actual] | [Roles/widths/states/limits] |
| Structural / source inspection | [Method] | [Code or N/A] | [Actual or N/A] | [No end-to-end claim] |
| Accessibility | [Keyboard/focus/contrast/labels/table/motion methods] | [Code or N/A] | [Actual or N/A] | [Observed scope] |
| Role/record authorization | [Wrong-role/out-of-scope checks or inspected contract] | [Code or N/A] | [Actual or N/A] | [Results and limits; no unexecuted security claim] |

Use Passed, Failed, Skipped, Not Run, or Unavailable; explain each missing check. Do not combine counts into accuracy. Screenshots/mobile testing: [Not Run under owner directive, unless later instruction changes it]. If Faculty dashboard/shared layout changed, record its required layout regression separately. Checksums establish identity only.

## Protected-system evidence

| Boundary | Inspection / evidence | Actual result |
|---|---|---|
| Production changed only within approved scope | [Diff] | [Observed] |
| Protected dump/model/metrics/private templates/dependencies | [All change-layer review] | [Observed] |
| Synthetic data / no genuine identifiers | [Method] | [Observed] |
| Database access / mutations / target / audit side effects | [Explicit authorization or none; actual effects] | [Observed] |
| Migrations / Migration 011 / schema | [Evidence] | [Observed] |
| Reference assets loaded by production | [Search/diff] | [Observed] |
| Push / PR / merge / deployment / cleanup | [Authorization and actual action, or none] | [Observed] |

## Completed, pending, blocked, deferred

- Completed: [Evidence-backed only].
- Pending: [Remaining authorized work].
- Blocked: [Dependency/decision; exact owner action]. Cohort remains `BLOCKED` until Member 2 verifies eleven contract items and owner grants final owner approval; do not call it deferred.
- Deferred: [Consciously excluded later work; separate from blockers].
- Known limitations / failed or unrun checks / risks: [Specific].

Use `Your recorded GWA` and `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No average or placeholder values without all approvals. Retain MTR-07 `BLOCKED`; canonical MTR-21 `PLANNED` / `BLOCKED` separate from limited parent-case median; MTR-19 count, not canonical rate. Include `Prototype performance on synthetic academic records`.

## Next owner action and phrase

- Exact next action: [Review candidate; decide blocker; authorize publication separately].
- Requested status-ledger changes: [Proposal only; named writer applies approved update].
- Completion phrase: [Exact owner-supplied future-package phrase; only when earned].
- Blocked phrase: [Exact owner-supplied phrase; only after explicitly requested blocked handoff].

Retain this historical owner instruction verbatim; it is not a current usage measurement:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
