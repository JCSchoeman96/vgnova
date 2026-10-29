# Platform Core runtime deployment v1.0.0

**Status:** Proposed design; implementation in progress for review
**Scope:** Source-controlled deployment tooling for `vgnova-local-10039`

## Contract

Platform Core deployment accepts only the canonical archive and manifest defined by `PLATFORM_CORE_ASSEMBLY_CONTRACT_v1.4.0`. It verifies the supplied artifact against a full source commit immediately before deployment. It does not rebuild or modify the artifact.

The registered target is `vgnova-local-10039`, with its expected WordPress root, local home and site URLs, database name, and plugin entrypoint taken from the PE-007 target registry. The deployer also requires that the active plugin is the exact non-symlink path beneath that root.

The deployment command must run as a child of `site-code/bin/vgnova-runtime-mutate.php`. Before its first runtime filesystem write, it calls the source-controlled PHP helper, which boots the supplied WordPress installation and calls `vg_runtime_mutation_assert_session('vgnova-local-10039')`. The coordinator remains responsible for the registered target and both database locks. Environment variables alone are not accepted as proof.

## Filesystem layout

For a WordPress root at `.../app/public/`, deployment state is stored under `.../app/.vgnova-platform-core-deployments/`. The state root must be a non-symlink directory owned by the executing user with mode `0700`. Journal files use mode `0600`.

The active path is `wp-content/plugins/voelgoed-platform-core`. A unique deployment ID names the sibling stage, rollback, and failed-candidate directories. Active and stage must be directories on the same filesystem. Cross-device activation is rejected.

Before staging, the deployer inventories the old runtime tree, including file path, mode, size, SHA-256, canonical tree hash, root owner, group, mode, and device ID. Symbolic links and unsupported objects fail closed.

## Artifact staging

The canonical verifier validates the artifact archive and manifest. The deployer then inspects and writes each archive entry itself. It accepts only the manifest's regular files, canonical plugin root, supported file modes, and exact expected paths, sizes, and digests. It rejects traversal, absolute paths, duplicate entries, CR/LF, NUL, backslashes, links, devices, FIFOs, sockets, unlisted files, and mode or content mismatches.

The staged tree must have the same file count and canonical tree hash as the manifest, with no missing, extra, or mismatched files. No generic archive extraction routine is allowed.

## Atomic activation

The deployer records `swap_intent` durably, then calls Linux `renameat2` with `RENAME_EXCHANGE` on the active and stage directories. It does not fall back to sequential rename or symlink switching. After exchange, active contains the candidate and stage contains the exact prior tree. The deployer fsyncs both parent directories and records `swapped`.

The deployer runs PHP bootstrap health, local HTTP health, and exact runtime parity checks. Health and parity must both pass before finalization.

When the verified artifact already matches the active files and expected nested directory metadata, the deployer verifies health and rechecks the unchanged tree without creating a deployment journal or moving directories.

On success, it records `finalize_intent`, moves the old tree from stage to a unique rollback path with no-replace semantics, verifies the old digest, records `finalized`, then moves the completed journal to history. It does not delete retained rollback trees.

On a post-exchange failure with known trees, it records `rollback_intent`, exchanges active and stage again, verifies the old snapshot at active, and moves the known target candidate to the retained failed path. An incomplete stage is retained for diagnosis. The command exits non-zero after recording `rolled_back`. If restoration cannot be proven, the deployer retains all trees and the unresolved journal, records `rollback_failed` when possible, and refuses later deployment.

## Journal and recovery

The state root contains one `in-progress.json` journal. Journal updates use write, file fsync, atomic replace, and directory fsync. The journal has no credentials. It records artifact authority, tool provenance, all deployment paths, old and target tree digests, phase history, health and parity results, and recovery status.

The supported phases are `prepared`, `stage_verified`, `swap_intent`, `swapped`, `health_verified`, `parity_verified`, `finalize_intent`, and `finalized`. Failure phases are `rollback_intent`, `rolled_back`, and `rollback_failed`.

An unresolved journal blocks normal deployment. `status` reads the journal and fingerprints active, stage, rollback, and failed-candidate paths without changing them. Recovery actions inspect tree hashes and path topology; they do not trust the phase alone.

Rollback recovery may restore the old tree from stage or rollback, or finish retaining a candidate when the old tree is already active. Finalize recovery is permitted only when the target is active and the old tree is at stage or at the recorded rollback path after an interrupted rename. It reruns PE-007 session, WordPress, HTTP, and parity checks before finalizing. Unknown topology stops without deleting or moving paths.

## Health and exact parity

The source-controlled PHP helper loads the exact supplied WordPress root. It emits one deterministic JSON result and treats PHP warnings, notices, deprecations, fatal errors, uncaught exceptions, and unexpected output as failures. It verifies the target identity, active plugin entrypoint, `Voelgoed\\PlatformCore\\Plugin` class, and expected lifecycle registration.

HTTP health requests the registered home URL without following redirects. It requires the expected local WordPress response. It runs independently of PHP health.

Runtime parity re-fingerprints the complete active plugin directory and compares file paths, modes, sizes, SHA-256 values, file count, and tree hash with the canonical manifest. It also compares nested directory paths, modes, owners, and groups with the staged directory records saved in the journal before exchange. The prior-runtime snapshot records the same directory metadata. An unknown tree stops recovery without moving it; the journal remains available for diagnosis.

## Provenance

Every deployment record stores the deployer commit and file digest, canonical verifier file digest, artifact source commit, manifest digest, and archive digest. Before mutation, the deployer checks that its critical source files are tracked and match their committed bytes at the executing revision. The artifact source revision and deployer revision may differ.

## Policy and tests

`site-code/bin/deploy-platform-core.py` is a protected external mutator in `mutating-entrypoints.json`. Its approved filesystem actions are limited to owned stage and state creation, whole-directory exchange, and no-replace renames of its managed directories. It writes no WordPress database state.

The PHP health/session helper is read-only. The canonical source gate runs Python syntax checks, deployment unit tests, the real Linux exchange integration test, mutation-policy checks, the existing runtime mutation suites, and exact Platform Core assembly verification.

All deployment behavior is tested with disposable fixtures. PE-008 performs no live VGNova deployment, runtime plugin replacement, WordPress business mutation, Bricks mutation, or ACF mutation.
