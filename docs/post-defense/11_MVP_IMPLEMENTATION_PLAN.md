# Post-defense analytics MVP implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans inline, with test-driven-development and one fresh final review. Steps use checkbox syntax.

**Goal:** Deliver safe working representatives of the five authorized MVPs from the approved v3 guide.
**Architecture:** Shared pure calculations/prediction/support contracts feed a SELECT-only role-scoped repository and report service. Three small portal wrappers render one accessible shared view and GET API; existing workflows remain authoritative.
**Tech Stack:** Existing PHP 8.2, PDO/MariaDB, native JavaScript/SVG charts, existing design tokens, pinned Playwright/Edge. No dependency upgrade.
**Spec:** work-packages/WP1_PREDICTION_CLARITY.md through WP5_FACULTY_FOCUSED_REVISIONS.md; approved external v3 Blue Word document (hash in implementation status).

## Global constraints

- Exact accepted base a79fb4838c5e2bc163cf943cda20afba9946d26b; feature branch only.
- No changes to canonical main source, Apache, udm_radar, udm_radar_scratch, models/dump, starter seed, Migration 011, private responses, recovery/handoff or manuscript.
- A new provenance-guarded temporary analytics database is the only mutation target; do not reuse generic scratch mutators.
- Use computeWeightedGWA(), canonical grade predicates and computeRiskFromAvg(). Preserve zero/status exclusions and independent honors.
- Bounds unavailable without accepted residual evidence; deferred decisions and dated historical Faculty/section attribution unavailable where unrecorded.
- Active-cohort and recorded-subject-slot denominators remain explicit. No total-GWA sums, rankings or causal claims.
- Scope documentation precedes product code; commits contain passing work only. Push/PR/merge are recorded according to actual authorization/status.

## Review focus

- Multiple/current reused or ambiguous Faculty assignments must not duplicate grades or grant historical access.
- Entirely absent enrollment rows cannot masquerade as full population coverage.
- Text outcomes, zero/nonpositive units and a partial latest term must not create false improving/declining claims.
- Referral/case cardinality, absent deferrals and timestamps after/before closure must not become invented workflow evidence.
- Async filter responses and errors must not leave charts/counts labeled with a different active filter.

### Task 1: Pure analytics contracts (WP1, WP2, WP4)

**Files:** includes/analytics/calculations.php, predictions.php, interventions.php; tests/unit/post_defense_analytics_test.php.
**Interfaces:** analyticsAggregate(array): array; analyticsChange(?float previous, ?float current, bool comparable=true): array; analyticsTrend(array): array; analyticsGroup(array,string): array; analyticsPrediction(array): array; analyticsClipRange(float,float): ?array; analyticsSupportSummary(array cases,array referrals,array actions): array.
- [x] Write independent literal tests for unequal weights, exclusions, distinct counts, empty denominators, point/relative change, partial/no prior term, range clipping, source/completeness and case/referral timings.
- [x] Run PHP regression check. Expected: explicit missing-contract failure.
- [x] Implement minimal pure contracts, with no I/O or persistence.
- [x] Run check. Expected: all assertions pass; record RED/GREEN evidence.

### Task 2: Scoped repository, filters and disposable integration (WP3 integration, WP5 access)

**Files:** includes/analytics/repository.php, service.php; tests/fixtures/analytics_mvp_fixture.php; tests/integration/post_defense_analytics_test.php; tests/fixtures/analytics_mvp_server.mjs.
**Interfaces:** analyticsFilters(array raw,array user): array; analyticsReadData(PDO,array user): array; analyticsBuildReport(array data,array filters,array user): array; analyticsReport(PDO,array user,array raw): array.
**Consumes:** Task 1 contracts. **Produces:** identity-free report with filters/options, summary, grouped/term series, Student prediction/change, support counts/time samples and authorized case/referral links.
- [x] Write failing tests for all filters, SQL role/record limits, cross-portal parity, foreign IDs, duplicate loads, historical attribution and support filters.
- [x] Verify fixture rejects live/scratch database names, creates only a unique analytics-prefixed DB and operates on synthetic records from fixed seed.
- [x] Run tests. Expected: absent scoped contracts fail before any protected DB connection.
- [x] Implement parameterized SELECT queries and strict input/role validation. No joins that multiply grade rows.
- [x] Run integration against only the owned disposable DB. Expected: all assertions pass; schema/data stable under report calls; cleanup removes only owned disposable schema.

### Task 3: Shared accessible views and API (WP1–WP4)

**Files:** api/academic_analytics.php; includes/analytics/presentation.php; assets/js/academic-analytics.js; assets/css/academic-analytics.css; student/academic_analytics.php; admin/comparative_analytics.php; tests/browser/post-defense-analytics.spec.js.
**Consumes:** Task 2 report. **Produces:** authenticated GET endpoint, Student history/prediction UI and Admin academic/support filters.
- [x] Write browser tests for login/roles, filters, real chart/table refresh, prediction range unavailable, loading/error/empty, stale responses, keyboard, responsive/light/dark states and authorized drill-down.
- [x] Observe missing-view/endpoint RED.
- [x] Implement role wrappers, shared native controls/cards/SVG charts/tables and fetch cancellation. Use textContent/escaped output; do not return Student identities or private notes.
- [x] Run focused browser tests against disposable server/data. Expected: green with no protected database requests.

### Task 4: Focused Faculty view and existing entry points (WP5)

**Files:** faculty/assigned_analytics.php; minimal entry-link edits in student/dashboard.php, admin/analytics.php and faculty/dashboard.php; existing support workspace read-only deep-link handling if required.
**Consumes:** same report restricted to current assigned grades and own referrals. **Produces:** assigned average/coverage/review/notice/ack summary and scope-safe navigation.
- [x] Write/extend browser tests for foreign Faculty rejection, no historical access, assigned-only options and unavailable prior class comparison.
- [x] Implement focused view and links; preserve existing workflow/attention-banner structure.
- [x] Run faculty-dashboard-layout-regression.spec.js first, then feature browser tests. Expected: green.

### Task 5: Final verification, review and durable handoff (guide revision 4)

**Files:** five work packages; 10_IMPLEMENTATION_STATUS.md; 12_ANALYTICS_METHODS.md; docs/ROADMAP.md; docs/IMPLEMENTATION_HISTORY.md; docs/UI_UX_FINDINGS_REGISTER.md.
- [x] Run new unit/integration/browser checks and applicable existing academic/prediction/dashboard tests; inspect every result and lint/diff check.
- [x] Confirm no protected paths, current databases, Apache, recovery/handoff or manuscript changed.
- [ ] Obtain fresh final code/academic/scope review; fix important findings with failing regression tests and rerun affected suites.
- [x] Record actual implemented/tested/deferred status, exact files, evidence, commit/PR status and next safe actions. Preserve branch/worktree.
- [x] Preserve passing work in the owner-authorized local stop-rule checkpoint; no push, merge or canonical deployment.


## Final execution record

Desktop-only owner override: no further mobile tests or screenshots. Independent reviewer dispatch failed at the account usage limit; author self-review is recorded, with independent review still required before integration. Support-only dropdown choices were corrected after five failing regression assertions. Test/lifecycle details and the local checkpoint record are in 10_IMPLEMENTATION_STATUS.md; exact hash is in Git and the final handoff.
