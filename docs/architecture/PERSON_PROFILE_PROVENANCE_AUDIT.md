# Legacy Person Profile Provenance Audit

**Task:** 5C — read-only provenance and migration eligibility
**Audit date:** 2026-10-18
**Repository baseline:** origin/main at 33aae1f7d2c564ccb4117dbefafeeda83a676e66
**Runtime:** local WordPress staging at http://localhost:10039, inspected through Novamira
**WordPress persistent writes:** 0

## Decision summary

| Legacy profile | Decision | Confidence | Profile migration authority |
|---:|---|---|---|
| 753 — Lynette Beer | `DEFER` | MEDIUM | No source-origin artifact identifies this local record as genuine source content. Its links point to explicit test/QA objects. |
| 754 — Annelize van der Merwe | `DEFER` | LOW | No authoritative source evidence or independent local test relationship was found. |
| 755 — Thandi Mokoena | `DEFER` | LOW | No authoritative source evidence or independent local test relationship was found. |
| 756 — Elna Pretorius | `DEFER` | LOW | No authoritative source evidence or independent local test relationship was found. |
| 757 — Retha Coetzee | `DEFER` | MEDIUM | No source-origin artifact identifies this local record as genuine source content. Its links point to explicit test/QA objects. |

No profile qualifies as `MIGRATE` or `DO_NOT_MIGRATE`. There is no authoritative evidence that any record came from genuine source/editorial content, and no authoritative fixture/import record proving that a profile itself is a demo. Shared creation timing and QA-linked relationships are meaningful warnings, but they do not settle the origin of each profile. The five decisions were assessed individually.

The five current edges are independently classified `EXCLUDE` from Person relationship migration because their source event/products carry explicit test/QA indicators and contain no substantiating participation or commerce data. This edge decision does not classify the people themselves as test records.

## Scope and read-only method

The review covered profiles 753–757; attachments 558, 560, 562, 564, and 566; event 763; products 767 and 768; the surrounding creation cluster in IDs 540–780; current repository files and canonical origin/main Git history; active local WordPress code/configuration relevant to these records; and the two authorized local SQL snapshots. Runtime checks used Novamira read APIs, WordPress read APIs, and SQL SELECT statements only. No SQL write, ACF sync, rewrite flush, redirect, Bricks or FluentSnippet mutation, or post/term/relationship write was run.

The end-state runtime snapshot was compared to the captured start snapshot. The reviewed posts, attachments and metadata, file hashes, profile taxonomy relationships, event/product ACF relationships, Person counts, Person taxonomy relationships, and content_topic vocabulary were unchanged. See [Post-Task 5C current state](POST_TASK_5C_CURRENT_STATE.md).

### Evidence hierarchy

| Class | Meaning in this audit |
|---|---|
| `AUTHORITATIVE` | Direct evidence for the fact it records, such as an exact runtime row, migration manifest, source-controlled import script, archived source record, or source export. A local runtime row is authoritative for what staging stores, not by itself for where the data originated. |
| `STRONG_CIRCUMSTANTIAL` | A concrete pattern that raises or lowers a provenance hypothesis, such as a same-second creation batch, explicit QA names, linked test objects, a known stock-image batch, or a test script referencing the records. It does not establish source origin on its own. |
| `WEAK_CORROBORATION` | Plausibility evidence such as a real-world-looking name, published profile, profession, or a name appearing in an IA example. It cannot authorize migration. |

Locked interpretation:

```
REAL HUMAN EXISTS
!=
THIS LOCAL WORDPRESS RECORD IS GENUINE SOURCE CONTENT
```

Public-web research was not used. No profile is approved based on name, occupation, publication status, or presumed public existence.

## Canonical Person state at the start

| Check | Observed state |
|---|---|
| Persistent person posts | 0 |
| person_type terms | 1: Mentor / mentor |
| Person-to-person_type relationships | 0 |
| Person-to-content_topic relationships | 0 |
| content_topic terms | 55 |
| content_topic roots | 7: nuwe-jy, geloof, self, werk-geld, ouerskap, huwelik, mensstories |
| Person Primary Topic support | Unsupported |
| Person classification | NOT_APPLICABLE |

The Person role policy is in source-controlled Platform Core. The current Person field group supports person_type but no Primary Topic field. The live Mentor term authorizes the vocabulary concept only; it does not validate any legacy medewerker_rol assignment.

## Runtime profile inventory

All dates below are WordPress values; for these records the stored local and GMT date strings are identical. All five profiles are published, authored by user 1, and were created at 2026-06-04 06:05:47. Only profile 753 has a later modified value.

| ID | Post type / status | Title / slug | Author | Created local / GMT | Modified local / GMT | GUID / current URL | Excerpt / content | Featured image |
|---:|---|---|---:|---|---|---|---|---:|
| 753 | medewerker / publish | Lynette Beer / lynette-beer | 1 | 2026-06-04 06:05:47 / 06:05:47 | 2026-06-04 06:48:03 / 06:48:03 | http://localhost:10039/medewerkers/lynette-beer/ | Lewensafrigter en spreker. / same text in <p> | 558 |
| 754 | medewerker / publish | Annelize van der Merwe / annelize-van-der-merwe | 1 | 2026-06-04 06:05:47 / 06:05:47 | 2026-06-04 06:05:47 / 06:05:47 | http://localhost:10039/medewerkers/annelize-van-der-merwe/ | Finansiële mentor. / same text in <p> | 560 |
| 755 | medewerker / publish | Thandi Mokoena / thandi-mokoena | 1 | 2026-06-04 06:05:47 / 06:05:47 | 2026-06-04 06:05:47 / 06:05:47 | http://localhost:10039/medewerkers/thandi-mokoena/ | Huweliks- en gesinsterapeut. / same text in <p> | 562 |
| 756 | medewerker / publish | Elna Pretorius / elna-pretorius | 1 | 2026-06-04 06:05:47 / 06:05:47 | 2026-06-04 06:05:47 / 06:05:47 | http://localhost:10039/medewerkers/elna-pretorius/ | Wellness-afrigter. / same text in <p> | 564 |
| 757 | medewerker / publish | Retha Coetzee / retha-coetzee | 1 | 2026-06-04 06:05:47 / 06:05:47 | 2026-06-04 06:05:47 / 06:05:47 | http://localhost:10039/medewerkers/retha-coetzee/ | Skrywer en verhaalverteller. / same text in <p> | 566 |

Each profile has medewerker_rol=Mentor (term 298), medewerker_groep=Voelgoed Mentor (term 307), no content_topic terms, and no content_category terms. Every medewerker_kort_bio equals both the excerpt and text inside post content. A global scan found no other current ACF relationship edge to these profiles. Event revision 769 retains a historical copy of the same event_medewerkers values; it is not an additional live edge. The only additional current pointer is Bricks template 772's editor preview target, profile 753; it is an editor preview setting, not a content relationship.

### Profile metadata and ACF values

The complete relevant profile metadata includes _masteriyo_has_content=0 and _thumbnail_id for each record. ACF reference metadata is _medewerker_kort_bio=field_medewerker_kort_bio and _medewerker_profiel_status=field_medewerker_profiel_status on every profile; profile 753 also has _medewerker_titel=field_medewerker_titel. Profile 753 additionally has _edit_last=1 and a volatile _edit_lock; no other profile editor metadata was present. The underscored ACF reference keys identify field definitions, not extra content values. The edit lock/last-editor values are excluded from fingerprints.

| ID | ACF values | Other profile metadata |
|---:|---|---|
| 753 | medewerker_titel=Spreker; medewerker_kort_bio=Lewensafrigter en spreker.; medewerker_profiel_status=public | _masteriyo_has_content=0; _thumbnail_id=558; ACF reference keys for all populated fields; edit-last/lock values |
| 754 | medewerker_kort_bio=Finansiële mentor.; medewerker_profiel_status=public | _masteriyo_has_content=0; _thumbnail_id=560; ACF reference keys |
| 755 | medewerker_kort_bio=Huweliks- en gesinsterapeut.; medewerker_profiel_status=public | _masteriyo_has_content=0; _thumbnail_id=562; ACF reference keys |
| 756 | medewerker_kort_bio=Wellness-afrigter.; medewerker_profiel_status=public | _masteriyo_has_content=0; _thumbnail_id=564; ACF reference keys |
| 757 | medewerker_kort_bio=Skrywer en verhaalverteller.; medewerker_profiel_status=public | _masteriyo_has_content=0; _thumbnail_id=566; ACF reference keys |

No source/import ID, production reference, email, credentials, company, website, social value, or populated Primary Topic metadata was found on these profiles.

### URL and indexing observations

The current generated URLs are the local /medewerkers/{slug}/ URLs listed above. On 2026-10-17, the Task 5A read-only HTTP audit observed all five singles and the archive at HTTP 200 with self-canonical URLs and no robots/googlebot meta directive or X-Robots-Tag header. Task 5C confirmed the generated permalinks and found no Rank Math canonical, robots, title, or description override metadata. Task 5C did not issue a second front-end HTTP request, so the response observation is carried forward from the prior-day audit, not claimed as a new 2026-10-18 response. No route, canonical, indexing, or redirect was changed.

## Fingerprint comparison

The Task 5A values are retained verbatim in [the migration manifest](PERSON_PROFILE_MIGRATION_MANIFEST.csv). Task 5A documented covered fields but did not preserve the byte-level normalization/serialization recipe. Several common JSON and PHP serialization approaches did not reproduce its digests.

The deterministic Task 5C normalized JSON contains:

```
ID, post_type, status, title, slug,
created_local, created_gmt, modified_local, modified_gmt, author,
excerpt, content, featured_image_id, profile_acf,
taxonomy_relationships, incoming_acf_relationships
```

Object keys are recursively sorted; term IDs and incoming edges are sorted; JSON uses UTF-8, unescaped Unicode and slashes, and no whitespace; SHA-256 is computed over those JSON bytes. Editor lock/last-editor metadata, attachment file contents, and unrelated runtime metadata are excluded. Current post facts and assignments match Task 5A's published profile facts and relationship counts; no changed profile source fact was found.

| ID | Task 5A fingerprint | Task 5C fingerprint | Match | Source-fact change found |
|---:|---|---|---|---|
| 753 | 1878534e680af807c844fb52edfeffe14deb2001018199ffc7a4a1336a49fed6 | 2aad48c7dceef6dd0b4ba8db43b569cf12b7667621e56d6760ebe3377d6ca9f1 | NO | None found; Task 5A byte-level recipe unavailable |
| 754 | 269e18de8169bd1655b46207bc6cad8b2d924dc74be5ffdd1b0e8ea8137263db | 837f3555054e67228447453fbb409d1ec4a0b019b820d7a0da719904100ef43c | NO | None found; Task 5A byte-level recipe unavailable |
| 755 | bca4766d69a7e632a0c16f011249df71d6b3eaca52938a2fb440430fb51453bd | 41ed4f4974eb308c7d75ec259f6ce7ee6fb73fdb6c79689e09c2f1628a34d1f3 | NO | None found; Task 5A byte-level recipe unavailable |
| 756 | 792305193d811bad3b0f9576f8898103fafb06995737cb5f11fa0588162cf0cc | 8a7ecef9d6a81c3b30a71cd2da91520d4b1bb43e11b309ffcb521b8611754df3 | NO | None found; Task 5A byte-level recipe unavailable |
| 757 | baaf83ee01bc6fdf266dabef2d4cc636cc7a222395ae351ebc33808f8cb3b969 | e37c0cd48024fa9d9a12083101ad509757d850ba14c7af79f234f1c1d5477fc2 | NO | None found; Task 5A byte-level recipe unavailable |

NO means the Task 5A digest does not equal the Task 5C digest under the explicit Task 5C recipe. It does not demonstrate source drift. The discrepancy cannot be attributed to a source fact without Task 5A's normalization recipe; the old hash was not replaced. Task 5D must re-fingerprint immediately before any authorized write and stop on any unreviewed source change.

## Attachment provenance

The attachment posts and original files were read, including metadata and SHA-256. Each is image/jpeg, authored by user 1, parent 0, status inherit, titled for the paired profile, and created in a four-second batch on 2026-05-27; each modified timestamp equals its created timestamp. Each has no caption, description, alt text, creator/credit, or WordPress attachment parent. Each has a direct _pexels_source_url. _wp_attachment_metadata records the dimensions, file size, generated image sizes, and empty camera/credit/copyright fields. Each original file is featured on its paired profile.

| ID | Attachment title | Filename | Relative path | MIME / status | Dimensions | Size | Created / modified | Author / parent | File SHA-256 | Current use |
|---:|---|---|---|---|---:|---:|---|---|---|---|
| 558 | Lynette Beer | voelgoed-pexels-OHplpN.jpg | 2026/05/voelgoed-pexels-OHplpN.jpg | image/jpeg / inherit | 1200×900 | 110,475 bytes | 2026-05-27 08:06:10 / 08:06:10 | 1 / 0 | 5adf8753944a334925850783b7204fea281e16530e6ef7651e6b1fc86c9b2ba8 | Featured image on 753 |
| 560 | Annelize van der Merwe | voelgoed-pexels-Gc8Rza.jpg | 2026/05/voelgoed-pexels-Gc8Rza.jpg | image/jpeg / inherit | 1200×800 | 81,338 bytes | 2026-05-27 08:06:11 / 08:06:11 | 1 / 0 | 8806328e27b0333ebaf0df510615caf45adc1888cdc05ae1f5488f11c2d5db9d | Featured image on 754 |
| 562 | Thandi Mokoena | voelgoed-pexels-XNDBLA.jpg | 2026/05/voelgoed-pexels-XNDBLA.jpg | image/jpeg / inherit | 1200×800 | 46,630 bytes | 2026-05-27 08:06:11 / 08:06:11 | 1 / 0 | cc498fad8b5b6c1bd63ed16d4f11fdb5ed1a0668c4ebd3a5c8b8bb5ed1983036 | Featured image on 755 |
| 564 | Elna Pretorius | voelgoed-pexels-M4OozZ.jpg | 2026/05/voelgoed-pexels-M4OozZ.jpg | image/jpeg / inherit | 1200×1951 | 123,282 bytes | 2026-05-27 08:06:13 / 08:06:13 | 1 / 0 | 8fa26e88f5ba35e17c62dbd2b7a5dd1626478beaa00887f5ea1eaaa59624cf4e | Featured image on 756 |
| 566 | Retha Coetzee | voelgoed-pexels-TXEoYM.jpg | 2026/05/voelgoed-pexels-TXEoYM.jpg | image/jpeg / inherit | 1200×800 | 57,492 bytes | 2026-05-27 08:06:14 / 08:06:14 | 1 / 0 | 9db3a1e06c3533095cf7dff87ce17d2f76b9d61a0a733bf6b837ac15883385d5 | Featured image on 757 |

The five photos form a Pexels stock batch, but no source metadata ties a photo to the named person. Current event template 774 does not reference attachment 564; historical revisions of that template contain its filename. No image is established as a portrait. This supports OMIT for each photo while leaving the person's identity as a separate question; it does not invalidate any identity.

## Creation timeline and QA/rebuild cluster

The bounded runtime inventory covered IDs 540–780; the relevant cluster is 741–779. Numeric order is considered only alongside timestamps, author, titles, relationships, and source/repository evidence.

| ID | Type / title | Created local | Status / finding |
|---:|---|---|---|
| 741 | ACF post type: Medewerker | 2026-06-04 06:05:23 | Published database-backed CPT definition |
| 742–743 | ACF taxonomies: Medewerkerrol / Medewerkergroep | 06:05:26–06:05:27 | Published definitions |
| 744–747 | Profile ACF group and fields | 06:05:35 | Schema created seconds before profiles |
| 748–749 | Event Medewerkers group and field | 06:05:36 | Relationship schema |
| 750–752 | Medewerker Produkte group and fields | 06:05:42 | Product/event relationship schema |
| 753–757 | Five profiles | 06:05:47 | Same author and second; same status, same classifications, one short sentence each |
| 763–764 | Test Event; Tickera API key row | 06:10:50 | Explicit test/QA event |
| 765–766 | Test Event Recording; revision | 06:10:50–06:10:56 | Trashed product with QA slug |
| 767 | Test Event Recording QA2 | 06:11:19 | Published QA product without commerce setup |
| 768 | QA4 | 06:11:33 | Trashed product with QA title |
| 769–771 | Event/product revisions | 06:11:40 | Revisions created together |
| 772–774 | Medewerker Single, Medewerker Archive, Event Single templates | 06:34:33–06:34:52 | Rebuild templates by user 1 |
| 779 | Lynette profile revision | 06:47:23 | Only profile revision observed in this group |

The schema-before-profile pattern, same-second profiles, same author, stock-image batch, duplicated one-line bios, repeated assignments, and nearby test event/products form a coherent local rebuild/QA cluster. This is STRONG_CIRCUMSTANTIAL evidence of staging/test activity around the records. There is no creator script, fixture manifest, or source export proving that the profiles themselves were fixtures.

## Event 763 and products 767 / 768

### Event 763

| Attribute | Current value |
|---|---|
| Type / status | tc_events / publish |
| Title / slug | Test Event / test-event-medewerkers-qa |
| Created / modified | 2026-06-04 06:10:50 / 2026-06-05 11:09:06 |
| Author / featured image | 1 / none |
| event_medewerkers | 753, 757 |
| Other event fields | event_date_time=2026-06-26 23:45; event_end_date_time=2026-07-04 16:30; location, event logo, terms, and sponsor logo blank; presentation page 763; hide-after-expiration 0; limit level 0; show tickets automatically 0 |
| Event taxonomy / source metadata | No event_category assignment, source/import ID, or import marker |
| Revision / media | Revision 769 at 2026-06-04 06:11:40; no featured image |

Its explicit title and slug are QA indicators; the fields contain no location or participation detail. Both edges are excluded independently in the relationship ledger.

### Products 767 / 768

| ID | Status / title / slug | Created / modified | WooCommerce state | ACF relationship values |
|---:|---|---|---|---|
| 767 | publish / Test Event Recording QA2 / test-event-recording-qa2 | 2026-06-04 06:11:19 / 06:35:44 | Simple product; no SKU, price, regular/sale price, images, categories, or tags; instock; zero sales; no order references | produk_bron_event=763; produk_medewerkers=[753] |
| 768 | trash / QA4 / qa4__trashed | 2026-06-04 06:11:33 / 06:11:40 | No price or order references; trash metadata retains prior published status and desired slug qa4 | produk_bron_event=763; produk_medewerkers=[753,757] |

Product 767 also has _wp_old_slug=test-event-recording-qa2__trashed. Product 768 has _wp_trash_meta_status=publish, _wp_desired_post_slug=qa4, and a WooCommerce trash timestamp. Revisions 770/771 were created alongside event revision 769. Explicit QA naming, missing commerce setup/dependencies, and linkage to the QA event justify excluding these edges. Product 768's trash state is not used alone. No product, event, order, or profile was changed.

Event 763 also has the ACF reference key _event_medewerkers, _masteriyo_has_content=0, and edit-last/lock metadata. Product 767 has _produk_bron_event and _produk_medewerkers ACF reference keys, _masteriyo_has_content=0, _wp_old_slug, and its two ACF values; product 768 has the ACF reference keys, _masteriyo_has_content=0, trash metadata, and its two ACF values. No other relevant post metadata or source/import identifiers were found. No fixture-loader markers were found. No additional profile edges were found in event/product fields or broadcast_hosts / broadcast_guests.

## Repository and Git-history findings

Repository-wide searches covered profile IDs, names, slugs, attachment IDs/filenames, event/product IDs/titles, event_medewerkers, produk_medewerkers, Voelgoed Mentor, and medewerker.

- site-code/runtime-tests/person-foundation-qa.php snapshots IDs 753–757 and event/products 763/767/768 to assert they remain unchanged during temporary Person-foundation QA. It creates and cleans up its own temporary Person test object; it does not create these legacy profiles, attachments, event, or products. The file was inspected, not run in Task 5C.
- repo/VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md names Lynette Beer as an IA example. That is a design example, not an import record.
- No source-controlled fixture loader, seeder, migration script, SQL snippet, Bricks export, or import mapping creating these records/files was found.
- Runtime snippets 9 and 10 were inspected read-only: snippet 9 copies event staff into an empty product field; snippet 10 supplies Bricks query helpers. Neither creates the target records.
- No profile creator or source/import identifiers were found in active plugins, MU plugins, theme/child theme, ACF definitions, Bricks template 774, or active FluentSnippets.

Git history was searched on origin/main only. Hidden stashes and unrelated local references were not inspected.

| Commit | Path | Finding | Interpretation |
|---|---|---|---|
| 6e70c0c65c0a05bcb0a92ecf1aaf340286b9e270 | repo/VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md | Adds Lynette Beer as an IA example | Weak corroboration, not source-record provenance |
| f70d7fb527a6f0471a856236f0650a25a78a90b4 | site-code/runtime-tests/person-foundation-qa.php | Adds QA snapshot/assertions for legacy IDs and edges | Later regression safeguard; no profile-creation provenance or migration-eligibility evidence |
| 8fd5460c5e12bb9a868032bd801f5c900c1e3eb9 | Person Task 5A audit, manifest, architecture docs | Records IDs, slugs, image names, QA links from runtime audit | Documentation of runtime findings, not an original import source |

No historical source-controlled creator/import script or archived source row was found for these names, slugs, filenames, event 763, or products 767/768. That absence does not rule out a deleted external importer, manual editor workflow, or unavailable source material.

## Runtime automation, database snapshots, and source metadata

- ACF definition records define profile/event/product fields but contain no seed/import logic.
- Active local plugins, MU plugins, theme/child theme, ACF definitions, Bricks template 774, and active FluentSnippets were searched for creation patterns. No mechanism creating these specific records was identified.
- Current Bricks template 774 does not reference attachment 564. Historical template revisions 839–865 contain its filename. This is a historical asset reference, not evidence that the image depicts Elna.
- Read-only SQL inspection found no old source slug, source ID, import ID, or fixture marker on profiles or attachments. Revisions were observed only for profile 753, event 763, and products 767/768.
- The authorized local-backup-addon-database-dump.sql (2026-06-09) and local.sql (2026-10-03) contain the same records. These are post-creation snapshots; they establish persistence, not origin or authorship.
- No production export, historical source database, migration manifest, or archived editorial record establishing the five profiles as genuine Voelgoed records was available.

Material post/meta facts can be reproduced with read-only SELECTs over wp_posts and wp_postmeta, using the live prefix returned by WordPress:

```sql
SELECT ID, post_type, post_status, post_title, post_name,
       post_author, post_date, post_date_gmt, post_modified,
       post_modified_gmt, guid, post_excerpt, post_content
FROM wp_posts
WHERE ID IN (753, 754, 755, 756, 757, 558, 560, 562, 564, 566, 763, 767, 768);

SELECT post_id, meta_key, meta_value
FROM wp_postmeta
WHERE post_id IN (753, 754, 755, 756, 757, 558, 560, 562, 564, 566, 763, 767, 768)
ORDER BY post_id, meta_key, meta_id;
```

Taxonomy relationships were read separately; these two queries are examples for post/meta facts, not the complete relationship check.

## Independent profile decisions

### Profile 753 — Lynette Beer

**Decision:** `DEFER` — **Confidence:** MEDIUM

- **Authoritative:** Runtime confirms its published local state, ACF title/bio/status, image, two taxonomy assignments, and three incoming edges. No original CMS record, source ID, production export, or migration mapping was found.
- **Strong circumstantial:** Same-second schema/profile batch; stock Pexels image; duplicated one-sentence content; links to Test Event and QA-named products. These links inform the edge decision, not the profile’s source origin.
- **Weak corroboration:** The name appears in a canonical IA example; the name, publication status, and “Lewensafrigter en spreker.” are plausible, not source provenance.
- **Contradictory evidence:** Test/QA relationships weigh against migrating those edges but do not prove the profile is a fixture. The IA example is not an import artifact.
- **Unresolved:** Genuine source/editorial origin; Mentor and Voelgoed Mentor assignment validity; whether a verified portrait exists; whether Spreker was a durable title or event context.
- **Field decisions:** name DEFER; slug DEFER; photo OMIT; short bio DEFER; full bio OMIT; professional title OMIT; Mentor DEFER; Voelgoed Mentor DEFER; content_topic OMIT.

### Profile 754 — Annelize van der Merwe

**Decision:** `DEFER` — **Confidence:** LOW

- **Authoritative:** The local row and ACF values are known, but no source ID, export, import record, or editorial artifact ties it to genuine source content.
- **Strong circumstantial:** It shares the exact-second author/creation, schema timing, repeated classifications, stock-image batch, and duplicated one-line content.
- **Weak corroboration:** “Finansiële mentor.” is plausible profile copy and the post is published.
- **Contradictory evidence:** No QA-linked event/product edge points to 754. That distinguishes it from 753/757 but does not establish genuine source origin.
- **Unresolved:** Identity/source origin; classification validity; whether a verified portrait or fuller biography exists.
- **Field decisions:** name DEFER; slug DEFER; photo OMIT; short bio DEFER; full bio OMIT; professional title OMIT (no value); Mentor DEFER; Voelgoed Mentor DEFER; content_topic OMIT.

### Profile 755 — Thandi Mokoena

**Decision:** `DEFER` — **Confidence:** LOW

- **Authoritative:** No source identifier, export, import log, or archived editorial item establishes the origin of the local record.
- **Strong circumstantial:** It shares the exact-second schema/profile batch, author, Pexels image batch, repeated classifications, and bio duplication.
- **Weak corroboration:** “Huweliks- en gesinsterapeut.” is plausible and the profile is published; neither proves source origin.
- **Contradictory evidence:** No event, product, or other profile relationship points to 755. Its evidence differs from 753/757, but there is still no migration source.
- **Unresolved:** Whether the identity, text, and classifications came from genuine source/editorial data; whether a verified portrait or longer bio exists.
- **Field decisions:** name DEFER; slug DEFER; photo OMIT; short bio DEFER; full bio OMIT; professional title OMIT (no value); Mentor DEFER; Voelgoed Mentor DEFER; content_topic OMIT.

### Profile 756 — Elna Pretorius

**Decision:** `DEFER` — **Confidence:** LOW

- **Authoritative:** Runtime confirms the local record and ACF values, but no source export, original CMS identifier, import metadata, or durable editorial source connects it to the source corpus.
- **Strong circumstantial:** Same-second creation/author batch, shared Pexels image batch, repeated classifications, and one-line duplicated content.
- **Weak corroboration:** “Wellness-afrigter.” is plausible and the record is published; these facts establish neither identity nor source origin.
- **Contradictory evidence:** No QA-linked relationship points to 756. Current template 774 does not use attachment 564, while historical revisions do; neither resolves profile origin.
- **Unresolved:** Identity/source origin, classification validity, and whether there is a real portrait or distinct long bio.
- **Field decisions:** name DEFER; slug DEFER; photo OMIT; short bio DEFER; full bio OMIT; professional title OMIT (no value); Mentor DEFER; Voelgoed Mentor DEFER; content_topic OMIT.

### Profile 757 — Retha Coetzee

**Decision:** `DEFER` — **Confidence:** MEDIUM

- **Authoritative:** Runtime confirms its published state, ACF bio/status, image, two taxonomy assignments, and two incoming edges. No source-system ID, source export, or editorial artifact confirms origin.
- **Strong circumstantial:** Same-second profile/schema batch, author, stock image, duplicated minimal bio, and repeated classifications; links to Test Event and trashed QA4.
- **Weak corroboration:** “Skrywer en verhaalverteller.” is plausible. No source-controlled article attribution or identity record was found.
- **Contradictory evidence:** QA links are evidence about those edges and related objects, not proof that the person profile was fabricated.
- **Unresolved:** Genuine source identity; taxonomy validity; a verified portrait or distinct long bio.
- **Field decisions:** name DEFER; slug DEFER; photo OMIT; short bio DEFER; full bio OMIT; professional title OMIT (no value); Mentor DEFER; Voelgoed Mentor DEFER; content_topic OMIT.

### Field decision policy

These are future migration instructions; no value was copied.

| Field | Decision basis |
|---|---|
| Name | DEFER until identity and source-record origin are established per profile. |
| Slug | DEFER; no target exists and the local slug is not migration authority. |
| Featured image | OMIT for all five. Pexels origin is known, but a subject/portrait link is not. |
| Short bio | DEFER; each is one short phrase without source artifact and duplicates excerpt/content. |
| Full bio | OMIT; post content duplicates the short bio and is not a distinct long biography. |
| Professional title | OMIT. Spreker on 753 is contextual; the other four have no value. |
| Mentor assignment | DEFER; person_type=Mentor does not validate the legacy assignments. |
| Voelgoed Mentor grouping | DEFER; do not map to person_type or design a destination here. |
| content_topic | OMIT; current assignments are empty and expertise is not inferred. |

## Relationship decisions

EXCLUDE means do not migrate the current edge. It does not delete or change the WordPress relationship.

| Current edge | Source context | Profile decision | Edge decision / confidence | Basis | Future target |
|---|---|---|---|---|---|
| Event 763 → 753 | Test Event / test-event-medewerkers-qa | DEFER | EXCLUDE / MEDIUM | Explicit QA title/slug; sparse event; no participation details | event_participation only after independent future approval |
| Event 763 → 757 | Same event | DEFER | EXCLUDE / MEDIUM | Same QA source record; edge existence approves neither event nor Person | event_participation only after independent future approval |
| Product 767 → 753 | Test Event Recording QA2 | DEFER | EXCLUDE / MEDIUM | QA title, source event 763, no SKU/price/images/orders/sales | Product-to-Person after event participation and atomic automation replacement |
| Product 768 → 753 | QA4, trashed; source event 763 | DEFER | EXCLUDE / MEDIUM | QA title, no price/order data; trash status is not the sole basis | Product-to-Person after event participation and atomic automation replacement |
| Product 768 → 757 | Same product | DEFER | EXCLUDE / MEDIUM | Same QA source; no independent edge provenance | Product-to-Person after event participation and atomic automation replacement |

No additional event, product, episode host/guest, course, editorial, or profile edge was found. Product migration remains downstream of event participation and atomic replacement of the current copy helper. event_medewerkers, produk_medewerkers, produk_bron_event, snippets 9-acf-product-copy.php and 10-vg-bricks-medewerker-queries.php, Bricks data, routes, and relationships were not changed.

## Limitations

- No production export, original source database, migration manifest, source CMS IDs, or archived editorial artifacts were available.
- Local SQL backups post-date record creation and cannot establish original source.
- Negative searches cannot rule out deleted importers, manual creation, or unavailable source materials.
- Public evidence was not searched; if used later, it may corroborate identity but cannot prove this local record came from Voelgoed source content.
- Indexability uses prior-day HTTP observations plus current stored metadata; no second front-end request was made.
- Task 5A's hash recipe is not byte-for-byte reproducible. Old hashes remain intact; new deterministic hashes are recorded; no source drift was identified.

## Related records

- [Migration manifest](PERSON_PROFILE_MIGRATION_MANIFEST.csv)
- [Evidence ledger](PERSON_PROFILE_PROVENANCE_EVIDENCE.csv)
- [Relationship provenance](PERSON_RELATIONSHIP_PROVENANCE.csv)
- [Migration architecture](PERSON_MIGRATION_ARCHITECTURE.md)
- [Legacy decommission register](LEGACY_DECOMMISSION_REGISTER.md)
- [Post-Task 5C current state](POST_TASK_5C_CURRENT_STATE.md)
