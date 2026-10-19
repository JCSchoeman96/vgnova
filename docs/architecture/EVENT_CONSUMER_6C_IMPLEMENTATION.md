# Task 6C implementation: Event participant consumer cutover

**Date:** 2026-10-19
**Branch:** `feat/event-consumer-cutover-6c`
**Base:** `ba1b48c7726d9ce477a52eb32bb354a5259edd0c`
**Runtime:** Voelgoed at `http://localhost:10039/`

## Result

Event Single 774 now reads participant IDs through `EventParticipationRepository::participations_for_event()`. The marked Bricks query targets `event_participation`, preserves repository order with `post__in`, and returns `[0]` for an invalid or empty context. The adapter does not read `event_medewerkers`.

The new lifecycle component is `site-code/voelgoed-platform-core/includes/Event/EventParticipationBricks.php`. It registers `bricks/posts/query_vars` and `bricks/element/render`, recognizes only `vg-event-participation-query` and `vg-event-participants-section`, and leaves builder requests editable when the repository has no rows. For the marked query it returns only the five canonical query arguments, so Bricks-inherited meta, taxonomy, search, or ordering filters cannot narrow the repository result.

Template 774 changed only in its participant subtree. The section now contains the static introduction and canonical loop. The loop uses the `Event Participant Card` component, whose outer item remains an `event_participation` and whose nested Person query resolves `event_participation_person`. The card renders the Person featured image, title, optional `person_professional_title`, and canonical `{post_url}`. It does not render `event_role` or use legacy profile fields.

The local participant card and modal interaction were retired. The introduction at `3c88cc` stayed byte-for-byte unchanged. Product loop `cxqfiw` and all non-participant template content stayed unchanged. Template 774 remains published with its `tc_events` condition and original modified timestamp.

## Runtime component identity

Bricks 2.3.5 stores components in the `bricks_components` option. The Task 6C component is option entry `194bba`, titled `Event Participant Card`. It has no WordPress post ID or post status; those fields are not applicable.

The initial approved record hash was `270357dcf089d8aa99030e9089f82f907104616b82b0179e26ede2e7670fec6e`. A targeted link correction changed the final record hash to `481c60b1bd2310b96cae05834aa49202777788192927021d218c5d1445cadc7f`. The nested Person link uses Bricks `type=meta` with `{post_url}`, which produced canonical Person permalinks in the runtime QA.

The full option has 57 entries after the change. Its final SHA-256 is `9d11fa323621913572bbb6b03534eaec3ec721c537f9e759ea028ef5481a0293`. The 56 unrelated entries remained in their original order and retained their record hashes. Shared legacy component `6b463f` remained unchanged.

An exact backup of the full option was captured before the targeted link correction at `/tmp/vgnova-task6c/bricks-components-option-before-link-fix.phpser`, SHA-256 `644962adc7fbd9d3d823b69ab8f82d42757bf7b6d3020dcce0a0fec0cc1fdbfb`. The pre-create option hash `cee5337302536f18106b3776d20b9041992eecc6441d4c06bcdccfa0916d5ae9` was reconstructed by removing only `194bba` from the verified append-only result; it was not captured live before the initial component creation. The manifest records this provenance. Targeted removal is safe because the entry is uniquely identified and all other entries and their order were compared before and after.

## Template 774 structural evidence

The exact pre-write content array is saved at `/tmp/vgnova-task6c/template-774-exact-restore-input.json`; the full capture is `/tmp/vgnova-task6c/template-774-prewrite-capture.json`. The cutover output is `/tmp/vgnova-task6c/template-774-after-cutover.json`. A fresh read after the component link correction matched the saved cutover content array exactly.

| Evidence | Before | After | Result |
|---|---:|---:|---|
| Raw `_bricks_page_content_2` SHA-256 | `a613c6e33c3870bcf143ef126470699bc1d2018c4c2a1d4733419574eb3e23c3` | `d327a15afa894bbe723e947e8b839b486adc2c7c50475dacd9a21d96efd92b1f` | Participant subtree changed |
| Canonical export content SHA-256 | `3ead9d9269d3c74114d15aa4986d6a5f5e9d869e242ad9692fbc841bf42103bb` | `5450c53f5022485af64ee73537b244dc1c7c24858c58be66d791c8bbc7c79190` | Expected targeted change |
| Element count | 103 | 72 | 31 participant elements removed |
| Participant subtree `e1c7e9` | `dd4c13c51a0253e60868b1dbeeedb5051334d8977c113c4f070e5d1073944df8` | `12c205de641d99a99f9267d7616743b2c27476539fdd7420b2c55697e62f8afa` | Targeted change |
| Static introduction `3c88cc` | `a296bc1980f1b8e626a9d13d716e5ba69b3ee1031cff257ffc80ac560e3f8c88` | same | Unchanged |
| Product loop `cxqfiw` | `267660d4c9ec20b836274175bd9b61352630d90bca7a7c9df655e083aca54ab` | same | Unchanged |

The section marker is `vg-event-participants-section`; the query marker is `vg-event-participation-query`. Template 774 no longer contains the legacy `medewerker` participant query, modal `afc060`, modal body `fdca1f`, or trigger `30255d`. Its canonical loop uses `post_type=[event_participation]` and component instance `ae53cc` references component `194bba`.

## Platform Core deployment

The deployed adapter hash matches the source hash `1adee948ecb9a53f762e7f9de62bf721e1bc46c9f8d4e3f093f9fd26a9c87c63`. Runtime bootstrap and `Plugin.php` register the lifecycle class using the existing component convention. The source `integration.patch` contains the corresponding require, import, and registration additions. A guarded follow-up deployment replaced the prior adapter hash `4db7fec1bc2def2bdb8761a0d273c7bfbb39261033bc51dcbe5aada88016d189`; its exact backup is `/tmp/vgnova-task6c-review-fix-20261019/EventParticipationBricks.pre-queryvars-hardening.php`. A Novamira read and a WordPress runtime check confirmed byte parity, class loading, and both hook registrations.

The helper `10-vg-bricks-medewerker-queries.php` remains unchanged at SHA-256 `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2`. The fresh audit scanned 27 Bricks templates, all 57 components, active FluentSnippets, repository source, Git history, and architecture/evidence documents. It found zero active Bricks callers for the `event_medewerkers` helper mode. FluentSnippets exposed no source-edit ability in this connection, and the editor redirected to WordPress login. The mode was retained and retirement is deferred. The other five modes and the snippet's published state, all-pages condition, hook, priority, and accepted argument count remain unchanged. `9-acf-product-copy.php` remained unchanged at SHA-256 `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70`.

## Runtime QA

The QA Event was published Event 151, “Aanlyn, uitsendings …” (`aanlyn-uitsendings`). It had no `event_medewerkers` value and no canonical participations before testing. Its title, slug, status, modified time, taxonomy relationships, and participation count were unchanged after cleanup.

The frontend returned HTTP 200 before QA data existed and after cleanup. In both empty states, the participant section and card output were absent. While the final QA records existed, the section rendered Person B before Person A, matching display orders 10 and 20. Person A's professional title rendered; Person B's empty field produced no title. Both canonical Person URLs returned HTTP 200. No `/medewerkers/` participant links, legacy participant modal, or placeholder social links appeared. No featured images were assigned, so image resolution was not exercised.

Four initial two-Person/two-participation runs were used while diagnosing Bricks output and the HTML checker. The first two exposed missing `href` output from an internal link setting. The third exposed a checker false positive on Bricks `brx-query-trail` links. Each run cleaned its records successfully. The fourth run passed all frontend checks. A fifth run after query-constraint hardening passed the same rendered checks. At most two QA Persons and two participations existed at once; all 10 temporary Person records and 10 temporary participation records across the five runs were hard-deleted.

| Run | Person IDs | Participation IDs | Outcome | Cleanup |
|---|---|---|---|---|
| `task6c-20261019-vg01` | 1325, 1326 | 1327, 1328 | Link assertion failed before the component link fix | PASS |
| `task6c-20261019-vg02` | 1329, 1330 | 1331, 1332 | Diagnostic confirmed missing rendered `href` | PASS |
| `task6c-20261019-vg03` | 1333, 1334 | 1335, 1336 | Checker counted a Bricks loop-trail anchor as a social placeholder | PASS |
| `task6c-20261019-vg04` | 1337, 1338 | 1339, 1340 | Ordering, optional title, canonical links, and modal retirement passed | PASS |
| `task6c-review-fix-20261019-01` | 1341, 1342 | 1343, 1344 | Rechecked frontend output after marked-query constraints were cleared | PASS |

Event 763 remained unchanged with `event_medewerkers=[753,757]`, zero canonical participations, no participant section, and no legacy profile output. The canonical query did not fall back to the legacy field.

After cleanup, persistent counts were Person 0, Event Participation 0, Event Role terms 4, Event Role relationships 0, Person `person_type` relationships 0, and Person `content_topic` relationships 0. `content_topic` remained at 55 terms and 7 roots.

## Regression checks

The final run passed:

| Suite | Result |
|---|---:|
| Event Participation | 65/65 |
| Event Participation Bricks adapter | 12/12 |
| Event Role Seeder | 13/13 |
| Content Topic | 67/67 |
| Event Participation ACF schema | 3/3 |
| Person Foundation | 10/10 |
| Editorial Dimensions | 11/11 |
| Existing QA setup and cleanup | 4/4 combined |
| Task 6C QA ownership and rendering | 12/12 |

The distinct Platform Core total is 144/144: 65 Event Participation, 67 Content Topic, and 12 Bricks adapter tests. The dedicated 13-test Event Role Seeder suite overlaps with the Event Participation suite and is reported separately.

## Independent review follow-up

The independent review found that the marked query kept Bricks-inherited constraints such as `meta_query` and `tax_query`. A test with legacy Event filters failed before the fix. The adapter now returns only `post_type`, `post_status`, ordered `post__in`, `orderby=post__in`, and `posts_per_page=-1`; the focused suite passes 12/12. A separate regression test covers frontend Event context when Bricks supplies no preview ID.

After deploying the change, runtime QA run `task6c-review-fix-20261019-01` verified an empty Event before setup, B-before-A order with display orders 10 and 20, optional title output, canonical Person links, modal absence, and Event 763 isolation. Cleanup and `verify-clean` passed. No QA featured images were assigned, so image resolution remains untested. The exported Bricks snapshots and exact rollback files remain in `/tmp/vgnova-task6c/` on the implementation machine; the manifest records their paths and hashes.

A follow-up independent review was attempted with OpenCode after the hardening commit, but the provider returned “Insufficient account funds” before reviewing the code. The PR remains open for independent review.

No Person profile, legacy Event edge, or product edge was migrated. The runtime manifest records the exact state and hashes.
