# Local residue and integrity audit

**Task:** 4H, read-only provenance and integrity review
**Baseline:** 860cbefad36c9eaf8f47cbe9a72ab0e60b6a834e
**Runtime:** Local WordPress at http://localhost:10039/, inspected through Novamira MCP profile VGNova
**Mutation result:** 0 persistent WordPress writes

## Findings

Task 4H found one live published standard post, ID 375, and six detached relationships in WordPress core taxonomy category. The post's filler content supports a demo/import-placeholder classification, but no source establishes its creation provenance. Its lifecycle remains NEEDS_PROVENANCE, so Task 4H does not authorize its removal.

The six relationships point to object IDs whose post rows are absent. The global relationship scan found exactly these six orphan rows and no others. Their provenance is known from source-controlled task history or the local SQL backup. Each row is independently classified ORPHAN_CONFIRMED and is READY_TO_CLEAN, confidence HIGH. The audit recommends that a separately reviewed Task 4I may remove these six rows only.

## Core category state

The WordPress core taxonomy category exists. Its current registered object-type list is empty, and is_object_in_taxonomy('post', 'category') is false. It is hierarchical, public and publicly queryable. Its admin UI and REST API flags are enabled. It uses query var category_name and rewrite slug category with hierarchical rewrites enabled.

The term is voelgoed: term ID 1, term-taxonomy ID 1, slug voelgoed, name Voelgoed. Its stored count is 0. There are seven exact relationship rows to this term: one live relationship for post 375 and six rows for absent post IDs 1, 513, 514, 516, 518 and 519. The stored count is reported as observed; Task 4H did not recalculate it.

The six orphan records and recommendations are in [CORE_CATEGORY_ORPHAN_MANIFEST.csv](CORE_CATEGORY_ORPHAN_MANIFEST.csv). Their provenance and integrity states are separate fields. ID 1 is KNOWN_LEGACY_OBJECT; ID 513 is KNOWN_DUPLICATE_FIXTURE; IDs 514, 516, 518 and 519 are KNOWN_TASK_FIXTURE. All six are ORPHAN_CONFIRMED.

## Why core category is detached from posts

The runtime FluentSnippet 4-taxonomy-posts-hide.php at /home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/fluent-snippet-storage/4-taxonomy-posts-hide.php, labelled "[Taxonomy] Posts — Hide native Categories," is published and runs on all pages at init priority 20. Its callback calls:

    unregister_taxonomy_for_object_type('category', 'post');

The snippet also hides the native category admin menu and redirects the term editor to content_category. Its description says Voelgoed uses content_category as the post-category UI. It was created and last updated on May 28, 2026. This runtime snippet is the active owner of the unregistration mechanism.

The unregistration is intentional in the local runtime configuration. The published FluentSnippet names the action in its title and description. It is runtime-only and has no source-controlled implementation in this repository.

The snippet is present under wp-content/fluent-snippet-storage/ in the local WordPress runtime and is not tracked in the repository. Repository, MU plugin, theme, Platform Core, ACF taxonomy configuration, and Bricks integration searches found no source-controlled competing unregistration mechanism. ACF does not own the core category taxonomy. Platform Core registers only content_topic. The evidence therefore classifies this mechanism as **runtime-only**, with no source-controlled owner found.

The replacement in the ordinary post editor is content_category. That legacy Voelgoed taxonomy remains active for migration and consumer reasons. Canonical content_topic is a separate semantic taxonomy and is not the taxonomy in these orphan rows. The six core rows do not support any conclusion about removing content_category.

## Post 375 summary

Post 375 is a published standard post with slug integer-maximus-sapien-in-bibendum, title "Integer maximus sapien in bibendum.", author user 1 (JC_Admin), created May 20, 2026 at 14:08:15 and modified at 15:15:58. Its content is 4,347 characters of pseudo-Latin filler and has no revisions, autosaves or old slugs. It has one core category relationship to voelgoed and no content_category, content_topic, editorial dimension, HappyFiles, tag or Primary Topic relationship.

It has no Bricks, ACF, import, SEO, migration or template metadata. The bounded neighboring-object review found a Bricks "Posts" template created about eleven minutes before the post and modified near the post's modification time. This timing is circumstantial; no source links the post to that template. Repository/runtime/import and local SQL searches returned NO_SOURCE_FOUND for an originating content package or process.

Attachment 154 is shared by post 375 and published event 166. It is not a removal candidate with post 375. The public local route returns HTTP 200, is self-canonical, and has no noindex directive. No current inbound relationship, embedded permalink, template preview or other live object reference was found.

Classification: DEMO_OR_IMPORT_PLACEHOLDER, confidence MEDIUM. Lifecycle: NEEDS_PROVENANCE, confidence HIGH that the removal gate remains open. The open gate is specific origin and purpose; the lack of source prevents a READY_TO_REMOVE recommendation. Full evidence and the modeled future sequence are in [POST_375_PROVENANCE_AUDIT.md](POST_375_PROVENANCE_AUDIT.md).

## Orphan integrity and Task 4I scope

The global read-only scan used wp_term_relationships.object_id LEFT JOIN wp_posts.ID WHERE wp_posts.ID IS NULL, grouped by taxonomy and term. It returned six rows, all category term voelgoed, and no additional out-of-scope orphans.

| Scenario | Expected state |
|---|---|
| A. Clean only the six approved orphan rows | Core category has one relationship row, one live object (375), zero orphan rows; term voelgoed remains with stored count 0. |
| B. Later remove 375 after an independent provenance decision | Core category has zero relationship rows, zero live objects and zero orphan rows; term remains with stored count 0. Published standard posts become 0. Attachment 154 remains for event 166. |

In both scenarios, the currently locked Voelgoed taxonomies and editorial dimensions stay unchanged: content_category has 13 terms and 0 relationships/objects; content_topic has 55 terms, 7 roots and 0 relationships/objects, with 0 Primary Topic relationships; hf_cat_post has 15 terms and 0 relationships/objects; editorial_format has 3 terms and 0 relationships; editorial_collection has 0 terms and 0 relationships. These are distinct taxonomies and are not part of the orphan cleanup.

If Task 4I approves orphan cleanup, use a local-runtime guard, preflight each absent object and exact relationship, call wp_delete_object_term_relationships($object_id, 'category'), then verify the relationship is gone while the taxonomy and term remain. Do not write SQL.

If a later review closes post 375's provenance gate, first run the local WP-CLI command wp post delete 375 --force and verify the post is absent. Then remove only the confirmed detached core-category row through wp_delete_object_term_relationships(375, 'category'). This order runs normal deletion hooks before addressing the known unregistered-taxonomy residue. Preserve shared attachment 154.

## Recommendation

Task 4I may clean the six manifest rows after its own preflight. It should not remove post 375 based on Task 4H evidence. Core category remains distinct from content_category and content_topic. No WordPress data was changed during this audit.
