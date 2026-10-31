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

The Dashboard preserves the four primary portal KPI types and prioritizes five assigned-class cards: sections, assessment coverage, latest assessment mean, active owned referrals and notices issued. Closed referral drilldowns are excluded from the Active Referrals count; no support workflow is altered. Finalized outcomes stay in compact optional details.

Performance Trends uses a raw-percentage line with gaps. The database's Final column contains points/text, not a raw final percentage, so Final is not appended to that line. Final outcomes remain in a separate exact table. Entirely unrecorded stages have null risk datasets and a Not recorded annotation rather than an apparent measured No Data distribution. An absent chart library retains the exact evidence tables.

## Scope and historical boundary

The new SELECT-only Faculty repository revalidates the active Faculty account and limits current grades by owned subject/section, configured school year/semester and active Student records. Requested class selectors must match the owned load list; foreign or malformed selectors fail closed. The Dashboard roster is also restricted to current owned grade records. Existing navigation and grade-submission rules remain unchanged.

The old Trends selector accepted arbitrary subject/section values without validating ownership. Current assignments could also be applied to requested historical periods. Both paths are closed: historical requests render no historical Student records, and the page explains that dated attribution is unavailable.

`faculty_class_loads` has no school year, semester, creation timestamp or effective dates. None of the five requested loads has verified dated prior-assignment evidence. The previous-semester card therefore says Unavailable with its reason. Historical grades, current sections, encoder IDs and similarly named sections are not substituted. No previous-semester calculation or backfill was implemented, and there is no historical-feature commit. Execution of a genuinely dated comparable historical assignment remains unavailable pending an approved dated source and adapter.

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
