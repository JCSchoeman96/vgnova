# Task 7B — Product Single 124 Consumer Cutover Design

**Status:** DESIGN APPROVED IN CHAT; WRITTEN SPEC PENDING USER REVIEW  
**Date:** 2026-10-20  
**Repository:** `JCSchoeman96/vgnova`  
**Locked baseline:** `main` at `e1fa2245506f3d0ab641742e9904b0848981c006` (merged PR #27 / Task 7A)  
**Task:** 7B — Product Single 124 consumer audit and cutover

## 1. Intent

Task 7B cuts Product Single Template 124 away from its legacy Product-facing Medewerker query paths while preserving the approved Product domain boundaries established by Task 7A.

The desired outcome is:

1. Product People presentation reads only canonical `person` IDs returned by `ProductPeopleResolver`.
2. Product source-Event presentation remains a separate `produk_bron_event` concern and does not pass through `ProductPeopleResolver`.
3. `_event_name` remains a separate Tickera/commerce relationship and is unchanged.
4. The Product People visual presentation stays materially consistent with the current Product card, including short bio, while the underlying object changes from `medewerker` to `person`.
5. Legacy Product fields and historical values are preserved unless a later migration explicitly authorizes data changes.
6. Runtime-only helper paths are retired only when a fresh caller/removal audit proves removal safe.

Task 7B is a consumer cutover, not a Product data migration and not a Person-profile migration.

## 2. Approved starting state

Task 7A established the following current state:

- `ProductPeopleResolver::people_for_product(int): array` is the canonical Product → Person read authority.
- `product_people_mode` accepts exact `event` or `manual`; absent, blank, or invalid mode resolves to `[]`.
- `event` mode resolves `produk_bron_event` → canonical Event Participation → published Persons.
- `manual` mode resolves the explicit ordered `product_people_override` list and ignores source Event People.
- There is no legacy fallback to `produk_medewerkers`, `event_medewerkers`, or `medewerker`.
- Existing Products remain unconfigured; no canonical Product People mode/override meta was written by Task 7A.
- `produk_bron_event` is the approved source/provenance Event concept. Existing values were not automatically canonicalized.
- `_event_name` remains a separate commerce relationship.
- `produk_medewerkers` remains `KEEP_LEGACY`.
- Persistent `person` count and `event_participation` count were both zero after Task 7A QA cleanup.

At the approved baseline, Product Single 124 still has two independent legacy consumer paths:

- `vgpr03` → helper mode `product_medewerkers` → `produk_medewerkers` → `medewerker`.
- `vgpr13` → helper mode `product_source_event` → `produk_bron_event` → `tc_events`.
- The People loop uses shared legacy Medewerker Card component `6b463f` at `vgpr04`.

Shared Card `6b463f` remains used by other legacy consumers and must not be modified in Task 7B.

## 3. Scope

### 3.1 In scope

Task 7B includes:

- a fresh read-only runtime audit of Template 124 before any write;
- a source-controlled Bricks adapter for canonical Product People;
- a source-controlled Bricks adapter for Product source Event presentation;
- a new canonical Product Person Card for the Template 124 People loop;
- a targeted Template 124 cutover of the Product People path;
- a targeted Template 124 cutover of the source-Event path;
- frontend hiding of the complete Product People section when canonical People resolve empty, while keeping the marked section editable in Bricks builder context;
- fresh guarded runtime QA with temporary owned records;
- fresh runtime/repository caller and removal audits for legacy Product-facing helper paths and the legacy Product copy automation;
- conditional retirement of obsolete runtime paths only when their removal gates pass;
- architecture/current-state/evidence documentation updates.

### 3.2 Explicit non-scope

Task 7B does **not** authorize:

- migration of existing `produk_medewerkers` values to canonical Person data;
- creation of canonical Product People configuration for existing Products;
- migration of Products 765, 767, or 768;
- migration of Event 763 legacy edges;
- modification of Template 772 (`medewerker_products` or any other reverse consumer);
- modification of Template 773;
- modification of Template 774 or its `_event_name` product loop;
- modification of Template 1127;
- modification of legacy shared Card `6b463f`;
- modification of Event Participant Card `194bba`;
- migration of the Medewerker archive/profile domain;
- migration of broadcast host/guest relationships;
- deletion of `produk_medewerkers` or its historical values;
- deletion or semantic repurposing of `produk_bron_event`;
- any change to `_event_name`;
- reverse Product lookup/index design;
- Product data seeding or provenance reclassification;
- Task 7C or any later migration phase.

## 4. Architecture decision

Use source-controlled Platform Core adapters and a surgical Bricks cutover.

Do **not** move canonical behavior into the runtime-only FluentSnippet helper and do **not** make `ProductPeopleResolver` responsible for Bricks, HTML, source-Event presentation, or legacy fallback.

The target flow is:

```text
Product Single 124
│
├── Product People section
│   └── Product People Bricks adapter
│       └── ProductPeopleResolver::people_for_product(product_id)
│           └── ordered person[]
│               └── new Product Person Card
│                   ├── featured image
│                   ├── post_title
│                   ├── person_professional_title
│                   ├── post_excerpt
│                   └── canonical post_url
│
└── Source Event section
    └── Product Source Event Bricks adapter
        └── produk_bron_event
            └── validated tc_events ID
                └── existing source-Event presentation

Separate commerce relationship:
_event_name → unchanged and outside both adapters
```

## 5. Component boundaries

### 5.1 `ProductPeopleResolver`

`ProductPeopleResolver` remains the canonical Product People domain authority and should remain unchanged unless Task 7B discovers a genuine contradiction in the approved Task 7A contract.

It must continue to:

- require the current object to be a Product;
- use only explicit `product_people_mode`;
- return ordered published Person IDs;
- return `[]` for absent/blank/invalid mode;
- fail closed when ACF relationship reads are unavailable;
- never read `produk_medewerkers`, `event_medewerkers`, or `medewerker`;
- never write.

If the consumer cutover appears to require changing these semantics, implementation must **STOP** and return for architecture review.

### 5.2 Product People Bricks adapter

Add a source-controlled Product-domain Bricks lifecycle component following the proven Event Participation adapter pattern.

Its responsibilities are limited to:

- resolve the current Product context using Bricks preview/post context first, then normal singular/current-post fallbacks;
- recognize an exact semantic query marker, recommended and locked as `vg-product-people-query`;
- call `ProductPeopleResolver::people_for_product()`;
- return a Bricks query over `post_type=person` using only the resolver IDs;
- preserve resolver order with `post__in` + `orderby=post__in`;
- return `[0]` for an empty/invalid result so no unrelated Person posts can leak into the loop;
- recognize an exact Product People section marker, recommended and locked as `vg-product-people-section`;
- hide the complete marked Product People section on the frontend when the resolver result is empty;
- preserve the original render decision in Bricks builder/builder-call context so the empty section remains editable;
- leave every unmarked Bricks query and element unchanged.

The adapter must not infer mode, read legacy Product People fields, or provide fallback behavior.

### 5.3 Product source-Event Bricks adapter

Add a separate source-controlled Product-domain adapter for source-Event presentation.

Its responsibilities are limited to:

- resolve the current Product context;
- recognize an exact semantic query marker, recommended and locked as `vg-product-source-event-query`;
- read raw `produk_bron_event` through ACF only when `get_field()` is available;
- accept exactly one positive integer or digit-only positive numeric string as the source Event ID;
- validate the target as `post_type=tc_events` and not `trash`;
- return a Bricks query containing exactly that Event ID and preserve single-ID order;
- return `[0]` when ACF is unavailable or the value is absent, empty, malformed, multi-valued, wrong-type, missing, or trashed;
- leave all unmarked Bricks queries unchanged.

This adapter must not call `ProductPeopleResolver`, must not inspect `product_people_mode`, and must not read `_event_name`.

The source Event is allowed to render when Product People are empty or unconfigured.

### 5.4 New Product Person Card

Create a new Bricks component specifically for canonical Product Person presentation.

The card's loop object is directly a `person` post. It must render the same Product-page information contract currently expected by the Product Medewerker card, mapped to canonical Person fields:

| Presentation | Canonical Person source |
|---|---|
| Image | featured image |
| Name | `post_title` |
| Professional title | `person_professional_title` |
| Short bio | `post_excerpt` |
| Profile link | canonical `post_url` / Person permalink |

The button text remains `Bekyk profiel` unless the fresh Template 124 preflight proves a different current Product-specific label.

The card must not read `medewerker_titel`, `medewerker_kort_bio`, any other `medewerker_*` field, or Event Participation data.

Do not reuse or mutate:

- legacy Medewerker Card `6b463f`; or
- Event Participant Card `194bba`.

The new component's generated runtime ID and structural hash must be recorded in Task 7B evidence.

## 6. Template 124 cutover contract

The Template 124 edit must be surgical.

### 6.1 Product People path

The current Product People query element `vgpr03` must stop invoking helper mode `product_medewerkers` and instead carry/use the canonical Product People semantic query marker.

The current legacy Card `6b463f` instance under the Product People loop must be replaced with the new Product Person Card instance.

The enclosing Product People section must carry the `vg-product-people-section` marker and use the source-controlled render adapter for frontend empty-state behavior.

The canonical People loop has no legacy fallback.

### 6.2 Product People empty state

On the frontend, hide the complete Product People section whenever `ProductPeopleResolver` returns `[]`.

This includes:

- `UNCONFIGURED` Products (mode absent or blank);
- invalid/unknown mode;
- intentionally empty `manual` mode;
- `event` mode with no valid source Event;
- `event` mode whose Event has no eligible published canonical Persons;
- ACF-unavailable fail-closed behavior.

Do not display a visitor-facing empty or migration message.

In Bricks builder/builder-call context, preserve the section's normal render decision so editors can still edit an empty section.

Existing Products that remain unconfigured after Task 7B therefore show **no Product People section**, even if legacy `produk_medewerkers` contains values.

### 6.3 Source Event path

The current source-Event query element `vgpr13` must stop invoking helper mode `product_source_event` and instead use the source-controlled source-Event query marker.

Preserve the existing source-Event presentation/layout unless the fresh preflight shows that the target subtree must be minimally adjusted to attach the semantic query marker.

Do not route the source-Event relationship through `ProductPeopleResolver`.

A Product may show the source Event while its Product People section is hidden.

### 6.4 Protected Template 124 content

Before any Template 124 write, capture its complete runtime content/structure and a digest or equivalent whole-record proof.

After the targeted write, prove all non-target Template 124 subtrees are unchanged. Do not rely only on screenshots or visual inspection.

No unrelated WooCommerce Product content may be redesigned or reformatted by Task 7B.

## 7. Data and relationship semantics

### CURRENT → TARGET

| Concern | Current | Target after Task 7B |
|---|---|---|
| Product People read | `product_medewerkers` → `produk_medewerkers` → `medewerker` | `ProductPeopleResolver` → ordered published `person[]` |
| Product People card | shared legacy component `6b463f` | new Product Person Card |
| Product People empty state | helper-loop dependent | entire marked People section hidden on empty frontend result |
| Product source Event | `product_source_event` helper → `produk_bron_event` | separate source-controlled source-Event adapter → `produk_bron_event` |
| Commerce Event | `_event_name` | unchanged |
| Legacy Product people data | `produk_medewerkers` values retained | retained, not read by canonical Product People presentation |

### DEFER / KEEP

- `produk_medewerkers`: `KEEP_LEGACY`; preserve field and values.
- `produk_bron_event`: `KEEP`; approved source/provenance Event relationship.
- `_event_name`: `KEEP`; separate commerce relationship.
- Template 772 reverse Product consumer `medewerker_products`: `DEFER`.
- `event_products`, `medewerker_events`, `medewerker_products`, and unrelated helper modes: `KEEP`/`REPLACE_LATER` according to their existing lifecycle decisions.
- shared Medewerker Card `6b463f`: `KEEP_LEGACY` for remaining consumers.
- Event Participant Card `194bba`: unchanged.

### EXCLUDE

- Existing Product → Medewerker source edges already classified `EXCLUDE` stay `EXCLUDE` and are not copied to canonical Person relationships.
- Event 763 legacy edges already classified `EXCLUDE` stay unchanged.

## 8. Legacy runtime-path decommission gates

Task 7B must not assume cleanup is safe merely because Template 124 is cut over.

After successful canonical QA, perform a fresh caller/removal audit across:

- all Bricks templates;
- all Bricks components;
- active FluentSnippets/runtime helper source;
- repository source;
- current architecture/evidence documents;
- relevant Git history when needed to distinguish live callers from historical references.

### 8.1 `product_medewerkers`

May be retired only if the fresh audit proves active caller count is zero after Template 124 cutover.

### 8.2 `product_source_event`

May be retired only if:

1. Template 124 source-Event presentation has been independently replaced by the source-controlled source-Event adapter; and
2. fresh audit proves active caller count is zero.

### 8.3 `9-acf-product-copy.php`

May be retired only if the fresh removal audit proves no remaining runtime behavior depends on its writes to `produk_medewerkers`.

This check must include downstream legacy consumers, especially reverse Product lookup behavior such as Template 772 `medewerker_products`. A zero direct Template 124 caller count is **not** sufficient proof that the write path is removable.

If any remaining consumer or write-path contract depends on `produk_medewerkers` being populated, keep `9-acf-product-copy.php` unchanged and record the exact blocker as `RETIRE_PENDING_DEPENDENCY` / equivalent.

### 8.4 Access blocker

If a path is proven dead but safe FluentSnippets edit access is unavailable, do not use destructive or out-of-scope workarounds. Leave the code intact, record zero callers and the exact access blocker, and mark retirement pending access.

Consumer cutover success does not depend on forcing runtime-helper cleanup.

## 9. QA design

Task 7B must generate fresh consumer-behavior evidence. Task 7A QA is baseline evidence only and must not be reused as proof of the Template 124 cutover.

Use temporary owned QA records. Do not mutate the existing EXCLUDE Product/Event/Medewerker relationships to prove canonical behavior.

### 9.1 Product People scenarios

The guarded QA matrix must prove at minimum:

1. `UNCONFIGURED`: absent/blank mode returns no People and hides the Product People section on frontend.
2. `event`: valid source Event resolves canonical Persons in Event Participation order.
3. `event`: unpublished/non-eligible Person endpoints are excluded.
4. `event`: any stale/manual override values are ignored.
5. `manual`: explicit Person selection preserves editor order.
6. `manual`: duplicate IDs are de-duplicated by resolver contract while preserving first occurrence.
7. `manual`: intentionally empty override returns no People and hides the section.
8. invalid mode returns no People and hides the section.
9. legacy `produk_medewerkers` values do not affect canonical People output.
10. ACF-unavailable behavior fails closed rather than fataling; source-controlled adapter does not create a fallback.
11. canonical People query cannot leak unrelated Persons when the result is empty.
12. Bricks builder context keeps the marked section editable when results are empty.

### 9.2 Product Person Card scenarios

Prove a temporary canonical Person card renders the expected canonical fields:

- featured image;
- Person title;
- optional `person_professional_title`;
- `post_excerpt` as short bio;
- canonical Person permalink;
- no legacy `medewerker_*` dynamic tags or dependencies.

At least one generated canonical Person permalink must resolve successfully in the loaded runtime.

### 9.3 Source Event scenarios

Prove independently:

1. valid `produk_bron_event` resolves exactly one `tc_events` source Event;
2. source Event can render while Product People are `UNCONFIGURED`/empty;
3. manual People mode does not change source-Event presentation;
4. absent/empty source Event returns an empty query;
5. malformed/multi-valued source Event returns an empty query;
6. missing/wrong-post-type source Event returns an empty query;
7. trashed source Event returns an empty query;
8. ACF-unavailable source-Event read fails closed;
9. `_event_name` is neither read nor written by the source-Event adapter.

### 9.4 Cleanup

All QA-created data must have an ownership token and be hard-deleted in dependency-safe order.

Any third-party side effect must be treated as unexplained until ownership is positively proven. Ambiguous side effects are never deleted merely to make cleanup pass.

Final QA must prove zero owned residue across posts, post meta, relationship/taxonomy state, and other touched runtime surfaces.

Persistent Person and `event_participation` counts must return to the pre-QA baseline (expected zero at Task 7B start) unless an independently approved migration changes that baseline before execution begins.

## 10. Protected-state requirements

Capture and compare protected state before and after Task 7B runtime work.

At minimum protect:

- Products 765, 767, and 768, including record digests and relevant ACF/meta values;
- Event 763 and its legacy `event_medewerkers` values;
- Medewerker profiles 753–757;
- Template 124 whole record plus target/non-target structural evidence;
- Templates 772 and 774 whole records;
- component `6b463f`;
- component `194bba`;
- all existing canonical Product People meta on pre-existing Products;
- all existing `_event_name` Product rows;
- `produk_bron_event` values on protected Products;
- `produk_medewerkers` values on protected Products;
- legacy helper and copy-snippet hashes before any authorized retirement;
- relevant post-type/status counts;
- any third-party side-effect surface discovered during preflight or QA.

Expected differences must be enumerated explicitly. Unexplained protected-state drift is a stop condition.

## 11. Execution sequence

Implementation must follow this order:

1. **Read-only preflight** from the exact approved baseline or its later explicitly approved descendant. Verify live Template 124 structure, relevant component IDs, helper/copy snippet hashes, Product/source-Event values, canonical Product People meta, and current callers.
2. **TDD for source-controlled adapters.** Prove marked/unmarked behavior, ordering, empty fail-closed behavior, builder visibility, context resolution, and source-Event validation before production implementation.
3. **Deploy/verify adapters** without changing Template 124 yet; prove source/runtime parity.
4. **Create Product Person Card** and record its generated runtime identity/structure.
5. **Guarded Template 124 cutover** limited to the Product People and source-Event target subtrees.
6. **Fresh guarded runtime QA** against the approved matrix.
7. **Cleanup and protected-state proof.** Hard-delete owned QA data and confirm no unexplained residue/drift.
8. **Fresh decommission audit.** Retire only legacy runtime paths whose gates pass; otherwise leave them intact and record blockers.
9. **Final source/runtime parity and documentation.** Record hashes, structures, counts, caller states, and lifecycle decisions.
10. **Open implementation PR and STOP.** Do not begin a later migration task from the same branch.

## 12. Stop conditions

Implementation must stop for architecture/recovery review if any of the following occurs:

- `main` or the runtime has materially drifted from the approved/preflight baseline in a way that affects this design;
- Template 124 live structure materially differs from the audited `vgpr03` / `vgpr13` / `vgpr04` boundaries;
- `ProductPeopleResolver` must be semantically changed to make the consumer work;
- Product People cutover would require a legacy fallback;
- source Event presentation cannot remain independent from `ProductPeopleResolver`;
- `_event_name` would need to be changed or repurposed;
- any unrelated template/component/field must be modified to complete the target cutover;
- a runtime write affects a non-owned/non-approved surface;
- a third-party side effect cannot be positively attributed to the owned QA run;
- protected state cannot be restored/matched except for explicitly approved changes;
- legacy runtime-path callers or downstream dependencies are ambiguous;
- retirement of `9-acf-product-copy.php` would break a remaining `produk_medewerkers` consumer;
- safe FluentSnippets editing/removal cannot be performed through the available access path.

A cleanup/decommission blocker does not automatically invalidate the canonical Template 124 cutover; it means the blocked legacy path remains documented and intact.

## 13. Required evidence and documentation

The implementation PR must include or update source-controlled evidence sufficient for independent review.

Required evidence includes:

- exact implementation base and head SHAs;
- exact runtime/template/component IDs used;
- source/runtime SHA-256 parity for new Platform Core files;
- Template 124 before/after structural proof and protected non-target comparison;
- Product Person Card generated ID and structure/hash;
- all QA scenario results;
- cleanup ownership token and zero-residue proof;
- pre/post Person and `event_participation` counts;
- protected Product/Event/component/template equality evidence;
- `_event_name` equality evidence;
- legacy field equality evidence;
- before/after helper/copy snippet hashes, or exact retirement evidence when authorized;
- fresh caller counts for `product_medewerkers` and `product_source_event`;
- explicit dependency/removal ruling for `9-acf-product-copy.php`;
- final lifecycle states (`MIGRATED_CANONICAL`, `RETIRED`, `KEEP_LEGACY`, `RETIRE_PENDING_DEPENDENCY`, `RETIRE_PENDING_ACCESS`, or equivalent unambiguous labels).

Update the existing architecture records rather than creating contradictory parallel truth. At minimum expect updates to:

- `docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv` or its successor/current consumer map;
- `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`;
- `docs/architecture/IMPLEMENTATION_LEDGER.md`;
- a Task 7B implementation evidence document;
- a post-Task 7B current-state document;
- a machine-readable runtime manifest if the established project convention continues.

Historical audit documents may remain historical when clearly labeled as such; do not rewrite old point-in-time evidence merely to make it look current.

## 14. Success criteria

Task 7B is complete only when all of the following are true:

- Product Single 124 Product People presentation reads canonical Persons only through `ProductPeopleResolver`.
- No Template 124 Product People code reads `produk_medewerkers` or renders `medewerker` objects.
- The new Product Person Card preserves the approved Product presentation using canonical Person fields, including `post_excerpt` short bio.
- Empty canonical People hide the complete frontend Product People section with no legacy fallback and no visitor-facing empty message.
- The marked Product People section remains editable in Bricks builder context.
- Product source Event presentation reads `produk_bron_event` through a separate source-controlled path.
- Product source Event presentation remains independent of Product People mode/results.
- `_event_name` remains unchanged and separate.
- Protected legacy fields and values remain unchanged.
- Temporary QA data and owned side effects are removed completely.
- Protected state matches except for explicitly approved Template 124/new-component/adapter changes and any separately proven safe runtime-path retirement.
- Each legacy runtime path has a fresh, evidence-backed lifecycle ruling; no path is removed on assumption.
- Implementation stops at the PR for independent architecture review.

## 15. Deferred next work

Task 7B does not decide the later migration order for:

- Template 772 reverse Product relationships;
- canonical Person profile/archive migration;
- `produk_medewerkers` field deletion;
- Product provenance/data migration;
- reverse Product relationship indexing;
- broadcast relationships;
- Event 774 commerce Product relationship `_event_name`.

Those remain separate design/spec/implementation cycles.
