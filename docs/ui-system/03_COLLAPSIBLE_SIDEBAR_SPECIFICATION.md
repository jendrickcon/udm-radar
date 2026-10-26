# Collapsible desktop sidebar specification
Status: PLANNED / NOT IMPLEMENTED in production.
UI owner: Vincent, unified sidebar/dashboard/chart package. Member 2 independently verifies behavior and role boundaries.

## State and structure
- Expanded: current280px rail with icon+visible label, brand, badges, theme/preferences/logout.
- Collapsed: proposed80px icon rail; active page retains aria-current and visual indicator. Every icon-only item retains an accessible name; labels may be visually clipped plain text, never a hidden focusable control.
- Toggle: native named button, aria-controls points at unique sidebar ID, aria-expanded truthful; focus remains on toggle through transitions. Use “Expand navigation”/“Collapse navigation”.
- Content: grid/flex releases rail width without absolute overlap. min-width0 prevents chart/table overflow; no fixed viewport height or hidden main content.
- On legacy <=900px drawer, reference collapse is inactive; existing drawer remains untouched. Mobile production work/tests require separate authority.
- Accessible footer controls remain reachable. Text badges need meaningful names without squeezing unreadable numerals into the icon.

## Preference and lifecycle
Recommend local browser key udmRadarReferenceSidebarCollapsedV1, storing only boolean preference, no userID/academic data. It is distinct from theme/settings and not a DB preference. Private mode/storage denial falls back to expanded in-memory operation. Invalid strings default expanded.
One initialization per root using WeakMap; remount returns existing controller. destroy removes listeners/timers and restores the original host class/attributes. Do not duplicate controllers per chart or alter another portal root.
On desktop-to-drawer crossing expand visually without discarding desired desktop preference; returning desktop restores preference. Native focus remains intact; collapsing must not clip a currently focused footer control.

## Reference DOM/API
The non-production root .ui-ref-shell[data-ui-ref-shell] contains [data-ui-ref-sidebar], [data-ui-ref-toggle] and [data-ui-ref-content]. Sidebar ID is explicit, not globally generated. Plain link label span .ui-ref-nav-label and icon .ui-ref-nav-icon; icon aria-hidden=true and link has stable aria-label.
`initReferenceSidebar(root,{storageKey,storage,desktopQuery})` returns `{setCollapsed(boolean),destroy()}`; no auto-init/global export.
Reference event: **udmradar:reference-layout-change**, dispatched on root, bubbles=true; detail **{collapsed:boolean,phase:'settled'}**.
No production selectors, imports, URL changes, authentication/database calls or global theme overrides.

## Resize integration
The host chart calls an explicit resize hook after transition settles. A root-owned ResizeObserver can detect released content width; debounce to one resize frame/short timer. Observer and event may fire together, so coalesce, do not invoke an unbounded loop. Use no-op if chart is gone/hidden, then refresh when shown. Destroy disconnects observers, listeners and timers on removal/pagehide. Resize callback errors must not break navigation.
No window resize handler per chart on every mount. See [reference guide](13_REFERENCE_ASSET_USAGE_GUIDE.md) and the reference adapter. Reference tests cover lifecycle separately; actual geometry/hidden-panel chart resizing remains future acceptance.

## Required future cases
Expanded/collapsed, repeat toggle, keyboard focus, route/theme persistence, denied storage, invalid storage, cross-breakpoint restore, active/badge/footer names, enlarged text, reduced motion, remount/destroy, all three desktop widths and both themes across Admin/Faculty/Student. Faculty warning-banner structural contract stays intact. No production check is marked passing now.
