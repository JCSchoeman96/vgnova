# Task 6C: Canonical Event Participant Consumer Cutover — Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` to implement this plan after the independent plan approval gate. Do not begin implementation or mutate WordPress/Bricks before that approval.

**Goal:** Move only Event Single 774's participant presentation from the legacy `event_medewerkers → medewerker` path to `event_participation → person`, with repository-owned membership and ordering, canonical Person permalinks, and no legacy fallback.

**Architecture:** `EventParticipationBricks` is a Platform Core lifecycle component. It recognizes only the marked Bricks query and participant section, obtains IDs from `EventParticipationRepository::participations_for_event()`, preserves those IDs in Bricks `post__in` order, and fails closed. Template 774 uses a new Event Participant Card whose outer item remains an `event_participation` and whose nested Person Posts Query Loop resolves `event_participation_person`. The entire participant section is omitted on the frontend when canonical results are empty and remains available in the builder.

**Tech Stack:** WordPress, Bricks, ACF Pro, Voelgoed Platform Core, PHP standalone tests, Novamira CLI profile `localhost`, WP-CLI through Novamira, Git worktree.

**Approved design:** `docs/superpowers/specs/2026-10-18-event-consumer-cutover-design.md`

**Base:** `origin/main` at `ba1b48c7726d9ce477a52eb32bb354a5259edd0c`; implementation branch `feat/event-consumer-cutover-6c`.

**Plan status:** Independently approved and executed on 2026-10-19. The Event Single 774 cutover and runtime QA passed. The optional helper mode retirement was deferred after a zero-caller audit because FluentSnippets source editing was unavailable. See [implementation evidence](../../architecture/EVENT_CONSUMER_6C_IMPLEMENTATION.md), [current state](../../architecture/POST_TASK_6C_CURRENT_STATE.md), and [runtime manifest](../../architecture/EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json).

---

## Approval and stop gates

- This plan received independent approval before implementation began. Runtime operations followed the approved gates below; the final PR remains open and unmerged.
- After approval, recheck that the worktree is based on the expected SHA and has no unrelated changes. Keep all implementation in `.worktrees/feat-event-consumer-cutover-6c`; do not work in `main`.
- Stop before any Bricks write if Template 774 has materially changed from the approved 103-element Task 6B structure, if `Event Participant Card` already exists, if `3c88cc` cannot be preserved byte-for-byte, if the Bricks query or nested Person-loop contract does not work as designed, or if exact rollback cannot be verified.
- Stop and request architecture review if resolving the Person requires legacy Medewerker fields, Person excerpt/content, credentials, company, website, `content_topic`, `person_type`, a hard-coded route, Event-role inference, or another relationship model.
- The helper retirement gate is conditional. Any active caller or ambiguous result means the `event_medewerkers` helper mode stays unchanged and retirement is recorded as deferred.
- The existing ACF field, all legacy relationship values, all protected templates/components, and product-copy automation remain out of scope.

## File map

Create:

```text
site-code/voelgoed-platform-core/includes/Event/EventParticipationBricks.php
site-code/voelgoed-platform-core/tests/EventParticipationBricksTest.php
site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
site-code/runtime-tests/event-consumer-6c-qa-ownership.php
site-code/runtime-tests/event-consumer-6c-qa-rendering.php
site-code/runtime-tests/event-consumer-6c-qa.php
site-code/runtime-tests/tests/run-event-consumer-6c-qa-tests.php
docs/architecture/EVENT_CONSUMER_6C_IMPLEMENTATION.md
docs/architecture/POST_TASK_6C_CURRENT_STATE.md
docs/architecture/EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json
```

Modify only as required:

```text
site-code/voelgoed-platform-core/integration.patch
site-code/voelgoed-platform-core/tests/bootstrap.php
site-code/voelgoed-platform-core/README.md
docs/architecture/EVENT_CONSUMER_MIGRATION_AUDIT.md
docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv
docs/architecture/PERSON_CONSUMER_INVENTORY.csv
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Runtime writes are limited to Platform Core deployment, the new Bricks component, the participant subtree in Template 774, optional removal of only the `event_medewerkers` helper mode after its caller gate, and temporary QA records that are hard-deleted. Do not change any ACF JSON, ACF field definition, taxonomy vocabulary, protected template/component, other helper mode, or `9-acf-product-copy.php`.

---

## Task 1: Reconfirm the source and runtime baseline

- [x] **Step 1: Verify Git starting state.**

Run:

```bash
git status --short
git rev-parse HEAD
git merge-base HEAD origin/main
git branch --show-current
```

Expected before implementation: clean worktree except this reviewed plan if it has not yet been committed; branch `feat/event-consumer-cutover-6c`; merge base `ba1b48c7726d9ce477a52eb32bb354a5259edd0c`. Do not reset or rebase automatically if these checks differ.

- [x] **Step 2: Re-read live WordPress state through Novamira `vgnova`.**

Use Novamira CLI profile `localhost` as the primary interface. Verify the site is `http://localhost:10039/`, then record:

```text
Person count = 0
event_participation count = 0
event_role = 4 terms / 0 relationships
content_topic = 55 terms / 7 roots
Event 763 event_medewerkers = [753, 757]
Template 774 = published, tc_events condition, 103 elements
section e1c7e9; introduction 3c88cc; grid 1661b9; loop be9e2f
local card 2c59af; modal afc060; modal body fdca1f
product loop cxqfiw
no existing Event Participant Card component
```

Also snapshot/compare profiles 753–757, attachments 558/560/562/564/566, Event 763, Products 767/768, Templates 772/773/124/1127, component 6b463f, `event_medewerkers` ACF field configuration, and `9-acf-product-copy.php`. Reuse the Task 6A snapshot logic as a reference, but create a Task 6C manifest/snapshot routine; do not edit the Task 6A QA files.

Capture profiles' IDs, titles, slugs, statuses, modified values, profile ACF fields, `medewerker_rol`, `medewerker_groep`, `content_topic`, and `content_category` terms; attachments' IDs, titles, slugs, statuses, modified values, parents, and attachment metadata; Event 763's title/slug/status/modified value, `event_medewerkers`, and relevant taxonomy terms; Products 767/768's title/slug/status/modified values, `produk_bron_event`, `produk_medewerkers`, and relevant terms; exact term ID/name/slug/parent/count snapshots for `event_role` and `content_topic`; and full Bricks hashes/status/conditions for protected templates plus component 6b463f. Record the ACF definition key and field settings for `event_medewerkers`, and the full helper source/hash and product-copy snippet state.

- [x] **Step 3: Record runtime code deployment rollback state.**

Through Novamira, read the active plugin bootstrap and `includes/Plugin.php`. Save exact local backups and SHA-256 values for:

```text
wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php
wp-content/plugins/voelgoed-platform-core/includes/Plugin.php
wp-content/plugins/voelgoed-platform-core/includes/Event/EventParticipationBricks.php (confirm absent)
```

Record active helper `10-vg-bricks-medewerker-queries.php` bytes/hash, published state, run condition, hook, priority, and modes before any optional edit. Do not edit the helper during this baseline task.

**Gate:** A mismatch in protected state, baseline counts, branch ancestry, or materially changed Template 774 stops implementation before runtime mutation. Do not adapt to drift.

---

## Task 2: Write the adapter tests first (TDD)

- [x] **Step 1: Add `tests/EventParticipationBricksTest.php` and a standalone runner before creating the production class.**

`run-event-participation-bricks-tests.php` loads the existing test bootstrap, the real `EventParticipationRepository`, the new test class, and the adapter when present. Keep this runner separate from the historical runner so the existing 65-test baseline remains directly measurable.

Write eleven focused tests, with no duplicated repository ordering suite:

1. An unmarked Bricks Posts query is returned unchanged.
2. A marked query with no valid Event context gets `post__in=[0]`.
3. A marked query in a non-`tc_events` context gets `post__in=[0]`.
4. A marked valid Event query obtains IDs through the real repository method.
5. Repository order `[1305, 1302, 1310]` is retained as `post__in` and paired with `orderby=post__in`.
6. An empty repository result forces `post__in=[0]` and cannot query all participations.
7. A marked frontend section renders when repository IDs exist.
8. A marked frontend section is omitted when repository IDs are empty.
9. Builder/editor requests keep an empty marked section editable.
10. An unrelated Bricks section remains unchanged.
11. Lifecycle registration attaches the query and render filters with the required accepted argument counts.

Tests use the real repository with controlled WordPress test fixtures, so they prove adapter delegation without making `EventParticipationBricks` a second relationship authority. Add only the Bricks context stubs required by this runner; keep them isolated from unrelated suites where possible.

- [x] **Step 2: Run the new focused suite to establish RED.**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
```

Expected: failure because `EventParticipationBricks` is not implemented. Capture the failing test names. Do not proceed if the runner itself cannot load the test fixture or fails for an unrelated PHP error; fix the harness first and rerun RED.

---

## Task 3: Implement `EventParticipationBricks`

- [x] **Step 1: Create `includes/Event/EventParticipationBricks.php`.**

Use namespace `Voelgoed\PlatformCore\Event` and the existing lifecycle conventions. Implement a small component around `EventParticipationRepository`; do not add relationship writes or query membership logic.

Use the lifecycle method `register()` and keep the behavior in focused methods with these responsibilities:

```text
register()
filter_participation_query_vars($query_vars, $settings, $element_id, $element_name)
filter_participant_section_render($render_element, $element)
resolve_current_event_id()
participation_ids_for_event_context()
has_exact_css_class_token($settings, $marker)
```

The method names may follow the module's established style, but these seams and responsibilities remain testable separately.

Register these installed Bricks hooks with accepted argument counts verified against the installed source:

```text
bricks/posts/query_vars  — 4 arguments
bricks/element/render    — 2 arguments
```

The query filter must:

- recognize the exact token `vg-event-participation-query` in the Bricks `_cssClasses` setting; ignore other classes and unmarked loops;
- resolve current context in Bricks preview/post-ID, singular query-object, then current-post order; accept only an existing `tc_events` post;
- call `EventParticipationRepository::participations_for_event($event_id)` for valid Events;
- set `post_type=event_participation`, `post_status=publish`, `post__in` to the repository IDs or `[0]`, `orderby=post__in`, and `posts_per_page=-1`;
- preserve the repository's ID sequence exactly and never read `event_medewerkers`.

The render filter must:

- recognize only the exact `vg-event-participants-section` class token;
- return the original render decision for all unmarked elements;
- keep the section editable during Bricks builder/editor calls;
- on frontend Event requests, use the same repository method/result contract and set render false only when IDs are empty or context is invalid;
- share/memoize the per-request repository result for the current Event if that can be done without changing the repository contract.

Use the Bricks runtime context API present on this site (`\Bricks\Database::$page_data['preview_or_post_id']` when available, then the singular queried object, then current post). Guard class/property/function existence. Do not use a meta query, duplicate ordering, hard-coded template IDs, or runtime element IDs in Platform Core.

- [x] **Step 2: Run focused RED-to-GREEN and lint.**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php -l site-code/voelgoed-platform-core/includes/Event/EventParticipationBricks.php
```

Expected after the implementation: `11 Event Participation Bricks tests passed.` and no PHP syntax errors. If the installed Bricks hook contract or component visibility behavior differs from this design, stop for architecture review.

---

## Task 4: Register the lifecycle component and verify source regressions

- [x] **Step 1: Update the source-controlled integration patch.**

Add the new `EventParticipationBricks.php` require and the `Plugin.php` import plus `register_component('event_participation_bricks', EventParticipationBricks::class)` entry. Match the existing `EventParticipationAdmin` / `EventRoleSeeder` conventions. Do not register the repository as a lifecycle component.

- [x] **Step 2: Update the Platform Core README.**

Document the new Bricks component, semantic markers, focused test command, and deployment parity requirement. State that Template 774 is the sole consumer and that the adapter has no legacy fallback.

- [x] **Step 3: Run all source regression suites.**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
```

Expected established outputs remain `65/65` Event Participation, `13/13` dedicated Event Role Seeder, and `67/67` Content Topic, with the Bricks suite at `11/11`. The dedicated Role Seeder count is separately reported and must not be added twice because its 13 tests are included in the 65-test Event Participation runner. The expected distinct combined Platform Core total is `65 + 67 + 11 = 143`; confirm it from actual output.

Do not change historical test labels or counts to make the new result appear larger or smaller.

---

## Task 5: Prepare the canonical Bricks component without writing it yet

- [x] **Step 1: Reconfirm no existing canonical component.**

Use `novamira/bricks-list-components` with a search for `Event Participant Card`, then inspect the full list for a semantically equivalent canonical Event Participant component. If one exists, stop for architecture review. Do not overwrite or rename it.

- [x] **Step 2: Prepare and review the component element tree locally.**

Prepare the native Bricks element tree for title `Event Participant Card` and a safe Task 6C ownership marker on its root (for example a uniquely scoped `_cssClasses` token). Do not call a Bricks write ability in this task. Its structure must keep `event_participation` as the outer query-loop context and use a nested Person Posts Query Loop configured from `event_participation_person` to establish `person` rendering context.

Inside the Person context use only:

```text
featured image
post title
optional person_professional_title (hidden when empty)
canonical post permalink
```

The permalink uses Bricks/WordPress `{post_url}` while the nested query is on `person`; it must not contain a literal `/mense/` or `/medewerkers/` route. Do not render `event_role` or infer it from profile fields. Reuse only classes confirmed to be presentation-only. Do not carry modal triggers, modal classes, `{mb_people_title}`, modal `{post_content}`, or `#` social links.

If Bricks cannot natively express the approved nested relationship query and canonical Person context, stop and return the contradiction. Do not add a second PHP card resolver or change the approved model.

The component definition is created and verified only after the exact full Template 774 snapshot is saved in Task 7. This ordering makes the snapshot precede every Bricks write, including component creation.

---

## Task 6: Deploy source code before adding semantic markers

- [x] **Step 1: Recheck runtime file hashes against Task 1.**

If either bootstrap file drifted since backup, stop and recapture/review before deployment. Do not overwrite runtime-only changes.

- [x] **Step 2: Deploy the source-controlled class and integration additions.**

Use the existing Platform Core deployment procedure: copy the new source file byte-for-byte to `wp-content/plugins/voelgoed-platform-core/includes/Event/EventParticipationBricks.php`; apply only the Task 6C require/import/registration delta represented by `integration.patch` to the snapshotted runtime bootstrap files. Earlier Task 6A integration hunks are already deployed; do not blindly replay or reverse them. Preserve the pre-deployment files and prepare a minimal Task 6C delta patch from those exact files.

Build the temporary patch from copies of the exact pre-deploy runtime files: insert the new require after the existing Event module requires in `voelgoed-platform-core.php`, and insert the new `use` plus `register_component('event_participation_bricks', EventParticipationBricks::class)` after the existing Event imports/registrations in `includes/Plugin.php`. Require each insertion anchor to occur exactly once, generate the patch with `diff -u` using paths `a/voelgoed-platform-core.php` and `a/includes/Plugin.php`, and compare those additions with the committed `integration.patch` before applying. Do not hand-edit the active runtime bootstrap without a patch against its saved before-copy.

Before applying the delta:

```bash
patch --dry-run --fuzz=0 -p1 -i /tmp/vgnova-task6c/platform-core-task-6c.patch
```

Apply only if the dry run reports the expected bootstrap files/hunks and no fuzz. Then apply the same patch and copy the class. This local filesystem deployment is the documented fallback because Novamira file writes do not permit PHP files in the active plugin directory; use Novamira for all runtime inspection and WP-CLI.

- [x] **Step 3: Verify deployment.**

Run PHP lint on the deployed adapter and bootstrap files, compare source/runtime adapter SHA-256 byte-for-byte, verify the class is required and registered, and confirm plugin bootstrap through Novamira `novamira/run-wp-cli` (`wp eval` checking `class_exists` and the plugin component registration path). Record the deployed-source parity result.

If deployment or plugin loading fails, restore the exact bootstrap backups, remove only the newly deployed class if its SHA matches the Task 6C source, verify the prior plugin loads, and stop before Bricks mutation.

---

## Task 7: Capture the exact Bricks baseline before the first Bricks write

This capture occurs after code deployment and immediately before the first Bricks write, including component creation.

- [x] **Step 1: Read fresh Bricks state through Novamira.**

Use:

```text
novamira/bricks-list-templates({type:"content", status:"any"})
novamira/bricks-get-content({post_id:774, area:"content", summary:false})
novamira/bricks-list-components({verbose:true})
```

Save the full `bricks-get-content` response as `/tmp/vgnova-task6c/template-774-before.json` and component inventory as `/tmp/vgnova-task6c/components-before.json`. Preserve permissions as private to the task user. Hash canonical JSON with:

```bash
python3 -c 'import json,sys; x=json.load(sys.stdin); print(json.dumps(x,sort_keys=True,separators=(",",":"),ensure_ascii=False))' < /tmp/vgnova-task6c/template-774-before.json | sha256sum
```

Record template ID/status/modified/condition; full content hash; participant subtree hash; 103-element count; all participant IDs and query settings; static intro subtree hash; and product-loop `cxqfiw` settings/hash. The snapshot must include the exact full content area, not a summary response.

- [x] **Step 2: Enforce the exact baseline.**

Verify the 6B IDs and structural relationships, no preexisting semantic marker, no existing canonical card, `3c88cc` unchanged from the approved source state, and product loop `cxqfiw` unchanged. If any material drift exists, do not create the component or call any Bricks write ability.

- [x] **Step 3: Keep an exact rollback command ready.**

The rollback uses the saved full `areas.content` array as the `elements` input, not a reconstructed approximation:

```text
novamira/bricks-set-content({
  post_id: 774,
  area: "content",
  elements: <exact areas.content array from template-774-before.json>,
  force: false
})
```

After rollback, call `novamira/bricks-get-content({post_id:774, area:"content", summary:false})`, recompute the same canonical SHA-256 and require exact equality with the before hash and before element count. Do not restore any other template or shared component.

---

## Task 8: Mutate only the participant subtree in Template 774

- [x] **Step 1: Create and verify the new component.**

Call `novamira/bricks-create-component` with the reviewed native element tree prepared in Task 5, label `Event Participant Card`, and its Task 6C ownership marker. Then read it with `novamira/bricks-list-components({verbose:true})`; record component ID, title, status, marker, content hash, and element count. Confirm only one new component was created. If creation fails, do not mutate Template 774; remove the new component only if the returned ID and ownership marker prove it belongs to this run.

Bricks 2.3.5 stores components in the shared `bricks_components` option, not as WordPress posts. Use the verified native record identity and do not invent a post ID or status:

```text
storage_type: bricks_components_option
option_name: bricks_components
component_id: 194bba
component_title: Event Participant Card
post_id: null
post_status: null
record_hash: 270357dcf089d8aa99030e9089f82f907104616b82b0179e26ede2e7670fec6e
```

Before further component writes, capture an exact restorable backup of the entire `bricks_components` option and its hash. Record the whole-option before and after hashes around the component mutation. The pre-component option hash is reconstructed from the verified append-only Bricks create behavior by removing only entry `194bba`; label it reconstructed in the manifest because it was not captured before the original component creation. The option backup captured after component creation is exact. For rollback, remove only component `194bba` if a targeted removal can be proven safe. Otherwise restore the exact pre-6C option backup. Do not reorder, normalize, or overwrite unrelated component entries.

- [x] **Step 2: Add semantic markers with a targeted patch.**

Use `novamira/bricks-patch-elements` on post 774 / area `content` to add the exact class tokens:

```text
vg-event-participation-query
vg-event-participants-section
```

The query class goes on the canonical Bricks Posts query element; the section class goes on `e1c7e9`. Use the stable markers in `_cssClasses`, not a dependency on `be9e2f` or `e1c7e9` in Platform Core.

- [x] **Step 3: Replace only the legacy participant query settings.**

Patch the existing marked Posts query to use the `event_participation` post type, publish status, no pagination, and the Task 6C marker. Do not write `event_medewerkers` into its query settings. Do not alter the separate product loop `cxqfiw`.

- [x] **Step 4: Replace the local participant card/modal with the new component instance.**

Use `novamira/bricks-remove-content` only on the exact legacy participant card/modal subtree IDs found in the fresh snapshot; use `novamira/bricks-apply-component` with the new component ID inside the participant Posts loop. Keep `3c88cc` intact even if its parent is inside the participant section. Do not remove the whole section or grid unless the saved structure proves that doing so preserves the introduction and every non-participant child; prefer the narrowest descendant operation.

The relevant modal subtree IDs are evidence only; re-read the full tree before selecting removal IDs. Do not remove an unrelated modal or shared component.

- [x] **Step 5: Verify every Bricks write immediately.**

After each write, inspect the ability result's `verified`, `stored_element_count`, warnings, and returned revision ID. Read Template 774 back with full settings. If any write is unverified or the tree differs unexpectedly, stop and execute the exact rollback above before further writes.

- [x] **Step 6: Prove the targeted structural diff.**

Save full after content to `/tmp/vgnova-task6c/template-774-after.json`; compute full, participant subtree, introduction subtree, and product-loop hashes with the same canonical JSON method. Produce a structural diff proving:

```text
template condition unchanged
product loop cxqfiw unchanged
all non-participant sections unchanged
introduction 3c88cc byte-for-byte unchanged
participant loop no longer queries medewerker / _event_medewerkers
canonical event_participation loop and query marker present
section marker present
new component instance used
legacy participant modal and triggers absent
no legacy title/content/social placeholder output remains in the loop
```

Record before/after hashes and element counts; do not use visual inspection as a substitute.

---

## Task 9: Prove empty-state and Event 763 isolation before QA records

- [x] **Step 1: Recheck canonical counts and select the QA Event.**

Through WP-CLI via Novamira, select one published `tc_events` post that is not 763, has no `event_medewerkers`, and for which `EventParticipationRepository::participations_for_event($id)` is empty. Save its ID, title, slug, status, modified time, legacy relationship values, relevant taxonomy relationships, and canonical participation count. Do not update the Event.

- [x] **Step 2: Verify the empty-state page before creating any temporary data.**

Use Novamira WP-CLI to issue a read-only `wp eval` that calls `wp_remote_get(get_permalink(<qa-event-id>))` and records `wp_remote_retrieve_response_code()` as 200. Then use T3 browser tools: call `preview_status` first; if there is no automation-capable tab, call `preview_open`, then `preview_navigate` to the QA Event URL and inspect with `preview_snapshot`. Record the rendered page's absent participant section, absent card output, and absent legacy modal output.

- [x] **Step 3: Verify Event 763's canonical isolation.**

Before creating temporary records, confirm through WordPress/Novamira reads that Event 763 still has legacy `event_medewerkers=[753,757]` and zero repository participations. Verify its canonical section is absent and none of profiles 753/757 appear in canonical participant output. Repeat these checks while the temporary records for the other Event exist in Task 11.

If either Event page displays legacy participants or the empty section remains visible, stop, retain all source state, and debug the adapter before any QA record creation.

---

## Task 10: Run the fresh helper retirement audit at the cutover boundary

**Exact gate position:** after Template 774's targeted mutation and structural diff pass, and after the no-data empty-state check in Task 9, but before creating temporary QA Persons or Participations and before changing the helper.

- [x] **Step 1: Audit all runtime Bricks templates and components.**

Use `bricks-list-templates` and retrieve full content for every template, including every page of results; use `bricks-list-components({verbose:true})` for all components. Search their settings for active `vg_query_type=event_medewerkers` or equivalent references to the helper mode. Record each match and whether it is a live caller.

- [x] **Step 2: Audit active runtime and source callers.**

Inventory every active FluentSnippets item and read its runtime source, with focused inspection of `10-vg-bricks-medewerker-queries.php`; cross-check the FluentSnippets active list against files under `wp-content/fluent-snippet-storage/`. Search repository source with `rg -n`, Git history with `git log -S'event_medewerkers' --all`, and architecture/evidence docs. Classify text-only documentation and historical mentions separately from executable/current callers. Search coverage must include every source in the design; incomplete inventory or ambiguous dynamic callers means defer retirement.

- [x] **Step 3: Decide without modifying any other mode.**

If the fresh active caller count is exactly zero and evidence is conclusive, save an exact backup/hash and remove only the `event_medewerkers` switch branch using the FluentSnippets editor/runtime workflow. Do not edit its generated PHP file behind FluentSnippets. Keep the snippet published, with run condition `all`, hook `bricks/posts/query_vars`, priority 10, accepted args 4, and the five modes:

```text
event_products
medewerker_events
medewerker_products
product_medewerkers
product_source_event
```

If a caller remains, evidence is ambiguous, or the FluentSnippets-owned edit path is unavailable, leave the helper file and snippet state byte-for-byte unchanged and record `retirement deferred`. Never edit `9-acf-product-copy.php` or the ACF `event_medewerkers` field.

- [x] **Step 4: Verify the conditional helper change.**

If removed, record before/after SHA-256, exact mode-level diff, active FluentSnippet state before/after, remaining five mode names, hook, priority, accepted args, and run condition. If deferred, prove identical before/after hash and status. Re-run focused caller searches against the saved final Template 774 and components.

---

## Task 11: Add guarded two-Person runtime QA (test cleanup before creating records)

- [x] **Step 1: Add ownership and cleanup tests before QA helper code.**

Add `run-event-consumer-6c-qa-tests.php` and test ownership tracking/cleanup before implementing `event-consumer-6c-qa-ownership.php`. Cover at least:

1. Person IDs are tracked immediately after insertion and before marker writes.
2. Participation IDs are tracked immediately after `EventParticipationService::create()` returns.
3. Wrong post type refuses hard deletion.
4. Changed Event/Person endpoints refuse participation deletion.
5. Conflicting owner markers refuse deletion.
6. Participations are deleted before their owned Persons.
7. Cleanup continues across independent records after one cleanup failure.
8. A Person with a remaining owned participation is retained.
9. Successful cleanup verifies the post, metadata, and taxonomy relationships are gone.

Run the focused suite to establish RED, then implement the guards and require `9/9` passing. Keep the established QA setup/cleanup suite at `4/4`; report the 6C cleanup tests separately.

- [x] **Step 2: Implement the phase-based runtime QA runner.**

`event-consumer-6c-qa.php` has guarded `setup`, `cleanup`, and `verify-clean` phases. It writes a run manifest outside WordPress content under `/tmp/vgnova-task6c/qa/<run-token>.json`, and every created ID is written there immediately before any subsequent field or ownership-marker write. Re-running setup for the same token must refuse to create duplicate records. Cleanup continues over independent IDs and reports each failure.

- [x] **Step 3: Setup exactly two Persons and two canonical Participations.**

Invoke the script through Novamira `novamira/run-wp-cli` (`localhost`). WP-CLI `eval-file` rejects unknown `--phase` flags, so pass the phase and token as positional `key=value` arguments:

```json
{"args":["eval-file","/home/jcschoeman96/projects/current/vgnova/.worktrees/feat-event-consumer-cutover-6c/site-code/runtime-tests/event-consumer-6c-qa.php","phase=setup","run-token=<unique-token>"]}
```

The real QA Event is the ID captured in Task 9. Create exactly two published `person` posts with distinct run-token titles/slugs and `_vg_task_6c_qa_owner` value `[QA][AUTOMATED] Task 6C <run-token>`. Set Person A's `person_professional_title`; leave Person B's field empty. Do not reuse 753–757. Assign only safe, unprotected existing featured-image attachments; if none are suitable, leave images unset and record that fact.

Create exactly two published participations using `EventParticipationService::create($event_id, $person_id, [], $display_order, 'publish')`:

```text
Person A: display_order 20
Person B: display_order 10
```

Track each returned participation ID before any further call; write and verify the Task 6C owner marker. The service is the only participation writer. Do not add Event-role terms.

- [x] **Step 4: Prove rendered frontend output while records exist.**

Use T3 preview and inspect the QA Event. Record actual DOM order `Person B → Person A`; both names; Person A title; no fabricated title for B; each card's canonical `{post_url}` target; absence of `/medewerkers/` links and legacy modal behavior; and image resolution if images were assigned. Use read-only WP-CLI `wp eval` calls with `wp_remote_get()` and `wp_remote_retrieve_response_code()` for the QA Event and both Person permalinks; require 200 for each. Check Task 6B modal-specific selectors/content only, not unrelated site-wide modals.

While both temporary participations exist, recheck Event 763 independently: `event_medewerkers=[753,757]`, canonical participations zero, participant section absent, profiles 753/757 not rendered through the canonical loop, and no fallback.

- [x] **Step 5: Cleanup immediately in a `finally` path.**

Invoke `--phase=cleanup` through Novamira WP-CLI even if a frontend assertion fails. Verify each tracked ID, post type, QA Event endpoint, owned Person endpoint, and marker where present. Delete all owned participations first, hard-delete them with `wp_delete_post($id, true)`, then delete each Person whose own participation cleanup is verified. Continue after individual failures; report the ID and reason. Do not delete any record with a changed endpoint, wrong type, or conflicting marker.

- [x] **Step 6: Verify clean end state and Event immutability.**

Invoke `--phase=verify-clean`; require zero persistent `person`, `event_participation`, Person `person_type`, Person `content_topic`, and participation `event_role` relationships. Require four `event_role` terms, unchanged topic/role vocabulary, QA Event fields/taxonomies/status/modified value equal to the pre-QA snapshot, and its frontend participant section hidden again.

If cleanup verification fails, retry only the guarded cleanup for tracked IDs, continue independent cleanup, preserve the logs/manifest, and do not call the Task complete. If the implementation is abandoned after the failure, restore the exact Template 774 snapshot and remove only the uniquely owned new component after its instance is gone.

---

## Task 12: Final parity, documentation, and review package

- [x] **Step 1: Recheck all protected state.**

Compare before/after evidence for profiles 753–757; attachments 558/560/562/564/566; Event 763 and legacy edges; Products 767/768 and their ACF relationships; vocabularies; Templates 772/773/124/1127; component 6b463f; ACF `event_medewerkers`; and `9-acf-product-copy.php`. All must be unchanged. Record zero migrations of Person profiles, legacy Event edges, and product edges.

- [x] **Step 2: Write the runtime manifest from collected evidence.**

Populate `EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json` with the baseline SHA, template 774 before/after full and participant hashes and counts, storage-aware component identity/hash, the full `bricks_components` option hashes and backup provenance, semantic markers, source/runtime parity, integration patch result, helper hashes/state/decision, QA Event/Person/participation IDs, frontend order, canonical URL/status results, Event 763 isolation, cleanup results, protected-state results, and persistent final counts. Use JSON `null` for component `post_id` and `post_status`, and describe them as not applicable in prose. Store no credentials or unrelated runtime data. Validate:

```bash
python3 -m json.tool docs/architecture/EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json >/dev/null
```

- [x] **Step 3: Write implementation and current-state docs; update only materially changed records.**

Create `EVENT_CONSUMER_6C_IMPLEMENTATION.md` and `POST_TASK_6C_CURRENT_STATE.md`. Update the migration audit, migration map, Person consumer inventory, decommission register, and implementation ledger only for actual 6C status changes. Mark Event Single 774 `MIGRATED_CANONICAL`, Event Participant Card `ACTIVE_CANONICAL`, and its local modal `RETIRED`. Keep `event_medewerkers` ACF field and remaining legacy consumers `KEEP_LEGACY`. Mark only the participant-presentation dependency satisfied. Mark the helper mode `RETIRE` only if Task 10 removed it after a zero-caller audit; otherwise record `KEEP_LEGACY / retirement deferred`.

- [x] **Step 4: Run final source and artifact checks.**

```bash
php -l site-code/voelgoed-platform-core/includes/Event/EventParticipationBricks.php
php -l site-code/runtime-tests/event-consumer-6c-qa-ownership.php
php -l site-code/runtime-tests/event-consumer-6c-qa.php
php -l site-code/runtime-tests/tests/run-event-consumer-6c-qa-tests.php
php site-code/event-participation/tests/run-event-participation-foundation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php site-code/person-foundation/tests/run-person-foundation-tests.php
php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
php site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php
php site-code/runtime-tests/tests/run-event-participation-qa-cleanup-tests.php
php site-code/runtime-tests/tests/run-event-consumer-6c-qa-tests.php
python3 -m json.tool docs/architecture/EVENT_CONSUMER_6C_RUNTIME_MANIFEST.json >/dev/null
python3 -c 'import csv; [list(csv.reader(open(p, newline="", encoding="utf-8"))) for p in ["docs/architecture/EVENT_CONSUMER_MIGRATION_MAP.csv", "docs/architecture/PERSON_CONSUMER_INVENTORY.csv"]]'
git diff --check
```

Also run the source/runtime SHA comparison, `integration.patch` registration parity check, Bricks structural diff verifier, and final protected-state comparison. Expected established results are ACF/Event Participation Foundation 3/3, Platform Core Event Participation 65/65, dedicated Event Role Seeder 13/13 (overlapping, not added twice), Content Topic 67/67, Person Foundation 10/10, Editorial Dimensions 11/11, and existing QA setup/cleanup 4/4. The new Bricks suite is 11/11; the expected distinct combined Platform Core total is 143/143. Report exact suite totals and Task 6C runtime QA PASS/FAIL without rewriting historical counts.

- [x] **Step 5: Commit and open the review PR only after every gate passes.**

Create a focused commit on `feat/event-consumer-cutover-6c`, push, then open:

```text
Title: feat: cut Event participants over to canonical model
Base: main
State: open and unmerged
```

Register the PR URL with the active T3 thread immediately after opening it. Do not merge. In the handoff report the final branch/base/head/ahead/behind/worktree/PR state and every required runtime and test evidence field from the approved design.

---

## Exact rollback procedure

Use this sequence if the Bricks save, new component, runtime deployment, canonical query/empty-state proof, frontend QA, cleanup verification, or final parity check fails and the task is abandoned:

1. Stop further mutations and preserve the QA manifest/logs.
2. Run guarded cleanup for all tracked Task 6C participations first, then owned Persons; continue over independent IDs and report unresolved failures.
3. Restore Template 774 content with `novamira/bricks-set-content` using the exact `areas.content` array from `/tmp/vgnova-task6c/template-774-before.json`; read it back and require the original full hash, participant subtree hash, element count, product-loop settings, introduction subtree hash, and template condition.
4. If the new component was created by this run and is no longer referenced after template restore, verify its ownership marker/ID/hash and call `novamira/bricks-delete-component({component_id:<owned-id>, orphan_instances:"error"})`. Never delete another component.
5. If Platform Core deployment must be rolled back, restore the exact pre-deploy bootstrap backups and remove the new runtime class only when its hash equals the Task 6C source. Verify the previous plugin bootstrap loads and protected runtime file hashes match.
6. If helper retirement was applied and the task is abandoned, restore the exact helper backup only when its current hash equals the Task 6C after-hash. Verify the five other helper modes and snippet state. Do not touch the ACF field or `9-acf-product-copy.php`.
7. Verify protected templates/components/records and final persistent counts again. Document any state that could not be restored; do not claim success or open a PR.

---

## Independent plan approval gate

The written design and implementation plan were approved before implementation. The checklist records the completed sequence. The optional helper retirement was deferred at its caller gate, and the review PR remains open and unmerged.


## Execution record

The full Template 774 snapshot and exact restore input remain in `/tmp/vgnova-task6c/`. The new native component is option entry `194bba` in `bricks_components`; its component post ID and status are not applicable. Full option hashes, backup provenance, and targeted rollback proof are recorded in the runtime manifest.

Four temporary QA runs were cleaned successfully. The final run proved rendered ordering, optional title behavior, canonical Person URLs, modal retirement, Event 763 isolation, and the empty state after cleanup. Persistent Person and Event Participation counts returned to zero.
