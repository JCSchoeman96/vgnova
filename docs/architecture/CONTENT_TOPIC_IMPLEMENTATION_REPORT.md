# Content topic implementation report

Task: Voelgoed Architecture Correction, Task 1.

Status: Complete. Stopped before legacy taxonomy migration.

Branch: `feat/canonical-content-topic`

## A. Implementation

The existing Voelgoed Platform Core plugin owns the registration and setup logic. Its module registers `content_topic` on `init` at priority 100, after the platform post types are available. The taxonomy is hierarchical, public, publicly queryable, visible in admin and REST, available in navigation menus, and queryable. Its labels are `Onderwerpe` and `Onderwerp`.

The rewrite prefix is `onderwerp`, `with_front` is disabled, and hierarchical paths are enabled. The local site loaded `/onderwerp/nuwe-jy/` and `/onderwerp/nuwe-jy/skoonheid-versorging/velsorg/`.

The explicit `wp vg-content-topic-setup` command seeds the terms. It finds terms by taxonomy and slug, resolves parents by slug, and stops on conflicts without changing existing terms. It creates terms with empty descriptions and checks the final seven-root and 56-term totals. A successful setup flushes rewrite rules once. The option `vgpc_content_topic_rewrite_flushed_v1` records that flush. A second run created no terms and did not flush rewrites.

The active Platform Core installation is not a Git checkout. This repository tracks the module source and its two-line bootstrap patch under `site-code/voelgoed-platform-core/`. The patch loads the module and registers it as a Platform Core component. I applied the patch to the active local plugin after inspecting it with Novamira.

### Files changed

- `site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php`
- `site-code/voelgoed-platform-core/integration.patch`
- `site-code/voelgoed-platform-core/README.md`
- `site-code/voelgoed-platform-core/tests/bootstrap.php`
- `site-code/voelgoed-platform-core/tests/ContentTopicTaxonomyTest.php`
- `site-code/voelgoed-platform-core/tests/run-content-topic-tests.php`
- `docs/architecture/CONTENT_TOPIC_IMPLEMENTATION_REPORT.md`

## B. Result

The taxonomy has 7 roots and 56 terms. Its roots are Nuwe Jy, Geloof, Self, Werk + Geld, Ouerskap, Huwelik, and Mensstories.

It is attached to `post`, `mto-course`, `product`, `tc_events`, `medewerker`, `uitsending`, and `kompetisie`.

`Leefstyl` is absent from `content_topic` and remains in `content_category`. All 56 new terms have empty descriptions and zero assigned objects. This task added no Primary Topic or Secondary Topic mechanism.

The existing `content_category` taxonomy remains registered on `post`, `product`, `tc_events`, `mto-course`, `medewerker`, `uitsending`, `kompetisie`, `kompetisie_entry`, `testimonial`, and `team_member`.

## C. Verification

| Check | Result |
|---|---|
| `php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php` | Passed: 9 tests for definitions, registration, repeat seeding, one-time rewrite flushing, conflicts, parent links, suffix cleanup, Leefstyl absence, and legacy relationship preservation. |
| PHP lint across the tracked Platform Core PHP files | Passed with no syntax errors. |
| Patch dry-run with `--fuzz=0` against pre-change Platform Core bootstrap files, plus reverse dry-run against the active plugin | Passed. Both bootstrap changes apply cleanly and match the installed integration. |
| `php tests/run-dashboard-tests.php` from the active Platform Core plugin directory | Passed: 127 existing Platform Core tests. |
| `novamira --site vgnova doctor --json` | REST and authentication checks passed. Novamira reported that credentials use an owner-only file instead of an OS credential service. |
| First `wp vg-content-topic-setup` run | Passed: 7 roots, 56 terms; 53 created, 3 already present; rewrite rules flushed once. |
| Second `wp vg-content-topic-setup` run | Passed: 7 roots, 56 terms; 0 created, 56 reused; no rewrite flush. |
| Live WP-CLI taxonomy assertion | Passed: required flags, exact object types, 7 roots, 56 terms, no name or parent mismatches, no Leefstyl, empty descriptions, and no topic assignments. |
| `GET /wp-json/wp/v2/taxonomies/content_topic` | Returned HTTP 200. The REST response reports `Onderwerpe`, `content_topic`, hierarchical true, and the seven approved object types. |
| Admin taxonomy list and editor screens | The `Onderwerpe` list displays `Voeding & gewig` with a literal ampersand. The taxonomy UI appeared on post, course, product, event, medewerker, and broadcast edit screens. |
| Published sample content | The site loaded a post, course, product, event, medewerker, and broadcast. Published counts are 7 posts, 5 courses, 10 products, 17 events, 5 medewerkers, and 5 broadcasts. |
| Topic routes | The root and nested routes loaded under `/onderwerp/`. `Aftrede` and `Besigheid & entrepreneurskap` links also use the `/onderwerp/` prefix. |
| Competition editor | No existing `kompetisie` record was present. The new editor showed `Onderwerpe`. WordPress created an auto-draft when the editor opened; I deleted that draft, ID 1215. The final database query found zero `kompetisie` records. |

The first seed attempt stopped at `Voeding & gewig`. WordPress stored ampersands as `&amp;` in the database, so the first safety check treated the stored name as a conflict. The validator now compares against `sanitize_term_field(..., 'db')`. WordPress displays the expected literal ampersands in admin. The successful run reused the three terms left by the first attempt and created the other 53.

## D. Existing-data impact

Legacy relationships changed: No.

Existing `content_category` terms changed: No.

Existing post assignments changed: No.

The before and after snapshots both contain the same 13 `content_category` slugs and relationships. The non-empty relationships remain:

- `geloof` has post 516
- `mensstories` has post 521
- `natuurlik-gesond` has post 523
- `nuwe-jy` has posts 514 and 523
- `ouerskap` has post 518
- `self` has post 641
- `werk-geld` has post 519

The other six legacy terms remain empty. The old taxonomy's object types are unchanged. No relationship was copied to `content_topic`, and no test topic assignment remains.

## E. Deviations

- The active Platform Core directory is not a Git checkout, and the parent repository holds the staging documentation. This branch tracks the module and exact bootstrap patch in `site-code/voelgoed-platform-core/`. The architecture owner should confirm whether a separate Platform Core source repository must also receive this change.
- The WP-CLI command is `wp vg-content-topic-setup`. The initial multi-level name depended on an unregistered parent command, so WP-CLI did not expose it. I changed it to a standalone command before seeding.
- No existing competition record was available to edit. I checked the new editor without saving and removed its temporary auto-draft.

## F. Risks and blockers

- No implementation blocker remains for Task 1. Confirm the authoritative Platform Core repository before later work if it is separate from `vgnova`.
- `kompetisie` has no published or draft records, so the site had no existing competition object to open. The new editor showed `Onderwerpe`, and no test record remains.
- Task 2 and later migration work have not started. `content_category` remains in place and operates independently from `content_topic`.
