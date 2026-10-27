# Voelgoed Platform Core source modules

This directory contains the canonical Platform Core domain modules in the
`vgnova` repository. The tracked root plugin at `voelgoed-platform-core/` is
the assembly base; this module owns the runtime mutation session guard,
`content_topic`, Event Participation, Product People, and Product Contribution
overlays. The tracked
`integration.patch` is an assembly input that adds the four overlay includes
and the Taxonomy, Event, and Product lifecycle registrations to a disposable
build tree. Runtime supplies a bootstrap dependency and has no lifecycle
registration.

Build the plugin from an exact committed revision with:

```sh
python3 site-code/bin/build-platform-core.py --ref <commit> --output dist
python3 site-code/bin/check-platform-core-build.py --ref <commit> --verify-output dist
```

Canonical archive builds require CPython 3.14.4. Repository-owned USTAR and
stored-DEFLATE encoders define the archive bytes; host `tarfile` and zlib are
used only to inspect the result. The verifier checks the existing output
against the exact Git commit, manifest, patch, and assembled source inventory
without rebuilding those files. See
`docs/architecture/PLATFORM_CORE_ASSEMBLY_CONTRACT_v1.4.0.md` for the format
and provenance contract.

The build writes `voelgoed-platform-core.tar.gz` and its provenance manifest.
Do not manually copy modules or apply `integration.patch` in the WordPress
plugin directory. A controlled runtime deployment consumes a verified
assembled artifact and remains a separate task. The active local WordPress
plugin directory is derived runtime state, not a build input.

Domain module changes must first exist in this source-controlled module;
bootstrap and dashboard changes belong in the tracked root plugin tree. The
builder assembles both source trees from one commit. The active local WordPress
plugin directory is a runtime deployment copy and must not contain unique
architecture changes that are absent from the repository. Do not create a
competing `content_topic` definition in ACF, FluentSnippets, or theme code.

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
```

The active site seeds terms through `wp vg-content-topic-setup`. That command
creates only missing canonical terms, stops on slug or parent conflicts, and
flushes rewrite rules once after successful setup.

## Task 2 local content topic proof

`wp vg-content-topic-migrate` prints a read-only report by default. The Task 2
local proof migration is now retired after editorial review. Its historical
record remains in `vgpc_content_topic_migration_local_v1`; the separate
`vgpc_content_topic_migration_local_v1_lifecycle` option records retirement.

While active, the command supported this controlled flow:

```sh
wp vg-content-topic-migrate --format=json
wp vg-content-topic-migrate --apply --fingerprint=<reviewed-fingerprint> --format=json
wp vg-content-topic-migrate --format=json
wp vg-content-topic-migrate --rollback --format=json
```

The explicit retirement command is:

```sh
wp vg-content-topic-migrate --retire --fingerprint=<reviewed-fingerprint> --format=json
```

Retirement requires the Task 2 record, its reviewed fingerprint, and all seven
recorded canonical relationships to match the original manifest. It writes a
separate lifecycle marker and leaves the historical record unchanged. Repeating
retirement is safe. After retirement, dry run remains read-only and reports
`lifecycle: retired`; apply and rollback both fail with
`vgpc_topic_migration_retired` because editorial classification supersedes
rollback-to-seed behavior. `MIGRATION_SEED` continues to derive from the
historical record and current Direct Topics, so retirement alone does not mark
the five unreviewed objects as reviewed.

The apply command required the source fingerprint from the reviewed dry run.
If the legacy terms, assignments, canonical tree, or object types changed
before apply, it stopped without writing. The migration recorded only
canonical relationships it created. Rollback removed those recorded
relationships and left legacy category data and unrelated topic assignments in place.

## Event Participation foundation

The source contract, runtime state, legacy boundary, and post-merge Local JSON
stabilization gate are documented in
[`docs/architecture/EVENT_PARTICIPATION_IMPLEMENTATION.md`](../../docs/architecture/EVENT_PARTICIPATION_IMPLEMENTATION.md).

The Event Participation implementation lives in `includes/Event/`:

- `EventParticipationRepository` reads canonical relationships and rechecks candidate pairs with fresh post data plus ACF values while a write lock is held.
- `EventParticipationPairLock` serializes writes for the same Event × Person pair and concurrent edits to one existing participation. Scoped operations release only locks they acquire, in reverse order.
- `EventParticipationService` validates and creates participations under the pair lock. It rejects `future` status because creation accepts no scheduled publication date.
- `EventParticipationAdmin` locks existing participations before reading stored endpoints, rechecks pairs before writes, allows retirement with invalid endpoints, and derives titles under a scoped lock after refreshing the participation endpoints and their post caches.
- `EventRoleSeeder` seeds the approved `event_role` vocabulary on request.
- `EventParticipationBricks` supplies ordered repository IDs to the marked Event Single 774 participant query and hides the marked section on the frontend when no canonical participation exists. It has no `event_medewerkers` fallback.

The Bricks Posts query uses the semantic class token `vg-event-participation-query`. The participant section uses `vg-event-participants-section`. Platform Core changes behavior only for those exact markers.

Run the focused Bricks adapter checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
```

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

Seed the approved role terms through WP-CLI after the ACF taxonomy is registered:

```sh
wp vg-event-role-setup
```

Runtime deployment is separate from changes to this source directory. The
assembled artifact contains the tracked Event modules and their bootstrap
wiring. Do not manually apply `integration.patch` or copy module files into
the runtime plugin directory. Deployment and any targeted ACF JSON sync remain
separate controlled work.

The guarded staging check is `site-code/runtime-tests/event-participation-qa.php`.
Run it inside the loaded WordPress runtime with `wp eval-file`; it creates and
removes only uniquely owned temporary Person and participation records. The
setup failure boundary has a standalone regression check at
`site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php`.
Created-record ownership and failure-safe cleanup have focused regressions at
`site-code/runtime-tests/tests/run-event-participation-qa-cleanup-tests.php`.

## Simple Person relationships for Articles and Courses

Article and Masteriyo Course ACF fields are the authoritative relationship
source. The supported fields are `primary_author`, `additional_authors`,
`expert_contributors`, and `reviewed_by` on Articles, and `lead_instructor`,
`additional_instructors`, and `experts` on Courses. Platform Core validates
those values and projects them into the disposable
`vg_person_relationship_index` table for reverse Person reads. The index can be
rebuilt from source fields; it does not own publication access or membership
entitlement. Person records do not store copied Article or Course lists.

The source module classes are assembled only through
`site-code/voelgoed-platform-core/integration.patch`. That patch loads all
Person relationship classes and registers the editor lifecycle and WP-CLI
component. It does not install the index schema at plugin load. Schema setup,
full rebuild, and repair require the guarded maintenance commands:

```sh
wp vg-person-relationship-index setup
wp vg-person-relationship-index rebuild
wp vg-person-relationship-index reconcile --format=json
wp vg-person-relationship-index reconcile --repair --format=json
```

Run the source-only relationship tests with:

```sh
php site-code/voelgoed-platform-core/tests/run-person-relationship-tests.php
```

These checks do not access or mutate the Local WordPress runtime. Runtime
setup, rebuild, reconciliation repair, and deployment are separate guarded
operations. Do not derive Course people from WordPress or Masteriyo authors,
Woo Products, Product Contribution, Topics, FCM, legacy `medewerker` records,
or QA fixtures.

## Product People, Product Contribution, and Bricks adapters

The Product People and Product Contribution source classes and their Bricks
adapters live in `includes/Product/`.

Product People membership follows `product_people_mode`:

- `manual`: published Product Contributions are the canonical Product ↔ Person
  authority.
- `event`: Event Participation through `produk_bron_event` remains the live
  membership authority. Product Contribution may add an optional Product Role
  annotation to a Person who currently belongs to that source Event. A
  contribution alone never creates event-mode membership.
- Blank or invalid mode resolves to an empty list.

`ProductPeopleFields` owns only `product_people_mode`; it no longer defines
`product_people_override`. `ProductPeopleResolver` preserves the
`people_for_product(int $product_id): array` API and delegates canonical reads
to Product Contribution or Event Participation according to the mode. It does
not read `produk_medewerkers`, `event_medewerkers`, `medewerker`, or
`_event_name`.

Product Contribution classes have these responsibilities:

- `ProductRoleVocabulary` defines the approved Product Role vocabulary.
- `ProductContributionRepository` reads canonical Product ↔ Person membership
  and rich associations in either direction.
- `ProductContributionPairLock` serializes writes for a Product × Person pair
  and edits to an existing contribution.
- `ProductContributionService` validates and writes canonical contributions.
- `ProductContributionAdmin` applies admin and ACF lifecycle validation and
  maintains derived contribution titles.
- `ProductRoleSeeder` exposes the controlled `wp vg-product-role-setup`
  command after the ACF taxonomy is registered.
- `PersonProductsBricks` adapts the Person-side Product query. It responds only
  to `vg-person-products-query` and `vg-person-products-section` and delegates
  Product IDs exclusively to
  `ProductContributionRepository::products_for_person()`.

`ProductPeopleBricks` adapts only the exact `vg-product-people-query` and
`vg-product-people-section` markers. It delegates membership to
`ProductPeopleResolver` and preserves its order in the Person query. It hides
an empty frontend section while keeping it editable in builder contexts.
`ProductSourceEventBricks` adapts only the exact
`vg-product-source-event-query` marker. It independently reads and validates
`produk_bron_event`; it does not call `ProductPeopleResolver` or inspect
Product People mode or WooCommerce's `_event_name`.

Historical `product_people_override` database meta is not automatically
deleted. Canonical Product People resolution ignores it.

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

`integration.patch` supplies the bootstrap additions during deterministic
assembly. Runtime deployment remains a separate controlled step. Task 8 owns
the targeted Product Contribution ACF Local JSON sync and Product Role seeding
command. This source wiring task does not run `wp vg-product-role-setup`.
