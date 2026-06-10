---
name: novamira-bricks-acss-frontend-design
description: Use this skill when building, redesigning, auditing, or improving WordPress front-end interfaces through Novamira, especially on sites using Bricks Builder, Automatic.css (ACSS), Frames, BEM class methodology, ACF dynamic data, and vanilla CSS/JS. This skill creates distinctive, production-grade WordPress UI that avoids generic AI aesthetics while staying compatible with Bricks, ACSS tokens, Frames structures, and maintainable WordPress workflows.
disable-model-invocation: true
---

# Novamira Bricks + ACSS Frontend Design Skill

This skill guides AI agents working inside WordPress through Novamira to create distinctive, production-grade front-end interfaces using **Bricks Builder**, **Automatic.css (ACSS)**, **Frames**, **BEM methodology**, **ACF**, and **vanilla CSS/JS**.

The goal is not merely to make pages “look nice.” The goal is to build interfaces that are:

- visually memorable,
- structurally clean,
- easy to maintain in Bricks,
- token-driven through ACSS,
- dynamic where WordPress/ACF data should be dynamic,
- safe to iterate on through Novamira,
- and free of generic AI-generated SaaS aesthetics.

## When this skill must be used

Use this skill whenever the user asks to create, rebuild, redesign, style, improve, audit, or generate:

- a WordPress page, landing page, event page, archive, single template, header, footer, or reusable section;
- a Bricks Builder layout, component, query loop, template, modal, card, hero, CTA, pricing section, testimonial area, event listing, or WooCommerce/Tickera-related front-end UI;
- a design system or component system using Bricks + ACSS;
- CSS for a Bricks/ACSS site;
- ACF-powered dynamic layouts;
- Frames-based sections or templates;
- UI intended to be implemented by Novamira inside WordPress.

## Core rule

Build like a senior WordPress front-end developer using Bricks and ACSS, not like a generic AI website generator.

Every decision must serve four goals:

1. **Design distinction** — the interface must have a clear aesthetic point of view.
2. **WordPress maintainability** — the build must remain editable and understandable in Bricks.
3. **ACSS consistency** — spacing, typography, colors, sizing, and layout rhythm must be token-driven.
4. **Dynamic correctness** — content that belongs in ACF, CPTs, taxonomies, or Bricks dynamic data must not be hard-coded.

---

# 1. Novamira operating protocol

Novamira can work directly inside WordPress. That power must be treated carefully.

## Before changing anything

When connected to a real WordPress install, first inspect the current environment before proposing or applying implementation details:

- active theme and child theme;
- active plugins relevant to the task, especially Bricks, ACSS, Frames, ACF, WooCommerce, Tickera, PMPro, SEO, caching, and custom snippets/plugins;
- existing Bricks templates, template types, conditions, global classes, and query loops;
- existing ACSS configuration, token naming, color palette, typography scale, spacing scale, breakpoints, and utility conventions;
- existing ACF field groups, CPTs, taxonomies, option pages, and field names;
- existing CSS locations: child theme files, Bricks custom CSS, WPCode snippets, custom plugin CSS, or ACSS custom CSS;
- whether this is local, development, staging, or production.

## Safety rules

- Prefer development or staging. Do not make destructive changes on production unless the user explicitly instructs it.
- Before file edits, identify the exact file path and explain why that file is the correct place.
- Before database or content changes, identify the affected post/template/option IDs or slugs.
- Avoid direct database writes unless there is no safer WordPress API route.
- Prefer reversible changes: new draft templates, cloned templates, child-theme CSS, or custom plugin modules.
- Never overwrite existing Bricks templates or ACF groups without first checking their current structure.
- Do not create random helper snippets in multiple places. Keep implementation centralized and documented.
- If a change affects layout, test visually with a browser tool or request a screenshot/check before finalizing.

---

# 2. Design thinking before implementation

Before writing CSS, building Bricks structures, or generating code, define the design direction.

## Required design framing

For every substantial page or component, establish:

- **Purpose**: What business goal does this interface serve?
- **Audience**: Who is using or viewing it?
- **Content source**: Static Bricks content, ACF fields, CPT entries, taxonomy data, WooCommerce products, events, or options pages?
- **Tone**: Choose a clear visual posture, not a bland middle ground.
- **Memorable hook**: What is the one visual idea that makes this section recognizable?
- **Constraints**: Bricks, ACSS, Frames, ACF, performance, accessibility, mobile behaviour, and editing needs.

## Approved aesthetic directions

Choose intentionally from directions like:

- editorial luxury;
- warm organic editorial;
- high-utility industrial;
- refined minimalism;
- bold event-poster energy;
- premium conference/event brand;
- soft feminine lifestyle;
- brutalist/raw tech;
- art deco/geometric;
- cinematic media-brand;
- playful magazine layout;
- faith-based calm and warmth;
- sharp commercial conversion page.

Do not default to “modern SaaS cards on white with purple gradient.” That is generic and unacceptable unless the brand explicitly requires it.

## Banned generic AI design habits

Avoid:

- Inter/Roboto/Arial as lazy defaults;
- purple-to-blue gradients on white panels;
- generic floating gradient blobs;
- identical card grids with no hierarchy;
- 8px-radius SaaS panels everywhere;
- meaningless glassmorphism;
- excessive shadows without design reason;
- icon-card-icon-card repetition;
- layout decisions that ignore the actual content model;
- placeholder copy that does not match the brand or user’s real context.

---

# 3. Bricks Builder implementation rules

## Bricks structure first

Use Bricks-native structures wherever possible:

- Section;
- Container;
- Block;
- Div;
- Heading;
- Text;
- Rich Text;
- Image;
- Button;
- Query Loop;
- Template;
- Code element only when necessary.

Do not build front-end UI as one large HTML blob unless the user specifically asks for embed-style code. Bricks must remain editable.

## Element hierarchy

Prefer this structural pattern:

```text
Section / layout wrapper
└── Container / layout boundary
    └── BEM block component
        ├── BEM element
        ├── BEM element
        └── BEM element
```

Example:

```text
Section: .l-event-hero
└── Container: .l-event-hero__inner
    └── Component wrapper: .c-event-hero
        ├── .c-event-hero__eyebrow
        ├── .c-event-hero__heading
        ├── .c-event-hero__lede
        ├── .c-event-hero__meta-list
        └── .c-event-hero__actions
```

## Bricks class discipline

- Apply explicit classes to Bricks elements.
- Do not rely on Bricks generated IDs.
- Do not style random element IDs.
- Do not style broad global selectors such as `.brxe-div`, `.brxe-heading`, or `.bricks-button` unless building a deliberate global reset.
- Use global classes for reusable components.
- Use local classes only for one-off template-specific styling.
- Use Bricks dynamic data for ACF, CPT, taxonomy, WooCommerce, or user data.

---

# 4. Frames-first workflow

Frames is the preferred starting point for structure.

## How to use Frames

When building a common page section or template:

1. Look for an appropriate Frames pattern first.
2. Use the Frames section as the structural baseline where available.
3. Keep the underlying layout logic intact unless there is a clear reason to alter it.
4. Add project-specific BEM classes to the relevant wrapper and child elements.
5. Style through ACSS tokens and custom component CSS.
6. Do not hack Frames internals with brittle selectors.

## When not to use Frames

You may build custom Bricks structure instead of using Frames when:

- no Frames pattern fits the required content model;
- the design requires a deliberately unusual composition;
- the section is a custom ACF-powered query/template pattern;
- the Frames version would be harder to maintain than a clean custom Bricks structure.

When choosing not to use Frames, state the reason.

---

# 5. ACSS token rules

ACSS tokens are mandatory.

## Hard rule

Use ACSS tokens for all spacing, typography, color, radius, width rhythm, layout gaps, and reusable sizing decisions.

Prefer existing project tokens. Before inventing a custom variable, inspect the site’s ACSS setup.

## Preferred token categories

Use tokens from the site’s ACSS system, such as:

```css
var(--space-xs)
var(--space-s)
var(--space-m)
var(--space-l)
var(--space-xl)
var(--section-space-s)
var(--section-space-m)
var(--section-space-l)

var(--text-s)
var(--text-m)
var(--text-l)
var(--text-xl)
var(--h1)
var(--h2)
var(--h3)

var(--primary)
var(--primary-hover)
var(--secondary)
var(--accent)
var(--base)
var(--base-light)
var(--base-ultra-light)
var(--base-dark)
var(--shade)
var(--shade-light)
var(--shade-dark)
var(--white)
var(--black)

var(--radius-s)
var(--radius-m)
var(--radius-l)
var(--radius-xl)
var(--grid-gap)
```

Actual token names may differ by site. Inspect first. Use the site’s real tokens.

## Local variables are allowed only when mapped to ACSS

Good:

```css
.c-event-card {
  --card-padding: var(--space-m);
  --card-bg: var(--white);
  --card-border: var(--base-ultra-light);
  padding: var(--card-padding);
  background: var(--card-bg);
  border-color: var(--card-border);
}
```

Bad:

```css
.c-event-card {
  padding: 24px;
  background: #ffffff;
  border-color: #eeeeee;
}
```

## Hardcoded values

Avoid hardcoded `px`, `rem`, `em`, and hex values for design decisions.

Small literal values are acceptable only for technical CSS mechanics where no meaningful token exists, such as:

- `opacity: 0` / `opacity: 1`;
- `z-index` layers when documented;
- `transform: translateY(0)`;
- `line-height: 1` or `line-height: 1.1` where typography rhythm demands a ratio;
- `transition-duration` only if the site has no motion tokens;
- `border-width: 1px` only if the site has no border-width token.

When a technical literal is used, keep it minimal and intentional.

---

# 6. BEM methodology

Use deterministic BEM class naming.

## Prefixes

- `l-` for layout structures.
- `c-` for components.
- `u-` for rare utilities.
- `is-` for state classes.
- `has-` for state/feature flags.
- `js-` only for JavaScript hooks, never for styling.

## Naming pattern

```text
.c-block
.c-block__element
.c-block--modifier
.c-block__element--modifier
```

Examples:

```text
.c-event-card
.c-event-card__media
.c-event-card__date
.c-event-card__title
.c-event-card__meta
.c-event-card__price
.c-event-card__cta
.c-event-card--featured
.c-event-card--sold-out
```

## Rules

- A block must be understandable without its page context.
- An element must belong to its block.
- A modifier must describe a state, variation, or context.
- Do not create deeply nested class names like `.c-card__body__title__link`.
- Do not mix unrelated blocks inside one BEM chain.
- Do not use styling classes as content labels, for example `.red-box` or `.big-title`.
- Use semantic names, for example `.c-event-card__status` rather than `.c-event-card__orange-pill`.

---

# 7. CSS architecture

Use vanilla CSS only.

## Do not use

- Sass/SCSS-only syntax;
- Tailwind classes;
- Bootstrap layout classes;
- jQuery for UI behaviour;
- React/Vue components unless the user explicitly asks;
- raw Bricks element IDs as styling anchors;
- `!important` except as a last-resort integration patch, with comment explaining why.

## Component CSS template

Use this structure when generating CSS:

```css
/* ==========================================================================
   Component: c-event-card
   Context: Bricks + ACSS + ACF event query loop
   ========================================================================== */

.c-event-card {
  --card-padding: var(--space-m);
  --card-gap: var(--space-s);
  --card-bg: var(--white);
  --card-border: var(--base-ultra-light);
  --card-radius: var(--radius-l);
  --card-accent: var(--primary);

  container-type: inline-size;
  container-name: event-card;
  display: grid;
  gap: var(--card-gap);
  padding: var(--card-padding);
  background: var(--card-bg);
  border: 1px solid var(--card-border);
  border-radius: var(--card-radius);
}

.c-event-card__media {
  overflow: hidden;
  border-radius: var(--radius-m);
}

.c-event-card__title {
  font-size: var(--h3);
  color: var(--base-dark);
  line-height: 1.1;
}

.c-event-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-xs);
  font-size: var(--text-s);
  color: var(--base);
}

.c-event-card__cta {
  justify-self: start;
}

.c-event-card--featured {
  border-color: var(--card-accent);
}

@container event-card (max-width: 36rem) {
  .c-event-card {
    --card-padding: var(--space-s);
  }

  .c-event-card__title {
    font-size: var(--text-xl);
  }
}
```

Note: If the site has no `36rem`-style container threshold token, use the smallest necessary technical value for the query and keep the rest tokenized.

---

# 8. Responsive strategy

## Default rule

Use **container queries** for reusable components.

Use **media queries** only for viewport-level layout decisions.

## Container queries

Every reusable component block that changes internally based on available width should declare:

```css
.c-component-name {
  container-type: inline-size;
  container-name: component-name;
}
```

Then target it with:

```css
@container component-name (max-width: 40rem) {
  .c-component-name {
    /* component-specific changes */
  }
}
```

## Media queries

Use media queries only for:

- site header/nav changes;
- full-page grid changes;
- global shell layout;
- full-bleed hero composition tied to viewport;
- global typography overrides when truly needed.

Do not use media queries for every card, loop item, or component.

---

# 9. Valid `:has()` usage

Use vanilla CSS `:has()` carefully and only with real selectors.

## Good examples

```css
.l-event-grid:has(.c-event-card--featured) {
  align-items: stretch;
}

.c-event-card:has(.c-event-card__cta:hover) .c-event-card__title {
  color: var(--primary);
}

.c-pricing-card:has(.c-pricing-card__badge) {
  border-color: var(--primary);
}
```

## Bad examples

Do not use parent-looking syntax that is not valid vanilla CSS:

```css
:has(> &) {
  /* invalid as plain CSS */
}
```

Do not style unknown parents globally:

```css
:has(> .c-card):hover {
  /* too broad and unsafe */
}
```

Always anchor `:has()` to a real parent class.

---

# 10. ACF and dynamic data rules

Use ACF where the content is structured, repeated, reused, or managed by editors.

## ACF belongs in the data model when content includes

- event date, time, venue, city, speaker, artist, price, booking link, sold-out state;
- hero eyebrow, subtitle, CTA label, CTA URL;
- feature cards;
- testimonial fields;
- sponsor logos;
- page-specific alert bars;
- downloadable files;
- repeatable FAQs;
- structured media sections;
- options-page brand or global CTA fields.

## Field naming

Use clear snake_case names:

```text
event_start_date
event_end_date
event_venue
event_town
event_ticket_url
hero_eyebrow
hero_heading
hero_lede
cta_label
cta_url
featured_speaker_name
featured_speaker_bio
```

## ACF output in Bricks

- Use Bricks dynamic tags where possible.
- Use conditional display for optional fields.
- Do not output empty wrappers if a field is empty.
- For repeaters/flexible content, use Bricks query loops or appropriate ACF integration patterns.
- Escape output in PHP if custom code is required.
- Images should use IDs where possible so WordPress can handle responsive images.

## ACF planning output

When designing a dynamic page/template, provide:

```text
CPT / Template:
Field group:
Fields:
- field_name | type | required? | used in Bricks element/class
Conditional display rules:
Fallbacks:
```

---

# 11. Color and visual hierarchy

Use a controlled 60/30/10 color distribution.

## 60% dominant canvas

Usually background, section canvas, large quiet areas.

Use tokens like:

```css
var(--white)
var(--base-ultra-light)
var(--shade-ultra-light)
var(--bg-body)
var(--bg-surface)
```

Only use tokens that exist on the site.

## 30% supporting structure

Typography, borders, secondary panels, dividers, muted metadata.

Use tokens like:

```css
var(--base)
var(--base-dark)
var(--shade)
var(--base-light)
```

## 10% accent

CTAs, small badges, active states, focus indicators, important status labels.

Use tokens like:

```css
var(--primary)
var(--secondary)
var(--accent)
```

Do not smear the accent color across every card. Keep it valuable.

---

# 12. Typography rules

Use the site’s ACSS typography tokens and configured font stack.

## Required behaviour

- Inspect existing fonts before choosing new ones.
- Use heading tokens for hierarchy.
- Use body text tokens for readable copy.
- Do not default to generic fonts unless the site already uses them deliberately.
- Use strong typographic contrast where the brand allows it.
- Avoid too many font families.
- Preserve accessibility and readability.

## Type hierarchy example

```css
.c-hero__eyebrow {
  font-size: var(--text-s);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--primary);
}

.c-hero__heading {
  font-size: var(--h1);
  line-height: 1;
  color: var(--base-dark);
}

.c-hero__lede {
  max-width: 62ch;
  font-size: var(--text-l);
  color: var(--base);
}
```

---

# 13. Motion and interaction

Use motion sparingly and purposefully.

## Good motion

- button hover transitions;
- reveal states for interactive cards;
- subtle image scale on hover;
- accordion transitions;
- menu transitions;
- focus states;
- small directional shifts that support hierarchy.

## Bad motion

- constant floating blobs;
- excessive parallax;
- animations that slow down editing or performance;
- hover-only information that mobile users cannot access;
- motion with no reduced-motion fallback.

## Required reduced-motion fallback

```css
@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto;
    animation-duration: 0.01ms;
    animation-iteration-count: 1;
    transition-duration: 0.01ms;
  }
}
```

Use this globally only if the site does not already handle reduced motion.

---

# 14. Accessibility rules

Every design must remain accessible.

## Required checks

- semantic heading order;
- real buttons for actions;
- real links for navigation;
- visible focus states;
- sufficient contrast;
- meaningful link/button labels;
- alt text for meaningful images;
- decorative images marked appropriately;
- mobile tap targets large enough;
- no hover-only critical content;
- forms have labels and validation messaging;
- ARIA only when native HTML is insufficient.

## Focus state example

```css
.c-button:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: var(--space-xxs);
}
```

If the site has focus tokens, use those instead.

---

# 15. Performance rules

Keep the front end lean.

## Avoid

- unnecessary JavaScript;
- external libraries for simple interactions;
- large background videos without reason;
- uncompressed images;
- layout shifts from missing image dimensions;
- duplicated CSS in multiple Bricks elements;
- excessive nested DOM depth;
- query loops with expensive unbounded queries;
- loading sliders/carousels for static content.

## Prefer

- Bricks-native elements;
- CSS Grid/Flexbox;
- ACSS utilities and tokens;
- responsive WordPress images;
- query limits and pagination;
- CSS-only interactions where practical;
- small vanilla JS modules only when necessary.

---

# 16. JavaScript rules

Use vanilla JavaScript only when CSS/Bricks cannot handle the behaviour.

## Rules

- No jQuery unless the existing site already depends on it for that specific feature.
- Use `js-` classes for behaviour hooks.
- Do not bind JS to styling classes if avoidable.
- Scope scripts to the component.
- Guard against missing elements.
- Avoid global namespace pollution.

Example:

```js
(function () {
  const accordions = document.querySelectorAll('.js-faq-accordion');

  if (!accordions.length) return;

  accordions.forEach((accordion) => {
    const toggles = accordion.querySelectorAll('.js-faq-toggle');

    toggles.forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const item = toggle.closest('.c-faq__item');
        if (!item) return;

        item.classList.toggle('is-open');
      });
    });
  });
})();
```

---

# 17. Output format when responding to the user

When proposing or implementing a design, structure the response like this:

## 1. Design direction

State the chosen aesthetic clearly.

```text
Direction: Editorial event luxury with warm high-contrast typography and restrained accent use.
Memorable hook: Oversized date block overlapping the hero media.
```

## 2. Bricks structure

List the intended Bricks hierarchy.

```text
Section: .l-event-hero
Container: .l-event-hero__inner
Component: .c-event-hero
Elements:
- .c-event-hero__eyebrow
- .c-event-hero__heading
- .c-event-hero__lede
- .c-event-hero__meta
- .c-event-hero__actions
```

## 3. Dynamic data / ACF mapping

List fields and usage.

```text
ACF fields:
- event_start_date -> .c-event-hero__date
- event_venue -> .c-event-hero__venue
- event_ticket_url -> .c-event-hero__cta
```

## 4. CSS

Provide clean component CSS using ACSS tokens.

## 5. Optional JS

Only include JS if necessary.

## 6. QA checklist

Include implementation checks.

---

# 18. Implementation checklist

Before finalizing any page, section, or component, verify:

- [ ] The design has a clear aesthetic direction.
- [ ] Frames was considered as the structural starting point.
- [ ] Bricks structure is editable and not a single HTML blob.
- [ ] Classes use BEM with `l-`, `c-`, `u-`, `is-`, `has-`, or `js-` prefixes.
- [ ] Styling does not target Bricks generated IDs.
- [ ] ACSS tokens are used for spacing, typography, color, radius, and rhythm.
- [ ] Any local CSS variables map back to ACSS tokens.
- [ ] Container queries are used for reusable components.
- [ ] Media queries are reserved for viewport-level layout.
- [ ] `:has()` selectors are valid vanilla CSS and anchored to real parent classes.
- [ ] ACF/dynamic content is not hard-coded.
- [ ] Optional fields have fallbacks or conditional display.
- [ ] The design works on mobile, tablet, and desktop.
- [ ] Focus states are visible.
- [ ] Contrast is acceptable.
- [ ] Motion has a reduced-motion fallback where needed.
- [ ] CSS is centralized and not duplicated across random Bricks elements.
- [ ] No unnecessary JS libraries were added.
- [ ] Changes are safe, reversible, and documented.

---

# 19. Example prompt this skill should handle well

```text
Rebuild my event landing page in Bricks using ACSS and Frames. Use ACF for the event date, venue, speaker, price, and booking link. I want it to feel premium and editorial, not like a generic SaaS landing page. Give me the Bricks structure, BEM classes, ACF fields, and CSS.
```

Expected behaviour:

1. Inspect current WordPress/Bricks/ACSS/ACF setup if Novamira has access.
2. Choose a clear visual direction.
3. Start from a Frames-compatible structure if appropriate.
4. Define clean BEM class names.
5. Map content to ACF fields.
6. Generate tokenized vanilla CSS.
7. Explain exactly where the CSS and any field/template changes should live.
8. Provide a QA checklist.

---

# 20. Final principle

A good Bricks + ACSS build should feel custom, but not fragile.

A good Novamira implementation should be powerful, but not reckless.

A good AI-generated design should look intentional enough that nobody can tell it came from AI.
