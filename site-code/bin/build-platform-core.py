#!/usr/bin/env python3
"""Build one deterministic Voelgoed Platform Core artifact from Git objects."""

from __future__ import annotations

import argparse
import ctypes
import errno
import gzip
import hashlib
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
from collections import Counter
from dataclasses import dataclass
from pathlib import Path, PurePosixPath
from typing import NoReturn


ROOT_PATH = "voelgoed-platform-core"
ENTRYPOINT_PATH = f"{ROOT_PATH}/voelgoed-platform-core.php"
PLUGIN_CLASS_PATH = f"{ROOT_PATH}/includes/Plugin.php"
PATCH_PATH = "site-code/voelgoed-platform-core/integration.patch"
BUILDER_PATH = "site-code/bin/build-platform-core.py"
OVERLAY_PATHS = (
    "site-code/voelgoed-platform-core/includes/Taxonomy",
    "site-code/voelgoed-platform-core/includes/Event",
    "site-code/voelgoed-platform-core/includes/Product",
)
ARCHIVE_NAME = "voelgoed-platform-core.tar.gz"
MANIFEST_NAME = "voelgoed-platform-core.manifest.json"
SUPPORTED_FILE_MODES = {"100644": 0o644, "100755": 0o755}


class BuildFailure(RuntimeError):
    """An input, assembly, validation, or output-safety error."""


@dataclass(frozen=True)
class TreeFile:
    path: str
    mode: str
    object_id: str


def fail(message: str) -> NoReturn:
    raise BuildFailure(message)


def git_bytes(args: list[str], repo_root: Path, *, check: bool = True) -> bytes:
    result = subprocess.run(
        ["git", *args],
        cwd=repo_root,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if check and result.returncode != 0:
        fail(
            f"Git command failed ({result.returncode}): {' '.join(args)}\n"
            f"{result.stderr.decode('utf-8', 'replace')}"
        )
    return result.stdout


def resolve_commit(repo_root: Path, ref: str) -> str:
    raw = git_bytes(
        ["rev-parse", "--verify", "--end-of-options", f"{ref}^{{commit}}"],
        repo_root,
    ).decode("ascii", "strict").strip()
    if re.fullmatch(r"[0-9a-f]{40}", raw) is None:
        fail(f"Ref did not resolve to a full Git commit SHA: {ref}")
    return raw


def tracked_inputs(repo_root: Path, commit: str) -> dict[str, TreeFile]:
    pathspecs = [ROOT_PATH, *OVERLAY_PATHS, PATCH_PATH, BUILDER_PATH]
    listing = git_bytes(
        ["ls-tree", "-r", "-z", "--full-tree", commit, "--", *pathspecs],
        repo_root,
    )
    result: dict[str, TreeFile] = {}
    for record in listing.split(b"\0"):
        if not record:
            continue
        metadata, raw_path = record.split(b"\t", 1)
        mode_bytes, object_type, object_id = metadata.split(b" ", 2)
        try:
            path = raw_path.decode("utf-8", "strict")
            mode = mode_bytes.decode("ascii")
        except UnicodeDecodeError as error:
            fail(f"Invalid UTF-8 or mode in Git tree path: {raw_path!r}: {error}")
        if path in result:
            fail(f"Git tree returned a duplicate path: {path}")
        pure = PurePosixPath(path)
        if (
            pure.is_absolute()
            or ".." in pure.parts
            or "\\" in path
            or "\n" in path
            or "\r" in path
            or pure.as_posix() != path
        ):
            fail(f"Unsafe or non-normalized tracked path: {path!r}")
        if object_type != b"blob":
            fail(f"Unsupported Git object type for {path}: {object_type.decode('ascii', 'replace')}")
        if mode not in SUPPORTED_FILE_MODES:
            fail(f"Unsupported Git file mode {mode} for {path}; only regular 100644/100755 files are allowed.")
        result[path] = TreeFile(path=path, mode=mode, object_id=object_id.decode("ascii"))

    for required in (ENTRYPOINT_PATH, PLUGIN_CLASS_PATH, PATCH_PATH, BUILDER_PATH):
        if required not in result:
            fail(f"Required tracked build input is missing at {commit}: {required}")
    for overlay in OVERLAY_PATHS:
        if not any(path.startswith(overlay + "/") for path in result):
            fail(f"Required canonical overlay directory has no tracked files: {overlay}")
    return result


def read_blob(repo_root: Path, item: TreeFile) -> bytes:
    return git_bytes(["cat-file", "blob", item.object_id], repo_root)


def overlay_files_from_tree(inputs: dict[str, TreeFile]) -> dict[str, TreeFile]:
    files: dict[str, TreeFile] = {}
    for overlay in OVERLAY_PATHS:
        prefix = overlay + "/"
        for path, item in inputs.items():
            if path.startswith(prefix):
                runtime_path = "includes/" + path[len("site-code/voelgoed-platform-core/includes/"):]
                files[runtime_path] = item
    if not any(path.lower().endswith(".php") for path in files):
        fail("Canonical overlays contain no tracked runtime PHP modules.")
    return files


def source_path_for_overlay(runtime_path: str) -> str:
    return "site-code/voelgoed-platform-core/" + runtime_path


def check_executing_builder(repo_root: Path, inputs: dict[str, TreeFile]) -> bytes:
    item = inputs[BUILDER_PATH]
    committed = read_blob(repo_root, item)
    executing = Path(__file__).resolve().read_bytes()
    if hashlib.sha256(executing).digest() != hashlib.sha256(committed).digest():
        fail(
            "Executing builder bytes do not match the tracked builder at the requested commit; "
            "commit the builder before producing a provenance artifact."
        )
    return committed


def safe_output_path(repo_root: Path, raw_output: str) -> Path:
    supplied = Path(raw_output).expanduser()
    if not supplied.is_absolute():
        supplied = Path.cwd() / supplied
    try:
        parent = supplied.parent.resolve(strict=True)
    except OSError as error:
        fail(f"Output parent does not exist or cannot be resolved: {supplied.parent}: {error}")
    if not parent.is_dir():
        fail(f"Output parent is not a directory: {parent}")
    target = parent / supplied.name
    protected = [repo_root / ROOT_PATH, repo_root / "site-code"]
    common_git_dir = git_bytes(["rev-parse", "--git-common-dir"], repo_root).decode().strip()
    git_dir = Path(common_git_dir)
    if not git_dir.is_absolute():
        git_dir = repo_root / git_dir
    protected.append(git_dir.resolve())
    protected.extend(repo_root / path for path in (ROOT_PATH, *OVERLAY_PATHS))
    candidate = target.resolve(strict=False)
    for path in protected:
        protected_path = path.resolve(strict=False)
        if candidate == protected_path or protected_path in candidate.parents:
            fail(f"Unsafe output destination overlaps repository or source input: {candidate}")
    if target.exists() or target.is_symlink():
        fail(f"Output destination already exists; refusing to overwrite: {target}")
    return target


def atomic_publish_no_replace(source: Path, destination: Path) -> None:
    if not sys.platform.startswith("linux"):
        fail("Atomic no-replace output publication requires Linux renameat2; refusing to use a replacing rename.")
    try:
        libc = ctypes.CDLL(None, use_errno=True)
        renameat2 = libc.renameat2
    except (AttributeError, OSError) as error:
        fail(f"Linux renameat2 is unavailable for safe output publication: {error}")

    renameat2.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint]
    renameat2.restype = ctypes.c_int
    result = renameat2(
        -100,  # AT_FDCWD
        os.fsencode(source),
        -100,  # AT_FDCWD
        os.fsencode(destination),
        1,  # RENAME_NOREPLACE
    )
    if result == 0:
        return

    error_number = ctypes.get_errno()
    if error_number == errno.EEXIST:
        fail(f"Output destination already exists or appeared during assembly; refusing to overwrite: {destination}")
    if error_number in (errno.ENOSYS, errno.EINVAL):
        fail("Linux renameat2 with RENAME_NOREPLACE is unavailable; refusing unsafe output publication.")
    raise OSError(error_number, os.strerror(error_number), str(destination))


def write_tree_file(destination: Path, contents: bytes, mode: str) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_bytes(contents)
    destination.chmod(SUPPORTED_FILE_MODES[mode])


def text_file(path: Path, label: str) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError) as error:
        fail(f"Cannot read {label} as UTF-8: {error}")


def clean_bootstrap_guard(
    entrypoint: str,
    plugin_class: str,
    module_classes: set[str],
) -> None:
    if re.search(r"['\"]includes/(?:Taxonomy|Event|Product)/[^'\"]+\.php['\"]", entrypoint):
        fail("Root bootstrap is already integrated: it contains an overlay include path.")
    if re.search(r"^\s*use\s+Voelgoed\\PlatformCore\\(?:Taxonomy|Event|Product)\\", plugin_class, re.M):
        fail("Root bootstrap is already integrated: includes/Plugin.php imports an overlay class.")
    registration_lines = re.findall(r"^.*register_component\s*\(.*$", plugin_class, re.M)
    module_short_names = {value.rsplit("\\", 1)[-1] for value in module_classes}
    for line in registration_lines:
        if any(re.search(rf"\b{re.escape(name)}::class\b", line) for name in module_short_names):
            fail("Root bootstrap is already integrated: includes/Plugin.php registers an overlay class.")


def patch_owned_paths(patch_text: str) -> set[str]:
    paths: set[str] = set()
    for match in re.finditer(r"^(?:---|\+\+\+) [ab]/([^\t\r\n]+)", patch_text, re.M):
        path = match.group(1)
        if path == "/dev/null":
            continue
        paths.add(path)
    allowed = {"voelgoed-platform-core.php", "includes/Plugin.php"}
    if paths != allowed:
        fail(f"integration.patch may change only {sorted(allowed)}; it names {sorted(paths)}.")
    return paths


def patch_integration_markers(patch_text: str) -> tuple[list[str], list[str]]:
    additions = [line[1:] for line in patch_text.splitlines() if line.startswith("+") and not line.startswith("+++")]
    imports = [
        line.strip()
        for line in additions
        if re.fullmatch(r"\s*use\s+Voelgoed\\PlatformCore\\(?:Taxonomy|Event|Product)\\[A-Za-z_][A-Za-z0-9_]*;\s*", line)
    ]
    registrations = [
        line.strip()
        for line in additions
        if "register_component(" in line and re.search(r"::class\s*\);", line)
    ]
    if not imports or not registrations:
        fail("integration.patch does not contain recognizable overlay imports and lifecycle registrations.")
    return imports, registrations


def apply_patch(staging_root: Path, patch_path: Path, patch_text: str) -> None:
    patch_owned_paths(patch_text)
    check = subprocess.run(
        ["git", "apply", "--unidiff-zero", "--check", f"--directory={ROOT_PATH}", str(patch_path)],
        cwd=staging_root,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if check.returncode != 0:
        fail("integration.patch failed git apply --check:\n" + check.stderr.decode("utf-8", "replace"))
    applied = subprocess.run(
        ["git", "apply", "--unidiff-zero", f"--directory={ROOT_PATH}", str(patch_path)],
        cwd=staging_root,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if applied.returncode != 0:
        fail("integration.patch failed to apply:\n" + applied.stderr.decode("utf-8", "replace"))


def parse_include_paths(entrypoint: str) -> list[str]:
    match = re.search(r"\$vgpc_include_paths\s*=\s*\[(.*?)\];", entrypoint, re.S)
    if match is None:
        fail("Assembled entrypoint has no recognizable vgpc_include_paths array.")
    paths = re.findall(r"^\s*'([^']+)'\s*,?\s*$", match.group(1), re.M)
    if not paths:
        fail("Assembled entrypoint include array is empty or not structurally valid.")
    return paths


def php_classes(module_root: Path) -> set[str]:
    classes: set[str] = set()
    for path in module_root.rglob("*.php"):
        contents = text_file(path, f"assembled module {path}")
        namespace_match = re.search(r"^\s*namespace\s+([^;]+);", contents, re.M)
        namespace = namespace_match.group(1).strip() if namespace_match else ""
        for match in re.finditer(
            r"^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b",
            contents,
            re.M,
        ):
            classes.add(f"{namespace}\\{match.group(1)}" if namespace else match.group(1))
    return classes


def php_registration_contexts(method_body: str, registration_lines: list[str]) -> dict[str, tuple[int, bool]]:
    tokenizer = r"""
$source = stream_get_contents(STDIN);
$tokens = token_get_all("<?php\n" . $source);
$result = [];
foreach ($tokens as $token) {
    if (is_array($token)) {
        $result[] = [token_name($token[0]), $token[1]];
    } else {
        $result[] = [null, $token];
    }
}
echo json_encode($result, JSON_THROW_ON_ERROR);
"""
    source_bytes = method_body.encode("utf-8")
    prefix_bytes = len(b"<?php\n")
    targets: dict[int, str] = {}
    for line in registration_lines:
        line_bytes = line.encode("utf-8")
        position = source_bytes.find(line_bytes)
        if position < 0:
            fail(f"Cannot locate lifecycle registration for control-flow validation: {line}")
        call_offset = len(line_bytes) - len(line_bytes.lstrip())
        targets[position + call_offset] = line

    result = subprocess.run(
        ["php", "-r", tokenizer],
        input=source_bytes,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if result.returncode != 0:
        fail(
            "PHP tokenizer could not inspect lifecycle registration control flow: "
            + result.stderr.decode("utf-8", "replace")
        )
    try:
        tokens = json.loads(result.stdout.decode("utf-8", "strict"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        fail(f"PHP tokenizer returned invalid token data: {error}")

    contexts: dict[str, tuple[int, bool]] = {}
    brace_depth = 0
    parenthesis_depth = 0
    pending_control = False
    offset = -prefix_bytes
    control_tokens = {
        "T_IF",
        "T_ELSEIF",
        "T_ELSE",
        "T_FOR",
        "T_FOREACH",
        "T_WHILE",
        "T_DO",
        "T_SWITCH",
        "T_CASE",
        "T_DEFAULT",
        "T_TRY",
        "T_CATCH",
        "T_FINALLY",
        "T_MATCH",
        "T_FN",
        "T_FUNCTION",
        "T_DECLARE",
    }
    alternate_end_tokens = {"T_ENDIF", "T_ENDFOR", "T_ENDFOREACH", "T_ENDWHILE", "T_ENDSWITCH", "T_ENDDECLARE"}

    for token_name, token_text in tokens:
        token_bytes = token_text.encode("utf-8")
        token_end = offset + len(token_bytes)
        for target, line in targets.items():
            if offset <= target < token_end:
                contexts[line] = (brace_depth, pending_control)

        if token_name in control_tokens and brace_depth == 0:
            pending_control = True
        elif token_name in alternate_end_tokens and brace_depth == 0:
            pending_control = False
        elif token_text == "(":
            parenthesis_depth += 1
        elif token_text == ")":
            parenthesis_depth = max(0, parenthesis_depth - 1)
        elif token_text == "{":
            brace_depth += 1
        elif token_text == "}":
            brace_depth = max(0, brace_depth - 1)
            if brace_depth == 0:
                pending_control = False
        elif token_text == ";" and brace_depth == 0 and parenthesis_depth == 0:
            pending_control = False
        offset = token_end

    if set(contexts) != set(registration_lines):
        missing = sorted(set(registration_lines) - set(contexts))
        fail(f"PHP tokenizer could not locate lifecycle registrations: {missing}")
    return contexts


def validate_assembled_bootstrap(
    plugin_root: Path,
    runtime_php_paths: list[str],
    patch_text: str,
) -> None:
    entrypoint_path = plugin_root / "voelgoed-platform-core.php"
    plugin_class_path = plugin_root / "includes/Plugin.php"
    if not entrypoint_path.is_file() or not plugin_class_path.is_file():
        fail("Assembled plugin is missing its entrypoint or includes/Plugin.php.")
    entrypoint = text_file(entrypoint_path, "assembled plugin entrypoint")
    plugin_class = text_file(plugin_class_path, "assembled includes/Plugin.php")

    include_paths = parse_include_paths(entrypoint)
    duplicates = sorted(path for path, count in Counter(include_paths).items() if count > 1)
    if duplicates:
        fail(f"Duplicate bootstrap include paths: {duplicates}")
    for include_path in include_paths:
        pure = PurePosixPath(include_path)
        if pure.is_absolute() or ".." in pure.parts or "\\" in include_path:
            fail(f"Unsafe bootstrap include path: {include_path!r}")
        if not (plugin_root / include_path).is_file():
            fail(f"Bootstrap include path points to a missing assembled file: {include_path}")
    for module_path in sorted(runtime_php_paths, key=lambda item: item.encode("utf-8")):
        count = include_paths.count(module_path)
        if count != 1:
            fail(f"Canonical runtime module must appear exactly once in the bootstrap: {module_path} (found {count}).")

    import_lines = re.findall(r"^\s*use\s+[^;]+;\s*$", plugin_class, re.M)
    if len(import_lines) != len(set(line.strip() for line in import_lines)):
        fail("Duplicate import lines exist in includes/Plugin.php.")
    integration_imports, integration_registrations = patch_integration_markers(patch_text)
    for line in integration_imports:
        if sum(existing.strip() == line for existing in import_lines) != 1:
            fail(f"Integration lifecycle import must appear exactly once: {line}")

    register_method_match = re.search(
        r"\bpublic\s+function\s+register\s*\(\s*\)\s*:\s*void\s*\{",
        plugin_class,
    )
    if register_method_match is None:
        fail("Assembled includes/Plugin.php has no recognizable Plugin::register() method.")
    register_method_tail = plugin_class[register_method_match.end():]
    next_method_match = re.search(
        r"^\s*(?:public|protected|private)\s+function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(",
        register_method_tail,
        re.M,
    )
    if next_method_match is None:
        fail("Cannot identify the end of Plugin::register() for lifecycle validation.")
    register_method_body = register_method_tail[:next_method_match.start()]
    lifecycle_loop = re.search(
        r"^\s*foreach\s*\(\s*\$this->components\s+as\s+\$component\s*\)\s*\{",
        register_method_body,
        re.M,
    )
    if lifecycle_loop is None:
        fail("Plugin::register() has no component lifecycle dispatch loop.")

    lifecycle_setup = register_method_body[:lifecycle_loop.start()]
    idempotency_guard = re.compile(
        r"if\s*\(\s*\$this->registered\s*\)\s*\{\s*return\s*;\s*\}",
        re.S,
    )
    lifecycle_setup = idempotency_guard.sub("", lifecycle_setup, count=1)
    if re.search(r"\b(?:return|throw|exit|die)\b", lifecycle_setup):
        fail("Plugin::register() can exit before lifecycle registrations are dispatched.")

    registration_contexts = php_registration_contexts(register_method_body, integration_registrations)
    for line, (brace_depth, pending_control) in registration_contexts.items():
        if brace_depth != 0 or pending_control:
            fail(
                "Integration lifecycle registrations must be unconditional direct statements "
                f"in Plugin::register(): {line}"
            )

    registration_pattern = re.compile(
        r"register_component\s*\(\s*'([^']+)'\s*,\s*([A-Za-z_\\][A-Za-z0-9_\\]*)::class",
        re.S,
    )
    registrations = registration_pattern.findall(plugin_class)
    keys = [key for key, _ in registrations]
    classes = [class_name for _, class_name in registrations]
    if len(keys) != len(set(keys)) or len(classes) != len(set(classes)):
        fail("Duplicate lifecycle registration keys or classes exist in includes/Plugin.php.")
    for line in integration_registrations:
        if sum(existing.strip() == line for existing in plugin_class.splitlines()) != 1:
            fail(f"Integration lifecycle registration must appear exactly once: {line}")
        position = register_method_body.find(line)
        if position < 0 or position >= lifecycle_loop.start():
            fail(
                "Integration lifecycle registration must execute in Plugin::register() "
                f"before component dispatch: {line}"
            )

    imported_classes: dict[str, str] = {}
    for raw_import in import_lines:
        import_match = re.fullmatch(r"\s*use\s+([^;]+);\s*", raw_import)
        if import_match is None:
            continue
        qualified = import_match.group(1).strip()
        alias_match = re.search(r"\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$", qualified, re.I)
        short_name = alias_match.group(1) if alias_match else qualified.rsplit("\\", 1)[-1]
        imported_classes[short_name] = qualified.split(" as ", 1)[0].strip()

    declared_classes = php_classes(plugin_root / "includes")
    for _, class_name in registrations:
        resolved_name = imported_classes.get(class_name, class_name)
        if resolved_name not in declared_classes:
            fail(f"Lifecycle registration class has no matching assembled module declaration: {class_name}")


def php_contract_check(plugin_root: Path) -> None:
    result = subprocess.run(
        ["php", "-r", "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if result.returncode != 0:
        fail("PHP CLI is required to lint the assembled plugin: " + result.stderr.decode("utf-8", "replace"))
    version_string = result.stdout.decode("ascii", "replace").strip()
    if version_string != "8.5":
        fail(f"PHP 8.5 is the build and lint contract; found PHP {version_string or 'unknown'}.")
    php_paths = sorted(plugin_root.rglob("*.php"), key=lambda path: path.relative_to(plugin_root).as_posix().encode("utf-8"))
    if not php_paths:
        fail("Assembled plugin has no PHP files to lint.")
    for path in php_paths:
        result = subprocess.run(
            ["php", "-l", str(path)],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )
        if result.returncode != 0:
            detail = (result.stdout + result.stderr).decode("utf-8", "replace")
            fail(f"PHP 8.5 lint failed for {path.relative_to(plugin_root)}:\n{detail}")


def assembled_files(plugin_root: Path, modes: dict[str, str]) -> list[tuple[str, str, bytes]]:
    result: list[tuple[str, str, bytes]] = []
    actual_paths = {
        path.relative_to(plugin_root).as_posix()
        for path in plugin_root.rglob("*")
        if path.is_file()
    }
    if actual_paths != set(modes):
        missing = sorted(set(modes) - actual_paths)
        extra = sorted(actual_paths - set(modes))
        fail(f"Staged plugin inventory changed unexpectedly; missing={missing}, extra={extra}.")
    for relative_path in sorted(actual_paths, key=lambda value: value.encode("utf-8")):
        mode = modes[relative_path]
        if mode not in SUPPORTED_FILE_MODES:
            fail(f"Unsupported assembled file mode {mode}: {relative_path}")
        result.append((relative_path, mode, (plugin_root / relative_path).read_bytes()))
    return result


def archive_bytes(files: list[tuple[str, str, bytes]]) -> bytes:
    directories = {ROOT_PATH}
    for relative_path, _, _ in files:
        parent = PurePosixPath(f"{ROOT_PATH}/{relative_path}").parent
        while parent.as_posix() != ".":
            directories.add(parent.as_posix())
            parent = parent.parent
    ordered_directories = [ROOT_PATH] + sorted(
        directories - {ROOT_PATH}, key=lambda value: value.encode("utf-8")
    )
    raw_tar = io.BytesIO()
    with tarfile.open(fileobj=raw_tar, mode="w", format=tarfile.PAX_FORMAT) as archive:
        for directory in ordered_directories:
            info = tarfile.TarInfo(directory + "/")
            info.type = tarfile.DIRTYPE
            info.mode = 0o755
            info.uid = 0
            info.gid = 0
            info.uname = ""
            info.gname = ""
            info.mtime = 0
            info.size = 0
            archive.addfile(info)
        for relative_path, mode, contents in files:
            info = tarfile.TarInfo(f"{ROOT_PATH}/{relative_path}")
            info.type = tarfile.REGTYPE
            info.mode = SUPPORTED_FILE_MODES[mode]
            info.uid = 0
            info.gid = 0
            info.uname = ""
            info.gname = ""
            info.mtime = 0
            info.size = len(contents)
            archive.addfile(info, io.BytesIO(contents))

    output = io.BytesIO()
    with gzip.GzipFile(fileobj=output, mode="wb", filename="", compresslevel=9, mtime=0) as compressed:
        compressed.write(raw_tar.getvalue())
    return output.getvalue()


def build_manifest(
    *,
    commit: str,
    requested_ref: str,
    patch_sha256: str,
    builder_sha256: str,
    files: list[tuple[str, str, bytes]],
    archive: bytes,
) -> dict[str, object]:
    records = [
        {
            "path": path,
            "mode": mode,
            "size": len(contents),
            "sha256": hashlib.sha256(contents).hexdigest(),
        }
        for path, mode, contents in files
    ]
    canonical = "".join(
        f"{record['mode']} {record['sha256']} {record['path']}\n" for record in records
    ).encode("utf-8")
    return {
        "schema_version": 1,
        "artifact_name": ARCHIVE_NAME,
        "source_commit": commit,
        "source_ref_requested": requested_ref,
        "php_contract": {"minimum": "8.5", "ci": "8.5"},
        "root_source_path": ROOT_PATH,
        "overlay_source_paths": list(OVERLAY_PATHS),
        "integration_patch_path": PATCH_PATH,
        "integration_patch_sha256": patch_sha256,
        "builder_path": BUILDER_PATH,
        "builder_sha256": builder_sha256,
        "file_count": len(records),
        "files": records,
        "tree_sha256": hashlib.sha256(canonical).hexdigest(),
        "archive_sha256": hashlib.sha256(archive).hexdigest(),
    }


def serialize_manifest(manifest: dict[str, object]) -> bytes:
    return (json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n").encode("utf-8")


def assemble(
    repo_root: Path,
    commit: str,
    requested_ref: str,
    temporary_root: Path,
) -> tuple[list[tuple[str, str, bytes]], bytes, dict[str, object]]:
    inputs = tracked_inputs(repo_root, commit)
    builder_blob = check_executing_builder(repo_root, inputs)
    overlay_files = overlay_files_from_tree(inputs)
    runtime_php_paths = [path for path in overlay_files if path.lower().endswith(".php")]
    patch_bytes = read_blob(repo_root, inputs[PATCH_PATH])
    try:
        patch_text = patch_bytes.decode("utf-8", "strict")
    except UnicodeDecodeError as error:
        fail(f"integration.patch is not UTF-8: {error}")

    stage = temporary_root / "stage"
    plugin_root = stage / ROOT_PATH
    plugin_root.mkdir(parents=True)
    modes: dict[str, str] = {}

    for path, item in inputs.items():
        if path.startswith(ROOT_PATH + "/"):
            relative = path[len(ROOT_PATH) + 1:]
            contents = read_blob(repo_root, item)
            write_tree_file(plugin_root / relative, contents, item.mode)
            modes[relative] = item.mode

    for runtime_path, item in overlay_files.items():
        if runtime_path in modes:
            fail(f"Canonical overlay collides with a root plugin source path: {runtime_path}")
        source_path = source_path_for_overlay(runtime_path)
        source_item = inputs.get(source_path)
        if source_item is None:
            fail(f"Overlay file is not present in the resolved Git tree: {source_path}")
        write_tree_file(plugin_root / runtime_path, read_blob(repo_root, source_item), source_item.mode)
        modes[runtime_path] = source_item.mode

    overlay_classes: set[str] = set()
    for module_name in ("Taxonomy", "Event", "Product"):
        overlay_classes.update(php_classes(plugin_root / "includes" / module_name))
    clean_bootstrap_guard(
        text_file(plugin_root / "voelgoed-platform-core.php", "root plugin entrypoint"),
        text_file(plugin_root / "includes/Plugin.php", "root plugin includes/Plugin.php"),
        overlay_classes,
    )

    patch_file = temporary_root / "integration.patch"
    patch_file.write_bytes(patch_bytes)
    apply_patch(stage, patch_file, patch_text)
    validate_assembled_bootstrap(plugin_root, runtime_php_paths, patch_text)
    php_contract_check(plugin_root)

    files = assembled_files(plugin_root, modes)
    archive = archive_bytes(files)
    manifest = build_manifest(
        commit=commit,
        requested_ref=requested_ref,
        patch_sha256=hashlib.sha256(patch_bytes).hexdigest(),
        builder_sha256=hashlib.sha256(builder_blob).hexdigest(),
        files=files,
        archive=archive,
    )
    return files, archive, manifest


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ref", required=True, help="Git ref or commit to assemble")
    parser.add_argument("--output", required=True, help="New output directory; must not already exist")
    parser.add_argument(
        "--source-ref-requested",
        help=argparse.SUPPRESS,
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    repo_root_raw = git_bytes(["rev-parse", "--show-toplevel"], Path.cwd()).decode("utf-8").strip()
    repo_root = Path(repo_root_raw).resolve()
    commit = resolve_commit(repo_root, args.ref)
    requested_ref = args.source_ref_requested or args.ref
    output = safe_output_path(repo_root, args.output)
    parent = output.parent
    try:
        with tempfile.TemporaryDirectory(prefix="vgpc-stage-") as temporary_name:
            temporary_root = Path(temporary_name)
            _, archive, manifest = assemble(repo_root, commit, requested_ref, temporary_root)
            with tempfile.TemporaryDirectory(prefix=f".{output.name}.tmp-", dir=parent) as output_temp_name:
                output_temp = Path(output_temp_name)
                (output_temp / ARCHIVE_NAME).write_bytes(archive)
                (output_temp / MANIFEST_NAME).write_bytes(serialize_manifest(manifest))
                try:
                    atomic_publish_no_replace(output_temp, output)
                except BuildFailure:
                    raise
                except OSError as error:
                    fail(f"Cannot atomically publish build output at {output}: {error}")
    except BuildFailure:
        raise

    print(f"source_commit={commit}")
    print(f"file_count={manifest['file_count']}")
    print(f"tree_sha256={manifest['tree_sha256']}")
    print(f"archive_sha256={manifest['archive_sha256']}")
    print(f"output={output}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except BuildFailure as error:
        print(f"FAIL: {error}", file=sys.stderr)
        raise SystemExit(1)
