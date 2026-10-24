# Platform Core assembly contract v1.1.0

**Status:** Approved PE-005A reproducibility contract; supersedes v1.0.0
**Scope:** Source-to-artifact assembly only. Runtime deployment remains a separate task.

## Purpose

This contract defines one repeatable way to assemble the Voelgoed Platform Core plugin from one exact Git commit. The Local WordPress plugin directory, WordPress database, uploads, ACF state, and all other runtime state are outside the build boundary.

For the same commit, requested ref string, and canonical build toolchain, the builder must produce byte-identical archive files and identical manifests. The canonical toolchain is CPython 3.14.4 with the `tarfile.py` module SHA-256 recorded below. The archive's canonical identity is its SHA-256 digest; the manifest also records the assembled tree digest. Operating-system, architecture, zlib, and runner-image versions do not participate in archive encoding.

## Source inputs and ownership

The builder resolves the requested Git ref to a full commit SHA before reading any build input. It reads Git tree and blob objects at that SHA only, with Git replacement-object lookup disabled (`GIT_NO_REPLACE_OBJECTS=1`). Replacement refs therefore cannot substitute bytes while leaving the source commit SHA unchanged. The verifier also disables replacement lookup for its Git reads. Neither tool uses loose working-tree bytes for the plugin, overlays, patch, or builder.

| Input | Ownership | Use |
|---|---|---|
| `voelgoed-platform-core/` | Root plugin source | Complete tracked assembly base, including its existing entrypoint, dashboard code, services, tests, and tools |
| `site-code/voelgoed-platform-core/includes/Taxonomy/` | Canonical Platform Core module source | Overlay into the corresponding plugin `includes/Taxonomy/` path |
| `site-code/voelgoed-platform-core/includes/Event/` | Canonical Platform Core module source | Overlay into the corresponding plugin `includes/Event/` path |
| `site-code/voelgoed-platform-core/includes/Product/` | Canonical Platform Core module source | Overlay into the corresponding plugin `includes/Product/` path |
| `site-code/voelgoed-platform-core/integration.patch` | Transitional tracked bootstrap transformation | Apply once to the disposable staged plugin after overlay |
| `site-code/bin/build-platform-core.py` | Tracked assembly tool | Record its Git blob SHA-256 and require the executing bytes to match that commit blob |

The overlay list is closed. The builder does not copy `site-code` tests, its README, architecture documents, runtime QA, untracked files, or `integration.patch` into the plugin. Files under the three owned module directories are selected from tracked blobs only. Git symlinks, submodules, or unsupported object modes fail the build.

The plugin tree is assembled in a temporary directory. Source files and `integration.patch` remain unchanged. Destination collisions between the root tree and an overlay fail closed.

## Bootstrap transformation

Before applying the patch, the builder inspects the root plugin entrypoint and `includes/Plugin.php`. The base is rejected if it already contains a canonical overlay include path, an integration-owned Taxonomy/Event/Product import, or an integration-owned lifecycle registration. This prevents patching an already integrated bootstrap and recreating duplicate entries.

The builder checks and applies this zero-context patch with `git apply --unidiff-zero`, using `--check` before the single application. It validates every old/new file header against the exact two bootstrap paths owned by the patch. Git extended diff sections and binary patches are rejected, and no patch may name an overlay or any other staged file. A missing target, malformed or ambiguous hunk, failed check, or failed application aborts the build and produces no output artifact. The same applicability mode is used by the independent source-gate check.

After application, the builder:

1. Enumerates the tracked runtime PHP files under the three overlay directories from the resolved Git tree.
2. Requires the entrypoint's direct top-level `VGPC_PLUGIN_DIR` constant to equal `__DIR__ . '/'` and its direct `require_once` to load `VGPC_PLUGIN_DIR . 'includes/Plugin.php'` before the canonical include array. The complete entrypoint may contain only that `Plugin.php` require and the canonical module loop's `require_once`; any additional `include` or `require` fails. This ties bootstrap file checks to the assembled plugin root and prevents dependencies outside the artifact. An altered constant or missing or redirected `Plugin.php` require fails.
3. Uses PHP tokens from the complete entrypoint to require exactly one unconditional top-level literal `$vgpc_include_paths` assignment and one direct `foreach` consumer as the immediately following statement. Before the assignment, the only exit is the exact `defined('ABSPATH') || exit;` guard. A `return`, `throw`, `goto`, extra `exit`, or conditional/control scope before include consumption fails. The executable loop body resolves each path, checks that the file is readable, and calls `require_once`. Commented-out arrays or loops, empty replacement arrays, intervening mutation statements, braced or alternate-syntax conditional wrappers, and unsupported array expressions fail. Every enumerated overlay PHP path must appear exactly once in that executable list.
4. Requires every entrypoint include path to name an existing assembled file and rejects duplicate paths.
5. Finds the actual `Plugin` class and its direct executable `register(): void` method in the full-file token stream. It rejects duplicate integration-owned imports and lifecycle registrations. PHP tokenization confirms each integration import is an executable `use` statement and each lifecycle registration is an executable `$this->register_component(...)` call that starts a top-level statement inside that method. Comments, a commented-out original method paired with an empty replacement, short-circuit prefixes, conditional wrappers, alternate conditional syntax, and `goto` jumps cannot satisfy this check. It verifies that the private `register_component()` helper retains its class-existence guard, object construction, and keyed insertion behavior, so an early return cannot silently discard lifecycle components. The `Plugin::$registered` property must be a direct class property initialized to `false`, or the guard could suppress component registration on its first call.
6. After the first integration registration, `Plugin::register()` may write a root-owned component to a literal key that does not match an integration key. It cannot unset the collection, replace or unset an integration component, call an array mutator with the collection, expose the collection by reference, or access it through direct, braced, or variable property syntax. The `Plugin` class cannot define a constructor that can pre-mark lifecycle registration complete. The existing root-owned `cache_policy` assignment remains valid. The component dispatch loop must be a direct, unconditional `foreach ($this->components as $component)` statement whose body calls `$component->register()` only after `method_exists($component, 'register')`.
7. Requires exactly one unconditional top-level `plugins_loaded` callback after the canonical include loop, and that callback must be the entrypoint's final executable statement. The callback must invoke `(new Plugin())->register()`. A return, throw, exit, or goto between the include loop and that callback fails the build. Every registered class must be an actual unconditional namespace-scope class, interface, trait, or enum declaration in an assembled PHP module. Duplicate declaration names across different assembled PHP files fail case-insensitively, because PHP linting each file alone cannot detect cross-file redeclarations and PHP declaration names are case-insensitive. Declaration-like text in comments and declarations inside conditional execution scopes do not count. The setup region must not exit before dispatch.
8. Leaves lifecycle ownership to the tracked patch and existing subsystem integration tests. It does not infer lifecycle status from the total number of PHP files or hard-code historical module counts.
9. Lints every assembled PHP file with the PHP 8.5 CLI. A different PHP major/minor version or any syntax error fails the build.

## Artifact layout

The requested output directory must not already exist. The builder stages all work in a temporary sibling directory, completes validation, then publishes it with Linux `renameat2(RENAME_NOREPLACE)`. The no-replace operation prevents a destination created during assembly from being overwritten. Hosts without this syscall fail closed. A failed build leaves no partial output. It does not overwrite a file, symlink, empty directory, or non-empty directory supplied as the output path.

The output contains exactly:

```text
<output>/
├── voelgoed-platform-core.tar.gz
└── voelgoed-platform-core.manifest.json
```

The gzip tar archive contains one top-level directory, `voelgoed-platform-core/`. It includes the tracked root plugin tree plus the three approved overlays and the single patch result. It contains no manifest and no files outside the plugin tree.

## Deterministic archive metadata

The tar writer uses POSIX PAX format and a fixed, documented ordering: the root directory first, other directories sorted by UTF-8 path bytes, then files sorted by UTF-8 path bytes. Every tar entry has mtime `0`, uid/gid `0`, empty user/group names, and normalized directory mode `0755`.

Regular-file modes come from Git. Git mode `100755` becomes archive mode `0755`; `100644` becomes `0644`. Other file modes fail. CPython 3.14.4's PAX tar serializer runs with UTF-8 encoding and strict errors. The builder accepts that serializer only when the `tarfile.py` source SHA-256 is `c0db1a21e56ccf002147bffd10d97f452ad842820963ef793117f92a0cdaaad4`. The gzip envelope uses the versioned `vgpc-gzip-stored-v1` encoder. It emits one gzip member with header bytes `1f 8b 08 00 00 00 00 00 00 ff`, then DEFLATE stored blocks of at most 65,535 bytes, then the IEEE CRC-32 and uncompressed-size trailer. The encoder computes CRC-32 in Python using polynomial `0xedb88320`; it does not call zlib or another compressor. Every block has fixed byte alignment, little-endian length and one's-complement length. No timestamp, filename, user, host, or machine field enters the output. Unsupported Python versions or a changed `tarfile.py` hash fail before artifact creation. The GitHub Actions source and publication jobs set up CPython 3.14.4 and report the runtime zlib versions for audit; zlib is used only by the verifier to decompress and inspect archives.

## Manifest schema version 2

The UTF-8 JSON manifest uses stable key ordering and a final newline. `files` is sorted by bytewise UTF-8 order of normalized `/` relative paths within the plugin root. Paths must be valid UTF-8 normalized relative POSIX paths, cannot contain CR or LF or backslashes, and contain no absolute paths or `..` components.

Required fields:

```json
{
  "schema_version": 2,
  "artifact_name": "voelgoed-platform-core.tar.gz",
  "source_commit": "<full 40-character commit SHA>",
  "source_ref_requested": "HEAD",
  "build_toolchain": {
    "python_implementation": "CPython",
    "python_version": "3.14.4",
    "tarfile_module_sha256": "c0db1a21e56ccf002147bffd10d97f452ad842820963ef793117f92a0cdaaad4",
    "tar_serialization": "CPython-3.14.4-tarfile-pax-utf8-v1",
    "archive_format": "tar-pax+gzip",
    "archive_encoder_version": "vgpc-gzip-stored-v1",
    "zlib_encoding": "not-used"
  },
  "php_contract": {
    "minimum": "8.5",
    "ci": "8.5"
  },
  "root_source_path": "voelgoed-platform-core",
  "overlay_source_paths": [
    "site-code/voelgoed-platform-core/includes/Taxonomy",
    "site-code/voelgoed-platform-core/includes/Event",
    "site-code/voelgoed-platform-core/includes/Product"
  ],
  "integration_patch_path": "site-code/voelgoed-platform-core/integration.patch",
  "integration_patch_sha256": "<64 lowercase hexadecimal characters>",
  "builder_path": "site-code/bin/build-platform-core.py",
  "builder_sha256": "<64 lowercase hexadecimal characters>",
  "file_count": 1,
  "files": [
    {
      "path": "voelgoed-platform-core.php",
      "mode": "100644",
      "size": 1,
      "sha256": "<64 lowercase hexadecimal characters>"
    }
  ],
  "tree_sha256": "<64 lowercase hexadecimal characters>",
  "archive_sha256": "<64 lowercase hexadecimal characters>"
}
```

The example values in angle brackets are explanatory. A real manifest contains full digests and the full inventory. `file_count` must equal the number of records in `files`.

`builder_sha256` and `integration_patch_sha256` hash the exact Git blobs at `source_commit`. Before building, the builder verifies that its executing file bytes match the builder blob. This prevents an uncommitted builder edit from silently producing an artifact attributed to an older commit.

The manifest is a sibling of the archive and does not participate in either the plugin tree hash or archive contents. Its own SHA-256 can be calculated externally for release records. Toolchain fields identify only output-affecting canonical implementations. They exclude the OS, runner, Python executable path, zlib version, host, username, timestamps, and machine identity so the manifest remains byte-identical across supported hosts.

## Canonical tree hash

For every assembled regular file, in the manifest's bytewise path order, encode one UTF-8 line:

```text
<git-mode> <file-sha256> <relative-path>\n
```

The mode is exactly `100644` or `100755`; the file digest is 64 lowercase hexadecimal characters; the path is relative to the plugin root. SHA-256 the concatenation of all lines. The lower-case hex digest is `tree_sha256`.

The manifest itself is outside the plugin tree and cannot recursively contribute to `tree_sha256`. The verifier independently reconstructs these lines from the archive's file bytes and rejects any inventory, mode, file digest, or tree digest mismatch.

## Reproducibility verifier

`site-code/bin/check-platform-core-build.py` resolves the requested ref once and invokes two independent builds using that full commit SHA and the same requested-ref string. It requires equality for:

- source commit and file count;
- sorted path/mode/file-SHA inventory;
- tree SHA-256;
- archive SHA-256 and byte-for-byte archive contents;
- parsed manifest content.

It inspects the tar without extracting unsafe paths, requires exactly one top-level plugin directory and only directory/regular-file entries, and independently recomputes the manifest inventory, implied directory set, tree hash, and archive hash. It rejects unmanifested empty directories, invalid paths, and non-normalized gzip header fields. A changed archive file fails verification even if an attacker updates the manifest's `archive_sha256`, because the per-file digest and tree digest must still match.

The verifier requires one gzip member with no trailing data, reconstructs the canonical PAX tar stream from the inspected entries, and compares the uncompressed bytes exactly. It independently emits the specified stored-DEFLATE stream and requires byte-for-byte equality with the archive. This rejects concatenated gzip members, compressed DEFLATE blocks, alternate headers, extra global or per-entry PAX metadata, and alternate tar encodings while allowing PAX fields the canonical writer needs for a long path. Manifest `schema_version`, `file_count`, and file `size` values must be actual non-negative JSON integers; booleans are rejected even though Python treats `bool` as an `int` subclass.

The exact-output mode (`--verify-output <directory> --ref <commit>`) validates existing archive and manifest files without rebuilding them. It checks canonical manifest serialization, schema and toolchain values, source builder and patch hashes, archive hash, tar metadata, file records, and tree hash. It then constructs the expected assembled file inventory from the requested commit's Git archive, overlays the tracked files from the three owned module directories, applies the tracked patch in a disposable fixture, and compares that inventory with the verified archive. This source binding prevents a self-consistent but unrelated archive and manifest from passing. The publication workflow runs this mode on `dist/` and then uploads those same two unchanged files.

Negative fixtures use temporary Git repositories made from committed assembly inputs. They prove failure for a non-applying patch, a binary patch aimed at a canonical overlay, Git replacement objects, an already-integrated base bootstrap, a missing overlay PHP file, a patch include to a missing file, duplicate integration bootstrap entries, a redirected `VGPC_PLUGIN_DIR`, a missing `Plugin.php` bootstrap require, an additional external entrypoint require, cross-file duplicate PHP declarations including case-only name differences, a conditionally wrapped include assignment and loop, commented-out include arrays and registration methods with empty executable replacements, an include array cleared before consumption, direct, braced, and variable-property collection resets, whole-collection and keyed `unset`, integration-key overwrite, `array_splice`, reference aliasing, an early return from `register_component()`, a `Plugin::$registered` property initialized to `true`, a constructor that pre-marks registration complete, a missing or unreachable `plugins_loaded` callback, a lifecycle hook removed after registration, lifecycle registrations that are unreachable, short-circuited, conditionally wrapped, written in alternate conditional syntax, jumped over with `goto`, commented out, or outside the registration method, a conditionally wrapped dispatch loop, a missing dispatch call, a conditionally declared lifecycle class, a commented-out module declaration, an existing or concurrently appearing output destination, a deleted output sentinel, extra PAX metadata, concatenated gzip members, alternate gzip compression, malformed archive headers, unmanifested empty directories, CR/LF paths, boolean numeric fields, and a modified artifact. The concurrent-output fixture injects the destination only after build preflight, then checks both preservation of the competing destination and cleanup of staged output. The verifier checks that fixture inputs remain unchanged and that all temporary directories are removed.

## Source gate and main publication

`site-code/bin/check-source.sh` runs the existing source suites, existing static checks, the existing `git apply --check`, and the reproducibility verifier. GitHub Actions keeps the job identities `Dashboard checks`, `Canonical source checks`, and `VG Nova Platform Gate` unchanged.

Only a successful push run for `refs/heads/main` uploads canonical files. Both the source and publication jobs install CPython 3.14.4. The source job builds from `${GITHUB_SHA}` after the normal source checks, so artifact-creation failures fail `Canonical source checks`. A separate publisher job waits for `VG Nova Platform Gate` to succeed, checks out that exact `${GITHUB_SHA}`, builds `dist/`, verifies that exact output against the Git commit and patch, then uploads those unchanged archive and manifest files as `voelgoed-platform-core-${GITHUB_SHA}`. No write command runs on `dist/` after verification. No artifact is uploaded if either governed check or the aggregate gate fails. Pull-request runs perform the full reproducibility and tamper checks but do not publish the canonical main artifact. The outer GitHub artifact ZIP is transport packaging. `tree_sha256` identifies assembled plugin content, while `archive_sha256` identifies the canonical tar.gz byte stream.

## Failure and runtime boundary

Any unresolved ref, absent or mismatched builder blob, unsupported Git object, collision, unsafe path, invalid source mode, replacement-object substitution, already-integrated bootstrap, invalid plugin-root constant or bootstrap require, external bootstrap include/require, patch path outside the bootstrap allowlist, binary or unsupported extended patch section, patch error, missing include, duplicate include/import/registration/class declaration, invalid initial lifecycle flag, constructor-based or dynamic lifecycle registration bypass, removed lifecycle hook, conditional or unreachable lifecycle registration/class, lifecycle jump, undeclared lifecycle class, invalid manifest integer, unsupported canonical Python or `tarfile.py` identity, non-PHP-8.5 runtime, PHP lint error, unsafe output destination, unavailable no-replace publication, nondeterministic result, or archive/manifest/source mismatch fails nonzero. The builder must not leave a partial destination.

The builder and verifier use only the repository's Git objects, temporary files, Python standard library, Git, and PHP 8.5 CLI. They do not call WordPress, Novamira, WP-CLI, a database, or the active plugin directory. They do not deploy or modify runtime files. Deployment and runtime parity checks belong to PE-006.
