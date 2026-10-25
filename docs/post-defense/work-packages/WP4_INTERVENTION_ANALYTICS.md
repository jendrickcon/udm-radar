# Intervention analytics — MVP work package

Status: Implemented and tested locally; unmerged. Independent review remains pending (account usage limit). Authorized scope: approved v3 Blue revision guide and the user's MVP package (26 October 2026). Base a79fb4838c5e2bc163cf943cda20afba9946d26b.

## Purpose

Summarize existing support cases/referrals and evidenced response times without changing the workflow.

## Defense concern addressed

Panel requested visible review, notice, acknowledgment and response-time summaries with authorized drill-down.

## Current behavior

Existing academic_support_cases, support_case_referrals, support_actions and status history record review/notice/closure events. There is no explicit deferred-decision field. updated_at does not prove first Faculty action.

## MVP behavior

Admin summary of recorded eligible cases, referrals created/reviewed, notices issued, acknowledgments, open/closed cases, median first evidenced Faculty action and closure time with sample counts. Deferred notices return unavailable. Filters include period/Faculty/subject/year/section/case status/referral decision. Scoped case/referral links go to existing workspaces.

## Deferred final behavior

New defer/review decisions or schema, causal impact claims, public feed, private-note exposure and expanded support governance.

## Authorized roles

Admin aggregate case scope; Faculty owns only its assigned referrals/case context; Student gets no intervention analytics endpoint data.

## Required data

Existing case/referral IDs and timestamps; first case-level Faculty action from recorded support actions/status events or direct notice timestamp, with actor-role checks. No generic updated_at substitution.

## Formula definitions

Case counts deduplicate case_id; referral/notice/ack counts use referral IDs. reviewed=status beyond needs_review or issued notice. First-action hours=(earliest evidenced Faculty timestamp-case.created_at)/3600; closure=(closed_at-created_at)/3600, excluding missing/reversed times. Report medians and contributing samples; unavailable is null, not zero.

## UI components

Status/count summary, median time/sample counts, explicit unavailable deferral explanation and an identity-free authorized drill-down table.

## Files likely affected

includes/analytics/interventions.php; shared repository/service support query and shared view support section owned by WP3 integration; minimal read-only anchors/target support in existing case workspaces if needed.

## Database impact

Production SELECT-only. No schema, seed or row changes in udm_radar or udm_radar_scratch; no Migration 011. Isolated disposable synthetic fixtures only. The user's explicit temporary-database requirement overrides generic scratch-only skill guidance; tests must reject both protected names.

## Privacy and fairness boundaries

Prototype performance on synthetic academic records. Preserve canonical zero/text/units exclusions and independent honors policy. No official standing, pacing, benefits or punitive interpretation. Comparisons do not establish teaching performance or causation.

## Acceptance criteria

No double counting, no assumed deferrals, timestamps require direct evidence, no Student identity/note leak or causal assertion, drill-down retains existing authorization.

## Unit tests

Duplicate-case counts, issued/reviewed/ack states, median durations, missing/reversed timestamps and absent deferral evidence.

## Integration tests

Every support filter, own-referral restriction, no cross-Faculty referrals, authorization and exact drill-down scope.

## Browser tests

Status/decision filters, counts to authorized workspace links, null durations, empty cases and keyboard access.

## Empty and incomplete states

No cases => 0 counts with no time sample; no qualifying timestamps => N/A; explicit deferral count unavailable because the schema cannot prove it.

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
