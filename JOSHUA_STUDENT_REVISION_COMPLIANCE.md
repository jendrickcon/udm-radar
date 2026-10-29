# Student Portal review against the panel revision PDF

Reviewed: 29 October 2026 (Asia/Manila).

Source: `UDM-RADAR-Documentation-Outline-Revisions.pdf`, supplied by the user. **One page**; references below identify its numbered sections and bullets. SHA-256: `596461b509bfade1bb27e645129997b097a63b1d0b5326e21c95068230393629`.

**Verdict: PARTIALLY MATCHED.** The current local Student Portal implements the central output explanations, own-record comparisons, historical charts and authorization boundaries. It does not yet satisfy every panel requirement. The principal explicit functional gap is numerical lower/upper forecast boundaries. Full cross-portal reconciliation, remaining workflow verification and affected manuscript/manual/slide updates also lack complete current evidence.

**Ownership clarification:** this verdict concerns the PDF's system-wide requirements, not an assertion that all gaps are unfinished Member 2 deliverables. Numerical forecast-bound calculations belong to the shared model/analytics owner until explicitly assigned; population comparisons and intervention oversight belong primarily to Faculty/Admin. Joshua's current delivery remains the approved Student refinements and their verification. Shared dependencies and proposed redesigns do not silently expand that delivery.

This is a comparison review, not implementation authorization. The PDF's instructions were treated as requirements to assess, not commands to alter code, calculations, databases, models, permissions or publication state. Faculty/Admin-wide work is separated from Joshua's Student scope. No application/database changes or fresh runtime tests were performed in this review.

## Status meanings

- **Matched:** relevant implementation exists with identified source evidence; this does not substitute for owner usability acceptance.
- **Partial:** some required behavior exists but a clause or verification remains incomplete.
- **Blocked:** a requested capability is not implemented because an accepted prerequisite/method is missing; an honest unavailable message is safer but is not completion of that capability.
- **Other portal/shared:** coordinate with the Faculty/Admin or model/documentation owner; do not add unauthorized peer data to Student views.

## 1. Predictive output and forecast range — PDF page 1, section 1

| Requirement | Student result | Evidence / remaining work |
|---|---|---|
| State that the Decision Tree Regressor outputs a numerical estimated GWA | Matched | `includes/analytics/predictions.php:12` returns numeric `estimated_gwa`, labels Decision Tree versus legacy calculation/fallback, and provides an explicit regressor explanation; `assets/js/academic-analytics.js:196` renders it |
| Separate advisory risk from the model's numerical prediction | Matched | Prediction helper derives advisory through `computeRiskFromAvg`; Academic Review renders separate Estimated GWA and Advisory indicator fields. Earlier authorized Dashboard correction aligns its advisory with its displayed estimate |
| Display numerical lower and upper estimated boundaries | **Blocked / not matched** | `includes/analytics/predictions.php:22` deliberately returns `lower=null`, `upper=null`, `range_status=unavailable`. UI shows Unavailable. The method lacks an accepted held-out residual set; no margin is inferred from RMSE. Placeholders do not fulfill the PDF's requested bounds |
| Show source, evidence completeness, provisional/fallback status and concise explanation | Matched in the available-evidence contract | Academic Review shows source/completeness/provisional flag/generated timestamp. Dashboard uses prediction explanation metadata. Trend details show source, completeness, timestamp limitations and that the estimate is not a final grade. Legacy unknown evidence remains unknown |

**Required next decision:** the model/analytics owner must approve a forecast-range method and supporting validation evidence before numerical bounds are implemented and tested. This is a shared dependency, not permission for Member 2 to invent intervals or retrain a model. Keep range unavailable until then, and report the panel item as open.

## 2. Comparative academic analytics — PDF page 1, section 2

| Requirement | Student result | Evidence / scope |
|---|---|---|
| Previous/current GWA, point change, percentage change and history | Matched when records are eligible | `assets/js/academic-analytics.js` Student summary displays current selected term, previous comparable term, point/relative change and historical trend. `student/performance_trend_model.php:23` reuses accepted helpers; `includes/analytics/calculations.php:31` computes differences. Completed-to-completed comparison is distinct from current progress or estimates |
| Missing/incomplete comparisons should not mislead | Matched | Missing results remain null/Pending; noncomparable gaps/summer cases are withheld. Plain-language comparison identifies the actual periods. Exact chart/table evidence is retained |
| Average-GWA comparisons by semester, school year, year, subject and section | Other portal/shared for population comparisons | Student owns its own term history. Population/cohort comparison is not a Student entitlement. The Student cohort calculation/privacy contract remains blocked. Administrator/Faculty comparison readiness is the teammate/shared owner's review, not certified here |
| Same-subject comparisons across sections and Faculty assignments | Other portal | These expose authorized group/assignment views and belong to Faculty/Admin. They are not an unfinished requirement to expose other Students on Joshua's portal |
| Year/semester/year-level/subject/section/Faculty filters | Role-scoped match; broader requirement belongs to other portals | `includes/analytics/service.php:9` and presentation allow Students only school-year/semester filters; unsupported Student filters are rejected. This restriction is intentional. Other roles' full filter correctness requires their own verification |
| No ranking/teaching-performance interpretation | Boundary preserved for Student scope | Student exposes no Faculty ranking or peer comparison. Do not claim that this Student review verifies every Faculty/Admin chart's wording |

Do not interpret the PDF's broad filter list as authorization to relax Student own-record privacy. Compare equivalent metrics only within each role's authorized records.

## 3. Dashboard and intervention interpretability — PDF page 1, section 3

| Requirement | Student result | Evidence / remaining work |
|---|---|---|
| Clear summary cards, history and previous/current comparisons | Substantially implemented; usability acceptance pending | Dashboard and Academic Review have summaries; Performance Trend distinguishes completed/current/estimated evidence, two-decimal ticks, exact values, reference guides, accessible table/disclosures and truthful missing states. Academic Review historical chart spacing was separately improved |
| Forecast ranges plus subject/year-level charts | Partial / scope decision | Numerical ranges remain blocked as above. Own subject grades are shown in Student tables; dedicated Student subject/year-level comparison charts are not evidenced in the current Academic Review layout. Clarify which requested charts belong to Student versus Administrator population analytics before adding new features |
| Referrals reviewed, notices issued/deferred, acknowledgments and open/closed case summaries | Other portal for aggregate oversight; Student integration needs separate workflow evidence | Student Feedback/Support exists for its own notices and acknowledgment. `student/feedback.php:124` checks ownership/status before acknowledging. The current 25-test suite covers navigation and relevant negative filters, not acknowledgment writes or the complete intervention lifecycle |
| Faculty actions, intervention frequency, response-time indicators and authorized drill-down | Primarily Faculty/Admin/shared owner | Not a reason to expose other Students or Faculty-wide operational analytics in the Student portal. Coordinate with the teammate and preserve existing metric limitations |
| Main redesign effort on Student/Admin; Faculty focused on assigned work | Current ownership is consistent; full redesign acceptance not established | Joshua's refinements are Student-scoped, including role-guarded changes in shared presentation assets. The separate proposed visual-first Academic Review redesign remains a distinct scope from the small spacing improvement; do not label it fully delivered |

The PDF's requested usability outcome still needs the owner's acceptance. Source presence and automated geometry checks cannot prove that Students understand the views. Do not conflate the system's academic advisory risk with official standing, pacing, benefits or eligibility.

## 4. Verification and documentation — PDF page 1, section 4

| Requirement | Current evidence | Remaining gap |
|---|---|---|
| Verify predictive labels, bounds, GWA changes and averages | 550 earlier PHP checks and 16 calculator JS tests; current labels and accepted calculations inspected | Numeric forecast bounds cannot be verified while absent. All population comparative averages are outside this Student-only audit |
| Test filters, role/record authorization, incomplete states and interactions | Latest full Student browser run: 25 passed, zero failures/skips/flaky; source/test fingerprints and owned-database cleanup recorded. Focused chart/mobile and shared-role checks are separate evidence | Not exhaustive workflow, screen-reader or user-comprehension certification. Notice acknowledgment/intervention submissions are not certified by GET navigation |
| Confirm consistent authorized results across all three portals for identical filters | Partial | Tests verify Dashboard/Academic Review estimate/advisory agreement and timestamp ties; shared-role smoke checks exist | No complete three-portal, equivalent-authorized-population/filter/metric reconciliation matrix is evidenced for this latest candidate. Same filter text does not imply identical populations; compare the authorized overlap and defined metrics |
| Update affected manuscript, diagrams, screenshots, User Manual, slides and technical evidence after verification | Technical evidence and local MD reports are updated | A reconciled set of affected manuscript/User Manual/diagram/slide revisions and approved screenshots tied to the final candidate was not provided/evidenced here. A progress report is not proof that those artifacts are updated |
| Avoid causation, teaching-quality, official-standing and guaranteed-outcome claims | Matched for inspected Student explanations | Existing estimate/prototype cautions and unavailable states preserve these boundaries. Broader portal/manuscript wording needs its own check |

### Actual verification limits

The latest complete run used a new owned disposable database and real fixture authentication, not the installed 291-Student database. All 25 tests passed after the Academic Review spacing refinement; data stayed unchanged and deletion was verified. Earlier 550-PHP/16-JS/14-syntax totals refer to their separately recorded runs and are not a model accuracy score.

Original-account password verification remains pending by the user's choice to preserve passwords. Owner manual acceptance, comprehensive manual accessibility checks and write-workflow testing remain distinct. The PDF planning-export flow is also set aside because the approved private template/storage is missing; it is a separate feature blocker, not the panel's numerical forecast-range requirement.

## Student-focused action order

1. **Keep completed work and honest unavailable states.** No need to reimplement history, relative change or protected Student scoping.
2. **Escalate the numerical forecast-range requirement** to the model/shared analytics owner for an approved method and validation evidence. Record it as an unmet panel item until implemented and verified.
3. **Get a focused Student usability acceptance.** Check estimate-versus-advisory meaning, comparison periods, incomplete grades, guide labels and Student chart readability. Decide whether the separately scoped Academic Review redesign or an own-subject visual is actually needed to satisfy section 3.
4. **Plan guarded notice/acknowledgment regression** in an approved disposable target if Student intervention workflow acceptance is part of this package; preserve own-record and human-reviewed notice boundaries.
5. **Coordinate a three-role reconciliation matrix** with the Faculty/Admin teammate using equivalent authorized data, periods and metric definitions. This audit does not authorize changing those portals.
6. **Update only affected formal deliverables** after the relevant verification: manuscript sections, diagrams, User Manual pages, desktop screenshots and slides. Reference exact final commit/evidence once publication is authorized; do not claim this dirty local candidate is already merged.

No new implementation, schema/model change, data mutation, password reset, commit, push, PR or merge was performed for this audit.

## Source/evidence index

- Supplied revision PDF, page 1, sections 1–4; extracted text and rendered-page evidence retained in ignored `logs/pdf-revision-review/`.
- [Prediction contract](includes/analytics/predictions.php)
- [Authorized analytics service](includes/analytics/service.php)
- [Student trend model](student/performance_trend_model.php)
- [Student Performance Trend](student/trend.php)
- [Student Dashboard](student/dashboard.php)
- [Own-record support/acknowledgment](student/feedback.php)
- [Shared role-scoped analytics presentation](assets/js/academic-analytics.js)
- [Latest regression report](JOSHUA_STUDENT_REGRESSION_REPORT.md)
- [Member 2 roadmap](JOSHUA_MEMBER2_ROADMAP.md)
- [Canonical roadmap and blocked shared metrics](docs/ROADMAP.md)
