#!/usr/bin/env python3
"""Fixture-only tests for the Platform Core runtime deployer."""

from __future__ import annotations

import importlib.util
import errno
import hashlib
import io
import json
import os
import stat
import subprocess
import sys
import tarfile
import tempfile
import unittest
import urllib.error
from unittest import mock
from pathlib import Path
from types import SimpleNamespace


MODULE_PATH = Path(__file__).resolve().parents[2] / "runtime-tools/platform_core_deployment.py"


def load_deployer():
    spec = importlib.util.spec_from_file_location("platform_core_deployment", MODULE_PATH)
    if spec is None or spec.loader is None:
        raise AssertionError("Could not load the Platform Core deployment module.")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def write_tar(path: Path, entries: list[tuple[str, bytes, int, str]]) -> None:
    with tarfile.open(path, "w:gz", format=tarfile.USTAR_FORMAT) as archive:
        root = tarfile.TarInfo("voelgoed-platform-core/")
        root.type = tarfile.DIRTYPE
        root.mode = 0o755
        archive.addfile(root)
        for name, contents, mode, kind in entries:
            member = tarfile.TarInfo(name)
            member.mode = mode
            if kind == "file":
                member.size = len(contents)
                archive.addfile(member, io.BytesIO(contents))
            elif kind == "symlink":
                member.type = tarfile.SYMTYPE
                member.linkname = "../../outside"
                archive.addfile(member)
            elif kind == "hardlink":
                member.type = tarfile.LNKTYPE
                member.linkname = "voelgoed-platform-core/safe.txt"
                archive.addfile(member)
            elif kind == "fifo":
                member.type = tarfile.FIFOTYPE
                archive.addfile(member)
            elif kind == "socket":
                member.type = b"s"
                archive.addfile(member)
            elif kind == "device":
                member.type = tarfile.CHRTYPE
                member.devmajor = 1
                member.devminor = 3
                archive.addfile(member)
            elif kind == "directory":
                member.type = tarfile.DIRTYPE
                archive.addfile(member)


def sample_journal() -> dict[str, object]:
    timestamp = "2026-10-27T00:00:00Z"
    target_bytes = b"new"
    old_bytes = b"old"
    target_record = {
        "path": "includes/plugin.php",
        "mode": "100644",
        "size": len(target_bytes),
        "sha256": hashlib.sha256(target_bytes).hexdigest(),
    }
    old_record = {
        "path": "includes/plugin.php",
        "mode": "100755",
        "size": len(old_bytes),
        "sha256": hashlib.sha256(old_bytes).hexdigest(),
    }
    old_hash = hashlib.sha256(
        f"{old_record['mode']} {old_record['sha256']} {old_record['path']}\n".encode()
    ).hexdigest()
    return {
        "schema_version": 1,
        "deployment_id": "a" * 32,
        "target": "vgnova-local-10039",
        "wp_root": "/tmp/fixture/app/public",
        "source_commit": "b" * 40,
        "archive_sha256": "c" * 64,
        "manifest_sha256": "d" * 64,
        "target_tree_sha256": hashlib.sha256(
            f"{target_record['mode']} {target_record['sha256']} {target_record['path']}\n".encode()
        ).hexdigest(),
        "target_manifest_records": [target_record],
        "old_runtime_tree_sha256": old_hash,
        "old_runtime_snapshot": {
            "file_count": 1,
            "files": [old_record],
            "directories": ["includes"],
            "tree_sha256": old_hash,
            "root_owner": os.getuid(),
            "root_group": os.getgid(),
            "root_mode": 0o755,
            "device_id": 1,
        },
        "active_path": "/tmp/fixture/app/public/wp-content/plugins/voelgoed-platform-core",
        "stage_path": "/tmp/fixture/app/.vgnova-platform-core-stage-" + "a" * 32,
        "rollback_path": "/tmp/fixture/app/.vgnova-platform-core-rollback-" + "a" * 32,
        "failed_candidate_path": "/tmp/fixture/app/.vgnova-platform-core-failed-" + "a" * 32,
        "phase": "prepared",
        "phase_history": [{"phase": "prepared", "timestamp": timestamp}],
        "timestamps": {"created_at": timestamp, "updated_at": timestamp},
        "recovery_status": "none",
        "health_verification_result": None,
        "runtime_parity_result": None,
        "provenance": {
            "deployer_commit": "1" * 40,
            "deployer_sha256": "2" * 64,
            "deployer_module_sha256": "4" * 64,
            "verifier_sha256": "3" * 64,
        },
    }


def create_deployment_fixture(root: Path) -> dict[str, object]:
    wp_root = root / "app/public"
    active = wp_root / "wp-content/plugins/voelgoed-platform-core"
    (active / "includes").mkdir(parents=True)
    (active / "includes/old.php").write_bytes(b"old runtime")
    (active / "includes/old.php").chmod(0o755)
    entrypoint = active / "voelgoed-platform-core.php"
    entrypoint.write_text("<?php old entrypoint", encoding="utf-8")
    entrypoint.chmod(0o644)
    artifact_directory = root / "artifact"
    artifact_directory.mkdir()
    target_bytes = b"target runtime"
    entrypoint_bytes = b"<?php target entrypoint"
    target_records = [
        {
        "path": "includes/new.php",
        "mode": "100644",
        "size": len(target_bytes),
        "sha256": hashlib.sha256(target_bytes).hexdigest(),
        },
        {
            "path": "voelgoed-platform-core.php",
            "mode": "100644",
            "size": len(entrypoint_bytes),
            "sha256": hashlib.sha256(entrypoint_bytes).hexdigest(),
        },
    ]
    target_records.sort(key=lambda item: item["path"].encode("utf-8"))
    archive_path = artifact_directory / "voelgoed-platform-core.tar.gz"
    write_tar(
        archive_path,
        [
            ("voelgoed-platform-core/includes/", b"", 0o755, "directory"),
            ("voelgoed-platform-core/includes/new.php", target_bytes, 0o644, "file"),
            ("voelgoed-platform-core/voelgoed-platform-core.php", entrypoint_bytes, 0o644, "file"),
        ],
    )
    source_commit = "b" * 40
    manifest = {
        "schema_version": 3,
        "source_commit": source_commit,
        "file_count": len(target_records),
        "files": target_records,
        "tree_sha256": hashlib.sha256(
            "".join(f"{record['mode']} {record['sha256']} {record['path']}\n" for record in target_records).encode()
        ).hexdigest(),
        "archive_sha256": hashlib.sha256(archive_path.read_bytes()).hexdigest(),
    }
    manifest_path = artifact_directory / "voelgoed-platform-core.manifest.json"
    manifest_bytes = (json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode()
    manifest_path.write_bytes(manifest_bytes)
    target = {
        "id": "vgnova-local-10039",
        "home": "http://localhost:10039",
        "site": "http://localhost:10039",
        "database_name": "local",
        "database_host": "localhost",
        "platform_core_entrypoint": "wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php",
    }
    return {
        "wp_root": wp_root,
        "active": active,
        "artifact_directory": artifact_directory,
        "manifest": manifest,
        "manifest_sha256": hashlib.sha256(manifest_bytes).hexdigest(),
        "source_commit": source_commit,
        "target": target,
    }


def deployment_hooks(deployer, fixture: dict[str, object], **overrides):
    values = {
        "verify_artifact": lambda _directory, _commit: fixture["manifest"],
        "verify_session": lambda _target_id, _wp_root: {"ok": True},
        "verify_health": lambda _target_id, _wp_root: {"ok": True, "plugin_loaded": True},
        "verify_http_health": lambda _home, _site_name: {"ok": True, "status": 200},
        "provenance": lambda: {
            "deployer_commit": "1" * 40,
            "deployer_sha256": "2" * 64,
            "deployer_module_sha256": "4" * 64,
            "verifier_sha256": "3" * 64,
        },
        "deployment_id": lambda: "a" * 32,
    }
    values.update(overrides)
    return deployer.DeploymentHooks(**values)


def prepare_recovery_state(
    deployer,
    root: Path,
    fixture: dict[str, object],
    *,
    phase: str = "swap_intent",
    target_active: bool = True,
    old_at_rollback: bool = False,
) -> tuple[Path, dict[str, object]]:
    active = fixture["active"]
    wp_root = fixture["wp_root"]
    old_snapshot = deployer.fingerprint_tree(active)
    provenance = {
        "deployer_commit": "1" * 40,
        "deployer_sha256": "2" * 64,
        "deployer_module_sha256": "4" * 64,
        "verifier_sha256": "3" * 64,
    }
    state_root = root / "app/.vgnova-platform-core-deployments"
    state_root.mkdir(mode=0o700)
    journal = deployer._create_journal(
        deployment_id="a" * 32,
        target_id="vgnova-local-10039",
        wp_root=wp_root,
        source_commit=fixture["source_commit"],
        manifest=fixture["manifest"],
        manifest_sha256=fixture["manifest_sha256"],
        old_snapshot=old_snapshot,
        provenance=provenance,
    )
    deployer.write_journal(state_root / "in-progress.json", journal)
    deployer.extract_verified_archive(
        fixture["artifact_directory"] / "voelgoed-platform-core.tar.gz",
        Path(journal["stage_path"]),
        fixture["manifest"]["files"],
    )
    deployer.advance_journal(journal, "stage_verified")
    deployer.write_journal(state_root / "in-progress.json", journal)
    deployer.advance_journal(journal, "swap_intent")
    deployer.write_journal(state_root / "in-progress.json", journal)
    if target_active:
        deployer.rename_exchange(active, Path(journal["stage_path"]))
    if phase != "swap_intent":
        for next_phase in ("swapped", "health_verified", "parity_verified", "finalize_intent"):
            deployer.advance_journal(journal, next_phase)
            deployer.write_journal(state_root / "in-progress.json", journal)
            if next_phase == phase:
                break
    if phase == "rollback_intent":
        deployer.advance_journal(journal, "rollback_intent")
        deployer.write_journal(state_root / "in-progress.json", journal)
    if old_at_rollback:
        deployer.rename_noreplace(Path(journal["stage_path"]), Path(journal["rollback_path"]))
    return state_root, journal


class PlatformCoreDeploymentTests(unittest.TestCase):
    def test_relative_path_validator_rejects_parent_segments(self) -> None:
        self.assertTrue(MODULE_PATH.is_file(), "safe relative-path validation is not implemented")
        deployer = load_deployer()
        with self.assertRaises(deployer.DeploymentError):
            deployer.safe_relative_path("../outside.php")

    def test_manifest_records_require_exact_canonical_file_inventory(self) -> None:
        self.assertTrue(MODULE_PATH.is_file(), "deployment module is missing")
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "validate_manifest_records"), "manifest validation is not implemented")
        contents = b"plugin bytes"
        record = {
            "path": "includes/plugin.php",
            "mode": "100644",
            "size": len(contents),
            "sha256": hashlib.sha256(contents).hexdigest(),
        }
        manifest = {
            "file_count": 1,
            "files": [record],
            "tree_sha256": hashlib.sha256(
                f"{record['mode']} {record['sha256']} {record['path']}\n".encode()
            ).hexdigest(),
        }
        self.assertEqual(deployer.validate_manifest_records(manifest), [record])

    def test_manifest_records_reject_tree_digest_mismatch(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "validate_manifest_records"), "manifest validation is not implemented")
        with self.assertRaises(deployer.DeploymentError):
            deployer.validate_manifest_records({"file_count": 0, "files": [], "tree_sha256": "0" * 64})

    def test_manifest_records_reject_duplicate_and_unsafe_paths(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "validate_manifest_records"), "manifest validation is not implemented")
        for unsafe_path in ("../outside.php", "/absolute.php", "a//b.php", "a\\b.php", "a\0b.php", "a\r\nb.php"):
            record = {
                "path": unsafe_path,
                "mode": "100644",
                "size": 0,
                "sha256": hashlib.sha256(b"").hexdigest(),
            }
            with self.subTest(path=repr(unsafe_path)), self.assertRaises(deployer.DeploymentError):
                deployer.validate_manifest_records({"file_count": 1, "files": [record], "tree_sha256": "0" * 64})

    def test_runtime_fingerprint_records_file_and_root_metadata(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "fingerprint_tree"), "runtime fingerprinting is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / "plugin"
            (root / "includes").mkdir(parents=True)
            target = root / "includes/plugin.php"
            target.write_bytes(b"runtime bytes")
            target.chmod(0o755)
            result = deployer.fingerprint_tree(root)
            record = {
                "path": "includes/plugin.php",
                "mode": "100755",
                "size": len(b"runtime bytes"),
                "sha256": hashlib.sha256(b"runtime bytes").hexdigest(),
            }
            self.assertEqual(result["files"], [record])
            self.assertEqual(result["tree_sha256"], deployer.canonical_tree_sha256([record]))
            self.assertEqual(result["file_count"], 1)
            self.assertEqual(result["root_owner"], os.stat(root).st_uid)
            self.assertEqual(result["root_group"], os.stat(root).st_gid)
            self.assertEqual(result["root_mode"], stat.S_IMODE(os.stat(root).st_mode))
            self.assertEqual(result["device_id"], os.stat(root).st_dev)

    def test_runtime_fingerprint_rejects_symlinks(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "fingerprint_tree"), "runtime fingerprinting is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / "plugin"
            root.mkdir()
            (root / "unsafe-link").symlink_to("/etc/passwd")
            with self.assertRaises(deployer.DeploymentError):
                deployer.fingerprint_tree(root)

    def test_runtime_fingerprint_rejects_unsupported_filesystem_objects(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / "plugin"
            root.mkdir()
            os.mkfifo(root / "unsafe-pipe")
            with self.assertRaises(deployer.DeploymentError):
                deployer.fingerprint_tree(root)

    def test_runtime_target_requires_exact_identity_and_real_plugin_root(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            wp_root = Path(temporary) / "app/public"
            plugin = wp_root / "wp-content/plugins/voelgoed-platform-core"
            plugin.mkdir(parents=True)
            (plugin / "voelgoed-platform-core.php").write_text("<?php", encoding="utf-8")
            target = dict(deployer.EXPECTED_TARGET)
            self.assertEqual(
                deployer.validate_runtime_target(target, deployer.TARGET_ID, wp_root, wp_root)[0],
                plugin,
            )
            with self.assertRaises(deployer.DeploymentError):
                deployer.validate_runtime_target(target, "another-target", wp_root, wp_root)
            with self.assertRaises(deployer.DeploymentError):
                deployer.validate_runtime_target(target, deployer.TARGET_ID, Path(temporary), wp_root)

            real_plugin = wp_root / "wp-content/plugins/real-plugin"
            plugin.rename(real_plugin)
            plugin.symlink_to(real_plugin, target_is_directory=True)
            with self.assertRaises(deployer.DeploymentError):
                deployer.validate_runtime_target(target, deployer.TARGET_ID, wp_root, wp_root)

    def test_runtime_target_rejects_symlinked_wp_content_and_plugins_ancestors(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            for ancestor_name in ("wp-content", "plugins"):
                with self.subTest(ancestor=ancestor_name):
                    wp_root = root / ancestor_name / "app/public"
                    external = root / ancestor_name / "external"
                    real_plugin = external / "plugins/voelgoed-platform-core"
                    real_plugin.mkdir(parents=True)
                    (real_plugin / "voelgoed-platform-core.php").write_text("<?php", encoding="utf-8")
                    wp_content = wp_root / "wp-content"
                    if ancestor_name == "wp-content":
                        wp_root.mkdir(parents=True)
                        wp_content.symlink_to(external, target_is_directory=True)
                    else:
                        wp_content.mkdir(parents=True)
                        (wp_content / "plugins").symlink_to(external / "plugins", target_is_directory=True)
                    with self.assertRaises(deployer.DeploymentError):
                        deployer.validate_runtime_target(
                            dict(deployer.EXPECTED_TARGET), deployer.TARGET_ID, wp_root, wp_root
                        )

    def test_cross_filesystem_stage_is_rejected(self) -> None:
        deployer = load_deployer()
        with mock.patch.object(
            deployer,
            "_require_directory",
            side_effect=[SimpleNamespace(st_dev=10), SimpleNamespace(st_dev=11)],
        ):
            with self.assertRaises(deployer.DeploymentError):
                deployer.ensure_same_filesystem(Path("/fixture/active"), Path("/fixture/stage"))

    def test_verified_archive_extracts_only_manifest_files(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "extract_verified_archive"), "safe archive extraction is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            archive = Path(temporary) / "artifact.tar"
            stage = Path(temporary) / "stage"
            contents = b"fixture plugin"
            record = {
                "path": "safe.txt",
                "mode": "100644",
                "size": len(contents),
                "sha256": hashlib.sha256(contents).hexdigest(),
            }
            write_tar(archive, [("voelgoed-platform-core/safe.txt", contents, 0o644, "file")])
            result = deployer.extract_verified_archive(archive, stage, [record])
            self.assertEqual((stage / "safe.txt").read_bytes(), contents)
            self.assertEqual(result["files"], [record])
            self.assertEqual(result["tree_sha256"], deployer.canonical_tree_sha256([record]))

    def test_verified_archive_rejects_unsafe_and_unmanifested_members_before_writing(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "extract_verified_archive"), "safe archive extraction is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            contents = b"fixture plugin"
            record = {
                "path": "safe.txt",
                "mode": "100644",
                "size": len(contents),
                "sha256": hashlib.sha256(contents).hexdigest(),
            }
            for index, bad_entry in enumerate(
                [
                    ("voelgoed-platform-core/unsafe-link", b"", 0o777, "symlink"),
                    ("voelgoed-platform-core/unlisted.txt", b"extra", 0o644, "file"),
                    ("voelgoed-platform-core/../outside.txt", b"escape", 0o644, "file"),
                    ("voelgoed-platform-core/hardlink", b"", 0o644, "hardlink"),
                    ("voelgoed-platform-core/pipe", b"", 0o644, "fifo"),
                    ("voelgoed-platform-core/socket", b"", 0o644, "socket"),
                    ("voelgoed-platform-core/device", b"", 0o644, "device"),
                    ("/absolute.txt", b"escape", 0o644, "file"),
                    ("voelgoed-platform-core\\unsafe.txt", b"escape", 0o644, "file"),
                    ("voelgoed-platform-core/bad\r\nname.txt", b"escape", 0o644, "file"),
                    ("other-plugin/safe.txt", contents, 0o644, "file"),
                    ("voelgoed-platform-core/safe.txt", contents, 0o644, "file"),
                ]
            ):
                archive = root / f"unsafe-{index}.tar"
                stage = root / f"stage-{index}"
                write_tar(archive, [("voelgoed-platform-core/safe.txt", contents, 0o644, "file"), bad_entry])
                with self.assertRaises(deployer.DeploymentError):
                    deployer.extract_verified_archive(archive, stage, [record])
                self.assertFalse(stage.exists())
                self.assertFalse((root / "outside.txt").exists())

            for index, bad_entry in enumerate(
                [
                    ("voelgoed-platform-core/safe.txt", contents, 0o755, "file"),
                    ("voelgoed-platform-core/safe.txt", b"different length", 0o644, "file"),
                    ("voelgoed-platform-core/safe.txt", b"fixture plagin", 0o644, "file"),
                ]
            ):
                archive = root / f"mismatch-{index}.tar"
                stage = root / f"mismatch-stage-{index}"
                write_tar(archive, [bad_entry])
                with self.assertRaises(deployer.DeploymentError):
                    deployer.extract_verified_archive(archive, stage, [record])
                self.assertFalse(stage.exists())

    def test_atomic_exchange_swaps_whole_fixture_directories(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "rename_exchange"), "atomic directory exchange is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            active = root / "active"
            stage = root / "stage"
            active.mkdir()
            stage.mkdir()
            (active / "old.txt").write_text("old", encoding="utf-8")
            (stage / "new.txt").write_text("new", encoding="utf-8")
            deployer.rename_exchange(active, stage)
            self.assertEqual({path.name for path in active.iterdir()}, {"new.txt"})
            self.assertEqual({path.name for path in stage.iterdir()}, {"old.txt"})

    def test_atomic_exchange_unavailable_fails_without_sequential_fallback(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "rename_exchange"), "atomic directory exchange is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            active = root / "active"
            stage = root / "stage"
            active.mkdir()
            stage.mkdir()
            (active / "old.txt").write_text("old", encoding="utf-8")
            (stage / "new.txt").write_text("new", encoding="utf-8")

            def unavailable(*_args):
                raise OSError(errno.ENOSYS, "unsupported")

            with self.assertRaises(deployer.DeploymentError):
                deployer.rename_exchange(active, stage, syscall=unavailable)
            self.assertEqual((active / "old.txt").read_text(encoding="utf-8"), "old")
            self.assertEqual((stage / "new.txt").read_text(encoding="utf-8"), "new")

    def test_atomic_exchange_parent_fsync_failure_is_recovered_from_tree_hashes(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            state_root, journal = prepare_recovery_state(
                deployer, root, fixture, phase="swap_intent", target_active=False
            )
            stage = Path(journal["stage_path"])
            with mock.patch.object(deployer, "_fsync_directory", side_effect=OSError(errno.EIO, "injected")):
                with self.assertRaises(deployer.DeploymentError):
                    deployer.rename_exchange(fixture["active"], stage)

            self.assertEqual(deployer._tree_classification(fixture["active"], journal), "target")
            self.assertEqual(deployer._tree_classification(stage, journal), "old")
            result = deployer.recover_deployment(
                state_root,
                action="rollback",
                hooks=deployment_hooks(deployer, fixture),
            )
            self.assertEqual(result["journal"]["phase"], "rolled_back")
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            self.assertTrue(Path(journal["failed_candidate_path"]).is_dir())

    def test_no_replace_rename_preserves_existing_rollback_destination(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "rename_noreplace"), "no-replace rename is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            stage = root / "stage"
            rollback = root / "rollback"
            stage.mkdir()
            rollback.mkdir()
            (stage / "new.txt").write_text("new", encoding="utf-8")
            (rollback / "protected.txt").write_text("protected", encoding="utf-8")
            with self.assertRaises(deployer.DeploymentError):
                deployer.rename_noreplace(stage, rollback)
            self.assertTrue(stage.exists())
            self.assertEqual((rollback / "protected.txt").read_text(encoding="utf-8"), "protected")

    def test_journal_is_durable_private_and_validated(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "write_journal"), "durable journal writes are not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            journal_path = root / "in-progress.json"
            record = sample_journal()
            real_fsync = os.fsync
            real_replace = os.replace
            with mock.patch.object(deployer.os, "fsync", wraps=real_fsync) as fsync:
                with mock.patch.object(deployer.os, "replace", wraps=real_replace) as replace:
                    deployer.write_journal(journal_path, record)
            self.assertEqual(replace.call_count, 1)
            self.assertGreaterEqual(fsync.call_count, 2)
            self.assertEqual(stat.S_IMODE(journal_path.stat().st_mode), 0o600)
            self.assertEqual(deployer.read_journal(journal_path), record)
            self.assertEqual(list(root.glob(".in-progress.json.tmp-*")), [])

    def test_journal_rejects_unknown_phase_and_credential_fields(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "validate_journal"), "journal validation is not implemented")
        bad_phase = sample_journal()
        bad_phase["phase"] = "guess"
        with self.assertRaises(deployer.DeploymentError):
            deployer.validate_journal(bad_phase)
        credential = sample_journal()
        credential["database_password"] = "never store this"
        with self.assertRaises(deployer.DeploymentError):
            deployer.validate_journal(credential)
        skipped_phase = sample_journal()
        skipped_phase["phase"] = "finalized"
        skipped_phase["phase_history"] = [
            {"phase": "prepared", "timestamp": "2026-10-27T00:00:00Z"},
            {"phase": "finalized", "timestamp": "2026-10-27T00:00:01Z"},
        ]
        with self.assertRaises(deployer.DeploymentError):
            deployer.validate_journal(skipped_phase)

    def test_journal_carries_exact_target_and_prior_runtime_file_records(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "validate_journal"), "journal validation is not implemented")
        record = sample_journal()
        deployer.validate_journal(record)
        self.assertEqual(record["target_manifest_records"][0]["path"], "includes/plugin.php")
        self.assertEqual(record["old_runtime_snapshot"]["files"][0]["mode"], "100755")

    def test_journal_phase_advance_records_history_and_rejects_skips(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "advance_journal"), "journal phase transitions are not implemented")
        record = sample_journal()
        deployer.advance_journal(record, "stage_verified", now=lambda: "2026-10-27T01:00:00Z")
        self.assertEqual(record["phase"], "stage_verified")
        self.assertEqual([item["phase"] for item in record["phase_history"]], ["prepared", "stage_verified"])
        with self.assertRaises(deployer.DeploymentError):
            deployer.advance_journal(record, "finalized")

    def test_history_journal_move_uses_no_replace_semantics(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "rename_file_noreplace"), "no-replace journal retention is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            source = root / "in-progress.json"
            destination = root / "history.json"
            source.write_text("complete", encoding="utf-8")
            deployer.rename_file_noreplace(source, destination)
            self.assertFalse(source.exists())
            self.assertEqual(destination.read_text(encoding="utf-8"), "complete")

    def test_state_root_rejects_wrong_mode_and_symlink(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "ensure_state_root"), "deployment state-root validation is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            permissive = root / "permissive"
            permissive.mkdir(mode=0o755)
            permissive.chmod(0o755)
            with self.assertRaises(deployer.DeploymentError):
                deployer.ensure_state_root(permissive)
            real = root / "real"
            real.mkdir(mode=0o700)
            link = root / "link"
            link.symlink_to(real, target_is_directory=True)
            with self.assertRaises(deployer.DeploymentError):
                deployer.ensure_state_root(link)

    def test_deploy_uses_exact_artifact_and_retains_verified_old_runtime(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "deploy_artifact"), "deployment orchestration is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            record = deployer.deploy_artifact(
                repo_root=root,
                target=fixture["target"],
                target_id="vgnova-local-10039",
                wp_root=fixture["wp_root"],
                registered_wp_root=fixture["wp_root"],
                artifact_directory=fixture["artifact_directory"],
                source_commit=fixture["source_commit"],
                expected_manifest_sha256=fixture["manifest_sha256"],
                hooks=deployment_hooks(deployer, fixture),
            )
            self.assertEqual(record["phase"], "finalized")
            self.assertTrue((fixture["active"] / "includes/new.php").is_file())
            self.assertFalse((fixture["active"] / "includes/old.php").exists())
            rollback = root / "app" / (".vgnova-platform-core-rollback-" + "a" * 32)
            self.assertTrue((rollback / "includes/old.php").is_file())
            self.assertTrue((root / "app/.vgnova-platform-core-deployments/history" / ("a" * 32 + ".json")).is_file())

    def test_session_failure_precedes_any_runtime_filesystem_write(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "deploy_artifact"), "deployment orchestration is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])

            def no_session(_target_id, _wp_root):
                raise deployer.DeploymentError("no session")

            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256=fixture["manifest_sha256"],
                    hooks=deployment_hooks(deployer, fixture, verify_session=no_session),
                )
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            self.assertFalse((root / "app/.vgnova-platform-core-deployments").exists())

    def test_manifest_hash_mismatch_rejects_before_any_runtime_write(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256="f" * 64,
                    hooks=deployment_hooks(deployer, fixture),
                )
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            self.assertFalse((root / "app/.vgnova-platform-core-deployments").exists())

    def test_health_failure_atomically_restores_old_tree_and_retains_candidate(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "deploy_artifact"), "deployment orchestration is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256=fixture["manifest_sha256"],
                    hooks=deployment_hooks(
                        deployer,
                        fixture,
                        verify_health=lambda _target_id, _wp_root: {"ok": False},
                    ),
                )
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            failed = root / "app" / (".vgnova-platform-core-failed-" + "a" * 32)
            self.assertTrue((failed / "includes/new.php").is_file())
            history = root / "app/.vgnova-platform-core-deployments/history" / ("a" * 32 + ".json")
            self.assertEqual(json.loads(history.read_text(encoding="utf-8"))["phase"], "rolled_back")

    def test_failed_atomic_rollback_keeps_journal_and_both_trees(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            calls = {"exchange": 0}

            def fail_rollback(active, stage):
                calls["exchange"] += 1
                if calls["exchange"] == 1:
                    deployer.rename_exchange(active, stage)
                    return
                raise deployer.DeploymentError("injected rollback syscall failure")

            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256=fixture["manifest_sha256"],
                    hooks=deployment_hooks(
                        deployer,
                        fixture,
                        exchange=fail_rollback,
                        verify_health=lambda _target, _root: {"ok": False},
                    ),
                )
            state_root = root / "app/.vgnova-platform-core-deployments"
            journal = deployer.read_journal(state_root / "in-progress.json")
            self.assertEqual(journal["phase"], "rollback_failed")
            self.assertTrue(fixture["active"].is_dir())
            self.assertTrue(Path(journal["stage_path"]).is_dir())
            self.assertFalse(Path(journal["failed_candidate_path"]).exists())
            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256=fixture["manifest_sha256"],
                    hooks=deployment_hooks(deployer, fixture),
                )

    def test_parity_failure_atomically_restores_old_tree_and_retains_candidate(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "deploy_artifact"), "deployment orchestration is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])

            def contaminate_active(_target_id, _wp_root):
                (fixture["active"] / "extra.php").write_text("unexpected", encoding="utf-8")
                return {"ok": True}

            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256=fixture["manifest_sha256"],
                    hooks=deployment_hooks(deployer, fixture, verify_health=contaminate_active),
                )
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            failed = root / "app" / (".vgnova-platform-core-failed-" + "a" * 32)
            self.assertTrue((failed / "extra.php").is_file())

    def test_existing_unresolved_journal_blocks_deployment(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "deploy_artifact"), "deployment orchestration is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root = root / "app/.vgnova-platform-core-deployments"
            state_root.mkdir(mode=0o700)
            deployer.write_journal(state_root / "in-progress.json", sample_journal())
            with self.assertRaises(deployer.DeploymentError):
                deployer.deploy_artifact(
                    repo_root=root,
                    target=fixture["target"],
                    target_id="vgnova-local-10039",
                    wp_root=fixture["wp_root"],
                    registered_wp_root=fixture["wp_root"],
                    artifact_directory=fixture["artifact_directory"],
                    source_commit=fixture["source_commit"],
                    expected_manifest_sha256=fixture["manifest_sha256"],
                    hooks=deployment_hooks(deployer, fixture),
                )

    def test_status_classifies_swap_intent_without_writing(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "status_deployment"), "read-only deployment status is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root, _journal = prepare_recovery_state(deployer, root, fixture, phase="swap_intent")
            journal_path = state_root / "in-progress.json"
            before = journal_path.read_bytes()
            stage = root / "app" / (".vgnova-platform-core-stage-" + "a" * 32)
            stage_before = deployer.fingerprint_tree(stage)
            status = deployer.status_deployment(state_root)
            self.assertEqual(status["phase"], "swap_intent")
            self.assertEqual(status["active_tree"], "target")
            self.assertEqual(status["stage_tree"], "old")
            self.assertEqual(set(status["recovery_options"]), {"rollback", "finalize"})
            self.assertEqual(journal_path.read_bytes(), before)
            self.assertEqual(deployer.fingerprint_tree(stage), stage_before)

    def test_status_and_recovery_reject_journal_for_another_wordpress_root(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root, journal = prepare_recovery_state(deployer, root, fixture, phase="swap_intent")
            foreign_root = Path("/tmp/foreign-wordpress/app/public")
            foreign_app = foreign_root.parent
            journal["wp_root"] = str(foreign_root)
            journal["active_path"] = str(foreign_root / "wp-content/plugins/voelgoed-platform-core")
            journal["stage_path"] = str(foreign_app / f".vgnova-platform-core-stage-{journal['deployment_id']}")
            journal["rollback_path"] = str(foreign_app / f".vgnova-platform-core-rollback-{journal['deployment_id']}")
            journal["failed_candidate_path"] = str(foreign_app / f".vgnova-platform-core-failed-{journal['deployment_id']}")
            deployer.write_journal(state_root / "in-progress.json", journal)

            with mock.patch.object(deployer, "_tree_classification") as classify:
                with self.assertRaises(deployer.DeploymentError):
                    deployer.status_deployment(
                        state_root,
                        expected_target=deployer.TARGET_ID,
                        expected_wp_root=fixture["wp_root"],
                    )
                classify.assert_not_called()

            verify_session = mock.Mock(side_effect=deployer.DeploymentError("session should not be checked"))
            with self.assertRaises(deployer.DeploymentError):
                deployer.recover_deployment(
                    state_root,
                    action="rollback",
                    hooks=deployment_hooks(deployer, fixture, verify_session=verify_session),
                    expected_target=deployer.TARGET_ID,
                    expected_wp_root=fixture["wp_root"],
                )
            verify_session.assert_not_called()

    def test_rollback_recovery_uses_hashes_after_exchange_before_journal_update(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "recover_deployment"), "crash recovery is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            state_root, _journal = prepare_recovery_state(deployer, root, fixture, phase="swap_intent")
            result = deployer.recover_deployment(
                state_root,
                action="rollback",
                hooks=deployment_hooks(deployer, fixture),
            )
            self.assertEqual(result["journal"]["phase"], "rolled_back")
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            failed = root / "app" / (".vgnova-platform-core-failed-" + "a" * 32)
            self.assertTrue((failed / "includes/new.php").is_file())
            self.assertFalse((state_root / "in-progress.json").exists())

    def test_pre_exchange_recovery_restores_old_active_tree_and_retains_stage(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            state_root, journal = prepare_recovery_state(
                deployer,
                root,
                fixture,
                phase="swap_intent",
                target_active=False,
            )
            status = deployer.status_deployment(state_root)
            self.assertIn("rollback", status["recovery_options"])
            result = deployer.recover_deployment(
                state_root,
                action="rollback",
                hooks=deployment_hooks(deployer, fixture),
            )
            self.assertEqual(result["journal"]["phase"], "rolled_back")
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            self.assertTrue(Path(journal["failed_candidate_path"]).is_dir())

    def test_partial_stage_from_crash_during_extraction_is_retained_on_rollback(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            state_root, journal = prepare_recovery_state(
                deployer, root, fixture, phase="swap_intent", target_active=False
            )
            stage = Path(journal["stage_path"])
            deployer._remove_owned_tree(stage)
            stage.mkdir(mode=0o700)
            (stage / "partial.php").write_text("incomplete", encoding="utf-8")

            result = deployer.recover_deployment(
                state_root,
                action="rollback",
                hooks=deployment_hooks(deployer, fixture),
            )

            self.assertEqual(result["journal"]["phase"], "rolled_back")
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            failed = Path(journal["failed_candidate_path"])
            self.assertEqual((failed / "partial.php").read_text(encoding="utf-8"), "incomplete")

    def test_recovery_session_failure_precedes_journal_or_tree_changes(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root, _journal = prepare_recovery_state(deployer, root, fixture, phase="swap_intent")
            journal_path = state_root / "in-progress.json"
            journal_before = journal_path.read_bytes()
            active_before = deployer.fingerprint_tree(fixture["active"])
            stage_path = root / "app" / (".vgnova-platform-core-stage-" + "a" * 32)
            stage_before = deployer.fingerprint_tree(stage_path)

            def no_session(_target, _wp_root):
                raise deployer.DeploymentError("session unavailable")

            with self.assertRaises(deployer.DeploymentError):
                deployer.recover_deployment(
                    state_root,
                    action="rollback",
                    hooks=deployment_hooks(deployer, fixture, verify_session=no_session),
                )
            self.assertEqual(journal_path.read_bytes(), journal_before)
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), active_before)
            self.assertEqual(deployer.fingerprint_tree(stage_path), stage_before)

    def test_failed_finalize_recovery_rolls_back_and_retains_candidate(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            state_root, journal = prepare_recovery_state(deployer, root, fixture, phase="health_verified")
            hooks = deployment_hooks(
                deployer,
                fixture,
                verify_health=lambda _target, _root: {"ok": False},
            )
            with self.assertRaises(deployer.DeploymentError):
                deployer.recover_deployment(state_root, action="finalize", hooks=hooks)
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)
            self.assertTrue(Path(journal["failed_candidate_path"]).is_dir())
            self.assertFalse((state_root / "in-progress.json").exists())

    def test_rollback_recovery_after_rollback_intent_uses_tree_hashes(self) -> None:
        deployer = load_deployer()
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            before = deployer.fingerprint_tree(fixture["active"])
            state_root, _journal = prepare_recovery_state(deployer, root, fixture, phase="rollback_intent")
            result = deployer.recover_deployment(
                state_root,
                action="rollback",
                hooks=deployment_hooks(deployer, fixture),
            )
            self.assertEqual(result["journal"]["phase"], "rolled_back")
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), before)

    def test_finalize_recovery_rechecks_health_http_and_parity(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "recover_deployment"), "crash recovery is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root, _journal = prepare_recovery_state(deployer, root, fixture, phase="swap_intent")
            calls = {"session": 0, "health": 0, "http": 0}

            def session(_target_id, _wp_root):
                calls["session"] += 1
                return {"ok": True}

            def health(_target_id, _wp_root):
                calls["health"] += 1
                return {"ok": True, "site_name": "Voelgoed"}

            def http(_home, _site_name):
                calls["http"] += 1
                return {"ok": True, "status": 200}

            result = deployer.recover_deployment(
                state_root,
                action="finalize",
                hooks=deployment_hooks(
                    deployer,
                    fixture,
                    verify_session=session,
                    verify_health=health,
                    verify_http_health=http,
                ),
            )
            self.assertEqual(result["journal"]["phase"], "finalized")
            self.assertEqual(calls, {"session": 1, "health": 1, "http": 1})
            self.assertTrue((fixture["active"] / "includes/new.php").is_file())
            rollback = root / "app" / (".vgnova-platform-core-rollback-" + "a" * 32)
            self.assertTrue((rollback / "includes/old.php").is_file())

    def test_finalize_recovery_handles_crash_after_old_tree_rename(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "recover_deployment"), "crash recovery is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root, _journal = prepare_recovery_state(
                deployer,
                root,
                fixture,
                phase="finalize_intent",
                old_at_rollback=True,
            )
            result = deployer.recover_deployment(
                state_root,
                action="finalize",
                hooks=deployment_hooks(deployer, fixture),
            )
            self.assertEqual(result["journal"]["phase"], "finalized")
            rollback = root / "app" / (".vgnova-platform-core-rollback-" + "a" * 32)
            self.assertTrue((rollback / "includes/old.php").is_file())

    def test_unknown_recovery_topology_stops_without_moving_trees(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "recover_deployment"), "crash recovery is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            fixture = create_deployment_fixture(root)
            state_root, journal = prepare_recovery_state(deployer, root, fixture, phase="swap_intent")
            stage = Path(journal["stage_path"])
            deployer._remove_owned_tree(stage)
            active_before = deployer.fingerprint_tree(fixture["active"])
            with self.assertRaises(deployer.DeploymentError):
                deployer.recover_deployment(
                    state_root,
                    action="rollback",
                    hooks=deployment_hooks(deployer, fixture),
                )
            self.assertEqual(deployer.fingerprint_tree(fixture["active"]), active_before)
            self.assertTrue((state_root / "in-progress.json").is_file())
            self.assertFalse(Path(journal["rollback_path"]).exists())

    def test_committed_source_provenance_rejects_dirty_deployer_files(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "committed_source_provenance"), "committed-source provenance is not implemented")
        critical = (
            "site-code/bin/deploy-platform-core.py",
            "site-code/runtime-tools/platform_core_deployment.py",
            "site-code/bin/check-platform-core-build.py",
            "site-code/runtime-tools/platform-core-deployment-health.php",
        )
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            subprocess.run(["git", "init", "--quiet", str(root)], check=True)
            for relative in critical:
                path = root / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(f"committed source: {relative}\n", encoding="utf-8")
            subprocess.run(["git", "-C", str(root), "add", *critical], check=True)
            subprocess.run(
                [
                    "git",
                    "-C",
                    str(root),
                    "-c",
                    "user.name=PE-008 test",
                    "-c",
                    "user.email=pe008-test@example.invalid",
                    "commit",
                    "--quiet",
                    "-m",
                    "fixture source",
                ],
                check=True,
            )
            result = deployer.committed_source_provenance(root)
            self.assertEqual(len(result["deployer_commit"]), 40)
            self.assertEqual(len(result["deployer_sha256"]), 64)
            self.assertEqual(len(result["deployer_module_sha256"]), 64)
            self.assertEqual(len(result["verifier_sha256"]), 64)
            (root / critical[1]).write_text("dirty deployer source\n", encoding="utf-8")
            with self.assertRaises(deployer.DeploymentError):
                deployer.committed_source_provenance(root)

    def test_canonical_verifier_uses_exact_commit_and_output_path(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "run_canonical_verifier"), "canonical verifier integration is not implemented")
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            artifact = root / "artifact"
            artifact.mkdir()
            result = subprocess.CompletedProcess([], 0, b"PASS exact output", b"")
            with mock.patch.object(deployer.subprocess, "run", return_value=result) as run:
                deployer.run_canonical_verifier(root, artifact, "b" * 40)
            args, = run.call_args.args
            self.assertEqual(args[0:2], [sys.executable, str(root / "site-code/bin/check-platform-core-build.py")])
            self.assertEqual(args[2:], ["--ref", "b" * 40, "--verify-output", str(artifact)])
            self.assertEqual(run.call_args.kwargs["cwd"], root)
            self.assertFalse(run.call_args.kwargs.get("shell", False))

            run.reset_mock()
            with self.assertRaises(deployer.DeploymentError):
                deployer.run_canonical_verifier(root, artifact, "abbreviated")
            run.assert_not_called()

            with mock.patch.object(
                deployer.subprocess,
                "run",
                return_value=subprocess.CompletedProcess([], 1, b"", b"rejected"),
            ):
                with self.assertRaises(deployer.DeploymentError):
                    deployer.run_canonical_verifier(root, artifact, "b" * 40)

    def test_php_session_helper_requires_coordinator_fd_and_strict_json(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "run_php_health_helper"), "PHP session and health helper invocation is not implemented")
        result = {"ok": True, "target": "vgnova-local-10039"}
        encoded = json.dumps(result, sort_keys=True, separators=(",", ":")) + "\n"
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            completed = subprocess.CompletedProcess([], 0, encoded, "")
            with mock.patch.object(deployer.os, "fstat", return_value=object()) as fstat:
                with mock.patch.object(deployer.subprocess, "run", return_value=completed) as run:
                    self.assertEqual(
                        deployer.run_php_health_helper(root, root, "vgnova-local-10039", mode="session"),
                        result,
                    )
            fstat.assert_called_once_with(3)
            self.assertEqual(run.call_args.kwargs["pass_fds"], (3,))
            self.assertNotIn("DB_PASSWORD", run.call_args.kwargs["env"])

            with mock.patch.object(deployer.os, "fstat", return_value=object()):
                with mock.patch.object(
                    deployer.subprocess,
                    "run",
                    return_value=subprocess.CompletedProcess([], 0, encoded + "warning\n", ""),
                ):
                    with self.assertRaises(deployer.DeploymentError):
                        deployer.run_php_health_helper(root, root, "vgnova-local-10039", mode="health")

    def test_cli_rejects_unknown_target_wrong_root_and_missing_artifact_authority(self) -> None:
        cli = Path(__file__).resolve().parents[2] / "bin/deploy-platform-core.py"
        unknown_target = subprocess.run(
            [sys.executable, str(cli), "status", "--target", "other-target", "--wp-root", "/"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False,
        )
        self.assertNotEqual(unknown_target.returncode, 0)

        wrong_root = subprocess.run(
            [sys.executable, str(cli), "status", "--target", "vgnova-local-10039", "--wp-root", "/"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False,
        )
        self.assertNotEqual(wrong_root.returncode, 0)

        missing_authority = subprocess.run(
            [
                sys.executable,
                str(cli),
                "deploy",
                "--target",
                "vgnova-local-10039",
                "--wp-root",
                "/",
            ],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False,
        )
        self.assertNotEqual(missing_authority.returncode, 0)

    def test_local_http_health_rejects_redirects_and_wrong_site_content(self) -> None:
        deployer = load_deployer()
        self.assertTrue(hasattr(deployer, "verify_local_http"), "local HTTP health verification is not implemented")

        class Response:
            status = 200
            headers = {"Content-Type": "text/html; charset=utf-8"}

            def geturl(self):
                return "http://localhost:10039/"

            def read(self, _limit):
                return b"<!doctype html><html><title>Voelgoed</title></html>"

            def __enter__(self):
                return self

            def __exit__(self, *_args):
                return False

        class Opener:
            def open(self, _request, timeout):
                self.timeout = timeout
                return Response()

        self.assertTrue(deployer.verify_local_http("http://localhost:10039/", "Voelgoed", opener=Opener())["ok"])
        with self.assertRaises(deployer.DeploymentError):
            deployer.verify_local_http("https://example.com/", "Voelgoed", opener=Opener())

        class Redirect:
            def open(self, request, timeout):
                raise urllib.error.HTTPError(request.full_url, 302, "redirect", {}, None)

        with self.assertRaises(deployer.DeploymentError):
            deployer.verify_local_http("http://localhost:10039/", "Voelgoed", opener=Redirect())

        class WrongContent:
            def open(self, request, timeout):
                response = Response()
                response.geturl = lambda: "http://example.invalid/"
                return response

        with self.assertRaises(deployer.DeploymentError):
            deployer.verify_local_http("http://localhost:10039/", "Voelgoed", opener=WrongContent())

        class NonHtml:
            def open(self, _request, timeout):
                response = Response()
                response.headers = {"Content-Type": "application/json"}
                return response

        with self.assertRaises(deployer.DeploymentError):
            deployer.verify_local_http("http://localhost:10039/", "Voelgoed", opener=NonHtml())

        class ServerError:
            def open(self, _request, timeout):
                response = Response()
                response.status = 503
                return response

        with self.assertRaises(deployer.DeploymentError):
            deployer.verify_local_http("http://localhost:10039/", "Voelgoed", opener=ServerError())

        class WrongSite:
            def open(self, _request, timeout):
                response = Response()
                response.read = lambda _limit: b"<!doctype html><html><title>Other Site</title></html>"
                return response

        with self.assertRaises(deployer.DeploymentError):
            deployer.verify_local_http("http://localhost:10039/", "Voelgoed", opener=WrongSite())


if __name__ == "__main__":
    unittest.main()
