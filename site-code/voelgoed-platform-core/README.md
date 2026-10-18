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

- `EventParticipationRepository` reads canonical relationships and duplicate pairs.
- `EventParticipationService` validates and creates participations.
- `EventParticipationAdmin` validates ACF submissions and derives admin titles.
- `EventRoleSeeder` seeds the approved `event_role` vocabulary on request.

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

Seed the approved role terms through WP-CLI after the ACF taxonomy is registered:

```sh
wp vg-event-role-setup
```

Runtime deployment is separate from changes to this source directory. Apply
`integration.patch` to the runtime plugin bootstrap and deploy all four Event
classes before expecting the admin hooks or seeder command to be available.
Sync only the three Task 6A ACF JSON keys using the documented targeted procedure;
do not run a global ACF JSON sync.

The guarded staging check is `site-code/runtime-tests/event-participation-qa.php`.
Run it inside the loaded WordPress runtime with `wp eval-file`; it creates and
removes only uniquely owned temporary Person and participation records. The
setup failure boundary has a standalone regression check at
`site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php`.
