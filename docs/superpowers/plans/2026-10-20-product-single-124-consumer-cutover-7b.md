# Product Single 124 Consumer Cutover 7B Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cut Product Single 124 from legacy Product-facing Medewerker helper paths to canonical Product People and a separate source-Event adapter, preserve the existing Product card presentation with canonical Person data, and retire only legacy runtime paths whose fresh removal gates pass.

**Architecture:** Add two focused Product-domain Bricks lifecycle adapters in Platform Core: `ProductPeopleBricks` delegates ordered Person membership exclusively to `ProductPeopleResolver`, while `ProductSourceEventBricks` reads and validates `produk_bron_event` independently. Product Single 124 receives a surgical runtime edit: its People loop targets canonical `person` posts and a new Product Person Card, while its source-Event loop uses the separate source-Event adapter. Legacy fields remain untouched; helper modes and `9-acf-product-copy.php` are retired only if a fresh caller/dependency audit proves removal safe.

**Tech Stack:** WordPress, WooCommerce Product posts, ACF Pro, Bricks Builder runtime templates/components, PHP 8+, Voelgoed Platform Core, existing standalone PHP test harness, Novamira/WP-CLI runtime operations, FluentSnippets runtime storage, Git worktrees.

**Spec:** `docs/superpowers/specs/2026-10-20-product-single-124-consumer-cutover-design.md`

## Global Constraints

- Planning baseline is merged `main` at `e1fa2245506f3d0ab641742e9904b0848981c006` (PR #27 / Task 7A merge).
- Implementation must not start until this plan has been independently reviewed, approved, and merged. Execution must use the exact then-current `origin/main` SHA supplied by the reviewer after plan merge; if `origin/main` has unrelated drift, STOP for re-baselining.
- Execute only in an isolated implementation branch/worktree created through the repository Superpowers worktree workflow. Recommended branch: `feat/product-single-124-cutover-7b`.
- Platform Core source changes land under `site-code/voelgoed-platform-core/` before any runtime deployment.
- `ProductPeopleResolver` remains the canonical Product → Person authority. Do not change its semantics during Task 7B; any required semantic change is a STOP for architecture review.
- Product People must never fall back to `produk_medewerkers`, `event_medewerkers`, or `medewerker`.
- `produk_bron_event` remains the approved Product source/provenance Event relationship and is read separately from Product People presentation.
- `_event_name` remains a separate Tickera/commerce relationship. Do not read, write, migrate, rename, or repurpose it in Task 7B.
- `produk_medewerkers` remains `KEEP_LEGACY`; preserve the field and all existing values.
- Existing Products remain unconfigured unless explicitly changed by owned QA. Task 7B does not seed `product_people_mode` or `product_people_override` on real Products.
- Product People query marker is exactly `vg-product-people-query`.
- Product People section marker is exactly `vg-product-people-section`.
- Product source-Event query marker is exactly `vg-product-source-event-query`.
- Product People Bricks queries target only published `person` IDs returned by `ProductPeopleResolver`, preserve resolver order with `post__in` / `orderby=post__in`, use `posts_per_page=-1`, and force `post__in=[0]` on empty/invalid results.
- The complete marked Product People section is hidden on the frontend whenever canonical People resolve to `[]`; Bricks builder and builder-call contexts preserve the original render decision.
- Product source-Event adapter accepts exactly one positive integer or digit-only positive numeric string from raw ACF `produk_bron_event`; malformed/multi-valued, missing, wrong-post-type, trashed, or ACF-unavailable inputs fail closed to `post__in=[0]`.
- Product source-Event presentation is independent of Product People mode and may render while Product People are empty/unconfigured.
- New Product Person Card loop object is directly a `person` post and renders featured image, `post_title`, `person_professional_title`, `post_excerpt`, and canonical Person permalink. It must not read any `medewerker_*` field or Event Participation data.
- Do not modify shared Medewerker Card `6b463f` or Event Participant Card `194bba`.
- Do not modify Templates 772, 773, 774, or 1127 except read-only auditing. Do not modify Template 774's `_event_name` product loop.
- Template 124 changes are limited to the Product People target subtree, its section marker, and the source-Event query target. All unrelated WooCommerce/template structure must compare equal before/after.
- `product_medewerkers` may be retired only after a fresh active-caller audit proves zero callers.
- `product_source_event` may be retired only after the source-Event replacement is live and a fresh active-caller audit proves zero callers.
- `9-acf-product-copy.php` may be retired only if a fresh dependency audit proves no remaining behavior relies on its writes to `produk_medewerkers`; Template 772 `medewerker_products` is an explicit dependency to inspect. If dependency remains, record `RETIRE_PENDING_DEPENDENCY` and leave the snippet unchanged.
- If a runtime helper path is proven dead but safe FluentSnippets edit access is unavailable, leave it unchanged and record `RETIRE_PENDING_ACCESS`; do not use destructive workarounds.
- Existing EXCLUDE Product → Medewerker edges and Event 763 legacy edges remain EXCLUDE and unchanged.
- Task 7B runtime QA must use temporary owned records. Do not mutate Products 765/767/768, Event 763, or legacy Medewerker profiles to prove canonical behavior.
- Persistent Person and `event_participation` counts must return to the pre-QA baseline after cleanup (expected zero at Task 7B start unless an independently approved change alters the baseline before execution).
- Any third-party side effect is unexplained until ownership is positively proven. Ambiguous records are never deleted merely to make cleanup pass.
- Direct SQL writes are prohibited.
- Any stop condition in the spec is binding. Do not invent fallback architecture during execution.
- Implementation opens a PR and stops. Do not begin Task 7C or another migration on the same branch.

## Review Focus

1. **Context leakage:** marked Product queries rendered from a non-Product or missing context must return a guaranteed-empty query rather than query arbitrary `person` or `tc_events` posts.
2. **Inherited Bricks constraints:** marked queries must discard stale legacy `meta_query`, taxonomy, ordering, status, or search constraints rather than accidentally intersect canonical IDs with old Medewerker/Event configuration.
3. **Empty section builder behavior:** `UNCONFIGURED`, empty manual, invalid mode, or empty event mode must hide the complete frontend People section but remain editable in both Bricks builder and builder-call requests.
4. **Malformed source Event values:** arrays, objects, whitespace/case variants, zero/negative values, wrong post types, trashed events, and ACF-unavailable reads must fail closed without warnings, fatal errors, or `_event_name` fallback.
5. **Legacy decommission dependency:** zero Template 124 callers is not enough to remove `9-acf-product-copy.php`; the removal audit must prove no remaining reverse/product consumer relies on `produk_medewerkers` writes.

---

# File Structure

## Product Bricks adapters

Create:

```text
site-code/voelgoed-platform-core/includes/Product/ProductPeopleBricks.php
site-code/voelgoed-platform-core/includes/Product/ProductSourceEventBricks.php
```

Responsibilities:

- `ProductPeopleBricks.php`: only Product context resolution, marked canonical Person query adaptation, and marked People-section frontend visibility.
- `ProductSourceEventBricks.php`: only Product context resolution and marked source-Event query adaptation from `produk_bron_event`.

Do not put card rendering, template mutation, data migration, or legacy helper removal inside either class.

## Source tests

Create:

```text
site-code/voelgoed-platform-core/tests/ProductPeopleBricksTest.php
site-code/voelgoed-platform-core/tests/ProductSourceEventBricksTest.php
```

Modify:

```text
site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php
site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Modify `site-code/voelgoed-platform-core/tests/bootstrap.php` only if a generic Bricks test primitive is missing. Do not add Product-domain policy to the shared bootstrap.

## Platform Core wiring

Modify:

```text
site-code/voelgoed-platform-core/integration.patch
site-code/voelgoed-platform-core/README.md
```

Runtime deployment applies the equivalent bootstrap and `includes/Plugin.php` additions only after source tests pass.

## Runtime QA

Create:

```text
site-code/runtime-tests/product-single-124-qa.php
```

Reuse, without semantic broadening unless proven necessary:

```text
site-code/runtime-tests/product-people-qa-side-effects.php
```

The new QA script owns Task 7B temporary data, Template 124 behavior checks, Product Person Card assertions, source-Event assertions, cleanup, and protected-state evidence assembly. The existing side-effect helper remains the ownership gate for Tickera `tc_api_keys` side effects created by owned QA Events.

## Runtime-only Bricks / FluentSnippets surfaces

Runtime changes are evidence-backed but are not represented as executable repository exports unless an existing project mechanism supports them:

```text
Bricks Template 124
Bricks new Product Person Card component (generated runtime component ID)
wp-content/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php   # conditional exact-mode retirement only
wp-content/fluent-snippet-storage/9-acf-product-copy.php                # conditional retirement only
```

No source-controlled replacement of those runtime files is invented in Task 7B.

## Evidence and architecture docs

Create:

```text
docs/architecture/PRODUCT_SINGLE_124_7B_IMPLEMENTATION.md
docs/architecture/POST_TASK_7B_CURRENT_STATE.md
docs/architecture/PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json
```

Modify:

```text
docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Update `docs/architecture/PERSON_CONSUMER_INVENTORY.csv` only if its current Product 124/card rows need a current-state lifecycle update; do not rewrite historical audits merely to make old evidence look current.

---

# Canonical PHP Interfaces

## `ProductPeopleBricks`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Constructor and public lifecycle interface:

```php
public function __construct(?ProductPeopleResolver $resolver = null);
public function register(): void;

public function filter_people_query_vars(
    array $query_vars,
    array $settings,
    string $element_id,
    string $element_name
): array;

public function filter_people_section_render(bool $render_element, object $element): bool;
```

Lifecycle hooks:

```text
bricks/posts/query_vars  priority 10  accepted_args 4
bricks/element/render    priority 10  accepted_args 2
```

Marked query output contract:

```php
[
    'post_type' => ProductPeopleResolver::PERSON_POST_TYPE,
    'post_status' => ProductPeopleResolver::PERSON_ACTIVE_STATUS,
    'post__in' => $ids !== [] ? $ids : [0],
    'orderby' => 'post__in',
    'posts_per_page' => -1,
]
```

Use exact CSS-class token matching, not substring matching.

Current Product resolution priority matches the existing Event Participation Bricks pattern:

1. `Bricks\Database::$page_data['preview_or_post_id']` when positive;
2. singular queried object ID;
3. `get_the_ID()`;
4. global `$post->ID`.

The resolved ID is not trusted merely because it is positive; `ProductPeopleResolver` remains responsible for rejecting non-Product IDs.

Request-scoped memoization of resolver results is permitted inside this adapter only to avoid duplicate People resolution between the query and section-render hook. Cache by Product ID and do not persist anything.

## `ProductSourceEventBricks`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Public interface:

```php
public function register(): void;

public function filter_source_event_query_vars(
    array $query_vars,
    array $settings,
    string $element_id,
    string $element_name
): array;
```

Lifecycle hook:

```text
bricks/posts/query_vars  priority 10  accepted_args 4
```

Marked query output contract:

```php
[
    'post_type' => 'tc_events',
    'post__in' => $event_id !== null ? [$event_id] : [0],
    'orderby' => 'post__in',
    'posts_per_page' => 1,
]
```

Do not add a `post_status=publish` requirement because the approved spec validates source Event as `tc_events` and not `trash`; draft/private source Event editor/preview contexts remain valid source relationships unless a later design changes that rule.

`ProductSourceEventBricks` must:

- reject non-Product context before reading ACF;
- return empty when `get_field()` is unavailable;
- read `get_field(ProductPeopleResolver::SOURCE_EVENT_FIELD, $product_id, false)`;
- accept only one positive integer or digit-only positive numeric string;
- reject arrays/objects/floats/booleans/zero/negative/missing values;
- validate `get_post($event_id)` as an object whose `post_type` is `tc_events` and `post_status` is not `trash`;
- never read `product_people_mode`, `product_people_override`, `produk_medewerkers`, `event_medewerkers`, or `_event_name`.

Use the same context resolution and exact CSS-class token semantics as `ProductPeopleBricks`. Duplicate only the small context/class-token helpers necessary to keep the classes independently understandable; do not create a new abstraction merely to remove a few lines.

## Platform Core lifecycle registration

`integration.patch` must add bootstrap loads for:

```text
includes/Product/ProductPeopleBricks.php
includes/Product/ProductSourceEventBricks.php
```

and `includes/Plugin.php` lifecycle imports/registrations:

```php
use Voelgoed\PlatformCore\Product\ProductPeopleBricks;
use Voelgoed\PlatformCore\Product\ProductSourceEventBricks;

$this->register_component('product_people_bricks', ProductPeopleBricks::class);
$this->register_component('product_source_event_bricks', ProductSourceEventBricks::class);
```

`ProductPeopleResolver` remains unregistered as a lifecycle component.

---

# Task 1: Establish the Approved Execution Baseline and Fresh Runtime Preflight

**Files:** none

**Interfaces:**
- Consumes: approved Task 7B spec and approved/merged Task 7B plan.
- Produces: isolated implementation worktree plus a read-only preflight snapshot that later runtime writes must match.

- [ ] **Step 1: Verify the exact approved execution SHA**

Run from the stable checkout:

```bash
git fetch origin
git rev-parse origin/main
```

Expected: exact match to the reviewer-supplied execution SHA after this plan is merged. If no execution SHA has been supplied, or the SHA differs, STOP.

- [ ] **Step 2: Create/reuse the isolated implementation worktree**

Invoke `superpowers:using-git-worktrees`. Recommended branch/worktree:

```text
branch: feat/product-single-124-cutover-7b
worktree: .worktrees/feat-product-single-124-cutover-7b
```

Expected: clean worktree whose merge base is the approved execution SHA.

- [ ] **Step 3: Run the existing source baseline suites before editing**

Run at minimum:

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
php site-code/person-foundation/tests/run-person-foundation-tests.php
```

Expected: every suite exits 0. Record exact pass counts. Any baseline failure is a STOP before code changes.

- [ ] **Step 4: Capture fresh read-only runtime structure and hashes**

Using Novamira/WP-CLI read-only inspection, record:

```text
Template 124 complete content/record digest
Template 124 element IDs and parent/child structure around vgpr03 / vgpr13 / vgpr04
actual enclosing Product People section element ID
actual current source-Event subtree/layout
component 6b463f complete structure/hash and current caller list
component 194bba hash
Templates 772 / 774 whole-record hashes
Products 765 / 767 / 768 protected record + relevant ACF/meta values
Event 763 protected record + event_medewerkers
Medewerker profiles 753–757 hashes
all existing product_people_mode / product_people_override rows on pre-existing Products
all existing produk_bron_event / produk_medewerkers values on protected Products
all existing _event_name Product rows
Person count by status
Event Participation count by status
tc_api_keys exact protected snapshot
runtime Platform Core bootstrap / Plugin hashes
runtime ProductPeopleResolver hash
runtime helper 10-vg-bricks-medewerker-queries.php hash
runtime copy snippet 9-acf-product-copy.php hash
```

Expected: current live state matches the Task 7A post-state in all architecture-relevant respects. Material drift affecting the design is a STOP.

- [ ] **Step 5: Perform the pre-cutover active-caller audit**

Fresh-search all Bricks templates/components, active FluentSnippets/runtime PHP, repository source, and current docs for active/live references to:

```text
product_medewerkers
product_source_event
produk_medewerkers
9-acf-product-copy.php
medewerker_products
event_products
```

Record active callers separately from historical/documentation references. Expected known live callers at minimum: Template 124 for `product_medewerkers` and `product_source_event`; Template 772 for `medewerker_products`. Do not assume these counts if runtime says otherwise.

- [ ] **Step 6: Commit no changes for Task 1**

Task 1 is evidence/preflight only. Do not create a placeholder commit.

---

# Task 2: Add the Canonical Product People Bricks Adapter with TDD

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductPeopleBricks.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductPeopleBricksTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-people-tests.php`
- Modify only if generically necessary: `site-code/voelgoed-platform-core/tests/bootstrap.php`

**Interfaces:**
- Consumes: `ProductPeopleResolver::__construct(?EventParticipationRepository $repository = null)` and `ProductPeopleResolver::people_for_product(int): array`.
- Produces: lifecycle component `ProductPeopleBricks` with `register()`, `filter_people_query_vars(...)`, and `filter_people_section_render(...)`.

- [ ] **Step 1: Add failing tests for unmarked and invalid-context behavior**

In `ProductPeopleBricksTest.php`, add tests that assert:

```text
test_unmarked_query_is_returned_unchanged
test_marked_query_without_product_context_fails_closed
test_marked_query_with_non_product_context_fails_closed
test_unrelated_section_render_decision_is_unchanged
```

Use exact marker `vg-product-people-query` / `vg-product-people-section`; include suffix/prefix lookalikes to prove token matching is exact.

- [ ] **Step 2: Run the focused suite and observe RED**

Run:

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: FAIL because `ProductPeopleBricks` does not exist / new tests are unmet.

- [ ] **Step 3: Add failing tests for canonical membership/order and inherited-constraint removal**

Add tests asserting:

```text
test_marked_query_delegates_membership_to_product_people_resolver
test_marked_query_preserves_resolver_order_with_post_in_ordering
test_marked_query_discards_legacy_bricks_constraints
test_empty_canonical_result_forces_post_in_zero
```

The inherited-constraint test begins with stale legacy values such as `post_type=medewerker`, legacy `meta_query`, `tax_query`, order, status, and search; assert the returned array equals only the five locked canonical query keys.

- [ ] **Step 4: Add failing tests for section visibility and builder behavior**

Add tests asserting:

```text
test_frontend_people_section_renders_when_resolver_has_ids
test_frontend_people_section_is_hidden_for_unconfigured_product
test_frontend_people_section_is_hidden_for_empty_manual_product
test_builder_keeps_empty_people_section_editable
test_builder_call_keeps_empty_people_section_editable
```

Use the real resolver with test Product/Person/meta fixtures rather than adding a second fake authority.

- [ ] **Step 5: Add failing tests for Product context priority**

Add tests proving:

```text
Bricks preview_or_post_id wins over queried object
queried singular Product is used when preview ID is absent
get_the_ID fallback is used when both higher-priority sources are absent
global post fallback is used last
```

At least one test must put a non-Product in a lower-priority context to prove the selected context, not accidental fallback, controls resolution.

- [ ] **Step 6: Implement `ProductPeopleBricks` minimally**

Create `ProductPeopleBricks.php` with exactly the public signatures defined above. Use exact marker tokens, canonical query replacement, builder-safe section rendering, and request-scoped cache keyed by Product ID only if needed to avoid duplicate resolver calls.

Do not add any legacy reads or writes.

- [ ] **Step 7: Run the focused Product People suite GREEN**

Run:

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all Product People tests pass, including all pre-existing 7A tests.

- [ ] **Step 8: Commit Task 2**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductPeopleBricks.php \
        site-code/voelgoed-platform-core/tests/ProductPeopleBricksTest.php \
        site-code/voelgoed-platform-core/tests/run-product-people-tests.php \
        site-code/voelgoed-platform-core/tests/bootstrap.php
git commit -m "feat: add Product People Bricks adapter"
```

Omit `bootstrap.php` from `git add` if unchanged.

---

# Task 3: Add the Independent Product Source-Event Bricks Adapter with TDD

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductSourceEventBricks.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductSourceEventBricksTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-people-tests.php`
- Modify only if generically necessary: `site-code/voelgoed-platform-core/tests/bootstrap.php`

**Interfaces:**
- Consumes: `ProductPeopleResolver::SOURCE_EVENT_FIELD` constant only for the field name; it must not call resolver behavior.
- Produces: lifecycle component `ProductSourceEventBricks` with `register()` and `filter_source_event_query_vars(...)`.

- [ ] **Step 1: Add failing tests for unmarked and invalid Product context behavior**

Add tests asserting:

```text
test_unmarked_source_event_query_is_returned_unchanged
test_marked_source_event_query_without_product_context_fails_closed
test_marked_source_event_query_with_non_product_context_fails_closed
```

- [ ] **Step 2: Add failing tests for accepted source Event values**

Add tests asserting a valid source Event resolves identically when raw ACF returns:

```text
123
"123"
```

Expected marked output:

```php
[
    'post_type' => 'tc_events',
    'post__in' => [123],
    'orderby' => 'post__in',
    'posts_per_page' => 1,
]
```

- [ ] **Step 3: Add failing malformed-value tests**

Add separate tests or a data-driven equivalent for:

```text
[]
[123]
[123, 124]
(object) ['ID' => 123]
0
-1
0.5
true
false
""
" 123"
"123 "
"+123"
```

Each must return `post__in=[0]` and must not broaden into an all-Event query.

- [ ] **Step 4: Add failing endpoint-validation tests**

Assert empty query for:

```text
missing Event post
post_type != tc_events
post_status = trash
```

Also assert draft/private `tc_events` remains valid because the spec rejects trash only.

- [ ] **Step 5: Add failing inherited-constraint and independence tests**

Assert the marked adapter discards stale Bricks meta/tax/search/order constraints and returns only the locked source-Event query contract.

In test fixtures populate `_event_name`, `product_people_mode`, `product_people_override`, and `produk_medewerkers` with conflicting values; assert none changes the source Event result.

- [ ] **Step 6: Add an out-of-process no-ACF regression test**

Follow the existing Product resolver out-of-process pattern: launch PHP with WordPress post primitives and the real adapter class loaded but no `get_field()` function defined. Invoke a marked source-Event query for a valid Product context.

Expected: process exit 0 and exact `post__in=[0]` / empty query contract rather than fataling.

- [ ] **Step 7: Implement `ProductSourceEventBricks` minimally**

Create the exact public interface above. Reuse the small context/token algorithms conceptually from `EventParticipationBricks` / `ProductPeopleBricks`; do not introduce a shared abstraction in Task 7B.

- [ ] **Step 8: Run the Product People suite GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all existing and new Product tests pass.

- [ ] **Step 9: Commit Task 3**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductSourceEventBricks.php \
        site-code/voelgoed-platform-core/tests/ProductSourceEventBricksTest.php \
        site-code/voelgoed-platform-core/tests/run-product-people-tests.php \
        site-code/voelgoed-platform-core/tests/bootstrap.php
git commit -m "feat: add Product source Event Bricks adapter"
```

Omit `bootstrap.php` if unchanged.

---

# Task 4: Wire Both Product Bricks Components into Platform Core

**Files:**
- Modify: `site-code/voelgoed-platform-core/integration.patch`
- Modify: `site-code/voelgoed-platform-core/README.md`
- Modify: `site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php`

**Interfaces:**
- Consumes: `ProductPeopleBricks` and `ProductSourceEventBricks` from Tasks 2–3.
- Produces: source-controlled bootstrap/lifecycle wiring instructions for runtime deployment.

- [ ] **Step 1: Write failing integration tests for bootstrap loads**

Extend `ProductPeopleIntegrationTest` to require added patch lines for:

```text
'includes/Product/ProductPeopleBricks.php',
'includes/Product/ProductSourceEventBricks.php',
```

Expected before patch update: RED.

- [ ] **Step 2: Write failing lifecycle registration tests**

Require exact added lines:

```php
use Voelgoed\PlatformCore\Product\ProductPeopleBricks;
use Voelgoed\PlatformCore\Product\ProductSourceEventBricks;
$this->register_component('product_people_bricks', ProductPeopleBricks::class);
$this->register_component('product_source_event_bricks', ProductSourceEventBricks::class);
```

Keep the existing assertion that `ProductPeopleResolver` is not registered as a lifecycle component.

- [ ] **Step 3: Update `integration.patch` minimally**

Add only the two bootstrap file loads, two imports, and two lifecycle registrations. Preserve every existing Event/Product integration line and keep patch hunk counts valid.

- [ ] **Step 4: Update README integration/deployment notes**

Document the two new Product Bricks lifecycle classes, their exact semantic markers, and the fact that resolver/source-Event business boundaries are separate.

- [ ] **Step 5: Run Product People tests GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all tests pass, including integration hunk-count validation.

- [ ] **Step 6: Run full source regression suites**

Run the same baseline suites from Task 1. Expected: all exit 0 with no count regressions except the intentional new Product tests.

- [ ] **Step 7: Commit Task 4**

```bash
git add site-code/voelgoed-platform-core/integration.patch \
        site-code/voelgoed-platform-core/README.md \
        site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php
git commit -m "feat: wire Product Bricks adapters"
```

---

# Task 5: Build the Guarded Task 7B Runtime QA Harness Before Runtime Writes

**Files:**
- Create: `site-code/runtime-tests/product-single-124-qa.php`
- Test against existing: `site-code/runtime-tests/product-people-qa-side-effects.php`
- Modify only if a new generic ownership case is proven: `site-code/runtime-tests/product-people-qa-side-effects.php`
- If modified, extend: `site-code/voelgoed-platform-core/tests/ProductPeopleQaSideEffectsTest.php`

**Interfaces:**
- Consumes: both new Bricks adapters; Task 7A Product fields/resolver; Event Participation repository; existing owned Tickera side-effect guard.
- Produces: one reproducible loaded-runtime QA command that creates only owned test data, asserts the approved Task 7B matrix, cleans up, and emits machine-readable evidence payloads used later in the runtime manifest.

- [ ] **Step 1: Define exact QA ownership and preflight inputs**

The script must require a unique owner token shaped like:

```text
[QA][AUTOMATED] Task 7B <unique-token>
```

Before any QA write, it must assert the protected preflight snapshot captured in Task 1 still matches and assert no pre-existing record contains the new token.

- [ ] **Step 2: Implement owned fixture creation only**

The script may create temporary owned:

```text
1 Product suitable for Product Single 124 frontend rendering
1 source Event
multiple published Persons with deterministic title/professional-title/excerpt/permalink fixtures
at least 1 draft/private Person endpoint for exclusion coverage
ordered Event Participation records
```

Use ACF APIs for canonical/relationship writes where the runtime contract expects ACF; no direct SQL.

- [ ] **Step 3: Encode the Product People QA scenarios**

Add named checks for at least:

```text
unconfigured_returns_empty
unconfigured_section_hidden_frontend
event_mode_preserves_event_participation_order
event_mode_excludes_unpublished_person
event_mode_ignores_manual_override
manual_mode_preserves_explicit_order
manual_mode_deduplicates_first_occurrence
manual_empty_returns_empty
manual_empty_section_hidden_frontend
invalid_mode_returns_empty
legacy_produk_medewerkers_does_not_affect_output
empty_result_query_uses_post_in_zero
builder_keeps_empty_section_editable
```

- [ ] **Step 4: Encode the Product Person Card assertions**

The runtime harness does not create the card yet, but it must define the post-cutover assertions it will run after Task 7B card/template deployment:

```text
featured image comes from current person
name comes from person post_title
professional title comes from person_professional_title
short bio comes from person post_excerpt
profile link resolves to the canonical Person permalink and HTTP 200
no medewerker_* dynamic tag remains in the new component structure
```

Before the card exists, these post-cutover checks may be gated by an explicit `--post-cutover`/equivalent mode rather than falsely passing.

- [ ] **Step 5: Encode source-Event adapter scenarios**

Add named checks for:

```text
valid_source_event_returns_exact_event
source_event_renders_while_people_empty
manual_mode_does_not_change_source_event
empty_source_event_returns_empty
malformed_array_source_event_returns_empty
malformed_multi_source_event_returns_empty
missing_source_event_returns_empty
wrong_post_type_source_event_returns_empty
trashed_source_event_returns_empty
acf_path_is_used
_event_name_is_unchanged_and_irrelevant
```

Malformed runtime values must be written only to the owned QA Product and restored/removed through ACF-safe cleanup.

- [ ] **Step 6: Encode ownership-safe cleanup and side-effect handling**

Delete owned Event Participation records before Persons/Event/Product as required by dependencies. Reuse the existing `tc_api_keys` ownership gate for any Tickera side effect created by the owned Event.

The script must fail rather than delete any ambiguous side effect.

- [ ] **Step 7: Encode final protected-state assertions**

Require:

```text
owned posts/meta/term relationships = 0
owned Event Participation = 0
owned Person = 0
run token in posts/meta/options = 0
protected Products/Event/Medewerker records equal pre-QA snapshot
protected Templates 772/774 equal
components 6b463f/194bba equal
pre-existing canonical Product People meta equal
_event_name exact equality
legacy Product relationship exact equality
all unexplained side-effect sets empty
Person and Event Participation counts restored to pre-QA baseline
```

Template 124/new Product Person Card are handled as explicitly expected differences only after cutover.

- [ ] **Step 8: Run static/source-level validation of the harness**

At minimum:

```bash
php -l site-code/runtime-tests/product-single-124-qa.php
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: syntax clean and source suite GREEN. Do not run the write-capable QA against runtime yet.

- [ ] **Step 9: Commit Task 5**

```bash
git add site-code/runtime-tests/product-single-124-qa.php \
        site-code/runtime-tests/product-people-qa-side-effects.php \
        site-code/voelgoed-platform-core/tests/ProductPeopleQaSideEffectsTest.php
git commit -m "test: add Product Single 124 guarded QA"
```

Omit unchanged files.

---

# Task 6: Deploy the Source-Controlled Adapters and Prove Runtime Parity Before Template Cutover

**Files:**
- Runtime deploy targets only:
  - `wp-content/plugins/voelgoed-platform-core/includes/Product/ProductPeopleBricks.php`
  - `wp-content/plugins/voelgoed-platform-core/includes/Product/ProductSourceEventBricks.php`
  - bootstrap load list equivalent of source `integration.patch`
  - `wp-content/plugins/voelgoed-platform-core/includes/Plugin.php`
- Repository files: no new source beyond Tasks 2–5

**Interfaces:**
- Consumes: source-controlled adapter files and approved integration patch.
- Produces: loaded runtime adapters with source/runtime SHA-256 parity while Template 124 remains untouched.

- [ ] **Step 1: Reconfirm protected runtime preflight immediately before deployment**

Repeat the high-risk hashes/records from Task 1: Template 124, components, protected Products/Event, legacy helper/copy snippets, `_event_name`, canonical Product People meta, counts, and `tc_api_keys`.

Expected: exact match to the approved preflight. Drift is a STOP.

- [ ] **Step 2: Back up every runtime Platform Core file that will change**

Create timestamped recovery copies outside the served plugin path for bootstrap and `includes/Plugin.php`; record SHA-256 before deployment.

- [ ] **Step 3: Deploy only the two new adapter files and exact lifecycle wiring**

Apply the source-controlled file bytes first, then the exact bootstrap/Plugin additions described by `integration.patch`.

Do not change Template 124, Bricks components, FluentSnippets, Product fields, or Product data in this step.

- [ ] **Step 4: Prove source/runtime parity and class registration**

Assert SHA-256 equality for both adapter source/runtime files and prove loaded runtime has both lifecycle components/hook registrations.

Expected hooks:

```text
ProductPeopleBricks: bricks/posts/query_vars(4), bricks/element/render(2)
ProductSourceEventBricks: bricks/posts/query_vars(4)
```

- [ ] **Step 5: Run read-only adapter smoke checks against existing Products**

For existing unconfigured Products, canonical People result must be empty. Source Event adapter may return the existing `produk_bron_event` Event where valid. This step must not write Product meta.

- [ ] **Step 6: Commit no new repository change for deployment**

Runtime deployment evidence is recorded later in docs/manifest.

---

# Task 7: Create the Canonical Product Person Card and Surgically Cut Over Template 124

**Files / runtime surfaces:**
- Runtime Bricks components option: add one new Product Person Card component
- Runtime Bricks Template 124: targeted subtree edit only
- Repository source: no executable Bricks export is invented

**Interfaces:**
- Consumes: loaded `ProductPeopleBricks` / `ProductSourceEventBricks` adapters and the existing legacy Product card structure for visual/layout reference only.
- Produces: new canonical Product Person Card component plus Template 124 query/section markers and component instance.

- [ ] **Step 1: Capture write-near snapshots immediately before Bricks mutation**

Record exact Template 124 whole record, element list/parent tree, target subtree JSON, and component option state. Reconfirm `6b463f` and `194bba` hashes.

- [ ] **Step 2: Create a new Product Person Card by structurally preserving the current Product card presentation**

Create a new Bricks component with a new generated ID and a Product-specific name such as `Product Person Card`.

Use legacy component `6b463f` only as the presentation/layout reference; do not mutate it. The new component must be direct-`person` context and map dynamic content to:

```text
featured image
post_title
person_professional_title
post_excerpt
post_url
button label: current Product label, expected "Bekyk profiel" unless preflight proves otherwise
```

Remove/replace every `medewerker_*` dynamic reference. Do not add Event Participation role or unrelated Person fields.

- [ ] **Step 3: Verify the new component structure before Template 124 references it**

Assert:

```text
new component ID != 6b463f and != 194bba
legacy 6b463f hash unchanged
194bba hash unchanged
new component contains zero medewerker_ tokens
new component contains expected canonical Person field/tag tokens
```

Record component structural hash.

- [ ] **Step 4: Apply the Product People query marker to the existing `vgpr03` loop**

Remove the legacy `product_medewerkers` helper/query-mode marker from the target query and add exact class token `vg-product-people-query`. Remove stale query arguments that could intersect the adapter contract if present; the runtime adapter remains authoritative.

- [ ] **Step 5: Replace the `vgpr04` legacy card instance with the new Product Person Card instance**

Preserve the existing Product People grid/section layout outside the component swap.

- [ ] **Step 6: Mark the enclosing Product People section**

On the actual enclosing section identified in Task 1, add exact class token `vg-product-people-section`. Do not move unrelated elements into/out of the section.

- [ ] **Step 7: Apply the independent source-Event marker to `vgpr13`**

Remove legacy `product_source_event` helper/query-mode configuration and add exact class token `vg-product-source-event-query`. Preserve existing source-Event card/layout content.

- [ ] **Step 8: Prove structural isolation immediately after the write**

Compare Template 124 before/after:

```text
expected differences only in identified People query/card/section marker and source-Event query
all non-target element IDs unchanged
all non-target parent/child relationships unchanged
unrelated settings/content unchanged
whole-record difference explainable by expected target edits only
```

Any unexplained template drift is a STOP and restore-from-snapshot event.

- [ ] **Step 9: Verify frontend/builder smoke behavior without persistent QA fixtures**

Existing unconfigured Product pages must not show a legacy People fallback. Source-Event presentation may remain visible where `produk_bron_event` is valid. Bricks builder must still allow editing the People section.

- [ ] **Step 10: Commit no repository change for the runtime-only Bricks write**

Record runtime identity/hashes for later evidence.

---

# Task 8: Run the Full Guarded Task 7B Runtime QA and Restore Protected State

**Files:**
- Execute: `site-code/runtime-tests/product-single-124-qa.php`
- Runtime data: temporary owned fixtures only

**Interfaces:**
- Consumes: completed runtime adapter/card/template cutover.
- Produces: fresh Task 7B consumer evidence plus zero-residue cleanup proof.

- [ ] **Step 1: Take the final pre-QA protected snapshot**

The harness must compare this snapshot to Task 1/6 preflight and refuse to proceed on unexplained drift.

- [ ] **Step 2: Execute the guarded QA with a fresh unique token in post-cutover mode**

Run the loaded-runtime command defined by `product-single-124-qa.php` using a new unique Task 7B token.

Expected: every named Product People, Product Person Card, source-Event, builder, no-fallback, and independence scenario passes.

- [ ] **Step 3: Verify actual frontend rendering**

For the owned QA Product in event/manual states, fetch/render through the real Product Single 124 frontend and assert canonical Person card content/permalink. At least one canonical Person permalink must return HTTP 200 while the Person exists.

Verify a People-empty state omits the complete marked section, not merely loop children.

- [ ] **Step 4: Clean owned fixtures and owned third-party side effects**

Use ownership-safe dependency order. Re-verify Tickera side-effect ownership immediately before deletion. Ambiguous/unexplained rows cause FAIL and STOP.

- [ ] **Step 5: Run independent read-only token/residue scan**

Search posts, post meta, options, taxonomy/relationship state, Event Participation pairs, Person records, and known side-effect surfaces for the exact token.

Expected: zero references.

- [ ] **Step 6: Prove protected-state equality after cleanup**

Expected exact equality except the explicitly approved persistent implementation changes:

```text
new Platform Core adapter files/wiring
Template 124 targeted cutover
new Product Person Card component
any later separately authorized runtime-path retirement from Task 9
```

Products 765/767/768, Event 763, Medewerker profiles, Templates 772/774, components 6b463f/194bba, pre-existing canonical Product People meta, `_event_name`, `produk_bron_event`, `produk_medewerkers`, and protected side-effect sets must match their pre-QA state.

- [ ] **Step 7: If QA fails, recover before changing code**

Follow systematic-debugging: identify root cause, restore owned/protected state, record failed token/evidence, then add the smallest regression test before a fix. Do not repeatedly mutate runtime without a root-cause hypothesis.

- [ ] **Step 8: Commit no repository change solely for running QA**

QA results are added to evidence docs in Task 10.

---

# Task 9: Perform Fresh Legacy Decommission Audits and Retire Only Safe Runtime Paths

**Files / runtime surfaces:**
- Read/audit: all Bricks templates/components, active FluentSnippets/runtime helper code, repository source/docs, relevant Git history
- Conditional runtime edit: `10-vg-bricks-medewerker-queries.php`
- Conditional runtime retirement: `9-acf-product-copy.php`

**Interfaces:**
- Consumes: proven canonical Template 124 cutover from Task 8.
- Produces: explicit lifecycle ruling and evidence for each legacy Product-facing runtime path.

- [ ] **Step 1: Fresh-audit `product_medewerkers` callers**

Prove active runtime caller count after cutover. Historical docs are not callers.

If count is zero and safe FluentSnippets edit access exists, remove only the `product_medewerkers` mode branch from helper source, preserving every other mode byte-for-byte as far as the runtime representation permits. Record before/after hashes and a focused diff.

If callers remain or safe access is unavailable, leave it intact and record exact blocker.

- [ ] **Step 2: Fresh-audit `product_source_event` callers**

Require both:

```text
Template 124 source-Event presentation is proven on the new adapter
active caller count = 0
```

Then conditionally remove only that mode if safe access exists. Otherwise leave intact and record blocker.

- [ ] **Step 3: Audit the complete dependency chain for `9-acf-product-copy.php`**

Do not use direct-caller count alone. Trace all live behavior that reads `produk_medewerkers`, including at minimum Template 772 `medewerker_products` reverse lookup and any editor/runtime path that expects product saves to populate the legacy field.

Expected likely ruling from current architecture: if Template 772 or another live behavior still depends on populated `produk_medewerkers`, mark `RETIRE_PENDING_DEPENDENCY` and leave the copy snippet unchanged.

Only retire the snippet if the fresh audit positively proves no remaining dependency and safe FluentSnippets access exists.

- [ ] **Step 4: Re-run a protected smoke/regression check after any runtime snippet edit**

If either helper mode or the copy snippet changes, re-check:

```text
Template 124 canonical People/source Event behavior
Template 772 reverse Product behavior where applicable
unrelated helper modes remain callable/structurally intact
protected legacy fields unchanged
```

A runtime-helper edit that breaks another consumer must be restored immediately.

- [ ] **Step 5: Record final lifecycle labels**

For each path, use one unambiguous outcome:

```text
product_medewerkers: RETIRED | KEEP_LEGACY | RETIRE_PENDING_ACCESS | RETIRE_PENDING_CALLER
product_source_event: RETIRED | KEEP_LEGACY | RETIRE_PENDING_ACCESS | RETIRE_PENDING_CALLER
9-acf-product-copy.php: RETIRED | KEEP_LEGACY | RETIRE_PENDING_DEPENDENCY | RETIRE_PENDING_ACCESS
```

- [ ] **Step 6: Commit no repository change solely for runtime decommission**

Evidence/docs are committed in Task 10.

---

# Task 10: Write Task 7B Evidence, Current State, and Lifecycle Updates

**Files:**
- Create: `docs/architecture/PRODUCT_SINGLE_124_7B_IMPLEMENTATION.md`
- Create: `docs/architecture/POST_TASK_7B_CURRENT_STATE.md`
- Create: `docs/architecture/PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json`
- Modify: `docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv`
- Modify: `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`
- Modify: `docs/architecture/IMPLEMENTATION_LEDGER.md`
- Modify only if current-state row requires it: `docs/architecture/PERSON_CONSUMER_INVENTORY.csv`

**Interfaces:**
- Consumes: all source test output, runtime hashes, Template/card structural evidence, QA results, cleanup proof, and Task 9 lifecycle rulings.
- Produces: independently reviewable repository evidence for PR approval and the next-task baseline.

- [ ] **Step 1: Write the implementation evidence document**

`PRODUCT_SINGLE_124_7B_IMPLEMENTATION.md` must state exact:

```text
implementation base SHA and head SHA
runtime deployment hashes and source/runtime parity
Template 124 target element IDs and before/after structural proof
new Product Person Card generated ID and structural hash
query/section marker names
QA token and every scenario result
frontend/permalink proof
cleanup counts and independent token scan
protected-state comparison
helper/copy snippet before/after hashes
fresh caller counts
final lifecycle rulings
```

Failed/superseded QA runs, if any, remain clearly historical and are not completion evidence.

- [ ] **Step 2: Write `POST_TASK_7B_CURRENT_STATE.md`**

Separate `CURRENT`, `TARGET NEXT`, `DEFER / KEEP_LEGACY`, and `EXCLUDE` explicitly. Record that Product Single 124 Product People is canonical, source Event remains a separate `produk_bron_event` concern, `_event_name` is unchanged, and legacy-field values remain preserved.

Do not invent the next migration architecture beyond known deferred boundaries.

- [ ] **Step 3: Write the machine-readable runtime manifest**

`PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json` must be valid JSON and contain enough structured data to independently verify:

```text
repo/base/head
source/runtime hashes
protected-state digests
Template 124 before/after target and non-target evidence
new component identity/hash
QA token/scenarios/results
pre/post counts
side-effect cleanup
caller audit counts
runtime-path lifecycle rulings
legacy field equality
_event_name equality
```

- [ ] **Step 4: Update the consumer migration map**

Mark Product Single 124 Product People as `MIGRATED_CANONICAL` only if Task 8 passed. Keep source Event as its own canonical/retargeted concern. Update helper mode rows according to Task 9 evidence, not assumptions.

- [ ] **Step 5: Update the Legacy Decommission Register**

Close only gates actually satisfied. Preserve `produk_medewerkers` and its values. If `9-acf-product-copy.php` remains due Template 772 or another dependency, state that blocker precisely.

- [ ] **Step 6: Update the implementation ledger**

Add a seven-column Task 7B row with runtime impact and final state in separate cells. Record any execution rulings below the table without literal unescaped table-breaking pipes in prose cells.

- [ ] **Step 7: Validate docs/data formats**

Run:

```bash
git diff --check
php -r '$j=json_decode(file_get_contents("docs/architecture/PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest ok\n";'
```

Parse modified CSV files with a real CSV parser and assert consistent column counts. Validate Markdown table row column counts for modified architecture docs.

- [ ] **Step 8: Commit Task 10**

```bash
git add docs/architecture/PRODUCT_SINGLE_124_7B_IMPLEMENTATION.md \
        docs/architecture/POST_TASK_7B_CURRENT_STATE.md \
        docs/architecture/PRODUCT_SINGLE_124_7B_RUNTIME_MANIFEST.json \
        docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv \
        docs/architecture/LEGACY_DECOMMISSION_REGISTER.md \
        docs/architecture/IMPLEMENTATION_LEDGER.md \
        docs/architecture/PERSON_CONSUMER_INVENTORY.csv
git commit -m "docs: record Product Single 124 canonical cutover"
```

Omit `PERSON_CONSUMER_INVENTORY.csv` if unchanged.

---

# Task 11: Final Verification, PR Creation, and Hard Stop

**Files:** all implementation/evidence files from Tasks 2–10

**Interfaces:**
- Consumes: completed implementation branch and restored runtime.
- Produces: one implementation PR ready for independent architecture review; no merge and no next task.

- [ ] **Step 1: Run every relevant source suite from a clean worktree**

At minimum:

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
php site-code/person-foundation/tests/run-person-foundation-tests.php
git diff --check
```

Record exact counts and exit codes.

- [ ] **Step 2: Reconfirm final runtime parity and protected state**

Fresh-read source/runtime adapter hashes, runtime bootstrap/Plugin hashes, Template 124, new Product Person Card, protected templates/components, helper/copy snippet hashes, Product/Event legacy fields, `_event_name`, counts, and zero-residue token state.

Expected: exact match to the Task 10 manifest.

- [ ] **Step 3: Inspect the complete branch diff against the approved execution baseline**

Ensure no unrelated source, template export, data migration, or Task 7C work entered the branch.

- [ ] **Step 4: Open the implementation PR**

PR body must identify:

```text
approved execution baseline
implementation head SHA
source test counts
runtime QA token and pass count
source/runtime parity
Template/card structural evidence
protected-state equality
legacy helper/copy lifecycle outcomes
known deferred dependencies
no Task 7C started
```

Do not claim CI evidence if checks are local only.

- [ ] **Step 5: STOP**

Do not merge. Do not begin Task 7C, Person migration, reverse Product relationship design, or data migration. Wait for independent review verdict `APPROVE`, `REVISE`, or `BLOCK`.

---

# Plan Self-Review Checklist

Before this plan is considered ready for implementation approval, verify:

- Every Task 7B spec section maps to at least one task above.
- `ProductPeopleResolver` semantics are not altered by the plan.
- Product People and source-Event adapters remain separate classes and authorities.
- Empty Product People frontend behavior and builder behavior are both explicitly tested.
- Malformed source-Event and no-ACF paths are explicitly tested.
- New Product Person Card preserves the approved Product presentation including `post_excerpt` short bio.
- Template 124 non-target structural isolation has before/after proof.
- `product_medewerkers` and `product_source_event` retirement is caller-gated.
- `9-acf-product-copy.php` retirement is dependency-gated, explicitly including Template 772 reverse Product behavior.
- Runtime QA owns/cleans all temporary data and uses the existing Tickera side-effect ownership rule.
- `_event_name`, protected legacy fields, and EXCLUDE edges are never migrated or rewritten.
- Final evidence and PR stop conditions are explicit.
