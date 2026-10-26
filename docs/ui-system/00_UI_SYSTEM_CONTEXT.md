# Future UI, member and agent readiness

Reconciled 27 October 2026 against [merged PR #64](https://github.com/jendrickcon/udm-radar/pull/64).
Accepted PR64 runtime/local-deployment baseline: `bdb4a267f30aa31ea36983304a03b01dc28f0fcd`.
Current documentation main after PR63: `c29d68a7ef961c0be61ffbf296a10918303d5fea`.
Preserved owner-accepted readiness commit: `648207bc563f2bb1aa280d08ab9699f07a4f39c7`.
Existing branch: `docs/future-ui-member-agent-readiness`; no new branch/worktree.
The earlier PR64 integration at `b85d208d8ca7a2e9a628a401008ab448cb6377a3` and accepted reconciliation `afb3bd8cc49abc0d51e49b45367c286f7b24e7a5` are preserved. This publication alignment integrates PR63 main `c29d68a7ef961c0be61ffbf296a10918303d5fea` without rewriting history. Only obsolete PR63 publication-gate wording is corrected; production/onboarding files match current main.
Documentation: READY FOR OWNER REVIEW. Future post-MVP member implementation: NOT STARTED. Publication remains unauthorized.

## Product goal and authority

> Deliver a coherent, understandable, role-appropriate academic analytics experience across Administrator, Faculty, and Student portals, where charts and summaries explain current results, comparable periods, growth or change, coverage, sample size, support activity, and unavailable evidence without ranking Faculty, fabricating comparisons, or overwhelming users with technical text and controls.

Read [AGENTS](../../AGENTS.md), [contribution policy](../../CONTRIBUTING.md), [roadmap](../ROADMAP.md), [history](../IMPLEMENTATION_HISTORY.md), [UI governance](../UI_UX_GOVERNANCE.md) and [findings register](../UI_UX_FINDINGS_REGISTER.md). [PR #63](https://github.com/jendrickcon/udm-radar/pull/63) is merged at `c29d68a7ef961c0be61ffbf296a10918303d5fea`; the repository records now preserve PR62 history and current PR64/UI/bootstrap/local-deployment facts. Keep those three accepted status files identical to main; readiness changes stay under docs/ui-system.

## Accepted system, not future member reimplementation

| Area / source | Merged behavior at the canonical SHA | Future boundary |
| --- | --- | --- |
| Administrator routes | admin/analytics.php delegates to shared analyticsPage('admin'); admin/comparative_analytics.php redirects to analytics.php, default comparisons tab; admin/dashboard.php redirects to index.php | Preserve aliases and parameters; do not recreate a second destination |
| Program Analytics | Overview, Academic Comparisons and Support & Interventions tabs; original Overview preserved; ≤4 primary filters, Advanced disclosure and support-only filters | Existing MVP regression baseline; refinements require a finding or explicit approval |
| Comparison experience | Two selected groups, optional third, overlay/separate cards, chart with Comparison Details at right, exact Comparison Evidence table below, all-groups disclosure | Already shipped; no duplicate comparison engine, cards or formulas |
| Trends and changes | Actual school-year/semester labels, SVG historical plot, coverage/sample context, point/relative change and selected-group details | Retain existing behavior and evidence; do not call this a newly assigned member feature |
| Support | Case/referral-status visuals, referral→notice→receipt flow, human-readable first-notice mean/n, exact records and authorized drill-down | Counts are not effectiveness; MTR-19 stays count; parent-case median is not canonical MTR-21 |
| Dashboard summaries | admin/index.php has a Program Analytics snapshot; faculty/dashboard.php has Assigned-Class Academic Analytics summary and link | Future density/spacing changes are post-MVP refinements only |
| Faculty review | faculty/assigned_analytics.php delegates to shared Faculty view: Overview, Academic Trends, Referrals & Interventions; assigned-scope semantics retained | Existing faculty/analytics.php remains a separate older Class Analytics route; do not claim it was removed or every Faculty page was unified |
| Student review | student/academic_analytics.php delegates to own-record Academic review; current/previous comparable GWA, point/relative change, prediction explanation, SVG historical terms and exact table already exist | Joshua refines this accepted experience; no Student numeric cohort is implemented |
| Shared layout/themes | academic-analytics.css uses full available content width, named horizontal-overflow regions, responsive chart/details grid (1.85fr/1fr, stacked ≤1080px) and light/dark overrides; dashboard.css makes main-content the desktop vertical scroll owner | Source observations plus owner-reported deployment, not a fresh rendering/contrast/accessibility certification |
| Sidebar | Existing 280px desktop sidebar and existing ≤900px drawer | Retractable/collapsible desktop rail remains NOT STARTED; references do not ship it |
| Data onboarding | Schema-only bootstrap plus separate synthetic seed and verification guide | Fresh member-owned setup only; no existing database operations in this reconciliation |

PR #64 is merged at `bdb4a267f30aa31ea36983304a03b01dc28f0fcd`; its final implementation head is `9f6ed109d968c47fe439eecc816f68ec0167694d`. Local deployment is confirmed by the owner at http://localhost/capstone/. This documentation pass does not repeat application/database acceptance tests or upgrade prototype deployment to institutional UAT.

## Final portal ownership

Vincent owns Administrator and Faculty post-MVP improvements, their UI/sidebar/dashboard/chart refinements, own calculation-display/authorization/accessibility/theme/desktop tests and portal fixes. Package `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION`; assigned branch `feat/member1-admin-faculty-ui`.

Joshua (Member 2) owns Student post-MVP improvements, Student UI, calculation/privacy contracts, own tests and fixes. Package `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION`; assigned branch `feat/member2-student-portal`.

Both implement, self-verify, document and fix their own approved scope. Cross-review is supplemental. Shared CSS/JS/header/sidebar/API/fixtures/docs need one named integration custodian per exact file and sequential handoff. Neither member is universal UI/backend owner or tester-only. Already shipped MVP work is regression scope, not a new implementation assignment.

## SQL onboarding baseline

[database/udm_radar.sql](../../database/udm_radar.sql) is Mode A schema-only onboarding bootstrap: 18 CREATE TABLE definitions, zero INSERT statements, approved schema through Migration 010. It contains DROP TABLE statements, so it is for a fresh, empty member-owned local setup, never an overwrite of an existing populated database.

Import [database/seeds/001_synthetic_demo.sql](../../database/seeds/001_synthetic_demo.sql) separately only when the approved member setup needs synthetic demo records. The seed refuses a target with existing user accounts; it is not embedded in the schema. [database/README.md](../../database/README.md) contains the canonical onboarding/import/configuration/verification instructions and documented synthetic logins. Do not duplicate credentials here, run migrations 000–010 again after bootstrap, or execute Migration 011.

This package inspects SQL text only; no connection/import/seed/migration occurs. Follow the [member starting workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md) and current owner authorization for any later setup.

## Preserved blocked contracts

MTR-07 empirical prediction range, canonical MTR-21 referral closure, historical Faculty attribution without dated evidence, Student cohort-average series and Migration 011 remain BLOCKED. No residual bands, referral closure field, privacy threshold, historical assignment or cohort numbers are invented. Joshua owns the eleven-item Student cohort proposal; final owner approval is required. The proposed reference series “Your recorded GWA” has the visible unavailable explanation “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.” This reference label is not a claim that production already renders that disabled series.

## Status reconciliation and publication

PR #63 was owner-authorized and merged normally at `c29d68a7ef961c0be61ffbf296a10918303d5fea`, parents `bdb4a267f30aa31ea36983304a03b01dc28f0fcd` and reviewed head `96aa4d7a91616c31d38687f2e5851721fa7def82`. The canonical checkout was fast-forwarded cleanly. Only docs/IMPLEMENTATION_HISTORY.md, docs/ROADMAP.md and docs/post-defense/10_IMPLEMENTATION_STATUS.md changed; runtime/onboarding files remain identical to PR64.

The PR63 gate is COMPLETE; preserve the merged status files rather than copying older versions over them. See the [publication plan](16_REPOSITORY_ROADMAP_RECONCILIATION_PLAN.md). This readiness alignment is local only: no readiness push/PR, further merge-to-main, deployment, branch deletion or database operation is authorized. Member implementation remains NOT STARTED.

## Package map

[Goals](01_VISUAL_DIRECTION_AND_BOUNDARIES.md) · [tokens](02_DESIGN_TOKENS_AND_THEME_CONTRACT.md) · [sidebar](03_COLLAPSIBLE_SIDEBAR_SPECIFICATION.md) · [charts](04_CHART_AND_DATA_VISUALIZATION_STANDARD.md) · [Student contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md) · [states](06_COMPONENT_AND_STATE_PATTERNS.md) · [accessibility](07_ACCESSIBILITY_AND_KEYBOARD_CONTRACT.md) · [workflow](08_MEMBER_UI_CONTRIBUTION_WORKFLOW.md) · [agent protocol](09_AI_AGENT_UI_IMPLEMENTATION_PROTOCOL.md) · [acceptance](10_UI_TEST_AND_ACCEPTANCE_MATRIX.md) · [ownership](11_UI_OWNERSHIP_AND_CONFLICT_MAP.md) · [phases](12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) · [references](13_REFERENCE_ASSET_USAGE_GUIDE.md) · [handoffs](14_PROGRESS_REPORT_AND_HANDOFF_STANDARD.md) · [risks](15_KNOWN_RISKS_AND_STOP_CONDITIONS.md) · [repository reconciliation](16_REPOSITORY_ROADMAP_RECONCILIATION_PLAN.md).

## Owner desktop directive

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is historical. No mobile tests or screenshots are authorized.
