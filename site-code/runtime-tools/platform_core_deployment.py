"""Fail-closed deployment support for the canonical Platform Core artifact."""

from __future__ import annotations

import hashlib
import ctypes
import errno
import json
import os
import re
import subprocess
import sys
import uuid
import stat
import tarfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath
from typing import Any
import urllib.error
import urllib.parse
import urllib.request


class DeploymentError(RuntimeError):
    """Raised when a deployment precondition or transition fails."""


TARGET_ID = "vgnova-local-10039"
CRITICAL_SOURCE_PATHS = (
    "site-code/bin/deploy-platform-core.py",
    "site-code/runtime-tools/platform_core_deployment.py",
    "site-code/bin/check-platform-core-build.py",
    "site-code/runtime-tools/platform-core-deployment-health.php",
)
MUTATION_SESSION_ENVIRONMENT = (
    "VGNOVA_MUTATION_SESSION_ID",
    "VGNOVA_MUTATION_TARGET",
    "VGNOVA_MUTATION_DB_LOCK_A",
    "VGNOVA_MUTATION_DB_CONNECTION_ID_A",
    "VGNOVA_MUTATION_DB_LOCK_B",
    "VGNOVA_MUTATION_DB_CONNECTION_ID_B",
    "VGNOVA_MUTATION_LOCK_FILE",
)
DEPLOYMENT_PHASES = {
    "prepared",
    "stage_verified",
    "swap_intent",
    "swapped",
    "health_verified",
    "parity_verified",
    "finalize_intent",
    "finalized",
    "rollback_intent",
    "rolled_back",
    "rollback_failed",
}
JOURNAL_KEYS = {
    "schema_version",
    "deployment_id",
    "target",
    "wp_root",
    "source_commit",
    "archive_sha256",
    "manifest_sha256",
    "target_tree_sha256",
    "target_manifest_records",
    "target_directories",
    "old_runtime_tree_sha256",
    "old_runtime_snapshot",
    "active_path",
    "stage_path",
    "rollback_path",
    "failed_candidate_path",
    "phase",
    "phase_history",
    "timestamps",
    "recovery_status",
    "health_verification_result",
    "runtime_parity_result",
    "provenance",
}
PHASE_TRANSITIONS = {
    "prepared": {"stage_verified", "rollback_intent", "rollback_failed"},
    "stage_verified": {"swap_intent", "rollback_intent", "rollback_failed"},
    "swap_intent": {"swapped", "rollback_intent", "rollback_failed"},
    "swapped": {"health_verified", "rollback_intent", "rollback_failed"},
    "health_verified": {"parity_verified", "rollback_intent", "rollback_failed"},
    "parity_verified": {"finalize_intent", "rollback_intent", "rollback_failed"},
    "finalize_intent": {"finalized", "rollback_intent", "rollback_failed"},
    "rollback_intent": {"rolled_back", "rollback_failed", "finalize_intent"},
    "rollback_failed": {"rollback_intent", "rolled_back", "finalize_intent", "finalized", "rollback_failed"},
    "rolled_back": set(),
    "finalized": {"rollback_intent"},
}


def sha256_bytes(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def safe_relative_path(value: str) -> PurePosixPath:
    """Return a normalized archive-relative path or reject it."""
    if not isinstance(value, str) or not value:
        raise DeploymentError("Archive path must be a non-empty string.")
    try:
        value.encode("utf-8", "strict")
    except UnicodeEncodeError as error:
        raise DeploymentError("Archive path is not valid UTF-8.") from error
    if (
        value.startswith("/")
        or "\\" in value
        or "\0" in value
        or "\r" in value
        or "\n" in value
        or any(segment in ("", ".", "..") for segment in value.split("/"))
    ):
        raise DeploymentError("Archive path is unsafe or non-normalized.")
    path = PurePosixPath(value)
    if path.is_absolute() or path.as_posix() != value:
        raise DeploymentError("Archive path is unsafe or non-normalized.")
    return path


def canonical_tree_sha256(records: list[dict[str, Any]]) -> str:
    lines = b"".join(
        f"{record['mode']} {record['sha256']} {record['path']}\n".encode("utf-8")
        for record in records
    )
    return sha256_bytes(lines)


def validate_manifest_records(manifest: dict[str, Any]) -> list[dict[str, Any]]:
    """Validate the canonical file inventory and return independent records."""
    if not isinstance(manifest, dict):
        raise DeploymentError("Artifact manifest must be an object.")
    records = manifest.get("files")
    count = manifest.get("file_count")
    tree_hash = manifest.get("tree_sha256")
    if type(count) is not int or count < 1 or not isinstance(records, list) or count != len(records):
        raise DeploymentError("Artifact manifest file count is invalid.")
    if not isinstance(tree_hash, str) or re.fullmatch(r"[0-9a-f]{64}", tree_hash) is None:
        raise DeploymentError("Artifact manifest tree digest is invalid.")

    validated: list[dict[str, Any]] = []
    for record in records:
        if not isinstance(record, dict) or set(record) != {"path", "mode", "size", "sha256"}:
            raise DeploymentError("Artifact manifest file record has an unexpected shape.")
        path = record["path"]
        safe_relative_path(path)
        if record["mode"] not in ("100644", "100755"):
            raise DeploymentError("Artifact manifest file mode is unsupported.")
        if type(record["size"]) is not int or record["size"] < 0:
            raise DeploymentError("Artifact manifest file size is invalid.")
        if not isinstance(record["sha256"], str) or re.fullmatch(r"[0-9a-f]{64}", record["sha256"]) is None:
            raise DeploymentError("Artifact manifest file digest is invalid.")
        validated.append(dict(record))

    paths = [record["path"] for record in validated]
    if paths != sorted(paths, key=lambda item: item.encode("utf-8")) or len(set(paths)) != len(paths):
        raise DeploymentError("Artifact manifest paths are duplicated or not canonically sorted.")
    if canonical_tree_sha256(validated) != tree_hash:
        raise DeploymentError("Artifact manifest tree digest does not match its file records.")
    return validated


def _file_record(path: str, mode: int, size: int, digest: str) -> dict[str, Any]:
    return {
        "path": path,
        "mode": "100755" if mode == 0o755 else "100644",
        "size": size,
        "sha256": digest,
    }


def _hash_regular_file(path: Path, expected_stat: os.stat_result) -> tuple[int, str]:
    flags = os.O_RDONLY | getattr(os, "O_CLOEXEC", 0) | getattr(os, "O_NOFOLLOW", 0)
    try:
        descriptor = os.open(path, flags)
    except OSError as error:
        raise DeploymentError("Could not safely open a runtime file.") from error
    digest = hashlib.sha256()
    size = 0
    with os.fdopen(descriptor, "rb", closefd=True) as stream:
        opened = os.fstat(stream.fileno())
        if not stat.S_ISREG(opened.st_mode) or (opened.st_dev, opened.st_ino) != (
            expected_stat.st_dev,
            expected_stat.st_ino,
        ):
            raise DeploymentError("Runtime file changed while it was being fingerprinted.")
        while chunk := stream.read(1024 * 1024):
            digest.update(chunk)
            size += len(chunk)
        after = os.fstat(stream.fileno())
        if (after.st_dev, after.st_ino, after.st_size) != (opened.st_dev, opened.st_ino, size):
            raise DeploymentError("Runtime file changed while it was being fingerprinted.")
    return size, digest.hexdigest()


def fingerprint_tree(root: Path) -> dict[str, Any]:
    """Fingerprint regular files and reject links or unsupported objects."""
    root = Path(root)
    try:
        root_stat = os.lstat(root)
    except OSError as error:
        raise DeploymentError("Runtime tree root is missing or unreadable.") from error
    if not stat.S_ISDIR(root_stat.st_mode) or stat.S_ISLNK(root_stat.st_mode):
        raise DeploymentError("Runtime tree root must be a real directory.")

    files: list[dict[str, Any]] = []
    directories: list[dict[str, Any]] = []

    def visit(directory: Path, relative: PurePosixPath | None = None) -> None:
        try:
            children = sorted(os.scandir(directory), key=lambda item: item.name.encode("utf-8", "strict"))
        except (OSError, UnicodeEncodeError) as error:
            raise DeploymentError("Runtime tree contains an unreadable directory or invalid path.") from error
        for child in children:
            try:
                child_stat = child.stat(follow_symlinks=False)
                child_relative = PurePosixPath(child.name) if relative is None else relative / child.name
                relative_name = child_relative.as_posix()
                safe_relative_path(relative_name)
            except (OSError, UnicodeEncodeError, ValueError) as error:
                raise DeploymentError("Runtime tree contains an unsafe path.") from error
            if child_stat.st_dev != root_stat.st_dev:
                raise DeploymentError("Runtime tree crosses a filesystem boundary.")
            if stat.S_ISDIR(child_stat.st_mode):
                directories.append({"path": relative_name, "mode": stat.S_IMODE(child_stat.st_mode),
                                    "owner": child_stat.st_uid, "group": child_stat.st_gid})
                visit(Path(child.path), child_relative)
                continue
            if not stat.S_ISREG(child_stat.st_mode) or child_stat.st_nlink != 1:
                raise DeploymentError("Runtime tree contains a link or unsupported filesystem object.")
            file_mode = stat.S_IMODE(child_stat.st_mode)
            if file_mode not in (0o644, 0o755):
                raise DeploymentError("Runtime tree contains an unsupported file mode.")
            size, digest = _hash_regular_file(Path(child.path), child_stat)
            files.append(_file_record(relative_name, file_mode, size, digest))

    visit(root)
    files.sort(key=lambda item: item["path"].encode("utf-8"))
    directories.sort(key=lambda item: item["path"].encode("utf-8"))
    return {
        "file_count": len(files),
        "files": files,
        "directories": directories,
        "tree_sha256": canonical_tree_sha256(files),
        "root_owner": root_stat.st_uid,
        "root_group": root_stat.st_gid,
        "root_mode": stat.S_IMODE(root_stat.st_mode),
        "device_id": root_stat.st_dev,
    }


def _archive_member_path(member: tarfile.TarInfo) -> tuple[str, bool]:
    name = member.name
    is_directory = member.type == tarfile.DIRTYPE
    if is_directory:
        if name.endswith("//"):
            raise DeploymentError("Archive directory path is not canonical.")
        name = name[:-1] if name.endswith("/") else name
    elif name.endswith("/"):
        raise DeploymentError("Archive file path is not canonical.")
    path = safe_relative_path(name)
    return path.as_posix(), is_directory


def _archive_inventory(archive_path: Path, records: list[dict[str, Any]]) -> tuple[list[str], list[str]]:
    expected = {record["path"]: record for record in records}
    expected_directories = {""}
    for record in records:
        relative = safe_relative_path(record["path"])
        for parent in relative.parents:
            if parent.as_posix() != ".":
                expected_directories.add(parent.as_posix())

    actual_files: list[str] = []
    actual_directories: list[str] = []
    seen: set[str] = set()
    roots: set[str] = set()
    try:
        with tarfile.open(archive_path, mode="r:gz") as archive:
            for member in archive.getmembers():
                name, is_directory = _archive_member_path(member)
                if name in seen:
                    raise DeploymentError("Archive contains a duplicate path.")
                seen.add(name)
                roots.add(name.split("/", 1)[0])
                parts = name.split("/")
                if parts[0] != "voelgoed-platform-core":
                    raise DeploymentError("Archive contains an unexpected top-level path.")
                if is_directory:
                    if member.mode != 0o755:
                        raise DeploymentError("Archive directory mode is unsupported.")
                    relative = "/".join(parts[1:])
                    if relative not in expected_directories:
                        raise DeploymentError("Archive contains an unmanifested directory.")
                    actual_directories.append(relative)
                    continue
                if member.type not in (tarfile.REGTYPE, tarfile.AREGTYPE):
                    raise DeploymentError("Archive contains a link or unsupported filesystem object.")
                if len(parts) < 2:
                    raise DeploymentError("Archive file is outside the plugin tree.")
                relative = "/".join(parts[1:])
                record = expected.get(relative)
                if record is None:
                    raise DeploymentError("Archive contains a file absent from the manifest.")
                expected_mode = 0o755 if record["mode"] == "100755" else 0o644
                if member.mode != expected_mode or member.size != record["size"]:
                    raise DeploymentError("Archive file mode or size differs from the manifest.")
                stream = archive.extractfile(member)
                if stream is None:
                    raise DeploymentError("Archive file contents cannot be read.")
                digest = hashlib.sha256()
                size = 0
                with stream:
                    while chunk := stream.read(1024 * 1024):
                        digest.update(chunk)
                        size += len(chunk)
                if size != record["size"] or digest.hexdigest() != record["sha256"]:
                    raise DeploymentError("Archive file content differs from the manifest.")
                actual_files.append(relative)
    except (OSError, tarfile.TarError) as error:
        raise DeploymentError("Artifact archive cannot be read safely.") from error

    if roots != {"voelgoed-platform-core"}:
        raise DeploymentError("Archive must contain only the canonical plugin root.")
    expected_file_paths = set(expected)
    if set(actual_files) != expected_file_paths or len(actual_files) != len(expected_file_paths):
        raise DeploymentError("Archive file inventory differs from the manifest.")
    if set(actual_directories) != expected_directories or len(actual_directories) != len(expected_directories):
        raise DeploymentError("Archive directory inventory differs from the manifest.")
    return actual_files, actual_directories


def _create_file(path: Path, contents: bytes, mode: int) -> None:
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_CLOEXEC", 0) | getattr(os, "O_NOFOLLOW", 0)
    descriptor = os.open(path, flags, mode)
    with os.fdopen(descriptor, "wb", closefd=True) as stream:
        stream.write(contents)
        os.fchmod(stream.fileno(), mode)
        os.fsync(stream.fileno())


def _remove_owned_tree(path: Path) -> None:
    try:
        info = os.lstat(path)
    except FileNotFoundError:
        return
    if not stat.S_ISDIR(info.st_mode) or stat.S_ISLNK(info.st_mode):
        raise DeploymentError("Owned staging path changed to an unsupported object.")
    for child in os.scandir(path):
        child_path = Path(child.path)
        child_info = child.stat(follow_symlinks=False)
        if stat.S_ISDIR(child_info.st_mode) and not stat.S_ISLNK(child_info.st_mode):
            _remove_owned_tree(child_path)
        elif stat.S_ISREG(child_info.st_mode) and child_info.st_nlink == 1:
            os.unlink(child_path)
        else:
            raise DeploymentError("Owned staging tree contains an unsupported object.")
    os.rmdir(path)


def extract_verified_archive(
    archive_path: Path,
    stage_path: Path,
    records: list[dict[str, Any]],
) -> dict[str, Any]:
    """Inspect a verified archive, then write only its exact manifest files."""
    manifest = {
        "file_count": len(records),
        "files": records,
        "tree_sha256": canonical_tree_sha256(records),
    }
    validated = validate_manifest_records(manifest)
    _archive_inventory(Path(archive_path), validated)
    stage_path = Path(stage_path)
    if os.path.lexists(stage_path):
        raise DeploymentError("Staging path already exists.")
    parent_stat = os.lstat(stage_path.parent)
    if not stat.S_ISDIR(parent_stat.st_mode) or stat.S_ISLNK(parent_stat.st_mode):
        raise DeploymentError("Staging parent must be a real directory.")

    expected_directories: set[str] = set()
    for record in validated:
        relative = safe_relative_path(record["path"])
        for parent in relative.parents:
            if parent.as_posix() != ".":
                expected_directories.add(parent.as_posix())
    stage_path.mkdir(mode=0o755)
    os.chmod(stage_path, 0o755)
    try:
        for relative in sorted(expected_directories, key=lambda item: (item.count("/"), item.encode("utf-8"))):
            destination = stage_path.joinpath(*safe_relative_path(relative).parts)
            destination.mkdir(mode=0o755)
            os.chmod(destination, 0o755)

        expected = {record["path"]: record for record in validated}
        with tarfile.open(archive_path, mode="r:gz") as archive:
            for member in archive.getmembers():
                if member.type not in (tarfile.REGTYPE, tarfile.AREGTYPE):
                    continue
                member_name, _ = _archive_member_path(member)
                relative = "/".join(member_name.split("/", 1)[1:])
                record = expected[relative]
                stream = archive.extractfile(member)
                if stream is None:
                    raise DeploymentError("Archive file contents cannot be read.")
                destination = stage_path.joinpath(*safe_relative_path(relative).parts)
                contents = stream.read()
                mode = 0o755 if record["mode"] == "100755" else 0o644
                if len(contents) != record["size"] or sha256_bytes(contents) != record["sha256"]:
                    raise DeploymentError("Archive file changed during staging.")
                _create_file(destination, contents, mode)
        for relative in sorted(expected_directories, key=lambda item: (-item.count("/"), item.encode("utf-8"))):
            _fsync_directory(stage_path.joinpath(*safe_relative_path(relative).parts))
        _fsync_directory(stage_path)
        _fsync_directory(stage_path.parent)
        staged = fingerprint_tree(stage_path)
        if staged["files"] != validated or staged["tree_sha256"] != manifest["tree_sha256"]:
            raise DeploymentError("Staged tree differs from the verified artifact manifest.")
        return staged
    except BaseException:
        # Retain an incomplete stage for diagnosis; its identity is unknown.
        raise


def ensure_state_root(path: Path, *, create: bool = False) -> Path:
    """Require a private, user-owned deployment state directory."""
    path = Path(path)
    if os.path.lexists(path):
        try:
            info = os.lstat(path)
        except OSError as error:
            raise DeploymentError("Deployment state root cannot be inspected.") from error
        if not stat.S_ISDIR(info.st_mode) or stat.S_ISLNK(info.st_mode):
            raise DeploymentError("Deployment state root must be a real directory.")
    elif create:
        parent = os.lstat(path.parent)
        if not stat.S_ISDIR(parent.st_mode) or stat.S_ISLNK(parent.st_mode):
            raise DeploymentError("Deployment state parent must be a real directory.")
        try:
            path.mkdir(mode=0o700)
            _fsync_directory(path.parent)
        except OSError as error:
            raise DeploymentError("Deployment state root could not be created.") from error
        info = os.lstat(path)
    else:
        raise DeploymentError("Deployment state root does not exist.")
    if info.st_uid != os.getuid() or stat.S_IMODE(info.st_mode) != 0o700:
        raise DeploymentError("Deployment state root must be owned by this user with mode 0700.")
    return path


def _reject_duplicate_json_keys(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise DeploymentError("Deployment journal contains a duplicate JSON key.")
        result[key] = value
    return result


def _validate_directory_records(records: Any) -> None:
    if not isinstance(records, list):
        raise DeploymentError("Deployment journal directories are invalid.")
    paths = []
    for item in records:
        if not isinstance(item, dict) or set(item) != {"path", "mode", "owner", "group"}:
            raise DeploymentError("Deployment journal directory metadata is invalid.")
        safe_relative_path(item["path"])
        if any(type(item[key]) is not int or item[key] < 0 for key in ("mode", "owner", "group")) or item["mode"] > 0o7777:
            raise DeploymentError("Deployment journal directory metadata is invalid.")
        paths.append(item["path"])
    if paths != sorted(set(paths), key=lambda item: item.encode("utf-8")):
        raise DeploymentError("Deployment journal directories are not canonical.")


def validate_journal(record: dict[str, Any]) -> None:
    """Validate the exact durable deployment journal shape."""
    if not isinstance(record, dict) or set(record) != JOURNAL_KEYS:
        raise DeploymentError("Deployment journal has an unexpected schema.")
    if type(record["schema_version"]) is not int or record["schema_version"] != 1:
        raise DeploymentError("Deployment journal schema version is unsupported.")
    if not isinstance(record["deployment_id"], str) or re.fullmatch(r"[a-f0-9]{32}", record["deployment_id"]) is None:
        raise DeploymentError("Deployment journal ID is invalid.")
    if record["target"] != TARGET_ID:
        raise DeploymentError("Deployment journal target is unsupported.")
    if not isinstance(record["wp_root"], str) or not Path(record["wp_root"]).is_absolute():
        raise DeploymentError("Deployment journal WordPress root is invalid.")
    wp_root = Path(record["wp_root"])
    if wp_root == Path("/"):
        raise DeploymentError("Deployment journal WordPress root is invalid.")
    if not isinstance(record["source_commit"], str) or re.fullmatch(r"[a-f0-9]{40}", record["source_commit"]) is None:
        raise DeploymentError("Deployment journal source commit is invalid.")
    for field in ("archive_sha256", "manifest_sha256", "target_tree_sha256", "old_runtime_tree_sha256"):
        if not isinstance(record[field], str) or re.fullmatch(r"[a-f0-9]{64}", record[field]) is None:
            raise DeploymentError(f"Deployment journal {field} is invalid.")
    target_records = validate_manifest_records({
        "file_count": len(record["target_manifest_records"]) if isinstance(record["target_manifest_records"], list) else -1,
        "files": record["target_manifest_records"],
        "tree_sha256": record["target_tree_sha256"],
    })
    if canonical_tree_sha256(target_records) != record["target_tree_sha256"]:
        raise DeploymentError("Deployment journal target records do not match the target tree digest.")
    old_snapshot = record["old_runtime_snapshot"]
    if not isinstance(old_snapshot, dict) or set(old_snapshot) != {
        "file_count",
        "files",
        "directories",
        "tree_sha256",
        "root_owner",
        "root_group",
        "root_mode",
        "device_id",
    }:
        raise DeploymentError("Deployment journal prior-runtime snapshot is invalid.")
    old_records = validate_manifest_records({
        "file_count": old_snapshot.get("file_count"),
        "files": old_snapshot.get("files"),
        "tree_sha256": old_snapshot.get("tree_sha256"),
    })
    if old_snapshot["tree_sha256"] != record["old_runtime_tree_sha256"]:
        raise DeploymentError("Deployment journal prior-runtime snapshot digest is inconsistent.")
    _validate_directory_records(old_snapshot["directories"])
    target_directories = record["target_directories"]
    if target_directories is not None:
        _validate_directory_records(target_directories)
        if [item["path"] for item in target_directories] != _expected_directories(target_records):
            raise DeploymentError("Deployment journal target directories do not match the manifest.")
        if any(item["mode"] != 0o755 for item in target_directories):
            raise DeploymentError("Deployment journal target directory mode is invalid.")
    elif record["phase"] not in ("prepared", "rollback_intent", "rollback_failed"):
        raise DeploymentError("Deployment journal lacks approved target directories.")
    for field in ("root_owner", "root_group", "root_mode", "device_id"):
        if type(old_snapshot[field]) is not int or old_snapshot[field] < 0:
            raise DeploymentError("Deployment journal prior-runtime metadata is invalid.")
    for field in ("active_path", "stage_path", "rollback_path", "failed_candidate_path"):
        value = record[field]
        if not isinstance(value, str) or not Path(value).is_absolute() or Path(value) == Path("/"):
            raise DeploymentError(f"Deployment journal {field} is invalid.")
    app_root = wp_root.parent
    expected_paths = {
        "active_path": wp_root / "wp-content/plugins/voelgoed-platform-core",
        "stage_path": app_root / f".vgnova-platform-core-stage-{record['deployment_id']}",
        "rollback_path": app_root / f".vgnova-platform-core-rollback-{record['deployment_id']}",
        "failed_candidate_path": app_root / f".vgnova-platform-core-failed-{record['deployment_id']}",
    }
    for field, expected in expected_paths.items():
        if Path(record[field]) != expected:
            raise DeploymentError(f"Deployment journal {field} escapes its registered path.")
    if record["phase"] not in DEPLOYMENT_PHASES:
        raise DeploymentError("Deployment journal phase is unknown.")
    history = record["phase_history"]
    if not isinstance(history, list) or not history:
        raise DeploymentError("Deployment journal phase history is invalid.")
    for item in history:
        if not isinstance(item, dict) or set(item) != {"phase", "timestamp"}:
            raise DeploymentError("Deployment journal phase history entry is invalid.")
        if item["phase"] not in DEPLOYMENT_PHASES or not isinstance(item["timestamp"], str):
            raise DeploymentError("Deployment journal phase history entry is invalid.")
    if history[0]["phase"] != "prepared":
        raise DeploymentError("Deployment journal phase history must begin at prepared.")
    for previous, current in zip(history, history[1:]):
        if current["phase"] not in PHASE_TRANSITIONS[previous["phase"]]:
            raise DeploymentError("Deployment journal phase history contains an invalid transition.")
    if history[-1]["phase"] != record["phase"]:
        raise DeploymentError("Deployment journal phase history does not end at its current phase.")
    timestamps = record["timestamps"]
    if not isinstance(timestamps, dict) or set(timestamps) != {"created_at", "updated_at"}:
        raise DeploymentError("Deployment journal timestamps are invalid.")
    if not all(isinstance(value, str) and value for value in timestamps.values()):
        raise DeploymentError("Deployment journal timestamps are invalid.")
    if not isinstance(record["recovery_status"], str):
        raise DeploymentError("Deployment journal recovery status is invalid.")
    for field in ("health_verification_result", "runtime_parity_result"):
        if record[field] is not None and not isinstance(record[field], dict):
            raise DeploymentError(f"Deployment journal {field} is invalid.")
    provenance = record["provenance"]
    if not isinstance(provenance, dict) or set(provenance) != {
        "deployer_commit",
        "deployer_sha256",
        "deployer_module_sha256",
        "verifier_sha256",
    }:
        raise DeploymentError("Deployment journal provenance is invalid.")
    if not isinstance(provenance["deployer_commit"], str) or re.fullmatch(r"[a-f0-9]{40}", provenance["deployer_commit"]) is None:
        raise DeploymentError("Deployment journal deployer commit is invalid.")
    for field in ("deployer_sha256", "deployer_module_sha256", "verifier_sha256"):
        if not isinstance(provenance[field], str) or re.fullmatch(r"[a-f0-9]{64}", provenance[field]) is None:
            raise DeploymentError(f"Deployment journal {field} is invalid.")

    def reject_credentials(value: Any) -> None:
        if isinstance(value, dict):
            for key, child in value.items():
                if re.search(r"password|secret|credential|token", str(key), re.IGNORECASE):
                    raise DeploymentError("Deployment journal must not contain credential fields.")
                reject_credentials(child)
        elif isinstance(value, list):
            for child in value:
                reject_credentials(child)

    reject_credentials(record)


def _fsync_directory(path: Path) -> None:
    descriptor = os.open(path, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def write_journal(path: Path, record: dict[str, Any]) -> None:
    """Durably replace a private journal file."""
    path = Path(path)
    validate_journal(record)
    ensure_state_root(path.parent)
    if os.path.lexists(path):
        info = os.lstat(path)
        if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
            raise DeploymentError("Deployment journal path must be a regular file.")
        if info.st_uid != os.getuid() or stat.S_IMODE(info.st_mode) != 0o600:
            raise DeploymentError("Deployment journal must be owned by this user with mode 0600.")
    temporary = path.with_name(f".{path.name}.tmp-{uuid.uuid4().hex}")
    encoded = (json.dumps(record, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_CLOEXEC", 0) | getattr(os, "O_NOFOLLOW", 0)
    descriptor = os.open(temporary, flags, 0o600)
    try:
        os.fchmod(descriptor, 0o600)
        view = memoryview(encoded)
        while view:
            written = os.write(descriptor, view)
            if written <= 0:
                raise DeploymentError("Deployment journal write stopped before completion.")
            view = view[written:]
        os.fsync(descriptor)
    except BaseException:
        os.close(descriptor)
        try:
            os.unlink(temporary)
        except FileNotFoundError:
            pass
        raise
    else:
        os.close(descriptor)
    try:
        os.replace(temporary, path)
        _fsync_directory(path.parent)
    except OSError as error:
        try:
            os.unlink(temporary)
        except FileNotFoundError:
            pass
        raise DeploymentError("Deployment journal could not be committed durably.") from error


def utc_timestamp() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")


def advance_journal(
    record: dict[str, Any],
    phase: str,
    *,
    now: Any = utc_timestamp,
) -> dict[str, Any]:
    """Apply one allowed phase transition and append its timestamp."""
    validate_journal(record)
    if phase not in PHASE_TRANSITIONS[record["phase"]]:
        raise DeploymentError(f"Deployment phase transition {record['phase']} -> {phase} is not allowed.")
    timestamp = now()
    record["phase"] = phase
    record["phase_history"].append({"phase": phase, "timestamp": timestamp})
    record["timestamps"]["updated_at"] = timestamp
    if phase == "rollback_intent":
        record["recovery_status"] = "rollback_pending"
    elif phase == "rolled_back":
        record["recovery_status"] = "rolled_back"
    elif phase == "rollback_failed":
        record["recovery_status"] = "rollback_failed"
    elif phase == "finalized":
        record["recovery_status"] = "finalized"
    validate_journal(record)
    return record


def read_journal(path: Path) -> dict[str, Any]:
    """Read and validate a private, regular journal without following links."""
    path = Path(path)
    ensure_state_root(path.parent)
    try:
        info = os.lstat(path)
    except OSError as error:
        raise DeploymentError("Deployment journal is missing or unreadable.") from error
    if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
        raise DeploymentError("Deployment journal must be a regular file.")
    if info.st_uid != os.getuid() or stat.S_IMODE(info.st_mode) != 0o600:
        raise DeploymentError("Deployment journal must be owned by this user with mode 0600.")
    flags = os.O_RDONLY | getattr(os, "O_CLOEXEC", 0) | getattr(os, "O_NOFOLLOW", 0)
    try:
        descriptor = os.open(path, flags)
        with os.fdopen(descriptor, "rb") as stream:
            raw = stream.read()
    except OSError as error:
        raise DeploymentError("Deployment journal could not be read safely.") from error
    try:
        record = json.loads(raw.decode("utf-8", "strict"), object_pairs_hook=_reject_duplicate_json_keys)
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise DeploymentError("Deployment journal is not valid canonical JSON.") from error
    validate_journal(record)
    return record


def _renameat2(source: bytes, destination: bytes, flags: int) -> None:
    library = ctypes.CDLL(None, use_errno=True)
    function = getattr(library, "renameat2", None)
    if function is None:
        raise OSError(errno.ENOSYS, "renameat2 is unavailable")
    function.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint]
    function.restype = ctypes.c_int
    result = function(-100, source, -100, destination, flags)
    if result != 0:
        error_number = ctypes.get_errno()
        raise OSError(error_number, os.strerror(error_number))


def _require_directory(path: Path) -> os.stat_result:
    try:
        info = os.lstat(path)
    except OSError as error:
        raise DeploymentError("Atomic directory operation path is missing or unreadable.") from error
    if not stat.S_ISDIR(info.st_mode) or stat.S_ISLNK(info.st_mode):
        raise DeploymentError("Atomic directory operation requires real directories.")
    return info


def rename_exchange(
    left: Path,
    right: Path,
    *,
    syscall: Any = None,
) -> None:
    """Exchange two existing directories with RENAME_EXCHANGE only."""
    left = Path(left)
    right = Path(right)
    left_stat = _require_directory(left)
    right_stat = _require_directory(right)
    if left_stat.st_dev != right_stat.st_dev:
        raise DeploymentError("Atomic directory exchange cannot cross filesystems.")
    operation = _renameat2 if syscall is None else syscall
    try:
        operation(os.fsencode(left), os.fsencode(right), 2)
        _fsync_directory(left.parent)
        if right.parent != left.parent:
            _fsync_directory(right.parent)
    except OSError as error:
        raise DeploymentError(f"Atomic directory exchange failed with errno {error.errno}.") from error


def rename_noreplace(
    source: Path,
    destination: Path,
    *,
    syscall: Any = None,
) -> None:
    """Rename a managed directory without replacing any destination."""
    source = Path(source)
    destination = Path(destination)
    source_stat = _require_directory(source)
    if os.path.lexists(destination):
        raise DeploymentError("Managed destination already exists and will not be replaced.")
    destination_parent = _require_directory(destination.parent)
    if source_stat.st_dev != destination_parent.st_dev:
        raise DeploymentError("Managed directory rename cannot cross filesystems.")
    operation = _renameat2 if syscall is None else syscall
    try:
        operation(os.fsencode(source), os.fsencode(destination), 1)
        _fsync_directory(source.parent)
        if destination.parent != source.parent:
            _fsync_directory(destination.parent)
    except OSError as error:
        raise DeploymentError(f"No-replace managed directory rename failed with errno {error.errno}.") from error


def rename_file_noreplace(
    source: Path,
    destination: Path,
    *,
    syscall: Any = None,
) -> None:
    """Move one owned regular file without replacing an existing record."""
    source = Path(source)
    destination = Path(destination)
    try:
        source_stat = os.lstat(source)
    except OSError as error:
        raise DeploymentError("Journal source is missing or unreadable.") from error
    if not stat.S_ISREG(source_stat.st_mode) or stat.S_ISLNK(source_stat.st_mode) or source_stat.st_nlink != 1:
        raise DeploymentError("Journal source must be a regular unlinked file.")
    if os.path.lexists(destination):
        raise DeploymentError("Journal history destination already exists and will not be replaced.")
    destination_parent = _require_directory(destination.parent)
    if source_stat.st_dev != destination_parent.st_dev:
        raise DeploymentError("Journal history move cannot cross filesystems.")
    operation = _renameat2 if syscall is None else syscall
    try:
        operation(os.fsencode(source), os.fsencode(destination), 1)
        _fsync_directory(source.parent)
        if destination.parent != source.parent:
            _fsync_directory(destination.parent)
    except OSError as error:
        raise DeploymentError(f"No-replace journal history move failed with errno {error.errno}.") from error


def _expected_directories(records: list[dict[str, Any]]) -> list[str]:
    directories: set[str] = set()
    for record in records:
        path = safe_relative_path(record["path"])
        for parent in path.parents:
            if parent.as_posix() != ".":
                directories.add(parent.as_posix())
    return sorted(directories, key=lambda item: item.encode("utf-8"))


def compare_tree_to_manifest(
    fingerprint: dict[str, Any],
    records: list[dict[str, Any]],
    expected_directory_records: list[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    expected = {item["path"]: item for item in records}
    actual = {item["path"]: item for item in fingerprint["files"]}
    expected_paths = set(expected)
    actual_paths = set(actual)
    missing = sorted(expected_paths - actual_paths, key=lambda item: item.encode("utf-8"))
    extra = sorted(actual_paths - expected_paths, key=lambda item: item.encode("utf-8"))
    mismatched: list[str] = []
    mode_mismatches: list[str] = []
    for path in sorted(expected_paths & actual_paths, key=lambda item: item.encode("utf-8")):
        if expected[path]["size"] != actual[path]["size"] or expected[path]["sha256"] != actual[path]["sha256"]:
            mismatched.append(path)
        if expected[path]["mode"] != actual[path]["mode"]:
            mode_mismatches.append(path)
    expected_directories = _expected_directories(records)
    actual_directories = [item["path"] for item in fingerprint["directories"]]
    directory_metadata_mismatch = (expected_directory_records is not None and
                                   fingerprint["directories"] != expected_directory_records)
    missing_directories = sorted(set(expected_directories) - set(actual_directories), key=lambda item: item.encode("utf-8"))
    extra_directories = sorted(set(actual_directories) - set(expected_directories), key=lambda item: item.encode("utf-8"))
    expected_tree_hash = canonical_tree_sha256(records)
    ok = not (missing or extra or mismatched or mode_mismatches or missing_directories or extra_directories or directory_metadata_mismatch)
    ok = ok and fingerprint["file_count"] == len(records) and fingerprint["tree_sha256"] == expected_tree_hash
    return {
        "ok": ok,
        "file_count": fingerprint["file_count"],
        "tree_sha256": fingerprint["tree_sha256"],
        "missing": missing,
        "extra": extra,
        "mismatched": mismatched,
        "mode_mismatches": mode_mismatches,
        "missing_directories": missing_directories,
        "extra_directories": extra_directories,
        "directory_metadata_mismatch": directory_metadata_mismatch,
        "symlinks": 0,
        "unsupported": 0,
    }


def _matches_old_snapshot(actual: dict[str, Any], expected: dict[str, Any]) -> bool:
    return all(
        actual[field] == expected[field]
        for field in (
            "file_count",
            "files",
            "directories",
            "tree_sha256",
            "root_owner",
            "root_group",
            "root_mode",
            "device_id",
        )
    )


def _matches_target_tree(actual: dict[str, Any], journal: dict[str, Any]) -> bool:
    return (
        actual["files"] == journal["target_manifest_records"]
        and journal["target_directories"] is not None
        and actual["directories"] == journal["target_directories"]
        and actual["tree_sha256"] == journal["target_tree_sha256"]
    )


def _classify_tree(path: Path, journal: dict[str, Any]) -> str:
    if not os.path.lexists(path):
        return "missing"
    fingerprint = fingerprint_tree(path)
    if _matches_old_snapshot(fingerprint, journal["old_runtime_snapshot"]):
        return "old"
    if _matches_target_tree(fingerprint, journal):
        return "target"
    return "unknown"


def ensure_same_filesystem(active: Path, staged: Path) -> None:
    active_info = _require_directory(Path(active))
    stage_info = _require_directory(Path(staged))
    if active_info.st_dev != stage_info.st_dev:
        raise DeploymentError("Active and staged Platform Core trees are on different filesystems.")


EXPECTED_TARGET = {
    "id": TARGET_ID,
    "home": "http://localhost:10039",
    "site": "http://localhost:10039",
    "database_name": "local",
    "database_host": "localhost",
    "platform_core_entrypoint": "wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php",
}
REGISTERED_WORDPRESS_ROOT = Path(
    "/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public"
)


def validate_runtime_target(
    target: dict[str, Any],
    target_id: str,
    wp_root: Path,
    registered_wp_root: Path = REGISTERED_WORDPRESS_ROOT,
    *,
    require_active: bool = True,
) -> tuple[Path, Path]:
    """Bind the caller to the one registered VGNova runtime and plugin path."""
    if target_id != TARGET_ID or target != EXPECTED_TARGET:
        raise DeploymentError("Runtime target is not the registered VGNova target.")
    wp_root = Path(wp_root)
    registered_wp_root = Path(registered_wp_root)
    if not wp_root.is_absolute() or wp_root == Path("/") or wp_root != registered_wp_root:
        raise DeploymentError("WordPress root does not match the registered VGNova root.")
    try:
        root_info = os.lstat(wp_root)
    except OSError as error:
        raise DeploymentError("Registered WordPress root is missing or unreadable.") from error
    if not stat.S_ISDIR(root_info.st_mode) or stat.S_ISLNK(root_info.st_mode):
        raise DeploymentError("Registered WordPress root must be a real directory.")
    if wp_root.resolve(strict=True) != registered_wp_root:
        raise DeploymentError("WordPress root resolves outside the registered VGNova root.")

    entrypoint_relative = safe_relative_path(target["platform_core_entrypoint"])
    entrypoint = wp_root.joinpath(*entrypoint_relative.parts)
    plugin_root = entrypoint.parent
    if plugin_root != wp_root / "wp-content/plugins/voelgoed-platform-core":
        raise DeploymentError("Platform Core path differs from the registered plugin location.")
    for ancestor in (wp_root / "wp-content", wp_root / "wp-content/plugins"):
        try:
            ancestor_info = os.lstat(ancestor)
        except OSError as error:
            raise DeploymentError("Platform Core parent directory is missing or unreadable.") from error
        if not stat.S_ISDIR(ancestor_info.st_mode) or stat.S_ISLNK(ancestor_info.st_mode):
            raise DeploymentError("Platform Core parent directories must not be symlinks.")
        if ancestor.resolve(strict=True) != ancestor:
            raise DeploymentError("Platform Core parent directory resolves outside the WordPress root.")
    if not require_active:
        return plugin_root, entrypoint
    _require_directory(plugin_root)
    try:
        entrypoint_info = os.lstat(entrypoint)
    except OSError as error:
        raise DeploymentError("Active Platform Core entrypoint is missing or unreadable.") from error
    if not stat.S_ISREG(entrypoint_info.st_mode) or stat.S_ISLNK(entrypoint_info.st_mode):
        raise DeploymentError("Active Platform Core entrypoint must be a regular file.")
    if entrypoint.resolve(strict=True) != plugin_root.resolve(strict=True) / "voelgoed-platform-core.php":
        raise DeploymentError("Active Platform Core entrypoint resolves outside its plugin tree.")
    return plugin_root, entrypoint


class DeploymentHooks:
    """Inject process and failure boundaries for fixture tests."""

    def __init__(
        self,
        *,
        verify_artifact: Any,
        verify_session: Any,
        verify_health: Any,
        verify_http_health: Any,
        provenance: Any,
        deployment_id: Any = lambda: uuid.uuid4().hex,
        now: Any = utc_timestamp,
        exchange: Any = rename_exchange,
        rename_directory: Any = rename_noreplace,
        rename_file: Any = rename_file_noreplace,
    ) -> None:
        self.verify_artifact = verify_artifact
        self.verify_session = verify_session
        self.verify_health = verify_health
        self.verify_http_health = verify_http_health
        self.provenance = provenance
        self.deployment_id = deployment_id
        self.now = now
        self.exchange = exchange
        self.rename_directory = rename_directory
        self.rename_file = rename_file


def _create_journal(
    *,
    deployment_id: str,
    target_id: str,
    wp_root: Path,
    source_commit: str,
    manifest: dict[str, Any],
    manifest_sha256: str,
    old_snapshot: dict[str, Any],
    provenance: dict[str, str],
) -> dict[str, Any]:
    timestamp = utc_timestamp()
    app_root = wp_root.parent
    return {
        "schema_version": 1,
        "deployment_id": deployment_id,
        "target": target_id,
        "wp_root": str(wp_root),
        "source_commit": source_commit,
        "archive_sha256": manifest["archive_sha256"],
        "manifest_sha256": manifest_sha256,
        "target_tree_sha256": manifest["tree_sha256"],
        "target_manifest_records": manifest["files"],
        "target_directories": None,
        "old_runtime_tree_sha256": old_snapshot["tree_sha256"],
        "old_runtime_snapshot": old_snapshot,
        "active_path": str(wp_root / "wp-content/plugins/voelgoed-platform-core"),
        "stage_path": str(app_root / f".vgnova-platform-core-stage-{deployment_id}"),
        "rollback_path": str(app_root / f".vgnova-platform-core-rollback-{deployment_id}"),
        "failed_candidate_path": str(app_root / f".vgnova-platform-core-failed-{deployment_id}"),
        "phase": "prepared",
        "phase_history": [{"phase": "prepared", "timestamp": timestamp}],
        "timestamps": {"created_at": timestamp, "updated_at": timestamp},
        "recovery_status": "none",
        "health_verification_result": None,
        "runtime_parity_result": None,
        "provenance": provenance,
    }


def _save_phase(state_root: Path, journal: dict[str, Any], phase: str, *, hooks: DeploymentHooks) -> None:
    advance_journal(journal, phase, now=hooks.now)
    write_journal(state_root / "in-progress.json", journal)


def _archive_journal(state_root: Path, journal: dict[str, Any], hooks: DeploymentHooks) -> Path:
    history = state_root / "history"
    if os.path.lexists(history):
        ensure_state_root(history)
    else:
        history.mkdir(mode=0o700)
        _fsync_directory(state_root)
    destination = history / f"{journal['deployment_id']}.json"
    hooks.rename_file(state_root / "in-progress.json", destination)
    return destination


def _record_rollback_failure(
    state_root: Path,
    journal: dict[str, Any],
    hooks: DeploymentHooks,
) -> None:
    try:
        if journal["phase"] != "rollback_failed":
            advance_journal(journal, "rollback_failed", now=hooks.now)
        write_journal(state_root / "in-progress.json", journal)
    except BaseException:
        pass


def _require_hook_success(result: Any, label: str) -> dict[str, Any]:
    if not isinstance(result, dict) or result.get("ok") is not True:
        raise DeploymentError(f"{label} failed.")
    return result


def _verify_session(hooks: DeploymentHooks, target_id: str, wp_root: Path) -> dict[str, Any]:
    return _require_hook_success(hooks.verify_session(target_id, wp_root), "PE-007 session verification")


def _tree_classification(path: Path, journal: dict[str, Any]) -> str:
    try:
        return _classify_tree(path, journal)
    except DeploymentError:
        return "unknown"


def _perform_rollback(
    state_root: Path,
    journal: dict[str, Any],
    hooks: DeploymentHooks,
) -> Path:
    """Restore and prove the previous tree, retaining the candidate and journal."""
    _verify_session(hooks, journal["target"], Path(journal["wp_root"]))
    previous_phase = journal["phase"]
    try:
        if journal["phase"] != "rollback_intent":
            advance_journal(journal, "rollback_intent", now=hooks.now)
            write_journal(state_root / "in-progress.json", journal)
    except BaseException:
        # A journal write can fail after the exchange. The filesystem hashes
        # below decide whether rollback can proceed safely.
        pass

    try:
        paths = {
            "active": Path(journal["active_path"]),
            "stage": Path(journal["stage_path"]),
            "rollback": Path(journal["rollback_path"]),
            "failed": Path(journal["failed_candidate_path"]),
        }
        states = {name: _tree_classification(path, journal) for name, path in paths.items()}
        if "unknown" in states.values():
            raise DeploymentError("Rollback topology contains an unknown tree.")
        old_sources = [name for name in ("stage", "rollback") if states[name] == "old"]
        if states["active"] == "target":
            if len(old_sources) != 1 or states["failed"] != "missing":
                raise DeploymentError("Rollback topology does not identify one retained prior tree.")
            other_managed = "rollback" if old_sources[0] == "stage" else "stage"
            if states[other_managed] != "missing":
                raise DeploymentError("Rollback topology has another unexplained managed tree.")
            _require_directory(paths["active"])
            hooks.exchange(paths["active"], paths[old_sources[0]])
            states = {name: _tree_classification(path, journal) for name, path in paths.items()}

        if "unknown" in states.values():
            raise DeploymentError("Rollback topology contains an unknown tree.")
        if states["active"] != "old" or not _matches_old_snapshot(
            fingerprint_tree(paths["active"]), journal["old_runtime_snapshot"]
        ):
            raise DeploymentError("Previous Platform Core tree could not be restored exactly.")
        if any(states[name] == "old" for name in ("stage", "rollback")):
            raise DeploymentError("Rollback found an unexplained duplicate of the prior tree.")
        candidate_paths = [
            name for name in ("stage", "rollback", "failed") if states[name] == "target"
        ]
        if len(candidate_paths) > 1:
            raise DeploymentError("Rollback found duplicate candidate trees.")
        if not candidate_paths and previous_phase != "prepared":
            raise DeploymentError("Rollback cannot account for the candidate tree.")
        if candidate_paths and candidate_paths[0] in ("stage", "rollback"):
            hooks.rename_directory(paths[candidate_paths[0]], paths["failed"])

        if journal["phase"] != "rolled_back":
            advance_journal(journal, "rolled_back", now=hooks.now)
            write_journal(state_root / "in-progress.json", journal)
        return _archive_journal(state_root, journal, hooks)
    except BaseException as error:
        _record_rollback_failure(state_root, journal, hooks)
        if isinstance(error, DeploymentError):
            raise DeploymentError("Automatic rollback could not be proven; the journal and all trees were retained.") from error
        raise DeploymentError("Automatic rollback failed; the journal and all trees were retained.") from error


def _load_manifest_after_verification(
    artifact_directory: Path,
    source_commit: str,
    expected_manifest_sha256: str,
    verify_artifact: Any,
) -> tuple[dict[str, Any], Path, list[dict[str, Any]]]:
    if re.fullmatch(r"[a-f0-9]{40}", source_commit) is None:
        raise DeploymentError("Artifact source must be a full lowercase commit SHA.")
    if re.fullmatch(r"[a-f0-9]{64}", expected_manifest_sha256) is None:
        raise DeploymentError("Expected manifest SHA-256 must be a lowercase digest.")
    artifact_directory = Path(artifact_directory)
    directory_info = os.lstat(artifact_directory)
    if not stat.S_ISDIR(directory_info.st_mode) or stat.S_ISLNK(directory_info.st_mode):
        raise DeploymentError("Artifact directory must be a real directory.")
    verify_artifact(artifact_directory, source_commit)
    archive_path = artifact_directory / "voelgoed-platform-core.tar.gz"
    manifest_path = artifact_directory / "voelgoed-platform-core.manifest.json"
    for path in (archive_path, manifest_path):
        info = os.lstat(path)
        if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
            raise DeploymentError("Verified artifact output must contain regular files only.")
    raw_manifest = manifest_path.read_bytes()
    if sha256_bytes(raw_manifest) != expected_manifest_sha256:
        raise DeploymentError("Supplied manifest SHA-256 does not match the verified manifest bytes.")
    try:
        manifest = json.loads(raw_manifest.decode("utf-8", "strict"), object_pairs_hook=_reject_duplicate_json_keys)
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise DeploymentError("Verified artifact manifest cannot be parsed.") from error
    if not isinstance(manifest, dict) or manifest.get("source_commit") != source_commit or manifest.get("schema_version") != 3:
        raise DeploymentError("Verified artifact manifest is bound to a different source commit.")
    canonical = (json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode("utf-8")
    if canonical != raw_manifest:
        raise DeploymentError("Verified manifest bytes are not canonical JSON.")
    records = validate_manifest_records(manifest)
    archive_digest = manifest.get("archive_sha256")
    if not isinstance(archive_digest, str) or re.fullmatch(r"[a-f0-9]{64}", archive_digest) is None:
        raise DeploymentError("Verified artifact archive SHA-256 is invalid.")
    if sha256_bytes(archive_path.read_bytes()) != archive_digest:
        raise DeploymentError("Artifact archive changed after exact-output verification.")
    if set(os.listdir(artifact_directory)) != {archive_path.name, manifest_path.name}:
        raise DeploymentError("Artifact directory contains unexpected output files.")
    return manifest, archive_path, records


def _validate_provenance(provenance: Any) -> dict[str, str]:
    if not isinstance(provenance, dict) or set(provenance) != {
        "deployer_commit",
        "deployer_sha256",
        "deployer_module_sha256",
        "verifier_sha256",
    }:
        raise DeploymentError("Deployment source provenance is incomplete.")
    if not isinstance(provenance["deployer_commit"], str) or re.fullmatch(r"[a-f0-9]{40}", provenance["deployer_commit"]) is None:
        raise DeploymentError("Deployment source commit provenance is invalid.")
    for field in ("deployer_sha256", "deployer_module_sha256", "verifier_sha256"):
        if not isinstance(provenance[field], str) or re.fullmatch(r"[a-f0-9]{64}", provenance[field]) is None:
            raise DeploymentError(f"Deployment source {field} provenance is invalid.")
    return dict(provenance)


def committed_source_provenance(
    repo_root: Path,
    *,
    critical_paths: tuple[str, ...] = CRITICAL_SOURCE_PATHS,
) -> dict[str, str]:
    """Require deployment-critical source files to match the executing commit."""
    repo_root = Path(repo_root).resolve(strict=True)

    def git_bytes(arguments: list[str]) -> bytes:
        result = subprocess.run(
            ["git", "-C", str(repo_root), *arguments],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )
        if result.returncode != 0:
            raise DeploymentError("Git could not prove deployment source provenance.")
        return result.stdout

    commit_bytes = git_bytes(["rev-parse", "--verify", "HEAD^{commit}"])
    try:
        commit = commit_bytes.decode("ascii", "strict").strip()
    except UnicodeDecodeError as error:
        raise DeploymentError("Executing repository commit is invalid.") from error
    if re.fullmatch(r"[a-f0-9]{40}", commit) is None:
        raise DeploymentError("Executing repository commit is not a full SHA-1 commit ID.")

    current: dict[str, bytes] = {}
    for relative in critical_paths:
        safe = safe_relative_path(relative)
        path = repo_root.joinpath(*safe.parts)
        try:
            info = os.lstat(path)
        except OSError as error:
            raise DeploymentError("A deployment-critical source file is missing.") from error
        if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
            raise DeploymentError("A deployment-critical source file is not a regular file.")
        git_bytes(["ls-files", "--error-unmatch", "--", relative])
        committed = git_bytes(["show", f"{commit}:{relative}"])
        working = path.read_bytes()
        if working != committed:
            raise DeploymentError("A deployment-critical source file differs from its committed bytes.")
        current[relative] = working

    cli_path = "site-code/bin/deploy-platform-core.py"
    module_path = "site-code/runtime-tools/platform_core_deployment.py"
    verifier_path = "site-code/bin/check-platform-core-build.py"
    return {
        "deployer_commit": commit,
        "deployer_sha256": sha256_bytes(current[cli_path]),
        "deployer_module_sha256": sha256_bytes(current[module_path]),
        "verifier_sha256": sha256_bytes(current[verifier_path]),
    }


def run_canonical_verifier(repo_root: Path, artifact_directory: Path, source_commit: str) -> None:
    """Run the existing exact-output verifier for the exact full commit."""
    repo_root = Path(repo_root).resolve(strict=True)
    if re.fullmatch(r"[a-f0-9]{40}", source_commit) is None:
        raise DeploymentError("Artifact source must be a full lowercase commit SHA.")
    verifier = repo_root / "site-code/bin/check-platform-core-build.py"
    artifact_directory = Path(artifact_directory).resolve(strict=True)
    command = [
        sys.executable,
        str(verifier),
        "--ref",
        source_commit,
        "--verify-output",
        str(artifact_directory),
    ]
    try:
        result = subprocess.run(
            command,
            cwd=repo_root,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
            timeout=600,
        )
    except (OSError, subprocess.TimeoutExpired) as error:
        raise DeploymentError("Canonical artifact verification could not complete.") from error
    if result.returncode != 0:
        raise DeploymentError("Canonical exact-output verification rejected the artifact.")


def run_php_health_helper(
    repo_root: Path,
    wp_root: Path,
    target_id: str,
    *,
    mode: str,
) -> dict[str, Any]:
    """Invoke the source helper with the PE-007 host-lock descriptor and proof."""
    if target_id != TARGET_ID or mode not in ("session", "health"):
        raise DeploymentError("PHP health helper target or mode is unsupported.")
    try:
        os.fstat(3)
    except OSError as error:
        raise DeploymentError("PE-007 host-lock descriptor is unavailable.") from error
    helper = Path(repo_root).resolve(strict=True) / "site-code/runtime-tools/platform-core-deployment-health.php"
    wp_root = Path(wp_root).resolve(strict=True)
    child_environment = {
        key: os.environ[key]
        for key in ("PATH", "HOME", "XDG_STATE_HOME", "LANG", "LC_ALL", "TZ", "PHPRC", "PHP_INI_SCAN_DIR")
        if key in os.environ
    }
    for key in MUTATION_SESSION_ENVIRONMENT:
        if key in os.environ:
            child_environment[key] = os.environ[key]
    child_environment["VGNOVA_MUTATION_WP_ROOT"] = str(wp_root)
    command = [
        "php",
        "-d",
        "display_errors=1",
        "-d",
        "display_startup_errors=1",
        "-d",
        "log_errors=0",
        "-d",
        "error_reporting=-1",
        str(helper),
        mode,
        target_id,
        str(wp_root),
    ]
    try:
        result = subprocess.run(
            command,
            cwd=Path(repo_root),
            env=child_environment,
            pass_fds=(3,),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            encoding="utf-8",
            errors="strict",
            check=False,
            timeout=120,
        )
    except (OSError, subprocess.TimeoutExpired, UnicodeError) as error:
        raise DeploymentError("WordPress health helper could not complete cleanly.") from error
    if result.returncode != 0 or result.stderr != "":
        raise DeploymentError("WordPress health helper returned an error or unexpected diagnostic output.")
    try:
        parsed = json.loads(result.stdout, object_pairs_hook=_reject_duplicate_json_keys)
    except (json.JSONDecodeError, DeploymentError) as error:
        raise DeploymentError("WordPress health helper output is not one JSON result.") from error
    if not isinstance(parsed, dict):
        raise DeploymentError("WordPress health helper result must be a JSON object.")
    canonical = json.dumps(parsed, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n"
    if result.stdout != canonical:
        raise DeploymentError("WordPress health helper emitted unexpected output.")
    return parsed


class _NoRedirectHandler(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, _request, _response, _code, _message, _headers, _new_url):
        return None


def verify_local_http(
    url: str,
    site_name: str | None,
    *,
    opener: Any = None,
    timeout: float = 15.0,
) -> dict[str, Any]:
    """Require a direct HTML response from the registered local VGNova URL."""
    expected = "http://localhost:10039/"
    parsed = urllib.parse.urlsplit(url)
    if (
        url != expected
        or parsed.scheme != "http"
        or parsed.hostname != "localhost"
        or parsed.port != 10039
        or parsed.username is not None
        or parsed.password is not None
        or parsed.query
        or parsed.fragment
    ):
        raise DeploymentError("HTTP health URL differs from the registered local home URL.")
    if not isinstance(site_name, str) or not site_name.strip():
        raise DeploymentError("WordPress site name is unavailable for HTTP health verification.")
    if opener is None:
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), _NoRedirectHandler())
    request = urllib.request.Request(
        expected,
        headers={"Accept": "text/html", "User-Agent": "VGNova-Platform-Core-Health/1.0"},
        method="GET",
    )
    try:
        with opener.open(request, timeout=timeout) as response:
            status = getattr(response, "status", None)
            if status is None:
                status = response.getcode()
            final_url = response.geturl()
            content_type = response.headers.get("Content-Type", "")
            body = response.read(1024 * 1024 + 1)
    except urllib.error.HTTPError as error:
        error.close()
        raise DeploymentError("Local VGNova HTTP health request failed without following redirects.") from error
    except (OSError, urllib.error.URLError) as error:
        raise DeploymentError("Local VGNova HTTP health request failed without following redirects.") from error
    if status < 200 or status >= 300 or final_url != expected:
        raise DeploymentError("Local VGNova HTTP health returned an unexpected status or URL.")
    if not content_type.lower().startswith("text/html") or len(body) > 1024 * 1024:
        raise DeploymentError("Local VGNova HTTP health did not return bounded HTML.")
    document = body.decode("utf-8", "replace")
    if "<html" not in document.lower() or site_name.casefold() not in document.casefold():
        raise DeploymentError("Local HTTP response does not contain the expected WordPress site identity.")
    return {"ok": True, "status": status, "url": expected, "content_type": content_type}


def deploy_artifact(
    *,
    repo_root: Path,
    target: dict[str, Any],
    target_id: str,
    wp_root: Path,
    registered_wp_root: Path,
    artifact_directory: Path,
    source_commit: str,
    expected_manifest_sha256: str,
    hooks: DeploymentHooks,
) -> dict[str, Any]:
    """Deploy one verified artifact using PE-007 proof and atomic exchange."""
    active_path, _entrypoint = validate_runtime_target(target, target_id, wp_root, registered_wp_root)
    provenance = _validate_provenance(hooks.provenance())
    manifest, archive_path, records = _load_manifest_after_verification(
        artifact_directory,
        source_commit,
        expected_manifest_sha256,
        hooks.verify_artifact,
    )
    old_snapshot = fingerprint_tree(active_path)
    app_root = Path(wp_root).parent
    if os.stat(app_root).st_dev != old_snapshot["device_id"]:
        raise DeploymentError("Deployment state and active plugin must share a filesystem.")
    _verify_session(hooks, target_id, Path(wp_root))

    state_root = ensure_state_root(app_root / ".vgnova-platform-core-deployments", create=True)
    in_progress = state_root / "in-progress.json"
    if os.path.lexists(in_progress):
        read_journal(in_progress)
        raise DeploymentError("An unresolved Platform Core deployment journal blocks this deployment.")

    # A verified identical tree needs no exchange. Do not create a journal
    # whose old and target identities would be indistinguishable in recovery.
    expected_directories = [
        {"path": path, "mode": 0o755, "owner": os.geteuid(), "group": os.getegid()}
        for path in _expected_directories(records)
    ]
    if compare_tree_to_manifest(old_snapshot, records, expected_directories)["ok"]:
        php_health = _require_hook_success(hooks.verify_health(target_id, Path(wp_root)),
                                           "WordPress Platform Core bootstrap health")
        http_health = _require_hook_success(hooks.verify_http_health(target["home"] + "/", php_health.get("site_name")),
                                            "Local VGNova HTTP health")
        current = fingerprint_tree(active_path)
        if not _matches_old_snapshot(current, old_snapshot) or not compare_tree_to_manifest(current, records, expected_directories)["ok"]:
            raise DeploymentError("Unchanged Platform Core runtime changed during health verification.")
        return {"phase": "unchanged", "target": target_id, "source_commit": source_commit,
                "manifest_sha256": expected_manifest_sha256, "target_tree_sha256": manifest["tree_sha256"],
                "provenance": provenance, "health_verification_result": {"php": php_health, "http": http_health}}

    deployment_id = hooks.deployment_id()
    if not isinstance(deployment_id, str) or re.fullmatch(r"[a-f0-9]{32}", deployment_id) is None:
        raise DeploymentError("Deployment ID generator returned an invalid ID.")
    deployment_paths = [
        app_root / f".vgnova-platform-core-stage-{deployment_id}",
        app_root / f".vgnova-platform-core-rollback-{deployment_id}",
        app_root / f".vgnova-platform-core-failed-{deployment_id}",
    ]
    if any(os.path.lexists(path) for path in deployment_paths):
        raise DeploymentError("Deployment ID collides with an existing managed path.")

    journal = _create_journal(
        deployment_id=deployment_id,
        target_id=target_id,
        wp_root=Path(wp_root),
        source_commit=source_commit,
        manifest=manifest,
        manifest_sha256=expected_manifest_sha256,
        old_snapshot=old_snapshot,
        provenance=provenance,
    )
    write_journal(in_progress, journal)
    stage_path = Path(journal["stage_path"])
    rollback_path = Path(journal["rollback_path"])
    try:
        staged = extract_verified_archive(archive_path, stage_path, records)
        ensure_same_filesystem(active_path, stage_path)
        stage_report = compare_tree_to_manifest(staged, records)
        if not stage_report["ok"]:
            raise DeploymentError("Staged Platform Core tree does not match the verified artifact.")
        journal["target_directories"] = staged["directories"]
        _save_phase(state_root, journal, "stage_verified", hooks=hooks)
        _save_phase(state_root, journal, "swap_intent", hooks=hooks)
        if (_tree_classification(active_path, journal) != "old" or
                _tree_classification(stage_path, journal) != "target" or
                os.path.lexists(rollback_path) or os.path.lexists(Path(journal["failed_candidate_path"]))):
            raise DeploymentError("Deployment topology changed before exchange.")
        hooks.exchange(active_path, stage_path)
        _save_phase(state_root, journal, "swapped", hooks=hooks)

        php_health = _require_hook_success(
            hooks.verify_health(target_id, Path(wp_root)),
            "WordPress Platform Core bootstrap health",
        )
        http_health = _require_hook_success(
            hooks.verify_http_health(target["home"] + "/", php_health.get("site_name")),
            "Local VGNova HTTP health",
        )
        journal["health_verification_result"] = {"php": php_health, "http": http_health}
        _save_phase(state_root, journal, "health_verified", hooks=hooks)

        runtime_fingerprint = fingerprint_tree(active_path)
        parity = compare_tree_to_manifest(runtime_fingerprint, records, journal["target_directories"])
        journal["runtime_parity_result"] = parity
        if not parity["ok"]:
            raise DeploymentError("Active Platform Core runtime does not match the verified artifact.")
        _save_phase(state_root, journal, "parity_verified", hooks=hooks)
        _save_phase(state_root, journal, "finalize_intent", hooks=hooks)

        staged_old = fingerprint_tree(stage_path)
        if not _matches_old_snapshot(staged_old, old_snapshot):
            raise DeploymentError("Retained prior Platform Core tree changed before finalization.")
        if (_tree_classification(active_path, journal) != "target" or
                _tree_classification(stage_path, journal) != "old" or
                os.path.lexists(rollback_path) or os.path.lexists(Path(journal["failed_candidate_path"]))):
            raise DeploymentError("Deployment topology changed before retaining the prior tree.")
        hooks.rename_directory(stage_path, rollback_path)
        retained_old = fingerprint_tree(rollback_path)
        if not _matches_old_snapshot(retained_old, old_snapshot):
            raise DeploymentError("Retained rollback tree does not match the prior runtime snapshot.")
        _save_phase(state_root, journal, "finalized", hooks=hooks)
        _archive_journal(state_root, journal, hooks)
        return journal
    except BaseException as error:
        if journal["phase"] == "finalized":
            raise DeploymentError("Deployment is finalized but its history record needs finalize recovery.") from error
        try:
            _perform_rollback(state_root, journal, hooks)
        except DeploymentError as rollback_error:
            raise rollback_error from error
        raise DeploymentError("Deployment failed; the previous Platform Core tree was restored and the candidate retained.") from error


def _validate_journal_binding(
    journal: dict[str, Any],
    *,
    expected_target: str | None,
    expected_wp_root: Path | None,
) -> None:
    if expected_target is not None and journal["target"] != expected_target:
        raise DeploymentError("Deployment journal target differs from the requested target.")
    if expected_wp_root is not None and Path(journal["wp_root"]) != Path(expected_wp_root):
        raise DeploymentError("Deployment journal WordPress root differs from the registered target.")


def status_deployment(
    state_root: Path,
    *,
    expected_target: str | None = None,
    expected_wp_root: Path | None = None,
) -> dict[str, Any]:
    """Classify an unresolved deployment without changing any path or file."""
    state_root = ensure_state_root(Path(state_root))
    journal_path = state_root / "in-progress.json"
    if not os.path.lexists(journal_path):
        return {"in_progress": False, "recovery_options": []}
    journal = read_journal(journal_path)
    _validate_journal_binding(
        journal,
        expected_target=expected_target,
        expected_wp_root=expected_wp_root,
    )
    paths = {
        "active_tree": Path(journal["active_path"]),
        "stage_tree": Path(journal["stage_path"]),
        "rollback_tree": Path(journal["rollback_path"]),
        "failed_candidate_tree": Path(journal["failed_candidate_path"]),
    }
    states = {name: _tree_classification(path, journal) for name, path in paths.items()}
    old_sources = [name for name in ("stage_tree", "rollback_tree") if states[name] == "old"]
    candidate_sources = [
        name
        for name in ("stage_tree", "rollback_tree", "failed_candidate_tree")
        if states[name] == "target"
    ]
    recovery_options: list[str] = []
    if states["active_tree"] in ("old", "target") and len(old_sources) == 1:
        if states["active_tree"] == "target" and len(candidate_sources) <= 1:
            recovery_options = ["rollback", "finalize"]
        elif states["active_tree"] == "old" and len(candidate_sources) <= 1:
            recovery_options = ["rollback"]
    if states["active_tree"] == "old" and not candidate_sources and journal["phase"] == "prepared":
        recovery_options = ["rollback"]
    if states["active_tree"] == "old" and len(candidate_sources) == 1:
        candidate_name = candidate_sources[0]
        other_candidates = [
            name
            for name in ("stage_tree", "rollback_tree", "failed_candidate_tree")
            if name != candidate_name and states[name] != "missing"
        ]
        if not other_candidates:
            recovery_options = ["rollback"]
    if "unknown" in states.values():
        recovery_options = []
    return {
        "in_progress": True,
        "deployment_id": journal["deployment_id"],
        "target": journal["target"],
        "wp_root": journal["wp_root"],
        "phase": journal["phase"],
        **states,
        "rollback_path_status": states["rollback_tree"],
        "recovery_options": recovery_options,
        "journal_path": str(journal_path),
    }


def _perform_finalize(
    state_root: Path,
    journal: dict[str, Any],
    hooks: DeploymentHooks,
) -> Path:
    """Recheck target health and parity before retaining the previous tree."""
    committed_before_call = False
    try:
        paths = {
            "active": Path(journal["active_path"]),
            "stage": Path(journal["stage_path"]),
            "rollback": Path(journal["rollback_path"]),
            "failed": Path(journal["failed_candidate_path"]),
        }
        states = {name: _tree_classification(path, journal) for name, path in paths.items()}
        if states["active"] != "target":
            raise DeploymentError("Finalize requires the exact target tree at the active path.")
        old_sources = [name for name in ("stage", "rollback") if states[name] == "old"]
        other_managed = "rollback" if old_sources == ["stage"] else "stage"
        if len(old_sources) != 1 or states[other_managed] != "missing" or states["failed"] != "missing":
            raise DeploymentError("Finalize topology does not identify one retained prior tree.")
        if journal["phase"] not in {
            "swap_intent",
            "swapped",
            "health_verified",
            "parity_verified",
            "finalize_intent",
            "rollback_intent",
            "rollback_failed",
            "finalized",
        }:
            raise DeploymentError("Finalize journal phase is inconsistent with the active target tree.")

        _verify_session(hooks, journal["target"], Path(journal["wp_root"]))
        php_health = _require_hook_success(
            hooks.verify_health(journal["target"], Path(journal["wp_root"])),
            "WordPress Platform Core bootstrap health",
        )
        target = EXPECTED_TARGET
        http_health = _require_hook_success(
            hooks.verify_http_health(target["home"] + "/", php_health.get("site_name")),
            "Local VGNova HTTP health",
        )
        journal["health_verification_result"] = {"php": php_health, "http": http_health}
        parity = compare_tree_to_manifest(fingerprint_tree(paths["active"]), journal["target_manifest_records"], journal["target_directories"])
        journal["runtime_parity_result"] = parity
        if not parity["ok"]:
            raise DeploymentError("Active Platform Core runtime does not match the journaled artifact.")

        phase = journal["phase"]
        for prior, next_phase in (
            ("swap_intent", "swapped"),
            ("swapped", "health_verified"),
            ("health_verified", "parity_verified"),
        ):
            if phase == prior:
                _save_phase(state_root, journal, next_phase, hooks=hooks)
                phase = journal["phase"]
        if phase in ("parity_verified", "rollback_intent", "rollback_failed"):
            _save_phase(state_root, journal, "finalize_intent", hooks=hooks)
            phase = journal["phase"]
        if phase not in ("finalize_intent", "finalized"):
            raise DeploymentError("Finalize journal cannot advance from its recorded phase.")

        old_location = old_sources[0]
        if old_location == "stage":
            fresh_states = {name: _tree_classification(path, journal) for name, path in paths.items()}
            if fresh_states != {"active": "target", "stage": "old", "rollback": "missing", "failed": "missing"}:
                raise DeploymentError("Finalize topology changed before retaining the prior tree.")
            hooks.rename_directory(paths["stage"], paths["rollback"])
        retained = fingerprint_tree(paths["rollback"])
        if not _matches_old_snapshot(retained, journal["old_runtime_snapshot"]):
            raise DeploymentError("Retained rollback tree does not match the recorded prior runtime.")
        if journal["phase"] != "finalized":
            _save_phase(state_root, journal, "finalized", hooks=hooks)
        committed_before_call = True
        return _archive_journal(state_root, journal, hooks)
    except BaseException as error:
        if committed_before_call:
            raise DeploymentError("Deployment is finalized but its history record still needs finalize recovery.") from error
        try:
            _perform_rollback(state_root, journal, hooks)
        except DeploymentError as rollback_error:
            raise rollback_error from error
        raise DeploymentError("Finalize verification failed; the prior Platform Core tree was restored.") from error


def recover_deployment(
    state_root: Path,
    *,
    action: str,
    hooks: DeploymentHooks,
    expected_target: str | None = None,
    expected_wp_root: Path | None = None,
) -> dict[str, Any]:
    """Recover one unresolved deployment after explicit operator selection."""
    if action not in ("rollback", "finalize"):
        raise DeploymentError("Recovery action must be rollback or finalize.")
    state_root = ensure_state_root(Path(state_root))
    journal = read_journal(state_root / "in-progress.json")
    _validate_journal_binding(
        journal,
        expected_target=expected_target,
        expected_wp_root=expected_wp_root,
    )
    if action == "rollback":
        history_path = _perform_rollback(state_root, journal, hooks)
    else:
        history_path = _perform_finalize(state_root, journal, hooks)
    return {"journal": journal, "history_path": str(history_path)}
