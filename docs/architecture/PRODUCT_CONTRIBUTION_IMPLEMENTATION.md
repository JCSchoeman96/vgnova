# Product Contribution implementation

Status: COMPLETE
Version: 1.0.0
Final implementation baseline: `85094bc24dae2a92c623bbd5d77cc9945f3c69ed`
Runtime evidence baseline: PR #54, merge `7af938ac6f4422b1fa779b52444121e7a9293995`
Runtime recheck: PR #55, merge `85094bc24dae2a92c623bbd5d77cc9945f3c69ed`, with no runtime writes

## Purpose

Product Contribution is the canonical Product-to-Person relationship. One row represents one Product × Person pair and stores that Person's Product roles and Product-side display order. Multiple roles stay on one row. Reverse reads are derived from the same rows; the system stores no second Person-to-Product relationship.

## Schema ownership

ACF Pro Local JSON owns the editor schema:

| Definition | Key | Purpose |
|---|---|---|
| Post type | `product_contribution` | Administrative relationship record |
| Taxonomy | `product_role` | Flat Product role vocabulary, attached only to `product_contribution` |
| Field group | `group_vg_product_contribution_details` | Four fields on Product Contribution records |

The four fields are:

| Field | Type | Value |
|---|---|---|
| `product_contribution_product` | Post Object | WooCommerce Product |
| `product_contribution_person` | Post Object | Person |
| `product_contribution_roles` | Taxonomy | One or more `product_role` terms |
| `product_contribution_display_order` | Number | Non-negative Product-side Person order |

The Product Contribution post type is private and admin-facing. It is not publicly queryable, is excluded from search, has no archive or rewrite, and supports only the title. It remains available in wp-admin and REST for authenticated administration. WordPress registers its `query_var` as boolean `false`.

The ACF Local JSON files store `"none"` for the disabled query-var setting. That is ACF's source representation for WordPress's runtime `false` value. `"none"` is not a domain contract. Event Participation uses the same setting and runtime value.

## Product Role vocabulary

`ProductRoleVocabulary` is the single source for the approved Product Role names and slugs. `ProductRoleSeeder` reads that vocabulary when it checks or creates terms.

| Name | Slug |
|---|---|
| Aanbieder | `aanbieder` |
| Skrywer | `skrywer` |
| Instrukteur | `instrukteur` |
| Skepper | `skepper` |

Product roles describe a Person's contribution to a Product. Event roles describe a Person's participation in an Event. There is no automatic mapping between `product_role` and `event_role`.

## Relationship identity and locks

The canonical identity is `(product_id, person_id)`. A Product and Person pair can have only one non-retired Product Contribution. The uniqueness check reserves pairs in `publish`, `draft`, `pending`, `private`, and `future` status. `trash` and `auto-draft` do not reserve the pair.

`ProductContributionPairLock` uses a named lock for a Product × Person pair and a separate named lock for an existing contribution row. An edit locks its row before it reads the stored endpoints. It then locks the resolved pair and performs a fresh duplicate check after the locks are held. The fresh check bypasses request-local post and ACF caches. If the read is uncertain, the save fails closed.

## Writes and validation

`ProductContributionService` validates Product and Person endpoints, validates Product Role term IDs against `ProductRoleVocabulary`, derives the post title from the current Product and Person titles, and writes a contribution under the pair lock. Its create method accepts `publish`, `draft`, `pending`, or `private`. Direct service creation requires valid, non-trashed endpoints even for a draft.

`ProductContributionAdmin` applies the editor rules through ACF validation and WordPress post-save hooks. It checks submitted or stored endpoint values, validates role submissions, rejects negative or malformed display orders, locks existing rows before it reads their endpoints, and runs a fresh duplicate check before the post row is saved. The admin editor permits an incomplete draft with no complete Product × Person pair. That draft receives an empty title and does not reserve a pair. A published row needs both endpoints and at least one approved Product Role.

Manual mode accepts a valid Product and Person pair. Event mode also requires the Person to have an active Event Participation in the Product's source Event before publication. The source Event comes from `produk_bron_event`. Invalid or blank Product People mode cannot publish a contribution.

ACF's four field definitions keep their `required` flags off so an incomplete draft can be saved. The service and admin layer enforce the publication requirements. Scheduled publication uses the same validation path. Product Contribution titles follow the current Product and Person titles; title changes on either endpoint resync non-retired contribution rows under row locks.

If a service write fails after inserting a contribution, the service hard-deletes that new post and verifies that the post, its metadata, and its Product Role relationships are gone. It returns a rollback error if it cannot verify or complete that cleanup.

## Membership authority

Product People mode remains explicit:

| Mode | Membership authority |
|---|---|
| `manual` | Published Product Contribution rows |
| `event` | Event Participation for the Product's `produk_bron_event` source Event |
| Blank or invalid | No canonical People |

Manual Product People now comes from Product Contribution. It does not come from `product_people_override`, `produk_medewerkers`, or `medewerker`.

Event Product People comes from Event Participation. Product Contribution can add optional Product-role annotation for a Person who currently belongs to the source Event. It never creates Event membership. A stored annotation for a Person who has left the Event is excluded from active reads.

`produk_bron_event` remains the Product's source and provenance Event. It is independent of Product-role annotation and WooCommerce's `_event_name` relationship.

## Forward reads

`contributions_for_product()` returns published, valid contribution rows for a Product in a configured mode. It excludes rows with invalid endpoints or unapproved roles. Contribution rows sort by `product_contribution_display_order` ascending, then contribution ID ascending.

`people_for_product()` is the canonical forward membership read. In manual mode it returns the published Persons on valid Product Contributions in that same order. In event mode it returns published Persons in Event Participation order for the Product's source Event. Blank or invalid mode returns an empty list.

`associations_for_product()` returns an in-memory array with Product ID, Person ID, Product Role IDs, display order, and membership source. Manual associations come from Product Contributions. Event associations come from Event Participation and may include Product-role annotations for current Event members. The array is a read view, not stored reverse data.

## Reverse reads

`contributions_for_person()` returns published contribution rows for the Person, with valid Product and Person endpoints. `products_for_person()` returns published Products for a published Person. It unions manual Product Contribution membership and Event-derived membership, deduplicates Product IDs, and sorts them by Product ID ascending.

For manual membership, the repository requires a valid published Product Contribution. For event-derived membership, it requires the Product's source Event to be among the Person's active Event Participations. A Product Contribution cannot add or preserve event-derived membership on its own. This excludes stale Event annotations.

`associations_for_person()` builds in-memory association records from the same forward authority and sorts by Product ID ascending. The Product-side display order does not set Person-page Product order.

## Product People compatibility

`ProductPeopleResolver::people_for_product()` remains the stable compatibility API. It delegates to `ProductContributionRepository::people_for_product()` and returns an empty list if ACF reads are unavailable. It does not read `produk_medewerkers`, `event_medewerkers`, `medewerker`, or `_event_name`.

`ProductPeopleBricks` remains behaviorally stable. It handles only the exact `vg-product-people-query` and `vg-product-people-section` markers. It passes the resolver's ordered Person IDs to Bricks and hides an empty frontend section while leaving it visible in builder context.

## Person reverse Product adapter

`PersonProductsBricks` is implemented. It handles these exact markers:

```text
vg-person-products-query
vg-person-products-section
```

It resolves Person context in this order: Bricks `preview_or_post_id`, the singular queried object, `get_the_ID()`, then the global post. A present higher-priority context that is invalid or is not a Person returns no results; the adapter does not fall through to a lower-priority context. With no valid Person it fails closed to an empty query.

The marked query asks for published Products in the repository's Product ID order. Empty results use `post__in: [0]`. The marked section hides on the frontend when it has no Products and remains visible in Bricks builder context. The adapter delegates only to `ProductContributionRepository::products_for_person()`.

The Product Contribution implementation did not mutate a production Bricks template. The Person adapter is source code, not evidence that a production template currently calls it. Template 772 still has its separate legacy reverse Product dependency.

## Override retirement

The `product_people_override` source field definition and the override constants in `ProductPeopleFields` are retired. The canonical resolver does not read historical override metadata. Task 1 preflight found zero override rows that required migration, and the final Task 8 state still had zero rows under either `product_people_override` or `_product_people_override`.

No general metadata cleanup occurred. Historical database metadata is not automatically deleted. Task 9 rechecked both keys and found zero rows.

## Runtime activation and evidence

Task 8A audited runtime provenance. The historical automatic-writer verdict remains `UNKNOWN` because the available evidence did not identify every possible writer.

Task 8B normalized the active Platform Core directory to the controlled Task 8 artifact. Its 54 files matched the expected tree SHA-256 `a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760`. Task 8C then performed targeted ACF synchronization and runtime QA. PR #54 merged as `7af938ac6f4422b1fa779b52444121e7a9293995`, with parent 1 `caaba61069eabe347a2a0e0949fce41106e76be0` and parent 2 `8386a0e44d65e6b625d8c10bd8ac13cc0a91c7f6`.

Task 8C's final live QA run token was `[QA][AUTOMATED] Product Contribution 20261024T190033Z-d84e4dd076`. Its 16 live assertions passed. Setup safety passed 5/5 and cleanup safety passed 8/8. Cleanup left no owned records, no Product Role relationships, no WooCommerce lookup rows for the QA Products, and no unexplained Tickera records. Protected fingerprints stayed unchanged, and Platform Core stayed at the exact expected baseline.

PR #55 merged as `85094bc24dae2a92c623bbd5d77cc9945f3c69ed`. PE-006C added a manifest-pinned runtime integrity guard to the source gate and recorded a read-only runtime preflight. It did not change WordPress data, ACF, Bricks, FluentSnippets, or the active Platform Core directory.

Task 9 repeated read-only checks against the current runtime. The active Platform Core had 54 files and matched the Task 8 SHA-256 above. There were no missing, extra, mismatched, or mode-mismatched files. Its entrypoint hash was `3fc688ccf27ec11ee4bb9fe5e282630ded724d4a987e5489f9bc77142dca72bb`, `includes/Plugin.php` was `054b373bc4512cadf7f4e9700347867dc90ca359527770b0ecf3a3a825512625`, and `PersonProductsBricks.php` was `a7460a1e21d4318a9d3997e52ad9e0d804a155798b1b7992e786b4cb976170e5`.

### ACF query-var correction

The ACF JSON value `false` did not disable the query variable in ACF's stored CPT definition. PR #52 changed the source value to `"none"`. WordPress now reports `query_var === false` for both Product Contribution and Event Participation. Both CPTs were non-publicly-queryable before and after that correction.

PR #53 corrected ACF Local JSON synchronization metadata so the definitions could sync. The `modified` value is ACF synchronization metadata, not a MySQL row-write timestamp. Task 8C confirmed the three Product Contribution definitions were in sync. The unrelated Member Perks groups `group_vgmp_perk_details` and `group_vgmp_options` remained pending and were not synchronized.

## Source verification

Final Task 9 verification ran on the `85094bc24dae2a92c623bbd5d77cc9945f3c69ed` baseline.

| Gate | Result |
|---|---|
| `bash site-code/bin/check-source.sh` | PASS, including the PE-005 deterministic assembly gate |
| Product Contribution | 142 passed |
| Product People | 101 passed |
| Event Participation | 69 passed |
| Event Participation Bricks | 12 passed |
| Event Role Seeder | 13 passed |
| Product Contribution QA setup safety | 5/5 passed |
| Product Contribution QA cleanup safety | 9/9 passed |
| Person Foundation | 10 passed |
| Content Topic | 67 passed |
| Editorial Dimensions | 11 passed |
| Runtime manifest and three Product Contribution ACF JSON files | Valid JSON |

The Task 9 brief expected 8/8 cleanup safety tests. PR #55 added one cleanup assertion to the current runner, so the merged-main baseline reports 9/9. The Task 9 run used that current runner without changing test sources.

## Explicit non-goals and remaining legacy

Product Contribution did not decommission or migrate any of these:

- Template 772 or its `medewerker_products` query.
- Medewerker profiles, routes, templates, or archive.
- `produk_medewerkers` or its stored values.
- `event_medewerkers` where legacy consumers still use it.
- FluentSnippets snippet `9-acf-product-copy.php`.
- Template 773.
- Broadcast host and guest relationships.
- WooCommerce `_event_name` relationships.

Those items remain under their separate migration and removal gates. Product Contribution does not authorize URL changes, data migration, metadata deletion, Bricks edits, snippet edits, or further WordPress changes.
