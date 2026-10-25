# Analytics MVP methods and continuation

Scope: the approved v3 Blue revision guide, limited to the five MVP work packages. The running views display **Prototype performance on synthetic academic records**. These are decision-support summaries, not official standing, honors, pacing, retention, benefits or teaching-performance determinations.

Reconciled with continuity PR #61 at e8d72a6 on 26 October 2026. The canonical MTR-01–21 mapping and explicit non-equivalences are in [14_METRIC_RECONCILIATION.md](14_METRIC_RECONCILIATION.md). A representative pooled-record MVP is not a claim that all canonical Student-mean, population-coverage or lifecycle metrics are implemented. This methods record supersedes the original checkpoint's partial-current/case-median first-action rules.

## Shared contracts

`includes/analytics/` contains pure calculations, prediction interpretation, support timing, a SELECT-only scoped repository, a report service and one shared presentation. The three portal wrappers consume `api/academic_analytics.php`; no portal duplicates the formulas. The API accepts GET only and rechecks the actual active account and role. Report reads use a read-only transaction. This package neither runs predictions nor persists academic or support changes.

## Grades and denominators

- Average GWA = sum of eligible final points × credit units / eligible credit units, through `computeWeightedGWA()` and the accepted canonical predicates. Group averages pool the eligible grade records; they are not summed GWAs or unweighted averages of Student averages.
- Preserve canonical numeric-zero and textual exclusions. Numeric 0.00 remains a failed subject outcome but does not contribute to GWA under the existing unresolved failed-unit policy. INC remains unresolved; P and other statuses retain accepted helper behavior. Missing or nonpositive units cannot contribute.
- Recorded slots = distinct `grades.id` values in scope. No enrollment roster is inferred from missing grade rows. Eligible grades = recorded slots with an eligible numeric final grade and positive credit units. Numeric coverage = eligible grades / recorded slots × 100.
- Final-outcome coverage = canonical final outcomes other than INC / recorded slots × 100. A complete recorded textual or zero outcome may therefore increase final-outcome coverage without increasing numeric coverage. It does not become numeric zero in GWA.
- Students (n) = distinct Students represented by the selected recorded slots, including Students whose selected grades cannot contribute to a numeric mean. The service also returns contributing-Student count and eligible units. An empty denominator is unavailable, never fabricated zero.
- A period is provisional when any recorded slot lacks a resolved final outcome or an otherwise eligible grade lacks usable units. The Student current-term scalar is then unavailable/Pending. Only the separately labeled recorded-point historical series may show a provisional partial mean; it is not complete Current Term GWA or population coverage.

## Changes and periods

Point change = current GWA − previous GWA. Relative change = (current GWA − previous GWA) / previous GWA × 100. Values use the canonical two-decimal GWA. Relative change is not a prediction; logarithmic change is not used.

The latest selected recorded term is compared with the latest earlier complete, calculable comparable term under the same non-period filters. Regular terms use earlier regular terms (semester 1/2), excluding summer; summer compares only with summer. When a semester is selected, it remains fixed while looking into earlier school years. The prior term may lie outside a selected school year; both period labels are visible. Missing previous term, incomplete current term, missing numeric GWA, or a nonpositive denominator returns unavailable change/direction. Point change is primary; relative percentage is secondary contextual text. Changes in pooled groups can also reflect cohort composition; this is not a matched-Student causal analysis.

## Assignment and role boundaries

- Student: own recorded grades and latest stored estimate; school-year and semester filters only.
- Administrator: active Student cohort and authorized support records; school year, semester, subject curriculum year, subject, section and Faculty filters. Case status and referral decision affect support summaries only.
- Faculty: only current configured-term grades matching the signed-in Faculty member's existing subject and current-section loads; only own recorded referrals/actions. No cross-Faculty comparison options or historical grade access is added.
- Current `faculty_class_loads` has no dated school-year/semester assignment history. Current Student profile section is not historical membership, and `grades.encoded_by` is not teaching assignment. Historical section/Faculty attribution and prior-class change remain unavailable. Production reads use dynamic `getCurrentTerm()`; the fixture is an explicit fixed synthetic 2026–2027 first-semester test scenario.
- A grade remains one record even when multiple Faculty loads match. Ambiguous teaching attribution is labeled unavailable in Administrator Faculty–subject groups. Faculty filters may include records from the shared assigned load without asserting exclusive teaching responsibility.
- Subject curriculum year describes the subject, not a reconstructed historical Student year level. For same-subject section/Faculty review, select a subject; group order is identifier/period order, never best/worst rank.

All comparison views visibly state: “Comparative academic patterns support authorized review and do not independently measure teaching performance. Differences may reflect Student composition, prior achievement, subject difficulty, class size, record completeness, and semester conditions.”

## Stored predictions and uncertainty

The latest prediction is selected by `generated_at DESC, id DESC`. A valid stored numeric estimate is interpreted separately from its cached risk field: configured rules derive the High/Moderate/Low advisory indicator. Model, legacy calculation, calculation fallback, unknown source, provisional and insufficient-data states remain distinct. Unknown completeness is provisional. Latest stored snapshots are separate from the historical filters; the timestamp does not prove later grades were incorporated.

No accepted held-out residual set was found in the accepted source/artifacts. Therefore production lower/upper bounds are null and visibly unavailable. RMSE is not converted into a universal margin or confidence interval. The pure clipping contract tests the [1.00, 4.00] eligible GWA domain but creates no margin. A future package must approve and document residual provenance, sample, coverage target, calibration, subgroup limits and evaluation before supplying numerical bands.

Prediction coverage denominator = distinct Students with selected recorded grade slots. Available numerator = Students with a valid latest stored estimate. Model/calculation are source-family dimensions; provisional/unknown-completeness overlaps those dimensions and must not be summed with them. Complete/provisional fallback cross-tabs and canonical term active-population MTR-16 are not implemented. The Student's own estimate may still be displayed when no grade row is present.

## Support evidence and times

Eligible cases are distinct existing cases within role and selected case-period/status scope. Referrals created/reviewed/issued/acknowledged are distinct referral records, not case counts or Student counts. Reviewed means an existing action-taken, acknowledged or closed status, or recorded notice timestamp; it is a status summary, not independent proof of the quality of review. Issued and receipt acknowledgment use their own recorded timestamps.

No explicit notice-deferred decision exists in the accepted schema. `notices_deferred` is unavailable; closure without a notice is not inferred as deferral. Selecting deferred yields no attributed referral records and retains that explanation.

MTR-20 now uses the canonical arithmetic **mean** of distinct referral-ID cases' valid `(notice_issued_at − support_case_referrals.created_at)` durations, in decimal hours. Numerator is the sum of qualifying referral durations; denominator is their actual count, displayed as n. Only notice issuance supplies verified first-action evidence here; earlier unrecorded actions and generic same-parent Faculty events are not inferred. Missing, zero-date, malformed and reversed evidence is excluded; unacted referrals never contribute zero. Faculty sees own referrals only. This is the owner's authorized canonical-mean correction, independently assessed in IR-04; it is not a median silently renamed as a mean. Naive timestamps use Asia/Manila.

The closure display remains a **limited median recorded parent-case closed duration**, `(academic_support_cases.closed_at − academic_support_cases.created_at)`, over valid currently closed parent cases, with its own actual n. It is explicitly not canonical MTR-21. Latest reopened/referral lifecycle mapping is unestablished, and MTR-21 stays PLANNED / BLOCKED; no schema change or equivalence approval is made.

Notice count and issued/review filter inference use the same valid, non-reversed referral timestamp predicate as MTR-20. Receipt timestamps must be valid and not precede known creation or valid linked issuance. Valid legacy receipt timestamps without linked issuance may remain in an honestly labeled raw count. This count is not canonical MTR-19 acknowledgment rate; no missing notice or new rate is fabricated.

Subject, section, Faculty, curriculum-year and decision filters restrict support attribution to matching recorded referrals. Unreferred cases remain in the Administrator unfiltered case count but are not attributed to a subject or Faculty member without evidence. Counts and before/after patterns do not demonstrate that an intervention caused a grade change.

Aggregate responses contain no Student names, identifiers, raw notes or messages. Authorized case/referral identifiers only link to existing role-guarded workspaces. Those screens retain their current record authorization and can apply a validated read-only selected-record filter. No public activity feed or automatic notice is added.

## Reproducing verification safely

Run from this feature worktree, with existing PHP/MariaDB and the repository-pinned Playwright/Edge installation:

```powershell
& C:/xampp/php/php.exe tests/unit/post_defense_analytics_test.php
node tests/fixtures/run_analytics_mvp.mjs
```

The runner creates a random `udm_radar_analytics_mvp_<16 hex>` schema, imports only parsed accepted CREATE TABLE definitions, inserts fixed-seed synthetic variation, runs integration and real-login browser checks on a loopback private PHP server, and removes only its matching provenance-marked schema in `finally`. Owner permission allows reading database/udm_radar.sql solely for this DDL; exact section line ranges/table names/statement hashes, source SHA-256, provenance marker hash and byte-identical source result are logged. No dump/example data is executed. Both protected database names are rejected. Every operation after successful setup is inside cleanup scope; in-memory state and stdin cleanup work even if the state file cannot be written. A post-DROP filtered information-schema query asserts absence of the exact owned name. No existing database is connected to or inspected. Apache and the starter seed are untouched; ephemeral randomized login secrets stay ignored and are removed after cleanup.

Current reconciliation evidence is ignored `logs/analytics-reconciliation/`; `logs/post-defense-mvp/` is retained historical checkpoint evidence. The default launcher runs required Faculty desktop/structural layout checks before feature browsers. It excludes legacy mixed-viewport and screenshot cases under owner scope; feature viewports are 1440×900, 1024×768 and 1280×600 in both themes, with no new screenshots. Layout regression is generated HTML/source evidence; feature login/API/filter/drill-down tests use actual PHP handlers and synthetic records. Of the feature cases, controlled unavailable/race responses are separately mocked evidence, not extra live end-to-end tests. Historical mobile evidence is neither deleted nor newly certified. See 15_RECONCILIATION_VERIFICATION.md for current commands, actual totals, source sections, provenance and lifecycle results.

Use [10_IMPLEMENTATION_STATUS.md](10_IMPLEMENTATION_STATUS.md) for actual current results, exact unfinished work, delivery status and member continuation. Do not run uninspected legacy database mutators, reuse a protected database, merge, publish or replace canonical main as a continuation shortcut.
