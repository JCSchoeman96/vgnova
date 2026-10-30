# VGNova runtime mutation coordination v1.2.0

## Status

Approved PE-008 database transport amendment. It adds to the v1.1.0 coordinator contract. The v1.0.0 and v1.1.0 records remain unchanged.

The v1.1.0 host lock, two independent MariaDB advisory locks, target identity checks, supervisor lifecycle, descriptor boundary, and secret handling remain in force.

## Database socket classification

```text
VGNOVA_DB_SOCKET=NON_SECRET_TRANSPORT_CONTEXT
VGNOVA_DB_SOCKET_IS_SESSION_PROOF=NO
SESSION_PROOF_FIELDS_UNCHANGED=YES
```

`VGNOVA_DB_SOCKET` names the Unix socket selected by PE-007. It is transport context. It does not prove that a mutation session is active and is not part of the seven PE-007 proof values.

## Selection and child propagation

The coordinator keeps this precedence:

```text
--db-socket
    > incoming VGNOVA_DB_SOCKET
    > none
```

The coordinator validates a selected value as an absolute path. The CLI selection overrides a conflicting incoming environment value. PE-007 does not search for a socket when neither source selects one.

The child environment builder first sanitizes the inherited environment and removes any inherited `VGNOVA_DB_SOCKET`. It then validates and injects only the explicit socket selected by the coordinator. When no socket was selected, the child receives no `VGNOVA_DB_SOCKET` value. This value does not pass through the generic environment allowlist.

The process launcher sanitizes the environment again. The coordinator also passes the selected socket as an explicit launcher argument. After the second sanitization, the launcher injects only that validated argument. A conflicting value in the launcher's input environment cannot replace it.

The PE-007 proof remains exactly:

```text
VGNOVA_MUTATION_SESSION_ID
VGNOVA_MUTATION_TARGET
VGNOVA_MUTATION_DB_LOCK_A
VGNOVA_MUTATION_DB_CONNECTION_ID_A
VGNOVA_MUTATION_DB_LOCK_B
VGNOVA_MUTATION_DB_CONNECTION_ID_B
VGNOVA_MUTATION_LOCK_FILE
```

The host-lock descriptor remains FD3. The two keeper descriptors and their ownership checks are unchanged. PE-007 validates the socket path format but does not require the path to exist as a socket. PE-008 validates the filesystem object before starting PHP.

## PE-008 PHP health transport

The PE-008 `session` and `health` modes require the propagated socket. They reject missing or empty values, NUL bytes, relative paths, nonexistent paths, symlinks, regular files, directories, stale socket inodes, and other non-socket objects before launching PHP. Immediately before process launch, PE-008 confirms the selected path appears in Linux's `/proc/net/unix` registry; it does not connect to the database during validation. This narrows the path replacement window. It does not search Local directories or select another socket.

The PHP command remains an argv list and receives these exact settings for both modes:

```text
-d
mysqli.default_socket=<exact selected socket>
-d
pdo_mysql.default_socket=<exact selected socket>
```

The helper's strict canonical JSON response contract is unchanged. Read-only PE-008 `status` does not invoke the PHP health helper and does not require a database socket.

## Security and scope

No database credential is added to argv or to the seven session-proof values. `PHPRC` and `PHP_INI_SCAN_DIR` are not added to PE-007's generic environment allowlist. No database host, WordPress configuration, lock ownership, descriptor, or credential behavior changes.

This amendment concerns source behavior only. It authorizes no Local runtime mutation, Platform Core deployment, ACF sync, Person relationship change, or activation retry.
