# Event Consumer Migration Readiness Design

**Task:** 6B
**Date:** 2026-10-18
**Status:** Approved in principle; written specification prepared for final review
**Baseline:** `main` at `c2a5d7560bdd7d96b5a842dfa6c268060db7a3ae`

## Purpose

Determine which Event-facing consumers can move from `tc_events → event_medewerkers → medewerker` to `tc_events → event_participation → person`. Task 6B records runtime evidence and migration readiness. It does not cut over consumers, migrate data, or change WordPress.

## Current state

- Persistent `person` records: 0.
- Persistent `event_participation` records: 0.
- `event_role`: 4 approved terms and 0 relationships.
- `content_topic`: 55 terms across 7 roots.
- Event 763 has legacy `event_medewerkers` values 753 and 757. Task 5C excluded both edges from migration.
- The five `medewerker` records 753–757 remain `DEFER`.
- Products 767 and 768 and their three legacy product-person edges remain unchanged and excluded.

The absence of canonical Person or Event Participation rows is not a data prerequisite for Event Single cutover. With no approved source Event edge, canonical output for Event 763 is an empty result and should remain empty.

## Canonical Event-page contract

The loop object is `event_participation`. Each loop row resolves its `event_participation_person` relationship to a canonical `person`, and exposes the participation's `event_role` terms. The query input is the current `tc_events` post.

Platform Core owns the query integration and ordering. Results follow `event_participation_display_order` ascending, then participation ID ascending, as defined by `EventParticipationRepository`. The Bricks template must not add a competing sort rule. Exact method, class, or hook names remain for Task 6C planning after it confirms the active Bricks contract.

The future Event Participant Card receives an `event_participation` object and renders:

- the linked Person's featured image and title;
- `person_professional_title` when populated;
- the participation's `event_role` terms when populated;
- a link to the canonical Person permalink.

Role output is optional in Task 6C because the current Event design has no clear contextual role location. If shown, the source is `event_role` on the participation. Do not infer an Event role from `person_professional_title`, `person_type`, `medewerker_rol`, or `medewerker_titel`.

The first cutover does not require bio, company, credentials, `content_topic`, or `person_type`. Add them only if the Event template audit proves a required design dependency.

## Empty results and source preservation

When the canonical query returns no rows, hide the entire participant section. Do not show a default “No participants” message and do not add canonical-first/legacy-fallback behavior. The old Event relationships must not reappear because canonical data is empty.

The current 774 participant loop queries `medewerker` posts using `_event_medewerkers` as a post-meta key. Runtime metadata confirms the relationship value is stored on Event 763 under `event_medewerkers`; `_event_medewerkers` stores the ACF field key on the Event, not a reverse edge on each profile. A read-only query against `medewerker` therefore returns no matches for Event 763. The existing section has no empty-result condition and remains visible with its static content. Task 6C must replace the loop and hide the whole participant section for zero canonical rows.

Cutover does not delete or alter `event_medewerkers`, profile records, or source relationships. The field remains until all remaining read/write consumers are migrated and its decommission gate passes.

## Consumer decisions

| Consumer | Task 6B decision |
|---|---|
| Event Single 774 participant loop | `READY_FOR_CUTOVER`; replace with a canonical `event_participation[]` loop in 6C |
| Legacy `event_medewerkers` Bricks helper mode | `RETIRE_AFTER_6C` after verifying no remaining callers |
| New canonical query and Event Participant Card | Design for 6C; neither exists in runtime yet |
| Medewerker Single 772 reverse-event and reverse-product sections | `KEEP_LEGACY`; blocker `BLOCKED_BY_DATA` |
| Medewerker Archive 773 | `KEEP_LEGACY`; blocker `BLOCKED_BY_DATA` |
| Shared Medewerker Card 6b463f | `KEEP_LEGACY`; it reads Medewerker-specific title and bio fields and has other consumers |
| Product Single 124, product fields, helper modes, and copy automation | `KEEP_LEGACY`; replace later as one product migration phase |
| Voelgoed Live 1127 host and guest loops | `NOT_APPLICABLE` to 6B; both relationship fields have zero populated edges |
| Event 763 source edges | `EXCLUDE`; leave unchanged |

Each query mode is assessed separately. The active helper contains `event_medewerkers`, `event_products`, `medewerker_events`, `medewerker_products`, `product_medewerkers`, and `product_source_event`; only the first may retire after 6C. Product-related modes remain for a later product phase. Reverse profile queries remain legacy while profile provenance and canonical Person data are unresolved.

## Task 6C scope

### In scope

- Implement source-controlled canonical Bricks query integration in Voelgoed Platform Core.
- Add a new Event Participant Card whose input is an `event_participation` object.
- Replace the Event Single 774 participant query with the canonical query.
- Hide the complete participant section when the canonical result is empty.
- Retire the legacy Event people helper mode only after checking for remaining callers.
- QA with temporary, task-owned Person and Event Participation records, then remove and verify those records.

### Out of scope

- Templates 772 and 773 and the shared Card 6b463f.
- Product 124, `produk_medewerkers`, `produk_bron_event`, `9-acf-product-copy.php`, and product query modes.
- Profile migration, legacy Event-edge migration, redirects, route changes, or legacy field deletion.
- Broadcast/Uitsending host and guest migration.

## Readiness conclusion

Event Single 774 can cut over while persistent Person and Event Participation counts are zero. Task 6C must verify the canonical query with temporary owned QA records, verify the empty state, use canonical data only, and leave no QA records behind. No legacy Event edge is approved for migration. The expected persistent participant output for Event 763 is zero.
