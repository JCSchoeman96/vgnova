# VGNova runtime mutation coordination v1.1.0

## Status

PE-007A pre-merge architecture amendment, implemented on PE-007 PR #57 and awaiting independent final review. It supersedes v1.0.0 before merge. Do not merge PR #57 until this exact head passes independent review and its required checks.

The v1.0.0 record remains at [`VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.0.0.md`](VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.0.0.md). Independent review found that v1.0.0's single database-lock keeper could lose cross-host exclusion when its MariaDB connection ended. The change to two independently held locks preserves the accepted cross-host property after any one coordination-process failure.

## Purpose and scope

This contract serializes repository-owned one-shot administrative mutations against one registered VGNova runtime. Ordinary WordPress requests and application-domain writers remain outside the process-scoped coordinator. PE-007A changes source, tooling, tests, and documentation only. It performs no live WordPress writes, migrations, runtime QA, or Platform Core deployment.

## Lock contract

A session follows this order:

```text
host filesystem flock
→ MariaDB advisory lock A
→ MariaDB advisory lock B
→ verify target identity and both lock owners
→ start mutation child
```

The coordinator uses nonblocking exclusive `flock()`. A second local coordinator exits without waiting. Each database lock uses `GET_LOCK(name, 0)` on its own dedicated MariaDB connection, owned by a separate keeper process. A `NULL` result or query error fails closed. A `0` result reports contention. The child does not start unless both keepers are ready and a separate verification connection confirms that both canonical locks are still owned by the expected keeper connection IDs.

The verification connection is short-lived and owns neither advisory lock. It reads the target identity only after both keepers acquired their locks, checks `IS_USED_LOCK()` for A and B, then closes. It does not replace either keeper.

Lock names are derived from the same registered identity using the UTF-8 input:

```text
target_id + LF + database_name + LF + home + LF + site
```

The digest is the first 40 lowercase hexadecimal characters of SHA-256. Names are:

```text
vgnova.mutation.a.<40-char-digest>
vgnova.mutation.b.<40-char-digest>
```

Each name is 58 characters, below MariaDB's 64-character limit. Any difference in target identity changes both names.

Normal release is in reverse acquisition order:

```text
DB lock B
→ DB lock A
→ host filesystem flock
```

The filesystem lock file is persistent under `$XDG_STATE_HOME/vgnova/runtime-mutation/`, or `$HOME/.local/state/vgnova/runtime-mutation/` when `XDG_STATE_HOME` is unset. It is never unlinked. The kernel `flock()` state is authoritative; lock-file metadata is diagnostic only.

## Keeper independence and failure guarantee

Keeper A and keeper B are separate processes with separate MariaDB connections. Each independently reads `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, and `$table_prefix` through WP-CLI's pre-WordPress configuration reader. The password stays in process memory and is never passed in argv, metadata, status output, logs, or session environment.

PE-007 v1.1 guarantees cross-host exclusion if any one of these processes dies unexpectedly while a mutation child may still write:

- coordinator;
- supervisor;
- database keeper A;
- database keeper B.

If keeper A dies, keeper B retains lock B; if keeper B dies, keeper A retains lock A. A competing coordinator may obtain the now-free slot, but it must then acquire the still-held slot. It fails on that second acquisition, releases the partial lock, and never starts a child. The contender's filesystem lock is on its own host and is not part of this cross-host proof.

If the coordinator dies, the supervisor and both keepers continue. The supervisor reports completion to both keepers only after the child and every descendant exit. If the supervisor dies before that acknowledgement, each keeper fails closed and retains its MariaDB connection and inherited host-lock descriptor while a descendant could still write. Detached descendants remain covered by the subreaper process lifecycle. This v1.1 guarantee covers one coordination-process failure at a time; it does not claim safety after simultaneous failure of all independent lock holders.

## Child descriptor boundary

The mutation target inherits only:

```text
FD 3 — host-lock descriptor
```

Coordinator and keeper lifecycle/control descriptors are internal. The supervisor uses descriptors 4 and 5 for the keeper lifetimes, 6 for readiness, 7 for the start gate, and 8 for status. The target closes descriptors 4–8 before `exec`. The coordinator's descriptor launcher also closes inherited descriptors outside its explicit allowlist.

The process supervisor is a Linux PHP FFI subreaper. It creates a new session and process group, gates target start until registration is complete, and waits for and reaps descendants, including detached descendants that close FD3. Child arguments remain an argv array and are not shell-parsed.

On `SIGINT` or `SIGTERM`, the coordinator signals the target process group, waits for the supervisor and descendants, then escalates to `SIGKILL` when required. A child that catches a signal and exits normally retains its exit status. A child terminated by a signal returns `128 + signal`. Required Linux, FFI, `pcntl`, `posix`, and process support is checked before execution.

## Target identity

The non-secret target registry identifies `vgnova-local-10039` by database name, normalized host, `home`, `siteurl`, and the expected Platform Core entrypoint beneath the supplied absolute WordPress root. Configuration reads do not load plugins or themes. The verification connection checks database name, normalized host, prefixed options, and entrypoint after both advisory locks are acquired. A mismatch stops before a mutation child starts. A socket override changes transport only, not expected identity.

## Session proof and metadata

The coordinator exports only the following mutation proof values:

```text
VGNOVA_MUTATION_SESSION_ID
VGNOVA_MUTATION_TARGET
VGNOVA_MUTATION_DB_LOCK_A
VGNOVA_MUTATION_DB_CONNECTION_ID_A
VGNOVA_MUTATION_DB_LOCK_B
VGNOVA_MUTATION_DB_CONNECTION_ID_B
VGNOVA_MUTATION_LOCK_FILE
```

There are no singular lock-name or connection-ID aliases. The Platform Core session guard requires a valid host `flock()`, canonical A and B names, valid metadata, and independent `IS_USED_LOCK()` results that match each expected keeper connection ID. Missing, free, wrong-owner, wrong-name, or failed queries invalidate the session. Environment values alone do not prove an active session.

Active metadata uses schema version 2 and records both sanitized lock names and connection IDs:

```json
{
  "database_locks": {
    "a": {"name": "vgnova.mutation.a.<digest>", "connection_id": 123},
    "b": {"name": "vgnova.mutation.b.<digest>", "connection_id": 456}
  }
}
```

Metadata has no credentials, salts, keys, or environment dump. Status reports each lock independently with its name, `held`/`free`/`error` state, owner connection ID when available, and sanitized error code. Status does not acquire, release, or modify another session's locks.

## FCM integration descriptor contract

`fcm-self-featured-integration.py` requires FD3 plus the session environment. It does not use FD4 or another coordinator control descriptor. Nested WP-CLI receives `pass_fds=(3,)` and the same session environment. Before a mutating PHP action, the Platform Core guard independently verifies the host lock and both MariaDB lock owners with `IS_USED_LOCK()`.

A source-safe subprocess integration runs a Python target through the real `VgRuntimeMutationCore::runChildCommand()` boundary under a temporary host flock and fake WP executable. It checks FD3 present, FD4 absent, nested WP-CLI descriptor state and environment, and exact argv boundaries. It uses no Local WordPress instance or database.

## Static inventory and policy

The complete tracked PHP inventory remains the policy source of truth. The FCM Python runner remains classified as a protected external mutator. The source checker requires the WP-CLI mutating entrypoint to verify the active session before its first persistent write. Normal application-domain writer exemptions remain unchanged. This guardrail does not prove that every indirect mutation is detectable.

## TDD and required verification

PE-007A added RED evidence before production changes:

- a process test showed that after the old single keeper died, an independent-host contender could acquire the database exclusion boundary;
- a source-safe child through the actual coordinator showed the FCM runner failed because it required FD4, which the coordinator correctly withholds.

The required regression suites cover A-keeper and B-keeper death, coordinator death, supervisor death, detached descendants, partial A-acquired/B-failed cleanup, session proof spoofing, descriptor isolation, nested WP-CLI propagation, inventory policy, and secrets. Live WordPress and database mutation counts remain zero.

## Approved invocation

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/absolute/wordpress/root" \
  --purpose="human readable reason" \
  -- \
  <command> <arguments...>
```

For the Self Featured integration, wrap the Python runner itself:

```bash
php site-code/bin/vgnova-runtime-mutate.php \
  --target=vgnova-local-10039 \
  --wp-root="/absolute/wordpress/root" \
  --purpose="Run the Self featured-content integration" \
  -- python3 site-code/runtime-tests/fcm-self-featured-integration.py
```
