# Prediction clarity — MVP work package

Status: Implemented and tested locally; UNMERGED. Continuity integrated at e8d72a6. Independent follow-up at correction commit 63c2296f19c5ea0df99860c66f27cc196938d366: APPROVED WITH DOCUMENTED NON-BLOCKING LIMITATIONS; IR-01–08 closed. External Antigravity Stage B remains separate. Authorized scope: approved v3 Blue revision guide and the user's MVP package (26 October 2026). Base a79fb4838c5e2bc163cf943cda20afba9946d26b.

## Canonical metric mapping

MTR-06, MTR-07 (BLOCKED), MTR-08. See ../14_METRIC_RECONCILIATION.md for formulas, eligibility, evidence and non-equivalence; bounded MVP completion does not imply every canonical metric is implemented. Current review: ../16_INDEPENDENT_REVIEW_INITIAL.md. Desktop only (1440×900, 1024×768, 1280×600; both themes), no new screenshots; historical mobile evidence retained.

## Purpose

Separate numerical estimated GWA from rule-derived advisory interpretation.

## Defense concern addressed

The panel could not distinguish the regressor output, advisory rules and forecast uncertainty.

## Current behavior

Stored estimates contain source/completeness metadata. Existing interfaces mix projection terminology with cached risk. The inspected accepted tree has no held-out signed-residual artifact; scalar model metrics are not residuals.

## MVP behavior

A shared prediction contract and compact display return estimated GWA, separate configured risk, source, completeness, provisional status and lower/upper fields. Bounds remain null with a visible explanation until accepted residual evidence exists. No prediction is executed or persisted.

## Deferred final behavior

Empirical residual calibration, approved artifact ingestion and shaded numerical bands; no model retraining or RMSE-derived interval.

## Authorized roles

Student sees own estimate. Faculty sees only aggregate coverage for assigned records; Admin sees authorized aggregate coverage.

## Required data

Latest prediction ordered by generated_at DESC, id DESC; stored source/completeness. No private responses or training data.

## Formula definitions

Advisory risk uses computeRiskFromAvg(estimated GWA), separately from the regressor. Valid supplied boundaries are clipped to the documented GWA domain [1.00,4.00]; clipping creates no evidence or margin.

## UI components

Estimated GWA, lower/upper unavailable, advisory indicator, provenance, completeness, timestamp and visible explanatory copy; details for secondary help.

## Files likely affected

includes/analytics/predictions.php; includes/analytics/presentation.php (prediction fragment only); Student analytics view; minimal Student dashboard clarification.

## Database impact

Production SELECT-only. No schema, seed or row changes in udm_radar or udm_radar_scratch; no Migration 011. Isolated disposable synthetic fixtures only. The user's explicit temporary-database requirement overrides generic scratch-only skill guidance; tests must reject both protected names.

## Privacy and fairness boundaries

Prototype performance on synthetic academic records. Preserve canonical zero/text/units exclusions and independent honors policy. No official standing, pacing, benefits or punitive interpretation. Comparisons do not establish teaching performance or causation.

## Acceptance criteria

Model/calculation/unknown/insufficient states stay distinct; provisional and legacy-unknown inputs are not labeled complete; no numerical range appears without accepted evidence.

## Unit tests

Source distinctions, missing estimate, invalid/out-of-domain values, derived risk separate from cached risk, provisional flags and boundary clipping.

## Integration tests

Only the signed-in Student's latest estimate is returned; aggregate prediction coverage remains scoped; historical filters do not imply period-specific predictions.

## Browser tests

Prediction labels, unavailable bounds, help disclosure, incomplete state and responsive display.

## Empty and incomplete states

No stored estimate => N/A and insufficient data; missing residual evidence => range unavailable, never fabricated.

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
