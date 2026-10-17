# Task 4G Fixture Removal Report

## Scope and baseline

Task 4G authorizes permanent deletion of only IDs 521, 523, and 641 after all preflight gates pass. Post 375 is protected and remains unresolved.

| Detail | Value |
|---|---|
| Git baseline | Fresh `origin/main` at `13b5a51e6355626368046d0013c44a3910a4aecc` (merged PR #13) |
| Branch / worktree | `chore/remove-remaining-approved-fixtures` / `.worktrees/chore/remove-remaining-approved-fixtures` |
| Runtime | Novamira profile `vgnova`; verified `home_url('/') = http://localhost:10039/`; WordPress 7.1.3 |
| Authorized permanent deletion set | `post 521`, `product 523`, `post 641` |
| Protected object | `post 375`, `publish`, `integer-maximus-sapien-in-bibendum`, `NEEDS_PROVENANCE` |

No production or staging endpoint was used.

## Pre-removal evidence

### Object identity and classification

| ID | Type / status | Slug | `content_category` | `content_topic` | Primary Topic | Additional identity |
|---:|---|---|---|---|---|---|
| 521 | post / publish | `herstel-na-verlies` | `mensstories` | `mensstories` | none | modified `2026-05-27 08:05:54` |
| 523 | product / publish | `welstand-pakket` | `natuurlik-gesond`, `nuwe-jy` | `oefening-fiksheid` | `oefening-fiksheid` (term 357) | simple; `product_cat=uncategorized`; SKU empty; regular price `349.00`; gallery empty; no downloads, upsells, or cross-sells |
| 641 | post / publish | `qa-self-featured-content-test` | `self` | `self` | none | modified `2026-05-29 12:40:52` |

Protected post 375 was `post / publish`, slug `integer-maximus-sapien-in-bibendum`, modified `2026-05-20 15:15:58`; it had no `content_category`, `content_topic`, HappyFiles, editorial-format, editorial-collection, or Primary Topic relationship. Its status, slug, timestamp, and classifications were rechecked immediately before the proposed deletion.

### Inbound-reference gate

The read-only preflight found no live inbound references to 521, 523, or 641 in:

- ACF `relationship` and `post_object` values;
- other post metadata;
- Bricks metadata/settings/content;
- post content, including target slugs and exact numeric references;
- WordPress options outside the preserved historical migration record and lifecycle marker;
- the nine currently published FluentSnippets in `wp-content/fluent-snippet-storage`.

The current Featured Content Manager selections were read through ACF. Values were `featured_nuwe_jy=542`, `featured_geloof=534`, `featured_self=531`, and null for `featured_werk_geld`, `featured_ouerskap`, `featured_huwelik`, `featured_mensstories`, and `featured_leefstyl`. None selects a deletion target. The source scan found only retired migration provenance, tests/diagnostics, and the guarded Task 4F preview-maintenance script; these are historical or test evidence, not live consumers.

### WooCommerce transaction gate for product 523

WooCommerce 11.1.1 and WooCommerce Subscriptions are active; HPOS is enabled. WooCommerce API inspection enumerated 14 orders and one subscription. No order or subscription line item, refund item, or variation referenced product/variation ID 523. Read-only SQL checks of `woocommerce_order_items`, `woocommerce_order_itemmeta`, and the HPOS orders table returned no matching line items or refund references. No `product_variation` children reference 523. The order tables exist and the API/SQL checks both completed.

**Commerce result: NONE.**

### Bricks preview gate

Task 4F post-change state was rechecked immediately before removal.

| Template | Preview ID | Settings SHA-256 | Conditions | Elements SHA-256 | Non-settings meta SHA-256 |
|---:|---|---|---|---|---|
| 372 | absent | `1d2f05d2fadabc20364206161d8d7f3c665ddceefecad0edc546447a5fe4b7e4` | `postType=post` | `ca24a5fe03788c6dae4d0f0efaf6e76ccffcf45ac9c13aedc2f61cc89c8c851f` | `adc44f9c335658a2b5b73eebe9b1d1943d7da56123767feeffe6edf36a781330` |
| 690 | absent | `f88b1e1e75913d4e2f18b9e4d9fc542bbe4ea25cb8d2b9d63ba351940ec5600a` | none | `8599661518f167071a1a73a4b7abe8574761348562ce8d0e8e1ef7ce4b56b9b0` | `32f6c14582a951b6a4d2c26898f70ab6e6a434f6a066c99e9582fa7a8155882c` |

The settings, conditions, element hashes, and non-settings metadata matched the Task 4F baseline. No template referenced a deletion target.

### FCM integration gate

The required pre-deletion command `python3 site-code/runtime-tests/fcm-self-featured-integration.py` passed:

- fixture-free baseline probe: PASS;
- run 1: PASS, temporary post 1243, cleanup verified;
- run 2: PASS, temporary post 1244, cleanup verified;
- current `featured_self` target resolved dynamically to published product 531 on both runs;
- persistent post counts, taxonomy counts, FCM options, and migration provenance restored;
- temporary objects remaining: none;
- persistent state restored: YES.

The live post-test check confirmed 1243 and 1244 are absent and the pre-deletion baseline remains intact.

### Persistent baseline

| Runtime state | Before deletion |
|---|---:|
| Published standard posts | 3: IDs 375, 521, 641 |
| Trashed standard posts | 0 |
| Products | 21 total: 10 published, 7 draft, 1 private, 3 trash |
| `content_category` | 13 terms; 4 relationships across 3 objects |
| `content_topic` | 55 terms; 7 roots; 3 relationships across 3 objects |
| Primary Topic metadata rows | 1 |
| `hf_cat_post` | 15 terms; 2 relationships across 2 objects |
| `editorial_format` | 3 terms (`article`, `video`, `audio`); 0 relationships |
| `editorial_collection` | 0 terms; 0 relationships |

Ownership matched the approved baseline: 521 has `mensstories` in both content taxonomies and HappyFiles; 523 has both legacy categories, topic `oefening-fiksheid`, and Primary Topic `oefening-fiksheid`; 641 has `self` in both content taxonomies and HappyFiles.

### Migration provenance

| Option | SHA-256 before deletion |
|---|---|
| `vgpc_content_topic_migration_local_v1` | `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95` |
| `vgpc_content_topic_migration_local_v1_lifecycle` | `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3` |

The saved source fingerprint is `80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847`. Both records are preserved historical provenance.

### Media protection

No media deletion is authorized. All known attachments exist with files and metadata:

| Attachment | Owner before | File | Metadata | Other object usage |
|---:|---:|---|---|---|
| 522 | 521 | exists | exists | thumbnail of 521 only |
| 524 | 523 | exists | exists | product image of 523 only |
| 153 | 0 | exists | exists | thumbnail of 641 and published event 167 |

Product 523 has no gallery media or downloadable files. Attachment 153 is shared and remains independently used by event 167.

### Pre-removal decision

**All Task 4G preflight gates passed.** The runtime target was verified as the local `vgnova` site, identities matched, no blocking references or commerce history were found, both preview pointers were absent with unchanged hashes, the pre-deletion FCM test passed twice, and protected post 375 remained unchanged. No permanent deletion had been issued when this evidence was recorded.

## Post-removal verification

### Deletion result

The first CLI attempt included unsupported `--yes` and was rejected during argument parsing; a read-only check confirmed that all four objects still existed. No mutation occurred in that attempt. The successful single deletion operation was:

```sh
wp post delete 521 523 641 --force
```

WP-CLI exited 0 and reported success for IDs 521, 523, and 641. A final runtime check found all three permanently absent; none is merely trashed. Temporary FCM test posts 1243, 1244, 1245, and 1246 are also absent.

| ID | Before | After |
|---:|---|---|
| 521 | published post `herstel-na-verlies` | absent |
| 523 | published product `welstand-pakket` | absent |
| 641 | published post `qa-self-featured-content-test` | absent |

Protected post 375 remains published as `integer-maximus-sapien-in-bibendum`, with modified time `2026-05-20 15:15:58`; its category/topic/Primary Topic assignments remain empty.

### Post-removal counts

| Runtime state | After deletion |
|---|---:|
| Published standard posts | 1: ID 375 |
| Trashed standard posts | 0 |
| Products | 20 total: 9 published, 7 draft, 1 private, 3 trash |
| `content_category` | 13 terms; 0 relationships; 0 objects |
| `content_topic` | 55 terms; 7 roots; 0 relationships; 0 objects |
| Primary Topic metadata relationships | 0 |
| `hf_cat_post` | 15 terms; 0 relationships; 0 objects |
| `editorial_format` | 3 terms; 0 relationships |
| `editorial_collection` | 0 terms; 0 relationships |

The remaining published standard-post ID is explicitly 375. The 20 remaining product IDs are 525, 527, 529, 531, 642, 765, 767, 768, 842, 844, 845, 846, 905, 908, 911, 914, 917, 920, 923, and 1186. Product 523 has no remaining product taxonomy relationships, post metadata, or `wc_product_meta_lookup` row. The successful WP-CLI output names only the three authorized deletions.

The requested content taxonomies and HappyFiles have zero persistent relationships. Normal `wp_delete_post()` left two detached core `category=voelgoed` relationships because the `category` taxonomy was not registered to `post` and was therefore omitted from its taxonomy cleanup. The initial orphan scan found eight core `category` rows in total, including the two for deleted IDs 521 and 641.

### Post-deletion core category relationship correction

Before correction, both exact rows were rechecked: object IDs 521 and 641 were absent; each row had `term_taxonomy_id=1`, taxonomy `category`, `term_id=1`, slug `voelgoed`, and name `Voelgoed`. The global orphan scan found eight rows, all in `category`; the six unrelated rows belonged to IDs 1, 513, 514, 516, 518, and 519.

A guarded WordPress runtime operation verified `home_url('/')` was `http://localhost:10039/` and called `wp_delete_object_term_relationships(521, 'category')` and `wp_delete_object_term_relationships(641, 'category')`. Both target relationship queries are now empty. The `category` taxonomy and term `voelgoed` (term ID 1) remain. Its reported term count changed from 8 before correction to 0 after correction.

The post-correction orphan scan found six remaining `category` rows, on IDs 1, 513, 514, 516, 518, and 519. They match the unrelated rows from the pre-correction scan and were left unchanged. This correction does not claim that the database has no other orphan term relationships. No category term or taxonomy was deleted.

### Bricks, media, and routes

Templates 372 and 690 still have no `templatePreviewPostId`. Their settings, conditions, elements, and non-settings metadata hashes match the pre-removal Task 4F state in the table above.

| Attachment | Exists after | File | Metadata | Parent before → after | Other object use after |
|---:|---|---|---|---|---|
| 522 | yes | exists | exists; hash unchanged | 521 → 0 | none |
| 524 | yes | exists | exists; hash unchanged | 523 → 0 | none |
| 153 | yes | exists | exists; hash unchanged | 0 → 0 | published event 167 |

Product 523 had no gallery or downloadable-file attachments. No media was deleted; shared attachment 153 remains used by event 167.

| Removed route | HTTP status | Redirect |
|---|---:|---|
| `/content/herstel-na-verlies/` | 404 | none |
| `/winkel/welstand-pakket/` | 404 | none |
| `/content/qa-self-featured-content-test/` | 404 | none |

### Post-deletion FCM integration proof

The required post-deletion command `python3 site-code/runtime-tests/fcm-self-featured-integration.py` passed:

- fixture-free baseline probe: PASS; live absence flags for 521, 523, and 641 are false;
- run 1: PASS, temporary post 1245; HTTP 200, two featured-card matches, cleanup verified;
- run 2: PASS, temporary post 1246; HTTP 200, two featured-card matches, cleanup verified;
- both runs resolved the current `featured_self` selection dynamically to product 531;
- temporary objects remaining: none;
- persistent `content_category`, `content_topic`, and `hf_cat_post` relationships restored to 0;
- all FCM values and migration hashes unchanged; persistent state restored: YES.

The test exercised its own temporary Self article after all three persistent fixtures were absent.

After the core category correction, the integration test passed again with fixture-free baseline PASS, run 1 PASS (temporary post 1247), run 2 PASS (temporary post 1248), and persistent state restored. Both temporary posts and their relationships/meta were removed; the persistent category, topic, and HappyFiles fixture relationship counts remained zero. The live `featured_self` target remained 531 and the options and migration provenance were unchanged.

### Migration dry run and preserved provenance

The read-only command `wp vg-content-topic-migrate --format=json` returned exit 0 with:

| Field | Result |
|---|---|
| Lifecycle | `retired` |
| `baseline_matches` | `false` |
| Current fingerprint | `67b6e4ed70be35391bb4dc59d55050d3a4e959f4b3a6f0e1516e3fac2d2de919` |
| Current legacy relationships | 0 |

The command reported that apply and rollback remain disabled for the retired proof migration. The historical record SHA-256 remains `605344b12ef63f8106b7a9c4f35c86934cb01f3decb65bb41e1c06fc0d7f7f95`; the retirement marker SHA-256 remains `915049ef7fb936c7a0aa5548154f8c8e69452fbb589224fbb8868b3a472999d3`. Neither option was rewritten.

### Runtime impact

- Persistent objects permanently deleted: 3 (two posts, one product).
- Detached core `category` relationships removed through the WordPress taxonomy API: 2 (object IDs 521 and 641); six unrelated orphan rows were left unchanged.
- Media deleted: 0.
- Taxonomy terms deleted: 0.
- FCM options changed: 0.
- Migration records changed: 0.
- Protected object 375 changed: 0.
- Editorial format or collection assignments made: 0.

`content_category` retains all 13 terms and `content_topic` retains its canonical 55-term, 7-root tree. Neither taxonomy was decommissioned. The grouped fixture entry remains `DEFER` because 375 is still `NEEDS_PROVENANCE`.
