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
| `EventParticipationRepository` | Validates endpoint types, finds duplicate pairs, reads active participations, returns endpoint IDs, and applies deterministic relationship ordering |
| `EventParticipationService` | Validates Event × Person pairs, validates roles and display order, creates participation posts and ACF/taxonomy values, and removes a newly created post if its relationship writes fail |
| `EventParticipationAdmin` | Validates submitted ACF Event and Person fields against the shared pair rule; derives titles after ACF saves and endpoint title changes |
| `EventRoleSeeder` | Registers the explicit `wp vg-event-role-setup` command and seeds only the approved role vocabulary after a complete conflict check |

### Identity and uniqueness

The pair `(event_id, person_id)` is the identity. A Person may have only one non-retired participation for an Event; multiple contextual roles belong on that one participation. Duplicate detection includes `publish`, `draft`, `pending`, `private`, and `future` records, while `trash` and `auto-draft` do not reserve a pair. Editing the current participation excludes its own ID from the duplicate check.

This is an application-level contract enforced by the shared service and ACF admin validation. Direct SQL and unsupported writers are outside the canonical write path. A participating Event must be an existing, non-trashed `tc_events` post; the Person must be an existing, non-trashed `person` post. Active relationship queries include only published participations and reject malformed or trashed endpoints.

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

The title is derived as `{Event title} — {Person title}`. ACF saves recalculate it, and changing either endpoint title updates its non-retired participations. The title is not an identifier or manually maintained business value.

### Role governance

`EventRoleSeeder` verifies that the taxonomy exists and checks the complete existing vocabulary for unexpected terms, duplicate slugs, and name/slug conflicts before it writes. It creates only missing approved terms, then verifies the final exact vocabulary. Re-running it is safe. It creates no participation assignments and does not seed `Mentor`, `Kenner`, `Kunstenaar`, `Skrywer`, or `Aanbieder`.

## Runtime deployment and ACF synchronization

Platform Core source changes belong in `site-code/voelgoed-platform-core/` first. The four Event classes and the bootstrap integration were then deployed to the active local plugin copy. The Local WordPress site at `http://localhost:10039/` was identified by its `Voelgoed` site name. Novamira MCP resolved to a different local installation (`DanOptics`), so the approved local WP-CLI fallback was used for the correct staging runtime. No direct SQL writes were used.

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

The final staging QA run passed against Event 151 and temporary records 1272 and 1273. Cleanup verified zero remaining Person and participation records, no QA token, no relevant Person or role relationships, no cleanup errors, and unchanged protected state and QA Event. The selected Event was not edited. Event 763, profiles 753–757, attachments 558/560/562/564/566, and Products 767/768 remained unchanged.

## Legacy coexistence and migration boundary

The foundation does not migrate any existing relationship. The two `event_medewerkers` edges on Event 763 and three `produk_medewerkers` edges on Products 767/768 remain present and are excluded from migration under Task 5C. Their exclusion concerns those known edges only; it does not decide whether the linked profile records are genuine. All five `medewerker` profiles remain `DEFER`.

The legacy ACF fields, Bricks templates and components, `10-vg-bricks-medewerker-queries.php`, and FluentSnippets `9-acf-product-copy.php` remain active and unchanged. Event Single 774, the Medewerker reverse-event query, and product copy automation remain legacy consumers. Product field retargeting and copy automation must be migrated together in a separately approved task after event and Person data eligibility is resolved. No consumer migration, profile migration, route change, or decommissioning started in Task 6A.

## Related records

- [Approved design](../superpowers/specs/2026-10-18-event-participation-foundation-design.md)
- [Post-Task 6A current state](POST_TASK_6A_CURRENT_STATE.md)
- [Person migration architecture](PERSON_MIGRATION_ARCHITECTURE.md)
- [Person consumer inventory](PERSON_CONSUMER_INVENTORY.csv)
- [Legacy decommission register](LEGACY_DECOMMISSION_REGISTER.md)
