# Event consumer cutover design

**Task:** Voelgoed Task 6C

**Date:** 2026-10-18

**Status:** Conversational design approved; written specification submitted for final review

**Required base:** `main` at `ba1b48c7726d9ce477a52eb32bb354a5259edd0c`

## Goal

Move the participant presentation in Bricks Event Single template 774 from the legacy `event_medewerkers → medewerker` path to the canonical `event_participation → person` path. The cutover changes presentation only. It does not migrate legacy relationships or Person records.

For an Event, the public path becomes:

```text
tc_events → event_participation[] → person → canonical Person permalink
```

`EventParticipationRepository::participations_for_event()` remains the authority for Event membership, endpoint validation, published-only results, malformed-record exclusion, and display ordering. Bricks must preserve the returned ID order.

## Confirmed starting context

The implementation branch starts from the specified `origin/main` SHA in an isolated worktree. Read-only inspection of the current staging site found:

- Persistent `person` and `event_participation` counts are both zero.
- `event_role` has four terms and zero relationships; `content_topic` has 55 terms and seven roots.
- Event 763 stores legacy `event_medewerkers` values `[753, 757]`. Task 5C marks both edges `EXCLUDE`.
- Template 774, `Event Single`, is published and has the `tc_events` condition.
- The template has 103 Bricks elements. The legacy participant loop is `be9e2f`, its section is `e1c7e9`, its grid is `1661b9`, and its local card is `2c59af`.
- The participant loop still queries `medewerker` using `_event_medewerkers`.
- The static introduction is `3c88cc`.
- The separate product loop is `cxqfiw`.
- The participant modal is `afc060`, with body `fdca1f`.
- Component searches found no existing component named for Event Participants. The legacy shared `Medewerker Card` remains component `6b463f`.

The implementation must recapture the complete runtime baseline immediately before any Bricks write. A difference from the Task 6B structure is a stop condition, not permission to adapt the mutation to unknown drift.

## Scope

Task 6C changes only the participant presentation in Event Single 774 and adds its Platform Core integration. It will:

1. Add `EventParticipationBricks` at `site-code/voelgoed-platform-core/includes/Event/EventParticipationBricks.php`.
2. Register that lifecycle component through the source bootstrap and `integration.patch`, following `EventParticipationAdmin` and `EventRoleSeeder`.
3. Add semantic markers for the canonical participant query and its containing section. The proposed marker names are `vg-event-participation-query` and `vg-event-participants-section`.
4. Add a new Bricks component named `Event Participant Card`.
5. Replace the Event 774 participant loop and its local participant card/modal with the canonical loop and component.
6. Hide the complete participant section on the frontend when there are no canonical results, while keeping the section visible in the builder.
7. Run temporary staging QA and remove every owned QA record.
8. Audit callers before deciding whether to retire only the `event_medewerkers` mode in runtime helper `10-vg-bricks-medewerker-queries.php`.
9. Record before/after runtime evidence and update the architecture records listed below.

## Explicit exclusions

Do not migrate or change Medewerker profiles, Event relationships, product relationships, Product Template 124, Voelgoed Live Template 1127, Medewerker Single 772, Medewerker Archive 773, component `6b463f`, ACF schemas or vocabularies, routes, redirects, global styles, ACSS tokens, Frames, or product-copy automation `9-acf-product-copy.php`.

Keep the ACF `event_medewerkers` field active and unchanged. Keep the five other helper modes unchanged: `event_products`, `medewerker_events`, `medewerker_products`, `product_medewerkers`, and `product_source_event`.

## Platform Core adapter

`EventParticipationBricks` will be a lifecycle component. It will register a Bricks posts-query filter and an element-render filter, using only the semantic markers to identify the relevant query and section. Runtime element IDs are evidence for the targeted template edit; they are not the adapter's query or visibility contract.

For a marked query, the adapter will:

1. Resolve the current Event from Bricks preview context or the current singular post.
2. Accept only a valid `tc_events` context.
3. Call `EventParticipationRepository::participations_for_event($event_id)`.
4. Set the query to `event_participation`, `publish`, and all returned IDs, with `post__in` ordering and no pagination.

For repository IDs `[1305, 1302, 1310]`, the effective query must use `post_type=event_participation`, `post_status=publish`, `post__in=[1305,1302,1310]`, `orderby=post__in`, and `posts_per_page=-1`. The adapter must not implement repository validation or ordering a second time.

An invalid Event context or an empty repository result must produce a guaranteed-empty query, such as `post__in=[0]`. An empty list must never broaden the query to every participation record. Unmarked loops must pass through untouched. There is no fallback to or merge with `event_medewerkers`.

For the marked participant section, the adapter will use the same repository call to decide frontend visibility. It will omit the section when that call returns no IDs. It will not hide the section during Bricks builder/editor requests, and it will not affect unmarked sections. The implementation must use the Bricks render lifecycle available in the installed runtime and cover the builder exception with source tests.

## Event Participant Card

Create a new component separate from `6b463f`. Its outer loop item is an `event_participation`. A nested Person Posts Query Loop resolves `event_participation_person` to the canonical `person` rendering context.

The card renders:

- the Person featured image;
- the Person title;
- `person_professional_title` only when populated;
- a link using the Person's canonical WordPress permalink.

The card must not hard-code `/mense/` or link to `/medewerkers/`. It must not use legacy Medewerker fields, Person excerpt/content, credentials, company, website, `content_topic`, or `person_type`. Do not infer an Event role from profile fields. Event-role labels are deferred.

Reuse only local classes that inspection confirms are presentation-only. Do not carry the legacy modal trigger, modal-specific interaction classes, `{mb_people_title}`, modal `{post_content}`, or social links to `#` into the new card.

Record the new component's ID, exact title, status, content hash, and a Task 6C ownership marker where the Bricks component format allows one safely. Do not overwrite a component with a similar name.

## Event Single 774 mutation

Before any Bricks write, save an exact restorable copy and record at least the template ID, status, modified timestamp, full Bricks content hash, participant subtree hash, element count, participant element IDs/settings, product-loop settings, and template condition.

Only the participant section subtree may change: section `e1c7e9`, grid `1661b9`, loop `be9e2f`, and its local card/modal descendants. Add the semantic query and section markers and reference the new component. Keep the static introduction subtree `3c88cc` byte-for-byte unchanged. Keep product loop `cxqfiw`, all other sections, and the template condition unchanged.

After writing, compare full before/after structures and hashes. Prove that the legacy participant query and modal are absent, the canonical query and markers are present, the new component is used, and all protected subtrees are unchanged. A visual check alone is insufficient.

If a Bricks write, component creation, deployment, QA, or cleanup fails, restore the exact pre-Task-6C template snapshot. Remove only the uniquely owned new component if abandoning the task. Do not roll back protected legacy content.

## Source tests

Add focused Platform Core tests for the adapter. Cover unmarked queries, invalid and non-Event contexts, repository delegation, ID order preservation, guaranteed-empty results, frontend section visibility with and without IDs, builder/editor visibility, and unrelated sections. Do not duplicate repository ordering tests beyond proving the adapter passes repository order to Bricks.

Keep the established Task 6A baselines intact: Event Participation 65, Event Role Seeder 13, Content Topic 67, ACF schema 3, Person Foundation 10, Editorial Dimensions 11, and QA setup/cleanup 4. Report the new Bricks count and the combined count separately.

## Temporary staging QA

Choose a published real `tc_events` post other than Event 763 with no `event_medewerkers` values and no canonical participations. Snapshot its ID, title, slug, status, modified time, legacy relationship, relevant taxonomy relationships, and canonical participation count. Verify its frontend first returns HTTP success with no participant section, card, or legacy modal.

Create exactly two uniquely owned temporary `person` records with `[QA][AUTOMATED] Task 6C <run-token>` ownership text. Person A has a professional title; Person B has none. Give each a distinct name and slug. Through `EventParticipationService`, create exactly two participations for the selected Event, with A at display order 20 and B at 10.

Verify on the rendered Event page that B precedes A; both names and optional professional-title behavior render correctly; and each card links to the corresponding canonical Person permalink. If temporary featured images are assigned, verify both resolve. Use only unprotected existing attachments and leave attachment records unchanged. Verify both Person URLs return HTTP 200 and neither card links to `/medewerkers/`. Confirm the participant modal behavior and its legacy profile content are absent.

While the temporary records exist, verify Event 763 still has legacy values `753,757`, has zero canonical participations, and displays no canonical participant section or legacy profiles. This proves the consumer has no legacy fallback.

Delete temporary participations first, then Persons. Before each hard delete, verify the exact record ID, post type, Event endpoint, owned Person endpoint, and ownership marker where available. Continue cleanup after an individual failure and report each failure. After cleanup, confirm zero persistent Person records, zero participations, zero Person `person_type` and `content_topic` relationships, and zero participation `event_role` relationships. Confirm the QA Event is unchanged and its participant section is hidden again.

## Legacy helper retirement gate

Freshly search every Bricks template and component, active FluentSnippets/runtime helper code, repository source, Git history, and architecture/evidence documentation for `event_medewerkers` callers.

Remove only the `event_medewerkers` helper mode if and only if the caller count is zero. If evidence is ambiguous or any caller remains, retain the mode and record retirement as deferred. If removed, record helper before/after SHA-256, exact mode-level diff, snippet active state, remaining five modes, hook, priority, and run condition. Do not modify `9-acf-product-copy.php`.

## Protected state and final runtime records

Capture before/after evidence for profiles 753–757, attachments 558/560/562/564/566, Event 763 and its legacy values, Products 767/768 and their legacy fields, taxonomy vocabularies, Templates 772/773/124/1127, component `6b463f`, and `9-acf-product-copy.php`. No persistent Person or participation records, legacy relationship writes, new Event role terms, or new `content_topic` terms are allowed.

Create `docs/architecture/EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json` with the baseline SHA; Template 774 ID, status, modified time, before/after full-content and participant-subtree hashes, and before/after element counts; component ID, title, status, content hash, and ownership marker; semantic markers; source/runtime parity and `integration.patch` result; helper before/after hashes or unchanged status, exact mode diff, active state, remaining modes, hook, priority, run condition, and retirement decision; QA Event/Person/participation IDs; frontend order and canonical-link results; Event 763 isolation; cleanup results; QA outcome; and final persistent counts. Do not include credentials or unrelated runtime data.

Create `docs/architecture/EVENT_CONSUMER_6C_IMPLEMENTATION.md` and `docs/architecture/POST_TASK_6C_CURRENT_STATE.md`. Update these existing files only where Task 6C changes status:

- `docs/architecture/EVENT_CONSUMER_MIGRATION_AUDIT.md`
- `docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv`
- `docs/architecture/PERSON_CONSUMER_INVENTORY.csv`
- `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`
- `docs/architecture/IMPLEMENTATION_LEDGER.md`

After success, Event Single 774 is `MIGRATED_CANONICAL`, the new card is `ACTIVE_CANONICAL`, and the local Event participant modal is `RETIRED`. `event_medewerkers` remains `KEEP_LEGACY`; its helper mode is `RETIRE` only after the zero-caller gate, otherwise `KEEP_LEGACY` with retirement deferred. Do not claim that Medewerker profiles, routes, product relationships, or other legacy templates are removable.

## Stop conditions

Stop before runtime mutation if Template 774 has materially drifted from the captured Task 6B structure, a canonical Event Participant component is discovered, the Bricks query/section behavior contradicts this design, or exact rollback cannot be preserved. Do not invent another presentation model; return the contradiction for architecture review.

## Pull request

Use branch `feat/event-consumer-cutover-6c` from the specified `origin/main` base. The implementation PR title is `feat: cut Event participants over to canonical model`, targeting `main`. Keep the PR open and unmerged for independent review.

## Acceptance criteria

- The source-controlled Platform Core adapter owns only the marked canonical query and marked section visibility, delegates membership to the repository, preserves repository order, and fails closed.
- Event Single 774 has no legacy `medewerker` participant query or participant modal. It uses the new card and canonical Person permalink, keeps its static introduction, and leaves its product loop and unrelated content unchanged.
- Empty canonical results hide the whole frontend section, including on Event 763, while builder editing remains available.
- Temporary two-Person QA proves visible ordering, optional professional title, canonical links, no legacy fallback, pre/post empty state, and modal retirement. Cleanup returns persistent Person and participation counts to zero and leaves the real QA Event unchanged.
- Protected profiles, attachments, Event 763 legacy values, products, templates, shared card, product automation, fields, and vocabularies remain unchanged.
- Migration counts remain zero for legacy Person profiles, legacy Event edges, and legacy product edges.
- The helper retirement decision follows a fresh zero-caller audit and preserves all other modes.
- Platform Core tests, regression counts, PHP lint, JSON/CSV parsing, integration patch parity, runtime QA, and `git diff --check` are reported with exact results.
- The runtime manifest and architecture updates are complete, and the PR remains open and unmerged.
