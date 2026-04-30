# Dashboard

The post-login home page. Intentionally minimal for v1 — a welcome + a few quick-action shortcuts. Real dashboard widgets (open-order counts, inventory value, revenue over time, etc.) are deferred until actual usage reveals what's worth showing.

**Route**: `/dashboard` (post-login redirect target)
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md)

---

## Why minimal for v1

The store is dormant during initial build, so the operator doesn't yet know which numbers will actually inform decisions day-to-day. Designing widgets in advance produces speculative ones that don't get used. Better to ship with a welcoming placeholder, then add specific widgets once a real workflow surfaces concrete questions ("how many open orders right now?", "what's my inventory value?", etc.).

This explicitly defers what was old gaps.md #2 (Dashboard math). When the dashboard gets fleshed out, that work updates this doc.

---

## Layout

```
┌─────────────────────────────────────────────┐
│  Welcome back, {name}                       │
│  Mythic Fox Games                           │
├─────────────────────────────────────────────┤
│  Quick actions                              │
│                                             │
│   [+ Add Cards]    [⬆ Import Orders]        │
│   [📃 Catalog]     [💲 Export Pricing]      │
├─────────────────────────────────────────────┤
│  More dashboards coming soon as your        │
│  workflow takes shape.                      │
└─────────────────────────────────────────────┘
```

### Page header

| Element | Content |
|---|---|
| Greeting | "Welcome back, {first name from `users.name`}" |
| Subtitle | "Mythic Fox Games" |

No primary action button on the dashboard header — actions live in the Quick Actions block.

### Quick actions

A 2×2 grid of large click-targets, each opening a primary workflow:

| Tile | Destination |
|---|---|
| **+ Add Cards** | `/add-cards` |
| **⬆ Import Orders** | `/orders` (then opens the import modal automatically via a `?import=1` query param) |
| **📃 Catalog** | `/catalog` |
| **💲 Export Pricing** | `/inventory` (then opens the Export Pricing flow automatically via a `?export=1` query param) |

Tiles render as `MfPageContainer`-styled cards with an icon, label, and short description ("Add new cards to inventory", "Print packing slips and import a fresh batch", etc.). Tappable on mobile, hover-accented on desktop.

### Coming soon footer

A single muted line of text below the tiles: *"More dashboards coming soon as your workflow takes shape."* No further detail. As widgets get spec'd later, they replace this footer.

---

## Interactions

### Quick-action tiles

Standard navigation. The two tiles that open modals on the destination page (`Import Orders`, `Export Pricing`) pass a query param the destination page reads on mount:

- `/orders?import=1` → opens the import modal on `Orders` page mount.
- `/inventory?export=1` → triggers the Export Pricing recompute + preview modal on `Inventory` page mount.

This makes the dashboard quick-actions feel like real shortcuts rather than just "navigate then click again."

---

## Data

Reads:

- `users.name` for the greeting.

No other queries. No widgets, no aggregates, no joins.

---

## States

| State | Display |
|---|---|
| Default | Greeting + four quick-action tiles + coming-soon footer. |
| (No other states.) | |

---

## When to revisit

Add real widgets when at least one of these becomes true:

- The operator catches themselves opening Orders Table several times a day to count open orders → build "Open orders" widget with the saved query.
- The operator catches themselves recomputing inventory value mentally → build the value widget.
- A specific recurring question gets asked of the data ("how much did I sell this week?") → build that widget.

Don't pre-design widgets. Let usage drive the spec, then update this doc.

---

## Open questions

None. The minimal-shape decision is the answer.
