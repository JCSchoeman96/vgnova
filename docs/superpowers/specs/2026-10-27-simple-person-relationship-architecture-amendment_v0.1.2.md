# VG Nova Simple Person Relationship Architecture Amendment

**Version:** 0.1.2\
**Status:** APPROVED\
**Date:** 2026-10-27\
**Supersedes:** the supported editor-save publication-validity mechanics in v0.1.0/v0.1.1 only\
**Preserves:** every other approved v0.1.0 and v0.1.1 architecture decision

## Reason

The whole-branch review proved a second publication boundary: WordPress may persist `post_status=publish` before ACF persists the governed Person relationship fields. ACF then writes those relationship fields independently. If one field write succeeds and a later field write fails, a submitted final relationship state that is valid can still pass through or be left in an invalid published partial state.

The architecture therefore needs an order-independent preflight guarantee for supported saves that leave the source published.

## Locked editor-write safety contract

For every supported save whose resulting source status is `publish`, Platform Core must validate all of the following **before the WordPress post write is allowed**:

1. the currently persisted relationship projection is publish-valid;
2. the submitted final relationship projection is publish-valid;
3. every possible partial combination of changed governed relationship fields is publish-valid.

If any one of those states is invalid, the save is rejected before publication/update proceeds.

This rule makes correctness independent of ACF field-write ordering and resilient to a later partial ACF write failure.

## Partial-state model

For each governed field that is actually changed by the submitted save, there are two possible persisted values during a partial ACF write:

- the old persisted value; or
- the submitted new value.

Platform Core must evaluate the Cartesian set of those old/new choices.

For Articles there are at most four governed fields, therefore at most `2^4 = 16` projections.

For Courses there are at most three governed fields, therefore at most `2^3 = 8` projections.

The empty subset represents the currently persisted projection. The full subset represents the submitted final projection.

All combinations are validated using the existing publish rules:

- supported source type;
- valid canonical Person IDs;
- Person `post_type=person`;
- Person `post_status=publish` for a published source;
- no duplicate Person within a role;
- one Person may hold only one role on the source.

## Changed-field semantics

A governed field counts as changed only when the supported request actually submits that field and its normalized value differs from the fresh persisted value.

Omitted fields remain persisted values and do not expand the combination set.

Reordering a multi-value field counts as a change because `position` is part of the authoritative relationship contract.

## Consequences for editorial workflow

### Safe one-save edits

A one-save edit is allowed when every possible partial projection is publish-valid.

Examples normally safe:

- add an additional author who appears in no other role;
- remove an expert;
- replace one Person with another inside a single role;
- reorder People within one multi-value role, provided uniqueness remains valid.

### Cross-role moves

A move that can temporarily place one Person in two roles is rejected while the source remains published.

Example:

```text
current:
primary_author = Person A
experts = []

submitted:
primary_author = null
experts = [Person A]
```

One possible partial state is:

```text
primary_author = Person A
experts = [Person A]
```

which violates one-Person-one-role. The one-save edit must therefore be rejected.

The supported workflow is:

```text
save 1: remove Person A from primary_author
save 2: add Person A to experts
```

Both persisted states remain valid.

## Repairing an already-invalid published source

A currently invalid published source cannot repair governed relationship fields while remaining published in one save, because the empty partial subset is already invalid.

The supported repair flow is:

```text
publish → non-public status
repair relationships while non-public
publish again through the normal validated publication path
```

Transitioning an invalid published source to a non-public status must remain possible; the publish-valid partial-state rule does not block a save whose target status is non-public.

## Draft-to-publish rule

A draft/private source with relationships that would be invalid if published cannot simultaneously repair those relationships and publish in one save.

The empty partial subset would be invalid under publish rules, so the save is rejected.

The supported workflow is:

```text
save valid relationships while draft/private
then publish in a separate validated save
```

## Failure semantics

This amendment does not make ACF writes transactional.

Instead, it guarantees that any subset of governed field writes that can result from a supported published save is itself publish-valid.

If ACF later fails after persisting only part of the submitted governed fields, the source may not match the editor's requested final state, but it must remain relationship-valid for publication.

Operational failure logging/dirty-state handling remains as already approved.

## Existing v0.1.1 bypass boundary remains unchanged

This amendment does not alter the v0.1.1 ruling:

- supported publication paths must pre-validate;
- unauthorized repository-owned direct `wp_publish_post()` use is prohibited/guarded;
- unsupported low-level bypasses are detected and marked dirty;
- no global SQL interception is authorized.

## Revised acceptance criteria

A supported save whose target status is `publish` must prove:

- current persisted projection publish-valid;
- submitted final projection publish-valid;
- every old/new combination of changed governed fields publish-valid.

A currently invalid published source must be able to transition to a non-public status for repair.

A draft/private source with publish-invalid stored relationships must repair first and publish in a later save.

No atomic ACF persistence layer, custom SQL transaction, temporary publication status, or global SQL interception is authorized by this amendment.

## Explicitly rejected approaches

### Two-phase temporary publication hold

Rejected for now because it introduces a new publication orchestration state machine with transition-hook, cache, notification, indexing and failure-recovery complexity.

### Custom atomic ACF persistence/database transaction

Rejected because WordPress/ACF post-save hooks and external writes are not transactionally coordinated; taking ownership of ACF postmeta persistence would materially expand Platform Core's responsibility.

## Non-changes

This amendment does not change:

- Article/Course relationship field names or cardinalities;
- one-Person-one-role;
- derived index schema;
- Person lifecycle rules;
- Masteriyo authority;
- Product Contribution authority;
- v0.1.1 direct-publication bypass doctrine;
- runtime-activation boundary;
- Bricks scope;
- representative-data scope.
