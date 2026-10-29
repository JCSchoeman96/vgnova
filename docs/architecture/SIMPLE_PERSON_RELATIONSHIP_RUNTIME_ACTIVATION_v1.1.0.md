# Simple Person relationship runtime activation v1.1.0

## Result

```text
PREVIOUS_ATTEMPT=v1.0.0_BLOCKED
ACTIVATION_SOURCE_SHA=131cce920354ba90ceb7c9c5438422b2de16a6c3
ACF_RUNTIME_SOURCE_SHA=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS
REQUEST_READ_WINDOW_CONSISTENCY=NOT_REQUIRED
RUNTIME_SYMLINK_SWITCH=NOT_REQUIRED
RUNTIME_ACTIVATION=BLOCKED
PLATFORM_CORE_DEPLOYMENT=BLOCKED
PLATFORM_CORE_RUNTIME_PARITY=BLOCKED
UNEXPLAINED_RUNTIME_DRIFT=YES
ARTICLE_ACF_GROUP=BLOCKED_NOT_RUN
COURSE_ACF_GROUP=BLOCKED_NOT_RUN
PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED
INDEX_SCHEMA=BLOCKED_NOT_RUN
INDEX_SETUP_IDEMPOTENT=BLOCKED_NOT_RUN
INDEX_REBUILD=BLOCKED_NOT_RUN
INDEX_RECONCILIATION=BLOCKED_NOT_RUN
GOVERNED_META_PREEXISTING=NOT_CHECKED
INDEX_ROWS_AFTER_REBUILD=NOT_RUN
NEW_DIRTY_SOURCES=NOT_CHECKED
PLACEHOLDER_QA=NOT_STARTED
REAL_GOLDEN_JOURNEY_DATA=NOT_STARTED
BRICKS_MUTATIONS=0
REPRESENTATIVE_DATA_MUTATIONS=0
REAL_RELATIONSHIP_ASSIGNMENTS=0
COURSE_COMMERCE_MUTATIONS=0
MEMBERSHIP_MUTATIONS=0
```

The activation stopped before any Local runtime mutation. The active Platform Core tree contains file modes that PE-008 rejects, and no approved evidence establishes those live modes. Its paths and content match the last accepted 54-file manifest, but its complete runtime fingerprint cannot be verified. No permissions were changed and no deployment was attempted.

The zero mutation counters above record commands issued by this attempt. The protected pre-mutation snapshot and post-mutation comparison did not run, so this record does not claim a verified zero-delta audit of incidental WordPress runtime state.

## Source baseline and ACF generation

The first repository operation was:

```bash
git fetch origin --prune
git rev-parse origin/main
```

It returned the expected SHA `131cce920354ba90ceb7c9c5438422b2de16a6c3`. Its first-parent history includes the required merges:

| PR | Merge commit |
|---|---|
| #61, PE-008 deployer | `bfd6c98f7375a47307311ed534f273024b949e30` |
| #62, Person relationship foundation | `febe5f79f659568e3c984273e3ea8d6113a7ad14` |
| #63, blocked activation evidence | `5a3bc4fc89fa61ae8916aa460989f1e8912b473d` |
| #64, dedicated ACF source | `131cce920354ba90ceb7c9c5438422b2de16a6c3` |

The activation worktree is detached from the normal checkout at the exact activation SHA. It is at:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/simple-person-relationship-runtime-activation-v1-1
```

The normal checkout was not cleaned, reset, stashed, or edited. Its existing `AGENTS.md` modification and untracked `.agents/` directory were left untouched.

Generation 1 remains detached and clean at:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-source
HEAD=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
```

The candidate worktree is detached and clean at:

```text
/home/jcschoeman96/projects/runtime/vgnova-acf-sources/131cce920354ba90ceb7c9c5438422b2de16a6c3
HEAD=131cce920354ba90ceb7c9c5438422b2de16a6c3
```

The runtime `acf-json` symlink still resolves to generation 1 at `/home/jcschoeman96/projects/runtime/vgnova-acf-source/site-code/acf-json`. No symlink switch occurred. A byte comparison covered all 14 relative paths in each `site-code/acf-json` tree. Both worktrees match their Git blob contents, and their complete path sets and file bytes match each other. The full inventory records each relative path, Git blob SHA, Git-content SHA-256, and filesystem SHA-256 in [the ACF parity evidence](SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.1.0_ACF_JSON_PARITY.json), whose SHA-256 is:

```text
49adec85ea60b6b1d2615394df3668027a16eb70525458040b0a9de5ec0ed1f8
```

The two source JSON contracts were read from generation 1 and matched the activation commit byte-for-byte:

| Group | Location | Field names |
|---|---|---|
| `group_vg_article_people` | `post` | `primary_author`, `additional_authors`, `expert_contributors`, `reviewed_by` |
| `group_vg_course_people` | `mto-course` | `lead_instructor`, `additional_instructors`, `experts` |

All seven fields are `post_object` selectors for `person`, return IDs, and have `required=0` and `allow_null=1`. The serialized `bidirectional` and `show_in_rest` values are null in source JSON. This confirms the source contract only. Runtime ACF definitions were not inspected because the activation stopped before that stage.

## Target identity and read-only runtime checks

The registered target `vgnova-local-10039` matches the requested Local site:

| Property | Observed value |
|---|---|
| Home and site URL | `http://localhost:10039` |
| Database and host | `local` on `localhost` |
| WordPress root | `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/` |
| MySQL socket | `/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock` |
| WordPress | `7.1.4` |
| CLI PHP | `8.5.4` |
| Local PHP-FPM | `8.5.12`, from the Local site configuration and active process |
| ACF Pro | `6.8.1`, active |
| Masteriyo | `3.4.3`, active |
| Platform Core | `0.1.0`, active |
| `mto-course` | Registered |

WordPress booted through WP-CLI with the target's Local MySQL socket. A read-only HTTP request to `http://localhost:10039/` returned status `200`. The Novamira WP-CLI runner was unavailable because its host could not find `php`; the read-only shell fallback was used. No target mismatch was found.

PE-008 read-only status returned:

```json
{"in_progress":false,"recovery_options":[]}
```

There was no unresolved deployment or in-progress journal.

## Platform Core provenance gate

The latest accepted runtime evidence in [the Product Contribution runtime report](PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_v1.0.0.md) and [its manifest](PRODUCT_CONTRIBUTION_RUNTIME_NORMALIZATION_MANIFEST_v1.0.0.json) records a 54-file Platform Core tree with content-only tree SHA-256:

```text
a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
```

That accepted hash covers relative paths and file content hashes. It does not cover filesystem permissions. No later approved Platform Core deployment was found. The later Product Contribution runtime evidence reports `SKIPPED_ALREADY_EXACT` and zero deployments. The PR #64 ACF source cutover did not deploy Platform Core.

The current runtime root is:

```text
/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core
```

| Property | Current observation |
|---|---|
| Files | 54 |
| File modes | 53 files at `0664`; `bin/check.sh` at `0775` |
| Directories | 16, all at `0775` |
| Root mode | `0755` |
| Root owner/group | `jcschoeman96:jcschoeman96` (`1000:1000`) |
| Entrypoint | Regular file at the expected path; realpath remains inside the runtime tree; mode `0664` |
| Accepted content comparison | All file paths, sizes, and content hashes match the accepted manifest, as independently reviewed |
| Current PE-008 tree SHA-256 | Not computable; fingerprint rejected an unsupported mode |

PE-008's `fingerprint_tree()` accepts file modes `0644` and `0755`. It stopped at:

```text
DeploymentError: Runtime tree contains an unsupported file mode.
```

The accepted runtime content manifest does not record or authorize the current `0664` and `0775` permissions. Therefore `UNEXPLAINED_RUNTIME_DRIFT=YES` for runtime permission metadata. Although the content hashes match, full Platform Core runtime parity is blocked. This is the Stage 1 hard stop. No `chmod`, deployment, activation artifact build, or other Local change was made.

The last accepted runtime content fingerprint remains the prior value above. The current full fingerprint is unknown; this record does not substitute a content-only digest for PE-008's runtime fingerprint.

## Stages not run

The activation stopped before collision scanning and schema inspection. The following checks were not run:

- Governed Person relationship values or ACF reference metadata scan.
- Person relationship index table, schema-version option, dirty-marker, or row inspection.
- Protected pre-mutation snapshots for posts, statuses, commerce, memberships, Bricks, `content_topic`, Event Participation, Product Contribution, or pending ACF JSON.
- The activation artifact build in the requested output directory and its separate exact-output verification.
- PE-008 deployment, health checks, runtime parity, history, or rollback capture.
- Post-deployment auto-install check for the Person schema.
- ACF JSON status, targeted dry-runs, targeted syncs, or runtime ACF contract verification.
- Schema setup, idempotency check, index rebuild, dirty-state check, or read-only reconciliation.
- Protected post-mutation comparison.

No governed Person relationship values were read, adopted, changed, or assigned. No ACF sync, schema/index operation, Bricks edit, representative-data change, commerce operation, or membership operation was issued. Because the protected snapshot did not run, the governed metadata and index counts remain `NOT_CHECKED`, not zero.

## Review and verification

A fresh independent preflight reviewer confirmed that the current 54 files match the accepted content manifest but that PE-008 rejects 53 files at mode `0664` and one file at `0775`. The accepted manifest does not establish those modes. The reviewer recommended stopping before any Local mutation. No runtime permissions or files were changed during review.

`./site-code/bin/check-source.sh` exited `0`. It ran under CPython `3.14.4`, passed its source checks and negative cases, and created two reproducible temporary verification builds. Each had 66 files, tree SHA-256 `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373`, archive SHA-256 `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9`, and manifest SHA-256 `e722934a9fd51788d3dc11974d2b4682fead03cf69d772ee12ba882e627e55d7`. These were source-gate verification outputs. The activation artifact directory `/tmp/vgnova-person-runtime-131cce920354ba90ceb7c9c5438422b2de16a6c3` was not created and no artifact was passed to PE-008.

`git diff --check` passed for the staged evidence files. The protected pre/post comparison remains `NOT_RUN` because the activation stopped before the required pre-mutation snapshot.

The evidence-only branch contains this blocked preflight record and the ACF JSON parity inventory. It does not claim Phase 1 activation passed. Resolve and approve the runtime permission provenance, then restart the full preflight before attempting deployment.
