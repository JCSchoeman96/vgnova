# Product Contribution and Reverse Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the role-aware canonical Product Contribution foundation, cut manual Product People authority from `product_people_override` to Product Contribution only after a fresh zero-value/zero-required-caller gate passes, and expose safe forward/reverse Product↔Person reads without duplicate reverse storage.

**Architecture:** ACF Pro Local JSON owns the non-public `product_contribution` CPT, `product_role` taxonomy, and contribution fields. Platform Core owns role seeding, endpoint validation, pair uniqueness, canonical writes, mode-aware Product People reads, reverse Person→Product reads, and optional Bricks adaptation. Event-derived Product membership remains live-owned by Event Participation; Product Contribution may annotate event-derived membership with Product-specific roles but can never create it.

**Tech Stack:** WordPress, WooCommerce Product posts, ACF Pro Local JSON, PHP 8+, Voelgoed Platform Core, existing standalone PHP test harness, Bricks filters, Novamira/WP-CLI runtime operations, Git worktrees.

**Spec:** `docs/superpowers/specs/2026-10-22-product-contribution-reverse-product-design.md`

## Global Constraints

- Planning baseline is merged `main` at `b0206640a676ac4b0233c274581abdc4ac8ea477`.
- Implementation must not start until this plan has been independently approved and merged. The execution handoff must name the exact then-current `origin/main` SHA. If `origin/main` contains unrelated commits after `b0206640a676ac4b0233c274581abdc4ac8ea477`, STOP for re-baselining rather than silently adapting.
- Execute only in an isolated branch/worktree, recommended `feat/product-contribution-reverse-product`, created from the approved then-current `origin/main` using the repository Superpowers worktree workflow.
- Task 1 is a binding preflight. If non-empty `product_people_override` values or any required external consumer are found, STOP before the manual-authority cutover or ACF-field retirement. Record the evidence; do not invent a migration.
- Historical Task 7C evidence is context only. It does not satisfy the fresh implementation-time `product_people_override` gate.
- ACF Pro is the editor/schema layer. New CPT/taxonomy/field definitions are source-controlled Local JSON; do not hand-build duplicate editors.
- New ACF keys are exactly `post_type_product_contribution`, `taxonomy_product_role`, and `group_vg_product_contribution_details` unless the actual ACF exporter proves a required generated key variation before implementation. Any variation must be documented before sync.
- Sync only the three named Product Contribution ACF definitions. Do not run unrestricted ACF JSON sync.
- `product_people_mode` remains explicit with `event`, `manual`, or blank/invalid fail-closed behavior.
- `produk_bron_event` remains the Product source/provenance Event concept and must not be renamed, copied, or conflated with `_event_name`.
- `_event_name` remains commerce/Event presentation state and is never part of Product People or reverse Product membership.
- Manual mode authority after cutover is published, valid Product Contribution records only.
- Event mode membership authority remains live Event Participation only. Product Contribution is optional role annotation and never creates event-derived membership.
- Event roles and Product roles are separate contextual facts. Never auto-map `event_role` to `product_role`.
- Initial Product Role vocabulary is exactly: `Aanbieder`/`aanbieder`, `Skrywer`/`skrywer`, `Instrukteur`/`instrukteur`, `Skepper`/`skepper`.
- Every published Product Contribution requires at least one approved `product_role`, in both manual and event mode.
- One non-retired Product × Person pair owns one Product Contribution; multiple Product roles belong on that single record.
- Duplicate prevention includes `publish`, `draft`, `pending`, `private`, and `future`; `trash` and `auto-draft` do not block pair reuse.
- Forward Product→People public results expose only valid published `person` endpoints. The Product itself may be non-published in editor/preview contexts.
- Reverse Person→Product public results require the Person and returned Product endpoints to be published.
- `product_contribution_display_order` controls Product-side Person ordering only. It never becomes Person-page Product ordering.
- Manual Product→People ordering is contribution display order ASC, contribution ID ASC. Event mode preserves Event Participation ordering.
- Reverse `products_for_person()` must be deterministic and deduplicated; use Product ID ASC as the repository default ordering. Presentation may later impose another explicit sort without changing relationship meaning.
- No ACF bidirectional storage, Person-side Product list, persistent reverse index, transient relationship cache, cron sync, or background synchronization.
- Request-scoped memoization is allowed only where source tests prove it does not change freshness semantics across requests.
- Never read `produk_medewerkers`, `event_medewerkers`, `medewerker`, or `_event_name` as a canonical fallback.
- Do not migrate or mutate protected legacy Product→Medewerker edges, Event 763 excluded edges, profiles 753–757, Template 773, component `6b463f`, Template 1127 host/guest state, or Medewerker routes.
- Do not disable or edit `9-acf-product-copy.php` or retire `produk_medewerkers`/`event_medewerkers` in this unit.
- The separate deployed ACF security/licensing concern remains out of scope. Do not alter vendor licensing code or expose credentials during this task.
- Platform Core source changes land under `site-code/voelgoed-platform-core/` first; runtime copies are deployment targets, not unique architecture sources.
- Use Novamira as the primary runtime interface when available; local filesystem/WP-CLI may be used only as a documented fallback where Novamira cannot perform the required operation safely.
- Direct SQL writes are prohibited. Read-only SQL may be used for inventory/fingerprinting when needed.
- Runtime identity must be re-proven before any write: expected WordPress root `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`, home `http://localhost:10039`, database `local`; do not operate on the separate localhost:10049 installation.
- Whole-`wp_options` hashing is not a sole hard gate. Protected architectural fingerprints are hard gates; volatile option writes, if observed, are accounted separately without claiming zero database writes.
- Temporary QA records must carry unique `[QA][AUTOMATED] Product Contribution` ownership markers and be hard-deleted with guarded cleanup. Do not select cleanup targets by title alone.
- Runtime QA must finish with no owned temporary Person, Product Contribution, Event Participation, or Product records remaining.
- Any stop condition from the spec or this plan is binding. Stop and report evidence rather than inventing fallback architecture.

## Review Focus

1. **Override-gate false negative:** non-empty `product_people_override` data or a required external caller must block cutover even if the historical audit was zero; source-only references being intentionally replaced are not external blockers.
2. **Mode-switch stale leakage:** a contribution valid in manual mode but invalid after switching to event mode, or an event annotation whose Person leaves the source Event, must stay stored if appropriate but disappear from canonical active reads.
3. **Duplicate/race safety:** two writers attempting the same Product × Person pair must not both succeed; the post-lock fresh check must be authoritative rather than request-cache dependent.
4. **Role integrity:** a published contribution with zero roles, an unknown role term, a wrong-taxonomy term, or a malformed role ID must fail without partially persisting canonical state.
5. **Reverse-query leakage:** manual + event-derived membership must union and deduplicate correctly while excluding unpublished Products, non-published Persons, invalid modes, stale event annotations, and every legacy fallback field.

---

# File Structure

## ACF Local JSON

Create:

```text
site-code/acf-json/post_type_product_contribution.json
site-code/acf-json/taxonomy_product_role.json
site-code/acf-json/group_vg_product_contribution_details.json
```

## Product domain

Create:

```text
site-code/voelgoed-platform-core/includes/Product/ProductContributionRepository.php
site-code/voelgoed-platform-core/includes/Product/ProductContributionPairLock.php
site-code/voelgoed-platform-core/includes/Product/ProductContributionService.php
site-code/voelgoed-platform-core/includes/Product/ProductContributionAdmin.php
site-code/voelgoed-platform-core/includes/Product/ProductRoleSeeder.php
```

Conditionally create only after Task 6 proves a safe Person context:

```text
site-code/voelgoed-platform-core/includes/Product/PersonProductsBricks.php
```

Modify:

```text
site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php
site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php
site-code/voelgoed-platform-core/integration.patch
site-code/voelgoed-platform-core/README.md
```

`ProductPeopleBricks.php` must remain API-stable and should require no behavioral rewrite beyond dependency loading if tests prove the existing `ProductPeopleResolver` contract remains intact.

## Source tests

Create:

```text
site-code/voelgoed-platform-core/tests/ProductContributionSchemaTest.php
site-code/voelgoed-platform-core/tests/ProductRoleSeederTest.php
site-code/voelgoed-platform-core/tests/ProductContributionRepositoryTest.php
site-code/voelgoed-platform-core/tests/ProductContributionPairLockTest.php
site-code/voelgoed-platform-core/tests/ProductContributionServiceTest.php
site-code/voelgoed-platform-core/tests/ProductContributionAdminTest.php
site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

Conditionally create with `PersonProductsBricks.php`:

```text
site-code/voelgoed-platform-core/tests/PersonProductsBricksTest.php
```

Modify:

```text
site-code/voelgoed-platform-core/tests/ProductPeopleFieldsTest.php
site-code/voelgoed-platform-core/tests/ProductPeopleResolverTest.php
site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php
site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Modify shared test bootstrap only if a genuinely generic WordPress/ACF primitive is missing:

```text
site-code/voelgoed-platform-core/tests/bootstrap.php
```

Do not add Product-specific domain behavior to the shared bootstrap.

## Runtime QA

Create:

```text
site-code/runtime-tests/product-contribution-qa.php
```

If the Person-side Bricks adapter is implemented, extend the same owned QA flow or create one narrowly scoped request helper only if required to prove Person context; do not create an unrelated second architecture path.

## Evidence and architecture docs

Create:

```text
docs/architecture/PRODUCT_CONTRIBUTION_PREFLIGHT.md
docs/architecture/PRODUCT_CONTRIBUTION_IMPLEMENTATION.md
docs/architecture/POST_PRODUCT_CONTRIBUTION_CURRENT_STATE.md
docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST.json
```

Modify only where current-state evidence changes:

```text
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md
docs/architecture/PERSON_CONSUMER_INVENTORY.csv
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Do not rewrite historical Task 5C/6A/6B/6C/7A/7B/7C evidence files.

---

# Canonical Interfaces

## `ProductContributionRepository`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Required constants:

```php
POST_TYPE = 'product_contribution'
PRODUCT_POST_TYPE = 'product'
PERSON_POST_TYPE = 'person'
PRODUCT_FIELD = 'product_contribution_product'
PERSON_FIELD = 'product_contribution_person'
ROLE_TAXONOMY = 'product_role'
DISPLAY_ORDER_FIELD = 'product_contribution_display_order'
SOURCE_EVENT_FIELD = 'produk_bron_event'
ACTIVE_STATUS = 'publish'
UNIQUENESS_STATUSES = ['publish', 'draft', 'pending', 'private', 'future']
SOURCE_MANUAL = 'manual'
SOURCE_EVENT = 'event'
```

Constructor:

```php
public function __construct(?EventParticipationRepository $event_repository = null);
```

Required public behavior:

```php
public function is_valid_product(int $product_id): bool;
public function is_valid_person(int $person_id): bool;
public function is_valid_product_fresh(int $product_id): bool;
public function is_valid_person_fresh(int $person_id): bool;

/** @return array{product_id:int,person_id:int}|null */
public function stored_pair_ids_fresh(int $contribution_id): ?array;

/** @return list<int> */
public function find_pair_ids(int $product_id, int $person_id, ?int $exclude_contribution_id = null): array;

/** @return list<int>|null */
public function find_pair_ids_fresh(int $product_id, int $person_id, ?int $exclude_contribution_id = null): ?array;

/** @return list<int> */
public function contributions_for_product(int $product_id): array;

/** @return list<int> */
public function contributions_for_person(int $person_id): array;

/** @return list<int> */
public function people_for_product(int $product_id): array;

/** @return list<int> */
public function products_for_person(int $person_id): array;

/**
 * @return list<array{
 *   product_id:int,
 *   person_id:int,
 *   product_role_ids:list<int>,
 *   display_order:int,
 *   membership_source:string
 * }>
 */
public function associations_for_person(int $person_id): array;
```

Behavioral contract:

```text
people_for_product(invalid/non-product)
→ []

mode absent/empty/invalid
→ []

mode manual
→ active published Product Contributions for Product
→ every contribution must have valid endpoints + >=1 approved role
→ keep only published Person endpoints
→ sort display_order ASC, contribution ID ASC
→ return Person IDs once

mode event
→ source Event via produk_bron_event
→ EventParticipationRepository canonical people_for_event()
→ keep only published Person endpoints
→ preserve Event Participation order
→ ignore Product Contribution as membership authority

products_for_person(non-published/non-person)
→ []

products_for_person(published Person)
→ published manual Products with an active valid contribution for Person
UNION
→ published event-mode Products whose current source Event is in EventParticipationRepository::events_for_person(Person)
→ deduplicate
→ Product ID ASC
```

`contributions_for_product()` and `contributions_for_person()` return only published, endpoint-valid, role-valid, current-mode-valid contribution records. For event-mode Products, a contribution is current-mode-valid only while its Person remains canonically derived from that Product's source Event. Invalid/unconfigured Products contribute no active records.

`associations_for_person()` uses the same canonical membership as `products_for_person()` and returns one association per Product. Manual associations take role IDs and display order from Product Contribution and `membership_source='manual'`. Event associations use `membership_source='event'`, Event Participation order for `display_order`, and role IDs from an optional currently valid matching Product Contribution; if no annotation exists, `product_role_ids=[]`.

Product role IDs in association views are sorted numerically for deterministic output; role order is not business data.

## `ProductContributionPairLock`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Public interface mirrors the Event Participation pair-lock pattern:

```php
public function acquire(int $product_id, int $person_id): bool;
public function release(int $product_id, int $person_id): void;
```

The lock key must be domain-specific so it cannot collide with Event Participation locks. Release only a lock acquired by this instance.

## `ProductContributionService`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Allowed canonical write statuses:

```php
['publish', 'draft', 'pending', 'private']
```

Constructor:

```php
public function __construct(
    ?ProductContributionRepository $repository = null,
    ?ProductContributionPairLock $pair_lock = null
);
```

Required public interface:

```php
public function validate_pair(int $product_id, int $person_id, ?int $exclude_contribution_id = null): true|\WP_Error;
public function validate_pair_fresh(int $product_id, int $person_id, ?int $exclude_contribution_id = null): true|\WP_Error;

/** @param list<int> $product_role_term_ids */
public function validate_publishable(
    int $product_id,
    int $person_id,
    array $product_role_term_ids,
    ?int $exclude_contribution_id = null,
    bool $fresh = false
): true|\WP_Error;

public function title_for_pair(int $product_id, int $person_id): string|\WP_Error;

/** @param list<int> $product_role_term_ids */
public function create(
    int $product_id,
    int $person_id,
    array $product_role_term_ids,
    int $display_order = 0,
    string $status = 'publish'
): int|\WP_Error;
```

`validate_publishable()` must verify Product and Person endpoints, uniqueness, exact approved `product_role` term IDs, at least one role, and mode semantics. For `event` Products it additionally requires the Person to be in `ProductContributionRepository::people_for_product($product_id)` as derived from Event Participation. For `manual` Products the contribution itself establishes membership. Blank/invalid Product mode is not publishable.

`create()` must validate inputs, acquire the Product×Person lock, perform a fresh post-lock recheck, derive `{Product title} — {Person title}`, write the three ACF values plus taxonomy terms, and rollback the newly created contribution on any partial-write failure. Draft/pending/private creation still requires valid Product/Person endpoints and valid supplied role terms if terms are supplied; only publication requires non-empty roles and current mode semantics.

## `ProductContributionAdmin`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Public interface:

```php
public function __construct(?ProductContributionService $service = null);
public function register(): void;
```

Responsibilities:

```text
ACF/admin validation uses the same service pair/publication rules
publishing requires Product + Person + >=1 role
publishing an event-mode annotation requires current Event-derived membership
updates exclude the current contribution from duplicate detection
administrative title is always re-derived from Product + Person
manual title edits never become business data
incomplete drafts may remain drafts but never appear in active repository reads
```

Do not invent a second validation rule set in admin code.

## `ProductRoleSeeder`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Exact vocabulary:

```php
Aanbieder => aanbieder
Skrywer => skrywer
Instrukteur => instrukteur
Skepper => skepper
```

Seeder behavior mirrors the proven Event Role seeder: taxonomy must exist; same-slug/wrong-name, same-name/wrong-slug, and unexpected-term conflicts stop before mutation; clean/partial expected vocabulary is completed idempotently; no term relationships are created.

## `ProductPeopleFields`

After Task 1 gate passes, retain:

```text
product_people_mode
```

with exact values `event` / `manual`, blank allowed, no default.

Retire the ACF field definition for:

```text
product_people_override
```

from the source-controlled Product People group. Do not delete historical database meta as part of field retirement. Update manual-mode instructions to direct editors to Product Contributions.

## `ProductPeopleResolver`

Preserve public API exactly:

```php
/** @return list<int> */
public function people_for_product(int $product_id): array;
```

New constructor:

```php
public function __construct(?ProductContributionRepository $repository = null);
```

The implementation delegates canonical Product People resolution to `ProductContributionRepository::people_for_product()`. `ProductPeopleBricks` continues calling the resolver unchanged.

Before changing this constructor, execution must rerun a source search for `new ProductPeopleResolver(` and prove there is no required caller passing the old `EventParticipationRepository` positional dependency. If such a caller appears, STOP and adapt the plan only after review rather than creating a silent type break.

## Optional `PersonProductsBricks`

Only implement after Task 6 proves a safe Person context.

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Markers:

```text
vg-person-products-query
vg-person-products-section
```

Public lifecycle:

```php
public function __construct(?ProductContributionRepository $repository = null);
public function register(): void;
```

The query adapter must resolve only a current `person` context, call `products_for_person()`, constrain `post_type=product` and `post_status=publish`, use `post__in` with `[0]` on empty, and preserve repository order with `orderby=post__in`. The marked section hides on frontend when empty but remains visible in builder contexts. No template mutation is authorized in this task.

---

# Tasks

### Task 1: Re-prove baseline, runtime identity, and the `product_people_override` gate

**Files:**
- Create: `docs/architecture/PRODUCT_CONTRIBUTION_PREFLIGHT.md`

**Interfaces:**
- Consumes: approved spec and merged baseline `b0206640a676ac4b0233c274581abdc4ac8ea477`.
- Produces: a binary `PASS` or `STOP` gate and exact evidence used by every later task.

- [ ] **Step 1: Create the isolated implementation worktree from the approved then-current `origin/main` and record identity**

Verify branch/worktree isolation, `git status --short`, `git rev-parse HEAD`, `git rev-parse origin/main`, and merge-base. Expected: clean worktree; HEAD equals the approved execution baseline; no drift.

- [ ] **Step 2: Re-prove the runtime target before any write**

Record WordPress root, `home_url`, effective port, database name/server, Local site UUID/data-directory identity where available, WordPress version, ACF availability, and deployed Platform Core path/hash. Expected target remains localhost:10039 / database `local`; any mismatch is STOP.

- [ ] **Step 3: Inventory all stored `product_people_override` values read-only**

Use WordPress APIs or read-only SQL to identify Products with the meta key and distinguish missing, empty, serialized-empty, and non-empty values. Record Product IDs and normalized state, never secret values.

Expected gate: zero non-empty canonical override values. Any non-empty value is STOP for a separate provenance/migration decision.

- [ ] **Step 4: Inventory every current caller/consumer of `product_people_override`**

Search source, active Platform Core runtime code, Bricks metadata, FluentSnippets, and relevant WordPress option/postmeta-backed definitions. Classify the known ProductPeopleFields/ProductPeopleResolver/tests as planned source replacements, not external blockers.

Expected gate: no required external/runtime consumer depends on `product_people_override`. Any required external consumer is STOP.

- [ ] **Step 5: Inventory current `product_people_mode` configuration**

Record Product IDs grouped by `event`, `manual`, blank/absent, and invalid non-empty values. Confirm any existing manual Product with an empty override currently resolves to `[]`; do not reinterpret it.

- [ ] **Step 6: Snapshot protected runtime state**

At minimum fingerprint protected posts/postmeta/term relationships for profiles 753–757, Event 763, Products 765/767/768, relevant ACF definitions, Product People configuration, Event Participation state, Bricks templates/components named by the spec, FluentSnippets 9/10, and current Person/Product Contribution counts.

Do not use whole `wp_options` hash as the sole hard gate.

- [ ] **Step 7: Write `PRODUCT_CONTRIBUTION_PREFLIGHT.md`**

The document must contain exact repo/runtime identity, override-value count, external-caller result, Product mode inventory, protected-state fingerprint summary, and one explicit line:

```text
PRODUCT_CONTRIBUTION_OVERRIDE_GATE=PASS
```

or

```text
PRODUCT_CONTRIBUTION_OVERRIDE_GATE=STOP
```

- [ ] **Step 8: If the gate is STOP, stop the implementation immediately**

Do not create ACF definitions, Product Contribution code, migration records, or field retirement changes. Push the evidence-only branch/PR for review.

- [ ] **Step 9: If the gate is PASS, commit the preflight evidence**

```bash
git add docs/architecture/PRODUCT_CONTRIBUTION_PREFLIGHT.md
git commit -m "docs: record Product Contribution preflight"
```

### Task 2: Add the ACF Product Contribution schema and controlled Product Role vocabulary

**Files:**
- Create: `site-code/acf-json/post_type_product_contribution.json`
- Create: `site-code/acf-json/taxonomy_product_role.json`
- Create: `site-code/acf-json/group_vg_product_contribution_details.json`
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductRoleSeeder.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionSchemaTest.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductRoleSeederTest.php`
- Create: `site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`

**Interfaces:**
- Consumes: ACF Local JSON conventions from Event Participation and the exact approved four-role vocabulary.
- Produces: non-public `product_contribution`, non-public `product_role`, four contribution fields, and idempotent role seeding.

- [ ] **Step 1: Write failing schema tests**

Tests must assert exact CPT visibility/route settings, taxonomy settings/attachment, field names/types/targets/return formats, `bidirectional_target=[]`, taxonomy save/load behavior, display-order min/default/step, and that no Person-side/Product-side duplicate relationship field is created.

- [ ] **Step 2: Run the new Product Contribution runner and verify RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

Expected: schema/seeder classes or JSON definitions are missing.

- [ ] **Step 3: Create the three ACF Local JSON definitions**

Use the Event Participation JSON shape as the structural precedent. Product/Person Post Object fields are single-value, required for publication, return IDs, and have no bidirectional target. Roles use `product_role`, multiple selection, save/load native terms, no add-term from the field. Display order is integer-like number, default `0`, min `0`, step `1`.

- [ ] **Step 4: Write failing ProductRoleSeeder tests**

Cover empty vocabulary, partial expected vocabulary, rerun idempotence, exact final vocabulary, same-slug/wrong-name conflict, same-name/wrong-slug conflict, unexpected term, missing taxonomy, and proof that no term relationships are created.

- [ ] **Step 5: Implement `ProductRoleSeeder` minimally**

Follow EventRoleSeeder conflict-first semantics. Never delete/rename unexpected terms automatically.

- [ ] **Step 6: Run schema + seeder tests GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

Expected: all Task 2 tests pass.

- [ ] **Step 7: Commit**

```bash
git add site-code/acf-json site-code/voelgoed-platform-core/includes/Product/ProductRoleSeeder.php site-code/voelgoed-platform-core/tests
git commit -m "feat: add Product Contribution schema"
```

### Task 3: Implement canonical contribution reads, pair identity, and reverse Product queries

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductContributionRepository.php`
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductContributionPairLock.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionRepositoryTest.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionPairLockTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`

**Interfaces:**
- Consumes: `EventParticipationRepository`, Product People mode constants, the Task 2 ACF field/taxonomy names.
- Produces: the repository and pair-lock interfaces defined above; later service/resolver/Bricks tasks depend on them.

- [ ] **Step 1: Write failing endpoint and pair-lookup tests**

Cover valid/non-trashed Product and Person endpoints, wrong post type, missing/trash endpoint, uniqueness statuses, exclude-current behavior, fresh cache-bypassing pair lookup, and deterministic numeric pair IDs.

- [ ] **Step 2: Write failing forward-read tests**

Cover manual-mode active contributions, required roles, invalid role-less published rows being excluded, display-order + ID ordering, duplicate Person defense, unpublished Person filtering, invalid mode fail-closed, and no reads from `produk_medewerkers`/`event_medewerkers`/`_event_name`.

- [ ] **Step 3: Write failing event-mode tests**

Prove `people_for_product()` delegates membership to Event Participation, preserves its order, ignores contribution membership, allows an event association with no contribution, and excludes stale event-mode contribution annotations from `contributions_for_*()` when the Person leaves the Event.

- [ ] **Step 4: Write failing reverse-query tests**

For a published Person, prove `products_for_person()` returns the union of manual contribution Products and event-derived Products, deduplicates a Product reachable through both test paths, excludes unpublished Products and invalid modes, never reads legacy Product/Medewerker fields, and returns Product IDs ASC.

- [ ] **Step 5: Write failing association-view tests**

Manual association: role IDs + contribution display order + `membership_source=manual`. Event association: Event Participation display order + optional Product role annotation + `membership_source=event`; no annotation returns empty role IDs. Verify one association per Product.

- [ ] **Step 6: Write failing pair-lock tests**

Cover domain-specific lock key, acquire/release, failed acquire, release-without-acquire no-op, and separate Product pairs not sharing a lock key.

- [ ] **Step 7: Run RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 8: Implement `ProductContributionRepository`**

Use WordPress/ACF APIs for canonical field reads and targeted fresh SQL only for the post-lock uniqueness recheck pattern already established by Event Participation. Keep event eligibility delegated to `EventParticipationRepository`; do not reimplement Event Participation publication rules.

- [ ] **Step 9: Implement `ProductContributionPairLock`**

Mirror the proven named-lock pattern with a Product Contribution-specific key namespace.

- [ ] **Step 10: Run GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 11: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductContributionRepository.php site-code/voelgoed-platform-core/includes/Product/ProductContributionPairLock.php site-code/voelgoed-platform-core/tests
git commit -m "feat: add Product Contribution repository"
```

### Task 4: Implement canonical writes and ACF/admin validation

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductContributionService.php`
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductContributionAdmin.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionServiceTest.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionAdminTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`

**Interfaces:**
- Consumes: Task 3 repository/pair lock and Task 2 role taxonomy.
- Produces: canonical service writes, shared publication validation, and ACF/admin enforcement.

- [ ] **Step 1: Write failing service validation tests**

Cover invalid Product/Person, duplicate pair, negative display order, invalid status, zero roles on publish, unknown/wrong-taxonomy role IDs, manual-mode publish success, invalid/blank-mode publish rejection, and event-mode Person-not-in-source-Event rejection.

- [ ] **Step 2: Add the Review Focus race test**

Simulate a stale pre-lock view followed by a fresh duplicate appearing before the post-lock check. Expected: create returns duplicate/error and does not create a second live pair.

- [ ] **Step 3: Write failing write/rollback/title tests**

Prove canonical title `{Product title} — {Person title}`, one row for multiple roles, ACF Product/Person/display-order writes, native taxonomy term assignment, and hard rollback of a newly created contribution after any field/term write failure.

- [ ] **Step 4: Write failing admin tests**

Prove ACF/admin publication uses the service rules, update excludes current ID, role-less publish is blocked, stale event annotation cannot publish, incomplete draft can remain inactive, and saved title is re-derived rather than editor-authored.

- [ ] **Step 5: Run RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 6: Implement `ProductContributionService`**

Keep one validation path. Acquire the pair lock before the authoritative fresh duplicate check. Do not auto-create Product roles or Event relationships.

- [ ] **Step 7: Implement `ProductContributionAdmin`**

Reuse service validation; do not duplicate pair/mode/role rules in UI-only code.

- [ ] **Step 8: Run GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 9: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductContributionService.php site-code/voelgoed-platform-core/includes/Product/ProductContributionAdmin.php site-code/voelgoed-platform-core/tests
git commit -m "feat: validate Product Contributions"
```

### Task 5: Cut manual Product People authority to Product Contribution and retire the override field definition

**Files:**
- Modify: `site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php`
- Modify: `site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php`
- Modify: `site-code/voelgoed-platform-core/tests/ProductPeopleFieldsTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/ProductPeopleResolverTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-people-tests.php`

**Interfaces:**
- Consumes: Task 1 PASS gate and Task 3 `ProductContributionRepository`.
- Produces: stable `ProductPeopleResolver::people_for_product()` behavior with Product Contribution as manual authority and Event Participation as event authority.

- [ ] **Step 1: Re-run the constructor/caller source search immediately before editing**

Search `new ProductPeopleResolver(` and any reflection/DI wiring. Expected: no required caller passes an `EventParticipationRepository` positional argument. If one does, STOP for plan review.

- [ ] **Step 2: Write failing ProductPeopleFields tests for override retirement**

Assert the Product People group contains only the mode field, still supports exact `event`/`manual` values with blank/no default, no longer registers `product_people_override`, and manual instructions direct editors to Product Contributions.

- [ ] **Step 3: Rewrite resolver tests RED for new manual authority**

Manual mode must resolve ordered contribution People; role-less/stale/draft contributions must not leak; empty contributions return `[]`; legacy override meta must be ignored even if populated in a test fixture. Event and invalid/unconfigured behavior must remain unchanged.

- [ ] **Step 4: Add explicit compatibility tests for ProductPeopleBricks**

Existing marked Product query receives the same ordered Person IDs from the resolver API; empty section behavior remains unchanged. Do not alter Template 124 or its semantic markers.

- [ ] **Step 5: Run Product People RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

- [ ] **Step 6: Modify `ProductPeopleFields` and `ProductPeopleResolver` minimally**

Remove the override field definition/constant from the source group after the Task 1 PASS gate. Do not delete stored meta. Delegate resolver behavior to `ProductContributionRepository::people_for_product()` while preserving the public method signature.

- [ ] **Step 7: Run Product People and Product Contribution GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 8: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php site-code/voelgoed-platform-core/tests
git commit -m "refactor: use Product Contributions for manual people"
```

### Task 6: Prove Person context and conditionally add the reverse Product Bricks adapter

**Files:**
- Conditionally create: `site-code/voelgoed-platform-core/includes/Product/PersonProductsBricks.php`
- Conditionally create: `site-code/voelgoed-platform-core/tests/PersonProductsBricksTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`
- Record outcome later in implementation/current-state docs.

**Interfaces:**
- Consumes: `ProductContributionRepository::products_for_person()`.
- Produces: either a proven safe `PersonProductsBricks` adapter or an explicit evidence-backed defer with no adapter code.

- [ ] **Step 1: Inspect current Person/Bricks context mechanisms read-only**

Confirm how existing adapters resolve Bricks preview context, queried object, `get_the_ID()`, and global `$post`. Confirm a `person` context can be type-checked without depending on Template 772 or Medewerker state.

- [ ] **Step 2: Decide the gate from evidence only**

PASS only if the adapter can require `post_type=person` in every accepted context and fail closed otherwise. If safe context cannot be proven, record `PERSON_PRODUCTS_BRICKS=DEFER` and skip Steps 3–7; the repository reverse API still completes this unit.

- [ ] **Step 3: If PASS, write failing `PersonProductsBricksTest`**

Cover Bricks preview Person, queried Person, fallback Person, non-Person context fail-closed, exact CSS token matching, published Product query vars, deterministic `post__in`, empty `[0]`, frontend section hiding, and builder visibility.

- [ ] **Step 4: Run RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 5: Implement `PersonProductsBricks` minimally**

Use only repository results. Do not read Product Contribution meta, taxonomy, Event Participation, or legacy fields in the adapter.

- [ ] **Step 6: Run GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 7: Commit only if adapter code exists**

```bash
git add site-code/voelgoed-platform-core/includes/Product/PersonProductsBricks.php site-code/voelgoed-platform-core/tests
git commit -m "feat: add Person reverse Product adapter"
```

### Task 7: Wire Platform Core and run the complete source regression matrix

**Files:**
- Modify: `site-code/voelgoed-platform-core/integration.patch`
- Modify: `site-code/voelgoed-platform-core/README.md`
- Modify test runners as required by the final class list.

**Interfaces:**
- Consumes: Tasks 2–6 classes.
- Produces: source bootstrap/lifecycle wiring ready for controlled runtime deployment.

- [ ] **Step 1: Write/extend integration tests RED**

Assert source bootstrap/integration patch includes Product Contribution repository, pair lock, service, admin, role seeder, and conditional PersonProductsBricks only if Task 6 PASS. Assert existing Event Participation and Product People registrations remain present.

- [ ] **Step 2: Update `integration.patch` and README minimally**

Register lifecycle components using existing Platform Core conventions. Do not refactor unrelated bootstrap code.

- [ ] **Step 3: Run the focused suites**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all pass.

- [ ] **Step 4: Run the Event regressions**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php
```

Expected: all pass; Product Contribution changes do not alter Event Participation semantics.

- [ ] **Step 5: Run the remaining relevant Platform Core/Person regressions named in README**

At minimum include the Person Foundation and content/editorial suites used by the current baseline. Record exact commands and pass counts rather than claiming an aggregate without evidence.

- [ ] **Step 6: Run lint/format/static integrity checks**

Run `php -l` on every created/modified PHP file, parse all three new JSON files, and run:

```bash
git diff --check
```

- [ ] **Step 7: Commit**

```bash
git add site-code/voelgoed-platform-core/integration.patch site-code/voelgoed-platform-core/README.md site-code/voelgoed-platform-core/tests
git commit -m "chore: wire Product Contribution foundation"
```

### Task 8: Deploy through targeted ACF/Platform Core operations and run guarded runtime QA

**Files:**
- Create: `site-code/runtime-tests/product-contribution-qa.php`
- Create: `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST.json` after QA from captured machine-readable evidence.

**Interfaces:**
- Consumes: fully green source branch and Task 1 protected-state snapshot.
- Produces: runtime proof of schema, roles, write validation, forward/reverse reads, live Event behavior, cleanup, and protected-state preservation.

- [ ] **Step 1: Re-prove runtime identity and protected pre-state immediately before deployment**

Repeat the Task 1 target checks. If runtime identity differs or protected architectural state changed materially, STOP before writes.

- [ ] **Step 2: Deploy Platform Core source using the established controlled procedure**

Update the active plugin copy from reviewed source; verify source/runtime hashes for all new/modified Platform Core files. Do not place unique code only in runtime.

- [ ] **Step 3: Target-sync only the three Product Contribution ACF definitions**

Dry-run/inspect each exact key first, then sync only:

```text
post_type_product_contribution
taxonomy_product_role
group_vg_product_contribution_details
```

Verify runtime ACF definitions match source after sync. Do not sync unrelated pending JSON.

- [ ] **Step 4: Seed and verify the exact Product Role vocabulary**

Run the controlled seeder. Verify exactly four terms with exact names/slugs and zero unexpected terms/relationships before QA creates owned relationships.

- [ ] **Step 5: Write `product-contribution-qa.php` with guarded ownership cleanup before executing it**

QA creates only uniquely marked temporary data and tracks exact inserted IDs immediately. Use one temporary published Product, temporary published Person, and where needed a temporary Event + Event Participation created through canonical services. Never use profiles 753–757, Event 763, or protected Products 765/767/768 as mutable QA fixtures.

- [ ] **Step 6: Prove manual-mode behavior**

Create manual Product Contribution through `ProductContributionService` with multiple Product roles and non-default display order. Prove `people_for_product()` returns the Person, `products_for_person()` returns the Product, association roles/source/order are correct, duplicate pair creation is rejected, role-less publication is rejected, and Product People ignores any test legacy/override noise.

- [ ] **Step 7: Prove event-mode behavior and no role inheritance**

Create canonical Event Participation for the temporary Person, configure Product event mode/source Event, prove Product People membership derives live from Event Participation, create a Product Contribution role annotation, prove Product role data is explicit and no Event role is auto-mapped.

- [ ] **Step 8: Prove stale event annotation behavior**

Retire/remove the temporary Event Participation while leaving the Product Contribution annotation in place long enough to query. Expected: Person disappears from Product People and reverse Product membership; stale annotation does not resurrect membership. Restore only owned QA state if needed for later assertions, never protected state.

- [ ] **Step 9: Prove reverse public filtering and deduplication**

Exercise manual + event paths, unpublished Product exclusion, non-Person context fail-closed behavior, and one Product ID per association. If `PersonProductsBricks` exists, prove its safe context and empty-section behavior against the temporary Person without editing a production Bricks template.

- [ ] **Step 10: Prove admin/title/schema behavior**

Verify no public Product Contribution route/archive, exact admin title derivation, ACF endpoint restrictions, taxonomy save/load, display order, and publication validation where practical through the supported admin/business layer.

- [ ] **Step 11: Run guarded cleanup in `finally` or equivalent**

Before deleting each temporary record, verify post type and exact ownership marker. Hard-delete owned Product Contributions, Event Participations, Person, Product, and any temporary Event. Verify no matching post/meta/term relationship remains. Keep the four approved Product Role terms.

- [ ] **Step 12: Compare protected post-state**

Protected posts/postmeta/term relationships, Bricks/ACF definitions outside the three targeted keys, FluentSnippets, legacy edges, and route state must match the protected pre-state. If volatile options changed, classify them separately; do not claim zero DB writes.

- [ ] **Step 13: Write the runtime manifest**

Record runtime identity, source/runtime hashes, ACF sync keys, role vocabulary, QA scenario results, created/deleted owned IDs, final Person/EventParticipation/ProductContribution counts, protected-state comparisons, and any runtime volatility disclosure. Never include secrets/license values.

- [ ] **Step 14: Commit runtime QA source and manifest**

```bash
git add site-code/runtime-tests/product-contribution-qa.php docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST.json
git commit -m "test: verify Product Contribution runtime"
```

### Task 9: Document implementation state, update decommission evidence, and open the implementation PR

**Files:**
- Create: `docs/architecture/PRODUCT_CONTRIBUTION_IMPLEMENTATION.md`
- Create: `docs/architecture/POST_PRODUCT_CONTRIBUTION_CURRENT_STATE.md`
- Modify as evidence requires: `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`
- Modify as evidence requires: `docs/architecture/PERSON_CONSUMER_INVENTORY.csv`
- Modify: `docs/architecture/IMPLEMENTATION_LEDGER.md`

**Interfaces:**
- Consumes: source/runtime test evidence and Task 1/8 manifests.
- Produces: review-ready evidence and an open, unmerged implementation PR.

- [ ] **Step 1: Write implementation documentation from evidence only**

Record schema ownership, exact Product roles, uniqueness, validation, mode authority, reverse query semantics, resolver compatibility, override-field retirement gate/outcome, optional Bricks outcome, runtime deployment, test commands/pass counts, cleanup, and protected-state result.

- [ ] **Step 2: Update the current-state snapshot**

State clearly what is now canonical and what remains legacy. Do not mark Template 772, Medewerker routes, `produk_medewerkers`, `event_medewerkers`, copy snippet 9, Template 773, or broadcast relationships decommissioned merely because Product Contribution exists.

- [ ] **Step 3: Update the inventory/register only where classification evidence changed**

Expected examples if implementation succeeds:

```text
canonical Person→Product read contract: AVAILABLE
product_people_override: RETIRED_FROM_CANONICAL_SOURCE only if Task 1 PASS and source field removed
legacy medewerker_products: still RETIRE_PENDING_DEPENDENCY until its actual consumer cutover
produk_medewerkers + snippet 9: still governed by existing dependency chain
```

Use the register's existing classification vocabulary; do not invent a new state if an existing one fits.

- [ ] **Step 4: Run final full verification from a clean worktree state**

Re-run focused Product Contribution/Product People suites, Event regressions, relevant Person/Platform Core suites, PHP lint, JSON parse, `git diff --check`, and `git status --short`. Record exact results.

- [ ] **Step 5: Review branch diff against the approved execution baseline**

Confirm no files outside the authorized schema/Product domain/tests/runtime QA/evidence set changed. Any unexpected runtime or vendor-plugin file is STOP for root-cause review.

- [ ] **Step 6: Push the feature branch and open a PR against `main`**

The PR must remain open and unmerged. Include exact base SHA, head SHA, Task 1 gate outcome, ACF sync keys, source/runtime test evidence, optional Bricks decision, runtime QA result, protected-state result, and explicit out-of-scope confirmation.

- [ ] **Step 7: Stop for independent review**

Do not merge, begin Template 772 migration, decommission legacy helpers, or start Course/Broadcast work.

---

# Final Verification Matrix

Before declaring the implementation branch review-ready, prove all of the following with recorded evidence:

```text
[ ] exact execution baseline and isolated worktree
[ ] Task 1 override gate PASS
[ ] three source-controlled ACF definitions only
[ ] no public Product Contribution route/archive
[ ] exact four Product Role terms
[ ] pair uniqueness across non-retired statuses
[ ] race-safe fresh post-lock duplicate rejection
[ ] every published contribution has >=1 approved Product Role
[ ] event annotation publish requires current event-derived membership
[ ] event roles never map to Product roles
[ ] manual Product People comes only from Product Contribution
[ ] event Product People remains live Event Participation-derived
[ ] unconfigured/invalid mode fails closed
[ ] existing ProductPeopleResolver public method remains stable
[ ] ProductPeopleBricks behavior remains stable
[ ] reverse Products = manual union event-derived, deduped
[ ] reverse output excludes unpublished Products and non-published Persons
[ ] reverse default ordering is Product ID ASC
[ ] rich association source/role/order semantics match spec
[ ] stale event annotation cannot resurrect membership
[ ] no legacy fallback reads
[ ] no ACF bidirectional storage
[ ] no persistent reverse cache/index/sync
[ ] product_people_override source field retired only after fresh gate
[ ] no historical override meta deleted automatically
[ ] optional PersonProductsBricks implemented only if safe context proved
[ ] targeted ACF sync only
[ ] controlled runtime Product Role seeding
[ ] owned runtime QA cleanup complete
[ ] protected legacy state unchanged
[ ] volatile options, if any, separately disclosed
[ ] Product Contribution focused suite green
[ ] Product People suite green
[ ] Event Participation suites green
[ ] relevant Person/Platform Core regressions green
[ ] PHP lint green
[ ] JSON parse green
[ ] git diff --check green
[ ] implementation PR open and unmerged
```

## Execution Handoff

After this plan is independently approved and merged, execution should use **subagent-driven development** if available because the work has several independently reviewable boundaries—schema/seeding, repository/query semantics, write/admin validation, resolver cutover, optional Bricks, and runtime QA—and a mistake in authority or migration gating could corrupt relationship semantics. If subagents are unavailable, use the native executing-plans workflow task-by-task with the same gates and independent whole-branch review before merge.
