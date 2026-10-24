# PE-006A Runtime Artifact Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update PR #51 so its historical Task 8C evidence is reconciled to the published PE-005 artifact, the live QA harness rejects Platform Core drift before mutation, and Product cleanup plus its source gate cover lookup-table residue.

**Architecture:** Keep Task 8C's execution baseline and runtime results unchanged. Pin the actual PE-005 artifact manifest as a source-controlled fixture, use a standalone read-only PHP helper to compare the active plugin directory against its inventory and canonical tree digest, and call that helper during QA preflight before the mutation window. Add source-safe fixture tests for the integrity gate and a lookup-row cleanup regression test, then run the read-only current-state preflight and record it in the SemVer runtime manifest.

**Tech Stack:** PHP 8.5, CPython 3.14.4, Bash, GitHub Actions, WP-CLI read-only commands.

---

## File map

- Add `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json` as a byte-for-byte copy of the verified published manifest.
- Add `site-code/runtime-tests/platform-core-runtime-integrity.php` for standalone manifest validation, safe filesystem inventory, mode checks, hashes, and the PE-005 canonical tree digest.
- Modify `site-code/runtime-tests/product-contribution-qa.php` to load the helper, enforce the pinned content contract in preflight, and record the observed parity report.
- Add `site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php` for temporary-directory tests of the production helper and pre-mutation guard.
- Modify `site-code/runtime-tests/product-contribution-qa.php` and `site-code/runtime-tests/tests/run-product-contribution-qa-cleanup-tests.php` to detect remaining `wc_product_meta_lookup` rows for owned Product IDs.
- Modify `site-code/bin/check-source.sh` to execute the setup, cleanup, and new integrity safety runners.
- Rename the existing Task 8C manifest to `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json`, preserve Task 8C chronology, and add PE-006A artifact/runtime/read-only evidence.
- Update only repository references that name the old unversioned manifest.
- Add this plan at `docs/superpowers/plans/2026-10-24-pe-006a-runtime-artifact-reconciliation.md`.

## Task 1: Preserve the published artifact and historical branch

**Files:**
- Add: `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json`
- Modify: `docs/superpowers/plans/2026-10-24-pe-006a-runtime-artifact-reconciliation.md`

- [x] Fetch `origin/main` and the existing PR #51 branch.
- [x] Confirm `origin/main` is `c6b01f86ddee1a994cd8d41aa8df963bec883576` and PR #51's historical Task 8C commit is `a9f5991a494b28929688be2e4c3a32c6cc3e3d03`.
- [x] Merge the exact current `main` SHA into PR #51 without rebasing the historical commit.
- [x] Download artifact `12569672524` from workflow `40117786590`; verify metadata `head_sha`, transport digest, manifest digest, archive digest, and exact output with CPython 3.14.4.
- [x] Copy the verified published manifest into the source fixture and check that its SHA-256 remains `d95f77936381226d364faa0ae23fb4b3cb762cae766d5b0e88ce3e51e0c12e0f`.

## Task 2: Prove the current Local runtime identity and artifact parity

**Files:**
- Evidence only: `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json`

- [x] Use the Task 8C WP-CLI executable, PHP 8.5 binary, VGNova root, URL, and Local MySQL socket override.
- [x] First read only the runtime identity and require the exact authorized root, `localhost:10039`, local database on `localhost`, and active Platform Core plugin path.
- [x] Record WP-CLI PHP and web/FPM PHP separately when the latter can be determined from the running Local service without changing runtime files.
- [x] Compare every active plugin file to the downloaded artifact manifest: path, byte size, SHA-256, executable mode, regular-file status, and symlink/object status.
- [x] Recompute the PE-005 canonical tree hash from `<git-mode> <sha256> <relative-path>\n`, sorted by UTF-8 path bytes.
- [x] Read the WooCommerce lookup table for historical owned Product IDs `1535`, `1545`, `1547`, `1549`, and `1557`; require zero rows and do not repair residue.
- [x] If artifact parity is exact, record `SKIPPED_ALREADY_EXACT`. If any mismatch exists, stop runtime work and record exact paths under `BLOCKED_MISMATCH`.

## Task 3: Add failing tests for the Platform Core integrity guard

**Files:**
- Add: `site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php`
- Add: `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json`

- [x] Build temporary fixture directories from in-memory records; never point a unit test at WordPress or the installed plugin.
- [x] Test acceptance of an exact tree and rejection of modified, missing, and extra files.
- [x] Test rejection of symlinks, unsupported filesystem objects, executable-mode mismatches, non-executable-mode mismatches, unreadable/read failures, and malformed manifest records.
- [x] Exercise the actual preflight integrity callback through `vg_product_contribution_qa_guarded_run()` and assert each rejection leaves mutation and cleanup false and never calls body or cleanup.
- [x] Run the new test runner and confirm the tests fail before implementing the helper.

## Task 4: Implement the PE-005 canonical runtime integrity helper

**Files:**
- Add: `site-code/runtime-tests/platform-core-runtime-integrity.php`
- Modify: `site-code/runtime-tests/product-contribution-qa.php`

- [x] Validate the pinned fixture schema and require its file count and tree hash to match the reviewed PE-005 content contract.
- [x] Walk the runtime directory without following symlinks; reject symlinks and unsupported filesystem objects.
- [x] Compare expected and actual paths, sizes, SHA-256 digests, and Git executable/non-executable modes; fail on read or hash errors.
- [x] Recompute the canonical PE-005 tree representation using UTF-8 bytewise path ordering and require the pinned tree SHA-256.
- [x] Call the integrity check immediately after runtime identity validation and before any remaining preflight checks that could precede `mutation_started = true`.
- [x] Include the observed file count, tree digest, and mismatch details in the QA preflight report.
- [x] Run the integrity test runner and confirm all cases pass.

## Task 5: Verify owned WooCommerce lookup cleanup

**Files:**
- Modify: `site-code/runtime-tests/product-contribution-qa.php`
- Modify: `site-code/runtime-tests/tests/run-product-contribution-qa-cleanup-tests.php`

- [x] Add a read-only exact-ID count against the prefixed `wc_product_meta_lookup` table after owned Product deletion.
- [x] Treat query errors as cleanup failures and report the Product ID, table, and residual count.
- [x] Include lookup residue in the final owned-record audit even when the post itself is gone.
- [x] Add a clean deletion case, a retained owned lookup row case, an unrelated lookup row control, and a lookup-query failure case.
- [x] Run the cleanup test runner and confirm the lookup-residue regression test fails before the fix and passes after it.

## Task 6: Add the safety runners to the required source gate

**Files:**
- Modify: `site-code/bin/check-source.sh`

- [x] Execute the Product Contribution setup, cleanup, and integrity runners as explicit PHP commands.
- [x] Keep the live `product-contribution-qa.php` harness out of CI and preserve the existing Dashboard checks, Canonical source checks, VG Nova Platform Gate, and PE-005 verifier.
- [x] Run `bash -n`, ShellCheck, and `./site-code/bin/check-source.sh`.

## Task 7: Reconcile current read-only WordPress state and manifest provenance

**Files:**
- Rename the existing Task 8C manifest to: `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json`
- Modify: references to the unversioned manifest found by `rg`

- [x] After the source guard exists, load the QA file in library-only mode through the exact authorized WP-CLI bootstrap and call only its read-only preflight function.
- [x] Verify the three target ACF database definitions, four Product Role terms, zero role relationships, zero persistent Product Contributions, Persons and Event Participations, zero legacy Product People override rows, readable protected fingerprints, and approved 20-Product baseline.
- [x] Do not sync ACF, seed terms, call the mutating QA runner, or write to the database.
- [x] Rename the manifest without duplicating it; preserve `execution_baseline` and all Task 8C historical fields.
- [x] Add the historical Task 8C commit, PE-006A review baseline, canonical artifact identity, exact runtime parity, deployment decision, read-only preflight, and historical lookup-residue results as separate PE-006A provenance.
- [x] Label the historical Task 8B digest algorithm as `relative-path + NUL + file SHA-256 + newline`; label PE-005's canonical tree hash algorithm separately.
- [x] Validate JSON and search for stale unversioned-manifest references.

## Task 8: Verify, update PR #51, and prepare for independent review

**Files:**
- All PE-006A files listed above

- [x] Run `./voelgoed-platform-core/bin/check.sh` and `./site-code/bin/check-source.sh` under PHP 8.5.x and CPython 3.14.4.
- [x] Explicitly run all three Product Contribution QA safety runners, PHP lint, Python syntax/AST checks, `bash -n`, ShellCheck, actionlint, and `git diff --check`.
- [x] Confirm the PE-005 exact-output verifier remains green and no live QA entry point was run.
- [ ] Review the diff and confirm no WordPress or runtime mutation occurred.
- [ ] Commit and push the changes to the existing PR #51 branch only; keep PR #51 unmerged.
- [ ] Update PR #51's description to distinguish historical Task 8C live execution, PE-006A artifact reconciliation, and zero PE-006A runtime mutation.
- [ ] On the exact new PR head, verify Dashboard checks, Canonical source checks, and VG Nova Platform Gate succeed; confirm artifact publication is skipped and the strict ruleset reports the branch up to date with `main`.
- [ ] Inspect the Canonical source job log for all three safety runners and the PE-005 reproducibility verifier.
- [ ] Resolve the delegated independent review findings before reporting readiness.

## Stop conditions

- Stop before any WordPress read if the published artifact metadata or exact-output verifier does not match the stated PE-005 authority.
- Stop before further runtime access if runtime identity differs from the authorized VGNova installation.
- If runtime Platform Core parity differs, record every missing, extra, content-mismatched, mode-mismatched, symlink, or unsupported object and set `BLOCKED_MISMATCH`; do not deploy or repair.
- If current `main` changes, inspect every intervening commit and verify its latest published artifact before continuing.
- Stop and report any Git merge conflict without resolving it.
- Never run the mutating Task 8C QA, ACF sync, role seeding, or direct SQL writes during PE-006A.
