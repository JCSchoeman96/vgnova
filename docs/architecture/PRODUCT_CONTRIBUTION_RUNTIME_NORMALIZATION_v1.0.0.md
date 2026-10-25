# Product Contribution Runtime Normalization v1.0.0

## Result

```text
TASK_8B=PASS
```

Task 8B replaced the active Platform Core directory with a deterministic plugin built from the authorized source baseline. The active runtime matches the staged target by file path, size, and SHA-256. WordPress loaded the plugin successfully, and the protected state checks remained unchanged.

No ACF sync, Product Role seeding, Product Contribution QA fixtures, Task 8 runtime QA, or Task 9 work was performed.

## Execution baseline

- Authorized and freshly fetched `origin/main`: `e27a55c33b0393273c363b8fdcbfb65d3d1671ea`.
- No intervening commits were present at execution or final fetch.
- Worktree: `ops/product-contribution-runtime-normalization`, created clean from that baseline.
- Historical Task 8A findings remain `PROVENANCE=PARTIAL`, `old runtime baseline candidate=NO`, and `ongoing automatic writer=UNKNOWN`.
- This report records a new controlled runtime state. It does not change the Task 8A findings.

## Runtime identity

Identity was verified through the VGNova Novamira connection before replacement and after WordPress bootstrap:

| Property | Verified value |
|---|---|
| WordPress root | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/` |
| Home and site URL | `http://localhost:10039/` |
| Effective port | `10039` |
| Database | `local` on `localhost` |
| PHP | `8.5.12` |
| WordPress | `7.1.3` |
| ACF Pro | `6.8.1` |
| WooCommerce | `11.2.1` |
| Platform Core | Active at `wp-content/plugins/voelgoed-platform-core`, version `0.1.0` |

`localhost:10049` was not accessed.

## Source gate

The source gate and all five focused regressions passed before runtime writes and again after deployment. Before and after results were exit code `0` for every command.

| Command | Before | After |
|---|---:|---:|
| `bash site-code/bin/check-source.sh` | PASS | PASS |
| Product Contribution | 142 passed | 142 passed |
| Product People | 101 passed | 101 passed |
| Event Participation | 68 passed | 68 passed |
| Event Participation Bricks | 12 passed | 12 passed |
| Event Role Seeder | 13 passed | 13 passed |

The source gate also passed its Person Foundation (10), Event Participation schema (3), editorial dimension seeder (11), Task 6C QA ownership (12/12), QA cleanup (3/3), QA setup (1/1), and PHP lint checks.

## Pre-normalization runtime

The fresh predeployment scan reproduced the Task 8A Platform Core tree:

| Property | Value |
|---|---|
| Files | 81 |
| Tree SHA-256 | `fcc9839f165341c92745a426d246536d300e2a052647d4983ce8cb37b7f4560e` |
| `voelgoed-platform-core.php` | `7a1b25a840294b029aba48d24e076adfe551ea9dcefc6b983525e7a82e06736d` |
| `includes/Plugin.php` | `c50ad78ac7fe32cafe6c3476b9d61e68214047d69c255637d957da013e09f2f8` |
| `PersonProductsBricks.php` | Absent |

The complete old tree manifest is included in the JSON evidence file.

## Staged target

The target was built in a new staging directory from the tracked root `voelgoed-platform-core/` at the authorized worktree `HEAD`. The current `integration.patch` was checked, applied once, and followed by an overlay of the production modules under `site-code/voelgoed-platform-core/includes/`. No runtime files were used as build inputs, and source-only documentation was not copied.

| Property | Value |
|---|---|
| `integration.patch` SHA-256 | `7230916850225f7ffb1478cc180f8f42b32934505358ac437d771507ac3ba8a2` |
| Staged files | 54 |
| Staged tree SHA-256 | `a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760` |
| Staged PHP files linted | 53; all clean |
| `voelgoed-platform-core.php` | `3fc688ccf27ec11ee4bb9fe5e282630ded724d4a987e5489f9bc77142dca72bb` |
| `includes/Plugin.php` | `054b373bc4512cadf7f4e9700347867dc90ca359527770b0ecf3a3a825512625` |
| `PersonProductsBricks.php` | `a7460a1e21d4318a9d3997e52ad9e0d804a155798b1b7992e786b4cb976170e5` |
| Entrypoint header | `Requires PHP: 8.5` |

The staged tree contains all requested Product Contribution, Product Role, Product People, Person Products Bricks, Product Source Event Bricks, and Event Participation classes. The generated entrypoint and `Plugin.php` satisfy the Task 7 bootstrap and registration contract.

## Runtime delta

| Classification | Count | Explanation |
|---|---:|---|
| ADD | 1 | `includes/Product/PersonProductsBricks.php`, present in current source and absent from the old runtime |
| REPLACE | 3 | Duplicate-patched entrypoint; stale `includes/Plugin.php`; stale runtime `tests/bootstrap.php` |
| REMOVE | 28 | Test files and test runners found under source-only `site-code/voelgoed-platform-core/tests/`, outside the canonical production target |
| UNCHANGED | 50 | Byte-identical files |
| Unknown production differences | 0 | No unexplained runtime-only production file was found |

The entrypoint replacement removes the duplicate integration patch and sets the PHP requirement to 8.5. The `Plugin.php` replacement restores current source wiring. The test bootstrap replacement uses the tracked root plugin's canonical test bootstrap. The 28 removals are source-only test residue and are not production dependencies.

Removed paths:

```text
tests/ContentTopicMigrationTest.php
tests/ContentTopicRolesTest.php
tests/ContentTopicTaxonomyTest.php
tests/EventParticipationAdminTest.php
tests/EventParticipationBricksTest.php
tests/EventParticipationRepositoryTest.php
tests/EventParticipationServiceTest.php
tests/EventRoleSeederTest.php
tests/ProductContributionAdminTest.php
tests/ProductContributionPairLockTest.php
tests/ProductContributionRepositoryTest.php
tests/ProductContributionSchemaTest.php
tests/ProductContributionServiceTest.php
tests/ProductPeopleBricksTest.php
tests/ProductPeopleFieldsTest.php
tests/ProductPeopleIntegrationTest.php
tests/ProductPeopleQaSideEffectsTest.php
tests/ProductPeopleResolverTest.php
tests/ProductRoleSeederTest.php
tests/ProductRoleVocabularyTest.php
tests/ProductSingle124QaRequestTest.php
tests/ProductSourceEventBricksTest.php
tests/run-content-topic-tests.php
tests/run-event-participation-bricks-tests.php
tests/run-event-participation-tests.php
tests/run-event-role-seeder-tests.php
tests/run-product-contribution-tests.php
tests/run-product-people-tests.php
```

The JSON manifest includes every old and target file path, size, and SHA-256, plus the classified path delta.

## Rollback and deployment

Before replacement, the old plugin directory was renamed to this same-filesystem location outside `wp-content/plugins/`:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/.task8b-platform-core-backup-20261024T095612Z
```

The rollback directory remains in place. Its 81 files reproduce the old tree hash exactly. Its root mode is `0755`, owner/group are `1000:1000`, and it contains no symlinks.

After device IDs and manifests were checked, a guarded same-filesystem rename moved the old directory to the rollback path and the verified staged tree into the active plugin path. The failure handler was prepared before the first rename and would restore from the verified rollback copy if installation failed. Replacement succeeded; no individual runtime file was edited afterward.

## Runtime parity and WordPress bootstrap

The immediate post-replacement check and final post-verification rehash both found:

```text
missing=0
extra=0
mismatched=0
runtime files=54
runtime tree SHA-256=a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
exact parity=yes
```

The final rehash ran after the WordPress bootstrap and source regressions. The active `PersonProductsBricks.php`, entrypoint, and `Plugin.php` match the target manifest. `Requires PHP: 8.5` is present.

The VGNova WordPress bootstrap completed with no captured PHP warning, notice, deprecation, fatal, uncaught exception, or output. Platform Core remained active. Runtime class checks confirmed that these classes load from the active plugin directory: `ProductContributionRepository`, `ProductContributionPairLock`, `ProductContributionService`, `ProductContributionAdmin`, `ProductRoleSeeder`, `ProductPeopleResolver`, `PersonProductsBricks`, `EventParticipationRepository`, `ProductRoleVocabulary`, `ProductPeopleBricks`, and `ProductSourceEventBricks`.

The WP-CLI registry could not be verified in this environment. The Novamira `run-wp-cli` attempt stopped because its host could not find the `php` executable. The local WP-CLI fallback could not connect to the site database and therefore could not complete WordPress plugin bootstrap. The read-only registry check did not run `vg-product-role-setup`; the command was not executed. Source still contains the guarded `WP_CLI::add_command('vg-product-role-setup', ...)` registration in `ProductRoleSeeder`.

## Protected and persistent state

Postdeployment fingerprints matched the predeployment values for Profiles 753–757, Event 763, Products 765/767/768, Templates 773/1127, and Bricks component `6b463f`. FluentSnippets 9 and 10 also retained their predeployment hashes.

| Protected surface | Postdeployment evidence |
|---|---|
| Profiles 753–757 | 5 posts, 34 postmeta rows, 10 term relationships; all three digests match predeployment |
| Event 763 | 1 post, 15 postmeta rows, 0 term relationships; all digests match |
| Products 765/767/768 | 3 posts, 20 postmeta rows, 0 term relationships; all digests match |
| Templates 773/1127 | 2 posts, 11 postmeta rows, 0 term relationships; all digests match |
| Bricks component `6b463f` | Option ID 13809, row SHA-256 `55beb9ae2508c235362bc850d6f11954f0634d1bace3d8a59e3869c750b7d004`, component SHA-256 `57370bbb83d3125758eba9a5194eb1a898b8914b546ee4d84b32f642e07aeaac` |
| FluentSnippets 9 | SHA-256 `58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70` |
| FluentSnippets 10 | SHA-256 `e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2` |
| Event Participation schema | Group ID 1263, key `group_vg_event_participation_details`, four fields; field keys, names, labels, types, required flags, and source JSON match the predeployment schema |

Persistent state remained at zero: Product Contributions, Persons, Event Participations, Product Role terms, Product Role relationships, target Product Contribution ACF DB definitions, and rows for `product_people_override` / `_product_people_override`. No ACF sync or Product Role seeder execution occurred. No posts, terms, relationships, templates, Bricks components, snippets, or rewrite rules were changed by Task 8B.

The whole `wp_options` table was not used as a gate. The protected Bricks option row was fingerprinted directly; volatile option equality was not measured.

## Drift and source regression

The final active runtime rehash exactly matched the staged target after all bootstrap and verification work:

```text
UNEXPLAINED_RUNTIME_DRIFT_DURING_TASK_8B=NO
ONGOING_AUTOMATIC_WRITER=UNKNOWN
```

Postdeployment source commands and exact focused results:

| Command | Result |
|---|---|
| `bash site-code/bin/check-source.sh` | exit 0; source gate and PHP lint checks passed |
| `php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php` | exit 0; 142 passed |
| `php site-code/voelgoed-platform-core/tests/run-product-people-tests.php` | exit 0; 101 passed |
| `php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php` | exit 0; 68 passed |
| `php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php` | exit 0; 12 passed |
| `php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php` | exit 0; 13 passed |

## Evidence and scope

- Machine-readable file and state evidence: [`PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_MANIFEST_v1.0.0.json`](PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_MANIFEST_v1.0.0.json).
- Task 8A provenance audit was not modified.
- The final unversioned `PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST.json` was not created during this normalization task. The later Task 8C record is versioned as `PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json`.
- Task 8 runtime QA and Task 9 remain not started.
