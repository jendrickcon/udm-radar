# Member portal contribution workflow

Documentation: READY FOR OWNER REVIEW. Member implementation: NOT STARTED.

## Authority and assigned work

Read [AGENTS](../../AGENTS.md), [contribution policy](../../CONTRIBUTING.md), [roadmap](../ROADMAP.md), [history](../IMPLEMENTATION_HISTORY.md), [UI governance](../UI_UX_GOVERNANCE.md), [context](00_UI_SYSTEM_CONTEXT.md), [ownership](11_UI_OWNERSHIP_AND_CONFLICT_MAP.md), [acceptance](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) and the approved package. Older guides' mandatory-worktree and general checkpoint-push examples are superseded by current owner direction.

| Member | Assigned long-lived branch | Package |
| --- | --- | --- |
| Vincent | `feat/member1-admin-faculty-ui` | `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION` |
| Joshua | `feat/member2-student-portal` | `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION` |

Each human uses one local clone and one assigned branch. Verify whether the assigned branch exists when starting; this document does not create it. Creation needs an approved current-main base. Additional worktrees are owner-authorized exceptions, never routine member setup.

## Start an approved package

1. Confirm member, portal, assigned branch, approved literal files, shared custodians, scope, tests, stop conditions and delivery authority.
2. Preserve existing work through the authorized process. Unexpected dirty work stops switching; no automatic stash, reset, restore, forced checkout or cleanup.
3. Inspect status, fetch, switch to clean main and pull fast-forward only. Return to the assigned branch and integrate approved main using the package-approved method; resolve and verify conflicts.
4. Record full main/HEAD SHAs, absolute clone/served paths, clean status and current PR overlap before editing. Discover each laptop's XAMPP mapping; no assumed paths/server changes.
5. Both members implement, self-verify, fix and document their own portals. Cross-review never substitutes for their unit/integration/auth/browser/theme/accessibility checks.

Example for an already authorized clean package; stop on any failed command:

```powershell
$uiRepo = (git rev-parse --show-toplevel).Trim()
$uiAssigned = 'feat/member1-admin-faculty-ui' # Joshua: feat/member2-student-portal.
git -C $uiRepo status --short --branch
git -C $uiRepo fetch origin
git -C $uiRepo switch main
git -C $uiRepo pull --ff-only origin main
git -C $uiRepo switch $uiAssigned
git -C $uiRepo merge main # Only if merge is the package-approved integration method.
git -C $uiRepo status --short --branch
git -C $uiRepo rev-parse 'origin/main^{commit}'
git -C $uiRepo rev-parse HEAD
```

Optional AI task branches start from the member's own assigned HEAD in the same clone. Compatible examples: `feat/member1-admin-faculty-ui--sidebar`, `feat/member2-student-portal--gwa-chart`. Slash children beneath an existing parent ref cannot coexist because of Git's ref file/directory conflict. No branch from the other member and no concurrent agents switching one checkout.

## Shared contracts and verification

Assign one integration custodian per exact shared file before editing. Prefer portal-specific hooks; never two simultaneous editors on shared CSS/JS, header/sidebar, API, fixtures or documentation. Agree contracts first, implement portal work independently, self-verify independently, then review the package with fewer shared edits first. The second member updates main and reconciles before the next PR.

Vincent consumes approved backend values; a new shared field requires a documented presentation request and named technical owner. Joshua documents any Student field's API effects and Administrator/Faculty regressions. A finding returns to the original portal implementer unless shared ownership or explicit owner transfer applies.

Inspect test connections, handlers, subprocesses, writes, cleanup and auth effects before running them. No DB access is authorized by this preparation. Future isolated tests require separate target approval with no existing-DB fallback, provenance and cleanup evidence. Migration 011 remains excluded. Use the pinned installed desktop runner; do not install dependencies or select mixed mobile/screenshot suites. Faculty dashboard/shared layout changes require the Faculty structural regression.

## Definition of Done — required for each portal package
A package is not done because its page renders. Both Vincent and Joshua must supply:
1. Implemented approved scope on the assigned branch.
2. Passing portal-specific unit checks.
3. Passing relevant integration checks under separately authorized isolated targets.
4. Passing authenticated desktop workflows.
5. Passing negative authorization tests.
6. Passing light and dark themes, including cards, axes, legends, tooltips and details.
7. Passing keyboard and visible-focus checks.
8. No page horizontal overflow at 1440×900,1280×600,1024×768; one primary page scroll owner.
9. Exact accessible tables matching chart values, populations, units and periods.
10. Complete, empty, provisional, unavailable and noncomparable states tested.
11. Cross-portal regressions for affected shared contracts and navigation.
12. Protected-file integrity evidence.
13. No unauthorized database/schema/migration work.
14. Clean clone/worktree and complete diff review.
15. Full immutable candidate SHA and complete evidence handoff, when commit authority exists.
16. Owner retest of visible workflows, with defects resolved before acceptance.
17. Separate push, PR, merge and deployment authorization; self-verification grants none automatically.
Unavailable required evidence keeps the gate BLOCKED; inapplicable tests require a reason and owner acceptance, not an invented pass. Report PHP/SQL/authenticated/generated/mocked/structural/accessibility/syntax/integrity separately. Cross-review does not replace self-verification; fixes return to the portal implementer unless the owner explicitly transfers them.


## Candidate, owner review and publication

Review committed, unstaged, staged, untracked and combined changes. Stage only literal approved paths; inspect the staged diff and whitespace. Commit authority must be explicit. Implement → self-verify → authorized immutable candidate → complete owner handoff → owner visible retest and verified fixes → separately authorized push → one reviewed PR against verified current main, auto-merge off → separately authorized merge → separately authorized deployment.

No push before separate owner authorization. After an approved merge, record merge SHA; both members pull updated clean main fast-forward-only before further work. GitHub main is authoritative; no ZIP/manual source copying. Preserve worktrees/branches unless cleanup is separately authorized.

Preserved readiness `648207bc563f2bb1aa280d08ab9699f07a4f39c7` is owner-accepted. This final reconciliation is authorized for a local documentation commit only; no push or PR.

## Owner instruction retained verbatim

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is historical, not a current measurement. No mobile tests or screenshots are authorized. Future desktop acceptance covers 1440×900, 1280×600 and 1024×768, light and dark.

## Updated starting instructions — Vincent and Joshua

1. PR63 is merged at c29d68a7ef961c0be61ffbf296a10918303d5fea; wait for the owner's separate readiness-publication decision. One local clone each: clone current main if none exists; otherwise preserve authorized work, fetch and fast-forward clean main. Never clone an old readiness or analytics feature branch as the team starting point.
2. Record the actual full main SHA. It must contain PR64 runtime baseline bdb4a267f30aa31ea36983304a03b01dc28f0fcd, merged PR63 documentation main c29d68a7ef961c0be61ffbf296a10918303d5fea and the later owner-approved readiness merge. Review intervening changes; a later unknown SHA is not automatically an approved baseline.
3. Vincent uses feat/member1-admin-faculty-ui for approved Administrator/Faculty post-MVP refinements. Joshua uses feat/member2-student-portal for approved Student refinements/calculation/privacy verification. Create a missing assigned branch only under its approved package and base; no extra clone/worktree default.
4. Read [database/README.md](../../database/README.md) from that updated main before local setup. On a fresh, empty member-owned local database only, import [database/udm_radar.sql](../../database/udm_radar.sql) as the schema bootstrap; it contains18 CREATE TABLE definitions and zero INSERT statements, and also DROP TABLE statements. Never run this bootstrap over an existing populated database or the owner's existing udm_radar/udm_radar_scratch.
5. If the approved setup needs demonstration data, import [database/seeds/001_synthetic_demo.sql](../../database/seeds/001_synthetic_demo.sql) separately after schema. It refuses existing user accounts. Use the guide's documented synthetic logins; do not copy genuine records or commit local exports/credentials.
6. Follow the guide for machine-local DB configuration and [schema verification](../../database/verify/verify_schema.sql). Its Windows redirection examples are Command Prompt instructions, not PowerShell commands to execute blindly. Do not replay migrations000–010 after the bootstrap; Migration011 remains BLOCKED. All setup execution requires the appropriate human/owner authorization and a proven fresh target; this reconciliation runs no SQL.
7. Compare the live accepted MVP against the approved refinement/finding. Existing analytics tabs/cards/changes/support/trends/dashboard summaries are preservation/regression scope, not tasks to rebuild. Agree exact shared-file custodians and API impact before edits.
8. Self-verify each member's own implementation at the supported desktop sizes/themes, then hand off an authorized immutable candidate for owner retest. Publication gates remain separate. Both update main after approved merges.
