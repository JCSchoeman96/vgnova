# Platform Core assembly contract v1.0.0

**Status:** Approved implementation contract for PE-005
**Scope:** Source-to-artifact assembly only. Runtime deployment remains a separate task.

## Purpose

This contract defines one repeatable way to assemble the Voelgoed Platform Core plugin from one exact Git commit. The Local WordPress plugin directory, WordPress database, uploads, ACF state, and all other runtime state are outside the build boundary.

For the same commit, requested ref string, and supported PHP 8.5 build environment, the builder must produce byte-identical archive files and semantically identical manifests. The archive's canonical identity is its SHA-256 digest; the manifest also records the assembled tree digest.

## Source inputs and ownership

The builder resolves the requested Git ref to a full commit SHA before reading any build input. It reads Git tree and blob objects at that SHA only. It does not use loose working-tree bytes for the plugin, overlays, patch, or builder.

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

The builder checks and applies this zero-context patch with `git apply --unidiff-zero`, using `--check` before the single application. A missing target, malformed or ambiguous hunk, failed check, or failed application aborts the build and produces no output artifact. The same applicability mode is used by the independent source-gate check.

After application, the builder:

1. Enumerates the tracked runtime PHP files under the three overlay directories from the resolved Git tree.
2. Parses the plugin entrypoint's include list and requires each enumerated overlay PHP path exactly once.
3. Requires every entrypoint include path to name an existing assembled file and rejects duplicate paths.
4. Rejects duplicate integration-owned imports and lifecycle registrations. PHP tokenization confirms each integration import is an executable `use` statement and each lifecycle registration is an executable `$this->register_component(...)` call that starts a top-level statement inside `Plugin::register()`. Comments, short-circuit prefixes, conditional wrappers, and alternate conditional syntax cannot satisfy this check. The component dispatch loop must also be a direct, unconditional `foreach ($this->components as $component)` statement whose body calls `$component->register()` only after `method_exists($component, 'register')`. Every registered class must be an actual class, interface, trait, or enum declaration in an assembled PHP module; declaration-like text in comments is ignored. The setup region must not exit before the loop.
5. Leaves lifecycle ownership to the tracked patch and existing subsystem integration tests. It does not infer lifecycle status from the total number of PHP files or hard-code historical module counts.
6. Lints every assembled PHP file with the PHP 8.5 CLI. A different PHP major/minor version or any syntax error fails the build.

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

Regular-file modes come from Git. Git mode `100755` becomes archive mode `0755`; `100644` becomes `0644`. Other file modes fail. Gzip uses an empty original filename, mtime `0`, and compression level `9`. Its first ten header bytes are fixed to `1f 8b 08 00 00 00 00 00 02 ff`: deflate method, no optional fields, zero timestamp, maximum-compression hint, and unknown OS. No host timestamp, username, absolute path, umask, or machine-specific field enters the archive.

## Manifest schema version 1

The UTF-8 JSON manifest uses stable key ordering and a final newline. `files` is sorted by bytewise UTF-8 order of normalized `/` relative paths within the plugin root. Paths must be valid UTF-8 normalized relative POSIX paths, cannot contain CR or LF or backslashes, and contain no absolute paths or `..` components.

Required fields:

```json
{
  "schema_version": 1,
  "artifact_name": "voelgoed-platform-core.tar.gz",
  "source_commit": "<full 40-character commit SHA>",
  "source_ref_requested": "HEAD",
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

The manifest is a sibling of the archive and does not participate in either the plugin tree hash or archive contents. Its own SHA-256 can be calculated externally for release records.

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

The verifier requires one gzip member with no trailing data, reconstructs the canonical PAX tar stream from the inspected entries, and compares the uncompressed bytes exactly. This rejects concatenated gzip members, extra global or per-entry PAX metadata, and alternate tar encodings while allowing PAX fields the canonical writer needs for a long path. Manifest `schema_version`, `file_count`, and file `size` values must be actual non-negative JSON integers; booleans are rejected even though Python treats `bool` as an `int` subclass.

Negative fixtures use temporary Git repositories made from committed assembly inputs. They prove failure for a non-applying patch, an already-integrated base bootstrap, a missing overlay PHP file, a patch include to a missing file, duplicate integration bootstrap entries, lifecycle registrations that are unreachable, short-circuited, conditionally wrapped, written in alternate conditional syntax, commented out, or outside the registration method, a conditionally wrapped dispatch loop, a missing dispatch call, a commented-out module declaration, an existing or concurrently appearing output destination, a deleted output sentinel, extra PAX metadata, concatenated gzip members, malformed archive headers, unmanifested empty directories, CR/LF paths, boolean numeric fields, and a modified artifact. The verifier checks that fixture inputs remain unchanged and that all temporary directories are removed.

## Source gate and main publication

`site-code/bin/check-source.sh` runs the existing source suites, existing static checks, the existing `git apply --check`, and the reproducibility verifier. GitHub Actions keeps the job identities `Dashboard checks`, `Canonical source checks`, and `VG Nova Platform Gate` unchanged.

Only a successful push run for `refs/heads/main` creates and uploads the canonical files. The source job builds from `${GITHUB_SHA}` after the normal source checks, then uploads only the tar.gz and manifest using the artifact name `voelgoed-platform-core-${GITHUB_SHA}`. Pull-request runs perform the reproducibility checks but do not publish the canonical main artifact. The outer GitHub artifact ZIP is transport packaging; `archive_sha256` is the digest of `voelgoed-platform-core.tar.gz` and is the canonical artifact digest.

## Failure and runtime boundary

Any unresolved ref, absent or mismatched builder blob, unsupported Git object, collision, unsafe path, invalid source mode, already-integrated bootstrap, patch error, missing include, duplicate include/import/registration, conditional or unreachable lifecycle registration, undeclared lifecycle class, invalid manifest integer, non-PHP-8.5 runtime, PHP lint error, unsafe output destination, unavailable no-replace publication, nondeterministic result, or archive/manifest mismatch fails nonzero. The builder must not leave a partial destination.

The builder and verifier use only the repository's Git objects, temporary files, Python standard library, Git, and PHP 8.5 CLI. They do not call WordPress, Novamira, WP-CLI, a database, or the active plugin directory. They do not deploy or modify runtime files. Deployment and runtime parity checks belong to PE-006.
