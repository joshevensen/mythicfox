---
name: build-next-phase
description: Implement every pending task in the next pending phase from tasks/CHECKLIST.md, on a dedicated branch, with per-task commits, ending with a PR. Use when the user wants to advance the project build by one phase.
---

# build-next-phase

Implements all pending tasks in the lowest-numbered phase that has any pending tasks. Each task is implemented, tested, and committed individually. The whole phase ships as one PR.

## Workflow

### 1. Identify the next phase

Read [tasks/CHECKLIST.md](../../../tasks/CHECKLIST.md). Find the **lowest-numbered phase with at least one unchecked task**. Call this `<phase>` (e.g. `00-foundation`).

If every task is checked, tell the user the build is complete and stop.

### 2. Confirm scope with the user

Show the user:

- The phase name and total task count.
- The list of pending tasks in dependency order (id + title + size).
- A note about which task ID is currently in progress on `main` (if any — usually none).

Ask: **"Proceed with all N tasks, or just the next 1? (default: all)"**

If they pick a smaller scope, only do that many tasks but still follow the full git flow (branch, PR at end). Don't proceed without explicit confirmation.

### 3. Pre-flight checks

Before creating the branch:

- `git status` must be clean. If there are uncommitted changes, stop and tell the user.
- Current branch must be `main` (or whatever `git symbolic-ref refs/remotes/origin/HEAD` resolves to). Switch to `main` and `git pull` first.
- Confirm `composer ci:check` passes on `main` before starting. If it fails, stop — we don't want to inherit a broken baseline. (`ci:check` is the same gate CI runs: ESLint, Prettier, vue-tsc, Pint, and Pest. Plain `composer test` only covers Pint + Pest, so frontend lint/format/type errors will slip through to CI.)

### 4. Create the phase branch

```
git checkout -b build/<phase>
```

For example: `build/00-foundation`, `build/10-catalog`. The branch name is exactly `build/` + the phase directory name.

### 5. Work each task in dependency order

Read every pending task file in the phase directory. Sort by `id`. Within that order, respect `depends_on` — if a task depends on another in this phase, do that one first.

For **each task**:

1. **Read the task file.** Read every doc referenced in its `references:` list. Don't skip — the implementing context lives in the docs.
2. **Set `status: in_progress`** in the task file frontmatter. Don't commit this on its own.
3. **Implement** to make every line in `acceptance_criteria` true. Use the existing patterns in the codebase, the project's `AGENTS.md`, and the doc references. Don't introduce abstractions the task doesn't ask for.
4. **Run `composer ci:check`.** Mirrors what GitHub Actions runs — ESLint, Prettier, vue-tsc, Pint, Pest. If anything fails, fix and re-run. Do not move on with a failing check. (For tasks that only touch PHP and don't touch any `resources/js/**` files, `composer test` is acceptable as a faster signal — but the next end-of-phase `ci:check` must still pass.)
5. **Update the task file:**
   - Flip `status: in_progress` → `status: complete`.
   - Tick every `acceptance_criteria` checkbox `[ ]` → `[x]`.
6. **Update [CHECKLIST.md](../../../tasks/CHECKLIST.md):**
   - Flip the task's `[ ]` → `[x]`.
   - Increment the phase's `(X / N)` count.
   - Increment the total at the top.
7. **Commit** with the message format below. Stage only the files actually changed by this task plus the task file and CHECKLIST update — don't sweep up unrelated changes.

   ```
   task(<id>): <title>

   <one-paragraph summary of what was implemented and why, drawn from the task's Goal>

   Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
   ```

8. If anything in steps 3–4 reveals a spec ambiguity that the task didn't anticipate, **pause and surface it to the user** rather than guessing. Document the resolution in the commit message.

### 6. End-of-phase: open the PR

After the last task commits successfully:

1. Run `composer ci:check` one more time as a final sanity check. **Do not push if it fails** — fix the issues, commit the fix as `fix: …`, and re-run. Pushing a red branch wastes a CI cycle and forces a follow-up commit anyway.
2. `git push -u origin build/<phase>`.
3. Open a PR with `gh pr create`. Title: `Build phase: <phase>`. Body uses this template:

   ```
   ## Summary

   Implements every task in `tasks/<phase>/` per the build plan in `tasks/CHECKLIST.md`.

   ## Tasks shipped

   - <id>: <title>
   - <id>: <title>
   ...

   ## Test plan

   - [ ] CI green
   - [ ] Pull, run `composer install && npm ci && npm run build`, verify `composer ci:check` passes locally
   - [ ] <any task-specific manual verification — e.g. run a CSV import, hit the new route in a browser>

   🤖 Generated with [Claude Code](https://claude.com/claude-code)
   ```

4. Print the PR URL to the user.

### 7. Stop

Do not proceed to the next phase. Each phase invocation is independent. The user reviews and merges; on the next `/build-next-phase` call, the workflow starts over from a clean `main`.

## Hard rules

- **One task per commit.** Never bundle two tasks into one commit, even small ones.
- **Never `--amend`** a previous commit. If a task is wrong, make a new commit with `task(<id>): fix ...` on the same branch.
- **Never skip tests.** No `--no-verify`, no commenting out failing assertions.
- **`composer ci:check` is the gate, not `composer test`.** `composer test` only runs Pint + Pest. CI runs ESLint, Prettier, vue-tsc, Pint, Pest — all gated by `composer ci:check`. Any phase that touches `resources/js/**` must pass `composer ci:check` before push, or CI will go red on lint/format/types issues that locally looked fine.
- **Never push to `main`.** All work happens on `build/<phase>` and ships via PR.
- **Stop and surface anything blocked.** If a task can't be completed (needs operator-manual provisioning, spec gap, external dependency unavailable), set `status: blocked`, write a brief note in the task file under a new `## Blocker` section, commit that, and move to the next non-dependent task. Don't skip silently.
- **Phase 80 tasks are operator-manual.** Do not attempt to provision DigitalOcean droplets, create Spaces buckets, or configure Forge from inside this skill. Mark them `blocked` with a note for the operator.

## Failure recovery

If the skill stops mid-phase (network error, test fails the agent can't fix, etc.):

- The branch `build/<phase>` retains all committed progress.
- Re-invoking `/build-next-phase` will detect the existing branch, ask the user whether to resume on it or start fresh, and continue from the next pending task.

## Example session start

```
User: /build-next-phase
Agent: Next pending phase: 00-foundation (6 tasks pending, 0 complete).

  - 00-001 (S) Switch local development DB from SQLite to PostgreSQL
  - 00-002 (S) Strip Fortify down to single-user login...
  - 00-003 (S) Add user:create and user:reset-password Artisan commands
  - 00-004 (S) Add app config keys and .env.example entries...
  - 00-005 (S) Install Browsershot and smalot/pdfparser
  - 00-006 (M) Confirm Pest baseline and add GitHub Actions CI

  Proceed with all 6, or just the next 1? (default: all)
User: all
Agent: [creates branch build/00-foundation, works through tasks, opens PR at the end]
```
