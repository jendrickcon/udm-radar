# UI progress update — [WP-ID]

Template only. Use a short useful update at milestones; avoid more than about 60 seconds of silence during active work. Do not repeat the full plan, dump low-level tool output, or present progress as final completion.

- Current task: [Concrete authorized step].
- Branch / HEAD / baseline / absolute clone path: [Actual; repeat when changed].
- Completed: [Evidence-backed items since the last update].
- Early findings: [Observed fact and its implication; continue independent authorized work].
- Pending: [Remaining authorized work].
- Blocked: [Required dependency or decision; exact next owner action].
- Deferred: [Consciously excluded later work, separate from blockers].
- Next action: [Specific step that resolves remaining uncertainty].
- Test evidence so far: [Category, actual result/counts, limits; no combined accuracy].

Ownership reminder: Vincent owns the unified UI/sidebar/dashboard/chart experience and all shared presentation/styles/tokens; unnamed Member 2 owns analytics verification/testing/findings and separately approved backend fixes. Member 2 may propose failing UI tests but cannot independently restyle Vincent's files. Cross-owner minimal integration changes require owner approval and coordinated single-writer handoff, with explicit temporary transfer if needed.

Cohort data/privacy gate: `BLOCKED`, not deferred; use `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No average/placeholder calculation. Member 2 verifies eleven contract items; final owner approval follows the eleven verification items. Do not imply future UI is implemented or passing in a preparation update.

Retain this historical owner instruction when forwarding context; the usage line is not a measured current balance:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

Long work instructions belong in a linked Markdown file. A final immutable handoff is issued only after completion or an explicitly requested blocked handoff; this progress report transfers no ownership or publication authority.

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
