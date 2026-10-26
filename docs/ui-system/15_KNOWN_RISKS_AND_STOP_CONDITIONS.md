# Risks and stop conditions

Documentation: READY FOR OWNER REVIEW. Member implementation: NOT STARTED.
Vincent owns Administrator/Faculty implementation and self-verification; Member 2 owns Student implementation, calculations/privacy and self-verification. No universal UI/backend owner or tester-only role.

| Risk | Prevention / evidence | Stop affected work when |
| --- | --- | --- |
| Old ownership split persists | Portal map, own tests/fixes, cross-review supplemental | Student assigned to Vincent or all technical testing delegated to Member 2 |
| Shared-file collision | One owner-assigned custodian per literal file, sequential candidates | Simultaneous editor, unknown dirty changes or missing custody |
| Antigravity overlap | Wait immutable accepted handoff and actual main; no moving-source certification | Need to edit its active checkout or repeat accepted scope without finding/approval |
| PR #63 silent overwrite | Freeze three paths, use separate reconciliation plan after merge/close | Proposed update overlaps unresolved PR63 status/history |
| Main/base drift | Recheck refs/PR/files and approve current base | Unexplained overlapping commit or unapproved base |
| Sidebar/drawer collision | Separate desktop preference and existing drawer; no mobile tests | Fix needs unauthorized smaller-device behavior |
| Resize leaks/clipping | Single initializer/observer, settled/coalesced resize and cleanup tests | Duplicate handler or unexplained geometry remains |
| Inaccessible rail/tab/filter | Names/focus/current/expanded, keyboard, three desktop sizes | Route or primary control cannot be used |
| Scroll/filter overload | One primary scroll; context tabs, ≤4 primary filters, Advanced | Page horizontal overflow or hidden active scope |
| Theme gaps | Check every chart/control/table/details surface in each portal | White dark cards, unreadable labels/limits or hidden focus |
| False line/trend | Scalar for one period; chronological ≥2-valid-point line, real labels, gaps | Ordinal-only history, interpolation or invented point |
| Invalid comparison/growth | Approved unit/population/period/denominator, exact tables/n/coverage | Incompatible delta, zero baseline relative change, hidden missingness |
| Wrong average | Preserve canonical own Student GWA versus pooled recorded-grade mean | Relabeling or unsupported weighting/eligibility |
| Cohort privacy | BLOCKED until eleven Member 2 gates and owner approval | Any fabricated average/threshold/enable flag or peer detail |
| Support overclaim | MTR19 bounded count, MTR20 mean with n, parent median distinct | Count treated as effectiveness/rate or parent duration as MTR21 |
| Unsupported evidence | MTR07 range and historical Faculty attribution BLOCKED | Invented residual bounds, current-assignment historical inference/ranking |
| Academic meaning drift | Separate pass/fail, risk, standing, pacing, benefits; supportive copy | Punitive/official conclusion or formula change outside scope |
| DB/test side effects | Review full connection/include/subprocess/mutation paths before separately approved isolated tests | Existing DB access/fallback, genuine records, migration011 or unapproved resources |
| Reviewer fixes bypass implementer | Original owner repairs; explicit shared-contract/owner transfer exception | Unapproved redesign/transfer or self-review called independent |
| Evidence overclaim | Categorized actual counts, unrun checks block required gates | Syntax/hash/mock/source described as live production acceptance |
| Delivery without authority | Explicit commit/push/PR/merge/deploy/cleanup gates | Missing separate authorization |
| Mandatory-worktree drift | One human clone/assigned branch; same-clone AI child refs | Extra worktree without owner exception or branch from other member |

## Current preparation limits

Only docs/ui-system documentation/reference changes. No access to either database, production/browser handler execution, migrations/Migration 011, genuine records, model/dependency/auth changes, mobile tests or screenshots. PR #63's three files, Antigravity's active worktree and canonical checkout remain untouched.

Future branch names: `feat/member1-admin-faculty-ui` and `feat/member2-student-portal`. They are not created here. Same-clone task suffixes avoid Git parent/child ref conflicts. Both members self-verify before handoff. Cross-review reports defects; original implementer fixes unless explicitly reassigned.

Cohort unavailable-interface preparation is permitted despite the numeric BLOCKED contract; this does not authorize data access or release. Current amendments await local documentation commit approval; no push/PR/merge/deploy or cleanup. Preserve work on stopping, report exact blocker and safe continuation, and do not manufacture completion.
