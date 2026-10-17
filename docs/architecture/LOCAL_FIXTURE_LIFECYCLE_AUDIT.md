# Local fixture lifecycle and decommission audit

| Audit detail | Value |
|---|---|
| Date | 2026-10-17 |
| Repository baseline | origin/main at 8796ca0c1467856a61e1d0b5a4c05e21e815a21a (PR #10) |
| Runtime | Local WordPress at http://localhost:10039/ (WordPress 7.1.3; blog_public=1) |
| Scope | Read-only lifecycle and dependency audit of posts 375, 513, 514, 516, 518, 519, 521, 641 and product 523. |

## Decision summary

The runtime objects are not all at the same lifecycle stage. The standard-post corpus is five Task 2 SYSTEM_TEST posts (514, 516, 518, 519, 521), the explicit QA fixture 641, and unresolved placeholder 375; duplicate 513 is already trashed. Product 523 is a separate Task 2 proof product and is not editorial content.

| Recommendation | Objects | Register vocabulary |
|---|---|---|
| READY_TO_REMOVE | 513, 514, 516, 518, 519 | READY_TO_REMOVE |
| DEFER_REMOVAL | 521, 523, 641 | DEFER |
| NEEDS_PROVENANCE | 375 | DEFER until its origin is established |
| RETAIN_FIXTURE | none | — |

A separate Task 4E may remove only 513, 514, 516, 518, and 519. It should preserve every attachment as separate media. It should leave 521 and 523 in place until their Bricks preview targets are replaced, leave 641 until a repeatable Featured Content Manager integration test is available, and leave 375 until its provenance is resolved.

No WordPress object was changed during this audit.

## Method and boundaries

The fresh worktree started at the requested origin/main SHA. The complete required architecture and task history was read before runtime inspection. Repository ID searches covered the parent repository and the pinned voelgoed-member-perks submodule at 972a279be7a753594888e974132562e1cc099e24. The submodule had no references to any target ID. Runtime inspection used the Novamira CLI profile vgnova, which targets localhost:10039. The connected Novamira MCP endpoint reported localhost:10059, so it was not used for findings about this site.

Read-only WP-CLI evaluation inspected post identity, statuses, content, taxonomies, ACF data, revision counts, media, taxonomy ownership, option values, template configuration, and structured inbound references. Local HTTP GETs checked the published URLs and rendered Featured Content Manager output. The Task 2 migration command was run in its default read-only dry-run mode. Source and tests were inspected but not executed. The runtime Platform Core migration, role, and taxonomy implementation files matched their source-controlled module files byte-for-byte.

The connected local site reports blog_public=1. In this report, publicly reachable means served by the local public WordPress front end. The localhost host is not externally reachable from the public internet. Published fixture URLs returned HTTP 200 with self-canonical URLs; their robots metadata had no noindex directive. WordPress title searches returned each published object. No stored post-content, excerpt, postmeta, option, or slug reference was found that links to these objects by URL, except the Bricks template preview references described below.

No WordPress write, migration apply/rollback, ACF sync, post status change, taxonomy change, media change, or content edit was issued. No PHP tests were run. A final read-only re-query matched the initial object statuses and modified timestamps, taxonomy counts and owners, Primary values, Featured Content Manager options, and migration record, checksum, and retired marker.

## Runtime identity and object findings

All target objects have parent 0 except their listed media attachments. Published posts and product 523 are publicly queryable. All published target objects use author JC_Admin. Trashed post 513 now has author ID 0.

| ID | Type / status | Title and slug | Created → modified | Revisions | Action / confidence |
|---:|---|---|---|---:|---|
| 375 | post / publish | Integer maximus sapien in bibendum. — integer-maximus-sapien-in-bibendum | 2026-05-20 14:08:15 → 2026-05-20 15:15:58 | 0 | NEEDS_PROVENANCE / Low |
| 513 | post / trash | Jou oggend-routine vir meer rustigheid — oggend-routine-rustigheid__trashed | 2026-05-27 08:05:46 → 2026-05-27 08:06:35 | 0 | READY_TO_REMOVE / High |
| 514 | post / publish | Jou oggend-routine vir meer rustigheid — oggend-routine-rustigheid-2 | 2026-05-27 08:05:51 → 2026-05-27 08:05:51 | 0 | READY_TO_REMOVE / High |
| 516 | post / publish | Geloof wat jou werkweek dra — geloof-werkweek | 2026-05-27 08:05:51 → 2026-05-27 08:05:51 | 0 | READY_TO_REMOVE / High |
| 518 | post / publish | Ouerskap sonder perfeksie — ouerskap-sonder-perfeksie | 2026-05-27 08:05:52 → 2026-05-27 08:05:52 | 0 | READY_TO_REMOVE / High |
| 519 | post / publish | Geldwenke vir enkelma's — geldwenke-enkelma | 2026-05-27 08:05:53 → 2026-05-27 08:05:53 | 0 | READY_TO_REMOVE / High |
| 521 | post / publish | Herstel na verlies: 'n mensstory — herstel-na-verlies | 2026-05-27 08:05:54 → 2026-05-27 08:05:54 | 0 | DEFER_REMOVAL / High |
| 523 | product / publish | Voelgoed Welstand Pakket — welstand-pakket | 2026-05-27 08:05:55 → 2026-05-29 10:24:12 | 2 | DEFER_REMOVAL / High |
| 641 | post / publish | QA Self Featured Content Test — qa-self-featured-content-test | 2026-05-27 12:31:43 → 2026-05-29 12:40:52 | 1 | DEFER_REMOVAL / High |

## Public URLs and exposure

The requests below were local HTTP GETs against localhost:10039. Published fixtures returned 200, had self-canonical links, and had no noindex directive. WordPress emitted max-image-preview:large; no X-Robots-Tag header was present. WordPress title search returned each published fixture. The content/excerpt/slug scan found no stored inbound permalink link to these objects. This does not establish external search-engine exposure because this site is on localhost.

| ID | Local public URL | HTTP / indexing | Search and links |
|---:|---|---|---|
| 375 | http://localhost:10039/content/integer-maximus-sapien-in-bibendum/ | 200; self-canonical; no noindex | Included in title search; no stored inbound slug link |
| 513 | http://localhost:10039/?p=513 | 404; trashed | Not in public title search; search returns 514 only |
| 514 | http://localhost:10039/content/oggend-routine-rustigheid-2/ | 200; self-canonical; no noindex | Included in title search; no stored inbound slug link |
| 516 | http://localhost:10039/content/geloof-werkweek/ | 200; self-canonical; no noindex | Included in title search; no stored inbound slug link |
| 518 | http://localhost:10039/content/ouerskap-sonder-perfeksie/ | 200; self-canonical; no noindex | Included in title search; no stored inbound slug link |
| 519 | http://localhost:10039/content/geldwenke-enkelma/ | 200; self-canonical; no noindex | Included in title search; no stored inbound slug link |
| 521 | http://localhost:10039/content/herstel-na-verlies/ | 200; self-canonical; no noindex | Included in title search; Bricks template preview target |
| 523 | http://localhost:10039/winkel/welstand-pakket/ | 200; self-canonical; no noindex | Included in product title search; Bricks template preview target |
| 641 | http://localhost:10039/content/qa-self-featured-content-test/ | 200; self-canonical; no noindex | Included in title search; page renders the currently selected Self product 531 |

### 375 — unresolved placeholder

The title, slug, body, and excerpt are pseudo-Latin/sample-style content. The body repeats the title as headings and uses Lorem-style filler; it has no taxonomy relationships, Primary Topic, ACF relationships, or revision history. No repository source or import record explains its creation. This is strong evidence that it is not genuine editorial content, but the exact import or creation provenance remains unverified. The supported classification is unresolved demo/import placeholder, with NEEDS_PROVENANCE rather than removal readiness.

### 513 — trashed duplicate

Post 513 and published post 514 have byte-identical post content and excerpts (same 102-byte content SHA-256: a2a001c6016bcff5fa81f3f07d2a8a0ab18d0559e16c4f79c1b6b7e18b8de63e). Post 513 was created five seconds before 514 and moved to trash within the same minute. It has no taxonomy or ACF relationship and is absent from the Task 2 migration record. A title search returns 514, not 513; the trashed URL returns HTTP 404. Permanent removal of 513 does not remove 514 or invalidate Task 2 history. Its separate image attachment 569 must remain unless media cleanup is separately approved.

### 514, 516, and 518 — Task 2 SYSTEM_TEST seeds

Each is a short published test article created as part of the Task 2 proof set. Each has one direct legacy category relationship, a mirrored HappyFiles relationship, and one direct content_topic relationship. Each has no Primary Topic and reports MIGRATION_SEED. Their proof role is preserved by the migration record, repository tests, and historical reports. No live source code, ACF post-object field, option, Bricks preview, or content link requires these records.

### 519 — reviewed classification on a Task 2 proof article

Post 519 remains a Task 2 SYSTEM_TEST object, not verified editorial content. It has content_category werk-geld, direct content_topic begroting-geldbestuur, and Primary Topic begroting-geldbestuur (term 383). Its classification is REVIEWED. Effective Topic behavior includes the ancestor werk-geld. The reviewed classification is preserved by Task 3C documentation and the synthetic source test test_519_uses_the_specific_topic_and_infers_its_ancestors. No other object points to its Primary Topic state. Removing 519 would remove only this record's direct topic, Primary value, and effective ancestor contribution; the taxonomy terms and role behavior remain.

### 521 — Bricks editor preview reference

Post 521 is a Task 2 seed with content_category mensstories and direct content_topic mensstories, but no Primary Topic. The active Bricks template Posts (ID 372) stores templatePreviewPostId=521. That is a real editor preview reference. Replace that preview target and verify the template in a separate task before removing 521. This reference is not a content_topic proof requirement.

### 523 — reviewed product proof object

Product 523 is a published Task 2 SYSTEM_TEST product and is not editorial content. It has content_category nuwe-jy and natuurlik-gesond, direct content_topic oefening-fiksheid, and Primary Topic oefening-fiksheid (term 357). Its effective ancestor is nuwe-jy. It also has product_cat uncategorized and product_type simple. Source tests and Task 3C reports preserve its reviewed classification and the commercial-category edge case.

The active Bricks template Article Card (ID 690) stores templatePreviewPostId=523. Replace that preview target and verify the template before removing the product. Removing 523 later would remove its two legacy category relationships, one product category relationship, and one topic relationship; it would not change the canonical taxonomy tree.

### 641 — active Featured Content Manager QA target

Post 641 is explicitly titled and authored as temporary QA content. It has content_category self, mirrored HappyFiles self, direct content_topic self, no Primary Topic, and MIGRATION_SEED state.

The Featured Content Manager does not store 641 as a selected featured object. Its current featured_self option points to published product 531, Slaapmasker Deluxe. The active article dynamic-tag snippet reads an article's content_category term, maps self to the featured_self option, and renders the selected product. Post 641 is currently the only self-category article in the inspected corpus, and its HTTP 200 page renders Slaapmasker Deluxe from product 531. It is therefore the live manual integration target for the current Self-category Featured Content Manager → Bricks path. No source-controlled automated test replaces that end-to-end scenario. Defer removal until a reproducible test strategy exists; this is a dependency on the test scenario, not a direct option reference to object 641.

## Repository and runtime dependency inventory

### Repository references

A whole-repository exact-ID search found these meaningful reference categories:

- Historical documentation: Task 2 reports and mappings, Task 3C classification review, Task 4C corpus audit, and the editorial-format classification manifest. These preserve why the records were created and what their reviewed classifications were.
- Synthetic test fixtures: ContentTopicMigrationTest.php, ContentTopicRolesTest.php, and ContentTopicTaxonomyTest.php construct test data using the IDs. The tests create their own in-memory WordPress fixtures; they do not load local runtime posts.
- Historical migration manifest: ContentTopicMigration.php hardcodes the seven Task 2 objects and their original relationships. The migration is retired. Its default dry-run still compares the runtime against that historical baseline; it is not a normal page-rendering or business-rule dependency.
- No current implementation outside the retired migration manifest hardcodes IDs 375, 513, 514, 516, 518, 519, 521, 523, or 641.

Posts 375 and 513 appear only in Task 4C audit documentation. A historical report mentioning 519 or another proof ID is not an active runtime dependency.

### Runtime inbound and outbound references

The structured inbound scan found two active Bricks template preview values:

- Post 521 → Bricks template Posts, ID 372, templatePreviewPostId=521.
- Product 523 → Bricks template Article Card, ID 690, templatePreviewPostId=523.

The retired migration option vgpc_content_topic_migration_local_v1 retains historical references to 514, 516, 518, 519, 521, 523, and 641. These option rows preserve provenance; they do not require those posts to remain live. The option does not mention 513 or 375.

No ACF relationship or post-object field points to any target object. No post content, excerpt, internal slug link, or ACF option contains a live pointer to these IDs. ACF Primary Topic values on 519 and 523 point to content_topic terms, not to post objects. The current Featured Content Manager targets products 542, 534, and 531; none of its fields points to a target in this audit. Post 641's manual_vennoot ACF field is empty.

The exact scan filtered numeric Bricks array indexes, CSS/cache keys, and term-tree IDs that coincidentally matched post IDs. These are not object references. Attachment parent links and featured-image references are listed separately in the media section.

## Task 2 proof and migration lifecycle

The seven-object Task 2 proof set was 514, 516, 518, 519, 521, 523, and 641. Runtime records are not needed to preserve:

| Proof question | Are live objects required? | Evidence that remains |
|---|---|---|
| Task 2 migration history | No | Original option with source snapshot, relationship rows, fingerprint, and separate retired lifecycle marker |
| Apply or rollback behavior | No; both are lifecycle-guarded | Retired marker and source test coverage |
| Taxonomy registration and seeding | No | ContentTopicTaxonomyTest.php and Platform Core source |
| Primary Topic behavior | No | ContentTopicRolesTest.php synthetic fixtures |
| Derived Secondary and Effective Topic ancestor behavior | No | ContentTopicRolesTest.php, including 519 and 523 synthetic scenarios |
| Legacy mapping evidence | No | Task 2 mapping/report, Task 3C review, and the preserved migration option |

The live default command wp vg-content-topic-migrate --format=json currently reports lifecycle=retired, baseline_matches=true, and no blockers. Source inspection confirms that after proof relationships are removed, the same read-only dry-run will report lifecycle=retired with baseline mismatch/blockers because the original eight legacy relationships and seven objects no longer match. It returns a plan with blockers rather than a lifecycle conflict. Apply and rollback stop at the retired lifecycle guard before inspecting or changing relationships. Repeating retirement returns already-retired. The saved record and retirement marker remain unchanged and valid; the marker validates its own migration ID, reason, status, and timestamp, not the continued existence of fixture posts.

The expected dry-run mismatch is the historical command detecting source drift after its fixtures have been removed. It is not record corruption, and it does not mean the retired migration should be reactivated. Source-controlled tests and reports already preserve the behavior and evidence.

## Legacy category and canonical topic relationships

All 8 current content_category relationships belong to the seven Task 2 proof objects:

| Object | Type | content_category relationships |
|---:|---|---|
| 514 | post | nuwe-jy |
| 516 | post | geloof |
| 518 | post | ouerskap |
| 519 | post | werk-geld |
| 521 | post | mensstories |
| 523 | product | natuurlik-gesond; nuwe-jy |
| 641 | post | self |

All 7 current content_topic relationships also belong to those seven objects:

| Object | Type | Direct content_topic | Primary Topic |
|---:|---|---|---|
| 514 | post | nuwe-jy | none |
| 516 | post | geloof | none |
| 518 | post | ouerskap | none |
| 519 | post | begroting-geldbestuur | begroting-geldbestuur |
| 521 | post | mensstories | none |
| 523 | product | oefening-fiksheid | oefening-fiksheid |
| 641 | post | self | none |

For 514, 516, 518, 521, and 641, the direct topic is a Task 2 seed and no Primary is set. The role helper may return the direct term through its derived non-Primary set, but that does not mean the legacy seed was a reviewed editorial Secondary assignment. Post 519 and product 523 have reviewed Primary classifications.

### Removal simulation

The table applies the READY_TO_REMOVE set cumulatively in ID order. It then models removal of the remaining Task 2 proof objects after their individual gates pass. No terms are deleted in any scenario.

| Scenario | Standard posts (published / trash) | Products (published / total) | content_category (terms / relationships / objects) | content_topic (terms / relationships / objects) | editorial_format (terms / relationships / objects) | editorial_collection (terms / relationships / objects) | product_cat (terms / relationships / objects) |
|---|---:|---:|---:|---:|---:|---:|---:|
| Current | 7 / 1 | 10 / 21 | 13 / 8 / 7 | 55 / 7 / 7 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 17 / 17 |
| After 513 | 7 / 0 | 10 / 21 | 13 / 8 / 7 | 55 / 7 / 7 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 17 / 17 |
| After 514 | 6 / 0 | 10 / 21 | 13 / 7 / 6 | 55 / 6 / 6 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 17 / 17 |
| After 516 | 5 / 0 | 10 / 21 | 13 / 6 / 5 | 55 / 5 / 5 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 17 / 17 |
| After 518 | 4 / 0 | 10 / 21 | 13 / 5 / 4 | 55 / 4 / 4 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 17 / 17 |
| After 519 (READY set) | 3 / 0 | 10 / 21 | 13 / 4 / 3 | 55 / 3 / 3 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 17 / 17 |
| After later removal of 521, 523, and 641 | 1 / 0 | 9 / 20 | 13 / 0 / 0 | 55 / 0 / 0 | 3 / 0 / 0 | 0 / 0 / 0 | 1 / 16 / 16 |

The READY set removes four published posts and one already-trashed duplicate. Post 523 is deferred, so products remain 21 after the READY set. The eventual row assumes 523 is removed only after its preview-reference gate passes.

Even if every proof relationship is eventually removed, the result would be 13 content_category terms with zero relationships and 55 content_topic terms with zero relationships. That does not authorize removal of content_category, its ACF taxonomy definition, templates, routing, partner mappings, Featured Content Manager, or HappyFiles integration.

### Independent content_category consumers

The taxonomy remains active and public with 13 terms and rewrite slug content_category. Its remaining consumers are independent of the seven current post-term relationships:

- Active Bricks templates Posts (ID 372) and Product Card (ID 731) render the dynamic tag {post_terms_content_category}.
- The active article dynamic-tag snippet reads content_category to select the Featured Content Manager field.
- The active Vennoot shortcode reads content_category term mappings and renders partner content.
- The active category UI snippet keeps the taxonomy as the post classification UI.
- The active HappyFiles snippet mirrors content_category assignments into hf_cat_post and can flatten term parents.
- ACF-managed Voelgoed Kategorieë registers the taxonomy. The active Kategorie Vennoot Mapping field group stores term-level partner mappings. The older Content Category — Sidebar Promo ACF group is inactive and marked superseded.

Therefore, zero relationships after a future proof-fixture cleanup would still leave active read, write, template, and routing consumers. The taxonomy remains MIGRATE under its existing register gate.

## Featured Content Manager findings

The active ACF Featured Content Manager option page currently selects products 542, 534, and 531 for Nuwe Jy, Geloof, and Self. Its selected object is 531 for Self, not post 641. The active article helper maps the Self category on a source article to that product. Post 641 is the only current Self-category article in this proof corpus and its rendered page shows Slaapmasker Deluxe (product 531).

Removing 641 would not delete the Featured Content Manager option or product 531. It would remove the only existing local Self-category article that reproduces this current snippet + ACF + Bricks integration path. Retain it temporarily by DEFER_REMOVAL until a source-controlled or automatically cleaned temporary test can reproduce that result.

## Media

Media ownership was checked separately from post relationships. No media deletion is recommended.

| Object | Featured attachment | Ownership and use |
|---:|---|---|
| 375 | 154, lograstudio-yoga-3053488_1920.webp | Parent 0; also featured by event 166; shared; retain |
| 513 | 569, fix-QC5d.jpg | Parent 513; featured only by 513; exclusive to duplicate; retain separately |
| 514 | 515, voelgoed-pexels-zQMa2V.jpg | Parent 514; Pexels-style fixture asset; no other featured use; retain separately |
| 516 | 517, voelgoed-pexels-uceVqS.jpg | Parent 516; Pexels-style fixture asset; no other featured use; retain separately |
| 518 | 567, fix-FBgT.jpg | Parent 518; no other featured use; retain separately |
| 519 | 520, voelgoed-pexels-j3smjw.jpg | Parent 519; Pexels-style fixture asset; no other featured use; retain separately |
| 521 | 522, voelgoed-pexels-5u4bLy.jpg | Parent 521; Pexels-style fixture asset; no other featured use; retain separately |
| 523 | 524, voelgoed-pexels-rB2ntw.jpg | Parent 523; Pexels-style product fixture asset; no other featured use; retain separately |
| 641 | 153, cordallman-woman-9193216_1920.webp | Parent 0; also featured by event 167; shared; retain |

The local WordPress 7.1.3 wp_delete_post implementation detaches attachments to the deleted post's parent rather than deleting the attachments. A later removal task must still leave these attachments alone; any orphan-media cleanup is a separate decision.

## Removal gates and next task

- 513, 514, 516, 518, and 519 meet the object-level READY_TO_REMOVE criteria. Task 4E may remove only those objects and their attached taxonomy relationships. Preserve migration history and media attachments.
- 521 and 523 remain DEFER_REMOVAL until their active Bricks template preview IDs are replaced and verified.
- 641 remains DEFER_REMOVAL until a repeatable Featured Content Manager → Bricks Self-category test strategy replaces its live QA role.
- 375 remains NEEDS_PROVENANCE. The filler is clearly non-editorial, but its origin/import history was not recovered.

### Future fixture policy

1. Temporary QA records should be created by the test and removed by its teardown, including when the test fails.
2. Use a clear name such as [QA][Task 4E][Self featured output] and a unique run slug. Do not reuse ordinary editorial names.
3. Default temporary fixtures to draft or private. Publish only where the test needs front-end routing or rendering; apply noindex to any published QA route and keep it on local/staging.
4. Every persistent fixture needs a source-controlled definition, owner, reason it cannot be created safely on demand, expected relationships, and a cleanup gate.
5. Persistent fixtures must be visibly identified as QA, never resemble genuine editorial content, and should not be used to support production content.
6. Exclude local QA fixture content and media from production deployments. Never copy these records into production.
7. Keep proof definitions, expected results, and historical migration evidence in Git. Live WordPress records are not the sole record of why a fixture existed.

For object-level decisions and exact columns, see [Local Fixture Decommission Manifest](LOCAL_FIXTURE_DECOMMISSION_MANIFEST.csv). For the recorded post-task snapshot, see [Post Task 4D Current State](POST_TASK_4D_CURRENT_STATE.md).
