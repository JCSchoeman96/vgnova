# Editorial Dimensions Implementation — Task 4B

**Verified:** 2026-10-15
**Scope:** `editorial_format` and `editorial_collection` for standard `post` only.

## Ownership and source control

ACF Pro owns both taxonomy definitions and the `Editorial Classification` field group. No taxonomy registration was added to Voelgoed Platform Core, theme PHP, or FluentSnippets.

The tracked ACF Local JSON definitions are:

- `site-code/editorial-dimensions/acf-json/taxonomy_editorial_format.json`
- `site-code/editorial-dimensions/acf-json/taxonomy_editorial_collection.json`
- `site-code/editorial-dimensions/acf-json/group_vg_editorial_classification.json`

The active Bricks Child theme's ACF JSON directory is:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json
```

That directory is a symlink to this repository module's `site-code/editorial-dimensions/acf-json/`. ACF Local JSON reads and writes through that path. On a fresh checkout, create the target directory and link the active theme's `acf-json` directory to the repository-backed folder before syncing or editing these definitions in ACF. The JSON files were generated through the ACF-managed definition workflow, not hand-written.

At verification time ACF's runtime records reported `source: db`. Git remains canonical through the tracked Local JSON files; the current ACF database records are the runtime copy. ACF's runtime taxonomy payloads and the JSON files matched for all persisted registration properties. The field group location and every source-defined field property also matched. ACF adds runtime metadata when it loads fields (`ID`, `prefix`, `value`, `menu_order`, `id`, `class`, `parent`, `_name`, `_valid`); these are generated record/runtime values, not source configuration. The database `ID`, ACF validity marker, and modification timestamp are likewise excluded from the parity comparison.

This follows ACF's Local JSON workflow for field groups and ACF-managed taxonomies. See [ACF Local JSON](https://www.advancedcustomfields.com/resources/local-json/) and [WP-CLI JSON export](https://www.advancedcustomfields.com/resources/wp-acf-json-export/).

## Taxonomy configuration

Both taxonomies are flat, ACF-owned, and attached to `post` only. They have `public = false`, `publicly_queryable = false`, rewrite disabled (`rewrite = false` at runtime), no query variable, no navigation-menu or tag-cloud exposure, and `show_ui = true` for governed term administration. Their native taxonomy meta boxes are disabled so the ACF field group is the only post-editor control.

`editorial_format` has the closed approved vocabulary:

| Slug | Editor label |
|---|---|
| `article` | Artikel |
| `video` | Video |
| `audio` | Oudio |

The source-controlled seeder is `site-code/editorial-dimensions/seed-editorial-format-terms.php`. Run it in WP-CLI with `wp eval-file /path/to/vgnova/site-code/editorial-dimensions/seed-editorial-format-terms.php`. It is idempotent, creates no post relationships, and stops without changing terms if it finds a same-slug/name conflict or any unapproved format term. If a run stops after some approved terms were created, a rerun recognizes those terms and creates only the missing approved ones. It never seeds `editorial_collection`.

`editorial_collection` currently has no terms. Future terms require a reviewed collection list and must be created through the governed ACF/WordPress term-management workflow. Editors cannot add either taxonomy's terms from the post editor.

## Editor controls

One ACF group named **Editorial Classification** is located only on `post`.

- **Formaat** is an ACF taxonomy field using a radio control. It loads and saves `editorial_format` relationships, returns term IDs, has no default, is not required, and has inline term creation disabled.
- **Versameling** is an ACF taxonomy field using a multi-select control. It loads and saves `editorial_collection` relationships, returns term IDs, is optional, and has inline term creation disabled.

The fields store taxonomy relationships rather than a separate post-meta classification. The blank format state is intentional. The eventual one-format invariant is not globally enforced until the existing corpus has been reviewed and backfilled. There is no automatic `article` assignment or inference from content or legacy categories.

The WordPress core `post_format` taxonomy was left untouched.

## Runtime verification

Verified through Novamira/WP-CLI against `localhost:10039` (WordPress 7.1.3, ACF Pro 6.8.1):

- Both taxonomies are registered for `post` only, flat, private, non-queryable as public term pages, and have no rewrite rules.
- The editor group is active and located on standard posts only. Runtime ACF registration and field properties match the tracked Local JSON source as described above.
- A `WP_Query` `tax_query` for each taxonomy completed without error. Both returned zero posts because the final relationship count is zero. Bricks' query integration accepts WordPress taxonomy queries; its query implementation converts configured `tax_query` values into WordPress query arguments. No Bricks filters, templates, or listing UI were added.
- The post permalink structure remains `/content/%postname%/`. No editorial taxonomy rewrite rules exist.
- `editorial_format` contains exactly the three terms above and has zero relationships. `editorial_collection` contains zero terms and zero relationships.
- `content_topic` remains at 55 terms and 7 relationships across 7 objects. `content_category` remains at 13 terms and 8 relationships across 7 objects, matching the pre-task runtime snapshot.

## Controlled QA fixture

One temporary draft post, ID `1222`, was used and permanently deleted after verification. In the browser editor, the post saved with no format selected; the saved post had no editorial relationships. Assigning `article` created exactly one format relationship. Switching to `video` replaced `article`, leaving only `video`. The collection control remained empty throughout. The format assignment was removed and the draft was permanently deleted. No collection term was created.

The final runtime check confirmed that post `1222` no longer exists and both editorial taxonomies have zero relationships. The authenticated browser session was obtained through Novamira's credential-free temporary access flow; no credentials were requested.

## Tests and verification

The standalone automated coverage is in `site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php`. It checks taxonomy and field-group configuration, post-only scope, disabled public routing, ACF field cardinality and requiredness, no default, inline term governance, conflict handling, complete and partial rerun behavior, empty collection seeding, and the absence of relationship writes or unrelated ACF definitions.

The standalone test runner uses PHP 8 syntax. The staging runtime used for this verification runs PHP 8.5.3.

Commands run for this task:

```text
php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
php -l site-code/editorial-dimensions/seed-editorial-format-terms.php
php -l site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
git diff --check
```

The Platform Core test suite was also run at baseline and passed (`66 content topic tests passed`). Platform Core was not modified.

## Non-goals and legacy status

No production posts were classified or backfilled. No collection terms were proposed or created. `content_topic`, `content_category`, commercial classifications, access/membership systems, URLs, templates, and unrelated entity models were not changed. Editorial flags and campaign context remain deferred. The legacy decommission register was not updated because this implementation did not advance any `content_category` removal gate or make a legacy item newly obsolete.
