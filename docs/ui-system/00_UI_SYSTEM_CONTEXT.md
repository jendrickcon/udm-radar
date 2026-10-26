# Future UI, member and agent readiness

Package: FUTURE-UI-CHARTS-AND-MEMBER-AGENT-READINESS-WP1.
Updated 26 October 2026 by the owner portal-ownership correction and roadmap/goals addendum.
Documentation: READY FOR OWNER REVIEW. Future member implementation: NOT STARTED.
Production baseline: `edef1b382b7874af770feb5713c20e9b68979241`; tree `a434326da226198c404d1c6aef521b5cf0c525e8`.
Preparation branch: `docs/future-ui-member-agent-readiness`.
Earlier approved local 30-file documentation commit: `ee4ddf4b5e001328a1beeeea3471d58dd87c1614`.
These new amendments add the reconciliation plan, making 31 package files, and remain uncommitted pending explicit owner review/approval.

## Purpose and product goal

> Deliver a coherent, understandable, role-appropriate academic analytics experience across Administrator, Faculty, and Student portals, where charts and summaries explain current results, comparable periods, growth or change, coverage, sample size, support activity, and unavailable evidence without ranking Faculty, fabricating comparisons, or overwhelming users with technical text and controls.

Give both members durable contracts, reference assets and review templates for later approved portal work. Read [AGENTS](../../AGENTS.md), [contribution policy](../../CONTRIBUTING.md), [roadmap](../ROADMAP.md), [history](../IMPLEMENTATION_HISTORY.md), [UI governance](../UI_UX_GOVERNANCE.md) and [findings register](../UI_UX_FINDINGS_REGISTER.md). This preparation proposes future changes; it does not silently supersede those repository authorities.

## Current isolation record

A fresh read-only remote-ref check found actual main and cached origin/main unchanged at the baseline above. No fetch/branch mutation was needed for this amendment. [PR #63](https://github.com/jendrickcon/udm-radar/pull/63) metadata: OPEN, unmerged, auto-merge disabled, base `edef1b382b7874af770feb5713c20e9b68979241`, head `6d0ece576c74ac4c143719308865aa0c1cacc367`, three files. Freeze docs/ROADMAP.md, docs/IMPLEMENTATION_HISTORY.md and docs/post-defense/10_IMPLEMENTATION_STATUS.md.

At 12:12 UTC on 26 October 2026, Antigravity's separate feat/analytics-ui-integration worktree metadata showed `497c4c47ae44a28d580d0f35ee371cb7121f4a88` and clean status, ahead of origin/main by six commits. No final immutable handoff or acceptance was supplied to this package, so Phase A remains IN PROGRESS. Only metadata was checked; no source was reviewed/certified/edited. New member branches were absent from checked refs; none was created. Recheck all statuses before future action.

PR #62 remains merged/deployed per the owner's accepted prior deployment record; that historical runtime result was not re-executed here. No production page, existing DB or browser test is accessed by this preparation.

## Approved direction and member responsibility

Vincent owns Administrator and Faculty portal implementation, dashboard/sidebar/charts/filter/support/theme/accessibility work, own calculation-display/authorization/regression tests and portal-level fixes. Package: `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION`; branch `feat/member1-admin-faculty-ui`.

Member 2 owns Student implementation, Dashboard/Academic Analytics, recorded GWA/change/coverage/prediction clarity, Goal Calculator/Grades/history/Enrollment integration, Student sidebar/charts/themes/accessibility, Student calculation/privacy contracts, own verification and fixes. Package: `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION`; branch `feat/member2-student-portal`.

Both implement, test, verify, document and fix their assigned portals. Cross-review supplements own tests. Neither is universal UI/backend owner or tester-only. Shared global files require one owner-assigned integration custodian per literal file and sequential edits. Do not split a portal's sidebar and charts between members.

The [ten goals](01_VISUAL_DIRECTION_AND_BOUNDARIES.md) cover coherent role-specific analytics; visual-first charts/details with exact tables below; comparable growth/change; real academic periods; two groups plus optional third/no fourth; support flow/durations/n; complete themes; context filters and one main scroll owner; mandatory self-verification; truthfulness/fairness. The future Vincent sidebar does not block current MVP publication.

Cohort numeric series stays BLOCKED. Member 2 owns its Student unavailable interface and eleven-item proposal; final owner approval precedes any numeric release. Active label: “Your recorded GWA”. Disabled explanation: “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.” No pooled administrative mean substitution, fabricated values or threshold.

MTR-07, canonical MTR-21 and historical Faculty attribution remain BLOCKED. MTR-19 is bounded count; limited parent-case median is separate; MTR-20 arithmetic mean remains unchanged and its display uses “Average time before the first notice” plus human-readable duration/actual n.

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

## Package map

[Visual direction](01_VISUAL_DIRECTION_AND_BOUNDARIES.md) · [tokens](02_DESIGN_TOKENS_AND_THEME_CONTRACT.md) · [sidebar](03_COLLAPSIBLE_SIDEBAR_SPECIFICATION.md) · [charts](04_CHART_AND_DATA_VISUALIZATION_STANDARD.md) · [Student GWA/cohort contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md) · [components](06_COMPONENT_AND_STATE_PATTERNS.md) · [accessibility](07_ACCESSIBILITY_AND_KEYBOARD_CONTRACT.md) · [workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md) · [agent protocol](09_AI_AGENT_UI_IMPLEMENTATION_PROTOCOL.md) · [acceptance/Definition of Done](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) · [ownership](11_UI_OWNERSHIP_AND_CONFLICT_MAP.md) · [phases A–F](12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) · [reference guide](13_REFERENCE_ASSET_USAGE_GUIDE.md) · [handoffs](14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md) · [risks](15_KNOWN_RISKS_AND_STOP_CONDITIONS.md) · [repository reconciliation plan](16_REPOSITORY_ROADMAP_RECONCILIATION_PLAN.md).

## Preparation and workflow boundaries

Preserve the earlier readiness commit; revise all affected docs/templates/reference contracts under docs/ui-system and add the reconciliation plan. Validate links, branch/package/ownership/status consistency, JS/reference tests, UTF-8/LF/whitespace, no production loading and frozen-file integrity; review all Git layers before requesting local-commit approval. No production implementation, dependencies, models, authentication, migrations/Migration 011, DB access, genuine records, push/PR/merge/deployment or cleanup.

One local clone and assigned long-lived branch per human. AI task branches start only from own assigned HEAD in that clone; compatible task suffixes avoid parent-ref slash conflicts. Extra worktrees are explicitly owner-authorized exceptions. Save authorized work, inspect status, fetch/update clean main fast-forward, return/integrate by approved method, resolve conflicts and record base before editing.

Implement → self-verify → authorized immutable local candidate → complete handoff → owner retest/fixes → separately authorized push → one reviewed PR with auto-merge off → separately authorized merge/deployment. Both members update main after approved merges; no ZIP/manual source copying. Current amendments require new local documentation commit approval.

## Owner desktop directive

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is historical, not a current measurement. No mobile tests or screenshots are authorized. Future desktop acceptance covers 1440×900, 1280×600 and 1024×768, light and dark.
