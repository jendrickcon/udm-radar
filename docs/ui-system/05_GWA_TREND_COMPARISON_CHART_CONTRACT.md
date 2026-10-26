# Student GWA trend and future comparison contract
Documentation: READY FOR OWNER REVIEW. Student implementation: NOT STARTED. Numeric cohort: BLOCKED.
Current reference contract: own-recorded-gwa-reference-v1.
Active series: **Your recorded GWA**.
Comparison status: **BLOCKED**.
Exact disabled legend/column text: **Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.**

## Accepted inputs versus unavailable comparison
Current accepted Student analytics are own-record scoped. [Metric reconciliation](../post-defense/14_METRIC_RECONCILIATION.md) documents pooled recorded-grade group means as different from Student-mean aggregation. They cannot supply the requested Student cohort average. No existing data/role/privacy contract authorizes that Student-facing comparison.
The reference accepts recorded **complete** own-term GWA supplied by an authorized host, or null for unavailable/incomplete term. It does not compute GWA or cohorts. Every comparison numeric value is rejected, including a claimed enabled/approved flag. An optional disabled comparison dataset contains no points. A future implementation must use a separately versioned approved contract, not flip this reference flag.
Keep the blocked explanation visible whether the optional empty dataset is included or not.

## Library-neutral model
Input: schemaVersion exact string, scale{min:0,max:4}, points ordered by schoolYear and semester, cohort{status:'BLOCKED'}.
Each point: schoolYear consecutiveYYYY-YYYY, semester1/2/3, state 'recorded'|'incomplete'|'unavailable', value finite GWA from1.00 through4.00 only when recorded, otherwise null. The axis still begins at0; the canonical helper's zero exclusion means0 is not an eligible recorded GWA. No identifiers, raw grades or peer records. The host attaches authorized period labels; reference creates readable term labels and marks semester3 as Summer.
Reject duplicates, reversed term order, invalid/future schema, malformed year/state, missing values, numericstrings, unsupported cohort keys/status/values and out-of-scale numbers. Do not create extra periods or fill gaps with numbers.
Rendered output: labels, own values, availability flags, empty message, visible legend and table rows all derived from the same validated input. At least two valid ordered recorded points select a line; one valid point selects a scalar bar and does not imply a trend. Missing-term intervals break line segments rather than silently joining across unrepresented periods; plot points are not automatically a comparable-change calculation.

## Scale and states
Axis0–4 ascending per configured higher-is-better prototype scale. Do not reverse it. No simulated or placeholder average, smoothed interpolation or predicted number in recorded series. Missing or incomplete value =null, shown as Pending/Unavailable; no false zero and no risk inference. If all values absent, show “No complete recorded GWA is available for the selected terms.” and preserve blocked comparison message/table.
Canonical own GWA remains unit-weighted via existing helpers, zero/text exclusions intact; incomplete Current Term GWA unavailable. A future explicitly provisional partial recorded-point line would require a different label/contract; this own recorded complete-series contract rejects a numeric incomplete value.
Tooltip/table precision is2decimal points, plus state. Cohort column contains unavailable text only. Accessible table is the equivalent source if plotting or host library is unavailable.

## Enablement gate: Member 2 verifies, owner approves
The owner approved interface preparation only. Cohort is BLOCKED, not DEFERRED. Member 2 owns both the Student unavailable interface and the calculation/privacy proposal, and must complete self-verification; numeric release still requires final owner approval. Neither member may invent an average or privacy threshold.

| Gate | Required decision and evidence | Status |
| --- | --- | --- |
| 1 | Student-level unit of analysis, preferably one eligible Student GWA per comparable term | BLOCKED |
| 2 | Comparable cohort scope: program, subject curriculum year, school year and semester, with all membership rules explicit | BLOCKED |
| 3 | Eligible Student records, distinct-person handling and active/lifecycle boundary | BLOCKED |
| 4 | Excluded records, duplicate attempts and invalid evidence | BLOCKED |
| 5 | Missing/incomplete-term treatment, denominators and disclosure | BLOCKED |
| 6 | Minimum privacy-safe cohort size, suppression and differencing/filter attacks | BLOCKED |
| 7 | Aggregation/unit weighting: distinguish unit-weighted per-Student GWA from between-Student aggregation; no pooled substitution | BLOCKED |
| 8 | School-year and semester comparability, regular/summer policy and membership periods | BLOCKED |
| 9 | Role visibility and server authorization; Student own record plus separately authorized privacy-safe aggregate only | BLOCKED |
| 10 | Identifier-free synthetic expected results, exclusions, duplicates, sparse groups and authorization negatives | BLOCKED; tests PLANNED, no data contract accepted |
| 11 | Privacy/fairness review: composition bias, re-identification, small groups, non-ranking copy | BLOCKED |
| Final | Owner approval of Member 2's verified contract and exact implementation scope | BLOCKED |

No number is proposed for group-size threshold. Do not infer a threshold from another project's practice. Use no Faculty assignment default for Student cohorts. No single other Student's GWA/identity, raw rows or suppressed group counts may leak. Member 2 must consider whether release of counts/filters enables differencing before a future server endpoint is authorized.
No backend/DB changes or contract enablement are authorized now. Member 2 retains Student chart presentation and Student calculation/privacy responsibility after approval; shared global assets still need one assigned custodian.

## Reference acceptance cases
Node reference tests: own complete values preserve precision, null/incomplete accepted without numeric invention, all-missing empty text, disabled cohort legend exact, any numeric cohort/enable flag rejected, bad order/duplicates/scale rejected, null gaps remain gaps, all table cells match validated values, no fetch/import/DB.
Future production cases: own authorization, approved aggregate and suppression (still BLOCKED), complete/incomplete/missing/summer data, both themes/desktop widths, plot/table/legend parity, no script/library fallback and keyboard. Reference checks are not these production passes.

## Student growth and self-verification

Member 2 verifies current and previous comparable recorded-term GWA using accepted helpers, point change (current minus previous), supporting relative change only under approved nonzero-baseline comparability, grade/prediction coverage and missing/incomplete/summer rules. Exact start/end periods and population are visible. Incompatible inputs display “Change unavailable” with reason; plotted adjacency never establishes comparability.

Display real school-year and semester labels; missing terms remain gaps. The exact accessible table follows the visualization; legend, tooltips and table share values, precision and states. Test Student own-record authorization and another-Student denial, Goal Calculator, Grades/history, Enrollment Planning, themes, keyboard and desktop containment. Cross-review supplements Member 2's mandatory self-verification.
