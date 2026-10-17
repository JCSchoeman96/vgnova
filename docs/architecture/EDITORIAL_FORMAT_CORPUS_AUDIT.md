# Editorial Format Corpus Audit

## Scope and result

This audit covers the current local rebuild's standard WordPress `post` records. It does not describe the full historical or future production corpus.

**This manifest covers the current local corpus only. It is not the production-corpus migration manifest.**

The corpus contains eight standard posts across all statuses. Seven are published and in scope for disposition; one is in trash and excluded. Of the published posts, five are documented Task 2 local proof objects, one is a confirmed QA fixture, and post 375 remains unresolved. There are currently zero verified genuine editorial posts in this small local corpus.

The Task 2 proof set also included product 523, which is outside this post-only audit. Repository records describe 514, 516, 518, 519, 521, 523, and 641 as the seven local proof objects. Post 641 is retained as `QA_FIXTURE` because its title and body independently identify its QA role. The other five in-scope Task 2 proof posts use the allowed `SYSTEM_TEST` disposition.

## Corpus counts by status

| Status | Count | Handling |
|---|---:|---|
| `publish` | 7 | Included in the manifest |
| `draft` | 0 | Included if present; none found |
| `pending` | 0 | Included if present; none found |
| `private` | 0 | Included if present; none found |
| `future` | 0 | Included if present; none found |
| `trash` | 1 | Reported, excluded from editorial classification |
| `other` | 0 | No other standard-post statuses found |
| **Total standard posts** | **8** | Includes trash |

The separate revision count is 260. `auto-draft` and `inherit` standard-post records are both 0. Revisions and trash are not production editorial content for this manifest.

Trash record 513, “Jou oggend-routine vir meer rustigheid” (`oggend-routine-rustigheid__trashed`), is a trashed duplicate and is excluded.

## Disposition and proposal totals

| Measure | Count |
|---|---:|
| `EDITORIAL` | 0 |
| `QA_FIXTURE` | 1 |
| `SYSTEM_TEST` | 5 |
| `ENTITY_REVIEW` | 0 |
| `NEEDS_REVIEW` | 1 |
| Proposed `article` | 0 |
| Proposed `video` | 0 |
| Proposed `audio` | 0 |
| Proposed `NEEDS_REVIEW` | 1 |
| `NOT_APPLICABLE` | 6 |
| Confidence `HIGH` | 6 |
| Confidence `MEDIUM` | 0 |
| Confidence `LOW` | 1 |
| `review_required = YES` | 1 |

Confidence counts describe the row's disposition or format assessment. The six `HIGH` rows establish five known local proof/test objects and one QA fixture; none is a format classification. `review_required` means editorial-format or content-identity review. Lifecycle decisions for test fixtures are separate.

## High-confidence format proposals

There are no high-confidence `article`, `video`, or `audio` proposals. The available posts do not establish a complete primary editorial experience suitable for a format assignment.

| Post ID | Title | Proposed format | Reason |
|---|---|---|---|
| — | None | — | No in-scope post has a sufficiently established editorial identity and primary experience for a high-confidence format proposal. |

## Unresolved record

| Post ID | Disposition / proposal | Reason |
|---:|---|---|
| 375 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Pseudo-Latin title and Lorem ipsum body suggest demo or placeholder content. No explicit QA marker or tracked provenance was found, so its purpose and editorial identity remain unresolved. |

Post 375 is the only post that needs editorial identity or format review. Its placeholder signals are strong, but the repository does not provide the explicit Task 2 proof provenance recorded for the other objects.

No post showed a positive sign of being a disguised event, course, product, person profile, Voelgoed Live episode, or podcast episode. No `ENTITY_REVIEW` disposition was needed.

## Known local proof and QA fixtures

| Post ID | Disposition / proposal | Confidence | Format review | Evidence and lifecycle note |
|---:|---|---|---|---|
| 514 | `SYSTEM_TEST` / `NOT_APPLICABLE` | HIGH | NO | Task 2 documentation includes this post in its controlled local proof set. Its short example copy is consistent with that role. Cleanup or retention is a separate decision. |
| 516 | `SYSTEM_TEST` / `NOT_APPLICABLE` | HIGH | NO | Task 2 documentation includes this post in its controlled local proof set. Its short example copy is consistent with that role. Cleanup or retention is a separate decision. |
| 518 | `SYSTEM_TEST` / `NOT_APPLICABLE` | HIGH | NO | Task 2 documentation includes this post in its controlled local proof set. Its short example copy is consistent with that role. Cleanup or retention is a separate decision. |
| 519 | `SYSTEM_TEST` / `NOT_APPLICABLE` | HIGH | NO | Task 2 documentation includes this post in its controlled local proof set. Task 3C's `content_topic` review is a separate dimension. Cleanup or retention is a separate decision. |
| 521 | `SYSTEM_TEST` / `NOT_APPLICABLE` | HIGH | NO | Task 2 documentation includes this post in its controlled local proof set. Its short example copy and display-guidance ACF value are consistent with that role. Cleanup or retention is a separate decision. |
| 641 | `QA_FIXTURE` / `NOT_APPLICABLE` | HIGH | NO | Title, excerpt, and opening paragraph explicitly identify a temporary featured-content QA article. Cleanup or retention is a separate decision. |

The repository provenance is recorded in [Task 2 Current State](POST_TASK_2_CURRENT_STATE.md), [Content Topic Seed Editorial Review](CONTENT_TOPIC_SEED_EDITORIAL_REVIEW.md), and [Task 3C Current State](POST_TASK_3C_CURRENT_STATE.md). In this audit, `SYSTEM_TEST` means a controlled local migration-proof object, not a production editorial item. Existing topic/category values are recorded only as evidence; no topic review or changes were made.

## Evidence summary

- Post 375 contains pseudo-Latin title/body text, ordinary text blocks, and one featured image. No audio or video embed was found.
- Posts 514, 516, 518, 519, and 521 are controlled Task 2 local proof objects. Their short example copy and featured images do not make them production editorial records.
- Post 521's `article_lede` ACF value is display guidance, not article content.
- Post 641's title, excerpt, and opening text identify it as QA content; it is excluded from format proposals.
- The reviewed published posts had no audio/video embeds, media shortcodes, or media-led content. Incidental featured images do not establish format.
- Legacy `content_category` and current `content_topic` values were used only as context. Topic and format remain separate dimensions.

## Method

The corpus was enumerated from the current local WordPress runtime before classifying records. Review included title, slug, status, content, excerpt, featured-image metadata, blocks, embeds, shortcodes, ACF values, and current taxonomy assignments where present. The primary editorial experience governed format decisions.

`article` requires evidence of a text-led editorial experience. `video` and `audio` require evidence that watching or listening is the primary experience. A media embed or featured image alone is insufficient. Entity identity takes precedence over delivery medium. No automatic `article` default was used.

The format manifest contains one row for each of the seven in-scope published standard posts. Trashed record 513 is reported above but excluded. The CSV files are generated with standard CSV quoting.

## Runtime boundaries

Before and after the audit, runtime `editorial_format` relationships were 0 and `editorial_collection` relationships were 0. `editorial_format` had exactly `article`, `video`, and `audio`; `editorial_collection` had 0 terms. `content_category` remained at 13 terms and 8 relationships across 7 objects. No WordPress data or configuration was changed.

## Enforcement and next scope

**Can `editorial_format` safely become globally required? NOT YET.** The local standard-post corpus contains five known Task 2 proof objects and one QA fixture, plus unresolved post 375. There are zero verified genuine editorial posts against which the required-format workflow has been migration-tested. A globally required field would also affect retained proof and QA records while they remain standard posts.

Recommended next step: resolve the provenance and disposition of post 375; separately decide whether to retain or remove proof objects 514, 516, 518, 519, 521, and QA fixture 641; keep `editorial_collection` empty; and do not assign formats until the local standard-post corpus contains verified genuine editorial records. No proof or QA object was deleted or changed in Task 4C.
