# Design tokens and theme contract
Classification: **canonical pattern** = current shared source; **accepted variation** = scoped existing component; **legacy variation** = older parallel styles; **future consolidation candidate** = proposal, not shipped or institutionally approved.
Audited baseline edef1b382b7874af770feb5713c20e9b68979241. Source references below are repository paths, not a new import graph.

## Layout and typography audit
| Role | Current evidence | Future reference proposal / constraint | Class |
| --- | --- | --- | --- |
| Expanded sidebar | assets/css/dashboard.css:223 width 280px; nav 14px inset, links 10px 16px, 20px icon | --ui-ref-sidebar-expanded 280px | Canonical |
| Legacy sidebar | assets/css/style.css uses 240px | Do not use as current shared width or consolidate it here | Legacy |
| Collapsed rail | No current desktop collapsed rail | --ui-ref-sidebar-collapsed 80px proposed = 44px target + 18px each side; owner review required | Future candidate |
| Content gutter | dashboard.css:249 40px; <=900px current drawer content 20px | Proposed compact/comfortable 20–40px; use 40px default,20px compact | Canonical values / proposed density |
| Card and section gap | stat-grid gap20px; card/section margin24px | 20px card gap,24px section gap | Canonical |
| Chart width | New recorded analytics max-width1500px; legacy 2-column charts collapse near1200px; fixed-height legacy cards380px | Readable chart container 100% up to existing1500px, table text available; no fixed viewport height dependency | Accepted variation / proposed bound |
| Table overflow | data-table-scroll, min-width tables, page min-width0 | Horizontal scroll per named table, never entire page | Canonical |
| Page title / KPI | dashboard.css:252,266 2rem; Inter font from header | 2rem page/value, inherits existing text-size scaling | Canonical |
| Section/card | table-title1.2rem; analytics h2 1.2rem,h3 1rem | 1.2rem section,1rem card | Accepted variation |
| Body/helper | header p .95rem; stat label .85rem; metadata .75/.76rem | Body .95–1rem, helper/chart/table label .875–.95rem; compact metadata .875rem proposed for readability | Canonical evidence / candidate |
| Card geometry | card radius12px,padding24px; stat padding20px; select radius6px | Reuse shared geometry; no duplicate reset | Canonical |
| Shadows | --shadow-sm shared light/dark variants | Alias semantic shadow, no chart glow | Canonical |

Desktop breakpoint is >900px; existing media bands include <=1280, <=1100, <=1024 and <=900 drawer. References scope collapse to min-width901px. Future desktop tests cover1440×900,1024×768,1280×600. Existing smaller-device behavior is documented only, not altered/tested.

## Semantic roles
Reference tokens are scoped under .ui-ref-shell, .ui-ref-theme, .ui-ref-chart-card; they alias current variables first. Standalone fallback values come from existing CCS light/dark definitions in dashboard.css:65–117, not the inspiration images.
| Role / reference token | Current semantic source |
| --- | --- |
| surface / --ui-ref-surface | --bg-color |
| elevated card / --ui-ref-card | --card-bg |
| border / --ui-ref-border | --border-color |
| primary / --ui-ref-accent | --accent-blue |
| secondary / --ui-ref-accent-secondary | --secondary |
| success / --ui-ref-success | --risk-low |
| caution / --ui-ref-caution | --risk-mod |
| risk / --ui-ref-risk | --risk-high |
| unavailable / --ui-ref-unavailable | --text-gray; text label required, not only gray |
| primary text / --ui-ref-text | --text-dark |
| secondary text / --ui-ref-text-muted | --text-gray |
| focus / --ui-ref-focus | --accent-blue with visible outline |
| current line / --ui-ref-chart-current | primary accent, solid stroke + label |
| comparison / --ui-ref-chart-comparison | secondary accent, dashed stroke + label, not active under current blocked contract |
| average / --ui-ref-chart-average | secondary alias reserved for future approved aggregate; not evidence of implemented cohort |
| incomplete / --ui-ref-chart-insufficient | unavailable token + explicit status, gap marker/table text |

Classic/CCS overrides and html data-theme/data-font-size/data-motion are owned by shared header/preferences. Do not overwrite global variables from reference CSS. Chart colors are supplied from computed reference tokens on remount/theme update; preserve table meaning if Chart.js unavailable.

## Motion
Some current shared transitions are200–300ms legacy variation; governance requires brief150–200ms transitions. Reference proposal: sidebar180ms ease-out capped at200ms, disclosure/tab150ms, busy indicator immediate. On reduced motion use0ms, no decorative chart animation, still emit one settled resize notification. Timing values are engineering proposals within the governance limit, not approval for new production motion.
Vincent adapts Administrator/Faculty presentation and self-verifies it; Member 2 does the same for Student. Global design tokens/sidebar/chart assets require one owner-assigned integration custodian per exact shared file and sequential edits. Each checks the other portals affected by shared changes.

## Preparation verification boundary
Reference syntax can be checked; future production contrast/layout/keyboard verification remains NOT STARTED until authorized adaptation and actual desktop evidence. Existing typography/token overlap is a future consolidation candidate, not a task to rewrite production CSS now.
