# Progress report and handoff standard

Documentation: READY FOR OWNER REVIEW. Future member implementation: NOT STARTED.
Completed documentation/reference checks do not complete a production package.

## Progress and status

Use the [progress template](templates/UI_PROGRESS_UPDATE_TEMPLATE.md) for brief milestone updates with actual findings, blockers, next action and current branch/SHA. Keep completed, in-progress, planned, blocked and deferred work distinct. Cohort is BLOCKED, not DEFERRED. Both portal implementers self-verify; cross-review neither transfers files nor supplies missing self-tests.

The seven [roadmap statuses](12_IMPLEMENTATION_SEQUENCE_AND_ROADMAP.md) apply to phase/readiness fields. Test outcomes use Passed, Failed, Skipped, Not Run or Unavailable. Never infer an executed result from a source check, inherited class, checksum or an earlier agent's count.

## Separate evidence categories

For each run, record exact command/time/runtime/version/candidate or dirty-state fingerprint/target/exit code/actual passed-failed-skipped totals and evidence path. Preserve failures and distinguish required blocked evidence.

| Category | Establishes within actual scope | Limit |
| --- | --- | --- |
| Documentation/path/wording | Local links and contract consistency | No application behavior |
| Unit / standalone PHP regression checks | Asserted helper behavior | Not PHPUnit; state side effects |
| Integration | Exercised handlers/data on an authorized isolated target | Name target, provenance, mutations and cleanup; no implicit live fallback |
| Authenticated desktop browser | Tested role/page/scope/viewport | List untested roles/states and auth/data boundaries |
| Structural/source | Inspected markup/selectors/contracts | Not authenticated application coverage |
| Generated fixture browser | Static fixture interactions/geometry | Not live source-data or auth verification |
| Mocked transport/error/race | Controlled requests and stale-response behavior | Mock values do not validate database truth |
| Authorization negatives | Explicit permitted/denied cases | Name role, resource scope and server enforcement |
| Accessibility | Specific keyboard/focus/labels/contrast/table methods | No blanket conformance claim |
| Syntax / integrity | Parseability, hashes, change layers and allowed files | No functional accuracy claim |
| Owner retest | Acceptance of specified visible workflows/candidate/limitations | Not institutional UAT, merge or deployment |

Never combine these counts into an accuracy percentage or one quality total. Checksums prove identity only. No genuine records/credentials in evidence.

## Final owner handoff

Use the [handoff template](templates/UI_CHANGE_HANDOFF_TEMPLATE.md) after completion, or an explicitly requested blocked handoff. Include product goal/user problem, portal implementer and scope/non-goals, baseline/current-main/HEAD full SHAs, branch and absolute clone path/status, literal files/shared custodians, visual/calculation/growth/empty/unavailable contracts and actual self-verification/cross-portal results.

Review committed, unstaged, staged, untracked and combined changes against the approved allowlist. Record protected/model/dependency/auth/DB/migration/Migration 011/genuine-record checks and temporary cleanup. Supply immutable candidate only when an authorized commit exists; otherwise state no candidate and why. Owner retest/defect closure and each push/PR/merge/deployment approval must be separate.

Vincent: `feat/member1-admin-faculty-ui`, Administrator/Faculty implementation plus own testing/fixes. Joshua: `feat/member2-student-portal`, Student implementation/calculations/privacy plus own testing/fixes. One clone each; same-clone AI task branches from own assigned HEAD; additional worktrees only explicit exceptions. One custodian per exact shared file, sequential handoff. After approved merge both update clean main fast-forward; no ZIP/manual-source replacement.

Implement → self-verify → authorized local immutable candidate → handoff → owner retest/fixes → separately authorized push → one reviewed PR with auto-merge off → authorized merge → authorized deployment. No automatic cleanup or force-push.

Preserved readiness `648207bc563f2bb1aa280d08ab9699f07a4f39c7` is owner-accepted. This final reconciliation is authorized for a local documentation commit only; no push or PR. Record the new final immutable SHA separately; do not amend the preserved commit.

## Findings and decisions

Use the [findings template](templates/UI_REVIEW_FINDINGS_TEMPLATE.md) for exact SHA/file/function, expected/actual reproduction, impact, severity, regression, original implementer and blocker. A proposed fix is not resolved evidence. Author fixes stay self-reviewed until independently reviewed; cross-review does not authorize redesign or ownership transfer.

Use the [decision template](templates/OWNER_DECISION_TEMPLATE.md) for literal files, current/temporary custodian, scope/order, source SHAs, tests, explicit owner decision, remaining prohibitions and ownership-return conditions. Silence is not approval.

## Required continuity and limits

Retain “Prototype performance on synthetic academic records”. MTR-07, canonical MTR-21, historical Faculty attribution and numeric Student cohort remain BLOCKED. MTR-19 is a bounded receipt count, limited parent-case median is separate, and MTR-20 is the existing arithmetic mean with “Average time before the first notice”, actual eligible n and human-readable duration. No effectiveness/rank/official-standing inference.

Joshua owns the Student interface and eleven-item [cohort proposal](05_GWA_TREND_COMPARISON_CHART_CONTRACT.md); final owner approval is mandatory. Keep “Your recorded GWA” and “Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.” No pooled-mean substitution.

Include this directive verbatim in carried-forward report artifacts:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is historical, not a current measurement. No mobile tests or screenshots are authorized. Future desktop acceptance covers 1440×900, 1280×600 and 1024×768, light and dark.

Final successful preparation phrase: `FUTURE UI MEMBER AND AGENT READINESS PACKAGE PREPARED FOR OWNER REVIEW`. Explicitly requested blocked handoff phrase: `FUTURE UI READINESS PREPARATION BLOCKED`. Future implementation packages require their own owner-supplied phrases. Do not use preparation completion to imply production delivery.

## Required final reconciliation record

Every later handoff distinguishes accepted PR64 baseline bdb4a267f30aa31ea36983304a03b01dc28f0fcd from its own approved post-MVP changes. Record PR64 merged/local deployment (owner-confirmed), schema-only onboarding bootstrap, separate seed, database/README.md, Vincent/Admin-Faculty, Joshua/Student, own self-verification and all five BLOCKED contracts including Migration011.

The final reconciliation report must include current main, PR63 recommendation/overlap, corrected readiness files, actual corrections, updated starting/onboarding instructions, documentation/reference validation, final full SHA, clean status and publication order. PR63 is not merged/closed by this package. Local commit is authorized; no push or PR.
