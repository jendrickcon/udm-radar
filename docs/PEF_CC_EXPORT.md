# PEF and Curriculum Checklist planning PDFs

PEF-CC-WP1 provides an authenticated Student planning preview and a **PDF-only public download**. The output is two pages, in order: PEF, then Curriculum Checklist (CC). XLSX is a private intermediate form engine; no public Excel/XLSX download or fallback is available. The copy is advisory and student-prepared, subject to the designated academic evaluator's review. It does not update academic records or constitute official enrolment approval.

Every output retains `Prototype performance on synthetic academic records`. Supported contexts use the approved BSIT Software Development 2022–2023 mapping. Unsupported legacy Cyber Security/Data Science tracks remain blocked; they are never converted automatically. Broader offerings, prerequisites, load eligibility, graduation forecasts and WP2/WP3 planning policy remain outside this package.

## Accepted four-state workflow

The owner manually accepted the workflow on **20 October 2026**, including the safe review-required case. Automated coverage of the final correction was deferred until the separately authorized final gate. Manual acceptance does not establish formal UAT, institutional approval, genuine-record validation or production renderer validation.

| State | Preview and download | Selected-term GWA | Notice |
| --- | --- | --- | --- |
| A: Normal | `Generate planning preview`; `Download planning PDF` | Numeric in browser, signed model and PEF when complete and calculable | No provisional notice |
| B: Provisional | `Generate provisional planning preview`; `Download provisional planning PDF` | Browser Pending; model null; PEF blank | Red PROVISIONAL PLANNING COPY |
| C: Safe review required | `Generate review planning preview`; `Download review planning PDF` | Numeric only for complete, calculable selected outcomes; otherwise Pending/blank | Red PROVISIONAL PLANNING COPY |
| D: Blocked | Preview disabled; no usable signed download; direct export rejected | No invented result | Blocking explanation |

Unsafe errors take priority over review concerns; explicit safe review concerns take priority over provisional status. Missing grades or evaluator review alone do not make export impossible. C includes only verified values: unresolved section and/or proposed subjects stay blank. A verified nominal list may remain when only the section is unavailable. Target/evaluation chronology and server-derived subject/section authorization are still enforced. No arbitrary input becomes authorized because a copy requires review.

Incomplete selected outcomes, including unresolved INC, never produce a partial, cached, cumulative, predicted or fallback Term GWA. The shared `computeWeightedGWA()` contract and its current zero exclusion are unchanged. A zero final grade fails a subject; passing, advisory risk, official standing, curriculum pacing and enrolment eligibility remain separate concepts. Textual outcomes follow `config/constants.php`.

Current GS/WS remains blank when not verifiable. The separate verified historical-standing matrix is preserved; missing terms do not become WS. CC applicability remains `Reference / Requires Review`. CC Remarks remain P/F/blank and Status remains Taken/Need to Enroll/blank, according to the existing canonical mapping. Incomplete or textual values are not converted to zero.

## Private r6 template and form geometry

The active template is **v0.2-r6**, deployed separately from Git at:

`C:\ProgramData\UDM-RADAR\private\templates\pef-cc\v0.2-r6\template.xlsx`

SHA-256:

`5941b7b36c5bbee585f32162397de2cd18e0279171cdd6470578e389b18e43ec`

The template, original owner workbooks and generated XLSX/PDF files must remain outside every web root and Git. Verify the exact hash and canonical path, including junction ancestors. Do not edit/overwrite an approved private template to bypass its hash contract. The public page requires r6; an older version is not an operational rollback for the PDF endpoint.

Normal PEF prints A1:G71; Provisional and Review copies print A1:G75 to include the existing red title at A73 and notice at A74:G75. CC prints A1:O70 with the clean evaluator/Dean ending. There are 13 recorded and 12 proposed slots; capacity and print-bound failures reject without truncation. Original section row 51 and label row 52 remain separate. Program Chairperson MS. CHRISTINA P. ATAL and Dean MR. FRANCIS ARLANDO L. ATIENZA are preserved; their presence is not a staff signature or approval. Signature/date fields remain blank.

## Authoritative data and request safety

The server reloads the authenticated Student's context and rebuilds the scalar view model before export. A per-session HMAC binds identity, session, planning inputs, source evidence, policy version and template contract to the preview. Ten-minute expiry, stale records, changed sections/subjects, unknown authoritative fields, cross-session replay, CSRF failure and template mismatch still reject. Browser form fields do not establish ownership or academic truth. No draft or generated planning record is persisted to the application database.

XLSX values retain explicit text/number/blank types; source text cannot introduce formulas, external relationships or destinations. The private intermediate retains cell-level verification. Public HTTP/browser tests validate PDF response MIME, filename/header, two-page objects and absence of XLSX; native layout checks independently inspect actual rendered pages. Mocked transport responses establish client loading/error behavior only.

## Renderer and deployment boundary

The current **local Windows prototype renderer uses Microsoft Excel COM through Windows PowerShell**. PHP must have its installed PhpSpreadsheet dependency and subprocess support; the serving identity needs a usable Excel installation, fonts, Folio-capable printer environment, private template read permissions and isolated temp read/write/delete permissions. Do not assume an interactive desktop success establishes Apache service-account behavior.

The converter serializes jobs with `.pefcc_converter.lock`; do not delete that coordination file. It waits up to 20 seconds for the lock. The workbook generation budget does not prove a hard timeout around the blocking COM conversion. Hung-process recovery, throughput, ACLs and cleanup scheduling remain deployment responsibilities. The runtime PDF validator checks size/header and page tokens; it is not a full semantic PDF/layout validator.

**Azure/IIS requires separate renderer/service-account validation.** No production Azure/IIS renderer validation is claimed. This COM renderer cannot be presumed to run on Linux/Vercel. No replacement renderer or hosting deployment is implemented in this follow-up.

Fresh owned intermediate files are cleaned on success/failure/shutdown with bounded filename/path checks. Keep templates, recovery evidence, dependency folders and the converter lock. Test evidence goes to ignored browser output directories or a fresh private recovery folder, never source control.

## Guarded validation and prerequisites

All database-backed fixtures, handlers, subprocesses, owned grade mutations and cleanup must target **udm_radar_scratch**. Never use the live `udm_radar` as a fallback. Both PHP integration scripts mutate owned scratch fixtures; a read-only transaction inside a test does not make its setup read-only. Fixture identities are unique, validated and cleaned with their manifest. Private state files must be fresh and outside all web roots.

The scratch schema must support textual final grades. The observed DECIMAL(4,2) column cannot faithfully store INC and other recognized statuses. The fixture aborts before DML when this prerequisite is absent. The existing `005_standardize_final_grade_storage.sql` describes the prerequisite; running it is a separate owner decision, **not authorized automatically by test execution**. Do not coerce textual statuses or remove their assertions to obtain a pass.

Use installed Playwright **1.63.0**, matching package/lock/installed metadata, with the existing Edge channel and one worker. Do not auto-download another runner. Classify evidence separately as generated production HTML, mocked browser transport, authenticated scratch application, private workbook checks and native Excel output.

The final gate includes the six `tests/unit/pef_cc_*_test.php` PHP regression scripts, `tests/unit/display_preferences_contract_test.js`, focused planning/eligibility/print/loading/accessibility browser specs, faculty layout regression, guarded read/download integration and authenticated browser downloads. The native r6 matrix retains the operational stress cases and adds Normal, Provisional, complete Review and incomplete Review cases. It verifies combined two-page order, Folio dimensions, GWA/blank rules, red notice, standing, CC fields, signatories, clean ending and clipping. **The minimum operational font floor remains 8pt.** A missing printer/dependency or actual font/layout failure blocks that gate.

### Fresh final-gate status (20 October 2026)

Final gate **blocked**. Fresh PHP regression checks: **915 assertions passed, 0 failed** (six scripts). JavaScript display contract: **19 passed, 0 failed**. Pinned Playwright: **53 passed, 0 failed, 59 skipped**. The passed cases are generated production HTML or mocked transport, including 8 faculty-layout and 6 sidebar checks; 41 planning, 8 authenticated download and 10 authenticated accessibility cases remain skipped. No authenticated final State C result is claimed.

Guarded setup stopped before fixture DML on scratch `grades.final_grade = DECIMAL(4,2)`. Read-integration prerequisite check: **1 failed assertion, exit 1**. The download HTTP integration could not run without the guarded server/state. No migrations or application-row writes occurred.

Native Excel/independent PDF audit of 14 r6 workbooks: **4,434 assertions, 4,378 passed, 56 failed, exit 1**. Every failure concerns the unchanged 8pt floor. Active CC code/title text is 6.12pt; minima across all displayed text are 5.64pt on CC and 7.20/7.68pt on PEF. Two-page order, Folio geometry, selected/blank GWA, notices, native history/CC/signatory parity and the executed clipping checks passed. This does not waive unreadable operational text. The checker rejects missing native field contracts before rendering.

Two additional generated regressions exposed external form-associated controls failing to invalidate the signed preview/update the count. Both failed before a minimal `form.elements` event-listener correction and passed afterward; the complete focused run above includes them. No academic policy or template geometry changed.

No follow-up commit, push, PR update or merge occurred because the required native and database-backed gates did not pass. The separate dedicated accessibility server/fixture preflight also remains required.

## Dated historical implementation evidence

- 18 October 2026: the original WP1 used public XLSX downloads. That public transport contract is superseded by PDF-only output; private workbook cell tests remain relevant.
- 18–19 October 2026: r2/r3 section and font audits recorded preserved failures. The earlier native operational run had 1,399 checks with 44 font-floor failures. Those are dated observations, not results for the current r6 final gate, and must not be counted as passes or silently waived.
- 19 October 2026: the owner approved r6 original CCS geometry, official CC Remarks/Status and clean Dean ending. Private binary/rollback/recovery evidence is retained separately.
- PR #51 merged on 19 October 2026 Manila time. The PDF/state/UI continuation is PR #54. Its published implementation commit `0a52b3c` is preserved without rewriting history. A clean GitHub merge calculation is not test approval or owner merge authorization.
