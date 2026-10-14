# Content topic implementation report

Tasks: Voelgoed Architecture Correction, Task 1 and Task 1A.

Status: Task 1A complete. Task 2 has not started. No legacy migration or cleanup has been performed.

Functional Task 1 implementation: UNCHANGED.

Site checked: vgnova at http://localhost:10039/, WordPress 7.1.3.

## A. Task 1 implementation

Voelgoed Platform Core owns the registration and setup logic for content_topic. The active local plugin directory is a runtime copy, not a Git checkout.

Canonical source in the vgnova repository:

    site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php

The tracked integration.patch adds the module to the plugin bootstrap and registers it as a Platform Core component:

    site-code/voelgoed-platform-core/integration.patch

The taxonomy registers during init at priority 100, after the platform post types. It is hierarchical, public, publicly queryable, visible in admin and REST, available in navigation menus, and queryable. Its labels are Onderwerpe and Onderwerp.

The temporary rewrite prefix is /onderwerp/. Hierarchical term paths are enabled, with_front is false, and no root-level topic routes were added.

The explicit WP-CLI command wp vg-content-topic-setup seeds terms. It looks up terms by taxonomy and slug, resolves parents by known slug, and stops on conflicting names, slugs, or parents. It leaves descriptions empty and checks for exactly seven roots and 56 terms. A successful setup flushes rewrites once. The option vgpc_content_topic_rewrite_flushed_v1 records the flush. A repeated setup does not flush rewrites again.

The runtime taxonomy module matches the tracked source byte for byte. Both have SHA-256 24aeeb08858810ea93fecb511c6cd2b86651805af3916497196d48232f0a8964. The active plugin bootstrap contains both additions recorded by integration.patch.

Task 1 source files:

- site-code/voelgoed-platform-core/includes/Taxonomy/ContentTopicTaxonomy.php
- site-code/voelgoed-platform-core/integration.patch
- site-code/voelgoed-platform-core/README.md
- site-code/voelgoed-platform-core/tests/bootstrap.php
- site-code/voelgoed-platform-core/tests/ContentTopicTaxonomyTest.php
- site-code/voelgoed-platform-core/tests/run-content-topic-tests.php

Task 1A documentation files:

- AGENTS.md
- site-code/voelgoed-platform-core/README.md
- docs/architecture/CONTENT_TOPIC_IMPLEMENTATION_REPORT.md
- docs/architecture/POST_TASK_1_CURRENT_STATE.md

No functional PHP, database, taxonomy, template, relationship, or URL changes were made in Task 1A.

## B. Seeding provenance

The final content_topic state has 56 canonical terms. Three terms existed before the successful setup run:

| Name | Slug | Parent |
|---|---|---|
| Nuwe Jy | nuwe-jy | none |
| Gesondheid | gesondheid | nuwe-jy |
| Vrouegesondheid | vrouegesondheid | gesondheid |

Verified from the Task 1 run record: an earlier seed attempt stopped while checking Voeding & gewig because WordPress stored the ampersand-encoded name differently than the seeder expected. The successful setup output reported 53 terms created and three already present. The second setup output reported zero created and 56 reused.

Inferred from the locked seed order and the recorded failure point: the three terms listed above were created before the run stopped at Voeding & gewig. Current WordPress state confirms their names, slugs, and parents match the locked definitions. The Task 1 report records that the successful setup reused these three terms from the earlier attempt.

WordPress does not store a term creation timestamp. The exact time of the first attempt cannot be recovered from the current database.

| Provenance check | Result |
|---|---:|
| Canonical content_topic term count | 56 |
| Pre-existing canonical terms before successful run | 3 |
| Terms created by successful recorded run | 53 |
| Terms created by second idempotency run | 0 |
| Duplicate terms created | 0 |
| Unexpected -2 slugs | 0 |
| Parent conflicts | 0 |

The seeder reused the three valid terms instead of creating duplicates. No term was deleted or recreated in Task 1A.

## C. Platform Core source of truth

For the current project phase:

| Role | Location |
|---|---|
| Canonical repository | vgnova |
| Canonical Platform Core source | site-code/voelgoed-platform-core/ |
| Runtime plugin | /wp-content/plugins/voelgoed-platform-core/ |
| Integration record | site-code/voelgoed-platform-core/integration.patch |

The active WordPress plugin directory is a deployment and integration target. It is not a second source of truth. The integration patch records how to apply the tracked module to the plugin bootstrap.

Project rule: Any Platform Core code change must first exist in the source-controlled vgnova/site-code/voelgoed-platform-core/ module. The active local WordPress plugin directory must not contain unique architecture changes that are absent from the repository.

The local repository and the connected GitHub account's repository listing showed no separate Voelgoed Platform Core repository. A repository outside that inspected account or its visibility was not checked. If one is found later, the architecture owner can decide whether to move or mirror the source. No new repository was created.

At the start of Task 1A, branch feat/canonical-content-topic pointed to Task 1 commit 32525e49bdc6d4797e75f29da1d08340d19500c0. The branch had no remote tracking branch, and git ls-remote found no matching remote branch. It was local only. The Task 1 worktree was clean before the Task 1A documentation edits.

## D. Current taxonomy state

### Canonical taxonomy

| Property | Current state |
|---|---|
| Taxonomy | content_topic |
| Owner | Voelgoed Platform Core |
| Terms | 56 |
| Roots | 7 |
| Assignments | 0 |
| Public route prefix | /onderwerp/ |

The seven roots are Nuwe Jy, Geloof, Self, Werk + Geld, Ouerskap, Huwelik, and Mensstories. The full hierarchy appears in POST_TASK_1_CURRENT_STATE.md.

It is attached to exactly post, mto-course, product, tc_events, medewerker, uitsending, and kompetisie. It is not attached to page, team_member, testimonial, kompetisie_entry, vg_perk, tc_tickets, tc_orders, or product_variation.

Leefstyl does not exist in content_topic. There are no Primary Topic or Secondary Topic fields, no editorial_collection or editorial_format taxonomies, and no content_topic assignments.

### Legacy taxonomy

content_category remains live and independent from content_topic.

| Property | Current state |
|---|---|
| Legacy terms | 13 |
| Leefstyl in content_category | Yes |
| Legacy assignments migrated | No |
| Legacy cleanup started | No |
| content_topic assignments | None |

The legacy taxonomy still has its existing 13 terms, relationships, and attachments. Current attachments are post, product, tc_events, mto-course, medewerker, uitsending, kompetisie, kompetisie_entry, testimonial, and team_member.

The current database has eight content_category term relationships across seven objects. They match the Task 1 snapshot:

- geloof: post 516
- mensstories: post 521
- natuurlik-gesond: product 523
- nuwe-jy: post 514 and product 523
- ouerskap: post 518
- self: post 641
- werk-geld: post 519

No content_category term or assignment changed. No custom synchronization exists between content_category and content_topic. The active HappyFiles snippet syncs content_category with hf_cat_post only.

## E. Known legacy dependencies

These systems still use content_category and were not changed:

- Featured Content Manager, with eight legacy pillar fields, including Featured Leefstyl.
- Kategorie Vennoot Mapping, an ACF term field named linked_vennoot_kategorie on content_category.
- Vennoot block selection, which reads a manual partner override, direct vennoot_kategorie assignments, or the mapped content_category term.
- HappyFiles synchronization between content_category and hf_cat_post.
- Bricks category/archive views, conditions, and filters where they use content_category.
- Existing attachments to kompetisie_entry, testimonial, and team_member.
- FluentSnippets helpers that read content_category for article display or partner selection.

The live ACF schema confirms that Featured Content Manager has eight Post Object fields, including featured_leefstyl. An earlier local inventory described seven fields; that count was stale.

The live FluentSnippets directory contains nine PHP snippet files. The HappyFiles sync snippet references content_category and hf_cat_post. None of the nine files references content_topic.

## F. Verification

### Task 1A checks

| Command or check | Result |
|---|---|
| novamira --site vgnova doctor --json | WordPress 7.1.3, Novamira 1.12.5, authentication, REST, and Ability checks passed. The CLI reported its owner-only local credential-file fallback. |
| Read-only WordPress taxonomy snapshot through novamira/run-wp-cli | Passed. content_topic has 56 terms, seven roots, the exact expected object types, and zero relationships. content_category has 13 terms and eight relationships. |
| Parent-chain checks | Passed for Nuwe Jy → Skoonheid & versorging → Velsorg; Werk + Geld → Geld → Aftrede; and Werk + Geld → Werk → Besigheid & entrepreneurskap. |
| Legacy snapshot comparison | Passed. All 13 term slugs, eight relationships, and ten attached object types match the Task 1 report. |
| Unsupported-type checks | Passed. content_topic is absent from page, team_member, testimonial, kompetisie_entry, vg_perk, tc_tickets, tc_orders, and product_variation. |
| Missing future-model checks | Passed. No editorial_collection, editorial_format, or event_participation taxonomy is registered. No podcast, podcast_episode, broadcast, or person post type is registered. No ACF field name containing topic or onderwerp was found. |
| ACF field group list and schemas | Passed. Featured Content Manager has eight fields including featured_leefstyl. Event Medewerkers has one multi-value relationship field targeting medewerker. Kategorie Vennoot Mapping targets content_category terms. |
| FluentSnippets source scan | Passed. Nine stored PHP snippet files were inspected. No file references content_topic. The HappyFiles synchronizer uses content_category and hf_cat_post. |
| Runtime/source comparison | Passed. The active runtime ContentTopicTaxonomy.php is byte-identical to the tracked module. The runtime bootstrap contains both additions from integration.patch. |
| git ls-remote origin refs/heads/feat/canonical-content-topic | No remote branch found. The Task 1 branch was not pushed. |

### Automated tests rerun after the documentation edits

| Command | Result |
|---|---|
| php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php | Passed: 9 focused content topic tests. |
| php /home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core/tests/run-dashboard-tests.php | Passed: 127 Platform Core tests. |
| PHP lint over site-code/voelgoed-platform-core PHP files | Passed with no syntax errors. |

Task 1 also verified the REST taxonomy endpoint, editor taxonomy controls, representative content loads, and temporary /onderwerp/ routes. The temporary competition auto-draft created by opening the editor was removed during Task 1; the live competition CPT still has zero records.

## G. Existing-data impact

| Check | Result |
|---|---|
| Legacy relationships changed | NO |
| Existing content_category terms changed | NO |
| Existing post assignments changed | NO |
| content_topic assignments created | NO |
| Task 2 migration started | NO |

Task 1A WordPress checks were read-only. The only Task 1A changes are documentation and the source-of-truth rule.

## H. Findings and deviations

No deviation from the locked content_topic structure or object-type list was found.

The 53/56 count is explained by three canonical terms left by the earlier failed seed attempt. The current ACF schema also confirms that Featured Leefstyl exists, correcting the older local inventory that said it was missing.

The exact creation timestamp for the first three terms is not available because WordPress does not store one.

## I. Risks and blockers

No Task 1 implementation blocker remains. Before Task 2, use this report and POST_TASK_1_CURRENT_STATE.md as the current baseline. The local feature branch has not been pushed, so the committed source and documentation are not yet visible in the remote repository.

Task 2 and all later migration work remain out of scope.
