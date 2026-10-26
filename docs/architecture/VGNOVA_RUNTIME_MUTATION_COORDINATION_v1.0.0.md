# VGNova runtime mutation coordination v1.0.0

## Status

Superseded before merge by [v1.1.0](VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.1.0.md) on 2026-10-26 after independent review found that a single database-lock keeper could lose cross-host exclusion if its connection ended unexpectedly. This file remains the historical v1.0.0 design record. Its single-keeper model was implemented on PE-007 PR #57 but was not merged.

## Purpose and scope

This contract serializes repository-owned one-shot administrative and runtime-tool mutations against the Local VGNova WordPress runtime. Normal interactive WordPress admin requests and application writes remain outside this process-scoped coordinator. In particular, `ProductContributionService` and `EventParticipationService` remain ordinary domain services; the three WordPress editor callback classes that perform ordinary request-time synchronization are listed as narrow `explicit_exemption` entries in the inventory.

PE-007 changes source and tooling only. It does not perform live WordPress writes, migrations, runtime QA, or Platform Core deployment. Live mutation QA stays outside CI.

## Lock contract

Only one administrative mutation session may run for a target at a time. The coordinator acquires a persistent host filesystem lock first, then starts a dedicated database-lock keeper that acquires the target-specific MariaDB advisory lock on its own connection. Contention fails immediately. Normal release ends the keeper's database session before releasing the filesystem lock.

The filesystem lock is a persistent file under `$XDG_STATE_HOME/vgnova/runtime-mutation/`, or `$HOME/.local/state/vgnova/runtime-mutation/` when `XDG_STATE_HOME` is unset. It must not be under the repository, a worktree, the WordPress root, `wp-content`, or `/tmp`. The target lock file is `vgnova-local-10039.lock`. The file is never unlinked. The kernel `flock()` state is authoritative; metadata is diagnostic only.

The coordinator uses nonblocking exclusive `flock()`. A second local coordinator exits without waiting. The keeper's dedicated database connection uses `GET_LOCK(name, 0)`. A `NULL` result or query error is a failure. A `0` result is contention. No command can force, steal, kill, or release another session's lock.

## Crash safety and child lifetime

After taking the host `flock()`, the coordinator starts a separate database-lock keeper. The keeper opens a dedicated MariaDB connection, takes the advisory lock, checks target identity, and reports non-secret session proof. It retains its database connection and an inherited copy of the host-lock descriptor while waiting on a lifetime pipe.

The coordinator launches a repository-owned process supervisor after the keeper is ready. On Linux, the supervisor sets `PR_SET_CHILD_SUBREAPER` through PHP FFI, creates a new session, and forks the target command into its own process group. It reports the target process ID before the coordinator opens the start gate. The target receives the host-lock descriptor as FD 3 and the lifetime descriptor as FD 4. The supervisor also retains both descriptors. It waits for and reaps every descendant before exiting, including descendants that create another session or close FDs 3 and 4. The supervisor's lifetime pipe is the keeper's release authority; `/proc` process-group scans do not authorize lock release.

If the coordinator is killed, the supervisor and keeper remain alive with copies of the host-lock descriptor, and the keeper retains the MariaDB advisory lock. A regression forks a descendant that calls `setsid()`, closes FDs 3 and 4, and writes later; both locks stay held until that descendant exits. If the keeper exits unexpectedly, the coordinator and supervisor still hold the host lock. If the coordinator exits after normal child completion, the keeper releases the database lock first and closes its host-lock descriptor; the coordinator then records released metadata and closes its descriptor.

On `SIGINT` or `SIGTERM`, the coordinator signals the target process group, waits for the supervisor and its descendants, then escalates to `SIGKILL` when the target group remains active. A child that catches a signal and exits normally retains its own exit status. A child terminated by a signal returns `128 + signal`; when a coordinator signal initiated escalation, the coordinator's signal is reported only if the child itself was signal-terminated. The readiness handshake uses a bounded, interruptible wait and terminates the supervisor if startup is interrupted. Execution fails clearly when required Linux, FFI, `pcntl`, `posix`, or process support is unavailable. Child arguments are passed as an argv array without shell parsing.

## Target identity

The version 1 target registry contains only non-secret identity for `vgnova-local-10039`:

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

The invocation supplies an absolute WordPress root. Before starting a child, the database keeper reads `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, and `$table_prefix` individually through WP-CLI's pre-WordPress config reader. It does not load plugins or themes. The password stays in process memory and is never placed in argv, metadata, status output, logs, or session environment.

After the filesystem lock is held, the database keeper checks the actual database name and normalized host, reads `home` and `siteurl` from the prefixed options table, and verifies the Platform Core entrypoint beneath the supplied root. It performs these checks without booting WordPress. Any mismatch stops before the mutation child starts. A socket override changes only the connection transport, not the expected target identity.

The database lock name is `vgnova.mutation.` followed by the first 40 lowercase hexadecimal characters of SHA-256 over the UTF-8 string `target_id + LF + database_name + LF + home + LF + site`. This makes the full name 56 characters. The same identity yields the same lock name; a different target identity yields a different name.

## Session proof

After both locks and the identity check succeed, the coordinator writes sanitized metadata and exports only these child values:

```text
VGNOVA_MUTATION_SESSION_ID
VGNOVA_MUTATION_TARGET
VGNOVA_MUTATION_DB_LOCK
VGNOVA_MUTATION_DB_CONNECTION_ID
VGNOVA_MUTATION_LOCK_FILE
```

The session guard checks the target, session ID, expected lock path, lock-file metadata, and actual `flock()` ownership using a separate nonblocking probe handle. Inside WordPress it queries `IS_USED_LOCK(name)` through `$wpdb` and requires the owner connection ID to match the database keeper's connection ID. Environment values alone do not prove a session.

Every repository-owned mutating administrative entrypoint asserts the session before its first persistent write. A failed assertion leaves any mutation and cleanup windows closed. Normal domain services are excluded from this policy.

## Database configuration and secrets

`DB_HOST` parsing accepts a hostname or address with an optional port, or a hostname/address plus an explicit socket path. Malformed values fail closed. `--db-socket` and `VGNOVA_DB_SOCKET` override only the transport. The dedicated keeper connection uses the retrieved credentials and remains open until the mutation child lifetime pipe reaches EOF. The child environment is an explicit allowlist plus the session proof; arbitrary inherited environment values and database credentials are omitted.

Metadata records schema version, target, purpose, process and host details, start time, source revision, working directory, sanitized command summary, state, session ID, lock name, connection ID, and normal release details. It never stores credentials, salts, keys, or an environment dump.

## Status and exit codes

`--status` reports the target, lock path and metadata, current host-lock probe result, database lock name and owner state (`held`, `free`, or `error`), and owner connection ID when available. Database errors have a sanitized error code and do not prevent the host-lock state from being reported. It does not start a child, hold a session, release a lock, or alter another session. A short filesystem probe may acquire and immediately release a lock to distinguish free from held.

Pre-child exit codes are stable:

| Code | Meaning |
|---:|---|
| 0 | Coordinator or status success |
| 70 | Internal coordinator failure |
| 71 | Invalid configuration or target |
| 72 | Filesystem lock contention |
| 73 | Database advisory lock contention |
| 74 | Target identity mismatch |
| 75 | Nested mutation session forbidden |

After a child starts, its exit result is propagated. The coordinator does not turn a child failure into success.

## Inventory and policy gate

The repository inventory classifies every tracked PHP file as `mutating`, `read_only`, `library`, `test_only`, or `explicit_exemption`. The initial PE-007 inventory covers 123 tracked PHP files: 14 mutating entries, 46 libraries, 57 test-only files, one read-only file, and five explicit exemptions. Mutating entries name their guard and purpose. Exemptions are narrow and document the reviewed scope. The tracked Python Novamira runner is separately listed as an external mutator because its remote PHP payload performs create and cleanup actions.

The source checker tokenizes PHP and flags obvious WordPress post, metadata, taxonomy, option, ACF, Bricks template-setting, and `$wpdb` mutation calls. It fails for an unclassified mutator, missing or duplicate inventory path, unknown classification, missing assertion, or a declared guard that is absent. This is a source guardrail; it does not prove that every possible indirect mutation has been detected.

## Approved invocation

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/absolute/wordpress/root" \
  --purpose="human readable reason" \
  -- \
  <command> <arguments...>
```

Read-only tools may inspect the runtime without the lock. Their results are not guaranteed to describe a stable no-writer snapshot. A task that requires a stable observation window may voluntarily run under the coordinator.

Multi-command QA workflows acquire a session separately for each process invocation. For example, Event Consumer setup and cleanup each require the coordinator; the lock does not remain held between those phases. Product Contribution performs setup, assertions, and cleanup within one protected invocation.

For the Self Featured integration runner, wrap the Python process itself so each Novamira PHP request receives the same active session proof:

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/absolute/wordpress/root" \
  --purpose="Run the Self featured-content integration" \
  -- python3 site-code/runtime-tests/fcm-self-featured-integration.py
```
