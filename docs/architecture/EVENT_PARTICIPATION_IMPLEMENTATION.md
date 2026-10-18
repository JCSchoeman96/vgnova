# Event Participation implementation

**Task:** Voelgoed Task 6A
**Status:** Foundation implemented and active on local staging; legacy consumer migration has not started
**Runtime writes:** Three targeted ACF definition syncs, four approved `event_role` terms, Platform Core deployment, and temporary QA records that were removed

## Ownership

| Concern | Owner | Source |
|---|---|---|
| Event records | Tickera | Existing `tc_events` post type |
| Participation post type, role taxonomy, and fields | ACF Pro Local JSON | [`site-code/acf-json/`](../../site-code/acf-json/) |
| Pair validation, writes, queries, admin validation, title updates, and role seeding | Voelgoed Platform Core | [`site-code/voelgoed-platform-core/includes/Event/`](../../site-code/voelgoed-platform-core/includes/Event/) |
| Active plugin copy | Local WordPress deployment | `wp-content/plugins/voelgoed-platform-core/` |

The ACF source definitions are `post_type_event_participation.json`, `taxonomy_event_role.json`, and `group_vg_event_participation_details.json`. The database is runtime state; the tracked Local JSON is canonical. `content_topic` remains the only taxonomy registration exception owned by Platform Core.

## Schema

`event_participation` is an administrative CPT registered by ACF. It is not public or publicly queryable, is excluded from search, has no archive, permalink, or query variable, appears in the admin and REST API, and supports only the native title. The ACF CPT JSON uses `rewrite.permalink_rewrite: no_permalink`; using a bare `rewrite: false` is not equivalent in ACF's CPT loader.

The `Event Participation Details` field group applies only to `event_participation`:

| Field | Admin label | Type and contract |
|---|---|---|
| `event_participation_event` | Event Participation: Event | Required single Post Object to `tc_events`, returning an ID |
| `event_participation_person` | Event Participation: Person | Required single Post Object to `person`, returning an ID |
| `event_participation_roles` | Event Participation: Roles | Optional multi-select `event_role` taxonomy field; saves and loads native terms; editors cannot add terms inline |
| `event_participation_display_order` | Event Participation: Display Order | Optional non-negative integer, default `0` |

The Event and Person fields are the canonical endpoints. No relationship field was added to Tickera Events, and role values are not duplicated in post meta. Session, stage, room, time, biography overrides, notes, ticketing, booking, and payment data are outside this model.

`event_role` is a flat, non-public ACF taxonomy attached only to `event_participation`. It has an admin UI and REST support, with no public query, rewrite route, or query variable. Its exact controlled vocabulary is:

| Name | Slug |
|---|---|
| Spreker | `spreker` |
| Paneellid | `paneellid` |
| Fasiliteerder | `fasiliteerder` |
| Gas | `gas` |

## Platform Core behavior

| Class | Responsibility |
|---|---|
| `EventParticipationRepository` | Validates endpoint types, finds duplicate pairs, reads active participations, returns endpoint IDs, applies deterministic ordering, and performs fresh locked rechecks with candidate IDs plus cache-refreshed ACF values |
| `EventParticipationPairLock` | Acquires database named locks scoped to an Event × Person pair or one existing participation, and supports scoped cleanup that releases only newly acquired locks in reverse order |
| `EventParticipationService` | Validates Event × Person pairs, validates roles and display order, serializes creation for the same pair, creates participation posts and ACF/taxonomy values, and removes a newly created post if its relationship writes fail; rejects `future` creation because the API has no schedule-date input |
| `EventParticipationAdmin` | Locks existing participations before reading stored endpoints; validates submitted ACF Event and Person fields against the shared pair rule; rechecks under the pair lock before WordPress saves a row; allows retirement with invalid endpoints; recalculates titles under a scoped participation lock after refreshing pair fields and both endpoint posts |
| `EventRoleSeeder` | Registers the explicit `wp vg-event-role-setup` command and seeds only the approved role vocabulary after a complete conflict check |

### Identity and uniqueness

The pair `(event_id, person_id)` is the identity. A Person may have only one non-retired participation for an Event; multiple contextual roles belong on that one participation. Duplicate detection includes `publish`, `draft`, `pending`, `private`, and `future` records, while `trash` and `auto-draft` do not reserve a pair. Editing the current participation excludes its own ID from the duplicate check.

This is an application-level contract enforced by the service and WordPress admin save path. Because the ACF endpoints live in post meta and have no database unique index, pair writes take a MySQL named lock, recheck duplicates while holding it, and keep it through the write. Existing editor updates first take a participation-ID lock, then the target pair lock; both remain held through ACF persistence so two edits to different pairs cannot interleave. Status updates use the same participation lock. A transition to `trash` skips endpoint validation so a participation with a missing or trashed endpoint can still be retired.

The service creates `publish`, `draft`, `pending`, and `private` participations. It does not accept `future` because its input has no scheduled publication date. Existing future participations still reserve their Event × Person pair.

The locked duplicate recheck selects candidate IDs from the database, clears WordPress post and ACF value caches, and reads the stored endpoints through `get_field()`. A candidate post read error fails closed; a candidate that no longer exists without a database error is skipped. The staging database is MySQL 8.0.35; no database-version compatibility matrix was run. If a lock or authoritative database lookup fails, the write fails closed. Direct SQL writes and unsupported writers are outside the canonical write path. A participating Event must be an existing, non-trashed `tc_events` post; the Person must be an existing, non-trashed `person` post. Active relationship queries include only published participations and reject malformed or trashed endpoints.

### Query API

`EventParticipationRepository` returns integer IDs through these methods:

| Method | Result |
|---|---|
| `participations_for_event($event_id)` | Published participation IDs for one valid Event |
| `participations_for_person($person_id)` | Published participation IDs for one valid Person |
| `people_for_event($event_id)` | Valid Person IDs reached through published participations |
| `events_for_person($person_id)` | Valid Event IDs reached through published participations |
| `live_participations_for_event($event_id)` | Non-retired participation IDs for pair validation and title updates |
| `live_participations_for_person($person_id)` | Non-retired participation IDs for pair validation and title updates |

Relationships sort by `event_participation_display_order` ascending, then participation ID ascending. The implementation does not infer chronological order from Event dates.

### Administrative title

The title is derived as `{Event title} — {Person title}`. ACF saves recalculate it, and changing either endpoint title updates its non-retired participations. Title sync acquires the participation lock before reading status, endpoints, or title, refreshes the endpoint fields and both endpoint post caches after the lock, and holds it through `wp_update_post()`. Its scoped cleanup preserves locks held by an outer ACF save and releases new locks in reverse order. The title is not an identifier or manually maintained business value.

### Role governance

`EventRoleSeeder` verifies that the taxonomy exists and checks the complete existing vocabulary for unexpected terms, duplicate slugs, and name/slug conflicts before it writes. It creates only missing approved terms, then verifies the final exact vocabulary. Re-running it is safe. It creates no participation assignments and does not seed `Mentor`, `Kenner`, `Kunstenaar`, `Skrywer`, or `Aanbieder`.

## Runtime deployment and ACF synchronization

Platform Core source changes belong in `site-code/voelgoed-platform-core/` first. The five Event classes and the bootstrap integration were deployed to the active local plugin copy. The final review fixes were copied after a backup at `/tmp/vgnova-task6a-round4-runtime-backup-20261018`; all four modified Event class files passed byte-for-byte comparison with source. The endpoint-title cache fix was copied after a backup at `/tmp/vgnova-task6a-round5-runtime-backup-20261018`; `EventParticipationAdmin.php` passed byte-for-byte comparison with source. The default Novamira MCP profile initially resolved to a different local installation (`DanOptics`), so the initial runtime checks used local WP-CLI. For the final review QA, the explicitly selected `VGNova` Novamira profile was verified against `Voelgoed` at `http://localhost:10039/` and ran the guarded WP-CLI script. Local shell copied the source-controlled PHP files into the active plugin because Novamira file writes do not permit PHP in the active plugin directory. No direct SQL writes were used.

Only the three Task 6A ACF keys were dry-run and synchronized:

```sh
wp acf json sync --key=post_type_event_participation --dry-run
wp acf json sync --key=taxonomy_event_role --dry-run
wp acf json sync --key=group_vg_event_participation_details --dry-run

wp acf json sync --key=post_type_event_participation
wp acf json sync --key=taxonomy_event_role
wp acf json sync --key=group_vg_event_participation_details
```

The three definitions were compared with the active ACF configuration after sync. Generated ACF metadata and empty runtime defaults were excluded from the configuration comparison. The unrelated pending `group_vgmp_perk_details` and `group_vgmp_options` definitions were left untouched. The role seeder was run twice: the first run created the four roles; the second found all four and created none.

The active Bricks Child `acf-json` link was changed from the stable repository path to the Task 6A worktree's `site-code/acf-json/` so ACF reads the feature definitions during review. This is temporary: the runtime depends on the feature worktree until post-merge Task 6A-S copies the merged source into the main checkout, repoints the link, and verifies ACF parity. Do not remove the worktree before that stabilization succeeds.

## Runtime QA

Run the guarded QA inside the loaded local WordPress runtime:

```sh
wp eval-file site-code/runtime-tests/event-participation-qa.php
```

The script selects a published Tickera Event other than Event 763 with no legacy `event_medewerkers` value. It creates one uniquely owned temporary Person and one participation, checks pair validation, roles, ordering, title derivation, query direction, private exclusion, duplicate rejection, invalid endpoints, and non-public routing, then removes only records with its ownership marker. Preflight and setup failures emit JSON with `mutation_started: false` before any write. A focused setup-boundary regression check is available at `site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php`.

The final staging QA run passed against Event 151 with temporary Person 1310 and participation 1311. It covered duplicate rejection, private-status exclusion, non-public routing, invalid endpoints, Person-title synchronization, and retiring the participation while its Person was trashed, then restoring and publishing it after the Person was restored. Cleanup verified zero remaining Person and participation records, no QA token, no relevant Person or role relationships, no cleanup errors, and unchanged protected state and QA Event. The selected Event was not edited. Event 763, profiles 753–757, attachments 558/560/562/564/566, and Products 767/768 remained unchanged. A separate runtime smoke check acquired and released a named lock through the active WordPress database connection.

An earlier post-review QA attempt exposed a malformed newline in the fresh duplicate query. It created temporary orphan participation 1282 for Event 151 and Person 1280, then the Person was removed by the guarded cleanup. The remaining row carried that run's `[QA][AUTOMATED]` title token and had no ownership marker because the failure happened during the duplicate attempt. After confirming the baseline had zero participations and the row matched the failed run, it was hard-deleted and verified absent. The corrected guarded run above then passed with clean state.

## Legacy coexistence and migration boundary

The foundation does not migrate any existing relationship. The two `event_medewerkers` edges on Event 763 and three `produk_medewerkers` edges on Products 767/768 remain present and are excluded from migration under Task 5C. Their exclusion concerns those known edges only; it does not decide whether the linked profile records are genuine. All five `medewerker` profiles remain `DEFER`.

The legacy ACF fields, Bricks templates and components, `10-vg-bricks-medewerker-queries.php`, and FluentSnippets `9-acf-product-copy.php` remain active and unchanged. Event Single 774, the Medewerker reverse-event query, and product copy automation remain legacy consumers. Product field retargeting and copy automation must be migrated together in a separately approved task after event and Person data eligibility is resolved. No consumer migration, profile migration, route change, or decommissioning started in Task 6A.

## Related records

- [Approved design](../superpowers/specs/2026-10-18-event-participation-foundation-design.md)
- [Post-Task 6A current state](POST_TASK_6A_CURRENT_STATE.md)
- [Person migration architecture](PERSON_MIGRATION_ARCHITECTURE.md)
- [Person consumer inventory](PERSON_CONSUMER_INVENTORY.csv)
- [Legacy decommission register](LEGACY_DECOMMISSION_REGISTER.md)
