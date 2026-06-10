# Medewerkers — Admin workflow

How editors connect people, events, and WooCommerce products on Voelgoed using the **Medewerker** content model.

## What is a medewerker?

One reusable profile for any public contributor: mentor, speaker, host, artist, guest, writer, facilitator, or similar. Roles and groups use taxonomies — not separate CPTs per role.

**Public URLs**

- Archive: `/medewerkers/`
- Profile: `/medewerkers/{slug}/`

There is no `mentor` post type and no `/mentor/` URL structure on this site.

## Editor workflow

1. **Create each public person once** under **Medewerkers** (title = full name, featured image, excerpt/editor for bio).
2. **Assign one or more Medewerkerrolle** — for example Mentor, Spreker, Aanbieder.
3. **Assign a Medewerkergroep** when relevant — for example **Voelgoed Mentor**, **Event Spreker**.
4. **Optional profile fields** (*Medewerker Besonderhede*): `medewerker_titel`, `medewerker_kort_bio`, `medewerker_profiel_status`.
5. **On events** (`tc_events`), select people in **Event Medewerkers** (`event_medewerkers`). Use Medewerkerrolle for speaker vs mentor vs host — do not add separate ACF fields per role.
6. **After the event**, create a WooCommerce product for the recording.
7. **On the product**, set **Bron Event** (`produk_bron_event`).
8. **On the product**, set **Produk Medewerkers** (`produk_medewerkers`). If empty on save, FluentSnippets *[ACF] Product — Copy event medewerkers* (`9-acf-product-copy.php`) copies from the event’s `event_medewerkers` (never overwrites a manual selection).

## Future Bricks usage

| Page | Data to query |
|------|----------------|
| Event single | `event_medewerkers`; products where `produk_bron_event` = current event |
| Medewerker single | Profile; events/products linked via relationship fields |
| Product single | `produk_bron_event`, `produk_medewerkers` |

## Paid Memberships Pro

Do **not** attach PMP rules to the `medewerker` CPT. Gate access on articles, courses, products, and events.

## Active model summary

| Piece | Key / field |
|-------|-------------|
| CPT | `medewerker` |
| Taxonomies | `medewerker_rol`, `medewerker_groep` |
| Profile ACF | `medewerker_titel`, `medewerker_kort_bio`, `medewerker_profiel_status` |
| Event ACF | `event_medewerkers` → `medewerker` |
| Product ACF | `produk_bron_event`, `produk_medewerkers` |
