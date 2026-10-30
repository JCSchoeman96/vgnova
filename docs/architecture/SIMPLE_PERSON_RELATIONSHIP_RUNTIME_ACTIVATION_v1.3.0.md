# Simple Person Relationship Runtime Activation v1.3.0

**Outcome: `RUNTIME_ACTIVATION=BLOCKED`**

The run stopped at the mandatory PE-008 database transport preflight. The freshly rediscovered socket was active. The `session` helper passed its required checks, but the `health` helper raised `DeploymentError: WordPress health helper returned an error or unexpected diagnostic output.` The plan requires a stop on any preflight failure. No deployment, ACF sync, schema setup, rebuild, reconciliation, or content mutation ran.

I did not use the Local browser, wp-admin, or editor. The requested interactive freeze remains active.

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

No deployment ID, history entry, or rollback path was created. `PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED` because deployment and bootstrap did not succeed. ACF sync, schema setup, idempotency, rebuild, authoritative rebuild-source count/hash, and reconciliation are `NOT_RUN`. The protected post-snapshot and comparison were completed in the follow-up evidence below.
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


## Protected post-snapshot and comparison

The original protected snapshot collector was rerun with the same queries, row canonicalization, ordering, and hashing used for the 2026-10-30 10:14:40 UTC pre-snapshot. The protected post-snapshot was captured at 10:37:29 UTC. Every recorded category matched: protected posts and meta for post, person, mto-course, Woo products, Tickera events, Event Participation, and Product Contribution; governed Person relationship metadata; dirty markers; Person index/schema option; Bricks metadata; content_topic; WooCommerce order/subscription tables; and PMPro membership/history tables.

PROTECTED_POST_SNAPSHOT=PASS
PROTECTED_PRE_POST_COMPARISON=PASS

The four targeted ACF dry-runs were repeated after this snapshot and before the diagnostic. Each still reported one pending Create; no sync ran.

Before the diagnostic, origin/main remained 06ec482445ca4065b49e745d2164308c5548ce59; the activation branch was clean at 8645072cbf4901df6377486b97ddbe12df08ae2e. PE-008 status was {"in_progress":false,"recovery_options":[]}, and the deployment state directory was absent. The active Platform Core tree remained 54 files with content SHA-256 a2635dafeb1d82d481b6275526c3850263f549facac80293c810d2b0d93de760 and PE-008 tree SHA-256 856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56. The freshly rediscovered active AF_UNIX socket was /home/jcschoeman96/.config/Local/run/nAZNpFs99/mysql/mysqld.sock.

## Post-attempt read-only PE-008 helper diagnostic

This diagnostic ran after the blocked v1.3.0 attempt through PE-007. It did not deploy or mutate WordPress data. The raw helper stdout parsed as JSON for both modes; no credential-like values were found or redacted.

Session:

    returncode=0
    stdout_sha256=cc9ed387b025e232c95bf62d3cfaa8cb338402168309f1420266cb08f809e320
    stderr_sha256=e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
    stderr=""
    stdout={"checks":{"session_valid":true},"ok":true,"session_valid":true,"target":"vgnova-local-10039","wp_root":"/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public"}

Health:

    HEALTH_RETURN_CODE=1
    HEALTH_STDOUT_SHA256=1e362557f4ac820e567bf2281ebec99a1d2affe0ab2b1940eea56cbd1a77bfa1
    HEALTH_STDERR_SHA256=e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
    HEALTH_STDERR=""
    HEALTH_CHECKS={"lifecycle_registered":true,"no_php_diagnostics":true,"no_unexpected_output":false,"plugin_class_loaded":true,"plugin_entrypoint_active":true,"runtime_identity":true,"session_valid":true,"site_name_available":true,"wordpress_bootstrapped":true}
    HEALTH_STDOUT={"checks":{"lifecycle_registered":true,"no_php_diagnostics":true,"no_unexpected_output":false,"plugin_class_loaded":true,"plugin_entrypoint_active":true,"runtime_identity":true,"session_valid":true,"site_name_available":true,"wordpress_bootstrapped":true},"database":"local","home":"http://localhost:10039/","ok":false,"session_valid":true,"site":"http://localhost:10039/","site_name":"Voelgoed","target":"vgnova-local-10039","wp_root":"/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public"}

The raw result identifies no_unexpected_output=false as the only failed health check. no_php_diagnostics=true and empty stderr show that this diagnostic did not report a PHP diagnostic. The raw output does not establish the underlying cause of the unexpected output.

After the diagnostic, PE-008 remained clean, the deployment state directory remained absent, and the runtime fingerprint matched the accepted baseline. The same protected snapshot collector was rerun at 10:43:31 UTC and matched the 10:37:29 UTC snapshot.

DIAGNOSTIC_DEPLOYMENT_STATE_DELTA=NO
DIAGNOSTIC_RUNTIME_TREE_DELTA=NO
DIAGNOSTIC_PROTECTED_STATE_DELTA=NO

Full structured evidence: [SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.3.0_DETAILS.json](SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.3.0_DETAILS.json).
