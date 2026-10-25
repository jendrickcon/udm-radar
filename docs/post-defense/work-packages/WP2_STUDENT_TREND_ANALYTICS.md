# Student trend analytics — MVP work package

Status: Implemented and tested locally; unmerged. Continuity integrated at e8d72a6; independent review/corrections in progress. Authorized scope: approved v3 Blue revision guide and the user's MVP package (26 October 2026). Base a79fb4838c5e2bc163cf943cda20afba9946d26b.

## Canonical metric mapping

MTR-01–04; MTR-05 DEFERRED. See ../14_METRIC_RECONCILIATION.md for formulas, eligibility, evidence and non-equivalence; bounded MVP completion does not imply every canonical metric is implemented. Current review: ../16_INDEPENDENT_REVIEW_INITIAL.md. Desktop only (1440×900, 1024×768, 1280×600; both themes), no new screenshots; historical mobile evidence retained.

## Purpose

Explain historical term changes using shared canonical calculations.

## Defense concern addressed

Users need understandable previous/current GWA, direction and change, without treating logarithmic change as a forecast.

## Current behavior

Student trend and dashboard use existing historical grades and canonical helpers; comparative completeness and change summaries are not consolidated.

## MVP behavior

Own-record term trend, latest selected term, previous recorded complete term, point/relative change and plain-language direction. The incomplete current-term scalar is unavailable/Pending; only the separately labeled recorded-point series may show partial provisional values. Regular terms exclude summer; summer compares only to summer. Direction/change requires complete comparable terms.

## Deferred final behavior

Full legacy-chart replacement, log-change models, exports and advanced forecasts.

## Authorized roles

Student's own rows only. Shared math is consumed by authorized Admin/Faculty views without duplicating formulas.

## Required data

Recorded grades including missing/text outcomes, units and actual grade school_year/semester. Expected coverage is recorded subject slots, not inferred missing enrollments.

## Formula definitions

GWA = computeWeightedGWA(eligible points/units); zero/status exclusions unchanged. point_change=current-previous; relative_change=(current-previous)/previous*100 when denominator>0. Stable means equality at the helper's two-decimal result. No previous/partial/invalid denominator => unavailable direction/change.

## UI components

Previous/current cards, primary point change and secondary percentage context, historical line chart with accessible data table, completeness and estimate component.

## Files likely affected

includes/analytics/calculations.php (sole owner of shared numerical aggregation/change); Student analytics wrapper and shared view's Student section.

## Database impact

Production SELECT-only. No schema, seed or row changes in udm_radar or udm_radar_scratch; no Migration 011. Isolated disposable synthetic fixtures only. The user's explicit temporary-database requirement overrides generic scratch-only skill guidance; tests must reject both protected names.

## Privacy and fairness boundaries

Prototype performance on synthetic academic records. Preserve canonical zero/text/units exclusions and independent honors policy. No official standing, pacing, benefits or punitive interpretation. Comparisons do not establish teaching performance or causation.

## Acceptance criteria

Hand-calculated unequal-unit examples agree; INC/text/missing/zero units never become numeric zero; missing or partial comparisons are clearly unavailable.

## Unit tests

Weighted means, counts/denominators, coverage, empty/mixed statuses, zero denominator, missing prior term, complete versus provisional changes.

## Integration tests

Own-only rows and cross-role parity for identical authorized grade subsets; recorded period chronology rather than lexical semester guesses.

## Browser tests

Trend/table consistency, filter refresh, no-prior-term and incomplete explanations, keyboard and desktop rendering.

## Empty and incomplete states

No prior complete term => comparison unavailable; no usable final points => N/A; partial finals => provisional GWA with no asserted improving/declining direction.

## Evidence to preserve

RED/GREEN logs, actual pass/fail/skip counts, isolated-database target/provenance and cleanup evidence, desktop request/response role checks and protected-path/source hashes under ignored logs/post-defense-mvp/.

## Documentation to update

This package, docs/post-defense/10_IMPLEMENTATION_STATUS.md, shared methodology/implementation plan, canonical ROADMAP.md and IMPLEMENTATION_HISTORY.md, UI_UX_FINDINGS_REGISTER.md. Do not edit manuscript or handoff/recovery packages.

## Status checklist

- [x] Scope and interfaces established
- [x] Failing behavior test observed
- [x] MVP implemented
- [x] Unit/integration/browser verification recorded
- [x] Limits and continuation instructions current
- [x] Commit/PR status recorded truthfully
- [ ] Merged (separate authorization required)
