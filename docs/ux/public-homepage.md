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
│                                        │
│  [Shop on TCGPlayer →]                 │
├────────────────────────────────────────┤
│  ABOUT                                 │
│  ~2 paragraphs of brand story.         │
├────────────────────────────────────────┤
│  WHAT YOU GET                          │
│  3 short feature blocks.               │
├────────────────────────────────────────┤
│  CONTACT                               │
│  Email + form-free mailto link.        │
├────────────────────────────────────────┤
│  FOOTER                                │
│  © Mythic Fox Games · Admin            │
└────────────────────────────────────────┘
```

### Hero

| Element | Content |
|---|---|
| Logo | Mythic Fox Games card-style logo (same asset reused on packing slip, login, admin top nav) |
| Tagline | **"We buy & sell trading card games."** — concise, no "niche" framing |
| Primary CTA | "Shop on TCGPlayer →" → external link to the seller's TCGPlayer storefront URL (`target="_blank" rel="noopener"`) |

The TCGPlayer storefront URL is a placeholder until you confirm the actual one. Goes in a config value or a Blade/Vue prop so it's not hardcoded in markup.

### About

A short paragraph or two introducing Mythic Fox Games. Drafted copy:

> Mythic Fox Games is a TCGPlayer storefront for Magic: The Gathering, Lorcana, and Flesh & Blood collectors. We pack carefully, ship fast, and price fairly — every order is hand-packed and includes a personal packing slip.

Edit/replace as you see fit when you're ready to commit copy. The exact words live in the Vue component for this page; updates ship via a deploy.

### What you get

Three short feature blocks with a small icon each. Suggested set (you pick the right ones):

| Title | One-line description |
|---|---|
| Fast shipping | "Orders pack and ship within 1 business day." |
| Fair pricing | "Algorithmic pricing tracks the TCGPlayer market." |
| Hand-packed care | "Every shipment goes out with a custom packing slip." |

These are placeholder lines based on what's true about the operation. Edit before launch.

### Contact

A single line with the operator's contact email rendered as a `mailto:` link:

> Questions or buy-list inquiries? **josh@mythicfoxgames.com**

No contact form for v1 — the email link is enough and avoids the spam-filtering / form-validation work.

### Footer

Tiny, muted:

```
© 2026 Mythic Fox Games  ·  [Admin]
```

The "Admin" link points to `/login`. Discreet so it's not the focal point, but findable for the operator.

No social links, no nav, no newsletter signup. Single-page brand site.

---

## SEO

Standard hygiene:

| Item | Detail |
|---|---|
| `<title>` | "Mythic Fox Games — Buy & Sell Trading Card Games" |
| `<meta name="description">` | Short brand description (~150 chars) |
| OG / Twitter card | Meta tags + a 1200×630 OG image (use the Mythic Fox card logo on a brand-colored background) |
| Favicon | Branded `.ico` + Apple touch icon |
| `/sitemap.xml` | Lists `/` only (admin routes excluded) |
| `/robots.txt` | `Allow: /` and `Disallow: /login`, `/dashboard`, `/orders`, `/catalog`, `/inventory`, `/add-cards`, `/settings`. Sitemap link. |
| Structured data | JSON-LD `Organization` block in the page head: name, url, logo |

Submit `/sitemap.xml` to Google Search Console after launch.

---

## Mobile-first

Single responsive layout. Breakpoints from Tailwind defaults. Specific notes:

- Hero stacks logo above tagline above CTA on narrow screens; side-by-side allowed on wide.
- "What you get" block: 1 column on phone, 3 columns on desktop.
- All tap targets ≥ 44px.
- No JavaScript required to render the page (Inertia hydration is fine, but the SSR markup must be complete enough to make sense without JS).

---

## Data

Reads:

- None. The page is fully static (Vue component with hard-coded content).

Writes:

- None.

---

## States

| State | Display |
|---|---|
| Default | Static page, fully rendered. |
| (No other states.) | |

There are no loading, error, or empty states because there's no dynamic content.

---

## Open questions

1. **TCGPlayer storefront URL** — what's the correct link? Goes into a config value once known so it's not buried in markup.
2. **Brand color palette** — same open question from [components.md](components.md). Resolving it surfaces brand colors for the OG image, footer accent, and CTA button.
3. **Copy** — the about / feature lines above are drafted placeholders. Edit before launch.
