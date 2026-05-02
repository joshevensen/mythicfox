---
name: review-build-phase
description: Review a build-phase PR against each task's acceptance criteria, post findings as PR comments, and if everything is green, merge the PR and clean up the local branch. Use after /build-next-phase has opened a PR.
---

# review-build-phase

Reviews a PR produced by `/build-next-phase`. Verifies that each `task(<id>): ...` commit actually meets its task's acceptance criteria (not just claims to), flags scope creep, runs the test suite against the PR head, posts a structured review on the PR, and if all checks pass, merges the PR and cleans up local state.

## Workflow

### 1. Identify the PR

Take an optional PR number argument. If omitted:

- Infer from the current branch: if it's `build/<phase>`, find the open PR for that branch via `gh pr list --head build/<phase> --state open --json number,url`.
- If multiple PRs match, ask the user which one.
- If no PR matches, stop and tell the user to create one (or that one was already merged).

### 2. Pull PR metadata and diff

```
gh pr view <num> --json number,title,headRefName,headRefOid,baseRefName,state,mergeable,statusCheckRollup,reviewDecision,commits,files
gh pr diff <num>
```

Verify:

- PR is open. If merged or closed, stop.
- `mergeable` is `MERGEABLE`. If `CONFLICTING`, stop and surface to the user.
- Branch is `build/<phase>` shape. Confirm `<phase>` matches a directory in `tasks/`. If not, this isn't a build-phase PR — switch to a generic review and warn the user.

### 3. Check out the PR locally for testing

```
gh pr checkout <num>
```

This puts the working tree on the PR head. Note: this may switch from `main` if you weren't already on the build branch.

### 4. Triage open review-thread comments

PRs from `/build-next-phase` often pick up automated reviews (Copilot, etc.) and human review comments. Walk each unresolved thread before grading the build — fixes change the code under review, so doing this first avoids re-verifying after.

Fetch unresolved review threads via GraphQL (REST doesn't expose `isResolved` or thread IDs):

```
gh api graphql -F owner=<owner> -F repo=<repo> -F number=<num> -f query='
  query($owner:String!, $repo:String!, $number:Int!) {
    repository(owner:$owner, name:$repo) {
      pullRequest(number:$number) {
        reviewThreads(first:100) {
          nodes {
            id
            isResolved
            isOutdated
            comments(first:50) {
              nodes { id databaseId body path line author { login } }
            }
          }
        }
      }
    }
  }
'
```

Skip threads where `isResolved: true`. For each remaining thread, decide:

- **fix** — suggestion is correct or an obvious improvement. Apply the edit.
- **won't fix** — suggestion is wrong, conflicts with the spec, or duplicates a check CI already enforces (Pint, Prettier, ESLint, vue-tsc). State the reason.
- **defer** — valid but out of scope for this phase (e.g. a refactor that belongs in a later task). Reference where it should land.

In all three cases:

1. If `fix`: edit the code. Don't stage or commit yet — batch all fixes at the end of the step.
2. Reply on the thread with what you decided and why. Use the **first** comment's `databaseId` as the reply target:

   ```
   gh api -X POST repos/<owner>/<repo>/pulls/<num>/comments/<first_comment.databaseId>/replies \
     -f body="<reply>"
   ```

3. Resolve the thread:

   ```
   gh api graphql -F id=<thread.id> -f query='
     mutation($id:ID!) { resolveReviewThread(input:{threadId:$id}) { thread { id isResolved } } }
   '
   ```

After all threads handled, if any code changed:

- Run `composer test` to confirm nothing regressed.
- Commit with a focused message (e.g. `review: address Copilot feedback on MfTable types`). Group commits by concern; don't bundle unrelated fixes.
- Push to the PR branch.

Also pull PR-level (issue) comments via `gh pr view <num> --comments` for awareness, but don't auto-resolve those — they're conversational, not threaded. Note any actionable items in the final report.

#### Decision discipline

- **Don't fix lazily.** A vague "consider extracting this" is not a fix unless it's clearly right. Push back with a reason if you disagree — silent compliance pollutes history.
- **Don't auto-fix style nits CI already enforces.** Pint, Prettier, ESLint, vue-tsc — if those would catch it, the comment is redundant; say so and resolve.
- **Don't resolve threads you didn't actually address.** If a comment needs human judgment you can't make confidently, leave it open and surface in the final report under a `### Open review threads` heading.

### 5. Run the test suite against PR head

```
composer test
```

If it fails, capture the output. **Do not abort the review** — collect this as one finding and continue. The full report tells the user everything that's wrong, not just the first thing.

### 6. Wait for CI

```
gh pr checks <num> --watch
```

If CI is still running, wait. If CI fails, capture which checks failed; this becomes a finding.

### 7. Walk each task commit

List the PR's commits:

```
gh pr view <num> --json commits --jq '.commits[] | {oid: .oid, message: .messageHeadline}'
```

For each commit whose message matches `^task\((\d{2}-\d{3})\): `:

1. **Extract the task ID.** Load `tasks/<phase>/<id>-*.md`.
2. **Diff just this commit:** `git show <oid>`.
3. **Verify each acceptance criterion** in the task file:
   - For criteria that are testable in code (route renders, model exists, command works): confirm the code actually does what it says, not just that a checkbox got ticked. Read the relevant files in the diff.
   - For criteria that are testable via Pest: confirm a test exists in the diff that exercises the criterion. A criterion saying "rejects duplicate email" must have a test that asserts that, not just a happy-path test.
   - For criteria like "`composer test` passes": already covered by step 4.
4. **Flag scope creep:** if `git show <oid>` includes files unrelated to the task's stated scope, list them. Examples: a model task that also edits a Vue component; a CSV-parser task that also touches the deploy config.
5. **Cross-check `AGENTS.md`** for project-specific conventions (Wayfinder routes, no hardcoded URLs, single-user assumptions, etc.). Flag violations.
6. **Cross-check the system-level guidance:** no needless abstractions, no comments explaining what code does, no error handling for impossible cases, no `// removed` comments. Flag violations.

For commits that don't match the `task(<id>):` pattern (merges, doc-only, fixups, `review:` commits from step 4), note them but don't grade against criteria — just check for scope concerns.

### 8. Compose the review

Aggregate findings into a structured markdown body:

```
## Phase review: <phase>

### Test results
- composer test: <pass|fail>
- CI: <pass|fail|n/a>
- Mergeable: <yes|no>

### Review-thread triage

- Threads addressed: <N>
  - Fixed: <N>
  - Won't fix: <N> (with reasons)
  - Deferred: <N> (with target task/phase)
- Threads left open: <N> (only when human judgment was needed)

### Per-task verification

#### task(00-001): <title>
- ✅ <criterion> — <why it passes>
- ❌ <criterion> — <what's missing or wrong, with file:line>
- ⚠️ Scope: <unrelated file>

#### task(00-002): <title>
...

### Scope-creep summary
<aggregate list of files touched outside any task's scope>

### Convention violations
<list, with file:line>

### Recommendation
<merge | request-changes | comment>
```

The **recommendation** rule:

- **`merge`** — every acceptance criterion verified, `composer test` green, CI green, no convention violations, no scope creep, every review thread either resolved or explicitly left open with a justified reason. Equivalent to a clean approval.
- **`request-changes`** — any acceptance criterion fails, tests fail, or critical convention violation.
- **`comment`** — the in-between: nits, minor scope creep, suggestions, or threads left open for human judgment. Don't block merge but surface for awareness.

### 9. Post the review on the PR

Use `gh pr review`:

- For `merge` → `gh pr review <num> --approve --body-file <tmp>`
- For `request-changes` → `gh pr review <num> --request-changes --body-file <tmp>`
- For `comment` → `gh pr review <num> --comment --body-file <tmp>`

Per-line comments (e.g. "this acceptance criterion isn't actually met by line 42") can be inline review comments via `gh api repos/:owner/:repo/pulls/<num>/reviews` if the finding ties to a specific file/line; otherwise consolidate them into the body.

### 10. If recommendation is `merge`: complete the merge + cleanup

Only proceed past this point if the recommendation is `merge` AND `composer test` passed AND CI is green AND `mergeable: MERGEABLE`. Any other state: stop after posting the review.

```
gh pr merge <num> --merge --delete-branch
```

Use `--merge` (preserves the per-task commit history) not `--squash`. The per-task commits are valuable for `git bisect` and `git blame`. `--delete-branch` cleans up the remote.

After the merge succeeds:

```
git checkout main
git pull --ff-only
git branch -d build/<phase>
```

Use `-d` (safe delete) not `-D` — if Git refuses because the branch is unmerged, that's a real signal that something went wrong with the merge, not something to override.

If `git branch -d` fails because the branch is "not fully merged" but the PR shows merged, the local branch is just behind because we used a merge commit, not fast-forward. Use `git branch -D build/<phase>` in that specific case — but **only** after re-confirming the PR is merged via `gh pr view <num> --json state` returning `MERGED`.

### 11. Report back to the user

Print a summary:

- PR number, title, merge SHA.
- Per-task pass/fail tally.
- Findings posted to the PR.
- Local state: which branch you're on, whether the build branch was deleted.
- Suggested next step: `/build-next-phase` to start the next phase.

If the recommendation was NOT `merge`, the summary explains why and points the user at the PR review. Do not merge or clean up local state in that case.

## Hard rules

- **Never merge with red tests.** Even if every acceptance criterion passes, a failing test is grounds for `request-changes`, not approval.
- **Never `--squash`.** Per-task commits must be preserved.
- **Never force-merge** (`gh pr merge --admin` to bypass branch protection). If the merge is blocked by branch protection, surface that to the user — don't override.
- **Never delete an unmerged local branch with `-D`** unless the PR's state is verified `MERGED` first.
- **Never push to `main`** directly. All merging happens through `gh pr merge`.
- **One review per invocation.** If the user wants to re-review after pushing fixes, they re-invoke the skill.
- **Never resolve a thread you didn't address.** Resolving means "this is settled" — leaving a fix-or-not decision unmade and resolving the thread is worse than leaving it open.
- **Never bundle review-comment fixes into `task(<id>):` commits.** Use `review:` (or `chore:`) commits so the per-task history stays clean for `git bisect`.

## Failure recovery

- If the merge succeeds but local cleanup fails (network drop, etc.), the PR is still merged on GitHub. Re-invoking the skill on the now-merged PR will see `state: MERGED` and just do the local cleanup.
- If the review posts but the merge fails (branch protection, conflict appearing mid-merge), the review is still on the PR. Re-invoking is safe.

## Example session

```
User: /review-build-phase
Agent: Reviewing PR #12 (Build phase: 00-foundation, branch build/00-foundation).

  Triaging 4 unresolved review threads...
    ✅ #1 (Copilot, app/Console/Commands/CreateUser.php:42) — fixed: trimmed
       email before validation. Replied + resolved.
    💬 #2 (Copilot, config/fortify.php:18) — won't fix: comment is wrong about
       Fortify defaults; the spec explicitly disables registration. Replied
       with the doc reference + resolved.
    ⏭️ #3 (Copilot, app/Models/User.php:30) — defer: extracting the cast
       belongs in the 10-catalog refactor, not this phase. Replied + resolved.
    🤔 #4 (human, README.md:5) — left open: needs your judgment on the
       wording. Surfaced in final report.
  Pushed 1 review commit.

  Running composer test... PASS
  Waiting for CI... PASS
  Verifying 6 tasks...
    ✅ 00-001 (4/4 criteria met)
    ✅ 00-002 (5/5 criteria met)
    ⚠️ 00-003 (4/5 criteria — "rejects when a user already exists" has no test)
    ✅ 00-004 (7/7 criteria met)
    ✅ 00-005 (3/3 criteria met)
    ✅ 00-006 (5/5 criteria met)

  Recommendation: request-changes (one missing test, plus thread #4 awaiting
  your input)

  Review posted: https://github.com/.../pull/12#pullrequestreview-...
  Not merging. Fix the missing test, weigh in on thread #4, and re-invoke.
```
