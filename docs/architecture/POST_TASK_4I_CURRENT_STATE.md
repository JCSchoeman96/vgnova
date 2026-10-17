# Post Task 4I current state

- Task: 4I, clean confirmed core category orphan relationships
- Date: 2026-10-17
- Git baseline: Fresh origin/main at 72b0b8b36f8e5457ee8fc172117b5049388307e0
- Branch / worktree: chore/clean-core-category-orphans / .worktrees/chore-clean-core-category-orphans
- Runtime: Novamira profile vgnova; local site http://localhost:10039/; WordPress 7.1.3
- Runtime writes: Six approved category relationship removals through the WordPress taxonomy API

## Core category cleanup

Task 4H approved cleanup of only object IDs 1, 513, 514, 516, 518, and 519. All six had no wp_posts row and one exact relationship to category=voelgoed, term ID 1, term-taxonomy ID 1, term order 0.

| Check | Before | After |
|---|---:|---:|
| Global orphan term relationships | 6 | 0 |
| Core category rows for voelgoed | 7 | 1 |
| Live core category rows | 1 | 1 |
| Orphan core category rows | 6 | 0 |

The remaining live row is 375 to category=voelgoed. The taxonomy remains registered with no object types and is not registered to post. The voelgoed term remains at term ID 1 / term-taxonomy ID 1 with stored count 0.

The runtime owner remains wp-content/fluent-snippet-storage/4-taxonomy-posts-hide.php. Its SHA-256 is 167fa19506c408fe37f489688c21e5b8769202d9716b5196d634d8762a8298a5.

## Post 375

| Property | Value |
|---|---|
| Type / status | post / publish |
| Slug | integer-maximus-sapien-in-bibendum |
| Modified | 2026-05-20 15:15:58 |
| Core category | Live category=voelgoed relationship retained |
| content_category | None |
| content_topic | None |
| Primary Topic | None |
| Featured attachment | 154 |
| Lifecycle | NEEDS_PROVENANCE |

The four post-meta rows remain unchanged. The Task 4I ordered full-row PHP-serialized SHA-256 before and after cleanup is 735bca4ec7f5cc738a67622a9e333fd2195f27c6b9555fa3f9245ed9251e1d95. Task 4H's historical fingerprint is a70b9abc5fb7652484d4c90ce3db31be05cd60bdf3d7516adef7a87f207cb06e.

## Attachment 154

Attachment 154 remains an image/webp attachment with parent 0. Its file exists at wp-content/uploads/2026/04/lograstudio-yoga-3053488_1920.webp, its metadata exists, and its metadata hash is unchanged at 2ce3f34988b43eb22c851ee25d12a7b5c1bb9f1343955b8c38258de85b7da006. Published event 166 continues to use it.

## Taxonomy invariants

| Taxonomy or field | Terms | Roots | Relationships | Objects |
|---|---:|---:|---:|---:|
| content_category | 13 | 8 | 0 | 0 |
| content_topic | 55 | 7 | 0 | 0 |
| hf_cat_post | 15 | 15 | 0 | 0 |
| editorial_format | 3 | 3 | 0 | 0 |
| editorial_collection | 0 | 0 | 0 | 0 |

Primary Topic relationships remain 0. The extra roots columns show current taxonomy trees and do not change the required term and relationship counts.

## Featured Content Manager

The featured-content-manager ACF option values remain:

| Field | Value |
|---|---:|
| featured_nuwe_jy | 542 |
| featured_geloof | 534 |
| featured_self | 531 |
| featured_werk_geld | Unset |
| featured_ouerskap | Unset |
| featured_huwelik | Unset |
| featured_mensstories | Unset |
| featured_leefstyl | Unset |

Their SHA-256 fingerprints match the before values in TASK_4I_CORE_CATEGORY_CLEANUP_REPORT.md.

## Migration provenance

| Option | SHA-256 |
|---|---|
| vgpc_content_topic_migration_local_v1 | 605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95 |
| vgpc_content_topic_migration_local_v1_lifecycle | 915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3 |

Both options remain unchanged.

## FCM regression and runtime impact

The fixture-free baseline probe passed. FCM integration runs 1 and 2 passed with temporary posts 1249 and 1250. Both temporary posts and their relationships and metadata were removed. Persistent state was restored, and the final global orphan scan remained at 0.

Persistent Task 4I impact:

- Six approved orphan category relationships removed.
- Existing posts deleted or edited: 0.
- Media or terms deleted: 0.
- Taxonomy registrations changed: 0.
- FCM values changed: 0.
- Migration records changed: 0.

The grouped local fixture record remains DEFER because post 375 remains NEEDS_PROVENANCE.
