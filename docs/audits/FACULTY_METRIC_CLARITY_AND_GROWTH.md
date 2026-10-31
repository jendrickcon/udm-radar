# Faculty assessment clarity and paired class change

Starting baseline: `911c2eb68d4f22406bfa6700b95fe8dba5a6087b` (PR #67).
Branch: `fix/faculty-metric-clarity-and-growth`.
Status: implementation prepared for local owner retest; not pushed, merged or independently accepted.

## Observed problem

The Dashboard's former Grade Coverage and Assigned-Class Mean describe eligible finalized numeric point grades. Performance Trends displayed interim percentages under generic mean/completeness labels and plotted converted point equivalents as an estimated trajectory. These different stages could truthfully show 0% finalized coverage alongside 100% Midterm recording, but the presentation did not explain the distinction.

A read-only aggregate audit of the five requested approved synthetic class loads found 169 of 169 Midterm records, an overall Midterm percentage average of 85.1642%, and no eligible numeric finals. Machine Learning IT-32 has 36 recorded Midterm scores with mean 87.125%, explaining its former rounded 87% value. No local academic record was changed. The audit covers recorded slots; it cannot infer unrecorded enrollment opportunities.

## Delivered metric contract

| Area | Meaning |
| --- | --- |
| Current Assessment Coverage | Valid percentage scores at the named latest raw assessment stage / current assigned Student–subject record slots |
| Latest Assessment Mean | Equal-weight mean of recorded raw percentages at that single named stage, never a mix of stages or scales |
| Final numeric-grade coverage | Canonically eligible numeric final points / recorded expected slots; distinct from interim recording |
| Assigned-class finalized mean | Existing canonical unit-weighted eligible final points and exclusions, shown separately |
| Class change | Later minus earlier adjacent assessment mean for the same matched Student IDs in the selected assigned class; primary unit is percentage points |
| Relative change | Paired difference / earlier paired mean ×100, secondary and unavailable when the earlier mean is zero |
| Requiring Attention | Distinct Students with High/Moderate advisory risk from their latest available assessment or final outcome; source rules remain canonical |
| Notable changes | Absolute Preliminary→Midterm difference of at least 5 percentage points, with current risk and its stage identified; not statistical significance |

Missing scores never become zero. A genuine recorded zero is included. A partial matched set is explicitly provisional, with its sample displayed; no matched pair produces unavailable change. Portfolio completeness uses Student–subject slots, while Unique Students is deduplicated. All assessment averages name the stage and remain on the 0–100% scale.

The owner-approved simplification replaces the former primary/snapshot duplication with five primary cards: Assigned Classes, Unique Students, Overall Latest Assessment Mean, Assessment Coverage and Requiring Attention. The subsequent presentation correction adds two compact Dashboard summary cards, **Assigned classes** and **Support work**, without a full assigned-class table or unavailable-final explanation. Closed referral drilldowns remain excluded from Active Referrals; no support workflow is altered. Finalized Mean remains available in selected-class Trends detail only when eligible numeric final outcomes exist. Cumulative GWA remains separate in Student review by section.

## Faculty simplification continuation — 31 October 2026

Continuation starts at `ef942dc349b136b3b76f17f3bd96eec157cec8a3` on the same isolated branch. The owner-summary correction below supersedes this checkpoint's Dashboard table and selected-class card presentation. No push, merge or canonical deployment is authorized by either continuation.

- Dashboard: zero metric disclosures, one general **Review assigned classes** action and one **Review class** link per assigned class. Essential class metrics are visible in a captioned table. Existing authorized roster/support routes remain available.
- Trends: **Assigned-class details** is visible by default with separate stage, percentage mean, coverage, attention and action columns. The two definition disclosures are removed. **View risk-band counts** is the sole optional details disclosure, collapsed by default. Notable assessment changes appear only when qualifying records exist; their Preliminary–Midterm threshold remains a review rule, not statistical significance.
- Overall percentage availability requires every assigned class to have the same latest recorded stage. `facultyAssessmentOverview()` only gates presentation and reuses the existing Student–subject raw-score aggregate. It never averages class means. Mixed stages display **Mixed latest stages** and direct review to class rows. A missing class stage withholds a shared mean. Coverage remains valid recorded slots divided by all current assigned record slots.
- Eligible final points remain unit-weighted under the existing formula and exclusions. No risk, grading, GWA, authorization or historical-attribution rule changes. Historical Faculty attribution, MTR-07, canonical MTR-21, Student cohort-average numeric series and Migration 011 remain blocked.
- Double-encoded punctuation is repaired in the Trends source; both Faculty pages explicitly serve UTF-8 HTML. Regression coverage checks all Faculty PHP and Faculty-specific helper/CSS/JavaScript sources for known mojibake.
- Acceptance uses the repository-pinned desktop runner at 1440×900, 1280×600 and 1024×768 in both themes, actual handlers on a guarded disposable synthetic database, and separate shared-portal regressions. No screenshots, traces, videos or mobile tests. The external execution report records actual totals and database cleanup; source inspection alone is not live acceptance.

## Owner summary/table correction — 31 October 2026

Starting checkpoint: `1248ec21573c40bdeb726682abcc4f92c809426d`, same Faculty branch/worktree. This is presentation polish only; `includes/faculty_assessment.php`, repository scope and all calculation/coverage/risk rules remain unchanged.

- Keep the five top Dashboard KPIs. Replace its assigned-class table and loose support summary with two equal-width cards at 1440/1280 desktop and naturally stacked cards at 1024. Each has one right-aligned secondary action: **View performance trends** (`trend.php`) or **Review support cases** (`feedback.php?tab=support`). Remove the duplicate banner support button. No Dashboard disclosure, assigned-class rows or unavailable-final explanation.
- Trends owns the visible **Assigned-class comparison** table with seven semantic column headers, stacked subject/section and coverage cells, a purpose caption and unique accessible per-class review names. Stable ordering and all values are preserved.
- Replace selected-class KPI cards with the six-column **Selected class summary** table. Class Change retains a primary signed `pp` value and at most two supporting lines. Compact desktop wording preserves the full accessible stage, matched-sample, percentage-point, relative-change and provisional-subset label. Missing change and zero baselines remain unavailable under existing rules.
- **View risk-band counts** remains the sole optional disclosure. `.faculty-info-disclosure` uses a stable inline currentColor table SVG, a 46px summary, native keyboard behavior and scoped marker suppression. Open state retains the same icon; there is no chevron, replacement arrow, literal question-mark icon or external icon dependency. No optional description disclosure is needed; any later approved description must use an inline question-circle SVG under the same component contract.
- The risk table includes only observed stages, without blank merged rows. Entirely unrecorded classes retain a concise empty-state message. Assessment progress, raw/risk charts, eligible final details, paired change, notable changes and authorization remain intact.
- Focused acceptance covers six desktop/theme combinations, half-width/equal-height and stacked/natural-height cards, internally scrolling semantic tables, two-line Class Change geometry/accessibility, SVG/marker invariance, empty/mixed states and existing portal regressions. The external report records actual execution categories, unchanged local read-only fingerprints and owned disposable cleanup. Owner visual retest and separate push/merge approval remain pending.

Performance Trends uses a raw-percentage line with gaps. The database's Final column contains points/text, not a raw final percentage, so Final is not appended to that line. Final outcomes remain in a separate exact table. Entirely unrecorded stages have null risk datasets and a Not recorded annotation rather than an apparent measured No Data distribution. An absent chart library retains the exact evidence tables.

## Scope and historical boundary

The new SELECT-only Faculty repository revalidates the active Faculty account and limits current grades by owned subject/section, configured school year/semester and active Student records. Requested class selectors must match the owned load list; foreign or malformed selectors fail closed. The Dashboard roster is also restricted to current owned grade records. Existing navigation and grade-submission rules remain unchanged.

The old Trends selector accepted arbitrary subject/section values without validating ownership. Current assignments could also be applied to requested historical periods. Both paths are closed: historical requests render no historical Student records, and the page explains that dated attribution is unavailable.

`faculty_class_loads` has no school year, semester, creation timestamp or effective dates. None of the five requested loads has verified dated prior-assignment evidence. The concise previous-semester note therefore says Unavailable with its reason. Historical grades, current sections, encoder IDs and similarly named sections are not substituted. No previous-semester calculation or backfill was implemented, and there is no historical-feature commit. Execution of a genuinely dated comparable historical assignment remains unavailable pending an approved dated source and adapter.

MTR-07, canonical MTR-21, historical Faculty attribution, numerical Student cohorts and Migration 011 remain blocked. No GWA formula, risk threshold, schema, migration, model, dependency or private template changed.

## Verification and reproduction

Database-free checks:

```text
php tests/unit/faculty_assessment_test.php
php tests/unit/academic_outcome_contract_test.php
node tests/unit/display_preferences_contract_test.js
```

Owned-disposable acceptance entry point:

```text
node tests/fixtures/run_faculty_assessment.mjs
```

Provide an installed repository-pinned Playwright runner, `PHP_BINARY` when needed, and `FACULTY_CHART_ASSET` pointing to a local real Chart.js library cache. `FACULTY_EVIDENCE_DIR` must be a new owned output directory. The runner performs no dependency installation and no protected-database fallback. The application's unversioned Chart.js URL remains a reproducibility limitation; the cache hash is recorded.

The runner creates a guarded uniquely named disposable schema using selective canonical table definitions and approved synthetic fixtures, verifies its ownership marker, runs actual login/handlers and the scope integration test, fingerprints records before/after, and drops the database in `finally` with information_schema absence verification. No Faculty academic POST is dispatched by its allowlisted router. Student private-export tests use a deliberately absent owned prerequisite path.

Browser evidence is separated into Faculty authenticated behavior, accepted Student regressions, shared generated/structural behavior and the updated Dashboard compatibility check. Tests cover 1440×900, 1280×600 and 1024×768 in Light/Dark, keyboard focus, containment, chart/table parity, empty/final-text/partial populations and authorization. Faculty structural regressions run first. No mobile tests or captures are required.

The previous scratch-specific `faculty_trend_midterm_visibility_test.php` is now a compatibility entry point to the guarded current repository test; it no longer duplicates a point-trajectory algorithm or automatically selects an existing scratch database. Supply the newly owned fixture state on stdin. The academic-outcome regression now calls the actual extracted Faculty model and preserves its final-point/textual-count expectations.

## Owner retest

Use the isolated Faculty refinement URL and an approved synthetic Faculty login. Confirm the Dashboard shows available Midterm coverage/mean while finalized outcomes remain separate. Select each assigned class; inspect paired class change, raw-percentage chart, recording counts and notable changes. Confirm previous-semester comparison remains unavailable with a reason. Repeat key interactions in both themes and the required desktop widths, then verify existing Class Analytics, Term Grade Submission and Concerns navigation. Push and merge remain gated on owner visual retest and subsequent review.
