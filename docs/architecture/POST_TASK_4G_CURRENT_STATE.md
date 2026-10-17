# Post Task 4G Current State

| Snapshot detail | Value |
|---|---|
| Task | Voelgoed Task 4G — Controlled Removal of Remaining Approved Fixtures |
| Date | 2026-10-17 |
| Git baseline | Fresh `origin/main` at `13b5a51e6355626368046d0013c44a3910a4aecc` (merged PR #13) |
| Branch / worktree | `chore/remove-remaining-approved-fixtures` / `.worktrees/chore/remove-remaining-approved-fixtures` |
| Runtime | Novamira profile `vgnova`; verified `http://localhost:10039/`; WordPress 7.1.3 |
| Successful deletion command | `wp post delete 521 523 641 --force` |
| Permanent deletions | 521, 523, 641 only |

## Removed objects and protected post

| ID | Before | After | Media |
|---:|---|---|---|
| 521 | published post `herstel-na-verlies`, classified `mensstories` | permanently absent | attachment 522 preserved |
| 523 | published simple product `welstand-pakket`, SKU empty, price 349.00, `product_cat=uncategorized` | permanently absent | attachment 524 preserved; no gallery/download files |
| 641 | published post `qa-self-featured-content-test`, classified `self` | permanently absent | attachment 153 preserved and still shared with event 167 |

Post 375 remains `post / publish`, slug `integer-maximus-sapien-in-bibendum`, modified `2026-05-20 15:15:58`. Its `content_category`, `content_topic`, and Primary Topic values remain empty. It remains `NEEDS_PROVENANCE` and was not modified.

## Runtime counts

| State | Before | After |
|---|---:|---:|
| Published standard posts | 3 (375, 521, 641) | 1 (375) |
| Trashed standard posts | 0 | 0 |
| Products total | 21 | 20 |
| Products published | 10 | 9 |
| Products draft | 7 | 7 |
| Products private | 1 | 1 |
| Products trash | 3 | 3 |
| `content_category` terms / relationships / objects | 13 / 4 / 3 | 13 / 0 / 0 |
| `content_topic` terms / roots / relationships / objects | 55 / 7 / 3 / 3 | 55 / 7 / 0 / 0 |
| Primary Topic relationships | 1 | 0 |
| `hf_cat_post` terms / relationships / objects | 15 / 2 / 2 | 15 / 0 / 0 |
| `editorial_format` terms / relationships | 3 / 0 | 3 / 0 |
| `editorial_collection` terms / relationships | 0 / 0 | 0 / 0 |

After deletion, product 523 has no post metadata, product taxonomy relationships, or `wc_product_meta_lookup` row. The three requested content taxonomies have zero relationships and zero related objects. Their terms remain unchanged.

A read-only check found one detached core `category=voelgoed` relationship row each for deleted post IDs 521 and 641. The current runtime does not register core `category` for object type `post`; the standard deletion API left these legacy rows. No separate relationship or taxonomy cleanup was performed. This does not change the zero counts for `content_category`, `content_topic`, or `hf_cat_post`.

## Product 523 commerce preflight

WooCommerce 11.1.1 and WooCommerce Subscriptions were active; HPOS was enabled. The WooCommerce API enumerated 14 orders and one subscription. API and read-only SQL checks found no order line items, refund references, subscription items, or variations tied to product 523. No upsell/cross-sell or other product metadata reference was found. Commerce gate: **NONE**.

## Media survival

| Attachment | Exists | File | Metadata | Parent before → after | Usage after |
|---:|---|---|---|---|---|
| 522 | yes | yes | yes; hash unchanged | 521 → 0 | none |
| 524 | yes | yes | yes; hash unchanged | 523 → 0 | none |
| 153 | yes | yes | yes; hash unchanged | 0 → 0 | event 167 |

No media was deleted. Attachment 153 remains shared with event 167. Product 523 had an empty gallery and no downloadable files.

## Bricks previews and routes

| Template | Preview ID absent | Conditions / elements unchanged |
|---:|---|---|
| 372 | yes | yes; settings SHA `1d2f05d2fadabc20364206161d8d7f3c665ddceefecad0edc546447a5fe4b7e4`; elements SHA `ca24a5fe03788c6dae4d0f0efaf6e76ccffcf45ac9c13aedc2f61cc89c8c851f` |
| 690 | yes | yes; settings SHA `f88b1e1e75913d4e2f18b9e4d9fc542bbe4ea25cb8d2b9d63ba351940ec5600a`; elements SHA `8599661518f167071a1a73a4b7abe8574761348562ce8d0e8e1ef7ce4b56b9b0` |

The templates retained their Task 4F non-settings metadata hashes and conditions.

| Route | Status | Redirect |
|---|---:|---|
| `/content/herstel-na-verlies/` | 404 | none |
| `/winkel/welstand-pakket/` | 404 | none |
| `/content/qa-self-featured-content-test/` | 404 | none |

## Featured Content Manager post-deletion test

The post-deletion invocation of `python3 site-code/runtime-tests/fcm-self-featured-integration.py` passed:

| Check | Result |
|---|---|
| Fixture-free baseline probe | PASS |
| Integration run 1 | PASS; temporary post 1245; cleaned |
| Integration run 2 | PASS; temporary post 1246; cleaned |
| Current target | Dynamically resolved from `featured_self` to published product 531 |
| Temporary objects remaining | none |
| Persistent category/topic/HappyFiles fixture relationships restored to 0 | yes |
| Persistent state restored | yes |

Each run returned HTTP 200 and rendered two matching featured cards. All eight FCM options were unchanged.

The pre-deletion run also passed twice, using temporary IDs 1243 and 1244, and restored the baseline before deletion.

## Migration provenance

| Value | Result |
|---|---|
| Historical record SHA-256 | `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95` |
| Retirement marker SHA-256 | `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3` |
| Historical record changed | no |
| Retirement marker changed | no |
| Dry-run lifecycle | `retired` |
| `baseline_matches` | `false` |
| Current fingerprint | `67b6e4ed70be35391bb4dc59d55050d3a4e959f4b3a6f0e1516e3fac2d2de919` |
| Current legacy relationship count | 0 |

Only `wp vg-content-topic-migrate --format=json` was run; apply, rollback, and retire were not invoked. The dry run confirms the retired migration remains read-only and blocked from apply/rollback.

## Runtime writes

- Permanently deleted: posts 521 and 641; product 523.
- Temporary test posts: 1243, 1244, 1245, and 1246; all were deleted by the integration test.
- Media deleted: 0.
- Taxonomy terms deleted: 0.
- FCM option values changed: 0.
- Migration record or marker changed: 0.
- Protected post 375 changed: 0.
- Editorial format or collection assignments created: 0.

`content_category` retains its 13 terms and has zero relationships. `content_topic` retains its canonical 55-term, 7-root tree and has zero relationships. No taxonomy was decommissioned. The fixture entry remains `DEFER` because 375 remains unresolved. Task 4H has not started.
