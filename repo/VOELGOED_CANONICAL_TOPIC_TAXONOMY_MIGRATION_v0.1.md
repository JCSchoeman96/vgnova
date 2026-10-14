# Voelgoed Canonical Topic Taxonomy & Legacy Migration Framework
**Version:** 0.1
**Status:** LOCKED BASELINE
**Date:** 2026-10-13
**Companion documents:**
- `VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md`
- `VOELGOED_TAXONOMY_DISCOVERY_UX_v0.1.md`

> This document locks the exact `content_topic` hierarchy for the Voelgoed rebuild and defines the rules for migrating the legacy WordPress taxonomy into the new model.
>
> It is the canonical implementation contract for topic classification.

---

# 1. Purpose

The old Voelgoed WordPress category system mixes multiple unrelated concepts in a single taxonomy, including:

- actual subjects/topics;
- editorial series;
- Mentor/premium status;
- media format;
- partner/sponsored content;
- campaigns/competitions;
- editorial flags.

The rebuild must separate these dimensions.

The new topic taxonomy answers only:

> **What is this item about?**

Everything else belongs in its own taxonomy, relationship, field or native plugin system.

---

# 2. Canonical Topic Taxonomy

Internal taxonomy key:

```text
content_topic
```

Visitor-facing concept:

```text
Onderwerp
```

The taxonomy is hierarchical.

Normal maximum depth:

```text
Pillar
  → Topic
    → Subtopic
```

Maximum practical depth:

```text
3 levels
```

---

# 3. Locked Top-Level Pillars

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

These seven pillars are canonical.

---

# 4. Locked Complete Topic Tree

## 4.1 Nuwe Jy

Visitor meaning:

> Gesondheid, voeding, fiksheid & skoonheid

```text
NUWE JY
│
├── Gesondheid
│
├── Voeding & gewig
│   ├── Gesonde eetgewoontes
│   └── Gewigsbestuur
│
├── Oefening & fiksheid
│
├── Skoonheid & versorging
│   ├── Velsorg
│   ├── Grimering
│   └── Hare & lyfsorg
│
└── Resepte
```

### Intent

This pillar covers:

- general health and wellbeing;
- women-specific health;
- nutrition;
- weight management;
- exercise;
- beauty;
- skin;
- makeup;
- body/hair care;
- recipes.

Do not automatically create separate taxonomy terms for every health condition, symptom or beauty concern.

---

## 4.2 Geloof

Visitor meaning:

> Gebed, Bybel, geloofslewe & geestelike groei

```text
GELOOF
│
├── Gebed
├── Bybel
├── Geloofslewe
├── Geestelike groei
├── Vrae oor geloof
└── Hoop & vertroue
```

### Intent

This pillar covers the actual faith subject.

Branded series such as:

```text
GeloofsVRAAG
GeloofsPLAN
GeloofsLES
BybelANTWOORD
BybelTEMA
BybelVROUE
#StilwordSondag
```

are **not** topic terms.

They belong in:

```text
editorial_collection
```

---

## 4.3 Self

Visitor meaning:

> Emosionele welstand, selfvertroue & persoonlike groei

```text
SELF
│
├── Emosionele welstand
├── Selfbeeld & selfvertroue
├── Persoonlike groei
├── Doel & betekenis
├── Verhoudings & grense
└── Stres & balans
```

### Intent

Human needs such as:

```text
angs
verlies
hartseer
uitbranding
eensaamheid
selftwyfel
grense
vergelyking
```

do not automatically become taxonomy terms.

They may instead be expressed through:

- article subjects;
- curated links;
- landing-page sections;
- search shortcuts;
- synonyms;
- editorial copy.

---

## 4.4 Werk + Geld

Visitor meaning:

> Loopbaan, besigheid, geld & finansiële beplanning

```text
WERK + GELD
│
├── Werk
│   ├── Loopbaan
│   ├── Besigheid & entrepreneurskap
│   └── Leierskap & produktiwiteit
│
└── Geld
    ├── Begroting & geldbestuur
    ├── Skuld & krediet
    ├── Spaar & beleggings
    ├── Aftrede
    └── Boedels & testamente
```

### Intent

`Werk` and `Geld` form the two primary user-facing branches.

This allows the visitor to choose:

```text
Help my met my werk/besigheid
```

or:

```text
Help my met my geld/finansies
```

without exposing unnecessary complexity.

---

## 4.5 Ouerskap

Visitor meaning:

> Kinders, opvoeding, skool & gesinslewe

```text
OUERSKAP
│
├── Kinderontwikkeling
├── Opvoeding & dissipline
├── Emosies & selfvertroue
├── Skool & leer
├── Tegnologie & skermtyd
├── Gesinslewe
└── Ma-wees & balans
```

### Intent

The main taxonomy is organised around the parent's need rather than only the child's age.

Age/life-stage facets such as:

```text
baba
kleuter
laerskool
tiener
```

must not be added to `content_topic` unless a later documented need justifies a separate model.

---

## 4.6 Huwelik

Visitor meaning:

> Liefde, kommunikasie, intimiteit & verhoudingsgroei

```text
HUWELIK
│
├── Kommunikasie
├── Liefde & verbinding
├── Konflik & herstel
├── Vertroue & ontrouheid
├── Intimiteit
├── Geld & huwelik
└── Saam groei
```

### Intent

These topics are problem/need oriented.

Example:

```text
Article:
"Skuld plaas druk op ons huwelik"

Primary:
Huwelik > Geld & huwelik

Secondary:
Werk + Geld > Geld > Skuld & krediet
```

---

## 4.7 Mensstories

Visitor meaning:

> Ware verhale, bekende mense, hoop & inspirasie

```text
MENSSTORIES
│
├── Bekende mense
└── Ware lewensverhale
```

### Intent

This pillar should remain deliberately small.

The story's actual subject is carried by Primary/Secondary Topics elsewhere in the tree.

Example:

```text
Article:
"Só oorkom ek ... verlies"

Primary:
Mensstories > Ware lewensverhale

Secondary:
Self > Emosionele welstand
Geloof > Hoop & vertroue

Editorial Collection:
Só oorkom ek...
```

Do not recreate legacy categories such as:

```text
Hoopstories
My storie
Mense wat inspireer
Wat ek geleer het...
```

as child topics when they are actually editorial collections.

---

# 5. Primary and Secondary Topic Rule

This is locked.

Every normal editorial item should have:

```text
1 Primary Topic
0–several Secondary Topics
```

## 5.1 Primary Topic

The Primary Topic is the item's dominant semantic location.

It drives:

- breadcrumb context;
- main topic identity;
- default related-content matching;
- primary pillar reporting;
- default merchandising;
- primary topic hub placement;
- default pillar visual treatment where applicable.

## 5.2 Secondary Topics

Secondary Topics represent additional genuine subject relevance.

They support:

- cross-discovery;
- related content;
- cross-pillar relationships;
- search/filtering;
- secondary topic hubs.

Do not assign weak or speculative Secondary Topics merely to increase exposure.

---

# 6. Editor Assignment Rule

Editors select the **most specific useful topic**.

Do not manually assign all ancestors.

Example:

```text
Nuwe Jy > Skoonheid & versorging > Velsorg
```

is sufficient.

Do not additionally select:

```text
Nuwe Jy
Skoonheid & versorging
```

The hierarchy already provides those ancestors.

Likewise:

```text
Werk + Geld > Geld > Aftrede
```

is sufficient.

---

# 7. Topic Creation Rule

A new topic/subtopic may only be added when it passes all of these tests:

1. A first-time visitor immediately understands the label.
2. It represents a real subject, not a format, series, role or campaign.
3. There is enough existing or planned content to support a useful hub.
4. The distinction improves navigation or discovery.
5. It is materially different from existing topics.
6. It does not create unnecessary taxonomy depth.

Do not create terms only because:

- a keyword has search volume;
- one article mentions it;
- a marketing campaign uses the phrase;
- a legacy category already exists;
- an editor wants an additional label.

---

# 8. Dimensions Explicitly Excluded From `content_topic`

The following must remain separate.

## 8.1 Editorial Collection

```text
editorial_collection
```

Examples:

```text
GeloofsVRAAG
#StilwordSondag
Wat ek geleer het...
Hoopstories
Só oorkom ek...
Gelukkig getroud
```

Answers:

> Which recurring editorial series is this part of?

---

## 8.2 Editorial Format

```text
editorial_format
```

Values:

```text
article
video
audio
```

Visitor language:

```text
Lees
Kyk
Luister
```

---

## 8.3 Membership Access

Owned by:

```text
PMPro
```

Examples of legacy terms that must not remain topics:

```text
Self MENTOR
Geloof MENTOR
Nuwe jy MENTOR
Ouerskap MENTOR
Huwelik MENTOR
Geldsake MENTOR
Werk MENTOR
```

---

## 8.4 Person Relationships

Owned by explicit Person relationships.

Examples:

```text
author
mentor
expert
speaker
guest
host
instructor
```

These are not topic terms.

---

## 8.5 Commercial / Partner Context

Examples:

```text
Sponsored
Partner
Leefstyl vertical
Partner identity
```

These are separate from semantic subject.

---

## 8.6 Campaign / Competition Context

Examples:

```text
Nuwe Jy finalist
Competition participant
Campaign entrant
```

These belong in the Competition/Campaign model.

---

## 8.7 Editorial Flags

Examples:

```text
Redakteurskeuse
Moet Lees
Featured
Homepage Featured
```

These should normally be editorial controls/flags, not indexed subject taxonomies.

---

# 9. Legacy Migration Classification Framework

Every legacy WordPress category must be assigned one migration action.

Allowed actions:

```text
DIRECT MAP
SPLIT / RECLASSIFY
BECOMES COLLECTION
BECOMES FORMAT
BECOMES ACCESS
BECOMES COMMERCIAL
BECOMES CAMPAIGN
BECOMES FLAG
RETIRE
```

---

# 10. DIRECT MAP

Use when a legacy category cleanly represents one new topic.

Examples:

```text
Velsorg
→ Nuwe Jy > Skoonheid & versorging > Velsorg

Boedels en testamente
→ Werk + Geld > Geld > Boedels & testamente

Resepte
→ Nuwe Jy > Resepte
```

The article may still need Secondary Topics, but the old category gives a reliable Primary Topic starting point.

---

# 11. SPLIT / RECLASSIFY

Use when one legacy category contains articles covering multiple genuine subjects.

Examples may include broad legacy terms such as:

```text
Self
Gesond
Geldsake
Ouerskap
Huwelik
```

Each article must be classified based on its actual content.

Example:

```text
OLD:
Self

ARTICLE:
"Hoe stel ek beter grense?"

NEW:
Self > Verhoudings & grense
```

Another article from the same old category may become:

```text
Self > Emosionele welstand
```

This must not be handled as a blind one-to-one migration.

---

# 12. BECOMES COLLECTION

Use when the legacy category represents a recurring editorial franchise.

Examples:

```text
GeloofsVRAAG
→ editorial_collection: GeloofsVRAAG

#StilwordSondag
→ editorial_collection: #StilwordSondag

Hoopstories
→ editorial_collection: Hoopstories

Wat ek geleer het...
→ editorial_collection: Wat ek geleer het...
```

Each article must additionally receive its correct semantic `content_topic`.

---

# 13. BECOMES FORMAT

Use for legacy categories that encode media format.

Example:

```text
Self [Video]
```

becomes:

```text
editorial_format:
video

content_topic:
actual Self topic determined from article
```

Likewise:

```text
Ouerskap [Video]
```

is not migrated as a topic.

---

# 14. BECOMES ACCESS

Use when the legacy category primarily represents Mentor/member-only access.

Example:

```text
Self MENTOR
```

becomes:

```text
content_topic:
actual semantic topic

editorial_collection:
Mentorklas where applicable

Person:
relevant mentor

Access:
PMPro restriction
```

No `MENTOR` term should exist inside `content_topic`.

---

# 15. BECOMES COMMERCIAL

Use where the old category represents sponsorship, partner content or a Leefstyl commercial vertical.

Examples:

```text
Promosies
Aanlyn onderwys-vennoot
Natuurlik gesond-vennoot
Vakansie-vennoot
Finansies-vennote
Doen goed-vennoot
```

Each item must still receive its true `content_topic`.

Example:

```text
OLD:
Aanlyn onderwys-vennoot

NEW:
content_topic:
Ouerskap > Skool & leer

commercial context:
Partner

Leefstyl vertical:
Aanlyn Onderwys

partner:
relevant Partner entity
```

---

# 16. BECOMES CAMPAIGN

Use when the legacy term exists because of a competition or campaign.

Example:

```text
Nuwe jy-finaliste
```

becomes something such as:

```text
content_topic:
appropriate Nuwe Jy topic

competition:
Nuwe Jy [year]

relationship:
Finalist
```

The exact competition data model is defined separately.

---

# 17. BECOMES FLAG

Use for editorial curation terms.

Examples:

```text
Redakteurskeuse
Moet Lees
Featured
```

These should become:

- boolean fields;
- editorial controls;
- curated relationships;

rather than public topic terms.

---

# 18. RETIRE

Use when the legacy category:

- has no useful semantic meaning;
- duplicates another dimension;
- is obsolete;
- is an implementation artefact;
- is no longer useful to visitors or editors.

Retirement does not mean deleting content.

It means the legacy classification is not preserved in the new taxonomy.

---

# 19. Migration Order of Operations

For each existing post:

```text
1. Identify the actual subject of the article.
2. Assign one Primary Topic.
3. Assign genuine Secondary Topics where relevant.
4. Map legacy series → editorial_collection.
5. Map legacy video/audio indicator → editorial_format.
6. Map Mentor/member status → PMPro/access + Person relationship.
7. Map partner/sponsored status → commercial model.
8. Map campaign/competition status → campaign relationship.
9. Map editorial flags separately.
10. Retire obsolete legacy classification.
```

---

# 20. Migration Example — Mentor Content

```text
OLD

Category:
Self MENTOR

Article:
"Hoe hanteer jy emosionele uitputting?"

NEW

Primary Topic:
Self > Emosionele welstand

Editorial Collection:
Mentorklas

Person:
Relevant Mentor

Access:
PMPro → relevant membership
```

---

# 21. Migration Example — Faith Series

```text
OLD

Category:
GeloofsVRAAG

Article:
"Waarom antwoord God nie altyd my gebede nie?"

NEW

Primary Topic:
Geloof > Gebed

Secondary Topic:
Geloof > Vrae oor geloof

Editorial Collection:
GeloofsVRAAG
```

---

# 22. Migration Example — Cross-Pillar Content

```text
ARTICLE:
"Hoe skuld spanning in jou huwelik veroorsaak"

Primary Topic:
Huwelik > Geld & huwelik

Secondary Topic:
Werk + Geld > Geld > Skuld & krediet
```

The article should not be forced to choose only one conceptual area.

The Primary Topic establishes the dominant user journey.

---

# 23. Migration Example — Person Story

```text
ARTICLE:
"Só oorkom ek ... verlies"

Primary Topic:
Mensstories > Ware lewensverhale

Secondary Topics:
Self > Emosionele welstand
Geloof > Hoop & vertroue

Editorial Collection:
Só oorkom ek...
```

This preserves the story format while making its actual subjects discoverable.

---

# 24. Migration Example — Sponsored Financial Content

```text
ARTICLE:
Financial planning after age 55

Primary Topic:
Werk + Geld > Geld > Aftrede

Secondary Topic:
Werk + Geld > Geld > Spaar & beleggings

Commercial Context:
Sponsored

Partner:
Relevant financial partner
```

Sponsorship never replaces the real topic.

---

# 25. Migration Quality Rules

A migrated post is not considered complete unless:

1. It has an appropriate Primary Topic.
2. Its semantic classification makes sense without knowing the old site.
3. Series/format/access/commercial status are not encoded in `content_topic`.
4. Secondary Topics are genuinely relevant.
5. The selected topic is the most specific useful term.
6. It has not been over-classified.
7. Its classification passes the first-time visitor test.

---

# 26. Automation / AI-Assisted Migration

The 1,000+ post migration may be AI-assisted.

However:

- legacy category alone must not determine the new topic;
- article title, body, metadata and old category should all be considered;
- confidence should be recorded;
- ambiguous classifications should be reviewable;
- the canonical taxonomy in this document must be treated as a closed allowed set during migration.

Recommended result shape:

```text
post_id
title
old_category
primary_topic
secondary_topics[]
editorial_collection
editorial_format
commercial_context
access_context
campaign_context
confidence
review_required
```

This allows the migration to be audited before writing changes back into WordPress.

---

# 27. Topic Governance After Launch

Editors should not freely create new `content_topic` terms.

New topic requests should be reviewed against the Topic Creation Rule.

Preferred governance:

```text
Editors:
assign existing topics

Architecture/Admin:
approve/create new topics
```

This prevents the new taxonomy from degrading into the same problem as the legacy taxonomy.

---

# 28. Locked Rules

The following are now locked:

1. `content_topic` answers only what an entity is about.
2. The complete seven-pillar topic tree in this document is canonical.
3. Maximum normal depth is three levels.
4. Editors select the most specific useful term rather than every ancestor.
5. One Primary Topic is expected for normal editorial content.
6. Genuine Secondary Topics are allowed.
7. Series belong in `editorial_collection`.
8. Article/video/audio belongs in `editorial_format`.
9. Mentor/member access belongs in PMPro/access logic.
10. People/roles belong in Person relationships.
11. Partner/sponsored status belongs in the commercial model.
12. Competition/campaign identity belongs in campaign relationships.
13. Editorial flags are not subject topics.
14. Legacy categories must be explicitly classified by migration action.
15. Broad legacy categories must be reclassified article-by-article where required.
16. Existing legacy taxonomy must not be blindly imported.
17. New topic creation is governed, not free-form.
18. Every classification must pass the zero-prior-Voelgoed-knowledge test.

---

# 29. Items Still To Produce

This document locks the taxonomy and migration rules.

The following implementation artefacts are still required:

1. Full legacy category → migration-action matrix.
2. Full 1,105-post proposed migration dataset.
3. Confidence/review report for ambiguous posts.
4. Final WordPress/ACF taxonomy registration specification.
5. Primary Topic storage implementation.
6. Migration/import script specification.
7. Redirect/SEO migration plan where legacy category archives currently receive organic traffic.

These are implementation outputs, not architecture changes.

---

# 30. Canonical Document Set

The architecture is now split deliberately into three locked documents:

```text
DOCUMENT 1
Voelgoed Canonical Information Architecture & Content Model
→ entities, URLs, ownership, relationships

DOCUMENT 2
Voelgoed Taxonomy, Discovery & User-Facing Information Architecture
→ visitor discovery model and UX rules

DOCUMENT 3
Voelgoed Canonical Topic Taxonomy & Legacy Migration Framework
→ exact topic tree and migration contract
```

Together these documents form the current source of truth for the Voelgoed rebuild.
