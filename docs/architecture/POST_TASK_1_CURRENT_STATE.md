# Voelgoed current-state architecture after Task 1, Task 1A, and Task 1B

Inspection date: 2026-10-14
Site: vgnova at http://localhost:10039/
WordPress: 7.1.3
Status: Factual snapshot after Task 1 implementation, Task 1A housekeeping, and Task 1B taxonomy refinement.

This document describes the current staging site. It is not a proposal for later architecture. WordPress and ACF state were inspected through Novamira. Task 1A changed documentation only. Task 1B removed one unassigned canonical topic and updated its seed definition.

## 11.1 Executive summary

Task 1 introduced the canonical hierarchical taxonomy content_topic. Task 1B removed Vrouegesondheid after a fresh check found no assignments, leaving seven roots and 55 terms. The taxonomy is registered by the source-controlled Voelgoed Platform Core module and has no assignments.

The older content_category taxonomy remains live with 13 terms, current relationships, and current code and template dependencies. The site keeps both taxonomies in parallel while the architecture owner reviews the baseline. No legacy content_category term or relationship has been migrated or removed.

The two taxonomies are independent. No synchronization between content_category and content_topic exists in the Platform Core module or the nine FluentSnippets source files inspected. The existing HappyFiles sync remains between content_category and hf_cat_post.

## 11.2 Canonical taxonomy state

| Property | Current state |
|---|---|
| Taxonomy | content_topic |
| Owner | Voelgoed Platform Core |
| Terms | 55 |
| Root pillars | 7 |
| Assignments | 0 |
| Public rewrite prefix | /onderwerp/ |
| Rewrite mode | Hierarchical paths, with_front disabled |

Complete canonical hierarchy:

    content_topic
    ├── Nuwe Jy (nuwe-jy)
    │   ├── Gesondheid (gesondheid)
    │   ├── Voeding & gewig (voeding-gewig)
    │   │   ├── Gesonde eetgewoontes (gesonde-eetgewoontes)
    │   │   └── Gewigsbestuur (gewigsbestuur)
    │   ├── Oefening & fiksheid (oefening-fiksheid)
    │   ├── Skoonheid & versorging (skoonheid-versorging)
    │   │   ├── Velsorg (velsorg)
    │   │   ├── Grimering (grimering)
    │   │   └── Hare & lyfsorg (hare-lyfsorg)
    │   └── Resepte (resepte)
    ├── Geloof (geloof)
    │   ├── Gebed (gebed)
    │   ├── Bybel (bybel)
    │   ├── Geloofslewe (geloofslewe)
    │   ├── Geestelike groei (geestelike-groei)
    │   ├── Vrae oor geloof (vrae-oor-geloof)
    │   └── Hoop & vertroue (hoop-vertroue)
    ├── Self (self)
    │   ├── Emosionele welstand (emosionele-welstand)
    │   ├── Selfbeeld & selfvertroue (selfbeeld-selfvertroue)
    │   ├── Persoonlike groei (persoonlike-groei)
    │   ├── Doel & betekenis (doel-betekenis)
    │   ├── Verhoudings & grense (verhoudings-grense)
    │   └── Stres & balans (stres-balans)
    ├── Werk + Geld (werk-geld)
    │   ├── Werk (werk)
    │   │   ├── Loopbaan (loopbaan)
    │   │   ├── Besigheid & entrepreneurskap (besigheid-entrepreneurskap)
    │   │   └── Leierskap & produktiwiteit (leierskap-produktiwiteit)
    │   └── Geld (geld)
    │       ├── Begroting & geldbestuur (begroting-geldbestuur)
    │       ├── Skuld & krediet (skuld-krediet)
    │       ├── Spaar & beleggings (spaar-beleggings)
    │       ├── Aftrede (aftrede)
    │       └── Boedels & testamente (boedels-testamente)
    ├── Ouerskap (ouerskap)
    │   ├── Kinderontwikkeling (kinderontwikkeling)
    │   ├── Opvoeding & dissipline (opvoeding-dissipline)
    │   ├── Emosies & selfvertroue (emosies-selfvertroue)
    │   ├── Skool & leer (skool-leer)
    │   ├── Tegnologie & skermtyd (tegnologie-skermtyd)
    │   ├── Gesinslewe (gesinslewe)
    │   └── Ma-wees & balans (ma-wees-balans)
    ├── Huwelik (huwelik)
    │   ├── Kommunikasie (kommunikasie)
    │   ├── Liefde & verbinding (liefde-verbinding)
    │   ├── Konflik & herstel (konflik-herstel)
    │   ├── Vertroue & ontrouheid (vertroue-ontrouheid)
    │   ├── Intimiteit (intimiteit)
    │   ├── Geld & huwelik (geld-huwelik)
    │   └── Saam groei (saam-groei)
    └── Mensstories (mensstories)
        ├── Bekende mense (bekende-mense)
        └── Ware lewensverhale (ware-lewensverhale)

Runtime checks confirmed the exact root names and parent chains. The term list contains no Leefstyl and no unexpected -2 slugs. All term descriptions are empty.

Vrouegesondheid is absent. Gesondheid remains under Nuwe Jy and has no child terms.

## 11.3 Attached object types

content_topic is registered against exactly these current object types:

| Object type | Intended topic use |
|---|---|
| post | Editorial subject |
| mto-course | Course subject |
| product | Product subject and relevance |
| tc_events | Event subject |
| medewerker | Expertise and relevance areas |
| uitsending | Broadcast subject |
| kompetisie | Competition or campaign subject where relevant |

Registration does not mean existing content has been classified. All seven types currently have zero content_topic relationships.

## 11.4 Legacy taxonomy state

| Property | Current state |
|---|---|
| Taxonomy | content_category |
| Status | Live legacy taxonomy |
| Terms | 13 |
| Direct relationships | 8 across 7 objects |
| Leefstyl | Present |
| Cleanup or migration | None |

Current term tree:

    content_category
    ├── Nuwe Jy (nuwe-jy)
    ├── Geloof (geloof)
    ├── Self (self)
    ├── Werk + Geld (werk-geld)
    ├── Ouerskap (ouerskap)
    ├── Huwelik (huwelik)
    ├── Mensstories (mensstories)
    └── Leefstyl (leefstyl)
        ├── Aanlyn Onderwys (aanlyn-onderwys)
        ├── Doen Goed (doen-goed)
        ├── Finansies (finansies)
        ├── Natuurlik Gesond (natuurlik-gesond)
        └── Vakansie (vakansie)

Leefstyl and its children remain legacy category and partner-lane structures. They are not part of the canonical content_topic model.

Current assigned relationships:

| Legacy term | Object assignments |
|---|---|
| Geloof | post 516 |
| Mensstories | post 521 |
| Natuurlik Gesond | product 523 |
| Nuwe Jy | post 514, product 523 |
| Ouerskap | post 518 |
| Self | post 641 |
| Werk + Geld | post 519 |
| Huwelik, Leefstyl, Aanlyn Onderwys, Doen Goed, Finansies, Vakansie | none |

Product 523 has two legacy terms, Nuwe Jy and Natuurlik Gesond. Current assignments match the Task 1 snapshot.

content_category remains attached to:

    post
    product
    tc_events
    mto-course
    medewerker
    uitsending
    kompetisie
    kompetisie_entry
    testimonial
    team_member

No assignments were copied to content_topic. Task 1B changed no legacy terms or assignments.

## 11.5 Parallel-state diagram

    CURRENT TEMPORARY STATE

    LEGACY

    content_category
    ├── current 13 terms
    ├── current assignments
    ├── existing Bricks dependencies
    ├── Featured Content Manager
    ├── Vennoot mapping
    └── HappyFiles sync
             |
             | NOT MIGRATED YET
             v
    CANONICAL

    content_topic
    ├── 7 roots
    ├── 55 terms
    ├── 0 assignments
    └── future semantic source of truth

## 11.6 Known legacy dependencies

These live systems require review before or during a later controlled migration:

- Featured Content Manager. Its ACF options group contains eight single Post Object fields: Featured Nuwe Jy, Featured Geloof, Featured Self, Featured Werk Geld, Featured Ouerskap, Featured Huwelik, Featured Mensstories, and Featured Leefstyl. Each field accepts a product, course, or event. The manager still reflects the old pillar model and has not been converted to term-based merchandising.
- Kategorie Vennoot Mapping. The active ACF field linked_vennoot_kategorie is attached to content_category terms and selects a vennoot_kategorie term.
- Vennoot block selection logic. The active shortcode can resolve a partner from a manual override, direct vennoot_kategorie assignment, or a mapped content_category term.
- HappyFiles sync. FluentSnippets file 6-taxonomy-posts-happyfiles.php synchronizes content_category terms and post assignments with hf_cat_post.
- Bricks taxonomy and archive queries. Review category archive conditions, taxonomy filters, article conditions, and templates that query content_category.
- Legacy taxonomy attachments. content_category is still registered on kompetisie_entry, testimonial, and team_member as well as the current topic types.
- Article display helpers. An active FluentSnippets helper obtains the first content_category term for article display.
- No content_topic sync. The inspected Platform Core module and nine FluentSnippets source files contain no content_topic synchronization logic.

Task 1B changed none of these dependencies.

## 11.7 Current people architecture

medewerker is the active public contributor CPT and has five published records. Its taxonomies are medewerker_rol, with nine terms, and medewerker_groep, with five terms.

team_member remains a separate registered legacy CPT. It has no published records and remains attached to content_category. It is not attached to content_topic.

No people cleanup, role taxonomy cleanup, or event-participation migration has started. The event_participation taxonomy and post type are not registered.

## 11.8 Current events architecture

Tickera tc_events is the current event source and has 17 published events. event_category is the event-kind taxonomy and has eight terms.

The active ACF group Event Medewerkers adds event_medewerkers, a multi-value relationship field targeting medewerker. This is the current simple event-to-person relationship.

No event_participation model is registered, and no event relationship migration has started.

## 11.9 Current broadcast architecture

uitsending is the current Voelgoed Live CPT and has five published episodes.

Two taxonomy generations remain attached to it:

| Current taxonomy | Terms | Legacy taxonomy | Terms |
|---|---:|---|---:|
| broadcast_series | 3 | uitsending_series | 3 |
| broadcast_type | 9 | uitsending_type | 4 |

The Broadcast: Episode Details ACF field group is active on uitsending. It contains ten fields, including broadcast state, dates, replay and live URLs, episode number, hosts, guests, and related content.

Podcasts remain represented inside the uitsending model through format terms. There is no separate podcast or podcast_episode CPT. Broadcast taxonomy consolidation has not started.

## 11.10 Current commercial and partner architecture

vennoot remains the partner CPT. vennoot_kategorie remains the separate commercial taxonomy and is attached to vennoot, post, and page.

The ACF group Kategorie Vennoot Mapping stores linked_vennoot_kategorie on content_category terms. The active Vennoot block also supports direct category assignment and the manual_vennoot override. Leefstyl and its child terms remain present in the legacy category model, including existing partner-lane mappings.

No commercial or partner refactor has started.

## 11.11 Current membership architecture

PMPro remains the membership and access-control source. Voelgoed Member Perks remains the separate perk and benefit subsystem.

Task 1, Task 1A, and Task 1B did not change PMPro or Member Perks.

## 11.12 Current merchandising architecture

Featured Content Manager remains an ACF options page built around the older pillar model. Its eight fields are:

    Featured Nuwe Jy
    Featured Geloof
    Featured Self
    Featured Werk Geld
    Featured Ouerskap
    Featured Huwelik
    Featured Mensstories
    Featured Leefstyl

Each field selects one product, course, or event. The manager has not been converted to term-based layered merchandising.

## 11.13 Current URL state

Current registered routes and permalink settings include:

| Area | Current path |
|---|---|
| Editorial post single base | /content/{slug}/ |
| Editorial index | No registered post archive or published content page at /content/ |
| Course single | /leer/{slug}/ |
| Course archive | /kursusse/ |
| Product single | /winkel/{slug}/ |
| Product archive | /produkte/ |
| Event single and archive | /events/{slug}/ and /events/ |
| Voelgoed Live single and archive | /voelgoed-live/{slug}/ and /voelgoed-live/ |
| Medewerker profile and archive | /medewerkers/{slug}/ and /medewerkers/ |
| Competition single and archive | /kompetisie/{slug}/ and /kompetisie/ |
| Canonical topic term | /onderwerp/{hierarchical-term-path}/ |
| Legacy content_category term | /content_category/{term}/ |

The WordPress permalink structure is /content/%postname%/. The post type post has no archive, and no page with slug content is published. Course and shop archives resolve to /kursusse/ and /produkte/. The /leer/ and /winkel/ paths are single-item bases.

Canonical future routes such as /uitsendings/, /podsending/, /mense/, /lidmaatskap/, and /kompetisies/ were not implemented as part of Task 1, Task 1A, or Task 1B. No URL migration was performed.

## 11.14 Current source-of-truth map

| Domain | Current source of truth | Status |
|---|---|---|
| Editorial content | WordPress post | Live |
| Semantic topics | content_topic, registered by Platform Core | Canonical, unassigned |
| Legacy categories | content_category | Legacy, live, 13 terms |
| Courses | Masteriyo mto-course | Live |
| Products | WooCommerce product | Live |
| Events | Tickera tc_events | Live |
| People | medewerker | Live, later normalisation remains |
| Broadcasts | uitsending | Live, taxonomy consolidation remains |
| Membership | PMPro | Live |
| Member perks | Voelgoed Member Perks | Live, untouched |
| Partners | vennoot | Live, later review remains |
| Competitions | kompetisie and kompetisie_entry | Registered structure, zero records |
| Platform integration | Voelgoed Platform Core | Active; source-controlled in vgnova/site-code/voelgoed-platform-core/ |

The active WordPress plugin directory is the runtime deployment copy. Any Platform Core code change must first exist in the source-controlled vgnova/site-code/voelgoed-platform-core/ module.

## 11.15 NOT YET IMPLEMENTED

The following work has not started:

- Primary Topic field.
- Secondary Topic mechanism.
- editorial_collection taxonomy.
- editorial_format taxonomy.
- content_category term and assignment migration.
- event_participation model.
- Person-role normalisation.
- Podcast CPT.
- Broadcast taxonomy consolidation.
- Canonical URL realignment.
- Term-level merchandising.
- Leefstyl and commercial refactor.
- Competition final model review.
- SEO and schema finalisation.

Task 2 has not started. No legacy migration or cleanup has been performed.

## 11.16 Task 1B verification

Before removal, Vrouegesondheid was term ID 351 under Gesondheid. A direct relationship query returned zero assigned object IDs. Task 1B then removed that one content_topic term. The updated setup command returned 55 total terms, seven roots, zero newly created terms, and 55 existing terms. It did not flush rewrite rules.

The final content_topic taxonomy has 55 terms, seven roots, and zero relationships. The seven approved object-type attachments remain unchanged. Vrouegesondheid and Leefstyl are absent from content_topic.

The legacy snapshot still has 13 content_category terms, eight relationships, Leefstyl, the same ten object-type attachments, and the same assigned object IDs listed above. No legacy migration or cleanup has started.
