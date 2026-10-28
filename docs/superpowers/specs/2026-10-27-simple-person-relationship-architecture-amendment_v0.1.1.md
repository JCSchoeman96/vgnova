# VG Nova Simple Person Relationship Architecture Amendment

**Version:** 0.1.1\
**Status:** APPROVED\
**Date:** 2026-10-27\
**Supersedes:** publication-validity wording in `2026-10-27-simple-person-relationship-architecture-design_v0.1.0.md` only\
**Preserves:** all other v0.1.0 decisions unchanged

## Reason

The implementation review proved that WordPress core `wp_publish_post()` writes `post_status = publish` directly before firing transition/save hooks. The approved v0.1.0 wording therefore promised a universal pre-write veto that Platform Core cannot truthfully guarantee for arbitrary low-level publication paths without invasive global SQL interception.

The architecture is amended to distinguish supported Voelgoed publication paths from unsupported low-level bypasses.

## Locked publication-validity contract

Platform Core guarantees pre-publication Person relationship validation for every **supported Voelgoed publication path**:

- normal WordPress editor saves;
- REST/API updates that traverse supported WordPress post APIs and validation boundaries;
- scheduled publication governed by Platform Core;
- repository-owned Platform Core publication operations.

A supported publication path must prevent publication when the Article/Course Person relationship contract is invalid.

## Unsupported direct publication

Repository-owned production code MUST NOT use `wp_publish_post()` directly to publish governed Articles or Courses unless an approved pre-publication Person validation boundary has already run.

Direct low-level publication that bypasses the canonical mutation boundary is outside the supported publication contract and cannot be claimed to have a pre-write veto.

## Static source guard

Repository source checks must detect unauthorized `wp_publish_post()` use in repository-owned production code.

The check may use a narrow allow-list only where an approved validation boundary is explicit and tested. Tests and third-party/vendor/core code are outside this repository-owned guard.

## Runtime bypass detection

If Platform Core observes a governed Article/Course transition into `publish` and the stored Person relationship contract is invalid, it must:

- emit a high-severity sanitized integrity/architecture diagnostic;
- mark `_vg_person_relationship_index_dirty = 1`;
- ensure reverse relationship consumers fail safely;
- never claim that the invalid publication was prevented.

The detector must not silently rewrite authoritative ACF relationship values.

The detector must not perform a global SQL interception.

## Explicitly rejected mechanism

Do not intercept generic `$wpdb` SQL through the global `query` filter to suppress `UPDATE ... post_status='publish'`.

Reasons:
- fragile coupling to SQL text;
- global performance/behavior risk;
- plugins may generate equivalent writes differently;
- `wp_publish_post()` would continue firing transition/save hooks from in-memory published state even if SQL were suppressed;
- the mechanism would create inconsistent state rather than a trustworthy veto.

## Revised acceptance criterion

Replace:

> Published source objects cannot contain invalid Person relationships.

with:

> All supported publication paths prevent publication of a governed Article/Course whose Person relationship contract is invalid.

Additionally:

> Unsupported direct publication bypasses are detected as integrity violations, marked dirty, and fail safely in reverse relationship consumers.

## Non-changes

This amendment does not change:

- Article/Course ACF authority;
- Person roles/cardinality;
- one-Person-one-role;
- derived table schema;
- dirty-state doctrine;
- reverse-query service architecture;
- Masteriyo authority;
- Product Contribution boundaries;
- source-only implementation scope;
- runtime activation boundary;
- Bricks scope;
- representative-data scope.
