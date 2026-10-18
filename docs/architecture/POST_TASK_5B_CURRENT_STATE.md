# Post-Task 5B Current-State Snapshot

**Date:** 2026-10-18

**Source baseline SHA:** `c1d2af80a71b477e0eb859d3f74ea3741eab9b71`

**Runtime:** Local staging at `http://localhost:10039/`

## Canonical Person runtime

| Measure | Runtime state |
|---|---|
| ACF `person` registration | Active; public, queryable, admin-visible, REST-visible |
| Archive | `/mense/` |
| Single | `/mense/{person-slug}/` |
| Route verification | Archive and temporary single both returned HTTP 200 |
| Persistent Person records | 0 across published, draft, pending, private, future, trash, and auto-draft statuses |
| Person details group | Active; applies only to `person` |
| Person custom fields | Optional title, credentials, company, and website fields |
| Native profile fields | Title, featured image, excerpt, and content |

The only Person used was the uniquely marked temporary QA profile. Its post, metadata, and taxonomy relationships were permanently removed. No QA object remains in trash.

## `person_type`

| Measure | Runtime state |
|---|---|
| Taxonomy | Flat; admin and REST enabled; no public queries or rewrite route |
| Terms | 1 |
| Approved term | `Mentor` (`mentor`) |
| Persistent Person relationships | 0 |

The idempotent source seeder creates only the approved Mentor term. It does not assign people or import any `medewerker` relationship.

## `content_topic` and Primary Topic

| Measure | Runtime state |
|---|---|
| Terms | 55 |
| Roots | 7 |
| Supported object types | `post`, `mto-course`, `product`, `tc_events`, `medewerker`, `uitsending`, `kompetisie`, `person` |
| Persistent Person topic relationships | 0 |
| Person Primary Topic support | No |
| Person classification state | `NOT_APPLICABLE` |

The runtime QA assigned `gesondheid` temporarily and verified effective ancestor `nuwe-jy`; it then removed that relationship. A Primary assignment attempt was rejected and no Primary metadata remained.

## Editorial regression state

| Taxonomy | Terms | Relationships |
|---|---:|---:|
| `editorial_format` | 3 | 0 |
| `editorial_collection` | 0 | 0 |

The three editorial JSON definitions were moved into `site-code/acf-json/` without configuration changes. Their SHA-256 hashes match the Task 5B baseline and the active Bricks Child `acf-json` path. The editorial-dimensions standalone runner passes all 11 tests.

## Legacy protection snapshot

The runtime QA compared each record before and after its temporary Person test. All values matched:

| ID | Status | Slug | Modified | `medewerker_rol` | `medewerker_groep` | Topics / categories |
|---:|---|---|---|---|---|---|
| 753 | Published | `lynette-beer` | `2026-06-04 06:48:03` | `mentor` | `voelgoed-mentor` | None / none |
| 754 | Published | `annelize-van-der-merwe` | `2026-06-04 06:05:47` | `mentor` | `voelgoed-mentor` | None / none |
| 755 | Published | `thandi-mokoena` | `2026-06-04 06:05:47` | `mentor` | `voelgoed-mentor` | None / none |
| 756 | Published | `elna-pretorius` | `2026-06-04 06:05:47` | `mentor` | `voelgoed-mentor` | None / none |
| 757 | Published | `retha-coetzee` | `2026-06-04 06:05:47` | `mentor` | `voelgoed-mentor` | None / none |

All five migration decisions remain `DEFER`. No profile content, assignments, or relationships were copied.

| Record | Status / slug | Legacy relationships |
|---|---|---|
| Event 763 | Published / `test-event-medewerkers-qa` | `event_medewerkers`: 753, 757 |
| Product 767 | Published / `test-event-recording-qa2` | `produk_bron_event`: 763; `produk_medewerkers`: 753 |
| Product 768 | Trash / `qa4__trashed` | `produk_bron_event`: 763; `produk_medewerkers`: 753, 757 |

The event and product snapshots, including modified timestamps, were identical before and after QA. Event 763 edges and product people remain `PENDING_PROVENANCE`.

## Source and runtime verification

The Bricks Child theme's `acf-json` path points to the shared `site-code/acf-json/` directory. ACF JSON status and dry-run checks were limited to the three new Person keys, followed by individual syncs for those keys. No unrestricted ACF sync ran. The unrelated pending Member Perk field groups were left untouched.

The source-controlled Platform Core tests pass (67 tests), the editorial-dimensions tests pass (11 tests), and the Person foundation tests pass (10 tests). The deployed Core taxonomy and role files match the source-controlled versions.

## Migration authority and scope

`medewerker` remains operational at `/medewerkers/`; no redirect or Bricks/FluentSnippet migration was made. `team_member`, `team_department`, event 763, products 767/768, event participation, and other Person relationship domains remain outside Task 5B. Task 5C must resolve the five per-profile provenance decisions before any canonical Person is created from legacy data.

See [Person Foundation Implementation](PERSON_FOUNDATION_IMPLEMENTATION.md), [Post-Task 5A snapshot](POST_TASK_5A_CURRENT_STATE.md), and the [Legacy Decommission Register](LEGACY_DECOMMISSION_REGISTER.md).
