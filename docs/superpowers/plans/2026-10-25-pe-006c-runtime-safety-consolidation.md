# PE-006C runtime safety consolidation implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a published-artifact integrity gate before Product Contribution runtime QA can mutate WordPress, run its source-safe safety suites in canonical CI, and retain Task 8C provenance in a versioned manifest.

**Architecture:** Keep the merged PR #54 live QA history intact. Add a pure PHP verifier that compares a plugin directory to the pinned PE-005 manifest and returns exact file, content, mode, and canonical tree evidence. Invoke it after runtime identity validation and before any other preflight work can open the mutation window. Add temporary-fixture tests, wire all three Product Contribution safety runners into `check-source.sh`, and add separate PE-006C read-only provenance to the versioned runtime manifest.

**Tech Stack:** PHP 8.5, CPython 3.14.4, Bash, Git, GitHub Actions.

---

## Repository baseline

- Repository: `JCSchoeman96/vgnova`.
- `origin/main`: `7af938ac6f4422b1fa779b52444121e7a9293995`, merge commit for PR #54.
- Open PR #51: head `723e9401a877f14832e5fa1e1255be199e5aefef`. Treat it as an evidence source only.
- Active main ruleset: `VG Nova main protection`, strict required check `VG Nova Platform Gate`; current run `40167647912` succeeded.
- The preserved PR #51 patch is `/tmp/pe006a-pr51-local.patch`; expected SHA-256 is `a96cee9751a28ab655509ce4a0711a6f5dda65f21d4d139b2cd50f571816aba5`.
- The root checkout has a preexisting modified `AGENTS.md`. This plan and implementation use a separate worktree at `.worktrees/pe-006c-runtime-safety-consolidation`.
- Baseline `./voelgoed-platform-core/bin/check.sh` passed with 127 dashboard tests. Baseline `./site-code/bin/check-source.sh` passed, including the PE-005 reproducibility verifier.
- The current source gate does not invoke Product Contribution setup, cleanup, or integrity safety runners. The merged runtime manifest is still unversioned.

## Published authority

Use the actual downloaded files from GitHub artifact `12588965615`, workflow `40167647912`, name `voelgoed-platform-core-7af938ac6f4422b1fa779b52444121e7a9293995`.

```text
source_commit: 7af938ac6f4422b1fa779b52444121e7a9293995
schema_version: 3
file_count: 54
tree_sha256: 856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56
archive_sha256: 9d5fd288025703fe4f71ed1f3ee264cd3fc9c0b61cf74d137b40e941bc2e2243
manifest_sha256: c87d339ebe83203a82a3f02b27de9854be9e360835da9893077af9c9c318c1ee
transport_zip_sha256: 8129e43c2f72361ccac5bcdcc4c00f505f9ddef7b93a45d4ff22d143336744b4
```

The transport digest identifies the outer GitHub ZIP only. Runtime identity uses the published manifest and canonical PE-005 tree/archive values.

## Files and responsibilities

- `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json`: exact published manifest bytes, not a locally regenerated manifest.
- `site-code/runtime-tests/platform-core-runtime-integrity.php`: read-only, WordPress-independent manifest and filesystem comparison.
- `site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php`: temporary-tree regression suite, including failure at the mutation boundary.
- `site-code/runtime-tests/product-contribution-qa.php`: pin authority constants, call the integrity check immediately after runtime identity validation, preserve the report in QA evidence, and keep the old Task 8B hash historical-only.
- `site-code/bin/check-source.sh`: execute Product Contribution setup, cleanup, and integrity safety runners while keeping the live WordPress QA excluded.
- `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json`: versioned historical Task 8C record plus separate PE-006C read-only reconciliation evidence.
- `docs/superpowers/plans/2026-10-25-pe-006c-runtime-safety-consolidation.md`: this implementation sequence and acceptance record.
- `docs/superpowers/plans/2026-10-23-product-contribution-reverse-product.md`: update genuine instructions that point at the current manifest filename. Keep dated audit claims about what was or was not created at their original time.

## Task 1: Pin the published manifest

**Files:** Create `site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json`.

- [x] Copy `/tmp/pe006a-r-current-main-artifact/voelgoed-platform-core.manifest.json` byte-for-byte into the fixture.
- [x] Run `sha256sum site-code/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json` and require `c87d339ebe83203a82a3f02b27de9854be9e360835da9893077af9c9c318c1ee`.
- [x] Parse the fixture and require schema 3, source commit `7af938ac6f4422b1fa779b52444121e7a9293995`, 54 records, and the expected tree and archive hashes.

## Task 2: Write integrity tests first

**Files:** Create `site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php`.

- [x] Build small plugin directories under `sys_get_temp_dir()` and remove them in `finally`. Create their synthetic manifest from those fixture bytes so the suite never points to WordPress or the active plugin.
- [x] Test exact tree acceptance, modified content, missing file, extra file, executable-mode mismatch in both directions, symlinked file, symlinked plugin root with a trailing slash, empty/filesystem-root path rejection, unsupported filesystem object, unreadable/read failure, malformed JSON, duplicate manifest path, unsafe relative path, wrong source commit, and wrong canonical tree hash.
- [x] Also test wrong artifact name, wrong manifest digest, wrong schema, invalid file mode, wrong archive digest, and post-cleanup Platform Core drift.
- [x] Test the guarded mutation boundary by running the real integrity assertion in the preflight callback passed to `vg_product_contribution_qa_guarded_run()`. On a failed integrity report, require `mutation_started=false`, `cleanup_attempted=false`, and zero body and cleanup calls.
- [x] Run the integrity runner before implementation. It failed with `FAIL Platform Core integrity helper is missing.` After implementing the verifier, the mutation-window test exposed the missing guard assertion. The initial final runner passed 22/22. Independent review then found that a trailing slash could bypass root-symlink rejection. The new trailing-slash regression failed before the fix (22/23) and passed after root normalization (23/23); empty and filesystem-root inputs are also rejected before traversal (24/24).

## Task 3: Implement the pure integrity verifier

**Files:** Create `site-code/runtime-tests/platform-core-runtime-integrity.php`.

- [x] Define this pure report API and keep each returned field JSON-serializable:

```php
/** @return array<string, mixed> */
function vg_product_contribution_qa_platform_core_integrity_report(
    string $plugin_root,
    string $manifest_path,
    array $authority,
    ?callable $read_file = null
): array;
```

The `$authority` keys are `artifact_name`, `source_commit`, `schema_version`, `file_count`, `tree_sha256`, `archive_sha256`, and `manifest_sha256`. The report keys are `exact`, `artifact_name`, `artifact_source_commit`, `file_count`, `missing`, `extra`, `mismatched`, `mode_mismatched`, `symlinks`, `unsupported`, `unreadable`, `directory_errors`, `manifest_errors`, `runtime_tree_sha256`, `expected_tree_sha256`, and `observed_manifest_sha256`.

- [x] Return `exact=false` with a specific report entry for every rejected condition. Do not throw on expected filesystem/manifest failures; the caller rejects a non-exact report before mutation.
- [x] Validate exact manifest bytes by SHA-256, JSON shape, schema 3, source commit, file count, archive hash, expected tree hash, safe unique paths, sizes, SHA-256 values, and Git modes `100644` or `100755`.
- [x] Validate the artifact name equals `voelgoed-platform-core-` plus the pinned source commit. The published manifest has no artifact-name field, so derive this name from its verified source commit and the GitHub publication naming rule.
- [x] Walk with `lstat()` and `scandir()`. Never follow symlinks. Reject symlinks, non-regular/non-directory objects, unreadable files/directories, hash failures, and directory enumeration failures.
- [x] Derive runtime Git mode from executable bits. Hash canonical records in bytewise `strcmp()` order by UTF-8 slash-separated relative path using `<git-mode> <sha256> <relative-path>\n`.
- [x] Run the integrity runner and require all cases to pass. Keep malformed authority and filesystem errors fail-closed. Result: 22/22.

## Task 4: Bind the guard before mutation

**Files:** Modify `site-code/runtime-tests/product-contribution-qa.php` and the integrity runner.

- [x] Add these pinned authority values for the actual artifact and resolve the fixture relative to `__DIR__`:

```php
const VG_PRODUCT_CONTRIBUTION_QA_PLATFORM_CORE_AUTHORITY = [
    'artifact_name' => 'voelgoed-platform-core-7af938ac6f4422b1fa779b52444121e7a9293995',
    'source_commit' => '7af938ac6f4422b1fa779b52444121e7a9293995',
    'schema_version' => 3,
    'file_count' => 54,
    'tree_sha256' => '856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56',
    'archive_sha256' => '9d5fd288025703fe4f71ed1f3ee264cd3fc9c0b61cf74d137b40e941bc2e2243',
    'manifest_sha256' => 'c87d339ebe83203a82a3f02b27de9854be9e360835da9893077af9c9c318c1ee',
];
```
- [x] Immediately after `vg_product_contribution_qa_validate_identity()`, compute and validate Platform Core parity. Do this before the existing CPT, taxonomy, ACF, role, database-count, protected-state, and product-inventory checks.
- [x] Throw on any non-exact report before `vg_product_contribution_qa_guarded_run()` sets `mutation_started=true`. Keep `cleanup_attempted=false` when preflight rejects.
- [x] Put the full parity report in the QA context so a future authorized live run records file count, tree, missing/extra files, content/mode mismatches, symlinks, unsupported objects, and read failures.
- [x] Remove the Task 8B hash from active authority. Preserve its value only in the historical Task 8C manifest, with its old relative-path/NUL/file-hash/newline algorithm labeled.
- [x] Run setup, cleanup, and integrity safety runners. No mutating harness entrypoint ran.

## Task 5: Preserve cleanup checks and wire the source gate

**Files:** Modify `site-code/bin/check-source.sh`; modify the cleanup runner only if review proves the separate aggregate lookup audit adds coverage absent in PR #54.

- [x] Retain PR #54 checks for `wc_product_meta_lookup`, optional `wc_product_attributes_lookup`, lookup query failures, endpoint restoration in `finally`, and protected-state comparison.
- [x] Do not apply the PR #51 patch wholesale. Add a separate final aggregate lookup audit only after a regression test proved it catches lookup residue when the Product post is already absent.
- [x] Add these exact commands to `check-source.sh`: `php site-code/runtime-tests/tests/run-product-contribution-qa-setup-tests.php`, `php site-code/runtime-tests/tests/run-product-contribution-qa-cleanup-tests.php`, and `php site-code/runtime-tests/tests/run-product-contribution-qa-platform-core-integrity-tests.php`.
- [x] Keep `site-code/runtime-tests/product-contribution-qa.php` out of CI. Run `bash -n site-code/bin/check-source.sh` and `./site-code/bin/check-source.sh`; inspect output for all three runner summaries and the PE-005 reproducibility verifier.

## Task 6: Version and reconcile provenance

**Files:** Rename the runtime manifest to `docs/architecture/PRODUCT_CONTRIBUTION_RUNTIME_MANIFEST_v1.0.0.json`; update genuine current-manifest references.

- [x] Preserve the Task 8C execution baseline `caaba61069eabe347a2a0e0949fce41106e76be0`, historical PR head `8386a0e44d65e6b625d8c10bd8ac13cc0a91c7f6`, merged commit `7af938ac6f4422b1fa779b52444121e7a9293995`, two targeted ACF keys, two role setup runs with zero created, 16 passed live assertions, cleanup pass, and protected-state pass.
- [x] Add a separate PE-006C object. State that this change is source-only, made zero runtime writes, did not rerun live QA, found exact artifact/runtime parity, and chose `SKIPPED_ALREADY_EXACT`.
- [x] Record artifact workflow, ID/name, source commit, schema, count, tree/archive/manifest hashes, and transport digest with the transport digest labeled separately.
- [x] Record the two read-only ACF row IDs, `post_modified`, `post_modified_gmt`, raw `post_content` digests, stored `query_var`, and ACF `modified` metadata. State that `1792865666` is Local JSON sync metadata, not the database write timestamp.
- [x] Update references that instruct readers to use the current manifest filename. Keep dated statements about what had not yet been created at the time of those audits.
- [x] Parse the versioned JSON and search for live references to the unversioned current manifest path. Do not retain a duplicate unversioned manifest.

## Task 7: Full local verification

**Files:** No new files beyond the tasks above.

- [x] Run `./voelgoed-platform-core/bin/check.sh` (127 dashboard tests passed).
- [x] Run `./site-code/bin/check-source.sh`.
- [x] Run the setup, cleanup, and integrity runners explicitly: 5/5, 9/9, and 24/24 passed.
- [x] Run `python3 -B site-code/bin/check-platform-core-build.py --ref HEAD` under CPython 3.14.4; exact-output and reproducibility checks passed.
- [x] Run PHP lint, JSON parsing, Python AST checks, `bash -n`, ShellCheck, actionlint, and `git diff --check`.
- [x] Confirm the working tree diff changes no Platform Core source and contains no runtime/deployment operations.

## Task 8: Independent review and PR handoff

**Files:** Review only, then fix findings in this scope and rerun affected checks.

- [x] Obtain four independent read-only reviews against the implementation head: integrity guard, QA safety/cleanup, manifest/provenance, and CI source-gate integration. All reported no findings after the symlink-path fix.
- [x] Resolve the material trailing-slash symlink finding with a failing regression test, fix it, and rerun the integrity suite and full source gate.
- [x] Commit and push `chore/pe-006c-runtime-safety-consolidation` from the exact `7af938ac6f4422b1fa779b52444121e7a9293995` base.
- [x] Open PR #55 titled `test: harden Product Contribution runtime safety gate`, link it to the task thread, and leave it open and unmerged.
- [x] Verify Dashboard checks, Canonical source checks, and VG Nova Platform Gate pass on the implementation head; artifact publishing skipped.
- [x] Inspect the Canonical source log and confirm setup 5/5, cleanup 9/9, integrity 24/24, and PE-005 exact-output verification.
- [x] Compare PR #51-only code, tests, and provenance with PR #55. All hardening is retained or superseded by PR #54/PR #55; the earlier Task 8C run remains historical-only. PR #51 stays open.

## Runtime safety boundary

PE-006C permits only source, test-fixture, GitHub-artifact, and Git changes. It does not run the live Product Contribution QA harness, ACF synchronization, role seeding, database writes, Bricks or FluentSnippet writes, or Platform Core deployment. Any runtime mutation observed during this task stops the work.
