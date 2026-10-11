# Package Verification Workflow (`/verify-package`)

Diagnostic and report-only. Read `AGENTS.md`, `docs/ROADMAP.md`, `docs/IMPLEMENTATION_HISTORY.md`, and `CONTRIBUTING.md` first. Guidance does not enforce filesystem, shell, network, Git or database permissions; sandbox, approval, host and network policy provide enforcement.

## 1. Review all change layers

```text
git diff --name-status origin/main...HEAD
git diff --name-status
git diff --cached --name-status
git ls-files --others --exclude-standard
git status --short
```

Read each full diff and relevant untracked file. Check branch/base, scope and file allowlist. Include committed, unstaged, staged and untracked changes when selecting checks; a working-tree-only diff can miss committed runtime changes. Record unexpected files before proceeding. Do not repair the branch or discard work automatically.

## 2. Choose verification by impact

- Documentation/agent guidance: check Markdown, skill frontmatter, referenced paths, discovery and instruction consistency. Runtime tests may be Not Run with rationale.
- PHP: lint each changed/new PHP file from the complete reviewed union, then select applicable database-free regression scripts.
- Grading/storage/analytics: verify subject outcomes, numeric denominators, GWA/zero preservation and independent honors/risk behavior through applicable checks. Consult `academic-rules-guardian`.
- UI: inspect specs/fixtures and side effects, then use `playwright-verification` for layout, feature and complete applicable suites.

Do not execute every integration script merely because it exists. Discovery is not authorization to run it.

## 3. Database-backed safety gate

For each selected test, inspect direct connections, required/included configuration and handlers, subprocesses, database overrides, setup/writes/cleanup, shared lock files and temporary-file operations. Prove every mutation path targets `udm_radar_scratch`. Verify the selected database before any write. If a path can fall back to `udm_radar`, stop and report it; do not run that test. Rollback is cleanup, not proof that a test is read-only. Compare relevant before/after academic data or checksums when mutation fixtures are used; report audit writes honestly.

A relevant GWA parity audit may use the explicit target after its path is inspected:

```text
php tools/audit_gwa_parity.php --db=udm_radar_scratch
```

Never silently default to the live database.

## 4. Browser safety and execution

Verify the installed runner matches the package/lockfile pin, report its exact version and confirm the configured browser is available. Inspect authentication and request side effects before running. Do not allow npx automatic installation or download missing dependencies/browsers without authorization.

If Faculty dashboard/shared layout changed, run the Faculty layout regression first; include sidebar changes that affect it. Then run selected feature checks and the complete applicable suite using the installed local runner:

```text
node node_modules/@playwright/test/cli.js test --config=playwright.config.js tests/browser/faculty-dashboard-layout-regression.spec.js
node node_modules/@playwright/test/cli.js test --config=playwright.config.js
```

Keep reports, screenshots, traces and credential files ignored. Do not weaken tests or configuration to suppress active failures.

## 5. Record evidence

Record commands, targets/runtimes, exit codes, actual passed/failed/skipped totals and reasons. Use Passed, Failed, Skipped, Not Run or Unavailable; leave unexecuted claims unconfirmed. Classify browser evidence as live authenticated application, generated static HTML, mocked browser or repository source inspection. Do not call source inspection end-to-end testing or reuse historical counts as current acceptance evidence.

On failure, preserve and report the diagnostic output. Do not declare the package ready until its required checks and review gates are satisfied.

## Operating boundary

This workflow must not automatically stage, commit, push, merge, force-push, alter branches, restore files or suppress failures. Any recovery suggestion is advisory: preserve work, review the exact diff and proposed operation, and obtain owner authorization before overwriting or discarding files. Delivery belongs to a separately authorized action, not this diagnostic workflow.
