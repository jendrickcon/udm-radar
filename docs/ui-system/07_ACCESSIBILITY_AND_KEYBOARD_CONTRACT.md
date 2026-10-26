# Accessibility and keyboard contract
Status: PLANNED; reference-only tests do not establish portal accessibility or WCAG conformance.

1. Sidebar toggle is a native button with a clear accessible name, aria-controls pointing at the sidebar and truthful aria-expanded. It retains focus through a collapse/expand transition.
2. Expanded links show icon and text; collapsed links retain an accessible name and a pointer/keyboard discoverable supplementary label. Never hide a focusable label with aria-hidden while leaving its child button tabbable. The reference visually clips plain label spans, not controls.
3. Active route has aria-current=page or equivalent semantic indication plus non-color visual cue. Theme, display preferences and logout controls remain usable on the rail.
4. Desktop state is separate from the current <=900px navigation drawer. Future mobile behavior retains existing drawer semantics; this package does not change or test it.
5. Every filter has a unique name/visible label; curriculum year and Student profile year differ. Advanced disclosure exposes expanded state and active count. Its hidden fields are not in tab order.
6. If tabs are used, synchronize role, aria-selected, tabindex and controlled panels; Arrow/Home/End follow the widget's activation pattern. Native sections/disclosures are also acceptable; do not attach invalid tab roles to arbitrary links.
7. Async changes announce Updating/updated/failure via status/alert and aria-busy; retain focus and latest selected scope. Aborted stale requests must not overwrite values or announce false errors.
8. Chart labels, units, periods, states and all numeric values have a text/table equivalent from the same validated model. Missing values remain unavailable; no smoothing through gaps.
9. Visible legend distinguishes the active own-record series from the blocked cohort explanation by words and line pattern. Do not render a disabled button that promises a usable comparison.
10. Tooltip is supplemental; keyboard users can read equivalent values in the table. Do not require hover for privacy, non-ranking, completeness or model limitations.
11. Plot/canvas has a concise accessible name/description. Table has caption, column headers and term row headers. Empty chart displays an explicit message, not a blank canvas with axes alone.
12. Focus-visible works in light/dark and enlarged text; neither table clip nor sticky header obscures focus. Horizontal scrolling belongs only to named table containers.
13. Reduced motion makes layout changes immediate; chart resize still happens once settled. No decorative bounce/stagger or fixed-height blank region.
14. Modal close and Escape restore focus; a late opening transition cannot steal focus after close. Use existing Admin Grades regression.
15. Review desktop 1440×900, 1024×768 and 1280×600 in light/dark; enlarged text and reduced motion for affected controls. No mobile tests or screenshots under current owner direction.

## Required future evidence
Vincent records actual Administrator/Faculty keyboard, focus, geometry, role-negative and theme checks against his immutable candidate; Member 2 does the same for Student. Both run affected cross-portal regressions. Cross-review supplements this self-verification. Classify authenticated, generated, mocked, source and accessibility evidence separately. An inherited class name or syntax-valid reference is not a pass.

All theme surfaces must be checked separately for each owned portal, including chart axes/tooltips/legends and details. Keep exact accessible tables below plots and one primary page scroll owner; extended mechanics may use keyboard-accessible disclosures while critical limits and sample size stay visible.
