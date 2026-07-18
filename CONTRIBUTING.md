# Contributing to UdM-RADAR — Git Workflow

This is the workflow everyone on the team follows, every time. Read this
once, then it becomes muscle memory after a few uses.

## The one rule

**Nobody pushes directly to `main`.** All changes go through a branch and a
pull request (PR), reviewed by Jendrick before merging. This is enforced on
GitHub itself (branch protection), not just a policy we agree to verbally —
attempting to push straight to `main` will be rejected.

---

## Every time you start new work

```bash
git checkout main
git pull
```

Always start from an up-to-date `main`. Skipping this is the #1 cause of
annoying merge conflicts later.

### Create your branch

Naming convention: `yourname/short-description-of-the-task`

```bash
git checkout -b joshua/faculty-fix
```

```bash
git checkout -b vincent/admin-fix
```

```bash
git checkout -b jendrick/grades-csv-import
```

Keep it lowercase, hyphens not spaces, and specific enough that a teammate
glancing at the branch list knows what it's for without opening it.

---

## While you're working

Commit as you go — small, meaningful commits, not one giant commit at the
end of a session:

```bash
git add .
git commit -m "Fix faculty trend chart legend overlap"
```

Good commit messages describe *what changed*, not *how you felt about it*.
`"Fix analytics query double-counting"` — good. `"more fixes"` — not useful
to anyone six weeks from now, including you.

---

## When your piece is ready

### Push your branch (not main)

```bash
git push -u origin joshua/faculty-fix
```

The `-u` only matters the first time you push this branch — after that,
plain `git push` remembers where it goes.

### Open the pull request

**Option A — GitHub website (simplest, no install):**
After pushing, go to the repo on github.com. A yellow banner appears:
**"joshua/faculty-fix had recent pushes"** → click **Compare & pull
request** → write a short description of what you changed and why → **Create
pull request**.

**Option B — GitHub CLI (stays in the terminal):**
One-time setup: install from [cli.github.com](https://cli.github.com/), then
```bash
gh auth login
```
After that, from any branch you've pushed:
```bash
gh pr create
```
It'll prompt for a title and description right in the terminal.

---

## What happens next

Jendrick reviews the PR on GitHub — reads the diff, may leave comments
asking for changes. If changes are requested:

```bash
# still on your branch, joshua/faculty-fix
# ...make the requested edits...
git add .
git commit -m "Address review feedback"
git push
```

The PR updates automatically — no need to open a new one.

Once approved, Jendrick clicks **Merge pull request** on GitHub. Your
changes are now in `main`.

---

## After your PR is merged

Clean up — switch back to `main`, pull the merged changes, and delete your
now-finished branch:

```bash
git checkout main
git pull
git branch -d joshua/faculty-fix
```

The `-d` only deletes it locally; it's harmless to skip if you're not sure,
but it keeps `git branch` from filling up with stale branches over time.

---

## If you hit a merge conflict

This means you and someone else both changed the same lines of the same
file. Git will tell you which files are conflicted. Open them — you'll see
markers like this:

```
<<<<<<< HEAD
your version of the line
=======
their version of the line
>>>>>>> main
```

Edit the file by hand to keep whichever version is correct (or combine
both), delete the `<<<<<<<`/`=======`/`>>>>>>>` marker lines themselves,
save, then:

```bash
git add .
git commit -m "Resolve merge conflict"
git push
```

Not dangerous, just something to expect occasionally when multiple people
touch the same file — ask in the group chat if you're unsure which version
should win.

---

## Quick reference — the whole cycle in one block

```bash
git checkout main
git pull
git checkout -b yourname/what-youre-doing

# ...work, then...

git add .
git commit -m "describe the change"
git push -u origin yourname/what-youre-doing

# open PR on github.com or `gh pr create`
# wait for review/merge
# then clean up:

git checkout main
git pull
git branch -d yourname/what-youre-doing
```
