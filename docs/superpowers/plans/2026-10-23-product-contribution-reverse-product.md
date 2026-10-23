# Product Contribution and Reverse Product Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the role-aware canonical Product Contribution foundation, cut manual Product People authority from `product_people_override` to Product Contribution only after a fresh zero-value/zero-required-caller gate passes, and expose safe forward/reverse Product↔Person reads without duplicate reverse storage.

**Architecture:** ACF Pro Local JSON owns the non-public `product_contribution` CPT, `product_role` taxonomy, and contribution fields. Platform Core owns role seeding, endpoint validation, pair uniqueness, canonical writes, mode-aware Product People reads, rich Product↔Person association reads, reverse Person→Product reads, and optional Bricks adaptation. Event-derived Product membership remains live-owned by Event Participation; Product Contribution may annotate event-derived membership with Product-specific roles but can never create it.

**Tech Stack:** WordPress, WooCommerce Product posts, ACF Pro Local JSON, PHP 8+, Voelgoed Platform Core, existing standalone PHP test harness, Bricks filters, Novamira/WP-CLI runtime operations, Git worktrees.

**Spec:** `docs/superpowers/specs/2026-10-22-product-contribution-reverse-product-design.md`

## Global Constraints

- Planning baseline is merged `main` at `b0206640a676ac4b0233c274581abdc4ac8ea477`.
- Implementation must not start until this plan has been independently approved and merged. The execution handoff must name the exact then-current `origin/main` SHA. If `origin/main` contains unrelated commits after `b0206640a676ac4b0233c274581abdc4ac8ea477`, STOP for re-baselining rather than silently adapting.
- Execute only in an isolated branch/worktree, recommended `feat/product-contribution-reverse-product`, created from the approved then-current `origin/main` using the repository Superpowers worktree workflow.
- Task 1 is a binding preflight. If non-empty `product_people_override` values or any required external consumer are found, STOP before the manual-authority cutover or ACF-field retirement. Record the evidence; do not invent a migration.
- Historical Task 7C evidence is context only. It does not satisfy the fresh implementation-time `product_people_override` gate.
- ACF Pro is the editor/schema layer. New CPT/taxonomy/field definitions are source-controlled Local JSON; do not hand-build duplicate editors.
- New ACF keys are exactly `post_type_product_contribution`, `taxonomy_product_role`, and `group_vg_product_contribution_details`, following the existing Event Participation Local JSON convention.
- Sync only the three named Product Contribution ACF definitions. Do not run unrestricted ACF JSON sync.
- The Product Contribution CPT is administrative only: `public=false`, `publicly_queryable=false`, `exclude_from_search=true`, `show_ui=true`, `show_in_rest=true`, no archive/rewrite/query var, and native support limited to `title`.
- `product_people_mode` remains explicit with `event`, `manual`, or blank/invalid fail-closed behavior.
- `produk_bron_event` remains the Product source/provenance Event concept and must not be renamed, copied, or conflated with `_event_name`.
- `_event_name` remains commerce/Event presentation state and is never part of Product People or reverse Product membership.
- Manual mode authority after cutover is published, valid Product Contribution records only.
- Event mode membership authority remains live Event Participation only. Product Contribution is optional role annotation and never creates event-derived membership.
- Event roles and Product roles are separate contextual facts. Never auto-map `event_role` to `product_role`.
- Initial Product Role vocabulary is exactly: `Aanbieder`/`aanbieder`, `Skrywer`/`skrywer`, `Instrukteur`/`instrukteur`, `Skepper`/`skepper`.
- `ProductRoleVocabulary` is the only source for the approved Product Role labels and slugs. The seeder and every publication validator must read that source; they must not keep separate copies of the four-role map.
- Every published Product Contribution requires at least one approved `product_role`, in both manual and event mode.
- ACF field-level `required` flags for Product, Person, and Roles remain `0` so an incomplete draft can be saved; `ProductContributionService`/`ProductContributionAdmin` enforce the publication requirements. Do not use ACF's unconditional required flag in a way that makes incomplete drafts impossible.
- One non-retired Product × Person pair owns one Product Contribution; multiple Product roles belong on that single record.
- Duplicate prevention includes `publish`, `draft`, `pending`, `private`, and `future`; `trash` and `auto-draft` do not block pair reuse.
- Every existing-row save locks that contribution row before reading stored endpoints, then locks the resolved Product × Person pair before the authoritative fresh duplicate check. Trash retirement takes the row lock but bypasses endpoint, role, mode, and Event-membership validation.
- Contribution titles always derive from the current Product and Person titles. Product or Person title changes resync related non-retired contributions under contribution-row locks.
- Forward Product→People public results expose only valid published `person` endpoints. The Product itself may be non-published in editor/preview contexts.
- Reverse Person→Product public results require the Person and returned Product endpoints to be published.
- `product_contribution_display_order` controls Product-side Person ordering only. It never becomes Person-page Product ordering.
- Manual Product→People ordering is contribution display order ASC, contribution ID ASC. Event mode preserves Event Participation ordering.
- Reverse `products_for_person()` must be deterministic and deduplicated; use Product ID ASC as the repository default ordering. Presentation may later impose another explicit sort without changing relationship meaning.
- Rich association reads must work in both directions: Product→Person and Person→Product. They are in-memory array views, never another stored relationship.
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
- Runtime QA must finish with no owned temporary Person, Product Contribution, Event Participation, Product, or Event records remaining.
- Any stop condition from the spec or this plan is binding. Stop and report evidence rather than inventing fallback architecture.

## Review Focus

1. **Override-gate false negative:** non-empty `product_people_override` data or a required external caller must block cutover even if the historical audit was zero; source-only references being intentionally replaced are not external blockers.
2. **Mode-switch stale leakage:** a contribution valid in manual mode but invalid after switching to event mode, or an event annotation whose Person leaves the source Event, must stay stored if appropriate but disappear from canonical active reads.
3. **Duplicate/race safety:** two create writers and two existing rows moving toward the same Product × Person pair must not both succeed; the post-lock fresh check must be authoritative rather than request-cache dependent.
4. **Role integrity:** a published contribution with zero roles, an unknown role term, an unapproved fifth term in the correct taxonomy, a wrong-taxonomy term, or a malformed role ID must fail without partially persisting canonical state.
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
site-code/voelgoed-platform-core/includes/Product/ProductRoleVocabulary.php
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
site-code/voelgoed-platform-core/includes/Event/EventParticipationRepository.php
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
site-code/voelgoed-platform-core/tests/ProductRoleVocabularyTest.php
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
site-code/voelgoed-platform-core/tests/EventParticipationRepositoryTest.php
site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
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

/** @return list<int> Non-retired rows for title resync; no publication or role filtering. */
public function non_retired_contributions_for_product(int $product_id): array;

/** @return list<int> Non-retired rows for title resync; no publication or role filtering. */
public function non_retired_contributions_for_person(int $person_id): array;

/**
 * @return list<array{
 *   product_id:int,
 *   person_id:int,
 *   product_role_ids:list<int>,
 *   display_order:int,
 *   membership_source:string
 * }>
 */
public function associations_for_product(int $product_id): array;

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
→ defensive dedupe of candidate Product IDs
→ Product ID ASC
```

`contributions_for_product()` and `contributions_for_person()` return only published, endpoint-valid, role-valid, current-mode-valid contribution records. For event-mode Products, a contribution is current-mode-valid only while its Person remains canonically derived from that Product's source Event. Invalid/unconfigured Products contribute no active records.

`associations_for_product()` returns one canonical association per Person in Product-side order. Manual associations use contribution roles/order and `membership_source='manual'`. Event associations preserve Event Participation order, use `membership_source='event'`, and add role IDs only from an optional currently valid matching Product Contribution; a Person with no annotation still has an event association with `product_role_ids=[]`.

`associations_for_person()` uses the same canonical membership as `products_for_person()` and returns one association per Product in Product ID ASC. Manual associations take role IDs and display order from Product Contribution. Event associations use Event Participation order for the association's Product-side `display_order` and role IDs from an optional currently valid matching Product Contribution.

Product role IDs in association views are sorted numerically for deterministic output; role order is not business data.

For event-derived association order, add this method to `EventParticipationRepository` and cover it in its repository tests:

```php
public function display_order_for_pair(int $event_id, int $person_id): ?int;
```

Product Contribution must call that method. It must not query the Event Participation post type or read `event_participation_display_order` directly. Return no event association if the canonical read no longer finds an active pair.

The two `non_retired_contributions_for_*` methods return matching contribution IDs in ascending ID order for `publish`, `draft`, `pending`, `private`, and `future` only. They do not filter by role validity, Product mode, or current Event membership. Title synchronization uses these methods, then checks the stored endpoints fresh under each row lock.

## `ProductContributionPairLock`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Public interface mirrors the Event Participation pair-lock pattern:

```php
public function acquire(int $product_id, int $person_id): bool;
public function acquire_contribution(int $contribution_id): bool;
public function with_scoped_locks(callable $operation): mixed;
public function release(int $product_id, int $person_id): bool;
public function release_contribution(int $contribution_id): bool;
public function release_all(): void;
```

Use a domain-specific key namespace that cannot collide with Event Participation locks. Track locks acquired by this instance. Scoped release must release only locks acquired inside that scope, in reverse acquisition order. Release-all must release every lock still held by this instance. For an existing contribution save, acquire the contribution-row lock first, read stored endpoints fresh, then acquire the endpoint-pair lock. New contribution creation acquires the endpoint-pair lock. Never read stored endpoints before acquiring the row lock.

## `ProductContributionService`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Allowed canonical write statuses:

```php
['publish', 'draft', 'pending', 'private', 'future']
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

`validate_publishable()` must verify Product and Person endpoints, uniqueness, at least one role, and mode semantics. It must call `ProductRoleVocabulary::is_approved_term_id()` for each selected term ID. A term in the correct taxonomy is still rejected unless its taxonomy, slug, and label match the shared approved vocabulary. For `event` Products it additionally requires the Person to be in `ProductContributionRepository::people_for_product($product_id)` as derived from Event Participation. For `manual` Products the contribution itself establishes membership. Blank/invalid Product mode is not publishable.

`create()` must validate inputs, acquire the Product×Person lock, perform a fresh post-lock recheck, derive `{Product title} — {Person title}`, write the three ACF values plus taxonomy terms, and rollback the newly created contribution on any partial-write failure. Draft/pending/private/future creation still requires valid Product/Person endpoints and valid supplied role terms if terms are supplied; only publication requires non-empty roles and current mode semantics.

## Contribution lifecycle matrix

Apply these rules to every create, update, status transition, and restore. The admin path must enforce them before WordPress persists the post row or ACF fields.

| Target status | Active public read | Reserves Product × Person pair | Save and transition guards | Side effects |
|---|---|---|---|---|
| `publish` | Yes, only while endpoints, roles, Product mode, and any event membership remain valid | Yes | Require a valid non-trashed Product and Person, at least one approved Product Role, a unique pair after the locks, exact `event` or `manual` mode, and current Event Participation membership in event mode. | Save canonical fields and roles. Derive the title from the current Product and Person titles. |
| `draft` | No | Yes when both endpoint IDs form a pair | Incomplete drafts are allowed. When both endpoints are supplied, require valid endpoint types and IDs, approved role IDs when roles are supplied, and a unique pair after the locks. Do not require publish-only role cardinality or current event membership. | Save the valid submitted fields. Derive the title when both endpoints are present. |
| `pending`, `private`, `future` | No | Yes | Require a complete valid Product × Person pair and approved role IDs when roles are supplied. Check pair uniqueness after the locks. Do not apply publish-only role cardinality or current event membership. | Save canonical fields and roles. Derive the title from the endpoints. |
| `trash` | No | No | Retirement from any non-trash status must succeed after acquiring the contribution-row lock, even if either endpoint is missing, trashed, or the wrong type, Product mode is invalid, Product Roles are invalid, or Event membership is stale. Skip endpoint, pair, role, mode, and publication validation. | Set the status to trash. Keep fields and taxonomy terms intact for recovery. |
| `auto-draft` | No | No | Treat as a WordPress editing placeholder, not a contribution. Do not reserve a pair or require contribution fields. | No canonical relationship writes. |

A restore from trash to any non-trash status runs the target status guards and fresh duplicate check before WordPress restores the row. A trashed row stays retired and absent from active reads and pair uniqueness until a valid restore succeeds. A save that moves a published contribution to another non-trash status removes it from active reads but keeps its pair reserved. Any status change to trash uses the retirement exception above.

For an existing contribution, acquire its row lock before reading stored endpoints. Resolve submitted endpoints and any omitted stored endpoint values under that lock. If the target is not trash and a complete pair exists, acquire that pair lock, then run the fresh duplicate check excluding the current contribution. A lock or fresh-read failure must fail the save closed. Keep locks through the WordPress/ACF save and release scoped locks after title-sync operations; release remaining request locks on shutdown. New-record creation uses the pair lock and fresh duplicate check, without a row lock.

## `ProductContributionAdmin`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Public interface:

```php
public function __construct(
    ?ProductContributionRepository $repository = null,
    ?ProductContributionService $service = null,
    ?ProductContributionPairLock $pair_lock = null
);
public function register(): void;
```

Responsibilities:

```text
ACF/admin validation uses the same service pair/publication rules
apply the lifecycle matrix to every save and transition
lock an existing contribution row before reading stored endpoints
after fresh endpoint resolution, acquire the Product × Person lock before checking uniqueness
updates exclude the current contribution from duplicate detection
retirement to trash bypasses invalid endpoint, role, mode, and Event-membership checks
ACF field validation never blocks a submitted transition to trash
manual title edits never become business data
incomplete drafts remain inactive and never appear in active repository reads
Product and Person title changes resync related non-retired contribution titles under row locks
unchanged Product or Person titles do not trigger contribution writes
trashed and auto-draft contributions are excluded from title resync
```

Use the service for endpoint, role, pair, and publication validation. The admin class owns the save choreography and the trash-retirement exception, not a duplicate set of domain validation rules.

Register `wp_insert_post_data`, `acf/save_post`, `post_updated`, and `shutdown` hooks. Preliminary ACF field checks may validate submitted values for editor feedback, but they are advisory and must return success for a submitted transition to trash. In `wp_insert_post_data`, acquire the contribution-row lock first. If the target status is trash, return the post data without endpoint, role, mode, or membership checks. Otherwise, call `stored_pair_ids_fresh()` only after taking the row lock, resolve submitted and omitted stored endpoints, acquire the pair lock, and run the fresh duplicate check before allowing the post save. Keep both locks through `acf/save_post`. A lock failure or fresh pair-read failure stops the save with an error response. Release remaining request locks on `shutdown`.

On Product or Person `post_updated`, skip unchanged titles. Query related non-retired contribution IDs through the repository. For each row, use scoped locks, acquire its contribution lock, read the stored pair fresh, confirm the row is not trash or auto-draft and both endpoints remain valid, then update only when the derived title differs. Do not filter these title updates by Product mode, role validity, or Event membership. A trashed contribution must never be updated by a Product or Person title change.

## `ProductRoleVocabulary`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

This class owns the approved Product Role vocabulary and exposes one runtime check to the seeder and publication validator:

```php
public const TAXONOMY = 'product_role';

/** @return array<string, string> slug => approved label */
public static function approved_terms(): array;

public static function is_approved_term_id(int $term_id): bool;
```

`approved_terms()` returns the four labels and slugs from the global constraint. `is_approved_term_id()` resolves the term and requires its taxonomy, slug, and label to match that map exactly. The service and seeder must not define their own role maps. A fifth term in `product_role` is not approved and cannot satisfy publication validation.

## `ProductRoleSeeder`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Read the exact vocabulary from `ProductRoleVocabulary::approved_terms()`. The seeder must not keep a second copy. Behavior mirrors the proven Event Role seeder: taxonomy must exist; same-slug/wrong-name, same-name/wrong-slug, and unexpected-term conflicts stop before mutation; clean/partial expected vocabulary is completed idempotently; no term relationships are created.

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
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductRoleVocabulary.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionSchemaTest.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductRoleSeederTest.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductRoleVocabularyTest.php`
- Create: `site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`

**Interfaces:**
- Consumes: ACF Local JSON conventions from Event Participation and the exact approved four-role vocabulary.
- Produces: non-public `product_contribution`, non-public `product_role`, four contribution fields, and idempotent role seeding.

- [ ] **Step 1: Write failing schema tests**

Tests must assert exact CPT visibility/route settings, native support limited to `title`, taxonomy settings/attachment only to `product_contribution`, field names/types/targets/return formats, Product/Person/Role field `required=0`, `bidirectional_target=[]`, role `add_term=0`, `save_terms=1`, `load_terms=1`, role return IDs/multiple selection, display-order min/default/step, and that no Person-side/Product-side duplicate relationship field is created.

- [ ] **Step 2: Run the new Product Contribution runner and verify RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

Expected: schema/seeder classes or JSON definitions are missing.

- [ ] **Step 3: Create the three ACF Local JSON definitions**

Use the Event Participation JSON shape as the structural precedent. Product/Person Post Object fields are single-value, return IDs, `required=0`, no bidirectional target. The Product Role field is `required=0` at ACF level, uses `product_role`, multiple selection, save/load native terms, no add-term, return IDs, and no bidirectional target. Display order is a number with default `0`, min `0`, step `1`. Publication requirements remain service/admin rules so drafts may be incomplete.

- [ ] **Step 4: Write failing ProductRoleVocabulary tests**

Assert the exact four label/slug pairs and that `is_approved_term_id()` accepts only a term whose taxonomy, slug, and label match the shared map. Add a fifth term in `product_role` and prove it is rejected.

- [ ] **Step 5: Write failing ProductRoleSeeder tests**

Cover empty vocabulary, partial expected vocabulary, rerun idempotence, exact final vocabulary, same-slug/wrong-name conflict, same-name/wrong-slug conflict, unexpected term, missing taxonomy, proof that no term relationships are created, and proof that the seeder reads the shared `ProductRoleVocabulary` map.

- [ ] **Step 6: Run the vocabulary and seeder tests RED**

Include `ProductRoleVocabularyTest` and `ProductRoleSeederTest` in the Product Contribution runner. Run it and confirm the new vocabulary and seeder tests fail before their implementation exists.

- [ ] **Step 7: Implement `ProductRoleVocabulary` and `ProductRoleSeeder`**

Make the seeder consume `ProductRoleVocabulary::approved_terms()`, follow EventRoleSeeder conflict-first semantics, and never delete/rename unexpected terms automatically.

- [ ] **Step 8: Run schema, vocabulary, and seeder tests GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

Expected: all Task 2 tests pass.

- [ ] **Step 9: Commit**

```bash
git add site-code/acf-json site-code/voelgoed-platform-core/includes/Product/ProductRoleVocabulary.php site-code/voelgoed-platform-core/includes/Product/ProductRoleSeeder.php site-code/voelgoed-platform-core/tests
git commit -m "feat: add Product Contribution schema"
```

### Task 3: Implement canonical contribution reads, pair identity, rich associations, and reverse Product queries

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductContributionRepository.php`
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductContributionPairLock.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionRepositoryTest.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductContributionPairLockTest.php`
- Modify: `site-code/voelgoed-platform-core/includes/Event/EventParticipationRepository.php`
- Modify: `site-code/voelgoed-platform-core/tests/EventParticipationRepositoryTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-event-participation-tests.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php`

**Interfaces:**
- Consumes: `EventParticipationRepository`, Product People mode constants, the Task 2 ACF field/taxonomy names.
- Produces: the repository and pair-lock interfaces defined above; later service/resolver/Bricks tasks depend on them.

- [ ] **Step 1: Write failing endpoint and pair-lookup tests**

Cover valid/non-trashed Product and Person endpoints, wrong post type, missing/trash endpoint, uniqueness statuses, exclude-current behavior, fresh cache-bypassing pair lookup, deterministic numeric pair IDs, and non-retired title-resync lookups that exclude trash and auto-draft without applying role or mode filters.

- [ ] **Step 2: Write failing forward-read tests**

Cover manual-mode active contributions, required roles, invalid role-less published rows being excluded, display-order + ID ordering, duplicate Person defense, unpublished Person filtering, invalid mode fail-closed, and no reads from `produk_medewerkers`/`event_medewerkers`/`_event_name`.

- [ ] **Step 3: Write failing event-mode tests**

Prove `people_for_product()` delegates membership to Event Participation, preserves its order, ignores contribution membership, allows an event association with no contribution, and excludes stale event-mode contribution annotations from `contributions_for_*()` when the Person leaves the Event.

- [ ] **Step 4: Write failing reverse-query tests**

For a published Person, prove `products_for_person()` returns the union of manual contribution Products and event-derived Products, defensively deduplicates duplicate candidate Product IDs returned by discovery, excludes unpublished Products and invalid modes, never reads legacy Product/Medewerker fields, and returns Product IDs ASC.

- [ ] **Step 5: Write failing rich association tests in both directions**

`associations_for_product()` must return one association per canonical Person in Product-side order. `associations_for_person()` must return one association per canonical Product in Product ID ASC. Manual associations use contribution role IDs/order/source. Event associations use Event Participation order/source plus optional Product role annotation; no annotation returns empty role IDs. Verify role IDs are numerically deterministic and stale annotations do not appear.

Prove Product Contribution obtains Event order through the Event Participation repository contract and never reads Event Participation fields or rows directly.

- [ ] **Step 6: Write failing pair-lock tests**

Cover domain-specific lock keys, pair and contribution-row acquire/release, failed acquire, release-without-acquire no-op, separate Product pairs not sharing a lock key, scoped release preserving locks held before scope entry, and release-all cleanup.

- [ ] **Step 7: Run RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 8: Write failing Event Participation display-order tests**

Add `EventParticipationRepositoryTest` cases for a valid active pair, a missing pair, a trashed participation, and an invalid endpoint. Run the Event Participation test runner and confirm the new cases fail before implementation.

- [ ] **Step 9: Implement the Event Participation display-order read**

Implement `EventParticipationRepository::display_order_for_pair($event_id, $person_id)` as the Product repository's only source for an active Event Participation's display order. Return `null` when no active, valid participation exists.

- [ ] **Step 10: Implement `ProductContributionRepository`**

Use WordPress/ACF APIs for canonical field reads and targeted fresh SQL only for the post-lock uniqueness recheck pattern already established by Event Participation. Keep Event eligibility delegated to `EventParticipationRepository`; do not reimplement Event Participation publication rules. Use `EventParticipationRepository::display_order_for_pair()` for event association order. Product Contribution code must not query Event Participation rows or read `event_participation_display_order` itself.

- [ ] **Step 11: Implement `ProductContributionPairLock`**

Mirror the proven named-lock pattern with a Product Contribution-specific key namespace. Add the contribution-ID lock, scoped release, and release-all behavior. Row locks serialize edits to an existing contribution; pair locks serialize a Product × Person identity across different rows.

- [ ] **Step 12: Run GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

- [ ] **Step 13: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductContributionRepository.php site-code/voelgoed-platform-core/includes/Product/ProductContributionPairLock.php site-code/voelgoed-platform-core/includes/Event/EventParticipationRepository.php site-code/voelgoed-platform-core/tests
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

Cover invalid Product/Person, duplicate pair, negative display order, invalid status, zero roles on publish, unknown/wrong-taxonomy role IDs, a valid same-taxonomy fifth role rejected by the shared vocabulary check, manual-mode publish success, invalid/blank-mode publish rejection, and event-mode Person-not-in-source-Event rejection.

- [ ] **Step 2: Add the Review Focus race test**

Simulate a stale pre-lock view followed by a fresh duplicate appearing before the post-lock check. First prove create returns duplicate/error and creates no second live pair. Then test two existing contribution rows with different stored pairs racing toward the same target pair. Let request A acquire row A's lock, resolve its old pair, acquire the target pair lock, pass its fresh check, save, and release the pair lock. Let request B acquire row B's lock, resolve its old pair, then acquire the same target pair lock and run its fresh check. Assert request B returns a duplicate error and only row A owns the target pair.

- [ ] **Step 3: Write failing write/rollback/title tests**

Prove canonical title `{Product title} — {Person title}`, one row for multiple roles, ACF Product/Person/display-order writes, native taxonomy term assignment, and hard rollback of a newly created contribution after any field/term write failure.

- [ ] **Step 4: Write failing admin tests**

Prove ACF/admin publication uses the service rules, an existing contribution row lock is acquired before stored endpoint reads, update excludes current ID, role-less publish is blocked, stale event annotation cannot publish, incomplete draft can remain inactive, and saved titles are derived rather than editor-authored. Cover every row in the lifecycle matrix, including a move to trash with a deleted endpoint, a trashed endpoint, invalid Product Role IDs, and stale Event membership. ACF field validators must allow each trash transition, and each retirement must succeed and release locks. Cover restoration from trash running the target-status validation.

Add title-sync tests for Product rename, Person rename, no-op title update, and trashed contribution exclusion. Verify each related non-retired row is locked before its endpoints are read, only changed derived titles are written, and title sync does not require valid roles or current Event membership.

- [ ] **Step 5: Run RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
```

- [ ] **Step 6: Implement `ProductContributionService`**

Keep one validation path. Acquire the pair lock before the authoritative fresh duplicate check. Use `ProductRoleVocabulary` for role approval. Support `future` as a non-public status that reserves a complete valid pair. Do not auto-create Product roles or Event relationships.

- [ ] **Step 7: Implement `ProductContributionAdmin`**

Reuse service validation; do not duplicate pair/mode/role rules in UI-only code. For existing rows, acquire the contribution lock before any stored endpoint read, then acquire the resolved pair lock before the fresh duplicate check. Apply the lifecycle matrix, including the unconditional trash-retirement path. Register save hooks that retain locks through the ACF write and release them after the save or on shutdown. Register a Product/Person title-change hook that resyncs related non-retired titles under scoped contribution-row locks.

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

| Product Contribution class | Load from source | Register as lifecycle component |
|---|---|---|
| ProductRoleVocabulary | Yes | No |
| ProductContributionRepository | Yes | No |
| ProductContributionPairLock | Yes | No |
| ProductContributionService | Yes | No |
| ProductContributionAdmin | Yes | Yes |
| ProductRoleSeeder | Yes | Yes |
| PersonProductsBricks | Only if Task 6 passes | Only if Task 6 passes |

- [ ] **Step 1: Write/extend integration tests RED**

Assert the bootstrap loads `ProductRoleVocabulary`, repository, pair lock, service, admin, seeder, and conditional `PersonProductsBricks` only if Task 6 passes. Assert lifecycle registration includes only `ProductContributionAdmin`, `ProductRoleSeeder`, and conditional `PersonProductsBricks` from the new Product Contribution classes. Assert vocabulary, repository, pair lock, and service are loaded dependencies and are not lifecycle-registered. Assert existing Event Participation and Product People registrations remain present.

- [ ] **Step 2: Update `integration.patch` and README minimally**

Load all required source files through the bootstrap list, with the existing Event repository available before Product Contribution uses it. Require `ProductRoleVocabulary` before the service and seeder use it. Register only lifecycle components through `Plugin::register_component()`: `ProductContributionAdmin`, `ProductRoleSeeder`, and conditional `PersonProductsBricks`. Do not lifecycle-register `ProductRoleVocabulary`, `ProductContributionRepository`, `ProductContributionPairLock`, or `ProductContributionService`; other classes instantiate those dependencies. Keep the `EventParticipationRepository` extension in the existing Event repository load path. Do not refactor unrelated bootstrap code.

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

Create manual Product Contribution through `ProductContributionService` with multiple Product roles and non-default display order. Prove `people_for_product()` returns the Person, `products_for_person()` returns the Product, both association directions report correct roles/source/order, duplicate pair creation is rejected, role-less publication is rejected, and Product People ignores any test legacy/override noise.

- [ ] **Step 7: Prove event-mode behavior and no role inheritance**

Create canonical Event Participation for the temporary Person, configure Product event mode/source Event, prove Product People membership derives live from Event Participation, create a Product Contribution role annotation, prove both association directions preserve Event membership/order while Product role data is explicit, and prove no Event role is auto-mapped.

- [ ] **Step 8: Prove stale event annotation behavior**

Retire/remove the temporary Event Participation while leaving the Product Contribution annotation in place long enough to query. Expected: Person disappears from Product People and reverse Product membership; stale annotation does not resurrect membership. Restore only owned QA state if needed for later assertions, never protected state.

- [ ] **Step 9: Prove reverse public filtering and defensive deduplication**

Exercise manual + event paths, unpublished Product exclusion, non-published Person exclusion, duplicate candidate discovery defense, non-Person context fail-closed behavior, and one Product ID per association. If `PersonProductsBricks` exists, prove its safe context and empty-section behavior against the temporary Person without editing a production Bricks template.

- [ ] **Step 10: Prove admin/title/schema behavior**

Verify no public Product Contribution route/archive, exact admin title derivation, ACF endpoint restrictions, draft-save behavior with incomplete fields, taxonomy save/load, display order, and publication validation where practical through the supported admin/business layer.

- [ ] **Step 11: Run guarded cleanup in `finally` or equivalent**

Before deleting each temporary record, verify post type and exact ownership marker. Hard-delete owned Product Contributions, Event Participations, Person, Product, and any temporary Event. Verify no matching post/meta/term relationship or WooCommerce-owned lookup residue remains for the temporary Product. Keep the four approved Product Role terms.

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

Record schema ownership, exact Product roles, uniqueness, validation, mode authority, forward/reverse rich association semantics, resolver compatibility, override-field retirement gate/outcome, optional Bricks outcome, runtime deployment, test commands/pass counts, cleanup, and protected-state result.

- [ ] **Step 2: Update the current-state snapshot**

State clearly what is now canonical and what remains legacy. Do not mark Template 772, Medewerker routes, `produk_medewerkers`, `event_medewerkers`, copy snippet 9, Template 773, or broadcast relationships decommissioned merely because Product Contribution exists.

- [ ] **Step 3: Update the inventory/register only where classification evidence changed**

Use only the register's existing classification/lifecycle vocabulary. For `product_people_override`, record the factual outcome (source field definition removed, canonical resolver no longer reads it, historical DB meta not automatically deleted) and select an existing classification only if the register actually tracks that surface. Do not invent a new status. Existing legacy surfaces such as `medewerker_products`, `produk_medewerkers`, and snippet 9 retain their prior dependency/access rulings unless this task's fresh evidence directly changes them.

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
[ ] Product/Person/Role ACF fields allow incomplete drafts; publication rules enforced in service/admin
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
[ ] reverse Products = manual union event-derived, defensively deduped
[ ] reverse output excludes unpublished Products and non-published Persons
[ ] reverse default ordering is Product ID ASC
[ ] rich associations work Product→Person and Person→Product
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
