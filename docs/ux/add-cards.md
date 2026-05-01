# Add Cards

Mobile-first data entry surface for adding new stock to inventory. Designed for the couch — sort cards physically by set/condition, then sit with phone or tablet and increment counts as you go through the pile.

**Route**: `/add-cards`
**Access**: admin
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [catalog-schema.md](../catalog-schema.md)

---

## Purpose

Bulk acquisition workflow. The use case:

- New batch of cards arrives (collection purchase, sealed product crack, trade-in)
- Seller pre-sorts by Product → Set → Condition
- Picks up phone, picks the matching scope on Add Cards, scrolls the alphabetical list of cards in that scope
- Increments counts with +/- buttons as each card is processed
- Saves; counts are **added** to existing inventory (never replaced)
- Re-scopes for the next pile (different set, different condition) and repeats

This page is intentionally unlike [Inventory](inventory.md) — no prices, no overrides, no Export action. Just card name on the left, qty input on the right. Single concern.

---

## Layout

Vertical stack, full-screen on mobile, max-width container on desktop.

```
┌────────────────────────────────────┐
│ [Product ▾] [Set ▾] [Condition ▾]  │  ← top of page (scrolls away)
├────────────────────────────────────┤
│  Card Name           [-] 0 [+]     │
│  Card Name           [-] 0 [+]     │  ← scrollable body
│  Card Name           [-] 3 [+]     │     (qty>0 rows highlighted)
│  ...                               │
│  Card Name           [-] 0 [+]     │
├────────────────────────────────────┤
│ [          Save 14 cards         ] │  ← fixed bottom
└────────────────────────────────────┘
```

### Top — scope selectors

Three selectors set the page's scope. All three required before the card list renders. **Not sticky** — the selectors live at the top of the page and scroll away with normal page scroll. Scope is set once per pile, so always-visible isn't worth the vertical space.

| Selector | Type | Notes |
|---|---|---|
| Product | single-select | Magic / Lorcana TCG / Flesh & Blood TCG |
| Set | single-select, chained to Product | Lists all sets within the chosen product |
| Condition | single-select | One of the 11 TCGPlayer condition strings — see [catalog-schema.md §Condition vocabulary](../catalog-schema.md) |

Single-select on each (not multi). The whole page is scoped to one combination at a time. To re-scope (after finishing a pile), scroll back to the top — instant on mobile via tap-status-bar (iOS) or quick swipe up (Android). Auto-save kicks in when scope changes per §Re-scoping mid-session.

### Card list

Scrollable alphabetical list. One row per card matching `(set, condition)`. Each row:

- **Left**: card identity, two-line:
  - Card name
  - `#{Number}` in muted small text below
- **Right**: `MfQtyInput` — `−` button, integer display (tap to open numeric keypad), `+` button. Defaults to 0.

Rows with qty > 0 get a subtle **highlight** (green-tinted background + a small checkmark next to the qty). Visual feedback that the row is part of the pending save.

### Fixed bottom — Save

One full-width primary button **fixed-positioned** at the bottom of the viewport (not sticky-within-container — always visible while scrolling, never disappears off-screen). Label updates dynamically:

- No entries (all qty = 0): button label "Save", **disabled**.
- ≥1 entry: button label "Save N cards" (where N = sum of all entered quantities).

The card list scroll area has bottom padding equal to the Save button's height so the last row never sits underneath it.

---

## Interactions

### Saving

Tap **Save** → server upserts inventory rows for each entry where qty > 0:

- Find `inventory` row by `card_id`, or create one if it doesn't exist.
- `quantity = existing_quantity + entered_qty` (additive — never replace).
- `override_price`, `calculated_price`, `last_exported_price` left untouched. Pricing happens on the Inventory page / Export Pricing flow; this page never touches money.
- Server response: total count of cards saved.

After save:

- All qty inputs reset to 0.
- Toast: *"Added N cards to Welcome to Rathe (Near Mint)."*
- Scope selectors stay set — user is ready to keep going on the same scope, or re-scope manually.

Saves of qty = 0 are **skipped silently** (per the original scaffolding). No row is created, no error surfaced.

**Save button is disabled while a save is in flight** — prevents double-clicks from firing two parallel saves. The "Saving…" spinner state already implies this; making it explicit here so it's not just a visual cue. No debouncing on +/- taps — Vue's reactivity is synchronous, so the Save handler always reads the latest qty when clicked.

### Re-scoping mid-session

If the user changes any of Product / Set / Condition while there are pending qty inputs > 0:

1. **Auto-save** the current pending entries first (same flow as the Save button).
2. Show toast confirming save: *"Saved before switching."*
3. Then load the new scope.

This avoids data loss without making the user click Save manually before every switch. If the auto-save fails, the switch is cancelled and an `MfErrorBanner` appears at the top — user must resolve before changing scope.

If there are no pending entries (all qty = 0), the switch happens silently with no save call.

### +/- button behavior

- `+` increments by 1.
- `−` decrements by 1, floor at 0.
- Tapping the number opens the numeric keypad for direct entry.
- Long-press on `+` or `−` does **not** auto-repeat (avoid runaway counts; users sort one card at a time).

---

## Data

Reads:

- `cards` joined to `sets` joined to `products`, filtered by selected scope `(product_id, set_id, condition)`.

Writes (on save):

- `inventory.quantity` — additive upsert per entered (card_id, qty > 0) pair. Inserts new inventory rows when needed.

The set+condition combination might filter cards table to ~50–500 rows. Loaded in full (no pagination) — the scrollable list is the navigation.

---

## States

| State | Display |
|---|---|
| Selectors not all set | "Pick a product, set, and condition to add cards." Big empty placeholder; no list. |
| Scope set, zero matching cards | "No cards in {Set} match {Condition}. Try a different condition." (Not every set/condition combo exists in catalog.) |
| Loading | Skeleton list rows. |
| Saving in flight | Save button shows spinner + "Saving…"; inputs disabled. |
| Save success | Toast: "Added N cards to {Set} ({Condition})." Inputs reset to 0. |
| Save failure | `MfErrorBanner` above the list; pending qty inputs preserved so user can retry. |
| Re-scope auto-save in flight | Lightweight inline indicator (small spinner) on the top selectors; selectors disabled briefly until save completes, then new scope loads. |

---

## Mobile-specific notes

This page is the **only** admin surface explicitly optimized for phone. Considerations:

- **Tap target sizes**: +/- buttons ≥ 44 × 44px. Qty number tap area same.
- **No horizontal scroll**: row content fits viewport width on a 375px-wide screen.
- **Soft keyboard handling**: when the numeric keypad opens, the fixed bottom Save button stays above it (not obscured).
- **Pull-to-refresh disabled** — accidental gesture during scroll-and-tap shouldn't reload.
- **Scroll-to-top to re-scope**: scope selectors are at the top of the page, not sticky. Use the platform gesture (tap status bar on iOS, swipe up on Android) to snap to top when you're ready for a new pile.

Desktop renders the same layout, just centered with a max-width.

---

## Things to consider

- **Pending qty inputs live only in browser memory.** Closing the tab or losing connectivity mid-pile loses unsaved counts. Consider periodic auto-save (every 30 seconds when there are pending entries > 0) or a localStorage-backed draft so a refresh restores in-flight work.
- **Auto-save on re-scope can fail silently if you don't surface the error.** The current spec says the switch is cancelled on failure with a banner — make sure this is visible enough that the operator notices before tapping the selector again.
- **No undo for a wrong save.** If you accidentally enter qty 10 when you meant 1 and hit Save, that 10 has been added to inventory. Recovery requires going to the Inventory page and editing the qty back down. A "last save: undo" toast would help, but adds complexity — defer unless this happens often.
- **Sets with hundreds of cards.** Some Lorcana / Magic sets exceed 200 cards. Loaded all at once is fine for v1, but at 500+ rows the DOM can get sluggish on lower-end phones. Consider virtual scrolling if a target set ever feels slow.
- **Duplicate scrolling when re-scoping.** After auto-save, the new scope renders with everything at qty 0 again, but scroll position resets to the top. That's actually correct (new pile, fresh start), but worth confirming the scroll behavior doesn't disorient.
- **Pull-to-refresh is platform-fickle.** Disabling it from JS works on most browsers but isn't universal — iOS Safari can still trigger it in edge cases. Test on real devices.
