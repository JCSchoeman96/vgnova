# Post-Task 7B current state

## CURRENT

- Product Single Template 124 uses `ProductPeopleBricks` and `ProductPeopleResolver::people_for_product()` to query ordered canonical `person` records.
- Empty canonical Product People results hide the complete marked frontend section. Bricks builder and builder-call contexts keep it editable.
- Product Person Card `dc8611` renders canonical Person data and its `/mense/` profile link.
- Source Event presentation is independent. Template 124 marker `vg-product-source-event-query` uses `ProductSourceEventBricks`, which reads `produk_bron_event` and validates a single `tc_events` post.
- Template 124 element count is 82. The normalized non-target projection, core record, and non-element metadata match the captured pre-write state.
- Existing Products remain unconfigured for canonical Product People. No existing Product relationship was migrated.
- WooCommerce Store Coming Soon remains enabled with `woocommerce_coming_soon=yes` and `woocommerce_store_pages_only=yes`. Anonymous Product requests remain subject to that store gate.
- PR #29 is open against `main` for independent review and has not been merged.

## TARGET NEXT

- Wait for the independent review of PR #29.
- Any later Product or Person migration requires its own approved scope, data map, caller audit, and runtime proof.
- Task 7C has not started.

## DEFER / KEEP_LEGACY

- `produk_medewerkers` and all existing values remain unchanged.
- `product_medewerkers` and `product_source_event` branches remain in the active FluentSnippets helper. Each has zero active Bricks callers after the cutover, but safe FluentSnippets edit access was unavailable. Both are `RETIRE_PENDING_ACCESS`.
- `9-acf-product-copy.php` remains unchanged. Template 772 element `vgms33` still uses `medewerker_products`, which reads `produk_medewerkers`; its ruling is `RETIRE_PENDING_DEPENDENCY`.
- `produk_bron_event` remains the independent Product source Event field. Existing values remain unchanged.
- Shared Medewerker Card `6b463f` and Event Participant Card `194bba` remain unchanged.
- Templates 772, 773, 774, and 1127 remain outside the Task 7B write scope.

## EXCLUDE

- Products 765, 767, and 768, Event 763, real legacy Product-to-Medewerker edges, and existing canonical Product People metadata were not migrated or edited.
- `_event_name` remains an independent commerce relationship. It was not read for Product People, changed, migrated, or repurposed.
- No legacy Product data migration, reverse Product relationship redesign, or Task 7C work was started.

See [Task 7B implementation evidence](PRODUCT_SINGLE_124_7B_IMPLEMENTATION.md) and [runtime manifest](PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json).
