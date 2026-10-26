# Visual direction and boundaries
Status: PLANNED; no production design approval is implied by reference syntax checks.

## Reference interpretation
The master's description of two owner-supplied dashboard images inspires compact organized density, a retractable left sidebar, more chart room, summary-first hierarchy, clear comparison legends and coherent light/dark surfaces. The actual image files are not part of this preparation input, so no claim of pixel comparison or visual matching is made.

Adapt those principles to UDM-RADAR:
- Keep institutional identity, existing logo, semantic colors and role-specific workflows. No copied logos, branding, proprietary artwork or exact reference palette/layout.
- Use current shared dashboard tokens rather than transplanting an unrelated UI kit.
- Prefer a compact summary plus deeper integrated detail over a wall of repeated cards and filters.
- Improve chart prominence by releasing sidebar space, not by hiding labels, limitations or accessible tables.
- Keep Faculty review queues and Student-safe explanations visible. Administrative oversight, Faculty assigned review and Student self-awareness are distinct roles.
- Preserve current active navigation, text-size/theme/motion preferences and authorized deep routes.
- Figures describe synthetic prototype records. Always show “Prototype performance on synthetic academic records”.
- Comparison provides context, never teaching rank, Student rank, official standing, benefits, pacing or causal intervention effectiveness.

## Concrete adaptation choices
The canonical expanded sidebar is 280px, not the 240px legacy stylesheet. A proposed 80px reference rail follows a 44px control plus 18px sides; it is a review proposal rather than a shipped dimension. Existing 40px content gutters may become the audited 20–40px compact/comfortable range under owner-approved implementation.
Legend identifies a solid own-record line and a disabled comparison explanation using text/dash or shape, not color alone. No unavailable average values are drawn.
The wider plot keeps the configured 0–4 point axis and chronological term ordering. Dense cards retain readable typography and concise scope. Do not use decorative 3D, fabricated trend area, arbitrary risk colors or a forecast line described as recorded GWA.

## Approach and ownership
The owner chose unified UI ownership: Vincent designs and adapts sidebar, layouts, filters, charts, themes and accessibility as one package. Member 2 owns technical validation/data contracts and independent testing; do not split the shared UI files between people.
The alternative sidebar/chart member split is superseded and must not be used. The cohort comparison is BLOCKED until Member 2 verifies the contract and owner approves; a live comparison calculated from administrative pooled means is rejected.

## Non-goals
No live sidebar or chart, production CSS/JS import, new library/CDN, chart export, prediction range, Faculty ranking, formula rewrite, privacy threshold invention, migration or DB access. This package is an implementation framework for later review, not production implementation.
