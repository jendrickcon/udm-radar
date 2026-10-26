# UI review findings — [WP-ID / exact candidate SHA]

Template only. Report observed failures separately from risks and untested behavior. A proposed fix is not a resolved finding.

- Reviewer / member / date: [Name, time].
- Baseline / reviewed immutable SHA / branch / absolute clone path: [Actual values].
- Scope / inspected files / evidence category: [Exact boundary; source review is not end-to-end].
- Authorization / data target: [Default none; no database activity implied].

| ID | Severity | File and line / function | Expected | Actual | Impact | Required fix | Regression test / evidence | Publication blocker? | State |
|---|---|---|---|---|---|---|---|---|---|
| [UI-001] | [Critical / High / Medium / Low with reason] | [Exact location] | [Contract] | [Observed evidence] | [User/data effect] | [Small concrete correction] | [Existing or proposed check; actual result] | [Yes/No; reason] | [Open / proposed / reverified] |

For each finding include reproduction conditions, role, desktop width, exact command or review method, exit/result, and supporting evidence location. Do not invent screenshots or authenticated coverage. Preserve actual failures and distinguish a failing assertion from an unavailable environment.

## Routing and ownership

- Vincent owns all UI/UX, sidebar, dashboard and chart presentation/layout/styles/tokens, legends/tooltips/cards/filters/themes/accessibility/desktop integration. Send UI fixes to Vincent through the approved handoff.
- Member 2 owns analytics calculations/verification/testing/independent review/bugs and separately approved backend fixes. Member 2 may propose failing UI tests/findings but cannot independently restyle or edit Vincent's files. Any owner-approved minimal integration fix is coordinated with Vincent; record an explicit temporary transfer if another writer must edit his file.
- Shared entry pages/fixtures use one owner-appointed custodian and sequential handoff. No simultaneous file primary owners.

Cohort data/privacy is `BLOCKED`. Check exact unavailable label `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.`; no numeric/placeholder average, invented privacy threshold or pooled-mean relabeling. Member 2 verifies eleven contract items and the owner gives final approval after those eleven items. Vincent cannot invent that formula.

Preserve MTR-07 blocked bounds, canonical MTR-21 planned/blocked versus limited parent-case median, and MTR-19 count versus canonical rate. Academic semantics, privacy, role authorization, and missing-data defects are not cosmetic fixes.

## Resolution evidence

| Finding | Fix owner / approved files | New exact candidate SHA | Reverification command and category | Actual result | Remaining limits / owner decision |
|---|---|---|---|---|---|
| [ID] | [Name, paths] | [SHA] | [Execution] | [Passed/Failed/Skipped/Not Run/Unavailable] | [Specific] |

No automatic staging, commit, push, PR, merge, migration, cleanup or recovery follows from this report. Revalidation must target the actual final candidate; do not carry a prior passing result over new changes.

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
