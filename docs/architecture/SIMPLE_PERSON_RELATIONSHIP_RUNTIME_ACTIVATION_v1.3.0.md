# Simple Person Relationship Runtime Activation v1.3.0

**Outcome: `RUNTIME_ACTIVATION=BLOCKED`**

The run stopped at the mandatory PE-008 database transport preflight. The freshly rediscovered socket was active. The `session` helper passed its required checks, but the `health` helper raised `DeploymentError: WordPress health helper returned an error or unexpected diagnostic output.` The plan requires a stop on any preflight failure. No deployment, ACF sync, schema setup, rebuild, reconciliation, or content mutation ran.

I did not use the Local browser, wp-admin, or editor. Keep the requested interactive freeze in place until the protected post-snapshot is reported.

## Source and ACF gates

`origin/main` and the isolated worktree were at `06ec482445ca4065b49e745d2164308c5548ce59`. The PR #68 merge commit's second parent was `cc52a154e2c16271ad13dda00b6743a24327559a`.

Generation 1 ACF remained detached and clean at `5a3bc4fc89fa61ae8916aa460989f1e8912b473d`. All 14 ACF JSON paths and bytes matched the activation candidate. The runtime symlink still pointed to generation 1. The Article and Course fields matched the approved Person relationship contract.

Four targeted dry-runs each reported one pending Create: Article People, Course People, Member Perk Details, and Voelgoed Perk Settings. No ACF group was synchronized.

## Target and pre-snapshot

Explicit-socket WP-CLI and the connected WordPress runtime identified `http://localhost:10039/`, database `local`, database host `localhost`, and prefix `wp_`. WordPress was 7.1.4, CLI PHP was 8.5.4, Local PHP-FPM was reported as 8.5.12, ACF Pro was 6.8.1, Masteriyo was 3.4.3, and Platform Core was 0.1.0. Both `person` and `mto-course` were registered.

The live Platform Core tree matched the accepted baseline: 54 files, content SHA-256 `a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760`, PE-008 tree SHA-256 `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`, modes `53x0644,1x0755,16 directories x0755`, owner/group `1000:1000`.

The governed relationship keys had zero stored rows. The Person index table and schema-version option were absent. Dirty markers were zero. Counts and hashes for protected posts, commerce, editorial data, Bricks metadata, `content_topic`, PMPro, and WooCommerce are in the companion JSON.

## Artifact and PE-008 preflight

The fresh current-main artifact passed verification: 66 files, tree SHA-256 `d8a1244b1ad7e6767e271535959d5c4df2bf35fe0cec1dd1e3c10f11fd6ec373`, archive SHA-256 `3babd382c4dddb71d7b358f968eb4e62aa4d37d9d3048aa0bfa07d1a35cfe5c9`, manifest SHA-256 `0ce7b008739315af56f98a6fcb952ba76a8e454d5067f80f6bcee7466351460f`.

Immediately before preflight, `/home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock` was confirmed as an active Unix socket. The exact plan code exited 1 during `health`. It did not emit combined session/health JSON because the health helper raised before the JSON print. The exact traceback is in the companion JSON.

PE-008 status was clean before and after. Its deployment state directory remained absent. The full runtime fingerprint was unchanged. The required preflight deployment-state and runtime-tree deltas are both `NO`.

## Stop state

No deployment ID, history entry, or rollback path was created. `PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED` because deployment and bootstrap did not succeed. ACF sync, schema setup, idempotency, rebuild, authoritative rebuild-source count/hash, reconciliation, and protected post-snapshot are `NOT_RUN`.
The required `check-source.sh`, `git diff --check`, and staged diff check passed.

```text
PREVIOUS_ATTEMPT_V1_0_0=BLOCKED_ACF_SOURCE_GATE
PREVIOUS_ATTEMPT_V1_1_0=BLOCKED_RUNTIME_PERMISSION_PROVENANCE
PREVIOUS_ATTEMPT_V1_2_0=BLOCKED_PE008_DB_TRANSPORT_HEALTH

ACTIVATION_SOURCE_SHA=06ec482445ca4065b49e745d2164308c5548ce59
PE008_DB_TRANSPORT_SOURCE_FIX=MERGED
PE008_DB_TRANSPORT_FIX_MERGE_SHA=06ec482445ca4065b49e745d2164308c5548ce59

ACF_RUNTIME_SOURCE_SHA=5a3bc4fc89fa61ae8916aa460989f1e8912b473d
ACF_RUNTIME_JSON_PARITY_WITH_ACTIVATION_SHA=PASS
REQUEST_READ_WINDOW_CONSISTENCY=NOT_REQUIRED
RUNTIME_SYMLINK_SWITCH=NOT_REQUIRED

PRE_DEPLOY_RUNTIME_PE008_TREE_SHA256=856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56

PE008_SOCKET_TRANSPORT_PREFLIGHT=BLOCKED
PE008_SESSION_PREFLIGHT=PASS
PE008_HEALTH_PREFLIGHT=BLOCKED
PE008_PREFLIGHT_DEPLOYMENT_STATE_DELTA=NO
PE008_PREFLIGHT_RUNTIME_TREE_DELTA=NO

RUNTIME_ACTIVATION=BLOCKED
PLATFORM_CORE_DEPLOYMENT=BLOCKED
PLATFORM_CORE_RUNTIME_PARITY=NOT_RUN

ARTICLE_ACF_GROUP=NOT_RUN
COURSE_ACF_GROUP=NOT_RUN

PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED
INDEX_SCHEMA=NOT_RUN
INDEX_SETUP_IDEMPOTENT=NOT_RUN
INDEX_REBUILD=NOT_RUN
INDEX_RECONCILIATION=NOT_RUN

GOVERNED_META_PREEXISTING=0
INDEX_ROWS_AFTER_REBUILD=NOT_RUN
NEW_DIRTY_SOURCES=NOT_RUN

PLACEHOLDER_QA=NOT_STARTED
REAL_GOLDEN_JOURNEY_DATA=NOT_STARTED
BRICKS_MUTATIONS=0
REPRESENTATIVE_DATA_MUTATIONS=0
REAL_RELATIONSHIP_ASSIGNMENTS=0
COURSE_COMMERCE_MUTATIONS=0
MEMBERSHIP_MUTATIONS=0
```

Full structured evidence: [SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.3.0_DETAILS.json](SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.3.0_DETAILS.json).
