# Simple Person relationship runtime activation v1.0.0

## Result

```text
ACTIVATION_SOURCE_SHA=bfd6c98f7375a47307311ed534f273024b949e30
RUNTIME_ACTIVATION=BLOCKED
STOP_POINT=Stage 1, Step 5: ACF Local JSON source gate
```

The activation stopped before any Local runtime mutation. The active ACF JSON symlink resolves into the normal `vgnova` checkout, and that checkout has uncommitted changes. The Phase 1 instructions require a stop in this state. The symlink was not changed.

## Required result fields

```text
PLATFORM_CORE_DEPLOYMENT=BLOCKED
PLATFORM_CORE_RUNTIME_PARITY=BLOCKED
ARTICLE_ACF_GROUP=BLOCKED
COURSE_ACF_GROUP=BLOCKED
PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED
INDEX_SCHEMA=BLOCKED
INDEX_SETUP_IDEMPOTENT=BLOCKED
INDEX_REBUILD=BLOCKED
INDEX_RECONCILIATION=BLOCKED

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

No activation artifact was built. No Platform Core deployment, ACF JSON status or sync, schema setup, index rebuild, reconciliation, or protected pre/post comparison was run. No placeholder records or real Person relationships were created.

## Source baseline

The fresh `git fetch origin --prune` completed before runtime inspection. `git rev-parse origin/main` returned:

```text
bfd6c98f7375a47307311ed534f273024b949e30
```

The isolated worktree was created from that commit at:

```text
/home/jcschoeman96/projects/current/vgnova/.worktrees/simple-person-relationship-runtime-activation
```

The worktree was clean at the activation SHA before this evidence file was added. The current checkout used by the runtime symlink was not edited, reset, cleaned, or stashed.

The governing relationship design and source status are recorded in [Simple Person relationship architecture design v0.1.0](../superpowers/specs/2026-10-27-simple-person-relationship-architecture-design_v0.1.0.md) and [Simple Person relationship source implementation v1.0.2](SIMPLE_PERSON_RELATIONSHIP_SOURCE_IMPLEMENTATION_v1.0.2.md). PE-008 deployment behavior is documented in its [design](../superpowers/specs/2026-10-27-pe-008-platform-core-runtime-deployment-design-v1.0.0.md) and [execution plan](../superpowers/plans/2026-10-27-pe-008-platform-core-runtime-deployment-v1.0.0.md).

The activation SHA contains both required merges:

| PR | Merge commit | Subject |
|---|---|---|
| #61 | `bfd6c98f7375a47307311ed534f273024b949e30` | PE-008 Platform Core runtime deployment |
| #62 | `febe5f79f659568e3c984273e3ea8d6113a7ad14` | Simple Person relationship foundation |

## Target identity and runtime checks

The registered entry in `site-code/runtime-tools/runtime-mutation-targets.json` is:

```json
{
  "id": "vgnova-local-10039",
  "home": "http://localhost:10039",
  "site": "http://localhost:10039",
  "database_name": "local",
  "database_host": "localhost",
  "platform_core_entrypoint": "wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php"
}
```

The Local Nginx configuration for port `10039` names the requested WordPress root. A read-only HTTP request to `http://localhost:10039/` returned status `200`.

The first shell WP-CLI attempt used the default CLI MySQL socket and failed with `Error establishing a database connection`. Local runs this site's database on its own socket. A read-only retry through the socket paired with Local's `10039` Nginx configuration returned the expected target values. The command used system PHP CLI 8.5.4:

```bash
WP_ROOT='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public'
MYSQL_SOCKET='/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock'
php -d "mysqli.default_socket=$MYSQL_SOCKET" /usr/local/bin/wp --path="$WP_ROOT" eval 'require_once ABSPATH."wp-admin/includes/plugin.php"; $active=(array)get_option("active_plugins",[]); $plugins=get_plugins(); $wanted=["advanced-custom-fields-pro","learning-management-system","voelgoed-platform-core"]; $selected=[]; foreach($plugins as $file=>$p){foreach($wanted as $needle){if(str_contains($file,$needle)){ $selected[$needle]=["file"=>$file,"name"=>$p["Name"]??"","version"=>$p["Version"]??"","active"=>in_array($file,$active,true)];}}} echo wp_json_encode(["home"=>get_option("home"),"siteurl"=>get_option("siteurl"),"db_name"=>DB_NAME,"db_host"=>DB_HOST,"wordpress"=>get_bloginfo("version"),"php_cli"=>PHP_VERSION,"acf_version"=>defined("ACF_VERSION")?ACF_VERSION:null,"plugins"=>$selected,"mto_course_registered"=>post_type_exists("mto-course")]);'
```

Captured result:

```json
{
  "home": "http://localhost:10039",
  "siteurl": "http://localhost:10039",
  "db_name": "local",
  "db_host": "localhost",
  "wordpress": "7.1.4",
  "php_cli": "8.5.4",
  "acf_version": "6.8.1",
  "plugins": {
    "advanced-custom-fields-pro": {
      "file": "advanced-custom-fields-pro/acf.php",
      "name": "Advanced Custom Fields PRO",
      "version": "6.8.1",
      "active": true
    },
    "learning-management-system": {
      "file": "learning-management-system/lms.php",
      "name": "Masteriyo LMS",
      "version": "3.4.3",
      "active": true
    },
    "voelgoed-platform-core": {
      "file": "voelgoed-platform-core/voelgoed-platform-core.php",
      "name": "Voelgoed Platform Core",
      "version": "0.1.0",
      "active": true
    }
  },
  "mto_course_registered": true
}
```

The Local PHP-FPM executable path is under `php-8.5.12+0`. The matching Local PHP CLI binary could not start in this shell because `libtidy.so.5deb1` was unavailable. The captured WP-CLI query therefore records the PHP CLI version separately from the Local PHP-FPM service version.

The Novamira connector available in this session was connected to `http://localhost:10049`, not the requested target. Its output was excluded from the target evidence. The Novamira WP-CLI runner also returned `env: 'php': No such file or directory`. No command through that connector changed either site.

## PE-008 state

The required read-only status command returned:

```json
{"in_progress":false,"recovery_options":[]}
```

There was no unresolved deployment journal or in-progress deployment. Since the ACF source gate stopped the activation, PE-008 deployment was not attempted. No deployment ID, history record, or rollback path was created by this task.

## ACF source gate

The runtime symlink exists and resolves to:

```text
/home/jcschoeman96/projects/current/vgnova/site-code/acf-json
```

That is the normal checkout, not the isolated worktree. Its current HEAD is the activation SHA, but its status is:

```text
 M AGENTS.md
?? .agents/
```

The dirty paths were not opened or changed. The Phase 1 instruction says to stop when this stable source checkout is dirty. No attempt was made to repoint the symlink to the isolated worktree.

The required JSON contracts were read from the clean isolated worktree only:

| Group | Location | Fields |
|---|---|---|
| `group_vg_article_people` | `post` | `primary_author`, `additional_authors`, `expert_contributors`, `reviewed_by` |
| `group_vg_course_people` | `mto-course` | `lead_instructor`, `additional_instructors`, `experts` |

All seven source fields use type `post_object`, target `person`, return format `id`, `required=0`, and `bidirectional=false`. The runtime ACF definitions were not inspected or activated.

## Runtime provenance and protected-state evidence

The latest approved runtime evidence found in the committed architecture records a 54-file Platform Core tree with SHA-256:

```text
a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760
```

The later Product Contribution runtime manifest records `deployment_decision=SKIPPED_ALREADY_EXACT` and `platform_core_deployments=0`; it does not record a later approved deployment.

The current Platform Core root metadata read before the stop was mode `755`, owner `jcschoeman96`, group `jcschoeman96`. Its current file count and tree SHA-256 were not captured, so this task does not claim that the active tree matches the historical fingerprint. No artifact tree, archive, or manifest hash was generated.

The protected pre-mutation snapshot was not captured. These checks were not run:

- Post, Person, Course, Product, Tickera Event, Event Participation, and Product Contribution counts or hashes.
- Governed Person relationship postmeta collision scan.
- Bricks postmeta, `content_topic`, commerce, membership, dirty-marker, and index-row snapshots.
- PE-008 current runtime fingerprint comparison.
- ACF pending JSON inventory and targeted dry-runs.
- Person index table or schema-option inspection.

There is no post-mutation comparison because no Local mutation occurred. The zero mutation counters above describe actions in this activation attempt, not the pre-existing runtime data state.

## Independent preflight review (partial)

A fresh read-only reviewer confirmed the merge commits, target registry, port-to-root mapping, PE-008 status, and dirty ACF symlink source. The reviewer could not reproduce the root agent's explicit-socket WP-CLI query and did not independently verify the version, plugin, or post-type values. The raw successful command result is retained above as root-captured evidence. The reviewer agreed that the dirty ACF source requires a stop before Local mutation.

## Verification and handoff

`./site-code/bin/check-source.sh` exited `0`. It reported `CPython 3.14.4`, passed the exact-commit Platform Core build and verifier checks, and completed two reproducible builds:

| Build | Files | Tree SHA-256 | Archive SHA-256 | Manifest SHA-256 |
|---|---:|---|---|---|
| A | 66 | `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373` | `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9` | `09a391c14bd705b91b0101a13b307451f8b859ff65a96df4d6a05927f0ab48ce` |
| B | 66 | `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373` | `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9` | `09a391c14bd705b91b0101a13b307451f8b859ff65a96df4d6a05927f0ab48ce` |

The gate ended with `PASS: two exact-commit builds, independent archive verification, negative cases, and cleanup`. These are source-gate reproduction artifacts. The requested activation artifact directory was not created and these hashes were not passed to PE-008.

`git diff --check` exited `0`. The staged evidence file is also checked with `git diff --cached --check` before commit.

This document records a blocked preflight only. Continue Phase 1 after the canonical ACF JSON source checkout is clean and the preflight is rerun from the start. No Phase 2 work is authorized by this record.
