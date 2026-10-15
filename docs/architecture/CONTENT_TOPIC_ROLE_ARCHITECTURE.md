# Content topic role architecture

Status: implemented in the Platform Core source module. This document defines the contract for Primary and Secondary Topic roles over `content_topic`.

## 1. Direct topic membership

WordPress taxonomy relationships in `content_topic` are the only source of topic membership. A Direct Topic is a term directly assigned to an object. Editors should select the most specific useful term and do not need to assign its ancestors.

The role service reads these relationships with `wp_get_object_terms()`. It does not infer membership from `content_category`, titles, or other metadata.

## 2. Primary Topic storage

An object's Primary Topic is stored as one term ID in `_vg_primary_content_topic`. The term must exist in `content_topic` and must also be directly assigned to the object.

Platform Core defines the ACF field group `group_vg_content_topic_roles` in PHP. Its field key is `field_vg_primary_content_topic`, its field name is `_vg_primary_content_topic`, and its label is `Content Topic: Primary Topic`.

The ACF taxonomy field is a single select with `return_format=id`, `multiple=0`, `save_terms=0`, and `load_terms=0`. The field reads only `content_topic`. ACF does not use this role field to change the native taxonomy relationships.

## 3. Secondary Topics

Secondary Topics are derived as:

```text
Direct Topics minus the valid Primary Topic
```

The service does not store a Secondary Topic list. If an object has no Primary Topic, the derived set contains its direct terms. The classification state identifies that object's work as unreviewed or incomplete.

The service returns an error for Secondary Topics when the Primary reference is invalid or when the object type does not support Primary/Secondary roles.

## 4. Effective Topics

Effective Topics are every Direct Topic plus each ancestor needed for hierarchy matching. The service returns each term once and never adds inferred ancestors to the object's taxonomy relationships.

For example, if an object directly has `Aftrede`, its Effective Topics include `Aftrede`, `Geld`, and `Werk + Geld`.

## 5. Classification states

The service derives state from current direct relationships, the Primary reference, entity policy, and the Task 2 migration record.

| State | Meaning |
|---|---|
| `UNCLASSIFIED` | No Direct Topics and no Primary reference. |
| `MIGRATION_SEED` | At least one current Direct Topic is listed in `vgpc_content_topic_migration_local_v1`, and there is no valid Primary. |
| `NEEDS_REVIEW` | Direct Topics exist without a valid Primary, and the object is not currently represented by a migration-owned relationship. |
| `REVIEWED` | A valid Primary exists and is also a Direct Topic. |
| `INVALID` | A Primary reference exists but has an invalid value, points outside `content_topic`, or is not directly assigned. |
| `NOT_APPLICABLE` | The object type does not use Primary/Secondary roles. |

`NOT_APPLICABLE` is used for medarbeiders and any future person model. It does not imply that the object's expertise topic relationships are reviewed.

## 6. Task 2 provenance and lifecycle

The role service reads `vgpc_content_topic_migration_local_v1` to identify migration seeds. It does not edit or delete that option and does not claim ownership of its relationships. The option remains the historical answer to which seven canonical relationships Task 2 created.

The separate `vgpc_content_topic_migration_local_v1_lifecycle` option records whether the local proof migration is active or retired. Task 3C retired it after checking its migration ID, reviewed fingerprint, and all seven original relationships. Retirement does not change the provenance record or classification state. An object may remain `MIGRATION_SEED` while it still has an original recorded Direct Topic and no Primary.

Once retired, Task 2 apply and rollback both refuse with `vgpc_topic_migration_retired`. Editorial replacement has superseded rollback-to-seed semantics. The original rollback behavior remains described by its Task 2 report and applies only to an active, unretired proof.

## 7. Hierarchy rules

Editors should assign the most specific useful topic. Directly assigning a parent and its child is reported as a warning through `get_hierarchy_warnings()` and the edit-screen notice. The service does not delete or reinterpret either assignment.

## 8. Entity policy

| Object type | Primary supported | Editorial policy | Save enforcement |
|---|---:|---|---|
| `post` | Yes | Required for fully reviewed normal editorial content. | Soft during migration. Existing unreviewed posts remain editable. |
| `mto-course` | Yes | Optional. | No requirement. |
| `product` | Yes | Optional. | No requirement. |
| `tc_events` | Yes | Optional. | No requirement. |
| `uitsending` | Yes | Optional. | No requirement. |
| `kompetisie` | Yes | Optional. | No requirement. |
| `medewerker` | No | `content_topic` terms describe expertise or subject association. | Primary writes are rejected. |
| Future `person` | No | If introduced, `content_topic` terms describe expertise or subject association. | Primary writes are rejected. |

These policies match the current `content_topic` attachment list. `person` is documented as a future policy only and is not currently attached to the taxonomy.

## 9. Editor workflow

Editors use the native `content_topic` taxonomy control to assign Direct Topics. The ACF field `Content Topic: Primary Topic` selects one term from the same taxonomy. Its instructions explain that other direct terms are treated as Secondary Topics.

For a supported object, Platform Core shows the derived classification state on the edit screen. Migration seeds show `Topic classification awaiting editorial review`. No save blocker prevents an existing post from being edited without a Primary.

The PHP-registered ACF field group is source-controlled with the Platform Core role service. It is not an editable database group or an ACF Local JSON file.

## 10. Save validation

The field accepts an empty value so an editor can clear Primary. It rejects malformed values and terms outside `content_topic`. The update filter validates against the target post ID and only persists a term that is already directly assigned. A programmatic update with an unassigned term preserves the prior valid Primary. For a normal editor save, the later save handler can add the selected term through WordPress and then store it.

On ACF save, Platform Core validates the submitted field value. If the chosen term is not yet a Direct Topic, it adds that relationship with `wp_set_object_terms()`. If WordPress cannot establish that relationship, Platform Core clears the just-submitted Primary reference. A single successful editor save is sufficient.

The service does not require Primary at save time. Posts follow a soft editorial requirement during migration; other supported types remain optional.

## 11. Removed or deleted Primary terms

When WordPress removes the Primary term relationship, Platform Core clears the Primary reference. This includes direct editor changes. It does not promote another term.

When a term is deleted from `content_topic`, Platform Core clears references to it. No replacement is selected. Read and validation methods fail closed if they encounter a stale reference before cleanup runs.

## 12. Domain API

`Voelgoed\PlatformCore\Taxonomy\ContentTopicRoles` provides:

| Method | Contract |
|---|---|
| `get_primary_topic($object_id)` | Valid Primary term, `null` when none exists, or `WP_Error` for an invalid reference. |
| `get_secondary_topics($object_id)` | Direct terms minus a valid Primary, or `WP_Error` when the role is invalid or unsupported. |
| `get_direct_topics($object_id)` | Exactly the directly assigned `content_topic` terms. |
| `get_effective_topics($object_id)` | Direct terms plus ancestors, each once. |
| `get_classification_state($object_id)` | Derived state listed above. |
| `set_primary_topic($object_id, $term_id)` | Validates the term and entity, adds direct membership when needed, then stores the Primary term ID. |
| `clear_primary_topic($object_id)` | Clears Primary metadata without changing topic membership. |
| `validate_primary_topic($object_id)` | Returns `true` for no Primary or a valid reference, otherwise `WP_Error`. |
| `get_hierarchy_warnings($object_id)` | Reports redundant parent/child assignments without changing them. |
| `get_entity_policy()` | Returns the current role policy by post type. |
| `get_editor_status($object_id)` | Returns state, editor message, and hierarchy warnings. |

Downstream code should call this API instead of reading `_vg_primary_content_topic` directly.

## 13. Downstream query contract

Consumers can ask Platform Core for:

- the Primary Topic;
- direct Secondary Topics;
- all Direct Topics;
- all Effective Topics, including ancestors;
- the derived classification state.

This task does not connect the service to Bricks templates. Future Bricks integrations should call the service contract rather than implement their own interpretation of the Primary metadata.

## 14. Migration compatibility and non-goals

Task 3C applies the two architecture-approved HIGH-confidence classifications: object 519 uses `begroting-geldbestuur`, and product 523 uses `oefening-fiksheid`. Each is the only Direct Topic and is also Primary; its canonical ancestors are Effective Topics. The five remaining Task 2 seeds are unchanged. `content_category`, templates, and URLs are unchanged. See the Task 3C current-state snapshot for the exact live state and checks.
