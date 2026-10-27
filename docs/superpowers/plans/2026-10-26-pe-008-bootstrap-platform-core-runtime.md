# VGNova Platform Core Runtime Bootstrap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the authorized Local VGNova Platform Core runtime with the exact current-main published artifact under PE-007 mutation coordination, while preserving rollback, protected WordPress state, and Product Contribution runtime-authority alignment.

**Architecture:** Verify the actual published artifact, classify the active runtime as exact-old, exact-new, or unexpected, reconcile source authority under TDD, and perform any required same-filesystem directory replacement inside one PE-007 mutation session. The deployment retains a verified rollback tree and fails closed on any unexplained runtime state.

**Tech Stack:** PHP 8.5, WP-CLI, MariaDB, PE-007 host + dual advisory locks, CPython 3.14.4, GitHub Actions artifacts.

**Spec:** PE-007 coordination contracts plus Platform Core Assembly Contract v1.3.0.

**Current disposition:** PE-008S process changes passed independent review. PE-008T refreshes the existing draft PR #59 to the newest current-main publication, `de90ff061c20b164c127aa97e845e3f192429543`. Runtime deployment remains blocked pending exact-head review and a separate deployment authorization.

## Files and responsibilities

- `site-code/runtime-tests/product-contribution-qa.php` pins the published Platform Core authority used by future QA.
- `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json` stores the exact published manifest bytes as the QA fixture.
- `site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php` locks the new manifest hash and required 55-file metadata without weakening negative cases.
- `site-code/runtime-tools/pe008-protected-state.php` contains pure query-contract and serialization helpers for protected-state fingerprints; it has no automatic execution path.
- `site-code/runtime-tests/fixtures/person-1705-fingerprint-capture.json` and `person-1705-task10a-capture.raw.json` retain the captured Task 10A rows and the exact captured output bytes for offline hash reproduction.
- `site-code/runtime-tests/tests/run-pe008-protected-state-tests.php` pins query projection, ordering, PHP row shapes, serialized byte lengths, digests, and live-QA isolation.
- `site-code/bin/pe008-bootstrap-platform-core-runtime.php` performs only the bound PE-008 directory replacement and rollback.
- `site-code/runtime-tests/tests/run-pe008-bootstrap-platform-core-runtime-tests.php` exercises the helper with temporary trees only.
- `site-code/bin/check-source.sh` runs the offline PE-008 regression suite.
- `site-code/runtime-tools/mutating-entrypoints.json` classifies the helper as a PE-007-guarded mutator.
- `docs/architecture/VGNOVA_PLATFORM_CORE_RUNTIME_BOOTSTRAP_v1.0.0.md` and `docs/architecture/VGNOVA_PLATFORM_CORE_RUNTIME_BOOTSTRAP_MANIFEST_v1.0.0.json` record artifact, runtime, coordination, rollback, and protected-state evidence.

## Tasks

- [x] Confirm the fetched `origin/main` SHA, create the isolated worktree, inspect repository contracts, and record the current source baseline.
- [x] After `origin/main` advanced, inspect every intervening commit, stop before runtime mutation, and select the successful publication for the newest exact `main` SHA.
- [x] Download the intermediate artifact `12645287640` from successful workflow `40311497761` for `50ee4bee1ae1405522df7b67634b8935d4e9d9ed`; preserve its exact manifest as historical provenance alongside the prior 54-file manifest.
- [x] Verify transport ZIP SHA `e90073d5a7a3e2dff0a871090c7ae036d7a8922f60a589d11ca85870965a23ed`, archive SHA `22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9`, and published manifest SHA `b728e69d1488a28601982bfb5e84b4104b11cab5f6c9ae1dcd2a38192ef696a7`; run `check-platform-core-build.py --verify-output` with CPython 3.14.4 and the full source SHA.
- [x] Classify the active plugin against both exact manifests using the existing integrity verifier; stop on any state other than exact-old or exact-new.
- [x] Capture the read-only WordPress identity, coordinator lock state, protected DB definitions, term counts, approved product baseline, and fingerprints for protected runtime records. The Person baseline is exactly one explained record, ID 1705 (`lynette-beer`), from the recorded Task 10A session; preserve and fingerprint its post row, postmeta, taxonomy relationships, and raw ACF values.
- [x] Write the Product Contribution authority regression first and run it against the old 54-file authority to prove the published 55-file artifact is rejected.
- [x] Change the QA authority to the actual `50ee4bee…` publication and replace the fixture with its exact downloaded manifest bytes; PE-008T supersedes this intermediate authority with the newest `de90ff0…` publication.
- [x] Change the stale zero-Person precondition to require the exact Task 10A Person 1705 inventory and post/postmeta/taxonomy fingerprint; add it to the protected pre/post snapshot and prove the guard with source-only tests.
- [x] Write source-safe helper tests first, including uncoordinated execution, exact-old and already-new outcomes, mismatch refusal, unsafe stage refusal, backup collision, both rename failures, rollback verification, and retained backup behavior.
- [x] Implement the PE-008-only helper with fixed target paths and hashes; require the loaded PE-007 guard before any filesystem mutation, use whole-directory renames, verify after each state transition, and restore the old tree on failure.
- [x] Add the helper to the mutation inventory with the required session guard and add its offline suite to `check-source.sh`.
- [x] Run all requested source gates, PE-007 regressions, syntax checks, linters, and diff checks on the rebased current-main source; review the exact diff and confirm Platform Core canonical source bytes did not change.
- [ ] Commit the tested authority transition and helper before deployment; record the tooling commit separately from the artifact source commit.
- [ ] Extract the published archive outside `wp-content/plugins`, verify exact staged parity, reject unsafe filesystem objects, and prove active, stage, and backup parent share a device.
- [ ] Recheck PE-007 status, then run the helper only as the coordinator child in one session with both independent database lock keepers and the host lock.
- [ ] On exact-old state, retain a unique rollback tree, rename the staged artifact into place, verify exact parity, run a fresh no-write WordPress smoke, and prove the deployed session guard accepts the live coordinator session.
- [ ] On exact-new state, skip replacement and preserve all existing runtime files; in either case, prove the deployed guard fails closed outside a coordinator session.
- [ ] Compare the protected WordPress state with its predeployment snapshot, including the unchanged Person 1705 fingerprint and the exact one-record Person inventory; confirm all prohibited mutation counts are zero, rehash the active plugin, and confirm all coordinator locks are released.
- [ ] Write versioned PE-008 evidence without credentials or changes to historical Task 8B documents.
- [ ] Run independent review on artifact provenance, runtime classification, helper rollback and session enforcement, authority transition, postdeployment handshake, protected-state comparison, and evidence accuracy.
- [ ] Open a focused PR without merging; link it to the active thread, verify exact-head GitHub checks, and report the PR head for independent final review.

## PE-008R amendment, 2026-10-26

**Status:** Source-only remediation is in draft PR #59. Runtime deployment remains blocked until the revised exact PR head receives independent review and a later deployment task authorizes execution.

### Aborted pre-mutation QA invocation

The PE-008 attempt included `site-code/runtime-tests/product-contribution-qa.php` in a booted WordPress process. Its bottom-level entrypoint calls `vg_product_contribution_qa_run()` when `ABSPATH` is defined and `VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY` is not defined. The run function called `vg_runtime_mutation_assert_session('vgnova-local-10039')` at line 1344, before creating the run ID/token at line 1345 and before entering the guarded preflight or mutation body. PE-007 rejected the uncoordinated call.

Record the incident as `ABORTED_PRE_MUTATION_QA_INVOCATION`. The entrypoint was invoked once. The mutation body did not start, cleanup did not open, no QA owner rows or Product Contribution rows were created, no deployment occurred, and no persistent WordPress write resulted from the invocation. Do not describe it as a successful live QA run or erase the invocation.

### Person 1705 fingerprint provenance

Task 10A remains the authority for Person 1705. The live record remains ID 1705, type `person`, status `publish`, title `Lynette Beer`, slug `lynette-beer`, empty editorial fields, Mentor and Kenner relationships, no `content_topic` relationships, and only `_masteriyo_has_content=0` in postmeta. Current post, metadata, and relationship rows reproduce the preserved Task 10A capture.

The old expected hashes were not derived from the same inputs as `vg_product_contribution_qa_group_fingerprint()`. Task 10A hashed a single associative post row and nine relationship columns. The Product Contribution group query hashes a one-row post list and six relationship columns. Both paths hash the same one-row postmeta list, so its digest matches. The differences are input shape and projection, not WordPress data drift or serialization nondeterminism.

The classification is `HASH_PROVENANCE_DEFECT`. The source guard now uses the exact group-query digests: posts `f2a980eb64045700dc3a4ca07a7950ec3cb1daea14e4a400ac48e6837b2e9b5b`, postmeta `0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5`, and relationships `1d347313f990ba8063fe34926ef537e85d2dc4bdc575e5369338be45b9bdf7fb`. The regression first failed with 26/27 checks because the guard rejected these reproduced group-query hashes, then passed with 27/27 after the correction.

The new read-only library and its standalone regression use only captured fixture rows. Neither includes nor invokes the live Product Contribution QA file. They pin the Task 10A and group-query SQL, column order, row order, PHP scalar values, canonical serialization, byte lengths, and hashes. The PE-008 helper regression checks the deployment and fingerprinting sources for any QA include or `vg_product_contribution_qa_run()` invocation.

### Independent review rollback correction

Independent review found that failure to rename the stage to the failed-tree path returned before restoring the old backup. A fault-injection regression demonstrated the missing backup-restore attempt, and another review round found empty reservation directories on critical exits. The helper now verifies and reports the still-exact stage as retained, attempts old-tree restoration, and removes empty reservations on critical exits while leaving non-empty reservations intact. The regressions passed after the fixes; the helper suite now has 28 passing cases.

### PE-008R read-only runtime check

The authorized Local VGNova runtime still matches the exact old 54-file manifest: tree `856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56`, with zero missing, extra, content-mismatched, mode-mismatched, symlink, or unsupported objects. Person inventory remains exactly ID 1705. Product Contribution and Event Participation counts remain zero, and the Product Contribution QA owner-meta query returned no rows.

Coordinator status is free: host filesystem lock is not held, database lock A is free, and database lock B is free. No mutation session was acquired during PE-008R.

The superseded stage `/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-20261026T105633Z` was not used or modified. No Platform Core deployment, ACF write, taxonomy write, post write, Bricks write, FluentSnippet write, rewrite flush, plugin activation/deactivation, or successful live QA run occurred. The aborted pre-mutation QA invocation count is one.

### PE-008R remaining gates

- [x] Verify exact current baseline `50ee4bee1ae1405522df7b67634b8935d4e9d9ed` and exact published artifact `12645287640` from workflow `40311497761`.
- [x] Reproduce the Person 1705 hashes offline from the preserved capture and compare them with a separate read-only live query; do not use the QA harness for either check.
- [x] Add the source-safe fingerprint library, fixed capture fixtures, SQL/type/serialization assertions, and anti-live-QA regression.
- [x] Correct the Person baseline guard under TDD; retain the actual RED and GREEN results above.
- [x] Confirm the runtime remains exact-old, confirm Person 1705's logical state, and confirm both database advisory locks and the host lock are free.
- [x] Run all PE-008R source gates without live QA.
- [x] Complete a first independent review of the source and deployment helper. Resolve the uncovered stage-preservation rollback issue and keep runtime deployment blocked.
- [ ] Commit and push the rollback correction to draft PR #59, obtain a fresh independent review of the revised exact source, and verify exact-head GitHub checks.
- [ ] Stop after reporting the exact draft PR head. Do not deploy or merge.

### PE-008S source-only remediation

- [x] Replace the in-process WordPress session check with a fresh WP-CLI child that uses the staged `RuntimeMutationSession.php`; wait for the child to exit before creating the failed-tree reservation or renaming any directory.
- [x] Keep the filesystem-mutating deployment parent WordPress-free; suppress plugins, themes, MU plugins, and WP-Cron in the pre-rename guard child.
- [x] Pass the session environment and FD3 to guard and smoke children through the coordinator-safe descriptor wrapper; carry a DB socket override in PHP settings and the approved socket environment key without credentials.
- [x] Use a separate fresh WP-CLI process for the post-swap smoke.
- [x] Map `ROLLED_BACK` to exit 3, `BLOCKED` to exit 1, and `BLOCKED_CRITICAL` to exit 2; keep successful deployment and already-exact skip at exit 0.
- [x] Add temporary-fixture subprocess regressions for FD3 ownership, descriptor filtering, child termination before the first rename, socket propagation, and every terminal-state exit code.
- [x] Add narrow mutation-policy checks for the PE-008 isolated child guard boundary and retain direct session checks for all other mutators.
- [x] Record the PE-008S changes and TDD RED/GREEN results in the recovery evidence.
- [x] Run every required source gate, syntax/lint check, and diff check on the PE-008S code.
- [ ] Push the existing PR #59 branch; verify exact-head GitHub checks; stop with PR #59 still draft and unmerged.
- [ ] Do not deploy Platform Core or run live Product Contribution QA.

## PE-008T provenance refresh

**Status:** Source-only. PR #59 remains draft and unmerged. The runtime remains untouched and deployment remains blocked.

- [x] Fetch `origin/main`, verify it is `de90ff061c20b164c127aa97e845e3f192429543`, and rebase the existing PE-008 branch onto it.
- [x] Inspect `50ee4bee…` to `de90ff0…`; the net source diff adds documentation only.
- [x] Download the actual workflow `40342642997` artifact `12659772564`; verify transport ZIP `22128391bfb1c9ee040b929cc47385db71980d4d8dcacc0171d8b03fef413d7f`, manifest `7cd38e758babddf495e484013d9345750af6f39689bc428f67d89a814949a932`, archive `22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9`, tree `5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a`, and 55 files.
- [x] Verify the downloaded files with CPython 3.14.4 and `check-platform-core-build.py --ref de90ff061c20b164c127aa97e845e3f192429543 --verify-output`.
- [x] Preserve the exact `50ee4bee…` manifest at `voelgoed-platform-core-pe008-50ee.manifest.json`; replace the active manifest fixture with the exact downloaded `de90ff0…` bytes.
- [x] Update Product Contribution QA and deployment-helper authority constants to the `de90ff0…` publication without changing PE-008S deployment mechanics.
- [x] Add a regression proving the `50ee4bee…` and `de90ff0…` published manifests differ only in `source_commit` and `source_ref_requested`, while schema, inventory, tree, and archive match.
- [x] Run all PE-008S and source gates and commit the authority/tooling update as `cb6d76d78952fb568edde36e95930e93595b56d7`.
- [ ] Push the rebased branch to PR #59 and inspect exact-head GitHub checks.
- [ ] Stop for independent review. Do not deploy, merge, or mark the draft ready.
