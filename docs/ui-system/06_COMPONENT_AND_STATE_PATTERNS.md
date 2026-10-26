# Component and state patterns
Documentation: READY FOR OWNER REVIEW. Member implementation: NOT STARTED. Vincent owns Administrator/Faculty components and self-verification; Member 2 owns Student UI/calculation/privacy and self-verification. Shared components have one assigned integration custodian.
Use [tokens](02_DESIGN_TOKENS_AND_THEME_CONTRACT.md), [accessibility](07_ACCESSIBILITY_AND_KEYBOARD_CONTRACT.md) and the canonical shared UI. The table is a contract, not permission to duplicate standard components.

| Component | Purpose; required content | Optional content | Prohibited content | Keyboard behavior | Dark theme | Minimum future evidence |
| --- | --- | --- | --- | --- | --- | --- |
| KPI card | One decision-relevant value, clear metric/unit/scope, availability | Eligible n, coverage, authorized detail link | Invented zero, unlabeled arithmetic/pooled mean, decorative KPI wall | Static by default; action card uses named link/button | Semantic surface/text/status | Complete, empty, partial; same-scope formula parity |
| Chart card | Title, period/scope, legend, plot status, accessible table/summary | Method disclosure, authorized filters | Chart-only facts, hidden limitations, card nesting wall | Plot information available without pointer | Current/comparison distinct by label/dash | Both themes/desktop sizes and table parity |
| Compact filter bar | No more than four primary scope fields, labels and active scope | Explicit Apply if needed, Reset | Eight permanent dropdowns; silent value changes | Native controls; stable focus after updates | Readable selected/disabled states | Parent/child combinations, keyboard, request race |
| Advanced disclosure | Named trigger with expanded state and relevant extra fields | Active filter count | Hidden active constraints with no summary | Enter/Space; collapse returns focus if inside | Focus/expanded state clear | Open/closed, active collapsed filters, Reset |
| Context tabs | Named sections, selected state and associated panel | Deep link/history if accepted | Hidden focusable inactive panel, duplicate IDs | Native controls or correct ARIA tab model | Selected beyond color | Arrow/Home/End if ARIA tabs; refresh/back |
| Empty state | Scope-aware no-record message, retained headers and recovery | Reset link | Fabricated plot or unrelated default records | Recovery action reachable | Same contrast as body | No rows, zero matches, no loads |
| Unavailable metric | Explicit unavailable/Pending and reason | Next evidence needed | Numeric 0.00 substituted for missing | Explanation available without hover | Not excessively muted | Null, incomplete, unsupported contract |
| Loading state | Section-local busy/status and selected scope | Brief progress message | Old results shown under new filters | No unexpected focus move | Legible state | Slow transport, abort, latest response wins |
| Partial-data state | Completeness/coverage plus exact metric meaning | Separate explicitly provisional recorded-point history | Partial mean called complete term GWA | Readable textual status | Pattern/text plus color | Mixed numeric/INC/missing, units denominator |
| Error state | Safe message and retry/recovery | Technical correlation outside UI | Stack trace, identity, secrets, stale success | Alert and named retry; no trap | Contrast preserved | HTTP error, malformed response, retry success |
| Legend | Visible series names, stroke/shape, blocked comparison explanation | Keyboard visibility toggle only for available series | Color-only key or clickable blocked cohort | Static disabled description or named real toggle | Distinct labels and dash | Color-independent reading, table agreement |
| Chart-to-table fallback | Period, own value, state and comparison availability from identical validated model | Coverage/source metadata | Different rounding/population or hidden table behind chart failure | Real caption/thead/row headers | Tokenized grid | Values/precision/gaps equal; no-script fallback planned |
| Dashboard summary strip | Compact scope-aligned academic/support cues and one deeper route | Coverage n and next review action | Full reporting form, ambiguous sums of record types | Named action; sensible tab order | Fits shared stat vocabulary | Admin/Faculty/Student authorized context |
| Help/explanation | Essential definition visibly concise; longer mechanics disclosure | Shared academic guide | Crucial risk/privacy limit in hover only | Enter/Space/Escape, focus return | Visible text/focus | Keyboard and enlarged text |

## Example state vocabulary
“Your recorded GWA” means recorded own-term evidence, not estimated GWA. Incomplete current term may be Pending; no numeric value is invented. A recorded-point partial historical series, if separately authorized, needs a different explicit label and semantics.
“Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.” is the fixed disabled legend/column explanation in this package.
A zero **count** is legitimate; null mean/range/duration is unavailable. Receipt counts are not acknowledgment rates. Support durations use mean versus limited median labels accurately.

## Revised composition and controls

Visualizations and compact details precede exact accessible tables below; long formula/policy text uses accessible disclosure while critical warnings/n/coverage/limits stay visible. About two-thirds plot/one-third details is optional on wide desktop.

Comparison controls default to two groups, allow a third and no fourth; overlay/separate cards preserve scope. Mixed context does not authorize direct deltas. Growth is core for comparable evidence, with exact start/end terms, point/supporting relative/coverage/n change; otherwise “Change unavailable” plus reason. One-period scalar bars/dots differ from ≥2-valid-point ordered history lines. Real academic periods replace ordinal-only axes.

Support flow cards show referral → notice → receipt and case/referral states. Label MTR-20 “Average time before the first notice” with duration/n; bounded MTR-19 counts and limited parent median remain distinct from blocked MTR-21. Support counts do not measure effectiveness.

Use one primary page scroll owner, theme every card/control/table/chart/legend/axis/tooltip/badge/state/details surface, and keep no page horizontal overflow at supported desktop sizes.
