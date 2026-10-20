# Product People Foundation 7A Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the source-controlled Product People ACF field group and read-only `ProductPeopleResolver`, prove event-derived/manual/unconfigured behavior in source tests and guarded runtime QA, and leave every existing Product consumer and legacy Product relationship unchanged.

**Architecture:** `ProductPeopleFields` registers the two canonical Product People ACF fields in PHP. `ProductPeopleResolver` reads an explicit stored mode, resolves either ordered manual `person[]` values or live Event-derived People through `EventParticipationRepository`, filters public Person endpoints, and never writes or falls back to legacy Medewerker data. Task 7A ends with canonical relationship authority only; Product Single 124 remains legacy for Task 7B.

**Tech Stack:** WordPress, WooCommerce Product posts, ACF Pro local PHP field registration, PHP 8+, Voelgoed Platform Core, existing standalone PHP test harness, Novamira/WP-CLI runtime operations, Git worktrees.

**Spec:** `docs/superpowers/specs/2026-10-19-product-people-foundation-design.md`

## Global Constraints

- Planning baseline is merged `main` at `600e7497cc41a4fe8eaffc1f4fa411b0d9774c55`.
- Implementation must not start until this plan has been independently approved and merged. The execution handoff must name the exact then-current `origin/main` SHA. If `origin/main` contains unrelated commits after `600e7497cc41a4fe8eaffc1f4fa411b0d9774c55`, STOP for re-baselining rather than silently adapting.
- Execute only in isolated branch/worktree `feat/product-people-foundation-7a` using the repository's Superpowers worktree workflow.
- Platform Core source changes must land under `site-code/voelgoed-platform-core/` before any runtime deployment.
- Product People ACF ownership is PHP local registration in Platform Core. Do not add or sync ACF Local JSON for Task 7A.
- `produk_bron_event` remains the Product source/provenance Event concept. Do not re-register, rename, migrate, or rewrite it.
- `_event_name` remains a separate Event-commerce relationship and is completely out of scope.
- `produk_medewerkers` remains legacy. The resolver must never read it and Task 7A must not modify its values.
- `product_people_mode` is an optional ACF Select with stored values only `event` or `manual`, blank allowed, no default, `allow_null=1`, `multiple=0`, `return_format=value`.
- `product_people_override` is an optional ACF Relationship to `person`, zero or more ordered selections, `min=0`, `max=0`, return IDs, shown only when mode is `manual`.
- Absent or empty mode is `UNCONFIGURED` and resolves to `[]`, including after an ordinary ACF save with the mode blank.
- `manual` is full replacement. Empty manual override intentionally resolves to `[]`; it never falls back to Event.
- `event` ignores manual override and resolves live from `produk_bron_event` through `EventParticipationRepository`.
- Event-derived and manual results expose only valid `person` posts with `post_status=publish`.
- Preserve source ordering and de-duplicate by first occurrence.
- Product publication status is not an eligibility requirement; draft/private Product editor contexts may resolve People.
- No Product People write/sync hook, no reverse Product API, no persistent cache, no cron, no background synchronization.
- Do not modify Product Single 124, Medewerker Single 772, Event Single 774, Shared Medewerker Card `6b463f`, Event Participant Card `194bba`, Bricks data, or global styles.
- Do not modify FluentSnippets `9-acf-product-copy.php` or `10-vg-bricks-medewerker-queries.php`.
- Existing Products must not acquire `product_people_mode` or `product_people_override` merely because the field group is registered.
- Protected legacy Products 765, 767, and 768 and Event 763 must compare equal before/after runtime QA for all protected fields.
- Persistent Person count and persistent `event_participation` count must both end at zero.
- Temporary QA records must carry a unique `[QA][AUTOMATED] Task 7A` ownership marker and be hard-deleted; no QA residue may remain in Trash or post meta.
- Use Novamira as the primary runtime interface when available; use local filesystem/WP-CLI only as a documented fallback where Novamira cannot perform the required operation safely.
- Direct SQL writes are prohibited.
- Any stop condition from the spec is binding. Do not invent fallback architecture during execution.

## Review Focus

1. **Blank-mode Product save:** a Product with `produk_bron_event` but no non-empty canonical mode must remain `UNCONFIGURED` before and after an ACF save with the mode blank; resolver result stays `[]`.
2. **Unpublished Person leak:** a published Event Participation pointing at a `draft` or `private` Person may be returned by the Event repository layer, but Product People must filter that Person out.
3. **Malformed stored relationship values:** non-array manual data, non-numeric IDs, zero/negative IDs, missing posts, wrong post types, and malformed source Event values must fail closed without warnings or legacy fallback.
4. **Inactive-field leakage:** stale manual override data must not affect `event` mode, and stale `produk_bron_event` must not affect `manual` mode.
5. **Legacy/write regression:** populated `produk_medewerkers` and the existing copy automation must have no influence on resolver output; calling `people_for_product()` must not mutate Product meta, Event Participation, or any legacy relationship.

---

# File Structure

## Product module

Create:

```text
site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php
site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php
```

## Source tests

Create:

```text
site-code/voelgoed-platform-core/tests/ProductPeopleFieldsTest.php
site-code/voelgoed-platform-core/tests/ProductPeopleResolverTest.php
site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php
site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Modify only if a missing generic test primitive is proven necessary:

```text
site-code/voelgoed-platform-core/tests/bootstrap.php
```

Do not add Product-specific behavior to the shared bootstrap beyond generic WordPress/ACF test support.

## Platform Core wiring

Modify:

```text
site-code/voelgoed-platform-core/integration.patch
site-code/voelgoed-platform-core/README.md
```

## Runtime QA

Create:

```text
site-code/runtime-tests/product-people-qa.php
```

## Evidence and architecture docs

Create:

```text
docs/architecture/PRODUCT_PEOPLE_IMPLEMENTATION.md
docs/architecture/POST_TASK_7A_CURRENT_STATE.md
docs/architecture/PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json
```

Modify:

```text
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md
docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv
docs/architecture/PERSON_CONSUMER_INVENTORY.csv
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Historical Task 5C/6B/6C evidence files remain historical and must not be rewritten.

---

# Canonical PHP Interfaces

## `ProductPeopleFields`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Constants:

```php
GROUP_KEY = 'group_vg_product_people'
MODE_FIELD_KEY = 'field_vg_product_people_mode'
OVERRIDE_FIELD_KEY = 'field_vg_product_people_override'
MODE_FIELD = 'product_people_mode'
OVERRIDE_FIELD = 'product_people_override'
MODE_EVENT = 'event'
MODE_MANUAL = 'manual'
```

Public interface:

```php
public function register(): void;
public function register_field_group(): void;
```

`register()` owns only the `acf/init` lifecycle registration for the Product People field group.

The field group must be located only on `post_type == product` and must not define `produk_bron_event` or `produk_medewerkers`.

## `ProductPeopleResolver`

Namespace:

```php
Voelgoed\PlatformCore\Product
```

Constants:

```php
PRODUCT_POST_TYPE = 'product'
PERSON_POST_TYPE = 'person'
PERSON_ACTIVE_STATUS = 'publish'
SOURCE_EVENT_FIELD = 'produk_bron_event'
```

Public interface:

```php
public function __construct(?EventParticipationRepository $repository = null);

/** @return list<int> */
public function people_for_product(int $product_id): array;
```

Concrete read contract:

```text
invalid/non-product
→ []

raw stored product_people_mode absent or empty
→ []

mode = manual
→ read only product_people_override
→ require array-shaped relationship value
→ normalize positive integer/numeric-string IDs
→ preserve order
→ first occurrence wins
→ keep only existing published person posts
→ [] when nothing survives

mode = event
→ ignore product_people_override
→ read only produk_bron_event
→ accept one positive integer/numeric-string Event ID; malformed arrays/objects fail closed
→ validate Event through EventParticipationRepository::is_valid_event()
→ obtain ordered Person IDs through EventParticipationRepository::people_for_event()
→ defensively de-duplicate first occurrence
→ keep only existing published person posts
→ [] when nothing survives

other non-empty mode
→ []
```

Mode detection must use the raw stored Product meta value, not an ACF default. Because absent and empty are intentionally equivalent, `get_post_meta($product_id, ProductPeopleFields::MODE_FIELD, true)` is sufficient for the mode gate.

Use ACF reads for the relationship values:

```php
get_field(ProductPeopleFields::OVERRIDE_FIELD, $product_id, false)
get_field(self::SOURCE_EVENT_FIELD, $product_id, false)
```

If ACF field reads are unavailable, fail closed with `[]`.

Do not implement memoization in Task 7A. The spec permits request-scoped memoization but does not require it; omitting it keeps freshness and mutation tests unambiguous.

---

# Task 1: Establish the Approved Execution Baseline and Worktree

**Files:** none

**Interfaces:**
- Consumes: independently approved spec and this independently approved plan.
- Produces: isolated implementation branch with an exact approved merge base.

- [ ] **Step 1: Verify the plan is merged before implementation**

Run from the stable checkout:

```bash
git fetch origin
git rev-parse origin/main
```

Compare the result with the exact execution SHA named by the independent reviewer after the plan merge.

Expected:

```text
exact match to reviewer-supplied execution SHA
```

If no exact execution SHA has been supplied, or if `origin/main` differs, STOP.

- [ ] **Step 2: Invoke the worktree workflow**

Use the required Superpowers `using-git-worktrees` skill before implementation.

Reuse `.worktrees/feat-product-people-foundation-7a` only if it already exists, is on branch `feat/product-people-foundation-7a`, is clean, and has the exact approved merge base. Otherwise create the isolated worktree according to the skill.

- [ ] **Step 3: Verify isolation**

```bash
git status --short
git merge-base HEAD origin/main
git rev-parse HEAD
```

Expected: clean worktree and exact approved baseline.

- [ ] **Step 4: Perform read-only source/runtime preflight**

Before editing source, prove:

```text
spec exists at approved path
EventParticipationRepository exists and exposes is_valid_event() and people_for_event()
Product include directory does not contain divergent pre-existing Task 7A classes
active Product source field still resolves as produk_bron_event → tc_events
_event_name remains a distinct commerce relationship
no active ACF field/group already owns:
  group_vg_product_people
  field_vg_product_people_mode
  field_vg_product_people_override
  product_people_mode
  product_people_override
```

Enumerate all existing Products and record whether either new canonical meta key already exists. Any pre-existing non-empty canonical Task 7A value is a STOP condition.

No writes are allowed in this step.

---

# Task 2: Product People ACF Fields — TDD

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductPeopleFieldsTest.php`
- Create: `site-code/voelgoed-platform-core/tests/run-product-people-tests.php`

**Interfaces:**
- Produces: `ProductPeopleFields` constants and lifecycle registration consumed by the resolver and runtime integration.

- [ ] **Step 1: Write the failing field-contract tests**

`ProductPeopleFieldsTest` must include focused tests asserting:

```text
register() adds exactly one acf/init callback for field registration
register_field_group() is a no-op when acf_add_local_field_group is unavailable
field group key = group_vg_product_people
title = Product People
location = post_type == product only

product_people_mode:
  key = field_vg_product_people_mode
  type = select
  choices exactly event/manual
  default_value empty
  allow_null = 1
  required = 0
  multiple = 0
  return_format = value
  instructions explicitly say blank keeps Product unconfigured

product_people_override:
  key = field_vg_product_people_override
  type = relationship
  post_type exactly person
  required = 0
  min = 0
  max = 0
  return_format = id
  conditional logic = mode field equals manual

registration does not create or mutate Product meta
field group does not define produk_bron_event
field group does not define produk_medewerkers
```

`run-product-people-tests.php` initially loads bootstrap plus `ProductPeopleFieldsTest.php` and executes `test_*` methods using the existing runner convention.

- [ ] **Step 2: Run the focused test and verify RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: failure because `ProductPeopleFields` does not yet exist.

- [ ] **Step 3: Implement `ProductPeopleFields` minimally**

Create the constants and methods defined in this plan.

Use `add_action('acf/init', [$this, 'register_field_group'])` in `register()`.

Use one `acf_add_local_field_group()` definition. Do not add save hooks, validation hooks, defaults, legacy fields, reverse fields, or JSON ownership.

- [ ] **Step 4: Run the focused test and verify GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all Product People field tests pass.

- [ ] **Step 5: PHP lint**

```bash
php -l site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php
php -l site-code/voelgoed-platform-core/tests/ProductPeopleFieldsTest.php
php -l site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: no syntax errors.

- [ ] **Step 6: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductPeopleFields.php \
        site-code/voelgoed-platform-core/tests/ProductPeopleFieldsTest.php \
        site-code/voelgoed-platform-core/tests/run-product-people-tests.php
git commit -m "feat: register canonical Product People fields"
```

---

# Task 3: Product People Resolver — TDD

**Files:**
- Create: `site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php`
- Create: `site-code/voelgoed-platform-core/tests/ProductPeopleResolverTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-people-tests.php`
- Modify only if generic support is genuinely missing: `site-code/voelgoed-platform-core/tests/bootstrap.php`

**Interfaces:**
- Consumes: `ProductPeopleFields` constants and `EventParticipationRepository`.
- Produces: `ProductPeopleResolver::__construct(?EventParticipationRepository $repository = null)` and `people_for_product(int $product_id): array`.

- [ ] **Step 1: Extend the runner and write failing resolver tests**

Add `ProductPeopleResolverTest` to the runner and cover at minimum these observable contracts:

```text
1. invalid Product ID → []
2. non-product post → []
3. draft Product is allowed to resolve when otherwise configured
4. absent mode + valid source Event → []
5. empty stored mode + valid source Event → []
6. invalid non-empty mode → []
7. event mode + no source Event → []
8. event mode + wrong-type/trashed source Event → []
9. event mode preserves Event repository order
10. event mode filters missing/wrong-type Person endpoints
11. event mode filters draft Person
12. event mode filters private Person
13. event mode de-duplicates Person IDs preserving first occurrence
14. event mode ignores populated manual override
15. manual mode preserves relationship order
16. manual mode accepts numeric-string Person IDs and returns integers
17. manual mode empty override → [] despite valid source Event
18. manual mode filters zero/negative/non-numeric/missing/wrong-type IDs
19. manual mode filters draft/private Person
20. manual mode de-duplicates preserving first occurrence
21. manual mode ignores populated source Event
22. malformed non-array manual value fails closed
23. malformed array/object source Event fails closed
24. populated produk_medewerkers never becomes fallback
25. resolver performs no update_field/wp_update_post/Product-meta writes
```

Fixtures must register only the minimal test ACF definitions needed for `produk_bron_event`, Event Participation endpoint fields, and the new Product People fields. Do not redefine production behavior in the fixture.

For the unpublished Event-derived tests, deliberately create a `publish` participation whose Person endpoint is `draft`/`private` to prove Product-level publication filtering beyond repository endpoint validation.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: resolver tests fail because `ProductPeopleResolver` is missing.

- [ ] **Step 3: Implement the resolver contract**

Implement exactly:

```php
public function __construct(?EventParticipationRepository $repository = null);
public function people_for_product(int $product_id): array;
```

Implementation decisions locked by this plan:

```text
validate Product through get_post_type()
read mode through get_post_meta(..., true)
mode comparison is exact; whitespace/case variants are INVALID, not normalized into valid modes
manual relationship must be array-shaped; null/false/empty becomes []
manual elements accept positive integers and digit-only numeric strings; discard everything else
event source accepts one positive integer or digit-only numeric string; arrays/objects are invalid
use EventParticipationRepository::is_valid_event() before Event resolution
use EventParticipationRepository::people_for_event() for ordered Event-derived Person IDs
run the same final Person eligibility filter for manual and Event-derived IDs:
  existing post
  post_type === person
  post_status === publish
  first occurrence only
return list<int>
```

Do not add write hooks, caches, reverse lookups, HTML/Bricks knowledge, role data, or legacy fallback.

- [ ] **Step 4: Run focused tests and verify GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all Product People field/resolver tests pass.

- [ ] **Step 5: Run Event repository regression**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

Expected baseline: 65 Event Participation tests passed.

- [ ] **Step 6: PHP lint**

```bash
php -l site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php
php -l site-code/voelgoed-platform-core/tests/ProductPeopleResolverTest.php
```

Expected: no syntax errors.

- [ ] **Step 7: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Product/ProductPeopleResolver.php \
        site-code/voelgoed-platform-core/tests/ProductPeopleResolverTest.php \
        site-code/voelgoed-platform-core/tests/run-product-people-tests.php \
        site-code/voelgoed-platform-core/tests/bootstrap.php
git commit -m "feat: resolve canonical Product People"
```

If `bootstrap.php` was not modified, omit it from `git add`.

---

# Task 4: Platform Core Integration Wiring — TDD

**Files:**
- Create: `site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php`
- Modify: `site-code/voelgoed-platform-core/tests/run-product-people-tests.php`
- Modify: `site-code/voelgoed-platform-core/integration.patch`
- Modify: `site-code/voelgoed-platform-core/README.md`

**Interfaces:**
- Consumes: Product People classes from Tasks 2–3.
- Produces: source-controlled runtime bootstrap contract that loads both Product classes and registers only the lifecycle field component.

- [ ] **Step 1: Write the failing integration-patch test**

`ProductPeopleIntegrationTest` must parse `integration.patch` and assert it contains semantic additions for:

```text
voelgoed-platform-core.php requires:
  includes/Product/ProductPeopleFields.php
  includes/Product/ProductPeopleResolver.php

includes/Plugin.php imports:
  Voelgoed\PlatformCore\Product\ProductPeopleFields

includes/Plugin.php registers component:
  key product_people_fields
  class ProductPeopleFields::class
```

Also assert the patch does **not** register `ProductPeopleResolver` as a lifecycle component.

- [ ] **Step 2: Run focused tests and verify RED**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: integration test fails because the patch is not wired yet.

- [ ] **Step 3: Update `integration.patch`**

Add only the Product People bootstrap/load/register changes described above, preserving all existing Taxonomy/Event integration lines.

- [ ] **Step 4: Update Platform Core README**

Add a short `Product People foundation` section documenting:

```text
ProductPeopleFields owns the PHP-local ACF fields
ProductPeopleResolver is read-only canonical Product→Person authority
no Product consumer has migrated yet
focused test command:
  php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
runtime deployment remains a copied plugin deployment; no ACF JSON sync is used for Task 7A
```

Do not rewrite unrelated module documentation.

- [ ] **Step 5: Run focused tests and verify GREEN**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: all Product People tests pass.

- [ ] **Step 6: Validate patch syntax/applicability against a clean runtime-bootstrap fixture or copied runtime snapshot**

Use the existing repository validation convention. At minimum prove the patch has no malformed hunks and semantically matches the current runtime bootstrap before deployment.

If the runtime bootstrap has drifted such that the source patch cannot be applied semantically without unrelated edits, STOP.

- [ ] **Step 7: Commit**

```bash
git add site-code/voelgoed-platform-core/integration.patch \
        site-code/voelgoed-platform-core/README.md \
        site-code/voelgoed-platform-core/tests/ProductPeopleIntegrationTest.php \
        site-code/voelgoed-platform-core/tests/run-product-people-tests.php
git commit -m "feat: wire Product People into Platform Core"
```

---

# Task 5: Guarded Product People Runtime QA Script

**Files:**
- Create: `site-code/runtime-tests/product-people-qa.php`

**Interfaces:**
- Consumes: deployed `ProductPeopleFields`, `ProductPeopleResolver`, `EventParticipationRepository`, and `EventParticipationService`.
- Produces: one self-cleaning runtime QA execution with machine-readable summary data for the runtime manifest.

- [ ] **Step 1: Implement preflight guardrails before any mutation**

The script must STOP before creating records unless all are true:

```text
WordPress + ACF runtime loaded
classes exist:
  ProductPeopleFields
  ProductPeopleResolver
  EventParticipationRepository
  EventParticipationService
post types exist:
  product
  tc_events
  person
  event_participation
persistent Person count = 0
persistent event_participation count = 0
new Product People ACF group/fields match the locked runtime schema
Products 765/767/768 exist with expected legacy relationship values from the approved baseline
Event 763 retains event_medewerkers [753,757]
no existing Product has a non-empty product_people_mode or product_people_override before QA
legacy snippet/helper hashes were captured externally before this script is run
```

Use a unique run token and ownership meta key:

```text
_vg_task_7a_qa_owner
```

Ownership value must include:

```text
[QA][AUTOMATED] Task 7A
```

- [ ] **Step 2: Snapshot protected WordPress state**

Capture normalized before-state for:

```text
Products 765, 767, 768:
  post status/title/slug/modified
  produk_bron_event + field-key companion meta
  produk_medewerkers + field-key companion meta
  product_people_mode + companion meta
  product_people_override + companion meta

Event 763:
  post identity/status/modified
  event_medewerkers + companion meta

profiles 753–757:
  identity/status/modified and protected canonical/legacy relationship state

all Product IDs:
  whether product_people_mode/override meta exists and its value

persistent Person count
persistent event_participation count
event_role vocabulary/relationship counts
content_topic vocabulary/root counts
```

- [ ] **Step 3: Create only owned temporary QA records**

Create:

```text
1 temporary published tc_events Event with zero event_medewerkers
3 temporary Persons:
  Person A publish
  Person B publish
  Person C draft
1 temporary Product in draft status
3 published Event Participations through EventParticipationService::create():
  Event → Person B display_order 10
  Event → Person C display_order 15
  Event → Person A display_order 20
```

All records must receive the ownership meta marker immediately after ID creation. Track each created ID before any later write that could fail so `finally` cleanup can still own it safely.

No roles are required; Task 7A must not create or modify `event_role` vocabulary.

- [ ] **Step 4: Prove UNCONFIGURED and blank-save behavior**

On the temporary Product:

```text
set produk_bron_event to temporary Event using ACF API
leave product_people_mode absent
resolver → []
```

Then simulate a real ACF blank field save using the loaded ACF save path (`acf_save_post()` with the submitted Task 7A mode field blank). If the runtime cannot exercise the ACF save function safely, STOP rather than replacing this proof with a direct meta write.

Assert after save:

```text
stored mode is absent or empty, never event
resolver → []
```

Restore `$_POST`/request globals after the proof.

- [ ] **Step 5: Prove Event-derived behavior**

Set mode through ACF to `event`.

Expected resolver result:

```text
[Person B, Person A]
```

This simultaneously proves:

```text
Event Participation display order preserved
published participation → draft Person C is filtered by Product resolver
Product itself may be draft
```

Populate a stale manual override containing only Person A and prove Event mode still returns `[Person B, Person A]`.

- [ ] **Step 6: Prove Manual behavior**

Set mode `manual` and override `[Person A, Person B]`.

Expected:

```text
[Person A, Person B]
```

Set manual override empty while source Event remains valid.

Expected:

```text
[]
```

For malformed endpoint proof on the owned QA Product only, inject a temporary corrupt manual relationship value that contains a valid Person, the Event ID, and draft Person C. Use ACF API where it permits the value; direct raw-meta injection is allowed only for this explicitly malformed QA state and must be restored before the next scenario.

Expected: only the valid published Person survives.

- [ ] **Step 7: Prove invalid mode, invalid source, and legacy isolation**

Using only the owned QA Product:

```text
invalid non-empty mode → []
event mode + wrong-type source endpoint → []
event mode + missing source → []
event mode + malformed source value → []
manual mode ignores valid source Event
```

For the malformed source proof, use only the owned QA Product. With mode `event`, set `produk_bron_event` to an array/object-shaped value that violates the single-ID contract. Try the ACF update API first. If ACF rejects or normalizes the malformed value, inject it through raw Product meta for this QA case. After raw-meta injection, clear the ACF value cache for this Product field before resolution. Assert `[]`, then restore the valid source Event through ACF and confirm the field reads that Event before continuing. Record the result as `malformed_source_event_returns_empty` in the JSON summary and copy it into the runtime manifest.

Populate `produk_medewerkers` on the QA Product with an existing legacy Medewerker ID and prove it never affects resolver output.

Do not alter any real Product legacy field.

- [ ] **Step 8: Guarded cleanup in `finally`**

Before each hard delete, prove ownership by exact meta token and expected ID.

Hard-delete in dependency-safe order:

```text
temporary Product
3 temporary Event Participations
temporary Persons
temporary Event
```

After deletion, prove:

```text
no owned ID remains in any post status
no owned ID retains post meta
no owned participation retains taxonomy relationships
no run token remains in posts or post meta
nothing remains in Trash
persistent Person count = 0
persistent event_participation count = 0
```

- [ ] **Step 9: Compare protected snapshots**

All protected WordPress state must match the before snapshot exactly, except no exception is needed for Task 7A because new fields are PHP-local definitions rather than persisted ACF field-group posts.

- [ ] **Step 10: Emit machine-readable QA summary**

Print one JSON object containing at minimum:

```text
run token
owned IDs
all scenario PASS/FAIL results
malformed_source_event_returns_empty PASS/FAIL result
before/after persistent counts
protected-state comparison result
cleanup residue counts
```

Do not include credentials or unrelated content.

- [ ] **Step 11: Lint and commit**

```bash
php -l site-code/runtime-tests/product-people-qa.php
git add site-code/runtime-tests/product-people-qa.php
git commit -m "test: add Product People runtime QA"
```

Do not execute runtime QA until Task 6 deployment preflight succeeds.

---

# Task 6: Runtime Deployment, Field Registration Proof, and QA Execution

This task mutates the local WordPress runtime outside Git. Capture recovery state first.

Runtime plugin:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core
```

Feature source:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/feat-product-people-foundation-7a/site-code/voelgoed-platform-core
```

**Files:** runtime copies only; no new repository source files beyond earlier tasks.

**Interfaces:**
- Consumes: source Product classes, integration patch, runtime QA script.
- Produces: runtime evidence and exact before/after hashes for documentation.

- [ ] **Step 1: Capture full recovery snapshot before deployment**

Hash at minimum:

```text
runtime voelgoed-platform-core.php
runtime includes/Plugin.php
runtime 9-acf-product-copy.php
runtime 10-vg-bricks-medewerker-queries.php
```

Expected legacy snippet/helper hashes unless a separately approved change exists:

```text
9-acf-product-copy.php:
58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70

10-vg-bricks-medewerker-queries.php:
e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2
```

Capture fresh stable hashes/state for:

```text
Template 124
Template 772
Template 774
component 6b463f
component 194bba
full bricks_components option if that is the stable storage-aware comparison available
```

Do not mutate any of those assets.

If runtime Platform Core contains unrelated drift from the merged source outside the known deployment-copy model, STOP before copying Task 7A files.

- [ ] **Step 2: Capture existing Product canonical-meta baseline**

Read all Product IDs and record raw meta presence/value for:

```text
product_people_mode
_product_people_mode
product_people_override
_product_people_override
```

Expected before deployment: no non-empty canonical Task 7A values on existing Products.

This snapshot is used to prove field registration itself performs zero Product writes.

- [ ] **Step 3: Deploy Product source files**

Create runtime `includes/Product/` if absent and copy exactly:

```text
ProductPeopleFields.php
ProductPeopleResolver.php
```

No other new runtime Product architecture is permitted.

- [ ] **Step 4: Apply `integration.patch` semantically**

Update runtime:

```text
voelgoed-platform-core.php
includes/Plugin.php
```

so the runtime loads both Product classes and registers only `ProductPeopleFields` as a lifecycle component.

Do not hand-edit unrelated bootstrap code.

- [ ] **Step 5: Verify PHP/source-runtime parity**

Run PHP lint on deployed files and bootstrap files.

Compute SHA-256 and require exact source/runtime match for:

```text
ProductPeopleFields.php
ProductPeopleResolver.php
```

Record runtime bootstrap before/after hashes and prove the semantic diff matches `integration.patch` only.

- [ ] **Step 6: Bootstrap WordPress read-only**

Use a harmless WP-CLI command through Novamira.

Expected:

```text
plugin loads without fatal error
ProductPeopleFields class loaded
ProductPeopleResolver class loaded
```

On failure, restore the runtime snapshot and STOP.

- [ ] **Step 7: Verify the registered ACF contract**

Read the runtime local field group/fields and assert exact locked behavior:

```text
group_vg_product_people exists and is active
location product only
mode type select
choices event/manual
blank allowed
no default
optional
single value
return value
override type relationship
person post type only
min 0
max 0/unlimited
return id
manual-only conditional logic
```

Also verify legacy `produk_bron_event` and `produk_medewerkers` definitions are unchanged.

If field/group keys or names collide with a pre-existing runtime definition, restore and STOP.

- [ ] **Step 8: Prove registration wrote zero Product metadata**

Repeat the all-Product raw canonical-meta snapshot from Step 2.

Expected: exact equality.

No existing Product may gain Task 7A mode/override meta merely from plugin load/field registration.

- [ ] **Step 9: Execute guarded runtime QA**

Run inside the loaded WordPress runtime:

```bash
wp eval-file /home/jcschoeman96/projects/current/vgnova/.worktrees/feat-product-people-foundation-7a/site-code/runtime-tests/product-people-qa.php
```

Use Novamira's WP-CLI ability when available.

Require every scenario to PASS. Do not manually repair a failed assertion and continue.

- [ ] **Step 10: Verify final runtime state**

Require:

```text
Person = 0
event_participation = 0
Task 7A QA Product/Event/Person/participation residue = 0
no Task 7A run token in posts/postmeta
Products 765/767/768 protected state unchanged
Event 763 protected state unchanged
all pre-existing Products retain the same canonical-meta baseline they had before deployment
Template 124 unchanged
Template 772 unchanged
Template 774 unchanged
component 6b463f unchanged
component 194bba unchanged
9-acf-product-copy.php hash unchanged
10-vg-bricks-medewerker-queries.php hash unchanged
_event_name behavior/data unchanged
```

- [ ] **Step 11: Recovery rule if any runtime step fails**

Before leaving the task in a failed state:

```text
run guarded ownership cleanup for any Task 7A QA records
restore runtime voelgoed-platform-core.php and includes/Plugin.php from snapshot
remove only Task 7A Product runtime files if they did not exist before
verify legacy snippet/helper hashes
verify protected Product/Event/Bricks state
```

Do not restore unrelated runtime files or data.

---

# Task 7: Architecture Evidence and Lifecycle Documentation

**Files:**
- Create: `docs/architecture/PRODUCT_PEOPLE_IMPLEMENTATION.md`
- Create: `docs/architecture/POST_TASK_7A_CURRENT_STATE.md`
- Create: `docs/architecture/PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json`
- Modify: `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`
- Modify: `docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv`
- Modify: `docs/architecture/PERSON_CONSUMER_INVENTORY.csv`
- Modify: `docs/architecture/IMPLEMENTATION_LEDGER.md`

**Interfaces:**
- Consumes: final source tests, runtime hashes, QA JSON summary, protected-state comparisons.
- Produces: auditable Task 7A current-state record for Task 7B.

- [ ] **Step 1: Write implementation document from evidence, not intent**

`PRODUCT_PEOPLE_IMPLEMENTATION.md` must record:

```text
exact implementation baseline SHA
ProductPeopleFields keys/settings
ProductPeopleResolver public signature and resolution semantics
explicit UNCONFIGURED/event/manual/invalid behavior
use of EventParticipationRepository::people_for_event()
published Person filtering beyond Event repository endpoint validity
no Product write/sync path
no memoization/caching
no Bricks consumer migration
source/runtime SHA-256 parity
focused/regression test results
runtime QA result and cleanup result
```

- [ ] **Step 2: Write post-Task current-state document**

`POST_TASK_7A_CURRENT_STATE.md` must clearly separate:

```text
CURRENT:
  canonical Product People fields + resolver available
  existing Products still unconfigured
  existing Product consumers still legacy
  produk_medewerkers/copy helper/helper modes retained

TARGET NEXT:
  Task 7B Product Single 124 consumer audit/cutover

DEFER/KEEP_LEGACY:
  Template 772 reverse product consumer
  FluentSnippet helper retirement
  9-acf-product-copy.php retirement

EXCLUDE:
  existing Product→Medewerker edges already classified EXCLUDE remain EXCLUDE
```

Record final persistent counts and protected state.

- [ ] **Step 3: Build `PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json`**

Include exact machine-readable evidence:

```text
planning/spec baseline and execution baseline
source file SHA-256s
runtime file SHA-256s
runtime bootstrap before/after hashes
legacy helper/snippet hashes
ACF field-group normalized schema
all-existing-Product canonical-meta before/after equality result
Products 765/767/768 before/after protected values
Event 763 before/after protected values
Bricks template/component before/after hashes
QA owned IDs
QA scenario results
malformed_source_event_returns_empty result copied from QA JSON summary
cleanup residue counts
final Person/participation counts
focused/regression test counts
```

Use JSON `null` where a value is genuinely not applicable. Store no credentials.

Validate:

```bash
python3 -m json.tool docs/architecture/PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json >/dev/null
```

- [ ] **Step 4: Update Product lifecycle in decommission register**

Revise the Product Event/Person relationship section so it no longer says the target is copied canonical Person IDs.

Record the approved Task 7A architecture:

```text
produk_bron_event = approved source/provenance Event concept; existing values not automatically migrated
product_people_mode + product_people_override = canonical Product People configuration
ProductPeopleResolver = canonical read authority
event mode = live-derived Event Participation → Person
manual mode = explicit replacement Person list
produk_medewerkers = KEEP_LEGACY
9-acf-product-copy.php = KEEP_LEGACY
helper modes = KEEP_LEGACY/REPLACE_LATER
_event_name = KEEP separate commerce relationship
consumer-removal gates remain closed until Task 7B/later caller audits
```

Explicitly state that this Task 7A target supersedes the older planned snapshot-copy-to-canonical-Person target.

- [ ] **Step 5: Update migration/inventory CSVs minimally**

`EVENT_CONSUMER_MIGRATION_MAP.csv`:

```text
Product Single 124 remains legacy/unmigrated
Task 7A canonical Product People authority now exists
Task 7B owns consumer cutover
source-event and commerce-event concepts remain separate
```

`PERSON_CONSUMER_INVENTORY.csv`:

```text
add canonical ProductPeopleResolver authority
add product_people_override → person relationship source
retain Product Single 124 and product_medewerkers as legacy consumers
```

Do not mark any Product consumer migrated.

- [ ] **Step 6: Update implementation ledger**

Add Task 7A with:

```text
approved spec path
approved plan path
execution baseline
foundation-only scope
runtime QA complete
consumer cutover deferred to Task 7B
status = implemented / pending independent PR review
```

Do not fabricate merge SHA before merge.

- [ ] **Step 7: Validate docs**

```bash
python3 - <<'PY'
import csv
for path in [
    'docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv',
    'docs/architecture/PERSON_CONSUMER_INVENTORY.csv',
]:
    with open(path, newline='', encoding='utf-8') as f:
        list(csv.reader(f))
    print('OK', path)
PY

python3 -m json.tool docs/architecture/PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json >/dev/null
git diff --check
```

Expected: all pass.

- [ ] **Step 8: Commit documentation**

```bash
git add docs/architecture
git commit -m "docs: record Product People foundation state"
```

---

# Task 8: Full Verification, PR, and Stop

**Files:** all Task 7A files.

**Interfaces:**
- Consumes: complete implementation and runtime evidence.
- Produces: review-ready feature PR only. No merge.

- [ ] **Step 1: Run focused Product People tests**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
```

Expected: zero failures. Record exact passed count.

- [ ] **Step 2: Run protected regression suites**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
php site-code/person-foundation/tests/run-person-foundation-tests.php
```

Expected existing baselines unless a separately approved merged change altered them:

```text
Event Participation: 65 passed
Event Participation Bricks: 12 passed
Content Topic: 67 passed
Person foundation: 10 passed
```

Any unexpected baseline change requires investigation before PR.

- [ ] **Step 3: Lint all changed PHP**

```bash
git diff --name-only --diff-filter=ACM origin/main...HEAD -- '*.php' | while read -r file; do php -l "$file" || exit 1; done
```

Expected: no syntax errors.

- [ ] **Step 4: Validate source-controlled integration and docs**

```bash
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
python3 -m json.tool docs/architecture/PRODUCT_PEOPLE_7A_RUNTIME_MANIFEST.json >/dev/null
git diff --check
```

Re-run CSV parse validation from Task 7.

- [ ] **Step 5: Re-run final read-only runtime verification**

Without creating new QA records, verify and record:

```text
source/runtime Product class hashes still match
Product People field group still matches schema
all pre-existing Products have unchanged canonical-meta baseline
Person count = 0
event_participation count = 0
Products 765/767/768 unchanged
Event 763 unchanged
Templates/components unchanged
legacy snippet/helper hashes unchanged
```

- [ ] **Step 6: Review final diff for scope**

```bash
git status --short
git diff --stat origin/main...HEAD
git diff --name-only origin/main...HEAD
```

Expected changed paths only within the approved Task 7A file list.

No Bricks export, FluentSnippet source, ACF JSON, Product data export, or unrelated refactor may appear.

- [ ] **Step 7: Open the implementation PR**

Suggested title:

```text
feat: add canonical Product People foundation
```

PR body must include:

```text
execution baseline SHA
feature head SHA
scope summary
new field schema
resolver contract
focused/regression test counts
source/runtime SHA-256 parity
runtime QA scenario result
protected-state result
cleanup result
final Person/event_participation counts
legacy helper/snippet unchanged hashes
explicit statement: Product Single 124 unchanged; Task 7B not started
stop conditions encountered: none, or exact STOP report
```

- [ ] **Step 8: Stop for independent review**

Do not merge.

Do not begin Task 7B.

Do not retire `produk_medewerkers`, `9-acf-product-copy.php`, or any helper mode.

Do not remove the implementation worktree until the independent reviewer gives the next instruction.

---

# Plan Self-Review

## Spec coverage

- Canonical Product People fields: Task 2.
- Explicit blank/absent `UNCONFIGURED` state: Tasks 3, 5, 6.
- Manual full replacement and intentional empty list: Tasks 3 and 5.
- Live Event-derived People from Event Participation: Tasks 3 and 5.
- Published Person filtering, including unpublished repository endpoint case: Tasks 3 and 5.
- Separate `produk_bron_event` and `_event_name`: global constraints, Tasks 1, 7, 8.
- No legacy fallback/read/write: Tasks 3, 5, 6, 8.
- No Product data migration / no Bricks cutover: global constraints and final scope verification.
- Source/runtime parity: Task 6.
- Protected state and cleanup: Tasks 5–6.
- Architecture/current-state/decommission docs: Task 7.
- Task 7B remains separate: Tasks 7–8.

No spec requirement is intentionally left without an owning task.

## Type consistency

The plan uses one public resolver interface throughout:

```php
ProductPeopleResolver::__construct(?EventParticipationRepository $repository = null)
ProductPeopleResolver::people_for_product(int $product_id): array
```

Field names and keys are consistent in source tests, runtime QA, deployment proof, and documentation.

## Proportion / YAGNI

The plan adds only two production classes and one public resolver method. It deliberately omits reverse queries, save synchronization, persistent caching, Bricks adapters, role projection, migrations, and helper retirement because none belong to Task 7A.

## Execution gate

This plan itself does **not** authorize implementation. It must be saved at:

```text
docs/superpowers/plans/2026-10-20-product-people-foundation-7a.md
```

and independently reviewed/approved first. After the plan is merged, the independent reviewer must name the exact new `main` SHA for the implementation worktree baseline.
