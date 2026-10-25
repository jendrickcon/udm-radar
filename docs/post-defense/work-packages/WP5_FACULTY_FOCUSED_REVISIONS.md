# Focused Faculty view — MVP work package

Status: Implemented and tested locally; unmerged. Continuity integrated at e8d72a6; independent review/corrections in progress. Authorized scope: approved v3 Blue revision guide and the user's MVP package (26 October 2026). Base a79fb4838c5e2bc163cf943cda20afba9946d26b.

## Canonical metric mapping

MTR-12–14 limited current assigned scope; MTR-17–20 own referral evidence. See ../14_METRIC_RECONCILIATION.md for formulas, eligibility, evidence and non-equivalence; bounded MVP completion does not imply every canonical metric is implemented. Current review: ../16_INDEPENDENT_REVIEW_INITIAL.md. Desktop only (1440×900, 1024×768, 1280×600; both themes), no new screenshots; historical mobile evidence retained.

## Purpose

Expose a limited assigned-class review view using shared services.

## Defense concern addressed

Faculty need assigned-class trends and referral monitoring while major redesign stays with Student/Admin.

## Current behavior

Faculty loads authorize current subject/section access. Historical loads are undated and existing workflows remain the authority for notices and review.

## MVP behavior

Assigned-class average, final-grade coverage, Students requiring review, own referral/notice/ack counts and existing-workspace links. Prior-term class change returns unavailable when dated assignment evidence is absent. No cross-Faculty comparison controls.

## Deferred final behavior

Broad Faculty workflow redesign, historical teaching attribution, rankings and new notice/defer actions.

## Authorized roles

Faculty's own current subject/section loads and assigned referrals only. Foreign Faculty filters/record IDs fail closed.

## Required data

Current loads and their current grade rows; own assigned referral evidence. Do not fetch students' unrelated historical grades as a substitute for missing class history.

## Formula definitions

Consumes WP2's unit-weighted aggregation and WP4's counts/time contracts. Students requiring review=distinct current assigned Students with an eligible numeric subject outcome whose configured advisory risk is High/Moderate; this is not subject failure or official standing.

## UI components

Focused assigned-class summary/table, grade coverage, review count and support summary; existing dashboard entry link only.

## Files likely affected

faculty/assigned_analytics.php; minimal faculty/dashboard.php entry link; WP3-owned scoped repository/API adapters and shared view Faculty section.

## Database impact

Production SELECT-only. No schema, seed or row changes in udm_radar or udm_radar_scratch; no Migration 011. Isolated disposable synthetic fixtures only. The user's explicit temporary-database requirement overrides generic scratch-only skill guidance; tests must reject both protected names.

## Privacy and fairness boundaries

Prototype performance on synthetic academic records. Preserve canonical zero/text/units exclusions and independent honors policy. No official standing, pacing, benefits or punitive interpretation. Comparisons do not establish teaching performance or causation.

## Acceptance criteria

Same authorized grade subset yields same math as Admin; cross-Faculty data never appears; no new Faculty mutation workflow; dashboard banner containment preserved.

## Unit tests

Shared math/WP4 tests reused; distinct review counts separate risk from subject pass/fail.

## Integration tests

Subject+section ownership, another Faculty ID rejected, no historical or unrelated roster expansion, own-referral scope.

## Browser tests

Assigned-only filter options, focus/empty/desktop states, existing dashboard layout regression, authorized links.

## Empty and incomplete states

No current loads => no assigned-record data; no dated prior assignment => comparison unavailable with explanation.

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
