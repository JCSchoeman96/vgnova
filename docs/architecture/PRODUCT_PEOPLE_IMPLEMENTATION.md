# Product People foundation implementation (Task 7A)

This record documents the canonical Product People foundation as implemented and verified. It is foundation-only: the fields and read-only resolver exist, but no Product consumer, template, snippet, or legacy relationship has been migrated.

| Item | Record |
|---|---|
| Spec | [Product People foundation design](../superpowers/specs/2026-10-19-product-people-foundation-design.md) |
| Plan | [Task 7A plan](../superpowers/plans/2026-10-20-product-people-foundation-7a.md) |
| Execution baseline | `origin/main` = `b63f5d529722d2d50350ddbbdea3917b07250a4d` (verified at execution start and again before the PR) |
| Branch | `feat/product-people-foundation-7a` |
| Feature commits after baseline | `396e450` fields; `9d9fdbd` resolver; `4d3dd12` Platform Core wiring; `95ab18b` runtime QA script; `d0cddbc` Tickera side-effect QA fix; `6c14efd` ledger ruling; plus the Task 7 documentation commit |
| Feature head SHA | Recorded in the implementation PR body (the head cannot be recorded inside the commit that defines it) |
| Scope | `ProductPeopleFields`, `ProductPeopleResolver`, focused tests, `integration.patch` and README wiring, guarded runtime QA, and architecture records |

## Field schema

Group `group_vg_product_people` (title "Product People") is registered in PHP on `acf/init` through `ProductPeopleFields`. Location: `post_type == product`. The group is local, active, and not stored in ACF Local JSON.

| Field key | Name | Type | Settings |
|---|---|---|---|
| `field_vg_product_people_mode` | `product_people_mode` | select | choices `event` (Event-derived) and `manual` (Manual); blank allowed; no default; single value; optional; return value `value` |
| `field_vg_product_people_override` | `product_people_override` | relationship | post type `person` only; filter `search`; min 0; max 0 (unlimited); optional; return `id`; shown only when `product_people_mode == manual` |

The group does not define or modify `produk_bron_event` or `produk_medewerkers`. Field registration writes no Product meta: the runtime proof showed zero canonical Product meta rows before and after deployment.

## Resolver contract

```php
namespace Voelgoed\PlatformCore\Product;

final class ProductPeopleResolver
{
    public function __construct(?EventParticipationRepository $repository = null);
    /** @return list<int> */
    public function people_for_product(int $product_id): array;
}
```

Resolution is read-only and uses the explicit mode only:

| Stored mode | Behaviour |
|---|---|
| `event` | Reads `produk_bron_event` (ACF raw ID). Returns `[]` unless the value is a positive integer or digit-only string and `EventParticipationRepository::is_valid_event()` accepts it. Otherwise returns the published Persons from `EventParticipationRepository::people_for_event()`, in repository order (display order ascending, then participation ID ascending). |
| `manual` | Reads `product_people_override` (raw ID array). Accepts positive integers and digit-only strings, keeps editor order, drops duplicates by first occurrence, and returns only published Persons. A non-array value fails closed to `[]`. |
| absent, empty, or any other value | `[]`. Mode comparisons are exact; case and whitespace variants are invalid, not normalized. |

Invalid Product IDs and non-Product posts return `[]`. Event mode never falls back to manual data, and manual mode never falls back to the source Event. Neither mode ever reads `produk_medewerkers`, `event_medewerkers`, or the `medewerker` post type.

Published-Person filtering is applied on top of the repository. The repository validates endpoint types and active participation state, and the resolver additionally requires each Person to be `publish`, so draft and private Persons never appear, even in manual mode.

## Explicitly not implemented

- No Product write or sync path. The resolver never calls an update function, and the existing `9-acf-product-copy.php` snippet is untouched.
- No memoization or caching. Every call reads current state.
- No Bricks consumer migration. Product Single 124 and every Product consumer remain on legacy relationships.
- No retirement of `produk_medewerkers`, the copy helper, or the `product_medewerkers` and `product_source_event` helper modes.

## Source and runtime parity

| File | SHA-256 (source = runtime) |
|---|---|
| `includes/Product/ProductPeopleFields.php` | `bbefd2ff36822bef87db5a99da6725b6cbc94a564bb00b8fcee6b70a1b7c9635` |
| `includes/Product/ProductPeopleResolver.php` | `4f108ee2c30b4455c4a77b3c5a2d1e9f955f2c1af6ba9928a0a95320f9699b61` |

Runtime bootstrap changed only by the four Product lines in `integration.patch`. `voelgoed-platform-core.php` moved from `0a65ed37…` to `315985ba…`, and `includes/Plugin.php` moved from `596b37e8…` to `646b356e…`. The full hash set is in [`PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json`](PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json).

## Test results

| Suite | Result |
|---|---|
| Product People focused suite | 50 passed (fields 7; resolver 27; integration wiring 5; Tickera side-effect ownership and QA guards 11) |
| Event Participation | 65 passed |
| Event Participation Bricks | 12 passed |
| Event Role Seeder | 13 passed |
| Content Topic | 67 passed |
| Person foundation | 10 passed |

Red and green evidence was recorded per behaviour as the work progressed. The Tickera side-effect fix (Task 5 regression) was written test-first: the reproduction test failed with `The QA runtime must provide the Tickera side-effect planner`. The minimal planner then passed it. The ownership rule tests then failed for the unexplained, partial-match, pre-existing, and no-reference cases (7 failures in total, including the three QA guards). The full ownership rules then turned all of them green, and the focused suite reached 50 passed.

Mutation check: removing the published-status filter and falling back to the Event in manual mode each caused resolver tests to fail; the original file was restored byte-identical.

## Guarded runtime QA

The final run used run token `20261020055806-e770a89da8dc6181`. Its result was `PASS`, with no failures.

| Check | Result |
|---|---|
| Scenarios S01, S02, S02b, S03, S04, S05, S06, S07, S08, S09, S10, S11, S12, S13 | 14 of 14 PASS (S02 and S02b drove ACF blank saves through `acf_save_post()`) |
| `malformed_source_event_returns_empty` | PASS |
| `acf_save_path_fired` | true |
| Persons before / after | 0 / 0 |
| `event_participation` before / after | 0 / 0 |
| Owned QA residue (posts, meta rows, term relationships) | 0 / 0 / 0 |
| Run token in posts, post meta, or options | 0 / 0 / 0 |
| Owned Tickera side effect removed before the QA Event | `tc_api_keys` 1355, owned by this run, deleted after re-verification |
| Unexplained Tickera side effects | none |
| `tc_api_keys` exact equality to pre-QA state | true (18 pre-existing records) |
| Protected Product, Event, template, component, and snippet state | equal to pre-QA snapshot |

The QA runs inside the loaded runtime, is hard-deleted in dependency order with ownership markers verified at each step, and aborts before any mutation if its preflight fails.

## Earlier failed run and ruling

The first Task 6 run (token `20261020054436-e6890be3153eeea3`) failed its post-cleanup audit. All 13 scenarios and the malformed-source check passed, but Tickera created an owned-looking `tc_api_keys` record (post 1346) linked to the QA Event, and the QA cleanup did not target it. Recovery removed only that record after an exact ownership check, restored both bootstrap files to their pre-deployment hashes, removed the two Product files, and matched the pre-deployment snapshot. That run is recorded as evidence of the failure only. The ruling is in [IMPLEMENTATION_LEDGER.md](IMPLEMENTATION_LEDGER.md#task-7a-execution-rulings).

## Final counts and boundaries

- Persistent Person count: 0.
- Persistent `event_participation` count: 0.
- Product Single 124, Medewerker Single 772, Event Single 774, component `6b463f`, component `194bba`, `produk_medewerkers`, `_event_name`, existing `produk_bron_event` values, and both legacy snippet hashes are unchanged.
- Existing Products remain unconfigured (no canonical mode or override meta).
- Consumer cutover belongs to Task 7B.
