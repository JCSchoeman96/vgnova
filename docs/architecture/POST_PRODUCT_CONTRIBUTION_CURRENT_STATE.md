# Post Product Contribution current state

Status: CURRENT
Version: 1.0.0
Repository baseline: `85094bc24dae2a92c623bbd5d77cc9945f3c69ed`
Runtime inspection date: 2026-10-25 12:56 SAST

## Canonical now

Product-to-Person relationships use `product_contribution` and `product_role`. Platform Core owns `ProductContributionRepository`, `ProductContributionPairLock`, `ProductContributionService`, `ProductContributionAdmin`, `ProductRoleVocabulary`, `ProductRoleSeeder`, `ProductPeopleResolver`, `ProductPeopleBricks`, and `PersonProductsBricks`.

Manual Product People membership comes from published Product Contributions. Event-derived Product People membership comes from Event Participation for the Product's `produk_bron_event` source Event. Product Contribution roles can annotate a current Event member but cannot create Event membership. Product and Event roles remain separate vocabularies.

## Runtime now

The inspected site is the VGNova installation at `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`. Its home URL is `http://localhost:10039`, and its database is `local@localhost`.

The active Platform Core has 54 files and exactly matches the Task 8 SHA-256 `a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760`. File-set and content comparison against the pinned PE-005 runtime manifest found zero missing, extra, mismatched, or mode-mismatched files.

The three Product Contribution ACF definitions remain in sync. Runtime has exactly four Product Roles: Aanbieder (`aanbieder`), Skrywer (`skrywer`), Instrukteur (`instrukteur`), and Skepper (`skepper`). It has zero Product Role relationships, zero Product Contribution posts, zero Person posts, zero Event Participation posts, and zero rows for both `product_people_override` and `_product_people_override`.

WordPress reports `query_var === false` for both `product_contribution` and `event_participation`. Both definitions remain private, non-publicly-queryable, admin-facing, and title-only. The `product_role` taxonomy remains private, flat, and attached only to `product_contribution`.

The two unrelated Member Perks definitions, `group_vgmp_perk_details` and `group_vgmp_options`, remain pending. Task 9 did not sync them.

The 20 existing Products still have no configured `product_people_mode` value. No Product field migration occurred. Product Contribution QA fixtures were removed to baseline under Task 8C. The final Task 8C run token was `[QA][AUTOMATED] Product Contribution 20261024T190033Z-d84e4dd076`.

## Legacy still live

The canonical Product Contribution model did not remove `produk_medewerkers`, applicable `event_medewerkers` values, the `9-acf-product-copy.php` writer, Medewerker profiles or routes, Template 772's reverse Product query, Template 773, broadcast host and guest relationships, or `_event_name`. Their existing removal gates remain in force. Entry 18 of the Legacy Decommission Register stays `ADAPT`.

`ONGOING_AUTOMATIC_WRITER=UNKNOWN` remains the historical provenance verdict. The later runtime checks found no unexplained drift, but they do not establish whether every historical writer has been identified.

## Do not undo

- Do not restore `product_people_override` as a source field or read fallback. Do not delete historical metadata automatically.
- Do not use `produk_medewerkers`, `event_medewerkers`, `medewerker`, or `_event_name` as canonical Product People fallbacks.
- Do not let Product Contribution create Event membership or map Event roles to Product roles.
- Do not remove Template 772, `produk_medewerkers`, snippet 9, or Medewerker routes before their separate replacement and consumer gates pass.
- Do not treat `"none"` as the domain contract. The WordPress runtime contract is `query_var === false`.
- Do not sync the unrelated Member Perks definitions as part of Product Contribution work.

## Safe next work

A separately approved unit can resolve and verify the remaining Template 772 and legacy Product relationship consumers before proposing removal. Any Product or Person data entry, migration, Bricks edit, snippet change, or runtime cleanup needs its own approved scope. Task 9 starts no Task 10 or unrelated journey work.
