#!/usr/bin/env python3
"""Verify deterministic Platform Core builds and fail-closed fixture cases."""

from __future__ import annotations

import argparse
import gzip
import hashlib
import importlib.util
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path, PurePosixPath
from typing import Any


ROOT_PLUGIN = "voelgoed-platform-core"
BUILDER_PATH = "site-code/bin/build-platform-core.py"
PATCH_PATH = "site-code/voelgoed-platform-core/integration.patch"
OVERLAY_PATHS = (
    "site-code/voelgoed-platform-core/includes/Taxonomy",
    "site-code/voelgoed-platform-core/includes/Event",
    "site-code/voelgoed-platform-core/includes/Product",
)
FIXTURE_PATHS = (ROOT_PLUGIN, *OVERLAY_PATHS, PATCH_PATH, BUILDER_PATH)
ARCHIVE_NAME = "voelgoed-platform-core.tar.gz"
MANIFEST_NAME = "voelgoed-platform-core.manifest.json"


class CheckFailure(RuntimeError):
    """A reproducibility or negative-fixture assertion failed."""


def command(
    args: list[str],
    *,
    cwd: Path,
    input_bytes: bytes | None = None,
    check: bool = True,
) -> subprocess.CompletedProcess[bytes]:
    result = subprocess.run(
        args,
        cwd=cwd,
        input=input_bytes,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if check and result.returncode != 0:
        raise CheckFailure(
            f"Command failed ({result.returncode}): {' '.join(args)}\n"
            f"{result.stdout.decode('utf-8', 'replace')}"
            f"{result.stderr.decode('utf-8', 'replace')}"
        )
    return result


def resolve_commit(repo_root: Path, ref: str) -> str:
    result = command(
        ["git", "rev-parse", "--verify", "--end-of-options", f"{ref}^{{commit}}"],
        cwd=repo_root,
    )
    commit = result.stdout.decode("ascii").strip()
    if re.fullmatch(r"[0-9a-f]{40}", commit) is None:
        raise CheckFailure(f"Ref did not resolve to a full Git commit SHA: {ref}")
    return commit


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def safe_member_name(name: str) -> PurePosixPath:
    normalized = name.rstrip("/")
    path = PurePosixPath(normalized)
    if (
        not normalized
        or "\r" in normalized
        or "\n" in normalized
        or path.is_absolute()
        or ".." in path.parts
        or "\\" in normalized
        or path.as_posix() != normalized
    ):
        raise CheckFailure(f"Unsafe or non-normalized archive path: {name!r}")
    return path


def verify_archive(archive_path: Path, manifest: dict[str, Any]) -> None:
    archive_bytes = archive_path.read_bytes()
    if sha256(archive_bytes) != manifest.get("archive_sha256"):
        raise CheckFailure("Archive SHA-256 does not match the manifest.")
    expected_gzip_header = b"\x1f\x8b\x08\x00\x00\x00\x00\x00\x02\xff"
    if len(archive_bytes) < len(expected_gzip_header) or archive_bytes[:3] != b"\x1f\x8b\x08":
        raise CheckFailure("Artifact is not a gzip-compressed tar archive.")
    if archive_bytes[:10] != expected_gzip_header:
        raise CheckFailure("Gzip header does not match normalized deterministic metadata.")

    actual_files: list[dict[str, Any]] = []
    directory_names: set[str] = set()
    seen_names: set[str] = set()
    roots: set[str] = set()
    entry_order: list[str] = []

    try:
        with tarfile.open(archive_path, mode="r:gz") as archive:
            for member in archive.getmembers():
                path = safe_member_name(member.name)
                entry_order.append(member.name)
                if member.name in seen_names:
                    raise CheckFailure(f"Duplicate archive entry: {member.name}")
                seen_names.add(member.name)
                roots.add(path.parts[0])

                if (
                    member.mtime != 0
                    or member.uid != 0
                    or member.gid != 0
                    or member.uname != ""
                    or member.gname != ""
                ):
                    raise CheckFailure(f"Non-normalized tar metadata: {member.name}")

                if member.isdir():
                    if member.mode != 0o755:
                        raise CheckFailure(f"Non-normalized directory mode: {member.name}")
                    directory_names.add(path.as_posix())
                    continue

                if not member.isfile():
                    raise CheckFailure(f"Archive contains a non-regular entry: {member.name}")
                if member.mode not in (0o644, 0o755):
                    raise CheckFailure(f"Unsupported archive file mode: {member.name}")
                if path.parts[0] != ROOT_PLUGIN or len(path.parts) < 2:
                    raise CheckFailure(f"File is outside the plugin root: {member.name}")

                stream = archive.extractfile(member)
                if stream is None:
                    raise CheckFailure(f"Cannot read archive file: {member.name}")
                contents = stream.read()
                relative = PurePosixPath(*path.parts[1:]).as_posix()
                actual_files.append(
                    {
                        "path": relative,
                        "mode": "100755" if member.mode == 0o755 else "100644",
                        "size": len(contents),
                        "sha256": sha256(contents),
                    }
                )
    except (OSError, tarfile.TarError) as error:
        raise CheckFailure(f"Cannot read canonical tar.gz archive: {error}") from error

    if roots != {ROOT_PLUGIN}:
        raise CheckFailure(f"Archive must contain only {ROOT_PLUGIN}/ at its top level.")
    if ROOT_PLUGIN not in directory_names:
        raise CheckFailure(f"Archive is missing its {ROOT_PLUGIN}/ root directory.")
    if not actual_files:
        raise CheckFailure("Archive contains no regular plugin files.")

    expected_files = manifest.get("files")
    if not isinstance(expected_files, list) or not all(isinstance(item, dict) for item in expected_files):
        raise CheckFailure("Manifest files must be an array of file records.")
    expected_directory_names = {ROOT_PLUGIN}
    for record in expected_files:
        relative_path = record.get("path")
        if not isinstance(relative_path, str):
            raise CheckFailure("Manifest file path must be a string.")
        relative = safe_member_name(relative_path)
        for parent in relative.parents:
            if parent.as_posix() != ".":
                expected_directory_names.add(f"{ROOT_PLUGIN}/{parent.as_posix()}")
    if directory_names != expected_directory_names:
        raise CheckFailure(
            "Archive directory inventory differs from the directories implied by manifest files."
        )

    actual_files.sort(key=lambda item: item["path"].encode("utf-8"))
    expected_directories = [ROOT_PLUGIN] + sorted(
        expected_directory_names - {ROOT_PLUGIN}, key=lambda value: value.encode("utf-8")
    )
    expected_order = expected_directories + [ROOT_PLUGIN + "/" + item["path"] for item in actual_files]
    if entry_order != expected_order:
        raise CheckFailure("Archive entries are not in the documented deterministic order.")
    if actual_files != expected_files:
        raise CheckFailure("Archive file inventory, mode, size, or file hash differs from the manifest.")
    if manifest.get("file_count") != len(actual_files):
        raise CheckFailure("Manifest file_count differs from the archive inventory.")

    canonical_lines = b"".join(
        f"{item['mode']} {item['sha256']} {item['path']}\n".encode("utf-8")
        for item in actual_files
    )
    if sha256(canonical_lines) != manifest.get("tree_sha256"):
        raise CheckFailure("Archive-derived tree SHA-256 does not match the manifest.")


def validate_manifest(
    manifest: dict[str, Any],
    *,
    repo_root: Path,
    commit: str,
    requested_ref: str,
) -> None:
    required = {
        "schema_version",
        "artifact_name",
        "source_commit",
        "source_ref_requested",
        "php_contract",
        "root_source_path",
        "overlay_source_paths",
        "integration_patch_path",
        "integration_patch_sha256",
        "builder_path",
        "builder_sha256",
        "file_count",
        "files",
        "tree_sha256",
        "archive_sha256",
    }
    if not required.issubset(manifest):
        raise CheckFailure(f"Manifest is missing fields: {sorted(required - set(manifest))}")
    expected = {
        "schema_version": 1,
        "artifact_name": ARCHIVE_NAME,
        "source_commit": commit,
        "source_ref_requested": requested_ref,
        "php_contract": {"minimum": "8.5", "ci": "8.5"},
        "root_source_path": ROOT_PLUGIN,
        "overlay_source_paths": list(OVERLAY_PATHS),
        "integration_patch_path": PATCH_PATH,
        "builder_path": BUILDER_PATH,
    }
    for key, expected_value in expected.items():
        if manifest.get(key) != expected_value:
            raise CheckFailure(f"Manifest field {key!r} differs from its contract value.")
    for field in ("integration_patch_sha256", "builder_sha256", "tree_sha256", "archive_sha256"):
        if re.fullmatch(r"[0-9a-f]{64}", str(manifest[field])) is None:
            raise CheckFailure(f"Manifest field {field!r} is not a lowercase SHA-256 digest.")
    for field, source_path in (
        ("integration_patch_sha256", PATCH_PATH),
        ("builder_sha256", BUILDER_PATH),
    ):
        result = command(["git", "show", f"{commit}:{source_path}"], cwd=repo_root)
        if sha256(result.stdout) != manifest[field]:
            raise CheckFailure(f"Manifest {field} does not hash the committed source blob {source_path}.")
    if not isinstance(manifest["files"], list) or manifest["file_count"] != len(manifest["files"]):
        raise CheckFailure("Manifest file_count does not match its files array.")
    paths = [record.get("path") for record in manifest["files"] if isinstance(record, dict)]
    if len(paths) != len(manifest["files"]):
        raise CheckFailure("Manifest files contains a non-object record.")
    for path in paths:
        if not isinstance(path, str):
            raise CheckFailure("Manifest file path must be a string.")
        try:
            path.encode("utf-8", "strict")
        except UnicodeEncodeError as error:
            raise CheckFailure(f"Manifest path is not valid UTF-8: {path!r}") from error
        if "\r" in path or "\n" in path:
            raise CheckFailure("Manifest paths cannot contain CR or LF.")
        normalized = PurePosixPath(path)
        if (
            not path
            or normalized.is_absolute()
            or ".." in normalized.parts
            or "\\" in path
            or normalized.as_posix() != path
        ):
            raise CheckFailure(f"Manifest path is unsafe or non-normalized: {path!r}")
    if paths != sorted(paths, key=lambda value: str(value).encode("utf-8")):
        raise CheckFailure("Manifest files are not sorted by UTF-8 path bytes.")


def extract_fixture_archive(archive_bytes: bytes, destination: Path) -> None:
    with tarfile.open(fileobj=io.BytesIO(archive_bytes), mode="r:") as archive:
        for member in archive.getmembers():
            path = safe_member_name(member.name)
            target = destination.joinpath(*path.parts)
            if member.isdir():
                target.mkdir(parents=True, exist_ok=True)
                target.chmod(0o755)
            elif member.isfile():
                target.parent.mkdir(parents=True, exist_ok=True)
                stream = archive.extractfile(member)
                if stream is None:
                    raise CheckFailure(f"Cannot read fixture input: {member.name}")
                target.write_bytes(stream.read())
                target.chmod(member.mode & 0o777)
            else:
                raise CheckFailure(f"Source archive contains unsupported entry: {member.name}")


def create_fixture(repo_root: Path, commit: str, destination: Path) -> Path:
    destination.mkdir(parents=True)
    archive = command(
        ["git", "archive", "--format=tar", commit, "--", *FIXTURE_PATHS],
        cwd=repo_root,
    )
    extract_fixture_archive(archive.stdout, destination)
    command(["git", "init", "--quiet"], cwd=destination)
    command(["git", "config", "user.name", "PE-005 verifier"], cwd=destination)
    command(["git", "config", "user.email", "pe005-verifier@example.invalid"], cwd=destination)
    command(["git", "add", "--all"], cwd=destination)
    fixture_environment = {
        **os.environ,
        "GIT_AUTHOR_DATE": "2026-10-24T00:00:00+00:00",
        "GIT_COMMITTER_DATE": "2026-10-24T00:00:00+00:00",
    }
    result = subprocess.run(
        ["git", "commit", "--quiet", "-m", "fixture: PE-005 assembly inputs"],
        cwd=destination,
        env=fixture_environment,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if result.returncode != 0:
        raise CheckFailure(result.stderr.decode("utf-8", "replace"))
    return destination


def run_builder(
    builder: Path,
    repo_root: Path,
    ref: str,
    output: Path,
    requested_ref: str | None = None,
) -> subprocess.CompletedProcess[bytes]:
    args = [sys.executable, str(builder), "--ref", ref, "--output", str(output)]
    if requested_ref is not None:
        args.extend(("--source-ref-requested", requested_ref))
    return command(
        args,
        cwd=repo_root,
        check=False,
    )


def expect_build_failure(
    *,
    label: str,
    builder: Path,
    fixture: Path,
    ref: str,
    output: Path,
    expected_text: str,
    output_must_remain: bool = False,
    sentinel_must_remain: bool = False,
) -> None:
    result = run_builder(builder, fixture, ref, output)
    combined = (result.stdout + result.stderr).decode("utf-8", "replace")
    if result.returncode == 0:
        raise CheckFailure(f"Negative case unexpectedly succeeded: {label}")
    if expected_text.lower() not in combined.lower():
        raise CheckFailure(
            f"Negative case {label} failed for the wrong reason; expected {expected_text!r}.\n{combined}"
        )
    if output_must_remain:
        if not output.is_dir():
            raise CheckFailure(f"Negative case {label} removed its existing output directory.")
        sentinel = output / "sentinel.txt"
        if sentinel_must_remain and not sentinel.is_file():
            raise CheckFailure(f"Negative case {label} removed its existing output sentinel.")
        if sentinel.is_file() and sentinel.read_text(encoding="utf-8") != "preserve me\n":
            raise CheckFailure(f"Negative case {label} changed its existing output sentinel.")
    elif output.exists() or output.is_symlink():
        raise CheckFailure(f"Negative case {label} left a partial output directory.")
    status_after = command(["git", "status", "--porcelain"], cwd=fixture).stdout
    if status_after:
        raise CheckFailure(f"Negative case {label} changed tracked fixture inputs: {status_after!r}")
    print(f"PASS negative case: {label}")


def commit_fixture_changes(fixture: Path, message: str) -> None:
    command(["git", "add", "--all"], cwd=fixture)
    command(["git", "commit", "--quiet", "--amend", "-m", message], cwd=fixture)


def tamper_archive(source_archive: Path, destination: Path) -> None:
    changed = False
    with tarfile.open(source_archive, mode="r:gz") as source:
        members = source.getmembers()
        payloads: list[tuple[tarfile.TarInfo, bytes | None]] = []
        for member in members:
            info = tarfile.TarInfo(member.name)
            info.type = member.type
            info.mode = member.mode
            info.uid = 0
            info.gid = 0
            info.uname = ""
            info.gname = ""
            info.mtime = 0
            if member.isdir():
                info.size = 0
                payloads.append((info, None))
                continue
            stream = source.extractfile(member)
            if stream is None:
                raise CheckFailure(f"Cannot read file while creating tamper fixture: {member.name}")
            contents = stream.read()
            if not changed:
                contents += b"\nPE-005 deliberate tamper\n"
                changed = True
            info.size = len(contents)
            payloads.append((info, contents))

    if not changed:
        raise CheckFailure("No regular file was available to tamper with.")
    raw_tar = io.BytesIO()
    with tarfile.open(fileobj=raw_tar, mode="w", format=tarfile.PAX_FORMAT) as target:
        for info, contents in payloads:
            target.addfile(info, io.BytesIO(contents) if contents is not None else None)
    compressed = io.BytesIO()
    with gzip.GzipFile(fileobj=compressed, mode="wb", filename="", compresslevel=9, mtime=0) as target:
        target.write(raw_tar.getvalue())
    destination.write_bytes(compressed.getvalue())


def rewrite_archive(
    source_archive: Path,
    destination: Path,
    *,
    extra_directory: str | None = None,
    rename_first_file: str | None = None,
) -> str | None:
    directories: list[tarfile.TarInfo] = []
    files: list[tuple[tarfile.TarInfo, bytes]] = []
    renamed_to: str | None = None
    with tarfile.open(source_archive, mode="r:gz") as source:
        for member in source.getmembers():
            info = tarfile.TarInfo(member.name)
            info.type = member.type
            info.mode = member.mode
            info.uid = 0
            info.gid = 0
            info.uname = ""
            info.gname = ""
            info.mtime = 0
            if member.isdir():
                info.size = 0
                directories.append(info)
                continue
            stream = source.extractfile(member)
            if stream is None:
                raise CheckFailure(f"Cannot read file while rewriting archive fixture: {member.name}")
            contents = stream.read()
            if rename_first_file is not None and renamed_to is None:
                info.name = rename_first_file
                renamed_to = rename_first_file
            info.size = len(contents)
            files.append((info, contents))

    if extra_directory is not None:
        info = tarfile.TarInfo(extra_directory)
        info.type = tarfile.DIRTYPE
        info.mode = 0o755
        info.uid = 0
        info.gid = 0
        info.uname = ""
        info.gname = ""
        info.mtime = 0
        info.size = 0
        directories.append(info)

    directories.sort(key=lambda item: (item.name != ROOT_PLUGIN, item.name.encode("utf-8")))
    files.sort(key=lambda item: item[0].name.encode("utf-8"))
    raw_tar = io.BytesIO()
    with tarfile.open(fileobj=raw_tar, mode="w", format=tarfile.PAX_FORMAT) as target:
        for info in directories:
            target.addfile(info)
        for info, contents in files:
            target.addfile(info, io.BytesIO(contents))
    compressed = io.BytesIO()
    with gzip.GzipFile(fileobj=compressed, mode="wb", filename="", compresslevel=9, mtime=0) as target:
        target.write(raw_tar.getvalue())
    destination.write_bytes(compressed.getvalue())
    return renamed_to


def update_tree_hash(file_records: list[dict[str, Any]]) -> str:
    ordered = sorted(file_records, key=lambda item: item["path"].encode("utf-8"))
    canonical = b"".join(
        f"{item['mode']} {item['sha256']} {item['path']}\n".encode("utf-8")
        for item in ordered
    )
    return sha256(canonical)


def expect_archive_rejected(
    *,
    label: str,
    archive: Path,
    manifest: dict[str, Any],
    expected_text: str,
) -> None:
    try:
        verify_archive(archive, manifest)
    except CheckFailure as error:
        if expected_text.lower() not in str(error).lower():
            raise CheckFailure(f"{label} was rejected for the wrong reason: {error}") from error
    else:
        raise CheckFailure(f"Malformed archive passed verification: {label}")
    print(f"PASS negative case: {label}")


def run_verifier_hardening_cases(
    *,
    archive_path: Path,
    manifest: dict[str, Any],
    repo_root: Path,
    commit: str,
    temporary_root: Path,
) -> None:
    gzip_tamper = temporary_root / "gzip-header-tamper.tar.gz"
    gzip_bytes = bytearray(archive_path.read_bytes())
    gzip_bytes[9] = 0x03
    gzip_tamper.write_bytes(gzip_bytes)
    gzip_manifest = json.loads(json.dumps(manifest))
    gzip_manifest["archive_sha256"] = sha256(gzip_tamper.read_bytes())
    expect_archive_rejected(
        label="non-normalized gzip header is rejected",
        archive=gzip_tamper,
        manifest=gzip_manifest,
        expected_text="gzip header",
    )

    extra_directory_archive = temporary_root / "unmanifested-directory.tar.gz"
    rewrite_archive(
        archive_path,
        extra_directory_archive,
        extra_directory=f"{ROOT_PLUGIN}/unmanifested-empty-directory",
    )
    directory_manifest = json.loads(json.dumps(manifest))
    directory_manifest["archive_sha256"] = sha256(extra_directory_archive.read_bytes())
    expect_archive_rejected(
        label="unmanifested empty directory is rejected",
        archive=extra_directory_archive,
        manifest=directory_manifest,
        expected_text="directory inventory",
    )

    first_path = PurePosixPath(manifest["files"][0]["path"])
    parent = first_path.parent.as_posix()
    newline_basename = "PE005-invalid\npath.bin"
    newline_relative_path = newline_basename if parent == "." else f"{parent}/{newline_basename}"
    newline_archive = temporary_root / "newline-path.tar.gz"
    renamed_to = rewrite_archive(
        archive_path,
        newline_archive,
        rename_first_file=f"{ROOT_PLUGIN}/{newline_relative_path}",
    )
    if renamed_to is None:
        raise CheckFailure("Cannot prepare newline-path archive fixture.")
    newline_manifest = json.loads(json.dumps(manifest))
    newline_manifest["files"][0]["path"] = newline_relative_path
    newline_manifest["files"].sort(key=lambda item: item["path"].encode("utf-8"))
    newline_manifest["tree_sha256"] = update_tree_hash(newline_manifest["files"])
    newline_manifest["archive_sha256"] = sha256(newline_archive.read_bytes())
    expect_archive_rejected(
        label="newline-bearing archive path is rejected",
        archive=newline_archive,
        manifest=newline_manifest,
        expected_text="unsafe or non-normalized archive path",
    )

    try:
        validate_manifest(
            newline_manifest,
            repo_root=repo_root,
            commit=commit,
            requested_ref=manifest["source_ref_requested"],
        )
    except CheckFailure as error:
        if "cr or lf" not in str(error).lower():
            raise CheckFailure(f"Newline-bearing manifest path failed for the wrong reason: {error}") from error
    else:
        raise CheckFailure("Newline-bearing manifest path passed schema validation.")
    print("PASS negative case: newline-bearing manifest path is rejected")


def run_output_sentinel_guard_case(
    *,
    fixture: Path,
    temporary_root: Path,
) -> None:
    output = temporary_root / "sentinel-guard-output"
    output.mkdir()
    (output / "sentinel.txt").write_text("preserve me\n", encoding="utf-8")
    malicious_builder = temporary_root / "delete-sentinel-builder.py"
    malicious_builder.write_text(
        "import sys\n"
        "from pathlib import Path\n"
        "output = Path(sys.argv[sys.argv.index('--output') + 1])\n"
        "(output / 'sentinel.txt').unlink()\n"
        "print('sentinel guard probe', file=sys.stderr)\n"
        "raise SystemExit(1)\n",
        encoding="utf-8",
    )
    try:
        expect_build_failure(
            label="verifier detects output sentinel deletion",
            builder=malicious_builder,
            fixture=fixture,
            ref="HEAD",
            output=output,
            expected_text="sentinel guard probe",
            output_must_remain=True,
            sentinel_must_remain=True,
        )
    except CheckFailure as error:
        if "sentinel" not in str(error).lower():
            raise CheckFailure(f"Sentinel deletion was rejected for the wrong reason: {error}") from error
    else:
        raise CheckFailure("Verifier accepted a builder that deleted an existing output sentinel.")
    print("PASS negative case: verifier detects output sentinel deletion")


def run_negative_cases(
    *,
    repo_root: Path,
    commit: str,
    builder: Path,
    temporary_root: Path,
) -> None:
    cases = temporary_root / "negative-cases"
    cases.mkdir()

    fixture = create_fixture(repo_root, commit, cases / "patch-fails")
    patch_file = fixture / PATCH_PATH
    patch = patch_file.read_text(encoding="utf-8")
    patch = re.sub(r"@@ -25,0 \+26,20 @@", "@@ -25,0 +26,999 @@", patch, count=1)
    patch_file.write_text(patch, encoding="utf-8")
    commit_fixture_changes(fixture, "fixture: make integration patch fail")
    expect_build_failure(
        label="integration.patch does not apply",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=cases / "patch-fails-output",
        expected_text="patch",
    )

    fixture = create_fixture(repo_root, commit, cases / "already-integrated")
    entrypoint = fixture / ROOT_PLUGIN / "voelgoed-platform-core.php"
    entrypoint_text = entrypoint.read_text(encoding="utf-8")
    marker = "    'includes/Performance/CachePolicy.php',\n"
    if marker not in entrypoint_text:
        raise CheckFailure("Cannot prepare already-integrated bootstrap fixture.")
    entrypoint.write_text(
        entrypoint_text.replace(
            marker,
            marker + "    'includes/Taxonomy/ContentTopicTaxonomy.php',\n",
            1,
        ),
        encoding="utf-8",
    )
    commit_fixture_changes(fixture, "fixture: pre-integrate one module include")
    expect_build_failure(
        label="root bootstrap already contains an integration include",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=cases / "already-integrated-output",
        expected_text="already integrated",
    )

    fixture = create_fixture(repo_root, commit, cases / "missing-overlay")
    missing_module = fixture / "site-code/voelgoed-platform-core/includes/Event/EventParticipationAdmin.php"
    if not missing_module.is_file():
        raise CheckFailure("Cannot prepare missing-overlay fixture.")
    missing_module.unlink()
    commit_fixture_changes(fixture, "fixture: remove an overlay PHP module")
    expect_build_failure(
        label="tracked overlay PHP module is missing",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=cases / "missing-overlay-output",
        expected_text="EventParticipationAdmin.php",
    )

    fixture = create_fixture(repo_root, commit, cases / "missing-include")
    patch_file = fixture / PATCH_PATH
    patch = patch_file.read_text(encoding="utf-8")
    old_include = "includes/Taxonomy/ContentTopicTaxonomy.php"
    if old_include not in patch:
        raise CheckFailure("Cannot prepare missing-include fixture.")
    patch_file.write_text(patch.replace(old_include, "includes/Taxonomy/PE005Missing.php", 1), encoding="utf-8")
    commit_fixture_changes(fixture, "fixture: point bootstrap to a missing module")
    expect_build_failure(
        label="assembled bootstrap include points to a missing file",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=cases / "missing-include-output",
        expected_text="PE005Missing.php",
    )

    fixture = create_fixture(repo_root, commit, cases / "duplicate-bootstrap")
    patch_file = fixture / PATCH_PATH
    patch_lines = patch_file.read_text(encoding="utf-8").splitlines(keepends=True)
    added_line_index = next(
        (
            index
            for index, line in enumerate(patch_lines)
            if re.match(r"\+\s*'includes/(?:Taxonomy|Event|Product)/[^']+',$", line.rstrip("\n"))
        ),
        None,
    )
    if added_line_index is None:
        raise CheckFailure("Cannot prepare duplicate-bootstrap fixture.")
    hunk_index = max(index for index in range(added_line_index) if patch_lines[index].startswith("@@ "))
    hunk_match = re.match(r"(@@ -\d+,0 \+\d+,)(\d+)( @@.*\n?)$", patch_lines[hunk_index])
    if hunk_match is None:
        raise CheckFailure("Cannot update the duplicate-bootstrap patch hunk count.")
    patch_lines[hunk_index] = f"{hunk_match.group(1)}{int(hunk_match.group(2)) + 1}{hunk_match.group(3)}"
    patch_lines.insert(added_line_index + 1, patch_lines[added_line_index])
    patch_file.write_text("".join(patch_lines), encoding="utf-8")
    commit_fixture_changes(fixture, "fixture: duplicate an integration include")
    expect_build_failure(
        label="duplicate integration bootstrap entries",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=cases / "duplicate-bootstrap-output",
        expected_text="duplicate",
    )

    fixture = create_fixture(repo_root, commit, cases / "unreachable-lifecycle")
    patch_file = fixture / PATCH_PATH
    patch_lines = patch_file.read_text(encoding="utf-8").splitlines(keepends=True)
    registration_index = next(
        (
            index
            for index, line in enumerate(patch_lines)
            if line.startswith("+        $this->register_component('content_topic_taxonomy'")
        ),
        None,
    )
    if registration_index is None:
        raise CheckFailure("Cannot prepare unreachable-lifecycle fixture.")
    hunk_index = max(index for index in range(registration_index) if patch_lines[index].startswith("@@ "))
    hunk_match = re.match(r"(@@ -\d+,0 \+\d+,)(\d+)( @@.*\n?)$", patch_lines[hunk_index])
    if hunk_match is None:
        raise CheckFailure("Cannot update unreachable-lifecycle patch hunk count.")
    patch_lines[hunk_index] = f"{hunk_match.group(1)}{int(hunk_match.group(2)) + 1}{hunk_match.group(3)}"
    patch_lines.insert(registration_index, "+        return;\n")
    patch_file.write_text("".join(patch_lines), encoding="utf-8")
    commit_fixture_changes(fixture, "fixture: make lifecycle registrations unreachable")
    expect_build_failure(
        label="unreachable lifecycle registrations",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=cases / "unreachable-lifecycle-output",
        expected_text="exit before",
    )

    fixture = create_fixture(repo_root, commit, cases / "unsafe-output")
    protected_source = fixture / ROOT_PLUGIN / "voelgoed-platform-core.php"
    original_sha = sha256(protected_source.read_bytes())
    expect_build_failure(
        label="unsafe output points into the source plugin tree",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=fixture / ROOT_PLUGIN,
        expected_text="unsafe",
        output_must_remain=True,
    )
    if sha256(protected_source.read_bytes()) != original_sha:
        raise CheckFailure("Unsafe-output case modified the source plugin tree.")

    fixture = create_fixture(repo_root, commit, cases / "occupied-output")
    occupied_output = cases / "occupied-output-target"
    occupied_output.mkdir()
    sentinel = occupied_output / "sentinel.txt"
    sentinel.write_text("preserve me\n", encoding="utf-8")
    expect_build_failure(
        label="non-empty output destination is preserved",
        builder=builder,
        fixture=fixture,
        ref="HEAD",
        output=occupied_output,
        expected_text="output",
        output_must_remain=True,
        sentinel_must_remain=True,
    )
    run_output_sentinel_guard_case(fixture=fixture, temporary_root=cases)


def run_atomic_publish_case(builder: Path, temporary_root: Path) -> None:
    spec = importlib.util.spec_from_file_location("pe005_platform_builder_under_test", builder)
    if spec is None or spec.loader is None:
        raise CheckFailure("Cannot load the builder publication primitive for its race fixture.")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    publish = getattr(module, "atomic_publish_no_replace", None)
    if not callable(publish):
        raise CheckFailure("Builder has no atomic no-replace publication primitive.")

    source = temporary_root / "publication-source"
    source.mkdir()
    (source / "artifact.txt").write_text("staged artifact\n", encoding="utf-8")
    destination = temporary_root / "publication-destination"
    destination.mkdir()
    try:
        publish(source, destination)
    except module.BuildFailure as error:
        if "already exists" not in str(error).lower() and "appeared" not in str(error).lower():
            raise CheckFailure(f"No-replace publication failed for the wrong reason: {error}") from error
    else:
        raise CheckFailure("Atomic publication replaced a destination that appeared before publish.")
    if not destination.is_dir() or list(destination.iterdir()):
        raise CheckFailure("No-replace publication changed the existing empty destination.")
    if not (source / "artifact.txt").is_file():
        raise CheckFailure("No-replace publication consumed the staged source after rejection.")
    print("PASS negative case: atomic publish refuses a destination that appears during assembly")


def run_integrity_tamper_case(archive_path: Path, manifest: dict[str, Any], temporary_root: Path) -> None:
    corrupted_archive = temporary_root / "corrupted.tar.gz"
    tamper_archive(archive_path, corrupted_archive)
    corrupted_manifest = json.loads(json.dumps(manifest))
    corrupted_manifest["archive_sha256"] = sha256(corrupted_archive.read_bytes())
    try:
        verify_archive(corrupted_archive, corrupted_manifest)
    except CheckFailure as error:
        if "inventory" not in str(error).lower() and "hash" not in str(error).lower():
            raise CheckFailure(f"Tamper was rejected for an unexpected reason: {error}") from error
    else:
        raise CheckFailure("Archive tampering passed after updating archive_sha256 in the manifest.")
    print("PASS negative case: modified artifact bytes disagree with manifest inventory")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ref", default="HEAD", help="Git ref resolved once for both builds (default: HEAD)")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    repo_result = subprocess.run(
        ["git", "rev-parse", "--show-toplevel"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if repo_result.returncode != 0:
        raise CheckFailure("Run this verifier from inside a Git checkout.")
    repo_root = Path(repo_result.stdout.decode("utf-8").strip()).resolve()
    builder = repo_root / BUILDER_PATH
    if not builder.is_file():
        raise CheckFailure(f"Builder not found at {BUILDER_PATH}; implement it after this verifier.")

    commit = resolve_commit(repo_root, args.ref)
    print(f"source_commit={commit}")

    with tempfile.TemporaryDirectory(prefix="vgpc-repro-") as temporary_name:
        temporary_root = Path(temporary_name)
        build_a = temporary_root / "build-a"
        build_b = temporary_root / "build-b"

        result_a = run_builder(builder, repo_root, commit, build_a, requested_ref=args.ref)
        if result_a.returncode != 0:
            raise CheckFailure(
                "Build A failed:\n"
                + (result_a.stdout + result_a.stderr).decode("utf-8", "replace")
            )
        result_b = run_builder(builder, repo_root, commit, build_b, requested_ref=args.ref)
        if result_b.returncode != 0:
            raise CheckFailure(
                "Build B failed:\n"
                + (result_b.stdout + result_b.stderr).decode("utf-8", "replace")
            )

        manifest_path_a = build_a / MANIFEST_NAME
        manifest_path_b = build_b / MANIFEST_NAME
        archive_path_a = build_a / ARCHIVE_NAME
        archive_path_b = build_b / ARCHIVE_NAME
        if not all(path.is_file() for path in (manifest_path_a, manifest_path_b, archive_path_a, archive_path_b)):
            raise CheckFailure("A build did not produce exactly the required archive and manifest files.")
        if {path.name for path in build_a.iterdir()} != {ARCHIVE_NAME, MANIFEST_NAME}:
            raise CheckFailure("Build A output contains files beyond the archive and manifest.")
        if {path.name for path in build_b.iterdir()} != {ARCHIVE_NAME, MANIFEST_NAME}:
            raise CheckFailure("Build B output contains files beyond the archive and manifest.")

        manifest_a = json.loads(manifest_path_a.read_text(encoding="utf-8"))
        manifest_b = json.loads(manifest_path_b.read_text(encoding="utf-8"))
        validate_manifest(manifest_a, repo_root=repo_root, commit=commit, requested_ref=args.ref)
        validate_manifest(manifest_b, repo_root=repo_root, commit=commit, requested_ref=args.ref)
        if manifest_a != manifest_b:
            raise CheckFailure("Build A and B manifest semantic content differs.")
        if archive_path_a.read_bytes() != archive_path_b.read_bytes():
            raise CheckFailure("Build A and B archive bytes differ.")
        if manifest_a.get("source_commit") != commit:
            raise CheckFailure("Manifest source_commit differs from the resolved verifier commit.")
        verify_archive(archive_path_a, manifest_a)
        verify_archive(archive_path_b, manifest_b)

        for label, output in (("A", build_a), ("B", build_b)):
            manifest_path = output / MANIFEST_NAME
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            print(f"build_{label}_file_count={manifest['file_count']}")
            print(f"build_{label}_tree_sha256={manifest['tree_sha256']}")
            print(f"build_{label}_archive_sha256={manifest['archive_sha256']}")
            print(f"build_{label}_manifest_sha256={sha256(manifest_path.read_bytes())}")

        run_integrity_tamper_case(archive_path_a, manifest_a, temporary_root)
        run_verifier_hardening_cases(
            archive_path=archive_path_a,
            manifest=manifest_a,
            repo_root=repo_root,
            commit=commit,
            temporary_root=temporary_root,
        )
        run_negative_cases(
            repo_root=repo_root,
            commit=commit,
            builder=builder,
            temporary_root=temporary_root,
        )
        run_atomic_publish_case(builder, temporary_root)

    if temporary_root.exists():
        raise CheckFailure("Temporary verifier state was not cleaned.")
    print("PASS: two exact-commit builds, independent archive verification, negative cases, and cleanup")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except CheckFailure as error:
        print(f"FAIL: {error}", file=sys.stderr)
        raise SystemExit(1)
