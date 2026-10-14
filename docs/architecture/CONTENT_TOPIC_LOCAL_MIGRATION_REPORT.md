# Content topic local migration report

Status: Task 2 proof complete
Site: `vgnova` at `http://localhost:10039/`
Date: 2026-10-14

## Architecture sources

The owner supplied three local architecture documents. Their copied versions
are committed under `repo/`. The first hash is the owner's original local file
hash before the Task 1B synchronization. The final hash is the committed file
hash after synchronization.

| File | Title and version | Owner copy SHA-256 | Final `repo/` SHA-256 |
|---|---|---|---|
| `repo/VOELGOED_CANONICAL_INFORMATION_ARCHITECTURE_v0.1.md` | Voelgoed Canonical Information Architecture & Content Model, v0.1 | `e291a6b9d98564de0045030a0810f74c7db67f063e22aee8b187453593d583a5` | `156ab8e90d05b0d420d44c78246edb2646c4afa1af3ce2866e4334b2137cb2fb` |
| `repo/VOELGOED_CANONICAL_TOPIC_TAXONOMY_MIGRATION_v0.1.md` | Voelgoed Canonical Topic Taxonomy & Legacy Migration Framework, v0.1 | `7a45f194edb6912d265a6388929b5fdd7db1e8f81f588cc966932c6dd6384036` | `dfcde35dddff98705f534906fcb2c2998835d6e3bfd24286a1ce2fae6e814c85` |
| `repo/VOELGOED_TAXONOMY_DISCOVERY_UX_v0.1.md` | Voelgoed Taxonomy, Discovery & User-Facing Information Architecture, v0.1 | `6d94a64a5940bd60f3d5df041ccba4037344443197ed2a2a725a65e3d2d46216` | `cb56efc9bfea54745444d8adab5ff29017aa35b583b5839eaceffa57b6d00b2c` |

The approved Task 1B synchronization was required. The taxonomy and discovery
documents named Vrouegesondheid as a canonical topic. Their committed copies
remove that term. The canonical count is 55, there are seven roots, and
Gesondheid remains directly under Nuwe Jy. Trailing whitespace from the owner
copies was also removed so the branch passes `git diff --check`. No other
wording or architecture content changed.

## Baseline snapshot

The read-only Novamira snapshot before implementation and apply found:

```text
content_topic: 55 terms, 7 roots, 0 relationships
content_category: 13 terms, 8 relationships across 7 objects
```

The 13 legacy slugs were `aanlyn-onderwys`, `doen-goed`, `finansies`,
`geloof`, `huwelik`, `leefstyl`, `mensstories`, `natuurlik-gesond`, `nuwe-jy`,
`ouerskap`, `self`, `vakansie`, and `werk-geld`.

The exact legacy relationships were:

| Object | Type | Legacy slug |
|---:|---|---|
| 514 | post | `nuwe-jy` |
| 516 | post | `geloof` |
| 518 | post | `ouerskap` |
| 519 | post | `werk-geld` |
| 521 | post | `mensstories` |
| 523 | product | `natuurlik-gesond` |
| 523 | product | `nuwe-jy` |
| 641 | post | `self` |

`content_topic` remained attached to `kompetisie`, `medewerker`, `mto-course`,
`post`, `product`, `tc_events`, and `uitsending`. `content_category` remained
attached to `kompetisie`, `kompetisie_entry`, `medewerker`, `mto-course`,
`post`, `product`, `tc_events`, `team_member`, `testimonial`, and `uitsending`.

The runtime `ContentTopicTaxonomy.php` matched the source file before the
migration. Both SHA-256 values were
`8b107985c34f1989a73c74ea6e06862fcfb02796f6007c22c982e5256c43befe`.

## Classification

All 13 terms have explicit actions in
[`CONTENT_CATEGORY_TO_TOPIC_LOCAL_MAPPING.md`](CONTENT_CATEGORY_TO_TOPIC_LOCAL_MAPPING.md).
The seven semantic pillars map to their same-slug canonical roots. The six
Leefstyl and commercial vertical terms use `SKIP_COMMERCIAL_CONTEXT`.

## Initial dry run

The dry run passed the exact local manifest gate. Its source fingerprint was:

```text
80af0a1e03c7712ad5b3e7c95ad23cc1e570d4c6ab907bc73f2edd7da7768847
```

| ID | Type | Title | Legacy terms | Canonical topics | Skipped commercial terms | Action | Review |
|---:|---|---|---|---|---|---|---|
| 514 | post | Jou oggend-routine vir meer rustigheid | `nuwe-jy` | `nuwe-jy` | None | `DIRECT_MAP` | No |
| 516 | post | Geloof wat jou werkweek dra | `geloof` | `geloof` | None | `DIRECT_MAP` | No |
| 518 | post | Ouerskap sonder perfeksie | `ouerskap` | `ouerskap` | None | `DIRECT_MAP` | No |
| 519 | post | Geldwenke vir enkelma's | `werk-geld` | `werk-geld` | None | `DIRECT_MAP` | No |
| 521 | post | Herstel na verlies: 'n mensstory | `mensstories` | `mensstories` | None | `DIRECT_MAP` | No |
| 523 | product | Voelgoed Welstand Pakket | `nuwe-jy`, `natuurlik-gesond` | `nuwe-jy` | `natuurlik-gesond` | `MIGRATE_WITH_COMMERCIAL_SKIP` | No |
| 641 | post | QA Self Featured Content Test | `self` | `self` | None | `DIRECT_MAP` | No |

| Dry-run count | Result |
|---|---:|
| Objects inspected | 7 |
| Legacy relationships represented | 8 |
| Canonical relationships to add | 7 |
| Commercial relationships skipped | 1 |
| Review-required objects | 0 |
| Legacy relationships to delete | 0 |
| Canonical relationships to delete | 0 |

## Apply, idempotency, and rollback proof

The migration command ran the requested sequence through Novamira on the local
site:

| Stage | Result |
|---|---|
| Initial dry run | 7 additions, 0 review-required, 0 deletions; fingerprint recorded above |
| First apply | Created 7 canonical relationships and saved the versioned migration record |
| Second apply | Found 7 relationships already present and created 0 |
| Rollback | Removed the 7 relationships recorded as migration-created |
| Final apply | Recreated the 7 canonical relationships and left the migration record active |

The final run record contains these created rows:

| Object | Canonical slug | WordPress term ID |
|---:|---|---:|
| 514 | `nuwe-jy` | 349 |
| 516 | `geloof` | 363 |
| 518 | `ouerskap` | 388 |
| 519 | `werk-geld` | 377 |
| 521 | `mensstories` | 404 |
| 523 | `nuwe-jy` | 349 |
| 641 | `self` | 370 |

A final default dry run reports 7 canonical relationships already present and
0 relationships to add. The record stores no sensitive user data.

## Final live state

The final read-only Novamira assertions found:

```text
content_topic: 55 terms, 7 roots, 7 relationships across 7 objects
content_category: 13 terms, 8 relationships across 7 objects
```

Product 523 has `nuwe-jy` as its only `content_topic`. Its legacy terms remain
`nuwe-jy` and `natuurlik-gesond`. `natuurlik-gesond` was not migrated as a
topic. Leefstyl and Vrouegesondheid are absent from `content_topic`.

## Existing-data impact

| Area | Changed? |
|---|---|
| Legacy terms | No |
| Legacy assignments | No |
| Legacy taxonomy attachments | No |
| Templates | No |
| URLs | No |
| Vennoot mappings | No |
| HappyFiles | No |
| `content_topic` relationships | Yes, 7 added |

The legacy front end continues to use `content_category`. Task 2 added no
synchronization and changed no Bricks, merchandising, partner, or HappyFiles
behavior. The production legacy corpus remains untouched, and Task 3 has not
started.

## Verification

| Check | Result |
|---|---|
| Focused content topic tests | 21 passed, 0 failed |
| Platform Core dashboard tests | 127 passed, 0 failed |
| PHP lint | Passed for all Platform Core PHP files |
| `git diff --check` | Passed |
| Source/runtime `ContentTopicTaxonomy.php` parity | Matched, SHA-256 `8b107985c34f1989a73c74ea6e06862fcfb02796f6007c22c982e5256c43befe` |
| Source/runtime `ContentTopicMigration.php` parity | Matched, SHA-256 `0a47cfdff420cda2a12e66273886c260a0c03a9efbad90314576c8bcc9c06232` |
