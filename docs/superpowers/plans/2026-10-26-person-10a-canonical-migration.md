# Task 10A / Person 5D canonical migration implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task by task.

**Goal:** Create one canonical Person for owner-approved legacy profile 753, classify it as Mentor and Kenner, preserve all placeholder content and legacy relationships, and retain the four owner-confirmed fake profiles without deleting them.

**Architecture:** Expand the exact source-controlled `person_type` vocabulary with strict, idempotent seeding. Add a CSV authority artifact and a deterministic WP migration entry point that accepts only legacy ID 753, writes only identity and the two approved taxonomy relationships, verifies the new record, and removes only its exact owned target on post-insert failure. Keep `medewerker`, all legacy edges, Bricks consumers, snippets, routes, and Platform Core deployment unchanged.

**Tech Stack:** PHP 8.5, WordPress APIs, ACF-owned `person_type` taxonomy, PHP source regression runners, CSV and JSON evidence.

---

## Repository files

Create or modify only these files unless a verified source dependency requires a narrowly scoped addition:

- Modify `site-code/person-foundation/seed-person-type-terms.php` and `site-code/person-foundation/tests/run-person-foundation-tests.php` for the exact Mentor/Kenner vocabulary.
- Create `site-code/person-foundation/migrate-approved-person-profiles.php` and `site-code/person-foundation/tests/run-person-profile-migration-tests.php` for the CSV-driven migration and its failure cleanup.
- Modify `site-code/runtime-tools/mutating-entrypoints.json` and `site-code/bin/check-source.sh` to govern and run the new mutating entry point.
- Create `docs/architecture/PERSON_PROFILE_OWNER_DECISIONS_v1.0.0.md` and `docs/architecture/PERSON_PROFILE_MIGRATION_AUTHORITY_v1.0.0.csv` as owner-authority evidence.
- Modify `docs/architecture/PERSON_PROFILE_MIGRATION_MANIFEST.csv`, `docs/architecture/PERSON_LEGACY_CLASSIFICATION_MAPPING.csv`, `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`, and `docs/architecture/IMPLEMENTATION_LEDGER.md` as living records.
- Create `docs/architecture/PERSON_MIGRATION_ARCHITECTURE_v1.1.0.md`, `docs/architecture/PERSON_5D_IMPLEMENTATION_v1.0.0.md`, `docs/architecture/POST_TASK_10A_CURRENT_STATE_v1.0.0.md`, and `docs/architecture/PERSON_5D_RUNTIME_MANIFEST_v1.0.0.json`.
- Keep `docs/architecture/PERSON_MIGRATION_ARCHITECTURE.md` and the Task 5C snapshots historical and unchanged.

## Task 1: Extend the Person Type seeder with TDD

**Files:** `site-code/person-foundation/tests/run-person-foundation-tests.php`, `site-code/person-foundation/seed-person-type-terms.php`

- [x] Add failing cases for a clean run creating exactly `mentor` and `kenner`, an exact rerun creating nothing, a Mentor-only runtime creating only Kenner, and an unexpected third term failing before insertion.
- [x] Add failing name/slug conflict cases for both approved terms: each slug paired with the wrong name and each name paired with the wrong slug. Assert that every conflict fails before any insertion or term edit.
- [x] Run `php site-code/person-foundation/tests/run-person-foundation-tests.php` and confirm the new assertions fail because the current implementation approves Mentor only.
- [x] Set the exact ordered allowlist to `['mentor' => 'Mentor', 'kenner' => 'Kenner']`. Generalize the existing name/slug conflict scan over every approved pair, then retain a full preflight pass that rejects all unexpected terms before the insert loop.
- [x] Rerun `php site-code/person-foundation/tests/run-person-foundation-tests.php`; require all old and new tests to pass and confirm taxonomy assignments remain untouched.

## Task 2: Define migration authority and write migration tests first

**Files:** `docs/architecture/PERSON_PROFILE_MIGRATION_AUTHORITY_v1.0.0.csv`, `site-code/person-foundation/tests/run-person-profile-migration-tests.php`

- [x] Write the authority CSV header and five rows. Row 753 must encode legacy identity `753 / medewerker / Lynette Beer / lynette-beer`, decision `MIGRATE`, published target `Lynette Beer / lynette-beer`, `mentor|kenner`, every copy flag `NO`, `EXCLUDE_ALL_CURRENT`, and `OWNER_CONFIRMED_2026-10-25`. Rows 754–757 must encode `DO_NOT_MIGRATE`, blank target IDs, no copy, and the same owner authority.
- [x] Add a standalone migration test runner with an in-memory WordPress store. Define controlled stubs for post reads/inserts/deletes, term reads/assignment, ACF meta reads, thumbnail reads, and relationship-count reads; each write stub must record exact calls and model WordPress return shapes for post reads, metadata maps, and term-taxonomy IDs.
- [x] Add tests proving only a `MIGRATE` row is eligible, `DO_NOT_MIGRATE` and `DEFER` rows create nothing, and the approved authority file contains only one migratable ID, 753.
- [x] Add tests for wrong source ID/type/title/slug/status, changed identity, missing/wrong/unexpected Person Type vocabulary, an existing `lynette-beer` slug, and an exact-title Lynette conflict. Each case must fail before `wp_insert_post`.
- [x] Add success assertions for one published `person`, exact title and slug, empty content and excerpt, no thumbnail, no optional Person ACF values, exact `mentor` and `kenner` relationships, and no copied role/group/topic or Event/Product relationships.
- [x] Add failure-injection tests proving a post-insert term-assignment or verification failure deletes only the newly returned Person ID, confirms post/meta/taxonomy cleanup, and leaves unrelated Person records and legacy records untouched.
- [x] Run `php site-code/person-foundation/tests/run-person-profile-migration-tests.php` and confirm the new preflight cases fail before implementing them.

## Task 3: Implement the bounded, guarded migration

**Files:** `site-code/person-foundation/migrate-approved-person-profiles.php`, `site-code/runtime-tools/mutating-entrypoints.json`, `site-code/bin/check-source.sh`

- [x] Implement strict CSV loading with exact header validation, duplicate-ID rejection, explicit decision parsing, and an exact eligible-ID check of `[753]`. Do not search by name or enumerate `medewerker` candidates.
- [x] Before writes, verify the source ID, `medewerker` post type, approved source title/slug/status, absence of a conflicting canonical Person, and exact `person_type` name/slug vocabulary.
- [x] Insert only `post_type=person`, `post_status=publish`, title `Lynette Beer`, slug `lynette-beer`, empty content and excerpt. Do not pass metadata, thumbnail, ACF values, taxonomy assignments from the source, or relationship data to `wp_insert_post`.
- [x] Store the returned new Person ID immediately as the only cleanup owner. Assign exactly the approved Person Type slugs with replacement semantics, then verify the new Person by that ID and verify every empty-content and exact-term invariant.
- [x] If any post-insert step fails, hard-delete only the owned ID, then verify the post, postmeta, and taxonomy relationships are gone. Report cleanup failure separately and never touch the legacy profile or any prior Person.
- [x] Require `vg_runtime_mutation_assert_session('vgnova-local-10039')` before the first persistent write. Register this file as `mutating`, guarded, purpose `migration`, and add the new test runner to `check-source.sh`.
- [x] Run the migration runner, Person Foundation runner, and `php site-code/bin/check-runtime-mutation-policy.php`; fix only failures caused by this task.

## Task 4: Complete owner evidence and current architecture records

**Files:** the authority/evidence and living-record files listed above

- [x] Write owner decision version 1.0.0 with decision date `2026-10-25`, execution baseline `ab7e7d853772fb920237e47d8fa83c72ed389fba`, and separate sections for Person identity authority, content authority, and relationship authority.
- [x] State that 753 is genuine and approved for identity, Mentor, and Kenner only; its photo, bio, excerpt, content, title, profile status, legacy taxonomies, topic/category values, and every relationship remain excluded.
- [x] State that 754–757 are fake test/placeholding records with `DO_NOT_MIGRATE`; keep each legacy record and its consumers intact.
- [x] Create architecture v1.1.0 as a successor that identifies the unversioned architecture and Task 5C as historical evidence. Record dual-run, no deletion, no relationship migration, and no Template 772 cutover.
- [x] Update the living migration manifest without deleting columns or historical facts. For 753 use owner-approved identity/classification, excluded content/edges, `MIGRATE`, `HIGH`, target ID after runtime creation, `lynette-beer`, and `MIGRATED`. For 754–757 use `DO_NOT_MIGRATE`, `HIGH`, blank target fields, and `EXCLUDED`.
- [x] Update only the Kenner classification row to record owner-confirmed durable Person Type authority. Keep contextual/editorial roles and legacy groups separate.
- [x] Update the decommission register and Implementation Ledger to record one canonical Person, four retained fake placeholders, active legacy consumers, no merge, and the open/unmerged review state.

## Task 5: Reconfirm runtime access and capture the protected pre-state

**Runtime:** `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`, `http://localhost:10039`, database `local@localhost`

- [x] Use a VGNova-targeted Novamira MCP connection if available. Otherwise use the authorized local WP-CLI/shell fallback. Do not call or inspect the DanOptics connector or port `10049` again. On 2026-10-26 the VGNova Novamira MCP connection positively returned the expected ABSPATH, `http://localhost:10039`, database `local`, and host `localhost`; the authenticated CLI profile is `localhost`.
- [x] Before any write, positively verify ABSPATH, home/siteurl, database name and host; read profiles 753–757; compute the five Task 5C-normalized fingerprints while excluding exactly `_edit_lock` and `_edit_last`; and read the known Event/Product relationship values.
- [x] Verify canonical Person count 0, Event Participation count 0, Product Contribution count 0, Mentor present, Kenner absent, zero Person Type relationships, and no conflicting canonical Lynette Person.
- [x] Capture protected pre-state for Events 763, Products 765/767/768, Bricks templates 772/773/774/124/1127, relevant components, FluentSnippets, and Platform Core integrity. Compare the five profile and relationship facts to Task 5C. If access fails or any material source state differs, set `TASK_10A=BLOCKED` and perform no runtime writes.

## Task 6: Run the authorized writes under the mutation coordinator

- [x] Run the guarded Person Type seeder through `site-code/bin/vgnova-runtime-mutate.php`. Require the first result to create only Kenner while recognizing Mentor.
- [x] Rerun the seeder through a new coordinator session. Require no creation and both terms recognized.
- [x] Run the reviewed migration through a separate coordinator session. Require exactly one new Person for source ID 753 and record its exact returned ID.
- [x] If migration fails after insertion, verify that cleanup removed only that exact new Person. If the entire run must be aborted, delete only Kenner when this run created it, no prior provenance existed, and no relationship remains.

## Task 7: Verify exact runtime and route state

- [x] Verify the new Person by its exact returned ID: published, exact title/slug, empty excerpt/content, no thumbnail, empty optional ACF fields, exactly Mentor and Kenner, no `content_topic`, and no Event Participation or Product Contribution.
- [x] Verify profiles 753–757 and their post/meta/taxonomy fingerprints are unchanged. Verify Event 763 and Product 767/768 legacy relationships are unchanged and Product 765 remains unchanged.
- [x] Verify `/mense/`, `/mense/lynette-beer/`, and `/medewerkers/lynette-beer/` resolve. Confirm no redirect or canonical/robots change.
- [x] Verify the protected Bricks templates/components, FluentSnippets, unrelated ACF definitions, Member Perks definitions, and the 54-file Platform Core runtime remain unchanged.
- [x] Record exact pre/post counts, term and Person IDs, route results, fingerprints, and any unexpected drift in `PERSON_5D_RUNTIME_MANIFEST_v1.0.0.json`.

## Task 8: Write implementation/current-state evidence and run final gates

- [x] Write the implementation and post-Task-10A current-state documents from the verified result. Keep Task 5C and older snapshots intact.
- [x] Update the manifest with the exact target Person ID and `MIGRATED` state only after runtime verification succeeds. Leave the run `BLOCKED` and avoid claiming a migration if runtime access remains unavailable.
- [ ] Run the full `bash site-code/bin/check-source.sh` on the final branch head. Explicitly run Person Foundation, Person migration, Product Contribution, Product People, Event Participation, all relevant Bricks adapter suites, PE-005 assembly/integrity checks, PHP lint on changed PHP files, JSON parsing, CSV checks, and `git diff --check`.
- [ ] Confirm the final protected-state comparison, changed-file list, and exact test counts. Stage only reviewed task files and commit in reviewable groups.

## Task 9: Open an unmerged review PR

- [ ] Push `feat/person-5d-lynette-migration` and open a PR against `main` with the exact execution baseline, five owner decisions, source changes, runtime writes/IDs, route results, fresh source counts, protected-state result, unchanged legacy edges/profiles/templates/snippets/Platform Core, and no redirects.
- [ ] Register the PR with the T3 thread immediately after creation, confirm it remains open and unmerged, request an independent code/programme review, and stop before merge or Task 10B.

## Plan self-review

- The owner-approved scope is preserved: identity and two durable Person Types only. Content, relationships, consumers, deletion, redirects, and Platform Core deployment remain outside the migration.
- Task 5C remains historical and correct for the evidence available at that time. New owner authority is recorded as a later decision.
- The earlier access blocker is resolved. VGNova Novamira MCP positively identified the expected `:10039` runtime; local WP-CLI used the Local MySQL socket override. The guarded seed created only Kenner term 419, the exact rerun created nothing, and the migration created only Person 1705 for source 753. No post-insert failure occurred; cleanup remained available for the exact owned ID and is covered by failure-injection tests. Runtime protected-state checks passed. The branch remains unmerged; PR creation and independent review are pending.
