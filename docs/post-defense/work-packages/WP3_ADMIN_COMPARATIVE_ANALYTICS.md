# Administrator comparative analytics and shared transport — MVP work package

Status: Implemented and tested locally; unmerged. Independent review remains pending (account usage limit). Authorized scope: approved v3 Blue revision guide and the user's MVP package (26 October 2026). Base a79fb4838c5e2bc163cf943cda20afba9946d26b.

## Purpose

Deliver one filtered comparative-review page and the shared read-only repository/API/view infrastructure.

## Defense concern addressed

Show understandable term/year/subject/section/assignment patterns with visible counts and fairness limits.

## Current behavior

Current Admin analytics are broad snapshots. Grades record academic periods but student profiles and Faculty loads describe current membership; neither stores dated historical assignments.

## MVP behavior

School year, semester, year level, subject, section and Faculty filters; unit-weighted averages grouped by semester/year/curriculum year/subject/current section/current Faculty-subject assignment, counts and coverage. Previous recorded comparable period uses the same non-period filters. Historical section/Faculty attribution remains unavailable rather than borrowed from current loads.

## Deferred final behavior

Historical enrollment/teaching-assignment schema, adjusted causal models, rankings, exports, full chart redesign and large-cohort query optimization.

## Authorized roles

Admin may compare authorized records. Shared repository scopes Student IDs and Faculty current subject/section ownership before filtering or aggregation; identifiers and private notes are omitted.

## Required data

grades, subjects, active student_profiles/users, current faculty_class_loads, latest predictions. Faculty attribution requires one unambiguous current assignment; historical rows remain unattributed. Year-level history uses subject curriculum year, visibly labeled.

## Formula definitions

Pooled unit-weighted eligible final-point average, not sum of GWAs. n=distinct matched Students; eligible-grade count=canonical numeric GWA contributors with positive units; numeric coverage=eligible/recorded slots*100. Recorded-outcome coverage and incomplete counts are distinct. Missing enrollments outside grades cannot be inferred.

## UI components

Native labeled filters, active-filter summary, summary cards, horizontal comparison bars, historical term line, accessible tables, clear loading/error/empty states and existing-workspace links.

## Files likely affected

includes/analytics/repository.php; includes/analytics/service.php; api/academic_analytics.php; includes/analytics/presentation.php (shared view); assets/css/academic-analytics.css; assets/js/academic-analytics.js; admin/comparative_analytics.php; focused existing-page entry links.

## Database impact

Production SELECT-only. No schema, seed or row changes in udm_radar or udm_radar_scratch; no Migration 011. Isolated disposable synthetic fixtures only. The user's explicit temporary-database requirement overrides generic scratch-only skill guidance; tests must reject both protected names.

## Privacy and fairness boundaries

Prototype performance on synthetic academic records. Preserve canonical zero/text/units exclusions and independent honors policy. No official standing, pacing, benefits or punitive interpretation. Comparisons do not establish teaching performance or causation.

## Acceptance criteria

All six filters work; joins do not multiply grades; every comparison includes n, grade coverage, scope and period; historical attribution limitations are visible; no best/worst Faculty order.

## Unit tests

Filter type/allowlist validation, grouping and unknown attribution; shared calculations are tested under WP2.

## Integration tests

Actual SQL filters, duplicate loads, same-subject separate Faculty/sections, role constraints, period changes and unauthorized drill-down attempts on a disposable fixture.

## Browser tests

Dropdowns, chart/table refresh, fairness text, keyboard, loading/error/empty and theme/viewport matrix.

## Empty and incomplete states

No rows => table headings plus in-table empty message; unavailable historical assignment => warning and no falsely attributed mean.

## Evidence to preserve

RED/GREEN logs, actual pass/fail/skip counts, isolated-database target/provenance and cleanup evidence, screenshots, request/response role checks and protected-path/source hashes under ignored logs/post-defense-mvp/.

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
