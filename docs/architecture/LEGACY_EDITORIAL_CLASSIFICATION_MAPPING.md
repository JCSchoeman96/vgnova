# Legacy editorial classification mapping

**Status:** Evidence-based design mapping; no data migration
**Inspection date:** 2026-10-15
**Repository baseline:** origin/main after PR #7 merge, 0154c291bbdcd58397a82028a365f37293d2a24b
**Runtime:** WordPress at localhost:10039, inspected read-only through Novamira

This mapping records the legacy classification meanings found in the canonical documents and current runtime. It does not assign new dimensions to production content. The live content_category taxonomy, all term assignments, and all consumers remain unchanged.

## Reading this matrix

Each row is one distinct term, field, structure, or documented concept. A row may list a tightly related set of values from one runtime taxonomy or one field group; those values are listed in full. Totals count matrix rows, not terms within a grouped row, posts, or relationships. A documented example is not evidence that the term exists in the live site.

Confidence describes confidence in the meaning-to-bucket classification, not confidence in any item-level assignment. “Review required” refers to evidence needed before a later migration. An action describes a future mapping decision; it does not authorize action in Task 4A.

Migration actions:

- **DIRECT** — the source meaning has a direct destination.
- **SPLIT** — separate meanings encoded in one legacy label.
- **RECLASSIFY** — replace an old label after reviewing the actual item.
- **RETIRE** — no destination value is needed; confirm consumers before removing a structure.
- **DEFER** — destination architecture or migration owner belongs to a later phase.
- **NEEDS_REVIEW** — available evidence does not support a safe destination.

## Normalized classification matrix

| ID | Legacy term / field / structure | Current meaning and evidence | Target bucket and value | Confidence | Migration action | Later migration review required? | Notes |
|---|---|---|---|---|---|---|---|
| M01 | content_category taxonomy | Active ACF-managed, public, hierarchical taxonomy with 13 terms and 8 relationships across 7 objects; attached to multiple post types. It combines topic roots and commercial terms while historical category meanings remain incompletely inventoried. | NEEDS_REVIEW — mixed legacy system | High | NEEDS_REVIEW | Yes | Keep live until every term, relationship, and consumer has an approved destination. |
| M02 | Nuwe Jy / nuwe-jy | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: nuwe-jy | High | DIRECT | Yes, before item-level relationship migration | Direct term meaning is established; this does not migrate any relationship. |
| M03 | Geloof / geloof | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: geloof | High | DIRECT | Yes, before item-level relationship migration | Direct term meaning is established; this does not migrate any relationship. |
| M04 | Self / self | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: self | High | DIRECT | Yes, before item-level relationship migration | Check item meaning where a historic label encoded more than the topic. |
| M05 | Werk + Geld / werk-geld | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: werk-geld | High | DIRECT | Yes, before item-level relationship migration | Do not conflate with the commercial vertical Finansies. |
| M06 | Ouerskap / ouerskap | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: ouerskap | High | DIRECT | Yes, before item-level relationship migration | Review composite labels such as Ouerskap [Video] per item. |
| M07 | Huwelik / huwelik | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: huwelik | High | DIRECT | Yes, before item-level relationship migration | A named recurring marriage series is a separate collection dimension. |
| M08 | Mensstories / mensstories | One of the seven semantic pillars; local mapping contract maps it to the same content_topic root. | TOPIC — content_topic: mensstories | High | DIRECT | Yes, before item-level relationship migration | Direct term meaning is established; this does not migrate any relationship. |
| M09 | Leefstyl / leefstyl | Current parent term for commercial verticals; canonical docs explicitly exclude it from the topic pillars. | COMMERCIAL — Leefstyl commercial context; exact replacement value not approved | High | DEFER | Yes | Commercial architecture and all partner/content consumers remain for a later phase. |
| M10 | Aanlyn Onderwys / aanlyn-onderwys | Leefstyl child term documented as a commercial vertical; current partner mapping exists. | COMMERCIAL — Aanlyn Onderwys vertical; exact replacement value not approved | High | DEFER | Yes | Keep separate from any item’s semantic topic. |
| M11 | Doen Goed / doen-goed | Leefstyl child term documented as a commercial vertical; current partner mapping exists. | COMMERCIAL — Doen Goed vertical; exact replacement value not approved | High | DEFER | Yes | Keep separate from any item’s semantic topic. |
| M12 | Finansies / finansies | Leefstyl child term documented as a commercial vertical; current partner mapping exists. | COMMERCIAL — Finansies vertical; exact replacement value not approved | High | DEFER | Yes | Do not conflate with the semantic topic Werk + Geld. |
| M13 | Natuurlik Gesond / natuurlik-gesond | Leefstyl child term documented as a commercial vertical; one current content_category relationship is recorded in Task 3C evidence. | COMMERCIAL — Natuurlik Gesond vertical; exact replacement value not approved | High | DEFER | Yes | Do not promote the commercial label into content_topic. |
| M14 | Vakansie / vakansie | Leefstyl child term documented as a commercial vertical; current partner mapping exists. | COMMERCIAL — Vakansie vertical; exact replacement value not approved | High | DEFER | Yes | Commercial target architecture remains deferred. |
| M15 | vennoot_kategorie and its live terms: Aanlyn Onderwys, Doen Goed, Finansies, Geldgeheime, Natuurlik Gesond, Onderwys, Vakansie | Active partner-category taxonomy with seven terms; registration allows attachment to vennoot, post, and page. Term relationship counts by post type were not audited. | COMMERCIAL — partner category context; retain exact target mapping for later design | High | DEFER | Yes | This taxonomy is not editorial_collection or content_topic. |
| M16 | linked_vennoot_kategorie | ACF term field on content_category used to map legacy categories to partner categories. | COMMERCIAL — category-to-partner mapping | High | DEFER | Yes | Keep until the commercial replacement and consumers are verified. |
| M17 | manual_vennoot; hide_vennoot_blok | ACF partner display override fields on posts and pages. Active shortcode logic reads them. | COMMERCIAL — partner display association/control | High | DEFER | Yes | Do not reinterpret as an editorial flag. |
| M18 | Featured Content Manager options and fields: featured_nuwe_jy, featured_geloof, featured_self, featured_werk_geld, featured_ouerskap, featured_huwelik, featured_mensstories, featured_leefstyl | Active options page with single Post Object selectors configured for products, courses, and events. Current field values are featured_nuwe_jy=542, featured_geloof=534, and featured_self=531; the selected records’ post types were not verified here. A helper uses the first content_category term to choose among seven pillar fields; it has no Leefstyl mapping. | ENTITY / RELATIONSHIP CONCERN — curated featured-item relationships | High | DEFER | Yes | This is curation by relationship, not a boolean editorial flag. Topic-aware merchandising is separately governed. |
| M19 | Inactive ACF group Content Category — Sidebar Promo; category_promo_item, category_promo_label, category_promo_cta_text | Inactive term-attached group. Values were not read. Active helper aliases promo dynamic tags to Featured Content Manager; no active snippet read of these three fields was found. | NEEDS_REVIEW — dormant promo structure and unknown stored values | High | NEEDS_REVIEW | Yes | Inspect stored values, Bricks templates, and front-end consumers before retiring or replacing it. |
| M20 | post_tag | Core tag taxonomy has no current terms. Discovery UX says not to migrate generic tags blindly and to retire unneeded generic tags. | RETIRE — no target values identified | Medium | RETIRE | Yes, before disabling/removing any consumer | Empty term inventory does not itself prove that all consumers are absent. |
| M21 | post_format | Core taxonomy is registered with show_ui disabled and has no terms in the current runtime. It is not the approved editorial format model. | NEEDS_REVIEW — no live values to map; do not use as editorial_format | Medium | NEEDS_REVIEW | Yes | No removal of the WordPress core taxonomy is proposed. |
| M22 | broadcast_type; current values Audio, Video, Live Broadcast, Podcast, Recording, Speaker Session, Special, plus empty entries 342 and 343 | Current taxonomy attached only to uitsending. Audio has 2 assignments and Video has 3; entries 342 and 343 have no assignments. | ENTITY / RELATIONSHIP CONCERN — uitsending type | High | DEFER | Yes, in its separate broadcast architecture phase | Audio/Video here describe broadcast records, not standard editorial posts. |
| M23 | uitsending_type; current values Podcast, Recording, Regstreeks, Speaker Session | Current taxonomy attached only to uitsending. | ENTITY / RELATIONSHIP CONCERN — uitsending type | High | DEFER | Yes, in its separate broadcast architecture phase | Preserve the approved uitsending entity lock. |
| M24 | broadcast_series; current values 20-Twintig, Twintig Twintig, 341 | Current taxonomy attached only to uitsending; 20-Twintig has 5 assignments and the other listed terms have none. | ENTITY / RELATIONSHIP CONCERN — uitsending series | High | DEFER | Yes, in its separate broadcast architecture phase | Do not convert to editorial_collection. |
| M25 | uitsending_series; current values #MentorMaandag, #WebinaarWoensdag, Twintig-Twintig | Current taxonomy attached only to uitsending; the listed terms have no assignments. | ENTITY / RELATIONSHIP CONCERN — uitsending series | High | DEFER | Yes, in its separate broadcast architecture phase | Do not start broadcast-series consolidation in Task 4A. |
| M26 | medewerker_rol; current values Mentor, Spreker, Aanbieder, Kunstenaar, Gas, Paneellid, Fasiliteerder, Skrywer, Kenner | Current taxonomy on medewerker profiles; it classifies a person’s role. | ENTITY / RELATIONSHIP CONCERN — Person role | High | DEFER | Yes, in the separately approved Person phase | Do not apply a person role as an article topic or editorial collection. |
| M27 | medewerker_groep; current values Voelgoed Mentor, Voelgoed Kenner, Event Spreker, Bekende, Voelgoed Live Gas | Current taxonomy on medewerker profiles; it groups people by profile/program context. | ENTITY / RELATIONSHIP CONCERN — Person grouping/relationship context | High | DEFER | Yes, in the separately approved Person phase | No Person migration is part of Task 4A. |
| M28 | Documented recurring-series candidates: GeloofsVRAAG, #StilwordSondag, Wat ek geleer het..., Hoopstories, Só oorkom ek..., Gelukkig getroud | Canonical migration documents describe these as collection/franchise examples. Runtime category inventory does not establish them as current terms. | EDITORIAL_COLLECTION — candidate named series | Medium | DEFER | Yes | Confirm recurrence, ownership, item assignments, and any public landing-page need before creating values. |
| M29 | Mentorklas | Documentation-only example described as a possible collection “where applicable”; no live content_category term was found. It may instead describe access or a mentor program. | NEEDS_REVIEW — possible collection, access, or program context | Low | NEEDS_REVIEW | Yes | Do not treat this as a verified runtime classification or create a value until actual use is established. |
| M30 | Composite labels Self [Video] and Ouerskap [Video] | Documentation uses these to show that a legacy label can combine subject and medium. The item’s true subject is not established by the compound label. | EDITORIAL_FORMAT — video; subject separately maps to content_topic | High for format; item subject unknown | SPLIT | Yes | Do not migrate these labels as format-only or topic-only without item evidence. |
| M31 | Mentor access patterns: Self MENTOR, Geloof MENTOR, Nuwe jy MENTOR, Ouerskap MENTOR, Huwelik MENTOR, Geldsake MENTOR, Werk MENTOR | Migration documents identify these examples as access classifications, with topic, Person relationship, collection where applicable, and access kept separate. No matching current content_category terms were found. | ACCESS — PMPro/existing membership access; topic and Person relation handled separately | High for boundary; exact level unknown | DEFER | Yes | Inspect actual historical assignments and PMPro levels in the later access migration. |
| M32 | Person role concepts: author, mentor, expert, speaker, guest, host, instructor | Canonical migration material treats these as Person roles/relationships rather than semantic topics. | ENTITY / RELATIONSHIP CONCERN — Person/content relationship | High | DEFER | Yes | These are documented concepts, not confirmed legacy content_category terms. |
| M33 | Commercial examples: Promosies, Aanlyn onderwys-vennoot, Natuurlik gesond-vennoot, Vakansie-vennoot, Finansies-vennote, Doen goed-vennoot | Migration documents identify these as sponsorship, partner-content, or commercial labels. | COMMERCIAL — partner or commercial context | High | DEFER | Yes | Exact future commercial values and consumers remain to be designed. |
| M34 | Sponsored, Partner, Leefstyl vertical, Partner identity | Canonical migration guidance separates these meanings from semantic subject and editorial series. | COMMERCIAL — sponsorship, partner relationship, or vertical | High | DEFER | Yes | These labels are documented concepts, not verified live terms. |
| M35 | Nuwe jy-finaliste | Documented example of a time-bound campaign/competition initiative. No campaign taxonomy or campaign-context field is registered. | CAMPAIGN — campaign_context, pending campaign model | High for boundary | DEFER | Yes | Campaign metadata and landing-page decisions belong to a later phase. |
| M36 | Competition participant / Campaign entrant | Participation labels describe a person or entry’s relationship to a competition/campaign. | ENTITY / RELATIONSHIP CONCERN — competition participation/entry relationship | High | DEFER | Yes | Do not store as an editorial topic, collection, or generic campaign taxonomy. |
| M37 | Redakteurskeuse; Moet Lees | Named in canonical migration guidance as editorial markers, not semantic topics. No matching current content_category or post_tag values were found. | EDITORIAL_FLAG — possible independent post booleans | High for boundary; runtime need unproven | DEFER | Yes | No taxonomy or fields are approved in Task 4A. |
| M38 | Featured; Homepage Featured (documented examples) | Migration guidance describes these as editorial marker concepts, while the live Featured Content Manager uses Post Object relationships. | EDITORIAL_FLAG — only if an independent binary marker is confirmed | Medium | DEFER | Yes | Do not conflate these labels with the existing Featured Content Manager fields. |
| M39 | Broad historical-category examples: Self, Gesond, Geldsake, Ouerskap, Huwelik | Migration guidance warns that these broad legacy labels may cover multiple actual subjects and calls for item-by-item classification. This is a documented pattern, not an additional live term inventory. | NEEDS_REVIEW — item-level content_topic split or reclassification | High for need to review | RECLASSIFY | Yes | The seven current content_category root terms still map to the matching topic roots; this row records the separate ambiguity of older broad-category assignments. |

## Totals by target bucket

Counts are matrix rows under the rule above. They do not represent the number of live term values, assigned posts, or migrated records.

| Target bucket | Rows |
|---|---:|
| TOPIC | 7 |
| EDITORIAL_FORMAT | 1 |
| EDITORIAL_COLLECTION | 1 |
| EDITORIAL_FLAG | 2 |
| CAMPAIGN | 1 |
| ACCESS | 1 |
| COMMERCIAL | 11 |
| ENTITY / RELATIONSHIP CONCERN | 9 |
| RETIRE | 1 |
| NEEDS_REVIEW | 5 |
| **Total** | **39** |

## Source and runtime notes

Repository sources include the canonical Information Architecture, Topic Taxonomy Migration framework, Taxonomy Discovery UX, Legacy Decommission Register, local category-to-topic mapping, Content Topic Role Architecture, seed editorial review, and Task 3C current state.

Direct links: [Canonical Information Architecture](../../repo/VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md), [Topic Taxonomy Migration framework](../../repo/VOELGOED_CANONICAL_TOPIC_TAXONOMY_MIGRATION_v0.1.md), [Taxonomy Discovery UX](../../repo/VOELGOED_TAXONOMY_DISCOVERY_UX_v0.1.md), [local category-to-topic mapping](CONTENT_CATEGORY_TO_TOPIC_LOCAL_MAPPING.md), [Content Topic Role Architecture](CONTENT_TOPIC_ROLE_ARCHITECTURE.md), [seed editorial review](CONTENT_TOPIC_SEED_EDITORIAL_REVIEW.md), [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md), and [Legacy Decommission Register](LEGACY_DECOMMISSION_REGISTER.md).

Runtime inspection used read-only Novamira abilities on 2026-10-15. It confirmed the 13 content_category values, the seven content_topic roots, the seven vennoot_kategorie values, ACF taxonomy and field-group registrations, current Featured Content Manager values, and the entity-specific broadcast/person classification structures listed above. The active FluentSnippets directory contains nine PHP snippets; all nine files were read. Relevant consumers include:

- **2-vg-article-dynamic-tags.php** reads the first content_category term to select among seven Featured Content Manager fields.
- **3-shortcode-voelgoed-vennoot-block.php** reads the category-to-partner mapping and partner display overrides.
- **4-taxonomy-posts-hide.php** removes native category from the post editor while retaining content_category.
- **6-taxonomy-posts-happyfiles.php** synchronizes content_category with HappyFiles folders and may flatten category parent relationships during synchronization.

No WordPress data or configuration was written. No taxonomy or field registrations, relationships, templates, or URLs were changed.

## Migration constraints

- Keep content_category live until all terms, relationships, and consumers have verified destinations.
- Do not re-review migration seeds 514, 516, 518, 521, or 641 in this task; their topic review remains separate.
- Do not promote Leefstyl or its child verticals into content_topic.
- Do not convert Podcast or uitsending records into ordinary editorial posts based on their media.
- Do not begin Person migration, event participation implementation, broadcast taxonomy consolidation, 20-Twintig modeling, or podcast implementation.
- No production content is assigned a new editorial dimension by this mapping.
