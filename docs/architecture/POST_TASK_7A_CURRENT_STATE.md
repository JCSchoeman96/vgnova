# Post-Task 7A current state

Snapshot taken after the guarded runtime QA and cleanup of the Product People foundation. The detailed implementation record is [PRODUCT_PEOPLE_IMPLEMENTATION.md](PRODUCT_PEOPLE_IMPLEMENTATION.md); the machine-readable evidence is [PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json](PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json).

## CURRENT

- Canonical Product People fields are registered in ACF for Products: `product_people_mode` (`event` or `manual`, blank allowed) and `product_people_override` (Person relationship, shown only in manual mode).
- `ProductPeopleResolver::people_for_product()` is the canonical read authority for Product People. It is read-only, uses the explicit mode only, and returns published Persons.
- Existing Products are still unconfigured: no canonical mode or override meta exists on any Product.
- Existing Product consumers remain legacy. Product Single 124 still reads `produk_medewerkers` and `produk_bron_event` through its helper modes.
- Legacy relationships are retained: `produk_medewerkers` (KEEP_LEGACY), the FluentSnippet `9-acf-product-copy.php` (KEEP_LEGACY), and the helper modes `product_medewerkers`, `product_source_event`, and `event_products` (KEEP_LEGACY or REPLACE_LATER).
- `_event_name` remains a separate Tickera/commerce relationship and is not treated as Product People provenance.
- Final persistent counts: Person = 0, `event_participation` = 0, owned QA residue = 0.

## TARGET NEXT

**Task 7B: Product Single 124 consumer audit and cutover.** Retarget the Product People consumers to `ProductPeopleResolver`, verify the Product Single 124 presentation against the resolver, and keep every legacy relationship in place until its removal gate passes. Task 7B must start from this snapshot and must not reuse Task 7A runtime QA evidence as proof for consumer behaviour.

## DEFER / KEEP_LEGACY

| Item | Decision | Reason |
|---|---|---|
| Template 772 reverse product consumer (`medewerker_products`) | DEFER | Reverse lookup on `produk_medewerkers`; Person profile migration phase |
| FluentSnippet helper retirement (`10-vg-bricks-medewerker-queries.php`) | DEFER | Five active modes still serve Template 772 and Product Single 124; the FluentSnippets editor path was unavailable in an earlier phase |
| `9-acf-product-copy.php` retirement | DEFER | Still copies legacy event people into an empty `produk_medewerkers`; removal requires the Task 7B cutover |
| `produk_medewerkers` field | KEEP_LEGACY | Three legacy product-person edges remain as provenance; no migration approved |
| `produk_bron_event` values | KEEP | Approved source/provenance Event concept; existing values were not migrated |

## EXCLUDE

- Existing Product to Medewerker edges classified `EXCLUDE` by Task 5C (products 767 and 768, profiles 753, 757) remain `EXCLUDE`.
- Event 763 legacy edges to profiles 753 and 757 remain `EXCLUDE` and unchanged.
- No Product People data was created for any existing Product during Task 7A.

## Protected state

Compared exactly before and after the Task 7A runtime QA (pre-deployment snapshot against the final snapshot):

| Surface | Result |
|---|---|
| Products 765, 767, 768 (record digests and ACF values) | equal |
| Event 763 (record digest and `event_medewerkers` = [753, 757]) | equal |
| Medewerker profiles 753 to 757 | equal |
| Bricks templates 124, 772, 774 | equal |
| Bricks components `6b463f`, `194bba` | equal |
| Canonical Product People meta on existing Products | none before or after |
| `_event_name` rows on Products | equal |
| `tc_api_keys` (18 pre-existing records) | exact equality |
| Post-type and status counts | equal |
| Legacy snippet hashes (`9-acf-product-copy.php`, `10-vg-bricks-medewerker-queries.php`) | equal |

## Final counts

| Count | Value |
|---|---|
| Person | 0 |
| `event_participation` | 0 |
| Owned QA residue | 0 |
| Run token in posts, post meta, or options after QA | 0 |
| Existing Products carrying canonical Product People meta | 0 |
