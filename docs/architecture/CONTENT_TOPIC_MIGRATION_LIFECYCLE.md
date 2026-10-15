# Content Topic Local Migration Lifecycle

## Task 2 local proof

Task 2 ran `content-topic-local-v1` as a controlled local proof. It added seven
canonical `content_topic` relationships to seven objects and saved the
historical record in `vgpc_content_topic_migration_local_v1`. That record
identifies which relationships Task 2 created. It is provenance, not the
migration's active/inactive status.

The reviewed Task 2 fingerprint is:

```text
80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847
```

## Lifecycle decision

The command lifecycle changed as follows:

```text
ACTIVE
  ├─ default command: read-only dry run
  ├─ --apply: allowed only with the reviewed fingerprint
  └─ --rollback: removes only the seven recorded relationships
       ↓ explicit retirement after editorial review
RETIRED
  ├─ default command: read-only dry run; reports lifecycle: retired
  ├─ --apply: refused with vgpc_topic_migration_retired
  └─ --rollback: refused with vgpc_topic_migration_retired
```

Task 3C retired the local proof before replacing the two approved seed
relationships. Editorial classification supersedes rollback-to-seed semantics:
restoring an old root relationship would undo a reviewed classification. The
five unreviewed objects remain `MIGRATION_SEED`; retirement does not mark them
reviewed or change their relationships.

## Separate retirement marker

Retirement is stored in the separate option
`vgpc_content_topic_migration_local_v1_lifecycle`:

```text
status: retired
reason: editorial classification supersedes local migration seeds
retired_at: 2026-10-15T04:53:54+00:00
migration: content-topic-local-v1
```

The marker contains no duplicate relationship snapshot. The original option
`vgpc_content_topic_migration_local_v1` remains unchanged, with serialized
SHA-256:

```text
605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95
```

Retirement uses the explicit command:

```sh
wp vg-content-topic-migrate --retire --fingerprint=<reviewed-fingerprint>
```

The command requires the historical record, expected migration ID, matching
fingerprint, and all seven original recorded relationships. It refuses a
conflicting marker. A valid existing marker makes the operation idempotent.
`--retire` is separate from `--apply`; the default command remains read-only.

## Classification state

`MIGRATION_SEED` still derives from the historical record, current Direct
Topics, and the absence of a valid Primary. It does not read lifecycle status.
An object can therefore remain a migration seed after retirement while its
original Direct Topic remains. A Primary assignment still yields `REVIEWED`
through the Platform Core role service.

## Task 3C live result

The retirement preflight and final Novamira verification reported:

- `content_topic`: 55 terms, 7 roots, 7 relationships across 7 objects.
- `content_category`: 13 terms and 8 relationships across 7 objects.
- The historical migration fingerprint and serialized-record checksum still
  match the Task 3B snapshot.
- The final direct topics are `begroting-geldbestuur` for object 519 and
  `oefening-fiksheid` for product 523. The other five original seed roots are
  unchanged.
- `content_category` was not changed. Product 523 retains `nuwe-jy` and
  `natuurlik-gesond`; the commercial term is not a semantic topic.

See [POST_TASK_3C_CURRENT_STATE.md](POST_TASK_3C_CURRENT_STATE.md) for the
object-by-object live state, before-state snapshots, tests, and editor UX check.
