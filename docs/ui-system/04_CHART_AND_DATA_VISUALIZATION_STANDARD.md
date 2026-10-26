# Chart and visualization standard
Status: PLANNED; reference adapter is not production.
Vincent owns chart/legend/card/tooltip layout with the rest of UI; Member 2 verifies data meaning, APIs and results.

## Meaning first
Every chart names metric, unit, population, term order, eligible count/coverage and availability. Distinguish percentage term scores, final point grades, GWA, stored numerical estimate and rule-derived advisory. A chart never establishes official standing, pacing, benefits, teaching rank or causal support effects.
Use “Prototype performance on synthetic academic records”. Missing/no records is not0; valid zero counts are retained. Weighted recorded-grade pools and arithmetic Student GWA means are different metrics; do not relabel to make a new legend fit.

## Encoding
- Recorded own GWA: solid line, visible legend “Your recorded GWA”, distinct point marker. Gaps for unavailable terms; no line smoothing/interpolation through missing values.
- Cohort comparison is BLOCKED; show the exact unavailable legend explanation and do not draw any averaged/fabricated/placeholder series.
- Numeric chart direction is higher-is-better; configured point scale0–4 ascending. GWA helper currently excludes zero grades; axis lower bound0 is not permission to invent a zero GWA.
- Keep terms chronological by start year and semester; summer identified separately. Missing full term gaps need explicit labels/availability, not generated numeric values.
- Labels and tooltip2-decimal points with metric/unit/status; point-change explanation uses canonical comparable complete terms, not arbitrary adjacent chart positions.
- A trend is recorded history, not forecast. Predictions and unavailable ranges are separate.

## Card, legend and fallback
Title and scope before plot; visible solid/dashed/disabled legend with words; concise interpretation; equivalent table/text from identical model; methodology disclosure for secondary rules. All values accessible without hover. Mark unavailable/provisional context explicitly.
No stacked decorative charts, unnecessary dual axes, filled regions implying unrecorded data, hidden n/coverage or color-only meaning. No export/new Chart.js installation/CDN here.
Existing portal uses Chart.js via host pages. Reference configuration is injected into an already available approved constructor; if absent, accessible table and explanatory message remain usable. No library is imported by reference files.
Use [states](06_COMPONENT_AND_STATE_PATTERNS.md) and [GWA contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md).

## Theme, layout and life cycle
Semantic tokens, text/dash/pattern distinction, responsive container and named table scroll. Sidebar settled event + coalesced ResizeObserver update chart dimensions; explicit cleanup. No fixed viewport-height dependency.
Future tests: three desktop widths/light-dark, legend/table parity, long labels, no rows, one term, missing/incomplete/summer, reduced motion, hidden-panel show/resize and repeated init/destroy. Reference-only Node checks do not replace rendered/authenticated cases.
