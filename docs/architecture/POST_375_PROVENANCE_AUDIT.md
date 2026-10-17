# Post 375 provenance audit

**Audit:** Task 4H, read-only
**Baseline:** 860cbefad36c9eaf8f47cbe9a72ab0e60b6a834e (origin/main after PR #14)
**Runtime:** Local WordPress at http://localhost:10039/, inspected through the VGNova Novamira MCP profile
**Result:** DEMO_OR_IMPORT_PLACEHOLDER / NEEDS_PROVENANCE / MEDIUM classification confidence; HIGH confidence that the removal gate remains open

## Identity and current state

| Property | Current value |
|---|---|
| ID | 375 |
| Type / status | post / publish |
| Title | Integer maximus sapien in bibendum. |
| Slug | integer-maximus-sapien-in-bibendum |
| Author | User ID 1, JC_Admin |
| Created / GMT | 2026-05-20 14:08:15 / 2026-05-20 14:08:15 |
| Modified / GMT | 2026-05-20 15:15:58 / 2026-05-20 15:15:58 |
| Parent / menu order | 0 / 0 |
| GUID | http://localhost:10039/?p=375 |
| Comment / ping status | open / open |
| Comment count | 0 |
| Page template | None |
| Revisions / autosaves | 0 / 0 |
| Old slugs | None |

The author account and the absence of revision history do not establish who created the post or why. The revision result is recorded as history evidence, not as deletion proof.

## Content characteristics

The post has 4,347 content characters and 458 stripped-text words. Its 484-character, 72-word excerpt follows the same pseudo-Latin placeholder style. The content uses filler text with repeated title-like headings and generic paragraphs; it does not read as finished Voelgoed editorial copy. The structure has 37 top-level Gutenberg parse nodes: 6 core heading/block nodes, 13 core paragraph nodes and 18 freeform nodes. It contains six heading tags (one H2, three H3 and two H4) and 13 paragraph tags, including one empty paragraph. No long passage is reproduced here.

This supports a placeholder classification. It does not identify a specific import, starter template, fixture generator or authoring event.

## Taxonomy relationships

All relationship rows for object 375 were enumerated. There is one relationship:

| Taxonomy | Term | Result |
|---|---|---|
| WordPress core category | voelgoed (term ID 1, term-taxonomy ID 1) | Present; term_order=0 |

There are no post_tag, content_category, content_topic, editorial_format, editorial_collection, hf_cat_post, or other taxonomy relationships. There is no Primary Topic value. In particular, the relationship above belongs to core category; it is not a content_category relationship.

The six other rows for core category term voelgoed point to absent post IDs 1, 513, 514, 516, 518 and 519. The global orphan scan found no additional rows.

## Meta and ACF inventory

The post has four post-meta rows:

| Key | Finding |
|---|---|
| _thumbnail_id | 154 |
| _edit_last | 1 |
| _edit_lock | Present; value omitted because it is an editor lock token, not provenance |
| _masteriyo_has_content | 0 |

Novamira ACF read returned no ACF values for 375. No Bricks content or settings, Bricks template assignment, SEO metadata, import marker, migration marker, relationship/post-object field, old-slug marker or Primary Topic metadata was found. There is no template assignment. The _masteriyo_has_content=0 flag does not associate the post with a course.

The ordered four-row post-meta snapshot has SHA-256 a70b9abc5fb7652484d4c90ce3db31be05cd60bdf3d7516adef7a87f207cb06e when serialized in PHP. The initial and final audit reads returned the same keys and values.

## Chronology and nearby objects

Post 375 was created and last modified on May 20, 2026. It has no revisions, autosaves or old-slug history. The revision count alone is not used to infer that the post is disposable.

The bounded ID review covered IDs 350 to 400. It returned 11 non-revision, non-attachment objects, all authored by user ID 1, JC_Admin:

| ID | Type | Status | Title | Created | Modified |
|---:|---|---|---|---|---|
| 366 | bricks_template | trash | Mentor (Copy) | 2026-05-15 17:45:11 | 2026-05-15 17:45:11 |
| 369 | bricks_template | publish | Category Archive | 2026-05-15 17:46:21 | 2026-05-15 18:11:08 |
| 372 | bricks_template | publish | Posts | 2026-05-20 13:57:38 | 2026-05-20 15:14:07 |
| 375 | post | publish | Integer maximus sapien in bibendum. | 2026-05-20 14:08:15 | 2026-05-20 15:15:58 |
| 393 | course | publish | Aenean non mi vel nisi lacinia maximus. | 2026-05-24 09:04:38 | 2026-05-24 09:04:38 |
| 394 | wp_global_styles | publish | Custom Styles | 2026-05-24 08:45:35 | 2026-05-24 08:45:35 |
| 396 | section | publish | Phasellus ac eros. | 2026-05-24 08:46:18 | 2026-05-24 09:05:45 |
| 397 | lesson | publish | Cras ac nunc. | 2026-05-24 08:46:18 | 2026-05-24 09:06:27 |
| 398 | lesson | publish | New Lesson | 2026-05-24 08:46:18 | 2026-05-24 09:34:04 |
| 399 | lesson | publish | New Lesson | 2026-05-24 08:46:18 | 2026-05-24 08:46:18 |
| 400 | section | publish | New Section | 2026-05-24 08:46:18 | 2026-05-24 08:46:18 |

Template 372 was created 10 minutes 37 seconds before post 375 and modified 1 minute 51 seconds before post 375's last modification. These times overlap, but they do not prove that template 372 generated, imported or used post 375. Post 375 has no Bricks metadata. Historical Task 4F evidence says template 372 previously previewed post 521; that preview reference was cleared and is not a current dependency on 375.

## Source and import search

Searches covered the repository, local WordPress wp-content, plugin and theme files, MU plugins, FluentSnippet storage, Bricks/template and Frames-related files, ACF exports, fixture/import files, SQL snapshots and backups. Exact title, slug and short distinctive content fragments were searched.

The repository matches are historical audit documentation only. Runtime wp-content matches contain no source package, import file, fixture generator, template payload or plugin/snippet source for 375. The local SQL files local.sql and local-backup-addon-database-dump.sql contain the stored post row, which documents runtime state rather than its origin. The file wp-content/uploads/automatic-css/debug.log contains older requests to the route on May 27 at 11:51, 11:55 and 12:02, including local vg=qa2, vg=qa4, vg=qa5 and vg=qa6 values. Those entries show route access in a local debug context but do not establish the post's creation provenance.

**Source search result: NO_SOURCE_FOUND.**

Task 2's recorded migration objects do not include post 375. Task 4D and earlier snapshots describe it as an unresolved Lorem-style placeholder, but none ties it to a source import or a completed QA task.

## Attachment 154

Attachment 154 exists and its local file exists:

- File: wp-content/uploads/2026/04/lograstudio-yoga-3053488_1920.webp
- MIME: image/webp
- Dimensions: 1920 × 1080
- File size: 91,556 bytes
- Attachment author: user ID 1
- Attachment date: 2026-04-21 17:46:45
- Parent: 0
- Title/slug: lograstudio-yoga-3053488_1920
- Caption and description: empty

Its featured-image usage is shared by post 375 and published Tickera event 166, "Sport, gholfdae …". No other object use was found. Its classification is **shared**, not exclusive to 375. If post 375 is later removed, attachment 154 must remain available to event 166. This audit does not authorize media removal.

## Inbound references and consumers

The runtime search covered ACF relationship/post-object values, Bricks settings/content and preview metadata, Featured Content Manager values, post/page content links, menus, options, FluentSnippets, post meta and repository hard-codes. No current inbound relationship, Bricks preview, FCM value, menu link, embedded permalink, snippet reference or hard-coded source consumer pointing to post 375 was found.

The local route is currently public:

- Route: /content/integer-maximus-sapien-in-bibendum/
- HTTP: 200
- Title: Integer maximus sapien in bibendum. – Voelgoed
- Canonical: self-referencing route
- Search visibility: WordPress blog_public=1; WordPress search returns the post
- Robots: no noindex directive was found; the page advertises max-image-preview:large

This is local exposure evidence only. It does not establish external traffic, editorial intent or an inbound dependency.

## Provenance and lifecycle decisions

**Provenance classification:** DEMO_OR_IMPORT_PLACEHOLDER
**Classification confidence:** MEDIUM
**Lifecycle recommendation:** NEEDS_PROVENANCE
**Confidence that removal must remain gated:** HIGH

The placeholder-like title and filler content strongly indicate non-editorial/demo material. The nearby Bricks work and local QA-style route requests are supporting context only. No source or creation record connects the post to a specific demo/import batch, fixture task or system test. The evidence therefore does not meet the Task 4H gate that provenance be sufficiently understood before recommending READY_TO_REMOVE.

There is no evidence that historical Tasks 2 through 4G reports require the live object to remain. There is also no live inbound dependency. Those facts do not close the unresolved origin and purpose gate. Task 4H does not authorize deletion, trashing, unpublishing, reclassification or editing.

## Future removal sequence if a later review closes the provenance gate

Because core category is not registered to post, deleting 375 through normal WordPress deletion can leave its category relationship detached. The safer planned sequence is:

1. Reconfirm the exact local runtime guard, post identity, status, relationship and attachment state.
2. Delete post 375 with the local WP-CLI command wp post delete 375 --force, which invokes normal WordPress deletion hooks.
3. Verify the post row is absent.
4. Remove only the now-confirmed object-375 core-category relationship with wp_delete_object_term_relationships(375, 'category').
5. Verify the exact relationship is gone, the category taxonomy and voelgoed term remain, and term counts and other post relationships are as expected.
6. Keep attachment 154 because published event 166 still uses it.

Deleting first allows WordPress to run normal post deletion hooks and avoids temporarily removing the category from a still-published public post. The follow-up API call explicitly cleans the row that normal deletion may leave because the taxonomy is unregistered for posts. Do not use direct SQL for the relationship cleanup unless a later reviewed task proves the WordPress API cannot perform it.

## Open question

The unresolved gate is the specific origin and purpose of post 375: who or what created it, and whether it belongs to a demo/import process whose provenance or preservation requirements are not in the current repository/runtime evidence. No source was found. A separately reviewed Task 4I decision must keep it in place unless that gate is closed.
