# Repository status reconciliation and publication plan

Documentation: READY FOR OWNER REVIEW. PR63 disposition: pending owner decision.
PR64 runtime/onboarding is already merged and locally deployed at `bdb4a267f30aa31ea36983304a03b01dc28f0fcd`. This plan does not wait for a future Antigravity implementation handoff.

## PR #63 recommendation

**Update and merge PR #63 first after owner review.**
Reviewed head `6d0ece576c74ac4c143719308865aa0c1cacc367`, branch docs/analytics-post-merge-status: OPEN, three documentation files, unmerged, auto-merge disabled. Its valid PR62 merge/Stage B/deployment-verification/history changes are absent from main and should be retained. It was cleanly mergeable at API inspection, but its final deployment-acceptance gate and Phase6 continuation wording are stale after PR64.

Do not merge it unchanged merely because Git reports mergeable. Rebase/merge-base-update decisions and edits to its branch require the owner's explicit authorization; none is done here. Do not close it automatically. A consolidated replacement is a fallback only if the owner chooses that publication route and preserves the complete valid PR62 record plus current PR64 facts before closure.

## Exact updates required in PR63's three files

| Frozen target (unchanged by this reconciliation) | Valid PR63 content to preserve | Necessary update before PR63 acceptance |
| --- | --- | --- |
| docs/ROADMAP.md | PR62 merge edef1b382b7874af770feb5713c20e9b68979241, certified head 03c3d31efe66d376869d5955fe2e57b48cca7bb0, bounded Phases1–5 and incomplete canonical metrics | Record PR64 merge bdb4a267f30aa31ea36983304a03b01dc28f0fcd, final UI and local deployment; schema-only bootstrap plus separate seed/README. Replace pending acceptance/old no-UI-continuation language. Mark only delivered UI complete; manuscript or broader validation stays separate. Future Vincent/Joshua work is post-MVP refinement and unimplemented sidebar. |
| docs/IMPLEMENTATION_HISTORY.md | Chronological PR62 certification/merge/local verification, separately categorized historical evidence and original safety boundaries | Retain historical “pending at that time” context, append distinct PR64 implementation/bootstrap merge/local-deployment milestone. Do not rewrite earlier evidence as new testing or silently erase acceptance history. Record future readiness docs as documentation, not shipped sidebar/cohort. |
| docs/post-defense/10_IMPLEMENTATION_STATUS.md | MERGED bounded Phases1–5, MTR mapping, MTR20 mean and parent-duration distinction, PR62 historical verification | Add current PR64 UI/deployment and onboarding state; supersede obsolete “independent deployment acceptance next” continuation. Separate delivered chart/UI work from still-deferred manuscript/assets. Keep MTR07, canonical MTR21, dated historical Faculty, Student cohort and Migration011 BLOCKED. |

This branch still leaves all three files byte-identical to merged main. The only overlap is semantic/status sequencing, not a current file conflict: PR63 changes those three paths; readiness changes only docs/ui-system. Original PR63's old statements that the protected dump was unchanged describe its historical PR62 package, not PR64's separately authorized bootstrap refresh.

## Current implementation facts the status writer must preserve

PR64 merge: `bdb4a267f30aa31ea36983304a03b01dc28f0fcd`; final implementation head `9f6ed109d968c47fe439eecc816f68ec0167694d`. Local prototype deployment at http://localhost/capstone/ is supplied by the owner. No institutional UAT/genuine-record/production certification is implied.

Single Admin analytics destination with redirects, preserved Overview, two/three comparison groups, overlay/separate cards, chart/details/exact-table hierarchy, real terms/growth, support visuals and duration/n, Faculty scope and dashboard summaries are shipped. Future members must not rebuild them without an approved refinement or verified defect. The collapsible sidebar remains NOT STARTED.

database/udm_radar.sql is schema-only onboarding (18 CREATE TABLE, zero INSERT, DROP TABLE statements); approved schema through010. database/seeds/001_synthetic_demo.sql is separate. database/README.md contains member onboarding. No import into an existing populated database, migration replay, or Migration011.

## Publication order and conflict handling

1. Owner reviews this recommendation and authorizes the PR63 writer to update the existing three-file PR against actual current main.
2. Preserve valid PR62 changes, add current PR64/SQL/local-deployment facts, correct only stale continuation, and validate links/status/limitations. Recheck PR head, base, inventory, mergeability and auto-merge before an authorized merge.
3. After owner-approved PR63 merge, fetch main on the existing readiness branch, inspect the exact new commit and overlap. Integrate approved main by the authorized method; preserve accepted readiness 648207bc563f2bb1aa280d08ab9699f07a4f39c7 and this reconciliation history. Do not overwrite the three status files from an older version.
4. Recheck readiness against final main, run documentation/reference checks, obtain separate owner authorization to push and open one readiness documentation PR. Auto-merge stays off; merge authorization is separate.
5. After its reviewed merge, Vincent/Joshua clone or update main once; record the resulting full SHA containing PR64 and accepted documentation, then start only approved post-MVP portal packages with shared custodians.
6. If owner instead consolidates status/readiness into one PR, explicitly transfer the three-file allowlist and preserve all valid PR63 content. Close PR63 only after replacement preservation/acceptance is verified and owner authorizes closure.

A new conflicting main change stops only the affected integration; preserve local work. No reset/force-push/manual file copying or automatic publication/closure. This reconciliation authorizes only local documentation commits.

## Remaining contracts

MTR07 empirical range, canonical MTR21 lifecycle, dated historical Faculty attribution, numeric Student cohort and Migration011 remain BLOCKED. MTR19 remains bounded count; MTR20 is arithmetic mean of distinct eligible referral→first-notice durations; parent-case median is a different measure. Joshua owns Student calculations/privacy/interface and eleven-item cohort proposal; Vincent owns Administrator/Faculty. Both self-verify and fix their own portals, with supplemental cross-review and one shared-file custodian.

No database connection, SQL execution, source/model/dependency change, mobile test, screenshot, PR action or deployment occurs in this documentation package.
