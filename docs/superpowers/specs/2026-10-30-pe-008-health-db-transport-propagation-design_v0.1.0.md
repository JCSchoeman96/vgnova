# PE-008 WordPress Health DB Transport Propagation Design

**Version:** 0.1.0

**Status:** APPROVED

**Date:** 2026-10-30

**Repository:** `JCSchoeman96/vgnova`

## Intent

Fix the proven PE-008 source defect that prevents its WordPress `session` and `health` helper from bootstrapping the registered Local WordPress target when PHP's ambient/default MySQL socket differs from the database socket already selected and verified by PE-007.

The fix must preserve PE-007 as the single authority for runtime-mutation database transport, preserve PE-007 session-proof semantics, keep environment sanitization fail-closed, and keep PE-008's strict single-JSON health contract unchanged.

## Current failure

```text
operator supplies verified Local socket to PE-007
        ↓
PE-007 uses that socket for database lock keepers and target identity
        ↓
PE-007 starts deployer child
        ↓
selected socket is not exported to the child
        ↓
PE-008 run_php_health_helper() invokes literal `php`
        ↓
PHP uses ambient mysqli.default_socket
        ↓
/var/run/mysqld/mysqld.sock
        ↓
registered Local WordPress database is unavailable there
        ↓
WordPress emits database-error HTML
        ↓
PE-008 correctly rejects output because it is not one canonical JSON result
```

The strict JSON parser is not defective. The transport context is lost between PE-007 and PE-008.

## Root-cause status

```text
PE008_HEALTH_DB_TRANSPORT_ROOT_CAUSE=PROVEN
```

## Goals

1. Preserve PE-007 as the authority that selects and validates database transport.
2. Propagate the exact selected Unix socket to repository-owned mutation children as non-secret transport context.
3. Bind PE-008's PHP helper to that exact socket explicitly rather than ambient PHP configuration.
4. Preserve all existing PE-007 session-proof, lock, descriptor, target-identity, and secret-handling guarantees.
5. Preserve PE-008's strict canonical JSON health response requirement.
6. Fail closed before PHP launch if required socket transport is missing or malformed.
7. Prove the behavior through TDD and source-safe tests before any Local activation retry.

## Non-goals

- No Person Runtime Activation.
- No Platform Core runtime deployment.
- No Local WordPress mutation.
- No ACF sync.
- No schema/index work.
- No change to database credentials or how credentials are read.
- No socket rediscovery inside PE-008.
- No reliance on `PHPRC`, `PHP_INI_SCAN_DIR`, `MYSQL_UNIX_PORT`, or ambient PHP configuration.
- No weakening of the PE-008 JSON/output checks.
- No change to the meaning of the seven PE-007 mutation proof values.

## Approaches considered

### A. Propagate PE-007's selected transport — selected

PE-007 passes the exact validated socket it already selected to its child as `VGNOVA_DB_SOCKET`. PE-008 validates that transport context and explicitly configures PHP's MySQL socket for both helper modes.

### B. Add a second `--db-socket` to `deploy-platform-core.py` — rejected

This duplicates one critical transport value across two CLI layers.

### C. Use Local/PHP ambient configuration — rejected

This recreates the dependency that caused the failure.

## Architecture

### 1. `VGNOVA_DB_SOCKET` is transport context, not session proof

The existing PE-007 mutation proof remains exactly:

```text
VGNOVA_MUTATION_SESSION_ID
VGNOVA_MUTATION_TARGET
VGNOVA_MUTATION_DB_LOCK_A
VGNOVA_MUTATION_DB_CONNECTION_ID_A
VGNOVA_MUTATION_DB_LOCK_B
VGNOVA_MUTATION_DB_CONNECTION_ID_B
VGNOVA_MUTATION_LOCK_FILE
```

`VGNOVA_DB_SOCKET` is a separate, non-secret transport value. Possession of it proves nothing about session validity.

### 2. PE-007 injects the selected socket explicitly

Do **not** simply whitelist `VGNOVA_DB_SOCKET` in generic inherited safe-environment handling.

Extend:

```php
VgRuntimeMutationCore::childEnvironment(
    array $baseEnvironment,
    array $proof,
    ?string $databaseSocket = null
): array
```

Behavior:

- sanitize the base environment exactly as today;
- rebuild the seven proof fields exactly as today;
- if `$databaseSocket` is non-null/non-empty, validate it as an absolute safe DB path and inject `VGNOVA_DB_SOCKET`;
- if no socket is selected, ensure `VGNOVA_DB_SOCKET` is absent.

This makes the coordinator-selected value authoritative instead of trusting ambient environment.

### 3. CLI precedence stays coordinator-owned

Existing resolution remains:

```text
--db-socket=<path>
    over
incoming VGNOVA_DB_SOCKET
    over
no explicit socket
```

The child-launch callback captures the resolved `$socketOverride` and passes it explicitly to `childEnvironment()`.

### 4. PE-008 requires propagated socket transport

`run_php_health_helper()` reads `VGNOVA_DB_SOCKET` from its sanitized process environment.

Before PHP launch it must fail closed unless the value:

- exists;
- is non-empty;
- contains no NUL;
- is absolute;
- points to an existing Unix-domain socket;
- is not a symlink.

PE-008 must not search Local directories or guess a socket.

Read-only PE-008 `status` remains independent and does not require this transport.

### 5. PHP receives explicit socket overrides

The helper command remains argv-based and shell-free. Add:

```text
-d mysqli.default_socket=<exact VGNOVA_DB_SOCKET>
-d pdo_mysql.default_socket=<exact VGNOVA_DB_SOCKET>
```

Do not modify `DB_HOST`, `wp-config.php`, Local configuration, or credentials.

### 6. Environment sanitization remains strict

Do not broaden generic environment passthrough.

Do not add `PHPRC` or `PHP_INI_SCAN_DIR` to PE-007's safe environment.

`VGNOVA_DB_SOCKET` is injected only by explicit coordinator logic.

### 7. Credentials remain excluded

The socket path is non-secret transport context. No DB user/password is added to argv, journals, or environment dumps.

## Expected source changes

### `site-code/runtime-tools/runtime-mutation-core.php`

- extend `childEnvironment()` with explicit `?string $databaseSocket`;
- validate and inject `VGNOVA_DB_SOCKET`;
- do not trust it via generic inherited environment.

### `site-code/bin/vgnova-runtime-mutate.php`

- capture resolved `$socketOverride` in the child-launch callback;
- pass it explicitly to `childEnvironment()`.

### `site-code/runtime-tools/platform_core_deployment.py`

- add a fail-closed socket validator;
- require `VGNOVA_DB_SOCKET` for PHP `session`/`health`;
- bind `mysqli.default_socket` and `pdo_mysql.default_socket` via PHP `-d`.

## Tests

### `site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php`

Add coverage for:

- explicit socket reaches child environment;
- ambient socket is not trusted unless selected by coordinator;
- explicit selected socket overrides conflicting inherited socket;
- invalid/relative socket is rejected;
- seven session-proof fields remain unchanged.

### `site-code/runtime-tests/tests/test_platform_core_deployment.py`

Add RED-before-GREEN coverage for:

- missing socket fails before `subprocess.run`;
- malformed/relative socket fails before PHP launch;
- symlink/non-socket path fails;
- real AF_UNIX socket passes validation;
- both `session` and `health` bind exact `mysqli.default_socket`;
- both bind exact `pdo_mysql.default_socket`;
- strict JSON parsing behavior remains unchanged.

Use source-safe tests only. No Local mutation test belongs in the source PR.

## Documentation amendment

Add append-only:

```text
docs/architecture/VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.2.0.md
```

It must preserve v1.1.0 and state explicitly:

```text
VGNOVA_DB_SOCKET = non-secret transport context
VGNOVA_DB_SOCKET != mutation session proof
```

Do not rewrite historical activation evidence.

## TDD acceptance requirements

Minimum RED evidence:

1. PE-007 child environment currently drops the coordinator-selected socket.
2. PE-008 helper invocation currently has no explicit PHP DB socket binding.

Required GREEN verification:

```text
PE007_SELECTED_SOCKET_PROPAGATION=PASS
CLI_SOCKET_PRECEDENCE=PASS
AMBIENT_SOCKET_NOT_TRUSTED=PASS

PE008_MISSING_SOCKET_FAIL_CLOSED=PASS
PE008_INVALID_SOCKET_FAIL_CLOSED=PASS
PE008_SESSION_SOCKET_BINDING=PASS
PE008_HEALTH_SOCKET_BINDING=PASS

PE007_SESSION_PROOF_UNCHANGED=PASS
PE007_DESCRIPTOR_CONTRACT_UNCHANGED=PASS
PE008_STRICT_JSON_CONTRACT_UNCHANGED=PASS
SECRET_HANDLING_UNCHANGED=PASS
```

Run all existing PE-007, PE-008, runtime-mutation policy, Platform Core source, PHP syntax, and canonical source/build tests.

## Runtime safety

This source-fix PR performs **zero Local mutation**.

After the source fix is independently reviewed and merged, the next Person activation attempt must restart from Stage 1 against then-current `main`.

The next attempt should include an early read-only confirmation that the PE-007-selected socket reaches PE-008's helper before crossing a deployment write boundary.

## Interaction with PR #67

PR #67 remains a blocked-attempt evidence PR.

Before merge, correct it so:

```text
PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED
```

and describe PR #65 correctly as the blocked Person relationship activation preflight.

The PE-008 source fix must be a separate PR.

## Acceptance gate

```text
ROOT_CAUSE=PROVEN
SELECTED_APPROACH=PE007_PROPAGATES_SELECTED_DB_TRANSPORT

VGNOVA_DB_SOCKET_CLASSIFICATION=NON_SECRET_TRANSPORT_CONTEXT
SESSION_PROOF_FIELDS_UNCHANGED=YES

CLI_DB_SOCKET_PRECEDENCE=PASS
AMBIENT_DB_SOCKET_NOT_TRUSTED=PASS
CHILD_RECEIVES_EXACT_SELECTED_SOCKET=PASS

PE008_REQUIRES_VALID_UNIX_SOCKET=PASS
PE008_SESSION_HELPER_BINDS_EXACT_SOCKET=PASS
PE008_HEALTH_HELPER_BINDS_EXACT_SOCKET=PASS
PE008_STRICT_JSON_HEALTH_CONTRACT_UNCHANGED=YES

LOCAL_RUNTIME_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
ACF_SYNCS=0
PERSON_RELATIONSHIP_MUTATIONS=0

ALL_REQUIRED_TESTS=PASS
```
