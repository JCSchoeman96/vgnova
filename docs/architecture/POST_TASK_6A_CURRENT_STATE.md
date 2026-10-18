# Post-Task 6A current-state snapshot

**Date:** 2026-10-18
**Branch:** `feat/event-participation-6a`
**Base:** `main` at `cd1e6c8304b21f768610e56e79714f0e29e9adfd`
**Runtime:** Local staging at `http://localhost:10039/`

> **Historical pre-merge capture.** The branch, base, Task 6A outcome, and feature-worktree ACF path below record the review state before PR #20 merged. The final post-merge stabilization state appears in the section at the end.

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

The endpoint-title cache fix was deployed after backing up `EventParticipationAdmin.php` to `/tmp/vgnova-task6a-round5-runtime-backup-20261018`. That runtime file also passed byte-for-byte comparison with the source.

The Local ACF JSON path before Task 6A pointed to `/home/jcschoeman96/projects/current/vgnova/site-code/acf-json/`. It now points to:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/feat-event-participation-6a/site-code/acf-json
```

This temporary source dependency is required while the PR is open. Post-merge Task 6A-S must stabilize the path onto the merged main checkout and verify the three ACF definitions again before the worktree is removed.

The default Novamira MCP profile resolved to a different installation (`DanOptics`) during the initial runtime work, so local WP-CLI was used for those checks. For the final review QA, the explicitly selected `VGNova` Novamira profile was verified against `Voelgoed` at `http://localhost:10039/` and ran the guarded WP-CLI script. Local shell copied the source-controlled PHP files into the active plugin after a backup because Novamira file writes do not allow PHP files in the active plugin directory. No direct SQL writes were used.

## QA run and cleanup

The pre-revision runtime QA run after the round 4 review fixes passed on 2026-10-18 using published Event 151. It created temporary Person 1310 and participation 1311, checked the approved roles `paneellid` and `spreker`, used display order 17, verified title updates after changing the Person title, and verified trashing a participation while its Person endpoint was trashed followed by restoration and republishing.

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
| Temporary QA Person | 12 | All twelve hard-deleted and verified absent |
| Temporary QA participation | 13 | Twelve ownership-marked rows hard-deleted by QA; one duplicate from the failed query was individually verified and hard-deleted |
| Legacy profile, event, product, relationship, Bricks, or FluentSnippet writes | 0 | Protected state unchanged |

## Regression checks

| Check | Result |
|---|---:|
| Event Participation ACF schema contracts | 3 passed |
| Event Participation Platform Core tests | 65 passed |
| Content Topic Platform Core regression tests | 67 passed |
| Person Foundation tests | 10 passed |
| Editorial Dimensions tests | 11 passed |
| QA setup failure reporting regression check | 1 passed |
| QA cleanup ownership regressions | 3 passed |
| QA cleanup/setup regressions | 4/4 |
| PHP lint for changed PHP files | Passed |
| Shared ACF JSON parsing | 9 files parsed |

The Task 6A Platform Core suite adds 65 tests to the prior 67-test Content Topic suite, for 132 tests across those two standalone runners. Earlier reviews found that same-post edits could interleave, duplicate checks could use stale WordPress/ACF caches, and invalid endpoints could block retirement. Later reviews found that a failed candidate post read could be mistaken for no duplicate, title sync could restore a stale status after a trash operation, creation with `future` status had no scheduled date, and endpoint title reads could use stale post objects after a lock wait. The repository now fails closed on candidate read errors while skipping genuinely missing candidates; title sync takes a scoped participation lock before reading status or endpoints, refreshes the pair and both endpoint posts after acquiring the lock, and preserves locks owned by an outer save; and the service rejects `future` creation because it has no schedule-date input. Existing `future` rows still reserve their Event × Person pair. Regression tests cover those findings along with cached prechecks, endpoint status changes, different-pair edits, overlapping trash transitions, fresh endpoint titles after lock waits, and retirement with invalid endpoints.

The first post-review staging QA attempt exposed an incorrect escaped newline in the fresh duplicate query and left temporary participation 1282 behind after its duplicate attempt. The remaining row was tied to the failed run's unique QA title token and was hard-deleted after its Event and missing Person IDs were checked against the clean baseline. Subsequent guarded QA runs passed. Two guarded review-correction runs passed on 2026-10-18. The first used Event 151, temporary Person 1314, and tracked participation 1315. After tightening a cleanup read failure to fail closed, the final run used Event 151, temporary Person 1318, and tracked participation 1319. Both reported zero remaining Persons or participations, no run token, zero Person taxonomy and `event_role` relationships, no cleanup errors, and unchanged protected state and Event. A read-only post-run count confirmed Person 0, `event_participation` 0, Person taxonomy relationships 0, `event_role` relationships 0, and exactly 4 `event_role` terms. The staging database is MySQL 8.0.35; a cross-version compatibility matrix was not run. The Task 7 runtime QA setup boundary was also re-reviewed and fixed so token generation or service construction failures return JSON before mutation.

## Legacy and migration state

Event 763 still has its two `event_medewerkers` links to profiles 753 and 757; those known edges remain `EXCLUDE` and unchanged. Products 767 and 768, their `produk_bron_event` and `produk_medewerkers` values, all five `medewerker` profiles, and their other protected state remain unchanged. No edge or profile was copied to the canonical model.

Event Single 774, the Medewerker reverse-event query, and the product copy automation remain legacy consumers. The source ACF fields and consumers remain active. All five current profile records remain `DEFER`; product and event consumer migration remains gated on separately approved provenance decisions.

See [Event Participation implementation](EVENT_PARTICIPATION_IMPLEMENTATION.md) for the source contract and [Person migration architecture](PERSON_MIGRATION_ARCHITECTURE.md) for current Person eligibility and downstream gates.

## Post-merge Task 6A-S stabilization

- Date: 2026-10-18
- Task 6A PR: [#20](https://github.com/JCSchoeman96/vgnova/pull/20)
- Merge commit and stable main HEAD: `797c19597a39812df9d06090340ca60b1173cc37`
- Documentation PR: Pending creation, to remain open for independent review.

Task 6A-S moved the active ACF Local JSON symlink from the removed Task 6A worktree to the repository's stable `main` checkout. This was the only WordPress runtime change. WordPress data writes: **0**.

### ACF source path and file parity

| Check | Result |
|---|---|
| Old symlink target | `/home/jcschoeman96/projects/current/vgnova/.worktrees/feat-event-participation-6a/site-code/acf-json` |
| New symlink target | `/home/jcschoeman96/projects/current/vgnova/site-code/acf-json` |
| Resolved target after feature-worktree removal | `/home/jcschoeman96/projects/current/vgnova/site-code/acf-json` |
| Shared ACF files | 9/9 SHA-256 matches between the approved feature head and merged main |

The matching files and hashes are:

| ACF JSON file | SHA-256 in both trees |
|---|---|
| `group_vg_editorial_classification.json` | `ec32c95f75b9b1c6599b09312fa0b71a9806044277c0cd7865519df37ace7030` |
| `group_vg_person_details.json` | `d37ec3c5d908dd6497fd953319375b641a1534574e83b600eff0bfc702be8e95` |
| `post_type_person.json` | `222dc1839d2a0ffe891aadd4c84fc68a41cbb6cb72667c57ad14cdac4abe583a` |
| `taxonomy_editorial_collection.json` | `a6bb5c6a4d3b8ba3639e6288405c3b913e683c917e041b0d0c2b9735f34e41b4` |
| `taxonomy_editorial_format.json` | `90ad05e7cba39ea9f0f5e57f36e23990cc5074c25a83e2ad1f2c7273b8d22234` |
| `taxonomy_person_type.json` | `d32dba47956594771ce12e5b0eb6c4b61a866d7a90dcce679a777ffc57d9b8c2` |
| `group_vg_event_participation_details.json` | `09232c12e7d7cce7ee7c66c9fef4c699410297315112b883ca82b01d842d71c7` |
| `post_type_event_participation.json` | `e42dab60911974f762d68bc17fca8a151e92511a14e33f3972074ec75b1a6a2c` |
| `taxonomy_event_role.json` | `e9794d6b6f6be6ff563f5e13c0328d91fa29988434b0d3ed3c0d35834330a895` |

### Deployed Platform Core parity

The active plugin remained enabled after the worktree was removed. WordPress loaded all five Event Participation classes. Each deployed file matched the merged-main source byte-for-byte:

| Event class | Runtime/source SHA-256 |
|---|---|
| `EventParticipationRepository.php` | `77c853606d9cdefbb5db4a9c77476bf2c74c0a62bc1e6081d10e1391851eff7f` |
| `EventParticipationPairLock.php` | `95107d2db6c3e713c6685cd0c13aa7fe4764f89b7ac37596843e436f74e9c49c` |
| `EventParticipationService.php` | `4075158d9a6ab4b4079f5722bb9a2736190eb779dfd2ae1282e9c9bf74362600` |
| `EventParticipationAdmin.php` | `bbdbea299e6712f84d574ea5d5a25c517f2917920d36858f6bf8bf92b5e3b9f9` |
| `EventRoleSeeder.php` | `9b0396bf9b2e4b3515fb0b2bd5224e03e501fd964efe80e03cd3f1a76f98c4e6` |

Merged `integration.patch` contains the five Event component includes and their bootstrap registration.

### ACF runtime status and canonical state

The ACF Local JSON status check showed the three Task 6A definitions in sync. The detailed pending list contained only the existing Member Perk definitions `group_vgmp_perk_details` and `group_vgmp_options`; both remained untouched. The ACF runtime schemas matched the source configuration after excluding generated ACF metadata and empty runtime defaults.

| Definition | Runtime status |
|---|---|
| `post_type_event_participation` | IN_SYNC |
| `taxonomy_event_role` | IN_SYNC |
| `group_vg_event_participation_details` | IN_SYNC |

Read-only runtime checks after worktree removal confirmed:

| Runtime state | Result |
|---|---|
| `event_participation` registered | Yes |
| `event_participation` public / publicly queryable / archive / rewrite | `false` / `false` / `false` / `false` |
| Persistent Person records | 0 |
| Persistent `event_participation` records | 0 |
| `event_role` terms / relationships | 4 / 0: `spreker`, `paneellid`, `fasiliteerder`, `gas` |
| Person `person_type` relationships | 0 |
| Person `content_topic` relationships | 0 |
| `content_topic` terms / roots | 55 / 7 |

### Protected legacy state and worktree cleanup

Profiles 753–757 remain present and retain their reviewed values. Each remains `DEFER`. Event 763 retains `event_medewerkers` values `[753, 757]`. Product 767 retains `produk_bron_event=763` and `produk_medewerkers=[753]`. Product 768 retains `produk_bron_event=763` and `produk_medewerkers=[753, 757]`. No canonical Person or Event Participation relationship was created for these excluded edges. Bricks and FluentSnippets were not changed, and no legacy migration ran.

The Task 6A worktree and its local branch were removed after all source, runtime, and regression gates passed. The active ACF symlink still resolves to `site-code/acf-json/` in the stable main checkout. The runtime no longer depends on the Task 6A worktree. WordPress continued to load from `http://localhost:10039/` after removal.

### Task 6A-S regression gate

| Check | Result |
|---|---:|
| Event Participation | 65/65 |
| Event Role Seeder | 13/13 |
| Content Topic | 67/67 |
| ACF schema | 3/3 |
| Person Foundation | 10/10 |
| Editorial Dimensions | 11/11 |
| QA cleanup | 3/3 |
| QA setup | 1/1 |
| PHP lint across the affected source and test modules | PASS |
| ACF JSON parse | 9/9 |
| `git diff --check` | PASS |
