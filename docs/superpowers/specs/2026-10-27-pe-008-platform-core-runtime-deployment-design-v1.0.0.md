# PE-008 Platform Core runtime deployment design v1.0.0

**Status:** Proposed
**Date:** 2026-10-27
**Repository baseline:** `de90ff061c20b164c127aa97e845e3f192429543`

## Intent

PE-008 adds a reusable, fail-closed way to deploy one exact, verified Voelgoed Platform Core artifact into the registered VGNova WordPress runtime. It closes the gap between the existing source and artifact checks and the PE-007 runtime mutation session.

PE-008 is platform infrastructure. It contains no Person, Product, Event, or VS1 behavior. Its tests use disposable fixture roots. It makes no live VGNova changes.

## Scope

The deployer consumes the canonical archive and manifest produced under `PLATFORM_CORE_ASSEMBLY_CONTRACT_v1.4.0`. It does not rebuild or rewrite those files. Immediately before every deployment it invokes the existing verifier with the requested source commit and artifact directory, then compares the caller's manifest SHA-256 with the verified manifest bytes.

The only supported target is `vgnova-local-10039`. The deployer checks the registered WordPress root, home URL, site URL, database name, and Platform Core entrypoint. It also requires the active plugin path to be the exact non-symlink path beneath that WordPress root.

Every runtime filesystem mutation runs inside `site-code/bin/vgnova-runtime-mutate.php`. The Python process independently calls a source-controlled PHP helper that loads the identified WordPress installation and invokes `vg_runtime_mutation_assert_session('vgnova-local-10039')`. Environment values alone do not prove a session.

## Artifact and filesystem rules

The verifier must accept the exact supplied output for the exact full source commit before staging begins. The deployer rejects a missing, unknown, or abbreviated commit; a wrong manifest digest; a malformed archive; or a manifest whose bytes are not canonical.

The deployer inspects archive entries and writes them itself. It does not call `extractall()` or another generic extraction routine. Every file must be a regular file listed in the verified manifest, with the expected UTF-8 relative path, mode, size, and SHA-256. The archive must contain only the canonical plugin root. Absolute paths, traversal, duplicate paths, backslashes, NUL, CR/LF, links, devices, FIFOs, sockets, extra files, and unsupported modes fail closed.

The stage and active directories must share a filesystem. The stage, rollback, failed-candidate, and state paths use one unguessable deployment ID. The state root is outside `public/`, owned by the executing user, and mode `0700`. State and journal paths may not be symlinks.

Before activation, the deployer fingerprints the whole existing plugin tree. It records regular-file paths, modes, sizes, SHA-256 values, the canonical tree SHA-256, root owner/group/mode, and filesystem device. It rejects links and unsupported filesystem objects.

## Activation and rollback

Activation uses Linux `renameat2(..., RENAME_EXCHANGE)` to exchange the active plugin directory and the complete staged directory. PE-008 has no sequential rename fallback and never leaves the active path absent. It fsyncs both parent directories after each exchange.

The previous plugin tree remains at the stage path until health and exact runtime parity pass. On success, the deployer writes `finalize_intent`, moves the old tree to a unique rollback path with no-replace semantics, verifies its recorded tree digest, marks the journal finalized, and moves the completed record into history. PE-008 never deletes rollback directories.

Any post-exchange failure triggers another atomic exchange. The deployer verifies that the old tree is active, moves the failed candidate to its retained diagnostic path, records `rolled_back`, and exits non-zero. If it cannot prove the old tree is restored, it keeps every path and the unresolved journal, reports non-secret paths and hashes, and blocks future deployments.

## Journal and recovery

The state root has one `in-progress.json` journal, written with a temporary file, file fsync, atomic replace, and directory fsync. Journal files use mode `0600` and contain no credentials. Completed records are stored under `history/<deployment-id>.json`.

The journal records the target, WordPress root, deployment ID, source commit, archive and manifest digests, target and prior tree digests, deployer and verifier provenance, all managed paths, the current phase and phase history, timestamps, health and parity results, and recovery status.

The phases are `prepared`, `stage_verified`, `swap_intent`, `swapped`, `health_verified`, `parity_verified`, `finalize_intent`, and `finalized`. Failure recovery uses `rollback_intent`, `rolled_back`, and `rollback_failed`.

An unresolved journal blocks normal deployment. `status` is read-only. `recover --action=rollback` and `recover --action=finalize` inspect actual tree hashes and directory locations before acting. The phase is evidence, not filesystem authority. Unknown or mismatched topologies stop for manual review.

Rollback recovery handles both sides of an exchange whose journal write was interrupted. Finalize recovery reruns the PE-007 session check, WordPress health, HTTP health, and exact parity before it retains the old tree. Recovery also handles a crash after the old tree moved to rollback but before the journal reached history.

## Runtime health and parity

After activation, the PHP helper bootstraps the exact WordPress root and returns one machine-readable JSON result. The deployer rejects fatal errors, uncaught exceptions, warnings, notices, deprecations, unexpected output, a missing or inactive Platform Core entrypoint, a missing `Voelgoed\\PlatformCore\\Plugin` class, or missing expected lifecycle registration.

The deployer also requests the registered local home URL without following redirects. The response must be the expected local WordPress site response. The HTTP check does not replace the PHP check.

After health succeeds, the deployer fingerprints the active tree and requires exact equality with the verified artifact manifest: file count, paths, modes, sizes, per-file hashes, and tree hash. Missing files, extra files, mismatches, links, and unsupported objects fail parity and trigger rollback.

## Source provenance

Each deployment record captures the deployer repository commit, deployer file SHA-256, canonical verifier file SHA-256, artifact source commit, manifest SHA-256, and archive SHA-256. The deployer and verifier must be tracked and match their committed bytes at the executing repository revision. Dirty deployment-critical source fails closed. The artifact source commit may differ from the deployer commit.

## CLI

The primary entrypoint is `site-code/bin/deploy-platform-core.py`; reusable logic lives in `site-code/runtime-tools/platform_core_deployment.py`.

The CLI provides `deploy`, `status`, and `recover --action=rollback|finalize`. Deploy and recovery mutations require the registered target and WordPress root, plus a valid PE-007 session. Status is read-only. No production CLI option injects crashes or bypasses checks.

## Testing and execution boundary

Tests use temporary roots and deterministic fixture artifacts. They cover exact artifact authority, unsafe archive entries, filesystem identity, session gates, source provenance, staging, journal durability, atomic exchange, health and parity rollback, retained rollback trees, crash recovery at each journal boundary, and unknown topologies. At least one integration test calls the real Linux `RENAME_EXCHANGE` operation against temporary directories.

Failure injection uses test dependency injection. It is not exposed as a production CLI option.

During PE-008 implementation:

- WordPress business mutations: zero.
- Bricks mutations: zero.
- ACF mutations: zero.
- Live Platform Core deployments: zero.
- Runtime plugin replacements: zero.

## Handoff

PE-008 is merged and independently reviewed before Task 10B resumes. Task 10B then updates its branch, rebuilds the exact artifact, deploys through this tool, and records the PE-008 deployment record. Task 10B does not implement deployment logic.
