# Voelgoed Platform Core source modules

This directory is the current canonical, source-controlled Platform Core code
in the `vgnova` repository. It tracks the `content_topic` and Event Participation
modules installed into the existing Voelgoed Platform Core plugin at
`wp-content/plugins/voelgoed-platform-core`. The Local WordPress copy is not a
Git checkout. `integration.patch` records the bootstrap changes made to load
the source-controlled modules and register their lifecycle components.

Any Platform Core code change must first exist in this source-controlled
module. The active local WordPress plugin directory is a runtime deployment
copy and must not contain unique architecture changes that are absent from the
repository. Do not create a competing `content_topic` definition in ACF,
FluentSnippets, or theme code.

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
```

The active site seeds terms through `wp vg-content-topic-setup`. That command
creates only missing canonical terms, stops on slug or parent conflicts, and
flushes rewrite rules once after successful setup.

## Task 2 local content topic proof

`wp vg-content-topic-migrate` prints a read-only report by default. The Task 2
local proof migration is now retired after editorial review. Its historical
record remains in `vgpc_content_topic_migration_local_v1`; the separate
`vgpc_content_topic_migration_local_v1_lifecycle` option records retirement.

While active, the command supported this controlled flow:

```sh
wp vg-content-topic-migrate --format=json
wp vg-content-topic-migrate --apply --fingerprint=<reviewed-fingerprint> --format=json
wp vg-content-topic-migrate --format=json
wp vg-content-topic-migrate --rollback --format=json
```

The explicit retirement command is:

```sh
wp vg-content-topic-migrate --retire --fingerprint=<reviewed-fingerprint> --format=json
```

Retirement requires the Task 2 record, its reviewed fingerprint, and all seven
recorded canonical relationships to match the original manifest. It writes a
separate lifecycle marker and leaves the historical record unchanged. Repeating
retirement is safe. After retirement, dry run remains read-only and reports
`lifecycle: retired`; apply and rollback both fail with
`vgpc_topic_migration_retired` because editorial classification supersedes
rollback-to-seed behavior. `MIGRATION_SEED` continues to derive from the
historical record and current Direct Topics, so retirement alone does not mark
the five unreviewed objects as reviewed.

The apply command required the source fingerprint from the reviewed dry run.
If the legacy terms, assignments, canonical tree, or object types changed
before apply, it stopped without writing. The migration recorded only
canonical relationships it created. Rollback removed those recorded
relationships and left legacy category data and unrelated topic assignments in place.

## Event Participation foundation

The source contract, runtime state, legacy boundary, and post-merge Local JSON
stabilization gate are documented in
[`docs/architecture/EVENT_PARTICIPATION_IMPLEMENTATION.md`](../../docs/architecture/EVENT_PARTICIPATION_IMPLEMENTATION.md).

The Event Participation implementation lives in `includes/Event/`:

- `EventParticipationRepository` reads canonical relationships and rechecks candidate pairs with fresh post data plus ACF values while a write lock is held.
- `EventParticipationPairLock` serializes writes for the same Event × Person pair and concurrent edits to one existing participation. Scoped operations release only locks they acquire, in reverse order.
- `EventParticipationService` validates and creates participations under the pair lock. It rejects `future` status because creation accepts no scheduled publication date.
- `EventParticipationAdmin` locks existing participations before reading stored endpoints, rechecks pairs before writes, allows retirement with invalid endpoints, and derives titles under a scoped lock after refreshing the participation endpoints and their post caches.
- `EventRoleSeeder` seeds the approved `event_role` vocabulary on request.
- `EventParticipationBricks` supplies ordered repository IDs to the marked Event Single 774 participant query and hides the marked section on the frontend when no canonical participation exists. It has no `event_medewerkers` fallback.

The Bricks Posts query uses the semantic class token `vg-event-participation-query`. The participant section uses `vg-event-participants-section`. Platform Core changes behavior only for those exact markers.

Run the focused Bricks adapter checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
```

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

Seed the approved role terms through WP-CLI after the ACF taxonomy is registered:

```sh
wp vg-event-role-setup
```

Runtime deployment is separate from changes to this source directory. Apply
`integration.patch` to the runtime plugin bootstrap and deploy all six Event
classes before expecting the admin hooks, seeder command, or Bricks consumer to be available.
Sync only the three Task 6A ACF JSON keys using the documented targeted procedure;
do not run a global ACF JSON sync.

The guarded staging check is `site-code/runtime-tests/event-participation-qa.php`.
Run it inside the loaded WordPress runtime with `wp eval-file`; it creates and
removes only uniquely owned temporary Person and participation records. The
setup failure boundary has a standalone regression check at
`site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php`.
Created-record ownership and failure-safe cleanup have focused regressions at
`site-code/runtime-tests/tests/run-event-participation-qa-cleanup-tests.php`.

## Product People foundation

The Task 7A Product People foundation lives in `includes/Product/`:

- `ProductPeopleFields` owns the PHP-local ACF fields `product_people_mode` (`event` or `manual`, blank allowed, no default) and `product_people_override` (ordered `person` relationship, shown only in manual mode). It does not own `produk_bron_event` or `produk_medewerkers`.
- `ProductPeopleResolver` is the read-only canonical Product → Person authority. It resolves Event-derived People through `EventParticipationRepository` or manual People from `product_people_override`, keeps only published Persons, and returns `[]` when the mode is absent, empty, or invalid. It never reads `produk_medewerkers` and never writes.

No Product consumer has migrated yet. Product Single 124 and the legacy Product relationship helpers remain unchanged until the separate consumer-cutover task.

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Runtime deployment remains a copied plugin deployment. No ACF Local JSON sync is used for Task 7A.
