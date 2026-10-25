# Intervention analytics — MVP work package

Status: Implemented and tested locally; unmerged. Continuity integrated at e8d72a6; independent review/corrections in progress. Authorized scope: approved v3 Blue revision guide and the user's MVP package (26 October 2026). Base a79fb4838c5e2bc163cf943cda20afba9946d26b.

## Canonical metric mapping

MTR-17–18 counts, MTR-19 raw count only, MTR-20 canonical referral-to-notice mean; MTR-21 PLANNED / BLOCKED. See ../14_METRIC_RECONCILIATION.md for formulas, eligibility, evidence and non-equivalence; bounded MVP completion does not imply every canonical metric is implemented. Current review: ../16_INDEPENDENT_REVIEW_INITIAL.md. Desktop only (1440×900, 1024×768, 1280×600; both themes), no new screenshots; historical mobile evidence retained.

## Purpose

Summarize existing support cases/referrals and evidenced response times without changing the workflow.

## Defense concern addressed

Panel requested visible review, notice, acknowledgment and response-time summaries with authorized drill-down.

## Current behavior

Existing academic_support_cases, support_case_referrals, support_actions and status history record review/notice/closure events. There is no explicit deferred-decision field. updated_at does not prove first Faculty action.

## MVP behavior

Admin summary of recorded eligible cases, referrals created/reviewed, notices issued, acknowledgments, open/closed cases, canonical mean referral-to-first-notice time and limited median parent-case closed duration with sample counts. Deferred notices return unavailable. Filters include period/Faculty/subject/year/section/case status/referral decision. Scoped case/referral links go to existing workspaces.

## Deferred final behavior

New defer/review decisions or schema, causal impact claims, public feed, private-note exposure and expanded support governance.

## Authorized roles

Admin aggregate case scope; Faculty owns only its assigned referrals/case context; Student gets no intervention analytics endpoint data.

## Required data

Existing case/referral IDs and timestamps; MTR-20 referral.created_at and valid notice_issued_at only; no unrelated parent-case action substitution. No generic updated_at substitution.

## Formula definitions

Case counts deduplicate case_id; referral/notice/ack counts use referral IDs. reviewed=status beyond needs_review or issued notice. MTR-20 arithmetic mean hours=Σ(valid notice_issued_at-referral.created_at)/3600 divided by acted-upon distinct referral count. Unacted/invalid/reversed rows excluded. Closure remains a limited parent-case median, not MTR-21; actual samples shown, empty is null. Receipt count is not MTR-19 rate.

## UI components

Status/count summary, mean referral time and limited parent-case median/sample counts, explicit unavailable deferral explanation and an identity-free authorized drill-down table.

## Files likely affected

includes/analytics/interventions.php; shared repository/service support query and shared view support section owned by WP3 integration; minimal read-only anchors/target support in existing case workspaces if needed.

## Database impact

Production SELECT-only. No schema, seed or row changes in udm_radar or udm_radar_scratch; no Migration 011. Isolated disposable synthetic fixtures only. The user's explicit temporary-database requirement overrides generic scratch-only skill guidance; tests must reject both protected names.

## Privacy and fairness boundaries

Prototype performance on synthetic academic records. Preserve canonical zero/text/units exclusions and independent honors policy. No official standing, pacing, benefits or punitive interpretation. Comparisons do not establish teaching performance or causation.

## Acceptance criteria

No double counting, no assumed deferrals, timestamps require direct evidence, no Student identity/note leak or causal assertion, drill-down retains existing authorization.

## Unit tests

Duplicate-case counts, issued/reviewed/ack states, mean referral durations and limited parent-case median, missing/reversed timestamps and absent deferral evidence.

## Integration tests

Every support filter, own-referral restriction, no cross-Faculty referrals, authorization and exact drill-down scope.

## Browser tests

Status/decision filters, counts to authorized workspace links, null durations, empty cases and keyboard access.

## Empty and incomplete states

No cases => 0 counts with no time sample; no qualifying timestamps => N/A; explicit deferral count unavailable because the schema cannot prove it.

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
