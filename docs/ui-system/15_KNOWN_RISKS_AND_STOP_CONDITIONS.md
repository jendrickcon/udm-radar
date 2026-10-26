# Risks and stop conditions

Documentation: READY FOR OWNER REVIEW. Member implementation: NOT STARTED.
Vincent owns Administrator/Faculty implementation and self-verification; Joshua owns Student implementation, calculations/privacy and self-verification. No universal UI/backend owner or tester-only role.

| Risk | Prevention / evidence | Stop affected work when |
| --- | --- | --- |
| Old ownership split persists | Portal map, own tests/fixes, cross-review supplemental | Student assigned to Vincent or all technical testing delegated to Joshua |
| Shared-file collision | One owner-assigned custodian per literal file, sequential candidates | Simultaneous editor, unknown dirty changes or missing custody |
| Antigravity overlap | Use immutable merged PR64/current approved main; no need to wait for a superseded implementation handoff | Need to edit its active checkout or repeat accepted scope without finding/approval |
| PR #63 silent overwrite | Preserve the three accepted PR63 paths exactly as current main | Readiness edit would overwrite merged status/history or a new main change |
| Main/base drift | Recheck refs/PR/files and approve current base | Unexplained overlapping commit or unapproved base |
| Sidebar/drawer collision | Separate desktop preference and existing drawer; no mobile tests | Fix needs unauthorized smaller-device behavior |
| Resize leaks/clipping | Single initializer/observer, settled/coalesced resize and cleanup tests | Duplicate handler or unexplained geometry remains |
| Inaccessible rail/tab/filter | Names/focus/current/expanded, keyboard, three desktop sizes | Route or primary control cannot be used |
| Scroll/filter overload | One primary scroll; context tabs, ≤4 primary filters, Advanced | Page horizontal overflow or hidden active scope |
| Theme gaps | Check every chart/control/table/details surface in each portal | White dark cards, unreadable labels/limits or hidden focus |
| False line/trend | Scalar for one period; chronological ≥2-valid-point line, real labels, gaps | Ordinal-only history, interpolation or invented point |
| Invalid comparison/growth | Approved unit/population/period/denominator, exact tables/n/coverage | Incompatible delta, zero baseline relative change, hidden missingness |
| Wrong average | Preserve canonical own Student GWA versus pooled recorded-grade mean | Relabeling or unsupported weighting/eligibility |
| Cohort privacy | BLOCKED until eleven Joshua gates and owner approval | Any fabricated average/threshold/enable flag or peer detail |
| Support overclaim | MTR19 bounded count, MTR20 mean with n, parent median distinct | Count treated as effectiveness/rate or parent duration as MTR21 |
| Unsupported evidence | MTR07 range and historical Faculty attribution BLOCKED | Invented residual bounds, current-assignment historical inference/ranking |
| Academic meaning drift | Separate pass/fail, risk, standing, pacing, benefits; supportive copy | Punitive/official conclusion or formula change outside scope |
| DB/test side effects | Review full connection/include/subprocess/mutation paths before separately approved isolated tests | Existing DB access/fallback, genuine records, migration011 or unapproved resources |
| Reviewer fixes bypass implementer | Original owner repairs; explicit shared-contract/owner transfer exception | Unapproved redesign/transfer or self-review called independent |
| Evidence overclaim | Categorized actual counts, unrun checks block required gates | Syntax/hash/mock/source described as live production acceptance |
| Delivery without authority | Explicit commit/push/PR/merge/deploy/cleanup gates | Missing separate authorization |
| Mandatory-worktree drift | One human clone/assigned branch; same-clone AI child refs | Extra worktree without owner exception or branch from other member |

## Current preparation limits

Only docs/ui-system documentation/reference changes. No access to either database, production/browser handler execution, migrations/Migration 011, genuine records, model/dependency/auth changes, mobile tests or screenshots. Merged PR63 files match current main without readiness-specific edits; Antigravity's worktree remains untouched. The separate owner-authorized canonical fast-forward changed documentation only.

Future branch names: `feat/member1-admin-faculty-ui` and `feat/member2-student-portal`. They are not created here. Same-clone task suffixes avoid Git parent/child ref conflicts. Both members self-verify before handoff. Cross-review reports defects; original implementer fixes unless explicitly reassigned.

Cohort unavailable-interface preparation is permitted despite the numeric BLOCKED contract; this does not authorize data access or release. Current reconciliation has local documentation commit authorization only; no push/PR/merge/deploy or cleanup. Preserve work on stopping, report exact blocker and safe continuation, and do not manufacture completion.

## Reconciled stop conditions

Stop a proposed member task that reimplements accepted PR64 behavior without a finding or approved refinement. Stop onboarding that would execute bootstrap DROP TABLE statements against an existing populated target, replay migrations000–010, enable Migration011 or import seed implicitly. Current reconciliation performs source inspection only.

PR63 is owner-authorized and merged at c29d68a7ef961c0be61ffbf296a10918303d5fea; its canonical documentation fast-forward is complete. Runtime/onboarding remains identical to deployed PR64 bdb4a267f30aa31ea36983304a03b01dc28f0fcd. Readiness push/PR and all further remote actions require separate authorization. No production diff against current main is allowed.
