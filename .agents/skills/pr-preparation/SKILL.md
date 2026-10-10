---
name: pr-preparation
description: Review every Git change layer and prepare an evidence-backed PR title, description, file allowlist, validation results, and limitations for owner review.
---

# Pull Request Preparation

Read `AGENTS.md`, the canonical roadmap and history, and `CONTRIBUTING.md`. This skill guides behavior; it does not enforce filesystem, shell, network, Git or database permissions. Enforcement comes from sandbox, approval, host and network policy.

## Complete read-only change review

Confirm the branch, approved base, package scope and owner authorization. Inspect every layer separately; no single diff includes them all:

```text
git status --short --branch
git merge-base HEAD origin/main
git diff --name-status origin/main...HEAD
git diff --name-status
git diff --cached --name-status
git ls-files --others --exclude-standard
git status --short
git diff origin/main...HEAD --stat
git diff --stat
git diff --cached --stat
git diff origin/main...HEAD --check
git diff --check
git diff --cached --check
```

Read the full committed, unstaged and staged diffs and each relevant untracked file, not just names/statistics. Compare the union against the approved file allowlist. Stop and explain unexpected files or base changes; do not merge, rebase, reset or discard local work to make the list fit.

Check protected assets, dependencies, configuration, credentials and temporary artifacts in every layer. Use the protected-file workflow for evidence, without running recovery operations. For runtime changes, collate applicable lint/regression/browser evidence. For documentation-only work, validate Markdown/frontmatter and discovery; classify irrelevant runtime checks Not Run. Database-backed evidence requires prior inspection proving every mutation targets `udm_radar_scratch`, including handlers and subprocesses; rollback alone is not proof of read-only behavior.

## Evidence-backed description

Lead with the concrete problem and resulting behavior. Follow the contribution guide and scale detail to the change:

```markdown
## Summary of Changes
Describe the final change and its purpose.

## Affected Components
List the reviewed files, package boundaries and migration requirements.

## Verification & Test Results
For each applicable check, record its command, target/runtime, exit code,
actual totals, and status: Passed, Failed, Skipped, Not Run or Unavailable.
Classify browser checks as authenticated application, generated static HTML,
mocked browser or repository source inspection. List credential-dependent skips.

## Safety & Invariant Confirmation
Record observed results for protected assets, academic contracts,
mutation targets, dependency integrity, artifacts and working-tree status.
Leave unevaluated claims unconfirmed; do not precheck boxes.

## Known Limitations & Follow-Up Work
Identify unavailable evidence, deferred policy and remaining review gates.
```

Do not retain permanent expected totals, invent checks, call source inspection end-to-end testing, or describe a package as merged before Git confirms the merge.

## Delivery boundary

This preparation skill must not automatically stage, commit, push, merge, force-push, alter branches, restore files or suppress failures. Present the concrete diff and PR draft. A separate delivery step may carry out actions explicitly authorized by the project owner; honor existing authorization without inventing an additional gate. PR approval does not authorize unrelated work or automatic merge. Recovery advice requires exact-diff review and owner authorization before overwriting or discarding work.
