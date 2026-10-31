# Owner local testing handoff

Branch: feat/member2-student-portal
Baseline: 60a6f7614b594cd8759a3c9211e060c3b8d7ab62
Purpose: owner testing of the local Student candidate. This is not merge, deployment or final acceptance.

Updated: 29 October 2026 (Asia/Manila).

**Delivery scope: Student feature-branch owner testing.** The user subsequently authorized committing and pushing the reviewed Student deliverables on 29 October 2026, superseding the earlier publication hold. Original-password verification remains pending; password resets, PR, merge and deployment remain unauthorized. SQL datasets/private exports are excluded.

Previous published feature-branch HEAD: `94550c75268d103def200723b78e9d39b64edcba`. This delivery adds the reviewed Student refinements and reports. Identify the delivery commit with `git log -1` after updating the feature branch; the publication response records the verified remote SHA. Recorded fingerprints and tests identify the pre-commit application bytes; report-only publication updates do not change the tested application.

## Get the candidate

Check/preserve your existing local work before switching branches. In your own clone:

```powershell
git status --short --branch
git fetch origin
git switch --track origin/feat/member2-student-portal
```

After successful publication, these commands access the feature-branch delivery. If that local branch already exists, switch to it and update with git pull --ff-only only after checking local changes. Do not overwrite local credentials, private templates or existing databases. Private database dumps, credentials, .a, vendor/node_modules and test logs stay outside publication. Your existing schema-compatible local database/configuration is required; use database/README.md for a separately authorized fresh setup. Do not import over an existing database or replay migrations. Migration 011 remains excluded.

Start your local Apache/MySQL as usual. Open /udm-radar/login.php only if your clone is actually served at that folder; otherwise use your own served folder URL. A remote Git branch does not expose Joshua's localhost or copy his database. Use your own existing accounts, or the documented synthetic accounts if you separately imported the canonical seed.

For the latest candidate on Joshua's machine, use `http://localhost/udm-radar/login.php`. It currently selects the verified owner dataset in `udm_radar`: 291 Student profiles, 12,552 grades and 2,358 valid Student predictions in 18 canonical tables. See [owner dataset setup](OWNER_DATASET_SETUP_REPORT.md) for backups, compatibility mappings and excluded records retained in the review database.

Original owner-supplied account identifiers, names and password hashes were preserved. The user declined resetting `23-22-016`; use its existing password if known, otherwise leave original-password verification pending. Do not assume the earlier public demo password applies. The separate sanitized `002_synthetic_full_dataset.sql` uses a different account namespace and is not the data currently installed in normal localhost. Database distribution/import requires its own explicit decision; do not transfer raw owner backups in the application handoff.

## Retest checklist

- Dashboard: displayed estimate and risk agree; tied generation times use latest prediction ID.
- Calculator: target-generated grades locked, Reset clears scenarios, recorded grades preserved; Close/reopen starts clean.
- Grades: first/last risk tooltips fully visible, keyboard focus and Escape work.
- Performance Trend: completed semesters, current progress and estimated result remain distinct; missing finals remain Pending; exact table equals chart. Verify two-decimal Y ticks, close outlined value labels, estimate below its diamond, separate honors-value column, spaced/wrapped headers, optional honors guides and the adaptive axis that includes lower values.
- Eligible completed-term change: point delta first, supporting relative percent second. Example 2.25 to 2.50 = +0.25 points and +11.11%; no percent for invalid or noncomparable baselines.
- Both themes; desktop sizes 1440x900,1280x600,1024x768. Check keyboard/details, enlarged text and no page overflow.
- Enrollment Planning: own records and pending-grade state; foreign-ID request rejected. Ordinary clicks/Space select subjects, the count updates, the 12-subject limit holds and deselecting permits a replacement. PDF preview/download is set aside and NOT certified by these checks.

For each manual item, record PASS / FAIL / BLOCKED, tested account/role, served URL, current code fingerprint, expected result and actual result. Supply reproducible steps for a failure; do not include password hashes, session cookies or raw private records. A pending credential check is not a failed login and must not be marked passed without testing.

## Evidence and limits

Latest combined run: **550 PHP checks**, including **29 trend model/source checks**, **16 calculator JavaScript tests**, **25 authenticated browser tests with zero failures/skips/flaky**, and **14 syntax checks** passed. This run covers the current chart/model candidate. The older runtime-stall/pending-final-percent statements are superseded.

See [current regression report](JOSHUA_STUDENT_REGRESSION_REPORT.md) for actual commands, coverage, the first-run keyboard-test correction and limitations; [Member 2 roadmap](JOSHUA_MEMBER2_ROADMAP.md) tracks remaining work. Codex implementer verification replaced the external named-review workflow by user instruction; no independent reviewer or owner acceptance is claimed. Detailed runtime logs and application fingerprints are ignored/local under `logs/student-regression/` and `logs/student-trend/`.

Both regression databases were newly generated and token-owned, their data fingerprints stayed unchanged, and deletion was verified after each run. The installed owner database was not used for automated mutations. Static generated-fragment/model assertions are distinguished from authenticated application checks. Focused mobile chart verification exists separately; it is not part of the 25-test combined desktop count.

Automated browser runner, if you elect to run it in your environment: npm ci --ignore-scripts --no-audit --no-fund installs pinned Playwright 1.63.0. Installed Edge, PHP, MySQL and explicit approval to create/delete owned disposable databases are required. Cache the page's actual Chart.js asset first:

```powershell
New-Item -ItemType Directory -Path logs/student-trend -Force
Invoke-WebRequest https://cdn.jsdelivr.net/npm/chart.js -OutFile logs/student-trend/chart.umd.js
node tests/fixtures/run_student_trend.mjs
```

The runner uses token-owned unique test databases, never an existing udm_radar or udm_radar_scratch, and verifies cleanup. No screenshots/traces/videos. PDF/native print testing and final owner acceptance remain outstanding. No PR/merge/deploy is authorized by this handoff.

## Scope of the next publication review — no action authorized now

Application changes to review: `student/trend.php`, `student/performance_trend_model.php`, `student/enrolment_planning.php`, `assets/js/student-performance-trend.js`, `assets/css/student-performance-trend.css`, `assets/js/pef-cc.js`, `assets/css/pef-cc.css`. Regression changes: `tests/browser/student-performance-trend.spec.js`, `tests/unit/student_performance_trend_model_test.php`. Also include `assets/js/academic-analytics.js` and `assets/css/academic-analytics.css`: these are shared assets with Student-only guards for this refinement. Relevant setup/progress/roadmap/regression/handoff reports accompany the reviewed scope. See [publication preflight](JOSHUA_PUBLICATION_PREFLIGHT.md) for the complete proposed inclusion/exclusion list.

Exclude `.a`, superseded proposed-diff files, raw owner SQL backups/private data exports, local credentials/configuration, temporary server state, generated logs, sessions and dependencies. Inclusion of the separately sanitized large seed and its reports needs an explicit handoff decision; it must not be confused with the currently installed owner dataset. Preserve all unrelated local work.

Before any later authorized publication, recheck candidate fingerprints and Git diff, confirm no private material is staged, record the approved commit SHA, and verify the remote branch after pushing. Feature-branch publication does not authorize a PR, merge or deployment.

## Remaining owner decisions/checks

1. Original-password login remains deliberately pending; the user chose to preserve the password.
2. Owner manual desktop retest and acceptance of this specific local candidate remain pending. Codex cannot provide the owner's acceptance on their behalf.
3. Manual accessibility checks beyond executed keyboard/geometry coverage remain documented limitations.
4. PDF workflow stays set aside until the approved private assets and its separate verification are available.
5. Only reviewed Student feature-branch commit/push is authorized for this delivery. PR/merge/deploy, a full Academic Review redesign and broader Dashboard proposals remain separate scope.

## Latest browser verification — 29 October 2026

Complete Student browser suite rerun after the Academic Review spacing refinement: 25 passed, 0 failed/skipped/flaky, exit 0. Fixture data unchanged, disposable database deletion verified and canonical schema unchanged. Supplementary fingerprints include the current shared analytics JS/CSS. See JOSHUA_STUDENT_REGRESSION_REPORT.md for this latest checkpoint. This closes the pending full-suite rerun for that refinement; it does not authorize commit/push or replace owner acceptance.
