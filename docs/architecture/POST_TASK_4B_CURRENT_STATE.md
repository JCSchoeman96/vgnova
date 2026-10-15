# Post Task 4B Current State

**Captured:** 2026-10-15
**Runtime:** `localhost:10039`
**WordPress:** 7.1.3
**ACF Pro:** 6.8.1

## Source/runtime ownership

ACF Pro owns the two taxonomy definitions and the `Editorial Classification` field group. The Git source is the repository-backed ACF Local JSON directory:

```text
site-code/editorial-dimensions/acf-json/
```

The active theme path `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json` is symlinked to that directory. Novamira reported the ACF records as database-sourced (`source: db`). ACF API payloads were compared to the tracked JSON: taxonomy registration properties match; field group location and source-defined field properties match. ACF's generated record IDs/timestamps and runtime-derived field properties (`prefix`, `value`, `menu_order`, `id`, `class`, `parent`, `_name`, `_valid`) are not JSON configuration properties.

Tracked implementation files:

- `site-code/editorial-dimensions/acf-json/taxonomy_editorial_format.json`
- `site-code/editorial-dimensions/acf-json/taxonomy_editorial_collection.json`
- `site-code/editorial-dimensions/acf-json/group_vg_editorial_classification.json`
- `site-code/editorial-dimensions/seed-editorial-format-terms.php`
- `site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php`

## Live taxonomy state

| Property | `editorial_format` | `editorial_collection` |
|---|---|---|
| Registered | Yes | Yes |
| Object types | `post` only | `post` only |
| Hierarchical | No | No |
| Public | No | No |
| Publicly queryable | No | No |
| Rewrite | Disabled (`false`) | Disabled (`false`) |
| Query variable | Disabled (`false`) | Disabled (`false`) |
| Admin term UI | Available | Available |
| Navigation menus / tag cloud | Disabled | Disabled |
| Native taxonomy meta box | Disabled | Disabled |
| ACF post-editor control | Single-select radio | Optional multi-select |
| Required | No | No |
| Inline term creation | Disabled | Disabled |
| Terms | `article`, `video`, `audio` | None |
| Relationships | 0 | 0 |

The format term labels are `Artikel`, `Video`, and `Oudio`, respectively. The collection term count is zero. No default format exists. Both ACF controls load and save taxonomy terms.

The ACF field group is active on standard posts only. Browser inspection showed one `Editorial Classification` area, three single-select format choices, an empty multi-select collection control, no inline term creation controls, and no duplicate native taxonomy panels. Browser save with no format selected succeeded. The QA assignment check confirmed selecting `video` replaced `article`.

## Routing and query behavior

The post permalink structure remains `/content/%postname%/`. Neither taxonomy has public term pages, a rewrite base, or a matching rewrite rule. WordPress taxonomy queries completed without error; they returned no posts because the final relationship count is zero. Bricks can query registered post taxonomies through its WordPress taxonomy-query path; no visitor-facing query UI was added.

Both taxonomies attach to `post` and do not attach to `product`, `mto-course`, `tc_events`, `medewerker`, `uitsending`, or `kompetisie`. Core `post_format` remains registered and was not changed.

## Existing taxonomy counts

| Taxonomy | Terms | Relationships | Objects |
|---|---:|---:|---:|
| `editorial_format` | 3 | 0 | 0 |
| `editorial_collection` | 0 | 0 | 0 |
| `content_topic` | 55 | 7 | 7 |
| `content_category` | 13 | 8 | 7 |

The existing topic and legacy category counts and object sets match the pre-task runtime snapshot. The editorial relationship counts are zero after QA cleanup; no existing content was classified.

## Seeding and QA cleanup

The format seeder created `article`, `video`, and `audio` on its first run. A second run created nothing and recognized all three existing terms. It creates no relationships and does not seed collections.

Temporary QA draft post `1222` was used to verify blank save, single format assignment, replacement on format switch, and empty collection behavior. Its format relationship was removed and the draft was permanently deleted. Final verification confirmed the post is absent, no collection term exists, and both editorial taxonomies have zero relationships.

## Scope status

- `content_topic`: unchanged.
- `content_category`: unchanged and not ready for removal.
- Commercial classifications, PMPro/access, URLs, Bricks templates, and other entity models: unchanged.
- Editorial flags and campaign context: not implemented.
- Task 4C: not started.
