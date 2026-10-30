# PE-008 health database transport fix v1.0.0

## Result

```text
SOURCE_FIX_BASE_SHA=abeecd233bf9477da2a0499f3ec7cb4b1009faf7
ROOT_CAUSE=PROVEN
SELECTED_APPROACH=PE007_PROPAGATES_SELECTED_DB_TRANSPORT

VGNOVA_DB_SOCKET_CLASSIFICATION=NON_SECRET_TRANSPORT_CONTEXT
SESSION_PROOF_FIELDS_UNCHANGED=YES

PE007_SELECTED_SOCKET_PROPAGATION=PASS
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

PE007_SESSION_PROOF_UNCHANGED=PASS
PE007_DESCRIPTOR_CONTRACT_UNCHANGED=PASS
SECRET_HANDLING_UNCHANGED=PASS

LOCAL_RUNTIME_MUTATIONS=0
PLATFORM_CORE_DEPLOYMENTS=0
ACF_SYNCS=0
PERSON_RELATIONSHIP_MUTATIONS=0

ALL_REQUIRED_TESTS=PASS
```

## Hard prerequisite evidence

PR [#67](https://github.com/JCSchoeman96/vgnova/pull/67) merged on 2026-10-30 with merge commit `abeecd233bf9477da2a0499f3ec7cb4b1009faf7`, which is the exact `origin/main` source-fix base. Its merged `SIMPLE_PERSON_RELATIONSHIP_RUNTIME_ACTIVATION_v1.2.0.md` records `PERSON_SCHEMA_AUTO_INSTALL=BLOCKED_NOT_TESTED` and identifies PR #65 as `blocked Person relationship activation preflight`.

## Source change

PE-007 selects the database socket using its existing precedence: CLI `--db-socket`, then incoming `VGNOVA_DB_SOCKET`, then no selected socket. The child environment builder removes the inherited value, validates an explicitly selected absolute path without NUL bytes, and injects that value after environment sanitization.

The process launcher performs another environment sanitization. It now accepts the same selected socket as an explicit argument and injects it after that sanitization. The coordinator passes the resolved socket to both functions. The generic environment allowlist does not include `VGNOVA_DB_SOCKET`.

`VGNOVA_DB_SOCKET` remains non-secret transport context. PE-007 still uses the seven session-proof values listed in [the v1.2.0 coordination amendment](VGNOVA_RUNTIME_MUTATION_COORDINATION_v1.2.0.md). The process test observes the exact selected path in the launched child while the parent environment contains a conflicting ambient path.

PE-008's `session` and `health` helper modes now require the propagated value. Immediately before launching PHP, the helper checks that the path is absolute, nonempty, contains no NUL byte, exists, is not a symlink, has a Unix socket filesystem type, and appears as an active path in Linux's `/proc/net/unix` registry. That final check rejects stale socket inodes without connecting to the database and narrows the path replacement window between validation and launch. Because PHP later connects by pathname, a replacement after this final check remains a residual race; this validation does not pin the socket inode across PHP's connection. Tests use bound temporary AF_UNIX sockets for the accepted case and a closed-but-persisting socket inode for the stale case. Missing, empty, relative, NUL-bearing, nonexistent, symlink, regular-file, directory, and stale socket paths fail before `subprocess.run`.

PHP receives the exact selected socket in both `mysqli.default_socket` and `pdo_mysql.default_socket` through argv-based `-d` settings. The canonical JSON parser and the read-only `status` path are unchanged. `status` does not invoke the PHP health helper and does not need a socket.

## TDD evidence

Each changed behavior had a failing test before its production change.

| Behavior | RED evidence | GREEN evidence |
|---|---|---|
| PE-007 selected socket injection | `php site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php` failed with `The coordinator-selected database socket did not reach the child unchanged.` Exit 255. | The same command passed with 77 runtime mutation core tests. |
| PE-007 socket path validation | The core suite failed with `An invalid selected database socket was accepted: expected an exception.` Exit 255. | The same command passed with relative and NUL-bearing values rejected. |
| Coordinator launch forwarding | The core suite failed with `The child launch does not carry the coordinator-selected database socket through both environment boundaries.` Exit 255. | The core suite passed its precedence and callback wiring test. |
| Second environment sanitization | The core suite failed with `The child process did not receive the exact selected database socket.` Exit 255. The temporary child observed the socket missing. | The core suite passed after the explicit launcher argument injected the selected path after sanitization. |
| PE-008 missing socket | `python3 site-code/runtime-tests/tests/test_platform_core_deployment.py PlatformCoreDeploymentTests.test_php_health_helper_requires_explicit_database_socket_before_process_launch` failed with `AssertionError: DeploymentError not raised`. Exit 1. | The focused test passed for both `session` and `health`; `subprocess.run` was not called. |
| PE-008 invalid socket object | The focused invalid-socket test reported seven failures with `AssertionError: DeploymentError not raised` for empty, relative, NUL-bearing, nonexistent, symlink, regular-file, and directory paths. Exit 1. | The test passed for every invalid value and accepted a real bound AF_UNIX socket. |
| PE-008 stale socket inode | `python3 -m unittest site-code.runtime-tests.tests.test_platform_core_deployment.PlatformCoreDeploymentTests.test_php_health_helper_rejects_stale_database_socket_inode_before_process_launch` failed with `AssertionError: DeploymentError not raised`. Exit 1. `subprocess.run` was reached with the stale inode. | The stale inode test and invalid-socket test passed; both reject invalid paths before `subprocess.run`, while the bound AF_UNIX socket remains accepted. |
| PE-008 last-moment socket validation | `python3 -m unittest site-code.runtime-tests.tests.test_platform_core_deployment.PlatformCoreDeploymentTests.test_php_health_helper_revalidates_database_socket_immediately_before_process_launch` failed with `AssertionError: DeploymentError not raised`. Exit 1; the test closed the socket after the earlier check and `subprocess.run` was reached. | The last-moment validation, stale inode, and missing-socket tests passed; a socket that becomes stale during helper setup is rejected before `subprocess.run`. |
| PHP socket settings | The focused binding test failed for both modes. The actual argv began with `-d display_errors=1` instead of the two selected-socket settings. Exit 1. | The test passed for `session` and `health`, including a socket path with spaces. Each setting remained one argv value. |

## Verification

The requested source verification commands completed successfully:

```text
php site-code/runtime-tests/tests/run-runtime-mutation-core-tests.php = PASS, 77 tests
php site-code/runtime-tests/tests/run-runtime-mutation-session-tests.php = PASS, 19 tests
php site-code/runtime-tests/tests/run-runtime-mutation-policy-tests.php = PASS, 33 tests
python3 site-code/runtime-tests/tests/test_fcm_self_featured_integration.py = PASS, 3 tests
python3 site-code/runtime-tests/tests/test_platform_core_deployment.py = PASS, 61 tests
php site-code/bin/check-runtime-mutation-policy.php = PASS, 159 tracked PHP files, 42 with mutation APIs
php -l site-code/runtime-tools/runtime-mutation-core.php = PASS
php -l site-code/bin/vgnova-runtime-mutate.php = PASS
./site-code/bin/check-source.sh = PASS, exit 0
git diff --check = PASS
```

The source checker completed two exact-commit builds and its archive, negative-case, and cleanup checks. No PE-007 session-proof field, FD3 descriptor rule, credential handling, strict JSON rule, or status behavior changed.

## Runtime boundary

All tests use source files and temporary local fixtures. No Local WordPress mutation, Platform Core deployment, ACF sync, Person relationship write, commerce change, membership change, or editor change was run.
