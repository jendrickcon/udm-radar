# Protected Invariants Check Workflow (`/check-protected`)

Read-only inspection. Read `AGENTS.md`, the canonical roadmap/history and `CONTRIBUTING.md`. Skills/workflows guide behavior, not filesystem, shell, network, Git or database enforcement; actual enforcement comes from sandbox, approval, host and network policy.

## 1. Inspect every change layer

```text
git diff --name-status origin/main...HEAD
git diff --name-status
git diff --cached --name-status
git ls-files --others --exclude-standard
git status --short
```

Review the full diffs and untracked contents against the approved package allowlist. For each protected asset inspect committed, unstaged and staged changes separately:

```text
git diff origin/main...HEAD -- database/udm_radar.sql python_ml/model.pkl python_ml/model_metrics.json
git diff -- database/udm_radar.sql python_ml/model.pkl python_ml/model_metrics.json
git diff --cached -- database/udm_radar.sql python_ml/model.pkl python_ml/model_metrics.json
```

If a protected change lacks explicit package authorization, report the exact file and diff, preserve local work and stop the affected action. Do not offer an automatic rollback command. Recovery advice requires review of the exact proposed diff and owner authorization before any operation that could discard or overwrite work.

## 2. Faculty and academic contracts

Inspect relevant changes for the Faculty warning-banner closure before the Advisory, KPI/section grids, active roster and Top Performers. If dashboard/shared layout changed, require the corresponding layout regression; sidebar layout effects also need review. Source structure inspection and browser execution are separate evidence.

Verify subject outcomes against current shared helpers and independent GWA, risk and honors contracts. INC is unresolved, numeric 1.00–4.00 passes and zero fails; do not infer subject failure from High Risk. Preserve GWA/zero treatment and deferred textual-status policy. Do not execute model training, prediction batches or academic mutations merely to inspect invariants.

## 3. Schema, dependencies and secrets

Only inspect schema when relevant to the package. The documented baseline is 18 application base tables excluding temporary backup tables; a changed count needs migration evidence, not an automatic declaration of corruption. Report Not Run or Unavailable if no authorized target/inspection is available; never preconfirm the count.

Check dependency declarations/lockfiles, credentials, reports, screenshots, traces, environment files, unauthorized dumps, certificates and keys in all change layers. Confirm generated artifacts remain ignored. Do not print credential values. Do not modify protected files or create test credentials to exercise this diagnostic workflow.

Before any database-backed verification, inspect direct connections, handlers, subprocesses, overrides, writes/cleanup, locks and temporary files and prove every mutation path targets `udm_radar_scratch`. Rollback does not prove a test is read-only. Browser verification must use the installed repository-pinned runner after inspecting authentication/request side effects; report the version and never allow npx auto-installation.

## 4. Report observed results

For each applicable invariant, record the evidence and status: Passed, Failed, Skipped, Not Run or Unavailable. Include protected assets, layout, academic contracts, schema, dependencies and secret/artifact containment. Do not precheck claims, suppress discrepancies, invent test totals or label source inspection end-to-end verification.

This workflow must not automatically stage, commit, push, merge, force-push, alter branches, restore files or suppress failures. Keep recovery advisory and obtain owner authorization after exact-diff review. Explicit delivery authorization is handled outside this read-only workflow.
