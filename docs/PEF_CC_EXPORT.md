# PEF & Curriculum Checklist Planning Export

PEF-CC-WP1 adds **Enrolment Planning** to the authenticated Student portal. Supported Software Development students can preview a completed term and download a two-sheet Excel planning copy. Regular students receive a read-only nominal subject plan from the approved 2022–2023 mapping and coherent current-term evidence. Irregular students retain manual proposed subjects and export-only target details. It does not update any application record.

This is a Student-prepared planning copy for evaluator review. It is not an official PEF, approved Curriculum Checklist, enrolment decision, or Study Plan. Every output preserves `Prototype performance on synthetic academic records`. The owner confirmed Folio (8.5 × 13 inches) and authorized the retained supplied artwork. Final owner actual Print Preview and paper acceptance remain pending. The maximum-text print stress gate fails; this branch is a draft review candidate, not a release.

## Academic and data contract

The ownership key is the authenticated `users.id`. The server reloads identity, the Student's own complete grade history, and the subject catalogue using SELECTs in a consistent read-only transaction. Student IDs, identity, grades, units, GWA, staff names, cell addresses, paths and filenames cannot be supplied by the browser.

A completed evaluation term must have a valid consecutive school-year pair, semester 1 or 2, and exclusively historical rows. It must precede the current configured academic term. All rows are inspected before filtering. Mixed current/history flags, current or future terms, orphan subjects, duplicate records/codes, unsupported grades, incompatible identity and unusable source data require record review and block export. The page does not repair records or select a best/latest attempt.

All selected-term rows remain visible, including NULL, blank, INC and numeric zero. More than **13 recorded subjects** or **12 proposed subjects** blocks export; nothing is truncated. Zero proposed subjects is allowed only in the preserved Irregular manual workflow. An unresolved Regular plan cannot export an empty fallback. A Student with no completed term sees an explicit unavailable PEF state and can still view the independent reference checklist. Risk and predictions do not determine pacing or planning eligibility.

`computeWeightedGWA()` in `config/constants.php` remains the academic authority. Only the selected completed term enters the calculation. Its existing exclusion of numeric zero, textual outcomes and unusable units is preserved. Numeric 1.00–4.00 passes; zero fails; INC is unresolved. Subject outcome and GWA inclusion are separate. An unavailable GWA is shown as **Unavailable** and written to `PEF!C37` as an actual blank, never zero or an Excel formula. Completion/source conflicts suppress GWA pending review; overflow alone can retain the complete term's GWA for inspection.

Coverage uses one primary reason per recorded row so included plus excluded counts reconcile. NULL, blank, zero, INC, other textual status, invalid units and unsupported values remain distinct. Eligible units follow the canonical helper's integer-unit behavior. Fractional or nonpositive catalogue units require record review rather than inventing credits.

## Independent CC reference

The owner-approved Software Development planning mapping is a local configuration, not a new schema relationship or a universal curriculum-applicability decision. Every checklist remains **Reference / Requires Review**, regardless of account year or apparent completion. Its 2022–2023 grid is read from the private template independently of nominal subject planning.

Annotations use exact subject codes across the Student's available history, independently of the PEF evaluation term:

| State | Meaning |
| --- | --- |
| CMP | Recorded passing outcome |
| FAIL | Numeric zero |
| INC | Incomplete/unresolved |
| CUR | Current record |
| NA | Final result unavailable |
| REVIEW | Other status, invalid evidence, or ambiguous attempts |
| Blank | No matching record |

Multiple matches or conflicting attempts produce REVIEW without choosing a numeric grade. Blank does not mean Need to Enrol. The unresolved `ITE252` prerequisite text remains intact. No equivalency, applicability, prerequisite, completion percentage or deficiency inference occurs. Raw matching evidence participates in the signed snapshot even when its displayed state stays REVIEW or CUR.

## Preview, inputs and download

Irregular planning inputs are target school year (`YYYY-YYYY`, consecutive), target semester (1/2), optional single-line section (96 Unicode characters maximum), and zero to twelve distinct positive catalogue IDs. Server-loaded code/title/units preserve the submitted order. Regular targets, nominal IDs and section options are derived and validated on the server. Subject choices do not imply an offering or eligibility. Section tabs, line breaks and XML-invalid controls are rejected; no silent truncation occurs.

## Approved Software Development planning policy

`config/bsit_software_development_curriculum_2022_2023.php` contains only approved academic data: eight ordered terms, 58 unique subjects and 159 units, source-cell provenance, raw prerequisite text and elective-slot mappings. Its source is the department-supplied workbook; the confidential original roster is never deployed or committed. Catalogue display titles are retained where approved, with original titles recorded in the mapping. `ITE351/352/353/354` slots map to `SD351/352/353/354` in their approved terms; `ITE252` remains unresolved informational text. Blank prerequisites do not mean None. No prerequisite, equivalency, availability or study-plan approval is inferred.

The exact supported course is `BSIT - Software Development`, with an active Student account and Active profile. Cyber Security and Data Science records remain historical and return `unsupported_legacy_track_requires_review`; there is no track conversion or active CY/DS selection. Unknown course/status/lifecycle evidence requires review.

For Regular students, the stored year level is bound to exactly one coherent own current-term set corroborated by approved current-term metadata. Current rows must agree on school year, semester and `is_current=1`; subject code/title/units/year/semester must agree with the supported curriculum and catalogue. Partial legitimate current records and missing grades are accepted; absent, duplicate, malformed, conflicting or mismatched evidence returns `evaluator_review_required`. No calendar, student-number, section-only, faculty-load, grade-pattern or selected historical evaluation term inference is used.

- Current Semester 1 → Semester 2, same school year and nominal year; retain the recorded section read-only.
- Current Semester 2 → Semester 1, next school year and next nominal year; permit only actual target-term sections with the corresponding year prefix and current block ±1. Block zero, wrong years, malformed/nonexistent options and tampered free text are rejected. No Fifth Year is generated.
- Target section evidence comes from coherent active SD profile/current-record contexts and is reduced to nonpersonal section/term/provenance data. Current-year observations are never relabeled as future availability. This schema has no verified future-section source, so next-year planning can require evaluator confirmation rather than inventing options.
- Regular nominal IDs and order are read-only. Insertion, deletion, replacement, target override and manipulated hidden fields fail validation. An allowed section preference never changes pacing or enables manual subject editing.

Recorded current section and proposed section are displayed separately. The workbook's single section field is explicitly the proposed planning preference. Neither profile nor recorded section is changed. The signed preview includes curriculum hashes, policy version, own current-term evidence and eligible section provenance; any relevant change requires a fresh preview.

Identity is formatted with the existing name helper and uppercased once in the immutable model. Both HTML and Excel use those same display strings. Fixed College of Computing Studies identity must agree with the source college and supported BSIT program. Academic Standing, previous standing, evaluator, Dean, signatures, approval dates and professor fields are withheld and blank.

The server-rendered form works without JavaScript. JavaScript enhances counters, preview invalidation and recoverable download feedback; it calculates no academic result and stores no draft. The page uses existing portal components and theme tokens, with page-scoped layout only. Student navigation is inserted before Settings without changing Admin/Faculty items.

A PHP 8.2 readonly scalar model drives both preview and XLSX. A server-only random 32-byte per-session key signs a canonical SHA-256 model digest and ten-minute envelope with HMAC-SHA-256. The MAC binds the authenticated Student and session, template hash/version and expiry. It is separate from CSRF. The download POST reloads authoritative data and rebuilds the model. Changed records, identity, units, catalogue/reference evidence, planning choices, tampering, expiry or cross-session/Student replay requires a fresh preview (409). No model or draft is persisted.

Both routes enforce existing active-Student access. The export is POST-only (405 otherwise), checks CSRF (403), limits requests to 16 KiB, and rejects unexpected fields (422). Missing/invalid templates and generation failures produce a safe unavailable response (503), without SQL, private paths or stack traces in the response. Sessions close before expensive workbook work.

The request budget checks bounded actual body bytes as well as Content-Length, including chunked requests. POST uses the supplied URL-encoded form; unsupported media types return 415. Both routes disable deployment error display and install a generic fatal response before bootstrap. Actual loopback tests with initial `display_errors=1` and an injected generation timeout verified safe JSON/HTML 503 responses and shutdown cleanup. This does not establish every production Apache failure mode.

After a completed output passes size/package checks, the fixed download filename is `UDM-RADAR-PEF-CC.xlsx`. Headers include XLSX MIME, attachment disposition, private/no-store, no-cache, expiry zero and nosniff. There is no public output URL or database export-audit write.

## Template and fixed writer

Private sanitized review candidate: `pef-cc-template-candidate-v0.2-r2.xlsx` (not stored in Git; owner final print acceptance pending).

```text
Version: v0.2-r2
SHA-256: c1895897710d29672960a7414155627409dd847260236c1774f1f444d9bfed02
```

The runtime fails closed on wrong hash/version, wrong sheets, external relationships, unexpected formulas/logo, reference-grid or required print/merge/disclosure mismatch. Exactly PEF and CC are visible; StudentList, Study Plan, macros, embedded objects and external links are absent. Each generation loads a fresh verified template and preserves its geometry, styling, logo bytes, data validation, conditional formatting and nine bounded SUM formulas with calculated caches.

Relative to preserved v0.1 (`0e42f6ecbdd386d35e25a658dee0cda4edf48d14d1cdc0b2754a544746160d4c`), the candidate changes only `PEF!A51` wrap-text and row 51 height from 19.5 to 96 pt. Original font, merged region, columns and borders are retained; all other original ZIP part bytes are identical. The earlier 72 pt v0.2 trial fits the original phrase but overlaps labels for 96 wide Unicode characters, and remains recovery-only. Neither trial overwrites v0.1.

Fixed destinations are PEF identity `A10,A12,C14`, evaluation `A19,C19`, 13 recorded rows `A/B/E/F/G23:35`, scalar GWA `C37`, target `A49,C49,A51`, 12 proposed rows `A/B/G54:65`, and coverage `A78`. Withheld standing/staff/signature/date cells remain blank. CC uses identity `B1`, reference grade/remark/status destinations derived only from the verified 58-course grid, and a fixed `A3` title: `2022-2023 Curriculum Checklist - Reference / Requires Review`. This static title supplies the owner's exact applicability wording; the deployed blank template remains byte-identical.

Text, including stored database text, is written with explicit string typing; numbers and true blanks retain their types. Formula-like text cannot become formulas, hyperlinks or relationships. Writer-level CR/CRLF becomes LF; the request validator rejects multiline section input. No input controls destinations, sheet names, defined names or output paths. HTML escapes data; enhancements use safe text APIs.

## Private deployment on Windows/XAMPP

Use PHP 8.2 or a compatible runtime satisfying the lock (current validation: PHP 8.2.12 x64). Install the exact lock without plugins or scripts:

```powershell
C:\xampp\php\composer.bat install --no-plugins --no-scripts --no-interaction --prefer-dist
C:\xampp\php\composer.bat check-platform-reqs --no-plugins
C:\xampp\php\composer.bat audit --no-plugins
```

The dependency is pinned `phpoffice/phpspreadsheet:5.10.0`; existing dompdf packages are unchanged. Required extensions: ctype, dom, fileinfo, filter, gd, iconv, libxml, mbstring, simplexml, xml, xmlreader, xmlwriter, zip and zlib; application integration also requires pdo_mysql and curl. Do not copy vendor or node_modules between machines.

Default private locations:

```text
C:\ProgramData\UDM-RADAR\private\templates\pef-cc\v0.2-r2\template.xlsx
C:\ProgramData\UDM-RADAR\private\tmp\pef-cc\
```

Create them outside every web root. Copy only the sanitized candidate from the verified migration ZIP, never the source roster workbook. Confirm its hash before enabling exports:

```powershell
Get-FileHash -Algorithm SHA256 'C:\ProgramData\UDM-RADAR\private\templates\pef-cc\v0.2-r2\template.xlsx'
```

`config/pef_cc.php` contains non-secret defaults. Trusted environment overrides: `PEF_CC_PRIVATE_ROOT`, `PEF_CC_TEMPLATE_PATH`, `PEF_CC_TEMP_PATH`. Contract hash/version/capacities are fixed. No request override or original-workbook fallback exists. Canonical paths and ancestors must remain inside the approved private root and outside detected web roots, including escaping junctions/symlinks.

The deployment owner and SYSTEM control template writes. Identify the actual Apache service/process account. Give that account read access to templates and read/write/delete access only to the private output directory. Do not grant Everyone access or assume the interactive username is the Apache account. Verify ACLs with `icacls` before deployment. Local loopback CLI validation does not establish Apache service ACLs.

Each output uses exclusive creation and a random 128-bit `pefcc-<32 hex>.xlsx` basename with no Student content. Success, failure, stream completion/disconnect and PHP shutdown remove owned files. Cleanup remains possible if the template becomes unavailable. Generation has a 30-second runtime limit on Windows and elapsed deadline checks before streaming, plus a 5 MiB output limit. A hard Apache timeout/memory guarantee requires deployment-specific evidence; CPU/runtime limits alone do not establish every server failure mode.

The CLI reaper defaults to dry-run, checks private paths, ignores links/directories, preserves unrelated files, and deletes only owned filenames at least ten minutes old:

```powershell
C:\xampp\php\php.exe scripts/cleanup_pef_cc_exports.php --dry-run
C:\xampp\php\php.exe scripts/cleanup_pef_cc_exports.php --delete
```

Recommend an owner-configured five-minute scheduler for roughly fifteen-minute maximum abandoned retention while the scheduler functions. This package creates no scheduled task. Monitor non-identifying counts and failures; never place Student details in filenames or logs.

## Tests and new-laptop recovery

Safe new unit checks:

```powershell
C:\xampp\php\php.exe tests/unit/pef_cc_view_model_test.php
C:\xampp\php\php.exe tests/unit/pef_cc_template_test.php
C:\xampp\php\php.exe tests/unit/pef_cc_export_contract_test.php
C:\xampp\php\php.exe tests/unit/pef_cc_curriculum_test.php
```

Database fixtures require explicit `DB_NAME=udm_radar_scratch` and prove `SELECT DATABASE()` before mutations. They add random owned synthetic users/subjects/grades, remove only their own rows and compare whole-table fingerprints to preserve existing records. They never rebuild/truncate or fall back to `udm_radar`. A compatible migrated scratch schema must already exist. Inspect test paths and prerequisites before use.

```powershell
$env:DB_NAME='udm_radar_scratch'
C:\xampp\php\php.exe tests/integration/pef_cc_read_test.php
# Use your installed Node executable; the state file must be private and new.
node tests/fixtures/pef_cc_test_server.mjs --state-file C:\private-test-evidence\pef-state.json
```

Keep the harness running. It prints its random loopback URL, mounts only reviewed routes under `/udm-radar/`, uses real login and private sessions, and has no fixture HTTP endpoint. In another terminal:

```powershell
$env:DB_NAME='udm_radar_scratch'
$env:PEF_CC_STATE_FILE='C:\private-test-evidence\pef-state.json'
# Set PLAYWRIGHT_BASE_URL to the exact URL printed by the harness.
$env:PLAYWRIGHT_BASE_URL='http://127.0.0.1:<printed-port>/udm-radar/'
$env:PHP_BINARY='C:\xampp\php\php.exe'
C:\xampp\php\php.exe tests/integration/pef_cc_download_test.php
node node_modules/@playwright/test/cli.js --version
node node_modules/@playwright/test/cli.js test student-pef-cc-planning student-pef-cc-download
```

Use installed pinned Playwright **1.63.0** and installed Edge, never an auto-downloaded runner. Install JavaScript dependencies from the existing lock on a new machine. Audit every legacy spec/fixture and base URL before a full-suite run. Stop the fixture harness with Ctrl+C, confirm fingerprint-preserving cleanup, and remove private session/state files after retaining non-secret results. Do not run separate mutating suites against the same fixture baseline concurrently.

PRs #48–50 include cohort, Batch Predict and cross-portal Midterm browser suites with seed/cleanup or fixed-account mutations outside WP1's owned-fixture lifecycle. Do not run an unqualified full suite on a shared scratch baseline. The preservation-safe selection uses `--grep-invert 'Synthetic Multi-Year Cohort Portal Verification|Admin Batch Predict Flow and Interaction Verification|Cross-Portal Midterm Grade Visibility'`. Discover actual selected/excluded counts each run; report exclusions as **Not Run**, separately from legacy credential skips. These suites require a separately approved isolated cohort testbed. No cohort, Batch Predict or Midterm tool is changed by WP1.

The migration handoff carries Git branch/base/HEAD, a verified incremental Git bundle, binary patch and all 29 source copies, dependency/test evidence, SHA-256 manifest, and sanitized private template. Git is the primary source-code transfer. The private ZIP is an additional copy and template transfer mechanism. It excludes dependencies, browser binaries, source roster, generated Student exports, live dumps, credentials and session state. A review-candidate ZIP must remain labeled release-blocked; create a new final ZIP only after final template approval, never overwrite the original 20261017-202113 recovery assets.

## Deferred policy and release work

WP2/WP3 remain deferred. The approved narrow SD nominal list is part of WP1; broader automatic study plans, official irregular classification, universal curriculum-version inference, prerequisite eligibility, semester offerings, maximum load, graduation estimates and multi-term planning are excluded. Professor/instructor fields, staff approval, signatures, official submission, persistent drafts, themes, cohort seeding and manuscript changes are excluded.

Before release: resolve maximum-text print failures, obtain owner actual-size Excel Print Preview/paper acceptance, and verify production Apache ACLs/runtime/cleanup scheduling. Logo use is authorized. If valid source text does not fit, require a versioned template review; do not truncate, reduce accepted limits, weaken tests or shrink fonts further.

**Native Excel review, 2026-10-18:** v0.2-r2 fits normal, original 96-character, 96-wide-Latin and 96-wide-Unicode sections without label overlap, on one Folio page per sheet. Capacity remains 13/12. The opt-in `tests/integration/pef_cc_print_layout_test.ps1` reopens synthetic samples read-only, exports PDF, checks actual page dimensions, native grid/glyph bounds and a declared 8 pt variable-data review floor. PDF text presence alone is insufficient; raster review corroborates the geometry. This is not a substitute for owner Print Preview or institutional-printer acceptance.

The complete print stress gate remains **Failed**: maximum-length codes can cross cell borders, long titles render around 3.96–5.4 pt, and several normal/capacity fields are 7.44 pt under the retained scaling. The 8 pt floor is an explicit review criterion, not an asserted institutional standard. These failures are preserved, not waived; LibreOffice is unavailable. No actual print job or Windows default-printer change occurred. The software can be reviewed as a draft; release remains blocked.
