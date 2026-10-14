# Voelgoed Taxonomy, Discovery & User-Facing Information Architecture
**Version:** 0.1
**Status:** LOCKED BASELINE
**Date:** 2026-10-13
**Companion to:** `VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md`

> This document locks the user-facing taxonomy and discovery principles for the Voelgoed rebuild.
> The guiding test is simple: **a first-time visitor with zero knowledge of Voelgoed must immediately understand where to go and how to find content relevant to her needs.**

---

# 1. Core UX Principle

The backend may be sophisticated.

The frontend must feel obvious.

A visitor should never need to understand:

- WordPress post types;
- custom taxonomies;
- CPTs;
- PMPro;
- ACF relationships;
- editorial-series architecture;
- internal database structure.

The visitor should primarily be able to answer two questions:

1. **What am I interested in / what do I need help with?**
2. **How do I want to consume it?**

These become the two main discovery axes.

---

# 2. The Two Discovery Axes

## 2.1 ABOUT — what do you need help with?

The canonical top-level subject pillars are:

```text
Nuwe Jy
Geloof
Self
Werk + Geld
Ouerskap
Huwelik
Mensstories
```

These map to the shared backend taxonomy:

```text
content_topic
```

## 2.2 WHAT — how do you want to engage?

Visitor-facing actions include:

```text
Lees
Kyk
Luister
Leer
Events
Koop
Sluit aan
```

These map to the appropriate entity/content systems:

```text
Lees / Kyk / Luister → Editorial content, broadcasts, podcasts
Leer               → Masteriyo
Events             → Tickera
Koop               → WooCommerce
Sluit aan          → PMPro / Membership
```

The same content ecosystem should be reachable from either axis.

Example:

```text
Self
→ Emosionele welstand
→ Lees / Kyk / Luister / Leer
```

or:

```text
Kyk
→ Self
→ Emosionele welstand
```

---

# 3. Top-Level Topic Pillars Are Locked

The seven canonical top-level `content_topic` pillars are:

```text
content_topic
├── Nuwe Jy
├── Geloof
├── Self
├── Werk + Geld
├── Ouerskap
├── Huwelik
└── Mensstories
```

These are the main semantic areas of the Voelgoed ecosystem.

They should be reusable across:

- editorial content;
- podcasts;
- broadcasts;
- courses;
- products;
- events;
- people/expertise;
- competitions where relevant.

---

# 4. Branded Pillars Must Be Explained in Plain Language

Some Voelgoed pillar names are brand-led and are not completely self-explanatory to a new visitor.

Therefore each pillar must have a short explanatory descriptor in navigation and discovery interfaces where useful.

Baseline visitor-facing language:

```text
NUWE JY
Gesondheid, voeding, fiksheid & skoonheid

GELOOF
Gebed, Bybel, geloofslewe & geestelike groei

SELF
Emosionele welstand, selfvertroue & persoonlike groei

WERK + GELD
Loopbaan, besigheid, geld & finansiële beplanning

OUERSKAP
Kinders, opvoeding, skool & gesinslewe

HUWELIK
Liefde, kommunikasie, intimiteit & verhoudingsgroei

MENSSTORIES
Ware verhale, bekende mense, hoop & inspirasie
```

The exact marketing copy may later be refined, but the rule is locked:

> **A first-time visitor must not need prior Voelgoed knowledge to understand a pillar.**

---

# 5. Taxonomy Organises the Platform; UX Expresses Human Needs

The taxonomy must remain structured and scalable.

The visitor experience may use more natural language.

Example backend topic:

```text
Self > Emosionele welstand
```

Visitor-facing needs may include:

```text
Ek voel angstig of oorweldig
Ek probeer verlies verwerk
Ek voel emosioneel uitgeput
Ek sukkel om positief te bly
Ek voel vasgevang
```

These user needs do **not** automatically need to become taxonomy terms.

They may instead be:

- curated links;
- landing-page sections;
- search shortcuts;
- editorial modules;
- filtered topic views;
- campaign entry points.

Locked principle:

> **Do not create a taxonomy term for every human problem, keyword or search phrase.**

---

# 6. Progressive Disclosure

The entire taxonomy must not be exposed in the global menu.

The visitor should see more detail only as she moves deeper into the site.

## Level 1 — Global navigation

Show only the major pillars:

```text
Nuwe Jy
Geloof
Self
Werk + Geld
Ouerskap
Huwelik
Mensstories
```

## Level 2 — Pillar page

Expose the main child topics for that pillar.

Example:

```text
SELF

Selfbeeld & selfvertroue
Emosionele welstand
Persoonlike groei
Doel & betekenis
Verhoudings & vriendskap
Kommunikasie & grense
Balans & lewensritme
```

## Level 3 — Topic hub

Expose the actual content ecosystem:

```text
Featured guidance
Latest articles
Watch
Listen
Experts
Courses
Upcoming events
Recommended resources
```

Locked principle:

> **Navigation should reveal complexity gradually instead of presenting the visitor with the complete taxonomy tree at once.**

---

# 7. Primary and Secondary Topics

This model is now locked.

Every normal editorial item should have:

```text
1 Primary Topic
0–several Secondary Topics
```

Example:

```text
ARTICLE
"Hoe bly jy positief in 'n negatiewe wêreld?"

Primary topic:
Self > Emosionele welstand

Secondary topic:
Geloof > Geloofslewe

Editorial collection:
GeloofsVRAAG
```

## 7.1 Primary Topic responsibilities

The Primary Topic should drive:

- primary breadcrumb context;
- dominant pillar/topic identity;
- default related-content priority;
- pillar reporting;
- default merchandising;
- primary visual treatment where applicable;
- main topic hub placement.

Example:

```text
Home
> Self
> Emosionele welstand
> Hoe bly jy positief...
```

## 7.2 Secondary Topics

Secondary Topics support:

- cross-discovery;
- related-content matching;
- secondary topic hubs;
- semantic connections across pillars;
- search/filtering.

They must not obscure the item's primary purpose.

---

# 8. User-Facing Presentation of Article Classification

The backend may store several structured dimensions.

The visitor should see them naturally.

Example:

```text
SELF
Emosionele welstand

Hoe bly jy positief in 'n negatiewe wêreld?

Ook oor:
Geloofslewe

Deel van die reeks:
GeloofsVRAAG
```

The concepts mean:

```text
Primary Topic        → Main reason this content is relevant
Secondary Topic      → Also relevant to
Editorial Collection → Recurring Voelgoed series/franchise
```

These must remain separate.

---

# 9. Editorial Collections Are Separate From Topics

Create/use:

```text
editorial_collection
```

This answers:

> Which recurring Voelgoed editorial franchise or series does this item belong to?

Examples from the existing content ecosystem may include:

```text
#StilwordSondag
My storie
Wat ek geleer het...
Ja, jy kan!
Hoopstories
Antwoorde op kwelvrae
Vra 'n mentor
My geloof
Mense wat inspireer
GeloofsLES
BybelANTWOORD
GeloofsPLAN
BybelTEMA
GeloofsTERAPIE
GeloofsVRAAG
BybelVROUE
Só oorkom ek...
```

These should not compete with topics.

Example:

```text
content_topic:
Geloof > Gebed

editorial_collection:
#StilwordSondag
```

or:

```text
content_topic:
Self > Emosionele welstand

editorial_collection:
Wat ek geleer het...
```

---

# 10. Editorial Collections Are Secondary Discovery

A first-time visitor should not need to understand Voelgoed's internal series names before she can find useful content.

Therefore:

- Topic/need discovery comes first.
- Collections are exposed as **Reekse**.
- Collections become meaningful after the visitor encounters them.
- Collection landing pages may still be valuable for returning users and SEO where substantial.

Example:

```text
GeloofsVRAAG
'n Reeks waarin moeilike geloofsvrae prakties beantwoord word.

[Besigtig die reeks]
```

Locked principle:

> **Collections are a secondary discovery layer, not the site's primary information architecture.**

---

# 11. Editorial Format Is Separate From Topic

Create/use:

```text
editorial_format
```

Initial values:

```text
article
video
audio
```

Visitor-facing language:

```text
Lees
Kyk
Luister
```

Examples:

```text
Self [Video]
```

must disappear as a combined classification.

Instead:

```text
content_topic:
Self

editorial_format:
video
```

Likewise:

```text
Ouerskap [Video]
```

becomes:

```text
content_topic:
Ouerskap

editorial_format:
video
```

---

# 12. Membership / Mentor Access Is Separate From Topic

Legacy category structures such as:

```text
Self MENTOR
Geloof MENTOR
Ouerskap MENTOR
Nuwe jy MENTOR
Huwelik MENTOR
Geldsake MENTOR
Werk MENTOR
```

must not be carried into the topic taxonomy.

Instead:

```text
content_topic:
Self > appropriate child topic

editorial_collection:
Mentorklas      # where applicable

Person:
relevant mentor

Access:
PMPro membership restriction
```

Locked rule:

```text
Topic ≠ Person role ≠ Editorial series ≠ Access
```

---

# 13. Commercial / Partner Classification Is Separate From Topic

Sponsored and partner content still needs its real semantic topic.

Example:

```text
content_topic:
Werk + Geld > Aftrede

commercial context:
Sponsored

Leefstyl vertical:
Finansies

partner:
Everest Advisory
```

Another example:

```text
content_topic:
Ouerskap > Skool & leer

commercial context:
Partner

Leefstyl vertical:
Aanlyn Onderwys

partner:
Wolkskool
```

Locked principle:

> **Commercial origin must never replace semantic subject classification.**

---

# 14. Campaigns and Competitions Are Separate From Topic

Terms such as:

```text
Nuwe jy-finaliste
```

must not function as topics.

Instead use the appropriate campaign/competition relationship.

Example:

```text
content_topic:
Nuwe Jy

competition:
Nuwe Jy 2027

relationship:
Finalist

editorial_format:
video
```

---

# 15. Editorial Flags Are Not Topic Taxonomies

Classification such as:

```text
Redakteurskeuse
Moet Lees
Featured
Homepage Featured
```

should generally be treated as:

- editorial flags;
- curated relationships;
- boolean fields;
- homepage/editorial controls.

They should not become indexed topic archives unless there is a deliberate editorial reason.

---

# 16. Existing Generic Post Tags Should Not Be Migrated Blindly

The current WordPress tag system does not form a coherent semantic architecture.

The rebuild should therefore **not automatically preserve old `post_tag` terms as a public taxonomy**.

Default direction:

```text
Retire legacy generic post tags
```

Tags may only be reintroduced later under explicit editorial governance if there is a clear need.

---

# 17. Topic Depth Rule

Normal topic depth should be limited to:

```text
Pillar
  ↓
Topic
  ↓
Subtopic
```

Maximum practical depth:

```text
3 levels
```

Most content should normally be classified at level 2 or 3.

Avoid structures such as:

```text
Nuwe Jy
→ Gesondheid
→ Hormone
→ Menopouse
→ Warmgloede
```

Instead, specific phrases such as `warmgloede` can remain:

- article subjects;
- search language;
- page copy;
- synonyms;
- SEO keywords.

A subtopic should exist only when there is enough real content and user value to justify a meaningful hub.

---

# 18. Pillar Pages Must Be User-Centred Hubs

A pillar page is not a WordPress archive.

It must help the visitor decide where to go next.

Example:

```text
SELF

Help vir jou emosies, selfvertroue,
verhoudings en persoonlike groei.

Waarmee kan ons jou help?

[Selfbeeld & selfvertroue]
[Emosionele welstand]
[Persoonlike groei]
[Kommunikasie & grense]
[Verhoudings & vriendskap]
[Balans & lewensritme]
```

It may also surface:

```text
Popular questions
Latest articles
Watch
Listen
Learn
Experts
Courses
Events
Recommended resources
```

---

# 19. Topic Pages Must Be Cross-Entity Hubs

A topic such as:

```text
Self > Emosionele welstand
```

must not only list WordPress posts.

It should be able to surface:

- articles;
- videos;
- podcasts;
- broadcasts;
- people/experts;
- courses;
- relevant events;
- products/resources;
- membership content where appropriate.

The shared topic model exists specifically to enable this cross-entity discovery.

---

# 20. User-Facing Navigation Language

Technical labels must remain internal.

Preferred mapping:

| Internal concept | Visitor-facing language |
|---|---|
| `content_topic` | Onderwerp |
| Top-level topic | Tema / leefarea |
| `editorial_format` | Lees / Kyk / Luister |
| `editorial_collection` | Reeks |
| `person` | Mense |
| `podcast_series` | Podsendingsreeks |
| `broadcast_series` | Program / Reeks |
| Membership level | Actual membership/product name |
| Commercial taxonomy | Usually not exposed as taxonomy; use clear disclosure such as `Geborg` |

Visitors should never encounter internal labels such as:

```text
Primary Content Topic
Secondary Taxonomy
Entity Type
Custom Post Type
```

---

# 21. Search Must Use Human Language

Search should reflect visitor intent rather than WordPress internals.

Baseline concept:

```text
SOEK VOELGOED

[ Waarmee kan ons help?                         ]

Alles
Lees
Kyk
Luister
Leer
Events
Winkel
```

Optional topic refinement:

```text
Onderwerp:

Alle
Nuwe Jy
Geloof
Self
Werk + Geld
Ouerskap
Huwelik
Mensstories
```

Do not require visitors to understand terms such as:

```text
Post Type
Taxonomy
Podcast Episode CPT
```

---

# 22. Header / Global Navigation Direction

The accepted architecture remains:

```text
Ontdek
Inhoud
Leer
Events
Winkel
Lidmaatskap
```

with utilities such as:

```text
Search
Account
```

However, the final public wording of **Inhoud** remains open for UX testing.

A possible more action-oriented alternative is:

```text
Lees & Kyk
```

with items such as:

```text
Lees
Video's
Podsendings
Uitsendings
Alle inhoud
```

This label is **not yet locked**.

The underlying rule is locked:

> Prefer visitor action language over internal database language.

---

# 23. `Ontdek` Navigation Behaviour

`Ontdek` represents the ABOUT axis.

It should show only the seven major pillars with clear descriptions.

Example:

```text
ONTDEK

Nuwe Jy
Gesondheid, voeding, fiksheid & skoonheid

Geloof
Gebed, Bybel & geestelike groei

Self
Emosies, selfvertroue & persoonlike groei

Werk + Geld
Loopbaan, besigheid & finansies

Ouerskap
Kinders, skool & gesinslewe

Huwelik
Liefde, kommunikasie & intimiteit

Mensstories
Ware stories, mense & inspirasie
```

Do not expose all child topics in the global menu.

---

# 24. First-Time Visitor Test

Every major information-architecture decision must pass this test:

> A woman who has never heard of Voelgoed arrives on the site. Can she identify where to go without learning our internal brand structure first?

A successful journey should feel like:

```text
Wat soek jy vandag?

Self
Emosies, selfvertroue & persoonlike groei

↓ click

SELF

Waarmee kan ons jou help?

Emosionele welstand
Selfbeeld & selfvertroue
Persoonlike groei
Kommunikasie & grense
...

↓ click

EMOSIONELE WELSTAND

Articles
Watch
Listen
Experts
Courses
Events
Resources
```

The complexity of the backend should remain invisible.

---

# 25. Locked UX & Taxonomy Rules

The following are now locked:

1. The backend may be complex; the frontend must remain understandable.
2. Discovery uses two main axes: **ABOUT** and **WHAT**.
3. The seven top-level topic pillars are canonical.
4. Ambiguous/branded pillar names require plain-language explanatory copy.
5. `content_topic` answers only **what the item is about**.
6. One Primary Topic is required/strongly expected for normal editorial content.
7. Secondary Topics are allowed when genuinely relevant.
8. The Primary Topic controls the item's dominant navigation and relationship context.
9. `editorial_collection` is separate from `content_topic`.
10. Collections are presented to visitors as **Reekse**.
11. Collections are secondary discovery, not primary navigation.
12. `editorial_format` is separate from topic.
13. Format uses visitor language: **Lees / Kyk / Luister**.
14. Membership/access is separate from topic and is controlled by PMPro.
15. Mentor/person relationships are separate from topic and access.
16. Commercial/partner context is separate from semantic topic.
17. Competition/campaign context is separate from semantic topic.
18. Editorial flags such as Featured/Editor's Choice are not topics.
19. Legacy generic post tags are not migrated blindly.
20. Taxonomy depth should normally not exceed three levels.
21. Do not create taxonomy terms for every keyword, problem or search phrase.
22. Pillar pages are need-driven discovery hubs, not thin archives.
23. Topic pages aggregate relevant entities across the wider Voelgoed ecosystem.
24. Global navigation reveals only the high-level structure.
25. Deeper taxonomy is progressively disclosed.
26. Search and filters use human language, not WordPress terminology.
27. The site architecture must pass the **zero prior Voelgoed knowledge** test.

---

# 26. Items Not Yet Locked

The following are deliberately still open:

1. The exact complete child-topic tree beneath all seven pillars.
2. Which candidate child topics justify level-3 subtopics.
3. Exact final wording of pillar descriptions.
4. Exact public name for the current `Inhoud` navigation item.
5. Exact UI/component design for the `Ontdek` mega-menu.
6. Exact search/filter interface.
7. Exact implementation method for storing the Primary Topic.
8. Exact migration mapping for every one of the legacy categories.

These should be resolved by testing the actual content corpus against the user-centred rules in this document.

---

# 27. Status

This document is now the **locked user-facing taxonomy and discovery baseline** for the Voelgoed rebuild.

Together:

```text
Document 1
Canonical Information Architecture & Content Model
        +
Document 2
Taxonomy, Discovery & User-Facing Information Architecture
```

form the current architectural source of truth.

The next design task is to define and pressure-test the **complete child-topic tree under each of the seven pillars** using:

1. actual existing content volume;
2. first-time visitor clarity;
3. future editorial usefulness;
4. cross-entity usefulness;
5. sufficient substance to support a meaningful topic hub.
