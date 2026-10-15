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

That directory is a symlink to this repository module's `site-code/editorial-dimensions/acf-json/`. ACF Local JSON reads and writes through that path. ACF Pro owns the definitions, Git Local JSON is their canonical source-controlled representation, and the WordPress database holds the runtime records. The ACF JSON sync command is the explicit bridge between Git files and those database records. A file changing in Git does not update an existing database record until ACF sync runs.

At verification time ACF's runtime records reported `source: db`. ACF's runtime taxonomy payloads and the JSON files matched for all persisted registration properties. The field group location and every source-defined field property also matched. ACF adds runtime metadata when it loads fields (`ID`, `prefix`, `value`, `menu_order`, `id`, `class`, `parent`, `_name`, `_valid`); these are generated record/runtime values, not source configuration. The database `ID`, ACF validity marker, and modification timestamp are likewise excluded from the parity comparison.

### Fresh-environment setup

Use this order for a new Local or staging checkout. Run WP-CLI commands from the WordPress root. Set `VG_REPO_ROOT` to the checkout root and `VG_WP_ROOT` to the WordPress root.

1. Check out the Git revision that contains the three tracked JSON files listed above.
2. Make the active theme's `acf-json` path point to the repository folder. If the path does not exist, create the symlink. If it already exists, verify that it resolves to the repository folder and repair it before continuing if it does not.

   ```sh
   ACF_JSON_LINK="$VG_WP_ROOT/wp-content/themes/bricks-child/acf-json"
   ACF_JSON_SOURCE="$VG_REPO_ROOT/site-code/editorial-dimensions/acf-json"
   mkdir -p "$VG_WP_ROOT/wp-content/themes/bricks-child"

   if [ -L "$ACF_JSON_LINK" ]; then
     test "$(readlink -f "$ACF_JSON_LINK")" = "$(readlink -f "$ACF_JSON_SOURCE")" || exit 1
   elif [ ! -e "$ACF_JSON_LINK" ]; then
     ln -s "$ACF_JSON_SOURCE" "$ACF_JSON_LINK"
   else
     printf '%s\n' "Inspect the existing acf-json directory before continuing." >&2
     exit 1
   fi

   test "$(readlink -f "$ACF_JSON_LINK")" = "$(readlink -f "$ACF_JSON_SOURCE")"
   ```

   This creates the link only when the destination is absent. If a directory or a link to a different location exists, stop and inspect it. Preserve any unique JSON files before correcting the path.
3. Check the ACF JSON state, then dry-run and sync only the two taxonomy records and the editorial field group. Apply each sync only when its dry-run identifies the expected key and intended `Create` or `Update` action. If a key is missing or the proposed action is unexpected, stop and fix the JSON path or source before continuing. If ACF reports that a key is already in sync, no sync is needed for that key.

   ```sh
   wp acf json status --type=taxonomy --detailed
   wp acf json status --type=field-group --detailed

   wp acf json sync --type=taxonomy --key=taxonomy_editorial_format --dry-run
   wp acf json sync --type=taxonomy --key=taxonomy_editorial_collection --dry-run
   wp acf json sync --type=field-group --key=group_vg_editorial_classification --dry-run

   # After reviewing all dry-runs, apply only keys that showed the expected Create or Update action.
   wp acf json sync --type=taxonomy --key=taxonomy_editorial_format
   wp acf json sync --type=taxonomy --key=taxonomy_editorial_collection
   wp acf json sync --type=field-group --key=group_vg_editorial_classification
   ```

   Skip an apply command for any key already in sync. The installed ACF CLI returns "No syncable item found" for an already-synced key, with a non-zero exit status. Do not run an unfiltered sync as part of this setup. It could apply unrelated pending ACF JSON records.
4. Verify both taxonomies register before running the seeder:

   ```sh
   wp eval 'foreach (["editorial_format", "editorial_collection"] as $taxonomy) { if (! taxonomy_exists($taxonomy)) { WP_CLI::error("Taxonomy not registered: " . $taxonomy); } } WP_CLI::success("Both editorial taxonomies are registered.");'
   ```

   If either taxonomy is missing, stop and correct the ACF JSON path or sync result. Do not run the seeder before registration succeeds.
5. Seed only the approved format terms:

   ```sh
   wp eval-file "$VG_REPO_ROOT/site-code/editorial-dimensions/seed-editorial-format-terms.php"
   ```

6. Verify final source/runtime parity. `wp acf json status` must show the three Task 4B records as in sync. Compare the runtime ACF taxonomy and field-group properties with the tracked JSON, excluding generated runtime IDs and metadata described above. Confirm the term state is `article`, `video`, and `audio` for `editorial_format`, with no `editorial_collection` terms or editorial post relationships.

The `wp acf json status` and targeted `sync` commands use ACF Pro's WP-CLI JSON workflow, available in ACF 6.8 and later. This environment runs ACF Pro 6.8.1 and WP-CLI 2.12.0. The targeted dry-run commands were accepted here and reported the Task 4B records already in sync, so no database sync was run during this documentation revision. See [ACF Local JSON](https://www.advancedcustomfields.com/resources/local-json/), [WP-CLI JSON status](https://www.advancedcustomfields.com/resources/wp-acf-json-status/), and [WP-CLI JSON sync](https://www.advancedcustomfields.com/resources/wp-acf-json-sync/).

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

The authoritative classification source is the WordPress taxonomy relationship. `Save Terms` (`save_terms = 1`) writes the selected terms to the post's taxonomy relationship, and `Load Terms` (`load_terms = 1`) loads the post's taxonomy terms into the ACF control, as described in [ACF's Taxonomy field documentation](https://www.advancedcustomfields.com/resources/taxonomy/). ACF may also persist its normal field value in post meta. That meta is implementation residue or cache, not a second authoritative classification system. Downstream code and queries must use `tax_query`, `get_the_terms()`, `wp_get_object_terms()`, or equivalent WordPress taxonomy APIs. Do not query ACF post-meta values as the canonical editorial classification.

The blank format state is intentional. The eventual one-format invariant is not globally enforced until the existing corpus has been reviewed and backfilled. There is no automatic `article` assignment or inference from content or legacy categories.

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
