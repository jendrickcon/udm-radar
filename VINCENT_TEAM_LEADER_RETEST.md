# Vincent checkpoint: team-leader retest

Use branch `feat/member1-admin-faculty-ui`; the delivery message supplies its full verified SHA. Review the diff before running the checkout. Preserve any existing checkout changes and database. For a fresh local clone, follow [database onboarding](database/README.md): schema and synthetic seed are separate; do not overwrite a populated database, replay migrations or import Migration 011.

Use the documented synthetic Admin, Faculty01, Faculty02 and Student demo accounts from the onboarding/seed documentation. Do not use genuine records. This guide does not contain credentials.

- [ ] Admin: sidebar starts narrow, expands throughout hover, collapses on leave, and preserves active links/logout. Check keyboard and desktop toggle. Confirm the approved Faculty person-and-board icon and no white selection stripe.
- [ ] Admin and Faculty: switch light/dark and CCS/Classic, then check 1440×900, 1280×600 and 1024×768. Enlarge text. Confirm readable controls and no page horizontal overflow.
- [ ] Admin Program Analytics: switch every tab; apply a recorded filter, reload and confirm scope/tab persist. Clear filters. The Overview is explicitly a page-open student snapshot.
- [ ] Comparisons: two cards initially; add/remove the third; change selectors using the keyboard and retain focus. Unavailable cards must keep grouping/removal controls.
- [ ] Faculty: assigned-subject groups should appear immediately. Faculty01 sees its own referral; Faculty02 should not see Faculty01's referral or its Student details. Unassigned subjects/sections must expose no records.
- [ ] Support: follow an authorized case/referral link and confirm the intended record. Read the bounded receipt-count wording, mean first-notice duration/sample and the separately labeled parent median. No notice should be sent merely by viewing analytics.
- [ ] Admin Overview: open both “View chart data” disclosures and compare exact values with the charts. Check labels/tooltips in both themes. Chart-unavailable states must still provide exact data.
- [ ] Student: smoke-test the existing sidebar and navigation after these shared changes. Broader Joshua integration is on hold at the owner's request.
- [ ] Record defects with role, page, viewport/theme, steps and expected/actual behavior. Approve the checkpoint only after visible retest; this does not itself authorize merge/deployment.

Automated evidence and the remaining owner gates are in [the acceptance report](VINCENT_PHASE_C_ACCEPTANCE.md). For developer reproduction, run the explicit opt-in scripts/specs listed there with process-local synthetic credentials. Do not run the entire repository test suite blindly: other suites create databases or screenshots.
