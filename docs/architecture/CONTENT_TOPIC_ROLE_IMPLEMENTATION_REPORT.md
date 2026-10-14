# Content topic role implementation report

- Inspection date: 2026-10-14
- Site: `vgnova` at `http://localhost:10039/`
- Base commit: `70d3818210e6e25c510f792219b282f09647af0d`
- Branch: `feat/content-topic-roles`

## Source review

The implementation follows the Task 3 brief and the repository's Task 2 state. Reviewed material included:

- `AGENTS.md` and `site-code/voelgoed-platform-core/README.md`;
- the canonical information architecture, topic migration, and taxonomy discovery UX documents;
- the Task 1 topic implementation report, Task 2 migration report, legacy-to-canonical mapping, and Task 2 current-state baseline;
- `ContentTopicTaxonomy.php`, `ContentTopicMigration.php`, their existing tests, the Platform Core integration patch, and the active WordPress registrations;
- the live ACF group inventory and the registered Primary field schema through Novamira.

The active `content_topic` taxonomy is attached to `post`, `mto-course`, `product`, `tc_events`, `medewerker`, `uitsending`, and `kompetisie`. There was no competing Primary Topic field before this role layer. The new ACF group is PHP registered by Platform Core and reported by ACF as source `php`, `editable=false`.

## Pre-change and final live snapshot

The Task 2 preflight was checked before the role code was added to the local plugin. A fresh read-only check after implementation and fixture cleanup returned the same counts and assignments.

| Taxonomy | Terms | Roots | Relationships | Objects |
|---|---:|---:|---:|---:|
| `content_topic` | 55 | 7 | 7 | 7 |
| `content_category` | 13 | n/a | 8 | 7 |

The seven `content_topic` assignments remain:

| Object | Type | Direct Topic |
|---:|---|---|
| 514 | `post` | `nuwe-jy` |
| 516 | `post` | `geloof` |
| 518 | `post` | `ouerskap` |
| 519 | `post` | `werk-geld` |
| 521 | `post` | `mensstories` |
| 523 | `product` | `nuwe-jy` |
| 641 | `post` | `self` |

Every seed still has no Primary metadata and derives `MIGRATION_SEED`. The Task 2 option `vgpc_content_topic_migration_local_v1` remains present with its original seven relationship rows. Product 523 still has `nuwe-jy` and `natuurlik-gesond` in `content_category`; only `nuwe-jy` is in `content_topic`.

## Storage and field configuration

Primary is stored as a term ID in `_vg_primary_content_topic`. The ACF group `group_vg_content_topic_roles` contains one taxonomy select, field key `field_vg_primary_content_topic`, with these settings:

```text
taxonomy: content_topic
field_type: select
return_format: id
multiple: 0
save_terms: 0
load_terms: 0
```

The field label is `Content Topic: Primary Topic`. Its instructions tell editors to select the most specific useful topic and explain that other direct topics are Secondary Topics. ACF does not write taxonomy relationships from this field. The source is the PHP field group in the source-controlled Platform Core module, not a database group or Local JSON file.

The rejected alternatives were a separate Primary or Secondary taxonomy, a separately stored Secondary list, or an ACF field that also writes direct taxonomy relationships. Those approaches would duplicate topic membership or create competing data stores.

## Domain API and editor behavior

`Voelgoed\PlatformCore\Taxonomy\ContentTopicRoles` provides the Primary, Secondary, Direct Topic, Effective Topic, classification-state, validation, mutation, hierarchy-warning, and entity-policy methods documented in `CONTENT_TOPIC_ROLE_ARCHITECTURE.md`.

The setter accepts only terms from `content_topic`. If the selected Primary is not directly assigned, it adds that relationship with `wp_set_object_terms()` before saving the ACF term ID. ACF validation checks the submitted value, and the update filter also checks the target object type when an update comes from PHP without a normal edit form.

The native taxonomy control remains the Direct Topic editor. The Primary ACF control is separate and does not load or save the full taxonomy list. The ACF update filter validates against its target post ID and only persists a term that is already directly assigned. A programmatic update with an unassigned term preserves the prior valid Primary. On a normal editor save, the later save handler may add the selected term through WordPress before saving Primary. If WordPress cannot establish the selected direct relationship, Platform Core clears the submitted Primary instead of leaving an invalid reference. Admin notices show migration, review, and hierarchy status. Posts have a soft editorial requirement for Primary; the implementation adds no save blocker. Courses, products, events, broadcasts, and competitions remain optional. Medewerkers do not receive a Primary selector.

The WP admin preview resolved to the site's front-end login screen, so the edit form was not inspected in a browser. Novamira confirmed the registered field schema, location rules, and PHP source. The service behavior was also tested on a temporary draft.

## Temporary editor fixture

Novamira created draft post `1216`, titled `Task 3 Topic Role QA Fixture 2026-10-14`. The service selected `aftrede` as Primary, retained direct `gesondheid`, derived `gesondheid` as Secondary, and returned Effective Topics `gesondheid`, `aftrede`, `nuwe-jy`, `geld`, and `werk-geld`. The state was `REVIEWED`, and `validate_primary_topic()` passed.

The migration record checksum matched before and after the fixture check. Novamira permanently deleted draft `1216`, and a final read confirmed it no longer exists. No fixture remains.

## Task 2 compatibility

The migration service still owns only the relationships recorded in `vgpc_content_topic_migration_local_v1`. Primary changes leave that option untouched. Automated rollback coverage verifies that a Task 2 rollback clears a Primary when it removes that Primary's recorded relationship, and that it retains a separate Task 3 relationship and its Primary.

No rollback was run against the live seven-object dataset. The automated controlled fixtures cover both cases.

## Existing-data impact

| Area | Changed |
|---|---|
| `content_topic` terms | No |
| `content_topic` relationships | No |
| `content_category` terms or relationships | No |
| Seven seed classifications | No |
| Task 2 migration record | No |
| Bricks templates or queries | No |
| URLs | No |
| Commercial mappings | No |

The source module and local Platform Core runtime copy contain the same `ContentTopicRoles.php`, SHA-256 `dd691967d8cb321af25933fb80a365af31a7fb6684074f38b4d6185b5502806c`.

## Regression results

| Check | Result |
|---|---|
| Focused content-topic suite | 50 passed, 0 failed. |
| Platform Core dashboard suite | 127 passed, 0 failed. |
| PHP lint for all PHP files in the source-controlled content-topic module | Passed, 8 files checked. |
| `git diff --check` | Passed. |
| Final read-only Novamira snapshot | Passed. Counts and seven seed assignments match Task 2. |
| Temporary draft QA | Passed, then draft 1216 was permanently deleted and confirmed absent. |

Task 3 does not start editorial reclassification, production migration, or Task 3B. The feature branch is intended for review and is not to be merged as part of this task.
