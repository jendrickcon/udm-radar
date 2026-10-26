# Future member agent master prompt — [WP-ID]

Template only. The owner must fill and approve the work package, exact file allowlist, baseline, permissions, and completion phrases before execution. This prompt does not authorize production work by itself.

## Read first

Read applicable `AGENTS.md`, `CONTRIBUTING.md`, canonical `docs/ROADMAP.md`, `docs/IMPLEMENTATION_HISTORY.md`, `docs/UI_UX_GOVERNANCE.md`, `docs/UI_UX_FINDINGS_REGISTER.md`, post-defense context/status/metric reconciliation, the approved package [path], and [UI context](../00_UI_SYSTEM_CONTEXT.md), [agent protocol](../09_AI_AGENT_UI_IMPLEMENTATION_PROTOCOL.md), [ownership](../11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [acceptance](../10_UI_TEST_AND_ACCEPTANCE_MATRIX.md), and [reference guide](../13_REFERENCE_ASSET_USAGE_GUIDE.md). Discover and read applicable repository skills from this checkout; do not assume another host loaded them.

## Assigned work

- Owner approval evidence: [Exact instruction/date].
- Member/package: [Vincent — UI/UX, Sidebar, Dashboard and Chart Experience; or owner-named Member 2 — Analytics Calculations, Verification, Testing and Bug Fixes].
- Objective / non-goals: [Exact scope].
- Approved baseline / assigned branch / local branch / clone: [Full SHA; Vincent feat/member1-ui-ux or Member 2 feat/member2-analytics-verification; actual local branch; discovered absolute path].
- Approved literal files and primary owners: [List].
- Integration custodian / status writer / contract dependencies: [Names and exact SHAs].
- Focused checks and acceptance conditions: [Commands/categories/targets].
- Delivery authority: [Verified checkpoint commit/push conditions in the approved future package; one complete PR after owner review; merge/deploy/migrate/cleanup separately authorized].

Before editing, report member, assigned/local branches, discovered clone path, starting baseline, actual HEAD/origin/main SHAs, `git status --short --branch`, authorized scope, protected files, anticipated changes, shared-file primary owners, tests and stop conditions. Use the member's one existing clone and assigned long-lived branch. Save current work, fetch, update clean main with `--ff-only`, return to assigned branch and integrate main by the approved method; resolve conflicts and confirm clean status before implementation. Preserve unknown dirty work. No hard resets, forced checkout, automatic stashing or recovery commits. Quote PowerShell revision expressions containing braces.

Vincent is the single primary for all UI/sidebar CSS/JS, tokens, dashboard presentation/layout/spacing across all portals, content expansion, chart/graph presentation, legends, tooltips, chart cards, filters/advanced controls, themes, keyboard/accessibility, desktop behavior, and visual integration. Do not split sidebar and charts. Member 2 owns verification/calculations/data/privacy/authorization/backend/API/regressions/independent review/bugs within approved scope, may propose failing UI tests/findings, and cannot independently restyle or modify Vincent's files. Coordinate any separately approved minimal integration fix with Vincent and record an explicit temporary transfer if another writer must edit his file. Shared entry pages/fixtures require a custodian and sequential handoff.

Keep future UI `NOT IMPLEMENTED` / `PLANNED` until implemented and evidenced. Cohort data/privacy remains `BLOCKED`, not deferred. Vincent cannot invent the formula. Use `Your recorded GWA` and `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No populated/placeholder average, invented suppression threshold or pooled-mean relabeling. Member 2 must verify eleven [cohort contract](../05_GWA_TREND_COMPARISON_CHART_CONTRACT.md) items; the owner's final approval follows those eleven items.

Preserve `Prototype performance on synthetic academic records`, canonical academic calculations and separation of risk/outcomes/standing/pacing/benefits. MTR-07 bounds remain `BLOCKED` without verified active-model held-out residual evidence. Canonical MTR-21 referral closure remains `PLANNED` / `BLOCKED`, distinct from limited parent-case median. MTR-19 is bounded receipt count, not canonical rate. No genuine records, new model/library/dependency/schema, private PEF/CC changes, Migration 011 or unapproved API/auth/formula change.

No database access by default. Separately authorized tests require inspection of direct connections, includes/handlers, subprocesses, overrides, writes/cleanup/locks/temp files and proof of scratch-only mutation paths with no live fallback. Generic scratch advice does not permit seeding, rebuilds, migrations, or cleanup. Reference code is not production ready; adapt only within the approved package.

Give concise useful progress at milestones and avoid more than about 60 seconds of silence. Share current task, completed work, early findings, blocker and next action; avoid repeated plans/tool spam. Continue independent authorized work. Put long instructions in Markdown files. Inspect test effects, run focused checks before broad relevant checks, verify installed Playwright pin/version, preserve failures, and keep generated artifacts ignored. Faculty dashboard/shared-layout changes require its layout regression. Classify evidence accurately; never sum check counts into accuracy or describe source inspection as end-to-end coverage.

Retain the owner instruction verbatim in report output:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

This is a historical quote, not a live usage measurement. No mobile tests or screenshots under the directive.

Review committed, unstaged, staged, untracked and combined changes against the allowlist. Within approved future-package checkpoint authority, stage explicit files, review the full staged diff, verify focused checks, make small meaningful commits and push verified checkpoints to the assigned branch. At completion prepare one complete PR after owner review, with exact reviewed head and auto-merge off. Merge/deploy/migrate/cleanup/worktree removal still need separate authority. Current Sol preparation: commit only after owner review; report its immutable review commit as pending until then, with no preparation push/PR. Preserve frozen PR/status boundaries until authorized reconciliation.

Return a [complete handoff](UI_CHANGE_HANDOFF_TEMPLATE.md) after completion or an explicitly requested blocked handoff. Include actual commands/results by category, protected-system evidence, exact immutable commit if authorized, truthful status, limitations, and next owner action.

Exact future package success phrase: [Owner-supplied phrase].

Exact future package blocked-handoff phrase: [Owner-supplied phrase].

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
