# Post-Task 6A current-state snapshot

**Date:** 2026-10-18
**Branch:** `feat/event-participation-6a`
**Base:** `main` at `cd1e6c8304b21f768610e56e79714f0e29e9adfd`
**Runtime:** Local staging at `http://localhost:10039/`

## Task outcome

The canonical Event Participation foundation is implemented in source and active on the local staging site. This task did not migrate legacy profiles, Event-person links, or product-person links, and did not change Bricks or FluentSnippets consumers.

## Runtime schema and vocabulary

| Check | Final state |
|---|---|
| `event_participation` ACF CPT | Registered; admin and REST enabled; not public, queryable, searchable, archived, or rewritten |
| `event_role` ACF taxonomy | Registered and attached only to `event_participation`; non-public; no public route |
| ACF field group | `group_vg_event_participation_details`, active on `event_participation` |
| Persistent Person records | 0 |
| Persistent Event Participation records | 0 |
| `event_role` terms | Exactly 4: `spreker`, `paneellid`, `fasiliteerder`, `gas` |
| Event Participation role relationships | 0 |
| Person `person_type` relationships | 0 |
| Person `content_topic` relationships | 0 |

The first seeder run created the four approved Event roles. The second run created none and reported all four as existing. The three Task 6A ACF definitions were individually dry-run and synchronized; all three are in source/runtime parity. The unrelated pending Member Perk groups `group_vgmp_perk_details` and `group_vgmp_options` were not changed.

## Runtime deployment and ACF source path

The active Platform Core plugin copy at `wp-content/plugins/voelgoed-platform-core/` contains the four source-controlled Event classes and the Task 6A bootstrap integration. Plugin loading and PHP lint checks passed.

The deployed Event class files compare byte-for-byte with the source module. Runtime bootstrap hashes after deployment are:

| Runtime file | SHA-256 |
|---|---|
| `voelgoed-platform-core.php` | `9f6c994c06250416a42b922748f67bb26b29d99515b4e41a094d705c1ee5623d` |
| `includes/Plugin.php` | `65bfeb9cabc4320755183ea34ab1097090848638f324b8b85aa9b80948f9e1d2` |

The pre-deployment snapshot is retained at `/tmp/vgnova-task6a-runtime-predeploy-20261018`.

The Local ACF JSON path before Task 6A pointed to `/home/jcschoeman96/projects/current/vgnova/site-code/acf-json/`. It now points to:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/feat-event-participation-6a/site-code/acf-json
```

This temporary source dependency is required while the PR is open. Post-merge Task 6A-S must stabilize the path onto the merged main checkout and verify the three ACF definitions again before the worktree is removed.

Novamira MCP resolved to a different installation (`DanOptics`), so local WP-CLI was used to inspect and update the correct `Voelgoed` staging runtime. No direct SQL writes were used.

## QA run and cleanup

The final runtime QA run passed on 2026-10-18 using published Event 151. It created temporary Person 1272 and participation 1273, checked the approved roles `paneellid` and `spreker`, and used display order 17.

| QA check | Result |
|---|---|
| Published and reverse relationship queries | PASS |
| Private participation excluded from active queries | PASS |
| Participation has no public route | PASS |
| Duplicate Event × Person pair rejected | PASS |
| Invalid and trashed endpoint rejected | PASS |
| Temporary Person and participation removed | PASS |
| QA token and temporary relationships absent | PASS |
| Protected runtime state unchanged | PASS |
| Selected Event unchanged | PASS |
| Cleanup failures | 0 |

After cleanup, Person count and Event Participation count were both zero. Person type/topic and `event_role` relationship counts were zero. The four Event role terms remain by design.

## Runtime writes

| Write | Count | Final state |
|---|---:|---|
| Targeted ACF definition syncs | 3 | Persistent canonical schema active |
| Approved `event_role` term creation | 4 | Persistent vocabulary active |
| Platform Core PHP classes and bootstrap deployment | 4 classes plus bootstrap integration | Runtime copy matches source module |
| Temporary QA Person | 1 | Hard-deleted and verified absent |
| Temporary QA participation | 1 | Hard-deleted and verified absent |
| Legacy profile, event, product, relationship, Bricks, or FluentSnippet writes | 0 | Protected state unchanged |

## Regression checks

| Check | Result |
|---|---:|
| Event Participation ACF schema contracts | 3 passed |
| Event Participation Platform Core tests | 47 passed |
| Content Topic Platform Core regression tests | 67 passed |
| Person Foundation tests | 10 passed |
| Editorial Dimensions tests | 11 passed |
| QA setup failure reporting regression check | 1 passed |
| PHP lint for changed PHP files | Passed |
| Shared ACF JSON parsing | 9 files parsed |

The Task 6A Platform Core suite adds 47 tests to the prior 67-test Content Topic suite, for 114 tests across those two standalone runners. The Task 7 runtime QA setup boundary was also re-reviewed and fixed so token generation or service construction failures return JSON before mutation.

## Legacy and migration state

Event 763 still has its two `event_medewerkers` links to profiles 753 and 757; those known edges remain `EXCLUDE` and unchanged. Products 767 and 768, their `produk_bron_event` and `produk_medewerkers` values, all five `medewerker` profiles, and their other protected state remain unchanged. No edge or profile was copied to the canonical model.

Event Single 774, the Medewerker reverse-event query, and the product copy automation remain legacy consumers. The source ACF fields and consumers remain active. All five current profile records remain `DEFER`; product and event consumer migration remains gated on separately approved provenance decisions.

See [Event Participation implementation](EVENT_PARTICIPATION_IMPLEMENTATION.md) for the source contract and [Person migration architecture](PERSON_MIGRATION_ARCHITECTURE.md) for current Person eligibility and downstream gates.
