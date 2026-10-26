# Visual direction and boundaries

Documentation: READY FOR OWNER REVIEW. Future member implementation: NOT STARTED.

## Product goal

> Deliver a coherent, understandable, role-appropriate academic analytics experience across Administrator, Faculty, and Student portals, where charts and summaries explain current results, comparable periods, growth or change, coverage, sample size, support activity, and unavailable evidence without ranking Faculty, fabricating comparisons, or overwhelming users with technical text and controls.

## Ten MVP goals

| Goal | Required future behavior |
| --- | --- |
| 1. Coherent analytics | Administrator Program Analytics unifies Overview, Academic Comparisons, Support & Interventions. Faculty uses the same component language within assigned scope. Student presents own-record trends, prediction clarity and change. Dashboard summaries lead naturally to deeper analytics; reuse services rather than duplicate formulas. |
| 2. Visual-first evidence | Charts, compact summaries and details panels precede exact accessible tables below visualizations. Extended formulas/policy use accessible disclosures; critical warnings, sample size, coverage and limitations stay visible. Approximately two-thirds plot and one-third details is an optional wide-desktop arrangement. |
| 3. Growth and change | Where comparable evidence exists, show point change, supporting relative change, coverage/sample-size change, exact start/end periods and neutral direction. Otherwise show “Change unavailable” and a reason; no incompatible population, formula or unit differences. |
| 4. Academic periods | Use real school-year and semester labels, full names in tooltips/text/tables; ordinal positions alone are insufficient. Missing terms remain gaps. Active periods remain dynamic. |
| 5. Controlled comparisons | Two selected groups by default, optional third, no fourth in the MVP. Overlay and separate-card modes. Support school year, semester, curriculum year, subject, section and verified Faculty assignment. Contextual mixed-scope cards may coexist, but direct differences need mathematical comparability. Scalar bars/dots for one period; lines only for real ordered histories with at least two valid points. |
| 6. Support activity | Case/referral statuses and referral → notice → receipt flow. “Average time before the first notice” uses human-readable duration and actual eligible n/small-sample context. MTR-19 is a bounded count; parent-case median remains distinct from blocked MTR-21. Authorized read-only drill-down only; counts do not prove effectiveness. |
| 7. Complete themes | Light/dark surfaces for cards, controls, tables, canvas, legends, axes, tooltips, badges, empty states and details. Avoid pure-white dark cards; preserve contrast, focus and non-color identifiers. Check each portal independently. |
| 8. Accessible navigation | Clear tabs/context controls, at most four primary filters where possible, Advanced disclosure and support controls only in Support. One primary page scroll owner; no horizontal page overflow at supported desktop sizes. Future Vincent sidebar is not a current analytics MVP publication blocker. |
| 9. Portal accountability | Vincent implements, verifies and fixes Administrator/Faculty; Joshua does the same for Student. Cross-review supplements self-verification; shared files have one assigned custodian. |
| 10. Truth and fairness | No Faculty ranking/quality inference, current-load historical attribution, invented cohort/range, test-count model accuracy, advisory-risk official standing, or unapproved genuine-record/UAT claims. |

## Adapt existing UDM-RADAR patterns

The master's two dashboard-image descriptions motivate compact density, stronger plots, clear legends and coherent themes. Actual source images were not supplied to this preparation, so no pixel-match claim is made. Preserve institutional identity, logo, semantic colors, active routes and text-size/theme/motion preferences; do not copy another product's assets or add a UI kit.

The canonical expanded sidebar is 280px. The 80px rail and 20–40px density range are reference proposals subject to owner review. Widen plots without hiding labels, sample size, limitations or accessible tables. Preserve Faculty review and supportive Student explanations. Show “Prototype performance on synthetic academic records”.

Student own-record series is “Your recorded GWA”. Cohort comparison remains BLOCKED with the exact unavailable explanation in the [contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md); no numbers are drawn for it.

## Ownership and non-goals

Vincent adapts Administrator/Faculty sidebars, layouts, filters, charts, themes and accessibility together; Joshua owns these within Student plus Student calculation/privacy verification. Shared global assets need one named integration custodian and sequential edits. This replaces the former all-UI-versus-verification division.

No production implementation, DB access, migrations, dependencies, model changes or reference imports are authorized now. Do not redesign accepted Antigravity work without a verified finding or approved improvement. See the [phase plan](12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md).

## Reconciled PR64 delivery boundary

PR64 is merged and locally deployed at bdb4a267f30aa31ea36983304a03b01dc28f0fcd. The ten goals describe product requirements and regression expectations. The integrated Admin destination/tabs, original Overview, dashboard snapshots, two/three comparisons, overlay/separate cards, growth/details, real academic periods, exact tables, support flow and theme/layout treatment already exist.

Vincent and Joshua receive only approved post-MVP refinements or verified defects. The desktop collapsible sidebar remains NOT STARTED; do not claim references implement it. Existing Student Academic review already plots own recorded history and shows change/prediction explanations; future Student work refines it rather than creating an absent feature. No numeric cohort/range or historical Faculty attribution is enabled by PR64. Migration011 remains BLOCKED.
