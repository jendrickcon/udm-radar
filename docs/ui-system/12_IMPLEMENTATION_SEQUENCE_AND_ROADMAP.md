# Future implementation sequence
This is a proposed sequence under [canonical roadmap](../ROADMAP.md), not an amendment to PR #63 or authorization to begin production work.
All future work is PLANNED or BLOCKED. No production implementation item is PASSING.

| Gate | Owner / outputs | Entry and exit |
| --- | --- | --- |
| 0: current integration | Antigravity implementation; Sol independent review | Wait exact immutable handoff. Complete independent intake/review and owner disposition before rebasing future UI assumptions. Visible checkpoint is not acceptance. |
| 1: readiness review | Owner, Vincent and Member 2 | Review this validated uncommitted framework, proposals and unresolved decisions. Only after owner review may Sol commit it. Owner approves future member packages, files, baseline and authority; Member 2 name remains unassigned. |
| 2: technical baseline | Member 2 | Verify unchanged grading/metric/role contracts and construct identifier-free expected results, privacy questions and tests. No database access or backend edits without explicit future authority. |
| 3: unified UI implementation | Vincent: UI/UX, Sidebar, Dashboard and Chart Experience | Sidebar, content expansion, all portal presentation, shared tokens/styles/JS, filters, chart cards/legends/table and blocked cohort interface stay under Vincent's single primary ownership. Use one clone and assigned branch feat/member1-ui-ux; extra worktrees require exceptional owner authorization. |
| 4: technical independent review | Member 2: Analytics Calculations, Verification, Testing and Bug Fixes | Review Vincent's exact clean immutable SHA. Verify backend/API/roles/calculations and UI regressions; report defects. A reviewer must disclose any files they authored and obtain independent review for their own fixes. |
| 5: verified corrections | Owner approves; one active writer per file | Vincent fixes presentation files; Member 2 may make authorized backend/integration fixes. Any UI-file transfer is explicit, temporary and sequenced, never concurrent or a sidebar/chart split. Preserve accepted design scope. |
| 6: optional future cohort enablement | Member 2 verifies data/privacy; owner approves; Vincent presents | BLOCKED until eleven technical/privacy items and final owner approval are recorded. Requires approved backend/authorization/tests separately. A flag in reference JavaScript cannot enable it. |
| 7: publication and deployment | Owner authorizes distinct actions | Review completed evidence, immutable SHA and exact diff. Members push verified checkpoints within their approved package and open one reviewed PR when complete. Sol's current preparation has no push/PR authority and requires owner review before commit. Merge/deploy/migration/cleanup remain separate. |

## Tasks for the later implementer, not executed now
- Vincent first reconciles accepted Antigravity UI structure, then adapts sidebar root/controls and token aliases. Prove old navigation/roles work and released width resizes every plot.
- Vincent adapts chart model, card/legend/table and empty states together; no copying reference files into headers without tests. Own record series is active; cohort stays unavailable.
- Member 2 verifies canonical Student-level term GWA, record exclusions/units and returned scope. No new cohort arithmetic/threshold inferred from pooled administrative metrics.
- Member 2 independently verifies same immutable result at 1440×900,1024×768,1280×600 light/dark; focus/keyboard, latest response, missing states, formula and role negatives.
- Coordinate one integration custodian for shared fixtures and status documentation; do not change PR #63 files while open without owner reconciliation.
- If main advances, inspect every later commit and overlapping path before any new base is accepted; do not auto-merge/rebase away another member's work.

## Review focus
Highest-risk seams are desktop state versus mobile drawer, duplicate resize/listeners across portal navigation, table/plot mismatch, hidden active filters, Student-average versus pooled-grade semantics, and future privacy thresholds. The acceptance matrix maps each to a test. No broader chart redesign or new forecast/range is hidden in these tasks.

## Human and AI branch sequence
Vincent: feat/member1-ui-ux. Member 2: feat/member2-analytics-verification. Each uses one clone; extra worktrees only by exceptional owner authorization. Before the next package: save approved current work, inspect status, fetch, switch main, pull --ff-only, return assigned branch, integrate main by approved method, resolve conflicts and verify clean status/start SHA. Do not discard dirty work or reset/clean/force-push.
AI child branches may use compatible names such as feat/member1-ui-ux--sidebar or feat/member2-analytics-verification--cohort-contract, based on their own assigned HEAD. A slash child under an existing same-name parent ref cannot coexist in Git, so slash examples are conceptual and need compatible task-suffixed refs. Never branch from another member's branch.
Integrate a child task into the assigned branch only through the approved method. Human checkpoint pushes belong to the approved member workflow; Sol does not push this readiness package or commit before owner review. Immutable independent review is an exceptional owner-authorized worktree use, not the human implementation default.
