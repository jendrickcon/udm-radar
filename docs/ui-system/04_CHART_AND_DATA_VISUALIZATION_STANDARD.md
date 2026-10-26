# Chart and visualization standard
Status: PLANNED; reference adapter is not production.
Vincent owns Administrator/Faculty chart, legend, card and tooltip work and its self-verification; Member 2 owns Student presentation and calculations/privacy plus its self-verification. Shared files have one assigned integration custodian.

## Meaning first
Every chart names metric, unit, population, term order, eligible count/coverage and availability. Distinguish percentage term scores, final point grades, GWA, stored numerical estimate and rule-derived advisory. A chart never establishes official standing, pacing, benefits, teaching rank or causal support effects.
Use “Prototype performance on synthetic academic records”. Missing/no records is not0; valid zero counts are retained. Weighted recorded-grade pools and arithmetic Student GWA means are different metrics; do not relabel to make a new legend fit.

## Encoding
- Recorded own GWA: solid line only for at least two valid ordered history points; a one-period value uses a scalar bar/dot. Visible legend “Your recorded GWA”, distinct point marker. Gaps for unavailable terms; no line smoothing/interpolation through missing values.
- Cohort comparison is BLOCKED; show the exact unavailable legend explanation and do not draw any averaged/fabricated/placeholder series.
- Numeric chart direction is higher-is-better; configured point scale0–4 ascending. GWA helper currently excludes zero grades; axis lower bound0 is not permission to invent a zero GWA.
- Keep terms chronological by start year and semester; summer identified separately. Missing full term gaps need explicit labels/availability, not generated numeric values.
- Labels and tooltip2-decimal points with metric/unit/status; point-change explanation uses canonical comparable complete terms, not arbitrary adjacent chart positions.
- A trend is recorded history, not forecast. Predictions and unavailable ranges are separate.

## Card, legend and fallback
Title and scope before plot; visible solid/dashed/disabled legend with words; concise interpretation; exact accessible table below the visualization and text from the identical model; methodology disclosure for secondary rules. All values accessible without hover. Mark unavailable/provisional context explicitly.
No stacked decorative charts, unnecessary dual axes, filled regions implying unrecorded data, hidden n/coverage or color-only meaning. No export/new Chart.js installation/CDN here.
Existing portal uses Chart.js via host pages. Reference configuration is injected into an already available approved constructor; if absent, accessible table and explanatory message remain usable. No library is imported by reference files.
Use [states](06_COMPONENT_AND_STATE_PATTERNS.md) and [GWA contract](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md).

## Theme, layout and life cycle
Semantic tokens, text/dash/pattern distinction, responsive container and named table scroll. Sidebar settled event + coalesced ResizeObserver update chart dimensions; explicit cleanup. No fixed viewport-height dependency.
Future tests: three desktop widths/light-dark, legend/table parity, long labels, no rows, one term, missing/incomplete/summer, reduced motion, hidden-panel show/resize and repeated init/destroy. Reference-only Node checks do not replace rendered/authenticated cases.

## Comparison, change and support contracts

Two selected comparison groups by default; optional third, no fourth in the MVP. Overlay or separate-card modes; school year/semester/curriculum year/subject/section/verified Faculty assignment. Mixed-scope contextual cards may coexist, but direct deltas require comparable units, populations, formulas and periods.

Comparable growth/change is core: exact start/end periods, approved point and supporting relative changes, coverage and eligible-sample changes, neutral direction. A zero/invalid baseline or incompatible evidence shows “Change unavailable” with reason, never a misleading difference.

Visual-first chart/details may use about two-thirds plot and one-third detail on wide desktops, with exact table below. Keep critical n/coverage/limitations visible, extended policy/formulas in accessible disclosures. Historical axes use actual school year and semester, full names in tooltips/table, no ordinal-only periods.

Support cards visualize case/referral status and referral → notice → receipt flow. “Average time before the first notice” preserves MTR-20 arithmetic mean and displays human-readable duration with actual eligible n/small-sample explanation. MTR-19 stays bounded count; limited parent-case median is not canonical MTR-21. Counts do not prove effectiveness; drill-down remains authorized and read-only.

Verify all chart surfaces in light/dark in each portal; no pure-white dark card. One primary page scroll owner; no page horizontal overflow. This documents future behavior only.
