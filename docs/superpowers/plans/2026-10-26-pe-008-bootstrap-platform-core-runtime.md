# VGNova Platform Core Runtime Bootstrap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the authorized Local VGNova Platform Core runtime with the exact current-main published artifact under PE-007 mutation coordination, while preserving rollback, protected WordPress state, and Product Contribution runtime-authority alignment.

**Architecture:** Verify the actual published artifact, classify the active runtime as exact-old, exact-new, or unexpected, reconcile source authority under TDD, and perform any required same-filesystem directory replacement inside one PE-007 mutation session. The deployment retains a verified rollback tree and fails closed on any unexplained runtime state.

**Tech Stack:** PHP 8.5, WP-CLI, MariaDB, PE-007 host + dual advisory locks, CPython 3.14.4, GitHub Actions artifacts.

**Spec:** PE-007 coordination contracts plus Platform Core Assembly Contract v1.3.0.

## Files and responsibilities

- `site-code/runtime-tests/product-contribution-qa.php` pins the published Platform Core authority used by future QA.
- `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json` stores the exact published manifest bytes as the QA fixture.
- `site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php` locks the new manifest hash and required 55-file metadata without weakening negative cases.
- `site-code/bin/pe008-bootstrap-platform-core-runtime.php` performs only the bound PE-008 directory replacement and rollback.
- `site-code/runtime-tests/tests/run-pe008-bootstrap-platform-core-runtime-tests.php` exercises the helper with temporary trees only.
- `site-code/bin/check-source.sh` runs the offline PE-008 regression suite.
- `site-code/runtime-tools/mutating-entrypoints.json` classifies the helper as a PE-007-guarded mutator.
- `docs/architecture/VGNOVA_PLATFORM_CORE_RUNTIME_BOOTSTRAP_v1.0.0.md` and `docs/architecture/VGNOVA_PLATFORM_CORE_RUNTIME_BOOTSTRAP_MANIFEST_v1.0.0.json` record artifact, runtime, coordination, rollback, and protected-state evidence.

## Tasks

- [x] Confirm the fetched `origin/main` SHA, create the isolated worktree, inspect repository contracts, and record the current source baseline.
- [x] Download artifact `12634663495` from successful workflow `40287961867`; preserve the transport ZIP, embedded archive, published manifest, and prior 54-file manifest outside the active plugin directory.
- [x] Verify transport, archive, and manifest hashes, then run `check-platform-core-build.py --verify-output` with CPython 3.14.4 and the full source SHA.
- [x] Classify the active plugin against both exact manifests using the existing integrity verifier; stop on any state other than exact-old or exact-new.
- [x] Capture the read-only WordPress identity, coordinator lock state, protected DB definitions, term counts, approved product baseline, and fingerprints for protected runtime records. The Person baseline is exactly one explained record, ID 1705 (`lynette-beer`), from the recorded Task 10A session; preserve and fingerprint its post row, postmeta, taxonomy relationships, and raw ACF values.
- [x] Write the Product Contribution authority regression first and run it against the current authority to prove the 55-file artifact is rejected.
- [x] Change the QA authority and replace the fixture with the exact downloaded manifest bytes; update assertions and prove the authority transition passes while existing negative cases still fail closed.
- [x] Write source-safe helper tests first, including uncoordinated execution, exact-old and already-new outcomes, mismatch refusal, unsafe stage refusal, backup collision, both rename failures, rollback verification, and retained backup behavior.
- [x] Implement the PE-008-only helper with fixed target paths and hashes; require the loaded PE-007 guard before any filesystem mutation, use whole-directory renames, verify after each state transition, and restore the old tree on failure.
- [x] Add the helper to the mutation inventory with the required session guard and add its offline suite to `check-source.sh`.
- [x] Run all requested source gates, PE-007 regressions, syntax checks, linters, and diff checks; review the exact diff and confirm Platform Core canonical source bytes did not change.
- [ ] Commit the tested authority transition and helper before deployment; record the tooling commit separately from the artifact source commit.
- [ ] Extract the published archive outside `wp-content/plugins`, verify exact staged parity, reject unsafe filesystem objects, and prove active, stage, and backup parent share a device.
- [ ] Recheck PE-007 status, then run the helper only as the coordinator child in one session with both independent database lock keepers and the host lock.
- [ ] On exact-old state, retain a unique rollback tree, rename the staged artifact into place, verify exact parity, run a fresh no-write WordPress smoke, and prove the deployed session guard accepts the live coordinator session.
- [ ] On exact-new state, skip replacement and preserve all existing runtime files; in either case, prove the deployed guard fails closed outside a coordinator session.
- [ ] Compare the protected WordPress state with its predeployment snapshot, including the unchanged Person 1705 fingerprint and the exact one-record Person inventory; confirm all prohibited mutation counts are zero, rehash the active plugin, and confirm all coordinator locks are released.
- [ ] Write versioned PE-008 evidence without credentials or changes to historical Task 8B documents.
- [ ] Run independent review on artifact provenance, runtime classification, helper rollback and session enforcement, authority transition, postdeployment handshake, protected-state comparison, and evidence accuracy.
- [ ] Open a focused PR without merging; link it to the active thread, verify exact-head GitHub checks, and report the PR head for independent final review.
