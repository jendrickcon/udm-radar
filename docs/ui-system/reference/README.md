REFERENCE ONLY. NOT LOADED BY PRODUCTION PAGES.
Future implementers must adapt this file to the active UDM-RADAR layout and tests.

# Reference-only assets
These examples require explicit host adaptation. They are not an application, production import or permission to enable a cohort average. All shared presentation adaptation belongs to Vincent; Member 2 verifies technical/data/privacy contracts.

- [Tokens](ui-tokens.reference.css): scoped semantic aliases and proposed dimensions.
- [Sidebar CSS](collapsible-sidebar.reference.css), [controller](collapsible-sidebar.reference.js): explicit desktop root lifecycle.
- [Chart card CSS](chart-card.reference.css), [chart model/configuration](gwa-trend-chart.reference.js): own-recorded line, blocked optional comparison and table-equivalent model.
- [Node contract tests](reference-contract.test.mjs): identifier-free reference validation only.

## Explicit APIs
Sidebar: initReferenceSidebar(root,options) returns setCollapsed/destroy. Root data-ui-ref-shell, sidebar data-ui-ref-sidebar with ID, toggle data-ui-ref-toggle, content data-ui-ref-content. Icon-only links need stable accessible names; plain .ui-ref-nav-label may be visually clipped.
Event udmradar:reference-layout-change, detail {collapsed,phase:'settled'}, bubbles on root after transition.
Chart: validateRecordedGwaModel(input), buildGwaChartReference(input,options), attachReferenceChartResize(root,container,resize), mountReferenceChart({Chart,canvas,model,...}) return explicit cleanup hooks. No auto-init or fetch, no global Chart dependency assumed. Inject an already host-approved constructor.
Legend and accessible table text come from the validated model; host DOM writes textContent only. A disabled comparison entry is visible explanatory text, not an operable average toggle. Chart.js configuration disables its canvas-only legend in favor of the visible equivalent.

## Blocked comparison
“Your recorded GWA” may have authorized complete own-term points.
“Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.” stays **BLOCKED**. No numeric cohort input or enabled flag is accepted. Optional disabled dataset contains no numbers. Member 2 must verify the eleven technical/privacy items and owner approve before a separately versioned future implementation.

## Non-production checks
Run from repository root:
`node --check docs/ui-system/reference/collapsible-sidebar.reference.js`
`node --check docs/ui-system/reference/gwa-trend-chart.reference.js`
`node --test docs/ui-system/reference/reference-contract.test.mjs`
The repository does not declare type:module; tests load each standalone reference file as a data: ES module without modifying package.json. No third-party library, browser, network, DB or production handler runs.
CSS syntax validation may use an already installed parser; don't install a library merely to validate this prep. Static parsing cannot prove actual contrast/layout/keyboard rendering.

## Markup contract illustration
This is documentation, not a live component. Vincent adapts the markup to the accepted portal; Member 2 verifies the supplied data. Fill no numeric cohort cells.

```html
<section class="ui-ref-chart-card" aria-labelledby="own-gwa-heading">
  <h2 id="own-gwa-heading" class="ui-ref-chart-title">Recorded GWA by term</h2>
  <p class="ui-ref-chart-context">Prototype performance on synthetic academic records</p>
  <ul class="ui-ref-chart-legend" aria-label="Series legend">
    <li><span class="ui-ref-series-key" aria-hidden="true"></span>Your recorded GWA</li>
    <li><span class="ui-ref-series-key ui-ref-series-key--blocked" aria-hidden="true"></span>Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.</li>
  </ul>
  <p role="status">No complete recorded GWA is available for the selected terms.</p>
  <div class="ui-ref-chart-table-scroll" role="region" aria-label="Recorded GWA table" tabindex="0">
    <table>
      <caption>Own recorded term evidence; comparison contract BLOCKED</caption>
      <thead><tr><th scope="col">Term</th><th scope="col">Your recorded GWA</th><th scope="col">Availability</th><th scope="col">Comparison</th></tr></thead>
      <tbody><tr><td colspan="4">No complete recorded GWA is available for the selected terms.</td></tr></tbody>
    </table>
  </div>
</section>
```

When authorized own records exist, table term cells become row headers and values come only from tableRows; create the plot after the visible equivalent. Do not hide the entire table if Chart.js is absent. For supplemental rail tooltips, implement Escape dismissal in the eventual host; the CSS illustration alone is not a keyboard-tooltip implementation.
