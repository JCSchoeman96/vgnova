# Task 6B: Event Consumer Migration Readiness Audit

**Date:** 2026-10-18
**Baseline:** `origin/main` at `c2a5d7560bdd7d96b5a842dfa6c268060db7a3ae`
**Branch:** `docs/event-consumer-readiness-6b`
**Scope:** Read-only runtime discovery and documentation

## Objective

Record the exact migration readiness of the Event-facing legacy consumers before Task 6C. The canonical Event-page loop will use `event_participation` records and resolve each record to its canonical `person` endpoint. This task does not change WordPress runtime state or implement the consumer cutover.

## Locked decisions

- Event Single participant rows are `event_participation` objects, not direct `person` results.
- Platform Core owns the future Bricks query integration and its order: `event_participation_display_order` ascending, then participation ID ascending.
- The new participant card resolves `event_participation_person`, links to the canonical Person permalink, and displays the Person image/title, optional `person_professional_title`, and optionally populated `event_role` terms.
- The Event section is hidden when the canonical query returns zero rows. Do not show a default empty-state message or add legacy fallback behavior.
- The Event 763 edges to profiles 753 and 757 remain `EXCLUDE`; Event 763 therefore has zero canonical participants.
- Medewerker reverse-event/profile consumers and all product consumers remain on their existing data until separate approved migrations.
- Consumer cutover does not remove `event_medewerkers` or change any source relationship.

## Work sequence

1. Confirm the requested baseline and use the isolated documentation worktree.
2. Inspect the VGNova runtime through read-only Novamira and WP-CLI calls. Capture Bricks 774, 772, 773, 124, 1127, component 6b463f, ACF definitions, active FluentSnippets, canonical counts, and protected record values.
3. Re-audit the exact active `10-vg-bricks-medewerker-queries.php` source, hash, status, hook, modes, inputs, returned types, ordering, empty behavior, fallbacks, and template callers.
4. Cross-check source-controlled Platform Core, Git history, and existing architecture records. Distinguish runtime-only configuration from repository source.
5. Write the approved design, evidence audit, consumer map, post-task snapshot, and focused updates to the consumer inventory, decommission register, and implementation ledger.
6. Validate Markdown links and changed CSVs, run `git diff --check`, search for false implementation claims, and request an independent documentation review.
7. Commit and push the docs-only branch, open a PR against `main`, register it with the thread, and leave it unmerged.

## Runtime and Git boundaries

- WordPress, Bricks, FluentSnippets, ACF, taxonomy, route, profile, Event, and product writes: **0**.
- No PHP, JSON, JavaScript, CSS, Bricks export, or snippet source changes.
- No Person or Event Participation records created; no migration or redirect work.
- Keep the PR open and unmerged.

## Completion checks

- The Event 774 element and query contract, empty behavior, field dependencies, and cutover readiness are evidenced.
- Every active helper query mode has an independent lifecycle decision.
- Reverse-event, archive, shared card, product, and broadcast consumers are separately classified.
- Event 763 source edges remain unchanged and excluded from migration.
- Final counts and protected-state checks are recorded from read-only runtime reads.
- The PR targets `main` and remains open and unmerged.
