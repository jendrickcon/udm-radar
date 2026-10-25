# Independent review — initial findings

**Reviewer:** `/root/independent_reconciliation_review`, a separate agent process from the implementation author.
**Verdict:** **CHANGES REQUIRED**
**Publication:** Blocked pending corrections, follow-up review, and fresh authorized verification.

**Reviewed baseline:** `origin/main` `e8d72a6d487444dcc6b5d11b1e0824376d7e8015`
**Original implementation:** `554312cbef2efab9c0b574cef18f518be2b3b6d6`
**Reconciled merge reviewed:** `c32bc38d99d38401b9690adcc1a35d6f7f739a7c`
**Worktree:** `C:\Users\Alexa Mhae Tala\.codex\worktrees\post-defense-analytics-mvp\capstone`

This artifact records the initial source review. Corrections being prepared by the implementation author are not approved by this artifact.

## Review performed

I inspected the actual analytics PHP, JavaScript, CSS, wrappers, modified workspace entry points and support drill-down queries; new unit, integration, browser and fixture files; the five work packages; implementation status, methods, handoff and metric reconciliation; canonical definitions, requirements, acceptance matrix and decision documents; applicable repository guidance; and the separate committed, unstaged, staged and untracked change layers.

I made no file, index or branch changes. I did not access either protected database, read the protected dump/model artifacts, or run database/browser tests. I ran one database-free PHP reproduction using synthetic in-memory arrays to confirm partial-current, summer-comparison and invalid-timestamp behavior. This is focused reproduction evidence, not integration or browser coverage.

No CRITICAL finding was identified. Findings comprise two HIGH and six MEDIUM items.

## Publication-blocking findings

### IR-01 — HIGH: Partial term mean is exposed as Current Term GWA

**Location:** `includes/analytics/calculations.php:59–63`, `analyticsTrend()`; `assets/js/academic-analytics.js:56`.

**Evidence:** `analyticsAggregate()` correctly marks incomplete recorded terms provisional, but `analyticsTrend()` retains their numeric `gwa` in `current`. The Student KPI displays that number as “Current selected term GWA.” The database-free reproduction returned `partial_current_gwa: 4` with `partial_current_provisional: true` when one of two current subjects had no final grade. Existing tests suppress the change/direction but do not suppress a partially calculable current scalar.

**Impact:** Contradicts canonical MTR-01 and ADR-04, which require Pending/unavailable until the term is complete. A provisional note elsewhere does not make the scalar canonical Current Term GWA.

**Correction:** Return unavailable for the incomplete current scalar and show Pending/N/A with clear completeness context. An explicitly provisional recorded-grade series may remain separate. Add a regression with both a numeric current final and a missing current final.

**Blocks publication:** Yes.

### IR-02 — MEDIUM: Summer can become a regular term’s previous comparable term

**Location:** `includes/analytics/calculations.php:60–63`; `includes/analytics/service.php:63–67`.

**Evidence:** Both previous-period paths select the latest earlier complete calculable term without regular/summer comparability checks. The reproduction selected `2025-2026|3` as the previous term for `2026-2027|1`.

**Impact:** A regular-semester comparison may use summer, contrary to canonical MTR-02. The resulting delta can be mathematically correct but academically noncomparable.

**Correction:** Apply one shared comparability predicate to both selection paths. Preserve the authorized selected-semester constraint and visible period labels; exclude summer from regular comparisons and define summer-to-summer behavior explicitly. Add regular/summer, incomplete-prior and no-comparable-prior regressions.

**Blocks publication:** Yes.

### IR-03 — MEDIUM: Relative percentage has equal Student KPI prominence

**Location:** `assets/js/academic-analytics.js:56`; `assets/css/academic-analytics.css:11–13`.

**Evidence:** Relative change is rendered in the same metric list with the same typography and geometry as Point change.

**Impact:** Contradicts canonical MTR-04, FR-02 and ADR-01. Percentage changes on the bounded point scale receive the primary prominence those decisions reserve for point change.

**Correction:** Keep guarded percentage math, but render it as secondary contextual text or accessible help associated with point change.

**Blocks publication:** Yes.

### IR-04 — HIGH: MTR-20 implementation uses a different statistic, origin and unit

**Location:** `includes/analytics/interventions.php:21–46`, `analyticsSupportSummary()`; `includes/analytics/repository.php:61–63`; `assets/js/academic-analytics.js:42–45`.

**Evidence:** The implementation groups Faculty-authored case events and notices by parent case, measures from `academic_support_cases.created_at`, selects an earliest case event, and reports a median. Canonical MTR-20 specifies a mean anchored to `support_case_referrals.created_at`. A case event can also predate a later selected referral.

**Impact:** The result is not canonical Mean Time to First Action. Parent cases with multiple referrals contribute differently from distinct referral cases, and a filter can attribute an earlier unrelated same-case event to the selected referral.

**Correction:** Use the owner-authorized canonical mean correction: distinct referral IDs; verified referral creation to valid notice issuance; arithmetic mean over qualifying acted-upon referrals; no zero for unacted records; actual contributing sample; invalid/reversed evidence excluded and accounted for. Clearly state that currently evidenced first action is notice issuance only. Do not invent meeting events or treat generic case activity as referral action.

**Ruling on the proposed correction:** Defensible within this package. Canonical definitions explicitly name the referral creation column and verified notice column, and the owner authorizes option 1. The remaining “case” wording must be reconciled transparently as distinct referral-ID cases. No additional median preference or schema decision is required for that bounded correction.

**Blocks publication:** Yes.

### IR-05 — MEDIUM: Closure label does not identify the limited parent-case measure

**Location:** `includes/analytics/interventions.php:31–46`; `assets/js/academic-analytics.js:42–45`.

**Evidence:** The code computes the median of parent `closed_at − created_at`, but the UI label says only “Median time to closure.” It does not identify parent-case origin or explain that reopened/latest-referral lifecycle equivalence is unestablished.

**Impact:** Users can reasonably mistake this for canonical MTR-21 despite the reconciliation document correctly marking MTR-21 PLANNED / BLOCKED.

**Correction:** Label it explicitly as a limited parent-case closed-duration median, show its actual valid closed-parent-case sample, and state non-equivalence to canonical MTR-21. Preserve MTR-21 PLANNED / BLOCKED; add no schema fields.

**Blocks publication:** Yes, because the ambiguity affects metric evidence.

### IR-06 — MEDIUM: Fixture creation precedes its guaranteed cleanup scope

**Location:** `tests/fixtures/run_analytics_mvp.mjs:14–18,41–47`; original `tests/fixtures/analytics_mvp_fixture.php:108–109`.

**Evidence:** Setup creates the disposable database before writing the state file and before entering `try/finally`. A state-file write failure after successful setup therefore bypasses cleanup. The original cleanup executes DROP but does not assert absence afterward; the lifecycle record writes `removed:true` without that assertion.

**Impact:** Violates the owner’s same-run disposable-database lifecycle and cleanup-assertion requirement. A routine filesystem failure can leave the temporary schema behind.

**Correction:** Place every operation following successful creation under guaranteed cleanup. Retain sufficient in-memory ownership state to clean up if the state file cannot be written. Assert database absence after DROP. Preserve an exact target name and failure evidence if cleanup fails. Add a controlled post-setup/state-write failure reproduction.

**Blocks publication:** Yes.

### IR-07 — MEDIUM: Reconciled documentation still contains contradictory current instructions/status

**Location:** `docs/post-defense/12_ANALYTICS_METHODS.md`, “Changes and periods” and “Support evidence and times”; `docs/post-defense/work-packages/WP*.md`; `docs/post-defense/13_MVP_REVIEW_HANDOFF.md`; `docs/ROADMAP.md`; `docs/IMPLEMENTATION_HISTORY.md:10–18`; `docs/UI_UX_FINDINGS_REGISTER.md`, UX-012.

**Evidence:** The methods and work packages retain the partial-current and case-median contracts requiring correction. Work packages retain account-limit review status and mobile/screenshot evidence instructions. The original handoff tells a future agent that canonical integration has not occurred. Roadmap/history repeat old current verification claims. The analytics application milestone was added to implementation history before merge, contrary to this package’s explicit Step 8 boundary. UX-012 says Verified while the reconciled phase ledger correctly withholds VERIFIED pending independent review.

**Impact:** A future agent can resume superseded work or overstate canonical equivalence, review completion or fresh verification. The new reconciliation matrix is substantially more candid, but does not alone neutralize contradictory continuation instructions.

**Correction:** Reconcile current methods/work packages/status/roadmap against corrected source and actual fresh evidence. Preserve historical results as historical. Explicitly supersede the original handoff. Remove the premature application-history milestone; retain local evidence in the implementation ledger. Reconcile the UX status with the independent-review gate. Reference canonical metric IDs and preserve the exact owner desktop-only instruction.

**Blocks publication:** Yes.

### IR-08 — MEDIUM: Nonempty invalid timestamps count as issued notices

**Location:** `includes/analytics/interventions.php:26–29`; `includes/analytics/service.php:83–84`.

**Evidence:** Notice and acknowledgment counters use only `!empty()`. The pure reproduction counted `0000-00-00 00:00:00` as one issued notice. The same invalid issuance also influences recorded-review inference and the issued decision filter. Duration validation elsewhere does not correct these counters.

**Impact:** MTR-18’s valid issuance evidence can be overstated, and filter membership can contradict valid timing samples.

**Correction:** Use consistent timestamp validation for issuance-derived count/filter/review evidence. Validate acknowledgment timestamps before claiming recorded receipt events. Distinguish unusable evidence rather than fabricating timestamps. Add zero-date, malformed and reversed-time cases.

A valid recorded acknowledgment lacking a historical issuance timestamp is a separate limitation: the MVP may retain an honestly labeled raw acknowledgment count. Do not silently turn it into canonical MTR-19 rate or invent missing issuance evidence.

**Blocks publication:** Yes.

## Documented non-blocking limitations and accepted boundaries

### INFORMATIONAL — Limited canonical equivalence is appropriate

**Locations:** `docs/post-defense/14_METRIC_RECONCILIATION.md`; `includes/analytics/calculations.php`; `includes/analytics/service.php`.

Pooled unit-weighted recorded-grade means are not arithmetic means of completed Student GWAs. Subject curriculum year is not current Student year. Recorded-slot coverage cannot infer absent enrollments. Selected recorded-Student latest-snapshot coverage is not term active-population prediction coverage. A raw acknowledgment count is not canonical acknowledgment rate.

The reconciliation matrix identifies these differences. Preserve truthful UI formulas, scope and labels; keep the corresponding canonical equivalents incomplete where applicable. This review does **not** request new Student-mean aggregates, enrollment inference, rates, track filters or other deferred features.

**Blocks publication:** No, provided the final documentation/UI does not claim equivalence.

### INFORMATIONAL — Prediction coverage dimensions overlap

**Location:** `includes/analytics/service.php:72–74`; `assets/js/academic-analytics.js:73`.

Model/calculation counts classify source family; provisional counts classify completeness across source families. These are valid overlapping dimensions, not an exclusive partition. The current aggregate does not establish separate complete/provisional fallback counts.

Explain that overlap and retain the documented limited MTR-16 status. No additional cross-tab feature is required by this review.

**Blocks publication:** No with truthful disclosure.

### INFORMATIONAL — Security and read-only boundaries inspected

**Locations:** `api/academic_analytics.php`; `includes/analytics/repository.php`; `includes/analytics/service.php`; modified support drill-down queries.

Source inspection found:

- GET-only API, private/no-store response and actual active-account/role verification.
- Own-record Student scoping, current assigned Faculty grade scope and own Faculty referrals.
- Strict filter allowlists/types; parameterized record IDs and role constraints.
- SELECT-only analytics repository and an owned read-only transaction.
- Faculty drill-down retains `faculty_id = signed-in user`; foreign referral IDs produce no matching support record.
- Escaped PHP output and client text-node rendering.
- Fetch cancellation plus generation checks preventing stale responses from replacing newer results.
- Existing portal navigation retained through wrappers and minimal entry links.

No introduced authorization, SQL injection or identity/private-note leak was identified. These are source-review conclusions, not authenticated runtime certification.

**Blocks publication:** No independent defect identified.

### INFORMATIONAL — Academic, fairness and uncertainty boundaries inspected

The implementation reuses canonical grade/risk helpers and preserves zero/text/nonpositive-unit GWA exclusions. Grade and case/referral deduplication is explicit. Faculty attribution is restricted to verified current configured-term loads; ambiguous Administrator attribution is unavailable. Production current term is dynamic, while the fixture is explicitly fixed.

Unsupported prediction bounds remain null. Model and calculation provenance remain distinct from rule-derived advisory risk. No Faculty ranking, teaching-quality assertion, unilateral notice or punitive outcome was introduced. Small groups remain within existing authorized private roles; no anonymous-reidentification or suppression guarantee is claimed.

MTR-07 must remain BLOCKED. MTR-21 must remain PLANNED / BLOCKED.

**Blocks publication:** No additional defect identified beyond findings above.

### INFORMATIONAL — Desktop accessibility and verification limits

The presentation uses labeled native controls, status/error announcements, focus styling, table captions/headers, keyboard-focusable scrolling regions and chart data tables. Existing Faculty banner containment is structurally preserved by the minimal dashboard entry-link change.

The feature tests contain the authorized three desktop sizes and both themes, an actual login path, scope denial, navigation and a keyboard progression check. Controlled error/race cases are mocked browser evidence. Faculty layout checks are generated HTML/source evidence. Excluding mobile/screenshot cases is owner-authorized and is not a passing mobile result.

No desktop browser, contrast, screen-reader or full keyboard runtime claim is made by this independent source review. Fresh execution evidence remains required.

**Blocks publication:** Fresh required verification remains a gate.

## Required follow-up

Correct IR-01 through IR-08, retain RED-to-GREEN regression evidence for confirmed defects, and provide the exact correction state plus fresh desktop/disposable verification artifacts. I must inspect those changes and blocking-findings resolutions before issuing an approval verdict.

**Initial independent verdict: CHANGES REQUIRED.**
