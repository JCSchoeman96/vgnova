# PE-008 Platform Core runtime deployment implementation plan

> **For agentic workers:** Use the executing-plans workflow to implement this plan task by task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a reusable, fail-closed deployment tool for one verified Platform Core artifact and the registered VGNova runtime.

**Architecture:** Keep artifact, filesystem, journal, recovery, and orchestration logic in an importable Python module. The CLI binds that module to the registered target, canonical verifier, PHP session/health helper, and local HTTP check. Activation and rollback use only Linux `renameat2(RENAME_EXCHANGE)`.

**Tech Stack:** CPython 3.14.4, Python standard library, Linux `renameat2`, PHP 8.5 CLI, existing PE-007 coordinator, existing PE-005 exact-output verifier.

---

## Repository baseline and file map

- Baseline commit: `de90ff061c20b164c127aa97e845e3f192429543`.
- Existing artifact authority: `site-code/bin/check-platform-core-build.py` and `docs/architecture/PLATFORM_CORE_ASSEMBLY_CONTRACT_v1.4.0.md`.
- Existing runtime authority: `site-code/bin/vgnova-runtime-mutate.php`, `site-code/runtime-tools/runtime-mutation-session.php`, and `site-code/runtime-tools/runtime-mutation-targets.json`.
- Existing source gate: `site-code/bin/check-source.sh`.
- New module: `site-code/runtime-tools/platform_core_deployment.py` owns artifact inspection, secure extraction, tree fingerprints, durable journal operations, atomic exchange, deploy orchestration, health/parity decisions, recovery, and read-only status.
- New CLI: `site-code/bin/deploy-platform-core.py` parses `deploy`, `status`, and `recover`, validates source provenance, binds the one registered target, and delegates operations to the module.
- New PHP helper: `site-code/runtime-tools/platform-core-deployment-health.php` loads the exact WordPress root, validates registered identity and PE-007 session proof, and reports deterministic JSON health.
- New tests: `site-code/runtime-tests/tests/test_platform_core_deployment.py` uses temporary runtime roots, fixture archives, injected health/session/verifier functions, and one real Linux exchange integration case.
- Modified policy: `site-code/runtime-tools/mutating-entrypoints.json` registers the Python CLI as a protected external mutator; `site-code/bin/check-runtime-mutation-policy.php` validates external-mutator entries and allowed actions.
- Modified gate: `site-code/bin/check-source.sh` runs the external-mutator policy tests and deployment test suite; its existing exact-output check remains unchanged.
- Documentation: this plan, the proposed PE-008 design, `docs/architecture/PLATFORM_CORE_RUNTIME_DEPLOYMENT_v1.0.0.md`, and one open/unmerged ledger row.

## Task 1: Record the proposed contract

**Files:**
- Create: `docs/superpowers/specs/2026-10-27-pe-008-platform-core-runtime-deployment-design-v1.0.0.md`
- Create: `docs/architecture/PLATFORM_CORE_RUNTIME_DEPLOYMENT_v1.0.0.md`
- Create: `docs/superpowers/plans/2026-10-27-pe-008-platform-core-runtime-deployment-v1.0.0.md`
- Modify: `docs/architecture/IMPLEMENTATION_LEDGER.md`

- [x] Record the proposed target, artifact authority, PE-007 session rule, atomic exchange, state machine, recovery requirements, provenance, test scope, and zero-live-mutation boundary.
- [x] Add PE-008 to the ledger as open and unmerged. Do not change historical Task 8B evidence.

## Task 2: Test artifact safety and runtime fingerprints

**Files:**
- Create: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Create: `site-code/runtime-tools/platform_core_deployment.py`

- [x] Add tests for exact manifest record parsing, safe UTF-8 relative paths, duplicate rejection, mode/size/hash checks, and canonical tree hashing. Run the focused deployment suite.
- [x] Add tests for archive symlink, hardlink, device, FIFO, socket, absolute path, traversal, backslash, NUL, CR/LF, unexpected root, duplicate entry, and unmanifested-file rejection. Confirm each fixture fails before writing outside its stage root.
- [x] Add tests for regular-file-only runtime fingerprints, root metadata capture, symlink and unsupported-object rejection, active-path identity, and cross-device rejection.
- [x] Implement the path validator, archive inspector, secure writer, canonical fingerprint functions, and target-path checks. Run the focused deployment test suite.

## Task 3: Test and implement durable journal operations

**Files:**
- Modify: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Modify: `site-code/runtime-tools/platform_core_deployment.py`

- [x] Test mode `0700` state-root requirements, mode `0600` journal writes, temporary-file creation, file fsync, atomic replace, and parent-directory fsync through injected filesystem operations.
- [x] Test the exact required journal schema, allowed phases, path containment, phase history, unresolved-journal blocking, and rejection of malformed or credential-bearing records.
- [x] Implement journal read/write, state-root validation, deployment-ID path derivation, and phase transitions. Run the focused suite.

## Task 4: Test and implement atomic directory operations

**Files:**
- Modify: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Modify: `site-code/runtime-tools/platform_core_deployment.py`

- [x] Test that unsupported `renameat2` fails before exchange and that no sequential-rename fallback runs.
- [x] Test exchange order, directory fsync, no-replace rename behavior, and preservation of a preexisting rollback destination.
- [x] Add an integration test that calls the real Linux `RENAME_EXCHANGE` syscall on two temporary directories and proves their complete contents trade places atomically.
- [x] Implement the `ctypes` wrapper for `renameat2`, `RENAME_EXCHANGE`, and `RENAME_NOREPLACE`; translate `errno` to safe deployment errors. Run the focused suite, including the integration case.

## Task 5: Test and implement deploy, health, parity, and rollback

**Files:**
- Modify: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Modify: `site-code/runtime-tools/platform_core_deployment.py`

- [x] Test mandatory verifier invocation with the exact full source commit and output directory, supplied manifest digest mismatch, unknown commit, and dirty deployer/verifier source rejection.
- [x] Test that session proof is checked before the first state-directory, journal, or stage write. A fake environment without a passing session callback must leave the fixture root byte-for-byte unchanged.
- [x] Test the successful sequence: verified artifact, old-tree snapshot, verified stage, `swap_intent`, exchange, health, exact parity, `finalize_intent`, no-replace rollback retention, final journal, history record.
- [x] Test health failure and parity failure after exchange. Both must exchange the old tree back, verify the old tree digest, retain the failed candidate, retain the failure record, and return non-zero.
- [x] Test rollback failures and unresolved-journal blocking. Both must retain managed paths and leave an unresolved journal when restoration cannot be proven.
- [x] Implement deployment orchestration with injected verifier, session, health, HTTP, and filesystem operations. Run the focused suite.

## Task 6: Test and implement status and crash recovery

**Files:**
- Modify: `site-code/runtime-tests/tests/test_platform_core_deployment.py`
- Modify: `site-code/runtime-tools/platform_core_deployment.py`

- [x] Test read-only status for supported and unknown path topologies. Confirm status never writes or moves paths.
- [x] Test rollback recovery before exchange, after exchange and before `swapped`, after health, during rollback, and across finalization boundaries.
- [x] Test finalize recovery only for target-active plus old-at-stage or the verified post-rename rollback location. Assert it reruns session, PHP health, HTTP health, and exact parity.
- [x] Test malformed journals, incorrect tree fingerprints, unsupported objects, and unknown topology. Each must stop without choosing a tree or making unsafe changes.
- [x] Implement status classification and explicit rollback/finalize recovery based on actual fingerprints and paths. Run the focused suite.

## Task 7: Add the CLI and WordPress helper

**Files:**
- Create: `site-code/bin/deploy-platform-core.py`
- Create: `site-code/runtime-tools/platform-core-deployment-health.php`
- Modify: `site-code/runtime-tests/tests/test_platform_core_deployment.py`

- [x] Test argument parsing for `deploy`, `status`, and `recover --action=rollback|finalize`; reject unknown targets, wrong roots, filesystem-root paths, omitted artifact authority, and incomplete recovery options.
- [x] Test strict helper/HTTP output validation and failure paths.
- [x] Test HTTP refusal, redirects, wrong response identity, non-success status, and non-HTML response.
- [x] Implement the PHP helper with a strict error handler and output buffer. It bootstraps only the registered WordPress root, verifies runtime identity and Platform Core lifecycle health, and invokes the PE-007 session assertion without writing WordPress data.
- [x] Implement CLI argument handling, exact registered-target binding, committed-source provenance checks, canonical verifier subprocess invocation, PHP helper invocation, and local HTTP checks. Run focused tests and PHP lint.

## Task 8: Protect the mutator and wire the source gate

**Files:**
- Modify: `site-code/runtime-tools/mutating-entrypoints.json`
- Modify: `site-code/bin/check-runtime-mutation-policy.php`
- Modify: `site-code/runtime-tests/tests/run-runtime-mutation-policy-tests.php`
- Modify: `site-code/bin/check-source.sh`

- [x] Test valid and invalid external-mutator records, untracked or missing paths, wrong target, disabled coordinator requirement, and actions outside the approved filesystem allowlist.
- [x] Register the Python deployer as a protected external mutator with target `vgnova-local-10039`, required PE-007 wrapper/session helper, and exact filesystem action allowlist. Classify the PHP health helper as read-only.
- [x] Extend the mutation-policy checker to inventory tracked external Python mutators, validate their protection metadata and bounded action list, and reject duplicates or missing files.
- [x] Add the external-mutator policy tests and deployment Python suite to `site-code/bin/check-source.sh`.
- [x] Run the mutation-policy suite/checker, shell syntax check, and focused Python suite.

## Task 9: Run final source verification and review

**Files:**
- Review: all PE-008 files in this plan.
- Do not modify: Task 8B evidence or the active Local WordPress installation.

- [x] Run `./site-code/bin/check-source.sh` and require every existing and new suite plus the exact artifact verifier to pass.
- [x] Run `git diff --check`; inspect the final diff for scope leaks, unsafe archive APIs, sequential rename fallback, unbounded target behavior, or production crash switches.
- [x] Confirm the test suite used only temporary fixture roots and source-controlled changes for WordPress business data, Bricks, ACF, and live runtime deployment are zero.
- [x] Update the ledger row with the final branch and PR state. PR #61 is open on `feat/pe-008-platform-core-runtime-deployment`; independent review is pending. Do not merge PE-008 in this implementation task.
