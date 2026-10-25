#!/usr/bin/env python3
"""Source-only checks for FCM runtime process and lock-descriptor wiring."""

from __future__ import annotations

import importlib.util
import json
import os
import unittest
from pathlib import Path
from unittest import mock


SCRIPT = Path(__file__).resolve().parents[1] / "fcm-self-featured-integration.py"
SPEC = importlib.util.spec_from_file_location("fcm_self_featured_integration", SCRIPT)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Could not load the FCM integration runner.")
FCM = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(FCM)


class FcmRuntimeProcessTests(unittest.TestCase):
    def test_runtime_uses_local_wp_cli_and_passes_both_lock_descriptors(self) -> None:
        session = {
            "VGNOVA_MUTATION_SESSION_ID": "a" * 32,
            "VGNOVA_MUTATION_TARGET": "vgnova-local-10039",
            "VGNOVA_MUTATION_DB_LOCK": "vgnova.mutation." + "b" * 40,
            "VGNOVA_MUTATION_DB_CONNECTION_ID": "12345",
            "VGNOVA_MUTATION_LOCK_FILE": "/home/test/.local/state/vgnova/runtime-mutation/vgnova-local-10039.lock",
            "VGNOVA_MUTATION_WP_ROOT": "/home/test/Local Sites/voelgoed/app/public",
        }
        result = {"ok": True, "post": {"id": 42}}
        completed = FCM.subprocess.CompletedProcess(["wp"], 0, json.dumps(result), "")

        with mock.patch.dict(os.environ, session, clear=True):
            with mock.patch.object(FCM.os, "fstat", return_value=object()):
                with mock.patch.object(FCM.subprocess, "run", return_value=completed) as run:
                    self.assertEqual(
                        FCM.invoke_runtime(
                            "create",
                            slug="qa-automated-self-featured-integration-" + "c" * 32,
                            run_id="c" * 32,
                        ),
                        result,
                    )

        command, = run.call_args.args
        options = run.call_args.kwargs
        self.assertEqual(
            command,
            [
                "wp",
                "--path=" + session["VGNOVA_MUTATION_WP_ROOT"],
                "--quiet",
                "eval-file",
                str(FCM.CLI_WRAPPER),
            ],
        )
        self.assertEqual(options["pass_fds"], (3, 4))
        self.assertEqual(options["env"]["VGNOVA_FCM_ACTION"], "create")
        self.assertEqual(options["env"]["VGNOVA_FCM_RUN_ID"], "c" * 32)
        self.assertNotIn("novamira", " ".join(command))

    def test_mutation_requires_the_coordinator_file_descriptors(self) -> None:
        environment = {"VGNOVA_MUTATION_WP_ROOT": "/wordpress"}
        with mock.patch.dict(os.environ, environment, clear=True):
            with mock.patch.object(FCM.os, "fstat", side_effect=OSError("closed")):
                with mock.patch.object(FCM.subprocess, "run") as run:
                    with self.assertRaisesRegex(RuntimeError, "lock descriptors are unavailable"):
                        FCM.invoke_runtime("snapshot")
        run.assert_not_called()


if __name__ == "__main__":
    unittest.main()
