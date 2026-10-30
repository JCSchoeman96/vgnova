# PE-008 WordPress Health DB Transport Propagation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Version:** 0.1.0

**Date:** 2026-10-30

**Goal:** Fix the PE-008 WordPress health-helper transport defect so the exact DB socket selected by PE-007 is propagated as non-secret transport context and explicitly bound into PHP for `session` and `health`.

**Architecture:** PE-007 remains the sole authority that selects database transport. It explicitly injects the resolved socket into the mutation child as `VGNOVA_DB_SOCKET`, separate from the seven session-proof variables. PE-008 validates that exact Unix socket and passes it to PHP through `-d mysqli.default_socket=...` and `-d pdo_mysql.default_socket=...`; it does not rediscover Local configuration and does not weaken strict JSON validation.

**Tech Stack:** PHP 8.5, Python 3, Linux AF_UNIX sockets, WordPress runtime tooling, PE-007 runtime mutation coordinator, PE-008 Platform Core deployer, unittest-style fixture tests.

**Spec:** `docs/superpowers/specs/2026-10-30-pe-008-health-db-transport-propagation-design_v0.1.0.md`

## Global Constraints

- PR #67 must first be corrected and merged. Do not create the source-fix branch from the open PR #67 evidence branch.
- After PR #67 merges, run `git fetch origin --prune` and pin the exact fresh `origin/main` as `SOURCE_FIX_BASE_SHA`.
- Use an isolated clean worktree created from exact `SOURCE_FIX_BASE_SHA`.
- TDD is mandatory: write each regression test first, run it, and observe the expected RED before production code.
- No Local WordPress mutation or deployment is permitted in this source-fix slice.
- `VGNOVA_DB_SOCKET` is non-secret transport context, not PE-007 session proof.
- The seven existing `VGNOVA_MUTATION_*` proof fields and FD3 semantics remain unchanged.
- Do not add `VGNOVA_DB_SOCKET` to generic inherited safe-environment passthrough.
- Do not add `PHPRC`, `PHP_INI_SCAN_DIR`, credentials, or broad environment inheritance.
- CLI `--db-socket` remains authoritative over conflicting incoming `VGNOVA_DB_SOCKET`.
- PE-008 must not search Local directories or guess a socket.
- PE-008 read-only `status` must continue to work without DB-socket health bootstrap.
- Strict canonical JSON health parsing remains unchanged.
- No ACF, Person, Bricks, commerce, membership, or representative-data mutation.
- Historical activation evidence v1.0.0/v1.1.0/v1.2.0 remains append-only and untouched by this source PR.

## Review Focus

1. **Ambient socket spoofing:** a parent `VGNOVA_DB_SOCKET` must not survive unless the coordinator selected it.
2. **CLI/environment precedence:** explicit `--db-socket` must be the exact value crossing the child boundary when parent env conflicts.
3. **Socket path safety:** missing, relative, NUL-containing, symlink, regular-file, directory, or stale/non-socket paths must fail before PHP launch.
4. **Argv fidelity:** socket paths must remain one exact argv value; no shell interpretation or string interpolation is allowed.
5. **Session-proof regression:** no new transport variable may be treated as proof of an active PE-007 session.

---

## File Map

**Modify**
- `site-code/runtime-tools/runtime-mutation-core.php`
- `site-code/bin/vgnova-runtime-mutate.php`
- `site-code/runtime-tools/platform_core_deployment.py`
- `site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php`
- `site-code/runtime-tests/tests/test_platform_core_deployment.py`

**Create**
- `docs/architecture/VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.2.0.md`
- `docs/architecture/PE008_HEALTH_DB_TRANSPORT_FIX_v1.0.0.md`
- `docs/superpowers/specs/2026-10-30-pe-008-health-db-transport-propagation-design_v0.1.0.md`
- `docs/superpowers/plans/2026-10-30-pe-008-health-db-transport-propagation_v0.1.0.md`

No other production files should change unless a failing test proves a necessary dependency.

---

### Task 1: Gate on corrected/merged PR #67 and pin exact source base

**Interfaces:**
- Produces `SOURCE_FIX_BASE_SHA`.
- No production code changes.

- [x] **Step 1: Verify PR #67 is merged**

Require the merged evidence contains:
- `PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED`;
- PR #65 described as blocked Person relationship activation preflight.

If PR #67 is not merged or corrections are absent, STOP.

**Completed:** PR [#67](https://github.com/JCSchoeman96/vgnova/pull/67) merged on 2026-10-30 as `abeecd233bf9477da2a0499f3ec7cb4b1009faf7`. The merged v1.2.0 evidence contains `PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED` and describes PR #65 as the blocked Person relationship activation preflight.

- [x] **Step 2: Fresh fetch**

```bash
git fetch origin --prune
SOURCE_FIX_BASE_SHA="$(git rev-parse origin/main)"
```

Record full SHA.

- [x] **Step 3: Create isolated worktree**

Use `superpowers:using-git-worktrees`.

Suggested branch:

```text
fix/pe008-health-db-transport
```

Require exact base and clean worktree.

- [x] **Step 4: Add approved design and this plan to canonical paths**

Do not alter their substantive approved decisions.

---

### Task 2: TDD — PE-007 child transport contract

**Files:**
- Test: `site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php`
- Modify after RED: `site-code/runtime-tools/runtime-mutation-core.php`

**Interfaces:**
- Produces:
  `VgRuntimeMutationCore::childEnvironment(array $baseEnvironment, array $proof, ?string $databaseSocket = null): array`

- [x] **Step 1: Add RED test — selected socket is injected**

Test name:

```text
child environment injects explicit database socket transport separately from session proof
```

Assert:
- passing `/tmp/vgnova.sock` as explicit third argument produces `VGNOVA_DB_SOCKET=/tmp/vgnova.sock`;
- all seven existing session-proof variables still equal their expected values.

- [x] **Step 2: Add RED test — ambient socket is not trusted**

Test name:

```text
child environment does not inherit ambient database socket without explicit selection
```

Base environment includes:

```text
VGNOVA_DB_SOCKET=/tmp/ambient.sock
```

Call `childEnvironment(base, proof, null)`.

Assert `VGNOVA_DB_SOCKET` is absent.

- [x] **Step 3: Add RED test — explicit value wins**

Base environment contains `/tmp/wrong.sock`, explicit third arg is `/tmp/selected.sock`.

Assert child receives only `/tmp/selected.sock`.

- [x] **Step 4: Add RED test — unsafe transport rejected**

Assert relative path and NUL-containing value raise the existing runtime mutation exception before child environment is returned.

- [x] **Step 5: Run RED**

```bash
php site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php
```

Expected: new assertions fail because `childEnvironment()` does not yet accept/propagate explicit socket transport.

- [x] **Step 6: Implement minimum change**

Modify:

```php
public static function childEnvironment(
    array $baseEnvironment,
    array $proof,
    ?string $databaseSocket = null
): array
```

Requirements:
- keep current sanitization;
- keep current seven proof assignments unchanged;
- never inherit ambient `VGNOVA_DB_SOCKET` through generic safe environment;
- validate explicit socket path using existing DB absolute-path safety semantics;
- inject only the explicit selected value.

Do not require filesystem socket existence here; PE-007 selection is a transport-path contract and actual PE-008 helper validation owns AF_UNIX object validation.

- [x] **Step 7: Run GREEN**

```bash
php site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php
```

Expected PASS.

- [x] **Step 8: Commit**

Suggested:

```text
test/fix: propagate selected DB transport in PE-007 child environment
```

Fresh reviewer checks Task 2 before Task 3.

---

### Task 3: TDD — wire coordinator-selected socket into actual child launch

**Files:**
- Modify: `site-code/bin/vgnova-runtime-mutate.php`
- Test: use existing source-safe coordinator/core tests; add the smallest direct test/fixture required if current tests do not exercise callback propagation.

**Interfaces:**
- Consumes Task 2 `childEnvironment(..., ?string $databaseSocket)`.
- Produces actual coordinator child environment carrying the resolved `$socketOverride`.

- [x] **Step 1: Add RED proof for precedence at the launch boundary**

Use the smallest existing source-safe fixture/integration path capable of observing the launched child environment.

Set:
- parent `VGNOVA_DB_SOCKET=/tmp/ambient.sock`;
- coordinator-selected socket `/tmp/selected.sock`.

Assert child sees `/tmp/selected.sock`.

Do not require a real Local database for this test.

- [x] **Step 2: Run RED**

Run the exact focused test/fixture and record expected failure: child currently lacks the selected socket.

- [x] **Step 3: Implement minimum wiring**

In the mutation callback:
- capture `$socketOverride`;
- pass it as the third argument to `VgRuntimeMutationCore::childEnvironment()`.

Do not change keeper transport selection or target identity logic.

- [x] **Step 4: Run focused GREEN**

Expected exact selected socket reaches child.

- [x] **Step 5: Run PE-007 suites**

```bash
php site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php
php site-code/runtime-tests/tests/run-runtime-mutation-session-tests.php
python3 site-code/runtime-tests/tests/test_fcm_self_featured_integration.py
```

Expected PASS.

- [x] **Step 6: Commit**

Suggested:

```text
fix: pass PE-007 selected DB socket to mutation child
```

Fresh reviewer verifies precedence and no session-proof change.

---

### Task 4: TDD — fail-closed PE-008 socket validation

**Files:**
- Test: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Modify after RED: `site-code/runtime-tools/platform_core_deployment.py`

**Interfaces:**
- Produces helper such as:
  `_validated_php_db_socket(environment: Mapping[str, str] | None = None) -> str`
  or equivalent focused internal API.
- `run_php_health_helper()` consumes the validated exact socket.

- [x] **Step 1: Add RED test — missing socket**

Test:

```text
test_php_health_helper_requires_explicit_database_socket_before_process_launch
```

Patch `subprocess.run`.

With `VGNOVA_DB_SOCKET` absent:
- expect `DeploymentError`;
- assert `subprocess.run` not called.

- [x] **Step 2: Add RED tests — unsafe paths**

Cases:
- relative path;
- NUL-containing value;
- symlink path;
- regular file;
- directory;
- nonexistent path.

Each must fail before PHP launch.

- [x] **Step 3: Add RED test — real AF_UNIX socket accepted**

Create temporary real Unix socket with Python `socket.AF_UNIX`.

Provide its path as `VGNOVA_DB_SOCKET`.

Patch PHP process result to canonical JSON.

Current code will not enforce/use socket; test must fail for the intended missing behavior.

- [x] **Step 4: Run RED**

```bash
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py
```

Expected new tests fail for missing validation/binding behavior.

- [x] **Step 5: Implement minimum validator**

Requirements:
- exact environment value;
- absolute;
- no NUL;
- `lstat`;
- not symlink;
- `stat.S_ISSOCK`;
- fail closed with `DeploymentError`.

Do not resolve/search alternate sockets.

- [x] **Step 6: Run GREEN**

```bash
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py
```

Expected PASS.

- [x] **Step 7: Commit**

Suggested:

```text
test/fix: require validated Unix DB socket for PE-008 health helper
```

Fresh reviewer verifies fail-closed behavior.

---

### Task 5: TDD — explicit PHP socket binding for both helper modes

**Files:**
- Test: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Modify after RED: `site-code/runtime-tools/platform_core_deployment.py`

**Interfaces:**
- `run_php_health_helper()` must use the exact validated socket path.

- [x] **Step 1: Add RED test for `session` mode argv**

Create real temporary AF_UNIX socket.

Patch `subprocess.run`.

Call:

```python
run_php_health_helper(..., mode="session")
```

Assert argv contains exact pairs:

```text
-d
mysqli.default_socket=<socket>

-d
pdo_mysql.default_socket=<socket>
```

Assert socket path appears as one argv item, even if temporary parent path contains spaces where supported.

- [x] **Step 2: Add RED test for `health` mode argv**

Same exact assertions for `mode="health"`.

- [x] **Step 3: Assert transport environment classification**

The PHP child environment may contain `VGNOVA_DB_SOCKET` with the exact validated value, but it must not be added to `MUTATION_SESSION_ENVIRONMENT` or treated as session proof.

- [x] **Step 4: Run RED**

```bash
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py
```

Expected fail: current PHP argv lacks explicit socket bindings.

- [x] **Step 5: Implement minimum binding**

Add the two PHP `-d` settings before the helper script:

```text
mysqli.default_socket=<exact socket>
pdo_mysql.default_socket=<exact socket>
```

Keep argv list execution and strict JSON parsing unchanged.

- [x] **Step 6: Run GREEN**

```bash
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py
```

Expected PASS.

- [x] **Step 7: Commit**

Suggested:

```text
fix: bind PE-008 PHP health helper to selected DB socket
```

Fresh reviewer checks exact argv and strict JSON behavior.

---

### Task 6: Regression coverage for strict JSON, descriptors, secrets, and status

**Files:**
- Tests only unless a regression reveals a real defect.

- [x] **Step 1: Verify strict JSON tests remain unchanged/green**

Existing behavior must still reject:
- HTML;
- extra output;
- malformed JSON;
- diagnostics on stderr;
- nonzero PHP exit.

Run:

```bash
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py
```

- [x] **Step 2: Verify descriptor/session contract**

Run:

```bash
php site-code/runtime-tests/tests/run-runtime-mutation-session-tests.php
python3 site-code/runtime-tests/tests/test_fcm_self_featured_integration.py
```

Expected PASS.

- [x] **Step 3: Verify policy/secret checks**

```bash
php site-code/runtime-tests/tests/run-runtime-mutation-policy-tests.php
php site-code/bin/check-runtime-mutation-policy.php
```

Expected PASS.

- [x] **Step 4: Verify PE-008 status does not require DB socket helper**

Add/retain focused test that `status_deployment()` / CLI status path remains read-only and independent of `run_php_health_helper()`.

No socket requirement should be introduced into read-only status.

- [x] **Step 5: Commit test-only adjustments if needed**

No unrelated refactor.

---

### Task 7: Append-only architecture amendment and source-fix evidence

**Files:**
- Create: `docs/architecture/VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.2.0.md`
- Create: `docs/architecture/PE008_HEALTH_DB_TRANSPORT_FIX_v1.0.0.md`

**Interfaces:**
- Durable architecture/evidence only.

- [x] **Step 1: Write v1.2.0 PE-007 amendment**

Preserve v1.1.0.

Explicitly state:

```text
VGNOVA_DB_SOCKET=NON_SECRET_TRANSPORT_CONTEXT
VGNOVA_DB_SOCKET_IS_SESSION_PROOF=NO
```

Document:
- coordinator selection precedence;
- explicit child injection;
- no generic inheritance;
- unchanged seven proof fields;
- unchanged FD3/two-lock proof.

- [x] **Step 2: Write source-fix evidence**

Mandatory result block:

```text
SOURCE_FIX_BASE_SHA=<exact merged main after PR67>
ROOT_CAUSE=PROVEN
SELECTED_APPROACH=PE007_PROPAGATES_SELECTED_DB_TRANSPORT

VGNOVA_DB_SOCKET_CLASSIFICATION=NON_SECRET_TRANSPORT_CONTEXT
SESSION_PROOF_FIELDS_UNCHANGED=YES

CLI_DB_SOCKET_PRECEDENCE=PASS
AMBIENT_DB_SOCKET_NOT_TRUSTED=PASS
CHILD_RECEIVES_EXACT_SELECTED_SOCKET=PASS

PE008_REQUIRES_VALID_UNIX_SOCKET=PASS
PE008_MISSING_SOCKET_FAIL_CLOSED=PASS
PE008_INVALID_SOCKET_FAIL_CLOSED=PASS
PE008_SESSION_HELPER_BINDS_EXACT_SOCKET=PASS
PE008_HEALTH_HELPER_BINDS_EXACT_SOCKET=PASS
PE008_STRICT_JSON_HEALTH_CONTRACT_UNCHANGED=YES
PE008_STATUS_SOCKET_INDEPENDENT=YES

PE007_DESCRIPTOR_CONTRACT_UNCHANGED=PASS
SECRET_HANDLING_UNCHANGED=PASS

LOCAL_RUNTIME_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
ACF_SYNCS=0
PERSON_RELATIONSHIP_MUTATIONS=0

ALL_REQUIRED_TESTS=PASS
```

Include the RED evidence and GREEN commands/results.

- [x] **Step 3: Do not edit v1.2.0 activation evidence**

The blocked activation record is historical and remains unchanged after PR #67 merge.

---

### Task 8: Full verification and source-fix PR

- [x] **Step 1: Run focused suites**

```bash
php site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php
php site-code/runtime-tests/tests/run-runtime-mutation-session-tests.php
php site-code/runtime-tests/tests/run-runtime-mutation-policy-tests.php
python3 site-code/runtime-tests/tests/test_fcm_self_featured_integration.py
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py
php site-code/bin/check-runtime-mutation-policy.php
```

All PASS.

- [x] **Step 2: Run PHP syntax on changed PHP files**

```bash
php -l site-code/runtime-tools/runtime-mutation-core.php
php -l site-code/bin/vgnova-runtime-mutate.php
```

PASS.

- [x] **Step 3: Run canonical full source check**

```bash
./site-code/bin/check-source.sh
```

PASS.

- [x] **Step 4: Run whitespace checks**

```bash
git diff --check
```

PASS.

- [x] **Step 5: Fresh whole-branch reviewer**

Reviewer must specifically inspect:
- no ambient socket trust;
- CLI precedence;
- exact PE-007 child propagation;
- socket-object validation;
- exact PHP argv;
- no session-proof expansion;
- no descriptor change;
- no credential leakage;
- no Local mutation;
- no unrelated refactor.

Fix/re-review all Critical and Important findings.

- [ ] **Step 6: Open source-fix PR and STOP**

Suggested branch:

```text
fix/pe008-health-db-transport
```

Suggested title:

```text
fix: propagate PE-007 DB transport to PE-008 health checks
```

## Execution record

The source fix was implemented and reviewed on the isolated `fix/pe008-health-db-transport` worktree from `SOURCE_FIX_BASE_SHA=abeecd233bf9477da2a0499f3ec7cb4b1009faf7`. RED and GREEN evidence, focused suite results, full source-check output, and zero-runtime-mutation counters are recorded in `docs/architecture/PE008_HEALTH_DB_TRANSPORT_FIX_v1.0.0.md`. Task 8 Step 6 remains pending until this reviewed branch is published and its source-fix PR is created.

Hand back:
- exact base SHA;
- exact head SHA;
- changed files;
- RED evidence;
- focused test results;
- full `check-source.sh`;
- CI status;
- fresh review result.

Do not deploy or restart Person Runtime Activation.

---

# Acceptance Gate

```text
ROOT_CAUSE=PROVEN
SELECTED_APPROACH=PE007_PROPAGATES_SELECTED_DB_TRANSPORT

VGNOVA_DB_SOCKET_CLASSIFICATION=NON_SECRET_TRANSPORT_CONTEXT
SESSION_PROOF_FIELDS_UNCHANGED=YES

CLI_DB_SOCKET_PRECEDENCE=PASS
AMBIENT_DB_SOCKET_NOT_TRUSTED=PASS
CHILD_RECEIVES_EXACT_SELECTED_SOCKET=PASS

PE008_REQUIRES_VALID_UNIX_SOCKET=PASS
PE008_MISSING_SOCKET_FAIL_CLOSED=PASS
PE008_INVALID_SOCKET_FAIL_CLOSED=PASS
PE008_SESSION_HELPER_BINDS_EXACT_SOCKET=PASS
PE008_HEALTH_HELPER_BINDS_EXACT_SOCKET=PASS
PE008_STRICT_JSON_HEALTH_CONTRACT_UNCHANGED=YES
PE008_STATUS_SOCKET_INDEPENDENT=YES

PE007_DESCRIPTOR_CONTRACT_UNCHANGED=PASS
SECRET_HANDLING_UNCHANGED=PASS

LOCAL_RUNTIME_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
ACF_SYNCS=0
PERSON_RELATIONSHIP_MUTATIONS=0

ALL_REQUIRED_TESTS=PASS
```

Only after this source-fix PR is independently approved and merged may a new Person Runtime Activation attempt be planned.
