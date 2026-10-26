# VS1 Discovery Authority Gate

| Audit item | Value |
|---|---|
| Status | BLOCKED |
| Version | 1.0.0 |
| Audit date | 2026-10-26 |
| Repository baseline | `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` |
| Runtime | VG Nova Local, `http://localhost:10039/` |

## Gate summary

```text
DISCOVERY_GATE=BLOCKED
TOPIC_TO_ARTICLE_AUTHORITY=content_topic
TOPIC_TO_ARTICLE_RUNTIME=GAP
ARTICLE_TO_PERSON_AUTHORITY=NONE_PROVEN
ARTICLE_TO_PERSON_GATE=GAP
MENTOR_CLASSIFICATION_REUSE=PASS
PERSON_TO_PRODUCT_AUTHORITY=Product Contribution
PERSON_TO_PRODUCT_GATE=GAP
PERSON_TO_EVENT_AUTHORITY=Event Participation
PERSON_TO_EVENT_GATE=GAP
PERSON_TO_COURSE_AUTHORITY=NONE_PROVEN
PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED
COURSE_TO_WOO_MAPPING=GAP
PERSON_PROGRAMME_DEPENDENCY=YES
SOURCE_RUNTIME_CONFLICT=YES
```

The first hard architecture stop is Person → Course. No Course authority can be inferred from Product Contribution, a featured Course, shared topics, or a Woo Product. The full gate is blocked independently by missing Home → Topic and Topic → Article runtime paths, no canonical Article → Person authority, no Person Event/Product rows, and current Platform Core source/runtime drift.

## Edge-by-edge authority table

| Edge | Authority and source evidence | Current runtime evidence | Gate |
|---|---|---|---|
| Home → Topic | `content_topic` is the canonical taxonomy. `ContentTopicTaxonomy::register_taxonomy()` exposes public terms and nav-menu support. No source-controlled Home → Topic relationship or route is defined by that taxonomy registration. | `show_on_front=posts`, `page_on_front=0`; `wp_get_nav_menus()` returned no WordPress menus. Bricks Header template `77` is custom navigation and its current settings show no canonical `content_topic` term/link. | `GAP` |
| Topic → Article | `ContentTopicTaxonomy::OBJECT_TYPES` includes `post`; direct Article membership is the `content_topic` relationship. It is distinct from legacy `content_category`. | Seven root terms exist, but all have zero Article assignments. Published Article `375` has no `content_topic` or `content_category`. No published Bricks template condition references `content_topic` or its `/onderwerp/` archive. No Topic page currently has a qualifying Article from this data. | `GAP` |
| Article → Person | No canonical relationship field or service is present in tracked Article ACF definitions or Platform Core source. The spec names the expected simple role vocabulary—`primary_author`, `additional_authors`, `expert_contributors`, `reviewed_by`—but does not make those runtime fields. | The active `Content — Article` ACF group has only `article_lede` and `article_read_time`; `Editorial Classification` has taxonomy fields only; `Partner Display Override` points to `vennoot`. Article 375's `post_author` is WordPress user ID `1`, not a canonical Person relationship. No Article → Person row is proven. | `GAP` |
| Person → Course | No canonical Person ↔ Course relationship field, service, or approved participation model was found in tracked source. `LearningService` projects access from Masteriyo and does not associate a Course with a Person. Product Contribution is a separate Product × Person relationship. | Masteriyo Course model is `Masteriyo\Models\Course`, exposing `get_author_id()` / `set_author_id()`; published Course `532` has WordPress author user ID `1`, and no matching Person/Instructor/Product relationship keys were found in its post meta. No canonical Person → Course rows exist. | `ARCHITECTURE_DECISION_REQUIRED` |
| Person → Event | ACF Event Participation endpoint fields are `event_participation_event` → `tc_events` and `event_participation_person` → `person`; Platform Core `EventParticipationRepository::events_for_person()` resolves published rows. | Event Participation count is zero. Lynette has no canonical Event edge. | `GAP` |
| Person → Product | ACF Product Contribution fields are `product_contribution_product` → `product` and `product_contribution_person` → `person`; `ProductContributionRepository::products_for_person()` returns published Products. `PersonProductsBricks` adapts that repository only when the exact `vg-person-products-query` / `vg-person-products-section` markers are used. | Product Contribution count is zero. Runtime `PersonProductsBricks.php` matches its source hash, but no published canonical Person Bricks template consumes those markers. Templates `772` and `773` remain legacy `medewerker` templates. | `GAP` |

The source/runtime parity finding is separately classified in `VS1_CURRENT_REALITY_AUDIT_v1.0.0.md`: the current source build is 55 files, active runtime is 54 files, with one missing and five mismatched files. `content_topic`'s current runtime `object_type` list includes `post` and `person`, matching current source for that property, but its file hash differs. No discovery gate is allowed to pass by assuming the complete Platform Core deployment equals current source.

## Canonical Topic authority and live assignments

`site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php` declares `content_topic` and registers it for `post`, `mto-course`, `product`, `tc_events`, legacy `medewerker`, `uitsending`, `kompetisie`, and canonical `person`. Its public rewrite is `/onderwerp/` and it supports nav menus. `ContentTopicRoles::get_entity_policy()` treats Person assignments as expertise or relevance and marks Primary Topic as not applicable. The ACF Primary Topic field group does not apply to Person. Direct Person Topic assignments can exist without a Primary Topic; this policy does not create a Person → Course relationship.

The current Bricks template inventory contains no published template condition for `content_topic` or `/onderwerp/`. The taxonomy's public route is source-proven, but a current Topic Start-Here template/loop is not. This is a runtime consumer gap, not a reason to replace the canonical taxonomy.

Current runtime roots and selected Nuwe Jy child terms:

| Term | ID | Slug | Runtime Article assignments |
|---|---:|---|---:|
| Nuwe Jy | 349 | `nuwe-jy` | 0 |
| Gesondheid | 350 | `gesondheid` | 0 |
| Skoonheid & versorging | 358 | `skoonheid-versorging` | 0 |
| Oefening & fiksheid | 357 | `oefening-fiksheid` | 0 |
| Resepte | 362 | `resepte` | 0 |
| Voeding & gewig | 354 | `voeding-gewig` | 0 |
| Geloof | 363 | `geloof` | 0 |
| Self | 370 | `self` | 0 |
| Werk + Geld | 377 | `werk-geld` | 0 |
| Ouerskap | 388 | `ouerskap` | 0 |
| Huwelik | 396 | `huwelik` | 0 |
| Mensstories | 404 | `mensstories` | 0 |

Runtime confirms `content_topic` is attached to `post` and `person`. Article `375` and Person `1705` have no direct Topic assignments. The older `PERSON_CURRENT_STATE_AUDIT.md` and pre-Task-10A Person notes contain historical statements about taxonomy attachment; the current tracked source and current runtime attachment were checked directly for this gate. Lynette's lack of a Topic assignment is intentional and recorded in PR #58 evidence.

## Article → Person inventory

The VS1 spec's role names are requirements to compare against, not proof of existing ACF fields. Current source/runtime inventory:

| Candidate | Current evidence |
|---|---|
| `primary_author` | No field or canonical Person query found. WordPress `post_author` is a user ID and is not mapped to a Person post. |
| `additional_authors` | No Article field or canonical relationship model found. |
| `expert_contributors` | No Article field or canonical relationship model found. |
| `reviewed_by` | No Article field or canonical relationship model found. |

Current runtime Article field group `group_vg_content_article` (`Content — Article`) contains only `field_article_lede` (`article_lede`, textarea) and `field_article_read_time` (`article_read_time`, number). The other active Article groups are `group_vg_editorial_classification` (`editorial_format`, `editorial_collection`) and `group_partner_display_override` (`manual_vennoot` to `vennoot`, `hide_vennoot_blok`). The `group_vg_content_topic_roles` PHP group does not provide an Article → Person edge. The repository's tracked `site-code/acf-json/` has no Article Person relationship field definition.

This is a relationship gap, not authorization to create a rich participation entity. Any future implementation must use a separately approved simple relationship design that defines field names, cardinality, roles, validation, and consumer/reverse-query behavior.

## Person identity and Mentor classification

The current canonical Person is Lynette Beer (`person` ID `1705`, slug `lynette-beer`, published). `person_type` is the canonical classification authority, not legacy `medewerker_rol`. The current runtime group `group_vg_person_details` (`Persoon Besonderhede`) includes `person_type` as a taxonomy control. Lynette has exactly `Mentor / mentor` (term `410`) and `Kenner / kenner` (term `419`). Reuse of `person_type` for Mentor classification is proven; do not create a parallel Mentor type.

`content_topic` assignments to Person represent expertise or relevance. The Person has no Primary/Secondary topic role. Lynette currently has no direct Topic assignments. No article, Course, Event, or Product relationship is inferred from her identity or type.

Current Bricks runtime inspection found no canonical Person template. Template `772` (Medewerker Single) and `773` (Medewerker Archive) remain legacy. PR #58 verified `/mense/` and Lynette's canonical single route historically; it changed no Bricks template/component. Route evidence does not prove a current Person consumer template.

```text
MENTOR_CLASSIFICATION_REUSE=PASS
```

## Person → Product and Person → Event details

### Product Contribution

The source authority is `site-code/voelgoed-platform-core/includes/Product/ProductContributionRepository.php`:

- `POST_TYPE = product_contribution`;
- `PRODUCT_FIELD = product_contribution_product`;
- `PERSON_FIELD = product_contribution_person`;
- `products_for_person($person_id)` returns canonical reverse Product membership;
- `PersonProductsBricks` uses that repository only for explicit Bricks markers.

This source is current. The runtime file hash for `PersonProductsBricks.php` matches current source. That proves the adapter file is deployed; it does not prove a production Person template consumes it. Runtime Product Contribution count remains zero. The historical `PRODUCT_CONTRIBUTION_IMPLEMENTATION.md` status `COMPLETE` applies to the implementation and explicitly says template consumption was not proven. Therefore Product Contribution source completeness does not pass this Journey edge.

### Event Participation

The source authority is `site-code/voelgoed-platform-core/includes/Event/EventParticipationRepository.php`; ACF fields are `event_participation_event` and `event_participation_person`. `events_for_person($person_id)` returns Events through published Participation rows. Runtime schema is present, but there are zero Event Participation rows. PR #58 expressly excluded the old QA Event edges from migration; they remain legacy and cannot stand in for canonical Event Participation.

```text
PERSON_TO_PRODUCT_GATE=GAP
PERSON_TO_EVENT_GATE=GAP
```

## Person → Course and Course → Woo mapping

The Masteriyo author is a WordPress user, not a canonical Person. Read-only runtime inspection of Course `532` returned class `Masteriyo\Models\Course`, author methods `get_author_id()` / `set_author_id()`, WordPress author ID `1`, and no matching Person/Instructor/Product relationship keys in post meta. The ACF groups attached to `mto-course` expose the `content_topic` role field group only; no canonical Person relationship field is present. The Masteriyo courses listed at runtime do not expose a canonical Person relationship. No source or runtime consumer resolves a Person from an author user ID.

Masteriyo remains authoritative for Course access and its own Course commercial integration. Read-only `masteriyo/course-list` evidence shows each of the five published Courses has `wc_integration.linked_product_id=0` and `product_create=false`. This audit does not connect a Course to a Woo Product, and a Product Contribution cannot create Person → Course authority even if a Woo Product later maps to a Course.

```text
PERSON_TO_COURSE_AUTHORITY=NONE_PROVEN
PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED
COURSE_TO_WOO_MAPPING=GAP
```

Do not add a hidden Bricks query, infer the relation from `Product Contribution`, match Person and Course by `content_topic`, or treat FCM's featured Course as a Person relationship.

## Person-programme boundary

PR #58 made one canonical Person real and completed Task 10A identity migration only. It did not migrate Article, Event, Product, or Course relationships. Task 10B consumer cutover has not started. Template `772` and `773` remain legacy; no Person consumer, legacy profile, redirect, or relationship migration is authorized by VS1 preflight.

```text
PERSON_PROGRAMME_DEPENDENCY=YES
EXACT_DEPENDENCY=Task 10B must separately design and execute canonical Person consumer cutover; any VS1 Person-template change that overlaps templates 772/773 or their consumers must wait for and be coordinated with that programme unit.
```

## Verification and evidence classification

| Conclusion | Authority and exact evidence | Classification |
|---|---|---|
| `content_topic` is the canonical taxonomy and attaches to Article and Person | Source `ContentTopicTaxonomy::OBJECT_TYPES`; `ContentTopicRoles::get_entity_policy()`; selected VGNova runtime `get_taxonomy('content_topic')->object_type` | `BOTH`; current source and current Local runtime. Current runtime relationship counts are 0. |
| Seven root terms and selected Nuwe Jy children have no Article assignments | Runtime term IDs/slugs/counts; direct query of Article 375 taxonomies | `RUNTIME PROVEN`; current Local staging; Article provenance is unknown. |
| No Article → Person ACF relationship is present | Runtime ACF schemas for `group_vg_content_article`, `group_vg_editorial_classification`, `group_partner_display_override`; tracked `site-code/acf-json/` search; WordPress `post_author` runtime value | `BOTH`; current runtime schema and current source inventory. |
| Mentor classification uses `person_type` | Source ACF definitions in `site-code/acf-json/taxonomy_person_type.json`, `group_vg_person_details.json`; runtime terms and Lynette's two assignments | `BOTH`; current and owner-authorized identity data from merged PR #58. |
| Person → Product authority exists but no production Person consumer or rows are proven | `ProductContributionRepository::products_for_person()`, `PersonProductsBricks`; runtime hash and zero rows; Bricks template inventory | `BOTH` for model/adapter presence; current; no representative relationship data. |
| Person → Event authority exists but no rows are present | `EventParticipationRepository::events_for_person()` and ACF endpoint fields; runtime count zero | `BOTH` for schema/model; current; no representative relationship data. |
| Person → Course authority is absent from inspected source/runtime contracts | `LearningService`, `mto-course` ACF group inventory, Masteriyo Course model introspection and course-list output | `BOTH` within inspected contracts; current Local staging; no owner-approved Person → Course relationship. |
| Platform Core source and runtime differ | Fresh source build and file-by-file runtime comparison in Task 1 audit | `BOTH`; current; conflict classified as 1 missing and 5 mismatched files. |

## Required edge results

```text
HOME_TO_TOPIC_GATE=GAP
TOPIC_TO_ARTICLE_AUTHORITY=content_topic
TOPIC_TO_ARTICLE_RUNTIME=GAP
ARTICLE_TO_PERSON_AUTHORITY=NONE_PROVEN
ARTICLE_TO_PERSON_GATE=GAP
PERSON_TO_COURSE_AUTHORITY=NONE_PROVEN
PERSON_TO_COURSE_GATE=ARCHITECTURE_DECISION_REQUIRED
PERSON_TO_EVENT_AUTHORITY=Event Participation
PERSON_TO_EVENT_GATE=GAP
PERSON_TO_PRODUCT_AUTHORITY=Product Contribution
PERSON_TO_PRODUCT_GATE=GAP
COURSE_TO_WOO_MAPPING=GAP
DISCOVERY_GATE=BLOCKED
PERSON_PROGRAMME_DEPENDENCY=YES
```
