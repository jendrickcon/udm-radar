# UI test evidence — [WP-ID / candidate]

Template only. Leave unexecuted claims unconfirmed. Use Passed, Failed, Skipped, Not Run, or Unavailable; actual counts are discovered during execution, never copied from a prior report.

## Execution identity and safety preflight

- Date/time / executor / approved scope: [Actual values].
- Baseline / candidate SHA / branch / absolute repository clone path: [Full values].
- Dirty files or untracked evidence affecting execution: [Actual status].
- Runtime versions / installed Playwright pin and actual version / browser availability: [Evidence; no auto-install/download].
- Selected exact tests and why: [Focused before broader relevant checks].
- Inspected connections/includes/handlers/subprocesses/overrides/setup/writes/cleanup/locks/temp files: [Evidence].
- Database authority / target: [Default no access; separately authorized scratch mutation paths only, no live fallback].
- Authentication/request/audit side effects: [Observed and authorized; rollback is not isolation proof].
- Generated artifacts and ignored locations: [Actual paths].

## Results by category

| Category | Exact command / test selection | Environment, target and runtime | Exit code | Passed | Failed | Skipped | Result and evidence location |
|---|---|---|---|---|---|---|---|
| Markdown/path/terminology/format | [Command] | [Environment] | [Actual] | [Actual/N/A] | [Actual/N/A] | [Actual/N/A] | [Status] |
| PHP syntax / PHP regression checks | [Command; not PHPUnit] | [Target] | [Actual] | [Actual] | [Actual] | [Actual] | [Status] |
| JavaScript syntax/contract | [Command] | [Reference-only or app] | [Actual] | [Actual] | [Actual] | [Actual] | [Status] |
| Integration | [Command] | [Authorized database and effects] | [Actual] | [Actual] | [Actual] | [Actual] | [Status] |
| Live authenticated desktop browser | [Command] | [Role, page, widths, data] | [Actual] | [Actual] | [Actual] | [Actual] | [Status] |
| Generated static HTML browser | [Command] | [Fixture] | [Actual] | [Actual] | [Actual] | [Actual] | [Status] |
| Mocked browser | [Command] | [Mocks/fixture] | [Actual] | [Actual] | [Actual] | [Actual] | [Status] |
| Structural/source inspection | [Method] | [Files/SHA] | [Actual/N/A] | [N/A if no assertions] | [N/A] | [N/A] | [No end-to-end claim] |
| Accessibility/manual | [Method] | [Keyboard/focus/contrast/labels/table/reduced motion] | [Actual/N/A] | [Actual/N/A] | [Actual/N/A] | [Actual/N/A] | [Scoped observation] |

Do not total different categories into accuracy. A hash proves identity, not behavior. Unavailable credentials are an authentication limit, not a passing role check. Failing tests remain visible. Source inspection is separate from browser execution and data correctness.

## Requirement coverage

| Requirement / acceptance row | Exact test or inspection | Actual observed behavior | Implementation status | Limits / next action |
|---|---|---|---|---|
| [ID] | [Evidence] | [Observed] | [NOT IMPLEMENTED / PLANNED / TEST EXISTS / PASSING / FAILING / BLOCKED / NOT APPLICABLE] | [Specific] |

If Faculty dashboard or shared dashboard layout CSS changes, record `tests/browser/faculty-dashboard-layout-regression.spec.js` and warning-banner containment evidence. Future implementation remains `NOT IMPLEMENTED` / `PLANNED` in this readiness preparation; reference-only Node checks cannot establish production UI passing status.

## Data, ownership, and scope limits

Vincent owns unified presentation/sidebar/dashboard/chart/styles/tokens. Member 2 verifies calculations/privacy/auth/backend/API and tests, may propose failing UI checks, and cannot independently restyle Vincent's files. Minimal cross-owner fixes need owner approval, coordination, and explicit temporary transfer if needed.

Cohort gate remains `BLOCKED`: `Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.` No calculation/placeholder average or invented threshold. Member 2 must verify eleven contract items; final owner approval follows those eleven items. MTR-07 bounds remain blocked; canonical MTR-21 planned/blocked separate from limited parent-case median; MTR-19 count is not canonical rate.

- Failed/skipped/unrun checks and reasons: [Actual].
- Protected file / dependency / migration / database effects: [Evidence, not prechecked claims].
- Mobile tests and screenshots: [Not Run under retained owner directive unless later revised].
- Remaining acceptance blockers and exact next action: [Actual].

Owner instruction retained verbatim:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is a historical quote, not a current measurement. No production browser execution is authorized by this preparation template.

## Member branch and checkpoint contract

Use one existing local clone per human member and one assigned long-lived branch: Vincent `feat/member1-ui-ux`; Member 2 `feat/member2-analytics-verification`. Before editing, report member, assigned/local branches, clone path, baseline/HEAD/main SHAs, status, scope, protected files, expected files, shared owners, tests and stop conditions. Save current work through the approved process, fetch, fast-forward clean main, return to assigned branch, integrate main by the approved method, resolve conflicts and confirm clean status.

Optional AI child branches stay within the same clone and start from the member's own assigned HEAD, never the other member's branch. Compatible examples are `feat/member1-ui-ux--sidebar` and `feat/member2-analytics-verification--cohort-contract`. Slash descendants under an existing assigned branch cause a Git ref file/directory conflict; double-hyphen refs avoid it. No additional worktree unless explicitly owner-authorized for an exception.

Under the approved future package, stage explicit files, review the full staged diff, make small meaningful verified commits and push checkpoints to the assigned branch. At completion, prepare one complete PR after owner review with the exact reviewed head and categorized evidence. Keep auto-merge off; merge needs owner authorization. After approved merge, both clones update clean main fast-forward-only and verify the served repository uses updated main; use current main for the next approved package. GitHub main is authoritative, not copied source folders or ZIP files. No destructive recovery, deployment, migration, branch reset/deletion or cleanup follows automatically.

Current Sol preparation: no existing database access, production changes, or Antigravity/PR #63 edits. Future database access requires separate owner authority; generic scratch advice grants none. Commit corrected readiness files only after owner review; until then state `Review commit pending owner review`, with no preparation push/PR. Future checkpoint authority does not override that current preparation gate.
