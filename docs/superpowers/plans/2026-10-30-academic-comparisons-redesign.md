# Academic Comparisons Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task by task. The owner supplied the presentation design and requested implementation on the existing branch.

**Goal:** Make academic comparison configuration visible and understandable, prevent invalid Faculty plots, and remove artificial empty space from Overview.

**Architecture:** Preserve the report API, aggregation, authentication, and Faculty implementation. Introduce an Admin-only controller consuming the existing report and existing filter submission; isolate its markup and styles. Use native HTML scalar bars with exact values, not a new chart dependency.

**Tech stack:** PHP, existing vanilla JavaScript, existing CSS tokens, installed Playwright 1.63.0.

**Spec:** Owner's 2026-10-30 content-balanced-panel correction and Academic Comparisons priority revision, with four supplied screenshots.

## Constraints

- Continue `fix/admin-dashboard-analytics-interpretability` from `e84eb29044dbc4c70013e1e4bb969bf5818a9765`.
- No database use, formula/eligibility/authentication/model/migration changes, Student changes, or Joshua branch changes.
- Desktop only: 1440x900, 1280x600, 1024x768; Light and Dark. No screenshots, traces, video, or mobile tests.
- No new framework, UI library, chart library, or copied component code.
- Commit reviewed local candidate; no push, merge, or deployment.

## Review focus

- Faculty groups must originate in the response for the explicitly selected common subject and period; pending requests cannot display an old result.
- Null, numeric zero, provisional values, and unavailable group metrics must remain distinct.
- Mode changes preserve groups; Group C never exceeds three groups and has predictable focus return.
- Long labels, enlarged text, empty groups, and network failure must not cause overflow or fabricated results.
- Shared Faculty and Student presentation must retain their existing behavior.

## Task 1: Natural Overview layout

Files: dashboard CSS, Admin presentation controller, presentation markup, existing interpretability browser test.

- [ ] Replace forced-height test with measured natural row alignment and 12–32px interpretation/action gap assertions.
- [ ] Observe failures against the current height contract.
- [ ] Remove viewport/minimum panel heights. Use intrinsic grid stretch, compact content flow, table-only maximum scrolling, and independent stacked heights.
- [ ] Preserve the stateful chart toggle, exact values, captions, keyboard support, and themes.
- [ ] Run the desktop/theme matrix and check focus, scrolling and no horizontal overflow.

## Task 2: Task-first comparison workspace

Files: `includes/analytics/presentation.php`, new `assets/js/admin-academic-comparisons.js`, new `assets/css/admin-academic-comparisons.css`, narrow integration in `assets/js/academic-analytics.js`.

- [ ] Add failing generated-browser cases for visible dimensions, historic context relocation, common Faculty constraints, invalid/no-data states and compact valid results.
- [ ] Move Admin historical summary/trend/table into Overview without duplicating IDs; retain Faculty history.
- [ ] Render a single Admin workspace: dimension, common scope, groups A/B/optional C, metric, mode and explicit compare action.
- [ ] Submit scope using the existing form/report loader. Validate response filters and Faculty subject keys before allowing a chart.
- [ ] Render scalar bars and compact value/sample/coverage/status summary. Preserve A minus B difference semantics and canonical report values.
- [ ] Keep full exact evidence and calculations/interpretation disclosures. Never invent group prediction metrics.
- [ ] Verify mode persistence, three-group limit, predictable focus, pending/error recovery and partial values.

## Task 3: Regression and delivery

Files: synthetic report fixture, browser tests and applicable existing comparison regression selectors.

- [ ] Use actual PHP markup, real pure report aggregation and mocked HTTP transport; no database or credential fallback.
- [ ] Run PHP syntax and pure academic regression checks; installed Playwright syntax and version checks.
- [ ] Run updated Admin tests, shared Faculty layout and relevant desktop sidebar checks. Exclude mobile/screenshot/live-account tests explicitly.
- [ ] Review committed, unstaged, staged and untracked scope separately; verify protected paths unchanged.
- [ ] Commit locally, verify clean branch, and return exact SHA, scope, evidence and authenticated-test limitations.

## Design references and translation

- [Carbon form guidelines](https://www.carbondesignsystem.com/building-blocks/core/components/form/guidelines): aligned labeled controls and related-input grouping, translated to native fieldsets and the existing portal form controls.
- [USWDS alerts](https://designsystem.digital.gov/components/alert/): concise contextual correction notices, translated to native status/alert regions beside affected controls.
- No source code or CSS copied, so no third-party component or dependency license is introduced. Existing UDM-RADAR tokens, buttons, typography and cards remain authoritative.

## Evidence boundary

Generated browser tests establish presentation and client validation behavior, not live authentication or independent database authorization. Owner authenticated retest remains required before release.

## Owner closeout, 2026-10-30

- Implemented natural Overview rows, six visible comparison dimensions, scoped Faculty selection, compact invalid/unavailable states, exact evidence, historical relocation and three-group/mode behavior.
- Owner requested preserving both original comparison presentations: Separate cards reuse the original header, badge, metric-box and statistics classes; Overlay retains the original unified chart-left/summary-right composition.
- Before the final composition adjustments: 40 generated/mocked browser tests passed, 6 Faculty layout/source checks passed, and 531 PHP assertions passed. Logs remain ignored under `logs/admin-interpretability-codex/`.
- Owner explicitly requested skipping further testing. The final composition adjustments and initial-loading guard were therefore not rerun. No screenshots, mobile tests, database tests, or authenticated journeys were run.
- Existing opt-in authenticated comparison specs still contain old disclosure/card-selector assumptions. They require migration to the new Admin workspace before merge; their Faculty paths were preserved. Do not describe those suites as passing.
- No framework, dependency, backend calculation, authorization, database, model, or Student implementation changes.
- Recommendation: local owner retest; no push or merge. The planned legacy-suite update is deferred under the owner's wrap-up instruction.

### Exact changed-file inventory for this correction

1. `assets/css/dashboard.css`
2. `assets/css/admin-academic-comparisons.css`
3. `assets/js/academic-analytics.js`
4. `assets/js/admin-academic-comparisons.js`
5. `includes/analytics/presentation.php`
6. `tests/browser/admin-dashboard-interpretability.spec.js`
7. `tests/browser/admin-academic-comparisons.spec.js`
8. `tests/fixtures/admin_comparison_report.php`
9. `docs/superpowers/plans/2026-10-30-academic-comparisons-redesign.md`
