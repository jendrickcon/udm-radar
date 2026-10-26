# Future UI test and acceptance matrix
Allowed status vocabulary: **NOT IMPLEMENTED**, **PLANNED**, **TEST EXISTS**, **PASSING**, **FAILING**, **BLOCKED**, **NOT APPLICABLE**.
All production rows below are NOT IMPLEMENTED, PLANNED or BLOCKED. Preparation syntax/reference checks have a separate actual result log; they do not promote a production row to PASSING.
Vincent owns unified UI adaptation; Member 2 owns technical validation/independent review. Actual author/reviewer identity and any repair authorship must be disclosed.

| ID | Requirement / exact evidence | Primary work owner | Independent verification | Current production status |
| --- | --- | --- | --- | --- |
| SID-01 | Expanded icon+label/active/footer controls retain authorized routes | Vincent | Member 2, actual portal | NOT IMPLEMENTED |
| SID-02 | Collapsed icon-only names, current state and focus; no clipped footer | Vincent | Member 2 keyboard/AX | NOT IMPLEMENTED |
| SID-03 | Content expands; plot/table contained1440×900/1024×768/1280×600 | Vincent | Member 2 geometry | NOT IMPLEMENTED |
| SID-04 | Local preference valid/invalid/denied storage, no academic/private data | Vincent | Member 2 controlled browser storage | NOT IMPLEMENTED |
| SID-05 | Desktop/drawer crossing preserves desired desktop state; no accidental mobile change | Vincent | Member 2 source/desktop edge; mobile testing excluded | PLANNED |
| SID-06 | Light/dark,CCS/classic,text scaling,reduced motion | Vincent | Member 2 theme/keyboard | NOT IMPLEMENTED |
| SID-07 | One initializer, settled event, coalesced resize, destroy/remount/pagehide | Vincent | Member 2 leak/resize checks | NOT IMPLEMENTED |
| SID-08 | Faculty attention banner closes before all later cards/roster | Vincent | Member 2 Faculty structural suite first | PLANNED |
| UI-01 | Admin/Faculty/Student presentation preserves distinct user purpose | Vincent | Member 2 workflow checks | NOT IMPLEMENTED |
| UI-02 | Filter simplification ≤4 primary; advanced/support contextual, active scope visible | Vincent | Member 2 state/role negatives | NOT IMPLEMENTED |
| UI-03 | Dashboard shared-service summaries match period/denominator | Vincent | Member 2 calculation/API parity | NOT IMPLEMENTED |
| UI-04 | Navigation/deep routes and PR63 status interaction reconciled | Vincent + owner-designated integration sequence | Member 2 immutable intake | PLANNED |
| CH-01 | Recorded own GWA supplied through authorized canonical helper contract | Vincent interface; Member 2 data verifier | Member 2 numerical/role negatives | PLANNED |
| CH-02 | Comparison legend exact unavailable phrase, no numeric series | Vincent | Member 2 DOM/model/response check | NOT IMPLEMENTED |
| CH-03 | Student-level cohort unit/scope/eligibility/exclusions approved | Member 2 | Owner approval after independent evidence | BLOCKED |
| CH-04 | Missing/incomplete,distinct Students,weighting rules approved | Member 2 | Owner approval | BLOCKED |
| CH-05 | Privacy-safe minimum and suppression/differencing review | Member 2 | Owner privacy/fairness approval | BLOCKED |
| CH-06 | Role-specific server aggregate visibility; no raw peers | Member 2 | Independent auth tests and owner approval | BLOCKED |
| CH-07 | Synthetic verified cohort data/negative cases and final owner release | Member 2 | Owner approval; Vincent later consumes | BLOCKED |
| CH-08 | Chronological terms,summer label,missing gaps,scale0–4 ascending | Vincent | Member 2 library-neutral data + rendered checks | NOT IMPLEMENTED |
| CH-09 | No interpolation/smoothing/fabricated zero/placeholder averages | Vincent | Member 2 null/numeric tests | NOT IMPLEMENTED |
| CH-10 | Own record/incomplete/empty/single-term/chart-library-missing states | Vincent | Member 2 APP+GEN separate | NOT IMPLEMENTED |
| CH-11 | Visible non-color legend,table same model,tooltip2decimals | Vincent | Member 2 table/plot parity | NOT IMPLEMENTED |
| CH-12 | Resize after sidebar/tab/show,one observer,cleanup | Vincent | Member 2 repeated navigation | NOT IMPLEMENTED |
| A11Y-01 | Native named buttons,aria-expanded/current,tab/disclosure semantics | Vincent | Member 2 keyboard/AX | NOT IMPLEMENTED |
| A11Y-02 | No hidden focus,clear busy/status,error recovery/focus return | Vincent | Member 2 keyboard/race/mock categories | NOT IMPLEMENTED |
| A11Y-03 | Text/table equivalent,heading/caption,row headers,essential limitations visible | Vincent | Member 2 non-hover reading | NOT IMPLEMENTED |
| GOV-01 | Correct verified base,one local clone and assigned long-lived branch,file allowlist | Both separately | Owner intake | PLANNED |
| GOV-02 | Vincent sole primary for UI CSS/JS/tokens/chart/layout; technical fixes coordinated | Both separately | Owner ownership check | PLANNED |
| GOV-03 | Tests categorized PHP/SQL/APP/GEN/MOCK/structure/accessibility/syntax/integrity | Member 2; Vincent evidence supplied | Independent reviewer | PLANNED |
| GOV-04 | Protected dump/model/locks/templates/config untouched without explicit scope | Both | Member 2 diff/hash | PLANNED |
| GOV-05 | New disposable DB only if separately authorized,provenance+cleanup | Member 2 | Owner + independent lifecycle evidence | BLOCKED pending authorization |
| GOV-06 | Exact immutable commit,clean status,complete handoff | Both | Owner | PLANNED |
| GOV-07 | Verified checkpoint pushes within approved member packages; one completed PR for review; merge/deploy/migrate/cleanup separate | Both | Owner | PLANNED |
| GOV-08 | Mobile/screenshot work excluded; don't select broad mixed suites | Both | Member 2 command/test inventory | NOT APPLICABLE under current desktop-only scope |

## Focused execution order for later production work
1. Intake exact clean immutable SHA and baseline; check PR #63/current main. Inspect tests/fixtures/includes before runtime.
2. If Faculty/shared sidebar/layout changed, run safe Faculty structural checks first, excluding screenshot and mixed-mobile branches. Never call a screenshot inside a selected test.
3. Run data/contract regressions, then authorized isolated integration and authenticated portal workflows, then generated/mocked/keyboard cases. Record real passed/failed/skipped counts, never expected permanent totals.
4. Run Goal Calculator/Admin Grades focus/Student GWA/Grades-history/PEF navigation regressions if shared shell is affected; do not run export or mutation workflows against existing databases.
5. PHP lint,JS syntax,diff whitespace,protected-file and all-change-layer integrity,disposable cleanup. No combined accuracy number.
6. Report findings; owner approves any minimal repairs; independent re-review on corrected immutable SHA before publication decision.

## Preparation-only tests
[Reference contract tests](reference/reference-contract.test.mjs) exercise data validation, blocked comparison, missing gaps, configuration and lifecycle stubs without a browser or DB. Those tests are preparation evidence only. CSS parsing/JS syntax/Markdown checks are recorded in the external readiness report, not as implementation acceptance.
