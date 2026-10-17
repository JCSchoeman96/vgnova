# Post Task 4H current state

**Task:** 4H, read-only provenance and integrity audit
**Baseline:** origin/main at 860cbefad36c9eaf8f47cbe9a72ab0e60b6a834e, after merged PR #14
**Branch:** docs/post-375-provenance-orphan-audit
**Runtime:** VGNova Novamira MCP profile, local site http://localhost:10039/
**Persistent WordPress writes:** 0

## Current runtime state

| Check | Final observation |
|---|---|
| Standard published posts | 1, ID 375 |
| Standard trashed posts | 0 |
| Post 375 | Exists; post type post; status publish; slug integer-maximus-sapien-in-bibendum |
| Post 375 author | User ID 1, JC_Admin |
| Post 375 created | 2026-05-20 14:08:15 local and GMT |
| Post 375 modified | 2026-05-20 15:15:58 local and GMT |
| Post 375 revisions / autosaves | 0 / 0 |
| Post 375 core category | One live relationship to category term voelgoed, term ID 1 / term-taxonomy ID 1 |
| Post 375 content_category | None |
| Post 375 content_topic | None |
| Post 375 Primary Topic | None; zero Primary Topic meta rows globally and on 375 |
| Featured attachment | 154 |
| Inbound object dependencies | None found |
| Public local route | HTTP 200, self-canonical, no noindex directive; returned in WordPress search |

## Post 375 conclusion

**Provenance classification:** DEMO_OR_IMPORT_PLACEHOLDER
**Classification confidence:** MEDIUM
**Lifecycle recommendation:** NEEDS_PROVENANCE
**Confidence that removal remains gated:** HIGH

The filler content and title support a placeholder classification, but repository, runtime and local SQL source searches did not find the originating package or process. Nearby template work and debug-log route requests are circumstantial. The remaining gate is the specific origin and purpose of post 375. Task 4H does not authorize removal.

Attachment 154 exists, and its file exists at wp-content/uploads/2026/04/lograstudio-yoga-3053488_1920.webp. It has parent 0 and is featured on both post 375 and published event 166. Its classification is shared. If a later reviewed task removes 375, keep attachment 154 for event 166.

## Core category registration and term

The core category taxonomy exists, but its registered object-type list is empty and it is not registered to post. It is hierarchical, public and publicly queryable. Its admin UI and REST API flags are enabled. It uses query var category_name and rewrite slug category.

The runtime-only owner is FluentSnippet 4-taxonomy-posts-hide.php in wp-content/fluent-snippet-storage/. Its init priority 20 callback calls unregister_taxonomy_for_object_type('category', 'post') on all pages. The unregistration is intentional in the local runtime configuration; the published snippet title and description state that it hides core Categories and uses content_category for posts. No source-controlled unregister mechanism was found. This does not change the separate lifecycle of content_category or content_topic.

The category term voelgoed has term ID 1, term-taxonomy ID 1, slug voelgoed, name Voelgoed and stored count 0. It has seven relationship rows: one live row for post 375 and six orphan rows. The global scan found six orphan relationships, all taxonomy category / term voelgoed, and no additional rows.

## Six orphan classifications

| Object ID | Provenance | Integrity | Cleanup recommendation | Confidence |
|---:|---|---|---|---|
| 1 | KNOWN_LEGACY_OBJECT | ORPHAN_CONFIRMED | READY_TO_CLEAN | HIGH |
| 513 | KNOWN_DUPLICATE_FIXTURE | ORPHAN_CONFIRMED | READY_TO_CLEAN | HIGH |
| 514 | KNOWN_TASK_FIXTURE | ORPHAN_CONFIRMED | READY_TO_CLEAN | HIGH |
| 516 | KNOWN_TASK_FIXTURE | ORPHAN_CONFIRMED | READY_TO_CLEAN | HIGH |
| 518 | KNOWN_TASK_FIXTURE | ORPHAN_CONFIRMED | READY_TO_CLEAN | HIGH |
| 519 | KNOWN_TASK_FIXTURE | ORPHAN_CONFIRMED | READY_TO_CLEAN | HIGH |

The June 9 local SQL backup identifies ID 1 as the trashed default WordPress Hello world! sample: post type post, slug hello-world__trashed, author 1, created April 9, 2026 and modified May 29, 2026. Its post row is absent now. Task 4E history confirms 513 as a duplicate of removed Task 2 post 514, and 514, 516, 518 and 519 as removed Task 2 proof posts. Full row evidence is in CORE_CATEGORY_ORPHAN_MANIFEST.csv.

## Taxonomy counts and cleanup model

| Taxonomy | Terms | Relationships | Objects | Notes |
|---|---:|---:|---:|---|
| Core category | 1 | 7 | 7 stored object IDs | 1 live object; 6 absent objects |
| content_category | 13 | 0 | 0 | Retained legacy taxonomy |
| content_topic | 55 | 0 | 0 | 7 roots; canonical Platform Core taxonomy |
| hf_cat_post | 15 | 0 | 0 | HappyFiles |
| editorial_format | 3 | 0 | 0 | |
| editorial_collection | 0 | 0 | 0 | |

Scenario A, cleaning only the six approved orphan rows, leaves one core category relationship row for live post 375, one live object and zero orphans. The term remains with its stored count 0.

Scenario B, only if a later independent provenance review approves removal of 375, leaves zero core category rows, zero live objects and zero orphans; published standard posts become 0. The term remains and attachment 154 remains for event 166. content_category, content_topic, Primary Topic, HappyFiles and editorial-dimension states stay unchanged.

Task 4I may clean the six orphan rows after its own exact-row and local-runtime preflight, using wp_delete_object_term_relationships($object_id, 'category'). Post 375 removal is not authorized by Task 4H. If later authorized, first run wp post delete 375 --force, verify absence, then clean its now-detached core-category row through the taxonomy API.

## End-state integrity comparison

Read-only before and final snapshots show the same post 375 identity, status, slug, modified timestamp, attachment and category relationship. The four post-meta rows also match. Their ordered PHP-serialized SHA-256 is a70b9abc5fb7652484d4c90ce3db31be05cd60bdf3d7516adef7a87f207cb06e.

| State | Initial observation | Final observation |
|---|---|---|
| Post 375 | publish; same slug and modified timestamp; one category relationship | Same |
| Post 375 meta | 4 rows; SHA-256 a70b9abc5fb7652484d4c90ce3db31be05cd60bdf3d7516adef7a87f207cb06e | Same |
| Category rows for voelgoed | 7: object IDs 1, 375, 513, 514, 516, 518, 519 | Same |
| content_category | 13 terms, 0 relationships, 0 objects | Same |
| content_topic | 55 terms, 7 roots, 0 relationships, 0 objects | Same |
| hf_cat_post | 15 terms, 0 relationships, 0 objects | Same |
| editorial_format | 3 terms, 0 relationships | Same |
| editorial_collection | 0 terms, 0 relationships | Same |
| Primary Topic relationships | 0 | 0 |

Featured Content Manager values match their initial read: featured_nuwe_jy=542, featured_geloof=534, featured_self=531; featured_werk_geld, featured_ouerskap, featured_huwelik, featured_mensstories and featured_leefstyl are unset. Their PHP-serialized value hashes remain unchanged:

- featured_nuwe_jy: a20684f0f3874646fb5a8b411e41af8479fe6b6c4b55cb2b611f07adb349c030
- featured_geloof: 5d900f69c2ee21fd181ef368b64e380b3f34be5a1b324fe0fad37e37dcf4b7c9
- featured_self: a39a811431bdd4c2124f815494b7d43d43628f604793fee0a936297b0e768075
- Five unset values: 28859e345f55ce45426724100617830af221bdf3e01f4333ee34ba58d2df4429 each

The raw migration record SHA-256 remains 605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95. The lifecycle marker SHA-256 remains 915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3. The record retains source fingerprint 80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847 and remains retired.

The final stat of the local Automatic.css debug log showed mtime 2026-09-03 19:37:53 +0200, which predates this audit. No persistent WordPress writes occurred.

## Runtime impact

- WordPress writes: 0
- Posts changed: 0
- Taxonomy relationships changed: 0
- Terms changed: 0
- Media changed: 0
- Featured Content Manager changed: 0
- Migration provenance changed: 0

Task 4I has not started.
