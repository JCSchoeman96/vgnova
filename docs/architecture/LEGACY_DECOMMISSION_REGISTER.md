# Legacy decommission register

**Status:** Active governance register
**Inspection date:** 2026-10-17
**Repository baseline:** origin/main after PR #11 merge at d77c4515ec42ba6af294e7372906084630e1e52a
**Runtime:** WordPress at `localhost:10039`, inspected and updated through the Novamira CLI profile `vgnova`; the attached MCP endpoint targeted `localhost:10059` and was not used

This register is the authoritative ledger for architecture that must stay, adapt, migrate, or leave. It tracks live legacy systems and future replacements so a replacement can be completed before its consumers or data are removed.

This register tracks decommission lifecycle and does not replace the canonical architecture documents as the source of target architecture. Locked canonical architecture documents govern approved target architecture. If a register entry conflicts with a canonical architecture decision, the canonical document takes precedence and the register must be corrected.

The register describes source and runtime evidence. It does not authorize WordPress writes. The Task 1 through Task 3C current-state files remain historical snapshots and are not rewritten when the live architecture changes.

## State labels

The current-state label describes what exists. The lifecycle status describes what should happen next.

| Current-state label | Meaning |
|---|---|
| `CURRENT CANONICAL` | Current architecture is intentional and should remain. |
| `CURRENT LEGACY` | A previous model remains live or registered and still has data or consumers. |
| `TEMPORARY TRANSITIONAL` | A live structure must remain while its replacement is designed, implemented, or migrated. |
| `FUTURE / NOT YET IMPLEMENTED` | A target or proposal is not present in the current runtime. |
| `HISTORICAL PROVENANCE` | A retired process or record remains to explain earlier changes. |

## Lifecycle statuses

| Status | Meaning |
|---|---|
| `KEEP` | Current architecture is intentional and should remain. |
| `ADAPT` | The underlying concept remains, with structural or configuration changes required. |
| `MIGRATE` | A replacement exists or is planned, and data or consumers must move. |
| `DEFER` | The item is known, but replacement architecture or a removal gate is not ready. |
| `READY_TO_REMOVE` | Replacement and consumers are verified, so removal is safe to start. |
| `REMOVED` | The legacy component has been removed. |
| `VERIFIED` | Removal is complete, and checks confirm no remaining requirement. |
| `RETIRED` | An executable mechanism is disabled and retained for provenance. |

## Cleanup sequence

Complete each stage in order:

1. Discover the legacy item and its data, routes, and consumers.
2. Design the replacement.
3. Implement the replacement.
4. Migrate data.
5. Migrate consumers.
6. Verify the replacement and migrated consumers.
7. Decommission the legacy item.
8. Verify removal.

Do not remove an item before its removal gate passes. If a task finds a new blocker or makes an item obsolete, update this register with the evidence and exact gate.

## Register entries

### 1. `content_category`

| Field | Record |
|---|---|
| Current state | `CURRENT LEGACY`. The ACF taxonomy is active with 13 terms, 8 relationships across 7 objects, and attachments to `post`, `product`, `tc_events`, `mto-course`, `medewerker`, `uitsending`, `kompetisie`, `kompetisie_entry`, `testimonial`, and `team_member`. |
| Target state | Migrate each term and relationship to its correct topic, editorial, commercial, person/relationship, access, campaign, or editorial-control destination. Task 4A approves editorial_format and editorial_collection as post-only target dimensions; editorial_flag and campaign_context remain deferred. Keep the taxonomy until every term and consumer has a verified destination. |
| Lifecycle status | `MIGRATE` |
| Why it exists | It is the existing cross-content classification taxonomy. |
| Why it is legacy or why it is KEEP | It combines seven semantic pillars with Leefstyl commercial terms and other historical classifications. It remains live and has assigned relationships. |
| Replacement | content_topic for semantic subjects; editorial_format and editorial_collection as defined in [Editorial Dimensions Architecture](EDITORIAL_DIMENSIONS_ARCHITECTURE.md); PMPro/existing access systems for access; a later commercial model for partner classifications. editorial_flag and campaign_context remain deferred. The term-by-term mapping is in [Legacy Editorial Classification Mapping](LEGACY_EDITORIAL_CLASSIFICATION_MAPPING.md). |
| Dependencies / consumers | Current object-type attachments; Bricks archive conditions, filters, and queries; FluentSnippets 2-vg-article-dynamic-tags.php, 3-shortcode-voelgoed-vennoot-block.php, 4-taxonomy-posts-hide.php, and 6-taxonomy-posts-happyfiles.php; Featured Content Manager; Vennoot mapping and display selection; HappyFiles synchronization; legacy kompetisie_entry, testimonial, and team_member attachments. |
| Removal gate | Inventory all terms, relationships, and registered attachments. Approve a term-by-term migration map. Migrate data and every identified template, snippet, integration, merchandising, partner, and folder consumer. Confirm no read or write consumer still requires the taxonomy. Preserve the Leefstyl parent/child structure and rollback evidence before detaching or deleting anything; the active HappyFiles synchronization can flatten category parents. |
| Planned phase | Content classification and consumer migration, after each replacement dimension is ready. |
| Evidence / notes | Read-only runtime inspection confirms an active ACF-managed, public, hierarchical taxonomy with rewrite slug content_category: seven topic-root terms plus Leefstyl and its five commercial children, with 8 relationships across 7 objects. The current content_topic has 55 terms, 7 roots, and 7 relationships across 7 objects. Task 4D confirms every current content_category relationship belongs to the Task 2 proof set: 514, 516, 518, 519, 521, and 641 have one each; product 523 has two. Even if all seven proof objects are later removed, the taxonomy retains 13 terms and active consumers. The article helper reads the first content_category term for featured-field selection; the partner shortcode reads term mappings and overrides; active Bricks templates Posts (372) and Product Card (731) render the taxonomy; the category UI snippet keeps it as the post classification UI; the HappyFiles snippet syncs assignments and can flatten parent relationships. See [Task 4D audit](LOCAL_FIXTURE_LIFECYCLE_AUDIT.md), [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md), [topic migration report](CONTENT_TOPIC_LOCAL_MIGRATION_REPORT.md), [local mapping contract](CONTENT_CATEGORY_TO_TOPIC_LOCAL_MAPPING.md), [editorial dimensions architecture](EDITORIAL_DIMENSIONS_ARCHITECTURE.md), and [legacy editorial classification mapping](LEGACY_EDITORIAL_CLASSIFICATION_MAPPING.md). |

### 2. `content_topic`

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. The hierarchical taxonomy has 55 terms, 7 roots, and 7 current relationships. Platform Core owns registration and seed logic in `site-code/voelgoed-platform-core/`. |
| Target state | Keep as the shared semantic topic taxonomy. |
| Lifecycle status | `KEEP` |
| Why it exists | It classifies what content is about across the supported Voelgoed entities. |
| Why it is legacy or why it is KEEP | It is the approved canonical semantic model. It does not contain Leefstyl or commercial categories. |
| Replacement | None. Keep `content_topic` as the semantic source. |
| Dependencies / consumers | Platform Core taxonomy setup and role services; Primary Topic field; content classification; future cross-entity discovery and merchandising. It is attached to `post`, `mto-course`, `product`, `tc_events`, `medewerker`, `uitsending`, and `kompetisie`. |
| Removal gate | Not a removal candidate. Preserve the seven-root topic policy and change the registration only through a separately reviewed architecture task. |
| Planned phase | Ongoing canonical use. |
| Evidence / notes | `content_topic` is registered by source-controlled Platform Core, not ACF. The current tree and implementation are documented in [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md) and [Content Topic implementation](CONTENT_TOPIC_ROLE_ARCHITECTURE.md). |

### 3. Primary Topic role infrastructure

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. Platform Core registers the PHP ACF group `group_vg_content_topic_roles`; its Primary Topic field stores a single `content_topic` term ID. Secondary Topics are derived. |
| Target state | Keep the role service and its validation contract. |
| Lifecycle status | `KEEP` |
| Why it exists | It identifies the main topic for navigation, reporting, and downstream discovery without duplicating direct taxonomy membership. |
| Why it is legacy or why it is KEEP | This is the implemented canonical role layer. `post`, `mto-course`, `product`, `tc_events`, `uitsending`, and `kompetisie` support Primary Topic. `medewerker` does not. |
| Replacement | None. Consumers should use the Platform Core role API. |
| Dependencies / consumers | `ContentTopicRoles`, editor notices, post classifications, and future Bricks or merchandising integrations. |
| Removal gate | Not a removal candidate. Any future change must preserve the direct-topic invariant and verify current classifications. |
| Planned phase | Ongoing canonical use. |
| Evidence / notes | The field group is PHP-registered and source-controlled. See [role architecture](CONTENT_TOPIC_ROLE_ARCHITECTURE.md) and [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md). |

### 4. Task 2 local migration lifecycle and record

| Field | Record |
|---|---|
| Current state | `HISTORICAL PROVENANCE`. Task 3C retired the local proof execution lifecycle. The original option `vgpc_content_topic_migration_local_v1` remains unchanged, with a separate retired lifecycle marker. |
| Target state | Keep the retired status and original migration record as provenance. Do not reactivate or delete the record as ordinary cleanup. |
| Lifecycle status | `RETIRED` |
| Why it exists | Task 2 recorded the seven `content_topic` relationships created by its controlled local proof. |
| Why it is legacy or why it is KEEP | The apply and rollback mechanism is retired because reviewed editorial classifications superseded seed rollback semantics. The record itself explains what Task 2 created. |
| Replacement | The current Primary Topic and classification-state service governs editorial review. It does not replace the historical provenance record. |
| Dependencies / consumers | Platform Core migration command and classification-state logic read the lifecycle and record options. Historical reports reference the checksum and original relationship rows. |
| Removal gate | No removal is planned. Keep both the record and retirement marker while any code or audit may rely on their provenance. |
| Planned phase | Historical reference only. |
| Evidence / notes | The retired marker reports `editorial classification supersedes local migration seeds`. Task 4D confirms the saved migration option retains its original IDs, relationships, and source snapshot when fixture objects are removed. The marker validity check depends on its migration ID, reason, status, and timestamp, not live posts. The default read-only dry-run will then report baseline blockers while keeping lifecycle retired; apply and rollback still stop at the retired guard, and retirement reports already-retired. Keep this record and marker as historical provenance. See [Task 4D audit](LOCAL_FIXTURE_LIFECYCLE_AUDIT.md), [migration lifecycle](CONTENT_TOPIC_MIGRATION_LIFECYCLE.md), and [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md). |

### 5. Leefstyl and its commercial child terms

| Field | Record |
|---|---|
| Current state | CURRENT LEGACY. content_category contains Leefstyl (leefstyl) with Aanlyn Onderwys (aanlyn-onderwys), Doen Goed (doen-goed), Finansies (finansies), Natuurlik Gesond (natuurlik-gesond), and Vakansie (vakansie) beneath it. These terms are absent from content_topic. |
| Target state | Migrate commercial and partner meaning into an appropriate commercial model. Keep a separate semantic topic for each item where its subject is known. |
| Lifecycle status | `DEFER` |
| Why it exists | The legacy Leefstyl branch grouped commercial verticals and partner content. |
| Why it is legacy or why it is KEEP | Leefstyl is not a canonical `content_topic` pillar. The terms still support partner classification, merchandising, and old content relationships. |
| Replacement | A commercial or partner model, to be designed in a later architecture phase. Do not design the full model in this housekeeping task. |
| Dependencies / consumers | `content_category`; `vennoot_kategorie`; `linked_vennoot_kategorie`; Featured Content Manager field `featured_leefstyl`; Vennoot display selection; `hf_cat_post`; article, product, and partner content. |
| Removal gate | Approve a commercial model and mappings. Resolve each content and partner relationship. Rehome merchandising and HappyFiles consumers. Verify all template, snippet, and archive dependencies before retiring any Leefstyl term. |
| Planned phase | Commercial and partner model design, followed by data and consumer migration. |
| Evidence / notes | Runtime confirms 13 content_category terms. The five Leefstyl children have corresponding partner mapping values in vennoot_kategorie; natuurlik-gesond has one current content_category relationship. The root is not a topic pillar. The exact destination remains subject to the later commercial model. |

### 6. Partner and commercial classification mappings

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. `vennoot` is the current partner CPT. `vennoot_kategorie` is active on `vennoot`, `post`, and `page`. The ACF field `linked_vennoot_kategorie` maps `content_category` terms to partner categories. `manual_vennoot` and `hide_vennoot_blok` are active on posts and pages. |
| Target state | Keep the partner entity. Adapt or replace category-to-partner and content-display mappings as the commercial model is designed. |
| Lifecycle status | `ADAPT` |
| Why it exists | It connects partner profiles and commercial categories to relevant editorial displays. |
| Why it is legacy or why it is KEEP | The partner entity remains useful. The term mapping and display logic depend on the legacy category system and need to move with it. |
| Replacement | A documented partner/commercial model with equivalent required relationships and display behavior. Its schema is not approved here. |
| Dependencies / consumers | Seven published vennoot profiles; ACF group Kategorie Vennoot Mapping; ACF group Partner Display Override; direct vennoot_kategorie assignments; Vennoot block selection in 3-shortcode-voelgoed-vennoot-block.php; content_category; featured partner content. |
| Removal gate | Define the replacement and import/export mapping. Rehome partner-category assignments and all manual overrides. Verify content blocks and all matching logic before removing `linked_vennoot_kategorie` or its consumers. |
| Planned phase | Partner and commercial architecture, then consumer migration. |
| Evidence / notes | Runtime vennoot_kategorie terms are Aanlyn Onderwys, Doen Goed, Finansies, Geldgeheime, Natuurlik Gesond, Onderwys, and Vakansie. Current legacy mappings include Aanlyn Onderwys to Aanlyn Onderwys, Doen Goed to Doen Goed, Finansies to Finansies, Natuurlik Gesond to Natuurlik Gesond, and Vakansie to Vakansie. The shortcode reads linked_vennoot_kategorie, manual_vennoot, and hide_vennoot_blok. |

### 7. Featured Content Manager

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. The active options page has eight pillar-specific Post Object fields for products, courses, and events. Current non-empty values are Nuwe Jy, Geloof, and Self. |
| Target state | Adapt to the approved topic-aware priority: item override, exact-topic curated content, automatic exact-topic match, parent-topic fallback, then global fallback. |
| Lifecycle status | `ADAPT` |
| Why it exists | It provides global manual featured selections for each old pillar. |
| Why it is legacy or why it is KEEP | The manager is still useful as a global fallback. Its fields reflect the old pillar list and include featured_leefstyl. The active article helper reads the first content_category term and maps only the seven topic-root slugs to featured fields; it does not map leefstyl. |
| Replacement | Topic-aware featured-content selection and global fallback controls. The future field schema and UI are not designed here. |
| Dependencies / consumers | ACF options page `featured-content-manager`; fields `featured_nuwe_jy`, `featured_geloof`, `featured_self`, `featured_werk_geld`, `featured_ouerskap`, `featured_huwelik`, `featured_mensstories`, and `featured_leefstyl`; Bricks featured-content components and queries. |
| Removal gate | Implement and verify all priority levels. Migrate the three current selections and decide how to handle the five empty selections. Verify Bricks output, global fallback, and editor controls before removing old fields. |
| Planned phase | Merchandising design and implementation, then value and consumer migration. |
| Evidence / notes | Read-only Novamira inspection confirms current values are post IDs 542, 534, and 531 for Nuwe Jy, Geloof, and Self. The other five fields are empty. These ACF fields are Post Object relationships, not editorial flags. The article helper also exposes featured-field dynamic tags. The Task 3C snapshot says topic-based merchandising has not started. |

### 8. Inactive content-category sidebar promo group

| Field | Record |
|---|---|
| Current state | `CURRENT LEGACY`. ACF group `Content Category — Sidebar Promo` is inactive. It defines a featured promo item, label, and CTA text on `content_category`. |
| Target state | Reuse equivalent behavior through topic-aware merchandising if a current template still needs it. Otherwise retire the dormant group after a consumer check. |
| Lifecycle status | `DEFER` |
| Why it exists | It appears to have supported category archive promotion. |
| Why it is legacy or why it is KEEP | The group is inactive and tied to the legacy taxonomy. Current values and template consumers have not been audited. |
| Replacement | Topic-aware merchandising, if needed. No replacement is required if no current consumer remains. |
| Dependencies / consumers | ACF group `group_vg_content_category_promo`; any Bricks template, shortcode, or snippet reading `category_promo_item`, `category_promo_label`, or `category_promo_cta_text`. |
| Removal gate | Read current stored values and search active Bricks templates, code, and content output for all three fields. Confirm behavior of the vg_category_promo_* dynamic-tag aliases before changing the helper. If no requirement remains, record the evidence before deleting the group. |
| Planned phase | Merchandising consumer inventory. |
| Evidence / notes | Read-only Novamira reports the group as inactive, with 3 fields and content_category term location. Values were not read. The active article helper aliases vg_category_promo_* dynamic tags to Featured Content Manager fields and does not read category_promo_item, category_promo_label, or category_promo_cta_text; Bricks consumers still require review. |

### 9. Topic-aware layered merchandising

| Field | Record |
|---|---|
| Current state | `FUTURE / NOT YET IMPLEMENTED`. The Task 3C snapshot confirms term-level merchandising has not started. |
| Target state | Implement item override, exact-topic curated selection, automatic exact-topic selection, parent fallback, and global fallback in that order. |
| Lifecycle status | `DEFER` |
| Why it exists | This is the planned replacement for old pillar-specific controls and category-level merchandising. |
| Why it is legacy or why it is KEEP | It is a future replacement, not a current runtime component. It blocks safe removal of old featured controls. |
| Replacement | This is the replacement model. Its data schema, editor UI, and query implementation remain unapproved. |
| Dependencies / consumers | `content_topic`; Primary Topic roles; current Featured Content Manager values; inactive category promo fields; Bricks templates and topic hubs; products, courses, events, and other eligible entities. |
| Removal gate | Approve and implement the five priority levels. Verify each fallback with realistic content and confirm existing featured values have a destination. Then update the old manager entries and their removal gates. |
| Planned phase | Dedicated merchandising architecture and implementation. |
| Evidence / notes | The priority order is approved in this housekeeping brief. No runtime or template implementation is included here. |

### 10. HappyFiles attachment folders

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. The `happyfiles_category` taxonomy organizes media attachments into folders. |
| Target state | Keep as an administrative media organization system. |
| Lifecycle status | `KEEP` |
| Why it exists | It helps editors find assets in the WordPress media library. |
| Why it is legacy or why it is KEEP | These folders are organizational metadata, not semantic content architecture. A taxonomy redesign does not make them obsolete. |
| Replacement | None required. Do not move or remove folders as a side effect of topic migration. |
| Dependencies / consumers | Media-library editor workflows and asset assignments. |
| Removal gate | No removal planned. Change folders only through a separate media workflow review. |
| Planned phase | Ongoing use. |
| Evidence / notes | Runtime registration attaches `happyfiles_category` to `attachment`. The current term set includes pillar-named folders and general folders such as Website and Test. |

### 11. HappyFiles content-category mirrors and sync

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. The `hf_cat_post` taxonomy contains category-like folders. The current-state snapshot records FluentSnippets file `6-taxonomy-posts-happyfiles.php` syncing `content_category` terms and post assignments with `hf_cat_post`. `hf_cat_uitsending` also has category, series, and type folder trees. |
| Target state | Keep useful administrative folders. Adapt folder mappings only after the semantic and commercial content migration is designed. |
| Lifecycle status | `DEFER` |
| Why it exists | It supports editor organization for posts and Voelgoed Live records. |
| Why it is legacy or why it is KEEP | The post folder sync couples administrative organization to `content_category`. The folders remain useful and are not semantic taxonomy terms. |
| Replacement | A reviewed folder mapping that preserves media and editorial workflows without depending on retired semantic categories. |
| Dependencies / consumers | FluentSnippets `6-taxonomy-posts-happyfiles.php`; `content_category`; `hf_cat_post`; `hf_cat_uitsending`; media folder selections and content editors. |
| Removal gate | Inspect the active snippet and compare folder assignments before changing sync. Preserve files, folder assignments, and the Leefstyl parent/child structure. The current sync helper may set content_category and HappyFiles terms to parent 0, so verify its effects before running it during any later migration. Verify editor workflows and all migrated content before disabling the sync or retiring a folder. |
| Planned phase | Content-category consumer migration and separate media-folder review. |
| Evidence / notes | Task 1 current-state documentation records the post sync. Read-only inspection of 6-taxonomy-posts-happyfiles.php confirms bidirectional term/assignment sync and parent-flattening logic. Runtime folders include Content Categories, Uitsending Series, and Uitsending Types under hf_cat_uitsending. |

### 12. `medewerker` CPT

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. The current ACF CPT has 5 published profiles and uses `/medewerkers/` for archive and single URLs. |
| Target state | Preserve existing profiles and consumers while the dedicated Person architecture task defines the migration and normalization mechanics. Do not create a parallel Person system. Keep `/mense/` as the approved canonical People destination unless a later explicit architecture decision changes it. |
| Lifecycle status | `ADAPT` |
| Why it exists | It provides one public contributor profile model for current staging work. |
| Why it is legacy or why it is KEEP | It is the functioning runtime profile implementation, while the canonical IA defines a broader Person entity and the `/mense/` destination. Its continued operation does not make it permanently exempt from that architecture. |
| Replacement | The approved canonical Person model and `/mense/` area. Exact migration and normalization mechanics remain for the Person architecture task. |
| Dependencies / consumers | `medewerker_rol`; `medewerker_groep`; profile ACF fields; `content_topic` expertise terms; event and product relationship fields; Voelgoed Live hosts and guests; Bricks profile templates and query helpers. |
| Removal gate | Do not remove or rename `medewerker` until the approved migration maps and verifies every profile, relationship, URL, template, and consumer. |
| Planned phase | Dedicated Person architecture, followed by profile and consumer migration. |
| Evidence / notes | Runtime has five published profiles. `medewerker` is an ACF-managed CPT with the `/medewerkers/` archive. |

### 13. `medewerker_rol` and `medewerker_groep`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. `medewerker_rol` has 9 terms and `medewerker_groep` has 5 terms. Current assignments include `Mentor` and `Voelgoed Mentor` on the five published profiles. |
| Target state | Keep stable professional profile classifications where useful. Move event-, episode-, article-, and course-specific roles into the relationship that carries that context. |
| Lifecycle status | `ADAPT` |
| Why it exists | These taxonomies support profile labels and group discovery. |
| Why it is legacy or why it is KEEP | Some terms describe lasting professional identity, while terms such as event speaker or Voelgoed Live guest may describe work in a specific relationship. The distinction has not been implemented. |
| Replacement | A reviewed split between persistent profile classifications and contextual roles. No term migration is approved here. |
| Dependencies / consumers | Profile archive filters, Bricks templates, profile records, event relationships, Voelgoed Live host/guest relationships, and any query helpers using these terms. |
| Removal gate | Decide term by term whether each value describes the person or a specific participation. Migrate contextual assignments to relationship records. Verify public profile filters and archives before removing terms. |
| Planned phase | Person and relationship architecture, followed by role migration. |
| Evidence / notes | The current role list includes Mentor, Spreker, Aanbieder, Kunstenaar, Gas, Paneellid, Fasiliteerder, Skrywer, and Kenner. |

### 14. `team_member` and `team_department`

| Field | Record |
|---|---|
| Current state | `CURRENT LEGACY`. The ACF CPT is registered with the `/span/` archive and an active profile field group. The runtime query returned no published `team_member` records. `team_department` has 6 terms with no current assignments. |
| Target state | Consolidate real people into one approved Person model. Retire empty structures only after records and consumers are checked across all statuses. |
| Lifecycle status | `DEFER` |
| Why it exists | It provides a separate internal company-team profile model. |
| Why it is legacy or why it is KEEP | It duplicates person data structures and remains attached to legacy `content_category`. A zero published count does not prove there are no drafts, templates, or code consumers. |
| Replacement | The future canonical Person model and contextual employment or department relationships, if those details remain required. |
| Dependencies / consumers | ACF group `Team Member — Profile`; `team_department`; archive `/span/`; `content_category` attachment; Bricks templates, query loops, shortcodes, and internal team pages. |
| Removal gate | Check all post statuses, taxonomy assignments, Bricks templates and loops, internal pages, code, and links. Migrate real people and retained department data. Verify no consumer depends on this CPT, taxonomy, or archive before removal. |
| Planned phase | Person architecture and profile consolidation. |
| Evidence / notes | The ACF profile group contains job title, bios, photo, contact fields, display order, and featured flag. The live post query confirmed no published records only. |

### 15. Person model and contextual role storage

| Field | Record |
|---|---|
| Current state | `FUTURE / NOT YET IMPLEMENTED`. The source architecture calls for one Person model. The live runtime uses `medewerker`; there is no registered `person` CPT or contextual participation model. |
| Target state | Use one canonical Person entity. Store roles in the relationship context where the role depends on an event, article, course, product, episode, or programme. |
| Lifecycle status | `DEFER` |
| Why it exists | This target resolves duplicate person representations and distinguishes a person's profile from their role in a specific item. |
| Why it is legacy or why it is KEEP | It is a planned replacement direction, not a live model. Current fields and profiles must remain available while the data model is designed. |
| Replacement | The approved Person entity and contextual relationship structures, to be defined in a later phase. |
| Dependencies / consumers | `medewerker`; `team_member`; author and expert links; event participation; course instructors; product creators or endorsers; podcast hosts and guests; Voelgoed Live hosts and guests. |
| Removal gate | Approve the canonical Person entity and role ownership. Map every person ID, profile URL, role, and relationship. Migrate consumers and verify profile pages before removing duplicate models or global contextual terms. |
| Planned phase | Dedicated Person architecture, then data and consumer migration. |
| Evidence / notes | Current `content_topic` policy uses `medewerker` topics for expertise. The `person` model is documented as future-only in the role architecture. |

### 16. Event field `event_medewerkers`

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. Active ACF field `event_medewerkers` is a multi-value relationship from Tickera `tc_events` to `medewerker`. The live site has 17 published events. |
| Target state | Keep the event-to-person relationship. Adapt it to a participation model if event-specific roles or other participation details must be stored. |
| Lifecycle status | `ADAPT` |
| Why it exists | It links event records to the public contributor profiles involved in the event. |
| Why it is legacy or why it is KEEP | The relationship remains useful. Its current list field does not store a role or other participation context. |
| Replacement | Future event participation records if the dedicated architecture confirms the need. Do not create them in this task. |
| Dependencies / consumers | Tickera event editors and singles; ACF group `Event Medewerkers`; `medewerker` profiles; event templates and query loops; product event links and auto-fill. |
| Removal gate | Design and implement the relationship model. Migrate every event-person pair and any contextual roles. Verify event and profile pages, queries, and product auto-fill before removing the field. |
| Planned phase | Event relationship architecture and migration. |
| Evidence / notes | The active ACF group contains one relationship field targeting `medewerker`; no contextual role field is present. |

### 17. Event participation model

| Field | Record |
|---|---|
| Current state | `FUTURE / NOT YET IMPLEMENTED`. No `event_participation` CPT or taxonomy is registered. |
| Target state | Provide an event-person relationship model that can hold participation context when required. |
| Lifecycle status | `DEFER` |
| Why it exists | It is the planned path for contextual event roles rather than treating each role as a global person identity. |
| Why it is legacy or why it is KEEP | It is not a runtime component. It blocks safe removal of the current relation field only if the future requirements need more than a simple person list. |
| Replacement | A designed event participation model. The exact entity, fields, and query contract are undecided. |
| Dependencies / consumers | Tickera `tc_events`; `event_medewerkers`; `medewerker`; event profiles, schedules, templates, and products sourced from events. |
| Removal gate | Approve event participation requirements and queries. Build and verify the new model before moving event-person pairs or disabling the current field. |
| Planned phase | Event relationship architecture. |
| Evidence / notes | Historical Task 1 inspection found no registered event participation model. Current ACF discovery confirms `event_medewerkers` remains the simple relationship. |

### 18. Product event and person relationships

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Product ACF fields are `produk_bron_event` for a Tickera event and `produk_medewerkers` for one or more profiles. FluentSnippets file `9-acf-product-copy.php` copies event people into an empty product relationship on product save and preserves manual selections. |
| Target state | Keep useful product-event and product-person links. Adapt their source and query behavior if event participation or Person architecture changes. |
| Lifecycle status | `ADAPT` |
| Why it exists | It links event-derived products to the event and its contributors. |
| Why it is legacy or why it is KEEP | The links are in active editor workflows. The auto-fill reads the current simple event-person field, so changing that field can silently break product setup. |
| Replacement | Equivalent product relationships and editor automation using the approved event and Person models. |
| Dependencies / consumers | WooCommerce product editor; ACF group `Medewerker Produkte`; `produk_bron_event`; `produk_medewerkers`; `event_medewerkers`; FluentSnippets `9-acf-product-copy.php`; Bricks product templates. |
| Removal gate | Verify all source-event and person relationships. Migrate the automation and test it against manual selections. Confirm product displays and editor flow before disabling the snippet or removing fields. |
| Planned phase | After Event Participation and Person relationship designs are approved. |
| Evidence / notes | The current ACF schema confirms `produk_bron_event` targets `tc_events` and `produk_medewerkers` targets `medewerker`. |

### 19. Internal entity `uitsending`

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. ACF CPT key `uitsending` has 5 published episodes, archive enabled, and rewrite base `voelgoed-live`. |
| Target state | Keep `uitsending` as the durable internal entity and database identifier unless a future task finds a material technical reason to change it. |
| Lifecycle status | `KEEP` |
| Why it exists | It represents Voelgoed Live episodes as a domain entity. |
| Why it is legacy or why it is KEEP | It is the current registered entity and is intentionally independent of its public brand. Do not rename it to match the current brand. |
| Replacement | None. Keep the internal identifier. |
| Dependencies / consumers | Episode posts; ACF group `Broadcast: Episode Details`; Primary Topic role; broadcast series and type taxonomies; hosts, guests, related content; Bricks templates and queries. |
| Removal gate | No removal planned. Keep the CPT and its data model while public branding can change independently. |
| Planned phase | Ongoing use. |
| Evidence / notes | Novamira reports the ACF CPT label as `Voelgoed Live`, with `has_archive` enabled and rewrite slug `voelgoed-live`. |

### 20. Voelgoed Live public namespace

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. `/voelgoed-live/` is the current archive root and the public single-item base. |
| Target state | Keep Voelgoed Live as the public brand and `/voelgoed-live/` as the canonical public root. |
| Lifecycle status | `KEEP` |
| Why it exists | It is the public department brand and the route for Voelgoed Live content. |
| Why it is legacy or why it is KEEP | This is the approved public namespace. It is not a legacy route and is not on a removal or migration list. |
| Replacement | None. A future brand change may alter public labels or routes through a separate migration plan while keeping `uitsending`. |
| Dependencies / consumers | ACF CPT rewrite and archive; episode permalinks; navigation; Bricks archive and single templates; public links, SEO, feeds, and redirects. |
| Removal gate | No removal planned. A public brand or route change would require a separate decision and verified redirects. |
| Planned phase | Ongoing use. Series routes remain open for a dedicated architecture task. |
| Evidence / notes | Current ACF configuration sets rewrite slug and archive to `voelgoed-live`. The root does not prescribe the future series permalink scheme. |

### 21. Superseded public-route proposal `/uitsendings/`

| Field | Record |
|---|---|
| Current state | `HISTORICAL PROVENANCE`. Current runtime inspection found no `broadcast` CPT, no published page with that slug, and no dedicated route registration. |
| Target state | Keep `/voelgoed-live/` as the canonical public namespace; do not implement `/uitsendings/`. |
| Lifecycle status | `VERIFIED` |
| Why it exists | An older canonical architecture proposal used the internal Afrikaans term as a public archive path. |
| Why it is legacy or why it is KEEP | The governing proposal has been removed and the runtime has no such route. This records the superseded assumption for provenance; it is not a live legacy URL. |
| Replacement | `/voelgoed-live/` is the current canonical public root. |
| Dependencies / consumers | Only historical architecture references and this register. Current episode routes use the `voelgoed-live` rewrite. |
| Removal gate | Complete: current governing docs no longer propose this route, and runtime inspection found no dedicated archive or page. Reopen only if later runtime evidence finds a live route. |
| Planned phase | Verified supersession; preserve historical snapshots. |
| Evidence / notes | ACF lists only `uitsending` as the broadcast entity. The CPT rewrite is `voelgoed-live`; no matching published page exists. The rewrite check returned only the generic page matcher. |

### 22. Broadcast series and type taxonomies

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Two active ACF taxonomy pairs are attached to `uitsending`. `broadcast_series` has 3 terms, including `20-Twintig` with 5 assignments. `uitsending_series` has 3 terms and 0 assignments. `broadcast_type` has 9 terms, including Video with 3 assignments and Audio with 2. `uitsending_type` has 4 terms, including Podcast with 2 and Recording with 3. |
| Target state | Converge on one approved programme/series and type model after a dedicated architecture decision. Keep all current assignments until that model is ready. |
| Lifecycle status | `DEFER` |
| Why it exists | The taxonomies classify episode series and formats or types. |
| Why it is legacy or why it is KEEP | Duplicate taxonomy generations remain active. Current terms and rewrite settings differ. Numeric term names also remain: `341` in `broadcast_series`, and `342` and `343` in `broadcast_type`. Their current assignment count is zero. |
| Replacement | One canonical programme/series model and one approved type/format model. Exact CPT, taxonomy, term cleanup, and routes are not designed here. |
| Dependencies / consumers | `uitsending` relationships; current taxonomy archives; rewrite bases; Bricks templates and query loops; `hf_cat_uitsending` folders; links and SEO; Podcast terms and programme pages. |
| Removal gate | Decide which terms represent current content. Migrate all term assignments. Check taxonomy archive routes, Bricks queries, folder mappings, episode fields, and SEO links. Remove duplicate structures only after every consumer passes verification. |
| Planned phase | Dedicated broadcast architecture, followed by term and consumer migration. |
| Evidence / notes | Current rewrite bases are `voelgoed-live/reeks`, `voelgoed-live/legacy-reeks`, `broadcast-type`, and `voelgoed-live/formaat`. The runtime label and term inventory differ across the duplicate pairs. |

### 23. `20-Twintig` programme records

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. The `broadcast_series` term `20-Twintig` has 5 episode assignments. A separate published page titled `20-Twintig` exists at `/20-twintig/`. `uitsending_series` has an unassigned `Twintig-Twintig` term. |
| Target state | Decide how the programme page and episode series relate, and how they fit beneath the Voelgoed Live public root. |
| Lifecycle status | `DEFER` |
| Why it exists | The programme has a public landing page and a set of episode records. |
| Why it is legacy or why it is KEEP | The runtime currently represents it in both a page and a series taxonomy, with duplicate spelling and no approved relationship contract. |
| Replacement | One approved programme/series relationship and public presentation, defined in the broadcast architecture phase. |
| Dependencies / consumers | Page ID 1096; `broadcast_series`; `uitsending_series`; five assigned `uitsending` episodes; Bricks page and episode templates; public links and SEO. |
| Removal gate | Preserve the current landing page and episode links. Confirm the canonical programme record and path, migrate relationships, and verify all old links before removing a page, term, or route. |
| Planned phase | Dedicated programme and series architecture. |
| Evidence / notes | Page 1096 is published at `/20-twintig/` with no parent. This record does not approve an inner URL scheme under `/voelgoed-live/`. |

### 24. Podcast content represented by `uitsending`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. There is no `podcast` or `podcast_episode` CPT. The current `uitsending_type` taxonomy contains a Podcast term with 2 episode assignments. |
| Target state | Move podcast content to a separate podcast entity and series architecture after design approval. Keep Voelgoed Live as its own public brand and entity model. |
| Lifecycle status | `DEFER` |
| Why it exists | Audio podcast items currently share the Voelgoed Live episode structure. |
| Why it is legacy or why it is KEEP | Podcast and Voelgoed Live represent different public content systems, but current runtime data places some podcast episodes in `uitsending`. |
| Replacement | Future podcast entity and series model. Do not create or migrate it in this task. |
| Dependencies / consumers | `uitsending` records; `uitsending_type`; audio URLs and player behavior; show notes; hosts and guests; topic links; podcast feeds; Bricks templates; `/podsending/` links and SEO. |
| Removal gate | Design podcast identity, series, episode data, feeds, and URLs. Migrate all podcast records and consumers, then verify feed output, media, links, and SEO before removing podcast assignments from `uitsending`. |
| Planned phase | Dedicated podcast architecture and migration. |
| Evidence / notes | Current `uitsending_type` reports Podcast with 2 term assignments. No separate podcast post type appears in the runtime registry. |

### 25. Editorial index `/content/`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Posts use `/content/{slug}/`, but WordPress has no post archive and no published page with slug `content`. |
| Target state | Keep the single-item prefix and decide how the editorial index should be provided. |
| Lifecycle status | `ADAPT` |
| Why it exists | `/content/` is the approved editorial area and single-item base. |
| Why it is legacy or why it is KEEP | The public index is missing even though the path is documented as the editorial area. |
| Replacement | An approved archive or published page that provides the editorial index. |
| Dependencies / consumers | WordPress post rewrite; Bricks article archive and single templates; navigation; article filters; SEO and search. |
| Removal gate | No removal planned. Preserve `/content/{slug}/`; verify the index works before updating navigation or consumers. |
| Planned phase | Editorial archive and navigation implementation. |
| Evidence / notes | The live permalink structure is `/content/%postname%/`. Current-state snapshots report no registered post archive or published page at `/content/`. |

### 26. Course routes `/leer/` and `/kursusse/`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Current Masteriyo singles use `/leer/{slug}/`; the runtime archive uses `/kursusse/`. |
| Target state | Keep current URLs unchanged now. Converge the archive or hub with the canonical Learning area `/leer/` through a dedicated Learning/URL task, including redirects if needed. |
| Lifecycle status | `ADAPT` |
| Why it exists | Masteriyo owns the course entity and learning state. |
| Why it is legacy or why it is KEEP | The canonical IA locks `/leer/` as the Learning area. `/kursusse/` is current runtime state, not an approved permanent archive route. |
| Replacement | The final course archive or hub under the canonical `/leer/` area. The implementation and redirect requirements remain for the Learning/URL task. |
| Dependencies / consumers | Masteriyo `mto-course`; course links; Bricks templates; navigation; SEO and redirects from the legacy LMS site. |
| Removal gate | No runtime URL change is authorized here. Design and implement the final archive or hub, inventory affected links, and verify redirects and course flows before changing the runtime archive. Keep current course singles during that work. |
| Planned phase | Dedicated Learning/URL task. |
| Evidence / notes | The canonical IA names `/leer/` as the Learning area. The live post-type registry reports single rewrite `leer` and archive `kursusse`. |

### 27. Shop routes `/winkel/` and `/produkte/`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. WooCommerce product singles use `/winkel/{slug}/`; the shop page and archive use `/produkte/`. |
| Target state | Resolve the canonical shop landing route and preserve single-product URLs unless a separate migration approves a change. |
| Lifecycle status | `DEFER` |
| Why it exists | WooCommerce serves product singles and the shop catalog. |
| Why it is legacy or why it is KEEP | The canonical architecture names `/winkel/` as the Shop area, while runtime uses `/produkte/` for the catalog. The difference is a route convergence question, not permission to change a slug. |
| Replacement | An approved route plan with redirects and navigation updates if convergence is required. |
| Dependencies / consumers | WooCommerce shop page; product rewrite; Bricks shop and product templates; cart and checkout links; navigation; product cards; SEO. |
| Removal gate | Approve the canonical archive path, inventory inbound links, define redirects, test product and checkout flows, and verify SEO before changing any route. |
| Planned phase | URL architecture review and migration planning. |
| Evidence / notes | Current post-type registry reports product singles under `winkel` and shop archive under `produkte`. |

### 28. Event route `/events/`

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. Tickera `tc_events` uses `/events/` for the archive and event singles. |
| Target state | Keep the current event namespace. |
| Lifecycle status | `KEEP` |
| Why it exists | Tickera owns event registration and ticketing. |
| Why it is legacy or why it is KEEP | It is the current plugin-owned event route and is consistent with the canonical architecture. |
| Replacement | None. |
| Dependencies / consumers | Tickera, event category terms, ticket links, event templates, `event_medewerkers`, product event links, navigation, and SEO. |
| Removal gate | No removal planned. Keep the route stable through relationship migration. |
| Planned phase | Ongoing use. |
| Evidence / notes | The live post-type registry reports `tc_events` archive and rewrite slug `events`. |

### 29. Podcast route `/podsending/`

| Field | Record |
|---|---|
| Current state | `FUTURE / NOT YET IMPLEMENTED`. There is no podcast CPT or published page with this path. |
| Target state | Consider the route as part of a separate podcast entity and feed architecture. |
| Lifecycle status | `DEFER` |
| Why it exists | It is the documented public destination for podcast discovery. |
| Why it is legacy or why it is KEEP | It is a future route proposal, not a current route or legacy archive. |
| Replacement | None currently. A podcast architecture task must define the entity, series, episodes, feeds, and public URLs together. |
| Dependencies / consumers | Podcast records now represented in `uitsending`; podcast feeds and players; navigation; media; Bricks templates; SEO. |
| Removal gate | No current route removal. Before implementation, verify the future model and check for existing inbound links or legacy domain redirects. |
| Planned phase | Podcast architecture and URL planning. |
| Evidence / notes | Runtime has no podcast post type or published page with slug `podsending`. |

### 30. People route `/mense/`

| Field | Record |
|---|---|
| Current state | `FUTURE / NOT YET IMPLEMENTED`. The canonical IA approves the People area at `/mense/` and the canonical Person entity. The current runtime profile implementation is `medewerker`, with `/medewerkers/` archive and single URLs. |
| Target state | Implement the approved Person destination at `/mense/`. Define the exact migration and normalization mechanics from `medewerker` in the dedicated Person architecture task. |
| Lifecycle status | `DEFER` |
| Why it exists | It is the approved public destination for the canonical Person model. |
| Why it is legacy or why it is KEEP | `/mense/` has not been implemented in the runtime. The route and Person direction are approved; migration mechanics are not yet designed. |
| Replacement | The canonical Person entity and `/mense/` destination defined by the locked IA. |
| Dependencies / consumers | Five published profiles; event and product relations; Voelgoed Live hosts and guests; Bricks profile pages; inbound links; SEO. |
| Removal gate | Design the profile and relationship migration from `medewerker`; map and verify profiles, relations, templates, consumers, inbound links, and redirects before changing runtime URLs. The `/mense/` destination does not need re-approval unless a later explicit architecture decision changes it. |
| Planned phase | Person architecture and migration planning; `/mense/` is already approved. |
| Evidence / notes | Runtime ACF CPT and rewrite configuration report `medewerker` and `medewerkers`; no published page with slug `mense` was found. |

### 31. Competition route `/kompetisies/`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. The runtime `kompetisie` archive and single rewrite use `/kompetisie/`. |
| Target state | Decide whether the documented plural route needs convergence. Keep current routes until a migration plan is approved. |
| Lifecycle status | `DEFER` |
| Why it exists | The canonical IA proposes a competition area under `/kompetisies/`. |
| Why it is legacy or why it is KEEP | Runtime uses the singular `/kompetisie/` route. No redirect or URL migration is included in this task. |
| Replacement | An approved competition route decision, if any. |
| Dependencies / consumers | `kompetisie`, `kompetisie_entry`, competition terms, entry pages, Bricks templates, forms, inbound links, and SEO. |
| Removal gate | Review current and legacy URLs, decide on the public namespace, map competition and entry links, define redirects, and test submissions before changing route settings. |
| Planned phase | Competition architecture and URL review. |
| Evidence / notes | The runtime has no published competition records. The `kompetisie` CPT is registered with archive and rewrite slug `kompetisie`. |

### 32. Membership route `/lidmaatskap/`

| Field | Record |
|---|---|
| Current state | `FUTURE / NOT YET IMPLEMENTED`. No published page with this slug is present. PMPro currently uses pages such as `/membership/`, `/membership-levels/`, `/membership-checkout/`, and `/membership-account/`. |
| Target state | Decide whether to add a branded landing page and how it links to PMPro system pages. |
| Lifecycle status | `DEFER` |
| Why it exists | It is the documented umbrella route for membership discovery. |
| Why it is legacy or why it is KEEP | The proposed Afrikaans route is not live. PMPro's current checkout and account paths are functional system routes. |
| Replacement | A reviewed public landing page and navigation plan. PMPro remains the access and membership owner. |
| Dependencies / consumers | PMPro levels, checkout, confirmation, billing, cancellation, account pages; WooCommerce integration; membership CTAs; navigation; inbound links and SEO. |
| Removal gate | Preserve working member flows. Approve the public landing page and route, verify every PMPro link and redirect, and test checkout and account access before changing system URLs. |
| Planned phase | Membership UX and URL planning. |
| Evidence / notes | Current published pages include `membership` and `membership-levels`; the runtime page query found no `lidmaatskap` page. |

### 33. Local proof/test fixture records

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Task 4E permanently removed the approved READY_TO_REMOVE set (513, 514, 516, 518, and 519). Task 4F removed the Bricks preview references from templates 372 and 690 and replaced post 641's persistent QA role with a source-controlled test that passed twice and cleans up automatically. Post 375 remains unresolved. |
| Target state | Remove 521, 523, and 641 after independent Task 4G review; resolve 375's provenance separately. Preserve all media unless separately reviewed. |
| Lifecycle status | `DEFER` for the grouped inventory because its members have different gates. Task 4D recommendations remain preserved in the manifest; the separate Task 4E result record records removal and retained state. |
| Why it exists | Task 2 used seven local objects to prove the content_topic migration. Post 641 was also created to test Self Featured Content routing. Post 513 was a trashed duplicate of 514. Post 375 is an older unresolved Lorem-style placeholder. |
| Why it is legacy or why it is KEEP | These records are not verified editorial content. Historical migration evidence and topic tests remain in source-controlled reports and tests. Posts 521 and product 523 no longer provide Bricks editor previews. Post 641 remains in runtime but is no longer needed as a persistent QA target. |
| Replacement | Source-controlled Platform Core tests and historical reports preserve migration and topic behavior. `site-code/runtime-tests/fcm-self-featured-integration.py` replaces the persistent 641 test role with a repeatable, automatically cleaned Self → Featured Content Manager → Bricks test. Templates 372 and 690 now use Bricks' supported empty-preview state. No replacement is required for the retired migration record. |
| Dependencies / consumers | Historical option `vgpc_content_topic_migration_local_v1` retains the original Task 2 IDs as provenance. Template 372 no longer stores `templatePreviewPostId`; template 690 no longer stores `templatePreviewPostId`. Post 641 still has `content_category=self` and renders the current Featured Content Manager selection, but the automated test creates its own temporary Self article and reads the current `featured_self` option. No other exact object references or ACF post-object relationships were found. |
| Removal gate | Task 4F recommends 521, 523, and 641 as `READY_TO_REMOVE`; see the source behavior, stored template data, front-end checks, and two integration-test runs in the Task 4F report. Post 375 remains `NEEDS_PROVENANCE`, so the grouped fixture entry remains `DEFER`. Leave all media attachments for separate review. Do not remove `content_category` or `content_topic` based on relationship counts. |
| Planned phase | Task 4G may perform controlled removal of 521, 523, and 641 after independent review. Investigate 375's provenance separately. Task 4F did not delete any of the remaining objects. |
| Evidence / notes | Task 4E removed 513, 514, 516, 518, and 519. Task 4F changed only the preview settings on templates 372 and 690, then verified that the other template metadata and element data remained stable. The runtime still has 4 `content_category` relationships across 3 objects and 3 `content_topic` relationships across 3 objects; the 13 and 55 terms and active consumers remain. The retired migration record and marker are unchanged. The five associated attachments remain with `post_parent=0`. See [Task 4F fixture dependency replacement report](FIXTURE_DEPENDENCY_REPLACEMENT_REPORT.md), [Post Task 4F Current State](POST_TASK_4F_CURRENT_STATE.md), [Task 4F result record](FIXTURE_DEPENDENCY_REPLACEMENT_RESULT.csv), [Task 4E fixture removal report](TASK_4E_FIXTURE_REMOVAL_REPORT.md), [Task 4E result record](LOCAL_FIXTURE_REMOVAL_RESULT.csv), [Local Fixture Lifecycle Audit](LOCAL_FIXTURE_LIFECYCLE_AUDIT.md), and [Post Task 4D Current State](POST_TASK_4D_CURRENT_STATE.md). |

## Current lifecycle totals

Counts below include each numbered entry once.

| Lifecycle status | Count |
|---|---:|
| `KEEP` | 6 |
| `ADAPT` | 8 |
| `MIGRATE` | 1 |
| `DEFER` | 16 |
| `READY_TO_REMOVE` | 0 |
| `REMOVED` | 0 |
| `VERIFIED` | 1 |
| `RETIRED` | 1 |
