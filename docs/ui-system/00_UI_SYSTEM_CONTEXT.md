# Future UI, member and agent readiness
Package: FUTURE-UI-CHARTS-AND-MEMBER-AGENT-READINESS-WP1
Prepared 26 October 2026. Preparation only; no production implementation or rollout.
Baseline: `edef1b382b7874af770feb5713c20e9b68979241`; tree `a434326da226198c404d1c6aef521b5cf0c525e8`.
Branch: `docs/future-ui-member-agent-readiness`. The final report identifies this separately authorized managed worktree and the validated uncommitted snapshot; a new immutable commit awaits owner review.

## Purpose and authority
Give Vincent and Member 2 durable contracts, reference assets and review templates for later owner-authorized UI work. The owner cannot always supply a laptop, so the package must be usable by members and their agents without guessing formulas, ownership or release authority.
Read [AGENTS](../../AGENTS.md), [contribution policy](../../CONTRIBUTING.md), [roadmap](../ROADMAP.md), [implementation history](../IMPLEMENTATION_HISTORY.md), [UI governance](../UI_UX_GOVERNANCE.md) and [findings register](../UI_UX_FINDINGS_REGISTER.md) first. This package proposes future work; it does not replace those authorities or silently amend PR #63.

## Current isolation record
Remote fetch/prune completed before preparation. origin/main was the baseline above.
[PR #63](https://github.com/jendrickcon/udm-radar/pull/63) was OPEN, unmerged, auto-merge disabled, base edef1b382b7874af770feb5713c20e9b68979241 and head 6d0ece576c74ac4c143719308865aa0c1cacc367. Its files are frozen: docs/ROADMAP.md, docs/IMPLEMENTATION_HISTORY.md, docs/post-defense/10_IMPLEMENTATION_STATUS.md. No changes here depend on merging it. Recheck before future integration.
Antigravity's visible branch is feat/analytics-ui-integration in C:/xampp/htdocs/udm-radar-analytics-ui-integration. Git metadata showed checkpoint 7af3c26f2016e13554b4e0de7f43dc72b97698a5; that is NOT a final handoff or reviewed immutable implementation. Only path/status metadata was inspected to prove no docs/ui-system overlap; the worktree was not edited or certified.
No existing DB, application page or production browser test is accessed by this preparation. Earlier deployment evidence and Stage A preparation are preserved separately.

## Existing UI pattern inventory
These are source observations at the immutable baseline, not a rendering pass. Antigravity's later accepted integration must be audited again before future implementation.

| Area and source | Observed baseline | Classification / action |
| --- | --- | --- |
| Shared shell: includes/header.php, includes/sidebar.php | Header initializes CCS/classic, light/dark/system, text size and motion before paint; Inter and dashboard.css are shared. Sidebar is280px, icons20px, active-link class and badge/footer controls. | Canonical pattern; preserve preferences and names |
| Admin Dashboard: admin/index.php | Work-attention banner, advisory, actionable stat cards, risk plot, year/section scope and searchable/filterable roster. | Canonical purpose; compact new summaries must not duplicate full analytics |
| Program Analytics: admin/analytics.php | Six GET filters sy/semester/period/year_level/subject_id/section, Apply/Reset, cards, risk/distinction charts, section/curriculum tables, collapsed detailed subject-section view. | Accepted page variation; filter simplification belongs to Vincent's later unified scope |
| Comparative view: includes/analytics/presentation.php | Eight native scope fields, Apply/Clear, disclosure, recorded results/trend/comparison/support tables and methodology. Admin has a second comparative route. | Accepted bounded MVP; immediate integration belongs to Antigravity, not this prep |
| Faculty: faculty/dashboard.php, faculty/analytics.php | Attention banner, advisory, KPI/section grids, roster class/overall tabs and grade modal; Class Analytics uses assigned-load cards with risk disclosures. | Canonical role pattern; banner containment and assigned scope protected |
| Chart implementations | Existing portal Chart.js canvas charts coexist with newer scoped SVG line/bar and adjacent tables. Some older cards reserve380px height. | Accepted variations; fixed-height blank space is a future consolidation candidate |
| Cards, spacing and type | Shared12px radius,24px card padding,20px stat padding/gap,24px section gap,40px content gutter,2rem title/value and1.2rem table/section heading. | Canonical pattern; proposed compact density remains separately marked |
| Forms/buttons | Native labels/selects, tokenized6px control borders, primary/secondary actions, analytics44px targets; some local inline form styles. | Canonical controls; inline duplication is legacy variation |
| Tables | Named data-table-scroll regions, tokenized headers/borders, localized horizontal scroll and in-body empty rows; older page-specific empty paragraphs also exist. | Canonical containment; empty-state differences are consolidation candidates |
| Loading/error | Shared academic analytics uses aria-busy/status/alert, AbortController and generation ticket; hides stale results during load/failure. | Accepted async contract; preserve latest-response semantics |
| Focus/help | Shared focus-visible, guide modal, tooltip/disclosure and focus-return patterns. Essential data meaning remains visible. | Canonical requirement; inherited class does not prove actual keyboard behavior |
| Desktop/mobile split | >900px persistent sidebar; <=900px drawer; existing1280/1100/1024 bands and smaller breakpoints. | Canonical responsive behavior; only three desktop sizes are in this package's future acceptance |
| Token/motion drift | Legacy style.css sidebar240px and parallel color definitions; some old200–300ms transitions exceed current150–200ms governance. | Legacy variation; document without production consolidation now |

Detailed source values and proposed aliases are in the token contract. Findings are observations, not authorization to amend the frozen findings register or redesign Antigravity's current implementation.

## Approved direction
- Vincent: **UI/UX, Sidebar, Dashboard and Chart Experience**. One primary owner for all UI tokens, shared sidebar CSS/JS, chart layout/card styling and portal presentation. No sidebar-versus-chart member split.
- Member 2: **Analytics Calculations, Verification, Testing and Bug Fixes**. Verifies formulas, denominators, missingness, data/privacy contracts, API/authorization, regressions and Vincent's immutable implementation; fixes only after owner authorization.
- Cohort line status: **BLOCKED**, not deferred. Active label: **Your recorded GWA**. Disabled label: **Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.**.
- Reference files are non-production examples; no page imports them and no new library/CDN is introduced. Production behavior, formulas, dependencies, migrations, models and authentication remain unchanged.

## Package map
[Visual boundaries](01_VISUAL_DIRECTION_AND_BOUNDARIES.md) · [tokens](02_DESIGN_TOKENS_AND_THEME_CONTRACT.md) · [sidebar](03_COLLAPSIBLE_SIDEBAR_SPECIFICATION.md) · [charts](04_CHART_AND_DATA_VISUALIZATION_STANDARD.md) · [GWA data contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md) · [components](06_COMPONENT_AND_STATE_PATTERNS.md) · [accessibility](07_ACCESSIBILITY_AND_KEYBOARD_CONTRACT.md) · [member workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md) · [agent protocol](09_AI_AGENT_UI_IMPLEMENTATION_PROTOCOL.md) · [acceptance](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) · [ownership](11_UI_OWNERSHIP_AND_CONFLICT_MAP.md) · [sequence](12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) · [reference usage](13_REFERENCE_ASSET_USAGE_GUIDE.md) · [handoffs](14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md) · [risks](15_KNOWN_RISKS_AND_STOP_CONDITIONS.md).

## Preparation plan and checkpoints
1. Record remote/PR/active-worktree boundaries; audit canonical sources without running application handlers.
2. Write the sixteen contracts, seven templates and scoped inert reference assets; validate with the owner's unified Vincent/Member 2 correction.
3. Validate links/paths, terminology, reference syntax and reference-only contract tests; prove no production imports or protected changes.
4. Inspect all Git change layers and exact file allowlist; normalize text; present the exact validated diff for owner review; commit only after that review.
5. Return the validated preparation for owner review; after review authorizes a commit, return its immutable SHA and clean state. No push, new PR, merge, deploy or cleanup.

This is also the preparation plan requested by the planning workflow; the owner's docs/ui-system location overrides generic skill plan paths. The detailed master and subsequent owner corrections authorize preparing these artifacts, not implementing the future production design.

## Owner desktop directive
> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

Future accepted desktop checks: 1440×900, 1024×768, 1280×600, light/dark. Mobile implementation/testing and screenshots require a later owner scope change.

## Latest owner workflow correction
Human members use one local clone each. Vincent's assigned long-lived branch is feat/member1-ui-ux; Member 2's is feat/member2-analytics-verification. AI task branches stay inside that clone and start only from the member's assigned branch. Extra worktrees are exceptional and require explicit owner authorization. The existing Sol preparation worktree was separately authorized and is not the human default.
Members make regular verified checkpoint commits/pushes within their approved package, then one completed PR for owner/independent review; no automatic merge/deploy. Before the next approved package, update main fast-forward and integrate it using the package-approved method. GitHub main after approved merges is authoritative; no manual ZIP/source copying between laptops.
For this preparation specifically, the owner's latest correction requires owner review before any commit. Therefore the report must state commit pending review, not fabricate an immutable SHA.
