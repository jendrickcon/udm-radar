# Portal ownership and shared-file conflict map

Documentation: READY FOR OWNER REVIEW. Member implementation: NOT STARTED.

## Final member split

| Member / package / assigned branch | Complete portal responsibility | Mandatory self-verification |
| --- | --- | --- |
| Vincent — `ADMIN-AND-FACULTY-PORTAL-UI-ANALYTICS-AND-SELF-VERIFICATION` — `feat/member1-admin-faculty-ui` | Administrator Dashboard, Program Analytics Overview/Academic Comparisons/Support & Interventions; Faculty Dashboard and Assigned-Class Analytics; both portals' navigation/sidebar, charts, details/deltas, filters, legends, table fallbacks, themes, keyboard/accessibility and desktop layouts; portal-level fixes | Unit/integration, authenticated Admin and Faculty, role negatives, chart/table/value parity, support semantics, themes, keyboard, desktop containment and cross-portal regressions |
| Joshua — `STUDENT-PORTAL-ANALYTICS-UI-CALCULATIONS-AND-SELF-VERIFICATION` — `feat/member2-student-portal` | Student Dashboard/Academic Analytics, recorded GWA history/change, prediction/source/completeness/range-unavailable, Goal Calculator links, Grades/history, Enrollment Planning, Student sidebar/charts/legend/table/themes/accessibility; Student calculation/privacy contracts and portal fixes | Unit/integration, authenticated own-record and other-Student denial, current/previous comparable/summer/missing GWA/change, prediction clarity, table parity, Goal Calculator/Grades/Enrollment regressions, themes, keyboard and desktop containment |

Neither member is universal UI/backend owner or tester-only. Both implement, test, verify, fix and document their portals; do not split a portal's sidebar and charts between members. Vincent reuses approved analytics formulas/services. Joshua's Student technical responsibility does not grant edit authority over every backend file.

## One integration custodian per shared file

The future approved package must replace each “unassigned” entry with one named human custodian, literal paths, immutable source candidates, edit order and return conditions. Shared work stays BLOCKED until assigned; no default global-file ownership follows from the portal split.

| Potential shared file or area | Custodian now | Conflict rule |
| --- | --- | --- |
| `includes/sidebar.php`, `includes/header.php` | Unassigned | One active writer; preserve all portals and preferences |
| `assets/css/dashboard.css`, global design tokens | Unassigned | One owner-assigned custodian; portal-specific hooks preferred |
| `assets/css/academic-analytics.css`, `assets/js/academic-analytics.js` | Unassigned | Sequential candidate integration; no parallel line/function ownership |
| `includes/analytics/presentation.php`, `api/academic_analytics.php` | Unassigned | One technical owner per contract/file; approved service formulas and authorization retained |
| Shared browser fixtures/helpers | Unassigned | Review side effects; integrate tests sequentially and run all affected portal regressions |
| Shared UI-system/status documentation | Unassigned for future implementation | One status writer; merged PR63 status paths remain identical to current main |
| Admin/Faculty-only components | Vincent within approved literal allowlist | Own implementation, tests and fixes |
| Student-only components and calculation/privacy proposal | Joshua within approved literal allowlist | Own implementation, tests and fixes; cohort numeric release remains BLOCKED |

If Vincent needs a new backend field, document its presentation contract and request the assigned technical custodian's change; do not invent the formula in the UI. Joshua must document Administrator/Faculty API impact when changing an authorized shared Student field. Review findings never transfer ownership automatically.

## Integration order and cross-review

1. Agree shared contracts, owner-approved files, clean current-main base and custodians.
2. Implement portal-specific work independently and complete each member's self-verification.
3. Joshua may cross-review Vincent's calculation labels/privacy/authorization; Vincent may cross-review Joshua's visual consistency/usability/themes/accessibility. No independent redesign of the other's portal.
4. Original implementer fixes verified defects, unless the defect belongs to another assigned shared contract, or the owner explicitly transfers the fix/reassigns unavailable work. Disclose repair authorship; do not present self-review as independent review.
5. Review the package with fewer shared changes first. After its authorized merge, the second member updates main and reconciles before opening the next PR. Overlapping PRs require an explicit conflict-resolution order.
6. One custodian applies shared-file candidates sequentially, records source SHAs and runs cross-portal regressions.
7. Owner-visible retest precedes acceptance; push, PR, merge, deployment and cleanup retain separate authority. No manual file copying or silent overwrite.

Use the [owner-decision template](templates/OWNER_DECISION_TEMPLATE.md) for exact transfers: current primary, temporary receiver, literal files, candidate SHA/dirty state, reason, minimal scope, order, tests, owner decision and return condition.

## Branch and workflow boundaries

One clone per human and the assigned long-lived branch above. New branches are not created by this document. AI task branches start only from their own assigned HEAD in the same clone; use `feat/member1-admin-faculty-ui--sidebar` or `feat/member2-student-portal--gwa-chart`. A slash child beneath an existing branch ref conflicts with its ref path. Extra worktrees require explicit owner exceptions.

Before each package, preserve authorized work, inspect status, fetch/update clean main fast-forward, return/integrate by the approved method and record SHAs. After approved merges both members update main. No auto-stash/reset/clean/force-push or silent branch recreation.

## Cohort and protected boundaries

Joshua owns the Student chart interface and eleven-item calculation/privacy proposal; owner approval is the final release gate. Vincent neither owns Student UI nor defines its cohort formula. Keep “Your recorded GWA” active and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.” visible. No numeric cohort, privacy threshold, pooled-mean substitution or enable flag.

MTR-07, canonical MTR-21 and historical Faculty attribution remain BLOCKED. MTR-19 is bounded count; limited parent-case median is separate. Current preparation permits no DB/production/model/dependency/migration edits, mobile tests, screenshots or publication. Preserve the three merged PR63 files exactly as current main and follow the [publication plan](16_REPOSITORY_ROADMAP_RECONCILIATION_PLAN.md). Current reconciliation has local-commit authorization only; publication remains unauthorized.

## Post-MVP scope boundary and starting state

PR64 is already merged/deployed at bdb4a267f30aa31ea36983304a03b01dc28f0fcd. Ownership is portal accountability for approved post-MVP changes; it does not assign rebuilding existing Overview/tabs/comparison/growth/support/theme/dashboard/trend features. Vincent's and Joshua's future desktop collapsible navigation remains NOT STARTED.

Joshua is the named Member2 owner; existing assigned branch/package identifiers remain unchanged. Both self-verify their changes and accepted-MVP regressions. Member starting main must include PR64 and later owner-approved docs; use the merged database/README.md for separate schema/seed onboarding. Shared custodians still require assignment. PR63 is merged at c29d68a7ef961c0be61ffbf296a10918303d5fea; its three files match accepted main and receive no readiness-specific edits.
