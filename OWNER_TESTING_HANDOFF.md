# Owner local testing handoff

Branch: feat/member2-student-portal
Baseline: 60a6f7614b594cd8759a3c9211e060c3b8d7ab62
Purpose: owner testing of the local Student candidate. This is not merge, deployment or final acceptance.

## Get the candidate

Check/preserve your existing local work before switching branches. In your own clone:

```powershell
git status --short --branch
git fetch origin
git switch --track origin/feat/member2-student-portal
```

If that local branch already exists, switch to it and update with git pull --ff-only after checking local changes. Do not overwrite local credentials, private templates or existing databases. No database dump, credentials, .a, vendor/node_modules or test logs are included. Your existing schema-compatible local database/configuration is required; use database/README.md for a separately authorized fresh setup. Do not import over an existing database or replay migrations. Migration 011 remains excluded.

Start your local Apache/MySQL as usual. Open /udm-radar/login.php only if your clone is actually served at that folder; otherwise use your own served folder URL. A remote Git branch does not expose Joshua's localhost or copy his database. Use your own existing accounts, or the documented synthetic accounts if you separately imported the canonical seed.

## Retest checklist

- Dashboard: displayed estimate and risk agree; tied generation times use latest prediction ID.
- Calculator: target-generated grades locked, Reset clears scenarios, recorded grades preserved; Close/reopen starts clean.
- Grades: first/last risk tooltips fully visible, keyboard focus and Escape work.
- Performance Trend: history/current snapshots/stored estimate remain distinct; missing finals remain Pending; exact table equals chart.
- Eligible completed-term change: point delta first, supporting relative percent second. Example 2.25 to 2.50 = +0.25 points and +11.11%; no percent for invalid or noncomparable baselines.
- Both themes; desktop sizes 1440x900,1280x600,1024x768. Check keyboard/details, enlarged text and no page overflow.
- Enrollment Planning: own records and pending-grade state; foreign-ID request rejected. PDF preview/download is NOT certified by these checks.

## Evidence and limits

Latest model checks: 27 passed (includes supporting percent). Previous combined authenticated suite: 25 passed, 0 skipped/failures before the final percent-text addition. That addition has not been rerun in a browser because the local MySQL runtime subsequently stalled. Do not treat historical browser fingerprints as a test of the final model bytes.

Earlier PHP regression checks: 521 passed. Calculator JavaScript checks: 16 passed. Exact detail and known limitations are in JOSHUA_PROGRESS_UPDATE.md and JOSHUA_CODEX_REVIEW.md. Codex implementer verification replaced the external named-review workflow by user instruction; no independent reviewer acceptance is claimed. Original detailed runtime logs are ignored/local and not included.

Automated browser runner, if you elect to run it in your environment: npm ci --ignore-scripts --no-audit --no-fund installs pinned Playwright 1.63.0. Installed Edge, PHP, MySQL and explicit approval to create/delete owned disposable databases are required. Cache the page's actual Chart.js asset first:

```powershell
New-Item -ItemType Directory -Path logs/student-trend -Force
Invoke-WebRequest https://cdn.jsdelivr.net/npm/chart.js -OutFile logs/student-trend/chart.umd.js
node tests/fixtures/run_student_trend.mjs
```

The runner uses token-owned unique test databases, never an existing udm_radar or udm_radar_scratch, and verifies cleanup. No screenshots/traces/videos. PDF/native print testing and final owner acceptance remain outstanding. No PR/merge/deploy is authorized by this handoff.