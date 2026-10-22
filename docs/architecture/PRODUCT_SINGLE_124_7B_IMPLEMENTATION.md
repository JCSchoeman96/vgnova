# Product Single 124 Task 7B implementation

Status: implementation and runtime QA complete. PR #29 is open for independent review and is not merged.

## Repository baseline

- Locked base: `a6cccb7f9862d6b8ff11d6635da6ccfd5b6d6b76`.
- Source implementation head before this evidence commit: `1e4d7dea8ee9d200a63968cc25627d5ee852a5a3`.
- Branch: `feat/product-single-124-cutover-7b`.
- PR: [#29](https://github.com/JCSchoeman96/vgnova/pull/29), against `main`.
- Task 7C has not started.

## Runtime gate and route proof

Anonymous Product requests were intercepted by WooCommerce Store Coming Soon during `template_include` and exited before Bricks rendered Template 124. This was runtime store configuration, not a Task 7B consumer failure.

Frontend QA used a temporary, request-scoped `woocommerce_coming_soon_exclude` filter on `localhost:10039`. The Product 767 route probe used an anonymous GET with a one-request token. The filter was removed after the probe. The global options remained `woocommerce_coming_soon=yes` and `woocommerce_store_pages_only=yes` throughout QA.

The route trace is `/tmp/vg-task-7b-route-20261022-7b-bricks-trace-02.jsonl`, SHA-256 `d85ae0ed1a872ad2b6be330e1986405156f68118189fe196de5e5b18d3708d27`. Every record includes `ABSPATH`, `home_url`, `SERVER_PORT`, and request token `20261022-7b-bricks-trace-02`.

The trace proves this request path:

1. `template-loader.php` selected Bricks `single-product.php` after the scoped bypass.
2. Bricks entered `single-product.php` for Product 767 and selected content Template 124.
3. `Helpers::get_bricks_data()` returned 82 elements. Bricks took the data branch and did not enter its fallback.
4. `Frontend::render_content()` and content-area `Frontend::render_data()` both ran with 82 Template 124 elements.
5. `vgpr13` queried `tc_events` with `post__in=[763]`, `orderby=post__in`, and one result.
6. `vgpr14` rendered component `f60f14`. The loop produced the Event card marker and 663 bytes of component output.
7. The HTTP response showed the source Event card for Test Event. The Coming Soon interstitial was absent.

The full guarded QA made authenticated, same-origin frontend requests for its owned temporary Product. It verified the privileged identity before creating fixtures, disabled redirects, and checked the Novamira sandbox directory before every frontend request. No new top-level PHP file appeared.

WordPress and Bricks trace instrumentation was restored byte-for-byte. Current hashes are recorded in the runtime manifest. The temporary MU plugin is absent; the only MU plugin is `vg-masteriyo-course-products.php` with its original hash. The global Coming Soon options remain enabled.

## Template 124 and Product Person Card

Template 124 contained 82 elements before and after the cutover. The pre-write element-tree SHA-256 was `b629c9cb7412ff8531f06ba357eede11383c69424810a44469e5e740bf132826`; the post-write tree SHA-256 is `788c9e4b04a57091d5b6e2fafff6e38b374307b5fa8e0eb22f6c320910fb3088`.

The normalized projection that removes the approved markers, normalizes the Product Person Card swap, and excludes target-loop query settings is `450bd890a4413699827c8d4536b3d500d9f9555492deb85141580fdeef79dc5b`. Template core and non-element metadata hashes stayed equal at `5bf9d2165fc25d30602ffa37a5976199b2542021273edb8e094c0290502f68b4` and `ad786ff204f521df5b496799274c93e84a471697a4c8e8990e85225655a8cb10`. No unrelated Template 124 structure changed.

The four approved target locations are:

- `vgpr00`: Product People section marker `vg-product-people-section`.
- `vgpr03`: canonical Product People loop marker `vg-product-people-query`.
- `vgpr04`: direct loop child changed from shared Medewerker Card `6b463f` to Product Person Card `dc8611`.
- `vgpr13`: independent source Event marker `vg-product-source-event-query`; `vgpr14` retains source Event component `f60f14`.

Product Person Card `dc8611` has structural SHA-256 `4875f5962dd18b6cc66270cb62aa7030bd71c5704edf024b04db1348f74e8a33`. Its loop object is a `person`. It renders featured image, `post_title`, `person_professional_title`, `post_excerpt`, canonical `{post_url}`, and the Product action label `Bekyk profiel`. It contains no `medewerker_*` fields and does not use Event Participation as its loop object.

Protected component hashes stayed unchanged:

- `6b463f`: `57370bbb83d3125758eba9a5194eb1a898b8914b546ee4d84b32f642e07aeaac`.
- `194bba`: `481c60b1bd2310b96cae05834aa49202777788192927021d218c5d1445cadc7f`.

## Source and runtime parity

| File or record | SHA-256 |
|---|---|
| Source and runtime `ProductPeopleBricks.php` | `001967451c55ef4911427a53f0b72a3457ff925298450fc6a4517ff123d18ca7` |
| Source and runtime `ProductSourceEventBricks.php` | `9781aac4f6594d0626539f0f7fc488b863b3fe0af4a9e8b34e3604fed1467697` |
| Source `integration.patch` | `a7f4b868c8b9b205ed04ba60472362e256ab102c9b7e7e1760eeb719255c541d` |
| Runtime Platform Core bootstrap | `2a0645e9d0812cf13e27b2b4f3f1ff25c07650c3bd93deba672f21d96f6a71b6` |
| Runtime Platform Core `includes/Plugin.php` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` |
| Runtime `10-vg-bricks-medewerker-queries.php` | `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2` |
| Runtime `9-acf-product-copy.php` | `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70` |

Source and runtime adapter hashes match. The runtime integration files contain the expected registrations. Helper and copy snippet hashes match their preflight values.

## Guarded runtime QA

QA owner token: `20261022-7b-qa-392bf965d38cde40`. The final run passed all 30 scenarios. The machine-readable results and cleanup state are in [the runtime manifest](PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json).

| Scenario | Result |
|---|---|
| `unconfigured_returns_empty` | PASS |
| `unconfigured_section_hidden_frontend` | PASS |
| `event_mode_preserves_event_participation_order` | PASS |
| `event_mode_excludes_unpublished_person` | PASS |
| `event_mode_ignores_manual_override` | PASS |
| `manual_mode_preserves_explicit_order` | PASS |
| `manual_mode_deduplicates_first_occurrence` | PASS |
| `manual_empty_returns_empty` | PASS |
| `manual_empty_section_hidden_frontend` | PASS |
| `invalid_mode_returns_empty` | PASS |
| `legacy_produk_medewerkers_does_not_affect_output` | PASS |
| `empty_result_query_uses_post_in_zero` | PASS |
| `event_mode_empty_section_hidden_frontend` | PASS |
| `builder_keeps_empty_section_editable` | PASS |
| `builder_call_keeps_empty_section_editable` | PASS |
| `valid_source_event_returns_exact_event` | PASS |
| `source_event_renders_while_people_empty` | PASS |
| `manual_mode_does_not_change_source_event` | PASS |
| `empty_source_event_returns_empty` | PASS |
| `malformed_array_source_event_returns_empty` | PASS |
| `malformed_multi_source_event_returns_empty` | PASS |
| `malformed_scalar_source_event_returns_empty` | PASS |
| `missing_source_event_returns_empty` | PASS |
| `wrong_post_type_source_event_returns_empty` | PASS |
| `trashed_source_event_returns_empty` | PASS |
| `acf_path_is_used` | PASS |
| `_event_name_is_unchanged_and_irrelevant` | PASS |
| `acf_unavailable_fails_closed` | PASS |
| `product_person_card_uses_canonical_person_data` | PASS |
| `manual_product_person_card_preserves_order` | PASS |

The final run used temporary owned records only: Product 1470, Event 1463, Persons 1465–1469, and Event Participations 1471–1474. Tickera side effect 1464 was matched to the owned Event and owner token, rechecked before deletion, and deleted before the Event. There were no unexplained side effects or cleanup failures.

Cleanup proved zero owned posts, metadata rows, term relationships, and token references. Person and Event Participation counts were 0 before and after. All post-type counts matched the pre-QA snapshot. The temporary QA Product had `_event_name=[]` both before and after. Existing Product People fields, `produk_bron_event`, `produk_medewerkers`, and `_event_name` values matched the approved preflight state.

WooCommerce options stayed `yes/yes`; the sandbox directory listing matched before and after. The 11 protected post/meta digest pairs, Templates 772 and 774, components `6b463f` and `194bba`, Tickera API key set, and non-target Template 124 projection matched the preflight evidence.

Two earlier runs, tokens `20261022-7b-qa-2fbc2f50739c45ba` and `20261022-7b-qa-ff607b304a80ea90`, failed the canonical Person permalink assertion because the card had an empty dynamic link. Their cleanup passed. The Product Person Card was corrected to use the supported Bricks dynamic link setting, then the final run passed. An earlier isolated subprocess attempt also stopped because `PHP_BINARY` resolved to PHP-FPM without `libtidy`; no completion claim relies on it.

## Legacy lifecycle rulings

| Runtime path | Fresh caller or dependency evidence | Ruling |
|---|---|---|
| `product_medewerkers` helper mode | 0 active Bricks callers after cutover; helper hash unchanged; safe FluentSnippets edit access unavailable | `RETIRE_PENDING_ACCESS` |
| `product_source_event` helper mode | 0 active Bricks callers; replacement is live and independently verified; helper hash unchanged; safe FluentSnippets edit access unavailable | `RETIRE_PENDING_ACCESS` |
| `9-acf-product-copy.php` | Template 772 element `vgms33` still uses `medewerker_products`, which reads `produk_medewerkers`; copy snippet hash unchanged | `RETIRE_PENDING_DEPENDENCY` |
| `produk_medewerkers` field and values | Existing Product values match the preflight snapshot | `KEEP_LEGACY` |
| `produk_bron_event` field and values | Existing source Event values match the preflight snapshot; Template 124 has its own source Event adapter | `KEEP` |
| `_event_name` commerce relationship | Existing values match the preflight snapshot; Template 774 commerce loop is unchanged | `KEEP` |

Template 772 reverse Product behavior was explicitly inspected and left intact. No FluentSnippets snippet, Product data, Event 763 data, legacy Medewerker edge, Template 774 commerce loop, or unrelated template/component was changed.

## Local verification

| Suite or check | Result |
|---|---|
| Product People | 101 passed |
| Event Participation | 65 passed |
| Event Participation Bricks | 12 passed |
| Event Role Seeder | 13 passed |
| Content Topic | 67 passed |
| Person Foundation | 10 passed |
| Total suite pass executions, including 13 repeated Event Role Seeder tests | 268 passed |
| PHP lint for QA harness, request helper, adapters, and request tests | Passed |
| `git diff --check` | Passed |

All evidence in this report describes Task 7B only. PR #29 is open for independent review. It has not been merged, and Task 7C has not started.
