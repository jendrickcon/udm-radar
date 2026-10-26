# UI ownership and conflict map

Status: `PLANNED` ownership structure; future UI is `NOT IMPLEMENTED`. Vincent owns the unified UI experience on `feat/member1-ui-ux`; unnamed Member 2 owns analytics calculations/verification/testing on `feat/member2-analytics-verification`. Each uses one local clone and that assigned long-lived branch. Exact production files, baselines, tests and checkpoint/publication authority still require package approval.

## Owner-directed member split

| Member/package | Sole primary responsibility | Boundary |
|---|---|---|
| Member 1 — Vincent: UI/UX, Sidebar, Dashboard and Chart Experience | Sidebar CSS/JS, tokens, every Admin/Faculty/Student dashboard's presentation/layout/spacing, content expansion, chart/graph presentation including Student GWA and the cohort unavailable interface, legends, tooltips, chart cards, filters/advanced controls, light/dark themes, keyboard/accessibility, desktop behavior, and visual integration | Vincent cannot invent or change the cohort calculation/privacy contract, analytics formulas, role authorization, or backend behavior through UI ownership |
| Member 2 — unnamed: Analytics Calculations, Verification, Testing and Bug Fixes | Formulas, numerators/denominators, missing-data rules, comparison/cohort data and privacy contract, role authorization/backend/API verification, regression tests, independent review, bug reports, and separately owner-authorized backend/integration fixes | May propose failing UI tests and findings; may not independently restyle or modify Vincent's UI files |

The sidebar and charts are one UI ownership area. All shared UI/sidebar CSS/JS, chart layout/styles, and design tokens remain Vincent's primary ownership. Member 2 is not a second UI implementation owner. Neither member receives a production allowlist or commit/publication authority from this map alone.

## One primary per file

Replace each anticipated area below with exact literal paths in the approved [work package](templates/MEMBER_WORK_PACKAGE_TEMPLATE.md). If a file mixes presentation and backend logic, do not assign simultaneous ownership by function or line. Name one primary and a sequential integration sequence.

| Anticipated area | Sole primary / route | Conflict rule |
|---|---|---|
| Shared design tokens | Vincent | Member 2 submits findings; Vincent applies approved UI changes |
| Sidebar CSS and controller JavaScript | Vincent | Never split sidebar ownership from chart ownership |
| Dashboard layout, spacing, content expansion | Vincent | All portals remain within the unified UI package |
| Chart adapter presentation, chart CSS/layout, legends, tooltips, cards and accessible table presentation | Vincent | Member 2 verifies data semantics and reports issues without restyling |
| Filters/advanced controls, themes, keyboard/focus/accessibility and desktop visual integration | Vincent | Data/auth behavior needs Member 2 verification and approved backend ownership |
| Analytics calculations/data contract/backend/API changes | Member 2 only within explicit owner-approved backend files | Vincent consumes accepted values and must not invent formulas |
| Package-specific isolated tests and review reports | Named package primary in explicit allowlist | Member 2 may add approved failing UI tests/findings; report real failures |
| Shared production entry pages mixing UI/backend | Owner-appointed integration custodian with Vincent as UI primary | Sequential coordinated edits; explicit temporary transfer if another writer edits Vincent's file |
| Shared fixtures and test helpers | One owner-appointed integration custodian | Members submit test changes; custodian applies sequentially |
| Shared documentation/status records | One owner-designated status writer | Members propose ledger updates in handoffs; no competing writes |

No file has simultaneous primary owners. Review access does not transfer edit authority. If a file is already owned or a minimal fix crosses ownership, stop that edit until the owner records the exact temporary transfer, scope, sequence, and return to Vincent. No independent UI restyling by Member 2.

## Cohort gate

The cohort data/privacy contract is `BLOCKED`, not deferred. Vincent owns the explicitly approved unavailable interface and cannot invent its formula. The active Student label is `Your recorded GWA`. Use exactly `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` for the blocked series. No average calculation, placeholder average, suppression threshold, pooled-mean relabeling, or Faculty-assignment default is authorized.

Member 2 must verify and document eleven items: (1) Student-level unit of analysis; (2) comparable cohort definition; (3) eligible records; (4) excluded records; (5) missing/incomplete-term behavior; (6) minimum privacy-safe cohort size; (7) aggregation/unit-weighting; (8) school-year/semester comparability; (9) role visibility; (10) synthetic-data test evidence; (11) privacy/fairness review. Final owner approval follows all eleven items. See the [chart contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md). A verified proposal without that approval does not unblock the average. Vincent may prepare the second-series interface, legend, tooltip, unavailable state, accessible table column and explanation, but cannot define or simulate the value or substitute the Administrator pooled recorded-grade mean.

## Integration sequence

1. Owner approves member packages with exact files, their assigned long-lived branches in existing member clones, baseline SHAs, tests, verified checkpoint commit/push authority, and one custodian for each shared entry page/fixture. Record Vincent's unified UI ownership. Start with saved work, status, fetch, clean main fast-forward update, return to assigned branch, approved main integration, conflict resolution and clean status.
2. Vincent prepares the UI contract and owned presentation work. The reference event is `udmradar:reference-layout-change` with `{ collapsed: boolean, phase: 'settled' }`; reference tokens use `--ui-ref-*`. These remain reference-only until adapted and approved for production.
3. Member 2 verifies existing analytics contracts and authorized regression evidence independently. Report defects with exact SHA/file/expected/actual evidence. UI findings return to Vincent; backend fixes require separately approved backend files. Do not silently mark a failing test passing or rewrite a UI to avoid its assertion.
4. Vincent completes scoped UI fixes and desktop verification, makes meaningful verified commits and pushes checkpoints to his assigned branch under the approved package. Member 2 reviews the exact candidate SHA. Member 2 follows the same verified checkpoint process for approved technical files on their assigned branch. A blocked transfer requires an explicitly requested blocked handoff.
5. Custodian inspects candidates and current main, then integrates shared entry-page/fixture changes sequentially. An owner-approved minimal integration fix is coordinated with Vincent, and a temporary transfer is explicit if another writer must edit his file. Record source SHAs and return of primary ownership.
6. Run relevant combined desktop layout/chart/focus/theme checks, including the Faculty dashboard layout regression when required. Record actual results and limitations; component checks do not establish authenticated application coverage.
7. At package completion, confirm clean status and latest approved main, push the exact reviewed commit and prepare one complete PR after owner review under the approved workflow. Provide immutable head SHA, files and categorized evidence; request owner/independent review and disable auto-merge. Merge requires owner authority; deployment, migration and cleanup remain separate.

Preserve unknown local changes. No hard resets, forced checkouts, automatic stashes, or cleanup of another member's work. Use discovered absolute clone/served paths in Windows/XAMPP setups. AI child branches may be created within the member's clone from their own assigned HEAD, never from the other member's branch. Use compatible refs such as `feat/member1-ui-ux--sidebar` or `feat/member2-analytics-verification--cohort-contract`: slash descendants under the actual assigned branch conflict with Git's ref file/directory path. Additional worktrees require explicit owner exception approval, not routine setup. Follow the [member workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md).

After approved merges, GitHub main is authoritative: record the merge SHA, both members pull clean main fast-forward-only, verify the served repository is updated, and begin the next approved package from or integrated with current main. Do not exchange source folders/ZIP files or keep working indefinitely from the merged branch. Branch reset/recreation needs the next package's authority.

## Frozen preparation boundary

Preparation starts at `edef1b382b7874af770feb5713c20e9b68979241`. PR #63 is OPEN and UNMERGED at preparation. Its frozen paths are `docs/ROADMAP.md`, `docs/IMPLEMENTATION_HISTORY.md`, and `docs/post-defense/10_IMPLEMENTATION_STATUS.md`. Antigravity owns its separate analytics integration branch/worktree; no checkpoint is its final handoff. This preparation writes only documentation/reference assets under `docs/ui-system/`.

Before future implementation, revalidate main, PR state, assigned/local branches, clone status, existing owners and the approved allowlist. Main overlap or PR #63 reconciliation requiring an owner decision stops the affected edit. Propose status changes in handoff instead of editing frozen ledgers. The current Sol preparation may be committed only after owner review; its review commit remains pending until then. No preparation push/PR is authorized.

Use the [owner decision template](templates/OWNER_DECISION_TEMPLATE.md) for each transfer: literal paths, current primary, temporary receiver, exact immutable SHA and dirty-state disclosure, reason, permitted minimal edit, application order, tests, approval, and ownership-return condition. Informal help is not an ownership transfer.

MTR-07 bounds remain `BLOCKED`; canonical MTR-21 is `PLANNED` / `BLOCKED`, separate from the limited parent-case median; MTR-19 remains count only. No database access, migration, new dependency, genuine records, mobile testing, or screenshots follows from this map.
