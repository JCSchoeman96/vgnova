# AGENTS.md — Voelgoed Rebuild (Novamira Project)

> **Project:** Voelgoed Platform Consolidation  
> **Agent Scope:** AI assistants and automated tooling working within this codebase  
> **Last Updated:** October 2026

---

This project manages a WordPress Local Flywheel staging site through Novamira MCP in Cursor. For a staging snapshot of URLs, CPTs, field groups, and editor flows, see [`README.md`](README.md).

## Source of truth and runtime access

The Git repository is canonical for source-controlled architecture and code. The local WordPress installation is derived runtime state and may also contain database-managed content and configuration. Inspect the runtime before describing live state; do not treat runtime-only edits as source-controlled architecture.

Use Novamira MCP as the primary WordPress runtime interface. Use WP-CLI or the local shell for read-only verification and as a fallback. Direct SQL writes are exceptional and require a documented reason and explicit task scope.

Local runtime references:

- Site host: `localhost:10039`
- WordPress root: `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`
- SQL directory: `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/sql/`

## Rules
- Prefer Novamira MCP for WordPress inspection and changes.
- Inspect before editing: understand templates, components, globals, variables, and dynamic data first.
- Treat this site as staging only; avoid destructive actions unless explicitly approved.
- Prefer Bricks-native controls over custom PHP, JS, or CSS when possible.
- Reuse existing global classes, variables, theme styles, templates, and components before creating new ones.
- Patch specific elements or templates instead of rebuilding whole pages.
- Explain the plan briefly before making changes.
- After changes, report exactly what was changed and where.
- Flag risky actions before executing them.
- If MCP is unavailable, say so clearly and do not invent results.

## What We Are Building

**Voelgoed** is an Afrikaans women's wellness and lifestyle platform — a unified digital ecosystem consolidating **three separate WordPress sites** into one scalable, schema-rich, Bricks-based platform.

### Sites Being Consolidated

| Source Site | Content | Status |
|---|---|---|
| `voelgoed.co.za` | 500+ articles, memberships, events | Migrating |
| `leer.voelgoed.co.za` | 15 LearnDash courses | Migrating |
| `winkel.voelgoed.co.za` | 50+ WooCommerce products | Migrating |

### End Goal

One unified WordPress site — `voelgoed.co.za` — with:
- **500+ articles** classified against seven canonical semantic topic pillars, with commercial classifications kept separate
- **15 courses** (LMS — Masteriyo on staging; legacy source was LearnDash)
- **50+ products** (WooCommerce)
- **Events** (Tickera `tc_events` + `event_category` taxonomy — 8 event-type terms)
- **Medewerkers** (`medewerker` CPT) — one public profile model for all contributors
- **Tickera** ticketing for live events
- **Paid Memberships Pro** for gated content
- **Full GA4 + GTM + Meta Pixel** tracking
- **FluentCRM** email automation
- **Schema-rich structured data** for SEO

### Canonical topic pillars and legacy categories

The seven canonical semantic pillars live in `content_topic`: `nuwe-jy`, `geloof`, `self`, `werk-geld`, `ouerskap`, `huwelik`, and `mensstories`.

`content_category` is the current legacy taxonomy. It remains attached to live content until its terms, relationships, and consumers pass the gates in [`docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`](docs/architecture/LEGACY_DECOMMISSION_REGISTER.md). `Leefstyl` and its child terms are commercial classifications, not canonical `content_topic` pillars.


## Theme & Plugin Stack

### Theme
| Plugin / Tool | Purpose |
|---|---|
| **Bricks Builder** | Primary theme + page builder. ALL templates, layouts, and components are built in Bricks. Use dynamic tags (ACF: `{acf_field_name}`), conditions, and Template Parts. |

### Core Plugins
| Plugin | Purpose |
|---|---|
| **Advanced Custom Fields Pro (ACF)** | **ALL CPTs and custom fields.** No other field plugin is permitted. |
| **Automatic.css (ACSS)** | Utility classes, CSS variables, spacing + typography scale. Use ACSS before any custom CSS. |
| **ClassicMonks** | Bricks add-on: 50+ elements, query loop controls, animation suite, security, script manager, WooCommerce enhancements. |
| **WooCommerce** | E-commerce: products, shop, checkout, payments. |
| **Paid Memberships Pro (PMP)** | Membership tiers, content gating, member dashboard. |
| **Tickera** | Event ticketing and registration. Embedded on event single pages. |
| **Masteriyo** (staging) | Online courses (`mto-course`), lessons, enrollments. Replaces LearnDash on this install. |
| **FluentCRM** | Email marketing, automation, newsletter signup integration. |
| **FluentSnippets** | **ALL custom code snippets** (PHP, JS, CSS). The only approved snippet manager. |
| **Rank Math SEO** | SEO metadata, schema markup, XML sitemap, redirects. |
| **Fluent Forms** | Forms (contact, newsletter, nominations). Integrates with FluentCRM. |

### Voelgoed Platform Core source ownership

For the current project phase, the canonical Platform Core source is the
`site-code/voelgoed-platform-core/` module in the `vgnova` repository. The
active WordPress directory at `wp-content/plugins/voelgoed-platform-core/` is
a runtime deployment copy. The module's `integration.patch` records the
bootstrap changes needed to apply the source-controlled code to that copy.

The canonical `content_topic` taxonomy is the explicit domain-architecture
exception to ACF-owned taxonomy registration. Voelgoed Platform Core owns its
definition and seeding logic. This exception applies only to `content_topic`.

Any Platform Core code change must first exist in the source-controlled
`vgnova/site-code/voelgoed-platform-core/` module. The active local WordPress
plugin directory must not contain unique architecture changes that are absent
from the repository. Do not create a competing `content_topic` definition in
ACF, FluentSnippets, or theme code.

### Analytics & Tracking
| Tool | Purpose |
|---|---|
| **Google Tag Manager (GTM)** | Tag container — deploys GA4, Meta Pixel, and custom events |
| **GA4** | Primary analytics |
| **Meta Pixel** | Facebook/Instagram ad tracking |
| **Looker Studio** | Reporting dashboards |

---

## Non-Negotiable Development Rules

### 1. ACF for ALL CPTs and Fields — No Exceptions
> **RULE:** Every Custom Post Type and every custom field group MUST be created and managed via ACF Pro.  
> Never register CPTs or fields via raw `register_post_type()` or `register_taxonomy()` in theme files or random plugins.  
> ACF handles CPT registration under **ACF → Post Types** and field groups under **ACF → Field Groups**.

```php
// ✅ CORRECT — retrieve ACF fields like this
$event_date = get_field('event_start_date', $post_id);
$medewerker  = get_field('event_medewerkers', $post_id); // relationship array on tc_events

// ❌ WRONG — never bypass ACF
$event_date = get_post_meta($post_id, 'event_start_date', true);
```

**ACF naming (staging standard):** Use context-prefixed field **names** and **labels** so Bricks dynamic-data dropdowns stay scannable.

- Pattern: `[field_context]_[field_purpose]` (e.g. `medewerker_kort_bio`, `vennoot_logo`, `produk_bron_event`).
- Avoid generic names alone (`bio`, `status`, `email`, `logo`) unless prefixed by domain/context.
- Admin labels: `Context: Label` (e.g. **Medewerker: Kort bio**, **Site: Email**).
- Global options on `vg-company`: field **names** stay `company_name`, `contact_email`, etc.; admin labels use the **Site:** prefix only (no `site_*` rename until a dedicated migration pass).

### 2. FluentSnippets for ALL Code Snippets — No Exceptions
> **RULE:** ALL custom PHP, JavaScript, and CSS snippets must be created inside **FluentSnippets**, not in `functions.php`, theme files, or any other snippet plugin.  
> FluentSnippets provides safe activation/deactivation, error recovery, and version tracking.

- Label every snippet clearly: e.g. `[ACF] Product — Copy event medewerkers` (`9-acf-product-copy.php`).
- Set correct run conditions (all pages, admin only, specific post type, etc.)
- Never add untested code to snippets on production — test on staging first.

### 3. Custom Code is the LAST Resort
> **RULE:** Exhaust all native WordPress, plugin UI, and ACSS/Bricks configuration options **before** writing custom code.  
> Decision order:

```
1. WordPress native (menus, settings, built-in blocks)
2. ACF UI (field groups, options pages, CPT builder)
3. Bricks Builder UI (templates, conditions, dynamic tags, query loops)
4. ACSS utilities (spacing, colour, typography)
5. ClassicMonks features (animations, script manager, WooCommerce enhancements)
6. Plugin settings (PMP, Tickera, Masteriyo, FluentCRM)
7. FluentSnippets → ONLY when the above cannot solve it
```

> If you reach step 7, document WHY the plugin could not handle it in the snippet's description field.

---

## Established Architecture Decisions

These are **locked** — do not re-debate or propose alternatives:

| Decision | Detail |
|---|---|
| **Topic pillars** | Seven canonical roots in `content_topic`, owned by Voelgoed Platform Core. `content_category` remains live legacy until its migration gates pass. |
| **Relationships** | ACF relationship fields — never taxonomy cross-linking |
| **Medewerkers** | Single CPT (`medewerker`) + `medewerker_rol` / `medewerker_groep` taxonomies — NOT WordPress users; **no** legacy `mentor` CPT or `/mentor/` URLs |
| **Events** | Tickera `tc_events` + `event_category` taxonomy — NOT separate CPTs per type |
| **Voelgoed Live** | Internal domain entity and CPT key: `uitsending`. Public brand: Voelgoed Live. Canonical public root: `/voelgoed-live/`. The public brand does not determine the internal identifier. |
| **Competitions** | Separate CPT (`competition`) + child CPT (`competition_entry`) |
| **No-code first** | Native WordPress → Bricks UI → ACF UI before any code |
| **Naming convention** | `snake_case` for ACF fields, `BEM` for CSS classes, `data-attributes` for JS hooks |
| **URL structure** | `/content/slug`, `/events/slug`, `/kursusse/slug`, `/medewerkers/slug` etc. |

---

## Custom Post Types (CPTs)

| CPT | Slug | Archive URL | Notes |
|---|---|---|---|
| `post` (default) | — | `/content/{slug}/` | Standard articles (permalink prefix `/content/`) |
| `medewerker` | `medewerkers` | `/medewerkers/` | Public contributors — roles via `medewerker_rol`, groups via `medewerker_groep` |
| `uitsending` | `voelgoed-live` | `/voelgoed-live/` | Voelgoed Live episodes; internal key stays `uitsending` |
| `kompetisie` | `kompetisies` | `/kompetisie/` | Inspirasie Vrou etc. |
| `kompetisie_inskrywing` | `inskrywing` | Under parent | Competition entries |
| `vennoot` | (under `/content/`) | No archive | Sponsors/partners |
| `team_member`, `testimonial` | default | No public archive | Internal/profile use |

**Plugin CPTs (not ACF):** `tc_events` (Tickera, `/events/`), `mto-course` (Masteriyo, singles `/leer/`, archive `/kursusse/`), `product` (WooCommerce, singles `/winkel/`, shop `/produkte/`).

### Medewerkers (current MVP — fresh rebuild)

Staging treats medewerkers as a **greenfield** model. Do not recreate the old `mentor` CPT, mentor ACF groups, `/mentor/` rewrites, or compatibility redirects.

| Piece | Detail |
|-------|--------|
| **CPT** | `medewerker` — archive `/medewerkers/`, single `/medewerkers/{slug}/` |
| **Taxonomy** | `medewerker_rol` — Mentor, Spreker, Aanbieder, Kunstenaar, Gas, Paneellid, Fasiliteerder, Skrywer, Kenner |
| **Taxonomy** | `medewerker_groep` — Voelgoed Mentor, Voelgoed Kenner (`voelgoed-kenner`), Event Spreker, Bekende, Voelgoed Live Gas |
| **Profile ACF** (*Medewerker Besonderhede*) | `medewerker_titel`, `medewerker_kort_bio`, `medewerker_profiel_status` (`public` \| `private` \| `noindex` \| `archived`). Labels: Medewerker: Titel / Kort bio / Profielstatus. |
| **Event ACF** (*Event Medewerkers* on `tc_events`) | `event_medewerkers` → relationship to `medewerker` (Post ID, multiple) |
| **Product ACF** (*Medewerker Produkte*) | `produk_bron_event` → `tc_events`; `produk_medewerkers` → `medewerker` |
| **FluentSnippets** | `9-acf-product-copy.php` — on product save, copies `event_medewerkers` into empty `produk_medewerkers` (never overwrites manual selection) |
| **PMP** | Do **not** gate the `medewerker` CPT — gate articles, courses, products, events |

**Editor flow:** Create person → assign rol/groep → link on event → create product → set bron event → medewerkers auto-fill or set manually. Full steps: [`docs/development/medewerkers-admin-workflow.md`](docs/development/medewerkers-admin-workflow.md).

**Bricks (MVP shipped):** Templates *Medewerker Single* (772), *Medewerker Archive* (773), *Event Single* (774); components *Medewerker Card* (`6b463f`), *Medewerker Hero* (`4d1dd4`); FluentSnippets `10-vg-bricks-medewerker-queries.php` powers Query Loops via `query.vg_query_type`. Profile/card dynamic tags: `{acf_medewerker_titel}`, `{acf_medewerker_kort_bio}`.

---

## Coding Standards

### PHP (via FluentSnippets)
```php
// ✅ Always use hooks — never run loose code
add_action('acf/init', function() {
    // ACF-dependent setup here
});

add_action('init', function() {
    // CPT / taxonomy setup here (only if ACF UI cannot handle it)
});

// ✅ Security checks on any data input
$value = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';

// ✅ ACF field retrieval
$bio = get_field('medewerker_kort_bio', get_the_ID());
```

### Bricks Builder
- Use Bricks dynamic tags — never hardcode content that belongs in WordPress/ACF
- **ACF fields use underscores, not colons:** `{acf_medewerker_titel}`, `{acf_medewerker_kort_bio}`, `{acf_event_medewerkers}` — not `{acf:medewerker_titel}`
- Common post/taxonomy tags on this site: `{post_title}`, `{post_url}`, `{featured_image}`, `{post_terms_medewerker_rol}`
- Apply conditions for template visibility (post type, taxonomy, user role)
- Build reusable elements as **Template Parts**
- Use `data-*` attributes for JavaScript hooks — never target ACSS or BEM classes with JS

### CSS
- **ACSS utilities first** (`p-m`, `bg-base`, `text-dark`, etc.)
- **BEM naming** for custom classes: `.card`, `.card__title`, `.card--featured`
- **`data-attributes`** for state-driven styles: `[data-card-type="event"]`
- Never use `!important` unless overriding a third-party plugin with no other option

### JavaScript (via FluentSnippets or Bricks custom JS)
- Vanilla JS preferred — no jQuery unless required by a plugin
- Event delegation pattern: `document.addEventListener('click', e => { if (e.target.matches('[data-action]')) {...} })`
- Prefix all global functions/vars: `vg_initFilters()`, `vgCardState`

---

## Bricks Template Hierarchy

20 templates to build:

1. Header (global)
2. Footer (global)
3. Homepage
4. Article archive (`/content/`)
5. Article single
6. Event archive (`/events/`)
7. Event single
8. Course archive (`/kursusse/`)
9. Course single
10. Product archive (`/winkel/`)
11. Product single
12. Voelgoed Live archive (`/voelgoed-live/`)
13. Voelgoed Live series hub (permalink to be decided)
14. Voelgoed Live single (`/voelgoed-live/{slug}/`)
15. Competition archive
16. Competition single + entry pages
17. Medewerker archive (`/medewerkers/`)
18. Medewerker single (`/medewerkers/{slug}/`)
19. Category page (×8 — uses 1 template with dynamic data)
20. Search results + 404

---

## Component Library (Reusable Bricks Parts)

- `.card` — base card with article / event / course / product / Voelgoed Live / competition variants
- Hero section — full-width image + overlay + headline + CTA
- Featured content block — 60/40 large + sidebar layout
- Related content section — 3-column grid
- Newsletter CTA — FluentCRM-connected email form
- Stats/metrics block
- Testimonial carousel
- Breadcrumbs
- Category showcase — 8-category grid

---

## What Agents Must NOT Do

- ❌ Register CPTs or field groups outside ACF
- ❌ Add PHP snippets to `functions.php` or theme files
- ❌ Use any snippet plugin other than FluentSnippets
- ❌ Suggest replacing Bricks with another page builder
- ❌ Create new plugin recommendations without a clear gap analysis
- ❌ Modify URL slugs or taxonomy structures without explicit confirmation (breaking change)
- ❌ Write custom code when a plugin UI option exists
- ❌ Use jQuery when vanilla JS works
- ❌ Override ACSS variables with hardcoded hex values or px values
- ❌ Reintroduce `mentor` CPT, mentor-only ACF groups, or `/mentor/` URL redirects for “migration compatibility”
- ❌ Use taxonomy terms to link medewerkers to events/products (use ACF relationship fields only)

## Legacy cleanup obligation

When a task touches legacy or superseded architecture, remove it only after the replacement and every consumer are verified. If a blocker prevents removal, update the [Legacy Decommission Register](docs/architecture/LEGACY_DECOMMISSION_REGISTER.md) with the exact blocker and removal gate. Do not leave newly obsolete architecture undocumented.

Use this lifecycle:

1. Discover the legacy item and its consumers.
2. Design the replacement.
3. Implement the replacement.
4. Migrate data.
5. Migrate consumers.
6. Verify the replacement and migrated consumers.
7. Decommission the legacy item.
8. Verify removal.

---

*This file is the source of truth for all AI agents, automated tools, and contributors working on the Voelgoed Rebuild inside Novamira.*

---

## Voelgoed Member Perks Plugin Submodule

The `voelgoed-member-perks/` directory is a Git submodule for the custom **Voelgoed Member Perks** plugin.

When working inside `voelgoed-member-perks/`, agents must follow:

    voelgoed-member-perks/AGENTS.md

That plugin-specific `AGENTS.md` overrides the broader site-level rules where they conflict.

Important exception:

- The broader Voelgoed rebuild prefers ACF, Bricks, and FluentSnippets for ordinary site work.
- The Voelgoed Member Perks plugin is intentionally custom plugin code.
- Do not put plugin logic into FluentSnippets, theme files, or Bricks templates.
- Do not treat ACF as runtime authority inside the plugin.
- Do not hardcode PMPro level IDs, Woo product IDs, billing terms, or current programme/level assumptions.
- Use the plugin repo, branches, PRs, and slice planning docs as the implementation authority.

The parent `vgnova` repo remains the Novamira/staging documentation workspace. The plugin repo is the code authority for Voelgoed Member Perks.
