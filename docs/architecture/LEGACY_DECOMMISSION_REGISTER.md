# Legacy decommission register

**Status:** Active governance register
**Inspection date:** 2026-10-18
**Repository baseline:** origin/main at c2a5d7560bdd7d96b5a842dfa6c268060db7a3ae
**Runtime:** Local WordPress at localhost:10039, inspected through Novamira and WP-CLI. Tasks 5C and 6B used read-only runtime calls; WordPress writes = 0.

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
| Current state | `CURRENT LEGACY`. The ACF taxonomy is active with 13 terms and 0 runtime relationships across 0 objects. It remains attached to `post`, `product`, `tc_events`, `mto-course`, `medewerker`, `uitsending`, `kompetisie`, `kompetisie_entry`, `testimonial`, and `team_member`. |
| Target state | Migrate each term and relationship to its correct topic, editorial, commercial, person/relationship, access, campaign, or editorial-control destination. Task 4A approves editorial_format and editorial_collection as post-only target dimensions; editorial_flag and campaign_context remain deferred. Keep the taxonomy until every term and consumer has a verified destination. |
| Lifecycle status | `MIGRATE` |
| Why it exists | It is the existing cross-content classification taxonomy. |
| Why it is legacy or why it is KEEP | It combines seven semantic pillars with Leefstyl commercial terms and other historical classifications. It remains registered and has active consumers; zero current relationships do not pass its removal gate. |
| Replacement | content_topic for semantic subjects; editorial_format and editorial_collection as defined in [Editorial Dimensions Architecture](EDITORIAL_DIMENSIONS_ARCHITECTURE.md); PMPro/existing access systems for access; a later commercial model for partner classifications. editorial_flag and campaign_context remain deferred. The term-by-term mapping is in [Legacy Editorial Classification Mapping](LEGACY_EDITORIAL_CLASSIFICATION_MAPPING.md). |
| Dependencies / consumers | Current object-type attachments; Bricks archive conditions, filters, and queries; FluentSnippets 2-vg-article-dynamic-tags.php, 3-shortcode-voelgoed-vennoot-block.php, 4-taxonomy-posts-hide.php, and 6-taxonomy-posts-happyfiles.php; Featured Content Manager; Vennoot mapping and display selection; HappyFiles synchronization; legacy kompetisie_entry, testimonial, and team_member attachments. |
| Removal gate | Inventory all terms, relationships, and registered attachments. Approve a term-by-term migration map. Migrate data and every identified template, snippet, integration, merchandising, partner, and folder consumer. Confirm no read or write consumer still requires the taxonomy. Preserve the Leefstyl parent/child structure and rollback evidence before detaching or deleting anything; the active HappyFiles synchronization can flatten category parents. |
| Planned phase | Content classification and consumer migration, after each replacement dimension is ready. |
| Evidence / notes | Read-only runtime inspection confirms an active ACF-managed, public, hierarchical taxonomy with rewrite slug content_category: seven topic-root terms plus Leefstyl and its five commercial children. At Task 4D it had 8 relationships across 7 objects in the Task 2 proof set. Task 4G removed the approved fixture objects and left 13 terms with 0 relationships and 0 objects. Task 4H confirms that six remaining detached relationships belong to the separate WordPress core category taxonomy, not content_category; see entry 34 and [Core Category Orphan Manifest](CORE_CATEGORY_ORPHAN_MANIFEST.csv). The content_category taxonomy retains active consumers: the article helper reads its first term for featured-field selection; the partner shortcode reads term mappings and overrides; active Bricks templates Posts (372) and Product Card (731) render it; the category UI snippet keeps it as the post classification UI; the HappyFiles snippet syncs assignments and can flatten parent relationships. See [Task 4D audit](LOCAL_FIXTURE_LIFECYCLE_AUDIT.md), [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md), [topic migration report](CONTENT_TOPIC_LOCAL_MIGRATION_REPORT.md), [local mapping contract](CONTENT_CATEGORY_TO_TOPIC_LOCAL_MAPPING.md), [editorial dimensions architecture](EDITORIAL_DIMENSIONS_ARCHITECTURE.md), and [legacy editorial classification mapping](LEGACY_EDITORIAL_CLASSIFICATION_MAPPING.md). |

### 2. `content_topic`

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. The hierarchical taxonomy has 55 terms, 7 roots, and 0 runtime relationships after Task 5B QA. Platform Core owns registration and seed logic in `site-code/voelgoed-platform-core/`; the supported object types include both `medewerker` and `person`. |
| Target state | Keep as the shared semantic topic taxonomy. |
| Lifecycle status | `KEEP` |
| Why it exists | It classifies what content is about across the supported Voelgoed entities. |
| Why it is legacy or why it is KEEP | It is the approved canonical semantic model. It does not contain Leefstyl or commercial categories. |
| Replacement | None. Keep `content_topic` as the semantic source. |
| Dependencies / consumers | Platform Core taxonomy setup and role services; Primary Topic field; content classification; future cross-entity discovery and merchandising. It is attached to `post`, `mto-course`, `product`, `tc_events`, `medewerker`, `uitsending`, `kompetisie`, and `person`. |
| Removal gate | Not a removal candidate. Preserve the seven-root topic policy and change the registration only through a separately reviewed architecture task. |
| Planned phase | Ongoing canonical use. |
| Evidence / notes | `content_topic` is registered by source-controlled Platform Core, not ACF. Task 5B added `person` to its supported object types without changing the 55-term, 7-root tree or existing assignments. Runtime QA left 0 Person topic relationships. The current tree and implementation are documented in [Task 3C current state](POST_TASK_3C_CURRENT_STATE.md), [Content Topic implementation](CONTENT_TOPIC_ROLE_ARCHITECTURE.md), and the [Post-Task 5B snapshot](POST_TASK_5B_CURRENT_STATE.md). |

### 3. Primary Topic role infrastructure

| Field | Record |
|---|---|
| Current state | `CURRENT CANONICAL`. Platform Core registers the PHP ACF group `group_vg_content_topic_roles`; its Primary Topic field stores a single `content_topic` term ID. Secondary Topics are derived. |
| Target state | Keep the role service and its validation contract. |
| Lifecycle status | `KEEP` |
| Why it exists | It identifies the main topic for navigation, reporting, and downstream discovery without duplicating direct taxonomy membership. |
| Why it is legacy or why it is KEEP | This is the implemented canonical role layer. `post`, `mto-course`, `product`, `tc_events`, `uitsending`, and `kompetisie` support Primary Topic. `medewerker` and `person` do not; Person classification is `NOT_APPLICABLE`. |
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
| Current state | `TEMPORARY TRANSITIONAL`. ACF Pro registers `medewerker` from a database-backed definition (`post_type_medewerker`, ID 741); no source-controlled registration was found. It is public, queryable, REST-visible, supports title/editor/thumbnail/excerpt/revisions, and uses the `/medewerkers/` archive/single base. Runtime has 5 published profiles and no draft, private, or trash profiles. Task 5B added the canonical ACF Local JSON `person` model alongside it without copying any profile. |
| Target state | Keep the current profiles and consumers operational while resolving provenance separately and migrating only approved records and relationships later. The five current profiles are inventory records, not an approved migration corpus. Keep `/mense/` as the canonical People destination. |
| Lifecycle status | `ADAPT` |
| Why it exists | It provides one public contributor profile model for current staging work. |
| Why it is legacy or why it is KEEP | It is the functioning transitional profile implementation alongside the canonical Person entity and `/mense/` destination. Its continued operation does not make it permanently exempt from the approved migration architecture. |
| Replacement | The approved canonical Person model and `/mense/` area. The architecture mapping is defined; profile eligibility and migration authority remain unresolved per record. |
| Dependencies / consumers | Three profile ACF fields; `medewerker_rol` and `medewerker_groep`; attached `content_topic` and `content_category` (zero profile assignments); `event_medewerkers` (2 edges); `produk_medewerkers` (3 edges); empty `broadcast_hosts`/`broadcast_guests` fields; Bricks templates 772, 773, 774, 124, and 1127, shared card 6b463f, and FluentSnippets query helper. |
| Removal gate | Do not remove or rename `medewerker` until the approved migration maps and verifies every profile, relationship, URL, template, and consumer. |
| Planned phase | Task 5B Person foundation without legacy profile copies; Task 5C read-only per-profile provenance decisions completed; Task 5D only for independently approved MIGRATE rows if any are authorized later; domain consumer migration only for approved source data. |
| Evidence / notes | Task 5C independently decided 753–757 DEFER (MIGRATE=0; DO_NOT_MIGRATE=0; DEFER=5). No authoritative source-origin artifact was found; QA/rebuild indicators do not prove the profiles themselves are fixtures. The five event/product edges were independently marked EXCLUDE and remain present. Photos are Pexels stock without a subject link and are OMIT separately from profile identity. No Person targets exist; Person count is 0. Task 6B confirms Event Single 774 can cut over independently; Templates 772 and 773, the shared card, and product consumers remain legacy. See PERSON_PROFILE_PROVENANCE_AUDIT.md, PERSON_PROFILE_PROVENANCE_EVIDENCE.csv, PERSON_RELATIONSHIP_PROVENANCE.csv, PERSON_PROFILE_MIGRATION_MANIFEST.csv, POST_TASK_5C_CURRENT_STATE.md, and EVENT_CONSUMER_MIGRATION_AUDIT.md. |

### 13. `medewerker_rol` and `medewerker_groep`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. `medewerker_rol` has 9 terms and `medewerker_groep` has 5 terms. The only role assignment is `Mentor` on five profiles; `Voelgoed Mentor` is assigned to the same five. All other terms have zero assignments and all term descriptions are empty. |
| Target state | Keep stable professional profile classifications where useful. Move event-, episode-, article-, and course-specific roles into the relationship that carries that context. |
| Lifecycle status | `ADAPT` |
| Why it exists | These taxonomies support profile labels and group discovery. |
| Why it is legacy or why it is KEEP | `Mentor` is the only approved initial `person_type` vocabulary concept; this does not approve any current Mentor assignment for migration. `Kenner` and `Kunstenaar` remain unassigned future candidates. `Spreker`, `Gas`, `Paneellid`, and `Fasiliteerder` are contextual; `Skrywer` is editorial relationship role; `Aanbieder` needs review. `Event Spreker` and `Voelgoed Live Gas` are contextual groups; `Voelgoed Mentor` may represent programme membership and needs confirmation. |
| Replacement | A reviewed split between persistent profile classifications and contextual roles. No term migration is approved here. |
| Dependencies / consumers | Profile archive filters, Bricks templates, profile records, event relationships, Voelgoed Live host/guest relationships, and any query helpers using these terms. |
| Removal gate | Review every term and assignment against its domain. Migrate contextual values to verified relationship records, confirm whether `Voelgoed Mentor` is actual programme membership, and verify profile filters/templates before removing either taxonomy. Do not mechanically seed all terms into `person_type`. |
| Planned phase | Person and relationship architecture, followed by role migration. |
| Evidence / notes | Current term IDs, observed people, destinations, confidence, and rationale are in `PERSON_LEGACY_CLASSIFICATION_MAPPING.csv`. Mentor is the only approved initial vocabulary concept. Task 5C leaves all five Mentor assignments DEFER and all five Voelgoed Mentor assignments DEFER. The available Mentor person_type term does not validate them. |

### 14. `team_member` and `team_department`

| Field | Record |
|---|---|
| Current state | `CURRENT LEGACY`. ACF database-backed CPT is active with archive/rewrite `/span/` and an active 12-field profile group. Every status has zero `team_member` records. `team_department` has 6 terms with no assignments; no Bricks consumer was found. |
| Target state | Consolidate real people into one approved Person model. Retire empty structures only after records and consumers are checked across all statuses. |
| Lifecycle status | `DEFER` |
| Why it exists | It provides a separate internal company-team profile model. |
| Why it is legacy or why it is KEEP | It is a separate profile-shaped model attached to legacy `content_category`, but the field schema suggests company-team profiles and does not establish that its intended people overlap with public contributors. An empty corpus does not establish that the structure is unused. |
| Replacement | The future canonical Person model and contextual employment or department relationships, if those details remain required. |
| Dependencies / consumers | ACF group `Team Member — Profile`; `team_department`; archive `/span/`; `content_category` attachment; Bricks templates, query loops, shortcodes, and internal team pages. |
| Removal gate | Check all post statuses, taxonomy assignments, field values, shortcodes, templates, internal pages, code, and links; establish intended ownership first. Migrate real people and retained department data only if evidence supports it. Verify no consumer depends on this CPT, taxonomy, or archive before removal. |
| Planned phase | Person architecture and profile consolidation. |
| Evidence / notes | Task 5A confirms zero records across all statuses and no Bricks consumer. The ACF group has job title, short/full bios, photo override, contact fields, display order, and featured flag. The purpose remains unproven; recommendation is NEEDS_REVIEW, not automatic consolidation. |

### 15. Person model and contextual role storage

| Field | Record |
|---|---|
| Current state | CURRENT CANONICAL / FOUNDATION ACTIVE. The ACF Pro Person model and /mense/ routes are present. Platform Core policy defines Person expertise/relevance through content_topic and Primary Topic as NOT_APPLICABLE. Runtime has 0 persistent Person records, 1 Mentor person_type term, 0 Person term assignments, and 0 Person content_topic relationships. |
| Target state | Use one canonical Person entity. Store roles in the relationship context where the role depends on an event, article, course, product, episode, or programme. |
| Lifecycle status | `DEFER` |
| Why it exists | This target resolves duplicate person representations and distinguishes a person's profile from their role in a specific item. |
| Why it is legacy or why it is KEEP | The foundation is active; the five local medewerker profiles remain DEFER. Person existence does not authorize profile migration. |
| Replacement | The approved Person entity and migration contract defined by the canonical IA and Task 5A; implement the Person foundation in Task 5B and relationship structures in their owning domain phases. |
| Dependencies / consumers | `medewerker`; `team_member`; author and expert links; event participation; course instructors; product creators or endorsers; podcast hosts and guests; Voelgoed Live hosts and guests. |
| Removal gate | Implement the canonical Person entity and topic attachment; map every source ID, field, classification, URL, and relationship. Migrate and verify consumers by domain. Remove legacy structures only after route/SEO verification, zero active consumers, and retained rollback evidence. |
| Planned phase | Task 5C completed read-only; Task 5D only for approved MIGRATE rows if any; then domain relationship phases. |
| Evidence / notes | Current Task 5C corpus: MIGRATE=0, DO_NOT_MIGRATE=0, DEFER=5. No profile is ready to create as a Person. See the migration manifest, provenance audit, relationship ledger, and Post-Task 5C snapshot. |

### 16. Event field `event_medewerkers`

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Active ACF field `event_medewerkers` is a multi-value relationship from Tickera `tc_events` to `medewerker`. There are 17 published events; one has two linked profiles, and 16 have none. The canonical Event Participation foundation is now available, but the field and its current values remain unchanged. |
| Target state | Evaluate each observed pair and migrate only provenance-approved event/person edges to `event_participation`, which can represent event-specific role and participation metadata. |
| Lifecycle status | `ADAPT` |
| Why it exists | It links event records to the public contributor profiles involved in the event. |
| Why it is legacy or why it is KEEP | The simple list stores no role, session, appearance time, display order, featured status, or replay destination. Profile-level role/group terms were a global workaround and cannot reliably represent event-specific roles. |
| Replacement | `event_participation`. Do not permanently map the field to a bare Person list and do not infer contextual roles from `Mentor` or the profile title. |
| Dependencies / consumers | Tickera event editors; ACF group `Event Medewerkers`; `medewerker` profiles; Event Single 774 native participant loop; Medewerker Single 772 reverse-event query; product event links, product templates, and auto-fill. The Bricks helper's `event_medewerkers` mode is separate from the native 774 query. |
| Removal gate | Resolve provenance for each event-person pair and migrate only approved edges with reviewed contextual roles. Cut over and verify Event Single 774 against canonical Event Participation data. Verify the Medewerker reverse-event query, product auto-fill, all other templates, and every write path against approved targets before disabling/removing the field. Keep the field active while those legacy consumers remain. |
| Planned phase | Task 6A foundation complete; Task 6B proves Event Single 774 can be cut over independently in Task 6C. Event data migration remains gated by individual provenance approval. |
| Evidence / notes | ACF group Event Medewerkers has one multi-value relationship to medewerker. Event 763 links profiles 753 and 757. Task 5C marks both edges EXCLUDE because the explicit test/QA title/slug and sparse event fields support that edge decision. Both source relationships remain unchanged. Task 6B found that native Bricks loop `be9e2f` queries `_event_medewerkers` on profile posts even though the relationship is stored on the Event; the loop returns no matches for Event 763 and its section remains visible. The canonical loop can replace it without data migration, but Task 6C must hide the whole section for an empty canonical result and must not add legacy fallback. Template 772 stays `KEEP_LEGACY / BLOCKED_BY_DATA`; product consumers remain legacy. See EVENT_CONSUMER_MIGRATION_AUDIT.md and EVENT_CONSUMER_MIGRATION_MAP.csv. |

### 17. Event participation model

| Field | Record |
|---|---|
| Current state | CURRENT CANONICAL / FOUNDATION ACTIVE. ACF registers the non-public `event_participation` CPT, `event_role` taxonomy, and four-field details group. Platform Core provides pair validation, canonical writes and queries, administrative title behavior, and the controlled role seeder. Runtime has 0 persistent participations, exactly 4 approved role terms, and 0 role relationships. Task 5C classified both current Event 763 edges as EXCLUDE from migration. |
| Target state | Provide the canonical Event-to-Person participation relationship and the contextual metadata approved for event programming and display. |
| Lifecycle status | `KEEP` |
| Why it exists | It is the planned path for contextual event roles rather than treating each role as a global person identity. |
| Why it is legacy or why it is KEEP | This is the approved canonical relationship model. The legacy list remains insufficient to store event roles and participation order; a direct list conversion would lose that distinction. |
| Replacement | None. `event_participation` is the canonical relationship model. |
| Dependencies / consumers | Tickera `tc_events`; `event_medewerkers`; `medewerker`; event profiles, schedules, templates, and products sourced from events. |
| Removal gate | The foundation is implemented. Resolve the five DEFER Person profiles before any edge migration; classify every event edge and migrate only approved edges. Cut over Event Single 774 in Task 6C using canonical-only data; separately resolve the Medewerker reverse-event query and product copy workflow. Verify routes, templates, and every consumer before removing the old field. |
| Planned phase | Task 6A foundation complete; Event Single consumer cutover is ready for Task 6C; reverse-profile and product consumers remain in later phases. Event data migration has not started. |
| Evidence / notes | Event 763 links profiles 753 and 757. Task 5C excludes both edges because the source event is explicitly a test/QA record. This edge decision does not classify either profile as a fixture. Task 6A created no participation records and did not change the current ACF field or consumers. Task 6B confirms the canonical Event query can be introduced independently while Person and Event Participation counts are zero. Event 763 must render zero canonical participants; the source edges remain present and unchanged. See EVENT_CONSUMER_MIGRATION_AUDIT.md and POST_TASK_6B_CURRENT_STATE.md. |

### 18. Product event and person relationships

| Field | Record |
|---|---|
| Current state | `TEMPORARY TRANSITIONAL`. Product fields are `produk_bron_event` and `produk_medewerkers`. There are 20 products (9 publish, 7 draft, 1 private, 3 trash), 3 source-event links, and 3 product-person edges on two products, including a trash product. Active runtime snippet `9-acf-product-copy.php` copies event people into an empty product field. |
| Target state | Keep only verified product-event and product-person data; target canonical Person IDs and source event participants from the approved event relationship model. |
| Lifecycle status | `ADAPT` |
| Why it exists | It links event-derived products to the event and its contributors. |
| Why it is legacy or why it is KEEP | The product helper reads legacy `event_medewerkers` and writes `produk_medewerkers`. Changing product targets without replacing it could repopulate legacy IDs. The runtime snippet is FluentSnippets-managed and its executable file is not source-controlled. Manual versus copied product edges are not recorded. |
| Replacement | Product-to-Person relationships and editor automation that resolves canonical Person IDs from the event participation model, preserving the current no-overwrite behavior for manual selections. |
| Dependencies / consumers | WooCommerce product editor; ACF group `Medewerker Produkte`; `produk_bron_event`; `produk_medewerkers`; `event_medewerkers`; FluentSnippets `9-acf-product-copy.php`; Bricks product templates. |
| Removal gate | Reconcile every source-event and product-person edge across all statuses. Migrate only provenance-approved edges and explicitly resolve exclusions/deferred pairs. Replace the copy helper and query helper atomically with field retargeting; verify source mapping, empty-field copy, manual-selection preservation, Bricks displays, and editor flow before disabling/removing old fields or snippets. |
| Planned phase | Task 6A foundation complete; migrate products and automation together only after event and Person data eligibility is resolved. |
| Evidence / notes | Product 767 links event 763 and profile 753; product 768 links event 763 and profiles 753/757. Task 5C excludes all three product-person edges based on explicit QA titles, the QA source event, missing commerce setup, and no order references; the trash state on 768 is not used alone. Product 765 (trash) links event 763 without a profile edge. Current helper remains published and active; no copy provenance field exists. No record was changed. |

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
| Current state | `CURRENT CANONICAL / FOUNDATION ACTIVE`. ACF Pro registers `person` from shared Local JSON with archive `/mense/` and singles `/mense/{slug}/`. Runtime QA returned HTTP 200 for both routes; no persistent Person record remains. `medewerker` continues at `/medewerkers/`. |
| Target state | Keep `/mense/` as the canonical Person destination. Add discovery and production presentation only through separately scoped work; migrate profiles and consumers only after provenance approval. |
| Lifecycle status | `KEEP` |
| Why it exists | It is the approved public destination for the canonical Person model. |
| Why it is legacy or why it is KEEP | This is the approved canonical Person destination. It now coexists with the transitional `/medewerkers/` route; the active route does not imply profile migration approval or a redirect. |
| Replacement | None. Keep `/mense/` as the canonical People route. |
| Dependencies / consumers | `person`, `person_type`, Person `content_topic` expertise, five migration-unresolved profiles, event and product relations, Voelgoed Live hosts and guests, future Bricks profile pages, inbound links, and SEO. |
| Legacy redirect gate | Do not redirect `/medewerkers/` or its singles until every profile decision and relation is resolved; migrate and verify only approved records and edges; then verify templates, consumers, internal links, canonicals, and indexing behavior. |
| Planned phase | Task 5B foundation is active with zero persistent Person records; Task 5C provenance decisions completed with 0 MIGRATE and 5 DEFER; Task 5D is not required by the current corpus; later consumer and presentation work only for independently approved data. |
| Evidence / notes | The Task 5A collision review found no route collision; Task 5B activated `/mense/` and verified a temporary single at HTTP 200. Cleanup restored persistent Person posts and Person relationships to zero. `/medewerkers/` remains active with no redirects. See [Person Foundation Implementation](PERSON_FOUNDATION_IMPLEMENTATION.md) and the [Post-Task 5B snapshot](POST_TASK_5B_CURRENT_STATE.md). |

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
| Current state | TEMPORARY TRANSITIONAL. Task 4E removed 513, 514, 516, 518, and 519. Task 4F removed template preview references and replaced post 641's persistent QA role with a fixture-independent source-controlled test. Task 4G removed 521, 523, and 641 after their gates passed. Post 375 remains published. |
| Target state | Preserve historical task evidence. Resolve post 375's origin and purpose before deciding its lifecycle. Preserve shared and unresolved media unless separately reviewed. |
| Lifecycle status | DEFER |
| Why it exists | Task 2 used local objects to prove the content_topic migration. Post 641 also tested Self Featured Content routing. Post 513 was a trashed duplicate of 514. Post 375 is an older unresolved Lorem-style placeholder. |
| Why it is legacy or why it is KEEP | The removed proof objects have historical records in source-controlled reports. Post 375 has placeholder characteristics, but its specific source and purpose remain unknown. |
| Replacement | Source-controlled Platform Core tests and historical reports preserve migration and topic behavior. site-code/runtime-tests/fcm-self-featured-integration.py replaces the persistent 641 test role with a repeatable, automatically cleaned Self article to Featured Content Manager to Bricks test. Templates 372 and 690 use Bricks' supported empty-preview state. |
| Dependencies / consumers | The historical option vgpc_content_topic_migration_local_v1 retains the original Task 2 IDs as provenance. Templates 372 and 690 have no templatePreviewPostId. The automated test creates its own temporary Self article. Task 4H found no current inbound relationship, Bricks preview, Featured Content Manager value, menu link, embedded permalink, snippet reference or source hard-code for 375. Attachment 154 is shared with published event 166. |
| Removal gate | Post 375 is classified DEMO_OR_IMPORT_PLACEHOLDER with MEDIUM classification confidence and lifecycle NEEDS_PROVENANCE with HIGH confidence that the gate remains open. Close the specific origin and purpose question and independently review attachment 154 before any removal decision. If later approved, remove 375 through the WordPress deletion API, verify the post is absent, then clean its detached core category row through the WordPress taxonomy API. Keep attachment 154 because event 166 uses it. Do not remove content_category or content_topic based on zero relationship counts. |
| Planned phase | Task 4I removed the six confirmed core category orphan rows in entry 34. Post 375 remains out of mutation scope until its provenance gate is closed. |
| Evidence / notes | Task 4G removed only 521, 523, and 641; content_category retains 13 terms with 0 relationships and content_topic retains 55 terms and 7 roots with 0 relationships. HappyFiles retains 15 terms with 0 relationships. Product totals fell from 21 to 20. Attachments 522, 524, and 153 survive; 153 remains shared with event 167. Both Bricks preview IDs remain absent with unchanged conditions/elements. The post-deletion FCM test passed twice and left no temporary objects. The migration dry run is retired with baseline_matches=false; record and marker hashes remain unchanged. Task 4H finds post 375 unresolved despite placeholder evidence. Task 4I removed only the six separately approved core category relationships; post 375 and attachment 154 remain unchanged. See [Task 4H residue report](LOCAL_RESIDUE_INTEGRITY_AUDIT.md), [post 375 provenance record](POST_375_PROVENANCE_AUDIT.md), [Task 4G report](TASK_4G_FIXTURE_REMOVAL_REPORT.md), [Task 4G result record](TASK_4G_FIXTURE_REMOVAL_RESULT.csv), [Post Task 4G Current State](POST_TASK_4G_CURRENT_STATE.md), and the historical [Task 4F report](FIXTURE_DEPENDENCY_REPLACEMENT_REPORT.md), [Post Task 4F Current State](POST_TASK_4F_CURRENT_STATE.md), [Task 4E report](TASK_4E_FIXTURE_REMOVAL_REPORT.md), and [local fixture audit](LOCAL_FIXTURE_LIFECYCLE_AUDIT.md). |

### 34. Detached WordPress core category relationships

| Field | Record |
|---|---|
| Current state | HISTORICAL PROVENANCE. The six confirmed detached relationships have been removed. Core category remains unregistered to post, and term voelgoed has one live relationship to post 375. |
| Target state | Keep the category taxonomy and term voelgoed. Preserve post 375's live relationship unless a later independent provenance decision changes its lifecycle. |
| Lifecycle status | REMOVED |
| Why it exists | Previously deleted or trashed objects left relationships behind because the runtime unregisters core category from post. |
| Why it is legacy or why it is KEEP | The six removed rows had no corresponding current post objects or live consumers. Their historical provenance is separately documented. |
| Replacement | None. This was an integrity cleanup of detached relationships, not a taxonomy migration or category-term removal. |
| Dependencies / consumers | The historical migration option retains IDs 514, 516, 518 and 519 as provenance. No current consumer uses the removed relationships. Post 375's distinct live relationship remains. |
| Removal gate | Complete. The six approved rows are absent and the global orphan relationship scan returns 0. The category taxonomy, term voelgoed, and post 375's live relationship remain. No further post or term cleanup is authorized by this entry. |
| Planned phase | Task 4I complete. Post 375 remains separately gated by its provenance decision in entry 33. |
| Evidence / notes | Task 4H's full orphan scan returned exactly six rows, all taxonomy category and term voelgoed. ID 1 is a known legacy WordPress Hello world! sample from the June 9 SQL backup, trashed and absent in current runtime. ID 513 is a duplicate of Task 2 fixture 514. IDs 514, 516, 518 and 519 are Task 2 proof fixtures removed in Task 4E. Each row was ORPHAN_CONFIRMED, READY_TO_CLEAN, confidence HIGH. Task 4I rechecked all six absent post rows and exact relationship fields, then removed only those relationships through wp_delete_object_term_relationships(). The global orphan scan now returns 0. One live relationship remains for post 375; the category taxonomy and term voelgoed remain, with stored term count 0. Post 375 remains published and NEEDS_PROVENANCE. See [Core Category Orphan Manifest](CORE_CATEGORY_ORPHAN_MANIFEST.csv), [Task 4I cleanup report](TASK_4I_CORE_CATEGORY_CLEANUP_REPORT.md), [Task 4I result record](TASK_4I_CORE_CATEGORY_CLEANUP_RESULT.csv), and [Post Task 4I Current State](POST_TASK_4I_CURRENT_STATE.md). |

## Current lifecycle totals

Counts below include each numbered entry once.

| Lifecycle status | Count |
|---|---:|
| `KEEP` | 6 |
| `ADAPT` | 8 |
| `MIGRATE` | 1 |
| `DEFER` | 16 |
| `READY_TO_REMOVE` | 0 |
| `REMOVED` | 1 |
| `VERIFIED` | 1 |
| `RETIRED` | 1 |
