# Event Participation Foundation Design

| Property | Value |
|---|---|
| Task | Voelgoed Task 6A |
| Date | 2026-10-18 |
| Status | Approved for implementation after user approval on 2026-10-18. |
| Base | `main` at `cd1e6c8304b21f768610e56e79714f0e29e9adfd` |

## Purpose

Add the canonical relationship between Tickera Events (`tc_events`) and canonical People (`person`). Each relationship is one administrative `event_participation` post. Event-specific roles and display order belong to that relationship.

This task creates the schema and Platform Core behavior without migrating any existing event, product, or profile relationship. It creates no persistent Person or participation records and changes no Bricks or FluentSnippets consumer.

## Ownership and source files

| Concern | Owner | Source |
|---|---|---|
| Event records | Tickera | Existing `tc_events` post type |
| Participation CPT, role taxonomy, and fields | ACF Pro Local JSON | `site-code/acf-json/` |
| Pair validation, title derivation, queries, and role seeding | Voelgoed Platform Core | `site-code/voelgoed-platform-core/` |
| Active WordPress copy | Deployment target | `wp-content/plugins/voelgoed-platform-core/`, updated from source after review |

The three new ACF definitions are:

```text
site-code/acf-json/post_type_event_participation.json
site-code/acf-json/taxonomy_event_role.json
site-code/acf-json/group_vg_event_participation_details.json
```

No database-only ACF definition is canonical. `content_topic` remains the only taxonomy-registration exception owned by Platform Core. Task 6A does not change that ownership decision.

## Participation post type

Register `event_participation` through ACF with these settings:

| Setting | Value |
|---|---|
| `public` | `false` |
| `publicly_queryable` | `false` |
| `exclude_from_search` | `true` |
| `show_ui` | `true` |
| `show_in_rest` | `true` |
| `has_archive` | `false` |
| `rewrite` | `false` |
| `query_var` | `false` |
| Native supports | `title` only |

The title is administrative. There is no public single route or archive. Do not enable editor, excerpt, thumbnail, comments, author, or authored-content revisions.

### Fields

The field group `group_vg_event_participation_details`, titled `Event Participation Details`, applies only to `post_type == event_participation`.

| Name | Admin label | Type | Configuration |
|---|---|---|---|
| `event_participation_event` | Event Participation: Event | Post Object | `tc_events` only, one value, required, return ID |
| `event_participation_person` | Event Participation: Person | Post Object | `person` only, one value, required, return ID |
| `event_participation_roles` | Event Participation: Roles | Taxonomy | `event_role`, multiple, optional, save and load native terms, do not add terms in this field |
| `event_participation_display_order` | Event Participation: Display Order | Number | Optional, default `0`, integer, minimum `0` |

The Event and Person fields are the canonical endpoints. No `event_people`, `event_persons`, or other direct Event-to-Person field is added to `tc_events`. Roles use native taxonomy relationships only. No duplicate serialized role list is stored.

Do not add session, stage, room, track, time, biography/photo override, notes, appearance status, livestream, ticket, booking, payment, presentation, or external booking fields in Task 6A.

## Event role taxonomy

Register `event_role` through ACF and attach it only to `event_participation`.

| Setting | Value |
|---|---|
| `hierarchical` | `false` |
| `public` | `false` |
| `publicly_queryable` | `false` |
| `show_ui` | `true` |
| `show_in_rest` | `true` |
| `rewrite` | `false` |
| `query_var` | `false` |

The vocabulary is exactly:

| Name | Slug |
|---|---|
| Spreker | `spreker` |
| Paneellid | `paneellid` |
| Fasiliteerder | `fasiliteerder` |
| Gas | `gas` |

Do not seed `Mentor`, `Kenner`, `Kunstenaar`, `Skrywer`, or `Aanbieder`. Those describe possible Person classifications, editorial relationships, or an unresolved durable-versus-contextual concept. Task 6A does not decide those meanings.

### Seeder behavior

Add an explicit, source-controlled, idempotent role seeder in Platform Core. Before any write, it must confirm the taxonomy exists and the current vocabulary has no same-slug/wrong-name conflict, same-name/wrong-slug conflict, or unexpected term. On any conflict, stop before changing taxonomy state. With a clean or empty vocabulary, create only the missing approved terms, verify the resulting exact vocabulary, and create no term relationships.

The seeder tests cover an empty vocabulary, a rerun, the exact vocabulary, both conflict directions, an unexpected term, and a missing taxonomy.

## Identity, lifecycle, and validation

The canonical identity of a participation is `(event_id, person_id)`. The post title is not an identifier.

There may be only one non-retired participation for an Event × Person pair. Duplicate detection includes `publish`, `draft`, `pending`, `private`, and `future`. It ignores `trash` and `auto-draft`. Updating the same participation is allowed. A Person with several event roles has one participation with several `event_role` terms.

One shared Platform Core pair-check routine powers both ACF/admin validation and the canonical write/service path. The ACF editor cannot be the only enforcement layer. Supported canonical writes must validate the pair before accepting it. Direct SQL and arbitrary unsupported WordPress writes are outside the canonical write contract.

Before publishing, the participation must reference an existing, non-trashed `tc_events` post and an existing, non-trashed `person` post. Missing IDs and values of any other post type are rejected. Draft editing may remain incomplete where the WordPress or ACF workflow requires it, but incomplete records never appear in active queries.

WordPress post status defines participation lifecycle:

| Status | Meaning |
|---|---|
| `publish` | Active participation |
| `draft` | Incomplete or inactive |
| `trash` | Retired or deleted relationship |

Do not add a separate participation status field.

## Administrative title

Derive the native title from the current endpoint titles:

```text
{Event title} — {Person title}
```

Recalculate it when a participation is saved and when either endpoint title changes. A manually entered title is replaced by the derived title and never becomes business data. Title changes do not change pair identity.

## Query contract

Platform Core exposes four canonical query behaviors. Exact PHP class and method names follow the existing module conventions.

| Query | Result |
|---|---|
| Participations for an Event | Valid published participation records for that Event |
| Participations for a Person | Valid published participation records for that Person |
| People for an Event | Valid Person endpoints reached through published participation records |
| Events for a Person | Valid `tc_events` endpoints reached through published participation records |

All four queries must reject malformed endpoint references whose post type is not the expected type or whose post is missing or trashed. Draft, pending, private, future, trash, and auto-draft participation records are not active query results.

Event-to-participant results sort by `event_participation_display_order` ascending, then participation ID ascending. Person-to-event results use the same deterministic relationship ordering. Do not sort by Event date or invent chronological behavior.

## Runtime deployment and ACF sync

All Platform Core changes first land in `site-code/voelgoed-platform-core/`. Update the module bootstrap and `integration.patch` as needed, then apply the source to the active plugin copy using the existing deployment procedure. The runtime copy must not contain unique architecture code.

Check ACF JSON status and dry-run each of the three named keys. Sync only those definitions. Do not run unrestricted `wp acf json sync`; unrelated pending ACF definitions remain untouched. Verify source/runtime parity for the three definitions after sync. Generated runtime metadata is not source configuration drift.

Run the role seeder after taxonomy registration. The resulting runtime vocabulary has exactly the four approved roles and no participation assignments.

## Runtime QA

Use one existing published Tickera Event with no `event_medewerkers` relationship and an ID other than `763`. Snapshot its ID, status, slug, modified value, `event_medewerkers`, and its relevant taxonomy relationships before QA. Do not modify the selected Event.

Create one temporary Person and one temporary participation with unique ownership tokens and `[QA][AUTOMATED]` markers. Do not use profiles `753` through `757` or their names. The participation connects only the selected Event and temporary Person, has at least two approved roles, and uses a non-default display order.

Runtime QA proves CPT visibility and route behavior, endpoint restrictions, taxonomy save/load, display order, derived title, all four query directions, multiple roles on one record, and duplicate rejection through the canonical write path and admin/business validation where practical.

Cleanup runs in `finally` or equivalent guarded cleanup. Before deletion, it verifies each temporary record's post type and ownership token. It hard-deletes the temporary participation and Person, then verifies no matching post in any status, post meta, or taxonomy relationship remains. It never selects records for deletion by title alone. The four canonical event role terms remain.

After cleanup, compare the selected Event's protected snapshot with its pre-QA value. Also verify that profiles `753` through `757`, attachments `558`, `560`, `562`, `564`, and `566`, Event `763` and its two excluded legacy edges, Products `767` and `768`, their `produk_bron_event` and `produk_medewerkers` values, and `content_topic` vocabulary are unchanged. Confirm that no Person records or Person `person_type` and `content_topic` relationships remain.

## Documentation updates

Create:

```text
docs/architecture/EVENT_PARTICIPATION_IMPLEMENTATION.md
docs/architecture/POST_TASK_6A_CURRENT_STATE.md
docs/architecture/IMPLEMENTATION_LEDGER.md
```

The implementation document records ownership, schema, fields, roles, uniqueness, query behavior, title derivation, runtime deployment and QA, coexistence with legacy data, and the consumer migration boundary. The current-state snapshot records observed runtime state, controlled writes, exact tests, and final protected-state checks. The ledger stays a short index with historical facts backed by Git or existing evidence only. Its Task 6A row uses a pending PR and merge SHA until those values exist.

Update the Person migration architecture, consumer inventory, and legacy decommission register only where needed to record that the canonical Event Participation architecture is available. Mark Event Single `774`, product automation, and the Medewerker reverse Event query as legacy consumers. Do not mark them migrated. Record that the new foundation satisfies an architectural dependency while data and consumer gates still block removal of `medewerker`, `event_medewerkers`, and `produk_medewerkers`.

## Coexistence and exclusions

Keep `event_medewerkers` operational and unchanged. Do not retarget, rename, hide, clear, copy, or synchronize it. Migrate zero existing event edges, product edges, and Person profiles. In particular, keep Event `763`'s two relationships excluded and unchanged. Keep Products `767` and `768`, `produk_bron_event`, `produk_medewerkers`, and `9-acf-product-copy.php` unchanged.

Do not modify `10-vg-bricks-medewerker-queries.php`, Bricks templates `772`, `773`, `774`, `124`, or `1127`, or components `6b463f` and `4d1dd4`. Do not change CSS, SCSS, ACSS, frontend cards, Event routing, Tickera, event categories, ticketing, or create an Event session model. Do not run Task 5D or decommission any legacy profile or relationship system.

## Verification gates

The implementation is ready for review only when:

1. The three ACF JSON definitions are source-controlled and match runtime after targeted sync.
2. The Event Participation post type has no public route, archive, rewrite, or search inclusion.
3. The four approved event roles exist exactly, and the seeder is idempotent and conflict-safe.
4. Event and Person endpoints are single-valued, required for publication, and type-checked.
5. One Event × Person pair supports multiple roles, deterministic order, and a derived title.
6. Shared pair validation rejects duplicate non-retired pairs in both admin and canonical service writes.
7. Canonical queries return only valid published relationships in the required order.
8. Runtime QA proves both directions and removes every owned temporary record.
9. Persistent Person and Event Participation counts remain zero after QA.
10. All protected legacy state, Bricks, and FluentSnippets remain unchanged; no legacy relationship is migrated.
11. Person Foundation, Platform Core, and Editorial Dimensions regression suites pass. Report the previous Platform Core baseline `67`, the new total, and the Task 6A-specific increase.
12. PHP lint, JSON parsing, and `git diff --check` pass.
13. The feature branch is pushed and its PR remains open and unmerged for independent review.

The repository baseline suites pass at 10 Person Foundation tests, 67 Platform Core tests, and 11 Editorial Dimensions tests. Task 6A source tests add coverage for role seeding, pair validation, active queries, endpoint integrity, ordering, title derivation, and multiple roles.
