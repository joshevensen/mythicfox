# Public Homepage

The unauthenticated landing page at the root of mythicfoxgames.com. Brand presence, trust signal, and a clear path to the TCGPlayer storefront. Lives in the same Laravel app as the admin SaaS — same deploy, same brand assets — but on the root path with no auth requirement.

**Route**: `/` (root; public)
**Access**: public — no auth required
**Built on**: [ux-patterns.md](ux-patterns.md), [components.md](components.md), [saas-design.md](../saas-design.md)

---

## Routing model

This page lives at root. **All admin pages move to non-root paths**:

| Surface | Route |
|---|---|
| Public homepage | `/` |
| Login | `/login` |
| Dashboard | `/dashboard` |
| Orders | `/orders` |
| Catalog | `/catalog` |
| Inventory | `/inventory` |
| Add Cards | `/add-cards` |
| Settings | `/settings` |

Login redirects to `/dashboard` (not `/`) on success. Logged-in users visiting `/` see the public homepage normally — there is no auto-redirect from `/` to `/dashboard` for authed users (so you can preview the public face of your own site without signing out).

---

## Purpose

A static, hand-crafted brand page. No CMS, no database content — content lives in a Vue component and is updated via deploys. The page exists to:

- Establish that mythicfoxgames.com is a real business with a real shop
- Direct interested visitors to the TCGPlayer storefront where they actually transact
- Provide a contact path for buy-list inquiries / support
- Surface a discreet path to admin login for the operator

This page is **not** a storefront. TCGPlayer remains the sales channel — see [saas-design.md §TCGPlayer as the marketplace](../saas-design.md).

---

## Layout

Single responsive page. Mobile-first; desktop layout is the same content with wider gutters. Below is a top-to-bottom section order.

```
┌────────────────────────────────────────┐
│  HERO                                  │
│  [Mythic Fox logo]                     │
│  We buy & sell trading card games.     │
│  [Shop on TCGPlayer →]                 │
├────────────────────────────────────────┤
│  ABOUT                                 │
│  Brand paragraph + pack-fresh framing. │
├────────────────────────────────────────┤
│  WHAT YOU GET                          │
│  4 feature blocks (pack-fresh / honest │
│  condition / packaging / fast ship).   │
├────────────────────────────────────────┤
│  WHAT BUYERS SAY                       │
│  Rating + count + 3 review quotes      │
│  (hidden until first scrape succeeds). │
├────────────────────────────────────────┤
│  CONTACT                               │
│  mailto: link.                         │
├────────────────────────────────────────┤
│  FOOTER                                │
│  © 2025 – {year} · Admin               │
└────────────────────────────────────────┘
```

### Hero

| Element | Content |
|---|---|
| Logo | Mythic Fox Games card-style logo (same asset reused on packing slip, login, admin top nav) |
| Tagline | **"We buy & sell trading card games."** — concise, no "niche" framing |
| Primary CTA | "Shop on TCGPlayer →" → external link to `https://www.tcgplayer.com/sellers/Mythic-Fox-Games/623394e9` (`target="_blank" rel="noopener"`) |

Store the URL in `config/services.php` (e.g. `services.tcgplayer.storefront_url`) backed by a `.env` value, then reference it via Inertia's shared props so the homepage Vue component reads `page.props.tcgplayerStorefrontUrl` instead of hardcoding the URL in markup. Same approach for the seller ID `623394e9` if it's needed elsewhere (e.g. order # validation).

### About

A short paragraph introducing Mythic Fox Games, framed around what real TCG buyers care about (accurate condition grading, safe packaging, fast shipping, responsive support):

> Mythic Fox Games is a TCGPlayer storefront. We buy and sell any trading card game — currently specializing in Magic: The Gathering, Lorcana, and Flesh & Blood. Most of our inventory comes from sealed product we open ourselves, so cards arrive pack-fresh — never played, never shuffled. Every card is graded honestly, packed in penny sleeves with TCGuardian shipping protectors, and shipped within one business day. If anything arrives wrong, message me — I'll make it right.

The buy-side ("we buy any TCG") routes through the contact email for now; a structured buy list is mentioned as a future feature in [saas-design.md](../saas-design.md).

### What you get

Four short feature blocks with a PrimeIcon each. Each addresses a recurring complaint TCG buyers have about third-party sellers (condition lying, sloppy packing, slow ship, ghost-mode sellers).

| Title | One-line description |
|---|---|
| Pack-fresh inventory | "Most cards come straight from sealed product we open ourselves. Never played, never shuffled." |
| Honest condition | "Cards graded conservatively. NM means NM." |
| Protective packaging | "Penny sleeves and TCGuardian shipping protectors. Cards arrive flat and dry." |
| Fast shipping | "Orders pack and ship within 1 business day." |

Layout: 1 row × 4 on wide desktop, 2 × 2 on tablet, single column on phone. The "Pack-fresh" block leads because it's the strongest *why* behind the other claims — it's the underlying reason the condition grading is reliable. "Direct support" was dropped from the feature blocks since the contact section below already covers the same ground.

Tracking is intentionally not mentioned: TCGPlayer's checkout shows the actual shipping option for each order (tracked for $30+, plain envelope under that). Stating it on the homepage would force a qualifier that plants doubt for sub-$30 buyers. If you'd prefer to call it out, the positive framing would be *"Tracked shipping on orders $30+"*.

### What buyers say

Reads from the `seller_stats` singleton (see [saas-design.md §`seller_stats`](../saas-design.md)). Refreshed daily by the scheduled scraper.

- **Star rating + count** at the top, e.g. *"⭐ 4.9 from 312 reviews on TCGPlayer"*. Star glyphs use PrimeIcons (`pi pi-star-fill` / `pi pi-star`); rating value and count are pulled from `seller_stats.rating` and `seller_stats.review_count`.
- **3 quoted reviews** beneath, when comments were captured by the scrape (`seller_stats.feedback` non-empty). Each card shows the comment text, the reviewer's display name, and the date. If `feedback` is empty (the public page exposes ratings but not comment text) the section just shows the rating + count.
- **The whole section hides** when `seller_stats.scraped_at IS NULL` (the scraper hasn't succeeded yet, or has been disabled). No placeholder, no fake data.

Visual treatment: simple cards in a single row on desktop, stacked on mobile. Quote text uses `mf-brown` for emphasis; star glyphs use `mf-orange` (per [ux-patterns.md §Brand colors](ux-patterns.md)).

The rating + review count is **not a clickable link** — the Hero "Shop on TCGPlayer →" CTA already sends visitors to the storefront, and a second link to the same destination just clutters.

### Contact

A single line with the operator's contact email rendered as a `mailto:` link:

> Questions or buy-list inquiries? **josh@mythicfoxgames.com**

No contact form for v1 — the email link is enough and avoids the spam-filtering / form-validation work.

### Footer

Tiny, muted. Copyright year is dynamic so it never goes stale:

```
© 2025 – {currentYear} Mythic Fox Games  ·  [Admin]
```

- `{currentYear}` is computed at render time (`new Date().getFullYear()`).
- When `currentYear === 2025` the range collapses to a single year: `© 2025 Mythic Fox Games`. Otherwise the range form is used. (E.g. in 2026: `© 2025 – 2026 Mythic Fox Games`.)

The "Admin" link points to `/login`. Discreet so it's not the focal point, but findable for the operator.

No social links, no nav, no newsletter signup. Single-page brand site.

---

## SEO

Standard hygiene:

| Item | Detail |
|---|---|
| `<title>` | "Mythic Fox Games — Buy & Sell Trading Card Games" |
| `<meta name="description">` | "Mythic Fox Games is a TCGPlayer storefront for Magic: The Gathering, Lorcana, and Flesh & Blood. Pack-fresh cards, honest grading, fast shipping." |
| Favicon set | Generated and shipped in `public/`: `favicon.ico` (multi-size 16/32/48/64), `favicon-16x16.png`, `favicon-32x32.png`, `favicon-48x48.png`, `apple-touch-icon.png` (180×180), `android-chrome-192x192.png`, `android-chrome-512x512.png`, `site.webmanifest`. |
| `/sitemap.xml` | Lists `/` only (admin routes excluded) |
| `/robots.txt` | `Allow: /` and `Disallow: /login`, `/dashboard`, `/orders`, `/catalog`, `/inventory`, `/add-cards`, `/settings`. Sitemap link. |
| Structured data | JSON-LD `Organization` block in the page head: name, url, logo |

OG / Twitter card images are out of scope for v1.

### Required `<head>` markup

```html
<title>Mythic Fox Games — Buy & Sell Trading Card Games</title>
<meta name="description" content="Mythic Fox Games is a TCGPlayer storefront for Magic: The Gathering, Lorcana, and Flesh & Blood. Pack-fresh cards, honest grading, fast shipping.">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="icon" href="/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#EA5A1F">
```

Submit `/sitemap.xml` to Google Search Console after launch.

---

## Mobile-first

Single responsive layout. Breakpoints from Tailwind defaults. Specific notes:

- Hero stacks logo above tagline above CTA on narrow screens; side-by-side allowed on wide.
- "What you get" block: 1 column on phone, 2 × 2 on tablet, 1 row × 4 on desktop.
- "What buyers say" block: 1 column on phone, 1 row × 3 on tablet/desktop.
- All tap targets ≥ 44px.
- No JavaScript required to render the page (Inertia hydration is fine, but the SSR markup must be complete enough to make sense without JS).

---

## Data

Reads:

- `seller_stats` (singleton) — for the "What buyers say" section. See [saas-design.md §`seller_stats`](../saas-design.md). When `scraped_at IS NULL`, the section is hidden; otherwise rating, count, and feedback are rendered.

Writes:

- None.

The rest of the page (hero copy, about paragraph, feature blocks, contact, footer) is hard-coded in the Vue component and only changes via deploy.

---

## States

| State | Display |
|---|---|
| Default | Static page, fully rendered. |
| (No other states.) | |

There are no loading, error, or empty states because there's no dynamic content.
