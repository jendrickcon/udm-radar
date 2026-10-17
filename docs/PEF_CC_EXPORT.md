# PEF & Curriculum Checklist Planning Export

PEF-CC-WP1 adds **Enrolment Planning** to the authenticated Student portal. A Student can preview a completed term, manually choose up to twelve proposed subjects, enter export-only target details, and download a two-sheet Excel planning copy. It does not update any application record.

This is a Student-prepared planning copy for evaluator review. It is not an official PEF, approved Curriculum Checklist, enrolment decision, or Study Plan. Every output preserves `Prototype performance on synthetic academic records`. Final owner inspection of actual-size paper output and institutional authorization for the retained logos are release gates. Technical validation is not institutional approval.

## Academic and data contract

The ownership key is the authenticated `users.id`. The server reloads identity, the Student's own complete grade history, and the subject catalogue using SELECTs in a consistent read-only transaction. Student IDs, identity, grades, units, GWA, staff names, cell addresses, paths and filenames cannot be supplied by the browser.

A completed evaluation term must have a valid consecutive school-year pair, semester 1 or 2, and exclusively historical rows. It must precede the current configured academic term. All rows are inspected before filtering. Mixed current/history flags, current or future terms, orphan subjects, duplicate records/codes, unsupported grades, incompatible identity and unusable source data require record review and block export. The page does not repair records or select a best/latest attempt.

All selected-term rows remain visible, including NULL, blank, INC and numeric zero. More than **13 recorded subjects** or **12 proposed subjects** blocks export; nothing is truncated. Zero proposed subjects is allowed. A Student with no completed term sees an explicit unavailable PEF state and can still view the independent reference checklist. First through Fourth Year and cross-year accounts use the same rules; year level, pacing, risk and predictions do not grant or deny eligibility.

`computeWeightedGWA()` in `config/constants.php` remains the academic authority. Only the selected completed term enters the calculation. Its existing exclusion of numeric zero, textual outcomes and unusable units is preserved. Numeric 1.00–4.00 passes; zero fails; INC is unresolved. Subject outcome and GWA inclusion are separate. An unavailable GWA is shown as **Unavailable** and written to `PEF!C37` as an actual blank, never zero or an Excel formula. Completion/source conflicts suppress GWA pending review; overflow alone can retain the complete term's GWA for inspection.

Coverage uses one primary reason per recorded row so included plus excluded counts reconcile. NULL, blank, zero, INC, other textual status, invalid units and unsupported values remain distinct. Eligible units follow the canonical helper's integer-unit behavior. Fractional or nonpositive catalogue units require record review rather than inventing credits.

## Independent CC reference

There is no approved Student-to-curriculum mapping in the schema. Every checklist remains **Reference / Requires Review**, regardless of account year or apparent completion. The 2022–2023 grid is read from the approved template, not treated as an authoritative curriculum database.

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

Editable planning inputs are target school year (`YYYY-YYYY`, consecutive), target semester (1/2), optional single-line section (96 Unicode characters maximum), and zero to twelve distinct positive catalogue IDs. Server-loaded code/title/units preserve the submitted order. Subject choices do not imply an offering or eligibility. Section tabs, line breaks and XML-invalid controls are rejected; no silent truncation occurs.

Identity is formatted with the existing name helper and uppercased once in the immutable model. Both HTML and Excel use those same display strings. Fixed College of Computing Studies identity must agree with the source college and supported BSIT program. Academic Standing, previous standing, evaluator, Dean, signatures, approval dates and professor fields are withheld and blank.

The server-rendered form works without JavaScript. JavaScript enhances counters, preview invalidation and recoverable download feedback; it calculates no academic result and stores no draft. The page uses existing portal components and theme tokens, with page-scoped layout only. Student navigation is inserted before Settings without changing Admin/Faculty items.

A PHP 8.2 readonly scalar model drives both preview and XLSX. A server-only random 32-byte per-session key signs a canonical SHA-256 model digest and ten-minute envelope with HMAC-SHA-256. The MAC binds the authenticated Student and session, template hash/version and expiry. It is separate from CSRF. The download POST reloads authoritative data and rebuilds the model. Changed records, identity, units, catalogue/reference evidence, planning choices, tampering, expiry or cross-session/Student replay requires a fresh preview (409). No model or draft is persisted.

Both routes enforce existing active-Student access. The export is POST-only (405 otherwise), checks CSRF (403), limits requests to 16 KiB, and rejects unexpected fields (422). Missing/invalid templates and generation failures produce a safe unavailable response (503), without SQL, private paths or stack traces in the response. Sessions close before expensive workbook work.

The request budget checks bounded actual body bytes as well as Content-Length, including chunked requests. POST uses the supplied URL-encoded form; unsupported media types return 415. Both routes disable deployment error display and install a generic fatal response before bootstrap. Actual loopback tests with initial `display_errors=1` and an injected generation timeout verified safe JSON/HTML 503 responses and shutdown cleanup. This does not establish every production Apache failure mode.

After a completed output passes size/package checks, the fixed download filename is `UDM-RADAR-PEF-CC.xlsx`. Headers include XLSX MIME, attachment disposition, private/no-store, no-cache, expiry zero and nosniff. There is no public output URL or database export-audit write.

## Template and fixed writer

Approved sanitized candidate: `pef-cc-template-candidate-v0.1.xlsx` (not stored in Git).

```text
Version: v0.1
SHA-256: 0e42f6ecbdd386d35e25a658dee0cda4edf48d14d1cdc0b2754a544746160d4c
```

The runtime fails closed on wrong hash/version, wrong sheets, external relationships, unexpected formulas/logo, reference-grid or required print/merge/disclosure mismatch. Exactly PEF and CC are visible; StudentList, Study Plan, macros, embedded objects and external links are absent. Each generation loads a fresh verified template and preserves geometry, styling, logo bytes, data validation, conditional formatting and the nine approved bounded SUM formulas with calculated caches.

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
C:\ProgramData\UDM-RADAR\private\templates\pef-cc\v0.1\template.xlsx
C:\ProgramData\UDM-RADAR\private\tmp\pef-cc\
```

Create them outside every web root. Copy only the sanitized candidate from the verified migration ZIP, never the source roster workbook. Confirm its hash before enabling exports:

```powershell
Get-FileHash -Algorithm SHA256 'C:\ProgramData\UDM-RADAR\private\templates\pef-cc\v0.1\template.xlsx'
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

PR #48 adds `synthetic-cohort-portal-verification.spec.js`, whose before/after hooks unconditionally run cohort and section seed/cleanup tools on existing scratch records. It is outside WP1's owned-fixture lifecycle. Do not run an unqualified full suite against a shared scratch baseline under this package. The reviewed non-cohort selection can use `--grep-invert 'Synthetic Multi-Year Cohort Portal Verification'`; record those five cases as **Not Run**, separately from the six legacy credential skips. Execute cohort seeding tests only in a separately approved isolated cohort testbed. No cohort tool is changed by WP1.

The migration handoff carries Git branch/base/HEAD, a verified incremental Git bundle, binary patch and all 25 source copies, dependency and test evidence, a SHA-256 manifest, and the sanitized private template. Git is the primary source-code transfer. The upload-ready ZIP is an additional copy and the template transfer mechanism. It excludes dependencies, browser binaries, source roster, generated Student exports, live dumps, credentials and session state.

## Deferred policy and release work

WP2/WP3 remain policy-blocked: no automatic subject suggestions, official irregular classification, curriculum-version inference, prerequisite eligibility, semester offerings, maximum load, graduation estimates or multi-term Study Plan. Professor/instructor fields, staff approval, signatures, official submission, persistent drafts, themes, cohort seeding and manuscript changes are excluded.

Before release: review actual-size printed PEF/CC (including long valid data at capacity), authorize logo use, and verify production Apache ACLs/runtime/cleanup scheduling. If valid source text does not fit the approved geometry, require a versioned template review; do not truncate, shrink fonts further or silently change row/column geometry.

**Known v0.1 print limitation (native Excel, 2026-10-17):** a valid 96-character proposed section clips in the fixed `PEF!A51:G51` field. Maximum-length source names/codes/titles also require actual-paper legibility review at full capacity. The workbook retains the complete literal values; the writer does not truncate or change geometry/font settings. Release is blocked on a versioned template-layout review and owner acceptance of these cases. Passing software tests or opening Excel normally does not close this gate.
