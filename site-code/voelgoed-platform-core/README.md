# Voelgoed Platform Core content topic slice

This directory is the current canonical, source-controlled Platform Core code
in the `vgnova` repository. It tracks the `content_topic` module installed into
the existing Voelgoed Platform Core plugin at
`wp-content/plugins/voelgoed-platform-core`. The Local WordPress copy is not a
Git checkout. `integration.patch` records the two bootstrap changes made to
load and register this module.

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
