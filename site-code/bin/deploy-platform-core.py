#!/usr/bin/env python3
"""Deploy one exact Platform Core artifact under the PE-007 coordinator.

Mutating invocation:
  php site-code/bin/vgnova-runtime-mutate.php --target=vgnova-local-10039 \
    --wp-root="/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/" \
    --purpose="Deploy verified Platform Core artifact" -- \
    python3 site-code/bin/deploy-platform-core.py deploy ...
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path
from typing import Any

REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPOSITORY_ROOT / "site-code/runtime-tools"))

import platform_core_deployment as deployment  # noqa: E402


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Deploy a verified Platform Core artifact to VGNova local.")
    commands = parser.add_subparsers(dest="command", required=True)
    deploy = commands.add_parser("deploy", help="verify, stage, activate, and verify an exact artifact")
    _add_target_arguments(deploy)
    deploy.add_argument("--artifact-dir", required=True, type=Path)
    deploy.add_argument("--source-commit", required=True)
    deploy.add_argument("--manifest-sha256", required=True)

    status = commands.add_parser("status", help="classify unresolved deployment state without changing it")
    _add_target_arguments(status)

    recover = commands.add_parser("recover", help="explicitly recover one unresolved deployment")
    _add_target_arguments(recover)
    recover.add_argument("--action", required=True, choices=("rollback", "finalize"))
    return parser


def _add_target_arguments(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--target", required=True)
    parser.add_argument("--wp-root", required=True, type=Path)


def _read_registered_target(target_id: str) -> dict[str, Any]:
    registry_path = REPOSITORY_ROOT / "site-code/runtime-tools/runtime-mutation-targets.json"
    try:
        registry = json.loads(
            registry_path.read_text(encoding="utf-8"),
            object_pairs_hook=deployment._reject_duplicate_json_keys,
        )
    except (OSError, UnicodeError, json.JSONDecodeError, deployment.DeploymentError) as error:
        raise deployment.DeploymentError("PE-007 target registry could not be read safely.") from error
    if not isinstance(registry, dict) or not isinstance(registry.get("targets"), list):
        raise deployment.DeploymentError("PE-007 target registry has an unsupported shape.")
    matches = [item for item in registry["targets"] if isinstance(item, dict) and item.get("id") == target_id]
    if len(matches) != 1 or matches[0] != deployment.EXPECTED_TARGET:
        raise deployment.DeploymentError("Requested target is not the single registered VGNova target.")
    return matches[0]


def _session(target_id: str, wp_root: Path) -> dict[str, Any]:
    result = deployment.run_php_health_helper(
        REPOSITORY_ROOT,
        wp_root,
        target_id,
        mode="session",
    )
    if result.get("ok") is not True or result.get("session_valid") is not True:
        raise deployment.DeploymentError("PE-007 session helper did not prove the active session.")
    return result


def _health(target_id: str, wp_root: Path) -> dict[str, Any]:
    return deployment.run_php_health_helper(
        REPOSITORY_ROOT,
        wp_root,
        target_id,
        mode="health",
    )


def _hooks() -> deployment.DeploymentHooks:
    return deployment.DeploymentHooks(
        verify_artifact=lambda artifact_dir, source_commit: deployment.run_canonical_verifier(
            REPOSITORY_ROOT,
            artifact_dir,
            source_commit,
        ),
        verify_session=_session,
        verify_health=_health,
        verify_http_health=lambda url, site_name: deployment.verify_local_http(url, site_name),
        provenance=lambda: deployment.committed_source_provenance(REPOSITORY_ROOT),
    )


def _json_result(value: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n")


def _run(arguments: argparse.Namespace) -> dict[str, Any]:
    target = _read_registered_target(arguments.target)
    wp_root = Path(arguments.wp_root)
    plugin_root, _entrypoint = deployment.validate_runtime_target(
        target,
        arguments.target,
        wp_root,
        deployment.REGISTERED_WORDPRESS_ROOT,
        require_active=arguments.command == "deploy",
    )
    state_root = wp_root.parent / ".vgnova-platform-core-deployments"

    if arguments.command == "status":
        if not os.path.lexists(state_root):
            return {"in_progress": False, "recovery_options": []}
        return deployment.status_deployment(
            state_root,
            expected_target=arguments.target,
            expected_wp_root=wp_root,
        )

    if arguments.command == "deploy":
        journal = deployment.deploy_artifact(
            repo_root=REPOSITORY_ROOT,
            target=target,
            target_id=arguments.target,
            wp_root=wp_root,
            registered_wp_root=deployment.REGISTERED_WORDPRESS_ROOT,
            artifact_directory=arguments.artifact_dir,
            source_commit=arguments.source_commit,
            expected_manifest_sha256=arguments.manifest_sha256,
            hooks=_hooks(),
        )
        return {
            "ok": True,
            "deployment_id": journal["deployment_id"],
            "phase": journal["phase"],
            "target": journal["target"],
            "source_commit": journal["source_commit"],
            "manifest_sha256": journal["manifest_sha256"],
            "target_tree_sha256": journal["target_tree_sha256"],
            "old_runtime_tree_sha256": journal["old_runtime_tree_sha256"],
            "rollback_path": journal["rollback_path"],
            "history_path": str(state_root / "history" / f"{journal['deployment_id']}.json"),
            "provenance": journal["provenance"],
        }

    deployment.committed_source_provenance(REPOSITORY_ROOT)
    result = deployment.recover_deployment(
        state_root,
        action=arguments.action,
        hooks=_hooks(),
        expected_target=arguments.target,
        expected_wp_root=wp_root,
    )
    journal = result["journal"]
    return {
        "ok": True,
        "action": arguments.action,
        "deployment_id": journal["deployment_id"],
        "phase": journal["phase"],
        "target": journal["target"],
        "active_path": journal["active_path"],
        "history_path": result["history_path"],
    }


def main() -> int:
    parser = _parser()
    arguments = parser.parse_args()
    try:
        result = _run(arguments)
    except deployment.DeploymentError as error:
        sys.stderr.write(f"Platform Core deployment failed: {error}\n")
        return 1
    except (OSError, ValueError) as error:
        del error
        sys.stderr.write("Platform Core deployment failed: input or filesystem state is invalid.\n")
        return 1
    _json_result(result)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
