# Canonical Event Participation Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the source-controlled canonical `event_participation` Event↔Person relationship foundation, four controlled contextual Event roles, canonical query/write behavior, guarded runtime QA, and concise implementation ledger without migrating any legacy relationship.

**Architecture:** ACF Pro Local JSON owns the `event_participation` CPT, `event_role` taxonomy, and participation fields. Voelgoed Platform Core owns relationship validation, uniqueness, canonical queries, administrative title behavior, and the supported programmatic write path. Legacy `event_medewerkers`, Bricks consumers, and product automation continue unchanged in parallel.

**Tech Stack:** WordPress, ACF Pro Local JSON, PHP, Voelgoed Platform Core, WP-CLI/runtime QA, Git worktrees.

**Spec:** `docs/superpowers/specs/2026-10-18-event-participation-foundation-design.md`

## Global Constraints

- Baseline must be `origin/main` at `cd1e6c8304b21f768610e56e79714f0e29e9adfd`.
- Work only in isolated branch/worktree `feat/event-participation-6a`.
- `tc_events` remains the canonical Event object.
- `person` remains the canonical Person object.
- `event_participation` is non-public infrastructure with zero persistent records at task end.
- Persistent Person count must end at zero.
- Do not migrate Event 763 → 753/757.
- Do not migrate products 767/768.
- Do not modify `event_medewerkers`, `produk_medewerkers`, or `produk_bron_event`.
- Do not modify Bricks templates/components.
- Do not modify FluentSnippets `9-acf-product-copy.php` or `10-vg-bricks-medewerker-queries.php`.
- ACF schema is source-controlled under `site-code/acf-json/`.
- Platform Core architecture changes must exist under `site-code/voelgoed-platform-core/` before runtime deployment.
- Never run unrestricted `wp acf json sync`.
- Only the three Task 6A ACF definitions may be synchronized.
- Exactly four persistent `event_role` terms are allowed: `Spreker`, `Paneellid`, `Fasiliteerder`, `Gas`.
- Temporary QA records must use `[QA][AUTOMATED]` ownership markers and be hard-deleted.
- No QA record may remain in Trash.
- Any architecture contradiction discovered during implementation is a STOP condition.
- Do not begin consumer migration.

## Review Focus

1. **Duplicate pair hidden in non-published state:** an existing `draft`, `pending`, `private`, or `future` Event×Person participation must block another pair; `trash` and `auto-draft` must not.
2. **Malformed endpoints:** missing, trashed, or wrong-post-type Event/Person IDs must be rejected by canonical writes and omitted from canonical relationship results.
3. **Partial write failure:** if a canonical create inserts the participation post but a later ACF/taxonomy write fails, the owned partial participation must be hard-deleted rather than left orphaned.
4. **Role integrity:** role IDs must resolve to `event_role`; unrelated taxonomy terms must never be accepted as participation roles.
5. **Endpoint title change:** renaming the Event or Person must resynchronize administrative participation titles without changing relationship identity.

---

# File Structure

## ACF schema

Create:

```text
site-code/acf-json/post_type_event_participation.json
site-code/acf-json/taxonomy_event_role.json
site-code/acf-json/group_vg_event_participation_details.json
```

## Event Participation foundation

Create:

```text
site-code/event-participation/seed-event-role-terms.php
site-code/event-participation/tests/run-event-participation-foundation-tests.php
```

## Platform Core

Create:

```text
site-code/voelgoed-platform-core/includes/Event/EventParticipationRepository.php
site-code/voelgoed-platform-core/includes/Event/EventParticipationService.php
site-code/voelgoed-platform-core/includes/Event/EventParticipationAdmin.php

site-code/voelgoed-platform-core/tests/EventParticipationRepositoryTest.php
site-code/voelgoed-platform-core/tests/EventParticipationServiceTest.php
site-code/voelgoed-platform-core/tests/EventParticipationAdminTest.php
site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

Modify:

```text
site-code/voelgoed-platform-core/tests/bootstrap.php
site-code/voelgoed-platform-core/integration.patch
site-code/voelgoed-platform-core/README.md
```

Do not modify existing Content Topic behavior except shared test/bootstrap support needed by the new tests.

## Runtime QA

Create:

```text
site-code/runtime-tests/event-participation-qa.php
```

## Documentation

Create:

```text
docs/superpowers/specs/2026-10-18-event-participation-foundation-design.md
docs/superpowers/plans/2026-10-18-event-participation-foundation.md

docs/architecture/EVENT_PARTICIPATION_IMPLEMENTATION.md
docs/architecture/POST_TASK_6A_CURRENT_STATE.md
docs/architecture/IMPLEMENTATION_LEDGER.md
```

Modify only as materially required:

```text
docs/architecture/PERSON_MIGRATION_ARCHITECTURE.md
docs/architecture/PERSON_CONSUMER_INVENTORY.csv
docs/architecture/LEGACY_DECOMMISSION_REGISTER.md
site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
```

---

# Canonical PHP Interfaces

## `EventParticipationRepository`

Namespace:

```php
Voelgoed\PlatformCore\Event
```

Constants:

```php
POST_TYPE = 'event_participation'
EVENT_FIELD = 'event_participation_event'
PERSON_FIELD = 'event_participation_person'
DISPLAY_ORDER_FIELD = 'event_participation_display_order'
ROLE_TAXONOMY = 'event_role'
ACTIVE_STATUS = 'publish'
UNIQUENESS_STATUSES = ['publish', 'draft', 'pending', 'private', 'future']
```

Public interface:

```php
public function is_valid_event(int $event_id): bool;
public function is_valid_person(int $person_id): bool;

public function find_pair_ids(
    int $event_id,
    int $person_id,
    ?int $exclude_participation_id = null
): array;

public function participations_for_event(int $event_id): array;
public function participations_for_person(int $person_id): array;

public function people_for_event(int $event_id): array;
public function events_for_person(int $person_id): array;

public function live_participations_for_event(int $event_id): array;
public function live_participations_for_person(int $person_id): array;
```

Returned ID arrays must contain integers.

Canonical public relationship queries use only `publish`.

The `live_*` administrative helpers may include the non-retired uniqueness statuses.

Field values must be read through ACF APIs once candidate participation IDs are located. Meta queries may be used to find reverse relationships; do not replace ACF field reads with arbitrary direct-value ownership.

---

## `EventParticipationService`

Namespace:

```php
Voelgoed\PlatformCore\Event
```

Interface:

```php
public function __construct(?EventParticipationRepository $repository = null);

public function validate_pair(
    int $event_id,
    int $person_id,
    ?int $exclude_participation_id = null
): true|\WP_Error;

public function title_for_pair(
    int $event_id,
    int $person_id
): string|\WP_Error;

public function create(
    int $event_id,
    int $person_id,
    array $event_role_term_ids = [],
    int $display_order = 0,
    string $status = 'publish'
): int|\WP_Error;
```

`create()` is the canonical programmatic write path for Task 6A.

It must validate all inputs before inserting where possible.

If a failure occurs after the owned participation post is inserted, hard-delete that new post before returning the error.

Do not delete pre-existing objects during rollback.

---

## `EventParticipationAdmin`

Namespace:

```php
Voelgoed\PlatformCore\Event
```

Interface:

```php
public function __construct(
    ?EventParticipationRepository $repository = null,
    ?EventParticipationService $service = null
);

public function register(): void;

public function validate_acf_save(): void;
public function sync_participation_title(int|string $post_id): void;

public function sync_titles_for_event(
    int $post_id,
    \WP_Post $post,
    bool $update
): void;

public function sync_titles_for_person(
    int $post_id,
    \WP_Post $post,
    bool $update
): void;
```

`register()` owns the relevant ACF/save hooks.

It must use the shared service/repository logic rather than implementing a second duplicate algorithm.

---

# Task 1: Establish Worktree and Commit Approved Design/Plan

**Files:**
- Create: approved design file
- Create: this implementation plan file

- [ ] **Step 1: Verify baseline**

```bash
git fetch origin
git rev-parse origin/main
```

Expected:

```text
cd1e6c8304b21f768610e56e79714f0e29e9adfd
```

If different, STOP and report the new `main` state.

- [ ] **Step 2: Create isolated worktree**

Use the repository's standard worktree workflow.

Branch:

```text
feat/event-participation-6a
```

- [ ] **Step 3: Confirm clean branch**

```bash
git status --short
git merge-base HEAD origin/main
```

Expected clean and exact baseline.

- [ ] **Step 4: Save the approved design and this plan**

Do not reinterpret either document.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/specs/2026-10-18-event-participation-foundation-design.md \
        docs/superpowers/plans/2026-10-18-event-participation-foundation.md
git commit -m "docs: lock event participation foundation design"
```

---

# Task 2: ACF Schema — Tests First

**Files:**
- Create: `site-code/event-participation/tests/run-event-participation-foundation-tests.php`
- Create later in task:
  - `post_type_event_participation.json`
  - `taxonomy_event_role.json`
  - `group_vg_event_participation_details.json`

**Interfaces:**
- Produces the canonical ACF schema consumed by all later tasks.

- [ ] **Step 1: Write failing JSON-contract tests**

Add tests for:

```text
event_participation CPT exact key/slug
public=false
publicly_queryable=false
exclude_from_search=true
show_ui=true
show_in_rest=true
has_archive=false
rewrite=no public permalink
query_var disabled
supports exactly title
```

Add taxonomy test:

```text
key taxonomy_event_role
taxonomy event_role
object_type exactly event_participation
hierarchical=false
public=false
publicly_queryable=false
show_ui=true
show_in_rest=true
native meta box disabled
rewrite=no_permalink
query_var=none
```

Add field group test requiring exactly:

```text
event_participation_event
event_participation_person
event_participation_roles
event_participation_display_order
```

Assert:

```text
event:
  post_object
  tc_events only
  required
  single
  return ID

person:
  post_object
  person only
  required
  single
  return ID

roles:
  taxonomy event_role
  multi_select
  multiple
  optional
  add_term=0
  save_terms=1
  load_terms=1
  return ID

display order:
  number
  optional
  default 0
  minimum 0
```

- [ ] **Step 2: Run tests and confirm failure because files do not exist**

```bash
php site-code/event-participation/tests/run-event-participation-foundation-tests.php
```

Expected: FAIL on missing Task 6A JSON.

- [ ] **Step 3: Create the three ACF JSON definitions**

Mirror the existing source-controlled ACF conventions.

Use stable keys:

```text
post_type_event_participation
taxonomy_event_role
group_vg_event_participation_details

field_vg_event_participation_event
field_vg_event_participation_person
field_vg_event_participation_roles
field_vg_event_participation_display_order
```

Recommended editor labels:

```text
Eventdeelname / Eventdeelnames
Eventrol / Eventrolle
Eventdeelname Besonderhede
```

Do not create any public route.

- [ ] **Step 4: Parse all shared ACF JSON**

Use PHP/Python/jq or equivalent deterministic parser.

Expected: all files under `site-code/acf-json/` parse successfully.

- [ ] **Step 5: Update Editorial Dimensions shared-directory expectation**

The existing Editorial test currently asserts the exact six-file shared directory.

Update only that shared-file expectation to include the three Task 6A files.

Do not weaken it into an unrestricted glob acceptance.

Expected exact shared total after Task 6A:

```text
9 JSON definitions
```

- [ ] **Step 6: Run schema tests**

```bash
php site-code/event-participation/tests/run-event-participation-foundation-tests.php
php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
```

Expected schema tests PASS; Editorial remains 11/11.

- [ ] **Step 7: Commit**

```bash
git add site-code/acf-json \
        site-code/event-participation/tests \
        site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
git commit -m "feat: define event participation ACF schema"
```

---

# Task 3: Controlled `event_role` Seeder

**Files:**
- Create: `site-code/event-participation/seed-event-role-terms.php`
- Modify: Task 6A foundation test runner

**Interface:**

```php
function vg_seed_event_role_terms(): array;
```

Approved vocabulary:

```php
[
    'spreker' => 'Spreker',
    'paneellid' => 'Paneellid',
    'fasiliteerder' => 'Fasiliteerder',
    'gas' => 'Gas',
]
```

- [ ] **Step 1: Add failing seeder tests**

Tests must cover:

```text
clean taxonomy creates exactly four terms
creates no object relationships
rerun creates nothing
exact existing vocabulary is unchanged
partial approved vocabulary creates only missing roles
same slug / wrong name stops before writes
same name / wrong slug stops before writes
unexpected term stops before writes
missing taxonomy fails
```

- [ ] **Step 2: Run and confirm failure**

```bash
php site-code/event-participation/tests/run-event-participation-foundation-tests.php
```

- [ ] **Step 3: Implement `vg_seed_event_role_terms()`**

Follow the proven Person seeder pattern:

1. taxonomy must exist;
2. read all current terms;
3. preflight the complete current set;
4. reject all conflicts before inserting;
5. insert missing approved terms only;
6. verify each inserted name/slug;
7. return `created` and `existing`;
8. never call `wp_set_object_terms`.

WP-CLI execution:

```bash
wp eval-file /path/to/site-code/event-participation/seed-event-role-terms.php
```

- [ ] **Step 4: Run tests**

Expected all foundation tests PASS.

- [ ] **Step 5: PHP lint**

```bash
php -l site-code/event-participation/seed-event-role-terms.php
```

- [ ] **Step 6: Commit**

```bash
git add site-code/event-participation
git commit -m "feat: add controlled event role vocabulary"
```

---

# Task 4: Platform Core Repository — Tests First

**Files:**
- Create:
  - `includes/Event/EventParticipationRepository.php`
  - `tests/EventParticipationRepositoryTest.php`
  - `tests/run-event-participation-tests.php`
- Modify: `tests/bootstrap.php`

**Produces:** canonical read/query contract used by Service, Admin, QA and future consumers.

- [ ] **Step 1: Extend test bootstrap minimally**

Add only stubs/state required by Event Participation tests.

Do not change existing Content Topic expectations.

- [ ] **Step 2: Write failing repository tests**

At minimum:

```text
is_valid_event accepts only existing non-trashed tc_events
is_valid_person accepts only existing non-trashed person

participations_for_event returns publish only
participations_for_event orders display_order ASC then ID ASC

participations_for_person returns publish only

people_for_event returns valid person IDs only
events_for_person returns valid tc_events IDs only

malformed endpoint relationships are omitted

find_pair_ids considers:
publish
draft
pending
private
future

find_pair_ids ignores:
trash
auto-draft

exclude_participation_id excludes the edited participation itself
```

- [ ] **Step 3: Confirm tests fail**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

- [ ] **Step 4: Implement repository**

Use WordPress queries for candidate participation IDs.

Use ACF API for canonical Event/Person field-value reads.

Return integer IDs only.

Do not include draft/pending relationships in active public-facing query helpers.

- [ ] **Step 5: Run Event Participation tests**

Expected PASS for repository tests.

- [ ] **Step 6: Run existing Platform Core baseline**

```bash
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
```

Expected:

```text
67 content topic tests passed.
```

- [ ] **Step 7: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Event/EventParticipationRepository.php \
        site-code/voelgoed-platform-core/tests
git commit -m "feat: add event participation query repository"
```

---

# Task 5: Canonical Write Service

**Files:**
- Create:
  - `includes/Event/EventParticipationService.php`
  - `tests/EventParticipationServiceTest.php`

**Consumes:** `EventParticipationRepository`.

- [ ] **Step 1: Write failing service tests**

Required cases:

```text
valid pair passes

wrong Event type rejected
trashed/missing Event rejected

wrong Person type rejected
trashed/missing Person rejected

duplicate live pair rejected

duplicate trash-only pair does not block

negative display order rejected

unknown event_role term rejected
term from another taxonomy rejected

valid multiple event_role terms accepted

title_for_pair returns:
{Event title} — {Person title}

successful create:
  creates event_participation
  writes Event via update_field
  writes Person via update_field
  writes display order via update_field
  writes roles through native event_role relationship
  uses derived title

post-insert field/taxonomy failure:
  hard-deletes only newly owned participation
  returns error
```

- [ ] **Step 2: Run and confirm failure**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

- [ ] **Step 3: Implement Service**

Allowed create statuses:

```text
publish
draft
pending
private
future
```

Do not create directly in:

```text
trash
auto-draft
```

Use:

```text
update_field()
wp_set_object_terms()
```

for canonical writes.

No direct custom-field ownership through arbitrary `update_post_meta()`.

- [ ] **Step 4: Verify tests**

Run Event Participation tests and existing 67 Platform Core tests.

- [ ] **Step 5: Commit**

```bash
git add site-code/voelgoed-platform-core/includes/Event/EventParticipationService.php \
        site-code/voelgoed-platform-core/tests
git commit -m "feat: add canonical event participation service"
```

---

# Task 6: Admin Validation and Derived Titles

**Files:**
- Create:
  - `includes/Event/EventParticipationAdmin.php`
  - `tests/EventParticipationAdminTest.php`
- Modify:
  - `integration.patch`
  - `README.md`

**Consumes:** Repository + Service.

- [ ] **Step 1: Write failing Admin tests**

Required behavior:

```text
register() wires ACF validation
register() wires participation title sync
register() wires tc_events title-change sync
register() wires person title-change sync

ACF save:
  invalid endpoints rejected
  duplicate Event×Person rejected
  current participation excluded from own duplicate check

participation ACF save:
  title recalculated from endpoints

Event title update:
  live/non-retired related participation titles recalculated

Person title update:
  live/non-retired related participation titles recalculated

unrelated post type:
  no participation mutation
```

- [ ] **Step 2: Run and confirm failure**

- [ ] **Step 3: Implement Admin component**

Use exact ACF field keys:

```text
field_vg_event_participation_event
field_vg_event_participation_person
```

ACF validation must call shared Service/Repository behavior.

Do not create a second duplicate algorithm.

Use recursion guards where required for `wp_update_post()` title updates.

- [ ] **Step 4: Update Platform Core integration patch**

Preserve all existing Content Topic patch entries.

Add source/runtime bootstrap requirements for:

```text
includes/Event/EventParticipationRepository.php
includes/Event/EventParticipationService.php
includes/Event/EventParticipationAdmin.php
```

Add:

```php
use Voelgoed\PlatformCore\Event\EventParticipationAdmin;
```

and component registration:

```text
event_participation_admin
```

Do not register Repository or Service as lifecycle components if they have no `register()` lifecycle.

- [ ] **Step 5: Update Platform Core README**

Document:

```text
Event Participation source files
new test command
runtime deployment requirement
```

Do not turn README into the architecture spec.

- [ ] **Step 6: Run Event Participation + legacy Platform tests**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
```

Record new Event Participation count.

Compute:

```text
Platform Core total = 67 + Event Participation test count
```

- [ ] **Step 7: Commit**

```bash
git add site-code/voelgoed-platform-core
git commit -m "feat: enforce event participation integrity"
```

---

# Task 7: Runtime QA Script

**Files:**
- Create: `site-code/runtime-tests/event-participation-qa.php`

- [ ] **Step 1: Write QA guardrails before runtime mutation**

QA script must refuse to proceed unless:

```text
WP runtime loaded
event_participation registered
event_role registered
person registered

persistent Person count == 0
persistent event_participation count == 0

four expected event_role terms exist
no unexpected event_role terms

event 763 still has exact legacy relationships 753,757
products 767/768 retain expected relationships

candidate QA Event:
  published tc_events
  ID != 763
  zero event_medewerkers
```

- [ ] **Step 2: Snapshot protected runtime state**

At minimum:

```text
profiles 753–757
event 763
products 767/768
content_topic 55 / 7
Person count
participation count
event_role vocabulary
selected QA Event
```

- [ ] **Step 3: Implement owned QA record creation**

Create temporary Person with two independent markers:

```text
[QA][AUTOMATED]
unique run token
```

Use canonical `EventParticipationService::create()` to create participation.

Assign:

```text
Spreker
Paneellid
```

and non-zero display order.

- [ ] **Step 4: Verify runtime contract**

Assert:

```text
Event → participation PASS
Event → Person PASS
Person → participation PASS
Person → Event PASS
multiple roles PASS
display order PASS
derived title PASS
non-public participation routing PASS
```

- [ ] **Step 5: Prove duplicate rejection**

Call canonical Service create again for the same pair.

Expected:

```text
WP_Error
no second valid participation
```

- [ ] **Step 6: Prove endpoint title synchronization**

Temporarily changing the QA Person title is permitted because the QA Person is owned by this run.

Verify participation admin title follows.

Restore is unnecessary because QA Person will be hard-deleted.

Do not alter the real Event title.

- [ ] **Step 7: Guarded cleanup**

Use `try/finally`.

Before deletion verify ownership markers.

Hard-delete:

```text
QA participation
QA Person
```

Ensure none remains in Trash.

- [ ] **Step 8: Compare protected snapshots**

All real protected state must match before/after.

- [ ] **Step 9: Commit QA script**

```bash
git add site-code/runtime-tests/event-participation-qa.php
git commit -m "test: add event participation runtime QA"
```

Do not execute runtime QA yet if source/runtime deployment is not ready.

---

# Task 8: Pre-Merge Runtime Source Wiring

This task changes runtime state outside Git and must be carefully recorded.

## Stable source

Expected current active Local JSON source:

```text
/home/jcschoeman96/projects/current/vgnova/site-code/acf-json
```

Active Bricks Child link:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json
```

Feature source:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/<TASK-6A-WORKTREE>/site-code/acf-json
```

- [ ] **Step 1: Verify main remains clean and at expected merged baseline**

Do not use main for feature edits.

- [ ] **Step 2: Compare the pre-existing six canonical JSON definitions**

Before repointing, require exact byte/hash equality between stable main and feature worktree for the six pre-6A definitions:

```text
group_vg_editorial_classification.json
group_vg_person_details.json
post_type_person.json
taxonomy_editorial_collection.json
taxonomy_editorial_format.json
taxonomy_person_type.json
```

Stop on discrepancy.

- [ ] **Step 3: Verify feature source contains exactly nine approved JSON definitions**

Six previous + three Task 6A.

- [ ] **Step 4: Verify active `acf-json` path is a symlink to stable main**

If it is not exactly the expected stable source:

```text
STOP
```

- [ ] **Step 5: Repoint symlink to Task 6A worktree source**

This is a temporary **pre-merge review/runtime dependency**.

Do not copy JSON into the theme.

- [ ] **Step 6: Verify resolved symlink target**

Record exact before/after targets.

- [ ] **Step 7: Do not remove worktree after this point**

The runtime now depends on it until post-merge Task 6A-S.

---

# Task 9: Deploy Platform Core Runtime Copy

Runtime plugin:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core
```

- [ ] **Step 1: Snapshot runtime files before deployment**

At minimum hash:

```text
voelgoed-platform-core.php
includes/Plugin.php
```

and confirm new Event files do not already contain divergent runtime-only architecture.

- [ ] **Step 2: Deploy source Event classes**

Copy the three source-controlled Event classes into the corresponding runtime plugin `includes/Event/`.

- [ ] **Step 3: Apply the source-controlled integration patch semantically**

Runtime bootstrap must load/register exactly what `integration.patch` records.

Do not introduce runtime-only unique code.

- [ ] **Step 4: PHP lint deployed files**

- [ ] **Step 5: Verify plugin loads without fatal errors**

Use read-only WP-CLI bootstrap command.

If plugin bootstrap fails:

```text
STOP
```

and restore runtime files from the snapshot before further action.

---

# Task 10: Targeted ACF Sync and Role Seed

- [ ] **Step 1: Read ACF JSON status**

Check exactly:

```text
post_type_event_participation
taxonomy_event_role
group_vg_event_participation_details
```

Record any unrelated pending definitions separately.

Member Perk definitions remain untouched.

- [ ] **Step 2: Targeted sync only the three Task 6A definitions**

No unrestricted sync.

- [ ] **Step 3: Verify ACF registration**

Assert:

```text
event_participation registered
public false
publicly_queryable false
show_in_rest true
archive false
rewrite false

event_role attached only event_participation
```

- [ ] **Step 4: Seed Event roles once**

Run:

```bash
wp eval-file <feature-worktree>/site-code/event-participation/seed-event-role-terms.php
```

Expected created:

```text
spreker
paneellid
fasiliteerder
gas
```

- [ ] **Step 5: Rerun seeder**

Expected:

```text
created: none
existing: all four
```

- [ ] **Step 6: Verify exact vocabulary**

Exactly four terms.

Zero participation relationships.

- [ ] **Step 7: Source/runtime parity**

Prove exact meaningful configuration parity for all three Task 6A ACF definitions.

Ignore only documented generated runtime metadata.

- [ ] **Step 8: ACF status**

All three Task 6A keys:

```text
IN_SYNC
```

---

# Task 11: Execute Runtime QA

- [ ] **Step 1: Run QA**

Use the deployed Platform Core runtime and feature-worktree QA script.

- [ ] **Step 2: Require every QA assertion to pass**

Do not manually repair failed assertions and continue.

- [ ] **Step 3: Confirm hard cleanup**

Final:

```text
Person = 0
event_participation = 0
Person person_type relationships = 0
Person content_topic relationships = 0
event_participation event_role relationships = 0
```

Persistent Event roles remain exactly four.

- [ ] **Step 4: Verify legacy protection**

Require unchanged:

```text
753–757
event 763
event_medewerkers on 763
products 767/768
produk_medewerkers
produk_bron_event
```

- [ ] **Step 5: Record runtime write accounting**

Distinguish:

```text
persistent intended:
3 ACF synced definitions
4 event_role terms
Platform Core deployed source files/bootstrap integration

temporary and cleaned:
1 QA Person
1 QA participation
QA metadata/roles
```

Legacy content writes:

```text
0
```

---

# Task 12: Full Regression Gate

- [ ] **Step 1: Event foundation tests**

```bash
php site-code/event-participation/tests/run-event-participation-foundation-tests.php
```

- [ ] **Step 2: Platform Event tests**

```bash
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
```

Record count.

- [ ] **Step 3: Existing Platform Core**

```bash
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
```

Expected:

```text
67
```

- [ ] **Step 4: Person foundation**

```bash
php site-code/person-foundation/tests/run-person-foundation-tests.php
```

Expected:

```text
10
```

- [ ] **Step 5: Editorial dimensions**

```bash
php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
```

Expected:

```text
11
```

- [ ] **Step 6: PHP lint**

Lint every new/modified PHP source/test/runtime script.

- [ ] **Step 7: JSON parse**

All nine shared ACF JSON files parse.

- [ ] **Step 8: Git whitespace**

```bash
git diff --check origin/main...HEAD
```

Expected clean.

---

# Task 13: Documentation and Implementation Ledger

**Files:**
- Create:
  - `EVENT_PARTICIPATION_IMPLEMENTATION.md`
  - `POST_TASK_6A_CURRENT_STATE.md`
  - `IMPLEMENTATION_LEDGER.md`
- Update:
  - Person migration architecture
  - consumer inventory
  - decommission register

- [ ] **Step 1: Write implementation document**

Record:

```text
schema ownership
Platform Core class responsibilities
uniqueness contract
query API
admin title behavior
role governance
runtime deployment
ACF sync procedure
QA procedure
legacy coexistence
consumer migration boundary
temporary pre-merge Local JSON worktree dependency
```

- [ ] **Step 2: Write post-task snapshot**

Actual runtime state only.

Do not describe future consumer migration as implemented.

- [ ] **Step 3: Create concise Implementation Ledger**

Columns:

```text
Task
Purpose
PR
Merge SHA
Runtime impact
Final state
Detailed evidence
```

Backfill every clearly completed architectural task that can be supported by existing Git/docs.

Rules:

```text
one concise row per substantive task
no invented PR
no invented SHA
no invented test count
no review-correction rows unless they represent a real operational phase
Task 5B-S may be represented because it was an actual runtime stabilization gate
```

Task 6A row:

```text
PR: pending until PR exists
Merge SHA: pending
State: REVIEW
```

- [ ] **Step 4: Update consumer inventory**

Mark:

```text
event_participation foundation = AVAILABLE

Event Single 774 = LEGACY CONSUMER
Medewerker reverse-event query = LEGACY CONSUMER
product copy automation = LEGACY CONSUMER
```

Update Task 5C provenance labels for Event 763/Product 767/768 where older inventory still says `PENDING_PROVENANCE`; those known edges are now `EXCLUDE`.

Do not mark the underlying architecture dependencies removed.

- [ ] **Step 5: Update decommission register**

Record only:

```text
canonical event participation foundation now exists
```

Keep removal blocked by:

```text
5 DEFER profiles
legacy consumers
legacy fields
product automation
routing/template dependencies
```

- [ ] **Step 6: Update Person migration architecture**

Reflect Task 6A foundation availability.

Do not claim any Event edge migrated.

- [ ] **Step 7: Validate CSV and Markdown**

Parse all tracked CSVs.

Check local documentation links.

- [ ] **Step 8: Commit**

```bash
git add docs site-code/voelgoed-platform-core/README.md
git commit -m "docs: record event participation foundation state"
```

---

# Task 14: Final Review Gate Before PR

- [ ] **Step 1: Review changed files**

There must be no changes to:

```text
Bricks
FluentSnippets
product fields
legacy event fields
legacy profile schema
Person schema
content_topic implementation except test/bootstrap integration if actually required
```

- [ ] **Step 2: Search prohibited migrations**

Require no code that automatically converts:

```text
event_medewerkers → event_participation
medewerker → person
produk_medewerkers → person
```

- [ ] **Step 3: Search seeded role vocabulary**

Only:

```text
Spreker
Paneellid
Fasiliteerder
Gas
```

- [ ] **Step 4: Confirm persistent runtime state**

```text
Person: 0
event_participation: 0
event_role: 4
```

- [ ] **Step 5: Confirm temporary ACF symlink dependency**

The active ACF JSON path may now resolve to the Task 6A worktree for review.

Record:

```text
temporary dependency = YES
post-merge Task 6A-S required = YES
```

Do not remove the worktree.

- [ ] **Step 6: Confirm branch clean**

```bash
git status --short
```

Expected empty.

---

# Task 15: Push and Open PR

- [ ] **Step 1: Push**

```bash
git push -u origin feat/event-participation-6a
```

- [ ] **Step 2: Open PR**

Title:

```text
feat: add canonical event participation foundation
```

Base:

```text
main
```

- [ ] **Step 3: Do not merge**

Stop.

Do not begin:

```text
Task 6A-S
consumer migration
Task 6B
product migration
Bricks migration
```

---

# Required Agent Handoff

## Git

```text
branch:
base:
head:
ahead:
behind:
PR:
mergeable:
worktree:
```

## Changed source

List every new/modified source file.

## ACF

For each:

```text
post_type_event_participation
taxonomy_event_role
group_vg_event_participation_details
```

report:

```text
IN_SYNC / DRIFT
source/runtime parity PASS / FAIL
```

Also report unrelated pending ACF definitions without touching them.

## Runtime ACF source

```text
symlink before:
symlink after:

current temporary source:
feature worktree / other

runtime depends on Task 6A worktree:
YES / NO

post-merge stabilization required:
YES / NO
```

Expected during review:

```text
YES
YES
```

## Event Participation registration

```text
registered:
public:
publicly_queryable:
exclude_from_search:
show_in_rest:
archive:
rewrite:
persistent records:
```

## Event roles

```text
Spreker:
Paneellid:
Fasiliteerder:
Gas:
unexpected:
relationship count:
```

## Canonical query contract

```text
Event → participation:
PASS / FAIL

Event → Person:
PASS / FAIL

Person → participation:
PASS / FAIL

Person → Event:
PASS / FAIL

publish-only:
PASS / FAIL

deterministic order:
PASS / FAIL
```

## Integrity

```text
invalid Event rejected:
invalid Person rejected:
duplicate live pair rejected:
trash duplicate ignored:
multiple roles on one participation:
negative display order rejected:
foreign role term rejected:
partial-write rollback:
derived title:
endpoint-title resync:
```

## Runtime QA

```text
selected real Event:
selected Event had legacy people before QA:
0

QA Person created:
QA participation created:
duplicate attempt rejected:
QA Person hard-deleted:
QA participation hard-deleted:
selected Event unchanged:
```

## Final persistent state

```text
Person:
0

event_participation:
0

event_role:
4

Person person_type relationships:
0

Person content_topic relationships:
0

event_participation role relationships:
0
```

## Legacy protection

```text
profiles 753–757 unchanged:
event 763 unchanged:
event 763 event_medewerkers unchanged:
products 767/768 unchanged:
produk_medewerkers unchanged:
produk_bron_event unchanged:
Bricks changed:
NO
FluentSnippets changed:
NO
```

## Migration counts

```text
legacy profiles migrated:
0

legacy event edges migrated:
0

legacy product edges migrated:
0
```

## Tests

```text
Event Participation foundation:
N/N

Platform Core Event Participation:
N/N

existing Platform Core Content Topic:
67/67

Platform Core combined:
67 + new Event count = N

Person foundation:
10/10

Editorial Dimensions:
11/11

PHP lint:
PASS

ACF JSON parse:
9/9

git diff --check:
PASS
```

## Runtime writes

Distinguish:

```text
persistent ACF definition sync:
3

persistent event_role terms:
4

temporary QA Person:
1 created / 1 hard-deleted

temporary QA participation:
1 created / 1 hard-deleted

legacy WordPress content writes:
0
```

## Implementation ledger

```text
created:
YES

historical rows backed only by verified evidence:
YES

invented historical facts:
NO

Task 6A:
REVIEW / PR pending merge SHA
```

## Required Stop Statement

```text
Task 6A complete.

The canonical Event Participation foundation is active.

Tickera tc_events remains the canonical Event source.

event_participation now provides the canonical Event ↔ Person relationship model with contextual event_role assignments and deterministic display order.

Exactly four canonical contextual roles exist: Spreker, Paneellid, Fasiliteerder, and Gas.

No legacy medewerker profile, event_medewerkers relationship, product relationship, Bricks consumer, or FluentSnippet was migrated or modified.

The known Event 763 relationships remain excluded and unchanged.

Runtime QA used only uniquely owned temporary records and cleaned them completely.

Persistent Person records remain zero.

Persistent Event Participation records remain zero.

The implementation ledger now provides a concise project history while the detailed architecture, current-state snapshots, manifests, and decommission register remain the authoritative evidence.

The active pre-merge ACF Local JSON source temporarily depends on the Task 6A feature worktree and must be stabilized onto main after merge before that worktree is removed.

Consumer migration has not started.

Task 6A-S has not started.
```

Stop with the PR open and unmerged.