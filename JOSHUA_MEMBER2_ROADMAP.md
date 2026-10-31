# Member 2 roadmap: assignment, accomplishments and remaining verification

Updated: 29 October 2026, Asia/Manila. Owner: Joshua (Member 2). Branch: `feat/member2-student-portal`.

**Current position:** the approved Performance Trend work and separately requested Student fixes are implemented. Database setup passes. The latest combined automated regression run passes: 550 PHP checks, 16 calculator JavaScript tests, 25 authenticated browser tests (zero skips/failures/flaky), and 14 syntax checks. Owner manual acceptance and publication of recent local changes remain incomplete. PDF testing is set aside and blocked by missing private assets. The separate Academic Review redesign and proposed Dashboard enhancements are not completed features of this package. See [the current regression report](JOSHUA_STUDENT_REGRESSION_REPORT.md).

This is a Member 2 status and execution checklist, not a new authorization to implement proposals, reset passwords, mutate databases or publish changes. The canonical project roadmap remains [docs/ROADMAP.md](docs/ROADMAP.md), with historical milestones in [docs/IMPLEMENTATION_HISTORY.md](docs/IMPLEMENTATION_HISTORY.md). Those ledgers describe accepted project milestones; this document adds the current local Member 2 status without claiming it is merged.

## 1. Your assigned work

The supplied panel revision PDF has now been reviewed separately against Student scope. See [Student revision compliance](JOSHUA_STUDENT_REVISION_COMPLIANCE.md): partial overall match; numerical forecast bounds, complete cross-role reconciliation and formal deliverable evidence remain open. This audit does not authorize new implementation or relax Student privacy.

The supplied `JOSHUA_STUDENT_PORTAL_MASTER_WORK_PACKAGE_V2.md` assigns Joshua responsibility for approved Student portal revisions, calculations/privacy contracts, implementation, self-verification, fixes, documentation and owner handoff. `MEMBER_2_MASTER_INSTRUCTION.md` narrows the active feature package to `STUDENT-PERFORMANCE-TREND-IN-CHART-UX-WP1`; its addenda distinguish later scope from the active chart package.

| Assignment | What Joshua is responsible for | Current status |
|---|---|---|
| Student Performance Trend | Completed history, current progress and estimates; period labels; in-chart details; exact evidence; truthful gaps and comparisons | Implemented; latest automated regression passed; owner acceptance pending |
| Student calculations | Reuse accepted GWA/grade helpers; previous comparable term; point change and supporting relative percentage; incomplete and summer handling | Implemented in the trend model; current automated checks passed; owner dataset/manual acceptance pending |
| Student privacy and authorization | Own-record scope, reject another Student identifier, deny cross-role access and unsupported filters | Latest authenticated negative tests passed |
| Preserve Student portal functions | Home, Dashboard, Grades, Goal Calculator, Enrollment Planning, Feedback/Support, navigation and themes | Required regression scope; several separately authorized fixes completed |
| Desktop accessibility | Three supported desktop sizes, light/dark, keyboard, focus, readable tooltips and equivalent table | Targeted checks passed; final combined matrix and manual checks remain |
| Verification and documentation | Test evidence, exact files, calculation/privacy limits, progress reports and review/handoff | Reports exist; this roadmap consolidates remaining gates |
| Owner handoff | Specific candidate, known limits, owner retest, branch publication and integration coordination | Branch exists remotely; recent local changes are not published |

Faculty and Administrator feature implementation belongs to the teammate, not Joshua. Cross-portal regression and shared-file coordination are still part of safe integration. Existing MVP features are preserved features, not all new Member 2 deliverables.

## 2. What has been accomplished

| Area | Delivered behavior | Evidence and remaining limit |
|---|---|---|
| Setup and database | Canonical 18-table schema installed with the owner-approved compatible large dataset; original mock identities and credential hashes retained | [Current setup report](OWNER_DATASET_SETUP_REPORT.md), [verification](OWNER_DATASET_VERIFICATION.json), [setup gate](SETUP_REPORT.md). Setup PASS is not full portal UAT |
| Trend evidence model | Chronological completed terms, missing-term gaps, separate current grading observations and estimates; exact school-year/semester context | Latest model and authenticated browser checks passed; no new grading formula or model training |
| Change reporting | Eligible completed-to-completed GWA point change plus supporting relative percentage | No growth claim from a current partial observation or estimate; noncomparable periods remain unavailable |
| Chart interaction | Key point values, point details, keyboard/table linkage and fallback when chart library is unavailable | Latest combined authenticated suite passed; owner manual acceptance remains |
| Chart presentation | Clearer Current term progress / Estimated GWA labels; less repeated text; spacing below labels | Latest focused run recorded 34 checks across desktop sizes/themes; not a full application test total |
| Visual connector | Dashed guide between evidence stages using existing point coordinates | Explicitly a visual aid; does not become a recorded growth series or forecast path |
| Honors references | Optional guides; combined Dean's Lister / Cum Laude 3.25 reference, Magna 3.50 and Summa 3.75 | Guide references do not determine eligibility; underlying policy helpers unchanged |
| Evidence disclosures | Exact evidence table hidden by default with native open/close control; additional chart explanation collapsed | Keyboard and focus tested in targeted runs; Pending current-term row retained even when its empty axis slot is omitted |
| Dashboard consistency fix | Displayed advisory risk follows the displayed estimate through the existing helper; latest prediction tie selection is deterministic | Earlier fixture reproduced 2.15/stale LOW and verified MODERATE after the fix; final current-dataset cross-page retest remains |
| Goal Calculator | Target-derived Pre-Final locking, reset behavior and clearing temporary scenarios on close | Earlier JS/browser checks cover behavior; verify latest delivered assets and restored-dataset state |
| Grades & History | Risk-tooltip clipping fix | Earlier browser coverage; manual regression still required on the final candidate |
| Enrollment Planning selection | Ordinary-click, labeled subject checkboxes; keyboard toggling; live selection count and 12-subject limit | 16 focused checks recorded; GET/form selection only, not PDF generation |
| Enrollment Planning page | Public curriculum catalogue readiness, current records, Pending state and disclosures | Earlier isolated suite covered planning GET and negative identifiers; private workbook flow remains blocked |
| Review | Codex completed the replacement review checklists requested instead of Sol/Antigravity | Implementer verification, not independent external approval or owner acceptance |

### Current database facts

Normal URL: `http://localhost/udm-radar/login.php`. Normal database: `udm_radar`.

- 291 Student profiles, 4 Faculty accounts, 1 Administrator account.
- 12,552 grades, 2,358 valid Student predictions, 58 subjects.
- 18 canonical application tables; 32 declared foreign-key relationships checked with zero orphans.
- All ten required named CHECK constraints verified.
- Owner-approved exclusion of 182 blank-source Student predictions; these remain in `udm_radar_owner_dataset_review` and the original source file.
- One unreferenced Administrator-owned prediction and two rejected corrections with missing Student targets were also excluded from the compatible import.
- This supplied file had one actual legacy table, `_backup_student_profiles_course_wp6`; it was excluded. The requested second legacy table was absent.
- Both prior local datasets were backed up; recovery of the original `udm_radar` backup was rehearsed with matching schema/data fingerprints.
- No Migration 011 was run. Seven Student pages passed read-only rendering checks after replacement.

The separate `udm_radar_large_seed_demo` uses the earlier sanitized `24-00-NNN` account namespace. It is not the currently selected owner dataset. Its known demo passwords must not be assumed to authenticate the restored original accounts.

## 3. What the test evidence actually proves

| Evidence | Recorded result | How to use it now |
|---|---|---|
| Six database-free PHP regression scripts | 521 assertions passed in the recorded earlier runs | Historical GWA, final-grade, history, calculator, prediction explanation and Dashboard coverage |
| Trend model suite | 27 checks passed in later recorded runs; includes source-structure checks | Model/source checks, not authenticated browser proof |
| Calculator JavaScript suite | 16 tests passed in the recorded review | Calculator logic coverage, not every live UI state |
| Full authenticated isolated browser suite | 25 passed, 0 skipped/failures at the earlier candidate | Covers its exact fixture/candidate; not blanket certification of subsequent edits or current owner database |
| Subsequent UI checks | Separate focused checkbox, disclosure, connector, honors and refinement runs | Useful feature evidence; overlapping checks must not be summed as unique coverage |
| Latest owner-dataset setup | Schema, every-field staging round-trip, final fingerprint parity, relationships and backup recovery passed | Database compatibility and preservation evidence |
| Post-import Student page checks | Seven read-only renders passed with injected test authentication | Does not prove password login, real browser interaction, write workflows or PDF output |

The initial roadmap was documentation-only. A subsequent owner-authorized combined regression run is now complete; its current results and remaining limits are in [JOSHUA_STUDENT_REGRESSION_REPORT.md](JOSHUA_STUDENT_REGRESSION_REPORT.md). It supersedes the pending combined-run status and the earlier 521-PHP/27-model totals in historical entries. Older seed-setup blockers are superseded by the final owner-dataset setup PASS. Earlier review request files remain historical.

## 4. Remaining testing and verification

| Priority | Required check | Status now | Passing evidence needed |
|---|---|---|---|
| 1 | Original-account login | Deliberately pending: owner chose to preserve its password and declined reset | A confirmed existing credential is required for manual login proof; do not reset the account |
| 2 | Current code candidate | Latest UI edits remain uncommitted; no final immutable candidate yet | Exact file inventory/hashes, current HEAD plus dirty diff, PHP/JS syntax, whitespace and protected-file checks; later record approved final commit SHA |
| 3 | Complete isolated regression suite | PASS: latest combined run, 25 browser tests with no skips/failures/flaky; 550 PHP and 16 JS checks passed | See current regression report; unchanged fixture data and cleanup verified |
| 4 | Own-record security | PASS in latest authenticated fixture run | Logged-out denial, foreign Student ID/filter rejection, cross-role denial, no other Student grade/support leakage, logout invalidation |
| 5 | Calculation and source parity | Prior checks passed; restored dataset retest pending | Hand-check eligible term GWA, latest/previous comparison, delta/relative direction, missing finals, text outcomes, numeric zero, summer/nonadjacent periods and estimate metadata against accepted helpers |
| 6 | Trend edge cases | Latest combined matrix passed for covered fixture cases | 0/1/2+ completed terms, internal missing term, current-only/history-only, missing Midterm, missing/stale/provisional estimate, equal values and long/dense labels |
| 7 | Chart/table and honors | Latest automated checks passed; owner manual acceptance pending | Same source payload/precision, correct point-to-table focus including omitted Pending axis slot, no false continuity, connector explanation, optional guide toggle and reference-only meaning |
| 8 | Calculator and Grades | Latest authenticated regression passed; owner manual retest pending | Target locks calculated Pre-Final; Reset clears target/result; Close/reopen clears scenarios; impossible/partial inputs handled; original recorded grades unchanged; Grades tooltip fully visible |
| 9 | Planning controls and write workflows | Selection/GET tested; submissions not certified | Native click/Space, 12-limit/deselect, preserved selection, correct submitted subject IDs, stale-preview invalidation, foreign-record denial; submissions on an approved disposable target |
| 10 | Preview/PDF workflow | BLOCKED by approved workbook/private storage prerequisites | Verified private template and storage, then actual preview, two-page PEF/CC PDF, download/print layout, normal/provisional/review-required/rejected states, protected URLs and error paths |
| 11 | Desktop/accessibility | Focused automated passes; final matrix/manual audit pending | 1440×900, 1280×600, 1024×768 in light/dark; keyboard, visible focus, readable labels/contrast, reduced motion, zoom, no clipping/page-wide horizontal overflow; actual screen-reader check where available |
| 12 | Portal regressions and integration | Prior Student tests; final handoff validation pending | Home, Dashboard, Academic Review, Grades, Trend, Planning, Feedback/Support, navigation/theme persistence; targeted Admin/Faculty smoke if shared dependencies are affected |
| 13 | Owner acceptance and publication | Local handoff prepared; user explicitly chose no commit/push | Owner retest and recorded acceptance; any future publication needs separate authorization; no PR/merge implied |

Testing must not silently rebuild or mutate the installed owner database. Use bounded read-only checks for the installed dataset. Automated mutation, reset, workflow and export scenarios belong in an explicitly approved, isolated disposable target after reviewing every connection and cleanup path. Keep its excluded/quarantined source records separate from the installed dataset.

### PDF prerequisite checklist

- Obtain the approved private v0.2-r6 workbook, not a regenerated substitute.
- Verify SHA-256 `5941b7b36c5bbee585f32162397de2cd18e0279171cdd6470578e389b18e43ec`.
- Configure the approved private template and temporary storage paths outside web root and verify permissions.
- Check the required local rendering dependencies and actual template/catalogue compatibility.
- Test normal, incomplete/provisional, safely representable review-required and unsafe/rejected output.
- Verify two-page PEF then Curriculum Checklist order, field fit, original required readability floor, download, expiry/access control and native print output.

An Enrollment Planning HTTP 200 response or a selected subject list is not a PDF pass. Repository-wide older export checks are historical background, not evidence that this machine has the private asset or passes the final export gate.

## 5. Work requiring a separate scope decision

| Item | What remains | Boundary |
|---|---|---|
| Academic Review visual-first redesign — Addendum A | Four concise summaries; main completed-history chart; compact grading-stage coverage; one justified insight; accessible About these results; its own test/acceptance matrix | Existing shared analytics MVP page is preserved. No separate redesign implementation is evidenced; explicit expanded scope and shared-service ownership needed |
| Dashboard enhancements — Addendum B | Proposed progress sparkline, compact stage status, provenance/focus improvements | Proposed future phase, not part of the active chart package. Narrow risk/tie correction already completed does not authorize a broad redesign |
| Student cohort comparison | Calculation, eligibility, denominators, minimum privacy-safe population, fairness/visibility and owner-approved contract | Keep unavailable; do not substitute Admin pooled recorded grades |
| Prediction interval/range | Approved method and evidence under MTR-07 | Keep unavailable; do not create bounds from arbitrary error numbers |
| Faculty/Admin features, models and schema changes | Teammate or separately assigned custodian/package | No automatic scope expansion, Migration 011, retraining or global restyling |

These are not all unfinished approved coding tasks. Some are deliberately blocked or proposed work. Their absence does not justify implementing them as incidental fixes.

## 6. Recommended execution order

1. **Establish a usable original Student login.** Confirm a known credential or obtain specific reset authorization. Do not reset other accounts. Retest that the displayed Student matches the selected original identifier.
2. **Automated verification completed.** Latest isolated tests passed after correcting keyboard test navigation. Review the recorded results and preserve the candidate; rerun affected checks if code changes.
3. **Run owner desktop retest on the restored dataset.** Use known regular, irregular, sparse/no-current-grade and incomplete-record cases. Record account/role, URL, code fingerprint, expected versus observed result; do not expose hashes or private source rows in shared evidence.
4. **Resolve the PDF prerequisite separately.** After the approved workbook/storage are available, perform the actual guarded preview/PDF workflow tests. Otherwise retain an explicit blocked PDF gate.
5. **Complete accessibility and integration review.** Finish manual contrast/screen-reader checks as applicable and coordinate shared-file/API expectations with the Faculty/Admin teammate. A branch existing on GitHub does not prove a conflict-free merge.
6. **Review optional expansion.** If the owner wants Addendum A, approve it as a distinct Academic Review package; keep Addendum B and blocked metrics outside the current implementation.
7. **Prepare the final owner handoff.** Record tests by category and exact candidate SHA after authorized commit; include limitations, current setup/import instructions and unchanged protected systems. Obtain explicit permission for publication actions.

## 7. Branch and handoff status

- Local HEAD at roadmap preparation: `94550c75268d103def200723b78e9d39b64edcba`.
- Approved original setup baseline: `60a6f7614b594cd8759a3c9211e060c3b8d7ab62`.
- `feat/member2-student-portal` was verified on origin at the same HEAD in the latest branch check; this roadmap did not fetch, push or query newer remote integration state.
- Recent local planning controls, trend refinements, updated tests, seed/setup evidence and this roadmap are not all contained in that published commit.
- The current worktree is dirty. Preserve `.a` and unrelated existing work. Do not describe it as a clean final candidate or publish private backup/data exports accidentally.
- Current owner-compatible dataset differs from the separately prepared sanitized public 002 seed. Handoff instructions must state which dataset the owner is testing and its provenance/exclusions; Git does not transfer an installed database.
- No PR, merge, deployment, formal UAT or institutional acceptance is implied. Codex review replaced the requested Sol/Antigravity workflow at the user's direction; no external reviewer sign-off is claimed.

## 8. Completion criteria

Member 2's active package is ready for final owner review when the latest agreed candidate has passing applicable regression/authorization/desktop evidence, accepted calculation and privacy semantics, clear recorded-versus-current-versus-estimate behavior, documented unresolved external gates, a traceable handoff, and an owner retest decision. Claim full PDF completion only after the actual document gate passes. Claim Academic Review redesign completion only if that separate scope is authorized, implemented and verified.

Current outcome: **implementation and latest combined automated regression delivered; original-password login, owner manual verification/acceptance, PDF prerequisites and publication gates remain. Not every proposed future Student feature is complete.**

## Evidence references

- [Progress ledger](JOSHUA_PROGRESS_UPDATE.md)
- [Codex replacement review](JOSHUA_CODEX_REVIEW.md)
- [Self-review](JOSHUA_SELF_REVIEW.md)
- [Owner dataset setup](OWNER_DATASET_SETUP_REPORT.md)
- [Owner dataset verification](OWNER_DATASET_VERIFICATION.json)
- Historical baseline test summary: generated local evidence was not committed; see [the portable regression report](JOSHUA_STUDENT_REGRESSION_REPORT.md).
- [Owner testing handoff](OWNER_TESTING_HANDOFF.md)
- [Superseded external review request](JOSHUA_ANTIGRAVITY_REVIEW_REQUEST.md)

## Latest verification checkpoint — 29 October 2026

The complete 25-test authenticated Student suite has now also passed after the latest Academic Review spacing change: no failures, skips or flaky results; unchanged fixture data and verified disposable cleanup. Source changes were not needed. This supersedes the pending full-suite note for that refinement. Existing PHP/JS unit totals belong to their separately recorded earlier run, not a new execution in this browser-only checkpoint. Original-password/manual owner checks, PDF deferral and publication decisions remain open. No commit or push performed.
