# Editorial Format Corpus Audit

## Scope and result

This audit covers the current local rebuild's standard WordPress `post` records. It does not describe the full historical or future production corpus.

**This manifest covers the current local corpus only. It is not the production-corpus migration manifest.**

The corpus contains eight standard posts across all statuses. Seven are published and in scope for editorial review; one is in trash and excluded. Six published posts need an editorial identity or content review before a format can be proposed. Post 641 is a confirmed QA fixture. No post received a confident `article`, `video`, or `audio` proposal.

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
| `SYSTEM_TEST` | 0 |
| `ENTITY_REVIEW` | 0 |
| `NEEDS_REVIEW` | 6 |
| Proposed `article` | 0 |
| Proposed `video` | 0 |
| Proposed `audio` | 0 |
| Proposed `NEEDS_REVIEW` | 6 |
| `NOT_APPLICABLE` | 1 |
| Confidence `HIGH` | 1 |
| Confidence `MEDIUM` | 0 |
| Confidence `LOW` | 6 |
| `review_required = YES` | 7 |

Confidence counts describe the row's disposition or format assessment. The one `HIGH` row is the confirmed QA fixture, not a format classification.

## High-confidence format proposals

There are no high-confidence `article`, `video`, or `audio` proposals. The available posts do not establish a complete primary editorial experience suitable for a format assignment.

| Post ID | Title | Proposed format | Reason |
|---|---|---|---|
| — | None | — | No in-scope post has a sufficiently established editorial identity and primary experience for a high-confidence format proposal. |

## Ambiguous and non-editorial records

| Post ID | Disposition / proposal | Reason |
|---:|---|---|
| 375 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Pseudo-Latin title and Lorem ipsum body suggest demo or placeholder content. No explicit QA marker or tracked provenance was found, so its purpose and editorial identity remain unresolved. |
| 514 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Published local proof object with one short self-care lead and explicit example-image copy; this does not establish whether it is a real editorial item or a migration fixture. |
| 516 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Published local proof object with one short sentence and explicit example-image copy; this does not establish whether it is a real editorial item or a migration fixture. |
| 518 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Published local proof object with one short sentence and explicit example-image copy; this does not establish whether it is a real editorial item or a migration fixture. |
| 519 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Published local proof object with one short budgeting sentence and explicit example-image copy; a format cannot be inferred from its topic or featured image. |
| 521 | `NEEDS_REVIEW` / `NEEDS_REVIEW`, LOW | Published local proof object with a short lede; its ACF lede includes display instructions, not substantive article text. |
| 641 | `QA_FIXTURE` / `NOT_APPLICABLE`, HIGH | Title, excerpt, and opening paragraph explicitly call this a temporary QA article for featured-content routing. It remains untouched; any cleanup is a separate decision. |

No post showed a positive sign of being a disguised event, course, product, person profile, Voelgoed Live episode, or podcast episode. No `ENTITY_REVIEW` disposition was needed.

The six `NEEDS_REVIEW` records are not being silently treated as articles. Their current topics and legacy categories were recorded as evidence only and were not changed or re-evaluated.

## Evidence summary

- Post 375 contains pseudo-Latin title/body text, ordinary text blocks, and one featured image. No audio or video embed was found.
- Posts 514, 516, 518, 519, and 521 contain short proof copy with explicit example-image wording, plus featured images. Their bodies do not establish complete editorial experiences.
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

**Can `editorial_format` safely become globally required? NOT YET.** Six published records have unresolved editorial identity or insufficient content for a confident format, and the QA fixture still needs a separately recorded cleanup decision. No high-confidence format proposal is ready for a write task.

Recommended next step: conduct editorial review of posts 375, 514, 516, 518, 519, and 521; record a separate cleanup decision for 641; then prepare a write task limited to records confirmed as genuine editorial content. Do not create collection terms until recurring local or verified historical installments establish membership.
