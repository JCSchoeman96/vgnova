# Voelgoed current state after Task 3

- Inspection date: 2026-10-14
- Site: `vgnova` at `http://localhost:10039/`
- Implementation branch: `feat/content-topic-roles`
- Base: `70d3818210e6e25c510f792219b282f09647af0d`

Task 3 adds the Primary Topic role and derives Secondary Topics and Effective Topics from the existing `content_topic` taxonomy. It does not reclassify the seven Task 2 migration seeds.

## Current taxonomy data

| Taxonomy | Terms | Roots | Relationships | Objects |
|---|---:|---:|---:|---:|
| `content_topic` | 55 | 7 | 7 | 7 |
| `content_category` | 13 | n/a | 8 | 7 |

The `content_topic` taxonomy remains attached to `post`, `mto-course`, `product`, `tc_events`, `medewerker`, `uitsending`, and `kompetisie`.

## Seven Task 2 migration seeds

| Object | Type | Direct Topic | Primary | Classification state |
|---:|---|---|---|---|
| 514 | `post` | `nuwe-jy` | None | `MIGRATION_SEED` |
| 516 | `post` | `geloof` | None | `MIGRATION_SEED` |
| 518 | `post` | `ouerskap` | None | `MIGRATION_SEED` |
| 519 | `post` | `werk-geld` | None | `MIGRATION_SEED` |
| 521 | `post` | `mensstories` | None | `MIGRATION_SEED` |
| 523 | `product` | `nuwe-jy` | None | `MIGRATION_SEED` |
| 641 | `post` | `self` | None | `MIGRATION_SEED` |

The Task 2 option `vgpc_content_topic_migration_local_v1` remains present with its seven original relationship rows. No Primary metadata exists on the seven seeds.

For these unreviewed seeds, the API's mechanical Secondary Topics result equals each object's Direct Topics because no term is currently Primary. This result does not mark the seed as editor-reviewed. The classification state remains `MIGRATION_SEED` until an editor chooses a valid Primary.

Each assigned term is a root pillar, so the current Effective Topics for each seed equal its Direct Topics. No inferred ancestor relationships are stored.

## Primary and Secondary role state

Platform Core registers the ACF group `group_vg_content_topic_roles` from source-controlled PHP. It has one field, `Content Topic: Primary Topic`, with field key `field_vg_primary_content_topic` and meta key `_vg_primary_content_topic`.

The field reads `content_topic` terms and uses `save_terms=0`, `load_terms=0`, `return_format=id`, and `multiple=0`. The native taxonomy control remains the source of direct membership. Secondary Topics are derived and are not stored in a second field.

The group appears on `post`, `mto-course`, `product`, `tc_events`, `uitsending`, and `kompetisie`. It does not appear on `medewerker`.

## Legacy and commercial data

`content_category` remains live and unchanged. Product 523 retains both `nuwe-jy` and `natuurlik-gesond` in `content_category`; its only `content_topic` is `nuwe-jy`. The `natuurlik-gesond` legacy term is not a Primary, Secondary, or Effective Topic.

## Editor and QA state

Posts require a Primary by editorial policy after review, but save enforcement remains soft during migration. Other supported object types keep Primary optional. Medewerkers use topic assignments for expertise and have no Primary/Secondary role.

Temporary draft `1216` was used to verify Primary selection (`aftrede`), Secondary derivation (`gesondheid`), ancestor expansion, `REVIEWED` state, and the persisted invariant. The migration record was unchanged. The draft was permanently deleted and a read-only check confirmed it is absent.

The ACF schema and field locations were read through Novamira. The WP admin preview opened to a login screen, so a browser inspection of the edit form was not completed.

## Data and interface impact

| Area | Changed by Task 3 |
|---|---|
| `content_topic` terms | No |
| `content_topic` relationships | No |
| `content_category` terms or relationships | No |
| Seven Task 2 classifications | No |
| Task 2 migration record | No |
| Templates or queries | No |
| URLs | No |
| Commercial mappings | No |

The source module and the local Platform Core runtime copy of `ContentTopicRoles.php` match at SHA-256 `dd691967d8cb321af25933fb80a365af31a7fb6684074f38b4d6185b5502806c`. This branch is for an unmerged review PR. Task 3B and production-corpus migration have not started.

## Verification

| Check | Result |
|---|---|
| Focused content-topic suite | 50 passed, 0 failed. |
| Platform Core dashboard suite | 127 passed, 0 failed. |
| PHP lint for all PHP files in the source-controlled content-topic module | Passed, 8 files checked. |
| `git diff --check` | Passed. |
| Final read-only site snapshot | 55 topic terms, 7 roots, 7 relationships; 13 legacy terms, 8 relationships; all seven seeds unchanged. |
