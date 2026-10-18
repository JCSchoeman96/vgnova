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

The final review fixes were deployed from the source module after backing up the active Event classes to `/tmp/vgnova-task6a-round4-runtime-backup-20261018`. All four deployed Event class files passed byte-for-byte comparison with the source. The bootstrap was unchanged in this deployment.

The Local ACF JSON path before Task 6A pointed to `/home/jcschoeman96/projects/current/vgnova/site-code/acf-json/`. It now points to:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/feat-event-participation-6a/site-code/acf-json
```

This temporary source dependency is required while the PR is open. Post-merge Task 6A-S must stabilize the path onto the merged main checkout and verify the three ACF definitions again before the worktree is removed.

The default Novamira MCP profile resolved to a different installation (`DanOptics`) during the initial runtime work, so local WP-CLI was used for those checks. For the final review QA, the explicitly selected `VGNova` Novamira profile was verified against `Voelgoed` at `http://localhost:10039/` and ran the guarded WP-CLI script. Local shell copied the source-controlled PHP files into the active plugin after a backup because Novamira file writes do not allow PHP files in the active plugin directory. No direct SQL writes were used.

## QA run and cleanup

The final runtime QA run after the round 3 review fixes passed on 2026-10-18 using published Event 151. It created temporary Person 1306 and participation 1307, checked the approved roles `paneellid` and `spreker`, used display order 17, verified title updates after changing the Person title, and verified trashing a participation while its Person endpoint was trashed followed by restoration and republishing.

| QA check | Result |
|---|---|
| Published and reverse relationship queries | PASS |
| Private participation excluded from active queries | PASS |
| Participation has no public route | PASS |
| Duplicate Event × Person pair rejected | PASS |
| Invalid and trashed endpoint rejected | PASS |
| Participation title follows the renamed Person | PASS |
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
| Platform Core PHP classes and bootstrap deployment | 5 classes plus bootstrap integration | Runtime copy matches source module |
| Temporary QA Person | 9 | All nine hard-deleted and verified absent |
| Temporary QA participation | 10 | Nine ownership-marked rows hard-deleted by QA; one duplicate from the failed query was individually verified and hard-deleted |
| Legacy profile, event, product, relationship, Bricks, or FluentSnippet writes | 0 | Protected state unchanged |

## Regression checks

| Check | Result |
|---|---:|
| Event Participation ACF schema contracts | 3 passed |
| Event Participation Platform Core tests | 64 passed |
| Content Topic Platform Core regression tests | 67 passed |
| Person Foundation tests | 10 passed |
| Editorial Dimensions tests | 11 passed |
| QA setup failure reporting regression check | 1 passed |
| PHP lint for changed PHP files | Passed |
| Shared ACF JSON parsing | 9 files parsed |

The Task 6A Platform Core suite adds 64 tests to the prior 67-test Content Topic suite, for 131 tests across those two standalone runners. Earlier reviews found that same-post edits could interleave, duplicate checks could use stale WordPress/ACF caches, and invalid endpoints could block retirement. The final review found that a failed candidate post read could be mistaken for no duplicate, title sync could restore a stale status after a trash operation, and creation with `future` status had no scheduled date. The repository now fails closed on candidate read errors while skipping genuinely missing candidates; title sync takes a scoped participation lock before reading status or endpoints, refreshes them after acquiring the lock, and preserves locks owned by an outer save; and the service rejects `future` creation because it has no schedule-date input. Existing `future` rows still reserve their Event × Person pair. Regression tests cover those findings along with cached prechecks, endpoint status changes, different-pair edits, overlapping trash transitions, and retirement with invalid endpoints.

The first post-review staging QA attempt exposed an incorrect escaped newline in the fresh duplicate query and left temporary participation 1282 behind after its duplicate attempt. The remaining row was tied to the failed run's unique QA title token and was hard-deleted after its Event and missing Person IDs were checked against the clean baseline. Subsequent guarded QA runs passed; the final run above reported zero Persons and participations, no token, and unchanged protected runtime state. The staging database is MySQL 8.0.35; a cross-version compatibility matrix was not run. The Task 7 runtime QA setup boundary was also re-reviewed and fixed so token generation or service construction failures return JSON before mutation.

## Legacy and migration state

Event 763 still has its two `event_medewerkers` links to profiles 753 and 757; those known edges remain `EXCLUDE` and unchanged. Products 767 and 768, their `produk_bron_event` and `produk_medewerkers` values, all five `medewerker` profiles, and their other protected state remain unchanged. No edge or profile was copied to the canonical model.

Event Single 774, the Medewerker reverse-event query, and the product copy automation remain legacy consumers. The source ACF fields and consumers remain active. All five current profile records remain `DEFER`; product and event consumer migration remains gated on separately approved provenance decisions.

See [Event Participation implementation](EVENT_PARTICIPATION_IMPLEMENTATION.md) for the source contract and [Person migration architecture](PERSON_MIGRATION_ARCHITECTURE.md) for current Person eligibility and downstream gates.
