# Task 7C: Person consumer and legacy decommission readiness design

## Status and purpose

This document approves the Task 7C design gate only. Task 7C is a read-only
audit of current Person consumers and the readiness of their legacy dependencies
for later consumer replacement or retirement. It identifies an evidence-backed
sequence for addressing those dependencies without creating canonical Person
records, relationships, or migration authority.

The governing principle is:

```text
architecture dependency != data migration authority
```

Task 7C starts from merged `origin/main` at
`3e28f942a15e970270b5efa432e2a5392dfc6b0f`. PR #30 is merged. Task 7B and
Task 7B-S are complete. The merged [Post-Task 7B current
state](../../architecture/POST_TASK_7B_CURRENT_STATE.md) is the current
repository snapshot for the Product Single 124 cutover. Task 7C implementation
has not started.

## Authority and evidence

The fresh current runtime state is authoritative for claims about active
callers, runtime dependencies, routes, field values, and current records.
Historical implementation reports and snapshots support the audit but do not
establish current state. The existing [Person consumer
inventory](../../architecture/PERSON_CONSUMER_INVENTORY.csv) is an audit surface
to reconcile and refresh. Its historic caller counts and pre-Task 7B Product
Single 124 state must not be treated as current without fresh verification.

The audit must inspect the source-controlled Platform Core architecture and
compare relevant deployed runtime code and configuration where runtime behavior
depends on them. Record source and runtime evidence separately. Where they
differ, report the difference and use fresh runtime evidence for current caller
and dependency claims.

The Task 5C provenance rulings remain fixed for this audit:

| Record or edge | Current ruling | Task 7C treatment |
|---|---|---|
| Medewerker profiles 753, 754, 755, 756, and 757 | `DEFER` | Preserve each ruling. Do not create a Person or copy profile data. |
| Event 763 to profile 753 | `EXCLUDE` | Preserve the source edge unchanged. |
| Event 763 to profile 757 | `EXCLUDE` | Preserve the source edge unchanged. |
| Product 767 to profile 753 | `EXCLUDE` | Preserve the source edge unchanged. |
| Product 768 to profile 753 | `EXCLUDE` | Preserve the source edge unchanged. |
| Product 768 to profile 757 | `EXCLUDE` | Preserve the source edge unchanged. |

The audit may record genuinely new authoritative provenance evidence. It must
not silently change a `DEFER` or `EXCLUDE` ruling. Any proposed change requires
the evidence to be recorded and reviewed as a separate decision.

## Runtime identity and read-only boundary

Any runtime audit performed under a later Task 7C implementation must explicitly
target the Voelgoed WordPress install at `http://localhost:10039/`. The audit
must not infer runtime identity from a working directory, a prior snapshot, or
an assumed port.

Every runtime evidence payload must identify at least:

| Identity field | Required evidence |
|---|---|
| `ABSPATH` | The resolved WordPress installation root. |
| `home_url` | The runtime site's configured home URL. |
| Port | The actual request or runtime port, which must identify port `10039`. |
| Database identity | The connected database name and server identity sufficient to distinguish this install from another local WordPress database. Do not include credentials. |

This identity gate prevents a repeat of the Task 7B ambiguity between ports
10039 and 10049. If any required identity value is missing or points to another
install, stop runtime evidence collection and report the mismatch.

Task 7C runtime inspection is read-only. It must not write to WordPress, ACF,
Bricks, FluentSnippets, routes, records, relationships, options, terms, or
files. Capture enough before-and-after state to support a zero-write conclusion.
A tool that may save or auto-sync inspected configuration is outside the
read-only boundary.

## Fresh audit scope

The audit must inspect the current runtime and source evidence for each surface
below. It must discover and record every currently active caller found during
the audit. Historic counts are leads only.

| Surface | Fresh evidence required |
|---|---|
| Bricks Template 772, Medewerker Single | Conditions, current structure, all marked or native queries, element IDs, query helper modes, relationship directions, rendered fields, route behavior, and all distinct dependencies. Decompose this template using the concerns below. |
| Bricks Template 773, Medewerker Archive | Conditions, archive query and ordering, current `medewerker` dependency, loop elements, card instance, fields, and route behavior. |
| Shared Medewerker Card `6b463f` | Current structure and dynamic data, plus a complete fresh list of all active callers across templates, components, and other supported runtime surfaces. |
| `medewerker_events` | Definition, implementation, all current callers, and the legacy Event relationship data it reads. |
| `medewerker_products` | Definition, implementation, all current callers, and the legacy Product relationship data it reads. |
| `event_medewerkers` | ACF definition, active values, read consumers, write or copy consumers, and its role in the legacy Event and Product paths. |
| `produk_medewerkers` | ACF definition, active values, all readers and writers, including the Product Single 124 post-Task-7B state and Template 772. |
| `produk_bron_event` | ACF definition, active values, all current callers, and separation from Product People and the `_event_name` commerce relationship. |
| FluentSnippets `9-acf-product-copy.php` | Active state, complete save conditions and behavior, fields read and written, all current readers of its output, and any other supported purpose. |
| Relevant branches of `10-vg-bricks-medewerker-queries.php` | Current source and runtime parity, active state, exact mode-to-caller mapping for `medewerker_events`, `medewerker_products`, `event_medewerkers`, `event_products`, `product_medewerkers`, `product_source_event`, and any additional current branches discovered. |
| Routes `/medewerkers/` and `/medewerkers/{slug}/` | Current route responses, rewrite and canonical behavior, indexing signals, and any links or navigation that establish route dependencies. Inspect only; do not change them. |
| Medewerker dynamic data | Registry entries and actual uses, including `{acf_medewerker_titel}`, `{acf_medewerker_kort_bio}`, `{acf_medewerker_profiel_status}`, `{acf_event_medewerkers}`, `{acf_produk_medewerkers}`, `{post_terms_medewerker_rol}`, `{post_terms_medewerker_groep}`, `{acf_broadcast_hosts}`, `{acf_broadcast_guests}`, and any additional current tags. A registered tag is not proof of an active caller. |
| Medewerker classifications and ACF | Relevant definitions, values, usages, and consumers for `medewerker_rol`, `medewerker_groep`, profile fields such as `medewerker_titel`, `medewerker_kort_bio`, and `medewerker_profiel_status`, and related ACF groups. Do not edit definitions or values. |
| `PERSON_CONSUMER_INVENTORY.csv` | Reconcile every applicable row against fresh runtime evidence. Add or correct current-state caller and dependency evidence only in the later authorized audit output. |
| Additional callers | Search all currently active runtime callers discovered outside the listed surfaces, including any additional Bricks templates, components, code, snippets, routes, fields, and write paths. |

For each caller, record its owning surface, runtime identity, source or element
identifier, direction of the relationship, data read or written, whether the
caller is supported and active, evidence source, and any access limitation.
Separate an inactive registered definition from an active caller. Do not call a
legacy dependency unused until the audit has checked both read and write paths.
The report must connect these findings into an evidence-backed dependency order,
including prerequisite gates and the earliest safe point for breaking the legacy
Product copy chain. This is an audit result, not an implementation plan.

## Template 772 concerns

Template 772 must be decomposed into independent concerns. Do not treat the
template as one indivisible migration unit.

| Concern | Audit question |
|---|---|
| Person/profile presentation | Which profile fields and native Person presentation elements are used, and can the replacement be designed without copying profiles 753–757? |
| Reverse Event relationships | Which legacy and canonical relationship paths supply Events for a Medewerker? What read contract does a future Person view need? |
| Reverse Product relationships | Which legacy path supplies Products for a Medewerker? Is that behavior a supported requirement, safely derivable, or obsolete presentation? |
| Classification and taxonomy presentation | Which role/group terms, taxonomy tags, and other classifications are shown, and where do their meanings belong in a future supported flow? |
| Route identity and canonical destination | What does each legacy route identify, and what evidence would be required before any later route change or redirect? |
| Additional dependencies | What other fresh dependencies, consumers, or write paths does the audit find? Record and classify each separately. |

Assign a readiness classification to each audited concern or atomic dependency.
Different concerns within Template 772 may receive different classifications.
Task 7C does not authorize any Template 772 mutation.

## Reverse Event relationship

The likely canonical architecture is:

```text
Person -> Event Participation -> Event
```

The Event Participation foundation already exists. The source-controlled
`EventParticipationRepository` includes `events_for_person()`, which returns
Event IDs through Person-linked Event Participation records. Task 7C must
inspect the current repository/API contract and deployed runtime path to
determine:

1. whether the existing repository/API and runtime integration support the
   Person-to-Event read needed by the consumer;
2. whether result ordering, endpoint validation, publication rules, and Bricks
   integration satisfy that presentation, or whether a new read-only adapter or
   query interface would be needed; and
3. whether a later consumer replacement can be designed without persistent
   migrated Person data.

The existence of a repository method does not itself prove that a Bricks
consumer can use it safely or that legacy display data may be migrated. Do not
write Event Participation records or migrate any Event edge. The two Event 763
edges remain `EXCLUDE`.

## Reverse Product relationship

Do not assume the legacy `medewerker_products` presentation must survive.
Product Single 124 now resolves Product People forward from Product
configuration through the canonical Product People architecture. Existing
Product fields and values remain legacy evidence; they are not permission to
migrate the known edges.

The audit must decide which of these descriptions fits the current supported
requirement:

1. Person-to-Product is a genuine product requirement that needs a separately
   approved canonical reverse-query contract;
2. the presentation can be derived safely from canonical Product configuration
   without new persistent relationship storage; or
3. the reverse Product presentation is obsolete and should be retired instead of
   recreated.

Base that decision on current callers, editor and user flows, Product People
behavior, and available data. Do not add or propose as part of Task 7C a reverse
index, relationship storage, database scan contract, or synchronization design.
Any such architecture needs its own subsequent approval. Do not migrate the
three known Product-to-Medewerker edges.

## Legacy Product copy chain

Trace the complete surviving lifecycle and every reader or writer found around
it:

```text
produk_bron_event
  -> event_medewerkers
  -> 9-acf-product-copy.php
  -> produk_medewerkers
  -> medewerker_products
  -> Template 772
```

Determine which links in this chain still serve a genuine supported flow and
identify the earliest safe point at which the chain can be broken. Base that
point on current reader and writer evidence, not on historic caller counts. In
particular, determine whether `9-acf-product-copy.php` has any supported purpose
beyond filling the legacy reverse Product presentation. If it only supports an
obsolete reverse Product consumer, retiring that consumer may be preferable to
creating a canonical Person copy or synchronization path.

Keep Product source Event presentation separate from Product People. Task 7B's
Product Single 124 source Event path reads `produk_bron_event`. Do not read or
repurpose `_event_name` as Person or Product People provenance. Task 7C must not
port, edit, or deactivate the copy snippet.

## Template 773 and shared card

Freshly establish the current Template 773 dependency on `medewerker`, the
complete caller list for `6b463f`, and whether any post-Task-7B Product consumer
still references that component. The Task 7B cutover replaced the Product Single
124 Person card with Product Person Card `dc8611`; verify the current runtime
instead of relying only on that historic statement.

Determine whether the archive and card architecture can be replaced before
persistent Person migration, and whether `6b463f` remains legitimately shared or
remains only because legacy callers still use it. Do not mutate Template 773 or
component `6b463f` during Task 7C.

## Routes and future redirect gates

`/medewerkers/` and `/medewerkers/{slug}/` remain unchanged during Task 7C. No
redirects, rewrites, canonical tags, menus, or indexing settings may change. Do
not flush rewrites.

The audit must document the prerequisites for later route work. A legacy Person
single must not redirect until an independently verified canonical Person
destination exists for that identity. Persistent canonical Person count and the
actual per-profile target mappings are route gates. For any proposed
single-route redirect, the later task must have an approved identity/provenance
decision, a persistent target Person ID, a verified `/mense/{slug}/`
destination, and an unambiguous mapping from the exact legacy route. A nonzero
Person count alone is insufficient. Archive route work also requires a verified
canonical archive destination and a separate review of route, canonical, menu,
and indexing behavior.

Task 7C records these gates only. It does not perform route work or approve a
redirect.

## Readiness classifications

Assign exactly one current-state classification to each audited legacy
dependency or decomposed concern. Use only these values:

| Classification | Definition |
|---|---|
| `READY_FOR_CONSUMER_REPLACEMENT` | Canonical architecture exists sufficiently for a later consumer replacement to be designed without migrating unapproved data. |
| `BLOCKED_BY_PERSON_DATA` | The replacement fundamentally requires an approved persistent Person mapping or data set. |
| `BLOCKED_BY_RELATIONSHIP_MODEL` | Person architecture may exist, but the domain relationship or read contract is not sufficiently designed. |
| `RETIRE_PENDING_ACCESS` | Fresh evidence shows zero supported active callers, but safe edit or removal access is unavailable. |
| `RETIRE_PENDING_DEPENDENCY` | One or more supported active dependencies still require the legacy surface. |
| `KEEP_LEGACY` | The legacy surface remains intentionally required for a valid supported flow. |

Classify atomic concerns separately where a template or component has more than
one purpose. State the evidence for each classification, including any access
limitation. These classifications are readiness and lifecycle decisions only.
None authorizes deletion, retirement, migration, or runtime mutation.

## Audit outputs and next-task recommendation

When Task 7C implementation is separately authorized, it must produce at least:

1. `docs/architecture/PERSON_CONSUMER_DECOMMISSION_READINESS_AUDIT.md`, with
   evidence, callers, dependency classifications, route gates, the
   evidence-backed dependency order, and the final recommendation;
2. `docs/architecture/POST_TASK_7C_CURRENT_STATE.md`, with the post-audit
   current-state snapshot and runtime identity evidence;
3. a refreshed `docs/architecture/PERSON_CONSUMER_INVENTORY.csv` based on the
   fresh audit;
4. updates to `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md` only where
   fresh evidence changes a current ruling; and
5. one explicit recommendation for the next implementation task.

The recommendation must select exactly one next unit based on the audit. Options
include a Template 772 Person Single consumer foundation or cutover, a Template
773 archive and card cutover, a reverse Event query or adapter prerequisite, a
separate Product-to-Person reverse-relationship architecture decision,
retirement of an obsolete legacy consumer, or a finding that migration remains
blocked by Person data or provenance authority.

The recommendation must explain why that unit is the next dependency to address,
which legacy dependency it removes or unlocks, why it carries less risk than the
alternatives, and what remains blocked afterward. Task 7C does not execute the
recommendation or create an implementation plan.

Do not silently revise historical evidence documents as part of the design or a
later audit. Use fresh evidence in the specified audit outputs and update the
decommission register only when a current ruling changes.

## Explicit non-actions

Task 7C must not:

- create Person records or copy Medewerker profiles;
- migrate Event or Product relationship edges;
- write Event Participation records or configure Product People;
- mutate Templates 772 or 773 or component `6b463f`;
- edit FluentSnippets or ACF values or definitions;
- delete fields or taxonomies or retire helper modes;
- modify `/medewerkers/` routes, add redirects, or flush rewrites;
- delete Medewerker records or perform cleanup writes; or
- start the recommended next implementation task.

## Self-review constraints

Before Task 7C begins, verify that its approved scope still matches this design
and the merged repository baseline. Preserve the five `DEFER` profiles and five
`EXCLUDE` edges listed above. Reconcile all Product consumer claims with the
merged post-Task-7B state. Require fresh caller evidence before any retirement
recommendation, keep architecture readiness separate from data authority, and
treat reverse Product behavior as an open audit decision.
