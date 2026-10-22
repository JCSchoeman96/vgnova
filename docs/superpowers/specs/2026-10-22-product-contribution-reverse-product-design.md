# Product Contribution and Reverse Product Architecture Design

**Date:** 2026-10-22

**Status:** Written specification submitted for independent review

**Required base:** `main` at `c6cf4727d6b1e60128ef674b7c0127b207bf7573`

---

## 1. Purpose

This design adds a canonical, role-aware Product ↔ Person relationship model while preserving the Product People architecture already established by Tasks 7A–7C.

The intended user experience is that every canonical Person can have a landing page that dynamically shows the Events and Products they are part of, while each relationship remains owned by its domain rather than duplicated on the Person record.

The governing rule is:

```text
Each domain owns its relationship.
Both endpoints may query it.
ACF Pro owns editorial schema and editor UX.
Platform Core owns canonical rules, validation, and queries.
Bricks only presents canonical results.
```

The Product relationship must be role-aware. A Product can therefore know not only that a Person is involved, but also why they are involved, for example Presenter, Author, Teacher, or Creator.

This task is architectural. It does not migrate legacy Product relationships, create canonical Person records, replace Template 772 wholesale, or remove any legacy Product/Medewerker mechanism.

---

## 2. Existing Product People architecture that remains authoritative

The current Product People model uses an explicit mode:

```text
product_people_mode = event
or
product_people_mode = manual
```

The current resolver exposes:

```php
ProductPeopleResolver::people_for_product(int $product_id): array
```

and returns an ordered list of canonical Person IDs.

Existing event mode is:

```text
Product
→ product_people_mode = event
→ produk_bron_event
→ EventParticipationRepository
→ current canonical Persons
```

Existing manual mode currently reads:

```text
product_people_override
→ Person[]
```

The approved architecture in this design changes the manual authority to Product Contribution records while preserving the public `people_for_product()` contract.

The following remain explicitly non-authoritative for canonical Product People:

```text
produk_medewerkers
event_medewerkers
medewerker
_event_name
```

`produk_bron_event` remains the canonical Product source/provenance Event concept and remains distinct from `_event_name` commerce association.

---

## 3. Core architectural decision

Introduce a canonical non-public relationship entity:

```text
product_contribution
```

Each record represents one Product × Person pair and carries Product-specific contextual metadata.

Conceptually:

```text
product_contribution
├─ product_contribution_product
├─ product_contribution_person
├─ product_contribution_roles
└─ product_contribution_display_order
```

This follows the proven Event Participation pattern: ACF Pro supplies the non-public relationship CPT, taxonomy, and fields; Platform Core owns validation, uniqueness, service behavior, and canonical queries.

The canonical pair identity is:

```text
(product_id, person_id)
```

One Product × Person pair has one contribution record with one or more Product roles. Multiple roles do not create multiple contribution records.

---

## 4. Product mode authority

Product Contribution has different authority depending on `product_people_mode`.

### 4.1 Manual mode

For:

```text
product_people_mode = manual
```

published Product Contribution records are the complete canonical Product People authority.

A contribution means:

```text
Person is a member of this Product
+
these are the Person's Product-specific roles
+
this is their Product-side display order
```

No published Product Contribution means no canonical Person membership for that Product.

The current `product_people_override` field is not a second authority under the new model.

### 4.2 Event mode

For:

```text
product_people_mode = event
```

membership authority remains live-derived from canonical Event Participation through the Product's `produk_bron_event` source Event.

Product Contribution is optional annotation only.

Conceptually:

```text
Product
→ source Event
→ Event Participation
→ current Person membership

optional Product Contribution
→ Product-specific role annotation for a currently derived Person
```

A Product Contribution must never create, preserve, or resurrect event-derived membership on its own.

If the Person is no longer part of the source Event's canonical Event Participation set, they are no longer a canonical Person for the Product even if a Product Contribution record still exists.

### 4.3 Unconfigured or invalid mode

Absent, empty, or invalid `product_people_mode` continues to fail closed:

```text
[]
```

No Product Contribution record may make an unconfigured or invalid-mode Product canonical.

---

## 5. Product roles are separate from Event roles

Product roles and Event roles are different contextual facts.

There is no automatic mapping such as:

```text
Event Spreker → Product Presenter
Event Fasiliteerder → Product Teacher
```

A Person being a speaker at an Event does not prove they are the presenter, author, teacher, creator, or any other Product-specific role for a Product associated with that Event.

Therefore:

```text
Event Participation roles
≠
Product Contribution roles
```

Product roles are written only as Product Contribution data.

For event-derived Products, the Event relationship may establish membership, but Product-specific roles remain absent unless explicitly supplied through a Product Contribution.

---

## 6. ACF Pro ownership

ACF Pro is the default editor-facing schema and UX layer for this architecture.

Do not hand-build custom Product Contribution editors that duplicate ACF functionality.

The intended ownership split is:

```text
ACF Pro
→ relationship CPT definition
→ Product and Person selectors
→ Product Role taxonomy field
→ display-order field
→ conditional/editor UX

Platform Core
→ endpoint validation
→ pair uniqueness
→ role requirement
→ event-mode membership validation
→ canonical reads and writes
→ derived administrative title
→ Bricks adapters
```

No database-only ACF definition is canonical. Definitions must be source-controlled through the project's established ACF source-control convention.

---

## 7. Product Contribution CPT

Register `product_contribution` as an administrative, non-public relationship post type.

Required behavior:

```text
public: false
publicly_queryable: false
exclude_from_search: true
show_ui: true
show_in_rest: true
has_archive: false
rewrite: false
query_var: false
```

Native content is not business data. The post title is administrative only.

There is no public Product Contribution single route or archive.

The administrative title is derived from the endpoint titles:

```text
{Product title} — {Person title}
```

Manual title edits are overwritten and never become relationship identity.

---

## 8. Product Contribution fields

The Product Contribution field group applies only to `post_type == product_contribution`.

Required fields:

| Field | Type | Contract |
|---|---|---|
| `product_contribution_product` | Post Object | One WooCommerce `product`, required for publication, return ID |
| `product_contribution_person` | Post Object | One canonical `person`, required for publication, return ID |
| `product_contribution_roles` | Taxonomy | `product_role`, multiple, at least one role required for a published manual contribution |
| `product_contribution_display_order` | Number | Optional, integer, minimum `0`, default `0` |

The Product and Person fields are canonical endpoints.

Do not add duplicate Product→Person or Person→Product relationship fields merely for reverse lookup.

---

## 9. Product Role taxonomy

Introduce a flat, non-public taxonomy:

```text
product_role
```

attached only to `product_contribution`.

Required settings:

```text
hierarchical: false
public: false
publicly_queryable: false
show_ui: true
show_in_rest: true
rewrite: false
query_var: false
```

Initial vocabulary:

| Name | Slug | Meaning |
|---|---|---|
| Aanbieder | `aanbieder` | Presenter / on-product presenter role |
| Skrywer | `skrywer` | Author / writer role |
| Instrukteur | `instrukteur` | Teacher / instructor role |
| Skepper | `skepper` | Creator role |

These are Product-specific contextual roles only. They do not automatically imply Person classifications or Event roles.

The vocabulary must be source-controlled and seeded through the same idempotent, conflict-safe approach used for other controlled taxonomies.

Unexpected or conflicting slugs/names must stop the seeder before mutation.

---

## 10. Publication and lifecycle

WordPress post status defines Product Contribution lifecycle.

```text
publish → active contribution
draft   → incomplete/inactive
trash   → retired
```

Pending, private, and future records may participate in duplicate prevention but are not active public query results.

A published contribution must have:

```text
one valid Product endpoint
one valid Person endpoint
one unique non-retired Product × Person pair
valid non-negative display order
```

Manual-mode publication additionally requires:

```text
at least one approved product_role
```

Endpoint records must exist, have the expected post type, and not be trashed.

Public Person/Product consumers must further require the returned public endpoint to be published where public presentation requires it.

---

## 11. Pair uniqueness

The canonical identity is one non-retired Product × Person pair.

Duplicate detection must include the same non-retired lifecycle family used by Event Participation unless implementation review proves a Product-specific reason to differ:

```text
publish
draft
pending
private
future
```

Trash and auto-draft do not block reuse of the pair.

Updating the same contribution is allowed.

Multiple Product roles belong on the one contribution record.

Uniqueness must not depend only on ACF UI validation. Platform Core's canonical service/write path must enforce the same pair rule.

---

## 12. Event-mode contribution validation

For a Product in `event` mode, a Product Contribution is annotation only.

Before a Product Contribution can be published, its Person must currently be returned by the Product's canonical event-derived Product People resolution.

Equivalent rule:

```text
Product mode = event
AND
Person ∈ current canonical Event-derived People for Product
```

Otherwise publication must fail.

This prevents Product Contribution from becoming an alternate membership authority for event-derived Products.

If the Event relationship later changes and the Person is no longer canonically derived:

```text
stored contribution may remain
active Product association becomes false
resolver ignores the stale annotation for membership
```

The system must not automatically delete, rewrite, or synchronize the contribution record in response to Event Participation changes.

A later admin warning/filter for stale event-mode annotations may be useful, but it is not required for the foundation.

---

## 13. Mode changes

Switching a Product between:

```text
event ↔ manual
```

changes the authority of existing Product Contribution records.

No automatic copy, deletion, or synchronization occurs when the mode changes.

Semantics are:

```text
manual mode:
Product Contribution = membership + Product roles + Product-side order

event mode:
Event Participation = membership
Product Contribution = optional Product roles only
```

ACF editor instructions must state this clearly.

---

## 14. Canonical query contracts

Preserve the existing simple Product People API:

```php
ProductPeopleResolver::people_for_product(int $product_id): array
```

Return value remains:

```text
ordered list<int> of canonical Person IDs
```

Existing Product/Bricks consumers should not need to understand Product Contribution storage.

The resolver's manual branch changes from `product_people_override` to canonical Product Contribution records.

Add a dedicated Product Contribution relationship boundary conceptually equivalent to:

```php
ProductContributionRepository
```

with canonical read behaviors equivalent to:

```text
contributions_for_product(product_id)
contributions_for_person(person_id)
people_for_product(product_id)
products_for_person(person_id)
```

Exact PHP names may follow existing Platform Core conventions, but the behaviors are locked.

---

## 15. Reverse Product behavior

A Product belongs in a Person's reverse Product view when and only when that Person is currently canonical for the Product.

The reverse result is the union of two canonical cases.

### 15.1 Manual Products

Include a manual Product when a valid active Product Contribution exists for the Person.

```text
Person
→ published Product Contribution
→ manual Product
```

### 15.2 Event-derived Products

Include an event-mode Product when:

```text
Person
→ canonical Event Participation
→ Product source Event
→ Product configured in event mode
```

The optional Product Contribution may enrich that association with Product-specific roles but does not control membership.

### 15.3 Exclusions

Reverse Product queries must never use:

```text
produk_medewerkers
event_medewerkers
medewerker
_event_name
ACF bidirectional copies
Person-side copied Product IDs
```

Duplicate Product IDs are returned once.

Public Person-page results contain only public/published Products.

---

## 16. Rich association read model

Templates may need more than Product IDs.

Expose a read model conceptually equivalent to:

```text
ProductAssociationView
├─ product_id
├─ person_id
├─ product_roles[]
├─ display_order
└─ membership_source
   ├─ manual
   └─ event
```

This is an in-memory/read result, not another WordPress post type or stored record.

For manual membership:

```text
roles come from Product Contribution
order comes from Product Contribution
source = manual
```

For event-derived membership:

```text
membership comes from Event Participation
roles come from an optional matching Product Contribution
Person order comes from Event Participation
source = event
```

An event-derived association may validly have no Product-specific role annotation.

A manual active association may not be role-less.

---

## 17. Ordering

### 17.1 Product → People ordering

Manual mode:

```text
product_contribution_display_order ASC
then contribution ID ASC
```

Event mode:

```text
preserve Event Participation ordering
```

A Product Contribution annotation must not reorder event-derived membership.

### 17.2 Person → Products ordering

`product_contribution_display_order` is Product-side ordering only.

It must not silently become the global ordering of Products on a Person page.

The reverse Product API returns deterministic canonical membership. Person-template presentation may later choose an explicit approved sort such as date, menu order, or title without changing relationship meaning.

---

## 18. Bricks boundary

Bricks must not query ACF relationship internals directly.

The established pattern remains:

```text
Bricks semantic marker
→ Platform Core Bricks adapter
→ canonical repository/resolver
→ ordered endpoint IDs
```

Existing `ProductPeopleBricks` should continue using `ProductPeopleResolver::people_for_product()` so Product Single 124 does not need to know that manual authority changed internally.

A Person-side Product adapter may be introduced when a safe current Person context is available.

Conceptually:

```text
PersonProductsBricks
→ products_for_person(current_person_id)
→ published Product IDs
```

If rich cards need role data, Bricks receives it through the canonical Product association boundary rather than reading `product_role` or Product Contribution meta itself.

Empty canonical result sets should fail closed and allow the marked Person Product section to be hidden on the public frontend.

---

## 19. Performance and caching

Do not create a persistent reverse index merely to make Person → Product queries possible.

The initial implementation may use targeted WordPress/meta queries plus Event Participation reads behind the repository boundary.

Request-scoped memoization is allowed.

Do not add:

```text
transients
options-based relationship caches
cron synchronization
background reverse-index maintenance
ACF bidirectional storage
Person-side copied Product IDs
```

If catalogue scale later makes reverse reads expensive, internal query strategy may be optimized without changing the canonical repository or Bricks contracts.

---

## 20. `product_people_override` transition

Once Product Contribution becomes the manual authority, `product_people_override` must not remain a second manual authority.

However, field retirement is evidence-gated.

Before implementation-time cutover, perform a fresh inventory of:

```text
stored product_people_override values
all current callers/consumers
Product People configuration state
```

If there are zero values and no required callers:

```text
retire the field cleanly
no migration required
```

If any values or required callers exist:

```text
STOP
review provenance and consumer dependency
obtain explicit migration decision
never silently convert values into Product Contribution records
```

Historical Task 7C evidence is not sufficient by itself to authorize destructive retirement at implementation time.

---

## 21. Legacy coexistence

This architecture does not authorize removal or mutation of:

```text
produk_medewerkers
event_medewerkers
produk_bron_event
9-acf-product-copy.php
10-vg-bricks-medewerker-queries.php
medewerker CPT/routes
Template 772 legacy profile consumers
Template 773
component 6b463f
Template 1127 broadcast host/guest relationships
```

Their retirement remains governed by the existing decommission dependency chain and future evidence.

Product Contribution availability does not itself create migration authority for legacy Product→Medewerker edges.

Protected legacy edges remain excluded unless a later provenance decision explicitly changes them.

---

## 22. Person landing-page architecture

The Person CPT is a relationship aggregator, not a relationship owner.

A future Person Single may contain domain-specific sections such as:

```text
Person
├─ Events
│  └─ Event Participation reverse read
├─ Products
│  └─ Product Contribution / Product People reverse read
├─ Courses
│  └─ future Course-domain relationship
└─ Broadcasts
   └─ future Broadcast-domain relationship
```

The Person record does not store duplicate Event or Product relationship lists merely to render these sections.

Each domain remains independently evolvable behind its canonical query interface.

---

## 23. Implementation scope for the next unit

The next implementation unit may establish only the Product Contribution foundation and the canonical reverse Product relationship boundary.

In scope:

```text
ACF Pro product_contribution CPT
ACF Pro product_role taxonomy
ACF contribution fields
controlled Product Role vocabulary
Product Contribution validation/service/repository behavior
pair uniqueness
role requirement
mode-aware validation
ProductPeopleResolver manual-authority cutover
forward and reverse Product query contracts
request-scoped memoization if useful
Bricks reverse Product adapter only if safe Person context is proven
fresh product_people_override inventory before retirement decision
tests, runtime QA, and documentation
```

Out of scope:

```text
creating real canonical Person records
migrating profiles 753–757
migrating protected legacy Product edges
migrating Event 763 excluded edges
retiring produk_medewerkers
retiring event_medewerkers
disabling 9-acf-product-copy.php
wholesale Template 772 migration
Template 773 migration
broadcast relationship migration
course relationship architecture
route/redirect changes
Person URL migration
unrelated ACF/security remediation
persistent reverse indexes
```

---

## 24. Verification expectations

Implementation is ready for independent review only when it proves at minimum:

1. ACF definitions are source-controlled and runtime-synced only through targeted operations.
2. `product_contribution` has no public route or archive.
3. Product and Person endpoints are type-checked.
4. One non-retired Product × Person pair is unique.
5. Multiple Product roles live on one contribution record.
6. A published manual contribution requires at least one approved Product Role.
7. Event-mode publication rejects a Person who is not currently canonical for the Product's source Event.
8. Event-derived membership still changes live when Event Participation changes, without Product Contribution synchronization.
9. `people_for_product()` remains stable for existing Product consumers.
10. Manual mode resolves people from Product Contribution, not `product_people_override`.
11. `products_for_person()` returns the correct union of manual and event-derived Products and deduplicates IDs.
12. Public reverse Product output excludes non-published Products.
13. Product roles never derive automatically from Event roles.
14. No legacy `produk_medewerkers`, `event_medewerkers`, `medewerker`, or `_event_name` fallback is introduced.
15. No persistent reverse cache/index or ACF bidirectional copy is introduced.
16. `product_people_override` is retired only if a fresh zero-value/zero-required-caller gate passes; otherwise implementation stops for a separate migration decision.
17. Existing Event Participation, Product People, Person Foundation, and relevant Platform Core regression suites remain green.
18. Runtime QA uses owned temporary records, verifies both query directions, and removes all temporary state.
19. Protected legacy Product/Event/profile state remains unchanged.
20. The implementation branch and PR remain open and unmerged for independent review.

---

## 25. Final architecture summary

```text
                    PERSON
                   /      \
          reverse read    reverse read
               /              \
          EVENTS              PRODUCTS
             |                   |
 Event Participation      Product People layer
     relationship         /             \
             |      manual mode         event mode
             |          |                  |
             |    Product Contribution     |
             |    Person + Roles + Order   |
             |                             |
             └────────────────────── Event Participation
```

The final authority rules are:

```text
Manual Product:
Product Contribution owns membership + Product roles + Product-side order.

Event-derived Product:
Event Participation owns membership + Person order.
Product Contribution may add Product-specific roles only.

Person page:
read-only reverse aggregation of canonical domain relationships.

Editing:
remains in the owning domain; Person does not maintain duplicate reverse lists.
```

This architecture intentionally favors one source of truth per domain, rich bidirectional querying, ACF Pro editorial UX, and replaceable query internals over duplicated relationship state.
