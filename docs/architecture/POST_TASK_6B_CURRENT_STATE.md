# Post-Task 6B Current-State Snapshot

**Date:** 2026-10-18
**Branch:** `docs/event-consumer-readiness-6b`
**Base:** `origin/main` at `c2a5d7560bdd7d96b5a842dfa6c268060db7a3ae`
**Worktree:** `.worktrees/docs-event-consumer-readiness-6b`
**PR:** Pending creation; target `main`; merge is not authorized

## Task outcome

Task 6B completed read-only discovery of the Event-facing consumer surface. The evidence supports an independent Task 6C cutover for Event Single 774. No consumer was cut over and no legacy relationship was migrated.

## Runtime impact

| Mutation class | Count | Result |
|---|---:|---|
| WordPress writes | 0 | Read-only Novamira/WP-CLI calls only |
| Bricks writes | 0 | Templates and components unchanged |
| FluentSnippet writes | 0 | Both active runtime snippets unchanged |
| ACF definition writes | 0 | Field groups unchanged |
| Taxonomy writes | 0 | Terms and relationships unchanged |
| Route or redirect writes | 0 | No routing work |
| Canonical record creation | 0 | No Person or Event Participation created |
| Legacy data migrations | 0 | No event or product edge converted |

No PHP, JSON, JavaScript, CSS, Bricks export, or FluentSnippet source change was made in Git. Changes are documentation and evidence only.

## Canonical state

| Entity or vocabulary | Final state |
|---|---|
| Persistent `person` records | 0 |
| Persistent `event_participation` records | 0 |
| `event_role` | 4 terms / 0 relationships |
| `content_topic` | 55 terms / 7 roots |

The four `event_role` terms are `spreker`, `paneellid`, `fasiliteerder`, and `gas`. The seven `content_topic` roots remain `nuwe-jy`, `geloof`, `self`, `werk-geld`, `ouerskap`, `huwelik`, and `mensstories`.

## Protected legacy state

| Protected data | Final read-only state | Changed |
|---|---|---|
| Medewerker profiles 753–757 | Present; remain `DEFER` | No |
| Event 763 | `tc_events`; `event_medewerkers=[753,757]` | No |
| Event 763 → 753 | `EXCLUDE` source edge still present | No |
| Event 763 → 757 | `EXCLUDE` source edge still present | No |
| Product 767 | `produk_bron_event=763`; `produk_medewerkers=[753]` | No |
| Product 768 | Trash; `produk_bron_event=763`; `produk_medewerkers=[753,757]` | No |
| Three product-person edges | `EXCLUDE` source edges still present | No |
| Bricks | Templates, components, and configuration unchanged | No |
| FluentSnippets | Published helpers unchanged | No |

The Event 763 relationship remains serialized on the Event under `event_medewerkers`. Its `_event_medewerkers` value is the ACF field-key pointer, not a reverse relationship on profile posts. The legacy loop's profile-side query returns no IDs, but this does not change or remove the source edge.

## Event Single 774 readiness

| Item | Finding |
|---|---|
| Participant section | `e1c7e9`; static content remains visible when the loop is empty |
| Query container / loop | `1661b9` / `be9e2f` |
| Current legacy query | Native Bricks `medewerker` query using `_event_medewerkers={post_id}`; Bricks default ordering |
| Current returned object | `medewerker`; zero matches for Event 763 |
| Local card / modal | `2c59af` / `afc060`; no shared Card 6b463f instance |
| Dynamic tags | `{featured_image}`, `{post_title}`, `{acf_medewerker_titel}`, `{mb_people_title}`, `{post_content}`; the query also uses `{post_id}` |
| Current empty behavior | No explicit empty message or section condition; the loop yields no cards while the surrounding section stays visible |
| Canonical replacement | `event_participation[]`, ordered by display order ascending then participation ID ascending; resolve Person from `event_participation_person` |
| Readiness | `READY_FOR_CUTOVER` in Task 6C, with canonical-only query and whole-section hide on zero rows |

The separate product loop `cxqfiw` remains outside the Event participant cutover.

## Shared card and query helper

Card `6b463f` expects a `medewerker` post and renders featured image, post title, `medewerker_titel`, `medewerker_kort_bio`, and `{post_url}`. It remains `KEEP_LEGACY`; it is not safe for a canonical Person object without modification. Its consumers are Templates 773, 124, and 1127, not 774.

The active helper `10-vg-bricks-medewerker-queries.php` is published, runs on all pages, and has SHA-256 `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`. Its six modes have separate lifecycle decisions in the [consumer audit](EVENT_CONSUMER_MIGRATION_AUDIT.md#query-mode-inventory) and [migration map](EVENT_CONSUMER_MIGRATION_MAP.csv). Only the Event people mode may retire after 6C and a source-wide caller check. Product modes remain for the later product phase.

## Readiness counts

Counts below are based on each migration-map row's single primary `readiness_status`.

| Primary readiness state | Count |
|---|---:|
| `READY_FOR_CUTOVER` | 1 |
| `KEEP_LEGACY` | 13 |
| `BLOCKED_BY_DATA` | 0 |
| `BLOCKED_BY_ARCHITECTURE` | 2 |
| `RETIRE` | 1 |
| `NOT_APPLICABLE` | 4 |

`BLOCKED_BY_DATA` appears as a blocking reason on four `KEEP_LEGACY` rows: the Medewerker reverse Event query, reverse product query, Single template, and Archive template. It is not counted as a second primary state.

## Task 6C boundary

Task 6C should add the source-controlled Platform Core Bricks Event Participation query, add a new Event Participant Card, replace only the participant loop in Template 774, hide the complete participant section when the canonical query is empty, and retire the unused legacy Event people helper mode only if a source-wide caller check passes.

Task 6C does not include Templates 772 or 773, shared Card 6b463f, Product 124, product fields or automation, profile migration, legacy Event-edge migration, redirects, or broadcast consumers. `event_medewerkers` stays stored and unchanged until all consumers and write paths are migrated and the field-removal gate passes.

See the [Task 6B audit](EVENT_CONSUMER_MIGRATION_AUDIT.md), [consumer map](EVENT_CONSUMER_MIGRATION_MAP.csv), [design](../superpowers/specs/2026-10-18-event-consumer-migration-readiness-design.md), and [implementation ledger](IMPLEMENTATION_LEDGER.md).
