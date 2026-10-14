# Voelgoed Canonical Information Architecture & Content Model
**Version:** 0.1  
**Status:** LOCKED BASELINE  
**Date:** 2026-10-12  

> This document is the canonical baseline for the Voelgoed rebuild. It consolidates the architecture decisions that have been accepted and supersedes conflicting earlier proposals where they differ from the decisions below.

---

## 1. Core Architecture Principles

### 1.1 One site, multiple entity types
Voelgoed will operate as one consolidated platform, but each major business/content system keeps the correct native data owner.

### 1.2 Separate **WHAT it is** from **WHAT it is about**
Every object has two independent dimensions:

- **WHAT it is** = its content/entity type.
- **WHAT it is about** = the shared Voelgoed topic hierarchy.

Examples:

- A WooCommerce product is a **Product** about **Nuwe Jy > Gesondheid**.
- A Tickera event is an **Event** about **Geloof**.
- A podcast episode is a **Podcast Episode** about **Huwelik > Kommunikasie**.
- A person is a **Person** associated with one or more areas of expertise/topics.

### 1.3 One source of truth
Native systems own their native data:

- WordPress owns editorial posts/pages.
- Masteriyo owns courses and learning state.
- WooCommerce owns products, pricing, stock and checkout product data.
- Tickera owns ticketed events and ticketing data.
- Paid Memberships Pro (PMPro) owns membership levels and access control.
- ACF adds Voelgoed-specific metadata and relationships.
- Custom CPTs are created only where no existing system adequately owns the entity.

### 1.4 Relationships should be automatic wherever possible
Do not manually duplicate lists of related content.

The entity that creates the relationship owns that relationship. Other pages query it dynamically.

---

# 2. Canonical Top-Level Site Structure

| Area | Canonical URL | Primary model / owner | Status |
|---|---|---|---|
| Editorial content | `/content/` | WordPress Posts | LOCKED |
| Learning | `/leer/` | Masteriyo | LOCKED |
| Shop | `/winkel/` | WooCommerce | LOCKED |
| Events | `/events/` | Tickera | LOCKED |
| Broadcasts | `/uitsendings/` | `broadcast` CPT | LOCKED |
| Podcasts | `/podsending/` | `podcast_episode` CPT | LOCKED |
| Competitions | `/kompetisies/` | Competition CPT(s) | LOCKED |
| People | `/mense/` | `person` CPT | LOCKED |
| Memberships | `/lidmaatskap/` | PMPro + WordPress landing pages | LOCKED |

### Membership examples

```text
/lidmaatskap/
/lidmaatskap/voelgoed-vriendinne/
/lidmaatskap/voelgoed-netwerk/
```

`/lidmaatskap/` is the umbrella. Individual membership brands/products may have their own child landing pages.

---

# 3. Front-End Discovery Model

Voelgoed will expose two primary ways to discover content.

## 3.1 WHAT do you want to do?

Examples:

- Lees
- Kyk
- Luister
- Leer
- Gaan na 'n event
- Koop
- Sluit aan

These map primarily to entity/content types.

## 3.2 WHAT do you need help with?

Examples:

- Nuwe Jy
- Geloof
- Self
- Werk + Geld
- Ouerskap
- Huwelik
- Mensstories

These map to the shared topic hierarchy.

The site must not force visitors to understand internal WordPress architecture.

---

# 4. Shared Topic Taxonomy

## 4.1 Canonical taxonomy

```text
content_topic
```

This is a **hierarchical taxonomy** and is the primary semantic spine of the site.

Example:

```text
Nuwe Jy
├── Gesondheid
│   ├── Voeding
│   ├── Hormone
│   └── Gewigsbestuur
├── Oefening
└── Skoonheid

Geloof
├── Gebed
├── Bybelstudie
└── Geestelike Groei
```

## 4.2 Intended coverage

The same conceptual topic hierarchy should connect:

- `/content/`
- Masteriyo courses
- WooCommerce products
- Tickera events
- broadcasts
- podcasts
- people/expertise
- competitions where relevant

Where a plugin cannot directly use `content_topic`, it must be mapped or synchronised to the canonical topic model rather than manually maintaining a separate unrelated taxonomy.

## 4.3 Topic pages are pillar pages

Topic pages are not thin archives.

They should function as rich landing pages containing, where relevant:

- editorial introduction;
- child topics;
- featured people/experts;
- articles;
- videos;
- audio;
- courses;
- products;
- upcoming events;
- podcasts;
- broadcasts;
- membership CTA;
- curated featured content.

---

# 5. `/content/` Editorial Model

## 5.1 WordPress Posts remain the editorial entity

Individual editorial items use stable URLs:

```text
/content/{slug}/
```

## 5.2 Editorial format taxonomy

Create:

```text
editorial_format
```

Initial terms:

```text
article
video
audio
```

Possible future formats may be added only if there is a real editorial requirement.

## 5.3 Format does not belong in the canonical item URL

Do not create canonical structures such as:

```text
/content/video/{slug}/
/content/audio/{slug}/
```

The canonical item remains:

```text
/content/{slug}/
```

Format-specific landing/filter pages may still exist.

## 5.4 Standalone media vs recurring programmes

Use this rule:

- Standalone written/video/audio editorial item → `/content/`
- Recurring audio programme/series → `/podsending/`
- Recurring broadcast/video programme/series → `/uitsendings/`
- Learning material/course → `/leer/`

A podcast that also has a video recording remains one Podcast Episode. Do not create a duplicate Video post merely because the episode is available on YouTube.

---

# 6. People Architecture

## 6.1 One canonical Person CPT

Create:

```text
person
```

Canonical URLs:

```text
/mense/{person-slug}/
```

Every real person exists once.

Do not create duplicate Mentor, Speaker, Expert or Artist CPTs.

## 6.2 Person data

The Person entity stores facts about the human, for example:

- name;
- profile photo;
- short bio;
- full bio;
- professional title;
- credentials;
- company/brand;
- website;
- social profiles;
- topics/expertise;
- Voelgoed-specific profile metadata.

## 6.3 Persistent classification vs contextual role

These are separate concepts.

### Persistent person classification

A person may have one or more relatively stable Voelgoed classifications, for example:

```text
person_type
├── Mentor
├── Kenner
├── Kunstenaar
└── other future types
```

A person may belong to multiple types.

### Contextual role

A contextual role describes what the person is doing **in a specific relationship**.

Examples:

- Author of an article
- Expert contributor to an article
- Speaker at an event
- MC at an event
- Panel member at an event
- Performer at an event
- Host of a podcast
- Guest on a podcast
- Presenter of a broadcast
- Instructor on a course

**Speaker is not a permanent identity.**

Example:

```text
Person: Lynette Beer
Persistent type: Mentor

Event A:
Role: Speaker

Podcast B:
Role: Guest

Broadcast C:
Role: Presenter
```

## 6.4 Person type does not determine access

A Mentor being a Mentor does not automatically make all content linked to that Mentor premium.

PMPro controls access to individual content.

The following remain separate dimensions:

```text
Person type ≠ contextual role ≠ membership access
```

---

# 7. Dynamic `/mense/` Landing Pages

Every person receives a mini landing/entity page.

The page should be dynamically assembled from relationships rather than manually maintained.

Possible sections:

- Profile/bio
- Expertise/topics
- Next appearance
- Upcoming events
- Courses
- Latest articles
- Videos/audio
- Podcast appearances
- Broadcast appearances
- Products
- Previous events
- Replay links
- Related membership/premium content where appropriate

Example:

```text
/mense/minette-visser/

MINETTE VISSER
[Bio and profile]

NEXT
Nuwe Jy-Fees 2027
Speaker
[View event]

COURSES
[Automatically queried]

LATEST CONTENT
[Automatically queried]

PODCASTS & BROADCASTS
[Automatically queried]

PAST EVENTS
Nuwe Jy-Fees 2026
[Watch replay]
```

### Critical rule

Do **not** manually maintain a repeater on the Person record listing all past events, courses, articles, etc.

The originating objects own those relationships; the Person page queries them in reverse.

---

# 8. Event Architecture

## 8.1 Tickera remains the event source of truth

Do not create a duplicate public Event CPT.

Tickera owns:

- event instance;
- ticketing;
- ticket products/availability;
- transactional event data.

ACF adds Voelgoed-specific metadata and relationships where needed.

## 8.2 Event Type

Event Type answers:

> What kind of event is this?

Examples:

- Fees
- Vertoning
- Werkswinkel
- Reis
- Funksie
- Sport
- Aanlyn

Use Tickera's native event category/type capabilities where practical.

## 8.3 Event Series

Create a recurring event-series dimension:

```text
event_series
```

It answers:

> Which recurring branded event does this occurrence belong to?

Examples:

- Nuwe Jy-Fees
- Lynette Beer Live
- Girls-Bootreis
- Ek Wil Ek Kan Ek Sal
- Fliekaand

Example:

```text
Lynette Beer Live
├── Pretoria — March 2027
├── Cape Town — June 2027
├── Bloemfontein — September 2027
└── Pretoria — March 2028
```

Each dated occurrence remains its own real Tickera Event.

## 8.4 Evergreen event-series landing pages

Recurring event brands should have an evergreen landing page that accumulates authority over time.

Example:

```text
/events/nuwe-jy-fees/
```

Individual occurrences may use:

```text
/events/nuwe-jy-fees-2027/
/events/nuwe-jy-fees-2028/
```

The evergreen page may contain:

- event history;
- next occurrence;
- past occurrences;
- galleries;
- speakers;
- FAQs;
- replay links;
- related products;
- related content.

---

# 9. Event Participation Model

Person ↔ Event is a **rich relationship** and must not be reduced to a simple list of speakers.

Create an internal, non-public relationship/junction CPT:

```text
event_participation
```

This CPT has no public archive and no public frontend URL.

## 9.1 Core fields

```text
event_participation
├── person
├── event
├── role
├── session_title
├── session_description
├── appearance_start
├── appearance_end
├── display_order
├── featured
├── replay_destination
└── optional CTA override
```

## 9.2 Contextual event roles

Examples:

```text
Speaker
Kunstenaar / Performer
MC / Aanbieder
Paneellid
Fasiliteerder
Gas
```

The same Person may have different roles at different events.

## 9.3 Automatic event-page speakers

An event page queries `event_participation` where:

```text
event = current event
```

and renders all relevant speakers/performers automatically.

## 9.4 Automatic Person event history

A Person page queries `event_participation` where:

```text
person = current person
```

and splits results into:

- upcoming events;
- past events.

Past participation may link directly to a replay destination when one exists.

Example:

```text
Past Event
Nuwe Jy-Fees 2026
Role: Speaker
Replay destination: WooCommerce Replay Product

CTA:
Watch the replay
```

---

# 10. Simple Person Relationships

Not every relationship needs a junction CPT.

Use simple ACF/Post Object relationships when the relationship itself carries little or no extra metadata.

## Articles

```text
primary_author → Person
additional_authors → Person[]
expert_contributors → Person[]
reviewed_by → Person[]
```

## Podcasts

```text
hosts → Person[]
guests → Person[]
```

## Broadcasts

```text
presenters → Person[]
guests → Person[]
```

## Courses

Conceptually:

```text
lead_instructor → Person
additional_instructors → Person[]
experts → Person[]
```

Masteriyo remains the course owner.

## Products

Where relevant:

```text
created_by → Person[]
endorsed_by → Person[]
featured_expert → Person[]
```

If a relationship later needs substantial relationship-specific metadata, it may be promoted to a junction model.

---

# 11. Broadcast Architecture

Create one CPT:

```text
broadcast
```

Each Broadcast record is an individual episode.

Do **not** create separate Broadcast and Episode CPTs.

Create:

```text
broadcast_series
```

Example:

```text
/uitsendings/
/uitsendings/reeks/twintig-twintig/
/uitsendings/twintig-twintig-episode-42/
```

Typical Broadcast data may include:

- series;
- episode number;
- broadcast date;
- presenters;
- guests;
- video/embed URL;
- transcript/show notes;
- related topic;
- related content/products/events.

---

# 12. Podcast Architecture

Create:

```text
podcast_episode
```

with:

```text
podcast_series
```

Canonical structure:

```text
/podsending/
/podsending/reeks/{series}/
/podsending/{episode-slug}/
```

Typical episode data:

- series;
- episode number;
- date;
- duration;
- audio URL;
- Spotify URL;
- Apple Podcasts URL;
- optional YouTube/video URL;
- transcript/show notes;
- hosts;
- guests;
- content topics;
- related entities.

Podcasts remain separate from `/content/` because recurring podcast episodes have their own structured metadata and series relationships.

---

# 13. Competition Architecture

Canonical section:

```text
/kompetisies/
```

Competitions use dedicated custom post type(s) where needed.

The detailed Competition vs Competition Entry model will be defined separately, but the section and concept are locked.

Competitions do not need a permanent primary-navigation slot.

Active campaigns such as Inspirasievrou may be promoted dynamically through:

- announcement bars;
- homepage placements;
- temporary menu promotion;
- relevant topic/content sections.

---

# 14. Membership Architecture

Canonical umbrella:

```text
/lidmaatskap/
```

PMPro remains the source of truth for:

- membership levels;
- access;
- user entitlements;
- protected content.

Examples:

```text
/lidmaatskap/voelgoed-vriendinne/
/lidmaatskap/voelgoed-netwerk/
```

Do not duplicate access logic in Person types, taxonomies or ACF fields unless a deliberate integration layer is later designed.

---

# 15. Leefstyl, Partners & Sponsored Content

Leefstyl remains a distinct commercial/editorial dimension rather than replacing the shared `content_topic` hierarchy.

A sponsored article can therefore simultaneously have:

```text
content_topic:
Nuwe Jy > Reis

commercial context:
Sponsored

Leefstyl vertical:
Vakansie

partner:
Partner X
```

This preserves:

1. topical relevance;
2. commercial classification;
3. partner attribution;
4. Leefstyl browsing.

Existing Leefstyl/Partner/Sponsored taxonomies are retained conceptually.

The detailed Partner entity model is **not yet locked**.

---

# 16. Cross-Sell / Upsell Relationship Engine

Use the following precedence for automated recommendations:

```text
1. Explicit item-level relationship
        ↓
2. Topic-level curated relationship
        ↓
3. Automatic exact-topic match
        ↓
4. Automatic parent-topic match
        ↓
5. Global fallback
```

## 16.1 Level 1 — explicit item relationship

Example article:

```text
Featured Product → Product X
Featured Course → Course Y
Featured Event → Event Z
```

These always win.

## 16.2 Level 2 — topic-level merchandising

ACF term fields may be added to `content_topic`, for example:

```text
featured_product
featured_course
featured_event
featured_podcast
featured_broadcast
membership_cta
```

This makes merchandising scalable by topic.

## 16.3 Level 3 — automatic exact-topic matching

If no curated relationship exists, query published entities sharing the exact `content_topic`.

## 16.4 Level 4 — parent-topic fallback

If the specific child topic has no suitable entity, climb to the parent topic.

Example:

```text
Nuwe Jy > Gesondheid > Energie
```

may fall back to:

```text
Nuwe Jy > Gesondheid
```

## 16.5 Level 5 — global fallback

Only after all higher-priority options fail should the system use global defaults.

---

# 17. ACF Options Page Responsibility

The Options Page should hold **global defaults and behaviour**, not hundreds of individual relationships.

Appropriate global settings include:

```text
Default membership CTA
Default shop CTA
Default course fallback
Default newsletter CTA

Related article count
Related product count
Related event count

Fallback behaviour
Section labels
Global promotional settings
```

Topic-specific merchandising belongs on the Topic term.

Item-specific relationships belong on the item.

---

# 18. Relationship Ownership Rules

This is a locked rule.

### The originating entity owns the relationship.

Examples:

- Article says who authored it.
- Event Participation says which Person is appearing at which Event.
- Podcast says who hosted/guested.
- Broadcast says who presented/guested.
- Course says who teaches it.
- Product says who created/endorsed it.

The Person profile does not manually repeat these lists.

This keeps the site near one source of truth.

---

# 19. Header / Navigation Skeleton

The primary navigation should remain intentionally small.

Desktop baseline:

```text
LOGO | Ontdek | Inhoud | Leer | Events | Winkel | Lidmaatskap | Search | Account
```

## 19.1 `Ontdek`

This is the **ABOUT** navigation.

It exposes top-level pillars only, for example:

```text
Nuwe Jy
Geloof
Self
Werk + Geld
Ouerskap
Huwelik
Mensstories

→ Alle onderwerpe
```

Do not expose the entire topic tree in the global navigation.

Child topics live on the relevant pillar pages.

## 19.2 `Inhoud`

This is the **media/content type** navigation.

Example:

```text
Alle inhoud
Artikels
Video's
Oudio
Podsendings
Uitsendings

Leefstyl & Vennote
Kompetisies
```

## 19.3 Direct action/commercial destinations

Keep these directly accessible:

```text
Leer
Events
Winkel
Lidmaatskap
```

## 19.4 Utilities

Keep utilities minimal:

```text
Search
Account/Profile
```

## 19.5 Mobile

Use a clear click/accordion navigation rather than deep hover-style menus.

Baseline:

```text
Search

Ontdek
Inhoud
  Artikels
  Video
  Oudio
  Podsendings
  Uitsendings

Leer
Events
Winkel
Lidmaatskap

My rekening
```

---

# 20. Canonical Data Model Summary

```text
                              VOELGOED
                                 │
              ┌──────────────────┴──────────────────┐
              │                                     │
          WHAT IS IT?                         WHAT IS IT ABOUT?
              │                                     │
              │                                content_topic
              │                                     │
    ┌─────────┼────────────┐                        │
    │         │            │                        │
 Content    Leer         Winkel                 Nuwe Jy
 Events     Podcasts     Broadcasts             Geloof
 People     Competitions Membership              Self
                                                etc.
```

People connect across the ecosystem:

```text
PERSON
  │
  ├── Article author / contributor
  ├── Course instructor
  ├── Product creator / endorser
  ├── Podcast host / guest
  ├── Broadcast presenter / guest
  └── Event participation
          ├── Event
          ├── Contextual role
          ├── Session
          ├── Time
          └── Replay destination
```

---

# 21. Locked Source-of-Truth Rules

1. Do not duplicate native plugin entities unnecessarily.
2. Do not create a second public Event CPT beside Tickera.
3. Do not create separate Mentor/Speaker/Kenner/Kunstenaar CPTs.
4. A human exists once as a `person`.
5. Persistent Person classification is separate from contextual role.
6. Speaker/Host/Author/Instructor/etc. describe a relationship, not a permanent identity.
7. PMPro alone owns membership access decisions.
8. Person pages dynamically query relationships rather than manually storing activity lists.
9. Use a junction entity when the relationship itself carries meaningful data.
10. Use simple ACF/Post Object relationships when the relationship carries little extra data.
11. `content_topic` is the canonical semantic topic hierarchy.
12. Topic pages are rich pillar/landing pages, not thin archives.
13. Canonical content URLs should remain stable even if classifications change.
14. Cross-selling should be automatic-first with explicit editorial override.
15. Options Pages hold global configuration/fallbacks, not a giant manual relationship database.

---

# 22. Items Deliberately Not Yet Locked

The following still require a separate architecture decision:

1. Final complete `content_topic` hierarchy.
2. Final Leefstyl sub-taxonomy structure.
3. Exact Partner data model/CPT decision.
4. Detailed Competition and Competition Entry model.
5. Exact Masteriyo ↔ `content_topic` integration implementation.
6. Exact ACF field-group schemas and field names.
7. Exact technical implementation of bidirectional/reverse queries.
8. Exact rewrite/permalink implementation for taxonomy/pillar pages.
9. Final SEO/schema implementation rules for each entity.
10. Final UI/component specifications for the header and mega menus.

---

# 23. Status

This document is now the **locked architecture baseline** for the Voelgoed rebuild.

Future architecture decisions should extend this model rather than introduce parallel competing structures unless there is a documented technical or business reason to revise a locked rule.
