# Fixture Dependency Replacement Report

## Scope and baseline

Task 4F started from fresh `origin/main` at `9f5434d7dede5d0dbae62d1a6589d9bd7431b91` (PR #12) in the isolated branch `chore/replace-fixture-dependencies`. Runtime work used Novamira profile `vgnova`, verified as `http://localhost:10039/`.

No existing fixture object was deleted, trashed, or reclassified. Posts 375, 521, and 641 remain published; product 523 remains published. Temporary posts 1235 and 1236 existed only during their respective test runs and were deleted by WordPress API cleanup.

The source-controlled Bricks maintenance script is [replace-fixture-template-previews.php](../../site-code/runtime-tests/replace-fixture-template-previews.php). The source-controlled integration test is [fcm-self-featured-integration.py](../../site-code/runtime-tests/fcm-self-featured-integration.py), with its WordPress actions in [fcm-self-featured-runtime.php](../../site-code/runtime-tests/fcm-self-featured-runtime.php). No Bricks export/deployment convention existed in the repository, so the guarded WordPress API script records the smallest reproducible settings change.

## Bricks template 372 — Posts

| State | Value |
|---|---|
| Template identity | ID 372, title `Posts`, status `publish`, type `content` |
| Preview before | `templatePreviewType=single`, `templatePreviewPostType=post`, `templatePreviewPostId=521` |
| Settings SHA-256 before | `329883311b590ed02ee5f50358d3481df72624814a6aa73051e6f3094192c375` |
| All-meta SHA-256 before | `e8a3502b8d174724fb05e2f7f8458b64dd547f4bec1d85eb2513d1f82c202533` |
| Preview after | `templatePreviewPostId` key absent; `templatePreviewType=single` and `templatePreviewPostType=post` remain |
| Settings SHA-256 after | `1d2f05d2fadabc20364206161d8d7f3c665ddceefecad0edc546447a5fe4b7e4` |
| All-meta SHA-256 after | `3b30043b4e6ad7e8533188479763da5b32ee73a37087024f48d07d87c73e89fe` |
| Non-settings meta SHA-256 | `adc44f9c335658a2b5b73eebe9b1d1943d7da56123767feeffe6edf36a781330` before and after |
| Conditions | Unchanged: `postType` is `post` |
| Template elements SHA-256 | `ca24a5fe03788c6dae4d0f0efaf6e76ccffcf45ac9c13aedc2f61cc89c8c851f` before and after |

**Strategy:** Option A. The active template's conditions and content are intact, and Bricks 2.3.5 supports an empty single preview. Its settings control presents an empty `Search / Select` value; `Helpers::get_template_preview_query_vars()` adds `p` and `post_type` only when a preview ID exists. In the builder, an empty preview displays Bricks' `Click to set preview content.` placeholder and still initializes the template. Dynamic fields remain in the saved template and resolve in the real post context.

The relevant installed source is `wp-content/themes/bricks/includes/settings/settings-template.php:779-791`, `includes/helpers.php:3091-3187`, `includes/builder.php:820-846`, and `includes/elements/base.php:4280-4313`. The installed builder source also shows that it skips preview post setup when the ID is empty and skips a custom preview query when the query vars are empty.

The authenticated editor could not be opened: the collaborative browser reached the WordPress login page, and no credentials were requested. Stored settings, Bricks source behavior, and the normal front end were used for verification. The Posts template output for post 521 returned HTTP 200 before and after. After normalizing rotating WordPress/Bricks nonces and the generated block-style placeholder, the page HTML matched exactly (SHA-256 `e34ef5127e2ab7c07a6e1674da5297b47996d8235788f408c32fb444411c3e99`).

**Removal recommendation for 521: `READY_TO_REMOVE`.**

## Bricks template 690 — Article Card

| State | Value |
|---|---|
| Template identity | ID 690, title `Article Card`, status `publish`, type `section` |
| Preview before | `templatePreviewType=single`, `templatePreviewPostId=523` |
| Settings SHA-256 before | `5bec4721e5cad6051ebea93e99f941751a1bfee281b6d66b527f01bce0167341` |
| All-meta SHA-256 before | `dd0b7d0c46bccd86a20553afc259dd5d2659dd230da774d273e9710a226b3338` |
| Preview after | `templatePreviewPostId` key absent; `templatePreviewType=single` remains |
| Settings SHA-256 after | `f88b1e1e75913d4e2f18b9e4d9fc542bbe4ea25cb8d2b9d63ba351940ec5600a` |
| All-meta SHA-256 after | `1863646cb2d692d4afac7da5b59357d35aea728ffc24fb5076362ecd199e2602` |
| Non-settings meta SHA-256 | `32f6c14582a951b6a4d2c26898f70ab6e6a434f6a066c99e9582fa7a8155882c` before and after |
| Conditions | None before or after |
| Template elements SHA-256 | `8599661518f167071a1a73a4b7abe8574761348562ce8d0e8e1ef7ce4b56b9b0` before and after |

**Strategy:** Option A. Inspection showed template 690 is a section whose saved component instance is product-oriented: it renders post title/content and category data and includes a product add-to-cart element. Its structure, dynamic tags, and element data were unchanged. The preview was cleared rather than assigned to another product. Repository/runtime reference inspection found no active front-end consumer of this Article Card template; the matching references were revisions. Bricks 2.3.5 uses the same supported empty preview behavior described above.

The product 523 front end returned HTTP 200 before and after. After normalizing rotating WordPress/Bricks nonces and the generated block-style placeholder, the page HTML matched exactly (SHA-256 `200211b93c2ce2aa1a2f24db34390dfa877ba59930365451b489275781b356b8`). The template was not pointed at a replacement object.

Editor UI access was unavailable because the browser reached WordPress login. Installed source and stored settings confirm the empty state remains editable; visible post data in the editor will require a user to choose a preview object manually.

The prior Task 4D manifest identifies 523 as a reviewed Task 2 system-test product, records its `oefening-fiksheid` classification, and states that no live service requires it. Task 4F closes the remaining Bricks preview reference. Its product classification and relationships remain intact; the attachment remains untouched. `READY_TO_REMOVE` here closes the Task 4F dependency gate and is a recommendation for independent Task 4G review, not deletion authorization within Task 4F.

**Removal recommendation for 523: `READY_TO_REMOVE`.**

## FCM integration test replacing post 641

The test executes the actual local path:

```text
temporary published post
→ content_category=self
→ active FluentSnippet helper vg_article_get_featured_content_post_id()
→ current featured_self ACF option
→ active Bricks Posts template and Featured Content Block
→ normal public post URL
```

Run it from the repository root with:

```sh
python3 site-code/runtime-tests/fcm-self-featured-integration.py
```

The runner invokes WordPress actions through Novamira profile `vgnova` and refuses any runtime whose `home_url('/')` is not exactly `http://localhost:10039/`. Each run creates a standard `post` with title `[QA][AUTOMATED] Self Featured Content Integration`, a UUID-derived `qa-automated-self-featured-integration-*` slug, and status `publish` only for the front-end request. It assigns only `content_category=self`; the active HappyFiles integration mirrors `hf_cat_post=self`. The test asserts there is no `content_topic`, Primary Topic, `editorial_format`, or `editorial_collection` assignment.

The test reads the current `featured_self` ACF option and resolves the configured object's title and permalink on every run. It does not encode product ID 531 or its current title in the test contract. The current runtime value was 531, resolving to published product `Slaapmasker Deluxe` at `/winkel/slaapmasker-deluxe/`.

The test calls the active helper function and requests the temporary article's normal permalink. It requires HTTP success, the active Bricks Posts template marker, the Featured Content Block marker, and at least one rendered card containing both the current configured title and permalink. Each run produced two matching featured cards, reflecting the two active placements in the article template.

| Run | Temporary ID / slug | HTTP | Card matches | Cleanup | Persistent counts/options |
|---|---|---:|---:|---|---|
| 1 | 1235 / `qa-automated-self-featured-integration-6adb8dabacdb479da5fcb02e53e95bed` | 200 | 2 | PASS; post, taxonomy relationships, and meta absent | Restored exactly |
| 2 | 1236 / `qa-automated-self-featured-integration-d9a9c828c18045c48faa855ff6b2074d` | 200 | 2 | PASS; post, taxonomy relationships, and meta absent | Restored exactly |

Creation and rendering are protected by Python `try/finally` teardown and SIGINT/SIGTERM handlers. The WordPress helper also deletes a partially created post if an assertion fails during creation. Cleanup uses `wp_delete_post()` and verifies no matching post row, any taxonomy relationship, or post meta remains. After both runs there were no objects matching the automated QA identity, no temporary HappyFiles relationship, no editorial relationship, no post count change, and no `featured_*` option change. The migration record and marker hashes also matched their pre-test values after both runs.

The active production FluentSnippet `2-vg-article-dynamic-tags.php` was not changed. The live file at `wp-content/fluent-snippet-storage/2-vg-article-dynamic-tags.php` is not source-controlled in this repository, so the test verifies the current runtime helper rather than pinning a source-controlled copy of that implementation. It fails closed if `vg_article_get_featured_content_post_id()` is not loaded, then checks its selected ID against the live option and the real Bricks output. No FCM option was edited.

**641 dependency replacement: `COMPLETE`. Removal recommendation: `READY_TO_REMOVE`.** The integration result is evidence for the current local runtime; its confidence is medium because the active FluentSnippet implementation is not tracked in this repository.

## Remaining state and removal recommendations

| Object | Result | Recommendation |
|---:|---|---|
| 521 | Template 372 no longer refers to it; real post output is unchanged | `READY_TO_REMOVE` |
| 523 | Template 690 no longer refers to it; product page is unchanged | `READY_TO_REMOVE` |
| 641 | Two source-controlled end-to-end test runs pass and clean themselves | `READY_TO_REMOVE` |
| 375 | Not inspected for provenance or modified | `NEEDS_PROVENANCE` |

The grouped fixture inventory remains `DEFER` because 375 is unresolved. Task 4F did not delete any of these four objects. Task 4G may review controlled removal of 521, 523, and 641 while leaving 375.
