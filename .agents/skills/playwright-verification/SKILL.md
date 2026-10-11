---
name: playwright-verification
description: Select and run repository-pinned Playwright checks for UI changes, classify browser evidence, and report layout, accessibility, and authentication limits.
---

# Playwright Verification

Read `AGENTS.md`, the canonical roadmap and history, and `CONTRIBUTING.md`. This skill guides behavior; filesystem, shell, network, Git and database permissions are enforced by sandbox, approval, host and network policy, not by a skill.

## Verify the runner and side effects first

1. Inspect `package.json`, the active lockfile, local `node_modules/@playwright/test/package.json`, and `playwright.config.js`. Verify the installed version equals the repository pin and report the exact version used.
2. Use the installed repository-local runner directly. Do not use npx to auto-install another version. If the pinned runner is missing or mismatched, report Unavailable and the exact restoration requirement; dependency changes/downloads require owner authorization.
3. Confirm the configured browser channel/binary exists. Do not download browser binaries without authorization.
4. Inspect selected specs, fixtures, authentication, route handling, included server handlers and request side effects before execution. A GET, login, export or fixture can still write. For database-backed paths, inspect direct connections, subprocesses, overrides, writes/cleanup, locks and temporary files; prove every mutation targets `udm_radar_scratch`. Rollback does not prove read-only behavior. Never silently use the live database.
5. Verify reports, screenshots, traces and local credential files are ignored. Do not create or expose credentials merely to make tests run.

## Applicable execution

Discover specs in `tests/browser/`; do not assume fixed test totals. For `faculty/dashboard.php` or shared dashboard layout CSS changes, run Faculty layout regression first. Include it for sidebar changes that affect Faculty layout. Then run the feature-specific checks and complete applicable suite.

From the repository root, after the runner is verified:

```text
node node_modules/@playwright/test/cli.js test --config=playwright.config.js tests/browser/faculty-dashboard-layout-regression.spec.js
node node_modules/@playwright/test/cli.js test --config=playwright.config.js
```

Select existing feature specs by their actual paths. Record each command and exit code. For a documentation-only package, report UI runtime checks Not Run with the reason rather than inventing browser coverage.

Use the package's approved theme and viewport matrix. Common coverage includes 1440x900, 1280x720, 1024x768, 768x1024, 390x844, 360x800, 390x700 and 844x390 in light/dark themes. Confirm relevant keyboard behavior, accessible labels, mobile touch targets, console/page errors and unexpected failed requests. Do not silently change the approved acceptance matrix.

## Evidence and failures

Classify each check as:
- Live authenticated application: actual application login/session and real handlers. Injected sessions do not prove the login workflow.
- Generated static HTML: extracted markup or component fixtures.
- Mocked browser: mocked requests, responses or chart dependencies.
- Repository source inspection: file, structure or AST checks; never label these end-to-end tests.

Report Passed, Failed, Skipped, Not Run and Unavailable as applicable, with actual discovered totals, credential-dependent skips and their reasons. Do not precheck claims without evidence. Passing static or mocked tests do not establish unavailable authenticated coverage.

Do not weaken assertions, change configuration or hide diagnostics to mask failures. Preserve the failure and investigate within the approved package scope. Keep generated artifacts ignored; never stage them as part of a diagnostic run.

This skill must not automatically stage, commit, push, merge, force-push, alter branches, restore files or suppress failures. Recovery is advisory and requires exact-diff review and owner authorization before overwriting or discarding work.
