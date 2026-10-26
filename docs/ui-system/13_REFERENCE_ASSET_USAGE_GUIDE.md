# Reference asset usage guide
Status: REFERENCE ONLY; no production imports.
Start at [reference README](reference/README.md). All six recommended reference assets are inert until explicitly imported/initialized by a later authorized implementer.

| File | Purpose |
| --- | --- |
| reference/ui-tokens.reference.css | Scoped aliases to audited portal layout/theme tokens and marked design proposals |
| reference/collapsible-sidebar.reference.css | Scoped desktop expanded/rail layout illustration; no global reset |
| reference/collapsible-sidebar.reference.js | Explicit idempotent sidebar initializer, preference fallback, settled layout event and cleanup |
| reference/chart-card.reference.css | Scoped card/legend/table/empty-state presentation |
| reference/gwa-trend-chart.reference.js | Pure validated own-record model, optional blocked cohort metadata, Chart.js configuration via injected constructor and resize adapter |
| reference/reference-contract.test.mjs | Node-only identifier-free reference contract checks; not a production/browser acceptance suite |

## Adaptation checklist
1. Obtain approved UI implementation package/base and read the final accepted Antigravity integration, not an old checkpoint.
2. Vincent adapts Admin/Faculty UI and self-verifies; Member 2 adapts Student UI, verifies Student calculations/privacy and self-verifies. Global styles/tokens/JS need one assigned integration custodian, sequential edits and cross-portal regressions.
3. Map data attributes and scoped classes deliberately; preserve current navigation/accessibility-preferences system.
4. Supply recorded own data from an existing authorized service; no fetch/backend/DB is implemented by the reference.
5. Keep comparison status BLOCKED and pass no comparison numeric values. Data/privacy/threshold approval is a separate contract and future implementation.
6. Use host's existing Chart.js constructor if already approved/available; the adapter never imports or downloads Chart.js. On absence, keep text/table/empty-state explanation; no new library/CDN.
7. Build the visible text/table before plotting. The table helper derives cells from the identical validated model; DOM rendering must use safe text, no unsanitized HTML.
8. Mount with one lifecycle owner, call destroy on replacement/unload, and reconnect charts to the settled layout/ResizeObserver hook. No repeated global listeners.
9. Run focused self-verification and supplemental cross-review before any authorized live import; record evidence and unresolved states. Syntax/reference tests are not production rendering/accessibility acceptance.

## Excluded
No production header/sidebar/script/style references to docs/ui-system/reference; no API, server, authentication, local-secret, DB, model, migration or dependency operation.
Local reference CSS/JS syntax and Node contract checks are authorized preparation validation. Production browser implementation tests are NOT RUN because no production implementation is authorized.

The GWA reference selects a scalar bar for a single valid recorded value and a line only for at least two valid chronological points; empty data constructs no chart. Tables remain below the visual. Optional future two/three-group controls, wide chart/details layout and support cards are documented contracts, not implemented reference or production features.
