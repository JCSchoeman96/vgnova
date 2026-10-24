# PE-005 Platform Core assembly implementation plan

> **For agentic workers:** Implement the approved PE-005 brief task by task. Keep generated artifacts out of Git and do not access WordPress runtime state.

**Goal:** Build a reproducible Platform Core plugin artifact from one exact Git commit and publish it only from successful `main` source checks.

**Architecture:** `voelgoed-platform-core/` is the tracked assembly base. The three tracked `site-code/voelgoed-platform-core/includes/{Taxonomy,Event,Product}` trees are the only overlays. The tracked `integration.patch` is applied once to a disposable staging copy. The builder reads Git blobs for the resolved commit, validates the assembled bootstrap, lints the assembled PHP on PHP 8.5, writes a normalized tarball and manifest to a new output directory, and never reads WordPress runtime state.

**Tech Stack:** Python 3 standard library, Git object commands, PHP 8.5 CLI, Bash, GitHub Actions, ShellCheck, actionlint.

---

## Files and ownership

- `site-code/bin/build-platform-core.py`: Git-object reader, clean-base gate, patch application, overlay/bootstrap checks, PHP lint, deterministic archive and manifest writer.
- `site-code/bin/check-platform-core-build.py`: two-build reproducibility check, archive/manifest re-verification, isolated negative fixtures, and cleanup proof.
- `site-code/bin/check-source.sh`: invokes the new verifier and keeps the existing patch applicability check and source suites.
- `.github/workflows/voelgoed-dashboard.yml`: rebuilds and uploads the two canonical files only after `VG Nova Platform Gate` succeeds on a push to `main`; preserves all three governed check names.
- `.gitignore`: ignores `/dist/` only.
- `site-code/voelgoed-platform-core/README.md`: replaces manual patch-and-copy deployment instructions with the artifact workflow while preserving domain history.
- `docs/architecture/PLATFORM_CORE_ASSEMBLY_CONTRACT_v1.0.0.md`: canonical assembly and provenance contract.
- `docs/superpowers/plans/2026-10-24-pe-005-platform-core-assembly.md`: this implementation sequence.

## Task 1: Lock the contract and acceptance checks

**Files:** Create the contract and this plan.

- [x] Record the base, three overlays, patch input, output layout, failure rules, tree hash, manifest fields, runtime boundary, and main-only publication contract.
- [x] Confirm the existing repository has 20 tracked overlay PHP files, 12 patch-owned lifecycle imports and registrations, a clean root bootstrap, and a patch that applies to the tracked root tree.

## Task 2: Write the verifier's negative-fixture checks first

**Files:** Create `site-code/bin/check-platform-core-build.py`.

- [x] Add temporary Git fixture helpers. Each fixture copies only committed assembly inputs and the builder, commits them with a local test identity, and is removed by `TemporaryDirectory`.
- [x] Add cases for an invalid patch, already-integrated root include, absent overlay PHP file, patch include to an absent module, duplicate bootstrap include, non-empty output directory, artifact content tampering, an entrypoint return before module includes, and a component collection reset before dispatch.
- [x] Run the verifier with a red regression fixture before the review fixes. The binary-patch fixture succeeded against the old builder, proving the path-allowlist gap.

## Task 3: Implement commit-pinned assembly

**Files:** Create `site-code/bin/build-platform-core.py`.

- [x] Resolve `--ref` with `git rev-parse --verify --end-of-options <ref>^{commit}` and use the resulting full SHA for every `git ls-tree` and `git cat-file` read. Git replacement-object lookup is disabled.
- [x] Read the root plugin, three overlay trees, integration patch, and builder blob from that commit. Require the executing builder bytes to match the commit's `builder_path` blob.
- [x] Reject unsupported Git object modes, invalid/unsafe paths, empty overlays, source collisions, missing inputs, and a root bootstrap that already contains integration-owned module includes, imports, or registrations.
- [x] Build a staging plugin from the root tree, overlay only the three owned module directories, validate patch headers against the bootstrap allowlist, reject binary/extended patch sections, then check and apply the tracked patch once.
- [x] Parse the resulting entrypoint and `Plugin.php`. Require every dynamic overlay PHP path once, every include path to exist, module imports and lifecycle registrations to be unique, and each registered class to exist as an unconditional namespace-scope declaration. Reject pre-include returns, throws, and jumps, component collection resets after integration registration, conditional include scopes, and lifecycle `goto`. Do not hard-code historical module or lifecycle counts.
- [x] Require PHP major/minor 8.5 and run `php -l` on every staged PHP file.
- [x] Write the sorted file inventory, canonical tree hash, normalized PAX tar.gz, and manifest to a temporary sibling directory. Refuse an existing output path and atomically rename the completed output directory into place.
- [x] Run the negative-fixture harness. It covers each named failure, seven assembly-review findings, source immutability, and cleanup.

## Task 4: Prove reproducibility and artifact integrity

**Files:** Complete `site-code/bin/check-platform-core-build.py`.

- [x] Resolve the requested ref once and build it twice into separate temporary output directories using the same full commit SHA and requested ref string.
- [x] Compare source SHA, file count, path/mode/hash inventory, tree hash, archive hash, and parsed manifest content. Require the archive bytes to match.
- [x] Open the archive without extracting untrusted paths. Require one `voelgoed-platform-core/` root, only directories and regular files, normalized names/modes, and exact equality with manifest inventory.
- [x] Independently recalculate every file SHA and the canonical tree hash from archive bytes. Recompute the archive SHA. Reject a tampered file even when the manifest's archive hash is updated to the tampered archive hash.
- [x] Print both build records and each negative-case result. Verify the temporary root no longer exists after the test run.

## Task 5: Put assembly in source CI and publish only from `main`

**Files:** Modify `site-code/bin/check-source.sh` and `.github/workflows/voelgoed-dashboard.yml`.

- [x] Add `python3 site-code/bin/check-platform-core-build.py --ref HEAD` to the canonical source script without removing the existing test suites, static checks, or the `git apply --unidiff-zero --check` applicability check.
- [x] Add a step in the existing `source` job after source checks. Run the builder with `${GITHUB_SHA}` only when the event is a push and `github.ref` is `refs/heads/main`.
- [x] Add a separate publisher job that waits for `VG Nova Platform Gate` success, checks out `${{ github.sha }}`, rebuilds the exact commit, then uploads the tar.gz and manifest with artifact name `voelgoed-platform-core-${{ github.sha }}` and `if-no-files-found: error`.
- [x] Keep `Dashboard checks`, `Canonical source checks`, `VG Nova Platform Gate`, and the gate dependency list unchanged. PR heads run the verifier but do not upload artifacts. The source-job build also runs on main so artifact-creation failures fail `Canonical source checks`.

## Task 6: Update local artifact guidance

**Files:** Modify `.gitignore` and `site-code/voelgoed-platform-core/README.md`.

- [x] Ignore `/dist/`.
- [x] Document `build-platform-core.py --ref <commit> --output <new-directory>` as the canonical assembly path.
- [x] Describe `integration.patch` as a tracked assembly input. Remove manual runtime patch/copy instructions as the normal deployment path.
- [x] State that deployment consumes a verified artifact and remains a separate approved task. Do not rewrite unrelated migration, taxonomy, or subsystem history.

## Task 7: Run the complete PE-005 verification set

**Files:** No additional file scope.

- [x] Run the final verification set from the exact committed feature head, including both dashboard and canonical source checks, the standalone verifier, AST validation, `bash -n`, ShellCheck, actionlint, and `git diff --check`.
- [x] Record source SHA, file count, tree hash, archive hash, and manifest hash for two final builds in the PE-005 handoff report.
- [x] Inspect the final generated archive for one top-level plugin directory, expected root and overlay files, `PersonProductsBricks.php`, PHP 8.5 header, unique bootstrap entries, all patch-owned lifecycle registrations, and no `site-code` tests or README files.
- [x] Confirm no Local WordPress/runtime, database, or Novamira access and no deployment occurred.

## Task 8: Independent review and PR handoff

**Files:** Review only, then fix findings within this file scope.

- [ ] Delegate final read-only reviews for source ownership/assembly, manifest/reproducibility, and CI integration against the final exact head. Earlier review rounds identified and drove fixes for seven assembly issues and one CI publication issue.
- [ ] Fix blocking or material findings and rerun affected checks plus the full source gate.
- [ ] Push `chore/pe-005-platform-core-assembly` and open a focused PR titled `build: add deterministic Platform Core assembly`.
- [ ] Link the PR to this thread immediately. Verify the PR head equals the pushed commit and the base equals the current `main` SHA.
- [ ] Confirm `Dashboard checks`, `Canonical source checks`, and `VG Nova Platform Gate` succeed on the exact PR head, and inspect logs for the reproducibility verifier.
- [ ] Stop with the PR open and unmerged.
