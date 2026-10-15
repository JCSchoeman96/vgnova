# Voelgoed current state after Task 3C

- Inspection date: 2026-10-15
- Site: `vgnova` at `http://localhost:10039/`
- Branch: `feat/finalize-local-topic-review`
- Base: `origin/main` after PR #5 merge, `58f0b7a36a21144c2586eb9cef2845540527c3d2`
- Scope: retire the Task 2 local proof lifecycle and apply the two approved
  HIGH-confidence classifications only

Task 3C retired the Task 2 local proof migration and applied the approved
classifications for 519 and 523. No other seed was reviewed or changed.

## Migration lifecycle and provenance

The Task 2 historical option `vgpc_content_topic_migration_local_v1` remains
unchanged. Its source fingerprint is
`80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847`; the
serialized-record SHA-256 is
`605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95`.
It still records the original seven relationships:

| Object | Original Task 2 relationship |
|---:|---|
| 514 | `nuwe-jy` |
| 516 | `geloof` |
| 518 | `ouerskap` |
| 519 | `werk-geld` |
| 521 | `mensstories` |
| 523 | `nuwe-jy` |
| 641 | `self` |

The separate lifecycle option
`vgpc_content_topic_migration_local_v1_lifecycle` reports:

```text
status: retired
reason: editorial classification supersedes local migration seeds
retired_at: 2026-10-15T04:53:54+00:00
migration: content-topic-local-v1
```

The default dry run remains read-only and reports `lifecycle: retired`. Apply
and rollback both refuse with `vgpc_topic_migration_retired`; neither depends
on relationship drift to stop. The historical record is not deleted or
rewritten.

## Task 3C before-state snapshots

Immediately before classification, both objects had no Primary and state
`MIGRATION_SEED`:

| Object | Direct before | Primary before | State before | `content_category` before |
|---:|---|---|---|---|
| 519 | `werk-geld` (term ID 377) | None | `MIGRATION_SEED` | `werk-geld` |
| 523 | `nuwe-jy` (term ID 349) | None | `MIGRATION_SEED` | `nuwe-jy`, `natuurlik-gesond` |

Both mutations used `ContentTopicRoles::set_primary_topic()` to add and set the
approved specific topic, verified Direct and Primary membership, then removed
the obsolete root through `wp_remove_object_terms()`. Both final states passed
role, hierarchy, ancestor, state, and legacy-category checks. No recovery was
needed.

## Final seven-object state

| ID | Type | Direct Topics | Primary | Secondary | Effective Topics | State | `content_category` |
|---:|---|---|---|---|---|---|---|
| 514 | `post` | `nuwe-jy` | None | `nuwe-jy`* | `nuwe-jy` | `MIGRATION_SEED` | `nuwe-jy` |
| 516 | `post` | `geloof` | None | `geloof`* | `geloof` | `MIGRATION_SEED` | `geloof` |
| 518 | `post` | `ouerskap` | None | `ouerskap`* | `ouerskap` | `MIGRATION_SEED` | `ouerskap` |
| 519 | `post` | `begroting-geldbestuur` | `begroting-geldbestuur` | None | `begroting-geldbestuur`, `geld`, `werk-geld` | `REVIEWED` | `werk-geld` |
| 521 | `post` | `mensstories` | None | `mensstories`* | `mensstories` | `MIGRATION_SEED` | `mensstories` |
| 523 | `product` | `oefening-fiksheid` | `oefening-fiksheid` | None | `oefening-fiksheid`, `nuwe-jy` | `REVIEWED` | `natuurlik-gesond`, `nuwe-jy` |
| 641 | `post` | `self` | None | `self`* | `self` | `MIGRATION_SEED` | `self` |

* With no Primary selected, the role API mechanically returns Direct Topics from
its Secondary getter. This is not an editorially reviewed Secondary assignment.

No Direct Topic duplicates an ancestor and child. Product 523's
`natuurlik-gesond` remains only in `content_category`; it is not Direct,
Primary, Secondary, or Effective `content_topic` data.

## Taxonomy counts and scope

| Taxonomy | Terms | Roots | Relationships | Objects |
|---|---:|---:|---:|---:|
| `content_topic` | 55 | 7 | 7 | 7 |
| `content_category` | 13 | 8 | 8 | 7 |

`content_category` relationships are unchanged. Objects 514, 516, 518, 521, and
641 remain migration seeds with their Task 2 Direct Topics and no Primary.
Production-corpus migration was not performed, and Task 4 has not started.

## Editor UX and verification

- Browser editor UX verification: **COMPLETE**. Authenticated access was
  available through a temporary Novamira admin-access exchange; no credentials
  were requested.
- On product 523, the `Content Topic: Primary Topic` selector displayed
  `Oefening & fiksheid`. The native `Onderwerpe` panel showed only that Direct
  Topic.
- On post 519, the Primary selector displayed `Begroting & geldbestuur`.
- The content-topic test runner printed `66 content topic tests passed.` PHP
  lint passed for `ContentTopicMigration.php`; `git diff --check` passed.

The Task 3B snapshot remains preserved in
[`POST_TASK_3B_CURRENT_STATE.md`](POST_TASK_3B_CURRENT_STATE.md).
