# Editorial dimensions architecture

**Status:** Approved design for Task 4A; no runtime implementation
**Date:** 2026-10-15
**Baseline:** **origin/main** after PR #7 merge (0154c291bbdcd58397a82028a365f37293d2a24b)

This document defines editorial classification outside the semantic topic model. It does not authorize taxonomy registration, field changes, term creation, content migration, template changes, or URL changes.

## Classification boundaries

| Dimension | Question it answers | Owner |
|---|---|---|
| Entity | What kind of object is this? | Its post type or established plugin entity |
| Topic | What is this item about? | **content_topic** and its Primary Topic role |
| Editorial dimension | What editorial kind, series, marker, or time-bound initiative applies? | The dimension defined here |
| Relationship | Which other entity is connected to this item, and in what role? | ACF relationship field or the relevant domain entity |
| Access | Who may see the item? | PMPro and existing membership/access systems |
| Commercial context | Why or how is the item sponsored or associated with a partner? | A later commercial architecture phase |

An item may have values in several dimensions. One value must not be inferred from another. In particular, an entity's media, a topic label, or a partner association does not determine its editorial format or collection.

## Decisions

| Dimension | Decision | Storage recommendation | Cardinality | Entity types | Front-end visibility | Archive behavior | URL impact |
|---|---|---|---|---|---|---|---|
| **editorial_format** | **NEEDED** for ordinary editorial posts | Flat ACF Pro taxonomy, required single-select editor control | Exactly one value per standard editorial post | WordPress **post** only | Values may label or filter front-end listings | No public term archive, rewrite, or standalone term URL | None |
| **editorial_collection** | **NEEDED** for named, recurring editorial series or durable packages | Flat ACF Pro taxonomy | Zero or more; multiple only when each membership is real | WordPress **post** only | Values may label series lists and related installments | Disabled for MVP; review a landing page only when a collection has sufficient content and a user need | None |
| **editorial_flag** | **DEFERRED**; no runtime flag inventory supports an MVP taxonomy or fields | If later validated, independent ACF true/false fields on **post** | Each field independently true or false | WordPress **post** only, if approved | Internal editorial control unless a later use case approves display | None | None |
| **campaign_context** | **DEFERRED** to campaign/competition architecture | Relationship to the approved campaign or competition entity; no global taxonomy | Zero or more independent campaign relationships | No content entity is approved in Task 4A; a later model must name each supported entity type | Not a public classification label by default; a campaign entity may be public by separate decision | No default archive; campaign entity may get a landing page by separate decision | No content URL change |

The taxonomy choices for format and collection follow the canonical Information Architecture and fit repeated filtering across standard posts. ACF Pro owns their future registration under the site rules. ACF taxonomy controls should present format as a required single select and collection as a multi-select. Register both with editor UI and term-query support, while disabling public term archives and rewrite URLs. No values or registrations are being added in Task 4A.

Front-end visibility does not require a public taxonomy archive. Editorial format and collection values can appear in approved filters or lists while their term URLs remain disabled. Flags stay in editorial controls. Campaign visibility belongs to the campaign or competition entity when that later model is approved.

### Editorial format

**editorial_format** answers: “Is this standard editorial post primarily an article, video, or audio item?” The locked values are:

- **article**
- **video**
- **audio**

The intended rule is exactly one value for every ordinary editorial **post**. New standard editorial posts should require one value. Before applying the requirement to existing posts, review their format and backfill through a later migration; Task 4A assigns nothing. Text-led posts use **article**; an item created as a video-led or audio-led editorial post uses the matching value. A photo, embedded clip, or audio element inside an article does not change its format.

Entity identity takes precedence over delivery medium. A Podcast Episode remains its own entity and does not receive **editorial_format = audio**. A Voelgoed Live episode remains an **uitsending** and does not receive **editorial_format = video** or **audio**. **editorial_format** is not for **tc_events**, courses, products, people, competitions, podcast episodes, or **uitsending**.

The taxonomy is an editor and query dimension, not a public destination. Bricks may query posts by the taxonomy for Lees/Kyk/Luister navigation and filtered listings. Do not create public term archives, rewrite rules, or format-based permalinks.

### Editorial collection

**editorial_collection** answers: “Is this post one installment in a named, recurring Voelgoed editorial series or durable editorial package?” It is not a label for any phrase that happens to recur in copy.

The documentation names candidates such as GeloofsVRAAG, #StilwordSondag, Wat ek geleer het..., Hoopstories, Só oorkom ek..., and Gelukkig getroud. These are candidates for item-level and recurrence review, not an approved runtime term list. A future term must have a clear editorial owner, a durable name, and more than a one-off campaign or subject use.

Do not use this dimension for:

- semantic subjects, which remain in **content_topic**;
- Voelgoed Live series or podcast series, which belong to their own entities;
- campaigns, sponsorships, commercial verticals, or partner identities;
- a Person role such as mentor, speaker, or host;
- membership or other access restrictions.

Bricks can use the taxonomy to query a series list or related installments. Do not expose a term archive or term URL by default. A curated landing page may be approved later after content volume, navigation value, ownership, and SEO behavior are reviewed. It must not change post URLs.

### Editorial flags

Examples in the migration documents include Redakteurskeuse and Moet Lees. Runtime inspection found no such values in **content_category** or **post_tag**. The live Featured Content Manager stores selected post objects in options fields; it is curation by relationship, not proof that a general editorial flag system exists.

Do not create an **editorial_flag** taxonomy. If an editorial use case is confirmed later, represent each independent binary marker as an ACF true/false field on **post**. A checkbox list would require term governance and public taxonomy behavior that these two examples do not justify. Use Bricks meta queries only if an approved template or editor workflow needs them. No archives or URLs.

Do not convert the existing Featured Content Manager relationships into flags as part of this decision. Their topic-aware replacement is governed by the merchandising work recorded in the Legacy Decommission Register.

### Campaign context

**campaign_context** describes temporary association with a named editorial initiative. It is distinct from a semantic topic and from a durable recurring collection. A campaign may be time-bound; its content can remain evergreen after the initiative ends without inheriting the campaign as a permanent topic.

One item may relate to more than one campaign when each relationship is editorially true. The campaign record, once designed, should own its name, status, dates, and other campaign metadata. No generic **campaign** taxonomy is approved. A competition finalist or entrant should use the relevant competition and participation relationship/entity, not a campaign term on the content item.

Campaign records do not need public archives by default. A public landing page is a separate campaign UX decision. Campaign context must not change content URLs. The concept and storage are deferred; there is no campaign taxonomy or field in this phase.

## Storage and ownership

| Dimension | Why this representation fits | Owner and storage | Bricks queryability |
|---|---|---|---|
| **editorial_format** | A closed, single-value vocabulary is shared by normal posts and used to filter listings. A taxonomy gives consistent term queries without using a custom field as a second topic system. Hierarchy and public archives are unnecessary. | ACF Pro owns the taxonomy registration and editor control; terms attach only to **post**. | Yes, for post queries and filters; no public term archives. |
| **editorial_collection** | A post can belong to multiple durable series, and terms support related-installment queries. A field would duplicate series names across posts and make reuse/querying harder. | ACF Pro owns the taxonomy registration and editor control; terms attach only to **post**. | Yes, for post queries and filters; no public term archives by default. |
| **editorial_flag** | Current evidence does not show an established multi-value editorial flag system. Independent booleans are the smallest representation if separate binary controls are later approved. | Deferred. If approved, ACF Pro fields on **post**; no shared taxonomy. | Possible through ACF/meta queries if a confirmed consumer needs it. |
| **campaign_context** | Temporary campaigns need their own identity and metadata. A flat taxonomy would not own those details or distinguish competition participation relationships. | Deferred. Later model should relate content to the approved campaign or competition entity, using ACF relationship fields under the existing site rules. | Possible through relationship-aware queries once the entity model and consumer are approved. |

The canonical topic taxonomy remains the only semantic topic system. The ACF registration exception for **content_topic** is specific to Voelgoed Platform Core and does not extend to editorial dimensions.

## Decision table

| Question | Correct dimension |
|---|---|
| What is it about? | **content_topic** |
| Is it a standard article-, video-, or audio-led editorial post? | **editorial_format** |
| Is it part of a named, recurring editorial series? | **editorial_collection** |
| Is it temporarily part of an initiative? | **campaign_context** |
| Has an editor selected or marked it for a specific treatment? | Existing curation relationship, or a future **editorial_flag** field only after need is confirmed |
| Is it member-gated? | PMPro and the existing access system |
| Is it sponsored, partner-associated, or part of a commercial vertical? | Commercial context, designed in its later phase |
| Is it a podcast episode? | Podcast entity |
| Is it a Voelgoed Live episode? | **uitsending** entity; public brand Voelgoed Live at **/voelgoed-live/** |

## Boundaries with access and commercial context

Access classifications such as Premium, Members-only, or a Mentor access label belong in PMPro or the existing access system. They must not be converted to **content_topic**, **editorial_format**, **editorial_collection**, or **editorial_flag**. The current runtime **content_category** inventory contains no such terms; any historical assignments still require a membership-level and consumer audit before migration.

Commercial classifications do not describe what content is about and do not define a recurring editorial series. The live Leefstyl branch and its five child terms, along with **vennoot_kategorie** and partner mappings, remain commercial evidence. Their replacement belongs to the later commercial architecture phase. Task 4A does not design that system.

## Runtime and migration guardrails

Read-only Novamira inspection on 2026-10-15 found no registered **editorial_format** or **editorial_collection** taxonomy. The active ACF taxonomy **content_category** has 13 terms, and the seven **content_topic** roots remain separate. The five remaining topic migration seeds 514, 516, 518, 521, and 641 remain **MIGRATION_SEED**; this task does not re-review them.

The canonical Voelgoed Live lock remains internal entity **uitsending**, public brand Voelgoed Live, canonical root **/voelgoed-live/**. This decision does not begin broadcast series architecture, podcast implementation, Person migration, or event participation work.

The **content_category** taxonomy remains live until every term, relationship, and consumer has a verified destination. No production post is assigned a new editorial dimension here. Do not alter taxonomy registrations, terms, ACF groups, relationships, templates, or URLs in Task 4A.

## Task 4B recommendation

Implement only **editorial_format** and **editorial_collection** as ACF-owned, post-only taxonomies. Seed only the three already approved format values. Create no collection terms until a separate item-level review approves the recurring series list. Do not build **editorial_flag** or **campaign_context** in Task 4B; retain both as deferred decisions until a demonstrated runtime need and owner are documented.

## Canonical references

- [Canonical Information Architecture](../../repo/VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md)
- [Canonical Topic Taxonomy Migration framework](../../repo/VOELGOED_CANONICAL_TOPIC_TAXONOMY_MIGRATION_v0.1.md)
- [Taxonomy Discovery UX](../../repo/VOELGOED_TAXONOMY_DISCOVERY_UX_v0.1.md)
- [Legacy Decommission Register](LEGACY_DECOMMISSION_REGISTER.md)
- [Legacy Editorial Classification Mapping](LEGACY_EDITORIAL_CLASSIFICATION_MAPPING.md)
