---
id: "XX-NNN"
title: "Short imperative summary"
status: pending
phase: "XX-phase-name"
size: S  # S (<1h), M (1–4h), L (4h+)
depends_on: []  # list of task ids, e.g. ["00-001", "00-002"]
references:
  - docs/path/to/spec.md#section
---

## Goal

One short paragraph: what this task accomplishes and why it matters in the overall build.

## Acceptance criteria

- [ ] Concrete, verifiable statement.
- [ ] Another verifiable statement.
- [ ] `composer test` passes after the change.

## Implementation notes

Anything an autonomous agent would need to know to execute without re-deriving it from `/docs`: which Artisan generator to use, which file to edit, gotchas, naming conventions.

## Out of scope

Things this task does NOT do, to prevent scope creep into the next task.
