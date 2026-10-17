# Post Task 4F Current State

| Snapshot detail | Value |
|---|---|
| Task | Voelgoed Task 4F — Replace Remaining Fixture Dependencies Without Deletion |
| Date | 2026-10-17 |
| Git baseline | Fresh `origin/main` at `9f5434d7dede5d0dbae62d1a6589d9bd7431b91` (PR #12) |
| Branch / worktree | `chore/replace-fixture-dependencies` / `.worktrees/chore-replace-fixture-dependencies` |
| Runtime | WordPress at `http://localhost:10039/`, WordPress 7.1.3, Novamira CLI profile `vgnova` |
| Runtime mutation | Removed only `templatePreviewPostId` from Bricks templates 372 and 690 using the Bricks WordPress API |
| Existing fixture object deletions | 0 |

## Persistent objects

| ID | Type / status | Final identity and classification | Task 4F result |
|---:|---|---|---|
| 375 | post / publish | `integer-maximus-sapien-in-bibendum`; modified `2026-05-20 15:15:58`; no `content_category`, `content_topic`, or Primary Topic | Untouched; `NEEDS_PROVENANCE` |
| 521 | post / publish | `herstel-na-verlies`; modified `2026-05-27 08:05:54`; category and topic `mensstories`; no Primary Topic | Preview dependency replaced; `READY_TO_REMOVE` |
| 523 | product / publish | `welstand-pakket`; modified `2026-05-29 10:24:12`; categories `natuurlik-gesond`, `nuwe-jy`; topic and Primary Topic `oefening-fiksheid` | Preview dependency replaced; protected product remains; `READY_TO_REMOVE` |
| 641 | post / publish | `qa-self-featured-content-test`; modified `2026-05-29 12:40:52`; category and topic `self`; no Primary Topic | Persistent QA role replaced; `READY_TO_REMOVE` |

The standard-post state is 3 published and 0 trashed: 375, 521, and 641. Product totals remain 21 (10 published, 7 draft, 1 private, 3 trash). Product 523 remains present and unchanged in type, status, slug, modified timestamp, taxonomy relationships, and Primary Topic.

## Bricks preview state

| Template | Before | After | Conditions / content |
|---:|---|---|---|
| 372 `Posts` | Preview 521; settings hash `329883311b590ed02ee5f50358d3481df72624814a6aa73051e6f3094192c375` | Preview key absent; settings hash `1d2f05d2fadabc20364206161d8d7f3c665ddceefecad0edc546447a5fe4b7e4` | `postType=post` condition and element hash `ca24a5fe03788c6dae4d0f0efaf6e76ccffcf45ac9c13aedc2f61cc89c8c851f` unchanged |
| 690 `Article Card` | Preview 523; settings hash `5bec4721e5cad6051ebea93e99f941751a1bfee281b6d66b527f01bce0167341` | Preview key absent; settings hash `f88b1e1e75913d4e2f18b9e4d9fc542bbe4ea25cb8d2b9d63ba351940ec5600a` | No conditions; element hash `8599661518f167071a1a73a4b7abe8574761348562ce8d0e8e1ef7ce4b56b9b0` unchanged |

The templates' non-settings meta hashes remained unchanged. Template 372's conditions remain exactly `postType=post`; template 690 still has no conditions. The guarded maintenance script was run again after application and returned `already_applied` for both templates without further mutation.

The authenticated Bricks editor was unavailable because the browser reached the WordPress login page. Bricks 2.3.5 source confirms that an empty single-preview ID produces an editor placeholder, omits the preview `p`/`post_type` query, and skips preview post setup while leaving the template editable. Front-end checks returned HTTP 200 for post 521 and product 523 before and after. After removing rotating nonce values and the generated WordPress block-style placeholder, each page's output matched exactly. See the [Task 4F report](FIXTURE_DEPENDENCY_REPLACEMENT_REPORT.md) for hashes and source locations.

## FCM integration test state

The source-controlled integration test passed twice. It creates a published standard post named `[QA][AUTOMATED] Self Featured Content Integration`, assigns only `content_category=self`, exercises the active article helper, and requests normal Bricks front-end output. The current `featured_self` option was 531 before and after both runs; the test derives its target dynamically. Both pages returned HTTP 200 and contained two cards with the configured object's title and permalink. The active FluentSnippet implementation is stored in runtime `wp-content/fluent-snippet-storage/2-vg-article-dynamic-tags.php` and is not tracked in this repository; the test checks that its helper is loaded and validates its live result.

The final verification run posts were 1235 and 1236. Each was deleted by the test's WordPress API cleanup; post rows, taxonomy relationships, HappyFiles relationships, and meta are absent. Final QA identity search returned no rows. Published post count and all taxonomy relationship counts returned to baseline. All eight Featured Content Manager option values, the historical migration option, and its lifecycle marker remained unchanged.

## Final taxonomy and editorial counts

| Taxonomy | Terms | Roots | Relationships | Objects |
|---|---:|---:|---:|---:|
| `content_category` | 13 | 8 | 4 | 3 |
| `content_topic` | 55 | 7 | 3 | 3 |
| `hf_cat_post` | 15 | 15 | 2 | 2 |
| `editorial_format` | 3 | 3 | 0 | 0 |
| `editorial_collection` | 0 | 0 | 0 | 0 |

Persistent `content_category` relationships remain 521 → `mensstories`, 523 → `natuurlik-gesond` and `nuwe-jy`, and 641 → `self`. Persistent `content_topic` relationships remain 521 → `mensstories`, 523 → `oefening-fiksheid`, and 641 → `self`. Primary remains 523 → `oefening-fiksheid` (term 357). The automated test left no editorial-format or collection assignments.

## Migration provenance and lifecycle

The historical `vgpc_content_topic_migration_local_v1` record and `vgpc_content_topic_migration_local_v1_lifecycle` marker were unchanged. Their serialized SHA-256 values are `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95` and `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3`.

| Object | Removal readiness |
|---:|---|
| 521 | `READY_TO_REMOVE` |
| 523 | `READY_TO_REMOVE` |
| 641 | `READY_TO_REMOVE` |
| 375 | `NEEDS_PROVENANCE` |

The grouped fixture entry remains `DEFER` while 375 is unresolved. Task 4G may review controlled removal of 521, 523, and 641 and must leave 375. Task 4G has not started.

No remaining fixture object was deleted or trashed. No media was deleted. `content_category` and `content_topic` remain live. No editorial format or collection assignments were made.
