# Tasks

Ordered build plan derived from `/docs`. An autonomous agent works through these one at a time, marking each complete before moving on.

## How the agent works through tasks

1. Find the lowest-numbered task whose `status` is `pending` and whose `depends_on` are all `complete`.
2. Read the task file and the doc sections it references.
3. Implement until every line in `acceptance_criteria` passes.
4. Run `composer test` (lint + types + Pest). If anything fails, fix it before continuing.
5. Update `status: complete`, commit with message `task(<id>): <title>`, then pick the next task.
6. If a task is blocked by something outside the codebase (e.g. needs a real DO Spaces bucket), set `status: blocked` and move on to the next non-dependent task.

## Layout

Tasks are grouped by phase. Each phase is a directory; phases are processed roughly in order, but tasks can run in parallel within a phase if their `depends_on` allow it.

```
00-foundation/      DB, auth cleanup, env config, build deps
10-catalog/         Products, sets, cards, inventory, pricing
20-orders/          Orders, line items, four-source import
30-components/      Mf* component library
40-public-pages/    Public homepage, login
50-admin-pages/     Dashboard, settings, add-cards
60-data-pages/      Catalog, orders, inventory pages
70-jobs/            Packing-slip PDF, seller-stats scraper, file cleanup
80-deploy/          CI, Forge config, droplet provisioning
```

## Task file format

See `_template.md`. Every task has frontmatter (id, status, depends_on, etc.) and a body with description, acceptance criteria, references, and notes.

## Statuses

- `pending` — not started
- `in_progress` — agent is working on it now (only one task should be in this state at a time)
- `complete` — all acceptance criteria pass
- `blocked` — needs something the agent can't do (manual provisioning, decision from operator)

## Dependency syntax

`depends_on` accepts:

- **Task IDs** like `"00-001"` — depend on a specific task being `complete`.
- **Phase markers** like `"phase:10-catalog"` — depend on every task in that phase being `complete`. Use this for cross-phase dependencies when the consumer doesn't care about a specific upstream task, only that the whole upstream phase is done.

## Project decisions (locked in)

- **Single user.** No multi-tenant. No registration. No password reset email — admin uses Artisan commands.
- **No payment processing.** Personal tool, not charging anyone.
- **No outbound email.** Scraper health is checked on the Settings page, not via email alerts.
- **PDF text extraction:** `smalot/pdfparser`.
- **Headless rendering:** Spatie Browsershot (requires Chrome on the server — provisioned in phase 80).
- **Dark mode:** mandatory and the default.
- **Multi-sheet packing slips:** in v1.
- **Secrets in `.env`:** TCGPlayer seller ID, DO Spaces credentials, etc.
