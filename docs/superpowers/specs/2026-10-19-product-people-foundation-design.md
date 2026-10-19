# Product People Foundation Design

**Task:** 7A

**Date:** 2026-10-19

**Status:** Written specification submitted for independent review

**Required base:** `main` at `2b9ac1ae4fc73387e04ce42278b2a24ca993faca`

---

## 1. Purpose

Task 7A introduces the canonical Product People foundation in Platform Core.

It defines how a WooCommerce Product resolves canonical Person contributors without migrating existing Product data and without changing any existing Bricks consumer.

Task 7A establishes relationship authority only.

It does not cut Product Single 124 over to the new model.

The approved Product People rule is:

```text
Product
├─ product_people_mode = event
│    └─ produk_bron_event
│         └─ EventParticipationRepository
│              └─ current canonical Persons
│
└─ product_people_mode = manual
     └─ product_people_override
          └─ explicitly selected canonical Persons
```

The canonical relationship is resolved at read time.

Task 7A must not copy Event participants into Product metadata.

---

## 2. Current state

### 2.1 Product relationship fields

The existing ACF group `Medewerker Produkte` currently owns:

```text
produk_bron_event
→ optional single relationship / post-object value
→ target: tc_events
→ returns ID

produk_medewerkers
→ optional multi-value relationship
→ target: medewerker
→ returns IDs
```

These fields remain live legacy/transitional state.

### 2.2 Existing Product people automation

Active runtime snippet:

```text
9-acf-product-copy.php
```

currently hooks Product save and:

1. preserves a non-empty `produk_medewerkers`;
2. otherwise reads `produk_bron_event`;
3. reads that Event's legacy `event_medewerkers`;
4. copies those Medewerker IDs into `produk_medewerkers`.

This snapshot-copy behavior is legacy.

Task 7A does not modify or disable the snippet.

### 2.3 Existing Product consumers

Legacy Product-facing consumers include:

```text
Product Single 124
product_medewerkers helper mode
product_source_event helper mode
event_products helper mode
medewerker_products helper mode
Shared Medewerker Card 6b463f
```

All remain unchanged in Task 7A.

### 2.4 Existing Product data

Known protected legacy Product state includes:

```text
Product 765
→ source Event relationship present

Product 767
→ produk_bron_event = 763
→ produk_medewerkers = [753]

Product 768
→ produk_bron_event = 763
→ produk_medewerkers = [753,757]
```

The known Product→Medewerker edges remain `EXCLUDE` under the existing provenance decisions.

Task 7A must not migrate them.

### 2.5 Canonical Person/Event state

At the approved Task 7A baseline:

```text
persistent Person records:
0

persistent event_participation records:
0

event_role:
4 terms / 0 relationships

content_topic:
55 terms / 7 roots
```

Event 763 retains:

```text
event_medewerkers = [753,757]
canonical Event Participation count = 0
```

Those legacy values remain unchanged.

---

## 3. Locked architectural decisions

### 3.1 Product People default behavior

The approved Product People architecture is:

```text
A — live-derived Event People
with an explicit manual override
```

The canonical Product does not store a copied Event participant snapshot.

When Event-derived mode is active, changes to canonical Event Participation must be reflected on the Product on the next request.

### 3.2 Manual override semantics

Manual mode fully replaces Event-derived People.

There is no merge.

```text
manual mode
→ return exactly the valid manual Person override list

event mode
→ return exactly the valid current Event-derived Person list
```

### 3.3 Explicit mode field

Product People mode must be explicit.

Canonical modes:

```text
event
manual
```

An empty manual list is meaningful and means:

```text
show zero Product People
```

It must not fall back to Event-derived People.

### 3.4 Product source Event versus Event commerce relationship

Two distinct Event↔Product concepts remain separate.

#### Product source/provenance Event

```text
produk_bron_event
```

means the Product's editorial/source Event.

It may drive Event-derived Product People.

#### Event commerce/Product association

```text
_event_name
```

is a separate Tickera/Event-commerce relationship used by the Event-side Product/ticket presentation.

Task 7A must not combine, migrate, rewrite, or reinterpret `*event*name`.

### 3.5 Existing `produk_bron_event`

`produk_bron_event` is retained as the canonical Product source/provenance Event concept.

Task 7A does not create a replacement Event field.

However:

```text
architecture approval
≠
data migration approval
```

Existing stored `produk_bron_event` values are not automatically approved canonical Product People inputs.

### 3.6 Existing `produk_medewerkers`

`produk_medewerkers` remains legacy.

It continues targeting `medewerker`.

It must not be:

- retargeted to `person`;
- renamed;
- copied into the canonical override field;
- used by the Product People resolver;
- migrated during Task 7A.

### 3.7 New canonical Product→Person field

Task 7A introduces a separate canonical manual override field:

```text
product_people_override
→ person[]
```

This prevents legacy Medewerker IDs from becoming ambiguous canonical Person IDs.

---

## 4. Canonical ACF model

Platform Core must register a new source-controlled Product People ACF group.

Conceptual location:

```text
post_type == product
```

The new group owns only the canonical fields introduced by Task 7A.

It must not redefine the legacy runtime ACF fields.

### 4.1 `product_people_mode`

Required contract:

```text
name:
product_people_mode

type:
choice

choices:
event
manual

default UI value:
event

required:
yes
```

The editor-facing labels may be user-friendly equivalents such as:

```text
Event-derived
Manual
```

but the stored values are locked to:

```text
event
manual
```

### 4.2 `product_people_override`

Required contract:

```text
name:
product_people_override

type:
relationship

target post type:
person

return format:
ID

multiple:
yes

required:
no
```

Ordering must preserve the editor-selected relationship order.

The field should be visible only when:

```text
product_people_mode == manual
```

### 4.3 Existing fields

Task 7A must not re-register or modify:

```text
produk_bron_event
produk_medewerkers
```

The canonical resolver may read `produk_bron_event`.

It must never read `produk_medewerkers`.

---

## 5. Explicit migration state

Registering the new ACF fields must not silently configure existing Products.

The Product People resolver must distinguish:

```text
ACF default value
```

from:

```text
explicit stored Product mode
```

The approved states are:

### 5.1 UNCONFIGURED

```text
product_people_mode has no explicit stored value
```

Result:

```text
[]
```

This rule applies even if:

```text
produk_bron_event is populated
```

The ACF UI default `event` must not make an old Product canonical automatically.

### 5.2 EVENT

```text
explicit stored mode = event
```

Result:

```text
produk_bron_event
→ canonical Event Participation
→ canonical Persons
```

### 5.3 MANUAL

```text
explicit stored mode = manual
```

Result:

```text
product_people_override
→ canonical Persons
```

An empty override returns:

```text
[]
```

### 5.4 INVALID

Any stored mode other than:

```text
event
manual
```

returns:

```text
[]
```

Invalid state fails closed.

---

## 6. Platform Core ownership

Task 7A introduces a dedicated Product relationship boundary in Platform Core.

Recommended source structure:

```text
site-code/voelgoed-platform-core/includes/Product/
```

with focused classes conceptually equivalent to:

```text
ProductPeopleFields
ProductPeopleResolver
```

Exact namespaces and bootstrap wiring must follow existing Platform Core conventions.

### 6.1 ProductPeopleFields responsibility

The field-registration class owns:

```text
product_people_mode
product_people_override
```

It must not own:

```text
produk_bron_event
produk_medewerkers
```

### 6.2 ProductPeopleResolver responsibility

The resolver owns the canonical Product→Person read contract.

Primary public contract:

```php
people_for_product(int $product_id): array
```

Return value:

```text
ordered list<int> of canonical Person post IDs
```

The resolver must not return:

- WP_Query objects;
- Bricks query variables;
- HTML;
- presentation metadata;
- Medewerker IDs;
- Event Participation IDs.

### 6.3 Dependency direction

The approved dependency is:

```text
ProductPeopleResolver
        ↓
EventParticipationRepository
```

The reverse dependency is prohibited.

Neither ProductPeopleResolver nor EventParticipationRepository may depend on Bricks.

---

## 7. Product validation

The resolver must fail closed unless:

```text
$product_id > 0
get_post_type($product_id) === 'product'
```

Otherwise:

```text
[]
```

The Product itself does not need to be published for canonical resolution.

This allows the resolver to support editor/preview contexts later.

Public Person endpoints returned by the resolver must be published.

---

## 8. Event-derived resolution

For:

```text
product_people_mode = event
```

the resolver must:

1. completely ignore `product_people_override`;
2. read `produk_bron_event`;
3. normalize it to one valid Event ID;
4. verify the endpoint is `tc_events`;
5. delegate participation selection to `EventParticipationRepository`;
6. preserve repository participation ordering;
7. resolve each participation's canonical Person endpoint;
8. return valid published Person IDs.

The Product resolver must not independently query Event Participation eligibility.

It inherits the Event Participation repository's existing canonical contract.

That includes:

```text
published participation eligibility
valid Event endpoint
valid Person endpoint
display_order ASC
participation ID ASC tie-break
```

### 8.1 No Event fallback

If the source Event is:

- absent;
- malformed;
- wrong post type;
- otherwise invalid;

return:

```text
[]
```

### 8.2 No legacy fallback

Event mode must never fall back to:

```text
produk_medewerkers
event_medewerkers
medewerker
```

### 8.3 Duplicate Person endpoints

If malformed or unexpected upstream data resolves multiple participations to the same Person:

```text
return the Person once
```

Preserve the first participation occurrence.

---

## 9. Manual resolution

For:

```text
product_people_mode = manual
```

the resolver must:

1. completely ignore `produk_bron_event` for People resolution;
2. read `product_people_override`;
3. normalize values to integer IDs;
4. preserve editor-selected order;
5. discard duplicate IDs after the first occurrence;
6. discard missing posts;
7. discard non-`person` posts;
8. discard non-published Persons;
9. return the remaining ordered Person IDs.

An empty manual field returns:

```text
[]
```

There is no Event fallback.

---

## 10. Resolution precedence

The resolver logic must be mechanically equivalent to:

```text
1. validate Product

2. inspect explicitly stored product_people_mode

3. no stored mode
   → []

4. mode = manual
   → resolve only product_people_override

5. mode = event
   → resolve only produk_bron_event
   → EventParticipationRepository
   → canonical Person IDs

6. anything else
   → []
```

The resolver must not implement:

```text
manual override empty
→ fall back to Event
```

---

## 11. Read-only authority

ProductPeopleResolver is read-only.

It must never:

- write `product_people_mode`;
- write `product_people_override`;
- write `produk_medewerkers`;
- write `produk_bron_event`;
- create Event Participation records;
- update Event Participation records;
- synchronize Event participants into Product meta;
- hook Product save to persist derived People.

The relationship is derived at read time.

---

## 12. Caching

Task 7A may use request-scoped memoization only.

Conceptually:

```text
product_id → resolved Person IDs
```

No persistent Product People cache is approved.

Do not add:

- transients;
- options;
- background synchronization;
- cache invalidation hooks;
- cron jobs.

Changes to Event Participation or manual Product People should become visible on the next request.

---

## 13. Existing legacy Product mechanisms

Task 7A must leave these unchanged:

```text
9-acf-product-copy.php

10-vg-bricks-medewerker-queries.php

event_products helper mode
medewerker_products helper mode
product_medewerkers helper mode
product_source_event helper mode
```

No helper mode is retired during Task 7A.

The existence of ProductPeopleResolver is not sufficient evidence that any legacy consumer is safe to remove.

---

## 14. Bricks boundary

Task 7A makes no Bricks consumer changes.

Explicitly unchanged:

```text
Product Single 124
Medewerker Single 772
Event Single 774
Shared Medewerker Card 6b463f
Event Participant Card 194bba
```

Task 7A must not add ProductPeopleResolver logic directly to Bricks templates.

A later Task 7B will define the presentation adapter after a fresh Product Single 124 audit.

---

## 15. Product/Event commerce boundary

Task 7A must not modify:

```text
_event_name
```

or Event Single 774's Product/ticket query.

The approved distinction is:

```text
produk_bron_event
→ Product source/provenance Event

_event_name
→ Event commerce/Product association
```

These relationships remain separate until a future architecture task explicitly changes that decision.

---

## 16. Source implementation scope

Persistent source-controlled implementation may include:

```text
Product People ACF registration
ProductPeopleResolver
Platform Core bootstrap/integration registration
unit/regression tests
runtime QA helpers
integration.patch updates
architecture documentation
```

Implementation must follow existing Platform Core conventions.

No unrelated Product refactoring is approved.

---

## 17. Minimum regression coverage

Task 7A must include dedicated Product People tests.

At minimum:

1. invalid Product ID returns `[]`;
2. non-Product post returns `[]`;
3. unconfigured Product returns `[]`;
4. explicit event mode with no source Event returns `[]`;
5. event mode delegates participation selection to EventParticipationRepository;
6. event mode preserves canonical participation ordering;
7. event mode filters invalid Person endpoints;
8. event mode de-duplicates Person IDs preserving first occurrence;
9. event mode ignores populated manual override;
10. manual mode preserves override order;
11. manual mode allows intentional empty `[]`;
12. manual mode filters non-Person IDs;
13. manual mode filters unpublished Person endpoints;
14. manual mode de-duplicates preserving first occurrence;
15. manual mode ignores `produk_bron_event`;
16. invalid mode returns `[]`;
17. `produk_medewerkers` is never used as fallback;
18. resolution performs no Product/relationship writes.

Existing established suites must remain passing, especially:

```text
Event Participation
Event Participation Bricks
Person Foundation
ACF schema
Content Topic
```

Task 7A must not regress Task 6C.

---

## 18. Stored-mode detection gate

The implementation must prove that it can distinguish:

```text
mode field absent
```

from:

```text
explicit stored "event"
```

The observable contract is:

```text
old Product
+ no stored product_people_mode
+ populated produk_bron_event
→ []
```

If the chosen ACF/WordPress implementation cannot preserve this distinction:

```text
STOP
```

Do not weaken the contract by treating ACF's default value as explicit migration state.

---

## 19. Runtime QA

Task 7A may use guarded temporary runtime data.

Temporary records may include:

```text
temporary Event
temporary Persons
temporary Event Participations
temporary Products
```

Use the smallest dataset sufficient to verify the contract.

Every temporary object must carry a unique Task 7A ownership marker.

### 19.1 Required runtime scenarios

At minimum prove:

#### QA 1 — unconfigured Product

```text
valid produk_bron_event
no explicitly stored product_people_mode

result:
[]
```

#### QA 2 — Event-derived

```text
mode = event
source Event has two canonical Event Participations

result:
canonical Person IDs
in Event Participation order
```

#### QA 3 — Event mode ignores manual data

```text
mode = event
manual override populated

result:
Event-derived Persons only
```

#### QA 4 — Manual mode

```text
mode = manual
manual override contains valid Persons

result:
manual Persons
in editor-selected order
```

#### QA 5 — Intentional empty manual override

```text
mode = manual
manual override empty
source Event valid

result:
[]
```

#### QA 6 — invalid mode

```text
mode malformed

result:
[]
```

#### QA 7 — legacy isolation

```text
produk_medewerkers populated

result:
legacy field never influences canonical resolver
```

#### QA 8 — invalid manual endpoints

Non-Person or unpublished endpoints are excluded.

#### QA 9 — invalid source Event

Missing, malformed, or wrong-type source Event returns `[]`.

---

## 20. Runtime cleanup

Temporary QA records must be hard-deleted after verification.

Cleanup order must respect dependency safety.

The implementation plan may refine the exact order, but cleanup must ensure no dependent Task 7A record is left behind.

Final QA verification must prove:

```text
Task 7A temporary Products:
0

Task 7A temporary Persons:
0

Task 7A temporary Event Participations:
0

Task 7A temporary Events:
0
```

and restore the persistent canonical baseline:

```text
Person:
0

event_participation:
0
```

unless a separately approved merged task legitimately changes those counts before implementation, in which case Task 7A must stop for re-baselining.

---

## 21. Protected legacy state

Before runtime QA, capture protected state for at minimum:

```text
Products 765
Product 767
Product 768

produk_bron_event
produk_medewerkers

Event 763
event_medewerkers

profiles 753–757

Person persistent count
event_participation persistent count
```

Protect the current executable legacy mechanisms:

```text
9-acf-product-copy.php
10-vg-bricks-medewerker-queries.php
```

using exact runtime hashes.

Also protect relevant consumer state, using stable whole-record/template/component evidence where available:

```text
Template 124
Template 772
Template 774
component 6b463f
component 194bba
```

After QA, protected state must compare equal except for explicitly approved Task 7A source/runtime deployment and new ACF field registration.

---

## 22. No implicit writes to existing Products

Registering the ACF group must not populate Product metadata.

After registration, prove that protected existing Products did not acquire stored values for:

```text
product_people_mode
product_people_override
```

The UI default:

```text
event
```

is not a migration.

---

## 23. Platform Core deployment

Task 7A may deploy the approved Product People foundation to the active Platform Core runtime for QA.

Required source/runtime evidence:

```text
source ProductPeopleFields hash
runtime ProductPeopleFields hash
MATCH

source ProductPeopleResolver hash
runtime ProductPeopleResolver hash
MATCH
```

Also verify:

```text
classes loaded
field group registered
resolver callable
bootstrap/integration registration active
integration.patch matches source integration
```

Do not redeploy unrelated Platform Core code merely to normalize runtime state.

If unrelated runtime drift is discovered:

```text
STOP
```

and report it.

---

## 24. ACF runtime evidence

Runtime verification must prove:

```text
Product People field group:
registered

product_people_mode:
event | manual

UI default:
event

product_people_override:
targets person only

multiple:
yes

relationship order:
preserved

manual conditional display:
active
```

Also prove the legacy ACF definitions remain unchanged:

```text
produk_bron_event
produk_medewerkers
```

---

## 25. Persistent changes permitted

Task 7A may persist only:

```text
Platform Core Product People source
Platform Core Product People runtime deployment
new canonical Product People ACF definitions
source-controlled tests/QA support
architecture/evidence documents
```

Task 7A must not persist:

```text
Product mode values on existing Products
Product manual Person override values
new canonical Person records
new persistent Event Participation records
legacy field migration
legacy Product relationship changes
Bricks template mutations
Bricks component mutations
FluentSnippets mutations
Tickera commerce changes
```

---

## 26. Lifecycle state after Task 7A

### `produk_bron_event`

```text
concept:
approved canonical Product source/provenance Event

runtime/data state:
transitional

existing stored values:
not automatically migrated
```

Lifecycle:

```text
ADAPT
```

### `produk_medewerkers`

```text
CURRENT LEGACY
KEEP_LEGACY
```

### `9-acf-product-copy.php`

```text
CURRENT LEGACY
KEEP_LEGACY
```

### `product_medewerkers`

```text
KEEP_LEGACY
REPLACE_LATER
```

### `product_source_event`

```text
KEEP_LEGACY
REPLACE_LATER
```

### `event_products`

```text
KEEP_LEGACY
REPLACE_LATER
```

### `medewerker_products`

```text
KEEP_LEGACY
REPLACE_LATER
```

### `*event*name`

```text
KEEP
separate Event commerce/Product relationship
```

No helper or snippet removal gate passes merely because Task 7A exists.

---

## 27. Documentation

Task 7A should create:

```text
docs/architecture/PRODUCT_PEOPLE_IMPLEMENTATION.md
docs/architecture/POST_TASK_7A_CURRENT_STATE.md
```

Update architecture inventories only where the new foundation materially changes current state.

Expected candidates:

```text
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md
docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv
docs/architecture/PERSON_CONSUMER_INVENTORY.csv
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Historical Task 5C and Task 6B evidence must remain historical.

Do not rewrite historical statements as if the Task 7A architecture existed at the time.

Use later-state update sections/rows where necessary.

---

## 28. Task 7A success criteria

Task 7A is complete when all of the following are true:

```text
Product People source-controlled ACF fields:
IMPLEMENTED

product_people_mode:
REGISTERED

product_people_override:
REGISTERED

ProductPeopleResolver:
IMPLEMENTED

event-derived mode:
VERIFIED

manual mode:
VERIFIED

unconfigured mode:
VERIFIED → []

invalid mode:
VERIFIED → []

legacy produk_medewerkers fallback:
ABSENT

existing Product data migration:
0

Product Single 124:
UNCHANGED

legacy product automation:
UNCHANGED

legacy query helper:
UNCHANGED

protected Product state:
UNCHANGED

Person persistent count:
0

Event Participation persistent count:
0

temporary QA residue:
0

source/runtime parity:
PASS

existing regression suites:
PASS
```

At this point the system has a canonical Product People authority.

It does not yet have a migrated Product frontend consumer.

---

## 29. Task 7B boundary

Task 7B begins only after Task 7A has:

1. passed implementation review;
2. merged;
3. completed any required post-merge source/runtime stabilization.

Task 7B will perform a fresh runtime audit of Product Single 124 and design the consumer cutover.

Its conceptual transition is:

```text
CURRENT

Product
→ produk_medewerkers
→ medewerker
→ Shared Medewerker Card 6b463f
```

to:

```text
TARGET

Product
→ ProductPeopleResolver
→ person[]
→ canonical Person presentation
```

Task 7B must separately preserve the approved `produk_bron_event` source-Event presentation semantics.

Task 7B must not assume:

```text
Shared Medewerker Card 6b463f
```

is safe for canonical Person presentation.

The exact Bricks adapter/card/empty-state contract requires a fresh audit.

Automation and helper retirement occurs only at or after consumer cutover and only after fresh caller/removal gates pass.

---

## 30. Explicit exclusions

Task 7A excludes:

```text
Product Single 124 mutation
Medewerker Single 772 mutation
Event Single 774 mutation

Shared Medewerker Card 6b463f mutation
Event Participant Card 194bba mutation

_event_name mutation

produk_medewerkers data migration
produk_bron_event data migration

9-acf-product-copy.php mutation
10-vg-bricks-medewerker-queries.php mutation

Product 765 mutation
Product 767 mutation
Product 768 mutation

profiles 753–757 migration
Person migration
Event Participation migration

WooCommerce commerce behavior
Tickera relationship behavior

Product reverse relationship APIs
Product→Person synchronization
persistent caches
background jobs
cron
global CSS
unrelated ACF restructuring
```

---

## 31. Stop conditions

Implementation must stop and request architecture review if any of the following occur.

### Source Event meaning contradiction

If `produk_bron_event` does not reliably represent the approved Product source/provenance Event concept:

```text
STOP
```

### Stored/default mode ambiguity

If ACF/WordPress cannot reliably distinguish:

```text
unstored mode
```

from:

```text
explicit stored event mode
```

without changing the locked contract:

```text
STOP
```

### ACF collision

If the new source-controlled Product People field group conflicts with active runtime ACF registration or field identity:

```text
STOP
```

### Event Participation contract mismatch

If EventParticipationRepository cannot provide the locked participation eligibility and ordering semantics:

```text
STOP
```

### Existing Product mutation

If registering the new fields causes existing Products to gain stored canonical mode/override metadata:

```text
STOP
```

### Runtime drift

If active Platform Core differs materially from the expected merged source in a way unrelated to Task 7A:

```text
STOP
```

### Cleanup uncertainty

If temporary Task 7A QA ownership or exact hard-delete cleanup cannot be proven:

```text
STOP
```

### Legacy dependency requirement

If ProductPeopleResolver requires reading:

```text
produk_medewerkers
event_medewerkers
medewerker
```

to satisfy the approved contract:

```text
STOP
```

No fallback architecture may be invented during implementation.

---

## 32. Branch and review workflow

Implementation must begin from:

```text
main
2b9ac1ae4fc73387e04ce42278b2a24ca993faca
```

or the exact later `main` explicitly approved during implementation planning.

Use an isolated feature worktree/branch.

Suggested branch:

```text
feat/product-people-foundation-7a
```

Suggested PR title:

```text
feat: add canonical Product People foundation
```

The coding agent must:

1. implement only the approved plan;
2. gather source/runtime/test evidence;
3. open the PR;
4. stop.

The independent architecture reviewer must inspect the actual PR branch, diff, tests, runtime evidence, protected state, and cleanup before merge.

The coding agent must not merge its own PR.

---

## 33. Approval gates

This written specification requires independent approval before an implementation plan is written.

Written-spec approval authorizes only:

```text
writing the Task 7A implementation plan
```

It does not authorize implementation.

The implementation plan must then receive a separate review before any Task 7A Product, ACF runtime, Platform Core deployment, or QA mutation begins.

---

## 34. Final target state after Task 7A

```text
Product source Event authority:
produk_bron_event

Event commerce relationship:
_event_name
SEPARATE / UNCHANGED

Product People mode:
product_people_mode

Product manual People:
product_people_override → person[]

Product People authority:
ProductPeopleResolver

Event-derived Product People:
live from EventParticipationRepository

Manual Product People:
explicit replacement list

Existing Products:
UNCONFIGURED unless explicitly migrated/configured

Legacy produk_medewerkers:
KEEP_LEGACY

Legacy product copy automation:
KEEP_LEGACY

Legacy Bricks consumers:
KEEP_LEGACY

Product Single 124:
UNCHANGED

Persistent Person:
0

Persistent Event Participation:
0
```

Task 7A establishes the canonical foundation only.
