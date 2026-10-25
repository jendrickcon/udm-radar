# Reconciliation verification record — 26 October 2026

This is fresh reconciliation evidence, separate from the original checkpoint and the external Antigravity Stage A package. No Stage B certification is claimed. Baseline e8d72a6d487444dcc6b5d11b1e0824376d7e8015; original checkpoint 554312cbef2efab9c0b574cef18f518be2b3b6d6; normal continuity merge c32bc38d99d38401b9690adcc1a35d6f7f739a7c. Current logs are ignored logs/analytics-reconciliation/. Runtime/test correction files are frozen for independent follow-up; exact final immutable SHA will be in the owner report and Git.

## Commands and actual results

- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/post_defense_analytics_test.php ;  85 passed; exit 0 ;  logs/analytics-reconciliation/post_defense_analytics_test.log
- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/analytics_fixture_contract_test.php ;  7 passed; exit 0 ;  logs/analytics-reconciliation/analytics_fixture_contract_test.log
- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/analytics_review_regression_test.php ;  7 passed; exit 0 ;  logs/analytics-reconciliation/analytics_review_regression_test.log
- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/final_grade_helpers_test.php ;  271 passed; exit 0 ;  logs/analytics-reconciliation/final_grade_helpers_test.log
- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/gwa_helpers_test.php ;  108 passed; exit 0 ;  logs/analytics-reconciliation/gwa_helpers_test.log
- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/academic_outcome_contract_test.php ;  101 passed; exit 0 ;  logs/analytics-reconciliation/academic_outcome_contract_test.log
- PHP unit regression ;  C:/xampp/php/php.exe tests/unit/prediction_explanation_test.php ;  32 passed; exit 0 ;  logs/analytics-reconciliation/prediction_explanation_test.log
- SQL integration ;  node tests/fixtures/run_analytics_mvp.mjs → PHP tests/integration/post_defense_analytics_test.php ;  44 passed; exit 0 ;  integration-final.txt
- Feature browser ;  Same owned runner, pinned Playwright 1.63.0; installed Edge ;  29 passed, 0 failed/skipped; exit 0 ;  browser-final.json
- Faculty generated/source desktop ;  Runner first: faculty-dashboard-layout-regression.spec.js; --grep-invert=Multi-viewport; Capture required screenshots ;  6 passed, 0 failed/skipped; exit 0 ;  faculty-layout.json
- Existing generated desktop ;  Local pinned runner, student-dashboard-gwa-outcomes.spec.js + goal-calculator-feasibility.spec.js; --grep=1440; layout, keyboard and readable status 1024; completed and explicit unresolved; no feasible goal ;  20 passed, 0 failed/skipped; exit 0 ;  generated-desktop.json
- Canonical-equivalent Admin focus ;  Local pinned runner, isolated byte-exact Git-object source copy at e8d72a6; --grep=Accessibility & Focus Management; closing during the opening transition ;  3 passed; RESOLVED ON CURRENT MAIN ;  baseline-admin-focus.json
- Canonical-equivalent Student fixture ;  Same isolated copy, student-dashboard-gwa-outcomes.spec.js --grep=1440 ;  10 passed; RESOLVED ON CURRENT MAIN ;  baseline-student-gwa.json
- PHP syntax ;  php -l on full changed/new PHP inventory ;  21 files passed ;  php-lint.log
- JS syntax ;  node --check assets/js/academic-analytics.js; tests/browser/post-defense-analytics.spec.js; tests/fixtures/run_analytics_mvp.mjs ;  three files passed ;  current execution
- Whitespace; protection ;  git diff --check; cached/committed layers; protected path name-status against origin/main and HEAD ;  passed; no protected changes ;  final staged check; Git

## Evidence classification

The 29 feature cases comprise 27 real-login application/desktop cases and two controlled mocked transport/error cases. All use the actual login setup, but the mocked error/race responses are not additional live endpoint coverage. Faculty six cases are generated HTML/source structural evidence. Generated calculator/GWA and canonical-equivalent baseline checks are not authenticated workflow evidence. The two earlier baseline blockers were tested in a minimal byte-exact Git-object source copy under ignored logs, without using or writing canonical checkout and without database calls. No broader deployment work was resumed.

Desktop feature sizes: 1440x900, 1024x768, 1280x600 in both themes. No new screenshots/mobile tests; exclusions are owner scope, not passes. Existing generated desktop selections also use their unchanged desktop sizes. No assertions weakened. Browser JSON start time (UTC): 2026-10-25T18:29:56.486Z. Client reporting date/time zone: 26 October 2026, Asia/Manila.

## Regression evidence

- metric-red.txt: seven failing assertions for partial-current, summer comparison and mean/referral-origin contracts; metric-green.txt: 85 passed.
- browser-metrics-red.json: two expected failing cases for percent prominence and mean/closure labels; final browser gate green.
- timestamp-red.txt: original checkpoint module reproduced six invalid timestamp/sequence defects; timestamp-green.txt: seven passed.
- fixture-contract-red.txt: four source-section fingerprint assertions failed before implementation; fixture-contract-green.txt: seven parser/schema-only checks passed.
- Controlled state-write failure and early server-exit runs intentionally exit 1; each is an expected failure-path reproduction, not a failed acceptance run. Their lifecycle records assert exact target removal/source integrity; no stale schema reused.

## Temporary database and accepted DDL input

- Final full-suite database: udm_radar_analytics_mvp_fd9a822a645467c8.
- Provenance marker: analytics_fixture_owner; verified token SHA-256 b96007f0d7cee91986122fb71c581f569ab8358271249e489e310bd2e7493176. The secret token/login password are not published.
- Owner authorizes reading database/udm_radar.sql solely for accepted CREATE TABLE definitions. Source SHA-256 before/after: 5267faa7089410d5866467dfb87f211334fe93ec85b61bfffab6cc73969afd99. Byte-identical result: true.
- Entire file is read to parse statements safely; only the following 16 required CREATE TABLE sections execute. OTP/export-audit definitions are parsed but excluded. No INSERT/example/application/dump data or genuine records are imported.
- Source statement hashes, exact line spans and excluded sections are retained in fixture-lifecycle.json.
- Only the unique owned namespace is selected. Neither udm_radar nor udm_radar_scratch is connected to, inspected, rebuilt or modified. Target guards reject both protected names.
- Cleanup: provenance-checked cleanup-stdin → DROP exact owned name → filtered information_schema.SCHEMATA assertion count zero. removed=true; cleanup_asserted=true.

- admin_change_log ;  14–31 ;  c5da59bfb99d624ccbdc0ccff00675d095090d56e4e2683091711db17096b2c6
- faculty_class_loads ;  35–46 ;  77bc450bb781f09b223f9a438fc38c594d028f76bed29c0d3e1529c1293fe4b9
- feedback_messages ;  50–61 ;  9309ce34c5b70596c69df7ead65fde0b3be978c457fa19fbd31c9d3a61a9412f
- feedback_reports ;  65–89 ;  6b19da1a539d00d28ac39de14c7bfbc4cc0cf78ab24be3a9c96384f0344b9cf8
- feedback_status_history ;  93–106 ;  90a2e8df9807f031edcc6dffd568e64509573cb31156a3ca809d6fadef8e0609
- grades ;  110–132 ;  cd1fa0b35df0603bd1aadfb8827fdc63d304bcd3a80ca5baec8014c4464c533c
- pending_corrections ;  147–173 ;  49b85fda0e9a0b2646dc78cf5500c76093b3eb7fae9116642619d006bfc95a59
- pending_grade_batches ;  177–198 ;  b9e000799579eadfe23de097b6a97edb2513939d97bccc2a28bda0c5b06c2f41
- predictions ;  202–225 ;  7ced1cdf7cc14e7a073aea8669ab52398629801ff5c94af4ac673e312cbfbc2b
- student_profiles ;  229–247 ;  61115a946cc414bd5c6a31b57e87a0c98713948dadbf062012cb00a280248c28
- subjects ;  251–259 ;  450fd6d692094d6a12be755be88171477c0e2ce796b58e5c15a989eaf0ae321a
- support_actions ;  263–277 ;  ddd4246fe657dbac8fc123486b349e47a6dc82a0085d378a83a605720a56030a
- support_case_referrals ;  281–305 ;  e521a688a6e2a9fc589429554a8323c1d98e55d1cbd3adf0e66a10e7acda3e5d
- support_status_history ;  309–322 ;  c5b8124b17ac98b501cc2709ffd165ad21f53b80f0577597acfb91c4be8a92b3
- users ;  326–340 ;  8bb552616c1dc1b920d8913bb83521018378ecee14b58c7870f11cdf75a05d4c
- academic_support_cases ;  344–370 ;  35d76b879dc66b32c56ff07521f075c1416eebcf75240103aa80f6064edcc4eb

State write failure: target udm_radar_analytics_mvp_ef441e8e6fd30114; provenance token SHA-256 aada1009464244f38149be74e990b8c45143c98d3d2b01a95477eed5676e6c30; removed=true, absence asserted=true, DDL unchanged=true. Expected exit 1 confirmed in ANALYTICS_MVP_SIMULATE_STATE_WRITE_FAILURE.log.

Early server exit: target udm_radar_analytics_mvp_342c8653c007bde5; provenance token SHA-256 85dd4f47d58067f4e814c4706209b68f2cc49f46e49c7931fe2094d6f2c71bf3; removed=true, absence asserted=true, DDL unchanged=true. Expected exit 1 confirmed in ANALYTICS_MVP_SIMULATE_SERVER_EXIT.log.

## Owner instruction retained verbatim

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

No push, PR, merge to main, deployment, migration, Migration 011, Apache or canonical checkout change. No protected model/dependency/auth-policy/private-template/archive changes. External Stage A evidence was read only, not regenerated or overwritten. Independent reconciliation follow-up and owner integration decisions remain separate from Stage B certification.

## Exact desktop selection arguments

```text
Faculty: --grep-invert=Multi-viewport|Capture required screenshots
Generated calculator/GWA: --grep=1440|layout, keyboard and readable status 1024|completed and explicit unresolved|no feasible goal
Baseline Admin: --grep=Accessibility & Focus Management|closing during the opening transition
Baseline Student: --grep=1440
```
