# Canonical Person foundation implementation plan

> **For agentic workers:** Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task by task. Steps use checkbox syntax for tracking.

**Goal:** Register the ACF-owned `person` model at `/mense/`, keep the existing profile model intact, and extend Platform Core topic support for Person expertise.

**Architecture:** ACF Local JSON owns `person`, `person_type`, and the Person details field group in a new shared `site-code/acf-json/` directory. Platform Core adds Person to the existing `content_topic` object allowlist while retaining the existing non-primary policy. A one-shot term seeder and temporary runtime QA verify the model without creating lasting Person data or changing legacy relationships.

**Tech stack:** WordPress 7.1, ACF Pro 6.8, PHP 8.5, WordPress taxonomy APIs, Platform Core standalone PHP tests, and Novamira for the local runtime.

---

## File map

- Move the two editorial taxonomy JSON files and editorial field group from `site-code/editorial-dimensions/acf-json/` to `site-code/acf-json/` without changing their contents.
- Add `post_type_person.json`, `taxonomy_person_type.json`, and `group_vg_person_details.json` under `site-code/acf-json/`.
- Update `site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php` to read shared JSON and allow only the three editorial plus three approved Person definitions.
- Update `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php` and its tests to attach `content_topic` to `person`.
- Update `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicRoles.php` only to make its existing Person expertise policy current; keep the Primary policy disabled.
- Add a small Person term seeder and standalone tests under `site-code/person-foundation/`.
- Add a controlled runtime QA script under `site-code/runtime-tests/` that snapshots and compares legacy profile, event, and product state, creates one uniquely marked Person, verifies the contract, and permanently removes the Person in `finally`.
- Write `docs/architecture/PERSON_FOUNDATION_IMPLEMENTATION.md` and `docs/architecture/POST_TASK_5B_CURRENT_STATE.md`. Update the Person section of `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md` only to record the implemented foundation and preserve every legacy removal gate.

## Task 1: Extend Platform Core tests for Person topics

**Files:** `site-code/voelgoed-platform-core/tests/ContentTopicTaxonomyTest.php`, `site-code/voelgoed-platform-core/tests/ContentTopicRolesTest.php`.

- [x] Add a Person post fixture with a direct `content_topic` term.
- [x] Assert the object-type list equals its current seven entries plus `person`; keep all existing entries and their order unchanged.
- [x] Assert the Primary field location list excludes Person while preserving the existing `medewerker` policy.
- [x] Assert Person returns its direct topic and effective ancestors, `NOT_APPLICABLE` classification, and a rejected `set_primary_topic()` call with no Primary value stored.
- [x] Run `php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php`; the new assertion failed before the source change, then the complete suite passed after the change.

## Task 2: Implement the Person topic attachment

**Files:** `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php`, `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicRoles.php`.

- [x] Add `person` to `ContentTopicTaxonomy::OBJECT_TYPES` without removing or reordering current values.
- [x] Keep `person` in `get_entity_policy()` with `primary_supported` false and `primary_requirement` `not_applicable`; replace only the stale conditional wording in its policy description.
- [x] Run `php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php`; all 67 tests pass and runtime retains 55 terms and 7 roots.

## Task 3: Move editorial ACF JSON to the shared directory

**Files:** three current JSON files under `site-code/editorial-dimensions/acf-json/`, their new copies under `site-code/acf-json/`, and `site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php`.

- [x] Move the three JSON files with Git and verify each source/destination SHA-256 is identical.
- [x] Change the editorial standalone test to load JSON from `site-code/acf-json/`; preserve its editorial assertions and extend the shared-directory allowlist for the three approved Person definitions.
- [x] Run `php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php`; all 11 tests pass.

## Task 4: Add ACF Person definitions and safe Mentor seeding

**Files:** three Person JSON files under `site-code/acf-json/`; seeder and tests under `site-code/person-foundation/`.

- [x] Define `person` with public querying, REST support, archive and rewrite slug `mense`, `with_front` false, and title/editor/thumbnail/excerpt/revisions support.
- [x] Define flat, admin-visible `person_type` on `person`, with REST support and no public query, archive, or rewrite.
- [x] Define one `post_type == person` field group containing only optional professional title, credentials, company, website, and the `person_type` taxonomy control with `save_terms` and `load_terms` enabled and `add_term` disabled.
- [x] Add source tests for native profile storage, exact fields, field location, taxonomy flags, and taxonomy-control persistence.
- [x] Add an idempotent seeder for only `{name: Mentor, slug: mentor}`. Preflight conflicts before any write, verify stored output, leave exact terms unchanged, and never assign posts.
- [x] Add seeder tests for create, rerun, exact existing term, conflicting slug, conflicting name, unapproved terms, missing taxonomy, and zero relationships.
- [x] Run the new standalone runner (10 tests pass) and validate all six shared ACF JSON documents.

## Task 5: Add controlled runtime QA and protection checks

**Files:** `site-code/runtime-tests/person-foundation-qa.php` and the shared ACF JSON symlink at the active Bricks Child theme path.

- [x] Record legacy profiles 753–757, event 763, and products 767/768 before QA using IDs, status, slugs, modified timestamps, taxonomy terms, and relationship IDs.
- [x] Point the active child theme ACF JSON directory at the shared source directory; compare all three editorial files to baseline and confirm source/runtime parity.
- [x] Run ACF status checks by type, targeted dry runs for each new Person key, and targeted syncs for those keys. No unrestricted ACF sync ran.
- [x] Seed `Mentor` through the source-controlled seeder, verify one term with zero Person relationships, and retain it after cleanup.
- [x] Create a unique `[QA][AUTOMATED]` Person unrelated to any legacy identity. Verify native fields, custom fields, `Mentor`, one known topic and ancestors, `NOT_APPLICABLE`, rejected Primary assignment, and `/mense/` archive/single routing.
- [x] Permanently delete only the owned QA Person. Confirm no Person (including trash), post meta, `person_type` relationship, or `content_topic` relationship remains.
- [x] Compare legacy state before and after cleanup. Runtime retained 55 `content_topic` terms with seven roots and unchanged editorial format/collection counts.

## Task 6: Document the implementation and current runtime state

**Files:** `docs/architecture/PERSON_FOUNDATION_IMPLEMENTATION.md`, `docs/architecture/POST_TASK_5B_CURRENT_STATE.md`, `docs/architecture/LEGACY_DECOMMISSION_REGISTER.md`.

- [x] Document ACF/Platform Core ownership, routes, fields, taxonomy behavior, QA evidence, migration exclusions, and Task 5B non-goals.
- [x] Record final Person, `person_type`, topic, editorial, and legacy protection state from runtime output.
- [x] Update the Person route and affected topic/role register notes without marking any legacy item ready for removal.
- [x] Review `git diff --check` and verify no changes appear in excluded snippets, templates, IDs, legacy records, or topic vocabulary.
