# Member UI contribution workflow

Status: preparation only. Future UI is `NOT IMPLEMENTED` / `PLANNED`; the cohort calculation/privacy gate is `BLOCKED`. Vincent owns the unified UI experience. Unnamed Member 2 owns analytics calculations, verification, testing and approved technical bug fixes. Exact files, tests, and delivery authority still require an approved package.

## Context and authority

Read [AGENTS.md](../../AGENTS.md), [CONTRIBUTING.md](../../CONTRIBUTING.md), the canonical [roadmap](../ROADMAP.md), [history](../IMPLEMENTATION_HISTORY.md), [UI governance](../UI_UX_GOVERNANCE.md), [findings register](../UI_UX_FINDINGS_REGISTER.md), [post-defense context](../post-defense/00_CONTEXT_AND_CURRENT_STATE.md), [status ledger](../post-defense/10_IMPLEMENTATION_STATUS.md), this package's [context](00_UI_SYSTEM_CONTEXT.md), [ownership map](11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), approved work package, and applicable repository skills.

The [existing member workflow](../post-defense/08_MEMBER_CONTRIBUTION_WORKFLOW.md), [Git update guide](../MEMBER_GIT_UPDATE_GUIDE.md), and [continuation guide](../post-defense/09_AGENT_CONTINUATION_GUIDE.md) are background. Current owner instructions supersede their mandatory-worktree assumption and broad examples. They do not authorize stage-all, migrations, dependency installation, destructive recovery, publication outside the approved package, or cleanup.

## One clone and one assigned branch

Each human member uses one local repository clone on their laptop and one assigned long-lived feature branch:

| Member | Assigned branch | Package |
|---|---|---|
| Vincent | `feat/member1-ui-ux` | UI/UX, Sidebar, Dashboard and Chart Experience |
| Member 2 — name pending | `feat/member2-analytics-verification` | Analytics Calculations, Verification, Testing and Bug Fixes |

Use small meaningful commits and verified push checkpoints on the assigned branch within the approved package. Do not request another clone or worktree as routine setup. An extra worktree needs explicit owner authorization for an exception such as independent certification, conflict recovery, preservation of unique dirty work, or immutable review without changing the active checkout.

GitHub `main` is authoritative after approved merges. Synchronize through Git, not copied laptop folders, emailed source folders, or ZIP files. Discover the actual absolute clone path and XAMPP/Apache mapping on each laptop; do not assume paths match the owner's machine. Server configuration and database access are outside this workflow unless separately approved.

## Start each approved package

1. Confirm member, assigned branch, approved scope, literal file allowlist, sole owners, shared-file custodian, tests, stop conditions, and checkpoint/PR authority using the [work package template](templates/MEMBER_WORK_PACKAGE_TEMPLATE.md).
2. Save current work through the approved preservation/checkpoint process before switching branches. Unknown dirty work stops the switch; do not automatically stash, commit another member's work, reset, force checkout, or delete files.
3. Run `git status --short --branch`, fetch remote state, switch to clean main, and pull fast-forward only.
4. Return to the assigned branch and integrate main through the package's approved method. Resolve and verify conflicts before implementation; no blind selection of one side. Confirm clean status and record the starting full `origin/main` SHA and assigned-branch HEAD.
5. Report member, assigned/local branches, clone path, base/HEAD/main SHAs, status, authorized scope, protected files, anticipated changed files, shared-file ownership, required tests, and stop conditions before editing. Revalidate open PRs and overlap.

Preparation baseline: `edef1b382b7874af770feb5713c20e9b68979241`. It is historical, not a permanent future implementation base. At preparation PR #63 is OPEN and UNMERGED. `docs/ROADMAP.md`, `docs/IMPLEMENTATION_HISTORY.md`, and `docs/post-defense/10_IMPLEMENTATION_STATUS.md` are frozen for this preparation. Propose changes in handoff; do not edit Antigravity's branch/worktree or treat a checkpoint as its final handoff.

## Windows/XAMPP-aware update example

These are future-package instructions, not commands executed by this preparation. Start within the member's discovered clone. The example assumes the assigned branch exists, the checkout is clean, and merging main is the approved integration method. Stop on any command failure; do not continue automatically.

```powershell
$uiRepo = (git rev-parse --show-toplevel).Trim()
$uiAssigned = 'feat/member1-ui-ux' # Member 2 uses feat/member2-analytics-verification.
git -C $uiRepo status --short --branch
git -C $uiRepo fetch origin
git -C $uiRepo switch main
git -C $uiRepo pull --ff-only origin main
git -C $uiRepo switch $uiAssigned
git -C $uiRepo merge main
git -C $uiRepo status --short --branch
$uiBase = (git -C $uiRepo rev-parse 'origin/main^{commit}').Trim()
git -C $uiRepo rev-parse HEAD
```

Quote revision expressions with braces in PowerShell. If the assigned branch is missing, obtain its approved creation/base instructions rather than starting from whichever branch is active. No database operation is implied by a pull. Preserve local work; `git reset --hard`, `git clean -fd`, and `git push --force` are prohibited outside a separately authorized recovery package.

## Optional AI child branch within the same clone

An agent may use a temporary child branch from its own assigned branch's verified HEAD within that same clone. It must never branch from the other member's branch or create another worktree without explicit exception approval. Record the child base SHA and approved method for returning the tested change to the assigned branch. Avoid simultaneous agents switching the same checkout.

Use compatible names such as `feat/member1-ui-ux--sidebar`, `feat/member1-ui-ux--dashboard-layout`, `feat/member1-ui-ux--gwa-chart`, `feat/member2-analytics-verification--cohort-contract`, `feat/member2-analytics-verification--ui-review`, or `feat/member2-analytics-verification--bugfix`. These are logical child branches, not slash descendants. Git cannot normally store both an existing branch ref `feat/member1-ui-ux` and `feat/member1-ui-ux/sidebar`: the parent ref occupies the path needed for the child directory. The double hyphen avoids that ref file/directory conflict; the same applies to Member 2.

```powershell
# Only for a member's approved AI subtask; start from the clean assigned branch.
git -C $uiRepo switch $uiAssigned
$uiChildBase = (git -C $uiRepo rev-parse HEAD).Trim()
git -C $uiRepo switch -c 'feat/member1-ui-ux--sidebar' $uiChildBase
```

Member 2 substitutes its assigned branch and compatible child name. No example authorizes merging into main, cross-member branch creation, or destructive branch cleanup.

## Ownership, data and verification

Vincent owns sidebar CSS/JS, tokens, all Admin/Faculty/Student dashboard presentation/layout/spacing, content expansion, chart presentation including Student GWA and unavailable cohort UI, legends/tooltips/cards/filters/advanced controls/themes/keyboard/accessibility/desktop integration. Do not divide sidebar and charts. Member 2 verifies formulas, numerators/denominators, periods, missing data, cohort/privacy, authorization/backend/API, regressions, independent review and bugs, and implements only owner-authorized backend/integration fixes. A UI finding goes to Vincent for correction and back to Member 2 for verification. A calculation/backend finding needs owner authorization for Member 2's fix and Vincent's display verification.

No shared file has simultaneous primary editors. Shared entry pages/fixtures have one integration custodian and sequential handoff. Minimal cross-owner fixes are coordinated with Vincent and require explicit transfer if another writer must edit his UI files.

Cohort calculations remain `BLOCKED`. Vincent may prepare the second-series interface, legend, tooltip, unavailable state, accessible table column and empty-state explanation, but cannot invent or simulate its value. Use `Your recorded GWA` and exactly `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` Member 2 must verify all eleven [contract items](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md), followed by final owner approval. No placeholder average, invented privacy threshold, or Administrator pooled recorded-grade mean substitution.

Use the [acceptance matrix](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) and [evidence template](templates/UI_TEST_EVIDENCE_TEMPLATE.md). Inspect test side effects, auth/requests, connections, includes, subprocesses, overrides, writes/cleanup/locks/temp files before execution. Focused checks precede broad relevant checks. Use installed repository-pinned Playwright and record the version; no automatic download. Faculty dashboard/shared dashboard CSS changes require `tests/browser/faculty-dashboard-layout-regression.spec.js` and warning-banner containment.

Reference assets are not production ready. Preserve canonical academic formulas, scale/GWA/zero/status rules and dynamic academic periods. MTR-07 bounds remain `BLOCKED`; canonical MTR-21 referral closure remains `PLANNED` / `BLOCKED`, distinct from limited parent-case median; MTR-19 remains count, not canonical rate. Include `Prototype performance on synthetic academic records` in analytical user-facing output.

No database was accessed in this preparation. Future database access requires separate approval and proven scratch-only mutation paths with no live fallback. Generic scratch advice is not permission to seed, rebuild, migrate, or clean up. No Migration 011, schema/model/dependency/private-template change is authorized here.

## Verified checkpoints, one complete PR, and synchronization

Review committed, unstaged, staged, untracked and combined changes against the explicit allowlist. Stage literal approved files only, inspect the complete staged diff and whitespace check, run required focused verification, make a specific small commit, and push a verified checkpoint to the assigned branch within the package's approved commit/push authority. Existing checkpoint authorization persists; no blanket authority for unrelated files or packages is implied. Preserve evidence outside Git unless repository policy requires tracked evidence.

When the assigned package is complete, confirm a clean branch including the latest approved main, run the required tests, push the exact reviewed commit, and prepare one PR to main after owner review under the approved publication workflow. Supply immutable head SHA, files, categorized tests, accessibility/auth results, protected/database confirmations, limitations and next action. Request owner and independent review; keep auto-merge disabled. Merge requires owner authorization. Do not label branch work merged or a merge deployed.

After an approved merge, record the merge SHA and both members update clean main with `git pull --ff-only origin main`. Confirm the local served repository uses that updated main. Before the next approved package, start from or integrate current main through its approved method; do not keep working indefinitely from a merged feature branch or reset/recreate it without package authority.

This Sol readiness preparation has a stricter current boundary: commit only corrected documentation/reference files after owner review. Until that review/authorization, report the review commit as pending; do not invent an immutable delivery SHA. No push, PR, merge, deployment, migration, or cleanup is authorized for this preparation. Future final [handoffs](templates/UI_CHANGE_HANDOFF_TEMPLATE.md) follow completion or an explicitly requested blocked handoff.

## Owner instruction retained verbatim

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

Retain this in report output. The usage line is historical, not a current measurement. No mobile tests or screenshots under the directive.
