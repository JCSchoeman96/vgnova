# VG Nova Simple Person Relationship Architecture Design

**Version:** 0.1.0\
**Status:** APPROVED\
**Approval date:** 2026-10-27\
**Repository:** `JCSchoeman96/vgnova`\
**Baseline at approval:** `main` after merged PR #60 (`de90ff061c20b164c127aa97e845e3f192429543`)\
**Scope:** Canonical simple Person relationships for Articles and Masteriyo Courses\
**Target repository path:** `docs/superpowers/specs/2026-10-27-simple-person-relationship-architecture-design_v0.1.0.md`

## 1. Intent

This design resolves the two architecture blockers identified by the VS1 Golden Journey preflight:

```text
Article → Person
Person → Course
```

The design preserves the locked canonical information architecture:

- originating objects own simple relationships;
- canonical `person` records are the public human identity;
- reverse Person pages derive their content dynamically;
- Masteriyo remains Course authority;
- WordPress remains Article authority;
- ACF adds Voelgoed-specific metadata;
- Product Contribution remains Product↔Person authority;
- Event Participation remains Event↔Person authority;
- no copied reverse lists are maintained on Person;
- no hidden Bricks query becomes relationship authority.

The objective is to make Article↔Person and Course↔Person relationships easy for editors to maintain while giving Platform Core efficient, deterministic reverse queries at scale.

## 2. Article relationship model

Articles use simple authoritative ACF/Post Object relationships:

```text
primary_author       → Person       0..1
additional_authors   → Person[]     0..n
expert_contributors  → Person[]     0..n
reviewed_by          → Person[]     0..n
```

No `article_participation` junction CPT will be created. The Article is the only writable relationship owner. The canonical Person record does not store a copied Article list.

## 3. Course relationship model

Masteriyo Courses receive Voelgoed-specific simple Person relationships:

```text
lead_instructor         → Person       0..1
additional_instructors  → Person[]     0..n
experts                 → Person[]     0..n
```

No separate Course participation CPT will be created for these simple roles.

Masteriyo remains authoritative for Course content, lessons, learning state, enrolment, access, progress, native Course/Woo integration, and operational WordPress/Masteriyo author state.

The new ACF fields own only the public Voelgoed Course↔Person relationships. Masteriyo's WordPress-user author must not automatically become the canonical `lead_instructor`.

## 4. Explicitly rejected models

### 4.1 Raw ACF reverse meta queries

Rejected as the primary reverse-query architecture. Multi-value ACF/Post Object relationships must not lead to production serialized-meta `LIKE` scans for Person pages.

### 4.2 Bidirectional ACF relationship syncing

Rejected. No Article/Course field may automatically maintain a writable reverse field on Person.

### 4.3 Article Participation junction CPT

Rejected for current requirements because the canonical IA explicitly models these Article relationships as simple relationships with little/no relationship-specific metadata.

### 4.4 Course Participation junction CPT

Rejected for current requirements because the agreed Course roles are simple relationships with no approved relationship-specific metadata requiring a rich junction entity.

### 4.5 Masteriyo author as public Person authority

Rejected. A Masteriyo/WordPress user represents an operational account identity, not necessarily the public canonical human identity.

### 4.6 Product Contribution as Course↔Person authority

Explicitly prohibited. A Masteriyo Course may have a linked Woo Product, but Product Contribution remains Product-specific authority and must not be used to infer Course↔Person.

## 5. Derived normalized relationship index

The authoritative ACF fields remain editor-facing source of truth. Platform Core maintains one normalized derived index solely for reverse Person queries, efficient role filtering, deterministic ordering, Bricks/query adapters, and integrity/reconciliation tooling.

The index is disposable. Deleting and rebuilding it from authoritative Articles/Courses must never cause canonical relationship data loss.

### 5.1 Shared table

```text
{$wpdb->prefix}vg_person_relationship_index
```

Logical schema:

```text
id                BIGINT UNSIGNED AUTO_INCREMENT
source_post_id    BIGINT UNSIGNED NOT NULL
source_post_type  VARCHAR(32) NOT NULL
person_id         BIGINT UNSIGNED NOT NULL
role_key          VARCHAR(64) NOT NULL
position          INT UNSIGNED NOT NULL DEFAULT 0
```

Keys:

```text
PRIMARY KEY (id)
UNIQUE (source_post_id, person_id, role_key)
INDEX (person_id, source_post_type)
INDEX (person_id, role_key)
INDEX (source_post_id)
```

No SQL foreign keys are required. WordPress lifecycle and validation code owns integrity.

## 6. Approved source types and roles

Version 1 supports only explicitly approved source contracts.

### Article

Source type: `post`

Approved roles:

```text
primary_author
additional_authors
expert_contributors
reviewed_by
```

### Masteriyo Course

Current canonical plugin post type: `mto-course`.

Approved roles:

```text
lead_instructor
additional_instructors
experts
```

The installed runtime/source contract must still be reverified before live activation.

### Future extension

Podcast and Uitsending relationships may later reuse this table only through separately approved relationship contracts. Do not turn this into an unrestricted generic entity-relationship store.

## 7. One Person, one role per source object

Within a single source object, one Person may hold only one approved relationship role.

Within one Article:

```text
primary_author
additional_authors
expert_contributors
reviewed_by
```

Within one Course:

```text
lead_instructor
additional_instructors
experts
```

Duplicate-role assignment must fail validation. If future requirements prove that one Person genuinely needs multiple simultaneous roles on one object, that requires an explicit architecture amendment.

## 8. Ordering

Multi-value fields preserve editor ordering using `position`. Single-value fields use `position = 0`.

Index `position` represents order within one relationship role on one source object, not global Person-page ranking.

## 9. Publication validity

Draft/private source objects may reference published, draft, or private canonical Persons.

A published Article/Course may reference only Persons that:

```text
exist
AND post_type = person
AND post_status = publish
AND not be trashed
```

If a published source contains an invalid Person relationship, publication/update must be blocked with an actionable admin validation error. The system must identify the offending field/person/reason where possible.

The system must not silently remove or rewrite the relationship.

`primary_author` and `lead_instructor` remain optional at schema level.

## 10. Person lifecycle

If a Person referenced by a published source is later changed to draft/private/trash, the source relationship must not be silently deleted.

The authoritative ACF value remains so editors can repair it. Public consumers must exclude unpublished Persons. Integrity/reconciliation tooling surfaces dangling or non-public references.

## 11. Source lifecycle

Index rows may remain for draft/private/trash source objects. Public Person reverse queries must filter current source publication/access state.

Permanent source deletion removes that source's derived rows.

## 12. Index synchronisation

On an Article or Course relationship save:

```text
1. read submitted authoritative fields
2. validate field shapes
3. validate allowed Person IDs/types
4. enforce one-Person-one-role invariant
5. enforce publication validity where applicable
6. allow source save
7. synchronously rebuild that source object's index rows
```

The indexer replaces the full relationship projection for that source rather than incrementally patching rows.

## 13. Non-authoritative failure behaviour

If the source save succeeds but index rebuild fails:

```text
source save remains successful
authoritative ACF values remain intact
index failure is logged
source is marked dirty
reverse consumers fail safely
reconciliation can repair the index
```

Do not roll back or destroy editor content merely because a derived projection failed.

## 14. Dirty state

Use an internal, non-editor-facing source-object marker with this semantic contract:

```text
_vg_person_relationship_index_dirty = 1
```

Successful reindex clears the marker. Dirty state is operational metadata only and never relationship authority.

## 15. Rebuild and reconciliation

Platform Core must provide deterministic operations for:

```text
rebuild one source object
rebuild entire Person relationship index
reconcile index against authoritative source fields
```

The full rebuild must be idempotent, repeatable, safe, independent of frontend rendering, and capable of recreating the index from zero.

Required reconciliation classes include:

```text
missing index row
stale extra index row
wrong role
wrong position
invalid Person reference
unsupported source type
unsupported role
dirty source
```

Reconciliation must not silently mutate authoritative ACF relationship fields. It may rebuild derived rows from those fields only through an explicit repair operation.

## 16. Query architecture

Frontend and Bricks consumers must not query the custom table directly. Platform Core owns repository/service boundaries.

Recommended conceptual boundaries:

```text
PersonRelationshipRepository
PersonRelationshipIndexer
PersonRelationshipValidator
PersonRelationshipService
```

Additional narrowly focused helpers are allowed where needed.

## 17. Forward vs reverse reads

Forward reads (Article→People, Course→People) may consume authoritative ACF relationship fields directly through Platform Core and return validated canonical Persons.

Reverse reads (Person→Articles, Person→Courses) use the normalized index through Platform Core APIs.

No serialized ACF-meta reverse query is permitted as the production relationship mechanism.

## 18. Public query rules

A public reverse result must satisfy both sides:

```text
Person is currently publishable
AND
source object is currently publicly eligible
```

Additional native-provider access rules remain authoritative. The index answers relationship membership, not user entitlement.

## 19. Bricks integration boundary

Bricks is presentation only. Bricks may consume stable Platform Core APIs/adapters.

Bricks must not:
- query the custom table directly;
- reverse-query serialized ACF fields;
- infer Course relationships through Woo Products;
- infer Person relationships through WordPress authors;
- maintain copied Person relationship lists.

## 20. ACF contract

ACF fields must:
- restrict selection to canonical `person`;
- return IDs or another representation normalized immediately by Platform Core;
- preserve editor order for multi-value roles;
- expose clear instructions;
- avoid bidirectional relationship behaviour.

The selected return format must not become a public API contract outside Platform Core.

## 21. Migration rule

Existing legacy data is evidence, not automatically canonical relationship truth.

Never infer relationships solely from:
- legacy `medewerker`;
- WordPress `post_author`;
- Masteriyo author;
- Woo Product Contribution;
- shared Topic;
- FCM;
- coincidental names;
- historical QA fixtures.

## 22. Security and integrity

All mutation paths validate source object type, role allow-list, integer IDs, canonical Person post type, duplicate-role invariant, and publication validity. Use safe parameterized WordPress database operations.

Index rebuild/admin maintenance operations must be capability/session protected as required by the existing mutation coordination architecture.

## 23. Performance principles

The index exists specifically to avoid reverse serialized-meta scans. Do not implement a production relationship mechanism equivalent to:

```text
meta_value LIKE '%"1705"%'
```

## 24. Non-goals

This design does not authorize:
- frontend Person redesign;
- Task 10B legacy Person cutover;
- Article template redesign;
- Course template redesign;
- Journey Intent;
- Vriendinne membership implementation;
- Course pricing configuration;
- Course Woo Product creation;
- Member Perks flag changes;
- representative-data mutation;
- Podcast relationship implementation;
- Uitsending relationship implementation;
- Product Contribution changes beyond any minimal integration seam needed for global lifecycle coordination;
- Event Participation changes.

## 25. Consequence for VS1 Master Gate

Successful source implementation plus later runtime proof resolves the two architecture unknowns:

```text
ARTICLE_TO_PERSON_GATE
ARCHITECTURE_DECISION_REQUIRED
→ architecture resolved

PERSON_TO_COURSE_GATE
ARCHITECTURE_DECISION_REQUIRED
→ architecture resolved
```

This does not make `VS1_MASTER_GATE=PASS`. Representative data, Course commerce configuration, membership transitions, fulfilment, email, analytics, cache and regression blockers remain separate.

## 26. Final doctrine

```text
Originating objects own simple Person relationships.
ACF is the editor-facing write authority.
Platform Core validates and normalizes.
The custom index is disposable derived state.
Person pages query relationships in reverse.
No Person reverse lists are manually maintained.
Masteriyo remains Course authority.
WordPress users are not canonical People.
Woo Products do not define Course instructors.
Bricks presents canonical service data; it does not invent relationships.
```
