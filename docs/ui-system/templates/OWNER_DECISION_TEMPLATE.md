# Owner decision — [Decision ID / WP-ID]

Template only. Pending fields, an agent recommendation, silence, or elapsed time are not owner approval.

- Date/time / decision owner / requester: [Actual names].
- Baseline / exact reviewed candidate SHA / branch / absolute clone path: [Actual values].
- Question and reason a decision is required: [Concrete conflict or blocked contract].
- Affected exact files, systems, contracts, and existing primary owners: [Literal paths].
- Evidence and available options: [Concise facts, risks, focused tests, links].
- Recommended option and reason: [Recommendation, not implied approval].
- Owner's exact decision and evidence: [Pending until explicit].
- Approved actions / files / limits / expiry or revalidation trigger: [Specific].
- Still-prohibited actions: [Scope/commit/publication/database/merge/deploy/migration/cleanup as applicable].
- Required follow-up verification and reporter: [Commands/categories/owner].
- Exact next action: [Concrete].

## Ownership or minimal integration decision

Vincent remains sole primary for the unified UI/UX, Sidebar, Dashboard and Chart Experience, including all sidebar CSS/JS, dashboard presentation/layout/spacing, tokens, chart layout/styles, legends/tooltips/cards/filters/themes/accessibility/desktop integration. Member 2 owns analytics calculations/verification/testing/independent review/bugs and separately approved backend fixes. Member 2 may propose failing UI tests/findings but cannot independently restyle or edit Vincent's files.

For an owner-approved minimal integration fix, record:

| Exact file | Current primary | Temporary receiver, if needed | Permitted minimal edit | Sequential handoff order | Source/candidate SHA | Approval and ownership-return condition |
|---|---|---|---|---|---|---|
| [Path] | [Vincent/custodian] | [Name, or none] | [Exact scope] | [One writer at a time] | [Full SHA] | [Explicit] |

Shared entry pages and fixtures require one named integration custodian. No simultaneous primary owners, automatic transfer, reset, forced checkout, stash, recovery commit, or deletion of local work.

## Cohort data/privacy gate decision

Current status: `BLOCKED`, not deferred. Vincent may implement only the separately approved unavailable interface, with `Your recorded GWA` and `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No average calculation or placeholder, invented threshold, Faculty-assignment default, or pooled-mean relabeling.

Member 2 verifies all eleven items below, followed by final owner approval of the complete contract. Record the evidence and explicit decision; the average stays blocked until both stages are complete.

| Gate | Decision / exact accepted contract | Evidence / verifier / date | Owner acceptance |
|---|---|---|---|
| 1. Student-level unit of analysis | [Pending] | [Pending] | [Pending] |
| 2. Comparable cohort definition | [Pending] | [Pending] | [Pending] |
| 3. Eligible records | [Pending] | [Pending] | [Pending] |
| 4. Excluded records | [Pending] | [Pending] | [Pending] |
| 5. Missing/incomplete-term behavior | [Pending] | [Pending] | [Pending] |
| 6. Minimum privacy-safe cohort size | [Pending; no invented number] | [Pending] | [Pending] |
| 7. Aggregation/unit-weighting rules | [Pending] | [Pending] | [Pending] |
| 8. School-year/semester comparability | [Pending] | [Pending] | [Pending] |
| 9. Role visibility | [Pending] | [Pending] | [Pending] |
| 10. Synthetic-data test evidence | [Pending; actual categories/results] | [Pending] | [Pending] |
| 11. Privacy/fairness review | [Pending] | [Pending] | [Pending] |
| Final owner approval of complete verified contract and proposed use | [Pending explicit owner decision] | [Candidate SHA/scope] | [Pending] |

Approving an unavailable interface does not unblock calculation or database access. A contract approval does not automatically authorize a backend implementation, migration, deployment, push/PR, or merge. Those actions require their own explicit work-package authority. Preserve MTR-07 blocked bounds, canonical MTR-21 planned/blocked versus limited parent-case median, and MTR-19 count versus canonical rate.

## Delivery decision, if requested

- Verified checkpoint commit/push authority for this future package: [Exact scope, branch and test conditions].
- Owner review and one complete PR workflow: [Exact candidate SHA; target/files; auto-merge off].
- Current Sol readiness preparation review/commit gate, when relevant: [Owner review pending; no review commit or preparation push/PR yet].
- Merge / deployment / database / migration / cleanup permissions: [Separate explicit authority; none by default].
- Exact future package completion phrase / blocked phrase: [Owner supplies; preparation wording does not mean implementation completed].
- Final decision record writer / frozen ledger reconciliation authority: [Named owner; proposal only until approved].

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
