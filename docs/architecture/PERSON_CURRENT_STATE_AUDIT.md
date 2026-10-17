# Person Current-State Audit

**Task:** Voelgoed Task 5A
**Audit date:** 2026-10-17
**Runtime:** Local staging at `http://localhost:10039/`
**Repository baseline:** `afb91b71d8011f8423e719e85e096f9c59aff057`
**Method:** Read-only Novamira inspection, source search, and HTTP reads

## Scope and result

This audit records the current `medewerker` model and the dependencies that must be accounted for before the canonical `person` model is implemented. It makes no runtime changes. The canonical target remains `person` at `/mense/`; the current staging implementation remains `medewerker` at `/medewerkers/`.

The runtime contains five published profiles and no draft, private, or trashed `medewerker` records. The five profiles have the same short bio duplicated in the excerpt and post content. Every profile has the `Mentor` role and `Voelgoed Mentor` group. There are no profile `content_topic` or `content_category` assignments.

The largest migration constraint is the event-to-product workflow: `event_medewerkers` is a simple list, while the product save snippet copies that list into `produk_medewerkers`. Products therefore must not be migrated independently while the legacy automation remains active. Event participation needs a contextual relationship model before product automation is changed.

## Evidence boundaries

- The source repository is canonical for source-controlled architecture. WordPress is the runtime source for current database-managed registrations and content.
- Runtime registration, ACF definitions and values, Bricks templates, FluentSnippets files, records, and relationships were read through Novamira. HTTP status and page metadata were read without submitting forms or changing state.
- Runtime findings describe this staging snapshot as of the audit date. Recheck live records and hashes immediately before any migration.
- The `medewerker` ACF definition and profile ACF group have `source=db`; no source-controlled `medewerker` registration was found. The runtime snippets described below are FluentSnippets-managed and are not tracked in the repository.
- This document does not include secrets or unrelated runtime configuration.

## Current `medewerker` registration

| Property | Runtime value |
|---|---|
| Registration owner | ACF Pro Post Types, database-backed definition `post_type_medewerker` (admin definition ID 741) |
| Source classification | Runtime-only ACF-managed; no source-controlled registration found |
| Public / publicly queryable | `true` / `true` |
| Archive | Enabled; `/medewerkers/` |
| Single rewrite | `medewerkers`, `with_front=false` |
| REST | Enabled, namespace `wp/v2`; default REST base |
| Supports | `title`, `editor`, `thumbnail`, `excerpt`, `revisions`; runtime also reports `autosave` |
| Admin | UI, menu, admin bar, and navigation visibility enabled; menu order 0; `dashicons-groups` |
| Capabilities | `capability_type=post`, `map_meta_cap=true`; standard post capabilities, no custom role/capability scheme |
| Taxonomies attached | `medewerker_rol`, `medewerker_groep`, `content_category`, `content_topic` |
| Person target present? | No `person` CPT is registered |

`content_topic` ownership and seeding belong to Voelgoed Platform Core. Its current attachment to `medewerker` is not an ACF taxonomy registration and must remain distinct from the ACF-managed profile post type.

## Profile corpus

There are five `medewerker` records in total: five `publish`, zero `draft`, zero `private`, and zero `trash`. Each is authored by user ID 1 (`JC_Admin`). All records were created on 2026-06-04 06:05:47; Lynette's record was modified at 06:48:03 and the other four retain the original modification time 06:05:47.

| ID | Name / slug | Status | Photo | Professional title | Short bio | Profile status | Role / group | Topics / categories | Events | Products | Readiness |
|---:|---|---|---|---|---|---|---|---|---:|---:|---|
| 753 | Lynette Beer / `lynette-beer` | publish | Attachment 558, `voelgoed-pexels-OHplpN.jpg` | Spreker | Lewensafrigter en spreker. | public | Mentor / Voelgoed Mentor | none / none | 1 | 2 | Field, taxonomy, relationship, and provenance review |
| 754 | Annelize van der Merwe / `annelize-van-der-merwe` | publish | Attachment 560, `voelgoed-pexels-Gc8Rza.jpg` | empty | Finansiële mentor. | public | Mentor / Voelgoed Mentor | none / none | 0 | 0 | Field, taxonomy, and provenance review |
| 755 | Thandi Mokoena / `thandi-mokoena` | publish | Attachment 562, `voelgoed-pexels-XNDBLA.jpg` | empty | Huweliks- en gesinsterapeut. | public | Mentor / Voelgoed Mentor | none / none | 0 | 0 | Field, taxonomy, and provenance review |
| 756 | Elna Pretorius / `elna-pretorius` | publish | Attachment 564, `voelgoed-pexels-M4OozZ.jpg` | empty | Wellness-afrigter. | public | Mentor / Voelgoed Mentor | none / none | 0 | 0 | Field, taxonomy, and provenance review |
| 757 | Retha Coetzee / `retha-coetzee` | publish | Attachment 566, `voelgoed-pexels-TXEoYM.jpg` | empty | Skrywer en verhaalverteller. | public | Mentor / Voelgoed Mentor | none / none | 1 | 1 | Field, taxonomy, relationship, and provenance review |

The counts include all statuses of related records. Lynette has event 763, published product 767, and trashed product 768. Retha has event 763 and trashed product 768. No event or product relationship is inferred from the profile bio or title.

### Profile field locations

| Fact | Current source | Observed state |
|---|---|---|
| Name | `post_title` | Present for all five |
| Slug | `post_name` | Present for all five; currently unique |
| Photo | Featured image | Present for all five |
| Short bio | ACF `medewerker_kort_bio` | Present for all five |
| Excerpt | `post_excerpt` | Present for all five; exact copy of short bio |
| Full bio | `post_content` | Present for all five; exact copy of short bio, so no distinct long biography is available |
| Professional title | ACF `medewerker_titel` | Present only for Lynette (`Spreker`); this is a contextual role and is not a safe persistent identity value |
| Visibility/status | ACF `medewerker_profiel_status` | `public` on all five |
| Credentials, company/brand, website, social profiles | No current `medewerker` field/value found | Missing |

All profile permalinks are currently HTTP 200 and self-canonical under `/medewerkers/{slug}/`. The archive `/medewerkers/` is HTTP 200. The page responses inspected had no `robots` or `googlebot` meta directive and no `X-Robots-Tag` header. This does not prove what external search engines have indexed.

## Profile ACF schema

Active ACF field group `Medewerker Besonderhede` (`group_medewerker_besonderhede`, ID 744) is database-backed and located on `post_type == medewerker`.

| Field key | Name / label | Type | Required | Return / choices | Default | Conditional | Populated |
|---|---|---|---|---|---|---|---:|
| `field_medewerker_titel` | `medewerker_titel` / Medewerker: Titel | Text | no | N/A | empty | none | 1/5 |
| `field_medewerker_kort_bio` | `medewerker_kort_bio` / Medewerker: Kort bio | Textarea, 3 rows | no | N/A | empty | none | 5/5 |
| `field_medewerker_profiel_status` | `medewerker_profiel_status` / Medewerker: Profielstatus | Select | no | Value/label: `public` / Public, `private` / Private, `noindex` / Noindex, `archived` / Archived | `public` | none | 5/5 (`public`) |

The select permits four different concepts: published visibility (`public`), privacy/access (`private`), search indexing (`noindex`), and lifecycle (`archived`). No current Bricks template or runtime snippet was found applying these values to access or robots output. Do not copy this mixed field as one future Person status without separating its meanings.

## Canonical Person field mapping

This mapping identifies sources only; it does not define new ACF fields.

| Target concept | Source evidence | Classification | Migration note |
|---|---|---|---|
| Name | `post_title` on each profile | `EXISTING_DIRECT` | Preserve exact value; verify before creating a new record |
| Profile photo | Featured image attachment | `EXISTING_DIRECT` | Preserve attachment ID and URL provenance |
| Short bio | `medewerker_kort_bio` | `EXISTING_DIRECT` | Present on all five; currently duplicated in excerpt and content |
| Full bio | `post_content` | `EXISTING_DIRECT` | Stored, but identical to short bio on all five; review before treating as a separate long bio |
| Professional title | `medewerker_titel` | `EXISTING_TRANSFORM` | Only Lynette has `Spreker`; review as contextual rather than persistent |
| Credentials | No field or value found | `MISSING_OPTIONAL` | Do not invent |
| Company / brand | No field or value found | `MISSING_OPTIONAL` | Do not infer from events/products |
| Website | No field or value found | `MISSING_OPTIONAL` | Do not invent |
| Social profiles | No field or value found | `MISSING_OPTIONAL` | Do not invent |
| Expertise / topics | `content_topic` is attached to `medewerker` | `EXISTING_DIRECT` | The relationship model exists but all five currently have zero topic assignments |
| Voelgoed profile metadata | `medewerker_titel`, `medewerker_kort_bio` | `EXISTING_TRANSFORM` | Carry only after field-level review and preservation of source values |
| Profile visibility / status | `medewerker_profiel_status` plus WordPress post status | `EXISTING_TRANSFORM` | Split lifecycle, public access, and indexing policy; all five are currently published/public |

No concept is classified `MISSING_REQUIRED` at this stage because the canonical Person field requiredness has not yet been designed; names and photos are present for all five records. Migration should still be blocked per record until field meaning, profile classifications, and incoming relationships have been reviewed.

## `medewerker_rol` audit

The active ACF-managed, public, REST-visible, non-hierarchical taxonomy has rewrite `medewerker-rol`. It has nine terms; every description is empty.

| ID | Term / slug | Assignments | Assigned people | Conceptual destination | Recommendation |
|---:|---|---:|---|---|---|
| 300 | Aanbieder / `aanbieder` | 0 | — | `NEEDS_REVIEW` | Could describe a durable provider identity or a role in a particular offering; do not migrate yet |
| 304 | Fasiliteerder / `fasiliteerder` | 0 | — | `CONTEXTUAL_ROLE` | Store against the relevant event/programme when context is known |
| 302 | Gas / `gas` | 0 | — | `CONTEXTUAL_ROLE` | Guest is participation-specific |
| 306 | Kenner / `kenner` | 0 | — | `PERSISTENT_PERSON_TYPE` | Durable classification candidate in principle, but no assigned profile supports seeding it now |
| 301 | Kunstenaar / `kunstenaar` | 0 | — | `PERSISTENT_PERSON_TYPE` | Durable identity candidate in principle, but no assigned profile supports seeding it now |
| 298 | Mentor / `mentor` | 5 | Lynette Beer; Annelize van der Merwe; Thandi Mokoena; Elna Pretorius; Retha Coetzee | `PERSISTENT_PERSON_TYPE` | Strongest initial `person_type` candidate; review each assignment before migration |
| 303 | Paneellid / `paneellid` | 0 | — | `CONTEXTUAL_ROLE` | Panel membership belongs to a specific event/session |
| 305 | Skrywer / `skrywer` | 0 | — | `EDITORIAL_RELATIONSHIP_ROLE` | Model authorship on the relevant article; a person may write without being a permanent “writer” type |
| 299 | Spreker / `spreker` | 0 | — | `CONTEXTUAL_ROLE` | Speaker is attached to a specific event/session; Lynette's ACF title is not a taxonomy assignment |

The canonical `person_type` vocabulary should begin with **Mentor only**. The evidence is its profile-level assignment to all five records, rather than an event-specific relationship; this suggests a persistent classification, though each assignment needs owner confirmation. Kenner and Kunstenaar remain candidate concepts but have zero current assignments. This is the smallest vocabulary supported by current records.

## `medewerker_groep` audit

The active ACF-managed, public, REST-visible hierarchical taxonomy has rewrite `medewerker-groep`. Its five current terms have no parent and empty descriptions.

| ID | Term / slug | Assignments | Assigned people | Observed meaning | Recommendation |
|---:|---|---:|---|---|---|
| 310 | Bekende / `bekende` | 0 | — | Marketing/recognition grouping is plausible; not confirmed | `NEEDS_REVIEW`; do not seed a person type |
| 309 | Event Spreker / `event-spreker` | 0 | — | Event-context role | `MAP_TO_RELATIONSHIP` when event and participation are known |
| 308 | Voelgoed Kenner / `voelgoed-kenner` | 0 | — | Could mean community/programme membership or a profile classification | `NEEDS_REVIEW`; no current evidence to distinguish |
| 311 | Voelgoed Live Gas / `voelgoed-live-gas` | 0 | — | Broadcast-context guest role | `MAP_TO_RELATIONSHIP` when episode and participation are known |
| 307 | Voelgoed Mentor / `voelgoed-mentor` | 5 | Lynette Beer; Annelize van der Merwe; Thandi Mokoena; Elna Pretorius; Retha Coetzee | Programme/community grouping; duplicates the same five `Mentor` assignments | `MAP_TO_OTHER_MODEL` only if durable programme membership is confirmed; do not map mechanically to `person_type` |

All 14 term rows, term IDs, observed counts, destinations, confidence, and rationale are also recorded in `PERSON_LEGACY_CLASSIFICATION_MAPPING.csv`.

## Expertise and Primary Topic

`content_topic` is attached to `medewerker`; the five profiles have no current `content_topic` terms. `content_category` is also attached and has no profile assignments. No topic/category assignment is promoted, rewritten, or inferred from bios.

Because there are no current profile topic/category edges, there is no existing assignment to copy or review. The relationship-level action is `DO_NOT_COPY` for the current empty set; reclassify any assignments discovered in a later fresh runtime inventory before migration.

`content_topic` represents expertise or relevance for a Person. A Person has no editorial Primary Topic: `person → Primary Topic NOT_APPLICABLE`. The active role policy in `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicRoles.php` already includes `person` as a non-primary entity policy. The taxonomy registration allowlist does not yet attach `content_topic` to `person`.

When Person is introduced, update the object-type allowlist and its exact expectation in `site-code/voelgoed-platform-core/tests/ContentTopicTaxonomyTest.php`. Strengthen `site-code/voelgoed-platform-core/tests/ContentTopicRolesTest.php` to assert that Person does not support Primary Topic and that its content topics are expertise/relevance. The runtime ACF `Content Topic Roles` field group is Platform Core source-controlled; no Person location is currently present. Do not add Person to legacy `content_category` migration lists without a separate decision.

## Event relationships

There are 17 published `tc_events` and no other event statuses. Active ACF group `Event Medewerkers` (`group_event_medewerkers`, ID 748) contains `event_medewerkers`, a multi-value ACF relationship to `medewerker`, returning IDs.

| Event ID | Title | Status | Medewerker IDs | Count |
|---:|---|---|---|---:|
| 763 | Test Event | publish | 753 Lynette Beer; 757 Retha Coetzee | 2 |

Sixteen events have no linked people. Total: one event with people and two event-person edges.

The field is only a person list. It carries no role, session title, appearance time, display order, featured flag, replay destination, or other participation data. Its ACF instructions direct editors to the profile-level `medewerker_rol`; that is a global workaround, not a relationship-specific event role. The historical `Event Spreker` and `Voelgoed Live Gas` group terms have zero assignments. Do not infer event roles from the global `Mentor` role or from Lynette's `Spreker` title.

The permanent direct conversion `event_medewerkers → Person[]` is **not recommended**. Preserve the two observed edges in a deterministic source-to-target mapping and migrate them into the approved `event_participation` model after its fields and role semantics are designed.

## Product relationships and copy automation

Twenty products exist: 9 published, 7 drafts, 1 private, and 3 trashed. ACF group `Medewerker Produkte` (`group_medewerker_produkte`, ID 750) defines:

- `produk_bron_event`: optional single post object to `tc_events`, returning ID.
- `produk_medewerkers`: optional multi-value relationship to `medewerker`, returning IDs.

| Product ID | Title | Status | Source event | Medewerker IDs | Edge count |
|---:|---|---|---:|---|---:|
| 765 | Test Event Recording | trash | 763 | none | 0 |
| 767 | Test Event Recording QA2 | publish | 763 | 753 Lynette Beer | 1 |
| 768 | QA4 | trash | 763 | 753 Lynette Beer; 757 Retha Coetzee | 2 |

Two products have people, for three product-person edges; this includes one trashed product. Three products have a source event. The fields do not preserve whether a person was manually selected or copied. A matching source event is not proof that an edge was copied.

Runtime FluentSnippets file `wp-content/fluent-snippet-storage/9-acf-product-copy.php` is published and active on all pages. It hooks `acf/save_post` at priority 20, restricts to products, and uses `produk_medewerkers`, `produk_bron_event`, and the source event's `event_medewerkers`. If the product already has people it returns; otherwise it copies event relationship IDs into the product field. The file is not tracked in the Git repository; the repository has documentation references, not the executable snippet source.

The second active runtime snippet, `10-vg-bricks-medewerker-queries.php`, is also FluentSnippets-managed and untracked. It registers Bricks query modes for event people, events and products per profile, product people, and product source event. These snippets and their triggers must be migrated together with their consumers. If `produk_medewerkers` starts targeting Person while the copy helper still reads `event_medewerkers`, a product save can repopulate legacy IDs.

Product Person relationships should migrate **after** `event_participation`, with product field targets and copy automation updated atomically to resolve canonical Person IDs. Keep the original event/product pairs and identify manual versus copied values where provenance can be recovered; do not claim provenance that is not stored.

## Other Person-related domains

### Uitsending and podcast-like records

Five `uitsending` episodes are published. ACF group `Broadcast: Episode Details` (`group_vg_broadcast_episode`, ID 1070) has `broadcast_hosts` and `broadcast_guests` relationships to `medewerker`. Both fields are empty on all five episodes. Bricks template 1127 (`Voelgoed Live Single`) contains host and guest loops using these fields and the Medewerker Card component.

The `Praatpod` episode (ID 605) and `Luisterlys` episode (ID 609) are podcast-like records. No usable host/guest person relationship is populated. Later work should map episode-specific host/guest participation if the target episode model is approved; this audit does not create a podcast model or relationship.

### Courses

There are five published `mto-course` records and one draft (ID 438). No ACF relationship field, post meta, or Bricks consumer targeting `medewerker` was found for courses. Masteriyo's WordPress author remains a `wp_users` author, not a Person relationship. Do not infer instructor links from course authorship.

### Editorial authorship and review

No current ACF relationship field was found for article author, additional authors, expert contributors, or reviewed-by people. Core WordPress authorship is `post_author → wp_users`; the five profiles' `post_author` is user ID 1, `JC_Admin`. This does not establish that JC_Admin is the article author or profile owner.

Canonical editorial Person relationships remain missing and need a separate design. No fields are added in this task.

## Bricks consumer inventory

| Template/component | Type | Current dependency | Migration requirement |
|---|---|---|---|
| 773 `Medewerker Archive` | Archive template | Condition and loop target `medewerker`; grid is title ascending, 24 items; uses component `6b463f` | Rebuild condition and query for Person; preserve archive design and links |
| 772 `Medewerker Single` | Single template | Condition `medewerker`; title, `acf_medewerker_titel`, `acf_medewerker_kort_bio`, role/group terms, content; profile events/products loops | Map dynamic fields and replace relationship queries after their target models migrate |
| 774 `Event Single` | Single template | Query uses `post_type=["medewerker"]`, `meta_key="_event_medewerkers"`, and current event ID; also reads `acf_medewerker_titel` and uses the profile card | Migrate only with `event_participation`; retain event-specific context |
| 124 `Single Product` | WooCommerce template | `product_medewerkers` loop and `product_source_event` loop | Migrate with product fields and copy automation |
| 1127 `Voelgoed Live Single` | Single template | `broadcast_hosts`, `broadcast_guests` queries to `medewerker` | Migrate host/guest relationships after broadcast domain review |
| 6b463f `Medewerker Card` | Reusable component | Featured image, title, ACF title/bio, `{post_url}` | Adapt fields and public URL after Person profile migration |
| 4d1dd4 `Medewerker Hero` | Reusable component | Featured image and post title | No template instance found; verify use before retiring |
| 77 `Header` | Global template | “Medewerkers” text points to `#` (placeholder) | Resolve to canonical archive if retained; currently not a working profile link |
| 614 `Voelgoed Live Archive` | Archive template | No Person relationship found | No Person dependency found |

The Bricks dynamic-data registry exposes tags for `medewerker_titel`, `medewerker_kort_bio`, `medewerker_profiel_status`, `event_medewerkers`, `produk_medewerkers`, `medewerker_rol`, `medewerker_groep`, `broadcast_hosts`, and `broadcast_guests`. Relationship query modes are supplied by the runtime snippet noted above. Detailed rows are in `PERSON_CONSUMER_INVENTORY.csv`.

## URLs, SEO, and `/mense/` collision

- Current archive: `/medewerkers/`, HTTP 200.
- Current published singles: all five `/medewerkers/{slug}/`, HTTP 200, each with a self-canonical URL.
- Inspected responses contained no robots meta directive and no `X-Robots-Tag` header; the `noindex` ACF value is not currently reflected in the observed output.
- No working internal static link to `/medewerkers/` was found. Archive cards and relationship loops generate dynamic `{post_url}` links. Event 763 and products 767/768 are the known relationship-driven links; product 768 is trashed.
- Canonical target: `/mense/` and `/mense/{person-slug}/`. Redirect old archive to new archive and each old single to its verified equivalent only after target pages work.
- Slug continuity is not guaranteed by this audit: the five current slugs are unique, but no Person targets exist yet.

Collision result: **AVAILABLE**. `/mense/` currently returns 404; no page with slug `mense`, registered CPT/taxonomy rewrite, stored WordPress rewrite rule, Bricks route, redirect rule, menu target, or internal post/meta URL using `/mense/` was found. No rewrite flush or route mutation was performed.

## `team_member` legacy model

`team_member` is an active ACF database-managed CPT. It is public, queryable, REST-visible, uses archive and rewrite `span`, supports title/editor/thumbnail/excerpt/revisions/page attributes, and uses default post capabilities. Counts are 0 published, 0 draft, 0 private, and 0 trash (zero total). No Bricks consumer was found.

Active field group `Team Member — Profile` (`group_team_member_profile`) has 12 field entries; there are no posts, so all data fields have zero populated records.

| Field key | Name / label | Type | Required | Return format |
|---|---|---|---|---|
| `field_team_tab_profile` | — / Profile | tab | no | N/A |
| `field_team_job_title` | `team_job_title` / Job Title | text | yes | N/A |
| `field_team_short_bio` | `team_short_bio` / Short Bio | textarea | no | N/A |
| `field_team_full_bio` | `team_full_bio` / Full Bio | wysiwyg | no | N/A |
| `field_team_photo` | `team_photo` / Profile Photo | image | no | array |
| `field_team_tab_contact` | — / Contact & Social | tab | no | N/A |
| `field_team_email` | `team_email` / Email | email | no | N/A |
| `field_team_phone` | `team_phone` / Phone | text | no | N/A |
| `field_team_linkedin` | `team_linkedin` / LinkedIn URL | url | no | N/A |
| `field_team_tab_display` | — / Display | tab | no | N/A |
| `field_team_display_order` | `team_display_order` / Display Order | number | no | N/A |
| `field_team_featured` | `team_featured` / Featured Team Member | true/false | no | N/A |

`team_department` has six terms (Bemarking, Gebeure, Klantediens, Leierskap, Redaksie, Tegnologie), all with zero assignments. Taxonomy attachments also include `content_category` and `hf_cat_team_member`.

The schema suggests company-team profiles, but the intended real-world use is unproven and there are no current records or Bricks consumers. Classify this model as **NEEDS_REVIEW**. Preserve it until all statuses, field consumers, links, shortcodes, and runtime code are rechecked; do not merge it into Person or remove it based only on an empty corpus.

## Duplicate-person check

Titles, normalized names, slugs, WordPress user display names, and profile-like CPT records were compared across `medewerker`, `team_member`, events, products, `uitsending`, and WordPress users. The read-only WooCommerce scan checked 14 order billing-name pairs and 589 product metadata rows; none matched a profile name. No other candidate human record was found. Matches were limited to the five featured-image attachment titles and a revision of Lynette's profile; these are media/revision artifacts, not duplicate people. No `medewerker` email field exists, so no email-based comparison was available; customer email values were not read.

No records were merged. Zero-assignment terms in both taxonomies may be deprecation candidates after their Bricks/query consumers and editorial requirements are checked; this audit does not approve any term removal. Repeat duplicate checks before migration, including any later profile source or legitimate email values then available.

## Readiness summary

All five records require field and provenance review because the only long content duplicates the short bio and several canonical facts are absent. All five require taxonomy review because the assigned global Mentor type and Voelgoed Mentor grouping need confirmation. Lynette and Retha also require relationship review because they have event/product edges; the product edges include a trash record and no copy provenance.

The row-level migration manifest records IDs, source facts, relationship counts, readiness, and a SHA-256 snapshot fingerprint for each profile. The audit fingerprint covers title, slug, status, timestamps, author, excerpt, content, featured-image ID, ACF values, taxonomy term IDs, and incoming valid ACF relationships; revisions are excluded. Recompute fingerprints and re-inventory all relationships immediately before migration.

## Mutation statement

WordPress persistent writes: **0**. No posts, fields, taxonomies, relationships, routes, Bricks templates, snippets, or migration provenance were changed.

## Related documents

- [Canonical information architecture](../../repo/VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md)
- [Decommission register](LEGACY_DECOMMISSION_REGISTER.md)
- [Person profile migration manifest](PERSON_PROFILE_MIGRATION_MANIFEST.csv)
- [Legacy classification mapping](PERSON_LEGACY_CLASSIFICATION_MAPPING.csv)
- [Person consumer inventory](PERSON_CONSUMER_INVENTORY.csv)
- [Person migration architecture](PERSON_MIGRATION_ARCHITECTURE.md)
- [Post-Task 5A snapshot](POST_TASK_5A_CURRENT_STATE.md)
