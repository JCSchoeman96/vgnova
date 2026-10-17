# Task 4I core category cleanup report

Date: 2026-10-17
Runtime: Novamira profile vgnova, local site http://localhost:10039/
Result: Six approved orphan relationships removed. Post 375 and its live relationship remain unchanged.

## Git baseline

| Detail | Value |
|---|---|
| Base SHA | 72b0b8b36f8e5457ee8fc172117b5049388307e0 |
| Branch | chore/clean-core-category-orphans |
| Worktree | .worktrees/chore-clean-core-category-orphans |
| WordPress root | /home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/ |
| Verified home_url('/') | http://localhost:10039/ |

No production or staging endpoint was used.

## Cleanup authority

Task 4H authorized only six detached category=voelgoed relationships. The manifest identifies each row as ORPHAN_CONFIRMED and READY_TO_CLEAN, with HIGH confidence. This task did not revisit post 375's provenance decision.

| Object ID | Post row before | Relationship before | Cleanup authority | Result |
|---:|---|---|---|---|
| 1 | Absent | category, term 1, term-taxonomy 1, order 0 | Task 4H orphan decision | Removed |
| 513 | Absent | category, term 1, term-taxonomy 1, order 0 | Task 4H orphan decision | Removed |
| 514 | Absent | category, term 1, term-taxonomy 1, order 0 | Task 4H orphan decision | Removed |
| 516 | Absent | category, term 1, term-taxonomy 1, order 0 | Task 4H orphan decision | Removed |
| 518 | Absent | category, term 1, term-taxonomy 1, order 0 | Task 4H orphan decision | Removed |
| 519 | Absent | category, term 1, term-taxonomy 1, order 0 | Task 4H orphan decision | Removed |

Post 375 was protected. Its live category=voelgoed relationship was not part of the cleanup set.

## Preflight and before snapshot

The guarded Novamira PHP operation confirmed home_url('/') and the WordPress root before any write. It rechecked every approved object and relationship, post 375, the global orphan set, taxonomy registration, the voelgoed term, canonical taxonomy counts, FCM values, migration hashes, attachment 154, and the runtime owner snippet.

| Check | Before cleanup |
|---|---|
| Authorized post rows | All six absent |
| Exact authorized relationships | Six present, all category / voelgoed, term ID 1, term-taxonomy ID 1, term order 0 |
| Global orphan term relationships | 6, exactly IDs 1, 513, 514, 516, 518, 519 |
| Post 375 | post, publish, slug integer-maximus-sapien-in-bibendum, modified 2026-05-20 15:15:58 |
| Post 375 category relationship | Present, category=voelgoed, term-taxonomy ID 1, order 0 |
| Category taxonomy | Exists; registered object types []; not registered to post |
| voelgoed term | Term ID 1, term-taxonomy ID 1, slug voelgoed, name Voelgoed, stored count 0 |
| Category relationship rows | 7 total: live object 375 and six absent objects |
| Runtime owner | wp-content/fluent-snippet-storage/4-taxonomy-posts-hide.php; contains the category unregistration; SHA-256 167fa19506c408fe37f489688c21e5b8769202d9716b5196d634d8762a8298a5 |

The global orphan scan used wp_term_relationships.object_id LEFT JOIN wp_posts.ID WHERE wp_posts.ID IS NULL. It found no orphan rows outside the six approved category relationships.

| State | Before cleanup |
|---|---|
| content_category | 13 terms, 0 relationships, 0 objects |
| content_topic | 55 terms, 7 roots, 0 relationships, 0 objects |
| Primary Topic values | 0 non-empty relationships |
| hf_cat_post | 15 terms, 0 relationships, 0 objects |
| editorial_format | 3 terms, 0 relationships |
| editorial_collection | 0 terms, 0 relationships |
| Post 375 metadata | 4 rows; ordered full-row PHP-serialized SHA-256 735bca4ec7f5cc738a67622a9e333fd2195f27c6b9555fa3f9245ed9251e1d95 |
| Attachment 154 metadata | Present; PHP-serialized SHA-256 2ce3f34988b43eb22c851ee25d12a7b5c1bb9f1343955b8c38258de85b7da006 |

The four post 375 metadata keys were _edit_lock, _thumbnail_id, _edit_last, and _masteriyo_has_content. The lock value is not included here. Task 4H recorded its historical post-meta fingerprint as a70b9abc5fb7652484d4c90ce3db31be05cd60bdf3d7516adef7a87f207cb06e. The Task 4I before and after hashes use the same ordered SQL row format.

## WordPress API cleanup

One guarded runtime operation rechecked all preflight conditions before writing. It called wp_delete_object_term_relationships($object_id, 'category') once for each approved ID. SQL was used only for read-only verification. No term recount, direct SQL write, post edit, taxonomy registration change, or term deletion was run.

| Object ID | API call | Relationship after | Post row after |
|---:|---|---|---|
| 1 | Called | Absent | Absent |
| 513 | Called | Absent | Absent |
| 514 | Called | Absent | Absent |
| 516 | Called | Absent | Absent |
| 518 | Called | Absent | Absent |
| 519 | Called | Absent | Absent |

## After snapshot and global scan

The immediate scan in the cleanup operation returned zero orphan relationships. A second read-only verification after the FCM regression also returned zero.

| Core category state | After cleanup |
|---|---|
| Taxonomy | Exists; registered object types []; not registered to post |
| voelgoed term | Exists as term ID 1 / term-taxonomy ID 1; stored count 0 |
| Raw relationship rows | 1 |
| Live relationship rows | 1, object 375 |
| Orphan relationship rows | 0 |
| Global orphan term relationships | 0 |

The remaining category row is 375 to category=voelgoed, term-taxonomy ID 1, term order 0.

## Post 375 and attachment 154

Post 375 remains post / publish with slug integer-maximus-sapien-in-bibendum and modified timestamp 2026-05-20 15:15:58. Its live core category relationship remains. It has no content_category, content_topic, or Primary Topic value. Its four metadata rows and Task 4I ordered full-row hash remain unchanged at 735bca4ec7f5cc738a67622a9e333fd2195f27c6b9555fa3f9245ed9251e1d95. Its lifecycle remains NEEDS_PROVENANCE.

Attachment 154 remains an image/webp attachment with parent 0. Its file exists at wp-content/uploads/2026/04/lograstudio-yoga-3053488_1920.webp, the file size is 91,556 bytes, and metadata remains present with the same hash recorded above. Published event 166, slug sport-gholfdae, still uses attachment 154. Post 375 also retains it as the featured image.

## Canonical taxonomy invariants

| Taxonomy or field | After cleanup |
|---|---|
| content_category | 13 terms / 0 relationships / 0 objects |
| content_topic | 55 terms / 7 roots / 0 relationships / 0 objects |
| Primary Topic relationships | 0 |
| hf_cat_post | 15 terms / 0 relationships / 0 objects |
| editorial_format | 3 terms / 0 relationships |
| editorial_collection | 0 terms / 0 relationships |

The core-category cleanup did not alter content_category or content_topic. The FCM regression temporarily assigned its own Self classification and removed it with each temporary test post; final persistent counts match the before snapshot.

## Featured Content Manager

ACF reads from the featured-content-manager options store returned these values before and after cleanup:

| Field | Value | SHA-256 of PHP-serialized ACF value |
|---|---:|---|
| featured_nuwe_jy | 542 | a20684f0f3874646fb5a8b411e41af8479fe6b6c4b55cb2b611f07adb349c030 |
| featured_geloof | 534 | 5d900f69c2ee21fd181ef368b64e380b3f34be5a1b324fe0fad37e37dcf4b7c9 |
| featured_self | 531 | a39a811431bdd4c2124f815494b7d43d43628f604793fee0a936297b0e768075 |
| featured_werk_geld | Unset | 28859e345f55ce45426724100617830af221bdf3e01f4333ee34ba58d2df4429 |
| featured_ouerskap | Unset | 28859e345f55ce45426724100617830af221bdf3e01f4333ee34ba58d2df4429 |
| featured_huwelik | Unset | 28859e345f55ce45426724100617830af221bdf3e01f4333ee34ba58d2df4429 |
| featured_mensstories | Unset | 28859e345f55ce45426724100617830af221bdf3e01f4333ee34ba58d2df4429 |
| featured_leefstyl | Unset | 28859e345f55ce45426724100617830af221bdf3e01f4333ee34ba58d2df4429 |

No FCM value changed.

## Migration provenance

| Option | SHA-256 before and after |
|---|---|
| vgpc_content_topic_migration_local_v1 | 605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95 |
| vgpc_content_topic_migration_local_v1_lifecycle | 915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3 |

Both options remain unchanged.

## FCM regression

python3 site-code/runtime-tests/fcm-self-featured-integration.py passed after cleanup.

| Check | Result |
|---|---|
| Fixture-free baseline probe | PASS |
| Run 1 | PASS; temporary post 1249; HTTP 200; two featured-card matches; cleanup verified |
| Run 2 | PASS; temporary post 1250; HTTP 200; two featured-card matches; cleanup verified |
| Persistent state restored | YES |
| Temporary posts remaining | None |
| FCM values and migration hashes | Unchanged |

The test created and removed its two temporary posts. Neither post nor its relationships remain.

## Runtime impact

| Change | Count |
|---|---:|
| Approved orphan category relationships removed through WordPress API | 6 |
| Existing posts deleted | 0 |
| Existing posts edited | 0 |
| Media deleted | 0 |
| Terms deleted | 0 |
| Taxonomy registration changes | 0 |
| FCM values changed | 0 |
| Migration records changed | 0 |

Post 375 was not edited, trashed, unpublished, deleted, or reclassified. No removal of post 375 was authorized or attempted.
