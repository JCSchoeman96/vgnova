# Simple Person relationship runtime activation v1.2.0

## Result

```text
PREVIOUS_ATTEMPT_V1_0_0=BLOCKED_ACF_SOURCE_GATE
PREVIOUS_ATTEMPT_V1_1_0=BLOCKED_RUNTIME_PERMISSION_PROVENANCE

ACTIVATION_SOURCE_SHA=4b7a30a1a93a8b0d9707ddeec7eeb7462ce31439
ACF_RUNTIME_SOURCE_SHA=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS
REQUEST_READ_WINDOW_CONSISTENCY=NOT_REQUIRED
RUNTIME_SYMLINK_SWITCH=NOT_REQUIRED

PRE_DEPLOY_RUNTIME_PE008_TREE_SHA256=856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56

RUNTIME_ACTIVATION=BLOCKED
PLATFORM_CORE_DEPLOYMENT=BLOCKED
PLATFORM_CORE_RUNTIME_PARITY=BLOCKED
ARTICLE_ACF_GROUP=BLOCKED
COURSE_ACF_GROUP=BLOCKED
PERSON_SCHEMA_AUTO_INSTALL=NO
INDEX_SCHEMA=BLOCKED
INDEX_SETUP_IDEMPOTENT=BLOCKED
INDEX_REBUILD=BLOCKED
INDEX_RECONCILIATION=BLOCKED
GOVERNED_META_PREEXISTING=0
INDEX_ROWS_AFTER_REBUILD=NOT_RUN
NEW_DIRTY_SOURCES=0

PLACEHOLDER_QA=NOT_STARTED
REAL_GOLDEN_JOURNEY_DATA=NOT_STARTED
BRICKS_MUTATIONS=0
REPRESENTATIVE_DATA_MUTATIONS=0
REAL_RELATIONSHIP_ASSIGNMENTS=0
COURSE_COMMERCE_MUTATIONS=0
MEMBERSHIP_MUTATIONS=0
```

The restart passed source, ACF, target, protected pre-state, runtime-provenance, and artifact gates. The PE-008 deployment attempt then stopped during its read-only PE-007 session health check, before creating a deployment journal or staging files. No deployment write, file exchange, ACF sync, schema/index operation, or other planned application mutation occurred. The protected pre/post snapshots match. The issue is in the health-helper PHP/database transport path; per the approved plan, activation stopped without a workaround or source edit.

`PERSON_SCHEMA_AUTO_INSTALL=NO` records that no table or schema-version option appeared in the unchanged pre/post snapshots; deployment did not occur, so deploy-time auto-install behavior was not exercised. `INDEX_ROWS_AFTER_REBUILD=NOT_RUN`; the table was absent and no rebuild ran.

## Source baseline and worktree

The first repository commands were:

```bash
git fetch origin --prune
git rev-parse origin/main
```

`origin/main` was exactly `4b7a30a1a93a8b0d9707ddeec7eeb7462ce31439`. The activation worktree is at that commit on `ops/simple-person-relationship-runtime-activation-v1-2`:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/simple-person-relationship-runtime-activation-v1-2
```

The worktree was clean before the run. The required merge commits were ancestors of the activation SHA:

| PR | Merge commit |
|---|---|
| #61, PE-008 deployer | `bfd6c98f7375a47307311ed534f273024b949e30` |
| #62, Person relationship foundation | `febe5f79f659568e3c984273e3ea8d6113a7ad14` |
| #63, blocked activation evidence | `5a3bc4fc89fa61ae8916aa460989f1e8912b473d` |
| #64, dedicated ACF runtime source | `131cce920354ba90ceb7c9c5438422b2de16a6c3` |
| #65, runtime mutation coordination | `3e226294e8f72a859e50e5b9a8deb3023ec3ed6a` |
| #66, merged legacy-permission normalization | `4b7a30a1a93a8b0d9707ddeec7eeb7462ce31439` |

The ordinary checkout's pre-existing `AGENTS.md` modification and untracked `.agents/` directory were left untouched. No Platform Core source was edited during this activation attempt.

## Immutable ACF generation and parity

Generation 1 remains detached and clean at:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-source
HEAD=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
```

The requested detached activation candidate was absent before creation and is detached and clean at:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-sources/4b7a30a1a93a8b0d9707ddeec7eeb7462ce31439
HEAD=4b7a30a1a93a8b0d9707ddeec7eeb7462ce31439
```

The complete 14-file `site-code/acf-json` path and byte inventories match. Every file in both trees matches its exact Git blob. Per-file paths, blob IDs, SHA-256 values, and byte counts are in [the ACF parity inventory](SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.2.0_ACF_JSON_PARITY.json). The active runtime symlink still resolves to generation 1:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/themes/bricks-child/acf-json
-> /home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json
```

No ACF generation switch occurred.

## Target and pre-mutation state

The target was `vgnova-local-10039`, WordPress root `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/`. Explicit-socket WP-CLI verified `http://localhost:10039`, database `local`, WordPress `7.1.4`, CLI PHP `8.5.4`, active ACF Pro `6.8.1`, active Masteriyo `3.4.3`, and active Platform Core `0.1.0`. Local PHP-FPM was `8.5.12`.

PE-008 status before the attempt was `in_progress=false` with no recovery options. The active Platform Core tree passed every accepted provenance check:

| Property | Pre-deploy value |
|---|---|
| File count | 54 |
| Content tree SHA-256 | `a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760` |
| PE-008 tree SHA-256 | `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56` |
| Mode pattern | `53x0644,1x0755,16dirsx0755,root0755` |
| Owner/group | `1000:1000` throughout |
| Symlinks / unsupported objects | `0 / 0` |

The governed Person relationship values and seven ACF reference keys had zero rows; their empty-set SHA-256 was `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`. Dirty markers had zero rows with the same empty-set hash. The Person index table `wp_vg_person_relationship_index` was absent, and the schema-version option had no row.

The deterministic protected pre/post snapshots cover editorial content, people, courses, commerce, events, Bricks metadata, `content_topic`, PMPro, relationship metadata, and index state. Their canonical JSON hashes match at `22ad2b4e17638d3ce9aad0dde97b589dd7ceb52ad5d9da25087042116ced1ef8` after excluding only `captured_at_utc`. The full snapshots and comparison are in [the supporting evidence JSON](SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.2.0_DETAILS.json).

## Artifact and PE-008 attempt

The artifact was built from the exact activation SHA with CPython `3.14.4` into a new directory and passed exact-output verification:

```text
file_count=66
tree_sha256=d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373
archive_sha256=3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9
manifest_sha256=6604f328a86c4da493e3e104934cbfdf244067f717145dc9d75f562f3eae6109
source_commit=4b7a30a1a93a8b0d9707ddeec7eeb7462ce31439
exact_output_verification=PASS
```

Immediately before deployment, the full runtime fingerprint, PE-008 status, PE-007 locks, source worktree, ACF generations, and artifact hashes were rechecked. The PE-008 deploy command was then run inside PE-007. PE-007 acquired and released its host and database locks; its child exited with status `1`:

```text
Platform Core deployment failed: WordPress health helper output is not one JSON result.
```

The helper's read-only `session` and `health` modes were each reproduced under PE-007. Both returned code `0`, empty stderr, and the same 2,482-byte WordPress “Error establishing a database connection” HTML response instead of JSON. The helper uses `/usr/bin/php` 8.5.4, whose `mysqli.default_socket` is `/var/run/mysqld/mysqld.sock`; the verified Local target socket is `/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock`. The health helper in `site-code/runtime-tools/platform_core_deployment.py` does not pass the coordinator's `--db-socket` to PHP. The PE-007 child environment allowlist in `site-code/runtime-tools/runtime-mutation-core.php` also excludes `PHPRC` and `PHP_INI_SCAN_DIR`, so those shell overrides do not reach the deployer. This mismatch is the cause of the strict JSON health-check failure.

PE-008 status after the failed attempt remained `in_progress=false` with no recovery options. No deployment ID, history record, or rollback path was created. The post-attempt Platform Core fingerprint is byte-for-byte and mode-for-mode equal to the pre-deploy fingerprint above. The active runtime does not match the 66-file activation artifact because deployment did not take place; no deployment parity is claimed.

No socket shim, environment workaround, Local hot-patch, or source edit was attempted. The plan requires stopping on a source defect. Any source correction must follow the approved test, review, merge, and fresh Stage 1 restart process before another activation attempt.

## ACF state and activation stages not run

The detailed ACF pending inventory was unchanged before and after the failed attempt:

| ACF group | Before | After |
|---|---|---|
| `group_vg_article_people` | Create | Create |
| `group_vg_course_people` | Create | Create |
| `group_vgmp_perk_details` | Create | Create |
| `group_vgmp_options` | Create | Create |

No ACF group was synchronized. The two Member Perks groups remain pending and untouched. Generation 1 and the activation candidate remain clean; the runtime symlink still points to generation 1.

The following post-deployment stages were blocked and not run: Person class/CLI checks, auto-install verification, Article/Course dry-runs and sync, Person schema setup and idempotency, relationship-source rehash, index rebuild, reconciliation, and any placeholder QA. The index table remains absent, so no index rows were present; no rebuild result is claimed. No dirty sources were introduced. No Person, Article, or Course placeholder was created, and no real Person relationship was assigned.

## Repository verification

`./site-code/bin/check-source.sh` exited `0`. Its reported unit and policy suites passed, PHP syntax scans passed, and the exact-commit artifact verifier accepted the reproducibility and negative-case checks. The two checker builds matched at 66 files with tree SHA-256 `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373` and archive SHA-256 `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9`. These were checker verification builds; the activation artifact's manifest hash remains the exact-SHA build value recorded above.

## Required scope exclusions

```text
PLACEHOLDER_QA=NOT_STARTED
REAL_GOLDEN_JOURNEY_DATA=NOT_STARTED
BRICKS_MUTATIONS=0
REPRESENTATIVE_DATA_MUTATIONS=0
REAL_RELATIONSHIP_ASSIGNMENTS=0
COURSE_COMMERCE_MUTATIONS=0
MEMBERSHIP_MUTATIONS=0
```

This record documents the blocked v1.2.0 attempt. It does not start Phase 2. The evidence-only review PR number and head are reported in the activation handback.
