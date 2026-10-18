# Canonical Person Migration Architecture

**Task:** Voelgoed Person architecture and migration sequencing (Tasks 5A–5D)
**Status:** Person foundation active; current five-profile corpus remains deferred pending source-origin evidence
**Task 5C runtime writes:** 0

## Locked target

| Concern | Canonical target |
|---|---|
| CPT | `person` |
| Archive | `/mense/` |
| Single | `/mense/{person-slug}/` |
| Identity | One real human maps to one Person record |
| Persistent classification | `person_type` |
| Context-specific role | Stored on the relevant relationship |
| Expertise | `content_topic` relationships |
| Primary Topic | `NOT_APPLICABLE` for Person |

This design preserves the current `medewerker` runtime until its records and every consumer have been migrated and verified. It does not retain `medewerker` as the permanent canonical model.

## Architecture readiness and data readiness

Person foundation Task 5B was completed without copying any current medewerker record. Task 5C completed a read-only provenance review. Profiles 753–757 are each DEFER because no authoritative source-origin artifact establishes that the local records are genuine source content, while circumstantial QA/rebuild evidence is insufficient to prove that the profiles themselves are fixtures. The profile photo decisions and five current event/product edge decisions are recorded separately in PERSON_PROFILE_PROVENANCE_AUDIT.md and its CSV ledgers.

Task 5C does not authorize canonical duplication. A real-world-looking name, published status, plausible profession, or canonical IA example is not migration authority by itself. Task 5D is not required by the current corpus because there are zero MIGRATE rows.

## Field-level mapping contract

The mapping below identifies current storage sources and open review. `EXISTING_DIRECT` and `EXISTING_TRANSFORM` describe the shape of available runtime data; they do not approve that data for migration. No field-group creation or value transformation is authorized in Task 5A.

| Person concept | Current source | Mapping class | Contract |
|---|---|---|---|
| Name | `post_title` | `EXISTING_DIRECT` | Preserve source value and ID mapping; do not match by title alone |
| Profile photo | Featured image | `EXISTING_DIRECT` | Preserve attachment relation and source attachment ID |
| Short bio | `medewerker_kort_bio` | `EXISTING_DIRECT` | Copy value only after review; record that it duplicates excerpt/content |
| Full bio | `post_content` | `EXISTING_DIRECT` | Stored but identical to short bio for all current profiles; do not claim it is an authored long bio |
| Professional title | `medewerker_titel` | `EXISTING_TRANSFORM` | Review per value; `Spreker` is contextual, not automatically a permanent title |
| Credentials | No source | `MISSING_OPTIONAL` | Leave absent |
| Company / brand | No source | `MISSING_OPTIONAL` | Leave absent; do not infer from event or product links |
| Website | No source | `MISSING_OPTIONAL` | Leave absent |
| Social profiles | No source | `MISSING_OPTIONAL` | Leave absent |
| Expertise | Existing `content_topic` taxonomy attachment | `EXISTING_DIRECT` | Copy only verified assignments; current profiles have none |
| Voelgoed profile metadata | Current ACF title, short bio, and profile status | `EXISTING_TRANSFORM` | Preserve originals and separate mixed status meanings |
| Visibility / lifecycle | WordPress post status plus `medewerker_profiel_status` | `EXISTING_TRANSFORM` | Map publication/access, search indexing, and lifecycle independently |

No concept is classified `MISSING_REQUIRED` at this stage because canonical Person field requiredness has not yet been designed. New profile requirements must be decided in the Person implementation task; do not invent values to fill gaps.

## Classification mapping

### Initial `person_type` vocabulary

**Mentor** is the only approved initial `person_type` vocabulary concept. The canonical IA recognizes Mentor as a persistent person classification. This approves the concept only: it does not validate the five current `medewerker_rol=Mentor` assignments. Review each assignment independently during provenance resolution before copying it to any Person record.

`Kenner` and `Kunstenaar` are possible persistent identity concepts but have zero current assignments; do not seed them only because they existed as terms. Do not map `Voelgoed Mentor` into `person_type`: it appears to be a programme/community grouping, though its exact meaning needs confirmation.

### Contextual and editorial roles

`Spreker`, `Gas`, `Paneellid`, and `Fasiliteerder` describe a participation in an event, episode, or programme and belong on that relationship. `Event Spreker` and `Voelgoed Live Gas` also belong on their contextual relationship if real assignments are found. `Skrywer` belongs on article authorship rather than as a permanent Person type. `Aanbieder` needs review because it could be a durable professional identity or a contextual presenter role.

The separate classification CSV records every current term and its final Task 5A recommendation. No term assignments are changed in this task.

The initial `person_type` vocabulary may include `Mentor` in Task 5B. Creating that concept does not copy or approve any legacy term assignment. `Kenner` and `Kunstenaar` are not part of the initial vocabulary.

## Relationship strategy

| Domain | Current model | Target approach | Dependency |
|---|---|---|---|
| Event participation | `tc_events.event_medewerkers → medewerker[]` | `event_participation` between event and Person, with reviewed contextual role and any required session/schedule/display metadata | Design/build this before event consumer migration |
| Product people | `product.produk_medewerkers → medewerker[]` | Product-to-Person relation after eligible Person records exist; preserve manual/copy provenance if available | Migrate after event participation and update product copy automation atomically |
| Voelgoed Live | `broadcast_hosts`, `broadcast_guests` → `medewerker[]` | Episode-specific presenter/guest relationships to Person | Later broadcast-domain migration; current fields are empty |
| Podcast-like episodes | Same current `uitsending` fields; no populated relationships | Later episode `hosts` / `guests` if a podcast episode model is approved | No usable current relationship data found |
| Courses | No Person relation found | Decide instructor/expert relation separately; Masteriyo remains course owner | No current course Person edges to migrate |
| Editorial | `post_author → wp_users`; no profile relationship fields | Design author, additional author, expert, and reviewer links separately | Do not reinterpret WordPress users as Person records |

Do not implement the permanent direct conversion from event_medewerkers to a bare Person list. The source list lacks contextual information and the canonical architecture requires event_participation. Task 5C classifies event 763's edges to profiles 753 and 757 as EXCLUDE from migration because the event is explicitly named Test Event and has a QA slug with no contextual participation detail. The source edges remain present and unchanged. The exclusion does not classify either Person profile as a fixture.

Task 5C classifies the three observed product-person edges as EXCLUDE from migration: product 767 to profile 753, and product 768 to profiles 753 and 757. Their QA titles, source event 763, missing commerce setup, and lack of order references support excluding these edges; product 768's trash status is not used alone. The source fields remain unchanged. Product-to-Person migration remains downstream of event participation and atomic replacement of the current copy automation.

The consumer inventory separates architecture dependencies from current-data migration decisions. The event and product schemas remain real architecture dependencies. Task 5C's five currently known event/product edges are EXCLUDE from migration and remain unchanged; a future edge discovery requires its own provenance review.

## Migration phases

1. **Task 5B — Person foundation.** Completed without copying any current medewerker profile. Canonical person, /mense/, profile fields, and the initial person_type=Mentor vocabulary are present. Person Primary Topic remains unsupported and classification is NOT_APPLICABLE.
2. **Task 5C — Read-only provenance and migration eligibility.** Completed. Each current profile has exactly one decision and confidence. Profiles 753–757 are all DEFER; their field decisions are recorded separately. The two event edges and three product edges are each EXCLUDE. No Person record or relationship was created.
3. **Task 5D — Deterministic migration of independently approved MIGRATE rows.** Not started and not required by the current corpus, which has zero MIGRATE decisions. If a separately approved decision later creates a MIGRATE row, Task 5D must re-fingerprint immediately before writes; stop if source facts drift; create only approved Person records; copy only fields marked COPY; record deterministic legacy-ID-to-Person-ID mappings; and retain medewerker during the dual-run. Keep target IDs/slugs blank until each approved row is migrated.
4. **Task 6A+ — Event participation model.** Define the event_participation junction and contextual fields. The two current event 763 edges are EXCLUDE and remain unchanged. Future edges may migrate only after their event, Person, and edge each have independent approval.
5. **Then — Product relationships and automation.** Product-person migration remains downstream of event participation and atomic replacement of the current copy snippet/query helper. Product saves must resolve approved canonical Person IDs through approved event participation. Verify empty-field copy and manual-selection preservation paths.
6. **Later — Uitsending, podcast, course, and editorial relationships.** Recheck current records and define each domain's contextual relationships separately from WordPress account authorship.
7. **Final Person decommission task.** Inventory all statuses and consumers; freeze legacy writes; account for every profile as migrated or explicitly excluded; resolve every remaining DEFER; verify relations, templates, routes, search behavior, and inbound links; redirect old archive/singles only to verified targets; remove medewerker only after the zero-consumer gate passes.

### Why products follow event participation

The active product helper listens to `acf/save_post`, reads `event_medewerkers`, and fills an empty `produk_medewerkers` relationship. Migrating products first would either leave the field targeting legacy IDs or allow the helper to write legacy IDs back into a new target field. A one-step product migration would be viable only if the bridge and copy behavior were replaced atomically with deterministic ID mapping. The safer default is to migrate the event relationship model first, then migrate product fields and automation together.

## Dual-run and deterministic correspondence

Keep `medewerker` available and unmodified while the Person foundation is implemented and while any approved records are migrated and validated. Maintain a source-controlled migration manifest with at least:

`legacy_medewerker_id`, `legacy_slug`, `legacy_title`, `target_person_id`, `target_slug`, `migration_state`.

The current profile inventory records migration_decision, decision_confidence, provenance_summary, both Task 5A and Task 5C source fingerprints, fingerprint comparison, target_person_id, target_slug, and migration_state. All five profile decisions are DEFER; target IDs/slugs remain blank and migration state remains NOT_STARTED. Task 5A and Task 5C digests differ, but no source-fact change was found; Task 5A's byte-level normalization recipe was not retained, so the old fingerprint is preserved and not silently replaced. The manifest is an inventory, not a list of approved migration rows.

Use the legacy numeric ID as the primary lookup key. Slug can be a secondary verification attribute after uniqueness checks. Never match only by title. Record one row per legacy record, including `DO_NOT_MIGRATE` and `DEFER` outcomes, so retries remain deterministic. Leave `target_person_id` and `target_slug` empty until that row is approved and migrated.

During dual-run, keep source profile values and relationships intact. New Person consumers should use only verified Person IDs; old consumers continue reading `medewerker` until each domain is migrated. A consumer dependency does not approve the current data edges that consumer reads. Avoid bidirectional synchronization unless a separate design defines conflict handling and ownership.

## URL and SEO contract

The approved target is `/mense/` and `/mense/{person-slug}/`. The current archive and singles return 200 and self-canonicalize. `/mense/` is currently available and returns 404; no collision was found.

Do not assume slug preservation: current slugs are unique, but no Person target exists. For every published profile, verify target slug, status, canonical, indexing behavior, and internal links before redirecting. Then redirect `/medewerkers/` to `/mense/` and each old single to its verified equivalent. Preserve old route mapping in the migration manifest. Do not change or flush rewrite rules as part of discovery.

## Rollback contract

Before any write migration, preserve:

- Legacy post IDs, titles, slugs, statuses, author IDs, timestamps, excerpt, content, featured image, and all ACF values.
- Legacy role/group term IDs and assignments; `content_topic` and `content_category` term IDs and assignments.
- All incoming relationship edges, including trashed related products and source event references.
- Target Person IDs and slugs, migration timestamps, source fingerprints, and row-level migration state.
- Old URLs, target URLs, canonical/robots observations, and the eventual redirect map.
- A recoverable export or snapshot sufficient to restore the old values and links without relying on title matching.

No legacy record or relationship should be deleted during the dual-run. Rollback must be rehearsed against staging before decommissioning.

## Decommission gate

Do not remove or rename `medewerker`, its fields, taxonomies, or compatibility behavior until:

1. Every legacy record has an evidence-backed `MIGRATE` mapping, an explicit reviewed `DO_NOT_MIGRATE` exclusion, or a resolved `DEFER` decision that blocks decommissioning until closed.
2. Every relationship domain has an owner-approved migration and every edge is independently provenance-approved and reconciled to verified target records.
3. Bricks templates, query modes, dynamic tags, FluentSnippets, editorial flows, URLs, menus, REST consumers, and any code references point at approved targets.
4. Product copy automation can no longer write legacy IDs.
5. Public pages, canonical and indexing behavior, redirects, and internal links are verified.
6. Runtime and source searches show zero active legacy consumers across all relevant record statuses.
7. Rollback data is retained and the removal review approves the gate.

Task 5A meets none of the removal gates. The register must remain transitional/deferred and no item should be marked `READY_TO_REMOVE`.

## Explicit non-actions

Task 5C was read-only. It did not create or modify Person records, person_type, event_participation, fields, profile values, links, snippets, Bricks data, routes, or redirects. Task 5D has not started.


## Related evidence

- [Current-state audit](PERSON_CURRENT_STATE_AUDIT.md)
- [Profile migration manifest](PERSON_PROFILE_MIGRATION_MANIFEST.csv)
- [Profile provenance audit](PERSON_PROFILE_PROVENANCE_AUDIT.md)
- [Profile evidence ledger](PERSON_PROFILE_PROVENANCE_EVIDENCE.csv)
- [Relationship provenance](PERSON_RELATIONSHIP_PROVENANCE.csv)
- [Legacy classification mapping](PERSON_LEGACY_CLASSIFICATION_MAPPING.csv)
- [Consumer inventory](PERSON_CONSUMER_INVENTORY.csv)
- [Decommission register](LEGACY_DECOMMISSION_REGISTER.md)
- [Post-Task 5A snapshot](POST_TASK_5A_CURRENT_STATE.md)
- [Post-Task 5C snapshot](POST_TASK_5C_CURRENT_STATE.md)
